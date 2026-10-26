<?php

namespace App\Providers;

use App\Contracts\Theme\ThemeRepositoryInterface;
use App\Contracts\Theme\ThemeServiceInterface;
use App\Models\AdminBillingRecord;
use App\Models\DeveloperSetting;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyUnitInvoice;
use App\Models\User;
use App\Observers\AdminBillingRecordObserver;
use App\Observers\PropertyUnitInvoiceObserver;
use App\Policies\PropertyOwnershipTransferPolicy;
use App\Policies\PropertyPolicy;
use App\Repositories\Theme\ThemeCacheRepository;
use App\Repositories\Theme\ThemeRepository;
use App\Services\AgentInvitationService;
use App\Services\Authentication\ArchivedAccountService;
use App\Services\Authentication\LoginActivityService;
use App\Services\Authentication\LoginAttemptService;
use App\Services\Authentication\PasswordResetService;
use App\Services\Authentication\PhoneNormalizationService;
use App\Services\Authentication\RoleRedirectionService;
use App\Services\Authentication\SocialLoginService;
use App\Services\Authentication\TwoFactorAuthService;
use App\Services\BillingAccessService;
use App\Services\EmailService;
use App\Services\EnvironmentConfigService;
use App\Services\MultiChannelInvitationService;
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use App\Services\Theme\Support\SidebarThemeRegistry;
use App\Services\Theme\ThemeCssGenerator;
use App\Services\Theme\ThemeService;
use App\Services\WhatsAppService;
use App\View\Composers\SidebarComposer;
use App\View\Composers\ThemeViewComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\SessionGuard;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /* ============================================================
     | CONSTANTS
     * ============================================================ */

    public const LOGIN_BILLING_STATE_DEFAULT = [
        'state'   => null,
        'overdue' => false,
        'pending' => false,
    ];

    /**
     * Roles that grant the head-of-security / supervisor experience
     * on the `security.*` routes.
     */
    public const SUPERVISOR_USER_TYPES = [
        User::TYPE_SUPER_ADMIN, // 0
        User::TYPE_ADMIN,       // 1
    ];

    public static function buildLoginBillingState(?string $state): array
    {
        return [
            'state'   => $state,
            'overdue' => in_array($state, ['active_overdue', 'active_suspended'], true),
            'pending' => $state === 'pending_signature',
        ];
    }

    /* ============================================================
     | REGISTER — container bindings
     * ============================================================ */

    public function register(): void
    {
        $this->registerCommunicationServices();
        $this->registerBillingServices();
        $this->registerAuthenticationServices();
        $this->registerThemeServices();

        // Blade directives are registered in boot() — register() runs
        // before config is fully loaded in some setups, and directives
        // reference config values. Do not call registerBladeDirectives()
        // here to avoid double-registration.
    }

    private function registerCommunicationServices(): void
    {
        $this->app->singleton(SmsService::class, fn () => new SmsService());
        $this->app->singleton(EmailService::class, fn () => new EmailService());
        $this->app->singleton(WhatsAppService::class, fn () => new WhatsAppService());
        $this->app->singleton(SmsTemplateService::class, fn () => new SmsTemplateService());
        $this->app->singleton(EnvironmentConfigService::class, fn () => new EnvironmentConfigService());
        $this->app->singleton(PaymentService::class, fn () => new PaymentService());

        $this->app->singleton(MultiChannelInvitationService::class, fn ($app) =>
            new MultiChannelInvitationService(
                $app->make(SmsService::class),
                $app->make(EmailService::class),
                $app->make(WhatsAppService::class),
                $app->make(SmsTemplateService::class)
            )
        );

        $this->app->singleton(AgentInvitationService::class, fn ($app) =>
            new AgentInvitationService(
                $app->make(SmsService::class),
                $app->make(MultiChannelInvitationService::class),
                $app->make(EmailService::class),
                $app->make(WhatsAppService::class),
                $app->make(SmsTemplateService::class)
            )
        );
    }

    private function registerBillingServices(): void
    {
        $this->app->singleton(BillingAccessService::class, fn () => new BillingAccessService());
    }

    private function registerAuthenticationServices(): void
    {
        $this->app->singleton(PhoneNormalizationService::class, fn () => new PhoneNormalizationService());
        $this->app->singleton(RoleRedirectionService::class, fn () => new RoleRedirectionService());
        $this->app->singleton(ArchivedAccountService::class, fn () => new ArchivedAccountService());
        $this->app->singleton(LoginAttemptService::class, fn () => new LoginAttemptService());
        $this->app->singleton(SocialLoginService::class, fn () => new SocialLoginService());
        $this->app->singleton(PasswordResetService::class, fn () => new PasswordResetService());
        $this->app->singleton(TwoFactorAuthService::class, fn () => new TwoFactorAuthService());
        $this->app->singleton(LoginActivityService::class, fn () => new LoginActivityService());
    }

    private function registerThemeServices(): void
    {
        $this->app->singleton(SidebarThemeRegistry::class);
        $this->app->singleton(ThemeCssGenerator::class);
        $this->app->singleton(ThemeCacheRepository::class);

        $this->app->singleton(ThemeRepository::class);
        $this->app->alias(ThemeRepository::class, ThemeRepositoryInterface::class);

        $this->app->singleton(ThemeService::class);
        $this->app->alias(ThemeService::class, ThemeServiceInterface::class);
    }

    /* ============================================================
     | BOOT
     * ============================================================ */

    public function boot(): void
    {
        $this->forceHttpsInProduction();
        $this->configureDatabaseDefaults();

        $this->registerPolicies();
        $this->registerCustomGates();
        $this->registerObservers();

        $this->registerBladeDirectives();
        $this->registerViewComposers();
        $this->registerValidationRules();
        $this->registerMacros();

        $this->registerCustomConfiguration();
        $this->registerAuthenticationEvents();
        $this->registerTwoFactorRoutes();

        // Boot-time canaries and one-off setup
        $this->warnIfStrictModeIsOff();
        $this->autoCheckPendingEnvUpdates();
        $this->shareBillingContext();
    }

    private function forceHttpsInProduction(): void
    {
        $appEnv   = config('app.env');
        $appUrl   = config('app.url');
        $assetUrl = config('app.asset_url');

        if (in_array($appEnv, ['production', 'railway'], true)
            || str_starts_with((string) $appUrl, 'https://')) {
            URL::forceScheme('https');
            $this->app['url']->forceRootUrl($appUrl);

            if ($assetUrl) {
                config(['app.asset_url' => $assetUrl]);
                URL::forceRootUrl($assetUrl);
            } else {
                config(['app.asset_url' => $appUrl]);
            }
        }
    }

    private function configureDatabaseDefaults(): void
    {
        Schema::defaultStringLength(191);
    }

    /* ============================================================
     | POLICIES & GATES
     * ============================================================ */

    private function registerPolicies(): void
    {
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(PropertyOwnershipTransfer::class, PropertyOwnershipTransferPolicy::class);
    }

    private function registerCustomGates(): void
    {
        $this->registerPropertyGates();
        $this->registerAuthGates();
        $this->registerInvoiceGates();
        $this->registerPaymentGates();
        $this->registerBillingGates();
    }

    private function registerPropertyGates(): void
    {
        Gate::define('transfer-ownership', function ($user, $property) {
            if (!$user->isLandlord()) return false;
            if ($user->id !== $property->landlord_id) return false;

            $pending = $property->currentOwnershipTransfer;
            if ($pending && $pending->status === PropertyOwnershipTransfer::STATUS_PENDING) {
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
    }

    private function registerAuthGates(): void
    {
        Gate::define('manage-2fa', fn ($user) => Auth::check() && $user->id === Auth::id());

        Gate::define('view-login-activity', function ($user, $targetUser = null) {
            if ($targetUser && $user->id === $targetUser->id) return true;
            return $user->isAdmin() || $user->isSuperAdmin();
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

        Gate::define('manage-developer', function ($user) {
            return in_array((int) $user->type, [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_DEVELOPER,
            ], true);
        });

        // ===== NEW: schedule-management capability gate =====
        // Single source of truth used by controllers, policies, Blade,
        // and the sidebar. Keep this in sync with
        // SecurityScheduleController::canManageSchedules().
        Gate::define('manage-security-schedules', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;

            if (in_array((int) $user->type, self::SUPERVISOR_USER_TYPES, true)) {
                return true;
            }
            if (method_exists($user, 'isSupervisor') && $user->isSupervisor()) {
                return true;
            }
            if (method_exists($user, 'hasRole') && $user->hasRole('head_of_security')) {
                return true;
            }
            return false;
        });
    }

    private function registerInvoiceGates(): void
    {
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
                ], true);
            }
            return false;
        });

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
    }

    private function registerPaymentGates(): void
    {
        Gate::define('manage-payments', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;

            if ($user->isSuperAdmin() || $user->isAdmin()) return true;

            if (config('billing.allow_landlord_payment_management', false)
                && ($user->isLandlord() || $user->hasRole('landlord'))) {
                return true;
            }

            return false;
        });

        Gate::define('manage-payment-gateways', fn ($user = null) =>
            Gate::forUser($user ?: Auth::user())->allows('manage-payments')
        );

        Gate::define('manage-developer-payments', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;

            if ($user->hasRole('developer') || $user->type == User::TYPE_DEVELOPER) {
                return true;
            }

            if ($user->isSuperAdmin()) {
                return (bool) config('developer_payments.allow_super_admin_configure', true);
            }

            return false;
        });

        Gate::define('manage-developer-billing', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;

            if (!($user->hasRole('developer') || $user->type == User::TYPE_DEVELOPER)) {
                return false;
            }

            return (bool) config('developer_payments.can_bill', true);
        });
    }

    private function registerBillingGates(): void
    {
        Gate::define('billing.restricted', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;

            $svc = app(BillingAccessService::class);
            return $svc->shouldRestrictSuperAdmin($user)
                || $svc->shouldRestrictAdminToReadOnly($user);
        });

        Gate::define('billing.read-only', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;
            return app(BillingAccessService::class)->shouldRestrictAdminToReadOnly($user);
        });

        Gate::define('billing.hard-locked', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;
            return app(BillingAccessService::class)->shouldHardLockLandlordOrTenant($user);
        });

        Gate::define('perform-billing-sensitive-action', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user) return false;
            return app(BillingAccessService::class)->canPerformWrite($user);
        });

        Gate::define('is-primary-billing-contact', function ($user = null) {
            $user = $user ?: Auth::user();
            if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) return false;

            $developer = DeveloperSetting::current();
            if (!$developer) return false;

            return AdminBillingRecord::where('developer_setting_id', $developer->id)
                ->where('super_admin_id', $user->id)
                ->where('is_primary_for_billing', true)
                ->exists();
        });

        Gate::define('can-pay-system-billing', function ($user = null) {
            $user = $user ?: Auth::user();
            return $user && $user->type === User::TYPE_SUPER_ADMIN;
        });
    }

    /* ============================================================
     | OBSERVERS
     * ============================================================ */

    private function registerObservers(): void
    {
        PropertyUnitInvoice::observe(PropertyUnitInvoiceObserver::class);
        AdminBillingRecord::observe(AdminBillingRecordObserver::class);
    }

    /* ============================================================
     | STRICT-MODE CANARY
     * ============================================================ */

    private function warnIfStrictModeIsOff(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        if (config('database.default') !== 'mysql') {
            return;
        }

        try {
            $mode = DB::selectOne('SELECT @@sql_mode AS mode')?->mode ?? '';

            if (!str_contains($mode, 'STRICT_TRANS_TABLES')) {
                Log::warning('[DB] MySQL strict mode is OFF. Silent data coercion is possible.', [
                    'sql_mode'   => $mode,
                    'connection' => config('database.default'),
                    'hint'       => 'Set sql_mode=STRICT_TRANS_TABLES,... in my.ini and '
                                  . "'strict' => true in config/database.php.",
                ]);
            }
        } catch (\Throwable $e) {
            // DB not reachable yet — silently skip.
        }
    }

    /* ============================================================
     | PENDING ENV UPDATES
     * ============================================================ */

    private function autoCheckPendingEnvUpdates(): void
    {
        if (app()->runningInConsole() || !app()->request->is('admin/*')) {
            return;
        }

        try {
            if (session()->has('pending_env_update')
                && !session('pending_env_update.attempted', false)) {

                if (session('pending_env_update.timestamp', 0) < now()->subSeconds(5)->timestamp) {
                    $this->processPendingEnvUpdate();
                }
            }
        } catch (\Throwable $e) {
            // Silent
        }
    }

    private function processPendingEnvUpdate(): void
    {
        try {
            $pendingUpdate = session()->get('pending_env_update');
            session()->put('pending_env_update.attempted', true);
            session()->save();

            $service = app(EnvironmentConfigService::class);
            $result  = $service->updateEmailConfiguration(
                $pendingUpdate['email'],
                $pendingUpdate['password']
            );

            if ($result['success']) {
                session()->forget('pending_env_update');
                session()->save();

                session()->flash('env_update_complete', true);
                session()->flash('env_update_message', 'Email configuration updated in background.');
            } else {
                session()->flash('env_update_warning', true);
                session()->flash('env_update_message', 'Background update failed: ' . $result['message']);
            }
        } catch (\Throwable $e) {
            session()->flash('env_update_error', true);
            session()->flash('env_update_message', 'Background update error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | BILLING CONTEXT
     * ============================================================ */

    private function shareBillingContext(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $billing = app(BillingAccessService::class);
            $payload = $billing->bannerPayload();

            View::share('billingBanner', $payload);

            if (!empty($payload['visible'])) {
                View::share('billingState', $payload['state']);
            }

            $user = Auth::user();

            if ($user) {
                View::share('billingReadOnly', $billing->shouldRestrictAdminToReadOnly($user));
                View::share('systemBillingNotice', $billing->shouldRestrictSuperAdmin($user)
                    && in_array($payload['state'] ?? null, ['active_due_soon', 'active_overdue', 'active_suspended'], true));
            } else {
                View::share('billingReadOnly', false);
                View::share('systemBillingNotice', false);
            }
        } catch (\Throwable $e) {
            Log::warning('[Billing] shareBillingContext failed', ['error' => $e->getMessage()]);

            View::share('billingBanner', ['visible' => false]);
            View::share('billingReadOnly', false);
            View::share('systemBillingNotice', false);
        }
    }

    /* ============================================================
     | SECURITY CAPABILITIES (single source of truth)
     * ============================================================ */

    /**
     * Resolve the schedule-management capability for the given (or
     * current) user. Shared with every security.* and layouts.* view
     * so the sidebar can decide between "/security/dashboard" (head of
     * security) and "/security/my-schedules" (ordinary guard) without
     * duplicating the role logic in Blade.
     *
     * Controllers can also call this via
     *   app(AppServiceProvider::class)->resolveSecurityCapabilities()
     * or (simpler) mirror it in SecurityScheduleController::canManageSchedules()
     * — just keep the two in sync.
     */
    public static function resolveSecurityCapabilities(?User $user = null): array
    {
        $user = $user ?: Auth::user();

        $canManage = false;
        if ($user) {
            $canManage = in_array((int) $user->type, self::SUPERVISOR_USER_TYPES, true)
                || (method_exists($user, 'isSupervisor') && $user->isSupervisor())
                || (method_exists($user, 'hasRole') && $user->hasRole('head_of_security'));
        }

        return [
            'canManage'    => $canManage,
            'isSupervisor' => $canManage, // backwards-compat alias for existing blades
            'isGuard'      => !$canManage,
        ];
    }

    /* ============================================================
     | BLADE DIRECTIVES
     * ============================================================ */

    private function registerBladeDirectives(): void
    {
        $this->registerFormattingDirectives();
        $this->registerStatusDirectives();
        $this->registerDomainDirectives();
    }

    private function registerFormattingDirectives(): void
    {
        Blade::directive('money',    fn ($e) => "<?php echo 'GH₵ ' . number_format($e, 2); ?>");
        Blade::directive('date',     fn ($e) => "<?php echo ($e) ? ($e)->format('M j, Y') : 'N/A'; ?>");
        Blade::directive('datetime', fn ($e) => "<?php echo ($e) ? ($e)->format('M j, Y g:i A') : 'N/A'; ?>");
    }

    private function registerStatusDirectives(): void
    {
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
                    'revoked' => 'badge bg-dark',
                ];
                \$class = \$classes[\$status] ?? 'badge bg-secondary';
                echo '<span class=\"' . \$class . '\">' . ucfirst(str_replace('_', ' ', \$status)) . '</span>';
            ?>";
        });

        Blade::directive('userrole', function ($expression) {
            return "<?php
                \$type = $expression;
                \$roles = [
                    0 => 'Super Admin', 1 => 'Admin', 2 => 'Landlord',
                    3 => 'Tenant', 4 => 'Field Agent', 5 => 'Developer',
                    6 => 'Security Checkpoint',
                ];
                echo \$roles[\$type] ?? 'Unknown Role';
            ?>";
        });

        Blade::directive('freebadge', fn () => "<?php echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Free</span>'; ?>");

        Blade::directive('envstatus', function ($expression) {
            return "<?php
                \$config = $expression;
                if (\$config && !empty(\$config['configured'])) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Configured</span>';
                } else {
                    echo '<span class=\"badge bg-warning\"><i class=\"fas fa-exclamation-triangle\"></i> Needs Setup</span>';
                }
            ?>";
        });

        Blade::directive('emailstatus', function ($expression) {
            return "<?php
                \$emailConfig = $expression;
                if (\$emailConfig && !empty(\$emailConfig['can_send_emails'])) {
                    echo '<span class=\"badge bg-success\"><i class=\"fas fa-check-circle\"></i> Ready</span>';
                } else {
                    echo '<span class=\"badge bg-danger\"><i class=\"fas fa-times-circle\"></i> Not Ready</span>';
                }
            ?>";
        });

        Blade::directive('paymentstatus', function ($expression) {
            return "<?php
                \$paymentConfig = $expression;
                if (\$paymentConfig && !empty(\$paymentConfig['recipient_configured']) && !empty(\$paymentConfig['methods_available'])) {
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
                    'both' => ['badge bg-warning', 'Multiple Channels'],
                ];
                \$status = \$statuses[\$channel] ?? ['badge bg-secondary', 'Unknown'];
                echo '<span class=\"' . \$status[0] . '\">' . \$status[1] . '</span>';
            ?>";
        });

        Blade::directive('envupdatestatus', function ($expression) {
            return "<?php
                \$status = $expression;
                \$map = [
                    'processing' => ['badge bg-info', 'fa-sync-alt fa-spin', 'Processing'],
                    'success'    => ['badge bg-success', 'fa-check-circle', 'Completed'],
                    'warning'    => ['badge bg-warning', 'fa-exclamation-triangle', 'Needs Attention'],
                    'error'      => ['badge bg-danger', 'fa-times-circle', 'Failed'],
                ];
                [\$cls, \$icon, \$label] = \$map[\$status] ?? ['badge bg-secondary', 'fa-clock', 'Pending'];
                echo '<span class=\"' . \$cls . '\"><i class=\"fas ' . \$icon . '\"></i> ' . \$label . '</span>';
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
                if (\$location && !empty(\$location['country'])) {
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
                echo '<i class=\"fas ' . (\$icons[\$appearance] ?? 'fa-sun') . '\"></i> ' . ucfirst(\$appearance);
            ?>";
        });

        Blade::directive('sidebartheme', function ($expression) {
            return "<?php
                \$theme = $expression;
                \$colors = [
                    'default' => ['linear-gradient(135deg, #7267f0, #6258e0)', '#ffffff'],
                    'dark'    => ['linear-gradient(135deg, #1a1a2e, #16213e)', '#ecf0f1'],
                    'light'   => ['linear-gradient(135deg, #ffffff, #f0f0f0)', '#4a5568'],
                    'blue'    => ['linear-gradient(135deg, #1e3a5f, #1a56db)', '#ffffff'],
                    'green'   => ['linear-gradient(135deg, #065f46, #059669)', '#ffffff'],
                    'custom'  => ['linear-gradient(135deg, #7267f0, #6258e0)', '#ffffff'],
                ];
                [\$bg, \$text] = \$colors[\$theme] ?? \$colors['default'];
                echo '<div class=\"w-8 h-8 rounded-lg\" style=\"background: ' . \$bg . '; color: ' . \$text . ';\"></div>';
            ?>";
        });
    }

    private function registerDomainDirectives(): void
    {
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

        Blade::directive('invoicestatus', function ($expression) {
            return "<?php
                \$invoice = $expression;
                \$status = \$invoice->status ?? 'pending';
                \$map = [
                    'pending' => ['bg-warning', 'Pending', 'fa-clock'],
                    'partial' => ['bg-info', 'Partial', 'fa-adjust'],
                    'paid'    => ['bg-success', 'Paid', 'fa-check-circle'],
                    'overdue' => ['bg-danger', 'Overdue', 'fa-exclamation-triangle'],
                    'void'    => ['bg-secondary', 'Void', 'fa-ban'],
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
                    'advance_rent'      => ['bg-primary', 'Advance Rent', 'fa-calendar-check'],
                    'monthly_rent'      => ['bg-info', 'Monthly Rent', 'fa-calendar-day'],
                    'security_deposit'  => ['bg-secondary', 'Security Deposit', 'fa-shield-alt'],
                    'utility_deposit'   => ['bg-secondary', 'Utility Deposit', 'fa-bolt'],
                    'late_fee'          => ['bg-warning', 'Late Fee', 'fa-clock'],
                    'early_termination' => ['bg-danger', 'Early Termination', 'fa-times-circle'],
                    'other'             => ['bg-light', 'Other', 'fa-file-invoice'],
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

        Blade::directive('billingstate', function () {
            return "<?php
                \$__bs = \$billingBanner['state'] ?? null;
                \$__map = [
                    'not_configured'    => ['bg-secondary', 'Not Configured', 'fa-circle'],
                    'pending_signature' => ['bg-warning', 'Awaiting Signature', 'fa-signature'],
                    'active_current'    => ['bg-success', 'Current', 'fa-check-circle'],
                    'active_due_soon'   => ['bg-info', 'Due Soon', 'fa-clock'],
                    'active_overdue'    => ['bg-danger', 'Overdue', 'fa-exclamation-triangle'],
                    'active_suspended'  => ['bg-dark', 'Suspended', 'fa-ban'],
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

        Blade::if('billingwriteallowed', fn () => app(BillingAccessService::class)->canPerformWrite());

        Blade::if('billingprimarycontact', function () {
            $user = Auth::user();
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

        Blade::if('developeraccess', fn () =>
            Auth::check() && Auth::user()->can('manage-developer-payments')
        );

        Blade::if('developercanbill', fn () =>
            Auth::check() && Auth::user()->can('manage-developer-billing')
        );

        // ===== NEW: role-aware Blade helper =====
        // @canmanageschedules ... @endcanmanageschedules
        // Use this in the sidebar instead of relying on a controller-supplied
        // variable, so the sidebar is correct on every page including
        // error pages and 403s.
        Blade::if('canmanageschedules', fn () =>
            Auth::check() && Gate::forUser(Auth::user())->allows('manage-security-schedules')
        );
    }

    /* ============================================================
     | VIEW COMPOSERS
     * ============================================================ */

    private function registerViewComposers(): void
    {
        $this->registerCoreComposers();
        $this->registerSecurityComposers();
        $this->registerCommunicationComposers();
        $this->registerInvoiceComposers();
        $this->registerThemeComposers();
        $this->registerAuthComposers();
        $this->registerSidebarComposer();
    }

    private function registerCoreComposers(): void
    {
        View::composer('*', fn ($view) => $view->with('currentUser', Auth::user()));
    }

    /**
     * Share $canManage / $isSupervisor / $isGuard with every security.*
     * and layouts.* view. This is what makes the sidebar fix work on
     * every page without the controller having to compact it into every
     * view.
     */
    private function registerSecurityComposers(): void
    {
        View::composer(['security.*', 'layouts.*'], function ($view) {
            $caps = self::resolveSecurityCapabilities();

            $view->with('canManage', $caps['canManage']);
            // Backwards-compat alias — some blades still reference $isSupervisor.
            $view->with('isSupervisor', $caps['isSupervisor']);
            $view->with('isGuard', $caps['isGuard']);
        });
    }

    private function registerCommunicationComposers(): void
    {
        View::composer(['admin.*', 'superadmin.*'], function ($view) {
            if (app()->bound(SmsService::class)) {
                $view->with('smsStatus', app(SmsService::class)->getSystemStatus());
            }

            if (app()->bound(MultiChannelInvitationService::class)) {
                try {
                    $view->with('multiChannelStatus', app(MultiChannelInvitationService::class)->getServiceConfiguration());
                } catch (\Throwable $e) {
                    $view->with('multiChannelStatus', []);
                }
            }
        });

        View::composer(['admin.registration-plans.*', 'superadmin.registration-plans.*'], function ($view) {
            if (app()->bound(AgentInvitationService::class)) {
                $view->with('invitationStats', app(AgentInvitationService::class)->getInvitationStatistics());
            }
        });

        View::composer(['field-agent.*', 'admin.registration-plans.*'],
            fn ($view) => $view->with('isFreeService', true)
        );

        View::composer(['admin.system-settings.*', 'superadmin.system-settings.*'], function ($view) {
            if (app()->bound(EnvironmentConfigService::class)) {
                $view->with('environmentService', app(EnvironmentConfigService::class));
                try {
                    $view->with('emailConfiguration', app(EnvironmentConfigService::class)->getMailConfiguration());
                } catch (\Throwable $e) {
                    $view->with('emailConfiguration', []);
                }
            }

            $pending = session()->get('pending_env_update');
            $view->with('hasPendingEnvUpdate', !empty($pending));
            $view->with('pendingEnvUpdate', $pending);
            $view->with('envUpdateComplete', session('env_update_complete', false));
            $view->with('envUpdateWarning', session('env_update_warning', false));
            $view->with('envUpdateError', session('env_update_error', false));
            $view->with('envUpdateMessage', session('env_update_message', ''));
        });

        View::composer(['admin.*', 'superadmin.*', 'landlord.*'], function ($view) {
            if (app()->bound(PaymentService::class)) {
                try {
                    $view->with('paymentConfiguration', app(PaymentService::class)->checkPaymentMethodConfiguration());
                } catch (\Throwable $e) {
                    $view->with('paymentConfiguration', []);
                }
            }
        });

        View::composer(['admin.dashboard', 'superadmin.dashboard', 'layouts.admin'], function ($view) {
            if (class_exists(\App\Models\SystemSetting::class)) {
                try {
                    $settings = \App\Models\SystemSetting::getSettings();
                    $view->with('systemConfiguration', $settings->getSystemConfigurationStatus());
                    $view->with('emailConfigurationStatus', $settings->getEmailStatus());
                    $view->with('paymentConfigurationStatus', $settings->getPaymentConfigurationStatus());
                } catch (\Throwable $e) {
                    $view->with('systemConfiguration', []);
                    $view->with('emailConfigurationStatus', []);
                    $view->with('paymentConfigurationStatus', []);
                }
            }

            $view->with('hasPendingEnvUpdate', !empty(session()->get('pending_env_update')));

            if (app()->bound(MultiChannelInvitationService::class)) {
                try {
                    $view->with('channelStatistics', app(MultiChannelInvitationService::class)->getChannelStatistics());
                } catch (\Throwable $e) {
                    $view->with('channelStatistics', []);
                }
            }
        });
    }

    private function registerInvoiceComposers(): void
    {
        View::composer(['landlord.*', 'admin.*', 'superadmin.*'], function ($view) {
            $user = Auth::user();
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
                        ->sum(DB::raw('amount - amount_paid')),
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
     * Theme composer.
     *
     * Delegates entirely to ThemeViewComposer, which:
     *   - resolves settings (session-cached, service-backed, default fallback)
     *   - resolves custom colors (service-backed, default fallback)
     *   - generates CSS via the service (with DTO validation)
     *   - binds a fully-shaped `$theme` array + `$defaultColors` to the view
     *
     * No static calls to ThemeController anywhere.
     */
    private function registerThemeComposers(): void
    {
        View::composer(
            ['admin.*', 'superadmin.*', 'landlord.*', 'layouts.admin', 'partials.sidebar'],
            ThemeViewComposer::class
        );
    }

    private function registerAuthComposers(): void
    {
        View::composer('*', function ($view) {
            $user = Auth::user();
            if ($user) {
                $svc = app(TwoFactorAuthService::class);
                $view->with('twoFactorEnabled', $svc->isEnabled($user));
                $view->with('twoFactorMethod', $user->two_factor_method ?? 'email');
            }
        });

        View::composer(['auth.profile', 'profile.*', 'user.profile'], function ($view) {
            $user = Auth::user();
            if ($user && app()->bound(LoginActivityService::class)) {
                $svc = app(LoginActivityService::class);
                $view->with('recentLoginActivity', $svc->getRecentActivity($user, 10));
                $view->with('totalLogins', $user->login_count ?? 0);
                $view->with('lastLoginAt', $user->last_login_at);
                $view->with('lastLoginIp', $user->last_login_ip);
            }
        });

        View::composer(['admin.dashboard', 'superadmin.dashboard'], function ($view) {
            try {
                $totalUsers      = User::count();
                $activeToday     = User::where('last_login_at', '>=', now()->subDay())->count();
                $activeThisWeek  = User::where('last_login_at', '>=', now()->subWeek())->count();
                $twoFactorCount  = User::where('two_factor_enabled', true)->count();

                $view->with('authStats', [
                    'total_users'            => $totalUsers,
                    'active_today'           => $activeToday,
                    'active_this_week'       => $activeThisWeek,
                    'two_factor_enabled'     => $twoFactorCount,
                    'two_factor_percentage'  => $totalUsers > 0 ? round(($twoFactorCount / $totalUsers) * 100) : 0,
                ]);

                if (app()->bound(LoginActivityService::class)) {
                    $view->with('recentLogins',
                        DB::table('login_activities')->orderByDesc('created_at')->limit(10)->get()
                    );
                }
            } catch (\Throwable $e) {
                $view->with('authStats', []);
                $view->with('recentLogins', []);
            }
        });

        View::composer('auth.login', function ($view) {
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

                $view->with('loginBillingState', self::buildLoginBillingState($state));
                $view->with('loginBillingOverdue', in_array($state, ['active_overdue', 'active_suspended'], true));
            } catch (\Throwable $e) {
                Log::warning('[Billing] Failed to resolve login billing state', ['error' => $e->getMessage()]);
                $view->with('loginBillingState', self::LOGIN_BILLING_STATE_DEFAULT);
                $view->with('loginBillingOverdue', false);
            }
        });
    }

    private function registerSidebarComposer(): void
    {
        View::composer('partials.sidebar', SidebarComposer::class);
    }

    /* ============================================================
     | VALIDATION RULES
     * ============================================================ */

    private function registerValidationRules(): void
    {
        $this->registerSystemValidationRules();
        $this->registerAuthValidationRules();
        $this->registerGhanaValidationRules();
        $this->registerInvoiceValidationRules();
    }

    private function registerSystemValidationRules(): void
    {
        Validator::extend('ghana_phone', fn ($a, $v) => preg_match('/^(\+233|0)[235]\d{8}$/', $v));
        Validator::replacer('ghana_phone', fn ($m, $a) => 'The ' . $a . ' must be a valid Ghanaian phone number.');

        Validator::extend('digital_address', fn ($a, $v) => preg_match('/^[A-Z]{2}-\d{3}-\d{4}$/', $v));
        Validator::replacer('digital_address', fn ($m, $a) => 'The ' . $a . ' must be a valid Ghana digital address (e.g., AA-123-4567).');

        Validator::extend('free_service', fn () => true);
        Validator::replacer('free_service', fn () => 'This service is free - no payment required.');

        Validator::extend('valid_channel', fn ($a, $v) => in_array($v, ['sms', 'whatsapp', 'email', 'both'], true));
        Validator::replacer('valid_channel', fn ($m, $a) => 'The ' . $a . ' must be a valid channel: sms, whatsapp, email, or both.');

        Validator::extend('env_writable', function () {
            try {
                $envPath = base_path('.env');
                return file_exists($envPath) && is_writable($envPath);
            } catch (\Throwable $e) {
                return false;
            }
        });
        Validator::replacer('env_writable', fn () => 'The system environment file is not writable. Please check file permissions.');

        Validator::extend('valid_email_config', function ($a, $v) {
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return false;
            try {
                return app(EnvironmentConfigService::class)->validateEmailConfiguration($v);
            } catch (\Throwable $e) {
                return false;
            }
        });
        Validator::replacer('valid_email_config', fn ($m, $a) => 'The ' . $a . ' must be a valid email address and properly configured for the system.');

        Validator::extend('no_pending_env_update', fn () =>
            !session()->has('pending_env_update') || session('pending_env_update.attempted', false)
        );
        Validator::replacer('no_pending_env_update', fn () => 'A system environment update is currently in progress. Please wait for it to complete.');
    }

    private function registerAuthValidationRules(): void
    {
        Validator::extend('strong_password', fn ($a, $v) =>
            preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $v)
        );
        Validator::replacer('strong_password', fn ($m, $a) =>
            'The ' . $a . ' must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.'
        );

        Validator::extend('two_factor_code', fn ($a, $v) => preg_match('/^\d{6}$/', $v));
        Validator::replacer('two_factor_code', fn ($m, $a) => 'The ' . $a . ' must be a 6-digit number.');

        Validator::extend('email_or_phone', function ($a, $v) {
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) return true;
            return app(PhoneNormalizationService::class)->isValid($v);
        });
        Validator::replacer('email_or_phone', fn ($m, $a) => 'The ' . $a . ' must be a valid email address or Ghanaian phone number.');

        Validator::extend('current_password', fn ($a, $v) =>
            Auth::check() && Hash::check($v, Auth::user()->password)
        );
        Validator::replacer('current_password', fn ($m, $a) => 'The ' . $a . ' is incorrect.');

        Validator::extend('password_history', function ($a, $v) {
            $user = Auth::user();
            if (!$user || !$user->password_history) return true;

            $history = json_decode($user->password_history, true) ?? [];
            foreach ($history as $old) {
                if (Hash::check($v, $old)) return false;
            }
            return true;
        });
        Validator::replacer('password_history', fn () => 'You cannot reuse your last 5 passwords. Please choose a new password.');
    }

    private function registerGhanaValidationRules(): void
    {
        Validator::extend('advance_rent_months', fn ($a, $v) => is_numeric($v) && (int) $v >= 1 && (int) $v <= 60);
        Validator::replacer('advance_rent_months', fn ($m, $a) => 'The ' . $a . ' must be between 1 and 60 months.');

        Validator::extend('advance_rent_within_duration', function ($attribute, $value, $parameters, $validator) {
            $durationField = $parameters[0] ?? 'duration_months';
            $duration = (int) ($validator->getData()[$durationField] ?? 0);

            if ($duration <= 0) return true;
            return (int) $value <= $duration;
        });
        Validator::replacer('advance_rent_within_duration', fn ($m, $a) =>
            'The ' . $a . ' cannot exceed the total lease duration.'
        );

        Validator::extend('ghana_advance_compliant', function ($attribute, $value, $parameters) {
            $legalMax = isset($parameters[0]) ? (int) $parameters[0] : 6;
            return (int) $value <= $legalMax;
        });
        Validator::replacer('ghana_advance_compliant', function ($message, $attribute, $rule, $parameters) {
            $legalMax = $parameters[0] ?? 6;
            return "The {$attribute} exceeds the Rent Act 1963 legal maximum of {$legalMax} month(s) "
                 . "for this tenancy type. Confirmation is required if the tenant voluntarily offered it.";
        });
    }

    private function registerInvoiceValidationRules(): void
    {
        Validator::extend('invoice_type', function ($a, $v) {
            if (!class_exists(PropertyUnitInvoice::class)) return true;
            return in_array($v, [
                PropertyUnitInvoice::TYPE_ADVANCE_RENT,
                PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_LATE_FEE,
                PropertyUnitInvoice::TYPE_EARLY_TERMINATION,
                PropertyUnitInvoice::TYPE_OTHER,
            ], true);
        });
        Validator::replacer('invoice_type', fn ($m, $a) => 'The ' . $a . ' must be a valid invoice type.');

        Validator::extend('payment_method', fn ($a, $v) => in_array($v, [
            'cash', 'bank_transfer', 'card', 'cheque', 'mobile_money',
        ], true));
        Validator::replacer('payment_method', fn ($m, $a) =>
            'The ' . $a . ' must be one of: cash, bank transfer, card, cheque, or mobile money.'
        );

        Validator::extend('amount_within_invoice_balance', function ($attribute, $value, $parameters) {
            if (!class_exists(PropertyUnitInvoice::class)) return true;
            $invoiceId = $parameters[0] ?? null;
            if (!$invoiceId) return true;

            $invoice = PropertyUnitInvoice::find($invoiceId);
            if (!$invoice) return true;

            $remaining = max(0, $invoice->amount - $invoice->amount_paid);
            return (float) $value <= $remaining + 0.01;
        });
        Validator::replacer('amount_within_invoice_balance', fn ($m, $a) =>
            'The ' . $a . ' cannot exceed the invoice\'s remaining balance.'
        );
    }

    /* ============================================================
     | MACROS
     * ============================================================ */

    private function registerMacros(): void
    {
        $this->registerCollectionMacros();
        $this->registerStringMacros();
        $this->registerCarbonMacros();
        $this->registerResponseMacros();
        $this->registerRequestMacros();
        $this->registerAuthGuardMacros();
    }

    private function registerCollectionMacros(): void
    {
        Collection::macro('groupByStatus', fn () => $this->groupBy(fn ($item) => $item->status ?? 'unknown'));

        Collection::macro('hasEmailConfig', function () {
            try {
                $config = app(EnvironmentConfigService::class)->getMailConfiguration();
                return !empty($config['username']) && !empty($config['host']);
            } catch (\Throwable $e) {
                return false;
            }
        });

        Collection::macro('hasMultiChannelConfig', function () {
            try {
                $config = app(MultiChannelInvitationService::class)->getServiceConfiguration();
                return !empty($config['sms_service_configured'])
                    || !empty($config['email_service_configured'])
                    || !empty($config['whatsapp_service_configured']);
            } catch (\Throwable $e) {
                return false;
            }
        });

        Collection::macro('hasPendingEnvUpdate', fn () =>
            session()->has('pending_env_update') && !session('pending_env_update.attempted', false)
        );

        Collection::macro('userActivitySummary', function () {
            return [
                'total'              => $this->count(),
                'active_today'       => $this->filter(fn ($u) => $u->last_login_at && $u->last_login_at >= now()->subDay())->count(),
                'active_this_week'   => $this->filter(fn ($u) => $u->last_login_at && $u->last_login_at >= now()->subWeek())->count(),
                'two_factor_enabled' => $this->filter(fn ($u) => $u->two_factor_enabled ?? false)->count(),
                'never_logged_in'    => $this->filter(fn ($u) => !$u->last_login_at)->count(),
            ];
        });
    }

    private function registerStringMacros(): void
    {
        Str::macro('truncateMiddle', function ($text, $length = 30, $separator = '...') {
            if (strlen($text) <= $length) return $text;
            $half = (int) floor($length / 2);
            return substr($text, 0, $half) . $separator . substr($text, -$half);
        });
    }

    private function registerCarbonMacros(): void
    {
        Carbon::macro('addBusinessDays', function ($days) {
            $date = $this->copy();
            $added = 0;
            while ($added < $days) {
                $date->addDay();
                if (!$date->isWeekend()) $added++;
            }
            return $date;
        });
    }

    private function registerResponseMacros(): void
    {
        $factory = ResponseFactory::class;

        $factory::macro('success',      fn ($data = null, $message = 'Success', $status = 200) =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data], $status)
        );
        $factory::macro('error',        fn ($message = 'Error', $status = 400, $errors = null) =>
            response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status)
        );

        $factory::macro('freeService',  fn ($data = null, $message = 'Free service activated successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'is_free' => true])
        );
        $factory::macro('envConfig',    fn ($data = null, $message = 'Environment configuration updated successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'env_updated' => true])
        );
        $factory::macro('emailConfig',  fn ($data = null, $message = 'Email configuration updated successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'email_configured' => true])
        );
        $factory::macro('multiChannel', fn ($data = null, $message = 'Multi-channel message sent successfully') =>
            response()->json([
                'success' => true, 'message' => $message, 'data' => $data,
                'multi_channel' => true, 'channels_used' => $data['channels_successful'] ?? [],
            ])
        );
        $factory::macro('pendingEnvUpdate', fn ($data = null, $message = 'Environment update queued for background processing') =>
            response()->json([
                'success' => true, 'message' => $message, 'data' => $data,
                'env_update_queued' => true, 'pending_update' => true,
            ])
        );
        $factory::macro('themeSuccess', fn ($data = null, $message = 'Theme updated successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'theme_updated' => true])
        );
        $factory::macro('themeError',   fn ($message = 'Theme update failed', $errors = null) =>
            response()->json(['success' => false, 'message' => $message, 'errors' => $errors, 'theme_updated' => false], 422)
        );
        $factory::macro('invoiceSuccess', fn ($data = null, $message = 'Invoice processed successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'invoice' => true])
        );
        $factory::macro('invoiceError', fn ($message = 'Invoice operation failed', $errors = null) =>
            response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422)
        );
        $factory::macro('leaseSuccess', fn ($data = null, $message = 'Lease processed successfully') =>
            response()->json(['success' => true, 'message' => $message, 'data' => $data, 'lease' => true])
        );
        $factory::macro('leaseError',   fn ($message = 'Lease operation failed', $errors = null) =>
            response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422)
        );
        $factory::macro('developerSuccess', fn ($data = null, $message = 'Developer payment configuration saved') =>
            response()->json([
                'success' => true, 'message' => $message, 'data' => $data,
                'developer_payment' => true,
            ])
        );
        $factory::macro('developerError', fn ($message = 'Developer payment operation failed', $errors = null, $status = 422) =>
            response()->json([
                'success' => false, 'message' => $message, 'errors' => $errors,
                'developer_payment' => true,
            ], $status)
        );

        $factory::macro('billingRestricted', fn ($message = 'Action blocked: system billing is overdue.', $data = null) =>
            response()->json([
                'success' => false, 'message' => $message,
                'billing_restricted' => true, 'data' => $data,
            ], 402)
        );
        $factory::macro('billingReadOnly', fn ($message = 'Read-only mode: system billing is overdue.', $data = null) =>
            response()->json([
                'success' => false, 'message' => $message,
                'billing_read_only' => true, 'data' => $data,
            ], 403)
        );

        $factory::macro('authSuccess', function ($user, $token = null, $message = 'Authentication successful') {
            $response = [
                'success' => true,
                'message' => $message,
                'user'    => [
                    'id'                 => $user->id,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'phone'              => $user->phone,
                    'type'               => $user->type,
                    'role_name'          => $user->getRoleName(),
                    'avatar'             => $user->avatar_url,
                    'two_factor_enabled' => $user->two_factor_enabled ?? false,
                ],
            ];

            if ($token) {
                $response['token']      = $token;
                $response['token_type'] = 'Bearer';
            }

            return response()->json($response, 200);
        });

        $factory::macro('twoFactorRequired', fn ($userId, $message = 'Two-factor authentication required') =>
            response()->json([
                'success' => false, 'message' => $message,
                'requires_2fa' => true, 'user_id' => $userId,
            ], 401)
        );
    }

    private function registerRequestMacros(): void
    {
        \Illuminate\Http\Request::macro('deviceInfo', function () {
            $agent = new \Jenssegers\Agent\Agent();
            $agent->setUserAgent($this->userAgent());

            return [
                'device'     => $agent->device(),
                'platform'   => $agent->platform(),
                'browser'    => $agent->browser(),
                'is_mobile'  => $agent->isMobile(),
                'is_tablet'  => $agent->isTablet(),
                'is_desktop' => $agent->isDesktop(),
                'user_agent' => $this->userAgent(),
                'ip'         => $this->ip(),
            ];
        });
    }

    private function registerAuthGuardMacros(): void
    {
        SessionGuard::macro('canImpersonate', fn () =>
            $this->user() && $this->user()->isSuperAdmin()
        );

        SessionGuard::macro('impersonate', function ($user) {
            if (!$this->canImpersonate()) {
                throw new \RuntimeException('Unauthorized to impersonate users.');
            }

            session()->put('impersonate', [
                'original_user_id'   => $this->id(),
                'original_user_type' => $this->user()->type,
            ]);

            $this->login($user);
            return true;
        });

        SessionGuard::macro('stopImpersonating', function () {
            $data = session()->get('impersonate');
            if (!$data) return false;

            $originalUser = User::find($data['original_user_id']);
            if (!$originalUser) return false;

            $this->login($originalUser);
            session()->forget('impersonate');
            return true;
        });

        SessionGuard::macro('isImpersonating', fn () => session()->has('impersonate'));
    }

    /* ============================================================
     | CUSTOM CONFIGURATION
     * ============================================================ */

    private function registerCustomConfiguration(): void
    {
        $this->registerSystemConfig();
        $this->registerCommunicationConfig();
        $this->registerAuthConfig();
        $this->registerSocialLoginConfig();
        $this->registerLoginActivityConfig();
        $this->registerGhanaConfig();
        $this->registerThemeConfig();
        $this->registerBillingConfig();
        $this->registerDeveloperConfig();
    }

    private function registerSystemConfig(): void
    {
        config(['app.system_defaults' => [
            'currency'    => 'GHS',
            'timezone'    => 'Africa/Accra',
            'locale'      => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
        ]]);

        config(['app.env_update' => [
            'queue_enabled'    => config('queue.default') !== 'sync',
            'session_fallback' => true,
            'auto_process'     => true,
            'processing_delay' => 5,
        ]]);

        config(['app.invitation_expiry_days'           => (int) config('app.invitation_expiry_days', 7)]);
        config(['app.invitation_warning_days'          => (int) config('app.invitation_warning_days', 2)]);
        config(['app.invitation_auto_expiry'           => (bool) config('app.invitation_auto_expiry', true)]);
        config(['app.invitation_resend_extends_expiry' => (bool) config('app.invitation_resend_extends_expiry', true)]);
    }

    private function registerCommunicationConfig(): void
    {
        config(['services.env_config' => [
            'enabled'         => true,
            'auto_update'     => (bool) config('services.env_config.auto_update', true),
            'backup_enabled'  => (bool) config('services.env_config.backup_enabled', true),
            'allowed_keys'    => ['MAIL_USERNAME', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION'],
            'background_update' => [
                'enabled'       => true,
                'delay_seconds' => 5,
                'max_attempts'  => 1,
                'auto_retry'    => false,
            ],
        ]]);

        config(['services.multi_channel' => [
            'default_channel'  => 'sms',
            'fallback_enabled' => true,
            'channels' => [
                'sms'      => ['enabled' => true, 'priority' => 1, 'fallback_to' => null],
                'whatsapp' => ['enabled' => true, 'priority' => 2, 'fallback_to' => 'sms'],
                'email'    => ['enabled' => true, 'priority' => 3, 'fallback_to' => 'sms'],
            ],
            'analytics_enabled' => true,
            'delivery_receipts' => true,
        ]]);
    }

    private function registerAuthConfig(): void
    {
        config(['auth' => array_merge(config('auth', []), [
            'lockout' => [
                'enabled'       => true,
                'max_attempts'  => 5,
                'decay_minutes' => 1,
            ],
            'password_timeout' => 90,
            'two_factor' => [
                'enabled'            => true,
                'code_length'        => 6,
                'code_validity'      => 10,
                'max_attempts'       => 5,
                'backup_codes_count' => 8,
            ],
            'session' => [
                'single_session'   => (bool) config('auth.session.single_session', false),
                'session_lifetime' => (int) config('auth.session.session_lifetime', 120),
            ],
        ])]);
    }

    private function registerSocialLoginConfig(): void
    {
        $this->mergeServiceConfig('google',    'GOOGLE',    ['openid', 'profile', 'email']);
        $this->mergeServiceConfig('microsoft', 'MICROSOFT', ['openid', 'profile', 'email', 'User.Read']);
        $this->mergeServiceConfig('facebook',  'FACEBOOK',  ['email', 'public_profile']);
        $this->mergeServiceConfig('apple',     'APPLE',     null);
        $this->mergeServiceConfig('github',    'GITHUB',    null);
    }

    private function mergeServiceConfig(string $key, string $envPrefix, ?array $scopes): void
    {
        $existing = config("services.{$key}", []);

        $config = array_merge($existing, [
            'client_id'     => config("services.{$key}.client_id")     ?? env("{$envPrefix}_CLIENT_ID"),
            'client_secret' => config("services.{$key}.client_secret") ?? env("{$envPrefix}_CLIENT_SECRET"),
            'redirect'      => config("services.{$key}.redirect")      ?? env("{$envPrefix}_REDIRECT_URI", config('app.url') . "/auth/{$key}/callback"),
        ]);

        if ($scopes !== null) {
            $config['scopes'] = $scopes;
        }

        config(["services.{$key}" => $config]);
    }

    private function registerLoginActivityConfig(): void
    {
        config(['login_activity' => [
            'enabled'                  => true,
            'retention_days'           => 90,
            'log_failed_attempts'      => true,
            'log_suspicious_activity'  => true,
            'ip_geolocation'           => (bool) config('login_activity.ip_geolocation', true),
            'alert_on_suspicious'      => true,
        ]]);
    }

    private function registerGhanaConfig(): void
    {
        config(['leases' => [
            'ghana' => [
                'legal_max_advance_months' => [
                    'new_tenancy'   => 6,
                    'renewal'       => 3,
                    'short_tenancy' => 1,
                ],
                'currency'           => 'GHS',
                'governing_law'      => 'Rent Act, 1963 (Act 220)',
                'enforce_compliance' => (bool) config('leases.ghana.enforce_compliance', false),
            ],
            'defaults' => [
                'payment_due_day'        => 5,
                'grace_period_days'      => 0,
                'notice_period_days'     => 30,
                'deposit_payment_method' => 'upfront',
            ],
            'invoice' => [
                'auto_generate_on_lease_create' => (bool) config('leases.invoice.auto_generate_on_lease_create', true),
                'void_future_on_terminate'      => (bool) config('leases.invoice.void_future_on_terminate', true),
                'mark_overdue_after_days'       => (int) config('leases.invoice.mark_overdue_after_days', 1),
            ],
        ]]);
    }

    private function registerThemeConfig(): void
    {
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
                    'appearance'       => config('theme.defaults.appearance', 'system'),
                    'sidebar_theme'    => config('theme.defaults.sidebar_theme', 'default'),
                    'font_size'        => config('theme.defaults.font_size', 'medium'),
                    'layout'           => config('theme.defaults.layout', 'comfortable'),
                    'animations'       => config('theme.defaults.animations', 'enabled'),
                    'sidebar_position' => config('theme.defaults.sidebar_position', 'left'),
                    'header_style'     => config('theme.defaults.header_style', 'default'),
                    'density'          => config('theme.defaults.density', 'comfortable'),
                ],
                'cache' => [
                    'enabled' => (bool) config('theme.cache.enabled', true),
                    'ttl'     => (int) config('theme.cache.ttl', 3600),
                    'prefix'  => config('theme.cache.prefix', 'theme_'),
                ],
                // Preview route used by ThemePreviewController::livePreview().
                // Registered here so the controller's route() call never throws.
                'preview_route' => 'theme.preview.live',
            ],
            config('theme', [])
        )]);
    }

    private function registerBillingConfig(): void
    {
        config(['billing' => [
            'state_cache_ttl'                   => (int) config('billing.state_cache_ttl', 300),
            'default_grace_period_days'         => (int) config('billing.default_grace_period_days', 7),
            'due_soon_threshold_days'           => (int) config('billing.due_soon_threshold_days', 5),
            'default_reminder_days'             => [7, 3, 1],
            'payment_dashboard_route'           => 'superadmin.billing.dashboard',
            'suspension_route'                  => 'billing.suspended',
            'default_hard_lock_landlords'       => false,
            'online_providers'                  => ['paystack', 'expresspay', 'flutterwave', 'hubtel'],
            'exempt_roles'                      => ['developer'],
            'allow_landlord_payment_management' => (bool) config('billing.allow_landlord_payment_management', false),
        ]]);
    }

    private function registerDeveloperConfig(): void
    {
        config(['developer_payments' => [
            'access_enabled'              => (bool) config('developer_payments.access_enabled', true),
            'can_bill'                    => (bool) config('developer_payments.can_bill', true),
            'allow_super_admin_configure' => (bool) config('developer_payments.allow_super_admin_configure', true),
            'storage'                     => config('developer_payments.storage', 'env'),
        ]]);
    }

    /* ============================================================
     | AUTH EVENTS
     * ============================================================ */

    private function registerAuthenticationEvents(): void
    {
        Event::listen(Login::class, function ($event) {
            $user = $event->user;
            if (!$user->last_login_at) {
                $user->last_login_at = now();
                $user->save();
            }
        });

        Event::listen(Logout::class, function ($event) {
            if (session()->has('2fa:user_id')) {
                session()->forget('2fa:user_id');
            }
        });

        Event::listen(Failed::class, function ($event) {
            if (app()->bound(LoginActivityService::class)) {
                app(LoginActivityService::class)->recordFailedLogin($event->user, request(), 'invalid_credentials');
            }
        });

        Event::listen(PasswordReset::class, function ($event) {
            $user = $event->user;
            if (app()->bound(LoginActivityService::class)) {
                app(LoginActivityService::class)->recordPasswordChange($user, request());
            }
            $user->two_factor_enabled = false;
            $user->save();
        });

        Event::listen(\App\Events\BillingPaymentRecorded::class, fn () => DeveloperSetting::forgetCurrent());
        Event::listen(\App\Events\BillingAgreementSigned::class, fn () => DeveloperSetting::forgetCurrent());
    }

    /* ============================================================
     | 2FA ROUTES
     * ============================================================ */

    private function registerTwoFactorRoutes(): void
    {
        Route::macro('twoFactorAuth', function () {
            Route::group(['middleware' => ['web']], function () {
                Route::get('/2fa/verify',  [\App\Http\Controllers\Auth\LoginController::class, 'showTwoFactorForm'])->name('2fa.verify');
                Route::post('/2fa/verify', [\App\Http\Controllers\Auth\LoginController::class, 'verifyTwoFactor'])->name('2fa.verify.submit');
                Route::post('/2fa/resend', [\App\Http\Controllers\Auth\LoginController::class, 'resendTwoFactorCode'])->name('2fa.resend');
            });

            Route::group(['middleware' => ['web', 'auth']], function () {
                Route::post('/2fa/enable',  [\App\Http\Controllers\Auth\LoginController::class, 'enableTwoFactor'])->name('2fa.enable');
                Route::post('/2fa/disable', [\App\Http\Controllers\Auth\LoginController::class, 'disableTwoFactor'])->name('2fa.disable');
            });
        });
    }
}