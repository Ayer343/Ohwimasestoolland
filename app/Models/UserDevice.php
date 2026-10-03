<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class UserDevice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_devices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'capabilities',
        'is_trusted',
        'requires_verification',
        'first_seen_at',
        'last_seen_at',
        'verification_history',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
        'verification_history' => 'array',
        'is_trusted' => 'boolean',
        'requires_verification' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
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
        'first_seen_at',
        'last_seen_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'requires_verification' => true,
        'is_trusted' => false,
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user that owns this device.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Get all verification logs for this device.
     */
    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class, 'device_id', 'device_id');
    }

    /**
     * Get all schedules where this device was used for check-in.
     */
    public function checkinSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'checkin_device_id', 'device_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include trusted devices.
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    /**
     * Scope a query to only include untrusted devices.
     */
    public function scopeUntrusted($query)
    {
        return $query->where('is_trusted', false);
    }

    /**
     * Scope a query to only include devices that require verification.
     */
    public function scopeRequiresVerification($query)
    {
        return $query->where('requires_verification', true);
    }

    /**
     * Scope a query to only include devices for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include devices seen after a given date.
     */
    public function scopeSeenAfter($query, $date)
    {
        return $query->where('last_seen_at', '>=', Carbon::parse($date));
    }

    /**
     * Scope a query to only include devices seen before a given date.
     */
    public function scopeSeenBefore($query, $date)
    {
        return $query->where('last_seen_at', '<=', Carbon::parse($date));
    }

    /**
     * Scope a query to only include active devices (seen in last 30 days).
     */
    public function scopeActive($query)
    {
        return $query->where('last_seen_at', '>=', now()->subDays(30));
    }

    /**
     * Scope a query to only include inactive devices (not seen in last 30 days).
     */
    public function scopeInactive($query)
    {
        return $query->where('last_seen_at', '<', now()->subDays(30));
    }

    /**
     * Scope a query to only include devices with a specific OS.
     */
    public function scopeWithOs($query, $os)
    {
        return $query->where('os_version', 'LIKE', "{$os}%");
    }

    /**
     * Scope a query to only include devices with a specific app version.
     */
    public function scopeWithAppVersion($query, $version)
    {
        return $query->where('app_version', $version);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the device's full name (model + name).
     */
    public function getFullNameAttribute()
    {
        if ($this->device_name && $this->device_model) {
            return "{$this->device_name} ({$this->device_model})";
        }
        return $this->device_name ?? $this->device_model ?? $this->device_id;
    }

    /**
     * Get the device's last seen ago time.
     */
    public function getLastSeenAgoAttribute()
    {
        return $this->last_seen_at ? $this->last_seen_at->diffForHumans() : 'Never';
    }

    /**
     * Get the device's first seen ago time.
     */
    public function getFirstSeenAgoAttribute()
    {
        return $this->first_seen_at ? $this->first_seen_at->diffForHumans() : 'Never';
    }

    /**
     * Get the device's status (active/inactive).
     */
    public function getStatusAttribute()
    {
        if (!$this->last_seen_at) {
            return 'never_used';
        }
        
        if ($this->last_seen_at->gt(now()->subDays(30))) {
            return 'active';
        }
        
        return 'inactive';
    }

    /**
     * Get the device's status color for UI.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'success',
            'inactive' => 'warning',
            'never_used' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get the device's trust level as text.
     */
    public function getTrustLevelAttribute()
    {
        if ($this->is_trusted) {
            return 'Trusted';
        }
        
        return $this->requires_verification ? 'Requires Verification' : 'Untrusted';
    }

    /**
     * Get the device's trust color for UI.
     */
    public function getTrustColorAttribute()
    {
        if ($this->is_trusted) {
            return 'success';
        }
        
        return $this->requires_verification ? 'warning' : 'danger';
    }

    /**
     * Get the device's capabilities as a formatted string.
     */
    public function getCapabilitiesListAttribute()
    {
        if (empty($this->capabilities)) {
            return [];
        }
        
        return collect($this->capabilities)
            ->filter(function($value, $key) {
                return $value === true;
            })
            ->keys()
            ->map(function($capability) {
                return ucwords(str_replace('_', ' ', $capability));
            })
            ->toArray();
    }

    /**
     * Get the verification count for this device.
     */
    public function getVerificationCountAttribute()
    {
        return $this->verificationLogs()->count();
    }

    /**
     * Get the success rate of verifications.
     */
    public function getSuccessRateAttribute()
    {
        $total = $this->verificationLogs()->count();
        if ($total === 0) {
            return 0;
        }
        
        $success = $this->verificationLogs()->where('status', 'success')->count();
        return round(($success / $total) * 100, 2);
    }

    // ==================== MUTATORS ====================

    /**
     * Set the device's last seen at to now.
     */
    public function markAsSeen()
    {
        $this->last_seen_at = now();
        $this->save();
    }

    /**
     * Trust this device.
     */
    public function trust()
    {
        $this->is_trusted = true;
        $this->requires_verification = false;
        $this->save();
    }

    /**
     * Untrust this device.
     */
    public function untrust()
    {
        $this->is_trusted = false;
        $this->requires_verification = true;
        $this->save();
    }

    /**
     * Add verification attempt to history.
     */
    public function addVerificationAttempt($status, $metadata = [])
    {
        $history = $this->verification_history ?? [];
        $history[] = array_merge([
            'attempted_at' => now()->toDateTimeString(),
            'status' => $status,
        ], $metadata);
        
        $this->verification_history = $history;
        $this->save();
    }

    /**
     * Update app version.
     */
    public function updateAppVersion($version)
    {
        $this->app_version = $version;
        $this->save();
    }

    // ==================== CUSTOM METHODS ====================

    /**
     * Check if device has a specific capability.
     */
    public function hasCapability($capability)
    {
        return isset($this->capabilities[$capability]) && $this->capabilities[$capability] === true;
    }

    /**
     * Check if device is compatible with required capabilities.
     */
    public function isCompatibleWith($requiredCapabilities = [])
    {
        foreach ($requiredCapabilities as $capability) {
            if (!$this->hasCapability($capability)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verify device identity.
     */
    public function verifyIdentity($deviceId, $userId = null)
    {
        if ($this->device_id !== $deviceId) {
            return false;
        }
        
        if ($userId && $this->user_id !== $userId) {
            return false;
        }
        
        return true;
    }

    /**
     * Get verification statistics for this device.
     */
    public function getVerificationStats()
    {
        $logs = $this->verificationLogs()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        return [
            'total' => $logs->total ?? 0,
            'successful' => $logs->successful ?? 0,
            'failed' => $logs->failed ?? 0,
            'pending' => $logs->pending ?? 0,
            'success_rate' => $logs->total > 0 
                ? round(($logs->successful / $logs->total) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Check if device needs re-verification.
     */
    public function needsReVerification($maxAgeDays = 30)
    {
        if (!$this->last_seen_at) {
            return true;
        }
        
        return $this->last_seen_at->lt(now()->subDays($maxAgeDays));
    }

    /**
     * Get device usage timeline.
     */
    public function getUsageTimeline($days = 30)
    {
        return $this->verificationLogs()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function($log) {
                return $log->created_at->format('Y-m-d');
            })
            ->map(function($logs, $date) {
                return [
                    'date' => $date,
                    'total' => $logs->count(),
                    'success' => $logs->where('status', 'success')->count(),
                    'failed' => $logs->where('status', 'failed')->count(),
                    'methods' => $logs->groupBy('method')->map->count()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            if (!$device->first_seen_at) {
                $device->first_seen_at = now();
            }
            if (!$device->last_seen_at) {
                $device->last_seen_at = now();
            }
        });

        static::updating(function ($device) {
            if ($device->isDirty('is_trusted') && $device->is_trusted) {
                $device->requires_verification = false;
            }
        });
    }
}