<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VerificationLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verification_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'schedule_id',
        'action',
        'method',
        'status',
        'results',
        'location',
        'accuracy',
        'device_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'results' => 'array',
        'location' => 'array',
        'metadata' => 'array',
        'accuracy' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Available verification actions.
     */
    const ACTION_CHECKIN = 'checkin';
    const ACTION_CHECKOUT = 'checkout';
    const ACTION_BREAK_START = 'break_start';
    const ACTION_BREAK_END = 'break_end';
    const ACTION_VERIFY = 'verify';
    const ACTION_HANDOVER = 'handover';

    /**
     * Available verification methods.
     */
    const METHOD_GPS = 'gps';
    const METHOD_QR = 'qr';
    const METHOD_NFC = 'nfc';
    const METHOD_BIOMETRIC = 'biometric';
    const METHOD_MANUAL = 'manual';
    const METHOD_FACE = 'face';
    const METHOD_FINGERPRINT = 'fingerprint';
    const METHOD_PIN = 'pin';
    const METHOD_BEACON = 'beacon';
    const METHOD_WIFI = 'wifi';

    /**
     * Available verification statuses.
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REJECTED = 'rejected';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who performed this verification.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Get the schedule associated with this verification.
     */
    public function schedule()
    {
        return $this->belongsTo(SecuritySchedule::class, 'schedule_id')->withTrashed();
    }

    /**
     * Get the device used for this verification.
     */
    public function device()
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    /**
     * Get the QR code used for this verification.
     */
    public function qrCode()
    {
        if ($this->method === self::METHOD_QR && isset($this->metadata['qr_code_id'])) {
            return $this->belongsTo(PostQrCode::class, 'metadata->qr_code_id', 'id');
        }
        
        return null;
    }

    /**
     * Get the NFC tag used for this verification.
     */
    public function nfcTag()
    {
        if ($this->method === self::METHOD_NFC && isset($this->metadata['nfc_tag_id'])) {
            return $this->belongsTo(PostNfcTag::class, 'metadata->nfc_tag_id', 'id');
        }
        
        return null;
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include logs for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include logs for a specific schedule.
     */
    public function scopeForSchedule($query, $scheduleId)
    {
        return $query->where('schedule_id', $scheduleId);
    }

    /**
     * Scope a query to only include logs with a specific action.
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs with a specific method.
     */
    public function scopeWithMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Scope a query to only include logs with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include successful verifications.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope a query to only include failed verifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include pending verifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include check-in logs.
     */
    public function scopeCheckins($query)
    {
        return $query->where('action', self::ACTION_CHECKIN);
    }

    /**
     * Scope a query to only include check-out logs.
     */
    public function scopeCheckouts($query)
    {
        return $query->where('action', self::ACTION_CHECKOUT);
    }

    /**
     * Scope a query to only include break logs.
     */
    public function scopeBreaks($query)
    {
        return $query->whereIn('action', [self::ACTION_BREAK_START, self::ACTION_BREAK_END]);
    }

    /**
     * Scope a query to only include logs for a specific date.
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('created_at', Carbon::parse($date));
    }

    /**
     * Scope a query to only include logs within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
    }

    /**
     * Scope a query to only include logs from a specific device.
     */
    public function scopeFromDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope a query to only include logs from a specific IP.
     */
    public function scopeFromIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the action label.
     */
    public function getActionLabelAttribute()
    {
        return match($this->action) {
            self::ACTION_CHECKIN => 'Check In',
            self::ACTION_CHECKOUT => 'Check Out',
            self::ACTION_BREAK_START => 'Break Start',
            self::ACTION_BREAK_END => 'Break End',
            self::ACTION_VERIFY => 'Verification',
            self::ACTION_HANDOVER => 'Handover',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Get the method label.
     */
    public function getMethodLabelAttribute()
    {
        return match($this->method) {
            self::METHOD_GPS => 'GPS Location',
            self::METHOD_QR => 'QR Code',
            self::METHOD_NFC => 'NFC Tag',
            self::METHOD_BIOMETRIC => 'Biometric',
            self::METHOD_MANUAL => 'Manual Override',
            self::METHOD_FACE => 'Face Recognition',
            self::METHOD_FINGERPRINT => 'Fingerprint',
            self::METHOD_PIN => 'PIN Code',
            self::METHOD_BEACON => 'Bluetooth Beacon',
            self::METHOD_WIFI => 'WiFi Network',
            default => ucfirst($this->method),
        };
    }

    /**
     * Get the method icon.
     */
    public function getMethodIconAttribute()
    {
        return match($this->method) {
            self::METHOD_GPS => '📍',
            self::METHOD_QR => '📱',
            self::METHOD_NFC => '📡',
            self::METHOD_BIOMETRIC => '👆',
            self::METHOD_MANUAL => '✍️',
            self::METHOD_FACE => '👤',
            self::METHOD_FINGERPRINT => '🖐️',
            self::METHOD_PIN => '🔢',
            self::METHOD_BEACON => '📶',
            self::METHOD_WIFI => '📡',
            default => '✓',
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_SUCCESS => 'Success',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the status color for UI.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_PENDING => 'warning',
            self::STATUS_EXPIRED => 'secondary',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the time ago.
     */
    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the formatted location.
     */
    public function getFormattedLocationAttribute()
    {
        if (!$this->location) {
            return 'No location data';
        }

        $lat = $this->location['lat'] ?? $this->location['latitude'] ?? null;
        $lng = $this->location['lng'] ?? $this->location['longitude'] ?? null;

        if ($lat && $lng) {
            return "{$lat}, {$lng}";
        }

        if (isset($this->location['address'])) {
            return $this->location['address'];
        }

        return json_encode($this->location);
    }

    /**
     * Get the accuracy description.
     */
    public function getAccuracyDescriptionAttribute()
    {
        if (!$this->accuracy) {
            return 'Not available';
        }

        if ($this->accuracy < 10) {
            return "Excellent ({$this->accuracy}m)";
        } elseif ($this->accuracy < 50) {
            return "Good ({$this->accuracy}m)";
        } elseif ($this->accuracy < 100) {
            return "Fair ({$this->accuracy}m)";
        } else {
            return "Poor ({$this->accuracy}m)";
        }
    }

    // ==================== CUSTOM METHODS ====================

    /**
     * Log a verification attempt.
     */
    public static function logAttempt($userId, $scheduleId, $action, $method, $status, $metadata = [])
    {
        $request = request();
        
        return self::create([
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'action' => $action,
            'method' => $method,
            'status' => $status,
            'results' => $metadata['results'] ?? null,
            'location' => $metadata['location'] ?? null,
            'accuracy' => $metadata['accuracy'] ?? null,
            'device_id' => $metadata['device_id'] ?? $request->header('X-Device-ID'),
            'ip_address' => $metadata['ip_address'] ?? $request->ip(),
            'user_agent' => $metadata['user_agent'] ?? $request->userAgent(),
            'metadata' => array_merge($metadata, [
                'logged_at' => now()->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Check if this verification was successful.
     */
    public function isSuccessful()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if this verification failed.
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if this verification is pending.
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Get verification statistics for a user.
     */
    public static function getUserStats($userId, $days = 30)
    {
        $logs = self::forUser($userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $byMethod = $logs->groupBy('method')->map->count();
        $byAction = $logs->groupBy('action')->map->count();
        $byStatus = $logs->groupBy('status')->map->count();

        return [
            'total' => $logs->count(),
            'successful' => $logs->where('status', self::STATUS_SUCCESS)->count(),
            'failed' => $logs->where('status', self::STATUS_FAILED)->count(),
            'success_rate' => $logs->count() > 0 
                ? round(($logs->where('status', self::STATUS_SUCCESS)->count() / $logs->count()) * 100, 2)
                : 0,
            'by_method' => $byMethod,
            'by_action' => $byAction,
            'by_status' => $byStatus,
            'average_accuracy' => $logs->whereNotNull('accuracy')->avg('accuracy'),
            'most_used_method' => $byMethod->isNotEmpty() ? $byMethod->sortDesc()->keys()->first() : null,
        ];
    }

    /**
     * Get verification statistics for a schedule.
     */
    public static function getScheduleStats($scheduleId)
    {
        $logs = self::forSchedule($scheduleId)->get();

        return [
            'total' => $logs->count(),
            'checkins' => $logs->where('action', self::ACTION_CHECKIN)->count(),
            'checkouts' => $logs->where('action', self::ACTION_CHECKOUT)->count(),
            'breaks' => $logs->whereIn('action', [self::ACTION_BREAK_START, self::ACTION_BREAK_END])->count(),
            'by_method' => $logs->groupBy('method')->map->count(),
            'timeline' => $logs->groupBy(function($log) {
                return $log->created_at->format('Y-m-d H:00');
            })->map->count(),
        ];
    }

    /**
     * Get overall verification statistics.
     */
    public static function getOverallStats($days = 30)
    {
        $startDate = now()->subDays($days);
        $logs = self::where('created_at', '>=', $startDate)->get();

        $dailyStats = $logs->groupBy(function($log) {
            return $log->created_at->format('Y-m-d');
        })->map(function($dayLogs) {
            return [
                'total' => $dayLogs->count(),
                'success' => $dayLogs->where('status', self::STATUS_SUCCESS)->count(),
                'failed' => $dayLogs->where('status', self::STATUS_FAILED)->count(),
            ];
        });

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
                'days' => $days,
            ],
            'total' => $logs->count(),
            'successful' => $logs->where('status', self::STATUS_SUCCESS)->count(),
            'failed' => $logs->where('status', self::STATUS_FAILED)->count(),
            'pending' => $logs->where('status', self::STATUS_PENDING)->count(),
            'success_rate' => $logs->count() > 0 
                ? round(($logs->where('status', self::STATUS_SUCCESS)->count() / $logs->count()) * 100, 2)
                : 0,
            'by_method' => $logs->groupBy('method')->map(function($methodLogs) {
                return [
                    'count' => $methodLogs->count(),
                    'success_rate' => $methodLogs->count() > 0
                        ? round(($methodLogs->where('status', self::STATUS_SUCCESS)->count() / $methodLogs->count()) * 100, 2)
                        : 0,
                ];
            }),
            'by_action' => $logs->groupBy('action')->map->count(),
            'daily_stats' => $dailyStats,
            'unique_users' => $logs->unique('user_id')->count(),
            'unique_devices' => $logs->whereNotNull('device_id')->unique('device_id')->count(),
        ];
    }

    /**
     * Get failure reasons analysis.
     */
    public static function getFailureAnalysis($days = 30)
    {
        $failures = self::failed()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $reasons = [];

        foreach ($failures as $failure) {
            $reason = $failure->results['reason'] ?? $failure->metadata['reason'] ?? 'Unknown';
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        arsort($reasons);

        return [
            'total_failures' => $failures->count(),
            'reasons' => $reasons,
            'by_method' => $failures->groupBy('method')->map->count(),
            'by_device' => $failures->whereNotNull('device_id')->groupBy('device_id')->map(function($deviceFailures) {
                return [
                    'count' => $deviceFailures->count(),
                    'last_failure' => $deviceFailures->max('created_at')->diffForHumans(),
                ];
            })->sortByDesc('count')->take(10),
        ];
    }

    /**
     * Clean up old logs.
     */
    public static function cleanupOldLogs($daysOld = 90)
    {
        return self::where('created_at', '<', now()->subDays($daysOld))->delete();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (empty($log->metadata)) {
                $log->metadata = [];
            }
            
            $log->metadata = array_merge($log->metadata ?? [], [
                'created_at_timestamp' => now()->timestamp,
                'created_at_timezone' => config('app.timezone'),
            ]);
        });

        static::created(function ($log) {
            if ($log->status === self::STATUS_FAILED && $log->device_id) {
                $device = UserDevice::where('device_id', $log->device_id)->first();
                if ($device) {
                    $device->addVerificationAttempt('failed', [
                        'action' => $log->action,
                        'reason' => $log->results['reason'] ?? $log->metadata['reason'] ?? 'Unknown',
                        'schedule_id' => $log->schedule_id,
                    ]);
                }
            }
        });
    }
}