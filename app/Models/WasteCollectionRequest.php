<?php
// app/Models/WasteCollectionRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class WasteCollectionRequest extends Model
{
    use HasFactory, SoftDeletes;

    // ==================== TABLE FIELDS ====================

    protected $fillable = [
        'property_id',
        'unit_id',
        'requested_by',
        'assigned_to',
        'worker_id',
        'waste_type',
        'priority',
        'description',
        'special_instructions',
        'digital_address',
        'latitude',
        'longitude',
        'status',
        'approval_status',
        'assigned_at',
        'en_route_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'route_coordinates',
        'checkpoints',
        'completion_notes',
        'collection_notes',
        'before_photo',
        'after_photo',
        'waste_weight_kg',
        'completion_time',
        'metadata',
        // Approval fields
        'approval_requested_at',
        'approval_responded_at',
        'approved_by',
        'approval_notes',
        'rejection_reason',
        'approval_expires_at',
        'approval_notification_sent',
        'approval_reminder_sent_at',
        'approval_reminder_count',
        'source',
        'bin_full_reported_at',
    ];

    protected $casts = [
        'route_coordinates' => 'array',
        'checkpoints' => 'array',
        'metadata' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'assigned_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'approval_responded_at' => 'datetime',
        'approval_expires_at' => 'datetime',
        'approval_reminder_sent_at' => 'datetime',
        'approval_notification_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completion_time' => 'float',
        'waste_weight_kg' => 'float',
        'approval_reminder_count' => 'integer',
        'bin_full_reported_at' => 'datetime',
    ];

    // ==================== STATUS CONSTANTS ====================

    // Collection statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_EN_ROUTE = 'en_route';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MISSED = 'missed';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_EMERGENCY = 'emergency';

    // Waste types
    public const WASTE_GENERAL = 'general';
    public const WASTE_RECYCLABLE = 'recyclable';
    public const WASTE_ORGANIC = 'organic';
    public const WASTE_HAZARDOUS = 'hazardous';
    public const WASTE_BULK = 'bulk';

    // ==================== ✅ APPROVAL STATUS CONSTANTS ====================

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';
    public const APPROVAL_EXPIRED = 'expired';
    public const APPROVAL_AUTO_APPROVED = 'auto_approved';

    public const APPROVAL_EXPIRY_DAYS = 7;

    // ==================== RELATIONSHIPS ====================

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get waste collection requests in this zone.
     */
    public function wasteCollectionRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'collection_zone_id');
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(SanitationPersonnel::class, 'assigned_to');
    }

    public function worker()
    {
        return $this->belongsTo(SanitationWorker::class, 'worker_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function landlord()
    {
        return $this->hasOneThrough(
            User::class,
            Property::class,
            'id',
            'id',
            'property_id',
            'landlord_id'
        );
    }

    // ==================== SCOPES ====================

    // Collection status scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ASSIGNED,
            self::STATUS_EN_ROUTE,
            self::STATUS_ARRIVED,
            self::STATUS_IN_PROGRESS
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByWasteType($query, $type)
    {
        return $query->where('waste_type', $type);
    }

    public function scopeAssignedTo($query, $personnelId)
    {
        return $query->where('assigned_to', $personnelId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereDate('created_at', '>=', $from)
                     ->whereDate('created_at', '<=', $to);
    }

    // ==================== ✅ APPROVAL SCOPES ====================

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', self::APPROVAL_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('approval_status', [self::APPROVAL_APPROVED, self::APPROVAL_AUTO_APPROVED]);
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', self::APPROVAL_REJECTED);
    }

    public function scopeExpiredApprovals($query)
    {
        return $query->where('approval_status', self::APPROVAL_PENDING)
            ->where('approval_expires_at', '<', now());
    }

    public function scopeRequiresApprovalReminder($query)
    {
        return $query->where('approval_status', self::APPROVAL_PENDING)
            ->where('approval_expires_at', '>', now())
            ->where(function($q) {
                $q->whereNull('approval_reminder_sent_at')
                  ->orWhere('approval_reminder_sent_at', '<', now()->subDays(3));
            })
            ->where('approval_reminder_count', '<', 2);
    }

    // ==================== STATIC HELPER METHODS ====================

    /**
     * Get all statuses for waste collection requests.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_EN_ROUTE => 'En Route',
            self::STATUS_ARRIVED => 'Arrived',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MISSED => 'Missed',
        ];
    }

    /**
     * Get all priorities for waste collection requests.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_EMERGENCY => 'Emergency',
        ];
    }

    /**
     * Get all waste types for waste collection requests.
     */
    public static function getWasteTypes(): array
    {
        return [
            self::WASTE_GENERAL => 'General Waste',
            self::WASTE_RECYCLABLE => 'Recyclable',
            self::WASTE_ORGANIC => 'Organic',
            self::WASTE_HAZARDOUS => 'Hazardous',
            self::WASTE_BULK => 'Bulk Waste',
        ];
    }

    /**
     * Get all approval statuses.
     */
    public static function getApprovalStatuses(): array
    {
        return [
            self::APPROVAL_PENDING => 'Pending Approval',
            self::APPROVAL_APPROVED => 'Approved',
            self::APPROVAL_REJECTED => 'Rejected',
            self::APPROVAL_EXPIRED => 'Expired',
            self::APPROVAL_AUTO_APPROVED => 'Auto-Approved',
        ];
    }

    /**
     * Get status badge class mapping.
     */
    public static function getStatusBadgeMap(): array
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_ASSIGNED => 'info',
            self::STATUS_EN_ROUTE => 'primary',
            self::STATUS_ARRIVED => 'secondary',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_MISSED => 'dark',
        ];
    }

    /**
     * Get priority badge class mapping.
     */
    public static function getPriorityBadgeMap(): array
    {
        return [
            self::PRIORITY_LOW => 'secondary',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_EMERGENCY => 'danger',
        ];
    }

    /**
     * Get waste type badge class mapping.
     */
    public static function getWasteTypeBadgeMap(): array
    {
        return [
            self::WASTE_GENERAL => 'secondary',
            self::WASTE_RECYCLABLE => 'success',
            self::WASTE_ORGANIC => 'warning',
            self::WASTE_HAZARDOUS => 'danger',
            self::WASTE_BULK => 'info',
        ];
    }

    /**
     * Get approval status badge class mapping.
     */
    public static function getApprovalBadgeMap(): array
    {
        return [
            self::APPROVAL_PENDING => 'warning',
            self::APPROVAL_APPROVED => 'success',
            self::APPROVAL_REJECTED => 'danger',
            self::APPROVAL_EXPIRED => 'secondary',
            self::APPROVAL_AUTO_APPROVED => 'info',
        ];
    }

    // ==================== ACCESSORS ====================

    // Collection status accessors
    public function getStatusBadgeAttribute()
    {
        $badges = self::getStatusBadgeMap();
        return $badges[$this->status] ?? 'secondary';
    }

    public function getStatusLabelAttribute()
    {
        $labels = self::getStatuses();
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getPriorityBadgeAttribute()
    {
        $badges = self::getPriorityBadgeMap();
        return $badges[$this->priority] ?? 'secondary';
    }

    public function getPriorityLabelAttribute()
    {
        $priorities = self::getPriorities();
        return $priorities[$this->priority] ?? ucfirst($this->priority);
    }

    public function getWasteTypeBadgeAttribute()
    {
        $badges = self::getWasteTypeBadgeMap();
        return $badges[$this->waste_type] ?? 'secondary';
    }

    public function getWasteTypeLabelAttribute()
    {
        $wasteTypes = self::getWasteTypes();
        return $wasteTypes[$this->waste_type] ?? ucfirst($this->waste_type);
    }

    public function getDurationAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInMinutes($this->completed_at);
        }
        return null;
    }

    public function getDurationFormattedAttribute()
    {
        $duration = $this->duration;
        if ($duration === null) {
            return 'N/A';
        }
        
        $hours = floor($duration / 60);
        $minutes = $duration % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes} minutes";
    }

    public function getLocationForMapAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
                'address' => $this->digital_address,
                'property_name' => $this->property->property_name ?? 'Unknown',
                'status' => $this->status,
                'priority' => $this->priority,
            ];
        }
        return null;
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_ASSIGNED,
            self::STATUS_EN_ROUTE,
            self::STATUS_ARRIVED,
            self::STATUS_IN_PROGRESS
        ]);
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // ==================== ✅ APPROVAL ACCESSORS ====================

    public function getApprovalStatusLabelAttribute(): string
    {
        $labels = self::getApprovalStatuses();
        return $labels[$this->approval_status] ?? $this->approval_status;
    }

    public function getApprovalBadgeAttribute(): string
    {
        $badges = self::getApprovalBadgeMap();
        return $badges[$this->approval_status] ?? 'secondary';
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->approval_expires_at) {
            return null;
        }
        return now()->diffInDays($this->approval_expires_at, false);
    }

    public function getIsApprovalExpiredAttribute(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING &&
               $this->approval_expires_at &&
               $this->approval_expires_at->isPast();
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING &&
               !$this->is_approval_expired;
    }

    public function getApprovalStatusWithIconAttribute(): string
    {
        $icons = [
            self::APPROVAL_PENDING => '⏳ Pending',
            self::APPROVAL_APPROVED => '✅ Approved',
            self::APPROVAL_REJECTED => '❌ Rejected',
            self::APPROVAL_EXPIRED => '⏰ Expired',
            self::APPROVAL_AUTO_APPROVED => '⚡ Auto-Approved',
        ];
        return $icons[$this->approval_status] ?? $this->approval_status;
    }

    // ==================== STATUS COLOR HELPERS ====================

    public function getPriorityColor(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_EMERGENCY => 'red',
            default => 'gray',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_ASSIGNED => 'blue',
            self::STATUS_EN_ROUTE => 'indigo',
            self::STATUS_ARRIVED => 'purple',
            self::STATUS_IN_PROGRESS => 'cyan',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_MISSED => 'gray',
            default => 'gray',
        };
    }

    public function getApprovalColor(): string
    {
        return match($this->approval_status) {
            self::APPROVAL_PENDING => 'yellow',
            self::APPROVAL_APPROVED => 'green',
            self::APPROVAL_REJECTED => 'red',
            self::APPROVAL_EXPIRED => 'gray',
            self::APPROVAL_AUTO_APPROVED => 'blue',
            default => 'gray',
        };
    }

    // ==================== METHODS ====================

    /**
     * Assign request to a personnel.
     */
    public function assignTo($personnelId): self
    {
        // Only allow assignment if approved
        if (!in_array($this->approval_status, [self::APPROVAL_APPROVED, self::APPROVAL_AUTO_APPROVED])) {
            Log::warning('Cannot assign request - not approved', [
                'request_id' => $this->id,
                'approval_status' => $this->approval_status
            ]);
            return $this;
        }

        $this->update([
            'assigned_to' => $personnelId,
            'status' => self::STATUS_ASSIGNED,
            'assigned_at' => now()
        ]);

        Log::info('Waste collection request assigned', [
            'request_id' => $this->id,
            'personnel_id' => $personnelId,
            'assigned_by' => auth()->id()
        ]);

        return $this;
    }

    /**
     * Assign worker to request.
     */
    public function assignWorker($workerId): self
    {
        if (!in_array($this->approval_status, [self::APPROVAL_APPROVED, self::APPROVAL_AUTO_APPROVED])) {
            Log::warning('Cannot assign worker - request not approved', [
                'request_id' => $this->id,
                'approval_status' => $this->approval_status
            ]);
            return $this;
        }

        $this->update([
            'worker_id' => $workerId,
            'status' => self::STATUS_ASSIGNED,
            'assigned_at' => now()
        ]);

        Log::info('Worker assigned to waste collection', [
            'request_id' => $this->id,
            'worker_id' => $workerId,
            'assigned_by' => auth()->id()
        ]);

        return $this;
    }

    /**
     * Mark as en route.
     */
    public function markEnRoute(): self
    {
        $this->update([
            'status' => self::STATUS_EN_ROUTE,
            'en_route_at' => now()
        ]);

        Log::info('Personnel en route to waste collection', [
            'request_id' => $this->id,
            'personnel_id' => auth()->id()
        ]);

        return $this;
    }

    /**
     * Mark as arrived.
     */
    public function markArrived(): self
    {
        $this->update([
            'status' => self::STATUS_ARRIVED,
            'arrived_at' => now()
        ]);

        Log::info('Personnel arrived at waste collection site', [
            'request_id' => $this->id,
            'personnel_id' => auth()->id()
        ]);

        return $this;
    }

    /**
     * Mark as started (in progress).
     */
    public function markStarted(): self
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now()
        ]);

        Log::info('Waste collection started', [
            'request_id' => $this->id,
            'personnel_id' => auth()->id()
        ]);

        return $this;
    }

    /**
     * Mark as completed.
     */
    public function markCompleted($notes = null, $weight = null, $afterPhoto = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $notes,
            'waste_weight_kg' => $weight,
            'after_photo' => $afterPhoto ?? $this->after_photo,
            'completion_time' => $this->started_at ? $this->started_at->diffInMinutes(now()) : null,
        ]);

        Log::info('Waste collection completed', [
            'request_id' => $this->id,
            'personnel_id' => auth()->id(),
            'weight' => $weight,
            'completion_time' => $this->completion_time
        ]);

        return $this;
    }

    /**
     * Cancel the request.
     */
    public function cancel($reason = null): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;
        $metadata['cancelled_by'] = auth()->id();
        $metadata['cancelled_at'] = now()->toISOString();

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'metadata' => $metadata,
        ]);

        Log::info('Waste collection cancelled', [
            'request_id' => $this->id,
            'cancelled_by' => auth()->id(),
            'reason' => $reason
        ]);

        return $this;
    }

    // ==================== ✅ APPROVAL METHODS ====================

    /**
     * Request landlord approval for waste collection
     */
    public function requestApproval(?int $requestedBy = null): bool
    {
        if ($this->approval_status !== self::APPROVAL_PENDING) {
            Log::warning('Approval already requested or processed', [
                'request_id' => $this->id,
                'current_status' => $this->approval_status
            ]);
            return false;
        }

        $this->update([
            'approval_requested_at' => now(),
            'approval_expires_at' => now()->addDays(self::APPROVAL_EXPIRY_DAYS),
            'approval_notification_sent' => false,
            'approval_reminder_count' => 0,
            'metadata' => array_merge($this->metadata ?? [], [
                'approval_requested' => [
                    'requested_at' => now()->toISOString(),
                    'requested_by' => $requestedBy ?? auth()->id(),
                    'requested_by_name' => auth()->user()?->name ?? 'System',
                    'expiry_days' => self::APPROVAL_EXPIRY_DAYS,
                ]
            ])
        ]);

        // Send notification to landlord
        $this->sendApprovalRequestNotification();

        Log::info('Approval request sent to landlord', [
            'request_id' => $this->id,
            'property_id' => $this->property_id,
            'landlord_id' => $this->property?->landlord_id,
            'expires_at' => $this->approval_expires_at
        ]);

        return true;
    }

    /**
     * Approve the waste collection request (Landlord action)
     */
    public function approve(?int $approvedBy = null, ?string $notes = null): bool
    {
        if (!$this->canApprove) {
            Log::warning('Cannot approve - request not in pending approval state or expired', [
                'request_id' => $this->id,
                'approval_status' => $this->approval_status,
                'is_expired' => $this->is_approval_expired
            ]);
            return false;
        }

        $this->update([
            'approval_status' => self::APPROVAL_APPROVED,
            'approval_responded_at' => now(),
            'approved_by' => $approvedBy ?? auth()->id(),
            'approval_notes' => $notes,
            'status' => self::STATUS_PENDING, // Ready for assignment
            'metadata' => array_merge($this->metadata ?? [], [
                'approval_response' => [
                    'responded_at' => now()->toISOString(),
                    'responded_by' => $approvedBy ?? auth()->id(),
                    'responded_by_name' => auth()->user()?->name ?? 'System',
                    'response' => 'approved',
                    'notes' => $notes,
                ]
            ])
        ]);

        // Update property waste collection metadata
        $this->updatePropertyCollectionSettings();

        // Send notification to sanitation personnel
        $this->sendApprovalResponseNotification('approved');

        Log::info('Waste collection request approved by landlord', [
            'request_id' => $this->id,
            'approved_by' => $approvedBy ?? auth()->id(),
            'property_id' => $this->property_id
        ]);

        return true;
    }

    /**
     * Reject the waste collection request (Landlord action)
     */
    public function reject(?int $rejectedBy = null, ?string $reason = null): bool
    {
        if (!$this->canApprove) {
            Log::warning('Cannot reject - request not in pending approval state or expired', [
                'request_id' => $this->id,
                'approval_status' => $this->approval_status,
                'is_expired' => $this->is_approval_expired
            ]);
            return false;
        }

        $this->update([
            'approval_status' => self::APPROVAL_REJECTED,
            'approval_responded_at' => now(),
            'approved_by' => $rejectedBy ?? auth()->id(),
            'rejection_reason' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'approval_response' => [
                    'responded_at' => now()->toISOString(),
                    'responded_by' => $rejectedBy ?? auth()->id(),
                    'responded_by_name' => auth()->user()?->name ?? 'System',
                    'response' => 'rejected',
                    'reason' => $reason,
                ]
            ])
        ]);

        // Send notification to sanitation personnel
        $this->sendApprovalResponseNotification('rejected');

        Log::info('Waste collection request rejected by landlord', [
            'request_id' => $this->id,
            'rejected_by' => $rejectedBy ?? auth()->id(),
            'reason' => $reason,
            'property_id' => $this->property_id
        ]);

        return true;
    }

    /**
     * Auto-approve if landlord doesn't respond within expiry
     */
    public function autoApprove(): bool
    {
        if ($this->approval_status !== self::APPROVAL_PENDING) {
            return false;
        }

        if (!$this->is_approval_expired) {
            return false;
        }

        $this->update([
            'approval_status' => self::APPROVAL_AUTO_APPROVED,
            'approval_responded_at' => now(),
            'status' => self::STATUS_PENDING,
            'metadata' => array_merge($this->metadata ?? [], [
                'auto_approval' => [
                    'auto_approved_at' => now()->toISOString(),
                    'reason' => 'Landlord did not respond within ' . self::APPROVAL_EXPIRY_DAYS . ' days',
                ]
            ])
        ]);

        // Update property collection settings
        $this->updatePropertyCollectionSettings();

        Log::info('Waste collection request auto-approved (landlord did not respond)', [
            'request_id' => $this->id,
            'property_id' => $this->property_id,
            'expired_at' => $this->approval_expires_at
        ]);

        return true;
    }

    /**
     * Send reminder to landlord about pending approval
     */
    public function sendReminder(): bool
    {
        if ($this->approval_status !== self::APPROVAL_PENDING) {
            return false;
        }

        if ($this->is_approval_expired) {
            return false;
        }

        $landlord = $this->property?->landlord;
        
        if (!$landlord) {
            Log::warning('Cannot send reminder - no landlord found', [
                'request_id' => $this->id
            ]);
            return false;
        }

        try {
            // Send reminder notification (you'll need to create these notifications)
            // $landlord->notify(new \App\Notifications\WasteCollectionApprovalReminder($this));

            $this->update([
                'approval_reminder_sent_at' => now(),
                'approval_reminder_count' => $this->approval_reminder_count + 1,
            ]);

            Log::info('Approval reminder sent to landlord', [
                'request_id' => $this->id,
                'landlord_id' => $landlord->id,
                'reminder_count' => $this->approval_reminder_count
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send approval reminder: ' . $e->getMessage(), [
                'request_id' => $this->id
            ]);
            return false;
        }
    }

    // ==================== ✅ NOTIFICATION METHODS ====================

    /**
     * Send approval request notification to landlord
     */
    protected function sendApprovalRequestNotification(): void
    {
        $landlord = $this->property?->landlord;
        
        if (!$landlord) {
            Log::warning('No landlord found for approval notification', [
                'request_id' => $this->id,
                'property_id' => $this->property_id
            ]);
            return;
        }

        try {
            // Send email notification (uncomment when mail class is created)
            // Mail::to($landlord->email)->send(new \App\Mail\WasteCollectionApprovalRequest($this, $landlord));
            
            // Send in-app notification (uncomment when notification is created)
            // $landlord->notify(new \App\Notifications\WasteCollectionApprovalRequested($this));

            $this->update(['approval_notification_sent' => true]);

            Log::info('Approval request notification sent to landlord', [
                'request_id' => $this->id,
                'landlord_id' => $landlord->id,
                'landlord_email' => $landlord->email,
                'property_id' => $this->property_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send approval request notification: ' . $e->getMessage(), [
                'request_id' => $this->id,
                'landlord_id' => $landlord->id ?? null
            ]);
        }
    }

    /**
     * Send approval response notification to sanitation personnel
     */
    protected function sendApprovalResponseNotification(string $response): void
    {
        $requestedBy = $this->requestedBy;
        
        if (!$requestedBy) {
            return;
        }

        try {
            // Send in-app notification (uncomment when notifications are created)
            // if ($response === 'approved') {
            //     $requestedBy->notify(new \App\Notifications\WasteCollectionApproved($this));
            // } else {
            //     $requestedBy->notify(new \App\Notifications\WasteCollectionRejected($this));
            // }

            Log::info('Approval response notification sent', [
                'request_id' => $this->id,
                'response' => $response,
                'recipient_id' => $requestedBy->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send approval response notification: ' . $e->getMessage(), [
                'request_id' => $this->id,
                'response' => $response
            ]);
        }
    }

    /**
     * Update property collection settings after approval
     */
    protected function updatePropertyCollectionSettings(): void
    {
        $property = $this->property;
        
        if (!$property) {
            return;
        }

        $metadata = $property->metadata ?? [];
        $metadata['waste_collection'] = array_merge(
            $metadata['waste_collection'] ?? [],
            [
                'linked' => true,
                'linked_at' => now()->toISOString(),
                'linked_by' => auth()->id(),
                'linked_by_name' => auth()->user()?->name ?? 'System',
                'approval_status' => $this->approval_status,
                'approved_at' => $this->approval_responded_at?->toISOString(),
                'approved_by' => $this->approved_by,
                'collection_zone_id' => $this->metadata['collection_zone_id'] ?? null,
                'collection_frequency' => $this->metadata['collection_frequency'] ?? 'weekly',
                'collection_days' => $this->metadata['collection_days'] ?? ['Monday'],
                'waste_types' => $this->metadata['waste_types'] ?? ['general'],
                'special_instructions' => $this->metadata['special_instructions'] ?? null,
                'emergency_contact' => $this->metadata['emergency_contact'] ?? null,
            ]
        );
        
        $property->update(['metadata' => $metadata]);

        Log::info('Property collection settings updated after approval', [
            'property_id' => $property->id,
            'request_id' => $this->id,
            'approval_status' => $this->approval_status
        ]);
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Add a checkpoint to the route.
     */
    public function addCheckpoint($lat, $lng, $type = 'waypoint'): self
    {
        $checkpoints = $this->checkpoints ?? [];
        $checkpoints[] = [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'type' => $type,
            'timestamp' => now()->toISOString(),
        ];

        $this->update(['checkpoints' => $checkpoints]);

        return $this;
    }

    /**
     * Update route coordinates.
     */
    public function updateRoute($coordinates): self
    {
        $this->update(['route_coordinates' => $coordinates]);
        return $this;
    }

    /**
     * Get status history as array.
     */
    public function getStatusHistory(): array
    {
        $history = [];
        $statuses = [
            'assigned_at' => 'assigned',
            'en_route_at' => 'en_route',
            'arrived_at' => 'arrived',
            'started_at' => 'in_progress',
            'completed_at' => 'completed',
        ];

        foreach ($statuses as $field => $status) {
            if ($this->$field) {
                $history[] = [
                    'status' => $status,
                    'timestamp' => $this->$field,
                    'label' => $this->getStatusLabelForField($status),
                ];
            }
        }

        return $history;
    }

    /**
     * Get approval history as array.
     */
    public function getApprovalHistory(): array
    {
        $history = [];

        if ($this->approval_requested_at) {
            $history[] = [
                'event' => 'Requested',
                'timestamp' => $this->approval_requested_at,
                'label' => 'Approval Requested',
            ];
        }

        if ($this->approval_responded_at) {
            $status = $this->approval_status;
            $label = $status === self::APPROVAL_APPROVED ? 'Approved' : 
                    ($status === self::APPROVAL_REJECTED ? 'Rejected' : 
                    ($status === self::APPROVAL_AUTO_APPROVED ? 'Auto-Approved' : 'Responded'));
            
            $history[] = [
                'event' => 'responded',
                'timestamp' => $this->approval_responded_at,
                'label' => $label,
                'status' => $this->approval_status,
                'notes' => $this->approval_notes ?? $this->rejection_reason,
            ];
        }

        if ($this->approval_expires_at && $this->is_approval_expired) {
            $history[] = [
                'event' => 'expired',
                'timestamp' => $this->approval_expires_at,
                'label' => 'Approval Expired',
            ];
        }

        return $history;
    }

    /**
     * Get status label for field.
     */
    private function getStatusLabelForField($status): string
    {
        $labels = [
            'assigned' => 'Assigned',
            'en_route' => 'En Route',
            'arrived' => 'Arrived',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * Calculate estimated completion time.
     */
    public function getEstimatedCompletionTime(): ?int
    {
        if (!$this->assigned_at) {
            return null;
        }

        // If already completed, return actual time
        if ($this->completed_at) {
            return $this->assigned_at->diffInMinutes($this->completed_at);
        }

        // Calculate based on average completion time
        $avgCompletionTime = self::where('status', self::STATUS_COMPLETED)
            ->whereNotNull('completion_time')
            ->avg('completion_time');

        if ($avgCompletionTime) {
            return (int) $avgCompletionTime;
        }

        return 60; // Default 60 minutes estimate
    }

    /**
     * Check if request is urgent.
     */
    public function isUrgent(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_EMERGENCY]);
    }

    /**
     * Check if request is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        if (!$this->assigned_at) {
            return false;
        }

        $estimatedTime = $this->getEstimatedCompletionTime() ?? 60;
        $deadline = $this->assigned_at->addMinutes($estimatedTime);

        return now()->greaterThan($deadline);
    }

    /**
     * Get formatted weight with unit.
     */
    public function getFormattedWeightAttribute(): string
    {
        if (!$this->waste_weight_kg) {
            return 'N/A';
        }
        return number_format($this->waste_weight_kg, 2) . ' kg';
    }

    /**
     * Get completion status for display.
     */
    public function getCompletionStatusAttribute(): string
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return '✅ Completed';
        }
        if ($this->status === self::STATUS_CANCELLED) {
            return '❌ Cancelled';
        }
        if ($this->is_overdue) {
            return '⚠️ Overdue';
        }
        return '⏳ In Progress';
    }
    /**
 * Cooldown period (hours) between two bin-full reports for the same property.
 */
public const BIN_FULL_COOLDOWN_HOURS = 6;

/**
 * Does this property have a fresh bin-full report already?
 */
public static function hasFreshBinFullReport(int $propertyId): bool
{
    return static::where('property_id', $propertyId)
        ->where('source', 'bin_full')
        ->where('created_at', '>=', now()->subHours(self::BIN_FULL_COOLDOWN_HOURS))
        ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
        ->exists();
}

/**
 * Latest bin-full report for a property.
 */
public static function latestBinFullReport(int $propertyId): ?self
{
    return static::where('property_id', $propertyId)
        ->where('source', 'bin_full')
        ->latest()
        ->first();
}

}