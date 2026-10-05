<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Support\PaymentProviderRegistry;
use Illuminate\Support\Facades\Cache;

class PaymentConfigurationService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Raw env-level config from PaymentService, cached per request.
     */
    public function envConfiguration(): array
    {
        return Cache::remember(
            'payment_env_configuration',
            now()->addSeconds(30),
            fn () => $this->paymentService->checkPaymentMethodConfiguration()
        );
    }

    /**
     * Whether a provider is enabled at the env level AND configured.
     */
    public function isReady(string $provider): bool
    {
        $config = $this->envConfiguration()[$provider] ?? null;

        return $config
            && ($config['enabled'] ?? false)
            && ($config['configured'] ?? false);
    }

    /**
     * Whether a provider is switched on in SystemSetting.
     */
    public function isEnabledInSettings(string $provider, SystemSetting $settings): bool
    {
        $field = PaymentProviderRegistry::settingField($provider);

        return $field && (bool) ($settings->{$field} ?? false);
    }

    /**
     * Whether a provider is fully usable:
     *   env-enabled, env-configured, AND DB-enabled.
     */
    public function isAvailable(string $provider, SystemSetting $settings): bool
    {
        return $this->isReady($provider)
            && $this->isEnabledInSettings($provider, $settings);
    }

    /**
     * All available providers (ready + settings-enabled), keyed by provider.
     */
    public function availableProviders(SystemSetting $settings): array
    {
        $available = [];

        foreach (PaymentProviderRegistry::keys() as $provider) {
            if ($this->isAvailable($provider, $settings)) {
                $available[$provider] = $this->envConfiguration()[$provider] ?? [];
            }
        }

        return $available;
    }

    /**
     * Detailed status for a single provider (for dashboards).
     */
    public function statusFor(string $provider, SystemSetting $settings): array
    {
        $envConfig = $this->envConfiguration()[$provider] ?? null;

        return [
            'name'        => PaymentProviderRegistry::name($provider),
            'icon'        => PaymentProviderRegistry::icon($provider),
            'color'       => PaymentProviderRegistry::color($provider),
            'description' => PaymentProviderRegistry::description($provider),
            'enabled'     => $this->isEnabledInSettings($provider, $settings),
            'configured'  => (bool) ($envConfig['configured'] ?? false),
            'available'   => $this->isAvailable($provider, $settings),
        ];
    }

    /**
     * All provider statuses keyed by provider.
     */
    public function allStatuses(SystemSetting $settings): array
    {
        $statuses = [];

        foreach (PaymentProviderRegistry::keys() as $provider) {
            $statuses[$provider] = $this->statusFor($provider, $settings);
        }

        return $statuses;
    }

    /**
     * Forget the per-request cache — call after toggling a gateway.
     */
    public function flush(): void
    {
        Cache::forget('payment_env_configuration');
    }
}