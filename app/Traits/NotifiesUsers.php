<?php

namespace App\Traits;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Notifications\PropertyRegisteredNotification;
use App\Notifications\PaymentNotification;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

trait NotifiesUsers
{
    /**
     * Send a general notification to a user
     */
    public function notifyUser(User $user, $title, $message, $icon = 'fas fa-bell', $category = 'general', $actionUrl = null, $priority = 0)
    {
        try {
            $user->notify(new GeneralNotification($title, $message, $icon, $category, $actionUrl, $priority));
            
            Log::info('Notification sent to user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'title' => $title,
                'category' => $category,
                'priority' => $priority
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'title' => $title
            ]);
            
            throw $e;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function notifyUsers($users, $title, $message, $icon = 'fas fa-bell', $category = 'general', $actionUrl = null, $priority = 0)
    {
        try {
            Notification::send($users, new GeneralNotification($title, $message, $icon, $category, $actionUrl, $priority));
            
            Log::info('Notification sent to multiple users', [
                'user_count' => count($users),
                'title' => $title,
                'category' => $category
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send notifications to multiple users: ' . $e->getMessage(), [
                'title' => $title
            ]);
            
            throw $e;
        }
    }

    /**
     * ✅ NEW: Send urgent notification (high priority)
     */
    public function notifyUrgent(User $user, $title, $message, $icon = 'fas fa-exclamation-triangle text-danger')
    {
        return $this->notifyUser(
            $user,
            $title,
            $message,
            $icon,
            'urgent',
            null,
            3 // High priority
        );
    }

    /**
     * ✅ NEW: Send success notification
     */
    public function notifySuccess(User $user, $title, $message, $actionUrl = null)
    {
        return $this->notifyUser(
            $user,
            $title,
            $message,
            'fas fa-check-circle text-success',
            'success',
            $actionUrl,
            1 // Low priority
        );
    }

    /**
     * ✅ NEW: Send warning notification
     */
    public function notifyWarning(User $user, $title, $message, $actionUrl = null)
    {
        return $this->notifyUser(
            $user,
            $title,
            $message,
            'fas fa-exclamation-triangle text-warning',
            'warning',
            $actionUrl,
            2 // Medium priority
        );
    }

    /**
     * ✅ NEW: Send info notification
     */
    public function notifyInfo(User $user, $title, $message, $actionUrl = null)
    {
        return $this->notifyUser(
            $user,
            $title,
            $message,
            'fas fa-info-circle text-info',
            'info',
            $actionUrl,
            1 // Low priority
        );
    }

    /**
     * ✅ NEW: Clear notification for a user
     */
    public function clearNotificationForUser(User $user, ?string $category = null, ?string $action = null): bool
    {
        try {
            $query = $user->notifications();
            
            if ($category) {
                $query->where('data->category', $category);
            }
            
            if ($action) {
                $notifications = $query->where('read_at', null)->get();
                $clearedCount = 0;
                
                foreach ($notifications as $notification) {
                    $data = $notification->data ?? [];
                    if (isset($data['action']) && $data['action'] === $action) {
                        $notification->markAsRead();
                        $clearedCount++;
                    }
                }
                
                Log::info("Cleared {$clearedCount} notifications for user", [
                    'user_id' => $user->id,
                    'category' => $category,
                    'action' => $action,
                    'cleared_count' => $clearedCount
                ]);
                
                return $clearedCount > 0;
            }
            
            // Mark all unread notifications as read
            $unreadNotifications = $query->where('read_at', null)->get();
            $clearedCount = 0;
            
            foreach ($unreadNotifications as $notification) {
                $notification->markAsRead();
                $clearedCount++;
            }
            
            Log::info("Marked {$clearedCount} notifications as read for user", [
                'user_id' => $user->id,
                'category' => $category
            ]);
            
            return $clearedCount > 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to clear notifications: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NEW: Get user's unread notifications count
     */
    public function getUserUnreadCount(User $user, ?string $category = null): int
    {
        try {
            $query = $user->notifications()->where('read_at', null);
            
            if ($category) {
                $query->where('data->category', $category);
            }
            
            return $query->count();
            
        } catch (\Exception $e) {
            Log::error('Failed to get unread notification count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ NEW: Get user's notifications
     */
    public function getUserNotifications(User $user, bool $unreadOnly = false, ?string $category = null, int $limit = 50)
    {
        try {
            $query = $user->notifications()
                ->orderBy('created_at', 'desc');
            
            if ($unreadOnly) {
                $query->where('read_at', null);
            }
            
            if ($category) {
                $query->where('data->category', $category);
            }
            
            return $query->limit($limit)->get();
            
        } catch (\Exception $e) {
            Log::error('Failed to get user notifications: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * ✅ NEW: Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId, ?User $user = null): bool
    {
        try {
            $query = \Illuminate\Notifications\DatabaseNotification::where('id', $notificationId);
            
            if ($user) {
                $query->where('notifiable_id', $user->id)
                      ->where('notifiable_type', get_class($user));
            }
            
            $notification = $query->first();
            
            if ($notification) {
                $notification->markAsRead();
                
                Log::info('Notification marked as read', [
                    'notification_id' => $notificationId,
                    'user_id' => $user ? $user->id : 'system'
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NEW: Mark all notifications as read for a user
     */
    public function markAllNotificationsAsRead(User $user, ?string $category = null): bool
    {
        try {
            $query = $user->notifications()->where('read_at', null);
            
            if ($category) {
                $query->where('data->category', $category);
            }
            
            $notifications = $query->get();
            $markedCount = 0;
            
            foreach ($notifications as $notification) {
                $notification->markAsRead();
                $markedCount++;
            }
            
            Log::info("Marked {$markedCount} notifications as read for user", [
                'user_id' => $user->id,
                'category' => $category,
                'marked_count' => $markedCount
            ]);
            
            return $markedCount > 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send property registration notification
     */
    public function notifyPropertyRegistration($property, $agent = null)
    {
        $notification = new PropertyRegisteredNotification($property, $agent);
        
        // Notify admins and super admins
        $admins = User::whereIn('type', [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADMIN
        ])->get();
        
        Notification::send($admins, $notification);
        
        // Also notify the agent if exists
        if ($agent) {
            $agent->notify($notification);
        }
        
        // Notify property owner if exists
        if ($property->landlord) {
            $property->landlord->notify($notification);
        }
        
        Log::info('Property registration notification sent', [
            'property_id' => $property->id,
            'property_name' => $property->name,
            'admins_notified' => $admins->count(),
            'agent_notified' => $agent ? 'Yes' : 'No',
            'landlord_notified' => $property->landlord ? 'Yes' : 'No'
        ]);
    }

    /**
     * Send payment notification to tenant and landlord
     */
    public function notifyPayment($payment, $tenant, $landlord)
    {
        // Notify tenant
        if ($tenant) {
            $tenant->notify(new PaymentNotification($payment, 'tenant'));
        }
        
        // Notify landlord
        if ($landlord) {
            $landlord->notify(new PaymentNotification($payment, 'landlord'));
        }
        
        // Notify admins for large payments
        if ($payment->amount >= 1000) { // GH₵1000 or more
            $admins = User::whereIn('type', [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADMIN
            ])->get();
            
            Notification::send($admins, new PaymentNotification($payment, 'admin'));
        }
        
        Log::info('Payment notification sent', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'tenant_notified' => $tenant ? 'Yes' : 'No',
            'landlord_notified' => $landlord ? 'Yes' : 'No',
            'admins_notified' => $payment->amount >= 1000 ? 'Yes' : 'No'
        ]);
    }

    /**
     * Send system alert to specific user types
     */
    public function notifySystemAlert($alertType, $message, $severity = 'info', $userTypes = null)
    {
        $notification = new SystemAlertNotification($alertType, $message, $severity);
        
        $query = User::query();
        
        // Filter by user type if specified
        if ($userTypes) {
            $query->whereIn('type', $userTypes);
        }
        
        // Only send to active users
        $query->where('status', User::STATUS_ACTIVE);
        
        $users = $query->get();
        
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
        
        Log::info('System alert notification sent', [
            'alert_type' => $alertType,
            'severity' => $severity,
            'users_notified' => $users->count(),
            'user_types' => $userTypes ? implode(', ', $userTypes) : 'All'
        ]);
    }

    /**
     * Send notification to all users of a specific type
     */
    public function notifyUserType($userType, $title, $message, $icon = 'fas fa-bell', $actionUrl = null)
    {
        $users = User::where('type', $userType)
                    ->where('status', User::STATUS_ACTIVE)
                    ->get();
        
        if ($users->isNotEmpty()) {
            $this->notifyUsers($users, $title, $message, $icon, strtolower(User::getTypeName($userType)), $actionUrl);
        }
        
        Log::info('Notification sent to user type', [
            'user_type' => $userType,
            'title' => $title,
            'users_notified' => $users->count()
        ]);
    }
    
    /**
     * Send field agent notification
     */
    public function notifyFieldAgent($agent, $title, $message, $actionUrl = null)
    {
        if ($agent && $agent->isFieldAgent()) {
            $this->notifyUser($agent, $title, $message, 'fas fa-user-tie', 'field_agent', $actionUrl, 1);
            
            Log::info('Field agent notification sent', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'title' => $title
            ]);
        }
    }
    
    /**
     * Send landlord notification
     */
    public function notifyLandlord($landlord, $title, $message, $actionUrl = null)
    {
        if ($landlord && $landlord->isLandlord()) {
            $this->notifyUser($landlord, $title, $message, 'fas fa-building', 'landlord', $actionUrl);
            
            Log::info('Landlord notification sent', [
                'landlord_id' => $landlord->id,
                'landlord_name' => $landlord->name,
                'title' => $title
            ]);
        }
    }
    
    /**
     * Send tenant notification
     */
    public function notifyTenant($tenant, $title, $message, $actionUrl = null)
    {
        if ($tenant && $tenant->isTenant()) {
            $this->notifyUser($tenant, $title, $message, 'fas fa-users', 'tenant', $actionUrl);
            
            Log::info('Tenant notification sent', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'title' => $title
            ]);
        }
    }
    
    /**
     * Broadcast important system notifications
     */
    public function broadcastSystemNotification($title, $message, $severity = 'info')
    {
        // Send to all admins and developers
        $this->notifySystemAlert('system', $message, $severity, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADMIN,
            User::TYPE_DEVELOPER
        ]);
        
        // Also log it for audit
        Log::{$severity}("System Broadcast: {$title} - {$message}");
    }

    /**
     * ✅ NEW: Get user's notification summary
     */
    public function getUserNotificationSummary(User $user): array
    {
        try {
            $allNotifications = $user->notifications()->count();
            $unreadNotifications = $user->notifications()->where('read_at', null)->count();
            $recentNotifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data ?? [];
                    return [
                        'id' => $notification->id,
                        'title' => $data['title'] ?? 'Notification',
                        'message' => $data['message'] ?? '',
                        'icon' => $data['icon'] ?? 'fas fa-bell',
                        'category' => $data['category'] ?? 'general',
                        'priority' => $data['priority'] ?? 0,
                        'action_url' => $data['action_url'] ?? null,
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at,
                        'is_read' => !is_null($notification->read_at),
                        'time_ago' => $notification->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

            return [
                'total' => $allNotifications,
                'unread' => $unreadNotifications,
                'recent' => $recentNotifications,
                'has_notifications' => $allNotifications > 0,
                'has_unread' => $unreadNotifications > 0,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get notification summary: ' . $e->getMessage());
            return [
                'total' => 0,
                'unread' => 0,
                'recent' => [],
                'has_notifications' => false,
                'has_unread' => false,
            ];
        }
    }

    /**
     * ✅ NEW: Send notification with data array (for SystemSettingController)
     */
    public function notifyUserWithData(User $user, array $notificationData)
    {
        $title = $notificationData['title'] ?? 'Notification';
        $message = $notificationData['message'] ?? '';
        $icon = $notificationData['icon'] ?? 'fas fa-bell';
        $category = $notificationData['category'] ?? 'general';
        $actionUrl = $notificationData['action_url'] ?? null;
        $priority = $notificationData['priority'] ?? 0;
        $data = $notificationData['data'] ?? [];

        return $this->notifyUser(
            $user,
            $title,
            $message,
            $icon,
            $category,
            $actionUrl,
            $priority
        );
    }
}