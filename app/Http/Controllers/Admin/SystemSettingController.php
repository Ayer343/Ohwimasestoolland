<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Property;
use App\Http\Controllers\Admin\LogoController;
use App\Services\PaymentService;
use App\Services\EnvironmentConfigService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Jobs\UpdateSystemConfiguration;
use App\Jobs\UpdateAppNameConfiguration;
use App\Jobs\TestSystemConnection;
use App\Traits\NotifiesUsers;
use App\Traits\ChecksBillingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SystemSettingController extends Controller
{
    use NotifiesUsers, ChecksBillingAccess;

    /**
     * Single source of truth for boolean/checkbox fields.
     */
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
    ];

    /**
     * Single source of truth for the four notification-channel array fields.
     */
    protected const CHANNEL_FIELDS = [
        'invoice_notification_channels',
        'payment_reminder_channels',
        'overdue_notification_channels',
        'payment_confirmation_channels',
    ];

    protected $paymentService;
    protected $environmentService;
    protected $smsService;
    protected $whatsappService;

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

    protected function userIsDeveloper(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        return $user->hasRole('developer') || (int) $user->type === 5;
    }

    protected function userIsSuperAdmin(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        return $user->hasRole('super-admin') || (int) $user->type === 0;
    }

    protected function userIsPlainAdmin(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) return false;

        return !$this->userIsDeveloper($user)
            && !$this->userIsSuperAdmin($user)
            && ($user->hasRole('admin') || (int) $user->type === 1);
    }

    protected function isRestrictedAdmin(): bool
    {
        return $this->userIsPlainAdmin();
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
                    Log::debug('[SystemSettings] isConfigured() fallback failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SystemSettings] Failed to resolve SMS provider flags', [
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
                    Log::debug('[SystemSettings] WhatsApp isConfigured() fallback failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SystemSettings] Failed to resolve WhatsApp provider flags', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('[SystemSettings] Provider flags resolved', [
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
     | INDEX — read-only, never restricted
     * ============================================================ */
    public function index()
    {
        try {
            if (!SystemSetting::exists()) {
                $this->notifySuperAdminsAboutMissingSettings();

                return redirect()->route('admin.system-settings.create')
                    ->with('info', '⚠️ No system settings found. Please create system settings first to configure your application.');
            }

            $settings = SystemSetting::getSettings();

            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            $emailConfiguration   = $this->environmentService->getMailConfiguration();
            $appConfiguration     = $this->environmentService->getAppConfiguration();

            $invoiceGenerationStatus = $this->getInvoiceGenerationStatus($settings);
            $notificationChannels    = $this->getNotificationChannelStatus($settings);
            $smsStatus               = $this->smsService->getQuickStatus();

            $hasPendingEnvUpdate = session()->has('pending_system_update');
            $pendingEnvUpdate    = session()->get('pending_system_update', null);

            $enabledGateways = [
                'expresspay'  => $settings->enable_expresspay ?? false,
                'hubtel'      => $settings->enable_hubtel ?? false,
                'paystack'    => $settings->enable_paystack ?? false,
                'flutterwave' => $settings->enable_flutterwave ?? false,
            ];

            $providerFlags = $this->resolveProviderFlags();

            return view('admin.system-settings.index', array_merge(
                compact(
                    'settings',
                    'paymentConfiguration',
                    'emailConfiguration',
                    'appConfiguration',
                    'invoiceGenerationStatus',
                    'notificationChannels',
                    'smsStatus',
                    'hasPendingEnvUpdate',
                    'pendingEnvUpdate',
                    'enabledGateways'
                ),
                $providerFlags
            ))->with([
                'system_update_complete' => session('system_update_complete'),
                'system_update_warning'  => session('system_update_warning'),
                'system_update_error'    => session('system_update_error'),
                'system_update_message'  => session('system_update_message'),
                'app_update_complete'    => session('app_update_complete'),
                'app_update_warning'     => session('app_update_warning'),
                'app_update_error'       => session('app_update_error'),
                'app_update_message'     => session('app_update_message'),
                'env_update_complete'    => session('env_update_complete'),
                'env_update_warning'     => session('env_update_warning'),
                'env_update_error'       => session('env_update_error'),
                'env_update_message'     => session('env_update_message'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading system settings: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id'   => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Error loading system settings: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | CREATE — read-only form rendering
     * ============================================================ */
    public function create()
    {
        if ($this->isRestrictedAdmin()) {
            abort(403, 'Only developers and super-admins can create system settings.');
        }

        if (SystemSetting::exists()) {
            return redirect()->route('admin.system-settings.edit')
                ->with('info', 'System settings already exist. You can edit the existing settings.');
        }

        $emailConfiguration = $this->environmentService->getMailConfiguration();
        $appConfiguration   = $this->environmentService->getAppConfiguration();
        $smsStatus          = $this->smsService->getQuickStatus();

        $smsSenderId = config('sms.sender_id');

        $providerFlags = $this->resolveProviderFlags();

        return view('admin.system-settings.create', array_merge(
            compact(
                'emailConfiguration',
                'appConfiguration',
                'smsStatus',
                'smsSenderId'
            ),
            $providerFlags
        ))->with('adminOnly', false);
    }

    /* ============================================================
     | STORE — admin write operation
     * ============================================================ */
    public function store(Request $request)
    {
        if ($this->isRestrictedAdmin()) {
            abort(403, 'Only developers and super-admins can create system settings.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('create_system_settings');

        if (SystemSetting::count() > 0) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', 'System settings already exist. You can only edit the existing settings.');
        }

        $validator = $this->validateSettings($request->all());

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $data = $validator->validated();

            $data = $this->normalizeChannelFields($data, $request);

            $this->assertNotificationChannelsAreAllowed($data);

            $notificationDefaults = [
                'sms_notifications_enabled'         => false,
                'sms_reminder_enabled'              => false,
                'sms_payment_confirmation_enabled'  => false,
                'sms_daily_limit_per_user'          => 10,
                'sms_hourly_limit_per_user'         => 3,
                'enable_whatsapp_notifications'     => false,
                'whatsapp_reminder_enabled'         => false,
            ];

            foreach ($notificationDefaults as $key => $defaultValue) {
                if (!isset($data[$key])) {
                    $data[$key] = $request->input($key, $defaultValue);
                }
            }

            $tenantDefaults = [
                'enable_tenant_invoicing'          => true,
                'tenant_monthly_dues_amount'       => 50.00,
                'tenant_calculation_method'        => 'fixed',
                'auto_generate_tenant_invoices'    => true,
                'send_tenant_payment_reminders'    => true,
                'tenant_grace_period_days'         => 7,
                'tenant_late_payment_percentage'   => 5.00,
                'tenant_fixed_penalty_amount'      => 0.00,
            ];

            foreach ($tenantDefaults as $key => $defaultValue) {
                if (!isset($data[$key])) {
                    $data[$key] = $request->input($key, $defaultValue);
                }
            }

            $data = $this->normalizeBooleanFields($data, $request);

            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            if (empty($data['system_short_name']) && !empty($data['system_name'])) {
                $data['system_short_name'] = $this->generateSystemSlug($data['system_name']);
            } elseif (!empty($data['system_short_name'])) {
                $data['system_short_name'] = $this->generateSystemSlug($data['system_short_name']);
            }

            if (!isset($data['allow_registration'])) {
                $data['allow_registration'] = $request->input('allow_registration', true);
            }

            if (empty($data['registration_disabled_message'])) {
                $data['registration_disabled_message'] = 'New registrations are currently disabled. Please contact the administrator for assistance.';
            }

            if ($request->hasFile('system_logo')) {
                $logoController = app(LogoController::class);
                $data['system_logo'] = $logoController->handleLogoUpload($request->file('system_logo'));
            }

            if ($request->hasFile('system_favicon')) {
                $data['system_favicon'] = $this->handleFaviconUpload($request->file('system_favicon'));
            }

            if (array_key_exists('sms_sender_id', $data)) {
                $data['sms_sender_id'] = filled($data['sms_sender_id'])
                    ? trim((string) $data['sms_sender_id'])
                    : null;
            }

            $settings = SystemSetting::create($data);

            $this->clearSettingsReminders();
            $this->notifySuperAdminsAboutSettingsCreation($settings, auth()->user());

            Log::info('System settings created successfully - reminders cleared', [
                'settings_id'             => $settings->id,
                'created_by'              => auth()->id(),
                'created_by_name'         => auth()->user()->name,
                'system_name'             => $settings->system_name,
                'sms_sender_id'           => $settings->sms_sender_id,
                'sms_enabled'             => $settings->sms_notifications_enabled,
                'whatsapp_enabled'        => $settings->enable_whatsapp_notifications,
                'tenant_invoicing_enabled'=> $settings->enable_tenant_invoicing,
                'auto_generate_invoices'  => $settings->auto_generate_invoices,
                'enabled_gateways'        => [
                    'expresspay'  => $settings->enable_expresspay,
                    'hubtel'      => $settings->enable_hubtel,
                    'paystack'    => $settings->enable_paystack,
                    'flutterwave' => $settings->enable_flutterwave,
                ],
                'has_favicon'             => $settings->hasFavicon(),
                'reminders_cleared'       => true,
                'timestamp'               => now()->setTimezone('UTC')->toISOString()
            ]);

            $systemUpdateResult = $this->queueSystemConfigurationUpdate(
                $settings,
                array_merge($request->all(), [
                    'system_name'       => $settings->system_name,
                    'system_short_name' => $settings->system_short_name,
                    'system_email'      => $settings->system_email,
                ]),
                'create'
            );

            $successMessage = '✅ System settings created successfully!';
            $successMessage .= ' Notification channels configured: ' .
                implode(', ', $settings->invoice_notification_channels) . ' for invoices, ' .
                implode(', ', $settings->payment_reminder_channels) . ' for reminders.';

            if ($settings->hasFavicon()) {
                $successMessage .= ' Favicon uploaded successfully!';
            }

            $successMessage .= ' All pending setup reminders have been cleared.';

            if ($systemUpdateResult['success']) {
                $successMessage .= ' System configuration is being updated in the background!';

                return redirect()->route('admin.system-settings.index')
                    ->with('success', $successMessage)
                    ->with('system_update_status', 'processing')
                    ->with('provider', 'system_configuration')
                    ->with('tenant_invoicing_enabled', $settings->enable_tenant_invoicing ? 'Yes' : 'No')
                    ->with('tenant_dues_amount', $settings->formatAmount($settings->tenant_monthly_dues_amount ?? 0))
                    ->with('notification_channels', $this->getNotificationChannelSummary($settings))
                    ->with('reminders_cleared', true);
            } else {
                Log::warning("Failed to queue system configuration update, falling back to immediate update", [
                    'error' => $systemUpdateResult['message']
                ]);

                return $this->processSystemConfigurationImmediately(
                    $settings,
                    array_merge($request->all(), [
                        'system_name'       => $settings->system_name,
                        'system_short_name' => $settings->system_short_name,
                        'system_email'      => $settings->system_email,
                    ]),
                    'create'
                );
            }

        } catch (ValidationException $ve) {
            return redirect()->back()
                ->withErrors($ve->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error("Failed to create system settings: " . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception'    => $e,
                'trace'        => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', '❌ Error creating system settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /* ============================================================
     | EDIT — read-only form rendering
     * ============================================================ */
    public function edit()
    {
        try {
            $settings           = SystemSetting::getSettings();
            $emailConfiguration = $this->environmentService->getMailConfiguration();
            $appConfiguration   = $this->environmentService->getAppConfiguration();

            $adminOnly = $this->isRestrictedAdmin();

            $providerFlags = $this->resolveProviderFlags();

            return view('admin.system-settings.edit', array_merge(
                compact(
                    'settings',
                    'emailConfiguration',
                    'appConfiguration',
                    'adminOnly'
                ),
                $providerFlags
            ));
        } catch (\Exception $e) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', '❌ Error loading settings for editing: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | UPDATE — role-branched
     * ============================================================ */
    public function update(Request $request)
    {
        try {
            $settings = SystemSetting::getSettings();

            if (!$settings) {
                return redirect()->route('admin.system-settings.create')
                    ->with('error', '❌ System settings not found. Please create them first.');
            }

            // ---------------------------------------------------------
            // RESTRICTED ADMIN — only sms_sender_id is honoured.
            // This IS a write action, but it's the ONE write that a
            // plain admin is allowed to make. It also happens to be
            // safe during an overdue-billing window because it only
            // changes the SMS sender name shown on outbound messages,
            // which keeps communication with landlords/tenants working.
            // ---------------------------------------------------------
            if ($this->isRestrictedAdmin()) {
                return $this->updateSenderIdOnly($request, $settings);
            }

            // ---------------------------------------------------------
            // DEVELOPER / SUPER-ADMIN — full update
            // ---------------------------------------------------------

            // ✅ BILLING: block admin write when system billing is overdue
            $this->assertBillingAllowsWrite('update_system_settings');

            $validator = $this->validateSettings($request->all(), true);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $data = $validator->validated();

            $oldSettings = $settings->toArray();

            $data = $this->normalizeChannelFields($data, $request);

            $this->assertNotificationChannelsAreAllowed($data);

            $data = $this->normalizeBooleanFields($data, $request);

            if (!empty($data['enable_tenant_invoicing']) && empty($data['tenant_monthly_dues_amount'])) {
                $data['tenant_monthly_dues_amount'] = 50.00;
            }

            $data['updated_by'] = auth()->id();

            if (array_key_exists('sms_sender_id', $data)) {
                $data['sms_sender_id'] = filled($data['sms_sender_id'])
                    ? trim((string) $data['sms_sender_id'])
                    : null;
            }

            if ($request->hasFile('system_logo')) {
                if ($settings->system_logo) {
                    $logoController = app(LogoController::class);
                    $logoController->deleteLogoFile($settings->system_logo);
                }

                $logoController = app(LogoController::class);
                $data['system_logo'] = $logoController->handleLogoUpload($request->file('system_logo'));
            }

            if ($request->boolean('remove_logo') && $settings->system_logo) {
                $logoController = app(LogoController::class);
                $logoController->deleteLogoFile($settings->system_logo);
                $data['system_logo'] = null;
            }

            if ($request->hasFile('system_favicon')) {
                if ($settings->hasFavicon()) {
                    $this->deleteFaviconFile($settings->system_favicon);
                }
                $data['system_favicon'] = $this->handleFaviconUpload($request->file('system_favicon'));
            }

            if ($request->boolean('remove_favicon') && $settings->hasFavicon()) {
                $this->deleteFaviconFile($settings->system_favicon);
                $data['system_favicon'] = null;
            }

            if (isset($data['system_name']) && $data['system_name'] !== $settings->system_name) {
                $data['system_short_name'] = $this->generateSystemSlug($data['system_name']);
            }

            $settings->update($data);

            $this->clearSettingsReminders();

            $updatedSettings = SystemSetting::find($settings->id);

            Log::info('Updated system settings with notification channels and gateways:', [
                'invoice_channels'      => $updatedSettings->invoice_notification_channels,
                'reminder_channels'     => $updatedSettings->payment_reminder_channels,
                'overdue_channels'      => $updatedSettings->overdue_notification_channels,
                'confirmation_channels' => $updatedSettings->payment_confirmation_channels,
                'sms_sender_id'         => $updatedSettings->sms_sender_id,
                'sms_enabled'           => $updatedSettings->sms_notifications_enabled,
                'sms_reminder_enabled'  => $updatedSettings->sms_reminder_enabled,
                'sms_confirmation_enabled' => $updatedSettings->sms_payment_confirmation_enabled,
                'whatsapp_enabled'      => $updatedSettings->enable_whatsapp_notifications,
                'whatsapp_reminder_enabled' => $updatedSettings->whatsapp_reminder_enabled,
                'enabled_gateways'      => [
                    'expresspay'  => $updatedSettings->enable_expresspay,
                    'hubtel'      => $updatedSettings->enable_hubtel,
                    'paystack'    => $updatedSettings->enable_paystack,
                    'flutterwave' => $updatedSettings->enable_flutterwave,
                ],
                'has_favicon'           => $updatedSettings->hasFavicon(),
                'reminders_cleared'     => true
            ]);

            if ($this->trackNotificationChannelChanges($oldSettings, $updatedSettings->toArray())) {
                $this->notifyAboutNotificationChannelChange($updatedSettings, $oldSettings);
            }

            if ($this->trackTenantInvoiceChanges($oldSettings, $updatedSettings->toArray())) {
                $this->notifyAboutTenantInvoiceChange($updatedSettings, $oldSettings);
            }

            if ($this->trackInvoiceSettingsChanges($oldSettings, $updatedSettings->toArray())) {
                $this->notifyAboutInvoiceSettingChange($updatedSettings, $oldSettings);
            }

            if ($this->trackReminderSettingsChanges($oldSettings, $updatedSettings->toArray())) {
                $this->notifyAboutReminderSettingChange($updatedSettings, $oldSettings);
            }

            $this->notifySuperAdminsAboutSettingsUpdate($updatedSettings, auth()->user(), $oldSettings);

            $systemUpdateResult = $this->queueSystemConfigurationUpdate(
                $updatedSettings,
                array_merge($request->all(), [
                    'system_name'       => $updatedSettings->system_name,
                    'system_short_name' => $updatedSettings->system_short_name,
                    'system_email'      => $updatedSettings->system_email,
                ]),
                'update'
            );

            $successMessage = '✅ System settings updated successfully!';

            if ($updatedSettings->hasFavicon()) {
                $successMessage .= ' Favicon updated successfully!';
            }

            if (!$this->hasSettingsBeenConfiguredBefore($oldSettings)) {
                $successMessage .= ' All pending setup reminders have been cleared.';
            }

            if ($systemUpdateResult['success']) {
                $successMessage .= ' System configuration is being updated in the background!';

                return redirect()->route('admin.system-settings.index')
                    ->with('success', $successMessage)
                    ->with('system_update_status', 'processing')
                    ->with('provider', 'system_configuration')
                    ->with('tenant_invoicing_enabled', $updatedSettings->enable_tenant_invoicing ? 'Yes' : 'No')
                    ->with('tenant_dues_amount', $updatedSettings->formatAmount($updatedSettings->tenant_monthly_dues_amount ?? 0))
                    ->with('notification_channels', $this->getNotificationChannelSummary($updatedSettings));
            } else {
                Log::warning("Failed to queue system configuration update, falling back to immediate update", [
                    'error' => $systemUpdateResult['message']
                ]);

                return $this->processSystemConfigurationImmediately(
                    $updatedSettings,
                    array_merge($request->all(), [
                        'system_name'       => $updatedSettings->system_name,
                        'system_short_name' => $updatedSettings->system_short_name,
                        'system_email'      => $updatedSettings->system_email,
                    ]),
                    'update'
                );
            }

        } catch (ValidationException $ve) {
            return redirect()->back()
                ->withErrors($ve->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error("Failed to update system settings: " . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception'    => $e,
                'trace'        => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', '❌ Error updating system settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /* ============================================================
     | BOOLEAN NORMALIZATION
     * ============================================================ */
    protected function normalizeBooleanFields(array $data, Request $request): array
    {
        foreach (self::BOOLEAN_FIELDS as $field) {
            $raw = $data[$field] ?? $request->input($field);

            $data[$field] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }

    /* ============================================================
     | ADMIN-ONLY: whitelisted sender-ID update
     |
     | This is intentionally NOT gated by billing access. Rationale:
     |   - It's the only write a plain admin can make.
     |   - It only changes the SMS sender name shown to landlords
     |     and tenants — a *communication* concern, not a money one.
     |   - Blocking it would break the SMS sender on outbound
     |     messages without affecting the overdue state, which is
     |     worse than allowing it.
     * ============================================================ */
    protected function updateSenderIdOnly(Request $request, SystemSetting $settings)
    {
        $validator = Validator::make($request->all(), [
            'sms_sender_id' => [
                'sometimes',
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
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $oldSenderId = $settings->sms_sender_id;
        $newSenderId = $request->input('sms_sender_id');
        $newSenderId = filled($newSenderId) ? trim((string) $newSenderId) : null;

        $settings->sms_sender_id = $newSenderId;
        $settings->updated_by    = auth()->id();
        $settings->save();

        \Illuminate\Support\Facades\Cache::forget('system_settings');
        \Illuminate\Support\Facades\Cache::forget('system_settings.sms_sender_id');
        \Illuminate\Support\Facades\Cache::forget('sms_default_sender_id');

        try {
            $this->smsService->forgetGlobalSenderIdCache();
        } catch (\Throwable $e) {
            Log::debug('SmsService cache flush skipped: ' . $e->getMessage());
        }

        Log::info('SMS sender ID updated by admin', [
            'admin_id'      => auth()->id(),
            'admin_name'    => auth()->user()->name,
            'old_sender_id' => $oldSenderId,
            'new_sender_id' => $newSenderId,
        ]);

        try {
            $this->notifySuperAdminsAboutSenderIdChange(
                $settings,
                auth()->user(),
                $oldSenderId,
                $newSenderId
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify about sender ID change: ' . $e->getMessage());
        }

        return redirect()->route('admin.system-settings.index')
            ->with('success', '✅ Default SMS sender ID updated successfully.');
    }

    /* ============================================================
     | AJAX sender-ID endpoint — admin write operation
     * ============================================================ */
    public function updateSenderId(Request $request)
    {
        if (!$this->userIsPlainAdmin() && !$this->userIsDeveloper() && !$this->userIsSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('update_sms_sender_id');

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
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $settings    = SystemSetting::getSettings();
            $oldSenderId = $settings->sms_sender_id;
            $newSenderId = filled($request->input('sms_sender_id'))
                ? trim((string) $request->input('sms_sender_id'))
                : null;

            $settings->sms_sender_id = $newSenderId;
            $settings->updated_by    = auth()->id();
            $settings->save();

            \Illuminate\Support\Facades\Cache::forget('system_settings');
            \Illuminate\Support\Facades\Cache::forget('system_settings.sms_sender_id');
            \Illuminate\Support\Facades\Cache::forget('sms_default_sender_id');

            try {
                $this->smsService->forgetGlobalSenderIdCache();
            } catch (\Throwable $e) {
                Log::debug('SmsService cache flush skipped: ' . $e->getMessage());
            }

            Log::info('SMS sender ID updated via API', [
                'user_id'       => auth()->id(),
                'old_sender_id' => $oldSenderId,
                'new_sender_id' => $newSenderId,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => '✅ Default SMS sender ID updated successfully.',
                'sender_id' => $newSenderId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update SMS sender ID: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to update sender ID: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | NOTIFY: sender ID change (audit trail)
     * ============================================================ */
    protected function notifySuperAdminsAboutSenderIdChange(
        SystemSetting $settings,
        User $updater,
        ?string $oldSenderId,
        ?string $newSenderId
    ): void {
        $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

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
     | Tracking helpers
     * ============================================================ */
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
            'tenant_fixed_penalty_amount'
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

    private function notifyAboutTenantInvoiceChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldEnabled = $oldSettings['enable_tenant_invoicing'] ?? false;
            $newEnabled = $settings->enable_tenant_invoicing;

            $title = $newEnabled ? '👥 Tenant Invoicing Enabled' : '👥 Tenant Invoicing Disabled';
            $icon  = $newEnabled ? 'fas fa-users text-info' : 'fas fa-users-slash text-warning';

            $message = "Tenant invoicing has been " . ($newEnabled ? 'enabled' : 'disabled') . " by " . auth()->user()->name . ". ";

            if ($newEnabled) {
                $amount = $settings->formatAmount($settings->tenant_monthly_dues_amount ?? 0);
                $method = $settings->getTenantCalculationMethodText();
                $message .= "Monthly dues: {$amount}, Calculation: {$method}.";
            }

            $notificationData = [
                'title'   => $title,
                'message' => $message,
                'icon'    => $icon,
                'category'=> 'tenant_invoice_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority'=> 2,
                'data'    => [
                    'type'         => 'tenant_invoice_change',
                    'old_enabled'  => $oldEnabled,
                    'new_enabled'  => $newEnabled,
                    'old_amount'   => $oldSettings['tenant_monthly_dues_amount'] ?? 0,
                    'new_amount'   => $settings->tenant_monthly_dues_amount,
                    'old_method'   => $oldSettings['tenant_calculation_method'] ?? 'fixed',
                    'new_method'   => $settings->tenant_calculation_method,
                    'changed_by'   => auth()->id(),
                    'changed_by_name' => auth()->user()->name,
                    'timestamp'    => now()->toISOString()
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Tenant invoice change notification sent', [
                'admin_count' => $superAdmins->count(),
                'new_enabled' => $newEnabled
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify about tenant invoice change: ' . $e->getMessage());
        }
    }

    private function trackInvoiceSettingsChanges(array $oldSettings, array $newSettings): bool
    {
        $invoiceFields = ['auto_generate_invoices'];

        foreach ($invoiceFields as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {
                return true;
            }
        }

        return false;
    }

    private function trackReminderSettingsChanges(array $oldSettings, array $newSettings): bool
    {
        $reminderFields = ['send_payment_reminders', 'reminder_days_before'];

        foreach ($reminderFields as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {
                return true;
            }
        }

        return false;
    }

    private function notifyAboutInvoiceSettingChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldStatus = $oldSettings['auto_generate_invoices'] ?? false;
            $newStatus = $settings->auto_generate_invoices;

            if ($oldStatus == $newStatus) {
                return;
            }

            $statusText = $newStatus ? 'enabled' : 'disabled';
            $icon = $newStatus ? 'fas fa-play-circle text-success' : 'fas fa-stop-circle text-warning';

            $notificationData = [
                'title' => $newStatus ? '⚙️ Auto Invoice Generation Enabled' : '⚙️ Auto Invoice Generation Disabled',
                'message' => "Auto invoice generation has been {$statusText} by " . auth()->user()->name . ". " .
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
                    'changed_by_name' => auth()->user()->name,
                    'timestamp' => now()->toISOString()
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Invoice setting change notification sent', [
                'admin_count' => $superAdmins->count(),
                'new_status'  => $newStatus
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify about invoice setting change: ' . $e->getMessage());
        }
    }

    private function notifyAboutReminderSettingChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldEnabled = $oldSettings['send_payment_reminders'] ?? false;
            $newEnabled = $settings->send_payment_reminders;
            $oldDays    = $oldSettings['reminder_days_before'] ?? 7;
            $newDays    = $settings->reminder_days_before;

            $message = "";
            if ($oldEnabled != $newEnabled) {
                $statusText = $newEnabled ? 'enabled' : 'disabled';
                $message = "Payment reminders have been {$statusText} by " . auth()->user()->name . ". ";
            }

            if ($oldDays != $newDays && $newEnabled) {
                $message .= "Reminder days changed from {$oldDays} to {$newDays} days before due date.";
            } elseif ($oldDays != $newDays) {
                $message .= "Reminder days setting changed from {$oldDays} to {$newDays} days (but reminders are currently disabled).";
            }

            $icon = $newEnabled ? 'fas fa-bell text-success' : 'fas fa-bell-slash text-warning';

            $notificationData = [
                'title' => $newEnabled ? '🔔 Payment Reminders Enabled' : '🔕 Payment Reminders Disabled',
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
                    'changed_by_name' => auth()->user()->name,
                    'timestamp' => now()->toISOString()
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Reminder setting change notification sent', [
                'admin_count' => $superAdmins->count(),
                'new_enabled' => $newEnabled,
                'new_days'    => $newDays
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify about reminder setting change: ' . $e->getMessage());
        }
    }

    private function getInvoiceGenerationStatus(SystemSetting $settings): array
    {
        $nextMonth = now()->addMonth();
        $dueDate   = $settings->calculateDueDateForPeriod($nextMonth);

        return [
            'auto_generate_enabled'   => $settings->isAutoInvoiceGenerationEnabled(),
            'next_generation_month'   => $nextMonth->format('F Y'),
            'next_generation_date'    => $settings->getNextInvoiceGenerationDate()->format('F j, Y'),
            'next_due_date'           => $dueDate->format('F j, Y'),
            'reminders_enabled'       => $settings->shouldSendPaymentReminders(),
            'reminder_days'           => $settings->getReminderDaysBefore(),
            'grace_period'            => $settings->grace_period_days,
            'is_ready'                => $settings->isReadyForAutoInvoiceGeneration()['ready'],
            'readiness_issues'        => $settings->isReadyForAutoInvoiceGeneration()['issues']
        ];
    }

    /* ============================================================
     | TOGGLE auto invoice — admin write operation
     * ============================================================ */
    public function toggleAutoInvoiceGeneration(Request $request)
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('toggle_auto_invoice_generation');

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $settings  = SystemSetting::getSettings();
            $oldStatus = $settings->isAutoInvoiceGenerationEnabled();
            $newStatus = $request->enabled;

            $settings->auto_generate_invoices = $newStatus;
            $settings->updated_by = auth()->id();
            $settings->save();

            Log::info('Auto invoice generation toggled via API', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'toggled_by' => auth()->id()
            ]);

            $this->notifyAboutInvoiceSettingChange($settings, ['auto_generate_invoices' => $oldStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus
                    ? '✅ Auto invoice generation enabled successfully'
                    : '✅ Auto invoice generation disabled successfully',
                'enabled' => $newStatus,
                'next_generation_date' => $settings->getNextInvoiceGenerationDate()->format('Y-m-d')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle auto invoice generation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to update setting'
            ], 500);
        }
    }

    /* ============================================================
     | NOTIFICATION CHANNEL METHODS
     * ============================================================ */

    protected function getNotificationChannelStatus(SystemSetting $settings): array
    {
        $flags = $this->resolveProviderFlags();

        return [
            'invoice_notification_channels'  => $settings->invoice_notification_channels ?? ['email'],
            'invoice_notification_summary'   => $this->formatChannelSummary($settings->invoice_notification_channels ?? ['email']),

            'payment_reminder_channels'      => $settings->payment_reminder_channels ?? ['email'],
            'payment_reminder_summary'       => $this->formatChannelSummary($settings->payment_reminder_channels ?? ['email']),

            'overdue_notification_channels'  => $settings->overdue_notification_channels ?? ['email', 'sms'],
            'overdue_notification_summary'   => $this->formatChannelSummary($settings->overdue_notification_channels ?? ['email', 'sms']),

            'payment_confirmation_channels'  => $settings->payment_confirmation_channels ?? ['email'],
            'payment_confirmation_summary'   => $this->formatChannelSummary($settings->payment_confirmation_channels ?? ['email']),

            'sms_notifications_enabled'      => $settings->sms_notifications_enabled ?? false,
            'sms_reminder_enabled'           => $settings->sms_reminder_enabled ?? false,
            'sms_payment_confirmation_enabled' => $settings->sms_payment_confirmation_enabled ?? false,
            'sms_daily_limit_per_user'       => $settings->sms_daily_limit_per_user ?? 10,
            'sms_hourly_limit_per_user'      => $settings->sms_hourly_limit_per_user ?? 3,

            'enable_whatsapp_notifications'  => $settings->enable_whatsapp_notifications ?? false,
            'whatsapp_reminder_enabled'      => $settings->whatsapp_reminder_enabled ?? false,
            'whatsapp_provider'              => $settings->whatsapp_provider ?? null,

            'email_notifications_enabled'    => true,

            'sms_provider_configured'        => $flags['smsProviderConfigured'],
            'whatsapp_provider_configured'   => $flags['whatsappProviderConfigured'],
            'sms_active_provider'            => $flags['smsActiveProviderName'],
            'whatsapp_active_provider'       => $flags['whatsappActiveProviderName'],
        ];
    }

    protected function formatChannelSummary(array $channels): string
    {
        $channelNames = [
            'email'    => '📧 Email',
            'sms'      => '📱 SMS',
            'whatsapp' => '💬 WhatsApp',
        ];

        $names = array_map(function($channel) use ($channelNames) {
            return $channelNames[$channel] ?? $channel;
        }, $channels);

        return implode(' + ', $names);
    }

    protected function getNotificationChannelSummary(SystemSetting $settings): array
    {
        return [
            'invoice'      => $this->formatChannelSummary($settings->invoice_notification_channels ?? ['email']),
            'reminder'     => $this->formatChannelSummary($settings->payment_reminder_channels ?? ['email']),
            'overdue'      => $this->formatChannelSummary($settings->overdue_notification_channels ?? ['email', 'sms']),
            'confirmation' => $this->formatChannelSummary($settings->payment_confirmation_channels ?? ['email']),
        ];
    }

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

            if (is_array($oldValue)) { $oldValue = json_encode($oldValue); }
            if (is_array($newValue)) { $newValue = json_encode($newValue); }

            if ($oldValue != $newValue) {
                return true;
            }
        }

        return false;
    }

    protected function notifyAboutNotificationChannelChange(SystemSetting $settings, array $oldSettings): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $oldInvoiceChannels  = $oldSettings['invoice_notification_channels'] ?? ['email'];
            $newInvoiceChannels  = $settings->invoice_notification_channels ?? ['email'];

            $oldSmsEnabled       = $oldSettings['sms_notifications_enabled'] ?? false;
            $newSmsEnabled       = $settings->sms_notifications_enabled ?? false;

            $oldWhatsAppEnabled  = $oldSettings['enable_whatsapp_notifications'] ?? false;
            $newWhatsAppEnabled  = $settings->enable_whatsapp_notifications ?? false;

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
                'message' => "Notification channels have been updated by " . auth()->user()->name . ". Changes: " . implode('; ', $changes),
                'icon' => 'fas fa-bell text-info',
                'category' => 'notification_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 2,
                'data' => [
                    'type' => 'notification_channel_change',
                    'old_invoice_channels' => $oldInvoiceChannels,
                    'new_invoice_channels' => $newInvoiceChannels,
                    'old_sms_enabled'      => $oldSmsEnabled,
                    'new_sms_enabled'      => $newSmsEnabled,
                    'old_whatsapp_enabled' => $oldWhatsAppEnabled,
                    'new_whatsapp_enabled' => $newWhatsAppEnabled,
                    'changed_by'           => auth()->id(),
                    'changed_by_name'      => auth()->user()->name,
                    'timestamp'            => now()->toISOString(),
                    'notification_type'    => 'in_app_only'
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notification channel change notification sent', [
                'admin_count' => $superAdmins->count(),
                'changes'     => $changes
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify about notification channel change: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | UPDATE notification channels — admin write operation
     * ============================================================ */
    public function updateNotificationChannels(Request $request)
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('update_notification_channels');

        $validator = Validator::make($request->all(), [
            'invoice_notification_channels'   => 'nullable|array',
            'invoice_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_reminder_channels'       => 'nullable|array',
            'payment_reminder_channels.*'     => 'in:email,sms,whatsapp',
            'overdue_notification_channels'   => 'nullable|array',
            'overdue_notification_channels.*' => 'in:email,sms,whatsapp',
            'payment_confirmation_channels'   => 'nullable|array',
            'payment_confirmation_channels.*' => 'in:email,sms,whatsapp',
            'sms_notifications_enabled'       => 'nullable|boolean',
            'sms_reminder_enabled'            => 'nullable|boolean',
            'sms_payment_confirmation_enabled'=> 'nullable|boolean',
            'sms_daily_limit_per_user'        => 'nullable|integer|min:1|max:100',
            'sms_hourly_limit_per_user'       => 'nullable|integer|min:1|max:20',
            'enable_whatsapp_notifications'   => 'nullable|boolean',
            'whatsapp_reminder_enabled'       => 'nullable|boolean',
            'whatsapp_provider'               => 'nullable|in:twilio,vonage,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $settings    = SystemSetting::getSettings();
            $oldSettings = $settings->toArray();

            $updateData = [];

            foreach (self::CHANNEL_FIELDS as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $this->normalizeChannelArray($request->input($field, ['email']));
                }
            }

            $smsFields = [
                'sms_notifications_enabled',
                'sms_reminder_enabled',
                'sms_payment_confirmation_enabled',
                'sms_daily_limit_per_user',
                'sms_hourly_limit_per_user',
            ];

            foreach ($smsFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            $whatsappFields = [
                'enable_whatsapp_notifications',
                'whatsapp_reminder_enabled',
                'whatsapp_provider',
            ];

            foreach ($whatsappFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            $this->assertNotificationChannelsAreAllowed($updateData);

            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $settings->update($updateData);

                if ($this->trackNotificationChannelChanges($oldSettings, $settings->toArray())) {
                    $this->notifyAboutNotificationChannelChange($settings, $oldSettings);
                }

                Log::info('Notification channels updated via API', [
                    'updates'    => array_keys($updateData),
                    'updated_by' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '✅ Notification channels updated successfully',
                    'data'    => $this->getNotificationChannelStatus($settings)
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No changes detected',
                'data'    => $this->getNotificationChannelStatus($settings)
            ]);

        } catch (ValidationException $ve) {
            return response()->json([
                'success' => false,
                'errors'  => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update notification channels: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to update notification channels: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ============================================================
     | GET notification channels — read-only
     * ============================================================ */
    public function getNotificationChannelSettings()
    {
        try {
            $settings = SystemSetting::getSettings();
            $flags    = $this->resolveProviderFlags();

            return response()->json([
                'success' => true,
                'data'    => $this->getNotificationChannelStatus($settings),
                'sms_providers'   => $this->smsService->getAllProvidersWithStatus(),
                'sms_configured'  => $flags['smsProviderConfigured'],
                'sms_quick_status'=> $this->smsService->getQuickStatus(),
                'provider_flags'  => $flags,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get notification channel settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to retrieve notification settings'
            ], 500);
        }
    }

    /* ============================================================
     | TEST notification channels — read-only / communication
     * ============================================================ */
    public function testNotificationChannels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channels'       => 'required|array',
            'channels.*'     => 'in:email,sms,whatsapp',
            'phone_number'   => 'required_if:channels.*,sms,whatsapp|nullable|string',
            'email'          => 'required_if:channels.*,email|nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $settings   = SystemSetting::getSettings();
            $channels   = $request->input('channels', ['email']);
            $results    = [];
            $flags      = $this->resolveProviderFlags();

            $testUser    = auth()->user();
            $testMessage = "🔔 This is a test notification from {$settings->system_name}. Time: " . now()->format('Y-m-d H:i:s');

            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        if ($request->filled('email')) {
                            $results['email'] = $this->testEmailChannel($request->email, $testMessage);
                        } else {
                            $results['email'] = ['success' => false, 'message' => 'No email address provided'];
                        }
                        break;

                    case 'sms':
                        if (!$flags['smsProviderConfigured']) {
                            $results['sms'] = ['success' => false, 'message' => 'No SMS provider configured'];
                            break;
                        }
                        if ($request->filled('phone_number')) {
                            $results['sms'] = $this->testSmsChannel($request->phone_number, $testMessage);
                        } else {
                            $results['sms'] = ['success' => false, 'message' => 'No phone number provided'];
                        }
                        break;

                    case 'whatsapp':
                        if (!$flags['whatsappProviderConfigured']) {
                            $results['whatsapp'] = ['success' => false, 'message' => 'No WhatsApp provider configured'];
                            break;
                        }
                        if ($request->filled('phone_number')) {
                            $results['whatsapp'] = $this->testWhatsAppChannel($request->phone_number, $testMessage);
                        } else {
                            $results['whatsapp'] = ['success' => false, 'message' => 'No phone number provided'];
                        }
                        break;
                }
            }

            $allSuccessful = collect($results)->every(function($result) {
                return $result['success'] ?? false;
            });

            return response()->json([
                'success'         => $allSuccessful,
                'message'         => $allSuccessful ? '✅ All channels tested successfully' : '⚠️ Some channels failed',
                'results'         => $results,
                'channels_tested' => $channels
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to test notification channels: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to test notification channels: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function testEmailChannel(string $email, string $message): array
    {
        try {
            \Mail::raw($message, function($mail) use ($email) {
                $mail->to($email)
                    ->subject('Test Notification from System Settings')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success'   => true,
                'message'   => 'Test email sent successfully',
                'channel'   => 'email',
                'recipient' => $this->maskEmail($email)
            ];
        } catch (\Exception $e) {
            Log::error('Email test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Email test failed: ' . $e->getMessage(),
                'channel' => 'email'
            ];
        }
    }

    protected function testSmsChannel(string $phoneNumber, string $message): array
    {
        try {
            $result = $this->smsService->sendWithDefaultProvider($phoneNumber, $message, ['is_test' => true]);

            return [
                'success'   => $result['success'],
                'message'   => $result['success'] ? 'Test SMS sent successfully' : 'SMS test failed: ' . $result['message'],
                'channel'   => 'sms',
                'recipient' => $this->maskPhoneNumber($phoneNumber),
                'details'   => $result['details'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SMS test failed: ' . $e->getMessage(),
                'channel' => 'sms'
            ];
        }
    }

    protected function testWhatsAppChannel(string $phoneNumber, string $message): array
    {
        try {
            $settings = SystemSetting::getSettings();

            if (!$settings->enable_whatsapp_notifications) {
                return [
                    'success' => false,
                    'message' => 'WhatsApp notifications are disabled in settings',
                    'channel' => 'whatsapp'
                ];
            }

            $provider = $settings->whatsapp_provider ?? 'twilio';

            Log::info('WhatsApp test would send to: ' . $this->maskPhoneNumber($phoneNumber), [
                'provider' => $provider,
                'message'  => $message
            ]);

            return [
                'success' => false,
                'message' => 'WhatsApp integration not fully implemented yet',
                'channel' => 'whatsapp',
                'details' => [
                    'provider' => $provider,
                    'note'     => 'WhatsApp integration coming soon'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'WhatsApp test failed: ' . $e->getMessage(),
                'channel' => 'whatsapp'
            ];
        }
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $username = $parts[0];
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
     | UPDATE reminder settings — admin write operation
     * ============================================================ */
    public function updateReminderSettings(Request $request)
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('update_system_reminder_settings');

        $validator = Validator::make($request->all(), [
            'enabled'     => 'required|boolean',
            'days_before' => 'required|integer|min:1|max:30'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $settings    = SystemSetting::getSettings();
            $oldEnabled  = $settings->send_payment_reminders;
            $oldDays     = $settings->reminder_days_before;

            $settings->send_payment_reminders = $request->enabled;
            $settings->reminder_days_before   = $request->days_before;
            $settings->updated_by             = auth()->id();
            $settings->save();

            Log::info('Reminder settings updated via API', [
                'old_enabled' => $oldEnabled,
                'new_enabled' => $request->enabled,
                'old_days'    => $oldDays,
                'new_days'    => $request->days_before,
                'updated_by'  => auth()->id()
            ]);

            $this->notifyAboutReminderSettingChange($settings, [
                'send_payment_reminders' => $oldEnabled,
                'reminder_days_before'   => $oldDays
            ]);

            return response()->json([
                'success'     => true,
                'message'     => $request->enabled
                    ? "✅ Reminders enabled for {$request->days_before} days before due date"
                    : '✅ Reminders disabled',
                'enabled'     => $request->enabled,
                'days_before' => $request->days_before
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update reminder settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to update reminder settings'
            ], 500);
        }
    }

    /* ============================================================
     | GET invoice settings — read-only
     * ============================================================ */
    public function getInvoiceSettings()
    {
        try {
            $settings = SystemSetting::getSettings();
            $status   = $this->getInvoiceGenerationStatus($settings);

            return response()->json([
                'success' => true,
                'data'    => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to retrieve invoice settings'
            ], 500);
        }
    }

    /* ============================================================
     | VALIDATE invoice settings — read-only
     * ============================================================ */
    public function validateInvoiceSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'auto_generate_invoices'  => 'required|boolean',
            'send_payment_reminders'  => 'required|boolean',
            'reminder_days_before'    => 'required|integer|min:1|max:30',
            'grace_period_days'       => 'required|integer|min:0|max:30'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid'  => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $warnings = [];

        if ($data['send_payment_reminders'] && $data['reminder_days_before'] > $data['grace_period_days']) {
            $warnings[] = "⚠️ Reminder days ({$data['reminder_days_before']}) are greater than grace period ({$data['grace_period_days']}). Some reminders may be sent after invoices are already overdue.";
        }

        return response()->json([
            'valid'    => true,
            'warnings' => $warnings
        ]);
    }


    /* ============================================================
     | TOGGLE registration — admin write operation
     * ============================================================ */
    public function toggleRegistration(Request $request)
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('toggle_registration');

        $validator = Validator::make($request->all(), [
            'allow_registration'             => 'required|boolean',
            'registration_disabled_message'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $settings   = SystemSetting::getSettings();
            $oldStatus  = $settings->isRegistrationAllowed();

            $settings->allow_registration = $request->allow_registration;

            if ($request->filled('registration_disabled_message')) {
                $settings->registration_disabled_message = $request->registration_disabled_message;
            }

            $settings->updated_by = auth()->id();
            $settings->save();

            Log::info('Registration status toggled', [
                'old_status'  => $oldStatus,
                'new_status'  => $request->allow_registration,
                'toggled_by'  => auth()->id(),
                'message'     => $request->registration_disabled_message
            ]);

            $this->notifySuperAdminsAboutRegistrationToggle($settings, auth()->user(), $oldStatus);

            $message = $request->allow_registration
                ? '✅ Registration has been enabled successfully.'
                : '✅ Registration has been disabled successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'             => true,
                    'message'             => $message,
                    'registration_status' => $settings->getRegistrationStatus()
                ]);
            }

            return redirect()->route('admin.system-settings.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to toggle registration: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Failed to update registration status.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', '❌ Failed to update registration status.');
        }
    }

        /**
 * Toggle whether admins can mark invoices as paid manually (cash at the
 * office, cheque, bank deposit). When disabled, landlords must pay through
 * the online gateway.
 *
 * This is a non-financial admin action — it doesn't move money, it just
 * controls whether a manual payment path is available. Deliberately NOT
 * gated by the billing-access check, because blocking it would prevent
 * the landlord from paying in person even when they owe money, which is
 * worse than allowing it.
 */
public function toggleOfflinePayment(Request $request)
{
    if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    $validator = Validator::make($request->all(), [
        'allow_offline_payment' => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return redirect()->back()->withErrors($validator);
    }

    try {
        $settings = SystemSetting::getSettings();
        $oldValue = $settings->isOfflinePaymentAllowed();
        $newValue = (bool) $request->allow_offline_payment;

        $settings->allow_offline_payment = $newValue;
        $settings->updated_by            = auth()->id();
        $settings->save();

        Log::info('Offline payment setting toggled', [
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id'   => auth()->id(),
            'user_name' => auth()->user()->name,
        ]);

        $message = $newValue
            ? '✅ Office payments enabled. Admins can now mark invoices as paid manually.'
            : '⚠️ Office payments blocked. Landlords must use the online payment gateway.';

        return redirect()->back()->with('success', $message);

    } catch (\Exception $e) {
        Log::error('Failed to toggle offline payment: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to update offline payment setting.');
    }
}

    /* ============================================================
     | GET registration status — read-only
     * ============================================================ */
    public function getRegistrationStatus()
    {
        try {
            $settings = SystemSetting::getSettings();

            return response()->json([
                'success'             => true,
                'registration_status' => $settings->getRegistrationStatus()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get registration status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to retrieve registration status.'
            ], 500);
        }
    }

    private function notifySuperAdminsAboutRegistrationToggle(SystemSetting $settings, User $toggler, bool $oldStatus)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super admins found to notify about registration toggle');
                return;
            }

            $newStatus = $settings->allow_registration;
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
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about registration toggle', [
                'admin_count' => $superAdmins->count(),
                'new_status'  => $newStatus,
                'toggler_id'  => $toggler->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about registration toggle: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | NOTIFICATION helpers
     * ============================================================ */

    private function notifySuperAdminsAboutMissingSettings(): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super admins found to notify about missing system settings');
                return;
            }

            Log::info('Preparing to notify super admins about missing system settings', [
                'super_admin_count' => $superAdmins->count()
            ]);

            $notificationData = [
                'title' => '🚨 System Settings Missing',
                'message' => 'No system settings have been configured. This is critical - the system cannot function properly without configuration. Please create system settings immediately.',
                'icon' => 'fas fa-exclamation-triangle text-danger',
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.create'),
                'priority' => 3,
                'data' => [
                    'type' => 'system_settings_missing',
                    'alert_level' => 'critical',
                    'requires_attention' => true,
                    'missing_since' => now()->toISOString(),
                    'action_required' => 'Create system settings',
                    'impact' => 'System cannot function properly without settings',
                    'recommendation' => 'Navigate to System Settings and complete the setup wizard',
                    'notification_type' => 'in_app_only'
                ]
            ];

            $sentCount = 0;
            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
                $sentCount++;
            }

            Log::info('✅ Missing system settings notifications sent', [
                'admin_count' => $sentCount,
                'notification_type' => 'in_app_only',
                'email_not_sent' => true
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about missing settings: ' . $e->getMessage());
        }
    }

    private function notifySuperAdminsAboutSettingsCreation(SystemSetting $settings, User $creator)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super admins found to notify about system settings creation');
                return;
            }

            $registrationStatus = $settings->isRegistrationAllowed() ? 'Enabled' : 'Disabled';
            $invoiceStatus      = $settings->isAutoInvoiceGenerationEnabled() ? 'Enabled' : 'Disabled';
            $reminderStatus     = $settings->shouldSendPaymentReminders() ? 'Enabled' : 'Disabled';
            $faviconStatus      = $settings->hasFavicon() ? 'Uploaded' : 'Not uploaded';

            $notificationData = [
                'title' => '✅ System Settings Created',
                'message' => "System settings have been created by {$creator->name}. Registration: {$registrationStatus}, Auto Invoice: {$invoiceStatus}, Reminders: {$reminderStatus}, Favicon: {$faviconStatus}.",
                'icon' => 'fas fa-cog text-success',
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 1,
                'data' => [
                    'type' => 'system_settings_created',
                    'settings_id' => $settings->id,
                    'system_name' => $settings->system_name,
                    'created_by' => $creator->id,
                    'created_by_name' => $creator->name,
                    'timestamp' => now()->toISOString(),
                    'registration_allowed' => $settings->allow_registration,
                    'auto_generate_invoices' => $settings->auto_generate_invoices,
                    'send_payment_reminders' => $settings->send_payment_reminders,
                    'reminder_days_before' => $settings->reminder_days_before,
                    'has_favicon' => $settings->hasFavicon(),
                    'details' => [
                        'system_email' => $settings->system_email,
                        'system_phone' => $settings->system_phone,
                        'currency_code' => $settings->currency_code,
                    ],
                    'notification_type' => 'in_app_only'
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about system settings creation', [
                'admin_count' => $superAdmins->count(),
                'settings_id' => $settings->id,
                'creator_id'  => $creator->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about settings creation: ' . $e->getMessage());
        }
    }

    private function notifySuperAdminsAboutSettingsUpdate(SystemSetting $settings, User $updater, array $oldSettings)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super admins found to notify about system settings update');
                return;
            }

            $changedFields = $this->getChangedSettings($oldSettings, $settings->toArray());

            if (empty($changedFields)) {
                Log::info('No significant changes detected in system settings update');
                return;
            }

            $changesSummary = $this->formatChangesSummary($changedFields);

            $registrationChanged = isset($changedFields['allow_registration']);
            $invoiceChanged      = isset($changedFields['auto_generate_invoices']);
            $reminderChanged     = isset($changedFields['send_payment_reminders']) || isset($changedFields['reminder_days_before']);
            $faviconChanged      = isset($changedFields['system_favicon']);
            $senderIdChanged     = isset($changedFields['sms_sender_id']);

            $title = '📝 System Settings Updated';
            $icon  = 'fas fa-edit text-info';

            if ($registrationChanged) {
                $title = $settings->allow_registration ? '🔓 Registration Enabled' : '🔒 Registration Disabled';
                $icon  = $settings->allow_registration ? 'fas fa-unlock text-success' : 'fas fa-lock text-danger';
            } elseif ($invoiceChanged) {
                $title = $settings->auto_generate_invoices ? '⚙️ Auto Invoice Enabled' : '⚙️ Auto Invoice Disabled';
                $icon  = $settings->auto_generate_invoices ? 'fas fa-play-circle text-success' : 'fas fa-stop-circle text-warning';
            } elseif ($reminderChanged) {
                $title = $settings->send_payment_reminders ? '🔔 Reminders Updated' : '🔕 Reminders Updated';
                $icon  = $settings->send_payment_reminders ? 'fas fa-bell text-success' : 'fas fa-bell-slash text-warning';
            } elseif ($faviconChanged) {
                $title = $settings->hasFavicon() ? '🖼️ Favicon Updated' : '🖼️ Favicon Removed';
                $icon  = $settings->hasFavicon() ? 'fas fa-image text-success' : 'fas fa-image text-warning';
            } elseif ($senderIdChanged) {
                $title = '📱 SMS Sender ID Updated';
                $icon  = 'fas fa-comment-dots text-info';
            }

            $notificationData = [
                'title' => $title,
                'message' => "System settings have been updated by {$updater->name}. Changes: {$changesSummary}",
                'icon' => $icon,
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => $this->calculatePriority($changedFields),
                'data' => [
                    'type' => 'system_settings_updated',
                    'settings_id' => $settings->id,
                    'system_name' => $settings->system_name,
                    'updated_by'  => $updater->id,
                    'updated_by_name' => $updater->name,
                    'timestamp'   => now()->toISOString(),
                    'changed_fields' => $changedFields,
                    'changes_summary'=> $changesSummary,
                    'registration_allowed' => $settings->allow_registration,
                    'auto_generate_invoices' => $settings->auto_generate_invoices,
                    'send_payment_reminders' => $settings->send_payment_reminders,
                    'reminder_days_before' => $settings->reminder_days_before,
                    'has_favicon' => $settings->hasFavicon(),
                    'sms_sender_id' => $settings->sms_sender_id,
                    'is_critical_change' => $this->isCriticalChange($changedFields),
                    'notification_type' => 'in_app_only'
                ]
            ];

            if ($this->isCriticalChange($changedFields)) {
                $notificationData['priority'] = 3;
                $notificationData['icon'] = 'fas fa-exclamation-circle text-danger';
                $notificationData['title'] = '🚨 Critical System Settings Updated';
            }

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about system settings update', [
                'admin_count' => $superAdmins->count(),
                'settings_id' => $settings->id,
                'updater_id'  => $updater->id,
                'changed_fields_count' => count($changedFields),
                'registration_changed' => $registrationChanged,
                'invoice_changed'      => $invoiceChanged,
                'reminder_changed'     => $reminderChanged,
                'favicon_changed'      => $faviconChanged,
                'sender_id_changed'    => $senderIdChanged,
                'is_critical_change'   => $this->isCriticalChange($changedFields),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about settings update: ' . $e->getMessage());
        }
    }

    private function calculatePriority(array $changedFields): int
    {
        $priority = 1;

        $highPriorityFields = [
            'system_email',
            'monthly_dues_amount',
            'allow_registration',
            'auto_generate_invoices',
            'enable_expresspay',
            'enable_hubtel',
            'enable_paystack',
            'enable_flutterwave',
            'allow_offline_payment',
        ];

        foreach ($changedFields as $field => $change) {
            if (in_array($field, $highPriorityFields)) {
                $priority = 2;
            }
        }

        if ($this->isCriticalChange($changedFields)) {
            $priority = 3;
        }

        return $priority;
    }

    private function notifySuperAdminsAboutTestResults(array $testResults, string $testType, User $tester)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title' => $testResults['success'] ? '✅ System Test Passed' : '❌ System Test Failed',
                'message' => "System configuration test ({$testType}) completed by {$tester->name}. " . $testResults['message'],
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
                    'notification_type' => 'in_app_only'
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about test results', [
                'admin_count' => $superAdmins->count(),
                'test_type'   => $testType,
                'success'     => $testResults['success']
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about test results: ' . $e->getMessage());
        }
    }

    private function getChangedSettings(array $oldSettings, array $newSettings): array
    {
        $changedFields = [];

        $importantFields = [
            'system_name',
            'system_short_name',
            'system_email',
            'system_phone',
            'system_logo',
            'system_favicon',
            'sms_sender_id',
            'currency_code',
            'currency_symbol',
            'currency_position',
            'monthly_dues_amount',
            'calculation_method',
            'grace_period_days',
            'late_payment_percentage',
            'fixed_penalty_amount',
            'allow_registration',
            'registration_disabled_message',
            'auto_generate_invoices',
            'send_payment_reminders',
            'reminder_days_before',
            'enable_bulk_payments',
            'max_bulk_months',
            'bulk_payment_discount',

            'allow_offline_payment',

            'sms_notifications_enabled',
            'sms_reminder_enabled',
            'sms_payment_confirmation_enabled',
            'enable_whatsapp_notifications',
            'whatsapp_reminder_enabled',

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
        ];

        foreach ($importantFields as $field) {
            if (isset($oldSettings[$field], $newSettings[$field]) &&
                $oldSettings[$field] != $newSettings[$field]) {

                $changedFields[$field] = [
                    'old' => $oldSettings[$field],
                    'new' => $newSettings[$field],
                    'field_name' => $this->getFieldDisplayName($field),
                ];
            } elseif (isset($newSettings[$field]) &&
                      (!isset($oldSettings[$field]) || $oldSettings[$field] != $newSettings[$field])) {
                $changedFields[$field] = [
                    'old' => $oldSettings[$field] ?? 'not set',
                    'new' => $newSettings[$field],
                    'field_name' => $this->getFieldDisplayName($field),
                ];
            } elseif (isset($oldSettings[$field]) && !isset($newSettings[$field])) {
                $changedFields[$field] = [
                    'old' => $oldSettings[$field],
                    'new' => 'removed',
                    'field_name' => $this->getFieldDisplayName($field),
                ];
            }
        }

        return $changedFields;
    }

    private function getFieldDisplayName(string $field): string
    {
        $fieldNames = [
            'system_name' => 'System Name',
            'system_short_name' => 'System Short Name',
            'system_email' => 'System Email',
            'system_phone' => 'System Phone',
            'system_address' => 'System Address',
            'system_logo' => 'System Logo',
            'system_favicon' => 'Favicon',
            'sms_sender_id' => 'Default SMS Sender ID',

            'currency_code' => 'Currency Code',
            'currency_symbol' => 'Currency Symbol',
            'currency_position' => 'Currency Position',
            'decimal_places' => 'Decimal Places',

            'monthly_dues_amount' => 'Monthly Dues Amount',
            'calculation_method' => 'Dues Calculation Method',
            'per_property_amount' => 'Per Property Amount',

            'enable_tenant_invoicing' => 'Tenant Invoicing',
            'tenant_monthly_dues_amount' => 'Tenant Monthly Dues Amount',
            'tenant_calculation_method' => 'Tenant Calculation Method',
            'auto_generate_tenant_invoices' => 'Auto-Generate Tenant Invoices',
            'send_tenant_payment_reminders' => 'Send Tenant Payment Reminders',
            'tenant_grace_period_days' => 'Tenant Grace Period Days',
            'tenant_late_payment_percentage' => 'Tenant Late Payment Percentage',
            'tenant_fixed_penalty_amount' => 'Tenant Fixed Penalty Amount',

            'grace_period_days' => 'Grace Period Days',
            'late_payment_percentage' => 'Late Payment Percentage',
            'fixed_penalty_amount' => 'Fixed Penalty Amount',

            'auto_generate_invoices' => 'Auto Invoice Generation',
            'send_payment_reminders' => 'Payment Reminders',
            'reminder_days_before' => 'Reminder Days Before',

            'sms_notifications_enabled' => 'SMS Notifications',
            'sms_reminder_enabled' => 'SMS Reminders',
            'sms_payment_confirmation_enabled' => 'SMS Payment Confirmations',
            'enable_whatsapp_notifications' => 'WhatsApp Notifications',
            'whatsapp_reminder_enabled' => 'WhatsApp Reminders',

            'enable_expresspay' => 'ExpressPay',
            'enable_hubtel' => 'Hubtel',
            'enable_paystack' => 'Paystack',
            'enable_flutterwave' => 'Flutterwave',

            'enable_bulk_payments' => 'Bulk Payments',
            'max_bulk_months' => 'Max Bulk Months',
            'bulk_payment_discount' => 'Bulk Payment Discount',

            'allow_registration' => 'Registration Status',
            'registration_disabled_message' => 'Registration Disabled Message',
            'allow_offline_payment' => 'Office Payment Collection',

            'whatsapp_provider' => 'WhatsApp Provider',
            'twilio_sid' => 'Twilio SID',
            'twilio_token' => 'Twilio Token',
            'twilio_whatsapp_from' => 'Twilio WhatsApp From',
            'vonage_key' => 'Vonage API Key',
            'vonage_secret' => 'Vonage API Secret',
            'vonage_whatsapp_from' => 'Vonage WhatsApp From',
            'whatsapp_api_url' => 'WhatsApp API URL',
            'whatsapp_api_key' => 'WhatsApp API Key',

            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];

        if (!isset($fieldNames[$field])) {
            $formatted = ucfirst(str_replace('_', ' ', $field));
            $formatted = str_replace(
                ['Mtn', 'Momo', 'Api', 'Sid', 'Id', 'Url'],
                ['MTN', 'MoMo', 'API', 'SID', 'ID', 'URL'],
                $formatted
            );
            return $formatted;
        }

        return $fieldNames[$field];
    }

    private function formatChangesSummary(array $changedFields): string
    {
        $changes = [];

        foreach ($changedFields as $field => $change) {
            $fieldName = $change['field_name'];

            if (in_array($field, ['system_email'])) {
                $changes[] = "{$fieldName} updated";
            } else if ($field === 'system_logo') {
                $changes[] = "Logo updated";
            } else if ($field === 'system_favicon') {
                $status = $change['new'] !== 'removed' && !empty($change['new']) ? 'uploaded' : 'removed';
                $changes[] = "Favicon {$status}";
            } else if ($field === 'sms_sender_id') {
                $old = $change['old'] ?: '(empty)';
                $new = $change['new'] ?: '(empty)';
                $changes[] = "SMS Sender ID: {$old} → {$new}";
            } else if ($field === 'allow_registration') {
                $status = $change['new'] ? 'enabled' : 'disabled';
                $changes[] = "Registration {$status}";
            } else if ($field === 'auto_generate_invoices') {
                $status = $change['new'] ? 'enabled' : 'disabled';
                $changes[] = "Auto invoice {$status}";
            } else if ($field === 'send_payment_reminders') {
                $status = $change['new'] ? 'enabled' : 'disabled';
                $daysChanged = isset($changedFields['reminder_days_before']);
                if ($daysChanged) {
                    $changes[] = "Reminders {$status} with days changed";
                } else {
                    $changes[] = "Reminders {$status}";
                }
            } else if ($field === 'reminder_days_before') {
                if (!isset($changedFields['send_payment_reminders'])) {
                    $changes[] = "Reminder days: {$change['old']} → {$change['new']}";
                }
            } else if (in_array($field, [
    'enable_expresspay',
    'enable_hubtel',
    'enable_paystack',
    'enable_flutterwave',
    'sms_notifications_enabled',
    'sms_reminder_enabled',
    'sms_payment_confirmation_enabled',
    'enable_whatsapp_notifications',
    'whatsapp_reminder_enabled',
])) {
    $status = $change['new'] ? 'enabled' : 'disabled';
    $changes[] = "{$fieldName} {$status}";
} else if ($field === 'allow_offline_payment') {        // ← ADD THIS BRANCH
    $status = $change['new'] ? 'enabled' : 'blocked';
    $changes[] = "Office payments {$status}";
} else {
    $oldValue = is_bool($change['old']) ? ($change['old'] ? 'Yes' : 'No') : $change['old'];
    $newValue = is_bool($change['new']) ? ($change['new'] ? 'Yes' : 'No') : $change['new'];
    $changes[] = "{$fieldName}: {$oldValue} → {$newValue}";
}
        }

        return implode(', ', array_slice($changes, 0, 3)) . (count($changes) > 3 ? ' and more...' : '');
    }

    private function isCriticalChange(array $changedFields): bool
    {
        $criticalFields = [
            'system_email',
            'monthly_dues_amount',
            'currency_code',
            'allow_registration',
            'auto_generate_invoices',
            'send_payment_reminders'
        ];

        foreach ($criticalFields as $field) {
            if (isset($changedFields[$field])) {
                return true;
            }
        }

        return false;
    }

    /* ============================================================
     | CONFIG UPDATE — QUEUE / IMMEDIATE
     | ------------------------------------------------------------
     | These are internal helpers invoked by store()/update() — not
     | user-facing endpoints. They inherit the gating from their
     | callers, so they don't need their own assertBillingAllowsWrite.
     * ============================================================ */

    protected function queueSystemConfigurationUpdate($settings, $requestData, $action = 'update')
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
                    (new \App\Jobs\UpdateSystemConfiguration(
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

            \App\Jobs\UpdateSystemConfiguration::dispatch(
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

    protected function processSystemConfigurationImmediately($settings, $requestData, $action = 'update')
    {
        try {
            $envData = $this->getSystemEnvData($settings, $requestData, $action);

            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $this->backupEnvFile();

            $this->updateEnvFile($envData);

            Artisan::call('config:clear');
            sleep(1);
            Artisan::call('config:cache');
            sleep(2);

            if (function_exists('app')) {
                app()->loadEnvironmentFrom('.env');

                try {
                    app()->forgetInstance('config');
                    $config = require base_path('bootstrap/app.php');
                    app()->instance('config', new \Illuminate\Config\Repository(
                        require base_path('bootstrap/cache/config.php')
                    ));
                } catch (\Throwable $rebindEx) {
                    Log::debug('Config rebind after env update skipped: ' . $rebindEx->getMessage());
                }
            }

            $updatedValues = [];
            foreach ($envData as $key => $expectedValue) {
                $actualValue = env($key);
                $updatedValues[$key] = [
                    'expected' => $expectedValue,
                    'actual'   => $actualValue,
                    'match'    => $this->envValuesMatch($expectedValue, $actualValue),
                ];
            }

            Log::info("System configuration update verification", $updatedValues);

            $failedUpdates = array_filter($updatedValues, function($item) {
                return !$item['match'];
            });

            if (!empty($failedUpdates)) {
                Log::warning("Some environment variables failed to update", $failedUpdates);
                throw new \Exception("Some configuration values failed to update. Please try again.");
            }

            Log::info("System configuration updated successfully", [
                'action'       => $action,
                'verification' => $updatedValues
            ]);

            return redirect()->route('admin.system-settings.index')
                ->with('success', "✅ System settings " . ($action === 'create' ? 'created' : 'updated') . " successfully!")
                ->with('system_update_status', 'success');

        } catch (\Exception $e) {
            Log::error("Failed to update system configuration immediately: " . $e->getMessage());
            $this->restoreEnvBackup();

            return redirect()->back()
                ->with('error', '❌ Failed to update system configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    protected function getSystemEnvData($settings, $requestData, $action)
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

    protected function envValuesMatch($expected, $actual): bool
    {
        if ($expected === $actual) {
            return true;
        }

        $normalize = function ($value) {
            if (is_string($value)) {
                return trim($value, " \t\n\r\0\x0B\"'");
            }
            return $value;
        };

        return $normalize($expected) === $normalize($actual);
    }

    /* ============================================================
     | TEST system configuration — read-only / diagnostic
     * ============================================================ */
    public function testSystemConfiguration(Request $request)
    {
        $request->validate([
            'test_type' => 'required|in:email,payments,all,invoice',
            'queue'     => 'sometimes|boolean'
        ]);

        $testType  = $request->test_type;
        $queueTest = $request->boolean('queue', false);
        $tester    = auth()->user();

        try {
            if ($queueTest && config('queue.default') !== 'sync') {
                TestSystemConnection::dispatch($testType, $tester->id);

                return response()->json([
                    'success'   => true,
                    'message'   => '🔧 System configuration test queued for background processing',
                    'test_type' => $testType,
                    'queued'    => true,
                    'timestamp' => now()->toISOString()
                ]);
            }

            Artisan::call('config:clear');
            $result = $this->runSystemConfigurationTest($testType);

            $this->notifySuperAdminsAboutTestResults($result, $testType, $tester);

            $response = [
                'success'    => $result['success'],
                'message'    => $result['success'] ? '✅ ' . $result['message'] : '❌ ' . $result['message'],
                'test_type'  => $testType,
                'details'    => $result['details'] ?? [],
                'error_code' => $result['error_code'] ?? null,
                'timestamp'  => $result['timestamp'] ?? now()->toISOString(),
                'queued'     => false
            ];

            if ($result['success']) {
                Log::info("System configuration test successful for {$testType}", $response);
                return response()->json($response);
            }

            Log::warning("System configuration test failed for {$testType}", $response);
            return response()->json($response, 400);

        } catch (\Exception $e) {
            Log::error("System configuration test error for {$testType}: " . $e->getMessage());
            return response()->json([
                'success'    => false,
                'message'    => '❌ System configuration test failed: ' . $e->getMessage(),
                'test_type'  => $testType,
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
                'queued'     => false
            ], 500);
        }
    }

    protected function runSystemConfigurationTest($testType)
    {
        $results = [];

        try {
            switch ($testType) {
                case 'email':
                    $results = $this->environmentService->testEmailConfiguration();
                    break;

                case 'payments':
                    $results = $this->paymentService->testPaymentConfiguration();
                    break;

                case 'invoice':
                    $results = $this->testInvoiceConfiguration();
                    break;

                case 'all':
                    $emailResults   = $this->environmentService->testEmailConfiguration();
                    $paymentResults = $this->paymentService->testPaymentConfiguration();
                    $invoiceResults = $this->testInvoiceConfiguration();

                    $results = [
                        'success' => $emailResults['success'] && $paymentResults['success'] && $invoiceResults['success'],
                        'message' => 'Comprehensive system test completed',
                        'details' => [
                            'email'    => $emailResults,
                            'payments' => $paymentResults,
                            'invoice'  => $invoiceResults
                        ]
                    ];
                    break;
            }

            return $results;

        } catch (\Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Test execution failed: ' . $e->getMessage(),
                'error_code' => 'TEST_EXECUTION_ERROR'
            ];
        }
    }

    protected function testInvoiceConfiguration(): array
    {
        try {
            $settings = SystemSetting::getSettings();
            $issues   = [];

            if ($settings->isAutoInvoiceGenerationEnabled()) {
                $readiness = $settings->isReadyForAutoInvoiceGeneration();
                if (!$readiness['ready']) {
                    $issues = array_merge($issues, $readiness['issues']);
                }
            }

            if ($settings->shouldSendPaymentReminders()) {
                if ($settings->reminder_days_before < 1 || $settings->reminder_days_before > 30) {
                    $issues[] = "Reminder days ({$settings->reminder_days_before}) is outside valid range (1-30)";
                }
            }

            if ($settings->grace_period_days < 0 || $settings->grace_period_days > 30) {
                $issues[] = "Grace period ({$settings->grace_period_days}) is outside valid range (0-30)";
            }

            if ($settings->late_payment_percentage > 0 && $settings->fixed_penalty_amount > 0) {
                $issues[] = "Both percentage and fixed penalty are set. Only one should be used.";
            }

            $sampleProperty    = Property::where('status', 'active')->first();
            $calculationResult = null;
            if ($sampleProperty) {
                $amount = $settings->calculateMonthlyDuesForProperty($sampleProperty);
                $calculationResult = [
                    'property'          => $sampleProperty->id,
                    'calculated_amount' => $amount,
                    'formatted_amount'  => $settings->formatAmount($amount)
                ];
            }

            return [
                'success' => empty($issues),
                'message' => empty($issues) ? 'Invoice configuration is valid' : 'Invoice configuration has issues',
                'details' => [
                    'settings' => [
                        'auto_generate_invoices'  => $settings->auto_generate_invoices,
                        'send_payment_reminders'  => $settings->send_payment_reminders,
                        'reminder_days_before'    => $settings->reminder_days_before,
                        'grace_period_days'       => $settings->grace_period_days,
                        'late_payment_percentage' => $settings->late_payment_percentage,
                        'fixed_penalty_amount'    => $settings->fixed_penalty_amount
                    ],
                    'issues' => $issues,
                    'sample_calculation' => $calculationResult
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Invoice test failed: ' . $e->getMessage(),
                'error_code' => 'INVOICE_TEST_ERROR'
            ];
        }
    }

    /* ============================================================
     | DESTROY — admin write operation
     * ============================================================ */
    public function destroy()
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('delete_system_settings');

        try {
            $settings = SystemSetting::getSettings();
            $deleter  = auth()->user();

            if ($settings->system_logo) {
                $logoController = app(LogoController::class);
                $logoController->deleteLogoFile($settings->system_logo);
            }

            if ($settings->hasFavicon()) {
                $this->deleteFaviconFile($settings->system_favicon);
            }

            $settings->delete();

            $this->notifySuperAdminsAboutSettingsDeletion($settings, $deleter);

            return redirect()->route('admin.system-settings.index')
                ->with('success', '✅ System settings deleted successfully!');

        } catch (\Exception $e) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', '❌ Error deleting system settings: ' . $e->getMessage());
        }
    }

    private function notifySuperAdminsAboutSettingsDeletion(SystemSetting $settings, User $deleter)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title' => '🗑️ System Settings Deleted',
                'message' => "System settings have been deleted by {$deleter->name}. The system may not function properly.",
                'icon' => 'fas fa-trash-alt text-danger',
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.restore'),
                'priority' => 3,
                'data' => [
                    'type' => 'system_settings_deleted',
                    'settings_id' => $settings->id,
                    'system_name' => $settings->system_name,
                    'deleted_by' => $deleter->id,
                    'deleted_by_name' => $deleter->name,
                    'timestamp' => now()->toISOString(),
                    'can_restore' => true,
                    'notification_type' => 'in_app_only'
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::warning('✅ Notified super admins about system settings deletion', [
                'admin_count' => $superAdmins->count(),
                'settings_id' => $settings->id,
                'deleter_id'  => $deleter->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about settings deletion: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | RESTORE — admin write operation
     * ============================================================ */
    public function restore()
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('restore_system_settings');

        try {
            $settings = SystemSetting::withTrashed()->first();
            $restorer = auth()->user();

            if ($settings) {
                $settings->restore();

                $this->notifySuperAdminsAboutSettingsRestoration($settings, $restorer);

                return redirect()->route('admin.system-settings.index')
                    ->with('success', '✅ System settings restored successfully!');
            }

            return redirect()->route('admin.system-settings.index')
                ->with('error', '❌ No deleted system settings found.');

        } catch (\Exception $e) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', '❌ Error restoring system settings: ' . $e->getMessage());
        }
    }

    private function notifySuperAdminsAboutSettingsRestoration(SystemSetting $settings, User $restorer)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $registrationStatus = $settings->isRegistrationAllowed() ? 'enabled' : 'disabled';
            $invoiceStatus      = $settings->isAutoInvoiceGenerationEnabled() ? 'enabled' : 'disabled';
            $faviconStatus      = $settings->hasFavicon() ? 'restored' : 'not available';

            $notificationData = [
                'title' => '🔄 System Settings Restored',
                'message' => "System settings have been restored by {$restorer->name}. Registration: {$registrationStatus}, Auto Invoice: {$invoiceStatus}, Favicon: {$faviconStatus}.",
                'icon' => 'fas fa-redo text-success',
                'category' => 'system_settings',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 1,
                'data' => [
                    'type' => 'system_settings_restored',
                    'settings_id' => $settings->id,
                    'system_name' => $settings->system_name,
                    'restored_by' => $restorer->id,
                    'restored_by_name' => $restorer->name,
                    'timestamp' => now()->toISOString(),
                    'registration_allowed' => $settings->allow_registration,
                    'auto_generate_invoices' => $settings->auto_generate_invoices,
                    'has_favicon' => $settings->hasFavicon(),
                    'notification_type' => 'in_app_only'
                ]
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('✅ Notified super admins about system settings restoration', [
                'admin_count' => $superAdmins->count(),
                'settings_id' => $settings->id,
                'restorer_id' => $restorer->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to notify super admins about settings restoration: ' . $e->getMessage());
        }
    }

    /* ========== HELPER METHODS ========== */

    protected function generateSystemSlug($name): string
    {
        $slug = Str::slug($name);

        $count = SystemSetting::where('system_short_name', $slug)->count();

        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        return $slug;
    }

    protected function backupEnvFile()
    {
        try {
            $envPath    = base_path('.env');
            $backupPath = base_path('.env.backup.system.' . date('Y-m-d-H-i-s'));

            if (File::exists($envPath)) {
                File::copy($envPath, $backupPath);
                Log::info('.env file backed up to: ' . $backupPath);
                return $backupPath;
            } else {
                Log::warning('.env file not found for backup');
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
            return false;
        }
    }

    protected function restoreEnvBackup()
    {
        try {
            $backups = File::glob(base_path('.env.backup.system.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                File::copy($latestBackup, base_path('.env'));
                Log::info('Restored .env from backup: ' . $latestBackup);
                return true;
            }
            Log::warning('No system backups found to restore');
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found at: ' . $envPath);
        }

        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable. Check file permissions.');
        }

        $envContent = File::get($envPath);
        $updated = false;

        foreach ($data as $key => $value) {
            $escapedValue = $this->escapeEnvValue($value);

            $pattern = "/^{$key}\s*=\s*.*/m";
            $replacement = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
                Log::debug("Updated existing env variable: {$key}={$escapedValue}");
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
                Log::debug("Added new env variable: {$key}={$escapedValue}");
            }
        }

        if ($updated) {
            Artisan::call('config:clear');

            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";

            File::put($envPath, $envContent);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            Log::info('Updated .env file with system configuration', $data);
        } else {
            Log::warning('No changes made to .env file');
        }

        return $updated;
    }

    protected function escapeEnvValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (empty($value) && $value !== '0') {
            return '""';
        }

        if (preg_match('/[\\s#"\'\\\\`$!*?[\]{}|&;()<>]/', $value) || empty($value)) {
            $value = str_replace(['"', '\\'], ['\"', '\\\\'], $value);
            return '"' . $value . '"';
        }

        return $value;
    }

    protected function validateSettings(array $data, bool $isUpdate = false): \Illuminate\Validation\Validator
    {
        $rules = [
            'system_name'       => ($isUpdate ? 'sometimes|' : 'required|') . 'string|max:255',
            'system_short_name' => ($isUpdate ? 'sometimes|' : 'required|') . 'string|max:50|alpha_dash|unique:system_settings,system_short_name' . ($isUpdate ? ',' . SystemSetting::getSettings()->id : ''),
            'system_email'      => ($isUpdate ? 'sometimes|' : 'required|') . 'email|max:255',

            'system_email_password' => $isUpdate
                ? 'nullable|string|min:0'
                : 'required|string|min:1',

            'system_phone'      => ($isUpdate ? 'sometimes|' : 'required|') . 'string|max:20',
            'system_address'    => 'nullable|string|max:500',
            'system_logo'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_logo'       => 'nullable|boolean',

            'system_favicon'    => 'nullable|image|mimes:ico,png,svg,jpg,jpeg,gif|max:100',
            'remove_favicon'    => 'nullable|boolean',

            'sms_sender_id'     => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Za-z0-9 _\-]*$/',
            ],

            'currency_code'     => ($isUpdate ? 'sometimes|' : 'required|') . 'string|size:3',
            'currency_symbol'   => ($isUpdate ? 'sometimes|' : 'required|') . 'string|max:5',
            'currency_position' => ($isUpdate ? 'sometimes|' : 'required|') . 'in:left,right,left_with_space,right_with_space',
            'decimal_places'    => ($isUpdate ? 'sometimes|' : 'required|') . 'integer|min:0|max:4',

            'monthly_dues_amount' => ($isUpdate ? 'sometimes|' : 'required|') . 'numeric|min:0',
            'calculation_method'  => ($isUpdate ? 'sometimes|' : 'required|') . 'in:fixed,per_property',
            'per_property_amount' => 'required_if:calculation_method,per_property|numeric|min:0',

            'enable_tenant_invoicing'        => 'sometimes|boolean',
            'tenant_monthly_dues_amount'     => 'nullable|numeric|min:0',
            'tenant_calculation_method'      => 'nullable|in:fixed,per_property_unit',
            'auto_generate_tenant_invoices'  => 'sometimes|boolean',
            'send_tenant_payment_reminders'  => 'sometimes|boolean',
            'tenant_grace_period_days'       => 'nullable|integer|min:0|max:30',
            'tenant_late_payment_percentage' => 'nullable|numeric|min:0|max:100',
            'tenant_fixed_penalty_amount'    => 'nullable|numeric|min:0',

            'grace_period_days'       => ($isUpdate ? 'sometimes|' : 'required|') . 'integer|min:0|max:30',
            'late_payment_percentage' => ($isUpdate ? 'sometimes|' : 'required|') . 'numeric|min:0|max:100',
            'fixed_penalty_amount'    => 'nullable|numeric|min:0',

            'auto_generate_invoices' => ($isUpdate ? 'sometimes|' : 'required|') . 'boolean',
            'send_payment_reminders' => ($isUpdate ? 'sometimes|' : 'required|') . 'boolean',
            'reminder_days_before'   => ($isUpdate ? 'sometimes|' : 'required|') . 'integer|min:1|max:30',

            'enable_expresspay'  => 'sometimes|boolean',
            'enable_hubtel'      => 'sometimes|boolean',
            'enable_paystack'    => 'sometimes|boolean',
            'enable_flutterwave' => 'sometimes|boolean',

            'sms_notifications_enabled'         => 'sometimes|boolean',
            'sms_reminder_enabled'              => 'sometimes|boolean',
            'sms_payment_confirmation_enabled'  => 'sometimes|boolean',
            'enable_whatsapp_notifications'     => 'sometimes|boolean',
            'whatsapp_reminder_enabled'         => 'sometimes|boolean',
            'sms_daily_limit_per_user'          => 'nullable|integer|min:1|max:100',
            'sms_hourly_limit_per_user'         => 'nullable|integer|min:1|max:20',
            'whatsapp_provider'                 => 'nullable|in:twilio,vonage,custom',

            'invoice_notification_channels'    => 'sometimes|array',
            'invoice_notification_channels.*'  => 'in:email,sms,whatsapp',
            'payment_reminder_channels'        => 'sometimes|array',
            'payment_reminder_channels.*'      => 'in:email,sms,whatsapp',
            'overdue_notification_channels'    => 'sometimes|array',
            'overdue_notification_channels.*'  => 'in:email,sms,whatsapp',
            'payment_confirmation_channels'    => 'sometimes|array',
            'payment_confirmation_channels.*'  => 'in:email,sms,whatsapp',

            'enable_bulk_payments'  => ($isUpdate ? 'sometimes|' : 'required|') . 'boolean',
            'max_bulk_months'       => ($isUpdate ? 'sometimes|' : 'required|') . 'integer|min:1|max:12',
            'bulk_payment_discount' => 'nullable|numeric|min:0|max:100',

            'allow_registration'            => 'sometimes|boolean',
            'allow_offline_payment'         => 'sometimes|boolean',
            'registration_disabled_message' => 'nullable|string|max:500',
        ];

        $messages = [
            'system_short_name.alpha_dash' => 'System short name can only contain letters, numbers, dashes and underscores',
            'system_short_name.unique'     => 'This system short name is already taken',
            'system_email.email'           => 'Please enter a valid email address',
            'currency_code.required'       => 'Currency code is required',
            'currency_code.size'           => 'Currency code must be exactly 3 characters',
            'currency_symbol.required'     => 'Currency symbol is required',
            'currency_position.required'   => 'Currency position is required',
            'decimal_places.required'      => 'Number of decimal places is required',
            'monthly_dues_amount.required' => 'Monthly dues amount is required',
            'calculation_method.required'  => 'Calculation method is required',
            'grace_period_days.required'   => 'Grace period days is required',
            'late_payment_percentage.required' => 'Late payment percentage is required',
            'auto_generate_invoices.required'  => 'Auto generate invoices selection is required',
            'send_payment_reminders.required'  => 'Send payment reminders selection is required',
            'reminder_days_before.required'    => 'Reminder days before is required',
            'enable_bulk_payments.required'    => 'Enable bulk payments selection is required',
            'max_bulk_months.required'         => 'Maximum bulk months is required',
            'system_logo.image'  => 'The logo must be a valid image file',
            'system_logo.mimes'  => 'The logo must be a JPEG, PNG, GIF, or WebP image',
            'system_logo.max'    => 'The logo size must not exceed 2MB',
            'system_email_password.required' => 'Email password is required for SMTP authentication when creating settings.',
            'system_email_password.min'      => 'Email password must be at least 1 character.',
            'tenant_monthly_dues_amount.min' => 'Tenant monthly dues cannot be negative',
            'tenant_grace_period_days.integer' => 'Tenant grace period must be a whole number',
            'tenant_late_payment_percentage.min' => 'Tenant late payment percentage cannot be negative',
            'tenant_late_payment_percentage.max' => 'Tenant late payment percentage cannot exceed 100%',
            'system_favicon.image' => 'The favicon must be a valid image file',
            'system_favicon.mimes' => 'The favicon must be an ICO, PNG, SVG, JPG, or GIF image',
            'system_favicon.max'   => 'The favicon size must not exceed 100KB',
            'sms_sender_id.max'    => 'SMS Sender ID cannot exceed 11 characters.',
            'sms_sender_id.regex'  => 'SMS Sender ID may only contain letters, numbers, spaces, hyphens, and underscores.',
        ];

        $validator = Validator::make($data, $rules, $messages);

        $validator->after(function ($validator) use ($data) {
            $enable_expresspay  = filter_var($data['enable_expresspay']  ?? false, FILTER_VALIDATE_BOOLEAN);
            $enable_hubtel      = filter_var($data['enable_hubtel']      ?? false, FILTER_VALIDATE_BOOLEAN);
            $enable_paystack    = filter_var($data['enable_paystack']    ?? false, FILTER_VALIDATE_BOOLEAN);
            $enable_flutterwave = filter_var($data['enable_flutterwave'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $atLeastOneEnabled = $enable_expresspay || $enable_hubtel || $enable_paystack || $enable_flutterwave;

            if (!$atLeastOneEnabled) {
                $validator->errors()->add(
                    'enable_expresspay',
                    'At least one payment gateway must be enabled to create system settings.'
                );
            }

            if (isset($data['enable_tenant_invoicing']) && filter_var($data['enable_tenant_invoicing'], FILTER_VALIDATE_BOOLEAN)) {
                if (empty($data['tenant_monthly_dues_amount']) || $data['tenant_monthly_dues_amount'] <= 0) {
                    $validator->errors()->add(
                        'tenant_monthly_dues_amount',
                        'Tenant monthly dues amount is required and must be greater than 0 when tenant invoicing is enabled.'
                    );
                }
            }

            $hasPercentagePenalty = !empty($data['late_payment_percentage']) && $data['late_payment_percentage'] > 0;
            $hasFixedPenalty      = !empty($data['fixed_penalty_amount']) && $data['fixed_penalty_amount'] > 0;

            if ($hasPercentagePenalty && $hasFixedPenalty) {
                $validator->errors()->add(
                    'fixed_penalty_amount',
                    'Please use either percentage-based penalty OR fixed penalty amount, not both.'
                );
            }

            if (isset($data['send_payment_reminders']) && filter_var($data['send_payment_reminders'], FILTER_VALIDATE_BOOLEAN)) {
                if (empty($data['reminder_days_before']) || $data['reminder_days_before'] < 1) {
                    $validator->errors()->add(
                        'reminder_days_before',
                        'Reminder days before due date is required when payment reminders are enabled.'
                    );
                }
            }

            if (isset($data['grace_period_days'], $data['reminder_days_before']) &&
                $data['reminder_days_before'] > $data['grace_period_days']) {
                $validator->errors()->add(
                    'reminder_days_before',
                    'Reminder days cannot exceed the grace period. Reminders would be sent after invoices are already overdue.'
                );
            }

            if (!empty($data['system_short_name']) && preg_match('/--/', $data['system_short_name'])) {
                $validator->errors()->add(
                    'system_short_name',
                    'System short name cannot contain consecutive hyphens.'
                );
            }

            if (isset($data['enable_bulk_payments']) && filter_var($data['enable_bulk_payments'], FILTER_VALIDATE_BOOLEAN)) {
                if (empty($data['max_bulk_months']) || $data['max_bulk_months'] < 2) {
                    $validator->errors()->add(
                        'max_bulk_months',
                        'Maximum bulk months must be at least 2 when bulk payments are enabled.'
                    );
                }
            }

            if (isset($data['system_favicon']) && $data['system_favicon']) {
                $favicon = $data['system_favicon'];
                if (is_string($favicon)) {
                    $extension = strtolower(pathinfo($favicon, PATHINFO_EXTENSION));
                    $validExtensions = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'gif'];
                    if (!in_array($extension, $validExtensions)) {
                        $validator->errors()->add(
                            'system_favicon',
                            'Favicon must be an ICO, PNG, SVG, JPG, or GIF file.'
                        );
                    }
                }
            }

            if (isset($data['system_email_password']) && !empty($data['system_email_password'])) {
                if (strlen($data['system_email_password']) < 1) {
                    $validator->errors()->add(
                        'system_email_password',
                        'Email password must be at least 1 character if provided.'
                    );
                }
            }
        });

        return $validator;
    }

    /* ============================================================
     | GET system settings — read-only API
     * ============================================================ */
    public function getSystemSettings()
    {
        try {
            $settings = SystemSetting::getSettings();

            return response()->json([
                'system_name'       => $settings->system_name,
                'system_short_name' => $settings->system_short_name,
                'system_logo'       => $settings->system_logo,
                'system_favicon'    => $settings->getFaviconUrl(),
                'system_email'      => $settings->system_email,
                'system_phone'      => $settings->system_phone,
                'sms_sender_id'     => $settings->sms_sender_id,
                'registration_allowed' => $settings->isRegistrationAllowed(),
                'registration_disabled_message' => $settings->getRegistrationDisabledMessage(),
                'auto_generate_invoices' => $settings->isAutoInvoiceGenerationEnabled(),
                'send_payment_reminders' => $settings->shouldSendPaymentReminders(),
                'reminder_days_before'   => $settings->getReminderDaysBefore(),
                'enabled_gateways' => [
                    'expresspay'  => $settings->enable_expresspay ?? false,
                    'hubtel'      => $settings->enable_hubtel ?? false,
                    'paystack'    => $settings->enable_paystack ?? false,
                    'flutterwave' => $settings->enable_flutterwave ?? false,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'system_name'       => 'Hilltop Estate',
                'system_short_name' => 'hsm',
                'system_logo'       => null,
                'system_favicon'    => null,
                'sms_sender_id'     => null,
                'registration_allowed' => true,
                'registration_disabled_message' => null,
                'auto_generate_invoices' => false,
                'send_payment_reminders' => false,
                'reminder_days_before'   => 7,
                'enabled_gateways' => [
                    'expresspay'  => false,
                    'hubtel'      => false,
                    'paystack'    => false,
                    'flutterwave' => false,
                ]
            ]);
        }
    }

    /* ============================================================
     | TEST notification system — read-only / diagnostic
     * ============================================================ */
    public function testNotificationSystem()
    {
        try {
            $user     = auth()->user();
            $settings = SystemSetting::getSettings();

            $testData = [
                'title' => '🔔 Test Notification',
                'message' => 'This is a test notification from the system settings controller. Registration: ' .
                             ($settings->isRegistrationAllowed() ? 'enabled' : 'disabled') .
                             ', Auto Invoice: ' . ($settings->isAutoInvoiceGenerationEnabled() ? 'enabled' : 'disabled') .
                             ', Reminders: ' . ($settings->shouldSendPaymentReminders() ? 'enabled' : 'disabled'),
                'icon' => 'fas fa-bell text-primary',
                'category' => 'test',
                'action_url' => route('admin.system-settings.index'),
                'priority' => 1,
                'data' => [
                    'type' => 'test',
                    'timestamp' => now()->toISOString(),
                    'registration_allowed' => $settings->allow_registration,
                    'auto_generate_invoices' => $settings->auto_generate_invoices,
                    'send_payment_reminders' => $settings->send_payment_reminders,
                    'notification_type' => 'in_app_only'
                ]
            ];

            $this->notifyUserWithData($user, $testData);

            return redirect()->back()
                ->with('success', '✅ Test notification sent! Check your notifications panel.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', '❌ Failed to send test notification: ' . $e->getMessage());
        }
    }

    protected function clearSettingsReminders(): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            foreach ($superAdmins as $admin) {
                \Illuminate\Support\Facades\Cache::forget("user_{$admin->id}_last_settings_reminder");
                \Illuminate\Support\Facades\Cache::forget("user_{$admin->id}_last_reminder_type");

                if (session()->has("settings_reminder_{$admin->id}")) {
                    session()->forget("settings_reminder_{$admin->id}");
                }
            }

            \Illuminate\Support\Facades\Cache::forget('last_settings_reminder_sent');
            \Illuminate\Support\Facades\Cache::forget('settings_reminder_active');

            if (session()->has('pending_system_update')) {
                session()->forget('pending_system_update');
            }

            Log::info('✅ Cleared all system settings reminders', [
                'super_admins_cleared' => $superAdmins->count(),
                'cleared_by'           => auth()->id(),
                'cleared_by_name'      => auth()->user()->name,
                'timestamp'            => now()->setTimezone('UTC')->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to clear settings reminders: ' . $e->getMessage());
        }
    }

    protected function hasSettingsBeenConfiguredBefore(array $oldSettings): bool
    {
        if (empty($oldSettings)) {
            return false;
        }

        $wasMissing = !isset($oldSettings['id']) ||
                      empty($oldSettings['system_name']) ||
                      empty($oldSettings['system_email']);

        return !$wasMissing;
    }

    public function testReminderSystem(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can test the reminder system'
                ], 403);
            }

            $user->notify(new \App\Notifications\SystemSettingsReminderNotification('test', 0));

            Log::info('Test reminder sent to super admin', [
                'admin_id'    => $user->id,
                'admin_email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test reminder sent successfully. Check your notifications.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send test reminder: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test reminder: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ========== FAVICON HELPERS ========== */

    protected function handleFaviconUpload($file): string
    {
        try {
            $filename = 'favicon-' . time() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs('favicons', $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to store favicon');
            }

            Log::info('Favicon uploaded successfully', [
                'path'      => $path,
                'filename'  => $filename,
                'size'      => $file->getSize(),
                'mime_type' => $file->getMimeType()
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

    /* ============================================================
     | UPDATE status — read-only diagnostic
     * ============================================================ */
    public function updateStatus()
    {
        try {
            $settings = SystemSetting::getSettings();
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
                'done'           => (bool) $done,
                'app_name'       => $envAppName,
                'db_name'        => $dbAppName,
                'pending_jobs'   => $pendingJobs,
                'failed_jobs'    => $failedJobs,
                'queue_driver'   => config('queue.default'),
                'checked_at'     => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'done'  => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | RETRY env update — admin write operation
     * ============================================================ */
    public function retryEnvUpdate()
    {
        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('retry_env_update');

        try {
            $settings = SystemSetting::getSettings();

            $envData = [
                'APP_NAME'          => $settings->system_name,
                'APP_SHORT_NAME'    => $settings->system_short_name,
                'MAIL_FROM_ADDRESS' => $settings->system_email,
            ];

            if (!empty($settings->sms_sender_id)) {
                $envData['DEFAULT_SMS_SENDER_ID'] = $settings->sms_sender_id;
            }

            \App\Jobs\UpdateSystemConfiguration::dispatch(
                $settings,
                $envData,
                auth()->id(),
                'manual_retry'
            );

            return redirect()->route('admin.system-settings.index')
                ->with('success', '✅ Configuration update queued for background processing.')
                ->with('system_update_status', 'processing');
        } catch (\Throwable $e) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', '❌ Failed to queue configuration update: ' . $e->getMessage());
        }
    }
}