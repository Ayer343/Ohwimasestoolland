@extends('layouts.dev')

@section('title', 'Performance Dashboard - Developer')

@section('content')
<div class="performance-dashboard">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Performance Dashboard
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Real-time system performance metrics and analytics
                </p>
            </div>
            <div class="flex gap-3">
                <button onclick="refreshPerformanceData()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Performance Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-tachometer-alt text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Response Time</h3>
            </div>
            <p class="text-2xl font-bold" id="response-time" style="color: var(--text-primary);">245ms</p>
            <div class="flex items-center mt-2">
                <span id="response-trend" class="text-xs text-green-500"><i class="fas fa-arrow-down mr-1"></i>12%</span>
                <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last hour</span>
            </div>
        </div>

        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-rocket text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Throughput</h3>
            </div>
            <p class="text-2xl font-bold" id="throughput" style="color: var(--text-primary);">912 req/min</p>
            <div class="flex items-center mt-2">
                <span id="throughput-trend" class="text-xs text-green-500"><i class="fas fa-arrow-up mr-1"></i>8%</span>
                <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last hour</span>
            </div>
        </div>

        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-percent text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Error Rate</h3>
            </div>
            <p class="text-2xl font-bold" id="error-rate" style="color: var(--text-primary);">0.86%</p>
            <div class="flex items-center mt-2">
                <span id="error-trend" class="text-xs text-green-500"><i class="fas fa-arrow-down mr-1"></i>0.2%</span>
                <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last hour</span>
            </div>
        </div>

        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-database text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Query Time</h3>
            </div>
            <p class="text-2xl font-bold" id="query-time" style="color: var(--text-primary);">45ms</p>
            <div class="flex items-center mt-2">
                <span id="query-trend" class="text-xs text-yellow-500"><i class="fas fa-arrow-up mr-1"></i>5%</span>
                <span class="text-xs ml-2" style="color: var(--text-secondary);">vs last hour</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Response Time Chart -->
        <div class="chart-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Response Time Trend
                </h3>
                <div class="flex space-x-2">
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="response" data-period="hour"
                            style="background-color: var(--primary); color: white;">Last Hour</button>
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="response" data-period="day"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Last 24h</button>
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="response" data-period="week"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Last Week</button>
                </div>
            </div>
            <canvas id="responseTimeChart" height="250"></canvas>
        </div>

        <!-- Throughput Chart -->
        <div class="chart-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Throughput (Requests/Min)
                </h3>
                <div class="flex space-x-2">
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-chart="throughput" data-period="hour"
                            style="background-color: var(--primary); color: white;">Last Hour</button>
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="throughput" data-period="day"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Last 24h</button>
                    <button class="time-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-chart="throughput" data-period="week"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">Last Week</button>
                </div>
            </div>
            <canvas id="throughputChart" height="250"></canvas>
        </div>
    </div>

    <!-- Database Performance Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Slow Queries -->
        <div class="queries-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-hourglass-half mr-2" style="color: var(--primary);"></i>Slow Queries (Top 5)
                </h3>
                <button onclick="analyzeSlowQueries()" class="text-sm hover:underline" style="color: var(--primary);">
                    Analyze
                </button>
            </div>
            <div class="space-y-3" id="slow-queries-list">
                <div class="text-center py-8" id="slow-queries-loading">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading slow queries...</p>
                </div>
            </div>
        </div>

        <!-- API Performance -->
        <div class="api-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-code mr-2" style="color: var(--primary);"></i>API Endpoint Performance
                </h3>
                <button onclick="refreshApiPerformance()" class="text-sm hover:underline" style="color: var(--primary);">
                    Refresh
                </button>
            </div>
            <div class="space-y-3" id="api-performance-list">
                <div class="text-center py-8" id="api-performance-loading">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading API metrics...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Resource Usage Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- CPU/Memory Usage Over Time -->
        <div class="resource-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-microchip mr-2" style="color: var(--primary);"></i>Resource Usage Over Time
            </h3>
            <canvas id="resourceUsageChart" height="250"></canvas>
        </div>

        <!-- Cache Performance -->
        <div class="cache-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-memory mr-2" style="color: var(--primary);"></i>Cache Performance
            </h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Hit Rate</span>
                        <span class="font-semibold" id="cache-hit-rate" style="color: var(--text-primary);">85.5%</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" id="cache-hit-bar" style="width: 85.5%; background-color: #10b981;"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Miss Rate</span>
                        <span class="font-semibold" id="cache-miss-rate" style="color: var(--text-primary);">14.5%</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" id="cache-miss-bar" style="width: 14.5%; background-color: #ef4444;"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Memory Usage</span>
                        <span class="font-semibold" id="cache-memory" style="color: var(--text-primary);">512 MB</span>
                    </div>
                    <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full" id="cache-memory-bar" style="width: 65%; background-color: #f59e0b;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Suggestions -->
    <div class="suggestions-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-lightbulb mr-2" style="color: var(--primary);"></i>Optimization Suggestions
        </h3>
        <div class="space-y-3" id="optimization-suggestions">
            <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-database text-yellow-500 mt-1"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Database Query Optimization</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">Several slow queries detected. Consider adding indexes to frequently queried columns.</p>
                    </div>
                </div>
            </div>
            <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-tachometer-alt text-yellow-500 mt-1"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Cache Strategy Improvement</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">Cache hit rate could be improved. Consider caching more frequently accessed data.</p>
                    </div>
                </div>
            </div>
            <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-clock text-yellow-500 mt-1"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Queue Worker Scaling</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">Queue backlog detected. Consider increasing worker processes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .performance-dashboard {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .metric-card, .chart-card, .queries-card, .api-card, .resource-card, .cache-card, .suggestions-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .metric-card:hover, .chart-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .time-period-btn {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .time-period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    @media (max-width: 768px) {
        .performance-dashboard {
            padding: 0 0.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let responseTimeChart = null;
    let throughputChart = null;
    let resourceUsageChart = null;
    let refreshInterval = null;

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        loadPerformanceData();
        startAutoRefresh();
    });

    function initCharts() {
        // Response Time Chart
        const responseCtx = document.getElementById('responseTimeChart').getContext('2d');
        responseTimeChart = new Chart(responseCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [],
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    },
                    x: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    }
                }
            }
        });

        // Throughput Chart
        const throughputCtx = document.getElementById('throughputChart').getContext('2d');
        throughputChart = new Chart(throughputCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Requests per Minute',
                    data: [],
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    },
                    x: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    }
                }
            }
        });

        // Resource Usage Chart
        const resourceCtx = document.getElementById('resourceUsageChart').getContext('2d');
        resourceUsageChart = new Chart(resourceCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'CPU Usage (%)',
                        data: [],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Memory Usage (%)',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    },
                    x: {
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() },
                        grid: { color: 'rgba(var(--border-color-rgb), 0.1)' }
                    }
                }
            }
        });
    }

    function loadPerformanceData() {
        loadResponseTimeData('hour');
        loadThroughputData('hour');
        loadSlowQueries();
        loadApiPerformance();
        loadResourceUsage();
    }

    function loadResponseTimeData(period) {
        fetch(`{{ route('developer.performance.metrics') }}?type=response_time&period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (responseTimeChart) {
                    responseTimeChart.data.labels = data.labels || [];
                    responseTimeChart.data.datasets[0].data = data.values || [];
                    responseTimeChart.update();
                }
                
                if (data.current_value) {
                    document.getElementById('response-time').innerText = data.current_value + 'ms';
                }
                if (data.trend) {
                    const trendElem = document.getElementById('response-trend');
                    trendElem.innerHTML = `<i class="fas fa-arrow-${data.trend > 0 ? 'up' : 'down'} mr-1"></i>${Math.abs(data.trend)}%`;
                    trendElem.className = `text-xs ${data.trend > 0 ? 'text-red-500' : 'text-green-500'}`;
                }
            })
            .catch(error => console.error('Error loading response time data:', error));
    }

    function loadThroughputData(period) {
        fetch(`{{ route('developer.performance.metrics') }}?type=throughput&period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (throughputChart) {
                    throughputChart.data.labels = data.labels || [];
                    throughputChart.data.datasets[0].data = data.values || [];
                    throughputChart.update();
                }
                
                if (data.current_value) {
                    document.getElementById('throughput').innerText = data.current_value + ' req/min';
                }
                if (data.trend) {
                    const trendElem = document.getElementById('throughput-trend');
                    trendElem.innerHTML = `<i class="fas fa-arrow-${data.trend > 0 ? 'up' : 'down'} mr-1"></i>${Math.abs(data.trend)}%`;
                    trendElem.className = `text-xs ${data.trend > 0 ? 'text-green-500' : 'text-red-500'}`;
                }
            })
            .catch(error => console.error('Error loading throughput data:', error));
    }

    function loadSlowQueries() {
        fetch('{{ route("developer.performance.analysis.slow-queries") }}')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('slow-queries-list');
                if (data.queries && data.queries.length > 0) {
                    container.innerHTML = data.queries.map(query => `
                        <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm font-mono" style="color: var(--text-primary);">${escapeHtml(query.query)}</p>
                                    <div class="flex gap-4 mt-2">
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>${query.time}ms
                                        </span>
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-chart-line mr-1"></i>${query.count} occurrences
                                        </span>
                                    </div>
                                </div>
                                <button onclick="explainQuery('${query.id}')" class="text-xs px-2 py-1 rounded" 
                                        style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                    Explain
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-3xl mb-2" style="color: #10b981;"></i>
                            <p style="color: var(--text-secondary);">No slow queries detected</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading slow queries:', error);
                document.getElementById('slow-queries-list').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl mb-2" style="color: #ef4444;"></i>
                        <p style="color: var(--text-secondary);">Failed to load slow queries</p>
                    </div>
                `;
            });
    }

    function loadApiPerformance() {
        fetch('{{ route("developer.performance.analysis.api-performance") }}')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('api-performance-list');
                if (data.endpoints && data.endpoints.length > 0) {
                    container.innerHTML = data.endpoints.map(endpoint => `
                        <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">${endpoint.method} ${endpoint.path}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">${endpoint.count} requests</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold" style="color: var(--text-primary);">${endpoint.avg_time}ms</p>
                                    <span class="text-xs px-2 py-1 rounded-full ${endpoint.avg_time > 200 ? 'bg-yellow-500/20 text-yellow-500' : 'bg-green-500/20 text-green-500'}">
                                        ${endpoint.avg_time > 200 ? 'Slow' : 'Optimal'}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 w-full h-1 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                                <div class="h-full rounded-full" style="width: ${Math.min(100, (endpoint.avg_time / 500) * 100)}%; background-color: ${endpoint.avg_time > 200 ? '#f59e0b' : '#10b981'};"></div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-chart-line text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p style="color: var(--text-secondary);">No API data available</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading API performance:', error);
                document.getElementById('api-performance-list').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl mb-2" style="color: #ef4444;"></i>
                        <p style="color: var(--text-secondary);">Failed to load API performance data</p>
                    </div>
                `;
            });
    }

    function loadResourceUsage() {
        fetch('{{ route("developer.performance.metrics") }}?type=resources')
            .then(response => response.json())
            .then(data => {
                if (resourceUsageChart && data.labels && data.cpu && data.memory) {
                    resourceUsageChart.data.labels = data.labels;
                    resourceUsageChart.data.datasets[0].data = data.cpu;
                    resourceUsageChart.data.datasets[1].data = data.memory;
                    resourceUsageChart.update();
                }
            })
            .catch(error => console.error('Error loading resource usage:', error));
    }

    function refreshPerformanceData() {
        showNotification('Refreshing performance data...', 'info');
        loadPerformanceData();
    }

    function analyzeSlowQueries() {
        showNotification('Analyzing slow queries...', 'info');
        loadSlowQueries();
    }

    function refreshApiPerformance() {
        showNotification('Refreshing API performance data...', 'info');
        loadApiPerformance();
    }

    function explainQuery(queryId) {
        showNotification('Generating query explanation...', 'info');
        // Implement query explanation logic
    }

    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(() => {
            loadPerformanceData();
        }, 60000);
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all transform translate-x-0';
        
        const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
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
        setTimeout(() => notification.remove(), 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Period button handlers
    document.querySelectorAll('.time-period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const chart = this.dataset.chart;
            const period = this.dataset.period;
            
            if (chart === 'response') loadResponseTimeData(period);
            if (chart === 'throughput') loadThroughputData(period);
            
            document.querySelectorAll(`.time-period-btn[data-chart="${chart}"]`).forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '';
                b.style.color = '';
            });
            this.classList.add('active');
            this.style.backgroundColor = 'var(--primary)';
            this.style.color = 'white';
        });
    });
</script>
@endpush
@endsection