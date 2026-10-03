<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TransferReversalExpired extends Notification
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
        $propertyName = $this->transfer->property->property_name ?? 'Unknown Property';
        
        return (new MailMessage)
            ->subject('⏰ Transfer Reversal Request Expired')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your reversal request for property **{$propertyName}** has **expired** as no action was taken by the administrator within the deadline.")
            ->line('**Transfer Details:**')
            ->line("- Document Reference: {$this->transfer->document_reference}")
            ->line("- Requested On: " . ($this->transfer->reversal_requested_at ? $this->transfer->reversal_requested_at->format('F j, Y') : 'N/A'))
            ->line("- Expired On: " . now()->format('F j, Y \a\t g:i A'))
            ->line('**What you can do:**')
            ->line("1. Submit a new reversal request if still within the allowed window")
            ->line("2. Contact support for assistance")
            ->line("3. Consider other options like resubmitting a new transfer request")
            ->action('Submit New Request', route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]))
            ->line('We apologize for the delay and appreciate your understanding.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'reversal_expired',
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'requested_at' => $this->transfer->reversal_requested_at ? $this->transfer->reversal_requested_at->toISOString() : null,
            'expired_at' => now()->toISOString(),
            'message' => 'Your transfer reversal request has expired. Please submit a new request if needed.',
            'action_url' => route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ])
        ];
    }
}