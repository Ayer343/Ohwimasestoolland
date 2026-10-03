<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class BillingInvoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_billing_record_id',
        'agreement_id', // ✅ Added for consistency
        'developer_setting_id',
        'super_admin_id',
        'invoice_number',
        'amount',
        'original_amount',
        'amount_paid_already',
        'paid_amount',
        'currency',
        'description',
        'invoice_type',
        'issue_date',
        'due_date',
        'payment_due_days',
        'status',
        'is_recurring',
        'is_custom',
        'is_auto_generated',
        'billing_cycle_reference',
        'billing_month',
        'created_by',
        'paid_at',
        'sent_at',
        'sent_to',
        'payment_method',
        'transaction_reference',
        'payment_reference',
        'payment_data',
        'notes',
        'items',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount'              => 'decimal:2',
        'original_amount'     => 'decimal:2',
        'amount_paid_already' => 'decimal:2',
        'paid_amount'         => 'decimal:2',
        'issue_date'          => 'date',
        'due_date'            => 'date',
        'payment_due_days'    => 'integer',
        'is_recurring'        => 'boolean',
        'is_custom'           => 'boolean',
        'is_auto_generated'   => 'boolean',
        'paid_at'             => 'datetime',
        'sent_at'             => 'datetime',
        'items'               => 'array',
        'payment_data'        => 'array',
    ];

    /**
     * Invoice statuses
     */
    const STATUS_PENDING   = 'pending';
    const STATUS_PAID      = 'paid';
    const STATUS_OVERDUE   = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PARTIAL   = 'partial';

    /**
     * Invoice types
     */
    const TYPE_RECURRING  = 'recurring';
    const TYPE_ONE_TIME   = 'one_time';
    const TYPE_ADDITIONAL = 'additional';
    const TYPE_PENALTY    = 'penalty';
    const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Cached schema column checks — avoids repeated SHOW COLUMNS queries
     * on every relation access. Rebuilt per request.
     *
     * @var array<string, bool>
     */
    protected static array $schemaColumnCache = [];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate invoice number before creating
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }

            // Set default status if not set
            if (empty($invoice->status)) {
                $invoice->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Lightweight cached column existence check for this model's table.
     */
    protected static function hasColumn(string $column): bool
    {
        $key = static::class . '.' . $column;

        if (!array_key_exists($key, static::$schemaColumnCache)) {
            static::$schemaColumnCache[$key] = Schema::hasColumn((new static)->getTable(), $column);
        }

        return static::$schemaColumnCache[$key];
    }

    // ================================================================
    // ✅ RELATIONSHIPS - FIXED WITH AGREEMENT
    // ================================================================

    /**
     * Get the admin billing record associated with this invoice.
     */
    public function adminBillingRecord(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'admin_billing_record_id');
    }

    /**
     * ✅ FIXED: Get the agreement associated with this invoice.
     * Handles both `agreement_id` and `admin_billing_record_id` FK naming.
     */
    public function agreement(): BelongsTo
    {
        if (static::hasColumn('agreement_id')) {
            return $this->belongsTo(AdminBillingRecord::class, 'agreement_id');
        }

        return $this->belongsTo(AdminBillingRecord::class, 'admin_billing_record_id');
    }

    /**
     * Alias for adminBillingRecord - for backward compatibility.
     */
    public function billingRecord(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'admin_billing_record_id');
    }

    /**
     * Get the developer setting that owns the invoice.
     */
    public function developerSetting(): BelongsTo
    {
        return $this->belongsTo(DeveloperSetting::class);
    }

    /**
     * Get the super admin associated with this invoice.
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    /**
     * Get the user who created the invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get related payments.
     *
     * Note: `agreement_payments` uses `admin_billing_record_id` as its FK to
     * the agreement, not to this invoice — so this relation only works if you
     * later add a `billing_invoice_id` column to that table. Kept for BC.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AgreementPayment::class, 'billing_invoice_id');
    }

    /**
     * Get the InvoiceReminder rows scheduled for this invoice.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class, 'invoice_id');
    }

    // ================================================================
    // ✅ SCOPES
    // ================================================================

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
        return $query->where(function ($query) {
            $query->where('status', self::STATUS_OVERDUE)
                ->orWhere(function ($subQuery) {
                    $subQuery->where('status', self::STATUS_PENDING)
                        ->where('due_date', '<', now()->toDateString());
                });
        });
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }

    public function scopeForSuperAdmin($query, $superAdminId)
    {
        return $query->where('super_admin_id', $superAdminId);
    }

    public function scopeForAgreement($query, $agreementId)
    {
        return $query->where(function ($q) use ($agreementId) {
            if (static::hasColumn('agreement_id')) {
                $q->orWhere('agreement_id', $agreementId);
            }
            $q->orWhere('admin_billing_record_id', $agreementId);
        });
    }

    public function scopeForMonth($query, $month)
    {
        return $query->where('billing_month', $month);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE]);
    }

    // ================================================================
    // ✅ ACCESSORS & MUTATORS
    // ================================================================

    /**
     * Currency symbol map — kept in one place.
     */
    protected static function currencySymbols(): array
    {
        return [
            'GHS' => 'GH₵',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
    }

    public function getFormattedAmountAttribute(): string
    {
        $symbol = static::currencySymbols()[$this->currency] ?? $this->currency;
        return $symbol . ' ' . number_format((float) $this->amount, 2);
    }

    public function getFormattedPaidAmountAttribute(): string
    {
        $symbol = static::currencySymbols()[$this->currency] ?? $this->currency;
        return $symbol . ' ' . number_format((float) ($this->paid_amount ?? 0), 2);
    }

    public function getStatusTextAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getTypeTextAttribute(): string
    {
        return self::getTypeOptions()[$this->invoice_type] ?? ucfirst((string) $this->invoice_type);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID      => 'badge bg-success',
            self::STATUS_PENDING   => 'badge bg-warning',
            self::STATUS_OVERDUE   => 'badge bg-danger',
            self::STATUS_CANCELLED => 'badge bg-secondary',
            self::STATUS_PARTIAL   => 'badge bg-info',
            default                => 'badge bg-secondary',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID      => 'green',
            self::STATUS_PENDING   => 'yellow',
            self::STATUS_OVERDUE   => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_PARTIAL   => 'blue',
            default                => 'gray',
        };
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->invoice_type) {
            self::TYPE_RECURRING  => 'badge bg-primary',
            self::TYPE_ONE_TIME   => 'badge bg-info',
            self::TYPE_ADDITIONAL => 'badge bg-warning',
            self::TYPE_PENALTY    => 'badge bg-danger',
            self::TYPE_ADJUSTMENT => 'badge bg-secondary',
            default               => 'badge bg-secondary',
        };
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - (float) ($this->paid_amount ?? 0));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_OVERDUE
            || ($this->status === self::STATUS_PENDING && $this->due_date && $this->due_date->isPast());
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }

    public function getFilePathAttribute(): ?string
    {
        $path = "invoices/{$this->id}/invoice.pdf";

        return Storage::exists($path) ? Storage::url($path) : null;
    }

    public function getHasFileAttribute(): bool
    {
        return !is_null($this->file_path);
    }

    public function getLineItemsAttribute(): array
    {
        if (empty($this->items)) {
            return [
                [
                    'description' => $this->description ?? 'Invoice Item',
                    'quantity'    => 1,
                    'unit_price'  => (float) $this->amount,
                    'total'       => (float) $this->amount,
                ],
            ];
        }

        return $this->items;
    }

    public function getSummaryAttribute(): string
    {
        $summary = "Invoice #{$this->invoice_number}";

        $agreement = $this->relationLoaded('agreement')
            ? $this->agreement
            : ($this->relationLoaded('adminBillingRecord') ? $this->adminBillingRecord : null);

        if ($agreement) {
            $summary .= " - Agreement #{$agreement->agreement_number}";
        }

        if ($this->relationLoaded('superAdmin') && $this->superAdmin) {
            $summary .= " - {$this->superAdmin->name}";
        }

        $summary .= " - {$this->formatted_amount}";

        return $summary;
    }

    // ================================================================
    // ✅ BUSINESS LOGIC METHODS
    // ================================================================

    public function markAsPaid(array $paymentData = []): bool
    {
        $incomingNotes = $paymentData['notes'] ?? null;

        return $this->update([
            'status'                => self::STATUS_PAID,
            'paid_at'               => now(),
            'payment_method'        => $paymentData['payment_method']        ?? $this->payment_method,
            'transaction_reference' => $paymentData['transaction_reference'] ?? $this->transaction_reference,
            'payment_reference'     => $paymentData['payment_reference']     ?? $this->payment_reference,
            'paid_amount'           => $paymentData['amount']                ?? $this->amount,
            'payment_data'          => $paymentData,
            'notes'                 => $incomingNotes
                ? ($this->notes ? $this->notes . "\n" . $incomingNotes : $incomingNotes)
                : $this->notes,
        ]);
    }

    public function markAsOverdue(): bool
    {
        if ($this->status === self::STATUS_PENDING && $this->due_date && $this->due_date->isPast()) {
            return $this->update(['status' => self::STATUS_OVERDUE]);
        }

        return false;
    }

    public function markAsSent(string $email): bool
    {
        return $this->update([
            'sent_at' => now(),
            'sent_to' => $email,
        ]);
    }

    /**
     * ✅ FIXED: explicit nullable type on $reason — resolves PHP 8.4+ deprecation.
     */
    public function cancel(?string $reason = null): bool
    {
        if ($reason) {
            $newNotes = $this->notes
                ? $this->notes . "\nCancelled: " . $reason
                : "Cancelled: " . $reason;
        } else {
            $newNotes = $this->notes;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes'  => $newNotes,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPartiallyPaid(): bool
    {
        return ($this->paid_amount ?? 0) > 0 && ($this->paid_amount ?? 0) < $this->amount;
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(string $prefix = 'INV'): string
    {
        $year  = date('Y');
        $month = date('m');

        $lastInvoice = self::where('invoice_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice && preg_match('/-(\d+)$/', $lastInvoice->invoice_number, $matches)) {
            $lastNumber = (int) $matches[1];
            $newNumber  = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}-{$year}{$month}-{$newNumber}";
    }

    public function addLineItem(string $description, float $unitPrice, int $quantity = 1): self
    {
        $items   = $this->items ?? [];
        $items[] = [
            'description' => $description,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total'       => $unitPrice * $quantity,
        ];

        $this->items  = $items;
        $this->amount = collect($items)->sum('total');
        $this->save();

        return $this;
    }

    public function createRenewalInvoice(): ?self
    {
        if (!$this->is_recurring) {
            return null;
        }

        $newInvoice = $this->replicate([
            'id',
            'invoice_number',
            'status',
            'paid_at',
            'payment_method',
            'transaction_reference',
            'payment_reference',
            'sent_at',
            'sent_to',
            'payment_data',
            'created_at',
            'updated_at',
        ]);

        $newInvoice->status         = self::STATUS_PENDING;
        $newInvoice->issue_date     = now();
        $newInvoice->due_date       = now()->addDays($this->payment_due_days ?? 30);
        $newInvoice->invoice_number = null; // auto-generated by boot()
        $newInvoice->paid_amount    = 0;
        $newInvoice->sent_at        = null;
        $newInvoice->sent_to        = null;
        $newInvoice->save();

        return $newInvoice;
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_PAID      => 'Paid',
            self::STATUS_OVERDUE   => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PARTIAL   => 'Partial',
        ];
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_RECURRING  => 'Recurring',
            self::TYPE_ONE_TIME   => 'One Time',
            self::TYPE_ADDITIONAL => 'Additional',
            self::TYPE_PENALTY    => 'Penalty',
            self::TYPE_ADJUSTMENT => 'Adjustment',
        ];
    }
}