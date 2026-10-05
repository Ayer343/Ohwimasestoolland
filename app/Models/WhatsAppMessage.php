<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    /**
     * ✅ FIX: Laravel's snake-caser would derive `whats_app_messages`
     *    from `WhatsAppMessage`, but the migration created `whatsapp_messages`.
     *    Setting the table name explicitly removes the mismatch and
     *    prevents SQLSTATE[42S02] "Table doesn't exist" errors.
     */
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'to',
        'from',
        'message',
        'message_type',
        'template',
        'media_url',
        'caption',
        'parameters',
        'provider',
        'message_id',
        'status',
        'is_incoming',
        'response',
        'attempt_count',
        'last_attempt',
        'created_by',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'parameters'    => 'array',
        'response'      => 'array',
        'is_incoming'   => 'boolean',
        'scheduled_at'  => 'datetime',
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
        'last_attempt'  => 'datetime',
    ];

    protected $with = ['sender'];

    /**
     * Get the sender user
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the recipient
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'to');
    }

    /**
     * Get logs for this message
     */
    public function logs()
    {
        return $this->hasMany(WhatsAppLog::class, 'message_id');
    }
}