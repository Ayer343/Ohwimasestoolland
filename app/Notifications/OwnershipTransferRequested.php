<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnershipTransferRequested extends Notification
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
            ->subject('New Property Ownership Transfer Request')
            ->greeting('Hello ' . $notifiable->name)
            ->line('A new property ownership transfer request has been submitted.')
            ->line('Property: ' . ($this->transfer->property->property_name ?? 'N/A'))
            ->line('From: ' . ($this->transfer->currentLandlord->name ?? 'N/A'))
            ->line('To: ' . $this->transfer->new_owner_name)
            ->line('Document Reference: ' . $this->transfer->document_reference)
            ->action('Review Transfer', route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]))
            ->line('Please review and take appropriate action.');
    }

    public function toArray($notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'property_id' => $this->transfer->property_id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'document_reference' => $this->transfer->document_reference,
            'new_owner_name' => $this->transfer->new_owner_name,
            'message' => 'New ownership transfer request submitted'
        ];
    }
}