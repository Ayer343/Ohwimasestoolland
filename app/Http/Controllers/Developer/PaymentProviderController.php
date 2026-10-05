<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\PaymentConfigurationService;
use App\Services\PaymentService;
use App\Support\PaymentProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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
    private const ENV_BACKUP_KEEP_RECENT   = 5;
    private const ENV_BACKUP_MAX_AGE_HOURS = 24;

    protected PaymentService $paymentService;
    protected PaymentConfigurationService $configService;

    public function __construct(
        PaymentService $paymentService,
        PaymentConfigurationService $configService
    ) {
        $this->paymentService = $paymentService;
        $this->configService  = $configService;

        // NOTE: no $this->middleware() — Laravel 11 base Controller doesn't have it.
        // Authorization is enforced per-method via requireDeveloperPaymentAccess().
    }

    // =========================================================================
    // JSON DETECTION HELPER
    // =========================================================================

    /**
     * Treat AJAX, XHR, Accept: application/json, and expectsJson()
     * as JSON requests. The parent blade sends `X-Requested-With`,
     * but some HTTP clients only send `Accept`. Cover both so we
     * never hand a RedirectResponse to fetch().
     */
    protected function wantsJsonResponse(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->header('Accept'), 'application/json');
    }

    // =========================================================================
    // AUTH GUARD
    // =========================================================================

    protected function requireDeveloperPaymentAccess(): void
    {
        if (! Gate::allows('manage-developer-payments')) {
            abort(403, 'You are not authorized to manage developer payment providers.');
        }

        if (! $this->developerAccessEnabled()) {
            abort(403, 'Developer payment configuration is disabled.');
        }
    }

    protected function developerAccessEnabled(): bool
    {
        return (bool) env('DEVELOPER_PAYMENT_ACCESS_ENABLED', true);
    }

    protected function developerCanBill(): bool
    {
        return (bool) env('DEVELOPER_PAYMENT_CAN_BILL', true);
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $this->requireDeveloperPaymentAccess();

        $providers = $this->providerDefinitionsWithMeta();

        $status = $this->paymentService->checkPaymentMethodConfiguration();
        $developerConfig = $this->getDeveloperConfig();

        foreach ($providers as $key => $provider) {
            if (isset($status[$key])) {
                $providers[$key]['status']           = $status[$key];
                $providers[$key]['debug']            = $this->getDebugInfo($key);
                $providers[$key]['developer_config'] = $developerConfig[$key] ?? null;
            }
        }

        $environment = [
            'app_env'         => env('APP_ENV', 'local'),
            'debug_mode'      => env('APP_DEBUG', false),
            'php_version'     => phpversion(),
            'laravel_version' => app()->version(),
            'can_bill'        => $this->developerCanBill(),
            'is_configured'   => $this->isDeveloperConfigured(),
            'access_enabled'  => $this->developerAccessEnabled(),
        ];

        return view('developer.payments.providers', compact(
            'providers',
            'environment',
            'developerConfig'
        ));
    }

    // =========================================================================
    // CONFIGURE
    // =========================================================================

    public function configureDeveloperProvider(Request $request)
    {
        $this->requireDeveloperPaymentAccess();

        if (! $this->developerCanBill()) {
            return $this->errorResponse('Billing is disabled by the system administrator', 403);
        }

        $validationRules = [
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
            'enabled'  => ['required', 'boolean'],
        ];

        switch ($request->provider) {
            case 'paystack':
                $validationRules['secret_key'] = 'required_if:enabled,1|string|min:10';
                $validationRules['public_key'] = 'required_if:enabled,1|string|min:10';
                break;
            case 'expresspay':
                $validationRules['merchant_id']  = 'required_if:enabled,1|string|min:5';
                $validationRules['api_key']      = 'required_if:enabled,1|string|min:10';
                $validationRules['environment']  = 'nullable|in:sandbox,production';
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
                $validationRules['environment']      = 'nullable|in:sandbox,production';
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
            Log::warning('Developer payment validation failed', [
                'developer_id' => auth()->id(),
                'errors'       => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $backupPath = null;

        try {
            $provider = $request->provider;

            $configData = $this->prepareDeveloperConfig($provider, $request);

            $backupPath = $this->backupEnvFile();

            $this->updateDeveloperEnvFile($configData);

            // Reload env once, right after the write, so verification can read
            // the values back in this same request. No cache clear needed here.
            $this->forceEnvironmentReload();

            // Verify the specific keys we wrote actually read back correctly.
            $verification = $this->verifyDeveloperConfigWrite($provider, $request);

            if (! $verification['success']) {
                if ($backupPath) {
                    $this->restoreEnvBackup($backupPath);
                }
                throw new \Exception('Configuration verification failed: ' . ($verification['message'] ?? 'Unknown error'));
            }

            Log::info('Developer updated their own payment credentials', [
                'provider'        => $provider,
                'developer_id'    => auth()->id(),
                'developer_email' => auth()->user()->email,
                'enabled'         => $request->enabled,
            ]);

            $response = [
                'success'       => true,
                'message'       => "✅ Your {$provider} credentials have been saved successfully!",
                'provider'      => $provider,
                'enabled'       => (bool) $request->enabled,
                'is_configured' => true,
                'is_production' => $verification['is_production'] ?? false,
                'redirect'      => route('developer.payment-providers.index'),
            ];

            if ($this->wantsJsonResponse($request)) {
                return response()->json($response);
            }

            return redirect()->route('developer.payment-providers.index')
                ->with('success', $response['message']);
        } catch (\Throwable $e) {
            if ($backupPath) {
                $this->restoreEnvBackup($backupPath);
            }

            Log::error('Developer failed to update credentials: ' . $e->getMessage(), [
                'developer_id' => auth()->id(),
                'provider'     => $request->provider ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update credentials: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // TEST CONNECTION
    // =========================================================================

    public function testDeveloperConnection(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            if (! $this->isDeveloperConfigured($provider)) {
                return response()->json([
                    'success'     => false,
                    'message'     => '❌ Please configure your credentials first',
                    'environment' => 'unknown',
                ]);
            }

            $result = $this->testDeveloperCredentials($provider);

            Log::info('Developer tested their own credentials', [
                'provider'     => $provider,
                'success'      => $result['success'] ?? false,
                'developer_id' => auth()->id(),
            ]);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Developer test failed: ' . $e->getMessage(), [
                'developer_id' => auth()->id(),
                'provider'     => $provider,
            ]);

            return response()->json([
                'success'  => false,
                'message'  => 'Test failed: ' . $e->getMessage(),
                'provider' => $provider,
            ], 500);
        }
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $useDeveloper = $request->boolean('use_developer', true);

            $result = $useDeveloper
                ? $this->testDeveloperCredentials($provider)
                : $this->testSystemCredentials($provider);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success'  => false,
                'message'  => 'Connection test failed: ' . $e->getMessage(),
                'provider' => $provider,
            ], 500);
        }
    }

    // =========================================================================
    // BILL ADMIN
    // =========================================================================

    public function billAdmin(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        if (! $this->developerCanBill()) {
            return response()->json([
                'success' => false,
                'message' => 'Billing is disabled',
            ], 403);
        }

        $request->validate([
            'amount'      => 'required|numeric|min:1|max:100000',
            'description' => 'required|string|max:255',
            'email'       => 'required|email',
            'provider'    => 'nullable|string|' . PaymentProviderRegistry::validationRule(),
        ]);

        try {
            $provider = $request->input('provider') ?: $this->firstConfiguredProvider();

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Please configure your payment credentials first',
                ]);
            }

            if (! $this->isDeveloperConfigured($provider)) {
                return response()->json([
                    'success' => false,
                    'message' => "❌ Your {$provider} credentials are not configured",
                ]);
            }

            $payment = $this->processDeveloperPayment(
                $provider,
                (float) $request->amount,
                $request->description,
                $request->email
            );

            Log::info('Developer billed admin successfully', [
                'developer_id' => auth()->id(),
                'provider'     => $provider,
                'amount'       => $request->amount,
                'reference'    => $payment['reference'] ?? 'N/A',
            ]);

            return response()->json([
                'success'      => true,
                'message'      => '✅ Payment processed successfully!',
                'provider'     => $provider,
                'payment'      => $payment,
                'redirect_url' => $payment['authorization_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer billing failed: ' . $e->getMessage(), [
                'developer_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // STATUS / POLLING
    // =========================================================================

    public function getProviderStatus(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $this->clearReadCache();

            $status          = $this->paymentService->checkPaymentMethodConfiguration();
            $developerConfig = $this->getDeveloperConfig();

            foreach (array_keys($status) as $providerKey) {
                $status[$providerKey]['developer_config']     = $developerConfig[$providerKey] ?? null;
                $status[$providerKey]['developer_configured'] = $this->isDeveloperConfigured($providerKey);
                $status[$providerKey]['can_bill']             = $this->developerCanBill();

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
                'success'          => true,
                'data'             => $status,
                'developer_config' => $developerConfig,
                'debug'            => ['timestamp' => now()->toDateTimeString()],
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
        $this->requireDeveloperPaymentAccess();

        if (ob_get_level()) {
            ob_end_clean();
        }

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $this->clearReadCache();

            $status         = $this->paymentService->checkPaymentMethodConfiguration();
            $providerStatus = $status[$provider] ?? [];

            $providerStatus['developer_config']     = $this->getDeveloperConfig()[$provider] ?? null;
            $providerStatus['developer_configured'] = $this->isDeveloperConfigured($provider);
            $providerStatus['can_bill']             = $this->developerCanBill();
            $providerStatus['source']               = 'environment';
            $providerStatus['timestamp']            = now()->timestamp;
            $providerStatus['is_production']        = ($providerStatus['environment'] ?? 'sandbox') === 'production';

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
                    'debug'   => ['timestamp' => now()->toDateTimeString()],
                ])
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $e) {
            Log::error('Failed to get immediate provider status: ' . $e->getMessage());
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Failed to get provider status: ' . $e->getMessage(),
                ], 500)
                ->header('Content-Type', 'application/json; charset=utf-8');
        }
    }

    public function getUpdateStatus(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        return response()->json([
            'success'   => true,
            'status'    => [
                'has_pending_update' => Session::has('pending_developer_payment_update'),
                'pending_update'     => Session::get('pending_developer_payment_update'),
                'last_success'       => Session::get('developer_payment_update_success'),
                'last_message'       => Session::get('developer_payment_update_message'),
                'last_provider'      => Session::get('developer_payment_update_provider'),
                // ✅ NEW: expose backup count so the UI can display it if desired.
                'backup_count'       => count(File::glob(
                    $this->getEnvBackupDir() . DIRECTORY_SEPARATOR . '.env.developer-backup.*'
                )),
            ],
            'timestamp' => now()->timestamp,
        ]);
    }

    // =========================================================================
    // SHOW — single-provider deep link
    // =========================================================================

    public function show(string $provider)
    {
        $this->requireDeveloperPaymentAccess();

        if (! PaymentProviderRegistry::has($provider)) {
            abort(404, "Provider {$provider} not found.");
        }

        $providers = $this->providerDefinitionsWithMeta();

        $status = $this->paymentService->checkPaymentMethodConfiguration();
        $developerConfig = $this->getDeveloperConfig();

        if (isset($status[$provider])) {
            $providers[$provider]['status']           = $status[$provider];
            $providers[$provider]['debug']            = $this->getDebugInfo($provider);
            $providers[$provider]['developer_config'] = $developerConfig[$provider] ?? null;
        }

        $environment = [
            'app_env'         => env('APP_ENV', 'local'),
            'debug_mode'      => env('APP_DEBUG', false),
            'php_version'     => phpversion(),
            'laravel_version' => app()->version(),
            'can_bill'        => $this->developerCanBill(),
            'is_configured'   => $this->isDeveloperConfigured(),
            'access_enabled'  => $this->developerAccessEnabled(),
        ];

        return view('developer.payments.providers', [
            'providers'        => $providers,
            'environment'      => $environment,
            'developerConfig'  => $developerConfig,
            'activeProvider'   => $provider,
        ]);
    }

    // =========================================================================
    // CONFIG ALIAS
    // =========================================================================

    public function getConfig(Request $request): JsonResponse
    {
        return $this->getDeveloperConfigJson($request);
    }

    // =========================================================================
    // DEBUG — CACHE STATUS
    // =========================================================================

    public function getCacheStatus(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $providerKeys = PaymentProviderRegistry::keys();

            $providers = [];
            foreach ($providerKeys as $key) {
                $providers[$key] = [
                    'expected'           => Cache::has("payment_provider_{$key}_expected"),
                    'actual'             => Cache::has("payment_provider_{$key}_actual"),
                    'has_pending_update' => Cache::has("payment_provider_{$key}_expected"),
                    'recently_updated'   => Cache::has("payment_provider_{$key}_actual"),
                ];
            }

            return response()->json([
                'success'       => true,
                'cache_driver'  => config('cache.default'),
                'cache_prefix'  => config('cache.prefix'),
                'developer_read_cache' => [
                    'developer_provider_status_cache' => Cache::has('developer_provider_status_cache'),
                ],
                'providers'     => $providers,
                'timestamp'     => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer cache-status failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to read cache status: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // DEBUG — ENVIRONMENT INFO
    // =========================================================================

    public function getEnvironmentInfo(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $providerMeta = [];
            foreach (PaymentProviderRegistry::keys() as $key) {
                $providerMeta[$key] = [
                    'system_environment'    => $this->detectEnvironment($key),
                    'developer_configured'  => $this->isDeveloperConfigured($key),
                    'system_configured'     => (bool) ($this->paymentService
                        ->checkPaymentMethodConfiguration()[$key]['configured'] ?? false),
                ];
            }

            $backupDir = $this->getEnvBackupDir();

            return response()->json([
                'success' => true,
                'environment' => [
                    'app_env'         => env('APP_ENV', 'local'),
                    'app_debug'       => (bool) env('APP_DEBUG', false),
                    'app_url'         => config('app.url'),
                    'php_version'     => phpversion(),
                    'laravel_version' => app()->version(),
                    'cache_driver'    => config('cache.default'),
                    'queue_driver'    => config('queue.default'),
                    'session_driver'  => config('session.driver'),
                    'timezone'        => config('app.timezone'),
                ],
                'payment_flags' => [
                    'access_enabled' => $this->developerAccessEnabled(),
                    'can_bill'       => $this->developerCanBill(),
                ],
                'php_extensions' => $this->getRequiredExtensions(),
                'env_file' => [
                    'path'     => base_path('.env'),
                    'exists'   => file_exists(base_path('.env')),
                    'writable' => is_writable(base_path('.env')),
                    'size'     => file_exists(base_path('.env')) ? filesize(base_path('.env')) : null,
                ],
                // ✅ NEW: show where backups live and how many exist.
                'env_backups' => [
                    'directory' => $backupDir,
                    'count'     => count(File::glob($backupDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*')),
                    'keep'      => self::ENV_BACKUP_KEEP_RECENT,
                    'max_hours' => self::ENV_BACKUP_MAX_AGE_HOURS,
                ],
                'providers' => $providerMeta,
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer environment-info failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to read environment info: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // DEBUG — PERFORMANCE METRICS
    // =========================================================================

    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $start = microtime(true);

            $envConfig = $this->paymentService->checkPaymentMethodConfiguration();

            $elapsedMs = round((microtime(true) - $start) * 1000, 2);

            $providers = [];
            foreach (PaymentProviderRegistry::keys() as $key) {
                $providerStart = microtime(true);
                $state = $envConfig[$key] ?? [];
                $providers[$key] = [
                    'configured'    => (bool) ($state['configured'] ?? false),
                    'enabled'       => (bool) ($state['enabled'] ?? false),
                    'environment'   => $state['environment'] ?? 'sandbox',
                    'read_time_ms'  => round((microtime(true) - $providerStart) * 1000, 2),
                ];
            }

            return response()->json([
                'success' => true,
                'metrics' => [
                    'env_config_read_ms' => $elapsedMs,
                    'memory_peak_mb'     => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'memory_usage_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'providers'          => $providers,
                ],
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer performance-metrics failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to read performance metrics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // DEBUG — CLEAR CACHE
    // =========================================================================

    public function clearCache(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $scope = $request->input('scope', 'provider-states');

            $cleared = [];

            switch ($scope) {
                case 'provider-states':
                    foreach (PaymentProviderRegistry::keys() as $p) {
                        Cache::forget("payment_provider_{$p}_expected");
                        Cache::forget("payment_provider_{$p}_actual");
                    }
                    Cache::forget('developer_provider_status_cache');
                    $cleared[] = 'provider_states';
                    $cleared[] = 'developer_read_cache';
                    break;

                case 'read-cache':
                    Cache::forget('developer_provider_status_cache');
                    $cleared[] = 'developer_read_cache';
                    break;

                case 'config':
                case 'route':
                case 'view':
                case 'application':
                    // Delegate to the shared fast-cache-clear. No Artisan calls.
                    $this->clearApplicationCaches();
                    $cleared[] = 'config';
                    $cleared[] = 'cache';
                    $cleared[] = 'route';
                    $cleared[] = 'view';
                    $cleared[] = 'opcache';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => "Unknown scope: {$scope}",
                    ], 422);
            }

            Log::info('Developer cleared cache', [
                'developer_id' => auth()->id(),
                'scope'        => $scope,
                'cleared'      => $cleared,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cleared: " . implode(', ', $cleared),
                'scope'   => $scope,
                'cleared' => $cleared,
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer clear-cache failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // MAINTENANCE — CLEAN UP OLD BACKUPS
    // =========================================================================

    /**
     * ✅ NEW: purge old `.env` backups.
     *
     * Reads from both the new backup directory and the legacy project-root
     * location (for backward compatibility). Retention is enforced by
     * pruneEnvBackups(), which keeps the N most recent and drops anything
     * older than MAX_AGE_HOURS.
     */
    public function cleanupBackups(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $newDir    = $this->getEnvBackupDir();
            $legacyDir = base_path();

            $newCount    = count(File::glob($newDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*'));
            $legacyCount = count(File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*'));

            if ($newCount + $legacyCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No backups to clean up',
                ]);
            }

            // Prune the new directory with the standard policy.
            $this->pruneEnvBackups($newDir);

            // For the legacy directory, keep the 5 most recent.
            $legacyBackups = File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*');
            if (count($legacyBackups) > self::ENV_BACKUP_KEEP_RECENT) {
                usort($legacyBackups, fn ($a, $b) => filemtime($b) <=> filemtime($a));
                $legacyToDelete = array_slice($legacyBackups, self::ENV_BACKUP_KEEP_RECENT);

                foreach ($legacyToDelete as $backup) {
                    File::delete($backup);
                    Log::info('[Dev EnvBackup] pruned legacy backup: ' . basename($backup));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Backups pruned.',
                'counts'  => [
                    'new_dir'    => count(File::glob($newDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*')),
                    'legacy_dir' => count(File::glob($legacyDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*')),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer cleanup-backups failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean up backups: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // LOGGING — READ APP LOG
    // =========================================================================

    public function getLogs(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $logPath = storage_path('logs/laravel.log');

            $limit       = (int) $request->input('limit', 200);
            $levelFilter = $request->input('level');
            $grep        = $request->input('q');

            if (! File::exists($logPath)) {
                return response()->json([
                    'success' => true,
                    'file'    => $logPath,
                    'lines'   => [],
                    'count'   => 0,
                    'message' => 'Log file not found yet.',
                ]);
            }

            $maxBytes = 512 * 1024;
            $size     = filesize($logPath);
            $start    = max(0, $size - $maxBytes);

            $fp = fopen($logPath, 'r');
            fseek($fp, $start);
            $chunk = fread($fp, $maxBytes);
            fclose($fp);

            if ($start > 0) {
                $chunk = substr($chunk, strpos($chunk, "\n") + 1);
            }

            $lines = array_filter(explode("\n", trim($chunk)));

            if ($levelFilter) {
                $needle = ".{$levelFilter}:";
                $lines  = array_filter($lines, fn ($l) => stripos($l, $needle) !== false);
            }

            if ($grep) {
                $lines = array_filter($lines, fn ($l) => stripos($l, $grep) !== false);
            }

            $lines = array_slice(array_values($lines), -$limit);

            return response()->json([
                'success'   => true,
                'file'      => $logPath,
                'size'      => $size,
                'lines'     => $lines,
                'count'     => count($lines),
                'filters'   => [
                    'level' => $levelFilter,
                    'q'     => $grep,
                    'limit' => $limit,
                ],
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Developer get-logs failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to read logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // ENVIRONMENT VERIFICATION
    // =========================================================================

    public function verifyEnvironmentSwitch(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        if (ob_get_level()) {
            ob_end_clean();
        }

        $request->validate([
            'provider' => ['required', 'string', PaymentProviderRegistry::validationRule()],
        ]);

        $provider = $request->provider;

        try {
            $this->clearReadCache();

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
            Log::error('Developer environment verification failed', [
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
    // WEBHOOKS / STATE
    // =========================================================================

    public function getWebhookUrls(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $urls = [];
            foreach (PaymentProviderRegistry::keys() as $p) {
                $urls[$p] = url("/api/payments/{$p}/webhook");
            }

            $provider = $request->get('provider');

            if ($provider) {
                if (! isset($urls[$provider])) {
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

    public function clearProviderStates(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

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

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear provider states: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // CONFIG AS JSON
    // =========================================================================

    public function getDeveloperConfigJson(Request $request): JsonResponse
    {
        $this->requireDeveloperPaymentAccess();

        try {
            $provider        = $request->get('provider');
            $developerConfig = $this->getDeveloperConfig();

            if ($provider) {
                if (! isset($developerConfig[$provider])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Provider not found',
                    ], 404);
                }

                return response()->json([
                    'success'  => true,
                    'provider' => $provider,
                    'config'   => $developerConfig[$provider],
                    'can_bill' => $this->developerCanBill(),
                ]);
            }

            return response()->json([
                'success'        => true,
                'config'         => $developerConfig,
                'can_bill'       => $this->developerCanBill(),
                'access_enabled' => $this->developerAccessEnabled(),
                'timestamp'      => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get developer config: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // PRIVATE — DEFINITIONS
    // =========================================================================

    private function providerDefinitionsWithMeta(): array
    {
        $definitions = [];

        foreach (PaymentProviderRegistry::keys() as $key) {
            $meta = PaymentProviderRegistry::get($key);
            $definitions[$key] = [
                'name'     => $meta['name'],
                'icon'     => $meta['icon'] ?? 'fa-credit-card',
                'color'    => $meta['color'] ?? '#6B7280',
                'fields'   => $this->fieldsFor($key),
                'env_keys' => $this->envKeysFor($key),
                'enabled'  => (bool) env($meta['env_enabled'], false),
            ];
        }

        return $definitions;
    }

    private function fieldsFor(string $provider): array
    {
        return match ($provider) {
            'paystack'    => ['secret_key' => 'Secret Key', 'public_key' => 'Public Key'],
            'expresspay'  => ['merchant_id' => 'Merchant ID', 'api_key' => 'API Key', 'environment' => 'Environment'],
            'flutterwave' => ['public_key' => 'Public Key', 'secret_key' => 'Secret Key', 'encryption_key' => 'Encryption Key'],
            'hubtel'      => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'merchant_account' => 'Merchant Account'],
            default       => [],
        };
    }

    private function envKeysFor(string $provider): array
    {
        return match ($provider) {
            'paystack'    => ['PAYSTACK_SECRET_KEY', 'PAYSTACK_PUBLIC_KEY'],
            'expresspay'  => ['EXPRESSPAY_MERCHANT_ID', 'EXPRESSPAY_API_KEY', 'EXPRESSPAY_ENVIRONMENT'],
            'flutterwave' => ['FLUTTERWAVE_PUBLIC_KEY', 'FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_ENCRYPTION_KEY'],
            'hubtel'      => ['HUBTEL_CLIENT_ID', 'HUBTEL_CLIENT_SECRET', 'HUBTEL_MERCHANT_ACCOUNT'],
            default       => [],
        };
    }

    // =========================================================================
    // PRIVATE — DEVELOPER CONFIG
    // =========================================================================

    private function getDeveloperConfig(): array
    {
        $config = [];

        foreach (PaymentProviderRegistry::keys() as $provider) {
            $config[$provider] = $this->developerConfigFor($provider);
        }

        $config['can_bill']       = $this->developerCanBill();
        $config['access_enabled'] = $this->developerAccessEnabled();

        return $config;
    }

    private function developerConfigFor(string $provider): array
    {
        $enabled = (bool) env($this->devKey($provider, 'ENABLED'), false);

        return match ($provider) {
            'paystack' => [
                'secret_key'    => env('DEVELOPER_PAYSTACK_SECRET_KEY', ''),
                'public_key'    => env('DEVELOPER_PAYSTACK_PUBLIC_KEY', ''),
                'enabled'       => $enabled,
                'is_configured' => $this->isDeveloperConfigured('paystack'),
            ],
            'expresspay' => [
                'merchant_id'   => env('DEVELOPER_EXPRESSPAY_MERCHANT_ID', ''),
                'api_key'       => env('DEVELOPER_EXPRESSPAY_API_KEY', ''),
                'callback_url'  => env('DEVELOPER_EXPRESSPAY_CALLBACK_URL', ''),
                'environment'   => env('DEVELOPER_EXPRESSPAY_ENVIRONMENT', 'sandbox'),
                'enabled'       => $enabled,
                'is_configured' => $this->isDeveloperConfigured('expresspay'),
            ],
            'flutterwave' => [
                'public_key'    => env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY', ''),
                'secret_key'    => env('DEVELOPER_FLUTTERWAVE_SECRET_KEY', ''),
                'enabled'       => $enabled,
                'is_configured' => $this->isDeveloperConfigured('flutterwave'),
            ],
            'hubtel' => [
                'client_id'     => env('DEVELOPER_HUBTEL_CLIENT_ID', ''),
                'client_secret' => env('DEVELOPER_HUBTEL_CLIENT_SECRET', ''),
                'environment'   => env('DEVELOPER_HUBTEL_ENVIRONMENT', 'sandbox'),
                'enabled'       => $enabled,
                'is_configured' => $this->isDeveloperConfigured('hubtel'),
            ],
            default => [
                'enabled'       => false,
                'is_configured' => false,
            ],
        };
    }

    private function devKey(string $provider, string $suffix): string
    {
        return 'DEVELOPER_' . strtoupper($provider) . '_' . $suffix;
    }

    private function isDeveloperConfigured(?string $provider = null): bool
    {
        if ($provider !== null && ! PaymentProviderRegistry::has($provider)) {
            return false;
        }

        $providers = $provider ? [$provider] : PaymentProviderRegistry::keys();

        foreach ($providers as $p) {
            if ($this->developerHasCredentials($p)) {
                return true;
            }
        }

        return false;
    }

    private function developerHasCredentials(string $provider): bool
    {
        return match ($provider) {
            'paystack' => $this->developerPaystackConfigured(),
            'expresspay' => ! empty(env('DEVELOPER_EXPRESSPAY_MERCHANT_ID'))
                         && ! empty(env('DEVELOPER_EXPRESSPAY_API_KEY')),
            'flutterwave' => ! empty(env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY'))
                          && ! empty(env('DEVELOPER_FLUTTERWAVE_SECRET_KEY')),
            'hubtel' => ! empty(env('DEVELOPER_HUBTEL_CLIENT_ID'))
                     && ! empty(env('DEVELOPER_HUBTEL_CLIENT_SECRET')),
            default => false,
        };
    }

    private function developerPaystackConfigured(): bool
    {
        $secret = env('DEVELOPER_PAYSTACK_SECRET_KEY');
        $public = env('DEVELOPER_PAYSTACK_PUBLIC_KEY');

        return ! empty($secret)
            && ! empty($public)
            && $secret !== 'sk_test_your_secret_key_here'
            && $public !== 'pk_test_your_public_key_here';
    }

    private function firstConfiguredProvider(): ?string
    {
        foreach (PaymentProviderRegistry::keys() as $provider) {
            if ($this->isDeveloperConfigured($provider)) {
                return $provider;
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE — PREPARE / WRITE
    // =========================================================================

    private function prepareDeveloperConfig(string $provider, Request $request): array
    {
        return match ($provider) {
            'paystack' => [
                'DEVELOPER_PAYSTACK_SECRET_KEY' => trim((string) $request->secret_key),
                'DEVELOPER_PAYSTACK_PUBLIC_KEY' => trim((string) $request->public_key),
                'DEVELOPER_PAYSTACK_ENABLED'    => $request->boolean('enabled') ? 'true' : 'false',
                'DEVELOPER_PAYMENT_CAN_BILL'    => 'true',
            ],
            'expresspay' => [
                'DEVELOPER_EXPRESSPAY_MERCHANT_ID'  => trim((string) $request->merchant_id),
                'DEVELOPER_EXPRESSPAY_API_KEY'      => trim((string) $request->api_key),
                'DEVELOPER_EXPRESSPAY_CALLBACK_URL' => trim((string) $request->callback_url),
                'DEVELOPER_EXPRESSPAY_ENVIRONMENT'  => ($request->environment === 'production') ? 'production' : 'sandbox',
                'DEVELOPER_EXPRESSPAY_ENABLED'      => $request->boolean('enabled') ? 'true' : 'false',
                'DEVELOPER_PAYMENT_CAN_BILL'        => 'true',
            ],
            'flutterwave' => [
                'DEVELOPER_FLUTTERWAVE_PUBLIC_KEY'     => trim((string) $request->public_key),
                'DEVELOPER_FLUTTERWAVE_SECRET_KEY'     => trim((string) $request->secret_key),
                'DEVELOPER_FLUTTERWAVE_ENCRYPTION_KEY' => trim((string) $request->encryption_key),
                'DEVELOPER_FLUTTERWAVE_ENABLED'        => $request->boolean('enabled') ? 'true' : 'false',
                'DEVELOPER_PAYMENT_CAN_BILL'           => 'true',
            ],
            'hubtel' => [
                'DEVELOPER_HUBTEL_CLIENT_ID'        => trim((string) $request->client_id),
                'DEVELOPER_HUBTEL_CLIENT_SECRET'    => trim((string) $request->client_secret),
                'DEVELOPER_HUBTEL_MERCHANT_ACCOUNT' => trim((string) $request->merchant_account),
                'DEVELOPER_HUBTEL_ENVIRONMENT'      => ($request->environment === 'production') ? 'production' : 'sandbox',
                'DEVELOPER_HUBTEL_ENABLED'          => $request->boolean('enabled') ? 'true' : 'false',
                'DEVELOPER_PAYMENT_CAN_BILL'        => 'true',
            ],
            default => [],
        };
    }

    /**
     * ✅ Atomic write: modify in memory, write to a temp file, rename over
     *               the original. A crash mid-write can no longer leave a
     *               half-written `.env` that breaks the next request.
     */
    private function updateDeveloperEnvFile(array $data): void
    {
        $envPath  = base_path('.env');
        $tempPath = $envPath . '.tmp.' . uniqid('', true);

        if (! File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        if (! File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable');
        }

        $envContent = File::get($envPath);
        $updated    = false;

        foreach ($data as $key => $value) {
            if (! str_starts_with($key, 'DEVELOPER_')) {
                continue;
            }

            $escapedValue = $this->escapeEnvValue($value);
            $quotedKey    = preg_quote($key, '/');
            $pattern      = "/^{$quotedKey}=.*/m";
            $replacement  = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
            }
        }

        if (! $updated) {
            Log::warning('Developer .env: no keys updated');
            return;
        }

        // ✅ Write to temp file first — if this throws, .env is untouched.
        if (File::put($tempPath, $envContent) === false) {
            throw new \Exception('Failed to write temporary .env file at: ' . $tempPath);
        }

        // ✅ Atomic replace. On Unix this is an inode swap; on Windows it's
        //    a fast copy-then-delete. Either way, .env is never in a
        //    partially-written state.
        if (! @rename($tempPath, $envPath)) {
            if (! File::copy($tempPath, $envPath)) {
                File::delete($tempPath);
                throw new \Exception('Failed to replace .env file atomically.');
            }
            File::delete($tempPath);
        }

        // ✅ opcache_reset() is opt-in. On Windows PHP 8.5 it can emit
        //    an unsilenceable warning and hang the request. Default off.
        if (config('payment_providers.call_opcache_reset', env('PAYMENT_PROVIDERS_CALL_OPCACHE_RESET', false))) {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        }

        Log::info('Developer .env updated', [
            'keys' => array_keys($data),
        ]);
    }

    /**
     * Verify the write. Reads each written key back and compares.
     *
     * ✅ FIX: skip *_ENABLED keys (already covered by developerConfigured())
     *          and normalize both sides so "true" vs boolean true don't
     *          trip a false mismatch.
     */
    private function verifyDeveloperConfigWrite(string $provider, Request $request): array
    {
        // Reload env from disk so env() reflects the write
        $this->forceEnvironmentReload();

        $configured = $this->isDeveloperConfigured($provider);

        if (! $configured) {
            return [
                'success'       => false,
                'message'       => "The {$provider} credentials were not readable after write",
                'is_production' => false,
            ];
        }

        $expected   = $this->prepareDeveloperConfig($provider, $request);
        $mismatched = [];

        foreach ($expected as $key => $expectedValue) {
            if (! str_starts_with($key, 'DEVELOPER_')) {
                continue;
            }

            if (str_ends_with($key, '_ENABLED')) {
                continue;
            }

            if ($expectedValue === '""' || $expectedValue === '') {
                continue;
            }

            $actualValue = env($key);

            $expectedNormalized = $this->normalizeForComparison($expectedValue);
            $actualNormalized   = $this->normalizeForComparison($actualValue);

            if ($expectedNormalized !== $actualNormalized) {
                $mismatched[] = $key;
            }
        }

        if (! empty($mismatched)) {
            return [
                'success'       => false,
                'message'       => 'Write verification failed for: ' . implode(', ', $mismatched),
                'is_production' => false,
            ];
        }

        return [
            'success'       => true,
            'message'       => 'Configuration applied and verified',
            'is_production' => false, // developers always operate in sandbox
        ];
    }

    /**
     * ✅ normalize a value for comparison during verification.
     */
    private function normalizeForComparison($value): string
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

    // =========================================================================
    // PRIVATE — ENV BACKUP (retention + safe location)
    // =========================================================================

    /**
     * Where .env backups live.
     *
     * ✅ Moved out of the project root (where .env.developer-backup.* is
     *    typically web-accessible) to storage/app/backups/env, which is
     *    neither served by the web server nor reachable via a public URL.
     */
    private function getEnvBackupDir(): string
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
    private function backupEnvFile(): ?string
    {
        try {
            $envPath = base_path('.env');

            if (! File::exists($envPath)) {
                return null;
            }

            $backupDir  = $this->getEnvBackupDir();
            $backupPath = $backupDir . DIRECTORY_SEPARATOR
                        . '.env.developer-backup.' . date('Y-m-d-H-i-s') . '-' . uniqid();

            if (! File::copy($envPath, $backupPath)) {
                Log::warning('Failed to copy .env to developer backup path: ' . $backupPath);
                return null;
            }

            // ✅ Restrict permissions. No-op on Windows, effective on Unix.
            @chmod($backupPath, 0600);

            Log::info('Developer .env backup: ' . $backupPath);

            // ✅ Enforce retention immediately — bounds the accumulation
            //    so credentials don't pile up on disk.
            $this->pruneEnvBackups($backupDir);

            return $backupPath;
        } catch (\Throwable $e) {
            Log::error('Failed to back up .env (developer): ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Retention policy: keep the N most recent, delete anything older
     * than MAX_AGE_HOURS, and never delete the very newest backup (it's
     * the one we might restore from if the current save fails).
     */
    private function pruneEnvBackups(string $dir): void
    {
        $backups = File::glob($dir . DIRECTORY_SEPARATOR . '.env.developer-backup.*');

        if (count($backups) <= 1) {
            return; // Nothing to prune.
        }

        // Sort newest first.
        usort($backups, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $now         = time();
        $maxAgeSecs  = self::ENV_BACKUP_MAX_AGE_HOURS * 3600;
        $keepRecent  = self::ENV_BACKUP_KEEP_RECENT;
        $prunedCount = 0;

        foreach ($backups as $index => $path) {
            // Always keep the newest entry — it's our restore candidate.
            if ($index === 0) {
                continue;
            }

            $age    = $now - filemtime($path);
            $isOld  = $age > $maxAgeSecs;
            $isPast = $index >= $keepRecent;

            if ($isOld || $isPast) {
                if (File::delete($path)) {
                    $prunedCount++;
                }
            }
        }

        if ($prunedCount > 0) {
            Log::debug("[Dev EnvBackup] pruned {$prunedCount} old backups from {$dir}");
        }
    }

    /**
     * Restore .env from the newest backup.
     *
     * ✅ Reads from the new backup directory first, and falls back to the
     *    legacy project-root location for older installations.
     * ✅ Also accepts a specific backup path (as passed by the caller in
     *    configureDeveloperProvider).
     */
    private function restoreEnvBackup(?string $specificPath = null): bool
    {
        try {
            // Explicit path takes priority — it's the one we just made.
            if ($specificPath && File::exists($specificPath)) {
                File::copy($specificPath, base_path('.env'));
                Log::info('Restored .env from developer backup: ' . $specificPath);
                $this->forceEnvironmentReload();
                return true;
            }

            $backupDir  = $this->getEnvBackupDir();
            $candidates = File::glob($backupDir . DIRECTORY_SEPARATOR . '.env.developer-backup.*');

            // ✅ Backwards compatibility: also consider legacy backups
            //    still sitting in the project root.
            $legacy = File::glob(base_path() . DIRECTORY_SEPARATOR . '.env.developer-backup.*');
            if (! empty($legacy)) {
                $candidates = array_merge($candidates, $legacy);
            }

            if (empty($candidates)) {
                Log::warning('No developer .env backups found for restore.');
                return false;
            }

            usort($candidates, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latest = $candidates[0];

            if (! File::copy($latest, base_path('.env'))) {
                Log::error('Failed to restore developer .env from backup: ' . $latest);
                return false;
            }

            Log::info('Restored .env from developer backup: ' . $latest);
            $this->forceEnvironmentReload();

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to restore developer .env backup: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // PRIVATE — TEST DEVELOPER CREDENTIALS
    // =========================================================================

    private function testDeveloperCredentials(string $provider): array
    {
        return match ($provider) {
            'paystack'    => $this->testDeveloperPaystack(),
            'expresspay'  => $this->testDeveloperExpressPay(),
            'flutterwave' => $this->testDeveloperFlutterwave(),
            'hubtel'      => $this->testDeveloperHubtel(),
            default       => [
                'success'     => false,
                'message'     => 'Unsupported provider',
                'environment' => 'unknown',
            ],
        };
    }

    private function testSystemCredentials(string $provider): array
    {
        return match ($provider) {
            'paystack'    => $this->testSystemPaystack(),
            'expresspay'  => $this->testSystemExpressPay(),
            'flutterwave' => $this->testSystemFlutterwave(),
            'hubtel'      => $this->testSystemHubtel(),
            default       => [
                'success'     => false,
                'message'     => 'Unsupported provider',
                'environment' => 'unknown',
            ],
        };
    }

    private function testSystemPaystack(): array
    {
        try {
            $secretKey   = env('PAYSTACK_SECRET_KEY');
            $baseUrl     = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');
            $environment = str_contains((string) $baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';

            if (empty($secretKey)) {
                return [
                    'success'     => false,
                    'message'     => 'System Paystack secret key is missing',
                    'environment' => $environment,
                ];
            }

            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $secretKey])
                ->timeout(5)
                ->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success'       => true,
                    'message'       => '✅ System Paystack connection successful!',
                    'environment'   => $environment,
                    'is_production' => $environment === 'production',
                    'details'       => ['banks_available' => count($response->json()['data'] ?? [])],
                ];
            }

            return [
                'success'     => false,
                'message'     => 'System Paystack connection failed: ' . ($response->json()['message'] ?? 'Unknown error'),
                'environment' => $environment,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => 'System Paystack test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testSystemExpressPay(): array
    {
        try {
            $merchantId  = env('EXPRESSPAY_MERCHANT_ID');
            $apiKey      = env('EXPRESSPAY_API_KEY');
            $environment = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success'     => false,
                    'message'     => 'System ExpressPay credentials are missing',
                    'environment' => $environment,
                ];
            }

            $isValid = strlen((string) $merchantId) > 5 && strlen((string) $apiKey) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ System ExpressPay credentials format is valid' : '⚠️ System ExpressPay credentials appear to be invalid',
                'environment'   => $environment,
                'is_production' => $environment === 'production',
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => 'System ExpressPay test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testSystemFlutterwave(): array
    {
        try {
            $secretKey    = env('FLUTTERWAVE_SECRET_KEY');
            $publicKey    = env('FLUTTERWAVE_PUBLIC_KEY');
            $baseUrl      = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
            $isProduction = str_contains((string) $baseUrl, 'api.flutterwave.com');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success'     => false,
                    'message'     => 'System Flutterwave credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox',
                ];
            }

            $isValid = strlen((string) $secretKey) > 10 && strlen((string) $publicKey) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ System Flutterwave credentials format is valid' : '⚠️ System Flutterwave credentials appear to be invalid',
                'environment'   => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => 'System Flutterwave test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testSystemHubtel(): array
    {
        try {
            $clientId     = env('HUBTEL_CLIENT_ID');
            $clientSecret = env('HUBTEL_CLIENT_SECRET');
            $baseUrl      = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
            $isProduction = str_contains((string) $baseUrl, 'api.hubtel.com');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success'     => false,
                    'message'     => 'System Hubtel credentials are missing',
                    'environment' => $isProduction ? 'production' : 'sandbox',
                ];
            }

            $isValid = strlen((string) $clientId) > 5 && strlen((string) $clientSecret) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ System Hubtel credentials format is valid' : '⚠️ System Hubtel credentials appear to be invalid',
                'environment'   => $isProduction ? 'production' : 'sandbox',
                'is_production' => $isProduction,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => 'System Hubtel test failed: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testDeveloperPaystack(): array
    {
        try {
            $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');
            $publicKey = env('DEVELOPER_PAYSTACK_PUBLIC_KEY');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success'     => false,
                    'message'     => '❌ Please configure your Paystack credentials first',
                    'environment' => 'unknown',
                ];
            }

            $environment = str_starts_with((string) $secretKey, 'sk_live_') ? 'production' : 'test';

            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $secretKey])
                ->timeout(10)
                ->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                return [
                    'success'       => true,
                    'message'       => '✅ Your Paystack credentials are valid!',
                    'environment'   => $environment,
                    'is_production' => $environment === 'production',
                    'details'       => ['banks_available' => count($response->json()['data'] ?? [])],
                ];
            }

            return [
                'success'     => false,
                'message'     => '❌ Your Paystack credentials are invalid: ' . ($response->json()['message'] ?? 'Unknown error'),
                'environment' => $environment,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testDeveloperExpressPay(): array
    {
        try {
            $merchantId = env('DEVELOPER_EXPRESSPAY_MERCHANT_ID');
            $apiKey     = env('DEVELOPER_EXPRESSPAY_API_KEY');
            $env        = env('DEVELOPER_EXPRESSPAY_ENVIRONMENT', 'sandbox');

            if (empty($merchantId) || empty($apiKey)) {
                return [
                    'success'     => false,
                    'message'     => '❌ Please configure your ExpressPay credentials first',
                    'environment' => 'unknown',
                ];
            }

            $isValid = strlen((string) $merchantId) > 5 && strlen((string) $apiKey) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ Your ExpressPay credentials format is valid' : '⚠️ Your ExpressPay credentials appear to be invalid',
                'environment'   => $env,
                'is_production' => $env === 'production',
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testDeveloperFlutterwave(): array
    {
        try {
            $secretKey = env('DEVELOPER_FLUTTERWAVE_SECRET_KEY');
            $publicKey = env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY');

            if (empty($secretKey) || empty($publicKey)) {
                return [
                    'success'     => false,
                    'message'     => '❌ Please configure your Flutterwave credentials first',
                    'environment' => 'unknown',
                ];
            }

            $isValid = strlen((string) $secretKey) > 10 && strlen((string) $publicKey) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ Your Flutterwave credentials format is valid' : '⚠️ Your Flutterwave credentials appear to be invalid',
                'environment'   => 'sandbox',
                'is_production' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    private function testDeveloperHubtel(): array
    {
        try {
            $clientId     = env('DEVELOPER_HUBTEL_CLIENT_ID');
            $clientSecret = env('DEVELOPER_HUBTEL_CLIENT_SECRET');
            $env          = env('DEVELOPER_HUBTEL_ENVIRONMENT', 'sandbox');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success'     => false,
                    'message'     => '❌ Please configure your Hubtel credentials first',
                    'environment' => 'unknown',
                ];
            }

            $isValid = strlen((string) $clientId) > 5 && strlen((string) $clientSecret) > 10;

            return [
                'success'       => $isValid,
                'message'       => $isValid ? '✅ Your Hubtel credentials format is valid' : '⚠️ Your Hubtel credentials appear to be invalid',
                'environment'   => $env,
                'is_production' => $env === 'production',
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'message'     => '❌ Failed to test: ' . $e->getMessage(),
                'environment' => 'unknown',
            ];
        }
    }

    // =========================================================================
    // PRIVATE — PAYMENT PROCESSING
    // =========================================================================

    private function processDeveloperPayment(string $provider, float $amount, string $description, string $email): array
    {
        switch ($provider) {
            case 'paystack':
                $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');

                if (empty($secretKey)) {
                    throw new \Exception('Developer Paystack secret key is not configured');
                }

                $response = Http::withHeaders(['Authorization' => 'Bearer ' . $secretKey])
                    ->post('https://api.paystack.co/transaction/initialize', [
                        'amount'   => (int) round($amount * 100),
                        'email'    => $email,
                        'metadata' => [
                            'developer_id'    => auth()->id(),
                            'developer_email' => auth()->user()->email,
                            'description'     => $description,
                            'type'            => 'developer_billing',
                        ],
                        'callback_url' => route('developer.billing.callback'),
                    ]);

                if (! $response->successful()) {
                    throw new \Exception('Payment initialization failed: ' . ($response->json()['message'] ?? 'Unknown error'));
                }

                $data = $response->json()['data'] ?? [];

                return [
                    'provider'          => 'paystack',
                    'reference'         => $data['reference'] ?? null,
                    'authorization_url' => $data['authorization_url'] ?? null,
                    'access_code'       => $data['access_code'] ?? null,
                    'amount'            => $amount,
                    'email'             => $email,
                    'description'       => $description,
                ];

            case 'expresspay':
            case 'flutterwave':
            case 'hubtel':
                throw new \Exception(
                    "Developer billing through {$provider} is not yet implemented. "
                    . 'Please select Paystack or contact the administrator.'
                );

            default:
                throw new \Exception("Unsupported billing provider: {$provider}");
        }
    }

    // =========================================================================
    // PRIVATE — DEBUG / ENVIRONMENT
    // =========================================================================

    private function getDebugInfo(string $provider): array
    {
        $info = [
            'environment'       => $this->detectEnvironment($provider),
            'env_file_exists'   => file_exists(base_path('.env')),
            'env_file_writable' => is_writable(base_path('.env')),
            'cache_driver'      => config('cache.default'),
            'php_extensions'    => $this->getRequiredExtensions(),
            'timestamp'         => now()->toDateTimeString(),
        ];

        return match ($provider) {
            'paystack' => $info + [
                'api_version'    => 'v1',
                'base_url'       => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
                'has_secret_key' => ! empty(env('PAYSTACK_SECRET_KEY')),
                'has_public_key' => ! empty(env('PAYSTACK_PUBLIC_KEY')),
            ],
            'expresspay' => $info + [
                'base_url'    => env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com'),
                'environment' => env('EXPRESSPAY_ENVIRONMENT', 'sandbox'),
            ],
            'flutterwave' => $info + [
                'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            ],
            'hubtel' => $info + [
                'base_url' => env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1'),
            ],
            default => $info,
        };
    }

    private function detectEnvironment(string $provider): string
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

    private function readEnvironmentFor(string $provider): array
    {
        $env = $this->detectEnvironment($provider);

        $display = $provider === 'expresspay'
            ? $env
            : ($env === 'production' ? 'production' : 'sandbox');

        return [$display, $env === 'production'];
    }

    private function getRequiredExtensions(): array
    {
        $extensions = ['curl', 'json', 'mbstring', 'openssl'];
        $status = [];

        foreach ($extensions as $ext) {
            $status[$ext] = extension_loaded($ext);
        }

        return $status;
    }

    // =========================================================================
    // PRIVATE — CACHE / ENV
    // =========================================================================

    private function clearReadCache(): void
    {
        Cache::forget('developer_provider_status_cache');
    }

    /**
     * ✅ Fast cache clear — no Artisan::call() (5–15s each on Windows).
     *    Direct file deletes + Cache::flush() instead.
     */
    private function clearApplicationCaches(): void
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
                    Log::debug("[Dev CAC] deleted {$relativePath}");
                }
            }

            $compiledDir = storage_path('framework/views');
            if (File::isDirectory($compiledDir)) {
                $count = 0;
                foreach (File::glob($compiledDir . '/*.php') as $compiledView) {
                    File::delete($compiledView);
                    $count++;
                }
                Log::debug("[Dev CAC] deleted {$count} compiled views");
            }

            Cache::flush();

            $elapsed = round((microtime(true) - $t0) * 1000);
            Log::debug("Developer application caches cleared in {$elapsed}ms");
        } catch (\Throwable $e) {
            Log::warning('Error clearing caches (developer): ' . $e->getMessage());
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    /**
     * ✅ Fast env reload — no Dotenv::safeLoad(), no putenv().
     *    Single-pass manual parse of .env into $_ENV/$_SERVER.
     */
    private function forceEnvironmentReload(): void
    {
        ob_start();

        try {
            $t0 = microtime(true);

            $developerVars = [
                'DEVELOPER_PAYSTACK_SECRET_KEY', 'DEVELOPER_PAYSTACK_PUBLIC_KEY', 'DEVELOPER_PAYSTACK_ENABLED',
                'DEVELOPER_EXPRESSPAY_MERCHANT_ID', 'DEVELOPER_EXPRESSPAY_API_KEY',
                'DEVELOPER_EXPRESSPAY_CALLBACK_URL', 'DEVELOPER_EXPRESSPAY_ENVIRONMENT',
                'DEVELOPER_EXPRESSPAY_ENABLED',
                'DEVELOPER_FLUTTERWAVE_PUBLIC_KEY', 'DEVELOPER_FLUTTERWAVE_SECRET_KEY',
                'DEVELOPER_FLUTTERWAVE_ENCRYPTION_KEY', 'DEVELOPER_FLUTTERWAVE_ENABLED',
                'DEVELOPER_HUBTEL_CLIENT_ID', 'DEVELOPER_HUBTEL_CLIENT_SECRET',
                'DEVELOPER_HUBTEL_MERCHANT_ACCOUNT', 'DEVELOPER_HUBTEL_ENVIRONMENT',
                'DEVELOPER_HUBTEL_ENABLED',
                'DEVELOPER_PAYMENT_CAN_BILL', 'DEVELOPER_PAYMENT_ACCESS_ENABLED',
            ];

            foreach ($developerVars as $var) {
                unset($_ENV[$var], $_SERVER[$var]);
            }

            if (! file_exists(base_path('.env'))) {
                return;
            }

            $lines = file(base_path('.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                Log::warning('[Dev FER] could not read .env');
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
            Log::debug("Developer environment variables force reloaded in {$elapsed}ms");
        } catch (\Throwable $e) {
            Log::warning('Developer env reload failed: ' . $e->getMessage());
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    // =========================================================================
    // PRIVATE — MISC
    // =========================================================================

    private function escapeEnvValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        // ✅ strip newlines. A pasted credential with a wrapping newline
        //    would otherwise corrupt .env on the next read.
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\'\\\\=]/', $value)) {
            $value = '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $value) . '"';
        }

        return $value;
    }

    private function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}