<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    protected $table = 'security_logs';
    
    protected $fillable = [
        'event_type',
        'ip_address',
        'user_agent',
        'details',
        'severity',
        'user_id'
    ];
    
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];
}