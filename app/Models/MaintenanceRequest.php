<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MaintenanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_id',
        'property_id',
        'unit_id',
        'tenant_id',
        'landlord_id',
        'reported_by_user_id',      // ✅ Fixed: Added this
        'reported_by',              // ✅ For compatibility if your column is 'reported_by'
        'reported_by_type',         // ✅ Added this
        'assigned_to_user_id',
        'category_id',
        'priority',
        'status',
        'title',
        'description',
        'location_description',
        'issue_type',
        'estimated_cost',
        'actual_cost',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'scheduled_date',
        'completed_date',
        'emergency',
        'permission_granted',
        'images',
        'documents',
        'notes',
        'rating',
        'feedback',
        'resolution_details',
        'maintenance_type',
        'access_instructions',
        'tenant_availability',
        'vendor_id',
        'vendor_quote',
        'approved_by_landlord',
        'approved_date',
        'payment_status',
        'inspection_required',
        'inspection_date',
        'inspection_result',
        'recurring',
        'recurring_interval',
        'next_recurring_date',
        'source',
        'external_reference',
        'notify_tenant',
        'notify_landlord',
        'notify_technician',
        'created_by',
        'updated_by',
        'photos',                   // ✅ Added for compatibility with your controller
        'category',                 // ✅ Added for compatibility
        'cancelled_by',            // ✅ Added
        'cancelled_at',            // ✅ Added
        'cancellation_reason',     // ✅ Added
        'resolution_notes',        // ✅ Added
        'assigned_to',              // ✅ Added for compatibility
        'cost_estimate',           // ✅ Added for compatibility
        'estimated_completion_date', // ✅ Added for compatibility
        'updated_by',              // ✅ Added for compatibility
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'scheduled_date' => 'datetime',
        'completed_date' => 'datetime',
        'emergency' => 'boolean',
        'permission_granted' => 'boolean',
        'images' => 'array',
        'photos' => 'array',        // ✅ Added
        'documents' => 'array',
        'rating' => 'integer',
        'approved_by_landlord' => 'boolean',
        'approved_date' => 'datetime',
        'inspection_required' => 'boolean',
        'inspection_date' => 'datetime',
        'recurring' => 'boolean',
        'next_recurring_date' => 'datetime',
        'notify_tenant' => 'boolean',
        'notify_landlord' => 'boolean',
        'notify_technician' => 'boolean',
        'vendor_quote' => 'decimal:2',
        'cost_estimate' => 'decimal:2',      // ✅ Added
        'estimated_completion_date' => 'datetime', // ✅ Added
        'cancelled_at' => 'datetime',        // ✅ Added
    ];

    /**
     * Maintenance request statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_AWAITING_PARTS = 'awaiting_parts';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REOPENED = 'reopened';
    const STATUS_FOLLOW_UP_REQUIRED = 'follow_up_required';

    /**
     * Priority levels
     */
    const PRIORITY_EMERGENCY = 'emergency';
    const PRIORITY_URGENT = 'urgent';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    /**
     * Issue types
     */
    const ISSUE_TYPE_PLUMBING = 'plumbing';
    const ISSUE_TYPE_ELECTRICAL = 'electrical';
    const ISSUE_TYPE_HVAC = 'hvac';
    const ISSUE_TYPE_APPLIANCE = 'appliance';
    const ISSUE_TYPE_STRUCTURAL = 'structural';
    const ISSUE_TYPE_PAINTING = 'painting';
    const ISSUE_TYPE_CLEANING = 'cleaning';
    const ISSUE_TYPE_PEST_CONTROL = 'pest_control';
    const ISSUE_TYPE_LANDSCAPING = 'landscaping';
    const ISSUE_TYPE_SECURITY = 'security';
    const ISSUE_TYPE_GENERAL = 'general';

    /**
     * Maintenance types
     */
    const MAINTENANCE_TYPE_PREVENTIVE = 'preventive';
    const MAINTENANCE_TYPE_CORRECTIVE = 'corrective';
    const MAINTENANCE_TYPE_RENOVATION = 'renovation';
    const MAINTENANCE_TYPE_INSPECTION = 'inspection';
    const MAINTENANCE_TYPE_UPGRADE = 'upgrade';

    /**
     * Payment statuses
     */
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_QUOTED = 'quoted';
    const PAYMENT_STATUS_APPROVED = 'approved';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_PARTIAL = 'partial';
    const PAYMENT_STATUS_DISPUTED = 'disputed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    /**
     * Recurring intervals
     */
    const RECURRING_INTERVAL_WEEKLY = 'weekly';
    const RECURRING_INTERVAL_BIWEEKLY = 'biweekly';
    const RECURRING_INTERVAL_MONTHLY = 'monthly';
    const RECURRING_INTERVAL_QUARTERLY = 'quarterly';
    const RECURRING_INTERVAL_BIANNUALLY = 'biannually';
    const RECURRING_INTERVAL_ANNUALLY = 'annually';

    /**
     * Source types
     */
    const SOURCE_TENANT = 'tenant';
    const SOURCE_LANDLORD = 'landlord';
    const SOURCE_MANAGER = 'manager';
    const SOURCE_INSPECTION = 'inspection';
    const SOURCE_SYSTEM = 'system';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the property associated with the maintenance request.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the property unit associated with the maintenance request.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    /**
     * Get the tenant who reported the issue.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the landlord who owns the property.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * ✅ FIXED: Get the user who reported the issue.
     * This handles both column names for compatibility.
     */
    public function reporter(): BelongsTo
    {
        // Check which column exists in the table
        if ($this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'reported_by_user_id')) {
            return $this->belongsTo(User::class, 'reported_by_user_id');
        }
        
        // Fallback to 'reported_by' if that's the column name
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * ✅ FIXED: Alias for reporter() - maintains backward compatibility
     */
    public function reportedBy(): BelongsTo
    {
        return $this->reporter();
    }

    /**
     * Get the user assigned to handle the maintenance.
     */
    public function assignedTo(): BelongsTo
    {
        // Check which column exists
        if ($this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'assigned_to_user_id')) {
            return $this->belongsTo(User::class, 'assigned_to_user_id');
        }
        
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created the maintenance request.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the maintenance request.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the vendor assigned to the maintenance.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the maintenance category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCategory::class, 'category_id');
    }

    /**
     * Get the work logs for the maintenance request.
     */
    public function workLogs(): HasMany
    {
        return $this->hasMany(MaintenanceWorkLog::class);
    }

    /**
     * Get the quotes for the maintenance request.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(MaintenanceQuote::class);
    }

    /**
     * Get the inspections for the maintenance request.
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(MaintenanceInspection::class);
    }

    /**
     * Get the payments for the maintenance request.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Get the comments for the maintenance request.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the notifications for the maintenance request.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    // ==================== SCOPES ====================

    /**
     * Scope for active maintenance requests
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_AWAITING_PARTS,
        ]);
    }

    /**
     * Scope for completed maintenance requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending maintenance requests
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for emergency maintenance requests
     */
    public function scopeEmergency($query)
    {
        return $query->where('emergency', true)
                     ->orWhere('priority', self::PRIORITY_EMERGENCY)
                     ->orWhere('priority', self::PRIORITY_URGENT);
    }

    /**
     * Scope for overdue maintenance requests
     */
    public function scopeOverdue($query)
    {
        return $query->where('scheduled_date', '<', now())
                     ->whereIn('status', [
                         self::STATUS_SCHEDULED,
                         self::STATUS_IN_PROGRESS,
                         self::STATUS_ON_HOLD,
                     ]);
    }

    /**
     * Scope for maintenance requests by user type
     */
    public function scopeForUserType($query, $userId, $userType)
    {
        switch ($userType) {
            case 3: // Tenant
                return $query->where('tenant_id', $userId)
                             ->orWhere('reported_by_user_id', $userId)
                             ->orWhere('reported_by', $userId);
            case 2: // Landlord
                return $query->where('landlord_id', $userId);
            case 1: // Admin
            case 0: // Super Admin
                return $query;
            case 4: // Field Agent
                return $query->where('assigned_to_user_id', $userId)
                             ->orWhere('assigned_to', $userId);
            default:
                return $query->where('reported_by_user_id', $userId)
                             ->orWhere('reported_by', $userId);
        }
    }

    /**
     * Scope for maintenance requests requiring approval
     */
    public function scopeRequiringApproval($query)
    {
        return $query->where('status', self::STATUS_AWAITING_APPROVAL)
                     ->orWhere(function($q) {
                         $q->where('estimated_cost', '>', 500)
                           ->where('status', self::STATUS_PENDING);
                     });
    }

    // ==================== ACCESSORS ====================

    /**
     * Check if maintenance request is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->scheduled_date) {
            return false;
        }

        return $this->scheduled_date < now() 
            && in_array($this->status, [
                self::STATUS_SCHEDULED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_ON_HOLD,
            ]);
    }

    /**
     * Get the overdue days.
     */
    public function getOverdueDaysAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }

        return now()->diffInDays($this->scheduled_date);
    }

    /**
     * Check if maintenance request is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_AWAITING_PARTS,
        ]);
    }

    /**
     * Check if maintenance request requires approval.
     */
    public function getRequiresApprovalAttribute(): bool
    {
        return $this->status === self::STATUS_AWAITING_APPROVAL 
            || ($this->estimated_cost > 500 && $this->status === self::STATUS_PENDING);
    }

    /**
     * Get the current duration in progress.
     */
    public function getCurrentDurationAttribute(): ?int
    {
        if (!$this->scheduled_date) {
            return null;
        }

        return now()->diffInMinutes($this->scheduled_date);
    }

    /**
     * Get the total cost including actual and estimated.
     */
    public function getTotalCostAttribute(): float
    {
        return $this->actual_cost ?? $this->estimated_cost ?? 0;
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING, self::STATUS_AWAITING_APPROVAL => 'warning',
            self::STATUS_IN_PROGRESS, self::STATUS_SCHEDULED => 'info',
            self::STATUS_ON_HOLD, self::STATUS_AWAITING_PARTS => 'secondary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED, self::STATUS_REJECTED => 'dark',
            default => 'primary',
        };
    }

    /**
     * Get the priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_EMERGENCY, self::PRIORITY_URGENT => 'danger',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_LOW => 'success',
            default => 'secondary',
        };
    }

    /**
     * Get the display status.
     */
    public function getDisplayStatusAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get the display priority.
     */
    public function getDisplayPriorityAttribute(): string
    {
        return ucfirst($this->priority);
    }

    /**
     * Get the maintenance timeline.
     */
    public function getTimelineAttribute(): array
    {
        $timeline = [];

        if ($this->created_at) {
            $reporter = $this->reporter;
            $timeline[] = [
                'event' => 'Created',
                'date' => $this->created_at,
                'user' => $reporter?->name ?? 'System',
                'description' => 'Maintenance request created',
            ];
        }

        if ($this->scheduled_date) {
            $timeline[] = [
                'event' => 'Scheduled',
                'date' => $this->scheduled_date,
                'description' => 'Maintenance scheduled',
            ];
        }

        if ($this->approved_date) {
            $timeline[] = [
                'event' => 'Approved',
                'date' => $this->approved_date,
                'description' => 'Maintenance approved',
            ];
        }

        if ($this->completed_date) {
            $timeline[] = [
                'event' => 'Completed',
                'date' => $this->completed_date,
                'description' => 'Maintenance completed',
            ];
        }

        // Add work logs to timeline
        foreach ($this->workLogs as $log) {
            $timeline[] = [
                'event' => 'Work Log',
                'date' => $log->created_at,
                'user' => $log->user?->name,
                'description' => $log->description,
            ];
        }

        // Sort by date
        usort($timeline, function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $timeline;
    }

    /**
     * Get statistics for the maintenance request.
     */
    public function getStatisticsAttribute(): array
    {
        return [
            'total_work_hours' => $this->workLogs->sum('hours_spent'),
            'total_work_logs' => $this->workLogs->count(),
            'total_quotes' => $this->quotes->count(),
            'total_payments' => $this->payments->count(),
            'total_payments_amount' => $this->payments->sum('amount'),
            'average_rating' => $this->workLogs->avg('rating'),
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if maintenance request can be approved.
     */
    public function canBeApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_AWAITING_APPROVAL,
            self::STATUS_PENDING,
        ]);
    }

    /**
     * Check if maintenance request can be started.
     */
    public function canBeStarted(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Check if maintenance request can be completed.
     */
    public function canBeCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_PROGRESS,
            self::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Approve the maintenance request.
     */
    public function approve(array $data = []): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by_landlord = true;
        $this->approved_date = now();

        if (isset($data['approved_by'])) {
            $this->approved_by = $data['approved_by'];
        }

        if (isset($data['notes'])) {
            $this->notes = $this->notes . "\n" . $data['notes'];
        }

        return $this->save();
    }

    /**
     * Start the maintenance request.
     */
    public function start(array $data = []): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->scheduled_date = $this->scheduled_date ?? now();

        if (isset($data['assigned_to'])) {
            $this->assigned_to_user_id = $data['assigned_to'];
        }

        return $this->save();
    }

    /**
     * Complete the maintenance request.
     */
    public function complete(array $data = []): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_date = now();

        if (isset($data['actual_cost'])) {
            $this->actual_cost = $data['actual_cost'];
        }

        if (isset($data['actual_duration_minutes'])) {
            $this->actual_duration_minutes = $data['actual_duration_minutes'];
        }

        if (isset($data['resolution_details'])) {
            $this->resolution_details = $data['resolution_details'];
        }

        return $this->save();
    }

    /**
     * Calculate SLA compliance.
     */
    public function calculateSlaCompliance(): ?array
    {
        if (!$this->created_at || !$this->completed_date) {
            return null;
        }

        $totalDuration = $this->created_at->diffInMinutes($this->completed_date);
        $slaTarget = $this->getSlaTarget();

        return [
            'actual_duration' => $totalDuration,
            'sla_target' => $slaTarget,
            'compliant' => $totalDuration <= $slaTarget,
            'overage_minutes' => max(0, $totalDuration - $slaTarget),
            'compliance_percentage' => $slaTarget > 0 ? min(100, ($slaTarget / $totalDuration) * 100) : 100,
        ];
    }

    /**
     * Get SLA target based on priority.
     */
    private function getSlaTarget(): int
    {
        return match($this->priority) {
            self::PRIORITY_EMERGENCY => 120, // 2 hours
            self::PRIORITY_URGENT => 240, // 4 hours
            self::PRIORITY_HIGH => 1440, // 24 hours
            self::PRIORITY_MEDIUM => 4320, // 3 days
            self::PRIORITY_LOW => 10080, // 7 days
            default => 4320, // 3 days default
        };
    }

    /**
     * Generate reference ID.
     */
    public static function generateReferenceId(): string
    {
        $prefix = 'MR';
        $date = now()->format('ymd');
        $lastId = self::withTrashed()->where('reference_id', 'like', "{$prefix}{$date}%")->count();
        
        return $prefix . $date . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($maintenanceRequest) {
            if (empty($maintenanceRequest->reference_id)) {
                $maintenanceRequest->reference_id = self::generateReferenceId();
            }

            if (auth()->check() && empty($maintenanceRequest->created_by)) {
                $maintenanceRequest->created_by = auth()->id();
            }

            // ✅ Set reported_by_user_id if not set but reported_by is set
            if (empty($maintenanceRequest->reported_by_user_id) && !empty($maintenanceRequest->reported_by)) {
                $maintenanceRequest->reported_by_user_id = $maintenanceRequest->reported_by;
            }

            // Set priority based on emergency flag
            if ($maintenanceRequest->emergency && !$maintenanceRequest->priority) {
                $maintenanceRequest->priority = self::PRIORITY_EMERGENCY;
            }
        });

        static::updating(function ($maintenanceRequest) {
            if (auth()->check() && $maintenanceRequest->isDirty()) {
                $maintenanceRequest->updated_by = auth()->id();
            }

            // ✅ Sync reported_by_user_id if reported_by is being set
            if ($maintenanceRequest->isDirty('reported_by') && empty($maintenanceRequest->reported_by_user_id)) {
                $maintenanceRequest->reported_by_user_id = $maintenanceRequest->reported_by;
            }

            // Handle status transitions
            if ($maintenanceRequest->isDirty('status')) {
                $maintenanceRequest->handleStatusTransition(
                    $maintenanceRequest->getOriginal('status'),
                    $maintenanceRequest->status
                );
            }
        });
    }

    /**
     * Handle status transition.
     */
    private function handleStatusTransition(string $oldStatus, string $newStatus): void
    {
        // Set scheduled date when moving to scheduled
        if ($newStatus === self::STATUS_SCHEDULED && !$this->scheduled_date) {
            $this->scheduled_date = now()->addDays(1);
        }

        // Set completed date when moving to completed
        if ($newStatus === self::STATUS_COMPLETED && !$this->completed_date) {
            $this->completed_date = now();
        }

        // Log the status change
        try {
            SystemLog::create([
                'level' => 'info',
                'message' => "Maintenance request {$this->reference_id} status changed from {$oldStatus} to {$newStatus}",
                'source' => 'MaintenanceRequest',
                'context' => [
                    'maintenance_request_id' => $this->id,
                    'reference_id' => $this->reference_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'user_id' => auth()->id(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log status change: ' . $e->getMessage());
        }
    }

    // ==================== STATIC HELPER METHODS ====================

    /**
     * Get all status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_AWAITING_APPROVAL => 'Awaiting Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_AWAITING_PARTS => 'Awaiting Parts',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REOPENED => 'Reopened',
            self::STATUS_FOLLOW_UP_REQUIRED => 'Follow Up Required',
        ];
    }

    /**
     * Get all priority options.
     */
    public static function getPriorityOptions(): array
    {
        return [
            self::PRIORITY_EMERGENCY => 'Emergency (2 hours)',
            self::PRIORITY_URGENT => 'Urgent (4 hours)',
            self::PRIORITY_HIGH => 'High (24 hours)',
            self::PRIORITY_MEDIUM => 'Medium (3 days)',
            self::PRIORITY_LOW => 'Low (7 days)',
        ];
    }

    /**
     * Get all issue type options.
     */
    public static function getIssueTypeOptions(): array
    {
        return [
            self::ISSUE_TYPE_PLUMBING => 'Plumbing',
            self::ISSUE_TYPE_ELECTRICAL => 'Electrical',
            self::ISSUE_TYPE_HVAC => 'HVAC',
            self::ISSUE_TYPE_APPLIANCE => 'Appliance',
            self::ISSUE_TYPE_STRUCTURAL => 'Structural',
            self::ISSUE_TYPE_PAINTING => 'Painting',
            self::ISSUE_TYPE_CLEANING => 'Cleaning',
            self::ISSUE_TYPE_PEST_CONTROL => 'Pest Control',
            self::ISSUE_TYPE_LANDSCAPING => 'Landscaping',
            self::ISSUE_TYPE_SECURITY => 'Security',
            self::ISSUE_TYPE_GENERAL => 'General',
        ];
    }

    /**
     * Get all maintenance type options.
     */
    public static function getMaintenanceTypeOptions(): array
    {
        return [
            self::MAINTENANCE_TYPE_PREVENTIVE => 'Preventive',
            self::MAINTENANCE_TYPE_CORRECTIVE => 'Corrective',
            self::MAINTENANCE_TYPE_RENOVATION => 'Renovation',
            self::MAINTENANCE_TYPE_INSPECTION => 'Inspection',
            self::MAINTENANCE_TYPE_UPGRADE => 'Upgrade',
        ];
    }
}