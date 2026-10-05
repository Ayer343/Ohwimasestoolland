<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLog extends Model
{
    /**
     * ✅ FIX: Laravel's snake-caser would derive `whats_app_logs`
     *    from `WhatsAppLog`, but the migration created `whatsapp_logs`.
     *    Setting the table name explicitly removes the mismatch and
     *    prevents SQLSTATE[42S02] "Table doesn't exist" errors.
     */
    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'provider',
        'to',
        'from',
        'message',
        'type',
        'status',
        'message_id',
        'response',
        'created_by',
    ];

    protected $casts = [
        'response'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who created this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}