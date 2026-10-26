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
        'allow_offline_payment',

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

        // 🆕 Homepage Content Configuration (JSON)
        'homepage_config',

        // 🆕 📢 AD Space Configuration (JSON)
        'ad_config',

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
        'allow_offline_payment' => 'boolean',

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

        // 🆕 Homepage Config (JSON)
        'homepage_config' => 'array',

        // 🆕 📢 AD Space Config (JSON)
        'ad_config' => 'array',

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

    // ========== 🆕 HOMEPAGE CONFIG DEFAULTS ==========

    /**
     * Sensible defaults so the welcome page never breaks.
     * These are merged with whatever the admin has saved.
     *
     * ⚠️ Now includes a `login` block so the controller's
     *     updateLoginPage() / resetLoginPageConfig() have something
     *     to merge against and the blade can read HOMEPAGE_DEFAULTS['login'].
     */
    public const HOMEPAGE_DEFAULTS = [
        'hero' => [
            'title'         => null,
            'subtitle'      => 'Your trusted platform for property registration and estate management. Register your land or property today and join our growing community.',
            'background'    => null,
            'cta_primary'   => 'Register Your Land/Property',
            'cta_secondary' => 'Share Your Experience',
        ],
        'sections' => [
            'how_it_works'    => true,
            'services'        => true,
            'benefits'        => true,
            'stats'           => true,
            'testimonials'    => true,
            'payment_methods' => true,
            'faq'             => true,
            'cta'             => true,
            'contact'         => true,
        ],
        'social' => [
            'facebook'  => '',
            'twitter'   => '',
            'instagram' => '',
            'whatsapp'  => '',
            'linkedin'  => '',
            'youtube'   => '',
            'tiktok'    => '',
        ],
        'contact' => [
            'email'   => null,
            'phone'   => null,
            'address' => null,
            'hours'   => "Mon-Fri: 8:00 AM - 5:00 PM\nSat: 9:00 AM - 1:00 PM",
        ],
        'footer' => [
            'copyright' => null,
        ],

        // 🎨 Theme customization
        'theme' => [
            'preset' => 'default',

            'colors' => [
                'primary'   => null,
                'secondary' => null,
                'accent'    => null,
                'hero_bg'   => null,
                'hero_text' => '#ffffff',
            ],

            'fonts' => [
                'family'         => 'system',
                'base_size'      => 16,
                'heading_weight' => '700',
            ],

            'hero' => [
                'overlay_opacity' => 0.35,
                'text_align'      => 'center',
                'height'          => 'standard',
            ],

            'layout' => [
                'container_width' => 1200,
                'section_padding' => 'normal',
                'radius_style'    => 'rounded',
            ],

            'buttons' => [
                'shape'  => 'rounded',
                'shadow' => true,
            ],
        ],

        // 🆕 🔐 LOGIN PAGE DEFAULTS
        // Must exist because:
        //   - HomepageContentController::updateLoginPage() references it
        //   - HomepageContentController::resetLoginPageConfig() resets to it
        //   - index.blade.php reads HOMEPAGE_DEFAULTS['login']
        'login' => [
            'background'               => null,       // uploaded file path (storage/public/login-page/...)
            'background_url'           => null,       // external fallback URL
            'overlay_opacity'          => 0.85,
            'default_theme'            => 'dark',     // dark | light | system
            'system_name_override'     => null,
            'welcome_title'            => 'Welcome Back',
            'welcome_subtitle'         => 'Sign in to manage your properties, invoices and dues — all in one place.',
            'show_welcome_panel'       => true,
            'show_social_icons'        => true,
            'show_cookie_banner'       => true,
            'welcome_features'         => [
                ['icon' => 'fas fa-home',        'text' => 'Manage your properties'],
                ['icon' => 'fas fa-file-invoice', 'text' => 'Track invoices & dues'],
                ['icon' => 'fas fa-shield-alt',  'text' => 'Secure & reliable'],
            ],
            'footer_socials' => [
                ['platform' => '', 'url' => ''],
                ['platform' => '', 'url' => ''],
                ['platform' => '', 'url' => ''],
                ['platform' => '', 'url' => ''],
            ],

            // 🆕 🔐 LOGIN THEME — mirrors the homepage theme block so
            //     HomepageContentController::applyLoginThemePreset()
            //     and resetLoginTheme() have a full shape to merge into,
            //     and auth.login can render CSS variables directly.
            'theme' => [
                'preset' => 'default',

                'colors' => [
                    'primary'    => '#2563eb',
                    'secondary'  => '#1e40af',
                    'accent'     => '#f59e0b',
                    'panel_bg'   => '#0f172a',
                    'panel_text' => '#f8fafc',
                    'form_bg'    => '#ffffff',
                    'form_text'  => '#0f172a',
                ],

                'fonts' => [
                    'family'         => 'system',
                    'base_size'      => 16,
                    'heading_weight' => '700',
                ],

                'layout' => [
                    'split'        => 'panel-left',   // panel-left | panel-right | single-column
                    'panel_width'  => 50,             // percent, 30–70
                    'radius_style' => 'rounded',      // sharp | rounded | pill
                    'card_shadow'  => true,
                ],

                'buttons' => [
                    'shape'  => 'rounded',            // rounded | pill | square
                    'shadow' => true,
                ],
            ],
        ],
    ];

    /**
     * Built-in theme presets admins can apply with one click.
     */
    public const THEME_PRESETS = [
        'default' => [
            'name' => 'Default',
            'desc' => 'Blue/purple gradient — the current palette',
            'values' => [
                'colors' => [
                    'primary'   => '#3b82f6',
                    'secondary' => '#8b5cf6',
                    'accent'    => '#06b6d4',
                ],
                'fonts'   => ['family' => 'system', 'base_size' => 16, 'heading_weight' => '700'],
                'hero'    => ['overlay_opacity' => 0.35, 'text_align' => 'center', 'height' => 'standard'],
                'layout'  => ['container_width' => 1200, 'section_padding' => 'normal', 'radius_style' => 'rounded'],
                'buttons' => ['shape' => 'rounded', 'shadow' => true],
            ],
        ],

        'modern' => [
            'name' => 'Modern',
            'desc' => 'Teal-to-indigo, rounded, glassy',
            'values' => [
                'colors' => [
                    'primary'   => '#14b8a6',
                    'secondary' => '#6366f1',
                    'accent'    => '#f43f5e',
                ],
                'fonts'   => ['family' => 'inter', 'base_size' => 16, 'heading_weight' => '800'],
                'hero'    => ['overlay_opacity' => 0.45, 'text_align' => 'center', 'height' => 'tall'],
                'layout'  => ['container_width' => 1280, 'section_padding' => 'spacious', 'radius_style' => 'rounded'],
                'buttons' => ['shape' => 'pill', 'shadow' => true],
            ],
        ],

        'classic' => [
            'name' => 'Classic',
            'desc' => 'Navy & gold, sharp corners, serif',
            'values' => [
                'colors' => [
                    'primary'   => '#1e3a8a',
                    'secondary' => '#b45309',
                    'accent'    => '#0369a1',
                ],
                'fonts'   => ['family' => 'playfair', 'base_size' => 17, 'heading_weight' => '700'],
                'hero'    => ['overlay_opacity' => 0.55, 'text_align' => 'center', 'height' => 'standard'],
                'layout'  => ['container_width' => 1140, 'section_padding' => 'normal', 'radius_style' => 'sharp'],
                'buttons' => ['shape' => 'rounded', 'shadow' => false],
            ],
        ],

        'elegant' => [
            'name' => 'Elegant',
            'desc' => 'Deep plum & rose, pill shapes',
            'values' => [
                'colors' => [
                    'primary'   => '#7c3aed',
                    'secondary' => '#ec4899',
                    'accent'    => '#f59e0b',
                ],
                'fonts'   => ['family' => 'poppins', 'base_size' => 16, 'heading_weight' => '600'],
                'hero'    => ['overlay_opacity' => 0.50, 'text_align' => 'center', 'height' => 'tall'],
                'layout'  => ['container_width' => 1240, 'section_padding' => 'spacious', 'radius_style' => 'pill'],
                'buttons' => ['shape' => 'pill', 'shadow' => true],
            ],
        ],

        'bold' => [
            'name' => 'Bold',
            'desc' => 'High-contrast orange & black',
            'values' => [
                'colors' => [
                    'primary'   => '#ea580c',
                    'secondary' => '#0f172a',
                    'accent'    => '#22c55e',
                ],
                'fonts'   => ['family' => 'roboto', 'base_size' => 16, 'heading_weight' => '800'],
                'hero'    => ['overlay_opacity' => 0.60, 'text_align' => 'left', 'height' => 'full'],
                'layout'  => ['container_width' => 1320, 'section_padding' => 'normal', 'radius_style' => 'sharp'],
                'buttons' => ['shape' => 'square', 'shadow' => false],
            ],
        ],
    ];

    // ========== 🆕 📢 AD SPACE DEFAULTS ==========

    /**
     * Sensible defaults for the AD space subsystem.
     *
     * Stored under `ad_config` (JSON column). Every read goes through
     * getAdConfig() which merges these defaults in, so the app never
     * encounters a missing key.
     *
     * ⚠️ If you add a new ad setting, add its default HERE — the
     *     admin form, service layer, and Blade all read through
     *     getAdConfig() and will pick it up automatically.
     */
    public const AD_DEFAULTS = [
        // Master switch — off hides ads + blocks new campaign creation
        'enabled' => true,

        // Caps
        'max_active_per_landlord' => 3,   // includes pending_payment
        'max_on_homepage'         => 6,

        // Approval workflow
        'require_admin_approval'   => true,   // review campaigns before going live
        'auto_approve_mark_rented' => false,  // auto-approve "mark as rented" (use with caution)

        // Channel availability — each maps to AdCampaign.channels[]
        'channels' => [
            'homepage'  => true,
            'facebook'  => true,
            'whatsapp'  => true,
            'twitter'   => false,
            'linkedin'  => false,
            'instagram' => false,
        ],

        // Display options
        'show_sponsored_badge'  => true,
        'show_view_count'       => true,
        'allow_landlord_proof_upload' => true,
    ];

    /**
     * Seed pricing plans the admin can one-click create.
     * These are NOT auto-created — they're templates only.
     */
    public const AD_PLAN_PRESETS = [
        'monthly' => [
            'name'             => 'Standard Monthly',
            'billing_period'   => 'monthly',
            'duration_months'  => 1,
            'price'            => 50.00,
            'currency'         => 'GHS',
            'description'      => 'One month of homepage + social exposure.',
            'sort_order'       => 1,
            'is_active'        => true,
        ],
        'quarterly' => [
            'name'             => 'Quarterly Boost',
            'billing_period'   => 'monthly',
            'duration_months'  => 3,
            'price'            => 135.00,   // 10% off 3×50
            'currency'         => 'GHS',
            'description'      => 'Three months of exposure at a 10% discount.',
            'sort_order'       => 2,
            'is_active'        => true,
        ],
        'yearly' => [
            'name'             => 'Annual Premium',
            'billing_period'   => 'yearly',
            'duration_months'  => 12,
            'price'            => 480.00,   // 20% off 12×50
            'currency'         => 'GHS',
            'description'      => 'Full year of exposure at a 20% discount.',
            'sort_order'       => 3,
            'is_active'        => true,
        ],
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

    private static function getDefaultSettingsObject()
    {
        $settings = new self();
        $defaults = self::getDefaultSettings();

        foreach ($defaults as $key => $value) {
            $settings->$key = $value;
        }

        return $settings;
    }

    public static function clearCache()
    {
        Cache::forget('system_settings');
        Cache::forget('sms_default_sender_id');
    }

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

    public function isOfflinePaymentAllowed(): bool
    {
        return (bool) ($this->allow_offline_payment ?? true);
    }

    // ========== SMS SENDER ID METHODS ==========

    public function hasSmsSenderId(): bool
    {
        return !empty($this->sms_sender_id) && trim((string) $this->sms_sender_id) !== '';
    }

    public function getSmsSenderId(): ?string
    {
        return $this->hasSmsSenderId()
            ? trim((string) $this->sms_sender_id)
            : null;
    }

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

    public function getInvoiceNotificationChannels(): array
    {
        $channels = $this->invoice_notification_channels ?? ['email'];
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        return $channels;
    }

    public function getPaymentReminderChannels(): array
    {
        $channels = $this->payment_reminder_channels ?? ['email'];
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        return $channels;
    }

    public function getOverdueNotificationChannels(): array
    {
        $channels = $this->overdue_notification_channels ?? ['email', 'sms'];
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        return $channels;
    }

    public function getPaymentConfirmationChannels(): array
    {
        $channels = $this->payment_confirmation_channels ?? ['email'];
        if (!in_array('email', $channels)) {
            $channels[] = 'email';
        }
        return $channels;
    }

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

    public function isSmsEnabled(): bool
    {
        return $this->sms_notifications_enabled === true;
    }

    public function isSmsReminderEnabled(): bool
    {
        return $this->isSmsEnabled() && $this->sms_reminder_enabled === true;
    }

    public function isSmsPaymentConfirmationEnabled(): bool
    {
        return $this->isSmsEnabled() && $this->sms_payment_confirmation_enabled === true;
    }

    public function getSmsRateLimits(): array
    {
        return [
            'daily_limit' => $this->sms_daily_limit_per_user ?? 10,
            'hourly_limit' => $this->sms_hourly_limit_per_user ?? 3,
        ];
    }

    public function isWhatsAppEnabled(): bool
    {
        return $this->enable_whatsapp_notifications === true && $this->isWhatsAppConfigured();
    }

    public function isWhatsAppReminderEnabled(): bool
    {
        return $this->isWhatsAppEnabled() && $this->whatsapp_reminder_enabled === true;
    }

    public function isWhatsAppPaymentConfirmationEnabled(): bool
    {
        return $this->isWhatsAppEnabled() && $this->whatsapp_payment_confirmation_enabled === true;
    }

    public function shouldForceEmailFallback(): bool
    {
        return $this->force_email_fallback === true;
    }

    public function getNotificationRetryConfig(): array
    {
        return [
            'attempts' => $this->notification_retry_attempts ?? 3,
            'delay_minutes' => $this->notification_retry_delay_minutes ?? 5,
        ];
    }

    public function shouldEnableBulkNotifications(): bool
    {
        return $this->enable_bulk_notifications === true;
    }

    public function getBulkNotificationBatchSize(): int
    {
        return $this->bulk_notification_batch_size ?? 50;
    }

    public function getBulkNotificationDelaySeconds(): int
    {
        return $this->bulk_notification_delay_seconds ?? 2;
    }

    public function shouldLogAllNotifications(): bool
    {
        return $this->log_all_notifications === true;
    }

    public function shouldLogNotificationContent(): bool
    {
        return $this->log_notification_content === true;
    }

    public function getNotificationLogRetentionDays(): int
    {
        return $this->notification_log_retention_days ?? 90;
    }

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

    // ========== 🆕 HOMEPAGE CONTENT METHODS ==========

    public function getHomepageConfig(): array    {
        $stored = $this->homepage_config ?? [];

        if (!is_array($stored)) {
            $stored = [];
        }

        return $this->arrayReplaceRecursiveDeep(self::HOMEPAGE_DEFAULTS, $stored);
    }

    protected function arrayReplaceRecursiveDeep(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->arrayReplaceRecursiveDeep($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }

    public function hasCustomHomepage(): bool
    {
        return !empty($this->homepage_config)
            && is_array($this->homepage_config)
            && count($this->homepage_config) > 0;
    }

    public function getHomepageHeroImageUrl(): ?string
    {
        $config = $this->getHomepageConfig();
        $path   = $config['hero']['background'] ?? null;

        if (empty($path)) {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getHomepageHeroImagePath(): ?string
    {
        $config = $this->getHomepageConfig();
        $path   = $config['hero']['background'] ?? null;

        return !empty($path) ? $path : null;
    }

    public function getHomepageHeroTitle(): string
    {
        $config = $this->getHomepageConfig();
        $title  = $config['hero']['title'] ?? null;

        return !empty($title) ? $title : ($this->system_name ?? 'Community Portal');
    }

    public function getHomepageHeroSubtitle(): string
    {
        $config = $this->getHomepageConfig();
        return $config['hero']['subtitle'] ?? self::HOMEPAGE_DEFAULTS['hero']['subtitle'];
    }

    public function getHomepageHeroCtas(): array
    {
        $config = $this->getHomepageConfig();
        return [
            'primary'   => $config['hero']['cta_primary']   ?? self::HOMEPAGE_DEFAULTS['hero']['cta_primary'],
            'secondary' => $config['hero']['cta_secondary'] ?? self::HOMEPAGE_DEFAULTS['hero']['cta_secondary'],
        ];
    }

    public function getHomepageSections(): array
    {
        $config = $this->getHomepageConfig();
        return $config['sections'] ?? self::HOMEPAGE_DEFAULTS['sections'];
    }

    public function isHomepageSectionVisible(string $section): bool
    {
        $sections = $this->getHomepageSections();
        return (bool) ($sections[$section] ?? true);
    }

    public function getHomepageSocialLinks(): array
    {
        $config = $this->getHomepageConfig();
        return $config['social'] ?? self::HOMEPAGE_DEFAULTS['social'];
    }

    public function getHomepageSocialLinksNonEmpty(): array
    {
        return array_filter(
            $this->getHomepageSocialLinks(),
            fn($v) => !empty($v)
        );
    }

    public function getHomepageContact(): array
    {
        $config = $this->getHomepageConfig();
        $c      = $config['contact'] ?? self::HOMEPAGE_DEFAULTS['contact'];

        return [
            'email'   => !empty($c['email'])   ? $c['email']   : ($this->system_email  ?? ''),
            'phone'   => !empty($c['phone'])   ? $c['phone']   : ($this->system_phone  ?? ''),
            'address' => !empty($c['address']) ? $c['address'] : ('Estate Office, ' . ($this->system_name ?? 'Community Portal')),
            'hours'   => !empty($c['hours'])   ? $c['hours']   : self::HOMEPAGE_DEFAULTS['contact']['hours'],
        ];
    }

    public function getHomepageFooter(): array
    {
        $config    = $this->getHomepageConfig();
        $copyright = $config['footer']['copyright'] ?? null;

        if (empty($copyright)) {
            $copyright = '© ' . date('Y') . ' ' . ($this->system_name ?? 'Community Portal') . '. All rights reserved.';
        }

        return [
            'copyright' => $copyright,
        ];
    }

    public function resetHomepageConfig(): bool
    {
        $path = $this->getHomepageHeroImagePath();
        if (!empty($path)) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                // Silent
            }
        }

        $this->homepage_config = null;
        return $this->save();
    }

    public function setHomepageHeroBackground(?string $path): bool
    {
        $oldPath = $this->getHomepageHeroImagePath();
        if (!empty($oldPath) && $oldPath !== $path) {
            try {
                Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {
                // Silent
            }
        }

        $config = $this->getHomepageConfig();
        $config['hero']['background'] = $path;

        $this->homepage_config = $config;
        return $this->save();
    }

    public function getHomepageStatus(): array
    {
        $config       = $this->getHomepageConfig();
        $sections     = $config['sections'] ?? [];
        $social       = $config['social']   ?? [];
        $contact      = $config['contact']  ?? [];
        $hero         = $config['hero']     ?? [];

        $sectionsOn   = count(array_filter($sections));
        $sectionsAll  = count($sections);
        $socialSet    = count(array_filter($social));
        $socialAll    = count($social);
        $contactSet   = count(array_filter($contact)) > 0;

        return [
            'has_custom'      => $this->hasCustomHomepage(),
            'hero_image_url'  => $this->getHomepageHeroImageUrl(),
            'hero_title'      => $hero['title'] ?? null,
            'hero_title_used' => $this->getHomepageHeroTitle(),
            'hero_subtitle'   => $hero['subtitle'] ?? null,
            'sections_on'     => $sectionsOn,
            'sections_total'  => $sectionsAll,
            'social_set'      => $socialSet,
            'social_total'    => $socialAll,
            'contact_custom'  => $contactSet,
            'hero_custom'     => !empty($hero['title']) || !empty($hero['subtitle']),
            'theme'           => $this->getHomepageThemeStatus(),
        ];
    }

    // ========== 🎨 HOMEPAGE THEME METHODS ==========

    public function getHomepageTheme(): array
    {
        $config = $this->getHomepageConfig();
        return $config['theme'] ?? self::HOMEPAGE_DEFAULTS['theme'];
    }

    public function getHomepageThemePreset(): string
    {
        $theme = $this->getHomepageTheme();
        return $theme['preset'] ?? 'default';
    }

    public function applyHomepageThemePreset(string $presetKey): array
    {
        $preset = self::THEME_PRESETS[$presetKey] ?? null;
        if (!$preset) {
            return $this->getHomepageTheme();
        }

        $current = $this->getHomepageTheme();
        $merged  = $this->arrayReplaceRecursiveDeep($current, $preset['values']);
        $merged['preset'] = $presetKey;

        return $merged;
    }

    public function getHomepageThemeStatus(): array
    {
        $theme = $this->getHomepageTheme();

        return [
            'preset'           => $theme['preset'] ?? 'default',
            'preset_name'      => self::THEME_PRESETS[$theme['preset'] ?? 'default']['name'] ?? 'Default',
            'primary_color'    => $theme['colors']['primary']   ?? null,
            'secondary_color'  => $theme['colors']['secondary'] ?? null,
            'accent_color'     => $theme['colors']['accent']    ?? null,
            'font_family'      => $theme['fonts']['family']     ?? 'system',
            'hero_height'      => $theme['hero']['height']      ?? 'standard',
            'is_customised'    => ($theme['preset'] ?? 'default') !== 'default'
                                  || !empty($theme['colors']['primary'])
                                  || !empty($theme['colors']['secondary'])
                                  || !empty($theme['colors']['accent']),
        ];
    }

    // ========== 🔐 LOGIN PAGE CONFIG METHODS ==========
    // These are what HomepageContentController::index() / uploadLoginBackground()
    // / removeLoginBackground() / updateLoginPage() / resetLoginPage() need.

    /**
     * Get the resolved login-page config (defaults merged in).
     * Always returns a complete, safe-to-render array.
     */
    public function getLoginPageConfig(): array
    {
        $config = $this->getHomepageConfig();
        $login  = $config['login'] ?? self::HOMEPAGE_DEFAULTS['login'];

        if (!is_array($login)) {
            $login = self::HOMEPAGE_DEFAULTS['login'];
        }

        // Merge in any defaults that a partial save may have dropped.
        return $this->arrayReplaceRecursiveDeep(self::HOMEPAGE_DEFAULTS['login'], $login);
    }

    /**
     * Resolve the login background image URL.
     *
     * Priority:
     *   1. Uploaded file (storage/public/login-page/...)
     *   2. External fallback URL (login.background_url)
     *   3. null (caller uses a hard-coded default image)
     */
    public function getLoginPageBackgroundUrl(): ?string
    {
        $config = $this->getLoginPageConfig();

        // 1. Uploaded file
        $path = $config['background'] ?? null;
        if (!empty($path)) {
            try {
                if (Storage::disk('public')->exists($path)) {
                    return Storage::disk('public')->url($path);
                }
            } catch (\Throwable $e) {
                // Fall through to external URL
            }
        }

        // 2. External fallback URL
        $externalUrl = $config['background_url'] ?? null;
        if (!empty($externalUrl) && filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            return $externalUrl;
        }

        // 3. Nothing configured
        return null;
    }

    /**
     * Resolve the login background image PATH (for deletion / storage ops).
     */
    public function getLoginPageBackgroundPath(): ?string
    {
        $config = $this->getLoginPageConfig();
        $path   = $config['background'] ?? null;

        return !empty($path) ? $path : null;
    }

    /**
     * Store / replace / remove the login background image.
     *
     * Pass a relative storage path to set a new image
     * (e.g. "login-page/bg_abc123.jpg"), or null to remove it.
     *
     * Deletes the previous uploaded file when replacing/removing.
     */
    public function setLoginPageBackground(?string $path): bool
    {
        $oldPath = $this->getLoginPageBackgroundPath();

        if (!empty($oldPath) && $oldPath !== $path) {
            try {
                Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {
                // Silent — file may already be gone
            }
        }

        $config = $this->getHomepageConfig();
        $login  = $config['login'] ?? self::HOMEPAGE_DEFAULTS['login'];

        if (!is_array($login)) {
            $login = self::HOMEPAGE_DEFAULTS['login'];
        }

        $login['background'] = $path;
        $config['login']     = $login;

        $this->homepage_config = $config;
        return $this->save();
    }

    /**
     * Reset the login-page config back to defaults.
     * Deletes the uploaded background file if present.
     *
     * 🆕 Now also resets the login theme block to defaults, since
     *    the whole `login` key is being replaced with HOMEPAGE_DEFAULTS['login'].
     */
    public function resetLoginPageConfig(): bool
    {
        $path = $this->getLoginPageBackgroundPath();
        if (!empty($path)) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                // Silent
            }
        }

        $config = $this->getHomepageConfig();
        $config['login'] = self::HOMEPAGE_DEFAULTS['login'];

        $this->homepage_config = $config;
        return $this->save();
    }

    /**
     * Whether the login-page config deviates from defaults.
     */
    public function hasCustomLoginPage(): bool
    {
        $stored = $this->homepage_config['login'] ?? null;

        if (empty($stored) || !is_array($stored)) {
            return false;
        }

        $defaults = self::HOMEPAGE_DEFAULTS['login'];

        // Shallow compare of key settings
        foreach (['background', 'background_url', 'overlay_opacity', 'default_theme',
                  'system_name_override', 'welcome_title', 'welcome_subtitle'] as $key) {
            if (isset($stored[$key]) && $stored[$key] !== ($defaults[$key] ?? null)) {
                return true;
            }
        }

        foreach (['show_welcome_panel', 'show_social_icons', 'show_cookie_banner'] as $key) {
            if (array_key_exists($key, $stored) && (bool)$stored[$key] !== (bool)($defaults[$key] ?? true)) {
                return true;
            }
        }

        if (!empty($stored['welcome_features']) || !empty($stored['footer_socials'])) {
            return true;
        }

        return false;
    }

    // ========== 🆕 🔐 LOGIN THEME METHODS ==========

    /**
     * Get the resolved login theme (defaults merged in).
     */
    public function getLoginPageTheme(): array
    {
        $config = $this->getLoginPageConfig();
        return $config['theme'] ?? self::HOMEPAGE_DEFAULTS['login']['theme'];
    }

    /**
     * Current login theme preset key.
     */
    public function getLoginPageThemePreset(): string
    {
        $theme = $this->getLoginPageTheme();
        return $theme['preset'] ?? 'default';
    }

    /**
     * Summary of the login theme for admin cards / status panels.
     */
    public function getLoginPageThemeStatus(): array
    {
        $theme = $this->getLoginPageTheme();
        $preset = $theme['preset'] ?? 'default';

        return [
            'preset'          => $preset,
            'preset_name'     => self::THEME_PRESETS[$preset]['name'] ?? 'Custom',
            'primary_color'   => $theme['colors']['primary']   ?? null,
            'secondary_color' => $theme['colors']['secondary'] ?? null,
            'accent_color'    => $theme['colors']['accent']    ?? null,
            'font_family'     => $theme['fonts']['family']     ?? 'system',
            'layout_split'    => $theme['layout']['split']     ?? 'panel-left',
            'is_customised'   => $this->hasCustomLoginTheme(),
        ];
    }

    /**
     * Whether the admin has manually overridden the login theme.
     */
    public function hasCustomLoginTheme(): bool
    {
        $stored = $this->homepage_config['login']['theme'] ?? null;

        if (empty($stored) || !is_array($stored)) {
            return false;
        }

        // Anything other than the default preset with default palette counts as custom.
        if (($stored['preset'] ?? 'default') !== 'default') {
            return true;
        }

        $defaults = self::HOMEPAGE_DEFAULTS['login']['theme'];

        // Compare color/font/layout keys against defaults
        foreach (['colors', 'fonts', 'layout', 'buttons'] as $group) {
            foreach (($stored[$group] ?? []) as $key => $value) {
                if ($value !== ($defaults[$group][$key] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 🆕 Return which login theme fields the admin has EXPLICITLY set.
     *
     * Used by the login Blade so it can:
     *   - Render admin-set colors verbatim (both light + dark).
     *   - Fall back to sensible dark-mode defaults for fields the admin
     *     left at the theme default, preventing the "white card with
     *     invisible dark text on a dark page" bug.
     *
     * A field counts as "explicit" if the stored value differs from
     * HOMEPAGE_DEFAULTS['login']['theme'][...].
     */
    public function getLoginThemeOverrides(): array
    {
        $stored   = $this->homepage_config['login']['theme'] ?? [];
        $defaults = self::HOMEPAGE_DEFAULTS['login']['theme'] ?? [];

        $overrides = [
            'colors'  => [],
            'fonts'   => [],
            'layout'  => [],
            'buttons' => [],
        ];

        if (!is_array($stored)) {
            return $overrides;
        }

        foreach ($overrides as $group => $_) {
            foreach (($stored[$group] ?? []) as $key => $value) {
                $defaultValue = $defaults[$group][$key] ?? null;
                if ($value !== $defaultValue) {
                    $overrides[$group][$key] = true;
                }
            }
        }

        // 🆕 If the preset was changed to a non-default preset, treat
        //    every color the preset sets as "explicit" — otherwise the
        //    dark-mode fallbacks would clobber the preset's palette.
        $preset = $stored['preset'] ?? 'default';
        if ($preset !== 'default') {
            $presetValues = self::THEME_PRESETS[$preset]['values'] ?? [];

            // Primary/secondary/accent are always overridden by a preset
            foreach (['primary', 'secondary', 'accent'] as $k) {
                if (!empty($presetValues['colors'][$k])) {
                    $overrides['colors'][$k] = true;
                }
            }

            // Form/panel colors: only override if the preset (or the
            // store) actually sets them. Most presets only define
            // primary/secondary/accent, so form/panel keep the dark
            // fallbacks — which is exactly what we want.
            foreach (['panel_bg', 'panel_text', 'form_bg', 'form_text'] as $k) {
                if (!empty($stored['colors'][$k]) && $stored['colors'][$k] !== ($defaults['colors'][$k] ?? null)) {
                    $overrides['colors'][$k] = true;
                }
            }
        }

        return $overrides;
    }

    /**
     * Build the CSS variable map the login Blade can drop into :root.
     *
     * Keeping this in the model means the view stays dumb, and tests can
     * assert on the resolved variables rather than scraping HTML.
     */
    public function getLoginPageCssVariables(): array
    {
        $theme = $this->getLoginPageTheme();
        $c     = $theme['colors']  ?? [];
        $f     = $theme['fonts']   ?? [];
        $l     = $theme['layout']  ?? [];
        $b     = $theme['buttons'] ?? [];

        $radiusMap = [
            'sharp'   => '0',
            'rounded' => '0.75rem',
            'pill'    => '999px',
        ];

        $shapeMap = [
            'square'  => '0',
            'rounded' => '0.5rem',
            'pill'    => '999px',
        ];

        return [
            '--login-primary'        => $c['primary']    ?? '#2563eb',
            '--login-secondary'      => $c['secondary']  ?? '#1e40af',
            '--login-accent'         => $c['accent']     ?? '#f59e0b',
            '--login-panel-bg'       => $c['panel_bg']   ?? '#0f172a',
            '--login-panel-text'     => $c['panel_text'] ?? '#f8fafc',
            '--login-form-bg'        => $c['form_bg']    ?? '#ffffff',
            '--login-form-text'      => $c['form_text']  ?? '#0f172a',
            '--login-radius'         => $radiusMap[$l['radius_style'] ?? 'rounded'] ?? '0.75rem',
            '--login-btn-radius'     => $shapeMap[$b['shape'] ?? 'rounded'] ?? '0.5rem',
            '--login-font-family'    => $f['family'] ?? 'system',
            '--login-base-size'      => ($f['base_size'] ?? 16) . 'px',
            '--login-heading-weight' => $f['heading_weight'] ?? '700',
            '--login-panel-width'    => ($l['panel_width'] ?? 50) . '%',
            '--login-card-shadow'    => ($l['card_shadow'] ?? true)
                ? '0 20px 40px rgba(0,0,0,.15)'
                : 'none',
            '--login-btn-shadow'     => ($b['shadow'] ?? true)
                ? '0 6px 16px rgba(0,0,0,.15)'
                : 'none',
        ];
    }

    // ========== YEAR-END ARCHIVE METHODS ==========

    public function isYearEndArchiveEnabled(): bool
    {
        return (bool) ($this->enable_year_end_archive ?? true);
    }

    public function isYearEndArchiveEnabledForTenant(): bool
    {
        return (bool) ($this->enable_year_end_archive_tenant ?? true);
    }

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

    public function isYearEndArchiveDate(): bool
    {
        if (!$this->isYearEndArchiveEnabled()) {
            return false;
        }

        $month = $this->year_end_archive_month ?? 1;
        $day = $this->year_end_archive_day ?? 15;

        return now()->month == $month && now()->day == $day;
    }

    public function isTenantYearEndArchiveDate(): bool
    {
        if (!$this->isYearEndArchiveEnabledForTenant()) {
            return false;
        }

        $month = $this->year_end_archive_month_tenant ?? 1;
        $day = $this->year_end_archive_day_tenant ?? 16;

        return now()->month == $month && now()->day == $day;
    }

    public function getDaysUntilYearEndArchive(): ?int
    {
        $archiveDate = $this->getNextYearEndArchiveDate();

        if (!$archiveDate) {
            return null;
        }

        return now()->diffInDays($archiveDate, false);
    }

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

    public function scopeConfigured($query)
    {
        return $query->whereNotNull('system_name')
                    ->whereNotNull('system_email')
                    ->whereNotNull('currency_code');
    }

    public static function initialize(array $data, User $creator): self
    {
        $defaults = self::getDefaultSettings();
        $settings = array_merge($defaults, $data);
        $settings['created_by'] = $creator->id;
        $settings['updated_by'] = $creator->id;

        return static::create($settings);
    }

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

            'sms_sender_id' => config('sms.sender_id', null),

            'currency_code' => 'GHS',
            'currency_symbol' => 'GH₵',
            'currency_position' => 'left',
            'decimal_places' => 2,

            'monthly_dues_amount' => 100.00,
            'calculation_method' => 'fixed',
            'per_property_amount' => 50.00,

            'enable_tenant_invoicing' => true,
            'tenant_monthly_dues_amount' => 50.00,
            'tenant_calculation_method' => 'fixed',
            'auto_generate_tenant_invoices' => true,
            'send_tenant_payment_reminders' => true,
            'tenant_grace_period_days' => 7,
            'tenant_late_payment_percentage' => 5.00,
            'tenant_fixed_penalty_amount' => 0.00,

            'grace_period_days' => 7,
            'late_payment_percentage' => 5.00,
            'fixed_penalty_amount' => 0.00,

            'auto_generate_invoices' => true,
            'send_payment_reminders' => true,
            'reminder_days_before' => 3,

            'enable_expresspay' => false,
            'enable_hubtel' => false,
            'enable_paystack' => false,
            'enable_flutterwave' => false,

            'enable_bulk_payments' => true,
            'max_bulk_months' => 6,
            'bulk_payment_discount' => 0,

            'whatsapp_provider' => 'none',
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_whatsapp_from' => '',
            'vonage_key' => '',
            'vonage_secret' => '',
            'vonage_whatsapp_from' => '',
            'whatsapp_api_url' => '',
            'whatsapp_api_key' => '',

            'allow_registration' => true,
            'registration_disabled_message' => 'New registrations are currently disabled. Please contact the administrator for assistance.',

            // 🆕 Starts as null — HOMEPAGE_DEFAULTS supplies everything else
            'homepage_config' => null,

            // 🆕 📢 Starts as null — AD_DEFAULTS supplies everything else
            'ad_config' => null,
        ];
    }

    // ========== LOGO METHODS ==========

    public function hasLogo(): bool
    {
        return !empty($this->system_logo) && Storage::disk('public')->exists($this->system_logo);
    }

    public function getLogoUrl(): string
    {
        if ($this->hasLogo()) {
            return Storage::disk('public')->url($this->system_logo);
        }

        return asset('images/default-logo.png');
    }

    public function getDisplayLogo(): string
    {
        return $this->getLogoUrl();
    }

    public function getDefaultLogoPlaceholder(): string
    {
        return asset('images/default-system-logo.png');
    }

    public function removeLogo(): bool
    {
        $this->system_logo = null;
        return $this->save();
    }

    // ========== FAVICON METHODS ==========

    public function hasFavicon(): bool
    {
        return !empty($this->system_favicon) && Storage::disk('public')->exists($this->system_favicon);
    }

    public function getFaviconUrl(): ?string
    {
        if ($this->hasFavicon()) {
            return Storage::disk('public')->url($this->system_favicon);
        }

        return null;
    }

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

    public function getFaviconForLayout(): string
    {
        return $this->getFaviconHtml();
    }

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

    public function removeFavicon(): bool
    {
        if ($this->hasFavicon()) {
            Storage::disk('public')->delete($this->system_favicon);
        }

        $this->system_favicon = null;
        return $this->save();
    }

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

    public function getSystemShortName(): string
    {
        return $this->system_short_name ?? $this->system_name ?? config('app.name', 'System');
    }

    public function getSystemIdentifier(): array
    {
        return [
            'short_name' => $this->getSystemShortName(),
            'full_name' => $this->system_name,
            'slug' => $this->system_short_name,
            'display_name' => $this->system_name ?? $this->getSystemShortName()
        ];
    }

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

    public function isRegistrationAllowed(): bool
    {
        return $this->allow_registration ?? true;
    }

    public function getRegistrationDisabledMessage(): string
    {
        return $this->registration_disabled_message ??
               'New registrations are currently disabled. Please contact the administrator for assistance.';
    }

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

    public function enableRegistration(?string $message = null): bool
    {
        $this->allow_registration = true;
        $this->registration_disabled_message = $message;
        return $this->save();
    }

    public function disableRegistration(?string $message = null): bool
    {
        $this->allow_registration = false;

        if ($message) {
            $this->registration_disabled_message = $message;
        }

        return $this->save();
    }

    // ========== TENANT INVOICE METHODS ==========

    public function isTenantInvoicingEnabled(): bool
    {
        return $this->enable_tenant_invoicing ?? false;
    }

    public function getTenantMonthlyDuesAmount(): float
    {
        return (float) ($this->tenant_monthly_dues_amount ?? 0);
    }

    public function getTenantCalculationMethod(): string
    {
        return $this->tenant_calculation_method ?? 'fixed';
    }

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

    public function getCalculatedTenantDues(): float
    {
        return $this->calculateTenantDues();
    }

    public function getFormattedTenantMonthlyDues(): string
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return 'Not enabled';
        }

        $amount = $this->calculateTenantDues();
        return $this->formatAmount($amount);
    }

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

    public function shouldAutoGenerateTenantInvoices(): bool
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return false;
        }

        return (bool) ($this->auto_generate_tenant_invoices ?? true);
    }

    public function shouldSendTenantPaymentReminders(): bool
    {
        if (!$this->isTenantInvoicingEnabled()) {
            return false;
        }

        return (bool) ($this->send_tenant_payment_reminders ?? true);
    }

    public function getTenantGracePeriodDays(): int
    {
        return $this->tenant_grace_period_days ?? $this->grace_period_days ?? 7;
    }

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

    public function isPaymentConfigurationComplete(): bool
    {
        return $this->hasEnabledPaymentMethods();
    }

    public function hasEnabledPaymentMethods(): bool
    {
        return $this->enable_expresspay ||
               $this->enable_hubtel ||
               $this->enable_paystack ||
               $this->enable_flutterwave;
    }

    public function getEnabledPaymentProvidersCount(): int
    {
        $count = 0;
        if ($this->enable_expresspay) $count++;
        if ($this->enable_hubtel) $count++;
        if ($this->enable_paystack) $count++;
        if ($this->enable_flutterwave) $count++;

        return $count;
    }

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

    public function getEnabledGatewayNames(): array
    {
        $gateways = [];
        if ($this->enable_expresspay) $gateways[] = 'ExpressPay';
        if ($this->enable_hubtel) $gateways[] = 'Hubtel';
        if ($this->enable_paystack) $gateways[] = 'Paystack';
        if ($this->enable_flutterwave) $gateways[] = 'Flutterwave';

        return $gateways;
    }

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

    public function calculateDues(?Property $property = null): float
    {
        return match($this->calculation_method) {
            'per_property' => (float) $this->per_property_amount,
            'fixed' => (float) $this->monthly_dues_amount,
            default => (float) $this->monthly_dues_amount,
        };
    }

    public function calculateMonthlyDuesForProperty(Property $property): float
    {
        return $this->calculateDues($property);
    }

    public function getCalculationMethodDescription(): string
    {
        return match($this->calculation_method) {
            'per_property' => "Per Property - " . $this->formatAmount($this->per_property_amount) . " per property",
            'fixed' => "Fixed Amount - " . $this->formatAmount($this->monthly_dues_amount) . " for all properties",
            default => "Fixed Amount - " . $this->formatAmount($this->monthly_dues_amount) . " for all properties",
        };
    }

    public function getDuesAmount(): float
    {
        return match($this->calculation_method) {
            'per_property' => (float) $this->per_property_amount,
            'fixed' => (float) $this->monthly_dues_amount,
            default => (float) $this->monthly_dues_amount,
        };
    }

    public function getFormattedMonthlyDues(): string
    {
        return $this->formatAmount($this->getDuesAmount());
    }

    public function getFormattedDuesAmount(): string
    {
        if ($this->calculation_method === 'per_property') {
            return $this->formatAmount($this->per_property_amount) . ' per property';
        }

        return $this->formatAmount($this->monthly_dues_amount);
    }

    public function getMonthlyDuesAmount(): float
    {
        return (float) $this->monthly_dues_amount;
    }

    public function getCalculationMethod(): string
    {
        return $this->calculation_method;
    }

    public function getPerPropertyAmount(): float
    {
        return (float) $this->per_property_amount;
    }

    public function isPerPropertyCalculation(): bool
    {
        return $this->calculation_method === 'per_property';
    }

    public function isFixedCalculation(): bool
    {
        return $this->calculation_method === 'fixed';
    }

    // ========== PAYMENT REMINDER METHODS ==========

    public function shouldSendPaymentReminders(): bool
    {
        return $this->send_payment_reminders === true;
    }

    public function getReminderDaysBefore(): int
    {
        return $this->reminder_days_before ?? 3;
    }

    public function getPaymentReminderConfig(): array
    {
        return [
            'enabled' => $this->shouldSendPaymentReminders(),
            'days_before' => $this->getReminderDaysBefore(),
            'reminder_schedule' => $this->generateReminderSchedule()
        ];
    }

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

    public function isBulkPaymentEnabled(): bool
    {
        return (bool) $this->enable_bulk_payments;
    }

    public function getMaxBulkMonths(): int
    {
        return (int) $this->max_bulk_months;
    }

    public function validateBulkMonths(int $months): bool
    {
        if (!$this->isBulkPaymentEnabled()) {
            return false;
        }

        return $months >= 2 && $months <= $this->getMaxBulkMonths();
    }

    public function getBulkPaymentConfig(): array
    {
        return [
            'enabled' => $this->enable_bulk_payments,
            'max_months' => $this->max_bulk_months ?? 12,
            'discount_percentage' => $this->bulk_payment_discount ?? 0,
            'available_months' => range(2, $this->max_bulk_months ?? 12)
        ];
    }

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

    public function getBulkPaymentSettings(): array
    {
        return [
            'enabled' => $this->isBulkPaymentEnabled(),
            'max_months' => $this->getMaxBulkMonths(),
            'currency' => $this->getCurrencyInfo()
        ];
    }

    public function canProcessBulkPayments(): bool
    {
        return $this->isBulkPaymentEnabled() && $this->getMaxBulkMonths() > 0;
    }

    // ========== INVOICE GENERATION METHODS ==========

    public function isAutoInvoiceGenerationEnabled(): bool
    {
        return $this->auto_generate_invoices === true;
    }

    public function getNextInvoiceGenerationDate(): Carbon
    {
        return Carbon::now()->addMonth()->startOfMonth();
    }

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

    public function getDueDateForPeriod(Carbon $period): Carbon
    {
        return $period->copy()->endOfMonth()->addDays($this->grace_period_days);
    }

    // ========== PENALTY CALCULATION ==========

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

    public function isEmailConfigured(): bool
    {
        return !empty($this->system_email) &&
               filter_var($this->system_email, FILTER_VALIDATE_EMAIL) &&
               !empty($this->system_name);
    }

    public function isEmailPasswordConfigured(): bool
    {
        $mailPassword = config('mail.password');
        return !empty($mailPassword) && $mailPassword !== 'your_email_password';
    }

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

    // ========== 🆕 📢 AD SPACE METHODS ==========
    // These power the admin "AD Space" tab and the homepage ad loader.
    //
    // ⚠️ MIGRATION NOTE: the `ad_config` column must exist on
    //    `system_settings`. If it doesn't, all these methods still work
    //    (they return AD_DEFAULTS), but writes will fail. Run:
    //      php artisan migrate
    //    with the migration in database/migrations that adds:
    //      $table->json('ad_config')->nullable()->after('homepage_config');

    /**
     * Resolve the ad config (defaults merged in).
     * Never returns a partial array — the caller can trust every key.
     */
    public function getAdConfig(): array
    {
        $stored = $this->ad_config ?? [];

        if (!is_array($stored)) {
            $stored = [];
        }

        return $this->arrayReplaceRecursiveDeep(self::AD_DEFAULTS, $stored);
    }

    /**
     * Persist a new ad config.
     * Recursively merges with AD_DEFAULTS so unknown keys don't survive
     * a config reset, and missing keys don't break readers.
     */
    public function setAdConfig(array $config): bool
    {
        $this->ad_config = $this->arrayReplaceRecursiveDeep(self::AD_DEFAULTS, $config);
        return $this->save();
    }

    /**
     * Master switch — is the AD space subsystem on?
     */
    public function isAdSpaceEnabled(): bool
    {
        $config = $this->getAdConfig();
        return !empty($config['enabled']);
    }

    /**
     * Which channels are enabled. Returns only the enabled ones,
     * ordered for display (homepage first, then socials).
     */
    public function getAdChannels(): array
    {
        $config = $this->getAdConfig();
        $channels = $config['channels'] ?? [];

        $order = ['homepage', 'facebook', 'whatsapp', 'twitter', 'linkedin', 'instagram'];
        $enabled = [];

        foreach ($order as $channel) {
            if (!empty($channels[$channel])) {
                $enabled[] = $channel;
            }
        }

        return $enabled;
    }

    /**
     * All channels with their on/off state (for the admin form).
     */
    public function getAllAdChannels(): array
    {
        $config = $this->getAdConfig();
        $channels = $config['channels'] ?? self::AD_DEFAULTS['channels'];

        return [
            'homepage'  => (bool) ($channels['homepage']  ?? false),
            'facebook'  => (bool) ($channels['facebook']  ?? false),
            'whatsapp'  => (bool) ($channels['whatsapp']  ?? false),
            'twitter'   => (bool) ($channels['twitter']   ?? false),
            'linkedin'  => (bool) ($channels['linkedin']  ?? false),
            'instagram' => (bool) ($channels['instagram'] ?? false),
        ];
    }

    /**
     * Is a specific channel enabled for ads?
     */
    public function isChannelEnabledForAds(string $channel): bool
    {
        $config = $this->getAdConfig();
        return !empty($config['channels'][$channel]);
    }

    /**
     * The limits block — max active per landlord + max on homepage.
     */
    public function getAdLimits(): array
    {
        $config = $this->getAdConfig();

        return [
            'max_active_per_landlord' => (int) ($config['max_active_per_landlord'] ?? 3),
            'max_on_homepage'         => (int) ($config['max_on_homepage']         ?? 6),
        ];
    }

    /**
     * Does a new campaign require admin approval before going live?
     */
    public function shouldRequireAdApproval(): bool
    {
        $config = $this->getAdConfig();
        return !empty($config['require_admin_approval']);
    }

    /**
     * Should a landlord's "mark as rented" request auto-approve?
     * (Default: false — admin review recommended.)
     */
    public function shouldAutoApproveMarkRented(): bool
    {
        $config = $this->getAdConfig();
        return !empty($config['auto_approve_mark_rented']);
    }

    /**
     * Currency-aware price formatter for ad plans / invoices.
     *
     * Uses the system's currency config so a plan priced in GHS renders
     * as "₵50.00" and a plan priced in USD renders as "$50.00".
     */
    public function formatAdPrice(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? $this->currency_code ?? 'GHS';

        // If the plan's currency differs from the system default,
        // still render a sensible symbol.
        if ($currency !== ($this->currency_code ?? 'GHS')) {
            $symbol = match (strtoupper($currency)) {
                'GHS' => '₵',
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'NGN' => '₦',
                default => $currency . ' ',
            };

            return $symbol . number_format($amount, 2);
        }

        return $this->formatAmount($amount);
    }

    /**
     * Full ad space status snapshot — used by the admin card + Blade.
     */
    public function getAdSpaceStatus(): array
    {
        $config = $this->getAdConfig();
        $limits = $this->getAdLimits();

        return [
            'enabled'                  => $this->isAdSpaceEnabled(),
            'max_active_per_landlord'  => $limits['max_active_per_landlord'],
            'max_on_homepage'          => $limits['max_on_homepage'],
            'require_admin_approval'   => $this->shouldRequireAdApproval(),
            'auto_approve_mark_rented' => $this->shouldAutoApproveMarkRented(),

            // Channel lists
            'enabled_channels'         => $this->getAdChannels(),
            'all_channels'             => $this->getAllAdChannels(),
            'enabled_channel_count'    => count($this->getAdChannels()),

            // Display options
            'show_sponsored_badge'     => !empty($config['show_sponsored_badge']),
            'show_view_count'          => !empty($config['show_view_count']),
            'allow_landlord_proof_upload' => !empty($config['allow_landlord_proof_upload']),

            // Ready state
            'is_configured'            => $this->isAdSpaceEnabled()
                                          && $this->isChannelEnabledForAds('homepage'),
            'has_custom_config'        => !empty($this->ad_config)
                                          && is_array($this->ad_config)
                                          && count($this->ad_config) > 0,
        ];
    }

    /**
     * Return the pricing-plan presets the admin can one-click create.
     * Values are already formatted for direct insertion via
     * AdPricingPlan::create(...).
     */
    public static function getAdPlanPresets(): array
    {
        return self::AD_PLAN_PRESETS;
    }

    /**
     * Convenience: is the ad space ready to accept new campaigns?
     * (enabled + at least homepage channel on)
     */
    public function canAcceptNewAdCampaigns(): bool
    {
        return $this->isAdSpaceEnabled()
            && $this->isChannelEnabledForAds('homepage');
    }

    // ========== CONFIGURATION STATUS METHODS ==========

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
            'homepage' => $this->hasCustomHomepage(),
            'login_page' => $this->hasCustomLoginPage(),
            'login_theme' => $this->hasCustomLoginTheme(),
            'ad_space' => $this->getAdSpaceStatus()['is_configured'],
        ];
    }

    public function getPaymentConfigurationStatus(): array
    {
        return [
            'methods_available' => $this->hasEnabledPaymentMethods(),
            'enabled_methods_count' => $this->getEnabledPaymentProvidersCount(),
            'enabled_methods' => $this->getEnabledGatewayNames(),
            'bulk_payments_enabled' => $this->isBulkPaymentEnabled()
        ];
    }

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
                'homepage_configured' => $this->hasCustomHomepage(),
                'login_page_configured' => $this->hasCustomLoginPage(),
                'login_theme_configured' => $this->hasCustomLoginTheme(),
                'ad_space_configured' => $this->getAdSpaceStatus()['is_configured'],
            ],
            'payment_configuration' => $paymentStatus,
            'whatsapp_configuration' => $whatsappStatus,
            'registration_control' => $registrationStatus,
            'tenant_invoicing' => $tenantInvoiceStatus,
            'homepage_config' => $this->getHomepageStatus(),
            'login_page_config' => $this->getLoginPageStatus(),
            'ad_space_config' => $this->getAdSpaceStatus(),
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
            'identifier' => $this->getSystemIdentifier(),
            'homepage_status' => $this->getHomepageStatus(),
            'login_page_status' => $this->getLoginPageStatus(),
            'ad_space_status' => $this->getAdSpaceStatus(),
        ];
    }

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

    public function getSystemOverview(): array
    {
        return [
            'identification' => $this->getSystemIdentifier(),
            'registration' => $this->getRegistrationStatus(),
            'payment' => $this->getPaymentConfiguration(),
            'email' => $this->getEmailConfigurationStatusFull(),
            'whatsapp' => $this->getWhatsAppConfigurationStatus(),
            'tenant_invoicing' => $this->getTenantInvoiceConfig(),
            'homepage' => $this->getHomepageStatus(),
            'login_page' => $this->getLoginPageStatus(),
            'ad_space' => $this->getAdSpaceStatus(),
            'configuration_status' => $this->getSystemConfigurationStatusFull(),
            'sms_sender_id' => $this->getSmsSenderIdStatus(),
            'last_updated' => [
                'timestamp' => $this->updated_at,
                'by' => $this->updater?->name ?? 'System'
            ]
        ];
    }

    /**
     * Summary of the current login-page config for admin cards.
     *
     * 🆕 Now exposes the theme block, theme status, and resolved
     *    CSS variables so the Blade view doesn't have to dig
     *    into nested arrays itself.
     */
    public function getLoginPageStatus(): array
    {
        $config = $this->getLoginPageConfig();

        return [
            'has_custom'        => $this->hasCustomLoginPage(),
            'background_url'    => $this->getLoginPageBackgroundUrl(),
            'has_uploaded_bg'   => !empty($config['background']),
            'background_url_external' => $config['background_url'] ?? null,
            'overlay_opacity'   => $config['overlay_opacity'] ?? 0.85,
            'default_theme'     => $config['default_theme'] ?? 'dark',
            'welcome_title'     => $config['welcome_title'] ?? null,
            'welcome_subtitle'  => $config['welcome_subtitle'] ?? null,
            'show_welcome_panel'=> (bool) ($config['show_welcome_panel'] ?? true),
            'show_social_icons' => (bool) ($config['show_social_icons'] ?? true),
            'show_cookie_banner'=> (bool) ($config['show_cookie_banner'] ?? true),
            'features_count'    => count($config['welcome_features'] ?? []),
            'socials_count'     => count(array_filter(
                $config['footer_socials'] ?? [],
                fn($s) => !empty($s['url'])
            )),

            // 🆕 Theme summary + CSS variables ready for the Blade
            'theme'             => $config['theme'] ?? self::HOMEPAGE_DEFAULTS['login']['theme'],
            'theme_status'      => $this->getLoginPageThemeStatus(),
            'css_variables'     => $this->getLoginPageCssVariables(),
        ];
    }

    // ========== UTILITY METHODS ==========

    public function updateSettings(array $data, User $updater): bool
    {
        $data['updated_by'] = $updater->id;

        return $this->update($data);
    }

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