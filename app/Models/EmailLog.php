<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_id',
        'user_id',
        'to_email',
        'from_email',
        'subject',
        'message',
        'template',
        'type',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'failed_at',
        'failure_reason',
        'retry_count',
        'max_retries',
        'priority',
        'attachments',
        'headers',
        'metadata',
        'campaign_id',
        'batch_id',
        'provider',
        'provider_message_id',
        'cost',
        'ip_address',
        'user_agent',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'failed_at' => 'datetime',
        'attachments' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'cost' => 'decimal:4',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    /**
     * Email statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_OPENED = 'opened';
    const STATUS_CLICKED = 'clicked';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_COMPLAINED = 'complained';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * Email types
     */
    const TYPE_TRANSACTIONAL = 'transactional';
    const TYPE_MARKETING = 'marketing';
    const TYPE_NOTIFICATION = 'notification';
    const TYPE_SYSTEM = 'system';
    const TYPE_BULK = 'bulk';

    /**
     * Email priorities
     */
    const PRIORITY_HIGH = 'high';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_LOW = 'low';

    /**
     * Email providers
     */
    const PROVIDER_SMTP = 'smtp';
    const PROVIDER_MAILGUN = 'mailgun';
    const PROVIDER_SENDGRID = 'sendgrid';
    const PROVIDER_SES = 'ses';
    const PROVIDER_POSTMARK = 'postmark';
    const PROVIDER_SYSTEM = 'system';

    /**
     * Get the user associated with the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created the email.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for sent emails.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for delivered emails.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope for failed emails.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for emails in the last period.
     */
    public function scopeInPeriod($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Calculate delivery rate.
     */
    public static function calculateDeliveryRate($hours = 24): array
    {
        $total = self::where('created_at', '>=', now()->subHours($hours))->count();
        $delivered = self::where('status', self::STATUS_DELIVERED)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
        $failed = self::where('status', self::STATUS_FAILED)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
        
        $deliveryRate = $total > 0 ? ($delivered / $total) * 100 : 100;
        $failureRate = $total > 0 ? ($failed / $total) * 100 : 0;

        return [
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => round($deliveryRate, 2),
            'failure_rate' => round($failureRate, 2),
            'successful' => $deliveryRate >= 95,
            'time_period_hours' => $hours,
        ];
    }

    /**
     * Get recent failed emails.
     */
    public static function getRecentFailures($limit = 10)
    {
        return self::where('status', self::STATUS_FAILED)
            ->orderBy('failed_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'to_email' => $log->to_email,
                    'subject' => $log->subject,
                    'failure_reason' => $log->failure_reason,
                    'failed_at' => $log->failed_at?->toISOString(),
                    'retry_count' => $log->retry_count,
                    'provider' => $log->provider,
                ];
            });
    }

    /**
     * Generate reference ID.
     */
    public static function generateReferenceId(): string
    {
        $prefix = 'EML';
        $date = now()->format('ymd');
        $lastId = self::withTrashed()->where('reference_id', 'like', "{$prefix}{$date}%")->count();
        
        return $prefix . $date . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($emailLog) {
            if (empty($emailLog->reference_id)) {
                $emailLog->reference_id = self::generateReferenceId();
            }

            if (auth()->check() && empty($emailLog->created_by)) {
                $emailLog->created_by = auth()->id();
            }
        });
    }
}