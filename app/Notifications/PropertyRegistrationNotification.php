<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $property;
    protected $agent;

    public function __construct($property, $agent = null)
    {
        $this->property = $property;
        $this->agent = $agent;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $agentName = $this->agent ? $this->agent->name : 'Unknown Agent';
        $propertyAddress = $this->property->address ?? 'Unknown Address';
        
        return [
            'title' => 'New Property Registered',
            'message' => "Property at {$propertyAddress} has been registered by {$agentName}",
            'property' => [
                'id' => $this->property->id,
                'address' => $propertyAddress,
                'property_type' => $this->property->property_type ?? 'residential',
            ],
            'agent' => $this->agent ? [
                'id' => $this->agent->id,
                'name' => $agentName,
            ] : null,
            'action_url' => "/properties/{$this->property->id}",
            'icon' => 'fas fa-home',
            'category' => 'properties',
            'priority' => $this->property->priority ?? 0,
            'metadata' => [
                'type' => 'property_registration',
                'property_id' => $this->property->id,
                'agent_id' => $this->agent?->id,
                'registration_date' => now()->toDateString(),
            ]
        ];
    }
}