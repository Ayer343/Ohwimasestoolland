<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewLiveChatMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message) {}

    /**
     * Delivery channels.
     *  • database → in-app bell notification (always)
     *  • mail     → email to the recipient (optional)
     *
     * Add 'mail' below if you want email delivery.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'live_chat',
            'icon'            => 'comments',
            'title'           => 'New message from ' . ($this->message->user?->name ?? 'Support'),
            'message'         => Str::limit($this->message->body, 120),
            'conversation_id' => $this->message->conversation_id,
            'message_id'      => $this->message->id,
            'url'             => route('live-chat.show', $this->message->conversation_id),
        ];
    }

    /**
     * Optional email representation.
     * Only used when 'mail' is added to via().
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New support message')
            ->greeting('Hello ' . $notifiable->name)
            ->line(($this->message->user?->name ?? 'Someone') . ' sent you a message:')
            ->line(Str::limit($this->message->body, 200))
            ->action('View Conversation', route('live-chat.show', $this->message->conversation_id))
            ->line('Reply from the support chat.');
    }
}