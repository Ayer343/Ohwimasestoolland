<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class DeveloperEmailAudit extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'developer_email_audits';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'job_id',
        'action',
        'status',
        'message',
        'old_configuration',
        'new_configuration',
        'metadata',
        'ip_address',
        'user_agent',
        'error_details',
        'error_type',
        'execution_time_ms',
        'attempts',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_configuration' => 'array',
        'new_configuration' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
        'execution_time_ms' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'error_details',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'execution_time_formatted',
        'is_successful',
        'is_failed',
        'duration_seconds',
        'configuration_changes',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
            
            // Generate job ID if not provided
            if (empty($model->job_id)) {
                $model->job_id = 'audit_' . uniqid();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('status') && $model->status === 'completed' && empty($model->completed_at)) {
                $model->completed_at = now();
                
                // Calculate execution time if not set
                if (empty($model->execution_time_ms) && $model->started_at) {
                    $model->execution_time_ms = $model->started_at->diffInMilliseconds($model->completed_at);
                }
            }
        });
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include successful audits.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed audits.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include pending audits.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include audits for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include audits for a specific job.
     */
    public function scopeForJob($query, $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    /**
     * Scope a query to only include audits within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?? now();
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include audits for a specific action.
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include recent audits.
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get configuration changes in a readable format.
     */
    public function getConfigurationChangesAttribute(): array
    {
        $old = $this->old_configuration ?? [];
        $new = $this->new_configuration ?? [];
        $changes = [];

        // Compare configuration arrays
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        
        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                // Hide sensitive fields
                if ($this->isSensitiveField($key)) {
                    $oldValue = $oldValue ? '[HIDDEN]' : null;
                    $newValue = $newValue ? '[HIDDEN]' : null;
                }
                
                $changes[] = [
                    'field' => $key,
                    'old' => $oldValue,
                    'new' => $newValue,
                    'changed' => $oldValue !== $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if a field is sensitive.
     */
    protected function isSensitiveField(string $field): bool
    {
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'key',
            'api_key',
            'secret_key',
            'private_key',
            'client_secret',
            'access_token',
            'refresh_token',
            'smtp_password',
            'mail_password',
        ];

        foreach ($sensitiveFields as $sensitive) {
            if (stripos($field, $sensitive) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get execution time in formatted string.
     */
    public function getExecutionTimeFormattedAttribute(): ?string
    {
        if (!$this->execution_time_ms) {
            return null;
        }

        if ($this->execution_time_ms < 1000) {
            return $this->execution_time_ms . 'ms';
        }

        $seconds = floor($this->execution_time_ms / 1000);
        $milliseconds = $this->execution_time_ms % 1000;

        if ($milliseconds === 0) {
            return $seconds . 's';
        }

        return $seconds . 's ' . $milliseconds . 'ms';
    }

    /**
     * Check if the audit was successful.
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the audit failed.
     */
    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get duration in seconds.
     */
    public function getDurationSecondsAttribute(): ?float
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at, true);
    }

    /**
     * Mark the audit as started.
     */
    public function markAsStarted(): self
    {
        if (!$this->started_at) {
            $this->started_at = now();
            $this->save();
        }

        return $this;
    }

    /**
     * Mark the audit as completed.
     */
    public function markAsCompleted(string $status = 'success', ?string $message = null): self
    {
        $this->status = $status;
        $this->completed_at = now();
        
        if ($message) {
            $this->message = $message;
        }
        
        // Calculate execution time
        if ($this->started_at) {
            $this->execution_time_ms = $this->started_at->diffInMilliseconds($this->completed_at);
        }
        
        $this->save();

        return $this;
    }

    /**
     * Mark the audit as failed.
     */
    public function markAsFailed(string $error, ?string $errorType = null): self
    {
        $this->status = 'failed';
        $this->error_details = $error;
        $this->error_type = $errorType;
        $this->completed_at = now();
        
        // Calculate execution time
        if ($this->started_at) {
            $this->execution_time_ms = $this->started_at->diffInMilliseconds($this->completed_at);
        }
        
        $this->save();

        return $this;
    }

    /**
     * Increment attempts counter.
     */
    public function incrementAttempts(): self
    {
        $this->increment('attempts');
        return $this;
    }

    /**
     * Create a new audit log entry.
     */
    public static function createAudit(array $data): self
    {
        $audit = self::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'job_id' => $data['job_id'] ?? null,
            'action' => $data['action'] ?? 'unknown',
            'status' => $data['status'] ?? 'pending',
            'message' => $data['message'] ?? null,
            'old_configuration' => $data['old_configuration'] ?? null,
            'new_configuration' => $data['new_configuration'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'error_details' => $data['error_details'] ?? null,
            'error_type' => $data['error_type'] ?? null,
            'started_at' => $data['started_at'] ?? now(),
        ]);

        // If status is already completed, mark it as such
        if (in_array($audit->status, ['success', 'failed', 'cancelled']) && !$audit->completed_at) {
            $audit->markAsCompleted($audit->status);
        }

        return $audit;
    }

    /**
     * Get statistics for a user.
     */
    public static function getUserStatistics(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $total = self::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->count();

        $successful = self::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->successful()
            ->count();

        $failed = self::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->failed()
            ->count();

        $averageTime = self::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('execution_time_ms')
            ->avg('execution_time_ms');

        $mostCommonAction = self::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->first();

        return [
            'total_audits' => $total,
            'successful_audits' => $successful,
            'failed_audits' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'average_execution_time_ms' => round($averageTime ?? 0, 2),
            'average_execution_time_formatted' => $averageTime ? 
                ($averageTime < 1000 ? 
                    round($averageTime) . 'ms' : 
                    round($averageTime / 1000, 2) . 's') : 
                'N/A',
            'most_common_action' => $mostCommonAction ? [
                'action' => $mostCommonAction->action,
                'count' => $mostCommonAction->count
            ] : null,
            'period_days' => $days,
            'period_start' => $startDate->format('Y-m-d H:i:s'),
            'period_end' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get audit summary.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'job_id' => $this->job_id,
            'action' => $this->action,
            'status' => $this->status,
            'message' => $this->message,
            'is_successful' => $this->is_successful,
            'is_failed' => $this->is_failed,
            'configuration_changes_count' => count($this->configuration_changes),
            'execution_time' => $this->execution_time_formatted,
            'duration_seconds' => $this->duration_seconds,
            'attempts' => $this->attempts,
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email
            ] : null,
        ];
    }

    /**
     * Get detailed information.
     */
    public function getDetails(): array
    {
        $summary = $this->getSummary();
        
        $details = [
            'summary' => $summary,
            'configuration_changes' => $this->configuration_changes,
            'old_configuration' => $this->old_configuration,
            'new_configuration' => $this->new_configuration,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'error_details' => $this->is_failed ? $this->error_details : null,
            'error_type' => $this->error_type,
        ];

        // Remove null values
        return array_filter($details, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Clean up old audit records.
     */
    public static function cleanupOldRecords(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return self::where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Export audit data.
     */
    public static function exportData(array $filters = [], string $format = 'json'): mixed
    {
        $query = self::query();
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }
        
        $audits = $query->orderBy('created_at', 'desc')->get();
        
        if ($format === 'json') {
            return $audits->map(function ($audit) {
                return $audit->getDetails();
            });
        }
        
        // For CSV or other formats, you would add additional logic here
        return $audits;
    }
}