<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmergencyMode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emergency_modes';
    
    protected $fillable = [
        'name',
        'description',
        'reason',
        'reference_id',
        'is_active',
        'status',
        'severity_level',
        'affected_modules',
        'restricted_features',
        'allowed_operations',
        'activated_at',
        'activated_by',
        'scheduled_start',
        'scheduled_end',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'duration_minutes',
        'last_extended_at',
        'extensions_count',
        'affected_users_count',
        'impact_metrics',
        'notify_users',
        'notification_channels',
        'last_notified_at',
        'auto_recovery',
        'recovery_checks',
        'recovery_started_at',
        'recovery_completed_at',
        'settings',
        'environment_overrides',
        'feature_flags',
        'activation_log',
        'deactivation_log',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_users' => 'boolean',
        'auto_recovery' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'last_extended_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'recovery_started_at' => 'datetime',
        'recovery_completed_at' => 'datetime',
        'affected_modules' => 'array',
        'restricted_features' => 'array',
        'allowed_operations' => 'array',
        'notification_channels' => 'array',
        'impact_metrics' => 'array',
        'recovery_checks' => 'array',
        'settings' => 'array',
        'environment_overrides' => 'array',
        'feature_flags' => 'array',
        'activation_log' => 'array',
        'deactivation_log' => 'array',
    ];

    // Status constants
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ACTIVATING = 'activating';
    const STATUS_ACTIVE = 'active';
    const STATUS_DEACTIVATING = 'deactivating';
    const STATUS_RECOVERING = 'recovering';
    
    // Severity levels
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Scope for active emergency modes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for scheduled emergencies
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_INACTIVE)
                    ->whereNotNull('scheduled_start');
    }

    /**
     * Get emergency mode logs
     */
    public function logs()
    {
        return $this->hasMany(EmergencyModeLog::class);
    }

    /**
     * Get affected users
     */
    public function affectedUsers()
    {
        return $this->hasMany(EmergencyAffectedUser::class);
    }

    /**
     * User who activated the emergency mode
     */
    public function activatedByUser()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * User who deactivated the emergency mode
     */
    public function deactivatedByUser()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Check if emergency mode is active
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if emergency mode is scheduled
     */
    public function isScheduled(): bool
    {
        return !$this->is_active && $this->scheduled_start !== null;
    }

    /**
     * Calculate current duration
     */
    public function getCurrentDurationAttribute(): ?int
    {
        if (!$this->activated_at) {
            return null;
        }

        $endTime = $this->deactivated_at ?? now();
        return $endTime->diffInMinutes($this->activated_at);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->current_duration;
        
        if (!$duration) {
            return 'Not active';
        }

        $hours = floor($duration / 60);
        $minutes = $duration % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        
        return "{$minutes}m";
    }

    /**
     * Get time remaining for scheduled emergencies
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->scheduled_start || $this->is_active) {
            return null;
        }

        $diff = now()->diff($this->scheduled_start);
        
        if ($diff->invert) {
            return 'Overdue';
        }

        $parts = [];
        if ($diff->d > 0) $parts[] = "{$diff->d}d";
        if ($diff->h > 0) $parts[] = "{$diff->h}h";
        if ($diff->i > 0) $parts[] = "{$diff->i}m";
        
        return implode(' ', $parts) ?: 'Less than a minute';
    }

    /**
     * Get affected modules as array
     */
    public function getAffectedModulesListAttribute(): array
    {
        $modules = $this->affected_modules ?? [];
        
        $moduleNames = [
            'user_management' => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing' => 'Payment Processing',
            'communication' => 'Communication',
            'reporting' => 'Reporting',
            'api' => 'API Services',
            'dashboard' => 'Dashboard',
            'authentication' => 'Authentication',
            'database' => 'Database',
            'queue' => 'Queue System',
        ];

        $result = [];
        foreach ($modules as $module) {
            $result[$module] = $moduleNames[$module] ?? ucfirst(str_replace('_', ' ', $module));
        }

        return $result;
    }

    /**
     * Check if a specific module is affected
     */
    public function isModuleAffected(string $module): bool
    {
        $modules = $this->affected_modules ?? [];
        return in_array($module, $modules);
    }

    /**
     * Check if a feature is restricted
     */
    public function isFeatureRestricted(string $feature): bool
    {
        $features = $this->restricted_features ?? [];
        return in_array($feature, $features);
    }

    /**
     * Check if an operation is allowed
     */
    public function isOperationAllowed(string $operation): bool
    {
        $operations = $this->allowed_operations ?? [];
        return in_array($operation, $operations);
    }

    /**
     * Generate reference ID
     */
    public static function generateReferenceId(): string
    {
        return 'EM_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Get emergency mode statistics
     */
    public static function getStatistics(): array
    {
        $total = self::count();
        $active = self::where('is_active', true)->count();
        $scheduled = self::whereNotNull('scheduled_start')->where('is_active', false)->count();
        $completed = self::whereNotNull('deactivated_at')->count();
        
        // Average duration
        $avgDuration = self::whereNotNull('duration_minutes')
            ->where('duration_minutes', '>', 0)
            ->avg('duration_minutes');
        
        // Most common severity
        $commonSeverity = self::select('severity_level')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity_level')
            ->orderByDesc('count')
            ->first();

        return [
            'total_emergencies' => $total,
            'active_emergencies' => $active,
            'scheduled_emergencies' => $scheduled,
            'completed_emergencies' => $completed,
            'avg_duration_minutes' => round($avgDuration ?? 0),
            'most_common_severity' => $commonSeverity->severity_level ?? 'medium',
            'most_common_severity_count' => $commonSeverity->count ?? 0,
        ];
    }
}