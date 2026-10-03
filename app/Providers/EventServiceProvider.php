<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// Existing events / listeners
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Events\UserDeleted;
use App\Listeners\LogUserActivity;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\CleanupUserData;

// Live chat events / listeners
use App\Events\LiveChat\MessageSent;
use App\Events\LiveChat\ConversationUpdated;
use App\Events\LiveChat\UserTyping;
use App\Listeners\NotifyConversationParticipants;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event → listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [

        /* ============================================================
           USER LIFECYCLE
           ============================================================ */

        UserCreated::class => [
            LogUserActivity::class,
            SendWelcomeNotification::class,
        ],

        UserUpdated::class => [
            LogUserActivity::class,
        ],

        UserDeleted::class => [
            LogUserActivity::class,
            CleanupUserData::class,
        ],

        /* ============================================================
           LIVE CHAT — human-to-human support & escalation
           ============================================================ */

        /**
         * Fired when a new message is sent inside a conversation.
         *
         * The `MessageSent` event also implements ShouldBroadcast so it
         * goes out over WebSockets/Pusher/Reverb in addition to triggering
         * this server-side listener.
         *
         * NotifyConversationParticipants:
         *   - Queues a `NewLiveChatMessage` notification (DB + optional mail)
         *   for every participant except the sender.
         */
        MessageSent::class => [
            NotifyConversationParticipants::class,
        ],

        /**
         * Fired when a conversation's metadata changes (status, assignment,
         * last message). Currently used only for broadcasting — no listener
         * needed yet. Left here as a registration point for future use:
         *
         *   - Auto-escalate to developer if a support ticket stays
         *     unassigned for N minutes.
         *   - Send a push notification to the assigned agent.
         *   - Update a dashboard widget with live counters.
         */
        ConversationUpdated::class => [
            // \App\Listeners\EscalateStaleConversations::class,
            // \App\Listeners\PingAssignedAgent::class,
        ],

        /**
         * Fired when someone is typing. Broadcast-only for now — no
         * server-side handling required.
         */
        UserTyping::class => [
            // no listeners — broadcast only
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * We register manually here, so auto-discovery is disabled to prevent
     * double-firing. If you later switch to auto-discovery, set this to
     * `true` and remove the entries above.
     */
    protected $shouldDiscoverEvents = false;

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        //
    }
}