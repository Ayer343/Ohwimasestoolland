<?php

namespace App\Notifications;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ConstructionRegistrationSubmitted extends Notification
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

    /**
     * Optional: Add mail notification if needed
     * Uncomment if you want email notifications
     */
    // public function toMail($notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('New Construction Registration - Action Required')
    //         ->greeting('Hello ' . $notifiable->name)
    //         ->line($this->registration->name . ' has submitted a new construction registration.')
    //         ->line('**Property:** ' . $this->registration->property_name)
    //         ->line('**Location:** Plot ' . $this->registration->plot_number . ', ' . $this->registration->street_name)
    //         ->action('Review Registration', route('admin.construction-registrations.show', $this->registration->id))
    //         ->line('Please review this registration and take appropriate action.');
    // }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'construction_registration_submitted',
            'registration_id' => $this->registration->id,
            'registration_type' => $this->registration->registration_type,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'plot_number' => $this->registration->plot_number ?? 'N/A',
            'street_name' => $this->registration->street_name ?? 'N/A',
            'submitted_at' => $this->registration->created_at?->toISOString(),
            'message' => 'A new construction registration requires your review',
            'action_url' => route('admin.construction-registrations.show', $this->registration->id),
            'priority' => 'high',
            'icon' => 'fas fa-hard-hat',
            'category' => 'construction_registration'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'construction_registration_submitted',
            'registration_id' => $this->registration->id,
            'property_name' => $this->registration->property_name ?? 'Unknown Property',
            'landlord_name' => $this->registration->name ?? 'Unknown Landlord',
            'message' => 'New construction registration awaiting review',
            'time_ago' => now()->diffForHumans(),
            'action_url' => route('admin.construction-registrations.show', $this->registration->id)
        ]);
    }
}