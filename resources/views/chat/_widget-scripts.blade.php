{{-- resources/views/chat/_widget-scripts.blade.php --}}

<script id="chatWidgetScripts">
(function () {
    'use strict';

    // Prevent double-initialization if the partial is included twice
    if (window.__chatWidgetInitialized) {
        console.log('[chat-widget] already initialized — skipping');
        return;
    }
    window.__chatWidgetInitialized = true;

    const widget = document.getElementById('chatWidget');
    if (!widget) {
        console.warn('[chat-widget] #chatWidget not found');
        return;
    }

    /* =========================================================
       CONFIG
       ========================================================= */
    const sendUrl     = widget.dataset.sendUrl;
    const messagesUrl = widget.dataset.messagesUrl;
    const clearUrl    = widget.dataset.clearUrl;
    const topicsUrl   = widget.dataset.topicsUrl;
    const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content
                       || document.querySelector('input[name="_token"]')?.value;

    /* =========================================================
       ELEMENTS
       ========================================================= */
    const stream   = document.getElementById('chatStream');
    const form     = document.getElementById('chatForm');
    const input    = document.getElementById('chatInput');
    const sendBtn  = document.getElementById('chatSendBtn');
    const typing   = document.getElementById('chatTyping');
    const topicsEl = document.getElementById('chatTopics');
    const clearBtn = document.getElementById('chatClearBtn');
    const closeBtn = document.getElementById('chatCloseBtn');
    const emptyEl  = document.getElementById('chatEmptyState');

    if (!stream || !form || !input) {
        console.warn('[chat-widget] missing required elements');
        return;
    }

    /* =========================================================
       HELPERS
       ========================================================= */
    function scrollBottom() {
        stream.scrollTop = stream.scrollHeight;
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Append a bubble to the stream.
     * Also dispatches an event so the unified wrapper can auto-scroll.
     */
    function appendMessage(text, who) {
        if (emptyEl && emptyEl.parentNode) emptyEl.remove();

        const wrap = document.createElement('div');
        wrap.className = 'chat-widget__msg chat-widget__msg--' + who;

        const bubble = document.createElement('div');
        bubble.className = 'chat-widget__bubble';
        bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');

        wrap.appendChild(bubble);
        stream.appendChild(wrap);
        scrollBottom();

        // ⭐ Notify the unified wrapper
        window.dispatchEvent(new CustomEvent('unified-chat:message-appended', {
            detail: { pane: 'assistant' },
        }));
    }

    function setTyping(on) {
        typing?.classList.toggle('hidden', !on);
        if (sendBtn) sendBtn.disabled = on;
        if (input)   input.disabled   = on;
    }

    function resetStream() {
        stream.innerHTML = `
            <div class="chat-widget__empty" id="chatEmptyState">
                <i class="fas fa-comments"></i>
                <p>Ask me anything about using the app.</p>
            </div>`;
    }

    /* =========================================================
       SEND MESSAGE
       ========================================================= */
    async function sendMessage(text) {
        text = (text || '').trim();
        if (!text) return;

        appendMessage(text, 'user');
        input.value = '';
        setTyping(true);

        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: text }),
            });

            if (!res.ok) {
                console.error('[chat-widget] send HTTP', res.status);
                appendMessage('Server error. Please try again.', 'bot');
                return;
            }

            const data = await res.json();
            const reply = data.response || "Sorry, I didn't get that.";
            appendMessage(reply, 'bot');
        } catch (err) {
            console.error('[chat-widget] send threw', err);
            appendMessage('Network error. Please try again.', 'bot');
        } finally {
            setTyping(false);
            input?.focus();
        }
    }

    /* =========================================================
       EVENT WIRING
       ========================================================= */
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    // Quick topics
    topicsEl?.addEventListener('click', function (e) {
        const btn = e.target.closest('.chat-widget__topic');
        if (!btn) return;
        sendMessage(btn.dataset.topic);
    });

    // Clear history
    clearBtn?.addEventListener('click', async function () {
        if (!confirm('Clear all chat history?')) return;
        try {
            const res = await fetch(clearUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            const data = await res.json();
            if (data.success) {
                resetStream();
            }
        } catch (err) {
            console.error('[chat-widget] clear threw', err);
            alert('Failed to clear history.');
        }
    });

    // Close button — hides the widget itself. In unified mode we
    // ignore this because the unified wrapper handles closing.
    closeBtn?.addEventListener('click', function () {
        const unifiedMode = document.getElementById('unifiedChatPanel');
        if (unifiedMode) {
            // Unified wrapper dispatches the close — do nothing here
            return;
        }
        widget.style.display = 'none';
    });

    /* =========================================================
       UNIFIED WIDGET INTEGRATION
       ========================================================= */
    // When the unified panel opens or switches to our tab,
    // scroll the stream to the bottom and focus the input.
    window.addEventListener('unified-chat:opened', function (e) {
        if (e.detail?.tab && e.detail.tab !== 'assistant') return;
        requestAnimationFrame(() => {
            scrollBottom();
            input?.focus();
        });
    });

    window.addEventListener('unified-chat:tab-changed', function (e) {
        if (e.detail?.tab !== 'assistant') return;
        requestAnimationFrame(() => {
            scrollBottom();
            input?.focus();
        });
    });

    /* =========================================================
       PUBLIC API (optional)
       ========================================================= */
    window.__chatWidget = {
        sendMessage,
        resetStream,
        scrollBottom,
        version: '1.0.0',
    };

    /* =========================================================
       INIT
       ========================================================= */
    scrollBottom();
    input?.focus();

    console.log('[chat-widget] ready');
})();
</script>