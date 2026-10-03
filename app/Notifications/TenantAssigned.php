<?php

namespace App\Notifications;

use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected PropertyUnit $unit;
    protected ?User $landlord;
    protected ?User $approvedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(PropertyUnit $unit, ?User $landlord = null, ?User $approvedBy = null)
    {
        $this->unit       = $unit;
        $this->landlord   = $landlord;
        $this->approvedBy = $approvedBy;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $monthlyRent = $this->unit->current_rent_amount ?? $this->unit->monthly_rent;

        return (new MailMessage)
            ->subject('Tenant Assignment Confirmed')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your tenant application for **' . ($this->unit->property->property_name ?? 'the property') . '** - Unit ' . $this->unit->unit_number . ' has been approved.')
            ->line('**Unit Details:**')
            ->line('- Property: ' . ($this->unit->property->property_name ?? 'N/A'))
            ->line('- Unit Number: ' . $this->unit->unit_number)
            ->line('- Monthly Rent: GHS ' . number_format((float) $monthlyRent, 2))
            ->line('- Move-in Date: ' . ($this->unit->tenant_move_in_date
                ? $this->unit->tenant_move_in_date->format('F j, Y')
                : 'To be confirmed'))
            ->line('')
            ->line('**Landlord Contact Information:**')
            ->line('- Name: '  . ($this->landlord?->name  ?? 'N/A'))
            ->line('- Email: ' . ($this->landlord?->email ?? 'N/A'))
            ->line('')
            ->line('Please contact your landlord to finalize the lease agreement.')
            ->line('Thank you for choosing us!')
            ->action('View Unit Details', route('property-units.show', $this->unit->id))
            ->line('If you have any questions, please contact support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * ✅ FIX: Added missing UI-facing fields (icon, color, category, priority,
     * action_label) so the notification card renders consistently with the
     * rest of the tenant lifecycle notifications.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        $propertyName = $this->unit->property->property_name ?? 'N/A';
        $unitNumber   = $this->unit->unit_number ?? 'N/A';
        $monthlyRent  = $this->unit->current_rent_amount ?? $this->unit->monthly_rent;
        $moveIn       = $this->unit->tenant_move_in_date
            ? $this->unit->tenant_move_in_date->format('Y-m-d')
            : null;
        $approvedByName = $this->approvedBy?->name ?? 'System';

        return [
            // ---------------------------------------------------------
            // ✅ UI-facing fields
            // ---------------------------------------------------------
            'title'        => '🏠 Tenant Assignment Confirmed',
            'message'      => sprintf(
                'Your tenant application has been approved for %s - Unit %s. Monthly rent: GHS %s.',
                $propertyName,
                $unitNumber,
                number_format((float) $monthlyRent, 2)
            ),
            'icon'         => 'fas fa-home text-success',
            'color'        => 'success',
            'category'     => 'tenant_assigned',
            'priority'     => 2,
            'action_url'   => route('property-units.show', $this->unit->id),
            'action_label' => 'View Unit Details',

            // ---------------------------------------------------------
            // Preserved original fields (do NOT remove)
            // ---------------------------------------------------------
            'unit_id'        => $this->unit->id,
            'property_id'    => $this->unit->property_id,
            'property_name'  => $propertyName,
            'unit_number'    => $unitNumber,
            'monthly_rent'   => $monthlyRent,
            'move_in_date'   => $moveIn,
            'approved_by'    => $approvedByName,
            'type'           => 'tenant_assigned',
            'timestamp'      => now()->toISOString(),
        ];
    }

    /**
     * ✅ Optional: stable DB `type` column value (instead of the FQCN).
     * Comment out if you rely on the full class name elsewhere.
     */
    public function databaseType($notifiable): string
    {
        return 'tenant_assigned';
    }
}