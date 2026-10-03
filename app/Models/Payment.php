<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ PAYMENT STATUS CONSTANTS
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    // ✅ PAYMENT PROVIDER CONSTANTS (UPDATED FOR NEW GATEWAYS)
    const PROVIDER_EXPRESSPAY = 'expresspay';
    const PROVIDER_HUBTEL = 'hubtel';
    const PROVIDER_PAYSTACK = 'paystack';
    const PROVIDER_FLUTTERWAVE = 'flutterwave';
    const PROVIDER_MANUAL = 'manual';

    // ✅ PAYMENT TYPE CONSTANTS (for metadata)
    const TYPE_SINGLE = 'single';
    const TYPE_BULK = 'bulk';
    const TYPE_INVOICES = 'invoices';

    protected $fillable = [
        'property_id',
        'landlord_id',
        'amount',
        'currency',
        'payment_provider',
        'payment_method', // Kept for backward compatibility
        'transaction_id',
        'transaction_reference',
        'status',
        'payment_date',
        'processed_at',
        'refunded_at',
        'phone_number',
        'email',
        'network',
        'metadata',
        'description',
        'gateway_response',
        'gateway_metadata',
        'fee_amount',
        'net_amount',
        'refund_amount',
        'payment_type',
        'purpose',
        'notes',
        'failure_reason',
        'refund_reason',
        'verified_by',
        'verified_at',
        'processed_by',
        'refunded_by',
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
        'gateway_metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_amount',
        'status_display',
        'provider_display',
        'status_badge_class',
        'provider_badge_class',
        'is_verified',
        'verification_status',
        'verification_badge_class',
        'is_mobile_money',
        'is_online',
        'payment_type_display',
        'formatted_payment_date',
        'formatted_created_at',
        'can_cancel',
        'can_refund',
        'days_pending',
        'is_expiring_soon'
    ];

    /**
     * Get the property that owns the payment.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the landlord that owns the payment.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the invoices for the payment.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'payment_id');
    }

    /**
     * Get the user who created the payment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the payment.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who verified the payment.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who processed the payment.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the user who refunded the payment.
     */
    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    // ✅ STATUS SCOPES
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopePartiallyRefunded($query)
    {
        return $query->where('status', self::STATUS_PARTIALLY_REFUNDED);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->whereHas('invoices', function ($q) use ($days) {
            $q->whereDate('due_date', '<=', Carbon::now()->addDays($days))
              ->whereDate('due_date', '>=', Carbon::now());
        });
    }

    // ✅ PROVIDER SCOPES (UPDATED FOR NEW GATEWAYS)
    public function scopeExpresspay($query)
    {
        return $query->where('payment_provider', self::PROVIDER_EXPRESSPAY);
    }

    public function scopeHubtel($query)
    {
        return $query->where('payment_provider', self::PROVIDER_HUBTEL);
    }

    public function scopePaystack($query)
    {
        return $query->where('payment_provider', self::PROVIDER_PAYSTACK);
    }

    public function scopeFlutterwave($query)
    {
        return $query->where('payment_provider', self::PROVIDER_FLUTTERWAVE);
    }

    public function scopeManual($query)
    {
        return $query->where('payment_provider', self::PROVIDER_MANUAL);
    }

    public function scopeMobileMoney($query)
    {
        return $query->whereIn('payment_provider', [
            self::PROVIDER_EXPRESSPAY,
            self::PROVIDER_HUBTEL,
            self::PROVIDER_FLUTTERWAVE
        ]);
    }

    // ✅ STATUS CHECK METHODS
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_REFUNDED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Check if the payment is expiring soon
     */
    public function isExpiringSoon($days = 7): bool
    {
        if ($this->isCompleted() || $this->isFailed() || $this->isCancelled()) {
            return false;
        }

        if ($this->invoices && $this->invoices->count() > 0) {
            foreach ($this->invoices as $invoice) {
                if ($invoice->due_date) {
                    $dueDate = Carbon::parse($invoice->due_date);
                    $today = Carbon::now();
                    
                    if ($dueDate->isFuture() && $dueDate->diffInDays($today) <= $days) {
                        return true;
                    }
                }
            }
        }

        if ($this->created_at) {
            $expirationDate = Carbon::parse($this->created_at)->addDays(30);
            $today = Carbon::now();
            
            return $expirationDate->isFuture() && $expirationDate->diffInDays($today) <= $days;
        }

        return false;
    }

    // ✅ PROVIDER CHECK METHODS (UPDATED FOR NEW GATEWAYS)
    public function isExpresspay(): bool
    {
        return $this->payment_provider === self::PROVIDER_EXPRESSPAY;
    }

    public function isHubtel(): bool
    {
        return $this->payment_provider === self::PROVIDER_HUBTEL;
    }

    public function isPaystack(): bool
    {
        return $this->payment_provider === self::PROVIDER_PAYSTACK;
    }

    public function isFlutterwave(): bool
    {
        return $this->payment_provider === self::PROVIDER_FLUTTERWAVE;
    }

    public function isManual(): bool
    {
        return $this->payment_provider === self::PROVIDER_MANUAL;
    }

    public function isMobileMoney(): bool
    {
        return in_array($this->payment_provider, [
            self::PROVIDER_EXPRESSPAY,
            self::PROVIDER_HUBTEL,
            self::PROVIDER_FLUTTERWAVE
        ]);
    }

    public function isOnline(): bool
    {
        return $this->isPaystack();
    }

    public function requiresVerification(): bool
    {
        return $this->isMobileMoney() && $this->isPending();
    }

    // ✅ VERIFICATION METHODS
    public function isVerified(): bool
    {
        return !is_null($this->verified_at) && !is_null($this->verified_by);
    }

    public function markAsVerified($verifiedBy = null): void
    {
        $this->update([
            'verified_at' => now(),
            'verified_by' => $verifiedBy ?? auth()->id(),
            'updated_by' => auth()->id()
        ]);
        
        Log::info("Payment {$this->transaction_id} marked as verified", [
            'payment_id' => $this->id,
            'verified_by' => $verifiedBy ?? auth()->id()
        ]);
    }

    public function markAsUnverified(): void
    {
        $this->update([
            'verified_at' => null,
            'verified_by' => null,
            'updated_by' => auth()->id()
        ]);
        
        Log::info("Payment {$this->transaction_id} marked as unverified");
    }

    public function canBeVerified(): bool
    {
        return $this->isCompleted() && !$this->isVerified();
    }

    // ✅ STATUS UPDATE METHODS
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'updated_by' => auth()->id()
        ]);
        
        Log::info("Payment {$this->transaction_id} marked as processing");
    }

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
        
        Log::info("Payment {$this->transaction_id} marked as completed", [
            'payment_date' => $paymentDate ?? now(),
            'amount' => $this->amount
        ]);
    }

    public function markAsFailed($reason = null): void
    {
        $updateData = [
            'status' => self::STATUS_FAILED,
            'updated_by' => auth()->id()
        ];
        
        if ($reason) {
            $updateData['failure_reason'] = $reason;
            
            $metadata = $this->metadata ?? [];
            $metadata['failure_details'] = [
                'reason' => $reason,
                'failed_at' => now()->toDateTimeString()
            ];
            $updateData['metadata'] = $metadata;
        }
        
        $this->update($updateData);
        
        Log::warning("Payment {$this->transaction_id} marked as failed", [
            'reason' => $reason,
            'payment_id' => $this->id
        ]);
    }

    public function markAsRefunded($refundData = null, $refundedBy = null): void
    {
        $updateData = [
            'status' => self::STATUS_REFUNDED,
            'refunded_at' => now(),
            'refunded_by' => $refundedBy ?? auth()->id(),
            'updated_by' => auth()->id()
        ];
        
        if ($refundData) {
            $metadata = $this->metadata ?? [];
            $metadata['refund_data'] = $refundData;
            $updateData['metadata'] = $metadata;
        }
        
        $this->update($updateData);
        
        Log::info("Payment {$this->transaction_id} marked as refunded", [
            'refunded_by' => $refundedBy ?? auth()->id(),
            'amount' => $this->amount
        ]);
    }

    public function markAsPartiallyRefunded($refundAmount, $refundData = null): void
    {
        $updateData = [
            'status' => self::STATUS_PARTIALLY_REFUNDED,
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
            'refunded_by' => auth()->id(),
            'updated_by' => auth()->id()
        ];
        
        if ($refundData) {
            $metadata = $this->metadata ?? [];
            $metadata['partial_refund_data'] = $refundData;
            $updateData['metadata'] = $metadata;
        }
        
        $this->update($updateData);
        
        Log::info("Payment {$this->transaction_id} marked as partially refunded", [
            'refund_amount' => $refundAmount,
            'original_amount' => $this->amount
        ]);
    }

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
        
        Log::info("Payment {$this->transaction_id} marked as cancelled", [
            'reason' => $reason,
            'payment_id' => $this->id
        ]);
    }

    // ✅ BUSINESS LOGIC METHODS
    public function canBeCancelled(): bool
    {
        return $this->isActive() && !$this->isCompleted();
    }

    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && !$this->isRefunded() && !$this->isPartiallyRefunded();
    }

    public function canBePartiallyRefunded(): bool
    {
        return $this->isCompleted() && !$this->isRefunded();
    }

    public function isOverdue(): bool
    {
        if (!$this->isActive() || !$this->created_at) {
            return false;
        }
        
        return $this->created_at->diffInHours(now()) > 24;
    }

    // ✅ METADATA METHODS
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getInvoiceIds(): array
    {
        return $this->getMetadataValue('invoices', []);
    }

    public function getPaymentType(): string
    {
        return $this->getMetadataValue('payment_type', self::TYPE_SINGLE);
    }

    public function isBulkPayment(): bool
    {
        return $this->getPaymentType() === self::TYPE_BULK;
    }

    public function getBulkMonths(): ?int
    {
        return $this->getMetadataValue('months');
    }

    // ✅ ACCESSOR METHODS
    public function getFormattedAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->amount);
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
            default => ucfirst($this->status)
        };
    }

    public function getProviderDisplayAttribute(): string
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

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_PROCESSING => 'badge-info',
            self::STATUS_FAILED => 'badge-danger',
            self::STATUS_REFUNDED => 'badge-secondary',
            self::STATUS_PARTIALLY_REFUNDED => 'badge-info',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-light'
        };
    }

    public function getProviderBadgeClassAttribute(): string
    {
        return match($this->payment_provider) {
            self::PROVIDER_EXPRESSPAY => 'badge-primary',
            self::PROVIDER_HUBTEL => 'badge-info',
            self::PROVIDER_PAYSTACK => 'badge-success',
            self::PROVIDER_FLUTTERWAVE => 'badge-warning',
            self::PROVIDER_MANUAL => 'badge-secondary',
            default => 'badge-light'
        };
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->isVerified();
    }

    public function getVerificationStatusAttribute(): string
    {
        return $this->isVerified() ? 'Verified' : 'Unverified';
    }

    public function getVerificationBadgeClassAttribute(): string
    {
        return $this->isVerified() ? 'badge-success' : 'badge-warning';
    }

    public function getIsMobileMoneyAttribute(): bool
    {
        return $this->isMobileMoney();
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    public function getPaymentTypeDisplayAttribute(): string
    {
        if (!$this->payment_type) return 'N/A';
        
        return match($this->payment_type) {
            'rent' => 'Rent',
            'maintenance_fee' => 'Maintenance Fee',
            'service_charge' => 'Service Charge',
            'security_deposit' => 'Security Deposit',
            'utility_bill' => 'Utility Bill',
            'penalty_fee' => 'Penalty Fee',
            'other' => 'Other',
            default => ucwords(str_replace('_', ' ', $this->payment_type))
        };
    }

    public function getFormattedPaymentDateAttribute(): string
    {
        return $this->payment_date 
            ? $this->payment_date->format('M d, Y H:i')
            : 'N/A';
    }

    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('M d, Y H:i');
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function getCanRefundAttribute(): bool
    {
        return $this->canBeRefunded();
    }

    public function getDaysPendingAttribute(): int
    {
        if (!$this->isActive()) return 0;
        return $this->created_at->diffInDays(now());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->isExpiringSoon();
    }

    // ✅ STATIC METHODS (UPDATED FOR NEW GATEWAYS)
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

    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded'
        ];
    }

    public static function getPaymentTypes(): array
    {
        return [
            'rent' => 'Rent',
            'maintenance_fee' => 'Maintenance Fee',
            'service_charge' => 'Service Charge',
            'security_deposit' => 'Security Deposit',
            'utility_bill' => 'Utility Bill',
            'penalty_fee' => 'Penalty Fee',
            'other' => 'Other'
        ];
    }

    /**
     * Get gateway-specific instructions for payment completion
     */
    public function getPaymentInstructions(): string
    {
        if ($this->isMobileMoney()) {
            $provider = $this->provider_display;
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

    /**
     * Generate receipt data for payment confirmation
     */
    public function generateReceiptData(): array
    {
        $settings = SystemSetting::getSettings();
        
        return [
            'transaction_id' => $this->transaction_id,
            'reference' => $this->transaction_reference,
            'date' => $this->payment_date ?? $this->created_at,
            'landlord' => $this->landlord->name ?? 'N/A',
            'property' => $this->property->name ?? 'N/A',
            'amount' => $settings->formatAmount($this->amount),
            'provider' => $this->provider_display,
            'status' => $this->status_display,
            'description' => $this->description ?? $this->purpose ?? 'Property Payment',
            'verified' => $this->isVerified(),
            'verified_by' => $this->isVerified() ? ($this->verifier->name ?? 'N/A') : null,
            'verified_at' => $this->verified_at?->format('M d, Y H:i')
        ];
    }

    // ✅ MODEL EVENTS
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (auth()->check()) {
                $payment->created_by = auth()->id();
                $payment->updated_by = auth()->id();
            }
            
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = 'PAY_' . uniqid() . '_' . time();
            }
            
            if (empty($payment->currency)) {
                $settings = SystemSetting::getSettings();
                $payment->currency = $settings->currency_code ?? 'GHS';
            }
            
            if (empty($payment->payment_type)) {
                $payment->payment_type = 'service_charge';
            }
            
            if ($payment->fee_amount && !$payment->net_amount) {
                $payment->net_amount = $payment->amount - $payment->fee_amount;
            }
        });

        static::updating(function ($payment) {
            if (auth()->check()) {
                $payment->updated_by = auth()->id();
            }
            
            if ($payment->isDirty(['amount', 'fee_amount'])) {
                $payment->net_amount = $payment->amount - $payment->fee_amount;
            }
            
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
                Log::info("Payment {$payment->transaction_id} auto-cancelled due to timeout");
            }
        });
    }
}