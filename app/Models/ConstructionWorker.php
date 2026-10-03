<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class ConstructionWorker extends Model
{
    use SoftDeletes;

    // Status Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_TERMINATED = 'terminated';

    // Payment Frequency Constants
    const PAYMENT_DAILY = 'daily';
    const PAYMENT_WEEKLY = 'weekly';
    const PAYMENT_BIWEEKLY = 'biweekly';
    const PAYMENT_MONTHLY = 'monthly';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_id',
        'full_name',
        'phone',
        'email',
        'address',
        'id_number',
        'job_title',
        'trade',
        'specialization',
        'skills',
        'service_description',
        'daily_rate',
        'contract_rate',
        'payment_frequency',
        'documents',
        'status',
        'start_date',
        'end_date',
        'notes',
        'added_by',
        // 🔧 FIX: Remove contractor_id from fillable if it doesn't exist in database
        // 'contractor_id', // <-- COMMENT OUT OR REMOVE THIS LINE
        // ✅ NEW: Site access tracking fields
        'last_check_in_at',
        'last_check_out_at',
        'is_on_site',
        'current_site_id',
        'is_assigned_to_site',
        'assigned_to_site_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'skills' => 'array',
        'documents' => 'array',
        'daily_rate' => 'decimal:2',
        'contract_rate' => 'decimal:2',
        'payment_frequency' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // ✅ NEW: Site access casts
        'last_check_in_at' => 'datetime',
        'last_check_out_at' => 'datetime',
        'is_on_site' => 'boolean',
        'current_site_id' => 'integer',
        'is_assigned_to_site' => 'boolean',
        'assigned_to_site_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status_label',
        'status_badge_class',
        'formatted_daily_rate',
        'formatted_contract_rate',
        'skills_list',
        'is_currently_working',
        'has_badge',
        'badge_status',
        'badge_number',
        'badge_is_active',
        // ✅ NEW: Site access appends
        'is_on_site_today',
        'today_check_in_time',
        'days_on_site',
        'total_days_worked',
        'worker_type',
        'full_display_name',
        'contract_number',
        'contractor_name',
        'site_name',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    /**
     * Get the contract that this worker belongs to.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    /**
     * Get the user who added this worker.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the contractor associated with this worker.
     * 🔧 FIX: Use contract relationship to get contractor
     */
    public function contractor(): BelongsTo
    {
        return $this->hasOneThrough(
            User::class,
            ConstructionContract::class,
            'id', // Foreign key on ConstructionContract
            'id', // Foreign key on User
            'contract_id', // Local key on ConstructionWorker
            'contractor_user_id' // Local key on ConstructionContract
        );
    }

    /**
     * Get the current site where the worker is located.
     */
    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    /**
     * Get the skills associated with this worker.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(WorkerSkill::class, 'worker_id');
    }

    /**
     * Get the badge associated with this worker.
     */
    public function badge(): HasOne
    {
        return $this->hasOne(WorkerBadge::class, 'construction_worker_id');
    }

    /**
     * Get the verification logs for this worker (through badge).
     */
    public function verificationLogs()
    {
        return $this->hasManyThrough(
            BadgeVerificationLog::class,
            WorkerBadge::class,
            'construction_worker_id',
            'worker_badge_id',
            'id',
            'id'
        );
    }

    /**
     * ✅ NEW: Get site access logs for this worker.
     */
    public function siteAccessLogs(): HasMany
    {
        return $this->hasMany(WorkerSiteAccessLog::class, 'construction_worker_id');
    }

    /**
     * ✅ NEW: Get today's site access logs.
     */
    public function todaySiteAccessLogs()
    {
        return $this->siteAccessLogs()
            ->whereDate('accessed_at', today());
    }

    /**
     * ✅ NEW: Get check-ins for this worker.
     */
    public function checkIns(): HasMany
    {
        return $this->siteAccessLogs()->where('action', 'check_in');
    }

    /**
     * ✅ NEW: Get check-outs for this worker.
     */
    public function checkOuts(): HasMany
    {
        return $this->siteAccessLogs()->where('action', 'check_out');
    }

    /**
     * ✅ NEW: Get work history across all contracts.
     */
    public function workHistory(): HasMany
    {
        return $this->hasMany(WorkerWorkHistory::class, 'worker_id');
    }

    /**
     * ✅ NEW: Get the contractor user ID through contract relationship.
     */
    public function getContractorIdAttribute()
    {
        return $this->contract?->contractor_user_id;
    }

    /**
     * ✅ NEW: Get the contractor name through contract relationship.
     */
    public function getContractorNameAttribute(): ?string
    {
        return $this->contract?->contractor?->name;
    }

    // Add method to toggle site assignment
    public function toggleSiteAssignment()
    {
        $this->is_assigned_to_site = !$this->is_assigned_to_site;
        if ($this->is_assigned_to_site) {
            $this->assigned_to_site_at = now();
        } else {
            $this->assigned_to_site_at = null;
        }
        return $this->save();
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /**
     * Scope a query to only include active workers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include inactive workers.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope a query to only include pending workers.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include workers by trade.
     */
    public function scopeByTrade($query, $trade)
    {
        return $query->where('trade', $trade);
    }

    /**
     * Scope a query to only include workers by job title.
     */
    public function scopeByJobTitle($query, $jobTitle)
    {
        return $query->where('job_title', $jobTitle);
    }

    /**
     * Scope a query to only include workers for a specific contract.
     */
    public function scopeForContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope a query to only include workers who are currently working.
     */
    public function scopeCurrentlyWorking($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope to only include workers with badges.
     */
    public function scopeHasBadge($query)
    {
        return $query->whereHas('badge');
    }

    /**
     * Scope to only include workers without badges.
     */
    public function scopeWithoutBadge($query)
    {
        return $query->whereDoesntHave('badge');
    }

    /**
     * Scope to only include workers with active badges.
     */
    public function scopeWithActiveBadge($query)
    {
        return $query->whereHas('badge', function ($q) {
            $q->where('is_active', true)
              ->where(function ($sub) {
                  $sub->whereNull('valid_until')
                      ->orWhere('valid_until', '>', now());
              });
        });
    }

    /**
     * ✅ NEW: Scope to only include workers currently on site.
     */
    public function scopeOnSite($query)
    {
        return $query->where('is_on_site', true);
    }

    /**
     * ✅ NEW: Scope to only include workers by site.
     */
    public function scopeAtSite($query, $siteId)
    {
        return $query->where('current_site_id', $siteId);
    }

    /**
     * ✅ NEW: Scope to only include workers with expiring badges.
     */
    public function scopeWithExpiringBadges($query, $days = 7)
    {
        return $query->whereHas('badge', function ($q) use ($days) {
            $q->where('is_active', true)
              ->whereNotNull('valid_until')
              ->where('valid_until', '<=', now()->addDays($days))
              ->where('valid_until', '>', now());
        });
    }

    /**
     * ✅ NEW: Scope to only include workers with expired badges.
     */
    public function scopeWithExpiredBadges($query)
    {
        return $query->whereHas('badge', function ($q) {
            $q->where(function ($sub) {
                $sub->where('is_active', false)
                    ->orWhere('valid_until', '<=', now());
            });
        });
    }

    /**
     * ✅ NEW: Scope to filter by contractor.
     */
    public function scopeForContractor($query, $contractorId)
    {
        return $query->whereHas('contract', function ($q) use ($contractorId) {
            $q->where('contractor_user_id', $contractorId);
        });
    }

    // ============================================================
    // ACCESSORS & MUTATORS
    // ============================================================

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        if (isset($this->attributes['full_name'])) {
            return $this->attributes['full_name'];
        }

        if (isset($this->attributes['name'])) {
            return $this->attributes['name'];
        }

        if (isset($this->attributes['first_name']) || isset($this->attributes['last_name'])) {
            $firstName = $this->attributes['first_name'] ?? '';
            $lastName = $this->attributes['last_name'] ?? '';
            return trim($firstName . ' ' . $lastName);
        }

        return 'Worker #' . ($this->attributes['id'] ?? 'Unknown');
    }

    /**
     * Set the full name attribute.
     */
    public function setFullNameAttribute($value): void
    {
        $this->attributes['full_name'] = trim($value);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_TERMINATED => 'Terminated',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'status-badge active',
            self::STATUS_INACTIVE => 'status-badge inactive',
            self::STATUS_PENDING => 'status-badge pending',
            self::STATUS_COMPLETED => 'status-badge completed',
            self::STATUS_TERMINATED => 'status-badge terminated',
            default => 'status-badge',
        };
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_COMPLETED => 'blue',
            self::STATUS_TERMINATED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted daily rate.
     */
    public function getFormattedDailyRateAttribute(): string
    {
        if (!$this->daily_rate) {
            return '₵0.00';
        }
        return '₵' . number_format($this->daily_rate, 2);
    }

    /**
     * Get formatted contract rate.
     */
    public function getFormattedContractRateAttribute(): string
    {
        if (!$this->contract_rate) {
            return '₵0.00';
        }
        return '₵' . number_format($this->contract_rate, 2);
    }

    /**
     * Get the skills list as a string.
     */
    public function getSkillsListAttribute(): string
    {
        if (!$this->skills) {
            return 'No skills listed';
        }

        if (is_array($this->skills)) {
            return implode(', ', $this->skills);
        }

        if (is_string($this->skills)) {
            $decoded = json_decode($this->skills, true);
            if (is_array($decoded)) {
                return implode(', ', $decoded);
            }
            return $this->skills;
        }

        return 'No skills listed';
    }

    /**
     * Get the skills as an array.
     */
    public function getSkillsArrayAttribute(): array
    {
        if (!$this->skills) {
            return [];
        }

        if (is_array($this->skills)) {
            return $this->skills;
        }

        if (is_string($this->skills)) {
            $decoded = json_decode($this->skills, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Check if worker is currently working.
     */
    public function getIsCurrentlyWorkingAttribute(): bool
    {
        return $this->isCurrentlyWorking();
    }

    /**
     * Get the payment frequency label.
     */
    public function getPaymentFrequencyLabelAttribute(): string
    {
        return match($this->payment_frequency) {
            self::PAYMENT_DAILY => 'Daily',
            self::PAYMENT_WEEKLY => 'Weekly',
            self::PAYMENT_BIWEEKLY => 'Bi-Weekly',
            self::PAYMENT_MONTHLY => 'Monthly',
            default => ucfirst($this->payment_frequency ?? 'Not set'),
        };
    }

    /**
     * Get the contract number.
     */
    public function getContractNumberAttribute(): ?string
    {
        return $this->contract?->contract_number;
    }

    /**
     * Get the site name.
     */
    public function getSiteNameAttribute(): ?string
    {
        return $this->contract?->site?->name;
    }

    /**
     * Get the worker's display name (for dropdowns, etc.)
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->full_name;
        if ($this->job_title) {
            $name .= ' (' . $this->job_title . ')';
        }
        return $name;
    }

    /**
     * Get full display name with trade.
     */
    public function getFullDisplayNameAttribute(): string
    {
        $parts = [$this->full_name];
        
        if ($this->trade) {
            $parts[] = ucfirst($this->trade);
        }
        
        if ($this->job_title) {
            $parts[] = $this->job_title;
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Get worker type (based on status).
     */
    public function getWorkerTypeAttribute(): string
    {
        if ($this->isCurrentlyWorking()) {
            return 'Active Worker';
        }
        
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_TERMINATED => 'Terminated',
            default => 'Inactive',
        };
    }

    // ============================================================
    // BADGE ACCESSORS
    // ============================================================

    /**
     * Check if worker has a badge.
     */
    public function getHasBadgeAttribute(): bool
    {
        return $this->badge()->exists();
    }

    /**
     * Get the badge status.
     */
    public function getBadgeStatusAttribute(): ?string
    {
        return $this->badge?->status;
    }

    /**
     * Get the badge number.
     */
    public function getBadgeNumberAttribute(): ?string
    {
        return $this->badge?->badge_number;
    }

    /**
     * Check if badge is active.
     */
    public function getBadgeIsActiveAttribute(): bool
    {
        return $this->badge?->is_active ?? false;
    }

    // ============================================================
    // SITE ACCESS ACCESSORS
    // ============================================================

    /**
     * Check if worker is on site today.
     */
    public function getIsOnSiteTodayAttribute(): bool
    {
        return $this->isOnSiteToday();
    }

    /**
     * Get today's check-in time.
     */
    public function getTodayCheckInTimeAttribute(): ?string
    {
        $checkIn = $this->todayCheckIn();
        return $checkIn?->accessed_at?->format('H:i');
    }

    /**
     * Get days on site for this worker.
     */
    public function getDaysOnSiteAttribute(): int
    {
        return $this->siteAccessLogs()
            ->where('action', 'check_in')
            ->distinct('accessed_at')
            ->count('accessed_at');
    }

    /**
     * Get total days worked across all contracts.
     */
    public function getTotalDaysWorkedAttribute(): int
    {
        return $this->workHistory()->sum('days_worked') ?: 0;
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    /**
     * Check if the worker can be activated.
     */
    public function canBeActive(): bool
    {
        return in_array($this->status, [self::STATUS_INACTIVE, self::STATUS_PENDING]);
    }

    /**
     * Check if the worker can be terminated.
     */
    public function canBeTerminated(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_PENDING]);
    }

    /**
     * Check if the worker can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the worker is currently working.
     */
    public function isCurrentlyWorking(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();
        
        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the worker has a skill.
     */
    public function hasSkill(string $skill): bool
    {
        $skills = $this->getSkillsArrayAttribute();
        return in_array(strtolower($skill), array_map('strtolower', $skills));
    }

    // ============================================================
    // SITE ACCESS METHODS
    // ============================================================

    /**
     * Check if worker is on site today.
     */
    public function isOnSiteToday(): bool
    {
        $today = today();
        $lastCheckIn = $this->siteAccessLogs()
            ->where('action', 'check_in')
            ->whereDate('accessed_at', $today)
            ->latest()
            ->first();
        
        if (!$lastCheckIn) {
            return false;
        }
        
        $lastCheckOut = $this->siteAccessLogs()
            ->where('action', 'check_out')
            ->whereDate('accessed_at', $today)
            ->latest()
            ->first();
        
        return !$lastCheckOut || $lastCheckOut->accessed_at < $lastCheckIn->accessed_at;
    }

    /**
     * Get today's check-in.
     */
    public function todayCheckIn()
    {
        return $this->siteAccessLogs()
            ->where('action', 'check_in')
            ->whereDate('accessed_at', today())
            ->latest()
            ->first();
    }

    /**
     * Get today's check-out.
     */
    public function todayCheckOut()
    {
        return $this->siteAccessLogs()
            ->where('action', 'check_out')
            ->whereDate('accessed_at', today())
            ->latest()
            ->first();
    }

    /**
     * Record a check-in.
     */
    public function checkIn($siteId = null, $method = 'qr', $notes = null): WorkerSiteAccessLog
    {
        $log = WorkerSiteAccessLog::create([
            'construction_worker_id' => $this->id,
            'site_id' => $siteId ?? $this->current_site_id ?? $this->contract?->site_id,
            'accessed_at' => now(),
            'action' => 'check_in',
            'method' => $method,
            'ip_address' => request()->ip(),
            'notes' => $notes,
        ]);

        // Update worker status
        $this->update([
            'is_on_site' => true,
            'last_check_in_at' => now(),
            'current_site_id' => $siteId ?? $this->current_site_id,
        ]);

        Log::info('Worker checked in', [
            'worker_id' => $this->id,
            'worker_name' => $this->full_name,
            'site_id' => $siteId,
            'method' => $method,
        ]);

        return $log;
    }

    /**
     * Record a check-out.
     */
    public function checkOut($notes = null): WorkerSiteAccessLog
    {
        $log = WorkerSiteAccessLog::create([
            'construction_worker_id' => $this->id,
            'site_id' => $this->current_site_id,
            'accessed_at' => now(),
            'action' => 'check_out',
            'method' => 'qr',
            'ip_address' => request()->ip(),
            'notes' => $notes,
        ]);

        // Update worker status
        $this->update([
            'is_on_site' => false,
            'last_check_out_at' => now(),
        ]);

        Log::info('Worker checked out', [
            'worker_id' => $this->id,
            'worker_name' => $this->full_name,
            'site_id' => $this->current_site_id,
        ]);

        return $log;
    }

    /**
     * Get total hours worked today.
     */
    public function getTodayHoursWorked(): float
    {
        $checkIn = $this->todayCheckIn();
        if (!$checkIn) {
            return 0;
        }

        $checkOut = $this->todayCheckOut();
        if (!$checkOut) {
            return now()->diffInHours($checkIn->accessed_at);
        }

        return $checkOut->accessed_at->diffInHours($checkIn->accessed_at);
    }

    // ============================================================
    // WORKER MANAGEMENT METHODS
    // ============================================================

    /**
     * Activate the worker.
     */
    public function activate(): bool
    {
        if (!$this->canBeActive()) {
            return false;
        }
        $this->status = self::STATUS_ACTIVE;
        if (!$this->start_date) {
            $this->start_date = now();
        }
        return $this->save();
    }

    /**
     * Terminate the worker.
     */
    public function terminate(?string $reason = null): bool
    {
        if (!$this->canBeTerminated()) {
            return false;
        }
        $this->status = self::STATUS_TERMINATED;
        $this->end_date = now();
        
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Terminated: " . $reason;
        }
        
        return $this->save();
    }

    /**
     * Complete the worker's work.
     */
    public function completeWork(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }
        $this->status = self::STATUS_COMPLETED;
        $this->end_date = now();
        return $this->save();
    }

    /**
     * Add a skill to the worker.
     */
    public function addSkill(string $skill): bool
    {
        $skills = $this->getSkillsArrayAttribute();
        
        if (in_array(strtolower($skill), array_map('strtolower', $skills))) {
            return false;
        }
        
        $skills[] = $skill;
        $this->skills = $skills;
        return $this->save();
    }

    /**
     * Remove a skill from the worker.
     */
    public function removeSkill(string $skill): bool
    {
        $skills = $this->getSkillsArrayAttribute();
        $filtered = array_filter($skills, function($s) use ($skill) {
            return strtolower($s) !== strtolower($skill);
        });
        
        if (count($filtered) === count($skills)) {
            return false;
        }
        
        $this->skills = array_values($filtered);
        return $this->save();
    }

    /**
     * Calculate total earnings.
     */
    public function calculateTotalEarnings(?string $frequency = null): float
    {
        $rate = $this->daily_rate ?? 0;
        
        if (!$rate) {
            return 0;
        }

        $frequency = $frequency ?? $this->payment_frequency ?? self::PAYMENT_DAILY;
        
        return match($frequency) {
            self::PAYMENT_DAILY => $rate,
            self::PAYMENT_WEEKLY => $rate * 7,
            self::PAYMENT_BIWEEKLY => $rate * 14,
            self::PAYMENT_MONTHLY => $rate * 30,
            default => $rate,
        };
    }

    // ============================================================
    // BADGE MANAGEMENT METHODS
    // ============================================================

    /**
     * Get or create a badge for this worker.
     */
    public function getOrCreateBadge(): WorkerBadge
    {
        if ($this->badge) {
            return $this->badge;
        }

        $badge = WorkerBadge::create([
            'construction_worker_id' => $this->id,
            'construction_contract_id' => $this->contract_id,
            'badge_number' => $this->generateBadgeNumber(),
            'email_sent_to' => $this->email,
            'valid_from' => $this->start_date ?? now(),
            'valid_until' => $this->end_date ?? now()->addMonths(6),
            'is_active' => $this->status === self::STATUS_ACTIVE,
        ]);

        $badge->generateQRCode();
        $badge->save();

        return $badge;
    }

    /**
     * Generate a unique badge number.
     */
    protected function generateBadgeNumber(): string
    {
        $year = date('Y');
        $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));
        return "WB-{$year}-{$random}";
    }

    /**
     * Sync badge status with worker status.
     */
    public function syncBadgeStatus(): void
    {
        if (!$this->badge) {
            return;
        }

        $badge = $this->badge;
        
        if ($this->status === self::STATUS_ACTIVE) {
            $badge->is_active = true;
            $badge->valid_from = $this->start_date ?? now();
            $badge->valid_until = $this->end_date ?? now()->addMonths(6);
        } else {
            $badge->is_active = false;
        }
        
        $badge->save();
    }

    /**
     * Revoke the worker's badge.
     */
    public function revokeBadge(?string $reason = null): bool
    {
        if (!$this->badge) {
            return false;
        }

        $this->badge->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
            'revocation_reason' => $reason,
            'notes' => $reason ? "Revoked: " . $reason : "Revoked by admin",
        ]);

        Log::info('Badge revoked', [
            'worker_id' => $this->id,
            'badge_number' => $this->badge->badge_number,
            'reason' => $reason,
            'revoked_by' => auth()->id(),
        ]);

        return true;
    }

    // ============================================================
    // STATISTICS METHODS
    // ============================================================

    /**
     * Get worker statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_days_worked' => $this->getTotalDaysWorkedAttribute(),
            'days_on_site' => $this->getDaysOnSiteAttribute(),
            'today_hours' => $this->getTodayHoursWorked(),
            'badge_verifications' => $this->verificationLogs()->count(),
            'last_verification' => $this->verificationLogs()->latest()->first()?->verified_at,
            'check_ins_count' => $this->checkIns()->count(),
            'check_outs_count' => $this->checkOuts()->count(),
            'total_earnings' => $this->calculateTotalEarnings() * $this->getTotalDaysWorkedAttribute(),
        ];
    }

    /**
     * Get workers grouped by trade.
     */
    public static function getGroupedByTrade()
    {
        return self::where('status', self::STATUS_ACTIVE)
            ->select('trade', \DB::raw('count(*) as total'))
            ->groupBy('trade')
            ->get()
            ->pluck('total', 'trade')
            ->toArray();
    }

    /**
     * Get workers grouped by status.
     */
    public static function getGroupedByStatus()
    {
        return self::select('status', \DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
    }

    // ============================================================
    // BOOT METHOD
    // ============================================================

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Creating event
        static::creating(function ($worker) {
            // Set default status if not provided
            if (empty($worker->status)) {
                $worker->status = self::STATUS_PENDING;
            }

            // Set default start date if not provided
            if (empty($worker->start_date) && $worker->status === self::STATUS_ACTIVE) {
                $worker->start_date = now()->toDateString();
            }

            // Set added_by if not provided and user is authenticated
            if (empty($worker->added_by) && auth()->check()) {
                $worker->added_by = auth()->id();
            }

            // 🔧 FIX: Don't set contractor_id directly - it should come from the contract relationship
            // The contractor_id is derived from the contract's contractor_user_id
            // Removing this will prevent the error
        });

        // Created event
        static::created(function ($worker) {
            Log::info('Worker created', [
                'worker_id' => $worker->id,
                'contract_id' => $worker->contract_id,
                'worker_name' => $worker->full_name,
                'added_by' => $worker->added_by,
                'contractor_id' => $worker->contract?->contractor_user_id, // Get from contract
            ]);
        });

        // Updating event
        static::updating(function ($worker) {
            // If status is changing to active, set start_date if not set
            if ($worker->isDirty('status') && 
                $worker->status === self::STATUS_ACTIVE && 
                empty($worker->start_date)) {
                $worker->start_date = now()->toDateString();
            }

            // If status is changing to completed or terminated, set end_date if not set
            if ($worker->isDirty('status') && 
                in_array($worker->status, [self::STATUS_COMPLETED, self::STATUS_TERMINATED]) && 
                empty($worker->end_date)) {
                $worker->end_date = now()->toDateString();
            }

            // 🔧 FIX: Don't update contractor_id directly - it's derived from contract
            // If contract changed, the contractor_id will be accessed through the relationship
        });

        // Updated event
        static::updated(function ($worker) {
            // Log status changes
            if ($worker->wasChanged('status')) {
                $oldStatus = $worker->getOriginal('status');
                $newStatus = $worker->status;
                
                Log::info('Worker status changed', [
                    'worker_id' => $worker->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_by' => auth()->id(),
                ]);
            }

            // Sync badge status when worker status changes
            if ($worker->wasChanged('status') || 
                $worker->wasChanged('start_date') || 
                $worker->wasChanged('end_date')) {
                try {
                    $worker->syncBadgeStatus();
                } catch (\Exception $e) {
                    Log::warning('Failed to sync badge status', [
                        'worker_id' => $worker->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log site status changes
            if ($worker->wasChanged('is_on_site')) {
                Log::info('Worker site status changed', [
                    'worker_id' => $worker->id,
                    'is_on_site' => $worker->is_on_site,
                    'site_id' => $worker->current_site_id,
                ]);
            }
        });

        // Deleted event
        static::deleted(function ($worker) {
            Log::info('Worker deleted', [
                'worker_id' => $worker->id,
                'contract_id' => $worker->contract_id,
                'worker_name' => $worker->full_name,
                'deleted_by' => auth()->id(),
            ]);
        });

        // Restored event
        static::restored(function ($worker) {
            Log::info('Worker restored', [
                'worker_id' => $worker->id,
                'restored_by' => auth()->id(),
            ]);
        });

        // Force deleted event
        static::forceDeleted(function ($worker) {
            Log::warning('Worker permanently deleted', [
                'worker_id' => $worker->id,
                'deleted_by' => auth()->id(),
            ]);
        });
    }

    // ============================================================
    // QUERY HELPERS
    // ============================================================

    /**
     * Get workers with their contract.
     */
    public static function getWithContract($workerId)
    {
        return self::with(['contract', 'addedBy', 'badge', 'currentSite'])
            ->find($workerId);
    }

    /**
     * Get active workers for a contract.
     */
    public static function getActiveForContract($contractId)
    {
        return self::active()
            ->forContract($contractId)
            ->with(['badge'])
            ->get();
    }

    /**
     * Get workers by status.
     */
    public static function getByStatus(string $status)
    {
        return self::where('status', $status)
            ->with(['contract', 'badge'])
            ->get();
    }

    /**
     * Search workers.
     */
    public static function search($query)
    {
        return self::where('full_name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('trade', 'LIKE', "%{$query}%")
            ->orWhere('job_title', 'LIKE', "%{$query}%")
            ->orWhere('id_number', 'LIKE', "%{$query}%")
            ->with(['badge', 'contract'])
            ->get();
    }

    /**
     * Get workers with badge statistics.
     */
    public static function getWithBadgeStats()
    {
        return self::withCount(['badge' => function($q) {
            $q->whereNotNull('id');
        }])
        ->with(['badge'])
        ->get();
    }

    /**
     * Get workers needing badge (active, has email, no badge).
     */
    public static function getWorkersNeedingBadge()
    {
        return self::active()
            ->whereNotNull('email')
            ->whereDoesntHave('badge')
            ->get();
    }

    /**
     * Get workers with expiring badges.
     */
    public static function getWorkersWithExpiringBadges($days = 7)
    {
        return self::whereHas('badge', function($q) use ($days) {
            $q->where('is_active', true)
              ->whereNotNull('valid_until')
              ->where('valid_until', '<=', now()->addDays($days))
              ->where('valid_until', '>', now());
        })
        ->with(['badge'])
        ->get();
    }

    /**
     * Get workers currently on site.
     */
    public static function getWorkersOnSite($siteId = null)
    {
        $query = self::where('is_on_site', true)
            ->with(['badge', 'contract', 'currentSite']);
        
        if ($siteId) {
            $query->where('current_site_id', $siteId);
        }
        
        return $query->get();
    }

    /**
     * Get workers for admin dashboard.
     */
    public static function getForAdminDashboard($filters = [])
    {
        $query = self::with(['contract', 'contract.contractor', 'badge', 'addedBy']);
        
        // Apply filters
        if (!empty($filters['contractor_id'])) {
            $query->whereHas('contract', function($q) use ($filters) {
                $q->where('contractor_user_id', $filters['contractor_id']);
            });
        }
        
        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['trade'])) {
            $query->where('trade', $filters['trade']);
        }
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('id_number', 'LIKE', "%{$search}%");
            });
        }
        
        return $query;
    }
}