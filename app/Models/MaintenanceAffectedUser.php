<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MaintenanceAffectedUser extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'maintenance_affected_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maintenance_id',
        'user_id',
        'user_type',
        'affected_modules',
        'notified_at',
        'notification_channels',
        'acknowledged',
        'acknowledged_at',
        'impact_level',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'affected_modules' => 'array',
        'notification_channels' => 'array',
        'acknowledged' => 'boolean',
        'notified_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Impact level constants
     */
    const IMPACT_LOW = 'low';
    const IMPACT_MEDIUM = 'medium';
    const IMPACT_HIGH = 'high';
    const IMPACT_CRITICAL = 'critical';

    /**
     * Get the maintenance that owns the affected user record.
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    /**
     * Get the user that is affected.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by maintenance.
     */
    public function scopeByMaintenance($query, $maintenanceId)
    {
        return $query->where('maintenance_id', $maintenanceId);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by user type.
     */
    public function scopeByUserType($query, $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope a query to filter by impact level.
     */
    public function scopeByImpactLevel($query, $impactLevel)
    {
        return $query->where('impact_level', $impactLevel);
    }

    /**
     * Scope a query to filter by acknowledged status.
     */
    public function scopeAcknowledged($query, $acknowledged = true)
    {
        return $query->where('acknowledged', $acknowledged);
    }

    /**
     * Scope a query to filter by notified status.
     */
    public function scopeNotified($query, $notified = true)
    {
        if ($notified) {
            return $query->whereNotNull('notified_at');
        }
        return $query->whereNull('notified_at');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->whereDate('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Scope a query to get users affected by specific modules.
     */
    public function scopeByAffectedModule($query, $module)
    {
        return $query->whereJsonContains('affected_modules', $module);
    }

    /**
     * Scope a query to get recent affected users.
     */
    public function scopeRecent($query, $limit = 100)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Get all available impact levels.
     */
    public static function getAvailableImpactLevels(): array
    {
        return [
            self::IMPACT_LOW => 'Low',
            self::IMPACT_MEDIUM => 'Medium',
            self::IMPACT_HIGH => 'High',
            self::IMPACT_CRITICAL => 'Critical',
        ];
    }

    /**
     * Get the impact level color.
     */
    public function getImpactLevelColorAttribute(): string
    {
        return match($this->impact_level) {
            self::IMPACT_CRITICAL => 'danger',
            self::IMPACT_HIGH => 'danger',
            self::IMPACT_MEDIUM => 'warning',
            default => 'info',
        };
    }

    /**
     * Get the affected modules as a comma-separated string.
     */
    public function getAffectedModulesListAttribute(): string
    {
        return $this->affected_modules ? implode(', ', $this->affected_modules) : 'N/A';
    }

    /**
     * Get the notification channels as a comma-separated string.
     */
    public function getNotificationChannelsListAttribute(): string
    {
        return $this->notification_channels ? implode(', ', $this->notification_channels) : 'N/A';
    }

    /**
     * Get the user type name.
     */
    public function getUserTypeNameAttribute(): string
    {
        $userTypes = [
            '0' => 'Super Admin',
            '1' => 'Admin',
            '2' => 'Landlord',
            '3' => 'Tenant',
            '4' => 'Field Agent',
            '5' => 'Developer',
            '6' => 'Security Checkpoint',
        ];
        
        return $userTypes[$this->user_type] ?? 'Unknown';
    }

    /**
     * Check if user is notified.
     */
    public function getIsNotifiedAttribute(): bool
    {
        return !is_null($this->notified_at);
    }

    /**
     * Get the time since notification.
     */
    public function getTimeSinceNotificationAttribute(): ?string
    {
        return $this->notified_at ? $this->notified_at->diffForHumans() : null;
    }

    /**
     * Get the time since acknowledgment.
     */
    public function getTimeSinceAcknowledgmentAttribute(): ?string
    {
        return $this->acknowledged_at ? $this->acknowledged_at->diffForHumans() : null;
    }

    /**
     * Get the notification status.
     */
    public function getNotificationStatusAttribute(): string
    {
        if ($this->acknowledged) {
            return 'Acknowledged';
        }
        
        if ($this->is_notified) {
            return 'Notified';
        }
        
        return 'Pending';
    }

    /**
     * Get the notification status color.
     */
    public function getNotificationStatusColorAttribute(): string
    {
        if ($this->acknowledged) {
            return 'success';
        }
        
        if ($this->is_notified) {
            return 'warning';
        }
        
        return 'danger';
    }

    /**
     * Mark user as notified.
     */
    public function markAsNotified(array $channels = []): bool
    {
        $this->notified_at = now();
        $this->notification_channels = $channels ?: ['email', 'in_app'];
        return $this->save();
    }

    /**
     * Mark user as acknowledged.
     */
    public function markAsAcknowledged(?string $notes = null): bool
    {
        $this->acknowledged = true;
        $this->acknowledged_at = now();
        $this->notes = $notes;
        return $this->save();
    }

    /**
     * Update impact level.
     */
    public function updateImpactLevel(string $impactLevel): bool
    {
        $this->impact_level = $impactLevel;
        return $this->save();
    }

    /**
     * Add affected modules.
     */
    public function addAffectedModules(array $modules): bool
    {
        $currentModules = $this->affected_modules ?: [];
        $newModules = array_unique(array_merge($currentModules, $modules));
        
        $this->affected_modules = $newModules;
        return $this->save();
    }

    /**
     * Remove affected modules.
     */
    public function removeAffectedModules(array $modules): bool
    {
        $currentModules = $this->affected_modules ?: [];
        $newModules = array_diff($currentModules, $modules);
        
        $this->affected_modules = array_values($newModules);
        return $this->save();
    }

    /**
     * Check if user is affected by specific module.
     */
    public function isAffectedByModule(string $module): bool
    {
        $modules = $this->affected_modules ?: [];
        return in_array($module, $modules);
    }

    /**
     * Create or update affected user record.
     */
    public static function recordAffectedUser(int $maintenanceId, int $userId, array $data = []): self
    {
        $existing = self::where('maintenance_id', $maintenanceId)
            ->where('user_id', $userId)
            ->first();
        
        if ($existing) {
            // Update existing record
            if (isset($data['affected_modules'])) {
                $existing->addAffectedModules($data['affected_modules']);
            }
            
            if (isset($data['impact_level'])) {
                $existing->updateImpactLevel($data['impact_level']);
            }
            
            return $existing;
        }
        
        // Create new record
        $defaults = [
            'user_type' => User::find($userId)->type ?? 'unknown',
            'affected_modules' => $data['affected_modules'] ?? [],
            'impact_level' => $data['impact_level'] ?? self::IMPACT_MEDIUM,
            'metadata' => $data['metadata'] ?? [],
        ];
        
        return self::create(array_merge([
            'maintenance_id' => $maintenanceId,
            'user_id' => $userId,
        ], $defaults, $data));
    }

    /**
     * Bulk record affected users.
     */
    public static function bulkRecordAffectedUsers(int $maintenanceId, array $userIds, array $data = []): int
    {
        $records = [];
        $now = now();
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                continue;
            }
            
            $records[] = [
                'maintenance_id' => $maintenanceId,
                'user_id' => $userId,
                'user_type' => $user->type,
                'affected_modules' => json_encode($data['affected_modules'] ?? []),
                'impact_level' => $data['impact_level'] ?? self::IMPACT_MEDIUM,
                'metadata' => json_encode($data['metadata'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        
        if (!empty($records)) {
            return DB::table('maintenance_affected_users')->insertOrIgnore($records);
        }
        
        return 0;
    }

    /**
     * Mark multiple users as notified.
     */
    public static function bulkMarkAsNotified(int $maintenanceId, array $userIds, array $channels = []): int
    {
        return self::where('maintenance_id', $maintenanceId)
            ->whereIn('user_id', $userIds)
            ->whereNull('notified_at')
            ->update([
                'notified_at' => now(),
                'notification_channels' => json_encode($channels ?: ['email', 'in_app']),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark multiple users as acknowledged.
     */
    public static function bulkMarkAsAcknowledged(int $maintenanceId, array $userIds, ?string $notes = null): int
    {
        return self::where('maintenance_id', $maintenanceId)
            ->whereIn('user_id', $userIds)
            ->where('acknowledged', false)
            ->update([
                'acknowledged' => true,
                'acknowledged_at' => now(),
                'notes' => $notes,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get statistics for affected users.
     */
    public static function getStatistics(?int $maintenanceId = null): array
    {
        $query = self::query();
        
        if ($maintenanceId) {
            $query->where('maintenance_id', $maintenanceId);
        }
        
        $total = $query->count();
        $notified = $query->clone()->notified()->count();
        $acknowledged = $query->clone()->acknowledged()->count();
        
        $impactStats = $query->clone()
            ->selectRaw('impact_level, COUNT(*) as count')
            ->groupBy('impact_level')
            ->get()
            ->pluck('count', 'impact_level')
            ->toArray();
        
        $userTypeStats = $query->clone()
            ->selectRaw('user_type, COUNT(*) as count')
            ->groupBy('user_type')
            ->get()
            ->pluck('count', 'user_type')
            ->toArray();
        
        $notificationRate = $total > 0 ? ($notified / $total) * 100 : 0;
        $acknowledgmentRate = $notified > 0 ? ($acknowledged / $notified) * 100 : 0;
        
        return [
            'total_affected' => $total,
            'notified_count' => $notified,
            'acknowledged_count' => $acknowledged,
            'notification_rate' => round($notificationRate, 2),
            'acknowledgment_rate' => round($acknowledgmentRate, 2),
            'impact_distribution' => $impactStats,
            'user_type_distribution' => $userTypeStats,
            'pending_notification' => $total - $notified,
            'pending_acknowledgment' => $notified - $acknowledged,
        ];
    }

    /**
     * Get impact analysis by user type.
     */
    public static function getImpactAnalysis(int $maintenanceId): array
    {
        $stats = self::getStatistics($maintenanceId);
        
        $analysis = [
            'total_impact' => [
                'affected_users' => $stats['total_affected'],
                'notification_coverage' => $stats['notification_rate'],
                'acknowledgment_rate' => $stats['acknowledgment_rate'],
                'overall_impact_level' => self::calculateOverallImpactLevel($stats['impact_distribution']),
            ],
            'by_user_type' => [],
            'recommendations' => [],
        ];
        
        // Analyze by user type
        foreach ($stats['user_type_distribution'] as $userType => $count) {
            $typeStats = self::where('maintenance_id', $maintenanceId)
                ->where('user_type', $userType)
                ->get();
            
            $notifiedCount = $typeStats->whereNotNull('notified_at')->count();
            $acknowledgedCount = $typeStats->where('acknowledged', true)->count();
            
            $impactLevels = $typeStats->groupBy('impact_level')->map->count();
            $dominantImpact = $impactLevels->sortDesc()->keys()->first() ?? self::IMPACT_MEDIUM;
            
            $analysis['by_user_type'][$userType] = [
                'total' => $count,
                'notified' => $notifiedCount,
                'acknowledged' => $acknowledgedCount,
                'notification_rate' => $count > 0 ? ($notifiedCount / $count) * 100 : 0,
                'acknowledgment_rate' => $notifiedCount > 0 ? ($acknowledgedCount / $notifiedCount) * 100 : 0,
                'dominant_impact_level' => $dominantImpact,
                'impact_distribution' => $impactLevels->toArray(),
            ];
        }
        
        // Generate recommendations
        $analysis['recommendations'] = self::generateRecommendations($stats);
        
        return $analysis;
    }

    /**
     * Calculate overall impact level.
     */
    private static function calculateOverallImpactLevel(array $impactDistribution): string
    {
        if (empty($impactDistribution)) {
            return self::IMPACT_MEDIUM;
        }
        
        $weights = [
            self::IMPACT_CRITICAL => 4,
            self::IMPACT_HIGH => 3,
            self::IMPACT_MEDIUM => 2,
            self::IMPACT_LOW => 1,
        ];
        
        $totalWeight = 0;
        $totalCount = 0;
        
        foreach ($impactDistribution as $level => $count) {
            if (isset($weights[$level])) {
                $totalWeight += $weights[$level] * $count;
                $totalCount += $count;
            }
        }
        
        if ($totalCount === 0) {
            return self::IMPACT_MEDIUM;
        }
        
        $averageWeight = $totalWeight / $totalCount;
        
        if ($averageWeight >= 3.5) {
            return self::IMPACT_CRITICAL;
        } elseif ($averageWeight >= 2.5) {
            return self::IMPACT_HIGH;
        } elseif ($averageWeight >= 1.5) {
            return self::IMPACT_MEDIUM;
        } else {
            return self::IMPACT_LOW;
        }
    }

    /**
     * Generate recommendations based on statistics.
     */
    private static function generateRecommendations(array $stats): array
    {
        $recommendations = [];
        
        if ($stats['notification_rate'] < 70) {
            $recommendations[] = 'Consider sending follow-up notifications to users who have not been notified yet.';
        }
        
        if ($stats['acknowledgment_rate'] < 50) {
            $recommendations[] = 'Low acknowledgment rate. Consider escalating notifications or sending reminders.';
        }
        
        if (isset($stats['impact_distribution'][self::IMPACT_CRITICAL]) && 
            $stats['impact_distribution'][self::IMPACT_CRITICAL] > 0) {
            $recommendations[] = 'Critical impact users detected. Prioritize communication and support for these users.';
        }
        
        if ($stats['total_affected'] > 100 && $stats['notification_rate'] < 80) {
            $recommendations[] = 'Large user base affected with low notification rate. Consider batch notifications or system-wide alerts.';
        }
        
        return $recommendations;
    }

    /**
     * Get users requiring follow-up.
     */
    public static function getUsersRequiringFollowUp(int $maintenanceId): array
    {
        // Users who were notified but haven't acknowledged
        $notifiedNotAcknowledged = self::where('maintenance_id', $maintenanceId)
            ->notified()
            ->where('acknowledged', false)
            ->with('user')
            ->get();
        
        // Users with critical impact who haven't been notified
        $criticalNotNotified = self::where('maintenance_id', $maintenanceId)
            ->where('impact_level', self::IMPACT_CRITICAL)
            ->whereNull('notified_at')
            ->with('user')
            ->get();
        
        // Users notified more than 24 hours ago without acknowledgment
        $staleNotifications = self::where('maintenance_id', $maintenanceId)
            ->where('acknowledged', false)
            ->where('notified_at', '<', now()->subDay())
            ->with('user')
            ->get();
        
        return [
            'notified_not_acknowledged' => $notifiedNotAcknowledged,
            'critical_not_notified' => $criticalNotNotified,
            'stale_notifications' => $staleNotifications,
            'total_follow_up_needed' => $notifiedNotAcknowledged->count() + 
                                       $criticalNotNotified->count() + 
                                       $staleNotifications->count(),
        ];
    }

    /**
     * Export affected users data.
     */
    public function toExportArray(): array
    {
        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'maintenance_title' => $this->maintenance?->title,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'user_email' => $this->user?->email,
            'user_type' => $this->user_type_name,
            'affected_modules' => $this->affected_modules_list,
            'impact_level' => $this->impact_level,
            'impact_level_label' => ucfirst($this->impact_level),
            'notified' => $this->is_notified,
            'notified_at' => $this->notified_at?->format('Y-m-d H:i:s'),
            'notification_channels' => $this->notification_channels_list,
            'acknowledged' => $this->acknowledged ? 'Yes' : 'No',
            'acknowledged_at' => $this->acknowledged_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'notification_status' => $this->notification_status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Clean up old affected user records.
     */
    public static function cleanup(int $retentionDays = 180): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        $recordsToDelete = self::where('created_at', '<', $cutoffDate)->count();
        
        // Only delete if maintenance is completed and old enough
        $deleted = self::where('created_at', '<', $cutoffDate)
            ->whereHas('maintenance', function ($query) {
                $query->where('status', Maintenance::STATUS_COMPLETED)
                      ->where('actual_end', '<', now()->subDays(30));
            })
            ->delete();
        
        return [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
            'records_to_delete' => $recordsToDelete,
            'records_deleted' => $deleted,
            'cleanup_time' => now()->format('Y-m-d H:i:s'),
        ];
    }
}