{{--
    Universal Chat Widget
    ---------------------
    Renders inside ANY layout (app, dev, contract, field, landlord, secu, san, tenant).
    Uses only CSS variables so it inherits each layout's theme.
--}}

<div class="chat-widget" id="chatWidget"
     data-send-url="{{ route('chat.send') }}"
     data-messages-url="{{ route('chat.messages') }}"
     data-clear-url="{{ route('chat.clear') }}"
     data-topics-url="{{ route('chat.topics') }}"
     data-role-name="{{ $userRoleName ?? 'User' }}"
     data-role-id="{{ $userRoleId ?? '' }}">

    {{-- Header --}}
    <div class="chat-widget__header">
        <div class="chat-widget__title">
            <i class="fas fa-robot"></i>
            <div>
                <div class="chat-widget__title-main">Assistant</div>
                <div class="chat-widget__title-sub">
                    Helping you as <strong>{{ $userRoleName ?? 'User' }}</strong>
                </div>
            </div>
        </div>
        <div class="chat-widget__actions">
            <button type="button" id="chatClearBtn" class="chat-widget__icon-btn" title="Clear history">
                <i class="fas fa-trash-alt"></i>
            </button>
            <button type="button" id="chatCloseBtn" class="chat-widget__icon-btn" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    {{-- Quick topics --}}
    <div class="chat-widget__topics" id="chatTopics">
        @foreach($quickHelpTopics ?? [] as $topic)
            <button type="button" class="chat-widget__topic" data-topic="{{ $topic }}">
                {{ $topic }}
            </button>
        @endforeach
    </div>

    {{-- Message stream --}}
    <div class="chat-widget__stream" id="chatStream">
        @forelse($recentMessages ?? [] as $msg)
            <div class="chat-widget__msg chat-widget__msg--user">
                <div class="chat-widget__bubble">{{ $msg->message }}</div>
            </div>
            @if($msg->response)
                <div class="chat-widget__msg chat-widget__msg--bot">
                    <div class="chat-widget__bubble">{!! nl2br(e($msg->response)) !!}</div>
                </div>
            @endif
        @empty
            <div class="chat-widget__empty" id="chatEmptyState">
                <i class="fas fa-comments"></i>
                <p>Ask me anything about using the app.</p>
            </div>
        @endforelse
    </div>

    {{-- Typing indicator --}}
    <div class="chat-widget__typing hidden" id="chatTyping">
        <span></span><span></span><span></span>
    </div>

    {{-- Composer --}}
    <form class="chat-widget__composer" id="chatForm" autocomplete="off">
        @csrf
        <input type="text"
               id="chatInput"
               name="message"
               maxlength="1000"
               placeholder="Type your question…"
               required>
        <button type="submit" id="chatSendBtn" title="Send">
            <i class="fas fa-paper-plane"></i>
        </button>
    </form>
</div>

@include('chat._widget-styles')
@include('chat._widget-scripts')