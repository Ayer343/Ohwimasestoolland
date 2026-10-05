<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SmsService;
use App\Services\AgentInvitationService;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsTemplateService;
use App\Services\EnvironmentConfigService;
use App\Services\PaymentService;
use App\Services\EmailService;
use App\Services\WhatsAppService;

// ✅ BILLING: Billing access service
use App\Services\BillingAccessService;

// Authentication Services
use App\Services\Authentication\PhoneNormalizationService;
use App\Services\Authentication\RoleRedirectionService;
use App\Services\Authentication\ArchivedAccountService;
use App\Services\Authentication\LoginAttemptService;
use App\Services\Authentication\SocialLoginService;
use App\Services\Authentication\PasswordResetService;
use App\Services\Authentication\TwoFactorAuthService;
use App\Services\Authentication\LoginActivityService;

// Theme Services
use App\Contracts\Theme\ThemeServiceInterface;
use App\Contracts\Theme\ThemeRepositoryInterface;
use App\Repositories\Theme\ThemeRepository;
use App\Repositories\Theme\ThemeCacheRepository;
use App\Services\Theme\Support\SidebarThemeRegistry;
use App\Services\Theme\ThemeCssGenerator;
use App\Services\Theme\ThemeService;

// ✅ INVOICE: for observers
use App\Models\PropertyUnitInvoice;
use App\Observers\PropertyUnitInvoiceObserver;

// ✅ BILLING: for billing state & agreements
use App\Models\DeveloperSetting;
use App\Models\AdminBillingRecord;
use App\Models\User;

use Illuminate\Support\Facades\Gate;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Policies\PropertyOwnershipTransferPolicy;
use App\Policies\PropertyPolicy;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Canonical default shape for the login billing state.
     *
     * Single source of truth for both the controller and the view composer.
     * Any code that produces $loginBillingState must return this shape.
     */
    public const LOGIN_BILLING_STATE_DEFAULT = [
        'state'   => null,
        'overdue' => false,
        'pending' => false,
    ];

    /**
     * Build the canonical login billing state payload.
     *
     * Used by both the view composer here and (optionally) the controller,
     * so the blade receives the same shape from either source.
     *
     * @param  string|null $state
     * @return array{state: ?string, overdue: bool, pending: bool}
     */
    public static function buildLoginBillingState(?string $state): array
    {
        return [
            'state'   => $state,
            'overdue' => in_array($state, ['active_overdue', 'active_suspended'], true),
            'pending' => $state === 'pending_signature',
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ==================== COMMUNICATION SERVICES ====================

        $this->app->singleton(SmsService::class, fn () => new SmsService());
        $this->app->singleton(EmailService::class, fn () => new EmailService());
        $this->app->singleton(WhatsAppService::class, fn () => new WhatsAppService());
        $this->app->singleton(SmsTemplateService::class, fn () => new SmsTemplateService());
        $this->app->singleton(EnvironmentConfigService::class, fn () => new EnvironmentConfigService());
        $this->app->singleton(PaymentService::class, fn () => new PaymentService());

        $this->app->singleton(MultiChannelInvitationService::class, function ($app) {
            return new MultiChannelInvitationService(
                $app->make(SmsService::class),
                $app->make(EmailService::class),
                $app->make(WhatsAppService::class),
                $app->make(SmsTemplateService::class)
            );
        });

        $this->app->singleton(AgentInvitationService::class, function ($app) {
            return new AgentInvitationService(
                $app->make(SmsService::class),
                $app->make(MultiChannelInvitationService::class),
                $app->make(EmailService::class),
                $app->make(WhatsAppService::class),
                $app->make(SmsTemplateService::class)
            );
        });

        // ==================== ✅ BILLING ACCESS SERVICE ====================

        $this->app->singleton(BillingAccessService::class, fn () => new BillingAccessService());

        // ==================== AUTHENTICATION SERVICES ====================

        $this->app->singleton(PhoneNormalizationService::class, fn () => new PhoneNormalizationService());
        $this->app->singleton(RoleRedirectionService::class, fn () => new RoleRedirectionService());
        $this->app->singleton(ArchivedAccountService::class, fn () => new ArchivedAccountService());
        $this->app->singleton(LoginAttemptService::class, fn () => new LoginAttemptService());
        $this->app->singleton(SocialLoginService::class, fn () => new SocialLoginService());
        $this->app->singleton(PasswordResetService::class, fn () => new PasswordResetService());
        $this->app->singleton(TwoFactorAuthService::class, fn () => new TwoFactorAuthService());
        $this->app->singleton(LoginActivityService::class, fn () => new LoginActivityService());

        // ==================== 🎨 THEME SERVICES ====================

        $this->app->singleton(SidebarThemeRegistry::class);
        $this->app->singleton(ThemeCssGenerator::class);
        $this->app->singleton(ThemeCacheRepository::class);

        $this->app->singleton(ThemeRepository::class);
        $this->app->alias(ThemeRepository::class, ThemeRepositoryInterface::class);

        $this->app->singleton(ThemeService::class);
        $this->app->alias(ThemeService::class, ThemeServiceInterface::class);

        // Register custom blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production-like environments
        $appEnv   = env('APP_ENV');
        $appUrl   = config('app.url');
        $assetUrl = env('ASSET_URL');

        if ($appEnv === 'production' || $appEnv === 'railway' || str_starts_with((string) $appUrl, 'https://')) {
            URL::forceScheme('https');
            $this->app['url']->forceRootUrl($appUrl);

            if ($assetUrl) {
                config(['app.asset_url' => $assetUrl]);
                URL::forceRootUrl($assetUrl);
            } else {
                config(['app.asset_url' => $appUrl]);
            }
        }

        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        $this->registerPolicies();
        $this->registerCustomGates();
        $this->registerModelObservers();        // ✅ INVOICE
        $this->registerBillingModelListeners(); // ✅ BILLING — always-on billing guards
        $this->registerViewComposers();
        $this->registerValidationRules();
        $this->registerMacros();
        $this->registerCustomConfiguration();

        // ✅ GUARD: warn (but don't crash) if the DB session isn't strict.
        //           Silent data coercion is worse than a boot warning.
        $this->warnIfStrictModeIsOff();

        $this->autoCheckPendingEnvUpdates();

        // ✅ BILLING: share the banner once, not per-view
        $this->shareBillingContext();

        $this->registerAuthenticationEvents();
        $this->registerTwoFactorRoutes();
        $this->configureAuthentication();
    }

    /**
     * ✅ GUARD: Warn at boot time if MySQL is not running in strict mode.
     *
     * Why this exists:
     *
     *   Strict mode is what turns silent data corruption into a hard error.
     *   Without it, MySQL/MariaDB happily coerces invalid values to `''` or
     *   `0`, truncates over-length strings, and accepts `NULL` into `NOT NULL`
     *   columns. The bug that took hours to find in this codebase — the
     *   `user_activities.action` column silently getting `''` — could only
     *   have happened because strict mode was off.
     *
     *   This method checks the session `sql_mode` on every web request and
     *   logs a warning if `STRICT_TRANS_TABLES` is missing. It never throws
     *   and never touches the response — it's a canary, not a gate.
     *
     * Why web requests only (not console):
     *
     *   - `php artisan migrate`, `queue:work`, and `tinker` all boot the
     *     provider. Logging the warning there would be noise on every command.
     *   - Console commands generally run in controlled environments where
     *     strict mode matters less (migrations explicitly define their schemas).
     *   - If you *do* want console warnings too, remove the `runningInConsole()`
     *     check — but expect the log to fill up during development.
     *
     * Fail-open by design:
     *
     *   - If the DB isn't reachable yet (rare but possible during early boot),
     *     the catch block swallows the exception and returns silently.
     *   - The app never fails to boot because of this check.
     */
    protected function warnIfStrictModeIsOff(): void
    {
        // Skip in console — commands boot frequently and would spam the log.
        if (app()->runningInConsole()) {
            return;
        }

        // Only relevant for MySQL/MariaDB connections.
        if (config('database.default') !== 'mysql') {
            return;
        }

        try {
            $mode = \DB::selectOne('SELECT @@sql_mode AS mode')?->mode ?? '';

            if (!str_contains($mode, 'STRICT_TRANS_TABLES')) {
                Log::warning('[DB] MySQL strict mode is OFF. Silent data coercion is possible.', [
                    'sql_mode'   => $mode,
                    'connection' => config('database.default'),
                    'hint'       => 'Set sql_mode=STRICT_TRANS_TABLES,... in my.ini and set '
                                  . "'strict' => true in config/database.php.",
                ]);
            }
        } catch (\Throwable $e) {
            // DB not reachable yet (e.g. booting before connection is
            // established). Silently skip — this is a warning mechanism,
            // not a prerequisite.
        }
    }

    /**
     * Register application policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(PropertyOwnershipTransfer::class, PropertyOwnershipTransferPolicy::class);
    }

    /**
     * Register custom gates for property ownership transfer.
     */
    protected function registerCustomGates(): void
    {
        Gate::define('transfer-ownership', function ($user, $property) {
            if (!$user->isLandlord()) return false;
            if ($user->id !== $property->landlord_id) return false;

            $pendingTransfer = $property->currentOwnershipTransfer;
            if ($pendingTransfer && $pendingTransfer->status === PropertyOwnershipTransfer::STATUS_PENDING) {
                return false;
            }
            return true;
        });

        Gate::define('view-property-history', function ($user, $property) {
            if ($user->isAdmin()) return true;
            if ($user->isLandlord() && $user->id === $property->landlord_id) return true;
            return false;
        });

        Gate::define('manage-webhooks', fn ($user, $transfer) => $user->isAdmin());

        Gate::define('manage-2fa', fn ($user) => auth()->check() && $user->id === auth()->id());

        Gate::define('view-login-activity', function ($user, $targetUser = null) {
            if ($targetUser && $user->id === $targetUser->id) return true;
            if ($user->isAdmin() || $user->isSuperAdmin()) return true;
            return false;
        });

        Gate::define('impersonate-user', function ($user, $targetUser) {
            if ($user->id === $targetUser->id) return false;
            if (!$user->isSuperAdmin()) return false;
            if ($targetUser->isSuperAdmin()) return false;
            return true;
        });

        Gate::define('force-logout-user', function ($user, $targetUser) {
            if (!$user->isAdmin() && !$user->isSuperAdmin()) return false;
            if ($user->id === $targetUser->id) return false;
            if ($user->isSuperAdmin()) return !$targetUser->isSuperAdmin();
            if ($targetUser->isSuperAdmin() || $targetUser->isAdmin()) return false;
            return true;
        });

        // ✅ INVOICE: Invoice-level gates
        Gate::define('record-invoice-payment', function ($user, PropertyUnitInvoice $invoice) {
            if ($user->isSuperAdmin() || $user->isAdmin()) return true;
            if ($user->isLandlord() || $user->hasRole('landlord')) {
                return $invoice->landlord_id === $user->id;
            }
            return false;
        });

        Gate::define('void-invoice', function ($user, PropertyUnitInvoice $invoice) {
            if ($user->isSuperAdmin() || $user->isAdmin()) return true;
            if (($user->isLandlord() || $user->hasRole('landlord')) && $invoice->landlord_id === $user->id) {
                return in_array($invoice->status, [
                    PropertyUnitInvoice::STATUS_PENDING,
                    PropertyUnitInvoice::STATUS_PARTIAL,
                    PropertyUnitInvoice::STATUS_OVERDUE,
                ]);
            }
            return false;
        });

        // ✅ GHANA: Lease compliance gate
        Gate::define('view-lease-compliance', function ($user, $lease) {
            if ($user->isSuperAdmin() || $user->isAdmin()) return true;
            if (($user->isLandlord() || $user->hasRole('landlord')) && $lease->landlord_id === $user->id) {
                return true;
            }
            if (($user->isTenant() || $user->hasRole('tenant')) && $lease->tenant_id === $user->id) {
                return true;
            }
            return false;
        });

        // ================================================================
        // ✅ ADMIN: PAYMENT MANAGEMENT GATE
        // ================================================================
        //
        // Used by PaymentConfigController (togglePaymentGateway,
        // syncPaymentProviders), PaymentProviderController (admin), and
        // any future payment-admin surface.
        //
        // Policy:
        //   - Super Admin and Admin → always allowed.
        //   - Landlord → allowed only when
        //       config('billing.allow_landlord_payment_management') is true.
        //       Default is false, because gateway credentials are a
        //       system-wide setting, not a per-landlord one.
        //   - Everyone else → denied.
        //
        // This gate is independent from the `billing.restricted` /
        // `billing.read-only` gates: those govern *writes while overdue*,
        // this gate governs *who may touch payment config at all*.
        // Both must pass for a payment-config write to be allowed.
        //
        Gate::define('manage-payments', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;

            if ($user->isSuperAdmin() || $user->isAdmin()) {
                return true;
            }

            if (config('billing.allow_landlord_payment_management', false)
                && ($user->isLandlord() || $user->hasRole('landlord'))) {
                return true;
            }

            return false;
        });

        // Convenience alias for routes that specifically toggle gateways.
        // Reads more naturally at the route level than `manage-payments`.
        Gate::define('manage-payment-gateways', function ($user = null) {
            return Gate::forUser($user ?: auth()->user())->allows('manage-payments');
        });

        // ================================================================
        // ✅ NEW: DEVELOPER PAYMENT GATES
        // ================================================================
        //
        // Two gates, deliberately separated:
        //
        //   manage-developer-payments  → may access the developer payment
        //                                 controller at all (view + configure)
        //
        //   manage-developer-billing   → may call billAdmin (charge the
        //                                 super admin). This is split out so
        //                                 you can later allow developers to
        //                                 *view* their config while billing
        //                                 is frozen, without opening the
        //                                 billAdmin endpoint.
        //
        // Policy for manage-developer-payments:
        //   - Developer (role or type=5) → allowed.
        //   - Super Admin → allowed (for audit / support). Flip the
        //     `allow_super_admin_configure` config flag to `false` if
        //     you want super admins to *view* only — though in that case
        //     you'd also want a separate `view-developer-payments` gate.
        //   - Everyone else → denied.
        //
        Gate::define('manage-developer-payments', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;

            if ($user->hasRole('developer') || $user->type == 5) {
                return true;
            }

            if ($user->isSuperAdmin()) {
                // Controlled by config so you can flip this off without
                // editing the provider. Default: allow (audit-friendly).
                return (bool) config(
                    'developer_payments.allow_super_admin_configure',
                    env('DEVELOPER_ALLOW_SA_CONFIGURE', true)
                );
            }

            return false;
        });

        Gate::define('manage-developer-billing', function ($user = null) {
    $user = $user ?: auth()->user();
    if (!$user) return false;

    // Billing is a developer-only action. Super admins can view the
    // developer page (audit) but must never trigger a charge on their
    // own behalf through this gate.
    if (! ($user->hasRole('developer') || $user->type == 5)) {
        return false;
    }

    return (bool) env('DEVELOPER_PAYMENT_CAN_BILL', true);
});

        // ================================================================
        // ✅ BILLING: Gates
        // ================================================================

        Gate::define('billing.restricted', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;
            return app(BillingAccessService::class)->shouldRestrictSuperAdmin($user)
                || app(BillingAccessService::class)->shouldRestrictAdminToReadOnly($user);
        });

        Gate::define('billing.read-only', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;
            return app(BillingAccessService::class)->shouldRestrictAdminToReadOnly($user);
        });

        Gate::define('billing.hard-locked', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;
            return app(BillingAccessService::class)->shouldHardLockLandlordOrTenant($user);
        });

        Gate::define('perform-billing-sensitive-action', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;
            return app(BillingAccessService::class)->canPerformWrite($user);
        });

        Gate::define('is-primary-billing-contact', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) return false;

            $developer = DeveloperSetting::current();
            if (!$developer) return false;

            return AdminBillingRecord::where('developer_setting_id', $developer->id)
                ->where('super_admin_id', $user->id)
                ->where('is_primary_for_billing', true)
                ->exists();
        });

        Gate::define('can-pay-system-billing', function ($user = null) {
            $user = $user ?: auth()->user();
            if (!$user) return false;

            return $user->type === User::TYPE_SUPER_ADMIN;
        });
    }

    /**
     * ✅ INVOICE: Register model observers.
     */
    protected function registerModelObservers(): void
    {
        PropertyUnitInvoice::observe(PropertyUnitInvoiceObserver::class);
    }

    /**
     * ✅ BILLING: Register model event listeners for AdminBillingRecord.
     *
     * Why this lives on the provider and not in the model's boot():
     *
     *   The model's boot() has, at various points, failed to run reliably —
     *   likely due to opcache serving a stale compiled version, or a trait
     *   conflict in the class hierarchy. When boot() doesn't run, none of
     *   the model's static::saving / static::creating / static::updated
     *   hooks register, and every downstream feature that depends on them
     *   silently stops working (empty-status guard, overdue flip, audit log).
     *
     *   Registering the listeners here guarantees they are attached on
     *   every request, regardless of whether the model's boot() runs.
     *   The behaviour is identical; only the registration site differs.
     *
     * IMPORTANT: The model's own boot() has been REMOVED. Do not re-add
     * listeners there — doing so would double-register every hook and
     * produce duplicate audit-log entries on every write.
     *
     * Guards attached:
     *   1. creating (pre-insert): populate invoice_number, agreement_number,
     *      requested_by, created_by, due_date, payment_method, billing_month.
     *      Also backfills `description` if the caller forgot it, so strict
     *      mode never rejects an insert over the NOT NULL `description`
     *      column.
     *   2. saving (pre-write):
     *      a. normalize status (trim whitespace)
     *      b. prevent empty status overwrite on existing records
     *      c. auto-flip to overdue from a live status when due_date is past
     *   3. updated (post-save): audit log the diff
     *   4. created (post-save): audit log the new record
     *   5. deleted (post-save): audit log the deletion
     *   6. saved   (post-save): clear DeveloperSetting caches so the state
     *      machine re-evaluates on the next read
     *   7. deleted (post-delete): same cache clear so a deleted primary
     *      agreement doesn't leave a stale cached state
     *
     * NOTE ON CLOSURE SCOPE:
     *   Each closure below receives ONLY the model instance ($record).
     *   Outer variables (like the local $model) are NOT in scope inside
     *   a closure unless captured via `use (...)`. To keep things simple
     *   and safe, the guard branch references the model class via its
     *   fully-qualified name rather than a captured variable.
     *
     * NOTE ON WHICH STATUS VALUE TO INSPECT:
     *   getRawOriginal('status')   → the PRE-SAVE value loaded from the DB.
     *   getAttributes()['status']  → the INCOMING value about to be written.
     *
     *   The empty-wipe guard MUST inspect the INCOMING value — that is what
     *   the caller is trying to save. Inspecting the original would miss
     *   the exact case we are guarding against
     *   (original="active", incoming="").
     */
    protected function registerBillingModelListeners(): void
    {
        $model = \App\Models\AdminBillingRecord::class;

        // ------------------------------------------------------------------
        // 1) CREATING — populate defaults for new records
        // ------------------------------------------------------------------
        $model::creating(function ($record) {
            if (empty($record->invoice_number)) {
                $record->invoice_number = 'SA-REQ-' . strtoupper(uniqid());
            }

            if (empty($record->agreement_number)) {
                $record->agreement_number = $record->generateAgreementNumber();
            }

            if (empty($record->requested_by) && \Illuminate\Support\Facades\Auth::check()) {
                $record->requested_by = \Illuminate\Support\Facades\Auth::id();
                $record->requested_at = now();
            }

            if (empty($record->created_by) && \Illuminate\Support\Facades\Auth::check()) {
                $record->created_by = \Illuminate\Support\Facades\Auth::id();
            }

            if (empty($record->due_date) && !empty($record->start_date)) {
                $record->due_date = \Carbon\Carbon::parse($record->start_date)->addDays(30);
            }

            if (empty($record->payment_method)) {
                $record->payment_method = \App\Models\AdminBillingRecord::PAYMENT_METHOD_BANK_TRANSFER;
            }

            if (empty($record->billing_month)) {
                $record->billing_month = \Carbon\Carbon::now()->format('Y-m');
            }

            // ✅ OPTIONAL FIX: description is NOT NULL in the schema with no
            //    default. Backfill it here so any caller that forgets it
            //    still gets a valid row instead of a strict-mode error.
            if (empty($record->description)) {
                $record->description = 'System billing record — '
                    . ($record->agreement_number ?? $record->invoice_number ?? 'pending');
            }
        });

        // ------------------------------------------------------------------
        // 2) SAVING — normalize, guard, and auto-flip
        // ------------------------------------------------------------------
        $model::saving(function ($record) {
            // (a) Normalize status: trim whitespace on the INCOMING value
            $incoming = $record->getAttributes()['status'] ?? null;

            if (is_string($incoming)) {
                $trimmed = trim($incoming);

                if ($trimmed !== $incoming) {
                    $record->status = $trimmed;
                }
            }

            // (b) GUARD: never let status be wiped on an existing record.
            if ($record->exists && $record->isDirty('status')) {
                $incoming = $record->getAttributes()['status'] ?? null;

                if ($incoming === '' || $incoming === null) {
                    $existing = \App\Models\AdminBillingRecord::query()
                        ->whereKey($record->getKey())
                        ->value('status');

                    if (!empty($existing)) {
                        $record->status = $existing;

                        \Illuminate\Support\Facades\Log::warning(
                            '[AdminBillingRecord] Blocked empty status overwrite',
                            [
                                'record_id'        => $record->getKey(),
                                'preserved_status' => $existing,
                                'dirty_attributes' => array_keys($record->getDirty()),
                            ]
                        );
                    }
                }
            }

            // (c) Auto-flip to overdue ONLY from a live status.
            if ($record->exists) {
                $currentStatus = $record->getAttributes()['status'] ?? null;

                if (in_array($currentStatus, [
                    \App\Models\AdminBillingRecord::STATUS_ACTIVE,
                    \App\Models\AdminBillingRecord::STATUS_APPROVED,
                ], true)) {
                    $before = $currentStatus;

                    $record->updateStatusBasedOnDueDate();

                    $after = $record->getAttributes()['status'] ?? null;

                    if ($after !== $before) {
                        \Illuminate\Support\Facades\Log::info(
                            '[AdminBillingRecord] Status auto-flipped on save',
                            [
                                'record_id'        => $record->getKey(),
                                'from'             => $before,
                                'to'               => $after,
                                'dirty_attributes' => array_keys($record->getDirty()),
                            ]
                        );
                    }
                }
            }
        });

        // ------------------------------------------------------------------
        // 3) UPDATED — audit log the diff
        // ------------------------------------------------------------------
        $model::updated(function ($record) {
            $changes = $record->getChanges();
            unset($changes['updated_at']);

            if (!empty($changes)) {
                $original = $record->getOriginal();
                unset($original['updated_at']);

                \Illuminate\Support\Facades\Log::info('Admin billing record updated', [
                    'record_id'        => $record->id,
                    'agreement_number' => $record->agreement_number,
                    'changes'          => $changes,
                    'old_values'       => $original,
                    'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                    'timestamp'        => now()->toDateTimeString(),
                ]);
            }
        });

        // ------------------------------------------------------------------
        // 4) CREATED — audit log the new record
        // ------------------------------------------------------------------
        $model::created(function ($record) {
            \Illuminate\Support\Facades\Log::info('Admin billing record created', [
                'record_id'        => $record->id,
                'agreement_number' => $record->agreement_number,
                'amount'           => $record->amount,
                'super_admin_id'   => $record->super_admin_id,
                'is_primary'       => $record->is_primary_for_billing,
                'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                'timestamp'        => now()->toDateTimeString(),
            ]);
        });

        // ------------------------------------------------------------------
        // 5) DELETED — audit log the deletion
        // ------------------------------------------------------------------
        $model::deleted(function ($record) {
            \Illuminate\Support\Facades\Log::info('Admin billing record deleted', [
                'record_id'        => $record->id,
                'agreement_number' => $record->agreement_number,
                'record_data'      => $record->toArray(),
                'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                'timestamp'        => now()->toDateTimeString(),
            ]);
        });

        // ------------------------------------------------------------------
        // 6) SAVED — invalidate DeveloperSetting caches
        // ------------------------------------------------------------------
        $model::saved(function ($record) {
            \App\Models\DeveloperSetting::forgetCurrent();
        });

        // ------------------------------------------------------------------
        // 7) DELETED — invalidate DeveloperSetting caches (belt-and-braces)
        // ------------------------------------------------------------------
        $model::deleted(function ($record) {
            \App\Models\DeveloperSetting::forgetCurrent();
        });
    }

    /**
     * Auto-check for pending environment updates.
     */
    protected function autoCheckPendingEnvUpdates(): void
    {
        if (app()->runningInConsole() || !app()->request->is('admin/*')) {
            return;
        }

        try {
            if (session()->has('pending_env_update') && !session('pending_env_update.attempted', false)) {
                if (session('pending_env_update.timestamp', 0) < now()->subSeconds(5)->timestamp) {
                    $this->processPendingEnvUpdate();
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    /**
     * Process pending environment update.
     */
    protected function processPendingEnvUpdate(): void
    {
        try {
            $pendingUpdate = session()->get('pending_env_update');

            session()->put('pending_env_update.attempted', true);
            session()->save();

            $environmentService = app(EnvironmentConfigService::class);
            $emailUpdateResult = $environmentService->updateEmailConfiguration(
                $pendingUpdate['email'],
                $pendingUpdate['password']
            );

            if ($emailUpdateResult['success']) {
                session()->forget('pending_env_update');
                session()->save();

                session()->flash('env_update_complete', true);
                session()->flash('env_update_message', 'Email configuration updated successfully in background.');
            } else {
                session()->flash('env_update_warning', true);
                session()->flash('env_update_message', 'Background email configuration update failed: ' . $emailUpdateResult['message']);
            }
        } catch (\Exception $e) {
            session()->flash('env_update_error', true);
            session()->flash('env_update_message', 'Background update error: ' . $e->getMessage());
        }
    }

    /**
     * ✅ BILLING: Share billing context once per request.
     */
    protected function shareBillingContext(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $billing = app(BillingAccessService::class);
            $payload = $billing->bannerPayload();

            view()->share('billingBanner', $payload);

            if (!empty($payload['visible'])) {
                view()->share('billingState', $payload['state']);
            }

            $user = auth()->user();

            if ($user) {
                view()->share(
                    'billingReadOnly',
                    $billing->shouldRestrictAdminToReadOnly($user)
                );

                view()->share(
                    'systemBillingNotice',
                    $billing->shouldRestrictSuperAdmin($user)
                    && in_array(
                        $payload['state'] ?? null,
                        ['active_due_soon', 'active_overdue', 'active_suspended'],
                        true
                    )
                );
            } else {
                view()->share('billingReadOnly', false);
                view()->share('systemBillingNotice', false);
            }
        } catch (\Throwable $e) {
            Log::warning('[Billing] shareBillingContext failed', [
                'error' => $e->getMessage(),
            ]);

            view()->share('billingBanner', ['visible' => false]);
            view()->share('billingReadOnly', false);
            view()->share('systemBillingNotice', false);
        }
    }

    /**
     * Register custom blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('money', fn ($e) => "<?php echo 'GH₵ ' . number_format($e, 2); ?>");

        Blade::directive('date', fn ($e) => "<?php echo ($e) ? ($e)->format('M j, Y') : 'N/A'; ?>");

        Blade::directive('datetime', fn ($e) => "<?php echo ($e) ? ($e)->format('M j, Y g:i A') : 'N/A'; ?>");

        Blade::directive('statusbadge', function ($expression) {
            return "<?php
                \$status = $expression;
                \$classes = [
                    'active' => 'badge bg-success',
                    'inactive' => 'badge bg-secondary',
                    'pending' => 'badge bg-warning',
                    'suspended' => 'badge bg-danger',
                    'draft' => 'badge bg-secondary',
                    'assigned' => 'badge bg-info',
                    'in_progress' => 'badge bg-primary',
                    'completed' => 'badge bg-success',
                    'cancelled' => 'badge bg-danger',
                    'sent' => 'badge bg-info',
                    'accepted' => 'badge bg-success',
                    'expired' => 'badge bg-warning',
                    'failed' => 'badge bg-danger',
                    'revoked' => 'badge bg-dark'
                ];
                \$class = \$classes[\$status] ?? 'badge bg-secondary';
                echo '<span class=\"' . \$class . '\">' . ucfirst(str_replace('_', ' ', \$status)) . '</span>';
            ?>";
        });

        Blade::directive('userrole', function ($expression) {
            return "<?php
                \$type = $expression;
                \$roles = [
                    0 => 'Super Admin',
                    1 => 'Admin',
                    2 => 'Landlord',
                    3 => 'Tenant',
                    4 => 'Field Agent',
                    5 => 'Developer',
                    6 => 'Security Checkpoint'
                ];
                echo \$roles[\$type] ?? 'Unknown Role';
            ?>";
        });

        Blade::directive('freebadge', fn ($e) => "<?php echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Free</span>'; ?>");

        Blade::directive('envstatus', function ($expression) {
            return "<?php
                \$config = $expression;
                if (\$config && isset(\$config['configured']) && \$config['configured']) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Configured</span>';
                } else {
                    echo '<span class=\"badge bg-warning\"><i class=\"fas fa-exclamation-triangle\"></i> Needs Setup</span>';
                }
            ?>";
        });

        Blade::directive('emailstatus', function ($expression) {
            return "<?php
                \$emailConfig = $expression;
                if (\$emailConfig && isset(\$emailConfig['can_send_emails']) && \$emailConfig['can_send_emails']) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Ready</span>';
                } else {
                    echo '<span class=\"badge bg-danger\"><i class=\"fas fa-times-circle\"></i> Not Ready</span>';
                }
            ?>";
        });

        Blade::directive('paymentstatus', function ($expression) {
            return "<?php
                \$paymentConfig = $expression;
                if (\$paymentConfig && isset(\$paymentConfig['recipient_configured']) && \$paymentConfig['recipient_configured'] && isset(\$paymentConfig['methods_available']) && \$paymentConfig['methods_available']) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Ready</span>';
                } else {
                    echo '<span class=\"badge bg-warning\"><i class=\"fas fa-exclamation-triangle\"></i> Needs Setup</span>';
                }
            ?>";
        });

        Blade::directive('channelstatus', function ($expression) {
            return "<?php
                \$channel = $expression;
                \$statuses = [
                    'sms' => ['badge bg-primary', 'SMS'],
                    'whatsapp' => ['badge bg-success', 'WhatsApp'],
                    'email' => ['badge bg-info', 'Email'],
                    'both' => ['badge bg-warning', 'Multiple Channels']
                ];
                \$status = \$statuses[\$channel] ?? ['badge bg-secondary', 'Unknown'];
                echo '<span class=\"' . \$status[0] . '\">' . \$status[1] . '</span>';
            ?>";
        });

        Blade::directive('envupdatestatus', function ($expression) {
            return "<?php
                \$status = $expression;
                switch (\$status) {
                    case 'processing':
                        echo '<span class=\"badge bg-info\"><i class=\"fas fa-sync-alt fa-spin\"></i> Processing</span>';
                        break;
                    case 'success':
                        echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Completed</span>';
                        break;
                    case 'warning':
                        echo '<span class=\"badge bg-warning\"><i class=\"fas fa-exclamation-triangle\"></i> Needs Attention</span>';
                        break;
                    case 'error':
                        echo '<span class=\"badge bg-danger\"><i class=\"fas fa-times-circle\"></i> Failed</span>';
                        break;
                    default:
                        echo '<span class=\"badge bg-secondary\"><i class=\"fas fa-clock\"></i> Pending</span>';
                }
            ?>";
        });

        Blade::directive('twofactorstatus', function ($expression) {
            return "<?php
                \$user = $expression;
                if (\$user && \$user->two_factor_enabled) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-shield-alt\"></i> 2FA Enabled</span>';
                } else {
                    echo '<span class=\"badge bg-secondary\"><i class=\"fas fa-shield-alt\"></i> 2FA Disabled</span>';
                }
            ?>";
        });

        Blade::directive('loginactivity', function ($expression) {
            return "<?php
                \$lastLogin = $expression;
                if (\$lastLogin) {
                    \$diff = now()->diffInMinutes(\$lastLogin);
                    if (\$diff < 5) {
                        echo '<span class=\"badge bg-success\"><i class=\"fas fa-circle\"></i> Just now</span>';
                    } elseif (\$diff < 60) {
                        echo '<span class=\"badge bg-info\"><i class=\"fas fa-clock\"></i> ' . \$diff . ' min ago</span>';
                    } elseif (\$diff < 1440) {
                        echo '<span class=\"badge bg-warning\"><i class=\"fas fa-clock\"></i> ' . round(\$diff / 60) . ' hours ago</span>';
                    } else {
                        echo '<span class=\"badge bg-secondary\"><i class=\"fas fa-calendar\"></i> ' . \$lastLogin->format('M j, Y') . '</span>';
                    }
                } else {
                    echo '<span class=\"badge bg-secondary\">Never</span>';
                }
            ?>";
        });

        Blade::directive('deviceicon', function ($expression) {
            return "<?php
                \$device = $expression;
                if (str_contains(\$device, 'Mobile') || str_contains(\$device, 'Android') || str_contains(\$device, 'iPhone')) {
                    echo '<i class=\"fas fa-mobile-alt\"></i>';
                } elseif (str_contains(\$device, 'Tablet') || str_contains(\$device, 'iPad')) {
                    echo '<i class=\"fas fa-tablet-alt\"></i>';
                } else {
                    echo '<i class=\"fas fa-desktop\"></i>';
                }
            ?>";
        });

        Blade::directive('iplocation', function ($expression) {
            return "<?php
                \$location = $expression;
                if (\$location && isset(\$location['country'])) {
                    echo '<span class=\"badge bg-light text-dark\"><i class=\"fas fa-map-marker-alt\"></i> ' . \$location['country'] . '</span>';
                } else {
                    echo '<span class=\"badge bg-light text-dark\"><i class=\"fas fa-question-circle\"></i> Unknown</span>';
                }
            ?>";
        });

        Blade::directive('themeappearance', function ($expression) {
            return "<?php
                \$appearance = $expression;
                \$icons = ['light' => 'fa-sun', 'dark' => 'fa-moon', 'system' => 'fa-desktop'];
                \$icon = \$icons[\$appearance] ?? 'fa-sun';
                echo '<i class=\"fas ' . \$icon . '\"></i> ' . ucfirst(\$appearance);
            ?>";
        });

        Blade::directive('sidebartheme', function ($expression) {
            return "<?php                \$theme = $expression;
                \$colors = [
                    'default' => ['bg' => 'linear-gradient(135deg, #7267f0, #6258e0)', 'text' => '#ffffff'],
                    'dark' => ['bg' => 'linear-gradient(135deg, #1a1a2e, #16213e)', 'text' => '#ecf0f1'],
                    'light' => ['bg' => 'linear-gradient(135deg, #ffffff, #f0f0f0)', 'text' => '#4a5568'],
                    'blue' => ['bg' => 'linear-gradient(135deg, #1e3a5f, #1a56db)', 'text' => '#ffffff'],
                    'green' => ['bg' => 'linear-gradient(135deg, #065f46, #059669)', 'text' => '#ffffff'],
                    'custom' => ['bg' => 'linear-gradient(135deg, #7267f0, #6258e0)', 'text' => '#ffffff']
                ];
                \$themeData = \$colors[\$theme] ?? \$colors['default'];
                echo '<div class=\"w-8 h-8 rounded-lg\" style=\"background: ' . \$themeData['bg'] . '; color: ' . \$themeData['text'] . ';\"></div>';
            ?>";
        });

        // ========== ✅ GHANA: LEASE & PAYMENT-PHASE DIRECTIVES ==========

        Blade::directive('ghanaadvancebadge', function ($expression) {
            return "<?php
                \$lease = $expression;
                if (\$lease && \$lease->current_phase === 'advance') {
                    echo '<span class=\"badge bg-info\"><i class=\"fas fa-calendar-check\"></i> Advance Phase</span>';
                } elseif (\$lease && \$lease->current_phase === 'monthly') {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-calendar-day\"></i> Monthly Phase</span>';
                } else {
                    echo '<span class=\"badge bg-secondary\">No Phase</span>';
                }
            ?>";
        });

        Blade::directive('ghanacompliancebadge', function ($expression) {
            return "<?php
                \$lease = $expression;
                if (\$lease && \$lease->advance_rent_compliance_status === 'compliant') {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Compliant</span>';
                } elseif (\$lease) {
                    echo '<span class=\"badge bg-warning\" title=\"Exceeds Rent Act 1963 s.25(5) cap\"><i class=\"fas fa-exclamation-triangle\"></i> Exceeds Legal Limit</span>';
                } else {
                    echo '<span class=\"badge bg-secondary\">N/A</span>';
                }
            ?>";
        });

        Blade::directive('ghanapaymentfreq', function ($expression) {
            return "<?php
                \$lease = $expression;
                if (\$lease && \$lease->payment_frequency === 'advance_only') {
                    echo '<span class=\"badge bg-primary\"><i class=\"fas fa-money-bill-wave\"></i> Full Advance</span>';
                } elseif (\$lease) {
                    echo '<span class=\"badge bg-info\"><i class=\"fas fa-sync-alt\"></i> Advance + Monthly</span>';
                } else {
                    echo '<span class=\"badge bg-secondary\">N/A</span>';
                }
            ?>";
        });

        Blade::directive('ghanaadvanceamount', function ($expression) {
            return "<?php
                \$lease = $expression;
                echo \$lease ? ('GH₵ ' . number_format(\$lease->advance_rent_amount ?? 0, 2)) : 'GH₵ 0.00';
            ?>";
        });

        // ========== ✅ INVOICE: DIRECTIVES ==========

        Blade::directive('invoicestatus', function ($expression) {
            return "<?php
                \$invoice = $expression;
                \$status = \$invoice->status ?? 'pending';
                \$map = [
                    'pending' => ['bg-warning',   'Pending',    'fa-clock'],
                    'partial' => ['bg-info',      'Partial',    'fa-adjust'],
                    'paid'    => ['bg-success',   'Paid',       'fa-check-circle'],
                    'overdue' => ['bg-danger',    'Overdue',    'fa-exclamation-triangle'],
                    'void'    => ['bg-secondary', 'Void',       'fa-ban'],
                ];
                [\$cls, \$label, \$icon] = \$map[\$status] ?? ['bg-secondary', ucfirst(\$status), 'fa-circle'];
                echo '<span class=\"badge ' . \$cls . '\"><i class=\"fas ' . \$icon . '\"></i> ' . \$label . '</span>';
            ?>";
        });

        Blade::directive('invoicetypebadge', function ($expression) {
            return "<?php
                \$invoice = $expression;
                \$type = \$invoice->invoice_type ?? 'other';
                \$map = [
                    'advance_rent'     => ['bg-primary', 'Advance Rent',    'fa-calendar-check'],
                    'monthly_rent'     => ['bg-info',    'Monthly Rent',    'fa-calendar-day'],
                    'security_deposit' => ['bg-secondary','Security Deposit','fa-shield-alt'],
                    'utility_deposit'  => ['bg-secondary','Utility Deposit', 'fa-bolt'],
                    'late_fee'         => ['bg-warning', 'Late Fee',        'fa-clock'],
                    'early_termination'=> ['bg-danger',  'Early Termination','fa-times-circle'],
                    'other'            => ['bg-light',   'Other',           'fa-file-invoice'],
                ];
                [\$cls, \$label, \$icon] = \$map[\$type] ?? ['bg-light', ucfirst(str_replace('_', ' ', \$type)), 'fa-file-invoice'];
                echo '<span class=\"badge ' . \$cls . ' text-dark\"><i class=\"fas ' . \$icon . '\"></i> ' . \$label . '</span>';
            ?>";
        });

        Blade::directive('invoicebalance', function ($expression) {
            return "<?php
                \$invoice = $expression;
                \$balance = \$invoice ? max(0, (\$invoice->amount ?? 0) - (\$invoice->amount_paid ?? 0)) : 0;
                echo 'GH₵ ' . number_format(\$balance, 2);
            ?>";
        });

        Blade::directive('outstandingbadge', function ($expression) {
            return "<?php
                \$unit = $expression;
                \$balance = \$unit ? \$unit->outstanding_balance : 0;
                if (\$balance > 0) {
                    echo '<span class=\"badge bg-danger\"><i class=\"fas fa-exclamation-circle\"></i> GH₵ ' . number_format(\$balance, 2) . ' due</span>';
                } else {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Cleared</span>';
                }
            ?>";
        });

        // ========== ✅ BILLING: BLADE DIRECTIVES ==========

        Blade::directive('billingstate', function () {
            return "<?php
                \$__bs = \$billingBanner['state'] ?? null;
                \$__map = [
                    'not_configured'    => ['bg-secondary', 'Not Configured', 'fa-circle'],
                    'pending_signature' => ['bg-warning',   'Awaiting Signature', 'fa-signature'],
                    'active_current'    => ['bg-success',   'Current', 'fa-check-circle'],
                    'active_due_soon'   => ['bg-info',      'Due Soon', 'fa-clock'],
                    'active_overdue'    => ['bg-danger',    'Overdue', 'fa-exclamation-triangle'],
                    'active_suspended'  => ['bg-dark',      'Suspended', 'fa-ban'],
                ];
                [\$cls, \$label, \$icon] = \$__map[\$__bs] ?? ['bg-secondary', 'Unknown', 'fa-question'];
                echo '<span class=\"badge ' . \$cls . '\"><i class=\"fas ' . \$icon . '\"></i> ' . \$label . '</span>';
            ?>";
        });

        Blade::directive('billingduein', function () {
            return "<?php
                \$__days = \$billingBanner['days_until_due'] ?? null;
                if (\$__days === null) {
                    echo '<span class=\"text-muted\">N/A</span>';
                } elseif (\$__days > 0) {
                    echo '<span class=\"text-info\"><i class=\"fas fa-clock\"></i> Due in ' . \$__days . ' day' . (\$__days === 1 ? '' : 's') . '</span>';
                } elseif (\$__days === 0) {
                    echo '<span class=\"text-warning\"><i class=\"fas fa-exclamation-circle\"></i> Due today</span>';
                } else {
                    echo '<span class=\"text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> ' . abs(\$__days) . ' day' . (abs(\$__days) === 1 ? '' : 's') . ' overdue</span>';
                }
            ?>";
        });

        Blade::directive('billingreadonlynotice', function () {
            return "<?php
                if (!empty(\$billingReadOnly)) {
                    echo '<div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">'
                       . '<i class=\"fas fa-lock me-2\"></i>'
                       . '<div><strong>Read-only mode.</strong> System billing is overdue. '
                       . 'Administrative actions are temporarily disabled. Contact your super admin to resolve this.</div>'
                       . '</div>';
                }
            ?>";
        });

        Blade::if('billingwriteallowed', function () {
            return app(BillingAccessService::class)->canPerformWrite();
        });

        Blade::if('billingprimarycontact', function () {
            $user = auth()->user();
            if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) return false;

            $developer = DeveloperSetting::current();
            if (!$developer) return false;

            return AdminBillingRecord::where('developer_setting_id', $developer->id)
                ->where('super_admin_id', $user->id)
                ->where('is_primary_for_billing', true)
                ->exists();
        });

        Blade::if('billingoverdue', function () {
            try {
                $state = app(BillingAccessService::class)->bannerPayload()['state'] ?? null;
                return in_array($state, ['active_overdue', 'active_suspended'], true);
            } catch (\Throwable $e) {
                return false;
            }
        });

        // ========== ✅ NEW: DEVELOPER BLADE DIRECTIVES ==========

        /**
         * @developeraccess
         * ... @enddeveloperaccess
         * Renders content only for users who may manage developer payments.
         */
        Blade::if('developeraccess', function () {
            return auth()->check()
                && auth()->user()->can('manage-developer-payments');
        });

        /**
         * @developercanbill
         * ... @enddevelopercanbill
         * Renders content only for developers who may bill admins.
         */
        Blade::if('developercanbill', function () {
            return auth()->check()
                && auth()->user()->can('manage-developer-billing');
        });
    }

    /**
     * Register view composers.
     */
    protected function registerViewComposers(): void
    {
        \View::composer('*', fn ($view) => $view->with('currentUser', auth()->user()));

        \View::composer(['admin.*', 'superadmin.*'], function ($view) {
            if (app()->bound(SmsService::class)) {
                $view->with('smsStatus', app(SmsService::class)->getSystemStatus());
            }
        });

        \View::composer(['admin.*', 'superadmin.*'], function ($view) {
            if (app()->bound(MultiChannelInvitationService::class)) {
                try {
                    $view->with('multiChannelStatus', app(MultiChannelInvitationService::class)->getServiceConfiguration());
                } catch (\Exception $e) {
                    $view->with('multiChannelStatus', []);
                }
            }
        });

        \View::composer(['admin.registration-plans.*', 'superadmin.registration-plans.*'], function ($view) {
            if (app()->bound(AgentInvitationService::class)) {
                $view->with('invitationStats', app(AgentInvitationService::class)->getInvitationStatistics());
            }
        });

        \View::composer(['field-agent.*', 'admin.registration-plans.*'], fn ($view) => $view->with('isFreeService', true));

        \View::composer(['admin.system-settings.*', 'superadmin.system-settings.*'], function ($view) {
            if (app()->bound(EnvironmentConfigService::class)) {
                $view->with('environmentService', app(EnvironmentConfigService::class));
                try {
                    $view->with('emailConfiguration', app(EnvironmentConfigService::class)->getMailConfiguration());
                } catch (\Exception $e) {
                    $view->with('emailConfiguration', []);
                }
            }

            $pendingUpdate = session()->get('pending_env_update');
            $view->with('hasPendingEnvUpdate', !empty($pendingUpdate));
            $view->with('pendingEnvUpdate', $pendingUpdate);
            $view->with('envUpdateComplete', session('env_update_complete', false));
            $view->with('envUpdateWarning', session('env_update_warning', false));
            $view->with('envUpdateError', session('env_update_error', false));
            $view->with('envUpdateMessage', session('env_update_message', ''));
        });

        \View::composer(['admin.*', 'superadmin.*', 'landlord.*'], function ($view) {
            if (app()->bound(PaymentService::class)) {
                try {
                    $view->with('paymentConfiguration', app(PaymentService::class)->checkPaymentMethodConfiguration());
                } catch (\Exception $e) {
                    $view->with('paymentConfiguration', []);
                }
            }
        });

        \View::composer(['admin.dashboard', 'superadmin.dashboard', 'layouts.admin'], function ($view) {
            if (app()->bound(\App\Models\SystemSetting::class)) {
                try {
                    $settings = \App\Models\SystemSetting::getSettings();
                    $view->with('systemConfiguration', $settings->getSystemConfigurationStatus());
                    $view->with('emailConfigurationStatus', $settings->getEmailStatus());
                    $view->with('paymentConfigurationStatus', $settings->getPaymentConfigurationStatus());
                } catch (\Exception $e) {
                    $view->with('systemConfiguration', []);
                    $view->with('emailConfigurationStatus', []);
                    $view->with('paymentConfigurationStatus', []);
                }
            }

            $pendingUpdate = session()->get('pending_env_update');
            $view->with('hasPendingEnvUpdate', !empty($pendingUpdate));

            if (app()->bound(MultiChannelInvitationService::class)) {
                try {
                    $view->with('channelStatistics', app(MultiChannelInvitationService::class)->getChannelStatistics());
                } catch (\Exception $e) {
                    $view->with('channelStatistics', []);
                }
            }
        });

        \View::composer('*', function ($view) {
            $user = auth()->user();
            if ($user) {
                $twoFactorService = app(TwoFactorAuthService::class);
                $view->with('twoFactorEnabled', $twoFactorService->isEnabled($user));
                $view->with('twoFactorMethod', $user->two_factor_method ?? 'email');
            }
        });

        \View::composer(['auth.profile', 'profile.*', 'user.profile'], function ($view) {
            $user = auth()->user();
            if ($user && app()->bound(LoginActivityService::class)) {
                $activityService = app(LoginActivityService::class);
                $view->with('recentLoginActivity', $activityService->getRecentActivity($user, 10));
                $view->with('totalLogins', $user->login_count ?? 0);
                $view->with('lastLoginAt', $user->last_login_at);
                $view->with('lastLoginIp', $user->last_login_ip);
            }
        });

        \View::composer(['admin.dashboard', 'superadmin.dashboard'], function ($view) {
            try {
                $totalUsers = User::count();
                $activeToday = User::where('last_login_at', '>=', now()->subDay())->count();
                $activeThisWeek = User::where('last_login_at', '>=', now()->subWeek())->count();
                $twoFactorEnabled = User::where('two_factor_enabled', true)->count();

                $view->with('authStats', [
                    'total_users' => $totalUsers,
                    'active_today' => $activeToday,
                    'active_this_week' => $activeThisWeek,
                    'two_factor_enabled' => $twoFactorEnabled,
                    'two_factor_percentage' => $totalUsers > 0 ? round(($twoFactorEnabled / $totalUsers) * 100) : 0,
                ]);

                if (app()->bound(LoginActivityService::class)) {
                    $view->with('recentLogins', \DB::table('login_activities')->orderByDesc('created_at')->limit(10)->get());
                }
            } catch (\Exception $e) {
                $view->with('authStats', []);
                $view->with('recentLogins', []);
            }
        });

        \View::composer('auth.login', function ($view) {
            $socialProviders = [];
            foreach (['google', 'microsoft'] as $provider) {
                if (config("services.{$provider}.client_id") && config("services.{$provider}.client_secret")) {
                    $socialProviders[] = $provider;
                }
            }

            $view->with('socialProviders', $socialProviders);
            $view->with('socialEmail', session('social_email'));
            $view->with('socialName', session('social_name'));
            $view->with('socialProvider', session('social_provider'));

            try {
                $state = DeveloperSetting::current()?->billing_state;

                $view->with(
                    'loginBillingState',
                    self::buildLoginBillingState($state)
                );

                $view->with(
                    'loginBillingOverdue',
                    in_array($state, ['active_overdue', 'active_suspended'], true)
                );
            } catch (\Throwable $e) {
                Log::warning('[Billing] Failed to resolve login billing state', [
                    'error' => $e->getMessage(),
                ]);

                $view->with('loginBillingState', self::LOGIN_BILLING_STATE_DEFAULT);
                $view->with('loginBillingOverdue', false);
            }
        });

        // 🎨 Theme view composer
        \View::composer(['admin.*', 'superadmin.*', 'layouts.admin'], function ($view) {
            try {
                $themeSettings = \App\Http\Controllers\ThemeController::getThemeWithColors();
                $view->with('themeSettings', $themeSettings);
                $view->with('sidebarTheme', $themeSettings['sidebar_theme'] ?? 'default');
                $view->with('appearance', $themeSettings['appearance'] ?? 'system');
                $view->with('customColors', $themeSettings['custom_colors'] ?? []);
                $view->with('themeCss', $themeSettings['css'] ?? '');
            } catch (\Throwable $e) {
                $view->with('themeSettings', []);
                $view->with('sidebarTheme', 'default');
                $view->with('appearance', 'system');
                $view->with('customColors', []);
                $view->with('themeCss', '');
            }
        });

        // ========== ✅ INVOICE: GLOBAL INVOICE SUMMARY ==========
        \View::composer(['landlord.*', 'admin.*', 'superadmin.*'], function ($view) {
            $user = auth()->user();
            if (!$user || !class_exists(PropertyUnitInvoice::class)) {
                return;
            }

            try {
                $query = PropertyUnitInvoice::query();

                if ($user->isLandlord() || $user->hasRole('landlord')) {
                    $query->where('landlord_id', $user->id);
                } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
                    return;
                }

                $view->with('globalInvoiceStats', [
                    'total_invoiced' => (clone $query)->sum('amount'),
                    'total_paid'     => (clone $query)->sum('amount_paid'),
                    'outstanding'    => (clone $query)
                        ->whereIn('status', [
                            PropertyUnitInvoice::STATUS_PENDING,
                            PropertyUnitInvoice::STATUS_PARTIAL,
                            PropertyUnitInvoice::STATUS_OVERDUE,
                        ])
                        ->sum(\DB::raw('amount - amount_paid')),
                    'overdue_count'  => (clone $query)
                        ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
                        ->count(),
                ]);
            } catch (\Throwable $e) {
                $view->with('globalInvoiceStats', [
                    'total_invoiced' => 0,
                    'total_paid'     => 0,
                    'outstanding'    => 0,
                    'overdue_count'  => 0,
                ]);
            }
        });
    }

    /**
     * Register custom validation rules.
     */
    protected function registerValidationRules(): void
    {
        \Validator::extend('ghana_phone', fn ($a, $v) => preg_match('/^(\+233|0)[235]\d{8}$/', $v));
        \Validator::replacer('ghana_phone', fn ($m, $a) => 'The ' . $a . ' must be a valid Ghanaian phone number.');

        \Validator::extend('strong_password', fn ($a, $v) => preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $v));
        \Validator::replacer('strong_password', fn ($m, $a) => 'The ' . $a . ' must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.');

        \Validator::extend('digital_address', fn ($a, $v) => preg_match('/^[A-Z]{2}-\d{3}-\d{4}$/', $v));
        \Validator::replacer('digital_address', fn ($m, $a) => 'The ' . $a . ' must be a valid Ghana digital address (e.g., AA-123-4567).');

        \Validator::extend('free_service', fn () => true);
        \Validator::replacer('free_service', fn () => 'This service is free - no payment required.');

        \Validator::extend('valid_email_config', function ($a, $v) {
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return false;
            try {
                return app(EnvironmentConfigService::class)->validateEmailConfiguration($v);
            } catch (\Exception $e) {
                return false;
            }
        });
        \Validator::replacer('valid_email_config', fn ($m, $a) => 'The ' . $a . ' must be a valid email address and properly configured for the system.');

        \Validator::extend('env_writable', function () {
            try {
                $envPath = base_path('.env');
                return file_exists($envPath) && is_writable($envPath);
            } catch (\Exception $e) {
                return false;
            }
        });
        \Validator::replacer('env_writable', fn () => 'The system environment file is not writable. Please check file permissions.');

        \Validator::extend('valid_channel', fn ($a, $v) => in_array($v, ['sms', 'whatsapp', 'email', 'both'], true));
        \Validator::replacer('valid_channel', fn ($m, $a) => 'The ' . $a . ' must be a valid channel: sms, whatsapp, email, or both.');

        \Validator::extend('no_pending_env_update', fn () => !session()->has('pending_env_update') || session('pending_env_update.attempted', false));
        \Validator::replacer('no_pending_env_update', fn () => 'A system environment update is currently in progress. Please wait for it to complete.');

        \Validator::extend('two_factor_code', fn ($a, $v) => preg_match('/^\d{6}$/', $v));
        \Validator::replacer('two_factor_code', fn ($m, $a) => 'The ' . $a . ' must be a 6-digit number.');

        \Validator::extend('email_or_phone', function ($a, $v) {
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) return true;
            return app(PhoneNormalizationService::class)->isValid($v);
        });
        \Validator::replacer('email_or_phone', fn ($m, $a) => 'The ' . $a . ' must be a valid email address or Ghanaian phone number.');

        \Validator::extend('current_password', fn ($a, $v) => auth()->check() && \Hash::check($v, auth()->user()->password));
        \Validator::replacer('current_password', fn ($m, $a) => 'The ' . $a . ' is incorrect.');

        \Validator::extend('password_history', function ($a, $v) {
            $user = auth()->user();
            if (!$user || !$user->password_history) return true;

            $history = json_decode($user->password_history, true) ?? [];
            foreach ($history as $oldPassword) {
                if (\Hash::check($v, $oldPassword)) return false;
            }
            return true;
        });
        \Validator::replacer('password_history', fn () => 'You cannot reuse your last 5 passwords. Please choose a new password.');

        // ========== ✅ GHANA: LEASE VALIDATION RULES ==========

        \Validator::extend('advance_rent_months', fn ($a, $v) => is_numeric($v) && (int) $v >= 1 && (int) $v <= 60);
        \Validator::replacer('advance_rent_months', fn ($m, $a) => 'The ' . $a . ' must be between 1 and 60 months.');

        \Validator::extend('advance_rent_within_duration', function ($attribute, $value, $parameters, $validator) {
            $durationField = $parameters[0] ?? 'duration_months';
            $duration = (int) $validator->getData()[$durationField] ?? 0;

            if ($duration <= 0) return true;
            return (int) $value <= $duration;
        });
        \Validator::replacer('advance_rent_within_duration', fn ($m, $a) => 'The ' . $a . ' cannot exceed the total lease duration.');

        \Validator::extend('ghana_advance_compliant', function ($attribute, $value, $parameters) {
            $legalMax = isset($parameters[0]) ? (int) $parameters[0] : 6;
            return (int) $value <= $legalMax;
        });
        \Validator::replacer('ghana_advance_compliant', function ($message, $attribute, $rule, $parameters) {
            $legalMax = $parameters[0] ?? 6;
            return "The {$attribute} exceeds the Rent Act 1963 legal maximum of {$legalMax} month(s) "
                 . "for this tenancy type. Confirmation is required if the tenant voluntarily offered it.";
        });

        // ========== ✅ INVOICE: VALIDATION RULES ==========

        \Validator::extend('invoice_type', function ($attribute, $value) {
            if (!class_exists(PropertyUnitInvoice::class)) return true;
            return in_array($value, [
                PropertyUnitInvoice::TYPE_ADVANCE_RENT,
                PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_LATE_FEE,
                PropertyUnitInvoice::TYPE_EARLY_TERMINATION,
                PropertyUnitInvoice::TYPE_OTHER,
            ], true);
        });
        \Validator::replacer('invoice_type', fn ($m, $a) => 'The ' . $a . ' must be a valid invoice type.');

        \Validator::extend('payment_method', fn ($a, $v) => in_array($v, [
            'cash', 'bank_transfer', 'card', 'cheque', 'mobile_money',
        ], true));
        \Validator::replacer('payment_method', fn ($m, $a) => 'The ' . $a . ' must be one of: cash, bank transfer, card, cheque, or mobile money.');

        \Validator::extend('amount_within_invoice_balance', function ($attribute, $value, $parameters) {
            if (!class_exists(PropertyUnitInvoice::class)) return true;
            $invoiceId = $parameters[0] ?? null;
            if (!$invoiceId) return true;

            $invoice = PropertyUnitInvoice::find($invoiceId);
            if (!$invoice) return true;

            $remaining = max(0, $invoice->amount - $invoice->amount_paid);
            return (float) $value <= $remaining + 0.01;
        });
        \Validator::replacer('amount_within_invoice_balance', fn ($m, $a) => 'The ' . $a . ' cannot exceed the invoice\'s remaining balance.');
    }

    /**
     * Register custom macros.
     */
    protected function registerMacros(): void
    {
        \Illuminate\Support\Collection::macro('groupByStatus', function () {
            return $this->groupBy(fn ($item) => $item->status ?? 'unknown');
        });

        \Illuminate\Support\Str::macro('truncateMiddle', function ($text, $length = 30, $separator = '...') {
            if (strlen($text) <= $length) return $text;
            $halflen = floor($length / 2);
            return substr($text, 0, $halflen) . $separator . substr($text, -$halflen);
        });

        \Carbon\Carbon::macro('addBusinessDays', function ($days) {
            $date = $this->copy();
            $addedDays = 0;
            while ($addedDays < $days) {
                $date->addDay();
                if (!$date->isWeekend()) $addedDays++;
            }
            return $date;
        });

        \Illuminate\Routing\ResponseFactory::macro('success', fn ($data = null, $message = 'Success', $status = 200) => response()->json(['success' => true, 'message' => $message, 'data' => $data], $status));
        \Illuminate\Routing\ResponseFactory::macro('error', fn ($message = 'Error', $status = 400, $errors = null) => response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status));
        \Illuminate\Routing\ResponseFactory::macro('freeService', fn ($data = null, $message = 'Free service activated successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'is_free' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('envConfig', fn ($data = null, $message = 'Environment configuration updated successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'env_updated' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('emailConfig', fn ($data = null, $message = 'Email configuration updated successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'email_configured' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('multiChannel', fn ($data = null, $message = 'Multi-channel message sent successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'multi_channel' => true, 'channels_used' => $data['channels_successful'] ?? []], 200));
        \Illuminate\Routing\ResponseFactory::macro('pendingEnvUpdate', fn ($data = null, $message = 'Environment update queued for background processing') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'env_update_queued' => true, 'pending_update' => true], 200));

        \Illuminate\Support\Collection::macro('hasEmailConfig', function () {
            try {
                $config = app(EnvironmentConfigService::class)->getMailConfiguration();
                return !empty($config['username']) && !empty($config['host']);
            } catch (\Exception $e) {
                return false;
            }
        });

        \Illuminate\Support\Collection::macro('hasMultiChannelConfig', function () {
            try {
                $config = app(MultiChannelInvitationService::class)->getServiceConfiguration();
                return !empty($config['sms_service_configured']) || !empty($config['email_service_configured']) || !empty($config['whatsapp_service_configured']);
            } catch (\Exception $e) {
                return false;
            }
        });

        \Illuminate\Support\Collection::macro('hasPendingEnvUpdate', fn () => session()->has('pending_env_update') && !session('pending_env_update.attempted', false));

        \Illuminate\Routing\ResponseFactory::macro('authSuccess', function ($user, $token = null, $message = 'Authentication successful') {
            $response = [
                'success' => true,
                'message' => $message,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'role_name' => $user->getRoleName(),
                    'avatar' => $user->avatar_url,
                    'two_factor_enabled' => $user->two_factor_enabled ?? false,
                ],
            ];
            if ($token) {
                $response['token'] = $token;
                $response['token_type'] = 'Bearer';
            }
            return response()->json($response, 200);
        });

        \Illuminate\Routing\ResponseFactory::macro('twoFactorRequired', fn ($userId, $message = 'Two-factor authentication required') => response()->json(['success' => false, 'message' => $message, 'requires_2fa' => true, 'user_id' => $userId], 401));

        \Illuminate\Http\Request::macro('deviceInfo', function () {
            $agent = new \Jenssegers\Agent\Agent();
            $agent->setUserAgent($this->userAgent());
            return [
                'device' => $agent->device(),
                'platform' => $agent->platform(),
                'browser' => $agent->browser(),
                'is_mobile' => $agent->isMobile(),
                'is_tablet' => $agent->isTablet(),
                'is_desktop' => $agent->isDesktop(),
                'user_agent' => $this->userAgent(),
                'ip' => $this->ip(),
            ];
        });

        \Illuminate\Auth\SessionGuard::macro('canImpersonate', fn () => $this->user() && $this->user()->isSuperAdmin());

        \Illuminate\Auth\SessionGuard::macro('impersonate', function ($user) {
            if (!$this->canImpersonate()) throw new \Exception('Unauthorized to impersonate users.');
            session()->put('impersonate', [
                'original_user_id' => $this->id(),
                'original_user_type' => $this->user()->type,
            ]);
            $this->login($user);
            return true;
        });

        \Illuminate\Auth\SessionGuard::macro('stopImpersonating', function () {
            $impersonateData = session()->get('impersonate');
            if (!$impersonateData) return false;

            $originalUser = User::find($impersonateData['original_user_id']);
            if (!$originalUser) return false;

            $this->login($originalUser);
            session()->forget('impersonate');
            return true;
        });

        \Illuminate\Auth\SessionGuard::macro('isImpersonating', fn () => session()->has('impersonate'));

        \Illuminate\Support\Collection::macro('userActivitySummary', function () {
            return [
                'total' => $this->count(),
                'active_today' => $this->filter(fn ($u) => $u->last_login_at && $u->last_login_at >= now()->subDay())->count(),
                'active_this_week' => $this->filter(fn ($u) => $u->last_login_at && $u->last_login_at >= now()->subWeek())->count(),
                'two_factor_enabled' => $this->filter(fn ($u) => $u->two_factor_enabled ?? false)->count(),
                'never_logged_in' => $this->filter(fn ($u) => !$u->last_login_at)->count(),
            ];
        });

        \Illuminate\Routing\ResponseFactory::macro('themeSuccess', fn ($data = null, $message = 'Theme updated successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'theme_updated' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('themeError', fn ($message = 'Theme update failed', $errors = null) => response()->json(['success' => false, 'message' => $message, 'errors' => $errors, 'theme_updated' => false], 422));

        // ========== ✅ INVOICE: MACROS ==========

        \Illuminate\Routing\ResponseFactory::macro('invoiceSuccess', fn ($data = null, $message = 'Invoice processed successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'invoice' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('invoiceError', fn ($message = 'Invoice operation failed', $errors = null) => response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422));

        // ========== ✅ GHANA: MACROS ==========

        \Illuminate\Routing\ResponseFactory::macro('leaseSuccess', fn ($data = null, $message = 'Lease processed successfully') => response()->json(['success' => true, 'message' => $message, 'data' => $data, 'lease' => true], 200));
        \Illuminate\Routing\ResponseFactory::macro('leaseError', fn ($message = 'Lease operation failed', $errors = null) => response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422));

        // ========== ✅ BILLING: MACROS ==========

        \Illuminate\Routing\ResponseFactory::macro('billingRestricted', fn ($message = 'Action blocked: system billing is overdue.', $data = null) => response()->json([
            'success'          => false,
            'message'          => $message,
            'billing_restricted' => true,
            'data'             => $data,
        ], 402));

        \Illuminate\Routing\ResponseFactory::macro('billingReadOnly', fn ($message = 'Read-only mode: system billing is overdue.', $data = null) => response()->json([
            'success'         => false,
            'message'         => $message,
            'billing_read_only' => true,
            'data'            => $data,
        ], 403));

        // ========== ✅ NEW: DEVELOPER MACROS ==========

        \Illuminate\Routing\ResponseFactory::macro('developerSuccess', fn ($data = null, $message = 'Developer payment configuration saved') => response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'developer_payment' => true,
        ], 200));

        \Illuminate\Routing\ResponseFactory::macro('developerError', fn ($message = 'Developer payment operation failed', $errors = null, $status = 422) => response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'developer_payment' => true,
        ], $status));
    }

    /**
     * Register custom configuration.
     */
    protected function registerCustomConfiguration(): void
    {
        config(['services.env_config' => [
            'enabled' => true,
            'auto_update' => env('ENV_AUTO_UPDATE', true),
            'backup_enabled' => env('ENV_BACKUP_ENABLED', true),
            'allowed_keys' => ['MAIL_USERNAME', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION'],
            'background_update' => ['enabled' => true, 'delay_seconds' => 5, 'max_attempts' => 1, 'auto_retry' => false],
        ]]);

        config([
            'app.invitation_expiry_days' => env('INVITATION_EXPIRY_DAYS', 7),
            'app.invitation_warning_days' => env('INVITATION_WARNING_DAYS', 2),
            'app.invitation_auto_expiry' => env('INVITATION_AUTO_EXPIRY', true),
            'app.invitation_resend_extends_expiry' => env('INVITATION_RESEND_EXTENDS_EXPIRY', true),
        ]);

        config(['services.multi_channel' => [
            'default_channel' => 'sms',
            'fallback_enabled' => true,
            'channels' => [
                'sms' => ['enabled' => true, 'priority' => 1, 'fallback_to' => null],
                'whatsapp' => ['enabled' => true, 'priority' => 2, 'fallback_to' => 'sms'],
                'email' => ['enabled' => true, 'priority' => 3, 'fallback_to' => 'sms'],
            ],
            'analytics_enabled' => true,
            'delivery_receipts' => true,
        ]]);

        config(['app.system_defaults' => [
            'currency' => 'GHS',
            'timezone' => 'Africa/Accra',
            'locale' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
        ]]);

        config(['app.env_update' => [
            'queue_enabled' => config('queue.default') !== 'sync',
            'session_fallback' => true,
            'auto_process' => true,
            'processing_delay' => 5,
        ]]);

        config(['auth' => array_merge(config('auth', []), [
            'lockout' => ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 1],
            'password_timeout' => 90,
            'two_factor' => ['enabled' => true, 'code_length' => 6, 'code_validity' => 10, 'max_attempts' => 5, 'backup_codes_count' => 8],
            'session' => ['single_session' => env('SINGLE_SESSION', false), 'session_lifetime' => env('SESSION_LIFETIME', 120)],
        ])]);

        config(['services' => array_merge(config('services', []), [
            'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'), 'scopes' => ['openid', 'profile', 'email']],
            'microsoft' => ['client_id' => env('MICROSOFT_CLIENT_ID'), 'client_secret' => env('MICROSOFT_CLIENT_SECRET'), 'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/auth/microsoft/callback'), 'scopes' => ['openid', 'profile', 'email', 'User.Read']],
            'facebook' => ['client_id' => env('FACEBOOK_CLIENT_ID'), 'client_secret' => env('FACEBOOK_CLIENT_SECRET'), 'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL') . '/auth/facebook/callback'), 'scopes' => ['email', 'public_profile']],
            'apple' => ['client_id' => env('APPLE_CLIENT_ID'), 'client_secret' => env('APPLE_CLIENT_SECRET'), 'redirect' => env('APPLE_REDIRECT_URI', env('APP_URL') . '/auth/apple/callback')],
            'github' => ['client_id' => env('GITHUB_CLIENT_ID'), 'client_secret' => env('GITHUB_CLIENT_SECRET'), 'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/auth/github/callback')],
        ])]);

        config(['login_activity' => [
            'enabled' => true,
            'retention_days' => 90,
            'log_failed_attempts' => true,
            'log_suspicious_activity' => true,
            'ip_geolocation' => env('IP_GEOLOCATION_ENABLED', true),
            'alert_on_suspicious' => true,
        ]]);

        // ========== ✅ GHANA: LEASE COMPLIANCE CONFIGURATION ==========
        config(['leases' => [
            'ghana' => [
                'legal_max_advance_months' => [
                    'new_tenancy'    => 6,
                    'renewal'        => 3,
                    'short_tenancy'  => 1,
                ],
                'currency'          => 'GHS',
                'governing_law'     => 'Rent Act, 1963 (Act 220)',
                'enforce_compliance'=> env('GHANA_ENFORCE_ADVANCE_COMPLIANCE', false),
            ],
            'defaults' => [
                'payment_due_day'         => 5,
                'grace_period_days'       => 0,
                'notice_period_days'      => 30,
                'deposit_payment_method'  => 'upfront',
            ],
            'invoice' => [
                'auto_generate_on_lease_create' => env('INVOICE_AUTO_GENERATE', true),
                'void_future_on_terminate'      => env('INVOICE_VOID_FUTURE_ON_TERMINATE', true),
                'mark_overdue_after_days'       => env('INVOICE_OVERDUE_DAYS', 1),
            ],
        ]]);

        // 🎨 THEME CONFIGURATION
        config(['theme' => array_replace_recursive(
            [
                'options' => [
                    'appearance'       => ['light', 'dark', 'system'],
                    'sidebar_themes'   => ['default', 'dark', 'light', 'blue', 'green', 'custom'],
                    'font_sizes'       => ['small', 'medium', 'large'],
                    'layout'           => ['compact', 'comfortable'],
                    'animations'       => ['enabled', 'disabled'],
                    'sidebar_position' => ['left', 'right'],
                    'header_style'     => ['default', 'glass', 'solid'],
                    'density'          => ['comfortable', 'compact', 'spacious'],
                ],
                'defaults' => [
                    'appearance'       => env('THEME_DEFAULT_APPEARANCE', 'system'),
                    'sidebar_theme'    => env('THEME_DEFAULT_SIDEBAR_THEME', 'default'),
                    'font_size'        => env('THEME_DEFAULT_FONT_SIZE', 'medium'),
                    'layout'           => env('THEME_DEFAULT_LAYOUT', 'comfortable'),
                    'animations'       => env('THEME_DEFAULT_ANIMATIONS', 'enabled'),
                    'sidebar_position' => env('THEME_DEFAULT_SIDEBAR_POSITION', 'left'),
                    'header_style'     => env('THEME_DEFAULT_HEADER_STYLE', 'default'),
                    'density'          => env('THEME_DEFAULT_DENSITY', 'comfortable'),
                ],
                'cache' => [
                    'enabled' => env('THEME_CACHE_ENABLED', true),
                    'ttl'     => env('THEME_CACHE_TTL', 3600),
                    'prefix'  => env('THEME_CACHE_PREFIX', 'theme_'),
                ],
            ],
            config('theme', [])
        )]);

        // ========== ✅ BILLING: SYSTEM BILLING CONFIGURATION ==========
        config(['billing' => [
            'state_cache_ttl' => env('BILLING_STATE_CACHE_TTL', 300),
            'default_grace_period_days' => env('BILLING_DEFAULT_GRACE_DAYS', 7),
            'due_soon_threshold_days' => env('BILLING_DUE_SOON_DAYS', 5),
            'default_reminder_days' => [7, 3, 1],
            'payment_dashboard_route' => 'superadmin.billing.dashboard',
            'suspension_route' => 'billing.suspended',
            'default_hard_lock_landlords' => false,
            'online_providers' => ['paystack', 'expresspay', 'flutterwave', 'hubtel'],
            'exempt_roles' => ['developer'],

            // ✅ NEW: Whether landlords are allowed to manage payment
            //          provider credentials. Off by default because
            //          gateway credentials are system-wide, not
            //          per-landlord. Enable via .env if your deployment
            //          gives each landlord their own gateway account.
            'allow_landlord_payment_management' => env('BILLING_ALLOW_LANDLORD_PAYMENT_MGMT', false),
        ]]);

        // ========== ✅ NEW: DEVELOPER PAYMENT CONFIGURATION ==========
        //
        // Centralized flags for the developer payment controller. These
        // are read by the `manage-developer-payments` gate and the
        // developer blade, replacing scattered `env(...)` calls.
        //
        config(['developer_payments' => [
            // Master switch — when false, the developer payment area
            // becomes read-only (view still works, writes are blocked).
            'access_enabled' => env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true),

            // Whether developers may call the billAdmin endpoint.
            'can_bill' => env('DEVELOPER_PAYMENT_CAN_BILL', true),

            // Whether super admins may also configure developer credentials.
            // Default true so they can help debug a broken setup.
            'allow_super_admin_configure' => env('DEVELOPER_ALLOW_SA_CONFIGURE', true),

            // Storage backend for developer credentials. Today: 'env'.
            // In the future: 'database' — the controller already supports
            // both shapes at the read layer.
            'storage' => env('DEVELOPER_PAYMENT_STORAGE', 'env'),
        ]]);
    }

    /**
     * Register authentication event listeners.
     */
    protected function registerAuthenticationEvents(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $user = $event->user;
            if (!$user->last_login_at) {
                $user->last_login_at = now();
                $user->save();
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if (session()->has('2fa:user_id')) {
                session()->forget('2fa:user_id');
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            if (app()->bound(LoginActivityService::class)) {
                app(LoginActivityService::class)->recordFailedLogin($event->user, request(), 'invalid_credentials');
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\PasswordReset::class, function ($event) {
            $user = $event->user;
            if (app()->bound(LoginActivityService::class)) {
                app(LoginActivityService::class)->recordPasswordChange($user, request());
            }
            $user->two_factor_enabled = false;
            $user->save();
        });

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BillingPaymentRecorded::class,
            function ($event) {
                DeveloperSetting::forgetCurrent();
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BillingAgreementSigned::class,
            function ($event) {
                DeveloperSetting::forgetCurrent();
            }
        );
    }

    /**
     * Register 2FA routes macro.
     */
    protected function registerTwoFactorRoutes(): void
    {
        \Illuminate\Support\Facades\Route::macro('twoFactorAuth', function () {
            \Illuminate\Support\Facades\Route::group(['middleware' => ['web']], function () {
                \Illuminate\Support\Facades\Route::get('/2fa/verify', [\App\Http\Controllers\Auth\LoginController::class, 'showTwoFactorForm'])->name('2fa.verify');
                \Illuminate\Support\Facades\Route::post('/2fa/verify', [\App\Http\Controllers\Auth\LoginController::class, 'verifyTwoFactor'])->name('2fa.verify.submit');
                \Illuminate\Support\Facades\Route::post('/2fa/resend', [\App\Http\Controllers\Auth\LoginController::class, 'resendTwoFactorCode'])->name('2fa.resend');
            });

            \Illuminate\Support\Facades\Route::group(['middleware' => ['web', 'auth']], function () {
                \Illuminate\Support\Facades\Route::post('/2fa/enable', [\App\Http\Controllers\Auth\LoginController::class, 'enableTwoFactor'])->name('2fa.enable');
                \Illuminate\Support\Facades\Route::post('/2fa/disable', [\App\Http\Controllers\Auth\LoginController::class, 'disableTwoFactor'])->name('2fa.disable');
            });
        });
    }

    /**
     * Configure authentication defaults.
     */
    protected function configureAuthentication(): void
    {
        // Intentionally left minimal. Behavior is enforced by middleware.
    }
}