<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OwnershipTransferCompleted extends Notification
{
    use Queueable;

    protected $transfer;
    protected $recipientType;
    protected $certificatePath;

    public function __construct(PropertyOwnershipTransfer $transfer, string $recipientType = 'previous_owner', $certificatePath = null)
    {
        $this->transfer = $transfer;
        $this->recipientType = $recipientType;
        $this->certificatePath = $certificatePath;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->recipientType === 'previous_owner' 
            ? 'Ownership Transfer Completed' 
            : 'Congratulations! You are now the Property Owner';

        $message = $this->recipientType === 'previous_owner'
            ? 'The ownership transfer for your property has been completed.'
            : 'Congratulations! You are now the official owner of the property.';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name)
            ->line($message)
            ->line('Property: ' . ($this->transfer->property->property_name ?? 'N/A'))
            ->line('Transfer Date: ' . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'));

        if ($this->certificatePath) {
            $mail->line('Your transfer certificate is attached.');
        }

        return $mail->action('View Transfer Details', route('properties.ownership-transfers.show', [
            'property' => $this->transfer->property_id,
            'transfer' => $this->transfer->id
        ]));
    }

    public function toArray($notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'status' => 'completed',
            'message' => 'Ownership transfer completed'
        ];
    }
}