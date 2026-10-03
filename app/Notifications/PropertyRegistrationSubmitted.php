<?php

namespace App\Notifications;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class PropertyRegistrationSubmitted extends Notification
{
    use Queueable;

    protected $registration;

    public function __construct(LandlordConstructionRegistration $registration)
    {
        $this->registration = $registration;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'property_registration_submitted',
            'registration_id' => $this->registration->id,
            'registration_type' => $this->registration->registration_type,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'plot_number' => $this->registration->plot_number ?? 'N/A',
            'street_name' => $this->registration->street_name ?? 'N/A',
            'has_tenants' => $this->registration->has_tenants ?? false,
            'tenant_count' => $this->registration->tenant_count ?? 0,
            'submitted_at' => $this->registration->created_at?->toISOString(),
            'message' => 'A new property registration requires your verification',
            'action_url' => route('admin.construction-registrations.show', $this->registration->id),
            'priority' => 'high',
            'icon' => 'fas fa-building',
            'category' => 'property_registration'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'property_registration_submitted',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'message' => 'New property registration awaiting verification',
            'time_ago' => now()->diffForHumans(),
            'action_url' => route('admin.construction-registrations.show', $this->registration->id)
        ]);
    }
}