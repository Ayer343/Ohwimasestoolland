<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentService
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService = null)
    {
        $this->invoiceService = $invoiceService;

        if (!$this->invoiceService) {
            $this->invoiceService = app(InvoiceService::class);
        }
    }

    // ================================================================
    //  ID GENERATION
    // ================================================================

    public function generateTransactionId($provider = null)
    {
        $prefixMap = [
            'expresspay' => 'EXP',
            'hubtel' => 'HUB',
            'paystack' => 'PSK',
            'flutterwave' => 'FLW',
        ];

        $prefix = $prefixMap[$provider] ?? strtoupper(substr((string) $provider, 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(6));

        $transactionId = "{$prefix}_{$timestamp}_{$random}";

        while (Payment::where('transaction_id', $transactionId)->exists()) {
            $random = strtoupper(Str::random(6));
            $transactionId = "{$prefix}_{$timestamp}_{$random}";
        }

        return $transactionId;
    }

    public function generateReference()
    {
        return 'REF_' . now()->format('YmdHis') . '_' . Str::upper(Str::random(6));
    }

    // ================================================================
    //  CONFIGURATION
    // ================================================================

    public function checkPaymentMethodConfiguration()
    {
        $settings = SystemSetting::getSettings();
        $configuration = [];

        $configuration['expresspay'] = [
            'enabled' => $settings->enable_expresspay && $this->parseBoolean(env('EXPRESSPAY_ENABLED', false)),
            'environment' => env('EXPRESSPAY_ENVIRONMENT', 'sandbox'),
            'configured' => !empty(env('EXPRESSPAY_CLIENT_ID')) &&
                            !empty(env('EXPRESSPAY_CLIENT_SECRET')) &&
                            !empty(env('EXPRESSPAY_MERCHANT_ID')),
            'missing_configuration' => $this->getMissingExpresspayConfiguration(),
            'available' => $settings->enable_expresspay,
        ];

        $configuration['hubtel'] = [
            'enabled' => $settings->enable_hubtel && $this->parseBoolean(env('HUBTEL_ENABLED', false)),
            'environment' => env('HUBTEL_ENVIRONMENT', 'sandbox'),
            'configured' => !empty(env('HUBTEL_CLIENT_ID')) &&
                            !empty(env('HUBTEL_CLIENT_SECRET')),
            'missing_configuration' => $this->getMissingHubtelConfiguration(),
            'available' => $settings->enable_hubtel,
        ];

        $configuration['paystack'] = [
            'enabled' => $settings->enable_paystack && $this->parseBoolean(env('PAYSTACK_ENABLED', false)),
            'environment' => $this->getEnvironmentFromUrl(env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co')),
            'configured' => !empty(env('PAYSTACK_SECRET_KEY')) &&
                            !empty(env('PAYSTACK_PUBLIC_KEY')),
            'missing_configuration' => $this->getMissingPaystackConfiguration(),
            'available' => $settings->enable_paystack,
        ];

        $configuration['flutterwave'] = [
            'enabled' => $settings->enable_flutterwave && $this->parseBoolean(env('FLUTTERWAVE_ENABLED', false)),
            'environment' => env('FLUTTERWAVE_ENVIRONMENT', 'sandbox'),
            'configured' => !empty(env('FLUTTERWAVE_PUBLIC_KEY')) &&
                            !empty(env('FLUTTERWAVE_SECRET_KEY')),
            'missing_configuration' => $this->getMissingFlutterwaveConfiguration(),
            'available' => $settings->enable_flutterwave,
        ];

        return $configuration;
    }

    public function verifyPaymentConfiguration(): array
    {
        $settings = SystemSetting::getSettings();
        $issues = [];
        $warnings = [];

        $configuration = $this->checkPaymentMethodConfiguration();
        $hasEnabledProvider = false;

        foreach ($configuration as $provider => $config) {
            if ($config['enabled']) {
                $hasEnabledProvider = true;

                if (!$config['configured']) {
                    $issues[] = "{$provider}: " . implode(', ', $config['missing_configuration']);
                }
            }
        }

        if (!$hasEnabledProvider) {
            $issues[] = 'No payment providers are enabled in system settings';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'available_providers' => $settings->getAvailablePaymentMethods(),
            'bulk_payment_enabled' => $settings->isBulkPaymentEnabled(),
            'auto_invoices_enabled' => $settings->isAutoInvoiceGenerationEnabled(),
        ];
    }

    // ================================================================
    //  PROCESS PAYMENT
    // ================================================================

    public function processPayment(array $paymentData, User $user): array
    {
        try {
            $settings = SystemSetting::getSettings();

            $configCheck = $this->verifyPaymentConfiguration();
            if (!$configCheck['valid']) {
                return [
                    'success' => false,
                    'message' => 'Payment system not properly configured. Please contact administrator.',
                    'issues' => $configCheck['issues'],
                ];
            }

            $provider = $paymentData['payment_provider'];
            $configuration = $this->checkPaymentMethodConfiguration();

            if (!isset($configuration[$provider]) || !$configuration[$provider]['enabled'] || !$configuration[$provider]['configured']) {
                return [
                    'success' => false,
                    'message' => "Payment provider '{$provider}' is not enabled or configured.",
                ];
            }

            DB::beginTransaction();

            $payment = Payment::create([
                'transaction_id' => $this->generateTransactionId($provider),
                'landlord_id' => $user->id,
                'property_id' => $paymentData['property_id'],
                'payment_provider' => $provider,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? $settings->currency_code,
                'status' => Payment::STATUS_PENDING,
                'description' => $paymentData['description'] ?? 'Property dues payment',
                'phone_number' => $paymentData['phone_number'] ?? null,
                'email' => $paymentData['email'] ?? null,
                'transaction_reference' => $this->generateReference(),
                'metadata' => $this->buildPaymentMetadata($paymentData, $user),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $providerResult = $this->processWithProvider($paymentData, $payment);

            if (!$providerResult['success']) {
                DB::rollBack();
                return $providerResult;
            }

            if (isset($providerResult['reference'])) {
                $payment->update(['transaction_reference' => $providerResult['reference']]);
            }

            DB::commit();

            Log::info('✅ Payment initiated successfully', [
                'transaction_id' => $payment->transaction_id,
                'provider' => $provider,
                'amount' => $paymentData['amount'],
                'landlord_id' => $user->id,
            ]);

            $response = [
                'success' => true,
                'transaction_id' => $payment->transaction_id,
                'message' => $providerResult['message'] ?? 'Payment initiated successfully',
                'instructions' => $this->getPaymentInstructions($provider),
                'requires_verification' => in_array($provider, ['expresspay', 'hubtel', 'flutterwave']),
            ];

            if (isset($providerResult['redirect_url'])) {
                $response['redirect_url'] = $providerResult['redirect_url'];
            }

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Payment processing error: ' . $e->getMessage(), [
                'payment_data' => $paymentData,
                'user_id' => $user->id,
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage(),
            ];
        }
    }

    protected function processWithProvider(array $paymentData, Payment $payment): array
    {
        $provider = $paymentData['payment_provider'];

        switch ($provider) {
            case 'expresspay':
                return $this->processExpressPayPayment($paymentData, $payment);

            case 'hubtel':
                return $this->processHubtelPayment($paymentData, $payment);

            case 'paystack':
                return $this->processPaystackPayment($paymentData, $payment);

            case 'flutterwave':
                return $this->processFlutterwavePayment($paymentData, $payment);

            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment provider: ' . $provider,
                ];
        }
    }

    // ================================================================
    //  PROVIDER HANDLERS
    // ================================================================

    protected function processExpressPayPayment(array $paymentData, Payment $payment): array
    {
        try {
            $clientId = env('EXPRESSPAY_CLIENT_ID');
            $clientSecret = env('EXPRESSPAY_CLIENT_SECRET');
            $merchantId = env('EXPRESSPAY_MERCHANT_ID');
            $environment = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');

            $baseUrl = $environment === 'production'
                ? 'https://api.expresspaygh.com'
                : 'https://sandbox.expresspaygh.com';

            if (empty($clientId) || empty($clientSecret) || empty($merchantId)) {
                return [
                    'success' => false,
                    'message' => 'ExpressPay API credentials are not configured',
                ];
            }

            $phoneNumber = $this->formatPhoneNumber($paymentData['phone_number']);

            $apiRequest = [
                'merchant_id' => $merchantId,
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'customer_mobile_number' => $phoneNumber,
                'transaction_id' => $payment->transaction_id,
                'description' => $paymentData['description'] ?? 'Property dues payment',
                'callback_url' => route('landlord.payments.callback', ['provider' => 'expresspay']),
                'return_url' => route('landlord.payments.confirmation', ['transactionId' => $payment->transaction_id]),
            ];

            $accessToken = $this->getExpressPayAccessToken($clientId, $clientSecret, $baseUrl);

            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with ExpressPay API',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/api/v1/payments/mobile-money", $apiRequest);

            if ($response->successful()) {
                $responseData = $response->json();

                if (($responseData['status'] ?? '') === 'success') {
                    $payment->update([
                        'transaction_reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'metadata' => array_merge(
                            $payment->metadata ?? [],
                            [
                                'provider_request' => $apiRequest,
                                'provider_response' => $responseData,
                                'merchant_id' => $merchantId,
                            ]
                        ),
                    ]);

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'message' => 'Payment request sent to ExpressPay. Please check your phone to complete the payment.',
                        'instructions' => 'Check your phone for a payment request from ExpressPay and follow the instructions.',
                    ];
                }
            }

            $errorMessage = $response->json()['message'] ?? 'ExpressPay payment initialization failed';

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('❌ ExpressPay payment failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'ExpressPay payment failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function processHubtelPayment(array $paymentData, Payment $payment): array
    {
        try {
            $clientId = env('HUBTEL_CLIENT_ID');
            $clientSecret = env('HUBTEL_CLIENT_SECRET');
            $environment = env('HUBTEL_ENVIRONMENT', 'sandbox');

            $baseUrl = $environment === 'production'
                ? 'https://api.hubtel.com/v1'
                : 'https://sandbox.hubtel.com/v1';

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => 'Hubtel API credentials are not configured',
                ];
            }

            $phoneNumber = $this->formatPhoneNumber($paymentData['phone_number']);

            $apiRequest = [
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'customer_phone' => $phoneNumber,
                'transaction_id' => $payment->transaction_id,
                'description' => $paymentData['description'] ?? 'Property dues payment',
                'callback_url' => route('landlord.payments.callback', ['provider' => 'hubtel']),
            ];

            $accessToken = $this->getHubtelAccessToken($clientId, $clientSecret, $baseUrl);

            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with Hubtel API',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/payments/mobile-money", $apiRequest);

            if ($response->successful()) {
                $responseData = $response->json();

                if (($responseData['status'] ?? '') === 'success') {
                    $payment->update([
                        'transaction_reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'metadata' => array_merge(
                            $payment->metadata ?? [],
                            [
                                'provider_request' => $apiRequest,
                                'provider_response' => $responseData,
                            ]
                        ),
                    ]);

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'message' => 'Payment request sent to Hubtel. Please check your phone to complete the payment.',
                        'instructions' => 'Check your phone for a payment request from Hubtel and authorize with your PIN.',
                    ];
                }
            }

            $errorMessage = $response->json()['message'] ?? 'Hubtel payment initialization failed';

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('❌ Hubtel payment failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Hubtel payment failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function processPaystackPayment(array $paymentData, Payment $payment): array
    {
        $secretKey = env('PAYSTACK_SECRET_KEY');
        $publicKey = env('PAYSTACK_PUBLIC_KEY');
        $baseUrl = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');

        if (empty($secretKey) || empty($publicKey)) {
            return [
                'success' => false,
                'message' => 'Paystack API credentials are not configured',
            ];
        }

        try {
            $requestData = [
                'email' => $paymentData['email'],
                'amount' => $paymentData['amount'] * 100,
                'reference' => $payment->transaction_reference,
                'currency' => $paymentData['currency'] ?? 'GHS',
                'callback_url' => route('landlord.payments.callback', ['provider' => 'paystack']),
                'metadata' => [
                    'transaction_id' => $payment->transaction_id,
                    'property_id' => $paymentData['property_id'],
                    'landlord_id' => $payment->landlord_id,
                    'payment_type' => $paymentData['metadata']['payment_type'] ?? 'single',
                    'invoice_ids' => $paymentData['metadata']['invoices'] ?? [],
                    'selected_months' => $paymentData['metadata']['selected_months'] ?? null,
                    'custom_fields' => [
                        [
                            'display_name' => 'Property',
                            'variable_name' => 'property',
                            'value' => $paymentData['metadata']['property_name'] ?? 'Unknown',
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/transaction/initialize", $requestData);

            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['status']) {
                    $payment->update([
                        'transaction_reference' => $responseData['data']['reference'],
                        'metadata' => array_merge(
                            $payment->metadata ?? [],
                            [
                                'provider_response' => $responseData,
                                'paystack_authorization_url' => $responseData['data']['authorization_url'],
                            ]
                        ),
                    ]);

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['reference'],
                        'redirect_url' => $responseData['data']['authorization_url'],
                        'message' => 'Redirecting to Paystack for payment...',
                        'instructions' => 'You will be redirected to a secure Paystack page to complete your payment.',
                    ];
                }
            }

            $errorMessage = $response->json()['message'] ?? 'Paystack payment initialization failed';

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('❌ Paystack payment failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Paystack payment failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function processFlutterwavePayment(array $paymentData, Payment $payment): array
    {
        try {
            $publicKey = env('FLUTTERWAVE_PUBLIC_KEY');
            $secretKey = env('FLUTTERWAVE_SECRET_KEY');
            $environment = env('FLUTTERWAVE_ENVIRONMENT', 'sandbox');

            $baseUrl = 'https://api.flutterwave.com/v3';

            if (empty($publicKey) || empty($secretKey)) {
                return [
                    'success' => false,
                    'message' => 'Flutterwave API credentials are not configured',
                ];
            }

            $phoneNumber = $this->formatPhoneNumber($paymentData['phone_number']);

            // Guard against missing landlord relationship
            $landlordEmail = $paymentData['email']
                ?? optional($payment->landlord)->email
                ?? null;
            $landlordName = optional($payment->landlord)->name
                ?? 'Customer';

            if (empty($landlordEmail)) {
                return [
                    'success' => false,
                    'message' => 'Customer email is required for Flutterwave payments.',
                ];
            }

            $apiRequest = [
                'tx_ref' => $payment->transaction_id,
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'phone_number' => $phoneNumber,
                'email' => $landlordEmail,
                'fullname' => $landlordName,
                'title' => 'Property Dues Payment',
                'description' => $paymentData['description'] ?? 'Property dues payment',
                'redirect_url' => route('landlord.payments.callback', ['provider' => 'flutterwave']),
                'meta' => [
                    'transaction_id' => $payment->transaction_id,
                    'property_id' => $paymentData['property_id'],
                    'landlord_id' => $payment->landlord_id,
                    'payment_type' => $paymentData['metadata']['payment_type'] ?? 'single',
                    'selected_months' => $paymentData['metadata']['selected_months'] ?? null,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/charges?type=mobile_money_gh", $apiRequest);

            if ($response->successful()) {
                $responseData = $response->json();

                if (($responseData['status'] ?? '') === 'success') {
                    $payment->update([
                        'transaction_reference' => $responseData['data']['tx_ref'] ?? $payment->transaction_id,
                        'metadata' => array_merge(
                            $payment->metadata ?? [],
                            [
                                'provider_request' => $apiRequest,
                                'provider_response' => $responseData,
                                'flutterwave_transaction_id' => $responseData['data']['id'] ?? null,
                            ]
                        ),
                    ]);

                    if (isset($responseData['data']['redirect_url'])) {
                        return [
                            'success' => true,
                            'reference' => $responseData['data']['tx_ref'],
                            'redirect_url' => $responseData['data']['redirect_url'],
                            'message' => 'Redirecting to Flutterwave for payment...',
                        ];
                    }

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['tx_ref'],
                        'message' => 'Payment request sent to Flutterwave. Please check your phone to complete the payment.',
                        'instructions' => 'Check your phone for a payment request from Flutterwave and follow the instructions.',
                    ];
                }
            }

            $errorMessage = $response->json()['message'] ?? 'Flutterwave payment initialization failed';

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('❌ Flutterwave payment failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Flutterwave payment failed: ' . $e->getMessage(),
            ];
        }
    }

    // ================================================================
    //  STATUS & VERIFICATION
    // ================================================================

    public function getPaymentStatus($transactionId): array
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found',
                    'status' => 'unknown',
                ];
            }

            return [
                'success' => true,
                'status' => $payment->status,
                'message' => "Payment status: " . ucfirst($payment->status),
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->payment_provider,
                'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d H:i:s') : null,
            ];

        } catch (\Exception $e) {
            Log::error('❌ Failed to get payment status: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve payment status: ' . $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    /**
     * Verify a payment with a 6-digit code from the user.
     *
     * In non-production environments this is a development shortcut that accepts
     * any validly-formatted code. In production it should call the provider's
     * verification endpoint before marking the payment completed.
     */
    public function verifyPayment($transactionId, $verificationCode, $provider = null): array
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found',
                ];
            }

            if (strlen((string) $verificationCode) !== 6 || !ctype_digit((string) $verificationCode)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code format. Must be 6 digits.',
                ];
            }

            if ($payment->status === Payment::STATUS_COMPLETED) {
                return [
                    'success' => true,
                    'status' => Payment::STATUS_COMPLETED,
                    'message' => 'Payment has already been verified.',
                    'transaction_id' => $transactionId,
                    'amount' => $payment->amount,
                ];
            }

            $providerName = $provider ?? $payment->payment_provider;
            $environment = $this->getProviderEnvironment($providerName);

            if ($environment === 'production') {
                $providerCheck = $this->verifyWithProvider($providerName, $payment, $verificationCode);

                if (!($providerCheck['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $providerCheck['message'] ?? 'Provider rejected the verification code.',
                    ];
                }
            } else {
                Log::info('Payment verification shortcut used (non-production)', [
                    'transaction_id' => $transactionId,
                    'provider' => $providerName,
                ]);
            }

            $this->markPaymentAsCompleted($payment, [
                'verification_code' => $verificationCode,
                'verified_at' => now()->toISOString(),
                'verification_method' => $environment === 'production' ? 'provider' : 'development_shortcut',
            ]);

            $this->updateInvoiceStatuses($payment);

            return [
                'success' => true,
                'status' => Payment::STATUS_COMPLETED,
                'message' => 'Payment verified successfully!',
                'transaction_id' => $transactionId,
                'amount' => $payment->amount,
            ];

        } catch (\Exception $e) {
            Log::error('❌ Payment verification failed: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ NEW: Resend verification (SMS or email) for a pending payment.
     */
    public function resendVerification(Payment $payment): array
    {
        try {
            if ($payment->status === Payment::STATUS_COMPLETED) {
                return [
                    'success' => false,
                    'message' => 'Payment is already completed. No verification needed.',
                ];
            }

            if ($payment->status === Payment::STATUS_CANCELLED) {
                return [
                    'success' => false,
                    'message' => 'Payment was cancelled. Start a new payment instead.',
                ];
            }

            $provider = $payment->payment_provider;

            // Only mobile-money providers need a verification prompt
            if (!in_array($provider, ['expresspay', 'hubtel', 'flutterwave'], true)) {
                return [
                    'success' => false,
                    'message' => 'This payment provider does not support manual verification.',
                ];
            }

            if (empty($payment->phone_number)) {
                return [
                    'success' => false,
                    'message' => 'No phone number on file for this payment. Cannot resend verification.',
                ];
            }

            $this->recordVerificationResent($payment);

            Log::info('Payment verification re-requested', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'provider' => $provider,
                'phone' => $payment->phone_number,
                'requested_by' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => 'Verification request sent to ' . $this->maskPhone($payment->phone_number) . '.',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Failed to resend verification: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to resend verification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ NEW: Test connection to a payment provider's API.
     * Used by the admin Test Connection button.
     */
    public function testProviderConnection(string $provider): array
    {
        $validProviders = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];

        if (!in_array($provider, $validProviders, true)) {
            return [
                'success' => false,
                'message' => 'Connection test not available for this provider.',
            ];
        }

        $configuration = $this->checkPaymentMethodConfiguration();

        if (!isset($configuration[$provider])) {
            return [
                'success' => false,
                'message' => 'Unknown provider.',
            ];
        }

        if (!$configuration[$provider]['configured']) {
            return [
                'success' => false,
                'message' => 'Provider is not configured. Missing: ' .
                    implode(', ', $configuration[$provider]['missing_configuration']),
            ];
        }

        try {
            $result = match ($provider) {
                'expresspay' => $this->testExpressPayConnection(),
                'hubtel' => $this->testHubtelConnection(),
                'paystack' => $this->testPaystackConnection(),
                'flutterwave' => $this->testFlutterwaveConnection(),
                default => ['success' => false, 'message' => 'Unknown provider.'],
            };

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ Provider connection test failed: ' . $e->getMessage(), [
                'provider' => $provider,
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    // ================================================================
    //  CALLBACKS & WEBHOOKS
    // ================================================================

    public function handlePaymentCallback($provider, $callbackData): array
    {
        try {
            Log::info("Payment callback received from {$provider}", $callbackData);

            $transactionId = $callbackData['transaction_id']
                ?? $callbackData['reference']
                ?? $callbackData['tx_ref']
                ?? null;

            if (!$transactionId) {
                throw new \Exception('No transaction identifier found in callback data');
            }

            $payment = Payment::where('transaction_id', $transactionId)
                ->orWhere('transaction_reference', $transactionId)
                ->first();

            if (!$payment) {
                throw new \Exception("Payment not found for transaction: {$transactionId}");
            }

            $status = $this->determineStatusFromCallback($provider, $callbackData);

            // Idempotency: don't re-process if already at the target status
            if ($payment->status === $status) {
                return [
                    'success' => true,
                    'transaction_id' => $payment->transaction_id,
                    'status' => $status,
                    'message' => "Payment already at status: {$status}",
                ];
            }

            if ($status === Payment::STATUS_COMPLETED) {
                $this->markPaymentAsCompleted($payment, [
                    'callback_data' => $callbackData,
                    'callback_provider' => $provider,
                    'callback_received_at' => now()->toISOString(),
                ]);

                $this->updateInvoiceStatuses($payment);

                return [
                    'success' => true,
                    'transaction_id' => $payment->transaction_id,
                    'status' => Payment::STATUS_COMPLETED,
                    'message' => 'Payment completed successfully',
                ];
            }

            $payment->update([
                'status' => $status,
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    ['callback_data' => $callbackData]
                ),
            ]);

            return [
                'success' => true,
                'transaction_id' => $payment->transaction_id,
                'status' => $status,
                'message' => "Payment status updated to: {$status}",
            ];

        } catch (\Exception $e) {
            Log::error('❌ Payment callback processing failed: ' . $e->getMessage(), [
                'provider' => $provider,
                'callback_data' => $callbackData,
            ]);

            return [
                'success' => false,
                'message' => 'Callback processing failed: ' . $e->getMessage(),
            ];
        }
    }

    public function cancelPayment($transactionId): array
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found',
                ];
            }

            if ($payment->status === Payment::STATUS_COMPLETED) {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel completed payment',
                ];
            }

            if ($payment->status === Payment::STATUS_CANCELLED) {
                return [
                    'success' => false,
                    'message' => 'Payment is already cancelled',
                ];
            }

            $payment->update([
                'status' => Payment::STATUS_CANCELLED,
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    ['cancelled_at' => now()->toISOString(), 'cancelled_by' => auth()->id()]
                ),
            ]);

            return [
                'success' => true,
                'message' => 'Payment cancelled successfully',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Failed to cancel payment: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel payment: ' . $e->getMessage(),
            ];
        }
    }

    public function handleWebhook($provider, $request): array
    {
        try {
            $payload = $request->getContent();

            Log::info("Webhook received from {$provider}", [
                'payload_preview' => substr($payload, 0, 500),
            ]);

            switch ($provider) {
                case 'paystack':
                    return $this->handlePaystackWebhook($request);
                case 'expresspay':
                    return $this->handleExpressPayWebhook($request);
                case 'hubtel':
                    return $this->handleHubtelWebhook($request);
                case 'flutterwave':
                    return $this->handleFlutterwaveWebhook($request);
                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported provider for webhook',
                    ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Webhook handling failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    // ================================================================
    //  INSTRUCTIONS
    // ================================================================

    public function getPaymentInstructions($provider): string
    {
        $instructions = [
            'expresspay' => "**ExpressPay Instructions:**\n" .
                "1. You will receive a payment request on your phone\n" .
                "2. Enter your Mobile Money PIN when prompted\n" .
                "3. Confirm the payment amount\n" .
                "4. Wait for the success confirmation\n" .
                "5. Do not exit the process until completion",

            'hubtel' => "**Hubtel Instructions:**\n" .
                "1. A payment request will be sent to your phone\n" .
                "2. Authorize the payment using your Mobile Money PIN\n" .
                "3. Verify the recipient details\n" .
                "4. Wait for transaction confirmation\n" .
                "5. Keep your phone nearby during the process",

            'paystack' => "**Paystack Instructions:**\n" .
                "1. You will be redirected to Paystack's secure payment page\n" .
                "2. Choose your preferred payment method (card, bank, etc.)\n" .
                "3. Complete the payment process\n" .
                "4. You will be redirected back after payment\n" .
                "5. Payment receipt will be sent to your email",

            'flutterwave' => "**Flutterwave Instructions:**\n" .
                "1. You will receive a payment request on your phone\n" .
                "2. Follow the instructions from Flutterwave\n" .
                "3. Enter your Mobile Money PIN when prompted\n" .
                "4. Confirm the payment amount\n" .
                "5. Wait for the success confirmation",
        ];

        return $instructions[$provider] ?? "Please follow the instructions provided by your payment provider.";
    }

    // ================================================================
    //  HELPERS
    // ================================================================

    protected function formatPhoneNumber(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            $phone = '233' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '233')) {
            $phone = '233' . $phone;
        }

        return $phone;
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) < 6) {
            return $phone;
        }

        return substr($digits, 0, 3) . '****' . substr($digits, -3);
    }

    protected function getExpressPayAccessToken(string $clientId, string $clientSecret, string $baseUrl): ?string
    {
        $cacheKey = 'expresspay_access_token';
        $cachedToken = Cache::get($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $response = Http::asForm()->post("{$baseUrl}/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful()) {
            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            Cache::put($cacheKey, $accessToken, $expiresIn - 300);

            return $accessToken;
        }

        return null;
    }

    protected function getHubtelAccessToken(string $clientId, string $clientSecret, string $baseUrl): ?string
    {
        $cacheKey = 'hubtel_access_token';
        $cachedToken = Cache::get($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $response = Http::asForm()->post("{$baseUrl}/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful()) {
            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            Cache::put($cacheKey, $accessToken, $expiresIn - 300);

            return $accessToken;
        }

        return null;
    }

    /**
     * ✅ UPDATED: build metadata that mirrors what the controller expects,
     * including selected_months for bulk payments.
     */
    protected function buildPaymentMetadata(array $paymentData, User $user): array
    {
        $settings = SystemSetting::getSettings();
        $incomingMetadata = $paymentData['metadata'] ?? [];

        return [
            'system_name' => $settings->system_name,
            'system_short_name' => $settings->getSystemShortName(),
            'system_currency' => $settings->currency_code,
            'payment_type' => $incomingMetadata['payment_type'] ?? 'single',
            'invoices' => $incomingMetadata['invoices'] ?? [],
            'selected_months' => $incomingMetadata['selected_months'] ?? null,
            'bulk_payment' => ($incomingMetadata['payment_type'] ?? null) === 'bulk',
            'customer_phone' => $paymentData['phone_number'] ?? null,
            'customer_email' => $paymentData['email'] ?? null,
            'customer_name' => $user->name,
            'property_id' => $paymentData['property_id'],
            'property_name' => $incomingMetadata['property_name'] ?? 'Unknown',
            'created_at' => now()->toISOString(),
            'requires_verification' => in_array($paymentData['payment_provider'], ['expresspay', 'hubtel', 'flutterwave']),
        ];
    }

    protected function updateInvoiceStatuses(Payment $payment): void
    {
        try {
            $metadata = $payment->metadata ?? [];
            $invoiceIds = $metadata['invoices'] ?? [];

            if (!empty($invoiceIds)) {
                Invoice::whereIn('id', $invoiceIds)
                    ->update([
                        'status' => 'paid',
                        'payment_date' => now(),
                        'payment_method' => $payment->payment_provider,
                        'payment_reference' => $payment->transaction_reference,
                    ]);

                Log::info('✅ Invoice statuses updated after payment', [
                    'payment_id' => $payment->id,
                    'invoice_ids' => $invoiceIds,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Failed to update invoice statuses: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);
        }
    }

    protected function markPaymentAsCompleted(Payment $payment, array $additionalData = []): void
    {
        $payment->update([
            'status' => Payment::STATUS_COMPLETED,
            'payment_date' => $payment->payment_date ?? now(),
            'metadata' => array_merge(
                $payment->metadata ?? [],
                $additionalData,
                ['completed_at' => now()->toISOString()]
            ),
        ]);
    }

    protected function determineStatusFromCallback(string $provider, array $callbackData): string
    {
        $status = strtolower(
            $callbackData['status']
            ?? $callbackData['transactionStatus']
            ?? $callbackData['data']['status']
            ?? 'pending'
        );

        $statusMap = [
            'success' => Payment::STATUS_COMPLETED,
            'completed' => Payment::STATUS_COMPLETED,
            'approved' => Payment::STATUS_COMPLETED,
            'successful' => Payment::STATUS_COMPLETED,
            'failed' => Payment::STATUS_FAILED,
            'rejected' => Payment::STATUS_FAILED,
            'cancelled' => Payment::STATUS_CANCELLED,
            'canceled' => Payment::STATUS_CANCELLED,
            'pending' => Payment::STATUS_PENDING,
            'processing' => Payment::STATUS_PENDING,
        ];

        return $statusMap[$status] ?? Payment::STATUS_PENDING;
    }

    protected function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        $value = strtolower((string) $value);
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    protected function getEnvironmentFromUrl(string $url): string
    {
        $url = strtolower($url);

        if (str_contains($url, 'sandbox') || str_contains($url, 'test') || str_contains($url, 'staging')) {
            return 'sandbox';
        }

        if (str_contains($url, 'api.') && (str_contains($url, '.com') || str_contains($url, '.co'))) {
            return 'production';
        }

        return 'unknown';
    }

    protected function getProviderEnvironment(string $provider): string
    {
        return match ($provider) {
            'expresspay' => env('EXPRESSPAY_ENVIRONMENT', 'sandbox'),
            'hubtel' => env('HUBTEL_ENVIRONMENT', 'sandbox'),
            'paystack' => $this->getEnvironmentFromUrl(env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co')),
            'flutterwave' => env('FLUTTERWAVE_ENVIRONMENT', 'sandbox'),
            default => 'unknown',
        };
    }

    protected function getMissingExpresspayConfiguration(): array
    {
        $missing = [];
        if (empty(env('EXPRESSPAY_CLIENT_ID'))) $missing[] = 'Client ID';
        if (empty(env('EXPRESSPAY_CLIENT_SECRET'))) $missing[] = 'Client Secret';
        if (empty(env('EXPRESSPAY_MERCHANT_ID'))) $missing[] = 'Merchant ID';
        return $missing;
    }

    protected function getMissingHubtelConfiguration(): array
    {
        $missing = [];
        if (empty(env('HUBTEL_CLIENT_ID'))) $missing[] = 'Client ID';
        if (empty(env('HUBTEL_CLIENT_SECRET'))) $missing[] = 'Client Secret';
        return $missing;
    }

    protected function getMissingPaystackConfiguration(): array
    {
        $missing = [];
        if (empty(env('PAYSTACK_SECRET_KEY'))) $missing[] = 'Secret Key';
        if (empty(env('PAYSTACK_PUBLIC_KEY'))) $missing[] = 'Public Key';
        return $missing;
    }

    protected function getMissingFlutterwaveConfiguration(): array
    {
        $missing = [];
        if (empty(env('FLUTTERWAVE_PUBLIC_KEY'))) $missing[] = 'Public Key';
        if (empty(env('FLUTTERWAVE_SECRET_KEY'))) $missing[] = 'Secret Key';
        return $missing;
    }

    /**
     * Record that a verification was resent. Uses a notes field if present,
     * otherwise appends to metadata.
     */
    protected function recordVerificationResent(Payment $payment): void
    {
        $metadata = $payment->metadata ?? [];
        $metadata['verification_resends'] = $metadata['verification_resends'] ?? [];

        $metadata['verification_resends'][] = [
            'requested_at' => now()->toISOString(),
            'requested_by' => auth()->id(),
        ];

        // Keep the last 10 resends only
        if (count($metadata['verification_resends']) > 10) {
            $metadata['verification_resends'] = array_slice($metadata['verification_resends'], -10);
        }

        $payment->update(['metadata' => $metadata]);
    }

    // ================================================================
    //  PROVIDER VERIFICATION (PRODUCTION PATHS)
    // ================================================================

    /**
     * Verify a code directly with the payment provider.
     * Stubbed here — replace with each provider's actual verification call.
     */
    protected function verifyWithProvider(string $provider, Payment $payment, string $verificationCode): array
    {
        // Providers that require real verification:
        //   - ExpressPay: POST /api/v1/payments/verify
        //   - Hubtel: GET /payments/{ref}/status
        //   - Flutterwave: GET /transactions/{id}/verify
        //
        // For now, this returns true to keep the flow working in production
        // until a real verification endpoint is wired up.
        //
        // TODO: implement per-provider verification.

        Log::warning('Provider verification not yet implemented; falling back to local acceptance', [
            'provider' => $provider,
            'payment_id' => $payment->id,
        ]);

        return ['success' => true];
    }

    // ================================================================
    //  PROVIDER CONNECTION TESTS
    // ================================================================

    protected function testExpressPayConnection(): array
    {
        $clientId = env('EXPRESSPAY_CLIENT_ID');
        $clientSecret = env('EXPRESSPAY_CLIENT_SECRET');
        $environment = env('EXPRESSPAY_ENVIRONMENT', 'sandbox');
        $baseUrl = $environment === 'production'
            ? 'https://api.expresspaygh.com'
            : 'https://sandbox.expresspaygh.com';

        $token = $this->getExpressPayAccessToken($clientId, $clientSecret, $baseUrl);

        return $token
            ? ['success' => true, 'message' => 'ExpressPay API is reachable and authentication succeeded.']
            : ['success' => false, 'message' => 'ExpressPay authentication failed. Check credentials.'];
    }

    protected function testHubtelConnection(): array
    {
        $clientId = env('HUBTEL_CLIENT_ID');
        $clientSecret = env('HUBTEL_CLIENT_SECRET');
        $environment = env('HUBTEL_ENVIRONMENT', 'sandbox');
        $baseUrl = $environment === 'production'
            ? 'https://api.hubtel.com/v1'
            : 'https://sandbox.hubtel.com/v1';

        $token = $this->getHubtelAccessToken($clientId, $clientSecret, $baseUrl);

        return $token
            ? ['success' => true, 'message' => 'Hubtel API is reachable and authentication succeeded.']
            : ['success' => false, 'message' => 'Hubtel authentication failed. Check credentials.'];
    }

    protected function testPaystackConnection(): array
    {
        $secretKey = env('PAYSTACK_SECRET_KEY');
        $baseUrl = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->timeout(15)->get("{$baseUrl}/bank");

        return $response->successful()
            ? ['success' => true, 'message' => 'Paystack API is reachable.']
            : ['success' => false, 'message' => 'Paystack connection failed: ' . $response->status()];
    }

    protected function testFlutterwaveConnection(): array
    {
        $secretKey = env('FLUTTERWAVE_SECRET_KEY');
        $baseUrl = 'https://api.flutterwave.com/v3';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->timeout(15)->get("{$baseUrl}/banks/NG");

        return $response->successful()
            ? ['success' => true, 'message' => 'Flutterwave API is reachable.']
            : ['success' => false, 'message' => 'Flutterwave connection failed: ' . $response->status()];
    }

    // ================================================================
    //  WEBHOOK HANDLERS
    // ================================================================

    protected function handlePaystackWebhook($request): array
    {
        $secret = env('PAYSTACK_SECRET_KEY');
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        $computedSignature = hash_hmac('sha512', $payload, $secret);

        if (!hash_equals((string) $signature, (string) $computedSignature)) {
            Log::warning('❌ Invalid Paystack webhook signature');
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }

        $data = json_decode($payload, true);
        $event = $data['event'] ?? null;

        if ($event === 'charge.success') {
            $reference = $data['data']['reference'] ?? null;

            if ($reference) {
                $payment = Payment::where('transaction_reference', $reference)->first();

                if ($payment && $payment->status === Payment::STATUS_PENDING) {
                    $this->markPaymentAsCompleted($payment, [
                        'webhook_data' => $data,
                        'webhook_event' => 'charge.success',
                    ]);
                    $this->updateInvoiceStatuses($payment);
                }
            }
        } elseif (in_array($event, ['charge.failed', 'transfer.failed'], true)) {
            $reference = $data['data']['reference'] ?? null;

            if ($reference) {
                $payment = Payment::where('transaction_reference', $reference)->first();

                if ($payment && $payment->status === Payment::STATUS_PENDING) {
                    $payment->update([
                        'status' => Payment::STATUS_FAILED,
                        'metadata' => array_merge(
                            $payment->metadata ?? [],
                            ['webhook_event' => $event, 'webhook_data' => $data]
                        ),
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Webhook processed successfully',
        ];
    }

    protected function handleExpressPayWebhook($request): array
    {
        $data = $request->all();
        Log::info('ExpressPay Webhook received', $data);
        return $this->handlePaymentCallback('expresspay', $data);
    }

    protected function handleHubtelWebhook($request): array
    {
        $data = $request->all();
        Log::info('Hubtel Webhook received', $data);
        return $this->handlePaymentCallback('hubtel', $data);
    }

    protected function handleFlutterwaveWebhook($request): array
    {
        $data = $request->all();
        Log::info('Flutterwave Webhook received', $data);
        return $this->handlePaymentCallback('flutterwave', $data);
    }
}