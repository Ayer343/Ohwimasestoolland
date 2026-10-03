<?php

namespace App\Http\Controllers;

use App\Services\TenantPaymentService;
use App\Models\TenantInvoice;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(TenantPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Alias for create() method - maintains backward compatibility
     * Redirects to the create method
     */
    public function make(Request $request)
    {
        return $this->create($request);
    }

    /**
     * Display payment form for tenant
     */
    public function create(Request $request)
    {
        // Check if user is tenant
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only tenants can access this page.');
        }

        $tenant = auth()->user();
        $settings = SystemSetting::getSettings();

        // Check if tenant invoicing is enabled
        if (!$settings->enable_tenant_invoicing) {
            return redirect()->route('dashboard')
                ->with('info', 'Tenant invoicing is currently disabled.');
        }

        // Get tenant's property units
        $propertyUnits = PropertyUnit::where('tenant_id', $tenant->id)
            ->with('property')
            ->where('tenant_status', 'approved')
            ->get();

        if ($propertyUnits->isEmpty()) {
            return redirect()->route('tenant.invoices.my-invoices')
                ->with('info', 'You don\'t have any active property units.');
        }

        // Get outstanding invoices for all tenant's units
        $propertyUnitIds = $propertyUnits->pluck('id');
        
        $outstandingInvoices = TenantInvoice::whereIn('property_unit_id', $propertyUnitIds)
            ->whereIn('status', ['pending', 'overdue'])
            ->where('balance', '>', 0)
            ->with(['propertyUnit.property'])
            ->orderBy('due_date')
            ->get();

        $totalDue = $outstandingInvoices->sum('balance');

        // Get available payment methods using the same logic as PaymentConfigController
        $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
        $availableMethods = [];
        $paymentInstructions = [];

        // Filter providers based on system settings AND provider configuration
        foreach ($configurationStatus as $provider => $status) {
            // Check if provider is enabled in system settings
            $enabledField = 'enable_' . $provider;
            $isEnabledInSettings = $settings->$enabledField ?? false;
            
            // Provider must be BOTH enabled in system settings AND properly configured
            if ($status['enabled'] && $status['configured'] && $isEnabledInSettings) {
                $availableMethods[$provider] = $this->getProviderDisplayName($provider);
                $paymentInstructions[$provider] = $this->paymentService->getPaymentInstructions($provider);
            }
        }

        // If no payment methods available
        if (empty($availableMethods)) {
            return redirect()->back()
                ->with('error', 'No payment methods are currently available. Please contact administrator.');
        }

        // Check for pre-selected invoice
        $preSelectedInvoiceId = $request->input('invoice_id');
        $preSelectedInvoice = null;
        
        if ($preSelectedInvoiceId) {
            $preSelectedInvoice = TenantInvoice::where('id', $preSelectedInvoiceId)
                ->whereIn('property_unit_id', $propertyUnitIds)
                ->whereIn('status', ['pending', 'overdue'])
                ->first();
        }

        // Get gateway statuses with display metadata (matching PaymentConfigController)
        $gatewayStatuses = $this->getGatewayStatusesWithMetadata($configurationStatus, $settings);

        return view('tenant.payments.create', compact(
            'propertyUnits',
            'outstandingInvoices',
            'totalDue',
            'availableMethods',
            'paymentInstructions',
            'configurationStatus',
            'gatewayStatuses',
            'preSelectedInvoice',
            'settings'
        ));
    }

    /**
     * Process tenant payment (Updated for new gateways with system settings validation)
     */
    public function process(Request $request)
    {
        // Check if user is tenant
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $tenant = auth()->user();
        $settings = SystemSetting::getSettings();

        // Get available payment providers (must be enabled in both system settings AND configured)
        $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
        $availableProviders = [];
        
        foreach ($configurationStatus as $provider => $status) {
            $enabledField = 'enable_' . $provider;
            $isEnabledInSettings = $settings->$enabledField ?? false;
            
            if ($status['enabled'] && $status['configured'] && $isEnabledInSettings) {
                $availableProviders[] = $provider;
            }
        }

        // If no providers available, return error
        if (empty($availableProviders)) {
            return redirect()->back()
                ->with('error', 'No payment methods are currently available. Please contact administrator.')
                ->withInput();
        }

        // Updated validation rules for new gateways
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:tenant_invoices,id',
            'payment_provider' => 'required|in:' . implode(',', $availableProviders),
            'amount' => 'required|numeric|min:0.01',
            'phone_number' => 'required_if:payment_provider,expresspay,hubtel,flutterwave|nullable|string',
            'email' => 'required_if:payment_provider,paystack|nullable|email',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate invoice belongs to tenant and is pending
        $invoice = TenantInvoice::where('id', $request->invoice_id)
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->first();

        if (!$invoice) {
            return redirect()->back()
                ->with('error', 'Invalid invoice selected or invoice already paid.')
                ->withInput();
        }

        // Validate amount matches invoice balance
        if (abs($request->amount - $invoice->balance) > 0.01) {
            return redirect()->back()
                ->with('error', 'Payment amount must match the invoice balance of ' . 
                       $settings->formatAmount($invoice->balance))
                ->withInput();
        }

        try {
            $paymentData = [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'payment_provider' => $request->payment_provider,
                'amount' => $request->amount,
                'currency' => $settings->currency_code,
                'description' => $request->description ?? "Payment for invoice #{$invoice->invoice_number}",
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                    'period' => Carbon::parse($invoice->period . '-01')->format('F Y'),
                    'property_unit_id' => $invoice->property_unit_id,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                ]
            ];

            // Provider-specific data (Updated for new gateways)
            if (in_array($request->payment_provider, ['expresspay', 'hubtel', 'flutterwave'])) {
                $paymentData['phone_number'] = $request->phone_number;
            }
            
            if ($request->payment_provider === 'paystack') {
                $paymentData['email'] = $request->email;
            }

            \Log::info('Tenant payment initiated', [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'provider' => $request->payment_provider,
            ]);

            $result = $this->paymentService->processTenantPayment($paymentData, $tenant);

            if ($result['success']) {
                // Mark invoice as processing
                $invoice->update(['status' => 'processing']);

                if (isset($result['redirect_url'])) {
                    return redirect()->away($result['redirect_url'])
                        ->with('info', $result['message']);
                }

                return redirect()->route('tenant.payments.confirmation', [
                    'transactionId' => $result['transaction_id']
                ])->with('success', $result['message']);
            }

            return redirect()->back()
                ->with('error', $result['message'])
                ->withInput();

        } catch (\Exception $e) {
            \Log::error('Tenant payment processing error: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => $request->payment_provider
            ]);

            return redirect()->back()
                ->with('error', 'Payment processing failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display tenant payment history
     */
    public function history(Request $request)
    {
        // Check if user is tenant
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $tenant = auth()->user();
        $settings = SystemSetting::getSettings();

        // Check if tenant invoicing is enabled
        if (!$settings->enable_tenant_invoicing) {
            return redirect()->route('dashboard')
                ->with('info', 'Tenant invoicing is currently disabled.');
        }

        // Get filters from request
        $filters = [
            'status' => $request->get('status'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'property_unit_id' => $request->get('property_unit_id'),
            'search' => $request->get('search'),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
            'per_page' => $request->get('per_page', 15)
        ];

        // Get payments using the service (this returns a paginated result)
        $payments = $this->paymentService->getPaymentsForTenant($tenant->id, $filters);

        // Calculate individual statistics for the view
        $totalPaid = $this->paymentService->getTotalPaidByTenant($tenant->id);
        $pendingCount = $this->paymentService->getPendingCountByTenant($tenant->id);
        $completedCount = $this->paymentService->getCompletedCountByTenant($tenant->id);
        $failedCount = $this->paymentService->getFailedCountByTenant($tenant->id);

        // Get tenant's property units for filter dropdown
        $propertyUnits = PropertyUnit::where('tenant_id', $tenant->id)
            ->with('property')
            ->get()
            ->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'display_name' => $unit->property->property_name . ' - Unit ' . $unit->unit_number
                ];
            });

        // Define statuses for filter dropdown
        $statuses = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];

        return view('tenant.payments.history', compact(
            'payments',
            'totalPaid',
            'pendingCount',
            'completedCount',
            'failedCount',
            'propertyUnits',
            'statuses',
            'settings'
        ));
    }

    /**
     * Show payment confirmation
     */
    public function showConfirmation($transactionId)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $payment = $this->paymentService->getPaymentByTransactionId($transactionId);

        if (!$payment || $payment->tenant_id !== auth()->id()) {
            return redirect()->route('tenant.payments.history')
                ->with('error', 'Payment not found.');
        }

        $settings = SystemSetting::getSettings();
        $instructions = $this->paymentService->getPaymentInstructions($payment->payment_provider);

        $requiresVerification = in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) 
            && $payment->status === 'pending';

        return view('tenant.payments.confirmation', compact(
            'payment',
            'instructions',
            'requiresVerification',
            'settings'
        ));
    }

    /**
     * Show verification form for mobile money payments (Updated for new gateways)
     */
    public function showVerificationForm($transactionId)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $payment = $this->paymentService->getPaymentByTransactionId($transactionId);
        
        if (!$payment || $payment->tenant_id !== auth()->id()) {
            return redirect()->route('tenant.payments.history')
                ->with('error', 'Payment not found.');
        }
        
        if (!in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) || 
            $payment->status !== 'pending') {
            return redirect()->route('tenant.payments.confirmation', [
                'transactionId' => $transactionId
            ])->with('info', 'Payment does not require verification');
        }
        
        return view('tenant.payments.verify', compact('payment'));
    }

    /**
     * Verify payment (for mobile money) - Updated for new gateways
     */
    public function verifyPayment(Request $request)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'verification_code' => 'required|string|size:6',
            'provider' => 'required|in:expresspay,hubtel,flutterwave'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $result = $this->paymentService->verifyTenantPayment(
            $request->transaction_id,
            $request->verification_code,
            $request->provider
        );

        if ($result['success']) {
            return redirect()->route('tenant.payments.confirmation', [
                'transactionId' => $request->transaction_id
            ])->with('success', $result['message']);
        }

        return redirect()->back()
            ->with('error', $result['message'])
            ->withInput();
    }

    /**
     * Check payment status via AJAX
     */
    public function checkPaymentStatus($transactionId)
    {
        if (!auth()->user()->isTenant()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->paymentService->getPaymentStatus($transactionId);
            
            // Update invoice if payment completed
            if ($result['success'] && $result['status'] === 'completed') {
                $payment = $this->paymentService->getPaymentByTransactionId($transactionId);
                if ($payment && $payment->invoice) {
                    $payment->invoice->update([
                        'status' => 'paid',
                        'payment_date' => now(),
                        'payment_method' => $payment->payment_provider,
                        'paid_amount' => $payment->amount,
                        'balance' => 0
                    ]);
                }
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status'
            ], 500);
        }
    }

    /**
     * Cancel payment
     */
    public function cancelPayment($transactionId)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        $payment = $this->paymentService->getPaymentByTransactionId($transactionId);

        if (!$payment || $payment->tenant_id !== auth()->id()) {
            return redirect()->route('tenant.payments.history')
                ->with('error', 'Payment not found.');
        }

        if ($payment->status === 'completed') {
            return redirect()->back()
                ->with('error', 'Cannot cancel completed payment.');
        }

        $result = $this->paymentService->cancelTenantPayment($transactionId);

        if ($result['success']) {
            // Reset invoice status
            if ($payment->invoice) {
                $payment->invoice->update(['status' => 'pending']);
            }

            return redirect()->route('tenant.payments.history')
                ->with('success', $result['message']);
        }

        return redirect()->back()
            ->with('error', $result['message']);
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(Request $request, $transactionId)
    {
        if (!auth()->user()->isTenant()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment = $this->paymentService->getPaymentByTransactionId($transactionId);

        if (!$payment || $payment->tenant_id !== auth()->id()) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // In production, this would call the provider's API to resend the code
        \Log::info('Verification code resent', [
            'transaction_id' => $transactionId,
            'tenant_id' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent successfully. Please check your phone.'
        ]);
    }

    /**
     * Export payments as CSV
     */
    public function exportPayments(Request $request)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $tenant = auth()->user();
        $format = $request->get('format', 'csv');

        // Get filters
        $filters = [
            'status' => $request->get('status'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'property_unit_id' => $request->get('property_unit_id'),
            'search' => $request->get('search'),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
        ];

        // Get all payments (no pagination)
        $payments = $this->paymentService->getPaymentsForTenant($tenant->id, $filters);
        $allPayments = $payments->getCollection();

        $filename = 'payments_export_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($handle, [
            'Transaction ID',
            'Date',
            'Reference',
            'Invoice Number',
            'Period',
            'Property Unit',
            'Amount',
            'Payment Method',
            'Status',
            'Description'
        ]);
        
        // Data
        foreach ($allPayments as $payment) {
            fputcsv($handle, [
                $payment->transaction_id,
                $payment->created_at->format('Y-m-d H:i:s'),
                $payment->transaction_reference ?? 'N/A',
                $payment->invoice?->invoice_number ?? 'N/A',
                $payment->invoice ? Carbon::parse($payment->invoice->period . '-01')->format('F Y') : 'N/A',
                $payment->propertyUnit?->property?->property_name . ' - Unit ' . ($payment->propertyUnit?->unit_number ?? 'N/A'),
                $payment->amount,
                $this->getProviderDisplayName($payment->payment_provider),
                ucfirst($payment->status),
                $payment->description ?? 'N/A'
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get payment details (AJAX)
     */
    public function getPaymentDetails($paymentId)
    {
        if (!auth()->user()->isTenant()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment = $this->paymentService->getPaymentDetails($paymentId, auth()->id());

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $settings = SystemSetting::getSettings();

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'transaction_reference' => $payment->transaction_reference,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                'amount' => $payment->amount,
                'formatted_amount' => $settings->formatAmount($payment->amount),
                'currency' => $payment->currency,
                'payment_provider' => $payment->payment_provider,
                'provider_display' => $this->getProviderDisplayName($payment->payment_provider),
                'status' => $payment->status,
                'status_badge_class' => $this->getStatusBadgeClass($payment->status),
                'payment_date' => $payment->payment_date?->format('Y-m-d H:i:s'),
                'description' => $payment->description,
                'notes' => $payment->notes,
                'phone_number' => $payment->phone_number,
                'email' => $payment->email,
                'metadata' => $payment->metadata,
                'invoice' => $payment->invoice ? [
                    'id' => $payment->invoice->id,
                    'invoice_number' => $payment->invoice->invoice_number,
                    'period' => Carbon::parse($payment->invoice->period . '-01')->format('F Y'),
                    'amount' => $payment->invoice->total_amount,
                    'formatted_amount' => $settings->formatAmount($payment->invoice->total_amount)
                ] : null,
                'property_unit' => $payment->propertyUnit ? [
                    'id' => $payment->propertyUnit->id,
                    'unit_number' => $payment->propertyUnit->unit_number,
                    'property_name' => $payment->propertyUnit->property?->property_name ?? 'N/A'
                ] : null
            ],
            'settings' => [
                'currency_symbol' => $settings->currency_symbol,
                'currency_position' => $settings->currency_position,
                'decimal_places' => $settings->decimal_places ?? 2
            ]
        ]);
    }

    // ========== CALLBACK HANDLERS ==========

    /**
     * Handle payment callback from providers (Generic handler)
     */
    public function handleCallback(Request $request, $provider = null)
    {
        // If provider not in route parameter, try to get from request
        if (!$provider) {
            $provider = $request->get('provider') ?? $request->get('payment_provider');
        }

        \Log::info('Tenant payment callback received', [
            'provider' => $provider,
            'params' => $request->all(),
            'ip' => $request->ip()
        ]);

        try {
            $validProviders = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            
            if (!$provider || !in_array($provider, $validProviders)) {
                \Log::warning('Invalid provider in tenant callback', ['provider' => $provider]);
                return redirect()->route('tenant.dashboard')
                    ->with('error', 'Invalid payment provider.');
            }

            $result = $this->paymentService->handleTenantPaymentCallback($provider, $request->all());

            if ($result['success']) {
                // Update invoice status based on payment result
                if (isset($result['transaction_id']) && $result['status'] === 'completed') {
                    $this->updateTenantInvoiceStatus($result['transaction_id']);
                }

                \Log::info('Tenant callback processed successfully', [
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'provider' => $provider
                ]);

                if (isset($result['transaction_id'])) {
                    return redirect()->route('tenant.payments.confirmation', [
                        'transactionId' => $result['transaction_id']
                    ])->with('success', $result['message'] ?? 'Payment completed successfully!');
                }

                return redirect()->route('tenant.payments.history')
                    ->with('success', $result['message'] ?? 'Payment processed successfully!');
            }

            \Log::error('Tenant callback processing failed', [
                'provider' => $provider,
                'error' => $result['message'] ?? 'Unknown error'
            ]);

            return redirect()->route('tenant.payments.history')
                ->with('error', $result['message'] ?? 'Payment processing failed. Please contact support.');

        } catch (\Exception $e) {
            \Log::error('Tenant payment callback error: ' . $e->getMessage(), [
                'provider' => $provider,
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('tenant.dashboard')
                ->with('error', 'Payment callback processing failed. Please contact support.');
        }
    }

    /**
     * Handle Paystack callback specifically
     */
    public function handlePaystackCallback(Request $request)
    {
        \Log::info('Paystack tenant callback received', $request->all());

        try {
            $result = $this->paymentService->handleTenantPaymentCallback('paystack', $request->all());

            if ($result['success'] && isset($result['transaction_id']) && $result['status'] === 'completed') {
                $this->updateTenantInvoiceStatus($result['transaction_id']);
                
                return redirect()->route('tenant.payments.confirmation', [
                    'transactionId' => $result['transaction_id']
                ])->with('success', $result['message'] ?? 'Payment completed successfully!');
            }

            return redirect()->route('tenant.payments.history')
                ->with('error', $result['message'] ?? 'Payment processing failed.');

        } catch (\Exception $e) {
            \Log::error('Paystack callback error: ' . $e->getMessage(), [
                'params' => $request->all()
            ]);

            return redirect()->route('tenant.dashboard')
                ->with('error', 'Payment callback processing failed.');
        }
    }

    /**
     * Handle ExpressPay callback
     */
    public function handleExpressPayCallback(Request $request)
    {
        \Log::info('ExpressPay tenant callback received', $request->all());

        try {
            $result = $this->paymentService->handleTenantPaymentCallback('expresspay', $request->all());

            if ($result['success'] && isset($result['transaction_id']) && $result['status'] === 'completed') {
                $this->updateTenantInvoiceStatus($result['transaction_id']);
                
                return redirect()->route('tenant.payments.confirmation', [
                    'transactionId' => $result['transaction_id']
                ])->with('success', $result['message'] ?? 'Payment completed successfully!');
            }

            return redirect()->route('tenant.payments.history')
                ->with('error', $result['message'] ?? 'Payment processing failed.');

        } catch (\Exception $e) {
            \Log::error('ExpressPay callback error: ' . $e->getMessage(), [
                'params' => $request->all()
            ]);

            return redirect()->route('tenant.dashboard')
                ->with('error', 'Payment callback processing failed.');
        }
    }

    /**
     * Handle Hubtel callback
     */
    public function handleHubtelCallback(Request $request)
    {
        \Log::info('Hubtel tenant callback received', $request->all());

        try {
            $result = $this->paymentService->handleTenantPaymentCallback('hubtel', $request->all());

            if ($result['success'] && isset($result['transaction_id']) && $result['status'] === 'completed') {
                $this->updateTenantInvoiceStatus($result['transaction_id']);
                
                return redirect()->route('tenant.payments.confirmation', [
                    'transactionId' => $result['transaction_id']
                ])->with('success', $result['message'] ?? 'Payment completed successfully!');
            }

            return redirect()->route('tenant.payments.history')
                ->with('error', $result['message'] ?? 'Payment processing failed.');

        } catch (\Exception $e) {
            \Log::error('Hubtel callback error: ' . $e->getMessage(), [
                'params' => $request->all()
            ]);

            return redirect()->route('tenant.dashboard')
                ->with('error', 'Payment callback processing failed.');
        }
    }

    /**
     * Handle Flutterwave callback
     */
    public function handleFlutterwaveCallback(Request $request)
    {
        \Log::info('Flutterwave tenant callback received', $request->all());

        try {
            $result = $this->paymentService->handleTenantPaymentCallback('flutterwave', $request->all());

            if ($result['success'] && isset($result['transaction_id']) && $result['status'] === 'completed') {
                $this->updateTenantInvoiceStatus($result['transaction_id']);
                
                return redirect()->route('tenant.payments.confirmation', [
                    'transactionId' => $result['transaction_id']
                ])->with('success', $result['message'] ?? 'Payment completed successfully!');
            }

            return redirect()->route('tenant.payments.history')
                ->with('error', $result['message'] ?? 'Payment processing failed.');

        } catch (\Exception $e) {
            \Log::error('Flutterwave callback error: ' . $e->getMessage(), [
                'params' => $request->all()
            ]);

            return redirect()->route('tenant.dashboard')
                ->with('error', 'Payment callback processing failed.');
        }
    }

    /**
     * Handle webhook from payment providers
     */
    public function handleWebhook(Request $request, $provider)
    {
        \Log::info('Tenant payment webhook received', [
            'provider' => $provider,
            'headers' => $request->headers->all(),
            'payload' => $request->getContent(),
            'ip' => $request->ip()
        ]);

        try {
            $validProviders = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            
            if (!in_array($provider, $validProviders)) {
                \Log::warning('Invalid provider in webhook', ['provider' => $provider]);
                return response()->json(['error' => 'Invalid provider'], 400);
            }

            // The webhook handler in the service should process the raw request
            $result = $this->paymentService->handleTenantWebhook($provider, $request);

            if ($result['success'] && isset($result['transaction_id']) && $result['status'] === 'completed') {
                $this->updateTenantInvoiceStatus($result['transaction_id']);
                
                \Log::info('Webhook processed successfully', ['provider' => $provider]);
                return response()->json(['success' => true], 200);
            }

            \Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $result['message'] ?? 'Unknown error'
            ]);
            
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Webhook processing failed'], 400);

        } catch (\Exception $e) {
            \Log::error('Webhook processing error: ' . $e->getMessage(), [
                'provider' => $provider,
                'payload' => $request->getContent(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Update tenant invoice status after successful payment
     */
    protected function updateTenantInvoiceStatus($transactionId)
    {
        try {
            // Get payment from service
            $payment = $this->paymentService->getPaymentByTransactionId($transactionId);
            
            if (!$payment) {
                \Log::warning('Payment not found for invoice status update', ['transaction_id' => $transactionId]);
                return;
            }

            if ($payment->invoice) {
                $payment->invoice->update([
                    'status' => 'paid',
                    'payment_date' => now(),
                    'payment_method' => $payment->payment_provider,
                    'paid_amount' => $payment->amount,
                    'balance' => 0
                ]);

                \Log::info('Tenant invoice status updated to paid', [
                    'invoice_id' => $payment->invoice->id,
                    'transaction_id' => $transactionId
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error updating tenant invoice status: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
        }
    }

    // ========== NEW HELPER METHODS (Matching PaymentConfigController) ==========

    /**
     * Get gateway statuses with display metadata
     * This matches the getPaymentGatewayStatuses method from PaymentConfigController
     */
    protected function getGatewayStatusesWithMetadata($configurationStatus, $settings)
    {
        $gateways = [];
        $providers = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
        
        foreach ($providers as $provider) {
            $providerConfig = $configurationStatus[$provider] ?? null;
            $enabledField = 'enable_' . $provider;
            
            $gateways[$provider] = [
                'name' => $this->getProviderDisplayName($provider),
                'enabled' => $settings->$enabledField ?? false,
                'configured' => $providerConfig && $providerConfig['configured'],
                'available' => $providerConfig && $providerConfig['enabled'] && $providerConfig['configured'] && ($settings->$enabledField ?? false),
                'icon' => $this->getProviderIcon($provider),
                'color' => $this->getProviderColor($provider),
                'description' => $this->getProviderDescription($provider)
            ];
        }
        
        return $gateways;
    }

    /**
     * Get provider display name (Updated for new gateways)
     * Matches the one in PaymentConfigController
     */
    protected function getProviderDisplayName($providerKey)
    {
        $providers = [
            'expresspay' => 'ExpressPay',
            'hubtel' => 'Hubtel',
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash'
        ];

        return $providers[$providerKey] ?? ucfirst(str_replace('_', ' ', $providerKey));
    }

    /**
     * Get provider icon (Matches PaymentConfigController)
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
     * Get provider color (Matches PaymentConfigController)
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
     * Get provider description (Matches PaymentConfigController)
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

    /**
     * Get status badge class
     */
    protected function getStatusBadgeClass($status)
    {
        return match($status) {
            'completed' => 'badge-success',
            'pending' => 'badge-warning',
            'processing' => 'badge-info',
            'failed' => 'badge-danger',
            'refunded' => 'badge-secondary',
            'cancelled' => 'badge-secondary',
            default => 'badge-light'
        };
    }
}