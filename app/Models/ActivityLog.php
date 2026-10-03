<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'unit_id',
        'property_id',
        'type',
        'action',
        'description',
        'model_type',
        'model_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'model_id' => 'integer',
        'user_id' => 'integer',
        'unit_id' => 'integer',
        'property_id' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Default attribute values.
     *
     * @var array
     */
    protected $attributes = [
        'metadata' => '{}',
        'description' => '', // FIX: Set default empty string for description
    ];

    // =============================================
    // CONSTANTS
    // =============================================
    
    const TYPE_PAYMENT_MADE = 'payment_made';
    const TYPE_MAINTENANCE_REQUEST = 'maintenance_request';
    const TYPE_LEASE_SIGNED = 'lease_signed';
    const TYPE_TENANT_ASSIGNED = 'tenant_assigned';
    const TYPE_TENANT_APPROVED = 'tenant_approved';
    const TYPE_TENANT_VACATED = 'tenant_vacated';
    const TYPE_INVOICE_GENERATED = 'invoice_generated';
    const TYPE_UNIT_CREATED = 'unit_created';
    const TYPE_UNIT_UPDATED = 'unit_updated';
    
    // Security Schedule Types
    const TYPE_SCHEDULE = 'schedule';
    const TYPE_SCHEDULE_CREATED = 'schedule_created';
    const TYPE_SCHEDULE_UPDATED = 'schedule_updated';
    const TYPE_SCHEDULE_DELETED = 'schedule_deleted';
    const TYPE_SCHEDULE_RESTORED = 'schedule_restored';
    const TYPE_SCHEDULE_FORCE_DELETED = 'schedule_force_deleted';
    const TYPE_SCHEDULE_CHECKIN = 'schedule_checkin';
    const TYPE_SCHEDULE_CHECKOUT = 'schedule_checkout';
    const TYPE_SCHEDULE_ABSENT = 'schedule_absent';
    const TYPE_SCHEDULE_CANCELLED = 'schedule_cancelled';
    const TYPE_SCHEDULE_APPROVED = 'schedule_approved';
    const TYPE_SCHEDULE_UNAPPROVED = 'schedule_unapproved';
    const TYPE_SCHEDULE_BREAK_START = 'schedule_break_start';
    const TYPE_SCHEDULE_BREAK_END = 'schedule_break_end';
    const TYPE_SCHEDULE_HANDOVER = 'schedule_handover';
    const TYPE_SCHEDULE_ROTATION = 'schedule_rotation';
    const TYPE_SCHEDULE_ROTATION_REVERT = 'schedule_rotation_revert';

    // General Types
    const TYPE_GENERAL = 'general';
    const TYPE_ERROR = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_INFO = 'info';
    const TYPE_DEBUG = 'debug';

    // =============================================
    // RELATIONSHIPS
    // =============================================
    
    /**
     * Get the user that performed the activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the property unit related to this activity
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    /**
     * Get the property related to this activity
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the model that the activity belongs to (polymorphic)
     */
    public function model()
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }
        
        try {
            return $this->belongsTo($this->model_type, 'model_id');
        } catch (\Exception $e) {
            return null;
        }
    }

    // =============================================
    // SCOPES
    // =============================================
    
    /**
     * Scope to filter by activity type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by action
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by unit
     */
    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * Scope to filter by property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope to filter by model type
     */
    public function scopeForModel($query, $modelType, $modelId = null)
    {
        $query->where('model_type', $modelType);
        
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Scope to get recent activities
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get activities between dates
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's activities
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get activities from a specific IP
     */
    public function scopeFromIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope to exclude certain types
     */
    public function scopeExcludeTypes($query, array $types)
    {
        return $query->whereNotIn('type', $types);
    }

    // =============================================
    // ACCESSORS
    // =============================================
    
    /**
     * Get readable activity type
     */
    public function getReadableTypeAttribute(): string
    {
        $typeMap = [
            // Payment types
            self::TYPE_PAYMENT_MADE => 'Payment Made',
            self::TYPE_MAINTENANCE_REQUEST => 'Maintenance Request',
            self::TYPE_LEASE_SIGNED => 'Lease Signed',
            self::TYPE_TENANT_ASSIGNED => 'Tenant Assigned',
            self::TYPE_TENANT_APPROVED => 'Tenant Approved',
            self::TYPE_TENANT_VACATED => 'Tenant Vacated',
            self::TYPE_INVOICE_GENERATED => 'Invoice Generated',
            self::TYPE_UNIT_CREATED => 'Unit Created',
            self::TYPE_UNIT_UPDATED => 'Unit Updated',
            
            // Schedule types
            self::TYPE_SCHEDULE => 'Security Schedule',
            self::TYPE_SCHEDULE_CREATED => 'Schedule Created',
            self::TYPE_SCHEDULE_UPDATED => 'Schedule Updated',
            self::TYPE_SCHEDULE_DELETED => 'Schedule Deleted',
            self::TYPE_SCHEDULE_RESTORED => 'Schedule Restored',
            self::TYPE_SCHEDULE_FORCE_DELETED => 'Schedule Permanently Deleted',
            self::TYPE_SCHEDULE_CHECKIN => 'Check-in',
            self::TYPE_SCHEDULE_CHECKOUT => 'Check-out',
            self::TYPE_SCHEDULE_ABSENT => 'Marked Absent',
            self::TYPE_SCHEDULE_CANCELLED => 'Schedule Cancelled',
            self::TYPE_SCHEDULE_APPROVED => 'Schedule Approved',
            self::TYPE_SCHEDULE_UNAPPROVED => 'Schedule Unapproved',
            self::TYPE_SCHEDULE_BREAK_START => 'Break Started',
            self::TYPE_SCHEDULE_BREAK_END => 'Break Ended',
            self::TYPE_SCHEDULE_HANDOVER => 'Handover Completed',
            self::TYPE_SCHEDULE_ROTATION => 'Rotation Applied',
            self::TYPE_SCHEDULE_ROTATION_REVERT => 'Rotation Reverted',
            
            // General types
            self::TYPE_GENERAL => 'General',
            self::TYPE_ERROR => 'Error',
            self::TYPE_WARNING => 'Warning',
            self::TYPE_INFO => 'Information',
            self::TYPE_DEBUG => 'Debug',
        ];
        
        return $typeMap[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get formatted metadata
     */
    public function getFormattedMetadataAttribute(): ?string
    {
        if (empty($this->metadata) || !is_array($this->metadata)) {
            return null;
        }

        $formatted = [];
        foreach ($this->metadata as $key => $value) {
            // Skip internal fields
            if (in_array($key, ['schedule_id', 'post_id', 'user_id', 'shift_id'])) {
                continue;
            }
            
            // Format the value based on type
            if (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif ($value === null) {
                $value = 'N/A';
            }
            
            $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return !empty($formatted) ? implode(', ', $formatted) : null;
    }

    /**
     * Get metadata summary (first few items)
     */
    public function getMetadataSummaryAttribute(): ?string
    {
        $formatted = $this->formatted_metadata;
        
        if (!$formatted) {
            return null;
        }
        
        // Truncate to first 3 items
        $items = explode(', ', $formatted);
        $summary = array_slice($items, 0, 3);
        
        if (count($items) > 3) {
            $summary[] = '...';
        }
        
        return implode(', ', $summary);
    }

    /**
     * Get the time ago in human readable format
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get formatted created at date
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Get the icon for the activity type
     */
    public function getIconAttribute(): string
    {
        $iconMap = [
            self::TYPE_PAYMENT_MADE => '💰',
            self::TYPE_MAINTENANCE_REQUEST => '🔧',
            self::TYPE_LEASE_SIGNED => '📝',
            self::TYPE_TENANT_ASSIGNED => '👤',
            self::TYPE_TENANT_APPROVED => '✅',
            self::TYPE_TENANT_VACATED => '🚪',
            self::TYPE_INVOICE_GENERATED => '📄',
            self::TYPE_UNIT_CREATED => '🏢',
            self::TYPE_UNIT_UPDATED => '📋',
            self::TYPE_SCHEDULE_CREATED => '📅',
            self::TYPE_SCHEDULE_UPDATED => '✏️',
            self::TYPE_SCHEDULE_DELETED => '🗑️',
            self::TYPE_SCHEDULE_RESTORED => '🔄',
            self::TYPE_SCHEDULE_FORCE_DELETED => '💥',
            self::TYPE_SCHEDULE_CHECKIN => '📥',
            self::TYPE_SCHEDULE_CHECKOUT => '📤',
            self::TYPE_SCHEDULE_ABSENT => '❌',
            self::TYPE_SCHEDULE_CANCELLED => '🚫',
            self::TYPE_SCHEDULE_APPROVED => '👍',
            self::TYPE_SCHEDULE_UNAPPROVED => '👎',
            self::TYPE_SCHEDULE_BREAK_START => '☕',
            self::TYPE_SCHEDULE_BREAK_END => '✅',
            self::TYPE_SCHEDULE_HANDOVER => '🤝',
            self::TYPE_SCHEDULE_ROTATION => '🔄',
            self::TYPE_SCHEDULE_ROTATION_REVERT => '↩️',
            self::TYPE_ERROR => '⚠️',
            self::TYPE_WARNING => '⚠️',
            self::TYPE_INFO => 'ℹ️',
        ];
        
        return $iconMap[$this->type] ?? '📌';
    }

    /**
     * Get the color class for the activity type
     */
    public function getColorClassAttribute(): string
    {
        $colorMap = [
            self::TYPE_PAYMENT_MADE => 'success',
            self::TYPE_MAINTENANCE_REQUEST => 'warning',
            self::TYPE_LEASE_SIGNED => 'info',
            self::TYPE_TENANT_ASSIGNED => 'primary',
            self::TYPE_TENANT_APPROVED => 'success',
            self::TYPE_TENANT_VACATED => 'danger',
            self::TYPE_INVOICE_GENERATED => 'secondary',
            self::TYPE_UNIT_CREATED => 'info',
            self::TYPE_UNIT_UPDATED => 'primary',
            self::TYPE_SCHEDULE_CREATED => 'success',
            self::TYPE_SCHEDULE_UPDATED => 'info',
            self::TYPE_SCHEDULE_DELETED => 'danger',
            self::TYPE_SCHEDULE_RESTORED => 'success',
            self::TYPE_SCHEDULE_FORCE_DELETED => 'danger',
            self::TYPE_SCHEDULE_CHECKIN => 'success',
            self::TYPE_SCHEDULE_CHECKOUT => 'info',
            self::TYPE_SCHEDULE_ABSENT => 'danger',
            self::TYPE_SCHEDULE_CANCELLED => 'warning',
            self::TYPE_SCHEDULE_APPROVED => 'success',
            self::TYPE_SCHEDULE_UNAPPROVED => 'warning',
            self::TYPE_SCHEDULE_BREAK_START => 'primary',
            self::TYPE_SCHEDULE_BREAK_END => 'success',
            self::TYPE_SCHEDULE_HANDOVER => 'info',
            self::TYPE_SCHEDULE_ROTATION => 'warning',
            self::TYPE_SCHEDULE_ROTATION_REVERT => 'secondary',
            self::TYPE_ERROR => 'danger',
            self::TYPE_WARNING => 'warning',
            self::TYPE_INFO => 'info',
        ];
        
        return $colorMap[$this->type] ?? 'secondary';
    }

    /**
     * Get badge HTML for the activity type
     */
    public function getBadgeAttribute(): string
    {
        $colors = [
            'success' => 'bg-success',
            'info' => 'bg-info',
            'warning' => 'bg-warning',
            'danger' => 'bg-danger',
            'primary' => 'bg-primary',
            'secondary' => 'bg-secondary',
        ];
        
        $color = $this->color_class;
        $bgClass = $colors[$color] ?? 'bg-secondary';
        
        return "<span class='badge {$bgClass}'>{$this->readable_type}</span>";
    }

    // =============================================
    // HELPER METHODS
    // =============================================
    
    /**
     * Check if this activity is of a specific type
     */
    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Check if this activity is for a specific action
     */
    public function hasAction(string $action): bool
    {
        return $this->action === $action;
    }

    /**
     * Get a specific metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata has a specific key
     */
    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    /**
     * Get the user name (with fallback)
     */
    public function getUserNameAttribute(): string
    {
        return $this->user ? $this->user->name : 'System';
    }

    /**
     * Get the user email (with fallback)
     */
    public function getUserEmailAttribute(): ?string
    {
        return $this->user ? $this->user->email : null;
    }

    /**
     * Get the model name if exists
     */
    public function getModelNameAttribute(): ?string
    {
        $model = $this->model;
        
        if (!$model) {
            return null;
        }
        
        // Try common name attributes
        if (method_exists($model, 'getNameAttribute')) {
            return $model->name;
        }
        
        if (isset($model->name)) {
            return $model->name;
        }
        
        if (isset($model->title)) {
            return $model->title;
        }
        
        return get_class($model) . ' #' . $model->id;
    }

    // =============================================
    // STATIC HELPERS
    // =============================================
    
    /**
     * Log a custom activity
     */
    public static function log(string $type, string $description, array $metadata = [], ?int $userId = null): self
    {
        try {
            return self::create([
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Log an error
     */
    public static function logError(string $message, array $context = []): self
    {
        return self::log(self::TYPE_ERROR, $message, $context);
    }

    /**
     * Log a warning
     */
    public static function logWarning(string $message, array $context = []): self
    {
        return self::log(self::TYPE_WARNING, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function logInfo(string $message, array $context = []): self
    {
        return self::log(self::TYPE_INFO, $message, $context);
    }

    /**
     * Get statistics for a date range
     */
    public static function getStatistics($startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();
        
        $query = self::whereBetween('created_at', [$startDate, $endDate]);
        
        return [
            'total' => $query->count(),
            'by_type' => $query->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'by_user' => $query->selectRaw('user_id, count(*) as count')
                ->groupBy('user_id')
                ->with('user:id,name')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->user?->name ?? 'System' => $item->count];
                })
                ->toArray(),
            'daily_avg' => round($query->count() / max(1, $startDate->diffInDays($endDate)), 1),
        ];
    }

    /**
     * Clean old logs
     */
    public static function cleanOld(int $days = 90): int
    {
        $cutoff = now()->subDays($days);
        return self::where('created_at', '<', $cutoff)->delete();
    }

    // =============================================
    // BOOT METHOD
    // =============================================
    
    /**
     * The "booted" method of the model
     */
    protected static function booted()
    {
        static::creating(function ($log) {
            // Ensure description is never null
            if (empty($log->description)) {
                $log->description = 'No description provided';
            }
            
            // Ensure metadata is always an array
            if (empty($log->metadata)) {
                $log->metadata = [];
            }
            
            // Set user_id if not provided and user is logged in
            if (!$log->user_id && auth()->check()) {
                $log->user_id = auth()->id();
            }
            
            // Set IP and user agent if not provided
            if (!$log->ip_address && request()->hasHeader('X-Forwarded-For')) {
                $log->ip_address = request()->header('X-Forwarded-For');
            } elseif (!$log->ip_address) {
                $log->ip_address = request()->ip();
            }
            
            if (!$log->user_agent && request()->hasHeader('User-Agent')) {
                $log->user_agent = request()->header('User-Agent');
            }
        });
        
        static::created(function ($log) {
            // Optional: Trigger any post-creation events
            // event(new ActivityLogged($log));
        });
    }
}