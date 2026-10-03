<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_logs';
    
    protected $fillable = [
        'reference_id',
        'log_group',
        'log_subgroup',
        'level',
        'severity',
        'channel',
        'source',
        'component',
        'message',
        'summary',
        'context',
        'extra',
        'tags',
        'file',
        'line',
        'trace',
        'trace_data',
        'exception_class',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'request_data',
        'request_headers',
        'response_data',
        'response_code',
        'response_time_ms',
        'user_id',
        'user_type',
        'session_id',
        'user_context',
        'affected_users',
        'affected_users_count',
        'affected_modules',
        'affected_features',
        'resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'resolution_metadata',
        'related_maintenance_id',
        'related_emergency_id',
        'related_entity_id',
        'related_entity_type',
        'occurrence_count',
        'first_occurrence_at',
        'last_occurrence_at',
        'frequency_per_hour',
        'is_recurring',
        'recurrence_pattern',
        'alert_sent',
        'alert_sent_at',
        'alert_recipients',
        'notification_channels',
        'requires_human_intervention',
        'intervention_status',
        'priority',
        'sla_hours',
        'sla_deadline',
        'sla_breached',
        'analytics_data',
        'metrics',
        'performance_impact_score',
        'business_impact_score',
        'archived',
        'archived_at',
        'retention_days',
        'expires_at',
        'created_by',
        'updated_by',
        'assigned_to',
        'assigned_at',
    ];

    protected $casts = [
        'context' => 'array',
        'extra' => 'array',
        'tags' => 'array',
        'trace_data' => 'array',
        'request_data' => 'array',
        'request_headers' => 'array',
        'response_data' => 'array',
        'user_context' => 'array',
        'affected_users' => 'array',
        'affected_modules' => 'array',
        'affected_features' => 'array',
        'resolution_metadata' => 'array',
        'recurrence_pattern' => 'array',
        'alert_recipients' => 'array',
        'notification_channels' => 'array',
        'analytics_data' => 'array',
        'metrics' => 'array',
        'resolved' => 'boolean',
        'alert_sent' => 'boolean',
        'requires_human_intervention' => 'boolean',
        'sla_breached' => 'boolean',
        'archived' => 'boolean',
        'is_recurring' => 'boolean',
        'first_occurrence_at' => 'datetime',
        'last_occurrence_at' => 'datetime',
        'resolved_at' => 'datetime',
        'alert_sent_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'response_time_ms' => 'decimal:2',
        'frequency_per_hour' => 'decimal:2',
        'performance_impact_score' => 'decimal:2',
        'business_impact_score' => 'decimal:2',
        'occurrence_count' => 'integer',
        'affected_users_count' => 'integer',
        'response_code' => 'integer',
        'line' => 'integer',
        'sla_hours' => 'integer',
        'retention_days' => 'integer',
        'size' => 'integer',
    ];

    // Log levels (RFC 5424)
    const LEVEL_EMERGENCY = 'emergency';
    const LEVEL_ALERT = 'alert';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    const LEVEL_TRACE = 'trace';
    
    // Severity levels
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';
    
    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';
    
    // Intervention status
    const INTERVENTION_PENDING = 'pending';
    const INTERVENTION_IN_PROGRESS = 'in_progress';
    const INTERVENTION_COMPLETED = 'completed';
    const INTERVENTION_ESCALATED = 'escalated';
    
    // Common log groups
    const GROUP_AUTHENTICATION = 'authentication';
    const GROUP_AUTHORIZATION = 'authorization';
    const GROUP_DATABASE = 'database';
    const GROUP_API = 'api';
    const GROUP_QUEUE = 'queue';
    const GROUP_CACHE = 'cache';
    const GROUP_STORAGE = 'storage';
    const GROUP_EMAIL = 'email';
    const GROUP_SMS = 'sms';
    const GROUP_WHATSAPP = 'whatsapp';
    const GROUP_PAYMENT = 'payment';
    const GROUP_MAINTENANCE = 'maintenance';
    const GROUP_EMERGENCY = 'emergency';
    const GROUP_SECURITY = 'security';
    const GROUP_PERFORMANCE = 'performance';
    const GROUP_BUSINESS = 'business';
    const GROUP_SYSTEM = 'system';
    const GROUP_APPLICATION = 'application';

    /**
     * Scope for error logs (error, critical, emergency)
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('level', [self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_EMERGENCY, self::LEVEL_ALERT]);
    }

    /**
     * Scope for unresolved logs
     */
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    /**
     * Scope for high severity logs
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Scope for high priority logs
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Scope for logs requiring human intervention
     */
    public function scopeRequiresIntervention($query)
    {
        return $query->where('requires_human_intervention', true);
    }

    /**
     * Scope for active logs (not archived)
     */
    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }

    /**
     * Scope for recurring logs
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope for logs with breached SLA
     */
    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    /**
     * Scope for logs from specific source
     */
    public function scopeFromSource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope for logs from specific component
     */
    public function scopeFromComponent($query, $component)
    {
        return $query->where('component', $component);
    }

    /**
     * Scope for logs within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate = null)
    {
        if (!$endDate) {
            $endDate = now();
        }
        
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for logs affecting specific user
     */
    public function scopeAffectsUser($query, $userId)
    {
        return $query->whereJsonContains('affected_users', (string)$userId)
                    ->orWhere('user_id', $userId);
    }

    /**
     * Scope for logs affecting specific module
     */
    public function scopeAffectsModule($query, $module)
    {
        return $query->whereJsonContains('affected_modules', $module);
    }

    /**
     * Get log comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(SystemLogComment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get log attachments
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SystemLogAttachment::class);
    }

    /**
     * User who created/triggered the log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who resolved the log
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * User assigned to handle the log
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Related maintenance
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class, 'related_maintenance_id');
    }

    /**
     * Related emergency mode
     */
    public function emergencyMode(): BelongsTo
    {
        return $this->belongsTo(EmergencyMode::class, 'related_emergency_id');
    }

    /**
     * Get related entity (polymorphic)
     */
    public function relatedEntity()
    {
        return $this->morphTo('related_entity');
    }

    /**
     * Generate reference ID
     */
    public static function generateReferenceId(): string
    {
        return 'LOG_' . now()->format('Ymd_His') . '_' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Calculate log priority based on level and severity
     */
    public static function calculatePriority($level, $severity): string
    {
        $priorityMatrix = [
            self::LEVEL_EMERGENCY => self::PRIORITY_CRITICAL,
            self::LEVEL_ALERT => self::PRIORITY_CRITICAL,
            self::LEVEL_CRITICAL => self::PRIORITY_HIGH,
            self::LEVEL_ERROR => self::PRIORITY_HIGH,
            self::LEVEL_WARNING => self::PRIORITY_MEDIUM,
            self::LEVEL_NOTICE => self::PRIORITY_LOW,
            self::LEVEL_INFO => self::PRIORITY_LOW,
            self::LEVEL_DEBUG => self::PRIORITY_LOW,
            self::LEVEL_TRACE => self::PRIORITY_LOW,
        ];
        
        $priority = $priorityMatrix[$level] ?? self::PRIORITY_MEDIUM;
        
        // Adjust based on severity
        if ($severity === self::SEVERITY_CRITICAL && $priority !== self::PRIORITY_CRITICAL) {
            $priority = self::PRIORITY_CRITICAL;
        } elseif ($severity === self::SEVERITY_HIGH && $priority === self::PRIORITY_LOW) {
            $priority = self::PRIORITY_MEDIUM;
        }
        
        return $priority;
    }

    /**
     * Calculate severity based on level
     */
    public static function calculateSeverity($level): string
    {
        return match($level) {
            self::LEVEL_EMERGENCY, self::LEVEL_ALERT, self::LEVEL_CRITICAL => self::SEVERITY_CRITICAL,
            self::LEVEL_ERROR => self::SEVERITY_HIGH,
            self::LEVEL_WARNING => self::SEVERITY_MEDIUM,
            self::LEVEL_NOTICE, self::LEVEL_INFO => self::SEVERITY_LOW,
            self::LEVEL_DEBUG, self::LEVEL_TRACE => self::SEVERITY_LOW,
            default => self::SEVERITY_MEDIUM,
        };
    }

    /**
     * Calculate SLA deadline based on priority
     */
    public static function calculateSlaDeadline($priority, $createdAt = null): ?\Carbon\Carbon
    {
        $slaHours = [
            self::PRIORITY_CRITICAL => 1,  // 1 hour
            self::PRIORITY_HIGH => 4,      // 4 hours
            self::PRIORITY_MEDIUM => 24,   // 24 hours
            self::PRIORITY_LOW => 72,      // 72 hours
        ];
        
        $hours = $slaHours[$priority] ?? 24;
        $baseTime = $createdAt ?? now();
        
        return $baseTime->addHours($hours);
    }

    /**
     * Check if SLA is breached
     */
    public function checkSlaBreach(): bool
    {
        if (!$this->sla_deadline || $this->resolved) {
            return false;
        }
        
        return now()->greaterThan($this->sla_deadline);
    }

    /**
     * Calculate resolution time in hours
     */
    public function getResolutionTimeHoursAttribute(): ?float
    {
        if (!$this->resolved_at || !$this->created_at) {
            return null;
        }
        
        return $this->created_at->diffInHours($this->resolved_at, true);
    }

    /**
     * Get formatted resolution time
     */
    public function getFormattedResolutionTimeAttribute(): ?string
    {
        $hours = $this->resolution_time_hours;
        
        if (!$hours) {
            return null;
        }
        
        if ($hours < 1) {
            $minutes = $hours * 60;
            return round($minutes) . ' minutes';
        } elseif ($hours < 24) {
            return round($hours, 1) . ' hours';
        } else {
            $days = $hours / 24;
            return round($days, 1) . ' days';
        }
    }

    /**
     * Get time since creation
     */
    public function getTimeSinceCreationAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get time since last occurrence for recurring logs
     */
    public function getTimeSinceLastOccurrenceAttribute(): ?string
    {
        if (!$this->last_occurrence_at) {
            return null;
        }
        
        return $this->last_occurrence_at->diffForHumans();
    }

    /**
     * Check if log is stale (no activity for 7 days)
     */
    public function getIsStaleAttribute(): bool
    {
        $lastActivity = $this->last_occurrence_at ?? $this->created_at;
        return $lastActivity->diffInDays(now()) > 7;
    }

    /**
     * Get log color based on level
     */
    public function getLevelColorAttribute(): string
    {
        return match($this->level) {
            self::LEVEL_EMERGENCY, self::LEVEL_ALERT => 'danger',
            self::LEVEL_CRITICAL => 'danger',
            self::LEVEL_ERROR => 'danger',
            self::LEVEL_WARNING => 'warning',
            self::LEVEL_NOTICE => 'info',
            self::LEVEL_INFO => 'info',
            self::LEVEL_DEBUG, self::LEVEL_TRACE => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get severity color
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            self::SEVERITY_CRITICAL => 'danger',
            self::SEVERITY_HIGH => 'danger',
            self::SEVERITY_MEDIUM => 'warning',
            self::SEVERITY_LOW => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_CRITICAL => 'danger',
            self::PRIORITY_HIGH => 'danger',
            self::PRIORITY_MEDIUM => 'warning',
            self::PRIORITY_LOW => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get intervention status color
     */
    public function getInterventionStatusColorAttribute(): ?string
    {
        return match($this->intervention_status) {
            self::INTERVENTION_PENDING => 'warning',
            self::INTERVENTION_IN_PROGRESS => 'info',
            self::INTERVENTION_COMPLETED => 'success',
            self::INTERVENTION_ESCALATED => 'danger',
            default => null,
        };
    }

    /**
     * Get readable level name
     */
    public function getLevelNameAttribute(): string
    {
        return match($this->level) {
            self::LEVEL_EMERGENCY => 'Emergency',
            self::LEVEL_ALERT => 'Alert',
            self::LEVEL_CRITICAL => 'Critical',
            self::LEVEL_ERROR => 'Error',
            self::LEVEL_WARNING => 'Warning',
            self::LEVEL_NOTICE => 'Notice',
            self::LEVEL_INFO => 'Info',
            self::LEVEL_DEBUG => 'Debug',
            self::LEVEL_TRACE => 'Trace',
            default => ucfirst($this->level),
        };
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
            'maintenance' => 'Maintenance System',
            'emergency' => 'Emergency System',
            'security' => 'Security System',
        ];

        $result = [];
        foreach ($modules as $module) {
            $result[$module] = $moduleNames[$module] ?? ucfirst(str_replace('_', ' ', $module));
        }

        return $result;
    }

    /**
     * Get log statistics
     */
    public static function getStatistics($timeRange = '24h'): array
    {
        $startDate = match($timeRange) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDay(),
        };

        $total = self::where('created_at', '>=', $startDate)->count();
        $errors = self::where('created_at', '>=', $startDate)
            ->whereIn('level', [self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_EMERGENCY, self::LEVEL_ALERT])
            ->count();
        $warnings = self::where('created_at', '>=', $startDate)
            ->where('level', self::LEVEL_WARNING)
            ->count();
        $unresolved = self::where('created_at', '>=', $startDate)
            ->where('resolved', false)
            ->count();
        $resolved = self::where('created_at', '>=', $startDate)
            ->where('resolved', true)
            ->count();
        $recurring = self::where('created_at', '>=', $startDate)
            ->where('is_recurring', true)
            ->count();
        $slaBreached = self::where('created_at', '>=', $startDate)
            ->where('sla_breached', true)
            ->count();
        $requiresIntervention = self::where('created_at', '>=', $startDate)
            ->where('requires_human_intervention', true)
            ->count();

        // Average resolution time for resolved logs
        $avgResolutionHours = self::where('created_at', '>=', $startDate)
            ->where('resolved', true)
            ->whereNotNull('resolved_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, resolved_at)'));

        // Most common source
        $commonSource = self::select('source')
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('count')
            ->first();

        // Most common error message pattern
        $commonError = self::select('message')
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->whereIn('level', [self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_EMERGENCY])
            ->groupBy('message')
            ->orderByDesc('count')
            ->first();

        return [
            'time_range' => $timeRange,
            'start_date' => $startDate->toISOString(),
            'total_logs' => $total,
            'error_logs' => $errors,
            'warning_logs' => $warnings,
            'unresolved_logs' => $unresolved,
            'resolved_logs' => $resolved,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            'recurring_logs' => $recurring,
            'sla_breached_logs' => $slaBreached,
            'requires_intervention_logs' => $requiresIntervention,
            'avg_resolution_hours' => round($avgResolutionHours ?? 0, 1),
            'most_common_source' => $commonSource->source ?? 'Unknown',
            'most_common_source_count' => $commonSource->count ?? 0,
            'most_common_error' => $commonError->message ?? 'No errors',
            'most_common_error_count' => $commonError->count ?? 0,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get log trends
     */
    public static function getTrends($days = 30): array
    {
        $trends = [];
        $now = now();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
            
            $total = self::whereBetween('created_at', [$start, $end])->count();
            $errors = self::whereBetween('created_at', [$start, $end])
                ->whereIn('level', [self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_EMERGENCY])
                ->count();
            $resolved = self::whereBetween('created_at', [$start, $end])
                ->where('resolved', true)
                ->count();
            
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'total' => $total,
                'errors' => $errors,
                'resolved' => $resolved,
                'error_rate' => $total > 0 ? round(($errors / $total) * 100, 1) : 0,
                'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            ];
        }
        
        return $trends;
    }

    /**
     * Get top error sources
     */
    public static function getTopSources($limit = 10, $timeRange = '7d'): array
    {
        $startDate = match($timeRange) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        return self::select('source')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN level IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as errors', [
                self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_EMERGENCY, self::LEVEL_ALERT
            ])
            ->selectRaw('SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) as resolved')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('errors')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->source,
                    'total' => $item->total,
                    'errors' => $item->errors,
                    'resolved' => $item->resolved,
                    'error_rate' => $item->total > 0 ? round(($item->errors / $item->total) * 100, 1) : 0,
                    'resolution_rate' => $item->errors > 0 ? round(($item->resolved / $item->errors) * 100, 1) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Create a new system log entry
     */
    public static function createLog(array $data): self
    {
        // Generate reference ID if not provided
        if (!isset($data['reference_id'])) {
            $data['reference_id'] = self::generateReferenceId();
        }
        
        // Calculate severity if not provided
        if (!isset($data['severity']) && isset($data['level'])) {
            $data['severity'] = self::calculateSeverity($data['level']);
        }
        
        // Calculate priority if not provided
        if (!isset($data['priority']) && isset($data['level']) && isset($data['severity'])) {
            $data['priority'] = self::calculatePriority($data['level'], $data['severity']);
        }
        
        // Set SLA deadline if not provided but priority is set
        if (!isset($data['sla_deadline']) && isset($data['priority'])) {
            $data['sla_deadline'] = self::calculateSlaDeadline($data['priority']);
        }
        
        // Set first occurrence if this is a new error
        if (!isset($data['first_occurrence_at'])) {
            $data['first_occurrence_at'] = now();
        }
        
        // Set last occurrence
        $data['last_occurrence_at'] = now();
        
        // Check for similar recent errors to mark as recurring
        if (isset($data['message']) && isset($data['source']) && isset($data['component'])) {
            $similarLog = self::where('message', $data['message'])
                ->where('source', $data['source'])
                ->where('component', $data['component'])
                ->where('created_at', '>=', now()->subHours(24))
                ->first();
            
            if ($similarLog) {
                $data['is_recurring'] = true;
                $data['occurrence_count'] = $similarLog->occurrence_count + 1;
                
                // Update the previous log's recurrence pattern
                $similarLog->update([
                    'is_recurring' => true,
                    'recurrence_pattern' => array_merge(
                        $similarLog->recurrence_pattern ?? [],
                        ['last_updated' => now()->toISOString()]
                    ),
                ]);
            }
        }
        
        return self::create($data);
    }

    /**
     * Mark log as resolved
     */
    public function markAsResolved($resolvedBy = null, $notes = null, $metadata = null): bool
    {
        $this->resolved = true;
        $this->resolved_at = now();
        $this->resolved_by = $resolvedBy;
        $this->resolution_notes = $notes;
        $this->resolution_metadata = $metadata;
        
        // Check if SLA was breached
        $this->sla_breached = $this->checkSlaBreach();
        
        return $this->save();
    }

    /**
     * Assign log to user
     */
    public function assignTo($userId): bool
    {
        $this->assigned_to = $userId;
        $this->assigned_at = now();
        $this->intervention_status = self::INTERVENTION_IN_PROGRESS;
        
        return $this->save();
    }

    /**
     * Escalate log
     */
    public function escalate($reason = null): bool
    {
        $this->intervention_status = self::INTERVENTION_ESCALATED;
        
        if ($reason) {
            $currentMetadata = $this->resolution_metadata ?? [];
            $currentMetadata['escalation_reason'] = $reason;
            $currentMetadata['escalated_at'] = now()->toISOString();
            $this->resolution_metadata = $currentMetadata;
        }
        
        return $this->save();
    }

    /**
     * Archive log
     */
    public function archive(): bool
    {
        $this->archived = true;
        $this->archived_at = now();
        
        return $this->save();
    }

    /**
     * Unarchive log
     */
    public function unarchive(): bool
    {
        $this->archived = false;
        $this->archived_at = null;
        
        return $this->save();
    }

    /**
     * Send alert for this log
     */
    public function sendAlert($channels = ['email'], $recipients = null): bool
    {
        // This would integrate with your notification service
        // For now, just mark as alert sent
        
        $this->alert_sent = true;
        $this->alert_sent_at = now();
        $this->notification_channels = $channels;
        $this->alert_recipients = $recipients;
        
        return $this->save();
    }

    /**
     * Update occurrence count for recurring logs
     */
    public function incrementOccurrence(): bool
    {
        $this->occurrence_count += 1;
        $this->last_occurrence_at = now();
        
        // Update frequency per hour
        if ($this->first_occurrence_at) {
            $hoursSinceFirst = $this->first_occurrence_at->diffInHours(now());
            if ($hoursSinceFirst > 0) {
                $this->frequency_per_hour = $this->occurrence_count / $hoursSinceFirst;
            }
        }
        
        return $this->save();
    }
}