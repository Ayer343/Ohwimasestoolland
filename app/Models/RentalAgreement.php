<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class RentalAgreement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'property_id',
        'landlord_id',
        'agreement_number',
        'title',
        'description',
        'monthly_rent',
        'security_deposit',

        // Deposit installment fields
        'deposit_payment_method',
        'deposit_installment_months',
        'deposit_monthly_installment',
        'deposit_collected_so_far',
        'deposit_fully_paid_at',
        'deposit_refunded_amount',
        'deposit_refunded_at',

        // ✅ GHANA: Advance rent fields
        'advance_rent_months',
        'advance_rent_amount',
        'advance_rent_paid_at',
        'advance_rent_period_start',
        'advance_rent_period_end',
        'payment_frequency',
        'first_monthly_payment_date',
        'advance_rent_compliance_status',
        'advance_rent_acknowledged_at',

        // Late fees
        'late_fee_percentage',
        'late_fee_fixed',
        'grace_period_days',
        'payment_due_day',

        // Term
        'start_date',
        'end_date',
        'duration_months',
        'lease_type',
        'is_renewable',
        'renewal_notice_days',
        'renewal_rent_increase_percent',

        // Status
        'status',
        'is_auto_renew',
        'has_early_termination',
        'early_termination_fee',

        // Responsibilities
        'utilities_included',
        'tenant_responsibilities',
        'landlord_responsibilities',
        'special_terms',
        'renewal_terms',
        'house_rules',
        'property_condition',
        'terms',

        // Files
        'agreement_file_path',
        'signed_file_path',

        // Signatures
        'signed_at',
        'landlord_signed_at',
        'landlord_signed_by',
        'landlord_signature',
        'landlord_signature_type',
        'landlord_witness_signature',
        'landlord_witness_signed_at',
        'tenant_signed_at',
        'tenant_signed_by',
        'tenant_signature',
        'tenant_signature_type',
        'tenant_witness_signature',
        'tenant_witness_signed_at',
        'signatures',
        'witnesses',

        // Guarantors / contacts
        'guarantors',
        'emergency_contacts',

        // Financial tracking
        'total_rent_paid',
        'total_deposit_held',
        'last_rent_paid_date',

        // Termination
        'terminated_at',
        'termination_reason',
        'terminated_by',
        'termination_data',

        // Meta
        'created_by',
        'created_by_type',
        'approved_by',
        'approved_at',
        'notes',
        'metadata',

        // Renewal link
        'previous_lease_id',
        'previous_agreement_id',
    ];

    protected $casts = [
        // Dates
        'start_date'                => 'date',
        'end_date'                  => 'date',
        'last_rent_paid_date'       => 'date',

        // Timestamps
        'signed_at'                 => 'datetime',
        'landlord_signed_at'        => 'datetime',
        'landlord_witness_signed_at'=> 'datetime',
        'tenant_signed_at'          => 'datetime',
        'tenant_witness_signed_at'  => 'datetime',
        'terminated_at'             => 'datetime',
        'approved_at'               => 'datetime',
        'deposit_fully_paid_at'     => 'datetime',
        'deposit_refunded_at'       => 'datetime',

        // ✅ GHANA: Advance rent casts
        'advance_rent_paid_at'          => 'datetime',
        'advance_rent_period_start'     => 'date',
        'advance_rent_period_end'       => 'date',
        'first_monthly_payment_date'    => 'date',
        'advance_rent_acknowledged_at'  => 'datetime',

        // Money
        'monthly_rent'                 => 'decimal:2',
        'security_deposit'             => 'decimal:2',
        'deposit_monthly_installment'  => 'decimal:2',
        'deposit_collected_so_far'     => 'decimal:2',
        'deposit_refunded_amount'      => 'decimal:2',
        'late_fee_percentage'          => 'decimal:2',
        'late_fee_fixed'               => 'decimal:2',
        'early_termination_fee'        => 'decimal:2',
        'total_rent_paid'              => 'decimal:2',
        'total_deposit_held'           => 'decimal:2',
        'renewal_rent_increase_percent'=> 'decimal:2',

        // ✅ GHANA: Advance rent money
        'advance_rent_amount'          => 'decimal:2',

        // Booleans
        'is_renewable'                 => 'boolean',
        'is_auto_renew'                => 'boolean',
        'has_early_termination'        => 'boolean',

        // Arrays
        'utilities_included'           => 'array',
        'tenant_responsibilities'      => 'array',
        'landlord_responsibilities'    => 'array',
        'special_terms'                => 'array',
        'house_rules'                  => 'array',
        'property_condition'           => 'array',
        'signatures'                   => 'array',
        'witnesses'                    => 'array',
        'guarantors'                   => 'array',
        'emergency_contacts'           => 'array',
        'landlord_witness_signature'   => 'array',
        'tenant_witness_signature'     => 'array',
        'termination_data'             => 'array',
        'metadata'                     => 'array',
    ];

    // ========== STATUS CONSTANTS ==========
    const STATUS_DRAFT              = 'draft';
    const STATUS_PENDING            = 'pending';
    const STATUS_PENDING_LANDLORD   = 'pending_landlord';
    const STATUS_PENDING_TENANT     = 'pending_tenant';
    const STATUS_PENDING_SIGNATURE  = 'pending_signature';
    const STATUS_ACTIVE             = 'active';
    const STATUS_EXPIRED            = 'expired';
    const STATUS_TERMINATED         = 'terminated';
    const STATUS_CANCELLED          = 'cancelled';
    const STATUS_COMPLETED          = 'completed';
    const STATUS_RENEWED            = 'renewed';

    // ========== SIGNATURE CONSTANTS ==========
    const SIGNATURE_TYPE_DIGITAL    = 'digital';
    const SIGNATURE_TYPE_UPLOAD     = 'upload';
    const SIGNATURE_TYPE_ELECTRONIC = 'electronic';

    // ========== DEPOSIT CONSTANTS ==========
    const DEPOSIT_PAYMENT_UPFRONT     = 'upfront';
    const DEPOSIT_PAYMENT_INSTALLMENT = 'installment';

    // ========== WITNESS CONSTANTS ==========
    const WITNESS_PARTY_LANDLORD = 'landlord';
    const WITNESS_PARTY_TENANT   = 'tenant';

    // ========== ✅ GHANA: LEASE TYPE CONSTANTS ==========
    const LEASE_TYPE_FIXED         = 'fixed';
    const LEASE_TYPE_MONTH_TO_MONTH = 'month_to_month';

    // ========== ✅ GHANA: PAYMENT FREQUENCY CONSTANTS ==========
    const PAYMENT_FREQUENCY_MONTHLY      = 'monthly';
    const PAYMENT_FREQUENCY_ADVANCE_ONLY = 'advance_only';

    // ========== ✅ GHANA: COMPLIANCE CONSTANTS ==========
    const COMPLIANCE_COMPLIANT          = 'compliant';
    const COMPLIANCE_EXCEEDS_LEGAL_LIMIT = 'exceeds_legal_limit';

    // ========== ✅ GHANA: PHASE CONSTANTS ==========
    const PHASE_ADVANCE = 'advance';
    const PHASE_MONTHLY = 'monthly';

    // ========== RELATIONSHIPS ==========

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function terminator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    public function landlordSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_signed_by');
    }

    public function tenantSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_signed_by');
    }

    public function previousLease(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'previous_lease_id');
    }

    public function previousAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'previous_agreement_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(RentalAgreement::class, 'previous_lease_id');
    }

    /**
     * ✅ INVOICE: Invoices attached to this lease.
     * Uses PropertyUnitInvoice instead of the generic Invoice model.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(PropertyUnitInvoice::class, 'lease_id');
    }

    /**
     * ✅ INVOICE: Outstanding invoices only.
     */
    public function outstandingInvoices(): HasMany
    {
        return $this->invoices()->whereIn('status', [
            PropertyUnitInvoice::STATUS_PENDING,
            PropertyUnitInvoice::STATUS_PARTIAL,
            PropertyUnitInvoice::STATUS_OVERDUE,
        ]);
    }

    /**
     * ✅ INVOICE: Advance-rent invoice (typically just one).
     */
    public function advanceRentInvoice()
    {
        return $this->invoices()
            ->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT)
            ->first();
    }

    /**
     * ✅ INVOICE: Monthly rent invoices ordered by due date.
     */
    public function monthlyRentInvoices(): HasMany
    {
        return $this->invoices()
            ->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT)
            ->orderBy('due_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'rental_agreement_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RentalAgreementDocument::class, 'rental_agreement_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(RentalAgreementRevision::class, 'rental_agreement_id');
    }

    public function depositPayments(): HasMany
    {
        return $this->hasMany(DepositPayment::class, 'rental_agreement_id');
    }

    // ========== MUTATORS & ACCESSORS ==========

    public function setStatusAttribute($value)
    {
        $allowedStatuses = self::getStatuses();
        if (!in_array($value, $allowedStatuses)) {
            throw new \InvalidArgumentException(
                "Invalid status: '{$value}'. Allowed values: " . implode(', ', $allowedStatuses)
            );
        }
        $this->attributes['status'] = $value;
    }

    public function getFormattedMonthlyRentAttribute(): string
    {
        return 'GHS ' . number_format($this->monthly_rent, 2);
    }

    public function getFormattedSecurityDepositAttribute(): string
    {
        return 'GHS ' . number_format($this->security_deposit, 2);
    }

    public function getFormattedDepositCollectedAttribute(): string
    {
        return 'GHS ' . number_format($this->deposit_collected_so_far, 2);
    }

    public function getFormattedRemainingDepositAttribute(): string
    {
        $remaining = $this->security_deposit - $this->deposit_collected_so_far;
        return 'GHS ' . number_format(max(0, $remaining), 2);
    }

    public function getRemainingDepositAttribute(): float
    {
        return max(0, $this->security_deposit - $this->deposit_collected_so_far);
    }

    public function getIsDepositFullyPaidAttribute(): bool
    {
        return $this->deposit_collected_so_far >= $this->security_deposit;
    }

    public function getIsDepositInstallmentAttribute(): bool
    {
        return $this->deposit_payment_method === self::DEPOSIT_PAYMENT_INSTALLMENT;
    }

    public function getCurrentMonthlyPaymentAttribute(): float
    {
        $payment = $this->monthly_rent;

        if ($this->is_deposit_installment && !$this->is_deposit_fully_paid) {
            $monthsSinceStart = now()->diffInMonths($this->start_date);
            if ($monthsSinceStart < $this->deposit_installment_months) {
                $payment += $this->deposit_monthly_installment;
            }
        }

        return $payment;
    }

    public function getFormattedCurrentMonthlyPaymentAttribute(): string
    {
        return 'GHS ' . number_format($this->current_monthly_payment, 2);
    }

    public function getIsDepositInstallmentPeriodActiveAttribute(): bool
    {
        if (!$this->is_deposit_installment || $this->is_deposit_fully_paid) {
            return false;
        }
        $monthsSinceStart = now()->diffInMonths($this->start_date);
        return $monthsSinceStart < $this->deposit_installment_months;
    }

    public function getRemainingDepositInstallmentsAttribute(): int
    {
        if (!$this->is_deposit_installment || $this->is_deposit_fully_paid) {
            return 0;
        }
        $monthsSinceStart = now()->diffInMonths($this->start_date);
        return max(0, $this->deposit_installment_months - $monthsSinceStart);
    }

    public function getFormattedTotalRentAttribute(): string
    {
        return 'GHS ' . number_format($this->calculateTotalRent(), 2);
    }

    // ========== SIGNATURE STATE ACCESSORS ==========

    public function getIsFullySignedAttribute(): bool
    {
        return !empty($this->landlord_signed_at) && !empty($this->tenant_signed_at);
    }

    public function getIsLandlordSignedAttribute(): bool
    {
        return !empty($this->landlord_signed_at);
    }

    public function getIsTenantSignedAttribute(): bool
    {
        return !empty($this->tenant_signed_at);
    }

    public function getHasLandlordWitnessAttribute(): bool
    {
        return !empty($this->landlord_witness_signature) && !empty($this->landlord_witness_signed_at);
    }

    public function getHasTenantWitnessAttribute(): bool
    {
        return !empty($this->tenant_witness_signature) && !empty($this->tenant_witness_signed_at);
    }

    public function getHasWitnessSignaturesAttribute(): bool
    {
        return $this->has_landlord_witness || $this->has_tenant_witness;
    }

    public function getLandlordWitnessDetailsAttribute(): ?array
    {
        if (!$this->has_landlord_witness) {
            return null;
        }
        $witness = $this->landlord_witness_signature;
        return is_array($witness) ? $witness : [
            'name'      => $witness,
            'signed_at' => $this->landlord_witness_signed_at,
        ];
    }

    public function getTenantWitnessDetailsAttribute(): ?array
    {
        if (!$this->has_tenant_witness) {
            return null;
        }
        $witness = $this->tenant_witness_signature;
        return is_array($witness) ? $witness : [
            'name'      => $witness,
            'signed_at' => $this->tenant_witness_signed_at,
        ];
    }

    public function getAllWitnessesAttribute(): array
    {
        $witnesses = [];

        if ($landlordWitness = $this->landlord_witness_details) {
            $landlordWitness['party'] = self::WITNESS_PARTY_LANDLORD;
            $witnesses[] = $landlordWitness;
        }
        if ($tenantWitness = $this->tenant_witness_details) {
            $tenantWitness['party'] = self::WITNESS_PARTY_TENANT;
            $witnesses[] = $tenantWitness;
        }

        return $witnesses;
    }

    // ========== ✅ GHANA: ADVANCE RENT ACCESSORS ==========

    /**
     * ✅ GHANA: The current payment phase — 'advance' or 'monthly'.
     */
    public function getCurrentPhaseAttribute(): string
    {
        if (!$this->advance_rent_period_end) {
            // No advance structure — treat as monthly
            return self::PHASE_MONTHLY;
        }

        return now()->lt($this->advance_rent_period_end)
            ? self::PHASE_ADVANCE
            : self::PHASE_MONTHLY;
    }

    /**
     * ✅ GHANA: Are we currently inside the advance-rent period?
     */
    public function getIsInAdvancePhaseAttribute(): bool
    {
        return $this->current_phase === self::PHASE_ADVANCE;
    }

    /**
     * ✅ GHANA: Are we in the monthly phase (advance period elapsed)?
     */
    public function getIsInMonthlyPhaseAttribute(): bool
    {
        return $this->current_phase === self::PHASE_MONTHLY;
    }

    /**
     * ✅ GHANA: Next payment due date.
     * - In advance phase: the first monthly payment date (start of monthly phase).
     * - In monthly phase: the next monthly due date on/after today.
     */
    public function getNextPaymentDueDateAttribute(): ?Carbon
    {
        if (!$this->first_monthly_payment_date) {
            return null;
        }

        if ($this->current_phase === self::PHASE_ADVANCE) {
            return $this->first_monthly_payment_date->copy();
        }

        // Monthly phase — find next occurrence of payment_due_day
        $next = $this->first_monthly_payment_date->copy();
        $dueDay = (int) ($this->payment_due_day ?: 1);

        while ($next->lt(now()->startOfDay())) {
            $next->addMonthNoOverflow()->setDay(min($dueDay, $next->daysInMonth));
        }

        return $next;
    }

    /**
     * ✅ GHANA: Amount due at next payment.
     */
    public function getNextPaymentAmountAttribute(): float
    {
        if ($this->current_phase === self::PHASE_ADVANCE) {
            // Advance already paid; next charge is one month's rent
            return (float) $this->monthly_rent;
        }

        // Monthly phase: rent + deposit installment (if still active)
        return (float) $this->current_monthly_payment;
    }

    /**
     * ✅ GHANA: Months elapsed in the current advance period.
     */
    public function getAdvanceMonthsElapsedAttribute(): int
    {
        if (!$this->advance_rent_period_start) {
            return 0;
        }
        return max(0, $this->advance_rent_period_start->diffInMonths(now()));
    }

    /**
     * ✅ GHANA: Months remaining in the advance period.
     */
    public function getAdvanceMonthsRemainingAttribute(): int
    {
        if (!$this->advance_rent_period_end || $this->is_in_monthly_phase) {
            return 0;
        }
        return max(0, now()->diffInMonths($this->advance_rent_period_end));
    }

    /**
     * ✅ GHANA: Is this lease legally compliant on advance rent?
     */
    public function getIsAdvanceRentCompliantAttribute(): bool
    {
        return $this->advance_rent_compliance_status === self::COMPLIANCE_COMPLIANT;
    }

    /**
     * ✅ GHANA: Human-readable compliance label.
     */
    public function getAdvanceRentComplianceLabelAttribute(): string
    {
        return $this->is_advance_rent_compliant
            ? 'Compliant'
            : 'Exceeds Legal Limit';
    }

    /**
     * ✅ GHANA: Formatted advance rent amount.
     */
    public function getFormattedAdvanceRentAttribute(): string
    {
        return 'GHS ' . number_format($this->advance_rent_amount ?? 0, 2);
    }

    /**
     * ✅ GHANA: Whether the lease uses a monthly post-advance payment structure.
     */
    public function getHasMonthlyPhaseAttribute(): bool
    {
        return $this->payment_frequency === self::PAYMENT_FREQUENCY_MONTHLY
            && !empty($this->first_monthly_payment_date);
    }

    /**
     * ✅ GHANA: Whether the entire lease is paid as advance only.
     */
    public function getIsAdvanceOnlyAttribute(): bool
    {
        return $this->payment_frequency === self::PAYMENT_FREQUENCY_ADVANCE_ONLY;
    }

    // ========== LATE FEE LOGIC ==========

    public function calculateLateFee(?float $rentAmount = null): float
    {
        $rent = $rentAmount ?? $this->monthly_rent;
        $fee = 0;

        if ($this->late_fee_percentage > 0) {
            $fee += ($rent * $this->late_fee_percentage / 100);
        }
        if ($this->late_fee_fixed > 0) {
            $fee += $this->late_fee_fixed;
        }

        return $fee;
    }

    public function getFormattedLateFeeAttribute(): string
    {
        $fee = $this->calculateLateFee();
        return $fee > 0 ? 'GHS ' . number_format($fee, 2) : 'None';
    }

    public function getLateFeeDescriptionAttribute(): string
    {
        $parts = [];

        if ($this->late_fee_percentage > 0) {
            $parts[] = $this->late_fee_percentage . '% of rent';
        }
        if ($this->late_fee_fixed > 0) {
            $parts[] = 'GHS ' . number_format($this->late_fee_fixed, 2) . ' fixed';
        }
        if (empty($parts)) {
            return 'No late fees';
        }

        $description = implode(' + ', $parts);

        if ($this->grace_period_days > 0) {
            $description .= ' after ' . $this->grace_period_days . ' days grace period';
        }

        return $description;
    }

    // ========== ✅ INVOICE: FINANCIAL ACCESSORS ==========

    /**
     * ✅ INVOICE: Total invoiced across all invoices for this lease.
     */
    public function getTotalInvoicedAttribute(): float
    {
        return (float) $this->invoices()->sum('amount');
    }

    /**
     * ✅ INVOICE: Total paid across all invoices.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->invoices()->sum('amount_paid');
    }

    /**
     * ✅ INVOICE: Outstanding balance.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, $this->total_invoiced - $this->total_paid);
    }

    /**
     * ✅ INVOICE: Count of overdue invoices.
     */
    public function getOverdueInvoiceCountAttribute(): int
    {
        return $this->invoices()
            ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
            ->count();
    }

    /**
     * ✅ INVOICE: Formatted outstanding balance.
     */
    public function getFormattedOutstandingBalanceAttribute(): string
    {
        return 'GHS ' . number_format($this->outstanding_balance, 2);
    }

    // ========== BUSINESS LOGIC METHODS ==========

    public static function generateAgreementNumber(): string
    {
        $prefix = 'RA';
        $date = date('Ymd');
        $last = self::where('agreement_number', 'like', "{$prefix}{$date}%")
            ->orderBy('agreement_number', 'desc')
            ->value('agreement_number');

        if ($last) {
            $sequence = intval(substr($last, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s%s%04d', $prefix, $date, $sequence);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->start_date <= now()
            && (!$this->end_date || $this->end_date >= now());
    }

    public function isPendingSignature(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_LANDLORD,
            self::STATUS_PENDING_TENANT,
            self::STATUS_PENDING_SIGNATURE,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->end_date
            && $this->end_date->isPast()
            && $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function calculateTotalRent(): float
    {
        if (!$this->duration_months) {
            return 0;
        }
        return $this->monthly_rent * $this->duration_months;
    }

    public function getRemainingMonths(): int
    {
        if (!$this->isActive() || !$this->end_date) {
            return 0;
        }
        return max(0, now()->diffInMonths($this->end_date, false));
    }

    public function getDaysUntilExpiration(): int
    {
        if (!$this->isActive() || !$this->end_date) {
            return 0;
        }
        return now()->diffInDays($this->end_date, false);
    }

    /**
     * Record a deposit payment.
     */
    public function recordDepositPayment(float $amount, string $paymentMethod, ?string $reference = null, ?string $notes = null): array
    {
        $newTotal = $this->deposit_collected_so_far + $amount;
        $wasFullyPaid = $this->is_deposit_fully_paid;

        $this->update([
            'deposit_collected_so_far' => $newTotal,
            'deposit_fully_paid_at'    => $newTotal >= $this->security_deposit ? now() : null,
        ]);

        $result = [
            'previous_total'          => $this->deposit_collected_so_far - $amount,
            'amount_paid'             => $amount,
            'new_total'               => $newTotal,
            'remaining'               => max(0, $this->security_deposit - $newTotal),
            'is_fully_paid'           => $newTotal >= $this->security_deposit,
            'just_became_fully_paid'  => !$wasFullyPaid && ($newTotal >= $this->security_deposit),
        ];

        \Illuminate\Support\Facades\Log::info('Deposit payment recorded', [
            'rental_agreement_id' => $this->id,
            'amount'              => $amount,
            'payment_method'      => $paymentMethod,
            'reference'           => $reference,
            'notes'               => $notes,
            'result'              => $result,
        ]);

        return $result;
    }

    // ========== SIGNING METHODS ==========

    public function signAsLandlord(User $user, string $signatureData, string $signatureType = self::SIGNATURE_TYPE_DIGITAL, ?array $witnessData = null): void
    {
        $updateData = [
            'landlord_signed_at'      => now(),
            'landlord_signed_by'      => $user->id,
            'landlord_signature'      => $signatureData,
            'landlord_signature_type' => $signatureType,
            'status'                  => $this->tenant_signed_at
                ? self::STATUS_ACTIVE
                : self::STATUS_PENDING_TENANT,
        ];

        if ($witnessData) {
            $updateData['landlord_witness_signature'] = $witnessData;
            $updateData['landlord_witness_signed_at'] = now();
        }

        $this->update($updateData);
    }

    public function signAsTenant(User $user, string $signatureData, string $signatureType = self::SIGNATURE_TYPE_DIGITAL, ?array $witnessData = null): void
    {
        $updateData = [
            'tenant_signed_at'      => now(),
            'tenant_signed_by'      => $user->id,
            'tenant_signature'      => $signatureData,
            'tenant_signature_type' => $signatureType,
            'status'                => $this->landlord_signed_at
                ? self::STATUS_ACTIVE
                : self::STATUS_PENDING_LANDLORD,
        ];

        if ($witnessData) {
            $updateData['tenant_witness_signature'] = $witnessData;
            $updateData['tenant_witness_signed_at'] = now();
        }

        $this->update($updateData);
    }

    public function addLandlordWitness(array $witnessData): void
    {
        $this->update([
            'landlord_witness_signature' => $witnessData,
            'landlord_witness_signed_at' => now(),
        ]);
    }

    public function addTenantWitness(array $witnessData): void
    {
        $this->update([
            'tenant_witness_signature' => $witnessData,
            'tenant_witness_signed_at' => now(),
        ]);
    }

    /**
     * Terminate the agreement.
     */
    public function terminate(string $reason, User $terminatedBy, ?array $details = null): void
    {
        $this->update([
            'status'             => self::STATUS_TERMINATED,
            'terminated_at'      => now(),
            'termination_reason' => $reason,
            'terminated_by'      => $terminatedBy->id,
            'end_date'           => now(),
            'termination_data'   => array_merge($this->termination_data ?? [], [
                'termination_details' => $details,
                'terminated_by_type'  => $terminatedBy->type ?? $terminatedBy->getRole(),
            ]),
        ]);
    }

    // ========== ✅ GHANA: RENEWAL ==========

    /**
     * ✅ GHANA: Renew the agreement with proper advance-rent handling.
     * Renewal legal cap = 3 months (Rent Act 1963).
     */
    public function renew(
        int $durationMonths,
        ?float $newMonthlyRent = null,
        ?int $renewalAdvanceMonths = null,
        ?string $renewalPaymentFrequency = null,
        ?array $overrides = []
    ): self {
        $newRent = $newMonthlyRent ?? $this->monthly_rent;
        $startDate = $this->end_date
            ? $this->end_date->copy()->addDay()
            : now()->addDay();

        // ✅ GHANA: Renewal advance defaults
        $advanceMonths = $renewalAdvanceMonths ?? min(3, $this->advance_rent_months ?? 1);
        $paymentFrequency = $renewalPaymentFrequency ?? $this->payment_frequency ?? self::PAYMENT_FREQUENCY_MONTHLY;

        $advanceAmount      = $newRent * $advanceMonths;
        $advancePeriodStart = $startDate->copy();
        $advancePeriodEnd   = $startDate->copy()->addMonths($advanceMonths);

        $firstMonthlyPayment = $paymentFrequency === self::PAYMENT_FREQUENCY_MONTHLY
            ? $advancePeriodEnd->copy()->setDay((int) ($this->payment_due_day ?: 1))
            : null;

        // ✅ GHANA: Renewal cap is 3 months
        $complianceStatus = $advanceMonths <= 3
            ? self::COMPLIANCE_COMPLIANT
            : self::COMPLIANCE_EXCEEDS_LEGAL_LIMIT;

        $renewedAgreement = self::create(array_merge([
            'unit_id'      => $this->unit_id,
            'tenant_id'    => $this->tenant_id,
            'property_id'  => $this->property_id,
            'landlord_id'  => $this->landlord_id,
            'title'        => $this->title . ' (Renewal)',
            'description'  => $this->description,
            'monthly_rent' => $newRent,
            'security_deposit' => $this->security_deposit,
            'deposit_payment_method'      => $this->deposit_payment_method,
            'deposit_installment_months'  => $this->deposit_installment_months,
            'deposit_monthly_installment' => $this->deposit_monthly_installment,
            'deposit_collected_so_far'    => 0,

            // ✅ GHANA: Advance rent for renewal
            'advance_rent_months'            => $advanceMonths,
            'advance_rent_amount'            => $advanceAmount,
            'advance_rent_paid_at'           => now(),
            'advance_rent_period_start'      => $advancePeriodStart,
            'advance_rent_period_end'        => $advancePeriodEnd,
            'payment_frequency'              => $paymentFrequency,
            'first_monthly_payment_date'     => $firstMonthlyPayment,
            'advance_rent_compliance_status' => $complianceStatus,

            'late_fee_percentage' => $this->late_fee_percentage,
            'late_fee_fixed'      => $this->late_fee_fixed,
            'grace_period_days'   => $this->grace_period_days,
            'payment_due_day'     => $this->payment_due_day,
            'start_date'          => $startDate,
            'end_date'            => $startDate->copy()->addMonths($durationMonths),
            'duration_months'     => $durationMonths,
            'lease_type'          => $this->lease_type,
            'is_renewable'        => $this->is_renewable,
            'renewal_notice_days' => $this->renewal_notice_days,
            'renewal_rent_increase_percent' => $this->renewal_rent_increase_percent,
            'status'              => self::STATUS_PENDING,
            'is_auto_renew'       => $this->is_auto_renew,
            'has_early_termination' => $this->has_early_termination,
            'early_termination_fee' => $this->early_termination_fee,
            'utilities_included'  => $this->utilities_included,
            'tenant_responsibilities' => $this->tenant_responsibilities,
            'landlord_responsibilities' => $this->landlord_responsibilities,
            'special_terms'       => $this->special_terms,
            'house_rules'         => $this->house_rules,
            'property_condition'  => $this->property_condition,
            'created_by'          => $this->created_by,
            'created_by_type'     => $this->created_by_type,
            'previous_lease_id'   => $this->id,
            'previous_agreement_id' => $this->id,
        ], $overrides));

        $this->update(['status' => self::STATUS_COMPLETED]);

        return $renewedAgreement;
    }

    // ========== WITNESS HELPERS ==========

    public static function parseWitnessDataFromRequest(array $data): ?array
    {
        if (empty($data['witness_name']) || empty($data['witness_signature_data'])) {
            return null;
        }

        return [
            'name'                   => $data['witness_name'],
            'relationship'           => $data['witness_relationship'] ?? null,
            'role'                   => $data['witness_role'] ?? 'Witness',
            'email'                  => $data['witness_email'] ?? null,
            'phone'                  => $data['witness_phone'] ?? null,
            'signature'              => $data['witness_signature_data'],
            'signature_type'         => $data['witness_signature_type'] ?? self::SIGNATURE_TYPE_DIGITAL,
            'signed_at'              => now(),
            'witness_for'            => $data['witness_for'] ?? null,
            'witnessed_by_user_id'   => $data['witnessed_by_user_id'] ?? null,
            'witnessed_by_user_name' => $data['witnessed_by_user_name'] ?? null,
        ];
    }

    public static function validateWitnessData(array $data): bool
    {
        $required = ['name', 'signature', 'signature_type'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        $validTypes = [
            self::SIGNATURE_TYPE_DIGITAL,
            self::SIGNATURE_TYPE_UPLOAD,
            self::SIGNATURE_TYPE_ELECTRONIC,
        ];

        return in_array($data['signature_type'], $validTypes);
    }

    // ========== STATIC OPTIONS ==========

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT             => 'Draft',
            self::STATUS_PENDING           => 'Pending Approval',
            self::STATUS_PENDING_LANDLORD  => 'Pending Landlord Signature',
            self::STATUS_PENDING_TENANT    => 'Pending Tenant Signature',
            self::STATUS_PENDING_SIGNATURE => 'Pending Signature',
            self::STATUS_ACTIVE            => 'Active',
            self::STATUS_EXPIRED           => 'Expired',
            self::STATUS_TERMINATED        => 'Terminated',
            self::STATUS_CANCELLED         => 'Cancelled',
            self::STATUS_COMPLETED         => 'Completed',
            self::STATUS_RENEWED           => 'Renewed',
        ];
    }

    public static function getStatuses(): array
    {
        return array_keys(self::getStatusOptions());
    }

    public static function getSignatureTypeOptions(): array
    {
        return [
            self::SIGNATURE_TYPE_DIGITAL    => 'Digital Signature',
            self::SIGNATURE_TYPE_UPLOAD     => 'Uploaded Signature',
            self::SIGNATURE_TYPE_ELECTRONIC => 'Electronic Signature',
        ];
    }

    public static function getDepositPaymentMethodOptions(): array
    {
        return [
            self::DEPOSIT_PAYMENT_UPFRONT     => 'Pay Upfront',
            self::DEPOSIT_PAYMENT_INSTALLMENT => 'Monthly Installment',
        ];
    }

    public static function getWitnessPartyOptions(): array
    {
        return [
            self::WITNESS_PARTY_LANDLORD => "Landlord's Witness",
            self::WITNESS_PARTY_TENANT   => "Tenant's Witness",
        ];
    }

    // ========== ✅ GHANA: NEW OPTION LISTS ==========

    public static function getLeaseTypeOptions(): array
    {
        return [
            self::LEASE_TYPE_FIXED          => 'Fixed Term',
            self::LEASE_TYPE_MONTH_TO_MONTH => 'Month-to-Month',
        ];
    }

    public static function getPaymentFrequencyOptions(): array
    {
        return [
            self::PAYMENT_FREQUENCY_MONTHLY      => 'Advance + Monthly',
            self::PAYMENT_FREQUENCY_ADVANCE_ONLY => 'Full Advance Only',
        ];
    }

    public static function getAdvanceRentOptions(): array
    {
        return [
            1  => '1 Month (Short tenancy / monthly)',
            3  => '3 Months (Renewal max)',
            6  => '6 Months (Legal max for new tenancy)',
            12 => '12 Months (1 Year — market practice)',
            24 => '24 Months (2 Years — market practice)',
            36 => '36 Months (3 Years — market practice)',
        ];
    }

    public static function getComplianceStatusOptions(): array
    {
        return [
            self::COMPLIANCE_COMPLIANT           => 'Compliant',
            self::COMPLIANCE_EXCEEDS_LEGAL_LIMIT => 'Exceeds Legal Limit',
        ];
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($days))
            ->where('end_date', '>=', now());
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByLandlord($query, $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    public function scopeUnsigned($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('landlord_signed_at')
              ->orWhereNull('tenant_signed_at');
        });
    }

    public function scopePendingLandlordSignature($query)
    {
        return $query->where('status', self::STATUS_PENDING_LANDLORD)
            ->whereNull('landlord_signed_at');
    }

    public function scopePendingTenantSignature($query)
    {
        return $query->where('status', self::STATUS_PENDING_TENANT)
            ->whereNull('tenant_signed_at');
    }

    public function scopeWithWitnessSignatures($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('landlord_witness_signature')
              ->orWhereNotNull('tenant_witness_signature');
        });
    }

    public function scopeWithoutWitnessSignatures($query)
    {
        return $query->whereNull('landlord_witness_signature')
            ->whereNull('tenant_witness_signature');
    }

    public function scopeWithLandlordWitness($query)
    {
        return $query->whereNotNull('landlord_witness_signature');
    }

    public function scopeWithTenantWitness($query)
    {
        return $query->whereNotNull('tenant_witness_signature');
    }

    public function scopeWithDepositInstallment($query)
    {
        return $query->where('deposit_payment_method', self::DEPOSIT_PAYMENT_INSTALLMENT);
    }

    public function scopeDepositFullyPaid($query)
    {
        return $query->whereRaw('deposit_collected_so_far >= security_deposit');
    }

    public function scopeDepositNotFullyPaid($query)
    {
        return $query->whereRaw('deposit_collected_so_far < security_deposit');
    }

    public function scopeWithOverdueRent($query)
    {
        return $query->active()
            ->whereHas('invoices', function ($q) {
                $q->where('due_date', '<', now())
                  ->where('status', '!=', PropertyUnitInvoice::STATUS_PAID);
            });
    }

    public function scopeNeedsRenewal($query, $noticeDays = 30)
    {
        return $query->active()
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($noticeDays))
            ->where('is_renewable', true);
    }

    public function scopeByPropertyType($query, $propertyTypeId)
    {
        return $query->active()
            ->whereHas('property', function ($q) use ($propertyTypeId) {
                $q->where('property_type_id', $propertyTypeId);
            });
    }

    // ========== ✅ GHANA: NEW SCOPES ==========

    /**
     * ✅ GHANA: Leases currently in the advance-rent phase.
     */
    public function scopeInAdvancePhase($query)
    {
        return $query->whereNotNull('advance_rent_period_end')
            ->where('advance_rent_period_end', '>=', now());
    }

    /**
     * ✅ GHANA: Leases currently in the monthly phase.
     */
    public function scopeInMonthlyPhase($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('advance_rent_period_end')
              ->orWhere('advance_rent_period_end', '<', now());
        })->where('payment_frequency', self::PAYMENT_FREQUENCY_MONTHLY);
    }

    /**
     * ✅ GHANA: Only legally compliant leases.
     */
    public function scopeCompliant($query)
    {
        return $query->where('advance_rent_compliance_status', self::COMPLIANCE_COMPLIANT);
    }

    /**
     * ✅ GHANA: Only leases exceeding the legal advance cap.
     */
    public function scopeExceedingLegalLimit($query)
    {
        return $query->where('advance_rent_compliance_status', self::COMPLIANCE_EXCEEDS_LEGAL_LIMIT);
    }

    /**
     * ✅ GHANA: Leases with an upcoming monthly payment.
     */
    public function scopeWithUpcomingMonthlyPayment($query, int $days = 7)
    {
        return $query->where('payment_frequency', self::PAYMENT_FREQUENCY_MONTHLY)
            ->whereNotNull('first_monthly_payment_date')
            ->where('first_monthly_payment_date', '<=', now()->addDays($days));
    }

    /**
     * ✅ INVOICE: Leases with outstanding balances.
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->whereHas('invoices', function ($q) {
            $q->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ]);
        });
    }

    // ========== BOOT METHOD ==========

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->agreement_number)) {
                $model->agreement_number = self::generateAgreementNumber();
            }

            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
            }

            // Default late fees
            if (is_null($model->late_fee_percentage)) {
                $model->late_fee_percentage = 0;
            }
            if (is_null($model->late_fee_fixed)) {
                $model->late_fee_fixed = 0;
            }
            if (is_null($model->grace_period_days)) {
                $model->grace_period_days = 0;
            }
            if (is_null($model->deposit_collected_so_far)) {
                $model->deposit_collected_so_far = 0;
            }

            // ✅ GHANA: Defaults for advance rent
            if (is_null($model->advance_rent_months)) {
                $model->advance_rent_months = 1;
            }
            if (is_null($model->payment_frequency)) {
                $model->payment_frequency = self::PAYMENT_FREQUENCY_MONTHLY;
            }
            if (is_null($model->advance_rent_compliance_status)) {
                $model->advance_rent_compliance_status = self::COMPLIANCE_COMPLIANT;
            }

            // ✅ GHANA: Auto-compute derived fields if not explicitly set
            if ($model->monthly_rent && $model->advance_rent_months && !$model->advance_rent_amount) {
                $model->advance_rent_amount = $model->monthly_rent * $model->advance_rent_months;
            }

            if ($model->start_date && $model->advance_rent_months && !$model->advance_rent_period_start) {
                $start = Carbon::parse($model->start_date);
                $model->advance_rent_period_start = $start;
                $model->advance_rent_period_end   = $start->copy()->addMonths($model->advance_rent_months);
            }

            if ($model->payment_frequency === self::PAYMENT_FREQUENCY_MONTHLY
                && $model->advance_rent_period_end
                && !$model->first_monthly_payment_date) {
                $model->first_monthly_payment_date = Carbon::parse($model->advance_rent_period_end)
                    ->setDay((int) ($model->payment_due_day ?: 1));
            }
        });

        static::updating(function ($model) {
            // Auto-activate when both signatures exist
            if ($model->landlord_signed_at
                && $model->tenant_signed_at
                && $model->status !== self::STATUS_ACTIVE) {
                $model->status = self::STATUS_ACTIVE;
            }

            // Auto-mark deposit as fully paid
            if ($model->deposit_collected_so_far >= $model->security_deposit
                && !$model->deposit_fully_paid_at) {
                $model->deposit_fully_paid_at = now();
            }

            // ✅ GHANA: Auto-recompute compliance status if advance months changed
            if ($model->isDirty('advance_rent_months') && $model->isDirty('duration_months')) {
                $advanceMonths  = (int) $model->advance_rent_months;
                $durationMonths = (int) ($model->duration_months ?? 0);

                if ($durationMonths <= 6) {
                    $legalMax = 1;
                } elseif ($model->isDirty('previous_lease_id') || $model->previous_lease_id) {
                    $legalMax = 3;
                } else {
                    $legalMax = 6;
                }

                $model->advance_rent_compliance_status = $advanceMonths <= $legalMax
                    ? self::COMPLIANCE_COMPLIANT
                    : self::COMPLIANCE_EXCEEDS_LEGAL_LIMIT;
            }
        });
    }
}