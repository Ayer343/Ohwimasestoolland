<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TogglePaymentGatewayRequest;
use App\Models\SystemSetting;
use App\Services\PaymentConfigurationService;
use App\Support\PaymentProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentConfigController extends Controller
{
    public function __construct(
        protected PaymentConfigurationService $config
    ) {}

    /**
     * Aggregate payment configuration for the checkout page.
     */
    public function getPaymentConfiguration(): JsonResponse
    {
        try {
            $settings = SystemSetting::getSettings();

            return $this->ok([
                'payment_configuration' => [
                    'available_providers' => $this->config->availableProviders($settings),
                    'enabled_gateways'    => $settings->getEnabledGatewayNames(),
                    'gateway_count'       => $settings->getEnabledPaymentProvidersCount(),
                ],
                'currency'              => $settings->getCurrencyInfo(),
                'bulk_payment_enabled'  => $settings->enable_bulk_payments,
                'max_bulk_months'       => $settings->max_bulk_months,
                'system_logo'           => $settings->getLogoUrl(),
                'system_short_name'     => $settings->getSystemShortName(),
                'payment_methods'       => $settings->getPaymentMethodsForFrontend(),
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Error retrieving payment configuration', $e);
        }
    }

    /**
     * Rich payment-method list for the frontend.
     */
    public function getAvailablePaymentMethods(): JsonResponse
    {
        try {
            $settings = SystemSetting::getSettings();
            $envConfig = $this->config->envConfiguration();

            $availableMethods = [];

            foreach (PaymentProviderRegistry::keys() as $provider) {
                if (! $this->config->isAvailable($provider, $settings)) {
                    continue;
                }

                $availableMethods[$provider] = [
                    'name'         => PaymentProviderRegistry::name($provider),
                    'instructions' => $this->config->paymentService->getPaymentInstructions($provider),
                    'icon'         => PaymentProviderRegistry::icon($provider),
                    'color'        => PaymentProviderRegistry::color($provider),
                    'description'  => PaymentProviderRegistry::description($provider),
                    'environment'  => $envConfig[$provider]['environment'] ?? 'sandbox',
                ];
            }

            return $this->ok([
                'available_methods' => $availableMethods,
                'primary_provider'  => $settings->primary_payment_provider ?? null,
                'system_info'       => [
                    'name'       => $settings->system_name,
                    'short_name' => $settings->getSystemShortName(),
                    'logo'       => $settings->getLogoUrl(),
                ],
                'currency'     => $settings->getCurrencyInfo(),
                'dues_amount'  => $settings->getFormattedDuesAmount(),
                'bulk_payment' => [
                    'enabled'    => $settings->enable_bulk_payments,
                    'max_months' => $settings->max_bulk_months,
                    'options'    => $settings->getBulkPaymentOptions(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Error retrieving available payment methods', $e);
        }
    }

    /**
     * Reconcile SystemSetting flags with the actual env-level state.
     * Only ever *reflects* env reality — never fabricates availability.
     */
    public function syncPaymentProviders(): JsonResponse
    {
        try {
            $settings = SystemSetting::getSettings();
            $updated = false;
            $changes = [];

            foreach (PaymentProviderRegistry::keys() as $provider) {
                $field = PaymentProviderRegistry::settingField($provider);

                if (! $field || ! $settings->hasAttribute($field)) {
                    continue;
                }

                $newValue = $this->config->isReady($provider);

                if ((bool) $settings->{$field} !== $newValue) {
                    $changes[$provider] = [
                        'from' => (bool) $settings->{$field},
                        'to'   => $newValue,
                    ];
                    $settings->{$field} = $newValue;
                    $updated = true;
                }
            }

            if ($updated) {
                $settings->updated_by = auth()->id();
                $settings->save();
                $this->config->flush();

                Log::info('Payment providers synchronized', [
                    'user_id' => auth()->id(),
                    'changes' => $changes,
                ]);
            }

            return $this->ok([
                'message'        => $updated
                    ? 'Payment providers synchronized successfully'
                    : 'Payment providers are already synchronized',
                'updated'        => $updated,
                'changes'        => $changes,
                'enabled_gateways' => $settings->getEnabledGatewayNames(),
                'gateway_count'    => $settings->getEnabledPaymentProvidersCount(),
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Failed to sync payment providers', $e);
        }
    }

    /**
     * Toggle a single gateway on/off, guarding against enabling
     * an unconfigured provider or disabling the last active one.
     */
    public function togglePaymentGateway(TogglePaymentGatewayRequest $request): JsonResponse
    {
        try {
            $settings = SystemSetting::getSettings();
            $gateway  = $request->string('gateway')->toString();
            $enabled  = $request->boolean('enabled');

            $field = PaymentProviderRegistry::settingField($gateway);

            if (! $field || ! $settings->hasAttribute($field)) {
                return $this->badRequest("Unknown gateway: {$gateway}");
            }

            $previous = (bool) $settings->{$field};

            // Guard 1: cannot enable an unconfigured gateway.
            if ($enabled && ! $this->config->isReady($gateway)) {
                return $this->unprocessable(
                    "Cannot enable " . PaymentProviderRegistry::name($gateway)
                    . ". The gateway is not properly configured. "
                    . "Please configure it in the Payment Providers section first.",
                    ['needs_configuration' => true, 'gateway' => $gateway]
                );
            }

            // Guard 2: cannot disable the last enabled gateway.
            if (! $enabled && $previous && $this->enabledCount($settings) <= 1) {
                return $this->unprocessable(
                    "Cannot disable " . PaymentProviderRegistry::name($gateway)
                    . ". At least one payment gateway must remain enabled.",
                    ['last_gateway' => true, 'gateway' => $gateway]
                );
            }

            // No-op short circuit.
            if ($previous === $enabled) {
                return $this->ok([
                    'message'          => PaymentProviderRegistry::name($gateway)
                                          . ' is already ' . ($enabled ? 'enabled' : 'disabled'),
                    'gateway'          => $gateway,
                    'enabled'          => $enabled,
                    'enabled_gateways' => $settings->getEnabledGatewayNames(),
                    'gateway_count'    => $settings->getEnabledPaymentProvidersCount(),
                ]);
            }

            $settings->{$field}   = $enabled;
            $settings->updated_by = auth()->id();
            $settings->save();
            $this->config->flush();

            Log::info('Payment gateway toggled', [
                'user_id'  => auth()->id(),
                'gateway'  => $gateway,
                'from'     => $previous,
                'to'       => $enabled,
            ]);

            return $this->ok([
                'message'          => PaymentProviderRegistry::name($gateway)
                                      . ' has been ' . ($enabled ? 'enabled' : 'disabled')
                                      . ' successfully',
                'gateway'          => $gateway,
                'enabled'          => $enabled,
                'enabled_gateways' => $settings->getEnabledGatewayNames(),
                'gateway_count'    => $settings->getEnabledPaymentProvidersCount(),
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Error toggling payment gateway', $e);
        }
    }

    /**
     * Dashboard status for every gateway.
     */
    public function getPaymentGatewayStatuses(): JsonResponse
    {
        try {
            $settings = SystemSetting::getSettings();
            $gateways = $this->config->allStatuses($settings);

            return $this->ok([
                'gateways'             => $gateways,
                'total_enabled'        => $settings->getEnabledPaymentProvidersCount(),
                'total_available'      => collect($gateways)->where('available', true)->count(),
                'has_enabled_gateways' => $settings->hasEnabledPaymentMethods(),
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Error retrieving gateway statuses', $e);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function enabledCount(SystemSetting $settings): int
    {
        return collect(PaymentProviderRegistry::keys())
            ->filter(fn ($p) => $this->config->isEnabledInSettings($p, $settings))
            ->count();
    }

    protected function ok(array $payload): JsonResponse
    {
        return response()->json(['success' => true] + $payload);
    }

    protected function badRequest(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 400);
    }

    protected function unprocessable(string $message, array $extra = []): JsonResponse
    {
        return response()->json(
            ['success' => false, 'message' => $message] + $extra,
            422
        );
    }

    protected function fail(string $context, \Throwable $e): JsonResponse
    {
        Log::error("{$context}: " . $e->getMessage(), [
            'user_id' => auth()->id(),
            'trace'   => $e->getTraceAsString(),
        ]);

        $message = app()->isLocal()
            ? "{$context}: " . $e->getMessage()
            : $context . '.';

        return response()->json(['success' => false, 'message' => $message], 500);
    }
}