<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PropertyOwnershipChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $property;
    protected $transfer;

    /**
     * Create a new notification instance.
     */
    public function __construct(Property $property, PropertyOwnershipTransfer $transfer)
    {
        $this->property = $property;
        $this->transfer = $transfer;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['mail'];
        
        if ($notifiable->phone) {
            $channels[] = 'database';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Property Ownership Changed - ' . $this->property->property_name)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('The ownership of the property **' . $this->property->property_name . '** has been transferred.')
            ->line('Property Details:')
            ->line('- Property Name: ' . $this->property->property_name)
            ->line('- Registration Number: ' . $this->property->registration_pattern)
            ->line('- Address: ' . $this->property->street_name . ', ' . $this->property->zone)
            ->line('Transfer Reference: ' . $this->transfer->document_reference)
            ->line('Transfer Date: ' . ($this->transfer->transfer_date ? $this->transfer->transfer_date->format('F j, Y') : 'N/A'))
            ->action('View Property Details', url('/properties/' . $this->property->id))
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'property_id' => $this->property->id,
            'property_name' => $this->property->property_name,
            'transfer_id' => $this->transfer->id,
            'transfer_reference' => $this->transfer->document_reference,
            'message' => 'Property ownership has been transferred for ' . $this->property->property_name,
        ];
    }
}