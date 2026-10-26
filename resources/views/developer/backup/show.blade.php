@extends('layouts.dev')

@section('title', 'Backup Details — ' . ($backup['filename'] ?? $backup['name'] ?? 'Unknown'))

@section('content')
@php
    use App\Models\User;

    $filename  = $backup['filename'] ?? $backup['name'] ?? 'unknown';
    $sizeBytes = (int) ($backup['size'] ?? 0);
    $sizeHuman = $backup['size_formatted'] ?? (
        $sizeBytes >= 1073741824
            ? round($sizeBytes / 1073741824, 2) . ' GB'
            : ($sizeBytes >= 1048576
                ? round($sizeBytes / 1048576, 2) . ' MB'
                : round($sizeBytes / 1024, 2) . ' KB')
    );
    $createdAt = $backup['date'] ?? $backup['created_at'] ?? null;
    $verifyUrl = route('developer.backup.verify', ['filename' => $filename]);
    $download  = route('developer.backup.download', ['filename' => $filename]);
@endphp

<div class="max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-archive mr-2" style="color: var(--primary);"></i> Backup Details
            </h1>
            <p class="text-sm mt-1 font-mono truncate" style="color: var(--text-secondary);">
                {{ $filename }}
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('developer.backup.history') }}"
               class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
               style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-arrow-left mr-2"></i> Back to History
            </a>
            <a href="{{ route('developer.database.index') }}"
               class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
               style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-database mr-2"></i> Database
            </a>
        </div>
    </div>

    {{-- Summary Card --}}
    <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <div class="text-xs uppercase" style="color: var(--text-secondary);">Size</div>
                <div class="text-xl font-semibold" style="color: var(--text-primary);">{{ $sizeHuman }}</div>
            </div>
            <div>
                <div class="text-xs uppercase" style="color: var(--text-secondary);">Created</div>
                <div class="text-xl font-semibold" style="color: var(--text-primary);">
                    {{ $createdAt ? \Illuminate\Support\Carbon::parse($createdAt)->format('M d, Y H:i') : '—' }}
                </div>
                @if($createdAt)
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ \Illuminate\Support\Carbon::parse($createdAt)->diffForHumans() }}
                    </div>
                @endif
            </div>
            <div>
                <div class="text-xs uppercase" style="color: var(--text-secondary);">Type</div>
                <div class="text-xl font-semibold capitalize" style="color: var(--text-primary);">
                    {{ $backup['type'] ?? 'manual' }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase" style="color: var(--text-secondary);">Integrity</div>
                <div class="flex items-center gap-2">
                    <span class="badge" id="integrity-badge"
                          style="background-color: rgba(var(--text-secondary), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-question-circle mr-1"></i> Not verified
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tools mr-2" style="color: var(--primary);"></i> Actions
            </h3>
        </div>
        <div class="p-6 flex flex-wrap gap-3">
            <button type="button" onclick="BackupShow.verify()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-shield-alt mr-2"></i> Verify Integrity
            </button>

            <button type="button" onclick="BackupShow.download()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                    style="background-color: var(--success); color: white;">
                <i class="fas fa-download mr-2"></i> Download
            </button>

            <button type="button" onclick="BackupShow.confirmDelete()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                    style="background-color: var(--danger); color: white;">
                <i class="fas fa-trash-alt mr-2"></i> Delete Backup
            </button>
        </div>
    </div>

    {{-- Metadata --}}
    <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Metadata
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach((array) $backup as $key => $value)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td class="py-2 pr-4 font-medium align-top whitespace-nowrap"
                                    style="color: var(--text-secondary);">
                                    {{ ucfirst(str_replace('_', ' ', $key)) }}
                                </td>
                                <td class="py-2 break-all" style="color: var(--text-primary);">
                                    @if(is_array($value))
                                        <code class="text-xs">{{ json_encode($value) }}</code>
                                    @elseif(is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ $value === null ? '—' : $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Verify result panel (hidden until verification runs) --}}
    <div id="verify-panel" class="hidden card mb-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-shield-alt mr-2" style="color: var(--success);"></i> Verification Result
            </h3>
        </div>
        <div class="p-6" id="verify-content"></div>
    </div>
</div>

{{-- Hidden filename input used by JS --}}
<input type="hidden" id="backup-filename" value="{{ $filename }}">

{{-- Delete confirmation modal --}}
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
    <div class="card w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 id="deleteTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    Delete Backup
                </h3>
            </div>

            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                This will permanently delete <strong class="font-mono">{{ $filename }}</strong>.
                This action cannot be undone.
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="BackupShow.closeModal('deleteModal')"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    Cancel
                </button>
                <button type="button" onclick="BackupShow.deleteConfirmed()"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--danger); color: white;">
                    <i class="fas fa-trash-alt mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.4px;
    }

    @media (prefers-reduced-motion: reduce) {
        .card { transition: none !important; }
    }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const CSRF = @json(csrf_token());
    const FILENAME = @json($filename);

    const ROUTES = {
        verify:   @json($verifyUrl),
        download: @json($download),
        delete:   @json(route('developer.backup.delete')),
        history:  @json(route('developer.backup.history')),
    };

    function $(id) { return document.getElementById(id); }

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
        el.setAttribute('role', 'status');
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
            err.data = data;
            throw err;
        }
        return data;
    }

    /* ---------- Actions ---------- */

    function verify() {
        const badge = $('integrity-badge');
        badge.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verifying…';
        badge.style.backgroundColor = 'rgba(var(--info-rgb), 0.15)';
        badge.style.color = 'var(--info)';

        fetchJson(ROUTES.verify + '?filename=' + encodeURIComponent(FILENAME))
            .then(data => {
                const passed = !!data.success;

                badge.innerHTML = passed
                    ? '<i class="fas fa-check-circle mr-1"></i> Verified'
                    : '<i class="fas fa-times-circle mr-1"></i> Failed';
                badge.style.backgroundColor = passed
                    ? 'rgba(var(--success-rgb), 0.15)'
                    : 'rgba(var(--danger-rgb), 0.15)';
                badge.style.color = passed ? 'var(--success)' : 'var(--danger)';

                const panel   = $('verify-panel');
                const content = $('verify-content');
                panel.classList.remove('hidden');

                const rows = Object.entries(data)
                    .filter(([k]) => !['cached', 'cache_expires_at'].includes(k))
                    .map(([k, v]) => `
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span style="color: var(--text-secondary);">${escapeHtml(k.replace(/_/g, ' '))}</span>
                            <span class="font-mono text-sm" style="color: var(--text-primary);">
                                ${escapeHtml(typeof v === 'object' ? JSON.stringify(v) : String(v))}
                            </span>
                        </div>`)
                    .join('');

                content.innerHTML = rows || '<p class="text-sm" style="color: var(--text-secondary);">No additional details.</p>';
                toast(passed ? 'Backup verified.' : 'Backup verification failed.', passed ? 'success' : 'error');
            })
            .catch(err => {
                console.error('verify', err);
                badge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error';
                badge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.15)';
                badge.style.color = 'var(--danger)';
                toast('Verification failed: ' + err.message, 'error');
            });
    }

    function download() {
        const url = ROUTES.download + '?filename=' + encodeURIComponent(FILENAME);
        const link = document.createElement('a');
        link.href = url;
        link.download = FILENAME;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast('Download started.', 'success');
    }

    function confirmDelete() {
        $('deleteModal').classList.remove('hidden');
    }

    function closeModal(id) {
        const el = $(id);
        if (el) el.classList.add('hidden');
    }

    function deleteConfirmed() {
        closeModal('deleteModal');

        fetchJson(ROUTES.delete, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: FILENAME }),
        })
        .then(() => {
            toast('Backup deleted. Redirecting…', 'success');
            setTimeout(() => { window.location.href = ROUTES.history; }, 600);
        })
        .catch(err => {
            console.error('delete', err);
            toast('Delete failed: ' + err.message, 'error');
        });
    }

    /* ---------- Wire up ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', e => {
                if (e.target === modal) modal.classList.add('hidden');
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal('deleteModal');
        });
    });

    window.BackupShow = {
        verify,
        download,
        confirmDelete,
        deleteConfirmed,
        closeModal,
    };
})();
</script>
@endpush