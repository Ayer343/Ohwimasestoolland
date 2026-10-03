<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\EnvironmentConfigService;
use App\Jobs\UpdateWhatsAppConfiguration;
use App\Jobs\TestWhatsAppConnection;
use App\Jobs\SendTestWhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

class WhatsAppProviderController extends Controller
{
    protected $whatsappService;
    protected $environmentService;

    public function __construct(
        WhatsAppService $whatsappService,
        EnvironmentConfigService $environmentService
    ) {
        $this->whatsappService = $whatsappService;
        $this->environmentService = $environmentService;
    }

    /**
     * ✅ Index page showing all WhatsApp providers
     */
    public function index()
    {
        $providers = [
            'twilio' => [
                'name' => 'Twilio WhatsApp',
                'fields' => [
                    'twilio_sid' => 'Account SID',
                    'twilio_token' => 'Auth Token',
                    'twilio_whatsapp_from' => 'WhatsApp From Number'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === 'twilio',
                'is_active' => $this->whatsappService->isProviderActive('twilio'),
                'docs_url' => 'https://www.twilio.com/whatsapp'
            ],
            'vonage' => [
                'name' => 'Vonage WhatsApp',
                'fields' => [
                    'vonage_key' => 'API Key',
                    'vonage_secret' => 'API Secret',
                    'vonage_whatsapp_from' => 'WhatsApp From Number'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === 'vonage',
                'is_active' => $this->whatsappService->isProviderActive('vonage'),
                'docs_url' => 'https://developer.vonage.com/messages/concepts/whatsapp'
            ],
            'custom' => [
                'name' => 'Custom WhatsApp API',
                'fields' => [
                    'whatsapp_api_url' => 'API URL',
                    'whatsapp_api_key' => 'API Key'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === 'custom',
                'is_active' => $this->whatsappService->isProviderActive('custom'),
                'docs_url' => null
            ],
            '360dialog' => [
                'name' => '360Dialog WhatsApp',
                'fields' => [
                    'dialog_api_key' => 'API Key',
                    'dialog_phone_number_id' => 'Phone Number ID',
                    'dialog_business_id' => 'Business ID'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === '360dialog',
                'is_active' => $this->whatsappService->isProviderActive('360dialog'),
                'docs_url' => 'https://360dialog.com/whatsapp-api'
            ],
            'wati' => [
                'name' => 'WATI WhatsApp',
                'fields' => [
                    'wati_api_key' => 'API Key',
                    'wati_api_url' => 'API URL'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === 'wati',
                'is_active' => $this->whatsappService->isProviderActive('wati'),
                'docs_url' => 'https://docs.wati.io/'
            ],
            'vumaapi' => [
                'name' => 'VumaAPI WhatsApp (Ghana)',
                'fields' => [
                    'vumaapi_api_key' => 'API Key',
                    'vumaapi_api_secret' => 'API Secret',
                    'vumaapi_whatsapp_from' => 'WhatsApp Sender ID',
                    'vumaapi_api_url' => 'API Base URL'
                ],
                'enabled' => env('WHATSAPP_PROVIDER') === 'vumaapi',
                'is_active' => $this->whatsappService->isProviderActive('vumaapi'),
                'docs_url' => 'https://www.vumacloud.com/api/ghana'
            ]
        ];

        // Get current configuration status
        $configurationStatus = $this->whatsappService->checkWhatsAppConfiguration();
        $systemStatus = $this->whatsappService->getSystemStatus();

        // Check for pending configuration updates
        $hasPendingUpdate = session()->has('pending_whatsapp_update');
        $pendingUpdate = session()->get('pending_whatsapp_update', null);

        return view('admin.whatsapp.providers', compact(
            'providers',
            'configurationStatus',
            'systemStatus',
            'hasPendingUpdate',
            'pendingUpdate'
        ));
    }

    /**
     * ✅ Configure WhatsApp Provider with Background Processing
     */
    public function configureProvider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi',
            'whatsapp_enabled' => 'sometimes|boolean',

            // Twilio
            'twilio_sid' => 'required_if:provider,twilio|string',
            'twilio_token' => 'required_if:provider,twilio|string',
            'twilio_whatsapp_from' => 'required_if:provider,twilio|string|regex:/^whatsapp:\+\d+$/',

            // Vonage
            'vonage_key' => 'required_if:provider,vonage|string',
            'vonage_secret' => 'required_if:provider,vonage|string',
            'vonage_whatsapp_from' => 'required_if:provider,vonage|string|regex:/^\+\d+$/',

            // Custom
            'whatsapp_api_url' => 'required_if:provider,custom|url',
            'whatsapp_api_key' => 'required_if:provider,custom|string',

            // 360Dialog
            'dialog_api_key' => 'required_if:provider,360dialog|string',
            'dialog_phone_number_id' => 'required_if:provider,360dialog|string',
            'dialog_business_id' => 'required_if:provider,360dialog|string',

            // WATI
            'wati_api_key' => 'required_if:provider,wati|string',
            'wati_api_url' => 'required_if:provider,wati|url',

            // VumaAPI (Ghana)
            'vumaapi_api_key' => 'required_if:provider,vumaapi|string',
            'vumaapi_api_secret' => 'nullable|string',
            'vumaapi_whatsapp_from' => 'required_if:provider,vumaapi|string|regex:/^whatsapp:\+\d+$/',
            'vumaapi_api_url' => 'required_if:provider,vumaapi|url',
            'vumaapi_environment' => 'sometimes|in:sandbox,production',
        ], [
            'twilio_whatsapp_from.regex' => 'Twilio WhatsApp number must be in format: whatsapp:+1234567890',
            'vonage_whatsapp_from.regex' => 'Vonage WhatsApp number must be in format: +1234567890',
            'vumaapi_whatsapp_from.regex' => 'VumaAPI WhatsApp Sender ID must be in format: whatsapp:+233XXXXXXXXX',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $provider = $request->provider;

        try {
            $envData = $this->getProviderEnvData($provider, $request);

            // Validate provider-specific requirements
            $validationResult = $this->whatsappService->validateProviderConfiguration($provider, $envData);
            if (!$validationResult['success']) {
                return redirect()->back()
                    ->with('error', '❌ Configuration validation failed: ' . $validationResult['message'])
                    ->withInput();
            }

            // Queue configuration update for background processing
            $queueResult = $this->queueWhatsAppConfigurationUpdate($provider, $envData, 'configure');

            if ($queueResult['success']) {
                Log::info("{$provider} WhatsApp configuration update queued successfully", [
                    'provider' => $provider,
                    'method' => $queueResult['method']
                ]);

                return redirect()->route('admin.whatsapp-providers.index')
                    ->with('success', "✅ " . $this->getProviderName($provider) . " configuration is being updated in the background!")
                    ->with('provider', $provider)
                    ->with('config_update_status', 'processing');
            } else {
                // Fallback to immediate update
                Log::warning("Failed to queue WhatsApp configuration update, falling back to immediate update", [
                    'provider' => $provider,
                    'error' => $queueResult['message']
                ]);

                return $this->processWhatsAppConfigurationImmediately($provider, $envData);
            }

        } catch (\Exception $e) {
            Log::error("Failed to configure {$provider} WhatsApp: " . $e->getMessage(), [
                'provider' => $provider,
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return redirect()->back()
                ->with('error', '❌ Failed to configure WhatsApp provider: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * ✅ Queue WhatsApp configuration update for background processing
     */
    protected function queueWhatsAppConfigurationUpdate($provider, $envData, $action = 'configure')
    {
        try {
            // Option 1: Use Laravel queue if available
            if (config('queue.default') !== 'sync') {
                // Correct parameter order - config array first, then provider, then user ID
                UpdateWhatsAppConfiguration::dispatch($envData, $provider, auth()->id());

                return [
                    'success' => true,
                    'message' => 'WhatsApp configuration update queued successfully',
                    'method' => 'queue'
                ];
            }

            // Option 2: Use session-based delayed update
            session()->put('pending_whatsapp_update', [
                'provider' => $provider,
                'env_data' => $envData,
                'timestamp' => now()->timestamp,
                'attempted' => false,
                'action' => $action
            ]);

            return [
                'success' => true,
                'message' => 'WhatsApp configuration update scheduled via session',
                'method' => 'session'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to schedule WhatsApp configuration update: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ Process WhatsApp configuration immediately (fallback)
     */
    protected function processWhatsAppConfigurationImmediately($provider, $envData)
    {
        try {
            // Clear all caches before making changes
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // Backup current .env file
            $this->backupEnvFile();

            // Update .env file
            $this->updateEnvFile($envData);

            // Clear and cache config
            Artisan::call('config:clear');
            sleep(1);
            Artisan::call('config:cache');
            sleep(2);

            // Reload environment variables
            if (function_exists('app')) {
                app()->loadEnvironmentFrom('.env');
            }

            // Verify the update was successful
            $updatedValues = [];
            foreach ($envData as $key => $expectedValue) {
                $actualValue = env($key);
                $updatedValues[$key] = [
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                    'match' => $actualValue == $expectedValue
                ];
            }

            Log::info("{$provider} WhatsApp configuration update verification", $updatedValues);

            // Check if any values didn't match
            $failedUpdates = array_filter($updatedValues, function ($item) {
                return !$item['match'];
            });

            if (!empty($failedUpdates)) {
                Log::warning("Some environment variables failed to update", $failedUpdates);
                throw new \Exception("Some configuration values failed to update. Please try again.");
            }

            Log::info("{$provider} WhatsApp configuration updated successfully", [
                'provider' => $provider,
                'verification' => $updatedValues
            ]);

            return redirect()->route('admin.whatsapp-providers.index')
                ->with('success', "✅ " . $this->getProviderName($provider) . " configuration updated successfully!")
                ->with('provider', $provider);

        } catch (\Exception $e) {
            Log::error("Failed to update {$provider} WhatsApp configuration immediately: " . $e->getMessage());
            $this->restoreEnvBackup();

            return redirect()->back()
                ->with('error', '❌ Failed to update WhatsApp configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * ✅ Toggle WhatsApp provider
     */
    public function toggleProvider(Request $request)
    {
        Log::info('Toggle WhatsApp provider request received', $request->all());

        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi,none',
            'enable' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            Log::warning('Toggle WhatsApp provider validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        }

        $provider = $request->provider;
        $enable = $request->boolean('enable');

        try {
            if ($enable) {
                // Enable this provider, disable others
                $envData = ['WHATSAPP_PROVIDER' => $provider];
            } else {
                // Disable all WhatsApp providers
                $envData = ['WHATSAPP_PROVIDER' => 'none'];
            }

            // Queue the toggle operation
            $queueResult = $this->queueWhatsAppConfigurationUpdate($provider, $envData, 'toggle');

            if ($queueResult['success']) {
                Log::info("{$provider} WhatsApp toggle operation queued", [
                    'enable' => $enable,
                    'method' => $queueResult['method']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '✅ ' . $this->getProviderName($provider) . ' ' . ($enable ? 'enabling' : 'disabling') . ' in background!',
                    'enabled' => $enable,
                    'provider' => $provider,
                    'processing' => true
                ], 200, [], JSON_UNESCAPED_UNICODE);
            } else {
                // Fallback to immediate toggle
                $this->backupEnvFile();
                $this->updateEnvFile($envData);
                Artisan::call('config:clear');
                Artisan::call('config:cache');

                Log::info("{$provider} WhatsApp provider " . ($enable ? 'enabled' : 'disabled') . " successfully");

                return response()->json([
                    'success' => true,
                    'message' => '✅ ' . $this->getProviderName($provider) . ' ' . ($enable ? 'enabled' : 'disabled') . ' successfully!',
                    'enabled' => $enable,
                    'provider' => $provider,
                    'system_status' => $this->whatsappService->getSystemStatus()
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

        } catch (\Exception $e) {
            Log::error("Failed to toggle {$provider} WhatsApp provider: " . $e->getMessage(), [
                'provider' => $provider,
                'enable' => $enable,
                'exception' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to toggle WhatsApp provider: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✅ Test WhatsApp connection
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi',
            'queue' => 'sometimes|boolean'
        ]);

        $provider = $request->provider;
        $queueTest = $request->boolean('queue', false);

        try {
            if ($queueTest && config('queue.default') !== 'sync') {
                // Queue the connection test
                TestWhatsAppConnection::dispatch($provider, auth()->id());

                return response()->json([
                    'success' => true,
                    'message' => '🔧 WhatsApp connection test queued for background processing',
                    'provider' => $provider,
                    'queued' => true,
                    'timestamp' => now()->toISOString(),
                    'provider_name' => $this->getProviderName($provider)
                ]);
            }

            // Immediate test
            Artisan::call('config:clear');
            $result = $this->whatsappService->testConnection($provider);

            $response = [
                'success' => $result['success'],
                'message' => $result['success'] ? '✅ ' . $result['message'] : '❌ ' . $result['message'],
                'provider' => $provider,
                'details' => $result['details'] ?? [],
                'error_code' => $result['error_code'] ?? null,
                'timestamp' => $result['timestamp'] ?? now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued' => false
            ];

            if ($result['success']) {
                Log::info("WhatsApp connection test successful for {$provider}", $response);
                return response()->json($response);
            }

            Log::warning("WhatsApp connection test failed for {$provider}", $response);
            return response()->json($response, 400);

        } catch (\Exception $e) {
            Log::error("WhatsApp connection test error for {$provider}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '❌ Connection test failed: ' . $e->getMessage(),
                'provider' => $provider,
                'error_code' => 'EXCEPTION',
                'timestamp' => now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued' => false
            ], 500);
        }
    }

    /**
     * ✅ Send test WhatsApp message
     */
    public function sendTestMessage(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi',
            'phone_number' => 'required|string|max:20|regex:/^whatsapp:\+\d+$/',
            'message' => 'required|string|max:1000',
            'queue' => 'sometimes|boolean'
        ], [
            'phone_number.regex' => 'WhatsApp number must be in format: whatsapp:+1234567890'
        ]);

        $provider = $request->provider;
        $phoneNumber = $request->phone_number;
        $message = $request->message;
        $queueMessage = $request->boolean('queue', false);

        try {
            if ($queueMessage && config('queue.default') !== 'sync') {
                // Queue the message sending
                SendTestWhatsAppMessage::dispatch($provider, $phoneNumber, $message, auth()->id());

                return response()->json([
                    'success' => true,
                    'message' => '📱 Test WhatsApp message queued for background sending',
                    'provider' => $provider,
                    'queued' => true,
                    'timestamp' => now()->toISOString(),
                    'provider_name' => $this->getProviderName($provider)
                ]);
            }

            // Immediate message sending
            Artisan::call('config:clear');
            $result = $this->whatsappService->sendTestMessage($provider, $phoneNumber, $message);

            $response = [
                'success' => $result['success'],
                'message' => $result['success'] ? '✅ ' . $result['message'] : '❌ ' . $result['message'],
                'provider' => $provider,
                'details' => $result['details'] ?? [],
                'error_code' => $result['error_code'] ?? null,
                'timestamp' => $result['timestamp'] ?? now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'execution_time' => $result['execution_time_ms'] ?? null,
                'queued' => false
            ];

            if ($result['success']) {
                Log::info("Test WhatsApp message sent successfully via {$provider} to {$phoneNumber}");
                return response()->json($response);
            } else {
                Log::warning("Test WhatsApp message failed via {$provider}: " . $result['message']);
                return response()->json($response, 400);
            }
        } catch (\Exception $e) {
            Log::error("Test WhatsApp message error for {$provider}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '❌ Test WhatsApp message failed: ' . $e->getMessage(),
                'provider' => $provider,
                'error_code' => 'EXCEPTION',
                'timestamp' => now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued' => false
            ], 500);
        }
    }

    /**
     * ✅ Reset provider configuration
     */
    public function resetProvider(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi'
        ]);

        $provider = $request->provider;

        try {
            $envData = $this->getResetEnvData($provider);

            $this->backupEnvFile();
            $this->updateEnvFile($envData);

            // Clear all caches after reset
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:cache');

            Log::info("{$provider} WhatsApp configuration reset successfully");

            return response()->json([
                'success' => true,
                'message' => "✅ " . $this->getProviderName($provider) . " WhatsApp configuration reset successfully!",
                'provider' => $provider,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to reset {$provider} WhatsApp configuration: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '❌ Failed to reset WhatsApp configuration: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * ✅ Get WhatsApp configuration status
     */
    public function getConfigurationStatus()
    {
        try {
            $status = $this->whatsappService->checkWhatsAppConfiguration();
            $systemStatus = $this->whatsappService->getSystemStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'configuration' => $status,
                    'system' => $systemStatus
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get WhatsApp configuration status: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * ✅ Get provider configuration details
     */
    public function getProviderConfig(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati,vumaapi'
        ]);

        try {
            Artisan::call('config:clear');
            $config = $this->whatsappService->getProviderConfig($request->provider);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider configuration not found',
                    'error_code' => 'PROVIDER_NOT_FOUND',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $config,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider configuration: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========

    /**
     * Get provider environment data
     */
    protected function getProviderEnvData($provider, $request)
    {
        switch ($provider) {
            case 'twilio':
                return [
                    'WHATSAPP_PROVIDER' => 'twilio',
                    'TWILIO_SID' => $request->twilio_sid ?: '',
                    'TWILIO_AUTH_TOKEN' => $request->twilio_token ?: '',
                    'TWILIO_WHATSAPP_FROM' => $request->twilio_whatsapp_from ?: ''
                ];

            case 'vonage':
                return [
                    'WHATSAPP_PROVIDER' => 'vonage',
                    'VONAGE_KEY' => $request->vonage_key ?: '',
                    'VONAGE_SECRET' => $request->vonage_secret ?: '',
                    'VONAGE_WHATSAPP_FROM' => $request->vonage_whatsapp_from ?: ''
                ];

            case 'custom':
                return [
                    'WHATSAPP_PROVIDER' => 'custom',
                    'WHATSAPP_API_URL' => $request->whatsapp_api_url ?: '',
                    'WHATSAPP_API_KEY' => $request->whatsapp_api_key ?: ''
                ];

            case '360dialog':
                return [
                    'WHATSAPP_PROVIDER' => '360dialog',
                    'DIALOG_API_KEY' => $request->dialog_api_key ?: '',
                    'DIALOG_PHONE_NUMBER_ID' => $request->dialog_phone_number_id ?: '',
                    'DIALOG_BUSINESS_ID' => $request->dialog_business_id ?: ''
                ];

            case 'wati':
                return [
                    'WHATSAPP_PROVIDER' => 'wati',
                    'WATI_API_KEY' => $request->wati_api_key ?: '',
                    'WATI_API_URL' => $request->wati_api_url ?: ''
                ];

            case 'vumaapi':
                return [
                    'WHATSAPP_PROVIDER' => 'vumaapi',
                    'VUMAAPI_API_KEY' => $request->vumaapi_api_key ?: '',
                    'VUMAAPI_API_SECRET' => $request->vumaapi_api_secret ?: '',
                    'VUMAAPI_WHATSAPP_FROM' => $request->vumaapi_whatsapp_from ?: '',
                    'VUMAAPI_API_URL' => $request->vumaapi_api_url ?: 'https://api.vumacloud.com',
                    'VUMAAPI_ENVIRONMENT' => $request->vumaapi_environment ?: 'sandbox'
                ];

            default:
                return [];
        }
    }

    /**
     * Get reset environment data
     */
    protected function getResetEnvData($provider)
    {
        switch ($provider) {
            case 'twilio':
                return [
                    'TWILIO_SID' => '',
                    'TWILIO_AUTH_TOKEN' => '',
                    'TWILIO_WHATSAPP_FROM' => ''
                ];

            case 'vonage':
                return [
                    'VONAGE_KEY' => '',
                    'VONAGE_SECRET' => '',
                    'VONAGE_WHATSAPP_FROM' => ''
                ];

            case 'custom':
                return [
                    'WHATSAPP_API_URL' => '',
                    'WHATSAPP_API_KEY' => ''
                ];

            case '360dialog':
                return [
                    'DIALOG_API_KEY' => '',
                    'DIALOG_PHONE_NUMBER_ID' => '',
                    'DIALOG_BUSINESS_ID' => ''
                ];

            case 'wati':
                return [
                    'WATI_API_KEY' => '',
                    'WATI_API_URL' => ''
                ];

            case 'vumaapi':
                return [
                    'VUMAAPI_API_KEY' => '',
                    'VUMAAPI_API_SECRET' => '',
                    'VUMAAPI_WHATSAPP_FROM' => '',
                    'VUMAAPI_API_URL' => '',
                    'VUMAAPI_ENVIRONMENT' => ''
                ];

            default:
                return [];
        }
    }

    /**
     * Get provider display name
     */
    protected function getProviderName($providerKey)
    {
        $providers = [
            'twilio' => 'Twilio WhatsApp',
            'vonage' => 'Vonage WhatsApp',
            'custom' => 'Custom WhatsApp API',
            '360dialog' => '360Dialog WhatsApp',
            'wati' => 'WATI WhatsApp',
            'vumaapi' => 'VumaAPI WhatsApp (Ghana)'
        ];

        return $providers[$providerKey] ?? $providerKey;
    }

    /**
     * Backup .env file
     */
    protected function backupEnvFile()
    {
        try {
            $envPath = base_path('.env');
            $backupPath = base_path('.env.backup.whatsapp.' . date('Y-m-d-H-i-s'));

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

    /**
     * Restore .env backup
     */
    protected function restoreEnvBackup()
    {
        try {
            $backups = File::glob(base_path('.env.backup.whatsapp.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                File::copy($latestBackup, base_path('.env'));
                Log::info('Restored .env from backup: ' . $latestBackup);
                return true;
            }
            Log::warning('No WhatsApp backups found to restore');
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update .env file
     */
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

            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
                Log::debug("Updated existing env variable: {$key}={$escapedValue}");
            } else {
                // Add new variable if it doesn't exist
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
                Log::debug("Added new env variable: {$key}={$escapedValue}");
            }
        }

        if ($updated) {
            // Clear any cached config first
            Artisan::call('config:clear');

            // Normalize line endings and ensure proper formatting
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";

            File::put($envPath, $envContent);

            // Force reload of environment
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            Log::info('Updated .env file with WhatsApp configuration', $data);
        } else {
            Log::warning('No changes made to .env file');
        }

        return $updated;
    }

    /**
     * Escape environment value
     */
    protected function escapeEnvValue($value)
    {
        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return $value;
        }

        // Handle empty values
        if (empty($value) && $value !== '0') {
            return '""';
        }

        // If value contains spaces, quotes, or special characters, wrap in quotes
        if (preg_match('/[\\s#"\'\\\\`$!*?[\]{}|&;()<>]/', $value) || empty($value)) {
            $value = str_replace(['"', '\\'], ['\"', '\\\\'], $value);
            return '"' . $value . '"';
        }

        return $value;
    }

    /**
     * ✅ Check and process pending WhatsApp updates
     */
    public function checkPendingWhatsAppUpdates()
    {
        if (!session()->has('pending_whatsapp_update') || session('pending_whatsapp_update.attempted', false)) {
            return [
                'success' => false,
                'message' => 'No pending WhatsApp updates found or update already attempted'
            ];
        }

        $pendingUpdate = session()->get('pending_whatsapp_update');

        try {
            // Mark as attempted to prevent infinite loops
            session()->put('pending_whatsapp_update.attempted', true);
            session()->save();

            // Execute the WhatsApp configuration update
            $updateResult = $this->processWhatsAppConfigurationUpdate($pendingUpdate);

            if ($updateResult['success']) {
                session()->forget('pending_whatsapp_update');
                session()->save();

                $action = $pendingUpdate['action'] ?? 'configure';
                Log::info('Manual retry: WhatsApp configuration update completed successfully', [
                    'provider' => $pendingUpdate['provider'],
                    'action' => $action
                ]);

                return [
                    'success' => true,
                    'message' => 'WhatsApp configuration updated successfully!',
                    'action' => $action,
                    'provider' => $pendingUpdate['provider']
                ];
            } else {
                // Keep in session for manual retry but mark as attempted
                Log::warning('Manual retry: WhatsApp configuration update failed', [
                    'provider' => $pendingUpdate['provider'],
                    'action' => $pendingUpdate['action'] ?? 'configure',
                    'error' => $updateResult['message']
                ]);

                return [
                    'success' => false,
                    'message' => 'WhatsApp configuration update failed: ' . $updateResult['message'],
                    'provider' => $pendingUpdate['provider']
                ];
            }

        } catch (\Exception $e) {
            Log::error('Manual retry: WhatsApp configuration update process failed', [
                'provider' => $pendingUpdate['provider'],
                'action' => $pendingUpdate['action'] ?? 'configure',
                'error' => $e->getMessage()
            ]);

            // Mark as attempted to prevent repeated failures
            session()->put('pending_whatsapp_update.attempted', true);
            session()->save();

            return [
                'success' => false,
                'message' => 'Update process error: ' . $e->getMessage(),
                'provider' => $pendingUpdate['provider']
            ];
        }
    }

    /**
     * Process WhatsApp configuration update
     */
    protected function processWhatsAppConfigurationUpdate($pendingUpdate)
    {
        try {
            $provider = $pendingUpdate['provider'];
            $envData = $pendingUpdate['env_data'];

            // Clear all caches before making changes
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // Backup current .env file
            $this->backupEnvFile();

            // Update .env file
            $this->updateEnvFile($envData);

            // Clear and cache config
            Artisan::call('config:clear');
            sleep(1);
            Artisan::call('config:cache');
            sleep(2);

            // Reload environment variables
            if (function_exists('app')) {
                app()->loadEnvironmentFrom('.env');
            }

            // Verify the update was successful
            $updatedValues = [];
            foreach ($envData as $key => $expectedValue) {
                $actualValue = env($key);
                $updatedValues[$key] = [
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                    'match' => $actualValue == $expectedValue
                ];
            }

            // Check if any values didn't match
            $failedUpdates = array_filter($updatedValues, function ($item) {
                return !$item['match'];
            });

            if (!empty($failedUpdates)) {
                throw new \Exception("Some configuration values failed to update.");
            }

            Log::info("WhatsApp configuration update processed successfully", [
                'provider' => $provider,
                'verification' => $updatedValues
            ]);

            return [
                'success' => true,
                'message' => 'WhatsApp configuration updated successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to process WhatsApp configuration update: " . $e->getMessage());
            $this->restoreEnvBackup();

            return [
                'success' => false,
                'message' => 'Failed to update WhatsApp configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Manual retry for failed WhatsApp updates
     */
    public function retryWhatsAppUpdate()
    {
        try {
            $result = $this->checkPendingWhatsAppUpdates();

            if ($result) {
                if ($result['success']) {
                    return redirect()->route('admin.whatsapp-providers.index')
                        ->with('success', 'WhatsApp configuration updated successfully!')
                        ->with('provider', $result['provider']);
                } else {
                    return redirect()->route('admin.whatsapp-providers.index')
                        ->with('warning', $result['message'])
                        ->with('provider', $result['provider']);
                }
            }

            return redirect()->route('admin.whatsapp-providers.index')
                ->with('info', 'No pending WhatsApp configuration updates found.');

        } catch (\Exception $e) {
            return redirect()->route('admin.whatsapp-providers.index')
                ->with('error', 'Error retrying WhatsApp configuration update: ' . $e->getMessage());
        }
    }
}