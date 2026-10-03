@push('styles')
<style>
    /* ============================================================
       Universal Chat Widget — assistant / bot pane
       ------------------------------------------------------------
       Contexts:
         A) Standalone  → rendered directly in a page (max 720px,
                          centred, own border + shadow).
         B) Embedded    → rendered inside `.unified-chat__pane`
                          (the unified widget's Assistant tab).
                          In this mode the unified stylesheet owns
                          the outer chrome; this file only styles
                          the inner pieces (stream, bubbles, composer).

       ⭐ Context detection uses `:has()` on <body>. If the page
       contains a `.unified-chat__pane`, all "standalone chrome"
       rules are skipped automatically — no `!important` war needed.
       (For very old browsers without `:has()`, the unified
        stylesheet's `!important` overrides still win.)
       ============================================================ */

    /* ---------- Variables (shared) ---------- */
    .chat-widget {
        --cw-bg:        var(--card-bg, #ffffff);
        --cw-fg:        var(--text-primary, #111827);
        --cw-muted:     var(--text-secondary, #6b7280);
        --cw-border:    var(--border-color, #e5e7eb);
        --cw-accent:    var(--primary, #4f46e5);
        --cw-user-bg:   var(--primary, #4f46e5);
        --cw-user-fg:   #ffffff;
        --cw-bot-bg:    var(--bg-secondary, #f3f4f6);
        --cw-bot-fg:    var(--text-primary, #111827);

        display: flex;
        flex-direction: column;
        background: var(--cw-bg);
        color: var(--cw-fg);
        overflow: hidden;

        /* Safe defaults for embedded mode — standalone chrome is
           applied below in a separate block. */
        width: 100%;
        max-width: 100%;
        height: 100%;
        min-height: 0;
        margin: 0;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    /* ---------- Standalone chrome (only outside the unified panel) ---------- */
    body:not(:has(.unified-chat__pane)) .chat-widget {
        max-width: 720px;
        margin: 1.5rem auto;
        height: calc(100vh - 180px);
        min-height: 480px;
        border: 1px solid var(--cw-border);
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
    }

    /* ---------- Dark-mode contrast for user bubbles ---------- */
    /* In dark mode `--primary` is a light blue (#60a5fa); white text
       on it is low-contrast. Use a dark foreground instead. */
    [data-theme="dark"] .chat-widget,
    .dark-theme .chat-widget {
        --cw-user-fg: #0f172a;              /* slate-900 */
    }

    /* ---------- Header ---------- */
    .chat-widget__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1rem;
        background: var(--cw-accent);
        color: #fff;
        flex-shrink: 0;
    }
    .chat-widget__title { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .chat-widget__title i { font-size: 1.5rem; flex-shrink: 0; }
    .chat-widget__title-main { font-weight: 600; line-height: 1.1; }
    .chat-widget__title-sub { font-size: .75rem; opacity: .9; }
    .chat-widget__actions { display: flex; gap: .25rem; }
    .chat-widget__icon-btn {
        background: rgba(255, 255, 255, .15);
        color: #fff;
        border: none;
        border-radius: .5rem;
        width: 34px;
        height: 34px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }
    .chat-widget__icon-btn:hover { background: rgba(255, 255, 255, .3); }

    /* ---------- Topics ---------- */
    .chat-widget__topics {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        padding: .75rem 1rem;
        border-bottom: 1px solid var(--cw-border);
        background: var(--cw-bot-bg);
        max-height: 120px;
        overflow-y: auto;
        flex-shrink: 0;
        overscroll-behavior: contain;
    }
    .chat-widget__topic {
        font-size: .75rem;
        padding: .3rem .65rem;
        border-radius: 999px;
        border: 1px solid var(--cw-border);
        background: var(--cw-bg);
        color: var(--cw-fg);
        cursor: pointer;
        transition: border-color .15s, color .15s, background .15s;
        /* ⭐ No `transform: translateY(-1px)` — it caused scrollbar
           flicker when topics overflowed. Colour-only hover instead. */
    }
    .chat-widget__topic:hover {
        border-color: var(--cw-accent);
        color: var(--cw-accent);
        background: rgba(var(--primary-rgb, 79, 70, 229), .06);
    }

    /* ---------- Stream ---------- */
    .chat-widget__stream {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: .6rem;
        background: var(--cw-bg);
        overscroll-behavior: contain;
    }
    .chat-widget__msg { display: flex; }
    .chat-widget__msg--user { justify-content: flex-end; }
    .chat-widget__msg--bot  { justify-content: flex-start; }
    .chat-widget__bubble {
        max-width: 80%;
        padding: .6rem .9rem;
        border-radius: 1rem;
        font-size: .9rem;
        line-height: 1.45;
        word-wrap: break-word;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }
    .chat-widget__msg--user .chat-widget__bubble {
        background: var(--cw-user-bg);
        color: var(--cw-user-fg);
        border-bottom-right-radius: .25rem;
    }
    .chat-widget__msg--bot .chat-widget__bubble {
        background: var(--cw-bot-bg);
        color: var(--cw-bot-fg);
        border-bottom-left-radius: .25rem;
    }
    .chat-widget__empty {
        margin: auto;
        text-align: center;
        color: var(--cw-muted);
    }
    .chat-widget__empty i {
        font-size: 2rem;
        opacity: .4;
        display: block;
        margin-bottom: .5rem;
    }

    /* ---------- Typing indicator ---------- */
    .chat-widget__typing {
        display: flex;
        gap: .25rem;
        padding: .4rem 1rem .6rem;
        flex-shrink: 0;
    }
    /* ⭐ Self-contained `.hidden` — doesn't rely on a global rule. */
    .chat-widget__typing.hidden { display: none !important; }
    .chat-widget__typing span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--cw-muted);
        animation: cw-bounce 1.2s infinite ease-in-out;
    }
    .chat-widget__typing span:nth-child(2) { animation-delay: .15s; }
    .chat-widget__typing span:nth-child(3) { animation-delay: .3s; }

    @keyframes cw-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: .5; }
        40%           { transform: translateY(-5px); opacity: 1; }
    }

    /* ---------- Composer ---------- */
    .chat-widget__composer {
        display: flex;
        gap: .5rem;
        padding: .75rem 1rem;
        border-top: 1px solid var(--cw-border);
        background: var(--cw-bg);
        flex-shrink: 0;
    }
    .chat-widget__composer input {
        flex: 1;
        min-width: 0;
        padding: .65rem .9rem;
        border-radius: .75rem;
        border: 1px solid var(--cw-border);
        background: var(--cw-bot-bg);
        color: var(--cw-fg);
        font-size: .9rem;
        outline: none;
        transition: border-color .15s;
    }
    .chat-widget__composer input:focus {
        border-color: var(--cw-accent);
    }
    .chat-widget__composer button {
        width: 44px;
        height: 44px;
        border-radius: .75rem;
        border: none;
        background: var(--cw-accent);
        color: #fff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: opacity .15s;
        flex-shrink: 0;
    }
    .chat-widget__composer button:hover { opacity: .9; }
    .chat-widget__composer button:disabled { opacity: .5; cursor: not-allowed; }

    /* ---------- Global `.hidden` safety, scoped to this widget ---------- */
    .chat-widget .hidden { display: none !important; }

    /* ---------- Accessibility ---------- */
    @media (prefers-reduced-motion: reduce) {
        .chat-widget__typing span,
        .chat-widget__topic,
        .chat-widget__composer button {
            animation: none;
            transition: none;
        }
    }

    /* ---------- Standalone mobile ---------- */
    /* ⭐ Only applies outside the unified panel — the unified widget
       handles its own mobile sheet via its own media query. */
    @media (max-width: 640px) {
        body:not(:has(.unified-chat__pane)) .chat-widget {
            margin: 0;
            border-radius: 0;
            height: calc(100vh - 120px);
        }
        .chat-widget__bubble { max-width: 90%; }
    }
</style>
@endpush