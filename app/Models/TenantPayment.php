<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantPayment extends Model
{
    use SoftDeletes;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    // Payment provider constants (UPDATED FOR NEW GATEWAYS)
    const PROVIDER_EXPRESSPAY = 'expresspay';
    const PROVIDER_HUBTEL = 'hubtel';
    const PROVIDER_PAYSTACK = 'paystack';
    const PROVIDER_FLUTTERWAVE = 'flutterwave';
    const PROVIDER_MANUAL = 'manual';

    protected $table = 'tenant_payments';

    protected $fillable = [
        'transaction_id',
        'transaction_reference',
        'tenant_id',
        'invoice_id',
        'property_unit_id',
        'payment_provider',
        'amount',
        'currency',
        'status',
        'phone_number',
        'email',
        'payment_date',
        'payment_method',
        'description',
        'notes',
        'metadata',
        'gateway_response',
        'fee_amount',
        'net_amount',
        'refund_amount',
        'refund_reason',
        'processed_at',
        'refunded_at',
        'processed_by',
        'refunded_by',
        'verified_by',
        'verified_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
        'gateway_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = [
        'payment_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $appends = [
        'formatted_amount',
        'provider_display_name',
        'status_badge_class',
        'status_icon',
        'status_display',
        'is_mobile_money',
        'is_online',
        'can_be_cancelled',
        'can_be_refunded',
        'days_pending'
    ];

    /**
     * Get the tenant (user) who made this payment
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the invoice associated with this payment
     */
    public function invoice()
    {
        return $this->belongsTo(TenantInvoice::class, 'invoice_id');
    }

    /**
     * Get the property unit associated with this payment
     */
    public function propertyUnit()
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    /**
     * Get the user who processed this payment
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the user who refunded this payment
     */
    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Get the user who verified this payment
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who created this payment record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this payment record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== STATUS CHECK METHODS ==========

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if payment is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if payment is refunded
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Check if payment is partially refunded
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_REFUNDED;
    }

    /**
     * Check if payment is active (pending or processing)
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    // ========== PROVIDER CHECK METHODS (UPDATED) ==========

    /**
     * Check if payment is via ExpressPay
     */
    public function isExpresspay(): bool
    {
        return $this->payment_provider === self::PROVIDER_EXPRESSPAY;
    }

    /**
     * Check if payment is via Hubtel
     */
    public function isHubtel(): bool
    {
        return $this->payment_provider === self::PROVIDER_HUBTEL;
    }

    /**
     * Check if payment is via Paystack
     */
    public function isPaystack(): bool
    {
        return $this->payment_provider === self::PROVIDER_PAYSTACK;
    }

    /**
     * Check if payment is via Flutterwave
     */
    public function isFlutterwave(): bool
    {
        return $this->payment_provider === self::PROVIDER_FLUTTERWAVE;
    }

    /**
     * Check if payment is manual
     */
    public function isManual(): bool
    {
        return $this->payment_provider === self::PROVIDER_MANUAL;
    }

    /**
     * Check if payment is via mobile money
     */
    public function isMobileMoney(): bool
    {
        return in_array($this->payment_provider, [
            self::PROVIDER_EXPRESSPAY,
            self::PROVIDER_HUBTEL,
            self::PROVIDER_FLUTTERWAVE
        ]);
    }

    /**
     * Check if payment is online (Paystack)
     */
    public function isOnline(): bool
    {
        return $this->isPaystack();
    }

    /**
     * Check if payment requires verification
     */
    public function requiresVerification(): bool
    {
        return $this->isMobileMoney() && $this->isPending();
    }

    // ========== VERIFICATION METHODS ==========

    /**
     * Check if payment is verified
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at) && !is_null($this->verified_by);
    }

    /**
     * Mark payment as verified
     */
    public function markAsVerified($verifiedBy = null): void
    {
        $this->update([
            'verified_at' => now(),
            'verified_by' => $verifiedBy ?? auth()->id(),
            'updated_by' => auth()->id()
        ]);
    }

    /**
     * Check if payment can be verified
     */
    public function canBeVerified(): bool
    {
        return $this->isCompleted() && !$this->isVerified();
    }

    // ========== BUSINESS LOGIC METHODS ==========

    /**
     * Check if payment can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->isActive() && !$this->isCompleted();
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && !$this->isRefunded() && !$this->isPartiallyRefunded();
    }

    /**
     * Check if payment can be partially refunded
     */
    public function canBePartiallyRefunded(): bool
    {
        return $this->isCompleted() && !$this->isRefunded();
    }

    /**
     * Check if payment is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->isActive() || !$this->created_at) {
            return false;
        }
        
        // Consider payments overdue after 24 hours of pending
        return $this->created_at->diffInHours(now()) > 24;
    }

    /**
     * Get days pending
     */
    public function getDaysPending(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        return $this->created_at->diffInDays(now());
    }

    // ========== METADATA METHODS ==========

    /**
     * Get metadata value
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadataValue(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get invoice IDs from metadata
     */
    public function getInvoiceIds(): array
    {
        return $this->getMetadataValue('invoices', []);
    }

    // ========== STATUS UPDATE METHODS ==========

    /**
     * Mark payment as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'updated_by' => auth()->id()
        ]);
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted($paymentDate = null): void
    {
        $updateData = [
            'status' => self::STATUS_COMPLETED,
            'payment_date' => $paymentDate ?? now(),
            'processed_at' => now(),
            'processed_by' => auth()->id(),
            'updated_by' => auth()->id()
        ];

        if (empty($this->transaction_reference)) {
            $updateData['transaction_reference'] = 'COMPLETED_' . $this->transaction_id;
        }

        $this->update($updateData);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($reason = null): void
    {
        $updateData = [
            'status' => self::STATUS_FAILED,
            'updated_by' => auth()->id()
        ];
        
        if ($reason) {
            $updateData['notes'] = $this->notes . "\n[Failed: {$reason}]";
            
            $metadata = $this->metadata ?? [];
            $metadata['failure_details'] = [
                'reason' => $reason,
                'failed_at' => now()->toDateTimeString()
            ];
            $updateData['metadata'] = $metadata;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark payment as cancelled
     */
    public function markAsCancelled($reason = null): void
    {
        $updateData = [
            'status' => self::STATUS_CANCELLED,
            'updated_by' => auth()->id()
        ];
        
        if ($reason) {
            $updateData['notes'] = $this->notes . "\n[Cancelled: {$reason}]";
            
            $metadata = $this->metadata ?? [];
            $metadata['cancellation'] = [
                'reason' => $reason,
                'cancelled_at' => now()->toDateTimeString(),
                'cancelled_by' => auth()->id()
            ];
            $updateData['metadata'] = $metadata;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark payment as refunded
     */
    public function markAsRefunded($refundAmount = null, $refundReason = null, $refundedBy = null): void
    {
        $updateData = [
            'status' => self::STATUS_REFUNDED,
            'refunded_at' => now(),
            'refunded_by' => $refundedBy ?? auth()->id(),
            'updated_by' => auth()->id()
        ];
        
        if ($refundAmount) {
            $updateData['refund_amount'] = $refundAmount;
        }
        
        if ($refundReason) {
            $updateData['refund_reason'] = $refundReason;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark payment as partially refunded
     */
    public function markAsPartiallyRefunded($refundAmount, $refundReason = null): void
    {
        $updateData = [
            'status' => self::STATUS_PARTIALLY_REFUNDED,
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
            'refunded_by' => auth()->id(),
            'updated_by' => auth()->id()
        ];
        
        if ($refundReason) {
            $updateData['refund_reason'] = $refundReason;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['partial_refund'] = [
            'amount' => $refundAmount,
            'reason' => $refundReason,
            'refunded_at' => now()->toDateTimeString(),
            'refunded_by' => auth()->id()
        ];
        $updateData['metadata'] = $metadata;
        
        $this->update($updateData);
    }

    // ========== ACCESSOR METHODS ==========

    /**
     * Get formatted amount with currency symbol
     */
    public function getFormattedAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->amount);
    }

    /**
     * Get provider display name (UPDATED)
     */
    public function getProviderDisplayNameAttribute(): string
    {
        $providers = [
            self::PROVIDER_EXPRESSPAY => 'ExpressPay',
            self::PROVIDER_HUBTEL => 'Hubtel',
            self::PROVIDER_PAYSTACK => 'Paystack',
            self::PROVIDER_FLUTTERWAVE => 'Flutterwave',
            self::PROVIDER_MANUAL => 'Manual'
        ];
        
        return $providers[$this->payment_provider] ?? ucfirst(str_replace('_', ' ', $this->payment_provider));
    }

    /**
     * Get status display text
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_REFUNDED => 'secondary',
            self::STATUS_PARTIALLY_REFUNDED => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get status icon
     */
    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'check-circle',
            self::STATUS_PENDING => 'clock',
            self::STATUS_PROCESSING => 'spinner fa-spin',
            self::STATUS_FAILED => 'exclamation-circle',
            self::STATUS_CANCELLED => 'times-circle',
            self::STATUS_REFUNDED => 'undo-alt',
            self::STATUS_PARTIALLY_REFUNDED => 'undo-alt',
            default => 'circle'
        };
    }

    /**
     * Get is mobile money attribute
     */
    public function getIsMobileMoneyAttribute(): bool
    {
        return $this->isMobileMoney();
    }

    /**
     * Get is online attribute
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    /**
     * Get can be cancelled attribute
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    /**
     * Get can be refunded attribute
     */
    public function getCanBeRefundedAttribute(): bool
    {
        return $this->canBeRefunded();
    }

    /**
     * Get days pending attribute
     */
    public function getDaysPendingAttribute(): int
    {
        return $this->getDaysPending();
    }

    // ========== SCOPE METHODS ==========

    /**
     * Scope a query to only include completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include processing payments
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope a query to only include failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include payments by provider (UPDATED)
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    /**
     * Scope a query to only include ExpressPay payments
     */
    public function scopeExpresspay($query)
    {
        return $query->where('payment_provider', self::PROVIDER_EXPRESSPAY);
    }

    /**
     * Scope a query to only include Hubtel payments
     */
    public function scopeHubtel($query)
    {
        return $query->where('payment_provider', self::PROVIDER_HUBTEL);
    }

    /**
     * Scope a query to only include Paystack payments
     */
    public function scopePaystack($query)
    {
        return $query->where('payment_provider', self::PROVIDER_PAYSTACK);
    }

    /**
     * Scope a query to only include Flutterwave payments
     */
    public function scopeFlutterwave($query)
    {
        return $query->where('payment_provider', self::PROVIDER_FLUTTERWAVE);
    }

    /**
     * Scope a query to only include manual payments
     */
    public function scopeManual($query)
    {
        return $query->where('payment_provider', self::PROVIDER_MANUAL);
    }

    /**
     * Scope a query to only include mobile money payments
     */
    public function scopeMobileMoney($query)
    {
        return $query->whereIn('payment_provider', [
            self::PROVIDER_EXPRESSPAY,
            self::PROVIDER_HUBTEL,
            self::PROVIDER_FLUTTERWAVE
        ]);
    }

    /**
     * Scope a query to only include payments within date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include payments for a specific tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include payments for a specific property unit
     */
    public function scopeForPropertyUnit($query, $propertyUnitId)
    {
        return $query->where('property_unit_id', $propertyUnitId);
    }

    /**
     * Scope a query to only include verified payments
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope a query to only include unverified payments
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    // ========== STATIC METHODS ==========

    /**
     * Get available providers (UPDATED)
     */
    public static function getAvailableProviders(): array
    {
        return [
            self::PROVIDER_EXPRESSPAY => 'ExpressPay',
            self::PROVIDER_HUBTEL => 'Hubtel',
            self::PROVIDER_PAYSTACK => 'Paystack',
            self::PROVIDER_FLUTTERWAVE => 'Flutterwave',
            self::PROVIDER_MANUAL => 'Manual'
        ];
    }

    /**
     * Get available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded'
        ];
    }

    /**
     * Get payment summary for a tenant
     */
    public static function getSummaryForTenant(int $tenantId): array
    {
        $settings = SystemSetting::getSettings();
        
        $totalPaid = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_COMPLETED)
            ->sum('amount');
            
        $pendingCount = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_PENDING)
            ->count();
            
        $processingCount = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_PROCESSING)
            ->count();
            
        $completedCount = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_COMPLETED)
            ->count();
            
        $failedCount = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_FAILED)
            ->count();
            
        $recentPayments = self::where('tenant_id', $tenantId)
            ->where('status', self::STATUS_COMPLETED)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get();
            
        return [
            'total_paid' => $totalPaid,
            'formatted_total_paid' => $settings->formatAmount($totalPaid),
            'pending_count' => $pendingCount,
            'processing_count' => $processingCount,
            'completed_count' => $completedCount,
            'failed_count' => $failedCount,
            'recent_payments' => $recentPayments
        ];
    }

    /**
     * Generate unique transaction ID (UPDATED)
     */
    public static function generateTransactionId(?string $provider = null): string
    {
        $prefixMap = [
            self::PROVIDER_EXPRESSPAY => 'EXP',
            self::PROVIDER_HUBTEL => 'HUB',
            self::PROVIDER_PAYSTACK => 'PSK',
            self::PROVIDER_FLUTTERWAVE => 'FLW',
            self::PROVIDER_MANUAL => 'MNL'
        ];
        
        $prefix = $prefixMap[$provider] ?? 'TNT';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(\Illuminate\Support\Str::random(6));
        
        $transactionId = "{$prefix}_{$timestamp}_{$random}";
        
        while (self::where('transaction_id', $transactionId)->exists()) {
            $random = strtoupper(\Illuminate\Support\Str::random(6));
            $transactionId = "{$prefix}_{$timestamp}_{$random}";
        }
        
        return $transactionId;
    }

    /**
     * Generate unique reference number
     */
    public static function generateReference(): string
    {
        return 'TNT_REF_' . now()->format('YmdHis') . '_' . strtoupper(\Illuminate\Support\Str::random(6));
    }

    /**
     * Generate receipt data
     */
    public function generateReceiptData(): array
    {
        $settings = SystemSetting::getSettings();
        
        return [
            'transaction_id' => $this->transaction_id,
            'reference' => $this->transaction_reference,
            'date' => $this->payment_date ?? $this->created_at,
            'tenant' => $this->tenant->name ?? 'N/A',
            'property_unit' => $this->propertyUnit?->property?->property_name . ' - Unit ' . ($this->propertyUnit?->unit_number ?? 'N/A'),
            'amount' => $settings->formatAmount($this->amount),
            'provider' => $this->provider_display_name,
            'status' => $this->status_display,
            'description' => $this->description ?? 'Tenant Payment',
            'verified' => $this->isVerified(),
            'verified_by' => $this->isVerified() ? ($this->verifier->name ?? 'N/A') : null,
            'verified_at' => $this->verified_at?->format('M d, Y H:i'),
            'invoice_number' => $this->invoice->invoice_number ?? 'N/A',
            'period' => $this->invoice ? Carbon::parse($this->invoice->period . '-01')->format('F Y') : 'N/A'
        ];
    }

    /**
     * Get payment instructions based on provider
     */
    public function getPaymentInstructions(): string
    {
        if ($this->isMobileMoney()) {
            $provider = $this->provider_display_name;
            return "You will receive a prompt on your {$provider} to authorize the payment. Please check your phone and follow the instructions to complete the payment.";
        }
        
        if ($this->isPaystack()) {
            return "You will be redirected to Paystack to complete your payment securely. Please have your card or bank account ready.";
        }
        
        if ($this->isManual()) {
            return "This is a manual payment. Please follow instructions from the administrator to complete this payment.";
        }
        
        return "Please follow the instructions provided by your payment provider to complete this payment.";
    }

    // ========== MODEL EVENTS ==========

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($payment) {
            if (auth()->check()) {
                $payment->created_by = auth()->id();
                $payment->updated_by = auth()->id();
            }
            
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = self::generateTransactionId($payment->payment_provider);
            }
            
            if (empty($payment->currency)) {
                $settings = SystemSetting::getSettings();
                $payment->currency = $settings->currency_code ?? 'GHS';
            }
            
            // Set net amount if fee is provided
            if ($payment->fee_amount && !$payment->net_amount) {
                $payment->net_amount = $payment->amount - $payment->fee_amount;
            }
        });

        static::updating(function ($payment) {
            if (auth()->check()) {
                $payment->updated_by = auth()->id();
            }
            
            // Recalculate net amount if amount or fee changes
            if ($payment->isDirty(['amount', 'fee_amount'])) {
                $payment->net_amount = $payment->amount - $payment->fee_amount;
            }
            
            // Auto-set payment date when marked as completed
            if ($payment->isDirty('status') && $payment->status === self::STATUS_COMPLETED && !$payment->payment_date) {
                $payment->payment_date = now();
            }
        });

        // Auto-cancel overdue pending payments
        static::saving(function ($payment) {
            if ($payment->isOverdue() && $payment->isActive()) {
                $payment->status = self::STATUS_CANCELLED;
                $payment->notes = ($payment->notes ? $payment->notes . "\n" : '') . 
                                 '[Auto-cancelled: Payment pending for over 24 hours]';
            }
        });
    }
}