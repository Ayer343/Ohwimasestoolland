<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookCall extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_id',
        'webhook_type',
        'url',
        'payload',
        'response',
        'status_code',
        'status',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_attempt_at',
        'processed_at',
        'failed_at',
        'failure_reason',
        'headers',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'status_code' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';

    public static function calculateSuccessRate($hours = 24): array
    {
        $total = self::where('created_at', '>=', now()->subHours($hours))->count();
        $success = self::where('status', self::STATUS_SUCCESS)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
        $failed = self::where('status', self::STATUS_FAILED)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
        
        $successRate = $total > 0 ? ($success / $total) * 100 : 100;

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => round($successRate, 2),
            'requires_attention' => $successRate < 90,
        ];
    }
}