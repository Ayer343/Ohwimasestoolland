<?php

namespace App\Events\LiveChat;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
{
    $channels = [
        new PrivateChannel('user.' . $this->conversation->initiator_id),
    ];

    if ($this->conversation->assigned_to) {
        $channels[] = new PrivateChannel('user.' . $this->conversation->assigned_to);
    }

    return $channels;
}

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                   => $this->conversation->id,
            'status'               => $this->conversation->status,
            'assigned_to'          => $this->conversation->assigned_to,
            'last_message_at'      => optional($this->conversation->last_message_at)->toIso8601String(),
            'last_message_preview' => $this->conversation->last_message_preview,
        ];
    }
}