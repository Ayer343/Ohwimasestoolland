{{-- resources/views/live-chat/_widget-styles.blade.php --}}

<style id="liveChatWidgetStyles">
    /* ============================================================
       LIVE CHAT — Floating Widget
       Uses only CSS variables for theme compatibility.
       ============================================================ */

    /* ---------- Launcher ---------- */
    .lc-launcher {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary, #4f46e5), var(--secondary, #8b5cf6));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.4);
        z-index: 998;
        border: none;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .lc-launcher:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 12px 28px rgba(79, 70, 229, 0.55);
    }
    .lc-launcher:active { transform: scale(0.94); }

    /* Hide the inner launcher when hosted inside the unified panel */
    .unified-chat__pane .lc-launcher { display: none !important; }

    .lc-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: var(--danger, #ef4444);
        color: #fff;
        font-size: .68rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--card-bg, #fff);
    }
    .lc-badge.hidden { display: none; }

    /* ---------- Panel ---------- */
    .lc-panel {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 400px;
        max-width: calc(100vw - 32px);
        height: 600px;
        max-height: calc(100vh - 120px);
        background: var(--card-bg, #ffffff);
        color: var(--text-primary, #111827);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 1rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
        z-index: 998;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateY(20px) scale(0.96);
        opacity: 0;
        pointer-events: none;
        transition: transform .25s ease, opacity .25s ease;
    }
    .lc-panel.open {
        transform: translateY(0) scale(1);
        opacity: 1;
        pointer-events: auto;
    }
    /* Hide the panel's open/close transforms when inside the unified pane */
    .unified-chat__pane .lc-panel {
        position: static !important;
        transform: none !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    /* ---------- Panel header ---------- */
    .lc-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1rem;
        background: linear-gradient(135deg, var(--primary, #4f46e5), var(--secondary, #8b5cf6));
        color: #fff;
    }
    /* Hide the inner header when the unified widget provides one */
    .unified-chat__pane .lc-panel__header { display: none !important; }

    .lc-panel__header-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .lc-panel__title { display: flex; align-items: center; gap: .5rem; font-weight: 600; }
    .lc-panel__sub { font-size: .72rem; opacity: .9; }
    .lc-panel__header-actions { display: flex; gap: .25rem; }

    .lc-icon-btn {
        background: rgba(255, 255, 255, .15);
        border: none;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: .5rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .15s ease;
    }
    .lc-icon-btn:hover { background: rgba(255, 255, 255, .3); }

    /* ---------- Body ---------- */
    .lc-panel__body {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .lc-stream {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }

    .lc-empty {
        margin: auto;
        text-align: center;
        color: var(--text-secondary, #6b7280);
    }
    .lc-empty i {
        font-size: 2rem;
        opacity: .35;
        display: block;
        margin-bottom: .5rem;
    }

    /* ---------- Messages ---------- */
    .lc-msg {
        max-width: 80%;
        padding: .55rem .9rem;
        border-radius: 1rem;
        font-size: .9rem;
        line-height: 1.45;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .lc-msg.mine {
        align-self: flex-end;
        background: var(--primary, #4f46e5);
        color: #fff;
        border-bottom-right-radius: .25rem;
    }
    .lc-msg.theirs {
        align-self: flex-start;
        background: var(--bg-secondary, #f3f4f6);
        color: var(--text-primary, #111827);
        border-bottom-left-radius: .25rem;
    }

    /* Message meta — timestamp + sender */
    .lc-msg__meta {
        font-size: .68rem;
        opacity: .7;
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: .25rem;
    }
    .lc-msg.mine .lc-msg__meta { justify-content: flex-end; }
    .lc-msg.theirs .lc-msg__meta { justify-content: flex-start; }

    /* Sender name shown above their message */
    .lc-msg__sender {
        font-size: .72rem;
        font-weight: 600;
        color: var(--primary, #4f46e5);
        margin-bottom: .15rem;
    }

    /* ---------- System messages ---------- */
    .lc-system {
        align-self: center;
        max-width: 85%;
        background: var(--bg-secondary, #f3f4f6);
        color: var(--text-secondary, #6b7280);
        font-size: .72rem;
        padding: .3rem .85rem;
        border-radius: 999px;
        margin: .5rem 0;
        font-style: italic;
        text-align: center;
        border: 1px solid var(--border-color, #e5e7eb);
    }

    /* ---------- Typing indicator ---------- */
    .lc-typing {
        display: flex;
        align-items: center;
        gap: .35rem;
        padding: .35rem 1rem .5rem;
        color: var(--text-secondary, #6b7280);
        font-size: .75rem;
    }
    .lc-typing.hidden { display: none; }
    .lc-typing__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: lc-bounce 1.2s infinite ease-in-out;
    }
    .lc-typing__dot:nth-child(2) { animation-delay: .15s; }
    .lc-typing__dot:nth-child(3) { animation-delay: .3s; }

    @keyframes lc-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: .5; }
        40%           { transform: translateY(-4px); opacity: 1; }
    }

    /* ---------- Composer ---------- */
    .lc-composer {
        display: flex;
        gap: .5rem;
        padding: .75rem 1rem;
        border-top: 1px solid var(--border-color, #e5e7eb);
        background: var(--card-bg, #fff);
        flex-shrink: 0;
        margin-top: auto;
    }
    .lc-composer input {
        flex: 1;
        padding: .65rem .9rem;
        border-radius: .75rem;
        border: 1px solid var(--border-color, #e5e7eb);
        background: var(--bg-secondary, #f9fafb);
        color: var(--text-primary, #111827);
        font-size: .9rem;
        outline: none;
        transition: border-color .15s ease;
    }
    .lc-composer input:focus { border-color: var(--primary, #4f46e5); }
    .lc-composer button {
        width: 44px;
        height: 44px;
        border-radius: .75rem;
        border: none;
        background: var(--primary, #4f46e5);
        color: #fff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: opacity .15s ease, transform .1s ease;
    }
    .lc-composer button:hover { opacity: .9; }
    .lc-composer button:disabled { opacity: .5; cursor: not-allowed; }

    /* ---------- Mobile ---------- */
    @media (max-width: 480px) {
        .lc-panel {
            bottom: 0;
            right: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
        }
        .lc-launcher { bottom: 16px; right: 16px; }
    }

    /* ---------- Reduced motion ---------- */
    @media (prefers-reduced-motion: reduce) {
        .lc-launcher,
        .lc-panel,
        .lc-typing__dot {
            transition: none;
            animation: none;
        }
    }
</style>