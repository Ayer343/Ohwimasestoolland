<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceArchive;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Traits\ChecksBillingAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InvoiceApiController extends Controller
{
    use ChecksBillingAccess;

    public function __construct(
        protected InvoiceService      $invoiceService,
        protected PaymentService      $paymentService,
        protected NotificationService $notificationService,
    ) {}

    /* ============================================================
     | SETTINGS HELPER
     |
     | NOTE: named systemSettings() — NOT settings() — to avoid
     | colliding with the public action settings() below which maps
     | to GET /api/v1/admin/invoices/settings.
     * ============================================================ */

    protected function systemSettings(): SystemSetting
    {
        return SystemSetting::getSettings();
    }

    /* ============================================================
     | RESPONSE HELPERS
     * ============================================================ */

    protected function jsonSuccess($data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function jsonError(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    protected function isAdmin($user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Abort-style guard for offline-payment restrictions.
     * Mirrors the behaviour of the web controller's inline check.
     */
    protected function assertOfflinePaymentAllowed(string $context = 'record a payment'): void
    {
        if (!$this->systemSettings()->isOfflinePaymentAllowed()) {
            abort(403, "Office payments are currently disabled. You cannot {$context}. Landlords must use the online payment gateway.");
        }
    }

    /* ============================================================
     | LISTING
     * ============================================================ */

    /**
     * GET /api/v1/invoices
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isAdmin($user) && !$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $filters = $request->only([
            'property_id', 'status', 'period', 'search', 'type',
            'has_parent', 'is_bulk', 'has_penalty', 'has_coverage', 'has_discount',
        ]);

        if ($user->isLandlord()) {
            $filters['property_ids'] = Property::where('landlord_id', $user->id)
                ->pluck('id')
                ->toArray();
        }

        $perPage  = min((int) $request->get('per_page', 15), 100);
        $invoices = $this->invoiceService
            ->getInvoicesWithFilters($filters, $user)
            ->paginate($perPage);

        return $this->jsonSuccess([
            'invoices'   => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
            'statistics' => $this->invoiceService->getInvoiceStatistics($user),
        ]);
    }

    /**
     * GET /api/v1/invoices/{invoice}
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();

        if ($user->isLandlord() && $invoice->property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access to invoice.', 403);
        }

        if (!$this->isAdmin($user) && !$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $invoice->load(['property', 'property.landlord', 'payment', 'bulkPayment', 'childInvoices']);

        return $this->jsonSuccess([
            'invoice'             => $invoice,
            'coverage_info'       => $this->buildCoverageInfo($invoice),
            'notification_status' => $this->invoiceService->getInvoiceNotificationStatus($invoice->id),
        ]);
    }

    /**
     * GET /api/v1/invoices/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isAdmin($user) && !$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        return $this->jsonSuccess($this->invoiceService->getInvoiceStatistics($user));
    }

    /**
     * GET /api/v1/invoices/outstanding
     */
    public function outstanding(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        return $this->jsonSuccess(
            $this->invoiceService->getLandlordOutstandingInvoices($user->id)
        );
    }

    /* ============================================================
     | PAYMENT — landlord-initiated (NEVER billing-gated)
     * ============================================================ */

    /**
     * POST /api/v1/invoices/payment-summary
     */
    public function paymentSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Invalid invoice selection', 422, $validator->errors()->toArray());
        }

        $consolidatedCount = Invoice::whereIn('id', $request->invoice_ids)
            ->where('status', 'consolidated')
            ->count();

        if ($consolidatedCount > 0) {
            return $this->jsonError('Cannot process payment for consolidated invoices. Use the bulk invoice instead.', 422);
        }

        $summary = $this->invoiceService->getPaymentSummary($request->invoice_ids);
        if (!($summary['success'] ?? false)) {
            return $this->jsonError($summary['message'] ?? 'Failed to build summary', 422);
        }

        return $this->jsonSuccess($summary);
    }

    /**
     * POST /api/v1/invoices/process-payment
     */
    public function processPayment(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'    => 'required|array|min:1',
            'invoice_ids.*'  => 'integer|exists:invoices,id',
            'payment_method' => 'required|string',
            'phone_number'   => 'required_if:payment_method,mtn_momo,telecel_cash,airteltigo_cash',
            'email'          => 'nullable|email',
            'amount'         => 'required|numeric|min:0.01',
            'transaction_id' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $consolidated = Invoice::whereIn('id', $request->invoice_ids)
                ->where('status', 'consolidated')
                ->exists();

            if ($consolidated) {
                return $this->jsonError('Cannot pay consolidated invoices. Use the bulk invoice instead.', 422);
            }

            $result = $this->invoiceService->processPaymentForInvoices(
                $request->invoice_ids,
                $request->transaction_id ?? 'MANUAL-' . time(),
                $request->payment_method
            );

            if (!($result['success'] ?? false)) {
                return $this->jsonError($result['message'] ?? 'Payment failed', 422);
            }

            return $this->jsonSuccess($result, $result['message'] ?? 'Payment processed');
        } catch (\Throwable $e) {
            Log::error('API processPayment failed', ['error' => $e->getMessage()]);
            return $this->jsonError('An error occurred while processing payment.', 500);
        }
    }

    /* ============================================================
     | BULK PAYMENT (landlord)
     * ============================================================ */

    /**
     * GET /api/v1/invoices/properties/{property}/bulk-payment-options
     */
    public function bulkPaymentOptions(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();
        if (!$user->isLandlord() || $property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        return $this->jsonSuccess(
            $this->invoiceService->getBulkPaymentOptions($property)
        );
    }

    /**
     * POST /api/v1/invoices/bulk-payment
     */
    public function createBulkPayment(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'months'      => 'required|integer|min:2|max:12',
            'start_month' => 'nullable|date_format:Y-m',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $property = Property::findOrFail($request->property_id);
        if ($property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access to property.', 403);
        }

        if (!$this->systemSettings()->isBulkPaymentEnabled()) {
            return $this->jsonError('Bulk payments are currently disabled.', 422);
        }

        try {
            $startMonth = $request->start_month ?? now()->startOfMonth()->format('Y-m');
            $startDate  = Carbon::parse($startMonth . '-01');
            $endMonth   = $startDate->copy()->addMonths($request->months - 1)->format('Y-m');

            $existingIds = Invoice::where('property_id', $property->id)
                ->whereBetween('period', [$startMonth, $endMonth])
                ->whereIn('status', ['pending', 'overdue'])
                ->where('is_bulk_payment', false)
                ->pluck('id')
                ->toArray();

            $result = $this->invoiceService->generateBulkPaymentInvoice(
                $property,
                $request->months,
                $startMonth,
                $existingIds
            );

            if (!($result['success'] ?? false)) {
                return $this->jsonError($result['message'] ?? 'Bulk payment failed', 422);
            }

            return $this->jsonSuccess($result, $result['message'] ?? 'Bulk payment created');
        } catch (\Throwable $e) {
            Log::error('API createBulkPayment failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to create bulk payment.', 500);
        }
    }

    /**
     * POST /api/v1/invoices/{bulkInvoice}/process-bulk-payment
     */
    public function processBulkPayment(Request $request, Invoice $bulkInvoice): JsonResponse
    {
        $user = $request->user();
        if (!$user->isLandlord() || $bulkInvoice->property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        if (!$bulkInvoice->is_bulk_payment) {
            return $this->jsonError('This is not a bulk payment invoice.', 422);
        }

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $result = $this->invoiceService->processBulkPayment($bulkInvoice, $request->transaction_id);
        if (!($result['success'] ?? false)) {
            return $this->jsonError($result['message'] ?? 'Bulk payment failed', 422);
        }

        return $this->jsonSuccess($result, $result['message'] ?? 'Bulk payment processed');
    }

    /* ============================================================
     | COVERAGE
     * ============================================================ */

    /**
     * GET /api/v1/invoices/check-coverage?property_id=1&period=2026-10
     */
    public function checkCoverage(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user) && !$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'period'      => 'required|date_format:Y-m',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $property = Property::find($request->property_id);
        if ($user->isLandlord() && $property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access to property.', 403);
        }

        $isCovered = $this->invoiceService->isPeriodCoveredByBulkPayment($property, $request->period);

        return $this->jsonSuccess([
            'covered'  => $isCovered,
            'period'   => $request->period,
            'property' => $property->id,
            'message'  => $isCovered
                ? 'This period is covered by an active bulk payment.'
                : 'No bulk coverage for this period.',
        ]);
    }

    /**
     * GET /api/v1/invoices/{invoice}/coverage
     */
    public function coverage(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if ($user->isLandlord() && $invoice->property->landlord_id !== $user->id) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        return $this->jsonSuccess([
            'coverage' => $this->buildCoverageInfo($invoice),
            'is_bulk'  => (bool) $invoice->is_bulk_payment,
            'is_paid'  => $invoice->isPaid(),
        ]);
    }

    /* ============================================================
     | ADMIN — GENERATION
     * ============================================================ */

    /**
     * POST /api/v1/admin/invoices/generate-manual
     */
    public function generateManual(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('generate_landlord_invoice');

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'period'      => [
                'required', 'date_format:Y-m',
                Rule::unique('invoices')->where(function ($q) use ($request) {
                    return $q->where('property_id', $request->property_id)
                             ->where('is_bulk_payment', false)
                             ->whereNotIn('status', ['consolidated', 'cancelled']);
                }),
            ],
            'due_date'             => 'required|date|after_or_equal:today',
            'amount'               => 'required|numeric|min:0.01',
            'description'          => 'nullable|string|max:500',
            'apply_penalty'        => 'nullable|boolean',
            'penalty_amount'       => 'nullable|numeric|min:0',
            'send_notification'    => 'nullable|boolean',
            'ignore_bulk_coverage' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $property  = Property::find($request->property_id);
            $isCovered = $this->invoiceService->isPeriodCoveredByBulkPayment($property, $request->period);

            if ($isCovered && !$request->boolean('ignore_bulk_coverage', false)) {
                return $this->jsonError(
                    'This period is already covered by an active bulk payment. Pass ignore_bulk_coverage=true to override.',
                    422
                );
            }

            $result = $this->invoiceService->generateManualInvoice(
                $request->property_id,
                $request->period,
                $request->amount,
                Carbon::parse($request->due_date),
                false
            );

            if (!($result['success'] ?? false)) {
                return $this->jsonError($result['message'] ?? 'Generation failed', 422);
            }

            $invoice = Invoice::find($result['invoice_id']);

            if ($request->boolean('apply_penalty') && $request->penalty_amount > 0 && $invoice) {
                $invoice->applyPenalty($request->penalty_amount, 'Manual penalty');
            }

            if ($request->boolean('send_notification', true) && $invoice) {
                try {
                    $this->notificationService->sendLandlordInvoiceCreated($invoice);
                } catch (\Throwable $e) {
                    Log::warning('Notification failed after manual generation', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return $this->jsonSuccess([
                'invoice' => $invoice,
            ], $result['message'] ?? 'Invoice generated');
        } catch (\Throwable $e) {
            Log::error('API generateManual failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to generate invoice.', 500);
        }
    }

    /**
     * POST /api/v1/admin/invoices/generate-monthly
     */
    public function generateMonthly(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('generate_monthly_invoices');

        if (!$this->systemSettings()->isAutoInvoiceGenerationEnabled()) {
            return $this->jsonError('Auto invoice generation is disabled.', 422);
        }

        $lock = Cache::lock('generate_monthly_invoices', 600);
        if (!$lock->get()) {
            return $this->jsonError('A generation run is already in progress.', 429);
        }

        try {
            $result = $this->invoiceService->generateMonthlyInvoices();
            $lock->release();

            if (!($result['success'] ?? false)) {
                return $this->jsonError($result['message'] ?? 'Generation failed', 422);
            }

            return $this->jsonSuccess($result, $result['message'] ?? 'Monthly invoices generated');
        } catch (\Throwable $e) {
            $lock->release();
            Log::error('API generateMonthly failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Monthly generation failed.', 500);
        }
    }

    /* ============================================================
     | ADMIN — MARK PAID
     * ============================================================ */

    /**
     * POST /api/v1/admin/invoices/{invoice}/mark-paid
     */
    public function markAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        if (!$this->systemSettings()->isOfflinePaymentAllowed()) {
            return $this->jsonError('Office payments are currently disabled. Landlords must pay online.', 403);
        }

        $this->assertBillingAllowsWrite('mark_landlord_invoice_paid');

        $validator = Validator::make($request->all(), [
            'payment_method'    => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'send_confirmation' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        if ($invoice->status === 'consolidated') {
            return $this->jsonError('Cannot mark a consolidated invoice as paid.', 422);
        }

        try {
            DB::beginTransaction();

            $invoice->update([
                'status'            => Invoice::STATUS_PAID,
                'paid_amount'       => $invoice->total_amount,
                'balance'           => 0,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'payment_date'      => Carbon::parse($request->payment_date),
                'updated_by'        => $user->id,
            ]);

            if ($request->filled('notes')) {
                $invoice->addNote("Manually marked as paid by {$user->name}: {$request->notes}");
            }

            if ($invoice->is_bulk_payment) {
                $this->invoiceService->processBulkPayment(
                    $invoice,
                    $request->payment_reference ?? 'MANUAL-' . time()
                );
            }

            DB::commit();

            return $this->jsonSuccess(['invoice' => $invoice->fresh()], 'Invoice marked as paid.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API markAsPaid failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Error marking invoice as paid.', 500);
        }
    }

    /* ============================================================
     | ADMIN — PENALTIES
     * ============================================================ */

    public function applyPenalty(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('apply_landlord_penalty');

        if ($invoice->status === 'consolidated' || $invoice->isPaid()) {
            return $this->jsonError('Cannot apply penalty to this invoice.', 422);
        }

        $validator = Validator::make($request->all(), [
            'penalty_amount' => 'required|numeric|min:0.01',
            'reason'         => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $invoice->applyPenalty($request->penalty_amount, $request->reason);

        return $this->jsonSuccess(['invoice' => $invoice->fresh()], 'Penalty applied.');
    }

    public function removePenalty(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('remove_landlord_penalty');

        if ($invoice->isPaid()) {
            return $this->jsonError('Cannot remove penalty from a paid invoice.', 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $invoice->removePenalty($request->reason);

        return $this->jsonSuccess(['invoice' => $invoice->fresh()], 'Penalty removed.');
    }

    /* ============================================================
     | ADMIN — REMINDERS
     * ============================================================ */

    /**
     * POST /api/v1/admin/invoices/{invoice}/remind
     */
    public function sendReminder(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        if ($invoice->status === 'consolidated') {
            return $this->jsonError('Cannot remind for a consolidated invoice.', 422);
        }

        try {
            $result = match (true) {
                $invoice->status === 'paid'    => $this->notificationService->sendLandlordPaymentConfirmation($invoice),
                $invoice->status === 'overdue' => $this->notificationService->sendLandlordInvoiceOverdue($invoice),
                default                        => $this->notificationService->sendLandlordInvoiceReminder($invoice),
            };

            $channels = $result['channels'] ?? [];

            return $this->jsonSuccess([
                'invoice_id'    => $invoice->id,
                'status'        => $invoice->status,
                'channels_used' => $channels,
            ], empty($channels)
                ? 'Reminder dispatched (no channels confirmed).'
                : 'Reminder sent via: ' . implode(', ', $channels)
            );
        } catch (\Throwable $e) {
            Log::error('API sendReminder failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            return $this->jsonError('Failed to send reminder.', 500);
        }
    }

    /**
     * POST /api/v1/admin/invoices/bulk-remind
     */
    public function bulkSendReminders(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        $invoices = Invoice::whereIn('id', $request->invoice_ids)
            ->with(['property', 'property.landlord'])
            ->get();

        $sent   = 0;
        $failed = 0;
        $details = [];

        foreach ($invoices as $invoice) {
            try {
                $result = match (true) {
                    $invoice->status === 'paid'    => $this->notificationService->sendLandlordPaymentConfirmation($invoice),
                    $invoice->status === 'overdue' => $this->notificationService->sendLandlordInvoiceOverdue($invoice),
                    default                        => $this->notificationService->sendLandlordInvoiceReminder($invoice),
                };

                $channels = $result['channels'] ?? [];
                if (!empty($channels)) {
                    $sent++;
                } else {
                    $failed++;
                }

                $details[$invoice->id] = [
                    'success' => !empty($channels),
                    'channels' => $channels,
                ];
            } catch (\Throwable $e) {
                $failed++;
                $details[$invoice->id] = ['success' => false, 'error' => $e->getMessage()];
                Log::warning('Bulk reminder failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $this->jsonSuccess([
            'total'   => $invoices->count(),
            'sent'    => $sent,
            'failed'  => $failed,
            'details' => $details,
        ], "Sent {$sent} of {$invoices->count()} reminders.");
    }

    /* ============================================================
     | ADMIN — TRASH / RESTORE / DELETE
     * ============================================================ */

    /**
     * GET /api/v1/admin/invoices/trash
     */
    public function trash(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        $query = Invoice::onlyTrashed()->with(['property', 'property.landlord', 'creator']);

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'LIKE', "%{$s}%")
                  ->orWhere('payment_reference', 'LIKE', "%{$s}%")
                  ->orWhereHas('property', fn ($p) => $p
                      ->where('street_name', 'LIKE', "%{$s}%")
                      ->orWhere('house_number', 'LIKE', "%{$s}%"));
            });
        }

        $invoices = $query->orderBy('deleted_at', 'desc')->paginate($perPage);

        return $this->jsonSuccess([
            'invoices'   => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
            'statistics' => [
                'total_trashed' => Invoice::onlyTrashed()->count(),
                'bulk_trashed'  => Invoice::onlyTrashed()->where('is_bulk_payment', true)->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/invoices/{id}/restore
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('restore_landlord_invoice');

        $invoice = Invoice::onlyTrashed()->find($id);
        if (!$invoice) {
            return $this->jsonError('Invoice not found in trash.', 404);
        }

        $check = $this->canRestoreInvoice($invoice);
        if (!$check['can_restore']) {
            return $this->jsonError($check['message'], 422);
        }

        DB::beginTransaction();
        try {
            $invoice->restore();
            $invoice->addNote("Restored by {$user->name}");
            DB::commit();

            return $this->jsonSuccess(['invoice' => $invoice->fresh()], 'Invoice restored.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->jsonError('Failed to restore invoice.', 500);
        }
    }

    /**
     * DELETE /api/v1/admin/invoices/{invoice}
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('delete_landlord_invoice');

        if (!$this->canDeleteInvoice($invoice)) {
            return $this->jsonError($this->getDeletionErrorMessage($invoice), 422);
        }

        DB::beginTransaction();
        try {
            $archive = $this->createArchivalRecord($invoice, $request);
            $invoice->delete();
            DB::commit();

            return $this->jsonSuccess([
                'archive_id' => $archive->id,
            ], 'Invoice moved to trash.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API destroy failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Error deleting invoice.', 500);
        }
    }

    /* ============================================================
     | ADMIN — BULK STATUS UPDATE
     * ============================================================ */

    /**
     * POST /api/v1/admin/invoices/bulk-status
     * Body: { invoice_ids: [1,2], status: 'paid'|'pending'|..., payment_method?, payment_reference? }
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $this->assertBillingAllowsWrite('bulk_update_invoice_status');

        $validator = Validator::make($request->all(), [
            'invoice_ids'        => 'required|array|min:1',
            'invoice_ids.*'      => 'integer|exists:invoices,id',
            'status'             => 'required|in:paid,pending,overdue,partial,cancelled',
            'payment_method'     => 'nullable|string|max:255',
            'payment_reference'  => 'nullable|string|max:255',
            'send_notifications' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonError('Validation failed', 422, $validator->errors()->toArray());
        }

        // Enforce offline-payment toggle when marking paid
        if ($request->status === 'paid' && !$this->systemSettings()->isOfflinePaymentAllowed()) {
            return $this->jsonError(
                'Office payments are currently disabled. Landlords must pay through the online gateway.',
                403
            );
        }

        $ids = $request->invoice_ids;

        if (Invoice::whereIn('id', $ids)->where('status', 'consolidated')->exists()) {
            return $this->jsonError('Cannot update consolidated invoices via bulk operation.', 422);
        }

        DB::beginTransaction();
        try {
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
                    'updated_by' => $user->id,
                ]);

                $result = [
                    'success'       => true,
                    'message'       => "Successfully updated {$updatedCount} invoice(s)",
                    'updated_count' => $updatedCount,
                ];
            }

            DB::commit();

            // Optional notification fan-out
            if ($request->boolean('send_notifications', false) && ($result['success'] ?? false)) {
                if ($this->systemSettings()->shouldSendPaymentReminders()) {
                    foreach ($ids as $invoiceId) {
                        try {
                            $this->invoiceService->sendInvoiceStatusUpdateNotification($invoiceId, $request->status);
                        } catch (\Throwable $e) {
                            Log::warning('Status update notification failed', [
                                'invoice_id' => $invoiceId,
                                'error'      => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            Log::info('Bulk invoice status update', [
                'invoice_ids' => $ids,
                'status'      => $request->status,
                'user_id'     => $user->id,
            ]);

            return $this->jsonSuccess($result, $result['message'] ?? 'Bulk update complete');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in bulk invoice status update', [
                'invoice_ids' => $ids,
                'user_id'     => $user->id,
                'error'       => $e->getMessage(),
            ]);
            return $this->jsonError('Error updating invoice statuses', 500);
        }
    }

    /* ============================================================
     | EXPORTS — JSON payloads for mobile
     * ============================================================ */

    /**
     * GET /api/v1/invoices/export
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user) && !$user->isLandlord()) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $filters = $request->only([
            'property_id', 'status', 'period', 'search', 'type',
            'has_parent', 'is_bulk', 'has_coverage', 'has_discount', 'has_penalty',
        ]);

        if ($user->isLandlord()) {
            $filters['property_ids'] = Property::where('landlord_id', $user->id)->pluck('id')->toArray();
        }

        $invoices = $this->invoiceService->getInvoicesWithFilters($filters, $user)->get();

        return $this->jsonSuccess([
            'invoices'    => $invoices,
            'count'       => $invoices->count(),
            'statistics'  => $this->invoiceService->getInvoiceStatistics($user),
            'exported_at' => now()->toISOString(),
        ]);
    }

    /* ============================================================
     | SETTINGS (public action — GET /api/v1/admin/invoices/settings)
     * ============================================================ */

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return $this->jsonError('Unauthorized access.', 403);
        }

        $s = $this->systemSettings();

        return $this->jsonSuccess([
            'auto_generate_invoices'  => $s->isAutoInvoiceGenerationEnabled(),
            'send_payment_reminders'  => $s->shouldSendPaymentReminders(),
            'reminder_days_before'    => $s->getReminderDaysBefore(),
            'grace_period_days'       => $s->grace_period_days ?? 7,
            'late_payment_percentage' => $s->late_payment_percentage ?? 0,
            'fixed_penalty_amount'    => $s->fixed_penalty_amount ?? 0,
            'enable_bulk_payments'    => $s->isBulkPaymentEnabled(),
            'max_bulk_months'         => $s->max_bulk_months ?? 6,
            'offline_payment_allowed' => $s->isOfflinePaymentAllowed(),
            'currency_symbol'         => $s->currency_symbol ?? '₵',
        ]);
    }

    /* ============================================================
     | INTERNAL HELPERS
     * ============================================================ */

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

        $formatted = array_map(
            fn ($p) => preg_match('/^\d{4}-\d{2}$/', $p)
                ? Carbon::parse($p . '-01')->format('M Y')
                : (string) $p,
            $periods
        );

        return [
            'periods'           => $periods,
            'formatted_periods' => $formatted,
            'start'             => $invoice->bulk_coverage_start,
            'end'               => $invoice->bulk_coverage_end,
            'months_covered'    => count($periods),
            'is_active'         => true,
        ];
    }

    protected function canDeleteInvoice(Invoice $invoice): bool
    {
        if ($invoice->isPaid() && !empty($invoice->covers_periods)) return false;
        if (($invoice->paid_amount ?? 0) > 0) return false;
        if ($invoice->created_at < now()->subYear()) return false;
        if ($invoice->is_bulk_payment && $invoice->childInvoices()->count() > 0) return false;
        return true;
    }

    protected function getDeletionErrorMessage(Invoice $invoice): string
    {
        if ($invoice->isPaid() && !empty($invoice->covers_periods)) {
            return 'Cannot delete a paid invoice with active coverage.';
        }
        if (($invoice->paid_amount ?? 0) > 0) {
            return 'Cannot delete an invoice with payments.';
        }
        if ($invoice->created_at < now()->subYear()) {
            return 'Cannot delete invoices older than 1 year.';
        }
        if ($invoice->is_bulk_payment && $invoice->childInvoices()->count() > 0) {
            return 'Cannot delete a bulk invoice with child invoices.';
        }
        return 'Invoice cannot be deleted.';
    }

    protected function canRestoreInvoice(Invoice $invoice): array
    {
        if (!$invoice->property) {
            return ['can_restore' => false, 'message' => 'Property no longer exists.'];
        }

        $exists = Invoice::where('property_id', $invoice->property_id)
            ->where('period', $invoice->period)
            ->where('is_bulk_payment', $invoice->is_bulk_payment)
            ->where('id', '!=', $invoice->id)
            ->exists();

        if ($exists) {
            return ['can_restore' => false, 'message' => "Another invoice already exists for period {$invoice->period}."];
        }

        return ['can_restore' => true, 'message' => 'Can restore'];
    }

    protected function createArchivalRecord(Invoice $invoice, Request $request): InvoiceArchive
    {
        return InvoiceArchive::create([
            'original_invoice_id' => $invoice->id,
            'invoice_number'      => $invoice->invoice_number,
            'property_id'         => $invoice->property_id,
            'property_name'       => $invoice->property?->property_name ?? $invoice->property?->street_name,
            'landlord_id'         => $invoice->property?->landlord_id,
            'landlord_name'       => $invoice->property?->landlord?->name,
            'period'              => $invoice->period,
            'due_date'            => $invoice->due_date,
            'amount'              => $invoice->amount,
            'penalty_amount'      => $invoice->penalty_amount ?? 0,
            'total_amount'        => $invoice->total_amount,
            'paid_amount'         => $invoice->paid_amount ?? 0,
            'balance'             => $invoice->balance,
            'status'              => $invoice->status,
            'payment_method'      => $invoice->payment_method,
            'payment_reference'   => $invoice->payment_reference,
            'payment_date'        => $invoice->payment_date,
            'is_bulk_payment'     => $invoice->is_bulk_payment,
            'covers_periods'      => $invoice->covers_periods,
            'metadata'            => $invoice->metadata,
            'deleted_at'          => now(),
            'deleted_by'          => $request->user()->id,
            'deleted_by_name'     => $request->user()->name,
            'deletion_reason'     => $request->input('reason', 'API deletion'),
            'deletion_ip'         => $request->ip(),
            'archive_type'        => 'manual',
        ]);
    }
}