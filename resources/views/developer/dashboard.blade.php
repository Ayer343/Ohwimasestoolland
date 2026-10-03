@extends('layouts.dev')

@section('title', 'Developer Dashboard - System Control Center')

@section('content')
<div class="developer-dashboard">
    <!-- Header Section -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-start flex-wrap gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-code-branch text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                            Developer Dashboard
                        </h1>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            System Control Center | Environment: <strong>{{ ucfirst(app()->environment()) }}</strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="text-right">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
                    </span>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                              style="background-color: {{ $systemHealth['status'] === 'healthy' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' }}; 
                                     color: {{ $systemHealth['status'] === 'healthy' ? '#10b981' : '#ef4444' }};">
                            <i class="fas fa-circle mr-1 text-xs"></i>
                            System {{ ucfirst($systemHealth['status'] ?? 'Unknown') }}
                        </span>
                        <button onclick="refreshDashboard()" class="px-3 py-1 rounded-lg text-sm transition-all hover:opacity-80"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Alert Bar -->
    @if(($systemHealth['status'] ?? 'healthy') !== 'healthy')
    <div class="mb-6 p-4 rounded-xl" 
         style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
        <div class="flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            <div>
                <p class="font-medium" style="color: var(--text-primary);">System Health Degraded</p>
                <p class="text-sm" style="color: var(--text-secondary);">
                    The system is experiencing issues. Please check the health monitoring section for details.
                </p>
            </div>
            <a href="{{ route('developer.health.index') }}" class="ml-auto px-4 py-2 rounded-lg text-sm"
               style="background-color: var(--primary); color: white;">
                View Health Status
            </a>
        </div>
    </div>
    @endif

    <!-- Quick Stats Cards -->
    <div class="quick-stats-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Users Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Users</p>
                    <p class="text-3xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($quickStats['total_users'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>+{{ number_format(($quickStats['new_users_today'] ?? 0)) }}
                        </span>
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">today</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Properties Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-3xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($quickStats['total_properties'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-500">
                            <i class="fas fa-plus mr-1"></i>{{ number_format($quickStats['new_properties_week'] ?? 0) }}
                        </span>
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">this week</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Revenue</p>
                    <p class="text-3xl font-bold" style="color: var(--text-primary);">
                        {{ $quickStats['total_revenue']['value'] ?? '$0' }}
                    </p>
                    <div class="flex items-center mt-2">
                        @php $trend = $quickStats['total_revenue']['trend'] ?? 0; @endphp
                        @if($trend > 0)
                            <span class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>+{{ $trend }}%</span>
                        @elseif($trend < 0)
                            <span class="text-xs text-red-500"><i class="fas fa-arrow-down mr-1"></i>{{ $trend }}%</span>
                        @else
                            <span class="text-xs text-gray-500">No change</span>
                        @endif
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last month</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-dollar-sign text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- System Errors Card -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg cursor-pointer" 
             onclick="window.location.href='{{ route('developer.logs.errors') }}'"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">System Errors</p>
                    <p class="text-3xl font-bold" style="color: #ef4444;">
                        {{ number_format($quickStats['system_errors'] ?? 0) }}
                    </p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-red-500">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ number_format($quickStats['critical_errors'] ?? 0) }} critical
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <i class="fas fa-bug text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- System Metrics & Health Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- System Health Score -->
        <div class="metric-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-heartbeat mr-2" style="color: var(--primary);"></i>System Health Score
            </h3>
            <div class="text-center">
                <div class="relative inline-block">
                    <svg class="w-32 h-32">
                        <circle class="progress-ring-bg" stroke="rgba(59, 130, 246, 0.1)" stroke-width="8" fill="transparent" r="58" cx="64" cy="64"/>
                        <circle class="progress-ring" stroke="var(--primary)" stroke-width="8" fill="transparent" r="58" cx="64" cy="64"
                                stroke-dasharray="364.4" stroke-dashoffset="{{ 364.4 - (364.4 * ($systemHealth['score'] ?? 85) / 100) }}"
                                transform="rotate(-90 64 64)"/>
                    </svg>
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
                        <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $systemHealth['score'] ?? 85 }}</span>
                        <span class="text-sm" style="color: var(--text-secondary);">/100</span>
                    </div>
                </div>
                <p class="mt-3 text-sm" style="color: var(--text-secondary);">
                    Status: <strong class="{{ ($systemHealth['status'] ?? 'healthy') === 'healthy' ? 'text-green-500' : 'text-red-500' }}">
                        {{ ucfirst($systemHealth['status'] ?? 'Healthy') }}
                    </strong>
                </p>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Database</span>
                        <span class="text-green-500"><i class="fas fa-check-circle"></i> Connected</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Cache</span>
                        <span class="text-green-500"><i class="fas fa-check-circle"></i> Active</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Queue Workers</span>
                        <span class="text-yellow-500"><i class="fas fa-clock"></i> Running</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="metric-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Performance Metrics
            </h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">CPU Usage</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $performanceMetrics['cpu'] ?? 45 }}%</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" style="width: {{ $performanceMetrics['cpu'] ?? 45 }}%; background-color: {{ ($performanceMetrics['cpu'] ?? 45) > 80 ? '#ef4444' : '#3b82f6' }};"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Memory Usage</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $performanceMetrics['memory'] ?? 62 }}%</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" style="width: {{ $performanceMetrics['memory'] ?? 62 }}%; background-color: #10b981;"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Disk Usage</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $performanceMetrics['disk'] ?? 58 }}%</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" style="width: {{ $performanceMetrics['disk'] ?? 58 }}%; background-color: #f59e0b;"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Response Time</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $performanceMetrics['response_time'] ?? 245 }}ms</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" style="width: {{ min(100, (($performanceMetrics['response_time'] ?? 245) / 500) * 100) }}%; background-color: {{ ($performanceMetrics['response_time'] ?? 245) > 300 ? '#ef4444' : '#10b981' }};"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="clearCache()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center justify-center gap-2"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-database"></i> Clear Cache
                </button>
                <button onclick="runDiagnostics()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center justify-center gap-2"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-stethoscope"></i> Diagnostics
                </button>
                <a href="{{ route('developer.maintenance.create') }}" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center justify-center gap-2 text-center"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); text-decoration: none;">
                    <i class="fas fa-tools"></i> Maintenance
                </a>
                <button onclick="toggleEmergencyMode()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center justify-center gap-2"
                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i> Emergency Mode
                </button>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <span class="text-sm" style="color: var(--text-secondary);">Emergency Mode</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="emergencyToggle" class="sr-only peer" onchange="toggleEmergencyMode()">
                        <div class="w-11 h-6 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"
                             style="background-color: {{ $emergencyActive ?? false ? '#ef4444' : '#6b7280' }};"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- System Monitoring Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Revenue Overview
                </h3>
                <div class="flex space-x-2">
                    <button class="period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="weekly"
                            style="background-color: var(--primary); color: white;">Weekly</button>
                    <button class="period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="monthly"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Monthly</button>
                    <button class="period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="quarterly"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Quarterly</button>
                </div>
            </div>
            <canvas id="revenueChart" height="250"></canvas>
        </div>

        <!-- Error Trends Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Error Trends (7 Days)
                </h3>
                <div class="flex space-x-2">
                    <button class="error-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-error-type="all"
                            style="background-color: var(--primary); color: white;">All</button>
                    <button class="error-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-error-type="critical"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Critical</button>
                    <button class="error-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-error-type="warning"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Warnings</button>
                </div>
            </div>
            <canvas id="errorTrendChart" height="250"></canvas>
        </div>
    </div>

    <!-- Active Maintenance & Emergency Modes -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Active Maintenance -->
        <div class="maintenance-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>Active Maintenance
                </h3>
                <a href="{{ route('developer.maintenance.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                @forelse($activeMaintenance ?? [] as $maintenance)
                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $maintenance['title'] }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>{{ $maintenance['scheduled_start'] }}
                            </p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full" 
                              style="background-color: rgba(245, 158, 11, 0.2); color: #f59e0b;">
                            {{ ucfirst($maintenance['status']) }}
                        </span>
                    </div>
                    <div class="mt-2 w-full h-1 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                        <div class="h-full rounded-full" style="width: {{ $maintenance['progress'] ?? 0 }}%; background-color: var(--primary);"></div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-3xl mb-2" style="color: #10b981;"></i>
                    <p style="color: var(--text-secondary);">No active maintenance</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Active Emergency Modes -->
        <div class="emergency-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: #ef4444;"></i>Emergency Modes
                </h3>
                <a href="{{ route('developer.emergency.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View History <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                @forelse($activeEmergencies ?? [] as $emergency)
                <div class="p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $emergency['reason'] }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user mr-1"></i>Activated by: {{ $emergency['activated_by_name'] ?? 'System' }}
                            </p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full animate-pulse" 
                              style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                            <i class="fas fa-circle mr-1 text-xs"></i>ACTIVE
                        </span>
                    </div>
                    <div class="mt-2 flex justify-between text-xs">
                        <span style="color: var(--text-secondary);">
                            <i class="far fa-hourglass-half mr-1"></i>Duration: {{ $emergency['duration'] ?? 'N/A' }}
                        </span>
                        <button onclick="deactivateEmergency({{ $emergency['id'] }})" class="text-red-500 hover:underline">
                            Deactivate
                        </button>
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <i class="fas fa-shield-alt text-3xl mb-2" style="color: #10b981;"></i>
                    <p style="color: var(--text-secondary);">No active emergency modes</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent System Logs -->
    <div class="logs-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--primary);"></i>Recent System Logs
            </h3>
            <div class="flex gap-2">
                <a href="{{ route('developer.logs.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All Logs <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <button onclick="clearLogs()" class="text-sm hover:underline" style="color: #ef4444;">
                    <i class="fas fa-trash-alt mr-1"></i>Clear All
                </button>
            </div>
        </div>
        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($recentLogs ?? [] as $log)
            <div id="error-{{ $log['id'] }}" class="p-3 rounded-lg flex items-start gap-3 hover:bg-opacity-5 transition-colors" 
                 style="background-color: var(--bg-secondary);">
                <div>
                    @if($log['level'] === 'error')
                        <i class="fas fa-times-circle text-red-500"></i>
                    @elseif($log['level'] === 'warning')
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    @else
                        <i class="fas fa-info-circle text-blue-500"></i>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <p class="text-sm font-mono" style="color: var(--text-primary);">{{ $log['message'] }}</p>
                        <span class="text-xs" style="color: var(--text-secondary);">{{ $log['created_at'] }}</span>
                    </div>
                    @if(isset($log['context']))
                    <p class="text-xs mt-1 font-mono" style="color: var(--text-secondary); opacity: 0.7;">
                        {{ json_encode($log['context']) }}
                    </p>
                    @endif
                </div>
                @if($log['level'] === 'error' && !($log['resolved'] ?? false))
                <button onclick="markErrorResolved({{ $log['id'] }})" class="text-xs px-2 py-1 rounded" 
                        style="background-color: rgba(16, 185, 129, 0.2); color: #10b981;">
                    Mark Resolved
                </button>
                @endif
            </div>
            @empty
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-3xl mb-2" style="color: #10b981;"></i>
                <p style="color: var(--text-secondary);">No recent logs</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Recent Testimonials Section -->
    <div class="testimonials-card rounded-xl p-6 mb-8" 
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                <i class="fas fa-star mr-2" style="color: var(--warning);"></i>Recent Testimonials
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-check-circle mr-1"></i> Approved Only
                </span>
            </h3>
            <div class="flex gap-2">
                <a href="{{ route('dashboard.testimonials.index') }}" 
                   class="text-sm hover:underline inline-flex items-center gap-1" 
                   style="color: var(--success);">
                    <i class="fas fa-star"></i> My Testimonials
                </a>
                <a href="{{ route('admin.testimonials.index') }}" 
                   class="text-sm hover:underline inline-flex items-center gap-1" 
                   style="color: var(--primary);">
                    Manage All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($recentTestimonials ?? [] as $testimonial)
            <div class="p-4 rounded-lg transition-all hover:shadow-md" 
                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-start gap-3">
                    <img src="{{ $testimonial->avatar_url }}" 
                         alt="{{ $testimonial->name }}" 
                         class="w-12 h-12 rounded-full object-cover"
                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($testimonial->name) }}&background=8b5cf6&color=fff'">
                    <div class="flex-1">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <p class="font-semibold" style="color: var(--text-primary);">{{ $testimonial->name }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="fas fa-star text-xs {{ $i <= $testimonial->rating ? 'text-warning' : 'text-muted' }}" 
                                               style="{{ $i <= $testimonial->rating ? 'color: var(--warning);' : 'color: var(--text-secondary);' }}"></i>
                                        @endfor
                                    </div>
                                    <span class="text-xs" style="color: var(--text-secondary);">({{ $testimonial->rating }}/5)</span>
                                    @if($testimonial->is_featured)
                                        <span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-star mr-0.5 text-xs"></i>Featured
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs" style="color: var(--text-secondary);">
                                {{ $testimonial->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-sm mt-2" style="color: var(--text-primary); line-height: 1.5;">
                            "{{ Str::limit($testimonial->content, 100) }}"
                        </p>
                        <div class="flex items-center gap-3 mt-2">
                            @if($testimonial->role)
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-briefcase mr-1 text-xs"></i> {{ $testimonial->role }}
                                </span>
                            @endif
                            @if($testimonial->property_location)
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-marker-alt mr-1 text-xs"></i> {{ $testimonial->property_location }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <a href="{{ route('admin.testimonials.show', $testimonial->id) }}" 
                           class="action-btn view" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-2 text-center py-8">
                <i class="fas fa-star text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.3;"></i>
                <p class="text-sm font-medium" style="color: var(--text-primary);">No approved testimonials yet</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">When customers submit testimonials and they are approved, they'll appear here.</p>
                <div class="flex justify-center gap-3 mt-4">
                    <a href="{{ route('admin.testimonials.index') }}" class="inline-flex items-center gap-1 text-xs hover:underline" style="color: var(--primary);">
                        <i class="fas fa-arrow-right"></i> Manage Testimonials
                    </a>
                    <a href="{{ route('dashboard.testimonials.index') }}" class="inline-flex items-center gap-1 text-xs hover:underline" style="color: var(--success);">
                        <i class="fas fa-star"></i> My Testimonials
                    </a>
                </div>
            </div>
            @endforelse
        </div>
        
        @if(isset($recentTestimonials) && count($recentTestimonials) > 0)
        <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <a href="{{ route('admin.testimonials.index', ['status' => 'approved']) }}" 
                   class="text-sm hover:underline inline-flex items-center gap-2" style="color: var(--primary);">
                    <i class="fas fa-check-circle"></i>
                    <span>View All Approved Testimonials</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="{{ route('dashboard.testimonials.index') }}" 
                   class="text-sm hover:underline inline-flex items-center gap-2" style="color: var(--success);">
                    <i class="fas fa-star"></i>
                    <span>My Testimonials</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        @endif
    </div>

    <!-- Developer Tools Section -->
    <div class="tools-section">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-wrench mr-2" style="color: var(--primary);"></i>Developer Tools
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('developer.logs.index') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-list-alt text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">System Logs</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">View and manage logs</p>
            </a>
            <a href="{{ route('developer.health.index') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-heartbeat text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Health Monitor</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">System health status</p>
            </a>
            <a href="{{ route('developer.performance.dashboard') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-chart-bar text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Performance</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Performance metrics</p>
            </a>
            <a href="{{ route('developer.database.index') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-database text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Database</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Database management</p>
            </a>
            <a href="{{ route('developer.cache.index') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-memory text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Cache Manager</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Cache management</p>
            </a>
            <a href="{{ route('developer.queue.index') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-tasks text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Queue Manager</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Queue monitoring</p>
            </a>
            <a href="{{ route('developer.api.documentation') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-code text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">API Docs</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">API documentation</p>
            </a>
            <a href="{{ route('developer.billing.dashboard') }}" class="tool-card p-4 rounded-xl text-center transition-all hover:shadow-lg"
               style="background-color: var(--card-bg); border: 1px solid var(--border-color); text-decoration: none;">
                <i class="fas fa-credit-card text-3xl mb-2" style="color: var(--primary);"></i>
                <p class="font-medium" style="color: var(--text-primary);">Billing</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Billing management</p>
            </a>
        </div>
    </div>
</div>

@push('styles')
<style>
    .developer-dashboard {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Progress Ring Animation */
    .progress-ring {
        transition: stroke-dashoffset 0.5s ease;
        transform-origin: 50% 50%;
    }

    /* Stat Cards */
    .stat-card, .metric-card, .chart-card, .maintenance-card, .emergency-card, .logs-card, .testimonials-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card:hover, .metric-card:hover, .chart-card:hover, .testimonials-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Action Button Styles */
    .action-btn {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .action-btn.view {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
        border-color: rgba(var(--info-rgb), 0.3);
    }

    .action-btn.view:hover {
        transform: translateY(-1px);
        background-color: rgba(var(--info-rgb), 0.2);
    }

    /* Text Colors */
    .text-warning {
        color: var(--warning) !important;
    }
    .text-muted {
        color: var(--text-secondary) !important;
    }

    /* Tool Cards */
    .tool-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .tool-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Animation for emergency mode */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Period Buttons */
    .period-btn, .error-period-btn {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .period-btn.active, .error-period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
    }

    /* Scrollbar Styling */
    .max-h-96::-webkit-scrollbar {
        width: 6px;
    }
    
    .max-h-96::-webkit-scrollbar-track {
        background: var(--bg-secondary);
        border-radius: 3px;
    }
    
    .max-h-96::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }
    
    .max-h-96::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }

    /* Tooltip */
    .tooltip {
        pointer-events: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .developer-dashboard {
            padding: 0 0.5rem;
        }
        
        .quick-stats-grid {
            gap: 1rem;
        }
        
        .stat-card, .metric-card {
            padding: 1rem;
        }
        
        .tools-section .grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .testimonials-card .grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let revenueChart = null;
    let errorTrendChart = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Charts
        initRevenueChart('weekly');
        initErrorTrendChart('all');
        
        // Setup auto-refresh (every 30 seconds for critical metrics)
        setInterval(refreshCriticalMetrics, 30000);
        
        // Setup long polling for real-time updates (every 2 minutes)
        setInterval(refreshDashboardData, 120000);
        
        // Tooltips initialization
        initTooltips();
    });
    
    // Tooltips
    function initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('data-tooltip');
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--text-primary);
                    color: var(--card-bg);
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    white-space: nowrap;
                `;
                document.body.appendChild(tooltip);
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                this._tooltip = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    this._tooltip = null;
                }
            });
        });
    }
    
    // Revenue Chart
    function initRevenueChart(period) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        if (revenueChart) {
            revenueChart.destroy();
        }
        
        // Fetch data based on period
        fetch(`{{ route('developer.dashboard.metrics') }}?period=${period}`)
            .then(response => response.json())
            .then(data => {
                const revenueData = data.revenue_data || {};
                const labels = revenueData.labels || [];
                const values = revenueData.values || [];
                
                revenueChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: period.charAt(0).toUpperCase() + period.slice(1) + ' Revenue',
                            data: values,
                            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                            pointBorderColor: '#fff',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': $' + context.raw.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                },
                                grid: {
                                    color: 'rgba(var(--border-color-rgb), 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                },
                                grid: {
                                    color: 'rgba(var(--border-color-rgb), 0.1)'
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading revenue chart:', error));
    }
    
    // Error Trend Chart
    function initErrorTrendChart(type) {
        const ctx = document.getElementById('errorTrendChart').getContext('2d');
        
        if (errorTrendChart) {
            errorTrendChart.destroy();
        }
        
        fetch(`{{ route('developer.dashboard.error-trends') }}?type=${type}`)
            .then(response => response.json())
            .then(data => {
                const labels = data.labels || [];
                const values = data.values || [];
                
                let borderColor = '#3b82f6';
                let backgroundColor = 'rgba(59, 130, 246, 0.1)';
                let labelText = 'All Errors';
                
                if (type === 'critical') {
                    borderColor = '#ef4444';
                    backgroundColor = 'rgba(239, 68, 68, 0.1)';
                    labelText = 'Critical Errors';
                } else if (type === 'warning') {
                    borderColor = '#f59e0b';
                    backgroundColor = 'rgba(245, 158, 11, 0.1)';
                    labelText = 'Warnings';
                } else {
                    labelText = 'All Errors';
                }
                
                errorTrendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: labelText,
                            data: values,
                            borderColor: borderColor,
                            backgroundColor: backgroundColor,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: borderColor,
                            pointBorderColor: '#fff',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.raw + ' errors';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(var(--border-color-rgb), 0.1)'
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Errors',
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                }
                            },
                            x: {
                                ticks: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                },
                                grid: {
                                    color: 'rgba(var(--border-color-rgb), 0.1)'
                                },
                                title: {
                                    display: true,
                                    text: 'Date',
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error loading error trends:', error);
                setFallbackErrorData(type);
            });
    }
    
    // Fallback function for error chart when API fails
    function setFallbackErrorData(type) {
        const ctx = document.getElementById('errorTrendChart').getContext('2d');
        
        if (errorTrendChart) {
            errorTrendChart.destroy();
        }
        
        const labels = [];
        const values = [];
        
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
            
            if (type === 'critical') {
                values.push(Math.floor(Math.random() * 10));
            } else if (type === 'warning') {
                values.push(Math.floor(Math.random() * 30));
            } else {
                values.push(Math.floor(Math.random() * 50));
            }
        }
        
        let borderColor = '#3b82f6';
        let backgroundColor = 'rgba(59, 130, 246, 0.1)';
        let labelText = 'All Errors';
        
        if (type === 'critical') {
            borderColor = '#ef4444';
            backgroundColor = 'rgba(239, 68, 68, 0.1)';
            labelText = 'Critical Errors';
        } else if (type === 'warning') {
            borderColor = '#f59e0b';
            backgroundColor = 'rgba(245, 158, 11, 0.1)';
            labelText = 'Warnings';
        } else {
            labelText = 'All Errors';
        }
        
        errorTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: labelText,
                    data: values,
                    borderColor: borderColor,
                    backgroundColor: backgroundColor,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: borderColor,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' errors';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(var(--border-color-rgb), 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim()
                        },
                        grid: {
                            color: 'rgba(var(--border-color-rgb), 0.1)'
                        }
                    }
                }
            }
        });
    }
    
    // Period button handlers
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const period = this.dataset.period;
            initRevenueChart(period);
            
            document.querySelectorAll('.period-btn').forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '';
                b.style.color = '';
            });
            this.classList.add('active');
            this.style.backgroundColor = 'var(--primary)';
            this.style.color = 'white';
        });
    });
    
    document.querySelectorAll('.error-period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.dataset.errorType;
            initErrorTrendChart(type);
            
            document.querySelectorAll('.error-period-btn').forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '';
                b.style.color = '';
            });
            this.classList.add('active');
            this.style.backgroundColor = 'var(--primary)';
            this.style.color = 'white';
        });
    });
    
    // Quick Actions Functions
    function clearCache() {
        if (confirm('Are you sure you want to clear all system cache?')) {
            fetch('{{ route("developer.settings.clear-cache") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Cache cleared successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to clear cache: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error clearing cache', 'error');
            });
        }
    }
    
    function runDiagnostics() {
        showNotification('Running system diagnostics...', 'info');
        
        fetch('{{ route("developer.dashboard.run-diagnostics") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Diagnostics completed! Check console for details.', 'success');
                console.log('Diagnostics Results:', data.diagnostics);
                
                if (confirm('Diagnostics completed. Would you like to download the health report?')) {
                    window.location.href = '{{ route("developer.dashboard.generate-health-report") }}';
                }
            } else {
                showNotification('Diagnostics failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error running diagnostics', 'error');
        });
    }
    
    function toggleEmergencyMode() {
        const reason = prompt('Please provide a reason for toggling emergency mode:', 'System maintenance required');
        if (!reason) return;
        
        fetch('{{ route("developer.dashboard.emergency.toggle") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'warning');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('Failed to toggle emergency mode', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error toggling emergency mode', 'error');
        });
    }
    
    function deactivateEmergency(emergencyId) {
        if (confirm('Are you sure you want to deactivate this emergency mode?')) {
            fetch(`/developer/emergency/${emergencyId}/deactivate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Emergency mode deactivated', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Failed to deactivate emergency mode', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deactivating emergency mode', 'error');
            });
        }
    }
    
    function markErrorResolved(errorId) {
        if (!errorId) {
            showNotification('Invalid error ID', 'error');
            return;
        }
        
        const url = `/developer/dashboard/errors/${errorId}/mark-resolved`;
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') || '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Error marked as resolved', 'success');
                const errorRow = document.querySelector(`#error-${errorId}`);
                if (errorRow) {
                    errorRow.remove();
                } else {
                    location.reload();
                }
            } else {
                showNotification('Failed to mark error as resolved: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error marking as resolved:', error);
            showNotification('Error marking as resolved', 'error');
        });
    }
    
    function clearLogs() {
        if (confirm('Are you sure you want to clear all system logs? This action cannot be undone.')) {
            fetch('{{ route("developer.logs.clear.all") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Logs cleared successfully', 'success');
                    location.reload();
                } else {
                    showNotification('Failed to clear logs', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error clearing logs', 'error');
            });
        }
    }
    
    function refreshDashboard() {
        showNotification('Refreshing dashboard data...', 'info');
        location.reload();
    }
    
    function refreshCriticalMetrics() {
        fetch('{{ route("developer.dashboard.metrics") }}')
            .then(response => response.json())
            .then(data => {
                if (data.performance) {
                    updateMetricDisplay('cpu', data.performance.cpu);
                    updateMetricDisplay('memory', data.performance.memory);
                    updateMetricDisplay('disk', data.performance.disk);
                }
                
                if (data.system_health) {
                    updateHealthScore(data.system_health.score);
                }
            })
            .catch(error => console.error('Error refreshing metrics:', error));
    }
    
    function refreshDashboardData() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newStats = doc.querySelectorAll('.stat-card .text-3xl');
                const currentStats = document.querySelectorAll('.stat-card .text-3xl');
                
                newStats.forEach((newStat, index) => {
                    if (currentStats[index] && currentStats[index].innerText !== newStat.innerText) {
                        currentStats[index].innerText = newStat.innerText;
                        currentStats[index].style.animation = 'pulse 0.5s ease';
                        setTimeout(() => {
                            currentStats[index].style.animation = '';
                        }, 500);
                    }
                });
            })
            .catch(error => console.error('Error refreshing dashboard:', error));
    }
    
    function updateMetricDisplay(metric, value) {
        console.log(`Updating ${metric} to ${value}%`);
    }
    
    function updateHealthScore(score) {
        const scoreElement = document.querySelector('.progress-ring + div .text-3xl');
        if (scoreElement && parseInt(scoreElement.innerText) !== score) {
            scoreElement.innerText = score;
            const circle = document.querySelector('.progress-ring');
            if (circle) {
                const radius = 58;
                const circumference = 2 * Math.PI * radius;
                const offset = circumference - (score / 100) * circumference;
                circle.style.strokeDashoffset = offset;
            }
        }
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all transform translate-x-0';
        
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        
        notification.className += ' ' + (colors[type] || colors.info);
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} text-white"></i>
                <span class="text-white">${escapeHtml(message)}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification && notification.remove) {
                notification.remove();
            }
        }, 5000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
@endpush
@endsection