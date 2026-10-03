<?php

namespace App\Models\Traits;

trait CanChat
{
    /**
     * Check if user can participate in chat.
     */
    public function canChat(): bool
    {
        // Add your chat permission logic here
        return true; // Example: all users can chat
    }

    /**
     * Get user's chat conversations.
     */
    public function conversations()
    {
        // Implement conversation relationship
        return $this->belongsToMany(Conversation::class, 'conversation_user')
                    ->withTimestamps();
    }

    /**
     * Get user's chat messages.
     */
    public function messages()
    {
        // Implement messages relationship
        return $this->hasMany(Message::class);
    }
}