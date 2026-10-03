<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TransferReversalRejected extends Notification
{
    use Queueable;

    protected $transfer;
    protected $admin;
    protected $rejectionReason;
    protected $recipientType; // 'requester' or 'current_owner'

    public function __construct(
        PropertyOwnershipTransfer $transfer, 
        ?User $admin = null, 
        ?string $rejectionReason = null,
        string $recipientType = 'requester'
    ) {
        $this->transfer = $transfer;
        $this->admin = $admin;
        $this->rejectionReason = $rejectionReason;
        $this->recipientType = $recipientType;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $propertyName = $this->transfer->property->property_name ?? 'Unknown Property';
        $adminName = $this->admin ? $this->admin->name : 'Administrator';
        
        // Customize message based on who is receiving it
        $customMessage = $this->recipientType === 'current_owner' 
            ? "A reversal request for property **{$propertyName}** has been reviewed and **rejected**."
            : "Your reversal request for property **{$propertyName}** has been **rejected**.";
        
        return (new MailMessage)
            ->subject('❌ Transfer Reversal Request Rejected')
            ->greeting('Hello ' . $notifiable->name)
            ->line($customMessage)
            ->line('**Transfer Details:**')
            ->line("- Document Reference: {$this->transfer->document_reference}")
            ->line("- Original Transfer Date: " . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'))
            ->line("- Reviewed By: {$adminName}")
            ->line("- Rejected At: " . now()->format('F j, Y \a\t g:i A'))
            ->line('**Reason for Rejection:**')
            ->line($this->rejectionReason ?? 'No specific reason provided by administrator.')
            ->line('**What you can do:**')
            ->line("1. Review the rejection reason above")
            ->line("2. Contact support if you believe this decision was made in error")
            ->line("3. Consider other options like resubmitting a new transfer request")
            ->action('View Transfer Details', route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]))
            ->line('If you have questions, please contact support.');
    }

    public function toDatabase($notifiable): array
    {
        $adminName = $this->admin ? $this->admin->name : 'Administrator';
        
        $message = $this->recipientType === 'current_owner'
            ? "A reversal request for {$this->transfer->property->property_name} has been rejected by {$adminName}."
            : "Your transfer reversal request for {$this->transfer->property->property_name} has been rejected.";
        
        return [
            'type' => 'reversal_rejected',
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'rejection_reason' => $this->rejectionReason,
            'rejected_by' => $this->admin ? $this->admin->id : null,
            'rejected_by_name' => $adminName,
            'rejected_at' => now()->toISOString(),
            'recipient_type' => $this->recipientType,
            'message' => $message,
            'action_url' => route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ])
        ];
    }
}