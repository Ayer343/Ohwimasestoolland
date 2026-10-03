<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailFolder extends Model
{
    protected $fillable = [
        'user_email_account_id',
        'name',
        'external_id',
        'parent_id',
        'is_system',
        'sync_enabled',
        'last_sync_at',
        'metadata',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sync_enabled' => 'boolean',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(UserEmailAccount::class);
    }
}