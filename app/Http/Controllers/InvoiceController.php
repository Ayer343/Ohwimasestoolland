<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use App\Models\InvoiceArchive;
use App\Models\SystemSetting;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\NotificationService;
use App\Services\YearEndArchiveService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\NotifiesUsers;
use App\Traits\ChecksBillingAccess;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    use NotifiesUsers, ChecksBillingAccess;

    protected InvoiceService       $invoiceService;
    protected PaymentService       $paymentService;
    protected NotificationService  $notificationService;
    protected SmsService           $smsService;
    protected SmsTemplateService   $smsTemplateService;
    protected YearEndArchiveService $yearEndArchiveService;
    protected ?SystemSetting       $settings = null;

    public function __construct(
        InvoiceService $invoiceService,
        PaymentService $paymentService,
        NotificationService $notificationService,
        SmsService $smsService,
        SmsTemplateService $smsTemplateService,
        YearEndArchiveService $yearEndArchiveService
    ) {
        $this->invoiceService        = $invoiceService;
        $this->paymentService        = $paymentService;
        $this->notificationService   = $notificationService;
        $this->smsService            = $smsService;
        $this->smsTemplateService    = $smsTemplateService;
        $this->yearEndArchiveService = $yearEndArchiveService;
        $this->settings              = SystemSetting::getSettings();
    }

    /**
     * Always return a fresh settings instance rather than relying on
     * the snapshot taken at construction time.
     */
    protected function settings(): SystemSetting
    {
        return SystemSetting::getSettings();
    }

    /* ============================================================
     | INDEX / LISTING
     * ============================================================ */

    public function index(Request $request)
{
    if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
        return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
    }

    $filters = $request->only([
        'property_id', 'status', 'period', 'search', 'is_bulk_payment',
        'type', 'has_parent', 'is_bulk', 'has_penalty', 'has_coverage',
        'has_discount',
    ]);
    $user = auth()->user();

    // ✅ FIX: getInvoicesWithFilters() returns a query builder.
    //         Chain ->paginate() here and preserve query params on pagination links.
    $invoices = $this->invoiceService
    ->getInvoicesWithFilters($filters, $user)
    ->paginate(15)
    ->appends($request->query());

    $statistics = $this->invoiceService->getInvoiceStatistics($user);

    $totalInvoices   = $statistics['total_invoices']   ?? 0;
    $paidInvoices    = $statistics['paid_invoices']    ?? 0;
    $pendingInvoices = $statistics['pending_invoices'] ?? 0;
    $overdueInvoices = $statistics['overdue_invoices'] ?? 0;
    $totalDue        = $statistics['total_due']        ?? 0;
    $totalRevenue    = $statistics['total_revenue']    ?? 0;
    $totalPenalties  = $statistics['total_penalties']  ?? 0;
    $collectionRate  = $statistics['collection_rate']  ?? 0;

    // Cached aggregate counts — staleness of 60s is acceptable for a dashboard.
    $consolidatedInvoices = Cache::remember('invoices.consolidated_count', 60, fn () =>
        Invoice::where('status', 'consolidated')->count()
    );

    $activeCoverages = Cache::remember('invoices.active_coverage_count', 60, fn () =>
        Invoice::where('is_bulk_payment', true)
            ->where('status', 'paid')
            ->whereNotNull('covers_periods')
            ->count()
    );

    $settings = $this->settings();

    $autoGenerationEnabled = $settings->isAutoInvoiceGenerationEnabled();
    $remindersEnabled      = $settings->shouldSendPaymentReminders();
    $reminderDays          = $settings->getReminderDaysBefore();
    $gracePeriodDays       = $settings->grace_period_days ?? 7;

    $nextGenerationDate = null;
    if ($autoGenerationEnabled) {
        $nextGenerationDate = $settings->getNextInvoiceGenerationDate()->format('F j, Y');
    }

    $properties = Property::with('landlord')
        ->orderBy('street_name')
        ->orderBy('house_number')
        ->get();

    $periods = Cache::remember('invoices.distinct_periods', 300, fn () =>
        Invoice::select('period')
            ->where('status', '!=', 'consolidated')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period')
            ->toArray()
    );

    return view('admin.invoices.index', compact(
        'invoices', 'properties', 'periods',
        'totalInvoices', 'paidInvoices', 'pendingInvoices', 'overdueInvoices',
        'consolidatedInvoices', 'activeCoverages',
        'totalDue', 'totalRevenue', 'totalPenalties', 'collectionRate',
        'autoGenerationEnabled', 'remindersEnabled', 'reminderDays',
        'gracePeriodDays', 'nextGenerationDate', 'settings'
    ));
}

    /* ============================================================
     | NOTIFICATION HELPERS
     * ============================================================ */

    protected function sendNotificationThroughChannels(Invoice $invoice, string $type, array $additionalData = []): array
    {
        $results = [
            'email'         => false,
            'sms'           => false,
            'whatsapp'      => false,
            'channels_used' => [],
            'errors'        => [],
        ];

        $landlord = $invoice->property?->landlord;

        if (!$landlord) {
            $results['errors'][] = 'No landlord found for invoice';
            return $results;
        }

        try {
            $serviceResult = match ($type) {
                'invoice_generated'    => $this->notificationService->sendLandlordInvoiceCreated($invoice),
                'payment_reminder'     => $this->notificationService->sendLandlordInvoiceReminder($invoice),
                'overdue'              => $this->notificationService->sendLandlordInvoiceOverdue($invoice),
                'payment_confirmation' => $this->notificationService->sendLandlordPaymentConfirmation($invoice),
                default                => ['dispatched' => [], 'channels' => [], 'skipped' => ['unknown_type']],
            };

            $dispatched = $serviceResult['dispatched'] ?? [];

            foreach (['email', 'sms', 'whatsapp'] as $channel) {
                if (!empty($dispatched[$channel])) {
                    $results[$channel]          = true;
                    $results['channels_used'][] = $channel;
                }
            }

            if (!empty($serviceResult['skipped'])) {
                $results['errors'] = array_merge($results['errors'], (array) $serviceResult['skipped']);
            }

        } catch (\Throwable $e) {
            Log::error('[InvoiceController] Notification dispatch failed', [
                'invoice_id' => $invoice->id,
                'type'       => $type,
                'error'      => $e->getMessage(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        if (empty($results['channels_used'])
            && method_exists($this->settings(), 'shouldForceEmailFallback')
            && $this->settings()->shouldForceEmailFallback()) {
            try {
                $fallback = match ($type) {
                    'invoice_generated'    => $this->notificationService->sendLandlordInvoiceCreated($invoice),
                    'payment_reminder'     => $this->notificationService->sendLandlordInvoiceReminder($invoice),
                    'overdue'              => $this->notificationService->sendLandlordInvoiceOverdue($invoice),
                    'payment_confirmation' => $this->notificationService->sendLandlordPaymentConfirmation($invoice),
                    default                => null,
                };

                if ($fallback && !empty($fallback['dispatched']['email'])) {
                    $results['email']           = true;
                    $results['channels_used'][] = 'email_fallback';
                    $results['fallback_used']   = true;
                }
            } catch (\Throwable $e) {
                Log::warning('[InvoiceController] Email fallback failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->logNotificationAttempt($invoice, $type, $results);

        return $results;
    }

    protected function logNotificationAttempt(Invoice $invoice, string $type, array $results): void
    {
        try {
            $notificationNote = sprintf(
                "%s notification sent on %s via: %s (Email: %s, SMS: %s, WhatsApp: %s)",
                ucfirst(str_replace('_', ' ', $type)),
                now()->format('Y-m-d H:i:s'),
                implode(', ', $results['channels_used']) ?: 'none',
                $results['email']    ? 'Yes' : 'No',
                $results['sms']      ? 'Yes' : 'No',
                $results['whatsapp'] ? 'Yes' : 'No'
            );

            $invoice->addNote($notificationNote);
        } catch (\Throwable $e) {
            Log::warning("Failed to log notification attempt: " . $e->getMessage());
        }
    }

    protected function sendInvoiceUpdateNotification(Invoice $invoice, array $updateData, bool $wasOverdue = false): void
    {
        try {
            $result = $this->notificationService->sendLandlordInvoiceUpdate($invoice, $updateData);

            Log::info('[InvoiceController] Invoice update notification dispatched', [
                'invoice_id'  => $invoice->id,
                'was_overdue' => $wasOverdue,
                'channels'    => $result['channels']   ?? [],
                'dispatched'  => $result['dispatched'] ?? [],
                'skipped'     => $result['skipped']    ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send invoice update notification: " . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    /* ============================================================
     | LANDLORD LISTING
     * ============================================================ */

    public function landlordInvoices(Request $request)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        $landlordId = auth()->id();
        $properties = Property::where('landlord_id', $landlordId)->get();

        if ($properties->isEmpty()) {
            return view('landlord.invoices.index', [
                'invoices'           => collect(),
                'properties'         => $properties,
                'totalDue'           => 0,
                'outstandingInvoices'=> 0,
                'remindersEnabled'   => false,
                'reminderDays'       => 7,
                'gracePeriodDays'    => 7,
                'bulkCoverages'      => [],
                'totalCoveredMonths' => 0,
            ]);
        }

        $propertyIds = $properties->pluck('id');

        $query = Invoice::with(['property', 'bulkPayment', 'childInvoices'])
            ->whereIn('property_id', $propertyIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', ['consolidated']);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'bulk') {
                $query->where('is_bulk_payment', true);
            } elseif ($request->type === 'regular') {
                $query->where('is_bulk_payment', false)->whereNull('bulk_payment_id');
            }
        }

        $invoices = $query->orderBy('due_date', 'desc')->paginate(15);

        $outstandingInvoices = Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();

        $totalDue = Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum(DB::raw('amount + COALESCE(penalty_amount, 0)'));

        $settings          = $this->settings();
        $remindersEnabled  = $settings->shouldSendPaymentReminders();
        $reminderDays      = $settings->getReminderDaysBefore();
        $gracePeriodDays   = $settings->grace_period_days ?? 7;

        $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
        $availableMethods     = $settings->getAvailablePaymentMethods();

        $bulkOptions   = [];
        $bulkCoverages = [];

        if ($settings->isBulkPaymentEnabled()) {
            foreach ($properties as $property) {
                $bulkOptions[$property->id] = $this->invoiceService->getBulkPaymentOptions($property);
                $propertyCoverages = $this->invoiceService->getActiveBulkCoverages($property);
                if (!empty($propertyCoverages)) {
                    $bulkCoverages[$property->id] = $propertyCoverages;
                }
            }
        }

        $totalCoveredMonths = 0;
        foreach ($bulkCoverages as $propertyCoverages) {
            foreach ($propertyCoverages as $coverage) {
                $totalCoveredMonths += count($coverage['periods'] ?? []);
            }
        }

        return view('landlord.invoices.index', compact(
            'invoices', 'properties', 'outstandingInvoices', 'totalDue',
            'settings', 'paymentConfiguration', 'availableMethods',
            'bulkOptions', 'bulkCoverages',
            'remindersEnabled', 'reminderDays', 'gracePeriodDays',
            'totalCoveredMonths'
        ));
    }

    public function getOutstandingInvoices(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $result = $this->invoiceService->getLandlordOutstandingInvoices(auth()->id());

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('Failed to get outstanding invoices: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load outstanding invoices'], 500);
        }
    }

    public function getLandlordStatistics(): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $statistics = $this->invoiceService->getInvoiceStatistics(auth()->user());

            return response()->json(['success' => true, 'data' => $statistics]);
        } catch (\Exception $e) {
            Log::error('Failed to get landlord statistics: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load statistics'], 500);
        }
    }

    /* ============================================================
     | PAYMENT — landlord-initiated, NEVER restricted by billing
     * ============================================================ */

    public function showPaymentForm(Request $request)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Invalid invoice selection.');
        }

        $invoiceIds    = $request->invoice_ids;
        $summaryResult = $this->invoiceService->getPaymentSummary($invoiceIds);

        if (!$summaryResult['success']) {
            return redirect()->back()->with('error', $summaryResult['message']);
        }

        $landlordId = auth()->id();
        $invoices   = Invoice::with('property')->whereIn('id', $invoiceIds)->get();

        $invalidInvoices = $invoices->filter(function ($invoice) use ($landlordId) {
            return $invoice->property->landlord_id !== $landlordId
                || $invoice->status === 'consolidated';
        });

        if ($invalidInvoices->isNotEmpty()) {
            return redirect()->back()->with('error', 'Invalid invoice selection. Some invoices are not available for payment.');
        }

        $settings             = $this->settings();
        $paymentConfiguration = $this->paymentService->checkPaymentMethodConfiguration();
        $availableMethods     = $settings->getAvailablePaymentMethods();

        if (empty($availableMethods)) {
            return redirect()->back()->with('error', 'No payment methods are currently available. Please contact administrator.');
        }

        $property = $invoices->first()->property;

        $outstandingInvoices = Invoice::where('property_id', $property->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotIn('status', ['consolidated'])
            ->orderBy('due_date')
            ->get();

        $totalDue = $outstandingInvoices->sum('total_amount');

        $paymentInstructions = [];
        foreach ($availableMethods as $provider) {
            $paymentInstructions[$provider] = $this->paymentService->getPaymentInstructions($provider);
        }

        $recipientInfo = [
            'name'    => $settings->payment_account_name,
            'number'  => $settings->payment_mobile_number,
            'network' => $settings->payment_network,
            'message' => 'All payments are sent to the system administrator',
        ];

        $preSelectedInvoiceIds  = $invoiceIds;
        $preSelectedPaymentType = 'invoices';
        $preSelected            = true;
        $preSelectedInvoices    = $invoices;

        return view('landlord.payments.create', array_merge($summaryResult, [
            'settings'               => $settings,
            'paymentConfiguration'   => $paymentConfiguration,
            'availableMethods'       => $availableMethods,
            'property'               => $property,
            'outstandingInvoices'    => $outstandingInvoices,
            'totalDue'               => $totalDue,
            'paymentInstructions'    => $paymentInstructions,
            'configurationStatus'    => $paymentConfiguration,
            'recipientInfo'          => $recipientInfo,
            'preSelectedInvoiceIds'  => $preSelectedInvoiceIds,
            'preSelectedPaymentType' => $preSelectedPaymentType,
            'preSelected'            => $preSelected,
            'preSelectedInvoices'    => $preSelectedInvoices,
        ]));
    }

    public function processPayment(Request $request)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'    => 'required|array',
            'invoice_ids.*'  => 'exists:invoices,id',
            'payment_method' => 'required|string',
            'phone_number'   => 'required_if:payment_method,mtn_momo,telecel_cash,airteltigo_cash',
            'email'          => 'nullable|email',
            'amount'         => 'required|numeric|min:0.01',
            'transaction_id' => 'nullable|string|max:255',
            'payment_date'   => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $consolidatedInvoices = Invoice::whereIn('id', $request->invoice_ids)
                ->where('status', 'consolidated')
                ->count();

            if ($consolidatedInvoices > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot process payment for consolidated invoices. Please use the bulk invoice instead.')
                    ->withInput();
            }

            $result = $this->invoiceService->processPaymentForInvoices(
                $request->invoice_ids,
                $request->transaction_id ?? 'MANUAL-' . time(),
                $request->payment_method
            );

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message'])->withInput();
            }

            if (isset($result['bulk_invoice_id'])) {
                return redirect()->route('landlord.invoices.show', $result['bulk_invoice_id'])
                    ->with('success', 'Bulk payment created successfully! Please complete the payment.');
            }

            return redirect()->route('landlord.invoices')->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('Invoice payment processing error: ' . $e->getMessage(), [
                'user_id'     => auth()->id(),
                'invoice_ids' => $request->invoice_ids,
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while processing your payment. Please try again.')
                ->withInput();
        }
    }

    /* ============================================================
     | BULK PAYMENTS — landlord-initiated
     * ============================================================ */

    public function getBulkPaymentOptions(Request $request, Property $property): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord() || $property->landlord_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->invoiceService->getBulkPaymentOptions($property),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get bulk payment options: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get bulk payment options'], 500);
        }
    }

    public function createBulkPayment(Request $request)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        $validator = Validator::make($request->all(), [
            'property_id'   => 'required|exists:properties,id',
            'months'        => 'required|integer|min:2|max:12',
            'start_month'   => 'nullable|date_format:Y-m',
            'invoice_ids'   => 'nullable|array',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $property = Property::findOrFail($request->property_id);

            if ($property->landlord_id !== auth()->id()) {
                return redirect()->back()->with('error', 'Unauthorized access to property.')->withInput();
            }

            $settings = $this->settings();
            if (!$settings->isBulkPaymentEnabled()) {
                return redirect()->back()
                    ->with('error', 'Bulk payments are currently disabled. Please contact administrator.')
                    ->withInput();
            }

            $startMonth = $request->start_month ?? now()->startOfMonth()->format('Y-m');
            $startDate  = Carbon::parse($startMonth . '-01');
            $endDate    = $startDate->copy()->addMonths($request->months - 1);
            $endMonth   = $endDate->format('Y-m');

            $existingInvoices = Invoice::where('property_id', $property->id)
                ->whereBetween('period', [$startMonth, $endMonth])
                ->whereIn('status', ['pending', 'overdue'])
                ->where('is_bulk_payment', false)
                ->whereNull('bulk_parent_id')
                ->get();

            $invoiceIds = $request->invoice_ids ?? [];

            foreach ($existingInvoices as $existingInvoice) {
                if (!in_array($existingInvoice->id, $invoiceIds)) {
                    $invoiceIds[] = $existingInvoice->id;
                    Log::info('Auto-including existing invoice in bulk payment', [
                        'invoice_id'  => $existingInvoice->id,
                        'period'      => $existingInvoice->period,
                        'property_id' => $property->id,
                    ]);
                }
            }

            $result = $this->invoiceService->generateBulkPaymentInvoice(
                $property,
                $request->months,
                $startMonth,
                $invoiceIds
            );

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message'])->withInput();
            }

            $message = $result['message'];

            if ($existingInvoices->count() > 0) {
                $message .= " Automatically included {$existingInvoices->count()} existing invoice(s) for the period.";
            }

            if (isset($result['coverage_periods']) && count($result['coverage_periods']) > 0) {
                $formattedPeriods = collect($result['coverage_periods'])->map(function ($p) {
                    return Carbon::parse($p . '-01')->format('M Y');
                })->implode(', ');
                $message .= " Coverage periods: {$formattedPeriods}.";
            }

            if (isset($result['consolidated_count']) && $result['consolidated_count'] > 0) {
                $message .= " {$result['consolidated_count']} existing invoice(s) were consolidated into this bulk payment.";
            }

            return redirect()->route('landlord.invoices.show', $result['invoice']->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to create bulk payment: ' . $e->getMessage(), [
                'property_id' => $request->property_id,
                'months'      => $request->months,
                'start_month' => $request->start_month,
                'user_id'     => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create bulk payment. Please try again.')
                ->withInput();
        }
    }

    public function processBulkPayment(Request $request, Invoice $bulkInvoice)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            if ($bulkInvoice->property->landlord_id !== auth()->id()) {
                return redirect()->back()->with('error', 'Unauthorized access to invoice.')->withInput();
            }

            if (!$bulkInvoice->is_bulk_payment) {
                return redirect()->back()->with('error', 'This is not a bulk payment invoice.')->withInput();
            }

            $result = $this->invoiceService->processBulkPayment($bulkInvoice, $request->transaction_id);

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message'])->withInput();
            }

            $message = $result['message'];
            if (isset($result['coverage_periods']) && count($result['coverage_periods']) > 0) {
                $formattedPeriods = collect($result['coverage_periods'])->map(function ($p) {
                    return Carbon::parse($p . '-01')->format('M Y');
                })->implode(', ');
                $message .= " Coverage activated for: {$formattedPeriods}.";
            }

            return redirect()->route('landlord.invoices')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to process bulk payment: ' . $e->getMessage(), [
                'bulk_invoice_id' => $bulkInvoice->id,
                'user_id'         => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process bulk payment. Please try again.')
                ->withInput();
        }
    }

    /* ============================================================
     | MANUAL / MONTHLY GENERATION — admin write operations
     * ============================================================ */

    public function create()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $properties = Property::where('status', 'active')->get();
        $settings   = $this->settings();

        $remindersEnabled = $settings->shouldSendPaymentReminders();
        $reminderDays     = $settings->getReminderDaysBefore();

        $bulkCoverageWarnings = [];
        foreach ($properties as $property) {
            $coverageSummary = $this->invoiceService->getPropertyCoverageSummary($property);
            if ($coverageSummary['has_active_coverage']) {
                $bulkCoverageWarnings[$property->id] = $coverageSummary;
            }
        }

        return view('admin.invoices.create', compact(
            'properties', 'settings', 'bulkCoverageWarnings',
            'remindersEnabled', 'reminderDays'
        ));
    }

    public function generateManual(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('generate_landlord_invoice');

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'period'      => [
                'required',
                'date_format:Y-m',
                Rule::unique('invoices')->where(function ($query) use ($request) {
                    return $query->where('property_id', $request->property_id)
                                 ->where('is_bulk_payment', false)
                                 ->whereNotIn('status', ['consolidated', 'cancelled']);
                })->ignore($request->invoice_id),
            ],
            'due_date'             => 'required|date|after_or_equal:today',
            'amount'               => 'required|numeric|min:0.01',
            'description'          => 'nullable|string|max:500',
            'apply_penalty'        => 'nullable|boolean',
            'penalty_amount'       => 'nullable|numeric|min:0',
            'send_notification'    => 'nullable|boolean',
            'ignore_bulk_coverage' => 'nullable|boolean',
        ], [
            'period.unique' => 'A regular invoice for this property and period already exists and is not consolidated.',
        ]);

        try {
            $settings               = $this->settings();
            $shouldSendNotification = $validated['send_notification'] ?? $settings->shouldSendPaymentReminders();

            $property  = Property::find($validated['property_id']);
            $isCovered = $this->invoiceService->isPeriodCoveredByBulkPayment($property, $validated['period']);

            if ($isCovered && !($validated['ignore_bulk_coverage'] ?? false)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'This period is already covered by an active bulk payment. Check "Ignore Bulk Coverage" to override (not recommended).')
                    ->with('bulk_coverage_warning', true);
            }

            $result = $this->invoiceService->generateManualInvoice(
                $validated['property_id'],
                $validated['period'],
                $validated['amount'],
                Carbon::parse($validated['due_date']),
                false
            );

            if (!$result['success']) {
                return redirect()->back()->withInput()->with('error', $result['message']);
            }

            $invoice = Invoice::find($result['invoice_id']);

            if (($validated['apply_penalty'] ?? false) && $invoice) {
                $penaltyAmount = $validated['penalty_amount'] ?? 0;
                if ($penaltyAmount > 0) {
                    $invoice->applyPenalty($penaltyAmount, 'Manual penalty applied');
                }
            }

            $notificationResults = [];
            if ($shouldSendNotification && $invoice) {
                $notificationResults = $this->sendNotificationThroughChannels($invoice, 'invoice_generated');
            }

            $message = $result['message'];

            if ($isCovered && ($validated['ignore_bulk_coverage'] ?? false)) {
                $message = "⚠️ WARNING: This period was covered by bulk payment but generation was forced. " . $message;
            }

            if ($shouldSendNotification && !empty($notificationResults['channels_used'])) {
                $message .= " Notification sent via: " . implode(', ', $notificationResults['channels_used']);
            } elseif ($shouldSendNotification && empty($notificationResults['channels_used'])) {
                $message .= " Warning: Failed to send notifications through any channel.";
            }

            return redirect()->route('admin.invoices.show', $result['invoice_id'])->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Manual invoice generation failed: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to generate invoice: ' . $e->getMessage());
        }
    }

    public function generateMonthlyInvoices()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('generate_monthly_invoices');

        $settings = $this->settings();

        if (!$settings->isAutoInvoiceGenerationEnabled()) {
            return redirect()->back()
                ->with('warning', 'Auto invoice generation is currently disabled in System Settings. Enable it to generate invoices automatically, or use manual generation.')
                ->with('auto_generation_disabled', true);
        }

        $lock = Cache::lock('generate_monthly_invoices', 600);

        if (!$lock->get()) {
            return redirect()->back()
                ->with('info', 'A monthly generation run is already in progress. Please wait for it to finish.');
        }

        try {
            $result = $this->invoiceService->generateMonthlyInvoices();
        } catch (\Throwable $e) {
            $lock->release();
            Log::error('Monthly generation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Monthly generation failed: ' . $e->getMessage());
        }

        $lock->release();

        if (!($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['message'] ?? 'Generation failed.');
        }

        $message = $result['message'];

        if (isset($result['details']['skipped_bulk_coverage']) && $result['details']['skipped_bulk_coverage'] > 0) {
            $message .= " {$result['details']['skipped_bulk_coverage']} properties skipped (covered by bulk payments).";
        }

        if (isset($result['details']['notifications_sent']) && $result['details']['notifications_sent'] > 0) {
            $message .= " {$result['details']['notifications_sent']} notifications sent to landlords.";
        }

        if (!($result['reminders_enabled'] ?? true)) {
            $message .= " (Note: Payment reminders are disabled in settings)";
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('generation_details', $result['details'] ?? []);
    }

    public function manualGenerateInvoices(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('manual_generate_invoices');

        $validator = Validator::make($request->all(), [
            'period'               => 'nullable|date_format:Y-m',
            'force'                => 'nullable|boolean',
            'send_notifications'   => 'nullable|boolean',
            'ignore_bulk_coverage' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $settings           = $this->settings();
        $force              = $request->boolean('force', false);
        $sendNotifications  = $request->boolean('send_notifications', $settings->shouldSendPaymentReminders());
        $ignoreBulkCoverage = $request->boolean('ignore_bulk_coverage', false);

        if (!$force && !$settings->isAutoInvoiceGenerationEnabled()) {
            return redirect()->back()
                ->with('warning', 'Auto invoice generation is disabled. Use "Force Generation" to override or enable it in System Settings.')
                ->with('auto_generation_disabled', true);
        }

        $lock = Cache::lock('generate_monthly_invoices', 600);
        if (!$lock->get()) {
            return redirect()->back()
                ->with('info', 'A generation run is already in progress. Please wait.');
        }

        try {
            $result = $this->invoiceService->generateMonthlyInvoices(
                $request->period,
                $sendNotifications,
                $ignoreBulkCoverage
            );
        } catch (\Throwable $e) {
            $lock->release();
            return redirect()->back()->with('error', 'Generation failed: ' . $e->getMessage());
        }

        $lock->release();

        if (!($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['message'] ?? 'Generation failed.');
        }

        $message = "Manual invoice generation completed. " . $result['message'];

        if ($ignoreBulkCoverage) {
            $message = "⚠️ BULK COVERAGE IGNORED: " . $message;
        }

        if ($force && !$settings->isAutoInvoiceGenerationEnabled()) {
            $message = "⚠️ FORCED GENERATION (Auto-generation is disabled): " . $message;
        }

        if (!$sendNotifications) {
            $message .= " (Notifications were suppressed)";
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('generation_details', $result['details'] ?? []);
    }

    /* ============================================================
     | COVERAGE — read-only, never restricted
     * ============================================================ */

    public function checkBulkCoverage(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'property_id' => 'required|exists:properties,id',
                'period'      => 'required|date_format:Y-m',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $property = Property::find($request->property_id);

            if ($user->isLandlord() && $property->landlord_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to property.'], 403);
            }

            $isCovered = $this->invoiceService->isPeriodCoveredByBulkPayment($property, $request->period);

            $coverageDetails = null;
            if ($isCovered) {
                $bulkInvoice = Invoice::where('property_id', $property->id)
                    ->where('is_bulk_payment', true)
                    ->where('status', 'paid')
                    ->where(function ($query) use ($request) {
                        $query->whereJsonContains('covers_periods', $request->period)
                              ->orWhere(function ($q) use ($request) {
                                  $periodDate = Carbon::parse($request->period . '-01');
                                  $q->whereNotNull('bulk_coverage_start')
                                    ->whereNotNull('bulk_coverage_end')
                                    ->where('bulk_coverage_start', '<=', $periodDate->format('Y-m'))
                                    ->where('bulk_coverage_end', '>=', $periodDate->format('Y-m'));
                              });
                    })
                    ->first();

                if ($bulkInvoice) {
                    $coverageDetails = [
                        'bulk_invoice_id' => $bulkInvoice->id,
                        'payment_date'    => $bulkInvoice->payment_date?->format('Y-m-d'),
                        'transaction_id'  => $bulkInvoice->payment_reference,
                        'covered_periods' => $bulkInvoice->covers_periods,
                    ];
                }
            }

            return response()->json([
                'success'          => true,
                'covered'          => $isCovered,
                'coverage_details' => $coverageDetails,
                'period'           => $request->period,
                'property_id'      => $property->id,
                'message'          => $isCovered
                    ? 'This period is covered by an active bulk payment.'
                    : 'No bulk coverage for this period.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check bulk coverage: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to check bulk coverage: ' . $e->getMessage()], 500);
        }
    }

    public function getBulkCoverageSummary(Request $request, Property $property): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord() || $property->landlord_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $summary = $this->invoiceService->getPropertyCoverageSummary($property);

            if (isset($summary['coverages']) && is_array($summary['coverages'])) {
                $summary['coverages'] = collect($summary['coverages'])->map(function ($coverage) {
                    $coverage['formatted_periods'] = collect($coverage['periods'] ?? [])
                        ->map(fn ($p) => Carbon::parse($p . '-01')->format('F Y'))
                        ->toArray();
                    return $coverage;
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'data'    => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get bulk coverage summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get bulk coverage summary: ' . $e->getMessage()], 500);
        }
    }

    public function getCoverageInfo(Invoice $invoice)
    {
        try {
            $user = auth()->user();

            if ($user->isLandlord() && $invoice->property->landlord_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $coverageInfo = $this->buildCoverageInfo($invoice);

            return response()->json([
                'success'  => true,
                'coverage' => $coverageInfo,
                'is_bulk'  => (bool) $invoice->is_bulk_payment,
                'is_paid'  => $invoice->isPaid(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting coverage info: " . $e->getMessage(), ['invoice_id' => $invoice->id]);
            return response()->json(['success' => false, 'message' => 'Error retrieving coverage information: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Build a normalized coverage array for a bulk invoice.
     * Returns null when the invoice is not a paid bulk invoice.
     */
    protected function buildCoverageInfo(Invoice $invoice): ?array
    {
        if (!$invoice->is_bulk_payment || !$invoice->isPaid()) {
            return null;
        }

        $periods = $invoice->covers_periods;

        if (is_string($periods)) {
            $decoded = json_decode($periods, true);
            $periods = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($periods)) {
            $periods = [];
        }

        if (empty($periods) && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            $start   = Carbon::parse($invoice->bulk_coverage_start . '-01');
            $end     = Carbon::parse($invoice->bulk_coverage_end . '-01');
            $current = clone $start;
            while ($current <= $end) {
                $periods[] = $current->format('Y-m');
                $current->addMonth();
            }
        }

        $formattedPeriods = [];
        foreach ($periods as $period) {
            try {
                if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
                    $formattedPeriods[] = Carbon::parse($period . '-01')->format('M Y');
                } elseif (is_string($period)) {
                    $formattedPeriods[] = $period;
                }
            } catch (\Throwable $e) {
                $formattedPeriods[] = (string) $period;
            }
        }

        return [
            'periods'           => $periods,
            'formatted_periods' => $formattedPeriods,
            'start'             => $invoice->bulk_coverage_start,
            'end'               => $invoice->bulk_coverage_end,
            'formatted_start'   => $invoice->bulk_coverage_start
                ? Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y')
                : null,
            'formatted_end'     => $invoice->bulk_coverage_end
                ? Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y')
                : null,
            'months_covered'    => count($periods),
            'is_active'         => true,
            'message'           => 'This bulk payment covers ' . count($formattedPeriods) . ' months. No further invoices will be generated for these periods.',
        ];
    }

    /* ============================================================
     | SETTINGS TOGGLES — admin write operations
     * ============================================================ */

    public function toggleAutoGeneration(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('toggle_auto_generation');

        $validator = Validator::make($request->all(), ['enabled' => 'required|boolean']);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $settings   = $this->settings();
            $oldStatus  = $settings->isAutoInvoiceGenerationEnabled();
            $newStatus  = (bool) $request->enabled;

            $settings->auto_generate_invoices = $newStatus;
            $settings->updated_by             = auth()->id();
            $settings->save();

            $message = $newStatus
                ? '✅ Auto invoice generation has been enabled. Invoices will be generated automatically on the scheduled date.'
                : '⚠️ Auto invoice generation has been disabled. Invoices will only be generated manually.';

            Log::info('Auto invoice generation toggled', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'toggled_by' => auth()->id(),
            ]);

            $this->notifyAdminsAboutSettingChange('auto_generate_invoices', $oldStatus, $newStatus);

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to toggle auto generation: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update auto-generation setting.');
        }
    }

    public function updateReminderSettings(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('update_reminder_settings');

        $validator = Validator::make($request->all(), [
            'send_payment_reminders' => 'required|boolean',
            'reminder_days_before'   => 'required|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $settings          = $this->settings();
            $oldReminderStatus = $settings->send_payment_reminders;
            $oldReminderDays   = $settings->reminder_days_before;

            $settings->send_payment_reminders = $request->send_payment_reminders;
            $settings->reminder_days_before   = $request->reminder_days_before;
            $settings->updated_by             = auth()->id();
            $settings->save();

            $message = $request->send_payment_reminders
                ? "✅ Payment reminders enabled. Landlords will be notified {$request->reminder_days_before} days before due date."
                : "⚠️ Payment reminders disabled.";

            Log::info('Payment reminder settings updated', [
                'enabled'     => $request->send_payment_reminders,
                'days_before' => $request->reminder_days_before,
                'old_enabled' => $oldReminderStatus,
                'old_days'    => $oldReminderDays,
                'updated_by'  => auth()->id(),
            ]);

            $this->notifyAdminsAboutReminderChange(
                $oldReminderStatus,
                $oldReminderDays,
                $request->send_payment_reminders,
                $request->reminder_days_before
            );

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to update reminder settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update reminder settings.');
        }
    }

    public function markOverdueInvoices()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('mark_overdue_invoices');

        $result = $this->invoiceService->markOverdueInvoices();

        if (!($result['success'] ?? false)) {
            return redirect()->back()
                ->with('error', $result['message'] ?? 'Failed to mark overdue invoices.');
        }

        $message = $result['message'];

        if (($result['penalty_applied_count'] ?? 0) > 0) {
            $message .= " Penalties applied to {$result['penalty_applied_count']} invoices.";
        }

        if (($result['notified_count'] ?? 0) > 0) {
            $message .= " {$result['notified_count']} landlords notified.";
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('overdue_details', [
                'count'                 => $result['count'] ?? 0,
                'penalty_applied_count' => $result['penalty_applied_count'] ?? 0,
                'notified_count'        => $result['notified_count'] ?? 0,
                'failed_notifications'  => $result['failed_notifications'] ?? [],
            ]);
    }

    /* ============================================================
     | SHOW / PRINT — read-only, never restricted
     * ============================================================ */

    public function show(Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $invoice->load(['property', 'property.landlord', 'payment', 'creator', 'updater', 'bulkPayment', 'childInvoices']);

        $settings = $this->settings();

        $notificationStatus = $this->invoiceService->getInvoiceNotificationStatus($invoice->id);
        $notificationStatus = array_merge([
            'generated_notification_sent' => false,
            'generated_sent_at'           => null,
            'reminder_sent'               => false,
            'last_reminder_sent_at'       => null,
            'overdue_notification_sent'   => false,
            'overdue_sent_at'             => null,
            'has_reminder_schedule'       => false,
            'next_reminder_date'          => null,
        ], $notificationStatus ?? []);

        $coverageInfo = $this->buildCoverageInfo($invoice);

        return view('admin.invoices.show', compact('invoice', 'notificationStatus', 'coverageInfo', 'settings'));
    }

    public function showLandlordInvoice(Invoice $invoice)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        if ($invoice->property->landlord_id !== auth()->id()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access to invoice.');
        }

        $invoice->load(['property', 'payment', 'bulkPayment', 'childInvoices']);
        $settings = $this->settings();

        $bulkOptions = null;
        if (!$invoice->is_bulk_payment
            && in_array($invoice->status, ['pending', 'overdue'])
            && $settings->isBulkPaymentEnabled()) {
            $bulkOptions = $this->invoiceService->getBulkPaymentOptions($invoice->property);
        }

        $coverageInfo = $this->buildCoverageInfo($invoice);

        return view('landlord.invoices.show', compact('invoice', 'settings', 'bulkOptions', 'coverageInfo'));
    }

    public function print(Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized. Only administrators can print invoices.');
        }

        $invoice->load(['property', 'property.landlord', 'creator', 'bulkPayment', 'childInvoices']);
        $settings = $this->settings();

        return view('admin.invoices.print', compact('invoice', 'settings'));
    }

    public function printLandlordInvoice(Invoice $invoice)
    {
        if (!auth()->user()->isLandlord()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Unauthorized access.');
        }

        if ($invoice->property->landlord_id !== auth()->id()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access to invoice.');
        }

        $invoice->load(['property', 'property.landlord', 'bulkPayment', 'childInvoices']);
        $settings = $this->settings();

        return view('landlord.invoices.print', compact('invoice', 'settings'));
    }

    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->invoiceService->getInvoiceStatistics(auth()->user());
            return response()->json(['success' => true, 'data' => $statistics]);
        } catch (\Exception $e) {
            Log::error('Error retrieving invoice statistics: ' . $e->getMessage(), ['user_id' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Error retrieving invoice statistics.'], 500);
        }
    }

    public function getAutoGenerationStatus(): JsonResponse
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->invoiceService->getAutoInvoiceGenerationStatus(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting auto-generation status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error getting auto-generation status'], 500);
        }
    }

    public function getPaymentSummary(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'invoice_ids'   => 'required|array',
                'invoice_ids.*' => 'exists:invoices,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid invoice selection',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $consolidatedCount = Invoice::whereIn('id', $request->invoice_ids)
                ->where('status', 'consolidated')
                ->count();

            if ($consolidatedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot process payment for consolidated invoices. Please use the bulk invoice instead.',
                ], 422);
            }

            return response()->json($this->invoiceService->getPaymentSummary($request->invoice_ids));

        } catch (\Exception $e) {
            Log::error('Error getting payment summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error getting payment summary'], 500);
        }
    }

    /* ============================================================
     | UPDATE / PAID / PENALTIES — admin write operations
     * ============================================================ */

    public function update(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can update invoices.');
        }

        $this->assertBillingAllowsWrite('update_landlord_invoice');

        if ($invoice->status === 'consolidated') {
            return redirect()->back()->with('error', 'Cannot update a consolidated invoice.');
        }

        $validated = $request->validate([
            'amount'            => 'required|numeric|min:0.01',
            'due_date'          => 'required|date',
            'description'       => 'nullable|string|max:500',
            'notes'             => 'nullable|string',
            'send_notification' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $oldDueDate = $invoice->due_date;
            $oldAmount  = $invoice->amount;
            $wasOverdue = ($invoice->status === 'overdue')
                || ($invoice->due_date && $invoice->due_date < now());

            $invoice->update([
                'amount'      => $validated['amount'],
                'due_date'    => Carbon::parse($validated['due_date']),
                'description' => $validated['description'],
                'updated_by'  => auth()->id(),
            ]);

            if (!empty($validated['notes'])) {
                $invoice->addNote("Updated by " . auth()->user()->name . ": " . $validated['notes']);
            }

            DB::commit();

            $amountChanged  = (float) $oldAmount !== (float) $invoice->amount;
            $dueDateChanged = $oldDueDate && $oldDueDate->ne($invoice->due_date);
            $shouldNotify   = (bool) ($validated['send_notification'] ?? false);

            if ($shouldNotify && ($amountChanged || $dueDateChanged)) {
                $updateData = [
                    'invoice_number'       => $invoice->invoice_number,
                    'old_amount'           => $oldAmount,
                    'new_amount'           => $invoice->amount,
                    'old_due_date'         => $oldDueDate?->format('M d, Y'),
                    'new_due_date'         => $invoice->due_date->format('M d, Y'),
                    'formatted_old_amount' => $this->settings()->formatAmount($oldAmount),
                    'formatted_new_amount' => $this->settings()->formatAmount($invoice->amount),
                    'was_overdue'          => $wasOverdue,
                ];

                $this->sendInvoiceUpdateNotification($invoice->fresh(), $updateData, $wasOverdue);
            }

            return redirect()->back()->with('success', 'Invoice updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating invoice: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Error updating invoice. Please try again.');
        }
    }

    public function markAsPaid(Request $request, Invoice $invoice)
{
    if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
        return redirect()->back()->with('error', 'Unauthorized. Only administrators can mark invoices as paid.');
    }

    // ✅ NEW: enforce the offline-payment toggle
    $settings = $this->settings();
    if (!$settings->isOfflinePaymentAllowed()) {
        return redirect()->back()->with(
            'error',
            '❌ Office payments are currently disabled. Landlords must pay through the online gateway.'
        );
    }

        $this->assertBillingAllowsWrite('mark_landlord_invoice_paid');

        $validated = $request->validate([
            'payment_method'    => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'send_confirmation' => 'nullable|boolean',
            'activate_coverage' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            if ($invoice->status === 'consolidated') {
                return redirect()->back()
                    ->with('error', 'Cannot mark a consolidated invoice as paid. Please process the parent bulk invoice instead.')
                    ->withInput();
            }

            $invoice->update([
                'status'            => Invoice::STATUS_PAID,
                'paid_amount'       => $invoice->total_amount,
                'balance'           => 0,
                'payment_method'    => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
                'payment_date'      => Carbon::parse($validated['payment_date']),
                'updated_by'        => auth()->id(),
            ]);

            if (!empty($validated['notes'])) {
                $invoice->addNote("Manually marked as paid by " . auth()->user()->name . ": " . $validated['notes']);
            }

            if ($invoice->is_bulk_payment) {
                $result = $this->invoiceService->processBulkPayment($invoice, $validated['payment_reference'] ?? 'MANUAL-' . time());

                if ($result['success'] && isset($result['coverage_periods'])) {
                    $invoice->addNote("Bulk coverage activated for periods: " . implode(', ', $result['coverage_periods']));
                }
            }

            DB::commit();

            if ($validated['send_confirmation'] ?? false) {
                $this->sendNotificationThroughChannels($invoice, 'payment_confirmation');
            }

            $message = 'Invoice marked as paid successfully!';
            if ($invoice->is_bulk_payment && ($validated['activate_coverage'] ?? true)) {
                $message .= ' Bulk coverage activated.';
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking invoice as paid: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Error marking invoice as paid. Please try again.');
        }
    }

    public function resendNotification(Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($invoice->status === 'consolidated') {
            return redirect()->back()->with('error', 'Cannot send notification for a consolidated invoice.');
        }

        $type = match ($invoice->status) {
            'paid'    => 'payment_confirmation',
            'overdue' => 'overdue',
            default   => 'payment_reminder',
        };

        $results = $this->sendNotificationThroughChannels($invoice, $type);

        if (!empty($results['channels_used'])) {
            return redirect()->back()
                ->with('success', "Invoice notification resent successfully via: " . implode(', ', $results['channels_used']));
        }

        $errorMsg = 'Failed to resend notification through any channel.';
        if (!empty($results['errors'])) {
            $errorMsg .= ' Errors: ' . implode(', ', $results['errors']);
        }
        return redirect()->back()->with('error', $errorMsg);
    }

    public function applyPenalty(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can apply penalties.');
        }

        $this->assertBillingAllowsWrite('apply_landlord_penalty');

        if ($invoice->status === 'consolidated') {
            return redirect()->back()->with('error', 'Cannot apply penalty to a consolidated invoice.');
        }

        if ($invoice->isPaid()) {
            return redirect()->back()->with('error', 'Cannot apply penalty to a paid invoice.');
        }

        $validated = $request->validate([
            'penalty_amount' => 'required|numeric|min:0.01',
            'reason'         => 'required|string|max:255',
        ]);

        try {
            $invoice->applyPenalty($validated['penalty_amount'], $validated['reason']);
            return redirect()->back()->with('success', 'Penalty applied successfully!');
        } catch (\Exception $e) {
            Log::error('Error applying penalty: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Error applying penalty. Please try again.');
        }
    }

    public function removePenalty(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can remove penalties.');
        }

        $this->assertBillingAllowsWrite('remove_landlord_penalty');

        if ($invoice->status === 'consolidated') {
            return redirect()->back()->with('error', 'Cannot remove penalty from a consolidated invoice.');
        }

        if ($invoice->isPaid()) {
            return redirect()->back()->with('error', 'Cannot remove penalty from a paid invoice.');
        }

        $validated = $request->validate(['reason' => 'required|string|max:255']);

        try {
            $invoice->removePenalty($validated['reason']);
            return redirect()->back()->with('success', 'Penalty removed successfully!');
        } catch (\Exception $e) {
            Log::error('Error removing penalty: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Error removing penalty. Please try again.');
        }
    }

    /* ============================================================
     | DELETE / ARCHIVE / TRASH — admin write operations
     * ============================================================ */

    public function destroy(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can delete invoices.');
        }

        $this->assertBillingAllowsWrite('delete_landlord_invoice');

        if (!$this->canDeleteInvoice($invoice)) {
            return redirect()->back()->with('error', $this->getDeletionErrorMessage($invoice));
        }

        try {
            DB::beginTransaction();

            $archive = $this->createArchivalRecord($invoice, $request);

            $metadata = $invoice->metadata ?? [];
            $metadata['notes'] = array_merge($metadata['notes'] ?? [], [
                "Invoice moved to trash by " . auth()->user()->name . " on " . now()->toDateTimeString(),
            ]);
            $metadata['deleted_by']           = auth()->id();
            $metadata['deleted_by_name']      = auth()->user()->name;
            $metadata['deleted_reason']       = $request->input('reason', 'Manual deletion');
            $metadata['deleted_at_timestamp'] = now()->toDateTimeString();
            $metadata['archive_id']           = $archive->id;
            $invoice->update(['metadata' => $metadata]);

            $invoice->delete();

            Log::info('Invoice soft deleted with archive', [
                'invoice_id'       => $invoice->id,
                'invoice_number'   => $invoice->invoice_number,
                'archive_id'       => $archive->id,
                'deleted_by'       => auth()->id(),
                'deleted_by_name'  => auth()->user()->name,
                'deletion_reason'  => $request->input('reason', 'Manual deletion'),
                'deletion_ip'      => $request->ip(),
            ]);

            DB::commit();

            $this->notifyAdminsAboutDeletion($invoice, $request);

            $message = "✅ Invoice #{$invoice->invoice_number} has been moved to trash and archived.";
            if ($request->input('reason')) {
                $message .= " Reason: {$request->input('reason')}";
            }

            return redirect()->route('invoices.index')
                ->with('success', $message)
                ->with('archive_id', $archive->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting invoice: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
                'exception'  => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'Error deleting invoice. Please try again.');
        }
    }

    private function canDeleteInvoice(Invoice $invoice): bool
    {
        if ($invoice->isPaid() && !empty($invoice->covers_periods)) {
            return false;
        }
        if (($invoice->paid_amount ?? 0) > 0) {
            return false;
        }
        if ($invoice->created_at < now()->subYear()) {
            return false;
        }
        if ($invoice->period === now()->format('Y-m')
            && isset($invoice->metadata['generation_method'])
            && $invoice->metadata['generation_method'] === 'auto') {
            return false;
        }
        if ($invoice->is_bulk_payment && $invoice->childInvoices()->count() > 0) {
            return false;
        }
        if ($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods)) {
            return false;
        }

        return true;
    }

    private function getDeletionErrorMessage(Invoice $invoice): string
    {
        if ($invoice->isPaid() && !empty($invoice->covers_periods)) {
            return '❌ Cannot delete a paid invoice with active coverage. Reverse coverage first.';
        }
        if (($invoice->paid_amount ?? 0) > 0) {
            return '❌ Cannot delete an invoice with payments. Process refunds first.';
        }
        if ($invoice->created_at < now()->subYear()) {
            return '❌ Cannot delete invoices older than 1 year. These are archived for audit purposes.';
        }
        if ($invoice->period === now()->format('Y-m')
            && isset($invoice->metadata['generation_method'])
            && $invoice->metadata['generation_method'] === 'auto') {
            return '❌ Cannot delete current month\'s auto-generated invoice.';
        }
        if ($invoice->is_bulk_payment && $invoice->childInvoices()->count() > 0) {
            return '❌ Cannot delete a bulk invoice that has child invoices. Reverse consolidation first.';
        }
        if ($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods)) {
            return '❌ Cannot delete a paid bulk invoice with active coverage. Reverse consolidation first.';
        }

        return '❌ Invoice cannot be deleted due to system constraints.';
    }

    private function createArchivalRecord(Invoice $invoice, Request $request): InvoiceArchive
    {
        $settings = $this->settings();

        $balance = $invoice->balance ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0));
        if ($balance === null) {
            $balance = $invoice->total_amount;
        }

        $paymentMethod    = $invoice->payment_method ?? 'pending';
        $paymentReference = $invoice->payment_reference ?? null;
        $paymentDate      = $invoice->payment_date ?? null;
        $metadata         = $invoice->metadata ?? [];
        $coversPeriods    = $invoice->covers_periods ?? null;

        $propertyName = $invoice->property?->property_name
                     ?? $invoice->property?->street_name
                     ?? 'N/A';

        $landlordName = $invoice->property?->landlord?->name ?? 'Unknown';

        $monthName   = $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : null;
        $totalAmount = ($invoice->amount ?? 0) + ($invoice->penalty_amount ?? 0);

        $archiveData = [
            'original_invoice_id'   => $invoice->id,
            'invoice_number'        => $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
            'property_id'           => $invoice->property_id,
            'property_name'         => $propertyName,
            'landlord_id'           => $invoice->property?->landlord_id,
            'landlord_name'         => $landlordName,
            'period'                => $invoice->period,
            'month_name'            => $monthName,
            'due_date'              => $invoice->due_date,
            'amount'                => $invoice->amount ?? 0,
            'penalty_amount'        => $invoice->penalty_amount ?? 0,
            'total_amount'          => $totalAmount,
            'paid_amount'           => $invoice->paid_amount ?? 0,
            'balance'               => $balance,
            'status'                => $invoice->status ?? 'pending',
            'payment_method'        => $paymentMethod,
            'payment_reference'     => $paymentReference,
            'payment_date'          => $paymentDate,
            'is_bulk_payment'       => $invoice->is_bulk_payment ?? false,
            'bulk_payment_id'       => $invoice->bulk_payment_id ?? null,
            'covers_periods'        => $coversPeriods,
            'bulk_coverage_start'   => $invoice->bulk_coverage_start ?? null,
            'bulk_coverage_end'     => $invoice->bulk_coverage_end ?? null,
            'description'           => $invoice->description,
            'notes'                 => $invoice->notes,
            'metadata'              => $metadata,
            'original_created_at'   => $invoice->created_at,
            'original_created_by'   => $invoice->created_by,
            'original_updated_at'   => $invoice->updated_at,
            'original_updated_by'   => $invoice->updated_by,
            'deleted_at'            => now(),
            'deleted_by'            => auth()->id(),
            'deleted_by_name'       => auth()->user()->name,
            'deletion_reason'       => $request->input('reason', 'Manual deletion'),
            'deletion_ip'           => $request->ip(),
            'deletion_user_agent'   => $request->userAgent(),
            'archive_type'          => 'manual',
            'grace_period_days'     => $settings->grace_period_days ?? null,
            'late_payment_percentage'=> $settings->late_payment_percentage ?? null,
            'fixed_penalty_amount'  => $settings->fixed_penalty_amount ?? null,
        ];

        Log::info('Creating archive record with data', [
            'invoice_id'        => $invoice->id,
            'balance'           => $balance,
            'payment_method'    => $paymentMethod,
            'status'            => $invoice->status,
            'archive_data_keys' => array_keys($archiveData),
        ]);

        return InvoiceArchive::create($archiveData);
    }

    private function notifyAdminsAboutDeletion(Invoice $invoice, Request $request): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $settings = $this->settings();
            $propertyName = $invoice->property?->property_name
                         ?? $invoice->property?->street_name
                         ?? 'N/A';

            $notificationData = [
                'title'      => '🗑️ Invoice Deleted',
                'message'    => "Invoice #{$invoice->invoice_number} for period "
                              . ($invoice->period ? Carbon::parse($invoice->period . '-01')->format('M Y') : 'N/A')
                              . " was deleted by " . auth()->user()->name
                              . ". Amount: " . $settings->formatAmount($invoice->total_amount)
                              . ($request->input('reason') ? " Reason: {$request->input('reason')}" : ""),
                'icon'       => 'fas fa-trash-alt text-warning',
                'category'   => 'invoices',
                'action_url' => route('invoices.trash'),
                'priority'   => 2,
                'data'       => [
                    'type'            => 'invoice_deleted',
                    'invoice_id'      => $invoice->id,
                    'invoice_number'  => $invoice->invoice_number,
                    'property_id'     => $invoice->property_id,
                    'property_name'   => $propertyName,
                    'amount'          => $invoice->total_amount,
                    'period'          => $invoice->period,
                    'deleted_by'      => auth()->id(),
                    'deleted_by_name' => auth()->user()->name,
                    'deleted_at'      => now()->toISOString(),
                    'deletion_reason' => $request->input('reason', 'Manual deletion'),
                    'deletion_ip'     => $request->ip(),
                ],
            ];

            foreach ($admins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('Admins notified about invoice deletion', [
                'invoice_id'  => $invoice->id,
                'admin_count' => $admins->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about invoice deletion: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | ARCHIVE MANAGEMENT
     * ============================================================ */

    public function archives(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = InvoiceArchive::with(['property', 'landlord'])->orderBy('deleted_at', 'desc');

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }
        if ($request->filled('property_name')) {
            $query->where('property_name', 'like', '%' . $request->property_name . '%');
        }
        if ($request->filled('landlord_name')) {
            $query->where('landlord_name', 'like', '%' . $request->landlord_name . '%');
        }
        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('archive_type')) {
            $query->where('archive_type', $request->archive_type);
        }
        if ($request->filled('deleted_from')) {
            $query->whereDate('deleted_at', '>=', $request->deleted_from);
        }
        if ($request->filled('deleted_to')) {
            $query->whereDate('deleted_at', '<=', $request->deleted_to);
        }

        $archives = $query->paginate(20);

        $statistics = Cache::remember('invoices.archive_statistics', 60, function () {
            $oldest = InvoiceArchive::orderBy('deleted_at', 'asc')->first();
            $newest = InvoiceArchive::orderBy('deleted_at', 'desc')->first();

            return [
                'total_archives'        => InvoiceArchive::count(),
                'manual_archives'       => InvoiceArchive::where('archive_type', 'manual')->count(),
                'year_end_archives'     => InvoiceArchive::where('archive_type', 'year_end')->count(),
                'post_payment_archives' => InvoiceArchive::where('archive_type', 'post_payment')->count(),
                'total_amount'          => InvoiceArchive::sum('total_amount'),
                'total_penalties'       => InvoiceArchive::sum('penalty_amount'),
                'oldest_archive'        => $oldest,
                'newest_archive'        => $newest,
                'oldest_date'           => $oldest?->deleted_at?->format('M d, Y') ?? 'N/A',
                'newest_date'           => $newest?->deleted_at?->format('M d, Y') ?? 'N/A',
            ];
        });

        $periods = InvoiceArchive::select('period')->distinct()->orderBy('period', 'desc')->pluck('period');
        $system_settings = $this->settings();

        return view('admin.invoices.archives', compact('archives', 'statistics', 'periods', 'system_settings'));
    }

    public function exportArchive($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $archive = InvoiceArchive::findOrFail($id);

        $exportData = [
            'archive' => [
                'id'                  => $archive->id,
                'invoice_number'      => $archive->invoice_number,
                'original_invoice_id' => $archive->original_invoice_id,
                'property'            => ['id' => $archive->property_id, 'name' => $archive->property_name],
                'landlord'            => ['id' => $archive->landlord_id, 'name' => $archive->landlord_name],
                'period'              => [
                    'code'     => $archive->period,
                    'month'    => $archive->month_name,
                    'due_date' => $archive->due_date ? Carbon::parse($archive->due_date)->format('Y-m-d') : null,
                ],
                'financial'           => [
                    'amount'         => (float) $archive->amount,
                    'penalty_amount' => (float) $archive->penalty_amount,
                    'total_amount'   => (float) $archive->total_amount,
                    'paid_amount'    => (float) $archive->paid_amount,
                    'balance'        => (float) $archive->balance,
                ],
                'status'              => $archive->status,
                'payment'             => [
                    'method'    => $archive->payment_method,
                    'reference' => $archive->payment_reference,
                    'date'      => $archive->payment_date ? Carbon::parse($archive->payment_date)->format('Y-m-d') : null,
                ],
                'bulk_payment'        => [
                    'is_bulk'             => (bool) $archive->is_bulk_payment,
                    'covers_periods'      => $archive->covers_periods,
                    'bulk_coverage_start' => $archive->bulk_coverage_start,
                    'bulk_coverage_end'   => $archive->bulk_coverage_end,
                ],
                'archive'             => [
                    'type'            => $archive->archive_type,
                    'deleted_at'      => $archive->deleted_at ? Carbon::parse($archive->deleted_at)->toISOString() : null,
                    'deleted_by'      => ['id' => $archive->deleted_by, 'name' => $archive->deleted_by_name],
                    'deletion_reason' => $archive->deletion_reason,
                    'deletion_ip'     => $archive->deletion_ip,
                    'user_agent'      => $archive->deletion_user_agent,
                ],
                'original_creation'   => [
                    'created_at' => $archive->original_created_at ? Carbon::parse($archive->original_created_at)->toISOString() : null,
                    'created_by' => $archive->original_created_by,
                    'updated_at' => $archive->original_updated_at ? Carbon::parse($archive->original_updated_at)->toISOString() : null,
                    'updated_by' => $archive->original_updated_by,
                ],
                'system_settings'     => [
                    'grace_period_days'       => $archive->grace_period_days,
                    'late_payment_percentage' => (float) $archive->late_payment_percentage,
                    'fixed_penalty_amount'    => (float) $archive->fixed_penalty_amount,
                ],
                'description'         => $archive->description,
                'notes'               => $archive->notes,
                'metadata'            => $archive->metadata,
            ],
            'export_info' => [
                'generated_at'    => now()->toISOString(),
                'generated_by'    => auth()->user()->name,
                'generated_by_id' => auth()->id(),
                'format_version'  => '1.0',
                'export_type'     => 'single_archive',
            ],
        ];

        $filename = "archive_{$archive->invoice_number}_" . date('Y-m-d') . '.json';

        return response()->json($exportData, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Type'        => 'application/json',
        ]);
    }

    public function exportArchivePDF($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $archive  = InvoiceArchive::findOrFail($id);
        $settings = $this->settings();

        $pdf = Pdf::loadView('admin.invoices.archive-pdf', compact('archive', 'settings'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("archive-{$archive->invoice_number}.pdf");
    }

    public function getArchiveDetails($id): JsonResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $archive  = InvoiceArchive::with(['property', 'landlord'])->findOrFail($id);
        $settings = $this->settings();

        return response()->json([
            'success' => true,
            'archive' => $archive,
            'formatted' => [
                'total_amount'   => $settings->formatAmount($archive->total_amount),
                'penalty_amount' => $settings->formatAmount($archive->penalty_amount),
                'balance'        => $settings->formatAmount($archive->balance),
                'deleted_at'     => $archive->deleted_at?->format('M d, Y H:i'),
            ],
        ]);
    }

    public function exportAllArchives(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $format = $request->get('format', 'csv');
        $query  = InvoiceArchive::with(['property', 'landlord']);

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }
        if ($request->filled('property_name')) {
            $query->where('property_name', 'like', '%' . $request->property_name . '%');
        }
        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('deleted_from')) {
            $query->whereDate('deleted_at', '>=', $request->deleted_from);
        }
        if ($request->filled('deleted_to')) {
            $query->whereDate('deleted_at', '<=', $request->deleted_to);
        }

        $archives = $query->orderBy('deleted_at', 'desc')->get();
        $settings = $this->settings();

        if ($archives->isEmpty()) {
            return redirect()->back()->with('error', 'No records found to export.');
        }

        return match ($format) {
            'json'  => $this->exportArchivesAsJSON($archives),
            'excel' => $this->exportArchivesAsExcel($archives, $settings),
            'pdf'   => $this->exportArchivesAsPDF($archives, $settings),
            default => $this->exportArchivesAsCSV($archives, $settings),
        };
    }

    private function exportArchivesAsCSV($archives, $settings)
    {
        $filename = 'archive_export_' . date('Y-m-d_His') . '.csv';
        $handle   = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number', 'Property', 'Landlord', 'Period', 'Month',
            'Due Date', 'Total Amount', 'Status', 'Payment Method',
            'Payment Reference', 'Deleted At', 'Deleted By',
            'Deletion Reason', 'Archive Type',
        ]);

        foreach ($archives as $archive) {
            fputcsv($handle, [
                $archive->invoice_number,
                $archive->property_name,
                $archive->landlord_name,
                $archive->period,
                $archive->month_name,
                $archive->due_date ? Carbon::parse($archive->due_date)->format('Y-m-d') : '',
                $archive->total_amount,
                $archive->status,
                $archive->payment_method,
                $archive->payment_reference,
                $archive->deleted_at ? $archive->deleted_at->format('Y-m-d H:i:s') : '',
                $archive->deleted_by_name,
                $archive->deletion_reason,
                $archive->archive_type,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportArchivesAsJSON($archives)
    {
        $data = $archives->map(function ($archive) {
            return [
                'id'                => $archive->id,
                'invoice_number'    => $archive->invoice_number,
                'property_name'     => $archive->property_name,
                'landlord_name'     => $archive->landlord_name,
                'period'            => $archive->period,
                'month_name'        => $archive->month_name,
                'due_date'          => $archive->due_date ? Carbon::parse($archive->due_date)->format('Y-m-d') : null,
                'total_amount'      => $archive->total_amount,
                'status'            => $archive->status,
                'payment_method'    => $archive->payment_method,
                'payment_reference' => $archive->payment_reference,
                'payment_date'      => $archive->payment_date ? Carbon::parse($archive->payment_date)->format('Y-m-d') : null,
                'is_bulk_payment'   => $archive->is_bulk_payment,
                'covers_periods'    => $archive->covers_periods,
                'deleted_at'        => $archive->deleted_at ? $archive->deleted_at->format('Y-m-d H:i:s') : null,
                'deleted_by'        => $archive->deleted_by_name,
                'deletion_reason'   => $archive->deletion_reason,
                'archive_type'      => $archive->archive_type,
            ];
        });

        $filename = 'archive_export_' . date('Y-m-d_His') . '.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Alias for CSV export — a proper PhpSpreadsheet implementation is
     * planned but not yet wired in.
     */
    private function exportArchivesAsExcel($archives, $settings)
    {
        return $this->exportArchivesAsCSV($archives, $settings);
    }

    private function exportArchivesAsPDF($archives, $settings)
    {
        $data = [
            'archives'      => $archives,
            'settings'      => $settings,
            'export_date'   => now()->format('Y-m-d H:i:s'),
            'total_records' => $archives->count(),
            'total_amount'  => $archives->sum('total_amount'),
            'exported_by'   => auth()->user()->name,
        ];

        $pdf = Pdf::loadView('admin.invoices.archives-pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('archive_export_' . date('Y-m-d_His') . '.pdf');
    }

    public function bulkDeleteArchives(Request $request): JsonResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->assertBillingAllowsWrite('bulk_delete_archives');

        $validator = Validator::make($request->all(), [
            'archive_ids'   => 'required|array',
            'archive_ids.*' => 'integer|exists:invoice_archives,id',
            'confirm'       => 'required|accepted',
            'exported'      => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Confirm deletion and export first.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $archives = InvoiceArchive::whereIn('id', $request->archive_ids)->get();
            $count    = $archives->count();
            $total    = $archives->sum('total_amount');

            foreach ($archives as $archive) {
                $archive->delete();
            }

            DB::commit();

            Log::warning('Bulk archive deletion performed', [
                'archive_ids'  => $request->archive_ids,
                'count'        => $count,
                'total_amount' => $total,
                'deleted_by'   => auth()->id(),
            ]);

            return response()->json([
                'success'         => true,
                'message'         => "Deleted {$count} archive record(s).",
                'records_deleted' => $count,
                'total_amount'    => $total,
                'formatted'       => $this->settings()->formatAmount($total),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bulk archive deletion failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function archiveCleanup()
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only Super Admins can perform archive cleanup.');
        }

        $stats = Cache::remember('invoices.archive_cleanup_stats', 60, function () {
            return [
                'total_archives'     => InvoiceArchive::count(),
                'older_than_1_year'  => InvoiceArchive::where('deleted_at', '<', now()->subYear())->count(),
                'older_than_2_years' => InvoiceArchive::where('deleted_at', '<', now()->subYears(2))->count(),
                'older_than_5_years' => InvoiceArchive::where('deleted_at', '<', now()->subYears(5))->count(),
                'older_than_7_years' => InvoiceArchive::where('deleted_at', '<', now()->subYears(7))->count(),
                'total_amount'       => InvoiceArchive::sum('total_amount'),
                'oldest_archive'     => InvoiceArchive::orderBy('deleted_at', 'asc')->first(),
                'newest_archive'     => InvoiceArchive::orderBy('deleted_at', 'desc')->first(),
            ];
        });

        $system_settings = $this->settings();

        return view('admin.invoices.archive-cleanup', compact('stats', 'system_settings'));
    }

    public function performArchiveCleanup(Request $request): JsonResponse
    {
        if (ob_get_level()) {
            ob_clean();
        }

        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->assertBillingAllowsWrite('perform_archive_cleanup');

        try {
            $years      = $request->get('years');
            $archiveIds = $request->get('archive_ids');
            $confirm    = $request->get('confirm', false);
            $exported   = $request->get('exported', false);

            if (!$confirm) {
                return response()->json(['success' => false, 'message' => 'Please confirm deletion.']);
            }
            if (!$exported) {
                return response()->json(['success' => false, 'message' => 'Please export the data first.']);
            }

            DB::beginTransaction();

            $query = InvoiceArchive::query();

            if ($years !== null) {
                $cutoffDate = now()->subYears($years);
                $query->where('deleted_at', '<', $cutoffDate);
                $method = 'age';
            } elseif (!empty($archiveIds)) {
                $query->whereIn('id', $archiveIds);
                $method = 'selection';
            } else {
                return response()->json(['success' => false, 'message' => 'No criteria provided for cleanup.'], 422);
            }

            $archives    = $query->get();
            $count       = $archives->count();
            $totalAmount = $archives->sum('total_amount');

            if ($count === 0) {
                return response()->json(['success' => false, 'message' => 'No archives found matching criteria.'], 404);
            }

            Log::warning('Archive cleanup performed', [
                'method'          => $method,
                'years'           => $years,
                'archive_ids'     => $archiveIds,
                'records_deleted' => $count,
                'total_amount'    => $totalAmount,
                'deleted_by'      => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
            ]);

            InvoiceArchive::whereIn('id', $archives->pluck('id'))->delete();

            DB::commit();

            Cache::forget('invoices.archive_statistics');
            Cache::forget('invoices.archive_cleanup_stats');

            return response()->json([
                'success'          => true,
                'message'          => "Successfully deleted {$count} archive records.",
                'records_deleted'  => $count,
                'total_amount'     => $totalAmount,
                'formatted_amount' => $this->settings()->formatAmount($totalAmount),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Archive cleanup failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Cleanup failed: ' . $e->getMessage()], 500);
        }
    }

    public function previewCleanup(Request $request): JsonResponse
    {
        if (ob_get_level()) {
            ob_clean();
        }

        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $years      = $request->get('years', 7);
            $cutoffDate = now()->subYears($years);

            $count       = InvoiceArchive::where('deleted_at', '<', $cutoffDate)->count();
            $totalAmount = InvoiceArchive::where('deleted_at', '<', $cutoffDate)->sum('total_amount');

            return response()->json([
                'success'          => true,
                'count'            => $count,
                'total_amount'     => $totalAmount,
                'formatted_amount' => $this->settings()->formatAmount($totalAmount),
                'cutoff_date'      => $cutoffDate->format('Y-m-d'),
            ]);

        } catch (\Exception $e) {
            Log::error('Preview cleanup failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to preview cleanup: ' . $e->getMessage()], 500);
        }
    }

    /* ============================================================
     | YEAR-END ARCHIVE — admin write operations
     * ============================================================ */

    public function processYearEndArchive(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('process_year_end_archive');

        $year = $request->input('year', now()->subYear()->year);

        $lock = Cache::lock('process_year_end_archive_' . $year, 1800);
        if (!$lock->get()) {
            return redirect()->back()
                ->with('info', "A year-end archive for {$year} is already in progress.");
        }

        try {
            $archivedCount = 0;
            $errors        = 0;

            Invoice::whereYear('payment_date', $year)
                ->where('status', 'paid')
                ->whereNull('deleted_at')
                ->with(['property', 'property.landlord'])
                ->chunkById(200, function ($invoices) use (&$archivedCount, &$errors, $year) {
                    foreach ($invoices as $invoice) {
                        try {
                            DB::transaction(function () use ($invoice, $year) {
                                $this->yearEndArchiveService->archiveLandlordInvoice($invoice, $year);
                            });
                            $archivedCount++;
                        } catch (\Throwable $e) {
                            $errors++;
                            Log::error('Year-end archiving failed for invoice: ' . $e->getMessage(), [
                                'invoice_id' => $invoice->id,
                            ]);
                        }
                    }
                });

            $lock->release();

            $message = "Year-end archiving for {$year} completed. Archived: {$archivedCount} invoices. Errors: {$errors}";

            return redirect()->back()->with('success', $message);

        } catch (\Throwable $e) {
            $lock->release();
            Log::error('Year-end archive failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Year-end archiving failed: ' . $e->getMessage());
        }
    }

    public function reverseConsolidation(Invoice $bulkInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can reverse consolidations.');
        }

        $this->assertBillingAllowsWrite('reverse_consolidation');

        try {
            if (!$bulkInvoice->is_bulk_payment) {
                return redirect()->back()->with('error', 'This is not a bulk payment invoice.');
            }

            DB::beginTransaction();

            $childInvoices = $bulkInvoice->childInvoices;

            foreach ($childInvoices as $child) {
                $child->update([
                    'status'                 => 'pending',
                    'bulk_payment_id'        => null,
                    'bulk_payment_reference' => null,
                    'notes'                  => ($child->notes ?? '')
                        . "\nSeparated from bulk invoice #{$bulkInvoice->id} on " . now()->format('Y-m-d'),
                ]);
            }

            $bulkInvoice->update([
                'status'              => 'cancelled',
                'covers_periods'      => null,
                'bulk_coverage_start' => null,
                'bulk_coverage_end'   => null,
                'notes'               => ($bulkInvoice->notes ?? '')
                    . "\nConsolidation reversed on " . now()->format('Y-m-d'),
            ]);

            DB::commit();

            return redirect()->route('invoices.show', $bulkInvoice->id)
                ->with('success', "Successfully reversed consolidation. {$childInvoices->count()} invoices restored. Bulk coverage deactivated.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reversing consolidation: ' . $e->getMessage(), [
                'bulk_invoice_id' => $bulkInvoice->id,
                'user_id'         => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Error reversing consolidation. Please try again.');
        }
    }

    /* ============================================================
     | TEST / SUMMARY — read-only
     * ============================================================ */

    public function testCalculation(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['property_id' => 'required|exists:properties,id']);

        try {
            $result = $this->invoiceService->testCalculation($validated['property_id']);

            if (!($result['success'] ?? false)) {
                return response()->json($result, 400);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Test calculation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Test calculation failed'], 500);
        }
    }

    public function getGenerationSummary(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['period' => 'required|date_format:Y-m']);

        try {
            return response()->json($this->invoiceService->getGenerationSummary($validated['period']));
        } catch (\Exception $e) {
            Log::error('Error getting generation summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error getting generation summary'], 500);
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $this->assertBillingAllowsWrite('bulk_update_invoice_status');

            $validator = Validator::make($request->all(), [
                'invoice_ids'        => 'required|array',
                'invoice_ids.*'      => 'exists:invoices,id',
                'status'             => 'required|in:paid,pending,overdue,partial,cancelled',
                'payment_method'     => 'nullable|string|max:255',
                'payment_reference'  => 'nullable|string|max:255',
                'send_notifications' => 'nullable|boolean',
                'activate_coverage'  => 'nullable|boolean',
            ]);

             if ($validator->fails()) { /* ... */ }

        // ✅ NEW: enforce the offline-payment toggle when status = paid
        if ($request->status === 'paid') {
            $settings = $this->settings();
            if (!$settings->isOfflinePaymentAllowed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Office payments are currently disabled. Landlords must pay through the online gateway.',
                ], 403);
            }
        }

            $ids = $request->invoice_ids;

            if (Invoice::whereIn('id', $ids)->where('status', 'consolidated')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update consolidated invoices via bulk operation.',
                ], 422);
            }

            DB::beginTransaction();

            if ($request->status === 'paid') {
                $result = $this->invoiceService->processPaymentForInvoices(
                    $ids,
                    $request->payment_reference ?? 'BULK-' . time(),
                    $request->payment_method ?? 'manual'
                );
            } else {
                if ($request->status === 'cancelled') {
                    Invoice::whereIn('id', $ids)
                        ->where('is_bulk_payment', true)
                        ->update([
                            'covers_periods'      => null,
                            'bulk_coverage_start' => null,
                            'bulk_coverage_end'   => null,
                        ]);
                }

                $updatedCount = Invoice::whereIn('id', $ids)->update([
                    'status'     => $request->status,
                    'updated_by' => auth()->id(),
                ]);

                $result = [
                    'success'       => true,
                    'message'       => "Successfully updated {$updatedCount} invoice(s)",
                    'updated_count' => $updatedCount,
                ];
            }

            DB::commit();

            if ($request->boolean('send_notifications', false) && ($result['success'] ?? false)) {
                $settings = $this->settings();
                if ($settings->shouldSendPaymentReminders()) {
                    foreach ($ids as $invoiceId) {
                        $this->invoiceService->sendInvoiceStatusUpdateNotification($invoiceId, $request->status);
                    }
                }
            }

            Log::info('Bulk invoice status update', [
                'invoice_ids' => $ids,
                'status'      => $request->status,
                'user_id'     => auth()->id(),
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk invoice status update: ' . $e->getMessage(), [
                'invoice_ids' => $request->invoice_ids ?? [],
                'user_id'     => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error updating invoice statuses'], 500);
        }
    }

    public function export(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => false, 'message' => 'Export feature coming soon']);
    }

    public function getSettings(): JsonResponse
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $settings = $this->settings();

            return response()->json([
                'success' => true,
                'data'    => [
                    'auto_generate_invoices'  => $settings->isAutoInvoiceGenerationEnabled(),
                    'send_payment_reminders'  => $settings->shouldSendPaymentReminders(),
                    'reminder_days_before'    => $settings->getReminderDaysBefore(),
                    'grace_period_days'       => $settings->grace_period_days,
                    'late_payment_percentage' => $settings->late_payment_percentage,
                    'fixed_penalty_amount'    => $settings->fixed_penalty_amount,
                    'enable_bulk_payments'    => $settings->isBulkPaymentEnabled(),
                    'max_bulk_months'         => $settings->max_bulk_months,
                    'bulk_payment_due_days'   => $settings->bulk_payment_due_days ?? 14,
                    'next_generation_date'    => $settings->getNextInvoiceGenerationDate()->format('Y-m-d'),
                    'payment_recipient'       => [
                        'name'    => $settings->payment_account_name,
                        'phone'   => $settings->payment_mobile_number,
                        'network' => $settings->payment_network,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice settings: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve invoice settings'], 500);
        }
    }

    /* ============================================================
     | ADMIN ALERTS — internal notifications, not gated
     * ============================================================ */

    private function notifyAdminsAboutSettingChange(string $setting, $oldValue, $newValue): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $settingName = match ($setting) {
                'auto_generate_invoices' => 'Auto Invoice Generation',
                'send_payment_reminders' => 'Payment Reminders',
                default                  => ucfirst(str_replace('_', ' ', $setting)),
            };
            $status = $newValue ? 'enabled' : 'disabled';

            $notificationData = [
                'title'      => $newValue ? "⚙️ {$settingName} Enabled" : "⚙️ {$settingName} Disabled",
                'message'    => "{$settingName} has been {$status} by " . auth()->user()->name,
                'icon'       => $newValue ? 'fas fa-play-circle text-success' : 'fas fa-stop-circle text-warning',
                'category'   => 'system_settings',
                'action_url' => route('invoices.index'),
                'priority'   => 2,
                'data'       => [
                    'type'            => 'setting_change',
                    'setting'         => $setting,
                    'old_value'       => $oldValue,
                    'new_value'       => $newValue,
                    'changed_by'      => auth()->id(),
                    'changed_by_name' => auth()->user()->name,
                    'timestamp'       => now()->toISOString(),
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about setting change: ' . $e->getMessage());
        }
    }

    private function notifyAdminsAboutReminderChange(bool $oldEnabled, int $oldDays, bool $newEnabled, int $newDays): void
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                return;
            }

            $message = $newEnabled
                ? "Payment reminders enabled for {$newDays} days before due date"
                : "Payment reminders disabled";

            $notificationData = [
                'title'      => $newEnabled ? '🔔 Reminders Enabled' : '🔕 Reminders Disabled',
                'message'    => $message . " by " . auth()->user()->name,
                'icon'       => $newEnabled ? 'fas fa-bell text-success' : 'fas fa-bell-slash text-warning',
                'category'   => 'system_settings',
                'action_url' => route('invoices.index'),
                'priority'   => 2,
                'data'       => [
                    'type'            => 'reminder_settings_change',
                    'old_enabled'     => $oldEnabled,
                    'old_days'        => $oldDays,
                    'new_enabled'     => $newEnabled,
                    'new_days'        => $newDays,
                    'changed_by'      => auth()->id(),
                    'changed_by_name' => auth()->user()->name,
                    'timestamp'       => now()->toISOString(),
                ],
            ];

            foreach ($superAdmins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about reminder change: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | REAL-TIME STATUS — read-only
     * ============================================================ */

    public function getInvoiceStatus(Invoice $invoice): JsonResponse
    {
        try {
            $user = auth()->user();
            $isLandlord   = $user->isLandlord() && $invoice->property->landlord_id === $user->id;
            $isAdmin      = $user->isAdmin() || $user->isSuperAdmin();

            if (!$isLandlord && !$isAdmin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'                => $invoice->id,
                    'status'            => $invoice->status,
                    'payment_date'      => $invoice->payment_date,
                    'paid_amount'       => $invoice->paid_amount,
                    'balance'           => $invoice->balance,
                    'updated_at'        => $invoice->updated_at,
                    'is_bulk_covered'   => $invoice->is_bulk_payment && $invoice->isPaid(),
                    'coverage_periods'  => $invoice->is_bulk_payment && $invoice->isPaid() ? $invoice->covers_periods : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invoice status: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error retrieving invoice status'], 500);
        }
    }

    /* ============================================================
     | TRASH / RESTORE — admin write operations
     * ============================================================ */

    public function trash(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $filters = $request->only(['property_id', 'status', 'period', 'search', 'type']);

        $query = Invoice::onlyTrashed()->with(['property', 'property.landlord', 'creator']);

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('payment_reference', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('property', function ($propertyQuery) use ($searchTerm) {
                      $propertyQuery->where('street_name', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('house_number', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'bulk') {
                $query->where('is_bulk_payment', true);
            } elseif ($filters['type'] === 'regular') {
                $query->where('is_bulk_payment', false);
            }
        }

        $invoices = $query->orderBy('deleted_at', 'desc')->paginate(20);

        $statistics = [
            'total_trashed'          => Invoice::onlyTrashed()->count(),
            'bulk_trashed'           => Invoice::onlyTrashed()->where('is_bulk_payment', true)->count(),
            'regular_trashed'        => Invoice::onlyTrashed()->where('is_bulk_payment', false)->count(),
            'paid_trashed'           => Invoice::onlyTrashed()->where('status', 'paid')->count(),
            'pending_trashed'        => Invoice::onlyTrashed()->where('status', 'pending')->count(),
            'overdue_trashed'        => Invoice::onlyTrashed()->where('status', 'overdue')->count(),
            'total_amount_trashed'   => Invoice::onlyTrashed()->sum('amount'),
            'total_penalties_trashed'=> Invoice::onlyTrashed()->sum('penalty_amount'),
        ];

        $properties = Property::with('landlord')
            ->orderBy('street_name')
            ->orderBy('house_number')
            ->get();

        $periods = Invoice::onlyTrashed()
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        $settings              = $this->settings();
        $remindersEnabled      = $settings->shouldSendPaymentReminders();
        $reminderDays          = $settings->getReminderDaysBefore();
        $gracePeriodDays       = $settings->grace_period_days ?? 7;
        $trashRetentionDays    = $settings->trash_retention_days ?? 30;
        $autoGenerationEnabled = $settings->isAutoInvoiceGenerationEnabled();
        $bulkPaymentEnabled    = $settings->isBulkPaymentEnabled();
        $maxBulkMonths         = $settings->max_bulk_months ?? 12;

        return view('admin.invoices.trash', compact(
            'invoices', 'properties', 'periods', 'statistics', 'filters',
            'settings', 'remindersEnabled', 'reminderDays', 'gracePeriodDays',
            'trashRetentionDays', 'autoGenerationEnabled', 'bulkPaymentEnabled',
            'maxBulkMonths'
        ));
    }

    public function restore($id)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('landlord.invoices')->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('restore_landlord_invoice');

        try {
            DB::beginTransaction();

            $invoice = Invoice::onlyTrashed()->findOrFail($id);

            $restoreCheck = $this->canRestoreInvoice($invoice);
            if (!$restoreCheck['can_restore']) {
                return redirect()->back()->with('error', $restoreCheck['message']);
            }

            $restoreData = [
                'restored_by'         => auth()->id(),
                'restored_at'         => now(),
                'restoration_reason'  => 'Manual restoration by ' . auth()->user()->name,
                'original_deleted_at' => $invoice->deleted_at,
                'restored_by_name'    => auth()->user()->name,
            ];

            $metadata = $invoice->metadata ?? [];
            $metadata['restoration_history']   = array_merge($metadata['restoration_history'] ?? [], [$restoreData]);
            $metadata['last_restored_by']      = auth()->id();
            $metadata['last_restored_by_name'] = auth()->user()->name;
            $metadata['last_restored_at']      = now()->toDateTimeString();

            $invoice->update(['metadata' => $metadata]);
            $invoice->restore();

            Log::info('Invoice restored', [
                'invoice_id'          => $invoice->id,
                'invoice_number'      => $invoice->invoice_number,
                'restored_by'         => auth()->id(),
                'restored_by_name'    => auth()->user()->name,
                'original_deleted_at' => $invoice->deleted_at,
            ]);

            $invoice->addNote("Invoice restored from trash by " . auth()->user()->name);

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', "Invoice #{$invoice->invoice_number} has been restored successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore invoice: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'user_id'    => auth()->id(),
                'exception'  => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'Failed to restore invoice: ' . $e->getMessage());
        }
    }

    public function bulkRestore(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('bulk_restore_landlord_invoices');

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid selection'], 422);
            }
            return redirect()->back()->with('error', 'Invalid invoice selection.');
        }

        try {
            DB::beginTransaction();

            $restoredCount    = 0;
            $failedRestores   = [];
            $restoredInvoices = [];

            foreach ($request->invoice_ids as $invoiceId) {
                $invoice = Invoice::onlyTrashed()->find($invoiceId);

                if (!$invoice) {
                    $failedRestores[] = $invoiceId;
                    continue;
                }

                $restoreCheck = $this->canRestoreInvoice($invoice);
                if (!$restoreCheck['can_restore']) {
                    $failedRestores[] = ['id' => $invoiceId, 'reason' => $restoreCheck['message']];
                    continue;
                }

                $metadata = $invoice->metadata ?? [];
                $metadata['restoration_history'] = array_merge(
                    $metadata['restoration_history'] ?? [],
                    [['restored_by' => auth()->id(), 'restored_at' => now(), 'bulk_restore' => true]]
                );
                $invoice->update(['metadata' => $metadata]);
                $invoice->restore();

                $restoredCount++;
                $restoredInvoices[] = $invoice->id;

                $invoice->addNote("Restored in bulk operation by " . auth()->user()->name);
            }

            DB::commit();

            $message = "Successfully restored {$restoredCount} invoice(s).";
            if (!empty($failedRestores)) {
                $message .= " Failed to restore " . count($failedRestores) . " invoice(s).";
            }

            Log::info('Bulk invoice restoration', [
                'restored_count'    => $restoredCount,
                'restored_invoices' => $restoredInvoices,
                'failed_restores'   => $failedRestores,
                'user_id'           => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => [
                        'restored_count'    => $restoredCount,
                        'restored_invoices' => $restoredInvoices,
                        'failed_restores'   => $failedRestores,
                    ],
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage(), [
                'invoice_ids' => $request->invoice_ids,
                'user_id'     => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to restore invoices: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to restore invoices: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only super administrators can permanently delete invoices.');
        }

        $this->assertBillingAllowsWrite('force_delete_landlord_invoice');

        try {
            DB::beginTransaction();

            $invoice = Invoice::onlyTrashed()->findOrFail($id);

            $forceDeleteCheck = $this->canForceDeleteInvoice($invoice);
            if (!$forceDeleteCheck['can_delete']) {
                return redirect()->back()->with('error', $forceDeleteCheck['message']);
            }

            Log::warning('Invoice permanently deleted', [
                'invoice_id'          => $invoice->id,
                'invoice_number'      => $invoice->invoice_number,
                'property_id'         => $invoice->property_id,
                'period'              => $invoice->period,
                'amount'              => $invoice->amount,
                'status'              => $invoice->status,
                'deleted_by'          => auth()->id(),
                'deleted_by_name'     => auth()->user()->name,
                'original_deleted_at' => $invoice->deleted_at,
            ]);

            $invoice->forceDelete();

            DB::commit();

            return redirect()->route('invoices.trash')
                ->with('success', "Invoice #{$invoice->invoice_number} has been permanently deleted.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete invoice: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Failed to permanently delete invoice: ' . $e->getMessage());
        }
    }

    public function bulkForceDelete(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Only super administrators can permanently delete invoices.'], 403);
            }
            return redirect()->back()->with('error', 'Only super administrators can permanently delete invoices.');
        }

        $this->assertBillingAllowsWrite('bulk_force_delete_landlord_invoices');

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid selection'], 422);
            }
            return redirect()->back()->with('error', 'Invalid invoice selection.');
        }

        try {
            DB::beginTransaction();

            $deletedCount    = 0;
            $failedDeletes   = [];
            $deletedInvoices = [];

            foreach ($request->invoice_ids as $invoiceId) {
                $invoice = Invoice::onlyTrashed()->find($invoiceId);

                if (!$invoice) {
                    $failedDeletes[] = $invoiceId;
                    continue;
                }

                $forceDeleteCheck = $this->canForceDeleteInvoice($invoice);
                if (!$forceDeleteCheck['can_delete']) {
                    $failedDeletes[] = ['id' => $invoiceId, 'reason' => $forceDeleteCheck['message']];
                    continue;
                }

                Log::warning('Invoice permanently deleted (bulk)', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'deleted_by'     => auth()->id(),
                ]);

                $invoice->forceDelete();
                $deletedCount++;
                $deletedInvoices[] = $invoice->id;
            }

            DB::commit();

            $message = "Successfully permanently deleted {$deletedCount} invoice(s).";
            if (!empty($failedDeletes)) {
                $message .= " Failed to delete " . count($failedDeletes) . " invoice(s).";
            }

            Log::info('Bulk permanent invoice deletion', [
                'deleted_count'    => $deletedCount,
                'deleted_invoices' => $deletedInvoices,
                'failed_deletes'   => $failedDeletes,
                'user_id'          => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => [
                        'deleted_count'    => $deletedCount,
                        'deleted_invoices' => $deletedInvoices,
                        'failed_deletes'   => $failedDeletes,
                    ],
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk force delete failed: ' . $e->getMessage(), [
                'invoice_ids' => $request->invoice_ids,
                'user_id'     => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete invoices: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to delete invoices: ' . $e->getMessage());
        }
    }

    private function canRestoreInvoice(Invoice $invoice): array
    {
        if (!$invoice->property) {
            return [
                'can_restore' => false,
                'message'     => "Cannot restore: Property #{$invoice->property_id} no longer exists.",
            ];
        }

        $existingInvoice = Invoice::where('property_id', $invoice->property_id)
            ->where('period', $invoice->period)
            ->where('is_bulk_payment', $invoice->is_bulk_payment)
            ->where('id', '!=', $invoice->id)
            ->exists();

        if ($existingInvoice) {
            return [
                'can_restore' => false,
                'message'     => "Cannot restore: Another invoice already exists for period {$invoice->period}.",
            ];
        }

        if (!$invoice->is_bulk_payment) {
            $isCovered = $this->invoiceService->isPeriodCoveredByBulkPayment($invoice->property, $invoice->period);
            if ($isCovered) {
                return [
                    'can_restore' => false,
                    'message'     => "Cannot restore: Period {$invoice->period} is covered by an active bulk payment.",
                ];
            }
        }

        if ($invoice->is_bulk_payment && $invoice->status === 'paid') {
            return [
                'can_restore' => false,
                'message'     => "Cannot restore: Paid bulk invoice cannot be restored. It may have active coverage.",
            ];
        }

        return ['can_restore' => true, 'message' => 'Can restore'];
    }

    private function canForceDeleteInvoice(Invoice $invoice): array
    {
        $daysInTrash      = $invoice->deleted_at->diffInDays(now());
        $settings         = $this->settings();
        $minRetentionDays = $settings->trash_retention_days ?? 30;

        if ($daysInTrash < $minRetentionDays) {
            return [
                'can_delete' => false,
                'message'    => "Cannot permanently delete: Invoice has been in trash for only {$daysInTrash} days. Minimum retention is {$minRetentionDays} days.",
            ];
        }

        if ($invoice->is_bulk_payment && $invoice->status === 'paid' && !empty($invoice->covers_periods)) {
            return [
                'can_delete' => false,
                'message'    => "Cannot permanently delete: This bulk invoice has active coverage periods.",
            ];
        }

        if ($invoice->payment()->exists()) {
            return [
                'can_delete' => false,
                'message'    => "Cannot permanently delete: This invoice has associated payment records.",
            ];
        }

        return ['can_delete' => true, 'message' => 'Can delete'];
    }

    public function emptyTrash(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only super administrators can empty the trash.');
        }

        $this->assertBillingAllowsWrite('empty_landlord_invoice_trash');

        $validator = Validator::make($request->all(), [
            'confirm'         => 'required|accepted',
            'older_than_days' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $query = Invoice::onlyTrashed();

            if ($request->has('older_than_days')) {
                $cutoffDate = now()->subDays($request->older_than_days);
                $query->where('deleted_at', '<=', $cutoffDate);
            }

            $count = $query->count();

            Log::warning('Trash emptying', [
                'count'           => $count,
                'user_id'         => auth()->id(),
                'older_than_days' => $request->older_than_days,
            ]);

            $query->forceDelete();

            DB::commit();

            $message = "Successfully permanently deleted {$count} invoice(s) from trash.";
            if ($request->has('older_than_days')) {
                $message .= " (Deleted invoices older than {$request->older_than_days} days)";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage(), ['user_id' => auth()->id()]);
            return redirect()->back()->with('error', 'Failed to empty trash: ' . $e->getMessage());
        }
    }

    public function checkCanDelete(Invoice $invoice)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return response()->json(['can_delete' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $canDelete = true;
            $message   = 'Invoice can be deleted.';

            if ($invoice->is_bulk_payment && $invoice->childInvoices()->count() > 0) {
                $canDelete = false;
                $message   = 'Cannot delete a bulk invoice that has child invoices. Reverse consolidation first.';
            }

            if ($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods)) {
                $canDelete = false;
                $message   = 'Cannot delete a paid bulk invoice with active coverage. Reverse consolidation first.';
            }

            if ($invoice->payment()->exists()) {
                $canDelete = false;
                $message   = 'Cannot delete an invoice with associated payment records.';
            }

            return response()->json([
                'can_delete'     => $canDelete,
                'message'        => $message,
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking delete eligibility: ' . $e->getMessage());
            return response()->json(['can_delete' => false, 'message' => 'Error checking delete eligibility.'], 500);
        }
    }

    /* ============================================================
     | BULK PAID / EXPORTS — admin write / read
     * ============================================================ */

    public function bulkMarkPaid(Request $request)
{
    try {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ NEW: enforce the offline-payment toggle
        $settings = $this->settings();
        if (!$settings->isOfflinePaymentAllowed()) {
            return redirect()->back()->with(
                'error',
                '❌ Office payments are currently disabled. Landlords must pay through the online gateway.'
            );
        }

            $this->assertBillingAllowsWrite('bulk_mark_landlord_invoices_paid');

            $validator = Validator::make($request->all(), [
                'invoice_ids'       => 'required|string',
                'payment_method'    => 'nullable|string|max:255',
                'payment_reference' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid request.');
            }

            $invoiceIds = json_decode($request->invoice_ids, true);
            if (!is_array($invoiceIds) || empty($invoiceIds)) {
                return redirect()->back()->with('error', 'No valid invoices selected.');
            }

            DB::beginTransaction();

            $updatedCount = 0;
            $skippedIds   = [];

            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::find($invoiceId);

                if (!$invoice
                    || $invoice->status === 'consolidated'
                    || $invoice->isPaid()) {
                    $skippedIds[] = $invoiceId;
                    continue;
                }

                $invoice->update([
                    'status'            => Invoice::STATUS_PAID,
                    'paid_amount'       => $invoice->total_amount,
                    'balance'           => 0,
                    'payment_method'    => $request->payment_method,
                    'payment_reference' => $request->payment_reference,
                    'payment_date'      => now(),
                    'updated_by'        => auth()->id(),
                ]);

                $updatedCount++;
            }

            DB::commit();

            $message = "Successfully marked {$updatedCount} invoice(s) as paid.";
            if (!empty($skippedIds)) {
                $message .= " Skipped " . count($skippedIds) . " invoice(s) (already paid, consolidated, or missing).";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk mark paid failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process bulk payment.');
        }
    }

    /* ============================================================
     | EXPORTS — PDF / CSV
     * ============================================================ */

    /**
     * Single source of truth for invoice queries used by exports.
     * Delegates to the service so the UI and exports never drift.
     */
    private function buildInvoicesQuery(array $filters, User $user)
    {
        return $this->invoiceService->getInvoicesWithFilters($filters, $user);
    }

    public function exportCurrentPage(Request $request)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $filters = $request->only([
                'property_id', 'status', 'period', 'search', 'type',
                'has_parent', 'is_bulk', 'has_coverage', 'has_discount', 'has_penalty',
            ]);
            $user = auth()->user();

            $query   = $this->buildInvoicesQuery($filters, $user);
            $page    = $request->get('page', 1);
            $perPage = 15;

            $invoices   = $query->paginate($perPage, ['*'], 'page', $page);
            $statistics = $this->invoiceService->getInvoiceStatistics($user);
            $settings   = $this->settings();

            $pdf = Pdf::loadView('admin.invoices.pdf-export', compact('invoices', 'statistics', 'settings', 'filters'));

            return $pdf->download('invoices_' . now()->format('Y-m-d_His') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Failed to export current page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function exportCurrentPagePdf(Request $request)
    {
        return $this->exportCurrentPage($request);
    }

    public function exportAllFiltered(Request $request)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $filters = $request->only([
                'property_id', 'status', 'period', 'search', 'type',
                'has_parent', 'is_bulk', 'has_coverage', 'has_discount', 'has_penalty',
            ]);
            $user = auth()->user();

            $invoices   = $this->buildInvoicesQuery($filters, $user)->get();
            $statistics = $this->invoiceService->getInvoiceStatistics($user);
            $settings   = $this->settings();

            $pdf = Pdf::loadView('admin.invoices.pdf-export', compact('invoices', 'statistics', 'settings', 'filters'));

            return $pdf->download('invoices_export_' . now()->format('Y-m-d_His') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Failed to export all filtered: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function exportAllFilteredPdf(Request $request)
    {
        return $this->exportAllFiltered($request);
    }

    public function bulkExport(Request $request)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
                }
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $validator = Validator::make($request->all(), [
                'invoice_ids' => 'required|string',
                'format'      => 'nullable|in:pdf,csv,xlsx',
            ]);

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid selection'], 422);
                }
                return redirect()->back()->with('error', 'Invalid invoice selection.');
            }

            $invoiceIds = array_filter(explode(',', $request->invoice_ids), 'is_numeric');

            if (empty($invoiceIds)) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'No valid invoice IDs provided'], 422);
                }
                return redirect()->back()->with('error', 'No valid invoices selected.');
            }

            $invoices = Invoice::with(['property', 'property.landlord', 'payment'])
                ->whereIn('id', $invoiceIds)
                ->orderBy('period', 'desc')
                ->get();

            if ($invoices->isEmpty()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'No invoices found'], 404);
                }
                return redirect()->back()->with('error', 'No invoices found.');
            }

            $settings = $this->settings();

            $pdf = Pdf::loadView('admin.invoices.pdf-export-selected', compact('invoices', 'settings'));

            $filename = 'invoices_selected_' . count($invoices) . '_' . now()->format('Y-m-d_His') . '.pdf';

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'PDF generated successfully',
                    'filename' => $filename,
                    'count'    => $invoices->count(),
                ]);
            }

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Bulk export failed: ' . $e->getMessage(), [
                'invoice_ids' => $request->invoice_ids ?? null,
                'user_id'     => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function bulkExportAdminPdf(Request $request)
    {
        return $this->bulkExport($request);
    }

    public function exportAdminInvoicePdf(Invoice $invoice)
    {
        return $this->exportSinglePdf($invoice);
    }

    public function exportSinglePdf(Invoice $invoice)
    {
        try {
            $user = auth()->user();

            if ($user->isLandlord()) {
                if ($invoice->property->landlord_id !== $user->id) {
                    return redirect()->back()->with('error', 'Unauthorized access to invoice.');
                }
                $view = 'landlord.invoices.pdf-single';
            } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
                $view = 'admin.invoices.pdf-single';
            } else {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $invoice->load(['property', 'property.landlord', 'payment', 'bulkPayment', 'childInvoices']);
            $settings = $this->settings();

            $pdf = Pdf::loadView($view, compact('invoice', 'settings'));

            return $pdf->download('invoice_' . $invoice->invoice_number . '_' . now()->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Single invoice PDF export failed: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function bulkPrintAdmin(Request $request)
    {
        try {
            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $ids = array_filter(explode(',', $request->get('ids', '')), 'is_numeric');

            if (empty($ids)) {
                return redirect()->back()->with('error', 'No invoices selected.');
            }

            $invoices = Invoice::with(['property', 'property.landlord', 'payment'])
                ->whereIn('id', $ids)
                ->orderBy('period', 'desc')
                ->get();

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No valid invoices found.');
            }

            $settings = $this->settings();

            return view('admin.invoices.bulk-print', compact('invoices', 'settings'));

        } catch (\Exception $e) {
            Log::error('Bulk print failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to prepare invoices for printing: ' . $e->getMessage());
        }
    }

    public function landlordExportCurrentPage(Request $request)
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $landlordId = auth()->id();
            $filters    = $request->only(['property_id', 'status', 'type', 'coverage']);

            $query   = $this->getLandlordInvoicesQuery($landlordId, $filters);
            $page    = $request->get('page', 1);
            $perPage = 15;

            $invoices   = $query->paginate($perPage, ['*'], 'page', $page);
            $statistics = $this->invoiceService->getInvoiceStatistics(auth()->user());
            $settings   = $this->settings();

            $properties    = Property::where('landlord_id', $landlordId)->get();
            $bulkCoverages = $this->buildLandlordBulkCoverages($properties);

            $pdf = Pdf::loadView('landlord.invoices.pdf-export', compact(
                'invoices', 'statistics', 'settings', 'filters', 'properties', 'bulkCoverages'
            ));

            return $pdf->download('invoices_' . now()->format('Y-m-d_His') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Failed to export landlord current page: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'filters' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function landlordExportAllInvoices(Request $request)
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $landlordId = auth()->id();
            $filters    = $request->only(['property_id', 'status', 'type', 'coverage']);

            $invoices   = $this->getLandlordInvoicesQuery($landlordId, $filters)->get();
            $statistics = $this->invoiceService->getInvoiceStatistics(auth()->user());
            $settings   = $this->settings();

            $properties    = Property::where('landlord_id', $landlordId)->get();
            $bulkCoverages = $this->buildLandlordBulkCoverages($properties);

            $pdf = Pdf::loadView('landlord.invoices.pdf-export', compact(
                'invoices', 'statistics', 'settings', 'filters', 'properties', 'bulkCoverages'
            ));

            return $pdf->download('all_invoices_' . now()->format('Y-m-d_His') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Failed to export all landlord invoices: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'filters' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function landlordBulkExport(Request $request)
    {
        try {
            if (!auth()->user()->isLandlord()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
                }
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $validator = Validator::make($request->all(), [
                'invoice_ids' => 'required|string',
                'format'      => 'nullable|in:pdf,csv',
            ]);

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid selection'], 422);
                }
                return redirect()->back()->with('error', 'Invalid invoice selection.');
            }

            $invoiceIds = array_filter(explode(',', $request->invoice_ids), 'is_numeric');

            if (empty($invoiceIds)) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'No valid invoice IDs provided'], 422);
                }
                return redirect()->back()->with('error', 'No valid invoices selected.');
            }

            $landlordId = auth()->id();

            $invoices = Invoice::with(['property', 'payment'])
                ->whereIn('id', $invoiceIds)
                ->whereHas('property', function ($query) use ($landlordId) {
                    $query->where('landlord_id', $landlordId);
                })
                ->orderBy('due_date', 'desc')
                ->get();

            if ($invoices->isEmpty()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'No invoices found'], 404);
                }
                return redirect()->back()->with('error', 'No invoices found.');
            }

            $statistics    = $this->invoiceService->getInvoiceStatistics(auth()->user());
            $settings      = $this->settings();
            $properties    = Property::where('landlord_id', $landlordId)->get();
            $bulkCoverages = $this->buildLandlordBulkCoverages($properties);

            $pdf = Pdf::loadView('landlord.invoices.pdf-export-selected', compact(
                'invoices', 'statistics', 'settings', 'properties', 'bulkCoverages'
            ));

            $filename = 'selected_invoices_' . count($invoices) . '_' . now()->format('Y-m-d_His') . '.pdf';

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'PDF generated successfully',
                    'filename' => $filename,
                    'count'    => $invoices->count(),
                ]);
            }

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Landlord bulk export failed: ' . $e->getMessage(), [
                'invoice_ids' => $request->invoice_ids ?? null,
                'user_id'     => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function landlordExportSinglePdf(Invoice $invoice)
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            if ($invoice->property->landlord_id !== auth()->id()) {
                return redirect()->back()->with('error', 'Unauthorized access to invoice.');
            }

            $invoice->load(['property', 'payment', 'bulkPayment', 'childInvoices']);
            $settings = $this->settings();

            $pdf = Pdf::loadView('landlord.invoices.pdf-single', compact('invoice', 'settings'));

            $filename = 'invoice_' . ($invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT))
                      . '_' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Landlord single invoice PDF export failed: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    private function getLandlordInvoicesQuery(int $landlordId, array $filters)
    {
        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('id');

        $query = Invoice::with(['property', 'payment', 'bulkPayment', 'childInvoices'])
            ->whereIn('property_id', $propertyIds);

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'consolidated');
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'bulk') {
                $query->where('is_bulk_payment', true);
            } elseif ($filters['type'] === 'regular') {
                $query->where('is_bulk_payment', false)->whereNull('bulk_payment_id');
            }
        }

        if (!empty($filters['coverage'])) {
            $properties = Property::where('landlord_id', $landlordId)->get();
            $coveredInvoiceIds = [];

            foreach ($properties as $property) {
                $coverages = $this->invoiceService->getActiveBulkCoverages($property);
                foreach ($coverages as $coverage) {
                    if (isset($coverage['periods']) && is_array($coverage['periods'])) {
                        $coveredInvoices = Invoice::where('property_id', $property->id)
                            ->whereIn('period', $coverage['periods'])
                            ->where('is_bulk_payment', false)
                            ->pluck('id')
                            ->toArray();
                        $coveredInvoiceIds = array_merge($coveredInvoiceIds, $coveredInvoices);
                    }
                }
            }

            if ($filters['coverage'] === 'covered') {
                if (!empty($coveredInvoiceIds)) {
                    $query->whereIn('id', $coveredInvoiceIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($filters['coverage'] === 'not_covered') {
                if (!empty($coveredInvoiceIds)) {
                    $query->whereNotIn('id', $coveredInvoiceIds);
                }
            }
        }

        return $query->orderBy('due_date', 'desc');
    }

    private function buildLandlordBulkCoverages($properties): array
    {
        $bulkCoverages = [];

        foreach ($properties as $property) {
            $coverages = $this->invoiceService->getActiveBulkCoverages($property);
            if (!empty($coverages)) {
                $bulkCoverages[$property->id] = $coverages;
            }
        }

        return $bulkCoverages;
    }

    /* ============================================================
     | LANDLORD STATUS API
     * ============================================================ */

    public function getLandlordInvoiceStatus(Invoice $invoice): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord() || $invoice->property->landlord_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'               => $invoice->id,
                    'status'           => $invoice->status,
                    'payment_date'     => $invoice->payment_date,
                    'paid_amount'      => $invoice->paid_amount,
                    'balance'          => $invoice->balance,
                    'updated_at'       => $invoice->updated_at,
                    'is_bulk_covered'  => $invoice->is_bulk_payment && $invoice->isPaid(),
                    'coverage_periods' => $invoice->is_bulk_payment && $invoice->isPaid() ? $invoice->covers_periods : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting landlord invoice status: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error retrieving invoice status'], 500);
        }
    }

    public function getLandlordOutstandingSummary(): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $landlordId  = auth()->id();
            $propertyIds = Property::where('landlord_id', $landlordId)->pluck('id');

            $totalDue = Invoice::whereIn('property_id', $propertyIds)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum(DB::raw('amount + COALESCE(penalty_amount, 0)'));

            $outstandingCount = Invoice::whereIn('property_id', $propertyIds)
                ->whereIn('status', ['pending', 'overdue'])
                ->count();

            $overdueCount = Invoice::whereIn('property_id', $propertyIds)
                ->where('status', 'overdue')
                ->count();

            $totalPaid = Invoice::whereIn('property_id', $propertyIds)
                ->where('status', 'paid')
                ->sum(DB::raw('amount + COALESCE(penalty_amount, 0)'));

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_due'         => $totalDue,
                    'outstanding_count' => $outstandingCount,
                    'overdue_count'     => $overdueCount,
                    'total_paid'        => $totalPaid,
                    'currency_symbol'   => $this->settings()->currency_symbol ?? '₵',
                    'last_updated'      => now()->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting landlord outstanding summary: ' . $e->getMessage(), ['user_id' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Error retrieving summary'], 500);
        }
    }

    public function getLandlordInvoiceNotificationStatus(Invoice $invoice): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord() || $invoice->property->landlord_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->invoiceService->getInvoiceNotificationStatus($invoice->id),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invoice notification status: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'user_id'    => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error retrieving notification status'], 500);
        }
    }

    public function getLandlordCoverageSummary(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->isLandlord()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $landlordId = auth()->id();
            $properties = Property::where('landlord_id', $landlordId)->get();

            $coverageSummary    = [];
            $totalCoveredMonths = 0;
            $activeCoverages    = 0;
            $settings           = $this->settings();

            foreach ($properties as $property) {
                $coverages = $this->invoiceService->getActiveBulkCoverages($property);

                if (!empty($coverages)) {
                    $coverageSummary[$property->id] = [
                        'property_name'    => $property->property_name ?? $property->street_name,
                        'property_address' => $property->house_number . ' ' . $property->street_name,
                        'coverages'        => [],
                    ];

                    foreach ($coverages as $coverage) {
                        $periods          = $coverage['periods'] ?? [];
                        $formattedPeriods = collect($periods)
                            ->map(fn ($p) => Carbon::parse($p . '-01')->format('F Y'))
                            ->toArray();

                        $coverageSummary[$property->id]['coverages'][] = [
                            'invoice_id'             => $coverage['invoice_id'],
                            'invoice_number'         => $coverage['invoice_number'] ?? 'INV-' . str_pad($coverage['invoice_id'], 6, '0', STR_PAD_LEFT),
                            'payment_date'           => $coverage['payment_date'],
                            'formatted_payment_date' => $coverage['payment_date'] ? Carbon::parse($coverage['payment_date'])->format('M d, Y') : null,
                            'periods'                => $periods,
                            'formatted_periods'      => $formattedPeriods,
                            'months_covered'         => count($periods),
                            'amount'                 => $coverage['amount'] ?? 0,
                            'formatted_amount'       => $settings->formatAmount($coverage['amount'] ?? 0),
                        ];

                        $totalCoveredMonths += count($periods);
                        $activeCoverages++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'active_coverages'     => $activeCoverages,
                    'total_covered_months' => $totalCoveredMonths,
                    'coverage_summary'     => $coverageSummary,
                    'has_coverage'         => $activeCoverages > 0,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting landlord coverage summary: ' . $e->getMessage(), ['user_id' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Error retrieving coverage summary'], 500);
        }
    }

    /* ============================================================
     | YEAR-END MANAGEMENT — read + write split
     * ============================================================ */

    public function yearEndManagement(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $stats = $this->yearEndArchiveService->getYearEndStatistics(
            null,
            YearEndArchiveService::TYPE_LANDLORD
        );

        $unpaidInvoices = Invoice::where('status', '!=', Invoice::STATUS_PAID)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['property', 'property.landlord'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $archiveLogs = collect();
if (class_exists(\App\Models\ArchiveCleanupLog::class)) {
    try {
        $archiveLogs = \App\Models\ArchiveCleanupLog::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    } catch (\Throwable $e) {
        Log::warning('Could not fetch archive logs: ' . $e->getMessage());
    }
}

        return view('admin.invoices.year-end-management', compact('stats', 'unpaidInvoices', 'archiveLogs'))
            ->with([
                'system_settings' => $this->settings(),
                'settings'        => $this->settings(),
            ]);
    }

    public function getYearEndStatistics(Request $request, $year = null)
    {
        if (ob_get_level()) {
            ob_clean();
        }

        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $year = $year ?? $request->input('year', now()->subYear()->year);

        $stats = $this->yearEndArchiveService->getYearEndStatistics(
            $year,
            YearEndArchiveService::TYPE_LANDLORD
        );

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function sendYearEndReminders(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $type = $request->input('type', 'landlord');

        $results = $type === 'landlord'
            ? $this->yearEndArchiveService->sendYearEndRemindersLandlord()
            : $this->yearEndArchiveService->sendYearEndRemindersTenant();

        $message = "Sent {$results['reminders_sent']} reminders ({$results['paid_reminders']} for paid, {$results['unpaid_reminders']} for unpaid)";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'          => true,
                'message'          => $message,
                'reminders_sent'   => $results['reminders_sent'],
                'paid_reminders'   => $results['paid_reminders'],
                'unpaid_reminders' => $results['unpaid_reminders'],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function unpaidFromPreviousYears(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = Invoice::where('status', '!=', Invoice::STATUS_PAID)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['property', 'property.landlord']);

        if ($request->filled('year')) {
            $query->where(function ($q) use ($request) {
                $q->whereYear('created_at', $request->year)
                  ->orWhere('period', 'like', $request->year . '-%');
            });
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($sub) use ($search) {
                      $sub->where('street_name', 'like', "%{$search}%")
                          ->orWhere('house_number', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        $totalOutstanding = (clone $query)->sum(DB::raw('amount + COALESCE(penalty_amount, 0)'));
        $uniqueProperties = (clone $query)->distinct('property_id')->count('property_id');
        $oldestYearRaw    = (clone $query)->min('created_at');
        $oldestYear       = $oldestYearRaw ? Carbon::parse($oldestYearRaw)->year : null;

        $availableYears = Invoice::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $properties = Property::where('status', 'active')->orderBy('street_name')->get();

        $retentionMonths = $this->settings()->paid_invoice_retention_months_landlord ?? 3;

        return view('admin.invoices.unpaid-previous-years', compact(
            'invoices', 'totalOutstanding', 'uniqueProperties', 'oldestYear',
            'availableYears', 'properties', 'retentionMonths'
        ))->with([
            'system_settings' => $this->settings(),
            'settings'        => $this->settings(),
        ]);
    }

    /* ============================================================
     | REMINDERS — communication actions, not gated
     * ============================================================ */

    public function sendReminder(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $type    = $invoice->isOverdue() ? 'overdue' : 'payment_reminder';
            $results = $this->sendNotificationThroughChannels($invoice, $type);

            $reminderNote = "Reminder sent on " . now()->format('Y-m-d H:i:s')
                          . " by " . auth()->user()->name
                          . " via: " . implode(', ', $results['channels_used']);
            $invoice->addNote($reminderNote);

            $message = !empty($results['channels_used'])
                ? "Reminder sent successfully via: " . implode(', ', $results['channels_used'])
                : "Failed to send reminder through any channel.";

            if ($request->ajax()) {
                return response()->json([
                    'success'       => !empty($results['channels_used']),
                    'message'       => $message,
                    'channels_used' => $results['channels_used'],
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to send reminder: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to send reminder: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to send reminder');
        }
    }

    public function bulkSendReminders(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $invoices = Invoice::whereIn('id', $request->invoice_ids)
            ->with(['property', 'property.landlord'])
            ->get();

        $results = [
            'total'         => $invoices->count(),
            'sent'          => 0,
            'failed'        => 0,
            'channels_used' => [],
            'details'       => [],
        ];

        foreach ($invoices as $invoice) {
            try {
                $type                = $invoice->isOverdue() ? 'overdue' : 'payment_reminder';
                $notificationResults = $this->sendNotificationThroughChannels($invoice, $type);

                if (!empty($notificationResults['channels_used'])) {
                    $results['sent']++;
                    $results['channels_used'] = array_unique(array_merge(
                        $results['channels_used'],
                        $notificationResults['channels_used']
                    ));
                } else {
                    $results['failed']++;
                }

                $results['details'][$invoice->id] = [
                    'success'       => !empty($notificationResults['channels_used']),
                    'channels_used' => $notificationResults['channels_used'],
                ];

            } catch (\Exception $e) {
                $results['failed']++;
                Log::error("Failed to send reminder for invoice {$invoice->id}: " . $e->getMessage());
            }
        }

        $message = "Sent reminders: {$results['sent']} of {$results['total']} invoices";
        if (!empty($results['channels_used'])) {
            $message .= " via: " . implode(', ', $results['channels_used']);
        }

        return response()->json([
            'success'       => true,
            'message'       => $message,
            'sent_count'    => $results['sent'],
            'failed_count'  => $results['failed'],
            'channels_used' => $results['channels_used'],
            'details'       => $results['details'],
        ]);
    }

    /* ============================================================
     | EXPORTS (UNPAID / SELECTED) — read-only
     * ============================================================ */

    public function exportUnpaidInvoices(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = Invoice::where('status', '!=', Invoice::STATUS_PAID)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['property', 'property.landlord']);

        if ($request->filled('year')) {
            $query->where(function ($q) use ($request) {
                $q->whereYear('created_at', $request->year)
                  ->orWhere('period', 'like', $request->year . '-%');
            });
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($sub) use ($search) {
                      $sub->where('street_name', 'like', "%{$search}%")
                          ->orWhere('house_number', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        $filename = 'unpaid_invoices_' . date('Y-m-d_His') . '.csv';
        $handle   = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number', 'Property', 'Landlord Name', 'Landlord Email',
            'Period', 'Due Date', 'Amount', 'Penalty', 'Total Amount',
            'Balance', 'Status', 'Original Year',
        ]);

        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->invoice_number,
                $invoice->property->property_name ?? $invoice->property->street_name,
                $invoice->property->landlord->name ?? 'Unknown',
                $invoice->property->landlord->email ?? '',
                $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : 'N/A',
                $invoice->due_date->format('Y-m-d'),
                $invoice->amount,
                $invoice->penalty_amount ?? 0,
                $invoice->total_amount,
                $invoice->balance,
                $invoice->status,
                $invoice->original_year ?? $invoice->created_at->year,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function exportSelectedInvoices(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $ids = array_filter(explode(',', $request->get('ids', '')), 'is_numeric');

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No invoices selected.');
        }

        $invoices = Invoice::whereIn('id', $ids)
            ->with(['property', 'property.landlord'])
            ->get();

        $filename = 'selected_invoices_' . date('Y-m-d_His') . '.csv';
        $handle   = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number', 'Property', 'Landlord', 'Period', 'Due Date',
            'Amount', 'Penalty', 'Total', 'Balance', 'Status',
        ]);

        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->invoice_number,
                $invoice->property->property_name ?? $invoice->property->street_name,
                $invoice->property->landlord->name ?? 'Unknown',
                $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : 'N/A',
                $invoice->due_date->format('Y-m-d'),
                $invoice->amount,
                $invoice->penalty_amount ?? 0,
                $invoice->total_amount,
                $invoice->balance,
                $invoice->status,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /* ============================================================
     | POST-PAYMENT ARCHIVE — admin write operations
     * ============================================================ */

    public function processPostPaymentArchive(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $this->assertBillingAllowsWrite('process_post_payment_archive');

        $results = $this->yearEndArchiveService->processPostPaymentArchiveLandlord();

        $message = "Post-payment archiving completed. Processed: {$results['total']}, Archived: {$results['archived']}";
        if ($results['errors'] > 0) {
            $message .= ", Errors: {$results['errors']}";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => $message,
                'total'    => $results['total'],
                'archived' => $results['archived'],
                'errors'   => $results['errors'],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function previewPostPaymentArchive(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $retentionMonths = $this->settings()->paid_invoice_retention_months_landlord ?? 3;
        $cutoffDate      = now()->subMonths($retentionMonths);

        $invoices = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->with(['property', 'property.landlord'])
            ->get();

        $totalAmount = $invoices->sum('total_amount');

        return response()->json([
            'success'  => true,
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'property_name'    => $invoice->property->property_name ?? $invoice->property->street_name,
                    'landlord_name'    => $invoice->property->landlord->name ?? 'Unknown',
                    'original_year'    => $invoice->year_end_archive_year,
                    'payment_date'     => $invoice->payment_date->format('Y-m-d'),
                    'amount'           => $invoice->total_amount,
                    'formatted_amount' => $this->settings()->formatAmount($invoice->total_amount),
                ];
            }),
            'total'            => $invoices->count(),
            'total_amount'     => $totalAmount,
            'formatted_total'  => $this->settings()->formatAmount($totalAmount),
            'retention_months' => $retentionMonths,
        ]);
    }

    public function getEligibleForYearEndArchive(Request $request, $year)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoices = Invoice::whereYear('payment_date', $year)
            ->where('status', Invoice::STATUS_PAID)
            ->whereNull('deleted_at')
            ->whereNull('year_end_archived_at')
            ->with(['property', 'property.landlord'])
            ->get();

        return response()->json([
            'success'  => true,
            'count'    => $invoices->count(),
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'property_name'    => $invoice->property->property_name ?? $invoice->property->street_name,
                    'landlord_name'    => $invoice->property->landlord->name ?? 'Unknown',
                    'period'           => $invoice->period,
                    'amount'           => $invoice->total_amount,
                    'formatted_amount' => $this->settings()->formatAmount($invoice->total_amount),
                ];
            }),
        ]);
    }

    public function getArchiveInfo(Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $archive = InvoiceArchive::where('original_invoice_id', $invoice->id)->first();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id'                     => $invoice->id,
                'invoice_number'         => $invoice->invoice_number,
                'is_archived'            => !is_null($invoice->deleted_at),
                'is_year_end_archived'   => !is_null($invoice->year_end_archived_at ?? null),
                'year_end_archive_year'  => $invoice->year_end_archive_year ?? null,
                'original_year'          => $invoice->original_year ?? null,
                'archive_type'           => $invoice->archive_type ?? null,
                'archived_at'            => optional($invoice->archived_at ?? null)?->format('Y-m-d H:i:s'),
                'deleted_at'             => $invoice->deleted_at?->format('Y-m-d H:i:s'),
                'archive_record_exists'  => !is_null($archive),
                'archive_record'         => $archive ? [
                    'id'              => $archive->id,
                    'archive_type'    => $archive->archive_type,
                    'deleted_at'      => $archive->deleted_at?->format('Y-m-d H:i:s'),
                    'deleted_by_name' => $archive->deleted_by_name,
                ] : null,
            ],
        ]);
    }

    /**
 * Throw a 403-style redirect if offline payments are disabled and
 * the caller is trying to mark an invoice as paid manually.
 *
 * Call this at the top of any method that records a manual payment:
 *   - markAsPaid()
 *   - bulkMarkAsPaid()
 *   - bulkUpdateStatus()  (only when status === 'paid')
 */
protected function assertOfflinePaymentAllowed(string $context = 'record a payment'): void
{
    $settings = SystemSetting::getSettings();

    if (!$settings->isOfflinePaymentAllowed()) {
        abort(403, "Office payments are currently disabled. You cannot {$context}. "
                 . "Landlords must use the online payment gateway.");
    }
}

}