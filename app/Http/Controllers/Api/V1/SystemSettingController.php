<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\EnvironmentConfigService;
use App\Services\SmsService;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SystemSettingController extends Controller
{
    use NotifiesUsers;

    protected $paymentService;
    protected $environmentService;
    protected $smsService;

    /** Cache key for public settings. */
    private const PUBLIC_CACHE_KEY = 'public_system_settings';
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(
        PaymentService $paymentService,
        EnvironmentConfigService $environmentService,
        SmsService $smsService
    ) {
        $this->paymentService = $paymentService;
        $this->environmentService = $environmentService;
        $this->smsService = $smsService;
    }

    // ==================== PUBLIC ENDPOINTS ====================

    /**
     * Get public system settings for mobile app.
     * Does NOT require authentication.
     *
     * GET /api/v1/system-settings
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
                    // System identification
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

                    // Currency
                    'currency_code' => $settings->currency_code ?? 'GHS',
                    'currency_symbol' => $settings->currency_symbol ?? 'GH₵',
                    'currency_position' => $settings->currency_position ?? 'left',
                    'decimal_places' => $settings->decimal_places ?? 2,

                    // Registration
                    'registration_allowed' => $this->isRegistrationAllowed($settings),
                    'registration_disabled_message' => $this->getRegistrationDisabledMessage($settings),

                    // Payment gateways (public visibility)
                    'enabled_gateways' => [
                        'expresspay' => (bool) ($settings->enable_expresspay ?? false),
                        'hubtel' => (bool) ($settings->enable_hubtel ?? false),
                        'paystack' => (bool) ($settings->enable_paystack ?? false),
                        'flutterwave' => (bool) ($settings->enable_flutterwave ?? false),
                    ],

                    // Tenant invoicing (public visibility)
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

                    // Notification channels (public visibility)
                    'notification_channels' => [
                        'invoice' => $settings->invoice_notification_channels ?? ['email'],
                        'reminder' => $settings->payment_reminder_channels ?? ['email'],
                        'overdue' => $settings->overdue_notification_channels ?? ['email', 'sms'],
                        'confirmation' => $settings->payment_confirmation_channels ?? ['email'],
                    ],

                    // SMS
                    'sms' => [
                        'enabled' => (bool) ($settings->sms_notifications_enabled ?? false),
                        'reminder_enabled' => (bool) ($settings->sms_reminder_enabled ?? false),
                        'payment_confirmation_enabled' => (bool) ($settings->sms_payment_confirmation_enabled ?? false),
                        'daily_limit_per_user' => $settings->sms_daily_limit_per_user ?? 10,
                        'hourly_limit_per_user' => $settings->sms_hourly_limit_per_user ?? 3,
                    ],

                    // WhatsApp
                    'whatsapp' => [
                        'enabled' => (bool) ($settings->enable_whatsapp_notifications ?? false),
                        'reminder_enabled' => (bool) ($settings->whatsapp_reminder_enabled ?? false),
                    ],

                    // App version info
                    'app_version' => config('app.version', '1.0.0'),
                    'api_version' => 'v1',
                    'min_app_version' => config('app.min_app_version', '1.0.0'),
                    'force_update' => config('app.force_update', false),
                    'maintenance_mode' => false,
                    'registration_open' => $this->isRegistrationAllowed($settings),
                    'allow_user_registration' => $this->isRegistrationAllowed($settings),
                    'site_name' => $settings->system_name ?? 'Hilltop Estate Management',

                    // Meta — so the client can detect staleness
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

    /**
     * Default settings response used when DB is empty or throws.
     */
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

    // ==================== ADMIN: READ ====================

    /**
     * GET /api/v1/admin/system-settings
     *
     * Full admin-facing payload. Admin or Super Admin.
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

            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            $notificationChannels = $this->getNotificationChannelStatus($settings);
            $smsStatus = $this->smsService->getQuickStatus();
            $invoiceGenerationStatus = $this->getInvoiceGenerationStatus($settings);

            return response()->json([
                'success' => true,
                'data' => [
                    // System identification
                    'system_name' => $settings->system_name,
                    'system_short_name' => $settings->system_short_name,
                    'system_email' => $settings->system_email,
                    'system_phone' => $settings->system_phone,
                    'system_address' => $settings->system_address,
                    'logo_url' => $settings->system_logo
                        ? asset('storage/' . $settings->system_logo)
                        : null,
                    'favicon_url' => $settings->getFaviconUrl(),

                    // Currency
                    'currency_code' => $settings->currency_code,
                    'currency_symbol' => $settings->currency_symbol,
                    'currency_position' => $settings->currency_position,
                    'decimal_places' => $settings->decimal_places,

                    // Payment gateways
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

                    // Tenant invoicing
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

                    // Notification channels
                    'notification_channels' => $notificationChannels,
                    'sms_status' => $smsStatus,
                    'invoice_generation' => $invoiceGenerationStatus,

                    // Legacy dues config (kept for backward compat)
                    'monthly_dues_amount' => $settings->monthly_dues_amount,
                    'calculation_method' => $settings->calculation_method,
                    'per_property_amount' => $settings->per_property_amount,

                    // Legacy payment terms
                    'grace_period_days' => $settings->grace_period_days,
                    'late_payment_percentage' => $settings->late_payment_percentage,
                    'fixed_penalty_amount' => $settings->fixed_penalty_amount,

                    // Legacy system ops
                    'auto_generate_invoices' => (bool) ($settings->auto_generate_invoices ?? false),
                    'send_payment_reminders' => (bool) ($settings->send_payment_reminders ?? false),
                    'reminder_days_before' => $settings->reminder_days_before ?? 7,

                    // Bulk payments
                    'enable_bulk_payments' => (bool) ($settings->enable_bulk_payments ?? false),
                    'max_bulk_months' => $settings->max_bulk_months ?? 6,
                    'bulk_payment_discount' => $settings->bulk_payment_discount ?? 0,

                    // Registration
                    'allow_registration' => (bool) ($settings->allow_registration ?? true),
                    'registration_disabled_message' => $settings->registration_disabled_message,

                    // Audit
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

    // ==================== ADMIN: WRITE ====================

    /**
     * PUT /api/v1/admin/system-settings
     *
     * Super Admin only. Updates any of the whitelisted fields.
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

            // Validate the incoming request against the whitelist + types.
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
            $updateData = $this->buildUpdateData($request);

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

            $updateData['updated_by'] = $user->id;
            $settings->update($updateData);
            $settings->refresh();

            $this->clearPublicSettingsCache();
            $this->trackAndNotifyChanges($settings, $oldSettings, $user);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
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

    // ==================== NOTIFICATION CHANNELS ====================

    /**
     * PUT /api/v1/admin/notification-channels
     */
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

            // Extra cross-field check: hourly must be <= daily.
            $daily = $request->input('sms_daily_limit_per_user');
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
            $updateData = [];

            // Channel arrays
            $channelFields = [
                'invoice_notification_channels',
                'payment_reminder_channels',
                'overdue_notification_channels',
                'payment_confirmation_channels',
            ];

            foreach ($channelFields as $field) {
                if ($request->has($field)) {
                    $channels = $request->input($field, ['email']);
                    if (!is_array($channels)) {
                        $channels = ['email'];
                    }
                    if (!in_array('email', $channels, true)) {
                        $channels[] = 'email';
                    }
                    $updateData[$field] = array_values(array_unique($channels));
                }
            }

            // SMS
            foreach ([
                'sms_notifications_enabled',
                'sms_reminder_enabled',
                'sms_payment_confirmation_enabled',
                'sms_daily_limit_per_user',
                'sms_hourly_limit_per_user',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            // WhatsApp
            foreach ([
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
        } catch (\Exception $e) {
            Log::error('Failed to update notification channels via API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification channels: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * GET /api/v1/admin/notification-channels
     */
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

            return response()->json([
                'success' => true,
                'data' => $this->getNotificationChannelStatus($settings),
                'sms_providers' => $this->smsService->getAllProvidersWithStatus(),
                'sms_configured' => $this->smsService->isConfigured(),
                'sms_quick_status' => $this->smsService->getQuickStatus(),
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

    /**
     * POST /api/v1/admin/test-notification-channels
     */
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

            // Require destination per requested channel.
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

            $settings = SystemSetting::first();
            $systemName = $settings->system_name ?? config('app.name', 'System');
            $testMessage = "🔔 This is a test notification from {$systemName}. Time: " . now()->format('Y-m-d H:i:s');

            $results = [];
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $results['email'] = $this->testEmailChannel($request->input('email'), $testMessage);
                        break;

                    case 'sms':
                        $results['sms'] = $this->testSmsChannel($request->input('phone_number'), $testMessage);
                        break;

                    case 'whatsapp':
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

    // ==================== INVOICE / REMINDER SETTINGS ====================

    /**
     * GET /api/v1/admin/invoice-settings
     */
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

    /**
     * POST /api/v1/admin/toggle-auto-invoice
     */
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

    /**
     * POST /api/v1/admin/reminder-settings
     *
     * Mirrors the admin controller's updateReminderSettings. Was missing.
     */
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
            $oldDays = (int) $settings->reminder_days_before;
            $newEnabled = (bool) $request->enabled;
            $newDays = (int) $request->days_before;

            $settings->send_payment_reminders = $newEnabled;
            $settings->reminder_days_before = $newDays;
            $settings->updated_by = $user->id;
            $settings->save();

            $this->clearPublicSettingsCache();

            Log::info('Reminder settings updated via API', [
                'old_enabled' => $oldEnabled,
                'new_enabled' => $newEnabled,
                'old_days' => $oldDays,
                'new_days' => $newDays,
                'updated_by' => $user->id,
            ]);

            if ($oldEnabled !== $newEnabled || $oldDays !== $newDays) {
                $this->notifyAboutReminderSettingChange($settings, [
                    'send_payment_reminders' => $oldEnabled,
                    'reminder_days_before' => $oldDays,
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

    /**
     * POST /api/v1/admin/validate-invoice-settings
     *
     * Dry-run validation. Mirrors admin controller.
     */
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

    // ==================== REGISTRATION ====================

    /**
     * GET /api/v1/registration-status
     */
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

            // On failure, default to "allowed" so users aren't locked out.
            return response()->json([
                'success' => true,
                'data' => [
                    'registration_allowed' => true,
                    'disabled_message' => null,
                ],
            ]);
        }
    }

    /**
     * POST /api/v1/admin/toggle-registration
     */
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
                $this->notifySuperAdminsAboutRegistrationToggle(
                    $settings,
                    $user,
                    $oldStatus
                );
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

    // ==================== PAYMENT GATEWAYS ====================

    /**
     * GET /api/v1/admin/payment-gateways
     */
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

    /**
     * PUT /api/v1/admin/payment-gateways
     */
    public function updatePaymentGateways(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !$this->isSuperAdmin($user)) {
                return $this->forbiddenResponse('Unauthorized. Super Admin access required.');
            }

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

    // ==================== TESTS ====================

    /**
     * POST /api/v1/admin/test-configuration
     */
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
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }

            $testType = $request->test_type;
            $result = $this->runSystemConfigurationTest($testType);

            $this->notifySuperAdminsAboutTestResults($result, $testType, $user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test passed' : 'Test failed',
                'test_type' => $testType,
                'details' => $result['details'] ?? [],
                'error_code' => $result['error_code'] ?? null,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('System configuration test failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/test-reminder-system
     *
     * Mirrors the admin controller method. Was missing.
     */
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

    // ==================== HELPERS ====================

    private function isAdminOrSuperAdmin(User $user): bool
    {
        return in_array($user->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN], true);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->type === User::TYPE_SUPER_ADMIN;
    }

    private function unauthorizedResponse(string $message = 'Unauthenticated.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHENTICATED',
        ], 401);
    }

    private function forbiddenResponse(string $message = 'Unauthorized.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 403);
    }

    private function clearPublicSettingsCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /**
     * True if enabling/disabling the given gateways still leaves at least
     * one gateway enabled (considering values not in $updateData).
     */
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

    /**
     * Validate the update payload. Only fields in `getUpdatableFields()`
     * are considered; each has a proper rule.
     */
    private function validateUpdateRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            // System identification
            'system_name' => 'sometimes|string|max:255',
            'system_short_name' => 'sometimes|string|max:50|alpha_dash',
            'system_email' => 'sometimes|email|max:255',
            'system_phone' => 'sometimes|string|max:20',
            'system_address' => 'sometimes|nullable|string|max:500',

            // Currency
            'currency_code' => 'sometimes|string|size:3',
            'currency_symbol' => 'sometimes|string|max:5',
            'currency_position' => 'sometimes|in:left,right,left_with_space,right_with_space',
            'decimal_places' => 'sometimes|integer|min:0|max:4',

            // Payment gateways
            'enable_expresspay' => 'sometimes|boolean',
            'enable_hubtel' => 'sometimes|boolean',
            'enable_paystack' => 'sometimes|boolean',
            'enable_flutterwave' => 'sometimes|boolean',

            // Tenant invoicing
            'enable_tenant_invoicing' => 'sometimes|boolean',
            'tenant_monthly_dues_amount' => 'sometimes|nullable|numeric|min:0',
            'tenant_calculation_method' => 'sometimes|nullable|in:fixed,per_property_unit',
            'auto_generate_tenant_invoices' => 'sometimes|boolean',
            'send_tenant_payment_reminders' => 'sometimes|boolean',
            'tenant_grace_period_days' => 'sometimes|nullable|integer|min:0|max:30',
            'tenant_late_payment_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'tenant_fixed_penalty_amount' => 'sometimes|nullable|numeric|min:0',

            // Notification channels
            'invoice_notification_channels' => 'sometimes|array',
            'invoice_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_reminder_channels' => 'sometimes|array',
            'payment_reminder_channels.*' => 'in:email,sms,whatsapp',
            'overdue_notification_channels' => 'sometimes|array',
            'overdue_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_confirmation_channels' => 'sometimes|array',
            'payment_confirmation_channels.*' => 'in:email,sms,whatsapp',

            // SMS
            'sms_notifications_enabled' => 'sometimes|boolean',
            'sms_reminder_enabled' => 'sometimes|boolean',
            'sms_payment_confirmation_enabled' => 'sometimes|boolean',
            'sms_daily_limit_per_user' => 'sometimes|integer|min:1|max:100',
            'sms_hourly_limit_per_user' => 'sometimes|integer|min:1|max:20',

            // WhatsApp
            'enable_whatsapp_notifications' => 'sometimes|boolean',
            'whatsapp_reminder_enabled' => 'sometimes|boolean',
            'whatsapp_provider' => 'sometimes|nullable|in:twilio,vonage,custom',

            // Legacy dues
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

            // Registration
            'allow_registration' => 'sometimes|boolean',
            'registration_disabled_message' => 'sometimes|nullable|string|max:500',
        ]);
    }

    /**
     * Build the $updateData payload from a validated request.
     * Only whitelisted fields are included.
     */
    private function buildUpdateData(Request $request): array
    {
        $updateData = [];

        foreach ($this->getUpdatableFields() as $field) {
            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            // Boolean coercion
            if ($this->isBooleanField($field)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            // Channel arrays: ensure email present, dedupe, reindex.
            if (in_array($field, [
                'invoice_notification_channels',
                'payment_reminder_channels',
                'overdue_notification_channels',
                'payment_confirmation_channels',
            ], true)) {
                if (is_string($value)) {
                    $value = json_decode($value, true) ?: ['email'];
                }
                if (!is_array($value) || empty($value)) {
                    $value = ['email'];
                }
                if (!in_array('email', $value, true)) {
                    $value[] = 'email';
                }
                $value = array_values(array_unique($value));
            }

            $updateData[$field] = $value;
        }

        return $updateData;
    }

    /**
     * Whitelist of fields the API is allowed to update.
     * Mirrors the admin controller's list.
     */
    private function getUpdatableFields(): array
    {
        return [
            // System identification
            'system_name',
            'system_short_name',
            'system_email',
            'system_phone',
            'system_address',

            // Currency
            'currency_code',
            'currency_symbol',
            'currency_position',
            'decimal_places',

            // Payment gateways
            'enable_expresspay',
            'enable_hubtel',
            'enable_paystack',
            'enable_flutterwave',

            // Tenant invoicing
            'enable_tenant_invoicing',
            'tenant_monthly_dues_amount',
            'tenant_calculation_method',
            'auto_generate_tenant_invoices',
            'send_tenant_payment_reminders',
            'tenant_grace_period_days',
            'tenant_late_payment_percentage',
            'tenant_fixed_penalty_amount',

            // Notification channels
            'invoice_notification_channels',
            'payment_reminder_channels',
            'overdue_notification_channels',
            'payment_confirmation_channels',

            // SMS
            'sms_notifications_enabled',
            'sms_reminder_enabled',
            'sms_payment_confirmation_enabled',
            'sms_daily_limit_per_user',
            'sms_hourly_limit_per_user',

            // WhatsApp
            'enable_whatsapp_notifications',
            'whatsapp_reminder_enabled',
            'whatsapp_provider',

            // Legacy / misc
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
        ];
    }

    private function isBooleanField(string $field): bool
    {
        static $booleanFields = [
            'enable_expresspay',
            'enable_hubtel',
            'enable_paystack',
            'enable_flutterwave',
            'enable_tenant_invoicing',
            'auto_generate_tenant_invoices',
            'send_tenant_payment_reminders',
            'sms_notifications_enabled',
            'sms_reminder_enabled',
            'sms_payment_confirmation_enabled',
            'enable_whatsapp_notifications',
            'whatsapp_reminder_enabled',
            'auto_generate_invoices',
            'send_payment_reminders',
            'enable_bulk_payments',
            'allow_registration',
        ];

        return in_array($field, $booleanFields, true);
    }

    // ==================== REGISTRATION HELPERS ====================

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

    // ==================== NOTIFICATION HELPERS ====================

    /**
     * Channel status snapshot used by GET endpoints.
     */
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

    /**
     * Invoice generation status snapshot.
     */
    private function getInvoiceGenerationStatus(SystemSetting $settings): array
    {
        $nextMonth = now()->addMonth();
        $dueDate = $settings->calculateDueDateForPeriod($nextMonth);

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

    /**
     * Detect notification channel changes.
     */
    protected function trackNotificationChannelChanges(array $oldSettings, array $newSettings): bool
    {
        $channelFields = [
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
        ];

        foreach ($channelFields as $field) {
            $oldValue = $oldSettings[$field] ?? null;
            $newValue = $newSettings[$field] ?? null;

            if (is_array($oldValue)) {
                $oldValue = json_encode($oldValue);
            }
            if (is_array($newValue)) {
                $newValue = json_encode($newValue);
            }

            if ($oldValue != $newValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify super admins about notification-channel changes.
     */
    protected function notifyAboutNotificationChannelChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldInvoiceChannels = $oldSettings['invoice_notification_channels'] ?? ['email'];
            $newInvoiceChannels = $settings->invoice_notification_channels ?? ['email'];
            $oldSmsEnabled = $oldSettings['sms_notifications_enabled'] ?? false;
            $newSmsEnabled = $settings->sms_notifications_enabled ?? false;
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

            Log::info('Notification channel change notification sent', [
                'admin_count' => $superAdmins->count(),
                'changes' => $changes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify about notification channel change: ' . $e->getMessage());
        }
    }

    /**
     * Notify super admins about invoice settings change.
     */
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

            Log::info('Invoice setting change notification sent', [
                'admin_count' => $superAdmins->count(),
                'new_status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify about invoice setting change: ' . $e->getMessage());
        }
    }

    /**
     * Notify super admins about reminder settings change.
     */
    protected function notifyAboutReminderSettingChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldEnabled = $oldSettings['send_payment_reminders'] ?? false;
            $newEnabled = (bool) $settings->send_payment_reminders;
            $oldDays = $oldSettings['reminder_days_before'] ?? 7;
            $newDays = (int) $settings->reminder_days_before;

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

            Log::info('Reminder setting change notification sent', [
                'admin_count' => $superAdmins->count(),
                'new_enabled' => $newEnabled,
                'new_days' => $newDays,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify about reminder setting change: ' . $e->getMessage());
        }
    }

    /**
     * Notify super admins about registration toggle.
     */
    protected function notifySuperAdminsAboutRegistrationToggle(SystemSetting $settings, User $toggler, bool $oldStatus): void
    {
        try {
            $superAdmins = $this->getActiveSuperAdmins();
            if ($superAdmins->isEmpty()) {
                return;
            }

            $newStatus = (bool) $settings->allow_registration;
            $action = $newStatus ? 'enabled' : 'disabled';

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

            Log::info('Notified super admins about registration toggle', [
                'admin_count' => $superAdmins->count(),
                'new_status' => $newStatus,
                'toggler_id' => $toggler->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify super admins about registration toggle: ' . $e->getMessage());
        }
    }

    /**
     * Notify super admins about test results.
     */
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

            Log::info('Notified super admins about test results', [
                'admin_count' => $superAdmins->count(),
                'test_type' => $testType,
                'success' => $testResults['success'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify super admins about test results: ' . $e->getMessage());
        }
    }

    /**
     * Track and notify all relevant change categories.
     */
    protected function trackAndNotifyChanges(SystemSetting $settings, array $oldSettings, User $user): void
    {
        if ($this->trackNotificationChannelChanges($oldSettings, $settings->toArray())) {
            $this->notifyAboutNotificationChannelChange($settings, $oldSettings);
        }

        if (($oldSettings['auto_generate_invoices'] ?? false) != ($settings->auto_generate_invoices ?? false)) {
            $this->notifyAboutInvoiceSettingChange($settings, $oldSettings);
        }

        if (
            ($oldSettings['send_payment_reminders'] ?? false) != ($settings->send_payment_reminders ?? false) ||
            ($oldSettings['reminder_days_before'] ?? 7) != ($settings->reminder_days_before ?? 7)
        ) {
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

    /**
     * Cache-friendly super admin lookup.
     */
    private function getActiveSuperAdmins()
    {
        return User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();
    }

    // ==================== TEST HELPERS ====================

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
                    $emailResults = $this->environmentService->testEmailConfiguration();
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
}