<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id', 'user_id', 'body', 'type',
        'attachments', 'metadata', 'edited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata'    => 'array',
        'edited_at'   => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}