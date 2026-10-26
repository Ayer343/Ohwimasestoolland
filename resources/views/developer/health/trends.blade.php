@extends('layouts.dev')

@section('title', 'Health Trends & Alerts - Developer')

@section('content')
@include('developer.health._nav')

<div class="trends-page max-w-7xl mx-auto px-4">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Health Trends & Alerts
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Historical scores and any non-healthy reports.
                <span id="trend-last-updated" class="ml-2 text-xs"></span>
            </p>
        </div>
        <button onclick="TrendsPage.refresh()"
                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Trend Chart --}}
        <div class="lg:col-span-2 rounded-xl p-6"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                <h3 class="font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-area mr-2" style="color: var(--primary);"></i>Score Trend
                    <span id="trend-avg" class="ml-2 text-xs" style="color: var(--text-secondary);"></span>
                </h3>
                <div class="flex space-x-2">
                    <button class="period-btn px-3 py-1 rounded-lg text-sm active" data-period="7">7d</button>
                    <button class="period-btn px-3 py-1 rounded-lg text-sm" data-period="30">30d</button>
                    <button class="period-btn px-3 py-1 rounded-lg text-sm" data-period="90">90d</button>
                </div>
            </div>

            <div style="position: relative; min-height: 260px;">
                <canvas id="trendChart" height="220"></canvas>
                <div id="trendEmpty"
                     class="hidden absolute inset-0 flex items-center justify-center text-sm"
                     style="color: var(--text-secondary);">
                    <div class="text-center">
                        <i class="fas fa-chart-line text-3xl mb-2" style="opacity: 0.5;"></i>
                        <p>No trend data yet.</p>
                        <p class="text-xs mt-1">Generate reports to start tracking.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="rounded-xl p-6"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-bell mr-2" style="color: var(--primary);"></i>Active Alerts
                <span id="alerts-count" class="ml-2 text-xs font-normal"
                      style="color: var(--text-secondary);"></span>
            </h3>
            <div id="alerts-list" class="space-y-2">
                <p class="text-sm" style="color: var(--text-secondary);">Loading…</p>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .period-btn {
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .period-btn:hover { opacity: 0.85; }
    .period-btn.active {
        background-color: var(--primary) !important;
        color: white !important;
        font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const TrendsPage = (function () {
        const ROUTE_TRENDS = @json(route('developer.health.trends'));
        const ROUTE_ALERTS = @json(route('developer.health.alerts'));

        let chart = null;
        let currentPeriod = 7;

        /* ---------- utility ---------- */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function cssVar(name, fallback) {
            const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        }

        async function fetchJson(url) {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }

        function setLastUpdated() {
            const el = document.getElementById('trend-last-updated');
            if (el) el.textContent = '· updated ' + new Date().toLocaleTimeString();
        }

        /* ---------- empty state handling ---------- */
        function showEmptyTrend(show) {
            const empty = document.getElementById('trendEmpty');
            const canvas = document.getElementById('trendChart');
            if (!empty || !canvas) return;
            if (show) {
                empty.classList.remove('hidden');
                canvas.style.opacity = '0.15';
            } else {
                empty.classList.add('hidden');
                canvas.style.opacity = '1';
            }
        }

        /* ---------- trend chart ---------- */
        async function loadTrends(period) {
            currentPeriod = Number(period) || 7;
            const canvas = document.getElementById('trendChart');
            if (!canvas) return;

            let trends = [];
            let avg = null;

            try {
                const data = await fetchJson(`${ROUTE_TRENDS}?period=${encodeURIComponent(currentPeriod)}`);
                trends = Array.isArray(data.trends) ? data.trends : [];
                avg    = data.average_score ?? null;
            } catch (e) {
                console.error(e);
                trends = [];
            }

            // Average display
            const avgEl = document.getElementById('trend-avg');
            if (avgEl) {
                avgEl.textContent = (avg !== null && avg !== undefined)
                    ? `· avg ${Number(avg).toFixed(1)}/100`
                    : '';
            }

            // Destroy any prior chart before deciding
            if (chart) { chart.destroy(); chart = null; }

            // No data → show empty state, hide canvas
            if (trends.length === 0) {
                showEmptyTrend(true);
                setLastUpdated();
                return;
            }

            showEmptyTrend(false);

            const primary = cssVar('--primary', '#3b82f6');
            const text    = cssVar('--text-secondary', '#94a3b8');
            const gridC   = 'rgba(148, 163, 184, 0.1)';

            const labels = trends.map(t => t.date ?? '');
            const scores = trends.map(t => Number(t.score) || 0);
            const isSingle = scores.length === 1;

            chart = new Chart(canvas.getContext('2d'), {
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
                        pointRadius: isSingle ? 10 : 5,
                        pointHoverRadius: isSingle ? 12 : 7,
                        pointBackgroundColor: primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        spanGaps: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { labels: { color: text } },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Health Score: ' + ctx.raw + '/100',
                            },
                        },
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: { color: text, stepSize: 20 },
                            grid: { color: gridC },
                        },
                        x: {
                            ticks: { color: text },
                            grid: { color: gridC },
                        },
                    },
                },
            });

            setLastUpdated();
        }

        /* ---------- alerts ---------- */
        async function loadAlerts() {
            const wrap = document.getElementById('alerts-list');
            const countEl = document.getElementById('alerts-count');

            if (!wrap) return;

            try {
                const data = await fetchJson(ROUTE_ALERTS);
                const alerts = Array.isArray(data.alerts) ? data.alerts : [];

                if (countEl) {
                    countEl.textContent = alerts.length ? `(${alerts.length})` : '';
                }

                if (!alerts.length) {
                    wrap.innerHTML = `
                        <div class="text-center py-6" style="color: var(--text-secondary);">
                            <i class="fas fa-check-circle text-3xl mb-2 text-green-500"></i>
                            <p class="text-sm text-green-500 font-medium">No active alerts</p>
                            <p class="text-xs mt-1">All recent reports are healthy.</p>
                        </div>`;
                    return;
                }

                wrap.innerHTML = alerts.map(a => {
                    const statusRaw = String(a.status || 'unknown').toLowerCase();
                    const cls =
                        statusRaw === 'critical' ? 'text-red-500'
                        : statusRaw === 'degraded' || statusRaw === 'warning' ? 'text-yellow-500'
                        : 'text-gray-400';

                    return `
                        <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        ${escapeHtml(a.date ?? '—')}
                                    </p>
                                    <p class="text-sm font-medium ${cls} mt-1">
                                        ${escapeHtml(a.status ?? 'unknown')} · ${Number(a.score) || 0}/100
                                    </p>
                                </div>
                                <i class="fas fa-exclamation-triangle text-sm ${cls}"></i>
                            </div>
                        </div>`;
                }).join('');
            } catch (e) {
                console.error(e);
                wrap.innerHTML = '<p class="text-sm text-red-500">Failed to load alerts.</p>';
            }
        }

        /* ---------- bindings ---------- */
        function bindPeriodButtons() {
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    loadTrends(this.dataset.period);
                });
            });
        }

        function refresh() {
            loadTrends(currentPeriod);
            loadAlerts();
        }

        function init() {
            bindPeriodButtons();
            loadTrends(7);
            loadAlerts();
        }

        document.addEventListener('DOMContentLoaded', init);

        return { init, refresh, loadTrends, loadAlerts };
    })();
</script>
@endpush
@endsection