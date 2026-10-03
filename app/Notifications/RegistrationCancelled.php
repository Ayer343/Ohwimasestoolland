<?php

namespace App\Notifications;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class RegistrationCancelled extends Notification
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
            'type' => 'registration_cancelled',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'cancelled_at' => now()->toISOString(),
            'message' => 'A registration has been cancelled by the landlord',
            'action_url' => route('admin.construction-registrations.show', $this->registration->id),
            'priority' => 'low',
            'icon' => 'fas fa-ban',
            'category' => 'registration_cancellation'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'registration_cancelled',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'message' => 'Registration has been cancelled',
            'time_ago' => now()->diffForHumans(),
            'action_url' => route('admin.construction-registrations.show', $this->registration->id)
        ]);
    }
}