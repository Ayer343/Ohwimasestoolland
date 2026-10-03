<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * ⭐ ADDED: guest_token, guest_ip, guest_user_agent for guest support.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'guest_token',
        'guest_ip',
        'guest_user_agent',
        'message',
        'response',
        'is_bot',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_bot'     => 'boolean',
        'metadata'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ============================================================
       RELATIONSHIPS
       ============================================================ */

    /**
     * Get the user that owns the chat message.
     * Nullable because guest messages have no user_id.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ============================================================
       QUERY SCOPES
       ============================================================ */

    /**
     * Scope to messages for a specific user.
     *
     * ⭐ CHANGED: Now handles null $userId gracefully.
     *   - Passing a real ID → where('user_id', $id)
     *   - Passing null      → whereNull('user_id')
     *
     * This makes ChatController::getMessages() and clearHistory()
     * safe to call for both authenticated users and guests.
     */
    public function scopeForUser($query, $userId)
    {
        if ($userId === null) {
            return $query->whereNull('user_id');
        }

        return $query->where('user_id', $userId);
    }

    /**
     * ⭐ NEW: Scope to messages belonging to a specific guest session.
     *
     * Guest sessions are identified by a random token stored in the
     * Laravel session (see ChatController::guestToken()).
     */
    public function scopeForGuest($query, string $guestToken)
    {
        return $query->whereNull('user_id')
                     ->where('guest_token', $guestToken);
    }

    /**
     * ⭐ NEW: Scope to only guest messages (no registered user attached).
     */
    public function scopeGuestMessages($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * ⭐ NEW: Scope to only authenticated-user messages.
     */
    public function scopeAuthenticatedMessages($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to only bot messages.
     */
    public function scopeBotMessages($query)
    {
        return $query->where('is_bot', true);
    }

    /**
     * Scope to only user-submitted messages.
     */
    public function scopeUserMessages($query)
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to order by latest messages first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to order by oldest messages first.
     */
    public function scopeOldestFirst($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * ⭐ NEW: Scope to messages still awaiting a bot reply.
     * Useful for detecting dropped/failed generations.
     */
    public function scopeUnanswered($query)
    {
        return $query->where('is_bot', false)
                     ->whereNull('response');
    }

    /**
     * ⭐ NEW: Scope to purge guest messages older than N days.
     * Used by the PurgeGuestChatMessages command.
     */
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /* ============================================================
       HELPERS
       ============================================================ */

    /**
     * ⭐ NEW: Is this message from a guest?
     */
    public function isFromGuest(): bool
    {
        return is_null($this->user_id) && !empty($this->guest_token);
    }

    /**
     * ⭐ NEW: Is this message from an authenticated user?
     */
    public function isFromUser(): bool
    {
        return !is_null($this->user_id);
    }

    /**
     * ⭐ NEW: Display name for the sender (for admin/UI purposes).
     */
    public function getSenderLabelAttribute(): string
    {
        if ($this->isFromUser() && $this->user) {
            return $this->user->name;
        }

        if ($this->isFromGuest()) {
            return 'Guest (' . substr($this->guest_token, 0, 8) . '…)';
        }

        return 'Unknown';
    }
}