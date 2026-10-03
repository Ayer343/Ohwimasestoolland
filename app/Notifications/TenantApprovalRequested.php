<?php

namespace App\Notifications;

use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected PropertyUnit $unit;
    protected string $tenantType;
    protected string $tenantName;
    protected User $requestedBy;
    protected ?string $notes;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        PropertyUnit $unit,
        string $tenantType,
        string $tenantName,
        User $requestedBy,
        ?string $notes = null
    ) {
        $this->unit        = $unit;
        $this->tenantType  = $tenantType;
        $this->tenantName  = $tenantName;
        $this->requestedBy = $requestedBy;
        $this->notes       = $notes;
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
        $type = $this->tenantType === 'existing' ? 'Existing Tenant' : 'New Tenant Application';

        return (new MailMessage)
            ->subject('Tenant Approval Request - ' . $this->unit->property->property_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new tenant approval request requires your attention.')
            ->line('**Property:** ' . $this->unit->property->property_name)
            ->line('**Unit:** ' . $this->unit->unit_number)
            ->line('**Tenant Type:** ' . $type)
            ->line('**Tenant Name:** ' . $this->tenantName)
            ->line('**Requested By:** ' . $this->requestedBy->name)
            ->line('**Proposed Rent:** GHS ' . number_format((float) $this->unit->proposed_rent, 2))
            ->line('**Move-in Date:** ' . ($this->unit->tenant_move_in_date
                ? $this->unit->tenant_move_in_date->format('M d, Y')
                : 'Not specified'))
            ->when($this->notes, function ($mail) {
                return $mail->line('**Notes:** ' . $this->notes);
            })
            ->action('Review Request', route('property-units.pending-approvals'))
            ->line('Please review this request and take appropriate action.');
    }

    /**
     * Get the array representation of the notification.
     *
     * ✅ FIX: Added UI-facing fields (title, message, icon, color, category,
     * priority, action_url, action_label) so the admin notification card can
     * render a proper title/body/icon instead of falling back to a generic
     * "Notification / General" label.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // ----- Derived values used in the UI + fallbacks -----
        $propertyName = $this->unit->property->property_name ?? 'a property';
        $unitNumber   = $this->unit->unit_number ?? 'N/A';
        $landlordName = $this->requestedBy->name ?? 'The landlord';
        $tenantName   = $this->tenantName ?? 'a tenant';
        $typeLabel    = $this->tenantType === 'existing'
            ? 'existing tenant'
            : 'new tenant';

        $proposedRent = $this->unit->proposed_rent;
        $moveInDate   = $this->unit->tenant_move_in_date
            ? $this->unit->tenant_move_in_date->format('M d, Y')
            : 'Not specified';

        return [
            // ---------------------------------------------------------
            // ✅ UI-facing fields (what your notification card renders)
            // ---------------------------------------------------------
            'title'        => '🔔 Tenant Approval Required',
            'message'      => sprintf(
                '%s has requested approval to assign %s (%s) to unit %s at %s. Proposed rent: GHS %s. Move-in: %s.',
                $landlordName,
                $tenantName,
                $typeLabel,
                $unitNumber,
                $propertyName,
                $proposedRent !== null ? number_format((float) $proposedRent, 2) : '0.00',
                $moveInDate
            ),
            'icon'         => 'fas fa-user-clock text-warning',
            'color'        => 'warning',
            'category'     => 'tenant_approval_requested',
            'priority'     => 2,
            'action_url'   => route('property-units.pending-approvals'),
            'action_label' => 'Review Request',

            // ---------------------------------------------------------
            // Preserved original fields (do NOT remove — existing
            // consumers, emails, and pending-approvals page rely on them)
            // ---------------------------------------------------------
            'unit_id'       => $this->unit->id,
            'property_id'   => $this->unit->property_id,
            'property_name' => $this->unit->property->property_name,
            'unit_number'   => $this->unit->unit_number,
            'tenant_type'   => $this->tenantType,
            'tenant_name'   => $this->tenantName,
            'requested_by'  => [
                'id'    => $this->requestedBy->id,
                'name'  => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ],
            'proposed_rent' => $this->unit->proposed_rent,
            'move_in_date'  => $this->unit->tenant_move_in_date?->format('Y-m-d'),
            'notes'         => $this->notes,
            'type'          => 'tenant_approval_requested',
            'timestamp'     => now()->toISOString(),
        ];
    }

    /**
     * ✅ Optional: make the DB `type` column stable and readable
     * instead of the FQCN. Comment out if you rely on the FQCN elsewhere.
     */
    public function databaseType(object $notifiable): string
    {
        return 'tenant_approval_requested';
    }
}