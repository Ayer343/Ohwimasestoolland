<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message;
    protected $sender;
    protected $senderType;

    public function __construct($message, $sender, $senderType = 'user')
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->senderType = $senderType;
    }

    // Define channels
    public function via($notifiable)
    {
        // Use database for in-app notifications
        return ['database'];
        
        // For real-time, add 'broadcast' channel
        // return ['database', 'broadcast'];
    }

    // Database representation
    public function toDatabase($notifiable)
    {
        $senderName = $this->sender->name ?? 'System';
        $senderId = $this->sender->id ?? null;
        $senderAvatar = $this->sender->avatar_url ?? null;

        return [
            'title' => 'New Message',
            'message' => $this->message,
            'sender' => [
                'id' => $senderId,
                'name' => $senderName,
                'avatar' => $senderAvatar,
                'type' => $this->senderType,
            ],
            'action_url' => $senderId ? "/messages/{$senderId}" : null,
            'icon' => 'fas fa-envelope',
            'category' => 'messages',
            'priority' => 1,
            'metadata' => [
                'type' => 'message',
                'sender_type' => $this->senderType,
                'timestamp' => now()->toISOString(),
            ]
        ];
    }
}