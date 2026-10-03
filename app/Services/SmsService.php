<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Models\SmsLog;
use Exception;

class SmsService
{
    /**
     * Provider definitions — what each provider needs and where it lives.
     * Actual values come from config/sms.php.
     */
    protected array $providers = [
        'arkesel' => [
            'name'             => 'Arkesel SMS',
            'required_fields'  => ['api_key', 'sender_id'],
            'optional_fields'  => ['base_url'],
            'base_url'         => 'https://sms.arkesel.com',
            'api_version'      => 'v2',
            'send_endpoint'    => '/api/v2/sms/send',
            'balance_endpoint' => '/api/v2/balance',
        ],
        'twilio' => [
            'name'            => 'Twilio',
            'required_fields' => ['account_sid', 'auth_token', 'from_number'],
            'optional_fields' => [],
            'base_url'        => 'https://api.twilio.com',
        ],
        'africastalking' => [
            'name'            => 'Africa\'s Talking',
            'required_fields' => ['api_key', 'username'],
            'optional_fields' => ['sender_id'],
            'base_url'        => 'https://api.africastalking.com',
        ],
        'hubtel' => [
            'name'            => 'Hubtel SMS',
            'required_fields' => ['client_id', 'client_secret'],
            'optional_fields' => ['sender_id'],
            'base_url'        => 'https://api.hubtel.com',
        ],
        'nalosolutions' => [
            'name'            => 'Nalo Solutions',
            'required_fields' => ['api_key'],
            'optional_fields' => ['sender_id', 'base_url'],
            'base_url'        => 'https://sms.nalosolutions.com',
        ],
    ];

    /**
     * Cached global default sender ID (from system_settings.sms_sender_id).
     * Resolved once per request; invalidated via forgetGlobalSenderIdCache().
     */
    protected ?string $cachedGlobalSenderId = null;
    protected bool $globalSenderIdResolved = false;

    // =========================================================================
    // CONFIG ACCESSORS
    // =========================================================================

    protected function providerConfig(string $provider): array
    {
        return (array) config("sms.providers.{$provider}", []);
    }

    protected function isDryRun(): bool
    {
        return (bool) config('sms.dry_run', false);
    }

    protected function isTestMode(): bool
    {
        return (bool) config('sms.test_mode', false);
    }

    protected function defaultProviderKey(): string
    {
        return (string) config('sms.default', 'arkesel');
    }

    protected function maxMessageLength(): int
    {
        return (int) config('sms.max_length', 160);
    }

    protected function httpTimeout(): int
    {
        return (int) config('sms.timeout_seconds', 30);
    }

    // =========================================================================
    // SENDER ID RESOLUTION — the admin/developer bridge
    // =========================================================================

    /**
     * Resolve the sender ID for a provider using a three-level fallback:
     *
     *   1. Per-provider value  — set by a developer on the SMS Providers page.
     *   2. Global default      — set by an admin on System Settings.
     *   3. Final fallback      — config('sms.sender_id') or app short name.
     *
     * Per-provider wins because developers know each gateway's rules best.
     * The global default is the admin's convenience knob.
     */
    public function resolveSenderId(string $provider): string
    {
        // 1. Per-provider (developer-set)
        $providerConfig = $this->providerConfig($provider);
        $providerSenderId = $providerConfig['sender_id'] ?? null;

        if (is_string($providerSenderId) && trim($providerSenderId) !== '') {
            return trim($providerSenderId);
        }

        // 2. Global default (admin-set)
        $globalSenderId = $this->getGlobalSenderId();
        if ($globalSenderId !== null && $globalSenderId !== '') {
            return $globalSenderId;
        }

        // 3. Final fallback
        $fallback = config('sms.sender_id');

        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        return 'SYSTEM';
    }

    /**
     * Return the cached global sender ID from system_settings.
     * Null when unset. Cached for the current request only — the admin
     * update endpoint explicitly flushes the persistent cache.
     */
    protected function getGlobalSenderId(): ?string
    {
        if ($this->globalSenderIdResolved) {
            return $this->cachedGlobalSenderId;
        }

        $this->globalSenderIdResolved = true;

        try {
            $this->cachedGlobalSenderId = Cache::remember(
                'sms_default_sender_id',
                now()->addMinutes(10),
                function () {
                    try {
                        if (!class_exists(\App\Models\SystemSetting::class)) {
                            return null;
                        }

                        $settings = \App\Models\SystemSetting::getSettings();
                        $value = $settings?->sms_sender_id;

                        return is_string($value) && trim($value) !== ''
                            ? trim($value)
                            : null;
                    } catch (\Throwable $e) {
                        Log::debug('Failed to read global SMS sender ID: ' . $e->getMessage());
                        return null;
                    }
                }
            );
        } catch (\Throwable $e) {
            Log::debug('Failed to resolve global SMS sender ID from cache: ' . $e->getMessage());
            $this->cachedGlobalSenderId = null;
        }

        return $this->cachedGlobalSenderId;
    }

    /**
     * Flush the cached global sender ID. Called by the admin update path
     * so the very next SMS picks up the new value.
     */
    public function forgetGlobalSenderIdCache(): bool
    {
        try {
            Cache::forget('sms_default_sender_id');
            $this->cachedGlobalSenderId = null;
            $this->globalSenderIdResolved = false;
            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to forget global SMS sender ID cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Diagnostic — what sender ID would we use for this provider right now?
     * Useful for the SMS Providers page preview and for debugging.
     */
    public function explainSenderId(string $provider): array
    {
        $providerConfig = $this->providerConfig($provider);
        $providerSenderId = $providerConfig['sender_id'] ?? null;
        $globalSenderId = $this->getGlobalSenderId();

        $source = 'fallback';
        if (is_string($providerSenderId) && trim($providerSenderId) !== '') {
            $source = 'provider';
        } elseif (is_string($globalSenderId) && trim($globalSenderId) !== '') {
            $source = 'global';
        }

        return [
            'provider'             => $provider,
            'resolved'             => $this->resolveSenderId($provider),
            'source'               => $source,
            'provider_sender_id'   => $providerSenderId,
            'global_sender_id'     => $globalSenderId,
            'final_fallback'       => config('sms.sender_id') ?? 'SYSTEM',
        ];
    }

    // =========================================================================
    // PROVIDER READINESS
    // =========================================================================

    public function isProviderEnabled(string $provider): bool
    {
        if (!isset($this->providers[$provider])) {
            return false;
        }

        return (bool) config("sms.providers.{$provider}.enabled", false);
    }

    /**
     * A provider is "configured" when every required field is present.
     *
     * IMPORTANT: for providers where sender_id is required, we now accept
     * the global default as a valid substitute for the per-provider value.
     * This is what makes the admin's global setting actually useful.
     */
    public function isProviderConfigured(string $provider): bool
    {
        if (!isset($this->providers[$provider])) {
            return false;
        }

        $values = $this->providerConfig($provider);

        foreach ($this->providers[$provider]['required_fields'] as $field) {
            $value = $values[$field] ?? null;

            // For sender_id, fall back to the global default before
            // declaring the provider misconfigured.
            if ($field === 'sender_id' && (empty($value) && $value !== '0')) {
                $globalSenderId = $this->getGlobalSenderId();
                if (is_string($globalSenderId) && trim($globalSenderId) !== '') {
                    continue;
                }
            }

            if (empty($value) && $value !== '0') {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns list of required fields that are missing from the given
     * provider's config (or from the array passed in $valuesOverride).
     *
     * Same "sender_id falls back to global" behaviour as isProviderConfigured().
     */
    protected function getMissingConfiguration(string $provider, ?array $valuesOverride = null): array
    {
        $providerConfig = $this->providers[$provider] ?? null;

        if (!$providerConfig) {
            return ['provider_not_found'];
        }

        $values  = $valuesOverride ?? $this->providerConfig($provider);
        $missing = [];

        foreach ($providerConfig['required_fields'] as $field) {
            $value = $values[$field] ?? null;

            if ($field === 'sender_id' && (empty($value) && $value !== '0')) {
                $globalSenderId = $this->getGlobalSenderId();
                if (is_string($globalSenderId) && trim($globalSenderId) !== '') {
                    continue;
                }
            }

            if (empty($value) && $value !== '0') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public function getProviderReadiness($provider = null): array
    {
        $targetProvider = $provider ?: $this->getDefaultProvider();

        if (!$targetProvider || !isset($this->providers[$targetProvider])) {
            return [
                'ready'                => false,
                'message'              => 'No SMS provider available',
                'can_send_invitations' => false,
            ];
        }

        $enabled    = $this->isProviderEnabled($targetProvider);
        $configured = $this->isProviderConfigured($targetProvider);
        $ready      = $enabled && $configured;
        $name       = $this->providers[$targetProvider]['name'] ?? 'Unknown';

        return [
            'ready'                 => $ready,
            'enabled'               => $enabled,
            'configured'            => $configured,
            'provider'              => $targetProvider,
            'provider_name'         => $name,
            'can_send_invitations'  => $ready,
            'message'               => $ready
                ? "{$name} is ready"
                : (!$enabled
                    ? "{$name} is disabled"
                    : "{$name} is not configured"),
            'missing_configuration' => $ready ? [] : $this->getMissingConfiguration($targetProvider),
            'sender_id_source'      => $this->explainSenderId($targetProvider)['source'],
            'sender_id_resolved'    => $this->resolveSenderId($targetProvider),
        ];
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    public function getSystemStatus(): array
    {
        try {
            $defaultProvider    = $this->getDefaultProvider();
            $availableProviders = $this->getAvailableProviders();

            $enabledCount    = 0;
            $configuredCount = 0;
            $readyCount      = 0;

            foreach ($this->providers as $providerKey => $_) {
                $enabled    = $this->isProviderEnabled($providerKey);
                $configured = $this->isProviderConfigured($providerKey);

                if ($enabled)    $enabledCount++;
                if ($configured) $configuredCount++;
                if ($enabled && $configured) $readyCount++;
            }

            $systemReady  = !empty($availableProviders);
            $healthStatus = $readyCount > 0
                ? 'healthy'
                : ($enabledCount > 0 ? 'degraded' : 'offline');

            return [
                'system_ready'         => $systemReady,
                'health_status'        => $healthStatus,
                'default_provider'     => $defaultProvider,
                'available_providers'  => array_keys($availableProviders),
                'total_providers'      => count($this->providers),
                'enabled_providers'    => $enabledCount,
                'configured_providers' => $configuredCount,
                'ready_providers'      => $readyCount,
                'can_send_sms'         => $systemReady,
                'status_message'       => $systemReady
                    ? 'SMS service is operational'
                    : 'No SMS providers configured',
                'global_sender_id'     => $this->getGlobalSenderId(),
                'timestamp'            => now()->toISOString(),
                'details'              => [
                    'has_default_provider'     => !empty($defaultProvider),
                    'providers_available'      => count($availableProviders),
                    'configuration_checked_at' => now()->toISOString(),
                    'config_cached'            => app()->configurationIsCached(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error getting SMS system status: ' . $e->getMessage());

            return [
                'system_ready'         => false,
                'health_status'        => 'error',
                'default_provider'     => null,
                'available_providers'  => [],
                'total_providers'      => count($this->providers),
                'enabled_providers'    => 0,
                'configured_providers' => 0,
                'ready_providers'      => 0,
                'can_send_sms'         => false,
                'status_message'       => 'Error checking SMS service status',
                'timestamp'            => now()->toISOString(),
                'error'                => $e->getMessage(),
            ];
        }
    }

    public function getQuickStatus(): array
    {
        $ttl = (int) config('sms.cache_ttl', 300);

        return Cache::remember('sms_service_quick_status', $ttl, function () {
            $systemStatus = $this->getSystemStatus();

            return [
                'system_ready'   => $systemStatus['system_ready'] ?? false,
                'health_status'  => $systemStatus['health_status'] ?? 'unknown',
                'can_send_sms'   => $systemStatus['can_send_sms'] ?? false,
                'status_message' => $systemStatus['status_message'] ?? 'Status unknown',
                'has_providers'  => !empty($systemStatus['available_providers']),
                'timestamp'      => $systemStatus['timestamp'] ?? now()->toISOString(),
            ];
        });
    }

    public function checkSmsProviderConfiguration(): array
    {
        $status = [];

        foreach ($this->providers as $providerKey => $providerConfig) {
            $enabled    = $this->isProviderEnabled($providerKey);
            $configured = $this->isProviderConfigured($providerKey);

            $status[$providerKey] = [
                'enabled'               => $enabled,
                'configured'            => $configured,
                'missing_configuration' => $this->getMissingConfiguration($providerKey),
                'name'                  => $providerConfig['name'],
                'can_send'              => $enabled && $configured,
                'details'               => $this->getProviderConfig($providerKey),
                'readiness'             => $this->getProviderReadiness($providerKey),
                'sender_id'             => $this->resolveSenderId($providerKey),
                'sender_id_source'      => $this->explainSenderId($providerKey)['source'],
            ];
        }

        return $status;
    }

    public function checkConfiguration(): array
    {
        try {
            $systemStatus       = $this->getSystemStatus();
            $defaultProvider    = $this->getDefaultProvider();
            $availableProviders = $this->getAvailableProviders();

            $providerDetails = [];
            foreach ($this->providers as $providerKey => $_) {
                $providerDetails[$providerKey] = $this->getProviderConfig($providerKey);
            }

            return [
                'configured'          => $systemStatus['system_ready'],
                'default_provider'    => $defaultProvider,
                'available_providers' => array_keys($availableProviders),
                'system_status'       => $systemStatus,
                'provider_details'    => $providerDetails,
                'can_send_sms'        => $systemStatus['can_send_sms'],
                'global_sender_id'    => $this->getGlobalSenderId(),
                'details'             => [
                    'total_providers' => count($this->providers),
                    'ready_providers' => count($availableProviders),
                    'health_status'   => $systemStatus['health_status'],
                    'timestamp'       => now()->toISOString(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error checking SMS configuration: ' . $e->getMessage());

            return [
                'configured'          => false,
                'default_provider'    => null,
                'available_providers' => [],
                'system_status'       => $this->getSystemStatus(),
                'provider_details'    => [],
                'can_send_sms'        => false,
                'error'               => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        try {
            $systemStatus = $this->getSystemStatus();
            return $systemStatus['system_ready'] && $systemStatus['can_send_sms'];
        } catch (Exception $e) {
            Log::error('Error checking SMS configuration: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // PROVIDER RESOLUTION
    // =========================================================================

    public function getDefaultProvider(): ?string
    {
        $default = $this->defaultProviderKey();

        if (isset($this->providers[$default])
            && $this->isProviderEnabled($default)
            && $this->isProviderConfigured($default)
        ) {
            return $default;
        }

        foreach (array_keys($this->providers) as $key) {
            if ($this->isProviderEnabled($key) && $this->isProviderConfigured($key)) {
                return $key;
            }
        }

        return null;
    }

    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->providers as $providerKey => $providerConfig) {
            if ($this->isProviderEnabled($providerKey) && $this->isProviderConfigured($providerKey)) {
                $available[$providerKey] = [
                    'name'      => $providerConfig['name'],
                    'ready'     => true,
                    'readiness' => $this->getProviderReadiness($providerKey),
                ];
            }
        }

        return $available;
    }

    public function getProviderConfig(string $provider): ?array
    {
        $config = $this->providers[$provider] ?? null;

        if (!$config) {
            return null;
        }

        $config['enabled']               = $this->isProviderEnabled($provider);
        $config['configured']            = $this->isProviderConfigured($provider);
        $config['missing_configuration'] = $this->getMissingConfiguration($provider);
        $config['can_send']              = $config['enabled'] && $config['configured'];
        $config['readiness']             = $this->getProviderReadiness($provider);
        $config['effective_sender_id']   = $this->resolveSenderId($provider);
        $config['sender_id_source']      = $this->explainSenderId($provider)['source'];

        $values = $this->providerConfig($provider);

        foreach (array_merge($config['required_fields'], $config['optional_fields']) as $field) {
            $value = $values[$field] ?? null;
            $config['values'][$field]     = $value ? $this->maskSensitiveValue($field, $value) : null;
            $config['raw_values'][$field] = $value;
        }

        return $config;
    }

    public function getAllProvidersWithStatus(): array
    {
        $providers = [];

        foreach ($this->providers as $key => $config) {
            $providers[$key] = array_merge($config, [
                'enabled'               => $this->isProviderEnabled($key),
                'configured'            => $this->isProviderConfigured($key),
                'missing_configuration' => $this->getMissingConfiguration($key),
                'can_send'              => $this->isProviderEnabled($key) && $this->isProviderConfigured($key),
                'sender_id'             => $this->resolveSenderId($key),
                'sender_id_source'      => $this->explainSenderId($key)['source'],
            ]);
        }

        return $providers;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function validateProviderConfiguration(string $provider, array $config): array
    {
        if (!isset($this->providers[$provider])) {
            return [
                'success'    => false,
                'message'    => 'Provider not found',
                'error_code' => 'PROVIDER_NOT_FOUND',
            ];
        }

        $providerDef   = $this->providers[$provider];
        $missingFields = [];

        foreach ($providerDef['required_fields'] as $field) {
            $value = $config[$field] ?? null;

            // sender_id can be satisfied by the global default
            if ($field === 'sender_id' && (empty($value) && $value !== '0')) {
                $globalSenderId = $this->getGlobalSenderId();
                if (is_string($globalSenderId) && trim($globalSenderId) !== '') {
                    continue;
                }
            }

            if (empty($value) && $value !== '0') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return [
                'success'        => false,
                'message'        => 'Missing required fields: ' . implode(', ', $missingFields),
                'error_code'     => 'MISSING_REQUIRED_FIELDS',
                'missing_fields' => $missingFields,
            ];
        }

        $validationErrors = $this->validateFieldFormats($provider, $config);
        if (!empty($validationErrors)) {
            return [
                'success'           => false,
                'message'           => 'Configuration validation failed: ' . implode(', ', $validationErrors),
                'error_code'        => 'VALIDATION_FAILED',
                'validation_errors' => $validationErrors,
            ];
        }

        return [
            'success'  => true,
            'message'  => 'Configuration validation successful',
            'provider' => $provider,
        ];
    }

    protected function validateFieldFormats(string $provider, array $config): array
    {
        $errors = [];

        switch ($provider) {
            case 'twilio':
                $sid = $config['account_sid'] ?? '';
                if (!empty($sid) && !preg_match('/^AC[a-f0-9]{32}$/i', $sid)) {
                    $errors[] = 'Invalid Twilio Account SID format';
                }
                break;

            case 'africastalking':
                $username = $config['username'] ?? '';
                if (!empty($username) && strlen($username) < 3) {
                    $errors[] = "Africa's Talking username must be at least 3 characters";
                }
                break;

            case 'arkesel':
                $sender = $config['sender_id'] ?? '';
                if (!empty($sender) && !preg_match('/^[A-Za-z0-9 _-]{1,11}$/', $sender)) {
                    $errors[] = 'Arkesel Sender ID must be 1-11 alphanumeric characters';
                }
                break;
        }

        return $errors;
    }

    // =========================================================================
    // TOGGLE
    // =========================================================================

    public function toggleProvider(string $provider, bool $enable): array
    {
        try {
            $envKey  = $this->getEnableEnvKey($provider);
            $envPath = base_path('.env');

            if (!file_exists($envPath)) {
                throw new Exception('.env file not found');
            }

            $content  = file_get_contents($envPath);
            $newValue = $enable ? 'true' : 'false';
            $pattern  = "/^{$envKey}=.*/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$envKey}={$newValue}", $content);
            } else {
                $content .= "\n{$envKey}={$newValue}\n";
            }

            if (file_put_contents($envPath, $content) === false) {
                throw new Exception('Failed to write to .env file');
            }

            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $verify = preg_match("/^{$envKey}=(.+)$/m", $content, $m)
                ? trim($m[1], " \"'")
                : null;

            if ($verify === $newValue) {
                Log::info("{$provider} SMS provider " . ($enable ? 'enabled' : 'disabled') . " successfully");
                Cache::forget('sms_service_quick_status');

                return [
                    'success'  => true,
                    'message'  => 'Provider ' . ($enable ? 'enabled' : 'disabled') . ' successfully',
                    'provider' => $provider,
                    'enabled'  => $enable,
                ];
            }

            throw new Exception('Configuration update verification failed');
        } catch (Exception $e) {
            Log::error("Failed to toggle {$provider} SMS provider: " . $e->getMessage());

            return [
                'success'    => false,
                'message'    => 'Failed to toggle provider: ' . $e->getMessage(),
                'error_code' => 'TOGGLE_FAILED',
                'provider'   => $provider,
            ];
        }
    }

    // =========================================================================
    // SEND SMS
    // =========================================================================

    public function sendSMS($provider, $phoneNumber, $message, $options = []): array
    {
        if (!isset($this->providers[$provider])) {
            $error = "SMS provider {$provider} not found";
            Log::error($error);
            return $this->formatErrorResponse($error, $provider, 'PROVIDER_NOT_FOUND');
        }

        if (!$this->isProviderEnabled($provider)) {
            $error = "SMS provider {$provider} is not enabled";
            Log::warning($error);
            return $this->formatErrorResponse($error, $provider, 'PROVIDER_DISABLED');
        }

        if (!$this->isProviderConfigured($provider)) {
            $missing = $this->getMissingConfiguration($provider);
            $error   = "SMS provider {$provider} is not properly configured. Missing: " . implode(', ', $missing);
            Log::warning($error);
            return $this->formatErrorResponse($error, $provider, 'CONFIGURATION_INCOMPLETE', [
                'missing_configuration' => $missing,
            ]);
        }

        $normalized = $this->normalizePhoneNumber($phoneNumber);
        if (!$this->validatePhoneNumber($normalized)) {
            $error = "Invalid phone number format: {$phoneNumber}";
            Log::warning($error);
            return $this->formatErrorResponse($error, $provider, 'INVALID_PHONE_NUMBER');
        }

        $maxLength = $this->maxMessageLength();
        if (strlen($message) > $maxLength) {
            $error = "SMS message cannot exceed {$maxLength} characters. Current length: " . strlen($message);
            Log::warning($error);
            return $this->formatErrorResponse($error, $provider, 'MESSAGE_TOO_LONG', [
                'current_length' => strlen($message),
                'max_length'     => $maxLength,
            ]);
        }

        if ($this->isDryRun() || ($options['dry_run'] ?? false)) {
            Log::info("SMS dry-run: would send via {$provider}", [
                'to'             => $this->maskPhoneNumber($normalized),
                'message'        => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
                'sender_id'      => $this->resolveSenderId($provider),
                'sender_id_source' => $this->explainSenderId($provider)['source'],
            ]);

            return [
                'success'    => true,
                'message'    => 'SMS dry-run: message was not actually sent',
                'provider'   => $provider,
                'message_id' => 'dry-run-' . uniqid(),
                'is_test'    => true,
                'dry_run'    => true,
                'timestamp'  => now()->toISOString(),
                'details'    => [
                    'sender_id'        => $this->resolveSenderId($provider),
                    'sender_id_source' => $this->explainSenderId($provider)['source'],
                ],
            ];
        }

        try {
            $method = 'sendVia' . ucfirst($provider);

            if (!method_exists($this, $method)) {
                $error = "Unsupported SMS provider method: {$method}";
                Log::error($error);
                return $this->formatErrorResponse($error, $provider, 'UNSUPPORTED_METHOD');
            }

            $startTime = microtime(true);
            $result    = $this->$method($normalized, $message, $options);
            $execTime  = round((microtime(true) - $startTime) * 1000, 2);

            $result['execution_time_ms'] = $execTime;
            $result['is_test']           = $options['is_test'] ?? false;
            $result['timestamp']         = now()->toISOString();

            $this->logSMS($provider, $normalized, $message, $result);

            if ($result['success']) {
                Log::info("SMS sent successfully via {$provider}", [
                    'execution_time' => $execTime,
                    'message_id'     => $result['message_id'] ?? null,
                    'sender_id'      => $this->resolveSenderId($provider),
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $error = "SMS sending failed for {$provider}: " . $e->getMessage();
            Log::error($error, ['provider' => $provider, 'exception' => $e]);

            $this->logSMS($provider, $normalized, $message, [
                'success'    => false,
                'message'    => $e->getMessage(),
                'is_test'    => $options['is_test'] ?? false,
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ]);

            return $this->formatErrorResponse($error, $provider, 'EXCEPTION');
        }
    }

    public function sendWithDefaultProvider($phoneNumber, $message, $options = []): array
    {
        $defaultProvider = $this->getDefaultProvider();

        if (!$defaultProvider) {
            $systemStatus = $this->getSystemStatus();

            return [
                'success'       => false,
                'message'       => 'No enabled and configured SMS provider found',
                'error_code'    => 'NO_PROVIDER_AVAILABLE',
                'system_status' => $systemStatus,
            ];
        }

        return $this->sendSMS($defaultProvider, $phoneNumber, $message, $options);
    }

    public function send(string $phoneNumber, string $message, array $options = []): bool
    {
        $result = $this->sendWithDefaultProvider($phoneNumber, $message, $options);
        return (bool) ($result['success'] ?? false);
    }

    public function sendInvitationSms($phoneNumber, $message, $invitationToken = null): array
    {
        try {
            $systemStatus = $this->getSystemStatus();
            if (!$systemStatus['system_ready']) {
                return [
                    'success'       => false,
                    'message'       => 'SMS service is not configured or ready',
                    'error_code'    => 'SMS_SERVICE_NOT_READY',
                    'system_status' => $systemStatus,
                ];
            }

            $defaultProvider = $this->getDefaultProvider();

            if (!$defaultProvider) {
                return [
                    'success'       => false,
                    'message'       => 'No SMS provider available',
                    'error_code'    => 'NO_PROVIDER_AVAILABLE',
                    'system_status' => $systemStatus,
                ];
            }

            $finalMessage = $message;
            if (!empty($invitationToken)) {
                $invitationUrl = route('agent.invitations.accept', ['token' => $invitationToken]);
                $finalMessage .= "\n\nInvitation Link: {$invitationUrl}";
            }

            $result = $this->sendSMS($defaultProvider, $phoneNumber, $finalMessage);

            if ($result['success']) {
                Log::info('Invitation SMS sent successfully', [
                    'provider'   => $defaultProvider,
                    'message_id' => $result['message_id'] ?? null,
                ]);
            } else {
                Log::error('Invitation SMS failed', [
                    'provider' => $defaultProvider,
                    'error'    => $result['message'] ?? 'Unknown error',
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Error sending invitation SMS: ' . $e->getMessage());

            return [
                'success'    => false,
                'message'    => 'Failed to send invitation SMS: ' . $e->getMessage(),
                'error_code' => 'INVITATION_SMS_FAILED',
            ];
        }
    }

    public function sendInvitation(string $phoneNumber, string $invitationLink, string $tenantName, string $propertyName): bool
    {
        $message  = "Hello {$tenantName},\n\n";
        $message .= "You have been invited to join the property management system for {$propertyName}.\n\n";
        $message .= "Click the link below to complete your registration:\n";
        $message .= $invitationLink . "\n\n";
        $message .= "Thank you,\nHilltop Property Management Team";

        return $this->send($phoneNumber, $message);
    }

    // =========================================================================
    // PROVIDER IMPLEMENTATIONS
    //
    // Every sendVia* method calls resolveSenderId() so the admin's global
    // default is honoured when the developer hasn't pinned a per-provider
    // value.
    // =========================================================================

    protected function sendViaArkesel(string $phoneNumber, string $message, array $options = []): array
    {
        $cfg      = $this->providerConfig('arkesel');
        $apiKey   = $cfg['api_key']   ?? null;
        $baseUrl  = $cfg['base_url']  ?? 'https://sms.arkesel.com';
        $senderId = $this->resolveSenderId('arkesel');

        try {
            $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

            $payload = [
                'sender'     => $senderId,
                'message'    => $message,
                'recipients' => [$cleanNumber],
            ];

            $response = Http::timeout($this->httpTimeout())
                ->withHeaders([
                    'api-key'      => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(rtrim($baseUrl, '/') . '/api/v2/sms/send', $payload);

            $responseData = $response->json();
            $statusCode   = $response->status();

            if ($response->successful()) {
                $messageId = $responseData['id']
                    ?? $responseData['message_id']
                    ?? $responseData['data']['id']
                    ?? null;

                return [
                    'success'     => true,
                    'message'     => 'SMS sent successfully via Arkesel',
                    'provider'    => 'arkesel',
                    'message_id'  => $messageId,
                    'response'    => $responseData,
                    'status_code' => $statusCode,
                    'details'     => [
                        'sender_id'        => $senderId,
                        'sender_id_source' => $this->explainSenderId('arkesel')['source'],
                        'api_status'       => 'sent',
                        'recipient'        => $this->maskPhoneNumber($phoneNumber),
                    ],
                ];
            }

            $errorMessage = $responseData['message']
                ?? $responseData['error']
                ?? $responseData['data']['message']
                ?? 'Unknown error occurred';

            $errorCode = $responseData['code'] ?? $responseData['status'] ?? 'unknown';

            Log::warning('Arkesel SMS send failed', [
                'status_code'   => $statusCode,
                'error_message' => $errorMessage,
                'error_code'    => $errorCode,
            ]);

            return [
                'success'     => false,
                'message'     => "Arkesel SMS failed: {$errorMessage}",
                'provider'    => 'arkesel',
                'response'    => $responseData,
                'status_code' => $statusCode,
                'error_code'  => 'ARKESEL_API_ERROR',
                'details'     => [
                    'api_error_code' => $errorCode,
                    'api_message'    => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Arkesel SMS sending exception: ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => 'Arkesel SMS failed: ' . $e->getMessage(),
                'provider'    => 'arkesel',
                'response'    => ['error' => $e->getMessage()],
                'status_code' => 500,
                'error_code'  => 'NETWORK_ERROR',
            ];
        }
    }

    protected function sendViaTwilio(string $phoneNumber, string $message, array $options = []): array
    {
        $cfg        = $this->providerConfig('twilio');
        $accountSid = $cfg['account_sid'] ?? null;
        $authToken  = $cfg['auth_token']  ?? null;
        $fromNumber = $cfg['from_number'] ?? null;

        // Twilio uses a phone number as the sender. The global default
        // sender ID is a short code, so it can't substitute here — but
        // we still expose it in details for the audit trail.
        $effectiveSenderId = $this->resolveSenderId('twilio');

        try {
            $cleanTo   = preg_replace('/[^+\d]/', '', $phoneNumber);
            $cleanFrom = preg_replace('/[^+\d]/', '', (string) $fromNumber);

            if ($cleanTo === $cleanFrom) {
                return [
                    'success'     => false,
                    'message'     => 'Cannot send SMS to the same number as your Twilio phone number',
                    'provider'    => 'twilio',
                    'response'    => ['error' => 'Same to/from numbers'],
                    'status_code' => 400,
                    'error_code'  => 'SAME_NUMBERS',
                ];
            }

            $response = Http::timeout($this->httpTimeout())
                ->withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'To'   => $phoneNumber,
                    'From' => $fromNumber,
                    'Body' => $message,
                ]);

            $responseData = $response->json();
            $statusCode   = $response->status();

            if ($response->successful() && isset($responseData['sid'])) {
                return [
                    'success'     => true,
                    'message'     => 'SMS sent successfully via Twilio',
                    'provider'    => 'twilio',
                    'message_id'  => $responseData['sid'],
                    'response'    => $responseData,
                    'status_code' => $statusCode,
                    'details'     => [
                        'message_status'   => $responseData['status'] ?? 'sent',
                        'from_number'      => $fromNumber,
                        'sender_id'        => $effectiveSenderId,
                        'sender_id_source' => 'from_number', // Twilio always uses from_number
                        'api_status'       => 'success',
                    ],
                ];
            }

            $errorMessage = $responseData['message'] ?? $responseData['error_message'] ?? 'Unknown error occurred';
            $errorCode    = $responseData['code'] ?? 'unknown';

            return [
                'success'     => false,
                'message'     => "Twilio SMS failed: {$errorMessage}",
                'provider'    => 'twilio',
                'response'    => $responseData,
                'status_code' => $statusCode,
                'error_code'  => 'TWILIO_API_ERROR',
                'details'     => [
                    'api_error_code' => $errorCode,
                    'api_message'    => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'     => false,
                'message'     => 'Twilio SMS failed: ' . $e->getMessage(),
                'provider'    => 'twilio',
                'response'    => ['error' => $e->getMessage()],
                'status_code' => 500,
                'error_code'  => 'NETWORK_ERROR',
            ];
        }
    }

    protected function sendViaAfricastalking(string $phoneNumber, string $message, array $options = []): array
    {
        $cfg      = $this->providerConfig('africastalking');
        $apiKey   = $cfg['api_key']   ?? null;
        $username = $cfg['username']  ?? 'sandbox';
        $senderId = $this->resolveSenderId('africastalking');

        try {
            $payload = [
                'username' => $username,
                'to'       => $phoneNumber,
                'message'  => $message,
            ];

            if (!empty($senderId)) {
                $payload['from'] = $senderId;
            }

            $response = Http::timeout($this->httpTimeout())
                ->withHeaders([
                    'apiKey'       => $apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ])
                ->post('https://api.africastalking.com/version1/messaging', $payload);

            $responseData = $response->json();
            $statusCode   = $response->status();

            if ($response->successful() && isset($responseData['SMSMessageData']['Recipients'][0])) {
                $recipient = $responseData['SMSMessageData']['Recipients'][0];

                return [
                    'success'     => true,
                    'message'     => "SMS sent successfully via Africa's Talking",
                    'provider'    => 'africastalking',
                    'message_id'  => $recipient['messageId'] ?? null,
                    'response'    => $responseData,
                    'status_code' => $statusCode,
                    'details'     => [
                        'message_status'   => $recipient['status'] ?? 'sent',
                        'username'         => $username,
                        'sender_id'        => $senderId,
                        'sender_id_source' => $this->explainSenderId('africastalking')['source'],
                        'api_status'       => 'success',
                        'cost'             => $recipient['cost'] ?? 'unknown',
                    ],
                ];
            }

            $errorMessage = $responseData['SMSMessageData']['Message']
                ?? $responseData['errorMessage']
                ?? $responseData['message']
                ?? 'Unknown error occurred';

            $errorCode = $responseData['errorCode'] ?? 'unknown';

            Log::warning("Africa's Talking SMS send failed", [
                'status_code'   => $statusCode,
                'error_message' => $errorMessage,
                'error_code'    => $errorCode,
            ]);

            return [
                'success'     => false,
                'message'     => "Africa's Talking SMS failed: " . $errorMessage,
                'provider'    => 'africastalking',
                'response'    => $responseData,
                'status_code' => $statusCode,
                'error_code'  => 'AFRICAS_TALKING_API_ERROR',
                'details'     => [
                    'api_error_code' => $errorCode,
                    'api_message'    => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Africa's Talking SMS sending exception: " . $e->getMessage());
            return [
                'success'     => false,
                'message'     => "Africa's Talking SMS failed: " . $e->getMessage(),
                'provider'    => 'africastalking',
                'response'    => ['error' => $e->getMessage()],
                'status_code' => 500,
                'error_code'  => 'NETWORK_ERROR',
            ];
        }
    }

    protected function sendViaHubtel(string $phoneNumber, string $message, array $options = []): array
    {
        $cfg          = $this->providerConfig('hubtel');
        $clientId     = $cfg['client_id']     ?? null;
        $clientSecret = $cfg['client_secret'] ?? null;
        $senderId     = $this->resolveSenderId('hubtel');

        try {
            $payload = [
                'From'               => $senderId,
                'To'                 => $phoneNumber,
                'Content'            => $message,
                'RegisteredDelivery' => true,
            ];

            $response = Http::timeout($this->httpTimeout())
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post('https://api.hubtel.com/v1/messages', $payload);

            $responseData = $response->json();
            $statusCode   = $response->status();

            if ($response->successful() && isset($responseData['Status']) && $responseData['Status'] == 0) {
                return [
                    'success'     => true,
                    'message'     => 'SMS sent successfully via Hubtel',
                    'provider'    => 'hubtel',
                    'message_id'  => $responseData['MessageId'] ?? null,
                    'response'    => $responseData,
                    'status_code' => $statusCode,
                    'details'     => [
                        'sender_id'        => $senderId,
                        'sender_id_source' => $this->explainSenderId('hubtel')['source'],
                        'api_status'       => 'success',
                    ],
                ];
            }

            $errorMessage = $responseData['Message'] ?? 'Unknown error occurred';
            $errorStatus  = $responseData['Status']  ?? 'unknown';

            return [
                'success'     => false,
                'message'     => "Hubtel SMS failed: {$errorMessage}",
                'provider'    => 'hubtel',
                'response'    => $responseData,
                'status_code' => $statusCode,
                'error_code'  => 'HUBTEL_API_ERROR',
                'details'     => [
                    'api_error_status' => $errorStatus,
                    'api_message'      => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'     => false,
                'message'     => 'Hubtel SMS failed: ' . $e->getMessage(),
                'provider'    => 'hubtel',
                'response'    => ['error' => $e->getMessage()],
                'status_code' => 500,
                'error_code'  => 'NETWORK_ERROR',
            ];
        }
    }

    protected function sendViaNalosolutions(string $phoneNumber, string $message, array $options = []): array
    {
        $cfg      = $this->providerConfig('nalosolutions');
        $apiKey   = $cfg['api_key']   ?? null;
        $baseUrl  = $cfg['base_url']  ?? 'https://sms.nalosolutions.com';
        $senderId = $this->resolveSenderId('nalosolutions');

        try {
            $payload = [
                'key'  => $apiKey,
                'src'  => $senderId,
                'dest' => $phoneNumber,
                'msg'  => $message,
                'type' => 0,
            ];

            $response = Http::timeout($this->httpTimeout())
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post(rtrim($baseUrl, '/') . '/api/sms/send', $payload);

            $responseData = $response->json();
            $statusCode   = $response->status();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] == 'success') {
                return [
                    'success'     => true,
                    'message'     => 'SMS sent successfully via Nalo Solutions',
                    'provider'    => 'nalosolutions',
                    'message_id'  => $responseData['message_id'] ?? null,
                    'response'    => $responseData,
                    'status_code' => $statusCode,
                    'details'     => [
                        'sender_id'        => $senderId,
                        'sender_id_source' => $this->explainSenderId('nalosolutions')['source'],
                        'api_status'       => 'success',
                    ],
                ];
            }

            $errorMessage = $responseData['message'] ?? 'Unknown error occurred';
            $errorStatus  = $responseData['status']  ?? 'unknown';

            return [
                'success'     => false,
                'message'     => "Nalo Solutions SMS failed: {$errorMessage}",
                'provider'    => 'nalosolutions',
                'response'    => $responseData,
                'status_code' => $statusCode,
                'error_code'  => 'NALO_API_ERROR',
                'details'     => [
                    'api_error_status' => $errorStatus,
                    'api_message'      => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'     => false,
                'message'     => 'Nalo Solutions SMS failed: ' . $e->getMessage(),
                'provider'    => 'nalosolutions',
                'response'    => ['error' => $e->getMessage()],
                'status_code' => 500,
                'error_code'  => 'NETWORK_ERROR',
            ];
        }
    }

    // =========================================================================
    // CONNECTION TESTS
    // =========================================================================

    public function sendTestMessage($provider, $phoneNumber, $message): array
    {
        $result = $this->sendSMS($provider, $phoneNumber, $message, ['is_test' => true]);

        $result['test_details'] = [
            'type'             => 'test_message',
            'provider_status'  => $result['success'] ? 'operational' : 'failed',
            'sender_id'        => $this->resolveSenderId($provider),
            'sender_id_source' => $this->explainSenderId($provider)['source'],
            'timestamp'        => now()->toISOString(),
        ];

        return $result;
    }

    public function testConnection($provider): array
    {
        if (!isset($this->providers[$provider])) {
            return $this->formatErrorResponse("SMS provider {$provider} not found", $provider, 'PROVIDER_NOT_FOUND');
        }

        if (!$this->isProviderEnabled($provider)) {
            return $this->formatErrorResponse("SMS provider {$provider} is not enabled", $provider, 'PROVIDER_DISABLED');
        }

        if (!$this->isProviderConfigured($provider)) {
            $missing = $this->getMissingConfiguration($provider);
            return $this->formatErrorResponse(
                "SMS provider {$provider} is not properly configured. Missing: " . implode(', ', $missing),
                $provider,
                'CONFIGURATION_INCOMPLETE',
                ['missing_configuration' => $missing]
            );
        }

        try {
            $method = 'test' . ucfirst($provider) . 'Connection';

            if (method_exists($this, $method)) {
                $result = $this->$method();
                if ($result['success']) {
                    $result['timestamp'] = now()->toISOString();
                    $result['provider']  = $provider;
                }
                return $result;
            }

            return $this->performGenericConnectionTest($provider);
        } catch (Exception $e) {
            Log::error("SMS connection test failed for {$provider}: " . $e->getMessage());
            return $this->formatErrorResponse('Connection test failed: ' . $e->getMessage(), $provider, 'EXCEPTION');
        }
    }

    public function testArkeselConnection(): array
    {
        $cfg     = $this->providerConfig('arkesel');
        $apiKey  = $cfg['api_key']  ?? null;
        $baseUrl = $cfg['base_url'] ?? 'https://sms.arkesel.com';

        try {
            $endpoints = [
                rtrim($baseUrl, '/') . '/api/v2/balance',
                rtrim($baseUrl, '/') . '/api/v1/balance',
                rtrim($baseUrl, '/') . '/api/balance',
            ];

            foreach ($endpoints as $endpoint) {
                try {
                    $response = Http::timeout(15)
                        ->withHeaders(['api-key' => $apiKey])
                        ->get($endpoint);

                    if ($response->successful()) {
                        $data    = $response->json();
                        $balance = $data['balance']
                            ?? $data['data']['balance']
                            ?? $data['sms_balance']
                            ?? 'unknown';

                        return [
                            'success' => true,
                            'message' => 'Arkesel API is reachable and credentials are valid',
                            'details' => [
                                'api_status'       => 'operational',
                                'status_code'      => $response->status(),
                                'balance'          => $balance,
                                'sender_id'        => $this->resolveSenderId('arkesel'),
                                'sender_id_source' => $this->explainSenderId('arkesel')['source'],
                                'endpoint_used'    => $endpoint,
                            ],
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            return $this->testArkeselConnectionAlternative();
        } catch (Exception $e) {
            Log::error('Arkesel connection error: ' . $e->getMessage());
            return [
                'success'    => false,
                'message'    => 'Arkesel connection failed: ' . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
            ];
        }
    }

    protected function testArkeselConnectionAlternative(): array
    {
        $cfg     = $this->providerConfig('arkesel');
        $apiKey  = $cfg['api_key']  ?? null;
        $baseUrl = $cfg['base_url'] ?? 'https://sms.arkesel.com';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'api-key'      => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(rtrim($baseUrl, '/') . '/api/v2/sms/send', [
                    'sender'     => 'TEST',
                    'message'    => 'Connection Test',
                    'recipients' => ['0000000000'],
                ]);

            $status = $response->status();

            if (in_array($status, [400, 422])) {
                return [
                    'success' => true,
                    'message' => 'Arkesel API is reachable and credentials are valid',
                    'details' => [
                        'api_status'       => 'operational',
                        'test_result'      => 'Authentication successful, phone number invalid (expected)',
                        'sender_id'        => $this->resolveSenderId('arkesel'),
                        'sender_id_source' => $this->explainSenderId('arkesel')['source'],
                    ],
                ];
            }

            if (in_array($status, [401, 403])) {
                return [
                    'success'    => false,
                    'message'    => 'Arkesel authentication failed: Invalid API key',
                    'error_code' => 'AUTH_FAILED',
                    'details'    => [
                        'status_code' => $status,
                        'api_message' => $response->json()['message'] ?? 'Authentication failed',
                    ],
                ];
            }

            $errorMessage = $response->json()['message'] ?? 'API endpoint returned status: ' . $status;

            return [
                'success'    => false,
                'message'    => 'Arkesel connection failed: ' . $errorMessage,
                'error_code' => 'API_ERROR',
                'details'    => ['status_code' => $status, 'api_message' => $errorMessage],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Arkesel connection failed: ' . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
            ];
        }
    }

    public function testTwilioConnection(): array
    {
        $cfg        = $this->providerConfig('twilio');
        $accountSid = $cfg['account_sid'] ?? null;
        $authToken  = $cfg['auth_token']  ?? null;

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($accountSid, $authToken)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

            $data = $response->json();

            if ($response->successful() && isset($data['status'])) {
                return [
                    'success'        => true,
                    'message'        => 'Twilio connection successful',
                    'account_status' => $data['status'],
                    'friendly_name'  => $data['friendly_name'] ?? 'Unknown',
                    'details'        => [
                        'account_status' => $data['status'],
                        'friendly_name'  => $data['friendly_name'] ?? 'Unknown',
                        'api_status'     => 'operational',
                        'from_number'    => $cfg['from_number'] ?? null,
                    ],
                ];
            }

            $errorMessage = $data['message'] ?? $data['error_message'] ?? 'Unknown error occurred';

            return [
                'success'    => false,
                'message'    => 'Twilio connection failed: ' . $errorMessage,
                'error_code' => 'TWILIO_API_ERROR',
                'details'    => [
                    'api_status'  => 'failed',
                    'status_code' => $response->status(),
                    'error'       => $errorMessage,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Twilio connection failed: ' . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
                'details'    => ['api_status' => 'connection_error', 'error' => $e->getMessage()],
            ];
        }
    }

    public function testAfricasTalkingConnection(): array
    {
        $cfg      = $this->providerConfig('africastalking');
        $apiKey   = $cfg['api_key']  ?? null;
        $username = $cfg['username'] ?? 'sandbox';

        try {
            $endpoints = [
                "https://api.africastalking.com/version1/user?username={$username}",
                "https://api.africastalking.com/user",
                "https://api.sandbox.africastalking.com/version1/user?username={$username}",
            ];

            foreach ($endpoints as $endpoint) {
                try {
                    $response = Http::timeout(15)
                        ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
                        ->get($endpoint);

                    $data   = $response->json();
                    $status = $response->status();

                    if ($response->successful() && isset($data['UserData'])) {
                        return [
                            'success'  => true,
                            'message'  => "Africa's Talking connection successful",
                            'username' => $username,
                            'balance'  => $data['UserData']['balance'] ?? 'unknown',
                            'details'  => [
                                'username'         => $username,
                                'balance'          => $data['UserData']['balance'] ?? 'unknown',
                                'api_status'       => 'operational',
                                'sender_id'        => $this->resolveSenderId('africastalking'),
                                'sender_id_source' => $this->explainSenderId('africastalking')['source'],
                                'endpoint_used'    => $endpoint,
                            ],
                        ];
                    }

                    if (in_array($status, [401, 403])) {
                        return [
                            'success'    => false,
                            'message'    => "Africa's Talking authentication failed: Invalid API key or username",
                            'error_code' => 'AUTH_FAILED',
                            'details'    => [
                                'status_code' => $status,
                                'api_message' => $data['errorMessage'] ?? 'Authentication failed',
                            ],
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            return $this->testAfricasTalkingFinalFallback();
        } catch (Exception $e) {
            Log::error("Africa's Talking connection error: " . $e->getMessage());
            return [
                'success'    => false,
                'message'    => "Africa's Talking connection failed: " . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
            ];
        }
    }

    protected function testAfricasTalkingFinalFallback(): array
    {
        $cfg    = $this->providerConfig('africastalking');
        $apiKey = $cfg['api_key'] ?? null;

        try {
            $response = Http::timeout(10)
                ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
                ->get('https://api.africastalking.com');

            $status = $response->status();

            if ($response->successful() || $status === 404) {
                return [
                    'success'    => false,
                    'message'    => "Africa's Talking API is reachable but authentication or endpoint may be incorrect",
                    'error_code' => 'ENDPOINT_ISSUE',
                    'details'    => ['api_status' => 'reachable', 'status_code' => $status],
                ];
            }

            return [
                'success'    => false,
                'message'    => "Africa's Talking API is not reachable",
                'error_code' => 'NETWORK_ERROR',
                'details'    => ['api_status' => 'unreachable', 'status_code' => $status],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => "Africa's Talking connection failed: " . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
            ];
        }
    }

    public function testHubtelConnection(): array
    {
        $cfg          = $this->providerConfig('hubtel');
        $clientId     = $cfg['client_id']     ?? null;
        $clientSecret = $cfg['client_secret'] ?? null;

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($clientId, $clientSecret)
                ->get('https://api.hubtel.com/v1/account/balance');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success'  => true,
                    'message'  => 'Hubtel connection successful',
                    'balance'  => $data['balance']  ?? 'unknown',
                    'currency' => $data['currency'] ?? 'GHS',
                    'details'  => [
                        'balance'          => $data['balance']  ?? 'unknown',
                        'currency'         => $data['currency'] ?? 'GHS',
                        'api_status'       => 'operational',
                        'sender_id'        => $this->resolveSenderId('hubtel'),
                        'sender_id_source' => $this->explainSenderId('hubtel')['source'],
                    ],
                ];
            }

            $errorMessage = $response->json()['message'] ?? 'Unknown error occurred';

            return [
                'success'    => false,
                'message'    => 'Hubtel connection failed: ' . $errorMessage,
                'error_code' => 'HUBTEL_API_ERROR',
                'details'    => ['api_status' => 'failed', 'status_code' => $response->status()],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Hubtel connection failed: ' . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
                'details'    => ['api_status' => 'connection_error', 'error' => $e->getMessage()],
            ];
        }
    }

    public function testNaloSolutionsConnection(): array
    {
        $cfg    = $this->providerConfig('nalosolutions');
        $apiKey = $cfg['api_key'] ?? null;

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get('https://sms.nalosolutions.com/api/balance');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success'   => true,
                    'message'   => 'Nalo Solutions connection successful',
                    'balance'   => $data['balance'] ?? 'unknown',
                    'sender_id' => $this->resolveSenderId('nalosolutions'),
                    'details'   => [
                        'balance'          => $data['balance'] ?? 'unknown',
                        'sender_id'        => $this->resolveSenderId('nalosolutions'),
                        'sender_id_source' => $this->explainSenderId('nalosolutions')['source'],
                        'api_status'       => 'operational',
                    ],
                ];
            }

            $errorMessage = $response->json()['message'] ?? 'Unknown error occurred';

            return [
                'success'    => false,
                'message'    => 'Nalo Solutions connection failed: ' . $errorMessage,
                'error_code' => 'NALO_API_ERROR',
                'details'    => ['api_status' => 'failed', 'status_code' => $response->status()],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Nalo Solutions connection failed: ' . $e->getMessage(),
                'error_code' => 'NETWORK_ERROR',
                'details'    => ['api_status' => 'connection_error', 'error' => $e->getMessage()],
            ];
        }
    }

    protected function performGenericConnectionTest(string $provider): array
    {
        try {
            $testNumber  = '+233200000000';
            $testMessage = 'Test SMS - Connection verification';

            $result = $this->sendSMS($provider, $testNumber, $testMessage, ['is_test' => true]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Provider connection verified via test SMS',
                    'details' => [
                        'verification_method' => 'test_sms',
                        'api_status'          => 'operational',
                    ],
                ];
            }

            return [
                'success'    => false,
                'message'    => 'Provider connection test failed: ' . $result['message'],
                'error_code' => $result['error_code'] ?? 'TEST_SMS_FAILED',
                'details'    => [
                    'verification_method' => 'test_sms',
                    'api_status'          => 'failed',
                    'error'               => $result['message'],
                ],
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'message'    => 'Generic connection test failed: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION',
                'details'    => [
                    'verification_method' => 'generic',
                    'api_status'          => 'failed',
                    'error'               => $e->getMessage(),
                ],
            ];
        }
    }

    // =========================================================================
    // ENV KEY MAPS
    // =========================================================================

    protected function getEnvKey(string $provider, string $field): string
    {
        $map = [
            'arkesel' => [
                'api_key'   => 'ARKESEL_SMS_API_KEY',
                'sender_id' => 'ARKESEL_SMS_SENDER_ID',
                'base_url'  => 'ARKESEL_SMS_BASE_URL',
            ],
            'twilio' => [
                'account_sid' => 'TWILIO_ACCOUNT_SID',
                'auth_token'  => 'TWILIO_AUTH_TOKEN',
                'from_number' => 'TWILIO_FROM_NUMBER',
            ],
            'africastalking' => [
                'api_key'   => 'AFRICASTALKING_API_KEY',
                'username'  => 'AFRICASTALKING_USERNAME',
                'sender_id' => 'AFRICASTALKING_SENDER_ID',
            ],
            'hubtel' => [
                'client_id'     => 'HUBTEL_CLIENT_ID',
                'client_secret' => 'HUBTEL_CLIENT_SECRET',
                'sender_id'     => 'HUBTEL_SENDER_ID',
            ],
            'nalosolutions' => [
                'api_key'   => 'NALOSOLUTIONS_API_KEY',
                'sender_id' => 'NALOSOLUTIONS_SMS_SENDER_ID',
                'base_url'  => 'NALOSOLUTIONS_SMS_BASE_URL',
            ],
        ];

        return $map[$provider][$field] ?? strtoupper($provider) . '_SMS_' . strtoupper($field);
    }

    protected function getEnableEnvKey(string $provider): string
    {
        $map = [
            'arkesel'        => 'ARKESEL_SMS_ENABLED',
            'twilio'         => 'TWILIO_SMS_ENABLED',
            'africastalking' => 'AFRICASTALKING_SMS_ENABLED',
            'hubtel'         => 'HUBTEL_SMS_ENABLED',
            'nalosolutions'  => 'NALOSOLUTIONS_SMS_ENABLED',
        ];

        return $map[$provider] ?? strtoupper($provider) . '_SMS_ENABLED';
    }

    // =========================================================================
    // PHONE NORMALIZATION
    // =========================================================================

    protected function normalizePhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            $countryCode = (string) config('sms.default_country_code', '233');
            $clean = '+' . $countryCode . substr($clean, 1);
        } elseif (str_starts_with($clean, (string) config('sms.default_country_code', '233'))
            && !str_starts_with($clean, '+')
        ) {
            $clean = '+' . $clean;
        } elseif (!str_starts_with($clean, '+') && strlen($clean) >= 10) {
            $clean = '+' . $clean;
        }

        return $clean;
    }

    protected function validatePhoneNumber(string $phoneNumber): bool
    {
        $clean = preg_replace('/[^+\d]/', '', $phoneNumber);
        return (bool) preg_match('/^\+\d{10,15}$/', $clean);
    }

    // =========================================================================
    // USAGE STATS
    // =========================================================================

    public function getUsageStatistics(): array
    {
        try {
            $totalSent  = SmsLog::count();
            $successful = SmsLog::where('status', 'success')->count();
            $failed     = SmsLog::where('status', 'failed')->count();

            $thisMonth = SmsLog::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            $today = SmsLog::whereDate('created_at', today())->count();

            $providerStats = SmsLog::select('provider')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful')
                ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
                ->selectRaw('ROUND(SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate')
                ->groupBy('provider')
                ->get()
                ->keyBy('provider');

            $recentActivity = SmsLog::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return [
                'total_sent'      => $totalSent,
                'successful'      => $successful,
                'failed'          => $failed,
                'this_month'      => $thisMonth,
                'today'           => $today,
                'success_rate'    => $totalSent > 0 ? round(($successful / $totalSent) * 100, 2) : 0,
                'provider_stats'  => $providerStats,
                'recent_activity' => $recentActivity,
                'timestamp'       => now()->toISOString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get SMS usage statistics: ' . $e->getMessage());

            return [
                'total_sent'      => 0,
                'successful'      => 0,
                'failed'          => 0,
                'this_month'      => 0,
                'today'           => 0,
                'success_rate'    => 0,
                'provider_stats'  => [],
                'recent_activity' => [],
                'error'           => $e->getMessage(),
                'timestamp'       => now()->toISOString(),
            ];
        }
    }

    // =========================================================================
    // MAINTENANCE / HEALTH
    // =========================================================================

    public function cleanupOldLogs(int $days = 30): array
    {
        try {
            $cutoff = now()->subDays($days);
            $count  = SmsLog::where('created_at', '<', $cutoff)->delete();

            Log::info("Cleaned up {$count} SMS logs older than {$days} days");

            return [
                'success'       => true,
                'deleted_count' => $count,
                'message'       => "Cleaned up {$count} SMS logs older than {$days} days",
                'timestamp'     => now()->toISOString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to cleanup SMS logs: ' . $e->getMessage());

            return [
                'success'    => false,
                'message'    => 'Failed to cleanup SMS logs: ' . $e->getMessage(),
                'error_code' => 'CLEANUP_FAILED',
                'timestamp'  => now()->toISOString(),
            ];
        }
    }

    public function clearStatusCache(): bool
    {
        Cache::forget('sms_service_quick_status');
        $this->forgetGlobalSenderIdCache();
        return true;
    }

    public function validateEnvironmentConfig(): array
    {
        $issues          = [];
        $recommendations = [];

        $enabledProviders = [];
        foreach (array_keys($this->providers) as $key) {
            if ($this->isProviderEnabled($key)) {
                $enabledProviders[$key] = true;
            }
        }

        if (empty($enabledProviders)) {
            $issues[]          = 'No SMS providers are enabled';
            $recommendations[] = 'Enable at least one SMS provider in your .env file';
        }

        $configuredProviders = [];
        foreach (array_keys($enabledProviders) as $key) {
            if ($this->isProviderConfigured($key)) {
                $configuredProviders[$key] = true;
            }
        }

        if (empty($configuredProviders)) {
            $issues[]          = 'No enabled SMS providers are fully configured';
            $recommendations[] = 'Check required environment variables for enabled providers';
        }

        $default = config('sms.default');
        if ($default && !$this->isProviderEnabled($default)) {
            $issues[]          = "Default provider '{$default}' is not enabled";
            $recommendations[] = "Enable {$default} or change DEFAULT_SMS_PROVIDER";
        }

        return [
            'has_issues'             => !empty($issues),
            'issues'                 => $issues,
            'recommendations'        => $recommendations,
            'enabled_providers'      => count($enabledProviders),
            'configured_providers'   => count($configuredProviders),
            'default_provider_ready' => $default
                ? ($this->isProviderEnabled($default) && $this->isProviderConfigured($default))
                : false,
        ];
    }

    public function getSystemHealth(): array
    {
        $systemStatus  = $this->getSystemStatus();
        $configuration = $this->checkConfiguration();
        $environment   = $this->validateEnvironmentConfig();
        $usageStats    = $this->getUsageStatistics();

        $score = 0;
        if ($systemStatus['system_ready'])    $score += 40;
        if (!$environment['has_issues'])      $score += 30;
        if ($usageStats['success_rate'] > 80) $score += 30;

        return [
            'health_score'    => $score,
            'health_status'   => $score >= 80 ? 'excellent'
                : ($score >= 60 ? 'good'
                : ($score >= 40 ? 'fair' : 'poor')),
            'system_status'   => $systemStatus,
            'configuration'   => $configuration,
            'environment'     => $environment,
            'usage_stats'     => $usageStats,
            'timestamp'       => now()->toISOString(),
            'recommendations' => $this->generateHealthRecommendations($systemStatus, $environment, $usageStats),
        ];
    }

    protected function generateHealthRecommendations(array $systemStatus, array $environment, array $usageStats): array
    {
        $recommendations = [];

        if (!$systemStatus['system_ready']) {
            $recommendations[] = 'Configure at least one SMS provider to enable SMS functionality';
        }

        if ($environment['has_issues']) {
            $recommendations = array_merge($recommendations, $environment['recommendations']);
        }

        if ($usageStats['success_rate'] < 80 && $usageStats['total_sent'] > 10) {
            $recommendations[] = 'SMS success rate is low. Check provider configurations and balance.';
        }

        if ($systemStatus['ready_providers'] === 0) {
            $recommendations[] = 'No SMS providers are ready. Enable and configure at least one provider.';
        }

        return array_slice($recommendations, 0, 5);
    }

    // =========================================================================
    // LOGGING / MASKING / ERRORS
    // =========================================================================

    protected function logSMS(string $provider, string $phoneNumber, string $message, array $result): void
    {
        try {
            SmsLog::create([
                'provider'       => $provider,
                'phone_number'   => $this->maskPhoneNumber($phoneNumber),
                'message'        => $message,
                'status'         => $result['success'] ? 'success' : 'failed',
                'response'       => $result['response'] ?? null,
                'message_id'     => $result['message_id'] ?? null,
                'error_message'  => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
                'error_code'     => $result['error_code'] ?? null,
                'is_test'        => $result['is_test'] ?? false,
                'execution_time' => $result['execution_time_ms'] ?? null,
                'status_code'    => $result['status_code'] ?? null,
                'details'        => $result['details'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log SMS: ' . $e->getMessage());
        }
    }

    protected function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 8) {
            return $phoneNumber;
        }

        return substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -4);
    }

    protected function formatErrorResponse(string $message, string $provider, string $errorCode = 'UNKNOWN_ERROR', array $additionalData = []): array
    {
        return array_merge([
            'success'     => false,
            'message'     => $message,
            'provider'    => $provider,
            'error_code'  => $errorCode,
            'status_code' => 400,
            'timestamp'   => now()->toISOString(),
        ], $additionalData);
    }

    protected function maskSensitiveValue(string $field, $value): string
    {
        $sensitive = ['api_key', 'auth_token', 'client_secret', 'account_sid'];

        if (in_array($field, $sensitive) && strlen((string) $value) > 8) {
            return substr($value, 0, 4) . '****' . substr($value, -4);
        }

        return (string) $value;
    }
}