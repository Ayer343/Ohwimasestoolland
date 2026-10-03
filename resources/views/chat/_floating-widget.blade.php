{{-- resources/views/chat/_floating-widget.blade.php --}}
@php
    $chatUser     = auth()->user();
    $chatProvider = app(\App\Services\ChatHelpTopicProvider::class);
    $chatRoleName = $chatUser?->type_name ?? 'Guest';
@endphp

<button type="button"
        id="floatingChatLauncher"
        class="floating-chat-launcher"
        title="Chat with us"
        aria-label="Open chat">
    <i class="fas fa-comments"></i>
</button>

<div id="floatingChatPanel"
     class="floating-chat-panel"
     role="dialog"
     aria-label="Chat assistant">
    <div class="floating-chat-panel__header">
        <div>
            <strong>Assistant</strong>
            <div class="floating-chat-panel__sub">
                @if($chatUser)
                    Helping you as <strong>{{ $chatRoleName }}</strong>
                @else
                    Ask anything before you register
                @endif
            </div>
        </div>
        <button type="button"
                id="floatingChatClose"
                class="floating-chat-panel__close"
                aria-label="Close chat">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="floating-chat-panel__body">
        @include('chat._widget', [
            'userRoleName'    => $chatRoleName,
            'userRoleId'      => $chatUser?->type,
            'recentMessages'  => collect(),
            'quickHelpTopics' => $chatProvider->forUser($chatUser),
        ])
    </div>
</div>

@push('styles')
<style>
    .floating-chat-launcher {
        position: fixed;
        bottom: 20px; right: 20px;
        width: 58px; height: 58px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(var(--primary-rgb), 0.4);
        z-index: 999;
        transition: transform .2s ease, box-shadow .2s ease;
        border: none;
    }
    .floating-chat-launcher:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 12px 28px rgba(var(--primary-rgb), 0.55);
    }
    .floating-chat-launcher:active { transform: scale(0.94); }

    .floating-chat-panel {
        position: fixed;
        bottom: 90px; right: 20px;
        width: 380px;
        max-width: calc(100vw - 32px);
        height: 560px;
        max-height: calc(100vh - 120px);
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
        z-index: 999;
        display: flex; flex-direction: column;
        overflow: hidden;
        transform: translateY(20px) scale(0.96);
        opacity: 0;
        pointer-events: none;
        transition: transform .25s ease, opacity .25s ease;
    }
    .floating-chat-panel.open {
        transform: translateY(0) scale(1);
        opacity: 1;
        pointer-events: auto;
    }
    .floating-chat-panel__header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .75rem 1rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
    }
    .floating-chat-panel__sub { font-size: .75rem; opacity: .9; }
    .floating-chat-panel__close {
        background: rgba(255,255,255,.15);
        border: none; color: #fff;
        width: 32px; height: 32px;
        border-radius: .5rem;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .floating-chat-panel__body { flex: 1; overflow: hidden; padding: 0; }
    .floating-chat-panel__body .chat-widget {
        margin: 0; height: 100%; min-height: 0;
        border: none; border-radius: 0; box-shadow: none;
        max-width: 100%;
    }
    .floating-chat-panel__body .chat-widget__topics { max-height: 90px; }

    @media (max-width: 480px) {
        .floating-chat-panel {
            bottom: 0; right: 0; left: 0;
            width: 100%; height: 100vh; max-height: 100vh;
            border-radius: 0;
        }
        .floating-chat-launcher { bottom: 16px; right: 16px; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const launcher = document.getElementById('floatingChatLauncher');
    const panel    = document.getElementById('floatingChatPanel');
    const closeBtn = document.getElementById('floatingChatClose');
    if (!launcher || !panel) return;

    function open() {
        panel.classList.add('open');
        launcher.style.display = 'none';
    }
    function close() {
        panel.classList.remove('open');
        launcher.style.display = 'flex';
    }

    launcher.addEventListener('click', open);
    launcher.addEventListener('touchend', (e) => { e.preventDefault(); open(); });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
        closeBtn.addEventListener('touchend', (e) => { e.preventDefault(); close(); });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('open')) close();
    });

    if (!sessionStorage.getItem('chatAutoOpened')) {
        setTimeout(() => {
            if (!panel.classList.contains('open')) {
                open();
                sessionStorage.setItem('chatAutoOpened', '1');
            }
        }, 20000);
    }
})();
</script>
@endpush