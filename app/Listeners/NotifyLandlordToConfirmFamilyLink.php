<?php
// app/Listeners/NotifyLandlordToConfirmFamilyLink.php

namespace App\Listeners;

use App\Events\PropertyFamilyLinkAwaitingLandlordConfirmation;
use App\Notifications\FamilyLinkConfirmPromptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyLandlordToConfirmFamilyLink implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PropertyFamilyLinkAwaitingLandlordConfirmation $event): void
    {
        $link = $event->link;

        if (!$link->landlord) {
            Log::warning('No landlord to notify for confirmation prompt', [
                'link_id' => $link->id,
            ]);
            return;
        }

        $link->landlord->notify(
            new FamilyLinkConfirmPromptNotification($link)
        );

        Log::info('Landlord confirmation prompt dispatched', [
            'link_id'     => $link->id,
            'landlord_id' => $link->landlord_id,
        ]);
    }
}