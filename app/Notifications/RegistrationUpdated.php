<?php

namespace App\Notifications;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class RegistrationUpdated extends Notification
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
            'type' => 'registration_updated',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'status' => $this->registration->status,
            'updated_at' => now()->toISOString(),
            'message' => 'A registration has been updated and requires re-review',
            'action_url' => route('admin.construction-registrations.show', $this->registration->id),
            'priority' => 'medium',
            'icon' => 'fas fa-edit',
            'category' => 'registration_update'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'registration_updated',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'message' => 'Registration updated and needs re-review',
            'time_ago' => now()->diffForHumans(),
            'action_url' => route('admin.construction-registrations.show', $this->registration->id)
        ]);
    }
}