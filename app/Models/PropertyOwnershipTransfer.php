<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Services\OwnershipTransferService;
use App\Services\DigitalSignatureService;

class PropertyOwnershipTransfer extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    // Document types
    public const DOCUMENT_SALE_DEED = 'sale_deed';
    public const DOCUMENT_TITLE_DEED = 'title_deed';
    public const DOCUMENT_CONVEYANCE = 'conveyance';
    public const DOCUMENT_TRANSFER_CERTIFICATE = 'transfer_certificate';
    public const DOCUMENT_GIFT_DEED = 'gift_deed';
    public const DOCUMENT_WILL_PROBATE = 'will_probate';
    public const DOCUMENT_OTHER = 'other';

    // Workflow steps
    public const WORKFLOW_APPROVAL = 'approval';
    public const WORKFLOW_VERIFICATION = 'verification';
    public const WORKFLOW_COMPLETION = 'completion';

    // Reversal status constants
    public const REVERSAL_STATUS_NONE = 'none';
    public const REVERSAL_STATUS_PENDING = 'pending';
    public const REVERSAL_STATUS_APPROVED = 'approved';
    public const REVERSAL_STATUS_REJECTED = 'rejected';
    public const REVERSAL_STATUS_COMPLETED = 'completed';
    public const REVERSAL_STATUS_EXPIRED = 'expired';

    protected $table = 'property_ownership_transfers';

    protected $fillable = [
        'property_id',
        'current_landlord_id',
        'new_landlord_id',
        'requested_by_id',
        'admin_approved_by_id',
        'completed_by_id',
        'rejected_by_id',
        'status',
        'transfer_date',
        'sale_amount',
        'document_type',
        'document_reference',
        'document_url',
        'certificate_url',
        'new_owner_name',
        'new_owner_phone',
        'new_owner_email',
        'new_owner_address',
        'new_owner_id_type',
        'new_owner_id_number',
        'reason_for_transfer',
        'notes',
        'admin_notes',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'completed_at',
        'can_resubmit_after',
        'approval_workflow_step',
        'digital_signature_verified',
        'bulk_group_id',
        'bulk_position',
        'metadata',
        'reversal_requested_at',
        'reversal_requested_by',
        'reversal_reason',
        'reversal_processed_at',
        'reversal_processed_by',
        'reversal_status',
        'reversal_admin_notes',
        'reversal_deadline',
        'is_reversed',
        'reversal_transfer_id',
        'original_transfer_id',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'transfer_date' => 'date',
        'sale_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'can_resubmit_after' => 'datetime',
        'digital_signature_verified' => 'boolean',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
        'reversal_requested_at' => 'datetime',
        'reversal_processed_at' => 'datetime',
        'reversal_deadline' => 'datetime',
        'is_reversed' => 'boolean',
        'deleted_by' => 'integer',
    ];

    protected $appends = [
        'status_label',
        'document_type_label',
        'is_pending',
        'is_approved',
        'is_completed',
        'is_rejected',
        'is_cancelled',
        'transfer_document_url',
        'can_be_approved',
        'can_be_rejected',
        'can_be_completed',
        'can_be_cancelled',
        'requires_digital_signature',
        'is_bulk_transfer',
        'processing_time_days',
        'formatted_sale_amount',
        'certificate_url',
        'days_in_trash',
        'trashed_at_formatted',
        'can_be_restored',
        'can_be_permanently_deleted',
        'can_resubmit',
        'next_workflow_step',
        'transfer_history',
        'rejection_details',
        'is_for_existing_landlord',
        'new_owner_display_name',
        'new_owner_display_email',
        'new_owner_display_phone',
        'is_reversal_record',
        'reversal_status_label',
        'has_reversal_request',
        'can_request_reversal',
        'reversal_details',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->status = $model->status ?? self::STATUS_PENDING;
            $model->approval_workflow_step = $model->approval_workflow_step ?? self::WORKFLOW_APPROVAL;
            $model->generateDocumentReference();
            
            if (!$model->requested_by_id && auth()->check()) {
                $model->requested_by_id = auth()->id();
            }
            
            if (!$model->reversal_status) {
                $model->reversal_status = self::REVERSAL_STATUS_NONE;
            }
        });

        static::updating(function ($model) {
            $originalStatus = $model->getOriginal('status');
            $newStatus = $model->status;
            
            if ($originalStatus !== $newStatus) {
                switch ($newStatus) {
                    case self::STATUS_APPROVED:
                        $model->approved_at = now();
                        break;
                    case self::STATUS_REJECTED:
                        $model->rejected_at = now();
                        break;
                    case self::STATUS_CANCELLED:
                        $model->cancelled_at = now();
                        break;
                    case self::STATUS_COMPLETED:
                        $model->completed_at = $model->completed_at ?? now();
                        if (!$model->completed_by_id && auth()->check()) {
                            $model->completed_by_id = auth()->id();
                        }
                        break;
                }
            }
            
            if ($model->isDirty('status') && $model->status === self::STATUS_APPROVED) {
                $model->approval_workflow_step = self::WORKFLOW_VERIFICATION;
            }
            
            if ($model->isDirty('status') && $model->status === self::STATUS_COMPLETED) {
                $model->approval_workflow_step = self::WORKFLOW_COMPLETION;
            }
        });

        // ==============================================
// CASCADE SOFT DELETE - FIXED (Actually delete reversal records)
// ==============================================
static::deleting(function ($transfer) {
    // Static flag to prevent recursive loops
    static $isDeleting = false;
    
    if ($isDeleting) {
        Log::info('Skipping recursive cascade deletion', [
            'transfer_id' => $transfer->id
        ]);
        return;
    }
    
    $isDeleting = true;
    
    try {
        Log::info('Deleting event triggered for transfer', [
            'transfer_id' => $transfer->id,
            'is_reversal_record' => $transfer->isReversalRecord()
        ]);
        
        // ALWAYS find related reversal records (both directions)
        $relatedReversals = collect();
        
        if ($transfer->isReversalRecord()) {
            // If this is a reversal record, find its original transfer
            $originalId = $transfer->original_transfer_id ?? $transfer->metadata['original_transfer_id'] ?? null;
            if ($originalId) {
                $original = self::withTrashed()->find($originalId);
                if ($original && !$original->trashed()) {
                    $relatedReversals->push($original);
                }
            }
        } else {
            // If this is a regular transfer, find its reversal records
            $reversals = $transfer->reversalRecords()->get();
            foreach ($reversals as $reversal) {
                if (!$reversal->trashed()) {
                    $relatedReversals->push($reversal);
                }
            }
        }
        
        Log::info('Found related records to cascade', [
            'parent_id' => $transfer->id,
            'is_reversal' => $transfer->isReversalRecord(),
            'count' => $relatedReversals->count(),
            'ids' => $relatedReversals->pluck('id')->toArray()
        ]);
        
        // Delete all related records
        foreach ($relatedReversals as $related) {
            if (!$related->trashed()) {
                Log::info('Cascading soft delete to related record', [
                    'parent_id' => $transfer->id,
                    'related_id' => $related->id,
                    'related_is_reversal' => $related->isReversalRecord()
                ]);
                $related->delete();
            }
        }
        
    } finally {
        $isDeleting = false;
    }
});

        // ==============================================
        // CASCADE RESTORE - FIXED
        // ==============================================
        static::restoring(function ($transfer) {
            static $isRestoring = false;
            
            if ($isRestoring) {
                return;
            }
            
            $isRestoring = true;
            
            try {
                Log::info('Restoring event triggered for transfer', [
                    'transfer_id' => $transfer->id,
                    'is_reversal_record' => $transfer->isReversalRecord()
                ]);
                
                if (!$transfer->isReversalRecord()) {
                    $reversalRecords = $transfer->reversalRecords()->withTrashed()->get();
                    
                    foreach ($reversalRecords as $reversal) {
                        if ($reversal->trashed()) {
                            Log::info('Cascading restore to reversal record', [
                                'parent_id' => $transfer->id,
                                'reversal_id' => $reversal->id
                            ]);
                            $reversal->restore();
                        }
                    }
                }
            } finally {
                $isRestoring = false;
            }
        });

        // ==============================================
        // CASCADE FORCE DELETE - FIXED
        // ==============================================
        static::forceDeleting(function ($transfer) {
            static $isForceDeleting = false;
            
            if ($isForceDeleting) {
                return;
            }
            
            $isForceDeleting = true;
            
            try {
                Log::info('Force deleting event triggered for transfer', [
                    'transfer_id' => $transfer->id,
                    'is_reversal_record' => $transfer->isReversalRecord()
                ]);
                
                if (!$transfer->isReversalRecord()) {
                    $reversalRecords = $transfer->reversalRecords()->withTrashed()->get();
                    
                    foreach ($reversalRecords as $reversal) {
                        Log::info('Cascading force delete to reversal record', [
                            'parent_id' => $transfer->id,
                            'reversal_id' => $reversal->id
                        ]);
                        
                        if ($reversal->document_url) {
                            Storage::disk('public')->delete($reversal->document_url);
                        }
                        if ($reversal->certificate_url) {
                            Storage::disk('public')->delete($reversal->certificate_url);
                        }
                        
                        $reversal->forceDelete();
                    }
                }
            } finally {
                $isForceDeleting = false;
            }
        });

        static::created(function ($model) {
            Log::info('Property ownership transfer created', [
                'transfer_id' => $model->id,
                'property_id' => $model->property_id,
                'status' => $model->status,
                'requested_by' => $model->requested_by_id,
                'is_reversal' => $model->isReversalRecord()
            ]);
        });

        static::updated(function ($model) {
            if ($model->isDirty('status')) {
                Log::info('Property ownership transfer status changed', [
                    'transfer_id' => $model->id,
                    'old_status' => $model->getOriginal('status'),
                    'new_status' => $model->status,
                    'changed_by' => auth()->id() ?? 'system',
                    'property_id' => $model->property_id
                ]);
            }
            
            if ($model->isDirty('reversal_status')) {
                Log::info('Property ownership transfer reversal status changed', [
                    'transfer_id' => $model->id,
                    'old_reversal_status' => $model->getOriginal('reversal_status'),
                    'new_reversal_status' => $model->reversal_status,
                    'is_reversed' => $model->is_reversed
                ]);
            }
        });
    }

    /**
     * Generate unique document reference
     */
    protected function generateDocumentReference(): void
    {
        if (empty($this->document_reference)) {
            $datePrefix = now()->format('Ymd');
            $random = strtoupper(substr(md5(uniqid()), 0, 6));
            $this->document_reference = "TRANS-{$datePrefix}-{$random}";
        }
    }
    

    // =============================================
    // REVERSAL RELATIONSHIPS
    // =============================================

    /**
     * Get the user who requested the reversal
     */
    public function reversalRequestedBy()
    {
        return $this->belongsTo(User::class, 'reversal_requested_by');
    }

    /**
     * Get the user who processed the reversal (approved/rejected)
     */
    public function reversalProcessedBy()
    {
        return $this->belongsTo(User::class, 'reversal_processed_by');
    }

    /**
     * Get the reversal transfer record (the audit trail transfer created when reversal happens)
     */
    public function reversalTransfer()
    {
        return $this->belongsTo(PropertyOwnershipTransfer::class, 'reversal_transfer_id');
    }

    /**
     * Get the original transfer that was reversed (for reversal transfer records)
     */
    public function originalTransfer()
    {
        return $this->belongsTo(PropertyOwnershipTransfer::class, 'original_transfer_id');
    }

    /**
     * Get all reversal records related to this transfer (for normal transfers)
     */
    public function reversalRecords()
    {
        return $this->hasMany(PropertyOwnershipTransfer::class, 'original_transfer_id')
            ->where('metadata->is_reversal', true);
    }

    // =============================================
    // REVERSAL HELPER METHODS
    // =============================================

    /**
     * Check if this is a reversal record
     */
    public function isReversalRecord(): bool
    {
        if ($this->original_transfer_id) {
            return true;
        }
        
        return isset($this->metadata['is_reversal']) && $this->metadata['is_reversal'] === true;
    }

    /**
     * Get the reversal status label
     */
    public function getReversalStatusLabelAttribute(): string
    {
        $labels = [
            self::REVERSAL_STATUS_NONE => 'No Reversal',
            self::REVERSAL_STATUS_PENDING => 'Pending Review',
            self::REVERSAL_STATUS_APPROVED => 'Approved',
            self::REVERSAL_STATUS_REJECTED => 'Rejected',
            self::REVERSAL_STATUS_COMPLETED => 'Completed',
            self::REVERSAL_STATUS_EXPIRED => 'Expired',
        ];

        return $labels[$this->reversal_status] ?? ucfirst($this->reversal_status);
    }

    /**
     * Get whether this transfer has a reversal request
     */
    public function getHasReversalRequestAttribute(): bool
    {
        return $this->reversal_status === self::REVERSAL_STATUS_PENDING;
    }

    /**
     * Get whether reversal is possible
     */
    public function getCanRequestReversalAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED && 
               !$this->is_reversed && 
               $this->reversal_status !== self::REVERSAL_STATUS_PENDING;
    }

    /**
     * Get reversal details
     */
    public function getReversalDetailsAttribute(): ?array
    {
        if ($this->reversal_status === self::REVERSAL_STATUS_NONE) {
            return null;
        }
        
        return [
            'status' => $this->reversal_status,
            'status_label' => $this->reversal_status_label,
            'reason' => $this->reversal_reason,
            'requested_at' => $this->reversal_requested_at ? $this->reversal_requested_at->toISOString() : null,
            'requested_at_formatted' => $this->reversal_requested_at ? $this->reversal_requested_at->format('M j, Y H:i') : null,
            'requested_by' => $this->reversalRequestedBy ? $this->reversalRequestedBy->name : null,
            'deadline' => $this->reversal_deadline ? $this->reversal_deadline->toISOString() : null,
            'deadline_formatted' => $this->reversal_deadline ? $this->reversal_deadline->format('M j, Y') : null,
            'processed_at' => $this->reversal_processed_at ? $this->reversal_processed_at->toISOString() : null,
            'processed_by' => $this->reversalProcessedBy ? $this->reversalProcessedBy->name : null,
            'admin_notes' => $this->reversal_admin_notes,
            'is_reversed' => $this->is_reversed,
        ];
    }

    /**
     * Check if reversal request is expired
     */
    public function isReversalExpired(): bool
    {
        return $this->reversal_status === self::REVERSAL_STATUS_PENDING &&
               $this->reversal_deadline &&
               $this->reversal_deadline->isPast();
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function property()
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function currentLandlord()
    {
        return $this->belongsTo(User::class, 'current_landlord_id')->withTrashed();
    }

    public function newLandlord()
    {
        return $this->belongsTo(User::class, 'new_landlord_id')->withTrashed();
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id')->withTrashed();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'admin_approved_by_id')->withTrashed();
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_id')->withTrashed();
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_id')->withTrashed();
    }

    public function webhookLogs()
    {
        if (class_exists(\App\Models\WebhookLog::class)) {
            return $this->hasMany(WebhookLog::class, 'model_id')
                ->where('model_type', self::class);
        }
        
        return $this->hasMany(User::class, 'id')->whereRaw('1 = 0');
    }

    public function activityLogs()
    {
        if (class_exists(\App\Models\ActivityLog::class)) {
            return $this->morphMany(ActivityLog::class, 'loggable')
                ->orderBy('created_at', 'desc');
        }
        
        return $this->hasMany(User::class, 'id')->whereRaw('1 = 0');
    }

    public function certificate()
    {
        if (class_exists(\App\Models\TransferCertificate::class)) {
            return $this->hasOne(TransferCertificate::class);
        }
        
        return $this->hasOne(User::class, 'id')->whereRaw('1 = 0');
    }

    /**
     * Get related transfers in the same bulk group
     */
    public function relatedTransfers()
    {
        if (empty($this->bulk_group_id)) {
            return self::whereRaw('1 = 0');
        }

        return self::where('bulk_group_id', $this->bulk_group_id)
            ->where('id', '!=', $this->id);
    }

    /**
     * Get the current ownership transfer for the property (if any)
     */
    public function scopeCurrentTransfer($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_APPROVED
        ]);
    }

    // =============================================
    // REVERSAL SCOPES
    // =============================================

    public function scopePendingReversals($query)
    {
        return $query->where('reversal_status', self::REVERSAL_STATUS_PENDING)
            ->where('status', self::STATUS_COMPLETED)
            ->where('is_reversed', false);
    }

    public function scopeApprovedReversals($query)
    {
        return $query->where('reversal_status', self::REVERSAL_STATUS_APPROVED);
    }

    public function scopeCompletedReversals($query)
    {
        return $query->where('reversal_status', self::REVERSAL_STATUS_COMPLETED)
            ->where('is_reversed', true);
    }

    public function scopeReversalRecords($query)
    {
        return $query->where('metadata->is_reversal', true)
            ->orWhereNotNull('original_transfer_id');
    }

    public function scopeExpiredReversals($query)
    {
        return $query->where('reversal_status', self::REVERSAL_STATUS_PENDING)
            ->where('reversal_deadline', '<', now());
    }

    // =============================================
    // QUERY SCOPES
    // =============================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeRequiringAction($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeForLandlord($query, $landlordId)
    {
        return $query->where(function($q) use ($landlordId) {
            $q->where('current_landlord_id', $landlordId)
              ->orWhere('new_landlord_id', $landlordId);
        });
    }

    public function scopeWithDigitalSignature($query)
    {
        return $query->where('metadata->digital_signature', '!=', null);
    }

    public function scopeWithoutDigitalSignature($query)
    {
        return $query->whereNull('metadata->digital_signature')
                     ->orWhere('metadata->digital_signature', '=', '');
    }

    public function scopeBulkTransfers($query)
    {
        return $query->whereNotNull('bulk_group_id');
    }

    public function scopeIndividualTransfers($query)
    {
        return $query->whereNull('bulk_group_id');
    }

    public function scopeNeedsVerification($query)
    {
        return $query->where('approval_workflow_step', self::WORKFLOW_VERIFICATION);
    }

    public function scopeExpired($query, $days = 30)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('created_at', '<', now()->subDays($days));
    }

    public function scopeTrashedOlderThan(Builder $query, int $days): Builder
    {
        return $query->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days));
    }

    public function scopeTrashedByStatus(Builder $query, string $status): Builder
    {
        return $query->onlyTrashed()
            ->where('metadata->soft_deleted->status_at_deletion', $status);
    }

    public function scopeTrashedByUser(Builder $query, int $userId): Builder
    {
        return $query->onlyTrashed()
            ->where('metadata->soft_deleted->deleted_by', $userId);
    }

    public function scopeTrashedReversals(Builder $query): Builder
    {
        return $query->onlyTrashed()
            ->where(function($q) {
                $q->where('metadata->is_reversal', true)
                  ->orWhereNotNull('original_transfer_id');
            });
    }

    // =============================================
    // TRASH MANAGEMENT METHODS
    // =============================================

    public function canBeRestored(): bool
    {
        if (!$this->trashed()) {
            return false;
        }
        
        if ($this->isReversalRecord()) {
            return true;
        }
        
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }
        
        if (!$this->property) {
            return false;
        }
        
        if ($this->status === self::STATUS_PENDING) {
            $existingTransfer = self::where('property_id', $this->property_id)
                ->where('status', self::STATUS_PENDING)
                ->where('id', '!=', $this->id)
                ->first();
            
            if ($existingTransfer) {
                return false;
            }
        }
        
        return true;
    }

    public function canBePermanentlyDeleted(): bool
    {
        if (!$this->trashed()) {
            return false;
        }
        
        if ($this->isReversalRecord()) {
            return true;
        }
        
        $minDaysInTrash = config('ownership_transfer.min_days_before_permanent_delete', 0);
        
        if ($minDaysInTrash > 0 && $this->deleted_at && $this->deleted_at->diffInDays(now()) < $minDaysInTrash) {
            return false;
        }
        
        return true;
    }

    public function getDaysInTrashAttribute(): ?int
    {
        if (!$this->trashed() || !$this->deleted_at) {
            return null;
        }
        
        return $this->deleted_at->diffInDays(now());
    }

    public function getTrashedAtFormattedAttribute(): string
    {
        if (!$this->trashed() || !$this->deleted_at) {
            return 'N/A';
        }
        
        return $this->deleted_at->format('M j, Y H:i:s');
    }

    public function getDeletedByInfoAttribute(): ?array
    {
        $metadata = $this->metadata ?? [];
        
        if (isset($metadata['soft_deleted'])) {
            return [
                'user_id' => $metadata['soft_deleted']['deleted_by'] ?? null,
                'user_name' => $metadata['soft_deleted']['deleted_by_name'] ?? 'Unknown',
                'deleted_at' => $metadata['soft_deleted']['deleted_at'] ?? null,
                'deleted_reason' => $metadata['soft_deleted']['deleted_reason'] ?? null,
                'ip_address' => $metadata['soft_deleted']['ip_address'] ?? null,
                'is_reversal_record' => $metadata['soft_deleted']['is_reversal_record'] ?? false,
            ];
        }
        
        return null;
    }

    public function getRestoredInfoAttribute(): ?array
    {
        $metadata = $this->metadata ?? [];
        
        if (isset($metadata['restored'])) {
            return [
                'restored_at' => $metadata['restored']['restored_at'] ?? null,
                'restored_by' => $metadata['restored']['restored_by'] ?? null,
                'restored_by_name' => $metadata['restored']['restored_by_name'] ?? null,
                'restored_from_status' => $metadata['restored']['restored_from_status'] ?? null,
                'is_reversal_record' => $metadata['restored']['is_reversal_record'] ?? false,
            ];
        }
        
        return null;
    }

    public static function cleanupOldTrashedRecords(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $transfers = self::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();
        
        $deletedCount = 0;
        
        foreach ($transfers as $transfer) {
            if ($transfer->document_url) {
                Storage::disk('public')->delete($transfer->document_url);
            }
            
            if ($transfer->certificate && method_exists($transfer, 'certificate')) {
                if ($transfer->certificate && $transfer->certificate->certificate_url) {
                    Storage::disk('public')->delete($transfer->certificate->certificate_url);
                }
                if ($transfer->certificate) {
                    $transfer->certificate->forceDelete();
                }
            }
            
            if ($transfer->webhookLogs && method_exists($transfer->webhookLogs(), 'forceDelete')) {
                $transfer->webhookLogs()->forceDelete();
            }
            
            if ($transfer->activityLogs && method_exists($transfer->activityLogs(), 'forceDelete')) {
                $transfer->activityLogs()->forceDelete();
            }
            
            $transfer->forceDelete();
            $deletedCount++;
        }
        
        if ($deletedCount > 0) {
            Log::info('Cleaned up old trashed transfers', [
                'deleted_count' => $deletedCount,
                'days_to_keep' => $daysToKeep,
                'cutoff_date' => $cutoffDate
            ]);
        }
        
        return $deletedCount;
    }

    // =============================================
    // BUSINESS LOGIC METHODS
    // =============================================
    
    public function canBeApproved(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->requires_digital_signature && 
            !$this->digital_signature_verified) {
            return false;
        }

        return $this->checkApprovalRules();
    }

    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCompleted(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || empty($this->new_landlord_id)) {
            return false;
        }

        $newLandlord = $this->newLandlord;
        if (!$newLandlord || $newLandlord->status !== User::STATUS_ACTIVE) {
            return false;
        }

        if ($this->requires_digital_signature && 
            !$this->digital_signature_verified) {
            return false;
        }

        return true;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    private function checkApprovalRules(): bool
    {
        $rules = config('ownership_transfer.approval_rules', []);
        
        foreach ($rules as $rule) {
            if (!$this->checkRule($rule)) {
                return false;
            }
        }
        
        return true;
    }

    private function checkRule(array $rule): bool
    {
        switch ($rule['type']) {
            case 'amount_limit':
                return $this->sale_amount <= $rule['max_amount'];
            
            case 'property_type':
                $propertyType = $this->property->property_type ?? null;
                return !in_array($propertyType, $rule['restricted_types'] ?? []);
            
            case 'document_type':
                return !in_array($this->document_type, $rule['restricted_types'] ?? []);
            
            default:
                return true;
        }
    }

    public function markAsVerified(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || 
            $this->approval_workflow_step !== self::WORKFLOW_VERIFICATION) {
            return false;
        }

        $this->approval_workflow_step = self::WORKFLOW_COMPLETION;
        return $this->save();
    }

    public function resubmit(array $data = []): bool
    {
        if (!$this->can_resubmit) {
            return false;
        }

        $this->status = self::STATUS_PENDING;
        $this->approval_workflow_step = self::WORKFLOW_APPROVAL;
        $this->rejection_reason = null;
        $this->can_resubmit_after = null;
        $this->rejected_by_id = null;
        $this->rejected_at = null;
        
        if (!empty($data)) {
            $this->fill($data);
        }

        return $this->save();
    }

    public function sendInvitation(): array
    {
        $isExistingLandlord = $this->metadata['is_existing_landlord'] ?? false;
        
        if ($isExistingLandlord) {
            Log::info('Skipping invitation for existing landlord from model', [
                'transfer_id' => $this->id,
                'new_landlord_id' => $this->new_landlord_id,
                'new_owner_name' => $this->new_owner_name
            ]);
            return [
                'success' => true, 
                'message' => 'User already has an active account. No invitation needed.',
                'skip_invitation' => true,
                'is_existing_landlord' => true
            ];
        }
        
        if (!$this->newLandlord) {
            return ['success' => false, 'message' => 'New landlord not found'];
        }

        try {
            if (app()->has(OwnershipTransferService::class)) {
                $invitationResult = app(OwnershipTransferService::class)
                    ->sendInvitationToNewOwner($this);
            } else {
                $invitationResult = $this->sendManualInvitation();
            }

            Log::info('Invitation sent for transfer', [
                'transfer_id' => $this->id,
                'new_landlord_id' => $this->new_landlord_id,
                'is_existing_landlord' => $isExistingLandlord,
                'result' => $invitationResult
            ]);

            return $invitationResult;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation', [
                'transfer_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendManualInvitation(): array
    {
        $isExistingLandlord = $this->metadata['is_existing_landlord'] ?? false;
        
        if ($isExistingLandlord) {
            return [
                'success' => true,
                'message' => 'User already has an active account. No invitation needed.',
                'skip_invitation' => true,
                'is_existing_landlord' => true,
                'channels_attempted' => []
            ];
        }
        
        $channels = [];
        $results = [];
        
        if ($this->newLandlord && $this->newLandlord->status === User::STATUS_ACTIVE) {
            return [
                'success' => true,
                'message' => 'User already has an active account. No invitation needed.',
                'skip_invitation' => true,
                'is_existing_landlord' => true,
                'channels_attempted' => []
            ];
        }
        
        $email = $this->newLandlord->email ?? $this->new_owner_email;
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'email';
            $results['email'] = ['success' => true, 'message' => 'Email invitation queued'];
        }
        
        $phone = $this->newLandlord->phone ?? $this->new_owner_phone;
        if ($phone) {
            $channels[] = 'sms';
            $results['sms'] = ['success' => true, 'message' => 'SMS invitation queued'];
        }
        
        return [
            'success' => count($channels) > 0,
            'message' => count($channels) > 0 ? 'Invitation sent to new owner' : 'No communication channels available',
            'channels_attempted' => $channels,
            'results' => $results,
            'is_existing_landlord' => false
        ];
    }

    public function getIsForExistingLandlordAttribute(): bool
    {
        return $this->metadata['is_existing_landlord'] ?? false;
    }

    public function getNewOwnerDisplayNameAttribute(): string
    {
        if ($this->newLandlord) {
            return $this->newLandlord->name;
        }
        
        return $this->new_owner_name ?? 'Unknown';
    }

    public function getNewOwnerDisplayEmailAttribute(): string
    {
        if ($this->newLandlord && $this->newLandlord->email) {
            return $this->newLandlord->email;
        }
        
        if ($this->new_owner_email && !str_contains($this->new_owner_email, 'temp.hilltop.com')) {
            return $this->new_owner_email;
        }
        
        return 'Not provided';
    }

    public function getNewOwnerDisplayPhoneAttribute(): string
    {
        if ($this->newLandlord && $this->newLandlord->phone) {
            return $this->newLandlord->phone;
        }
        
        return $this->new_owner_phone ?? 'Not provided';
    }

    public function generateCertificate(): ?string
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        try {
            if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                Log::warning('PDF class not found, cannot generate certificate');
                return $this->generateSimpleCertificate();
            }
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::class;
            
            $unitsTransferred = $this->metadata['completion_details']['units_transferred'] ?? 0;
            $tenantsTransferred = $this->metadata['completion_details']['units_with_tenants'] ?? 0;
            
            $html = view('certificates.ownership-transfer', [
                'transfer' => $this,
                'property' => $this->property,
                'current_owner' => $this->currentLandlord,
                'new_owner' => $this->newLandlord,
                'approved_by' => $this->approvedBy,
                'certificate_number' => 'OTC-' . strtoupper(uniqid()),
                'issue_date' => now()->format('F j, Y'),
                'units_transferred' => $unitsTransferred,
                'tenants_transferred' => $tenantsTransferred
            ])->render();

            $filename = "certificate_{$this->id}.pdf";
            $path = "ownership-transfers/certificates/{$filename}";
            
            $directory = storage_path('app/public/ownership-transfers/certificates');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            Storage::put("public/{$path}", $pdf::loadHTML($html)->output());
            
            if (class_exists(\App\Models\TransferCertificate::class)) {
                \App\Models\TransferCertificate::updateOrCreate(
                    ['transfer_id' => $this->id],
                    [
                        'certificate_number' => 'OTC-' . strtoupper(uniqid()),
                        'certificate_url' => $path,
                        'issued_at' => now(),
                        'issued_by_id' => auth()->id()
                    ]
                );
            }

            $metadata = $this->metadata ?? [];
            $metadata['certificate'] = [
                'generated_at' => now()->toISOString(),
                'path' => $path,
                'filename' => $filename
            ];
            $this->metadata = $metadata;
            $this->save();

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to generate certificate', [
                'transfer_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return $this->generateSimpleCertificate();
        }
    }

    private function generateSimpleCertificate(): ?string
    {
        $content = "PROPERTY OWNERSHIP TRANSFER CERTIFICATE\n";
        $content .= "=====================================\n\n";
        $content .= "Certificate Number: OTC-" . strtoupper(uniqid()) . "\n";
        $content .= "Issue Date: " . now()->format('F j, Y') . "\n\n";
        $content .= "Property: " . ($this->property->property_name ?? 'N/A') . "\n";
        $content .= "Transfer Reference: " . $this->document_reference . "\n\n";
        $content .= "Previous Owner: " . ($this->currentLandlord->name ?? 'N/A') . "\n";
        $content .= "New Owner: " . ($this->newLandlord->name ?? $this->new_owner_name) . "\n";
        $content .= "Transfer Date: " . ($this->transfer_date ? $this->transfer_date->format('F j, Y') : 'N/A') . "\n";
        $content .= "Sale Amount: " . $this->formatted_sale_amount . "\n\n";
        $content .= "This certifies that the property ownership has been successfully transferred.\n";
        $content .= "Generated on: " . now()->toDateTimeString();

        $filename = "certificate_{$this->id}.txt";
        $path = "ownership-transfers/certificates/{$filename}";
        
        Storage::put("public/{$path}", $content);
        
        return $path;
    }

    public function verifyDigitalSignature(array $signatureData): array
    {
        if (!app()->has(DigitalSignatureService::class)) {
            return ['success' => false, 'message' => 'Digital signature service not available', 'valid' => false];
        }

        $service = app(DigitalSignatureService::class);
        $result = $service->verifySignature($signatureData);

        if ($result['valid'] ?? false) {
            $this->digital_signature_verified = true;
            $metadata = $this->metadata ?? [];
            $metadata['digital_signature_verification'] = [
                'verified_at' => now()->toISOString(),
                'verified_by' => auth()->id(),
                'verified_by_name' => auth()->user()->name ?? 'System',
                'verification_data' => $result
            ];
            $this->metadata = $metadata;
            $this->save();
        }

        return $result;
    }

    public function getOwnershipHistoryAttribute(): array
    {
        $history = [];
        
        $history[] = [
            'transfer_id' => $this->id,
            'from_landlord_id' => $this->current_landlord_id,
            'from_landlord_name' => $this->currentLandlord->name ?? 'Unknown',
            'to_landlord_id' => $this->new_landlord_id,
            'to_landlord_name' => $this->newLandlord->name ?? $this->new_owner_name,
            'transfer_date' => $this->transfer_date ? $this->transfer_date->toISOString() : null,
            'document_reference' => $this->document_reference,
            'sale_amount' => $this->sale_amount,
            'status' => $this->status
        ];
        
        if ($this->property) {
            $previousTransfers = self::where('property_id', $this->property_id)
                ->where('id', '!=', $this->id)
                ->where('status', self::STATUS_COMPLETED)
                ->orderBy('completed_at', 'desc')
                ->limit(10)
                ->get();
                
            foreach ($previousTransfers as $transfer) {
                $history[] = [
                    'transfer_id' => $transfer->id,
                    'from_landlord_id' => $transfer->current_landlord_id,
                    'from_landlord_name' => $transfer->currentLandlord->name ?? 'Unknown',
                    'to_landlord_id' => $transfer->new_landlord_id,
                    'to_landlord_name' => $transfer->newLandlord->name ?? $transfer->new_owner_name,
                    'transfer_date' => $transfer->transfer_date ? $transfer->transfer_date->toISOString() : null,
                    'document_reference' => $transfer->document_reference,
                    'sale_amount' => $transfer->sale_amount,
                    'status' => $transfer->status
                ];
            }
        }
        
        return $history;
    }

    // =============================================
    // APPENDED ATTRIBUTES
    // =============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_COMPLETED => 'Completed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            self::DOCUMENT_SALE_DEED => 'Sale Deed',
            self::DOCUMENT_TITLE_DEED => 'Title Deed',
            self::DOCUMENT_CONVEYANCE => 'Conveyance',
            self::DOCUMENT_TRANSFER_CERTIFICATE => 'Transfer Certificate',
            self::DOCUMENT_GIFT_DEED => 'Gift Deed',
            self::DOCUMENT_WILL_PROBATE => 'Will/Probate',
            self::DOCUMENT_OTHER => 'Other Document',
        ];

        return $labels[$this->document_type] ?? ucfirst($this->document_type);
    }

    public function getIsReversalRecordAttribute(): bool
    {
        return $this->isReversalRecord();
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getTransferDocumentUrlAttribute(): ?string
    {
        if (!$this->document_url) {
            return null;
        }

        return Storage::url($this->document_url);
    }

    public function getCertificateUrlAttribute(): ?string
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        $certificatePath = "ownership-transfers/certificates/certificate_{$this->id}.pdf";
        
        if (Storage::exists("public/{$certificatePath}")) {
            return Storage::url($certificatePath);
        }
        
        $txtPath = "ownership-transfers/certificates/certificate_{$this->id}.txt";
        if (Storage::exists("public/{$txtPath}")) {
            return Storage::url($txtPath);
        }

        return null;
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->canBeApproved();
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return $this->canBeRejected();
    }

    public function getCanBeCompletedAttribute(): bool
    {
        return $this->canBeCompleted();
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function getCanBeRestoredAttribute(): bool
    {
        return $this->canBeRestored();
    }

    public function getCanBePermanentlyDeletedAttribute(): bool
    {
        return $this->canBePermanentlyDeleted();
    }

    public function getRequiresDigitalSignatureAttribute(): bool
    {
        return config('ownership_transfer.require_digital_signature', false) && 
               $this->sale_amount >= config('ownership_transfer.digital_signature_threshold', 1000000);
    }

    public function getIsBulkTransferAttribute(): bool
    {
        return !empty($this->bulk_group_id);
    }

    public function getProcessingTimeDaysAttribute(): ?int
    {
        if (!$this->created_at || !$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInDays($this->completed_at);
    }

    public function getFormattedSaleAmountAttribute(): string
    {
        if (!$this->sale_amount) {
            return 'N/A';
        }

        return number_format($this->sale_amount, 2);
    }

    public function getCanResubmitAttribute()
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        if ($user->id !== $this->current_landlord_id) {
            return false;
        }
        
        if ($this->status !== self::STATUS_REJECTED) {
            return false;
        }
        
        if ($this->can_resubmit_after && $this->can_resubmit_after->isFuture()) {
            return false;
        }
        
        if (isset($this->metadata['resubmitted_to'])) {
            return false;
        }
        
        return true;
    }

    public function getNextWorkflowStepAttribute(): ?string
    {
        $workflow = config('ownership_transfer.workflow', [
            self::WORKFLOW_APPROVAL,
            self::WORKFLOW_VERIFICATION,
            self::WORKFLOW_COMPLETION
        ]);

        $currentIndex = array_search($this->approval_workflow_step, $workflow);
        
        if ($currentIndex === false || $currentIndex + 1 >= count($workflow)) {
            return null;
        }

        return $workflow[$currentIndex + 1];
    }

    public function getTransferHistoryAttribute(): array
    {
        $history = [];
        
        if ($this->created_at) {
            $history[] = [
                'date' => $this->created_at->toISOString(),
                'formatted_date' => $this->created_at->format('M j, Y H:i'),
                'action' => 'Transfer Request Created',
                'by' => $this->requestedBy->name ?? 'System',
                'by_id' => $this->requested_by_id,
                'status' => 'created'
            ];
        }
        
        if ($this->approved_at) {
            $history[] = [
                'date' => $this->approved_at->toISOString(),
                'formatted_date' => $this->approved_at->format('M j, Y H:i'),
                'action' => 'Transfer Approved',
                'by' => $this->approvedBy->name ?? 'Admin',
                'by_id' => $this->admin_approved_by_id,
                'status' => 'approved'
            ];
        }
        
        if ($this->completed_at) {
            $history[] = [
                'date' => $this->completed_at->toISOString(),
                'formatted_date' => $this->completed_at->format('M j, Y H:i'),
                'action' => 'Transfer Completed',
                'by' => $this->completedBy->name ?? 'Admin',
                'by_id' => $this->completed_by_id,
                'status' => 'completed'
            ];
        }
        
        if ($this->rejected_at) {
            $history[] = [
                'date' => $this->rejected_at->toISOString(),
                'formatted_date' => $this->rejected_at->format('M j, Y H:i'),
                'action' => 'Transfer Rejected',
                'by' => $this->rejectedBy->name ?? 'Admin',
                'by_id' => $this->rejected_by_id,
                'status' => 'rejected',
                'reason' => $this->rejection_reason
            ];
        }
        
        if ($this->cancelled_at) {
            $history[] = [
                'date' => $this->cancelled_at->toISOString(),
                'formatted_date' => $this->cancelled_at->format('M j, Y H:i'),
                'action' => 'Transfer Cancelled',
                'by' => $this->currentLandlord->name ?? 'Landlord',
                'by_id' => $this->current_landlord_id,
                'status' => 'cancelled'
            ];
        }

        if ($this->reversal_status !== self::REVERSAL_STATUS_NONE) {
            if ($this->reversal_requested_at) {
                $history[] = [
                    'date' => $this->reversal_requested_at->toISOString(),
                    'formatted_date' => $this->reversal_requested_at->format('M j, Y H:i'),
                    'action' => 'Reversal Requested',
                    'by' => $this->reversalRequestedBy->name ?? 'Landlord',
                    'by_id' => $this->reversal_requested_by,
                    'status' => 'reversal_requested',
                    'reason' => $this->reversal_reason
                ];
            }
            
            if ($this->reversal_processed_at && $this->reversal_status === self::REVERSAL_STATUS_APPROVED) {
                $history[] = [
                    'date' => $this->reversal_processed_at->toISOString(),
                    'formatted_date' => $this->reversal_processed_at->format('M j, Y H:i'),
                    'action' => 'Reversal Approved',
                    'by' => $this->reversalProcessedBy->name ?? 'Admin',
                    'by_id' => $this->reversal_processed_by,
                    'status' => 'reversal_approved'
                ];
            }
            
            if ($this->reversal_processed_at && $this->reversal_status === self::REVERSAL_STATUS_REJECTED) {
                $history[] = [
                    'date' => $this->reversal_processed_at->toISOString(),
                    'formatted_date' => $this->reversal_processed_at->format('M j, Y H:i'),
                    'action' => 'Reversal Rejected',
                    'by' => $this->reversalProcessedBy->name ?? 'Admin',
                    'by_id' => $this->reversal_processed_by,
                    'status' => 'reversal_rejected',
                    'reason' => $this->reversal_admin_notes
                ];
            }
            
            if ($this->is_reversed && $this->reversal_status === self::REVERSAL_STATUS_COMPLETED) {
                $history[] = [
                    'date' => $this->reversal_processed_at ? $this->reversal_processed_at->toISOString() : now()->toISOString(),
                    'formatted_date' => $this->reversal_processed_at ? $this->reversal_processed_at->format('M j, Y H:i') : now()->format('M j, Y H:i'),
                    'action' => 'Reversal Executed',
                    'by' => $this->reversalProcessedBy->name ?? 'Admin',
                    'by_id' => $this->reversal_processed_by,
                    'status' => 'reversal_executed'
                ];
            }
        }

        usort($history, function($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return $history;
    }

    public function getRejectionDetailsAttribute(): ?array
    {
        if ($this->status !== self::STATUS_REJECTED) {
            return null;
        }
        
        return [
            'reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at ? $this->rejected_at->toISOString() : null,
            'rejected_at_formatted' => $this->rejected_at ? $this->rejected_at->format('M j, Y H:i') : null,
            'rejected_by' => $this->rejectedBy->name ?? 'Admin',
            'can_resubmit' => $this->can_resubmit,
            'resubmit_after' => $this->can_resubmit_after ? $this->can_resubmit_after->format('M j, Y') : null,
            'admin_notes' => $this->admin_notes
        ];
    }

    // =============================================
    // STATIC METHODS
    // =============================================
    
    public static function getDocumentTypes(): array
    {
        return [
            self::DOCUMENT_SALE_DEED => 'Sale Deed',
            self::DOCUMENT_TITLE_DEED => 'Title Deed',
            self::DOCUMENT_CONVEYANCE => 'Conveyance',
            self::DOCUMENT_TRANSFER_CERTIFICATE => 'Transfer Certificate',
            self::DOCUMENT_GIFT_DEED => 'Gift Deed',
            self::DOCUMENT_WILL_PROBATE => 'Will/Probate',
            self::DOCUMENT_OTHER => 'Other Document',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }

    public static function getReversalStatuses(): array
    {
        return [
            self::REVERSAL_STATUS_NONE => 'No Reversal',
            self::REVERSAL_STATUS_PENDING => 'Pending Review',
            self::REVERSAL_STATUS_APPROVED => 'Approved',
            self::REVERSAL_STATUS_REJECTED => 'Rejected',
            self::REVERSAL_STATUS_COMPLETED => 'Completed',
            self::REVERSAL_STATUS_EXPIRED => 'Expired',
        ];
    }

    public static function getWorkflowSteps(): array
    {
        return [
            self::WORKFLOW_APPROVAL => 'Approval',
            self::WORKFLOW_VERIFICATION => 'Verification',
            self::WORKFLOW_COMPLETION => 'Completion',
        ];
    }

    public static function getStatistics(): array
    {
        $cacheKey = 'ownership_transfer_statistics';
        $ttl = now()->addMinutes(15);

        return cache()->remember($cacheKey, $ttl, function() {
            return [
                'total' => self::count(),
                'pending' => self::where('status', self::STATUS_PENDING)->count(),
                'approved' => self::where('status', self::STATUS_APPROVED)->count(),
                'completed' => self::where('status', self::STATUS_COMPLETED)->count(),
                'rejected' => self::where('status', self::STATUS_REJECTED)->count(),
                'cancelled' => self::where('status', self::STATUS_CANCELLED)->count(),
                'total_value' => self::where('status', self::STATUS_COMPLETED)->sum('sale_amount'),
                'avg_processing_time' => self::where('status', self::STATUS_COMPLETED)
                    ->whereNotNull('completed_at')
                    ->whereNotNull('created_at')
                    ->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(DAY, created_at, completed_at)')) ?? 0,
                'reversal_pending' => self::where('reversal_status', self::REVERSAL_STATUS_PENDING)->count(),
                'reversal_completed' => self::where('reversal_status', self::REVERSAL_STATUS_COMPLETED)->where('is_reversed', true)->count(),
                'reversal_rejected' => self::where('reversal_status', self::REVERSAL_STATUS_REJECTED)->count(),
                'reversal_records_total' => self::where('metadata->is_reversal', true)->count(),
                'monthly_trend' => self::select(
                        \Illuminate\Support\Facades\DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                        \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'),
                        \Illuminate\Support\Facades\DB::raw('SUM(sale_amount) as total_value')
                    )
                    ->whereYear('created_at', now()->year)
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
                    ->toArray()
            ];
        });
    }

    public static function cleanupExpiredTransfers(): int
    {
        $expiredDate = now()->subDays(config('ownership_transfer.expiry_days', 90));
        
        return self::where('created_at', '<', $expiredDate)
            ->whereIn('status', [self::STATUS_REJECTED, self::STATUS_CANCELLED])
            ->delete();
    }

    public static function getStaleTransfers(int $days = 7): \Illuminate\Support\Collection
    {
        return self::where('status', self::STATUS_PENDING)
            ->where('created_at', '<', now()->subDays($days))
            ->with(['property', 'currentLandlord'])
            ->get();
    }

    public static function getExpiredReversalRequests(): \Illuminate\Support\Collection
    {
        return self::where('reversal_status', self::REVERSAL_STATUS_PENDING)
            ->where('reversal_deadline', '<', now())
            ->where('status', self::STATUS_COMPLETED)
            ->where('is_reversed', false)
            ->with(['property', 'currentLandlord'])
            ->get();
    }

    // =============================================
    // EVENT HANDLERS
    // =============================================
    
    public function onApproved()
    {
        if (class_exists(\App\Events\OwnershipTransferApproved::class)) {
            event(new \App\Events\OwnershipTransferApproved($this));
        }
    }

    public function onCompleted()
    {
        if (class_exists(\App\Events\OwnershipTransferCompleted::class)) {
            event(new \App\Events\OwnershipTransferCompleted($this));
        }
    }

    public function onRejected()
    {
        if (class_exists(\App\Events\OwnershipTransferRejected::class)) {
            event(new \App\Events\OwnershipTransferRejected($this));
        }
    }

    public function onCancelled()
    {
        if (class_exists(\App\Events\OwnershipTransferCancelled::class)) {
            event(new \App\Events\OwnershipTransferCancelled($this));
        }
    }

    public function onReversalRequested()
    {
        if (class_exists(\App\Events\TransferReversalRequested::class)) {
            event(new \App\Events\TransferReversalRequested($this));
        }
    }

    public function onReversalCompleted()
    {
        if (class_exists(\App\Events\TransferReversalCompleted::class)) {
            event(new \App\Events\TransferReversalCompleted($this));
        }
    }

    // =============================================
    // EXPORT METHODS
    // =============================================
    
    public function toExportArray(): array
    {
        return [
            'Transfer ID' => $this->id,
            'Document Reference' => $this->document_reference,
            'Property Name' => $this->property->property_name ?? 'N/A',
            'Property Registration' => $this->property->registration_pattern ?? 'N/A',
            'Property Address' => $this->property ? "{$this->property->street_name}, {$this->property->zone}" : 'N/A',
            'Current Owner' => $this->currentLandlord->name ?? 'N/A',
            'Current Owner Email' => $this->currentLandlord->email ?? 'N/A',
            'Current Owner Phone' => $this->currentLandlord->phone ?? 'N/A',
            'New Owner' => $this->newLandlord->name ?? $this->new_owner_name,
            'New Owner Email' => $this->newLandlord->email ?? $this->new_owner_email,
            'New Owner Phone' => $this->newLandlord->phone ?? $this->new_owner_phone,
            'Transfer Date' => $this->transfer_date ? $this->transfer_date->format('Y-m-d') : 'N/A',
            'Sale Amount' => $this->formatted_sale_amount,
            'Document Type' => $this->document_type_label,
            'Status' => $this->status_label,
            'Reversal Status' => $this->reversal_status_label,
            'Is Reversed' => $this->is_reversed ? 'Yes' : 'No',
            'Reversal Reason' => $this->reversal_reason ?? 'N/A',
            'Requested Date' => $this->created_at->format('Y-m-d H:i:s'),
            'Requested By' => $this->requestedBy->name ?? 'N/A',
            'Approved Date' => optional($this->approved_at)->format('Y-m-d H:i:s'),
            'Approved By' => $this->approvedBy->name ?? 'N/A',
            'Completed Date' => optional($this->completed_at)->format('Y-m-d H:i:s'),
            'Completed By' => $this->completedBy->name ?? 'N/A',
            'Rejected Date' => optional($this->rejected_at)->format('Y-m-d H:i:s'),
            'Rejection Reason' => $this->rejection_reason ?? 'N/A',
            'Processing Time (Days)' => $this->processing_time_days ?? 'N/A',
            'Reason for Transfer' => $this->reason_for_transfer ?? 'N/A',
            'Admin Notes' => $this->admin_notes ?? 'N/A',
            'Digital Signature Verified' => $this->digital_signature_verified ? 'Yes' : 'No',
            'Bulk Transfer' => $this->is_bulk_transfer ? 'Yes' : 'No',
            'Bulk Group ID' => $this->bulk_group_id ?? 'N/A',
            'Workflow Step' => $this->approval_workflow_step,
            'Is Reversal Record' => $this->is_reversal_record ? 'Yes' : 'No',
            'Original Transfer ID' => $this->original_transfer_id ?? 'N/A',
            'Created At' => $this->created_at->format('Y-m-d H:i:s'),
            'Updated At' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    // =============================================
    // VALIDATION RULES
    // =============================================
    
    public static function validationRules(string $context = 'create'): array
    {
        $baseRules = [
            'new_owner_name' => 'required|string|max:255',
            'new_owner_phone' => 'required|string|max:20',
            'new_owner_email' => 'nullable|email|max:255',
            'new_owner_address' => 'nullable|string|max:500',
            'transfer_date' => 'required|date',
            'sale_amount' => 'nullable|numeric|min:0',
            'document_type' => 'required|in:' . implode(',', array_keys(self::getDocumentTypes())),
            'document_reference' => 'nullable|string|max:100',
            'transfer_document' => 'required_if:context,create|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'reason_for_transfer' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];

        $updateRules = [
            'status' => 'sometimes|in:' . implode(',', array_keys(self::getStatuses())),
            'admin_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:status,rejected|string|max:1000',
        ];

        $reversalRules = [
            'reversal_reason' => 'required|string|min:10|max:1000',
            'confirm_reversal' => 'required|accepted',
        ];

        $bulkRules = [
            'property_ids' => 'required|array|min:1',
            'property_ids.*' => 'exists:properties,id',
            'bulk_notes' => 'nullable|string|max:2000',
        ];

        switch ($context) {
            case 'update':
                return array_merge($baseRules, $updateRules);
            case 'bulk':
                return array_merge($baseRules, $bulkRules);
            case 'reversal':
                return $reversalRules;
            case 'create':
            default:
                return $baseRules;
        }
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    public static function getCurrentTransferForProperty(int $propertyId): ?self
    {
        return self::where('property_id', $propertyId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED])
            ->first();
    }

    public static function hasPendingTransfer(int $propertyId): bool
    {
        return self::where('property_id', $propertyId)
            ->where('status', self::STATUS_PENDING)
            ->exists();
    }

    public static function getTransfersForUser(int $userId, ?string $status = null): Builder
    {
        $query = self::where(function($q) use ($userId) {
            $q->where('current_landlord_id', $userId)
              ->orWhere('new_landlord_id', $userId);
        });
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->orderBy('created_at', 'desc');
    }

    public static function getLandlordSummary(int $landlordId): array
    {
        return [
            'sent' => self::where('current_landlord_id', $landlordId)->count(),
            'sent_pending' => self::where('current_landlord_id', $landlordId)
                ->where('status', self::STATUS_PENDING)->count(),
            'sent_approved' => self::where('current_landlord_id', $landlordId)
                ->where('status', self::STATUS_APPROVED)->count(),
            'sent_completed' => self::where('current_landlord_id', $landlordId)
                ->where('status', self::STATUS_COMPLETED)->count(),
            'sent_rejected' => self::where('current_landlord_id', $landlordId)
                ->where('status', self::STATUS_REJECTED)->count(),
            'received' => self::where('new_landlord_id', $landlordId)->count(),
            'received_pending' => self::where('new_landlord_id', $landlordId)
                ->where('status', self::STATUS_PENDING)->count(),
            'received_approved' => self::where('new_landlord_id', $landlordId)
                ->where('status', self::STATUS_APPROVED)->count(),
            'received_completed' => self::where('new_landlord_id', $landlordId)
                ->where('status', self::STATUS_COMPLETED)->count(),
            'total_value_received' => self::where('new_landlord_id', $landlordId)
                ->where('status', self::STATUS_COMPLETED)
                ->sum('sale_amount'),
            'total_value_sent' => self::where('current_landlord_id', $landlordId)
                ->where('status', self::STATUS_COMPLETED)
                ->sum('sale_amount'),
            'reversal_requests_sent' => self::where('current_landlord_id', $landlordId)
                ->where('reversal_status', self::REVERSAL_STATUS_PENDING)
                ->count(),
            'reversal_completed' => self::where('current_landlord_id', $landlordId)
                ->where('is_reversed', true)
                ->count(),
            'reversal_records_received' => self::where('new_landlord_id', $landlordId)
                ->where('metadata->is_reversal', true)
                ->count(),
        ];
    }

    public function getReadinessReport(): array
    {
        if (!$this->property) {
            return ['error' => 'Property not found'];
        }

        $units = PropertyUnit::where('property_id', $this->property_id)->get();
        $unitsWithTenants = $units->filter(function($unit) {
            return !is_null($unit->tenant_id);
        });
        
        return [
            'property_name' => $this->property->property_name,
            'property_id' => $this->property_id,
            'has_units' => $units->isNotEmpty(),
            'total_units' => $units->count(),
            'units_with_tenants' => $unitsWithTenants->count(),
            'tenants_will_transfer' => $unitsWithTenants->isNotEmpty(),
            'tenants_list' => $unitsWithTenants->map(function($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'tenant_id' => $unit->tenant_id,
                    'tenant_name' => $unit->tenant->name ?? 'Unknown',
                    'tenant_email' => $unit->tenant->email ?? 'N/A',
                    'tenant_phone' => $unit->tenant->phone ?? 'N/A',
                    'monthly_rent' => $unit->monthly_rent,
                ];
            })->values()->toArray(),
            'vacant_units' => $units->filter(function($unit) {
                return is_null($unit->tenant_id);
            })->map(function($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'unit_type' => $unit->unit_type,
                    'status' => $unit->status
                ];
            })->values()->toArray(),
            'warning' => $unitsWithTenants->isNotEmpty() 
                ? "Note: {$unitsWithTenants->count()} tenant(s) will be transferred to the new landlord."
                : "No tenants will be transferred.",
            'transfer_ready' => true,
            'requires_confirmation' => $unitsWithTenants->isNotEmpty(),
        ];
    }

    public function canBeResubmitted(): bool
    {
        if ($this->status !== self::STATUS_REJECTED) {
            return false;
        }
        
        if ($this->can_resubmit_after && $this->can_resubmit_after->isFuture()) {
            return false;
        }
        
        if (isset($this->metadata['resubmitted_to'])) {
            return false;
        }
        
        return true;
    }

    public function getResubmissionErrorMessage(): string
    {
        if ($this->status !== self::STATUS_REJECTED) {
            return 'Only rejected transfers can be resubmitted.';
        }
        
        if ($this->can_resubmit_after && $this->can_resubmit_after->isFuture()) {
            return 'Resubmission is not allowed until ' . $this->can_resubmit_after->format('M j, Y');
        }
        
        if (isset($this->metadata['resubmitted_to'])) {
            return 'This transfer has already been resubmitted.';
        }
        
        return 'Unknown reason.';
    }
}