<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityPersonnelRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $personnel,
        public User $rejectedBy,
        public string $reason,
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
        return (new MailMessage)
            ->subject('Security Personnel Submission Rejected')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for **{$this->personnel->name}** was rejected by {$this->rejectedBy->name}.")
            ->line("**Reason:** {$this->reason}")
            ->line('You may resubmit a corrected record if appropriate.')
            ->action('Back to Personnel', $this->indexUrl());
    }

    public function toArray(object $notifiable): array
    {
        return [
            // --- required by NotificationController::formatNotification() ---
            'type'       => 'security_personnel_rejected',
            'title'      => 'Submission rejected',
            'message'    => "{$this->personnel->name}'s submission was rejected: {$this->reason}",
            'icon'       => 'fas fa-times-circle text-danger',
            'category'   => 'security',
            'priority'   => 3,
            'action_url' => $this->indexUrl(),

            // --- role-scoping ---
            'roles'      => ['security-personnel', 'admin', 'super-admin'],

            // --- domain payload ---
            'personnel_id'     => $this->personnel->id,
            'personnel_name'   => $this->personnel->name,
            'rejected_by_id'   => $this->rejectedBy->id,
            'rejected_by_name' => $this->rejectedBy->name,
            'reason'           => $this->reason,

            // --- back-compat for older frontend code reading 'url' ---
            'url'        => $this->indexUrl(),
        ];
    }

    private function indexUrl(): string
    {
        // Rejected records live in the 'pending' tab until the head
        // re-submits (they're soft-deleted after rejection, so the
        // 'active' tab wouldn't show them).
        return route('security.personnel.index', ['tab' => 'pending']);
    }
}