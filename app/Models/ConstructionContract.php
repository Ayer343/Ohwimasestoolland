<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ConstructionContract extends Model
{
    use SoftDeletes;

    // ==================== CONSTANTS ====================
    
    // Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_UNDER_REVIEW = 'under_review';

    // Contractor Type Constants
    const CONTRACTOR_TYPE_COMPANY = 'company';
    const CONTRACTOR_TYPE_INDIVIDUAL = 'individual';

    // Contractor Status Constants
    const CONTRACTOR_STATUS_PENDING = 'pending';
    const CONTRACTOR_STATUS_INVITED = 'invited';
    const CONTRACTOR_STATUS_VIEWED = 'viewed';
    const CONTRACTOR_STATUS_PASSWORD_SET = 'password_set';
    const CONTRACTOR_STATUS_ACTIVE = 'active';
    const CONTRACTOR_STATUS_INACTIVE = 'inactive';
    const CONTRACTOR_STATUS_SUSPENDED = 'suspended';
    const CONTRACTOR_STATUS_BLOCKED = 'blocked';

    // Invitation Types
    const INVITATION_TYPE_WELCOME = 'welcome';
    const INVITATION_TYPE_REGISTRATION = 'registration';
    const INVITATION_TYPE_ACCOUNT_SETUP = 'account_setup';
    const INVITATION_TYPE_PASSWORD_SETUP = 'password_setup';
    const INVITATION_TYPE_CONTRACTOR_WELCOME = 'contractor_welcome';

    // ==================== FILLABLE ATTRIBUTES ====================
    
    protected $fillable = [
        'contract_number', 'title', 'description',
        'landlord_id', 'property_id',
        'contractor_type', 'contractor_name', 'contractor_phone',
        'contractor_email', 'contractor_address',
        'company_registration_number', 'company_tin',
        'contract_amount', 'contract_start_date', 'contract_end_date',
        'estimated_completion_date',
        'contract_documents',
        'work_scope', 'materials_required',
        'status',
        'approved_by', 'approved_at', 'admin_notes', 'rejection_reason',
        'admin_notified', 'admin_notified_at',
        // Contractor Invitation Fields
        'contractor_user_id',
        'contractor_invitation_sent_at',
        'contractor_invitation_accepted_at',
        'contractor_invitation_expires_at',
        'contractor_invitation_channels',
        'contractor_invitation_type',
        'contractor_invitation_token',
        'contractor_invitation_metadata',
        'contractor_invitation_attempts',
        'contractor_invitation_sent',
        'contractor_invitation_accepted',
        'contractor_invitation_expired',
        'contractor_password_set_at',
        'contractor_has_set_password',
        'contractor_last_activity_at',
        'contractor_status',
        'contractor_username',
        'contractor_registered_at',
        'primary_worker_id',
        'assigned_worker_ids',
        'contractor_emergency_phone',
        'contractor_emergency_contact',
        'contractor_preferences',
        'progress_percentage',
        'actual_completion_date',
        // Site/location fields
        'location',
        'site_id',
    ];

    // ==================== CASTS ====================
    
    protected $casts = [
        'contract_documents' => 'array',
        'work_scope' => 'array',
        'materials_required' => 'array',
        'contract_amount' => 'decimal:2',
        'progress_percentage' => 'integer',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'estimated_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'approved_at' => 'datetime',
        'admin_notified_at' => 'datetime',
        'completed_at' => 'datetime',
        // Contractor Invitation Casts
        'contractor_invitation_sent_at' => 'datetime',
        'contractor_invitation_accepted_at' => 'datetime',
        'contractor_invitation_expires_at' => 'datetime',
        'contractor_invitation_channels' => 'array',
        'contractor_invitation_metadata' => 'array',
        'contractor_invitation_sent' => 'boolean',
        'contractor_invitation_accepted' => 'boolean',
        'contractor_invitation_expired' => 'boolean',
        'contractor_password_set_at' => 'datetime',
        'contractor_has_set_password' => 'boolean',
        'contractor_last_activity_at' => 'datetime',
        'contractor_registered_at' => 'datetime',
        'assigned_worker_ids' => 'array',
        'contractor_preferences' => 'array',
    ];

    // ==================== DEFAULT ATTRIBUTES ====================
    
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'contractor_type' => self::CONTRACTOR_TYPE_COMPANY,
        'contractor_status' => self::CONTRACTOR_STATUS_PENDING,
        'contractor_invitation_sent' => false,
        'contractor_invitation_accepted' => false,
        'contractor_invitation_expired' => false,
        'contractor_has_set_password' => false,
        'contractor_invitation_attempts' => 0,
        'admin_notified' => false,
        'progress_percentage' => 0,
    ];

    // ==================== APPENDED ATTRIBUTES ====================
    
    protected $appends = [
        'status_label',
        'status_badge_class',
        'contractor_type_label',
        'is_overdue',
        'worker_count',
        'active_worker_count',
        'invitation_status_label',
        'invitation_status_badge_class',
        'is_contractor_invited',
        'is_contractor_active',
        'contractor_account_status',
        'days_until_invitation_expiry',
        'has_contractor_user',
        'contractor_display_name',
        'progress_formatted',
        'progress_color',
        'progress_bar_width',
        'invitation_accepted_status',
        'invitation_display_status',
    ];

    // ==================== BOOT METHOD ====================
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (empty($contract->contract_number)) {
                $contract->contract_number = $contract->generateContractNumber();
            }
            
            if (empty($contract->contractor_status)) {
                $contract->contractor_status = self::CONTRACTOR_STATUS_PENDING;
            }

            if (!isset($contract->progress_percentage)) {
                $contract->progress_percentage = 0;
            }
        });

        static::updating(function ($contract) {
            // Handle invitation acceptance
            if ($contract->isDirty('contractor_invitation_accepted_at') && 
                !is_null($contract->contractor_invitation_accepted_at)) {
                $contract->contractor_invitation_accepted = true;
                $contract->contractor_status = self::CONTRACTOR_STATUS_ACTIVE;
            }

            // Handle password setup
            if ($contract->isDirty('contractor_password_set_at') && 
                !is_null($contract->contractor_password_set_at)) {
                $contract->contractor_has_set_password = true;
                if ($contract->contractor_status === self::CONTRACTOR_STATUS_INVITED) {
                    $contract->contractor_status = self::CONTRACTOR_STATUS_PASSWORD_SET;
                }
            }

            // Handle invitation expiry
            if ($contract->contractor_invitation_expires_at && 
                $contract->contractor_invitation_expires_at->isPast() &&
                !$contract->contractor_invitation_accepted) {
                $contract->contractor_invitation_expired = true;
                $contract->contractor_status = self::CONTRACTOR_STATUS_PENDING;
            }

            // Ensure progress_percentage stays within bounds
            if ($contract->isDirty('progress_percentage')) {
                $value = (int) $contract->progress_percentage;
                $contract->progress_percentage = max(0, min(100, $value));
            }
        });
    }

    // ==================== RELATIONSHIPS ====================
    
    /**
     * Get the landlord (user) for this contract.
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the property for this contract.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * ✅ Get the site associated with this contract (if you have a sites table).
     * If you don't have a sites table, you can remove this or use a location field.
     */
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * ✅ Get the contractor (user) for this contract.
     * IMPORTANT: This is the main relationship for the contractor user.
     */
    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_user_id');
    }

    /**
     * ✅ Alias for contractor() for clarity in admin views.
     */
    public function contractorUser()
    {
        return $this->belongsTo(User::class, 'contractor_user_id');
    }

    /**
     * Get the approved by user.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the primary worker for this contract.
     */
    public function primaryWorker()
    {
        return $this->belongsTo(User::class, 'primary_worker_id');
    }

    /**
     * Get the progress updates for this contract.
     */
    public function progressUpdates()
    {
        return $this->hasMany(ConstructionProgressUpdate::class, 'contract_id');
    }

    /**
     * ✅ Get the workers for this contract.
     * This is the main relationship needed for admin worker viewing.
     */
    public function workers()
    {
        return $this->hasMany(ConstructionWorker::class, 'contract_id');
    }

    /**
     * Get the milestones for this contract.
     */
    public function milestones()
    {
        return $this->hasMany(ConstructionMilestone::class, 'contract_id');
    }

    /**
     * Get the activity logs for this contract.
     */
    public function activityLogs()
    {
        return $this->hasMany(ConstructionActivityLog::class, 'contract_id');
    }

    /**
     * Get the notifications for this contract.
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get the contractor invitations for this contract.
     */
    public function contractorInvitations()
    {
        return $this->hasMany(ContractorInvitation::class, 'contract_id');
    }

    /**
     * Get the latest contractor invitation.
     */
    public function latestContractorInvitation()
    {
        return $this->hasOne(ContractorInvitation::class, 'contract_id')->latest();
    }

    /**
     * Get the payments for this contract.
     */
    public function payments()
    {
        return $this->hasMany(ConstructionPayment::class, 'contract_id');
    }

    /**
     * Get the documents for this contract.
     */
    public function documents()
    {
        return $this->hasMany(ConstructionDocument::class, 'contract_id');
    }

    /**
     * Get the site visits for this contract.
     */
    public function siteVisits()
    {
        return $this->hasMany(ConstructionSiteVisit::class, 'contract_id');
    }

    // ==================== SCOPES ====================
    
    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeByLandlord($query, $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_APPROVED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_UNDER_REVIEW
        ]);
    }

    public function scopeWithInvitedContractors($query)
    {
        return $query->where('contractor_invitation_sent', true);
    }

    public function scopeWithActiveContractors($query)
    {
        return $query->where('contractor_status', self::CONTRACTOR_STATUS_ACTIVE);
    }

    public function scopeContractorPasswordNotSet($query)
    {
        return $query->where('contractor_has_set_password', false)
                    ->whereNotNull('contractor_user_id');
    }

    public function scopeOverdue($query)
    {
        return $query->where('estimated_completion_date', '<', now())
                    ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeHighProgress($query, $percentage = 75)
    {
        return $query->where('progress_percentage', '>=', $percentage);
    }

    public function scopeLowProgress($query, $percentage = 25)
    {
        return $query->where('progress_percentage', '<=', $percentage);
    }

    public function scopeInvitationAccepted($query)
    {
        return $query->where('contractor_invitation_accepted', true)
                    ->whereNotNull('contractor_invitation_accepted_at');
    }

    public function scopeInvitationPending($query)
    {
        return $query->where('contractor_invitation_sent', true)
                    ->where('contractor_invitation_accepted', false)
                    ->where('contractor_invitation_expired', false);
    }

    public function scopeInvitationExpired($query)
    {
        return $query->where('contractor_invitation_expired', true)
                    ->orWhere(function($q) {
                        $q->where('contractor_invitation_sent', true)
                          ->where('contractor_invitation_accepted', false)
                          ->where('contractor_invitation_expires_at', '<', now());
                    });
    }

    // ==================== ACCESSORS ====================
    
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'badge-secondary',
            self::STATUS_PENDING_APPROVAL => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_IN_PROGRESS => 'badge-info',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_CANCELLED => 'badge-danger',
            self::STATUS_ON_HOLD => 'badge-warning',
            self::STATUS_UNDER_REVIEW => 'badge-primary',
            default => 'badge-secondary',
        };
    }

    public function getContractorTypeLabelAttribute(): string
    {
        return match($this->contractor_type) {
            self::CONTRACTOR_TYPE_COMPANY => 'Company',
            self::CONTRACTOR_TYPE_INDIVIDUAL => 'Individual Contractor',
            default => ucfirst($this->contractor_type),
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->estimated_completion_date) {
            return false;
        }
        return $this->estimated_completion_date->isPast() && 
               !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function getProgressFormattedAttribute(): string
    {
        return $this->progress_percentage . '%';
    }

    public function getProgressColorAttribute(): string
    {
        $progress = $this->progress_percentage ?? 0;
        if ($progress >= 75) return 'success';
        if ($progress >= 50) return 'primary';
        if ($progress >= 25) return 'warning';
        return 'danger';
    }

    public function getProgressBarWidthAttribute(): int
    {
        return min(100, max(0, (int) $this->progress_percentage));
    }

    public function getWorkerCountAttribute(): int
    {
        return $this->workers()->count();
    }

    public function getActiveWorkerCountAttribute(): int
    {
        return $this->workers()->where('status', 'active')->count();
    }

    // ==================== CONTRACTOR INVITATION ACCESSORS ====================
    
    public function getInvitationStatusLabelAttribute(): string
    {
        if ($this->contractor_invitation_accepted) {
            return 'Accepted';
        }
        
        if ($this->contractor_invitation_expired) {
            return 'Expired';
        }
        
        if ($this->contractor_invitation_sent) {
            return 'Sent';
        }
        
        return 'Not Sent';
    }

    public function getInvitationStatusBadgeClassAttribute(): string
    {
        if ($this->contractor_invitation_accepted) {
            return 'badge-success';
        }
        
        if ($this->contractor_invitation_expired) {
            return 'badge-danger';
        }
        
        if ($this->contractor_invitation_sent) {
            return 'badge-info';
        }
        
        return 'badge-secondary';
    }

    public function getInvitationAcceptedStatusAttribute(): string
    {
        if ($this->contractor_invitation_accepted && $this->contractor_invitation_accepted_at) {
            return 'accepted';
        }
        
        if ($this->contractor_invitation_expired) {
            return 'expired';
        }
        
        if ($this->contractor_invitation_sent) {
            return 'pending';
        }
        
        return 'not_sent';
    }

    public function getInvitationDisplayStatusAttribute(): array
    {
        $status = $this->invitation_accepted_status;
        
        switch ($status) {
            case 'accepted':
                return [
                    'icon' => 'fas fa-handshake',
                    'color' => 'success',
                    'label' => 'Accepted',
                    'message' => 'Contractor has accepted the invitation',
                    'timestamp' => $this->contractor_invitation_accepted_at
                ];
            case 'expired':
                return [
                    'icon' => 'fas fa-exclamation-circle',
                    'color' => 'danger',
                    'label' => 'Expired',
                    'message' => 'Invitation has expired',
                    'timestamp' => $this->contractor_invitation_expires_at
                ];
            case 'pending':
                return [
                    'icon' => 'fas fa-clock',
                    'color' => 'warning',
                    'label' => 'Awaiting acceptance',
                    'message' => 'Waiting for contractor to accept',
                    'timestamp' => $this->contractor_invitation_sent_at
                ];
            default:
                return [
                    'icon' => 'fas fa-hourglass-half',
                    'color' => 'secondary',
                    'label' => 'Not sent',
                    'message' => 'Invitation not sent yet',
                    'timestamp' => null
                ];
        }
    }

    public function getIsContractorInvitedAttribute(): bool
    {
        return $this->contractor_invitation_sent && !$this->contractor_invitation_expired;
    }

    public function getIsContractorActiveAttribute(): bool
    {
        return $this->contractor_status === self::CONTRACTOR_STATUS_ACTIVE &&
               $this->contractor_has_set_password;
    }

    public function getContractorAccountStatusAttribute(): array
    {
        $statuses = [
            self::CONTRACTOR_STATUS_PENDING => ['label' => 'Pending', 'class' => 'badge-secondary'],
            self::CONTRACTOR_STATUS_INVITED => ['label' => 'Invited', 'class' => 'badge-info'],
            self::CONTRACTOR_STATUS_VIEWED => ['label' => 'Viewed', 'class' => 'badge-primary'],
            self::CONTRACTOR_STATUS_PASSWORD_SET => ['label' => 'Password Set', 'class' => 'badge-warning'],
            self::CONTRACTOR_STATUS_ACTIVE => ['label' => 'Active', 'class' => 'badge-success'],
            self::CONTRACTOR_STATUS_INACTIVE => ['label' => 'Inactive', 'class' => 'badge-secondary'],
            self::CONTRACTOR_STATUS_SUSPENDED => ['label' => 'Suspended', 'class' => 'badge-danger'],
            self::CONTRACTOR_STATUS_BLOCKED => ['label' => 'Blocked', 'class' => 'badge-dark'],
        ];

        return $statuses[$this->contractor_status] ?? ['label' => 'Unknown', 'class' => 'badge-secondary'];
    }

    public function getDaysUntilInvitationExpiryAttribute(): ?int
    {
        if (!$this->contractor_invitation_expires_at || $this->contractor_invitation_expired) {
            return null;
        }

        return now()->diffInDays($this->contractor_invitation_expires_at, false);
    }

    public function getHasContractorUserAttribute(): bool
    {
        return !is_null($this->contractor_user_id) && 
               $this->contractorUser()->exists();
    }

    public function getContractorDisplayNameAttribute(): string
    {
        if ($this->has_contractor_user && $this->contractorUser) {
            return $this->contractorUser->name;
        }
        return $this->contractor_name ?? 'Unknown Contractor';
    }

    // ==================== MUTATORS ====================
    
    public function setProgressPercentageAttribute($value)
    {
        $value = (int) $value;
        $value = max(0, min(100, $value));
        $this->attributes['progress_percentage'] = $value;
    }

    // ==================== HELPER METHODS ====================
    
    public function generateContractNumber(): string
    {
        $prefix = 'CON-';
        $year = date('Y');
        $month = date('m');
        $random = Str::upper(Str::random(6));
        
        $number = $prefix . $year . $month . '-' . $random;
        
        while (self::where('contract_number', $number)->exists()) {
            $random = Str::upper(Str::random(6));
            $number = $prefix . $year . $month . '-' . $random;
        }
        
        return $number;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function canInviteContractor(): bool
    {
        return $this->status === self::STATUS_APPROVED &&
               !$this->contractor_invitation_sent &&
               !$this->contractor_invitation_accepted &&
               ($this->contractor_email || $this->contractor_phone);
    }

    public function canResendInvitation(): bool
    {
        return $this->contractor_invitation_sent &&
               !$this->contractor_invitation_accepted &&
               ($this->contractor_email || $this->contractor_phone);
    }

    public function canContractorSetPassword(): bool
    {
        return $this->contractor_user_id &&
               !$this->contractor_has_set_password &&
               $this->contractor_invitation_sent &&
               !$this->contractor_invitation_expired;
    }

    public function isInvitationExpired(): bool
    {
        return $this->contractor_invitation_expired || 
               ($this->contractor_invitation_sent && 
                !$this->contractor_invitation_accepted && 
                $this->contractor_invitation_expires_at && 
                $this->contractor_invitation_expires_at->isPast());
    }

    public function isInvitationPending(): bool
    {
        return $this->contractor_invitation_sent && 
               !$this->contractor_invitation_accepted && 
               !$this->isInvitationExpired();
    }

    // ==================== ACTION METHODS ====================
    
    public function approve(int $adminId, ?string $notes = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $adminId;
        $this->approved_at = now();
        $this->admin_notes = $notes ?? $this->admin_notes;
        
        $this->logActivity('approved', "Contract approved by admin ID: {$adminId}");
        
        return $this->save();
    }

    public function reject(int $adminId, string $reason): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->rejection_reason = $reason;
        $this->admin_notes = $reason;
        
        $this->logActivity('rejected', "Contract rejected by admin ID: {$adminId}. Reason: {$reason}");
        
        return $this->save();
    }

    public function startWork(): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->logActivity('started', 'Work started on contract');
        
        return $this->save();
    }

    public function complete(?string $notes = null): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->actual_completion_date = now();
        
        if ($notes) {
            $this->admin_notes = ($this->admin_notes ? $this->admin_notes . "\n" : '') . "Completed: " . $notes;
        }
        $this->logActivity('completed', 'Contract completed');
        
        return $this->save();
    }

    public function pause(string $reason): bool
    {
        if (!in_array($this->status, [self::STATUS_IN_PROGRESS, self::STATUS_APPROVED])) {
            return false;
        }

        $this->status = self::STATUS_ON_HOLD;
        $this->admin_notes = ($this->admin_notes ? $this->admin_notes . "\n" : '') . "Paused: " . $reason;
        $this->logActivity('paused', "Contract paused. Reason: {$reason}");
        
        return $this->save();
    }

    public function resume(): bool
    {
        if ($this->status !== self::STATUS_ON_HOLD) {
            return false;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->admin_notes = ($this->admin_notes ? $this->admin_notes . "\n" : '') . "Resumed: " . now()->toDateTimeString();
        $this->logActivity('resumed', 'Contract resumed');
        
        return $this->save();
    }

    public function markInvitationSent(array $channels = ['email']): bool
    {
        $this->contractor_invitation_sent = true;
        $this->contractor_invitation_sent_at = now();
        $this->contractor_invitation_channels = $channels;
        $this->contractor_invitation_expires_at = now()->addDays(7);
        $this->contractor_status = self::CONTRACTOR_STATUS_INVITED;
        
        if (empty($this->contractor_invitation_token)) {
            $this->contractor_invitation_token = Str::random(64);
        }
        
        return $this->save();
    }

    public function markInvitationAccepted(?int $userId = null): bool
    {
        if ($this->contractor_invitation_accepted) {
            Log::info('Contract invitation already accepted', [
                'contract_id' => $this->id,
                'contract_number' => $this->contract_number,
                'accepted_at' => $this->contractor_invitation_accepted_at
            ]);
            return true;
        }

        $this->contractor_invitation_accepted = true;
        $this->contractor_invitation_accepted_at = now();
        $this->contractor_status = self::CONTRACTOR_STATUS_ACTIVE;
        $this->contractor_invitation_expired = false;
        
        if ($userId) {
            $this->contractor_user_id = $userId;
        }

        if ($this->status === self::STATUS_APPROVED) {
            $this->status = self::STATUS_IN_PROGRESS;
        }

        if ($this->status === self::STATUS_PENDING_APPROVAL) {
            $this->status = self::STATUS_APPROVED;
        }

        $saved = $this->save();
        
        if ($saved) {
            $this->logActivity('invitation_accepted', 'Contractor accepted invitation');
            
            Log::info('Contract invitation marked as accepted', [
                'contract_id' => $this->id,
                'contract_number' => $this->contract_number,
                'user_id' => $userId,
                'status' => $this->status,
                'accepted_at' => $this->contractor_invitation_accepted_at,
                'contractor_status' => $this->contractor_status
            ]);
        }

        return $saved;
    }

    public function syncFromUserInvitation(UserInvitation $invitation): bool
    {
        if ($invitation->status !== UserInvitation::STATUS_ACCEPTED || !$invitation->accepted_at) {
            Log::warning('Cannot sync from non-accepted invitation', [
                'contract_id' => $this->id,
                'invitation_id' => $invitation->id,
                'invitation_status' => $invitation->status
            ]);
            return false;
        }

        $this->contractor_invitation_accepted = true;
        $this->contractor_invitation_accepted_at = $invitation->accepted_at;
        $this->contractor_user_id = $invitation->user_id;
        $this->contractor_status = self::CONTRACTOR_STATUS_ACTIVE;
        $this->contractor_invitation_expired = false;
        
        if ($this->status === self::STATUS_APPROVED) {
            $this->status = self::STATUS_IN_PROGRESS;
        }

        $saved = $this->save();
        
        if ($saved) {
            Log::info('Contract synced from user invitation', [
                'contract_id' => $this->id,
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id
            ]);
        }

        return $saved;
    }

    public function markPasswordSet(): bool
    {
        $this->contractor_has_set_password = true;
        $this->contractor_password_set_at = now();
        
        if ($this->contractor_status === self::CONTRACTOR_STATUS_INVITED) {
            $this->contractor_status = self::CONTRACTOR_STATUS_PASSWORD_SET;
        }
        
        return $this->save();
    }

    public function updateContractorActivity(): bool
    {
        $this->contractor_last_activity_at = now();
        return $this->save();
    }

    public function logActivity(string $action, string $description): void
    {
        try {
            $this->activityLogs()->create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'status_before' => $this->getOriginal('status'),
                    'status_after' => $this->status,
                    'contractor_status_before' => $this->getOriginal('contractor_status'),
                    'contractor_status_after' => $this->contractor_status,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to log contract activity: ' . $e->getMessage(), [
                'contract_id' => $this->id,
                'action' => $action
            ]);
        }
    }

    public function getWorkersByTrade(?string $trade = null)
    {
        $query = $this->workers()->where('status', 'active');
        if ($trade) {
            $query->where('trade', $trade);
        }
        return $query->get();
    }

    public function getAssignedWorkerIdsAttribute(): array
    {
        $ids = $this->attributes['assigned_worker_ids'] ?? null;
        if (is_string($ids)) {
            return json_decode($ids, true) ?? [];
        }
        return $ids ?? [];
    }

    public function setAssignedWorkerIdsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['assigned_worker_ids'] = json_encode(array_unique($value));
        } else {
            $this->attributes['assigned_worker_ids'] = $value;
        }
    }

    public function getTotalContractValueAttribute(): float
    {
        $total = $this->contract_amount ?? 0;
        return $total;
    }

    public function getDurationInDaysAttribute(): ?int
    {
        if (!$this->contract_start_date) {
            return null;
        }
        
        $endDate = $this->contract_end_date ?? $this->estimated_completion_date ?? now();
        return $this->contract_start_date->diffInDays($endDate);
    }

    public function isWithinWarranty(): bool
    {
        if (!$this->completed_at) {
            return false;
        }
        
        $warrantyEnd = $this->completed_at->addYear();
        return now()->lessThanOrEqualTo($warrantyEnd);
    }

    public function updateProgress(int $newProgress, ?string $notes = null): bool
    {
        $oldProgress = $this->progress_percentage ?? 0;
        $this->progress_percentage = $newProgress;
        
        if ($this->save()) {
            $this->logActivity('progress_updated', "Progress updated from {$oldProgress}% to {$newProgress}%");
            return true;
        }
        
        return false;
    }

    public function calculateProgressFromMilestones(): int
    {
        $total = $this->milestones()->count();
        if ($total === 0) {
            return 0;
        }
        
        $completed = $this->milestones()->where('status', 'completed')->count();
        return (int) round(($completed / $total) * 100);
    }

    public function syncProgressWithMilestones(): bool
    {
        $calculated = $this->calculateProgressFromMilestones();
        $this->progress_percentage = $calculated;
        return $this->save();
    }

    public function getInvitationStatusForApi(): array
    {
        return [
            'sent' => $this->contractor_invitation_sent,
            'accepted' => $this->contractor_invitation_accepted,
            'expired' => $this->isInvitationExpired(),
            'pending' => $this->isInvitationPending(),
            'sent_at' => $this->contractor_invitation_sent_at?->toISOString(),
            'accepted_at' => $this->contractor_invitation_accepted_at?->toISOString(),
            'expires_at' => $this->contractor_invitation_expires_at?->toISOString(),
            'status' => $this->invitation_accepted_status,
            'display' => $this->invitation_display_status,
            'has_user' => $this->has_contractor_user,
            'user_id' => $this->contractor_user_id,
            'contractor_status' => $this->contractor_status,
        ];
    }
    
}