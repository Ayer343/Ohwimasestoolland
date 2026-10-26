<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupRestoreCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $expiresInMinutes = 15) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your backup restore verification code')
            ->greeting('Confirm your backup restore')
            ->line('A request to restore the system from a backup was initiated on your account.')
            ->line('Enter the following code to confirm:')
            ->line("**{$this->code}**")
            ->line("This code expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request this, ignore this message and rotate your credentials.');
    }
}