<?php
// app/Notifications/PropertyUnitsCreatedNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PropertyUnitsCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $property;

    public function __construct($property)
    {
        $this->property = $property;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail($notifiable)
    {
        $unitCount = $this->property->propertyUnits()->count();
        
        return (new MailMessage)
            ->subject("✅ Property Units Created Successfully - {$this->property->property_name}")
            ->greeting("Congratulations {$notifiable->name}!")
            ->line("You have successfully created {$unitCount} property unit(s) for:")
            ->line("**Property:** {$this->property->property_name}")
            ->line("Your tenants can now access their portals!")
            ->line("What's next?")
            ->line("✓ Tenants can log in and view their units")
            ->line("✓ You can now track rent payments")
            ->line("✓ Maintenance requests can be submitted")
            ->action('View Your Property', route('landlord.properties.show', $this->property->id))
            ->line("Thank you for completing your property setup!");
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'property_units_created',
            'category' => 'property_management',
            'priority' => 1,
            'title' => 'Property Units Created Successfully',
            'message' => "You've successfully created property units for '{$this->property->property_name}'. Your tenants can now access their portals.",
            'property_id' => $this->property->id,
            'property_name' => $this->property->property_name,
            'unit_count' => $this->property->propertyUnits()->count(),
            'icon' => 'fas fa-check-circle',
            'icon_color' => '#10B981',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'type' => 'property_units_created',
            'title' => 'Property Units Created',
            'message' => "Units created for {$this->property->property_name}",
        ]);
    }
}