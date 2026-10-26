@extends('layouts.dev')

@section('title', 'Log ' . ($log->reference_id ?? $log->id) . ' - Developer')

@section('content')
@include('developer.system-logs._nav')

@php
    $level    = strtolower($log->level ?? 'info');
    $badgeMap = [
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
    $badge = $badgeMap[$level] ?? $badgeMap['info'];

    $isResolved = (bool) $log->resolved;
    $isArchived = (bool) $log->archived;
@endphp

<div class="log-show">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-start flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route('developer.system-logs.index') }}"
                       class="text-sm px-3 py-1.5 rounded-lg"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                    <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                        <i class="fas fa-file-lines mr-2" style="color: var(--primary);"></i>
                        {{ $log->reference_id ?? ('Log #' . $log->id) }}
                    </h1>
                    <span class="text-xs px-2 py-1 rounded-full {{ $badge }}">
                        {{ strtoupper($level) }}
                    </span>
                    @if($isResolved)
                        <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">
                            <i class="fas fa-check mr-1"></i>Resolved
                        </span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-500/20 text-yellow-500">
                            <i class="fas fa-clock mr-1"></i>Open
                        </span>
                    @endif
                    @if($isArchived)
                        <span class="text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-400">
                            <i class="fas fa-box-archive mr-1"></i>Archived
                        </span>
                    @endif
                </div>
                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                    Created {{ $log->created_at?->diffForHumans() }}
                    @if($log->source) · Source: {{ $log->source }}@endif
                    @if($log->component) · Component: {{ $log->component }}@endif
                </p>
            </div>

            <div class="flex gap-2 flex-wrap" id="actions-bar">
                @if(!$isResolved)
                    <button data-action="resolve" class="px-3 py-2 rounded-lg text-sm" style="background-color: #10b981; color: white;">
                        <i class="fas fa-check mr-1"></i>Resolve
                    </button>
                @endif
                <button data-action="assign" class="px-3 py-2 rounded-lg text-sm" style="background-color: #3b82f6; color: white;">
                    <i class="fas fa-user-plus mr-1"></i>Assign
                </button>
                <button data-action="escalate" class="px-3 py-2 rounded-lg text-sm" style="background-color: #ef4444; color: white;">
                    <i class="fas fa-arrow-up mr-1"></i>Escalate
                </button>
                <button data-action="alert" class="px-3 py-2 rounded-lg text-sm" style="background-color: #f59e0b; color: white;">
                    <i class="fas fa-bell mr-1"></i>Alert
                </button>
                @if(!$isArchived)
                    <button data-action="archive" class="px-3 py-2 rounded-lg text-sm" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-box-archive mr-1"></i>Archive
                    </button>
                @else
                    <button data-action="unarchive" class="px-3 py-2 rounded-lg text-sm" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-box-open mr-1"></i>Unarchive
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Message / context -->
            <div class="rounded-xl p-5"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-circle-info mr-2" style="color: var(--primary);"></i>Message
                </h3>
                <p class="text-sm whitespace-pre-wrap break-words" style="color: var(--text-primary);">{{ $log->message }}</p>

                @if($log->summary)
                    <div class="mt-4 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-xs font-semibold mb-1" style="color: var(--text-secondary);">Summary</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $log->summary }}</p>
                    </div>
                @endif

                @if($log->file || $log->line)
                    <div class="mt-4 p-3 rounded-lg font-mono text-xs"
                         style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-file-code mr-2"></i>
                        {{ $log->file ?? '—' }}@if($log->line):{{ $log->line }}@endif
                    </div>
                @endif
            </div>

            <!-- Comments -->
            <div class="rounded-xl p-5"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-comments mr-2" style="color: var(--primary);"></i>
                    Comments ({{ $log->comments->count() }})
                </h3>

                <div id="comments-list" class="space-y-3 mb-4">
                    @forelse($log->comments as $comment)
                        <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $comment->created_by_name ?? 'Unknown' }}
                                </span>
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    {{ $comment->created_at?->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">{{ $comment->comment }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-center py-4" style="color: var(--text-secondary);">
                            No comments yet.
                        </p>
                    @endforelse
                </div>

                <form id="comment-form" class="mt-4">
                    <textarea id="comment-input" rows="3"
                              placeholder="Add a comment…"
                              class="w-full px-3 py-2 rounded-lg text-sm"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-paper-plane mr-1"></i>Post Comment
                        </button>
                    </div>
                </form>
            </div>

            <!-- Similar / recurring -->
            @if(($similarLogs ?? collect())->isNotEmpty() || ($logHistory ?? collect())->isNotEmpty())
                <div class="rounded-xl p-5"
                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i>
                        Related Logs
                    </h3>
                    <ul class="space-y-2">
                        @foreach(($logHistory ?? collect())->take(10) as $rel)
                            <li class="flex justify-between items-center text-sm">
                                <a href="{{ route('developer.system-logs.show', $rel->id) }}"
                                   class="hover:underline truncate max-w-md"
                                   style="color: var(--primary);">
                                    {{ \Illuminate\Support\Str::limit($rel->message, 80) }}
                                </a>
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    {{ $rel->created_at?->diffForHumans() }}
                                </span>
                            </li>
                        @endforeach
                        @foreach(($similarLogs ?? collect())->take(5) as $rel)
                            <li class="flex justify-between items-center text-sm">
                                <a href="{{ route('developer.system-logs.show', $rel->id) }}"
                                   class="hover:underline truncate max-w-md"
                                   style="color: var(--primary);">
                                    {{ \Illuminate\Support\Str::limit($rel->message, 80) }}
                                </a>
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    {{ $rel->created_at?->diffForHumans() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Side column -->
        <div class="space-y-6">
            <!-- Metadata -->
            <div class="rounded-xl p-5"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-circle-info mr-2" style="color: var(--primary);"></i>Details
                </h3>
                <dl class="text-sm space-y-2">
                    @php
                        $meta = [
                            'Severity'   => $log->severity ?? '—',
                            'Priority'   => $log->priority ?? '—',
                            'Group'      => $log->log_group ?? '—',
                            'User'       => $log->user?->name ?? '—',
                            'Assigned'   => $log->assignee?->name ?? '—',
                            'Resolved by'=> $log->resolver?->name ?? '—',
                            'Response'   => $log->response_time_ms ? $log->response_time_ms . ' ms' : '—',
                            'Code'       => $log->response_code ?? '—',
                            'Occurrences'=> $log->occurrence_count ?? '—',
                            'Recurring'  => $log->is_recurring ? 'Yes' : 'No',
                            'SLA breached'=> $log->sla_breached ? 'Yes' : 'No',
                        ];
                    @endphp
                    @foreach($meta as $k => $v)
                        <div class="flex justify-between gap-3">
                            <dt style="color: var(--text-secondary);">{{ $k }}</dt>
                            <dd class="text-right" style="color: var(--text-primary);">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <!-- Attachments -->
            <div class="rounded-xl p-5"
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-paperclip mr-2" style="color: var(--primary);"></i>
                        Attachments ({{ $log->attachments->count() }})
                    </h3>
                    <label class="cursor-pointer text-xs px-2 py-1 rounded-lg"
                           style="background-color: var(--primary); color: white;">
                        <i class="fas fa-upload mr-1"></i>Upload
                        <input type="file" id="attachment-input" class="hidden">
                    </label>
                </div>

                <ul id="attachments-list" class="space-y-2">
                    @forelse($log->attachments as $att)
                        <li class="flex justify-between items-center p-2 rounded-lg text-sm"
                            style="background-color: var(--bg-secondary);"
                            data-attachment-id="{{ $att->id }}">
                            <div class="truncate">
                                <i class="fas fa-file mr-2" style="color: var(--primary);"></i>
                                <a href="{{ route('developer.system-logs.attachments.download', ['log' => $log->id, 'attachment' => $att->id]) }}"
                                   class="hover:underline"
                                   style="color: var(--text-primary);">
                                    {{ $att->original_filename }}
                                </a>
                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">
                                    {{ $att->created_at?->diffForHumans() }}
                                </span>
                            </div>
                            <button class="text-red-500 hover:text-red-400 btn-delete-attachment"
                                    data-id="{{ $att->id }}"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </li>
                    @empty
                        <li id="attachments-empty" class="text-sm text-center py-3"
                            style="color: var(--text-secondary);">
                            No attachments yet.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-show { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-show { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script>
const SystemLogShow = (function () {
    const ROUTES = {
        resolve:  @json(route('developer.system-logs.resolve',  $log->id)),
        assign:   @json(route('developer.system-logs.assign',   $log->id)),
        escalate: @json(route('developer.system-logs.escalate', $log->id)),
        comment:  @json(route('developer.system-logs.comment',  $log->id)),
        alert:    @json(route('developer.system-logs.alert',    $log->id)),
        archive:  @json(route('developer.system-logs.archive',  $log->id)),
        unarchive:@json(route('developer.system-logs.unarchive',$log->id)),
        attachUpload: @json(route('developer.system-logs.attachments.upload', $log->id)),
        attachBase:   @json(url('developer/system-logs/' . $log->id . '/attachments')),
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
        if (!res.ok) throw Object.assign(new Error(data.message || 'HTTP ' + res.status), { data });
        return data;
    }

    async function action(name) {
        try {
            let data = {};
            switch (name) {
                case 'resolve': {
                    const notes = prompt('Resolution notes (optional):') ?? '';
                    data = { resolution_notes: notes };
                    break;
                }
                case 'assign': {
                    const uid = prompt('Assignee user ID (Developer/Super Admin):');
                    if (!uid) return;
                    data = { user_id: Number(uid) };
                    break;
                }
                case 'escalate': {
                    const reason = prompt('Escalation reason (min 10 chars):');
                    if (!reason || reason.trim().length < 10) return notify('Reason too short.', 'warning');
                    data = { reason: reason.trim() };
                    break;
                }
                case 'alert': {
                    const channels = (prompt('Channels (comma separated: email,sms,whatsapp,in_app,slack):', 'email') || '')
                        .split(',').map(s => s.trim()).filter(Boolean);
                    if (!channels.length) return;
                    data = { channels, message: prompt('Alert message (optional):') ?? '' };
                    break;
                }
            }
            const res = await postJson(ROUTES[name], data);
            notify(res.message || 'Done.', 'success');
            setTimeout(() => window.location.reload(), 700);
        } catch (e) {
            notify(e.data?.message || e.message || 'Action failed.', 'error');
        }
    }

    async function postComment(e) {
        e.preventDefault();
        const input = document.getElementById('comment-input');
        const text = input.value.trim();
        if (text.length < 5) return notify('Comment must be at least 5 characters.', 'warning');
        try {
            const res = await postJson(ROUTES.comment, { comment: text });
            notify('Comment posted.', 'success');

            const list = document.getElementById('comments-list');
            const el = document.createElement('div');
            el.className = 'p-3 rounded-lg';
            el.style.backgroundColor = 'var(--bg-secondary)';
            el.innerHTML = `
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">${escapeHtml(res.comment.created_by)}</span>
                    <span class="text-xs" style="color: var(--text-secondary);">${escapeHtml(res.comment.time_ago)}</span>
                </div>
                <p class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">${escapeHtml(res.comment.comment)}</p>`;
            list.prepend(el);
            input.value = '';
        } catch (err) {
            notify(err.data?.message || err.message || 'Failed to post comment.', 'error');
        }
    }

    async function uploadAttachment(file) {
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch(ROUTES.attachUpload, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Upload failed');
            notify('Attachment uploaded.', 'success');
            setTimeout(() => window.location.reload(), 700);
        } catch (e) {
            notify(e.message || 'Upload failed.', 'error');
        }
    }

    async function deleteAttachment(id) {
        if (!confirm('Delete this attachment?')) return;
        try {
            const res = await fetch(ROUTES.attachBase + '/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Delete failed');
            notify('Attachment deleted.', 'success');
            document.querySelector(`[data-attachment-id="${id}"]`)?.remove();
        } catch (e) {
            notify(e.message || 'Delete failed.', 'error');
        }
    }

    function init() {
        document.querySelectorAll('[data-action]').forEach(b => {
            b.addEventListener('click', () => action(b.dataset.action));
        });
        document.getElementById('comment-form')?.addEventListener('submit', postComment);
        document.getElementById('attachment-input')?.addEventListener('change', function () {
            if (this.files[0]) uploadAttachment(this.files[0]);
            this.value = '';
        });
        document.querySelectorAll('.btn-delete-attachment').forEach(b => {
            b.addEventListener('click', () => deleteAttachment(b.dataset.id));
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => SystemLogShow.init());
</script>
@endpush
@endsection