<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected $paymentService;

    /**
     * Constructor with dependency injection
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    // ================================================================
    //  LANDLORD PAYMENT FLOW (unchanged)
    // ================================================================

    /**
     * Display payment page for a specific property - WITH COVERAGE AWARENESS
     * ✅ FIXED: Properly handles query parameters from GET requests
     */
    public function showPaymentForm($propertyId)
    {
        \Log::info('Payment form called', [
            'propertyId' => $propertyId,
            'all_query' => request()->query(),
            'invoice_ids_query' => request()->query('invoice_ids'),
            'invoice_ids_input' => request()->input('invoice_ids'),
            'method' => request()->method(),
            'url' => request()->fullUrl()
        ]);

        $property = Property::with('landlord')->findOrFail($propertyId);

        if (auth()->id() !== $property->landlord_id) {
            return redirect()->route('landlord.invoices')
                ->with('error', 'Unauthorized. You can only make payments for your own properties.');
        }

        $settings = SystemSetting::getSettings();

        $preSelectedInvoiceIds = request()->query('invoice_ids', []);

        if (!is_array($preSelectedInvoiceIds)) {
            $preSelectedInvoiceIds = [$preSelectedInvoiceIds];
        }

        $preSelectedInvoiceIds = array_filter($preSelectedInvoiceIds);

        $preSelectedPaymentType = request()->query('payment_type', 'invoices');
        $preSelected = request()->query('pre_selected', false) === 'true' || !empty($preSelectedInvoiceIds);

        \Log::info('Invoice IDs from query', [
            'preSelectedInvoiceIds' => $preSelectedInvoiceIds,
            'preSelected' => $preSelected,
            'payment_type' => $preSelectedPaymentType
        ]);

        $existingCoverage = $this->getExistingBulkCoverage($property);
        $availableMonths = $this->getAvailableMonthsForBulkPayment($property, $settings);

        $outstandingInvoices = Invoice::where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'overdue', 'processing'])
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'consolidated')
            ->whereNull('deleted_at')
            ->whereNotIn('period', $existingCoverage['covered_periods'])
            ->orderBy('due_date')
            ->get();

        $totalDue = $outstandingInvoices->sum('total_amount');

        $consolidatedPending = Invoice::where('property_id', $propertyId)
            ->where('status', 'consolidated')
            ->whereNull('deleted_at')
            ->where(function($q) {
                $q->whereNull('bulk_payment_id')
                  ->orWhereHas('bulkPayment', function($sub) {
                      $sub->where('status', 'paid');
                  });
            })
            ->get();

        foreach ($consolidatedPending as $consolidated) {
            if ($consolidated->bulkPayment && $consolidated->bulkPayment->status === 'paid') {
                $consolidated->update([
                    'status' => 'paid',
                    'payment_date' => $consolidated->bulkPayment->payment_date,
                    'payment_method' => $consolidated->bulkPayment->payment_provider,
                    'payment_reference' => $consolidated->bulkPayment->transaction_reference
                ]);
                \Log::info('Auto-marked consolidated invoice as paid', [
                    'consolidated_id' => $consolidated->id,
                    'bulk_payment_id' => $consolidated->bulk_payment_id
                ]);
            }
        }

        $outstandingInvoices = Invoice::where('property_id', $propertyId)
            ->whereIn('status', ['pending', 'overdue', 'processing'])
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'consolidated')
            ->whereNull('deleted_at')
            ->whereNotIn('period', $existingCoverage['covered_periods'])
            ->orderBy('due_date')
            ->get();

        if ($preSelected && !empty($preSelectedInvoiceIds)) {
            $preSelectedInvoiceIds = Invoice::whereIn('id', $preSelectedInvoiceIds)
                ->where('property_id', $propertyId)
                ->whereIn('status', ['pending', 'overdue', 'processing'])
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'consolidated')
                ->whereNotIn('period', $existingCoverage['covered_periods'])
                ->pluck('id')
                ->toArray();

            \Log::info('Filtered invoice IDs', [
                'filteredInvoiceIds' => $preSelectedInvoiceIds
            ]);
        }

        if (empty($preSelectedInvoiceIds)) {
            $preSelectedInvoiceIds = $outstandingInvoices->pluck('id')->toArray();
            \Log::info('No valid pre-selected invoices, using outstanding', [
                'outstandingInvoiceIds' => $preSelectedInvoiceIds
            ]);
        }

        if (empty($preSelectedInvoiceIds)) {
            return redirect()->route('landlord.invoices')
                ->with('error', 'No valid invoices available for payment.');
        }

        $preSelectedInvoices = Invoice::whereIn('id', $preSelectedInvoiceIds)
            ->where('property_id', $propertyId)
            ->get();

        $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
        $availableMethods = [];
        $paymentInstructions = [];

        foreach ($configurationStatus as $provider => $status) {
            if ($status['enabled'] && $status['configured']) {
                $availableMethods[$provider] = $this->getProviderDisplayName($provider);
                $paymentInstructions[$provider] = $this->paymentService->getPaymentInstructions($provider);
            }
        }

        if (empty($availableMethods)) {
            return redirect()->route('landlord.invoices')
                ->with('error', 'No payment methods are currently available. Please contact administrator.');
        }

        return view('landlord.payments.create', compact(
            'property', 'settings', 'outstandingInvoices', 'totalDue',
            'availableMethods', 'paymentInstructions', 'configurationStatus',
            'preSelectedInvoiceIds', 'preSelectedPaymentType',
            'preSelected', 'preSelectedInvoices',
            'existingCoverage', 'availableMonths'
        ));
    }

    /**
     * Get existing bulk coverage for a property (including pending bulk invoices)
     */
    private function getExistingBulkCoverage(Property $property): array
    {
        $coveredPeriods = [];
        $activeCoverages = [];
        $pendingCoverages = [];

        $bulkInvoices = Invoice::where('property_id', $property->id)
            ->where('is_bulk_payment', true)
            ->whereIn('status', ['paid', 'pending', 'processing'])
            ->whereNull('deleted_at')
            ->get();

        foreach ($bulkInvoices as $bulkInvoice) {
            $periods = $bulkInvoice->covers_periods;

            if (is_string($periods)) {
                $periods = json_decode($periods, true);
            }

            if (empty($periods) && $bulkInvoice->bulk_coverage_start && $bulkInvoice->bulk_coverage_end) {
                $startDate = Carbon::parse($bulkInvoice->bulk_coverage_start . '-01');
                $endDate = Carbon::parse($bulkInvoice->bulk_coverage_end . '-01');
                $current = clone $startDate;
                $periods = [];

                while ($current <= $endDate) {
                    $periods[] = $current->format('Y-m');
                    $current->addMonth();
                }
            }

            if (is_array($periods) && !empty($periods)) {
                $coveredPeriods = array_merge($coveredPeriods, $periods);

                $coverageInfo = [
                    'invoice_id' => $bulkInvoice->id,
                    'invoice_number' => $bulkInvoice->invoice_number ?? 'INV-' . str_pad($bulkInvoice->id, 6, '0', STR_PAD_LEFT),
                    'periods' => $periods,
                    'start' => min($periods),
                    'end' => max($periods),
                    'formatted_range' => $this->formatCoverageRange($periods),
                    'status' => $bulkInvoice->status,
                    'months_count' => count($periods),
                    'is_bulk' => true
                ];

                if ($bulkInvoice->status === 'paid') {
                    $activeCoverages[] = $coverageInfo;
                } else {
                    $pendingCoverages[] = $coverageInfo;
                }
            }
        }

        $coveredPeriods = array_unique($coveredPeriods);
        sort($coveredPeriods);

        return [
            'has_coverage' => !empty($activeCoverages),
            'has_pending_coverage' => !empty($pendingCoverages),
            'covered_periods' => $coveredPeriods,
            'active_coverages' => $activeCoverages,
            'pending_coverages' => $pendingCoverages,
            'coverage_count' => count($coveredPeriods),
            'pending_count' => count($pendingCoverages)
        ];
    }

    /**
     * Alias for paymentHistory - maintains backward compatibility with dashboard
     */
    public function history(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            try {
                $user = auth()->user();
                $limit = $request->get('limit', 5);

                $payments = Payment::with(['property'])
                    ->where('landlord_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => $payment->amount,
                            'status' => $payment->status,
                            'payment_method' => $payment->payment_provider,
                            'created_at' => $payment->created_at,
                            'payment_date' => $payment->payment_date,
                            'property' => $payment->property ? [
                                'id' => $payment->property->id,
                                'name' => $payment->property->property_name ?? $payment->property->street_name
                            ] : null,
                            'tenant' => null,
                            'invoices' => null
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => $payments,
                    'payments' => $payments
                ]);

            } catch (\Exception $e) {
                \Log::error('Dashboard payments history error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'payments' => []
                ]);
            }
        }

        return $this->paymentHistory($request);
    }

    /**
     * Get available months for bulk payment (excluding already covered months)
     */
    private function getAvailableMonthsForBulkPayment(Property $property, $settings): array
    {
        $maxMonths = $settings->max_bulk_months ?? 12;
        $existingCoverage = $this->getExistingBulkCoverage($property);
        $coveredPeriods = $existingCoverage['covered_periods'];

        $availableMonths = [];
        $currentDate = now();

        for ($i = 0; $i < $maxMonths; $i++) {
            $date = $currentDate->copy()->addMonths($i);
            $period = $date->format('Y-m');

            $isCovered = in_array($period, $coveredPeriods);
            $isPast = $date->startOfMonth()->lt(now()->startOfMonth());

            $isPendingCoverage = false;
            foreach ($existingCoverage['pending_coverages'] as $pending) {
                if (in_array($period, $pending['periods'])) {
                    $isPendingCoverage = true;
                    break;
                }
            }

            $availableMonths[] = [
                'value' => $period,
                'year' => $date->format('Y'),
                'month' => $date->format('m'),
                'month_name' => $date->format('F'),
                'formatted' => $date->format('F Y'),
                'is_covered' => $isCovered,
                'is_pending_coverage' => $isPendingCoverage,
                'is_available' => !$isCovered && !$isPast,
                'disabled' => $isCovered || $isPast,
                'disabled_reason' => $isCovered
                    ? ($isPendingCoverage ? 'Already has a pending bulk payment waiting for payment' : 'Already covered by existing bulk payment')
                    : ($isPast ? 'Past months cannot be paid' : null)
            ];
        }

        return $availableMonths;
    }

    /**
     * Format coverage range for display
     */
    private function formatCoverageRange(array $periods): string
    {
        if (empty($periods)) {
            return '';
        }

        try {
            $dates = array_map(function($period) {
                return Carbon::parse($period . '-01');
            }, $periods);

            $first = min($dates);
            $last = max($dates);

            if ($first->format('Y-m') === $last->format('Y-m')) {
                return $first->format('F Y');
            }

            return $first->format('M Y') . ' - ' . $last->format('M Y');
        } catch (\Exception $e) {
            return implode(', ', $periods);
        }
    }

    /**
     * Process payment for a property
     * ✅ FIXED: Resets invoice statuses on failure
     */
    public function processPayment(Request $request)
    {
        $settings = SystemSetting::getSettings();

        $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
        $availableProviders = array_keys(array_filter($configurationStatus, function($status) {
            return $status['enabled'] && $status['configured'];
        }));

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'payment_provider' => 'required|in:' . implode(',', $availableProviders),
            'payment_type' => 'required|in:invoices,bulk',
            'amount' => 'required|numeric|min:0.01',
            'phone_number' => 'required_if:payment_provider,expresspay,hubtel,flutterwave|nullable|string',
            'email' => 'required_if:payment_provider,paystack|email',
            'description' => 'nullable|string|max:255',
            'pay_invoices' => 'nullable|array',
            'pay_invoices.*' => 'exists:invoices,id',
            'selected_months' => 'nullable|string',
        ], [
            'pay_invoices.*.exists' => 'One or more selected invoices are invalid.',
            'phone_number.required_if' => 'Your phone number is required to process the payment.',
            'email.required_if' => 'Email is required for Paystack payments.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        $validator->sometimes('selected_months', 'required|string', function ($input) {
            return $input->payment_type === 'bulk';
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $property = Property::find($request->property_id);

        if (auth()->id() !== $property->landlord_id) {
            return redirect()->back()
                ->with('error', 'Unauthorized. You can only make payments for your own properties.');
        }

        if ($request->payment_type === 'invoices') {
            $validationResult = $this->validateInvoiceSelection($request, $property->id);
            if (!$validationResult['valid']) {
                return redirect()->back()
                    ->with('error', $validationResult['message'])
                    ->withInput();
            }

            $maxAmount = Invoice::whereIn('id', $request->pay_invoices)
                ->where('status', '!=', 'paid')
                ->sum('total_amount');

            if ($request->amount > $maxAmount) {
                return redirect()->back()
                    ->with('error', 'Payment amount exceeds total due amount.')
                    ->withInput();
            }
        }

        if ($request->payment_type === 'bulk') {
            $validationResult = $this->validateBulkPaymentWithCoverage($request, $property);
            if (!$validationResult['valid']) {
                return redirect()->back()
                    ->with('error', $validationResult['message'])
                    ->withInput();
            }
        }

        $provider = $request->payment_provider;
        if (!$configurationStatus[$provider]['enabled'] || !$configurationStatus[$provider]['configured']) {
            return back()->withErrors([
                'payment_provider' => 'This payment provider is not properly configured. Please contact administrator.'
            ])->withInput();
        }

        try {
            $paymentData = [
                'property_id' => $request->property_id,
                'payment_provider' => $provider,
                'amount' => $request->amount,
                'currency' => $settings->currency_code,
                'description' => $request->description ?? $this->generatePaymentDescription($request, $property),
                'metadata' => [
                    'invoices' => $request->pay_invoices ?? [],
                    'property_name' => $property->property_name ?? $property->street_name,
                    'landlord_id' => auth()->id(),
                    'payment_type' => $request->payment_type,
                    'selected_months' => $request->selected_months ?? null,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                    'system_currency' => $settings->currency_code,
                    'formatted_amount' => $settings->formatAmount($request->amount),
                ]
            ];

            if (in_array($provider, ['expresspay', 'hubtel', 'flutterwave'])) {
                $paymentData['phone_number'] = $request->phone_number;
            }

            if ($provider === 'paystack') {
                $paymentData['email'] = $request->email;
            }

            $user = auth()->user();

            \Log::info('Payment initiated', [
                'landlord_id' => auth()->id(),
                'property_id' => $request->property_id,
                'amount' => $request->amount,
                'provider' => $provider,
                'customer_phone' => $request->phone_number ?? null,
                'initiated_at' => now()->toISOString()
            ]);

            $result = $this->paymentService->processPayment($paymentData, $user);

            if ($result['success']) {
                if ($request->payment_type === 'invoices' && isset($result['transaction_id'])) {
                    $payment = Payment::where('transaction_id', $result['transaction_id'])->first();
                    if ($payment && !empty($request->pay_invoices)) {
                        $currentMetadata = $payment->metadata ?? [];

                        if (is_string($currentMetadata)) {
                            $currentMetadata = json_decode($currentMetadata, true) ?? [];
                        }

                        $updatedMetadata = array_merge($currentMetadata, [
                            'invoices' => $request->pay_invoices,
                            'payment_type' => 'invoices'
                        ]);

                        $payment->update([
                            'metadata' => $updatedMetadata
                        ]);

                        Invoice::whereIn('id', $request->pay_invoices)
                            ->where('status', '!=', 'paid')
                            ->update(['status' => 'processing']);
                    }
                }

                if ($request->payment_type === 'bulk' && isset($result['transaction_id']) && $request->selected_months) {
                    $this->createBulkPaymentInvoices($request, $property, $result['transaction_id']);
                }

                if (isset($result['redirect_url'])) {
                    return redirect()->away($result['redirect_url'])
                        ->with('info', $result['message'] ?? 'Redirecting to payment gateway...');
                } else {
                    return redirect()->route('landlord.payments.confirmation', [
                        'transactionId' => $result['transaction_id']
                    ])->with([
                        'success' => $result['message'],
                        'instructions' => $result['instructions'] ?? null,
                        'provider' => $provider,
                    ]);
                }
            }

            if (!empty($request->pay_invoices)) {
                $this->resetInvoiceStatusesForInvoices($request->pay_invoices);
            }

            return redirect()->back()
                ->with('error', $result['message'] ?? 'Payment processing failed')
                ->withInput();

        } catch (\Exception $e) {
            \Log::error('Payment processing error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'property_id' => $request->property_id,
                'provider' => $request->payment_provider,
                'payment_type' => $request->payment_type
            ]);

            if (!empty($request->pay_invoices)) {
                $this->resetInvoiceStatusesForInvoices($request->pay_invoices);
            }

            return redirect()->back()
                ->with('error', 'Payment processing failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Validate bulk payment request with coverage awareness
     */
    protected function validateBulkPaymentWithCoverage($request, $property)
    {
        $settings = SystemSetting::getSettings();

        if (!$settings->enable_bulk_payments) {
            return ['valid' => false, 'message' => 'Bulk payments are currently disabled.'];
        }

        if (empty($request->selected_months)) {
            return ['valid' => false, 'message' => "Please select at least 1 month for bulk payment."];
        }

        $selectedMonths = explode(',', $request->selected_months);
        $monthsCount = count($selectedMonths);

        if ($monthsCount < 1) {
            return ['valid' => false, 'message' => "Please select at least 1 month for bulk payment."];
        }

        if ($monthsCount > $settings->max_bulk_months) {
            return ['valid' => false, 'message' => "Number of months cannot exceed {$settings->max_bulk_months}."];
        }

        if (!$this->areMonthsConsecutive($selectedMonths)) {
            return ['valid' => false, 'message' => "Months must be selected consecutively (e.g., April, May, June). Please select a continuous range of months."];
        }

        $existingCoverage = $this->getExistingBulkCoverage($property);
        $coveredMonths = array_intersect($selectedMonths, $existingCoverage['covered_periods']);

        if (!empty($coveredMonths)) {
            $coveredList = implode(', ', array_map(function($month) {
                return Carbon::parse($month . '-01')->format('F Y');
            }, $coveredMonths));

            return ['valid' => false, 'message' => "The following months are already covered by existing bulk payments: {$coveredList}. Please remove them from your selection."];
        }

        $monthlyDues = $settings->calculateDues($property) ?? 0;
        $subtotal = $monthlyDues * $monthsCount;

        // Apply bulk discount if configured (must match the client-side calculation)
        $discountPercentage = $settings->bulk_payment_discount ?? 0;
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $expectedAmount = $subtotal - $discountAmount;

        if (abs($expectedAmount - $request->amount) > 0.01) {
            return ['valid' => false, 'message' => 'Payment amount does not match the calculated bulk payment amount.'];
        }

        return ['valid' => true];
    }

    /**
     * Check if months are consecutive
     */
    private function areMonthsConsecutive(array $months): bool
    {
        if (count($months) <= 1) {
            return true;
        }

        sort($months);

        for ($i = 0; $i < count($months) - 1; $i++) {
            $current = Carbon::parse($months[$i] . '-01');
            $next = Carbon::parse($months[$i + 1] . '-01');

            if ($current->addMonth()->format('Y-m') !== $next->format('Y-m')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create bulk payment invoices
     */
    private function createBulkPaymentInvoices(Request $request, Property $property, string $transactionId)
    {
        try {
            $settings = SystemSetting::getSettings();
            $selectedMonths = explode(',', $request->selected_months);
            $monthlyAmount = $settings->calculateDues($property) ?? 0;

            sort($selectedMonths);
            $startMonth = $selectedMonths[0];
            $endMonth = $selectedMonths[count($selectedMonths) - 1];

            $existingInvoices = [];
            $monthsToCreate = [];

            foreach ($selectedMonths as $month) {
                $existingInvoice = Invoice::where('property_id', $property->id)
                    ->where('period', $month)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingInvoice) {
                    $existingInvoices[] = $existingInvoice;
                } else {
                    $monthsToCreate[] = $month;
                }
            }

            $bulkInvoice = Invoice::create([
                'property_id' => $property->id,
                'amount' => $request->amount,
                'period' => 'bulk-' . now()->format('Y-m-d'),
                'due_date' => now()->addDays(30),
                'status' => 'processing',
                'payment_reference' => $transactionId,
                'payment_method' => $request->payment_provider,
                'is_bulk_payment' => true,
                'bulk_months' => count($selectedMonths),
                'bulk_start_month' => $startMonth,
                'bulk_end_month' => $endMonth,
                'description' => 'Bulk payment for ' . count($selectedMonths) . ' months (' .
                                 Carbon::parse($startMonth . '-01')->format('M Y') . ' - ' .
                                 Carbon::parse($endMonth . '-01')->format('M Y') . ')',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'covers_periods' => json_encode($selectedMonths),
                'bulk_coverage_start' => $startMonth,
                'bulk_coverage_end' => $endMonth,
                'notes' => "📦 Bulk payment created for " . count($selectedMonths) . " months"
            ]);

            foreach ($existingInvoices as $existingInvoice) {
                $existingInvoice->update([
                    'bulk_payment_id' => $bulkInvoice->id,
                    'bulk_payment_reference' => $bulkInvoice->id . '-BULK',
                    'status' => 'consolidated',
                    'payment_date' => null,
                    'payment_method' => null,
                    'payment_reference' => null,
                    'notes' => ($existingInvoice->notes ? $existingInvoice->notes . "\n" : '') .
                               "📦 Consolidated into bulk payment #{$bulkInvoice->id} on " . now()->format('Y-m-d')
                ]);
            }

            foreach ($monthsToCreate as $month) {
                [$year, $monthNum] = explode('-', $month);

                Invoice::create([
                    'property_id' => $property->id,
                    'amount' => $monthlyAmount,
                    'period' => $year . '-' . str_pad($monthNum, 2, '0', STR_PAD_LEFT),
                    'due_date' => Carbon::create($year, $monthNum, 1)->endOfMonth(),
                    'status' => 'consolidated',
                    'bulk_payment_id' => $bulkInvoice->id,
                    'bulk_payment_reference' => $bulkInvoice->id . '-BULK',
                    'description' => 'Monthly service charge for ' .
                        Carbon::create($year, $monthNum, 1)->format('F Y'),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'notes' => "📦 Part of bulk payment #{$bulkInvoice->id}"
                ]);
            }

            \Log::info('Bulk payment invoices created', [
                'bulk_invoice_id' => $bulkInvoice->id,
                'transaction_id' => $transactionId,
                'months_count' => count($selectedMonths),
                'existing_invoices_consolidated' => count($existingInvoices),
                'new_invoices_created' => count($monthsToCreate)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating bulk payment invoices: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'property_id' => $property->id
            ]);
        }
    }

    /**
     * Display payment confirmation page
     */
    public function showConfirmation($transactionId)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)
                ->where('landlord_id', auth()->id())
                ->firstOrFail();

            $settings = SystemSetting::getSettings();

            $statusResult = $this->paymentService->getPaymentStatus($transactionId);
            if ($statusResult['success'] && $statusResult['status'] !== $payment->status) {
                $payment->update(['status' => $statusResult['status']]);
                $payment->refresh();
            }

            $instructions = session('instructions');
            $successMessage = session('success');
            $providerInstructions = $this->paymentService->getPaymentInstructions($payment->payment_provider);

            $requiresVerification = in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave'])
                && $payment->status === 'pending';

            return view('landlord.payments.confirmation', compact(
                'payment', 'instructions', 'providerInstructions',
                'successMessage', 'requiresVerification', 'settings'
            ));

        } catch (\Exception $e) {
            \Log::error('Payment confirmation error: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Payment confirmation not found.');
        }
    }

    /**
     * Handle payment callback from providers
     */
    public function handleCallback(Request $request, $provider)
    {
        try {
            \Log::info("Payment callback received", [
                'provider' => $provider,
                'params' => $request->all(),
                'ip' => $request->ip()
            ]);

            $validProviders = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            if (!in_array($provider, $validProviders)) {
                \Log::warning("Invalid provider in callback: {$provider}");
                return response()->json(['error' => 'Invalid provider'], 400);
            }

            $result = $this->paymentService->handlePaymentCallback($provider, $request->all());

            if ($result['success']) {
                if ($result['status'] === 'completed') {
                    $this->updateInvoiceStatuses($result['transaction_id']);
                } else {
                    if (isset($result['transaction_id'])) {
                        $this->resetInvoiceStatuses($result['transaction_id']);
                    } else {
                        $transactionId = $request->input('transaction_id') ?? $request->input('tx_ref');
                        if ($transactionId) {
                            $this->resetInvoiceStatuses($transactionId);
                        }
                    }
                }

                \Log::info("Callback processed successfully", [
                    'transaction_id' => $result['transaction_id'] ?? 'unknown',
                    'provider' => $provider,
                    'status' => $result['status'] ?? 'unknown'
                ]);

                return redirect()->route('landlord.payments.confirmation', [
                    'transactionId' => $result['transaction_id'] ?? $request->input('transaction_id')
                ])->with('success', $result['message']);
            } else {
                \Log::error("Callback processing failed", [
                    'provider' => $provider,
                    'error' => $result['message']
                ]);

                $transactionId = $result['transaction_id'] ?? $request->input('transaction_id') ?? $request->input('tx_ref');
                if ($transactionId) {
                    $this->resetInvoiceStatuses($transactionId);
                }

                $propertyId = $request->input('property_id') ?? $request->input('metadata.property_id');

                if ($propertyId) {
                    return redirect()->route('landlord.payments.create', $propertyId)
                        ->with('error', $result['message']);
                }

                return redirect()->route('dashboard')
                    ->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            \Log::error('Payment callback error: ' . $e->getMessage(), [
                'provider' => $provider,
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            $transactionId = $request->input('transaction_id') ?? $request->input('tx_ref');
            if ($transactionId) {
                try {
                    $this->resetInvoiceStatuses($transactionId);
                } catch (\Exception $inner) {
                    \Log::error('Failed to reset invoice statuses after callback exception: ' . $inner->getMessage());
                }
            }

            return redirect()->route('dashboard')
                ->with('error', 'Payment callback processing failed.');
        }
    }

    /**
     * Update invoice statuses when payment is completed
     */
    private function updateInvoiceStatuses($transactionId)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (!$payment) {
                return;
            }

            $metadata = $payment->metadata ?? [];
            $invoiceIds = $metadata['invoices'] ?? [];

            if (!empty($invoiceIds)) {
                Invoice::whereIn('id', $invoiceIds)
                    ->update([
                        'status' => 'paid',
                        'payment_date' => now(),
                        'payment_method' => $payment->payment_provider,
                        'payment_reference' => $payment->transaction_reference
                    ]);

                \Log::info("Invoices status updated after payment", [
                    'payment_id' => $payment->id,
                    'transaction_id' => $transactionId,
                    'invoice_ids' => $invoiceIds
                ]);
            }

            if (isset($metadata['payment_type']) && $metadata['payment_type'] === 'bulk') {
                $this->updateBulkPaymentInvoices($transactionId);
            }

        } catch (\Exception $e) {
            \Log::error('Error updating invoice statuses: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
        }
    }

    /**
     * Update bulk payment invoices
     */
    private function updateBulkPaymentInvoices($transactionId)
    {
        try {
            $bulkInvoices = Invoice::where('payment_reference', $transactionId)
                ->where('is_bulk_payment', true)
                ->get();

            foreach ($bulkInvoices as $bulkInvoice) {
                $bulkInvoice->update([
                    'status' => 'paid',
                    'payment_date' => now(),
                    'payment_method' => 'bulk_payment',
                    'payment_reference' => $transactionId,
                    'notes' => ($bulkInvoice->notes ? $bulkInvoice->notes . "\n" : '') .
                               "✅ Bulk payment completed on " . now()->format('Y-m-d H:i:s')
                ]);

                Invoice::where('bulk_payment_id', $bulkInvoice->id)
                    ->update([
                        'payment_date' => null,
                        'payment_method' => null,
                        'payment_reference' => $transactionId . '-CONSOLIDATED',
                        'notes' => DB::raw("CONCAT(IFNULL(notes, ''), '\n✅ Covered by bulk payment #{$bulkInvoice->id} on " . now()->format('Y-m-d') . "')")
                    ]);

                \Log::info('Bulk payment invoices updated', [
                    'bulk_invoice_id' => $bulkInvoice->id,
                    'transaction_id' => $transactionId
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error updating bulk payment invoices: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
        }
    }

    /**
     * Verify payment (for mobile money providers)
     */
    public function verifyPayment(Request $request)
    {
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

        $result = $this->paymentService->verifyPayment(
            $request->transaction_id,
            $request->verification_code,
            $request->provider
        );

        if ($result['success']) {
            if ($result['status'] === 'completed') {
                $this->updateInvoiceStatuses($request->transaction_id);
            } else {
                $this->resetInvoiceStatuses($request->transaction_id);
            }

            return redirect()->route('landlord.payments.confirmation', [
                'transactionId' => $request->transaction_id
            ])->with('success', $result['message']);
        }

        $this->resetInvoiceStatuses($request->transaction_id);

        return redirect()->back()
            ->with('error', $result['message'])
            ->withInput();
    }

    /**
     * Show verification form for mobile money payments
     */
    public function showVerificationForm($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)
                         ->where('landlord_id', auth()->id())
                         ->firstOrFail();

        if (!in_array($payment->payment_provider, ['expresspay', 'hubtel', 'flutterwave']) ||
            $payment->status !== 'pending') {
            return redirect()->route('landlord.payments.confirmation', [
                'transactionId' => $transactionId
            ])->with('info', 'Payment does not require verification');
        }

        return view('landlord.payments.verify', compact('payment'));
    }

    /**
     * Check payment status via AJAX
     */
    public function checkPaymentStatus($transactionId)
    {
        try {
            \Log::info('Controller: Checking payment status', ['transaction_id' => $transactionId]);

            $result = $this->paymentService->getPaymentStatus($transactionId);

            if ($result['success'] && isset($result['status'])) {
                $payment = Payment::where('transaction_id', $transactionId)->first();
                if ($payment && $payment->status !== $result['status']) {
                    $updateData = ['status' => $result['status']];

                    if ($result['status'] === Payment::STATUS_COMPLETED && !$payment->payment_date) {
                        $updateData['payment_date'] = now();
                        $this->updateInvoiceStatuses($transactionId);
                    } elseif ($result['status'] === Payment::STATUS_FAILED || $result['status'] === Payment::STATUS_CANCELLED) {
                        $this->resetInvoiceStatuses($transactionId);
                    }

                    $payment->update($updateData);

                    \Log::info('Controller: Updated payment status', [
                        'transaction_id' => $transactionId,
                        'old_status' => $payment->status,
                        'new_status' => $result['status']
                    ]);
                }
            }

            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('Controller: Payment status check error: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display payment history for landlord
     */
    public function paymentHistory(Request $request)
    {
        $query = Payment::with('property')
            ->where('landlord_id', auth()->id());

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('property_id') && $request->property_id) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('payment_provider') && $request->payment_provider) {
            $query->where('payment_provider', $request->payment_provider);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('transaction_reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('property', function($q) use ($search) {
                      $q->where('property_name', 'like', "%{$search}%");
                  });
            });
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSortFields = ['created_at', 'payment_date', 'amount', 'status'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $payments = $query->paginate(20);
        $settings = SystemSetting::getSettings();

        $completedPaymentsTotal = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount') ?? 0.00;

        $pendingCount = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_PENDING)
            ->count();

        $processingCount = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_PROCESSING)
            ->count();

        $failedCount = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_FAILED)
            ->count();

        $cancelledCount = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_CANCELLED)
            ->count();

        $lastPayment = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereNotNull('payment_date')
            ->latest('payment_date')
            ->first();

        $properties = Property::where('landlord_id', auth()->id())
            ->orderBy('property_name')
            ->get();

        $paymentProviders = Payment::where('landlord_id', auth()->id())
            ->select('payment_provider')
            ->distinct()
            ->pluck('payment_provider')
            ->mapWithKeys(function($provider) {
                $displayName = match($provider) {
                    Payment::PROVIDER_EXPRESSPAY => 'ExpressPay',
                    Payment::PROVIDER_HUBTEL => 'Hubtel',
                    Payment::PROVIDER_PAYSTACK => 'Paystack',
                    Payment::PROVIDER_FLUTTERWAVE => 'Flutterwave',
                    Payment::PROVIDER_MANUAL => 'Manual',
                    default => ucfirst(str_replace('_', ' ', $provider))
                };
                return [$provider => $displayName];
            })
            ->toArray();

        $statuses = [
            Payment::STATUS_PENDING => 'Pending',
            Payment::STATUS_PROCESSING => 'Processing',
            Payment::STATUS_COMPLETED => 'Completed',
            Payment::STATUS_FAILED => 'Failed',
            Payment::STATUS_CANCELLED => 'Cancelled',
            Payment::STATUS_REFUNDED => 'Refunded',
            Payment::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
        ];

        $monthlyTotals = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function($item) use ($settings) {
                $item->month_name = Carbon::create($item->year, $item->month, 1)->format('M Y');
                $item->formatted_total = $settings->formatAmount($item->total ?? 0.00);
                return $item;
            });

        $overduePayments = Payment::where('landlord_id', auth()->id())
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->where('created_at', '<=', now()->subHours(24))
            ->count();

        $expiringSoonCount = Payment::where('landlord_id', auth()->id())
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->whereHas('invoices', function($query) {
                $query->whereDate('due_date', '>=', now())
                      ->whereDate('due_date', '<=', now()->addDays(7))
                      ->where('status', '!=', 'paid');
            })
            ->count();

        $averagePayment = Payment::where('landlord_id', auth()->id())
            ->where('status', Payment::STATUS_COMPLETED)
            ->avg('amount') ?? 0.00;

        return view('landlord.payments.history', compact(
            'payments', 'settings', 'completedPaymentsTotal', 'pendingCount',
            'processingCount', 'failedCount', 'cancelledCount', 'lastPayment',
            'properties', 'paymentProviders', 'statuses', 'monthlyTotals',
            'overduePayments', 'expiringSoonCount', 'averagePayment', 'request'
        ));
    }

    /**
     * Get payment details API
     */
    public function getPaymentDetails($paymentId)
    {
        try {
            $payment = Payment::with(['property', 'invoices'])
                ->where('landlord_id', auth()->id())
                ->findOrFail($paymentId);

            return response()->json([
                'success' => true,
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }
    }

    /**
     * Cancel a payment (landlord-facing)
     */
    public function cancelPayment($transactionId)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)
                            ->where('landlord_id', auth()->id())
                            ->firstOrFail();

            if ($payment->status === 'completed') {
                return redirect()->back()
                    ->with('error', 'Cannot cancel completed payment');
            }

            $result = $this->paymentService->cancelPayment($transactionId);

            if ($result['success']) {
                $this->resetInvoiceStatuses($transactionId);

                return redirect()->route('landlord.payments.history')
                    ->with('success', $result['message']);
            }

            return redirect()->back()
                ->with('error', $result['message']);

        } catch (\Exception $e) {
            \Log::error('Payment cancellation error: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel payment: ' . $e->getMessage());
        }
    }

    /**
     * Reset invoice statuses when payment is cancelled or failed
     */
    private function resetInvoiceStatuses($transactionId)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            $invoiceIds = [];
            $paymentType = 'invoices';

            if ($payment) {
                $metadata = $payment->metadata ?? [];
                $invoiceIds = $metadata['invoices'] ?? [];
                $paymentType = $metadata['payment_type'] ?? 'invoices';
            } else {
                $invoices = Invoice::where('payment_reference', $transactionId)
                    ->where('status', 'processing')
                    ->get();

                if ($invoices->isNotEmpty()) {
                    $invoiceIds = $invoices->pluck('id')->toArray();
                    \Log::info('Found invoices with payment_reference', [
                        'transaction_id' => $transactionId,
                        'invoice_ids' => $invoiceIds
                    ]);
                }
            }

            if (!empty($invoiceIds)) {
                if ($paymentType === 'bulk') {
                    $bulkInvoice = Invoice::where('payment_reference', $transactionId)
                        ->where('is_bulk_payment', true)
                        ->where('status', 'processing')
                        ->first();

                    if ($bulkInvoice) {
                        $bulkInvoice->update([
                            'status' => 'pending',
                            'payment_date' => null,
                            'payment_method' => null,
                            'payment_reference' => null,
                            'notes' => ($bulkInvoice->notes ? $bulkInvoice->notes . "\n" : '') .
                                       "🔄 Bulk payment was cancelled/failed on " . now()->format('Y-m-d H:i:s')
                        ]);

                        Invoice::where('bulk_payment_id', $bulkInvoice->id)
                            ->where('status', 'consolidated')
                            ->update([
                                'status' => 'pending',
                                'bulk_payment_id' => null,
                                'bulk_payment_reference' => null,
                                'payment_date' => null,
                                'payment_method' => null,
                                'payment_reference' => null,
                                'notes' => DB::raw("CONCAT(IFNULL(notes, ''), '\n🔄 Bulk payment was cancelled/failed on " . now()->format('Y-m-d') . "')")
                            ]);

                        \Log::info("Bulk invoice and children reset after cancellation/failure", [
                            'transaction_id' => $transactionId,
                            'bulk_invoice_id' => $bulkInvoice->id
                        ]);
                    } else {
                        Invoice::whereIn('id', $invoiceIds)
                            ->where('status', 'processing')
                            ->update([
                                'status' => 'pending',
                                'payment_date' => null,
                                'payment_method' => null,
                                'payment_reference' => null
                            ]);

                        \Log::info("Invoices status reset after payment cancellation/failure (bulk - fallback)", [
                            'transaction_id' => $transactionId,
                            'invoice_ids' => $invoiceIds
                        ]);
                    }
                } else {
                    $updated = Invoice::whereIn('id', $invoiceIds)
                        ->where('status', 'processing')
                        ->update([
                            'status' => 'pending',
                            'payment_date' => null,
                            'payment_method' => null,
                            'payment_reference' => null
                        ]);

                    \Log::info("Invoices status reset after payment cancellation/failure", [
                        'transaction_id' => $transactionId,
                        'invoice_ids' => $invoiceIds,
                        'updated_count' => $updated
                    ]);
                }
            } else {
                \Log::warning('No invoice IDs found to reset', [
                    'transaction_id' => $transactionId,
                    'payment_exists' => $payment ? true : false
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error resetting invoice statuses: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
        }
    }

    /**
     * Reset invoice statuses for a list of invoice IDs
     */
    private function resetInvoiceStatusesForInvoices(array $invoiceIds)
    {
        try {
            if (empty($invoiceIds)) {
                return;
            }

            $updated = Invoice::whereIn('id', $invoiceIds)
                ->where('status', 'processing')
                ->update([
                    'status' => 'pending',
                    'payment_date' => null,
                    'payment_method' => null,
                    'payment_reference' => null
                ]);

            if ($updated > 0) {
                \Log::info("Reset invoice statuses for invoices", [
                    'invoice_ids' => $invoiceIds,
                    'count' => $updated
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error resetting invoice statuses for invoices: ' . $e->getMessage(), [
                'invoice_ids' => $invoiceIds
            ]);
        }
    }

    /**
     * Webhook handler for payment providers
     */
    public function handleWebhook(Request $request, $provider)
    {
        try {
            \Log::info("Webhook received from {$provider}", [
                'headers' => $request->headers->all(),
                'payload' => $request->getContent(),
                'ip' => $request->ip()
            ]);

            $result = $this->paymentService->handleWebhook($provider, $request);

            if ($result['success']) {
                if ($result['status'] === 'completed' && isset($result['transaction_id'])) {
                    $this->updateInvoiceStatuses($result['transaction_id']);
                } elseif (isset($result['transaction_id']) && in_array($result['status'], ['failed', 'cancelled'])) {
                    $this->resetInvoiceStatuses($result['transaction_id']);
                }

                \Log::info("Webhook processed successfully", ['provider' => $provider]);
                return response()->json([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                \Log::error("Webhook processing failed", [
                    'provider' => $provider,
                    'error' => $result['message']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

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
     * Test payment provider connection (Admin only)
     */
    public function testConnection($provider)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validProviders = ['expresspay', 'hubtel', 'paystack', 'flutterwave'];
            if (!in_array($provider, $validProviders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection test not available for this provider'
                ]);
            }

            $result = $this->paymentService->testProviderConnection($provider);
            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('Connection test error: ' . $e->getMessage(), [
                'provider' => $provider,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ================================================================
    //  ADMIN PAYMENT MANAGEMENT
    // ================================================================

    /**
     * Admin payment index
     */
    public function index(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized. Only administrators can view all payments.');
        }

        $query = Payment::with(['property', 'landlord', 'invoices']);

        if ($request->has('landlord_id') && $request->landlord_id) {
            $query->where('landlord_id', $request->landlord_id);
        }

        if ($request->has('property_id') && $request->property_id) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('payment_provider') && $request->payment_provider) {
            $query->where('payment_provider', $request->payment_provider);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('transaction_reference', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('landlord', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('property', function($q) use ($search) {
                      $q->where('property_name', 'like', "%{$search}%")
                        ->orWhere('digital_address', 'like', "%{$search}%")
                        ->orWhere('street_name', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('house_number', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(25);

        $landlords = User::where(function($q) {
            $q->where('type', User::TYPE_LANDLORD)
              ->orWhereHas('roles', function($roleQ) {
                  $roleQ->where('slug', 'landlord');
              });
        })->get();

        $properties = Property::all();

        $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
        $paymentProviders = [];
        foreach ($configurationStatus as $provider => $status) {
            $paymentProviders[$provider] = $this->getProviderDisplayName($provider);
        }

        $settings = SystemSetting::getSettings();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $todayRevenue = Payment::where('status', 'completed')
            ->whereDate('payment_date', today())
            ->sum('amount');
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $totalPayments = Payment::count();
        $pendingPayments = Payment::where('status', 'pending')->count();

        return view('admin.payments.index', compact(
            'payments', 'landlords', 'properties', 'paymentProviders',
            'totalRevenue', 'todayRevenue', 'monthlyRevenue', 'totalPayments',
            'pendingPayments', 'settings'
        ));
    }

    /**
     * Show payment details for admin
     */
    public function showAdmin($id)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized. Only administrators can view payment details.');
        }

        $payment = Payment::with(['property', 'landlord', 'invoices'])->findOrFail($id);
        $settings = SystemSetting::getSettings();

        return view('admin.payments.show', compact('payment', 'settings'));
    }

    /**
     * ✅ UPDATED: Update payment status (admin only)
     *
     * Handles:
     *   - The status dropdown on the admin payment detail page
     *   - The "Cancel Payment" button (submits status=cancelled)
     *   - The "Mark as Refunded" button (submits status=refunded)
     *   - The "Force Complete Payment" form (submits status=completed)
     *
     * Side effects:
     *   - status=completed  → marks related invoices as paid
     *   - status=cancelled  → resets related invoices to pending
     *   - status=failed     → resets related invoices to pending
     *   - status=refunded   → stamps refunded_at (if column exists) and resets invoices
     */
    public function updateStatus(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->updateStatusResponse(
                $request,
                false,
                'Unauthorized. Only administrators can update payment status.',
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,completed,failed,refunded,cancelled',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $payment = Payment::findOrFail($id);
        $oldStatus = $payment->status;

        // Guard against destructive transitions
        if ($oldStatus === 'completed' && in_array($request->status, ['pending', 'processing'], true)) {
            $msg = 'A completed payment cannot be set back to pending. Use "Refunded" instead.';
            return $this->updateStatusResponse($request, false, $msg, 422);
        }

        DB::beginTransaction();

        try {
            $updateData = ['status' => $request->status];

            if ($request->status === 'completed' && !$payment->payment_date) {
                $updateData['payment_date'] = now();
            }

            if ($request->status === 'refunded' && Schema::hasColumn('payments', 'refunded_at')) {
                $updateData['refunded_at'] = now();
            }

            $payment->update($updateData);

            // Sync related invoices
            if ($request->status === 'completed') {
                $this->updateInvoiceStatuses($payment->transaction_id);
            } elseif (in_array($request->status, ['failed', 'cancelled', 'refunded'], true)) {
                $this->resetInvoiceStatuses($payment->transaction_id);
            }

            DB::commit();

            \Log::info('Payment status updated by admin', [
                'payment_id'   => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'old_status'   => $oldStatus,
                'new_status'   => $request->status,
                'admin_id'     => auth()->id(),
            ]);

            return $this->updateStatusResponse(
                $request,
                true,
                'Payment status updated successfully!',
                200,
                $request->status
            );

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Failed to update payment status', [
                'payment_id' => $id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return $this->updateStatusResponse(
                $request,
                false,
                'Failed to update payment status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Standardized response for updateStatus (JSON or redirect).
     */
    private function updateStatusResponse(Request $request, bool $success, string $message, int $status = 200, ?string $newStatus = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'status'  => $newStatus,
            ], $status);
        }

        return $success
            ? redirect()->back()->with('success', $message)
            : redirect()->back()->with('error', $message);
    }

    /**
     * ✅ NEW: Confirm a bank transfer payment (admin only).
     */
    public function confirmBankTransfer(Request $request, $transactionId)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        try {
            DB::beginTransaction();

            $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

            if ($payment->status === 'completed') {
                DB::rollBack();
                return redirect()->back()->with('info', 'Payment is already completed.');
            }

            $payment->update([
                'status'       => 'completed',
                'payment_date' => $payment->payment_date ?? now(),
                'updated_by'   => auth()->id(),
            ]);

            $this->updateInvoiceStatuses($payment->transaction_id);

            DB::commit();

            \Log::info('Bank transfer confirmed by admin', [
                'payment_id'     => $payment->id,
                'transaction_id' => $transactionId,
                'admin_id'       => auth()->id(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank transfer confirmed successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Bank transfer confirmed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to confirm bank transfer: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to confirm bank transfer: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to confirm bank transfer.');
        }
    }

    /**
     * ✅ NEW: Mark a cash payment as collected (admin only).
     */
    public function markCashCollected(Request $request, $transactionId)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        try {
            DB::beginTransaction();

            $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

            if ($payment->status === 'completed') {
                DB::rollBack();
                return redirect()->back()->with('info', 'Payment is already completed.');
            }

            $payment->update([
                'status'         => 'completed',
                'payment_date'   => $payment->payment_date ?? now(),
                'payment_method' => $payment->payment_method ?? 'cash',
                'updated_by'     => auth()->id(),
            ]);

            $this->updateInvoiceStatuses($payment->transaction_id);

            DB::commit();

            \Log::info('Cash payment marked as collected', [
                'payment_id'     => $payment->id,
                'transaction_id' => $transactionId,
                'admin_id'       => auth()->id(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cash payment marked as collected!',
                ]);
            }

            return redirect()->back()->with('success', 'Cash payment marked as collected!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to mark cash collected: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark cash collected: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to mark cash collected.');
        }
    }

    /**
     * ✅ NEW: Resend verification to the payer (admin only).
     */
    public function resendVerification(Request $request, $transactionId)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        try {
            $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

            $result = $this->paymentService->resendVerification($payment);

            if ($result['success'] ?? false) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $result['message'] ?? 'Verification resent successfully.',
                    ]);
                }
                return redirect()->back()->with('success', $result['message'] ?? 'Verification resent successfully.');
            }

            $message = $result['message'] ?? 'Failed to resend verification.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message);

        } catch (\Exception $e) {
            \Log::error('Failed to resend verification: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resend verification: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to resend verification.');
        }
    }

    /**
     * ✅ NEW: Force-verify a pending payment (admin only).
     */
    public function forceVerifyPayment(Request $request, $transactionId)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

            if ($payment->status === 'completed') {
                DB::rollBack();
                return redirect()->back()->with('info', 'Payment is already completed.');
            }

            $payment->update([
                'status'       => 'completed',
                'payment_date' => $payment->payment_date ?? now(),
                'updated_by'   => auth()->id(),
                'notes'        => trim(($payment->notes ?? '') . "\n✅ Force-verified by admin on " . now()->format('Y-m-d H:i:s')
                                . ($request->filled('reason') ? ' | Reason: ' . $request->reason : '')),
            ]);

            $this->updateInvoiceStatuses($payment->transaction_id);

            DB::commit();

            \Log::info('Payment force-verified by admin', [
                'payment_id'     => $payment->id,
                'transaction_id' => $transactionId,
                'admin_id'       => auth()->id(),
                'reason'         => $request->reason,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment force-verified successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Payment force-verified successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to force-verify payment: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to force-verify payment: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to force-verify payment.');
        }
    }

    /**
     * Export payments to CSV
     */
    public function export(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'Unauthorized. Only administrators can export payments.');
        }

        $query = Payment::with(['property', 'landlord']);

        if ($request->has('landlord_id') && $request->landlord_id) {
            $query->where('landlord_id', $request->landlord_id);
        }

        if ($request->has('property_id') && $request->property_id) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->orderBy('created_at', 'desc')->get();
        $settings = SystemSetting::getSettings();

        $fileName = 'payments_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($payments, $settings) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Transaction ID', 'Date', 'Landlord', 'Landlord Email', 'Property',
                'Amount', 'Currency', 'Formatted Amount', 'Payment Provider',
                'Status', 'Reference', 'Notes'
            ]);

            foreach ($payments as $payment) {
                $propertyName = $payment->property
                    ? ($payment->property->property_name ?? $payment->property->street_name ?? 'N/A')
                    : 'N/A';

                fputcsv($file, [
                    $payment->transaction_id,
                    $payment->payment_date
                        ? $payment->payment_date->format('Y-m-d H:i:s')
                        : ($payment->created_at ? $payment->created_at->format('Y-m-d H:i:s') : 'N/A'),
                    $payment->landlord->name ?? 'N/A',
                    $payment->landlord->email ?? 'N/A',
                    $propertyName,
                    $payment->amount,
                    $payment->currency,
                    $settings->formatAmount($payment->amount),
                    $this->getProviderDisplayName($payment->payment_provider),
                    ucfirst($payment->status),
                    $payment->transaction_reference ?? 'N/A',
                    $payment->notes ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get payment statistics for dashboard
     */
    public function getStatistics()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::getSettings();

        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $todayRevenue = Payment::where('status', 'completed')
            ->whereDate('payment_date', today())
            ->sum('amount');
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $totalPayments = Payment::count();
        $pendingPayments = Payment::where('status', 'pending')->count();

        $revenueByProvider = Payment::where('status', 'completed')
            ->selectRaw('payment_provider, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_provider')
            ->get()
            ->mapWithKeys(function($item) use ($settings) {
                return [
                    $item->payment_provider => [
                        'total' => $item->total,
                        'formatted_total' => $settings->formatAmount($item->total),
                        'count' => $item->count
                    ]
                ];
            });

        $monthlyTrend = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function($item) use ($settings) {
                $item->formatted_total = $settings->formatAmount($item->total);
                return $item;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'formatted_total_revenue' => $settings->formatAmount($totalRevenue),
                'today_revenue' => $todayRevenue,
                'formatted_today_revenue' => $settings->formatAmount($todayRevenue),
                'monthly_revenue' => $monthlyRevenue,
                'formatted_monthly_revenue' => $settings->formatAmount($monthlyRevenue),
                'total_payments' => $totalPayments,
                'pending_payments' => $pendingPayments,
                'revenue_by_provider' => $revenueByProvider,
                'monthly_trend' => $monthlyTrend,
                'currency' => $settings->getCurrencyInfo()
            ]
        ]);
    }

    /**
     * Get payment instructions for a specific provider
     */
    public function getPaymentInstructions($provider)
    {
        try {
            $instructions = $this->paymentService->getPaymentInstructions($provider);

            return response()->json([
                'success' => true,
                'instructions' => $instructions
            ]);

        } catch (\Exception $e) {
            \Log::error('Get payment instructions error: ' . $e->getMessage(), [
                'provider' => $provider
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment instructions'
            ], 500);
        }
    }

    /**
     * Check payment method configuration
     */
    public function checkConfiguration()
    {
        try {
            $configuration = $this->paymentService->checkPaymentMethodConfiguration();

            return response()->json([
                'success' => true,
                'configuration' => $configuration
            ]);

        } catch (\Exception $e) {
            \Log::error('Check configuration error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment configuration'
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========

    /**
     * Validate invoice selection for payment
     */
    protected function validateInvoiceSelection($request, $propertyId)
    {
        if (empty($request->pay_invoices)) {
            return ['valid' => false, 'message' => 'Please select at least one invoice to pay.'];
        }

        $invalidInvoices = Invoice::whereIn('id', $request->pay_invoices)
            ->where('property_id', '!=', $propertyId)
            ->exists();

        if ($invalidInvoices) {
            return ['valid' => false, 'message' => 'One or more selected invoices do not belong to this property.'];
        }

        $paidInvoices = Invoice::whereIn('id', $request->pay_invoices)
            ->where('status', 'paid')
            ->exists();

        if ($paidInvoices) {
            return ['valid' => false, 'message' => 'One or more selected invoices are already paid.'];
        }

        $selectedInvoicesTotal = Invoice::whereIn('id', $request->pay_invoices)
            ->sum('total_amount');

        if (abs($selectedInvoicesTotal - $request->amount) > 0.01) {
            return ['valid' => false, 'message' => 'Payment amount does not match the total of selected invoices.'];
        }

        return ['valid' => true];
    }

    /**
     * Generate appropriate payment description based on payment type
     */
    protected function generatePaymentDescription($request, $property)
    {
        $settings = SystemSetting::getSettings();
        $formattedAmount = $settings->formatAmount($request->amount);
        $propertyLabel = $property->property_name ?? $property->street_name ?? 'Property';

        switch ($request->payment_type) {
            case 'invoices':
                $invoiceCount = count($request->pay_invoices ?? []);
                return "Payment for {$invoiceCount} invoice(s) - {$propertyLabel} - {$formattedAmount}";

            case 'bulk':
                $monthsCount = !empty($request->selected_months)
                    ? count(explode(',', $request->selected_months))
                    : 1;
                return "Bulk payment for {$monthsCount} months - {$propertyLabel} - {$formattedAmount}";

            default:
                return "Payment for {$propertyLabel} - {$formattedAmount}";
        }
    }

    /**
     * Helper method to get provider display name
     */
    protected function getProviderDisplayName($providerKey)
    {
        $providers = [
            'expresspay' => 'ExpressPay',
            'hubtel' => 'Hubtel',
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave',
        ];

        return $providers[$providerKey] ?? ucfirst(str_replace('_', ' ', $providerKey));
    }

    /**
     * Handle ExpressPay webhook
     */
    public function handleExpressPayWebhook(Request $request)
    {
        \Log::info('ExpressPay Webhook received', $request->all());

        $result = $this->paymentService->handleWebhook('expresspay', $request);

        if ($result['success']) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }

    /**
     * Handle Hubtel callback
     */
    public function handleHubtelCallback(Request $request)
    {
        \Log::info('Hubtel Callback received', $request->all());

        $result = $this->paymentService->handlePaymentCallback('hubtel', $request->all());

        if ($result['success']) {
            return redirect()->route('landlord.payments.confirmation', [
                'transactionId' => $result['transaction_id']
            ])->with('success', $result['message']);
        }

        return redirect()->route('dashboard')->with('error', $result['message']);
    }

    /**
     * Handle Flutterwave callback
     */
    public function handleFlutterwaveCallback(Request $request)
    {
        \Log::info('Flutterwave Callback received', $request->all());

        $result = $this->paymentService->handlePaymentCallback('flutterwave', $request->all());

        if ($result['success']) {
            return redirect()->route('landlord.payments.confirmation', [
                'transactionId' => $result['transaction_id']
            ])->with('success', $result['message']);
        }

        return redirect()->route('dashboard')->with('error', $result['message']);
    }

    /**
     * Handle Paystack callback
     */
    public function handlePaystackCallback(Request $request)
    {
        \Log::info('Paystack Callback received', $request->all());

        $result = $this->paymentService->handlePaymentCallback('paystack', $request->all());

        if ($result['success']) {
            return redirect()->route('landlord.payments.confirmation', [
                'transactionId' => $result['transaction_id']
            ])->with('success', $result['message']);
        }

        return redirect()->route('dashboard')->with('error', $result['message']);
    }
}