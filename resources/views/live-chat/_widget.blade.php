{{-- resources/views/live-chat/_widget.blade.php --}}
{{--
    Floating support chat widget.
    Renders inside any layout via:
        @include('live-chat._widget')

    Requires (must be authenticated):
        - styles partial: chat._widget-styles is NOT this — this is the
          live-chat one:  chat._widget-styles is for the AI bot widget.
          For live chat, the styles are in live-chat._widget-styles.
--}}

@auth
@php
    $__lcUser = auth()->user();
    $__lcRoleName = $__lcUser?->type_name ?? 'User';
@endphp

<button type="button"
        id="liveChatLauncher"
        class="lc-launcher"
        title="Chat with support"
        aria-label="Open support chat"
        data-user-id="{{ $__lcUser->id }}"
        data-user-name="{{ $__lcUser->name }}"
        data-role-name="{{ $__lcRoleName }}">
    <i class="fas fa-headset"></i>
    <span id="liveChatUnreadBadge" class="lc-badge hidden">0</span>
</button>

<div id="liveChatPanel"
     class="lc-panel"
     role="dialog"
     aria-label="Support chat"
     aria-hidden="true">

    <div class="lc-panel__header">
        <div class="lc-panel__header-info">
            <div class="lc-panel__title">
                <i class="fas fa-headset"></i>
                <span>Support Chat</span>
            </div>
            <div class="lc-panel__sub" id="liveChatStatus">Connecting…</div>
        </div>
        <div class="lc-panel__header-actions">
            <button type="button" id="liveChatExpand" class="lc-icon-btn" title="Open full view">
                <i class="fas fa-expand-alt"></i>
            </button>
            <button type="button" id="liveChatClose" class="lc-icon-btn" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="lc-panel__body">
        <div class="lc-stream" id="liveChatStream">
            <div class="lc-empty" id="liveChatEmpty">
                <i class="fas fa-comments"></i>
                <p>No messages yet. Say hi to get started!</p>
            </div>
        </div>

        <div class="lc-typing hidden" id="liveChatTyping">
            <span class="lc-typing__dot"></span>
            <span class="lc-typing__dot"></span>
            <span class="lc-typing__dot"></span>
            <span class="lc-typing__label">Someone is typing…</span>
        </div>

        <form class="lc-composer" id="liveChatComposer" autocomplete="off">
            @csrf
            <input type="text"
                   id="liveChatInput"
                   name="body"
                   maxlength="5000"
                   placeholder="Type your message…"
                   required>
            <button type="submit" id="liveChatSendBtn" title="Send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

{{-- Inline styles + script — MUST be @include, not @push --}}
@include('live-chat._widget-styles')
@include('live-chat._widget-scripts')
@endauth