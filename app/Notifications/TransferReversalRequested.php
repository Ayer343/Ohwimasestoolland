<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TransferReversalRequested extends Notification
{
    use Queueable;

    protected $transfer;
    protected $requester;

    public function __construct(PropertyOwnershipTransfer $transfer, $requester = null)
    {
        $this->transfer = $transfer;
        $this->requester = $requester ?? $transfer->currentLandlord;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $propertyName = $this->transfer->property->property_name ?? 'Unknown Property';
        $requesterName = $this->requester->name ?? 'A landlord';
        
        return (new MailMessage)
            ->subject('⚠️ Transfer Reversal Requested - Action Required')
            ->greeting('Hello ' . $notifiable->name)
            ->line("{$requesterName} has requested a reversal of ownership transfer for property: **{$propertyName}**.")
            ->line('**Transfer Details:**')
            ->line("- Document Reference: {$this->transfer->document_reference}")
            ->line("- Original Transfer Date: " . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'))
            ->line("- Requested By: {$requesterName}")
            ->line("- Requested At: " . now()->format('F j, Y \a\t g:i A'))
            ->line('**Reason for Reversal:**')
            ->line($this->transfer->reversal_reason ?? 'No reason provided')
            ->action('Review Reversal Request', route('admin.ownership-transfers.reversal-requests'))
            ->line('Please review this request and take appropriate action within the deadline.')
            ->line('If no action is taken, the request will expire on: ' . ($this->transfer->reversal_deadline ? $this->transfer->reversal_deadline->format('F j, Y') : 'N/A'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'reversal_requested',
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'property_id' => $this->transfer->property_id,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'requester_id' => $this->requester->id,
            'requester_name' => $this->requester->name,
            'reversal_reason' => $this->transfer->reversal_reason,
            'reversal_deadline' => $this->transfer->reversal_deadline ? $this->transfer->reversal_deadline->toISOString() : null,
            'message' => 'A transfer reversal request requires your review',
            'action_url' => route('admin.ownership-transfers.reversal-requests'),
            'priority' => 'high'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'reversal_requested',
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'requester_name' => $this->requester->name,
            'message' => 'New transfer reversal request awaiting review',
            'time_ago' => now()->diffForHumans()
        ]);
    }
}