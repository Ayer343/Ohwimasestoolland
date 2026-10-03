<?php

namespace App\Notifications;

use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantAssignmentRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected $unit;
    protected $requestedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(PropertyUnit $unit, User $requestedBy)
    {
        $this->unit = $unit;
        $this->requestedBy = $requestedBy;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Unit Assignment Request - ' . $this->unit->property->property_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A unit assignment has been requested for you.')
            ->line('**Property:** ' . $this->unit->property->property_name)
            ->line('**Unit:** ' . $this->unit->unit_number)
            ->line('**Requested By:** ' . $this->requestedBy->name)
            ->line('**Proposed Rent:** GHS ' . number_format($this->unit->proposed_rent, 2))
            ->line('**Proposed Move-in Date:** ' . ($this->unit->tenant_move_in_date ? $this->unit->tenant_move_in_date->format('M d, Y') : 'Not specified'))
            ->action('View Request', route('property-units.show', $this->unit->id))
            ->line('This request is pending admin approval. You will be notified once it is approved.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'unit_id' => $this->unit->id,
            'property_id' => $this->unit->property_id,
            'property_name' => $this->unit->property->property_name,
            'unit_number' => $this->unit->unit_number,
            'requested_by' => [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ],
            'proposed_rent' => $this->unit->proposed_rent,
            'move_in_date' => $this->unit->tenant_move_in_date?->format('Y-m-d'),
            'type' => 'tenant_assignment_requested',
            'timestamp' => now()->toISOString(),
        ];
    }
}