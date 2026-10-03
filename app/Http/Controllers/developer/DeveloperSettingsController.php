<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Models\DeveloperEmailAudit;
use App\Models\BillingInvoice;
use App\Services\DeveloperEmailService;
use App\Services\DeveloperEmailConfigurationService;
use App\Services\DeveloperBillingService;
use App\Traits\NotifiesUsers;
use App\Traits\AuditLogger;
use App\Traits\BillingHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DeveloperSettingsController extends Controller
{
    use NotifiesUsers, AuditLogger, BillingHelperTrait;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER   = 5;

    // Email update status constants
    const EMAIL_UPDATE_QUEUED     = 'queued';
    const EMAIL_UPDATE_PROCESSING = 'processing';
    const EMAIL_UPDATE_COMPLETED  = 'completed';
    const EMAIL_UPDATE_FAILED     = 'failed';

    // Cache keys
    const EMAIL_UPDATE_STATUS_KEY = 'developer_email_update_status_';
    const EMAIL_CONFIG_CACHE_KEY  = 'developer_email_config_v2_';
    const SETTINGS_CACHE_KEY      = 'developer_settings_v2';
    const EMAIL_STATUS_CACHE_KEY  = 'developer_email_status';
    const API_KEYS_CACHE_KEY      = 'developer_api_keys';
    const BILLING_CACHE_KEY       = 'developer_billing_settings_v2';
    const PRIMARY_SA_CACHE_KEY    = 'developer_primary_super_admin';

    // Cache TTLs
    const CACHE_TTL_SETTINGS      = 3600;
    const CACHE_TTL_EMAIL_STATUS  = 1800;
    const CACHE_TTL_API_KEYS      = 7200;
    const CACHE_TTL_VERIFICATION  = 86400;
    const CACHE_TTL_EMAIL_CONFIG  = 3600;
    const CACHE_TTL_BILLING       = 1800;

    /** Allowed sections for the settings page */
    const ALLOWED_SECTIONS = ['general', 'email', 'billing', 'api', 'monitoring', 'analytics'];

    /** Default reminder offsets when none are selected */
    const DEFAULT_REMINDER_DAYS = [7, 3, 1];

    protected $emailService;
    protected $emailConfigService;
    protected $billingService;

    public function __construct(
        DeveloperEmailService $emailService,
        DeveloperEmailConfigurationService $emailConfigService,
        DeveloperBillingService $billingService
    ) {
        $this->emailService       = $emailService;
        $this->emailConfigService = $emailConfigService;
        $this->billingService     = $billingService;
    }

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canManageDeveloperSettings(): bool
    {
        $user = auth()->user();
        return $user
            && ($user->type === self::USER_TYPE_DEVELOPER
                || $user->type === self::USER_TYPE_SUPER_ADMIN);
    }

    private function canManageDeveloperEmail(): bool
    {
        return $this->canManageDeveloperSettings();
    }

    private function canManageDeveloperSecurity(): bool
    {
        return $this->canManageDeveloperSettings();
    }

    private function canGenerateApiKey(): bool
    {
        return $this->canManageDeveloperSettings();
    }

    private function canClearCache(): bool
    {
        return $this->canManageDeveloperSettings();
    }

    private function canManageBilling(): bool
    {
        return $this->canManageDeveloperSettings();
    }

    /* ============================================================
     | INDEX
     * ============================================================ */

    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$this->canManageDeveloperSettings()) {
                Log::warning('Developer settings access denied - invalid user type', [
                    'user_id'        => $user->id,
                    'user_type'      => $user->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip'             => $request->ip(),
                ]);
                abort(403, 'Unauthorized to manage developer settings. You must be a developer (type 5) or super admin (type 0).');
            }

            $settings    = $this->getCachedDeveloperSettings();
            $emailConfig = $this->getCachedEmailConfig($user->id);
            $emailStatus = $this->getCachedEmailStatus();
            $emailUpdateStatus = $this->getEmailUpdateStatus($user->id);

            $emailAudits = DeveloperEmailAudit::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            if ($settings->developer_smtp_password) {
                try {
                    $settings->developer_smtp_password = Crypt::decryptString($settings->developer_smtp_password);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt SMTP password: ' . $e->getMessage());
                    $settings->developer_smtp_password = '';
                }
            } else {
                $settings->developer_smtp_password = $this->emailConfigService->getDecryptedPassword() ?? '';
            }

            $apiKeys = $this->getCachedApiKeys();

            // ---------- BILLING DATA ----------
            $billingData = $this->getBillingSectionData($settings, $user);

            $this->logAudit('settings_view', 'Developer settings viewed', [
                'user_type'           => $user->type,
                'settings_id'         => $settings->id,
                'email_update_status' => $emailUpdateStatus,
                'billing_loaded'      => $billingData['has_primary_super_admin'],
                'cache_hit'           => Cache::has($this->getCacheKey(self::SETTINGS_CACHE_KEY)),
            ]);

            // Optional section routing (?section=email|billing|security|api)
            $section = $request->get('section', 'general');
            if (!in_array($section, self::ALLOWED_SECTIONS, true)) {
                $section = 'general';
            }

            return view('developer.settings.index', array_merge(
                compact(
                    'settings',
                    'emailConfig',
                    'emailStatus',
                    'emailUpdateStatus',
                    'emailAudits',
                    'apiKeys',
                    'section'
                ),
                $billingData
            ));

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::error('Authorization error in developer settings: ' . $e->getMessage());
            return redirect()->route('developer.index')
                ->with('error', 'You are not authorized to access developer settings.');
        } catch (\Exception $e) {
            Log::error('Developer settings error: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? 'none',
            ]);
            return redirect()->back()->with('error', 'Error loading settings: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | GENERAL / MONITORING / ANALYTICS SETTINGS UPDATE
     * ============================================================ */

    public function update(Request $request)
    {
        $key = 'settings_update:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->respondToSettingsUpdate($request, false, "Too many update attempts. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key);

        if (!$this->canManageDeveloperSettings()) {
            abort(403, 'Unauthorized to update developer settings.');
        }

        $section = $request->input('section', 'general');
        if (!in_array($section, ['general', 'monitoring', 'analytics'], true)) {
            $section = 'general';
        }

        $validator = $this->validateDeveloperSettings($request->all(), $section);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fix the validation errors',
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors');
        }

        DB::beginTransaction();

        try {
            $settings     = DeveloperSetting::first() ?: $this->initializeDeveloperSettings();
            $oldSettings  = $settings->toArray();
            $data         = $validator->validated();

            // Email config must be updated via the dedicated endpoint
            if ($this->hasEmailConfigChanged($data, $oldSettings)) {
                DB::rollBack();
                return $this->respondToSettingsUpdate(
                    $request,
                    false,
                    'Email configuration has changed. Please update email settings separately.',
                    route('developer.settings.index', ['section' => 'email'])
                );
            }

            // Normalize JSON-ish fields
            if (array_key_exists('allowed_ips', $data)) {
                $data['allowed_ips'] = !empty($data['allowed_ips'])
                    ? json_encode($this->validateAndSanitizeIPs((string) $data['allowed_ips']))
                    : json_encode([]);
            }

            if (array_key_exists('report_recipients', $data)) {
                $data['report_recipients'] = !empty($data['report_recipients'])
                    ? json_encode($this->validateAndSanitizeEmails((string) $data['report_recipients']))
                    : json_encode([]);
            }

            // Booleans arrive as "0"/"1" — cast to real bools
            foreach ([
                'developer_access_enabled', 'api_enabled',
                'enable_system_monitoring', 'enable_error_tracking',
                'enable_performance_monitoring', 'enable_query_cache',
                'enable_analytics', 'enable_daily_reports', 'anonymize_ip',
            ] as $boolField) {
                if (array_key_exists($boolField, $data)) {
                    $data[$boolField] = (bool) $data[$boolField];
                }
            }

            // Section field is only for routing — never persisted
            unset($data['section']);

            $settings->update($data);

            $this->clearDeveloperCache();
            $settings = $this->getCachedDeveloperSettings(true);

            $this->applySettingsChanges($settings);

            DB::commit();

            $this->notifyDeveloperAboutSettingsUpdate($settings, auth()->user(), $oldSettings);

            $this->logAudit('settings_update', 'Developer settings updated', [
                'section'        => $section,
                'changed_fields' => array_keys(array_diff_assoc($data, $oldSettings)),
                'user_type'      => auth()->user()->type,
                'cache_cleared'  => true,
            ]);

            return $this->respondToSettingsUpdate(
                $request,
                true,
                'Settings updated successfully!'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update developer settings: ' . $e->getMessage(), [
                'request_data' => $this->sanitizeLogData($request->all()),
                'exception'    => $e,
            ]);

            return $this->respondToSettingsUpdate(
                $request,
                false,
                'Failed to update settings: ' . $e->getMessage()
            );
        }
    }

    /**
     * Return JSON for AJAX submits, redirect otherwise.
     */
    private function respondToSettingsUpdate(Request $request, bool $success, string $message, ?string $redirectTo = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
            ], $success ? 200 : 422);
        }

        $redirect = $redirectTo
            ? redirect()->to($redirectTo)
            : redirect()->back();

        return $success
            ? $redirect->with('success', $message)
            : $redirect->with('error', $message)->withInput();
    }

    /* ============================================================
     | SECTION-AWARE VALIDATION
     * ============================================================ */

    /**
     * Validate only the fields relevant to the given section.
     */
    private function validateDeveloperSettings(array $data, string $section = 'general')
    {
        // Common rules applied regardless of section (harmless if absent)
        $common = [
            'section' => 'nullable|string|in:general,monitoring,analytics',
        ];

        $general = [
            'developer_name'                => 'required|string|max:255',
            'developer_email'               => 'required|email|max:255',
            'developer_phone'               => 'nullable|string|max:20|regex:/^(0)[0-9]{9}$/',
            'developer_company'             => 'nullable|string|max:255',
            'developer_website'             => 'nullable|url|max:255',
            'developer_address'             => 'nullable|string|max:500',
            'developer_access_enabled'      => 'boolean',
            'allowed_ips'                   => 'nullable|string',
            'status'                        => 'required|in:active,inactive,suspended',
            'cache_duration'                => 'integer|min:300|max:86400',
            'enable_query_cache'            => 'boolean',
            'max_upload_size'               => 'integer|min:500|max:10240',
            'max_execution_time'            => 'integer|min:30|max:3600',
            'notes'                         => 'nullable|string|max:1000',
        ];

        $monitoring = [
            'enable_system_monitoring'      => 'boolean',
            'enable_error_tracking'         => 'boolean',
            'enable_performance_monitoring' => 'boolean',
            'log_retention_days'            => 'integer|min:30|max:3650',
            'report_recipients'             => 'nullable|string',
        ];

        $analytics = [
            'enable_analytics'              => 'boolean',
            'analytics_provider'            => 'nullable|in:google,internal,matomo,mixpanel',
            'analytics_tracking_id'         => 'nullable|string|max:100',
            'enable_daily_reports'          => 'boolean',
            'anonymize_ip'                  => 'boolean',
        ];

        $rules = array_merge($common, match ($section) {
            'monitoring' => $monitoring,
            'analytics'  => $analytics,
            default      => $general,
        });

        return Validator::make($data, $rules, [
            'developer_email.required'  => 'Developer email is required',
            'developer_email.email'     => 'Please provide a valid email address',
            'developer_phone.regex'     => 'Please provide a valid Ghanaian mobile number',
            'max_upload_size.min'       => 'Maximum upload size must be at least 500KB',
            'log_retention_days.min'    => 'Log retention must be at least 30 days',
        ]);
    }

    /* ============================================================
     | BILLING — AUTOMATION SETTINGS ONLY
     | ------------------------------------------------------------
     | This endpoint is intentionally narrow.
     |
     | What it does:
     |   - Toggles `auto_generate_invoices` on/off
     |   - Toggles `send_payment_reminders` on/off
     |   - Adjusts `invoice_due_days`
     |   - Adjusts `reminder_days_before` (array of ints)
     |
     | What it does NOT do (by design):
     |   - Create, update, or supersede billing agreements
     |   - Change the primary super admin
     |   - Generate invoices
     |   - Touch amount, currency, cycle, or payment method
     |
     | Those concerns live on the Billing Dashboard:
     |   - POST /billing/create-agreement
     |   - POST /billing/change-primary-super-admin
     |   - POST /billing/generate-monthly-invoice
     * ============================================================ */

    public function updateBillingSettings(Request $request)
    {
        $key = 'billing_settings_update:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()
                ->with('error', "Too many settings update attempts. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key, 60); // 10 attempts per minute

        if (!$this->canManageBilling()) {
            abort(403, 'Unauthorized to update billing settings.');
        }

        $validator = Validator::make($request->all(), [
            'auto_generate_invoices' => 'nullable|boolean',
            'send_payment_reminders' => 'nullable|boolean',
            'invoice_due_days'       => 'nullable|integer|min:1|max:90',
            'reminder_days_before'   => 'nullable|array',
            'reminder_days_before.*' => 'integer|min:1|max:30',
        ], [
            'invoice_due_days.min'       => 'Invoice due days must be at least 1.',
            'invoice_due_days.max'       => 'Invoice due days cannot exceed 90.',
            'reminder_days_before.*.min' => 'Reminder days must be at least 1.',
            'reminder_days_before.*.max' => 'Reminder days cannot exceed 30.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the billing settings errors.');
        }

        DB::beginTransaction();

        try {
            $user     = auth()->user();
            $settings = DeveloperSetting::first() ?: $this->initializeDeveloperSettings();

            // ✅ FIX: use the model's safe accessor. It handles the case where
            // billing_rules is stored as an array (cast) or a JSON string
            // (legacy), and fills in DEFAULT_BILLING_RULES for missing keys.
            $existingRules = $settings->getBillingRulesArray();

            $newRules = array_merge($existingRules, [
                'auto_generate_invoices' => $request->boolean('auto_generate_invoices', false),
                'send_payment_reminders' => $request->boolean('send_payment_reminders', false),
                'invoice_due_days'       => (int) $request->input('invoice_due_days', 30),
                'reminder_days_before'   => $this->normalizeReminderDays(
                    $request->input('reminder_days_before', self::DEFAULT_REMINDER_DAYS)
                ),
            ]);

            // ✅ FIX: pass the array directly. The 'array' cast on
            // DeveloperSetting::$casts handles json_encode on save.
            // Do NOT wrap in json_encode() — that double-encodes.
            $settings->update([
                'billing_rules' => $newRules,
                'updated_at'    => now(),
            ]);

            DB::commit();

            $this->clearDeveloperCache();

            $this->logAudit('billing_settings_updated', 'Developer billing automation settings updated', [
                'user_id'                => $user->id,
                'auto_generate_invoices' => $newRules['auto_generate_invoices'],
                'send_payment_reminders' => $newRules['send_payment_reminders'],
                'invoice_due_days'       => $newRules['invoice_due_days'],
                'reminder_days_before'   => $newRules['reminder_days_before'],
            ]);

            return redirect()
                ->route('developer.settings.index', ['section' => 'billing'])
                ->with('success', 'Billing automation settings updated successfully.')
                ->with('billing_settings_updated', true);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update billing settings', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update billing settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Normalize reminder_days_before input.
     *
     * Accepts an array of scalars, keeps values in [1..30], dedupes,
     * sorts descending, and falls back to DEFAULT_REMINDER_DAYS when empty.
     */
    private function normalizeReminderDays($input): array
    {
        if (!is_array($input)) {
            return self::DEFAULT_REMINDER_DAYS;
        }

        $clean = collect($input)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v >= 1 && $v <= 30)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return !empty($clean) ? $clean : self::DEFAULT_REMINDER_DAYS;
    }

    /* ============================================================
     | CHANGE PRIMARY SUPER ADMIN
     | ------------------------------------------------------------
     | This still lives on the settings controller for route
     | compatibility, but the UI should be on the Billing Dashboard.
     | The rule: primary-SA change is a billing operation, not a
     | settings operation. Do NOT expose this on the settings form.
     * ============================================================ */

    public function changePrimarySuperAdmin(Request $request)
    {
        if (!$this->canManageBilling()) {
            abort(403, 'Unauthorized to change primary super admin.');
        }

        $validator = Validator::make($request->all(), [
            'new_primary_super_admin_id' => 'required|exists:users,id',
            'billing_contact_name'       => 'nullable|string|max:255',
            'billing_contact_email'      => 'nullable|email|max:255',
            'billing_contact_phone'      => 'nullable|string|max:20',
            'reason'                     => 'nullable|string|max:500',
            'resend_for_signature'       => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $user     = auth()->user();
            $settings = DeveloperSetting::first();

            if (!$settings) {
                throw new \Exception('Developer settings not initialized.');
            }

            $newPrimary = User::where('id', $request->new_primary_super_admin_id)
                ->where('type', self::USER_TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->firstOrFail();

            $oldPrimaryId = $settings->primary_super_admin_id;

            AdminBillingRecord::where('developer_setting_id', $settings->id)
                ->update(['is_primary_for_billing' => false]);

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $settings->id)
                ->where('super_admin_id', $newPrimary->id)
                ->first();

            if (!$primaryAgreement) {
                $primaryAgreement = $this->createAgreementForSuperAdmin($settings, $newPrimary, $user->id);
            }

            $primaryAgreement->update([
                'is_primary_for_billing'  => true,
                'billing_contact_name'    => $request->get('billing_contact_name', $newPrimary->name),
                'billing_contact_email'   => $request->get('billing_contact_email', $newPrimary->email),
                'billing_contact_phone'   => $request->get('billing_contact_phone', $newPrimary->phone),
                'status'                  => $primaryAgreement->status === 'terminated' ? 'pending' : $primaryAgreement->status,
            ]);

            $settings->update([
                'primary_super_admin_id' => $newPrimary->id,
            ]);

            DB::commit();

            $this->clearDeveloperCache();
            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget($this->getCacheKey(self::PRIMARY_SA_CACHE_KEY));

            $this->notifySuperAdminsAboutBillingChange(
                $settings,
                $newPrimary,
                $oldPrimaryId,
                [
                    'created'              => 0,
                    'updated'              => 1,
                    'superseded'           => 0,
                    'all_super_admin_ids'  => $this->getAllActiveSuperAdminIds(),
                    'primary_agreement_id' => $primaryAgreement->id,
                    'resend_for_signature' => $request->boolean('resend_for_signature', false),
                ],
                $user,
                $request->get('reason')
            );

            $this->logAudit('primary_super_admin_changed', 'Primary super admin changed', [
                'new_primary_id'   => $newPrimary->id,
                'new_primary_name' => $newPrimary->name,
                'old_primary_id'   => $oldPrimaryId,
                'reason'           => $request->get('reason'),
            ]);

            return redirect()->route('developer.settings.index', ['section' => 'billing'])
                ->with('success', "Primary Super Admin changed to {$newPrimary->name} ({$newPrimary->email}).");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change primary super admin: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to change primary super admin: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | BILLING SECTION DATA (read-only — no side effects)
     * ============================================================ */

    /**
     * Build billing data for the settings view.
     *
     * Pure read. Cached per developer setting for CACHE_TTL_BILLING seconds.
     */
    private function getBillingSectionData(DeveloperSetting $settings, User $user): array
    {
        $cacheKey = $this->getCacheKey(self::BILLING_CACHE_KEY . $settings->id);

        return Cache::remember($cacheKey, self::CACHE_TTL_BILLING, function () use ($settings, $user) {
            $superAdmins = User::where('type', self::USER_TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email', 'phone']);

            $primaryAgreement = null;
            $primarySuperAdmin = null;
            $primaryBillingContact = null;

            if (Schema::hasTable('admin_billing_records')) {
                $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $settings->id)
                    ->where('is_primary_for_billing', true)
                    ->with('superAdmin')
                    ->first();

                if ($primaryAgreement && $primaryAgreement->superAdmin) {
                    $primarySuperAdmin = $primaryAgreement->superAdmin;
                    $primaryBillingContact = [
                        'name'  => $primaryAgreement->billing_contact_name  ?? $primarySuperAdmin->name,
                        'email' => $primaryAgreement->billing_contact_email ?? $primarySuperAdmin->email,
                        'phone' => $primaryAgreement->billing_contact_phone ?? $primarySuperAdmin->phone,
                    ];
                }
            }

            $agreements = collect();
            if (Schema::hasTable('admin_billing_records')) {
                $agreements = AdminBillingRecord::where('developer_setting_id', $settings->id)
                    ->with('superAdmin')
                    ->orderByDesc('is_primary_for_billing')
                    ->orderByDesc('created_at')
                    ->get();
            }

            $recentInvoices = collect();
            if (Schema::hasTable('billing_invoices')) {
                $q = BillingInvoice::where('developer_setting_id', $settings->id);
                if ($primaryAgreement && Schema::hasColumn('billing_invoices', 'agreement_id')) {
                    $q->where('agreement_id', $primaryAgreement->id);
                } elseif ($primaryAgreement && Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                    $q->where('admin_billing_record_id', $primaryAgreement->id);
                }
                $recentInvoices = $q->orderByDesc('created_at')->limit(5)->get();
            }

            // ✅ FIX: use the model's safe accessor instead of json_decode().
            // The previous code called json_decode() on a value that is cast
            // to array by the DeveloperSetting model — throwing TypeError
            // on PHP 8+.
            $billingRules = $settings->getBillingRulesArray();

            return [
                'superAdmins'             => $superAdmins,
                'primaryAgreement'        => $primaryAgreement,
                'primarySuperAdmin'       => $primarySuperAdmin,
                'primaryBillingContact'   => $primaryBillingContact,
                'billingAgreements'       => $agreements,
                'recentInvoices'          => $recentInvoices,
                'billingRules'            => $billingRules,
                'has_primary_super_admin' => $primarySuperAdmin !== null,
                'paymentMethods'          => $this->getPaymentMethodsConfiguration(),
                'billingStats'            => [
                    'total_agreements'       => $agreements->count(),
                    'active_agreements'      => $agreements->where('status', 'active')->count(),
                    'pending_agreements'     => $agreements->where('status', 'pending')->count(),
                    'total_amount_agreed'    => $agreements->sum('amount'),
                    'total_amount_received'  => $agreements->sum('amount_received'),
                    'total_pending_payments' => max(0, $agreements->sum('amount') - $agreements->sum('amount_received')),
                ],
            ];
        });
    }

    /* ============================================================
     | BILLING INVOICES LISTING
     * ============================================================ */

    /**
     * Show a paginated list of billing invoices for the current developer.
     *
     * Route:  GET developer/billing/invoices  →  developer.billing.invoices
     * View:   resources/views/developer/billing/invoices.blade.php
     */
    public function billingInvoices(Request $request)
    {
        if (!$this->canManageBilling()) {
            abort(403, 'Unauthorized to view billing invoices.');
        }

        try {
            $user     = auth()->user();
            $settings = DeveloperSetting::first();

            if (!$settings) {
                return redirect()
                    ->route('developer.settings.index', ['section' => 'billing'])
                    ->with('error', 'Developer settings not initialized.');
            }

            if (!Schema::hasTable('billing_invoices')) {
                return redirect()
                    ->route('developer.settings.index', ['section' => 'billing'])
                    ->with('error', 'Billing invoices table is not available yet.');
            }

            // ---------- BASE QUERY ----------
            $query = BillingInvoice::where('developer_setting_id', $settings->id);

            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('transaction_reference', 'like', "%{$search}%")
                      ->orWhere('payment_reference', 'like', "%{$search}%");
                });
            }

            if ($request->filled('from')) {
                $query->whereDate('issue_date', '>=', $request->get('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('issue_date', '<=', $request->get('to'));
            }

            if ($request->filled('invoice_type')) {
                $query->where('invoice_type', $request->get('invoice_type'));
            }

            // ---------- PAGINATED RESULTS ----------
            $invoices = (clone $query)
                ->orderByDesc('issue_date')
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();

            // ---------- STATS (fresh clone, no pagination leak) ----------
            $statsQuery = clone $query;

            $stats = [
                'total'           => (clone $statsQuery)->count(),
                'paid'            => (clone $statsQuery)->where('status', 'paid')->count(),
                'pending'         => (clone $statsQuery)->where('status', 'pending')->count(),
                'overdue'         => (clone $statsQuery)->where('status', 'overdue')->count(),
                'cancelled'       => (clone $statsQuery)->where('status', 'cancelled')->count(),

                'sum_paid'        => (float) (clone $statsQuery)
                                        ->where('status', 'paid')
                                        ->sum('amount'),

                'sum_collected'   => (float) (clone $statsQuery)->sum('paid_amount'),

                'sum_outstanding' => (float) (clone $statsQuery)
                                        ->whereIn('status', ['pending', 'overdue'])
                                        ->sum('amount'),
            ];

            $agreements = Schema::hasTable('admin_billing_records')
                ? AdminBillingRecord::where('developer_setting_id', $settings->id)
                    ->with('superAdmin')
                    ->orderByDesc('is_primary_for_billing')
                    ->get()
                : collect();

            $this->logAudit('billing_invoices_view', 'Billing invoices listing viewed', [
                'user_id'      => $user->id,
                'user_type'    => $user->type,
                'settings_id'  => $settings->id,
                'filters'      => $request->only(['status', 'search', 'from', 'to', 'invoice_type']),
                'result_count' => $invoices->count(),
            ]);

            return view('developer.billing.invoices', compact(
                'settings',
                'invoices',
                'agreements',
                'stats'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to load billing invoices: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('developer.settings.index', ['section' => 'billing'])
                ->with('error', 'Failed to load invoices: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | AGREEMENT CREATION (used only by changePrimarySuperAdmin)
     | ------------------------------------------------------------
     | Full agreement creation lives in DeveloperBillingController.
     | This helper exists ONLY to satisfy the change-primary flow
     | when no agreement exists yet for the new primary SA.
     * ============================================================ */

    private function createAgreementForSuperAdmin(
        DeveloperSetting $settings,
        User $superAdmin,
        int $requestedBy
    ): AdminBillingRecord {
        $cycle = $settings->billing_cycle ?? 'monthly';

        return AdminBillingRecord::create([
            'developer_setting_id'   => $settings->id,
            'super_admin_id'         => $superAdmin->id,
            'agreement_number'       => $this->generateAgreementNumber(),
            'amount'                 => $settings->monthly_billing_amount,
            'currency'               => $settings->billing_currency,
            'billing_frequency'      => $cycle,
            'description'            => 'Billing agreement for system services',
            'start_date'             => now(),
            'due_date'               => $this->calculateDueDateBasedOnFrequency(now(), $cycle),
            'status'                 => 'pending',
            'payment_status'         => 'unpaid',
            'payment_method'         => $settings->payment_method,
            'payment_account_name'   => $settings->payment_account_name,
            'payment_account_number' => $settings->payment_account_number,
            'payment_bank_name'      => $settings->payment_bank_name,
            'requested_by'           => $requestedBy,
            'requested_at'           => now(),
            'is_primary_for_billing' => false,
        ]);
    }

    /* ============================================================
     | NOTIFICATIONS — billing changes
     * ============================================================ */

    private function notifySuperAdminsAboutBillingChange(
        DeveloperSetting $settings,
        User $newPrimary,
        ?int $oldPrimaryId,
        array $syncResult,
        User $actor,
        ?string $reason = null
    ): void {
        try {
            $superAdmins = User::where('type', self::USER_TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $primaryChanged = $oldPrimaryId !== $newPrimary->id;

            $title = $primaryChanged
                ? '💼 Primary Billing Contact Changed'
                : '💼 Billing Settings Updated';

            $icon = $primaryChanged
                ? 'fas fa-user-tie text-info'
                : 'fas fa-file-invoice-dollar text-info';

            $parts = [];
            $parts[] = "Billing settings updated by {$actor->name}.";

            if ($primaryChanged) {
                $oldPrimary = $oldPrimaryId
                    ? User::find($oldPrimaryId)?->name ?? "ID:{$oldPrimaryId}"
                    : 'None';
                $parts[] = "Primary billing contact: {$oldPrimary} → {$newPrimary->name}.";
            }

            $parts[] = sprintf(
                'Amount: %s %s per %s.',
                $settings->billing_currency,
                number_format($settings->monthly_billing_amount ?? 0, 2),
                $settings->billing_cycle ?? 'monthly'
            );

            if ($syncResult['created'] > 0 || $syncResult['updated'] > 0) {
                $parts[] = sprintf(
                    'Agreements: %d created, %d updated.',
                    $syncResult['created'],
                    $syncResult['updated']
                );
            }

            if ($reason) {
                $parts[] = "Reason: {$reason}";
            }

            $notificationData = [
                'title'      => $title,
                'message'    => implode(' ', $parts),
                'icon'       => $icon,
                'category'   => 'developer_billing',
                'action_url' => route('developer.settings.index', ['section' => 'billing']),
                'priority'   => $primaryChanged ? 3 : 2,
                'data'       => [
                    'type'                 => 'developer_billing_change',
                    'old_primary_id'       => $oldPrimaryId,
                    'new_primary_id'       => $newPrimary->id,
                    'new_primary_name'     => $newPrimary->name,
                    'amount'               => $settings->monthly_billing_amount,
                    'currency'             => $settings->billing_currency,
                    'cycle'                => $settings->billing_cycle,
                    'payment_method'       => $settings->payment_method,
                    'agreements_created'   => $syncResult['created'],
                    'agreements_updated'   => $syncResult['updated'],
                    'agreements_superseded'=> $syncResult['superseded'],
                    'primary_agreement_id' => $syncResult['primary_agreement_id'] ?? null,
                    'changed_by'           => $actor->id,
                    'changed_by_name'      => $actor->name,
                    'timestamp'            => now()->toISOString(),
                    'notification_type'    => 'in_app_only',
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about billing change', [
                'admin_count'     => $superAdmins->count(),
                'primary_changed' => $primaryChanged,
                'new_primary'     => $newPrimary->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about billing change: ' . $e->getMessage());
        }
    }

    private function getAllActiveSuperAdminIds(): array
    {
        return User::where('type', self::USER_TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->pluck('id')
            ->all();
    }

    /* ============================================================
     | EMAIL CONFIGURATION
     * ============================================================ */

    public function updateEmailConfiguration(Request $request)
    {
        $key = 'email_config_update:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many email update attempts. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key, 300);

        try {
            if (!$this->canManageDeveloperEmail()) {
                abort(403, 'Unauthorized to update email configuration.');
            }

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'developer_mail_host'         => 'required|string|max:255',
                'developer_mail_port'         => 'required|integer|min:1|max:65535',
                'developer_mail_username'     => 'required|email',
                'developer_mail_password'     => 'required|string|min:6',
                'developer_mail_encryption'   => 'required|in:tls,ssl,none',
                'developer_mail_from_address' => 'required|email',
                'developer_mail_from_name'    => 'required|string|max:255',
                'send_verification_email'     => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput()
                    ->with('error', 'Please fix the validation errors');
            }

            $data = $validator->validated();

            DB::beginTransaction();

            try {
                $configData = [
                    'host'         => $data['developer_mail_host'],
                    'port'         => $data['developer_mail_port'],
                    'username'     => $data['developer_mail_username'],
                    'password'     => $data['developer_mail_password'],
                    'encryption'   => $data['developer_mail_encryption'],
                    'from_address' => $data['developer_mail_from_address'],
                    'from_name'    => $data['developer_mail_from_name'],
                ];

                $saveResult = $this->emailConfigService->saveDeveloperSmtpConfig($configData);
                if (!$saveResult['success']) {
                    throw new \Exception($saveResult['message']);
                }

                $settings = DeveloperSetting::first();
                if ($settings) {
                    $settings->update([
                        'developer_smtp_host'        => $data['developer_mail_host'],
                        'developer_smtp_port'        => $data['developer_mail_port'],
                        'developer_smtp_username'    => $data['developer_mail_username'],
                        'developer_smtp_encryption'  => $data['developer_mail_encryption'],
                        'developer_email_from'       => $data['developer_mail_from_address'],
                        'developer_email_from_name'  => $data['developer_mail_from_name'],
                    ]);

                    if (!empty($data['developer_mail_password'])) {
                        $settings->developer_smtp_password = Crypt::encryptString($data['developer_mail_password']);
                        $settings->save();
                    }
                }

                $testResult = $this->emailService->testConfiguration(
                    null,
                    $data['developer_mail_from_address'],
                    'connection',
                    'developer'
                );

                $verificationSent = false;
                if ($request->boolean('send_verification_email') && $testResult['success']) {
                    $verificationResult = $this->sendEmailConfigurationVerification(
                        $data['developer_mail_from_address'],
                        $user
                    );
                    $verificationSent = $verificationResult['success'];
                }

                $this->clearEmailCache($user->id);

                $this->logEmailConfigurationAudit(
                    $user,
                    'update',
                    $configData,
                    [],
                    $testResult['success'],
                    $testResult['message'] ?? '',
                    'immediate'
                );

                DB::commit();

                $message = 'Email configuration updated successfully!';
                if ($testResult['success']) {
                    $message .= ' Test connection passed.';
                    if ($verificationSent) {
                        $message .= ' Verification email sent.';
                    }
                } else {
                    $message .= ' But test connection failed: ' . $testResult['message'];
                }

                $messageType = $testResult['success'] ? 'success' : 'warning';

                return redirect()->route('developer.settings.index', ['section' => 'email'])
                    ->with($messageType, $message)
                    ->with('email_test_result', $testResult)
                    ->with('verification_sent', $verificationSent);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Email config update failed: ' . $e->getMessage(), [
                'user_id'      => auth()->id(),
                'user_type'    => auth()->user()->type,
                'request_data' => $request->except(['developer_mail_password']),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to update email configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function testEmailConfiguration(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request. AJAX required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'test_email'  => 'required|email|max:255',
            'test_type'   => 'required|in:connection,send,template,full',
            'config_type' => 'nullable|in:developer,system',
            'template'    => 'nullable|in:welcome,invoice,notification,alert',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if (!$this->canManageDeveloperEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $data       = $validator->validated();
            $configType = $data['config_type'] ?? 'developer';
            $configStatus = $this->getCachedEmailStatus();

            if (!$configStatus['configured']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email configuration not set up',
                    'details' => 'Please configure your email settings first.',
                ], 400);
            }

            $result = $this->emailService->testConfiguration(
                null,
                $data['test_email'],
                $data['test_type'],
                $configType
            );

            Cache::forget($this->getCacheKey(self::EMAIL_STATUS_CACHE_KEY));

            $this->logAudit('email_test', 'Email configuration test performed', [
                'test_type'   => $data['test_type'],
                'config_type' => $configType,
                'user_type'   => auth()->user()->type,
                'success'     => $result['success'],
                'test_email'  => $data['test_email'],
                'ip_address'  => $request->ip(),
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Email configuration test failed: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | SECURITY / API / CACHE
     * ============================================================ */

    public function updateSecuritySettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enable_two_factor'    => 'boolean',
            'session_timeout'      => 'integer|min:15|max:1440',
            'max_login_attempts'   => 'integer|min:3|max:10',
            'password_expiry_days' => 'integer|min:30|max:180',
            'allowed_ips'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()
                ->with('error', 'Please fix the security settings errors');
        }

        try {
            if (!$this->canManageDeveloperSecurity()) {
                abort(403, 'Unauthorized to update security settings.');
            }

            $settings = DeveloperSetting::first() ?: $this->initializeDeveloperSettings();
            $data     = $validator->validated();

            if (array_key_exists('allowed_ips', $data)) {
                $data['allowed_ips'] = !empty($data['allowed_ips'])
                    ? json_encode($this->validateAndSanitizeIPs((string) $data['allowed_ips']))
                    : json_encode([]);
            }

            foreach (['enable_two_factor'] as $boolField) {
                if (array_key_exists($boolField, $data)) {
                    $data[$boolField] = (bool) $data[$boolField];
                }
            }

            $settings->update($data);
            $this->clearDeveloperCache();

            if (isset($data['session_timeout'])) {
                config(['session.lifetime' => $data['session_timeout']]);
            }

            $this->logAudit('security_settings_updated', 'Security settings updated', [
                'changed_fields' => array_keys($data),
                'user_type'      => auth()->user()->type,
                'ip_address'     => request()->ip(),
            ]);

            return redirect()->route('developer.settings.index', ['section' => 'api'])
                ->with('success', 'Security settings updated successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to update security settings: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update security settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function generateApiKey()
    {
        $key = 'api_key_generate:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "You can only generate API keys once every 24 hours. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key, 86400);

        try {
            if (!$this->canGenerateApiKey()) {
                abort(403, 'Unauthorized to generate API keys.');
            }

            $settings = DeveloperSetting::first() ?: DeveloperSetting::create();

            $apiKey    = $this->generateSecureApiKey();
            $secretKey = $this->generateSecureSecretKey();

            $settings->update([
                'developer_api_key'        => $apiKey,
                'developer_secret_key'     => Hash::make($secretKey),
                'api_key_last_generated'   => now(),
                'updated_at'               => now(),
            ]);

            Cache::forget($this->getCacheKey(self::API_KEYS_CACHE_KEY));

            session()->flash('api_keys_generated', [
                'api_key'    => $apiKey,
                'secret_key' => $secretKey,
                'timestamp'  => now()->toISOString(),
            ]);

            $this->logAudit('api_key_generated', 'New API keys generated', [
                'user_type'     => auth()->user()->type,
                'ip_address'    => request()->ip(),
                'cache_cleared' => true,
            ]);

            return redirect()->route('developer.settings.index', ['section' => 'api'])
                ->with('success', 'New API keys generated successfully! Save them securely.')
                ->with('warning', 'Your old API keys are now invalid.');

        } catch (\Exception $e) {
            Log::error('Failed to generate API keys: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate API keys: ' . $e->getMessage());
        }
    }

    public function apiDocumentation()
    {
        try {
            if (!$this->canGenerateApiKey()) {
                abort(403, 'Unauthorized to view API documentation.');
            }

            return view('developer.settings.api-documentation', [
                'settings' => $this->getCachedDeveloperSettings(),
                'apiKeys'  => $this->getCachedApiKeys(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load API documentation: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load API documentation: ' . $e->getMessage());
        }
    }

    public function clearCache()
    {
        try {
            if (!$this->canClearCache()) {
                abort(403, 'Unauthorized to clear cache.');
            }

            $user = auth()->user();

            $this->clearDeveloperCache();

            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget($this->getCacheKey(self::BILLING_CACHE_KEY));
            Cache::forget($this->getCacheKey(self::PRIMARY_SA_CACHE_KEY));

            $this->logAudit('cache_cleared', 'Developer cache cleared', [
                'user_id'                    => $user->id,
                'user_type'                  => $user->type,
                'ip_address'                 => request()->ip(),
                'cache_type'                 => 'developer_specific',
                'framework_cache_cleared'    => true,
            ]);

            return redirect()->route('developer.settings.index')
                ->with('success', 'Developer cache cleared successfully!')
                ->with('info', 'Framework cache has also been cleared.');

        } catch (\Exception $e) {
            Log::error('Failed to clear cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | CACHE MANAGEMENT
     * ============================================================ */

    private function getCachedDeveloperSettings(bool $forceRefresh = false)
    {
        $cacheKey = $this->getCacheKey(self::SETTINGS_CACHE_KEY);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SETTINGS, function () {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                $settings = $this->initializeDeveloperSettings();
            }
            return $settings;
        });
    }

    private function getCachedEmailConfig(int $userId)
    {
        $cacheKey = $this->getCacheKey(self::EMAIL_CONFIG_CACHE_KEY . $userId);
        return Cache::remember($cacheKey, self::CACHE_TTL_EMAIL_CONFIG, function () {
            return $this->emailConfigService->getCurrentDeveloperConfig();
        });
    }

    private function getCachedEmailStatus()
    {
        $cacheKey = $this->getCacheKey(self::EMAIL_STATUS_CACHE_KEY);
        return Cache::remember($cacheKey, self::CACHE_TTL_EMAIL_STATUS, function () {
            return $this->emailService->checkConfigurationStatus();
        });
    }

    private function getCachedApiKeys()
    {
        $cacheKey = $this->getCacheKey(self::API_KEYS_CACHE_KEY);
        return Cache::remember($cacheKey, self::CACHE_TTL_API_KEYS, function () {
            return $this->fetchApiKeys();
        });
    }

    private function clearDeveloperCache(): bool
    {
        try {
            $userId   = auth()->id();
            $settings = DeveloperSetting::first();

            $cacheKeys = [
                $this->getCacheKey(self::SETTINGS_CACHE_KEY),
                $this->getCacheKey(self::EMAIL_STATUS_CACHE_KEY),
                $this->getCacheKey(self::API_KEYS_CACHE_KEY),
                $this->getCacheKey(self::PRIMARY_SA_CACHE_KEY),
            ];

            if ($userId) {
                $cacheKeys[] = $this->getCacheKey(self::EMAIL_CONFIG_CACHE_KEY . $userId);
                $cacheKeys[] = 'email_verification_' . $userId;
            }

            if ($settings) {
                $cacheKeys[] = $this->getCacheKey(self::BILLING_CACHE_KEY . $settings->id);
            }

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            Log::debug('Developer cache cleared', [
                'user_id'            => $userId,
                'cache_keys_cleared' => $cacheKeys,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to clear developer cache: ' . $e->getMessage());
            return false;
        }
    }

    private function clearEmailCache(int $userId): bool
    {
        try {
            foreach ([
                $this->getCacheKey(self::EMAIL_STATUS_CACHE_KEY),
                $this->getCacheKey(self::EMAIL_CONFIG_CACHE_KEY . $userId),
            ] as $key) {
                Cache::forget($key);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear email cache: ' . $e->getMessage());
            return false;
        }
    }

    private function getCacheKey(string $baseKey): string
    {
        $userId = auth()->id() ?? 'guest';
        $appEnv = config('app.env', 'production');
        return "dev:{$appEnv}:{$userId}:{$baseKey}";
    }

    private function cacheDeveloperData(string $key, $data, ?int $ttl = null): bool
    {
        try {
            Cache::put($this->getCacheKey($key), $data, $ttl ?? self::CACHE_TTL_SETTINGS);
            return true;
        } catch (\Exception $e) {
            Log::warning("Cache write failed for key: {$key}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getCachedOrFresh(string $key, callable $callback, ?int $ttl = null)
    {
        $cacheKey = $this->getCacheKey($key);
        try {
            return Cache::remember($cacheKey, $ttl ?? self::CACHE_TTL_SETTINGS, $callback);
        } catch (\Exception $e) {
            Log::warning("Cache read failed for {$key}", ['error' => $e->getMessage()]);
            return $callback();
        }
    }

    /* ============================================================
     | HELPERS
     * ============================================================ */

    private function sendEmailConfigurationVerification(string $email, User $user): array
    {
        try {
            $verificationCode = Str::random(6);
            $expiresAt        = now()->addHours(24);

            $cacheKey  = 'email_verification_' . $user->id;
            $cacheData = [
                'code'       => $verificationCode,
                'email'      => $email,
                'expires_at' => $expiresAt,
                'attempts'   => 0,
            ];

            if (!$this->cacheDeveloperData($cacheKey, $cacheData, self::CACHE_TTL_VERIFICATION)) {
                throw new \Exception('Failed to store verification code in cache');
            }

            $subject = 'Email Configuration Verification - ' . config('app.name');

            $sent = $this->emailService->sendVerificationEmail($email, $subject, $verificationCode, $user);

            if ($sent) {
                $this->logEmailConfigurationAudit(
                    $user, 'verification_sent',
                    ['email' => $email], [],
                    true, 'Verification email sent successfully', null
                );

                return [
                    'success'    => true,
                    'message'    => 'Verification email sent',
                    'expires_at' => $expiresAt,
                ];
            }

            return ['success' => false, 'message' => 'Failed to send verification email'];

        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function hasEmailConfigChanged(array $newData, array $oldData): bool
    {
        $emailFields = [
            'developer_smtp_host',
            'developer_smtp_port',
            'developer_smtp_username',
            'developer_smtp_password',
            'developer_smtp_encryption',
            'developer_email_from',
            'developer_email_from_name',
        ];

        foreach ($emailFields as $field) {
            if (isset($newData[$field]) && isset($oldData[$field])) {
                if ($newData[$field] !== $oldData[$field]) return true;
            } elseif (isset($newData[$field]) || isset($oldData[$field])) {
                return true;
            }
        }
        return false;
    }

    private function getEmailUpdateStatus(int $userId): array
    {
        $statuses = [];
        $keys     = Cache::get('developer_email_update_keys_' . $userId, []);

        foreach ($keys as $jobId) {
            $status = Cache::get(self::EMAIL_UPDATE_STATUS_KEY . $jobId);
            if ($status) $statuses[$jobId] = $status;
        }

        return array_filter($statuses, function ($status) {
            return isset($status['timestamp']) &&
                   Carbon::parse($status['timestamp'])->gt(now()->subDays(7));
        });
    }

    private function logEmailConfigurationAudit(
        User $user,
        string $action,
        array $newConfig,
        array $oldConfig,
        ?bool $success,
        string $message,
        ?string $jobId = null
    ): void {
        try {
            DeveloperEmailAudit::create([
                'user_id'            => $user->id,
                'job_id'             => $jobId,
                'action'             => $action,
                'status'             => $success === true ? 'success' : ($success === false ? 'failed' : 'pending'),
                'message'            => Str::limit($message, 500),
                'old_configuration'  => json_encode($oldConfig),
                'new_configuration'  => json_encode($newConfig),
                'ip_address'         => request()->ip(),
                'user_agent'         => request()->userAgent(),
                'metadata'           => json_encode([
                    'timestamp'      => now()->toISOString(),
                    'app_env'        => config('app.env'),
                    'user_type'      => $user->type,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log email configuration audit: ' . $e->getMessage());
        }
    }

    private function initializeDeveloperSettings(): DeveloperSetting
    {
        $defaults = [
            'developer_name'              => auth()->user()->name,
            'developer_email'             => auth()->user()->email,
            'developer_access_enabled'    => true,
            'enable_system_monitoring'    => true,
            'cache_duration'              => 3600,
            'max_upload_size'             => 2048,
            'log_retention_days'          => 90,
            'session_timeout'             => 120,
            'status'                      => 'active',
            'billing_currency'            => 'GHS',
            'billing_cycle'               => 'monthly',
            'monthly_billing_amount'      => 0,
            'billing_status'              => 'inactive',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ];

        return DeveloperSetting::create($defaults);
    }

    private function fetchApiKeys()
    {
        try {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                return [
                    'has_api_key'    => false,
                    'api_enabled'    => false,
                    'base_url'       => config('app.url') . '/api/developer',
                    'last_generated' => null,
                ];
            }

            return [
                'has_api_key'    => !empty($settings->developer_secret_key),
                'api_enabled'    => (bool) ($settings->api_enabled ?? false),
                'base_url'       => $settings->api_base_url ?? config('app.url') . '/api/developer',
                'last_generated' => $settings->api_key_last_generated ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch API keys: ' . $e->getMessage());
            return [
                'has_api_key'    => false,
                'api_enabled'    => false,
                'base_url'       => config('app.url') . '/api/developer',
                'last_generated' => null,
            ];
        }
    }

    private function generateSecureApiKey(): string
    {
        return 'dev_' . bin2hex(random_bytes(16));
    }

    private function generateSecureSecretKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function applySettingsChanges(DeveloperSetting $settings): void
    {
        try {
            $this->clearDeveloperCache();
            $this->getCachedDeveloperSettings(true);

            Artisan::call('config:clear');
            Artisan::call('view:clear');

            if ($settings->session_timeout) {
                config(['session.lifetime' => $settings->session_timeout]);
            }

            Log::info('Developer settings applied successfully', [
                'developer'     => auth()->user()->email,
                'user_type'     => auth()->user()->type,
                'settings_id'   => $settings->id,
                'cache_rebuilt' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to apply settings changes: ' . $e->getMessage());
            throw $e;
        }
    }

    private function notifyDeveloperAboutSettingsUpdate(DeveloperSetting $settings, User $user, array $oldSettings): void
    {
        try {
            $changedFields = array_keys(array_diff_assoc($settings->toArray(), $oldSettings));
            if (count($changedFields) > 0) {
                Log::info('Developer settings update notification', [
                    'user_id'           => $user->id,
                    'user_type'         => $user->type,
                    'settings_id'       => $settings->id,
                    'changed_fields'    => $changedFields,
                    'cache_invalidated' => true,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log settings update notification: ' . $e->getMessage());
        }
    }

    private function validateAndSanitizeIPs(string $ipString): array
    {
        $ips = array_map('trim', explode(',', $ipString));
        $valid = [];
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) $valid[] = $ip;
        }
        return $valid;
    }

    private function validateAndSanitizeEmails(string $emailString): array
    {
        $emails = array_map('trim', explode(',', $emailString));
        $valid  = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = filter_var($email, FILTER_SANITIZE_EMAIL);
            }
        }
        return $valid;
    }

    private function sanitizeLogData(array $data): array
    {
        $sensitive = [
            'password', 'secret', 'token', 'key',
            'credit_card', 'cvv', 'ssn',
            'developer_smtp_password', 'developer_secret_key',
        ];

        foreach ($data as $key => $value) {
            foreach ($sensitive as $needle) {
                if (stripos($key, $needle) !== false && !empty($value)) {
                    $data[$key] = '[REDACTED]';
                }
            }
        }
        return $data;
    }

    /**
 * Cache config.
 */
public function configCache()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('config:cache');
        return redirect()->back()->with('success', 'Configuration cached successfully.');
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('config:cache failed: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to cache config: ' . $e->getMessage());
    }
}

public function configClear()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        return redirect()->back()->with('success', 'Configuration cache cleared.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to clear config: ' . $e->getMessage());
    }
}

public function routeCache()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('route:cache');
        return redirect()->back()->with('success', 'Routes cached successfully.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to cache routes: ' . $e->getMessage());
    }
}

public function routeClear()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        return redirect()->back()->with('success', 'Route cache cleared.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to clear routes: ' . $e->getMessage());
    }
}

public function viewCache()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('view:cache');
        return redirect()->back()->with('success', 'Views cached successfully.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to cache views: ' . $e->getMessage());
    }
}

public function viewClear()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        return redirect()->back()->with('success', 'View cache cleared.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to clear views: ' . $e->getMessage());
    }
}

public function queueRestart()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('queue:restart');
        return redirect()->back()->with('success', 'Queue restart signal sent.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to restart queue: ' . $e->getMessage());
    }
}

public function sessionClean()
{
    try {
        // Only works if you use the database session driver.
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('last_activity', '<', now()->subMinutes(config('session.lifetime'))->timestamp)
                ->delete();
        }
        return redirect()->back()->with('success', 'Expired sessions cleaned.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to clean sessions: ' . $e->getMessage());
    }
}

public function scheduleRun()
{
    try {
        \Illuminate\Support\Facades\Artisan::call('schedule:run');
        $output = \Illuminate\Support\Facades\Artisan::output();
        return redirect()->back()->with('success', 'Scheduler ran successfully. ' . strip_tags($output));
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Failed to run scheduler: ' . $e->getMessage());
    }
}

}