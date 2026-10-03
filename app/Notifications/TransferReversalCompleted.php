<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TransferReversalCompleted extends Notification
{
    use Queueable;

    protected $transfer;
    protected $reversalTransfer;

    public function __construct(PropertyOwnershipTransfer $transfer, $reversalTransfer = null)
    {
        $this->transfer = $transfer;
        $this->reversalTransfer = $reversalTransfer;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $isOriginalOwner = $notifiable->id === $this->transfer->current_landlord_id;
        $propertyName = $this->transfer->property->property_name ?? 'Unknown Property';
        
        if ($isOriginalOwner) {
            return (new MailMessage)
                ->subject('✅ Transfer Reversal Completed - Property Restored')
                ->greeting('Hello ' . $notifiable->name)
                ->line("The reversal of property **{$propertyName}** has been **completed successfully**!")
                ->line('**Reversal Details:**')
                ->line("- Original Transfer Reference: {$this->transfer->document_reference}")
                ->line("- Reversal Completed At: " . now()->format('F j, Y \a\t g:i A'))
                ->line("- The property has been returned to your portfolio")
                ->line('**What has been restored:**')
                ->line("✓ Property ownership has been returned to you")
                ->line("✓ All property units are back under your management")
                ->line("✓ Tenant relationships have been restored")
                ->line("✓ Rental agreements are back under your name")
                ->action('View Your Property', route('properties.show', $this->transfer->property_id))
                ->line('Thank you for your patience throughout this process.');
        } else {
            return (new MailMessage)
                ->subject('📋 Transfer Reversal Completed - Property Removed')
                ->greeting('Hello ' . $notifiable->name)
                ->line("The property **{$propertyName}** has been removed from your portfolio due to a successful reversal request.")
                ->line('**Reversal Details:**')
                ->line("- Original Transfer Reference: {$this->transfer->document_reference}")
                ->line("- Reversal Completed At: " . now()->format('F j, Y \a\t g:i A'))
                ->line('**What this means for you:**')
                ->line("✓ The property is no longer under your management")
                ->line("✓ You will no longer receive notifications for this property")
                ->line("✓ Please update your records accordingly")
                ->action('View Your Properties', route('landlord.ownership-transfers.index'))
                ->line('If you believe this is an error, please contact support immediately.');
        }
    }

    public function toDatabase($notifiable): array
    {
        $isOriginalOwner = $notifiable->id === $this->transfer->current_landlord_id;
        
        return [
            'type' => 'reversal_completed',
            'transfer_id' => $this->transfer->id,
            'reversal_transfer_id' => $this->reversalTransfer ? $this->reversalTransfer->id : null,
            'transfer_reference' => $this->transfer->document_reference,
            'property_name' => $this->transfer->property->property_name ?? 'Unknown',
            'is_original_owner' => $isOriginalOwner,
            'completed_at' => now()->toISOString(),
            'message' => $isOriginalOwner 
                ? 'Your transfer reversal has been completed. The property has been returned to you.'
                : 'A transfer reversal has been completed. The property has been removed from your portfolio.',
            'action_url' => route('properties.show', $this->transfer->property_id)
        ];
    }
}