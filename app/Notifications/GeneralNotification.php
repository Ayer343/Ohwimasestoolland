<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $title;
    public $message;
    public $icon;
    public $category;
    public $actionUrl;
    public $priority;
    public $data;
    public $roles; // Role targeting support
    public $imageUrl; // Optional image URL for rich notifications

    /**
     * Create a new notification instance.
     *
     * @param string $title Notification title
     * @param string $message Notification message body
     * @param string $icon FontAwesome icon class (e.g., 'fas fa-bell')
     * @param string $category Category for filtering (e.g., 'system', 'payment', 'property')
     * @param string|null $actionUrl URL to redirect when notification is clicked
     * @param int $priority Priority level (0=Normal, 1=Low, 2=Medium, 3=High)
     * @param array $data Additional custom data
     * @param string|array|null $roles Role(s) that can see this notification (null=all roles, 'all'=all roles, 'landlord'=single role, ['admin','landlord']=multiple roles)
     * @param string|null $imageUrl Optional image URL for rich notifications
     */
    public function __construct(
        string $title,
        string $message,
        string $icon = 'fas fa-bell',
        string $category = 'general',
        ?string $actionUrl = null,
        int $priority = 1,
        array $data = [],
        $roles = null,
        ?string $imageUrl = null
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->icon = $icon;
        $this->category = $category;
        $this->actionUrl = $actionUrl;
        $this->priority = $priority;
        $this->data = $data;
        $this->roles = $this->normalizeRoles($roles);
        $this->imageUrl = $imageUrl;
    }

    /**
     * Normalize roles to consistent array format.
     */
    protected function normalizeRoles($roles): ?array
    {
        if ($roles === null) {
            return null;
        }

        if ($roles === 'all') {
            return ['all'];
        }

        if (is_string($roles)) {
            return [$roles];
        }

        if (is_array($roles)) {
            return $roles;
        }

        return null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * ✅ FIX: Removed 'nexmo' channel. Laravel's built-in SMS drivers
     *         (nexmo/vonage/twilio) require additional packages that are
     *         not installed in this project. All SMS is handled through
     *         the application's own App\Services\SmsService instead.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Add mail channel for medium and high priority notifications.
        if ($this->priority >= 2) {
            $channels[] = 'mail';
        }

        // ❌ SMS intentionally NOT added here.
        //    Priority >= 3 notifications still get delivered in-app + by mail.
        //    If SMS is desired for a given notification, dispatch it directly
        //    via App\Services\SmsService where the notification is triggered.

        return $channels;
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray($notifiable): array
    {
        $notificationData = [
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'category' => $this->category,
            'action_url' => $this->actionUrl,
            'priority' => $this->priority,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];

        if ($this->roles !== null) {
            $notificationData['roles'] = $this->roles;
        }

        if ($this->imageUrl !== null) {
            $notificationData['image_url'] = $this->imageUrl;
        }

        return $notificationData;
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'category' => $this->category,
            'action_url' => $this->actionUrl,
            'priority' => $this->priority,
            'image_url' => $this->imageUrl,
            'timestamp' => now()->toISOString(),
            'time_ago' => now()->diffForHumans(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $mailMessage = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->title)
            ->greeting('Hello ' . ($notifiable->name ?? 'User') . '!')
            ->line($this->message)
            ->action('View Details', $this->actionUrl ?? url('/'))
            ->line('Thank you for using our application!');

        if ($this->priority >= 2) {
            $mailMessage->level('urgent');
        }

        return $mailMessage;
    }

    /**
     * Determine if this notification is visible for a specific role.
     */
    public function isVisibleForRole(?string $roleSlug): bool
    {
        if ($this->roles === null) {
            return true;
        }

        if (in_array('all', $this->roles)) {
            return true;
        }

        if (!$roleSlug) {
            return false;
        }

        return in_array($roleSlug, $this->roles);
    }

    /**
     * Get the priority label for display.
     */
    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            3 => 'High',
            2 => 'Medium',
            1 => 'Low',
            default => 'Normal',
        };
    }

    /**
     * Get the priority color for UI.
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            3 => 'danger',
            2 => 'warning',
            1 => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get the targeted roles as a string for display.
     */
    public function getTargetedRolesString(): string
    {
        if ($this->roles === null || in_array('all', $this->roles)) {
            return 'All Roles';
        }

        $roleNames = array_map(function($role) {
            return ucfirst(str_replace('-', ' ', $role));
        }, $this->roles);

        return implode(', ', $roleNames);
    }
}