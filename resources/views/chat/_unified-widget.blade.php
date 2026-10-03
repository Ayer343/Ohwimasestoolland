{{--
    Unified Chat Widget
    -------------------
    Single bubble → tabbed panel with:
      • Assistant  → bot chat   (chat._widget)
      • Support    → live chat  (live-chat._widget)

    Renders inside ANY layout.
    Include ONCE:
        @include('chat._unified-widget')

    Features:
      • Click outside the panel to close it
      • Drag the launcher anywhere (persisted per-browser)
      • Double-click the launcher to reset its position
      • Panel opens from the launcher and auto-flips to stay on screen

    ⭐ FIXES APPLIED (2024 / rev 2):
      • Root wrapper is `pointer-events: none` so it never blocks
        clicks on the page (profile dropdown, logout, notifications, etc.)
      • Interactive children (launcher + panel) opt back in with
        `pointer-events: auto` — BUT the panel only opts in when it is
        actually open. When closed, the panel is `display: none`, so it
        can never intercept clicks (this was the logout bug).
      • Launcher + panel z-index lowered below Bootstrap dropdowns
        (1030 / 1040) so the header menu always wins.
      • Safety-net script is now state-aware: it re-applies
        `pointer-events` only to elements inside an OPEN panel, and it
        never un-hides a closed panel.
      • `--booting` state only hides children, never blocks the page.
--}}

@auth
@php
    $__ucUser    = auth()->user();
    $__ucRole    = $__ucUser?->type_name ?? 'User';
    $__ucIsAgent = in_array($__ucUser->type, [
        \App\Models\User::TYPE_ADMIN,
        \App\Models\User::TYPE_SUPER_ADMIN,
        \App\Models\User::TYPE_DEVELOPER,
    ], true);
    $__ucTopics  = app(\App\Services\ChatHelpTopicProvider::class)->forUser($__ucUser);
@endphp

{{-- ⭐ `unified-chat--booting` hides the widget's CHILDREN (not the root)
     until its JS is fully wired. The root stays in the DOM but is
     click-through (`pointer-events: none`) so it can never intercept
     clicks on the rest of the page. --}}
<div class="unified-chat unified-chat--booting"
     id="unifiedChat"
     data-user-id="{{ $__ucUser->id }}"
     data-user-name="{{ $__ucUser->name }}"
     data-role-name="{{ $__ucRole }}"
     data-is-agent="{{ $__ucIsAgent ? '1' : '0' }}"
     style="pointer-events: none;">

    {{-- ================= LAUNCHER ================= --}}
    {{-- ⭐ Draggable + click-to-open. Double-click resets position.
         `pointer-events: auto` re-applied inline so this works even if
         the external stylesheet hasn't loaded yet.
         `z-index` inline keeps it below Bootstrap dropdowns. --}}
    <button type="button"
            id="unifiedChatLauncher"
            class="unified-chat__launcher"
            style="pointer-events: auto; z-index: 1030;"
            title="Chat with us (drag to move, double-click to reset)"
            aria-label="Open chat">
        <i class="fas fa-comments"></i>
        <span id="unifiedChatBadge" class="unified-chat__badge hidden">0</span>
    </button>

    {{-- ================= PANEL ================= --}}
    {{-- ⭐ Panel is CLOSED by default. We inline `display: none` so it
         cannot intercept clicks even before the stylesheet loads — this
         is the fix for the profile-dropdown / logout click-trap.
         When opened, JS toggles `.is-open` which flips it to
         `display: flex; pointer-events: auto`. --}}
    <div id="unifiedChatPanel"
         class="unified-chat__panel"
         role="dialog"
         aria-label="Chat panel"
         aria-hidden="true"
         style="display: none; pointer-events: none; z-index: 1040;">

        {{-- Header --}}
        <div class="unified-chat__header">
            <div class="unified-chat__title">
                <i class="fas fa-comments" id="unifiedChatHeaderIcon"></i>
                <div>
                    <div class="unified-chat__title-main" id="unifiedChatHeaderTitle">
                        Assistant
                    </div>
                    <div class="unified-chat__title-sub" id="unifiedChatHeaderSub">
                        Helping you as <strong>{{ $__ucRole }}</strong>
                    </div>
                </div>
            </div>
            <div class="unified-chat__actions">
                <button type="button"
                        id="unifiedChatExpand"
                        class="unified-chat__icon-btn"
                        title="Open full view"
                        aria-label="Open full view">
                    <i class="fas fa-expand-alt"></i>
                </button>
                <button type="button"
                        id="unifiedChatClose"
                        class="unified-chat__icon-btn"
                        title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="unified-chat__tabs" role="tablist">
            <button type="button"
                    class="unified-chat__tab is-active"
                    data-tab="assistant"
                    role="tab"
                    aria-selected="true">
                <i class="fas fa-robot"></i>
                <span>Assistant</span>
            </button>
            <button type="button"
                    class="unified-chat__tab"
                    data-tab="support"
                    role="tab"
                    aria-selected="false">
                <i class="fas fa-headset"></i>
                <span>Support</span>
                <span id="unifiedChatSupportBadge" class="unified-chat__tab-badge hidden">0</span>
            </button>
        </div>

        {{-- Tab content --}}
        <div class="unified-chat__body">

            {{-- Assistant tab — bot chat --}}
            <div class="unified-chat__pane is-active" data-pane="assistant">
                @include('chat._widget', [
                    'userRoleName'    => $__ucRole,
                    'userRoleId'      => $__ucUser->type ?? null,
                    'recentMessages'  => collect(),
                    'quickHelpTopics' => $__ucTopics,
                ])
            </div>

            {{-- Support tab — live chat --}}
            <div class="unified-chat__pane" data-pane="support">
                @include('live-chat._widget')
            </div>
        </div>
    </div>
</div>

@include('chat._unified-widget-styles')
@include('chat._unified-widget-scripts')

{{-- ⭐ Safety net — state-aware.
     • Root always `pointer-events: none`.
     • Launcher always `pointer-events: auto`.
     • Panel gets `pointer-events: auto` ONLY when it is visible
       (`.is-open` / aria-hidden="false" / display !== 'none').
     • Interactive descendants inside the panel are re-enabled only
       when the panel is open.
     • Never un-hides a closed panel.
     • Watches panel attribute changes so the fix tracks open/close. --}}
<script>
    (function () {
        var root, panel, launcher;

        function isPanelOpen() {
            if (!panel) return false;
            if (panel.classList.contains('is-open')) return true;
            if (panel.getAttribute('aria-hidden') === 'false') return true;
            var d = panel.style.display || getComputedStyle(panel).display;
            return d && d !== 'none';
        }

        function applyUnifiedChatFixes() {
            if (!root) root = document.getElementById('unifiedChat');
            if (!root) return;

            // Root must NEVER block clicks on the page.
            root.style.pointerEvents = 'none';

            // Launcher is always clickable.
            launcher = launcher || document.getElementById('unifiedChatLauncher');
            if (launcher) launcher.style.pointerEvents = 'auto';

            panel = panel || document.getElementById('unifiedChatPanel');
            if (!panel) return;

            var open = isPanelOpen();

            // Panel: click-through when closed, clickable when open.
            panel.style.pointerEvents = open ? 'auto' : 'none';

            // Interactive descendants: opt-in only when the panel is open.
            var interactive = panel.querySelectorAll(
                'button, a, input, textarea, select, iframe, form, label, ' +
                '[role="button"], [tabindex], [contenteditable]'
            );
            interactive.forEach(function (el) {
                el.style.pointerEvents = open ? 'auto' : '';
            });

            // Watchdog: clear booting flag even if the widget script failed.
            setTimeout(function () {
                root.classList.remove('unified-chat--booting');
            }, 1500);
        }

        function watchPanel() {
            panel = panel || document.getElementById('unifiedChatPanel');
            if (!panel || panel.__ucWatchAttached) return;
            panel.__ucWatchAttached = true;
            new MutationObserver(function () {
                applyUnifiedChatFixes();
            }).observe(panel, {
                attributes: true,
                attributeFilter: ['class', 'aria-hidden', 'style'],
            });
        }

        function boot() {
            root = document.getElementById('unifiedChat');
            panel = document.getElementById('unifiedChatPanel');
            launcher = document.getElementById('unifiedChatLauncher');
            applyUnifiedChatFixes();
            watchPanel();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }

        // Re-run once everything settles (fonts, images, inner widgets).
        window.addEventListener('load', boot);
    })();
</script>
@endauth