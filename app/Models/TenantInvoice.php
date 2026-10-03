<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class TenantInvoice extends Model
{
    use SoftDeletes;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    // Calculation method constants
    const METHOD_FIXED = 'fixed';
    const METHOD_PER_PROPERTY_UNIT = 'per_property_unit';
    const METHOD_PERCENTAGE_OF_LANDLORD = 'percentage_of_landlord';

    // Archive status constants
    const ARCHIVE_STATUS_PENDING = 'pending';
    const ARCHIVE_STATUS_APPROVED = 'approved';
    const ARCHIVE_STATUS_ARCHIVED = 'archived';
    const ARCHIVE_STATUS_REJECTED = 'rejected';

    // Archive type constants
    const ARCHIVE_TYPE_YEAR_END = 'year_end';
    const ARCHIVE_TYPE_POST_PAYMENT = 'post_payment';
    const ARCHIVE_TYPE_MANUAL = 'manual';

    protected $table = 'tenant_invoices';

    protected $fillable = [
        'tenant_id',
        'property_unit_id',
        'invoice_number',
        'period',
        'due_date',
        'community_dues',
        'additional_charges',
        'paid_amount',
        'balance',
        'total_amount',
        'status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'penalty_amount',
        'penalty_applied_at',
        'penalty_reason',
        'grace_period_days',
        'calculation_method',
        'calculation_details',
        'description',
        'notes',
        'created_by',
        'updated_by',
        'metadata',
        // Year-end archiving fields
        'archived_at',
        'archived_by',
        'archive_reason',
        'archive_type',
        'archive_approved_by_tenant',
        'archive_approved_at',
        'archive_notification_sent_at',
        'archive_reminder_sent_at',
        'archive_status',
        'year_end_archived_at',
        'year_end_archive_year',
        'original_year'
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'penalty_applied_at' => 'datetime',
        'community_dues' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'grace_period_days' => 'integer',
        'calculation_details' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // Year-end archiving casts
        'archived_at' => 'datetime',
        'archive_approved_at' => 'datetime',
        'archive_notification_sent_at' => 'datetime',
        'archive_reminder_sent_at' => 'datetime',
        'archive_approved_by_tenant' => 'boolean',
        'year_end_archived_at' => 'datetime',
        'original_year' => 'integer'
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'community_dues' => 0,
        'additional_charges' => 0,
        'paid_amount' => 0,
        'balance' => 0,
        'total_amount' => 0,
        'penalty_amount' => 0,
        'grace_period_days' => 7,
        'archive_status' => self::ARCHIVE_STATUS_PENDING
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }

            if (empty($invoice->grace_period_days)) {
                $settings = SystemSetting::getSettings();
                $invoice->grace_period_days = $settings->tenant_grace_period_days ?? 7;
            }

            // Set original year for year-end tracking
            if (empty($invoice->original_year)) {
                $invoice->original_year = $invoice->created_at ? $invoice->created_at->year : now()->year;
            }

            $invoice->total_amount = $invoice->calculateTotalAmount();
            $invoice->balance = $invoice->total_amount - ($invoice->paid_amount ?? 0);
            $invoice->calculation_details = $invoice->generateCalculationDetails();

            $metadata = $invoice->metadata ?? [];
            $metadata['system_settings_at_creation'] = $invoice->getSystemSettingsSnapshot();
            $invoice->metadata = $metadata;
        });

        static::updating(function ($invoice) {
            if ($invoice->isDirty(['community_dues', 'additional_charges', 'penalty_amount'])) {
                $invoice->total_amount = $invoice->calculateTotalAmount();
                $invoice->balance = $invoice->total_amount - ($invoice->paid_amount ?? 0);
            }

            $invoice->updateStatusBasedOnBalanceAndDueDate();

            if ($invoice->isDirty('status')) {
                $metadata = $invoice->metadata ?? [];
                $metadata['status_changes'][] = [
                    'from' => $invoice->getOriginal('status'),
                    'to' => $invoice->status,
                    'changed_by' => auth()->id(),
                    'changed_by_name' => auth()->user()->name ?? 'System',
                    'changed_at' => now()->toDateTimeString(),
                    'reason' => 'Auto-update based on payment/due date'
                ];
                $invoice->metadata = $metadata;
            }
        });

        static::retrieved(function ($invoice) {
            if ($invoice->shouldBeOverdue() && $invoice->status !== self::STATUS_OVERDUE) {
                $invoice->status = self::STATUS_OVERDUE;
                $invoice->saveQuietly();
            }
        });
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'CDI-' . date('Y') . '-';
        $lastInvoice = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total amount
     */
    public function calculateTotalAmount(): float
    {
        return ($this->community_dues ?? 0) +
               ($this->additional_charges ?? 0) +
               ($this->penalty_amount ?? 0);
    }

    /**
     * Generate calculation details for audit
     */
    protected function generateCalculationDetails(): array
    {
        return [
            'community_dues' => $this->community_dues ?? 0,
            'additional_charges' => $this->additional_charges ?? 0,
            'penalty_amount' => $this->penalty_amount ?? 0,
            'total' => $this->total_amount ?? 0,
            'calculation_method' => $this->calculation_method ?? self::METHOD_FIXED,
            'grace_period_days' => $this->grace_period_days,
            'due_date' => $this->due_date?->toDateString(),
            'generated_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Get system settings snapshot
     */
    protected function getSystemSettingsSnapshot(): array
    {
        $settings = SystemSetting::getSettings();

        return [
            'enable_tenant_invoicing' => $settings->enable_tenant_invoicing,
            'tenant_monthly_dues_amount' => $settings->tenant_monthly_dues_amount,
            'tenant_calculation_method' => $settings->tenant_calculation_method,
            'tenant_dues_percentage' => $settings->tenant_dues_percentage,
            'auto_generate_tenant_invoices' => $settings->auto_generate_tenant_invoices,
            'send_tenant_payment_reminders' => $settings->send_tenant_payment_reminders,
            'tenant_grace_period_days' => $settings->tenant_grace_period_days,
            'tenant_late_payment_percentage' => $settings->tenant_late_payment_percentage,
            'tenant_fixed_penalty_amount' => $settings->tenant_fixed_penalty_amount,
            'currency_code' => $settings->currency_code,
            'currency_symbol' => $settings->currency_symbol,
            'currency_position' => $settings->currency_position,
            'snapshot_taken_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Update status based on balance and due date
     */
    protected function updateStatusBasedOnBalanceAndDueDate(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($this->balance <= 0 && ($this->paid_amount ?? 0) > 0) {
            $this->status = self::STATUS_PAID;
            return;
        }

        if ($this->shouldBeOverdue()) {
            $this->status = self::STATUS_OVERDUE;
            return;
        }

        if ($this->status !== self::STATUS_PENDING && $this->balance > 0 && !$this->shouldBeOverdue()) {
            $this->status = self::STATUS_PENDING;
        }
    }

    /**
     * Check if invoice should be overdue
     */
    public function shouldBeOverdue(): bool
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED])) {
            return false;
        }

        if (!$this->due_date) {
            return false;
        }

        $gracePeriodEnd = $this->due_date->copy()->addDays($this->grace_period_days ?? 7);

        return now()->startOfDay()->gt($gracePeriodEnd) && $this->balance > 0;
    }

    // ========== YEAR-END ARCHIVING METHODS ==========

    /**
     * Check if invoice is from a specific year
     */
    public function isFromYear(int $year): bool
    {
        return $this->created_at->year == $year ||
               str_starts_with($this->period, "{$year}-");
    }

    /**
     * Get the child invoices (for bulk payments)
     */
    public function childInvoices()
    {
        return $this->hasMany(self::class, 'bulk_parent_id');
    }

    /**
     * Get the parent invoice (if this is a child of a bulk payment)
     */
    public function parentInvoice()
    {
        return $this->belongsTo(self::class, 'bulk_parent_id');
    }

    /**
     * Check if invoice has been year-end archived
     */
    public function isYearEndArchived(): bool
    {
        return !is_null($this->year_end_archived_at);
    }

    /**
     * Check if invoice is eligible for year-end archive (paid)
     */
    public function isEligibleForYearEndArchive(): bool
    {
        return $this->status === self::STATUS_PAID &&
               is_null($this->year_end_archived_at) &&
               is_null($this->deleted_at);
    }

    /**
     * Check if invoice is eligible for post-payment archive
     */
    public function isEligibleForPostPaymentArchive(int $retentionMonths): bool
    {
        if ($this->status !== self::STATUS_PAID) {
            return false;
        }

        if (is_null($this->year_end_archived_at)) {
            return false;
        }

        if (is_null($this->payment_date)) {
            return false;
        }

        // Calculate cutoff date - we want invoices paid BEFORE the cutoff date
        $cutoffDate = now()->subMonths($retentionMonths);

        // Log for debugging
        \Illuminate\Support\Facades\Log::info('Checking post-payment eligibility', [
            'invoice_id' => $this->id,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
            'is_eligible' => $this->payment_date->lte($cutoffDate)
        ]);

        // Invoice is eligible if payment date is on or before the cutoff date
        return $this->payment_date->lte($cutoffDate) && is_null($this->deleted_at);
    }

    /**
     * Mark invoice as year-end processed (kept for unpaid)
     */
    public function markAsYearEndProcessed(int $year): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['year_end_processed'] = [
            'processed_at' => now()->toDateTimeString(),
            'year' => $year,
            'status' => $this->status,
            'notes' => 'Kept active for payment collection'
        ];

        $this->update([
            'year_end_archived_at' => now(),
            'year_end_archive_year' => $year,
            'original_year' => $year,
            'metadata' => $metadata
        ]);

        return $this;
    }

    /**
     * Archive invoice (move to trash)
     */
    public function archive(string $reason, string $type = self::ARCHIVE_TYPE_YEAR_END, ?int $archivedBy = null): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['archive_details'] = [
            'archived_at' => now()->toDateTimeString(),
            'archived_by' => $archivedBy,
            'archived_by_name' => $archivedBy ? User::find($archivedBy)?->name : 'System',
            'archive_reason' => $reason,
            'archive_type' => $type,
            'invoice_status_before_archive' => $this->status,
            'balance_before_archive' => $this->balance,
            'paid_amount_before_archive' => $this->paid_amount
        ];

        $this->update([
            'archived_at' => now(),
            'archived_by' => $archivedBy,
            'archive_reason' => $reason,
            'archive_type' => $type,
            'archive_status' => self::ARCHIVE_STATUS_ARCHIVED,
            'metadata' => $metadata
        ]);

        // Soft delete the invoice
        $this->delete();

        return $this;
    }

    /**
     * Request tenant approval for archiving
     */
    public function requestArchiveApproval(): self
    {
        $this->update([
            'archive_status' => self::ARCHIVE_STATUS_PENDING,
            'archive_reminder_sent_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'archive_requested_at' => now()->toDateTimeString(),
                'archive_requested_by' => 'system'
            ])
        ]);

        return $this;
    }

    /**
     * Process tenant approval of archive
     */
    public function processArchiveApproval(bool $approved, ?string $reason = null): self
    {
        if ($approved) {
            $this->update([
                'archive_approved_by_tenant' => true,
                'archive_approved_at' => now(),
                'archive_status' => self::ARCHIVE_STATUS_APPROVED,
                'metadata' => array_merge($this->metadata ?? [], [
                    'archive_approved_at' => now()->toDateTimeString(),
                    'archive_approval_reason' => $reason
                ])
            ]);
        } else {
            $this->update([
                'archive_status' => self::ARCHIVE_STATUS_REJECTED,
                'metadata' => array_merge($this->metadata ?? [], [
                    'archive_rejected_at' => now()->toDateTimeString(),
                    'archive_rejection_reason' => $reason,
                    'archive_rejected_by' => auth()->id() ?? 'system'
                ])
            ]);
        }

        return $this;
    }

    // ========== RELATIONSHIPS ==========

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function propertyUnit()
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function property()
    {
        return $this->hasOneThrough(
            Property::class,
            PropertyUnit::class,
            'id',
            'id',
            'property_unit_id',
            'property_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function payments()
    {
        return $this->hasMany(TenantPayment::class, 'invoice_id');
    }

    // ========== ATTRIBUTE ACCESSORS ==========

    public function getFormattedInvoiceNumberAttribute(): string
    {
        return $this->invoice_number;
    }

    public function getTotalAmountAttribute($value): float
    {
        if (isset($this->attributes['total_amount']) && $this->attributes['total_amount'] !== null) {
            return (float) $this->attributes['total_amount'];
        }
        return $this->calculateTotalAmount();
    }

    public function setTotalAmountAttribute($value): void
    {
        $this->attributes['total_amount'] = $value;
    }

    public function getBalanceAttribute($value): float
    {
        if (isset($this->attributes['balance']) && $this->attributes['balance'] !== null) {
            return (float) $this->attributes['balance'];
        }
        return $this->total_amount - ($this->paid_amount ?? 0);
    }

    public function setBalanceAttribute($value): void
    {
        $this->attributes['balance'] = $value;
    }

    public function getBaseAmountAttribute(): float
    {
        return $this->community_dues ?? 0;
    }

    public function getFormattedBaseAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->base_amount);
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->total_amount);
    }

    public function getFormattedPaidAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->paid_amount ?? 0);
    }

    public function getFormattedBalanceAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->balance);
    }

    public function getFormattedPenaltyAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->penalty_amount ?? 0);
    }

    public function getMonthNameAttribute(): string
    {
        return Carbon::parse($this->period . '-01')->format('F Y');
    }

    public function getShortMonthNameAttribute(): string
    {
        return Carbon::parse($this->period . '-01')->format('M Y');
    }

    public function getPropertyUnitDisplayAttribute(): string
    {
        if (!$this->propertyUnit) {
            return 'N/A';
        }
        return $this->propertyUnit->property->property_name . ' - ' . $this->propertyUnit->unit_number;
    }

    /**
     * Get days until due date (positive if future, negative if past)
     */
    public function getDaysUntilDueAttribute(): int
    {
        if (!$this->due_date) {
            return 0;
        }
        return (int) now()->startOfDay()->diffInDays($this->due_date, false);
    }

    /**
     * Get days overdue (0 if not overdue)
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->due_date || $this->due_date->gt(now())) {
            return 0;
        }

        $graceEnd = $this->due_date->copy()->addDays($this->grace_period_days ?? 7);

        if (now()->lte($graceEnd)) {
            return 0;
        }

        return (int) $graceEnd->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Get days remaining in grace period
     */
    public function getDaysInGraceAttribute(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        $graceEnd = $this->due_date->copy()->addDays($this->grace_period_days ?? 7);

        if (now()->gt($graceEnd)) {
            return 0;
        }

        if (now()->lt($this->due_date)) {
            return (int) $this->days_until_due;
        }

        return (int) now()->diffInDays($graceEnd);
    }

    /**
     * Check if invoice is before due date
     */
    public function getBeforeDueDateAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        return $this->due_date->gt(now());
    }

    /**
     * Check if within grace period
     */
    public function getWithinGracePeriodAttribute(): bool
    {
        if (!$this->due_date || $this->isPaid()) {
            return false;
        }

        if ($this->due_date->gt(now())) {
            return false;
        }

        $graceEnd = $this->due_date->copy()->addDays($this->grace_period_days ?? 7);

        return now()->lte($graceEnd);
    }

    /**
     * Check if after grace period
     */
    public function getAfterGracePeriodAttribute(): bool
    {
        if (!$this->due_date || $this->isPaid()) {
            return false;
        }

        $graceEnd = $this->due_date->copy()->addDays($this->grace_period_days ?? 7);

        return now()->gt($graceEnd);
    }

    /**
     * Get grace period end date
     */
    public function getGracePeriodEndAttribute(): ?Carbon
    {
        if (!$this->due_date) {
            return null;
        }
        return $this->due_date->copy()->addDays($this->grace_period_days ?? 7);
    }

    /**
     * Get grace period status text
     */
    public function getGraceStatusTextAttribute(): string
    {
        if ($this->isPaid()) {
            return "Paid on " . ($this->payment_date?->format('M d, Y') ?? 'N/A');
        }

        if (!$this->due_date) {
            return "No due date";
        }

        $daysUntilDue = $this->days_until_due;

        if ($daysUntilDue === 0) {
            return "Due today";
        }

        if ($daysUntilDue > 0) {
            if ($daysUntilDue <= 3) {
                return "Due in {$daysUntilDue} day" . ($daysUntilDue != 1 ? 's' : '') . " • Due soon!";
            }
            return "Due in {$daysUntilDue} day" . ($daysUntilDue != 1 ? 's' : '');
        }

        $daysInGrace = $this->days_in_grace;
        if ($daysInGrace > 0) {
            return "Grace period: {$daysInGrace} day" . ($daysInGrace != 1 ? 's' : '') . " left";
        }

        $daysOverdue = $this->days_overdue;
        return "Grace period ended • Overdue by {$daysOverdue} day" . ($daysOverdue != 1 ? 's' : '');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'bg-green-100 text-green-800',
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_OVERDUE => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'fas fa-check-circle',
            self::STATUS_PENDING => 'fas fa-clock',
            self::STATUS_OVERDUE => 'fas fa-exclamation-triangle',
            self::STATUS_CANCELLED => 'fas fa-ban',
            default => 'fas fa-circle'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_OVERDUE => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get archive status badge class
     */
    public function getArchiveStatusBadgeClassAttribute(): string
    {
        return match($this->archive_status) {
            self::ARCHIVE_STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::ARCHIVE_STATUS_APPROVED => 'bg-green-100 text-green-800',
            self::ARCHIVE_STATUS_ARCHIVED => 'bg-gray-100 text-gray-800',
            self::ARCHIVE_STATUS_REJECTED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // ========== BUSINESS LOGIC METHODS ==========

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE || $this->shouldBeOverdue();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCurrentMonth(): bool
    {
        return $this->period === now()->format('Y-m');
    }

    public function isDueSoon(): bool
    {
        $daysUntilDue = $this->days_until_due;

        if ($daysUntilDue > 0 && $daysUntilDue <= 3) {
            return true;
        }

        return $this->within_grace_period;
    }

    public function canApplyPenalty(): bool
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED])) {
            return false;
        }

        if ($this->balance <= 0) {
            return false;
        }

        if ($this->within_grace_period) {
            return false;
        }

        if (($this->penalty_amount ?? 0) > 0) {
            return false;
        }

        return true;
    }

    public function calculatePotentialPenalty(): array
    {
        $settings = SystemSetting::getSettings();

        $percentage = $settings->tenant_late_payment_percentage ?? 0;
        $fixed = $settings->tenant_fixed_penalty_amount ?? 0;

        $percentageAmount = $this->total_amount * ($percentage / 100);
        $fixedAmount = $fixed;

        $recommendedAmount = 0;
        $method = 'none';

        if ($percentage > 0 && $fixed > 0) {
            $recommendedAmount = round($percentageAmount, 2);
            $method = 'percentage';
        } elseif ($percentage > 0) {
            $recommendedAmount = round($percentageAmount, 2);
            $method = 'percentage';
        } elseif ($fixed > 0) {
            $recommendedAmount = $fixedAmount;
            $method = 'fixed';
        }

        return [
            'can_apply' => $this->canApplyPenalty() && $recommendedAmount > 0,
            'percentage' => $percentage,
            'fixed' => $fixed,
            'percentage_amount' => round($percentageAmount, 2),
            'fixed_amount' => $fixedAmount,
            'recommended_amount' => $recommendedAmount,
            'recommended_method' => $method,
            'formatted_recommended' => $settings->formatAmount($recommendedAmount),
            'days_overdue' => $this->days_overdue,
            'within_grace_period' => $this->within_grace_period
        ];
    }

    /**
     * Apply a penalty to the invoice.
     *
     * ✅ PHP 8.4+ fix: `?string $reason` for explicit nullability.
     */
    public function applyPenalty(float $amount, ?string $reason = null, array $options = []): self
    {
        $settings = SystemSetting::getSettings();

        $metadata = $this->metadata ?? [];
        $metadata['penalty_applied'] = $metadata['penalty_applied'] ?? [];
        $metadata['penalty_applied'][] = [
            'amount' => $amount,
            'reason' => $reason,
            'applied_by' => auth()->id(),
            'applied_by_name' => auth()->user()->name ?? 'System',
            'applied_at' => now()->toDateTimeString(),
            'system_percentage' => $settings->tenant_late_payment_percentage,
            'system_fixed' => $settings->tenant_fixed_penalty_amount,
            'options' => $options
        ];

        $this->penalty_amount = ($this->penalty_amount ?? 0) + $amount;
        $this->penalty_applied_at = now();
        $this->penalty_reason = $reason;
        $this->metadata = $metadata;

        $this->total_amount = $this->calculateTotalAmount();
        $this->balance = $this->total_amount - ($this->paid_amount ?? 0);

        $this->save();

        return $this;
    }

    /**
     * Remove a penalty from the invoice.
     *
     * ✅ PHP 8.4+ fix: `?string $reason` for explicit nullability.
     */
    public function removePenalty(?string $reason = null): self
    {
        if (($this->penalty_amount ?? 0) <= 0) {
            return $this;
        }

        $metadata = $this->metadata ?? [];
        $metadata['penalty_removed'] = $metadata['penalty_removed'] ?? [];
        $metadata['penalty_removed'][] = [
            'amount' => $this->penalty_amount,
            'reason' => $reason,
            'removed_by' => auth()->id(),
            'removed_by_name' => auth()->user()->name ?? 'System',
            'removed_at' => now()->toDateTimeString()
        ];

        $this->penalty_amount = 0;
        $this->penalty_applied_at = null;
        $this->penalty_reason = null;
        $this->metadata = $metadata;

        $this->total_amount = $this->calculateTotalAmount();
        $this->balance = $this->total_amount - ($this->paid_amount ?? 0);

        $this->save();

        return $this;
    }

    /**
     * Record a payment against the invoice.
     *
     * ✅ PHP 8.4+ fix: `?string $reference`, `?string $notes` for explicit nullability.
     */
    public function recordPayment(float $amount, string $method, ?string $reference = null, ?string $notes = null): self
    {
        if ($amount < $this->total_amount) {
            throw new \InvalidArgumentException('Partial payments are not accepted. Payment amount must be at least the full invoice amount of ' . $this->total_amount);
        }

        $metadata = $this->metadata ?? [];
        $metadata['payments'] = $metadata['payments'] ?? [];
        $metadata['payments'][] = [
            'amount' => $this->total_amount,
            'method' => $method,
            'reference' => $reference,
            'recorded_by' => auth()->id(),
            'recorded_by_name' => auth()->user()->name ?? 'System',
            'recorded_at' => now()->toDateTimeString(),
            'notes' => $notes
        ];

        $excessPayment = $amount - $this->total_amount;
        if ($excessPayment > 0) {
            $metadata['excess_payment'] = [
                'amount' => $excessPayment,
                'notes' => 'Payment exceeded invoice amount by ' . ($excessPayment),
                'handling' => 'Recorded as credit for future invoices',
                'recorded_at' => now()->toDateTimeString()
            ];
        }

        $this->paid_amount = $this->total_amount;
        $this->payment_method = $method;
        $this->payment_reference = $reference;
        $this->payment_date = now();
        $this->status = self::STATUS_PAID;
        $this->balance = 0;
        $this->metadata = $metadata;

        $metadata['fully_paid_at'] = now()->toDateTimeString();
        $metadata['fully_paid_by'] = auth()->user()->name ?? 'System';

        $this->metadata = $metadata;
        $this->save();

        return $this;
    }

    /**
     * Mark the invoice as paid.
     *
     * ✅ PHP 8.4+ fix: `?string $paymentReference`, `?string $notes` for explicit nullability.
     */
    public function markAsPaid(string $paymentMethod, ?string $paymentReference = null, ?string $notes = null): self
    {
        return $this->recordPayment($this->total_amount, $paymentMethod, $paymentReference, $notes);
    }

    /**
     * Mark the invoice as cancelled.
     *
     * ✅ PHP 8.4+ fix: `?string $reason` for explicit nullability.
     */
    public function markAsCancelled(?string $reason = null): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancelled'] = [
            'reason' => $reason,
            'cancelled_by' => auth()->id(),
            'cancelled_by_name' => auth()->user()->name ?? 'System',
            'cancelled_at' => now()->toDateTimeString(),
            'previous_status' => $this->status
        ];

        $this->status = self::STATUS_CANCELLED;
        $this->metadata = $metadata;
        $this->save();

        return $this;
    }

    public function getPaymentSummaryAttribute(): array
    {
        $settings = SystemSetting::getSettings();

        return [
            'total' => $this->total_amount,
            'formatted_total' => $this->formatted_total_amount,
            'paid' => $this->paid_amount ?? 0,
            'formatted_paid' => $this->formatted_paid_amount,
            'balance' => $this->balance,
            'formatted_balance' => $this->formatted_balance,
            'penalty' => $this->penalty_amount ?? 0,
            'formatted_penalty' => $this->formatted_penalty_amount,
            'payment_percentage' => $this->total_amount > 0
                ? round((($this->paid_amount ?? 0) / $this->total_amount) * 100, 2)
                : 0,
            'status' => $this->status,
            'status_badge' => $this->status_badge_class,
            'status_icon' => $this->status_icon,
            'status_color' => $this->status_color
        ];
    }

    public function getDueDateInfoAttribute(): array
    {
        $settings = SystemSetting::getSettings();

        return [
            'due_date' => $this->due_date?->toDateString(),
            'formatted_due_date' => $this->due_date?->format('F j, Y'),
            'days_until_due' => $this->days_until_due,
            'days_overdue' => $this->days_overdue,
            'is_overdue' => $this->isOverdue(),
            'grace_period_days' => $this->grace_period_days,
            'within_grace_period' => $this->within_grace_period,
            'grace_period_end' => $this->grace_period_end?->toDateString(),
            'formatted_grace_end' => $this->grace_period_end?->format('F j, Y'),
            'days_in_grace' => $this->days_in_grace,
            'can_apply_penalty' => $this->canApplyPenalty(),
            'penalty_info' => $this->calculatePotentialPenalty(),
            'grace_status' => $this->grace_period_status
        ];
    }

    /**
     * Get archive info attribute
     */
    public function getArchiveInfoAttribute(): array
    {
        return [
            'is_archived' => !is_null($this->deleted_at),
            'is_year_end_archived' => $this->isYearEndArchived(),
            'year_end_archive_year' => $this->year_end_archive_year,
            'original_year' => $this->original_year,
            'archive_status' => $this->archive_status,
            'archive_type' => $this->archive_type,
            'archive_reason' => $this->archive_reason,
            'archived_at' => $this->archived_at?->toDateString(),
            'formatted_archived_at' => $this->archived_at?->format('F j, Y'),
            'archive_approved_by_tenant' => $this->archive_approved_by_tenant,
            'archive_approved_at' => $this->archive_approved_at?->toDateString()
        ];
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_OVERDUE)
              ->orWhere(function($sub) {
                  $sub->where('status', self::STATUS_PENDING)
                      ->whereRaw('due_date + INTERVAL grace_period_days DAY < NOW()')
                      ->whereRaw('paid_amount < total_amount');
              });
        });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_OVERDUE
        ]);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPropertyUnit($query, $propertyUnitId)
    {
        return $query->where('property_unit_id', $propertyUnitId);
    }

    public function scopeForProperty($query, $propertyId)
    {
        return $query->whereHas('propertyUnit', function($q) use ($propertyId) {
            $q->where('property_id', $propertyId);
        });
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', now()->toDateString())
            ->where('status', self::STATUS_PENDING);
    }

    public function scopeDueThisWeek($query)
    {
        return $query->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('status', self::STATUS_PENDING);
    }

    public function scopeWithBalance($query)
    {
        return $query->whereRaw('paid_amount < total_amount')
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE]);
    }

    public function scopeWithinGracePeriod($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('due_date', '<', now())
            ->whereRaw('due_date + INTERVAL grace_period_days DAY >= NOW()');
    }

    public function scopePastGracePeriod($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereRaw('due_date + INTERVAL grace_period_days DAY < NOW()');
    }

    public function scopeGeneratedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeDueBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    public function scopeLatestPeriod($query)
    {
        return $query->orderBy('period', 'desc');
    }

    public function scopeOrderByDueDate($query, $direction = 'asc')
    {
        return $query->orderBy('due_date', $direction);
    }

    public function scopeDueSoon($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereBetween('due_date', [now(), now()->addDays(3)]);
    }

    public function scopeCanApplyPenalty($query)
    {
        $settings = SystemSetting::getSettings();
        $hasPenalty = ($settings->tenant_late_payment_percentage ?? 0) > 0 ||
                     ($settings->tenant_fixed_penalty_amount ?? 0) > 0;

        if (!$hasPenalty) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('status', self::STATUS_PENDING)
            ->whereRaw('due_date + INTERVAL grace_period_days DAY < NOW()')
            ->where('penalty_amount', 0);
    }

    // ========== YEAR-END ARCHIVE SCOPES ==========

    /**
     * Scope to get invoices from a specific year
     */
    public function scopeFromYear($query, $year)
    {
        return $query->whereYear('created_at', $year)
            ->orWhere('period', 'like', "{$year}-%");
    }

    /**
     * Scope to get invoices not year-end archived
     */
    public function scopeNotYearEndArchived($query)
    {
        return $query->whereNull('year_end_archived_at');
    }

    /**
     * Scope to get paid invoices eligible for year-end archive
     */
    public function scopePaidAndEligibleForYearEndArchive($query, $year)
    {
        return $query->fromYear($year)
            ->where('status', self::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->whereNull('deleted_at');
    }

    /**
     * Scope to get unpaid invoices from a specific year
     */
    public function scopeUnpaidFromYear($query, $year)
    {
        return $query->fromYear($year)
            ->where('status', '!=', self::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->whereNull('deleted_at');
    }

    /**
     * Scope to get invoices ready for post-payment archive
     */
    public function scopeReadyForPostPaymentArchive($query, $monthsAfterPayment)
    {
        $cutoffDate = now()->subMonths($monthsAfterPayment);

        return $query->where('status', self::STATUS_PAID)
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at');
    }

    /**
     * Scope to get eligible invoices for year-end archive
     */
    public function scopeEligibleForArchive($query)
    {
        return $query->where('status', self::STATUS_PAID)
            ->whereNull('archived_at')
            ->where(function($q) {
                $q->whereNull('archive_status')
                  ->orWhere('archive_status', self::ARCHIVE_STATUS_PENDING);
            });
    }

    /**
     * Scope to get invoices ready for archive after retention period
     */
    public function scopeReadyForArchive($query, $monthsAfterPayment)
    {
        $cutoffDate = now()->subMonths($monthsAfterPayment);

        return $query->where('status', self::STATUS_PAID)
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->whereNull('archived_at');
    }

    /**
     * Scope to get year-end archive invoices for a specific year
     */
    public function scopeYearEndArchive($query, $year)
    {
        return $query->where('status', self::STATUS_PAID)
            ->whereYear('payment_date', $year)
            ->whereNull('archived_at');
    }

    /**
     * Scope to get unpaid and overdue invoices
     */
    public function scopeUnpaidAndOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('due_date', '<', now());
    }

    // ========== STATIC HELPER METHODS ==========

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function getCalculationMethodOptions(): array
    {
        return [
            self::METHOD_FIXED => 'Fixed Amount',
            self::METHOD_PER_PROPERTY_UNIT => 'Per Property Unit',
            self::METHOD_PERCENTAGE_OF_LANDLORD => 'Percentage of Landlord Dues',
        ];
    }

    public static function getArchiveStatusOptions(): array
    {
        return [
            self::ARCHIVE_STATUS_PENDING => 'Pending',
            self::ARCHIVE_STATUS_APPROVED => 'Approved',
            self::ARCHIVE_STATUS_ARCHIVED => 'Archived',
            self::ARCHIVE_STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function getArchiveTypeOptions(): array
    {
        return [
            self::ARCHIVE_TYPE_YEAR_END => 'Year End Archive',
            self::ARCHIVE_TYPE_POST_PAYMENT => 'Post Payment Archive',
            self::ARCHIVE_TYPE_MANUAL => 'Manual Archive',
        ];
    }

    public static function getPaymentMethodOptions(): array
    {
        return [
            'mtn_momo' => 'MTN Mobile Money',
            'telecel_cash' => 'Telecel Cash',
            'airteltigo_cash' => 'AirtelTigo Cash',
            'paystack' => 'Paystack',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'other' => 'Other'
        ];
    }

    public static function getStatistics(array $filters = []): array
    {
        $query = self::query();

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['property_id'])) {
            $query->whereHas('propertyUnit', function($q) use ($filters) {
                $q->where('property_id', $filters['property_id']);
            });
        }

        if (!empty($filters['period_from'])) {
            $query->where('period', '>=', $filters['period_from']);
        }

        if (!empty($filters['period_to'])) {
            $query->where('period', '<=', $filters['period_to']);
        }

        $stats = [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'total_balance' => $query->sum('balance'),
            'total_penalties' => $query->sum('penalty_amount'),
            'paid_invoices' => (clone $query)->where('status', self::STATUS_PAID)->count(),
            'pending_invoices' => (clone $query)->where('status', self::STATUS_PENDING)->count(),
            'overdue_invoices' => (clone $query)->where('status', self::STATUS_OVERDUE)->count(),
            'cancelled_invoices' => (clone $query)->where('status', self::STATUS_CANCELLED)->count(),
            'within_grace_period' => (clone $query)->where('status', self::STATUS_PENDING)
                ->where('due_date', '<', now())
                ->whereRaw('due_date + INTERVAL grace_period_days DAY >= NOW()')
                ->count(),
            'past_grace_period' => (clone $query)->where('status', self::STATUS_PENDING)
                ->whereRaw('due_date + INTERVAL grace_period_days DAY < NOW()')
                ->count(),
            // Year-end archive statistics
            'year_end_archived' => (clone $query)->whereNotNull('year_end_archived_at')->count(),
            'archived_paid_invoices' => (clone $query)->where('status', self::STATUS_PAID)->whereNotNull('year_end_archived_at')->count(),
            'unpaid_kept_invoices' => (clone $query)->where('status', '!=', self::STATUS_PAID)->whereNotNull('year_end_archived_at')->count(),
            'pending_archive_approval' => (clone $query)->where('archive_status', self::ARCHIVE_STATUS_PENDING)->count(),
            'post_payment_archived' => (clone $query)->where('archive_type', self::ARCHIVE_TYPE_POST_PAYMENT)->count()
        ];

        $stats['collection_rate'] = $stats['total_amount'] > 0
            ? round(($stats['total_paid'] / $stats['total_amount']) * 100, 2)
            : 0;

        return $stats;
    }

    public static function getNextGenerationPeriod(): string
    {
        $lastInvoice = self::orderBy('period', 'desc')->first();

        if ($lastInvoice) {
            return Carbon::parse($lastInvoice->period . '-01')->addMonth()->format('Y-m');
        }

        return now()->startOfMonth()->format('Y-m');
    }

    public static function hasInvoicesForPeriod(string $period): bool
    {
        return self::where('period', $period)->exists();
    }

    /**
     * Get year-end archive statistics for a specific year
     */
    public static function getYearEndStatistics(int $year): array
    {
        $totalFromYear = self::fromYear($year)->count();
        $paidFromYear = self::fromYear($year)
            ->where('status', self::STATUS_PAID)
            ->count();
        $unpaidFromYear = self::fromYear($year)
            ->where('status', '!=', self::STATUS_PAID)
            ->count();

        $yearEndArchived = self::fromYear($year)
            ->whereNotNull('year_end_archived_at')
            ->count();

        return [
            'year' => $year,
            'total_invoices' => $totalFromYear,
            'paid_invoices' => $paidFromYear,
            'unpaid_invoices' => $unpaidFromYear,
            'year_end_archived' => $yearEndArchived,
            'archived_rate' => $totalFromYear > 0 ? round(($yearEndArchived / $totalFromYear) * 100, 2) : 0
        ];
    }
}