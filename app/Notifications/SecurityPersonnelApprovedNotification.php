<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityPersonnelApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $personnel,
        public User $approvedBy,
        public bool $invitationDispatched = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Security Personnel Submission Approved')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for **{$this->personnel->name}** has been approved by {$this->approvedBy->name}.")
            ->line('The officer is now active.');

        if ($this->invitationDispatched) {
            $mail->line('An invitation was dispatched automatically using the channels you queued.');
        } else {
            $mail->line('You can now send an invitation from the personnel list if you have not done so already.');
        }

        $mail->action('View Personnel', $this->indexUrl());

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->invitationDispatched
            ? "{$this->personnel->name} was approved by {$this->approvedBy->name}. The queued invitation was sent."
            : "{$this->personnel->name} was approved by {$this->approvedBy->name}.";

        return [
            // --- required by NotificationController::formatNotification() ---
            'type'       => 'security_personnel_approved',
            'title'      => 'Submission approved',
            'message'    => $message,
            'icon'       => 'fas fa-check-circle text-success',
            'category'   => 'security',
            'priority'   => 2,
            'action_url' => $this->indexUrl(),

            // --- role-scoping ---
            'roles'      => ['security-personnel', 'admin', 'super-admin'],

            // --- domain payload ---
            'personnel_id'          => $this->personnel->id,
            'personnel_name'        => $this->personnel->name,
            'approved_by_id'        => $this->approvedBy->id,
            'approved_by_name'      => $this->approvedBy->name,
            'invitation_dispatched' => $this->invitationDispatched,

            // --- back-compat for older frontend code reading 'url' ---
            // NB: previously pointed at security.personnel.show, which
            // returns JSON-only. Now points at the index page.
            'url'        => $this->indexUrl(),
        ];
    }

    private function indexUrl(): string
    {
        return route('security.personnel.index', ['tab' => 'active']);
    }
}