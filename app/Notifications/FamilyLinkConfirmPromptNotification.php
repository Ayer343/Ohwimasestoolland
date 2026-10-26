<?php
// app/Notifications/FamilyLinkConfirmPromptNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyLinkConfirmPromptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PropertyFamilyLink $link,
    ) {}

    /**
     * Channels: BOTH database and email.
     *
     * - 'database' → the landlord sees an in-app notification they can
     *                act on (deep-links to the confirm action).
     * - 'mail'     → an email reminder that survives them not being
     *                logged in.
     *
     * If you only want one, remove it — but for a "you need to confirm
     * something" prompt, both is the right default.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $propertyName = $this->link->property->property_name ?? 'your property';

        return [
            'title'   => 'Confirm your family link proposal',
            'message' => sprintf(
                'Please confirm the proposal to link %s as your %s for %s.',
                $this->link->proposed_name,
                $this->link->relationship_label,
                $propertyName
            ),
            'icon'       => 'fas fa-hand-pointer',
            'category'   => 'family_link',
            'priority'   => 2,
            'action_url' => route('landlord.family-links.index', [
                'status' => PropertyFamilyLink::STATUS_PENDING_LANDLORD,
            ]),
            'roles'      => ['landlord'],   // must match NotificationController's resolver
            'link_id'    => $this->link->id,
            'property_id'=> $this->link->property_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $propertyName = $this->link->property->property_name ?? 'your property';

        return (new MailMessage)
            ->subject('Confirm your family link proposal')
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line(sprintf(
                'You proposed linking %s as your %s to %s.',
                $this->link->proposed_name,
                $this->link->relationship_label,
                $propertyName
            ))
            ->line('Please confirm this proposal so it can be sent to administrators for review.')
            ->action('Review & confirm', route('landlord.family-links.index', [
                'status' => PropertyFamilyLink::STATUS_PENDING_LANDLORD,
            ]))
            ->line('If you did not submit this proposal, you can safely ignore this email.');
    }
}