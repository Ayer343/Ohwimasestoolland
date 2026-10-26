@extends('layouts.dev')

@section('title', 'Restore from Backup — Developer')

@section('content')
@php
    $backups = $backups ?? [];
    $sizeHuman = fn (int $b): string => $b >= 1073741824
        ? round($b / 1073741824, 2) . ' GB'
        : ($b >= 1048576 ? round($b / 1048576, 2) . ' MB' : round($b / 1024, 2) . ' KB');
@endphp

<div class="max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-undo mr-2" style="color: var(--danger);"></i> Restore from Backup
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            This operation will replace the current database with the selected backup.
        </p>
    </div>

    {{-- Warning --}}
    <div class="card mb-6" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2); border-radius: 0.75rem;">
        <div class="p-5 flex items-start gap-3">
            <i class="fas fa-exclamation-triangle mt-1 text-lg" style="color: var(--danger);"></i>
            <div>
                <h3 class="font-semibold mb-1" style="color: var(--danger);">Danger zone</h3>
                <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                    <li>• The system will enter <strong>maintenance mode</strong> during the restore.</li>
                    <li>• All current data will be <strong>replaced</strong> by the backup contents.</li>
                    <li>• A 6-character verification code will be <strong>emailed to you</strong> to confirm the action.</li>
                    <li>• This action is logged and cannot be undone.</li>
                </ul>
            </div>
        </div>
    </div>

    <form id="restore-form" action="{{ route('developer.backup.restore') }}" method="POST">
        @csrf

        {{-- Backup selection --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-archive mr-2" style="color: var(--primary);"></i> Select Backup
                </h3>
            </div>
            <div class="p-6">
                @if(count($backups) === 0)
                    <p class="text-sm" style="color: var(--text-secondary);">
                        No backups available. Create one first.
                    </p>
                @else
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Backup file <span class="text-red-500">*</span>
                    </label>
                    <select name="backup_filename" id="backup_filename" required
                            class="w-full p-3 border rounded-lg"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">— Choose a backup —</option>
                        @foreach($backups as $b)
                            @php
                                $name  = $b['filename'] ?? $b['name'] ?? 'unknown';
                                $bytes = (int) ($b['size'] ?? 0);
                                $date  = $b['date'] ?? $b['created_at'] ?? '';
                            @endphp
                            <option value="{{ $name }}">
                                {{ $name }} — {{ $date }} — {{ $sizeHuman($bytes) }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        {{-- Verification --}}
        <div class="card mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> Confirmation
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="verification_code" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Verification code <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2 flex-wrap">
                        <input type="text" id="verification_code" name="verification_code"
                               inputmode="numeric" pattern="[0-9]*" maxlength="6"
                               autocomplete="off" required
                               class="w-40 p-3 border rounded-lg font-mono text-center tracking-widest"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="000000">
                        <button type="button" onclick="BackupRestore.sendCode()"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-paper-plane mr-2"></i> Email me a code
                        </button>
                    </div>
                    <div class="text-xs mt-2" id="code-status" style="color: var(--text-secondary);">
                        Click “Email me a code” to receive a 6-character verification code.
                    </div>
                </div>

                <div class="flex items-start">
                    <input type="checkbox" id="confirm_restore" name="confirm_restore" value="1" required
                           class="h-4 w-4 mt-1 rounded"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <label for="confirm_restore" class="ml-2 text-sm" style="color: var(--text-secondary);">
                        I understand this will replace the current database with the backup.
                        <span class="text-red-500">*</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 flex justify-between items-center flex-wrap gap-3">
                <a href="{{ route('developer.backup.history') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to History
                </a>
                <button type="submit" id="submit-restore" @if(count($backups) === 0) disabled @endif
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--danger); color: white; @if(count($backups) === 0) opacity: 0.5; cursor: not-allowed; @endif">
                    <i class="fas fa-undo mr-2"></i> Restore Now
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }

    #verification_code {
        letter-spacing: 0.5em;
        font-size: 1.15rem;
    }

    @media (prefers-reduced-motion: reduce) { .card { transition: none !important; } }
    @media (max-width: 768px) { .card { border-radius: 0.5rem; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const CSRF = @json(csrf_token());
    const CODE_URL = @json(route('developer.backup.verification-code'));

    function $(id) { return document.getElementById(id); }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
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

    function sendCode() {
        const status = $('code-status');
        status.textContent = 'Sending…';
        status.style.color = 'var(--text-secondary)';

        fetchJson(CODE_URL, { method: 'POST' })
            .then(data => {
                status.textContent = data.message || 'Verification code sent to your email.';
                status.style.color = 'var(--success)';
            })
            .catch(err => {
                status.textContent = 'Failed: ' + err.message;
                status.style.color = 'var(--danger)';
            });
    }

    window.BackupRestore = { sendCode };
})();
</script>
@endpush