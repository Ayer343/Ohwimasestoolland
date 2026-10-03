<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceLog extends Model
{
    protected $fillable = [
        'endpoint',
        'method',
        'response_time',
        'memory_usage',
        'query_count',
        'query_time',
        'user_id',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'response_time' => 'float',
        'memory_usage' => 'integer',
        'query_count' => 'integer',
        'query_time' => 'float',
        'user_id' => 'integer',
    ];
}