<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnershipTransferApproved extends Notification
{
    use Queueable;

    protected $transfer;
    protected $recipientType;

    public function __construct(PropertyOwnershipTransfer $transfer, string $recipientType = 'current_landlord')
    {
        $this->transfer = $transfer;
        $this->recipientType = $recipientType;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->recipientType === 'current_landlord' 
            ? 'Ownership Transfer Approved' 
            : 'You are the New Owner';

        $message = $this->recipientType === 'current_landlord'
            ? 'Your property ownership transfer request has been approved.'
            : 'You have been designated as the new owner of a property.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name)
            ->line($message)
            ->line('Property: ' . ($this->transfer->property->property_name ?? 'N/A'))
            ->line('Transfer Date: ' . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'))
            ->action('View Transfer Details', route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]));
    }

    public function toArray($notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'status' => 'approved',
            'message' => 'Ownership transfer approved'
        ];
    }
}