<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class MaintenanceLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'maintenance_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maintenance_id',
        'action',
        'details',
        'metadata',
        'performed_by',
        'performed_by_type',
        'performed_by_name',
        'ip_address',
        'user_agent',
        'related_model_type',
        'related_model_id',
        'severity',
        'tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Action constants
     */
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_APPROVED = 'approved';
    const ACTION_STARTED = 'started';
    const ACTION_COMPLETED = 'completed';
    const ACTION_CANCELLED = 'cancelled';
    const ACTION_NOTIFIED = 'notified';
    const ACTION_RESCHEDULED = 'rescheduled';
    const ACTION_ESCALATED = 'escalated';
    const ACTION_COMMENT_ADDED = 'comment_added';
    const ACTION_FILE_UPLOADED = 'file_uploaded';
    const ACTION_STATUS_CHANGED = 'status_changed';
    const ACTION_USER_NOTIFIED = 'user_notified';
    const ACTION_SYSTEM_CHECK = 'system_check';
    const ACTION_EMERGENCY_LINKED = 'emergency_linked';

    /**
     * Severity constants
     */
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_SUCCESS = 'success';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the maintenance that owns the log.
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the related model (polymorphic).
     */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by severity.
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to filter by performer.
     */
    public function scopeByPerformer($query, $performerId)
    {
        return $query->where('performed_by', $performerId);
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
     * Scope a query to filter by tags.
     */
    public function scopeWithTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
        
        return $query;
    }

    /**
     * Scope a query to get recent logs.
     */
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope a query to get critical logs.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL)
                    ->orWhere('severity', self::SEVERITY_ERROR);
    }

    /**
     * Get all available actions.
     */
    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_STARTED => 'Started',
            self::ACTION_COMPLETED => 'Completed',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_NOTIFIED => 'Notified',
            self::ACTION_RESCHEDULED => 'Rescheduled',
            self::ACTION_ESCALATED => 'Escalated',
            self::ACTION_COMMENT_ADDED => 'Comment Added',
            self::ACTION_FILE_UPLOADED => 'File Uploaded',
            self::ACTION_STATUS_CHANGED => 'Status Changed',
            self::ACTION_USER_NOTIFIED => 'User Notified',
            self::ACTION_SYSTEM_CHECK => 'System Check',
            self::ACTION_EMERGENCY_LINKED => 'Emergency Linked',
        ];
    }

    /**
     * Get all available severities.
     */
    public static function getAvailableSeverities(): array
    {
        return [
            self::SEVERITY_INFO => 'Info',
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_ERROR => 'Error',
            self::SEVERITY_CRITICAL => 'Critical',
            self::SEVERITY_SUCCESS => 'Success',
        ];
    }

    /**
     * Get all available statuses.
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    /**
     * Get the severity color.
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            self::SEVERITY_CRITICAL => 'danger',
            self::SEVERITY_ERROR => 'danger',
            self::SEVERITY_WARNING => 'warning',
            self::SEVERITY_SUCCESS => 'success',
            default => 'info',
        };
    }

    /**
     * Get the action icon.
     */
    public function getActionIconAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'plus-circle',
            self::ACTION_UPDATED => 'edit',
            self::ACTION_APPROVED => 'check-circle',
            self::ACTION_STARTED => 'play-circle',
            self::ACTION_COMPLETED => 'flag-checkered',
            self::ACTION_CANCELLED => 'times-circle',
            self::ACTION_NOTIFIED => 'bell',
            self::ACTION_RESCHEDULED => 'calendar-alt',
            self::ACTION_ESCALATED => 'exclamation-triangle',
            self::ACTION_COMMENT_ADDED => 'comment',
            self::ACTION_FILE_UPLOADED => 'paperclip',
            self::ACTION_STATUS_CHANGED => 'exchange-alt',
            self::ACTION_USER_NOTIFIED => 'user-check',
            self::ACTION_SYSTEM_CHECK => 'stethoscope',
            self::ACTION_EMERGENCY_LINKED => 'link',
            default => 'history',
        };
    }

    /**
     * Get the time ago formatted.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get formatted metadata for display.
     */
    public function getFormattedMetadataAttribute(): array
    {
        $metadata = $this->metadata ?? [];
        
        // Add user-friendly labels
        $formatted = [];
        
        foreach ($metadata as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', $key));
            
            if (is_array($value)) {
                $formatted[$label] = json_encode($value, JSON_PRETTY_PRINT);
            } elseif ($value instanceof \Carbon\Carbon) {
                $formatted[$label] = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $formatted[$label] = $value ? 'Yes' : 'No';
            } else {
                $formatted[$label] = $value;
            }
        }
        
        return $formatted;
    }

    /**
     * Check if log is critical.
     */
    public function getIsCriticalAttribute(): bool
    {
        return in_array($this->severity, [self::SEVERITY_CRITICAL, self::SEVERITY_ERROR]);
    }

    /**
     * Check if log is successful.
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->severity === self::SEVERITY_SUCCESS;
    }

    /**
     * Get the performer name with fallback.
     */
    public function getPerformerNameAttribute(): string
    {
        return $this->performed_by_name ?? $this->performer?->name ?? 'System';
    }

    /**
     * Get the performer type.
     */
    public function getPerformerTypeAttribute(): string
    {
        return $this->performed_by_type ?? $this->performer?->type ?? 'system';
    }

    /**
     * Create a maintenance log.
     */
    public static function createLog(array $data): self
    {
        $defaults = [
            'performed_by' => Auth::id(),
            'performed_by_name' => Auth::check() ? Auth::user()->name : 'System',
            'performed_by_type' => Auth::check() ? Auth::user()->type : 'system',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'severity' => self::SEVERITY_INFO,
            'metadata' => [],
            'tags' => [],
        ];

        return self::create(array_merge($defaults, $data));
    }

    /**
     * Create a log for maintenance creation.
     */
    public static function logCreation(Maintenance $maintenance, array $metadata = []): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_CREATED,
            'details' => 'Maintenance schedule created',
            'metadata' => array_merge($metadata, [
                'maintenance_title' => $maintenance->title,
                'maintenance_type' => $maintenance->maintenance_type,
                'scheduled_start' => $maintenance->scheduled_start,
                'scheduled_end' => $maintenance->scheduled_end,
                'impact_level' => $maintenance->impact_level,
            ]),
            'tags' => ['creation', 'maintenance'],
        ]);
    }

    /**
     * Create a log for maintenance approval.
     */
    public static function logApproval(Maintenance $maintenance, User $approver, ?string $notes = null): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_APPROVED,
            'details' => 'Maintenance schedule approved' . ($notes ? " - {$notes}" : ''),
            'metadata' => [
                'approver_id' => $approver->id,
                'approver_name' => $approver->name,
                'notes' => $notes,
                'approved_at' => now(),
            ],
            'severity' => self::SEVERITY_SUCCESS,
            'tags' => ['approval', 'maintenance'],
        ]);
    }

    /**
     * Create a log for maintenance start.
     */
    public static function logStart(Maintenance $maintenance, User $starter, ?string $notes = null): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_STARTED,
            'details' => 'Maintenance started' . ($notes ? " - {$notes}" : ''),
            'metadata' => [
                'starter_id' => $starter->id,
                'starter_name' => $starter->name,
                'notes' => $notes,
                'actual_start' => $maintenance->actual_start,
            ],
            'severity' => self::SEVERITY_WARNING,
            'tags' => ['start', 'maintenance'],
        ]);
    }

    /**
     * Create a log for maintenance completion.
     */
    public static function logCompletion(Maintenance $maintenance, User $completer, array $data = []): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_COMPLETED,
            'details' => 'Maintenance completed successfully',
            'metadata' => array_merge($data, [
                'completer_id' => $completer->id,
                'completer_name' => $completer->name,
                'actual_end' => $maintenance->actual_end,
                'actual_duration_minutes' => $maintenance->actual_duration_minutes,
                'affected_users' => $maintenance->actual_affected_users,
            ]),
            'severity' => self::SEVERITY_SUCCESS,
            'tags' => ['completion', 'maintenance'],
        ]);
    }

    /**
     * Create a log for maintenance cancellation.
     */
    public static function logCancellation(Maintenance $maintenance, User $canceller, string $reason): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_CANCELLED,
            'details' => 'Maintenance cancelled - ' . $reason,
            'metadata' => [
                'canceller_id' => $canceller->id,
                'canceller_name' => $canceller->name,
                'reason' => $reason,
                'previous_status' => $maintenance->getOriginal('status'),
            ],
            'severity' => self::SEVERITY_WARNING,
            'tags' => ['cancellation', 'maintenance'],
        ]);
    }

    /**
     * Create a log for maintenance update.
     */
    public static function logUpdate(Maintenance $maintenance, User $updater, array $changes, ?string $notes = null): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_UPDATED,
            'details' => 'Maintenance schedule updated' . ($notes ? " - {$notes}" : ''),
            'metadata' => [
                'updater_id' => $updater->id,
                'updater_name' => $updater->name,
                'changes' => $changes,
                'notes' => $notes,
            ],
            'tags' => ['update', 'maintenance'],
        ]);
    }

    /**
     * Create a log for user notification.
     */
    public static function logNotification(Maintenance $maintenance, int $userCount, array $channels): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_USER_NOTIFIED,
            'details' => "Notified {$userCount} users about maintenance",
            'metadata' => [
                'user_count' => $userCount,
                'channels' => $channels,
                'notification_time' => now(),
            ],
            'severity' => self::SEVERITY_INFO,
            'tags' => ['notification', 'users'],
        ]);
    }

    /**
     * Create a log for system check.
     */
    public static function logSystemCheck(Maintenance $maintenance, array $results, bool $passed = true): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_SYSTEM_CHECK,
            'details' => 'System check ' . ($passed ? 'passed' : 'failed'),
            'metadata' => [
                'results' => $results,
                'passed' => $passed,
                'check_time' => now(),
            ],
            'severity' => $passed ? self::SEVERITY_SUCCESS : self::SEVERITY_ERROR,
            'tags' => ['system_check', 'validation'],
        ]);
    }

    /**
     * Create a log for emergency mode linking.
     */
    public static function logEmergencyLink(Maintenance $maintenance, EmergencyMode $emergencyMode): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_EMERGENCY_LINKED,
            'details' => "Linked to emergency mode: {$emergencyMode->name}",
            'metadata' => [
                'emergency_id' => $emergencyMode->id,
                'emergency_name' => $emergencyMode->name,
                'emergency_reference' => $emergencyMode->reference_id,
                'link_time' => now(),
            ],
            'severity' => self::SEVERITY_WARNING,
            'tags' => ['emergency', 'link'],
        ]);
    }

    /**
     * Create a log for status change.
     */
    public static function logStatusChange(Maintenance $maintenance, string $oldStatus, string $newStatus, User $changer): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_STATUS_CHANGED,
            'details' => "Status changed from {$oldStatus} to {$newStatus}",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changer_id' => $changer->id,
                'changer_name' => $changer->name,
            ],
            'severity' => self::SEVERITY_INFO,
            'tags' => ['status_change', 'maintenance'],
        ]);
    }

    /**
     * Create a log for comment addition.
     */
    public static function logComment(Maintenance $maintenance, User $commenter, string $comment): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_COMMENT_ADDED,
            'details' => 'Comment added: ' . Str::limit($comment, 100),
            'metadata' => [
                'commenter_id' => $commenter->id,
                'commenter_name' => $commenter->name,
                'comment_preview' => Str::limit($comment, 200),
            ],
            'severity' => self::SEVERITY_INFO,
            'tags' => ['comment', 'discussion'],
        ]);
    }

    /**
     * Create a log for file upload.
     */
    public static function logFileUpload(Maintenance $maintenance, User $uploader, string $filename, array $metadata = []): self
    {
        return self::createLog([
            'maintenance_id' => $maintenance->id,
            'action' => self::ACTION_FILE_UPLOADED,
            'details' => "File uploaded: {$filename}",
            'metadata' => array_merge($metadata, [
                'uploader_id' => $uploader->id,
                'uploader_name' => $uploader->name,
                'filename' => $filename,
                'upload_time' => now(),
            ]),
            'severity' => self::SEVERITY_INFO,
            'tags' => ['file_upload', 'attachment'],
        ]);
    }

    /**
     * Get statistics for maintenance logs.
     */
    public static function getStatistics(?int $maintenanceId = null): array
    {
        $query = self::query();
        
        if ($maintenanceId) {
            $query->where('maintenance_id', $maintenanceId);
        }
        
        $total = $query->count();
        $last24Hours = $query->where('created_at', '>=', now()->subDay())->count();
        
        $severityStats = $query->clone()
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->get()
            ->pluck('count', 'severity')
            ->toArray();
        
        $actionStats = $query->clone()
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->pluck('count', 'action')
            ->toArray();
        
        $performerStats = $query->clone()
            ->selectRaw('performed_by_name, COUNT(*) as count')
            ->groupBy('performed_by_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->pluck('count', 'performed_by_name')
            ->toArray();
        
        $criticalCount = $query->clone()
            ->whereIn('severity', [self::SEVERITY_CRITICAL, self::SEVERITY_ERROR])
            ->count();
        
        return [
            'total_logs' => $total,
            'last_24_hours' => $last24Hours,
            'severity_distribution' => $severityStats,
            'top_actions' => $actionStats,
            'top_performers' => $performerStats,
            'critical_count' => $criticalCount,
            'average_per_day' => $total > 0 ? round($total / max(1, $query->clone()->distinct('DATE(created_at)')->count('DATE(created_at)')), 2) : 0,
        ];
    }

    /**
     * Get trends for maintenance logs.
     */
    public static function getTrends(int $days = 30, ?int $maintenanceId = null): array
    {
        $trends = [];
        $endDate = now();
        $startDate = now()->subDays($days);
        
        $query = self::query()
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        if ($maintenanceId) {
            $query->where('maintenance_id', $maintenanceId);
        }
        
        // Daily trends
        $dailyTrends = $query->clone()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Severity trends
        $severityTrends = $query->clone()
            ->selectRaw('severity, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('severity', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('severity');
        
        // Action trends
        $actionTrends = $query->clone()
            ->selectRaw('action, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('action', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('action');
        
        // Peak hours
        $peakHours = $query->clone()
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        
        return [
            'daily_trends' => $dailyTrends,
            'severity_trends' => $severityTrends,
            'action_trends' => $actionTrends,
            'peak_hours' => $peakHours,
            'time_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $days,
            ],
        ];
    }

    /**
     * Search logs by criteria.
     */
    public static function search(array $criteria)
    {
        $query = self::query();
        
        if (isset($criteria['maintenance_id'])) {
            $query->where('maintenance_id', $criteria['maintenance_id']);
        }
        
        if (isset($criteria['action'])) {
            $query->where('action', $criteria['action']);
        }
        
        if (isset($criteria['severity'])) {
            $query->where('severity', $criteria['severity']);
        }
        
        if (isset($criteria['performed_by'])) {
            $query->where('performed_by', $criteria['performed_by']);
        }
        
        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function($q) use ($search) {
                $q->where('details', 'like', "%{$search}%")
                  ->orWhere('performed_by_name', 'like', "%{$search}%");
            });
        }
        
        if (isset($criteria['date_from'])) {
            $query->whereDate('created_at', '>=', $criteria['date_from']);
        }
        
        if (isset($criteria['date_to'])) {
            $query->whereDate('created_at', '<=', $criteria['date_to']);
        }
        
        if (isset($criteria['tags'])) {
            $query->whereJsonContains('tags', $criteria['tags']);
        }
        
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Clean up old logs based on retention policy.
     */
    public static function cleanup(int $retentionDays = 90): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        $logsToDelete = self::where('created_at', '<', $cutoffDate)->count();
        
        // You can choose to delete or archive
        $deleted = self::where('created_at', '<', $cutoffDate)->delete();
        
        return [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
            'logs_to_delete' => $logsToDelete,
            'logs_deleted' => $deleted,
            'cleanup_time' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Export logs to array format.
     */
    public function toExportArray(): array
    {
        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'maintenance_title' => $this->maintenance?->title,
            'action' => $this->action,
            'action_label' => self::getAvailableActions()[$this->action] ?? $this->action,
            'details' => $this->details,
            'severity' => $this->severity,
            'severity_label' => self::getAvailableSeverities()[$this->severity] ?? $this->severity,
            'performed_by' => $this->performer_name,
            'performed_by_type' => $this->performer_type,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->formatted_metadata,
            'tags' => implode(', ', $this->tags ?? []),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'time_ago' => $this->time_ago,
        ];
    }
}