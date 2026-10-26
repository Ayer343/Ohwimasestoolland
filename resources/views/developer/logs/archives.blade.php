@extends('layouts.dev')

@section('title', 'Log Archives - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $archives = $archives ?? [];

    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    };
@endphp

<div class="log-archives">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-box-archive mr-2" style="color: var(--primary);"></i>Log Archives
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            {{ count($archives) }} archived log file{{ count($archives) === 1 ? '' : 's' }}
        </p>
    </div>

    <div class="rounded-xl overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <table class="w-full text-sm">
            <thead style="background-color: var(--bg-secondary);">
                <tr style="color: var(--text-secondary);">
                    <th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left w-32">Size</th>
                    <th class="p-3 text-left w-48">Modified</th>
                    <th class="p-3 text-right w-48">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($archives as $a)
                    <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                        <td class="p-3 font-mono text-xs">{{ $a['name'] }}</td>
                        <td class="p-3 text-xs" style="color: var(--text-secondary);">
                            {{ $formatBytes($a['size']) }}
                        </td>
                        <td class="p-3 text-xs" style="color: var(--text-secondary);">{{ $a['modified'] }}</td>
                        <td class="p-3 text-right">
                            <a href="{{ route('developer.logs.archives.show', $a['name']) }}"
                               class="text-xs px-3 py-1.5 rounded-lg mr-1"
                               style="background-color: var(--primary); color: white;">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <button class="text-xs px-3 py-1.5 rounded-lg btn-delete-archive"
                                    data-name="{{ $a['name'] }}"
                                    style="background-color: #ef4444; color: white;">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-box-open text-3xl mb-2 opacity-50"></i>
                            <p>No archives yet. Use bulk archive to create one.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('styles')
<style>
    .log-archives { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-archives { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF = @json(csrf_token());

    function notify(msg, type) {
        const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
        const el = document.createElement('div');
        el.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ' + (colors[type] || colors.info);
        el.innerHTML = `<div class="flex items-center gap-3"><span class="text-white">${msg}</span></div>`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    document.querySelectorAll('.btn-delete-archive').forEach(btn => {
        btn.addEventListener('click', async function () {
            const name = this.dataset.name;
            if (!confirm('Delete archive "' + name + '"?')) return;
            const url = @json(url('developer/logs/archives')) + '/' + encodeURIComponent(name);
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Delete failed');
                notify('Archive deleted.', 'success');
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                notify(e.message || 'Delete failed.', 'error');
            }
        });
    });
});
</script>
@endpush
@endsection