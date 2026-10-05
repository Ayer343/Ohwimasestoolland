<?php
// app/Listeners/NotifyAdminsOfFamilyLinkProposal.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkLandlordConfirmed;
use App\Models\User;
use App\Notifications\FamilyLinkProposedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAdminsOfFamilyLinkProposal implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle($event): void
    {
        $link = $event->link;

        // Safeguard: this listener should only fire on confirmation,
        // not on initial proposal.
        if (!$link->isAwaitingAdminReview()
            && $link->status !== \App\Models\PropertyFamilyLink::STATUS_PENDING) {
            Log::debug('NotifyAdminsOfFamilyLinkProposal skipped — link not yet admin-reviewable', [
                'link_id' => $link->id,
                'status'  => $link->status,
            ]);
            return;
        }

        $admins = User::whereIn('type', [
                User::TYPE_ADMIN,
                User::TYPE_SUPER_ADMIN,
            ])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('No active admins to notify of family link proposal', [
                'link_id' => $link->id,
            ]);
            return;
        }

        $channels = config('property_family_links.notify_channels', ['email']);

        $dispatched = 0;

        foreach ($admins as $admin) {
            try {
                $admin->notify(
                    new FamilyLinkProposedNotification($link, $event->landlord, $channels)
                );
                $dispatched++;
            } catch (\Throwable $e) {
                Log::error('Failed to notify admin about family link', [
                    'link_id'  => $link->id,
                    'admin_id' => $admin->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        Log::info('Family link proposal notifications dispatched', [
            'link_id'         => $link->id,
            'admin_count'     => $admins->count(),
            'dispatched'      => $dispatched,
            'channels'        => $channels,
        ]);
    }
}