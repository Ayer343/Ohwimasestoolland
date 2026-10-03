<?php

namespace App\Events\LiveChat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $reader,
        public int $lastReadMessageId
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id'       => $this->conversation->id,
            'reader_id'             => $this->reader->id,
            'reader_name'           => $this->reader->name,
            'last_read_message_id'  => $this->lastReadMessageId,
            'read_at'               => now()->toIso8601String(),
        ];
    }
}