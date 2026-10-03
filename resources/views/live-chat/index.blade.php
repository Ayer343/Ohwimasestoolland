{{-- resources/views/live-chat/index.blade.php --}}

@php
    $__lcUser = auth()->user();
    $__lcLayout = match (true) {
        $__lcUser->isSuperAdmin()          => 'layouts.app',
        $__lcUser->isAdmin()               => 'layouts.app',
        $__lcUser->isDeveloper()           => 'layouts.dev',
        $__lcUser->isLandlord()            => 'layouts.landlord',
        $__lcUser->isTenant()              => 'layouts.tenant',
        $__lcUser->isFieldAgent()          => 'layouts.field',
        $__lcUser->isSecurityPersonnel()   => 'layouts.secu',
        $__lcUser->isContractor()          => 'layouts.contract',
        $__lcUser->isSanitationPersonnel() => 'layouts.san',
        default                            => 'layouts.app',
    };

    $isAgent = in_array($__lcUser->type, [
        \App\Models\User::TYPE_ADMIN,
        \App\Models\User::TYPE_SUPER_ADMIN,
        \App\Models\User::TYPE_DEVELOPER,
    ], true);

    // Precompute aggregates for the stats strip
    $total    = $conversations->total();
    $open     = $conversations->getCollection()->whereIn('status', ['open', 'assigned'])->count();
    $unreadTotal = $conversations->getCollection()->sum(fn ($c) => $c->unreadCountFor(auth()->id()));
@endphp

@extends($__lcLayout)

@section('title', 'Support Chat')

@section('content')
<div class="lci-root">

    {{-- ================= HEADER ================= --}}
    <header class="lci-header">
        <div class="lci-header__main">
            <div class="lci-header__icon">
                <i class="fas fa-headset"></i>
            </div>
            <div>
                <h1 class="lci-header__title">Support Chat</h1>
                <p class="lci-header__sub">
                    Your conversations with support and other teams.
                </p>
            </div>
        </div>

        <div class="lci-header__actions">
            @if($isAgent)
                <a href="{{ route('live-chat.agents.inbox') }}" class="lci-btn lci-btn--primary">
                    <i class="fas fa-inbox"></i>
                    <span>Agent Inbox</span>
                </a>
            @endif
            <form method="POST" action="{{ route('live-chat.start') }}" class="lci-inline-form">
                @csrf
                <button type="submit" class="lci-btn lci-btn--ghost">
                    <i class="fas fa-plus"></i>
                    <span>New Chat</span>
                </button>
            </form>
        </div>
    </header>

    {{-- ================= STATS ================= --}}
    @if(!$conversations->isEmpty())
        <div class="lci-stats">
            <div class="lci-stat">
                <div class="lci-stat__icon lci-stat__icon--primary">
                    <i class="fas fa-comments"></i>
                </div>
                <div>
                    <div class="lci-stat__value">{{ $total }}</div>
                    <div class="lci-stat__label">Total</div>
                </div>
            </div>
            <div class="lci-stat">
                <div class="lci-stat__icon lci-stat__icon--info">
                    <i class="fas fa-circle-dot"></i>
                </div>
                <div>
                    <div class="lci-stat__value">{{ $open }}</div>
                    <div class="lci-stat__label">Open</div>
                </div>
            </div>
            <div class="lci-stat">
                <div class="lci-stat__icon lci-stat__icon--danger">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <div class="lci-stat__value">{{ $unreadTotal }}</div>
                    <div class="lci-stat__label">Unread</div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= FILTER BAR ================= --}}
    @if(!$conversations->isEmpty())
        <div class="lci-toolbar">
            <div class="lci-search">
                <i class="fas fa-search"></i>
                <input type="text" id="lciSearch" placeholder="Search conversations…">
            </div>
            <div class="lci-filters">
                <button type="button" class="lci-chip is-active" data-filter="all">All</button>
                <button type="button" class="lci-chip" data-filter="unread">Unread</button>
                <button type="button" class="lci-chip" data-filter="open">Open</button>
                <button type="button" class="lci-chip" data-filter="closed">Closed</button>
            </div>
        </div>
    @endif

    {{-- ================= LIST ================= --}}
    @if($conversations->isEmpty())
        <div class="lci-empty">
            <div class="lci-empty__icon">
                <i class="fas fa-comments"></i>
            </div>
            <h3 class="lci-empty__title">No conversations yet</h3>
            <p class="lci-empty__sub">
                Start a new chat with the support team. We'll get back to you quickly.
            </p>
            <form method="POST" action="{{ route('live-chat.start') }}">
                @csrf
                <button type="submit" class="lci-btn lci-btn--primary">
                    <i class="fas fa-plus"></i> Start New Chat
                </button>
            </form>
        </div>
    @else
        <div class="lci-list" id="lciList">
            @foreach($conversations as $c)
                @php
                    $unread       = $c->unreadCountFor(auth()->id());
                    $isInitiator  = $c->initiator_id === auth()->id();
                    $other        = $isInitiator ? $c->assignee : $c->initiator;
                    $otherName    = $other->name ?? 'Support Team';
                    $otherType    = $other->type_name ?? null;
                    $initials     = collect(explode(' ', trim($otherName)))
                        ->filter()->slice(0, 2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode('');
                    $preview = $c->subject
                        ?: 'Conversation #' . $c->id;
                    if ($c->last_message_preview) {
                        $preview = $c->last_message_preview;
                    }
                @endphp

                <a href="{{ route('live-chat.show', $c) }}"
                   class="lci-item {{ $unread > 0 ? 'is-unread' : '' }}"
                   data-status="{{ $c->status }}"
                   data-unread="{{ $unread > 0 ? '1' : '0' }}"
                   data-search="{{ strtolower($otherName . ' ' . $preview . ' ' . $c->subject) }}">

                    <div class="lci-item__avatar">
                        @if($other?->photo_url)
                            <img src="{{ $other->photo_url }}" alt="">
                        @else
                            {{ $initials ?: '?' }}
                        @endif
                        @if($unread > 0)
                            <span class="lci-item__dot"></span>
                        @endif
                    </div>

                    <div class="lci-item__body">
                        <div class="lci-item__top">
                            <div class="lci-item__who">
                                <span class="lci-item__name">{{ $otherName }}</span>
                                @if($otherType)
                                    <span class="lci-item__role">{{ $otherType }}</span>
                                @endif
                            </div>
                            <span class="lci-item__time">
                                {{ optional($c->last_message_at)->diffForHumans() ?? 'New' }}
                            </span>
                        </div>

                        <div class="lci-item__preview">{{ $preview }}</div>

                        <div class="lci-item__meta">
                            <span class="lci-tag lci-tag--{{ $c->status }}">
                                {{ ucfirst($c->status) }}
                            </span>
                            <span class="lci-tag lci-tag--{{ $c->type }}">
                                {{ $c->type === 'escalation' ? 'Escalation' : 'Support' }}
                            </span>
                            @if($c->priority !== 'normal')
                                <span class="lci-tag lci-tag--priority-{{ $c->priority }}">
                                    {{ ucfirst($c->priority) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($unread > 0)
                        <div class="lci-item__unread">{{ $unread > 99 ? '99+' : $unread }}</div>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="lci-pagination">
            {{ $conversations->links() }}
        </div>
    @endif
</div>

{{-- ============================================================
     STYLES — inline, no @push
     ============================================================ --}}
<style id="liveChatIndexStyles">
    .lci-root {
        max-width: 900px;
        margin: 0 auto;
        padding: 1.5rem 1rem;
        color: var(--text-primary, #111827);
    }

    /* ---------- Header ---------- */
    .lci-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }
    .lci-header__main { display: flex; align-items: center; gap: .875rem; }
    .lci-header__icon {
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
    .lci-header__title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }
    .lci-header__sub {
        margin: .15rem 0 0;
        font-size: .875rem;
        color: var(--text-secondary);
    }
    .lci-header__actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .lci-inline-form { display: inline; }

    /* ---------- Buttons ---------- */
    .lci-btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1rem;
        border-radius: .55rem;
        font-size: .875rem;
        font-weight: 500;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: transform .15s ease, opacity .15s ease, background .15s ease;
    }
    .lci-btn--primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
    }
    .lci-btn--primary:hover { transform: translateY(-1px); color: #fff; }
    .lci-btn--ghost {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .lci-btn--ghost:hover { background: var(--card-bg); color: var(--text-primary); }

    /* ---------- Stats ---------- */
    .lci-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .75rem;
        margin-bottom: 1.25rem;
    }
    .lci-stat {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .875rem 1rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: .75rem;
    }
    .lci-stat__icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .lci-stat__icon--primary { background: rgba(59,130,246,.12); color: #3b82f6; }
    .lci-stat__icon--info    { background: rgba(6,182,212,.12); color: #06b6d4; }
    .lci-stat__icon--danger  { background: rgba(239,68,68,.12); color: #ef4444; }
    .lci-stat__value { font-size: 1.25rem; font-weight: 700; line-height: 1; }
    .lci-stat__label { font-size: .72rem; color: var(--text-secondary); margin-top: 2px; }

    /* ---------- Toolbar ---------- */
    .lci-toolbar {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .lci-search {
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
    .lci-search i { color: var(--text-secondary); font-size: .875rem; }
    .lci-search input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: .875rem;
        outline: none;
    }
    .lci-filters { display: flex; gap: .35rem; flex-wrap: wrap; }
    .lci-chip {
        padding: .4rem .85rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-size: .8rem;
        cursor: pointer;
        transition: all .15s ease;
    }
    .lci-chip:hover { color: var(--text-primary); }
    .lci-chip.is-active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* ---------- List ---------- */
    .lci-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .lci-item {
        display: flex;
        align-items: center;
        gap: .875rem;
        padding: 1rem 1.1rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: .75rem;
        text-decoration: none;
        color: inherit;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        position: relative;
    }
    .lci-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0,0,0,.06);
        border-color: var(--primary);
    }
    .lci-item.is-unread { border-left: 3px solid var(--primary); }

    .lci-item__avatar {
        position: relative;
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
    .lci-item__avatar img { width: 100%; height: 100%; object-fit: cover; }
    .lci-item__dot {
        position: absolute;
        top: 0; right: 0;
        width: 12px; height: 12px;
        border-radius: 50%;
        background: var(--danger);
        border: 2px solid var(--card-bg);
    }

    .lci-item__body { flex: 1; min-width: 0; }
    .lci-item__top {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: .5rem;
        margin-bottom: 3px;
    }
    .lci-item__who { display: flex; align-items: baseline; gap: .4rem; min-width: 0; }
    .lci-item__name {
        font-weight: 600;
        font-size: .95rem;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lci-item__role {
        font-size: .72rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .lci-item__time {
        font-size: .72rem;
        color: var(--text-secondary);
        flex-shrink: 0;
    }
    .lci-item__preview {
        font-size: .85rem;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: .35rem;
    }
    .lci-item__meta { display: flex; gap: .3rem; flex-wrap: wrap; }

    .lci-item__unread {
        min-width: 24px;
        height: 24px;
        padding: 0 7px;
        border-radius: 999px;
        background: var(--primary);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* ---------- Tags ---------- */
    .lci-tag {
        font-size: .68rem;
        font-weight: 500;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--bg-secondary);
        color: var(--text-secondary);
        text-transform: capitalize;
    }
    .lci-tag--open     { background: rgba(59,130,246,.15);  color: #3b82f6; }
    .lci-tag--assigned { background: rgba(139,92,246,.15);  color: #8b5cf6; }
    .lci-tag--resolved { background: rgba(16,185,129,.15);  color: #10b981; }
    .lci-tag--closed   { background: rgba(107,114,128,.15); color: #6b7280; }
    .lci-tag--support    { background: rgba(59,130,246,.1);  color: #3b82f6; }
    .lci-tag--escalation { background: rgba(245,158,11,.15); color: #f59e0b; }
    .lci-tag--priority-high   { background: rgba(245,158,11,.15); color: #f59e0b; }
    .lci-tag--priority-urgent { background: rgba(239,68,68,.15);  color: #ef4444; }

    /* ---------- Empty ---------- */
    .lci-empty {
        text-align: center;
        padding: 4rem 1.5rem;
        background: var(--card-bg);
        border-radius: 1rem;
        border: 1px dashed var(--border-color);
    }
    .lci-empty__icon {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: rgba(var(--primary-rgb, 59,130,246), .1);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.6rem;
    }
    .lci-empty__title { margin: 0 0 .35rem; font-size: 1.1rem; }
    .lci-empty__sub {
        margin: 0 0 1.25rem;
        color: var(--text-secondary);
        font-size: .9rem;
        max-width: 380px;
        margin-left: auto;
        margin-right: auto;
    }

    /* ---------- Pagination ---------- */
    .lci-pagination { margin-top: 1.5rem; text-align: center; }
    .lci-pagination nav { display: inline-flex; }

    /* ---------- Mobile ---------- */
    @media (max-width: 640px) {
        .lci-stats { grid-template-columns: 1fr; }
        .lci-header__title { font-size: 1.25rem; }
        .lci-toolbar { flex-direction: column; align-items: stretch; }
        .lci-search { min-width: 0; }
        .lci-item { padding: .85rem; }
        .lci-item__role { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .lci-item, .lci-btn, .lci-chip { transition: none; }
    }
</style>

{{-- ============================================================
     SCRIPT — client-side filter/search
     ============================================================ --}}
<script id="liveChatIndexScripts">
(function () {
    'use strict';

    const searchInput = document.getElementById('lciSearch');
    const chipBtns    = document.querySelectorAll('.lci-chip');
    const listRoot    = document.getElementById('lciList');

    if (!listRoot) return;

    const items = Array.from(listRoot.querySelectorAll('.lci-item'));
    let activeFilter = 'all';
    let activeSearch = '';

    function applyFilters() {
        let visibleCount = 0;

        items.forEach((item) => {
            const status      = item.dataset.status;
            const isUnread    = item.dataset.unread === '1';
            const searchBlob  = item.dataset.search || '';

            const matchesFilter =
                activeFilter === 'all' ? true :
                activeFilter === 'unread' ? isUnread :
                activeFilter === 'open' ? (status === 'open' || status === 'assigned') :
                activeFilter === 'closed' ? status === 'closed' :
                true;

            const matchesSearch =
                !activeSearch || searchBlob.includes(activeSearch);

            const show = matchesFilter && matchesSearch;
            item.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Toggle "no results" message if all hidden
        let noResultEl = document.getElementById('lciNoResult');
        if (visibleCount === 0) {
            if (!noResultEl) {
                noResultEl = document.createElement('div');
                noResultEl.id = 'lciNoResult';
                noResultEl.className = 'lci-empty';
                noResultEl.style.padding = '2rem 1rem';
                noResultEl.innerHTML = `
                    <div class="lci-empty__icon"><i class="fas fa-search"></i></div>
                    <p class="lci-empty__sub">No conversations match your filter.</p>
                `;
                listRoot.appendChild(noResultEl);
            }
        } else if (noResultEl) {
            noResultEl.remove();
        }
    }

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeSearch = e.target.value.trim().toLowerCase();
            applyFilters();
        });
    }

    // Chips
    chipBtns.forEach((chip) => {
        chip.addEventListener('click', () => {
            chipBtns.forEach((c) => c.classList.remove('is-active'));
            chip.classList.add('is-active');
            activeFilter = chip.dataset.filter;
            applyFilters();
        });
    });

    console.log('[live-chat index] ready, items:', items.length);
})();
</script>
@endsection