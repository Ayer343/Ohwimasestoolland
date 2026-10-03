<?php

namespace App\Services;

use App\Models\TenantPayment;
use App\Models\TenantInvoice;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TenantPaymentService extends PaymentService
{
    /**
     * TenantPaymentService constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Generate a unique transaction ID for tenant payments
     */
    public function generateTenantTransactionId($provider = null): string
    {
        $prefixMap = [
            'expresspay' => 'TNT_EXP',
            'hubtel' => 'TNT_HUB',
            'paystack' => 'TNT_PSK',
            'flutterwave' => 'TNT_FLW'
        ];
        
        $prefix = $prefixMap[$provider] ?? 'TNT_PAY';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(6));
        
        $transactionId = "{$prefix}_{$timestamp}_{$random}";
        
        while (TenantPayment::where('transaction_id', $transactionId)->exists()) {
            $random = strtoupper(Str::random(6));
            $transactionId = "{$prefix}_{$timestamp}_{$random}";
        }
        
        return $transactionId;
    }

    /**
     * Process tenant payment (UPDATED FOR NEW GATEWAYS)
     */
    public function processTenantPayment(array $paymentData, User $tenant): array
    {
        try {
            $settings = SystemSetting::getSettings();
            
            // Validate tenant invoicing is enabled
            if (!$settings->enable_tenant_invoicing) {
                return [
                    'success' => false,
                    'message' => 'Tenant invoicing is currently disabled.'
                ];
            }

            // Validate configuration
            $configCheck = $this->verifyPaymentConfiguration();
            if (!$configCheck['valid']) {
                return [
                    'success' => false,
                    'message' => 'Payment system not properly configured. Please contact administrator.',
                    'issues' => $configCheck['issues']
                ];
            }

            // Validate the payment provider is enabled
            $provider = $paymentData['payment_provider'];
            $configuration = $this->checkPaymentMethodConfiguration();
            
            if (!isset($configuration[$provider]) || !$configuration[$provider]['enabled'] || !$configuration[$provider]['configured']) {
                return [
                    'success' => false,
                    'message' => "Payment provider '{$provider}' is not enabled or configured."
                ];
            }

            // Validate invoice belongs to tenant and is pending
            $invoice = TenantInvoice::where('id', $paymentData['invoice_id'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->first();

            if (!$invoice) {
                return [
                    'success' => false,
                    'message' => 'Invalid invoice selected or invoice already paid.'
                ];
            }

            // Validate amount matches invoice balance
            if (abs($paymentData['amount'] - $invoice->balance) > 0.01) {
                return [
                    'success' => false,
                    'message' => 'Payment amount must match the invoice balance.'
                ];
            }

            DB::beginTransaction();

            // Create payment record
            $payment = TenantPayment::create([
                'transaction_id' => $this->generateTenantTransactionId($provider),
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'property_unit_id' => $invoice->property_unit_id,
                'payment_provider' => $provider,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? $settings->currency_code,
                'status' => TenantPayment::STATUS_PENDING,
                'description' => $paymentData['description'] ?? "Payment for invoice #{$invoice->invoice_number}",
                'phone_number' => $paymentData['phone_number'] ?? null,
                'email' => $paymentData['email'] ?? null,
                'transaction_reference' => $this->generateTenantReference(),
                'metadata' => $this->buildTenantPaymentMetadata($paymentData, $tenant, $invoice),
                'created_by' => $tenant->id,
                'updated_by' => $tenant->id,
            ]);

            // Mark invoice as processing
            $invoice->update(['status' => 'processing']);

            // Process with payment provider
            $providerResult = $this->processWithTenantProvider($paymentData, $payment);
            
            if (!$providerResult['success']) {
                DB::rollBack();
                return $providerResult;
            }

            // Update payment with provider reference
            if (isset($providerResult['reference'])) {
                $payment->update(['transaction_reference' => $providerResult['reference']]);
            }

            DB::commit();

            Log::info('✅ Tenant payment initiated', [
                'transaction_id' => $payment->transaction_id,
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'amount' => $paymentData['amount'],
                'provider' => $provider
            ]);

            // Prepare response
            $response = [
                'success' => true,
                'transaction_id' => $payment->transaction_id,
                'message' => $providerResult['message'] ?? 'Payment initiated successfully',
                'instructions' => $this->getPaymentInstructions($provider),
                'requires_verification' => in_array($provider, ['expresspay', 'hubtel', 'flutterwave'])
            ];

            if (isset($providerResult['redirect_url'])) {
                $response['redirect_url'] = $providerResult['redirect_url'];
            }

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Tenant payment processing error: ' . $e->getMessage(), [
                'payment_data' => $paymentData,
                'tenant_id' => $tenant->id
            ]);
            
            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process with tenant-specific provider (UPDATED)
     */
    protected function processWithTenantProvider(array $paymentData, TenantPayment $payment): array
    {
        $provider = $paymentData['payment_provider'];
        
        switch ($provider) {
            case 'expresspay':
                return $this->processTenantExpressPayPayment($paymentData, $payment);
                
            case 'hubtel':
                return $this->processTenantHubtelPayment($paymentData, $payment);
                
            case 'paystack':
                return $this->processTenantPaystackPayment($paymentData, $payment);
                
            case 'flutterwave':
                return $this->processTenantFlutterwavePayment($paymentData, $payment);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment provider: ' . $provider
                ];
        }
    }

    /**
     * Process ExpressPay payment for tenant
     */
    protected function processTenantExpressPayPayment(array $paymentData, TenantPayment $payment): array
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
                    'message' => 'ExpressPay API credentials are not configured.'
                ];
            }

            $apiRequest = [
                'merchant_id' => $merchantId,
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'customer_mobile_number' => $this->formatPhoneNumber($paymentData['phone_number']),
                'transaction_id' => $payment->transaction_id,
                'description' => $paymentData['description'] ?? "Tenant payment for invoice #{$payment->invoice->invoice_number}",
                'callback_url' => route('tenant.payments.callback', ['provider' => 'expresspay']),
                'return_url' => route('tenant.payments.confirmation', ['transactionId' => $payment->transaction_id])
            ];

            $accessToken = $this->getExpressPayAccessToken($clientId, $clientSecret, $baseUrl);
            
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with ExpressPay API.'
                ];
            }
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
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
                                'merchant_id' => $merchantId
                            ]
                        )
                    ]);

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'message' => 'Payment request sent to ExpressPay. Please check your phone to authorize.',
                        'verification_required' => true
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'ExpressPay payment failed. Please try again.'
            ];

        } catch (\Exception $e) {
            Log::error('❌ ExpressPay payment failed for tenant: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ExpressPay payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Hubtel payment for tenant
     */
    protected function processTenantHubtelPayment(array $paymentData, TenantPayment $payment): array
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
                    'message' => 'Hubtel API credentials are not configured.'
                ];
            }

            $apiRequest = [
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'customer_phone' => $this->formatPhoneNumber($paymentData['phone_number']),
                'transaction_id' => $payment->transaction_id,
                'description' => $paymentData['description'] ?? "Tenant payment for invoice #{$payment->invoice->invoice_number}",
                'callback_url' => route('tenant.payments.callback', ['provider' => 'hubtel'])
            ];

            $accessToken = $this->getHubtelAccessToken($clientId, $clientSecret, $baseUrl);
            
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with Hubtel API.'
                ];
            }
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
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
                                'provider_response' => $responseData
                            ]
                        )
                    ]);

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['transaction_id'] ?? $payment->transaction_id,
                        'message' => 'Payment request sent to Hubtel. Please check your phone to authorize.',
                        'verification_required' => true
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Hubtel payment failed. Please try again.'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Hubtel payment failed for tenant: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Hubtel payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Paystack payment for tenant
     */
    protected function processTenantPaystackPayment(array $paymentData, TenantPayment $payment): array
    {
        $secretKey = env('PAYSTACK_SECRET_KEY');
        $publicKey = env('PAYSTACK_PUBLIC_KEY');
        $baseUrl = env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co');

        if (empty($secretKey) || empty($publicKey)) {
            return [
                'success' => false,
                'message' => 'Paystack API credentials are not configured.'
            ];
        }

        try {
            $requestData = [
                'email' => $paymentData['email'],
                'amount' => $paymentData['amount'] * 100,
                'reference' => $payment->transaction_reference,
                'currency' => $paymentData['currency'] ?? 'GHS',
                'callback_url' => route('tenant.payments.callback', ['provider' => 'paystack']),
                'metadata' => [
                    'transaction_id' => $payment->transaction_id,
                    'invoice_id' => $payment->invoice_id,
                    'tenant_id' => $payment->tenant_id,
                    'invoice_number' => $payment->invoice->invoice_number,
                    'tenant_name' => $payment->tenant->name,
                    'custom_fields' => [
                        [
                            'display_name' => 'Tenant',
                            'variable_name' => 'tenant',
                            'value' => $payment->tenant->name
                        ],
                        [
                            'display_name' => 'Invoice',
                            'variable_name' => 'invoice',
                            'value' => $payment->invoice->invoice_number
                        ],
                        [
                            'display_name' => 'Period',
                            'variable_name' => 'period',
                            'value' => Carbon::parse($payment->invoice->period . '-01')->format('F Y')
                        ]
                    ]
                ]
            ];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
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
                            ['paystack_authorization_url' => $responseData['data']['authorization_url']]
                        )
                    ]);

                    return [
                        'success' => true,
                        'redirect_url' => $responseData['data']['authorization_url'],
                        'reference' => $responseData['data']['reference'],
                        'message' => 'Redirecting to Paystack for payment...'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Paystack payment initialization failed.'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Paystack payment failed for tenant: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Paystack payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Flutterwave payment for tenant
     */
    protected function processTenantFlutterwavePayment(array $paymentData, TenantPayment $payment): array
    {
        try {
            $publicKey = env('FLUTTERWAVE_PUBLIC_KEY');
            $secretKey = env('FLUTTERWAVE_SECRET_KEY');
            $environment = env('FLUTTERWAVE_ENVIRONMENT', 'sandbox');
            
            $baseUrl = $environment === 'production' 
                ? 'https://api.flutterwave.com/v3'
                : 'https://api.flutterwave.com/v3';

            if (empty($publicKey) || empty($secretKey)) {
                return [
                    'success' => false,
                    'message' => 'Flutterwave API credentials are not configured.'
                ];
            }

            $apiRequest = [
                'tx_ref' => $payment->transaction_id,
                'amount' => (string) $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'GHS',
                'phone_number' => $this->formatPhoneNumber($paymentData['phone_number']),
                'email' => $paymentData['email'] ?? $payment->tenant->email,
                'fullname' => $payment->tenant->name,
                'title' => 'Tenant Invoice Payment',
                'description' => $paymentData['description'] ?? "Payment for invoice #{$payment->invoice->invoice_number}",
                'redirect_url' => route('tenant.payments.callback', ['provider' => 'flutterwave']),
                'meta' => [
                    'transaction_id' => $payment->transaction_id,
                    'invoice_id' => $payment->invoice_id,
                    'tenant_id' => $payment->tenant_id,
                    'invoice_number' => $payment->invoice->invoice_number
                ]
            ];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
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
                                'flutterwave_transaction_id' => $responseData['data']['id'] ?? null
                            ]
                        )
                    ]);

                    if (isset($responseData['data']['redirect_url'])) {
                        return [
                            'success' => true,
                            'redirect_url' => $responseData['data']['redirect_url'],
                            'reference' => $responseData['data']['tx_ref'],
                            'message' => 'Redirecting to Flutterwave for payment...'
                        ];
                    }

                    return [
                        'success' => true,
                        'reference' => $responseData['data']['tx_ref'],
                        'message' => 'Payment request sent to Flutterwave. Please check your phone to authorize.',
                        'verification_required' => true
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Flutterwave payment initialization failed.'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Flutterwave payment failed for tenant: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Flutterwave payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payments for a specific tenant
     */
    public function getPaymentsForTenant(int $tenantId, array $filters = [])
    {
        $query = TenantPayment::with(['invoice.propertyUnit.property'])
            ->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['property_unit_id'])) {
            $query->where('property_unit_id', $filters['property_unit_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('transaction_reference', 'like', "%{$search}%")
                  ->orWhereHas('invoice', function($q) use ($search) {
                      $q->where('invoice_number', 'like', "%{$search}%");
                  });
            });
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $allowedSortFields = ['created_at', 'payment_date', 'amount', 'status'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get payment by transaction ID
     */
    public function getPaymentByTransactionId(string $transactionId): ?TenantPayment
    {
        return TenantPayment::with(['invoice.propertyUnit.property', 'tenant'])
            ->where('transaction_id', $transactionId)
            ->first();
    }

    /**
     * Get total paid by tenant
     */
    public function getTotalPaidByTenant(int $tenantId): float
    {
        return TenantPayment::where('tenant_id', $tenantId)
            ->where('status', TenantPayment::STATUS_COMPLETED)
            ->sum('amount') ?? 0.00;
    }

    /**
     * Get payment counts by tenant
     */
    public function getPaymentCountsByTenant(int $tenantId): array
    {
        return [
            'total' => TenantPayment::where('tenant_id', $tenantId)->count(),
            'completed' => TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->count(),
            'pending' => TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_PENDING)
                ->count(),
            'processing' => TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_PROCESSING)
                ->count(),
            'failed' => TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_FAILED)
                ->count(),
            'cancelled' => TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_CANCELLED)
                ->count()
        ];
    }

    /**
     * Get pending count by tenant
     */
    public function getPendingCountByTenant(int $tenantId): int
    {
        return TenantPayment::where('tenant_id', $tenantId)
            ->where('status', TenantPayment::STATUS_PENDING)
            ->count();
    }

    /**
     * Get completed count by tenant
     */
    public function getCompletedCountByTenant(int $tenantId): int
    {
        return TenantPayment::where('tenant_id', $tenantId)
            ->where('status', TenantPayment::STATUS_COMPLETED)
            ->count();
    }

    /**
     * Get failed count by tenant
     */
    public function getFailedCountByTenant(int $tenantId): int
    {
        return TenantPayment::where('tenant_id', $tenantId)
            ->where('status', TenantPayment::STATUS_FAILED)
            ->count();
    }

    /**
     * Verify tenant payment (UPDATED)
     */
    public function verifyTenantPayment(string $transactionId, string $verificationCode, string $provider): array
    {
        try {
            $payment = TenantPayment::where('transaction_id', $transactionId)->first();
            
            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found'
                ];
            }

            // Validate verification code format
            if (strlen($verificationCode) !== 6 || !is_numeric($verificationCode)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code format. Must be 6 digits.'
                ];
            }

            // Mark payment as completed
            $this->markTenantPaymentAsCompleted($payment, [
                'verification_code' => $verificationCode,
                'verified_at' => now()->toISOString(),
                'provider' => $provider
            ]);

            // Update invoice status
            $this->updateTenantInvoiceStatus($payment->invoice);

            Log::info('✅ Tenant payment verified', [
                'transaction_id' => $transactionId,
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id
            ]);

            return [
                'success' => true,
                'status' => TenantPayment::STATUS_COMPLETED,
                'message' => 'Payment verified successfully!',
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            Log::error('❌ Tenant payment verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel tenant payment
     */
    public function cancelTenantPayment(string $transactionId): array
    {
        try {
            $payment = TenantPayment::where('transaction_id', $transactionId)->first();
            
            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment not found'
                ];
            }

            if ($payment->status === TenantPayment::STATUS_COMPLETED) {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel completed payment'
                ];
            }

            $payment->update([
                'status' => TenantPayment::STATUS_CANCELLED,
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    ['cancelled_at' => now()->toISOString()]
                )
            ]);

            // Reset invoice status
            if ($payment->invoice && $payment->invoice->status === 'processing') {
                $payment->invoice->update(['status' => 'pending']);
            }

            Log::info('✅ Tenant payment cancelled', [
                'transaction_id' => $transactionId,
                'payment_id' => $payment->id
            ]);

            return [
                'success' => true,
                'message' => 'Payment cancelled successfully'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Failed to cancel tenant payment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to cancel payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate tenant reference number
     */
    protected function generateTenantReference(): string
    {
        return 'TNT_REF_' . now()->format('YmdHis') . '_' . Str::upper(Str::random(6));
    }

    /**
     * Build tenant payment metadata (UPDATED - removed recipient references)
     */
    protected function buildTenantPaymentMetadata(array $paymentData, User $tenant, TenantInvoice $invoice): array
    {
        $settings = SystemSetting::getSettings();
        
        return [
            'system_name' => $settings->system_name,
            'system_short_name' => $settings->getSystemShortName(),
            'system_currency' => $settings->currency_code,
            
            'payment_type' => 'tenant_invoice',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_period' => Carbon::parse($invoice->period . '-01')->format('F Y'),
            
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'tenant_phone' => $paymentData['phone_number'] ?? null,
            
            'property_unit_id' => $invoice->property_unit_id,
            'property_name' => $invoice->propertyUnit->property->property_name ?? 'Unknown',
            'unit_number' => $invoice->propertyUnit->unit_number ?? 'Unknown',
            
            'created_at' => now()->toISOString(),
            'requires_verification' => in_array($paymentData['payment_provider'], ['expresspay', 'hubtel', 'flutterwave'])
        ];
    }

    /**
     * Mark tenant payment as completed
     */
    protected function markTenantPaymentAsCompleted(TenantPayment $payment, array $additionalData = []): void
    {
        $payment->update([
            'status' => TenantPayment::STATUS_COMPLETED,
            'payment_date' => now(),
            'metadata' => array_merge(
                $payment->metadata ?? [],
                $additionalData,
                ['completed_at' => now()->toISOString()]
            )
        ]);
    }

    /**
     * Update tenant invoice status after payment
     */
    protected function updateTenantInvoiceStatus(TenantInvoice $invoice): void
    {
        if ($invoice) {
            $invoice->update([
                'status' => TenantInvoice::STATUS_PAID,
                'paid_amount' => $invoice->total_amount,
                'balance' => 0,
                'payment_date' => now(),
                'payment_method' => $invoice->payment_method ?? null,
                'payment_reference' => $invoice->payment_reference ?? null
            ]);

            Log::info('✅ Tenant invoice marked as paid', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id' => $invoice->tenant_id
            ]);
        }
    }

    /**
     * Get tenant payment statistics
     */
    public function getTenantPaymentStatistics(int $tenantId): array
    {
        try {
            $totalPayments = TenantPayment::where('tenant_id', $tenantId)->count();
            $completedPayments = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->count();
            $processingPayments = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_PROCESSING)
                ->count();
            $pendingPayments = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_PENDING)
                ->count();
            
            $totalAmount = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->sum('amount');
            
            $recentPayments = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->orderBy('payment_date', 'desc')
                ->limit(5)
                ->get();

            $monthlyTotals = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->where('created_at', '>=', now()->subMonths(6))
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            $paymentByProvider = TenantPayment::where('tenant_id', $tenantId)
                ->where('status', TenantPayment::STATUS_COMPLETED)
                ->selectRaw('payment_provider, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('payment_provider')
                ->get()
                ->mapWithKeys(function($item) {
                    $providerNames = [
                        'expresspay' => 'ExpressPay',
                        'hubtel' => 'Hubtel',
                        'paystack' => 'Paystack',
                        'flutterwave' => 'Flutterwave'
                    ];
                    return [$item->payment_provider => [
                        'total' => $item->total,
                        'count' => $item->count,
                        'provider_name' => $providerNames[$item->payment_provider] ?? ucfirst($item->payment_provider)
                    ]];
                });

            return [
                'total_payments' => $totalPayments,
                'completed_payments' => $completedPayments,
                'processing_payments' => $processingPayments,
                'pending_payments' => $pendingPayments,
                'total_amount' => $totalAmount,
                'formatted_total_amount' => SystemSetting::getSettings()->formatAmount($totalAmount),
                'recent_payments' => $recentPayments,
                'monthly_totals' => $monthlyTotals,
                'payment_by_provider' => $paymentByProvider,
                'success_rate' => $totalPayments > 0 ? round(($completedPayments / $totalPayments) * 100, 2) : 0
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get tenant payment statistics: ' . $e->getMessage());
            return [
                'total_payments' => 0,
                'completed_payments' => 0,
                'processing_payments' => 0,
                'pending_payments' => 0,
                'total_amount' => 0,
                'formatted_total_amount' => '₵0.00',
                'recent_payments' => collect(),
                'monthly_totals' => collect(),
                'payment_by_provider' => collect(),
                'success_rate' => 0
            ];
        }
    }

    /**
     * Get payment details for tenant
     */
    public function getPaymentDetails(int $paymentId, int $tenantId): ?TenantPayment
    {
        return TenantPayment::with(['invoice.propertyUnit.property', 'tenant'])
            ->where('id', $paymentId)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}