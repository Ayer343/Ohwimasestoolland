<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'level',
        'message',
        'file',
        'line',
        'code',
        'trace',
        'user_id',
        'url',
        'method',
        'ip',
        'user_agent',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'user_id' => 'integer',
    ];
}