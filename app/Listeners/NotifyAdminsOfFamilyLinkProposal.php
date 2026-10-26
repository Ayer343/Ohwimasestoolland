<?php
// app/Listeners/NotifyAdminsOfFamilyLinkProposal.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkLandlordConfirmed;
use App\Models\PropertyFamilyLink;
use App\Models\User;
use App\Notifications\FamilyLinkProposedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAdminsOfFamilyLinkProposal implements ShouldQueue
{
    /**
     * Explicit queue name so the worker can be targeted.
     * Make sure you run:  php artisan queue:work --queue=notifications
     */
    public string $queue = 'notifications';

    /**
     * Retry policy for transient failures (mail server hiccups, etc.).
     */
    public int $tries = 3;

    public int $backoff = 30;

    /**
     * The `handle` method's first parameter MUST be type-hinted with the
     * event class. Laravel's auto-discovery uses this hint to bind the
     * listener to the event. Without it, the listener never fires unless
     * explicitly registered in EventServiceProvider.
     */
    public function handle(PropertyFamilyLinkLandlordConfirmed $event): void
    {
        $link     = $event->link;
        $landlord = $event->landlord;

        // -------------------------------------------------------------
        // Guard: only notify when the link is actually awaiting admin
        // review. This protects against a future refactor where the
        // event gets fired from a different code path.
        // -------------------------------------------------------------
        if (!$link->isAwaitingAdminReview()
            && $link->status !== PropertyFamilyLink::STATUS_PENDING) {
            Log::debug('NotifyAdminsOfFamilyLinkProposal skipped — link not admin-reviewable', [
                'link_id' => $link->id,
                'status'  => $link->status,
            ]);
            return;
        }

        // -------------------------------------------------------------
        // Find active admins and super-admins.
        // -------------------------------------------------------------
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

        // -------------------------------------------------------------
        // Channels: always include 'database' so the admin sees this in
        // their in-app notifications, plus whatever config allows.
        //
        // If config returns ['email'], the final list is:
        //     ['database', 'email']
        //
        // If you want to completely disable email, set the config to [].
        // The database channel is always kept.
        // -------------------------------------------------------------
        $configChannels = (array) config('property_family_links.notify_channels', ['email']);
        $channels       = array_values(array_unique(array_merge(['database'], $configChannels)));

        $dispatched = 0;
        $failed     = 0;

        foreach ($admins as $admin) {
            try {
                $admin->notify(
                    new FamilyLinkProposedNotification($link, $landlord, $channels)
                );
                $dispatched++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Failed to notify admin about family link', [
                    'link_id'  => $link->id,
                    'admin_id' => $admin->id,
                    'error'    => $e->getMessage(),
                    'trace'    => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Family link proposal notifications dispatched', [
            'link_id'     => $link->id,
            'admin_count' => $admins->count(),
            'dispatched'  => $dispatched,
            'failed'      => $failed,
            'channels'    => $channels,
        ]);
    }

    /**
     * Called when the listener has exhausted its retries.
     * Useful for alerting / dead-letter handling.
     */
    public function failed(PropertyFamilyLinkLandlordConfirmed $event, \Throwable $exception): void
    {
        Log::error('NotifyAdminsOfFamilyLinkProposal failed permanently', [
            'link_id' => $event->link->id ?? null,
            'error'   => $exception->getMessage(),
        ]);
    }
}