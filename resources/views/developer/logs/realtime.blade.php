@extends('layouts.dev')

@section('title', 'Realtime Errors - Developer')

@section('content')
@include('developer.logs._nav')

<div class="log-realtime">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-satellite-dish mr-2" style="color: var(--primary);"></i>Realtime Errors
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Latest error-level entries · refreshes every 10s
                <span id="rt-updated" class="ml-2 text-xs"></span>
            </p>
        </div>
        <button id="rt-refresh" class="px-4 py-2 rounded-lg text-sm"
                style="background-color: var(--primary); color: white;">
            <i class="fas fa-sync-alt mr-2"></i>Refresh Now
        </button>
    </div>

    <div class="rounded-xl overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <table class="w-full text-sm">
            <thead style="background-color: var(--bg-secondary);">
                <tr style="color: var(--text-secondary);">
                    <th class="p-3 text-left w-44">Timestamp</th>
                    <th class="p-3 text-left">Message</th>
                </tr>
            </thead>
            <tbody id="rt-body">
                <tr>
                    <td colspan="2" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                        Loading…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@push('styles')
<style>
    .log-realtime { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-realtime { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script>
const RealtimeErrors = (function () {
    const URL = @json(route('developer.logs.recent.errors'));
    const body = document.getElementById('rt-body');
    const updated = document.getElementById('rt-updated');
    const btn = document.getElementById('rt-refresh');
    let timer = null;

    function escapeHtml(t) {
        const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML;
    }

    async function load() {
        try {
            const res = await fetch(URL + '?limit=100', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            const errors = Array.isArray(data.errors) ? data.errors : [];

            if (errors.length === 0) {
                body.innerHTML = `<tr><td colspan="2" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-check-circle text-3xl mb-2 opacity-50"></i>
                    <p>No recent errors.</p>
                </td></tr>`;
            } else {
                body.innerHTML = errors.map(e => `
                    <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                        <td class="p-3 font-mono text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                            ${escapeHtml(e.timestamp ?? '—')}
                        </td>
                        <td class="p-3 font-mono text-xs break-all">
                            ${escapeHtml((e.message || '').slice(0, 800))}
                        </td>
                    </tr>
                `).join('');
            }

            if (updated) updated.textContent = '· updated ' + new Date().toLocaleTimeString();
        } catch (e) {
            console.error(e);
            if (updated) updated.textContent = '· failed';
        }
    }

    function bind() {
        btn.addEventListener('click', load);
        load();
        timer = setInterval(load, 10_000);
    }

    return { bind };
})();

document.addEventListener('DOMContentLoaded', RealtimeErrors.bind);
</script>
@endpush
@endsection