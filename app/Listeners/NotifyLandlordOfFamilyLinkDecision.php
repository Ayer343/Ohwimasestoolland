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
    public string $queue = 'notifications';

    /**
     * Handle any of the three decision events.
     */
    public function handle($event): void
    {
        $link = $event->link;

        // ── 1. Notify the landlord of the decision ──
        if ($link->landlord) {
            $link->landlord->notify(
                new FamilyLinkDecisionNotification($link, $this->decisionType($event))
            );
        }

        // ── 2. On approval, welcome the linked user ──
        if ($event instanceof PropertyFamilyLinkApproved) {
            $linkedUser = $link->linkedUser;

            if ($linkedUser) {
                $linkedUser->notify(
                    new FamilyLinkDecisionNotification($link, 'linked_welcome')
                );
            }
        }

        // ── 3. On revoke, tell the linked user they lost access ──
        if ($event instanceof PropertyFamilyLinkRevoked && $link->linkedUser) {
            $link->linkedUser->notify(
                new FamilyLinkDecisionNotification($link, 'revoked_for_linked')
            );
        }

        Log::info('Family link decision notifications dispatched', [
            'link_id'   => $link->id,
            'decision'  => $this->decisionType($event),
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