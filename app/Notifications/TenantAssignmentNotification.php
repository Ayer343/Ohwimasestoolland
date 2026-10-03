<?php

namespace App\Notifications;

use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class TenantAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The notification payload (built by PropertyUnitController::buildTenantAssignmentPayload()).
     *
     * @var array
     */
    public array $data;

    /**
     * Create a new notification instance.
     *
     * @param  array  $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * Notifications are stored in the database by default.
     * Mail is only sent when the recipient is the tenant (or when
     * the payload explicitly requests it) to avoid spamming admins.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // Only send mail to the tenant (or if explicitly requested)
        if (($this->data['for_tenant'] ?? false) === true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $isTenant = ($this->data['for_tenant'] ?? false) === true;
        $isApproved = ($this->data['approval_status'] ?? null) === 'approved';

        $propertyName = $this->data['property_name'] ?? 'the property';
        $unitNumber   = $this->data['unit_number']   ?? 'N/A';
        $unitName     = $this->data['unit_name']     ?? null;
        $rent         = $this->data['monthly_rent']  ?? 0;
        $deposit      = $this->data['security_deposit'] ?? 0;
        $moveIn       = $this->data['move_in_date']  ?? 'Not specified';

        // ----- Subject + greeting vary by recipient -----
        if ($isTenant) {
            $subject = $isApproved
                ? 'Your tenancy has been approved'
                : 'You have been assigned to a property unit';

            $greeting = 'Hello ' . ($this->data['tenant_name'] ?? 'there') . ',';
        } else {
            $subject  = 'New tenant assigned to a property unit';
            $greeting = 'Hello ' . ($notifiable->name ?? 'Admin') . ',';
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($isTenant
                ? ($isApproved
                    ? 'Good news — your tenancy assignment has been approved.'
                    : 'A property unit has been assigned to you. Please review the details below.')
                : 'A new tenant has been assigned to a property unit. Please review the details below.')
            ->line('**Unit Details:**')
            ->line('• Unit Number: ' . $unitNumber)
            ->line('• Unit Name: ' . ($unitName ?: 'N/A'))
            ->line('• Property: ' . $propertyName)
            ->line('• Address: ' . ($this->data['property_address'] ?? 'N/A'))
            ->line('• Unit Type: ' . ($this->data['unit_type'] ?? 'N/A'))
            ->line('• Bedrooms: ' . ($this->data['bedrooms'] ?? 0))
            ->line('• Bathrooms: ' . ($this->data['bathrooms'] ?? 0));

        if (!$isTenant) {
            // Add tenant details for admins
            $mail->line('**Tenant Details:**')
                 ->line('• Name: '  . ($this->data['tenant_name']  ?? 'N/A'))
                 ->line('• Email: ' . ($this->data['tenant_email'] ?? 'N/A'))
                 ->line('• Phone: ' . ($this->data['tenant_phone'] ?? 'N/A'));
        }

        $mail->line('**Financial Details:**')
             ->line('• Monthly Rent: GHS ' . number_format((float) $rent, 2))
             ->line('• Security Deposit: GHS ' . number_format((float) $deposit, 2))
             ->line('• Move-in Date: ' . $moveIn);

        if ($this->data['requires_approval'] ?? false) {
            $mail->line('**Status:** Pending admin approval');
        } elseif ($isApproved) {
            $mail->line('**Status:** Approved');
        }

        $actionUrl   = $this->data['action_url']   ?? url('/');
        $actionLabel = $isTenant ? 'View Unit Details' : 'Review Assignment';

        $mail->action($actionLabel, $actionUrl)
             ->line('Thank you for using our platform.');

        return $mail;
    }

    /**
     * Get the array representation of the notification (stored in DB).
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        // Determine a friendly title + message for the UI
        $isTenant   = ($this->data['for_tenant'] ?? false) === true;
        $isApproved = ($this->data['approval_status'] ?? null) === 'approved';
        $requiresApproval = $this->data['requires_approval'] ?? false;

        $unitNumber   = $this->data['unit_number']   ?? 'N/A';
        $propertyName = $this->data['property_name'] ?? 'the property';

        if ($isTenant) {
            if ($isApproved) {
                $title   = 'Tenancy Approved';
                $message = "Your tenancy for unit {$unitNumber} at {$propertyName} has been approved.";
            } else {
                $title   = 'Unit Assigned';
                $message = "You have been assigned to unit {$unitNumber} at {$propertyName}.";
            }
        } else {
            if ($requiresApproval) {
                $title   = 'Tenant Assignment Pending Approval';
                $message = "A tenant has been assigned to unit {$unitNumber} at {$propertyName} and requires your approval.";
            } elseif ($isApproved) {
                $title   = 'Tenant Assignment Approved';
                $message = "The tenant assignment for unit {$unitNumber} at {$propertyName} has been approved.";
            } else {
                $title   = 'New Tenant Assigned';
                $message = "A new tenant has been assigned to unit {$unitNumber} at {$propertyName}.";
            }
        }

        return [
            // ----- Core notification fields (used by the UI) -----
            'title'         => $title,
            'message'       => $message,
            'icon'          => $isTenant ? 'fas fa-home' : 'fas fa-user-check',
            'color'         => $requiresApproval ? 'warning' : 'success',
            'action_url'    => $this->data['action_url']    ?? null,
            'action_label'  => $isTenant ? 'View Unit' : 'Review Assignment',

            // ----- Metadata (used by notification lists / deep links) -----
            'type'          => 'tenant_assignment',
            'for_tenant'    => $isTenant,
            'requires_approval' => $requiresApproval,

            // ----- Full payload (so the UI can render rich details if needed) -----
            'unit_id'          => $this->data['unit_id']          ?? null,
            'unit_number'      => $unitNumber,
            'unit_name'        => $this->data['unit_name']        ?? null,
            'unit_type'        => $this->data['unit_type']        ?? null,
            'bedrooms'         => $this->data['bedrooms']         ?? null,
            'bathrooms'        => $this->data['bathrooms']        ?? null,

            'property_id'      => $this->data['property_id']      ?? null,
            'property_name'    => $propertyName,
            'property_address' => $this->data['property_address'] ?? null,

            'tenant_id'        => $this->data['tenant_id']        ?? null,
            'tenant_name'      => $this->data['tenant_name']      ?? null,
            'tenant_email'     => $this->data['tenant_email']     ?? null,
            'tenant_phone'     => $this->data['tenant_phone']     ?? null,

            'assigned_by_id'    => $this->data['assigned_by_id']    ?? null,
            'assigned_by_name'  => $this->data['assigned_by_name']  ?? null,
            'assigned_by_email' => $this->data['assigned_by_email'] ?? null,
            'assigned_at'       => $this->data['assigned_at']       ?? null,

            'monthly_rent'      => $this->data['monthly_rent']      ?? null,
            'security_deposit'  => $this->data['security_deposit']  ?? null,
            'move_in_date'      => $this->data['move_in_date']      ?? null,
            'preferred_move_in' => $this->data['preferred_move_in'] ?? null,
            'preferred_rent'    => $this->data['preferred_rent']    ?? null,

            'status'            => $this->data['status']            ?? null,
            'approval_status'   => $this->data['approval_status']   ?? null,
            'created_at'        => Carbon::now()->toDateTimeString(),
        ];
    }

    /**
     * Get the notification's database type (optional override).
     *
     * Useful if you want the notification's DB `type` column to be
     * a stable, human-readable string instead of the FQCN.
     *
     * @return string
     */
    public function databaseType($notifiable): string
    {
        return 'tenant_assignment';
    }

    /**
     * Determine which queue the notification should be sent on.
     * (Uses the default queue if null is returned.)
     *
     * @return string|null
     */
    public function viaQueues(): array
    {
        return [
            'mail'     => 'notifications',
            'database' => 'notifications',
        ];
    }
}