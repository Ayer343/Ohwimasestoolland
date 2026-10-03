<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DeveloperSetting extends Model
{
    use SoftDeletes;

    protected $table = 'developer_settings';

    /* ============================================================
     | FILLABLE — every column that DeveloperSettingsController,
     | DeveloperBillingController, and the settings UI actually write.
     * ============================================================ */
    protected $fillable = [
        // ---------- Developer Information ----------
        'developer_name',
        'developer_email',
        'developer_phone',
        'developer_company',
        'developer_website',
        'developer_address',

        // ---------- System Access ----------
        'developer_access_enabled',
        'allowed_ips',
        'allowed_features',
        'developer_secret_key',

        // ---------- Billing Core ----------
        'monthly_billing_amount',
        'billing_currency',
        'billing_cycle',
        'billing_start_date',
        'next_billing_date',
        'billing_active',
        'billing_status',
        'billing_rules',

        // ---------- Primary Super Admin ----------
        'primary_super_admin_id',
        'billing_contact_name',
        'billing_contact_email',
        'billing_contact_phone',

        // ---------- Payment Method ----------
        'payment_method',
        'payment_mobile_number',
        'payment_mobile_network',
        'payment_account_name',
        'payment_account_number',
        'payment_bank_name',
        'payment_bank_branch',

        // ---------- Email / SMTP ----------
        'developer_smtp_host',
        'developer_smtp_port',
        'developer_smtp_username',
        'developer_smtp_password',
        'developer_smtp_encryption',
        'developer_email_from',
        'developer_email_from_name',

        // ---------- API ----------
        'api_base_url',
        'api_version',
        'api_enabled',
        'api_rate_limits',

        // ---------- Monitoring ----------
        'enable_system_monitoring',
        'enable_error_tracking',
        'enable_performance_monitoring',
        'log_retention_days',

        // ---------- Maintenance ----------
        'maintenance_mode',
        'maintenance_message',
        'maintenance_allowed_ips',
        'maintenance_start',
        'maintenance_end',

        // ---------- Security ----------
        'enable_two_factor',
        'session_timeout',
        'max_login_attempts',
        'password_expiry_days',

        // ---------- Performance ----------
        'cache_duration',
        'enable_query_cache',
        'max_upload_size',
        'max_execution_time',

        // ---------- Backup ----------
        'enable_auto_backup',
        'backup_frequency',
        'backup_retention_days',
        'backup_storage_locations',

        // ---------- Analytics & Reporting ----------
        'enable_analytics',
        'analytics_provider',
        'analytics_tracking_id',
        'enable_daily_reports',
        'report_recipients',

        // ---------- Custom / Metadata ----------
        'custom_config',
        'feature_flags',
        'environment_variables',
        'status',
        'notes',
        'metadata',
    ];

    /* ============================================================
     | CASTS — canonical types returned by the model
     * ============================================================ */
    protected $casts = [
        // Booleans
        'developer_access_enabled'       => 'boolean',
        'api_enabled'                    => 'boolean',
        'enable_system_monitoring'       => 'boolean',
        'enable_error_tracking'          => 'boolean',
        'enable_performance_monitoring'  => 'boolean',
        'enable_two_factor'              => 'boolean',
        'enable_query_cache'             => 'boolean',
        'enable_analytics'               => 'boolean',
        'enable_daily_reports'           => 'boolean',
        'enable_auto_backup'             => 'boolean',
        'maintenance_mode'               => 'boolean',
        'billing_active'                 => 'boolean',

        // Arrays / JSON
        'allowed_ips'                    => 'array',
        'allowed_features'               => 'array',
        'report_recipients'              => 'array',
        'api_rate_limits'                => 'array',
        'maintenance_allowed_ips'        => 'array',
        'backup_storage_locations'       => 'array',
        'custom_config'                  => 'array',
        'feature_flags'                  => 'array',
        'environment_variables'          => 'array',
        'metadata'                       => 'array',
        'billing_rules'                  => 'array',

        // Dates
        'billing_start_date'             => 'date',
        'next_billing_date'              => 'date',
        'maintenance_start'              => 'datetime',
        'maintenance_end'                => 'datetime',

        // Numbers
        'monthly_billing_amount'         => 'decimal:2',
        'log_retention_days'             => 'integer',
        'cache_duration'                 => 'integer',
        'max_upload_size'                => 'integer',
        'max_execution_time'             => 'integer',
        'backup_retention_days'          => 'integer',
        'session_timeout'                => 'integer',
        'max_login_attempts'             => 'integer',
        'password_expiry_days'           => 'integer',
        'primary_super_admin_id'         => 'integer',

        // Encrypted
        'developer_smtp_password'        => 'encrypted',
    ];

    /* ============================================================
     | CONSTANTS
     * ============================================================ */
    const CACHE_DURATION     = 300;
    const CACHE_KEY          = 'developer_settings';
    const CACHE_KEY_BY_EMAIL = 'developer_settings_email_';

    /** ✅ BILLING: dedicated cache keys */
    const BILLING_STATE_CACHE_KEY = 'developer_setting_billing_state';
    const BILLING_STATE_TTL       = 300;
    const PRIMARY_AGREEMENT_CACHE_KEY = 'developer_primary_billing_agreement';

    // Developer account statuses
    const STATUS_ACTIVE    = 'active';
    const STATUS_INACTIVE  = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    // Billing statuses (mirrors billing_status column)
    const BILLING_STATUS_PENDING   = 'pending';
    const BILLING_STATUS_ACTIVE    = 'active';
    const BILLING_STATUS_INACTIVE  = 'inactive';
    const BILLING_STATUS_SUSPENDED = 'suspended';

    // Billing cycles
    const BILLING_CYCLE_WEEKLY    = 'weekly';
    const BILLING_CYCLE_MONTHLY   = 'monthly';
    const BILLING_CYCLE_QUARTERLY = 'quarterly';
    const BILLING_CYCLE_YEARLY    = 'yearly';

    // Payment methods
    const PAYMENT_METHOD_BANK   = 'bank_transfer';
    const PAYMENT_METHOD_MOBILE = 'mobile_money';
    const PAYMENT_METHOD_CASH   = 'cash';
    const PAYMENT_METHOD_CHECK  = 'check';

    // ✅ BILLING: agreement statuses (mirrors AdminBillingRecord)
    const AGREEMENT_STATUS_PENDING    = 'pending';
    const AGREEMENT_STATUS_ACTIVE     = 'active';
    const AGREEMENT_STATUS_COMPLETED  = 'completed';
    const AGREEMENT_STATUS_TERMINATED = 'terminated';
    const AGREEMENT_STATUS_REJECTED   = 'rejected';

    // ✅ BILLING: canonical state strings
    const STATE_NOT_CONFIGURED    = 'not_configured';
    const STATE_PENDING_SIGNATURE = 'pending_signature';
    const STATE_ACTIVE_CURRENT    = 'active_current';
    const STATE_ACTIVE_DUE_SOON   = 'active_due_soon';
    const STATE_ACTIVE_OVERDUE    = 'active_overdue';
    const STATE_ACTIVE_SUSPENDED  = 'active_suspended';

    /**
     * Canonical billing_rules keys and their defaults.
     * Single source of truth for the model, the settings controller, the
     * billing service, and the SendPaymentReminderJob.
     *
     * When a key is missing from the stored JSON, the value here is used.
     * Individual getters override the default if the semantics differ from
     * a straight fallback (see isAutoInvoiceGenerationEnabled()).
     */
    const DEFAULT_BILLING_RULES = [
        'auto_generate_invoices'         => false,
        'send_payment_reminders'         => false,
        'invoice_due_days'               => 30,
        'reminder_days_before'           => [7, 3, 1],
        'grace_period_days'              => 7,
        'hard_lock_landlords_on_overdue' => false,
        'hard_lock_landlords_after_days' => 0,
    ];

    /* ============================================================
     | BOOT
     * ============================================================ */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            $model->clearCache();
            $model->clearBillingStateCache();
        });

        static::deleted(function ($model) {
            $model->clearCache();
            $model->clearBillingStateCache();
        });

        static::restored(function ($model) {
            $model->clearCache();
            $model->clearBillingStateCache();
        });
    }

    /* ============================================================
     | STATIC ACCESSORS
     * ============================================================ */

    public static function getSettings()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            return self::first() ?? self::createDefaultSettings();
        });
    }

    /**
     * ✅ BILLING: canonical "current row" accessor.
     *
     * Same idea as getSettings() but with a dedicated cache key so
     * middleware and the billing banner don't collide with anything
     * else that happens to use getSettings().
     */
    public static function current(): ?self
    {
        return Cache::remember('developer_setting_current', self::BILLING_STATE_TTL, function () {
            return self::first();
        });
    }

    /**
     * ✅ BILLING: forget the cached current row + billing state.
     */
    public static function forgetCurrent(): void
    {
        Cache::forget('developer_setting_current');
        Cache::forget(self::BILLING_STATE_CACHE_KEY);
        Cache::forget(self::PRIMARY_AGREEMENT_CACHE_KEY);
    }

    public static function getByEmail($email)
    {
        $cacheKey = self::CACHE_KEY_BY_EMAIL . md5($email);

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($email) {
            return self::where('developer_email', $email)->first();
        });
    }

    public static function isAccessEnabled(): bool
    {
        $settings = self::getSettings();

        return $settings
            && $settings->developer_access_enabled
            && $settings->status === self::STATUS_ACTIVE;
    }

    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function user()
    {
        return $this->belongsTo(User::class, 'developer_email', 'email');
    }

    public function primarySuperAdmin()
    {
        return $this->belongsTo(User::class, 'primary_super_admin_id');
    }

    public function billingAgreements()
    {
        return $this->hasMany(AdminBillingRecord::class, 'developer_setting_id');
    }

    public function primaryBillingAgreement()
    {
        return $this->hasOne(AdminBillingRecord::class, 'developer_setting_id')
            ->where('is_primary_for_billing', true);
    }

    public function billingInvoices()
    {
        return $this->hasMany(BillingInvoice::class, 'developer_setting_id');
    }

    /* ============================================================
     | ✅ BILLING STATE MACHINE
     | ------------------------------------------------------------
     | Single source of truth for "what is the current billing
     | situation?" Used by:
     |   - BillingAccessService
     |   - EnsureSuperAdminBillingAccess middleware
     |   - EnsureLandlordTenantBillingPassthrough middleware
     |   - ChecksBillingAccess trait
     |   - Every view composer that renders a banner
     * ============================================================ */

    /**
     * Cached billing state.
     *
     * Returns one of:
     *   not_configured, pending_signature, active_current,
     *   active_due_soon, active_overdue, active_suspended
     */
    public function getBillingStateAttribute(): string
    {
        return Cache::remember(
            self::BILLING_STATE_CACHE_KEY,
            self::BILLING_STATE_TTL,
            fn () => $this->computeBillingState()
        );
    }

    /**
     * Uncached billing-state computation.
     */
    public function computeBillingState(): string
    {
        if (!$this->primary_super_admin_id || !$this->billing_status) {
            return self::STATE_NOT_CONFIGURED;
        }

        $agreement = AdminBillingRecord::where('developer_setting_id', $this->id)
            ->where('is_primary_for_billing', true)
            ->where('status', self::AGREEMENT_STATUS_ACTIVE)
            ->first();

        if (!$agreement) {
            return $this->billing_status === self::BILLING_STATUS_PENDING
                ? self::STATE_PENDING_SIGNATURE
                : self::STATE_NOT_CONFIGURED;
        }

        $dueDate = $agreement->due_date ? Carbon::parse($agreement->due_date) : null;

        if (!$dueDate) {
            return self::STATE_ACTIVE_CURRENT;
        }

        $rules     = $this->getBillingRulesArray();
        $graceDays = (int) ($rules['grace_period_days'] ?? 7);
        $dueSoon   = (int) config('billing.due_soon_threshold_days', 5);

        $now = now();

        if ($now->lt($dueDate)) {
            return $now->diffInDays($dueDate) <= $dueSoon
                ? self::STATE_ACTIVE_DUE_SOON
                : self::STATE_ACTIVE_CURRENT;
        }

        if ($now->lt($dueDate->copy()->addDays($graceDays))) {
            return self::STATE_ACTIVE_OVERDUE;
        }

        return self::STATE_ACTIVE_SUSPENDED;
    }

    /* ---------------- state predicates ---------------- */

    public function isNotConfigured(): bool
    {
        return $this->billing_state === self::STATE_NOT_CONFIGURED;
    }

    public function isPendingSignature(): bool
    {
        return $this->billing_state === self::STATE_PENDING_SIGNATURE;
    }

    public function isBillingCurrent(): bool
    {
        return $this->billing_state === self::STATE_ACTIVE_CURRENT;
    }

    public function isDueSoon(): bool
    {
        return $this->billing_state === self::STATE_ACTIVE_DUE_SOON;
    }

    public function isOverdue(): bool
    {
        return $this->billing_state === self::STATE_ACTIVE_OVERDUE;
    }

    public function isSuspended(): bool
    {
        return $this->billing_state === self::STATE_ACTIVE_SUSPENDED;
    }

    /* ---------------- policy decisions ---------------- */

    /**
     * Should we restrict the super admin to read + pay only?
     *
     * Yes when:
     *   - there's no signed agreement yet (pending_signature)
     *   - the primary agreement is overdue or suspended
     */
    public function isBillingRestrictingSuperAdmin(): bool
    {
        return in_array($this->billing_state, [
            self::STATE_PENDING_SIGNATURE,
            self::STATE_ACTIVE_OVERDUE,
            self::STATE_ACTIVE_SUSPENDED,
        ], true);
    }

    /**
     * Should admin staff be put into read-only mode?
     *
     * Yes only when the agreement is actually overdue or suspended.
     * Pending signature does not yet restrict admins.
     */
    public function isBillingRestrictingAdmins(): bool
    {
        return in_array($this->billing_state, [
            self::STATE_ACTIVE_OVERDUE,
            self::STATE_ACTIVE_SUSPENDED,
        ], true);
    }

    /**
     * Should landlords and tenants be hard-locked?
     *
     * Default: NO. Their payments fund the resolution of the debt.
     * Only true when the developer explicitly opted in AND the
     * agreement has been overdue past `hard_lock_landlords_after_days`.
     */
    public function isBillingBlockingLandlordsAndTenants(): bool
    {
        $rules = $this->getBillingRulesArray();

        if (empty($rules['hard_lock_landlords_on_overdue'])) {
            return false;
        }

        $lockAfterDays = (int) ($rules['hard_lock_landlords_after_days'] ?? 0);
        if ($lockAfterDays <= 0) {
            return false;
        }

        $agreement = $this->primary_billing_agreement;
        if (!$agreement || !$agreement->due_date) {
            return false;
        }

        $daysOverdue = Carbon::parse($agreement->due_date)->diffInDays(now(), false);

        return $daysOverdue >= $lockAfterDays;
    }

    /* ---------------- cached derived values ---------------- */

    /**
     * The primary billing agreement for this developer, cached.
     */
    public function getPrimaryBillingAgreementAttribute(): ?AdminBillingRecord
    {
        return Cache::remember(
            self::PRIMARY_AGREEMENT_CACHE_KEY,
            self::BILLING_STATE_TTL,
            function () {
                return AdminBillingRecord::where('developer_setting_id', $this->id)
                    ->where('is_primary_for_billing', true)
                    ->where('status', self::AGREEMENT_STATUS_ACTIVE)
                    ->first();
            }
        );
    }

    /**
     * Days until the primary billing agreement is due.
     * Negative = overdue. Null = no agreement / no due date.
     */
    public function daysUntilBillingDue(): ?int
    {
        $agreement = $this->primary_billing_agreement;
        if (!$agreement || !$agreement->due_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays(
            Carbon::parse($agreement->due_date)->startOfDay(),
            false
        );
    }

    /**
     * Days overdue on the primary billing agreement.
     * 0 or negative = not overdue. Null = no agreement / no due date.
     */
    public function daysOverdueOnBilling(): ?int
    {
        $days = $this->daysUntilBillingDue();
        if ($days === null) {
            return null;
        }
        return $days < 0 ? abs($days) : 0;
    }

    /* ============================================================
     | BILLING ACCESSORS / HELPERS
     * ============================================================ */

    /**
     * True when the developer profile is eligible for recurring billing.
     * Requires BOTH billing_active = true AND billing_status = 'active'.
     */
    public function isBillingActive(): bool
    {
        return (bool) $this->billing_active
            && $this->billing_status === self::BILLING_STATUS_ACTIVE;
    }

    /**
     * Safe accessor for billing_rules — always returns an array.
     *
     * Works whether the column is cast to array (default), returns a raw
     * JSON string (pre-migration), or comes back null/empty.
     *
     * Missing keys are filled from DEFAULT_BILLING_RULES so downstream code
     * never has to guess what "missing" means.
     */
    public function getBillingRulesArray(): array
    {
        $rules = $this->billing_rules;

        if (is_string($rules) && $rules !== '') {
            $decoded = json_decode($rules, true);
            $rules   = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rules)) {
            $rules = [];
        }

        // Fill missing keys with defaults — but do NOT overwrite keys
        // that exist with falsy values (false is a legitimate value).
        return array_replace(self::DEFAULT_BILLING_RULES, $rules);
    }

    /**
     * True when the developer has turned auto-invoice generation ON.
     *
     * Default is FALSE when the key is missing. The settings controller
     * writes false unless the toggle is checked, so absence of the key
     * means "not enabled" is the correct semantic.
     */
    public function isAutoInvoiceGenerationEnabled(): bool
    {
        $rules = $this->billing_rules;

        if (is_string($rules) && $rules !== '') {
            $decoded = json_decode($rules, true);
            $rules   = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rules)) {
            return false;
        }

        if (!array_key_exists('auto_generate_invoices', $rules)) {
            return false;
        }

        return (bool) $rules['auto_generate_invoices'];
    }

    /**
     * True when the developer has turned payment reminders ON.
     * Same "missing = false" semantic as auto-invoice.
     */
    public function shouldSendPaymentReminders(): bool
    {
        $rules = $this->billing_rules;

        if (is_string($rules) && $rules !== '') {
            $decoded = json_decode($rules, true);
            $rules   = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rules)) {
            return false;
        }

        if (!array_key_exists('send_payment_reminders', $rules)) {
            return false;
        }

        return (bool) $rules['send_payment_reminders'];
    }

    public function getInvoiceDueDays(): int
    {
        return (int) ($this->getBillingRulesArray()['invoice_due_days'] ?? 30);
    }

    public function getReminderDaysBefore(): array
    {
        $days = $this->getBillingRulesArray()['reminder_days_before'] ?? [7, 3, 1];

        if (!is_array($days)) {
            return [7, 3, 1];
        }

        $clean = collect($days)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v >= 1 && $v <= 30)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return !empty($clean) ? $clean : [7, 3, 1];
    }

    /**
     * Human-readable payment method details (mirrors BillingHelperTrait).
     */
    public function getPaymentMethodDetails(): array
    {
        $method = $this->payment_method ?? self::PAYMENT_METHOD_BANK;

        $label = [
            self::PAYMENT_METHOD_BANK   => 'Bank Transfer',
            self::PAYMENT_METHOD_MOBILE => 'Mobile Money',
            self::PAYMENT_METHOD_CASH   => 'Cash',
            self::PAYMENT_METHOD_CHECK  => 'Check',
        ][$method] ?? ucfirst(str_replace('_', ' ', $method));

        $display = '';
        switch ($method) {
            case self::PAYMENT_METHOD_BANK:
                $display = sprintf(
                    '%s - %s (%s)',
                    $this->payment_bank_name ?? 'Bank',
                    $this->payment_account_number ?? 'N/A',
                    $this->payment_account_name ?? 'N/A'
                );
                break;
            case self::PAYMENT_METHOD_MOBILE:
                $display = sprintf(
                    '%s - %s (%s)',
                    ucfirst($this->payment_mobile_network ?? 'Mobile Money'),
                    $this->payment_mobile_number ?? 'N/A',
                    $this->payment_account_name ?? 'N/A'
                );
                break;
            case self::PAYMENT_METHOD_CASH:
                $display = 'Cash Payment';
                break;
            case self::PAYMENT_METHOD_CHECK:
                $display = 'Check payable to ' . ($this->payment_account_name ?? 'Developer');
                break;
        }

        return [
            'method'       => $method,
            'method_label' => $label,
            'display_text' => $display,
        ];
    }

    public function hasPrimarySuperAdmin(): bool
    {
        if (!Schema::hasColumn('developer_settings', 'primary_super_admin_id')) {
            return false;
        }
        return !empty($this->primary_super_admin_id);
    }

    public function getPrimaryBillingContact(): ?array
    {
        if ($this->hasPrimarySuperAdmin()) {
            $sa = $this->relationLoaded('primarySuperAdmin')
                ? $this->primarySuperAdmin
                : $this->primarySuperAdmin()->first();

            if ($sa) {
                return [
                    'name'  => $this->billing_contact_name  ?? $sa->name,
                    'email' => $this->billing_contact_email ?? $sa->email,
                    'phone' => $this->billing_contact_phone ?? $sa->phone,
                ];
            }
        }

        $agreement = $this->primaryBillingAgreement()->with('superAdmin')->first();
        if ($agreement && $agreement->superAdmin) {
            return [
                'name'  => $agreement->billing_contact_name  ?? $agreement->superAdmin->name,
                'email' => $agreement->billing_contact_email ?? $agreement->superAdmin->email,
                'phone' => $agreement->billing_contact_phone ?? $agreement->superAdmin->phone,
            ];
        }

        return null;
    }

    /* ============================================================
     | EMAIL CONFIG
     * ============================================================ */

    public function getEmailConfig(): array
    {
        return [
            'host'          => $this->developer_smtp_host,
            'port'          => $this->developer_smtp_port,
            'username'      => $this->developer_smtp_username,
            'password'      => $this->developer_smtp_password,
            'encryption'    => $this->developer_smtp_encryption,
            'from_address'  => $this->developer_email_from,
            'from_name'     => $this->developer_email_from_name,
            'is_configured' => $this->isEmailConfigured(),
        ];
    }

    public function isEmailConfigured(): bool
    {
        return !empty($this->developer_smtp_host)
            && !empty($this->developer_smtp_username)
            && !empty($this->developer_smtp_password);
    }

    /* ============================================================
     | API CONFIG
     * ============================================================ */

    public function getApiConfig(): array
    {
        return [
            'base_url'       => $this->api_base_url,
            'version'        => $this->api_version,
            'enabled'        => $this->api_enabled,
            'has_secret_key' => !empty($this->developer_secret_key),
        ];
    }

    /* ============================================================
     | SECURITY / MONITORING / ANALYTICS / PERFORMANCE
     * ============================================================ */

    public function getSecurityConfig(): array
    {
        return [
            'two_factor_enabled'      => $this->enable_two_factor,
            'session_timeout_minutes' => $this->session_timeout,
            'max_login_attempts'      => $this->max_login_attempts,
            'password_expiry_days'    => $this->password_expiry_days,
            'allowed_ips'             => $this->allowed_ips ?? [],
        ];
    }

    public function getMonitoringConfig(): array
    {
        return [
            'system_monitoring'      => $this->enable_system_monitoring,
            'error_tracking'         => $this->enable_error_tracking,
            'performance_monitoring' => $this->enable_performance_monitoring,
            'log_retention_days'     => $this->log_retention_days,
        ];
    }

    public function getAnalyticsConfig(): array
    {
        return [
            'enabled'           => $this->enable_analytics,
            'provider'          => $this->analytics_provider,
            'tracking_id'       => $this->analytics_tracking_id,
            'daily_reports'     => $this->enable_daily_reports,
            'report_recipients' => $this->report_recipients ?? [],
        ];
    }

    public function getPerformanceConfig(): array
    {
        return [
            'cache_duration_seconds' => $this->cache_duration,
            'query_cache_enabled'    => $this->enable_query_cache,
            'max_upload_size_kb'     => $this->max_upload_size,
            'max_execution_time'     => $this->max_execution_time,
        ];
    }

    /**
     * Aggregate config — extended with billing state.
     */
    public function getAllConfig(): array
    {
        return [
            'developer_info' => [
                'name'    => $this->developer_name,
                'email'   => $this->developer_email,
                'phone'   => $this->developer_phone,
                'company' => $this->developer_company,
                'website' => $this->developer_website,
                'address' => $this->developer_address,
            ],
            'access' => [
                'enabled'          => $this->developer_access_enabled,
                'status'           => $this->status,
                'allowed_ips'      => $this->allowed_ips ?? [],
                'allowed_features' => $this->allowed_features ?? [],
            ],
            'email'       => $this->getEmailConfig(),
            'api'         => $this->getApiConfig(),
            'security'    => $this->getSecurityConfig(),
            'monitoring'  => $this->getMonitoringConfig(),
            'analytics'   => $this->getAnalyticsConfig(),
            'performance' => $this->getPerformanceConfig(),
            'billing'     => [
                'active'            => $this->isBillingActive(),
                'status'            => $this->billing_status,
                'amount'            => (float) $this->monthly_billing_amount,
                'currency'          => $this->billing_currency,
                'cycle'             => $this->billing_cycle,
                'start_date'        => optional($this->billing_start_date)->toDateString(),
                'next_billing_date' => optional($this->next_billing_date)->toDateString(),
                'payment_method'    => $this->getPaymentMethodDetails(),
                'rules'             => $this->getBillingRulesArray(),
                'primary_contact'   => $this->getPrimaryBillingContact(),
                'has_primary_sa'    => $this->hasPrimarySuperAdmin(),

                // ✅ enforcement surface
                'state'                 => $this->billing_state,
                'days_until_due'        => $this->daysUntilBillingDue(),
                'days_overdue'          => $this->daysOverdueOnBilling(),
                'restricts_super_admin' => $this->isBillingRestrictingSuperAdmin(),
                'restricts_admins'      => $this->isBillingRestrictingAdmins(),
                'blocks_landlords'      => $this->isBillingBlockingLandlordsAndTenants(),
            ],
        ];
    }

    /* ============================================================
     | API KEY MANAGEMENT
     * ============================================================ */

    /**
     * Generate and persist a new secret key.
     *
     * NOTE: The schema has no `developer_api_key` column, so only the
     * secret is persisted. The plaintext api_key is returned for the
     * caller to show once — it is not stored here.
     */
    public function generateApiKey(): array
    {
        $apiKey    = 'dev_' . bin2hex(random_bytes(16));
        $secretKey = bin2hex(random_bytes(32));

        $update = [
            'developer_secret_key' => $secretKey,
        ];

        if (Schema::hasColumn('developer_settings', 'api_key_last_generated')) {
            $update['api_key_last_generated'] = now();
        }

        $this->update($update);

        $this->clearCache();

        return [
            'api_key'      => $apiKey,
            'secret_key'   => $secretKey,
            'generated_at' => now(),
        ];
    }

    public function verifyApiKey($secretKey): bool
    {
        return hash_equals($this->developer_secret_key ?? '', (string) $secretKey);
    }

    /* ============================================================
     | EMAIL CONFIG UPDATE
     * ============================================================ */

    /**
     * IMPORTANT: `developer_smtp_password` uses the `encrypted` cast.
     * Passing a plaintext value here is correct — the cast encrypts it.
     * Do NOT pre-encrypt with Crypt::encryptString (that would double-encrypt).
     */
    public function updateEmailConfig(array $config): self
    {
        $validated = [
            'developer_smtp_host'       => $config['host']         ?? null,
            'developer_smtp_port'       => $config['port']         ?? null,
            'developer_smtp_username'   => $config['username']     ?? null,
            'developer_smtp_password'   => $config['password']     ?? null,
            'developer_smtp_encryption' => $config['encryption']   ?? 'tls',
            'developer_email_from'      => $config['from_address'] ?? null,
            'developer_email_from_name' => $config['from_name']    ?? null,
        ];

        $this->update($validated);
        $this->clearCache();

        Log::info('Developer email configuration updated', [
            'developer_id'    => $this->id,
            'developer_email' => $this->developer_email,
        ]);

        return $this;
    }

    /* ============================================================
     | BILLING RULES UPDATE
     * ============================================================ */

    /**
     * Merge new billing_rules with the existing ones and persist.
     *
     * Use this from any caller that needs to toggle flags without
     * clobbering the other keys. Also clears the billing-state cache
     * so enforcement flips immediately.
     */
    public function updateBillingRules(array $newRules): self
    {
        $merged = array_replace($this->getBillingRulesArray(), $newRules);

        $this->update([
            'billing_rules' => $merged,
        ]);

        $this->clearCache();
        $this->clearBillingStateCache();

        return $this;
    }

    /* ============================================================
     | STATUS MANAGEMENT
     * ============================================================ */

    public function suspendAccess(?string $reason = null): self
    {
        $this->update([
            'status'                   => self::STATUS_SUSPENDED,
            'developer_access_enabled' => false,
            'notes'                    => $reason ?: $this->notes,
        ]);

        $this->clearCache();

        Log::warning('Developer access suspended', [
            'developer_id'    => $this->id,
            'developer_email' => $this->developer_email,
            'reason'          => $reason,
        ]);

        return $this;
    }

    public function activateAccess(): self
    {
        $this->update([
            'status'                   => self::STATUS_ACTIVE,
            'developer_access_enabled' => true,
        ]);

        $this->clearCache();

        Log::info('Developer access activated', [
            'developer_id'    => $this->id,
            'developer_email' => $this->developer_email,
        ]);

        return $this;
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips, true);
    }

    /* ============================================================
     | CACHE
     * ============================================================ */

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);

        if ($this->developer_email) {
            Cache::forget(self::CACHE_KEY_BY_EMAIL . md5($this->developer_email));
        }

        Cache::forget('email_configuration_status');
        Cache::forget('developer_settings_' . auth()->id());

        // Billing-related caches that reference this record
        Cache::forget('developer_billing_settings_v2:' . $this->id);
        Cache::forget('developer_primary_super_admin');
        Cache::forget('developer_setting_current');

        Log::debug('Developer settings cache cleared', [
            'developer_id'    => $this->id,
            'developer_email' => $this->developer_email,
        ]);
    }

    /**
     * ✅ BILLING: forget just the billing-state caches.
     *
     * Called by the model's saved/deleted hooks so any write flips the
     * cached state. Also called explicitly by the payment / signature
     * flow to make enforcement react instantly.
     */
    public function clearBillingStateCache(): void
    {
        Cache::forget(self::BILLING_STATE_CACHE_KEY);
        Cache::forget(self::PRIMARY_AGREEMENT_CACHE_KEY);
        Cache::forget('developer_setting_current');
    }

    /* ============================================================
     | DEFAULTS
     * ============================================================ */

    public static function createDefaultSettings(): self
    {
        $user = auth()->user();

        $defaults = [
            'developer_name'                 => $user->name  ?? 'System Developer',
            'developer_email'                => $user->email ?? 'developer@system.com',
            'developer_access_enabled'       => true,
            'status'                         => self::STATUS_ACTIVE,
            'enable_system_monitoring'       => true,
            'enable_error_tracking'          => true,
            'enable_performance_monitoring'  => true,
            'log_retention_days'             => 90,
            'cache_duration'                 => 3600,
            'enable_query_cache'             => true,
            'max_upload_size'                => 2048,
            'session_timeout'                => 120,
            'max_login_attempts'             => 5,
            'password_expiry_days'           => 90,
            'enable_analytics'               => true,
            'analytics_provider'             => 'internal',
            'enable_daily_reports'           => true,

            // Billing defaults (safe, inactive)
            'monthly_billing_amount'         => 0.00,
            'billing_currency'               => 'GHS',
            'billing_cycle'                  => self::BILLING_CYCLE_MONTHLY,
            'billing_active'                 => false,
            'billing_status'                 => self::BILLING_STATUS_PENDING,
            'payment_method'                 => self::PAYMENT_METHOD_BANK,

            'billing_rules'                  => self::DEFAULT_BILLING_RULES,
        ];

        $settings = self::create($defaults);

        Log::info('Default developer settings created', [
            'developer_id'    => $settings->id,
            'developer_email' => $settings->developer_email,
        ]);

        return $settings;
    }

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->where('developer_access_enabled', true);
    }

    public function scopeBillingActive($query)
    {
        return $query->where('billing_active', true)
                     ->where('billing_status', self::BILLING_STATUS_ACTIVE);
    }

    public function scopeWithPrimarySuperAdmin($query)
    {
        if (Schema::hasColumn('developer_settings', 'primary_super_admin_id')) {
            return $query->whereNotNull('primary_super_admin_id');
        }
        return $query;
    }

    /**
     * ✅ BILLING: records whose primary agreement is currently due.
     * Note: due_soon/overdue are state-machine concepts, so this scope
     * is a coarse DB-level filter — refine in PHP with billing_state.
     */
    public function scopeWithDueBilling($query)
    {
        return $query->whereHas('primaryBillingAgreement', function ($q) {
            $q->where('status', self::AGREEMENT_STATUS_ACTIVE)
              ->whereNotNull('due_date')
              ->whereDate('due_date', '<=', now());
        });
    }
}