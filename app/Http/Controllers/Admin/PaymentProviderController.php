<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentConfigurationService;
use App\Services\PaymentService;
use App\Support\PaymentProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class PaymentProviderController extends Controller
{
    /**
     * Retention policy for `.env` backups. Backups are a safety net for
     * atomic-save rollback, not a version history — keep the newest few,
     * drop anything older than this window.
     */
    private const ENV_BACKUP_KEEP_RECENT  = 5;
    private const ENV_BACKUP_MAX_AGE_HOURS = 24;

    protected PaymentService $paymentService;
    protected PaymentConfigurationService $configService;

    public function __construct(
        PaymentService $paymentService,
        PaymentConfigurationService $configService
    ) {
        $this->paymentService = $paymentService;
        $this->configService  = $configService;

        // NOTE: Do NOT call $this->middleware(...) here.
        // Laravel 11's base Controller has no middleware() method.
        // Authorization is enforced per-method via $this->authorize('manage-payments')
        // and at the route layer via the `can:manage-payments` middleware.
    }

    // =========================================================================
    // JSON DETECTION HELPER
    // =========================================================================

    protected function wantsJsonResponse(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->header('Accept'), 'application/json');
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $this->authorize('manage-payments');

        $providers = [];
        foreach (PaymentProviderRegistry::all() as $key => $meta) {
            $providers[$key] = [
                'name'    => $meta['name'],
                'fields'  => $this->fieldsFor($key),
                'enabled' => (bool) env($meta['env_enabled'], false),
            ];
        }

        $providerStates = [];
        foreach (array_keys($providers) as $providerKey) {
            $expectedState = Cache::get("payment_provider_{$providerKey}_expected");
            $actualState   = Cache::get("payment_provider_{$providerKey}_actual");

            if ($expectedState || $actualState) {
                $providerStates[$providerKey] = [
                    'expected'          => $expectedState,
                    'actual'            => $actualState,
                    'has_pending_state' => !empty($expectedState) || !empty($actualState),
                ];
            }
        }

        $configurationStatus = $this->getConfigurationStatusWithReload();
        $hasPendingUpdate    = session()->has('pending_payment_update');
        $pendingUpdate       = session()->get('pending_payment_update', null);
        $updateStatus        = [
            'success'  => session('payment_update_success'),
            'message'  => session('payment_update_message'),
            'provider' => session('payment_update_provider'),
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

    // =========================================================================
    // CONFIGURE
    // =========================================================================

    public function configureProvider(Request $request)
    {
        $this->authorize('manage-payments');

        $provider = $request->input('provider');

        $validationRules = [
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
            'enabled'  => ['required', 'boolean'],
        ];

        switch ($provider) {
            case 'expresspay':
                $validationRules['merchant_id']  = 'required_if:enabled,1|string|min:5';
                $validationRules['api_key']      = 'required_if:enabled,1|string|min:10';
                $validationRules['environment']  = 'required|in:sandbox,production';
                $validationRules['callback_url'] = 'nullable|url|max:255';
                break;

            case 'flutterwave':
                $validationRules['public_key']     = 'required_if:enabled,1|string|min:10';
                $validationRules['secret_key']     = 'required_if:enabled,1|string|min:10';
                $validationRules['encryption_key'] = 'nullable|string';
                break;

            case 'hubtel':
                $validationRules['client_id']        = 'required_if:enabled,1|string|min:5';
                $validationRules['client_secret']    = 'required_if:enabled,1|string|min:10';
                $validationRules['merchant_account'] = 'nullable|string';
                break;

            case 'paystack':
                $validationRules['secret_key']     = 'required_if:enabled,1|string|min:10';
                $validationRules['public_key']     = 'required_if:enabled,1|string|min:10';
                $validationRules['merchant_id']    = 'nullable|string|max:255';
                $validationRules['webhook_secret'] = 'nullable|string|max:255';
                break;
        }

        $validator = Validator::make($request->all(), $validationRules, [
            'environment.in'          => 'Environment must be either sandbox or production',
            'merchant_id.required_if' => 'Merchant ID is required when ExpressPay is enabled',
            'api_key.required_if'     => 'API Key is required when ExpressPay is enabled',
            'public_key.required_if'  => 'Public Key is required when this provider is enabled',
            'secret_key.required_if'  => 'Secret Key is required when this provider is enabled',
            'callback_url.url'        => 'The callback URL must be a valid HTTPS URL.',
        ]);

        if ($validator->fails()) {
            Log::warning('Payment provider configuration validation failed', [
                'provider' => $provider,
                'errors'   => $validator->errors()->toArray(),
            ]);

            $errorResponse = [
                'success' => false,
                'message' => 'Please fix the validation errors.',
                'errors'  => $validator->errors()->toArray(),
            ];

            if ($this->wantsJsonResponse($request)) {
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
                'provider'        => $provider,
                'environment'     => $request->environment ?? 'sandbox',
                'enabled'         => (bool) $request->enabled,
                'timestamp'       => now()->timestamp,
                'has_credentials' => $this->hasAnyCredential($request),
                'is_production'   => ($request->environment ?? 'sandbox') === 'production',
            ];

            Session::put('submitted_config', $submittedConfig);

            $updateResult = $this->processPaymentProviderUpdate($configData, $provider);

            if ($updateResult['success']) {
                $providerName = PaymentProviderRegistry::name($provider);
                $isProduction = ($request->environment ?? 'sandbox') === 'production';

                $successMessage = "✅ {$providerName} configuration updated successfully!";
                $successMessage .= $isProduction
                    ? ' 🚀 Production environment activated.'
                    : ' 🧪 Sandbox environment configured for testing.';

                Log::info('Payment provider configuration updated successfully', [
                    'provider'        => $provider,
                    'enabled'         => $request->enabled,
                    'environment'     => $request->environment ?? 'sandbox',
                    'is_production'   => $isProduction,
                    'has_credentials' => $this->hasAnyCredential($request),
                    'timestamp'       => now()->toDateTimeString(),
                ]);

                $responseData = [
                    'success'          => true,
                    'message'          => $successMessage,
                    'provider'         => $provider,
                    'environment'      => $request->environment ?? 'sandbox',
                    'enabled'          => (bool) $request->enabled,
                    'is_production'    => $isProduction,
                    'submitted_config' => $submittedConfig,
                    'redirect'         => route('admin.payment-providers.index'),
                ];

                if ($this->wantsJsonResponse($request)) {
                    return response()->json($responseData);
                }

                return redirect()->route('admin.payment-providers.index')
                    ->with('success', $successMessage)
                    ->with('payment_update_status', 'success')
                    ->with('payment_update_provider', $provider)
                    ->with('submitted_config', $submittedConfig);
            }

            throw new \Exception($updateResult['message'] ?? 'Failed to update configuration');
        } catch (\Throwable $e) {
            Log::error("Failed to configure {$provider}: " . $e->getMessage(), [
                'provider'     => $provider,
                'exception'    => get_class($e),
                'request_keys' => array_keys($request->except([
                    'api_key', 'secret_key', 'public_key', 'encryption_key',
                    'client_secret', 'merchant_id', 'client_id',
                    'webhook_secret', 'merchant_account', 'callback_url',
                ])),
            ]);

            Cache::forget("payment_provider_{$provider}_expected");

            $errorResponse = [
                'success' => false,
                'message' => '❌ Failed to update configuration: ' . $e->getMessage(),
            ];

            if ($this->wantsJsonResponse($request)) {
                return response()->json($errorResponse, 500);
            }

            return redirect()->back()
                ->with('error', $errorResponse['message'])
                ->withInput();
        }
    }

    // =========================================================================
    // RESET
    // =========================================================================

    public function resetProvider(Request $request)
    {
        $this->authorize('manage-payments');

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $configData   = $this->getResetConfig($provider);
            $updateResult = $this->processPaymentProviderUpdate($configData, $provider);

            if ($updateResult['success']) {
                $providerName   = PaymentProviderRegistry::name($provider);
                $successMessage = "✅ {$providerName} configuration reset successfully!";

                Log::info('Payment provider reset successfully', [
                    'provider'         => $provider,
                    'reset_to_sandbox' => true,
                ]);

                $responseData = [
                    'success'  => true,
                    'message'  => $successMessage,
                    'redirect' => route('admin.payment-providers.index'),
                ];

                if ($this->wantsJsonResponse($request)) {
                    return response()->json($responseData);
                }

                return redirect()->route('admin.payment-providers.index')
                    ->with('success', $successMessage)
                    ->with('payment_update_status', 'success')
                    ->with('payment_update_provider', $provider);
            }

            throw new \Exception($updateResult['message']);
        } catch (\Throwable $e) {
            Log::error("Failed to reset {$provider}: " . $e->getMessage());
            Cache::forget("payment_provider_{$provider}_expected");

            $errorResponse = [
                'success' => false,
                'message' => '❌ Failed to reset configuration: ' . $e->getMessage(),
            ];

            if ($this->wantsJsonResponse($request)) {
                return response()->json($errorResponse, 500);
            }

            return redirect()->back()->with('error', $errorResponse['message']);
        }
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    public function getProviderStatus(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $this->clearApplicationCaches(true);
            $this->settle();

            $status = $this->paymentService->checkPaymentMethodConfiguration();

            foreach (array_keys($status) as $providerKey) {
                $expectedState = Cache::get("payment_provider_{$providerKey}_expected");
                $actualState   = Cache::get("payment_provider_{$providerKey}_actual");

                if ($expectedState) {
                    $status[$providerKey]['expected_state']     = $expectedState;
                    $status[$providerKey]['has_pending_update'] = true;
                }

                if ($actualState) {
                    $status[$providerKey]['actual_state']     = $actualState;
                    $status[$providerKey]['recently_updated'] = true;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $status,
                'debug'   => [
                    'environment_reloaded' => true,
                    'timestamp'            => now()->toDateTimeString(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get provider status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getImmediateProviderStatus(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $this->clearApplicationCaches(true);
            $this->settle(300000);

            $status         = $this->paymentService->checkPaymentMethodConfiguration();
            $providerStatus = $status[$provider] ?? [];

            $providerStatus['source']        = 'environment';
            $providerStatus['timestamp']     = now()->timestamp;
            $providerStatus['is_production'] = ($providerStatus['environment'] ?? 'sandbox') === 'production';

            if ($expected = Cache::get("payment_provider_{$provider}_expected")) {
                $providerStatus['expected_state']     = $expected;
                $providerStatus['has_pending_update'] = true;
            }

            if ($actual = Cache::get("payment_provider_{$provider}_actual")) {
                $providerStatus['actual_state']         = $actual;
                $providerStatus['recently_updated']     = true;
                $providerStatus['actual_environment']   = $actual['environment'] ?? 'sandbox';
                $providerStatus['actual_is_production'] = ($actual['environment'] ?? 'sandbox') === 'production';
            }

            return response()
                ->json([
                    'success' => true,
                    'data'    => [$provider => $providerStatus],
                    'debug'   => [
                        'environment_reloaded' => true,
                        'timestamp'            => now()->toDateTimeString(),
                    ],
                ])
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $e) {
            Log::error('Failed to get immediate provider status: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()
                ->json([
                    'success' => false,
                    'message' => 'Failed to get immediate provider status: ' . $e->getMessage(),
                ], 500)
                ->header('Content-Type', 'application/json; charset=utf-8');
        }
    }

    public function getUpdateStatus(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        $status = [
            'has_pending_update' => Session::has('pending_payment_update'),
            'pending_update'     => Session::get('pending_payment_update'),
            'last_success'       => Session::get('payment_update_success'),
            'last_message'       => Session::get('payment_update_message'),
            'last_provider'      => Session::get('payment_update_provider'),
            // ✅ NEW: expose backup count so the UI can display it if desired.
            'backup_count'       => count(File::glob(
                $this->getEnvBackupDir() . DIRECTORY_SEPARATOR . '.env.backup.*'
            )),
        ];

        return response()->json([
            'success'   => true,
            'status'    => $status,
            'timestamp' => now()->timestamp,
        ]);
    }

    // =========================================================================
    // CONNECTION TESTING
    // =========================================================================

    public function testConnection(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $this->clearApplicationCaches(true);
            $result = $this->testProviderConnection($provider);

            if ($result['success']) {
                Log::info("Connection test successful for {$provider}", $result['details'] ?? []);
                return response()->json([
                    'success'       => true,
                    'message'       => '✅ Connection test successful! ' . ($result['message'] ?? ''),
                    'details'       => $result['details'] ?? [],
                    'environment'   => $result['environment'] ?? 'unknown',
                    'is_production' => ($result['environment'] ?? 'unknown') === 'production',
                ]);
            }

            Log::warning("Connection test failed for {$provider}: " . $result['message'], $result['details'] ?? []);
            return response()->json([
                'success'     => false,
                'message'     => '❌ ' . $result['message'],
                'details'     => $result['details'] ?? [],
                'environment' => $result['environment'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error("Connection test error for {$provider}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '❌ Connection test failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function verifyEnvironmentSwitch(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $this->clearApplicationCaches(true);

            [$displayEnv, $isProduction] = $this->readEnvironmentFor($provider);

            return response()
                ->json([
                    'success'             => true,
                    'current_environment' => $displayEnv,
                    'is_production'       => $isProduction,
                    'provider'            => $provider,
                    'timestamp'           => now()->timestamp,
                ])
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $e) {
            Log::error('Environment verification failed', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            return response()
                ->json([
                    'success' => false,
                    'message' => 'Failed to verify environment: ' . $e->getMessage(),
                ], 500)
                ->header('Content-Type', 'application/json; charset=utf-8');
        }
    }

    // =========================================================================
    // JOB / RETRY
    // =========================================================================

    public function retryPaymentUpdate(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $result = $this->checkPendingPaymentUpdates($request);

            if ($result instanceof JsonResponse) {
                return $result;
            }

            if ($result && ($result['success'] ?? false)) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'Payment provider configuration updated successfully!',
                    'provider' => $result['provider'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No pending payment provider updates found.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Error retrying payment provider update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrying payment provider update: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkPendingPaymentUpdates(?Request $request = null)
    {
        $this->authorize('manage-payments');

        $wantsJson = $request && $this->wantsJsonResponse($request);

        if (!Session::has('pending_payment_update')
            || Session::get('pending_payment_update.attempted', false)) {

            $response = [
                'success' => false,
                'message' => 'No pending payment provider updates found or update already attempted',
            ];

            return $wantsJson ? response()->json($response, 404) : $response;
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
                    'provider'      => $pendingUpdate['provider'],
                    'is_production' => $pendingUpdate['is_production'] ?? false,
                ]);

                $response = [
                    'success'  => true,
                    'message'  => 'Payment provider configuration updated successfully!',
                    'provider' => $pendingUpdate['provider'],
                ];

                return $wantsJson ? response()->json($response) : $response;
            }

            Log::warning('Manual retry: Payment provider update failed', [
                'provider' => $pendingUpdate['provider'],
                'error'    => $updateResult['message'],
            ]);

            $response = [
                'success' => false,
                'message' => 'Payment provider configuration update failed: ' . $updateResult['message'],
            ];

            return $wantsJson ? response()->json($response, 500) : $response;
        } catch (\Throwable $e) {
            Log::error('Manual retry: Payment provider update process failed', [
                'provider' => $pendingUpdate['provider'],
                'error'    => $e->getMessage(),
            ]);

            Session::put('pending_payment_update.attempted', true);
            Session::save();

            $response = [
                'success' => false,
                'message' => 'Update process error: ' . $e->getMessage(),
            ];

            return $wantsJson ? response()->json($response, 500) : $response;
        }
    }

    public function checkJobStatus(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        $provider = $request->get('provider');
        $jobId    = $request->get('job_id');

        if (!$provider || !$jobId) {
            return response()->json([
                'success' => false,
                'message' => 'Provider and job_id are required',
            ], 422);
        }

        if (!PaymentProviderRegistry::has($provider)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown provider',
            ], 422);
        }

        try {
            $jobStatus = Cache::get("payment_provider_job_{$provider}_{$jobId}");

            if ($jobStatus) {
                return response()->json([
                    'success'   => true,
                    'status'    => $jobStatus,
                    'timestamp' => now()->timestamp,
                ]);
            }

            $expectedState = Cache::get("payment_provider_{$provider}_expected");
            if ($expectedState) {
                return response()->json([
                    'success'        => true,
                    'status'         => 'pending',
                    'message'        => 'Job is still processing',
                    'expected_state' => $expectedState,
                    'timestamp'      => now()->timestamp,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Job not found or already completed',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to check job status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check job status: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // CONFIG & WEBHOOKS
    // =========================================================================

    public function getProviderConfig(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $provider = $request->get('provider');

            if ($provider) {
                if (!PaymentProviderRegistry::has($provider)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Provider not found',
                    ], 404);
                }

                $meta   = PaymentProviderRegistry::get($provider);
                $config = [
                    'name'        => $meta['name'],
                    'fields'      => $this->fieldsFor($provider),
                    'enabled'     => (bool) env($meta['env_enabled'], false),
                    'configured'  => $this->getProviderStateFromEnv($provider)['configured'] ?? false,
                    'environment' => $this->currentEnvironment($provider),
                ];

                return response()->json([
                    'success'  => true,
                    'provider' => $provider,
                    'config'   => $config,
                ]);
            }

            $allConfigs = [];
            foreach (PaymentProviderRegistry::keys() as $p) {
                $meta = PaymentProviderRegistry::get($p);
                $allConfigs[$p] = [
                    'name'        => $meta['name'],
                    'enabled'     => (bool) env($meta['env_enabled'], false),
                    'configured'  => $this->getProviderStateFromEnv($p)['configured'] ?? false,
                    'environment' => $this->currentEnvironment($p),
                ];
            }

            return response()->json([
                'success' => true,
                'configs' => $allConfigs,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get provider config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get provider config: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getWebhookUrls(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $urls = [];
            foreach (PaymentProviderRegistry::keys() as $p) {
                $urls[$p] = url("/api/payments/{$p}/webhook");
            }

            $provider = $request->get('provider');

            if ($provider) {
                if (!isset($urls[$provider])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Provider not found',
                    ], 404);
                }

                return response()->json([
                    'success'     => true,
                    'provider'    => $provider,
                    'webhook_url' => $urls[$provider],
                ]);
            }

            return response()->json([
                'success'      => true,
                'webhook_urls' => $urls,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get webhook URLs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook URLs: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // STATE MANAGEMENT / MAINTENANCE
    // =========================================================================

    public function clearProviderStates(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $provider = $request->get('provider', 'all');

            if ($provider === 'all') {
                foreach (PaymentProviderRegistry::keys() as $p) {
                    Cache::forget("payment_provider_{$p}_expected");
                    Cache::forget("payment_provider_{$p}_actual");
                }
                $message = 'Cleared all provider states';
            } elseif (PaymentProviderRegistry::has($provider)) {
                Cache::forget("payment_provider_{$provider}_expected");
                Cache::forget("payment_provider_{$provider}_actual");
                $message = "Cleared states for {$provider}";
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown provider',
                ], 422);
            }

            Log::info('Cleared provider states', [
                'provider' => $provider,
                'action'   => 'manual_clear',
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear provider states: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up old .env backups.
     *
     * ✅ UPDATED: reads from both the new backup directory and the legacy
     *             location (project root) for backward compatibility, and
     *             delegates retention to pruneEnvBackups().
     */
    public function cleanupBackups(Request $request): JsonResponse
    {
        $this->authorize('manage-payments');

        try {
            $newDir   = $this->getEnvBackupDir();
            $legacyDir = base_path();

            $newCount    = count(File::glob($newDir . DIRECTORY_SEPARATOR . '.env.backup.*'));
            $legacyCount = count(File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.backup.*'));

            if ($newCount + $legacyCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No backups to clean up',
                ]);
            }

            // Prune the new directory with the standard policy.
            $this->pruneEnvBackups($newDir);

            // For the legacy directory (files created before this refactor),
            // keep the 5 most recent and delete the rest. This is a one-time
            // migration path; future backups won't be created here.
            $legacyBackups = File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.backup.*');
            if (count($legacyBackups) > self::ENV_BACKUP_KEEP_RECENT) {
                usort($legacyBackups, fn ($a, $b) => filemtime($b) <=> filemtime($a));
                $legacyToDelete = array_slice($legacyBackups, self::ENV_BACKUP_KEEP_RECENT);

                foreach ($legacyToDelete as $backup) {
                    File::delete($backup);
                    Log::info('[EnvBackup] pruned legacy backup: ' . basename($backup));
                }
            }

            $remaining = count(File::glob($newDir . DIRECTORY_SEPARATOR . '.env.backup.*'))
                       + count(File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.backup.*'));

            return response()->json([
                'success' => true,
                'message' => "Backups pruned. {$remaining} remaining.",
                'counts'  => [
                    'new_dir'    => count(File::glob($newDir . DIRECTORY_SEPARATOR . '.env.backup.*')),
                    'legacy_dir' => count(File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.backup.*')),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to clean up backups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean up backups: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // INTERNAL — UPDATE PIPELINE
    // =========================================================================

    protected function processPaymentProviderUpdate(array $configData, string $provider)
    {
        $backupPath = null;

        try {
            Log::info('Processing payment provider update immediately', [
                'provider'    => $provider,
                'config_keys' => array_keys($configData),
            ]);

            $this->storeExpectedProviderState($provider, $configData);

            $backupPath = $this->backupEnvFile();

            if (! $this->updateEnvFile($configData)) {
                throw new \Exception('Failed to update environment file');
            }

            $this->forceEnvironmentReload();

            $verification = $this->verifyConfigurationUpdate($provider, $configData);

            if (! $verification['success']) {
                $this->restoreEnvBackup();
                throw new \Exception(
                    'Configuration verification failed: '
                    . ($verification['message'] ?? 'Unknown error')
                );
            }

            $this->storeActualProviderState($provider);

            return [
                'success'      => true,
                'message'      => 'Configuration updated successfully',
                'verification' => $verification,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to process payment provider update: ' . $e->getMessage(), [
                'provider'    => $provider,
                'config_keys' => array_keys($configData),
            ]);

            if ($backupPath) {
                $this->restoreEnvBackup();
            }

            Cache::forget("payment_provider_{$provider}_expected");

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function verifyConfigurationUpdate(string $provider, array $configData)
    {
        try {
            $actualState         = $this->getProviderStateFromEnv($provider);
            $expectedEnabled     = $this->getEnabledFromConfig($provider, $configData);
            $expectedEnvironment = $this->getExpectedEnvironmentFromConfig($provider, $configData);

            $verification = [
                'success'              => true,
                'enabled'              => $actualState['enabled'],
                'environment'          => $actualState['environment'],
                'expected_enabled'     => $expectedEnabled,
                'expected_environment' => $expectedEnvironment,
                'enabled_match'        => $actualState['enabled'] === $expectedEnabled,
                'environment_match'    => $actualState['environment'] === $expectedEnvironment,
                'configured'           => $actualState['configured'],
                'key_mismatches'       => [],
                'timestamp'            => now()->timestamp,
            ];

            foreach ($configData as $key => $expectedValue) {
                if ($key === 'enabled') {
                    continue;
                }

                if (str_ends_with($key, '_ENABLED')) {
                    continue;
                }

                $expectedString = (string) $expectedValue;

                if ($expectedString === '' || $expectedString === '""') {
                    continue;
                }

                $actualValue = env($key);

                $expectedNormalized = $this->normalizeForComparison($expectedValue);
                $actualNormalized   = $this->normalizeForComparison($actualValue);

                if ($expectedNormalized !== $actualNormalized) {
                    $verification['key_mismatches'][] = [
                        'key'      => $key,
                        'expected' => $this->maskSecret($key, $expectedString),
                        'actual'   => $this->maskSecret($key, (string) $actualValue),
                    ];
                }
            }

            if (!$verification['enabled_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Enabled state does not match expected value';

                Log::warning('Enabled state mismatch during verification', [
                    'provider'    => $provider,
                    'expected'    => $expectedEnabled,
                    'actual'      => $actualState['enabled'],
                    'config_keys' => array_keys($configData),
                ]);
            }

            if (!$verification['environment_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Environment does not match expected value';

                Log::warning('Environment mismatch during verification', [
                    'provider' => $provider,
                    'expected' => $expectedEnvironment,
                    'actual'   => $actualState['environment'],
                ]);
            }

            if (!empty($verification['key_mismatches'])) {
                $verification['success'] = false;
                $verification['message'] = 'One or more keys did not read back with the expected value.';

                Log::warning('Key mismatch during verification', [
                    'provider'   => $provider,
                    'mismatches' => $verification['key_mismatches'],
                ]);
            }

            if ($verification['success'] && $actualState['environment'] === 'production') {
                Log::info('Production environment successfully verified', [
                    'provider'    => $provider,
                    'environment' => $actualState['environment'],
                    'enabled'     => $actualState['enabled'],
                    'configured'  => $actualState['configured'],
                ]);
            }

            return $verification;
        } catch (\Throwable $e) {
            Log::error('Configuration verification failed: ' . $e->getMessage(), [
                'provider' => $provider,
            ]);

            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function normalizeForComparison($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        $string = trim((string) $value);
        $lower  = strtolower($string);

        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
            return '1';
        }
        if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
            return '0';
        }

        return $string;
    }

    protected function maskSecret(string $key, string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $needle = strtoupper($key);
        $isSecret = str_contains($needle, 'SECRET')
                 || str_contains($needle, 'KEY')
                 || str_contains($needle, 'PASSWORD')
                 || str_contains($needle, 'TOKEN');

        if (! $isSecret) {
            return $value;
        }

        return strlen($value) <= 8
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 4) . '…' . substr($value, -4);
    }

    protected function storeExpectedProviderState(string $provider, array $configData): void
    {
        try {
            $environment = $this->getExpectedEnvironmentFromConfig($provider, $configData);

            $expectedState = [
                'enabled'        => $this->getEnabledFromConfig($provider, $configData),
                'configured'     => true,
                'environment'    => $environment,
                'timestamp'      => now()->timestamp,
                'job_dispatched' => true,
                'is_production'  => $environment === 'production',
            ];

            Cache::put("payment_provider_{$provider}_expected", $expectedState, 300);

            Log::info('Stored expected provider state', [
                'provider'       => $provider,
                'expected_state' => $expectedState,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to store expected provider state: ' . $e->getMessage());
        }
    }

    protected function storeActualProviderState(string $provider): void
    {
        try {
            $actualState = $this->getProviderStateFromEnv($provider);
            $actualState['timestamp']     = now()->timestamp;
            $actualState['verified']      = true;
            $actualState['is_production'] = $actualState['environment'] === 'production';

            Cache::put("payment_provider_{$provider}_actual", $actualState, 300);
            Cache::forget("payment_provider_{$provider}_expected");

            Log::info('Stored actual provider state', [
                'provider' => $provider,
                'state'    => $actualState,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to store actual provider state: ' . $e->getMessage());
        }
    }

    protected function getProviderStateFromEnv(string $provider): array
    {
        $state = [
            'enabled'                  => false,
            'configured'               => false,
            'environment'              => 'sandbox',
            'merchant_id_configured'   => false,
            'api_key_configured'       => false,
            'public_key_configured'    => false,
            'secret_key_configured'    => false,
            'client_id_configured'     => false,
            'client_secret_configured' => false,
        ];

        switch ($provider) {
            case 'expresspay':
                $state['enabled']                = env('EXPRESSPAY_ENABLED') === 'true';
                $state['environment']            = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
                $state['merchant_id_configured'] = !empty(env('EXPRESSPAY_MERCHANT_ID'));
                $state['api_key_configured']     = !empty(env('EXPRESSPAY_API_KEY'));
                $state['environment_configured'] = !empty(env('EXPRESSPAY_ENVIRONMENT'));
                $state['configured'] =
                    $state['merchant_id_configured']
                    && $state['api_key_configured']
                    && $state['environment_configured'];
                break;

            case 'flutterwave':
                $state['enabled']                   = env('FLUTTERWAVE_ENABLED') === 'true';
                $state['public_key_configured']     = !empty(env('FLUTTERWAVE_PUBLIC_KEY'));
                $state['secret_key_configured']     = !empty(env('FLUTTERWAVE_SECRET_KEY'));
                $state['encryption_key_configured'] = !empty(env('FLUTTERWAVE_ENCRYPTION_KEY'));
                $state['configured'] =
                    $state['public_key_configured'] && $state['secret_key_configured'];
                $state['environment'] =
                    str_contains((string) env('FLUTTERWAVE_BASE_URL', ''), 'api.flutterwave.com')
                        ? 'production' : 'sandbox';
                break;

            case 'hubtel':
                $state['enabled']                     = env('HUBTEL_ENABLED') === 'true';
                $state['client_id_configured']        = !empty(env('HUBTEL_CLIENT_ID'));
                $state['client_secret_configured']    = !empty(env('HUBTEL_CLIENT_SECRET'));
                $state['merchant_account_configured'] = !empty(env('HUBTEL_MERCHANT_ACCOUNT'));
                $state['configured'] =
                    $state['client_id_configured'] && $state['client_secret_configured'];

                $explicitEnv = env('HUBTEL_ENVIRONMENT');
                if (!empty($explicitEnv)) {
                    $state['environment'] = $explicitEnv === 'production' ? 'production' : 'sandbox';
                } else {
                    $state['environment'] = str_contains((string) env('HUBTEL_BASE_URL', ''), 'api.hubtel.com')
                        ? 'production' : 'sandbox';
                }
                break;

            case 'paystack':
                $enabledValue = env('PAYSTACK_ENABLED');
                $secretKey    = env('PAYSTACK_SECRET_KEY');
                $publicKey    = env('PAYSTACK_PUBLIC_KEY');

                $state['enabled']               = $enabledValue === 'true' || $enabledValue === true;
                $state['secret_key_configured'] = !empty($secretKey)
                    && $secretKey !== 'sk_test_your_secret_key_here'
                    && strlen((string) $secretKey) > 20;
                $state['public_key_configured'] = !empty($publicKey)
                    && $publicKey !== 'pk_test_your_public_key_here'
                    && strlen((string) $publicKey) > 20;
                $state['configured'] =
                    $state['secret_key_configured']
                    && $state['public_key_configured']
                    && $state['enabled'];
                $state['environment'] =
                    str_contains((string) env('PAYSTACK_PAYMENT_URL', ''), 'api.paystack.co')
                        ? 'production' : 'sandbox';

                Log::info('Paystack state from environment', [
                    'enabled'               => $state['enabled'],
                    'enabled_raw'           => $enabledValue,
                    'secret_key_configured' => $state['secret_key_configured'],
                    'public_key_configured' => $state['public_key_configured'],
                    'configured'            => $state['configured'],
                    'secret_key_length'     => strlen((string) $secretKey),
                    'public_key_length'     => strlen((string) $publicKey),
                ]);
                break;
        }

        $state['missing_configuration'] = $this->getMissingConfiguration($provider, $state);

        return $state;
    }

    protected function getMissingConfiguration(string $provider, array $state): array
    {
        $missing = [];

        switch ($provider) {
            case 'expresspay':
                if (!$state['merchant_id_configured']) $missing[] = 'Merchant ID';
                if (!$state['api_key_configured'])     $missing[] = 'API Key';
                if (empty($state['environment_configured'])) $missing[] = 'Environment';
                break;
            case 'flutterwave':
                if (!$state['public_key_configured']) $missing[] = 'Public Key';
                if (!$state['secret_key_configured']) $missing[] = 'Secret Key';
                break;
            case 'hubtel':
                if (!$state['client_id_configured'])     $missing[] = 'Client ID';
                if (!$state['client_secret_configured']) $missing[] = 'Client Secret';
                break;
            case 'paystack':
                if (!$state['secret_key_configured']) $missing[] = 'Secret Key';
                if (!$state['public_key_configured']) $missing[] = 'Public Key';
                break;
        }

        return $missing;
    }

    // =========================================================================
    // INTERNAL — ENV WRITING
    // =========================================================================

    protected function prepareProviderConfig(string $provider, Request $request): array
    {
        $enabled = $request->boolean('enabled') ? 'true' : 'false';

        switch ($provider) {
            case 'expresspay':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production'
                    ? 'https://api.expresspaygh.com'
                    : 'https://sandbox.expresspaygh.com';

                return [
                    'EXPRESSPAY_MERCHANT_ID'  => $request->merchant_id ?? '',
                    'EXPRESSPAY_API_KEY'      => $request->api_key ?? '',
                    'EXPRESSPAY_ENVIRONMENT'  => $environment,
                    'EXPRESSPAY_BASE_URL'     => $baseUrl,
                    'EXPRESSPAY_CALLBACK_URL' => trim((string) $request->callback_url),
                    'EXPRESSPAY_ENABLED'      => $enabled,
                ];

            case 'flutterwave':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production'
                    ? 'https://api.flutterwave.com/v3'
                    : 'https://sandbox.flutterwave.com/v3';

                return [
                    'FLUTTERWAVE_PUBLIC_KEY'     => $request->public_key ?? '',
                    'FLUTTERWAVE_SECRET_KEY'     => $request->secret_key ?? '',
                    'FLUTTERWAVE_ENCRYPTION_KEY' => $request->encryption_key ?? '',
                    'FLUTTERWAVE_BASE_URL'       => $baseUrl,
                    'FLUTTERWAVE_ENABLED'        => $enabled,
                ];

            case 'hubtel':
                $environment = $request->environment ?? 'sandbox';
                $baseUrl = $environment === 'production'
                    ? 'https://api.hubtel.com/v1'
                    : 'https://sandbox.hubtel.com/v1';

                return [
                    'HUBTEL_CLIENT_ID'        => $request->client_id ?? '',
                    'HUBTEL_CLIENT_SECRET'    => $request->client_secret ?? '',
                    'HUBTEL_MERCHANT_ACCOUNT' => $request->merchant_account ?? '',
                    'HUBTEL_ENVIRONMENT'      => $environment,
                    'HUBTEL_BASE_URL'         => $baseUrl,
                    'HUBTEL_ENABLED'          => $enabled,
                ];

            case 'paystack':
                return [
                    'PAYSTACK_SECRET_KEY'  => trim((string) $request->secret_key),
                    'PAYSTACK_PUBLIC_KEY'  => trim((string) $request->public_key),
                    'PAYSTACK_PAYMENT_URL' => 'https://api.paystack.co',
                    'PAYSTACK_ENABLED'     => $enabled,
                    'PAYSTACK_WEBHOOK_URL' => url('/api/payments/paystack/webhook'),
                    'PAYSTACK_MERCHANT_ID'    => trim((string) $request->merchant_id),
                    'PAYSTACK_WEBHOOK_SECRET' => trim((string) $request->webhook_secret),
                ];

            default:
                return ['enabled' => $enabled];
        }
    }

    protected function getResetConfig(string $provider): array
    {
        switch ($provider) {
            case 'expresspay':
                return [
                    'EXPRESSPAY_MERCHANT_ID'  => '',
                    'EXPRESSPAY_API_KEY'      => '',
                    'EXPRESSPAY_ENVIRONMENT'  => 'sandbox',
                    'EXPRESSPAY_BASE_URL'     => 'https://sandbox.expresspaygh.com',
                    'EXPRESSPAY_CALLBACK_URL' => '',
                    'EXPRESSPAY_ENABLED'      => 'false',
                ];
            case 'flutterwave':
                return [
                    'FLUTTERWAVE_PUBLIC_KEY'     => '',
                    'FLUTTERWAVE_SECRET_KEY'     => '',
                    'FLUTTERWAVE_ENCRYPTION_KEY' => '',
                    'FLUTTERWAVE_BASE_URL'       => 'https://sandbox.flutterwave.com/v3',
                    'FLUTTERWAVE_ENABLED'        => 'false',
                ];
            case 'hubtel':
                return [
                    'HUBTEL_CLIENT_ID'        => '',
                    'HUBTEL_CLIENT_SECRET'    => '',
                    'HUBTEL_MERCHANT_ACCOUNT' => '',
                    'HUBTEL_ENVIRONMENT'      => 'sandbox',
                    'HUBTEL_BASE_URL'         => 'https://sandbox.hubtel.com/v1',
                    'HUBTEL_ENABLED'          => 'false',
                ];
            case 'paystack':
                return [
                    'PAYSTACK_SECRET_KEY'     => '',
                    'PAYSTACK_PUBLIC_KEY'     => '',
                    'PAYSTACK_PAYMENT_URL'    => 'https://api.paystack.co',
                    'PAYSTACK_ENABLED'        => 'false',
                    'PAYSTACK_MERCHANT_ID'    => '',
                    'PAYSTACK_WEBHOOK_SECRET' => '',
                ];
            default:
                return [];
        }
    }

    /**
     * ✅ Atomic write: modify in memory, write to a temp file, rename over
     *               the original. A crash mid-write can no longer leave a
     *               half-written `.env` that breaks the next request.
     */
    protected function updateEnvFile(array $data): bool
    {
        $envPath  = base_path('.env');
        $tempPath = $envPath . '.tmp.' . uniqid('', true);

        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found at: ' . $envPath);
        }
        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable. Check file permissions.');
        }

        $envContent = File::get($envPath);
        $updated    = false;

        foreach ($data as $key => $value) {
            if ($key === 'enabled') {
                continue;
            }

            $escapedValue = $this->escapeEnvValue($value);
            $quotedKey    = preg_quote($key, '/');
            $pattern      = "/^{$quotedKey}=.*/m";
            $replacement  = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
                Log::debug("Updated existing environment variable: {$key}");
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
                Log::debug("Added new environment variable: {$key}");
            }
        }

        if (!$updated) {
            Log::warning('No updates made to .env file');
            return false;
        }

        // ✅ Write to temp file first — if this throws, .env is untouched.
        if (File::put($tempPath, $envContent) === false) {
            throw new \Exception('Failed to write temporary .env file at: ' . $tempPath);
        }

        // ✅ Atomic replace. On Unix this is an inode swap; on Windows it's
        //    a fast copy-then-delete. Either way, .env is never in a
        //    partially-written state.
        if (! @rename($tempPath, $envPath)) {
            // Windows fallback: rename fails if the destination exists.
            if (! File::copy($tempPath, $envPath)) {
                File::delete($tempPath);
                throw new \Exception('Failed to replace .env file atomically.');
            }
            File::delete($tempPath);
        }

        Log::info('Updated .env file with payment provider configuration', [
            'keys_updated'        => array_keys(array_diff_key($data, ['enabled' => true])),
            'contains_production' => in_array('production', array_values($data), true),
        ]);

        return true;
    }

    protected function escapeEnvValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        // ✅ Collapse CR/LF to spaces before any other escaping.
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\'\\\\=]/', $value)) {
            $value = '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $value) . '"';
        }

        return $value;
    }

    // =========================================================================
    // INTERNAL — ENV BACKUP (retention + safe location)
    // =========================================================================

    /**
     * Where .env backups live.
     *
     * ✅ Moved out of the project root (where .env.backup.* is typically
     *    web-accessible) to storage/app/backups/env, which is neither
     *    served by the web server nor reachable via a public URL.
     */
    protected function getEnvBackupDir(): string
    {
        $dir = storage_path('app/backups/env');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true);
        }

        return $dir;
    }

    /**
     * Back up .env before a write.
     *
     * ✅ Backups live in storage/app/backups/env (not the project root).
     * ✅ Permission-restricted to 0600 where the OS supports it.
     * ✅ Retention enforced on every save: keep the N most recent, drop
     *    anything older than MAX_AGE_HOURS.
     */
    protected function backupEnvFile(): ?string
    {
        try {
            $envPath = base_path('.env');

            if (! File::exists($envPath)) {
                return null;
            }

            $backupDir  = $this->getEnvBackupDir();
            $backupPath = $backupDir . DIRECTORY_SEPARATOR
                        . '.env.backup.' . date('Y-m-d-H-i-s') . '-' . uniqid();

            if (! File::copy($envPath, $backupPath)) {
                Log::warning('Failed to copy .env to backup path: ' . $backupPath);
                return null;
            }

            // ✅ Restrict permissions. No-op on Windows, effective on Unix.
            @chmod($backupPath, 0600);

            Log::info('.env file backed up to: ' . $backupPath);

            // ✅ Enforce retention immediately — bounds the accumulation
            //    so credentials don't pile up on disk.
            $this->pruneEnvBackups($backupDir);

            return $backupPath;
        } catch (\Throwable $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Retention policy: keep the N most recent, delete anything older
     * than MAX_AGE_HOURS, and never delete the very newest backup (it's
     * the one we might restore from if the current save fails).
     */
    protected function pruneEnvBackups(string $dir): void
    {
        $backups = File::glob($dir . DIRECTORY_SEPARATOR . '.env.backup.*');

        if (count($backups) <= 1) {
            return; // Nothing to prune.
        }

        // Sort newest first.
        usort($backups, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $now          = time();
        $maxAgeSecs   = self::ENV_BACKUP_MAX_AGE_HOURS * 3600;
        $keepRecent   = self::ENV_BACKUP_KEEP_RECENT;
        $prunedCount  = 0;

        foreach ($backups as $index => $path) {
            // Always keep the newest entry — it's our restore candidate.
            if ($index === 0) {
                continue;
            }

            $age    = $now - filemtime($path);
            $isOld  = $age > $maxAgeSecs;
            $isPast = $index >= $keepRecent;

            // Delete if it's older than the max age OR beyond the keep-count.
            if ($isOld || $isPast) {
                if (File::delete($path)) {
                    $prunedCount++;
                }
            }
        }

        if ($prunedCount > 0) {
            Log::debug("[EnvBackup] pruned {$prunedCount} old backups from {$dir}");
        }
    }

    /**
     * Restore .env from the newest backup.
     *
     * ✅ Reads from the new backup directory first, and falls back to the
     *    legacy project-root location for older installations.
     */
    protected function restoreEnvBackup(): bool
    {
        try {
            $backupDir = $this->getEnvBackupDir();

            $candidates = File::glob($backupDir . DIRECTORY_SEPARATOR . '.env.backup.*');

            // ✅ Backwards compatibility: also consider legacy backups
            //    still sitting in the project root.
            $legacy = File::glob(base_path() . DIRECTORY_SEPARATOR . '.env.backup.*');
            if (! empty($legacy)) {
                $candidates = array_merge($candidates, $legacy);
            }

            if (empty($candidates)) {
                Log::warning('No .env backups found for restore.');
                return false;
            }

            // Newest first — accept either layout.
            usort($candidates, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latest = $candidates[0];

            if (! File::copy($latest, base_path('.env'))) {
                Log::error('Failed to restore .env from backup: ' . $latest);
                return false;
            }

            Log::info('Restored .env from backup: ' . $latest);

            $this->forceEnvironmentReload();
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // INTERNAL — CACHE / ENV RELOAD
    // =========================================================================

    protected function getConfigurationStatusWithReload(): array
    {
        try {
            $this->clearApplicationCaches(true);
            $this->settle();
            return $this->paymentService->checkPaymentMethodConfiguration();
        } catch (\Throwable $e) {
            Log::error('Failed to get configuration status with reload: ' . $e->getMessage());
            return [];
        }
    }

    protected function clearApplicationCaches(bool $forceReload = false): void
    {
        ob_start();

        try {
            $t0 = microtime(true);

            $cachedFiles = [
                'bootstrap/cache/config.php',
                'bootstrap/cache/routes-v7.php',
                'bootstrap/cache/routes.php',
                'bootstrap/cache/packages.php',
                'bootstrap/cache/services.php',
            ];

            foreach ($cachedFiles as $relativePath) {
                $full = base_path($relativePath);
                if (File::exists($full)) {
                    File::delete($full);
                    Log::debug("[CAC] deleted {$relativePath}");
                }
            }

            $compiledDir = storage_path('framework/views');
            if (File::isDirectory($compiledDir)) {
                $count = 0;
                foreach (File::glob($compiledDir . '/*.php') as $compiledView) {
                    File::delete($compiledView);
                    $count++;
                }
                Log::debug("[CAC] deleted {$count} compiled views");
            }

            Cache::flush();

            if ($forceReload) {
                $this->forceEnvironmentReload();
            }

            $elapsed = round((microtime(true) - $t0) * 1000);
            Log::debug("Application caches cleared in {$elapsed}ms" . ($forceReload ? ' and environment reloaded' : ''));
        } catch (\Throwable $e) {
            Log::warning('Error clearing caches: ' . $e->getMessage());
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    protected function forceEnvironmentReload(): void
    {
        ob_start();

        try {
            $t0 = microtime(true);

            $paymentVars = [
                'EXPRESSPAY_ENABLED', 'EXPRESSPAY_MERCHANT_ID', 'EXPRESSPAY_API_KEY',
                'EXPRESSPAY_ENVIRONMENT', 'EXPRESSPAY_BASE_URL', 'EXPRESSPAY_CALLBACK_URL',
                'FLUTTERWAVE_ENABLED', 'FLUTTERWAVE_PUBLIC_KEY', 'FLUTTERWAVE_SECRET_KEY',
                'FLUTTERWAVE_ENCRYPTION_KEY', 'FLUTTERWAVE_BASE_URL',
                'HUBTEL_ENABLED', 'HUBTEL_CLIENT_ID', 'HUBTEL_CLIENT_SECRET',
                'HUBTEL_MERCHANT_ACCOUNT', 'HUBTEL_ENVIRONMENT', 'HUBTEL_BASE_URL',
                'PAYSTACK_ENABLED', 'PAYSTACK_SECRET_KEY', 'PAYSTACK_PUBLIC_KEY',
                'PAYSTACK_PAYMENT_URL', 'PAYSTACK_WEBHOOK_URL',
                'PAYSTACK_MERCHANT_ID', 'PAYSTACK_WEBHOOK_SECRET',
            ];

            foreach ($paymentVars as $var) {
                unset($_ENV[$var], $_SERVER[$var]);
            }

            if (!file_exists(base_path('.env'))) {
                return;
            }

            $lines = file(base_path('.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                Log::warning('[FER] could not read .env');
                return;
            }

            foreach ($lines as $line) {
                if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim(trim($value), '"\'');

                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }

            $elapsed = round((microtime(true) - $t0) * 1000);
            Log::debug("Environment variables force reloaded in {$elapsed}ms");
        } catch (\Throwable $e) {
            Log::warning('Error forcing environment reload: ' . $e->getMessage());
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    // =========================================================================
    // INTERNAL — CONNECTION TESTS
    // =========================================================================

    protected function testProviderConnection(string $provider): array
    {
        try {
            $this->clearApplicationCaches(true);
            $gatewayStatus = $this->paymentService->checkPaymentMethodConfiguration();

            if (!isset($gatewayStatus[$provider])) {
                return ['success' => false, 'message' => 'Provider configuration not found', 'environment' => 'unknown'];
            }

            $providerConfig = $gatewayStatus[$provider];

            if (!$providerConfig['enabled']) {
                return [
                    'success'     => false,
                    'message'     => 'Provider is not enabled',
                    'environment' => $providerConfig['environment'] ?? 'unknown',
                ];
            }

            if (!$providerConfig['configured']) {
                return [
                    'success'     => false,
                    'message'     => 'Provider is not properly configured',
                    'details'     => ['missing_configuration' => $providerConfig['missing_configuration'] ?? []],
                    'environment' => $providerConfig['environment'] ?? 'unknown',
                ];
            }

            return match ($provider) {
                'paystack'    => $this->testPaystackConnection(),
                'expresspay'  => $this->testExpressPayConnection(),
                'flutterwave' => $this->testFlutterwaveConnection(),
                'hubtel'      => $this->testHubtelConnection(),
                default       => [
                    'success'     => false,
                    'message'     => 'Unsupported provider for connection testing',
                    'environment' => 'unknown',
                ],
            };
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => 'Test configuration error: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    protected function testPaystackConnection(): array
    {
        try {
            $this->clearApplicationCaches(true);

            $secretKey    = env('PAYSTACK_SECRET_KEY');
            $baseUrl      = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
            $environment  = str_contains((string) $baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
            $isProduction = $environment === 'production';

            if (empty($secretKey)) {
                return [
                    'success'       => false,
                    'message'       => 'Paystack secret key is missing',
                    'environment'   => $environment,
                    'is_production' => $isProduction,
                ];
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->timeout(10)->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success'       => true,
                    'message'       => 'Paystack API connection successful',
                    'environment'   => $environment,
                    'is_production' => $isProduction,
                    'details'       => [
                        'status'          => $response->status(),
                        'banks_available' => count($response->json()['data'] ?? []),
                        'is_production'   => $isProduction,
                    ],
                ];
            }

            return [
                'success'       => false,
                'message'       => 'Paystack API connection failed: ' . ($response->json()['message'] ?? 'Unknown error'),
                'environment'   => $environment,
                'is_production' => $isProduction,
                'details'       => ['status' => $response->status(), 'is_production' => $isProduction],
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'message'       => 'Paystack connection test failed: ' . $e->getMessage(),
                'environment'   => 'unknown',
                'is_production' => false,
            ];
        }
    }

    protected function testExpressPayConnection(): array
    {
        try {
            $merchantId   = env('EXPRESSPAY_MERCHANT_ID');
            $apiKey       = env('EXPRESSPAY_API_KEY');
            $baseUrl      = env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com');
            $environment  = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
            $isProduction = $environment === 'production';

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success'       => false,
                    'message'       => 'ExpressPay credentials are missing',
                    'environment'   => $environment,
                    'is_production' => $isProduction,
                ];
            }

            $isValidFormat = strlen((string) $merchantId) > 5 && strlen((string) $apiKey) > 10;

            return [
                'success'       => $isValidFormat,
                'message'       => $isValidFormat ? 'ExpressPay credentials format is valid' : 'ExpressPay credentials appear to be invalid',
                'environment'   => $environment,
                'is_production' => $isProduction,
                'details'       => [
                    'base_url'           => $baseUrl,
                    'merchant_id_length' => strlen((string) $merchantId),
                    'api_key_length'     => strlen((string) $apiKey),
                    'is_production'      => $isProduction,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'message'       => 'ExpressPay connection test failed: ' . $e->getMessage(),
                'environment'   => 'unknown',
                'is_production' => false,
            ];
        }
    }

    protected function testFlutterwaveConnection(): array
    {
        try {
            $secretKey    = env('FLUTTERWAVE_SECRET_KEY');
            $publicKey    = env('FLUTTERWAVE_PUBLIC_KEY');
            $baseUrl      = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
            $isProduction = str_contains((string) $baseUrl, 'api.flutterwave.com');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success'       => false,
                    'message'       => 'Flutterwave credentials are missing',
                    'environment'   => $isProduction ? 'production' : 'sandbox',
                    'is_production' => $isProduction,
                ];
            }

            $isValidFormat = strlen((string) $secretKey) > 10 && strlen((string) $publicKey) > 10;

            return [
                'success'       => $isValidFormat,
                'message'       => $isValidFormat ? 'Flutterwave credentials format is valid' : 'Flutterwave credentials appear to be invalid',
                'environment'   => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
                'details'       => [
                    'base_url'          => $baseUrl,
                    'secret_key_length' => strlen((string) $secretKey),
                    'public_key_length' => strlen((string) $publicKey),
                    'is_production'     => $isProduction,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'message'       => 'Flutterwave connection test failed: ' . $e->getMessage(),
                'environment'   => 'unknown',
                'is_production' => false,
            ];
        }
    }

    protected function testHubtelConnection(): array
    {
        try {
            $clientId     = env('HUBTEL_CLIENT_ID');
            $clientSecret = env('HUBTEL_CLIENT_SECRET');
            $baseUrl      = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
            $isProduction = str_contains((string) $baseUrl, 'api.hubtel.com');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success'       => false,
                    'message'       => 'Hubtel credentials are missing',
                    'environment'   => $isProduction ? 'production' : 'sandbox',
                    'is_production' => $isProduction,
                ];
            }

            $isValidFormat = strlen((string) $clientId) > 5 && strlen((string) $clientSecret) > 10;

            return [
                'success'       => $isValidFormat,
                'message'       => $isValidFormat ? 'Hubtel credentials format is valid' : 'Hubtel credentials appear to be invalid',
                'environment'   => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
                'details'       => [
                    'base_url'             => $baseUrl,
                    'client_id_length'     => strlen((string) $clientId),
                    'client_secret_length' => strlen((string) $clientSecret),
                    'is_production'        => $isProduction,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'message'       => 'Hubtel connection test failed: ' . $e->getMessage(),
                'environment'   => 'unknown',
                'is_production' => false,
            ];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function getEnabledFromConfig(string $provider, array $configData): bool
    {
        $key = PaymentProviderRegistry::get($provider)['env_enabled'] ?? null;
        return $key && ($configData[$key] ?? null) === 'true';
    }

    protected function getExpectedEnvironmentFromConfig(string $provider, array $configData): string
    {
        switch ($provider) {
            case 'expresspay':
                return $configData['EXPRESSPAY_ENVIRONMENT'] ?? 'sandbox';
            case 'flutterwave':
                return str_contains((string) ($configData['FLUTTERWAVE_BASE_URL'] ?? ''), 'api.flutterwave.com') ? 'production' : 'sandbox';
            case 'hubtel':
                if (!empty($configData['HUBTEL_ENVIRONMENT'])) {
                    return $configData['HUBTEL_ENVIRONMENT'] === 'production' ? 'production' : 'sandbox';
                }
                return str_contains((string) ($configData['HUBTEL_BASE_URL'] ?? ''), 'api.hubtel.com') ? 'production' : 'sandbox';
            case 'paystack':
                return str_contains((string) ($configData['PAYSTACK_PAYMENT_URL'] ?? ''), 'api.paystack.co') ? 'production' : 'sandbox';
            default:
                return 'sandbox';
        }
    }

    protected function currentEnvironment(string $provider): string
    {
        return match ($provider) {
            'expresspay'  => env('EXPRESSPAY_ENVIRONMENT', 'sandbox'),
            'flutterwave' => str_contains((string) env('FLUTTERWAVE_BASE_URL', ''), 'api.flutterwave.com') ? 'production' : 'sandbox',
            'hubtel'      => env('HUBTEL_ENVIRONMENT')
                ? (env('HUBTEL_ENVIRONMENT') === 'production' ? 'production' : 'sandbox')
                : (str_contains((string) env('HUBTEL_BASE_URL', ''), 'api.hubtel.com') ? 'production' : 'sandbox'),
            'paystack'    => str_contains((string) env('PAYSTACK_PAYMENT_URL', ''), 'api.paystack.co') ? 'production' : 'sandbox',
            default       => 'sandbox',
        };
    }

    protected function readEnvironmentFor(string $provider): array
    {
        $env = $this->currentEnvironment($provider);

        $display = $provider === 'expresspay'
            ? $env
            : ($env === 'production' ? 'production' : 'sandbox');

        return [$display, $env === 'production'];
    }

    protected function fieldsFor(string $provider): array
    {
        return match ($provider) {
            'expresspay'  => [
                'merchant_id'   => 'Merchant ID',
                'api_key'       => 'API Key',
                'environment'   => 'Environment',
                'callback_url'  => 'Callback URL',
            ],
            'flutterwave' => [
                'public_key'     => 'Public Key',
                'secret_key'     => 'Secret Key',
                'encryption_key' => 'Encryption Key',
            ],
            'hubtel'      => [
                'client_id'        => 'Client ID',
                'client_secret'    => 'Client Secret',
                'merchant_account' => 'Merchant Account',
            ],
            'paystack'    => [
                'secret_key'     => 'Secret Key',
                'public_key'     => 'Public Key',
                'merchant_id'    => 'Merchant ID',
                'webhook_secret' => 'Webhook Secret',
            ],
            default => [],
        };
    }

    protected function hasAnyCredential(Request $request): bool
    {
        return (bool) (
            $request->filled('api_key')
            || $request->filled('public_key')
            || $request->filled('secret_key')
            || $request->filled('encryption_key')
            || $request->filled('client_id')
            || $request->filled('client_secret')
            || $request->filled('merchant_id')
            || $request->filled('merchant_account')
            || $request->filled('webhook_secret')
            || $request->filled('callback_url')
        );
    }

    protected function safeOpcacheReset(): void
    {
        if (! config('payment_providers.call_opcache_reset', env('PAYMENT_PROVIDERS_CALL_OPCACHE_RESET', false))) {
            return;
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    protected function settle(int $micros = 500000): void
    {
        usleep($micros);
    }
}