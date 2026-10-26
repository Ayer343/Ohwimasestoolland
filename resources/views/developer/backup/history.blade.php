@extends('layouts.dev')

@section('title', 'Backup History — Developer')

@section('content')
@php
    $backups      = $backups ?? [];
    $totalBackups = (int) ($total_backups ?? count($backups));
    $totalSize    = (int) ($total_size ?? 0);
    $latest       = $latest_backup ?? null;
    $stats        = $stats ?? [];

    $sizeHuman = fn (int $b): string => $b >= 1073741824
        ? round($b / 1073741824, 2) . ' GB'
        : ($b >= 1048576 ? round($b / 1048576, 2) . ' MB' : round($b / 1024, 2) . ' KB');
@endphp

<div class="max-w-6xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-archive mr-2" style="color: var(--primary);"></i> Backup History
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                All backups available on this server
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('developer.database.index') }}"
               class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
               style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-database mr-2"></i> Database
            </a>
            <button type="button" onclick="BackupHistory.create()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-plus mr-2"></i> Create Backup
            </button>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="text-xs uppercase" style="color: var(--text-secondary);">Total Backups</div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalBackups) }}</div>
        </div>
        <div class="card p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="text-xs uppercase" style="color: var(--text-secondary);">Total Size</div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $sizeHuman($totalSize) }}</div>
        </div>
        <div class="card p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="text-xs uppercase" style="color: var(--text-secondary);">Latest</div>
            <div class="text-lg font-bold truncate" style="color: var(--text-primary);">
                {{ $latest['filename'] ?? $latest['name'] ?? '—' }}
            </div>
            @if($latest)
                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                    {{ $latest['date'] ?? $latest['created_at'] ?? '' }}
                </div>
            @endif
        </div>
    </div>

    {{-- Backup list --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i> All Backups
            </h3>
        </div>

        <div class="p-6">
            @if(count($backups) === 0)
                <div class="text-center py-12">
                    <i class="fas fa-archive text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">No backups yet</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        Create your first backup to get started.
                    </p>
                    <button type="button" onclick="BackupHistory.create()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 inline-flex items-center"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-plus mr-2"></i> Create Backup
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background-color: var(--bg-secondary);">
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase" style="color: var(--text-secondary);">Filename</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase" style="color: var(--text-secondary);">Date</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase" style="color: var(--text-secondary);">Size</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase" style="color: var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backups as $b)
                                @php
                                    $name = $b['filename'] ?? $b['name'] ?? 'unknown';
                                    $date = $b['date'] ?? $b['created_at'] ?? '';
                                    $bytes = (int) ($b['size'] ?? 0);
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="py-3 px-4 font-mono truncate max-w-xs" style="color: var(--text-primary);">
                                        <a href="{{ route('developer.backup.show', ['filename' => $name]) }}"
                                           class="hover:underline" style="color: var(--primary);">
                                            {{ $name }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $date }}</td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $sizeHuman($bytes) }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex gap-1 flex-wrap">
                                            <a href="{{ route('developer.backup.show', ['filename' => $name]) }}"
                                               class="p-2 rounded transition-all hover:opacity-80"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" onclick="BackupHistory.download('{{ $name }}')"
                                                    class="p-2 rounded transition-all hover:opacity-80"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                    title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button type="button" onclick="BackupHistory.remove('{{ $name }}')"
                                                    class="p-2 rounded transition-all hover:opacity-80"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                    title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }
    @media (prefers-reduced-motion: reduce) { .card { transition: none !important; } }
    @media (max-width: 768px) { .card { border-radius: 0.5rem; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const CSRF = @json(csrf_token());
    const ROUTES = {
        create:   @json(route('developer.backup.create')),
        download: @json(route('developer.backup.download')),
        delete:   @json(route('developer.backup.delete')),
        history:  @json(route('developer.backup.history')),
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function toast(message, type = 'info') {
        const palette = {
            success: ['success', 'fa-check-circle'],
            error:   ['danger',  'fa-exclamation-circle'],
            warning: ['warning', 'fa-exclamation-triangle'],
            info:    ['info',    'fa-info-circle'],
        };
        const [key, icon] = palette[type] || palette.info;

        const el = document.createElement('div');
        el.className = 'fixed top-4 right-4 z-[60] px-6 py-3 rounded-lg shadow-lg';
        el.style.cssText = `background-color: rgba(var(--${key}-rgb), 0.95); color: white; border-left: 4px solid var(--${key});`;
        el.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas ${icon}"></i>
                <span>${escapeHtml(message)}</span>
                <button type="button" class="ml-2 opacity-80 hover:opacity-100">
                    <i class="fas fa-times"></i>
                </button>
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
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.message || ('HTTP ' + res.status));
            err.status = res.status;
            throw err;
        }
        return data;
    }

    function create() {
        toast('Creating backup…', 'info');
        fetchJson(ROUTES.create, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
        })
        .then(() => {
            toast('Backup created.', 'success');
            setTimeout(() => window.location.reload(), 600);
        })
        .catch(err => toast('Failed to create backup: ' + err.message, 'error'));
    }

    function download(name) {
        const url = ROUTES.download + '?filename=' + encodeURIComponent(name);
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast('Download started.', 'success');
    }

    function remove(name) {
        if (!confirm(`Delete backup "${name}"? This cannot be undone.`)) return;

        fetchJson(ROUTES.delete, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename: name }),
        })
        .then(() => {
            toast('Backup deleted.', 'success');
            setTimeout(() => window.location.reload(), 600);
        })
        .catch(err => toast('Delete failed: ' + err.message, 'error'));
    }

    window.BackupHistory = { create, download, remove };
})();
</script>
@endpush