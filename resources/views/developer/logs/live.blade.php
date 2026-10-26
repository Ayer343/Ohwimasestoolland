@extends('layouts.dev')

@section('title', 'Live Logs - Developer')

@section('content')
@include('developer.logs._nav')

<div class="log-live">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Live Log Tail
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Streaming recent entries from <code>laravel.log</code>
                <span id="live-status" class="ml-2 text-xs"></span>
            </p>
        </div>
        <div class="flex gap-2">
            <button id="btn-toggle" class="px-4 py-2 rounded-lg text-sm"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-pause mr-2"></i>Pause
            </button>
            <button id="btn-clear" class="px-4 py-2 rounded-lg text-sm"
                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-trash mr-2"></i>Clear
            </button>
        </div>
    </div>

    <div id="live-output"
         class="rounded-xl p-4 font-mono text-xs overflow-y-auto"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);
                color: var(--text-primary); height: 70vh; white-space: pre-wrap;">
        <p style="color: var(--text-secondary);">Waiting for data…</p>
    </div>
</div>

@push('styles')
<style>
    .log-live { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-live { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script>
const LiveLog = (function () {
    const URL = @json(route('developer.logs.stream'));
    const out = document.getElementById('live-output');
    const statusEl = document.getElementById('live-status');
    const btn = document.getElementById('btn-toggle');
    const clear = document.getElementById('btn-clear');

    let controller = null;
    let running = true;
    let lastText = '';

    function setStatus(t) { if (statusEl) statusEl.textContent = '· ' + t; }

    async function loop() {
        while (running) {
            try {
                controller = new AbortController();
                const res = await fetch(URL + '?t=' + Date.now(), {
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.body) throw new Error('no body');
                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buf = '';

                setStatus('streaming');
                while (running) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buf += decoder.decode(value, { stream: true });
                    // Chunk buffered output
                    if (buf.length > 0) {
                        lastText += buf;
                        buf = '';
                        // Keep last ~500 KB on screen
                        if (lastText.length > 500_000) lastText = lastText.slice(-500_000);
                        out.textContent = lastText;
                        out.scrollTop = out.scrollHeight;
                    }
                }
                setStatus('reconnecting…');
            } catch (e) {
                if (!running) break;
                setStatus('error — retrying');
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    }

    function bind() {
        btn.addEventListener('click', () => {
            running = !running;
            btn.innerHTML = running
                ? '<i class="fas fa-pause mr-2"></i>Pause'
                : '<i class="fas fa-play mr-2"></i>Resume';
            if (running) loop();
            else if (controller) controller.abort();
        });

        clear.addEventListener('click', () => {
            lastText = '';
            out.textContent = '';
        });

        loop();
    }

    return { bind };
})();

document.addEventListener('DOMContentLoaded', LiveLog.bind);
</script>
@endpush
@endsection