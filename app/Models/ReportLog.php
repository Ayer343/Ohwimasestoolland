<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportLog extends Model
{
    protected $fillable = [
        'report_type',
        'period_start',
        'period_end',
        'format',
        'generated_by',
        'file_path',
        'file_size',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'file_size' => 'integer',
        'generated_by' => 'integer',
    ];
}