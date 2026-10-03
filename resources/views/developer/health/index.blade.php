@extends('layouts.dev')

@section('title', 'System Health Monitor - Developer')

@section('content')
<div class="health-monitor">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-heartbeat mr-2" style="color: var(--primary);"></i>System Health Monitor
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Real-time system health monitoring and diagnostics
                </p>
            </div>
            <div class="flex gap-3">
                <button onclick="refreshHealthData()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button onclick="runFullDiagnostics()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-stethoscope mr-2"></i>Run Diagnostics
                </button>
            </div>
        </div>
    </div>

    <!-- Overall Health Score Card -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <div class="health-score-card rounded-xl p-6 text-center lg:col-span-1"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="relative inline-block">
                <svg class="w-32 h-32">
                    <circle class="progress-ring-bg" stroke="rgba(59, 130, 246, 0.1)" stroke-width="8" fill="transparent" r="58" cx="64" cy="64"/>
                    <circle class="progress-ring" stroke="var(--primary)" stroke-width="8" fill="transparent" r="58" cx="64" cy="64"
                            stroke-dasharray="364.4" stroke-dashoffset="{{ 364.4 - (364.4 * ($overallHealth['score'] ?? 85) / 100) }}"
                            transform="rotate(-90 64 64)"/>
                </svg>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
                    <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $overallHealth['score'] ?? 85 }}</span>
                    <span class="text-sm" style="color: var(--text-secondary);">/100</span>
                </div>
            </div>
            <h3 class="text-lg font-semibold mt-3" style="color: var(--text-primary);">Overall Health</h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Status: <strong class="{{ ($overallHealth['status'] ?? 'healthy') === 'healthy' ? 'text-green-500' : 'text-red-500' }}">
                    {{ ucfirst($overallHealth['status'] ?? 'Healthy') }}
                </strong>
            </p>
        </div>

        <!-- System Metrics Cards -->
        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-microchip text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">CPU Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $systemMetrics['cpu'] ?? 45 }}%</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div class="h-full rounded-full transition-all" style="width: {{ $systemMetrics['cpu'] ?? 45 }}%; background-color: {{ ($systemMetrics['cpu'] ?? 45) > 80 ? '#ef4444' : '#10b981' }};"></div>
            </div>
        </div>

        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-memory text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Memory Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $systemMetrics['memory'] ?? 62 }}%</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div class="h-full rounded-full transition-all" style="width: {{ $systemMetrics['memory'] ?? 62 }}%; background-color: #3b82f6;"></div>
            </div>
        </div>

        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-hdd text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Disk Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $systemMetrics['disk'] ?? 58 }}%</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div class="h-full rounded-full transition-all" style="width: {{ $systemMetrics['disk'] ?? 58 }}%; background-color: #f59e0b;"></div>
            </div>
        </div>
    </div>

    <!-- Component Status Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @foreach($componentStatus as $component => $data)
        <div class="component-card rounded-xl p-4 transition-all hover:shadow-lg"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h3 class="font-semibold" style="color: var(--text-primary);">{{ ucfirst($component) }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $data['name'] }}</p>
                </div>
                <div class="w-2 h-2 rounded-full {{ $data['status'] === 'operational' ? 'bg-green-500' : ($data['status'] === 'degraded' ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span style="color: var(--text-secondary);">Status</span>
                <span class="font-medium {{ $data['status'] === 'operational' ? 'text-green-500' : ($data['status'] === 'degraded' ? 'text-yellow-500' : 'text-red-500') }}">
                    {{ ucfirst($data['status']) }}
                </span>
            </div>
            <div class="flex justify-between items-center text-sm mt-2">
                <span style="color: var(--text-secondary);">Latency</span>
                <span class="font-medium" style="color: var(--text-primary);">{{ $data['latency'] }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Health History Chart -->
    <div class="chart-card rounded-xl p-6 mb-8"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Health Score History (7 Days)
            </h3>
            <div class="flex space-x-2">
                <button class="history-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="7"
                        style="background-color: var(--primary); color: white;">7 Days</button>
                <button class="history-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="30"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">30 Days</button>
            </div>
        </div>
        <canvas id="healthHistoryChart" height="200"></canvas>
    </div>

    <!-- Detailed Health Checks -->
    <div class="checks-card rounded-xl p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i>Detailed Health Checks
        </h3>
        <div class="space-y-3">
            <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Database Connection</p>
                        <p class="text-xs" style="color: var(--text-secondary);">MySQL Database connectivity</p>
                    </div>
                    <span id="db-status" class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                        <i class="fas fa-check-circle mr-1"></i>Connected
                    </span>
                </div>
            </div>

            <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Cache System</p>
                        <p class="text-xs" style="color: var(--text-secondary);">{{ ucfirst(config('cache.default')) }} cache driver</p>
                    </div>
                    <span id="cache-status" class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                        <i class="fas fa-check-circle mr-1"></i>Operational
                    </span>
                </div>
            </div>

            <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Queue Workers</p>
                        <p class="text-xs" style="color: var(--text-secondary);">Job queue processing status</p>
                    </div>
                    <span id="queue-status" class="text-xs px-2 py-1 rounded-full bg-yellow-500/20 text-yellow-500">
                        <i class="fas fa-clock mr-1"></i>Running
                    </span>
                </div>
            </div>

            <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Storage</p>
                        <p class="text-xs" style="color: var(--text-secondary);">Storage writability check</p>
                    </div>
                    <span id="storage-status" class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                        <i class="fas fa-check-circle mr-1"></i>Writable
                    </span>
                </div>
            </div>

            <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Session Handler</p>
                        <p class="text-xs" style="color: var(--text-secondary);">{{ ucfirst(config('session.driver')) }} session driver</p>
                    </div>
                    <span id="session-status" class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                        <i class="fas fa-check-circle mr-1"></i>Active
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .health-monitor {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .progress-ring {
        transition: stroke-dashoffset 0.5s ease;
        transform-origin: 50% 50%;
    }

    .health-score-card, .metric-card, .component-card, .chart-card, .checks-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .health-score-card:hover, .metric-card:hover, .component-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .history-period-btn {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .history-period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    @media (max-width: 768px) {
        .health-monitor {
            padding: 0 0.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let healthHistoryChart = null;
    let autoRefreshInterval = null;

    document.addEventListener('DOMContentLoaded', function() {
        initHealthHistoryChart();
        startAutoRefresh();
    });

    function initHealthHistoryChart() {
        const ctx = document.getElementById('healthHistoryChart').getContext('2d');
        const historyData = @json($healthHistory);
        
        const labels = historyData.map(item => item.date);
        const scores = historyData.map(item => item.score);

        if (healthHistoryChart) {
            healthHistoryChart.destroy();
        }

        healthHistoryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Health Score',
                    data: scores,
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
                                return 'Health Score: ' + context.raw + '/100';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim(),
                            stepSize: 20
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

    function refreshHealthData() {
        showNotification('Refreshing health data...', 'info');
        
        fetch('{{ route("developer.health.overall") }}')
            .then(response => response.json())
            .then(data => {
                updateHealthScore(data.score, data.status);
            })
            .catch(error => console.error('Error refreshing health data:', error));
    }

    function runFullDiagnostics() {
        showNotification('Running full system diagnostics...', 'info');
        
        fetch('{{ route("developer.health.run-diagnostics") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Diagnostics completed successfully!', 'success');
                updateDiagnosticsResults(data.diagnostics);
            } else {
                showNotification('Diagnostics failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error running diagnostics:', error);
            showNotification('Error running diagnostics', 'error');
        });
    }

    function updateHealthScore(score, status) {
        const scoreElement = document.querySelector('.health-score-card .text-3xl');
        const statusElement = document.querySelector('.health-score-card strong');
        
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
        
        if (statusElement) {
            statusElement.innerText = status.charAt(0).toUpperCase() + status.slice(1);
            statusElement.className = status === 'healthy' ? 'text-green-500' : 'text-red-500';
        }
    }

    function updateDiagnosticsResults(diagnostics) {
        // Update status indicators based on diagnostic results
        if (diagnostics.database && diagnostics.database.passed) {
            updateStatusBadge('db-status', true, 'Connected');
        }
        
        if (diagnostics.cache && diagnostics.cache.passed) {
            updateStatusBadge('cache-status', true, 'Operational');
        }
        
        if (diagnostics.storage && diagnostics.storage.passed) {
            updateStatusBadge('storage-status', true, 'Writable');
        }
        
        if (diagnostics.session && diagnostics.session.passed) {
            updateStatusBadge('session-status', true, 'Active');
        }
    }

    function updateStatusBadge(elementId, passed, successMessage) {
        const badge = document.getElementById(elementId);
        if (badge) {
            if (passed) {
                badge.className = 'text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500';
                badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i>' + successMessage;
            } else {
                badge.className = 'text-xs px-2 py-1 rounded-full bg-red-500/20 text-red-500';
                badge.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>Failed';
            }
        }
    }

    function startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        autoRefreshInterval = setInterval(() => {
            refreshHealthData();
        }, 60000); // Refresh every minute
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

    // History period buttons
    document.querySelectorAll('.history-period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const period = this.dataset.period;
            
            fetch(`{{ route("developer.health.trends") }}?period=${period}`)
                .then(response => response.json())
                .then(data => {
                    const trends = data.trends || [];
                    const labels = trends.map(t => t.date);
                    const scores = trends.map(t => t.score);
                    
                    if (healthHistoryChart) {
                        healthHistoryChart.data.labels = labels;
                        healthHistoryChart.data.datasets[0].data = scores;
                        healthHistoryChart.update();
                    }
                });
            
            document.querySelectorAll('.history-period-btn').forEach(b => {
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