<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SystemSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // System Identification
        'system_name',
        'system_short_name',
        'system_email',
        'system_phone',
        'system_address',
        'system_logo',
        'system_favicon',

        // Default SMS Sender ID (admin-editable, overridden by per-provider values)
        'sms_sender_id',

        // Currency Configuration
        'currency_code',
        'currency_symbol',
        'currency_position',
        'decimal_places',
        
        // Dues Configuration
        'monthly_dues_amount',
        'calculation_method',
        'per_property_amount',
        
        // Tenant Dues Configuration
        'enable_tenant_invoicing',
        'tenant_monthly_dues_amount',
        'tenant_calculation_method',
        'auto_generate_tenant_invoices',
        'send_tenant_payment_reminders',
        'tenant_grace_period_days',
        'tenant_late_payment_percentage',
        'tenant_fixed_penalty_amount',
        
        // Payment Terms
        'grace_period_days',
        'late_payment_percentage',
        'fixed_penalty_amount',
        
        // System Operations
        'auto_generate_invoices',
        'send_payment_reminders',
        'reminder_days_before',
        
        // Payment Gateways
        'enable_expresspay',
        'enable_hubtel',
        'enable_paystack',
        'enable_flutterwave',

        // Bulk Payment Settings
        'enable_bulk_payments',
        'max_bulk_months',
        'bulk_payment_discount',
        
        // WhatsApp Configuration
        'whatsapp_provider',
        'twilio_sid',
        'twilio_token',
        'twilio_whatsapp_from',
        'vonage_key',
        'vonage_secret',
        'vonage_whatsapp_from',
        'whatsapp_api_url',
        'whatsapp_api_key',
        
        // Registration Control
        'allow_registration',
        'registration_disabled_message',
        
        // Notification Channel Settings
        'invoice_notification_channels',
        'payment_reminder_channels',
        'overdue_notification_channels',
        'payment_confirmation_channels',
        
        // SMS Settings
        'sms_notifications_enabled',
        'sms_reminder_enabled',
        'sms_payment_confirmation_enabled',
        'sms_daily_limit_per_user',
        'sms_hourly_limit_per_user',
        
        // WhatsApp Notification Settings
        'enable_whatsapp_notifications',
        'whatsapp_reminder_enabled',
        'whatsapp_payment_confirmation_enabled',
        
        // Additional Notification Settings
        'enable_notification_preferences',
        'force_email_fallback',
        'notification_retry_attempts',
        'notification_retry_delay_minutes',
        
        // Message Templates
        'sms_invoice_generated_template',
        'sms_payment_reminder_template',
        'sms_overdue_template',
        'sms_payment_confirmation_template',
        'whatsapp_invoice_generated_template',
        'whatsapp_payment_reminder_template',
        'whatsapp_overdue_template',
        'whatsapp_payment_confirmation_template',
        
        // Bulk Notification Settings
        'enable_bulk_notifications',
        'bulk_notification_batch_size',
        'bulk_notification_delay_seconds',
        
        // Notification Logging
        'log_all_notifications',
        'log_notification_content',
        'notification_log_retention_days',
        
        // Year-End Archive Settings
        'enable_year_end_archive',
        'year_end_archive_month',
        'year_end_archive_day',
        'archive_paid_invoices_only',
        'keep_unpaid_invoices',
        'paid_invoice_retention_months',
        'auto_archive_paid_after_retention',
        'notify_landlords_before_archive',
        'archive_notification_days_landlord',
        'send_yearly_archive_report_landlord',
        
        // Tenant Year-End Archive Settings
        'enable_year_end_archive_tenant',
        'year_end_archive_month_tenant',
        'year_end_archive_day_tenant',
        'paid_invoice_retention_months_tenant',
        'auto_archive_paid_after_retention_tenant',
        'notify_tenants_before_archive',
        'archive_notification_days_tenant',
        'send_yearly_archive_report_tenant',
        
        // Audit
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'monthly_dues_amount' => 'decimal:2',
        'per_property_amount' => 'decimal:2',
        'late_payment_percentage' => 'decimal:2',
        'fixed_penalty_amount' => 'decimal:2',
        'bulk_payment_discount' => 'decimal:2',
        
        // Tenant Dues Casts
        'enable_tenant_invoicing' => 'boolean',
        'tenant_monthly_dues_amount' => 'decimal:2',
        'auto_generate_tenant_invoices' => 'boolean',
        'send_tenant_payment_reminders' => 'boolean',
        'tenant_grace_period_days' => 'integer',
        'tenant_late_payment_percentage' => 'decimal:2',
        'tenant_fixed_penalty_amount' => 'decimal:2',
        
        'auto_generate_invoices' => 'boolean',
        'send_payment_reminders' => 'boolean',
        
        // Payment Gateway Casts
        'enable_expresspay' => 'boolean',
        'enable_hubtel' => 'boolean',
        'enable_paystack' => 'boolean',
        'enable_flutterwave' => 'boolean',
        
        'enable_bulk_payments' => 'boolean',
        'max_bulk_months' => 'integer',
        'decimal_places' => 'integer',
        'grace_period_days' => 'integer',
        'reminder_days_before' => 'integer',
        
        // WhatsApp Configuration Casts
        'whatsapp_provider' => 'string',
        
        // SMS Sender ID — plain string, nullable
        'sms_sender_id' => 'string',
        
        // Registration Control Casts
        'allow_registration' => 'boolean',
        
        // Notification Channel Casts
        'invoice_notification_channels' => 'array',
        'payment_reminder_channels' => 'array',
        'overdue_notification_channels' => 'array',
        'payment_confirmation_channels' => 'array',
        
        // SMS Settings Casts
        'sms_notifications_enabled' => 'boolean',
        'sms_reminder_enabled' => 'boolean',
        'sms_payment_confirmation_enabled' => 'boolean',
        'sms_daily_limit_per_user' => 'integer',
        'sms_hourly_limit_per_user' => 'integer',
        
        // WhatsApp Notification Casts
        'enable_whatsapp_notifications' => 'boolean',
        'whatsapp_reminder_enabled' => 'boolean',
        'whatsapp_payment_confirmation_enabled' => 'boolean',
        
        // Additional Notification Casts
        'enable_notification_preferences' => 'boolean',
        'force_email_fallback' => 'boolean',
        'notification_retry_attempts' => 'integer',
        'notification_retry_delay_minutes' => 'integer',
        
        // Bulk Notification Casts
        'enable_bulk_notifications' => 'boolean',
        'bulk_notification_batch_size' => 'integer',
        'bulk_notification_delay_seconds' => 'integer',
        
        // Notification Logging Casts
        'log_all_notifications' => 'boolean',
        'log_notification_content' => 'boolean',
        'notification_log_retention_days' => 'integer',
        
        // Year-End Archive Casts
        'enable_year_end_archive' => 'boolean',
        'year_end_archive_month' => 'integer',
        'year_end_archive_day' => 'integer',
        'archive_paid_invoices_only' => 'boolean',
        'keep_unpaid_invoices' => 'boolean',
        'paid_invoice_retention_months' => 'integer',
        'auto_archive_paid_after_retention' => 'boolean',
        'notify_landlords_before_archive' => 'boolean',
        'archive_notification_days_landlord' => 'integer',
        'send_yearly_archive_report_landlord' => 'boolean',
        
        // Tenant Year-End Archive Casts
        'enable_year_end_archive_tenant' => 'boolean',
        'year_end_archive_month_tenant' => 'integer',
        'year_end_archive_day_tenant' => 'integer',
        'paid_invoice_retention_months_tenant' => 'integer',
        'auto_archive_paid_after_retention_tenant' => 'boolean',
        'notify_tenants_before_archive' => 'boolean',
        'archive_notification_days_tenant' => 'integer',
        'send_yearly_archive_report_tenant' => 'boolean',
        
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        // Notification Channel Defaults
        'invoice_notification_channels' => '["email"]',
        'payment_reminder_channels' => '["email"]',
        'overdue_notification_channels' => '["email","sms"]',
        'payment_confirmation_channels' => '["email"]',
        
        // SMS Defaults
        'sms_notifications_enabled' => false,
        'sms_reminder_enabled' => false,
        'sms_payment_confirmation_enabled' => false,
        'sms_daily_limit_per_user' => 10,
        'sms_hourly_limit_per_user' => 3,
        
        // WhatsApp Defaults
        'enable_whatsapp_notifications' => false,
        'whatsapp_reminder_enabled' => false,
        'whatsapp_payment_confirmation_enabled' => false,
        
        // Additional Notification Defaults
        'enable_notification_preferences' => true,
        'force_email_fallback' => true,
        'notification_retry_attempts' => 3,
        'notification_retry_delay_minutes' => 5,
        
        // Bulk Notification Defaults
        'enable_bulk_notifications' => true,
        'bulk_notification_batch_size' => 50,
        'bulk_notification_delay_seconds' => 2,
        
        // Notification Logging Defaults
        'log_all_notifications' => true,
        'log_notification_content' => false,
        'notification_log_retention_days' => 90,
    ];

    // ========== RELATIONSHIPS ==========
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== STATIC METHODS WITH TABLE EXISTENCE CHECK ==========

    /**
     * Get system settings with graceful handling when table doesn't exist
     */
    public static function getSettings()
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                return self::getDefaultSettingsObject();
            }
            
            return Cache::remember('system_settings', 3600, function () {
                $settings = self::first();
                
                if (!$settings) {
                    return self::getDefaultSettingsObject();
                }
                
                return $settings;
            });
        } catch (\Exception $e) {
            return self::getDefaultSettingsObject();
        }
    }
    
    /**
     * Get default settings as a SystemSetting object
     */
    private static function getDefaultSettingsObject()
    {
        $settings = new self();
        $defaults = self::getDefaultSettings();
        
        foreach ($defaults as $key => $value) {
            $settings->$key = $value;
        }
        
        return $settings;
    }

    /**
     * Clear settings cache when updated
     */
    public static function clearCache()
    {
        Cache::forget('system_settings');

        // Also clear the global SMS sender ID cache so the next SMS
        // dispatch picks up a freshly-saved value.
        Cache::forget('sms_default_sender_id');
    }

    /**
     * Check if system settings exist
     */
    public static function exists(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                return false;
            }
            return self::count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get settings or fail if not found
     */
    public static function getSettingsOrFail(): self
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                throw new \Exception('System settings table does not exist. Please run migrations first.');
            }
            return static::firstOrFail();
        } catch (\Exception $e) {
            return self::getDefaultSettingsObject();
        }
    }

    /**
     * Check if system settings are configured
     */
    public static function isConfigured(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                return false;
            }
            return static::exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========== SMS SENDER ID METHODS (NEW) ==========

    /**
     * Check if a global default SMS sender ID is set.
     */
    public function hasSmsSenderId(): bool
    {
        return !empty($this->sms_sender_id) && trim((string) $this->sms_sender_id) !== '';
    }

    /**
     * Get the global default SMS sender ID, or null when unset.
     */
    public function getSmsSenderId(): ?string
    {
        return $this->hasSmsSenderId()
            ? trim((string) $this->sms_sender_id)
            : null;
    }

    /**
     * Get the effective SMS sender ID for a given provider, taking the
     * per-provider value into account first.
     *
     * Priority:
     *   1. Provider-specific sender_id (config/sms.php)
     *   2. Global default (this model's sms_sender_id)
     *   3. Final fallback
     */
    public function getEffectiveSmsSenderId(string $provider): string
    {
        $providerSenderId = config("sms.providers.{$provider}.sender_id");

        if (is_string($providerSenderId) && trim($providerSenderId) !== '') {
            return trim($providerSenderId);
        }

        if ($this->hasSmsSenderId()) {
            return $this->getSmsSenderId();
        }

        $fallback = config('sms.sender_id');

        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        return 'SYSTEM';
    }

    /**
     * Diagnostic — where did the sender ID come from for this provider?
     */
    public function explainSmsSenderIdSource(string $provider): string
    {
        $providerSenderId = config("sms.providers.{$provider}.sender_id");

        if (is_string($providerSenderId) && trim($providerSenderId) !== '') {
            return 'provider';
        }

        if ($this->hasSmsSenderId()) {
            return 'global';
        }

        return 'fallback';
    }

    /**
     * Status for the index/admin card.
     */
    public function getSmsSenderIdStatus(): array
    {
        $hasSenderId = $this->hasSmsSenderId();

        return [
            'has_sender_id' => $hasSenderId,
            'sender_id' => $this->getSmsSenderId(),
            'status' => $hasSenderId ? 'configured' : 'not_configured',
            'message' => $hasSenderId
                ? 'Default SMS sender ID is configured'
                : 'No default SMS sender ID set',
        ];
    }

    // ========== NOTIFICATION CHANNEL METHODS ==========

    /**
     * Get channels for invoice generation notifications
     */
    public function getInvoiceNotificationChannels(): array
    {
        $channels = $this->invoice_notification_channels ?? ['email'];
        
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        
        return $channels;
    }

    /**
     * Get channels for payment reminder notifications
     */
    public function getPaymentReminderChannels(): array
    {
        $channels = $this->payment_reminder_channels ?? ['email'];
        
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        
        return $channels;
    }

    /**
     * Get channels for overdue notifications
     */
    public function getOverdueNotificationChannels(): array
    {
        $channels = $this->overdue_notification_channels ?? ['email', 'sms'];
        
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        
        return $channels;
    }

    /**
     * Get channels for payment confirmation notifications
     */
    public function getPaymentConfirmationChannels(): array
    {
        $channels = $this->payment_confirmation_channels ?? ['email'];
        
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        
        return $channels;
    }

    /**
     * Check if a specific channel should be used for a notification type
     */
    public function shouldUseChannel(string $notificationType, string $channel): bool
    {
        $channels = match($notificationType) {
            'invoice_generated' => $this->getInvoiceNotificationChannels(),
            'payment_reminder' => $this->getPaymentReminderChannels(),
            'overdue' => $this->getOverdueNotificationChannels(),
            'payment_confirmation' => $this->getPaymentConfirmationChannels(),
            default => ['email']
        };
        
        return in_array($channel, $channels);
    }

    /**
     * Check if SMS is enabled for any notification type
     */
    public function isSmsEnabled(): bool
    {
        return $this->sms_notifications_enabled === true;
    }

    /**
     * Check if SMS reminders are enabled
     */
    public function isSmsReminderEnabled(): bool
    {
        return $this->isSmsEnabled() && $this->sms_reminder_enabled === true;
    }

    /**
     * Check if SMS payment confirmation is enabled
     */
    public function isSmsPaymentConfirmationEnabled(): bool
    {
        return $this->isSmsEnabled() && $this->sms_payment_confirmation_enabled === true;
    }

    /**
     * Get SMS rate limits
     */
    public function getSmsRateLimits(): array
    {
        return [
            'daily_limit' => $this->sms_daily_limit_per_user ?? 10,
            'hourly_limit' => $this->sms_hourly_limit_per_user ?? 3,
        ];
    }

    /**
     * Check if WhatsApp is enabled for any notification type
     */
    public function isWhatsAppEnabled(): bool
    {
        return $this->enable_whatsapp_notifications === true && $this->isWhatsAppConfigured();
    }

    /**
     * Check if WhatsApp reminders are enabled
     */
    public function isWhatsAppReminderEnabled(): bool
    {
        return $this->isWhatsAppEnabled() && $this->whatsapp_reminder_enabled === true;
    }

    /**
     * Check if WhatsApp payment confirmation is enabled
     */
    public function isWhatsAppPaymentConfirmationEnabled(): bool
    {
        return $this->isWhatsAppEnabled() && $this->whatsapp_payment_confirmation_enabled === true;
    }

    /**
     * Should force email fallback for failed notifications
     */
    public function shouldForceEmailFallback(): bool
    {
        return $this->force_email_fallback === true;
    }

    /**
     * Get notification retry configuration
     */
    public function getNotificationRetryConfig(): array
    {
        return [
            'attempts' => $this->notification_retry_attempts ?? 3,
            'delay_minutes' => $this->notification_retry_delay_minutes ?? 5,
        ];
    }

    /**
     * Should enable bulk notifications
     */
    public function shouldEnableBulkNotifications(): bool
    {
        return $this->enable_bulk_notifications === true;
    }

    /**
     * Get bulk notification batch size
     */
    public function getBulkNotificationBatchSize(): int
    {
        return $this->bulk_notification_batch_size ?? 50;
    }

    /**
     * Get bulk notification delay between batches (seconds)
     */
    public function getBulkNotificationDelaySeconds(): int
    {
        return $this->bulk_notification_delay_seconds ?? 2;
    }

    /**
     * Should log all notifications
     */
    public function shouldLogAllNotifications(): bool
    {
        return $this->log_all_notifications === true;
    }

    /**
     * Should log notification content (privacy concern)
     */
    public function shouldLogNotificationContent(): bool
    {
        return $this->log_notification_content === true;
    }

    /**
     * Get notification log retention days
     */
    public function getNotificationLogRetentionDays(): int
    {
        return $this->notification_log_retention_days ?? 90;
    }

    /**
     * Get SMS template for a specific type
     */
    public function getSmsTemplate(string $type): ?string
    {
        $templates = [
            'invoice_generated' => $this->sms_invoice_generated_template,
            'payment_reminder' => $this->sms_payment_reminder_template,
            'overdue' => $this->sms_overdue_template,
            'payment_confirmation' => $this->sms_payment_confirmation_template,
        ];
        
        return $templates[$type] ?? null;
    }

    /**
     * Get WhatsApp template for a specific type
     */
    public function getWhatsAppTemplate(string $type): ?string
    {
        $templates = [
            'invoice_generated' => $this->whatsapp_invoice_generated_template,
            'payment_reminder' => $this->whatsapp_payment_reminder_template,
            'overdue' => $this->whatsapp_overdue_template,
            'payment_confirmation' => $this->whatsapp_payment_confirmation_template,
        ];
        
        return $templates[$type] ?? null;
    }
    
    /**
     * Get notification configuration summary
     */
    public function getNotificationConfigSummary(): array
    {
        return [
            'channels' => [
                'invoice_generated' => $this->getInvoiceNotificationChannels(),
                'payment_reminder' => $this->getPaymentReminderChannels(),
                'overdue' => $this->getOverdueNotificationChannels(),
                'payment_confirmation' => $this->getPaymentConfirmationChannels(),
            ],
            'sms' => [
                'enabled' => $this->isSmsEnabled(),
                'reminders' => $this->isSmsReminderEnabled(),
                'payment_confirmation' => $this->isSmsPaymentConfirmationEnabled(),
                'rate_limits' => $this->getSmsRateLimits(),
                'sender_id' => $this->getSmsSenderId(),
                'sender_id_status' => $this->getSmsSenderIdStatus(),
                'has_templates' => !empty($this->sms_invoice_generated_template) || 
                                   !empty($this->sms_payment_reminder_template) ||
                                   !empty($this->sms_overdue_template) ||
                                   !empty($this->sms_payment_confirmation_template),
            ],
            'whatsapp' => [
                'enabled' => $this->isWhatsAppEnabled(),
                'configured' => $this->isWhatsAppConfigured(),
                'provider' => $this->getWhatsAppProviderDisplayName(),
                'reminders' => $this->isWhatsAppReminderEnabled(),
                'payment_confirmation' => $this->isWhatsAppPaymentConfirmationEnabled(),
                'has_templates' => !empty($this->whatsapp_invoice_generated_template) || 
                                   !empty($this->whatsapp_payment_reminder_template) ||
                                   !empty($this->whatsapp_overdue_template) ||
                                   !empty($this->whatsapp_payment_confirmation_template),
            ],
            'settings' => [
                'force_email_fallback' => $this->shouldForceEmailFallback(),
                'retry_attempts' => $this->getNotificationRetryConfig()['attempts'],
                'retry_delay_minutes' => $this->getNotificationRetryConfig()['delay_minutes'],
                'bulk_notifications' => $this->shouldEnableBulkNotifications(),
                'bulk_batch_size' => $this->getBulkNotificationBatchSize(),
                'log_all' => $this->shouldLogAllNotifications(),
                'log_retention_days' => $this->getNotificationLogRetentionDays(),
            ],
            'user_preferences_enabled' => $this->enable_notification_preferences === true,
        ];
    }

    /**
     * Get default SMS message for a notification type
     */
    public function getDefaultSmsMessage(string $type, array $data = []): string
    {
        $systemShortName = $this->getSystemShortName();
        
        return match($type) {
            'invoice_generated' => "{$systemShortName}: New invoice #{$data['invoice_number']} for {$data['period']} of {$data['amount']} is due on {$data['due_date']}. Pay via: {$data['payment_link']}",
            'payment_reminder' => "{$systemShortName}: Reminder: Invoice #{$data['invoice_number']} of {$data['amount']} is due on {$data['due_date']}. Pay now: {$data['payment_link']}",
            'overdue' => "⚠️ URGENT: {$systemShortName}: Invoice #{$data['invoice_number']} of {$data['amount']} is OVERDUE. Late fees may apply. Pay now: {$data['payment_link']}",
            'payment_confirmation' => "✅ {$systemShortName}: Payment of {$data['amount']} received for invoice #{$data['invoice_number']}. Thank you!",
            default => "{$systemShortName}: " . ($data['message'] ?? 'Notification from system')
        };
    }

    /**
     * Get default WhatsApp message for a notification type
     */
    public function getDefaultWhatsAppMessage(string $type, array $data = []): string
    {
        $systemName = $this->system_name;
        
        return match($type) {
            'invoice_generated' => "*{$systemName}* 🏢\n\nNew invoice generated:\n\n📄 *Invoice:* #{$data['invoice_number']}\n📅 *Period:* {$data['period']}\n💰 *Amount:* {$data['amount']}\n📆 *Due:* {$data['due_date']}\n\n🔗 Pay here: {$data['payment_link']}",
            'payment_reminder' => "*{$systemName}* 🔔\n\nPayment Reminder:\n\n📄 *Invoice:* #{$data['invoice_number']}\n💰 *Amount:* {$data['amount']}\n📆 *Due:* {$data['due_date']}\n\n🔗 Pay now: {$data['payment_link']}",
            'overdue' => "*URGENT* ⚠️ *{$systemName}*\n\nYour invoice #{$data['invoice_number']} of *{$data['amount']}* is OVERDUE!\n\nLate fees may apply. Please pay immediately:\n\n🔗 {$data['payment_link']}",
            'payment_confirmation' => "*{$systemName}* ✅\n\nPayment Confirmation:\n\n📄 *Invoice:* #{$data['invoice_number']}\n💰 *Amount:* {$data['amount']}\n\nThank you for your payment!",
            default => "*{$systemName}*\n\n" . ($data['message'] ?? 'Notification from system')
        };
    }

    // ========== YEAR-END ARCHIVE METHODS ==========

    /**
     * Check if year-end archiving is enabled for landlord invoices
     */
    public function isYearEndArchiveEnabled(): bool
    {
        return (bool) ($this->enable_year_end_archive ?? true);
    }

    /**
     * Check if year-end archiving is enabled for tenant invoices
     */
    public function isYearEndArchiveEnabledForTenant(): bool
    {
        return (bool) ($this->enable_year_end_archive_tenant ?? true);
    }

    /**
     * Get year-end archive configuration
     */
    public function getYearEndArchiveConfig(): array
    {
        return [
            'enabled' => $this->isYearEndArchiveEnabled(),
            'month' => $this->year_end_archive_month ?? 1,
            'day' => $this->year_end_archive_day ?? 15,
            'archive_paid_only' => (bool) ($this->archive_paid_invoices_only ?? true),
            'keep_unpaid' => (bool) ($this->keep_unpaid_invoices ?? true),
            'retention_months' => $this->paid_invoice_retention_months ?? 3,
            'auto_archive' => (bool) ($this->auto_archive_paid_after_retention ?? true),
            'notify_landlords' => (bool) ($this->notify_landlords_before_archive ?? true),
            'notification_days' => $this->archive_notification_days_landlord ?? 30,
            'send_report' => (bool) ($this->send_yearly_archive_report_landlord ?? true),
        ];
    }

    /**
     * Get tenant year-end archive configuration
     */
    public function getTenantYearEndArchiveConfig(): array
    {
        return [
            'enabled' => $this->isYearEndArchiveEnabledForTenant(),
            'month' => $this->year_end_archive_month_tenant ?? 1,
            'day' => $this->year_end_archive_day_tenant ?? 16,
            'retention_months' => $this->paid_invoice_retention_months_tenant ?? 3,
            'auto_archive' => (bool) ($this->auto_archive_paid_after_retention_tenant ?? true),
            'notify_tenants' => (bool) ($this->notify_tenants_before_archive ?? true),
            'notification_days' => $this->archive_notification_days_tenant ?? 30,
            'send_report' => (bool) ($this->send_yearly_archive_report_tenant ?? true),
        ];
    }

    /**
     * Get the next year-end archive date
     */
    public function getNextYearEndArchiveDate(): ?Carbon
    {
        if (!$this->isYearEndArchiveEnabled()) {
            return null;
        }

        $month = $this->year_end_archive_month ?? 1;
        $day = $this->year_end_archive_day ?? 15;
        
        $archiveDate = Carbon::create(now()->year, $month, $day);
        
        if ($archiveDate->isPast()) {
            $archiveDate->addYear();
        }
        
        return $archiveDate;
    }

    /**
     * Get the next tenant year-end archive date
     */
    public function getNextTenantYearEndArchiveDate(): ?Carbon
    {
        if (!$this->isYearEndArchiveEnabledForTenant()) {
            return null;
        }

        $month = $this->year_end_archive_month_tenant ?? 1;
        $day = $this->year_end_archive_day_tenant ?? 16;
        
        $archiveDate = Carbon::create(now()->year, $month, $day);
        
        if ($archiveDate->isPast()) {
            $archiveDate->addYear();
        }
        
        return $archiveDate;
    }

    /**
     * Check if today is the year-end archive date
     */
    public function isYearEndArchiveDate(): bool
    {
        if (!$this->isYearEndArchiveEnabled()) {
            return false;
        }

        $month = $this->year_end_archive_month ?? 1;
        $day = $this->year_end_archive_day ?? 15;
        
        return now()->month == $month && now()->day == $day;
    }

    /**
     * Check if today is the tenant year-end archive date
     */
    public function isTenantYearEndArchiveDate(): bool
    {
        if (!$this->isYearEndArchiveEnabledForTenant()) {
            return false;
        }

        $month = $this->year_end_archive_month_tenant ?? 1;
        $day = $this->year_end_archive_day_tenant ?? 16;
        
        return now()->month == $month && now()->day == $day;
    }

    /**
     * Get days until next year-end archive
     */
    public function getDaysUntilYearEndArchive(): ?int
    {
        $archiveDate = $this->getNextYearEndArchiveDate();
        
        if (!$archiveDate) {
            return null;
        }
        
        return now()->diffInDays($archiveDate, false);
    }

    /**
     * Check if landlord archive reminders should be sent today
     */
    public function shouldSendLandlordArchiveReminders(): bool
    {
        if (!$this->isYearEndArchiveEnabled()) {
            return false;
        }
        
        $archiveDate = $this->getNextYearEndArchiveDate();
        if (!$archiveDate) {
            return false;
        }
        
        $daysUntilArchive = (int) now()->diffInDays($archiveDate, false);
        $reminderDays = $this->archive_notification_days_landlord ?? 30;
        
        return $daysUntilArchive == $reminderDays;
    }

    /**
     * Check if tenant archive reminders should be sent today
     */
    public function shouldSendTenantArchiveReminders(): bool
    {
        if (!$this->isYearEndArchiveEnabledForTenant()) {
            return false;
        }
        
        $archiveDate = $this->getNextTenantYearEndArchiveDate();
        if (!$archiveDate) {
            return false;
        }
        
        $daysUntilArchive = (int) now()->diffInDays($archiveDate, false);
        $reminderDays = $this->archive_notification_days_tenant ?? 30;
        
        return $daysUntilArchive == $reminderDays;
    }

    /**
     * Scope to get only configured systems
     */
    public function scopeConfigured($query)
    {
        return $query->whereNotNull('system_name')
                    ->whereNotNull('system_email')
                    ->whereNotNull('currency_code');
    }

    /**
     * Initialize system settings with default values
     */
    public static function initialize(array $data, User $creator): self
    {
        $defaults = self::getDefaultSettings();
        $settings = array_merge($defaults, $data);
        $settings['created_by'] = $creator->id;
        $settings['updated_by'] = $creator->id;

        return static::create($settings);
    }

    /**
     * Get default system settings
     */
    public static function getDefaultSettings(): array
    {
        return [
            'system_name' => config('app.name', 'Property Management System'),
            'system_short_name' => 'property-system',
            'system_email' => config('mail.from.address', 'admin@example.com'),
            'system_phone' => '+233000000000',
            'system_address' => '',
            'system_logo' => null,
            'system_favicon' => null,

            // Default SMS sender ID — inherits from config/sms.php if set,
            // otherwise blank so the admin can fill it in.
            'sms_sender_id' => config('sms.sender_id', null),
            
            'currency_code' => 'GHS',
            'currency_symbol' => 'GH₵',
            'currency_position' => 'left',
            'decimal_places' => 2,
            
            // Dues Configuration
            'monthly_dues_amount' => 100.00,
            'calculation_method' => 'fixed',
            'per_property_amount' => 50.00,
            
            // Tenant Dues Defaults
            'enable_tenant_invoicing' => true,
            'tenant_monthly_dues_amount' => 50.00,
            'tenant_calculation_method' => 'fixed',
            'auto_generate_tenant_invoices' => true,
            'send_tenant_payment_reminders' => true,
            'tenant_grace_period_days' => 7,
            'tenant_late_payment_percentage' => 5.00,
            'tenant_fixed_penalty_amount' => 0.00,
            
            // Payment Terms
            'grace_period_days' => 7,
            'late_payment_percentage' => 5.00,
            'fixed_penalty_amount' => 0.00,
            
            // System Operations
            'auto_generate_invoices' => true,
            'send_payment_reminders' => true,
            'reminder_days_before' => 3,
            
            // Payment Gateways - All disabled by default
            'enable_expresspay' => false,
            'enable_hubtel' => false,
            'enable_paystack' => false,
            'enable_flutterwave' => false,
            
            // Bulk Payment Settings
            'enable_bulk_payments' => true,
            'max_bulk_months' => 6,
            'bulk_payment_discount' => 0,
            
            // WhatsApp Configuration Defaults
            'whatsapp_provider' => 'none',
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_whatsapp_from' => '',
            'vonage_key' => '',
            'vonage_secret' => '',
            'vonage_whatsapp_from' => '',
            'whatsapp_api_url' => '',
            'whatsapp_api_key' => '',
            
            // Registration Control Defaults
            'allow_registration' => true,
            'registration_disabled_message' => 'New registrations are currently disabled. Please contact the administrator for assistance.',
        ];
    }

    // ========== LOGO METHODS ==========

    /**
     * Check if logo exists
     */
    public function hasLogo(): bool
    {
        return !empty($this->system_logo) && Storage::disk('public')->exists($this->system_logo);
    }

    /**
     * Get logo URL
     */
    public function getLogoUrl(): string
    {
        if ($this->hasLogo()) {
            return Storage::disk('public')->url($this->system_logo);
        }
        
        return asset('images/default-logo.png');
    }

    /**
     * Get display logo (for placeholder)
     */
    public function getDisplayLogo(): string
    {
        return $this->getLogoUrl();
    }

    /**
     * Get default logo placeholder
     */
    public function getDefaultLogoPlaceholder(): string
    {
        return asset('images/default-system-logo.png');
    }

    /**
     * Remove logo from system
     */
    public function removeLogo(): bool
    {
        $this->system_logo = null;
        return $this->save();
    }

    // ========== FAVICON METHODS ==========

    /**
     * Check if favicon exists
     */
    public function hasFavicon(): bool
    {
        return !empty($this->system_favicon) && Storage::disk('public')->exists($this->system_favicon);
    }

    /**
     * Get favicon URL
     */
    public function getFaviconUrl(): ?string
    {
        if ($this->hasFavicon()) {
            return Storage::disk('public')->url($this->system_favicon);
        }
        
        return null;
    }

    /**
     * Get favicon HTML tag
     */
    public function getFaviconHtml(): string
    {
        if ($this->hasFavicon()) {
            $url = $this->getFaviconUrl();
            return '<link rel="icon" href="' . e($url) . '" type="image/x-icon">
                    <link rel="shortcut icon" href="' . e($url) . '" type="image/x-icon">
                    <link rel="apple-touch-icon" href="' . e($url) . '">';
        }
        
        return '';
    }

    /**
     * Get favicon for layout/head section
     */
    public function getFaviconForLayout(): string
    {
        return $this->getFaviconHtml();
    }

    /**
     * Get favicon paths for different sizes
     */
    public function getFaviconSizes(): array
    {
        if (!$this->hasFavicon()) {
            return [];
        }

        $url = $this->getFaviconUrl();
        
        return [
            '16x16' => $url,
            '32x32' => $url,
            '64x64' => $url,
            'apple_touch' => $url,
        ];
    }

    /**
     * Remove favicon from system
     */
    public function removeFavicon(): bool
    {
        if ($this->hasFavicon()) {
            Storage::disk('public')->delete($this->system_favicon);
        }
        
        $this->system_favicon = null;
        return $this->save();
    }

    /**
     * Get favicon status for display
     */
    public function getFaviconStatus(): array
    {
        $hasFavicon = $this->hasFavicon();
        
        return [
            'has_favicon' => $hasFavicon,
            'url' => $this->getFaviconUrl(),
            'status' => $hasFavicon ? 'configured' : 'not_configured',
            'message' => $hasFavicon ? 'Favicon is configured' : 'No favicon set',
            'display_name' => $hasFavicon ? basename($this->system_favicon) : null,
        ];
    }

    // ========== SYSTEM IDENTIFICATION METHODS ==========

    /**
     * Get system short name or fallback to system name
     */
    public function getSystemShortName(): string
    {
        return $this->system_short_name ?? $this->system_name ?? config('app.name', 'System');
    }

    /**
     * Get system identifier (short name for URLs, full name for display)
     */
    public function getSystemIdentifier(): array
    {
        return [
            'short_name' => $this->getSystemShortName(),
            'full_name' => $this->system_name,
            'slug' => $this->system_short_name,
            'display_name' => $this->system_name ?? $this->getSystemShortName()
        ];
    }

    /**
     * Generate a system short name from the system name
     */
    public function generateShortName(): string
    {
        if (empty($this->system_name)) {
            return 'system';
        }

        $shortName = strtolower($this->system_name);
        $shortName = preg_replace('/[^a-z0-9]+/', '-', $shortName);
        $shortName = trim($shortName, '-');

        if (empty($shortName)) {
            $shortName = 'property-system';
        }

        $baseShortName = $shortName;
        $counter = 1;
        
        while (self::where('system_short_name', $shortName)
                ->where('id', '!=', $this->id)
                ->exists()) {
            $shortName = $baseShortName . '-' . $counter;
            $counter++;
        }

        return $shortName;
    }

    // ========== REGISTRATION CONTROL METHODS ==========
    
    /**
     * Check if registration is allowed
     */
    public function isRegistrationAllowed(): bool
    {
        return $this->allow_registration ?? true;
    }

    /**
     * Get registration disabled message
     */
    public function getRegistrationDisabledMessage(): string
    {
        return $this->registration_disabled_message ?? 
               'New registrations are currently disabled. Please contact the administrator for assistance.';
    }

    /**
     * Check if registration is allowed with detailed status
     */
    public function getRegistrationStatus(): array
    {
        $allowed = $this->isRegistrationAllowed();
        
        return [
            'allowed' => $allowed,
            'message' => $allowed ? 'Registration is enabled' : $this->getRegistrationDisabledMessage(),
            'disabled_message' => $this->registration_disabled_message,
            'can_register' => $allowed,
            'button_visible' => $allowed,
            'button_text' => $allowed ? 'Register Your Land/Property' : 'Registration Currently Disabled',
            'button_class' => $allowed ? 'btn-success' : 'btn-secondary',
            'button_disabled' => !$allowed,
        ];
    }

    /**
     * Enable registration with optional message
     */
    public function enableRegistration(?string $message = null): bool
    {
        $this->allow_registration = true;
        $this->registration_disabled_message = $message;
        return $this->save();
    }

    /**
     * Disable registration with custom message
     */
    public function disableRegistration(?string $message = null): bool
    {
        $this->allow_registration = false;
        
        if ($message) {
            $this->registration_disabled_message = $message;
        }
        
        return $this->save();
    }

    // ========== TENANT INVOICE METHODS ==========

    /**
     * Check if tenant invoicing is enabled
     */
    public function isTenantInvoicingEnabled(): bool
    {
        return $this->enable_tenant_invoicing ?? false;
    }

    /**
     * Get tenant monthly dues amount
     */
    public function getTenantMonthlyDuesAmount(): float
    {
        return (float) ($this->tenant_monthly_dues_amount ?? 0);
    }

    /**
     * Get tenant calculation method
     */
    public function getTenantCalculationMethod(): string
    {
        return $this->tenant_calculation_method ?? 'fixed';
    }

    /**
     * Get tenant calculation method text for display
     */
    public function getTenantCalculationMethodText(): string
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return 'Not enabled';
        }

        $method = $this->getTenantCalculationMethod();
        
        return match($method) {
            'fixed' => 'Fixed Amount',
            'per_property_unit' => 'Per Property Unit',
            default => 'Fixed Amount'
        };
    }

    /**
     * Calculate tenant dues based on landlord dues and calculation method
     */
    public function calculateTenantDues(?float $landlordDues = null): float
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return 0;
        }

        $method = $this->getTenantCalculationMethod();
        
        return match($method) {
            'fixed' => $this->getTenantMonthlyDuesAmount(),
            'per_property_unit' => $this->getTenantMonthlyDuesAmount(),
            default => $this->getTenantMonthlyDuesAmount()
        };
    }

    /**
     * Alias for calculateTenantDues() to maintain compatibility with views
     */
    public function getCalculatedTenantDues(): float
    {
        return $this->calculateTenantDues();
    }

    /**
     * Get formatted tenant monthly dues
     */
    public function getFormattedTenantMonthlyDues(): string
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return 'Not enabled';
        }
        
        $amount = $this->calculateTenantDues();
        return $this->formatAmount($amount);
    }

    /**
     * Get tenant calculation method description
     */
    public function getTenantCalculationMethodDescription(): string
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return 'Tenant invoicing disabled';
        }

        $method = $this->getTenantCalculationMethod();
        $formattedAmount = $this->formatAmount($this->getTenantMonthlyDuesAmount());
        
        return match($method) {
            'fixed' => "Fixed Amount - {$formattedAmount} per tenant",
            'per_property_unit' => "Per Property Unit - {$formattedAmount} per property unit",
            default => "Fixed Amount - {$formattedAmount} per tenant"
        };
    }

    /**
     * Get tenant invoice generation settings
     */
    public function getTenantInvoiceSettings(): array
    {
        return [
            'enabled' => $this->isTenantInvoicingEnabled(),
            'monthly_amount' => $this->getTenantMonthlyDuesAmount(),
            'formatted_amount' => $this->getFormattedTenantMonthlyDues(),
            'calculation_method' => $this->getTenantCalculationMethod(),
            'calculation_description' => $this->getTenantCalculationMethodDescription(),
            'auto_generate' => (bool) ($this->auto_generate_tenant_invoices ?? true),
            'send_reminders' => (bool) ($this->send_tenant_payment_reminders ?? true),
            'grace_period_days' => $this->tenant_grace_period_days ?? $this->grace_period_days,
            'late_payment_percentage' => $this->tenant_late_payment_percentage ?? $this->late_payment_percentage,
            'fixed_penalty_amount' => $this->tenant_fixed_penalty_amount ?? $this->fixed_penalty_amount,
        ];
    }

    /**
     * Check if auto-generate tenant invoices is enabled
     */
    public function shouldAutoGenerateTenantInvoices(): bool
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return false;
        }
        
        return (bool) ($this->auto_generate_tenant_invoices ?? true);
    }

    /**
     * Check if tenant payment reminders should be sent
     */
    public function shouldSendTenantPaymentReminders(): bool
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return false;
        }
        
        return (bool) ($this->send_tenant_payment_reminders ?? true);
    }

    /**
     * Get tenant grace period days
     */
    public function getTenantGracePeriodDays(): int
    {
        return $this->tenant_grace_period_days ?? $this->grace_period_days ?? 7;
    }

    /**
     * Calculate tenant penalty for overdue payment
     */
    public function calculateTenantPenalty(float $originalAmount, int $daysOverdue): float
    {
        $gracePeriod = $this->getTenantGracePeriodDays();
        
        if ($daysOverdue <= $gracePeriod) {
            return 0.00;
        }

        if (!empty($this->tenant_fixed_penalty_amount) && $this->tenant_fixed_penalty_amount > 0) {
            return (float) $this->tenant_fixed_penalty_amount;
        }

        $penaltyPercentage = $this->tenant_late_payment_percentage ?? $this->late_payment_percentage ?? 5;
        return ($originalAmount * $penaltyPercentage) / 100;
    }

    /**
     * Get tenant penalty configuration
     */
    public function getTenantPenaltyConfig(): array
    {
        return [
            'grace_period_days' => $this->getTenantGracePeriodDays(),
            'late_payment_percentage' => $this->tenant_late_payment_percentage ?? $this->late_payment_percentage,
            'fixed_penalty_amount' => $this->tenant_fixed_penalty_amount,
            'formatted_fixed_penalty' => $this->tenant_fixed_penalty_amount ? 
                $this->formatAmount($this->tenant_fixed_penalty_amount) : null,
            'uses_percentage' => empty($this->tenant_fixed_penalty_amount) || $this->tenant_fixed_penalty_amount == 0,
            'uses_fixed' => !empty($this->tenant_fixed_penalty_amount) && $this->tenant_fixed_penalty_amount > 0,
        ];
    }

    /**
     * Get complete tenant invoice configuration
     */
    public function getTenantInvoiceConfig(): array
    {
        return [
            'settings' => $this->getTenantInvoiceSettings(),
            'penalty' => $this->getTenantPenaltyConfig(),
            'calculation' => [
                'method' => $this->getTenantCalculationMethod(),
                'description' => $this->getTenantCalculationMethodDescription(),
                'amount' => $this->getTenantMonthlyDuesAmount(),
                'formatted_amount' => $this->getFormattedTenantMonthlyDues(),
            ],
            'operations' => [
                'auto_generate' => $this->shouldAutoGenerateTenantInvoices(),
                'send_reminders' => $this->shouldSendTenantPaymentReminders(),
            ]
        ];
    }

    // ========== CURRENCY METHODS ==========

    /**
     * Format amount with currency
     */
    public function formatAmount($amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            $amount = 0;
        }
        
        $amount = (float) $amount;
        $decimalPlaces = $this->decimal_places ?? 2;
        $formatted = number_format($amount, $decimalPlaces);
        $currencySymbol = $this->currency_symbol ?? '₵';
        
        $position = $this->currency_position ?? 'left';
        
        return match($position) {
            'left' => $currencySymbol . $formatted,
            'right' => $formatted . $currencySymbol,
            'left_with_space' => $currencySymbol . ' ' . $formatted,
            'right_with_space' => $formatted . ' ' . $currencySymbol,
            default => $currencySymbol . $formatted
        };
    }

    public function safeFormatAmount($amount, float $default = 0): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            $amount = $default;
        }
        
        return $this->formatAmount($amount);
    }

    /**
     * Get currency info
     */
    public function getCurrencyInfo(): array
    {
        return [
            'code' => $this->currency_code,
            'symbol' => $this->currency_symbol,
            'position' => $this->currency_position,
            'decimal_places' => $this->decimal_places
        ];
    }

    // ========== PAYMENT METHODS ==========

    /**
     * Check if payment configuration is complete
     */
    public function isPaymentConfigurationComplete(): bool
    {
        return $this->hasEnabledPaymentMethods();
    }

    /**
     * Check if any payment methods are enabled
     */
    public function hasEnabledPaymentMethods(): bool
    {
        return $this->enable_expresspay || 
               $this->enable_hubtel || 
               $this->enable_paystack || 
               $this->enable_flutterwave;
    }

    /**
     * Get count of enabled payment providers
     */
    public function getEnabledPaymentProvidersCount(): int
    {
        $count = 0;
        if ($this->enable_expresspay) $count++;
        if ($this->enable_hubtel) $count++;
        if ($this->enable_paystack) $count++;
        if ($this->enable_flutterwave) $count++;
        
        return $count;
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];
        
        if ($this->enable_expresspay) {
            $methods['expresspay'] = 'ExpressPay';
        }
        if ($this->enable_hubtel) {
            $methods['hubtel'] = 'Hubtel';
        }
        if ($this->enable_paystack) {
            $methods['paystack'] = 'Paystack';
        }
        if ($this->enable_flutterwave) {
            $methods['flutterwave'] = 'Flutterwave';
        }
        
        return $methods;
    }

    /**
     * Check if specific payment method is enabled
     */
    public function isPaymentMethodEnabled(string $method): bool
    {
        return match($method) {
            'expresspay' => (bool) $this->enable_expresspay,
            'hubtel' => (bool) $this->enable_hubtel,
            'paystack' => (bool) $this->enable_paystack,
            'flutterwave' => (bool) $this->enable_flutterwave,
            default => false
        };
    }

    /**
     * Get list of enabled gateway names
     */
    public function getEnabledGatewayNames(): array
    {
        $gateways = [];
        if ($this->enable_expresspay) $gateways[] = 'ExpressPay';
        if ($this->enable_hubtel) $gateways[] = 'Hubtel';
        if ($this->enable_paystack) $gateways[] = 'Paystack';
        if ($this->enable_flutterwave) $gateways[] = 'Flutterwave';
        
        return $gateways;
    }

    /**
     * Get payment methods for frontend display
     */
    public function getPaymentMethodsForFrontend(): array
    {
        $methods = [];
        
        if ($this->enable_expresspay) {
            $methods['expresspay'] = [
                'name' => 'ExpressPay',
                'icon' => 'fa-credit-card',
                'description' => 'Mobile money & online payments',
                'color' => '#0066CC'
            ];
        }
        if ($this->enable_hubtel) {
            $methods['hubtel'] = [
                'name' => 'Hubtel',
                'icon' => 'fa-phone-alt',
                'description' => 'Mobile money collections',
                'color' => '#2563EB'
            ];
        }
        if ($this->enable_paystack) {
            $methods['paystack'] = [
                'name' => 'Paystack',
                'icon' => 'fa-credit-card',
                'description' => 'Cards, bank transfers & mobile money',
                'color' => '#3B82F6'
            ];
        }
        if ($this->enable_flutterwave) {
            $methods['flutterwave'] = [
                'name' => 'Flutterwave',
                'icon' => 'fa-cloud-upload-alt',
                'description' => 'Pan-African payment gateway',
                'color' => '#F97316'
            ];
        }
        
        return $methods;
    }

    // ========== DUES CALCULATION METHODS ==========

    /**
     * Calculate dues for a property
     */
    public function calculateDues(?Property $property = null): float
    {
        return match($this->calculation_method) {
            'per_property' => (float) $this->per_property_amount,
            'fixed' => (float) $this->monthly_dues_amount,
            default => (float) $this->monthly_dues_amount,
        };
    }

    /**
     * Calculate monthly dues for a property
     */
    public function calculateMonthlyDuesForProperty(Property $property): float
    {
        return $this->calculateDues($property);
    }

    /**
     * Get the calculation method description
     */
    public function getCalculationMethodDescription(): string
    {
        return match($this->calculation_method) {
            'per_property' => "Per Property - " . $this->formatAmount($this->per_property_amount) . " per property",
            'fixed' => "Fixed Amount - " . $this->formatAmount($this->monthly_dues_amount) . " for all properties",
            default => "Fixed Amount - " . $this->formatAmount($this->monthly_dues_amount) . " for all properties",
        };
    }

    /**
     * Get dues amount without property dependency
     */
    public function getDuesAmount(): float
    {
        return match($this->calculation_method) {
            'per_property' => (float) $this->per_property_amount,
            'fixed' => (float) $this->monthly_dues_amount,
            default => (float) $this->monthly_dues_amount,
        };
    }

    /**
     * Get formatted monthly dues amount
     */
    public function getFormattedMonthlyDues(): string
    {
        return $this->formatAmount($this->getDuesAmount());
    }

    /**
     * Get formatted dues amount
     */
    public function getFormattedDuesAmount(): string
    {
        if ($this->calculation_method === 'per_property') {
            return $this->formatAmount($this->per_property_amount) . ' per property';
        }
        
        return $this->formatAmount($this->monthly_dues_amount);
    }

    /**
     * Get the monthly dues amount
     */
    public function getMonthlyDuesAmount(): float
    {
        return (float) $this->monthly_dues_amount;
    }

    /**
     * Get the calculation method
     */
    public function getCalculationMethod(): string
    {
        return $this->calculation_method;
    }

    /**
     * Get the per property amount
     */
    public function getPerPropertyAmount(): float
    {
        return (float) $this->per_property_amount;
    }

    /**
     * Check if calculation is per property
     */
    public function isPerPropertyCalculation(): bool
    {
        return $this->calculation_method === 'per_property';
    }

    /**
     * Check if calculation is fixed amount
     */
    public function isFixedCalculation(): bool
    {
        return $this->calculation_method === 'fixed';
    }

    // ========== PAYMENT REMINDER METHODS ==========

    /**
     * Check if payment reminders should be sent
     */
    public function shouldSendPaymentReminders(): bool
    {
        return $this->send_payment_reminders === true;
    }

    /**
     * Get the number of days before due date to send reminders
     */
    public function getReminderDaysBefore(): int
    {
        return $this->reminder_days_before ?? 3;
    }

    /**
     * Get payment reminder configuration
     */
    public function getPaymentReminderConfig(): array
    {
        return [
            'enabled' => $this->shouldSendPaymentReminders(),
            'days_before' => $this->getReminderDaysBefore(),
            'reminder_schedule' => $this->generateReminderSchedule()
        ];
    }

    /**
     * Generate reminder schedule based on configuration
     */
    protected function generateReminderSchedule(): array
    {
        if (!$this->shouldSendPaymentReminders()) {
            return [];
        }

        $schedule = [];
        $daysBefore = $this->getReminderDaysBefore();
        
        $schedule[] = [
            'days_before' => $daysBefore,
            'description' => "First reminder: {$daysBefore} days before due date"
        ];
        
        if ($daysBefore > 1) {
            $schedule[] = [
                'days_before' => 1,
                'description' => "Final reminder: 1 day before due date"
            ];
        }
        
        $schedule[] = [
            'days_before' => 0,
            'description' => "Due date reminder: On the due date"
        ];
        
        return $schedule;
    }

    /**
     * Calculate next reminder date based on due date
     */
    public function calculateNextReminderDate(Carbon $dueDate): ?Carbon
    {
        if (!$this->shouldSendPaymentReminders()) {
            return null;
        }

        $today = Carbon::today();
        $daysUntilDue = $today->diffInDays($dueDate, false);
        
        if ($daysUntilDue < 0) {
            return null;
        }

        $reminderDays = $this->getReminderDaysBefore();
        
        if ($daysUntilDue <= $reminderDays) {
            return $today;
        }
        
        return $dueDate->copy()->subDays($reminderDays);
    }

    /**
     * Get all scheduled reminder dates for a billing period
     */
    public function getScheduledReminderDates(Carbon $dueDate): array
    {
        if (!$this->shouldSendPaymentReminders()) {
            return [];
        }

        $reminderDates = [];
        $daysBefore = $this->getReminderDaysBefore();
        
        if ($daysBefore > 0) {
            $reminderDates[] = [
                'type' => 'first_reminder',
                'date' => $dueDate->copy()->subDays($daysBefore)->format('Y-m-d'),
                'days_before' => $daysBefore
            ];
        }
        
        if ($daysBefore > 1) {
            $reminderDates[] = [
                'type' => 'final_reminder',
                'date' => $dueDate->copy()->subDay()->format('Y-m-d'),
                'days_before' => 1
            ];
        }
        
        $reminderDates[] = [
            'type' => 'due_date_reminder',
            'date' => $dueDate->format('Y-m-d'),
            'days_before' => 0
        ];
        
        return $reminderDates;
    }

    // ========== BULK PAYMENT METHODS ==========

    /**
     * Check if bulk payments are enabled
     */
    public function isBulkPaymentEnabled(): bool
    {
        return (bool) $this->enable_bulk_payments;
    }

    /**
     * Get maximum bulk payment months allowed
     */
    public function getMaxBulkMonths(): int
    {
        return (int) $this->max_bulk_months;
    }

    /**
     * Validate if requested bulk months are within limits
     */
    public function validateBulkMonths(int $months): bool
    {
        if (!$this->isBulkPaymentEnabled()) {
            return false;
        }

        return $months >= 2 && $months <= $this->getMaxBulkMonths();
    }

    /**
     * Get bulk payment configuration
     */
    public function getBulkPaymentConfig(): array
    {
        return [
            'enabled' => $this->enable_bulk_payments,
            'max_months' => $this->max_bulk_months ?? 12,
            'discount_percentage' => $this->bulk_payment_discount ?? 0,
            'available_months' => range(2, $this->max_bulk_months ?? 12)
        ];
    }

    /**
     * Get bulk payment discount information
     */
    public function getBulkDiscountInfo(): array
    {
        return [
            'enabled' => isset($this->bulk_payment_discount) && $this->bulk_payment_discount > 0,
            'percentage' => $this->bulk_payment_discount ?? 0,
            'description' => $this->bulk_payment_discount > 0 
                ? "Save {$this->bulk_payment_discount}% on bulk payments!" 
                : "No discount applied for bulk payments"
        ];
    }

    /**
     * Calculate bulk payment amount with discount
     */
    public function calculateBulkPaymentAmount(Property $property, int $months): float
    {
        if (!$this->validateBulkMonths($months)) {
            throw new \Exception(
                "Invalid bulk payment months requested. Must be between 2 and {$this->max_bulk_months} months."
            );
        }

        $monthlyDues = $this->calculateDues($property);
        $total = $monthlyDues * $months;
        
        if (isset($this->bulk_payment_discount) && $this->bulk_payment_discount > 0) {
            $discount = $total * ($this->bulk_payment_discount / 100);
            $total -= $discount;
        }
        
        return round($total, 2);
    }

    /**
     * Calculate bulk dues for multiple months
     */
    public function calculateBulkDues(Property $property, int $months): float
    {
        if (!$this->validateBulkMonths($months)) {
            throw new \Exception(
                "Invalid bulk payment months requested. Must be between 2 and {$this->max_bulk_months} months."
            );
        }

        $monthlyDues = $this->calculateDues($property);
        return $monthlyDues * $months;
    }

    /**
     * Get available bulk payment options
     */
    public function getBulkPaymentOptions(): array
    {
        if (!$this->enable_bulk_payments) {
            return [];
        }

        $options = [];
        $monthlyAmount = $this->monthly_dues_amount;
        
        for ($i = 2; $i <= $this->max_bulk_months; $i++) {
            $total = $monthlyAmount * $i;
            
            if (isset($this->bulk_payment_discount) && $this->bulk_payment_discount > 0) {
                $discount = $total * ($this->bulk_payment_discount / 100);
                $total -= $discount;
            }
            
            $options[$i] = [
                'months' => $i,
                'original_amount' => $monthlyAmount * $i,
                'discounted_amount' => $total,
                'savings' => isset($this->bulk_payment_discount) ? ($monthlyAmount * $i) - $total : 0,
                'discount_percentage' => $this->bulk_payment_discount ?? 0,
                'formatted_amount' => $this->formatAmount($total),
                'formatted_savings' => isset($this->bulk_payment_discount) 
                    ? $this->formatAmount(($monthlyAmount * $i) - $total) 
                    : 'GH₵0.00'
            ];
        }
        
        return $options;
    }

    /**
     * Generate due dates for bulk payment
     */
    public function generateBulkDueDates(Carbon $startDate, int $months): array
    {
        $dueDates = [];
        
        for ($i = 0; $i < $months; $i++) {
            $dueDate = $startDate->copy()->addMonths($i);
            $dueDates[] = [
                'month' => $dueDate->format('F Y'),
                'due_date' => $this->getDueDateForPeriod($dueDate)->format('Y-m-d'),
                'period' => $dueDate->format('Y-m')
            ];
        }
        
        return $dueDates;
    }

    /**
     * Get bulk payment settings
     */
    public function getBulkPaymentSettings(): array
    {
        return [
            'enabled' => $this->isBulkPaymentEnabled(),
            'max_months' => $this->getMaxBulkMonths(),
            'currency' => $this->getCurrencyInfo()
        ];
    }

    /**
     * Check if system can process bulk payments
     */
    public function canProcessBulkPayments(): bool
    {
        return $this->isBulkPaymentEnabled() && $this->getMaxBulkMonths() > 0;
    }

    // ========== INVOICE GENERATION METHODS ==========

    /**
     * Check if auto invoice generation is enabled
     */
    public function isAutoInvoiceGenerationEnabled(): bool
    {
        return $this->auto_generate_invoices === true;
    }

    /**
     * Get the next invoice generation date
     */
    public function getNextInvoiceGenerationDate(): Carbon
    {
        return Carbon::now()->addMonth()->startOfMonth();
    }

    /**
     * Check if system is ready for auto-invoice generation
     */
    public function isReadyForAutoInvoiceGeneration(): array
    {
        $issues = [];
        
        if (!$this->isAutoInvoiceGenerationEnabled()) {
            $issues[] = 'Auto invoice generation is disabled';
        }
        
        if ($this->getDuesAmount() <= 0) {
            $issues[] = 'Monthly dues amount is not set or is zero';
        }
        
        if (!$this->hasEnabledPaymentMethods()) {
            $issues[] = 'No payment methods are enabled';
        }
        
        return [
            'ready' => empty($issues),
            'issues' => $issues,
            'can_generate' => empty($issues),
            'next_generation_date' => $this->getNextInvoiceGenerationDate()->format('Y-m-d'),
            'settings_summary' => [
                'auto_generate' => $this->auto_generate_invoices,
                'dues_amount' => $this->getFormattedDuesAmount(),
                'payment_methods_count' => $this->getEnabledPaymentProvidersCount()
            ]
        ];
    }

    /**
     * Get invoice generation settings
     */
    public function getInvoiceGenerationSettings(): array
    {
        return [
            'auto_generate' => $this->auto_generate_invoices,
            'calculation_method' => $this->calculation_method,
            'monthly_amount' => $this->monthly_dues_amount,
            'per_property_amount' => $this->per_property_amount,
            'grace_period_days' => $this->grace_period_days,
            'reminder_enabled' => $this->shouldSendPaymentReminders(),
            'reminder_days_before' => $this->getReminderDaysBefore(),
            'due_date_calculation' => 'End of month + ' . $this->grace_period_days . ' days grace period',
            'tenant_invoicing' => $this->getTenantInvoiceSettings()
        ];
    }

    /**
     * Get due date for a period (end of month + grace period)
     */
    public function getDueDateForPeriod(Carbon $period): Carbon
    {
        return $period->copy()->endOfMonth()->addDays($this->grace_period_days);
    }

    // ========== PENALTY CALCULATION ==========

    /**
     * Calculate penalty for overdue payment
     */
    public function calculatePenalty($amount, $daysOverdue)
    {
        $daysOverdue = abs($daysOverdue);
        
        if ($this->fixed_penalty_amount > 0) {
            return $this->fixed_penalty_amount;
        }
        
        if ($this->late_payment_percentage > 0) {
            return ($amount * $this->late_payment_percentage / 100);
        }
        
        return 0;
    }

    // ========== EMAIL CONFIGURATION METHODS ==========

    /**
     * Check if system can send emails
     */
    public function canSendEmails(): array
    {
        $issues = [];
        
        if (empty($this->system_email)) {
            $issues[] = 'System email not set';
        }
        
        if (empty($this->system_name)) {
            $issues[] = 'System name not set';
        }
        
        return [
            'can_send' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Get email configuration status
     */
    public function getEmailConfigurationStatus(): array
    {
        $emailCheck = $this->canSendEmails();
        
        return [
            'configured' => $emailCheck['can_send'],
            'issues' => $emailCheck['issues'],
            'system_email' => $this->system_email,
            'system_name' => $this->system_name,
            'system_short_name' => $this->getSystemShortName()
        ];
    }

    /**
     * Get email configuration for system emails
     */
    public function getEmailConfiguration(): array
    {
        return [
            'from_address' => $this->system_email ?? config('mail.from.address'),
            'from_name' => $this->system_name ?? config('mail.from.name'),
            'reply_to' => $this->system_email ?? config('mail.from.address'),
            'system_name' => $this->system_name,
            'system_short_name' => $this->getSystemShortName(),
            'system_email' => $this->system_email,
            'system_phone' => $this->system_phone,
            'system_address' => $this->system_address,
            'logo_url' => $this->getLogoUrl()
        ];
    }

    /**
     * Check if system email is configured
     */
    public function isEmailConfigured(): bool
    {
        return !empty($this->system_email) && 
               filter_var($this->system_email, FILTER_VALIDATE_EMAIL) &&
               !empty($this->system_name);
    }

    /**
     * Check if email password is configured in environment
     */
    public function isEmailPasswordConfigured(): bool
    {
        $mailPassword = config('mail.password');
        return !empty($mailPassword) && $mailPassword !== 'your_email_password';
    }

    /**
     * Get email configuration status including password check
     */
    public function getEmailConfigurationStatusFull(): array
    {
        $emailConfigured = $this->isEmailConfigured();
        $passwordConfigured = $this->isEmailPasswordConfigured();
        
        $status = 'not_configured';
        $issues = [];

        if (!$emailConfigured) {
            $issues[] = 'System email address is not configured or invalid';
        }

        if (!$passwordConfigured) {
            $issues[] = 'Email password is not configured in environment';
        }

        if ($emailConfigured && $passwordConfigured) {
            $status = 'fully_configured';
        } elseif ($emailConfigured && !$passwordConfigured) {
            $status = 'password_missing';
        }

        return [
            'status' => $status,
            'email_configured' => $emailConfigured,
            'password_configured' => $passwordConfigured,
            'can_send_emails' => $emailConfigured && $passwordConfigured,
            'issues' => $issues,
            'recommendations' => $this->getEmailConfigurationRecommendations()
        ];
    }

    /**
     * Validate email configuration for sending
     */
    public function validateEmailConfiguration(): array
    {
        $configStatus = $this->getEmailConfigurationStatusFull();
        
        return [
            'is_valid' => $configStatus['can_send_emails'],
            'errors' => $configStatus['issues'],
            'configuration' => $this->getEmailConfiguration(),
            'password_configured' => $configStatus['password_configured']
        ];
    }

    /**
     * Get email sender information for invitations
     */
    public function getEmailSenderInfo(): array
    {
        $validation = $this->validateEmailConfiguration();
        
        if (!$validation['is_valid']) {
            return [
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'reply_to' => config('mail.from.address'),
                'system_name' => config('app.name', 'Property Registration System'),
                'system_short_name' => $this->getSystemShortName(),
                'contact_email' => config('mail.from.address'),
                'contact_phone' => $this->system_phone,
                'using_fallback' => true,
                'can_send_emails' => false
            ];
        }

        return [
            'from_email' => $this->system_email,
            'from_name' => $this->system_name,
            'reply_to' => $this->system_email,
            'system_name' => $this->system_name,
            'system_short_name' => $this->getSystemShortName(),
            'contact_email' => $this->system_email,
            'contact_phone' => $this->system_phone,
            'using_fallback' => false,
            'can_send_emails' => true
        ];
    }

    /**
     * Get email template variables for agent invitations
     */
    public function getEmailTemplateVariables(): array
    {
        $senderInfo = $this->getEmailSenderInfo();
        
        return [
            'systemName' => $senderInfo['system_name'],
            'systemShortName' => $senderInfo['system_short_name'],
            'systemEmail' => $senderInfo['contact_email'],
            'systemPhone' => $this->system_phone,
            'systemAddress' => $this->system_address,
            'systemLogo' => $this->getDisplayLogo(),
            'fromEmail' => $senderInfo['from_email'],
            'fromName' => $senderInfo['from_name'],
            'usingFallback' => $senderInfo['using_fallback'],
            'canSendEmails' => $senderInfo['can_send_emails']
        ];
    }

    /**
     * Check if system can send emails (comprehensive version)
     */
    public function canSendEmailsFull(): array
    {
        $emailValidation = $this->validateEmailConfiguration();
        $laravelConfig = $this->checkLaravelMailConfiguration();
        
        $canSend = $emailValidation['is_valid'] && $laravelConfig['configured'];
        
        return [
            'can_send' => $canSend,
            'email_validation' => $emailValidation,
            'laravel_config' => $laravelConfig,
            'issues' => array_merge(
                $emailValidation['errors'],
                $laravelConfig['issues']
            ),
            'recommendation' => $canSend ? 
                'System is ready to send emails' : 
                'Please configure system email and Laravel mail settings'
        ];
    }

    /**
     * Check Laravel mail configuration
     */
    public function checkLaravelMailConfiguration(): array
    {
        $issues = [];
        $configured = true;

        $mailDriver = config('mail.default');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');
        $mailPassword = config('mail.password');

        if (empty($mailDriver) || $mailDriver === 'log') {
            $issues[] = 'Mail driver is set to log or not configured';
            $configured = false;
        }

        if (empty($fromAddress)) {
            $issues[] = 'Default from address is not configured in Laravel';
            $configured = false;
        }

        if (empty($fromName)) {
            $issues[] = 'Default from name is not configured in Laravel';
        }

        if (empty($mailPassword) || $mailPassword === 'your_email_password') {
            $issues[] = 'Email password is not configured in environment';
            $configured = false;
        }

        return [
            'configured' => $configured,
            'driver' => $mailDriver,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'password_configured' => !empty($mailPassword) && $mailPassword !== 'your_email_password',
            'issues' => $issues
        ];
    }

    /**
     * Get complete email status for admin dashboard
     */
    public function getEmailStatus(): array
    {
        $canSend = $this->canSendEmailsFull();
        $senderInfo = $this->getEmailSenderInfo();
        $configStatus = $this->getEmailConfigurationStatusFull();

        return [
            'status' => $configStatus['status'],
            'can_send_emails' => $canSend['can_send'],
            'system_email_configured' => $this->isEmailConfigured(),
            'email_password_configured' => $configStatus['password_configured'],
            'sender_information' => $senderInfo,
            'laravel_mail_config' => [
                'driver' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'password_configured' => $configStatus['password_configured'],
            ],
            'issues' => $canSend['issues'],
            'recommendations' => $this->getEmailConfigurationRecommendations(),
            'configuration_status' => $configStatus
        ];
    }

    /**
     * Get email configuration recommendations
     */
    protected function getEmailConfigurationRecommendations(): array
    {
        $recommendations = [];
        $configStatus = $this->getEmailConfigurationStatusFull();

        if (!$configStatus['email_configured']) {
            $recommendations[] = 'Configure system email and name in system settings';
        }

        if (!$configStatus['password_configured']) {
            $recommendations[] = 'Configure email password in system settings (will update .env file)';
        }

        if (config('mail.default') === 'log') {
            $recommendations[] = 'Change mail driver from "log" to "smtp" in .env file';
        }

        if (empty(config('mail.from.address'))) {
            $recommendations[] = 'Configure default from address in Laravel mail configuration';
        }

        return $recommendations;
    }

    /**
     * Get email configuration for agent invitations
     */
    public function getAgentInvitationEmailConfig(): array
    {
        $emailConfig = $this->getEmailConfiguration();
        $canSend = $this->canSendEmailsFull();

        return [
            'from_email' => $emailConfig['from_address'],
            'from_name' => $emailConfig['from_name'],
            'reply_to' => $emailConfig['reply_to'],
            'system_name' => $emailConfig['system_name'],
            'system_short_name' => $emailConfig['system_short_name'],
            'system_logo' => $emailConfig['logo_url'],
            'can_send_emails' => $canSend['can_send'],
            'configuration_issues' => $canSend['issues'],
            'sender_info' => $this->getEmailSenderInfo()
        ];
    }

    // ========== WHATSAPP CONFIGURATION METHODS ==========

    /**
     * Check if WhatsApp is configured
     */
    public function isWhatsAppConfigured(): bool
    {
        if (empty($this->whatsapp_provider) || $this->whatsapp_provider === 'none') {
            return false;
        }

        return match($this->whatsapp_provider) {
            'twilio' => !empty($this->twilio_sid) && !empty($this->twilio_token) && !empty($this->twilio_whatsapp_from),
            'vonage' => !empty($this->vonage_key) && !empty($this->vonage_secret) && !empty($this->vonage_whatsapp_from),
            'custom' => !empty($this->whatsapp_api_url) && !empty($this->whatsapp_api_key),
            default => false
        };
    }

    /**
     * Get WhatsApp configuration status
     */
    public function getWhatsAppConfigurationStatus(): array
    {
        $configured = $this->isWhatsAppConfigured();
        $issues = [];

        if (empty($this->whatsapp_provider) || $this->whatsapp_provider === 'none') {
            $issues[] = 'WhatsApp provider not selected';
        } else {
            switch ($this->whatsapp_provider) {
                case 'twilio':
                    if (empty($this->twilio_sid)) $issues[] = 'Twilio SID required';
                    if (empty($this->twilio_token)) $issues[] = 'Twilio Token required';
                    if (empty($this->twilio_whatsapp_from)) $issues[] = 'Twilio WhatsApp From number required';
                    break;
                case 'vonage':
                    if (empty($this->vonage_key)) $issues[] = 'Vonage API Key required';
                    if (empty($this->vonage_secret)) $issues[] = 'Vonage API Secret required';
                    if (empty($this->vonage_whatsapp_from)) $issues[] = 'Vonage WhatsApp From number required';
                    break;
                case 'custom':
                    if (empty($this->whatsapp_api_url)) $issues[] = 'WhatsApp API URL required';
                    if (empty($this->whatsapp_api_key)) $issues[] = 'WhatsApp API Key required';
                    break;
            }
        }

        return [
            'configured' => $configured,
            'provider' => $this->whatsapp_provider,
            'issues' => $issues,
            'can_send_messages' => $configured
        ];
    }

    /**
     * Get WhatsApp configuration for services
     */
    public function getWhatsAppConfiguration(): array
    {
        return [
            'provider' => $this->whatsapp_provider,
            'twilio_sid' => $this->twilio_sid,
            'twilio_token' => $this->twilio_token,
            'twilio_whatsapp_from' => $this->twilio_whatsapp_from,
            'vonage_key' => $this->vonage_key,
            'vonage_secret' => $this->vonage_secret,
            'vonage_whatsapp_from' => $this->vonage_whatsapp_from,
            'whatsapp_api_url' => $this->whatsapp_api_url,
            'whatsapp_api_key' => $this->whatsapp_api_key,
        ];
    }

    /**
     * Get WhatsApp provider display name
     */
    public function getWhatsAppProviderDisplayName(): string
    {
        return match($this->whatsapp_provider) {
            'twilio' => 'Twilio',
            'vonage' => 'Vonage',
            'custom' => 'Custom API',
            'none' => 'Disabled',
            default => 'Not Configured'
        };
    }

    // ========== CONFIGURATION STATUS METHODS ==========

    /**
     * Get system configuration status
     */
    public function getSystemConfigurationStatus(): array
    {
        return [
            'logo' => $this->hasLogo(),
            'favicon' => $this->hasFavicon(),
            'payment' => $this->isPaymentConfigurationComplete(),
            'email' => $this->canSendEmails()['can_send'],
            'whatsapp' => $this->isWhatsAppConfigured(),
            'currency' => !empty($this->currency_code) && !empty($this->currency_symbol),
            'dues' => !empty($this->calculation_method) && !empty($this->monthly_dues_amount),
            'short_name' => !empty($this->system_short_name),
            'registration' => $this->isRegistrationAllowed(),
            'tenant_invoicing' => $this->isTenantInvoicingEnabled(),
            'sms_sender_id' => $this->hasSmsSenderId(),
        ];
    }

    /**
     * Get payment configuration status
     */
    public function getPaymentConfigurationStatus(): array
    {
        return [
            'methods_available' => $this->hasEnabledPaymentMethods(),
            'enabled_methods_count' => $this->getEnabledPaymentProvidersCount(),
            'enabled_methods' => $this->getEnabledGatewayNames(),
            'bulk_payments_enabled' => $this->isBulkPaymentEnabled()
        ];
    }

    /**
     * Get complete system configuration status
     */
    public function getSystemConfigurationStatusFull(): array
    {
        $emailStatus = $this->getEmailStatus();
        $paymentStatus = $this->getPaymentConfigurationStatus();
        $whatsappStatus = $this->getWhatsAppConfigurationStatus();
        $registrationStatus = $this->getRegistrationStatus();
        $tenantInvoiceStatus = $this->getTenantInvoiceConfig();

        return [
            'system_info' => [
                'name_configured' => !empty($this->system_name),
                'short_name_configured' => !empty($this->system_short_name),
                'email_configured' => $this->isEmailConfigured(),
                'email_password_configured' => $emailStatus['email_password_configured'],
                'phone_configured' => !empty($this->system_phone),
                'logo_configured' => $this->hasLogo(),
                'favicon_configured' => $this->hasFavicon(),
                'sms_sender_id_configured' => $this->hasSmsSenderId(),
                'sms_sender_id' => $this->getSmsSenderId(),
                'can_send_emails' => $emailStatus['can_send_emails'],
                'email_issues' => $emailStatus['issues'],
            ],
            'payment_configuration' => $paymentStatus,
            'whatsapp_configuration' => $whatsappStatus,
            'registration_control' => $registrationStatus,
            'tenant_invoicing' => $tenantInvoiceStatus,
            'dues_configuration' => [
                'method' => $this->calculation_method,
                'amount_configured' => $this->getDuesAmount() > 0,
                'formatted_amount' => $this->getFormattedDuesAmount(),
            ],
            'operations' => [
                'invoices_auto_generated' => $this->auto_generate_invoices,
                'reminders_enabled' => $this->shouldSendPaymentReminders(),
                'reminder_days' => $this->getReminderDaysBefore(),
                'tenant_invoices_auto_generated' => $this->shouldAutoGenerateTenantInvoices(),
                'tenant_reminders_enabled' => $this->shouldSendTenantPaymentReminders(),
            ],
            'email_configuration' => $emailStatus,
            'whatsapp_configuration_full' => $whatsappStatus,
            'overall_status' => $this->getOverallSystemStatus()
        ];
    }

    /**
     * Get overall system status
     */
    protected function getOverallSystemStatus(): string
    {
        $emailStatus = $this->getEmailStatus();
        $paymentStatus = $this->getPaymentConfigurationStatus();
        $whatsappStatus = $this->getWhatsAppConfigurationStatus();

        $criticalServices = $emailStatus['can_send_emails'] && $paymentStatus['methods_available'];
        $allServices = $criticalServices && $whatsappStatus['configured'];

        if ($allServices) {
            return 'fully_configured';
        }

        if ($criticalServices) {
            return 'partially_configured';
        }

        return 'needs_configuration';
    }

    // ========== COMPOSITE METHODS ==========

    /**
     * Get system information
     */
    public function getSystemInfo(): array
    {
        $emailStatus = $this->getEmailStatus();
        $whatsappStatus = $this->getWhatsAppConfigurationStatus();
        
        return [
            'name' => $this->system_name,
            'short_name' => $this->getSystemShortName(),
            'email' => $this->system_email,
            'phone' => $this->system_phone,
            'address' => $this->system_address,
            'logo' => $this->getLogoUrl(),
            'logo_path' => $this->system_logo,
            'favicon' => $this->getFaviconUrl(),
            'favicon_path' => $this->system_favicon,
            'favicon_status' => $this->hasFavicon(),
            'sms_sender_id' => $this->getSmsSenderId(),
            'sms_sender_id_status' => $this->getSmsSenderIdStatus(),
            'email_status' => $emailStatus,
            'whatsapp_status' => $whatsappStatus,
            'can_send_emails' => $emailStatus['can_send_emails'],
            'can_send_whatsapp' => $whatsappStatus['can_send_messages'],
            'identifier' => $this->getSystemIdentifier()
        ];
    }

    /**
     * Get payment configuration for frontend
     */
    public function getPaymentConfiguration(): array
    {
        return [
            'available_methods' => $this->getAvailablePaymentMethods(),
            'bulk_payment_enabled' => $this->isBulkPaymentEnabled(),
            'max_bulk_months' => $this->getMaxBulkMonths(),
            'currency' => $this->getCurrencyInfo(),
            'system_short_name' => $this->getSystemShortName(),
            'payment_methods' => $this->getPaymentMethodsForFrontend()
        ];
    }

    /**
     * Get complete system overview for dashboard
     */
    public function getSystemOverview(): array
    {
        return [
            'identification' => $this->getSystemIdentifier(),
            'registration' => $this->getRegistrationStatus(),
            'payment' => $this->getPaymentConfiguration(),
            'email' => $this->getEmailConfigurationStatusFull(),
            'whatsapp' => $this->getWhatsAppConfigurationStatus(),
            'tenant_invoicing' => $this->getTenantInvoiceConfig(),
            'configuration_status' => $this->getSystemConfigurationStatusFull(),
            'sms_sender_id' => $this->getSmsSenderIdStatus(),
            'last_updated' => [
                'timestamp' => $this->updated_at,
                'by' => $this->updater?->name ?? 'System'
            ]
        ];
    }

    // ========== UTILITY METHODS ==========

    /**
     * Update settings with audit trail
     */
    public function updateSettings(array $data, User $updater): bool
    {
        $data['updated_by'] = $updater->id;
        
        return $this->update($data);
    }

    /**
     * Boot method for clearing cache
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });

        static::restored(function () {
            self::clearCache();
        });
    }

    /**
     * Calculate due date for a given period based on settings
     */
    public function calculateDueDateForPeriod(Carbon $periodDate): Carbon
    {
        $dueDay = 5;
        
        $dueDate = $periodDate->copy()->startOfMonth()->addDays($dueDay - 1);
        
        if ($this->grace_period_days > 0) {
            $dueDate->addDays($this->grace_period_days);
        }
        
        return $dueDate;
    }
}