<?php
// app/Listeners/NotifyLandlordOfFamilyLinkDecision.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkApproved;
use App\Events\PropertyFamilyLinkRejected;
use App\Events\PropertyFamilyLinkRevoked;
use App\Notifications\FamilyLinkDecisionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyLandlordOfFamilyLinkDecision implements ShouldQueue
{
    /**
     * Explicit queue name — keeps all family-link notifications on
     * one queue. Run:  php artisan queue:work --queue=notifications,default
     */
    public string $queue = 'notifications';

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Handle any of the three decision events.
     *
     * IMPORTANT: the union type-hint lets Laravel's listener
     * auto-discovery bind this listener to all three events. Do NOT
     * remove it — without the type-hint, this listener never fires
     * unless it is registered explicitly in EventServiceProvider.
     */
    public function handle(
        PropertyFamilyLinkApproved|PropertyFamilyLinkRejected|PropertyFamilyLinkRevoked $event
    ): void {
        $link = $event->link;

        // Eager-load the two users this listener may notify.
        // `refresh()` re-fetches from DB with the relations in one go,
        // replacing any lazy loads in subsequent accesses.
        $link->loadMissing(['landlord', 'linkedUser']);

        $decision = $this->decisionType($event);

        // -------------------------------------------------------------
        // 1. Notify the landlord of the decision
        //    (covers approved, rejected, and revoked)
        // -------------------------------------------------------------
        if ($link->landlord) {
            try {
                $link->landlord->notify(
                    new FamilyLinkDecisionNotification($link, $decision)
                );

                Log::info('Landlord notified of family link decision', [
                    'link_id'     => $link->id,
                    'landlord_id' => $link->landlord_id,
                    'decision'    => $decision,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to notify landlord of family link decision', [
                    'link_id'     => $link->id,
                    'landlord_id' => $link->landlord_id,
                    'decision'    => $decision,
                    'error'       => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        } else {
            Log::warning('No landlord to notify for family link decision', [
                'link_id'  => $link->id,
                'decision' => $decision,
            ]);
        }

        // -------------------------------------------------------------
        // 2. On approval, welcome the linked user
        // -------------------------------------------------------------
        if ($event instanceof PropertyFamilyLinkApproved) {
            if ($link->linkedUser) {
                try {
                    $link->linkedUser->notify(
                        new FamilyLinkDecisionNotification($link, 'linked_welcome')
                    );

                    Log::info('Linked user welcomed after approval', [
                        'link_id'        => $link->id,
                        'linked_user_id' => $link->linked_user_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to send welcome notification to linked user', [
                        'link_id'        => $link->id,
                        'linked_user_id' => $link->linked_user_id,
                        'error'          => $e->getMessage(),
                    ]);
                    // Do not rethrow — the landlord notification already
                    // succeeded, and a linked-user welcome failure should
                    // not roll back the whole job (which would re-notify
                    // the landlord on retry).
                }
            } else {
                Log::debug('Approval event fired but no linked user attached', [
                    'link_id' => $link->id,
                ]);
            }
        }

        // -------------------------------------------------------------
        // 3. On revoke, tell the linked user they lost access
        // -------------------------------------------------------------
        if ($event instanceof PropertyFamilyLinkRevoked) {
            if ($link->linkedUser) {
                try {
                    $link->linkedUser->notify(
                        new FamilyLinkDecisionNotification($link, 'revoked_for_linked')
                    );

                    Log::info('Linked user notified of revocation', [
                        'link_id'        => $link->id,
                        'linked_user_id' => $link->linked_user_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to notify linked user of revocation', [
                        'link_id'        => $link->id,
                        'linked_user_id' => $link->linked_user_id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Family link decision notifications dispatched', [
            'link_id'  => $link->id,
            'decision' => $decision,
        ]);
    }

    /**
     * Called when the listener has exhausted its retries.
     */
    public function failed(
        PropertyFamilyLinkApproved|PropertyFamilyLinkRejected|PropertyFamilyLinkRevoked $event,
        \Throwable $exception
    ): void {
        Log::error('NotifyLandlordOfFamilyLinkDecision failed permanently', [
            'link_id'  => $event->link->id ?? null,
            'decision' => $this->decisionType($event),
            'error'    => $exception->getMessage(),
        ]);
    }

    /**
     * Map event class → semantic decision string used by the
     * notification to pick copy / template / channels.
     */
    protected function decisionType($event): string
    {
        return match (true) {
            $event instanceof PropertyFamilyLinkApproved => 'approved',
            $event instanceof PropertyFamilyLinkRejected => 'rejected',
            $event instanceof PropertyFamilyLinkRevoked  => 'revoked',
            default                                       => 'unknown',
        };
    }
}