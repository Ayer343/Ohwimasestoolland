<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Maintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'maintenances';
    
    protected $fillable = [
        'title',
        'reference_id',
        'description',
        'technical_details',
        'user_impact_description',
        'status',
        'impact_level',
        'maintenance_type',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'completed_within_estimate',
        'completion_verified_at',
        'affected_modules',
        'affected_services',
        'affected_features',
        'allowed_operations',
        'affected_user_types',
        'estimated_affected_users',
        'actual_affected_users',
        'notify_users',
        'notification_channels',
        'notified_at',
        'notification_sent_at',
        'reminder_sent_at',
        'is_emergency',
        'emergency_reason',
        'related_emergency_id',
        'has_rollback_plan',
        'rollback_procedure',
        'rollback_conditions',
        'rollback_initiated_at',
        'rollback_completed_at',
        'pre_maintenance_checks',
        'post_maintenance_checks',
        'all_checks_passed',
        'checks_completed_at',
        'change_log',
        'dependencies',
        'team_members',
        'communication_log',
        'downtime_minutes',
        'performance_impact',
        'user_feedback',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'settings',
        'environment_changes',
        'feature_flags',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'notified_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rollback_initiated_at' => 'datetime',
        'rollback_completed_at' => 'datetime',
        'checks_completed_at' => 'datetime',
        'completion_verified_at' => 'datetime',
        'is_emergency' => 'boolean',
        'notify_users' => 'boolean',
        'has_rollback_plan' => 'boolean',
        'all_checks_passed' => 'boolean',
        'completed_within_estimate' => 'boolean',
        'acknowledged' => 'boolean',
        'estimated_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'estimated_affected_users' => 'integer',
        'actual_affected_users' => 'integer',
        'downtime_minutes' => 'decimal:2',
        'affected_modules' => 'array',
        'affected_services' => 'array',
        'affected_features' => 'array',
        'allowed_operations' => 'array',
        'affected_user_types' => 'array',
        'notification_channels' => 'array',
        'rollback_conditions' => 'array',
        'pre_maintenance_checks' => 'array',
        'post_maintenance_checks' => 'array',
        'change_log' => 'array',
        'dependencies' => 'array',
        'team_members' => 'array',
        'communication_log' => 'array',
        'performance_impact' => 'array',
        'user_feedback' => 'array',
        'settings' => 'array',
        'environment_changes' => 'array',
        'feature_flags' => 'array',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DELAYED = 'delayed';
    
    // Impact levels
    const IMPACT_LOW = 'low';
    const IMPACT_MEDIUM = 'medium';
    const IMPACT_HIGH = 'high';
    const IMPACT_CRITICAL = 'critical';
    
    // Maintenance types
    const TYPE_PLANNED = 'planned';
    const TYPE_EMERGENCY = 'emergency';
    const TYPE_HOTFIX = 'hotfix';
    const TYPE_UPGRADE = 'upgrade';
    const TYPE_SECURITY = 'security';

    /**
     * Scope for active maintenance (in progress)
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope for scheduled maintenance
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope for upcoming maintenance (next 24 hours)
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->where('scheduled_start', '<=', now()->addDay())
                    ->where('scheduled_start', '>=', now());
    }

    /**
     * Scope for completed maintenance
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for emergency maintenance
     */
    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }

    /**
     * Get maintenance logs
     */
    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    /**
     * Get affected users
     */
    public function affectedUsers()
    {
        return $this->hasMany(MaintenanceAffectedUser::class);
    }

    /**
     * User who created the maintenance
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who approved the maintenance
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who completed the maintenance
     */
    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Related emergency mode
     */
    public function emergencyMode()
    {
        return $this->belongsTo(EmergencyMode::class, 'related_emergency_id');
    }

    /**
     * Calculate time remaining until scheduled start
     */
    public function getTimeUntilStartAttribute(): ?string
    {
        if (!$this->scheduled_start || $this->status !== self::STATUS_SCHEDULED) {
            return null;
        }

        $now = now();
        if ($this->scheduled_start->isPast()) {
            return 'Already started';
        }

        $diff = $now->diff($this->scheduled_start);
        
        $parts = [];
        if ($diff->d > 0) $parts[] = "{$diff->d}d";
        if ($diff->h > 0) $parts[] = "{$diff->h}h";
        if ($diff->i > 0) $parts[] = "{$diff->i}m";
        
        return implode(' ', $parts) ?: 'Less than a minute';
    }

    /**
     * Calculate current duration if in progress
     */
    public function getCurrentDurationAttribute(): ?int
    {
        if ($this->status !== self::STATUS_IN_PROGRESS || !$this->actual_start) {
            return null;
        }

        return now()->diffInMinutes($this->actual_start);
    }

    /**
     * Calculate total duration if completed
     */
    public function getTotalDurationAttribute(): ?int
    {
        if ($this->status !== self::STATUS_COMPLETED || !$this->actual_start || !$this->actual_end) {
            return null;
        }

        return $this->actual_start->diffInMinutes($this->actual_end);
    }

    /**
     * Check if maintenance is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS || !$this->scheduled_end) {
            return false;
        }

        return now()->greaterThan($this->scheduled_end);
    }

    /**
     * Check if maintenance is starting soon (within 1 hour)
     */
    public function getIsStartingSoonAttribute(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED || !$this->scheduled_start) {
            return false;
        }

        return $this->scheduled_start->diffInMinutes(now()) <= 60;
    }

    /**
     * Get affected modules as array with names
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
            'storage' => 'File Storage',
            'cache' => 'Cache System',
            'email' => 'Email Service',
            'sms' => 'SMS Service',
            'whatsapp' => 'WhatsApp Service',
        ];

        $result = [];
        foreach ($modules as $module) {
            $result[$module] = $moduleNames[$module] ?? ucfirst(str_replace('_', ' ', $module));
        }

        return $result;
    }

    /**
     * Check if maintenance affects a specific module
     */
    public function affectsModule(string $module): bool
    {
        $modules = $this->affected_modules ?? [];
        return in_array($module, $modules);
    }

    /**
     * Check if maintenance affects a specific user type
     */
    public function affectsUserType(string $userType): bool
    {
        $userTypes = $this->affected_user_types ?? [];
        return in_array($userType, $userTypes);
    }

    /**
     * Generate reference ID
     */
    public static function generateReferenceId(): string
    {
        return 'MT_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    // In App\Models\Maintenance.php

/**
 * Get maintenance statistics
 */
public static function getStatistics()
{
    $total = self::count();
    $completed = self::where('status', 'completed')->count();
    $cancelled = self::where('status', 'cancelled')->count();
    $inProgress = self::where('status', 'in_progress')->count();
    $scheduled = self::where('status', 'scheduled')->count();
    $draft = self::where('status', 'draft')->count();
    
    // Duration statistics
    $durationStats = self::where('status', 'completed')
        ->selectRaw('AVG(estimated_duration_minutes) as avg_estimated, 
                     AVG(actual_duration_minutes) as avg_actual,
                     AVG(downtime_minutes) as avg_downtime,
                     SUM(estimated_duration_minutes) as total_estimated,
                     SUM(actual_duration_minutes) as total_actual')
        ->first();
    
    // Accuracy statistics
    $accuracyStats = self::where('status', 'completed')
        ->selectRaw('AVG(CASE WHEN completed_within_estimate = 1 THEN 100 ELSE 0 END) as on_time_rate,
                     AVG(CASE WHEN actual_affected_users > 0 
                          THEN (estimated_affected_users / actual_affected_users) * 100 
                          ELSE 0 END) as user_impact_accuracy')
        ->first();
    
    // Calculate duration accuracy
    $avgEstimated = $durationStats->avg_estimated ?? 0;
    $avgActual = $durationStats->avg_actual ?? 0;
    $durationAccuracy = 0;
    
    if ($avgEstimated > 0 && $avgActual > 0) {
        $variance = abs($avgEstimated - $avgActual) / $avgEstimated;
        $durationAccuracy = round((1 - $variance) * 100);
        $durationAccuracy = max(0, min(100, $durationAccuracy)); // Ensure between 0-100
    }
    
    // Calculate downtime efficiency
    $avgDowntime = $durationStats->avg_downtime ?? 0;
    $downtimeEfficiency = 100;
    
    if ($avgActual > 0 && $avgDowntime > 0) {
        $downtimeEfficiency = round((1 - ($avgDowntime / $avgActual)) * 100);
        $downtimeEfficiency = max(0, min(100, $downtimeEfficiency));
    }
    
    // Get average affected users
    $userStats = self::where('status', 'completed')
        ->selectRaw('AVG(estimated_affected_users) as avg_estimated_users,
                     AVG(actual_affected_users) as avg_actual_users')
        ->first();
    
    return [
        'total' => $total,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'in_progress' => $inProgress,
        'scheduled' => $scheduled,
        'draft' => $draft,
        'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
        'avg_duration' => round($avgEstimated),
        'avg_actual_duration' => round($avgActual),
        'avg_duration_variance' => $avgEstimated > 0 
            ? round(abs(($avgEstimated - $avgActual) / $avgEstimated) * 100)
            : 0,
        'duration_accuracy' => $durationAccuracy,
        'on_time_rate' => round($accuracyStats->on_time_rate ?? 0),
        'user_impact_accuracy' => round($accuracyStats->user_impact_accuracy ?? 0),
        'downtime_efficiency' => $downtimeEfficiency,
        'avg_estimated_users' => round($userStats->avg_estimated_users ?? 0),
        'avg_actual_users' => round($userStats->avg_actual_users ?? 0),
        'total_estimated_minutes' => round($durationStats->total_estimated ?? 0),
        'total_actual_minutes' => round($durationStats->total_actual ?? 0),
    ];
}

/**
 * Get maintenance trends
 */
public static function getTrends()
{
    // Get monthly trends for the last 6 months
    $monthly = self::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
        ->where('created_at', '>=', now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->pluck('count', 'month');
    
    // Get weekly trends for the last 8 weeks
    $weekly = self::selectRaw("YEARWEEK(created_at, 1) as week, COUNT(*) as count")
        ->where('created_at', '>=', now()->subWeeks(8))
        ->groupBy('week')
        ->orderBy('week')
        ->pluck('count', 'week');
    
    // Format monthly labels (e.g., "Jan 2024")
    $formattedMonthly = collect();
    foreach ($monthly as $month => $count) {
        $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
        $formattedMonthly[$date->format('M Y')] = $count;
    }
    
    // Format weekly labels (e.g., "Week 12")
    $formattedWeekly = collect();
    foreach ($weekly as $week => $count) {
        $formattedWeekly["Week " . substr($week, -2)] = $count;
    }
    
    return [
        'monthly' => $formattedMonthly,
        'weekly' => $formattedWeekly,
        'monthly_max' => $monthly->max() ?? 0,
        'weekly_max' => $weekly->max() ?? 0,
    ];
}

    // In your Maintenance model
/**
 * Get trash statistics
 */
public static function getTrashStatistics()
{
    $total = self::onlyTrashed()->count();
    $oldest = self::onlyTrashed()->orderBy('deleted_at')->first();
    $newest = self::onlyTrashed()->orderBy('deleted_at', 'desc')->first();
    
    // Get count by status before deletion
    $statusDistribution = self::onlyTrashed()
        ->select('status', \DB::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->get()
        ->pluck('count', 'status');
    
    // Get count by user type
    $userDistribution = \DB::table('maintenances')
        ->join('users', 'maintenances.deleted_by', '=', 'users.id')
        ->select('users.type', \DB::raw('COUNT(*) as count'))
        ->whereNotNull('maintenances.deleted_at')
        ->groupBy('users.type')
        ->get()
        ->pluck('count', 'type');
    
    return [
        'total' => $total,
        'oldest' => $oldest,
        'newest' => $newest,
        'status_distribution' => $statusDistribution,
        'user_distribution' => $userDistribution,
        'estimated_size_kb' => $total * 2, // Rough estimate
    ];
}

}