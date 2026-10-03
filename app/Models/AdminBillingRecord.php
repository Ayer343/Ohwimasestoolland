<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AdminBillingRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'admin_billing_records';

    /**
     * The attributes that are mass assignable.
     *
     * Reconciled against the actual `admin_billing_records` schema.
     * Removed dead entries:
     *   - received_date         → use payment_date
     *   - transaction_reference → use transaction_id
     *   - completed_at          → use paid_at
     *
     * Added columns that exist in the schema but weren't fillable:
     *   - payment_date, transaction_id, payment_gateway, paid_at, overdue_at,
     *     rejected_at, rejected_by, rejection_reason, last_notification_sent_at,
     *     reminder_sent_at, notification_count, receipt_path, invoice_path,
     *     attachments, signature_metadata, auto_renew, renewal_notice_days,
     *     next_renewal_date, last_payment_reminder_sent, payment_reminder_count.
     */
    protected $fillable = [
        'developer_setting_id',
        'super_admin_id',
        'invoice_number',
        'agreement_number',
        'amount',
        'currency',
        'description',
        'start_date',
        'due_date',
        'end_date',

        // Status / payment state
        'status',
        'payment_status',
        'payment_method',
        'billing_frequency',
        'category',
        'amount_received',

        // Payment metadata (schema-aligned)
        'payment_date',           // ✅ replaces received_date
        'transaction_id',         // ✅ replaces transaction_reference
        'payment_gateway',
        'payment_notes',
        'receipt_path',
        'invoice_path',
        'attachments',

        // Paid / overdue markers
        'paid_at',                // ✅ replaces completed_at
        'overdue_at',

        // Audit / attribution
        'requested_by',
        'requested_at',
        'created_by',
        'completed_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',

        // Metadata / notes
        'metadata',
        'notes',

        // Agreement lifecycle
        'agreed_at',
        'agreed_by',
        'agreement_pdf_path',
        'signed_agreement_pdf_path',
        'signing_invitation_sent_at',
        'signing_completed_at',
        'signing_invitation_sent_by',
        'agreement_generated_at',
        'agreement_generated_by',
        'previous_agreement_id',
        'change_reason',
        'effective_date',
        'termination_date',
        'terminated_by',
        'terminated_at',
        'termination_reason',
        'rejected_at',
        'rejected_by',
        'rejection_reason',

        // Primary super admin
        'is_primary_for_billing',
        'billing_contact_name',
        'billing_contact_email',
        'billing_contact_phone',
        'primary_assigned_at',
        'primary_assigned_by',

        // Signatures
        'developer_signed_at',
        'super_admin_signed_at',
        'last_signature_attempt_at',
        'signature_attempt_count',
        'signature_metadata',

        // Payment destination details
        'payment_account_name',
        'payment_account_number',
        'payment_bank_name',
        'payment_bank_branch',
        'payment_mobile_number',
        'payment_mobile_network',
        'payment_mobile_account_name',

        // Billing cycle tracking
        'billing_month',
        'last_invoice_generated_at',
        'next_invoice_date',
        'total_paid_to_date',
        'shared_payment_history',

        // Renewal / notification tracking
        'auto_renew',
        'renewal_notice_days',
        'next_renewal_date',
        'last_payment_reminder_sent',
        'payment_reminder_count',
        'last_notification_sent_at',
        'reminder_sent_at',
        'notification_count',

        // Agreement metadata
        'terms',
    ];

    /**
     * The attributes that should be cast.
     *
     * Reconciled against the actual schema. Removed dead casts:
     *   - received_date (column doesn't exist)
     *   - completed_at  (column doesn't exist)
     *
     * Added casts for existing datetime columns that weren't cast:
     *   - payment_date, paid_at, overdue_at, rejected_at,
     *     last_notification_sent_at, reminder_sent_at
     */
    protected $casts = [
        'amount'                     => 'decimal:2',
        'amount_received'            => 'decimal:2',
        'total_paid_to_date'         => 'decimal:2',

        // Date columns
        'start_date'                 => 'date',
        'due_date'                   => 'date',
        'end_date'                   => 'date',
        'payment_date'               => 'date',       // ✅ added
        'termination_date'           => 'date',
        'effective_date'             => 'date',
        'next_invoice_date'          => 'date',
        'next_renewal_date'          => 'date',

        // Datetime columns
        'requested_at'               => 'datetime',
        'paid_at'                    => 'datetime',   // ✅ added
        'overdue_at'                 => 'datetime',   // ✅ added
        'cancelled_at'               => 'datetime',
        'terminated_at'              => 'datetime',
        'rejected_at'                => 'datetime',   // ✅ added
        'agreed_at'                  => 'datetime',
        'agreement_generated_at'     => 'datetime',
        'signing_invitation_sent_at' => 'datetime',
        'signing_completed_at'       => 'datetime',
        'primary_assigned_at'        => 'datetime',
        'developer_signed_at'        => 'datetime',
        'super_admin_signed_at'      => 'datetime',
        'last_signature_attempt_at'  => 'datetime',
        'last_invoice_generated_at'  => 'datetime',
        'last_payment_reminder_sent' => 'datetime',
        'last_notification_sent_at'  => 'datetime',   // ✅ added
        'reminder_sent_at'           => 'datetime',   // ✅ added

        // Arrays / JSON
        'metadata'                   => 'array',
        'shared_payment_history'     => 'array',
        'signature_metadata'         => 'array',
        'terms'                      => 'array',

        // Booleans
        'is_primary_for_billing'     => 'boolean',
        'auto_renew'                 => 'boolean',

        // Timestamps
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
        'deleted_at'                 => 'datetime',
    ];

    /**
     * Default attribute values for NEW records only.
     *
     * NOTE: This array is applied on instantiation. It does NOT overwrite
     * columns on update. If you ever see these values bleeding into
     * updates, it means the model was instantiated fresh somewhere.
     */
    protected $attributes = [
        'status'                 => 'pending',
        'payment_status'         => 'unpaid',
        'currency'               => 'GHS',
        'category'               => 'other',
        'metadata'               => '[]',
        'shared_payment_history' => '[]',
        'payment_method'         => 'bank_transfer',
        'is_primary_for_billing' => false,
        'auto_renew'             => true,
        'renewal_notice_days'    => 30,
        'payment_reminder_count' => 0,
        'signature_attempt_count'=> 0,
    ];

    /* ============================================================
     | STATUS / PAYMENT / METHOD CONSTANTS
     * ============================================================ */

    const STATUS_PENDING     = 'pending';
    const STATUS_APPROVED    = 'approved';
    const STATUS_OVERDUE     = 'overdue';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';
    const STATUS_DISPUTED    = 'disputed';
    const STATUS_ACTIVE      = 'active';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_SUPERSEDED  = 'superseded';
    const STATUS_TERMINATED  = 'terminated';
    const STATUS_DRAFT       = 'draft';

    /**
     * All statuses considered "live" — i.e., a live agreement shouldn't
     * have its status silently overwritten by an unrelated update.
     */
    const LIVE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_OVERDUE,
        self::STATUS_COMPLETED,
        self::STATUS_DISPUTED,
    ];

    const PAYMENT_STATUS_UNPAID                = 'unpaid';
    const PAYMENT_STATUS_PARTIAL               = 'partial';
    const PAYMENT_STATUS_PAID                  = 'paid';
    const PAYMENT_STATUS_REFUNDED              = 'refunded';
    const PAYMENT_STATUS_FAILED                = 'failed';
    const PAYMENT_STATUS_OVERDUE               = 'overdue';
    const PAYMENT_STATUS_PENDING_CONFIRMATION  = 'pending_confirmation';
    const PAYMENT_STATUS_PARTIAL_PENDING       = 'partial_pending';

    const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    const PAYMENT_METHOD_MOBILE_MONEY  = 'mobile_money';
    const PAYMENT_METHOD_CASH          = 'cash';
    const PAYMENT_METHOD_CHECK         = 'check';
    const PAYMENT_METHOD_OTHER         = 'other';

    const BILLING_FREQUENCY_ONE_TIME  = 'one_time';
    const BILLING_FREQUENCY_WEEKLY    = 'weekly';
    const BILLING_FREQUENCY_MONTHLY   = 'monthly';
    const BILLING_FREQUENCY_QUARTERLY = 'quarterly';
    const BILLING_FREQUENCY_YEARLY    = 'yearly';

    const MOBILE_NETWORK_MTN        = 'mtn';
    const MOBILE_NETWORK_VODAFONE   = 'vodafone';
    const MOBILE_NETWORK_AIRTELTIGO = 'airteltigo';

    const CATEGORY_HOSTING      = 'hosting';
    const CATEGORY_MAINTENANCE  = 'maintenance';
    const CATEGORY_UPGRADE      = 'upgrade';
    const CATEGORY_EMERGENCY    = 'emergency';
    const CATEGORY_LICENSE      = 'license';
    const CATEGORY_HARDWARE     = 'hardware';
    const CATEGORY_SOFTWARE     = 'software';
    const CATEGORY_CONSULTATION = 'consultation';
    const CATEGORY_OTHER        = 'other';

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_UNPAID);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    public function scopePrimaryForBilling($query)
    {
        return $query->where('is_primary_for_billing', true);
    }

    public function scopeNonPrimary($query)
    {
        return $query->where('is_primary_for_billing', false);
    }

    public function scopeForDeveloper($query, $developerSettingId)
    {
        return $query->where('developer_setting_id', $developerSettingId);
    }

    public function scopeForSuperAdmin($query, $superAdminId)
    {
        return $query->where('super_admin_id', $superAdminId);
    }

    public function scopeDue($query)
    {
        return $query->where('due_date', '<=', now())
                     ->where('payment_status', '!=', self::PAYMENT_STATUS_PAID);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('billing_frequency', $frequency);
    }

    public function scopeByBillingMonth($query, $billingMonth)
    {
        return $query->where('billing_month', $billingMonth);
    }

    public function scopeBankTransfer($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_BANK_TRANSFER);
    }

    public function scopeMobileMoney($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_MOBILE_MONEY);
    }

    public function scopeByMobileNetwork($query, $network)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_MOBILE_MONEY)
                     ->where('payment_mobile_network', $network);
    }

    public function scopeAwaitingSignature($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->whereNotNull('agreement_pdf_path')
                     ->whereNull('signed_agreement_pdf_path');
    }

    public function scopeFullySigned($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->whereNotNull('signed_agreement_pdf_path')
                     ->whereNotNull('signing_completed_at');
    }

    /**
     * Search scope.
     *
     * ✅ Fixed: `transaction_reference` → `transaction_id` (column renamed).
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'LIKE', "%{$search}%")
              ->orWhere('agreement_number', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('transaction_id', 'LIKE', "%{$search}%")   // ✅ fixed
              ->orWhere('payment_notes', 'LIKE', "%{$search}%")
              ->orWhere('payment_account_name', 'LIKE', "%{$search}%")
              ->orWhere('payment_account_number', 'LIKE', "%{$search}%")
              ->orWhere('payment_bank_name', 'LIKE', "%{$search}%")
              ->orWhere('payment_mobile_number', 'LIKE', "%{$search}%")
              ->orWhere('billing_contact_name', 'LIKE', "%{$search}%")
              ->orWhere('billing_contact_email', 'LIKE', "%{$search}%")
              ->orWhereHas('developerSetting', function ($q) use ($search) {
                  $q->where('developer_name', 'LIKE', "%{$search}%")
                    ->orWhere('developer_email', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('superAdmin', function ($q) use ($search) {
                  $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
              });
        });
    }

    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function developerSetting()
    {
        return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
    }

    public function superAdmin()
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function primaryAssignedBy()
    {
        return $this->belongsTo(User::class, 'primary_assigned_by');
    }

    public function payments()
    {
        return $this->hasMany(AgreementPayment::class, 'admin_billing_record_id');
    }

    public function confirmedPayments()
    {
        return $this->hasMany(AgreementPayment::class, 'admin_billing_record_id')
            ->where('status', 'confirmed');
    }

    public function signatures()
    {
        return $this->hasMany(AgreementSignature::class, 'agreement_id');
    }

    public function developerSignature()
    {
        return $this->hasOne(AgreementSignature::class, 'agreement_id')
            ->where('signature_type', 'developer');
    }

    public function superAdminSignature()
    {
        return $this->hasOne(AgreementSignature::class, 'agreement_id')
            ->where('signature_type', 'super_admin');
    }

    public function reminders()
    {
        return $this->hasMany(PaymentReminder::class, 'admin_billing_record_id');
    }

    public function dispute()
    {
        return $this->hasOne(BillingDispute::class, 'admin_billing_record_id');
    }

    public function previousAgreement()
    {
        return $this->belongsTo(self::class, 'previous_agreement_id');
    }

    public function nextAgreement()
    {
        return $this->hasOne(self::class, 'previous_agreement_id');
    }

    public function invoices()
    {
        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
            return $this->hasMany(BillingInvoice::class, 'agreement_id');
        }

        return $this->hasMany(BillingInvoice::class, 'admin_billing_record_id');
    }

    public function superAdminPaymentRecords()
    {
        return $this->hasMany(SuperAdminPaymentRecord::class, 'agreement_id');
    }

    /* ============================================================
     | PRIMARY SUPER ADMIN
     * ============================================================ */

    public function isPrimaryForBilling(): bool
    {
        return (bool) $this->is_primary_for_billing;
    }

    public function makePrimary(?int $assignedByUserId = null): bool
    {
        self::where('developer_setting_id', $this->developer_setting_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary_for_billing' => false]);

        $this->is_primary_for_billing = true;
        $this->primary_assigned_at    = now();
        $this->primary_assigned_by    = $assignedByUserId ?? Auth::id();

        if ($this->superAdmin && empty($this->billing_contact_name)) {
            $this->billing_contact_name  = $this->superAdmin->name;
            $this->billing_contact_email = $this->superAdmin->email;
            $this->billing_contact_phone = $this->superAdmin->phone;
        }

        return $this->save();
    }

    public function removePrimary(): bool
    {
        $this->is_primary_for_billing = false;
        return $this->save();
    }

    public static function getPrimaryForDeveloper($developerSettingId)
    {
        return self::where('developer_setting_id', $developerSettingId)
            ->where('is_primary_for_billing', true)
            ->first();
    }

    public function getBillingContactAttribute(): array
    {
        return [
            'name'  => $this->billing_contact_name  ?? $this->superAdmin?->name,
            'email' => $this->billing_contact_email ?? $this->superAdmin?->email,
            'phone' => $this->billing_contact_phone ?? $this->superAdmin?->phone,
        ];
    }

    /* ============================================================
     | SIGNATURES
     * ============================================================ */

    public function isDeveloperSigned(): bool
    {
        return $this->developer_signed_at !== null
            || $this->signatures()->where('signature_type', 'developer')->exists();
    }

    public function isSuperAdminSigned(): bool
    {
        return $this->super_admin_signed_at !== null
            || $this->signatures()->where('signature_type', 'super_admin')->exists();
    }

    public function isFullySigned(): bool
    {
        return $this->isDeveloperSigned() && $this->isSuperAdminSigned();
    }

    public function recordDeveloperSignature($signatureId = null): bool
    {
        $this->developer_signed_at = now();
        return $this->save();
    }

    public function recordSuperAdminSignature($signatureId = null): bool
    {
        $this->super_admin_signed_at = now();

        if ($this->isDeveloperSigned() && $this->isSuperAdminSigned()) {
            $this->signing_completed_at = now();
            $this->status               = self::STATUS_ACTIVE;
        }

        return $this->save();
    }

    public function incrementSignatureAttempt(): int
    {
        $this->signature_attempt_count++;
        $this->last_signature_attempt_at = now();
        $this->save();

        return $this->signature_attempt_count;
    }

    public function hasReachedMaxSignatureAttempts(int $maxAttempts = 5): bool
    {
        return $this->signature_attempt_count >= $maxAttempts;
    }

    /* ============================================================
     | PAYMENT DETAILS ACCESSORS
     * ============================================================ */

    public function getPaymentMethodDetailsAttribute(): array
    {
        $details = [
            'method'       => $this->payment_method ?? self::PAYMENT_METHOD_BANK_TRANSFER,
            'method_label' => $this->payment_method_name,
            'display_text' => '',
            'fields'       => [],
        ];

        switch ($this->payment_method) {
            case self::PAYMENT_METHOD_BANK_TRANSFER:
                $details['display_text'] = sprintf(
                    '%s - %s (%s)',
                    $this->payment_bank_name ?? 'Bank Transfer',
                    $this->payment_account_number ?? 'N/A',
                    $this->payment_account_name ?? 'N/A'
                );
                $details['fields'] = [
                    'account_name'   => $this->payment_account_name,
                    'account_number' => $this->payment_account_number,
                    'bank_name'      => $this->payment_bank_name,
                    'bank_branch'    => $this->payment_bank_branch,
                ];
                break;

            case self::PAYMENT_METHOD_MOBILE_MONEY:
                $networkName = $this->mobile_network_name;
                $details['display_text'] = sprintf(
                    '%s - %s (%s)',
                    $networkName,
                    $this->payment_mobile_number ?? 'N/A',
                    $this->payment_account_name ?? 'N/A'
                );
                $details['fields'] = [
                    'mobile_number'        => $this->payment_mobile_number,
                    'mobile_network'       => $this->payment_mobile_network,
                    'mobile_network_label' => $networkName,
                    'account_name'         => $this->payment_mobile_account_name ?? $this->payment_account_name,
                ];
                break;

            case self::PAYMENT_METHOD_CASH:
                $details['display_text'] = 'Cash Payment';
                $details['fields']       = [];
                break;

            case self::PAYMENT_METHOD_CHECK:
                $details['display_text'] = sprintf(
                    'Check payable to: %s',
                    $this->payment_account_name ?? 'Developer'
                );
                $details['fields'] = [
                    'payable_to' => $this->payment_account_name ?? 'Developer',
                ];
                break;

            default:
                $details['display_text'] = 'Standard Payment';
                break;
        }

        return $details;
    }

    public function getMobileNetworkNameAttribute(): string
    {
        $networks = [
            self::MOBILE_NETWORK_MTN        => 'MTN Mobile Money',
            self::MOBILE_NETWORK_VODAFONE   => 'Vodafone Cash',
            self::MOBILE_NETWORK_AIRTELTIGO => 'AirtelTigo Money',
        ];

        return $networks[$this->payment_mobile_network]
            ?? ucfirst($this->payment_mobile_network ?? 'Mobile Money');
    }

    public function hasBankDetails(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_BANK_TRANSFER
            && !empty($this->payment_account_number);
    }

    public function hasMobileMoneyDetails(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_MOBILE_MONEY
            && !empty($this->payment_mobile_number);
    }

    public function getBankDetailsAttribute(): string
    {
        if (!$this->hasBankDetails()) {
            return 'N/A';
        }

        $parts = [];
        if ($this->payment_bank_name)      $parts[] = $this->payment_bank_name;
        if ($this->payment_account_number) $parts[] = "Acc: {$this->payment_account_number}";
        if ($this->payment_account_name)   $parts[] = "Name: {$this->payment_account_name}";
        if ($this->payment_bank_branch)    $parts[] = "Branch: {$this->payment_bank_branch}";

        return implode(' | ', $parts);
    }

    public function getMobileMoneyDetailsAttribute(): string
    {
        if (!$this->hasMobileMoneyDetails()) {
            return 'N/A';
        }

        $parts = [];
        if ($this->payment_mobile_network) $parts[] = $this->mobile_network_name;
        if ($this->payment_mobile_number)  $parts[] = $this->payment_mobile_number;

        $accountName = $this->payment_mobile_account_name ?? $this->payment_account_name;
        if ($accountName) $parts[] = "Name: {$accountName}";

        return implode(' | ', $parts);
    }

    /* ============================================================
     | SHARED PAYMENTS
     * ============================================================ */

    public function getSharedPaymentHistoryAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?? [];
    }

    /**
     * Records a shared payment in the history JSON column.
     *
     * NOTE: the JSON key is `transaction_reference` (that's just a
     * nested array key inside the JSON blob, not a column name). It's
     * fine to keep it named that — no DB column is involved.
     */
    public function addSharedPayment($superAdminId, $amount, $paymentDate, ?string $transactionReference = null): array
    {
        $history = $this->shared_payment_history;
        $billingMonth = $this->billing_month ?? Carbon::now()->format('Y-m');

        if (!isset($history[$billingMonth])) {
            $history[$billingMonth] = [];
        }

        $history[$billingMonth][] = [
            'super_admin_id'        => $superAdminId,
            'amount'                => $amount,
            'payment_date'          => $paymentDate,
            'transaction_reference' => $transactionReference,
            'recorded_at'           => now()->toDateTimeString(),
        ];

        $this->total_paid_to_date     += $amount;
        $this->shared_payment_history = $history;
        $this->save();

        return $history;
    }

    public function getTotalPaidThisMonthAttribute(): float
    {
        $billingMonth = $this->billing_month ?? Carbon::now()->format('Y-m');
        $history = $this->shared_payment_history;

        $total = 0;
        if (isset($history[$billingMonth])) {
            foreach ($history[$billingMonth] as $payment) {
                $total += $payment['amount'];
            }
        }

        return $total;
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->amount - $this->total_paid_this_month);
    }

    /* ============================================================
     | STATUS CHECKS
     * ============================================================ */

    public function isOverdue()
    {
        if (in_array($this->status, [self::STATUS_PENDING, self::STATUS_CANCELLED], true)) {
            return false;
        }

        if ($this->due_date && $this->due_date < now()->startOfDay()) {
            return $this->payment_status !== self::PAYMENT_STATUS_PAID;
        }

        return false;
    }

    public function isPending()   { return $this->status === self::STATUS_PENDING; }
    public function isCompleted() { return $this->status === self::STATUS_COMPLETED; }
    public function isActive()    { return $this->status === self::STATUS_ACTIVE; }
    public function isPaid()      { return $this->payment_status === self::PAYMENT_STATUS_PAID; }
    public function isPartiallyPaid() { return $this->payment_status === self::PAYMENT_STATUS_PARTIAL; }
    public function isUnpaid()    { return $this->payment_status === self::PAYMENT_STATUS_UNPAID; }

    public function canBeCancelled()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE], true)
            && $this->payment_status !== self::PAYMENT_STATUS_PAID;
    }

    public function canBeMarkedAsReceived()
    {
        return !$this->isPaid()
            && !$this->isCompleted()
            && $this->status !== self::STATUS_CANCELLED;
    }

    public function canBeSigned(): bool
    {
        return $this->status === self::STATUS_PENDING
            && !$this->isFullySigned()
            && $this->agreement_pdf_path !== null;
    }

    public function canBeTerminated(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /* ============================================================
     | ATTRIBUTE ACCESSORS
     * ============================================================ */

    public function getOutstandingBalanceAttribute()
    {
        if ($this->isPaid()) return 0.00;
        if ($this->isPartiallyPaid()) return $this->amount - $this->amount_received;
        return $this->amount;
    }

    public function getPaymentPercentageAttribute()
    {
        if ($this->amount <= 0) return 0;
        if ($this->isPaid()) return 100;
        if ($this->isPartiallyPaid() && $this->amount_received > 0) {
            return round(($this->amount_received / $this->amount) * 100, 2);
        }
        return 0;
    }

    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format((float) $this->amount, 2);
    }

    public function getFormattedAmountReceivedAttribute()
    {
        return $this->currency . ' ' . number_format((float) ($this->amount_received ?? 0), 2);
    }

    public function getFormattedOutstandingBalanceAttribute()
    {
        return $this->currency . ' ' . number_format((float) $this->outstanding_balance, 2);
    }

    public function getPaymentMethodNameAttribute()
    {
        $methods = [
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_METHOD_MOBILE_MONEY  => 'Mobile Money',
            self::PAYMENT_METHOD_CASH          => 'Cash',
            self::PAYMENT_METHOD_CHECK         => 'Check',
            self::PAYMENT_METHOD_OTHER         => 'Other',
        ];

        return $methods[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    public function getBillingFrequencyNameAttribute()
    {
        $frequencies = [
            self::BILLING_FREQUENCY_ONE_TIME  => 'One Time',
            self::BILLING_FREQUENCY_WEEKLY    => 'Weekly',
            self::BILLING_FREQUENCY_MONTHLY   => 'Monthly',
            self::BILLING_FREQUENCY_QUARTERLY => 'Quarterly',
            self::BILLING_FREQUENCY_YEARLY    => 'Yearly',
        ];

        return $frequencies[$this->billing_frequency] ?? ucfirst((string) $this->billing_frequency);
    }

    public function getCategoryNameAttribute()
    {
        $categories = [
            self::CATEGORY_HOSTING      => 'Hosting',
            self::CATEGORY_MAINTENANCE  => 'Maintenance',
            self::CATEGORY_UPGRADE      => 'Upgrade',
            self::CATEGORY_EMERGENCY    => 'Emergency',
            self::CATEGORY_LICENSE      => 'License',
            self::CATEGORY_HARDWARE     => 'Hardware',
            self::CATEGORY_SOFTWARE     => 'Software',
            self::CATEGORY_CONSULTATION => 'Consultation',
            self::CATEGORY_OTHER        => 'Other',
        ];

        return $categories[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getStatusBadgeColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING    => 'warning',
            self::STATUS_APPROVED   => 'info',
            self::STATUS_OVERDUE    => 'danger',
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_CANCELLED  => 'secondary',
            self::STATUS_DISPUTED   => 'dark',
            self::STATUS_ACTIVE     => 'success',
            self::STATUS_REJECTED   => 'danger',
            self::STATUS_SUPERSEDED => 'info',
            self::STATUS_TERMINATED => 'secondary',
            self::STATUS_DRAFT      => 'secondary',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    public function getPaymentStatusBadgeColorAttribute()
    {
        $colors = [
            self::PAYMENT_STATUS_UNPAID               => 'danger',
            self::PAYMENT_STATUS_PARTIAL              => 'warning',
            self::PAYMENT_STATUS_PAID                 => 'success',
            self::PAYMENT_STATUS_REFUNDED             => 'info',
            self::PAYMENT_STATUS_FAILED               => 'dark',
            self::PAYMENT_STATUS_OVERDUE              => 'danger',
            self::PAYMENT_STATUS_PENDING_CONFIRMATION => 'warning',
            self::PAYMENT_STATUS_PARTIAL_PENDING      => 'warning',
        ];

        return $colors[$this->payment_status] ?? 'secondary';
    }

    public function getSignatureStatusTextAttribute(): string
    {
        if ($this->isFullySigned()) return 'Fully Signed';
        if ($this->isDeveloperSigned()) return 'Developer Signed - Awaiting Super Admin';
        if ($this->isSuperAdminSigned()) return 'Super Admin Signed - Awaiting Developer';
        return 'Not Signed';
    }

    /* ============================================================
     | ACTIONS
     * ============================================================ */

    /**
     * Mark this billing record as paid.
     *
     * ✅ Reconciled with the actual schema:
     *   - `received_date`        → `payment_date`
     *   - `transaction_reference`→ `transaction_id`
     *   - `completed_at`         → `paid_at`
     *   - `completed_by`         → kept (column now exists)
     *
     * Under strict mode, the previous version would throw
     * "Unknown column 'received_date' ..." on every call.
     */
    public function markAsPaid($amount, $receivedDate, ?string $transactionReference = null, ?string $notes = null)
    {
        if ($this->isPaid() || $this->isCompleted()) {
            return false;
        }

        $this->amount_received = $amount;
        $this->payment_date    = $receivedDate;          // ✅ was received_date
        $this->transaction_id  = $transactionReference;  // ✅ was transaction_reference
        $this->payment_notes   = $notes;

        $this->payment_status = $amount >= $this->amount
            ? self::PAYMENT_STATUS_PAID
            : self::PAYMENT_STATUS_PARTIAL;

        $this->status       = self::STATUS_COMPLETED;
        $this->completed_by = Auth::id();                // ✅ column exists
        $this->paid_at      = now();                     // ✅ was completed_at

        return $this->save();
    }

    /**
     * Mark as cancelled.
     *
     * All three columns (`cancelled_by`, `cancelled_at`,
     * `cancellation_reason`) exist in the schema — added via the
     * migration we ran earlier.
     */
    public function markAsCancelled(?string $reason = 'Cancelled by user')
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->status              = self::STATUS_CANCELLED;
        $this->cancellation_reason = $reason;
        $this->cancelled_by        = Auth::id();
        $this->cancelled_at        = now();

        return $this->save();
    }

    public function markAsActive(?int $userId = null)
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status     = self::STATUS_ACTIVE;
        $this->agreed_at  = now();
        $this->agreed_by  = $userId ?? Auth::id();

        return $this->save();
    }

    public function markAsTerminated($reason, ?int $userId = null, ?Carbon $effectiveDate = null)
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->status             = self::STATUS_TERMINATED;
        $this->termination_reason = $reason;
        $this->terminated_by      = $userId ?? Auth::id();
        $this->terminated_at      = now();
        $this->termination_date   = $effectiveDate ?? now();

        return $this->save();
    }

    public function updateStatusBasedOnDueDate()
    {
        if ($this->isOverdue() && $this->status !== self::STATUS_OVERDUE) {
            $this->status = self::STATUS_OVERDUE;
            return true;
        }

        return false;
    }

    public function updatePaymentStatus()
    {
        $status = self::PAYMENT_STATUS_UNPAID;

        if ($this->amount_received >= $this->amount) {
            $status = self::PAYMENT_STATUS_PAID;
        } elseif ($this->amount_received > 0) {
            $status = self::PAYMENT_STATUS_PARTIAL;
        }

        if ($status !== self::PAYMENT_STATUS_PAID && $this->isOverdue()) {
            $this->status = self::STATUS_OVERDUE;
        }

        $this->payment_status = $status;

        return $this->save();
    }

    public function sendForSigning(?int $userId = null)
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->signing_invitation_sent_at = now();
        $this->signing_invitation_sent_by = $userId ?? Auth::id();

        return $this->save();
    }

    public function recordPayment($amount, array $paymentData = [])
    {
        if (!$this->canBeMarkedAsReceived()) {
            return null;
        }

        $payment = $this->payments()->create(array_merge([
            'amount_paid'  => $amount,
            'currency'     => $this->currency,
            'payment_date' => now(),
            'status'       => 'pending_confirmation',
            'recorded_by'  => Auth::id(),
            'recorded_at'  => now(),
        ], $paymentData));

        $this->amount_received += $amount;
        $this->save();

        $this->updatePaymentStatus();

        return $payment;
    }

    public function addTransaction($amount, $type, ?string $reference = null, ?string $method = null, ?string $notes = null)
    {
        return $this->payments()->create([
            'amount_paid'           => $amount,
            'currency'              => $this->currency,
            'transaction_type'      => $type,
            'transaction_reference' => $reference,
            'payment_method'        => $method ?? $this->payment_method,
            'confirmed_by'          => Auth::id(),
            'confirmed_at'          => now(),
            'notes'                 => $notes,
        ]);
    }

    public function addReminder($type = 'general', ?string $notes = null)
    {
        if (!$this->superAdmin) {
            return null;
        }

        return $this->reminders()->create([
            'sent_to'       => $this->superAdmin->email,
            'sent_by'       => Auth::id(),
            'reminder_type' => $type,
            'sent_at'       => now(),
            'notes'         => $notes,
        ]);
    }

    public function generateAgreementNumber(): string
    {
        $prefix = 'AGR';
        $year   = date('Y');
        $month  = date('m');

        $lastAgreement = self::where('agreement_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('agreement_number', 'desc')
            ->first();

        if ($lastAgreement) {
            $lastNumber = (int) substr($lastAgreement->agreement_number, -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return "{$prefix}-{$year}{$month}-{$nextNumber}";
    }

    public function calculateNextBillingDate(): ?Carbon
    {
        $startDate = Carbon::parse($this->start_date ?? $this->created_at);

        return match ($this->billing_frequency) {
            self::BILLING_FREQUENCY_WEEKLY    => $startDate->copy()->addWeek(),
            self::BILLING_FREQUENCY_MONTHLY   => $startDate->copy()->addMonth(),
            self::BILLING_FREQUENCY_QUARTERLY => $startDate->copy()->addMonths(3),
            self::BILLING_FREQUENCY_YEARLY    => $startDate->copy()->addYear(),
            default                            => null,
        };
    }

    /* ============================================================
     | PERMISSIONS
     * ============================================================ */

    public function canView()
    {
        $user = Auth::user();
        if (!$user) return false;

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;

        if (method_exists($user, 'isDeveloper')
            && $user->isDeveloper()
            && $this->developer_setting_id === optional($user->developerSetting)->id) {
            return true;
        }

        if ($this->requested_by === $user->id) return true;
        if ($this->super_admin_id === $user->id) return true;

        return false;
    }

    public function canUpdate()
    {
        $user = Auth::user();
        if (!$user) return false;

        if ($this->isCompleted() || $this->status === self::STATUS_CANCELLED) return false;

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;

        if (method_exists($user, 'isDeveloper')
            && $user->isDeveloper()
            && $this->developer_setting_id === optional($user->developerSetting)->id
            && !$this->isPaid()) {
            return true;
        }

        if ($this->requested_by === $user->id && $this->isPending()) return true;

        return false;
    }

    public function canDelete()
    {
        $user = Auth::user();
        if (!$user) return false;
        if (!$this->isPending()) return false;

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;

        if (method_exists($user, 'isDeveloper')
            && $user->isDeveloper()
            && $this->developer_setting_id === optional($user->developerSetting)->id) {
            return true;
        }

        if ($this->requested_by === $user->id) return true;

        return false;
    }

    /* ============================================================
     | STATISTICS
     * ============================================================ */

    public static function getStatistics($developerSettingId = null, $startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($developerSettingId) {
            $query->where('developer_setting_id', $developerSettingId);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $total         = (clone $query)->count();
        $totalAmount   = (clone $query)->sum('amount');
        $totalReceived = (clone $query)->sum('amount_received');
        $pending       = (clone $query)->where('status', self::STATUS_PENDING)->count();
        $completed     = (clone $query)->where('status', self::STATUS_COMPLETED)->count();
        $overdue       = (clone $query)->where('status', self::STATUS_OVERDUE)->count();
        $cancelled     = (clone $query)->where('status', self::STATUS_CANCELLED)->count();
        $active        = (clone $query)->where('status', self::STATUS_ACTIVE)->count();

        return [
            'total_requests'      => $total,
            'total_amount'        => $totalAmount,
            'total_received'      => $totalReceived,
            'pending_requests'    => $pending,
            'completed_requests'  => $completed,
            'overdue_requests'    => $overdue,
            'cancelled_requests'  => $cancelled,
            'active_agreements'   => $active,
            'outstanding_balance' => $totalAmount - $totalReceived,
            'completion_rate'     => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'average_amount'      => $total > 0 ? round($totalAmount / $total, 2) : 0,
        ];
    }

    public static function getPaymentMethodBreakdown($developerSettingId = null)
    {
        $query = self::query();

        if ($developerSettingId) {
            $query->where('developer_setting_id', $developerSettingId);
        }

        return $query->select(
                'payment_method',
                \DB::raw('COUNT(*) as count'),
                \DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                $methods = [
                    self::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
                    self::PAYMENT_METHOD_MOBILE_MONEY  => 'Mobile Money',
                    self::PAYMENT_METHOD_CASH          => 'Cash',
                    self::PAYMENT_METHOD_CHECK         => 'Check',
                    self::PAYMENT_METHOD_OTHER         => 'Other',
                ];

                return [
                    'method'       => $item->payment_method,
                    'method_label' => $methods[$item->payment_method] ?? ucfirst($item->payment_method),
                    'count'        => $item->count,
                    'total_amount' => $item->total_amount,
                ];
            });
    }

    public static function getPrimaryStatistics($developerSettingId)
    {
        $primaryAgreement = self::getPrimaryForDeveloper($developerSettingId);

        if (!$primaryAgreement) {
            return null;
        }

        $totalPaidThisMonth = $primaryAgreement->total_paid_this_month;

        return [
            'agreement_id'       => $primaryAgreement->id,
            'agreement_number'   => $primaryAgreement->agreement_number,
            'amount'             => $primaryAgreement->amount,
            'total_paid'         => $totalPaidThisMonth,
            'remaining'          => $primaryAgreement->amount - $totalPaidThisMonth,
            'billing_month'      => $primaryAgreement->billing_month ?? Carbon::now()->format('Y-m'),
            'payment_percentage' => $primaryAgreement->amount > 0
                ? round(($totalPaidThisMonth / $primaryAgreement->amount) * 100, 2)
                : 0,
            'billing_contact'    => $primaryAgreement->billing_contact,
        ];
    }

    public function activityLogs()
    {
        return collect();
    }

    /* ============================================================
     | BOOT — intentionally empty.
     |
     | All model event listeners (creating / saving / updated /
     | created / deleted) for AdminBillingRecord are registered in
     | AppServiceProvider::registerBillingModelListeners().
     |
     | Reason: this model's boot() has, at various points, failed to
     | run reliably (opcache serving stale compiled bytecode, or a
     | subtle trait-hierarchy conflict). Registering from the provider
     | guarantees the listeners are attached on every request.
     |
     | DO NOT re-add static::saving/creating/updated/created/deleted
     | hooks here. Doing so would double-register every hook and
     | produce duplicate audit-log entries on every write.
     * ============================================================ */
}