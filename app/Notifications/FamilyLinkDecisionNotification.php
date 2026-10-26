<?php
// app/Notifications/FamilyLinkDecisionNotification.php

namespace App\Notifications;

use App\Models\PropertyFamilyLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyLinkDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $decision  One of: 'approved', 'rejected', 'revoked',
     *                            'linked_welcome', 'revoked_for_linked'
     */
    public function __construct(
        public PropertyFamilyLink $link,
        public string $decision,
    ) {}

    /**
     * Channels per decision:
     *
     *   approved           → database + mail  (landlord)
     *   rejected           → database + mail  (landlord)
     *   revoked            → database + mail  (landlord)
     *   linked_welcome     → database + mail  (linked user)
     *   revoked_for_linked → database + mail  (linked user)
     *
     * Adjust per your policy. Database is required for the in-app bell.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $propertyName = $this->link->property->property_name ?? 'your property';
        $memberName   = $this->link->proposed_name;
        $relationship = $this->link->relationship_label;

        return match ($this->decision) {
            'approved' => [
                'title'      => 'Family link approved',
                'message'    => sprintf(
                    '%s has been approved as your %s for %s.',
                    $memberName,
                    $relationship,
                    $propertyName
                ),
                'icon'       => 'fas fa-check-circle',
                'category'   => 'family_link',
                'priority'   => 2,
                'action_url' => route('landlord.family-links.index', ['status' => 'approved']),
                'roles'      => ['landlord'],
                'link_id'    => $this->link->id,
            ],

            'rejected' => [
                'title'      => 'Family link rejected',
                'message'    => sprintf(
                    'Your proposal to link %s as your %s for %s was rejected. Reason: %s',
                    $memberName,
                    $relationship,
                    $propertyName,
                    $this->link->admin_notes ?: 'Not specified'
                ),
                'icon'       => 'fas fa-times-circle',
                'category'   => 'family_link',
                'priority'   => 3,   // higher priority — actionable
                'action_url' => route('landlord.family-links.index', ['status' => 'rejected']),
                'roles'      => ['landlord'],
                'link_id'    => $this->link->id,
                'reason'     => $this->link->admin_notes,
            ],

            'revoked' => [
                'title'      => 'Family link revoked',
                'message'    => sprintf(
                    'Your family link for %s at %s has been revoked.',
                    $memberName,
                    $propertyName
                ),
                'icon'       => 'fas fa-ban',
                'category'   => 'family_link',
                'priority'   => 2,
                'action_url' => route('landlord.family-links.index'),
                'roles'      => ['landlord'],
                'link_id'    => $this->link->id,
            ],

            'linked_welcome' => [
                'title'      => 'Welcome! You\'ve been linked',
                'message'    => sprintf(
                    'You\'ve been linked as a %s for %s. Set your password to get started.',
                    $relationship,
                    $propertyName
                ),
                'icon'       => 'fas fa-user-check',
                'category'   => 'family_link',
                'priority'   => 1,
                'action_url' => route('password.request'),
                'roles'      => ['landlord'],   // linked users are typed as landlord
                'link_id'    => $this->link->id,
            ],

            'revoked_for_linked' => [
                'title'      => 'Your access has been removed',
                'message'    => sprintf(
                    'Your access to %s has been revoked.',
                    $propertyName
                ),
                'icon'       => 'fas fa-user-slash',
                'category'   => 'family_link',
                'priority'   => 2,
                'action_url' => route('dashboard'),
                'roles'      => ['landlord'],
                'link_id'    => $this->link->id,
            ],

            default => [
                'title'      => 'Family link update',
                'message'    => 'There has been an update to a family link.',
                'icon'       => 'fas fa-info-circle',
                'category'   => 'family_link',
                'priority'   => 1,
                'roles'      => ['landlord'],
                'link_id'    => $this->link->id,
            ],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $propertyName = $this->link->property->property_name ?? 'your property';
        $memberName   = $this->link->proposed_name;
        $relationship = $this->link->relationship_label;

        return match ($this->decision) {
            'approved' => (new MailMessage)
                ->subject('Family link approved')
                ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
                ->line(sprintf(
                    '%s has been approved as your %s for %s.',
                    $memberName,
                    $relationship,
                    $propertyName
                ))
                ->line('The family member will receive an invitation to set up their account.')
                ->action('View family links', route('landlord.family-links.index', ['status' => 'approved'])),

            'rejected' => (new MailMessage)
                ->subject('Family link proposal rejected')
                ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
                ->line(sprintf(
                    'Your proposal to link %s as your %s for %s was rejected.',
                    $memberName,
                    $relationship,
                    $propertyName
                ))
                ->line('Reason: ' . ($this->link->admin_notes ?: 'Not specified'))
                ->line('You can edit and resubmit the proposal from the Family Links page.')
                ->action('Edit & resubmit', route('landlord.family-links.index', ['status' => 'rejected'])),

            'revoked' => (new MailMessage)
                ->subject('Family link revoked')
                ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
                ->line(sprintf(
                    'Your family link for %s at %s has been revoked.',
                    $memberName,
                    $propertyName
                ))
                ->action('View family links', route('landlord.family-links.index')),

            'linked_welcome' => (new MailMessage)
                ->subject('You\'ve been linked — set your password')
                ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
                ->line(sprintf(
                    'You\'ve been linked as a %s for %s.',
                    $relationship,
                    $propertyName
                ))
                ->line('Set your password to get started.')
                ->action('Set password', route('password.request')),

            'revoked_for_linked' => (new MailMessage)
                ->subject('Your access has been removed')
                ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
                ->line(sprintf('Your access to %s has been revoked.', $propertyName))
                ->action('Go to dashboard', route('dashboard')),

            default => (new MailMessage)
                ->subject('Family link update')
                ->line('There has been an update to a family link.'),
        };
    }
}