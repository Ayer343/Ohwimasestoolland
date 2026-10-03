<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SuperAdminAgreementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $notificationType = $this->data['notification_type'];
        $agreementNumber = $this->data['agreement_number'];
        $amount = $this->data['amount'];
        $currency = $this->data['currency'];
        $description = $this->data['description'];
        $startDate = $this->data['start_date'];
        $billingFrequency = $this->data['billing_frequency'];
        $developerName = $this->data['developer_name'];
        $developerEmail = $this->data['developer_email'];
        $customMessage = $this->data['message'] ?? '';

        $subject = $this->getSubject($notificationType, $agreementNumber);
        $actionUrl = $this->getActionUrl($notificationType, $this->data['agreement_id']);
        $actionText = $this->getActionText($notificationType);

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getIntroLine($notificationType, $developerName));

        // Add agreement details
        $mailMessage->line('')
            ->line('**Agreement Details:**')
            ->line("- **Agreement Number:** {$agreementNumber}")
            ->line("- **Amount:** {$currency} " . number_format($amount, 2))
            ->line("- **Billing Frequency:** " . ucfirst($billingFrequency))
            ->line("- **Start Date:** " . \Carbon\Carbon::parse($startDate)->format('F j, Y'))
            ->line("- **Description:** {$description}");

        // Add custom message if exists
        if ($customMessage) {
            $mailMessage->line('')
                ->line("**Message from {$developerName}:**")
                ->line($customMessage);
        }

        // Add action button if URL exists
        if ($actionUrl) {
            $mailMessage->action($actionText, $actionUrl);
        }

        // Add outro lines
        $mailMessage->line('')
            ->line($this->getOutroLine($notificationType, $developerName, $developerEmail));

        // Add reply-to for developer
        if ($developerEmail) {
            $mailMessage->replyTo($developerEmail, $developerName);
        }

        return $mailMessage;
    }

    /**
     * Get the database representation of the notification.
     * This matches your notification bell's expected format
     */
    public function toDatabase($notifiable)
    {
        $notificationType = $this->data['notification_type'];
        $priority = $this->getPriority($notificationType);
        $category = $this->getCategory($notificationType);
        
        return [
            // Required fields for your notification bell
            'title' => $this->getTitle($notificationType, $this->data['agreement_number']),
            'message' => $this->getNotificationMessage($notificationType, $this->data['agreement_number']),
            'icon' => $this->getIcon($notificationType),
            'category' => $category,
            'priority' => $priority,
            'action_url' => $this->getActionUrl($notificationType, $this->data['agreement_id']),
            'action_text' => $this->getActionText($notificationType),
            'is_unread' => true, // New notifications are unread by default
            
            // Additional data from your job
            'agreement_id' => $this->data['agreement_id'],
            'agreement_number' => $this->data['agreement_number'],
            'amount' => $this->data['amount'],
            'currency' => $this->data['currency'],
            'description' => $this->data['description'],
            'notification_type' => $notificationType,
            'developer_name' => $this->data['developer_name'],
            'developer_email' => $this->data['developer_email'],
            'custom_message' => $this->data['message'] ?? '',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the notification title.
     */
    protected function getTitle($notificationType, $agreementNumber)
    {
        switch ($notificationType) {
            case 'signature_request':
                return "Signature Required - Agreement #{$agreementNumber}";
            case 'agreement_created':
                return "New Billing Agreement - #{$agreementNumber}";
            case 'agreement_updated':
                return "Agreement Updated - #{$agreementNumber}";
            case 'agreement_terminated':
                return "Agreement Terminated - #{$agreementNumber}";
            default:
                return "Billing Agreement Notification - #{$agreementNumber}";
        }
    }

    /**
     * Get the notification message.
     */
    protected function getNotificationMessage($notificationType, $agreementNumber)
    {
        switch ($notificationType) {
            case 'signature_request':
                return "A billing agreement (#{$agreementNumber}) requires your signature. Please review and sign at your earliest convenience.";
            case 'agreement_created':
                return "A new billing agreement (#{$agreementNumber}) has been created for you. Please review the details.";
            case 'agreement_updated':
                return "Billing agreement #{$agreementNumber} has been updated. Please review the changes.";
            case 'agreement_terminated':
                return "Billing agreement #{$agreementNumber} has been terminated. No further billing will occur.";
            default:
                return "New notification regarding billing agreement #{$agreementNumber}.";
        }
    }

    /**
     * Get the mail subject.
     */
    protected function getSubject($notificationType, $agreementNumber)
    {
        switch ($notificationType) {
            case 'signature_request':
                return "🔔 Action Required: Sign Your Billing Agreement #{$agreementNumber}";
            case 'agreement_created':
                return "📄 New Billing Agreement Created: #{$agreementNumber}";
            case 'agreement_updated':
                return "✏️ Billing Agreement Updated: #{$agreementNumber}";
            case 'agreement_terminated':
                return "❌ Billing Agreement Terminated: #{$agreementNumber}";
            default:
                return "Billing Agreement #{$agreementNumber}";
        }
    }

    /**
     * Get the mail intro line.
     */
    protected function getIntroLine($notificationType, $developerName)
    {
        switch ($notificationType) {
            case 'signature_request':
                return "{$developerName} has sent you a billing agreement that requires your electronic signature.";
            case 'agreement_created':
                return "{$developerName} has created a new billing agreement for your review.";
            case 'agreement_updated':
                return "{$developerName} has updated an existing billing agreement.";
            case 'agreement_terminated':
                return "A billing agreement has been terminated by {$developerName}.";
            default:
                return "You have a new notification regarding a billing agreement.";
        }
    }

    /**
     * Get the mail outro line.
     */
    protected function getOutroLine($notificationType, $developerName, $developerEmail)
    {
        $lines = [];
        
        switch ($notificationType) {
            case 'signature_request':
                $lines[] = 'Please review the agreement and provide your electronic signature at your earliest convenience.';
                break;
            case 'agreement_created':
                $lines[] = 'You will receive a separate notification when the agreement is ready for your signature.';
                break;
            case 'agreement_updated':
                $lines[] = 'Please review the updated terms in the agreement.';
                break;
        }
        
        $lines[] = 'If you have any questions, please contact ' . $developerName . ' at ' . $developerEmail . '.';
        $lines[] = 'Thank you for your prompt attention to this matter.';
        
        return implode(' ', $lines);
    }

    /**
     * Get the action text.
     */
    protected function getActionText($notificationType)
    {
        switch ($notificationType) {
            case 'signature_request':
                return 'Review & Sign Agreement';
            case 'agreement_created':
                return 'View Agreement Details';
            case 'agreement_updated':
                return 'View Updated Agreement';
            case 'agreement_terminated':
                return 'View Termination Details';
            default:
                return 'View Agreement';
        }
    }

    /**
     * Get the action URL.
     */
    protected function getActionUrl($notificationType, $agreementId)
    {
        $baseUrl = config('app.url');
        
        switch ($notificationType) {
            case 'signature_request':
                return $baseUrl . "/superadmin/billing/agreements/{$agreementId}/sign";
            case 'agreement_created':
            case 'agreement_updated':
            case 'agreement_terminated':
            default:
                return $baseUrl . "/superadmin/billing/agreements/{$agreementId}";
        }
    }

    /**
     * Get the notification icon (matches your notification bell expectations).
     */
    protected function getIcon($notificationType)
    {
        switch ($notificationType) {
            case 'signature_request':
                return 'fas fa-signature';
            case 'agreement_created':
                return 'fas fa-handshake';
            case 'agreement_updated':
                return 'fas fa-edit';
            case 'agreement_terminated':
                return 'fas fa-times-circle';
            default:
                return 'fas fa-bell';
        }
    }

    /**
     * Get the notification priority (0-3, with 3 being highest).
     */
    protected function getPriority($notificationType)
    {
        switch ($notificationType) {
            case 'signature_request':
                return 3; // High priority
            case 'agreement_terminated':
                return 3; // High priority
            case 'agreement_updated':
                return 2; // Medium priority
            case 'agreement_created':
                return 1; // Normal priority
            default:
                return 1; // Normal priority
        }
    }

    /**
     * Get the notification category.
     */
    protected function getCategory($notificationType)
    {
        switch ($notificationType) {
            case 'signature_request':
                return 'action_required';
            case 'agreement_created':
                return 'agreement';
            case 'agreement_updated':
                return 'agreement';
            case 'agreement_terminated':
                return 'alert';
            default:
                return 'general';
        }
    }
}