<?php
// app/Notifications/FamilyLinkProposedNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyLinkProposedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $landlord,
        public array $channels = ['database', 'email'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'New family link proposal',
            'message' => sprintf(
                '%s proposed %s as a %s for %s.',
                $this->landlord->name,
                $this->link->proposed_name,
                $this->link->relationship_label,
                $this->link->property->property_name ?? 'a property'
            ),
            'icon'       => 'fas fa-user-plus',
            'category'   => 'family_link',
            'priority'   => 1,
            'action_url' => route('admin.family-links.show', $this->link->id),
            'roles'      => ['admin', 'super-admin'],
            'link_id'    => $this->link->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Family link proposal awaiting review')
            ->line(sprintf(
                '%s proposed %s as a %s.',
                $this->landlord->name,
                $this->link->proposed_name,
                $this->link->relationship_label
            ))
            ->action('Review proposal', route('admin.family-links.show', $this->link->id))
            ->line('Please review this family link proposal.');
    }
}