<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityPersonnelSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $submittedUser,
        public User $head,
        public ?array $queuedInvitation = null,
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
        $meta = $this->decodeMetadata();

        $mail = (new MailMessage)
            ->subject('New Security Personnel Submission Awaiting Approval')
            ->greeting("Hello {$notifiable->name},")
            ->line("The Head of Security ({$this->head->name}) has submitted a new security personnel record for approval.")
            ->line("**Name:** {$this->submittedUser->name}")
            ->line("**Email:** " . ($this->submittedUser->email ?? '—'))
            ->line("**Phone:** " . ($this->submittedUser->phone ?? '—'))
            ->line("**Submitted at:** " . ($meta['submitted_at'] ?? now()->toDateTimeString()));

        if ($this->queuedInvitation && ! empty($this->queuedInvitation['channels'])) {
            $channels = implode(', ', array_map('strtoupper', $this->queuedInvitation['channels']));
            $mail->line("An invitation has been **queued** and will be sent automatically on approval via: {$channels}.");
        }

        $mail->action('Review Submission', $this->reviewUrl())
             ->line('No invitation has been sent yet. Approve or reject the submission to continue.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $meta = $this->decodeMetadata();

        return [
            // --- required by NotificationController::formatNotification() ---
            'type'       => 'security_personnel_submitted',
            'title'      => 'New security personnel submission',
            'message'    => "{$this->head->name} submitted {$this->submittedUser->name} for approval.",
            'icon'       => 'fas fa-user-shield text-warning',
            'category'   => 'security',
            'priority'   => 2,
            'action_url' => $this->reviewUrl(),

            // --- role-scoping so it only appears for admins ---
            'roles'      => ['admin', 'super-admin'],

            // --- domain payload ---
            'user_id'           => $this->submittedUser->id,
            'user_name'         => $this->submittedUser->name,
            'user_email'        => $this->submittedUser->email,
            'user_phone'        => $this->submittedUser->phone,
            'submitted_by_id'   => $this->head->id,
            'submitted_by_name' => $this->head->name,
            'submitted_at'      => $meta['submitted_at'] ?? now()->toIso8601String(),
            'queued_channels'   => $this->queuedInvitation['channels'] ?? [],
            'review_url'        => $this->reviewUrl(),

            // --- back-compat for older frontend code reading 'url' ---
            'url'        => $this->reviewUrl(),
        ];
    }

    /**
     * Normalize the submitted user's metadata column to a plain array.
     * (May be a JSON string, an array, or null depending on how the
     * model was hydrated.)
     */
    private function decodeMetadata(): array
    {
        $meta = $this->submittedUser->metadata ?? [];

        if (is_string($meta)) {
            return json_decode($meta, true) ?? [];
        }

        return is_array($meta) ? $meta : [];
    }

    private function reviewUrl(): string
    {
        return route('admin.users.index', ['queue' => 'pending_personnel']);
    }
}