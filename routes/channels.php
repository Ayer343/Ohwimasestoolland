<?php

use App\Models\Conversation;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::find($conversationId)
        ?->participants()
        ->where('user_id', $user->id)
        ->exists() ?? false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});