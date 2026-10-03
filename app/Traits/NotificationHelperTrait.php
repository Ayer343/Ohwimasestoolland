<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

trait NotificationHelperTrait
{
    /**
     * Send notification to a user.
     *
     * @param User $user
     * @param $notification
     * @return void
     */
    protected function sendNotification($user, $notification)
    {
        try {
            $user->notify($notification);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to multiple users.
     *
     * @param array $users
     * @param $notification
     * @return void
     */
    protected function sendBulkNotification($users, $notification)
    {
        try {
            Notification::send($users, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to send bulk notification', [
                'user_count' => count($users),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark notification as read.
     *
     * @param User $user
     * @param string $notificationId
     * @return void
     */
    protected function markNotificationAsRead($user, $notificationId)
    {
        $notification = $user->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param User $user
     * @return void
     */
    protected function markAllNotificationsAsRead($user)
    {
        $user->unreadNotifications->markAsRead();
    }

    /**
     * Get unread notifications count for a user.
     *
     * @param User $user
     * @return int
     */
    protected function getUnreadNotificationsCount($user)
    {
        return $user->unreadNotifications->count();
    }

    /**
     * Get recent notifications for a user.
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Notifications\DatabaseNotificationCollection
     */
    protected function getRecentNotifications($user, $limit = 10)
    {
        return $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}