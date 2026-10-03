{{-- resources/views/live-chat/_widget-scripts.blade.php --}}

<script id="liveChatWidgetScripts">
(function () {
    'use strict';

    const launcher = document.getElementById('liveChatLauncher');
    const panel    = document.getElementById('liveChatPanel');
    const closeBtn = document.getElementById('liveChatClose');
    const expandBtn= document.getElementById('liveChatExpand');
    const stream   = document.getElementById('liveChatStream');
    const form     = document.getElementById('liveChatComposer');
    const input    = document.getElementById('liveChatInput');
    const sendBtn  = document.getElementById('liveChatSendBtn');
    const status   = document.getElementById('liveChatStatus');
    const badge    = document.getElementById('liveChatUnreadBadge');
    const typing   = document.getElementById('liveChatTyping');
    const emptyEl  = document.getElementById('liveChatEmpty');

    if (!launcher || !panel) {
        console.warn('[live-chat] widget elements missing from DOM');
        return;
    }

    const ME = parseInt(launcher.dataset.userId, 10);
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
              || document.querySelector('#liveChatComposer input[name="_token"]')?.value;

    const ROUTES = {
        start:    @json(route('live-chat.start')),
        unread:   @json(route('live-chat.unread.count')),
        index:    @json(route('live-chat.index')),
        show:     (id) => @json(url('/live-chat')) + '/' + id,
        messages: (id) => @json(url('/live-chat')) + '/' + id + '/messages',
    };

    console.log('[live-chat] widget booting', {
        me: ME,
        hasCsrf: !!CSRF,
        startUrl: ROUTES.start,
    });

    let conversationId = sessionStorage.getItem('lc.conversationId') || null;
    let lastMessageId  = 0;
    let pollTimer      = null;
    let unreadTimer    = null;
    let polling        = false;
    let isOpen         = false;
    let bootstrapped   = false;

    /* =========================================================
       STATUS HELPERS
       ========================================================= */
    function setStatus(text) {
        if (status) status.textContent = text;
    }

    /* =========================================================
       OPEN / CLOSE
       ========================================================= */
    function open() {
        isOpen = true;
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        launcher.style.display = 'none';
        hideBadge();

        // Always try to bootstrap on open — this also recovers from a
        // stale conversationId in sessionStorage.
        ensureConversation()
            .then((id) => {
                if (!id) return;
                return loadMessages(true);
            })
            .then(() => {
                startPolling();
            })
            .catch((err) => {
                console.error('[live-chat] open() chain failed', err);
            });

        setTimeout(() => input?.focus(), 250);
    }

    function close() {
        isOpen = false;
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        launcher.style.display = 'flex';
        stopPolling();
    }

    launcher.addEventListener('click', open);
    launcher.addEventListener('touchend', (e) => { e.preventDefault(); open(); });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
        closeBtn.addEventListener('touchend', (e) => { e.preventDefault(); close(); });
    }

    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            if (conversationId) {
                window.location.href = ROUTES.show(conversationId);
            } else {
                window.location.href = ROUTES.index;
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) close();
    });

    /* =========================================================
       CONVERSATION BOOTSTRAP
       ========================================================= */
    async function ensureConversation() {
        if (conversationId && bootstrapped) {
            setStatus('Connected');
            return conversationId;
        }

        setStatus('Connecting…');

        if (!CSRF) {
            console.error('[live-chat] CSRF token missing');
            setStatus('Connection failed (no CSRF)');
            return null;
        }

        try {
            const res = await fetch(ROUTES.start, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ subject: null }),
            });

            if (!res.ok) {
                console.error('[live-chat] start HTTP', res.status, res.statusText);
                setStatus('Connection failed (' + res.status + ')');
                return null;
            }

            const ct = res.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                const raw = await res.text();
                console.error('[live-chat] start non-JSON response', raw.slice(0, 200));
                setStatus('Connection failed (bad response)');
                return null;
            }

            const data = await res.json();

            if (!data.success || !data.conversation || !data.conversation.id) {
                console.error('[live-chat] start unexpected shape', data);
                setStatus('Connection failed (bad data)');
                return null;
            }

            conversationId = data.conversation.id;
            bootstrapped   = true;
            sessionStorage.setItem('lc.conversationId', conversationId);
            setStatus('Connected');
            return conversationId;

        } catch (err) {
            console.error('[live-chat] start threw', err);
            setStatus('Connection failed (network)');
            return null;
        }
    }

    /* =========================================================
       MESSAGES — load, render, send
       ========================================================= */
    async function loadMessages(initial = false) {
        if (!conversationId || polling) return;
        polling = true;

        try {
            const url = ROUTES.messages(conversationId) + '?after=' + lastMessageId;
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json' },
            });

            if (!res.ok) {
                console.warn('[live-chat] messages HTTP', res.status);
                return;
            }

            const data = await res.json();

            if (data.success && Array.isArray(data.messages)) {
                if (initial) {
                    stream.innerHTML = '';
                    lastMessageId = 0;
                }
                data.messages.forEach(renderMessage);
                if (data.messages.length) {
                    lastMessageId = Math.max(
                        lastMessageId,
                        ...data.messages.map((m) => m.id)
                    );
                }
                scrollBottom();
                setStatus('Connected');
            }
        } catch (err) {
            console.error('[live-chat] loadMessages failed', err);
        } finally {
            polling = false;
        }
    }

    function renderMessage(m) {
        if (emptyEl && emptyEl.parentNode) emptyEl.remove();

        // Skip if already rendered (idempotent)
        if (stream.querySelector('[data-mid="' + m.id + '"]')) return;

        const mine = m.user_id === ME;
        const wrap = document.createElement('div');
        wrap.className = 'lc-msg ' + (mine ? 'mine' : 'theirs');
        wrap.dataset.mid = m.id;

        const body = document.createElement('div');
        body.textContent = m.body || '';

        const meta = document.createElement('div');
        meta.className = 'lc-msg__meta';
        meta.textContent = formatTime(m.created_at) + (mine ? '' : ' · ' + (m.user_name || 'Support'));

        wrap.appendChild(body);
        wrap.appendChild(meta);
        stream.appendChild(wrap);
    }

    function formatTime(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch { return ''; }
    }

    function scrollBottom() {
        stream.scrollTop = stream.scrollHeight;
    }

    /* =========================================================
       SEND
       ========================================================= */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;

        if (!conversationId) {
            await ensureConversation();
            if (!conversationId) return;
        }

        input.value = '';
        sendBtn.disabled = true;

        try {
            const res = await fetch(ROUTES.messages(conversationId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ body }),
            });

            if (!res.ok) {
                console.error('[live-chat] send HTTP', res.status);
                return;
            }

            const data = await res.json();
            if (data.success && data.message) {
                renderMessage(data.message);
                lastMessageId = Math.max(lastMessageId, data.message.id);
                scrollBottom();
            }
        } catch (err) {
            console.error('[live-chat] send failed', err);
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    });

    /* =========================================================
       POLLING
       ========================================================= */
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(() => {
            if (!document.hidden && isOpen) loadMessages(false);
        }, 4000);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function startUnreadPolling() {
        if (unreadTimer) return;
        const tick = async () => {
            try {
                const res = await fetch(ROUTES.unread, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (data.success) showBadge(data.count);
            } catch {}
        };
        tick();
        unreadTimer = setInterval(tick, 20000);
    }

    function showBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    function hideBadge() { badge?.classList.add('hidden'); }

    /* =========================================================
       INIT
       ========================================================= */
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && isOpen) loadMessages(false);
    });

    startUnreadPolling();

    console.log('[live-chat] widget ready');
})();
</script>