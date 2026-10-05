<?php
// app/Notifications/FamilyLinkDecisionNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FamilyLinkDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PropertyFamilyLink $link,
        /** @var 'approved'|'rejected'|'revoked'|'linked_welcome'|'revoked_for_linked' */
        public string $decision
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->link->property->property_name;

        return match ($this->decision) {
            'approved' => (new MailMessage)
                ->subject('Family Link Approved')
                ->line("Your family link for {$this->link->proposed_name} on {$property} has been approved.")
                ->action('View Property', route('properties.show', $this->link->property_id)),

            'rejected' => (new MailMessage)
                ->subject('Family Link Rejected')
                ->line("Your proposal for {$this->link->proposed_name} on {$property} was rejected.")
                ->line("Reason: " . ($this->link->admin_notes ?? 'Not specified')),

            'revoked' => (new MailMessage)
                ->subject('Family Link Revoked')
                ->line("The family link for {$this->link->display_name} on {$property} has been revoked."),

            'linked_welcome' => (new MailMessage)
                ->subject("You've Been Linked to {$property}")
                ->line("{$this->link->landlord->name} has added you as a linked family member on {$property}.")
                ->action('View Property', route('properties.show', $this->link->property_id)),

            'revoked_for_linked' => (new MailMessage)
                ->subject('Your Access Has Been Revoked')
                ->line("Your access to {$property} has been revoked."),

            default => (new MailMessage)->subject('Family Link Update'),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'family_link_' . $this->decision,
            'link_id'     => $this->link->id,
            'property_id' => $this->link->property_id,
            'decision'    => $this->decision,
        ];
    }
}