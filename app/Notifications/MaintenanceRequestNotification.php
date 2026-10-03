<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class MaintenanceRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The maintenance request instance.
     *
     * @var MaintenanceRequest
     */
    protected $maintenanceRequest;

    /**
     * The property unit instance.
     *
     * @var PropertyUnit
     */
    protected $unit;

    /**
     * The notification type.
     *
     * @var string
     */
    protected $type;

    /**
     * The user who triggered the notification.
     *
     * @var User
     */
    protected $triggeredBy;

    /**
     * Additional data for the notification.
     *
     * @var array
     */
    protected $additionalData;

    /**
     * The roles that should receive this notification.
     *
     * @var array|string
     */
    protected $roles;

    /**
     * Create a new notification instance.
     *
     * @param MaintenanceRequest $maintenanceRequest
     * @param PropertyUnit $unit
     * @param string $type
     * @param User|null $triggeredBy
     * @param array $additionalData
     * @param array|string|null $roles
     */
    public function __construct(
        MaintenanceRequest $maintenanceRequest,
        PropertyUnit $unit,
        string $type = 'submitted',
        ?User $triggeredBy = null,
        array $additionalData = [],
        $roles = null
    ) {
        $this->maintenanceRequest = $maintenanceRequest;
        $this->unit = $unit;
        $this->type = $type;
        $this->triggeredBy = $triggeredBy ?? auth()->user();
        $this->additionalData = $additionalData;
        $this->roles = $roles ?? $this->determineRoles($type);
    }

    /**
     * Determine which roles should receive this notification.
     *
     * @param string $type
     * @return array|string
     */
    protected function determineRoles(string $type): array|string
    {
        switch ($type) {
            case 'submitted':
            case 'cancelled':
                // Landlord and admins should be notified
                return ['landlord', 'admin', 'super-admin'];
                
            case 'status_updated':
                // Tenant and landlord should be notified
                return ['tenant', 'landlord', 'admin', 'super-admin'];
                
            case 'completed':
            case 'assigned':
                // Everyone involved
                return ['tenant', 'landlord', 'admin', 'super-admin'];
                
            default:
                return 'all';
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        // Add mail for urgent/high priority notifications
        if ($this->maintenanceRequest->priority === 'urgent' || 
            $this->maintenanceRequest->priority === 'high') {
            $channels[] = 'mail';
        }
        
        // Check user preferences
        if (method_exists($notifiable, 'notification_preferences')) {
            $preferences = $notifiable->notification_preferences ?? [];
            
            if (isset($preferences['email_enabled']) && $preferences['email_enabled']) {
                if (!in_array('mail', $channels)) {
                    $channels[] = 'mail';
                }
            }
            
            if (isset($preferences['push_enabled']) && !$preferences['push_enabled']) {
                $channels = array_diff($channels, ['broadcast']);
            }
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $message = $this->getEmailMessage($notifiable);
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting($this->getGreeting($notifiable))
            ->line($message)
            ->action($this->getActionText(), $this->getActionUrl())
            ->line($this->getAdditionalInfo())
            ->line($this->getPriorityNote())
            ->line('Thank you for using our property management system.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        $isTenant = $notifiable->isTenant();
        $isLandlord = $notifiable->isLandlord();
        $isAdmin = $notifiable->isAdmin() || $notifiable->isSuperAdmin();
        
        // Build notification data based on receiver role
        $data = [
            'type' => 'maintenance_request',
            'sub_type' => $this->type,
            'request_id' => $this->maintenanceRequest->id,
            'reference_id' => $this->maintenanceRequest->reference_id,
            'unit_id' => $this->unit->id,
            'unit_number' => $this->unit->unit_number,
            'unit_name' => $this->unit->unit_name,
            'property_id' => $this->unit->property_id,
            'property_name' => $this->unit->property->property_name ?? 'N/A',
            'title' => $this->maintenanceRequest->title,
            'description' => $this->maintenanceRequest->description,
            'priority' => $this->maintenanceRequest->priority,
            'priority_level' => $this->getPriorityLevel($this->maintenanceRequest->priority),
            'category' => $this->maintenanceRequest->category,
            'status' => $this->maintenanceRequest->status,
            'landlord_id' => $this->maintenanceRequest->landlord_id,
            'tenant_id' => $this->maintenanceRequest->tenant_id,
            'created_at' => $this->maintenanceRequest->created_at->toDateTimeString(),
            'has_photos' => !empty($this->maintenanceRequest->photos),
            'action_url' => $this->getActionUrl(),
            'roles' => $this->roles,
        ];

        // Add role-specific data
        if ($isTenant) {
            $data['message'] = $this->getTenantMessage();
            $data['icon'] = 'fas fa-tools text-warning';
        } elseif ($isLandlord) {
            $data['message'] = $this->getLandlordMessage();
            $data['icon'] = 'fas fa-building text-primary';
            $data['tenant_name'] = $this->getTenantName();
            $data['tenant_email'] = $this->getTenantEmail();
            $data['tenant_phone'] = $this->getTenantPhone();
        } elseif ($isAdmin) {
            $data['message'] = $this->getAdminMessage();
            $data['icon'] = 'fas fa-shield-alt text-danger';
            $data['landlord_name'] = $this->getLandlordName();
            $data['tenant_name'] = $this->getTenantName();
        } else {
            $data['message'] = $this->getGeneralMessage();
            $data['icon'] = 'fas fa-bell text-primary';
        }

        // Add additional data
        if (!empty($this->additionalData)) {
            $data = array_merge($data, $this->additionalData);
        }

        return $data;
    }

    /**
     * Get the notification's database type.
     *
     * @return string
     */
    public function databaseType(): string
    {
        return 'maintenance_request';
    }

    /**
     * Get the notification's category for grouping.
     *
     * @return string
     */
    public function category(): string
    {
        return 'maintenance';
    }

    /**
     * Get the notification's priority (0-3).
     *
     * @return int
     */
    public function priority(): int
    {
        return match($this->maintenanceRequest->priority) {
            'urgent' => 3,
            'high' => 2,
            'medium' => 1,
            default => 0,
        };
    }

    /**
     * Get the action URL for the notification.
     *
     * @return string
     */
    protected function getActionUrl(): string
    {
        return route('property-units.maintenance-requests', $this->unit->id);
    }

    /**
     * Get the action text for the notification.
     *
     * @return string
     */
    protected function getActionText(): string
    {
        return 'View Maintenance Request';
    }

    /**
     * Get the email subject based on notification type.
     *
     * @return string
     */
    protected function getEmailSubject(): string
    {
        $subject = match($this->type) {
            'submitted' => '🔧 New Maintenance Request',
            'cancelled' => '❌ Maintenance Request Cancelled',
            'status_updated' => '📋 Maintenance Request Status Updated',
            'assigned' => '👤 Maintenance Request Assigned',
            'completed' => '✅ Maintenance Request Completed',
            default => '📢 Maintenance Request Notification',
        };

        return $subject . ' - ' . $this->maintenanceRequest->reference_id;
    }

    /**
     * Get the email message for the notification.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function getEmailMessage($notifiable): string
    {
        $isTenant = $notifiable->isTenant();
        $isLandlord = $notifiable->isLandlord();
        
        if ($isTenant) {
            return $this->getTenantMessage();
        } elseif ($isLandlord) {
            return $this->getLandlordMessage();
        } else {
            return $this->getAdminMessage();
        }
    }

    /**
     * Get the greeting for the email.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function getGreeting($notifiable): string
    {
        return 'Hello ' . ($notifiable->name ?? 'User') . '!';
    }

    /**
     * Get the email message for tenant.
     *
     * @return string
     */
    protected function getTenantMessage(): string
    {
        return match($this->type) {
            'submitted' => "Your maintenance request \"{$this->maintenanceRequest->title}\" has been submitted successfully. The landlord has been notified and will review your request shortly.",
            'cancelled' => "Your maintenance request \"{$this->maintenanceRequest->title}\" has been cancelled.",
            'status_updated' => "The status of your maintenance request \"{$this->maintenanceRequest->title}\" has been updated to " . ucfirst(str_replace('_', ' ', $this->maintenanceRequest->status)) . ".",
            'assigned' => "A technician has been assigned to your maintenance request \"{$this->maintenanceRequest->title}\".",
            'completed' => "Your maintenance request \"{$this->maintenanceRequest->title}\" has been marked as completed. Your unit is now available.",
            default => "There has been an update to your maintenance request \"{$this->maintenanceRequest->title}\".",
        };
    }

    /**
     * Get the email message for landlord.
     *
     * @return string
     */
    protected function getLandlordMessage(): string
    {
        $tenantName = $this->getTenantName();
        $unitNumber = $this->unit->unit_number;
        $propertyName = $this->unit->property->property_name ?? 'Property';
        
        return match($this->type) {
            'submitted' => "A new maintenance request has been submitted for Unit {$unitNumber} at {$propertyName} by {$tenantName}.\n\nTitle: {$this->maintenanceRequest->title}\nDescription: {$this->maintenanceRequest->description}\nPriority: " . ucfirst($this->maintenanceRequest->priority) . "\nCategory: " . ucfirst($this->maintenanceRequest->category),
            'cancelled' => "The maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been cancelled by {$tenantName}.",
            'status_updated' => "The status of maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been updated to " . ucfirst(str_replace('_', ' ', $this->maintenanceRequest->status)) . ".",
            'assigned' => "A technician has been assigned to maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber}.",
            'completed' => "Maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been marked as completed.",
            default => "There has been an update to maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber}.",
        };
    }

    /**
     * Get the email message for admin.
     *
     * @return string
     */
    protected function getAdminMessage(): string
    {
        $tenantName = $this->getTenantName();
        $landlordName = $this->getLandlordName();
        $unitNumber = $this->unit->unit_number;
        $propertyName = $this->unit->property->property_name ?? 'Property';
        
        return match($this->type) {
            'submitted' => "A new maintenance request has been submitted for Unit {$unitNumber} at {$propertyName}.\n\nTenant: {$tenantName}\nLandlord: {$landlordName}\nTitle: {$this->maintenanceRequest->title}\nPriority: " . ucfirst($this->maintenanceRequest->priority) . "\nCategory: " . ucfirst($this->maintenanceRequest->category),
            'cancelled' => "Maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been cancelled.",
            'status_updated' => "Maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been updated to " . ucfirst(str_replace('_', ' ', $this->maintenanceRequest->status)) . ".",
            default => "Maintenance request \"{$this->maintenanceRequest->title}\" for Unit {$unitNumber} has been updated.",
        };
    }

    /**
     * Get general email message.
     *
     * @return string
     */
    protected function getGeneralMessage(): string
    {
        return "Maintenance request \"{$this->maintenanceRequest->title}\" has been updated. Current status: " . ucfirst(str_replace('_', ' ', $this->maintenanceRequest->status));
    }

    /**
     * Get additional info for email.
     *
     * @return string
     */
    protected function getAdditionalInfo(): string
    {
        $info = [];
        
        if ($this->maintenanceRequest->estimated_completion_date) {
            $info[] = "📅 Estimated Completion: " . $this->maintenanceRequest->estimated_completion_date->format('M d, Y');
        }
        
        if ($this->maintenanceRequest->cost_estimate) {
            $info[] = "💰 Estimated Cost: $" . number_format($this->maintenanceRequest->cost_estimate, 2);
        }
        
        if ($this->maintenanceRequest->notes) {
            $info[] = "📝 Notes: " . $this->maintenanceRequest->notes;
        }
        
        return empty($info) ? '' : "\n\n" . implode("\n", $info);
    }

    /**
     * Get priority note for email.
     *
     * @return string
     */
    protected function getPriorityNote(): string
    {
        if ($this->maintenanceRequest->priority === 'urgent') {
            return "⚠️ This is an URGENT request that requires immediate attention!";
        } elseif ($this->maintenanceRequest->priority === 'high') {
            return "❗ This is a HIGH priority request that requires prompt attention.";
        }
        
        return '';
    }

    /**
     * Get tenant name.
     *
     * @return string
     */
    protected function getTenantName(): string
    {
        if ($this->maintenanceRequest->tenant_id) {
            $tenant = User::find($this->maintenanceRequest->tenant_id);
            return $tenant?->name ?? 'Unknown Tenant';
        }
        
        if ($this->unit->tenant_id) {
            $tenant = User::find($this->unit->tenant_id);
            return $tenant?->name ?? 'Unknown Tenant';
        }
        
        return 'No tenant assigned';
    }

    /**
     * Get tenant email.
     *
     * @return string|null
     */
    protected function getTenantEmail(): ?string
    {
        if ($this->maintenanceRequest->tenant_id) {
            $tenant = User::find($this->maintenanceRequest->tenant_id);
            return $tenant?->email;
        }
        
        if ($this->unit->tenant_id) {
            $tenant = User::find($this->unit->tenant_id);
            return $tenant?->email;
        }
        
        return null;
    }

    /**
     * Get tenant phone.
     *
     * @return string|null
     */
    protected function getTenantPhone(): ?string
    {
        if ($this->maintenanceRequest->tenant_id) {
            $tenant = User::find($this->maintenanceRequest->tenant_id);
            return $tenant?->phone;
        }
        
        if ($this->unit->tenant_id) {
            $tenant = User::find($this->unit->tenant_id);
            return $tenant?->phone;
        }
        
        return null;
    }

    /**
     * Get landlord name.
     *
     * @return string
     */
    protected function getLandlordName(): string
    {
        if ($this->maintenanceRequest->landlord_id) {
            $landlord = User::find($this->maintenanceRequest->landlord_id);
            return $landlord?->name ?? 'Unknown Landlord';
        }
        
        if ($this->unit->property->landlord_id) {
            $landlord = User::find($this->unit->property->landlord_id);
            return $landlord?->name ?? 'Unknown Landlord';
        }
        
        return 'No landlord assigned';
    }

    /**
     * Get priority level for display.
     *
     * @param string $priority
     * @return string
     */
    protected function getPriorityLevel(string $priority): string
    {
        return match($priority) {
            'urgent' => 'Emergency',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Normal',
        };
    }
}