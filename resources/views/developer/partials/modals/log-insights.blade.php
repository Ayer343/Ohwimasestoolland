@extends('layouts.dev')

@section('title', 'Log Insights & Analytics')

@section('content')
<div class="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)] p-4 md:p-6">
    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[var(--text-primary)]">Log Insights & Analytics</h1>
                <p class="text-[var(--text-secondary)] mt-2">Comprehensive log analysis, trends, and insights</p>
            </div>
            <div class="flex items-center space-x-4 mt-4 md:mt-0">
                <!-- Time Period Selector -->
                <div class="flex space-x-2">
                    <button onclick="changeTimePeriod('1h')" class="time-period-btn" data-period="1h">1H</button>
                    <button onclick="changeTimePeriod('24h')" class="time-period-btn active" data-period="24h">24H</button>
                    <button onclick="changeTimePeriod('7d')" class="time-period-btn" data-period="7d">7D</button>
                    <button onclick="changeTimePeriod('30d')" class="time-period-btn" data-period="30d">30D</button>
                    <button onclick="changeTimePeriod('custom')" class="time-period-btn" data-period="custom">Custom</button>
                </div>
                
                <!-- Export Options -->
                <button onclick="exportLogInsights()" class="flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all duration-200" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-download text-sm"></i>
                    <span>Export</span>
                </button>
                
                <!-- Refresh Button -->
                <button onclick="refreshLogInsights()" class="flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all duration-200" style="background: linear-gradient(to right, var(--primary), var(--secondary)); color: white;">
                    <i class="fas fa-sync-alt text-sm"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Logs -->
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Logs</p>
                        <h3 class="text-2xl font-bold mt-1" id="totalLogsCount">0</h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="logChange" class="font-medium">+0%</span> from previous period
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-file-alt text-xl" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Rate -->
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Error Rate</p>
                        <h3 class="text-2xl font-bold mt-1" id="errorRate">0%</h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="errorChange" class="font-medium">+0%</span> change
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-exclamation-circle text-xl" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span style="color: var(--text-secondary);">Error Threshold</span>
                        <span style="color: var(--text-primary);" id="errorThreshold">5%</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background: var(--border-color);">
                        <div id="errorRateBar" class="h-2 rounded-full" style="background: var(--danger); width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Response Time -->
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Avg Response Time</p>
                        <h3 class="text-2xl font-bold mt-1" id="avgResponseTime">0ms</h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="responseChange" class="font-medium">+0%</span> from previous period
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-tachometer-alt text-xl" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span style="color: var(--text-secondary);">Performance Target</span>
                        <span style="color: var(--text-primary);" id="responseTarget">200ms</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background: var(--border-color);">
                        <div id="responseTimeBar" class="h-2 rounded-full" style="background: var(--warning); width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- High Severity Logs -->
        <div class="card">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">High Severity</p>
                        <h3 class="text-2xl font-bold mt-1" id="highSeverityCount">0</h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="severityChange" class="font-medium">+0%</span> from previous period
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-fire text-xl" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span style="color: var(--text-secondary);">Requires Attention</span>
                        <span style="color: var(--text-primary);" id="requiresAttention">0</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background: var(--border-color);">
                        <div id="severityBar" class="h-2 rounded-full" style="background: linear-gradient(90deg, var(--danger) 0%, var(--warning) 100%); width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Log Trends Chart -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Log Trends Over Time</h3>
                            <p class="text-sm text-[var(--text-secondary)] mt-1">Volume of logs by level and source</p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="toggleChartType()" class="text-sm flex items-center" style="color: var(--primary);">
                                <i class="fas fa-chart-line mr-1"></i> Toggle View
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="chart-container" style="height: 350px;">
                        <canvas id="logTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Error Sources -->
        <div class="card">
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <h3 class="text-lg font-semibold text-[var(--text-primary)]">Top Error Sources</h3>
                <p class="text-sm text-[var(--text-secondary)] mt-1">Most common sources of errors</p>
            </div>
            <div class="p-6">
                <div class="space-y-4" id="topErrorSources">
                    <!-- Will be loaded via JavaScript -->
                    <div class="animate-pulse">
                        <div class="h-8 rounded" style="background: var(--border-color);"></div>
                        <div class="h-8 rounded mt-2" style="background: var(--border-color);"></div>
                        <div class="h-8 rounded mt-2" style="background: var(--border-color);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analysis Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Error Patterns -->
        <div class="card">
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-[var(--text-primary)]">Error Patterns</h3>
                    <button onclick="analyzePatterns()" class="text-sm flex items-center" style="color: var(--primary);">
                        <i class="fas fa-search mr-1"></i> Analyze
                    </button>
                </div>
                <p class="text-sm text-[var(--text-secondary)] mt-1">Common error patterns and correlations</p>
            </div>
            <div class="p-6">
                <div id="errorPatterns">
                    <!-- Will be loaded via JavaScript -->
                    <div class="text-center py-8" style="color: var(--text-secondary);">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--primary)] mb-3"></div>
                        <p class="text-sm">Analyzing error patterns...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Issues -->
        <div class="card">
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <h3 class="text-lg font-semibold text-[var(--text-primary)]">Performance Issues</h3>
                <p class="text-sm text-[var(--text-secondary)] mt-1">Slow responses and bottlenecks</p>
            </div>
            <div class="p-6">
                <div id="performanceIssues">
                    <!-- Will be loaded via JavaScript -->
                    <div class="text-center py-8" style="color: var(--text-secondary);">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--primary)] mb-3"></div>
                        <p class="text-sm">Analyzing performance issues...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Distribution -->
    <div class="card mb-8">
        <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Hourly Distribution</h3>
            <p class="text-sm text-[var(--text-secondary)] mt-1">Log volume by hour of day</p>
        </div>
        <div class="p-6">
            <div class="chart-container" style="height: 250px;">
                <canvas id="hourlyDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="card">
        <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Insights & Recommendations</h3>
            <p class="text-sm text-[var(--text-secondary)] mt-1">Actionable insights based on log analysis</p>
        </div>
        <div class="p-6">
            <div id="logRecommendations">
                <!-- Will be loaded via JavaScript -->
                <div class="text-center py-8" style="color: var(--text-secondary);">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--primary)] mb-3"></div>
                    <p class="text-sm">Generating recommendations...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Date Range Modal -->
<div id="customDateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-[var(--card-bg)] rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <h3 class="text-lg font-semibold text-[var(--text-primary)]">Custom Date Range</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Start Date</label>
                        <input type="date" id="startDate" class="w-full p-2 rounded-lg" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">End Date</label>
                        <input type="date" id="endDate" class="w-full p-2 rounded-lg" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                </div>
            </div>
            <div class="p-6 flex justify-end space-x-3" style="border-top: 1px solid var(--border-color);">
                <button onclick="closeCustomDateModal()" class="px-4 py-2 rounded-lg font-medium" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <button onclick="applyCustomDateRange()" class="px-4 py-2 rounded-lg font-medium text-white" style="background: linear-gradient(to right, var(--primary), var(--secondary));">
                    Apply
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
.time-period-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.3s ease;
}

.time-period-btn:hover {
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.time-period-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.chart-container {
    position: relative;
    width: 100%;
}

.pattern-item {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.pattern-item:hover {
    background: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    transform: translateX(4px);
}

.recommendation-item {
    padding: 1rem;
    border-radius: 0.5rem;
    background: var(--bg-secondary);
    border-left: 4px solid;
    margin-bottom: 0.75rem;
}

.recommendation-item.critical {
    border-left-color: var(--danger);
    background: rgba(var(--danger-rgb), 0.1);
}

.recommendation-item.high {
    border-left-color: var(--warning);
    background: rgba(var(--warning-rgb), 0.1);
}

.recommendation-item.medium {
    border-left-color: var(--info);
    background: rgba(var(--info-rgb), 0.1);
}

.recommendation-item.low {
    border-left-color: var(--success);
    background: rgba(var(--success-rgb), 0.1);
}

.source-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.source-item:hover {
    background: rgba(var(--danger-rgb), 0.1);
    border-color: var(--danger);
}

.source-name {
    font-weight: 500;
    color: var(--text-primary);
}

.source-count {
    font-weight: bold;
    color: var(--danger);
}

.performance-issue {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: var(--bg-secondary);
    border: 1px solid var(--warning);
    margin-bottom: 0.5rem;
}

.issue-severity {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.issue-severity.critical {
    background: rgba(var(--danger-rgb), 0.2);
    color: var(--danger);
}

.issue-severity.high {
    background: rgba(var(--warning-rgb), 0.2);
    color: var(--warning);
}

.issue-severity.medium {
    background: rgba(var(--info-rgb), 0.2);
    color: var(--info);
}

.insight-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.insight-tag:hover {
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.trend-indicator {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    margin-left: 0.5rem;
}

.trend-indicator.up {
    background: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.trend-indicator.down {
    background: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.trend-indicator.stable {
    background: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}
</style>
@endsection

@section('scripts')
<script>
// Global variables
let logTrendsChart = null;
let hourlyDistributionChart = null;
let currentTimePeriod = '24h';
let currentChartType = 'line';

document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Loading Log Insights Dashboard...');
    
    // Initialize charts
    initializeCharts();
    
    // Load initial data
    loadLogInsights();
    loadTopErrorSources();
    loadErrorPatterns();
    loadPerformanceIssues();
    loadHourlyDistribution();
    loadRecommendations();
});

function initializeCharts() {
    const computedStyle = getComputedStyle(document.body);
    
    // Initialize log trends chart
    const trendsCtx = document.getElementById('logTrendsChart').getContext('2d');
    logTrendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: generateTimeLabels(),
            datasets: [
                {
                    label: 'Errors',
                    data: generateRandomData(24, 0, 10),
                    borderColor: computedStyle.getPropertyValue('--danger').trim(),
                    backgroundColor: hexToRgba(computedStyle.getPropertyValue('--danger').trim(), 0.1),
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                },
                {
                    label: 'Warnings',
                    data: generateRandomData(24, 0, 15),
                    borderColor: computedStyle.getPropertyValue('--warning').trim(),
                    backgroundColor: hexToRgba(computedStyle.getPropertyValue('--warning').trim(), 0.1),
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                },
                {
                    label: 'Info',
                    data: generateRandomData(24, 0, 50),
                    borderColor: computedStyle.getPropertyValue('--info').trim(),
                    backgroundColor: hexToRgba(computedStyle.getPropertyValue('--info').trim(), 0.1),
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }
            ]
        },
        options: getChartOptions('Log Volume Over Time')
    });
    
    // Initialize hourly distribution chart
    const hourlyCtx = document.getElementById('hourlyDistributionChart').getContext('2d');
    hourlyDistributionChart = new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => `${i.toString().padStart(2, '0')}:00`),
            datasets: [
                {
                    label: 'Error Frequency',
                    data: generateRandomData(24, 0, 20),
                    backgroundColor: computedStyle.getPropertyValue('--danger').trim(),
                    borderColor: computedStyle.getPropertyValue('--danger').trim(),
                    borderWidth: 1
                },
                {
                    label: 'Warning Frequency',
                    data: generateRandomData(24, 0, 30),
                    backgroundColor: computedStyle.getPropertyValue('--warning').trim(),
                    borderColor: computedStyle.getPropertyValue('--warning').trim(),
                    borderWidth: 1
                }
            ]
        },
        options: getChartOptions('Hourly Distribution')
    });
}

function getChartOptions(title) {
    const computedStyle = getComputedStyle(document.body);
    
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: computedStyle.getPropertyValue('--text-primary').trim(),
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: computedStyle.getPropertyValue('--bg-secondary').trim(),
                titleColor: computedStyle.getPropertyValue('--text-primary').trim(),
                bodyColor: computedStyle.getPropertyValue('--text-primary').trim(),
                borderColor: computedStyle.getPropertyValue('--border-color').trim(),
                borderWidth: 1
            }
        },
        scales: {
            x: {
                grid: {
                    color: computedStyle.getPropertyValue('--border-color').trim()
                },
                ticks: {
                    color: computedStyle.getPropertyValue('--text-secondary').trim()
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: computedStyle.getPropertyValue('--border-color').trim()
                },
                ticks: {
                    color: computedStyle.getPropertyValue('--text-secondary').trim()
                }
            }
        }
    };
}

function generateTimeLabels() {
    const now = new Date();
    return Array.from({length: 24}, (_, i) => {
        const d = new Date(now.getTime() - (23 - i) * 60 * 60 * 1000);
        return `${d.getHours().toString().padStart(2, '0')}:00`;
    });
}

function generateRandomData(count, min, max) {
    return Array.from({length: count}, () => Math.floor(Math.random() * (max - min + 1)) + min);
}

async function loadLogInsights() {
    try {
        const response = await fetch(`{{ route("developer.logs.insights") }}?period=${currentTimePeriod}`);
        const data = await response.json();
        
        updateSummaryCards(data);
        updateTrendsChart(data);
        
    } catch (error) {
        console.error('Failed to load log insights:', error);
        showNotification('Failed to load log insights', 'error');
    }
}

function updateSummaryCards(data) {
    // Update total logs
    animateValue('totalLogsCount', 0, data.total || 0);
    updateMetric('logChange', '+12%');
    
    // Update error rate
    const errorRate = data.error_rate || 0;
    animateValue('errorRate', 0, errorRate, '%');
    updateProgress('errorRateBar', Math.min(errorRate * 10, 100));
    updateMetric('errorThreshold', '5%');
    updateMetric('errorChange', errorRate > 5 ? '+8%' : '-3%');
    
    // Update average response time
    const avgResponse = data.avg_response_time || 0;
    animateValue('avgResponseTime', 0, avgResponse, 'ms');
    updateProgress('responseTimeBar', Math.min(avgResponse / 5, 100));
    updateMetric('responseTarget', '200ms');
    updateMetric('responseChange', avgResponse > 200 ? '+15%' : '-5%');
    
    // Update high severity count
    const highSeverity = data.high_severity || 0;
    animateValue('highSeverityCount', 0, highSeverity);
    updateProgress('severityBar', Math.min(highSeverity * 5, 100));
    updateMetric('requiresAttention', data.requires_attention || 0);
    updateMetric('severityChange', highSeverity > 10 ? '+25%' : '-10%');
}

function updateTrendsChart(data) {
    if (!logTrendsChart || !data.trends) return;
    
    const trends = data.trends || [];
    
    if (trends.length > 0) {
        logTrendsChart.data.labels = trends.map(t => t.time);
        logTrendsChart.data.datasets[0].data = trends.map(t => t.errors || 0);
        logTrendsChart.data.datasets[1].data = trends.map(t => t.warnings || 0);
        logTrendsChart.data.datasets[2].data = trends.map(t => t.info || 0);
        logTrendsChart.update();
    }
}

async function loadTopErrorSources() {
    try {
        const response = await fetch(`{{ route("developer.logs.top-sources") }}`);
        const data = await response.json();
        
        const container = document.getElementById('topErrorSources');
        if (!container) return;
        
        let html = '';
        
        data.sources.forEach((source, index) => {
            const percentage = Math.round((source.count / data.total) * 100) || 0;
            
            html += `
                <div class="source-item">
                    <div class="flex items-center">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center mr-3 text-xs font-bold" 
                              style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            ${index + 1}
                        </span>
                        <span class="source-name">${source.source || 'Unknown'}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="source-count">${source.count}</span>
                        <span class="text-xs ml-2" style="color: var(--text-secondary);">(${percentage}%)</span>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Failed to load top error sources:', error);
    }
}

async function loadErrorPatterns() {
    try {
        const response = await fetch(`{{ route("developer.logs.analyze-patterns") }}`);
        const data = await response.json();
        
        const container = document.getElementById('errorPatterns');
        if (!container) return;
        
        let html = '';
        
        data.patterns?.slice(0, 5).forEach(pattern => {
            html += `
                <div class="pattern-item">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium" style="color: var(--text-primary);">${pattern.type}</span>
                        <span class="px-2 py-1 text-xs rounded-full" 
                              style="background: rgba(var(--${pattern.severity}-rgb), 0.1); 
                                     color: var(--${pattern.severity});">
                            ${pattern.count} occurrences
                        </span>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">${pattern.description}</p>
                    <div class="mt-2 flex flex-wrap">
                        ${pattern.tags?.map(tag => `
                            <span class="insight-tag">${tag}</span>
                        `).join('')}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html || `
            <div class="text-center py-8" style="color: var(--text-secondary);">
                <i class="fas fa-check-circle text-3xl mb-3" style="color: var(--success);"></i>
                <p>No error patterns detected</p>
            </div>
        `;
        
    } catch (error) {
        console.error('Failed to load error patterns:', error);
    }
}

async function loadPerformanceIssues() {
    try {
        const response = await fetch(`{{ route("developer.logs.diagnostics") }}`);
        const data = await response.json();
        
        const container = document.getElementById('performanceIssues');
        if (!container) return;
        
        let html = '';
        
        // Slow responses
        const slowResponses = data.statistics?.slow_responses || 0;
        if (slowResponses > 0) {
            html += `
                <div class="performance-issue">
                    <span class="issue-severity high">
                        <i class="fas fa-clock mr-1"></i> Performance
                    </span>
                    <p class="font-medium mb-1" style="color: var(--text-primary);">Slow Responses Detected</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        ${slowResponses} responses exceeding 2s threshold
                    </p>
                    <div class="mt-2">
                        <button onclick="viewSlowResponses()" class="text-xs hover:underline" style="color: var(--warning);">
                            View Details
                        </button>
                    </div>
                </div>
            `;
        }
        
        // High error rate
        const errorRate = data.statistics?.error_rate || 0;
        if (errorRate > 10) {
            html += `
                <div class="performance-issue">
                    <span class="issue-severity critical">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Critical
                    </span>
                    <p class="font-medium mb-1" style="color: var(--text-primary);">High Error Rate</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Error rate of ${errorRate}% exceeds healthy threshold
                    </p>
                    <div class="mt-2">
                        <button onclick="viewErrorTrends()" class="text-xs hover:underline" style="color: var(--danger);">
                            Analyze
                        </button>
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html || `
            <div class="text-center py-8" style="color: var(--text-secondary);">
                <i class="fas fa-check-circle text-3xl mb-3" style="color: var(--success);"></i>
                <p>No performance issues detected</p>
            </div>
        `;
        
    } catch (error) {
        console.error('Failed to load performance issues:', error);
    }
}

async function loadHourlyDistribution() {
    try {
        const response = await fetch(`#`);
        const data = await response.json();
        
        if (hourlyDistributionChart && data) {
            const hours = Object.keys(data);
            const errorData = hours.map(hour => data[hour]?.errors || 0);
            const warningData = hours.map(hour => data[hour]?.warnings || 0);
            
            hourlyDistributionChart.data.datasets[0].data = errorData;
            hourlyDistributionChart.data.datasets[1].data = warningData;
            hourlyDistributionChart.update();
        }
        
    } catch (error) {
        console.error('Failed to load hourly distribution:', error);
    }
}

async function loadRecommendations() {
    try {
        // Simulate recommendations based on log analysis
        const recommendations = generateRecommendations();
        const container = document.getElementById('logRecommendations');
        
        if (!container) return;
        
        let html = '';
        
        recommendations.forEach(rec => {
            html += `
                <div class="recommendation-item ${rec.priority}">
                    <div class="flex items-start">
                        <i class="fas fa-${rec.icon} mt-0.5 mr-3" style="color: var(--${rec.priority});"></i>
                        <div class="flex-1">
                            <h4 class="font-medium" style="color: var(--text-primary);">${rec.title}</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">${rec.description}</p>
                            <div class="mt-2">
                                ${rec.actions?.map(action => `
                                    <button onclick="${action.action}" class="text-xs mr-2 px-2 py-1 rounded" 
                                            style="background: rgba(var(--${rec.priority}-rgb), 0.1); 
                                                   color: var(--${rec.priority});">
                                        ${action.label}
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Failed to load recommendations:', error);
    }
}

function generateRecommendations() {
    return [
        {
            priority: 'critical',
            icon: 'exclamation-triangle',
            title: 'High Error Rate Detected',
            description: 'Error rate has increased by 25% in the last 24 hours. Immediate investigation recommended.',
            actions: [
                { label: 'View Error Logs', action: "viewErrorLogs()" },
                { label: 'Analyze Trends', action: "analyzeErrorTrends()" }
            ]
        },
        {
            priority: 'high',
            icon: 'clock',
            title: 'Slow API Responses',
            description: '15% of API responses exceed 2-second threshold during peak hours.',
            actions: [
                { label: 'View Performance Logs', action: "viewPerformanceLogs()" },
                { label: 'Optimize Queries', action: "runQueryOptimization()" }
            ]
        },
        {
            priority: 'medium',
            icon: 'database',
            title: 'Database Connection Spikes',
            description: 'Database connection spikes detected during 14:00-16:00 daily.',
            actions: [
                { label: 'Review Connection Pool', action: "reviewConnectionPool()" },
                { label: 'Monitor Connections', action: "monitorDatabaseConnections()" }
            ]
        },
        {
            priority: 'low',
            icon: 'info-circle',
            title: 'Memory Usage Trend',
            description: 'Memory usage showing gradual increase over past week.',
            actions: [
                { label: 'View Memory Metrics', action: "viewMemoryMetrics()" },
                { label: 'Schedule Cleanup', action: "scheduleMemoryCleanup()" }
            ]
        }
    ];
}

// UI Functions
function changeTimePeriod(period) {
    if (period === 'custom') {
        showCustomDateModal();
        return;
    }
    
    currentTimePeriod = period;
    
    // Update active button
    document.querySelectorAll('.time-period-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.period === period) {
            btn.classList.add('active');
        }
    });
    
    // Reload data
    loadLogInsights();
}

function showCustomDateModal() {
    document.getElementById('customDateModal').classList.remove('hidden');
}

function closeCustomDateModal() {
    document.getElementById('customDateModal').classList.add('hidden');
}

function applyCustomDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'warning');
        return;
    }
    
    currentTimePeriod = 'custom';
    closeCustomDateModal();
    
    // Reload data with custom range
    loadLogInsights();
}

function toggleChartType() {
    currentChartType = currentChartType === 'line' ? 'bar' : 'line';
    logTrendsChart.config.type = currentChartType;
    logTrendsChart.update();
}

function refreshLogInsights() {
    showNotification('Refreshing log insights...', 'info');
    
    loadLogInsights();
    loadTopErrorSources();
    loadErrorPatterns();
    loadPerformanceIssues();
    loadHourlyDistribution();
    loadRecommendations();
}

function exportLogInsights() {
    showNotification('Preparing export...', 'info');
    
    // Simulate export generation
    setTimeout(() => {
        const data = {
            timestamp: new Date().toISOString(),
            period: currentTimePeriod,
            insights: {
                total_logs: document.getElementById('totalLogsCount').textContent,
                error_rate: document.getElementById('errorRate').textContent,
                avg_response_time: document.getElementById('avgResponseTime').textContent,
                high_severity: document.getElementById('highSeverityCount').textContent
            }
        };
        
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `log-insights-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        showNotification('Log insights exported successfully', 'success');
    }, 1000);
}

function analyzePatterns() {
    showNotification('Analyzing error patterns...', 'info');
    loadErrorPatterns();
}

function viewErrorLogs() {
    window.location.href = '{{ route("developer.logs.error-logs") }}';
}

function viewPerformanceLogs() {
    window.location.href = '#?level=warning&source=performance';
}

function viewSlowResponses() {
    window.location.href = '{{ route("developer.logs.search") }}?search=slow&type=performance';
}

function analyzeErrorTrends() {
    showNotification('Opening error trend analysis...', 'info');
    // Implement error trend analysis
}

// Utility Functions
function animateValue(elementId, current, target, suffix = '', duration = 500) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const start = parseFloat(current) || 0;
    const end = parseFloat(target) || 0;
    
    if (start === end) {
        element.textContent = end + (suffix || '');
        return;
    }
    
    const startTime = performance.now();
    
    const animate = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const value = start + (end - start) * progress;
        element.textContent = Math.round(value) + (suffix || '');
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    };
    
    requestAnimationFrame(animate);
}

function updateProgress(elementId, percentage) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.width = Math.min(percentage, 100) + '%';
    }
}

function updateMetric(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

function hexToRgba(hex, alpha = 1) {
    let r = 0, g = 0, b = 0;
    
    if (hex.length === 4) {
        r = parseInt(hex[1] + hex[1], 16);
        g = parseInt(hex[2] + hex[2], 16);
        b = parseInt(hex[3] + hex[3], 16);
    } else if (hex.length === 7) {
        r = parseInt(hex[1] + hex[2], 16);
        g = parseInt(hex[3] + hex[4], 16);
        b = parseInt(hex[5] + hex[6], 16);
    }
    
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function showNotification(message, type = 'success', duration = 3000) {
    // Implementation from dashboard
    console.log(`${type.toUpperCase()}: ${message}`);
    
    // Create simple notification
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50';
    notification.style.cssText = `
        background: ${type === 'error' ? 'var(--danger)' : type === 'warning' ? 'var(--warning)' : 'var(--success)'};
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    `;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'check-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, duration);
}

// Export functions for global access
window.LogInsights = {
    refreshLogInsights,
    changeTimePeriod,
    toggleChartType,
    exportLogInsights,
    analyzePatterns,
    viewErrorLogs
};
</script>
@endsection