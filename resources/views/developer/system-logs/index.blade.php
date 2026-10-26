@extends('layouts.dev')

@section('title', 'System Logs - Developer')

@section('content')
@include('developer.system-logs._nav')

@php
    $logs         = $logs         ?? collect();
    $filterOptions= $filterOptions?? [];
    $statistics   = $statistics   ?? [];

    $stats = [
        'total'      => (int) ($statistics['total_logs']      ?? 0),
        'errors'     => (int) ($statistics['error_logs']      ?? 0),
        'warnings'   => (int) ($statistics['warning_logs']    ?? 0),
        'resolved'   => (int) ($statistics['resolved_logs']   ?? 0),
        'unresolved' => (int) ($statistics['unresolved_logs'] ?? 0),
        'critical'   => (int) ($statistics['critical_logs']   ?? 0),
    ];

    $levelColor = [
        'emergency' => 'text-red-500',
        'alert'     => 'text-red-500',
        'critical'  => 'text-red-500',
        'error'     => 'text-red-400',
        'warning'   => 'text-yellow-500',
        'notice'    => 'text-blue-400',
        'info'      => 'text-blue-500',
        'debug'     => 'text-gray-400',
        'trace'     => 'text-gray-500',
    ];

    $levelBadge = [
        'emergency' => 'bg-red-500/20 text-red-500',
        'alert'     => 'bg-red-500/20 text-red-500',
        'critical'  => 'bg-red-500/20 text-red-500',
        'error'     => 'bg-red-500/20 text-red-400',
        'warning'   => 'bg-yellow-500/20 text-yellow-500',
        'notice'    => 'bg-blue-500/20 text-blue-400',
        'info'      => 'bg-blue-500/20 text-blue-500',
        'debug'     => 'bg-gray-500/20 text-gray-400',
        'trace'     => 'bg-gray-500/20 text-gray-500',
    ];
@endphp

<div class="system-logs">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>System Logs
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Search, filter, and manage system logs
                    <span id="last-updated" class="ml-2 text-xs"></span>
                </p>
            </div>
            <div class="flex gap-3">
                <button id="btn-refresh"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button id="btn-export"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-file-export mr-2"></i>Export CSV
                </button>
                <button id="btn-cleanup"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-broom mr-2"></i>Cleanup
                </button>
            </div>
        </div>
    </div>

    <!-- Stats strip -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        @foreach([
            ['label' => 'Total',      'value' => $stats['total'],      'icon' => 'fa-database',        'color' => 'var(--primary)'],
            ['label' => 'Errors',     'value' => $stats['errors'],     'icon' => 'fa-circle-exclamation','color' => '#ef4444'],
            ['label' => 'Warnings',   'value' => $stats['warnings'],   'icon' => 'fa-triangle-exclamation','color' => '#f59e0b'],
            ['label' => 'Critical',   'value' => $stats['critical'],   'icon' => 'fa-fire',            'color' => '#ef4444'],
            ['label' => 'Resolved',   'value' => $stats['resolved'],   'icon' => 'fa-circle-check',    'color' => '#10b981'],
            ['label' => 'Unresolved', 'value' => $stats['unresolved'], 'icon' => 'fa-clock',           'color' => '#3b82f6'],
        ] as $card)
            <div class="metric-card rounded-xl p-4"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas {{ $card['icon'] }}" style="color: {{ $card['color'] }};"></i>
                    <span class="text-xs" style="color: var(--text-secondary);">{{ $card['label'] }}</span>
                </div>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="rounded-xl p-4 mb-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <form id="filters-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Message, reference, file…"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Level</label>
                <select name="level" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All</option>
                    @foreach(($filterOptions['levels'] ?? []) as $k => $v)
                        <option value="{{ $k }}" @selected(request('level') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Severity</label>
                <select name="severity" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All</option>
                    @foreach(($filterOptions['severities'] ?? []) as $k => $v)
                        <option value="{{ $k }}" @selected(request('severity') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Source</label>
                <select name="source" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All</option>
                    @foreach(($filterOptions['sources'] ?? []) as $s)
                        <option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Component</label>
                <select name="component" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All</option>
                    @foreach(($filterOptions['components'] ?? []) as $c)
                        <option value="{{ $c }}" @selected(request('component') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Resolved</label>
                <select name="resolved" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All</option>
                    <option value="1" @selected(request('resolved') === '1')>Yes</option>
                    <option value="0" @selected(request('resolved') === '0')>No</option>
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div class="lg:col-span-4 flex justify-end gap-2 mt-2">
                <a href="{{ route('developer.system-logs.index') }}"
                   class="px-4 py-2 rounded-lg text-sm"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-times mr-2"></i>Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Bulk action bar (hidden until selection) -->
    <div id="bulk-bar" class="hidden rounded-xl p-3 mb-4 flex flex-wrap gap-2 items-center"
         style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
        <span class="text-sm" style="color: var(--text-primary);">
            <span id="bulk-count">0</span> selected
        </span>
        <div class="flex-1"></div>
        <button data-bulk="resolve"  class="px-3 py-1.5 rounded-lg text-sm" style="background-color: #10b981; color: white;"><i class="fas fa-check mr-1"></i>Resolve</button>
        <button data-bulk="archive"  class="px-3 py-1.5 rounded-lg text-sm" style="background-color: var(--primary); color: white;"><i class="fas fa-box-archive mr-1"></i>Archive</button>
        <button data-bulk="unarchive"class="px-3 py-1.5 rounded-lg text-sm" style="background-color: var(--primary); color: white;"><i class="fas fa-box-open mr-1"></i>Unarchive</button>
        <button data-bulk="assign"   class="px-3 py-1.5 rounded-lg text-sm" style="background-color: #3b82f6; color: white;"><i class="fas fa-user-plus mr-1"></i>Assign</button>
        <button data-bulk="escalate" class="px-3 py-1.5 rounded-lg text-sm" style="background-color: #ef4444; color: white;"><i class="fas fa-arrow-up mr-1"></i>Escalate</button>
    </div>

    <!-- Logs table -->
    <div class="rounded-xl overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: var(--bg-secondary);">
                    <tr style="color: var(--text-secondary);">
                        <th class="p-3 text-left w-8">
                            <input type="checkbox" id="select-all" class="rounded">
                        </th>
                        <th class="p-3 text-left">Reference</th>
                        <th class="p-3 text-left">Level</th>
                        <th class="p-3 text-left">Source</th>
                        <th class="p-3 text-left">Component</th>
                        <th class="p-3 text-left">Message</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">When</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $level = strtolower($log->level ?? 'info');
                            $badge = $levelBadge[$level] ?? $levelBadge['info'];
                        @endphp
                        <tr class="border-t hover:bg-black/5 transition-colors"
                            style="border-color: var(--border-color); color: var(--text-primary);">
                            <td class="p-3">
                                <input type="checkbox" class="log-checkbox rounded" value="{{ $log->id }}">
                            </td>
                            <td class="p-3 font-mono text-xs">{{ $log->reference_id ?? '—' }}</td>
                            <td class="p-3">
                                <span class="text-xs px-2 py-1 rounded-full {{ $badge }}">
                                    {{ strtoupper($level) }}
                                </span>
                            </td>
                            <td class="p-3">{{ $log->source ?? '—' }}</td>
                            <td class="p-3">{{ $log->component ?? '—' }}</td>
                            <td class="p-3 max-w-md truncate" title="{{ $log->message }}">
                                {{ \Illuminate\Support\Str::limit($log->message, 90) }}
                            </td>
                            <td class="p-3">
                                @if($log->resolved)
                                    <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                                        <i class="fas fa-check mr-1"></i>Resolved
                                    </span>
                                @else
                                    <span class="text-xs px-2 py-1 rounded-full bg-yellow-500/20 text-yellow-500">
                                        <i class="fas fa-clock mr-1"></i>Open
                                    </span>
                                @endif
                                @if($log->archived)
                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-400 ml-1">
                                        <i class="fas fa-box-archive mr-1"></i>Archived
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-xs" style="color: var(--text-secondary);">
                                {{ $log->created_at?->diffForHumans() }}
                            </td>
                            <td class="p-3 text-right">
                                <a href="{{ route('developer.system-logs.show', $log->id) }}"
                                   class="text-sm px-3 py-1.5 rounded-lg"
                                   style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-inbox text-3xl mb-2 opacity-50"></i>
                                <p>No logs match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs instanceof \Illuminate\Pagination\AbstractPaginator && $logs->hasPages())
            <div class="p-4 border-t" style="border-color: var(--border-color);">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .system-logs { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    .metric-card { transition: transform .2s ease, box-shadow .2s ease; }
    .metric-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,.1); }
    @media (max-width: 768px) { .system-logs { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script>
const SystemLogsIndex = (function () {
    const ROUTES = {
        bulk:   @json(route('developer.system-logs.bulk')),
        export: @json(route('developer.system-logs.export')),
        cleanup:@json(route('developer.system-logs.cleanup')),
    };
    const CSRF = @json(csrf_token());

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }

    function notify(message, type = 'info') {
        const colors = { success:'bg-green-500', error:'bg-red-500', warning:'bg-yellow-500', info:'bg-blue-500' };
        const icons  = { success:'fa-check-circle', error:'fa-exclamation-circle', warning:'fa-exclamation-triangle', info:'fa-info-circle' };
        const el = document.createElement('div');
        el.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ' + (colors[type] || colors.info);
        el.innerHTML = `<div class="flex items-center gap-3">
            <i class="fas ${icons[type] || icons.info} text-white"></i>
            <span class="text-white">${escapeHtml(message)}</span>
            <button class="text-white"><i class="fas fa-times"></i></button>
        </div>`;
        el.querySelector('button').addEventListener('click', () => el.remove());
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw Object.assign(new Error(data.message || 'HTTP ' + res.status), { data, status: res.status });
        return data;
    }

    function selectedIds() {
        return Array.from(document.querySelectorAll('.log-checkbox:checked')).map(c => Number(c.value));
    }

    function refreshBulkBar() {
        const ids = selectedIds();
        const bar = document.getElementById('bulk-bar');
        const cnt = document.getElementById('bulk-count');
        if (cnt) cnt.textContent = ids.length;
        if (bar) bar.classList.toggle('hidden', ids.length === 0);
    }

    async function bulk(action) {
        const ids = selectedIds();
        if (ids.length === 0) return notify('Select at least one log.', 'warning');

        let data = {};
        if (action === 'assign') {
            const uid = prompt('Assignee user ID (Developer/Super Admin):');
            if (!uid) return;
            data.user_id = Number(uid);
        } else if (action === 'escalate') {
            const reason = prompt('Escalation reason (min 10 chars):');
            if (!reason || reason.trim().length < 10) return notify('Reason too short.', 'warning');
            data.reason = reason.trim();
        }

        try {
            const res = await postJson(ROUTES.bulk, { log_ids: ids, action, data });
            notify(res.message || 'Bulk action complete.', 'success');
            setTimeout(() => window.location.reload(), 800);
        } catch (e) {
            notify(e.data?.message || e.message || 'Bulk action failed.', 'error');
        }
    }

    async function exportCsv() {
        try {
            const res = await fetch(ROUTES.export, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/csv',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({
                    format: 'csv',
                    filters: Object.fromEntries(new FormData(document.getElementById('filters-form')).entries()),
                }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'system_logs_export.csv'; a.click();
            URL.revokeObjectURL(url);
            notify('Export started.', 'success');
        } catch (e) {
            notify('Export failed.', 'error');
        }
    }

    async function cleanup() {
        const days = prompt('Retention days (30–3650):', '90');
        if (!days) return;
        try {
            const res = await postJson(ROUTES.cleanup, { retention_days: Number(days), archive_only: true });
            notify(res.message || 'Cleanup complete.', 'success');
        } catch (e) {
            notify(e.data?.message || e.message || 'Cleanup failed.', 'error');
        }
    }

    function init() {
        document.getElementById('select-all')?.addEventListener('change', function () {
            document.querySelectorAll('.log-checkbox').forEach(c => { c.checked = this.checked; });
            refreshBulkBar();
        });
        document.querySelectorAll('.log-checkbox').forEach(c => c.addEventListener('change', refreshBulkBar));
        document.querySelectorAll('[data-bulk]').forEach(b => b.addEventListener('click', () => bulk(b.dataset.bulk)));
        document.getElementById('btn-refresh')?.addEventListener('click', () => window.location.reload());
        document.getElementById('btn-export')?.addEventListener('click', exportCsv);
        document.getElementById('btn-cleanup')?.addEventListener('click', cleanup);
        document.getElementById('filters-form')?.addEventListener('submit', () => {
            document.getElementById('last-updated').textContent = '· ' + new Date().toLocaleTimeString();
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => SystemLogsIndex.init());
</script>
@endpush
@endsection