{{-- resources/views/chat/_unified-widget-styles.blade.php --}}

<style id="unifiedChatStyles">
    /* ============================================================
       UNIFIED CHAT — launcher + tabbed panel
       Inherits theme via CSS variables.

       NOTE ON INNER WIDGET RESETS
       ---------------------------
       The live-chat widget (`.lc-*`) owns its own reset rules
       inside `live-chat/_widget-styles.blade.php`, scoped under
       `.unified-chat__pane`. We do NOT redeclare those here —
       single source of truth.

       This file is responsible only for:
         • unified shell chrome (launcher, panel, header, tabs, panes)
         • resetting the assistant widget (`.chat-widget*`)
         • shared sizing of the pane body

       ⭐ POINTER-EVENTS CONTRACT
       ---------------------------
       The root `.unified-chat` is a full-viewport fixed container
       that exists ONLY to position its children. It must NEVER
       intercept clicks meant for the rest of the page (profile
       dropdown, logout button, notification bell, modals, etc.).

       Therefore:
         • `.unified-chat`          → pointer-events: none
         • `.unified-chat__launcher`→ pointer-events: auto
         • `.unified-chat__panel`   → display: none + pointer-events: none
                                      when closed (removed from hit-test)
         • `.unified-chat__panel.open` → display: flex + pointer-events: auto
         • any interactive descendant → pointer-events: auto

       If you add new top-level children to the widget, opt them
       back in explicitly. Never let the root become clickable.
       ============================================================ */

    /* ============================================================
       ⭐ POINTER-EVENTS CONTRACT — DO NOT REMOVE
       ============================================================ */

    /* Root: full-screen fixed container, always click-through */
    .unified-chat {
        position: fixed;
        inset: 0;
        z-index: 996;              /* below launcher/panel (997) */
        pointer-events: none;      /* ⭐ critical */
    }

    /* Interactive children opt back in */
    .unified-chat__launcher {
        pointer-events: auto;      /* ⭐ critical */
    }

    /* Catch-all for descendants that must receive clicks.
       Kept as a safety net for dynamically rendered content
       (bot messages, topic chips, live-chat bubbles, etc.) */
    .unified-chat__panel button,
    .unified-chat__panel a,
    .unified-chat__panel input,
    .unified-chat__panel textarea,
    .unified-chat__panel select,
    .unified-chat__panel [role="button"],
    .unified-chat__panel [tabindex]:not([tabindex="-1"]) {
        pointer-events: auto;
    }

    /* ============================================================
       ⭐ BOOTING STATE
       ------------------------------------------------------------
       Until the widget's JS removes `.unified-chat--booting`, the
       launcher and panel are completely invisible and non-interactive.
       This prevents the "flash open + collapse" bug on page load.
       ============================================================ */
    .unified-chat--booting .unified-chat__launcher,
    .unified-chat--booting .unified-chat__panel {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition: none !important;
    }

    /* Once booted, gently fade the launcher in (only once). */
    .unified-chat:not(.unified-chat--booting) .unified-chat__launcher {
        animation: unifiedChatLauncherIn .22s ease-out both;
    }

    @keyframes unifiedChatLauncherIn {
        from { opacity: 0; transform: scale(.85); }
        to   { opacity: 1; transform: scale(1); }
    }

    /* ============================================================
       LAUNCHER
       ============================================================ */
    .unified-chat__launcher {
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
        box-shadow: 0 8px 24px rgba(79, 70, 229, .4);
        z-index: 997;
        border: none;
        transition: transform .2s ease, box-shadow .2s ease;

        /* ⭐ Pointer-events contract — this element must be clickable */
        pointer-events: auto;

        /* ⭐ Drag & drop affordances */
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        -webkit-tap-highlight-color: transparent;
    }
    .unified-chat__launcher:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 12px 28px rgba(79, 70, 229, .55);
    }
    .unified-chat__launcher:active { transform: scale(.94); }

    .unified-chat__launcher.is-dragging {
        transition: none !important;
        cursor: grabbing !important;
        box-shadow: 0 16px 36px rgba(79, 70, 229, .55);
        transform: scale(1.06);
    }
    .unified-chat__launcher.is-dragging:hover {
        transform: scale(1.06);
    }

    /* ============================================================
       ⭐ SIDEBAR-AWARE POSITIONING (JS-only, documented here)
       ------------------------------------------------------------
       The launcher and panel are clamped in JS so they never sit
       on top of the sidebar. See `chat/_unified-widget-scripts.blade.php`
       (`clampToViewport()` + `positionPanelNearLauncher()`).
       ============================================================ */

    .unified-chat__badge {
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
        pointer-events: none;   /* badge itself isn't clickable */
    }
    .unified-chat__badge.hidden { display: none; }

    /* ============================================================
       ⭐ PANEL
       ------------------------------------------------------------
       ⭐ CRITICAL: the panel is `display: none` when closed so it
       is fully removed from hit-testing. This is what guarantees
       the profile dropdown / logout button can never be blocked
       by an invisible panel. JS toggles `.open` to show it.
       ============================================================ */
    .unified-chat__panel {
        position: fixed;
        bottom: 90px;                /* fallback if JS hasn't positioned it */
        right: 20px;                 /* fallback if JS hasn't positioned it */
        width: 400px;
        max-width: calc(100vw - 32px);
        height: 640px;
        max-height: calc(100vh - 120px);
        background: var(--card-bg, #fff);
        color: var(--text-primary, #111827);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 1rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
        z-index: 997;
        flex-direction: column;
        overflow: hidden;

        /* ⭐ CLOSED STATE — never in the render tree. */
        display: none;
        opacity: 0;
        pointer-events: none;

        /* Default entry: slide up from below (panel opens above the launcher). */
        transform: translateY(20px) scale(.96);
        transform-origin: bottom right;
        transition: transform .25s ease, opacity .25s ease;
    }

    /* ⭐ OPEN STATE — visible, clickable, animated in. */
    .unified-chat__panel.open {
        display: flex;
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }

    /* ⭐ Flip variants — applied by JS depending on which side of the
       launcher the panel is placed. They only tweak the transform so
       the panel slides in from the direction it will rest in. */

    .unified-chat__panel--above {
        transform: translateY(20px) scale(.96);
    }
    .unified-chat__panel--above.open {
        transform: translateY(0) scale(1);
    }

    .unified-chat__panel--below {
        transform: translateY(-20px) scale(.96);
    }
    .unified-chat__panel--below.open {
        transform: translateY(0) scale(1);
    }

    .unified-chat__panel--left  { transform-origin: bottom left; }
    .unified-chat__panel--right { transform-origin: bottom right; }
    .unified-chat__panel--below.unified-chat__panel--left  { transform-origin: top left; }
    .unified-chat__panel--below.unified-chat__panel--right { transform-origin: top right; }

    /* ---------- Header ---------- */
    .unified-chat__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1rem;
        background: linear-gradient(135deg, var(--primary, #4f46e5), var(--secondary, #8b5cf6));
        color: #fff;
        flex-shrink: 0;
    }
    .unified-chat__title {
        display: flex;
        align-items: center;
        gap: .75rem;
        min-width: 0;
    }
    .unified-chat__title > i {
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .unified-chat__title-main {
        font-weight: 600;
        line-height: 1.1;
    }
    .unified-chat__title-sub {
        font-size: .72rem;
        opacity: .9;
    }
    .unified-chat__actions { display: flex; gap: .25rem; }

    .unified-chat__icon-btn {
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
        pointer-events: auto;        /* ⭐ explicit */
    }
    .unified-chat__icon-btn:hover { background: rgba(255, 255, 255, .3); }
    .unified-chat__icon-btn.hidden { display: none !important; }

    /* ---------- Tabs ---------- */
    .unified-chat__tabs {
        display: flex;
        background: var(--bg-secondary, #f9fafb);
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        flex-shrink: 0;
    }
    .unified-chat__tab {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        padding: .75rem .5rem;
        background: transparent;
        border: none;
        color: var(--text-secondary, #6b7280);
        font-size: .85rem;
        font-weight: 500;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all .15s ease;
        position: relative;
        pointer-events: auto;        /* ⭐ explicit */
    }
    .unified-chat__tab:hover {
        color: var(--text-primary, #111827);
        background: rgba(var(--primary-rgb, 79, 70, 229), .04);
    }
    .unified-chat__tab.is-active {
        color: var(--primary, #4f46e5);
        border-bottom-color: var(--primary, #4f46e5);
        background: var(--card-bg, #fff);
    }
    .unified-chat__tab-badge {
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: var(--danger, #ef4444);
        color: #fff;
        font-size: .65rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    .unified-chat__tab-badge.hidden { display: none; }

    /* ---------- Body / panes ---------- */
    .unified-chat__body {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    .unified-chat__pane {
        display: none;
        flex: 1;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    .unified-chat__pane.is-active { display: flex; }

    /* ============================================================
       INNER WIDGET RESET — ASSISTANT ONLY
       ------------------------------------------------------------
       The live-chat widget (`.lc-*`) already ships its own reset
       rules in `live-chat/_widget-styles.blade.php` scoped under
       `.unified-chat__pane`. We only reset the assistant widget
       here (`.chat-widget*`) plus any legacy selectors.
       ============================================================ */

    .unified-chat__pane .chat-widget__header {
        display: none !important;
    }

    .unified-chat__pane .chat-widget {
        position: static !important;
        width: 100% !important;
        height: 100% !important;
        max-width: 100% !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        transform: none !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        bottom: auto !important;
        right: auto !important;
        left: auto !important;
        top: auto !important;
        z-index: auto !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    .unified-chat__pane .chat-widget__launcher,
    .unified-chat__pane .floating-chat-launcher {
        display: none !important;
    }

    .unified-chat__pane .chat-widget__stream,
    .unified-chat__pane .live-chat-stream {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        pointer-events: auto !important;
    }

    .unified-chat__pane .chat-widget__composer,
    .unified-chat__pane .live-chat-composer {
        flex-shrink: 0 !important;
        margin-top: auto !important;
    }

    .unified-chat__pane .chat-widget__topics {
        max-height: 100px !important;
        overflow-y: auto !important;
    }

    .unified-chat__pane .chat-widget__stream {
        padding: 1rem !important;
    }

    /* ============================================================
       ⭐ MOBILE
       ============================================================ */
    @media (max-width: 480px) {
        .unified-chat__panel {
            bottom: 0;
            right: 0;
            left: 0;
            top: 0 !important;
            width: 100%;
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
        }
        .unified-chat__launcher { bottom: 16px; right: 16px; }

        .unified-chat__panel,
        .unified-chat__panel--above,
        .unified-chat__panel--below {
            transform: translateY(12px);
            transform-origin: center bottom;
        }
        .unified-chat__panel.open,
        .unified-chat__panel--above.open,
        .unified-chat__panel--below.open {
            transform: translateY(0);
        }
    }

    /* ============================================================
       REDUCED MOTION
       ============================================================ */
    @media (prefers-reduced-motion: reduce) {
        .unified-chat__launcher,
        .unified-chat__panel,
        .unified-chat__tab { transition: none; }

        .unified-chat:not(.unified-chat--booting) .unified-chat__launcher {
            animation: none;
        }
    }
</style>