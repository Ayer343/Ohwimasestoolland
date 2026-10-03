<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    use SoftDeletes;

    public const TYPE_SUPPORT    = 'support';
    public const TYPE_ESCALATION = 'escalation';

    public const STATUS_OPEN     = 'open';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED   = 'closed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'subject', 'type', 'status', 'priority',
        'initiator_id', 'assigned_to',
        'last_message_at', 'last_message_preview', 'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'metadata'        => 'array',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at', 'last_read_message_id', 'muted_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest()->limit(1);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('participants', fn ($q) => $q->where('user_id', $userId));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ASSIGNED]);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /* ---------------- Helpers ---------------- */

    public function unreadCountFor(int $userId): int
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        if (!$participant) return 0;

        return $this->messages()
            ->whereNull('deleted_at')
            ->where('user_id', '!=', $userId)
            ->when($participant->last_read_message_id,
                fn ($q, $id) => $q->where('id', '>', $id)
            )
            ->count();
    }
}