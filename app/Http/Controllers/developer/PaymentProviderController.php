<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class PaymentProviderController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * 📊 Index - Display all providers with developer status
     */
    public function index()
    {
        // Get provider definitions
        $providers = $this->getProviderDefinitions();
        
        // Get system status
        $status = $this->paymentService->checkPaymentMethodConfiguration();
        
        // Get developer's own configuration
        $developerConfig = $this->getDeveloperConfig();
        
        // Add status to providers
        foreach ($providers as $key => $provider) {
            if (isset($status[$key])) {
                $providers[$key]['status'] = $status[$key];
                $providers[$key]['debug'] = $this->getDebugInfo($key);
                $providers[$key]['developer_config'] = $developerConfig[$key] ?? null;
            }
        }

        $environment = [
            'app_env' => env('APP_ENV', 'local'),
            'debug_mode' => env('APP_DEBUG', false),
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'can_bill' => env('DEVELOPER_PAYMENT_CAN_BILL', true),
            'is_configured' => $this->isDeveloperConfigured(),
            'access_enabled' => env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true)
        ];

        return view('developer.payments.providers', compact(
            'providers', 
            'environment',
            'developerConfig'
        ));
    }

    /**
     * 💳 Configure Developer's OWN credentials
     */
    public function configureDeveloperProvider(Request $request)
    {
        // Check access
        if (!env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true)) {
            return $this->errorResponse('Developer payment configuration is disabled', 403);
        }

        if (!env('DEVELOPER_PAYMENT_CAN_BILL', true)) {
            return $this->errorResponse('Billing is disabled by system administrator', 403);
        }

        // Validation rules (matches admin controller style)
        $validationRules = [
            'provider' => 'required|in:paystack,expresspay,flutterwave,hubtel',
            'enabled' => 'required|boolean'
        ];

        switch ($request->provider) {
            case 'paystack':
                $validationRules['secret_key'] = 'required|string|min:10';
                $validationRules['public_key'] = 'required|string|min:10';
                break;
            case 'expresspay':
                $validationRules['merchant_id'] = 'required|string|min:5';
                $validationRules['api_key'] = 'required|string|min:10';
                break;
            case 'flutterwave':
                $validationRules['public_key'] = 'required|string|min:10';
                $validationRules['secret_key'] = 'required|string|min:10';
                break;
            case 'hubtel':
                $validationRules['client_id'] = 'required|string|min:5';
                $validationRules['client_secret'] = 'required|string|min:10';
                break;
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            Log::warning('Developer payment validation failed', [
                'developer_id' => auth()->id(),
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $provider = $request->provider;
            
            // Prepare config data (matches admin style)
            $configData = $this->prepareDeveloperConfig($provider, $request);
            
            // Update .env file
            $this->updateDeveloperEnvFile($configData);
            
            // Clear config cache
            Artisan::call('config:clear');
            
            Log::info('Developer updated their own payment credentials', [
                'provider' => $provider,
                'developer_id' => auth()->id(),
                'developer_email' => auth()->user()->email,
                'enabled' => $request->enabled
            ]);

            return response()->json([
                'success' => true,
                'message' => "✅ Your {$provider} credentials have been saved successfully!",
                'provider' => $provider,
                'enabled' => $request->enabled,
                'is_configured' => true,
                'redirect' => route('developer.payment-providers.index')
            ]);

        } catch (\Exception $e) {
            Log::error('Developer failed to update credentials: ' . $e->getMessage(), [
                'developer_id' => auth()->id(),
                'provider' => $request->provider ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🧪 Test Developer's OWN credentials
     */
    public function testDeveloperConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:paystack,expresspay,flutterwave,hubtel'
        ]);

        $provider = $request->provider;

        try {
            // Check if credentials are configured
            if (!$this->isDeveloperConfigured($provider)) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Please configure your credentials first',
                    'environment' => 'unknown'
                ]);
            }

            $result = $this->testDeveloperCredentials($provider);
            
            Log::info('Developer tested their own credentials', [
                'provider' => $provider,
                'success' => $result['success'],
                'developer_id' => auth()->id()
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Developer test failed: ' . $e->getMessage(), [
                'developer_id' => auth()->id(),
                'provider' => $provider
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'provider' => $provider
            ], 500);
        }
    }

    /**
     * 💰 Bill admin using developer's credentials
     */
    public function billAdmin(Request $request)
    {
        if (!env('DEVELOPER_PAYMENT_CAN_BILL', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Billing is disabled'
            ], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'description' => 'required|string|max:255',
            'email' => 'required|email'
        ]);

        try {
            if (!$this->isDeveloperConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Please configure your payment credentials first'
                ]);
            }

            $payment = $this->processDeveloperPayment(
                $request->amount,
                $request->description,
                $request->email
            );

            Log::info('Developer billed admin successfully', [
                'developer_id' => auth()->id(),
                'amount' => $request->amount,
                'reference' => $payment['reference'] ?? 'N/A'
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ Payment processed successfully!',
                'payment' => $payment,
                'redirect_url' => $payment['authorization_url'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Developer billing failed: ' . $e->getMessage(), [
                'developer_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔍 Get Developer Configuration as JSON (API)
     * ✅ RENAMED: Was getDeveloperConfig() - conflict with private method
     */
    public function getDeveloperConfigJson(Request $request)
    {
        try {
            $provider = $request->get('provider');
            $developerConfig = $this->getDeveloperConfig();
            
            if ($provider) {
                if (!isset($developerConfig[$provider])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Provider not found'
                    ], 404);
                }
                
                return response()->json([
                    'success' => true,
                    'provider' => $provider,
                    'config' => $developerConfig[$provider],
                    'can_bill' => env('DEVELOPER_PAYMENT_CAN_BILL', true)
                ]);
            }
            
            return response()->json([
                'success' => true,
                'config' => $developerConfig,
                'can_bill' => env('DEVELOPER_PAYMENT_CAN_BILL', true),
                'access_enabled' => env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true),
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get developer config: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============================================
    // STATUS METHODS (Matches Admin Style)
    // =============================================

    /**
     * 📊 Get provider status
     */
    public function getProviderStatus(Request $request)
    {
        try {
            $this->clearReadCache();
            
            $status = $this->paymentService->checkPaymentMethodConfiguration();
            $developerConfig = $this->getDeveloperConfig();
            
            foreach (array_keys($status) as $providerKey) {
                // Add developer config
                $status[$providerKey]['developer_config'] = $developerConfig[$providerKey] ?? null;
                $status[$providerKey]['developer_configured'] = $this->isDeveloperConfigured($providerKey);
                $status[$providerKey]['can_bill'] = env('DEVELOPER_PAYMENT_CAN_BILL', true);
                
                // Add cache states (matches admin)
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
                'developer_config' => $developerConfig,
                'debug' => [
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get provider status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ⚡ Get immediate provider status
     */
    public function getImmediateProviderStatus(Request $request)
    {
        try {
            $request->validate([
                'provider' => 'required|in:paystack,expresspay,flutterwave,hubtel'
            ]);
            
            $provider = $request->provider;
            
            // Clear caches (matches admin style)
            $this->clearReadCache();
            
            // Get fresh status
            $status = $this->paymentService->checkPaymentMethodConfiguration();
            $providerStatus = $status[$provider] ?? [];
            
            // Add developer-specific info
            $providerStatus['developer_config'] = $this->getDeveloperConfig()[$provider] ?? null;
            $providerStatus['developer_configured'] = $this->isDeveloperConfigured($provider);
            $providerStatus['can_bill'] = env('DEVELOPER_PAYMENT_CAN_BILL', true);
            $providerStatus['source'] = 'environment';
            $providerStatus['timestamp'] = now()->timestamp;
            
            // Check cache states (matches admin)
            $expectedState = Cache::get("payment_provider_{$provider}_expected");
            $actualState = Cache::get("payment_provider_{$provider}_actual");
            
            if ($expectedState) {
                $providerStatus['expected_state'] = $expectedState;
                $providerStatus['has_pending_update'] = true;
            }
            
            if ($actualState) {
                $providerStatus['actual_state'] = $actualState;
                $providerStatus['recently_updated'] = true;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    $provider => $providerStatus
                ],
                'debug' => [
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get immediate provider status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🧪 Test connection (system credentials - read-only)
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:expresspay,flutterwave,hubtel,paystack'
        ]);

        $provider = $request->provider;
        
        try {
            $useDeveloperCredentials = $request->get('use_developer', false);
            
            if ($useDeveloperCredentials) {
                $result = $this->testDeveloperCredentials($provider);
            } else {
                $result = $this->testSystemCredentials($provider);
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'provider' => $provider
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Get provider definitions (matches admin)
     */
    private function getProviderDefinitions()
    {
        return [
            'paystack' => [
                'name' => 'Paystack',
                'fields' => [
                    'secret_key' => 'Secret Key',
                    'public_key' => 'Public Key'
                ],
                'env_keys' => [
                    'PAYSTACK_SECRET_KEY',
                    'PAYSTACK_PUBLIC_KEY'
                ],
                'enabled' => env('PAYSTACK_ENABLED', false)
            ],
            'expresspay' => [
                'name' => 'ExpressPay',
                'fields' => [
                    'merchant_id' => 'Merchant ID',
                    'api_key' => 'API Key',
                    'environment' => 'Environment'
                ],
                'env_keys' => [
                    'EXPRESSPAY_MERCHANT_ID',
                    'EXPRESSPAY_API_KEY',
                    'EXPRESSPAY_ENVIRONMENT'
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
                'env_keys' => [
                    'FLUTTERWAVE_PUBLIC_KEY',
                    'FLUTTERWAVE_SECRET_KEY',
                    'FLUTTERWAVE_ENCRYPTION_KEY'
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
                'env_keys' => [
                    'HUBTEL_CLIENT_ID',
                    'HUBTEL_CLIENT_SECRET',
                    'HUBTEL_MERCHANT_ACCOUNT'
                ],
                'enabled' => env('HUBTEL_ENABLED', false)
            ]
        ];
    }

    /**
     * ✅ Get developer's own configuration (PRIVATE - returns array)
     */
    private function getDeveloperConfig()
    {
        return [
            'paystack' => [
                'secret_key' => env('DEVELOPER_PAYSTACK_SECRET_KEY', ''),
                'public_key' => env('DEVELOPER_PAYSTACK_PUBLIC_KEY', ''),
                'enabled' => env('DEVELOPER_PAYSTACK_ENABLED', false),
                'is_configured' => $this->isDeveloperConfigured('paystack')
            ],
            'expresspay' => [
                'merchant_id' => env('DEVELOPER_EXPRESSPAY_MERCHANT_ID', ''),
                'api_key' => env('DEVELOPER_EXPRESSPAY_API_KEY', ''),
                'enabled' => env('DEVELOPER_EXPRESSPAY_ENABLED', false),
                'is_configured' => $this->isDeveloperConfigured('expresspay')
            ],
            'flutterwave' => [
                'public_key' => env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY', ''),
                'secret_key' => env('DEVELOPER_FLUTTERWAVE_SECRET_KEY', ''),
                'enabled' => env('DEVELOPER_FLUTTERWAVE_ENABLED', false),
                'is_configured' => $this->isDeveloperConfigured('flutterwave')
            ],
            'hubtel' => [
                'client_id' => env('DEVELOPER_HUBTEL_CLIENT_ID', ''),
                'client_secret' => env('DEVELOPER_HUBTEL_CLIENT_SECRET', ''),
                'enabled' => env('DEVELOPER_HUBTEL_ENABLED', false),
                'is_configured' => $this->isDeveloperConfigured('hubtel')
            ],
            'can_bill' => env('DEVELOPER_PAYMENT_CAN_BILL', true),
            'access_enabled' => env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true)
        ];
    }

    /**
     * ✅ Check if developer has configured their credentials
     */
    private function isDeveloperConfigured($provider = null)
    {
        if ($provider === 'paystack' || $provider === null) {
            if (!empty(env('DEVELOPER_PAYSTACK_SECRET_KEY')) && 
                !empty(env('DEVELOPER_PAYSTACK_PUBLIC_KEY'))) {
                return true;
            }
        }
        
        if ($provider === 'expresspay' || $provider === null) {
            if (!empty(env('DEVELOPER_EXPRESSPAY_MERCHANT_ID')) && 
                !empty(env('DEVELOPER_EXPRESSPAY_API_KEY'))) {
                return true;
            }
        }
        
        if ($provider === 'flutterwave' || $provider === null) {
            if (!empty(env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY')) && 
                !empty(env('DEVELOPER_FLUTTERWAVE_SECRET_KEY'))) {
                return true;
            }
        }
        
        if ($provider === 'hubtel' || $provider === null) {
            if (!empty(env('DEVELOPER_HUBTEL_CLIENT_ID')) && 
                !empty(env('DEVELOPER_HUBTEL_CLIENT_SECRET'))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Prepare developer configuration data
     */
    private function prepareDeveloperConfig($provider, $request)
    {
        switch ($provider) {
            case 'paystack':
                return [
                    'DEVELOPER_PAYSTACK_SECRET_KEY' => trim($request->secret_key),
                    'DEVELOPER_PAYSTACK_PUBLIC_KEY' => trim($request->public_key),
                    'DEVELOPER_PAYSTACK_ENABLED' => $request->enabled ? 'true' : 'false',
                    'DEVELOPER_PAYMENT_CAN_BILL' => 'true'
                ];
            case 'expresspay':
                return [
                    'DEVELOPER_EXPRESSPAY_MERCHANT_ID' => trim($request->merchant_id ?? ''),
                    'DEVELOPER_EXPRESSPAY_API_KEY' => trim($request->api_key ?? ''),
                    'DEVELOPER_EXPRESSPAY_ENABLED' => $request->enabled ? 'true' : 'false',
                    'DEVELOPER_PAYMENT_CAN_BILL' => 'true'
                ];
            case 'flutterwave':
                return [
                    'DEVELOPER_FLUTTERWAVE_PUBLIC_KEY' => trim($request->public_key ?? ''),
                    'DEVELOPER_FLUTTERWAVE_SECRET_KEY' => trim($request->secret_key ?? ''),
                    'DEVELOPER_FLUTTERWAVE_ENCRYPTION_KEY' => trim($request->encryption_key ?? ''),
                    'DEVELOPER_FLUTTERWAVE_ENABLED' => $request->enabled ? 'true' : 'false',
                    'DEVELOPER_PAYMENT_CAN_BILL' => 'true'
                ];
            case 'hubtel':
                return [
                    'DEVELOPER_HUBTEL_CLIENT_ID' => trim($request->client_id ?? ''),
                    'DEVELOPER_HUBTEL_CLIENT_SECRET' => trim($request->client_secret ?? ''),
                    'DEVELOPER_HUBTEL_MERCHANT_ACCOUNT' => trim($request->merchant_account ?? ''),
                    'DEVELOPER_HUBTEL_ENABLED' => $request->enabled ? 'true' : 'false',
                    'DEVELOPER_PAYMENT_CAN_BILL' => 'true'
                ];
            default:
                return [];
        }
    }

    /**
     * Update .env file with developer configuration
     */
    private function updateDeveloperEnvFile(array $data)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable');
        }

        $envContent = File::get($envPath);

        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'DEVELOPER_')) {
                continue;
            }
            
            $escapedValue = $this->escapeEnvValue($value);
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$escapedValue}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
            }
        }

        File::put($envPath, $envContent);
    }

    /**
     * Test developer's own credentials
     */
    private function testDeveloperCredentials($provider)
    {
        switch ($provider) {
            case 'paystack':
                return $this->testDeveloperPaystack();
            case 'expresspay':
                return $this->testDeveloperExpressPay();
            case 'flutterwave':
                return $this->testDeveloperFlutterwave();
            case 'hubtel':
                return $this->testDeveloperHubtel();
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported provider',
                    'environment' => 'unknown'
                ];
        }
    }

    /**
     * Test system credentials (read-only)
     */
    private function testSystemCredentials($provider)
    {
        switch ($provider) {
            case 'paystack':
                return $this->testSystemPaystack();
            case 'expresspay':
                return $this->testSystemExpressPay();
            case 'flutterwave':
                return $this->testSystemFlutterwave();
            case 'hubtel':
                return $this->testSystemHubtel();
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported provider',
                    'environment' => 'unknown'
                ];
        }
    }

    /**
     * Test system Paystack credentials (read-only)
     */
    private function testSystemPaystack()
    {
        try {
            $secretKey = env('PAYSTACK_SECRET_KEY');
            $baseUrl = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
            $environment = str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';

            if (empty($secretKey)) {
                return [
                    'success' => false,
                    'message' => 'System Paystack secret key is missing',
                    'environment' => $environment
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey
            ])->timeout(5)->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => '✅ System Paystack connection successful!',
                    'environment' => $environment,
                    'is_production' => $environment === 'production',
                    'details' => [
                        'banks_available' => count($response->json()['data'] ?? [])
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'System Paystack connection failed: ' . ($response->json()['message'] ?? 'Unknown error'),
                    'environment' => $environment
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'System Paystack test failed: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test system ExpressPay credentials (read-only)
     */
    private function testSystemExpressPay()
    {
        try {
            $merchantId = env('EXPRESSPAY_MERCHANT_ID');
            $apiKey = env('EXPRESSPAY_API_KEY');
            $environment = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'System ExpressPay credentials are missing',
                    'environment' => $environment
                ];
            }

            $isValidFormat = strlen($merchantId) > 5 && strlen($apiKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ System ExpressPay credentials format is valid' 
                    : '⚠️ System ExpressPay credentials appear to be invalid',
                'environment' => $environment,
                'is_production' => $environment === 'production'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'System ExpressPay test failed: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test system Flutterwave credentials (read-only)
     */
    private function testSystemFlutterwave()
    {
        try {
            $secretKey = env('FLUTTERWAVE_SECRET_KEY');
            $publicKey = env('FLUTTERWAVE_PUBLIC_KEY');
            $baseUrl = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
            $isProduction = str_contains($baseUrl, 'api.flutterwave.com');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success' => false,
                    'message' => 'System Flutterwave credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox'
                ];
            }

            $isValidFormat = strlen($secretKey) > 10 && strlen($publicKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ System Flutterwave credentials format is valid' 
                    : '⚠️ System Flutterwave credentials appear to be invalid',
                'environment' => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'System Flutterwave test failed: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test system Hubtel credentials (read-only)
     */
    private function testSystemHubtel()
    {
        try {
            $clientId = env('HUBTEL_CLIENT_ID');
            $clientSecret = env('HUBTEL_CLIENT_SECRET');
            $baseUrl = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
            $isProduction = str_contains($baseUrl, 'api.hubtel.com');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => 'System Hubtel credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox'
                ];
            }

            $isValidFormat = strlen($clientId) > 5 && strlen($clientSecret) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ System Hubtel credentials format is valid' 
                    : '⚠️ System Hubtel credentials appear to be invalid',
                'environment' => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'System Hubtel test failed: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test developer's Paystack credentials
     */
    private function testDeveloperPaystack()
    {
        try {
            $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');
            $publicKey = env('DEVELOPER_PAYSTACK_PUBLIC_KEY');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success' => false,
                    'message' => '❌ Please configure your Paystack credentials first',
                    'environment' => 'unknown'
                ];
            }

            $environment = strpos($secretKey, 'sk_live_') !== false ? 'production' : 'test';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey
            ])->timeout(10)->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => '✅ Your Paystack credentials are valid!',
                    'environment' => $environment,
                    'is_production' => $environment === 'production',
                    'details' => [
                        'banks_available' => count($response->json()['data'] ?? [])
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '❌ Your Paystack credentials are invalid: ' . ($response->json()['message'] ?? 'Unknown error'),
                    'environment' => $environment
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test developer's ExpressPay credentials
     */
    private function testDeveloperExpressPay()
    {
        try {
            $merchantId = env('DEVELOPER_EXPRESSPAY_MERCHANT_ID');
            $apiKey = env('DEVELOPER_EXPRESSPAY_API_KEY');

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => '❌ Please configure your ExpressPay credentials first',
                    'environment' => 'unknown'
                ];
            }

            $isValidFormat = strlen($merchantId) > 5 && strlen($apiKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ Your ExpressPay credentials format is valid' 
                    : '⚠️ Your ExpressPay credentials appear to be invalid',
                'environment' => 'sandbox',
                'is_production' => false
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test developer's Flutterwave credentials
     */
    private function testDeveloperFlutterwave()
    {
        try {
            $secretKey = env('DEVELOPER_FLUTTERWAVE_SECRET_KEY');
            $publicKey = env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success' => false,
                    'message' => '❌ Please configure your Flutterwave credentials first',
                    'environment' => 'unknown'
                ];
            }

            $isValidFormat = strlen($secretKey) > 10 && strlen($publicKey) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ Your Flutterwave credentials format is valid' 
                    : '⚠️ Your Flutterwave credentials appear to be invalid',
                'environment' => 'sandbox',
                'is_production' => false
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Test developer's Hubtel credentials
     */
    private function testDeveloperHubtel()
    {
        try {
            $clientId = env('DEVELOPER_HUBTEL_CLIENT_ID');
            $clientSecret = env('DEVELOPER_HUBTEL_CLIENT_SECRET');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => '❌ Please configure your Hubtel credentials first',
                    'environment' => 'unknown'
                ];
            }

            $isValidFormat = strlen($clientId) > 5 && strlen($clientSecret) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? '✅ Your Hubtel credentials format is valid' 
                    : '⚠️ Your Hubtel credentials appear to be invalid',
                'environment' => 'sandbox',
                'is_production' => false
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown'
            ];
        }
    }

    /**
     * Process payment using developer's credentials
     */
    private function processDeveloperPayment($amount, $description, $email)
    {
        $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');
        
        if (empty($secretKey)) {
            throw new \Exception('Developer Paystack secret key is not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey
        ])->post('https://api.paystack.co/transaction/initialize', [
            'amount' => $amount * 100,
            'email' => $email,
            'metadata' => [
                'developer_id' => auth()->id(),
                'developer_email' => auth()->user()->email,
                'description' => $description,
                'type' => 'developer_billing'
            ],
            'callback_url' => route('developer.billing.callback')
        ]);

        if (!$response->successful()) {
            throw new \Exception('Payment initialization failed: ' . ($response->json()['message'] ?? 'Unknown error'));
        }

        $data = $response->json()['data'] ?? [];
        
        return [
            'reference' => $data['reference'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'amount' => $amount,
            'email' => $email,
            'description' => $description
        ];
    }

    /**
     * Escape environment value (matches admin)
     */
    private function escapeEnvValue($value)
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
        
        if (preg_match('/[\\s#"\'\\\\=]/', $value)) {
            $value = '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $value) . '"';
        }
        
        return $value;
    }

    /**
     * Get debug information (matches admin)
     */
    private function getDebugInfo($provider)
    {
        $info = [
            'environment' => $this->detectEnvironment($provider),
            'env_file_exists' => file_exists(base_path('.env')),
            'env_file_writable' => is_writable(base_path('.env')),
            'cache_driver' => config('cache.default'),
            'php_extensions' => $this->getRequiredExtensions(),
            'timestamp' => now()->toDateTimeString()
        ];
        
        switch ($provider) {
            case 'paystack':
                $info['api_version'] = 'v1';
                $info['base_url'] = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
                $info['has_secret_key'] = !empty(env('PAYSTACK_SECRET_KEY'));
                $info['has_public_key'] = !empty(env('PAYSTACK_PUBLIC_KEY'));
                break;
            case 'expresspay':
                $info['base_url'] = env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com');
                $info['environment'] = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
                break;
            case 'flutterwave':
                $info['base_url'] = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
                break;
            case 'hubtel':
                $info['base_url'] = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
                break;
        }
        
        return $info;
    }

    /**
     * Detect environment (matches admin)
     */
    private function detectEnvironment($provider)
    {
        switch ($provider) {
            case 'expresspay':
                return env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
            case 'flutterwave':
                return str_contains(env('FLUTTERWAVE_BASE_URL', ''), 'api.flutterwave.com') 
                    ? 'production' : 'sandbox';
            case 'hubtel':
                return str_contains(env('HUBTEL_BASE_URL', ''), 'api.hubtel.com') 
                    ? 'production' : 'sandbox';
            case 'paystack':
                return str_contains(env('PAYSTACK_PAYMENT_URL', ''), 'api.paystack.co') 
                    ? 'production' : 'sandbox';
            default:
                return 'sandbox';
        }
    }

    /**
     * Get required PHP extensions (matches admin)
     */
    private function getRequiredExtensions()
    {
        $extensions = ['curl', 'json', 'mbstring', 'openssl'];
        $status = [];
        foreach ($extensions as $ext) {
            $status[$ext] = extension_loaded($ext);
        }
        return $status;
    }

    /**
     * Clear read cache
     */
    private function clearReadCache()
    {
        Cache::forget('developer_provider_status_cache');
    }

    /**
     * Error response helper
     */
    private function errorResponse($message, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }
}