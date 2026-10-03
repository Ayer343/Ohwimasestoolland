{{-- resources/views/live-chat/agent-inbox.blade.php --}}

@php
    $__lcUser = auth()->user();
    $__lcLayout = match (true) {
        $__lcUser->isDeveloper() => 'layouts.dev',
        $__lcUser->isSuperAdmin() => 'layouts.app',
        $__lcUser->isAdmin()      => 'layouts.app',
        default                   => 'layouts.app',
    };

    // Stats for the strip
    $col       = $conversations->getCollection();
    $totalOpen = $col->whereIn('status', ['open', 'assigned'])->count();
    $mine      = $col->where('assigned_to', $__lcUser->id)->count();
    $unassigned= $col->whereNull('assigned_to')->count();
@endphp

@extends($__lcLayout)

@section('title', 'Agent Inbox')

@section('content')
<div class="lca-root">

    {{-- ================= HEADER ================= --}}
    <header class="lca-header">
        <div class="lca-header__main">
            <div class="lca-header__icon">
                <i class="fas fa-headset"></i>
            </div>
            <div>
                <h1 class="lca-header__title">Agent Inbox</h1>
                <p class="lca-header__sub">
                    Conversations waiting for you and your team.
                </p>
            </div>
        </div>
        <div class="lca-header__actions">
            <button type="button" class="lca-btn lca-btn--ghost" id="lcaPurgeBtn" title="Purge old closed conversations">
                <i class="fas fa-broom"></i>
                <span>Purge Closed</span>
            </button>
            <a href="{{ route('live-chat.index') }}" class="lca-btn lca-btn--ghost">
                <i class="fas fa-arrow-left"></i>
                <span>My Chats</span>
            </a>
        </div>
    </header>

    {{-- ================= STATS ================= --}}
    @if(!$conversations->isEmpty())
        <div class="lca-stats">
            <div class="lca-stat">
                <div class="lca-stat__icon lca-stat__icon--primary"><i class="fas fa-inbox"></i></div>
                <div>
                    <div class="lca-stat__value">{{ $totalOpen }}</div>
                    <div class="lca-stat__label">Open</div>
                </div>
            </div>
            <div class="lca-stat">
                <div class="lca-stat__icon lca-stat__icon--success"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="lca-stat__value">{{ $mine }}</div>
                    <div class="lca-stat__label">Assigned to me</div>
                </div>
            </div>
            <div class="lca-stat">
                <div class="lca-stat__icon lca-stat__icon--warning"><i class="fas fa-user-clock"></i></div>
                <div>
                    <div class="lca-stat__value">{{ $unassigned }}</div>
                    <div class="lca-stat__label">Unassigned</div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= FILTERS ================= --}}
    @if(!$conversations->isEmpty())
        <div class="lca-toolbar">
            <div class="lca-search">
                <i class="fas fa-search"></i>
                <input type="text" id="lcaSearch" placeholder="Search by user, subject, or message…">
            </div>
            <div class="lca-filters">
                <button type="button" class="lca-chip is-active" data-filter="all">All</button>
                <button type="button" class="lca-chip" data-filter="mine">Mine</button>
                <button type="button" class="lca-chip" data-filter="unassigned">Unassigned</button>
                <button type="button" class="lca-chip" data-filter="others">Others</button>
            </div>
        </div>
    @endif

    {{-- ================= LIST ================= --}}
    @if($conversations->isEmpty())
        <div class="lca-empty">
            <div class="lca-empty__icon"><i class="fas fa-inbox"></i></div>
            <h3 class="lca-empty__title">No open conversations</h3>
            <p class="lca-empty__sub">
                When a user or admin sends a message, it will appear here.
            </p>
        </div>
    @else
        <div class="lca-list" id="lcaList">
            @foreach($conversations as $c)
                @php
                    $isMine     = $c->assigned_to === auth()->id();
                    $isUnassign = is_null($c->assigned_to);
                    $initiator  = $c->initiator;
                    $initials   = collect(explode(' ', trim($initiator->name ?? 'U')))
                        ->filter()->slice(0, 2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode('');

                    $stateClass = $isMine ? 'is-mine' : ($isUnassign ? 'is-unassigned' : 'is-other');
                    $filterKey  = $isMine ? 'mine' : ($isUnassign ? 'unassigned' : 'others');
                @endphp

                <article class="lca-item {{ $stateClass }}"
                         data-conversation-id="{{ $c->id }}"
                         data-filter-key="{{ $filterKey }}"
                         data-search="{{ strtolower(($initiator->name ?? '') . ' ' . ($c->subject ?? '') . ' ' . ($c->last_message_preview ?? '')) }}">

                    <div class="lca-item__avatar">
                        @if($initiator?->photo_url)
                            <img src="{{ $initiator->photo_url }}" alt="">
                        @else
                            {{ $initials ?: '?' }}
                        @endif
                    </div>

                    <div class="lca-item__body">
                        <div class="lca-item__top">
                            <div class="lca-item__who">
                                <a href="{{ route('live-chat.show', $c) }}" class="lca-item__title">
                                    {{ $c->subject ?: 'Conversation #' . $c->id }}
                                </a>
                                <span class="lca-item__from">
                                    <i class="fas fa-user"></i>
                                    {{ $initiator->name ?? 'Unknown' }}
                                    <span class="lca-item__role">({{ $initiator->type_name ?? 'User' }})</span>
                                </span>
                            </div>
                            <span class="lca-item__time">
                                {{ optional($c->last_message_at)->diffForHumans() ?? 'New' }}
                            </span>
                        </div>

                        <div class="lca-item__meta">
                            <span class="lca-tag lca-tag--{{ $c->type }}">
                                {{ $c->type === 'escalation' ? 'Escalation' : 'Support' }}
                            </span>
                            <span class="lca-tag lca-tag--{{ $c->status }}">
                                {{ ucfirst($c->status) }}
                            </span>
                            @if($c->priority !== 'normal')
                                <span class="lca-tag lca-tag--priority-{{ $c->priority }}">
                                    {{ ucfirst($c->priority) }}
                                </span>
                            @endif
                            @if($isMine)
                                <span class="lca-tag lca-tag--assigned">
                                    <i class="fas fa-check"></i> Yours
                                </span>
                            @elseif($c->assigned_to)
                                <span class="lca-tag">
                                    <i class="fas fa-user"></i> {{ $c->assignee->name ?? 'Assigned' }}
                                </span>
                            @endif
                        </div>

                        @if($c->last_message_preview)
                            <div class="lca-item__preview">{{ $c->last_message_preview }}</div>
                        @endif
                    </div>

                    <div class="lca-item__actions">
                        @if($isUnassign)
                            <form method="POST" action="{{ route('live-chat.assign', $c) }}" class="lca-inline-form">
                                @csrf
                                <button type="submit" class="lca-btn lca-btn--primary lca-btn--sm">
                                    <i class="fas fa-hand-paper"></i> Claim
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('live-chat.show', $c) }}" class="lca-btn lca-btn--ghost lca-btn--sm">
                            <i class="fas fa-comments"></i> Open
                        </a>

                        <button type="button"
                                class="lca-icon-btn lca-icon-btn--danger lca-delete-btn"
                                data-conversation-id="{{ $c->id }}"
                                data-conversation-title="{{ $c->subject ?: 'Conversation #' . $c->id }}"
                                title="Delete conversation">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="lca-pagination">
            {{ $conversations->links() }}
        </div>
    @endif
</div>

{{-- ============================================================
     STYLES — inline, no @push
     ============================================================ --}}
<style id="liveChatAgentInboxStyles">
    .lca-root {
        max-width: 1000px;
        margin: 0 auto;
        padding: 1.5rem 1rem;
        color: var(--text-primary, #111827);
    }

    /* ---------- Header ---------- */
    .lca-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }
    .lca-header__main { display: flex; align-items: center; gap: .875rem; }
    .lca-header__icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .lca-header__title { margin: 0; font-size: 1.5rem; font-weight: 700; }
    .lca-header__sub { margin: .15rem 0 0; font-size: .875rem; color: var(--text-secondary); }
    .lca-header__actions { display: flex; gap: .5rem; flex-wrap: wrap; }

    /* ---------- Buttons ---------- */
    .lca-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .55rem 1rem;
        border-radius: .55rem;
        font-size: .85rem;
        font-weight: 500;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: transform .15s ease, opacity .15s ease, background .15s ease;
    }
    .lca-btn--sm { padding: .4rem .8rem; font-size: .78rem; }
    .lca-btn--primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
    }
    .lca-btn--primary:hover { transform: translateY(-1px); color: #fff; }
    .lca-btn--ghost {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .lca-btn--ghost:hover { background: var(--card-bg); color: var(--text-primary); }

    .lca-inline-form { display: inline; }

    .lca-icon-btn {
        width: 34px; height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .5rem;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all .15s ease;
    }
    .lca-icon-btn:hover { background: var(--card-bg); color: var(--text-primary); }
    .lca-icon-btn--danger:hover { background: rgba(239,68,68,.1); color: #ef4444; border-color: rgba(239,68,68,.3); }

    /* ---------- Stats ---------- */
    .lca-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .75rem;
        margin-bottom: 1.25rem;
    }
    .lca-stat {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .875rem 1rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: .75rem;
    }
    .lca-stat__icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .lca-stat__icon--primary { background: rgba(59,130,246,.12); color: #3b82f6; }
    .lca-stat__icon--success { background: rgba(16,185,129,.12); color: #10b981; }
    .lca-stat__icon--warning { background: rgba(245,158,11,.12); color: #f59e0b; }
    .lca-stat__value { font-size: 1.25rem; font-weight: 700; line-height: 1; }
    .lca-stat__label { font-size: .72rem; color: var(--text-secondary); margin-top: 2px; }

    /* ---------- Toolbar ---------- */
    .lca-toolbar {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .lca-search {
        flex: 1;
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .5rem .85rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: .55rem;
        min-width: 220px;
    }
    .lca-search i { color: var(--text-secondary); font-size: .875rem; }
    .lca-search input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: .875rem;
        outline: none;
    }
    .lca-filters { display: flex; gap: .35rem; flex-wrap: wrap; }
    .lca-chip {
        padding: .4rem .85rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-size: .8rem;
        cursor: pointer;
        transition: all .15s ease;
    }
    .lca-chip:hover { color: var(--text-primary); }
    .lca-chip.is-active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* ---------- List ---------- */
    .lca-list { display: flex; flex-direction: column; gap: .65rem; }
    .lca-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.1rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--border-color);
        border-radius: .75rem;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .lca-item:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.06); }
    .lca-item.is-unassigned { border-left-color: #f59e0b; }
    .lca-item.is-mine       { border-left-color: #10b981; }
    .lca-item.is-other      { border-left-color: #6b7280; }

    .lca-item__avatar {
        width: 46px; height: 46px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: .9rem;
        flex-shrink: 0;
        overflow: hidden;
    }
    .lca-item__avatar img { width: 100%; height: 100%; object-fit: cover; }

    .lca-item__body { flex: 1; min-width: 0; }
    .lca-item__top {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: .5rem;
        margin-bottom: .35rem;
        flex-wrap: wrap;
    }
    .lca-item__who { display: flex; align-items: baseline; gap: .4rem; min-width: 0; flex-wrap: wrap; }
    .lca-item__title {
        font-weight: 600;
        color: var(--text-primary);
        text-decoration: none;
        font-size: .95rem;
    }
    .lca-item__title:hover { color: var(--primary); }
    .lca-item__from {
        font-size: .75rem;
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }
    .lca-item__role { opacity: .7; }
    .lca-item__time { font-size: .72rem; color: var(--text-secondary); flex-shrink: 0; }
    .lca-item__meta { display: flex; gap: .3rem; flex-wrap: wrap; margin-bottom: .35rem; }
    .lca-item__preview {
        font-size: .85rem;
        color: var(--text-secondary);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .lca-item__actions { display: flex; gap: .4rem; align-items: center; flex-shrink: 0; flex-wrap: wrap; }

    /* ---------- Tags ---------- */
    .lca-tag {
        font-size: .68rem;
        font-weight: 500;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--bg-secondary);
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        gap: .25rem;
    }
    .lca-tag--open     { background: rgba(59,130,246,.15);  color: #3b82f6; }
    .lca-tag--assigned { background: rgba(16,185,129,.15);  color: #10b981; }
    .lca-tag--resolved { background: rgba(16,185,129,.15);  color: #10b981; }
    .lca-tag--closed   { background: rgba(107,114,128,.15); color: #6b7280; }
    .lca-tag--support    { background: rgba(59,130,246,.1);  color: #3b82f6; }
    .lca-tag--escalation { background: rgba(245,158,11,.15); color: #f59e0b; }
    .lca-tag--priority-high   { background: rgba(245,158,11,.15); color: #f59e0b; }
    .lca-tag--priority-urgent { background: rgba(239,68,68,.15);  color: #ef4444; }

    /* ---------- Empty ---------- */
    .lca-empty {
        text-align: center;
        padding: 4rem 1.5rem;
        background: var(--card-bg);
        border-radius: 1rem;
        border: 1px dashed var(--border-color);
    }
    .lca-empty__icon {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: rgba(59,130,246,.1);
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.6rem;
    }
    .lca-empty__title { margin: 0 0 .35rem; font-size: 1.1rem; }
    .lca-empty__sub { margin: 0; color: var(--text-secondary); font-size: .9rem; }

    .lca-pagination { margin-top: 1.5rem; text-align: center; }

    /* ---------- Mobile ---------- */
    @media (max-width: 640px) {
        .lca-stats { grid-template-columns: 1fr; }
        .lca-toolbar { flex-direction: column; align-items: stretch; }
        .lca-search { min-width: 0; }
        .lca-item { flex-direction: column; align-items: stretch; }
        .lca-item__actions { justify-content: flex-end; }
        .lca-item__role { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .lca-item, .lca-btn, .lca-chip { transition: none; }
    }
</style>

{{-- ============================================================
     SCRIPT — filters + search + delete + purge
     ============================================================ --}}
<script id="liveChatAgentInboxScripts">
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const searchInput = document.getElementById('lcaSearch');
    const chipBtns    = document.querySelectorAll('.lca-chip');
    const listRoot    = document.getElementById('lcaList');
    const purgeBtn    = document.getElementById('lcaPurgeBtn');

    const URL_BASE  = @json(url('/live-chat'));
    const URL_PURGE = @json(route('live-chat.purge.closed'));

    let activeFilter = 'all';
    let activeSearch = '';

    /* =========================================================
       FILTER + SEARCH
       ========================================================= */
    function applyFilters() {
        if (!listRoot) return;
        const items = Array.from(listRoot.querySelectorAll('.lca-item'));
        let visible = 0;

        items.forEach((item) => {
            const key        = item.dataset.filterKey;
            const searchBlob = item.dataset.search || '';

            const matchesFilter =
                activeFilter === 'all' ? true : key === activeFilter;

            const matchesSearch =
                !activeSearch || searchBlob.includes(activeSearch);

            const show = matchesFilter && matchesSearch;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        let noResult = document.getElementById('lcaNoResult');
        if (visible === 0) {
            if (!noResult) {
                noResult = document.createElement('div');
                noResult.id = 'lcaNoResult';
                noResult.className = 'lca-empty';
                noResult.style.padding = '2rem 1rem';
                noResult.innerHTML = `
                    <div class="lca-empty__icon"><i class="fas fa-search"></i></div>
                    <p class="lca-empty__sub">No conversations match your filter.</p>
                `;
                listRoot.appendChild(noResult);
            }
        } else if (noResult) {
            noResult.remove();
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeSearch = e.target.value.trim().toLowerCase();
            applyFilters();
        });
    }

    chipBtns.forEach((chip) => {
        chip.addEventListener('click', () => {
            chipBtns.forEach((c) => c.classList.remove('is-active'));
            chip.classList.add('is-active');
            activeFilter = chip.dataset.filter;
            applyFilters();
        });
    });

    /* =========================================================
       DELETE A CONVERSATION
       ========================================================= */
    function attachDeleteHandlers() {
        document.querySelectorAll('.lca-delete-btn').forEach((btn) => {
            if (btn.dataset.wired) return;
            btn.dataset.wired = '1';

            btn.addEventListener('click', async () => {
                const id    = btn.dataset.conversationId;
                const title = btn.dataset.conversationTitle || `Conversation #${id}`;

                if (!confirm(`Delete "${title}"?\n\nThis will remove the conversation and all its messages.`)) return;

                btn.disabled = true;
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const res = await fetch(`${URL_BASE}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Delete failed');
                    }

                    // Fade out and remove the row
                    const item = btn.closest('.lca-item');
                    if (item) {
                        item.style.transition = 'opacity .25s ease, transform .25s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(20px)';
                        setTimeout(() => item.remove(), 300);
                    }

                    console.log('[agent-inbox] deleted conversation', id);
                } catch (err) {
                    console.error('[agent-inbox] delete failed', err);
                    alert(err.message || 'Failed to delete conversation.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            });
        });
    }

    /* =========================================================
       PURGE CLOSED
       ========================================================= */
    if (purgeBtn) {
        purgeBtn.addEventListener('click', async () => {
            const days = prompt(
                'Permanently delete all closed conversations older than how many days?\n\n' +
                'Enter a number (1-365). This cannot be undone.',
                '30'
            );
            if (days === null) return;

            const n = parseInt(days, 10);
            if (isNaN(n) || n < 1 || n > 365) {
                alert('Please enter a number between 1 and 365.');
                return;
            }

            const original = purgeBtn.innerHTML;
            purgeBtn.disabled = true;
            purgeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Purging…';

            try {
                const res = await fetch(URL_PURGE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ days: n }),
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Purge failed');
                }

                alert(data.message || 'Purge completed.');
                window.location.reload();
            } catch (err) {
                console.error('[agent-inbox] purge failed', err);
                alert(err.message || 'Failed to purge.');
                purgeBtn.disabled = false;
                purgeBtn.innerHTML = original;
            }
        });
    }

    /* =========================================================
       INIT
       ========================================================= */
    attachDeleteHandlers();
    console.log('[agent-inbox] ready');

})();
</script>
@endsection