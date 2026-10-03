<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Jobs\UpdatePaymentProviderConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class PaymentProviderController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * ✅ ENHANCED: Index with immediate state feedback
     */
    public function index()
    {
        $providers = [
            'expresspay' => [
                'name' => 'ExpressPay',
                'fields' => [
                    'merchant_id' => 'Merchant ID',
                    'api_key' => 'API Key',
                    'environment' => 'Environment'
                ],
                'enabled' => env('EXPRESSPAY_ENABLED', false)
            ],
            'flutterwave' => [
                'name' => 'Flutterwave',
                'fields' => [
                    'public_key' => 'Public Key',
                    'secret_key' => 'Secret Key',
                    'encryption_key' => 'Encryption Key'
                ],
                'enabled' => env('FLUTTERWAVE_ENABLED', false)
            ],
            'hubtel' => [
                'name' => 'Hubtel',
                'fields' => [
                    'client_id' => 'Client ID',
                    'client_secret' => 'Client Secret',
                    'merchant_account' => 'Merchant Account'
                ],
                'enabled' => env('HUBTEL_ENABLED', false)
            ],
            'paystack' => [
                'name' => 'Paystack',
                'fields' => [
                    'secret_key' => 'Secret Key',
                    'public_key' => 'Public Key'
                ],
                'enabled' => env('PAYSTACK_ENABLED', false)
            ]
        ];

        $providerStates = [];
        foreach (array_keys($providers) as $providerKey) {
            $expectedState = Cache::get("payment_provider_{$providerKey}_expected");
            $actualState = Cache::get("payment_provider_{$providerKey}_actual");
            
            if ($expectedState || $actualState) {
                $providerStates[$providerKey] = [
                    'expected' => $expectedState,
                    'actual' => $actualState,
                    'has_pending_state' => !empty($expectedState) || !empty($actualState)
                ];
            }
        }

        $configurationStatus = $this->getConfigurationStatusWithReload();
        $hasPendingUpdate = session()->has('pending_payment_update');
        $pendingUpdate = session()->get('pending_payment_update', null);
        $updateStatus = [
            'success' => session('payment_update_success'),
            'message' => session('payment_update_message'),
            'provider' => session('payment_update_provider')
        ];

        $submittedConfig = session('submitted_config', null);
        if ($submittedConfig) {
            Session::forget('submitted_config');
        }

        return view('admin.payments.providers', compact(
            'providers', 
            'configurationStatus',
            'hasPendingUpdate',
            'pendingUpdate',
            'updateStatus',
            'providerStates',
            'submittedConfig'
        ));
    }

    /**
     * ✅ ENHANCED: Get configuration status with forced environment reload
     */
    protected function getConfigurationStatusWithReload()
    {
        try {
            $this->clearApplicationCaches(true);
            usleep(500000);
            return $this->paymentService->checkPaymentMethodConfiguration();
        } catch (\Exception $e) {
            Log::error('Failed to get configuration status with reload: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ ENHANCED: Configure provider with enhanced validation and AJAX support
     */
    public function configureProvider(Request $request)
    {
        $provider = $request->provider;
        
        $validationRules = [
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack',
            'enabled' => 'required|boolean'
        ];

        switch ($provider) {
            case 'expresspay':
                $validationRules['merchant_id'] = 'required_if:enabled,1|string|min:5';
                $validationRules['api_key'] = 'required_if:enabled,1|string|min:10';
                $validationRules['environment'] = 'required|in:sandbox,production';
                break;
                
            case 'flutterwave':
                $validationRules['public_key'] = 'required_if:enabled,1|string|min:10';
                $validationRules['secret_key'] = 'required_if:enabled,1|string|min:10';
                $validationRules['encryption_key'] = 'nullable|string';
                break;
                
            case 'hubtel':
                $validationRules['client_id'] = 'required_if:enabled,1|string|min:5';
                $validationRules['client_secret'] = 'required_if:enabled,1|string|min:10';
                $validationRules['merchant_account'] = 'nullable|string';
                break;
                
            case 'paystack':
                $validationRules['secret_key'] = 'required_if:enabled,1|string|min:10';
                $validationRules['public_key'] = 'required_if:enabled,1|string|min:10';
                break;
        }

        $validator = Validator::make($request->all(), $validationRules, [
            'environment.in' => 'Environment must be either sandbox or production',
            'merchant_id.required_if' => 'Merchant ID is required when ExpressPay is enabled',
            'api_key.required_if' => 'API Key is required when ExpressPay is enabled',
            'public_key.required_if' => 'Public Key is required when this provider is enabled',
            'secret_key.required_if' => 'Secret Key is required when this provider is enabled'
        ]);

        if ($validator->fails()) {
            Log::warning('Payment provider configuration validation failed', [
                'provider' => $provider,
                'errors' => $validator->errors()->toArray()
            ]);
            
            $errorResponse = [
                'success' => false,
                'message' => 'Please fix the validation errors.',
                'errors' => $validator->errors()->toArray()
            ];
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($errorResponse, 422);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        try {
            $configData = $this->prepareProviderConfig($provider, $request);
            
            $submittedConfig = [
                'provider' => $provider,
                'environment' => $request->environment ?? 'sandbox',
                'enabled' => (bool)$request->enabled,
                'timestamp' => now()->timestamp,
                'has_credentials' => !empty($request->api_key ?? $request->public_key ?? $request->client_id ?? $request->secret_key),
                'is_production' => ($request->environment ?? 'sandbox') === 'production'
            ];
            
            Session::put('submitted_config', $submittedConfig);
            $this->storeExpectedProviderState($provider, $configData);
            
            $updateResult = $this->processPaymentProviderUpdate($configData, $provider);
            
            if ($updateResult['success']) {
                $providerName = $this->getProviderName($provider);
                $isProduction = ($request->environment ?? 'sandbox') === 'production';
                
                $successMessage = "✅ {$providerName} configuration updated successfully!";
                
                if ($isProduction) {
                    $successMessage .= " 🚀 Production environment activated.";
                } else {
                    $successMessage .= " 🧪 Sandbox environment configured for testing.";
                }
                
                Log::info("Payment provider configuration updated successfully", [
                    'provider' => $provider,
                    'enabled' => $request->enabled,
                    'environment' => $request->environment ?? 'sandbox',
                    'is_production' => $isProduction,
                    'api_key_set' => !empty($request->api_key ?? $request->public_key ?? $request->client_id ?? $request->secret_key),
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                $responseData = [
                    'success' => true,
                    'message' => $successMessage,
                    'provider' => $provider,
                    'environment' => $request->environment ?? 'sandbox',
                    'enabled' => (bool)$request->enabled,
                    'is_production' => $isProduction,
                    'submitted_config' => $submittedConfig,
                    'redirect' => route('admin.payment-providers.index')
                ];
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json($responseData);
                }
                
                return redirect()->route('admin.payment-providers.index')
                    ->with('success', $successMessage)
                    ->with('payment_update_status', 'success')
                    ->with('payment_update_provider', $provider)
                    ->with('submitted_config', $submittedConfig);
            } else {
                throw new \Exception($updateResult['message'] ?? 'Failed to update configuration');
            }

        } catch (\Exception $e) {
            Log::error("Failed to configure {$provider}: " . $e->getMessage(), [
                'provider' => $provider,
                'exception' => $e,
                'request_data' => $request->except(['api_key', 'secret_key', 'public_key', 'encryption_key', 'client_secret'])
            ]);
            
            Cache::forget("payment_provider_{$provider}_expected");
            
            $errorResponse = [
                'success' => false,
                'message' => '❌ Failed to update configuration: ' . $e->getMessage()
            ];
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($errorResponse, 500);
            }
            
            return redirect()->back()
                ->with('error', $errorResponse['message'])
                ->withInput();
        }
    }

    /**
     * Get current environment for provider
     */
    protected function getCurrentEnvironment(string $provider)
    {
        switch ($provider) {
            case 'expresspay':
                return env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
            case 'flutterwave':
                $baseUrl = env('FLUTTERWAVE_BASE_URL', '');
                return str_contains($baseUrl, 'api.flutterwave.com') ? 'production' : 'sandbox';
            case 'hubtel':
                $baseUrl = env('HUBTEL_BASE_URL', '');
                return str_contains($baseUrl, 'api.hubtel.com') ? 'production' : 'sandbox';
            case 'paystack':
                $baseUrl = env('PAYSTACK_PAYMENT_URL', '');
                return str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
            default:
                return 'sandbox';
        }
    }

    /**
     * Get environment key for provider
     */
    protected function getEnvironmentKey(string $provider)
    {
        switch ($provider) {
            case 'expresspay':
                return 'EXPRESSPAY_ENVIRONMENT';
            case 'flutterwave':
                return 'FLUTTERWAVE_BASE_URL';
            case 'hubtel':
                return 'HUBTEL_BASE_URL';
            case 'paystack':
                return 'PAYSTACK_PAYMENT_URL';
            default:
                return '';
        }
    }

    /**
     * Store expected provider state for immediate feedback
     */
    protected function storeExpectedProviderState(string $provider, array $configData)
    {
        try {
            $expectedState = [
                'enabled' => $this->getEnabledFromConfig($provider, $configData),
                'configured' => true,
                'environment' => $this->getExpectedEnvironmentFromConfig($provider, $configData),
                'timestamp' => now()->timestamp,
                'job_dispatched' => true,
                'is_production' => $this->getExpectedEnvironmentFromConfig($provider, $configData) === 'production'
            ];
            
            Cache::put("payment_provider_{$provider}_expected", $expectedState, 300);
            
            Log::info("Stored expected provider state", [
                'provider' => $provider,
                'expected_state' => $expectedState
            ]);
            
        } catch (\Exception $e) {
            Log::warning("Failed to store expected provider state: " . $e->getMessage());
        }
    }

    /**
     * Get enabled state from config data
     */
    protected function getEnabledFromConfig(string $provider, array $configData)
    {
        switch ($provider) {
            case 'expresspay':
                return isset($configData['EXPRESSPAY_ENABLED']) && $configData['EXPRESSPAY_ENABLED'] === 'true';
            case 'flutterwave':
                return isset($configData['FLUTTERWAVE_ENABLED']) && $configData['FLUTTERWAVE_ENABLED'] === 'true';
            case 'hubtel':
                return isset($configData['HUBTEL_ENABLED']) && $configData['HUBTEL_ENABLED'] === 'true';
            case 'paystack':
                return isset($configData['PAYSTACK_ENABLED']) && $configData['PAYSTACK_ENABLED'] === 'true';
            default:
                return false;
        }
    }

    /**
     * Get expected environment from config data
     */
    protected function getExpectedEnvironmentFromConfig(string $provider, array $configData)
    {
        switch ($provider) {
            case 'expresspay':
                return $configData['EXPRESSPAY_ENVIRONMENT'] ?? 'sandbox';
            case 'flutterwave':
                $baseUrl = $configData['FLUTTERWAVE_BASE_URL'] ?? '';
                return str_contains($baseUrl, 'api.flutterwave.com') ? 'production' : 'sandbox';
            case 'hubtel':
                $baseUrl = $configData['HUBTEL_BASE_URL'] ?? '';
                return str_contains($baseUrl, 'api.hubtel.com') ? 'production' : 'sandbox';
            case 'paystack':
                $baseUrl = $configData['PAYSTACK_PAYMENT_URL'] ?? '';
                return str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
            default:
                return 'sandbox';
        }
    }

    /**
     * ✅ CRITICAL FIX: Process payment provider update immediately
     */
    protected function processPaymentProviderUpdate(array $configData, string $provider)
    {
        try {
            Log::info('Processing payment provider update immediately', [
                'provider' => $provider,
                'config_keys' => array_keys($configData)
            ]);
            
            $this->storeExpectedProviderState($provider, $configData);
            $this->clearApplicationCaches(true);
            
            $backupPath = $this->backupEnvFile();
            
            $envUpdateResult = $this->updateEnvFile($configData);
            
            if (!$envUpdateResult) {
                throw new \Exception('Failed to update environment file');
            }
            
            $this->forceEnvironmentReload();
            usleep(200000);
            $this->forceEnvironmentReload();
            usleep(200000);
            
            $verification = $this->verifyConfigurationUpdate($provider, $configData);
            
            if (!$verification['success']) {
                $this->restoreEnvBackup();
                throw new \Exception('Configuration verification failed: ' . ($verification['message'] ?? 'Unknown error'));
            }
            
            $this->storeActualProviderState($provider);
            
            return [
                'success' => true,
                'message' => 'Configuration updated successfully',
                'verification' => $verification
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to process payment provider update: " . $e->getMessage(), [
                'provider' => $provider,
                'config_data_keys' => array_keys($configData)
            ]);
            
            $this->restoreEnvBackup();
            Cache::forget("payment_provider_{$provider}_expected");
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify configuration update with detailed logging
     */
    protected function verifyConfigurationUpdate(string $provider, array $configData)
    {
        try {
            $this->forceEnvironmentReload();
            usleep(300000);
            $this->forceEnvironmentReload();
            usleep(200000);
            
            $actualState = $this->getProviderStateFromEnv($provider);
            $expectedEnabled = $this->getEnabledFromConfig($provider, $configData);
            $expectedEnvironment = $this->getExpectedEnvironmentFromConfig($provider, $configData);
            
            $verification = [
                'success' => true,
                'enabled' => $actualState['enabled'],
                'environment' => $actualState['environment'],
                'expected_enabled' => $expectedEnabled,
                'expected_environment' => $expectedEnvironment,
                'enabled_match' => $actualState['enabled'] === $expectedEnabled,
                'environment_match' => $actualState['environment'] === $expectedEnvironment,
                'configured' => $actualState['configured'],
                'timestamp' => now()->timestamp
            ];
            
            if (!$verification['enabled_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Enabled state does not match expected value';
                Log::warning('Enabled state mismatch during verification', [
                    'provider' => $provider,
                    'expected' => $expectedEnabled,
                    'actual' => $actualState['enabled'],
                    'config_data' => $configData
                ]);
            }
            
            if (!$verification['environment_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Environment does not match expected value';
                Log::warning('Environment mismatch during verification', [
                    'provider' => $provider,
                    'expected' => $expectedEnvironment,
                    'actual' => $actualState['environment']
                ]);
            }
            
            if ($verification['success'] && $actualState['environment'] === 'production') {
                Log::info('Production environment successfully verified', [
                    'provider' => $provider,
                    'environment' => $actualState['environment'],
                    'enabled' => $actualState['enabled'],
                    'configured' => $actualState['configured']
                ]);
            }
            
            return $verification;
            
        } catch (\Exception $e) {
            Log::error('Configuration verification failed: ' . $e->getMessage(), [
                'provider' => $provider
            ]);
            
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Store actual provider state with environment info
     */
    protected function storeActualProviderState(string $provider)
    {
        try {
            $actualState = $this->getProviderStateFromEnv($provider);
            $actualState['timestamp'] = now()->timestamp;
            $actualState['verified'] = true;
            $actualState['is_production'] = $actualState['environment'] === 'production';
            
            Cache::put("payment_provider_{$provider}_actual", $actualState, 300);
            Cache::forget("payment_provider_{$provider}_expected");
            
            Log::info('Stored actual provider state', [
                'provider' => $provider,
                'state' => $actualState
            ]);
            
        } catch (\Exception $e) {
            Log::warning("Failed to store actual provider state: " . $e->getMessage());
        }
    }

    /**
     * Get provider state directly from environment with more details
     */
    protected function getProviderStateFromEnv(string $provider)
    {
        $state = [
            'enabled' => false,
            'configured' => false,
            'environment' => 'sandbox',
            'merchant_id_configured' => false,
            'api_key_configured' => false,
            'public_key_configured' => false,
            'secret_key_configured' => false,
            'client_id_configured' => false,
            'client_secret_configured' => false
        ];
        
        switch ($provider) {
            case 'expresspay':
                $state['enabled'] = env('EXPRESSPAY_ENABLED') === 'true';
                $state['environment'] = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
                $state['merchant_id_configured'] = !empty(env('EXPRESSPAY_MERCHANT_ID'));
                $state['api_key_configured'] = !empty(env('EXPRESSPAY_API_KEY'));
                $state['environment_configured'] = !empty(env('EXPRESSPAY_ENVIRONMENT'));
                $state['configured'] = $state['merchant_id_configured'] && 
                                      $state['api_key_configured'] && 
                                      $state['environment_configured'];
                break;
                
            case 'flutterwave':
                $state['enabled'] = env('FLUTTERWAVE_ENABLED') === 'true';
                $state['public_key_configured'] = !empty(env('FLUTTERWAVE_PUBLIC_KEY'));
                $state['secret_key_configured'] = !empty(env('FLUTTERWAVE_SECRET_KEY'));
                $state['encryption_key_configured'] = !empty(env('FLUTTERWAVE_ENCRYPTION_KEY'));
                $state['configured'] = $state['public_key_configured'] && 
                                      $state['secret_key_configured'];
                $baseUrl = env('FLUTTERWAVE_BASE_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.flutterwave.com') ? 'production' : 'sandbox';
                break;
                
            case 'hubtel':
                $state['enabled'] = env('HUBTEL_ENABLED') === 'true';
                $state['client_id_configured'] = !empty(env('HUBTEL_CLIENT_ID'));
                $state['client_secret_configured'] = !empty(env('HUBTEL_CLIENT_SECRET'));
                $state['merchant_account_configured'] = !empty(env('HUBTEL_MERCHANT_ACCOUNT'));
                $state['configured'] = $state['client_id_configured'] && 
                                      $state['client_secret_configured'];
                $baseUrl = env('HUBTEL_BASE_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.hubtel.com') ? 'production' : 'sandbox';
                break;
                
            case 'paystack':
                $enabledValue = env('PAYSTACK_ENABLED');
                $secretKey = env('PAYSTACK_SECRET_KEY');
                $publicKey = env('PAYSTACK_PUBLIC_KEY');
                
                $state['enabled'] = $enabledValue === 'true' || $enabledValue === true;
                $state['secret_key_configured'] = !empty($secretKey) && 
                                                   $secretKey !== 'sk_test_your_secret_key_here' &&
                                                   strlen($secretKey) > 20;
                $state['public_key_configured'] = !empty($publicKey) && 
                                                  $publicKey !== 'pk_test_your_public_key_here' &&
                                                  strlen($publicKey) > 20;
                $state['configured'] = $state['secret_key_configured'] && 
                                      $state['public_key_configured'] && 
                                      $state['enabled'];
                $baseUrl = env('PAYSTACK_PAYMENT_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
                
                Log::info('Paystack state from environment', [
                    'enabled' => $state['enabled'],
                    'enabled_raw' => $enabledValue,
                    'secret_key_configured' => $state['secret_key_configured'],
                    'public_key_configured' => $state['public_key_configured'],
                    'configured' => $state['configured'],
                    'secret_key_length' => strlen($secretKey),
                    'public_key_length' => strlen($publicKey)
                ]);
                break;
        }
        
        $state['missing_configuration'] = $this->getMissingConfiguration($provider, $state);
        
        return $state;
    }

    /**
     * Get missing configuration for provider state
     */
    protected function getMissingConfiguration(string $provider, array $state)
    {
        $missing = [];
        
        switch ($provider) {
            case 'expresspay':
                if (!$state['merchant_id_configured']) $missing[] = 'Merchant ID';
                if (!$state['api_key_configured']) $missing[] = 'API Key';
                if (!$state['environment_configured']) $missing[] = 'Environment';
                break;
                
            case 'flutterwave':
                if (!$state['public_key_configured']) $missing[] = 'Public Key';
                if (!$state['secret_key_configured']) $missing[] = 'Secret Key';
                break;
                
            case 'hubtel':
                if (!$state['client_id_configured']) $missing[] = 'Client ID';
                if (!$state['client_secret_configured']) $missing[] = 'Client Secret';
                break;
                
            case 'paystack':
                if (!$state['secret_key_configured']) $missing[] = 'Secret Key';
                if (!$state['public_key_configured']) $missing[] = 'Public Key';
                break;
        }
        
        return $missing;
    }

    /**
     * Manual retry for failed payment provider updates
     */
    public function retryPaymentUpdate(Request $request)
    {
        try {
            $result = $this->checkPendingPaymentUpdates();
            
            if ($result && $result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'Payment provider configuration updated successfully!',
                    'provider' => $result['provider']
                ];
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json($response);
                }
                
                return redirect()->route('admin.payment-providers.index')
                    ->with('success', 'Payment provider configuration updated successfully!')
                    ->with('payment_update_success', true)
                    ->with('payment_update_message', 'Configuration updated successfully')
                    ->with('payment_update_provider', $result['provider']);
            } else {
                $response = [
                    'success' => false,
                    'message' => 'No pending payment provider updates found.'
                ];
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json($response, 404);
                }
                
                return redirect()->route('admin.payment-providers.index')
                    ->with('info', 'No pending payment provider updates found.')
                    ->with('payment_update_success', null);
            }
                
        } catch (\Exception $e) {
            $errorResponse = [
                'success' => false,
                'message' => 'Error retrying payment provider update: ' . $e->getMessage()
            ];
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($errorResponse, 500);
            }
            
            return redirect()->route('admin.payment-providers.index')
                ->with('error', 'Error retrying payment provider update: ' . $e->getMessage())
                ->with('payment_update_success', false)
                ->with('payment_update_message', $e->getMessage());
        }
    }

    /**
     * Check and process pending payment provider updates
     */
    public function checkPendingPaymentUpdates(Request $request)
    {
        if (!Session::has('pending_payment_update') || Session::get('pending_payment_update.attempted', false)) {
            $response = [
                'success' => false,
                'message' => 'No pending payment provider updates found or update already attempted'
            ];
            
            if ($request && ($request->ajax() || $request->wantsJson())) {
                return response()->json($response, 404);
            }
            
            return $response;
        }

        $pendingUpdate = Session::get('pending_payment_update');
        
        try {
            Session::put('pending_payment_update.attempted', true);
            Session::save();

            $updateResult = $this->processPaymentProviderUpdate(
                $pendingUpdate['config_data'], 
                $pendingUpdate['provider']
            );

            if ($updateResult['success']) {
                Session::forget('pending_payment_update');
                Session::save();
                
                Log::info('Manual retry: Payment provider update completed successfully', [
                    'provider' => $pendingUpdate['provider'],
                    'is_production' => $pendingUpdate['is_production'] ?? false
                ]);
                
                $response = [
                    'success' => true,
                    'message' => 'Payment provider configuration updated successfully!',
                    'provider' => $pendingUpdate['provider']
                ];
                
                if ($request && ($request->ajax() || $request->wantsJson())) {
                    return response()->json($response);
                }
                
                return $response;
            } else {
                Log::warning('Manual retry: Payment provider update failed', [
                    'provider' => $pendingUpdate['provider'],
                    'error' => $updateResult['message'],
                    'is_production' => $pendingUpdate['is_production'] ?? false
                ]);
                
                $response = [
                    'success' => false,
                    'message' => 'Payment provider configuration update failed: ' . $updateResult['message']
                ];
                
                if ($request && ($request->ajax() || $request->wantsJson())) {
                    return response()->json($response, 500);
                }
                
                return $response;
            }
            
        } catch (\Exception $e) {
            Log::error('Manual retry: Payment provider update process failed', [
                'provider' => $pendingUpdate['provider'],
                'error' => $e->getMessage(),
                'is_production' => $pendingUpdate['is_production'] ?? false
            ]);
            
            Session::put('pending_payment_update.attempted', true);
            Session::save();
            
            $response = [
                'success' => false,
                'message' => 'Update process error: ' . $e->getMessage()
            ];
            
            if ($request && ($request->ajax() || $request->wantsJson())) {
                return response()->json($response, 500);
            }
            
            return $response;
        }
    }

    /**
     * Prepare provider configuration data
     */
    protected function prepareProviderConfig($provider, $request)
    {
        $baseConfig = [
            'enabled' => $request->enabled ? 'true' : 'false'
        ];

        switch ($provider) {
            case 'expresspay':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production' ? 
                    'https://api.expresspaygh.com' : 
                    'https://sandbox.expresspaygh.com';
                
                return array_merge($baseConfig, [
                    'EXPRESSPAY_MERCHANT_ID' => $request->merchant_id ?? '',
                    'EXPRESSPAY_API_KEY' => $request->api_key ?? '',
                    'EXPRESSPAY_ENVIRONMENT' => $environment,
                    'EXPRESSPAY_BASE_URL' => $baseUrl,
                    'EXPRESSPAY_ENABLED' => $request->enabled ? 'true' : 'false'
                ]);

            case 'flutterwave':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production' ? 
                    'https://api.flutterwave.com/v3' : 
                    'https://sandbox.flutterwave.com/v3';
                
                return array_merge($baseConfig, [
                    'FLUTTERWAVE_PUBLIC_KEY' => $request->public_key ?? '',
                    'FLUTTERWAVE_SECRET_KEY' => $request->secret_key ?? '',
                    'FLUTTERWAVE_ENCRYPTION_KEY' => $request->encryption_key ?? '',
                    'FLUTTERWAVE_BASE_URL' => $baseUrl,
                    'FLUTTERWAVE_ENABLED' => $request->enabled ? 'true' : 'false'
                ]);

            case 'hubtel':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production' ? 
                    'https://api.hubtel.com/v1' : 
                    'https://sandbox.hubtel.com/v1';
                
                return array_merge($baseConfig, [
                    'HUBTEL_CLIENT_ID' => $request->client_id ?? '',
                    'HUBTEL_CLIENT_SECRET' => $request->client_secret ?? '',
                    'HUBTEL_MERCHANT_ACCOUNT' => $request->merchant_account ?? '',
                    'HUBTEL_BASE_URL' => $baseUrl,
                    'HUBTEL_ENABLED' => $request->enabled ? 'true' : 'false'
                ]);

            case 'paystack':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = 'https://api.paystack.co';
                
                return [
                    'PAYSTACK_SECRET_KEY' => trim($request->secret_key ?? ''),
                    'PAYSTACK_PUBLIC_KEY' => trim($request->public_key ?? ''),
                    'PAYSTACK_PAYMENT_URL' => $baseUrl,
                    'PAYSTACK_ENABLED' => $request->enabled ? 'true' : 'false',
                    'PAYSTACK_WEBHOOK_URL' => url('/api/payments/paystack/webhook')
                ];

            default:
                return $baseConfig;
        }
    }

    /**
     * Cache management methods with environment reload
     */
    protected function clearApplicationCaches(bool $forceReload = false)
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            Cache::flush();
            
            if (File::exists(base_path('bootstrap/cache/packages.php'))) {
                File::delete(base_path('bootstrap/cache/packages.php'));
            }
            if (File::exists(base_path('bootstrap/cache/services.php'))) {
                File::delete(base_path('bootstrap/cache/services.php'));
            }
            
            if ($forceReload) {
                $this->forceEnvironmentReload();
            }
            
            usleep(500000);
            
            Log::debug('Application caches cleared' . ($forceReload ? ' and environment reloaded' : ''));
            
        } catch (\Exception $e) {
            Log::warning('Error clearing caches: ' . $e->getMessage());
        }
    }

    /**
     * Force environment variable reload
     */
    protected function forceEnvironmentReload()
    {
        try {
            $paymentVars = [
                'EXPRESSPAY_ENABLED', 'EXPRESSPAY_MERCHANT_ID', 'EXPRESSPAY_API_KEY', 'EXPRESSPAY_ENVIRONMENT', 'EXPRESSPAY_BASE_URL',
                'FLUTTERWAVE_ENABLED', 'FLUTTERWAVE_PUBLIC_KEY', 'FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_ENCRYPTION_KEY', 'FLUTTERWAVE_BASE_URL',
                'HUBTEL_ENABLED', 'HUBTEL_CLIENT_ID', 'HUBTEL_CLIENT_SECRET', 'HUBTEL_MERCHANT_ACCOUNT', 'HUBTEL_BASE_URL',
                'PAYSTACK_ENABLED', 'PAYSTACK_SECRET_KEY', 'PAYSTACK_PUBLIC_KEY', 'PAYSTACK_PAYMENT_URL'
            ];
            
            foreach ($paymentVars as $var) {
                if (function_exists('putenv')) {
                    putenv($var);
                }
                unset($_ENV[$var], $_SERVER[$var]);
            }
            
            if (file_exists(base_path('.env'))) {
                try {
                    $dotenv = \Dotenv\Dotenv::createUnsafeMutable(base_path());
                    $dotenv->safeLoad();
                    
                    $lines = file(base_path('.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                            list($key, $value) = explode('=', $line, 2);
                            $key = trim($key);
                            $value = trim($value);
                            $value = trim($value, '"\'');
                            
                            if (function_exists('putenv')) {
                                putenv("{$key}={$value}");
                            }
                            $_ENV[$key] = $value;
                            $_SERVER[$key] = $value;
                        }
                    }
                    
                    Log::debug('Environment variables force reloaded');
                } catch (\Exception $e) {
                    Log::warning('Error forcing environment reload: ' . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Error forcing environment reload: ' . $e->getMessage());
        }
    }

    protected function rebuildApplicationCaches()
    {
        try {
            Artisan::call('config:cache');
            usleep(1000000);
        } catch (\Exception $e) {
            Log::warning('Error rebuilding caches: ' . $e->getMessage());
        }
    }

    /**
     * Environment file management
     */
    protected function backupEnvFile()
    {
        try {
            $envPath = base_path('.env');
            $backupPath = base_path('.env.backup.' . date('Y-m-d-H-i-s'));
            
            if (File::exists($envPath)) {
                File::copy($envPath, $backupPath);
                Log::info('.env file backed up to: ' . $backupPath);
                return $backupPath;
            }
        } catch (\Exception $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
        }
        
        return null;
    }

    protected function restoreEnvBackup()
    {
        try {
            $backups = File::glob(base_path('.env.backup.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                File::copy($latestBackup, base_path('.env'));
                Log::info('Restored .env from backup: ' . $latestBackup);
                
                $this->forceEnvironmentReload();
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Update .env file with better error handling
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

        try {
            $envContent = File::get($envPath);
            $updated = false;

            foreach ($data as $key => $value) {
                if ($key === 'enabled') {
                    continue;
                }
                
                $escapedValue = $this->escapeEnvValue($value);
                
                $pattern = "/^{$key}=.*/m";
                $replacement = "{$key}={$escapedValue}";

                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                    $updated = true;
                    
                    Log::debug("Updated existing environment variable: {$key}", [
                        'new_value' => $escapedValue
                    ]);
                } else {
                    $envContent .= "\n{$key}={$escapedValue}";
                    $updated = true;
                    
                    Log::debug("Added new environment variable: {$key}", [
                        'value' => $escapedValue
                    ]);
                }
            }

            if ($updated) {
                File::put($envPath, $envContent);
                
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
                
                Log::info('Updated .env file with payment provider configuration', [
                    'keys_updated' => array_keys(array_diff_key($data, ['enabled' => true])),
                    'contains_production' => in_array('production', array_values($data))
                ]);
                
                return true;
            }
            
            Log::warning('No updates made to .env file');
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to update .env file: ' . $e->getMessage(), [
                'data_keys' => array_keys($data)
            ]);
            throw new \Exception('Environment file update failed: ' . $e->getMessage());
        }
    }

    /**
     * Get current environment value
     */
    protected function getCurrentEnvValue(string $key)
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, $key . '=') === 0) {
                list(, $value) = explode('=', $line, 2);
                return trim($value, '"\'');
            }
        }
        
        return null;
    }

    /**
     * Escape environment value
     */
    protected function escapeEnvValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        if (empty($value)) {
            return '""';
        }
        
        $value = (string) $value;
        
        if ($value === 'production') {
            Log::info('Production environment configuration detected in .env update');
        }
        
        if (preg_match('/[\\s#"\'\\\\=]/', $value)) {
            $value = '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $value) . '"';
        }
        
        return $value;
    }

    /**
     * Test connection
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack'
        ]);

        $provider = $request->provider;
        
        try {
            $this->clearApplicationCaches(true);
            
            $result = $this->testProviderConnection($provider);
            
            if ($result['success']) {
                Log::info("Connection test successful for {$provider}", $result['details'] ?? []);
                return response()->json([
                    'success' => true, 
                    'message' => '✅ Connection test successful! ' . ($result['message'] ?? ''),
                    'details' => $result['details'] ?? [],
                    'environment' => $result['environment'] ?? 'unknown',
                    'is_production' => ($result['environment'] ?? 'unknown') === 'production'
                ]);
            }
            
            Log::warning("Connection test failed for {$provider}: " . $result['message'], $result['details'] ?? []);
            return response()->json([
                'success' => false, 
                'message' => '❌ ' . $result['message'],
                'details' => $result['details'] ?? [],
                'environment' => $result['environment'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            Log::error("Connection test error for {$provider}: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => '❌ Connection test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test provider connection
     */
    protected function testProviderConnection($provider)
    {
        try {
            $this->clearApplicationCaches(true);
            $gatewayStatus = $this->paymentService->checkPaymentMethodConfiguration();
            
            if (!isset($gatewayStatus[$provider])) {
                return [
                    'success' => false,
                    'message' => 'Provider configuration not found',
                    'environment' => 'unknown'
                ];
            }

            $providerConfig = $gatewayStatus[$provider];
            
            if (!$providerConfig['enabled']) {
                return [
                    'success' => false,
                    'message' => 'Provider is not enabled',
                    'environment' => $providerConfig['environment'] ?? 'unknown'
                ];
            }

            if (!$providerConfig['configured']) {
                return [
                    'success' => false,
                    'message' => 'Provider is not properly configured',
                    'details' => [
                        'missing_configuration' => $providerConfig['missing_configuration']
                    ],
                    'environment' => $providerConfig['environment'] ?? 'unknown'
                ];
            }

            switch ($provider) {
                case 'paystack':
                    return $this->testPaystackConnection();
                case 'expresspay':
                    return $this->testExpressPayConnection();
                case 'flutterwave':
                    return $this->testFlutterwaveConnection();
                case 'hubtel':
                    return $this->testHubtelConnection();
                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported provider for connection testing',
                        'environment' => 'unknown'
                    ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Test configuration error: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    protected function testPaystackConnection()
    {
        try {
            $this->clearApplicationCaches(true);
            
            $secretKey = env('PAYSTACK_SECRET_KEY');
            $baseUrl = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
            
            $environment = str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
            $isProduction = $environment === 'production';

            if (empty($secretKey)) {
                return [
                    'success' => false,
                    'message' => 'Paystack secret key is missing',
                    'environment' => $environment,
                    'is_production' => $isProduction
                ];
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey
            ])->timeout(10)->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Paystack API connection successful',
                    'environment' => $environment,
                    'is_production' => $isProduction,
                    'details' => [
                        'status' => $response->status(),
                        'banks_available' => count($response->json()['data'] ?? []),
                        'is_production' => $isProduction
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Paystack API connection failed: ' . ($response->json()['message'] ?? 'Unknown error'),
                    'environment' => $environment,
                    'is_production' => $isProduction,
                    'details' => [
                        'status' => $response->status(),
                        'is_production' => $isProduction
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Paystack connection test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
                'is_production' => false
            ];
        }
    }

    protected function testExpressPayConnection()
    {
        try {
            $merchantId = env('EXPRESSPAY_MERCHANT_ID');
            $apiKey = env('EXPRESSPAY_API_KEY');
            $baseUrl = env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com');
            $environment = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
            $isProduction = $environment === 'production';

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'ExpressPay credentials are missing',
                    'environment' => $environment,
                    'is_production' => $isProduction
                ];
            }

            $isValidFormat = strlen($merchantId) > 5 && strlen($apiKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? 'ExpressPay credentials format is valid' 
                    : 'ExpressPay credentials appear to be invalid',
                'environment' => $environment,
                'is_production' => $isProduction,
                'details' => [
                    'base_url' => $baseUrl,
                    'merchant_id_length' => strlen($merchantId),
                    'api_key_length' => strlen($apiKey),
                    'is_production' => $isProduction
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ExpressPay connection test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
                'is_production' => false
            ];
        }
    }

    protected function testFlutterwaveConnection()
    {
        try {
            $secretKey = env('FLUTTERWAVE_SECRET_KEY');
            $publicKey = env('FLUTTERWAVE_PUBLIC_KEY');
            $baseUrl = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
            $isProduction = str_contains($baseUrl, 'api.flutterwave.com');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success' => false,
                    'message' => 'Flutterwave credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox',
                    'is_production' => $isProduction
                ];
            }

            $isValidFormat = strlen($secretKey) > 10 && strlen($publicKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? 'Flutterwave credentials format is valid' 
                    : 'Flutterwave credentials appear to be invalid',
                'environment' => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
                'details' => [
                    'base_url' => $baseUrl,
                    'secret_key_length' => strlen($secretKey),
                    'public_key_length' => strlen($publicKey),
                    'is_production' => $isProduction
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Flutterwave connection test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
                'is_production' => false
            ];
        }
    }

    protected function testHubtelConnection()
    {
        try {
            $clientId = env('HUBTEL_CLIENT_ID');
            $clientSecret = env('HUBTEL_CLIENT_SECRET');
            $baseUrl = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
            $isProduction = str_contains($baseUrl, 'api.hubtel.com');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => 'Hubtel credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox',
                    'is_production' => $isProduction
                ];
            }

            $isValidFormat = strlen($clientId) > 5 && strlen($clientSecret) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? 'Hubtel credentials format is valid' 
                    : 'Hubtel credentials appear to be invalid',
                'environment' => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
                'details' => [
                    'base_url' => $baseUrl,
                    'client_id_length' => strlen($clientId),
                    'client_secret_length' => strlen($clientSecret),
                    'is_production' => $isProduction
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Hubtel connection test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
                'is_production' => false
            ];
        }
    }

    /**
     * Get provider status
     */
    public function getProviderStatus()
    {
        try {
            $this->clearApplicationCaches(true);
            usleep(500000);
            
            $status = $this->paymentService->checkPaymentMethodConfiguration();
            
            foreach (array_keys($status) as $providerKey) {
                $expectedState = Cache::get("payment_provider_{$providerKey}_expected");
                $actualState = Cache::get("payment_provider_{$providerKey}_actual");
                
                if ($expectedState) {
                    $status[$providerKey]['expected_state'] = $expectedState;
                    $status[$providerKey]['has_pending_update'] = true;
                }
                
                if ($actualState) {
                    $status[$providerKey]['actual_state'] = $actualState;
                    $status[$providerKey]['recently_updated'] = true;
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $status,
                'debug' => [
                    'environment_reloaded' => true,
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get provider status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status: ' . $e->getMessage()
            ]);
        }
    }

   /**
 * Get immediate provider status after update
 */
public function getImmediateProviderStatus(Request $request)
{
    // Clean any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    Log::info('Immediate status request received', [
        'provider' => $request->provider,
        'all_input' => $request->all()
    ]);
    
    try {
        $request->validate([
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack'
        ]);
        
        $provider = $request->provider;
        
        // Clear caches and reload environment
        $this->clearApplicationCaches(true);
        usleep(300000);
        
        // Get fresh status
        $status = $this->paymentService->checkPaymentMethodConfiguration();
        $providerStatus = $status[$provider] ?? [];
        
        Log::info('Provider status retrieved', [
            'provider' => $provider,
            'status' => $providerStatus
        ]);
        
        // Add additional information
        $providerStatus['source'] = 'environment';
        $providerStatus['timestamp'] = now()->timestamp;
        $providerStatus['is_production'] = ($providerStatus['environment'] ?? 'sandbox') === 'production';
        
        // Check if there's a pending state in cache
        $expectedState = Cache::get("payment_provider_{$provider}_expected");
        $actualState = Cache::get("payment_provider_{$provider}_actual");
        
        if ($expectedState) {
            $providerStatus['expected_state'] = $expectedState;
            $providerStatus['has_pending_update'] = true;
        }
        
        if ($actualState) {
            $providerStatus['actual_state'] = $actualState;
            $providerStatus['recently_updated'] = true;
            $providerStatus['actual_environment'] = $actualState['environment'] ?? 'sandbox';
            $providerStatus['actual_is_production'] = ($actualState['environment'] ?? 'sandbox') === 'production';
        }
        
        $responseData = [
            'success' => true,
            'data' => [
                $provider => $providerStatus
            ],
            'debug' => [
                'environment_reloaded' => true,
                'timestamp' => now()->toDateTimeString()
            ]
        ];
        
        Log::info('Immediate status response', [
            'provider' => $provider,
            'response' => $responseData
        ]);
        
        return response()
            ->json($responseData)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('X-Content-Type-Options', 'nosniff');
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning('Immediate status validation failed', [
            'errors' => $e->errors(),
            'provider' => $request->provider
        ]);
        
        return response()
            ->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422)
            ->header('Content-Type', 'application/json; charset=utf-8');
            
    } catch (\Exception $e) {
        Log::error('Failed to get immediate provider status: ' . $e->getMessage(), [
            'provider' => $request->provider ?? 'unknown',
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()
            ->json([
                'success' => false,
                'message' => 'Failed to get immediate provider status: ' . $e->getMessage()
            ], 500)
            ->header('Content-Type', 'application/json; charset=utf-8');
    }
}

    /**
     * Check job status for a payment provider update
     */
    public function checkJobStatus(Request $request)
    {
        try {
            $provider = $request->get('provider');
            $jobId = $request->get('job_id');
            
            if (!$provider || !$jobId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider and job_id are required'
                ], 422);
            }
            
            // Check if job exists in cache
            $jobStatus = Cache::get("payment_provider_job_{$provider}_{$jobId}");
            
            if ($jobStatus) {
                return response()->json([
                    'success' => true,
                    'status' => $jobStatus,
                    'timestamp' => now()->timestamp
                ]);
            }
            
            // Check if there's a pending state in cache
            $expectedState = Cache::get("payment_provider_{$provider}_expected");
            if ($expectedState) {
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Job is still processing',
                    'expected_state' => $expectedState,
                    'timestamp' => now()->timestamp
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Job not found or already completed'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Failed to check job status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check job status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get provider configuration
     */
    public function getProviderConfig(Request $request)
    {
        try {
            $provider = $request->get('provider');
            
            if ($provider) {
                $providers = [
                    'expresspay' => [
                        'name' => 'ExpressPay',
                        'fields' => ['merchant_id', 'api_key', 'environment'],
                        'enabled' => env('EXPRESSPAY_ENABLED', false)
                    ],
                    'flutterwave' => [
                        'name' => 'Flutterwave',
                        'fields' => ['public_key', 'secret_key', 'encryption_key'],
                        'enabled' => env('FLUTTERWAVE_ENABLED', false)
                    ],
                    'hubtel' => [
                        'name' => 'Hubtel',
                        'fields' => ['client_id', 'client_secret', 'merchant_account'],
                        'enabled' => env('HUBTEL_ENABLED', false)
                    ],
                    'paystack' => [
                        'name' => 'Paystack',
                        'fields' => ['secret_key', 'public_key'],
                        'enabled' => env('PAYSTACK_ENABLED', false)
                    ]
                ];
                
                if (isset($providers[$provider])) {
                    $config = $providers[$provider];
                    $config['configured'] = $this->getProviderStateFromEnv($provider)['configured'] ?? false;
                    $config['environment'] = $this->getCurrentEnvironment($provider);
                    
                    return response()->json([
                        'success' => true,
                        'provider' => $provider,
                        'config' => $config
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found'
                ], 404);
            }
            
            // Return all providers
            $allConfigs = [];
            $providers = ['expresspay', 'flutterwave', 'hubtel', 'paystack'];
            foreach ($providers as $p) {
                $allConfigs[$p] = [
                    'name' => $this->getProviderName($p),
                    'enabled' => env(strtoupper($p) . '_ENABLED', false),
                    'configured' => $this->getProviderStateFromEnv($p)['configured'] ?? false,
                    'environment' => $this->getCurrentEnvironment($p)
                ];
            }
            
            return response()->json([
                'success' => true,
                'configs' => $allConfigs
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get provider config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get webhook URLs for providers
     */
    public function getWebhookUrls(Request $request)
    {
        try {
            $urls = [
                'paystack' => url('/api/payments/paystack/webhook'),
                'expresspay' => url('/api/payments/expresspay/webhook'),
                'flutterwave' => url('/api/payments/flutterwave/webhook'),
                'hubtel' => url('/api/payments/hubtel/webhook')
            ];
            
            $provider = $request->get('provider');
            
            if ($provider) {
                if (isset($urls[$provider])) {
                    return response()->json([
                        'success' => true,
                        'provider' => $provider,
                        'webhook_url' => $urls[$provider]
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'webhook_urls' => $urls
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get webhook URLs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook URLs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get update status
     */
    public function getUpdateStatus()
    {
        $status = [
            'has_pending_update' => Session::has('pending_payment_update'),
            'pending_update' => Session::get('pending_payment_update'),
            'last_success' => Session::get('payment_update_success'),
            'last_message' => Session::get('payment_update_message'),
            'last_provider' => Session::get('payment_update_provider')
        ];

        return response()->json([
            'success' => true,
            'status' => $status,
            'timestamp' => now()->timestamp
        ]);
    }

    /**
     * Clear provider states
     */
    public function clearProviderStates(Request $request)
    {
        try {
            $provider = $request->get('provider', 'all');
            
            if ($provider === 'all') {
                $providers = ['expresspay', 'flutterwave', 'hubtel', 'paystack'];
                foreach ($providers as $p) {
                    Cache::forget("payment_provider_{$p}_expected");
                    Cache::forget("payment_provider_{$p}_actual");
                }
                $message = 'Cleared all provider states';
            } else {
                Cache::forget("payment_provider_{$provider}_expected");
                Cache::forget("payment_provider_{$provider}_actual");
                $message = "Cleared states for {$provider}";
            }
            
            Log::info('Cleared provider states', [
                'provider' => $provider,
                'action' => 'manual_clear'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear provider states: ' . $e->getMessage()
            ]);
        }
    }

    protected function getProviderName($providerKey)
    {
        $providers = [
            'expresspay' => 'ExpressPay',
            'flutterwave' => 'Flutterwave',
            'hubtel' => 'Hubtel',
            'paystack' => 'Paystack'
        ];

        return $providers[$providerKey] ?? $providerKey;
    }
    
    /**
     * Reset provider
     */
    public function resetProvider(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack'
        ]);

        $provider = $request->provider;
        
        try {
            $configData = $this->getResetConfig($provider);
            $this->storeExpectedProviderState($provider, $configData);
            
            $updateResult = $this->processPaymentProviderUpdate($configData, $provider);
            
            if ($updateResult['success']) {
                $providerName = $this->getProviderName($provider);
                $successMessage = "✅ {$providerName} configuration reset successfully!";
                
                Log::info("Payment provider reset successfully", [
                    'provider' => $provider,
                    'reset_to_sandbox' => true
                ]);
                
                $responseData = [
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => route('admin.payment-providers.index')
                ];
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json($responseData);
                }
                
                return redirect()->route('admin.payment-providers.index')
                    ->with('success', $successMessage)
                    ->with('payment_update_status', 'success')
                    ->with('payment_update_provider', $provider);
            } else {
                throw new \Exception($updateResult['message']);
            }

        } catch (\Exception $e) {
            Log::error("Failed to reset {$provider}: " . $e->getMessage());
            Cache::forget("payment_provider_{$provider}_expected");
            
            $errorResponse = [
                'success' => false,
                'message' => '❌ Failed to reset configuration: ' . $e->getMessage()
            ];
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($errorResponse, 500);
            }
            
            return redirect()->back()
                ->with('error', $errorResponse['message']);
        }
    }

    /**
     * Get reset configuration for provider
     */
    protected function getResetConfig($provider)
    {
        switch ($provider) {
            case 'expresspay':
                return [
                    'EXPRESSPAY_MERCHANT_ID' => '',
                    'EXPRESSPAY_API_KEY' => '',
                    'EXPRESSPAY_ENVIRONMENT' => 'sandbox',
                    'EXPRESSPAY_BASE_URL' => 'https://sandbox.expresspaygh.com',
                    'EXPRESSPAY_ENABLED' => 'false'
                ];
                
            case 'flutterwave':
                return [
                    'FLUTTERWAVE_PUBLIC_KEY' => '',
                    'FLUTTERWAVE_SECRET_KEY' => '',
                    'FLUTTERWAVE_ENCRYPTION_KEY' => '',
                    'FLUTTERWAVE_BASE_URL' => 'https://sandbox.flutterwave.com/v3',
                    'FLUTTERWAVE_ENABLED' => 'false'
                ];
                
            case 'hubtel':
                return [
                    'HUBTEL_CLIENT_ID' => '',
                    'HUBTEL_CLIENT_SECRET' => '',
                    'HUBTEL_MERCHANT_ACCOUNT' => '',
                    'HUBTEL_BASE_URL' => 'https://sandbox.hubtel.com/v1',
                    'HUBTEL_ENABLED' => 'false'
                ];
                
            case 'paystack':
                return [
                    'PAYSTACK_SECRET_KEY' => '',
                    'PAYSTACK_PUBLIC_KEY' => '',
                    'PAYSTACK_PAYMENT_URL' => 'https://api.paystack.co',
                    'PAYSTACK_ENABLED' => 'false'
                ];
                
            default:
                return [];
        }
    }

    /**
 * Verify environment switch
 */
public function verifyEnvironmentSwitch(Request $request)
{
    // Clean any output buffers to prevent BOM issues
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the incoming request
    Log::info('Verify environment request received', [
        'provider' => $request->provider,
        'all_input' => $request->all(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
    
    try {
        $request->validate([
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack'
        ]);

        $provider = $request->provider;
        
        Log::info('Verifying environment for provider', ['provider' => $provider]);
        
        $this->clearApplicationCaches(true);
        
        $currentEnv = '';
        $isProduction = false;
        
        switch ($provider) {
            case 'expresspay':
                $currentEnv = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
                $isProduction = $currentEnv === 'production';
                break;
                
            case 'flutterwave':
                $currentEnv = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
                $isProduction = str_contains($currentEnv, 'api.flutterwave.com');
                break;
                
            case 'hubtel':
                $currentEnv = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
                $isProduction = str_contains($currentEnv, 'api.hubtel.com');
                break;
                
            case 'paystack':
                $currentEnv = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
                $isProduction = str_contains($currentEnv, 'api.paystack.co');
                break;
            
            default:
                $currentEnv = 'sandbox';
                $isProduction = false;
                break;
        }

        if ($provider === 'expresspay') {
            $displayEnv = $currentEnv;
        } else {
            $displayEnv = $isProduction ? 'production' : 'sandbox';
        }

        $responseData = [
            'success' => true,
            'current_environment' => $displayEnv,
            'is_production' => $isProduction,
            'provider' => $provider,
            'timestamp' => now()->timestamp
        ];
        
        // Log the response data
        Log::info('Environment verification response', $responseData);
        
        // Return JSON with proper headers
        return response()
            ->json($responseData)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('X-Content-Type-Options', 'nosniff');
            
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning('Environment verification validation failed', [
            'errors' => $e->errors(),
            'provider' => $request->provider
        ]);
        
        return response()
            ->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422)
            ->header('Content-Type', 'application/json; charset=utf-8');
            
    } catch (\Exception $e) {
        Log::error('Environment verification failed', [
            'provider' => $request->provider ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()
            ->json([
                'success' => false,
                'message' => 'Failed to verify environment: ' . $e->getMessage()
            ], 500)
            ->header('Content-Type', 'application/json; charset=utf-8');
    }
}

    /**
     * Clean up old .env backups
     */
    public function cleanupBackups()
    {
        try {
            $backups = File::glob(base_path('.env.backup.*'));
            $keepLast = 5;
            
            if (count($backups) > $keepLast) {
                sort($backups);
                $toDelete = array_slice($backups, 0, count($backups) - $keepLast);
                
                foreach ($toDelete as $backup) {
                    File::delete($backup);
                    Log::info('Deleted old backup: ' . $backup);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cleaned up ' . count($toDelete) . ' old backups'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'No old backups to clean up'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clean up backups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean up backups: ' . $e->getMessage()
            ]);
        }
    }
}