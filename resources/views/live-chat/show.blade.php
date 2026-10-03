{{-- resources/views/live-chat/show.blade.php --}}

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

    $otherParticipant = $conversation->participants
        ->where('user_id', '!=', auth()->id())
        ->first()?->user;

    // Who can delete? (initiator, assignee, or admin/developer)
    $__lcCanDelete = $conversation->initiator_id === auth()->id()
        || $conversation->assigned_to === auth()->id()
        || in_array($__lcUser->type, [
            \App\Models\User::TYPE_ADMIN,
            \App\Models\User::TYPE_SUPER_ADMIN,
            \App\Models\User::TYPE_DEVELOPER,
        ], true);
@endphp

@extends($__lcLayout)

@section('title', $conversation->subject ?: 'Conversation #' . $conversation->id)

@section('content')
<div class="lcs-root"
     data-conversation-id="{{ $conversation->id }}"
     data-user-id="{{ auth()->id() }}"
     data-csrf="{{ csrf_token() }}"
     data-status="{{ $conversation->status }}">

    {{-- ================= HEADER ================= --}}
    <header class="lcs-header">
        <div class="lcs-header__left">
            <a href="{{ route('live-chat.index') }}" class="lcs-icon-btn" title="Back to inbox">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="lcs-header__title-block">
                <h1 class="lcs-header__title">
                    {{ $conversation->subject ?: 'Conversation #' . $conversation->id }}
                </h1>
                <div class="lcs-header__meta">
                    <span class="lcs-pill lcs-pill--{{ $conversation->status }}">
                        <span class="lcs-pill__dot"></span>
                        {{ ucfirst($conversation->status) }}
                    </span>
                    <span class="lcs-pill lcs-pill--{{ $conversation->type }}">
                        {{ $conversation->type === 'escalation' ? 'Escalation' : 'Support' }}
                    </span>
                    @if($conversation->assignee)
                        <span class="lcs-meta-text">
                            <i class="fas fa-user-check"></i>
                            Assigned to <strong>{{ $conversation->assignee->name }}</strong>
                        </span>
                    @else
                        <span class="lcs-meta-text lcs-meta-text--warn">
                            <i class="fas fa-user-clock"></i>
                            Unassigned
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="lcs-header__right">
            <button type="button" class="lcs-btn lcs-btn--ghost" id="lcsInfoToggle" title="Conversation info">
                <i class="fas fa-info-circle"></i>
                <span class="lcs-btn__label">Details</span>
            </button>

            {{-- Clear from my inbox only --}}
            <button type="button" class="lcs-btn lcs-btn--ghost" id="lcsClearBtn"
                    title="Remove this conversation from your inbox">
                <i class="fas fa-eye-slash"></i>
                <span class="lcs-btn__label">Clear</span>
            </button>

            {{-- Delete (soft) — initiator/assignee/agent only --}}
            @if($__lcCanDelete)
                <button type="button" class="lcs-btn lcs-btn--danger" id="lcsDeleteBtn"
                        title="Delete this conversation">
                    <i class="fas fa-trash"></i>
                    <span class="lcs-btn__label">Delete</span>
                </button>
            @endif

            @if($conversation->status !== 'closed')
                <button type="button" class="lcs-btn lcs-btn--ghost" id="lcsCloseBtn" title="Close conversation">
                    <i class="fas fa-times-circle"></i>
                    <span class="lcs-btn__label">Close</span>
                </button>
            @endif
        </div>
    </header>

    {{-- ================= BODY ================= --}}
    <div class="lcs-body">

        {{-- -------- MESSAGE STREAM -------- --}}
        <main class="lcs-stream" id="lcsStream" aria-live="polite">
            @if($messages->isEmpty())
                <div class="lcs-empty" id="lcsEmpty">
                    <div class="lcs-empty__icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="lcs-empty__title">No messages yet</h3>
                    <p class="lcs-empty__sub">Start the conversation below.</p>
                </div>
            @else
                @php $lastDate = null; @endphp
                @foreach($messages as $m)
                    @php
                        $mine = $m->user_id === auth()->id();
                        $msgDate = $m->created_at->format('Y-m-d');
                    @endphp

                    @if($lastDate !== $msgDate)
                        <div class="lcs-day-sep">
                            <span>{{ $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('M d, Y')) }}</span>
                        </div>
                        @php $lastDate = $msgDate; @endphp
                    @endif

                    <div class="lcs-msg {{ $mine ? 'lcs-msg--mine' : 'lcs-msg--theirs' }}"
                         data-mid="{{ $m->id }}">
                        @if(!$mine)
                            <div class="lcs-msg__avatar" title="{{ $m->user?->name ?? 'Unknown' }}">
                                @if($m->user?->photo_url)
                                    <img src="{{ $m->user->photo_url }}" alt="">
                                @else
                                    {{ strtoupper(substr($m->user?->name ?? 'U', 0, 2)) }}
                                @endif
                            </div>
                        @endif

                        <div class="lcs-msg__bubble">
                            @if(!$mine && $m->user)
                                <div class="lcs-msg__sender">{{ $m->user->name }}</div>
                            @endif
                            <div class="lcs-msg__body">{{ $m->body }}</div>
                            <div class="lcs-msg__time">
                                {{ $m->created_at->format('H:i') }}
                                @if($mine)
                                    <i class="fas fa-check-double lcs-msg__tick"></i>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="lcs-typing hidden" id="lcsTyping">
                <div class="lcs-typing__dots">
                    <span></span><span></span><span></span>
                </div>
                <span class="lcs-typing__label">Someone is typing…</span>
            </div>
        </main>

        {{-- -------- SIDE PANEL -------- --}}
        <aside class="lcs-aside" id="lcsAside" aria-label="Conversation details">
            <div class="lcs-aside__header">
                <h3>Details</h3>
                <button type="button" class="lcs-icon-btn" id="lcsAsideClose" title="Close panel">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="lcs-aside__section">
                <div class="lcs-aside__label">Started by</div>
                <div class="lcs-aside__user">
                    <div class="lcs-aside__avatar">
                        {{ strtoupper(substr($conversation->initiator->name ?? 'U', 0, 2)) }}
                    </div>
                    <div>
                        <div class="lcs-aside__name">{{ $conversation->initiator->name ?? 'Unknown' }}</div>
                        <div class="lcs-aside__role">{{ $conversation->initiator->type_name ?? 'User' }}</div>
                    </div>
                </div>
            </div>

            @if($otherParticipant)
                <div class="lcs-aside__section">
                    <div class="lcs-aside__label">Other participant</div>
                    <div class="lcs-aside__user">
                        <div class="lcs-aside__avatar lcs-aside__avatar--alt">
                            {{ strtoupper(substr($otherParticipant->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="lcs-aside__name">{{ $otherParticipant->name }}</div>
                            <div class="lcs-aside__role">{{ $otherParticipant->type_name ?? 'User' }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="lcs-aside__section">
                <div class="lcs-aside__label">Properties</div>
                <div class="lcs-aside__row"><span>Status</span> <strong>{{ ucfirst($conversation->status) }}</strong></div>
                <div class="lcs-aside__row"><span>Type</span> <strong>{{ ucfirst($conversation->type) }}</strong></div>
                <div class="lcs-aside__row"><span>Priority</span> <strong>{{ ucfirst($conversation->priority) }}</strong></div>
                <div class="lcs-aside__row"><span>Messages</span> <strong>{{ $messages->count() }}</strong></div>
                <div class="lcs-aside__row">
                    <span>Created</span>
                    <strong>{{ $conversation->created_at->format('M d, Y H:i') }}</strong>
                </div>
                @if($conversation->last_message_at)
                    <div class="lcs-aside__row">
                        <span>Last activity</span>
                        <strong>{{ $conversation->last_message_at->diffForHumans() }}</strong>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    {{-- ================= COMPOSER ================= --}}
    @if($conversation->status !== 'closed')
        <footer class="lcs-footer">
            <form class="lcs-composer" id="lcsForm" autocomplete="off">
                @csrf
                <input type="text"
                       id="lcsInput"
                       name="body"
                       maxlength="5000"
                       placeholder="Type your message…"
                       required>
                <button type="submit" id="lcsSendBtn" title="Send (Enter)">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            <div class="lcs-footer__hint">
                Press <kbd>Enter</kbd> to send
            </div>
        </footer>
    @else
        <footer class="lcs-footer lcs-footer--closed">
            <i class="fas fa-lock"></i>
            This conversation is closed.
        </footer>
    @endif
</div>

{{-- ============================================================
     STYLES — inline, no @push
     ============================================================ --}}
<style id="liveChatShowStyles">
    .lcs-root {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 80px);
        max-width: 1200px;
        margin: 0 auto;
        background: var(--card-bg, #fff);
        color: var(--text-primary, #111827);
    }

    /* ---------- Header ---------- */
    .lcs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        background: var(--card-bg, #fff);
        flex-wrap: wrap;
    }
    .lcs-header__left { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .lcs-header__title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 500px;
    }
    .lcs-header__title-block { min-width: 0; }
    .lcs-header__meta { display: flex; align-items: center; gap: .4rem; margin-top: .25rem; flex-wrap: wrap; }
    .lcs-header__right { display: flex; gap: .5rem; flex-wrap: wrap; }

    .lcs-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: .5rem;
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all .15s ease;
    }
    .lcs-icon-btn:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }

    .lcs-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .5rem .9rem;
        border-radius: .5rem;
        font-size: .85rem;
        font-weight: 500;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .lcs-btn--ghost {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border-color: var(--border-color);
    }
    .lcs-btn--ghost:hover { background: var(--card-bg); }
    .lcs-btn--danger {
        background: var(--danger, #ef4444);
        color: #fff;
    }
    .lcs-btn--danger:hover { opacity: .9; }
    .lcs-btn__label { display: inline; }

    /* ---------- Pills / meta ---------- */
    .lcs-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .2rem .6rem;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 500;
        background: var(--bg-secondary);
        color: var(--text-secondary);
    }
    .lcs-pill__dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .lcs-pill--open     { background: rgba(59,130,246,.15); color: #3b82f6; }
    .lcs-pill--assigned { background: rgba(139,92,246,.15); color: #8b5cf6; }
    .lcs-pill--resolved { background: rgba(16,185,129,.15); color: #10b981; }
    .lcs-pill--closed   { background: rgba(107,114,128,.15); color: #6b7280; }
    .lcs-pill--support    { background: rgba(59,130,246,.1); color: #3b82f6; }
    .lcs-pill--escalation { background: rgba(245,158,11,.15); color: #f59e0b; }

    .lcs-meta-text {
        font-size: .75rem;
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }
    .lcs-meta-text--warn { color: var(--warning, #f59e0b); }

    /* ---------- Body layout ---------- */
    .lcs-body {
        flex: 1;
        display: flex;
        min-height: 0;
        overflow: hidden;
    }

    /* ---------- Stream ---------- */
    .lcs-stream {
        flex: 1;
        overflow-y: auto;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: .25rem;
        background: var(--bg-secondary);
    }

    .lcs-day-sep {
        display: flex;
        justify-content: center;
        margin: 1rem 0 .5rem;
    }
    .lcs-day-sep span {
        background: var(--card-bg);
        color: var(--text-secondary);
        font-size: .72rem;
        padding: .2rem .7rem;
        border-radius: 999px;
        border: 1px solid var(--border-color);
    }

    .lcs-msg {
        display: flex;
        gap: .6rem;
        margin: .35rem 0;
        max-width: 75%;
        animation: lcsMsgIn .2s ease-out;
    }
    .lcs-msg--mine   { align-self: flex-end; flex-direction: row-reverse; }
    .lcs-msg--theirs { align-self: flex-start; }

    @keyframes lcsMsgIn {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .lcs-msg__avatar {
        flex-shrink: 0;
        width: 34px; height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 700;
        margin-top: 4px;
        overflow: hidden;
    }
    .lcs-msg__avatar img { width: 100%; height: 100%; object-fit: cover; }

    .lcs-msg__bubble {
        padding: .6rem .85rem;
        border-radius: 1rem;
        font-size: .9rem;
        line-height: 1.45;
        position: relative;
        min-width: 80px;
    }
    .lcs-msg--mine .lcs-msg__bubble {
        background: var(--primary);
        color: #fff;
        border-bottom-right-radius: .25rem;
    }
    .lcs-msg--theirs .lcs-msg__bubble {
        background: var(--card-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-bottom-left-radius: .25rem;
    }
    .lcs-msg__sender {
        font-size: .72rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: .15rem;
    }
    .lcs-msg__body { word-wrap: break-word; white-space: pre-wrap; }
    .lcs-msg__time {
        font-size: .65rem;
        opacity: .75;
        margin-top: .3rem;
        display: flex;
        align-items: center;
        gap: .25rem;
        justify-content: flex-end;
    }
    .lcs-msg__tick { color: rgba(255,255,255,.9); }

    /* ---------- Empty state ---------- */
    .lcs-empty {
        margin: auto;
        text-align: center;
        color: var(--text-secondary);
    }
    .lcs-empty__icon {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto .75rem;
    }
    .lcs-empty__icon i { font-size: 1.6rem; color: var(--primary); opacity: .7; }
    .lcs-empty__title { font-size: 1rem; margin: 0 0 .25rem; color: var(--text-primary); }
    .lcs-empty__sub { font-size: .85rem; margin: 0; }

    /* ---------- Typing ---------- */
    .lcs-typing {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .4rem .6rem;
        margin-left: 44px;
        color: var(--text-secondary);
        font-size: .75rem;
    }
    .lcs-typing.hidden { display: none; }
    .lcs-typing__dots { display: inline-flex; gap: 3px; }
    .lcs-typing__dots span {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: lcsBounce 1.2s infinite ease-in-out;
    }
    .lcs-typing__dots span:nth-child(2) { animation-delay: .15s; }
    .lcs-typing__dots span:nth-child(3) { animation-delay: .3s; }
    @keyframes lcsBounce {
        0%, 80%, 100% { transform: translateY(0); opacity: .5; }
        40%           { transform: translateY(-4px); opacity: 1; }
    }

    /* ---------- Aside ---------- */
    .lcs-aside {
        width: 300px;
        flex-shrink: 0;
        border-left: 1px solid var(--border-color);
        background: var(--card-bg);
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .lcs-aside.hidden { display: none; }
    .lcs-aside__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .lcs-aside__header h3 { margin: 0; font-size: .95rem; font-weight: 600; }

    .lcs-aside__section {
        padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .lcs-aside__section:last-child { border-bottom: none; }
    .lcs-aside__label {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--text-secondary);
        margin-bottom: .6rem;
    }
    .lcs-aside__user { display: flex; gap: .6rem; align-items: center; }
    .lcs-aside__avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .lcs-aside__avatar--alt {
        background: linear-gradient(135deg, #f59e0b, #ef4444);
    }
    .lcs-aside__name { font-size: .9rem; font-weight: 600; color: var(--text-primary); }
    .lcs-aside__role { font-size: .72rem; color: var(--text-secondary); }

    .lcs-aside__row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .35rem 0;
        font-size: .85rem;
        color: var(--text-secondary);
    }
    .lcs-aside__row strong { color: var(--text-primary); font-weight: 600; }

    /* ---------- Footer / composer ---------- */
    .lcs-footer {
        border-top: 1px solid var(--border-color);
        background: var(--card-bg);
        padding: .75rem 1rem;
    }
    .lcs-footer--closed {
        text-align: center;
        color: var(--text-secondary);
        font-size: .85rem;
        padding: 1rem;
    }

    .lcs-composer {
        display: flex;
        gap: .5rem;
        align-items: center;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        padding: .35rem .35rem .35rem .9rem;
        transition: border-color .15s ease;
    }
    .lcs-composer:focus-within { border-color: var(--primary); }
    .lcs-composer input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: .9rem;
        outline: none;
        padding: .55rem 0;
    }
    .lcs-composer button {
        width: 40px; height: 40px;
        border-radius: .75rem;
        border: none;
        background: var(--primary);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: opacity .15s ease, transform .1s ease;
    }
    .lcs-composer button:hover { opacity: .9; }
    .lcs-composer button:disabled { opacity: .5; cursor: not-allowed; }

    .lcs-footer__hint {
        margin-top: .4rem;
        text-align: center;
        font-size: .68rem;
        color: var(--text-secondary);
    }
    .lcs-footer__hint kbd {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: .25rem;
        padding: 0 .35rem;
        font-size: .68rem;
        font-family: inherit;
    }

    /* ---------- Mobile ---------- */
    @media (max-width: 768px) {
        .lcs-root { height: calc(100vh - 60px); }
        .lcs-header { padding: .75rem; }
        .lcs-header__title { font-size: .95rem; }
        .lcs-btn__label { display: none; }
        .lcs-btn { padding: .5rem; }
        .lcs-aside {
            position: absolute;
            top: 0; right: 0;
            height: 100%;
            z-index: 20;
            box-shadow: -10px 0 30px rgba(0,0,0,.15);
        }
        .lcs-aside.hidden { display: none; }
        .lcs-stream { padding: 1rem .75rem; }
        .lcs-msg { max-width: 85%; }
        .lcs-footer__hint { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .lcs-msg, .lcs-typing__dots span { animation: none; }
    }
</style>

{{-- ============================================================
     SCRIPT — inline, no @push
     ============================================================ --}}
<script id="liveChatShowScripts">
(function () {
    'use strict';

    const root = document.querySelector('.lcs-root');
    if (!root) return;

    const conversationId = root.dataset.conversationId;
    const ME             = parseInt(root.dataset.userId, 10);
    const CSRF           = root.dataset.csrf;
    const STATUS         = root.dataset.status;

    const stream   = document.getElementById('lcsStream');
    const form     = document.getElementById('lcsForm');
    const input    = document.getElementById('lcsInput');
    const sendBtn  = document.getElementById('lcsSendBtn');
    const closeBtn = document.getElementById('lcsCloseBtn');
    const clearBtn = document.getElementById('lcsClearBtn');
    const deleteBtn= document.getElementById('lcsDeleteBtn');
    const emptyEl  = document.getElementById('lcsEmpty');
    const typing   = document.getElementById('lcsTyping');
    const aside    = document.getElementById('lcsAside');
    const infoBtn  = document.getElementById('lcsInfoToggle');
    const asideX   = document.getElementById('lcsAsideClose');

    let lastMessageId = stream.querySelector('.lcs-msg:last-child')?.dataset.mid || 0;
    let pollTimer = null;

    console.log('[live-chat show] booting', { conversationId, ME });

    /* =========================================================
       HELPERS
       ========================================================= */
    function scrollBottom() {
        stream.scrollTop = stream.scrollHeight;
    }

    function formatTime(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch { return ''; }
    }

    function initials(name) {
        return String(name || 'U')
            .split(/\s+/).slice(0, 2)
            .map(w => w[0]).join('').toUpperCase();
    }

    /* =========================================================
       RENDER
       ========================================================= */
    function renderMessage(m) {
        if (emptyEl && emptyEl.parentNode) emptyEl.remove();
        if (stream.querySelector('[data-mid="' + m.id + '"]')) return;

        const mine = m.user_id === ME;
        const wrap = document.createElement('div');
        wrap.className = 'lcs-msg ' + (mine ? 'lcs-msg--mine' : 'lcs-msg--theirs');
        wrap.dataset.mid = m.id;

        if (!mine) {
            const avatar = document.createElement('div');
            avatar.className = 'lcs-msg__avatar';
            avatar.textContent = initials(m.user_name);
            wrap.appendChild(avatar);
        }

        const bubble = document.createElement('div');
        bubble.className = 'lcs-msg__bubble';

        if (!mine && m.user_name) {
            const sender = document.createElement('div');
            sender.className = 'lcs-msg__sender';
            sender.textContent = m.user_name;
            bubble.appendChild(sender);
        }

        const body = document.createElement('div');
        body.className = 'lcs-msg__body';
        body.textContent = m.body || '';
        bubble.appendChild(body);

        const time = document.createElement('div');
        time.className = 'lcs-msg__time';
        time.textContent = formatTime(m.created_at);
        if (mine) {
            const tick = document.createElement('i');
            tick.className = 'fas fa-check-double lcs-msg__tick';
            time.appendChild(tick);
        }
        bubble.appendChild(time);

        wrap.appendChild(bubble);
        stream.appendChild(wrap);
    }

    /* =========================================================
       POLLING
       ========================================================= */
    async function pollMessages() {
        try {
            const res = await fetch(`/live-chat/${conversationId}/messages?after=${lastMessageId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && Array.isArray(data.messages) && data.messages.length) {
                data.messages.forEach(renderMessage);
                lastMessageId = Math.max(lastMessageId, ...data.messages.map(m => m.id));
                scrollBottom();
            }
        } catch (err) {
            /* network hiccup — retry next tick */
        }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(() => { if (!document.hidden) pollMessages(); }, 4000);
    }

    /* =========================================================
       SEND
       ========================================================= */
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body) return;

            input.value = '';
            sendBtn.disabled = true;

            try {
                const res = await fetch(`/live-chat/${conversationId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({ body }),
                });

                if (!res.ok) {
                    console.error('[live-chat show] send HTTP', res.status);
                    input.value = body;
                    return;
                }

                const data = await res.json();
                if (data.success && data.message) {
                    renderMessage(data.message);
                    lastMessageId = Math.max(lastMessageId, data.message.id);
                    scrollBottom();
                } else {
                    input.value = body;
                }
            } catch (err) {
                console.error('[live-chat show] send threw', err);
                input.value = body;
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        });
    }

    /* =========================================================
       CLOSE
       ========================================================= */
    if (closeBtn) {
        closeBtn.addEventListener('click', async () => {
            if (!confirm('Close this conversation?')) return;
            try {
                const res = await fetch(`/live-chat/${conversationId}/close`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) window.location.reload();
            } catch {}
        });
    }

    /* =========================================================
       CLEAR (remove from my inbox only)
       ========================================================= */
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            if (!confirm(
                'Remove this conversation from your inbox?\n\n' +
                'It will stay visible to other participants.'
            )) return;

            const original = clearBtn.innerHTML;
            clearBtn.disabled = true;
            clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const res = await fetch(`/live-chat/${conversationId}/clear`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.href = @json(route('live-chat.index'));
                } else {
                    alert(data.message || 'Failed to clear conversation.');
                    clearBtn.disabled = false;
                    clearBtn.innerHTML = original;
                }
            } catch (err) {
                console.error('[live-chat show] clear threw', err);
                alert('Failed to clear conversation.');
                clearBtn.disabled = false;
                clearBtn.innerHTML = original;
            }
        });
    }

    /* =========================================================
       DELETE (soft delete for everyone)
       ========================================================= */
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm(
                'Delete this conversation?\n\n' +
                'This will remove it and all its messages for everyone.\n' +
                'This action cannot be undone.'
            )) return;

            const original = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const res = await fetch(`/live-chat/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.href = @json(route('live-chat.index'));
                } else {
                    alert(data.message || 'Failed to delete conversation.');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = original;
                }
            } catch (err) {
                console.error('[live-chat show] delete threw', err);
                alert('Failed to delete conversation.');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = original;
            }
        });
    }

    /* =========================================================
       ASIDE TOGGLE
       ========================================================= */
    if (infoBtn && aside) {
        infoBtn.addEventListener('click', () => {
            aside.classList.toggle('hidden');
        });
    }
    if (asideX && aside) {
        asideX.addEventListener('click', () => {
            aside.classList.add('hidden');
        });
    }

    // On mobile, start with aside hidden
    if (window.innerWidth <= 768 && aside) {
        aside.classList.add('hidden');
    }

    /* =========================================================
       INIT
       ========================================================= */
    scrollBottom();
    if (STATUS !== 'closed') startPolling();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pollMessages();
    });

    console.log('[live-chat show] ready');
})();
</script>
@endsection