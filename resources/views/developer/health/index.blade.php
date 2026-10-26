@extends('layouts.dev')

@section('title', 'System Health Monitor - Developer')

@section('content')
@include('developer.health._nav')

@php
    $overallHealth   = $overallHealth   ?? ['score' => 0, 'status' => 'unknown'];
    $systemMetrics   = $systemMetrics   ?? ['cpu' => null, 'memory' => null, 'disk' => null];
    $componentStatus = $componentStatus ?? [];
    $healthHistory   = is_array($healthHistory ?? null) ? $healthHistory : [];

    $score  = (int) ($overallHealth['score'] ?? 0);
    $status = strtolower($overallHealth['status'] ?? 'unknown');

    $statusColors = [
        'healthy'     => ['dot' => 'bg-green-500',  'text' => 'text-green-500',  'ring' => '#10b981'],
        'operational' => ['dot' => 'bg-green-500',  'text' => 'text-green-500',  'ring' => '#10b981'],
        'degraded'    => ['dot' => 'bg-yellow-500', 'text' => 'text-yellow-500', 'ring' => '#f59e0b'],
        'warning'     => ['dot' => 'bg-yellow-500', 'text' => 'text-yellow-500', 'ring' => '#f59e0b'],
        'critical'    => ['dot' => 'bg-red-500',    'text' => 'text-red-500',    'ring' => '#ef4444'],
        'down'        => ['dot' => 'bg-red-500',    'text' => 'text-red-500',    'ring' => '#ef4444'],
        'unknown'     => ['dot' => 'bg-gray-400',   'text' => 'text-gray-400',   'ring' => '#9ca3af'],
    ];

    $overallStyle = $statusColors[$status] ?? $statusColors['unknown'];

    // Metric helper: returns value/display/width/color with N/A fallback.
    $metric = function ($key, $defaultColor) use ($systemMetrics) {
        $value = $systemMetrics[$key] ?? null;
        return [
            'value'   => is_numeric($value) ? (float) $value : null,
            'display' => is_numeric($value) ? number_format((float) $value, 1) . '%' : 'N/A',
            'width'   => is_numeric($value) ? min(100, max(0, (float) $value)) : 0,
            'color'   => is_numeric($value) && $value > 80 ? '#ef4444' : $defaultColor,
        ];
    };

    $cpu    = $metric('cpu',    '#10b981');
    $memory = $metric('memory', '#3b82f6');
    $disk   = $metric('disk',   '#f59e0b');
@endphp

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
                    <span id="last-updated" class="ml-2 text-xs"></span>
                </p>
            </div>
            <div class="flex gap-3">
                <button onclick="HealthMonitor.refreshHealthData(false)"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button onclick="HealthMonitor.runFullDiagnostics()" id="run-diagnostics-btn"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-stethoscope mr-2"></i>Run Diagnostics
                </button>
            </div>
        </div>
    </div>

    <!-- Overall Health Score + Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <div class="health-score-card rounded-xl p-6 text-center lg:col-span-1"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="relative inline-block">
                <div class="relative" style="width: 160px; height: 160px;">
                    <canvas id="healthScoreDoughnut" width="160" height="160"></canvas>
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center"
                         style="pointer-events: none;">
                        <span id="health-score-value" class="text-3xl font-bold"
                              style="color: var(--text-primary);">{{ $score }}</span>
                        <span class="text-sm" style="color: var(--text-secondary);">/100</span>
                    </div>
                </div>
            </div>
            <h3 class="text-lg font-semibold mt-3" style="color: var(--text-primary);">Overall Health</h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Status:
                <strong id="health-status-text" class="{{ $overallStyle['text'] }}">
                    {{ ucfirst($status) }}
                </strong>
            </p>
        </div>

        <!-- CPU -->
        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-microchip text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">CPU Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" id="metric-cpu">{{ $cpu['display'] }}</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div id="metric-cpu-bar" class="h-full rounded-full transition-all"
                     data-default-color="#10b981"
                     style="width: {{ $cpu['width'] }}%; background-color: {{ $cpu['color'] }};"></div>
            </div>
        </div>

        <!-- Memory -->
        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-memory text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Memory Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" id="metric-memory">{{ $memory['display'] }}</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div id="metric-memory-bar" class="h-full rounded-full transition-all"
                     data-default-color="#3b82f6"
                     style="width: {{ $memory['width'] }}%; background-color: {{ $memory['color'] }};"></div>
            </div>
        </div>

        <!-- Disk -->
        <div class="metric-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-hdd text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Disk Usage</h3>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" id="metric-disk">{{ $disk['display'] }}</p>
            <div class="mt-2 w-full h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                <div id="metric-disk-bar" class="h-full rounded-full transition-all"
                     data-default-color="#f59e0b"
                     style="width: {{ $disk['width'] }}%; background-color: {{ $disk['color'] }};"></div>
            </div>
        </div>
    </div>

    <!-- Component Status Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="component-status-grid">
        @forelse($componentStatus as $component => $data)
            @php
                $cStatus = strtolower($data['status'] ?? 'unknown');
                $style   = $statusColors[$cStatus] ?? $statusColors['unknown'];
            @endphp
            <div class="component-card rounded-xl p-4 transition-all hover:shadow-lg"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);"
                 data-component="{{ $component }}">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold" style="color: var(--text-primary);">{{ ucfirst($component) }}</h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $data['name'] ?? '—' }}</p>
                    </div>
                    <div class="w-2 h-2 rounded-full {{ $style['dot'] }}" data-role="component-dot"></div>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span style="color: var(--text-secondary);">Status</span>
                    <span class="font-medium {{ $style['text'] }}" data-role="component-status-text">
                        {{ ucfirst($cStatus) }}
                    </span>
                </div>
                <div class="flex justify-between items-center text-sm mt-2">
                    <span style="color: var(--text-secondary);">Latency</span>
                    <span class="font-medium" style="color: var(--text-primary);" data-role="component-latency">
                        {{ $data['latency'] ?? '—' }}
                    </span>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-sm py-6" style="color: var(--text-secondary);">
                No component data available.
            </div>
        @endforelse
    </div>

    <!-- Health Score History -->
    <div class="chart-card rounded-xl p-6 mb-8"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Health Score History
            </h3>
            <div class="flex space-x-2">
                <button class="history-period-btn px-3 py-1 rounded-lg text-sm transition-all active" data-period="7"
                        style="background-color: var(--primary); color: white;">7 Days</button>
                <button class="history-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="30"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">30 Days</button>
                <button class="history-period-btn px-3 py-1 rounded-lg text-sm transition-all" data-period="90"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">90 Days</button>
            </div>
        </div>
        <div style="position: relative; min-height: 220px;">
            <canvas id="healthHistoryChart" height="200"></canvas>
            <div id="healthHistoryEmpty"
                 class="hidden absolute inset-0 flex items-center justify-center text-sm"
                 style="color: var(--text-secondary);">
                <div class="text-center">
                    <i class="fas fa-chart-line text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p>No health history yet.</p>
                    <p class="text-xs mt-1">Generate a report to start tracking.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Health Checks -->
    <div class="checks-card rounded-xl p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i>Detailed Health Checks
        </h3>
        <div class="space-y-3">
            @php
                $detailedChecks = [
                    ['id' => 'db-status',      'title' => 'Database Connection', 'desc' => (DB::connection()->getDriverName() ?? 'unknown') . ' database connectivity'],
                    ['id' => 'cache-status',   'title' => 'Cache System',        'desc' => ucfirst(config('cache.default') ?? 'unknown') . ' cache driver'],
                    ['id' => 'queue-status',   'title' => 'Queue Workers',       'desc' => 'Job queue processing status'],
                    ['id' => 'storage-status', 'title' => 'Storage',             'desc' => 'Storage writability check'],
                    ['id' => 'session-status', 'title' => 'Session Handler',     'desc' => ucfirst(config('session.driver') ?? 'unknown') . ' session driver'],
                ];
            @endphp

            @foreach($detailedChecks as $check)
                <div class="health-check-item p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $check['title'] }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $check['desc'] }}</p>
                        </div>
                        <span id="{{ $check['id'] }}"
                              class="text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-400">
                            <i class="fas fa-question-circle mr-1"></i>Pending
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('styles')
<style>
    .health-monitor { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }

    .health-score-card, .metric-card, .component-card, .chart-card, .checks-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .health-score-card:hover, .metric-card:hover, .component-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .history-period-btn { cursor: pointer; transition: all 0.2s ease; }

    .history-period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
    }

    @media (max-width: 768px) {
        .health-monitor { padding: 0 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const HealthMonitor = (function () {
        const ROUTES = {
            overall:     @json(route('developer.health.overall')),
            metrics:     @json(route('developer.health.metrics')),
            diagnostics: @json(route('developer.health.run-diagnostics')),
            trends:      @json(route('developer.health.trends')),
        };
        const CSRF = @json(csrf_token());

        const STATUS_STYLES = {
            healthy:     { dot: 'bg-green-500',  text: 'text-green-500',  ring: '#10b981' },
            operational: { dot: 'bg-green-500',  text: 'text-green-500',  ring: '#10b981' },
            degraded:    { dot: 'bg-yellow-500', text: 'text-yellow-500', ring: '#f59e0b' },
            warning:     { dot: 'bg-yellow-500', text: 'text-yellow-500', ring: '#f59e0b' },
            critical:    { dot: 'bg-red-500',    text: 'text-red-500',    ring: '#ef4444' },
            down:        { dot: 'bg-red-500',    text: 'text-red-500',    ring: '#ef4444' },
            unknown:     { dot: 'bg-gray-400',   text: 'text-gray-400',   ring: '#9ca3af' },
        };

        const BADGE_STYLES = {
            passed:  { cls: 'text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500', icon: 'fa-check-circle' },
            failed:  { cls: 'text-xs px-2 py-1 rounded-full bg-red-500/20 text-red-500',     icon: 'fa-exclamation-circle' },
            pending: { cls: 'text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-400',   icon: 'fa-question-circle' },
        };

        let lineChart = null;
        let doughnutChart = null;
        let refreshTimer = null;
        let refreshInFlight = false;

        /* ---------- utility ---------- */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error:   'bg-red-500',
                warning: 'bg-yellow-500',
                info:    'bg-blue-500',
            };
            const icons = {
                success: 'fa-check-circle',
                error:   'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info:    'fa-info-circle',
            };
            const el = document.createElement('div');
            el.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all ' + (colors[type] || colors.info);
            el.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fas ${icons[type] || icons.info} text-white"></i>
                    <span class="text-white">${escapeHtml(message)}</span>
                    <button class="text-white hover:text-gray-200"><i class="fas fa-times"></i></button>
                </div>`;
            el.querySelector('button').addEventListener('click', () => el.remove());
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 5000);
        }

        async function fetchJson(url, options = {}) {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {}),
                },
                credentials: 'same-origin',
                ...options,
            });
            if (!res.ok) {
                const err = new Error('HTTP ' + res.status);
                err.status = res.status;
                throw err;
            }
            return res.json();
        }

        function cssVar(name, fallback) {
            const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        }

        /* ---------- doughnut chart (overall score) ---------- */
        function initDoughnut(score, statusColor) {
            const canvas = document.getElementById('healthScoreDoughnut');
            if (!canvas || typeof Chart === 'undefined') return;

            if (doughnutChart) doughnutChart.destroy();

            const trackColor = cssVar('--bg-secondary', '#e2e8f0');
            const safeScore  = Math.max(0, Math.min(100, Number(score) || 0));

            doughnutChart = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [safeScore, 100 - safeScore],
                        backgroundColor: [statusColor, trackColor],
                        borderWidth: 0,
                        circumference: 360,
                        rotation: 0,
                    }],
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    cutout: '78%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false },
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: false,
                        duration: 600,
                    },
                },
            });
        }

        function updateDoughnut(score, statusColor) {
            if (!doughnutChart) {
                initDoughnut(score, statusColor);
                return;
            }
            const safeScore = Math.max(0, Math.min(100, Number(score) || 0));
            doughnutChart.data.datasets[0].data = [safeScore, 100 - safeScore];
            doughnutChart.data.datasets[0].backgroundColor = [
                statusColor,
                cssVar('--bg-secondary', '#e2e8f0'),
            ];
            doughnutChart.update();
        }

        /* ---------- line chart (history) ---------- */
        function setHistoryEmptyVisible(visible) {
            const el = document.getElementById('healthHistoryEmpty');
            const canvas = document.getElementById('healthHistoryChart');
            if (!el || !canvas) return;
            if (visible) {
                el.classList.remove('hidden');
                canvas.style.opacity = '0.15';
            } else {
                el.classList.add('hidden');
                canvas.style.opacity = '1';
            }
        }

        function initLineChart(initialData) {
            const canvas = document.getElementById('healthHistoryChart');
            if (!canvas || typeof Chart === 'undefined') return;

            const primary       = cssVar('--primary', '#3b82f6');
            const textSecondary = cssVar('--text-secondary', '#94a3b8');

            const safeData = Array.isArray(initialData) ? initialData : [];
            const labels   = safeData.map(d => d.date ?? '');
            const scores   = safeData.map(d => Number(d.score) || 0);

            if (lineChart) lineChart.destroy();

            lineChart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Health Score',
                        data: scores,
                        borderColor: primary,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: primary,
                        pointBorderColor: '#fff',
                        pointRadius: scores.length === 1 ? 6 : 4,
                        pointHoverRadius: 6,
                        spanGaps: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { labels: { color: textSecondary } },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Health Score: ' + ctx.raw + '/100',
                            },
                        },
                    },
                    scales: {
                        y: {
                            min: 0, max: 100,
                            ticks: { color: textSecondary, stepSize: 20 },
                            grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        },
                        x: {
                            ticks: { color: textSecondary },
                            grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        },
                    },
                },
            });

            setHistoryEmptyVisible(safeData.length === 0);
        }

        /* ---------- rendering ---------- */
        function updateHealthScore(score, status) {
            score  = Number(score) || 0;
            status = String(status || 'unknown').toLowerCase();
            const style = STATUS_STYLES[status] || STATUS_STYLES.unknown;

            const valueEl = document.getElementById('health-score-value');
            const textEl  = document.getElementById('health-status-text');

            if (valueEl) valueEl.textContent = score;
            if (textEl) {
                textEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                textEl.className = style.text;
            }

            updateDoughnut(score, style.ring);
            setLastUpdated();
        }

        function updateMetric(key, value) {
            const valueEl = document.getElementById('metric-' + key);
            const barEl   = document.getElementById('metric-' + key + '-bar');
            if (!valueEl || !barEl) return;

            const numeric = (value === null || value === undefined || value === '') ? null : Number(value);
            if (numeric === null || Number.isNaN(numeric)) {
                valueEl.textContent = 'N/A';
                barEl.style.width = '0%';
                return;
            }
            valueEl.textContent = numeric.toFixed(1) + '%';
            barEl.style.width = Math.min(100, Math.max(0, numeric)) + '%';

            const defaultColor = barEl.dataset.defaultColor || '#3b82f6';
            barEl.style.backgroundColor = numeric > 80 ? '#ef4444' : defaultColor;
        }

        function updateStatusBadge(elementId, passed, successMessage) {
            const badge = document.getElementById(elementId);
            if (!badge) return;
            const state = passed ? 'passed' : 'failed';
            const style = BADGE_STYLES[state];
            badge.className = style.cls;
            badge.innerHTML = `<i class="fas ${style.icon} mr-1"></i>${escapeHtml(passed ? successMessage : 'Failed')}`;
        }

        function setLastUpdated() {
            const el = document.getElementById('last-updated');
            if (el) el.textContent = '· updated ' + new Date().toLocaleTimeString();
        }

        function updateComponentCard(component, status, latency) {
            const card = document.querySelector(`[data-component="${component}"]`);
            if (!card) return;
            const style = STATUS_STYLES[String(status).toLowerCase()] || STATUS_STYLES.unknown;
            const dot   = card.querySelector('[data-role="component-dot"]');
            const text  = card.querySelector('[data-role="component-status-text"]');
            if (dot)  dot.className  = 'w-2 h-2 rounded-full ' + style.dot;
            if (text) {
                text.textContent = String(status).charAt(0).toUpperCase() + String(status).slice(1);
                text.className   = 'font-medium ' + style.text;
            }
            if (latency !== undefined) {
                const latEl = card.querySelector('[data-role="component-latency"]');
                if (latEl) latEl.textContent = latency ?? '—';
            }
        }

        /* ---------- data loads ---------- */
        async function refreshHealthData(silent = false) {
            if (refreshInFlight) return;
            refreshInFlight = true;

            if (!silent) showNotification('Refreshing health data...', 'info');
            try {
                const [overall, metrics] = await Promise.all([
                    fetchJson(ROUTES.overall),
                    fetchJson(ROUTES.metrics),
                ]);

                updateHealthScore(overall.score, overall.status);

                if (overall.components) {
                    Object.entries(overall.components).forEach(([component, compStatus]) => {
                        updateComponentCard(component, compStatus);
                    });
                }

                if (metrics) {
                    updateMetric('cpu',    metrics.cpu);
                    updateMetric('memory', metrics.memory);
                    updateMetric('disk',   metrics.disk);
                }

                if (!silent) showNotification('Health data refreshed.', 'success');
            } catch (e) {
                console.error(e);
                if (!silent) showNotification('Failed to refresh health data.', 'error');
            } finally {
                refreshInFlight = false;
            }
        }

        async function runFullDiagnostics() {
            const btn = document.getElementById('run-diagnostics-btn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Running...'; }

            showNotification('Running full system diagnostics...', 'info');
            try {
                const data = await fetchJson(ROUTES.diagnostics, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Content-Type': 'application/json',
                    },
                });
                if (data.success) {
                    showNotification('Diagnostics completed.', 'success');
                    updateDiagnosticsResults(data.diagnostics || {});
                } else {
                    showNotification('Diagnostics failed: ' + (data.message || 'Unknown error'), 'error');
                }
            } catch (e) {
                console.error(e);
                showNotification('Error running diagnostics.', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stethoscope mr-2"></i>Run Diagnostics'; }
            }
        }

        function updateDiagnosticsResults(diagnostics) {
            const map = {
                database: ['db-status',      'Connected'],
                cache:    ['cache-status',   'Operational'],
                storage:  ['storage-status', 'Writable'],
                session:  ['session-status', 'Active'],
                queue:    ['queue-status',   'Running'],
            };
            Object.entries(map).forEach(([key, [elId, msg]]) => {
                const result = diagnostics[key];
                if (result) updateStatusBadge(elId, !!result.passed, msg);
            });
        }

        async function loadTrends(period) {
            try {
                const data = await fetchJson(`${ROUTES.trends}?period=${encodeURIComponent(period)}`);
                const trends = Array.isArray(data.trends) ? data.trends : [];

                if (trends.length === 0) {
                    if (lineChart) { lineChart.destroy(); lineChart = null; }
                    setHistoryEmptyVisible(true);
                    return;
                }

                setHistoryEmptyVisible(false);

                if (lineChart) {
                    lineChart.data.labels = trends.map(t => t.date ?? '');
                    lineChart.data.datasets[0].data = trends.map(t => Number(t.score) || 0);
                    lineChart.data.datasets[0].pointRadius = trends.length === 1 ? 6 : 4;
                    lineChart.update();
                } else {
                    initLineChart(trends);
                }
            } catch (e) {
                console.error(e);
                showNotification('Failed to load trends.', 'error');
            }
        }

        /* ---------- init ---------- */
        function bindPeriodButtons() {
            document.querySelectorAll('.history-period-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const period = this.dataset.period;
                    document.querySelectorAll('.history-period-btn').forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = '';
                        b.style.color = '';
                    });
                    this.classList.add('active');
                    this.style.backgroundColor = 'var(--primary)';
                    this.style.color = 'white';
                    loadTrends(period);
                });
            });
        }

        function startAutoRefresh(seconds = 60) {
            if (refreshTimer) clearInterval(refreshTimer);
            refreshTimer = setInterval(() => refreshHealthData(true), seconds * 1000);
        }

        function init(initialHistory, initialScore, initialStatus) {
            const statusStyle = STATUS_STYLES[String(initialStatus || 'unknown').toLowerCase()]
                || STATUS_STYLES.unknown;

            initDoughnut(initialScore, statusStyle.ring);
            initLineChart(initialHistory || []);
            bindPeriodButtons();

            // Kick off diagnostics so the "Pending" badges resolve on first render.
            runFullDiagnostics().catch(() => { /* silent */ });
            startAutoRefresh(60);
        }

        return { init, refreshHealthData, runFullDiagnostics, loadTrends };
    })();

    document.addEventListener('DOMContentLoaded', function () {
        HealthMonitor.init(
            @json($healthHistory),
            {{ $score }},
            @json($status)
        );
    });
</script>
@endpush
@endsection