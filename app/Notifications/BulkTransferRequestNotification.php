<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BulkTransferRequestNotification extends Notification
{
    use Queueable;

    protected $transfers;
    protected $failedCount;

    public function __construct(array $transfers, int $failedCount)
    {
        $this->transfers = $transfers;
        $this->failedCount = $failedCount;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $successCount = count($this->transfers);
        
        return (new MailMessage)
            ->subject('Bulk Ownership Transfer Request')
            ->greeting('Hello ' . $notifiable->name)
            ->line("A bulk ownership transfer request has been submitted.")
            ->line("Successful Transfers: {$successCount}")
            ->line("Failed Transfers: {$this->failedCount}")
            ->action('View Transfers', route('admin.ownership-transfers.index'))
            ->line('Please review and take appropriate action.');
    }

    public function toArray($notifiable): array
    {
        return [
            'successful_count' => count($this->transfers),
            'failed_count' => $this->failedCount,
            'message' => 'Bulk ownership transfer request submitted'
        ];
    }
}