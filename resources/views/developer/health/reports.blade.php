@extends('layouts.dev')

@section('title', 'Health Reports - Developer')

@section('content')
@include('developer.health._nav')

<div class="health-reports max-w-6xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-file-medical mr-2" style="color: var(--primary);"></i>Health Reports
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Generate, inspect, and download historical health snapshots.
            </p>
        </div>
        <div class="flex gap-3">
            <button onclick="ReportsPage.loadReports()"
                    class="px-4 py-2 rounded-lg text-sm"
                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
            <button onclick="ReportsPage.generateReport()" id="gen-btn"
                    class="px-4 py-2 rounded-lg text-sm"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-plus mr-2"></i>Generate Report
            </button>
        </div>
    </div>

    <div class="rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div id="reports-list" class="space-y-3">
            <p class="text-sm" style="color: var(--text-secondary);">Loading reports…</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const ReportsPage = (function () {
        const ROUTES = {
            history:  @json(route('developer.health.history')),
            generate: @json(route('developer.health.generate-report')),
        };
        const VIEW_BASE = @json(url('/developer/health/reports'));
        const CSRF = @json(csrf_token());

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function fetchJson(url, opts = {}) {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(opts.headers || {}),
                },
                credentials: 'same-origin',
                ...opts,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }

        function statusColor(status) {
            const map = {
                healthy:  'text-green-500',
                degraded: 'text-yellow-500',
                critical: 'text-red-500',
            };
            return map[String(status).toLowerCase()] || 'text-gray-400';
        }

        async function loadReports() {
            const wrap = document.getElementById('reports-list');
            wrap.innerHTML = '<p class="text-sm" style="color: var(--text-secondary);">Loading…</p>';
            try {
                const data = await fetchJson(ROUTES.history);
                const rows = Array.isArray(data.history) ? data.history : [];

                if (!rows.length) {
                    wrap.innerHTML = `
                        <div class="text-center py-8" style="color: var(--text-secondary);">
                            <i class="fas fa-file-medical text-3xl mb-3" style="opacity: 0.5;"></i>
                            <p class="text-sm">No reports yet.</p>
                            <p class="text-xs mt-1">Click <strong>Generate Report</strong> to create one.</p>
                        </div>`;
                    return;
                }

                wrap.innerHTML = rows.map(r => {
                    const id = encodeURIComponent(r.id);
                    return `
                        <div class="flex justify-between items-center p-3 rounded-lg"
                             style="background-color: var(--bg-secondary);">
                            <div>
                                <p class="font-medium text-sm" style="color: var(--text-primary);">${escapeHtml(r.id)}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">${escapeHtml(r.generated_at ?? '—')}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium ${statusColor(r.status)}">
                                    ${escapeHtml(r.status ?? 'unknown')} (${Number(r.score) || 0}/100)
                                </span>
                                <a href="${VIEW_BASE}/${id}/view" target="_blank"
                                   class="text-xs px-3 py-1 rounded-lg"
                                   style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                                <a href="${VIEW_BASE}/${id}/download"
                                   class="text-xs px-3 py-1 rounded-lg"
                                   style="background-color: var(--card-bg); color: var(--text-secondary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                        </div>`;
                }).join('');
            } catch (e) {
                console.error(e);
                wrap.innerHTML = '<p class="text-sm text-red-500">Failed to load reports.</p>';
            }
        }

        async function generateReport() {
            const btn = document.getElementById('gen-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating…';
            try {
                await fetchJson(ROUTES.generate, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                });
                await loadReports();
            } catch (e) {
                console.error(e);
                alert('Failed to generate report');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus mr-2"></i>Generate Report';
            }
        }

        document.addEventListener('DOMContentLoaded', loadReports);

        return { loadReports, generateReport };
    })();
</script>
@endpush
@endsection