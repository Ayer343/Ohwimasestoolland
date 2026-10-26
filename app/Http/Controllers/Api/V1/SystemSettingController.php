<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Http\Controllers\Admin\LogoController;
use App\Services\PaymentService;
use App\Services\EnvironmentConfigService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Jobs\UpdateSystemConfiguration;
use App\Jobs\TestSystemConnection;
use App\Traits\NotifiesUsers;
use App\Traits\ChecksBillingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SystemSettingController extends Controller
{
    use NotifiesUsers, ChecksBillingAccess;

    protected $paymentService;
    protected $environmentService;
    protected $smsService;
    protected $whatsappService;

    /** Cache key for public settings. */
    private const PUBLIC_CACHE_KEY = 'public_system_settings';
    private const PUBLIC_CACHE_TTL = 3600;

    /** Single source of truth for boolean/checkbox fields. */
    protected const BOOLEAN_FIELDS = [
        'enable_tenant_invoicing',
        'auto_generate_tenant_invoices',
        'send_tenant_payment_reminders',
        'sms_notifications_enabled',
        'sms_reminder_enabled',
        'sms_payment_confirmation_enabled',
        'enable_whatsapp_notifications',
        'whatsapp_reminder_enabled',
        'enable_expresspay',
        'enable_hubtel',
        'enable_paystack',
        'enable_flutterwave',
        'enable_bulk_payments',
        'allow_offline_payment',
        'auto_generate_invoices',
        'send_payment_reminders',
        'allow_registration',
    ];

    /** Single source of truth for the four notification-channel array fields. */
    protected const CHANNEL_FIELDS = [
        'invoice_notification_channels',
        'payment_reminder_channels',
        'overdue_notification_channels',
        'payment_confirmation_channels',
    ];

    public function __construct(
        PaymentService $paymentService,
        EnvironmentConfigService $environmentService,
        SmsService $smsService,
        WhatsAppService $whatsappService
    ) {
        $this->paymentService     = $paymentService;
        $this->environmentService = $environmentService;
        $this->smsService         = $smsService;
        $this->whatsappService    = $whatsappService;
    }

    /* ============================================================
     | ROLE HELPERS
     * ============================================================ */

    protected function isAdminOrSuperAdmin(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        return in_array($user->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN], true);
    }

    protected function isSuperAdmin(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        return $user && $user->type === User::TYPE_SUPER_ADMIN;
    }

    protected function isDeveloper(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        return $user->hasRole('developer') || (int) $user->type === 5;
    }

    /* ============================================================
     | PUBLIC ENDPOINTS
     * ============================================================ */

    /**
     * GET /api/v1/system-settings
     * Public — no auth required.
     */
    public function getPublicSettings()
    {
        try {
            $settings = Cache::remember(
                self::PUBLIC_CACHE_KEY,
                self::PUBLIC_CACHE_TTL,
                fn() => SystemSetting::first()
            );

            if (!$settings) {
                Log::warning('No system settings found in database, returning defaults');
                return $this->getDefaultSettingsResponse('fallback');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'system_name' => $settings->system_name ?? config('app.name', 'Hilltop Estate'),
                    'system_short_name' => $settings->system_short_name ?? 'HSM',
                    'system_email' => $settings->system_email ?? 'info@hilltopestate.com',
                    'system_phone' => $settings->system_phone ?? '+233-595652410',
                    'system_address' => $settings->system_address ?? 'Accra, Ghana',
                    'contact_phone' => $settings->system_phone ?? '+233-595652410',
                    'contact_email' => $settings->system_email ?? 'info@hilltopestate.com',
                    'powered_by' => 'SteveTech Engineering',
                    'logo_url' => $settings->system_logo
                        ? asset('storage/' . $settings->system_logo)
                        : null,
                    'favicon_url' => $settings->getFaviconUrl(),

                    'currency_code' => $settings->currency_code ?? 'GHS',
                    'currency_symbol' => $settings->currency_symbol ?? 'GH₵',
                    'currency_position' => $settings->currency_position ?? 'left',
                    'decimal_places' => $settings->decimal_places ?? 2,

                    'registration_allowed' => $this->isRegistrationAllowed($settings),
                    'registration_disabled_message' => $this->getRegistrationDisabledMessage($settings),

                    'enabled_gateways' => [
                        'expresspay' => (bool) ($settings->enable_expresspay ?? false),
                        'hubtel' => (bool) ($settings->enable_hubtel ?? false),
                        'paystack' => (bool) ($settings->enable_paystack ?? false),
                        'flutterwave' => (bool) ($settings->enable_flutterwave ?? false),
                    ],

                    'tenant_invoicing' => [
                        'enabled' => (bool) ($settings->enable_tenant_invoicing ?? false),
                        'monthly_dues_amount' => $settings->tenant_monthly_dues_amount ?? 50.00,
                        'calculation_method' => $settings->tenant_calculation_method ?? 'fixed',
                        'auto_generate_invoices' => (bool) ($settings->auto_generate_tenant_invoices ?? true),
                        'send_payment_reminders' => (bool) ($settings->send_tenant_payment_reminders ?? true),
                        'grace_period_days' => $settings->tenant_grace_period_days ?? 7,
                        'late_payment_percentage' => $settings->tenant_late_payment_percentage ?? 5.00,
                        'fixed_penalty_amount' => $settings->tenant_fixed_penalty_amount ?? 0.00,
                    ],

                    'notification_channels' => [
                        'invoice' => $settings->invoice_notification_channels ?? ['email'],
                        'reminder' => $settings->payment_reminder_channels ?? ['email'],
                        'overdue' => $settings->overdue_notification_channels ?? ['email', 'sms'],
                        'confirmation' => $settings->payment_confirmation_channels ?? ['email'],
                    ],

                    'sms' => [
                        'enabled' => (bool) ($settings->sms_notifications_enabled ?? false),
                        'reminder_enabled' => (bool) ($settings->sms_reminder_enabled ?? false),
                        'payment_confirmation_enabled' => (bool) ($settings->sms_payment_confirmation_enabled ?? false),
                        'daily_limit_per_user' => $settings->sms_daily_limit_per_user ?? 10,
                        'hourly_limit_per_user' => $settings->sms_hourly_limit_per_user ?? 3,
                    ],

                    'whatsapp' => [
                        'enabled' => (bool) ($settings->enable_whatsapp_notifications ?? false),
                        'reminder_enabled' => (bool) ($settings->whatsapp_reminder_enabled ?? false),
                    ],

                    'app_version' => config('app.version', '1.0.0'),
                    'api_version' => 'v1',
                    'min_app_version' => config('app.min_app_version', '1.0.0'),
                    'force_update' => config('app.force_update', false),
                    'maintenance_mode' => false,
                    'registration_open' => $this->isRegistrationAllowed($settings),
                    'allow_user_registration' => $this->isRegistrationAllowed($settings),
                    'site_name' => $settings->system_name ?? 'Hilltop Estate Management',

                    'settings_id' => $settings->id,
                    'updated_at' => optional($settings->updated_at)->toISOString(),
                ],
                'meta' => [
                    'cached_at' => now()->toISOString(),
                    'cache_ttl' => self::PUBLIC_CACHE_TTL,
                    'source' => 'database',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public system settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getDefaultSettingsResponse('error_fallback');
        }
    }

    protected function getDefaultSettingsResponse(string $source)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'system_name' => 'Hilltop Estate',
                'system_short_name' => 'HSM',
                'system_email' => 'info@hilltopestate.com',
                'system_phone' => '+233-595652410',
                'system_address' => 'Accra, Ghana',
                'contact_phone' => '+233-595652410',
                'contact_email' => 'info@hilltopestate.com',
                'powered_by' => 'SteveTech Engineering',
                'logo_url' => null,
                'favicon_url' => null,
                'currency_code' => 'GHS',
                'currency_symbol' => 'GH₵',
                'currency_position' => 'left',
                'decimal_places' => 2,
                'registration_allowed' => true,
                'registration_disabled_message' => null,
                'enabled_gateways' => [
                    'expresspay' => false,
                    'hubtel' => false,
                    'paystack' => false,
                    'flutterwave' => false,
                ],
                'tenant_invoicing' => [
                    'enabled' => false,
                    'monthly_dues_amount' => 50.00,
                    'calculation_method' => 'fixed',
                    'auto_generate_invoices' => true,
                    'send_payment_reminders' => true,
                    'grace_period_days' => 7,
                    'late_payment_percentage' => 5.00,
                    'fixed_penalty_amount' => 0.00,
                ],
                'notification_channels' => [
                    'invoice' => ['email'],
                    'reminder' => ['email'],
                    'overdue' => ['email', 'sms'],
                    'confirmation' => ['email'],
                ],
                'sms' => [
                    'enabled' => false,
                    'reminder_enabled' => false,
                    'payment_confirmation_enabled' => false,
                    'daily_limit_per_user' => 10,
                    'hourly_limit_per_user' => 3,
                ],
                'whatsapp' => [
                    'enabled' => false,
                    'reminder_enabled' => false,
                ],
                'app_version' => '1.0.0',
                'api_version' => 'v1',
                'min_app_version' => '1.0.0',
                'force_update' => false,
                'maintenance_mode' => false,
                'registration_open' => true,
                'allow_user_registration' => true,
                'site_name' => 'Hilltop Estate Management',
                'settings_id' => null,
                'updated_at' => null,
            ],
            'meta' => [
                'cached_at' => now()->toISOString(),
                'source' => $source,
            ],
        ]);
    }

    /* ============================================================
     | ADMIN: READ
     * ============================================================ */

    /**
     * GET /api/v1/admin/system-settings
     */
    public function getAdminSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated. Please login.');
            }
            if (!$this->isAdminOrSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Admin access required.');
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found. Please configure the system first.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $paymentConfiguration    = $this->paymentService->checkPaymentMethodConfiguration();
            $notificationChannels    = $this->getNotificationChannelStatus($settings);
            $smsStatus               = $this->smsService->getQuickStatus();
            $invoiceGenerationStatus = $this->getInvoiceGenerationStatus($settings);
            $providerFlags           = $this->resolveProviderFlags();

            return response()->json([
                'success' => true,
                'data' => [
                    'system_name' => $settings->system_name,
                    'system_short_name' => $settings->system_short_name,
                    'system_email' => $settings->system_email,
                    'system_phone' => $settings->system_phone,
                    'system_address' => $settings->system_address,
                    'logo_url' => $settings->system_logo
                        ? asset('storage/' . $settings->system_logo)
                        : null,
                    'favicon_url' => $settings->getFaviconUrl(),

                    'currency_code' => $settings->currency_code,
                    'currency_symbol' => $settings->currency_symbol,
                    'currency_position' => $settings->currency_position,
                    'decimal_places' => $settings->decimal_places,

                    'payment_gateways' => [
                        'expresspay' => [
                            'enabled' => (bool) ($settings->enable_expresspay ?? false),
                            'configured' => (bool) ($paymentConfiguration['expresspay'] ?? false),
                        ],
                        'hubtel' => [
                            'enabled' => (bool) ($settings->enable_hubtel ?? false),
                            'configured' => (bool) ($paymentConfiguration['hubtel'] ?? false),
                        ],
                        'paystack' => [
                            'enabled' => (bool) ($settings->enable_paystack ?? false),
                            'configured' => (bool) ($paymentConfiguration['paystack'] ?? false),
                        ],
                        'flutterwave' => [
                            'enabled' => (bool) ($settings->enable_flutterwave ?? false),
                            'configured' => (bool) ($paymentConfiguration['flutterwave'] ?? false),
                        ],
                    ],
                    'payment_configuration_summary' => $paymentConfiguration,

                    'tenant_invoicing' => [
                        'enabled' => (bool) ($settings->enable_tenant_invoicing ?? false),
                        'monthly_dues_amount' => $settings->tenant_monthly_dues_amount ?? 50.00,
                        'calculation_method' => $settings->tenant_calculation_method ?? 'fixed',
                        'auto_generate_invoices' => (bool) ($settings->auto_generate_tenant_invoices ?? true),
                        'send_payment_reminders' => (bool) ($settings->send_tenant_payment_reminders ?? true),
                        'grace_period_days' => $settings->tenant_grace_period_days ?? 7,
                        'late_payment_percentage' => $settings->tenant_late_payment_percentage ?? 5.00,
                        'fixed_penalty_amount' => $settings->tenant_fixed_penalty_amount ?? 0.00,
                    ],

                    'notification_channels' => $notificationChannels,
                    'sms_status' => $smsStatus,
                    'invoice_generation' => $invoiceGenerationStatus,

                    'provider_flags' => $providerFlags,

                    'monthly_dues_amount' => $settings->monthly_dues_amount,
                    'calculation_method' => $settings->calculation_method,
                    'per_property_amount' => $settings->per_property_amount,

                    'grace_period_days' => $settings->grace_period_days,
                    'late_payment_percentage' => $settings->late_payment_percentage,
                    'fixed_penalty_amount' => $settings->fixed_penalty_amount,

                    'auto_generate_invoices' => (bool) ($settings->auto_generate_invoices ?? false),
                    'send_payment_reminders' => (bool) ($settings->send_payment_reminders ?? false),
                    'reminder_days_before' => $settings->reminder_days_before ?? 7,

                    'enable_bulk_payments' => (bool) ($settings->enable_bulk_payments ?? false),
                    'max_bulk_months' => $settings->max_bulk_months ?? 6,
                    'bulk_payment_discount' => $settings->bulk_payment_discount ?? 0,

                    'allow_registration' => (bool) ($settings->allow_registration ?? true),
                    'registration_disabled_message' => $settings->registration_disabled_message,

                    'allow_offline_payment' => (bool) ($settings->allow_offline_payment ?? false),
                    'sms_sender_id' => $settings->sms_sender_id,

                    'created_by' => $settings->created_by,
                    'updated_by' => $settings->updated_by,
                    'created_at' => $settings->created_at,
                    'updated_at' => $settings->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch admin system settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load system settings: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * GET /api/v1/admin/system-settings/simple
     * Lightweight payload (mobile bootstrap).
     */
    public function getSystemSettings(Request $request)
    {
        try {
            $settings = SystemSetting::first();

            if (!$settings) {
                return response()->json([
                    'system_name' => 'Hilltop Estate',
                    'system_short_name' => 'hsm',
                    'system_logo' => null,
                    'system_favicon' => null,
                    'sms_sender_id' => null,
                    'registration_allowed' => true,
                    'registration_disabled_message' => null,
                    'auto_generate_invoices' => false,
                    'send_payment_reminders' => false,
                    'reminder_days_before' => 7,
                    'allow_offline_payment' => false,
                    'enabled_gateways' => [
                        'expresspay' => false,
                        'hubtel' => false,
                        'paystack' => false,
                        'flutterwave' => false,
                    ],
                ]);
            }

            return response()->json([
                'system_name' => $settings->system_name,
                'system_short_name' => $settings->system_short_name,
                'system_logo' => $settings->system_logo,
                'system_favicon' => $settings->getFaviconUrl(),
                'system_email' => $settings->system_email,
                'system_phone' => $settings->system_phone,
                'sms_sender_id' => $settings->sms_sender_id,
                'registration_allowed' => $settings->isRegistrationAllowed(),
                'registration_disabled_message' => $settings->getRegistrationDisabledMessage(),
                'auto_generate_invoices' => $settings->isAutoInvoiceGenerationEnabled(),
                'send_payment_reminders' => $settings->shouldSendPaymentReminders(),
                'reminder_days_before' => $settings->getReminderDaysBefore(),
                'allow_offline_payment' => (bool) ($settings->allow_offline_payment ?? false),
                'enabled_gateways' => [
                    'expresspay' => $settings->enable_expresspay ?? false,
                    'hubtel' => $settings->enable_hubtel ?? false,
                    'paystack' => $settings->enable_paystack ?? false,
                    'flutterwave' => $settings->enable_flutterwave ?? false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'system_name' => 'Hilltop Estate',
                'system_short_name' => 'hsm',
                'system_logo' => null,
                'system_favicon' => null,
                'sms_sender_id' => null,
                'registration_allowed' => true,
                'registration_disabled_message' => null,
                'auto_generate_invoices' => false,
                'send_payment_reminders' => false,
                'reminder_days_before' => 7,
                'allow_offline_payment' => false,
                'enabled_gateways' => [
                    'expresspay' => false,
                    'hubtel' => false,
                    'paystack' => false,
                    'flutterwave' => false,
                ],
            ]);
        }
    }

    /* ============================================================
     | PROVIDER FLAGS
     * ============================================================ */

    protected function resolveProviderFlags(): array
    {
        $smsConfigured      = false;
        $whatsappConfigured = false;
        $smsActiveName      = null;
        $whatsappActiveName = null;

        $knownSmsProviders      = ['arkesel', 'twilio', 'africastalking', 'hubtel', 'nalosolutions'];
        $knownWhatsappProviders = ['twilio', 'vonage', 'custom', '360dialog', 'wati', 'vumaapi'];

        try {
            if (method_exists($this->smsService, 'getSystemStatus')) {
                $status = $this->smsService->getSystemStatus();

                if (is_array($status)) {
                    $smsConfigured = (bool) (
                        ($status['configured_providers'] ?? 0) > 0
                        || ($status['enabled_providers'] ?? 0) > 0
                        || ($status['can_send_sms'] ?? false)
                        || ($status['system_ready'] ?? false)
                    );

                    $defaultKey = $status['default_provider'] ?? null;
                    if ($defaultKey && !empty($status['available_providers'][0])) {
                        $defaultKey = $status['available_providers'][0];
                    }

                    if ($defaultKey) {
                        $providerMap = $this->smsService->checkSmsProviderConfiguration();
                        $smsActiveName = $providerMap[$defaultKey]['name']
                            ?? $providerMap[$defaultKey]['details']['name']
                            ?? $defaultKey;
                    }
                }
            }

            if (!$smsConfigured && method_exists($this->smsService, 'checkSmsProviderConfiguration')) {
                $map = $this->smsService->checkSmsProviderConfiguration();

                if (is_array($map)) {
                    foreach ($knownSmsProviders as $p) {
                        $entry = $map[$p] ?? null;
                        if (is_array($entry) && !empty($entry['configured'])) {
                            $smsConfigured = true;
                            $smsActiveName = $smsActiveName ?: ($entry['name'] ?? $p);
                            break;
                        }
                    }
                }
            }

            if (!$smsConfigured && method_exists($this->smsService, 'isConfigured')) {
                try {
                    $smsConfigured = (bool) $this->smsService->isConfigured();
                } catch (\Throwable $e) {
                    Log::debug('[API SystemSettings] isConfigured() fallback failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[API SystemSettings] Failed to resolve SMS provider flags', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            if (method_exists($this->whatsappService, 'getSystemStatus')) {
                $status = $this->whatsappService->getSystemStatus();

                if (is_array($status)) {
                    $whatsappConfigured = (bool) (
                        ($status['configured_providers'] ?? 0) > 0
                        || ($status['enabled_providers'] ?? 0) > 0
                        || ($status['can_send'] ?? false)
                        || ($status['system_ready'] ?? false)
                    );

                    $whatsappActiveName = $status['default_provider']
                        ?? $status['active_provider']
                        ?? null;
                }
            }

            if (!$whatsappConfigured && method_exists($this->whatsappService, 'checkWhatsAppConfiguration')) {
                $map = $this->whatsappService->checkWhatsAppConfiguration();

                if (is_array($map)) {
                    foreach ($knownWhatsappProviders as $p) {
                        $entry = $map[$p] ?? null;
                        if (is_array($entry) && !empty($entry['configured'])) {
                            $whatsappConfigured = true;
                            $whatsappActiveName = $whatsappActiveName ?: ($entry['name'] ?? $p);
                            break;
                        }
                    }
                }
            }

            if (!$whatsappConfigured && method_exists($this->whatsappService, 'isConfigured')) {
                try {
                    $whatsappConfigured = (bool) $this->whatsappService->isConfigured();
                } catch (\Throwable $e) {
                    Log::debug('[API SystemSettings] WhatsApp isConfigured() fallback failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[API SystemSettings] Failed to resolve WhatsApp provider flags', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('[API SystemSettings] Provider flags resolved', [
            'sms_provider_configured'      => $smsConfigured,
            'sms_active_provider'          => $smsActiveName,
            'whatsapp_provider_configured' => $whatsappConfigured,
            'whatsapp_active_provider'     => $whatsappActiveName,
        ]);

        return [
            'smsProviderConfigured'      => $smsConfigured,
            'whatsappProviderConfigured' => $whatsappConfigured,
            'smsActiveProviderName'      => $smsActiveName,
            'whatsappActiveProviderName' => $whatsappActiveName,
        ];
    }

    protected function normalizeChannelArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            $value = [];
        }

        $value = array_values(array_intersect($value, ['email', 'sms', 'whatsapp']));

        if (!in_array('email', $value, true)) {
            $value[] = 'email';
        }

        return array_values(array_unique($value));
    }

    protected function normalizeChannelFields(array $data, Request $request): array
    {
        foreach (self::CHANNEL_FIELDS as $field) {
            $raw = $data[$field] ?? $request->input($field, []);
            $data[$field] = $this->normalizeChannelArray($raw);
        }

        return $data;
    }

    protected function normalizeBooleanFields(array $data, Request $request): array
    {
        foreach (self::BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $data) || $request->has($field)) {
                $raw = $data[$field] ?? $request->input($field);
                $data[$field] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }

    /**
     * Throws ValidationException if sms/whatsapp is selected without a configured provider.
     */
    protected function assertNotificationChannelsAreAllowed(array $data): void
    {
        $flags = $this->resolveProviderFlags();

        $errors = [];

        if (!$flags['smsProviderConfigured']) {
            foreach (self::CHANNEL_FIELDS as $field) {
                $channels = $data[$field] ?? [];
                if (is_string($channels)) {
                    $decoded = json_decode($channels, true);
                    if (is_array($decoded)) $channels = $decoded;
                }

                if (is_array($channels) && in_array('sms', $channels, true)) {
                    $errors[$field] = 'SMS notifications cannot be enabled because no SMS provider is configured. Configure an SMS provider first.';
                }
            }

            if (!empty($data['sms_notifications_enabled'])
                && filter_var($data['sms_notifications_enabled'], FILTER_VALIDATE_BOOLEAN)) {
                $errors['sms_notifications_enabled'] = 'Enable SMS notifications after configuring an SMS provider.';
            }
        }

        if (!$flags['whatsappProviderConfigured']) {
            foreach (self::CHANNEL_FIELDS as $field) {
                $channels = $data[$field] ?? [];
                if (is_string($channels)) {
                    $decoded = json_decode($channels, true);
                    if (is_array($decoded)) $channels = $decoded;
                }

                if (is_array($channels) && in_array('whatsapp', $channels, true)) {
                    $errors[$field] = 'WhatsApp notifications cannot be enabled because no WhatsApp provider is configured. Configure a WhatsApp provider first.';
                }
            }

            if (!empty($data['enable_whatsapp_notifications'])
                && filter_var($data['enable_whatsapp_notifications'], FILTER_VALIDATE_BOOLEAN)) {
                $errors['enable_whatsapp_notifications'] = 'Enable WhatsApp notifications after configuring a WhatsApp provider.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /* ============================================================
     | ADMIN: WRITE — full settings update
     * ============================================================ */

    /**
     * PUT /api/v1/admin/system-settings
     * Super Admin only. Supports multipart/form-data for logo/favicon.
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated. Please login.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_update_system_settings');

            $validator = $this->validateUpdateRequest($request);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found. Please configure the system first.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldSettings = $settings->toArray();
            $updateData  = $this->buildUpdateData($request);

            // Normalize channel arrays via the shared helper.
            $updateData = $this->normalizeChannelFields($updateData, $request);

            // Normalize booleans.
            $updateData = $this->normalizeBooleanFields($updateData, $request);

            // Favicon / logo removal flags
            if ($request->boolean('remove_logo') && $settings->system_logo) {
                $logoController = app(LogoController::class);
                $logoController->deleteLogoFile($settings->system_logo);
                $updateData['system_logo'] = null;
            }

            if ($request->boolean('remove_favicon') && $settings->hasFavicon()) {
                $this->deleteFaviconFile($settings->system_favicon);
                $updateData['system_favicon'] = null;
            }

            // File uploads (logo/favicon)
            if ($request->hasFile('system_logo')) {
                if ($settings->system_logo) {
                    $logoController = app(LogoController::class);
                    $logoController->deleteLogoFile($settings->system_logo);
                }
                $logoController = app(LogoController::class);
                $updateData['system_logo'] = $logoController->handleLogoUpload($request->file('system_logo'));
            }

            if ($request->hasFile('system_favicon')) {
                if ($settings->hasFavicon()) {
                    $this->deleteFaviconFile($settings->system_favicon);
                }
                $updateData['system_favicon'] = $this->handleFaviconUpload($request->file('system_favicon'));
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected',
                    'data' => ['updated_fields' => []],
                ]);
            }

            // Safety: at least one gateway must remain enabled.
            if (!$this->wouldKeepAtLeastOneGateway($updateData, $oldSettings)) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one payment gateway must remain enabled.',
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            // Provider-availability check for notification channels.
            $this->assertNotificationChannelsAreAllowed($updateData);

            $updateData['updated_by'] = $user->id;
            $settings->update($updateData);
            $settings->refresh();

            $this->clearPublicSettingsCache();
            $this->clearSettingsReminders();
            $this->trackAndNotifyChanges($settings, $oldSettings, $user);

            // Queue env propagation (APP_NAME, MAIL_FROM_ADDRESS, SMS sender)
            $this->queueSystemConfigurationUpdate(
                $settings,
                array_merge($request->all(), [
                    'system_name'       => $settings->system_name,
                    'system_short_name' => $settings->system_short_name,
                    'system_email'      => $settings->system_email,
                ]),
                'update'
            );

            Log::info('System settings updated via API', [
                'updated_by' => $user->id,
                'updated_by_name' => $user->name,
                'fields' => array_keys($updateData),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System settings updated successfully',
                'data' => [
                    'updated_fields' => array_keys($updateData),
                    'updated_at' => $settings->updated_at,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update system settings via API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | ADMIN: SMS SENDER ID
     * ============================================================ */

    /**
     * PUT /api/v1/admin/sms-sender-id
     *
     * Allowed for Admin + Super Admin (matches restricted-admin semantics).
     * Intentionally NOT billing-gated (see web controller rationale).
     */
    public function updateSenderId(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isAdminOrSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Admin access required.');
            }

            $validator = Validator::make($request->all(), [
                'sms_sender_id' => [
                    'nullable',
                    'string',
                    'max:11',
                    'regex:/^[A-Za-z0-9 _\-]*$/',
                ],
            ], [
                'sms_sender_id.max'   => 'SMS Sender ID cannot exceed 11 characters.',
                'sms_sender_id.regex' => 'SMS Sender ID may only contain letters, numbers, spaces, hyphens, and underscores.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldSenderId = $settings->sms_sender_id;
            $newSenderId = filled($request->input('sms_sender_id'))
                ? trim((string) $request->input('sms_sender_id'))
                : null;

            $settings->sms_sender_id = $newSenderId;
            $settings->updated_by    = $user->id;
            $settings->save();

            Cache::forget('system_settings');
            Cache::forget('system_settings.sms_sender_id');
            Cache::forget('sms_default_sender_id');
            $this->clearPublicSettingsCache();

            try {
                $this->smsService->forgetGlobalSenderIdCache();
            } catch (\Throwable $e) {
                Log::debug('SmsService cache flush skipped: ' . $e->getMessage());
            }

            Log::info('SMS sender ID updated via API', [
                'user_id'       => $user->id,
                'old_sender_id' => $oldSenderId,
                'new_sender_id' => $newSenderId,
            ]);

            try {
                $this->notifySuperAdminsAboutSenderIdChange(
                    $settings,
                    $user,
                    $oldSenderId,
                    $newSenderId
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify about sender ID change: ' . $e->getMessage());
            }

            return response()->json([
                'success'   => true,
                'message'   => 'Default SMS sender ID updated successfully.',
                'sender_id' => $newSenderId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update SMS sender ID: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sender ID: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    protected function notifySuperAdminsAboutSenderIdChange(
        SystemSetting $settings,
        User $updater,
        ?string $oldSenderId,
        ?string $newSenderId
    ): void {
        $superAdmins = $this->getActiveSuperAdmins();
        if ($superAdmins->isEmpty()) {
            return;
        }

        $notificationData = [
            'title'    => '📱 SMS Sender ID Updated',
            'message'  => "Default SMS sender ID changed by {$updater->name} " .
                          "from '" . ($oldSenderId ?: '(empty)') . "' " .
                          "to '" . ($newSenderId ?: '(empty)') . "'.",
            'icon'     => 'fas fa-comment-dots text-info',
            'category' => 'system_settings',
            'action_url' => route('admin.system-settings.index'),
            'priority' => 2,
            'data'     => [
                'type'          => 'sms_sender_id_changed',
                'old_sender_id' => $oldSenderId,
                'new_sender_id' => $newSenderId,
                'changed_by'    => $updater->id,
                'changed_by_name' => $updater->name,
                'timestamp'     => now()->toISOString(),
                'notification_type' => 'in_app_only',
            ],
        ];

        foreach ($superAdmins as $admin) {
            $this->notifyUserWithData($admin, $notificationData);
        }
    }

    /* ============================================================
     | ADMIN: OFFLINE PAYMENT TOGGLE
     * ============================================================ */

    /**
     * POST /api/v1/admin/toggle-offline-payment
     *
     * Intentionally NOT billing-gated (matches web controller rationale:
     * blocking it would prevent in-person payments when landlord owes money).
     */
    public function toggleOfflinePayment(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isSuperAdmin($user) && !$this->isAdminOrSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized access.');
            }

            $validator = Validator::make($request->all(), [
                'allow_offline_payment' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldValue = (bool) ($settings->allow_offline_payment ?? false);
            $newValue = (bool) $request->allow_offline_payment;

            $settings->allow_offline_payment = $newValue;
            $settings->updated_by            = $user->id;
            $settings->save();

            $this->clearPublicSettingsCache();

            Log::info('Offline payment setting toggled via API', [
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'user_id'   => $user->id,
                'user_name' => $user->name,
            ]);

            $message = $newValue
                ? 'Office payments enabled. Admins can now mark invoices as paid manually.'
                : 'Office payments blocked. Landlords must use the online payment gateway.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'allow_offline_payment' => $newValue,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle offline payment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update offline payment setting.',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | NOTIFICATION CHANNELS
     * ============================================================ */

    public function updateNotificationChannels(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated. Please login.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_update_notification_channels');

            $validator = Validator::make($request->all(), [
                'invoice_notification_channels' => 'nullable|array',
                'invoice_notification_channels.*' => 'in:email,sms,whatsapp',
                'payment_reminder_channels' => 'nullable|array',
                'payment_reminder_channels.*' => 'in:email,sms,whatsapp',
                'overdue_notification_channels' => 'nullable|array',
                'overdue_notification_channels.*' => 'in:email,sms,whatsapp',
                'payment_confirmation_channels' => 'nullable|array',
                'payment_confirmation_channels.*' => 'in:email,sms,whatsapp',
                'sms_notifications_enabled' => 'nullable|boolean',
                'sms_reminder_enabled' => 'nullable|boolean',
                'sms_payment_confirmation_enabled' => 'nullable|boolean',
                'sms_daily_limit_per_user' => 'nullable|integer|min:1|max:100',
                'sms_hourly_limit_per_user' => 'nullable|integer|min:1|max:20',
                'enable_whatsapp_notifications' => 'nullable|boolean',
                'whatsapp_reminder_enabled' => 'nullable|boolean',
                'whatsapp_provider' => 'nullable|in:twilio,vonage,custom',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $daily  = $request->input('sms_daily_limit_per_user');
            $hourly = $request->input('sms_hourly_limit_per_user');
            if ($daily !== null && $hourly !== null && $hourly > $daily) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hourly SMS limit cannot exceed daily limit.',
                    'errors' => [
                        'sms_hourly_limit_per_user' => ['Hourly limit must be <= daily limit.'],
                    ],
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldSettings = $settings->toArray();
            $updateData  = [];

            foreach (self::CHANNEL_FIELDS as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $this->normalizeChannelArray($request->input($field, ['email']));
                }
            }

            foreach ([
                'sms_notifications_enabled',
                'sms_reminder_enabled',
                'sms_payment_confirmation_enabled',
                'sms_daily_limit_per_user',
                'sms_hourly_limit_per_user',
                'enable_whatsapp_notifications',
                'whatsapp_reminder_enabled',
                'whatsapp_provider',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected',
                    'data' => $this->getNotificationChannelStatus($settings),
                ]);
            }

            $this->assertNotificationChannelsAreAllowed($updateData);

            $updateData['updated_by'] = $user->id;
            $settings->update($updateData);
            $settings->refresh();

            $this->clearPublicSettingsCache();

            if ($this->trackNotificationChannelChanges($oldSettings, $settings->toArray())) {
                $this->notifyAboutNotificationChannelChange($settings, $oldSettings);
            }

            Log::info('Notification channels updated via API', [
                'updates' => array_keys($updateData),
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification channels updated successfully',
                'data' => $this->getNotificationChannelStatus($settings),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update notification channels via API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification channels: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function getNotificationChannelSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $flags = $this->resolveProviderFlags();

            return response()->json([
                'success' => true,
                'data' => $this->getNotificationChannelStatus($settings),
                'sms_providers' => $this->smsService->getAllProvidersWithStatus(),
                'sms_configured' => $flags['smsProviderConfigured'],
                'sms_quick_status' => $this->smsService->getQuickStatus(),
                'provider_flags' => $flags,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notification channel settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification settings',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function testNotificationChannels(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $validator = Validator::make($request->all(), [
                'channels' => 'required|array|min:1',
                'channels.*' => 'in:email,sms,whatsapp',
                'phone_number' => 'nullable|string|max:20',
                'email' => 'nullable|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $channels = $request->input('channels', ['email']);

            if (in_array('email', $channels, true) && !$request->filled('email')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email address is required to test the email channel.',
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }
            if (
                (in_array('sms', $channels, true) || in_array('whatsapp', $channels, true))
                && !$request->filled('phone_number')
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required to test SMS/WhatsApp channels.',
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings   = SystemSetting::first();
            $systemName = $settings->system_name ?? config('app.name', 'System');
            $testMessage = "🔔 This is a test notification from {$systemName}. Time: " . now()->format('Y-m-d H:i:s');

            $flags   = $this->resolveProviderFlags();
            $results = [];

            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $results['email'] = $this->testEmailChannel($request->input('email'), $testMessage);
                        break;

                    case 'sms':
                        if (!$flags['smsProviderConfigured']) {
                            $results['sms'] = [
                                'success' => false,
                                'message' => 'No SMS provider configured',
                                'channel' => 'sms',
                            ];
                            break;
                        }
                        $results['sms'] = $this->testSmsChannel($request->input('phone_number'), $testMessage);
                        break;

                    case 'whatsapp':
                        if (!$flags['whatsappProviderConfigured']) {
                            $results['whatsapp'] = [
                                'success' => false,
                                'message' => 'No WhatsApp provider configured',
                                'channel' => 'whatsapp',
                            ];
                            break;
                        }
                        $results['whatsapp'] = $this->testWhatsAppChannel($request->input('phone_number'), $testMessage);
                        break;
                }
            }

            $allSuccessful = collect($results)->every(fn($r) => $r['success'] ?? false);

            return response()->json([
                'success' => $allSuccessful,
                'message' => $allSuccessful
                    ? 'All channels tested successfully'
                    : 'Some channels failed',
                'results' => $results,
                'channels_tested' => $channels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to test notification channels: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to test notification channels: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | INVOICE / REMINDER SETTINGS
     * ============================================================ */

    public function getInvoiceSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->getInvoiceGenerationStatus($settings),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get invoice settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice settings',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function toggleAutoInvoiceGeneration(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_toggle_auto_invoice_generation');

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldStatus = (bool) $settings->auto_generate_invoices;
            $newStatus = (bool) $request->enabled;

            $settings->auto_generate_invoices = $newStatus;
            $settings->updated_by = $user->id;
            $settings->save();

            $this->clearPublicSettingsCache();

            Log::info('Auto invoice generation toggled via API', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'toggled_by' => $user->id,
            ]);

            if ($oldStatus !== $newStatus) {
                $this->notifyAboutInvoiceSettingChange($settings, [
                    'auto_generate_invoices' => $oldStatus,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $newStatus
                    ? 'Auto invoice generation enabled successfully'
                    : 'Auto invoice generation disabled successfully',
                'data' => [
                    'enabled' => $newStatus,
                    'next_generation_date' => $settings->getNextInvoiceGenerationDate()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle auto invoice generation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function updateReminderSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_update_reminder_settings');

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'days_before' => 'required|integer|min:1|max:30',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldEnabled = (bool) $settings->send_payment_reminders;
            $oldDays    = (int) $settings->reminder_days_before;
            $newEnabled = (bool) $request->enabled;
            $newDays    = (int) $request->days_before;

            $settings->send_payment_reminders = $newEnabled;
            $settings->reminder_days_before   = $newDays;
            $settings->updated_by = $user->id;
            $settings->save();

            $this->clearPublicSettingsCache();

            Log::info('Reminder settings updated via API', [
                'old_enabled' => $oldEnabled,
                'new_enabled' => $newEnabled,
                'old_days'    => $oldDays,
                'new_days'    => $newDays,
                'updated_by'  => $user->id,
            ]);

            if ($oldEnabled !== $newEnabled || $oldDays !== $newDays) {
                $this->notifyAboutReminderSettingChange($settings, [
                    'send_payment_reminders' => $oldEnabled,
                    'reminder_days_before'   => $oldDays,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $newEnabled
                    ? "Reminders enabled for {$newDays} days before due date"
                    : 'Reminders disabled',
                'data' => [
                    'enabled' => $newEnabled,
                    'days_before' => $newDays,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update reminder settings via API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update reminder settings',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function validateInvoiceSettings(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $validator = Validator::make($request->all(), [
                'auto_generate_invoices' => 'required|boolean',
                'send_payment_reminders' => 'required|boolean',
                'reminder_days_before' => 'required|integer|min:1|max:30',
                'grace_period_days' => 'required|integer|min:0|max:30',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'valid' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $warnings = [];

            if ($data['send_payment_reminders'] && $data['reminder_days_before'] > $data['grace_period_days']) {
                $warnings[] = "Reminder days ({$data['reminder_days_before']}) are greater than grace period ({$data['grace_period_days']}). Some reminders may be sent after invoices are already overdue.";
            }

            return response()->json([
                'valid' => true,
                'warnings' => $warnings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to validate invoice settings: ' . $e->getMessage());

            return response()->json([
                'valid' => false,
                'errors' => ['general' => [$e->getMessage()]],
            ], 500);
        }
    }

    /* ============================================================
     | REGISTRATION
     * ============================================================ */

    public function getRegistrationStatus()
    {
        try {
            $settings = SystemSetting::first();

            if (!$settings) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'registration_allowed' => true,
                        'disabled_message' => null,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'registration_allowed' => $this->isRegistrationAllowed($settings),
                    'disabled_message' => $this->getRegistrationDisabledMessage($settings),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get registration status: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => [
                    'registration_allowed' => true,
                    'disabled_message' => null,
                ],
            ]);
        }
    }

    public function toggleRegistration(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated. Please login.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_toggle_registration');

            $validator = Validator::make($request->all(), [
                'allow_registration' => 'required|boolean',
                'registration_disabled_message' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found. Please configure the system first.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldStatus = (bool) ($settings->allow_registration ?? true);

            $settings->allow_registration = (bool) $request->allow_registration;
            if ($request->filled('registration_disabled_message')) {
                $settings->registration_disabled_message = $request->registration_disabled_message;
            }
            $settings->updated_by = $user->id;
            $settings->save();

            $this->clearPublicSettingsCache();

            Log::info('Registration toggled via API', [
                'old_status' => $oldStatus,
                'new_status' => $settings->allow_registration,
                'toggled_by' => $user->id,
                'toggled_by_name' => $user->name,
            ]);

            if ($oldStatus !== (bool) $settings->allow_registration) {
                $this->notifySuperAdminsAboutRegistrationToggle($settings, $user, $oldStatus);
            }

            return response()->json([
                'success' => true,
                'message' => $settings->allow_registration
                    ? 'Registration enabled successfully'
                    : 'Registration disabled successfully',
                'data' => [
                    'registration_allowed' => (bool) $settings->allow_registration,
                    'disabled_message' => $settings->registration_disabled_message,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle registration via API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle registration: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | PAYMENT GATEWAYS
     * ============================================================ */

    public function getPaymentGateways(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();

            return response()->json([
                'success' => true,
                'data' => [
                    'gateways' => [
                        'expresspay' => [
                            'enabled' => (bool) ($settings->enable_expresspay ?? false),
                            'configured' => (bool) ($paymentConfiguration['expresspay'] ?? false),
                        ],
                        'hubtel' => [
                            'enabled' => (bool) ($settings->enable_hubtel ?? false),
                            'configured' => (bool) ($paymentConfiguration['hubtel'] ?? false),
                        ],
                        'paystack' => [
                            'enabled' => (bool) ($settings->enable_paystack ?? false),
                            'configured' => (bool) ($paymentConfiguration['paystack'] ?? false),
                        ],
                        'flutterwave' => [
                            'enabled' => (bool) ($settings->enable_flutterwave ?? false),
                            'configured' => (bool) ($paymentConfiguration['flutterwave'] ?? false),
                        ],
                    ],
                    'summary' => $paymentConfiguration,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment gateways: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment gateways',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function updatePaymentGateways(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_update_payment_gateways');

            $validator = Validator::make($request->all(), [
                'enable_expresspay' => 'nullable|boolean',
                'enable_hubtel' => 'nullable|boolean',
                'enable_paystack' => 'nullable|boolean',
                'enable_flutterwave' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $oldSettings = $settings->toArray();
            $updateData = [];

            foreach ([
                'enable_expresspay',
                'enable_hubtel',
                'enable_paystack',
                'enable_flutterwave',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN);
                }
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected',
                ]);
            }

            if (!$this->wouldKeepAtLeastOneGateway($updateData, $oldSettings)) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one payment gateway must be enabled.',
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $updateData['updated_by'] = $user->id;
            $settings->update($updateData);
            $settings->refresh();

            $this->clearPublicSettingsCache();

            Log::info('Payment gateways updated via API', [
                'updated_by' => $user->id,
                'updates' => array_map(
                    fn($v) => $v ? 'enabled' : 'disabled',
                    array_intersect_key($updateData, array_flip([
                        'enable_expresspay',
                        'enable_hubtel',
                        'enable_paystack',
                        'enable_flutterwave',
                    ]))
                ),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment gateways updated successfully',
                'data' => [
                    'gateways' => [
                        'expresspay' => (bool) $settings->enable_expresspay,
                        'hubtel' => (bool) $settings->enable_hubtel,
                        'paystack' => (bool) $settings->enable_paystack,
                        'flutterwave' => (bool) $settings->enable_flutterwave,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update payment gateways: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment gateways: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | TESTS
     * ============================================================ */

    public function testSystemConfiguration(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isAdminOrSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Admin access required.');
            }

            $validator = Validator::make($request->all(), [
                'test_type' => 'required|in:email,payments,all,invoice',
                'queue' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $testType  = $request->test_type;
            $queueTest = $request->boolean('queue', false);

            if ($queueTest && config('queue.default') !== 'sync') {
                TestSystemConnection::dispatch($testType, $user->id);

                return response()->json([
                    'success' => true,
                    'message' => 'System configuration test queued for background processing',
                    'test_type' => $testType,
                    'queued' => true,
                    'timestamp' => now()->toISOString(),
                ]);
            }

            Artisan::call('config:clear');
            $result = $this->runSystemConfigurationTest($testType);

            $this->notifySuperAdminsAboutTestResults($result, $testType, $user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test passed' : 'Test failed',
                'test_type' => $testType,
                'details' => $result['details'] ?? [],
                'error_code' => $result['error_code'] ?? null,
                'timestamp' => now()->toISOString(),
                'queued' => false,
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('System configuration test failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function testReminderSystem(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Only super admins can test the reminder system');
            }

            $user->notify(new \App\Notifications\SystemSettingsReminderNotification('test', 0));

            Log::info('Test reminder sent to super admin', [
                'admin_id' => $user->id,
                'admin_email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test reminder sent successfully. Check your notifications.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send test reminder: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test reminder: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/test-notification-system
     * Fires an in-app test notification to the caller.
     */
    public function testNotificationSystem(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $settings = SystemSetting::first();

            $testData = [
                'title' => '🔔 Test Notification',
                'message' => 'This is a test notification from the system settings API. Registration: ' .
                             ($this->isRegistrationAllowed($settings) ? 'enabled' : 'disabled') .
                             ', Auto Invoice: ' . (($settings?->auto_generate_invoices) ? 'enabled' : 'disabled') .
                             ', Reminders: ' . (($settings?->send_payment_reminders) ? 'enabled' : 'disabled'),
                'icon' => 'fas fa-bell text-primary',
                'category' => 'test',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 1,
                'data' => [
                    'type' => 'test',
                    'timestamp' => now()->toISOString(),
                    'notification_type' => 'in_app_only',
                ],
            ];

            $this->notifyUserWithData($user, $testData);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent. Check your notifications panel.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send test notification: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | ENV SYNC DIAGNOSTICS
     * ============================================================ */

    /**
     * GET /api/v1/admin/system-settings/update-status
     * Returns whether the DB settings have been propagated to .env.
     */
    public function updateStatus(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isAdminOrSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Admin access required.');
            }

            $settings = SystemSetting::first();
            $envAppName = env('APP_NAME');
            $dbAppName  = $settings?->system_name;

            $done = $dbAppName && $envAppName &&
                trim($envAppName, " \t\n\r\0\x0B\"'") === trim($dbAppName, " \t\n\r\0\x0B\"'");

            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'system_configuration')
                ->count();

            $failedJobs = \DB::table('failed_jobs')
                ->where('queue', 'system_configuration')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'done'         => (bool) $done,
                    'app_name'     => $envAppName,
                    'db_name'      => $dbAppName,
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs'  => $failedJobs,
                    'queue_driver' => config('queue.default'),
                    'checked_at'   => now()->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'done'  => false,
                'error' => $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/system-settings/retry-env-update
     */
    public function retryEnvUpdate(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }
            if (!$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

            // ✅ BILLING: block write when system billing is overdue
            $this->assertBillingAllowsWrite('api_retry_env_update');

            $settings = SystemSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'System settings not found.',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ], 404);
            }

            $envData = [
                'APP_NAME'          => $settings->system_name,
                'APP_SHORT_NAME'    => $settings->system_short_name,
                'MAIL_FROM_ADDRESS' => $settings->system_email,
            ];

            if (!empty($settings->sms_sender_id)) {
                $envData['DEFAULT_SMS_SENDER_ID'] = $settings->sms_sender_id;
            }

            UpdateSystemConfiguration::dispatch(
                $settings,
                $envData,
                $user->id,
                'manual_retry'
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuration update queued for background processing.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue configuration update: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /* ============================================================
     | ENV PROPAGATION HELPERS
     * ============================================================ */

    protected function queueSystemConfigurationUpdate($settings, $requestData, $action = 'update'): array
    {
        try {
            $envData = $this->getSystemEnvData($settings, $requestData, $action);

            if (empty($envData)) {
                return [
                    'success' => true,
                    'message' => 'No environment variables required updating',
                    'method'  => 'noop',
                ];
            }

            if (config('queue.default') === 'sync') {
                dispatch(function () use ($settings, $envData, $action) {
                    (new UpdateSystemConfiguration(
                        $settings,
                        $envData,
                        auth()->id(),
                        $action
                    ))->handle();
                })->afterResponse();

                return [
                    'success' => true,
                    'message' => 'System configuration update scheduled after response',
                    'method'  => 'after_response',
                ];
            }

            UpdateSystemConfiguration::dispatch(
                $settings,
                $envData,
                auth()->id(),
                $action
            );

            return [
                'success' => true,
                'message' => 'System configuration update queued successfully',
                'method'  => 'queue',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch system configuration update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to schedule system configuration update: ' . $e->getMessage(),
            ];
        }
    }

    protected function getSystemEnvData($settings, $requestData, $action): array
    {
        $envData = [];

        $systemName = $requestData['system_name']
            ?? $settings->system_name
            ?? config('app.name')
            ?? 'Property Management System';

        $systemShortName = $requestData['system_short_name']
            ?? $settings->system_short_name
            ?? Str::slug($systemName);

        if (!empty($systemName)) {
            $envData['APP_NAME']       = $systemName;
            $envData['APP_SHORT_NAME'] = $systemShortName;
        }

        if (isset($requestData['system_email']) || isset($settings->system_email)) {
            $envData['MAIL_FROM_ADDRESS'] = $requestData['system_email'] ?? $settings->system_email;

            if (isset($requestData['system_email_password']) && !empty($requestData['system_email_password'])) {
                $envData['MAIL_PASSWORD'] = $requestData['system_email_password'];
            }
        }

        if (!empty($settings->sms_sender_id)) {
            $envData['DEFAULT_SMS_SENDER_ID'] = $settings->sms_sender_id;
        }

        return $envData;
    }

    /* ============================================================
     | CHANGE TRACKING + NOTIFICATION
     * ============================================================ */

    protected function trackAndNotifyChanges(SystemSetting $settings, array $oldSettings, User $user): void
    {
        if ($this->trackNotificationChannelChanges($oldSettings, $settings->toArray())) {
            $this->notifyAboutNotificationChannelChange($settings, $oldSettings);
        }

        if ($this->trackTenantInvoiceChanges($oldSettings, $settings->toArray())) {
            $this->notifyAboutTenantInvoiceChange($settings, $oldSettings);
        }

        if ($this->trackInvoiceSettingsChanges($oldSettings, $settings->toArray())) {
            $this->notifyAboutInvoiceSettingChange($settings, $oldSettings);
        }

        if ($this->trackReminderSettingsChanges($oldSettings, $settings->toArray())) {
            $this->notifyAboutReminderSettingChange($settings, $oldSettings);
        }

        if (($oldSettings['allow_registration'] ?? true) != ($settings->allow_registration ?? true)) {
            $this->notifySuperAdminsAboutRegistrationToggle(
                $settings,
                $user,
                (bool) ($oldSettings['allow_registration'] ?? true)
            );
        }
    }

    private function trackTenantInvoiceChanges(array $oldSettings, array $newSettings): bool
    {
        $tenantFields = [
            'enable_tenant_invoicing',
            'tenant_monthly_dues_amount',
            'tenant_calculation_method',
            'auto_generate_tenant_invoices',
            'send_tenant_payment_reminders',
            'tenant_grace_period_days',
            'tenant_late_payment_percentage',
            'tenant_fixed_penalty_amount',
        ];

        foreach ($tenantFields as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {
                return true;
            }
            if (isset($newSettings[$field]) && !isset($oldSettings[$field])) {
                return true;
            }
            if (isset($oldSettings[$field]) && !isset($newSettings[$field])) {
                return true;
            }
        }

        return false;
    }

    private function trackInvoiceSettingsChanges(array $oldSettings, array $newSettings): bool
    {
        foreach (['auto_generate_invoices'] as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {
                return true;
            }
        }

        return false;
    }

    private function trackReminderSettingsChanges(array $oldSettings, array $newSettings): bool
    {
        foreach (['send_payment_reminders', 'reminder_days_before'] as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {
                return true;
            }
        }

        return false;
    }

    protected function trackNotificationChannelChanges(array $oldSettings, array $newSettings): bool
    {
        $channelFields = array_merge(self::CHANNEL_FIELDS, [
            'sms_notifications_enabled',
            'sms_reminder_enabled',
            'sms_payment_confirmation_enabled',
            'sms_daily_limit_per_user',
            'sms_hourly_limit_per_user',
            'enable_whatsapp_notifications',
            'whatsapp_reminder_enabled',
        ]);

        foreach ($channelFields as $field) {
            $oldValue = $oldSettings[$field] ?? null;
            $newValue = $newSettings[$field] ?? null;

            if (is_array($oldValue)) $oldValue = json_encode($oldValue);
            if (is_array($newValue)) $newValue = json_encode($newValue);

            if ($oldValue != $newValue) {
                return true;
            }
        }

        return false;
    }

    protected function notifyAboutNotificationChannelChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldInvoiceChannels = $oldSettings['invoice_notification_channels'] ?? ['email'];
            $newInvoiceChannels = $settings->invoice_notification_channels ?? ['email'];
            $oldSmsEnabled      = $oldSettings['sms_notifications_enabled'] ?? false;
            $newSmsEnabled      = $settings->sms_notifications_enabled ?? false;
            $oldWhatsAppEnabled = $oldSettings['enable_whatsapp_notifications'] ?? false;
            $newWhatsAppEnabled = $settings->enable_whatsapp_notifications ?? false;

            $changes = [];

            if ($oldInvoiceChannels != $newInvoiceChannels) {
                $changes[] = "Invoice notifications: " . $this->formatChannelSummary($oldInvoiceChannels) .
                    " → " . $this->formatChannelSummary($newInvoiceChannels);
            }
            if ($oldSmsEnabled != $newSmsEnabled) {
                $changes[] = "SMS notifications: " . ($oldSmsEnabled ? 'enabled' : 'disabled') .
                    " → " . ($newSmsEnabled ? 'enabled' : 'disabled');
            }
            if ($oldWhatsAppEnabled != $newWhatsAppEnabled) {
                $changes[] = "WhatsApp notifications: " . ($oldWhatsAppEnabled ? 'enabled' : 'disabled') .
                    " → " . ($newWhatsAppEnabled ? 'enabled' : 'disabled');
            }

            if (empty($changes)) {
                return;
            }

            $notificationData = [
                'title' => '📢 Notification Channels Updated',
                'message' => "Notification channels have been updated. Changes: " . implode('; ', $changes),
                'icon' => 'fas fa-bell text-info',
                'category' => 'notification_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'notification_channel_change',
                    'old_invoice_channels' => $oldInvoiceChannels,
                    'new_invoice_channels' => $newInvoiceChannels,
                    'old_sms_enabled' => $oldSmsEnabled,
                    'new_sms_enabled' => $newSmsEnabled,
                    'old_whatsapp_enabled' => $oldWhatsAppEnabled,
                    'new_whatsapp_enabled' => $newWhatsAppEnabled,
                    'changed_by' => auth()->id(),
                    'timestamp' => now()->toISOString(),
                    'notification_type' => 'in_app_only',
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify about notification channel change: ' . $e->getMessage());
        }
    }

    protected function notifyAboutTenantInvoiceChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldEnabled = $oldSettings['enable_tenant_invoicing'] ?? false;
            $newEnabled = (bool) $settings->enable_tenant_invoicing;

            $title = $newEnabled ? '👥 Tenant Invoicing Enabled' : '👥 Tenant Invoicing Disabled';
            $icon  = $newEnabled ? 'fas fa-users text-info' : 'fas fa-users-slash text-warning';

            $message = "Tenant invoicing has been " . ($newEnabled ? 'enabled' : 'disabled') . ". ";

            if ($newEnabled) {
                $amount = method_exists($settings, 'formatAmount')
                    ? $settings->formatAmount($settings->tenant_monthly_dues_amount ?? 0)
                    : ($settings->tenant_monthly_dues_amount ?? 0);
                $method = method_exists($settings, 'getTenantCalculationMethodText')
                    ? $settings->getTenantCalculationMethodText()
                    : ($settings->tenant_calculation_method ?? 'fixed');
                $message .= "Monthly dues: {$amount}, Calculation: {$method}.";
            }

            $notificationData = [
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'category' => 'tenant_invoice_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'tenant_invoice_change',
                    'old_enabled' => $oldEnabled,
                    'new_enabled' => $newEnabled,
                    'old_amount' => $oldSettings['tenant_monthly_dues_amount'] ?? 0,
                    'new_amount' => $settings->tenant_monthly_dues_amount,
                    'old_method' => $oldSettings['tenant_calculation_method'] ?? 'fixed',
                    'new_method' => $settings->tenant_calculation_method,
                    'changed_by' => auth()->id(),
                    'timestamp' => now()->toISOString(),
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify about tenant invoice change: ' . $e->getMessage());
        }
    }

    protected function notifyAboutInvoiceSettingChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldStatus = $oldSettings['auto_generate_invoices'] ?? false;
            $newStatus = (bool) $settings->auto_generate_invoices;

            if ((bool) $oldStatus === $newStatus) {
                return;
            }

            $statusText = $newStatus ? 'enabled' : 'disabled';
            $icon = $newStatus ? 'fas fa-play-circle text-success' : 'fas fa-stop-circle text-warning';

            $notificationData = [
                'title' => $newStatus
                    ? '⚙️ Auto Invoice Generation Enabled'
                    : '⚙️ Auto Invoice Generation Disabled',
                'message' => "Auto invoice generation has been {$statusText}. " .
                    ($newStatus
                        ? "Invoices will now be generated automatically on the scheduled date."
                        : "Invoices will only be generated manually."),
                'icon' => $icon,
                'category' => 'invoice_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'invoice_setting_change',
                    'setting' => 'auto_generate_invoices',
                    'old_value' => $oldStatus,
                    'new_value' => $newStatus,
                    'changed_by' => auth()->id(),
                    'timestamp' => now()->toISOString(),
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify about invoice setting change: ' . $e->getMessage());
        }
    }

    protected function notifyAboutReminderSettingChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldEnabled = $oldSettings['send_payment_reminders'] ?? false;
            $newEnabled = (bool) $settings->send_payment_reminders;
            $oldDays    = $oldSettings['reminder_days_before'] ?? 7;
            $newDays    = (int) $settings->reminder_days_before;

            if ((bool) $oldEnabled === $newEnabled && $oldDays === $newDays) {
                return;
            }

            $message = "";
            if ((bool) $oldEnabled !== $newEnabled) {
                $statusText = $newEnabled ? 'enabled' : 'disabled';
                $message = "Payment reminders have been {$statusText}. ";
            }

            if ($oldDays !== $newDays) {
                $message .= "Reminder days changed from {$oldDays} to {$newDays} days before due date.";
            }

            $icon = $newEnabled ? 'fas fa-bell text-success' : 'fas fa-bell-slash text-warning';

            $notificationData = [
                'title' => $newEnabled ? '🔔 Payment Reminders Updated' : '🔕 Payment Reminders Updated',
                'message' => $message,
                'icon' => $icon,
                'category' => 'reminder_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'reminder_setting_change',
                    'old_enabled' => $oldEnabled,
                    'new_enabled' => $newEnabled,
                    'old_days' => $oldDays,
                    'new_days' => $newDays,
                    'changed_by' => auth()->id(),
                    'timestamp' => now()->toISOString(),
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify about reminder setting change: ' . $e->getMessage());
        }
    }

    protected function notifySuperAdminsAboutRegistrationToggle(SystemSetting $settings, User $toggler, bool $oldStatus): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $newStatus = (bool) $settings->allow_registration;
            $action    = $newStatus ? 'enabled' : 'disabled';

            $notificationData = [
                'title' => $newStatus ? '🔓 Registration Enabled' : '🔒 Registration Disabled',
                'message' => "Registration has been {$action} by {$toggler->name}." .
                    ($newStatus ? '' : ' New registrations are now blocked.'),
                'icon' => $newStatus ? 'fas fa-unlock text-success' : 'fas fa-lock text-danger',
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'registration_toggle',
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'action' => $action,
                    'toggled_by' => $toggler->id,
                    'toggled_by_name' => $toggler->name,
                    'timestamp' => now()->toISOString(),
                    'disabled_message' => $settings->registration_disabled_message,
                    'notification_type' => 'in_app_only',
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify super admins about registration toggle: ' . $e->getMessage());
        }
    }

    protected function notifySuperAdminsAboutTestResults(array $testResults, string $testType, User $tester): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title' => $testResults['success'] ? '✅ System Test Passed' : '❌ System Test Failed',
                'message' => "System configuration test ({$testType}) completed by {$tester->name}. " . ($testResults['message'] ?? ''),
                'icon' => $testResults['success'] ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger',
                'category' => 'system_tests',
                'action_url' => route('admin.system-settings.index'),
                'priority' => $testResults['success'] ? 1 : 2,
                'data' => [
                    'type' => 'system_test_completed',
                    'test_type' => $testType,
                    'success' => $testResults['success'],
                    'tester_id' => $tester->id,
                    'tester_name' => $tester->name,
                    'timestamp' => now()->toISOString(),
                    'details' => $testResults['details'] ?? [],
                    'error_code' => $testResults['error_code'] ?? null,
                    'notification_type' => 'in_app_only',
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify super admins about test results: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | SETTINGS REMINDER CLEANUP
     * ============================================================ */

    protected function clearSettingsReminders(): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();

            foreach ($superAdmins as $admin) {
                Cache::forget("user_{$admin->id}_last_settings_reminder");
                Cache::forget("user_{$admin->id}_last_reminder_type");

                if (session()->has("settings_reminder_{$admin->id}")) {
                    session()->forget("settings_reminder_{$admin->id}");
                }
            }

            Cache::forget('last_settings_reminder_sent');
            Cache::forget('settings_reminder_active');

            if (session()->has('pending_system_update')) {
                session()->forget('pending_system_update');
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear settings reminders: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | HELPERS
     * ============================================================ */

    protected function unauthorizedResponse(string $message = 'Unauthenticated.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHENTICATED',
        ], 401);
    }

    protected function forbiddenResponse(string $message = 'Unauthorized.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 403);
    }

    protected function clearPublicSettingsCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    private function wouldKeepAtLeastOneGateway(array $updateData, array $oldSettings): bool
    {
        $gateways = [
            'enable_expresspay',
            'enable_hubtel',
            'enable_paystack',
            'enable_flutterwave',
        ];

        $enabledCount = 0;
        foreach ($gateways as $field) {
            $value = array_key_exists($field, $updateData)
                ? (bool) $updateData[$field]
                : (bool) ($oldSettings[$field] ?? false);
            if ($value) {
                $enabledCount++;
            }
        }

        return $enabledCount > 0;
    }

    private function getActiveSuperAdmins()
    {
        return User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();
    }

    private function isRegistrationAllowed($settings): bool
    {
        if (!$settings) {
            return true;
        }
        if (method_exists($settings, 'isRegistrationAllowed')) {
            return (bool) $settings->isRegistrationAllowed();
        }
        return (bool) ($settings->allow_registration ?? true);
    }

    private function getRegistrationDisabledMessage($settings): ?string
    {
        if (!$settings) {
            return null;
        }
        if (method_exists($settings, 'getRegistrationDisabledMessage')) {
            return $settings->getRegistrationDisabledMessage();
        }
        return $settings->registration_disabled_message ?? null;
    }

    protected function getNotificationChannelStatus(SystemSetting $settings): array
    {
        return [
            'invoice_notification_channels' => $settings->invoice_notification_channels ?? ['email'],
            'invoice_notification_summary' => $this->formatChannelSummary($settings->invoice_notification_channels ?? ['email']),
            'payment_reminder_channels' => $settings->payment_reminder_channels ?? ['email'],
            'payment_reminder_summary' => $this->formatChannelSummary($settings->payment_reminder_channels ?? ['email']),
            'overdue_notification_channels' => $settings->overdue_notification_channels ?? ['email', 'sms'],
            'overdue_notification_summary' => $this->formatChannelSummary($settings->overdue_notification_channels ?? ['email', 'sms']),
            'payment_confirmation_channels' => $settings->payment_confirmation_channels ?? ['email'],
            'payment_confirmation_summary' => $this->formatChannelSummary($settings->payment_confirmation_channels ?? ['email']),
            'sms_notifications_enabled' => (bool) ($settings->sms_notifications_enabled ?? false),
            'sms_reminder_enabled' => (bool) ($settings->sms_reminder_enabled ?? false),
            'sms_payment_confirmation_enabled' => (bool) ($settings->sms_payment_confirmation_enabled ?? false),
            'sms_daily_limit_per_user' => $settings->sms_daily_limit_per_user ?? 10,
            'sms_hourly_limit_per_user' => $settings->sms_hourly_limit_per_user ?? 3,
            'enable_whatsapp_notifications' => (bool) ($settings->enable_whatsapp_notifications ?? false),
            'whatsapp_reminder_enabled' => (bool) ($settings->whatsapp_reminder_enabled ?? false),
            'whatsapp_provider' => $settings->whatsapp_provider ?? null,
            'email_notifications_enabled' => true,
        ];
    }

    protected function formatChannelSummary(array $channels): string
    {
        $channelNames = [
            'email' => '📧 Email',
            'sms' => '📱 SMS',
            'whatsapp' => '💬 WhatsApp',
        ];

        $names = array_map(
            fn($channel) => $channelNames[$channel] ?? $channel,
            $channels
        );

        return implode(' + ', $names);
    }

    private function getInvoiceGenerationStatus(SystemSetting $settings): array
    {
        $nextMonth = now()->addMonth();
        $dueDate   = $settings->calculateDueDateForPeriod($nextMonth);

        $readiness = $settings->isReadyForAutoInvoiceGeneration();

        return [
            'auto_generate_enabled' => $settings->isAutoInvoiceGenerationEnabled(),
            'next_generation_month' => $nextMonth->format('F Y'),
            'next_generation_date' => $settings->getNextInvoiceGenerationDate()->format('F j, Y'),
            'next_due_date' => $dueDate->format('F j, Y'),
            'reminders_enabled' => $settings->shouldSendPaymentReminders(),
            'reminder_days' => $settings->getReminderDaysBefore(),
            'grace_period' => $settings->grace_period_days,
            'is_ready' => $readiness['ready'] ?? false,
            'readiness_issues' => $readiness['issues'] ?? [],
        ];
    }

    /* ============================================================
     | VALIDATION + BUILD UPDATE DATA
     * ============================================================ */

    private function validateUpdateRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'system_name' => 'sometimes|string|max:255',
            'system_short_name' => 'sometimes|string|max:50|alpha_dash',
            'system_email' => 'sometimes|email|max:255',
            'system_email_password' => 'sometimes|nullable|string|min:0',
            'system_phone' => 'sometimes|string|max:20',
            'system_address' => 'sometimes|nullable|string|max:500',

            'system_logo' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_logo' => 'sometimes|boolean',
            'system_favicon' => 'sometimes|image|mimes:ico,png,svg,jpg,jpeg,gif|max:100',
            'remove_favicon' => 'sometimes|boolean',

            'sms_sender_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Za-z0-9 _\-]*$/',
            ],

            'currency_code' => 'sometimes|string|size:3',
            'currency_symbol' => 'sometimes|string|max:5',
            'currency_position' => 'sometimes|in:left,right,left_with_space,right_with_space',
            'decimal_places' => 'sometimes|integer|min:0|max:4',

            'enable_expresspay' => 'sometimes|boolean',
            'enable_hubtel' => 'sometimes|boolean',
            'enable_paystack' => 'sometimes|boolean',
            'enable_flutterwave' => 'sometimes|boolean',

            'enable_tenant_invoicing' => 'sometimes|boolean',
            'tenant_monthly_dues_amount' => 'sometimes|nullable|numeric|min:0',
            'tenant_calculation_method' => 'sometimes|nullable|in:fixed,per_property_unit',
            'auto_generate_tenant_invoices' => 'sometimes|boolean',
            'send_tenant_payment_reminders' => 'sometimes|boolean',
            'tenant_grace_period_days' => 'sometimes|nullable|integer|min:0|max:30',
            'tenant_late_payment_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'tenant_fixed_penalty_amount' => 'sometimes|nullable|numeric|min:0',

            'invoice_notification_channels' => 'sometimes|array',
            'invoice_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_reminder_channels' => 'sometimes|array',
            'payment_reminder_channels.*' => 'in:email,sms,whatsapp',
            'overdue_notification_channels' => 'sometimes|array',
            'overdue_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_confirmation_channels' => 'sometimes|array',
            'payment_confirmation_channels.*' => 'in:email,sms,whatsapp',

            'sms_notifications_enabled' => 'sometimes|boolean',
            'sms_reminder_enabled' => 'sometimes|boolean',
            'sms_payment_confirmation_enabled' => 'sometimes|boolean',
            'sms_daily_limit_per_user' => 'sometimes|integer|min:1|max:100',
            'sms_hourly_limit_per_user' => 'sometimes|integer|min:1|max:20',

            'enable_whatsapp_notifications' => 'sometimes|boolean',
            'whatsapp_reminder_enabled' => 'sometimes|boolean',
            'whatsapp_provider' => 'sometimes|nullable|in:twilio,vonage,custom',

            'monthly_dues_amount' => 'sometimes|numeric|min:0',
            'calculation_method' => 'sometimes|in:fixed,per_property',
            'per_property_amount' => 'sometimes|nullable|numeric|min:0',
            'grace_period_days' => 'sometimes|integer|min:0|max:30',
            'late_payment_percentage' => 'sometimes|numeric|min:0|max:100',
            'fixed_penalty_amount' => 'sometimes|nullable|numeric|min:0',
            'auto_generate_invoices' => 'sometimes|boolean',
            'send_payment_reminders' => 'sometimes|boolean',
            'reminder_days_before' => 'sometimes|integer|min:1|max:30',
            'enable_bulk_payments' => 'sometimes|boolean',
            'max_bulk_months' => 'sometimes|integer|min:1|max:12',
            'bulk_payment_discount' => 'sometimes|nullable|numeric|min:0|max:100',

            'allow_registration' => 'sometimes|boolean',
            'registration_disabled_message' => 'sometimes|nullable|string|max:500',
            'allow_offline_payment' => 'sometimes|boolean',
        ], [
            'system_short_name.alpha_dash' => 'System short name can only contain letters, numbers, dashes and underscores.',
            'sms_sender_id.max'   => 'SMS Sender ID cannot exceed 11 characters.',
            'sms_sender_id.regex' => 'SMS Sender ID may only contain letters, numbers, spaces, hyphens, and underscores.',
        ]);
    }

    private function buildUpdateData(Request $request): array
    {
        $updateData = [];

        foreach ($this->getUpdatableFields() as $field) {
            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if ($this->isBooleanField($field)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            if (in_array($field, self::CHANNEL_FIELDS, true)) {
                $value = $this->normalizeChannelArray($value);
            }

            $updateData[$field] = $value;
        }

        return $updateData;
    }

    private function getUpdatableFields(): array
    {
        return [
            'system_name',
            'system_short_name',
            'system_email',
            'system_phone',
            'system_address',

            'currency_code',
            'currency_symbol',
            'currency_position',
            'decimal_places',

            'enable_expresspay',
            'enable_hubtel',
            'enable_paystack',
            'enable_flutterwave',

            'enable_tenant_invoicing',
            'tenant_monthly_dues_amount',
            'tenant_calculation_method',
            'auto_generate_tenant_invoices',
            'send_tenant_payment_reminders',
            'tenant_grace_period_days',
            'tenant_late_payment_percentage',
            'tenant_fixed_penalty_amount',

            'invoice_notification_channels',
            'payment_reminder_channels',
            'overdue_notification_channels',
            'payment_confirmation_channels',

            'sms_notifications_enabled',
            'sms_reminder_enabled',
            'sms_payment_confirmation_enabled',
            'sms_daily_limit_per_user',
            'sms_hourly_limit_per_user',

            'enable_whatsapp_notifications',
            'whatsapp_reminder_enabled',
            'whatsapp_provider',

            'monthly_dues_amount',
            'calculation_method',
            'per_property_amount',
            'grace_period_days',
            'late_payment_percentage',
            'fixed_penalty_amount',
            'auto_generate_invoices',
            'send_payment_reminders',
            'reminder_days_before',
            'enable_bulk_payments',
            'max_bulk_months',
            'bulk_payment_discount',
            'allow_registration',
            'registration_disabled_message',
            'allow_offline_payment',
            'sms_sender_id',
        ];
    }

    private function isBooleanField(string $field): bool
    {
        return in_array($field, self::BOOLEAN_FIELDS, true);
    }

    /* ============================================================
     | TEST HELPERS
     * ============================================================ */

    protected function runSystemConfigurationTest(string $testType): array
    {
        try {
            switch ($testType) {
                case 'email':
                    return $this->environmentService->testEmailConfiguration();

                case 'payments':
                    return $this->paymentService->testPaymentConfiguration();

                case 'invoice':
                    return $this->testInvoiceConfiguration();

                case 'all':
                    $emailResults   = $this->environmentService->testEmailConfiguration();
                    $paymentResults = $this->paymentService->testPaymentConfiguration();
                    $invoiceResults = $this->testInvoiceConfiguration();

                    return [
                        'success' => ($emailResults['success'] ?? false)
                            && ($paymentResults['success'] ?? false)
                            && ($invoiceResults['success'] ?? false),
                        'message' => 'Comprehensive system test completed',
                        'details' => [
                            'email' => $emailResults,
                            'payments' => $paymentResults,
                            'invoice' => $invoiceResults,
                        ],
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => 'Invalid test type',
                        'error_code' => 'INVALID_TEST_TYPE',
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Test execution failed: ' . $e->getMessage(),
                'error_code' => 'TEST_EXECUTION_ERROR',
            ];
        }
    }

    protected function testInvoiceConfiguration(): array
    {
        try {
            $settings = SystemSetting::first();
            if (!$settings) {
                return [
                    'success' => false,
                    'message' => 'System settings not found',
                    'error_code' => 'SETTINGS_NOT_FOUND',
                ];
            }

            $issues = [];

            if ($settings->isAutoInvoiceGenerationEnabled()) {
                $readiness = $settings->isReadyForAutoInvoiceGeneration();
                if (!($readiness['ready'] ?? true)) {
                    $issues = array_merge($issues, $readiness['issues'] ?? []);
                }
            }

            if ($settings->shouldSendPaymentReminders()) {
                $days = (int) $settings->reminder_days_before;
                if ($days < 1 || $days > 30) {
                    $issues[] = "Reminder days ({$days}) is outside valid range (1-30)";
                }
            }

            $grace = (int) $settings->grace_period_days;
            if ($grace < 0 || $grace > 30) {
                $issues[] = "Grace period ({$grace}) is outside valid range (0-30)";
            }

            return [
                'success' => empty($issues),
                'message' => empty($issues)
                    ? 'Invoice configuration is valid'
                    : 'Invoice configuration has issues',
                'details' => [
                    'settings' => [
                        'auto_generate_invoices' => $settings->auto_generate_invoices,
                        'send_payment_reminders' => $settings->send_payment_reminders,
                        'reminder_days_before' => $settings->reminder_days_before,
                        'grace_period_days' => $settings->grace_period_days,
                        'late_payment_percentage' => $settings->late_payment_percentage,
                        'fixed_penalty_amount' => $settings->fixed_penalty_amount,
                    ],
                    'issues' => $issues,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Invoice test failed: ' . $e->getMessage(),
                'error_code' => 'INVOICE_TEST_ERROR',
            ];
        }
    }

    protected function testEmailChannel(string $email, string $message): array
    {
        try {
            Mail::raw($message, function ($mail) use ($email) {
                $mail->to($email)
                    ->subject('Test Notification from System Settings')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => 'Test email sent successfully',
                'channel' => 'email',
                'recipient' => $this->maskEmail($email),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Email test failed: ' . $e->getMessage(),
                'channel' => 'email',
            ];
        }
    }

    protected function testSmsChannel(string $phoneNumber, string $message): array
    {
        try {
            $result = $this->smsService->sendWithDefaultProvider($phoneNumber, $message, ['is_test' => true]);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false)
                    ? 'Test SMS sent successfully'
                    : 'SMS test failed: ' . ($result['message'] ?? 'Unknown error'),
                'channel' => 'sms',
                'recipient' => $this->maskPhoneNumber($phoneNumber),
                'details' => $result['details'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SMS test failed: ' . $e->getMessage(),
                'channel' => 'sms',
            ];
        }
    }

    protected function testWhatsAppChannel(string $phoneNumber, string $message): array
    {
        try {
            $settings = SystemSetting::first();

            if (!$settings || !$settings->enable_whatsapp_notifications) {
                return [
                    'success' => false,
                    'message' => 'WhatsApp notifications are disabled in settings',
                    'channel' => 'whatsapp',
                ];
            }

            $provider = $settings->whatsapp_provider ?? 'twilio';

            Log::info('WhatsApp test requested (not yet implemented)', [
                'recipient' => $this->maskPhoneNumber($phoneNumber),
                'provider' => $provider,
            ]);

            return [
                'success' => false,
                'message' => 'WhatsApp integration not fully implemented yet',
                'channel' => 'whatsapp',
                'details' => [
                    'provider' => $provider,
                    'note' => 'WhatsApp integration coming soon',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'WhatsApp test failed: ' . $e->getMessage(),
                'channel' => 'whatsapp',
            ];
        }
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $username = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        if (strlen($username) > 4) {
            $maskedUsername = substr($username, 0, 2) . '****' . substr($username, -2);
        } else {
            $maskedUsername = substr($username, 0, 1) . '***';
        }

        return $maskedUsername . '@' . $domain;
    }

    protected function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 8) {
            return $phoneNumber;
        }

        return substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -4);
    }

    /* ============================================================
     | FAVICON HELPERS
     * ============================================================ */

    protected function handleFaviconUpload($file): string
    {
        try {
            $filename = 'favicon-' . time() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs('favicons', $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to store favicon');
            }

            Log::info('Favicon uploaded successfully via API', [
                'path'      => $path,
                'filename'  => $filename,
                'size'      => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload favicon: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function deleteFaviconFile(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info('Favicon deleted successfully', ['path' => $path]);
                return true;
            }

            Log::warning('Favicon file not found for deletion', ['path' => $path]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete favicon: ' . $e->getMessage(), ['path' => $path]);
            return false;
        }
    }
}