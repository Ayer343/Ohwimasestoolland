<?php

namespace App\Notifications;

use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected PropertyUnit $unit;
    protected string $tenantType;
    protected User $approvedBy;
    protected bool $invitationSent;
    protected ?string $approvalNotes;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        PropertyUnit $unit,
        string $tenantType,
        User $approvedBy,
        bool $invitationSent = false,
        ?string $approvalNotes = null
    ) {
        $this->unit           = $unit;
        $this->tenantType     = $tenantType;
        $this->approvedBy     = $approvedBy;
        $this->invitationSent = $invitationSent;
        $this->approvalNotes  = $approvalNotes;
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
        $type = $this->tenantType === 'existing' ? 'Existing Tenant' : 'New Tenant';

        $mail = (new MailMessage)
            ->subject('Tenant Approved - ' . $this->unit->property->property_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A tenant has been approved for your property.')
            ->line('**Property:** ' . $this->unit->property->property_name)
            ->line('**Unit:** ' . $this->unit->unit_number)
            ->line('**Tenant Type:** ' . $type)
            ->line('**Tenant Name:** ' . ($this->unit->tenant ? $this->unit->tenant->name : 'New Tenant'))
            ->line('**Approved By:** ' . $this->approvedBy->name)
            ->line('**Final Rent:** GHS ' . number_format((float) $this->unit->current_rent_amount, 2))
            ->line('**Move-in Date:** ' . ($this->unit->tenant_move_in_date
                ? $this->unit->tenant_move_in_date->format('M d, Y')
                : 'Not specified'));

        if ($this->invitationSent) {
            $mail->line('✅ **Invitation has been sent to the tenant.**');
        }

        if ($this->approvalNotes) {
            $mail->line('**Approval Notes:** ' . $this->approvalNotes);
        }

        return $mail->action('View Unit', route('property-units.show', $this->unit->id))
            ->line('The unit has been marked as occupied.');
    }

    /**
     * Get the array representation of the notification.
     *
     * ✅ FIX: Added UI-facing fields (title, message, icon, color, category,
     * priority, action_url, action_label) so the admin / landlord notification
     * card renders properly instead of falling back to "Notification / General".
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // ----- Derived values used in UI + message -----
        $propertyName = $this->unit->property->property_name ?? 'a property';
        $unitNumber   = $this->unit->unit_number ?? 'N/A';
        $tenantName   = $this->unit->tenant->name ?? 'New Tenant';
        $approvedBy   = $this->approvedBy->name ?? 'Admin';
        $typeLabel    = $this->tenantType === 'existing' ? 'existing tenant' : 'new tenant';

        $finalRent = $this->unit->current_rent_amount ?? 0;
        $moveIn    = $this->unit->tenant_move_in_date
            ? $this->unit->tenant_move_in_date->format('M d, Y')
            : 'Not specified';

        // ----- Build a readable message -----
        $message = sprintf(
            '%s approved %s (%s) for unit %s at %s. Final rent: GHS %s. Move-in: %s.',
            $approvedBy,
            $tenantName,
            $typeLabel,
            $unitNumber,
            $propertyName,
            number_format((float) $finalRent, 2),
            $moveIn
        );

        if ($this->invitationSent) {
            $message .= ' An invitation has been sent to the tenant.';
        }

        if ($this->approvalNotes) {
            $message .= ' Notes: ' . $this->approvalNotes;
        }

        return [
            // ---------------------------------------------------------
            // ✅ UI-facing fields
            // ---------------------------------------------------------
            'title'        => '✅ Tenant Approved',
            'message'      => $message,
            'icon'         => 'fas fa-user-check text-success',
            'color'        => 'success',
            'category'     => 'tenant_approved',
            'priority'     => 2,
            'action_url'   => route('property-units.show', $this->unit->id),
            'action_label' => 'View Unit',

            // ---------------------------------------------------------
            // Preserved original fields (do NOT remove)
            // ---------------------------------------------------------
            'unit_id'         => $this->unit->id,
            'property_id'     => $this->unit->property_id,
            'property_name'   => $this->unit->property->property_name,
            'unit_number'     => $this->unit->unit_number,
            'tenant_type'     => $this->tenantType,
            'tenant_name'     => $tenantName,
            'approved_by'     => [
                'id'    => $this->approvedBy->id,
                'name'  => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ],
            'final_rent'      => $this->unit->current_rent_amount,
            'move_in_date'    => $this->unit->tenant_move_in_date?->format('Y-m-d'),
            'invitation_sent' => $this->invitationSent,
            'approval_notes'  => $this->approvalNotes,
            'type'            => 'tenant_approved',
            'timestamp'       => now()->toISOString(),
        ];
    }

    /**
     * ✅ Optional: stable DB `type` column value (instead of the FQCN).
     * Comment out if you rely on the full class name elsewhere.
     */
    public function databaseType(object $notifiable): string
    {
        return 'tenant_approved';
    }
}