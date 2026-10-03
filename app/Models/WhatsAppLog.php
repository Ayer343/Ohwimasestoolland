<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLog extends Model
{
    protected $fillable = [
        'provider',
        'to',
        'from',
        'message',
        'type',
        'status',
        'message_id',
        'response',
        'created_by'
    ];

    protected $casts = [
        'response' => 'array',
        'created_at' => 'datetime'
    ];

    /**
     * Get the user who created this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}