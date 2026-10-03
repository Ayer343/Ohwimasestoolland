{{-- resources/views/chat/_unified-widget-scripts.blade.php --}}

<script id="unifiedChatScripts">
(function () {
    'use strict';

    const root       = document.getElementById('unifiedChat');
    const launcher   = document.getElementById('unifiedChatLauncher');
    const panel      = document.getElementById('unifiedChatPanel');
    const closeBtn   = document.getElementById('unifiedChatClose');
    const expandBtn  = document.getElementById('unifiedChatExpand');
    const badge      = document.getElementById('unifiedChatBadge');
    const supBadge   = document.getElementById('unifiedChatSupportBadge');
    const iconEl     = document.getElementById('unifiedChatHeaderIcon');
    const titleEl    = document.getElementById('unifiedChatHeaderTitle');
    const subEl      = document.getElementById('unifiedChatHeaderSub');
    const tabs       = document.querySelectorAll('.unified-chat__tab');
    const panes      = document.querySelectorAll('.unified-chat__pane');

    if (!root || !launcher || !panel) return;

    const ROLE           = root.dataset.roleName || 'User';
    const BASE_LIVE_CHAT = @json(url('/live-chat'));

    /* =========================================================
       Panel positioning constants
       ========================================================= */
    const PANEL_GAP    = 12;   // px gap between launcher and panel
    const PANEL_MARGIN = 8;    // px min margin from viewport edges

    /* ⭐ SIDEBAR-AWARE: gap between the sidebar's right edge and the
       launcher / panel. Prevents the widget from ever sitting on
       top of the sidebar in either expanded or collapsed state. */
    const SIDEBAR_MARGIN = 12;

    /* ⭐ HEADER/UI PROTECTION
       ---------------------------------------------------------
       Elements matching this selector will never trigger the
       click-outside-to-close behavior. This protects the profile
       dropdown (and its logout form), the notification bell, and
       any other global UI that lives in the header, from having
       its click intercepted by the chat widget.

       Add `data-no-chat-close` to any wrapper that should be
       immune — the header, the notification wrapper, dropdown
       menus, etc. */
    const NO_CLOSE_SELECTOR = [
        'header',
        '.devheader',
        '.notification-wrapper',
        '.profile-dropdown',
        '.user-menu',
        '[data-no-chat-close]',
    ].join(',');

    /* =========================================================
       Shared boot state for the bridge
       ========================================================= */
    window.__unifiedChat = window.__unifiedChat || {};
    window.__unifiedChat.isReady = false;
    window.__unifiedChat.isOpen  = false;

    /* =========================================================
       ⭐ SIDEBAR-AWARE HELPERS
       ========================================================= */
    function getSidebarEl() {
        return document.querySelector('.sidebar, #sidebar');
    }

    function getSidebarRightEdge() {
        const sidebar = getSidebarEl();
        if (!sidebar) return 0;

        const cs = getComputedStyle(sidebar);
        if (cs.display === 'none' ||
            cs.visibility === 'hidden' ||
            parseFloat(cs.opacity) === 0) {
            return 0;
        }

        const rect = sidebar.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return 0;
        if (rect.right <= 0) return 0;

        return rect.right;
    }

    function getMinLeftForWidget() {
        const edge = getSidebarRightEdge();
        return edge > 0 ? edge + SIDEBAR_MARGIN : 0;
    }

    /* =========================================================
       ⭐ PANEL VISIBILITY (single source of truth)
       ---------------------------------------------------------
       All show/hide goes through these two helpers so display,
       pointer-events, aria-hidden, and the `.open` class always
       stay in sync — regardless of what the safety-net script in
       the blade does.
       ========================================================= */
    function showPanel() {
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        panel.style.display       = 'flex';
        panel.style.pointerEvents = 'auto';
    }

    function hidePanel() {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        panel.style.pointerEvents = 'none';
        panel.style.display       = 'none';
    }

    function isPanelOpen() {
        return panel.classList.contains('open');
    }

    /* =========================================================
       ⭐ PANEL POSITIONING — open near the launcher
       ========================================================= */
    function positionPanelNearLauncher() {
        if (window.matchMedia('(max-width: 480px)').matches) {
            panel.style.left   = '';
            panel.style.top    = '';
            panel.style.bottom = '';
            panel.style.right  = '';
            panel.classList.remove(
                'unified-chat__panel--above', 'unified-chat__panel--below',
                'unified-chat__panel--left',  'unified-chat__panel--right'
            );
            return;
        }

        const vw = window.innerWidth;
        const vh = window.innerHeight;

        const pw = panel.offsetWidth  || 400;
        const ph = panel.offsetHeight || 640;

        const lr = launcher.getBoundingClientRect();

        // ---------- Vertical decision ----------
        const spaceAbove = lr.top - PANEL_MARGIN;
        const spaceBelow = vh - lr.bottom - PANEL_MARGIN;

        const placeAbove = spaceAbove >= ph + PANEL_GAP || spaceAbove >= spaceBelow;

        let top;
        let verticalClass;
        if (placeAbove) {
            top = lr.top - PANEL_GAP - ph;
            verticalClass = 'unified-chat__panel--above';
        } else {
            top = lr.bottom + PANEL_GAP;
            verticalClass = 'unified-chat__panel--below';
        }

        top = Math.max(PANEL_MARGIN, Math.min(top, vh - ph - PANEL_MARGIN));

        // ---------- Horizontal decision ----------
        const launcherCenterX = lr.left + lr.width / 2;
        const prefersLeft = launcherCenterX < vw / 2;

        let left;
        let horizontalClass;
        if (prefersLeft) {
            left = lr.left;
            horizontalClass = 'unified-chat__panel--left';
        } else {
            left = lr.right - pw;
            horizontalClass = 'unified-chat__panel--right';
        }

        const minLeft = getMinLeftForWidget();
        left = Math.max(minLeft, Math.min(left, vw - pw - PANEL_MARGIN));

        // ---------- Apply ----------
        panel.style.bottom = 'auto';
        panel.style.right  = 'auto';
        panel.style.left   = left + 'px';
        panel.style.top    = top + 'px';

        panel.classList.remove(
            'unified-chat__panel--above', 'unified-chat__panel--below',
            'unified-chat__panel--left',  'unified-chat__panel--right'
        );
        panel.classList.add(verticalClass, horizontalClass);
    }

    /* =========================================================
       OPEN / CLOSE
       ========================================================= */
    function open() {
        // Show first so offsetWidth/Height are measurable for positioning.
        showPanel();
        positionPanelNearLauncher();

        launcher.style.display = 'none';
        hideBadge();

        window.__unifiedChat.isOpen = true;

        requestAnimationFrame(() => scrollActivePaneToBottom(activeTab()));

        dispatch('unified-chat:opened', { tab: activeTab() });

        if (activeTab() === 'support') {
            dispatch('live-chat:opened', {});
        }
    }

    function close() {
        hidePanel();

        launcher.style.display = 'flex';

        window.__unifiedChat.isOpen = false;

        dispatch('unified-chat:closed', {});
        dispatch('live-chat:closed', {});
    }

    window.openUnifiedChatPanel  = open;
    window.closeUnifiedChatPanel = close;

    launcher.addEventListener('click', open);
    launcher.addEventListener('touchend', (e) => { e.preventDefault(); open(); });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
        closeBtn.addEventListener('touchend', (e) => { e.preventDefault(); close(); });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isPanelOpen()) close();
    });

    /* =========================================================
       ⭐ CLICK-OUTSIDE-TO-CLOSE
       ---------------------------------------------------------
       Rules (in order of priority):
         1. Never fire if the panel is already closed.
         2. Never fire for non-primary mouse buttons.
         3. Never fire if the click is inside the panel.
         4. Never fire if the click is on the launcher.
         5. Never fire if the click is inside another dialog/modal.
         6. ⭐ Never fire if the click is inside the header, the
            profile dropdown, the notification bell, or anything
            marked `data-no-chat-close`.
         7. NEVER call preventDefault() or stopPropagation().

       ⭐ Runs in BUBBLE phase so it can never preempt the click
       handlers that own the header's dropdown / logout button.
       ========================================================= */
    function isInsidePanel(node) {
        return node && panel.contains(node);
    }

    function isOnLauncher(node) {
        return node && launcher.contains(node);
    }

    function isInOtherDialog(node) {
        if (!node || !node.closest) return false;
        const dialog = node.closest(
            '[role="dialog"], .modal, [class*="modal"], [class*="Modal"], [class*="dialog"], [class*="Dialog"]'
        );
        return dialog && dialog !== panel && !panel.contains(dialog);
    }

    function isInProtectedRegion(node) {
        if (!node || !node.closest) return false;
        try {
            return !!node.closest(NO_CLOSE_SELECTOR);
        } catch (_) {
            return false;
        }
    }

    document.addEventListener('pointerdown', (e) => {
        if (!isPanelOpen()) return;
        if (e.button !== undefined && e.button !== 0) return;

        const t = e.target;

        if (isInsidePanel(t))       return;
        if (isOnLauncher(t))        return;
        if (isInOtherDialog(t))     return;
        if (isInProtectedRegion(t)) return;

        // ⭐ Bubble phase → do not preventDefault / stopPropagation.
        close();
    }, false);   // ← was `true` (capture); now false (bubble)

    /* =========================================================
       OPEN FULL VIEW
       ========================================================= */
    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            const convId = sessionStorage.getItem('lc.conversationId');
            window.location.href = convId
                ? `${BASE_LIVE_CHAT}/${convId}`
                : BASE_LIVE_CHAT;
        });
    }

    /* =========================================================
       TABS
       ========================================================= */
    function activeTab() {
        const t = document.querySelector('.unified-chat__tab.is-active');
        return t ? t.dataset.tab : 'assistant';
    }

    function switchTab(key) {
        tabs.forEach((t) => {
            const on = t.dataset.tab === key;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        panes.forEach((p) => {
            p.classList.toggle('is-active', p.dataset.pane === key);
        });

        updateHeaderFor(key);

        // ⭐ Re-anchor after the pane changes height — otherwise the
        // panel can drift off-screen when switching between a short
        // and a tall tab.
        requestAnimationFrame(() => {
            if (isPanelOpen()) positionPanelNearLauncher();
            scrollActivePaneToBottom(key);
        });

        dispatch('unified-chat:tab-changed', { tab: key });

        if (key === 'support') {
            dispatch('live-chat:opened', {});
        } else {
            dispatch('live-chat:closed', {});
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    function updateHeaderFor(key) {
        if (!titleEl || !subEl || !iconEl) return;

        if (key === 'assistant') {
            iconEl.className = 'fas fa-robot';
            titleEl.textContent = 'Assistant';
            subEl.innerHTML = `Helping you as <strong>${escapeHtml(ROLE)}</strong>`;
            expandBtn?.classList.add('hidden');
        } else {
            iconEl.className = 'fas fa-headset';
            titleEl.textContent = 'Support Chat';
            subEl.textContent = 'Real people, real answers';
            expandBtn?.classList.remove('hidden');
        }
    }

    /* =========================================================
       SCROLL
       ========================================================= */
    function scrollActivePaneToBottom(key) {
        const selector = key === 'assistant' ? '#chatStream' : '#liveChatStream';
        const stream   = panel.querySelector(selector);
        if (!stream) return;

        const nearBottom = stream.scrollHeight - stream.scrollTop - stream.clientHeight < 120;
        if (nearBottom || stream.scrollTop === 0) {
            stream.scrollTop = stream.scrollHeight;
        }
    }

    window.addEventListener('unified-chat:message-appended', (e) => {
        if (!isPanelOpen()) return;
        if (e.detail.pane !== activeTab()) return;

        requestAnimationFrame(() => scrollActivePaneToBottom(e.detail.pane));
    });

    /* =========================================================
       BADGES
       ========================================================= */
    function updateBadges() {
        const supportCount = parseInt(
            document.getElementById('liveChatUnreadBadge')?.textContent || '0',
            10
        ) || 0;

        if (badge) {
            if (supportCount > 0) {
                badge.textContent = supportCount > 99 ? '99+' : supportCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        if (supBadge) {
            if (supportCount > 0) {
                supBadge.textContent = supportCount > 99 ? '99+' : supportCount;
                supBadge.classList.remove('hidden');
            } else {
                supBadge.classList.add('hidden');
            }
        }
    }

    function hideBadge() {
        badge?.classList.add('hidden');
    }

    /* ⭐ Guard against double-initialization (e.g., SPA re-mount). */
    if (window.__unifiedChat.__badgeInterval) {
        clearInterval(window.__unifiedChat.__badgeInterval);
    }
    window.__unifiedChat.__badgeInterval = setInterval(updateBadges, 3000);
    updateBadges();

    /* =========================================================
       DRAG-AND-DROP LAUNCHER
       ========================================================= */
    const DRAG_THRESHOLD = 5;
    const STORAGE_KEY    = 'unifiedChatLauncherPos';

    let dragState = null;

    function readStoredPos() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const p = JSON.parse(raw);
            if (typeof p?.right === 'number' && typeof p?.bottom === 'number') return p;
        } catch (_) { /* ignore */ }
        return null;
    }

    function storePos(right, bottom) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ right, bottom }));
        } catch (_) { /* ignore */ }
    }

    function clampToViewport(right, bottom, w, h) {
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        const margin  = 4;
        const minLeft = getMinLeftForWidget();

        const maxRightBySidebar = vw - w - minLeft;

        const maxRight  = Math.max(margin, Math.min(vw - w - margin, maxRightBySidebar));
        const maxBottom = Math.max(margin, vh - h - margin);

        return {
            right:  Math.min(Math.max(margin, right),  maxRight),
            bottom: Math.min(Math.max(margin, bottom), maxBottom),
        };
    }

    function applyPos(right, bottom) {
        launcher.style.right  = right + 'px';
        launcher.style.bottom = bottom + 'px';
    }

    function resetLauncherPos() {
        launcher.style.right  = '';
        launcher.style.bottom = '';
        try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
    }

    function getCurrentOffset() {
        const rect = launcher.getBoundingClientRect();
        const right  = window.innerWidth  - rect.right;
        const bottom = window.innerHeight - rect.bottom;
        return { right, bottom };
    }

    function reclampLauncherAndPanel() {
        if (launcher.style.right || launcher.style.bottom) {
            const w = launcher.offsetWidth  || 58;
            const h = launcher.offsetHeight || 58;
            const cur = getCurrentOffset();
            const { right, bottom } = clampToViewport(cur.right, cur.bottom, w, h);
            applyPos(right, bottom);
            storePos(right, bottom);
        }

        if (isPanelOpen()) {
            positionPanelNearLauncher();
        }
    }

    (function initLauncherPos() {
        const saved = readStoredPos();
        if (!saved) return;
        const w = launcher.offsetWidth || 58;
        const h = launcher.offsetHeight || 58;
        const { right, bottom } = clampToViewport(saved.right, saved.bottom, w, h);
        applyPos(right, bottom);
    })();

    window.addEventListener('resize', reclampLauncherAndPanel);

    let sidebarReclampTimer = null;
    window.addEventListener('sidebar-toggle', () => {
        reclampLauncherAndPanel();
        clearTimeout(sidebarReclampTimer);
        sidebarReclampTimer = setTimeout(reclampLauncherAndPanel, 350);
    });

    const sidebarEl = getSidebarEl();
    if (sidebarEl && window.ResizeObserver) {
        let sidebarRaf = null;
        new ResizeObserver(() => {
            if (sidebarRaf) return;
            sidebarRaf = requestAnimationFrame(() => {
                sidebarRaf = null;
                reclampLauncherAndPanel();
            });
        }).observe(sidebarEl);
    }

    launcher.addEventListener('pointerdown', (e) => {
        if (e.button !== undefined && e.button !== 0) return;
        if (getComputedStyle(launcher).display === 'none') return;

        const { right, bottom } = getCurrentOffset();

        dragState = {
            pointerId:   e.pointerId,
            startX:      e.clientX,
            startY:      e.clientY,
            startRight:  right,
            startBottom: bottom,
            moved:       false,
        };

        launcher.setPointerCapture?.(e.pointerId);
    });

    launcher.addEventListener('pointermove', (e) => {
        if (!dragState || e.pointerId !== dragState.pointerId) return;

        const dx = e.clientX - dragState.startX;
        const dy = e.clientY - dragState.startY;

        if (!dragState.moved) {
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) return;
            dragState.moved = true;
            launcher.classList.add('is-dragging');
            document.body.style.userSelect = 'none';
        }

        const nextRight  = dragState.startRight  - dx;
        const nextBottom = dragState.startBottom - dy;

        const w = launcher.offsetWidth  || 58;
        const h = launcher.offsetHeight || 58;
        const { right, bottom } = clampToViewport(nextRight, nextBottom, w, h);

        applyPos(right, bottom);
    });

    function endDrag(e) {
        if (!dragState || (e && e.pointerId !== dragState.pointerId)) return;

        const wasDragging = dragState.moved;
        const { right, bottom } = getCurrentOffset();

        launcher.releasePointerCapture?.(dragState.pointerId);
        launcher.classList.remove('is-dragging');
        document.body.style.userSelect = '';

        dragState = null;

        if (wasDragging) {
            storePos(right, bottom);

            if (isPanelOpen()) {
                positionPanelNearLauncher();
            }

            // ⭐ Suppress the synthetic click that follows a drag, and
            // self-clean if no click arrives.
            const swallow = (ev) => {
                ev.stopPropagation();
                ev.preventDefault();
                launcher.removeEventListener('click', swallow, true);
            };
            launcher.addEventListener('click', swallow, { capture: true });
            setTimeout(() => {
                launcher.removeEventListener('click', swallow, true);
            }, 250);
        }
    }

    launcher.addEventListener('pointerup', endDrag);
    launcher.addEventListener('pointercancel', endDrag);

    launcher.addEventListener('dblclick', (e) => {
        e.preventDefault();
        resetLauncherPos();
    });

    /* =========================================================
       UTILITIES
       ========================================================= */
    function dispatch(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* =========================================================
       BOOT
       ========================================================= */
    function boot() {
        // Force the initial closed state through the canonical helper
        // so display / pointer-events / aria-hidden / class all agree.
        hidePanel();

        launcher.style.display = 'flex';
        hideBadge();

        requestAnimationFrame(() => {
            root.classList.remove('unified-chat--booting');
            window.__unifiedChat.isReady = true;
            reclampLauncherAndPanel();
            console.log('[unified-chat] ready (role: ' + ROLE + ')');
        });
    }

    boot();
})();
</script>

{{-- =============================================================
     LIVE-CHAT BRIDGE (unchanged)
     ============================================================= --}}
<script id="unifiedLiveChatBridge">
(function () {
    'use strict';

    const panel = document.getElementById('unifiedChatPanel');

    if (!panel) return;

    window.__unifiedChat = window.__unifiedChat || {};

    window.addEventListener('live-chat:opened', () => {
        if (!window.__unifiedChat.isReady) return;
        window.__unifiedChat.isOpen = true;
        window.dispatchEvent(new CustomEvent('live-chat:visibility', {
            detail: { open: true }
        }));
    });

    window.addEventListener('live-chat:closed', () => {
        if (!window.__unifiedChat.isReady) return;
        window.__unifiedChat.isOpen = false;
        window.dispatchEvent(new CustomEvent('live-chat:visibility', {
            detail: { open: false }
        }));
    });

    window.addEventListener('live-chat:message-appended', (e) => {
        window.dispatchEvent(new CustomEvent('unified-chat:message-appended', {
            detail: { pane: 'support', message: e.detail?.message }
        }));
    });

    const innerBadge = document.getElementById('liveChatUnreadBadge');
    if (innerBadge && window.MutationObserver) {
        new MutationObserver(() => {
            window.dispatchEvent(new CustomEvent('live-chat:badge-changed', {
                detail: { count: parseInt(innerBadge.textContent || '0', 10) || 0 }
            }));
        }).observe(innerBadge, {
            childList: true,
            characterData: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    }

    console.log('[unified-chat] live-chat bridge ready');
})();
</script>