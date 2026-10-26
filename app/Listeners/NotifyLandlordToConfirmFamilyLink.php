<?php
// app/Listeners/NotifyLandlordToConfirmFamilyLink.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkAwaitingLandlordConfirmation;
use App\Notifications\FamilyLinkConfirmPromptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyLandlordToConfirmFamilyLink implements ShouldQueue
{
    /**
     * Explicit queue name — keep all family-link notifications on the
     * same queue so a single worker can handle them all.
     *
     *   php artisan queue:work --queue=notifications,default
     */
    public string $queue = 'notifications';

    /**
     * Retry policy for transient failures (mail server down, etc.).
     */
    public int $tries = 3;

    public int $backoff = 30;

    public function handle(PropertyFamilyLinkAwaitingLandlordConfirmation $event): void
    {
        $link = $event->link;

        // -------------------------------------------------------------
        // Load the landlord once, with a null-safe guard.
        // `$link->landlord` triggers a lazy DB query; do it explicitly
        // so failures are logged with context.
        // -------------------------------------------------------------
        $landlord = $link->landlord;

        if (!$landlord) {
            Log::warning('No landlord to notify for confirmation prompt', [
                'link_id'     => $link->id,
                'landlord_id' => $link->landlord_id,
            ]);
            return;
        }

        // -------------------------------------------------------------
        // Only send the prompt if the link is actually awaiting the
        // landlord's confirmation. The event name implies this, but
        // re-checking makes the listener robust against future misuse
        // (e.g. if the event is dispatched from a new code path).
        // -------------------------------------------------------------
        if (!$link->isAwaitingLandlordConfirmation()) {
            Log::debug('Skipping landlord confirmation prompt — link not awaiting landlord', [
                'link_id' => $link->id,
                'status'  => $link->status,
            ]);
            return;
        }

        try {
            $landlord->notify(
                new FamilyLinkConfirmPromptNotification($link)
            );

            Log::info('Landlord confirmation prompt dispatched', [
                'link_id'     => $link->id,
                'landlord_id' => $landlord->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send landlord confirmation prompt', [
                'link_id'     => $link->id,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            // Rethrow so the queue's retry policy kicks in for transient
            // failures (network errors, SMTP timeouts, etc.).
            throw $e;
        }
    }

    /**
     * Called when the listener has exhausted its retries.
     */
    public function failed(
        PropertyFamilyLinkAwaitingLandlordConfirmation $event,
        \Throwable $exception
    ): void {
        Log::error('NotifyLandlordToConfirmFamilyLink failed permanently', [
            'link_id'     => $event->link->id ?? null,
            'landlord_id' => $event->link->landlord_id ?? null,
            'error'       => $exception->getMessage(),
        ]);
    }
}