<?php
// app/Notifications/FamilyLinkProposedNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FamilyLinkProposedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $landlord,
        public array $channels = ['email']
    ) {}

    public function via(object $notifiable): array
    {
        // Map custom channel keys → Laravel notification channel classes
        $map = [
            'email' => 'mail',
            'sms'   => \App\Notifications\Channels\SmsChannel::class,
            'whatsapp' => \App\Notifications\Channels\WhatsAppChannel::class,
        ];

        return collect($this->channels)
            ->map(fn ($c) => $map[$c] ?? $c)
            ->unique()
            ->values()
            ->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Family Link Proposal Awaiting Review')
            ->line("{$this->landlord->name} proposed linking a family member to property {$this->link->property->property_name}.")
            ->line("Member: {$this->link->proposed_name} ({$this->link->relationship_label})")
            ->action('Review Proposal', route('admin.family-links.show', $this->link->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'family_link_proposed',
            'link_id'     => $this->link->id,
            'property_id' => $this->link->property_id,
            'landlord'    => $this->landlord->name,
            'member'      => $this->link->proposed_name,
        ];
    }
}