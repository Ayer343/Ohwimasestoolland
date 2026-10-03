<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TransferReversalApproved extends Notification
{
    use Queueable;

    protected $transfer;
    protected $adminNotes;

    public function __construct(PropertyOwnershipTransfer $transfer, $adminNotes = null)
    {
        $this->transfer = $transfer;
        $this->adminNotes = $adminNotes;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $isRequester = $notifiable->id === $this->transfer->current_landlord_id;
        $propertyName = $this->transfer->property->property_name ?? 'Unknown Property';
        
        if ($isRequester) {
            return (new MailMessage)
                ->subject('✅ Transfer Reversal Approved - Property Being Returned')
                ->greeting('Hello ' . $notifiable->name)
                ->line("Great news! Your reversal request for property **{$propertyName}** has been **approved**.")
                ->line('**Transfer Details:**')
                ->line("- Document Reference: {$this->transfer->document_reference}")
                ->line("- Original Transfer Date: " . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'))
                ->line("- Approved At: " . now()->format('F j, Y \a\t g:i A'))
                ->line('**What happens next:**')
                ->line("1. The property ownership is being returned to you")
                ->line("2. You will receive a confirmation once the reversal is complete")
                ->line("3. All property units and tenant relationships will be restored")
                ->action('Track Reversal Status', route('properties.ownership-transfers.show', [
                    'property' => $this->transfer->property_id,
                    'transfer' => $this->transfer->id
                ]))
                ->line('Thank you for your patience.');
        } else {
            return (new MailMessage)
                ->subject('📋 Transfer Reversal Approved - Action Required')
                ->greeting('Hello ' . $notifiable->name)
                ->line("A reversal request for property **{$propertyName}** has been approved.")
                ->line('**Transfer Details:**')
                ->line("- Document Reference: {$this->transfer->document_reference}")
                ->line("- The property is being returned to the original owner")
                ->line("- Please update your records accordingly")
                ->action('View Transfer Details', route('admin.ownership-transfers.show', $this->transfer->id))
                ->line('If you have any questions, please contact support.');
        }
    }

    public function toDatabase($notifiable): array
    {
        $isRequester = $notifiable->id === $this->transfer->current_landlord_id;
        
        return [
            'type' => 'reversal_approved',
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'is_requester' => $isRequester,
            'admin_notes' => $this->adminNotes,
            'approved_at' => now()->toISOString(),
            'message' => $isRequester 
                ? 'Your transfer reversal request has been approved. The property is being returned to you.'
                : 'A transfer reversal request has been approved.',
            'action_url' => $isRequester 
                ? route('properties.ownership-transfers.show', ['property' => $this->transfer->property_id, 'transfer' => $this->transfer->id])
                : route('admin.ownership-transfers.show', $this->transfer->id)
        ];
    }
}