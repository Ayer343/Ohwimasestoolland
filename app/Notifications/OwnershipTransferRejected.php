<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnershipTransferRejected extends Notification
{
    use Queueable;

    protected $transfer;

    public function __construct(PropertyOwnershipTransfer $transfer)
    {
        $this->transfer = $transfer;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ownership Transfer Rejected')
            ->greeting('Hello ' . $notifiable->name)
            ->line('Your property ownership transfer request has been rejected.')
            ->line('Property: ' . ($this->transfer->property->property_name ?? 'N/A'))
            ->line('Reason: ' . ($this->transfer->rejection_reason ?? 'No reason provided'))
            ->action('View Details', route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]));
    }

    public function toArray($notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'reason' => $this->transfer->rejection_reason,
            'message' => 'Ownership transfer rejected'
        ];
    }
}