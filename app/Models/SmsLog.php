<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsLog extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sms_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider',
        'phone_number',
        'message',
        'status',
        'response',
        'message_id',
        'error_message',
        'error_code',
        'is_test',
        'execution_time',
        'status_code',
        'details',
        'user_id',
        'sent_at',
        'delivered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'response' => 'array',
        'details' => 'array',
        'is_test' => 'boolean',
        'execution_time' => 'float',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'response',
        'details',
    ];

    /**
     * Get the user who sent the SMS.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========================================== //
    // 🔍 SCOPES                                 //
    // ========================================== //

    /**
     * Scope a query to only include successful messages.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed messages.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include pending messages.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include test messages.
     */
    public function scopeTest($query)
    {
        return $query->where('is_test', true);
    }

    /**
     * Scope a query to only include production messages.
     */
    public function scopeProduction($query)
    {
        return $query->where('is_test', false);
    }

    /**
     * Scope by provider.
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope by phone number (partial match).
     */
    public function scopeByPhone($query, $phoneNumber)
    {
        return $query->where('phone_number', 'like', '%' . $phoneNumber . '%');
    }

    /**
     * Scope for today's messages.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for this week's messages.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope for this month's messages.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    // ========================================== //
    // 🏷️ ACCESSORS                              //
    // ========================================== //

    /**
     * Get the masked phone number for display.
     */
    public function getMaskedPhoneAttribute()
    {
        if (empty($this->phone_number)) {
            return 'N/A';
        }
        
        if (strlen($this->phone_number) <= 8) {
            return $this->phone_number;
        }
        
        return substr($this->phone_number, 0, 4) . '****' . substr($this->phone_number, -4);
    }

    /**
     * Get the status badge class for HTML.
     */
    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            'success' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            'failed'   => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
            'pending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
            default    => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get the status icon class.
     */
    public function getStatusIconAttribute()
    {
        return match ($this->status) {
            'success' => 'fa-check-circle text-green-500',
            'failed'  => 'fa-exclamation-circle text-red-500',
            'pending' => 'fa-clock text-yellow-500',
            default   => 'fa-circle text-gray-400',
        };
    }

    /**
     * Get the message preview (truncated).
     */
    public function getPreviewAttribute()
    {
        if (empty($this->message)) {
            return 'No message content';
        }
        
        return strlen($this->message) > 100 
            ? substr($this->message, 0, 100) . '...' 
            : $this->message;
    }

    /**
     * Get the formatted execution time.
     */
    public function getFormattedExecutionTimeAttribute()
    {
        if ($this->execution_time === null) {
            return 'N/A';
        }
        
        return number_format($this->execution_time, 2) . ' ms';
    }

    /**
     * Get the provider display name.
     */
    public function getProviderDisplayAttribute()
    {
        $providerMap = [
            'arkesel' => 'Arkesel SMS',
            'twilio' => 'Twilio',
            'africastalking' => 'Africa\'s Talking',
            'hubtel' => 'Hubtel SMS',
            'nalosolutions' => 'Nalo Solutions',
        ];

        return $providerMap[$this->provider] ?? ucfirst($this->provider);
    }

    /**
     * Get whether the SMS was successful.
     */
    public function getIsSuccessfulAttribute()
    {
        return $this->status === 'success';
    }

    /**
     * Get whether the SMS failed.
     */
    public function getIsFailedAttribute()
    {
        return $this->status === 'failed';
    }

    /**
     * Get whether the SMS is pending.
     */
    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    // ========================================== //
    // ✅ HELPER METHODS                         //
    // ========================================== //

    /**
     * Check if the SMS was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the SMS failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the SMS is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the SMS is a test.
     */
    public function isTest(): bool
    {
        return (bool) $this->is_test;
    }

    /**
     * Get the error message or a default message.
     */
    public function getErrorMessage(): string
    {
        return $this->error_message ?? 'No error message available';
    }

    /**
     * Get the delivery status in human readable format.
     */
    public function getDeliveryStatus(): string
    {
        if ($this->delivered_at) {
            return 'Delivered at ' . $this->delivered_at->format('Y-m-d H:i:s');
        }
        
        if ($this->isFailed()) {
            return 'Failed: ' . $this->getErrorMessage();
        }
        
        if ($this->isPending()) {
            return 'Pending delivery';
        }
        
        return 'Sent';
    }

    /**
     * Calculate the delivery time in seconds.
     */
    public function getDeliveryTime(): ?float
    {
        if ($this->sent_at && $this->delivered_at) {
            return $this->delivered_at->diffInSeconds($this->sent_at);
        }
        return null;
    }

    /**
     * Format the delivery time for display.
     */
    public function getFormattedDeliveryTimeAttribute(): string
    {
        $time = $this->getDeliveryTime();
        if ($time === null) {
            return 'N/A';
        }
        
        if ($time < 60) {
            return number_format($time, 0) . ' seconds';
        } elseif ($time < 3600) {
            return number_format($time / 60, 1) . ' minutes';
        }
        
        return number_format($time / 3600, 1) . ' hours';
    }

    // ========================================== //
    // 📊 STATISTICS METHODS                     //
    // ========================================== //

    /**
     * Get count of SMS by status.
     */
    public static function getCountByStatus(): array
    {
        return [
            'total' => self::count(),
            'successful' => self::successful()->count(),
            'failed' => self::failed()->count(),
            'pending' => self::pending()->count(),
        ];
    }

    /**
     * Get success rate percentage.
     */
    public static function getSuccessRate(): float
    {
        $total = self::count();
        if ($total === 0) {
            return 0;
        }
        
        $successful = self::successful()->count();
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get counts by provider.
     */
    public static function getCountByProvider(): array
    {
        return self::select('provider')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful')
            ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider')
            ->toArray();
    }

    // ========================================== //
    // 🔄 OVERRIDEN METHODS                      //
    // ========================================== //

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-set sent_at when created
        static::creating(function ($model) {
            if (!$model->sent_at) {
                $model->sent_at = now();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge($this->casts, [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ]);
    }
}