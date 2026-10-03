<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use App\Jobs\UpdateSmsConfiguration;
use App\Jobs\TestSmsConnection;
use App\Jobs\SendTestSmsMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SmsProviderController extends Controller
{
    protected SmsService $smsService;

    /**
     * Fields required per provider before it can be enabled.
     */
    protected array $requiredFields = [
    // sender_id is intentionally omitted — it falls back to the global
    // default from System Settings when empty. Only the credentials that
    // the provider genuinely cannot work without are listed here.
    'arkesel'        => ['api_key'],
    'twilio'         => ['account_sid', 'auth_token', 'from_number'],
    'africastalking' => ['api_key', 'username'],
    'hubtel'         => ['client_id', 'client_secret'],
    'nalosolutions'  => ['api_key'],
];

    /**
     * Maps provider field names to config paths.
     */
    protected array $configKeys = [
        'arkesel' => [
            'api_key'   => 'arkesel.api_key',
            'sender_id' => 'arkesel.sender_id',
            'base_url'  => 'arkesel.base_url',
        ],
        'twilio' => [
            'account_sid' => 'twilio.account_sid',
            'auth_token'  => 'twilio.auth_token',
            'from_number' => 'twilio.from_number',
        ],
        'africastalking' => [
            'api_key'   => 'africastalking.api_key',
            'username'  => 'africastalking.username',
            'sender_id' => 'africastalking.sender_id',
        ],
        'hubtel' => [
            'client_id'     => 'hubtel.client_id',
            'client_secret' => 'hubtel.client_secret',
            'sender_id'     => 'hubtel.sender_id',
        ],
        'nalosolutions' => [
            'api_key'   => 'nalosolutions.api_key',
            'sender_id' => 'nalosolutions.sender_id',
            'base_url'  => 'nalosolutions.base_url',
        ],
    ];

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /* ============================================================
     | ROLE HELPERS — enforced on every write method
     * ============================================================ */

    /**
     * Is the current user a developer (type 5 or has "developer" role)?
     */
    protected function isDeveloper(?object $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasRole('developer') || (int) $user->type === 5;
    }

    /**
     * Guard: only developers may mutate SMS provider configuration.
     * Super-admins (type 0) have read-only access. Admins (type 1) are
     * blocked at the route middleware layer; this is defense-in-depth.
     */
    protected function requireDeveloper(): void
    {
        $user = auth()->user();

        if (!$this->isDeveloper($user)) {
            Log::warning('[SMS-CONFIG] Non-developer attempted SMS provider write', [
                'user_id'   => $user?->id,
                'user_type' => $user?->type,
                'route'     => request()->route()?->getName(),
                'url'       => request()->fullUrl(),
                'method'    => request()->method(),
            ]);

            abort(403, 'Only developers can modify SMS provider configuration.');
        }
    }

    /* ============================================================
     | INDEX — read-only for super-admins, full access for developers
     * ============================================================ */
    public function index()
    {
        $user        = auth()->user();
        $isDeveloper = $this->isDeveloper($user);

        $providers = [
            'arkesel' => [
                'name'          => 'Arkesel SMS',
                'fields'        => [
                    'api_key'   => 'API Key',
                    'sender_id' => 'Sender ID',
                    'base_url'  => 'Base URL',
                ],
                'enabled'       => config('sms.providers.arkesel.enabled', false),
                'api_version'   => 'v2',
                'send_endpoint' => '/api/v2/sms/send',
            ],
            'twilio' => [
                'name'   => 'Twilio',
                'fields' => [
                    'account_sid' => 'Account SID',
                    'auth_token'  => 'Auth Token',
                    'from_number' => 'From Number',
                ],
                'enabled' => config('sms.providers.twilio.enabled', false),
            ],
            'africastalking' => [
                'name'   => 'Africa\'s Talking',
                'fields' => [
                    'api_key'   => 'API Key',
                    'username'  => 'Username',
                    'sender_id' => 'Sender ID',
                ],
                'enabled' => config('sms.providers.africastalking.enabled', false),
            ],
            'hubtel' => [
                'name'   => 'Hubtel SMS',
                'fields' => [
                    'client_id'     => 'Client ID',
                    'client_secret' => 'Client Secret',
                    'sender_id'     => 'Sender ID',
                ],
                'enabled' => config('sms.providers.hubtel.enabled', false),
            ],
            'nalosolutions' => [
                'name'   => 'Nalo Solutions',
                'fields' => [
                    'api_key'   => 'API Key',
                    'sender_id' => 'Sender ID',
                    'base_url'  => 'Base URL',
                ],
                'enabled' => config('sms.providers.nalosolutions.enabled', false),
            ],
        ];

        // Attach the effective sender ID + its source to every provider so
        // the read-only view can display them without exposing credentials.
        foreach ($providers as $key => &$provider) {
            $provider['effective_sender_id'] = $this->smsService->resolveSenderId($key);
            $provider['sender_id_source']    = $this->smsService->explainSenderId($key)['source'];
        }
        unset($provider);

        $configurationStatus    = $this->smsService->checkSmsProviderConfiguration();
        $systemStatus           = $this->smsService->getSystemStatus();
        $hasPendingConfigUpdate = session()->has('pending_sms_config_update');
        $pendingConfigUpdate    = session()->get('pending_sms_config_update', null);

        // Global default (admin-set) — super-admins need to know what value
        // is being used as a fallback when a per-provider value is missing.
        $globalSenderId = null;
        try {
            $settings       = \App\Models\SystemSetting::getSettings();
            $globalSenderId = $settings?->sms_sender_id;
        } catch (\Throwable $e) {
            // Settings table may not exist yet — non-fatal.
        }

        return view('admin.sms.providers', compact(
            'providers',
            'configurationStatus',
            'systemStatus',
            'hasPendingConfigUpdate',
            'pendingConfigUpdate',
            'isDeveloper',
            'globalSenderId'
        ));
    }

    /* ============================================================
     | CONFIGURE PROVIDER — DEVELOPER ONLY
     * ============================================================ */
    public function configureProvider(Request $request)
    {
        $this->requireDeveloper();

        $isAjax = $request->ajax() || $request->wantsJson();

        Log::info('[SMS-CONFIG] Incoming request', [
            'provider'      => $request->input('provider'),
            'enabled_raw'   => $request->input('enabled'),
            'enabled_bool'  => $request->boolean('enabled'),
            'sender_id'     => $request->input('sender_id'),
            'api_key_set'   => !empty($request->input('api_key')),
            'base_url'      => $request->input('base_url'),
            'all_keys'      => array_keys($request->all()),
            'is_ajax'       => $isAjax,
        ]);

        $validator = Validator::make($request->all(), [
            'provider'  => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
            'enabled'   => 'sometimes|boolean',
            'sender_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Za-z0-9 _-]*$/',
            ],
        ], [
            'provider.required' => 'Provider is required.',
            'provider.in'       => 'Unknown SMS provider.',
            'sender_id.regex'   => 'Sender ID may only contain letters, numbers, spaces, hyphens, and underscores.',
            'sender_id.max'     => 'Sender ID cannot exceed 11 characters.',
        ]);

        if ($validator->fails()) {
            Log::warning('[SMS-CONFIG] Base validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return $this->respondError(
                $isAjax,
                'Validation failed',
                422,
                ['errors' => $validator->errors()->toArray()]
            );
        }

        $provider = $request->provider;

        try {
            $merged = $this->mergeWithExistingConfig($provider, $request);

            Log::info('[SMS-CONFIG] Merged data', [
                'provider' => $provider,
                'merged'   => array_map(
                    fn ($v, $k) => (str_contains($k, 'key') || str_contains($k, 'token') || str_contains($k, 'secret'))
                        ? ($v ? '***' . substr($v, -4) : '(empty)')
                        : $v,
                    $merged,
                    array_keys($merged)
                ),
            ]);

            if ($request->boolean('enabled')) {
                $missing = [];
                foreach ($this->requiredFields[$provider] ?? [] as $field) {
                    if (empty(trim((string) ($merged[$field] ?? '')))) {
                        $missing[] = $field;
                    }
                }

                if (!empty($missing)) {
                    Log::warning('[SMS-CONFIG] Missing required fields after merge', [
                        'provider' => $provider,
                        'missing'  => $missing,
                    ]);

                    return $this->respondError(
                        $isAjax,
                        '❌ Missing required fields when enabled: ' . implode(', ', $missing),
                        422,
                        [
                            'errors'  => array_fill_keys($missing, ['Required when provider is enabled']),
                            'missing' => $missing,
                        ]
                    );
                }
            }

            $envData = $this->getProviderEnvDataFromMerged($provider, $merged, $request->boolean('enabled'));

            $validationResult = $this->smsService->validateProviderConfiguration($provider, $merged);

            if (!($validationResult['success'] ?? false)) {
                Log::warning('[SMS-CONFIG] SmsService validation failed', [
                    'provider' => $provider,
                    'result'   => $validationResult,
                ]);

                return $this->respondError(
                    $isAjax,
                    '❌ Configuration validation failed: ' . ($validationResult['message'] ?? 'Unknown error'),
                    422,
                    ['errors' => $validationResult['errors'] ?? []]
                );
            }

            // Defer the .env write — no Artisan calls
            $this->deferEnvWrite($provider, $envData);

            $msg = '✅ ' . $this->getProviderName($provider) . ' configuration updated successfully!';

            if ($isAjax) {
                return response()->json([
                    'success'        => true,
                    'message'        => $msg,
                    'provider'       => $provider,
                    'refresh_status' => true,
                ]);
            }

            return redirect()->route('admin.sms-providers.index')
                ->with('success', $msg)
                ->with('provider', $provider);

        } catch (\Throwable $e) {
            Log::error("Failed to configure {$provider} SMS: " . $e->getMessage(), [
                'provider'     => $provider,
                'request_data' => $request->except(['api_key', 'auth_token', 'client_secret']),
                'exception'    => $e,
            ]);

            return $this->respondError(
                $isAjax,
                '❌ Failed to configure SMS provider: ' . $e->getMessage(),
                500
            );
        }
    }

    /* ============================================================
     | DEFER ENV WRITE — NO ARTISAN CALLS
     * ============================================================ */
    protected function deferEnvWrite(string $provider, array $envData): void
    {
        app()->terminating(function () use ($provider, $envData) {
            try {
                $this->backupEnvFile();
                $this->updateEnvFile($envData);

                // Delete bootstrap/cache/config.php WITHOUT booting an
                // Artisan kernel. Laravel rebuilds it on the next request.
                $this->deleteConfigCacheFile();

                $verification  = $this->verifyEnvFile($envData);
                $failedUpdates = array_filter($verification, fn ($v) => !$v['match']);

                if (!empty($failedUpdates)) {
                    Log::warning("[SMS-CONFIG] Deferred verification failed for {$provider}", $failedUpdates);
                    $this->restoreEnvBackup();
                    return;
                }

                Log::info("[SMS-CONFIG] Deferred env write completed for {$provider}", [
                    'provider'     => $provider,
                    'verification' => $verification,
                ]);
            } catch (\Throwable $e) {
                Log::error("[SMS-CONFIG] Deferred env write failed for {$provider}: " . $e->getMessage());
                $this->restoreEnvBackup();
            }
        });
    }

    /* ============================================================
     | CONFIG CACHE FILE HELPER (no Artisan)
     * ============================================================ */
    protected function deleteConfigCacheFile(): void
    {
        $files = [
            'config.php',
            'routes-v7.php',
            'services.php',
            'packages.php',
        ];

        foreach ($files as $file) {
            $path = base_path("bootstrap/cache/{$file}");
            if (File::exists($path)) {
                try {
                    File::delete($path);
                } catch (\Throwable $e) {
                    // Ignore individual file failures — Laravel rebuilds what's needed
                }
            }
        }
    }

    protected function flushConfigCache(): void
    {
        try {
            $this->deleteConfigCacheFile();

            try {
                Cache::flush();
            } catch (\Throwable $e) {
                // Cache store may be unavailable; ignore
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        } catch (\Throwable $e) {
            Log::warning('Cache flush failed: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | MERGE INCOMING WITH EXISTING CONFIG
     * ============================================================ */
    protected function mergeWithExistingConfig(string $provider, Request $request): array
{
    $map    = $this->configKeys[$provider] ?? [];
    $merged = [];

    foreach ($map as $field => $configPath) {
        // Distinguish two cases:
        //
        //   1. The field was NOT included in the request at all.
        //      This happens when a form omits a field it doesn't manage
        //      (e.g. a read-only view, or a partial update). In this case,
        //      keep the existing config value so we don't accidentally
        //      wipe it.
        //
        //   2. The field WAS included, but may be empty.
        //      This is the developer explicitly clearing the value.
        //      Honour it — write the empty string as-is.
        //
        if (!$request->has($field)) {
            $existing = config("sms.providers.{$configPath}");
            $merged[$field] = (string) ($existing ?? '');
            continue;
        }

        $incoming = $request->input($field);
        $incoming = is_string($incoming) ? trim($incoming) : $incoming;

        $merged[$field] = (string) ($incoming ?? '');
    }

    return $merged;
}

    /* ============================================================
     | BUILD ENV DATA FROM MERGED VALUES
     * ============================================================ */
    protected function getProviderEnvDataFromMerged(string $provider, array $merged, bool $enabled): array
    {
        $flag = $enabled ? 'true' : 'false';

        switch ($provider) {
            case 'arkesel':
                return [
                    'ARKESEL_SMS_API_KEY'   => $merged['api_key']   ?? '',
                    'ARKESEL_SMS_SENDER_ID' => $merged['sender_id'] ?? '',
                    'ARKESEL_SMS_BASE_URL'  => $merged['base_url']  ?: 'https://sms.arkesel.com',
                    'ARKESEL_SMS_ENABLED'   => $flag,
                ];

            case 'twilio':
                return [
                    'TWILIO_ACCOUNT_SID' => $merged['account_sid'] ?? '',
                    'TWILIO_AUTH_TOKEN'  => $merged['auth_token']  ?? '',
                    'TWILIO_FROM_NUMBER' => $merged['from_number'] ?? '',
                    'TWILIO_SMS_ENABLED' => $flag,
                ];

            case 'africastalking':
                return [
                    'AFRICASTALKING_API_KEY'     => $merged['api_key']   ?? '',
                    'AFRICASTALKING_USERNAME'    => $merged['username']  ?: 'sandbox',
                    'AFRICASTALKING_SENDER_ID'   => $merged['sender_id'] ?? '',
                    'AFRICASTALKING_SMS_ENABLED' => $flag,
                ];

            case 'hubtel':
                return [
                    'HUBTEL_CLIENT_ID'     => $merged['client_id']     ?? '',
                    'HUBTEL_CLIENT_SECRET' => $merged['client_secret'] ?? '',
                    'HUBTEL_SENDER_ID'     => $merged['sender_id']     ?? '',
                    'HUBTEL_SMS_ENABLED'   => $flag,
                ];

            case 'nalosolutions':
                return [
                    'NALOSOLUTIONS_API_KEY'     => $merged['api_key']   ?? '',
                    'NALOSOLUTIONS_SENDER_ID'   => $merged['sender_id'] ?? '',
                    'NALOSOLUTIONS_BASE_URL'    => $merged['base_url']  ?: 'https://sms.nalosolutions.com',
                    'NALOSOLUTIONS_SMS_ENABLED' => $flag,
                ];

            default:
                return [];
        }
    }

    /* ============================================================
     | QUEUE / SCHEDULE
     * ============================================================ */
    protected function queueSmsConfigurationUpdate($provider, $envData, $action = 'configure')
    {
        try {
            if (config('queue.default') !== 'sync') {
                UpdateSmsConfiguration::dispatch($provider, $envData, auth()->id());

                return [
                    'success' => true,
                    'message' => 'SMS configuration update queued successfully',
                    'method'  => 'queue',
                ];
            }

            return [
                'success' => true,
                'message' => 'Sync mode — will write directly',
                'method'  => 'sync',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to schedule SMS configuration update: ' . $e->getMessage(),
            ];
        }
    }

    /* ============================================================
     | PENDING UPDATES (backward compat)
     * ============================================================ */
    public function checkPendingSmsUpdates()
    {
        if (!session()->has('pending_sms_config_update')
            || session('pending_sms_config_update.attempted', false)) {
            return [
                'success' => false,
                'message' => 'No pending SMS configuration updates found',
            ];
        }

        $pendingUpdate = session()->get('pending_sms_config_update');

        try {
            session()->put('pending_sms_config_update.attempted', true);
            session()->save();

            $updateResult = $this->processSmsConfigurationUpdate($pendingUpdate);

            if ($updateResult['success']) {
                session()->forget('pending_sms_config_update');
                session()->save();

                return [
                    'success'  => true,
                    'message'  => 'SMS configuration updated successfully!',
                    'action'   => $pendingUpdate['action'] ?? 'configure',
                    'provider' => $pendingUpdate['provider'],
                ];
            }

            return [
                'success'  => false,
                'message'  => 'SMS configuration update failed: ' . $updateResult['message'],
                'provider' => $pendingUpdate['provider'],
            ];
        } catch (\Throwable $e) {
            session()->put('pending_sms_config_update.attempted', true);
            session()->save();

            return [
                'success'  => false,
                'message'  => 'Update process error: ' . $e->getMessage(),
                'provider' => $pendingUpdate['provider'] ?? 'unknown',
            ];
        }
    }

    protected function processSmsConfigurationUpdate($pendingUpdate)
    {
        try {
            $provider = $pendingUpdate['provider'];
            $envData  = $pendingUpdate['env_data'];

            $this->backupEnvFile();
            $this->updateEnvFile($envData);
            $this->deleteConfigCacheFile();

            $verification  = $this->verifyEnvFile($envData);
            $failedUpdates = array_filter($verification, fn ($v) => !$v['match']);

            if (!empty($failedUpdates)) {
                throw new \RuntimeException('Some configuration values failed to update.');
            }

            return [
                'success' => true,
                'message' => 'SMS configuration updated successfully',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to process SMS configuration update: ' . $e->getMessage());
            $this->restoreEnvBackup();

            return [
                'success' => false,
                'message' => 'Failed to update SMS configuration: ' . $e->getMessage(),
            ];
        }
    }

    public function retrySmsConfigUpdate()
    {
        $this->requireDeveloper();

        try {
            $result = $this->checkPendingSmsUpdates();

            if ($result['success'] ?? false) {
                return redirect()->route('admin.sms-providers.index')
                    ->with('success', $result['message'])
                    ->with('provider', $result['provider'] ?? null);
            }

            return redirect()->route('admin.sms-providers.index')
                ->with('warning', $result['message'] ?? 'No pending updates')
                ->with('provider', $result['provider'] ?? null);
        } catch (\Throwable $e) {
            return redirect()->route('admin.sms-providers.index')
                ->with('error', 'Error retrying: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | TOGGLE PROVIDER — DEVELOPER ONLY
     * ============================================================ */
    public function toggleProvider(Request $request)
    {
        $this->requireDeveloper();

        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
            'enable'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'    => false,
                'message'    => 'Validation failed: ' . $validator->errors()->first(),
                'error_code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $provider = $request->provider;
        $enable   = $request->boolean('enable');

        try {
            if ($enable) {
                $missing = [];
                $cfg = config("sms.providers.{$provider}", []);

                foreach ($this->requiredFields[$provider] ?? [] as $field) {
                    if (empty($cfg[$field])) {
                        $missing[] = $field;
                    }
                }

                if (!empty($missing)) {
                    return response()->json([
                        'success'    => false,
                        'message'    => '❌ Cannot enable — missing: ' . implode(', ', $missing),
                        'error_code' => 'MISSING_CONFIG',
                        'missing'    => $missing,
                    ], 422, [], JSON_UNESCAPED_UNICODE);
                }
            }

            $envKey  = strtoupper($provider) . '_SMS_ENABLED';
            $envData = [$envKey => $enable ? 'true' : 'false'];

            $this->deferEnvWrite($provider, $envData);

            return response()->json([
                'success'  => true,
                'message'  => '✅ ' . $this->getProviderName($provider) . ' ' . ($enable ? 'enabled' : 'disabled') . ' successfully!',
                'enabled'  => $enable,
                'provider' => $provider,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::error("Failed to toggle {$provider} SMS provider: " . $e->getMessage());

            return response()->json([
                'success'    => false,
                'message'    => '❌ Failed to toggle SMS provider: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ============================================================
     | TEST CONNECTION — DEVELOPER ONLY
     * ============================================================ */
    public function testConnection(Request $request)
    {
        $this->requireDeveloper();

        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
            'queue'    => 'sometimes|boolean',
        ]);

        $provider  = $request->provider;
        $queueTest = $request->boolean('queue', false);

        try {
            if ($queueTest && config('queue.default') !== 'sync') {
                TestSmsConnection::dispatch($provider, auth()->id());

                return response()->json([
                    'success'       => true,
                    'message'       => '🔧 Connection test queued for background processing',
                    'provider'      => $provider,
                    'queued'        => true,
                    'timestamp'     => now()->toISOString(),
                    'provider_name' => $this->getProviderName($provider),
                ]);
            }

            $result = $this->smsService->testConnection($provider);

            $response = [
                'success'       => $result['success'] ?? false,
                'message'       => ($result['success'] ?? false)
                    ? '✅ ' . ($result['message'] ?? 'OK')
                    : '❌ ' . ($result['message'] ?? 'Failed'),
                'provider'      => $provider,
                'details'       => $result['details'] ?? [],
                'error_code'    => $result['error_code'] ?? null,
                'timestamp'     => $result['timestamp'] ?? now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued'        => false,
            ];

            return response()->json($response, $result['success'] ? 200 : 400);

        } catch (\Throwable $e) {
            Log::error("SMS connection test error for {$provider}: " . $e->getMessage());

            return response()->json([
                'success'       => false,
                'message'       => '❌ Connection test failed: ' . $e->getMessage(),
                'provider'      => $provider,
                'error_code'    => 'EXCEPTION',
                'timestamp'     => now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued'        => false,
            ], 500);
        }
    }

    /* ============================================================
     | SEND TEST SMS — DEVELOPER ONLY
     * ============================================================ */
    public function sendTestSMS(Request $request)
    {
        $this->requireDeveloper();

        $request->validate([
            'provider'     => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
            'phone_number' => 'required|string|max:20',
            'message'      => 'required|string|max:160',
            'queue'        => 'sometimes|boolean',
        ]);

        $provider    = $request->provider;
        $phoneNumber = $request->phone_number;
        $message     = $request->message;
        $queueSms    = $request->boolean('queue', false);

        try {
            if ($queueSms && config('queue.default') !== 'sync') {
                SendTestSmsMessage::dispatch($provider, $phoneNumber, $message, auth()->id());

                return response()->json([
                    'success'       => true,
                    'message'       => '📱 Test SMS queued for background sending',
                    'provider'      => $provider,
                    'queued'        => true,
                    'timestamp'     => now()->toISOString(),
                    'provider_name' => $this->getProviderName($provider),
                ]);
            }

            $result = $this->smsService->sendTestMessage($provider, $phoneNumber, $message);

            $response = [
                'success'        => $result['success'] ?? false,
                'message'        => ($result['success'] ?? false)
                    ? '✅ ' . ($result['message'] ?? 'Sent')
                    : '❌ ' . ($result['message'] ?? 'Failed'),
                'provider'       => $provider,
                'details'        => $result['details'] ?? $result['test_details'] ?? [],
                'error_code'     => $result['error_code'] ?? null,
                'timestamp'      => $result['timestamp'] ?? now()->toISOString(),
                'provider_name'  => $this->getProviderName($provider),
                'execution_time' => $result['execution_time_ms'] ?? null,
                'queued'         => false,
            ];

            return response()->json($response, $result['success'] ? 200 : 400);

        } catch (\Throwable $e) {
            Log::error("Test SMS error for {$provider}: " . $e->getMessage());

            return response()->json([
                'success'       => false,
                'message'       => '❌ Test SMS failed: ' . $e->getMessage(),
                'provider'      => $provider,
                'error_code'    => 'EXCEPTION',
                'timestamp'     => now()->toISOString(),
                'provider_name' => $this->getProviderName($provider),
                'queued'        => false,
            ], 500);
        }
    }

    /* ============================================================
     | BULK OPERATIONS — DEVELOPER ONLY
     * ============================================================ */
    public function bulkOperations(Request $request)
    {
        $this->requireDeveloper();

        $validator = Validator::make($request->all(), [
            'operation'   => 'required|in:enable_all,disable_all,test_all,cleanup',
            'providers'   => 'sometimes|array',
            'providers.*' => 'in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
            ], 422);
        }

        try {
            $operation = $request->operation;
            $providers = $request->providers ?? ['arkesel', 'twilio', 'africastalking', 'hubtel', 'nalosolutions'];

            $result = match ($operation) {
                'enable_all'  => $this->bulkEnableProviders($providers),
                'disable_all' => $this->bulkDisableProviders($providers),
                'test_all'    => $this->bulkTestProviders($providers),
                'cleanup'     => $this->bulkCleanup($providers),
                default       => ['success' => false, 'message' => 'Unknown operation'],
            };

            return response()->json($result);

        } catch (\Throwable $e) {
            Log::error("Bulk SMS operation failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function bulkEnableProviders($providers)
    {
        $envData = [];
        foreach ($providers as $provider) {
            $envData[strtoupper($provider) . '_SMS_ENABLED'] = 'true';
        }

        $this->deferEnvWrite('bulk_operation', $envData);

        return [
            'success'   => true,
            'message'   => 'Providers enabled successfully',
            'providers' => $providers,
            'queued'    => false,
        ];
    }

    protected function bulkDisableProviders($providers)
    {
        $envData = [];
        foreach ($providers as $provider) {
            $envData[strtoupper($provider) . '_SMS_ENABLED'] = 'false';
        }

        $this->deferEnvWrite('bulk_operation', $envData);

        return [
            'success'   => true,
            'message'   => 'Providers disabled successfully',
            'providers' => $providers,
            'queued'    => false,
        ];
    }

    protected function bulkTestProviders($providers)
    {
        $results = [];
        foreach ($providers as $provider) {
            if (config('queue.default') !== 'sync') {
                TestSmsConnection::dispatch($provider, auth()->id());
                $results[$provider] = 'queued';
            } else {
                $testResult = $this->smsService->testConnection($provider);
                $results[$provider] = ($testResult['success'] ?? false) ? 'success' : 'failed';
            }
        }

        return [
            'success' => true,
            'message' => 'Bulk test operation completed',
            'results' => $results,
            'queued'  => config('queue.default') !== 'sync',
        ];
    }

    protected function bulkCleanup($providers)
    {
        $logResult    = $this->smsService->cleanupOldLogs(30);
        $backupResult = $this->cleanupBackupsInternal();

        return [
            'success'        => true,
            'message'        => 'Bulk cleanup completed',
            'log_cleanup'    => $logResult,
            'backup_cleanup' => $backupResult,
        ];
    }

    /* ============================================================
     | QUEUE STATUS — READ-ONLY (super-admin + developer)
     * ============================================================ */
    public function getQueueStatus()
    {
        try {
            return response()->json([
                'success'      => true,
                'queue_status' => [
                    'driver'          => config('queue.default'),
                    'can_queue'       => config('queue.default') !== 'sync',
                    'pending_updates' => session()->has('pending_sms_config_update'),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get queue status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | ENV FILE HELPERS
     * ============================================================ */
    protected function verifyEnvFile(array $envData): array
    {
        $envPath    = base_path('.env');
        $envContent = File::exists($envPath) ? File::get($envPath) : '';
        $results    = [];

        foreach ($envData as $key => $expected) {
            if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $envContent, $m)) {
                $actual = trim($m[1], "\"'");
            } else {
                $actual = null;
            }

            $results[$key] = [
                'expected' => (string) $expected,
                'actual'   => (string) $actual,
                'match'    => (string) $actual === (string) $expected,
            ];
        }

        return $results;
    }

    protected function backupEnvFile()
    {
        try {
            $envPath    = base_path('.env');
            $backupPath = base_path('.env.backup.sms.' . date('Y-m-d-H-i-s'));

            if (File::exists($envPath)) {
                File::copy($envPath, $backupPath);
                return $backupPath;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
            return false;
        }
    }

    protected function restoreEnvBackup()
    {
        try {
            $backups = File::glob(base_path('.env.backup.sms.*'));
            if (!empty($backups)) {
                $latest = max($backups);
                File::copy($latest, base_path('.env'));
                Log::info('Restored .env from backup: ' . $latest);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            throw new \RuntimeException('.env file not found at: ' . $envPath);
        }

        if (!File::isWritable($envPath)) {
            throw new \RuntimeException('.env file is not writable.');
        }

        $envContent = File::get($envPath);
        $updated    = false;

        foreach ($data as $key => $value) {
            $escaped     = $this->escapeEnvValue($value);
            $pattern     = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = "{$key}={$escaped}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$key}={$escaped}";
            }

            $updated = true;
        }

        if ($updated) {
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";

            File::put($envPath, $envContent);

            Log::info('Updated .env file with SMS configuration', $data);
        }

        return $updated;
    }

    protected function escapeEnvValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value === null || $value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\'\\\\`$!*?\[\]{}|&;()<>]/', $value)) {
            $value = str_replace(['"', '\\'], ['\"', '\\\\'], $value);
            return '"' . $value . '"';
        }

        return (string) $value;
    }

    protected function getProviderName($key)
    {
        return [
            'arkesel'        => 'Arkesel SMS',
            'twilio'         => 'Twilio',
            'africastalking' => "Africa's Talking",
            'hubtel'         => 'Hubtel SMS',
            'nalosolutions'  => 'Nalo Solutions',
        ][$key] ?? $key;
    }

    /* ============================================================
     | READ-ONLY ENDPOINTS — open to super-admin + developer
     * ============================================================ */
    public function getProviderStatus()
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'providers' => $this->smsService->checkSmsProviderConfiguration(),
                    'system'    => $this->smsService->getSystemStatus(),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to get SMS provider status: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    public function getUsageStatistics()
    {
        try {
            return response()->json([
                'success'   => true,
                'data'      => $this->smsService->getUsageStatistics(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to get SMS usage statistics: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    public function getProviderConfig(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        try {
            $config = $this->smsService->getProviderConfig($request->provider);

            if (!$config) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Provider configuration not found',
                    'error_code' => 'PROVIDER_NOT_FOUND',
                    'timestamp'  => now()->toISOString(),
                ], 404);
            }

            return response()->json([
                'success'   => true,
                'data'      => $config,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to get provider configuration: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    public function verifyEnvironment(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        $provider = $request->provider;

        try {
            $isProduction = false;
            $display      = 'N/A';
            $cfg          = config("sms.providers.{$provider}", []);

            switch ($provider) {
                case 'arkesel':
                    $baseUrl      = $cfg['base_url'] ?? 'https://sms.arkesel.com';
                    $display      = $baseUrl;
                    $isProduction = str_contains($baseUrl, 'sms.arkesel.com') && !str_contains($baseUrl, 'sandbox');
                    break;
                case 'twilio':
                    $sid          = $cfg['account_sid'] ?? '';
                    $display      = $sid ? 'AC…' . substr($sid, -6) : 'N/A';
                    $isProduction = !empty($sid) && str_starts_with($sid, 'AC');
                    break;
                case 'africastalking':
                    $username     = strtolower((string) ($cfg['username'] ?? 'sandbox'));
                    $display      = $username;
                    $isProduction = $username !== 'sandbox';
                    break;
                case 'hubtel':
                    $clientId     = $cfg['client_id'] ?? '';
                    $display      = $clientId ? 'Client…' . substr($clientId, -6) : 'N/A';
                    $isProduction = !empty($clientId) && strlen($clientId) > 10;
                    break;
                case 'nalosolutions':
                    $baseUrl      = $cfg['base_url'] ?? 'https://sms.nalosolutions.com';
                    $display      = $baseUrl;
                    $isProduction = str_contains($baseUrl, 'sms.nalosolutions.com');
                    break;
            }

            return response()->json([
                'success'             => true,
                'current_environment' => $display,
                'is_production'       => $isProduction,
                'provider'            => $provider,
                'provider_name'       => $this->getProviderName($provider),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to verify SMS environment for {$provider}: " . $e->getMessage());

            return response()->json([
                'success'  => false,
                'message'  => '❌ Failed to verify environment: ' . $e->getMessage(),
                'provider' => $provider,
            ], 500);
        }
    }

    /* ============================================================
     | DEBUG ENDPOINTS — DEVELOPER ONLY
     * ============================================================ */
    public function debugEnvUpdate(Request $request)
    {
        $this->requireDeveloper();

        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        $provider = $request->provider;
        $merged   = $this->mergeWithExistingConfig($provider, $request);
        $envData  = $this->getProviderEnvDataFromMerged($provider, $merged, $request->boolean('enabled'));

        return response()->json([
            'success' => true,
            'debug_info' => [
                'provider'    => $provider,
                'incoming'    => $request->except(['api_key', 'auth_token', 'client_secret']),
                'merged'      => array_map(
                    fn ($v, $k) => str_contains($k, 'key') || str_contains($k, 'token') || str_contains($k, 'secret')
                        ? ($v ? '***' . substr($v, -4) : '(empty)')
                        : $v,
                    $merged,
                    array_keys($merged)
                ),
                'env_data'    => array_map(
                    fn ($v, $k) => str_contains($k, 'KEY') || str_contains($k, 'TOKEN') || str_contains($k, 'SECRET')
                        ? ($v ? '***' . substr($v, -4) : '(empty)')
                        : $v,
                    $envData,
                    array_keys($envData)
                ),
                'current_env' => $this->safeCurrentEnvSnapshot($provider),
            ],
        ]);
    }

    protected function safeCurrentEnvSnapshot(string $provider): array
    {
        $config = config("sms.providers.{$provider}", []);
        $mask   = fn ($val) => $val ? '***' . substr((string) $val, -4) : null;

        return [
            'enabled'     => $config['enabled'] ?? null,
            'sender_id'   => $config['sender_id'] ?? null,
            'api_key'     => $mask($config['api_key'] ?? null),
            'username'    => $config['username'] ?? null,
            'from_number' => $config['from_number'] ?? null,
            'base_url'    => $config['base_url'] ?? null,
        ];
    }

    public function debugArkeselApi(Request $request)
    {
        $this->requireDeveloper();

        $request->validate(['provider' => 'required|in:arkesel']);

        try {
            $debugResults = $this->smsService->debugArkeselApi();

            return response()->json([
                'success'       => true,
                'message'       => 'Arkesel API debug completed',
                'provider'      => 'arkesel',
                'debug_results' => $debugResults,
                'timestamp'     => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Arkesel API debug failed: " . $e->getMessage());

            return response()->json([
                'success'    => false,
                'message'    => '❌ API debug failed: ' . $e->getMessage(),
                'provider'   => 'arkesel',
                'error_code' => 'DEBUG_FAILED',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    /* ============================================================
     | BACKUP CLEANUP — DEVELOPER ONLY
     * ============================================================ */
    public function cleanupBackups()
    {
        $this->requireDeveloper();

        try {
            $result = $this->cleanupBackupsInternal();

            return response()->json([
                'success'       => true,
                'message'       => $result['message'],
                'deleted_count' => $result['deleted_count'],
                'timestamp'     => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to clean up SMS backups: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    protected function cleanupBackupsInternal(): array
    {
        $backups  = File::glob(base_path('.env.backup.sms.*'));
        $keepLast = 5;

        if (count($backups) <= $keepLast) {
            return ['message' => 'No old SMS backups to clean up', 'deleted_count' => 0];
        }

        sort($backups);
        $toDelete = array_slice($backups, 0, count($backups) - $keepLast);

        foreach ($toDelete as $file) {
            File::delete($file);
        }

        return [
            'message'       => 'Cleaned up ' . count($toDelete) . ' old SMS backups',
            'deleted_count' => count($toDelete),
        ];
    }

    /* ============================================================
     | RESET PROVIDER — DEVELOPER ONLY
     * ============================================================ */
    public function resetProvider(Request $request)
    {
        $this->requireDeveloper();

        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        $provider = $request->provider;

        try {
            $envData = match ($provider) {
                'arkesel' => [
                    'ARKESEL_SMS_API_KEY'   => '',
                    'ARKESEL_SMS_SENDER_ID' => '',
                    'ARKESEL_SMS_BASE_URL'  => '',
                    'ARKESEL_SMS_ENABLED'   => 'false',
                ],
                'twilio' => [
                    'TWILIO_ACCOUNT_SID' => '',
                    'TWILIO_AUTH_TOKEN'  => '',
                    'TWILIO_FROM_NUMBER' => '',
                    'TWILIO_SMS_ENABLED' => 'false',
                ],
                'africastalking' => [
                    'AFRICASTALKING_API_KEY'     => '',
                    'AFRICASTALKING_USERNAME'    => '',
                    'AFRICASTALKING_SENDER_ID'   => '',
                    'AFRICASTALKING_SMS_ENABLED' => 'false',
                ],
                'hubtel' => [
                    'HUBTEL_CLIENT_ID'     => '',
                    'HUBTEL_CLIENT_SECRET' => '',
                    'HUBTEL_SENDER_ID'     => '',
                    'HUBTEL_SMS_ENABLED'   => 'false',
                ],
                'nalosolutions' => [
                    'NALOSOLUTIONS_API_KEY'     => '',
                    'NALOSOLUTIONS_SENDER_ID'   => '',
                    'NALOSOLUTIONS_BASE_URL'    => '',
                    'NALOSOLUTIONS_SMS_ENABLED' => 'false',
                ],
                default => [],
            };

            $this->deferEnvWrite($provider, $envData);

            return response()->json([
                'success'   => true,
                'message'   => '✅ ' . $this->getProviderName($provider) . ' SMS configuration reset successfully!',
                'provider'  => $provider,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Throwable $e) {
            Log::error("Failed to reset {$provider} SMS configuration: " . $e->getMessage());

            return response()->json([
                'success'    => false,
                'message'    => '❌ Failed to reset SMS configuration: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    /* ============================================================
     | SET DEFAULT PROVIDER — DEVELOPER ONLY
     * ============================================================ */
    public function setDefaultProvider(Request $request)
    {
        $this->requireDeveloper();

        $request->validate([
            'provider' => 'required|in:arkesel,twilio,africastalking,hubtel,nalosolutions',
        ]);

        try {
            $provider = $request->provider;

            if (!$this->smsService->isProviderEnabled($provider)
                || !$this->smsService->isProviderConfigured($provider)) {
                return response()->json([
                    'success'       => false,
                    'message'       => '❌ Cannot set disabled or unconfigured provider as default',
                    'error_code'    => 'PROVIDER_NOT_READY',
                    'timestamp'     => now()->toISOString(),
                    'provider_name' => $this->getProviderName($provider),
                ], 400);
            }

            $this->deferEnvWrite($provider, ['DEFAULT_SMS_PROVIDER' => $provider]);

            return response()->json([
                'success'       => true,
                'message'       => '✅ Default SMS provider set to ' . $this->getProviderName($provider),
                'provider'      => $provider,
                'system_status' => $this->smsService->getSystemStatus(),
                'timestamp'     => now()->toISOString(),
            ]);

        } catch (\Throwable $e) {
            Log::error("Failed to set default SMS provider: " . $e->getMessage());

            return response()->json([
                'success'    => false,
                'message'    => '❌ Failed to set default SMS provider: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    /* ============================================================
     | CLEANUP OLD LOGS — DEVELOPER ONLY
     * ============================================================ */
    public function cleanupOldLogs(Request $request)
    {
        $this->requireDeveloper();

        $request->validate(['days' => 'sometimes|integer|min:1|max:365']);

        try {
            $days   = $request->days ?? 30;
            $result = $this->smsService->cleanupOldLogs($days);

            if ($result['success']) {
                return response()->json([
                    'success'       => true,
                    'message'       => '✅ ' . $result['message'],
                    'deleted_count' => $result['deleted_count'],
                    'timestamp'     => now()->toISOString(),
                ]);
            }

            return response()->json([
                'success'    => false,
                'message'    => '❌ ' . $result['message'],
                'error_code' => $result['error_code'] ?? 'CLEANUP_FAILED',
                'timestamp'  => now()->toISOString(),
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'message'    => '❌ Failed to cleanup SMS logs: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    /* ============================================================
     | RESPONSE HELPERS
     * ============================================================ */
    protected function respondError(bool $isAjax, string $message, int $status = 400, array $extra = [])
    {
        if ($isAjax) {
            return response()->json(array_merge([
                'success' => false,
                'message' => $message,
            ], $extra), $status);
        }

        return redirect()->back()->with('error', $message)->withInput();
    }
}