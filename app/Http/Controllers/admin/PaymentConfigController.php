<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentConfigController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get payment configuration for payment processing
     */
    public function getPaymentConfiguration()
    {
        try {
            $settings = SystemSetting::getSettings();
            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            
            // Get enabled payment methods from system settings (Updated for new gateways)
            $enabledMethods = [];
            if ($settings->enable_expresspay) $enabledMethods[] = 'expresspay';
            if ($settings->enable_hubtel) $enabledMethods[] = 'hubtel';
            if ($settings->enable_paystack) $enabledMethods[] = 'paystack';
            if ($settings->enable_flutterwave) $enabledMethods[] = 'flutterwave';

            // Filter available providers based on system settings AND provider configuration
            $availableProviders = array_filter($paymentConfiguration, function($status, $provider) use ($enabledMethods) {
                return $status['enabled'] && $status['configured'] && in_array($provider, $enabledMethods);
            }, ARRAY_FILTER_USE_BOTH);

            $paymentInfo = [
                'available_providers' => $availableProviders,
                'enabled_gateways' => $settings->getEnabledGatewayNames(),
                'gateway_count' => $settings->getEnabledPaymentProvidersCount(),
            ];

            return response()->json([
                'success' => true,
                'payment_configuration' => $paymentInfo,
                'currency' => $settings->getCurrencyInfo(),
                'bulk_payment_enabled' => $settings->enable_bulk_payments,
                'max_bulk_months' => $settings->max_bulk_months,
                'system_logo' => $settings->getLogoUrl(),
                'system_short_name' => $settings->getSystemShortName(),
                'payment_methods' => $settings->getPaymentMethodsForFrontend()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available payment methods for frontend (Updated for new gateways)
     */
    public function getAvailablePaymentMethods()
    {
        try {
            $settings = SystemSetting::getSettings();
            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            
            $availableMethods = [];
            
            // Check each provider against system settings (Updated gateway list)
            $providers = [
                'expresspay' => 'enable_expresspay',
                'hubtel' => 'enable_hubtel',
                'paystack' => 'enable_paystack',
                'flutterwave' => 'enable_flutterwave'
            ];
            
            foreach ($providers as $provider => $enabledField) {
                $providerConfig = $paymentConfiguration[$provider] ?? null;
                $isEnabledInSettings = $settings->$enabledField ?? false;
                
                // Provider must be BOTH enabled in system settings AND properly configured
                if ($providerConfig && $providerConfig['enabled'] && $providerConfig['configured'] && $isEnabledInSettings) {
                    $availableMethods[$provider] = [
                        'name' => $this->getProviderDisplayName($provider),
                        'instructions' => $this->paymentService->getPaymentInstructions($provider),
                        'icon' => $this->getProviderIcon($provider),
                        'color' => $this->getProviderColor($provider),
                        'description' => $this->getProviderDescription($provider)
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'available_methods' => $availableMethods,
                'primary_provider' => $settings->primary_payment_provider ?? null,
                'system_info' => [
                    'name' => $settings->system_name,
                    'short_name' => $settings->getSystemShortName(),
                    'logo' => $settings->getLogoUrl()
                ],
                'currency' => $settings->getCurrencyInfo(),
                'dues_amount' => $settings->getFormattedDuesAmount(),
                'bulk_payment' => [
                    'enabled' => $settings->enable_bulk_payments,
                    'max_months' => $settings->max_bulk_months,
                    'options' => $settings->getBulkPaymentOptions()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync payment providers with system settings (Updated for new gateways)
     */
    public function syncPaymentProviders()
    {
        try {
            $settings = SystemSetting::getSettings();
            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            
            $updated = false;
            
            // Sync enabled status based on provider configuration (Updated gateway list)
            $providers = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            
            foreach ($providers as $provider) {
                $status = $paymentConfiguration[$provider] ?? null;
                $enabledField = 'enable_' . $provider;
                
                if ($status && property_exists($settings, $enabledField)) {
                    // Only enable if provider is properly configured
                    $newValue = $status['enabled'] && $status['configured'];
                    
                    if ($settings->$enabledField != $newValue) {
                        $settings->$enabledField = $newValue;
                        $updated = true;
                    }
                }
            }
            
            if ($updated) {
                $settings->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment providers synchronized successfully',
                    'updated_settings' => [
                        'enabled_gateways' => $settings->getEnabledGatewayNames(),
                        'gateway_count' => $settings->getEnabledPaymentProvidersCount()
                    ]
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Payment providers are already synchronized',
                'current_gateways' => $settings->getEnabledGatewayNames(),
                'gateway_count' => $settings->getEnabledPaymentProvidersCount()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync payment providers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle a specific payment gateway (New endpoint)
     */
    public function togglePaymentGateway(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gateway' => 'required|in:expresspay,hubtel,paystack,flutterwave',
            'enabled' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = SystemSetting::getSettings();
            $gateway = $request->gateway;
            $enabledField = 'enable_' . $gateway;
            
            // Check if gateway is properly configured before enabling
            if ($request->enabled) {
                $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
                $gatewayConfig = $paymentConfiguration[$gateway] ?? null;
                
                if (!$gatewayConfig || !$gatewayConfig['configured']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot enable {$gateway}. The gateway is not properly configured. Please configure it in the Payment Providers section first.",
                        'gateway' => $gateway,
                        'needs_configuration' => true
                    ], 422);
                }
            }
            
            $settings->$enabledField = $request->enabled;
            $settings->updated_by = auth()->id();
            $settings->save();
            
            $status = $request->enabled ? 'enabled' : 'disabled';
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($gateway) . " has been {$status} successfully",
                'gateway' => $gateway,
                'enabled' => $request->enabled,
                'enabled_gateways' => $settings->getEnabledGatewayNames(),
                'gateway_count' => $settings->getEnabledPaymentProvidersCount()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling payment gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment gateway statuses (New endpoint)
     */
    public function getPaymentGatewayStatuses()
    {
        try {
            $settings = SystemSetting::getSettings();
            $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
            
            $gateways = [];
            $providers = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            
            foreach ($providers as $provider) {
                $providerConfig = $paymentConfiguration[$provider] ?? null;
                $enabledField = 'enable_' . $provider;
                
                $gateways[$provider] = [
                    'name' => $this->getProviderDisplayName($provider),
                    'enabled' => $settings->$enabledField ?? false,
                    'configured' => $providerConfig && $providerConfig['configured'],
                    'available' => $providerConfig && $providerConfig['enabled'] && $providerConfig['configured'],
                    'icon' => $this->getProviderIcon($provider),
                    'color' => $this->getProviderColor($provider),
                    'description' => $this->getProviderDescription($provider)
                ];
            }
            
            return response()->json([
                'success' => true,
                'gateways' => $gateways,
                'total_enabled' => $settings->getEnabledPaymentProvidersCount(),
                'total_available' => count(array_filter($gateways, fn($g) => $g['available'])),
                'has_enabled_gateways' => $settings->hasEnabledPaymentMethods()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving gateway statuses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get provider display name (Updated for new gateways)
     */
    protected function getProviderDisplayName($providerKey)
    {
        $providers = [
            'expresspay' => 'ExpressPay',
            'hubtel' => 'Hubtel',
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave'
        ];

        return $providers[$providerKey] ?? ucfirst(str_replace('_', ' ', $providerKey));
    }

    /**
     * Helper method to get provider icon (New helper)
     */
    protected function getProviderIcon($providerKey)
    {
        $icons = [
            'expresspay' => 'fa-credit-card',
            'hubtel' => 'fa-phone-alt',
            'paystack' => 'fa-credit-card',
            'flutterwave' => 'fa-cloud-upload-alt'
        ];

        return $icons[$providerKey] ?? 'fa-credit-card';
    }

    /**
     * Helper method to get provider color (New helper)
     */
    protected function getProviderColor($providerKey)
    {
        $colors = [
            'expresspay' => '#0066CC',
            'hubtel' => '#2563EB',
            'paystack' => '#3B82F6',
            'flutterwave' => '#F97316'
        ];

        return $colors[$providerKey] ?? '#6B7280';
    }

    /**
     * Helper method to get provider description (New helper)
     */
    protected function getProviderDescription($providerKey)
    {
        $descriptions = [
            'expresspay' => 'Mobile money & online payments',
            'hubtel' => 'Mobile money collections',
            'paystack' => 'Cards, bank transfers & mobile money',
            'flutterwave' => 'Pan-African payment gateway'
        ];

        return $descriptions[$providerKey] ?? 'Payment gateway';
    }
}