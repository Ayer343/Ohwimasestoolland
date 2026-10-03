<?php

namespace App\Notifications;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ConstructionRegistrationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $registration;
    protected $status;
    protected $notes;

    public function __construct(LandlordConstructionRegistration $registration, string $status, ?string $notes = null)
    {
        $this->registration = $registration;
        $this->status = $status;
        $this->notes = $notes;
    }

    /**
     * Get the notification's delivery channels
     */
    public function via($notifiable)
    {
        $channels = ['database'];
        
        // Add mail if landlord has email
        if ($notifiable && $notifiable->email) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation
     */
    public function toMail($notifiable)
    {
        if ($this->status === LandlordConstructionRegistration::STATUS_APPROVED) {
            return $this->toApprovedMail($notifiable);
        } elseif ($this->status === LandlordConstructionRegistration::STATUS_REJECTED) {
            return $this->toRejectedMail($notifiable);
        } elseif ($this->status === LandlordConstructionRegistration::STATUS_NEEDS_INFO) {
            return $this->toNeedsInfoMail($notifiable);
        }
        
        return $this->toDefaultMail($notifiable);
    }

    /**
     * Approved notification mail
     */
    private function toApprovedMail($notifiable)
    {
        // Use admin route for approved notifications (since this goes to registered landlords)
        $propertyUrl = $this->registration->approved_property_id 
            ? route('properties.show', $this->registration->approved_property_id)
            : null;
        
        $mail = (new MailMessage)
            ->subject('Your Property Registration Has Been Approved! ✅')
            ->greeting('Congratulations ' . ($notifiable->name ?? 'Valued Landlord') . '!')
            ->line('Your property registration has been **APPROVED** by our estate management team.')
            ->line('')
            ->line('**Property Details:**')
            ->line('- Property Name: ' . ($this->registration->property_name ?? 'Not specified'))
            ->line('- Location: ' . ($this->registration->street_name ?? 'Not specified') . ', Plot ' . ($this->registration->plot_number ?? 'Not specified'));
        
        if ($this->registration->formatted_property_type) {
            $mail->line('- Property Type: ' . $this->registration->formatted_property_type);
        }
        
        if ($this->registration->approvedProperty && $this->registration->approvedProperty->registration_pattern) {
            $mail->line('- Registration Pattern: ' . $this->registration->approvedProperty->registration_pattern);
        }
        
        $mail->line('')
            ->line('You can now access your property in the landlord portal.');
        
        if ($propertyUrl) {
            $mail->action('View Your Property', $propertyUrl);
        }
        
        $mail->line('If you have any questions, please contact our estate management office.')
            ->salutation('Thank you for registering with us!');
        
        return $mail;
    }

    /**
     * Rejected notification mail
     */
    private function toRejectedMail($notifiable)
    {
        $rejectionReason = $this->notes ?? $this->registration->rejection_reason ?? 'No specific reason provided';
        
        return (new MailMessage)
            ->subject('Update on Your Property Registration')
            ->greeting('Hello ' . ($notifiable->name ?? 'Valued Landlord') . '!')
            ->line('We have reviewed your property registration and unfortunately, we are unable to approve it at this time.')
            ->line('')
            ->line('**Reason for rejection:**')
            ->line('> ' . $rejectionReason)
            ->line('')
            ->line('**What you can do:**')
            ->line('• Review the rejection reason above')
            ->line('• Make the necessary corrections to your application')
            ->line('• Contact our estate management office for clarification')
            ->line('• Submit a new registration with the required information')
            ->line('')
            ->action('Contact Estate Management', route('contact'))
            ->line('We apologize for any inconvenience and are happy to assist you with the process.')
            ->salutation('Estate Management Team');
    }

    /**
     * Needs information notification mail
     */
    private function toNeedsInfoMail($notifiable)
    {
        $infoRequested = $this->notes ?? $this->registration->info_requested ?? 'Please provide more details about your registration.';
        
        return (new MailMessage)
            ->subject('Additional Information Required for Your Property Registration 📋')
            ->greeting('Hello ' . ($notifiable->name ?? 'Valued Landlord') . '!')
            ->line('We need some additional information to process your property registration.')
            ->line('')
            ->line('**Requested Information:**')
            ->line('> ' . $infoRequested)
            ->line('')
            ->line('Please log in to your account and update your registration with the requested information.')
            ->action('View Registration', route('admin.construction-registrations.show', $this->registration->id))
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Thank you for your cooperation!');
    }

    /**
     * Default notification mail
     */
    private function toDefaultMail($notifiable)
    {
        $statusLabel = $this->registration->status_label ?? ucfirst($this->status);
        
        $mail = (new MailMessage)
            ->subject('Update on Your Property Registration')
            ->greeting('Hello ' . ($notifiable->name ?? 'Valued Landlord') . '!')
            ->line('Your property registration status has been updated.')
            ->line('')
            ->line('**New Status:** ' . $statusLabel);
        
        if ($this->notes) {
            $mail->line('**Notes:** ' . $this->notes);
        }
        
        $mail->line('')
            ->action('View Registration', route('admin.construction-registrations.show', $this->registration->id))
            ->line('Thank you for your patience!');
        
        return $mail;
    }

    /**
     * Get the SMS representation (used by SmsService)
     */
    public function toSms($notifiable): ?string
    {
        try {
            if ($this->status === LandlordConstructionRegistration::STATUS_APPROVED) {
                return "✅ Your property registration for {$this->registration->property_name} has been APPROVED! Log in to view your property.";
            } elseif ($this->status === LandlordConstructionRegistration::STATUS_REJECTED) {
                $reason = $this->notes ?? $this->registration->rejection_reason ?? 'Please check email for details';
                $shortReason = strlen($reason) > 80 ? substr($reason, 0, 77) . '...' : $reason;
                return "❌ Your property registration has been reviewed. Reason: {$shortReason}";
            } elseif ($this->status === LandlordConstructionRegistration::STATUS_NEEDS_INFO) {
                return "📋 Additional information needed for your property registration. Please log in to update your application.";
            }
            
            $statusLabel = $this->registration->status_label ?? $this->status;
            return "📝 Your property registration status has been updated to {$statusLabel}. Check your email for details.";
        } catch (\Exception $e) {
            Log::error('Failed to generate SMS message', [
                'error' => $e->getMessage(),
                'registration_id' => $this->registration->id
            ]);
            return "Your property registration status has been updated. Please check your email for details.";
        }
    }

    /**
     * Get the array representation of the notification for database
     */
    public function toDatabase($notifiable)
    {
        $statusLabel = $this->registration->status_label ?? ucfirst($this->status);
        
        return [
            'registration_id' => $this->registration->id,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'property_name' => $this->registration->property_name,
            'plot_number' => $this->registration->plot_number,
            'notes' => $this->notes,
            'rejection_reason' => $this->registration->rejection_reason,
            'type' => 'construction_status_update',
            'message' => "Your property registration has been {$statusLabel}",
            'action_url' => route('admin.construction-registrations.show', $this->registration->id),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast representation
     */
    public function toBroadcast($notifiable)
    {
        $statusLabel = $this->registration->status_label ?? ucfirst($this->status);
        
        return new BroadcastMessage([
            'registration_id' => $this->registration->id,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'property_name' => $this->registration->property_name,
            'message' => "Your property registration has been {$statusLabel}",
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get the array representation for array channel
     */
    public function toArray($notifiable)
    {
        $statusLabel = $this->registration->status_label ?? ucfirst($this->status);
        
        return [
            'registration_id' => $this->registration->id,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'property_name' => $this->registration->property_name,
            'plot_number' => $this->registration->plot_number,
            'notes' => $this->notes,
            'message' => "Your property registration has been {$statusLabel}",
        ];
    }
}