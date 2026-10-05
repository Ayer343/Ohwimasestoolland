<?php
// app/Notifications/FamilyLinkConfirmPromptNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FamilyLinkConfirmPromptNotification extends Notification
{
    use Queueable;

    public function __construct(public PropertyFamilyLink $link) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->link->property->property_name ?? 'your property';

        return (new MailMessage)
            ->subject('Confirm your family-link proposal')
            ->line("You proposed linking {$this->link->proposed_name} ({$this->link->relationship_label}) to {$property}.")
            ->line('Please confirm this proposal to send it to administrators for review.')
            ->action('Confirm Proposal', route('landlord.family-links.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'family_link_awaiting_landlord_confirmation',
            'link_id'     => $this->link->id,
            'property_id' => $this->link->property_id,
            'member'      => $this->link->proposed_name,
        ];
    }
}