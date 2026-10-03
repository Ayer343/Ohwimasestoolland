<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $fillable = [
        'backup_type',
        'filename',
        'size',
        'status',
        'started_at',
        'completed_at',
        'duration',
        'error_message',
        'notes',
        'cleaned_up',
        'cleaned_up_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cleaned_up_at' => 'datetime',
        'cleaned_up' => 'boolean',
        'duration' => 'integer',
    ];
}