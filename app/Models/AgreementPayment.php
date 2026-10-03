<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementPayment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'agreement_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_billing_record_id',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'transaction_id',
        'payment_date',
        'receipt_path',
        'status',
        'notes',
        'confirmation_notes',
        'rejection_reason',
        'recorded_by',
        'recorded_at',
        'confirmed_by',
        'confirmed_at',
        'rejected_by',
        'rejected_at',
        'submitted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'date',
        'recorded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Payment statuses
     */
    const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    /**
     * Payment methods
     */
    const METHOD_MTN = 'mtn';
    const METHOD_TELECEL = 'telecel';
    const METHOD_AIRTELTIGO = 'airteltigo';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH = 'cash';
    const METHOD_OTHER = 'other';

    /**
     * Get all status options
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING_CONFIRMATION => 'Pending Confirmation',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * Get all payment method options
     */
    public static function getPaymentMethodOptions(): array
    {
        return [
            self::METHOD_MTN => 'MTN Mobile Money',
            self::METHOD_TELECEL => 'Telecel Cash',
            self::METHOD_AIRTELTIGO => 'AirtelTigo Money',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CASH => 'Cash',
            self::METHOD_OTHER => 'Other',
        ];
    }

    /**
     * Get the billing agreement.
     */
    public function billingAgreement(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'admin_billing_record_id');
    }

    /**
     * Get the agreement (alias for billingAgreement).
     * This is added to fix the error: Call to undefined method App\Models\AgreementPayment::agreement()
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'admin_billing_record_id');
    }

    /**
     * Get the user who recorded the payment.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who confirmed the payment.
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get the user who rejected the payment.
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who submitted the payment (for super admins).
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Check if payment is pending confirmation
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRMATION;
    }

    /**
     * Check if payment is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if payment is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get formatted status with badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_CONFIRMED => 'badge bg-success',
            self::STATUS_PENDING_CONFIRMATION => 'badge bg-warning',
            self::STATUS_REJECTED => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }

    /**
     * Get payment method display name
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return self::getPaymentMethodOptions()[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    /**
     * Get payment amount with currency symbol
     */
    public function getFormattedAmountAttribute(): string
    {
        // Get currency from agreement
        $agreement = $this->billingAgreement;
        $currency = $agreement->currency ?? 'GHS';
        
        $symbols = [
            'GHS' => 'GH₵',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($this->amount_paid, 2);
    }

    /**
     * Get receipt URL
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }
        return asset('storage/' . $this->receipt_path);
    }

    /**
     * Get receipt file name
     */
    public function getReceiptFileNameAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }
        return basename($this->receipt_path);
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_CONFIRMATION);
    }

    /**
     * Scope a query to only include confirmed payments.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope a query to only include rejected payments.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}