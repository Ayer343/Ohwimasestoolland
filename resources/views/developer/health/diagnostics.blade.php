@extends('layouts.dev')

@section('title', 'System Diagnostics - Developer')

@section('content')
@include('developer.health._nav')
<div class="diagnostics-page max-w-7xl mx-auto px-4">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-stethoscope mr-2" style="color: var(--primary);"></i>System Diagnostics
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Run full diagnostics across database, cache, storage, queue, session, and environment.
            </p>
        </div>
        <button onclick="runDiagnostics()" id="run-btn"
                class="px-4 py-2 rounded-lg text-sm"
                style="background-color: var(--primary); color: white;">
            <i class="fas fa-play mr-2"></i>Run Diagnostics
        </button>
    </div>

    <div id="diag-summary" class="rounded-xl p-4 mb-6 hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <p class="font-medium" style="color: var(--text-primary);">Overall: <span id="diag-summary-text">—</span></p>
        <p class="text-xs mt-1" style="color: var(--text-secondary);" id="diag-timestamp"></p>
    </div>

    <div class="rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div id="diag-results" class="space-y-3">
            <p class="text-sm" style="color: var(--text-secondary);">Click "Run Diagnostics" to start.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const ROUTE_DIAG = @json(route('developer.health.run-diagnostics'));
    const CSRF = @json(csrf_token());

    async function runDiagnostics() {
        const btn = document.getElementById('run-btn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Running…';
        document.getElementById('diag-results').innerHTML =
            '<p class="text-sm" style="color: var(--text-secondary);">Running diagnostics…</p>';
        try {
            const res = await fetch(ROUTE_DIAG, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            const summary = document.getElementById('diag-summary');
            summary.classList.remove('hidden');
            document.getElementById('diag-summary-text').textContent =
                data.all_passed ? 'All checks passed' : 'One or more checks failed';
            document.getElementById('diag-summary-text').className =
                data.all_passed ? 'text-green-500' : 'text-red-500';
            document.getElementById('diag-timestamp').textContent = data.timestamp || '';

            const wrap = document.getElementById('diag-results');
            wrap.innerHTML = Object.entries(data.diagnostics || {}).map(([name, r]) => {
                const color = r.passed ? 'text-green-500' : 'text-red-500';
                const icon  = r.passed ? 'fa-check-circle' : 'fa-exclamation-circle';
                return `
                    <div class="flex justify-between items-center p-3 rounded-lg"
                         style="background-color: var(--bg-secondary);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">${name}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">${r.message ?? ''}</p>
                        </div>
                        <span class="text-xs ${color}"><i class="fas ${icon} mr-1"></i>${r.passed ? 'Passed' : 'Failed'}</span>
                    </div>`;
            }).join('');
        } catch (e) {
            document.getElementById('diag-results').innerHTML =
                '<p class="text-sm text-red-500">Diagnostics failed to run.</p>';
        } finally {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-play mr-2"></i>Run Diagnostics';
        }
    }
</script>
@endpush
@endsection