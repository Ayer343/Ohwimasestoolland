<?php

namespace App\Notifications;

use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TransferStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transfer;
    protected $status;
    protected $additionalInfo;

    /**
     * Create a new notification instance.
     */
    public function __construct(PropertyOwnershipTransfer $transfer, string $status, $additionalInfo = null)
    {
        $this->transfer = $transfer;
        $this->status = $status;
        $this->additionalInfo = $additionalInfo;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['mail', 'database'];
        
        // Add broadcast for real-time notifications if needed
        if (config('ownership_transfer.broadcast_notifications', false)) {
            $channels[] = 'broadcast';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getSubject();
        $message = $this->getMessage($notifiable);
        $actionText = $this->getActionText();
        $actionUrl = $this->getActionUrl($notifiable);

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name)
            ->line($message);

        // Add property details
        $mail->line('')
             ->line('**Property Details:**')
             ->line('- Property: ' . ($this->transfer->property->property_name ?? 'N/A'))
             ->line('- Document Reference: ' . $this->transfer->document_reference)
             ->line('- Transfer Date: ' . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'));

        // Add status-specific additional information
        $additionalInfo = $this->getAdditionalInfoLines();
        foreach ($additionalInfo as $infoLine) {
            $mail->line($infoLine);
        }

        // Add action button
        if ($actionUrl) {
            $mail->action($actionText, $actionUrl);
        }

        // Add footer
        $mail->line('')
             ->line('Thank you for using our service!')
             ->line('If you have any questions, please contact support.');

        return $mail;
    }

    /**
     * Get the array representation of the notification for database.
     */
    public function toArray($notifiable): array
    {
        Log::info('TransferStatusNotification toArray called', [
            'notifiable_id' => $notifiable->id,
            'notifiable_type' => get_class($notifiable),
            'transfer_id' => $this->transfer->id,
            'status' => $this->status,
            'message' => $this->getMessage($notifiable)
        ]);
        
        return [
            'transfer_id' => $this->transfer->id,
            'property_id' => $this->transfer->property_id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'document_reference' => $this->transfer->document_reference,
            'status' => $this->status,
            'status_label' => $this->transfer->status_label,
            'message' => $this->getMessage($notifiable),
            'additional_info' => $this->additionalInfo,
            'timestamp' => now()->toISOString(),
            'reversal_status' => $this->transfer->reversal_status ?? null,
            'is_reversed' => $this->transfer->is_reversed ?? false,
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast($notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'property_name' => $this->transfer->property->property_name ?? 'N/A',
            'document_reference' => $this->transfer->document_reference,
            'status' => $this->status,
            'status_label' => $this->transfer->status_label,
            'message' => $this->getMessage($notifiable),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the subject based on status
     */
    private function getSubject(): string
    {
        $subjects = [
            // Original transfer statuses
            'submitted' => 'Ownership Transfer Request Submitted',
            'request_submitted' => 'Ownership Transfer Request Submitted',
            'approved' => 'Ownership Transfer Approved',
            'request_approved' => 'Your Transfer Request Has Been Approved',
            'rejected' => 'Ownership Transfer Rejected',
            'request_rejected' => 'Your Transfer Request Has Been Rejected',
            'completed' => 'Ownership Transfer Completed',
            'cancelled' => 'Ownership Transfer Cancelled',
            'resubmitted' => 'Transfer Request Resubmitted',
            'resubmitted_receiver' => 'Transfer Request Resubmitted - Action Required',
            
            // Reversal statuses
            'reversal_requested' => '⚠️ Transfer Reversal Request Submitted',
            'reversal_approved' => '✅ Transfer Reversal Approved',
            'reversal_rejected' => '❌ Transfer Reversal Rejected',
            'reversal_completed_owner' => '✅ Transfer Reversal Completed - Property Restored',
            'reversal_completed_receiver' => '📋 Transfer Reversal Completed - Property Removed',
            'reversal_expired' => '⏰ Transfer Reversal Request Expired',
            'reversal_cancelled' => 'Transfer Reversal Cancelled',
        ];

        return $subjects[$this->status] ?? 'Ownership Transfer Status Update';
    }

    /**
     * Get the message based on status and recipient
     */
    private function getMessage($notifiable): string
    {
        $propertyName = $this->transfer->property->property_name ?? 'your property';
        $isAdmin = $notifiable->isAdmin() ?? false;
        $isRequester = $notifiable->id === $this->transfer->current_landlord_id;
        $isReceiver = $notifiable->id === $this->transfer->new_landlord_id;

        $messages = [
            // Original transfer statuses
            'submitted' => "Your ownership transfer request for {$propertyName} has been submitted successfully. It is now pending admin approval.",
            'request_submitted' => "Your ownership transfer request for {$propertyName} has been submitted successfully. It is now pending admin approval.",
            'approved' => "Your ownership transfer request for {$propertyName} has been approved. The new owner will receive an invitation to complete the process.",
            'request_approved' => "Your transfer request for {$propertyName} has been approved by an administrator.",
            'rejected' => "Your ownership transfer request for {$propertyName} has been rejected. Reason: " . ($this->transfer->rejection_reason ?? 'No reason provided'),
            'request_rejected' => "Your transfer request for {$propertyName} has been rejected. Reason: " . ($this->transfer->rejection_reason ?? 'No reason provided'),
            'completed' => "The ownership transfer for {$propertyName} has been completed successfully. The property is now under new ownership.",
            'cancelled' => "Your ownership transfer request for {$propertyName} has been cancelled.",
            'resubmitted' => "Your transfer request for {$propertyName} has been resubmitted successfully and is pending admin approval.",
            'resubmitted_receiver' => "A transfer request for {$propertyName} has been resubmitted. You have been designated as the new owner.",
            
            // Reversal statuses
            'reversal_requested' => $isAdmin 
                ? "A reversal request has been submitted for property {$propertyName} by {$this->transfer->currentLandlord->name}. Please review and take action."
                : "Your reversal request for property {$propertyName} has been submitted successfully. An administrator will review it within " . config('ownership_transfer.reversal.review_deadline_days', 7) . " days.",
            
            'reversal_approved' => $isRequester
                ? "Great news! Your reversal request for property {$propertyName} has been approved. The property is being returned to you."
                : "A reversal request for property {$propertyName} has been approved. The property is being returned to the original owner.",
            
            'reversal_rejected' => "Your reversal request for property {$propertyName} has been rejected. Reason: " . ($this->additionalInfo ?? 'No specific reason provided'),
            
            'reversal_completed_owner' => "The reversal for property {$propertyName} has been completed successfully. The property has been returned to your portfolio.",
            
            'reversal_completed_receiver' => "The reversal for property {$propertyName} has been completed. The property has been removed from your portfolio.",
            
            'reversal_expired' => "Your reversal request for property {$propertyName} has expired as no action was taken within the deadline.",
            
            'reversal_cancelled' => "Your reversal request for property {$propertyName} has been cancelled as requested.",
        ];

        // Admin-specific messages
        if ($isAdmin && $this->status === 'reversal_requested') {
            return $messages['reversal_requested'];
        }

        return $messages[$this->status] ?? "Your ownership transfer request for {$propertyName} status has been updated to: {$this->status}";
    }

    /**
     * Get additional info lines for the email
     */
    private function getAdditionalInfoLines(): array
    {
        $lines = [];
        
        switch ($this->status) {
            case 'rejected':
            case 'request_rejected':
                if ($this->transfer->can_resubmit_after && $this->transfer->can_resubmit_after->isFuture()) {
                    $lines[] = '';
                    $lines[] = '**Resubmission Information:**';
                    $lines[] = "- You can resubmit this request after: " . $this->transfer->can_resubmit_after->format('F j, Y');
                } elseif ($this->transfer->canBeResubmitted()) {
                    $lines[] = '';
                    $lines[] = '**Resubmission Information:**';
                    $lines[] = "- You can resubmit this request now with corrections.";
                }
                break;
                
            case 'reversal_requested':
                if ($this->transfer->reversal_deadline) {
                    $lines[] = '';
                    $lines[] = '**Deadline Information:**';
                    $lines[] = "- This request will expire on: " . $this->transfer->reversal_deadline->format('F j, Y');
                }
                if ($this->transfer->reversal_reason) {
                    $lines[] = '';
                    $lines[] = '**Reason Provided:**';
                    $lines[] = "- " . $this->transfer->reversal_reason;
                }
                break;
                
            case 'reversal_approved':
                $lines[] = '';
                $lines[] = '**Next Steps:**';
                $lines[] = "- The property ownership is being transferred back";
                $lines[] = "- You will receive a confirmation once complete";
                $lines[] = "- This process typically takes a few minutes";
                break;
                
            case 'reversal_completed_owner':
                $lines[] = '';
                $lines[] = '**What has been restored:**';
                $lines[] = "✓ Property ownership has been returned to you";
                $lines[] = "✓ All property units are back under your management";
                $lines[] = "✓ Tenant relationships have been restored";
                if ($this->transfer->reversal_transfer_id) {
                    $lines[] = '';
                    $lines[] = "**Reversal Reference:** #{$this->transfer->reversal_transfer_id}";
                }
                break;
                
            case 'reversal_completed_receiver':
                $lines[] = '';
                $lines[] = '**What this means for you:**';
                $lines[] = "✓ The property is no longer under your management";
                $lines[] = "✓ You will no longer receive notifications for this property";
                $lines[] = "✓ Please update your records accordingly";
                break;
                
            case 'approved':
            case 'request_approved':
                if ($this->transfer->new_landlord_id && !($this->transfer->metadata['is_existing_landlord'] ?? false)) {
                    $lines[] = '';
                    $lines[] = '**Next Steps for New Owner:**';
                    $lines[] = "- The new owner will receive an invitation email";
                    $lines[] = "- They need to accept the invitation to complete registration";
                    $lines[] = "- Once registered, the transfer can be completed";
                }
                break;
        }
        
        return $lines;
    }

    /**
     * Get action button text
     */
    private function getActionText(): string
    {
        $actionTexts = [
            'submitted' => 'Track Request',
            'request_submitted' => 'Track Request',
            'approved' => 'View Transfer',
            'request_approved' => 'View Transfer',
            'rejected' => 'View Details',
            'request_rejected' => 'View Details',
            'completed' => 'Download Certificate',
            'cancelled' => 'View Details',
            'resubmitted' => 'Track Request',
            'resubmitted_receiver' => 'View Transfer',
            'reversal_requested' => 'Track Request',
            'reversal_approved' => 'Track Status',
            'reversal_rejected' => 'View Details',
            'reversal_completed_owner' => 'View Property',
            'reversal_completed_receiver' => 'View Properties',
            'reversal_expired' => 'Submit New Request',
            'reversal_cancelled' => 'View Transfers',
        ];

        return $actionTexts[$this->status] ?? 'View Transfer Details';
    }

    /**
     * Get action URL based on status and recipient
     */
    private function getActionUrl($notifiable): ?string
    {
        $isAdmin = $notifiable->isAdmin() ?? false;
        
        // Reversal-related URLs
        if (in_array($this->status, ['reversal_requested', 'reversal_approved', 'reversal_rejected', 'reversal_completed_receiver'])) {
            if ($isAdmin && $this->status === 'reversal_requested') {
                return route('admin.ownership-transfers.reversal-requests');
            }
            return route('properties.ownership-transfers.show', [
                'property' => $this->transfer->property_id,
                'transfer' => $this->transfer->id
            ]);
        }
        
        // Completion URLs
        if ($this->status === 'reversal_completed_owner') {
            return route('properties.show', $this->transfer->property_id);
        }
        
        // Standard URLs
        if ($isAdmin) {
            return route('admin.ownership-transfers.show', $this->transfer->id);
        }
        
        return route('properties.ownership-transfers.show', [
            'property' => $this->transfer->property_id,
            'transfer' => $this->transfer->id
        ]);
    }
}