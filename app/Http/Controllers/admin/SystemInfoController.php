<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Property;
use App\Models\Invoice;
use App\Models\User;
use App\Services\EnvironmentConfigService;
use App\Services\WhatsAppService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemInfoController extends Controller
{
    protected $environmentService;
    protected $whatsappService;
    protected $smsService;

    public function __construct(
        EnvironmentConfigService $environmentService,
        WhatsAppService $whatsappService,
        SmsService $smsService
    ) {
        $this->environmentService = $environmentService;
        $this->whatsappService = $whatsappService;
        $this->smsService = $smsService;
    }

    /**
     * Get system information including logo
     */
    public function getSystemInfo()
    {
        try {
            $settings = SystemSetting::getSettings();
            $emailConfiguration = $this->environmentService->getMailConfiguration();
            $whatsappConfiguration = $this->environmentService->getWhatsAppConfiguration();
            
            return response()->json([
                'success' => true,
                'system_info' => $settings->getSystemInfo(),
                'configuration_status' => $settings->getSystemConfigurationStatus(),
                'email_configuration' => $emailConfiguration,
                'whatsapp_configuration' => $whatsappConfiguration
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving system information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate dues for a property (API endpoint)
     */
    public function calculateDuesApi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'period' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $property = Property::find($request->property_id);
            $settings = SystemSetting::getSettings();
            
            $duesAmount = $settings->calculateDues($property);
            $dueDate = Carbon::parse($request->period)->endOfMonth();

            return response()->json([
                'success' => true,
                'dues_amount' => $duesAmount,
                'formatted_amount' => $settings->formatAmount($duesAmount),
                'due_date' => $dueDate->format('Y-m-d'),
                'currency' => $settings->getCurrencyInfo(),
                'system_name' => $settings->system_name,
                'system_short_name' => $settings->system_short_name,
                'system_logo' => $settings->getLogoUrl()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating dues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if bulk payments are enabled
     */
    public function checkBulkPaymentEnabled()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            return response()->json([
                'success' => true,
                'enabled' => $settings->enable_bulk_payments,
                'max_months' => $settings->max_bulk_months,
                'discount' => $settings->bulk_payment_discount,
                'discount_enabled' => ($settings->bulk_payment_discount ?? 0) > 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking bulk payment status'
            ], 500);
        }
    }

    /**
     * Get invoice settings (API)
     */
    public function getInvoiceSettings()
    {
        try {
            $settings = SystemSetting::getSettings();
            $invoiceSettings = $settings->getInvoiceGenerationSettings();
            
            return response()->json([
                'success' => true,
                'data' => $invoiceSettings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoice settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification settings (API)
     */
    public function getNotificationSettings()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            $notificationSettings = [
                'channels' => [
                    'invoice_generated' => $settings->getInvoiceNotificationChannels(),
                    'payment_reminder' => $settings->getPaymentReminderChannels(),
                    'overdue' => $settings->getOverdueNotificationChannels(),
                    'payment_confirmation' => $settings->getPaymentConfirmationChannels(),
                ],
                'sms' => [
                    'enabled' => $settings->isSmsEnabled(),
                    'reminder_enabled' => $settings->isSmsReminderEnabled(),
                    'payment_confirmation_enabled' => $settings->isSmsPaymentConfirmationEnabled(),
                    'rate_limits' => $settings->getSmsRateLimits(),
                ],
                'whatsapp' => [
                    'enabled' => $settings->isWhatsAppEnabled(),
                    'reminder_enabled' => $settings->isWhatsAppReminderEnabled(),
                    'payment_confirmation_enabled' => $settings->isWhatsAppPaymentConfirmationEnabled(),
                    'configured' => $settings->isWhatsAppConfigured(),
                    'provider' => $settings->whatsapp_provider,
                ],
                'email' => [
                    'enabled' => true,
                    'configured' => $settings->isEmailConfigured(),
                ],
                'settings' => [
                    'force_email_fallback' => $settings->shouldForceEmailFallback(),
                    'retry_attempts' => $settings->notification_retry_attempts,
                    'retry_delay_minutes' => $settings->notification_retry_delay_minutes,
                    'enable_bulk_notifications' => $settings->shouldEnableBulkNotifications(),
                    'bulk_batch_size' => $settings->getBulkNotificationBatchSize(),
                ],
                'templates' => [
                    'sms' => [
                        'invoice_generated' => $settings->sms_invoice_generated_template,
                        'payment_reminder' => $settings->sms_payment_reminder_template,
                        'overdue' => $settings->sms_overdue_template,
                        'payment_confirmation' => $settings->sms_payment_confirmation_template,
                    ],
                    'whatsapp' => [
                        'invoice_generated' => $settings->whatsapp_invoice_generated_template,
                        'payment_reminder' => $settings->whatsapp_payment_reminder_template,
                        'overdue' => $settings->whatsapp_overdue_template,
                        'payment_confirmation' => $settings->whatsapp_payment_confirmation_template,
                    ],
                ],
                'has_custom_templates' => [
                    'sms' => !empty($settings->sms_invoice_generated_template) ||
                             !empty($settings->sms_payment_reminder_template) ||
                             !empty($settings->sms_overdue_template) ||
                             !empty($settings->sms_payment_confirmation_template),
                    'whatsapp' => !empty($settings->whatsapp_invoice_generated_template) ||
                                  !empty($settings->whatsapp_payment_reminder_template) ||
                                  !empty($settings->whatsapp_overdue_template) ||
                                  !empty($settings->whatsapp_payment_confirmation_template),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $notificationSettings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notification settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            // Check email configuration
            $emailStatus = $settings->getEmailConfigurationStatusFull();
            
            // Check SMS status
            $smsStatus = $this->smsService->getSystemStatus();
            
            // Check WhatsApp configuration
            $whatsappStatus = $settings->getWhatsAppConfigurationStatus();
            
            // Check payment configuration
            $paymentStatus = $settings->getPaymentConfigurationStatus();
            
            // Check database connection
            $dbConnected = false;
            try {
                DB::connection()->getPdo();
                $dbConnected = true;
            } catch (\Exception $e) {
                $dbConnected = false;
            }
            
            // Check cache
            $cacheWorking = false;
            try {
                Cache::put('health_check', 'ok', 60);
                $cacheWorking = Cache::get('health_check') === 'ok';
            } catch (\Exception $e) {
                $cacheWorking = false;
            }
            
            // Calculate overall health
            $criticalServices = [
                'database' => $dbConnected,
                'cache' => $cacheWorking,
                'email' => $emailStatus['can_send_emails'],
                'payment_recipient' => $paymentStatus['recipient_configured'],
            ];
            
            $allCritical = collect($criticalServices)->every(fn($service) => $service === true);
            $hasWarnings = !$allCritical;
            
            $issues = [];
            if (!$dbConnected) $issues[] = 'Database connection failed';
            if (!$cacheWorking) $issues[] = 'Cache system not working';
            if (!$emailStatus['can_send_emails']) $issues[] = 'Email service not configured properly';
            if (!$paymentStatus['recipient_configured']) $issues[] = 'Payment recipient not configured';
            if ($settings->isSmsEnabled() && !$smsStatus['system_ready']) $issues[] = 'SMS service not ready';
            if ($settings->isWhatsAppEnabled() && !$whatsappStatus['configured']) $issues[] = 'WhatsApp is enabled but not configured';
            
            $healthStatus = $allCritical ? 'healthy' : ($hasWarnings ? 'degraded' : 'unhealthy');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $healthStatus,
                    'overall' => $allCritical ? 'All systems operational' : 'Some services require attention',
                    'issues' => $issues,
                    'components' => [
                        'database' => [
                            'status' => $dbConnected ? 'healthy' : 'critical',
                            'message' => $dbConnected ? 'Connected' : 'Connection failed'
                        ],
                        'cache' => [
                            'status' => $cacheWorking ? 'healthy' : 'critical',
                            'message' => $cacheWorking ? 'Working' : 'Not responding'
                        ],
                        'email' => [
                            'status' => $emailStatus['can_send_emails'] ? 'healthy' : 'warning',
                            'message' => $emailStatus['can_send_emails'] ? 'Configured' : 'Not configured',
                            'details' => $emailStatus
                        ],
                        'sms' => [
                            'status' => $smsStatus['system_ready'] ? 'healthy' : 'warning',
                            'message' => $smsStatus['system_ready'] ? 'Ready' : 'Not ready',
                            'details' => $smsStatus
                        ],
                        'whatsapp' => [
                            'status' => $whatsappStatus['configured'] ? 'healthy' : 'warning',
                            'message' => $whatsappStatus['configured'] ? 'Configured' : 'Not configured',
                            'details' => $whatsappStatus
                        ],
                        'payment' => [
                            'status' => $paymentStatus['recipient_configured'] ? 'healthy' : 'warning',
                            'message' => $paymentStatus['recipient_configured'] ? 'Configured' : 'Not configured',
                            'details' => $paymentStatus
                        ]
                    ],
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving system health: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get environment information
     */
    public function getEnvironmentInfo()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            $environmentInfo = [
                'app' => [
                    'name' => config('app.name'),
                    'environment' => app()->environment(),
                    'version' => app()->version(),
                    'debug' => config('app.debug'),
                    'url' => config('app.url'),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                ],
                'server' => [
                    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'timezone' => config('app.timezone'),
                    'date' => now()->format('Y-m-d H:i:s'),
                ],
                'database' => [
                    'connection' => config('database.default'),
                    'driver' => config('database.connections.mysql.driver'),
                    'host' => config('database.connections.mysql.host'),
                    'database' => config('database.connections.mysql.database'),
                ],
                'queue' => [
                    'default' => config('queue.default'),
                    'connection' => config('queue.connections.' . config('queue.default') . '.driver', 'Unknown'),
                ],
                'cache' => [
                    'default' => config('cache.default'),
                    'store' => config('cache.stores.' . config('cache.default') . '.driver', 'Unknown'),
                ],
                'mail' => [
                    'default' => config('mail.default'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ],
                'system_settings' => [
                    'name' => $settings->system_name,
                    'short_name' => $settings->system_short_name,
                    'email' => $settings->system_email,
                    'phone' => $settings->system_phone,
                    'currency' => $settings->currency_code,
                    'auto_invoice' => $settings->auto_generate_invoices,
                    'bulk_payments' => $settings->enable_bulk_payments,
                    'tenant_invoicing' => $settings->enable_tenant_invoicing,
                    'registration_allowed' => $settings->isRegistrationAllowed(),
                ],
                'performance' => [
                    'total_properties' => Property::count(),
                    'total_users' => User::count(),
                    'total_invoices' => Invoice::count(),
                    'pending_invoices' => Invoice::where('status', 'pending')->count(),
                    'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $environmentInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving environment information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function getSystemStatistics()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            $statistics = [
                'users' => [
                    'total' => User::count(),
                    'landlords' => User::where('type', User::TYPE_LANDLORD)->count(),
                    'tenants' => User::where('type', User::TYPE_TENANT)->count(),
                    'admins' => User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])->count(),
                    'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                    'inactive' => User::where('status', User::STATUS_INACTIVE)->count(),
                ],
                'properties' => [
                    'total' => Property::count(),
                    'active' => Property::where('status', 'active')->count(),
                    'inactive' => Property::where('status', 'inactive')->count(),
                ],
                'invoices' => [
                    'total' => Invoice::count(),
                    'paid' => Invoice::where('status', 'paid')->count(),
                    'pending' => Invoice::where('status', 'pending')->count(),
                    'overdue' => Invoice::where('status', 'overdue')->count(),
                    'consolidated' => Invoice::where('status', 'consolidated')->count(),
                    'total_amount' => $settings->formatAmount(Invoice::sum('amount')),
                    'paid_amount' => $settings->formatAmount(Invoice::where('status', 'paid')->sum('amount')),
                    'pending_amount' => $settings->formatAmount(Invoice::where('status', 'pending')->sum('amount')),
                    'overdue_amount' => $settings->formatAmount(Invoice::where('status', 'overdue')->sum('amount')),
                    'collection_rate' => $this->calculateCollectionRate(),
                ],
                'bulk_payments' => [
                    'total' => Invoice::where('is_bulk_payment', true)->count(),
                    'paid' => Invoice::where('is_bulk_payment', true)->where('status', 'paid')->count(),
                    'total_coverage_months' => $this->calculateTotalCoverageMonths(),
                ],
                'notifications' => [
                    'total_sent' => 0, // Would need notification logs table
                    'successful' => 0,
                    'failed' => 0,
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving system statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate collection rate
     */
    protected function calculateCollectionRate(): float
    {
        $totalInvoices = Invoice::where('status', '!=', 'consolidated')->count();
        $paidInvoices = Invoice::where('status', 'paid')->count();
        
        if ($totalInvoices === 0) {
            return 0;
        }
        
        return round(($paidInvoices / $totalInvoices) * 100, 2);
    }

    /**
     * Calculate total coverage months from bulk payments
     */
    protected function calculateTotalCoverageMonths(): int
    {
        $total = 0;
        $bulkInvoices = Invoice::where('is_bulk_payment', true)
            ->where('status', 'paid')
            ->whereNotNull('covers_periods')
            ->get();
        
        foreach ($bulkInvoices as $invoice) {
            $periods = $invoice->covers_periods;
            if (is_string($periods)) {
                $periods = json_decode($periods, true);
            }
            if (is_array($periods)) {
                $total += count($periods);
            }
        }
        
        return $total;
    }

    /**
     * Clear system cache
     */
    public function clearCache()
    {
        try {
            // Clear application cache
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            
            // Clear system settings cache
            SystemSetting::clearCache();
            
            return response()->json([
                'success' => true,
                'message' => '✅ System cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system uptime
     */
    public function getSystemUptime()
    {
        try {
            $uptime = null;
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('uptime');
            }
            
            // Get the earliest created_at from any record
            $earliestUser = User::orderBy('created_at', 'asc')->first();
            $earliestProperty = Property::orderBy('created_at', 'asc')->first();
            $earliestInvoice = Invoice::orderBy('created_at', 'asc')->first();
            
            $systemStartDate = null;
            if ($earliestUser) $systemStartDate = $earliestUser->created_at;
            if ($earliestProperty && (!$systemStartDate || $earliestProperty->created_at < $systemStartDate)) $systemStartDate = $earliestProperty->created_at;
            if ($earliestInvoice && (!$systemStartDate || $earliestInvoice->created_at < $systemStartDate)) $systemStartDate = $earliestInvoice->created_at;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'server_uptime' => $uptime ? trim($uptime) : 'Unknown',
                    'system_start_date' => $systemStartDate ? $systemStartDate->format('Y-m-d H:i:s') : null,
                    'system_age_days' => $systemStartDate ? $systemStartDate->diffInDays(now()) : null,
                    'current_time' => now()->format('Y-m-d H:i:s'),
                    'timezone' => config('app.timezone'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving system uptime: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending updates information
     */
    public function getPendingUpdates()
    {
        try {
            $pendingUpdates = [
                'system_settings' => session()->has('pending_system_update'),
                'email_config' => session()->has('pending_email_update'),
                'whatsapp_config' => session()->has('pending_whatsapp_update'),
                'last_check' => now()->format('Y-m-d H:i:s'),
            ];
            
            if ($pendingUpdates['system_settings']) {
                $pendingUpdates['system_settings_data'] = session('pending_system_update');
            }
            
            if ($pendingUpdates['email_config']) {
                $pendingUpdates['email_config_data'] = session('pending_email_update');
            }
            
            if ($pendingUpdates['whatsapp_config']) {
                $pendingUpdates['whatsapp_config_data'] = session('pending_whatsapp_update');
            }
            
            return response()->json([
                'success' => true,
                'data' => $pendingUpdates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking pending updates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database status
     */
    public function getDatabaseStatus()
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            // Get database size
            $databaseSize = 0;
            $databaseName = $connection->getDatabaseName();
            
            $results = DB::select("
                SELECT 
                    table_schema AS 'database',
                    SUM(data_length + index_length) / 1024 / 1024 AS 'size_mb'
                FROM information_schema.TABLES
                WHERE table_schema = ?
                GROUP BY table_schema
            ", [$databaseName]);
            
            if (!empty($results)) {
                $databaseSize = round($results[0]->size_mb, 2);
            }
            
            // Get table counts
            $tables = [];
            $tablesList = DB::select("SHOW TABLES");
            $tableKey = 'Tables_in_' . $databaseName;
            
            foreach ($tablesList as $table) {
                $tableName = $table->$tableKey;
                $count = DB::table($tableName)->count();
                $tables[] = [
                    'name' => $tableName,
                    'rows' => $count,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'connected' => true,
                    'database_name' => $databaseName,
                    'size_mb' => $databaseSize,
                    'total_tables' => count($tables),
                    'tables' => $tables,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ], 500);
        }
    }
}