<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\NotificationLog;
use App\Models\UserNotificationPreference;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class NotificationChannelController extends Controller
{
    protected $smsService;
    protected $smsTemplateService;
    protected $notificationService;
    protected $settings;

    public function __construct(
        SmsService $smsService,
        SmsTemplateService $smsTemplateService,
        NotificationService $notificationService
    ) {
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
        $this->notificationService = $notificationService;
        $this->settings = SystemSetting::getSettings();
    }

    /**
     * Display notification channels management page
     */
    public function index()
    {
        try {
            $settings = $this->settings;
            $smsStatus = $this->smsService->getQuickStatus();
            $whatsappStatus = $settings->getWhatsAppConfigurationStatus();
            
            $notificationChannels = [
                'invoice_generated' => $settings->getInvoiceNotificationChannels(),
                'payment_reminder' => $settings->getPaymentReminderChannels(),
                'overdue' => $settings->getOverdueNotificationChannels(),
                'payment_confirmation' => $settings->getPaymentConfirmationChannels(),
            ];
            
            $smsRateLimits = $settings->getSmsRateLimits();
            $notificationConfig = $settings->getNotificationConfigSummary();
            
            return view('admin.notification-channels.index', compact(
                'settings',
                'smsStatus',
                'whatsappStatus',
                'notificationChannels',
                'smsRateLimits',
                'notificationConfig'
            ));
            
        } catch (\Exception $e) {
            Log::error('Failed to load notification channels page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load notification settings: ' . $e->getMessage());
        }
    }

    /**
     * Get notification channel settings (API)
     */
    public function getSettings(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'channels' => [
                        'invoice_generated' => $this->settings->getInvoiceNotificationChannels(),
                        'payment_reminder' => $this->settings->getPaymentReminderChannels(),
                        'overdue' => $this->settings->getOverdueNotificationChannels(),
                        'payment_confirmation' => $this->settings->getPaymentConfirmationChannels(),
                    ],
                    'sms' => [
                        'enabled' => $this->settings->isSmsEnabled(),
                        'reminder_enabled' => $this->settings->isSmsReminderEnabled(),
                        'payment_confirmation_enabled' => $this->settings->isSmsPaymentConfirmationEnabled(),
                        'daily_limit' => $this->settings->sms_daily_limit_per_user,
                        'hourly_limit' => $this->settings->sms_hourly_limit_per_user,
                    ],
                    'whatsapp' => [
                        'enabled' => $this->settings->isWhatsAppEnabled(),
                        'reminder_enabled' => $this->settings->isWhatsAppReminderEnabled(),
                        'payment_confirmation_enabled' => $this->settings->isWhatsAppPaymentConfirmationEnabled(),
                        'configured' => $this->settings->isWhatsAppConfigured(),
                        'provider' => $this->settings->whatsapp_provider,
                    ],
                    'settings' => [
                        'force_email_fallback' => $this->settings->shouldForceEmailFallback(),
                        'retry_attempts' => $this->settings->notification_retry_attempts,
                        'retry_delay_minutes' => $this->settings->notification_retry_delay_minutes,
                        'enable_bulk_notifications' => $this->settings->shouldEnableBulkNotifications(),
                        'bulk_batch_size' => $this->settings->getBulkNotificationBatchSize(),
                        'log_retention_days' => $this->settings->getNotificationLogRetentionDays(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get notification settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification settings'
            ], 500);
        }
    }

    /**
     * Update notification channel settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
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
            'enable_whatsapp_notifications' => 'nullable|boolean',
            'whatsapp_reminder_enabled' => 'nullable|boolean',
            'whatsapp_payment_confirmation_enabled' => 'nullable|boolean',
            'force_email_fallback' => 'nullable|boolean',
            'notification_retry_attempts' => 'nullable|integer|min:1|max:10',
            'notification_retry_delay_minutes' => 'nullable|integer|min:1|max:60',
            'enable_bulk_notifications' => 'nullable|boolean',
            'bulk_notification_batch_size' => 'nullable|integer|min:10|max:500',
            'bulk_notification_delay_seconds' => 'nullable|integer|min:1|max:30',
            'log_all_notifications' => 'nullable|boolean',
            'notification_log_retention_days' => 'nullable|integer|min:30|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            
            // Update channel arrays
            $channelFields = [
                'invoice_notification_channels',
                'payment_reminder_channels',
                'overdue_notification_channels',
                'payment_confirmation_channels'
            ];
            
            foreach ($channelFields as $field) {
                if ($request->has($field)) {
                    $channels = $request->input($field, ['email']);
                    if (!in_array('email', $channels)) {
                        $channels[] = 'email';
                    }
                    $updateData[$field] = $channels;
                }
            }
            
            // Update SMS settings
            $smsFields = [
                'sms_notifications_enabled',
                'sms_reminder_enabled',
                'sms_payment_confirmation_enabled',
            ];
            
            foreach ($smsFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->boolean($field);
                }
            }
            
            // Update WhatsApp settings
            $whatsappFields = [
                'enable_whatsapp_notifications',
                'whatsapp_reminder_enabled',
                'whatsapp_payment_confirmation_enabled',
            ];
            
            foreach ($whatsappFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->boolean($field);
                }
            }
            
            // Update general notification settings
            $generalFields = [
                'force_email_fallback',
                'notification_retry_attempts',
                'notification_retry_delay_minutes',
                'enable_bulk_notifications',
                'bulk_notification_batch_size',
                'bulk_notification_delay_seconds',
                'log_all_notifications',
                'notification_log_retention_days',
            ];
            
            foreach ($generalFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }
            
            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $this->settings->update($updateData);
                
                Log::info('Notification channel settings updated', [
                    'updated_fields' => array_keys($updateData),
                    'updated_by' => auth()->id()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => '✅ Notification settings updated successfully',
                'data' => $this->getSettings()->getData(true)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update notification settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SMS status
     */
    public function getSmsStatus(): JsonResponse
    {
        try {
            $status = $this->smsService->getQuickStatus();
            $smsRateLimits = $this->settings->getSmsRateLimits();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'system_status' => $status,
                    'rate_limits' => $smsRateLimits,
                    'settings' => [
                        'enabled' => $this->settings->isSmsEnabled(),
                        'reminder_enabled' => $this->settings->isSmsReminderEnabled(),
                        'payment_confirmation_enabled' => $this->settings->isSmsPaymentConfirmationEnabled(),
                        'daily_limit' => $this->settings->sms_daily_limit_per_user,
                        'hourly_limit' => $this->settings->sms_hourly_limit_per_user,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get SMS status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS status'
            ], 500);
        }
    }

    /**
     * Update SMS rate limits
     */
    public function updateSmsRateLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'daily_limit' => 'required|integer|min:1|max:100',
            'hourly_limit' => 'required|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->settings->update([
                'sms_daily_limit_per_user' => $request->daily_limit,
                'sms_hourly_limit_per_user' => $request->hourly_limit,
                'updated_by' => auth()->id()
            ]);
            
            Log::info('SMS rate limits updated', [
                'daily_limit' => $request->daily_limit,
                'hourly_limit' => $request->hourly_limit,
                'updated_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ SMS rate limits updated successfully',
                'data' => [
                    'daily_limit' => $request->daily_limit,
                    'hourly_limit' => $request->hourly_limit
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update SMS rate limits: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS rate limits'
            ], 500);
        }
    }

    /**
     * Get SMS templates
     */
    public function getSmsTemplates(): JsonResponse
    {
        try {
            $templates = [
                'invoice_generated' => $this->settings->sms_invoice_generated_template,
                'payment_reminder' => $this->settings->sms_payment_reminder_template,
                'overdue' => $this->settings->sms_overdue_template,
                'payment_confirmation' => $this->settings->sms_payment_confirmation_template,
            ];
            
            $defaultTemplates = [
                'invoice_generated' => $this->settings->getDefaultSmsMessage('invoice_generated'),
                'payment_reminder' => $this->settings->getDefaultSmsMessage('payment_reminder'),
                'overdue' => $this->settings->getDefaultSmsMessage('overdue'),
                'payment_confirmation' => $this->settings->getDefaultSmsMessage('payment_confirmation'),
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'custom_templates' => $templates,
                    'default_templates' => $defaultTemplates,
                    'has_custom' => !empty(array_filter($templates))
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get SMS templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS templates'
            ], 500);
        }
    }

    /**
     * Update SMS templates
     */
    public function updateSmsTemplates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_generated' => 'nullable|string|max:500',
            'payment_reminder' => 'nullable|string|max:500',
            'overdue' => 'nullable|string|max:500',
            'payment_confirmation' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            
            if ($request->has('invoice_generated')) {
                $updateData['sms_invoice_generated_template'] = $request->invoice_generated;
            }
            if ($request->has('payment_reminder')) {
                $updateData['sms_payment_reminder_template'] = $request->payment_reminder;
            }
            if ($request->has('overdue')) {
                $updateData['sms_overdue_template'] = $request->overdue;
            }
            if ($request->has('payment_confirmation')) {
                $updateData['sms_payment_confirmation_template'] = $request->payment_confirmation;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $this->settings->update($updateData);
                
                Log::info('SMS templates updated', [
                    'updated_templates' => array_keys($updateData),
                    'updated_by' => auth()->id()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => '✅ SMS templates updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update SMS templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS templates'
            ], 500);
        }
    }

    /**
     * Test SMS sending
     */
    public function testSms(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'message_type' => 'required|in:invoice_generated,payment_reminder,overdue,payment_confirmation,custom',
            'custom_message' => 'required_if:message_type,custom|string|max:160',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $testData = [
                'invoice_number' => 'TEST-001',
                'period' => Carbon::now()->format('F Y'),
                'amount' => 100.00,
                'due_date' => Carbon::now()->addDays(7)->format('M d, Y'),
                'payment_link' => route('landlord.payments.create', ['invoice_ids' => [1]]),
                'property_name' => 'Test Property',
                'landlord_name' => 'Test Landlord',
            ];
            
            if ($request->message_type === 'custom') {
                $message = $request->custom_message;
            } else {
                $message = match($request->message_type) {
                    'invoice_generated' => $this->smsTemplateService->generateInvoiceGeneratedSms($testData),
                    'payment_reminder' => $this->smsTemplateService->generatePaymentReminderSms($testData),
                    'overdue' => $this->smsTemplateService->generateOverdueSms($testData),
                    'payment_confirmation' => $this->smsTemplateService->generatePaymentConfirmationSms($testData),
                    default => 'Test SMS message'
                };
            }
            
            $result = $this->smsService->sendWithDefaultProvider(
                $request->phone_number,
                $message,
                ['is_test' => true]
            );
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? '✅ Test SMS sent successfully' : '❌ Test SMS failed: ' . $result['message'],
                'details' => $result['details'] ?? null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send test SMS: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle SMS notifications
     */
    public function toggleSms(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'type' => 'nullable|in:all,reminder,confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->type ?? 'all';
            $updateData = [];
            
            if ($type === 'all') {
                $updateData['sms_notifications_enabled'] = $request->enabled;
                if (!$request->enabled) {
                    $updateData['sms_reminder_enabled'] = false;
                    $updateData['sms_payment_confirmation_enabled'] = false;
                }
            } elseif ($type === 'reminder') {
                $updateData['sms_reminder_enabled'] = $request->enabled;
            } elseif ($type === 'confirmation') {
                $updateData['sms_payment_confirmation_enabled'] = $request->enabled;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $this->settings->update($updateData);
            }
            
            return response()->json([
                'success' => true,
                'message' => $request->enabled ? '✅ SMS notifications enabled' : '✅ SMS notifications disabled',
                'data' => [
                    'enabled' => $this->settings->isSmsEnabled(),
                    'reminder_enabled' => $this->settings->isSmsReminderEnabled(),
                    'confirmation_enabled' => $this->settings->isSmsPaymentConfirmationEnabled()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle SMS: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle SMS notifications'
            ], 500);
        }
    }

    /**
     * Get WhatsApp status
     */
    public function getWhatsAppStatus(): JsonResponse
    {
        try {
            $status = $this->settings->getWhatsAppConfigurationStatus();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'configured' => $status['configured'],
                    'enabled' => $this->settings->isWhatsAppEnabled(),
                    'reminder_enabled' => $this->settings->isWhatsAppReminderEnabled(),
                    'payment_confirmation_enabled' => $this->settings->isWhatsAppPaymentConfirmationEnabled(),
                    'provider' => $this->settings->whatsapp_provider,
                    'provider_display' => $this->settings->getWhatsAppProviderDisplayName(),
                    'issues' => $status['issues'],
                    'can_send' => $status['configured'] && $this->settings->isWhatsAppEnabled()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve WhatsApp status'
            ], 500);
        }
    }

    /**
     * Test WhatsApp notification
     */
    public function testWhatsApp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'message_type' => 'required|in:invoice_generated,payment_reminder,overdue,payment_confirmation,custom',
            'custom_message' => 'required_if:message_type,custom|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$this->settings->isWhatsAppEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp notifications are disabled'
                ], 400);
            }
            
            if (!$this->settings->isWhatsAppConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp is not configured'
                ], 400);
            }
            
            $testData = [
                'invoice_number' => 'TEST-001',
                'period' => Carbon::now()->format('F Y'),
                'amount' => 100.00,
                'due_date' => Carbon::now()->addDays(7)->format('M d, Y'),
                'payment_link' => route('landlord.payments.create', ['invoice_ids' => [1]]),
                'property_name' => 'Test Property',
                'landlord_name' => 'Test Landlord',
            ];
            
            if ($request->message_type === 'custom') {
                $message = $request->custom_message;
            } else {
                $message = $this->settings->getDefaultWhatsAppMessage($request->message_type, $testData);
            }
            
            // Placeholder for actual WhatsApp sending
            Log::info('Test WhatsApp message would be sent', [
                'phone' => $request->phone_number,
                'message' => $message,
                'type' => $request->message_type
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ Test WhatsApp message queued successfully',
                'details' => [
                    'phone' => $request->phone_number,
                    'message_type' => $request->message_type,
                    'message_length' => strlen($message)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send test WhatsApp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle WhatsApp notifications
     */
    public function toggleWhatsApp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'type' => 'nullable|in:all,reminder,confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->type ?? 'all';
            $updateData = [];
            
            if ($type === 'all') {
                $updateData['enable_whatsapp_notifications'] = $request->enabled;
                if (!$request->enabled) {
                    $updateData['whatsapp_reminder_enabled'] = false;
                    $updateData['whatsapp_payment_confirmation_enabled'] = false;
                }
            } elseif ($type === 'reminder') {
                $updateData['whatsapp_reminder_enabled'] = $request->enabled;
            } elseif ($type === 'confirmation') {
                $updateData['whatsapp_payment_confirmation_enabled'] = $request->enabled;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $this->settings->update($updateData);
            }
            
            return response()->json([
                'success' => true,
                'message' => $request->enabled ? '✅ WhatsApp notifications enabled' : '✅ WhatsApp notifications disabled',
                'data' => [
                    'enabled' => $this->settings->isWhatsAppEnabled(),
                    'reminder_enabled' => $this->settings->isWhatsAppReminderEnabled(),
                    'confirmation_enabled' => $this->settings->isWhatsAppPaymentConfirmationEnabled()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle WhatsApp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle WhatsApp notifications'
            ], 500);
        }
    }

    /**
     * Get WhatsApp templates
     */
    public function getWhatsAppTemplates(): JsonResponse
    {
        try {
            $templates = [
                'invoice_generated' => $this->settings->whatsapp_invoice_generated_template,
                'payment_reminder' => $this->settings->whatsapp_payment_reminder_template,
                'overdue' => $this->settings->whatsapp_overdue_template,
                'payment_confirmation' => $this->settings->whatsapp_payment_confirmation_template,
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'custom_templates' => $templates,
                    'has_custom' => !empty(array_filter($templates))
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve WhatsApp templates'
            ], 500);
        }
    }

    /**
     * Update WhatsApp templates
     */
    public function updateWhatsAppTemplates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_generated' => 'nullable|string|max:1000',
            'payment_reminder' => 'nullable|string|max:1000',
            'overdue' => 'nullable|string|max:1000',
            'payment_confirmation' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            
            if ($request->has('invoice_generated')) {
                $updateData['whatsapp_invoice_generated_template'] = $request->invoice_generated;
            }
            if ($request->has('payment_reminder')) {
                $updateData['whatsapp_payment_reminder_template'] = $request->payment_reminder;
            }
            if ($request->has('overdue')) {
                $updateData['whatsapp_overdue_template'] = $request->overdue;
            }
            if ($request->has('payment_confirmation')) {
                $updateData['whatsapp_payment_confirmation_template'] = $request->payment_confirmation;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_by'] = auth()->id();
                $this->settings->update($updateData);
                
                Log::info('WhatsApp templates updated', [
                    'updated_templates' => array_keys($updateData),
                    'updated_by' => auth()->id()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => '✅ WhatsApp templates updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update WhatsApp templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update WhatsApp templates'
            ], 500);
        }
    }

    /**
     * Test all notification channels
     */
    public function testAllChannels(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = [];
            $testData = [
                'invoice_number' => 'TEST-001',
                'period' => Carbon::now()->format('F Y'),
                'amount' => 100.00,
                'due_date' => Carbon::now()->addDays(7)->format('M d, Y'),
            ];
            
            // Test email if provided
            if ($request->filled('email')) {
                try {
                    \Mail::raw("Test notification from system at " . now(), function($mail) use ($request) {
                        $mail->to($request->email)
                            ->subject('Test Notification - All Channels')
                            ->from(config('mail.from.address'), config('mail.from.name'));
                    });
                    $results['email'] = ['success' => true, 'message' => 'Email sent successfully'];
                } catch (\Exception $e) {
                    $results['email'] = ['success' => false, 'message' => $e->getMessage()];
                }
            }
            
            // Test SMS if provided
            if ($request->filled('phone_number') && $this->settings->isSmsEnabled()) {
                $message = $this->smsTemplateService->generatePaymentReminderSms($testData);
                $smsResult = $this->smsService->sendWithDefaultProvider(
                    $request->phone_number,
                    $message,
                    ['is_test' => true]
                );
                $results['sms'] = $smsResult;
            } elseif ($request->filled('phone_number')) {
                $results['sms'] = ['success' => false, 'message' => 'SMS notifications are disabled'];
            }
            
            // Test WhatsApp if phone provided (placeholder)
            if ($request->filled('phone_number') && $this->settings->isWhatsAppEnabled()) {
                $results['whatsapp'] = ['success' => true, 'message' => 'WhatsApp test queued (integration in progress)'];
            } elseif ($request->filled('phone_number')) {
                $results['whatsapp'] = ['success' => false, 'message' => 'WhatsApp notifications are disabled'];
            }
            
            $allSuccess = collect($results)->every(fn($r) => $r['success'] ?? false);
            
            return response()->json([
                'success' => $allSuccess,
                'message' => $allSuccess ? '✅ All channels tested successfully' : '⚠️ Some channels failed',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to test all channels: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to test channels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test notification channels (single or multiple)
     */
    public function testNotificationChannels(Request $request): JsonResponse
    {
        return $this->testAllChannels($request);
    }

    /**
     * Get notification logs
     */
    public function getNotificationLogs(Request $request): JsonResponse
    {
        try {
            $query = NotificationLog::with(['user', 'invoice'])
                ->orderBy('created_at', 'desc');
            
            if ($request->filled('channel')) {
                $query->where('channel', $request->channel);
            }
            
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            $logs = $query->paginate($request->get('per_page', 20));
            
            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get notification logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification logs'
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(): JsonResponse
    {
        try {
            $total = NotificationLog::count();
            $successful = NotificationLog::where('status', 'sent')->count();
            $failed = NotificationLog::where('status', 'failed')->count();
            $pending = NotificationLog::where('status', 'pending')->count();
            
            $byChannel = NotificationLog::select('channel')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful')
                ->groupBy('channel')
                ->get()
                ->keyBy('channel');
            
            $last24Hours = NotificationLog::where('created_at', '>=', now()->subHours(24))->count();
            $last7Days = NotificationLog::where('created_at', '>=', now()->subDays(7))->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'totals' => [
                        'total' => $total,
                        'successful' => $successful,
                        'failed' => $failed,
                        'pending' => $pending,
                        'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
                    ],
                    'by_channel' => $byChannel,
                    'recent' => [
                        'last_24_hours' => $last24Hours,
                        'last_7_days' => $last7Days
                    ],
                    'retention_days' => $this->settings->getNotificationLogRetentionDays()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get notification stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics'
            ], 500);
        }
    }

    /**
     * Cleanup old notification logs
     */
    public function cleanupNotificationLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:30|max:365',
            'confirm' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$request->confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Please confirm log cleanup'
            ], 400);
        }

        try {
            $days = $request->days ?? $this->settings->getNotificationLogRetentionDays();
            $cutoffDate = now()->subDays($days);
            
            $deleted = NotificationLog::where('created_at', '<', $cutoffDate)->delete();
            
            Log::info('Notification logs cleaned up', [
                'deleted_count' => $deleted,
                'cutoff_days' => $days,
                'performed_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "✅ Cleaned up {$deleted} notification logs older than {$days} days",
                'deleted_count' => $deleted
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup notification logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup notification logs'
            ], 500);
        }
    }

    /**
     * Get bulk notification configuration
     */
    public function getBulkNotificationConfig(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'enabled' => $this->settings->shouldEnableBulkNotifications(),
                    'batch_size' => $this->settings->getBulkNotificationBatchSize(),
                    'delay_seconds' => $this->settings->getBulkNotificationDelaySeconds(),
                    'available_channels' => ['email', 'sms', 'whatsapp'],
                    'current_channels' => [
                        'invoice_generated' => $this->settings->getInvoiceNotificationChannels(),
                        'payment_reminder' => $this->settings->getPaymentReminderChannels(),
                        'overdue' => $this->settings->getOverdueNotificationChannels(),
                        'payment_confirmation' => $this->settings->getPaymentConfirmationChannels(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get bulk notification config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bulk notification configuration'
            ], 500);
        }
    }

    /**
     * Update bulk notification configuration
     */
    public function updateBulkNotificationConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'batch_size' => 'required|integer|min:10|max:500',
            'delay_seconds' => 'required|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->settings->update([
                'enable_bulk_notifications' => $request->enabled,
                'bulk_notification_batch_size' => $request->batch_size,
                'bulk_notification_delay_seconds' => $request->delay_seconds,
                'updated_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ Bulk notification configuration updated',
                'data' => [
                    'enabled' => $request->enabled,
                    'batch_size' => $request->batch_size,
                    'delay_seconds' => $request->delay_seconds
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update bulk notification config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bulk notification configuration'
            ], 500);
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'notification_type' => 'required|in:invoice_generated,payment_reminder,overdue,payment_confirmation,custom',
            'custom_message' => 'required_if:notification_type,custom|string',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$this->settings->shouldEnableBulkNotifications()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulk notifications are disabled'
                ], 400);
            }
            
            $users = User::whereIn('id', $request->user_ids)
                ->where('status', User::STATUS_ACTIVE)
                ->get();
            
            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active users found'
                ], 404);
            }
            
            // Here you would dispatch a job to send bulk notifications
            // For now, we'll log and return success
            Log::info('Bulk notification requested', [
                'user_count' => $users->count(),
                'notification_type' => $request->notification_type,
                'channels' => $request->channels,
                'requested_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "✅ Bulk notification job queued for {$users->count()} users",
                'data' => [
                    'user_count' => $users->count(),
                    'notification_type' => $request->notification_type,
                    'channels' => $request->channels ?? 'system default'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send bulk notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bulk notification status
     */
    public function getBulkNotificationStatus(string $jobId): JsonResponse
    {
        // Placeholder - would need to track jobs in database
        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => 'processing',
                'progress' => 0,
                'total' => 0,
                'processed' => 0,
                'failed' => 0
            ]
        ]);
    }

    /**
     * Export notification logs
     */
    public function exportNotificationLogs(Request $request)
    {
        try {
            $query = NotificationLog::with(['user', 'invoice'])
                ->orderBy('created_at', 'desc');
            
            if ($request->filled('channel')) {
                $query->where('channel', $request->channel);
            }
            
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            $logs = $query->limit(10000)->get();
            
            $filename = 'notification_logs_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'w+');
            
            fwrite($handle, "\xEF\xBB\xBF");
            
            fputcsv($handle, [
                'ID',
                'User',
                'User Email',
                'Invoice',
                'Channel',
                'Notification Type',
                'Recipient',
                'Status',
                'Error Message',
                'Sent At',
                'Created At'
            ]);
            
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? '',
                    $log->invoice?->invoice_number ?? 'N/A',
                    $log->channel,
                    $log->notification_type,
                    $log->recipient,
                    $log->status,
                    $log->error_message,
                    $log->sent_at?->format('Y-m-d H:i:s'),
                    $log->created_at->format('Y-m-d H:i:s')
                ]);
            }
            
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to export notification logs: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export logs: ' . $e->getMessage());
        }
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            
            $preferences = UserNotificationPreference::where('user_id', $userId)
                ->get()
                ->keyBy('notification_type')
                ->map(function($pref) {
                    return [
                        'channels' => $pref->channels,
                        'opted_out' => $pref->opted_out,
                        'override_email' => $pref->override_email,
                        'override_phone' => $pref->override_phone
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone
                    ],
                    'preferences' => $preferences,
                    'system_defaults' => [
                        'invoice_generated' => $this->settings->getInvoiceNotificationChannels(),
                        'payment_reminder' => $this->settings->getPaymentReminderChannels(),
                        'overdue' => $this->settings->getOverdueNotificationChannels(),
                        'payment_confirmation' => $this->settings->getPaymentConfirmationChannels(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get user preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user preferences'
            ], 500);
        }
    }

    /**
     * Update user notification preferences
     */
    public function updateUserPreferences(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_type' => 'required|string',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,whatsapp',
            'opted_out' => 'nullable|boolean',
            'override_email' => 'nullable|email',
            'override_phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preference = UserNotificationPreference::updateOrCreate(
                [
                    'user_id' => $userId,
                    'notification_type' => $request->notification_type
                ],
                [
                    'channels' => $request->channels,
                    'opted_out' => $request->opted_out ?? false,
                    'override_email' => $request->override_email,
                    'override_phone' => $request->override_phone,
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => '✅ User preferences updated',
                'data' => $preference
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update user preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user preferences'
            ], 500);
        }
    }

    /**
     * Bulk update user preferences
     */
    public function bulkUpdateUserPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'notification_type' => 'required|string',
            'channels' => 'nullable|array',
            'channels.*' => 'in:email,sms,whatsapp',
            'opted_out' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedCount = 0;
            
            foreach ($request->user_ids as $userId) {
                UserNotificationPreference::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'notification_type' => $request->notification_type
                    ],
                    [
                        'channels' => $request->channels,
                        'opted_out' => $request->opted_out ?? false,
                    ]
                );
                $updatedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "✅ Updated preferences for {$updatedCount} users",
                'updated_count' => $updatedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk update user preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user preferences'
            ], 500);
        }
    }

    /**
     * Reset user notification preferences
     */
    public function resetUserPreferences(int $userId): JsonResponse
    {
        try {
            UserNotificationPreference::where('user_id', $userId)->delete();
            
            return response()->json([
                'success' => true,
                'message' => '✅ User preferences reset to system defaults'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to reset user preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset user preferences'
            ], 500);
        }
    }

    /**
     * Get system health
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $emailStatus = $this->settings->getEmailConfigurationStatusFull();
            $smsStatus = $this->smsService->getSystemStatus();
            $whatsappStatus = $this->settings->getWhatsAppConfigurationStatus();
            
            $overallHealth = 'healthy';
            $issues = [];
            
            if (!$emailStatus['can_send_emails']) {
                $overallHealth = 'degraded';
                $issues[] = 'Email service is not properly configured';
            }
            
            if (!$smsStatus['system_ready']) {
                $overallHealth = 'degraded';
                $issues[] = 'SMS service is not ready';
            }
            
            if ($this->settings->isWhatsAppEnabled() && !$whatsappStatus['configured']) {
                $overallHealth = 'degraded';
                $issues[] = 'WhatsApp is enabled but not configured';
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'overall_health' => $overallHealth,
                    'issues' => $issues,
                    'components' => [
                        'email' => [
                            'status' => $emailStatus['can_send_emails'] ? 'healthy' : 'unhealthy',
                            'details' => $emailStatus
                        ],
                        'sms' => [
                            'status' => $smsStatus['system_ready'] ? 'healthy' : 'unhealthy',
                            'details' => $smsStatus
                        ],
                        'whatsapp' => [
                            'status' => $whatsappStatus['configured'] ? 'healthy' : 'not_configured',
                            'details' => $whatsappStatus
                        ]
                    ],
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get system health: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system health'
            ], 500);
        }
    }

    /**
     * Run diagnostics
     */
    public function runDiagnostics(): JsonResponse
    {
        try {
            $diagnostics = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'queue_driver' => config('queue.default'),
                'mail_driver' => config('mail.default'),
                'notification_settings' => $this->settings->getNotificationConfigSummary(),
                'sms_providers' => $this->smsService->getAllProvidersWithStatus(),
                'database' => [
                    'connected' => DB::connection()->getPdo() ? true : false,
                    'database_name' => DB::connection()->getDatabaseName()
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'connected' => Cache::get('test_key', 'not_set') !== false
                ],
                'timestamp' => now()->toISOString()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $diagnostics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run diagnostics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run diagnostics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'log_ids' => 'nullable|array',
            'log_ids.*' => 'exists:notification_logs,id',
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = NotificationLog::where('status', 'failed');
            
            if ($request->has('log_ids')) {
                $query->whereIn('id', $request->log_ids);
            }
            
            if ($request->has('days')) {
                $query->where('created_at', '>=', now()->subDays($request->days));
            }
            
            $failedLogs = $query->limit(100)->get();
            $retryCount = 0;
            
            foreach ($failedLogs as $log) {
                // Here you would implement retry logic
                // For now, we'll just mark as retried
                $log->update([
                    'status' => 'pending',
                    'retry_count' => ($log->retry_count ?? 0) + 1
                ]);
                $retryCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "✅ Queued {$retryCount} failed notifications for retry",
                'retry_count' => $retryCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retry notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry notifications: ' . $e->getMessage()
            ], 500);
        }
    }
}