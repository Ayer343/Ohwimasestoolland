<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PropertyUnitInvoice extends Model
{
    use SoftDeletes;

    // ========== ✅ INVOICE: TYPES ==========
    public const TYPE_ADVANCE_RENT      = 'advance_rent';
    public const TYPE_MONTHLY_RENT      = 'monthly_rent';
    public const TYPE_SECURITY_DEPOSIT  = 'security_deposit';
    public const TYPE_UTILITY_DEPOSIT   = 'utility_deposit';
    public const TYPE_LATE_FEE          = 'late_fee';
    public const TYPE_EARLY_TERMINATION = 'early_termination';
    public const TYPE_OTHER             = 'other';

    // ========== ✅ INVOICE: STATUSES ==========
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID    = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_VOID    = 'void';

    /**
     * ✅ FIXED: Added the columns the controller and views actually write/read:
     *   - issue_date          (written by generateInvoice, read by view)
     *   - last_payment_at     (written by recordPayment, read by view)
     *   - payment_method      (written by recordPayment, read by view)
     *   - payment_reference   (written by recordPayment, read by view)
     *   - voided_by           (written by voidInvoice, read by view)
     *   - advance_rent_months (written by generateInvoice when type = advance_rent)
     *   - is_advance_rent     (written by generateInvoice, boolean flag)
     *   - metadata            (receipt paths, audit extras)
     */
    protected $fillable = [
        // Relations
        'lease_id', 'unit_id', 'property_id', 'tenant_id', 'landlord_id',

        // Identification
        'invoice_type', 'reference', 'description',

        // Amounts
        'amount', 'amount_paid',

        // Dates
        'issue_date', 'due_date', 'period_start', 'period_end',

        // Status
        'status',
        'paid_at',
        'last_payment_at',

        // Payment
        'payment_method',
        'payment_reference',

        // Void
        'void_reason', 'voided_at', 'voided_by',

        // Ghana: advance rent snapshot
        'advance_rent_months', 'is_advance_rent',

        // Meta
        'metadata',
        'notes',
    ];

    /**
     * ✅ FIXED: Added the missing casts so Carbon methods work:
     *   - issue_date
     *   - last_payment_at
     *   - voided_by (kept as int, no cast needed)
     *   - is_advance_rent (boolean)
     *   - metadata (array)
     *   - advance_rent_months (integer)
     */
    protected $casts = [
        // Amounts
        'amount'              => 'decimal:2',
        'amount_paid'         => 'decimal:2',

        // Dates / timestamps
        'issue_date'          => 'date',
        'due_date'            => 'date',
        'period_start'        => 'date',
        'period_end'          => 'date',
        'paid_at'             => 'datetime',
        'last_payment_at'     => 'datetime',
        'voided_at'           => 'datetime',

        // Booleans
        'is_advance_rent'     => 'boolean',

        // Integers
        'advance_rent_months' => 'integer',

        // Arrays / JSON
        'metadata'            => 'array',
    ];

    /**
     * ✅ Appended attributes for JSON serialization.
     */
    protected $appends = [
        'balance',
        'is_paid',
        'is_overdue',
        'is_void',
        'display_status',
        'formatted_amount',
        'formatted_amount_paid',
        'formatted_balance',
    ];

    // ========== RELATIONSHIPS ==========

    public function lease(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'lease_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * ✅ NEW: The user who voided this invoice (if any).
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // ========== SCOPES ==========

    public function scopeOutstanding($q)
    {
        return $q->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PARTIAL,
            self::STATUS_OVERDUE,
        ]);
    }

    public function scopeForLease($q, $leaseId)
    {
        return $q->where('lease_id', $leaseId);
    }

    public function scopeForUnit($q, $unitId)
    {
        return $q->where('unit_id', $unitId);
    }

    public function scopeForTenant($q, $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopePaid($q)
    {
        return $q->where('status', self::STATUS_PAID);
    }

    public function scopeUnpaid($q)
    {
        return $q->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PARTIAL,
            self::STATUS_OVERDUE,
        ]);
    }

    public function scopeOverdue($q)
    {
        return $q->where('status', self::STATUS_OVERDUE);
    }

    public function scopeVoided($q)
    {
        return $q->where('status', self::STATUS_VOID);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('invoice_type', $type);
    }

    /**
     * ✅ NEW: Invoices past their due date that aren't paid/void.
     * Useful for the `mark-overdue` command.
     */
    public function scopePastDue($q)
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL])
                 ->whereNotNull('due_date')
                 ->where('due_date', '<', now()->startOfDay());
    }

    // ========== ACCESSORS ==========

    /**
     * ✅ Balance owed on this invoice (never negative).
     */
    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function getIsVoidAttribute(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * ✅ Human-readable status for the UI.
     */
    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_PAID    => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_VOID    => 'Void',
            default              => ucfirst($this->status),
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        return config('leases.ghana.currency.symbol', 'GH₵') . ' '
            . number_format((float) $this->amount, 2);
    }

    public function getFormattedAmountPaidAttribute(): string
    {
        return config('leases.ghana.currency.symbol', 'GH₵') . ' '
            . number_format((float) $this->amount_paid, 2);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return config('leases.ghana.currency.symbol', 'GH₵') . ' '
            . number_format($this->balance, 2);
    }

    /**
     * ✅ NEW: Safe-formatted last payment date.
     * Works whether the column is cast or was written as a raw string.
     */
    public function getFormattedLastPaymentAtAttribute(): ?string
    {
        if (!$this->last_payment_at) return null;

        return $this->last_payment_at instanceof Carbon
            ? $this->last_payment_at->format('F j, Y')
            : Carbon::parse($this->last_payment_at)->format('F j, Y');
    }

    /**
     * ✅ NEW: Safe-formatted issue date.
     */
    public function getFormattedIssueDateAttribute(): ?string
    {
        if (!$this->issue_date) return null;

        return $this->issue_date instanceof Carbon
            ? $this->issue_date->format('F j, Y')
            : Carbon::parse($this->issue_date)->format('F j, Y');
    }

    // ========== HELPERS ==========

    /**
     * Mark as fully or partially paid.
     */
    public function markAsPaid(?float $amount = null): void
    {
        $this->amount_paid = $amount ?? $this->amount;
        $this->status = $this->amount_paid >= $this->amount
            ? self::STATUS_PAID
            : self::STATUS_PARTIAL;

        $this->paid_at = $this->status === self::STATUS_PAID
            ? now()
            : $this->paid_at;

        $this->last_payment_at = now();

        $this->save();
    }

    /**
     * ✅ NEW: Record a partial/full payment in one call.
     * Updates amount_paid, method, reference, and status atomically.
     */
    public function recordPayment(
        float $amount,
        ?string $method = null,
        ?string $reference = null,
        ?Carbon $paidAt = null
    ): void {
        $this->amount_paid = (float) $this->amount_paid + $amount;
        $this->payment_method = $method ?? $this->payment_method;
        $this->payment_reference = $reference ?? $this->payment_reference;
        $this->last_payment_at = $paidAt ?? now();

        if ($this->amount_paid >= $this->amount) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = $this->paid_at ?? now();
        } else {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();
    }

    /**
     * ✅ NEW: Void the invoice with a reason.
     */
    public function void(string $reason, ?int $voidedBy = null): void
    {
        $this->status = self::STATUS_VOID;
        $this->void_reason = $reason;
        $this->voided_at = now();
        $this->voided_by = $voidedBy ?? auth()->id();
        $this->save();
    }

    /**
     * ✅ NEW: Recompute status from amount_paid.
     * Useful after data repairs or external payment syncs.
     */
    public function recalculateStatus(): string
    {
        if ($this->status === self::STATUS_VOID) {
            return $this->status;
        }

        $amount = (float) $this->amount;
        $paid   = (float) $this->amount_paid;

        if ($paid >= $amount) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = $this->paid_at ?? now();
        } elseif ($paid > 0) {
            $this->status = self::STATUS_PARTIAL;
            $this->paid_at = null;
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = self::STATUS_OVERDUE;
            $this->paid_at = null;
        } else {
            $this->status = self::STATUS_PENDING;
            $this->paid_at = null;
        }

        $this->save();
        return $this->status;
    }

    // ========== STATIC HELPERS ==========

    /**
     * ✅ NEW: Reference prefixes by invoice type.
     */
    public static function referencePrefix(string $type): string
    {
        return match ($type) {
            self::TYPE_ADVANCE_RENT      => 'ADV',
            self::TYPE_MONTHLY_RENT      => 'MR',
            self::TYPE_SECURITY_DEPOSIT  => 'DEP',
            self::TYPE_UTILITY_DEPOSIT   => 'UTIL',
            self::TYPE_LATE_FEE          => 'LATE',
            self::TYPE_EARLY_TERMINATION => 'TERM',
            default                      => 'INV',
        };
    }

    /**
     * ✅ NEW: All valid invoice types.
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_ADVANCE_RENT      => 'Advance Rent',
            self::TYPE_MONTHLY_RENT      => 'Monthly Rent',
            self::TYPE_SECURITY_DEPOSIT  => 'Security Deposit',
            self::TYPE_UTILITY_DEPOSIT   => 'Utility Deposit',
            self::TYPE_LATE_FEE          => 'Late Fee',
            self::TYPE_EARLY_TERMINATION => 'Early Termination',
            self::TYPE_OTHER             => 'Other',
        ];
    }

    /**
     * ✅ NEW: All valid statuses.
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_PAID    => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_VOID    => 'Void',
        ];
    }
}