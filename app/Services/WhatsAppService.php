<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client as TwilioClient;
use Vonage\Client as VonageClient;
use Vonage\Client\Credentials\Basic as VonageBasic;
use Exception;

class WhatsAppService
{
    protected $isConfigured;
    protected $provider;
    protected $lastError;
    protected $config;

    public function __construct()
    {
        $this->config = $this->loadConfiguration();
        $this->isConfigured = $this->checkConfiguration();
        $this->provider = $this->config['provider'];
        $this->lastError = null;
    }

    /* ============================================================
     | CONFIG LOADING
     * ============================================================ */

    /**
     * Load configuration from environment variables.
     * Uses config() first, env() as fallback — so both config/whatsapp.php
     * and direct .env writes are respected.
     */
    protected function loadConfiguration(): array
    {
        return [
            'provider' => env('WHATSAPP_PROVIDER', config('whatsapp.provider', 'none')),

            'twilio' => [
                'sid'           => config('whatsapp.twilio_sid', env('TWILIO_SID')),
                'token'         => config('whatsapp.twilio_token', env('TWILIO_AUTH_TOKEN')),
                'whatsapp_from' => config('whatsapp.twilio_whatsapp_from', env('TWILIO_WHATSAPP_FROM')),
            ],

            'vonage' => [
                'key'           => config('whatsapp.vonage_key', env('VONAGE_KEY')),
                'secret'        => config('whatsapp.vonage_secret', env('VONAGE_SECRET')),
                'whatsapp_from' => config('whatsapp.vonage_whatsapp_from', env('VONAGE_WHATSAPP_FROM')),
            ],

            'custom' => [
                'api_key' => config('whatsapp.whatsapp_api_key', env('WHATSAPP_API_KEY')),
                'api_url' => config('whatsapp.whatsapp_api_url', env('WHATSAPP_API_URL')),
            ],

            '360dialog' => [
                'api_key'         => config('whatsapp.dialog_api_key', env('DIALOG_API_KEY')),
                'phone_number_id' => config('whatsapp.dialog_phone_number_id', env('DIALOG_PHONE_NUMBER_ID')),
                'business_id'     => config('whatsapp.dialog_business_id', env('DIALOG_BUSINESS_ID')),
            ],

            'wati' => [
                'api_key' => config('whatsapp.wati_api_key', env('WATI_API_KEY')),
                'api_url' => config('whatsapp.wati_api_url', env('WATI_API_URL')),
            ],

            'vumaapi' => [
                'api_key'       => config('whatsapp.vumaapi_api_key', env('VUMAAPI_API_KEY')),
                'api_secret'    => config('whatsapp.vumaapi_api_secret', env('VUMAAPI_API_SECRET')),
                'whatsapp_from' => config('whatsapp.vumaapi_whatsapp_from', env('VUMAAPI_WHATSAPP_FROM')),
                'api_url'       => config('whatsapp.vumaapi_api_url', env('VUMAAPI_API_URL', 'https://api.vumacloud.com')),
                'environment'   => config('whatsapp.vumaapi_environment', env('VUMAAPI_ENVIRONMENT', 'sandbox')),
            ],

            'enabled' => config('whatsapp.enabled', false),
        ];
    }

    /**
     * Check configuration status.
     */
    protected function checkConfiguration(): bool
    {
        if ($this->config['provider'] === 'none') {
            return false;
        }

        return match ($this->config['provider']) {
            'twilio' => !empty($this->config['twilio']['sid'])
                     && !empty($this->config['twilio']['token'])
                     && !empty($this->config['twilio']['whatsapp_from']),

            'vonage' => !empty($this->config['vonage']['key'])
                     && !empty($this->config['vonage']['secret'])
                     && !empty($this->config['vonage']['whatsapp_from']),

            'custom' => !empty($this->config['custom']['api_key'])
                     && !empty($this->config['custom']['api_url']),

            '360dialog' => !empty($this->config['360dialog']['api_key'])
                        && !empty($this->config['360dialog']['phone_number_id'])
                        && !empty($this->config['360dialog']['business_id']),

            'wati' => !empty($this->config['wati']['api_key'])
                   && !empty($this->config['wati']['api_url']),

            'vumaapi' => !empty($this->config['vumaapi']['api_key'])
                      && !empty($this->config['vumaapi']['whatsapp_from'])
                      && !empty($this->config['vumaapi']['api_url']),

            default => false,
        };
    }

    /* ============================================================
     | PUBLIC ACCESSORS (for consumers like SystemSettingController)
     * ============================================================ */

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function getActiveProviderKey(): string
    {
        return $this->provider;
    }

    public function getActiveProviderName(): string
    {
        return $this->getProviderDisplayName($this->provider);
    }

    public function hasAnyConfiguredProvider(): bool
    {
        return $this->isConfigured;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /* ============================================================
     | STATUS METHODS
     * ============================================================ */

    /**
     * Get quick status.
     */
    public function getQuickStatus(): array
    {
        try {
            $health = $this->checkHealth();

            $status = [
                'system_ready'         => $this->isConfigured && $health === 'healthy',
                'can_send_whatsapp'    => $this->isConfigured && in_array($health, ['healthy', 'degraded'], true),
                'provider'             => $this->provider,
                'provider_name'        => $this->getProviderDisplayName($this->provider),
                'health_status'        => $health,
                'message'              => $this->getStatusMessage($health),
                'last_checked'         => now()->toISOString(),
                'configuration_status' => $this->isConfigured ? 'configured' : 'not_configured',
                'network_status'       => $this->checkNetworkConnectivity()['connected'] ? 'online' : 'offline',
            ];

            if ($this->isConfigured) {
                $status['provider_details'] = $this->getProviderConfigurationStatus();
            }

            return $status;

        } catch (Exception $e) {
            Log::error('WhatsApp getQuickStatus failed: ' . $e->getMessage());

            return [
                'system_ready'      => false,
                'can_send_whatsapp' => false,
                'provider'          => $this->provider,
                'health_status'     => 'error',
                'message'           => 'Error checking status: ' . $e->getMessage(),
                'error'             => $e->getMessage(),
                'last_checked'      => now()->toISOString(),
            ];
        }
    }

    /**
     * Get all providers with status.
     */
    public function getAllProvidersWithStatus(): array
    {
        $providers = [
            'twilio' => [
                'name'                  => 'Twilio WhatsApp',
                'key'                   => 'twilio',
                'enabled'               => $this->isProviderActive('twilio'),
                'configured'            => $this->isProviderConfigured('twilio'),
                'status'                => $this->getProviderStatus('twilio'),
                'missing_configuration' => $this->getMissingConfigurationFields('twilio'),
                'icon'                  => 'fab fa-twilio',
                'color'                 => '#F22F46',
                'documentation_url'     => 'https://www.twilio.com/docs/whatsapp',
            ],
            'vonage' => [
                'name'                  => 'Vonage WhatsApp',
                'key'                   => 'vonage',
                'enabled'               => $this->isProviderActive('vonage'),
                'configured'            => $this->isProviderConfigured('vonage'),
                'status'                => $this->getProviderStatus('vonage'),
                'missing_configuration' => $this->getMissingConfigurationFields('vonage'),
                'icon'                  => 'fab fa-vonage',
                'color'                 => '#1A1A2E',
                'documentation_url'     => 'https://developer.vonage.com/en/messages/whatsapp/overview',
            ],
            'custom' => [
                'name'                  => 'Custom WhatsApp API',
                'key'                   => 'custom',
                'enabled'               => $this->isProviderActive('custom'),
                'configured'            => $this->isProviderConfigured('custom'),
                'status'                => $this->getProviderStatus('custom'),
                'missing_configuration' => $this->getMissingConfigurationFields('custom'),
                'icon'                  => 'fas fa-code',
                'color'                 => '#6B7280',
                'documentation_url'     => '#',
            ],
            '360dialog' => [
                'name'                  => '360Dialog WhatsApp',
                'key'                   => '360dialog',
                'enabled'               => $this->isProviderActive('360dialog'),
                'configured'            => $this->isProviderConfigured('360dialog'),
                'status'                => $this->getProviderStatus('360dialog'),
                'missing_configuration' => $this->getMissingConfigurationFields('360dialog'),
                'icon'                  => 'fas fa-comments',
                'color'                 => '#00A884',
                'documentation_url'     => 'https://docs.360dialog.com/',
            ],
            'wati' => [
                'name'                  => 'WATI WhatsApp',
                'key'                   => 'wati',
                'enabled'               => $this->isProviderActive('wati'),
                'configured'            => $this->isProviderConfigured('wati'),
                'status'                => $this->getProviderStatus('wati'),
                'missing_configuration' => $this->getMissingConfigurationFields('wati'),
                'icon'                  => 'fas fa-comment-dots',
                'color'                 => '#00A884',
                'documentation_url'     => 'https://docs.wati.io/',
            ],
            'vumaapi' => [
                'name'                  => 'VumaAPI WhatsApp (Ghana)',
                'key'                   => 'vumaapi',
                'enabled'               => $this->isProviderActive('vumaapi'),
                'configured'            => $this->isProviderConfigured('vumaapi'),
                'status'                => $this->getProviderStatus('vumaapi'),
                'missing_configuration' => $this->getMissingConfigurationFields('vumaapi'),
                'icon'                  => 'fas fa-sms',
                'color'                 => '#0EA5E9',
                'documentation_url'     => 'https://www.vumacloud.com/api/ghana',
            ],
        ];

        foreach ($providers as $key => &$provider) {
            $provider['is_active'] = $this->provider === $key && $this->isConfigured;
            $provider['is_ready'] = $provider['enabled'] && $provider['configured'] && $provider['is_active'];
        }
        unset($provider);

        return $providers;
    }

    /**
     * Check if a specific provider is properly configured.
     */
    protected function isProviderConfigured(string $provider): bool
    {
        switch ($provider) {
            case 'twilio':
                return !empty($this->config['twilio']['sid'])
                    && !empty($this->config['twilio']['token'])
                    && !empty($this->config['twilio']['whatsapp_from']);

            case 'vonage':
                return !empty($this->config['vonage']['key'])
                    && !empty($this->config['vonage']['secret'])
                    && !empty($this->config['vonage']['whatsapp_from']);

            case 'custom':
                return !empty($this->config['custom']['api_key'])
                    && !empty($this->config['custom']['api_url']);

            case '360dialog':
                return !empty($this->config['360dialog']['api_key'])
                    && !empty($this->config['360dialog']['phone_number_id'])
                    && !empty($this->config['360dialog']['business_id']);

            case 'wati':
                return !empty($this->config['wati']['api_key'])
                    && !empty($this->config['wati']['api_url']);

            case 'vumaapi':
                return !empty($this->config['vumaapi']['api_key'])
                    && !empty($this->config['vumaapi']['whatsapp_from'])
                    && !empty($this->config['vumaapi']['api_url']);

            default:
                return false;
        }
    }

    /**
     * Get provider status.
     */
    protected function getProviderStatus(string $provider): string
    {
        try {
            if ($this->provider !== $provider) {
                return 'inactive';
            }

            if (!$this->isProviderConfigured($provider)) {
                return 'misconfigured';
            }

            $health = $this->checkHealth();

            return match ($health) {
                'healthy'             => 'healthy',
                'degraded'            => 'degraded',
                'network_error'       => 'network_error',
                'invalid_credentials' => 'invalid_credentials',
                'timeout'             => 'timeout',
                default               => 'unhealthy',
            };

        } catch (Exception $e) {
            Log::error("Error getting status for provider {$provider}: " . $e->getMessage());
            return 'error';
        }
    }

    /**
     * Get provider configuration status details.
     */
    protected function getProviderConfigurationStatus(): array
    {
        $status = [
            'provider'      => $this->provider,
            'is_configured' => $this->isConfigured,
            'has_network'   => $this->checkNetworkConnectivity()['connected'],
            'health'        => $this->checkHealth(),
        ];

        switch ($this->provider) {
            case 'twilio':
                $status['sid_present']   = !empty($this->config['twilio']['sid']);
                $status['token_present'] = !empty($this->config['twilio']['token']);
                $status['from_present']  = !empty($this->config['twilio']['whatsapp_from']);
                break;

            case 'vonage':
                $status['key_present']    = !empty($this->config['vonage']['key']);
                $status['secret_present'] = !empty($this->config['vonage']['secret']);
                $status['from_present']   = !empty($this->config['vonage']['whatsapp_from']);
                break;

            case 'custom':
                $status['api_key_present'] = !empty($this->config['custom']['api_key']);
                $status['api_url_present'] = !empty($this->config['custom']['api_url']);
                break;

            case '360dialog':
                $status['api_key_present']     = !empty($this->config['360dialog']['api_key']);
                $status['phone_id_present']    = !empty($this->config['360dialog']['phone_number_id']);
                $status['business_id_present'] = !empty($this->config['360dialog']['business_id']);
                break;

            case 'wati':
                $status['api_key_present'] = !empty($this->config['wati']['api_key']);
                $status['api_url_present'] = !empty($this->config['wati']['api_url']);
                break;

            case 'vumaapi':
                $status['api_key_present']    = !empty($this->config['vumaapi']['api_key']);
                $status['api_secret_present'] = !empty($this->config['vumaapi']['api_secret']);
                $status['from_present']       = !empty($this->config['vumaapi']['whatsapp_from']);
                $status['api_url_present']    = !empty($this->config['vumaapi']['api_url']);
                $status['environment']        = $this->config['vumaapi']['environment'] ?? 'sandbox';
                break;
        }

        return $status;
    }

    /**
     * Check if a specific provider is active and properly configured.
     */
    public function isProviderActive(string $provider): bool
    {
        try {
            $activeProvider = env('WHATSAPP_PROVIDER', config('whatsapp.provider', 'none'));

            if ($activeProvider !== $provider) {
                return false;
            }

            return $this->isProviderConfigured($provider);

        } catch (Exception $e) {
            Log::error("Error checking if provider {$provider} is active: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check WhatsApp configuration for admin panel.
     */
    public function checkWhatsAppConfiguration(): array
    {
        try {
            $activeProvider = env('WHATSAPP_PROVIDER', config('whatsapp.provider', 'none'));
            $isConfigured = false;
            $missingFields = [];

            if ($activeProvider !== 'none') {
                $isConfigured = $this->isProviderActive($activeProvider);

                if (!$isConfigured) {
                    $missingFields = $this->getMissingConfigurationFields($activeProvider);
                }
            }

            return [
                'active_provider' => $activeProvider,
                'is_configured'   => $isConfigured,
                'missing_fields'  => $missingFields,
                'provider_name'   => $this->getProviderDisplayName($activeProvider),
                'timestamp'       => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error("Error checking WhatsApp configuration: " . $e->getMessage());

            return [
                'active_provider' => 'error',
                'is_configured'   => false,
                'missing_fields'  => ['error' => $e->getMessage()],
                'provider_name'   => 'Error',
                'timestamp'       => now()->toISOString(),
            ];
        }
    }

    /**
     * Get missing configuration fields for a provider.
     */
    protected function getMissingConfigurationFields(string $provider): array
    {
        $missing = [];

        switch ($provider) {
            case 'twilio':
                if (empty($this->config['twilio']['sid']))           $missing[] = 'TWILIO_SID';
                if (empty($this->config['twilio']['token']))         $missing[] = 'TWILIO_AUTH_TOKEN';
                if (empty($this->config['twilio']['whatsapp_from'])) $missing[] = 'TWILIO_WHATSAPP_FROM';
                break;

            case 'vonage':
                if (empty($this->config['vonage']['key']))           $missing[] = 'VONAGE_KEY';
                if (empty($this->config['vonage']['secret']))        $missing[] = 'VONAGE_SECRET';
                if (empty($this->config['vonage']['whatsapp_from'])) $missing[] = 'VONAGE_WHATSAPP_FROM';
                break;

            case 'custom':
                if (empty($this->config['custom']['api_key'])) $missing[] = 'WHATSAPP_API_KEY';
                if (empty($this->config['custom']['api_url'])) $missing[] = 'WHATSAPP_API_URL';
                break;

            case '360dialog':
                if (empty($this->config['360dialog']['api_key']))         $missing[] = 'DIALOG_API_KEY';
                if (empty($this->config['360dialog']['phone_number_id'])) $missing[] = 'DIALOG_PHONE_NUMBER_ID';
                if (empty($this->config['360dialog']['business_id']))     $missing[] = 'DIALOG_BUSINESS_ID';
                break;

            case 'wati':
                if (empty($this->config['wati']['api_key'])) $missing[] = 'WATI_API_KEY';
                if (empty($this->config['wati']['api_url'])) $missing[] = 'WATI_API_URL';
                break;

            case 'vumaapi':
                if (empty($this->config['vumaapi']['api_key']))       $missing[] = 'VUMAAPI_API_KEY';
                if (empty($this->config['vumaapi']['whatsapp_from'])) $missing[] = 'VUMAAPI_WHATSAPP_FROM';
                if (empty($this->config['vumaapi']['api_url']))       $missing[] = 'VUMAAPI_API_URL';
                // VUMAAPI_API_SECRET is optional
                break;
        }

        return $missing;
    }

    /**
     * Get provider display name.
     */
    protected function getProviderDisplayName(string $providerKey): string
    {
        $providers = [
            'twilio'    => 'Twilio WhatsApp',
            'vonage'    => 'Vonage WhatsApp',
            'custom'    => 'Custom WhatsApp API',
            '360dialog' => '360Dialog WhatsApp',
            'wati'      => 'WATI WhatsApp',
            'vumaapi'   => 'VumaAPI WhatsApp (Ghana)',
            'none'      => 'None',
            'error'     => 'Error',
        ];

        return $providers[$providerKey] ?? $providerKey;
    }

    /**
     * Get system status with network connectivity checks.
     *
     * Includes aggregated keys so consumers (e.g. SystemSettingController)
     * can check any of: configured / enabled / can_send / system_ready.
     */
    public function getSystemStatus(): array
    {
        $networkStatus = $this->checkNetworkConnectivity();
        $health = $this->isConfigured
            ? ($networkStatus['connected'] ? $this->checkHealth() : 'network_error')
            : 'not_configured';

        $status = [
            'enabled'               => $this->isConfigured,
            'provider'              => $this->provider,
            'provider_name'         => $this->getProviderDisplayName($this->provider),
            'configured'            => $this->isConfigured,
            'configured_providers'  => $this->isConfigured ? 1 : 0,
            'enabled_providers'     => $this->isConfigured ? 1 : 0,
            'can_send'              => $this->isConfigured && $networkStatus['connected'],
            'system_ready'          => $this->isConfigured && $health === 'healthy',
            'last_checked'          => now()->toISOString(),
            'health'                => $health,
            'configuration_status'  => $this->getConfigurationStatus(),
            'network_status'        => $networkStatus,
            'features' => [
                'text_messages'  => true,
                'media_messages' => $this->supportsMedia(),
                'templates'      => $this->supportsTemplates(),
                'bulk_messages'  => true,
            ],
            'limits' => [
                'rate_limit'  => $this->getRateLimit(),
                'daily_limit' => $this->getDailyLimit(),
            ],
            'statistics' => $this->getStatistics(),
        ];

        $status['message'] = $this->getStatusMessage($health);

        return $status;
    }

    /**
     * Validate provider configuration for admin panel.
     */
    public function validateProviderConfiguration(string $provider, array $data): array
    {
        try {
            $requiredFields = $this->getProviderRequiredFields($provider);

            foreach ($requiredFields as $field) {
                if (empty($data[$field] ?? null)) {
                    return [
                        'success' => false,
                        'message' => "Missing required field: {$field}",
                    ];
                }
            }

            switch ($provider) {
                case 'twilio':
                    if (!preg_match('/^whatsapp:\+\d+$/', $data['TWILIO_WHATSAPP_FROM'] ?? '')) {
                        return [
                            'success' => false,
                            'message' => 'Invalid Twilio WhatsApp number format. Must be: whatsapp:+1234567890',
                        ];
                    }
                    break;

                case 'vonage':
                    if (!preg_match('/^\+\d+$/', $data['VONAGE_WHATSAPP_FROM'] ?? '')) {
                        return [
                            'success' => false,
                            'message' => 'Invalid Vonage WhatsApp number format. Must be: +1234567890',
                        ];
                    }
                    break;

                case 'custom':
                case 'wati':
                    $url = $data['WHATSAPP_API_URL'] ?? $data['WATI_API_URL'] ?? '';
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        return [
                            'success' => false,
                            'message' => 'Invalid API URL format',
                        ];
                    }
                    break;

                case 'vumaapi':
                    if (!preg_match('/^whatsapp:\+\d+$/', $data['VUMAAPI_WHATSAPP_FROM'] ?? '')) {
                        return [
                            'success' => false,
                            'message' => 'Invalid VumaAPI WhatsApp Sender ID format. Must be: whatsapp:+233XXXXXXXXX',
                        ];
                    }
                    if (!empty($data['VUMAAPI_API_URL']) && !filter_var($data['VUMAAPI_API_URL'], FILTER_VALIDATE_URL)) {
                        return [
                            'success' => false,
                            'message' => 'Invalid VumaAPI API Base URL format',
                        ];
                    }
                    break;

                case '360dialog':
                    break;
            }

            return [
                'success' => true,
                'message' => 'Configuration validation passed',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get required fields for provider.
     */
    protected function getProviderRequiredFields(string $provider): array
    {
        $fields = [
            'twilio'    => ['TWILIO_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_WHATSAPP_FROM'],
            'vonage'    => ['VONAGE_KEY', 'VONAGE_SECRET', 'VONAGE_WHATSAPP_FROM'],
            'custom'    => ['WHATSAPP_API_URL', 'WHATSAPP_API_KEY'],
            '360dialog' => ['DIALOG_API_KEY', 'DIALOG_PHONE_NUMBER_ID', 'DIALOG_BUSINESS_ID'],
            'wati'      => ['WATI_API_KEY', 'WATI_API_URL'],
            'vumaapi'   => ['VUMAAPI_API_KEY', 'VUMAAPI_WHATSAPP_FROM', 'VUMAAPI_API_URL'],
        ];

        return $fields[$provider] ?? [];
    }

    /**
     * Test connection for a provider.
     */
    public function testConnection(string $provider): array
    {
        $originalProvider = $this->provider;
        $originalConfig = $this->config;

        try {
            $testConfig = $this->createTestConfigFromEnv($provider);

            $this->provider = $provider;
            $this->config = $testConfig;

            $isConfigured = $this->checkConfiguration();

            if (!$isConfigured) {
                return [
                    'success'     => false,
                    'message'     => "{$provider} is not properly configured. Check all required fields.",
                    'provider'    => $provider,
                    'configured'  => false,
                    'error_code'  => 'NOT_CONFIGURED',
                    'timestamp'   => now()->toISOString(),
                ];
            }

            $networkStatus = $this->checkNetworkConnectivity();
            if (!$networkStatus['connected']) {
                return [
                    'success'    => false,
                    'message'    => 'Network connectivity issue: ' . $networkStatus['message'],
                    'provider'   => $provider,
                    'configured' => true,
                    'error_code' => 'NETWORK_ERROR',
                    'timestamp'  => now()->toISOString(),
                ];
            }

            $healthStatus = $this->checkHealth();

            if (in_array($healthStatus, ['healthy', 'degraded'], true)) {
                return [
                    'success'       => true,
                    'message'       => "✅ {$this->getProviderDisplayName($provider)} connection test successful!",
                    'provider'      => $provider,
                    'configured'    => true,
                    'health_status' => $healthStatus,
                    'timestamp'     => now()->toISOString(),
                    'details' => [
                        'network'       => $networkStatus,
                        'configuration' => 'valid',
                    ],
                ];
            }

            return [
                'success'       => false,
                'message'       => "❌ {$this->getProviderDisplayName($provider)} connection test failed. Status: {$healthStatus}",
                'provider'      => $provider,
                'configured'    => true,
                'health_status' => $healthStatus,
                'error_code'    => strtoupper($healthStatus),
                'timestamp'     => now()->toISOString(),
                'details' => [
                    'network'        => $networkStatus,
                    'configuration'  => 'valid',
                    'health_message' => $this->getStatusMessage($healthStatus),
                ],
            ];

        } catch (Exception $e) {
            Log::error("WhatsApp connection test error for {$provider}: " . $e->getMessage());

            return [
                'success'    => false,
                'message'    => '❌ Connection test failed: ' . $e->getMessage(),
                'provider'   => $provider,
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ];

        } finally {
            $this->provider = $originalProvider;
            $this->config = $originalConfig;
        }
    }

    /**
     * Send test message.
     */
    public function sendTestMessage(string $provider, string $phoneNumber, string $message): array
    {
        $originalProvider = $this->provider;
        $originalConfig = $this->config;
        $originalIsConfigured = $this->isConfigured;

        try {
            $testConfig = $this->createTestConfigFromEnv($provider);

            $this->provider = $provider;
            $this->config = $testConfig;
            $this->isConfigured = $this->checkConfiguration();

            $startTime = microtime(true);
            $result = $this->sendMessage($phoneNumber, $message, ['message_type' => 'test']);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['success']) {
                return [
                    'success'           => true,
                    'message'           => "✅ Test message sent successfully via {$this->getProviderDisplayName($provider)}!",
                    'provider'          => $provider,
                    'details' => [
                        'message_id'         => $result['message_id'] ?? 'unknown',
                        'to'                 => $phoneNumber,
                        'execution_time_ms'  => $executionTime,
                        'provider_response'  => $result,
                    ],
                    'execution_time_ms' => $executionTime,
                    'timestamp'         => now()->toISOString(),
                ];
            }

            return [
                'success'           => false,
                'message'           => "❌ Failed to send test message: " . ($result['message'] ?? 'Unknown error'),
                'provider'          => $provider,
                'details' => [
                    'to'                => $phoneNumber,
                    'error'             => $result['message'] ?? 'Unknown error',
                    'error_code'        => $result['error_code'] ?? 'SEND_FAILED',
                    'execution_time_ms' => $executionTime,
                ],
                'error_code'        => $result['error_code'] ?? 'SEND_FAILED',
                'timestamp'         => now()->toISOString(),
                'execution_time_ms' => $executionTime,
            ];

        } catch (Exception $e) {
            Log::error("WhatsApp test message error for {$provider}: " . $e->getMessage());

            return [
                'success'    => false,
                'message'    => '❌ Test message failed: ' . $e->getMessage(),
                'provider'   => $provider,
                'error_code' => 'EXCEPTION',
                'timestamp'  => now()->toISOString(),
            ];

        } finally {
            $this->provider = $originalProvider;
            $this->config = $originalConfig;
            $this->isConfigured = $originalIsConfigured;
        }
    }

    /**
     * Get provider configuration.
     */
    public function getProviderConfig(string $provider): ?array
    {
        try {
            $config = [];

            switch ($provider) {
                case 'twilio':
                    $config = [
                        'provider'              => 'twilio',
                        'TWILIO_SID'            => $this->config['twilio']['sid'],
                        'TWILIO_AUTH_TOKEN'     => $this->config['twilio']['token'],
                        'TWILIO_WHATSAPP_FROM'  => $this->config['twilio']['whatsapp_from'],
                        'is_active'             => $this->isProviderActive('twilio'),
                    ];
                    break;

                case 'vonage':
                    $config = [
                        'provider'               => 'vonage',
                        'VONAGE_KEY'             => $this->config['vonage']['key'],
                        'VONAGE_SECRET'          => $this->config['vonage']['secret'],
                        'VONAGE_WHATSAPP_FROM'   => $this->config['vonage']['whatsapp_from'],
                        'is_active'              => $this->isProviderActive('vonage'),
                    ];
                    break;

                case 'custom':
                    $config = [
                        'provider'         => 'custom',
                        'WHATSAPP_API_URL' => $this->config['custom']['api_url'],
                        'WHATSAPP_API_KEY' => $this->config['custom']['api_key'],
                        'is_active'        => $this->isProviderActive('custom'),
                    ];
                    break;

                case '360dialog':
                    $config = [
                        'provider'                => '360dialog',
                        'DIALOG_API_KEY'          => $this->config['360dialog']['api_key'],
                        'DIALOG_PHONE_NUMBER_ID'  => $this->config['360dialog']['phone_number_id'],
                        'DIALOG_BUSINESS_ID'      => $this->config['360dialog']['business_id'],
                        'is_active'               => $this->isProviderActive('360dialog'),
                    ];
                    break;

                case 'wati':
                    $config = [
                        'provider'     => 'wati',
                        'WATI_API_KEY' => $this->config['wati']['api_key'],
                        'WATI_API_URL' => $this->config['wati']['api_url'],
                        'is_active'    => $this->isProviderActive('wati'),
                    ];
                    break;

                case 'vumaapi':
                    $config = [
                        'provider'                => 'vumaapi',
                        'VUMAAPI_API_KEY'         => $this->config['vumaapi']['api_key'],
                        'VUMAAPI_API_SECRET'      => $this->config['vumaapi']['api_secret'],
                        'VUMAAPI_WHATSAPP_FROM'   => $this->config['vumaapi']['whatsapp_from'],
                        'VUMAAPI_API_URL'         => $this->config['vumaapi']['api_url'],
                        'VUMAAPI_ENVIRONMENT'     => $this->config['vumaapi']['environment'],
                        'is_active'               => $this->isProviderActive('vumaapi'),
                    ];
                    break;
            }

            if (!empty($config)) {
                $config['provider_name'] = $this->getProviderDisplayName($provider);
                $config['timestamp'] = now()->toISOString();
            }

            return $config;

        } catch (Exception $e) {
            Log::error("Failed to get provider configuration for {$provider}: " . $e->getMessage());
            return null;
        }
    }

    /* ============================================================
     | HEALTH CHECKS
     * ============================================================ */

    /**
     * Check service health with network awareness.
     *
     * - Auto-invalidates stale cache when config state changed.
     * - Only returns 'network_error' when general internet is unreachable.
     */
    protected function checkHealth(): string
    {
        // Stale-cache guard: if the cached value disagrees with current config
        // state (e.g. config was just fixed but cache says "not_configured"),
        // bust the cache and re-evaluate.
        $cached = Cache::get('whatsapp_health_check');
        if ($cached === 'not_configured' && $this->isConfigured) {
            Cache::forget('whatsapp_health_check');
        } elseif ($cached !== null && $cached !== 'not_configured' && !$this->isConfigured) {
            Cache::forget('whatsapp_health_check');
        }

        return Cache::remember('whatsapp_health_check', 300, function () {
            try {
                if (!$this->isConfigured) {
                    return 'not_configured';
                }

                $networkStatus = $this->checkNetworkConnectivity();
                if (!$networkStatus['connected']) {
                    Log::warning('WhatsApp health check failed due to network issues: ' . $networkStatus['message']);
                    return 'network_error';
                }

                return match ($this->provider) {
                    'twilio'    => $this->checkTwilioHealth(),
                    'vonage'    => $this->checkVonageHealth(),
                    'custom'    => $this->checkCustomHealth(),
                    '360dialog' => $this->check360DialogHealth(),
                    'wati'      => $this->checkWatiHealth(),
                    'vumaapi'   => $this->checkVumaApiHealth(),
                    default     => 'unknown',
                };

            } catch (Exception $e) {
                Log::error('WhatsApp health check failed: ' . $e->getMessage());
                return 'unhealthy';
            }
        });
    }

    /**
     * Check VumaAPI service health.
     *
     * VumaAPI does not expose a documented public health endpoint.
     * We probe a few likely paths; if none respond but credentials are
     * present and the host is reachable, we return 'degraded' rather
     * than 'network_error' — the provider is usable, we just can't
     * pre-flight it.
     */
    protected function checkVumaApiHealth(): string
    {
        try {
            $vumaConfig = $this->config['vumaapi'];

            if (empty($vumaConfig['api_key'])) {
                return 'misconfigured';
            }

            $baseUrl = rtrim($vumaConfig['api_url'] ?: 'https://api.vumacloud.com', '/');

            $headers = [
                'Authorization' => 'Bearer ' . $vumaConfig['api_key'],
                'X-API-KEY'     => $vumaConfig['api_key'],
                'Accept'        => 'application/json',
            ];

            if (!empty($vumaConfig['api_secret'])) {
                $headers['X-API-SECRET'] = $vumaConfig['api_secret'];
            }

            // Probe a few likely health/account endpoints. If any returns 2xx,
            // we're healthy. If any returns 401/403, credentials are wrong.
            // If everything 404s or times out, we still consider the provider
            // usable (degraded) — sending will reveal any real problems.
            $candidates = ['/api/v1/health', '/health', '/api/v1/account', '/api/v1/balance'];

            $sawReachableHost = false;

            foreach ($candidates as $path) {
                try {
                    $response = Http::withHeaders($headers)
                        ->timeout(8)
                        ->get($baseUrl . $path);

                    $sawReachableHost = true;

                    if ($response->successful()) {
                        return 'healthy';
                    }

                    if (in_array($response->status(), [401, 403], true)) {
                        return 'invalid_credentials';
                    }

                    // 404 or other status → try the next candidate
                    Log::debug("VumaAPI health probe {$path} returned status: " . $response->status());

                } catch (\Throwable $e) {
                    // DNS failure on this host is a real signal, but we want to
                    // try all candidates before concluding the host is unreachable.
                    Log::debug("VumaAPI health probe {$path} failed: " . $e->getMessage());
                    continue;
                }
            }

            if ($sawReachableHost) {
                // Host responded to at least one probe but no endpoint was usable.
                // Provider is still usable — we just can't pre-flight it.
                return 'degraded';
            }

            // Nothing responded at all — this is a real connectivity problem.
            Log::warning('VumaAPI health check: no probe endpoints reachable');
            return 'network_error';

        } catch (Exception $e) {
            Log::error('VumaAPI health check failed: ' . $e->getMessage());
            return 'degraded';
        }
    }

    /**
     * Check 360Dialog service health.
     */
    protected function check360DialogHealth(): string
    {
        try {
            $dialogConfig = $this->config['360dialog'];

            if (empty($dialogConfig['api_key'])) {
                return 'misconfigured';
            }

            $response = Http::withHeaders([
                'D360-API-KEY' => $dialogConfig['api_key'],
            ])->timeout(10)
              ->get('https://waba.360dialog.io/v1/health');

            if ($response->successful()) {
                return 'healthy';
            }

            Log::warning('360Dialog health check returned status: ' . $response->status());
            return 'degraded';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('360Dialog health check failed: ' . $errorMessage);

            if (str_contains($errorMessage, 'Could not resolve host') || str_contains($errorMessage, 'cURL error 6')) {
                return 'network_error';
            }
            if (str_contains($errorMessage, 'Authentication') || str_contains($errorMessage, '401')) {
                return 'invalid_credentials';
            }

            return 'unhealthy';
        }
    }

    /**
     * Check WATI service health.
     */
    protected function checkWatiHealth(): string
    {
        try {
            $watiConfig = $this->config['wati'];

            if (empty($watiConfig['api_key']) || empty($watiConfig['api_url'])) {
                return 'misconfigured';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $watiConfig['api_key'],
            ])->timeout(10)
              ->get(rtrim($watiConfig['api_url'], '/') . '/api/v1/getProfileInfo');

            if ($response->successful()) {
                return 'healthy';
            }

            Log::warning('WATI health check returned status: ' . $response->status());
            return 'degraded';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('WATI health check failed: ' . $errorMessage);

            if (str_contains($errorMessage, 'Could not resolve host') || str_contains($errorMessage, 'cURL error 6')) {
                return 'network_error';
            }
            if (str_contains($errorMessage, 'Authentication') || str_contains($errorMessage, '401')) {
                return 'invalid_credentials';
            }

            return 'unhealthy';
        }
    }

    /**
     * Check Twilio service health.
     */
    protected function checkTwilioHealth(): string
    {
        try {
            $twilioConfig = $this->config['twilio'];

            if (empty($twilioConfig['sid']) || empty($twilioConfig['token'])) {
                return 'misconfigured';
            }

            $twilio = new TwilioClient($twilioConfig['sid'], $twilioConfig['token']);

            $account = $twilio->api->v2010->accounts($twilioConfig['sid'])->fetch();

            if ($account->status !== 'active') {
                Log::warning('Twilio account status: ' . $account->status);
                return 'degraded';
            }

            return 'healthy';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Twilio health check failed: ' . $errorMessage);

            if (str_contains($errorMessage, 'Could not resolve host')) {
                return 'network_error';
            }
            if (str_contains($errorMessage, 'Authentication Error') || str_contains($errorMessage, '401')) {
                return 'invalid_credentials';
            }
            if (str_contains($errorMessage, 'timeout')) {
                return 'timeout';
            }

            return 'unhealthy';
        }
    }

    /**
     * Check Vonage service health.
     */
    protected function checkVonageHealth(): string
    {
        try {
            $vonageConfig = $this->config['vonage'];

            if (empty($vonageConfig['key']) || empty($vonageConfig['secret'])) {
                return 'misconfigured';
            }

            $basic = new VonageBasic($vonageConfig['key'], $vonageConfig['secret']);
            $client = new VonageClient($basic);
            $balance = $client->getBalance();

            if ($balance < 1.00) {
                Log::warning('Vonage balance low: ' . $balance);
                return 'degraded';
            }

            return 'healthy';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Vonage health check failed: ' . $errorMessage);

            if (str_contains($errorMessage, 'Could not resolve host')) {
                return 'network_error';
            }
            if (str_contains($errorMessage, 'authentication') || str_contains($errorMessage, '401')) {
                return 'invalid_credentials';
            }

            return 'unhealthy';
        }
    }

    /**
     * Check Custom API health.
     */
    protected function checkCustomHealth(): string
    {
        try {
            $customConfig = $this->config['custom'];

            if (empty($customConfig['api_key']) || empty($customConfig['api_url'])) {
                return 'misconfigured';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $customConfig['api_key'],
            ])->timeout(10)
              ->get(rtrim($customConfig['api_url'], '/') . '/health');

            if ($response->successful()) {
                $data = $response->json();
                return $data['status'] ?? 'healthy';
            }

            Log::warning('Custom API health check returned status: ' . $response->status());
            return 'degraded';

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Custom API health check failed: ' . $errorMessage);

            if (str_contains($errorMessage, 'Could not resolve host') || str_contains($errorMessage, 'cURL error 6')) {
                return 'network_error';
            }
            if (str_contains($errorMessage, 'timeout')) {
                return 'timeout';
            }

            return 'unhealthy';
        }
    }

    /**
     * Check network connectivity to external APIs.
     */
    public function checkNetworkConnectivity(): array
    {
        $cacheKey = 'whatsapp_network_status';

        return Cache::remember($cacheKey, 300, function () {
            $testUrls = [
                'twilio'    => 'https://api.twilio.com',
                'vonage'    => 'https://api.nexmo.com',
                'google'    => 'https://8.8.8.8',
            ];

            // Also probe the currently active provider's host, if it's not
            // already covered above. This is best-effort — a failure here
            // does not make the whole network "down".
            $activeHost = null;
            if ($this->provider === 'vumaapi' && !empty($this->config['vumaapi']['api_url'])) {
                $activeHost = $this->config['vumaapi']['api_url'];
            } elseif ($this->provider === 'wati' && !empty($this->config['wati']['api_url'])) {
                $activeHost = $this->config['wati']['api_url'];
            } elseif ($this->provider === 'custom' && !empty($this->config['custom']['api_url'])) {
                $activeHost = $this->config['custom']['api_url'];
            } elseif ($this->provider === '360dialog') {
                $activeHost = 'https://waba.360dialog.io';
            }

            foreach ($testUrls as $service => $url) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders(['User-Agent' => 'PropertyManagementSystem/1.0'])
                        ->get($url);

                    if ($response->successful() || in_array($response->status(), [401, 404], true)) {
                        return [
                            'connected'      => true,
                            'message'        => 'Network connectivity confirmed',
                            'tested_service' => $service,
                            'response_time'  => $response->transferStats ? $response->transferStats->getTransferTime() : null,
                        ];
                    }
                } catch (Exception $e) {
                    Log::warning("Network connectivity test failed for {$service}: " . $e->getMessage());
                    continue;
                }
            }

            if ($this->testDnsResolution()) {
                return [
                    'connected'      => true,
                    'message'        => 'DNS resolution working but API endpoints may be blocked',
                    'tested_service' => 'dns_only',
                ];
            }

            return [
                'connected'      => false,
                'message'        => 'Cannot reach external APIs - check network connectivity and DNS',
                'tested_service' => 'none',
            ];
        });
    }

    /**
     * Test basic DNS resolution.
     */
    protected function testDnsResolution(): bool
    {
        try {
            return gethostbyname('api.twilio.com') !== 'api.twilio.com'
                || gethostbyname('google.com') !== 'google.com';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get detailed configuration status.
     */
    public function getConfigurationStatus(): array
    {
        $status = [
            'provider'   => $this->provider,
            'configured' => $this->isConfigured,
            'issues'     => [],
        ];

        if (!$this->isConfigured) {
            switch ($this->provider) {
                case 'twilio':
                    if (empty($this->config['twilio']['sid']))           $status['issues'][] = 'Twilio SID missing';
                    if (empty($this->config['twilio']['token']))         $status['issues'][] = 'Twilio Token missing';
                    if (empty($this->config['twilio']['whatsapp_from'])) $status['issues'][] = 'Twilio From number missing';
                    break;

                case 'vonage':
                    if (empty($this->config['vonage']['key']))           $status['issues'][] = 'Vonage API Key missing';
                    if (empty($this->config['vonage']['secret']))        $status['issues'][] = 'Vonage API Secret missing';
                    if (empty($this->config['vonage']['whatsapp_from'])) $status['issues'][] = 'Vonage From number missing';
                    break;

                case 'custom':
                    if (empty($this->config['custom']['api_key'])) $status['issues'][] = 'Custom API Key missing';
                    if (empty($this->config['custom']['api_url'])) $status['issues'][] = 'Custom API URL missing';
                    break;

                case '360dialog':
                    if (empty($this->config['360dialog']['api_key']))         $status['issues'][] = '360Dialog API Key missing';
                    if (empty($this->config['360dialog']['phone_number_id'])) $status['issues'][] = '360Dialog Phone Number ID missing';
                    if (empty($this->config['360dialog']['business_id']))     $status['issues'][] = '360Dialog Business ID missing';
                    break;

                case 'wati':
                    if (empty($this->config['wati']['api_key'])) $status['issues'][] = 'WATI API Key missing';
                    if (empty($this->config['wati']['api_url'])) $status['issues'][] = 'WATI API URL missing';
                    break;

                case 'vumaapi':
                    if (empty($this->config['vumaapi']['api_key']))       $status['issues'][] = 'VumaAPI API Key missing';
                    if (empty($this->config['vumaapi']['whatsapp_from'])) $status['issues'][] = 'VumaAPI WhatsApp Sender ID missing';
                    if (empty($this->config['vumaapi']['api_url']))       $status['issues'][] = 'VumaAPI API Base URL missing';
                    break;

                default:
                    $status['issues'][] = 'No WhatsApp provider selected';
            }
        }

        return $status;
    }

    /**
     * Get status message based on health.
     */
    protected function getStatusMessage(string $health): string
    {
        return match ($health) {
            'healthy'             => 'WhatsApp service is operating normally',
            'degraded'            => 'WhatsApp service is experiencing minor issues',
            'unhealthy'           => 'WhatsApp service is unavailable',
            'network_error'       => 'Network connectivity issue - cannot reach WhatsApp provider',
            'invalid_credentials' => 'Authentication failed - check API credentials',
            'timeout'             => 'Service timeout - provider is responding slowly',
            'misconfigured'       => 'Service configuration is incomplete',
            'not_configured'      => 'WhatsApp service is not configured',
            default               => 'WhatsApp service status unknown',
        };
    }

    /**
     * Check if provider supports media messages.
     */
    protected function supportsMedia(): bool
    {
        return in_array($this->provider, ['twilio', 'vonage', 'custom', '360dialog', 'wati', 'vumaapi'], true);
    }

    /**
     * Check if provider supports templates.
     */
    protected function supportsTemplates(): bool
    {
        return in_array($this->provider, ['twilio', 'vonage', '360dialog', 'wati'], true);
    }

    protected function getRateLimit(): array
    {
        return [
            'max_requests_per_minute' => config('whatsapp.rate_limit', 60),
            'remaining'               => null,
            'reset_at'                => null,
        ];
    }

    protected function getDailyLimit(): array
    {
        return [
            'max_messages_per_day' => config('whatsapp.daily_limit', 1000),
            'used_today'           => $this->getMessagesSentToday(),
            'remaining'            => null,
        ];
    }

    protected function getStatistics(): array
    {
        return Cache::remember('whatsapp_statistics', 3600, function () {
            return [
                'total_messages_sent'    => 0,
                'successful_messages'    => 0,
                'failed_messages'        => 0,
                'success_rate'           => 0,
                'last_message_sent'      => null,
                'average_response_time'  => null,
            ];
        });
    }

    protected function getMessagesSentToday(): int
    {
        return 0;
    }

    /* ============================================================
     | SENDING
     * ============================================================ */

    /**
     * Send WhatsApp message.
     */
    public function sendMessage($to, $message, $options = []): array
    {
        $this->lastError = null;

        try {
            $validatedNumber = $this->validatePhoneNumber($to);
            if (!$validatedNumber) {
                throw new Exception("Invalid phone number format: {$to}");
            }

            if (!$this->isConfigured) {
                Log::warning("WhatsApp service not configured, would send to: {$validatedNumber}", [
                    'message_length' => strlen($message),
                    'options'        => $options,
                ]);

                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent successfully (simulated - service not configured)',
                    'message_id' => 'simulated-' . uniqid(),
                    'provider'   => 'simulated',
                    'to'         => $validatedNumber,
                ];
            }

            $networkStatus = $this->checkNetworkConnectivity();
            if (!$networkStatus['connected']) {
                throw new Exception('Network connectivity issue: ' . $networkStatus['message']);
            }

            $messageType = $options['message_type'] ?? 'text';
            $template = $options['template'] ?? null;

            $messageData = $this->prepareMessageData($validatedNumber, $message, $messageType, $template, $options);

            $result = $this->sendViaProvider($messageData);

            Log::info("WhatsApp message sent successfully", [
                'to'           => $validatedNumber,
                'message_type' => $messageType,
                'message_id'   => $result['message_id'] ?? 'unknown',
                'provider'     => $result['provider'],
            ]);

            return $result;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            Log::error("WhatsApp message failed: " . $e->getMessage(), [
                'to'      => $to,
                'error'   => $e->getMessage(),
                'options' => $options,
            ]);

            return [
                'success'          => false,
                'message'          => 'WhatsApp sending failed: ' . $e->getMessage(),
                'error_code'       => $this->getErrorCode($e->getMessage()),
                'to'               => $to,
                'suggested_action' => $this->getSuggestedAction($e->getMessage()),
            ];
        }
    }

    protected function getErrorCode(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'Could not resolve host')) {
            return 'NETWORK_DNS_ERROR';
        }
        if (str_contains($errorMessage, 'Authentication Error') || str_contains($errorMessage, '401')) {
            return 'AUTHENTICATION_FAILED';
        }
        if (str_contains($errorMessage, 'timeout')) {
            return 'REQUEST_TIMEOUT';
        }
        if (str_contains($errorMessage, 'Invalid phone number')) {
            return 'INVALID_PHONE_NUMBER';
        }
        return 'SEND_FAILED';
    }

    protected function getSuggestedAction(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'Could not resolve host')) {
            return 'Check your internet connection and DNS settings';
        }
        if (str_contains($errorMessage, 'Authentication Error')) {
            return 'Verify your API credentials in system settings';
        }
        if (str_contains($errorMessage, 'timeout')) {
            return 'The service is responding slowly. Try again in a few moments';
        }
        return 'Check the system logs for more details';
    }

    protected function validatePhoneNumber(string $phone): ?string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        if (preg_match('/^\+?[1-9]\d{1,14}$/', $cleaned)) {
            if (strpos($cleaned, '+') !== 0) {
                if (strlen($cleaned) <= 10) {
                    $cleaned = '+233' . ltrim($cleaned, '0');
                } else {
                    $cleaned = '+' . $cleaned;
                }
            }
            return $cleaned;
        }

        return null;
    }

    protected function prepareMessageData(string $to, string $message, string $type, ?string $template, array $options): array
    {
        $baseData = [
            'to'      => $to,
            'message' => $message,
            'type'    => $type,
        ];

        switch ($type) {
            case 'template':
                $baseData['template_name'] = $template;
                $baseData['template_parameters'] = $options['parameters'] ?? [];
                break;

            case 'media':
                $baseData['media_url'] = $options['media_url'] ?? null;
                $baseData['caption'] = $options['caption'] ?? $message;
                break;

            case 'interactive':
                $baseData['buttons'] = $options['buttons'] ?? [];
                $baseData['header'] = $options['header'] ?? null;
                break;

            case 'text':
            default:
                break;
        }

        return array_merge($baseData, $options);
    }

    protected function sendViaProvider(array $messageData): array
    {
        switch ($this->provider) {
            case 'twilio':    return $this->sendViaTwilio($messageData);
            case 'vonage':    return $this->sendViaVonage($messageData);
            case 'custom':    return $this->sendViaCustom($messageData);
            case '360dialog': return $this->sendVia360Dialog($messageData);
            case 'wati':      return $this->sendViaWati($messageData);
            case 'vumaapi':   return $this->sendViaVumaApi($messageData);

            default:
                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent successfully (simulated)',
                    'message_id' => 'simulated-' . uniqid(),
                    'provider'   => 'simulated',
                ];
        }
    }

    /**
     * Send via VumaAPI (Ghana).
     */
    protected function sendViaVumaApi(array $messageData): array
    {
        $vumaConfig = $this->config['vumaapi'];

        if (empty($vumaConfig['api_key']) || empty($vumaConfig['whatsapp_from'])) {
            throw new Exception('VumaAPI WhatsApp not properly configured');
        }

        try {
            $baseUrl = rtrim($vumaConfig['api_url'] ?: 'https://api.vumacloud.com', '/');
            $url = $baseUrl . '/api/v1/whatsapp/send';

            // Strip the "whatsapp:" prefix from the recipient if present.
            $to = preg_replace('/^whatsapp:/i', '', $messageData['to']);

            $payload = [
                'from' => $vumaConfig['whatsapp_from'],
                'to'   => $to,
                'type' => 'text',
                'text' => [
                    'body' => $messageData['message'],
                ],
            ];

            if ($messageData['type'] === 'media' && !empty($messageData['media_url'])) {
                $payload['type'] = 'media';
                $payload['media'] = [
                    'url'     => $messageData['media_url'],
                    'caption' => $messageData['caption'] ?? '',
                ];
            }

            $headers = [
                'Authorization' => 'Bearer ' . $vumaConfig['api_key'],
                'X-API-KEY'     => $vumaConfig['api_key'],
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ];

            if (!empty($vumaConfig['api_secret'])) {
                $headers['X-API-SECRET'] = $vumaConfig['api_secret'];
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent via VumaAPI',
                    'message_id' => $responseData['message_id']
                        ?? $responseData['id']
                        ?? 'vuma-' . uniqid(),
                    'provider'   => 'vumaapi',
                    'to'         => $messageData['to'],
                    'status'     => $responseData['status'] ?? 'sent',
                ];
            }

            throw new Exception('VumaAPI returned error: ' . $response->body());

        } catch (Exception $e) {
            Log::error('VumaAPI WhatsApp send failed: ' . $e->getMessage());
            throw new Exception('VumaAPI error: ' . $e->getMessage());
        }
    }

    protected function sendVia360Dialog(array $messageData): array
    {
        $dialogConfig = $this->config['360dialog'];

        if (empty($dialogConfig['api_key']) || empty($dialogConfig['phone_number_id'])) {
            throw new Exception('360Dialog WhatsApp not properly configured');
        }

        try {
            $url = "https://waba.360dialog.io/v1/messages";

            $payload = [
                'to'   => $messageData['to'],
                'type' => 'text',
                'text' => ['body' => $messageData['message']],
            ];

            if ($messageData['type'] === 'media' && !empty($messageData['media_url'])) {
                $urlParts = parse_url($messageData['media_url']);
                $extension = pathinfo($urlParts['path'], PATHINFO_EXTENSION);

                $mediaTypes = [
                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                    'mp3' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio',
                    'mp4' => 'video', 'mov' => 'video', 'avi' => 'video',
                    'pdf' => 'document', 'doc' => 'document', 'docx' => 'document',
                ];

                $mediaType = $mediaTypes[strtolower($extension)] ?? 'document';

                $payload['type'] = $mediaType;
                $payload[$mediaType] = ['link' => $messageData['media_url']];

                if ($mediaType === 'image' && !empty($messageData['caption'])) {
                    $payload[$mediaType]['caption'] = $messageData['caption'];
                }
            }

            $response = Http::withHeaders([
                'D360-API-KEY' => $dialogConfig['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(30)
              ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent via 360Dialog',
                    'message_id' => $responseData['messages'][0]['id'] ?? '360d-' . uniqid(),
                    'provider'   => '360dialog',
                    'to'         => $messageData['to'],
                    'status'     => 'sent',
                ];
            }

            throw new Exception('360Dialog API returned error: ' . $response->body());

        } catch (Exception $e) {
            Log::error('360Dialog WhatsApp send failed: ' . $e->getMessage());
            throw new Exception('360Dialog API error: ' . $e->getMessage());
        }
    }

    protected function sendViaWati(array $messageData): array
    {
        $watiConfig = $this->config['wati'];

        if (empty($watiConfig['api_key']) || empty($watiConfig['api_url'])) {
            throw new Exception('WATI WhatsApp not properly configured');
        }

        try {
            $url = rtrim($watiConfig['api_url'], '/') . '/api/v1/sendSessionMessage';

            $payload = [
                'phone' => $messageData['to'],
                'text'  => $messageData['message'],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $watiConfig['api_key'],
                'Content-Type'  => 'application/json',
            ])->timeout(30)
              ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent via WATI',
                    'message_id' => $responseData['id'] ?? 'wati-' . uniqid(),
                    'provider'   => 'wati',
                    'to'         => $messageData['to'],
                    'status'     => $responseData['status'] ?? 'sent',
                ];
            }

            throw new Exception('WATI API returned error: ' . $response->body());

        } catch (Exception $e) {
            Log::error('WATI WhatsApp send failed: ' . $e->getMessage());
            throw new Exception('WATI API error: ' . $e->getMessage());
        }
    }

    protected function sendViaTwilio(array $messageData): array
    {
        $twilioConfig = $this->config['twilio'];

        if (empty($twilioConfig['sid']) || empty($twilioConfig['token']) || empty($twilioConfig['whatsapp_from'])) {
            throw new Exception('Twilio WhatsApp not properly configured');
        }

        try {
            $twilio = new TwilioClient($twilioConfig['sid'], $twilioConfig['token']);

            $from = 'whatsapp:' . $twilioConfig['whatsapp_from'];
            $to = 'whatsapp:' . $messageData['to'];

            $messageOptions = [
                'from' => $from,
                'body' => $messageData['message'],
            ];

            if ($messageData['type'] === 'media' && !empty($messageData['media_url'])) {
                $messageOptions['mediaUrl'] = [$messageData['media_url']];
                if (!empty($messageData['caption'])) {
                    $messageOptions['body'] = $messageData['caption'];
                }
            }

            $message = $twilio->messages->create($to, $messageOptions);

            return [
                'success'    => true,
                'message'    => 'WhatsApp message sent via Twilio',
                'message_id' => $message->sid,
                'provider'   => 'twilio',
                'to'         => $messageData['to'],
                'status'     => $message->status,
            ];

        } catch (Exception $e) {
            Log::error('Twilio WhatsApp send failed: ' . $e->getMessage());
            throw new Exception('Twilio API error: ' . $e->getMessage());
        }
    }

    protected function sendViaVonage(array $messageData): array
    {
        $vonageConfig = $this->config['vonage'];

        if (empty($vonageConfig['key']) || empty($vonageConfig['secret']) || empty($vonageConfig['whatsapp_from'])) {
            throw new Exception('Vonage WhatsApp not properly configured');
        }

        try {
            $basic = new VonageBasic($vonageConfig['key'], $vonageConfig['secret']);
            $client = new VonageClient($basic);

            $messagePayload = [
                'to'      => $messageData['to'],
                'from'    => $vonageConfig['whatsapp_from'],
                'text'    => $messageData['message'],
                'channel' => 'whatsapp',
            ];

            if ($messageData['type'] === 'media' && !empty($messageData['media_url'])) {
                $messagePayload['message_type'] = 'image';
                $messagePayload['image'] = ['url' => $messageData['media_url']];
                if (!empty($messageData['caption'])) {
                    $messagePayload['image']['caption'] = $messageData['caption'];
                }
            }

            $response = $client->message()->send($messagePayload);

            return [
                'success'    => true,
                'message'    => 'WhatsApp message sent via Vonage',
                'message_id' => $response->getMessageId(),
                'provider'   => 'vonage',
                'to'         => $messageData['to'],
                'status'     => $response->getStatus(),
            ];

        } catch (Exception $e) {
            Log::error('Vonage WhatsApp send failed: ' . $e->getMessage());
            throw new Exception('Vonage API error: ' . $e->getMessage());
        }
    }

    protected function sendViaCustom(array $messageData): array
    {
        $customConfig = $this->config['custom'];

        if (empty($customConfig['api_key']) || empty($customConfig['api_url'])) {
            throw new Exception('Custom WhatsApp API not properly configured');
        }

        try {
            $payload = [
                'to'      => $messageData['to'],
                'message' => $messageData['message'],
                'type'    => $messageData['type'],
            ];

            if ($messageData['type'] === 'media' && !empty($messageData['media_url'])) {
                $payload['media_url'] = $messageData['media_url'];
                $payload['caption'] = $messageData['caption'] ?? '';
            }

            if ($messageData['type'] === 'template' && !empty($messageData['template_name'])) {
                $payload['template_name'] = $messageData['template_name'];
                $payload['template_parameters'] = $messageData['template_parameters'] ?? [];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $customConfig['api_key'],
                'Content-Type'  => 'application/json',
            ])->timeout(30)
              ->post($customConfig['api_url'], $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success'    => true,
                    'message'    => 'WhatsApp message sent via custom API',
                    'message_id' => $responseData['id'] ?? 'custom-' . uniqid(),
                    'provider'   => 'custom',
                    'to'         => $messageData['to'],
                    'status'     => $responseData['status'] ?? 'sent',
                ];
            }

            throw new Exception('Custom API returned error: ' . $response->body());

        } catch (Exception $e) {
            Log::error('Custom WhatsApp API send failed: ' . $e->getMessage());
            throw new Exception('Custom API error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | TEST / DIAGNOSTIC METHODS
     * ============================================================ */

    /**
     * Test service connectivity with comprehensive diagnostics.
     * (Nullable type fixed for PHP 8.4+.)
     */
    public function testConnectionAdvanced(?array $testConfig = null): array
    {
        try {
            $configToUse = $testConfig ? $this->createTestConfiguration($testConfig) : $this->config;
            $testNumber = config('whatsapp.test_number', '+233000000000');

            if (!$testNumber) {
                return [
                    'success'     => false,
                    'message'     => 'No test number configured',
                    'configured'  => $this->isConfigured,
                    'diagnostics' => $this->runDiagnostics($configToUse),
                ];
            }

            $diagnostics = $this->runDiagnostics($configToUse);

            if (!$diagnostics['network']['connected']) {
                return [
                    'success'     => false,
                    'message'     => 'Network connectivity issue: ' . $diagnostics['network']['message'],
                    'configured'  => $this->isConfigured,
                    'diagnostics' => $diagnostics,
                ];
            }

            $testResult = $this->sendTestMessageInternal($testNumber, $configToUse);

            return [
                'success'            => $testResult['success'],
                'message'            => $testResult['message'],
                'message_id'         => $testResult['message_id'] ?? null,
                'configured'         => $this->isConfigured,
                'provider'           => $configToUse['provider'],
                'test_configuration' => $testConfig ? 'custom' : 'current',
                'diagnostics'        => $diagnostics,
            ];

        } catch (Exception $e) {
            return [
                'success'     => false,
                'message'     => 'Connection test failed: ' . $e->getMessage(),
                'configured'  => $this->isConfigured,
                'error'       => $e->getMessage(),
                'provider'    => $testConfig['provider'] ?? $this->provider,
                'diagnostics' => $this->runDiagnostics($testConfig ?? $this->config),
            ];
        }
    }

    protected function runDiagnostics(array $config): array
    {
        return [
            'network'                => $this->checkNetworkConnectivity(),
            'dns_resolution'         => $this->testDnsResolution(),
            'provider_configuration' => $this->validateProviderConfigurationInternal($config),
            'system_time'            => now()->toISOString(),
            'php_version'            => PHP_VERSION,
            'laravel_version'        => app()->version(),
        ];
    }

    protected function validateProviderConfigurationInternal(array $config): array
    {
        $provider = $config['provider'];

        $validation = [
            'provider' => $provider,
            'valid'    => false,
            'issues'   => [],
        ];

        switch ($provider) {
            case 'twilio':
                if (empty($config['twilio']['sid']))           $validation['issues'][] = 'Twilio SID missing';
                if (empty($config['twilio']['token']))         $validation['issues'][] = 'Twilio Token missing';
                if (empty($config['twilio']['whatsapp_from'])) $validation['issues'][] = 'Twilio From number missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            case 'vonage':
                if (empty($config['vonage']['key']))           $validation['issues'][] = 'Vonage API Key missing';
                if (empty($config['vonage']['secret']))        $validation['issues'][] = 'Vonage API Secret missing';
                if (empty($config['vonage']['whatsapp_from'])) $validation['issues'][] = 'Vonage From number missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            case 'custom':
                if (empty($config['custom']['api_key'])) $validation['issues'][] = 'Custom API Key missing';
                if (empty($config['custom']['api_url'])) $validation['issues'][] = 'Custom API URL missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            case '360dialog':
                if (empty($config['360dialog']['api_key']))         $validation['issues'][] = '360Dialog API Key missing';
                if (empty($config['360dialog']['phone_number_id'])) $validation['issues'][] = '360Dialog Phone Number ID missing';
                if (empty($config['360dialog']['business_id']))     $validation['issues'][] = '360Dialog Business ID missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            case 'wati':
                if (empty($config['wati']['api_key'])) $validation['issues'][] = 'WATI API Key missing';
                if (empty($config['wati']['api_url'])) $validation['issues'][] = 'WATI API URL missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            case 'vumaapi':
                if (empty($config['vumaapi']['api_key']))       $validation['issues'][] = 'VumaAPI API Key missing';
                if (empty($config['vumaapi']['whatsapp_from'])) $validation['issues'][] = 'VumaAPI WhatsApp Sender ID missing';
                if (empty($config['vumaapi']['api_url']))       $validation['issues'][] = 'VumaAPI API Base URL missing';
                $validation['valid'] = empty($validation['issues']);
                break;

            default:
                $validation['issues'][] = 'No valid provider selected';
        }

        return $validation;
    }

    protected function createTestConfiguration(array $testConfig): array
    {
        return [
            'provider' => $testConfig['provider'] ?? 'none',
            'twilio' => [
                'sid'           => $testConfig['twilio_sid'] ?? '',
                'token'         => $testConfig['twilio_token'] ?? '',
                'whatsapp_from' => $testConfig['twilio_whatsapp_from'] ?? '',
            ],
            'vonage' => [
                'key'           => $testConfig['vonage_key'] ?? '',
                'secret'        => $testConfig['vonage_secret'] ?? '',
                'whatsapp_from' => $testConfig['vonage_whatsapp_from'] ?? '',
            ],
            'custom' => [
                'api_key' => $testConfig['whatsapp_api_key'] ?? '',
                'api_url' => $testConfig['whatsapp_api_url'] ?? '',
            ],
            '360dialog' => [
                'api_key'         => $testConfig['dialog_api_key'] ?? '',
                'phone_number_id' => $testConfig['dialog_phone_number_id'] ?? '',
                'business_id'     => $testConfig['dialog_business_id'] ?? '',
            ],
            'wati' => [
                'api_key' => $testConfig['wati_api_key'] ?? '',
                'api_url' => $testConfig['wati_api_url'] ?? '',
            ],
            'vumaapi' => [
                'api_key'       => $testConfig['vumaapi_api_key'] ?? '',
                'api_secret'    => $testConfig['vumaapi_api_secret'] ?? '',
                'whatsapp_from' => $testConfig['vumaapi_whatsapp_from'] ?? '',
                'api_url'       => $testConfig['vumaapi_api_url'] ?? 'https://api.vumacloud.com',
                'environment'   => $testConfig['vumaapi_environment'] ?? 'sandbox',
            ],
            'enabled' => true,
        ];
    }

    protected function createTestConfigFromEnv(string $provider): array
    {
        $config = [
            'provider' => $provider,
            'enabled'  => true,
        ];

        switch ($provider) {
            case 'twilio':
                $config['twilio'] = [
                    'sid'           => $this->config['twilio']['sid'],
                    'token'         => $this->config['twilio']['token'],
                    'whatsapp_from' => $this->config['twilio']['whatsapp_from'],
                ];
                break;

            case 'vonage':
                $config['vonage'] = [
                    'key'           => $this->config['vonage']['key'],
                    'secret'        => $this->config['vonage']['secret'],
                    'whatsapp_from' => $this->config['vonage']['whatsapp_from'],
                ];
                break;

            case 'custom':
                $config['custom'] = [
                    'api_key' => $this->config['custom']['api_key'],
                    'api_url' => $this->config['custom']['api_url'],
                ];
                break;

            case '360dialog':
                $config['360dialog'] = [
                    'api_key'         => $this->config['360dialog']['api_key'],
                    'phone_number_id' => $this->config['360dialog']['phone_number_id'],
                    'business_id'     => $this->config['360dialog']['business_id'],
                ];
                break;

            case 'wati':
                $config['wati'] = [
                    'api_key' => $this->config['wati']['api_key'],
                    'api_url' => $this->config['wati']['api_url'],
                ];
                break;

            case 'vumaapi':
                $config['vumaapi'] = [
                    'api_key'       => $this->config['vumaapi']['api_key'],
                    'api_secret'    => $this->config['vumaapi']['api_secret'],
                    'whatsapp_from' => $this->config['vumaapi']['whatsapp_from'],
                    'api_url'       => $this->config['vumaapi']['api_url'],
                    'environment'   => $this->config['vumaapi']['environment'],
                ];
                break;
        }

        return $config;
    }

    protected function sendTestMessageInternal(string $testNumber, array $config): array
    {
        $provider = $config['provider'];

        if ($provider === 'none') {
            return [
                'success' => false,
                'message' => 'No WhatsApp provider selected for testing',
            ];
        }

        $isConfigured = match ($provider) {
            'twilio'    => !empty($config['twilio']['sid']) && !empty($config['twilio']['token']) && !empty($config['twilio']['whatsapp_from']),
            'vonage'    => !empty($config['vonage']['key']) && !empty($config['vonage']['secret']) && !empty($config['vonage']['whatsapp_from']),
            'custom'    => !empty($config['custom']['api_key']) && !empty($config['custom']['api_url']),
            '360dialog' => !empty($config['360dialog']['api_key']) && !empty($config['360dialog']['phone_number_id']) && !empty($config['360dialog']['business_id']),
            'wati'      => !empty($config['wati']['api_key']) && !empty($config['wati']['api_url']),
            'vumaapi'   => !empty($config['vumaapi']['api_key']) && !empty($config['vumaapi']['whatsapp_from']) && !empty($config['vumaapi']['api_url']),
            default     => false,
        };

        if (!$isConfigured) {
            return [
                'success' => false,
                'message' => 'Test configuration is incomplete for ' . $provider,
            ];
        }

        $testMessage = "🔧 WhatsApp Service Test\n\n" .
                      "This is a test message from your Property Management System.\n\n" .
                      "✅ Configuration: " . $provider . "\n" .
                      "📅 Time: " . now()->format('Y-m-d H:i:s') . "\n" .
                      "Status: CONNECTED";

        $originalConfig = $this->config;
        $originalProvider = $this->provider;
        $originalIsConfigured = $this->isConfigured;

        try {
            $this->config = $config;
            $this->isConfigured = true;
            $this->provider = $provider;

            $result = $this->sendMessage($testNumber, $testMessage, ['message_type' => 'test']);

            return $result;

        } finally {
            $this->config = $originalConfig;
            $this->isConfigured = $originalIsConfigured;
            $this->provider = $originalProvider;
        }
    }

    /* ============================================================
     | MESSAGE TYPE ACCESSORS
     * ============================================================ */

    public function getSupportedMessageTypes(): array
    {
        return [
            'text'        => 'Text Message',
            'template'    => 'Template Message',
            'media'       => 'Media Message',
            'interactive' => 'Interactive Message',
        ];
    }

    /* ============================================================
     | BUSINESS MESSAGE BUILDERS
     * ============================================================ */

    public function sendPaymentReminder($to, $invoice, $settings): array
    {
        $message = $this->buildPaymentReminderMessage($invoice, $settings);

        return $this->sendMessage($to, $message, [
            'message_type' => 'template',
            'template'     => 'payment_reminder',
        ]);
    }

    protected function buildPaymentReminderMessage($invoice, $settings): string
    {
        return "💰 *Payment Reminder*\n\n" .
               "Hello, this is a friendly reminder from *{$settings->system_name}*.\n\n" .
               "📋 *Invoice Details:*\n" .
               "• Invoice #: {$invoice->invoice_number}\n" .
               "• Amount Due: {$settings->formatAmount($invoice->amount_due)}\n" .
               "• Due Date: {$invoice->due_date->format('M j, Y')}\n\n" .
               "💳 *Payment Methods:*\n" .
               "{$settings->getPaymentInstructions()}\n\n" .
               "For questions, contact: {$settings->system_phone}\n" .
               "Thank you! 🏠";
    }

    public function sendInvoiceNotification($to, $invoice, $settings): array
    {
        $message = $this->buildInvoiceNotificationMessage($invoice, $settings);

        return $this->sendMessage($to, $message, [
            'message_type' => 'template',
            'template'     => 'invoice_notification',
        ]);
    }

    protected function buildInvoiceNotificationMessage($invoice, $settings): string
    {
        return "📄 *New Invoice Generated*\n\n" .
               "Hello, a new invoice has been generated by *{$settings->system_name}*.\n\n" .
               "📋 *Invoice Details:*\n" .
               "• Invoice #: {$invoice->invoice_number}\n" .
               "• Amount: {$settings->formatAmount($invoice->total_amount)}\n" .
               "• Due Date: {$invoice->due_date->format('M j, Y')}\n" .
               "• Period: {$invoice->billing_period}\n\n" .
               "💳 *Payment Instructions:*\n" .
               "{$settings->getPaymentInstructions()}\n\n" .
               "Contact: {$settings->system_phone}\n" .
               "Thank you for your prompt payment! 🏠";
    }

    /* ============================================================
     | DEBUG / CACHE
     * ============================================================ */

    public function getCurrentConfiguration(): array
    {
        return [
            'provider'       => $this->provider,
            'configured'     => $this->isConfigured,
            'config'         => $this->config,
            'network_status' => $this->checkNetworkConnectivity(),
        ];
    }

    public function clearCache(): void
    {
        Cache::forget('whatsapp_health_check');
        Cache::forget('whatsapp_network_status');
        Cache::forget('whatsapp_statistics');
    }

    public function getTroubleshootingSuggestions(): array
    {
        $networkStatus = $this->checkNetworkConnectivity();

        $suggestions = [];

        if (!$networkStatus['connected']) {
            $suggestions[] = 'Check your internet connection';
            $suggestions[] = 'Verify DNS settings (try 8.8.8.8 and 8.8.4.4)';
            $suggestions[] = 'Check firewall settings for outgoing HTTPS (port 443)';
            $suggestions[] = 'If behind a proxy, configure HTTP_PROXY and HTTPS_PROXY environment variables';
        }

        if ($this->isConfigured && $this->checkHealth() === 'invalid_credentials') {
            $suggestions[] = 'Verify your API credentials in system settings';
            $suggestions[] = 'Check if your account has sufficient balance/credits';
            $suggestions[] = 'Ensure your WhatsApp number is properly configured in the provider dashboard';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Check the system logs for detailed error information';
            $suggestions[] = 'Verify that the WhatsApp provider service is operational';
            $suggestions[] = 'Test with a different phone number if possible';
        }

        return $suggestions;
    }
}