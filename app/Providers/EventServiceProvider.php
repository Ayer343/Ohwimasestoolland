<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// ── Existing user lifecycle ────────────────────────────────────────
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Events\UserDeleted;
use App\Listeners\LogUserActivity;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\CleanupUserData;

// ── Property family links — two-stage approval workflow ────────────
use App\Events\PropertyFamilyLinkProposed;
use App\Events\PropertyFamilyLinkAwaitingLandlordConfirmation;
use App\Events\PropertyFamilyLinkLandlordConfirmed;
use App\Events\PropertyFamilyLinkApproved;
use App\Events\PropertyFamilyLinkRejected;
use App\Events\PropertyFamilyLinkRevoked;

use App\Listeners\NotifyLandlordToConfirmFamilyLink;
use App\Listeners\NotifyAdminsOfFamilyLinkProposal;
use App\Listeners\NotifyLandlordOfFamilyLinkDecision;
use App\Listeners\LogFamilyLinkActivity;

// ── Live chat ──────────────────────────────────────────────────────
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
           PROPERTY FAMILY LINKS — TWO-STAGE APPROVAL
           ============================================================
           Landlords propose family members (spouse, son, daughter, etc.)
           to be linked to their properties. The flow has TWO gates:

             Gate 1 — Landlord's own confirmation
             Gate 2 — Admin review & approval

           An invitation is dispatched to the family member ONLY after
           both gates pass. This prevents accidental or coerced links
           and ensures the family member is never contacted until the
           account is truly ready.

           Lifecycle:
             proposed                → notify landlord to confirm
             awaitingLandlordConfirm → (no listener — informational)
             landlordConfirmed       → notify admins to review
             approved                → notify landlord + invite family member
             rejected                → notify landlord with reason
             revoked                 → notify linked user + landlord
           ============================================================ */

        /**
         * Fired when a landlord first submits a proposal.
         *
         * At this stage NO ONE is invited, and admins are NOT yet
         * notified — the landlord still needs to confirm their own
         * intent. Just writes an audit-trail entry.
         *
         * NotifyLandlordToConfirmFamilyLink is NOT attached here because
         * the landlord is the one who just submitted the form — they
         * know it needs confirmation. Only attach the audit logger.
         */
        PropertyFamilyLinkProposed::class => [
            LogFamilyLinkActivity::class,
        ],

        /**
         * Fired when the link sits in `pending_landlord_confirmation`.
         *
         * Sent only if the link was created via a bulk/admin path or a
         * background job that needs to prompt the landlord out-of-band.
         * Attaches the confirmation-prompt notification so the landlord
         * gets a nudge to return and confirm.
         */
        PropertyFamilyLinkAwaitingLandlordConfirmation::class => [
            NotifyLandlordToConfirmFamilyLink::class,
            LogFamilyLinkActivity::class,
        ],

        /**
         * Fired when the landlord confirms their own proposal.
         *
         * This is the trigger that moves the link into admin review and
         * NOTIFIES ADMINS. Previously this notification fired on the
         * initial proposal; it now fires here so admins never see
         * unconfirmed proposals.
         */
        PropertyFamilyLinkLandlordConfirmed::class => [
            NotifyAdminsOfFamilyLinkProposal::class,
            LogFamilyLinkActivity::class,
        ],

        /**
         * Fired when the admin approves the proposal.
         *
         * On approval the service:
         *   1. Creates the linked user as PENDING (if new)
         *   2. Sends the family member an invitation via UserInvitationService
         *   3. Fires this event
         *
         * Listeners here notify the landlord of the decision. The family
         * member has already been invited by the service before this
         * event dispatches — do not send a second invitation.
         */
        PropertyFamilyLinkApproved::class => [
            NotifyLandlordOfFamilyLinkDecision::class,
            LogFamilyLinkActivity::class,
        ],

        /**
         * Fired when the admin rejects the proposal.
         *
         * The family member is never contacted — no user account was
         * created and no invitation was sent.
         */
        PropertyFamilyLinkRejected::class => [
            NotifyLandlordOfFamilyLinkDecision::class,
            LogFamilyLinkActivity::class,
        ],

        /**
         * Fired when a previously-approved link is revoked by the
         * landlord or an admin.
         *
         * The linked user loses access immediately (authorization
         * traits query `status = 'approved'`). Notification listener
         * handles both landlord and linked-user branches internally.
         */
        PropertyFamilyLinkRevoked::class => [
            NotifyLandlordOfFamilyLinkDecision::class,
            LogFamilyLinkActivity::class,
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
         */
        MessageSent::class => [
            NotifyConversationParticipants::class,
        ],

        /**
         * Fired when a conversation's metadata changes (status, assignment,
         * last message). Currently broadcast-only — no listener yet.
         *
         * Future use:
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