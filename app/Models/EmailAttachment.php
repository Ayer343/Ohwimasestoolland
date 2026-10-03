<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_id',
        'user_email_account_id',
        'filename',
        'mime_type',
        'size',
        'path',
        'url',
        'is_inline',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_inline' => 'boolean',
        'metadata' => 'array',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(UserEmailAccount::class);
    }
}