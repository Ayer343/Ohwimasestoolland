<?php
// app/Listeners/LogFamilyLinkActivity.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkProposed;
use App\Events\PropertyFamilyLinkApproved;
use App\Events\PropertyFamilyLinkRejected;
use App\Events\PropertyFamilyLinkRevoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogFamilyLinkActivity implements ShouldQueue
{
    public string $queue = 'audit';

    public function handle($event): void
    {
        $link = $event->link;

        [$action, $actor, $description] = $this->describe($event, $link);

        // ── 1. Application log ──
        Log::channel('audit')->info("Family link {$action}", [
            'link_id'      => $link->id,
            'property_id'  => $link->property_id,
            'landlord_id'  => $link->landlord_id,
            'linked_user'  => $link->linked_user_id,
            'status'       => $link->status,
            'actor_id'     => $actor?->id,
            'actor_name'   => $actor?->name,
        ]);

        // ── 2. Property activity log (if the model supports it) ──
        if ($link->property && method_exists($link->property, 'logActivity')) {
            $link->property->logActivity(
                "family_link_{$action}",
                $description
            );
        }
    }

    /**
     * @return array{0:string,1:?\App\Models\User,2:string}
     */
    protected function describe($event, $link): array
    {
        if ($event instanceof PropertyFamilyLinkProposed) {
            return [
                'proposed',
                $event->landlord,
                sprintf(
                    'Family link proposed by %s | Member: %s (%s)',
                    $event->landlord->name,
                    $link->proposed_name,
                    $link->relationship_label
                ),
            ];
        }

        if ($event instanceof PropertyFamilyLinkApproved) {
            return [
                'approved',
                $event->admin ?? null,
                sprintf(
                    'Family link approved | Member: %s | Linked user: %s',
                    $link->proposed_name,
                    $link->linked_user_id ? "User #{$link->linked_user_id}" : 'N/A'
                ),
            ];
        }

        if ($event instanceof PropertyFamilyLinkRejected) {
            return [
                'rejected',
                $event->admin ?? null,
                sprintf(
                    'Family link rejected | Member: %s | Reason: %s',
                    $link->proposed_name,
                    $event->reason ?? 'Not specified'
                ),
            ];
        }

        if ($event instanceof PropertyFamilyLinkRevoked) {
            return [
                'revoked',
                $event->actor ?? null,
                sprintf(
                    'Family link revoked | Member: %s | Reason: %s',
                    $link->display_name,
                    $event->reason ?? 'Not specified'
                ),
            ];
        }

        return ['unknown', null, 'Family link event'];
    }
}