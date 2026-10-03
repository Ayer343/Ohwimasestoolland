<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model
{
    protected $fillable = [
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'load_average_1min',
        'load_average_5min',
        'load_average_15min',
        'database_connections',
        'queue_size',
        'alerts_count',
        'recorded_at',
    ];

    protected $casts = [
        'cpu_usage' => 'float',
        'memory_usage' => 'float',
        'disk_usage' => 'float',
        'load_average_1min' => 'float',
        'load_average_5min' => 'float',
        'load_average_15min' => 'float',
        'database_connections' => 'integer',
        'queue_size' => 'integer',
        'alerts_count' => 'integer',
        'recorded_at' => 'datetime',
    ];
}