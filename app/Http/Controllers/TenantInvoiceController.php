<?php

namespace App\Http\Controllers;

use App\Models\TenantInvoice;
use App\Models\User;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Services\TenantInvoiceService;
use App\Services\YearEndArchiveService;
use App\Services\NotificationService;
use App\Models\TenantInvoiceArchive;
use App\Traits\NotifiesUsers;
use App\Traits\ChecksBillingAccess;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TenantInvoiceCreated;
use App\Mail\TenantInvoiceReminder;
use App\Mail\TenantPaymentConfirmation;

class TenantInvoiceController extends Controller
{
    use NotifiesUsers, ChecksBillingAccess;

    protected $tenantInvoiceService;
    protected $settings;
    protected $notificationService;

    public function __construct(TenantInvoiceService $tenantInvoiceService, NotificationService $notificationService)
    {
        $this->tenantInvoiceService = $tenantInvoiceService;
        $this->notificationService  = $notificationService;
        $this->settings             = SystemSetting::getSettings();

        Log::info('TenantInvoiceController initialized', [
            'notification_service_set' => !is_null($this->notificationService),
        ]);
    }

    /* ============================================================
     | INDEX / LISTING — read-only
     * ============================================================ */

    public function index(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('tenant.invoices.my-invoices')
                ->with('error', 'Unauthorized access.');
        }

        if (!$this->settings->enable_tenant_invoicing) {
            return redirect()->route('admin.dashboard')
                ->with('warning', '⚠️ Tenant invoicing is currently disabled. Enable it in System Settings to manage tenant invoices.');
        }

        $query = TenantInvoice::with(['tenant', 'propertyUnit.property']);

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('property_unit_id')) {
            $query->where('property_unit_id', $request->property_unit_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('overdue_only') && $request->overdue_only) {
            $graceDays = $this->settings->tenant_grace_period_days ?? 7;
            $query->where('status', TenantInvoice::STATUS_PENDING)
                  ->where('due_date', '<', now()->subDays($graceDays));
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(15);

        foreach ($invoices as $invoice) {
            if ($invoice->propertyUnit === null) {
                Log::warning('Invoice has missing propertyUnit relationship', [
                    'invoice_id'       => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'property_unit_id' => $invoice->property_unit_id,
                    'tenant_id'        => $invoice->tenant_id,
                    'period'           => $invoice->period,
                ]);

                $emptyPropertyUnit = new \App\Models\PropertyUnit();
                $emptyPropertyUnit->setRelation('property', null);
                $invoice->setRelation('propertyUnit', $emptyPropertyUnit);
            }
        }

        $statistics = $this->getEnhancedStatistics();

        $tenants = User::where('type', User::TYPE_TENANT)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        $propertyUnits = PropertyUnit::with('property')
            ->whereHas('tenant', function ($query) {
                $query->whereNotNull('id');
            })
            ->orderBy('unit_number')
            ->get()
            ->map(function ($unit) {
                return [
                    'id'           => $unit->id,
                    'display_name' => $unit->property->property_name . ' - ' . $unit->unit_number,
                ];
            });

        $periods = TenantInvoice::select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        $nextDueDate = $this->calculateNextDueDate();
        $default_due_date = $this->calculateDefaultDueDate()->format('Y-m-d');

        $calculation_method = $this->settings->getTenantCalculationMethodText();
        $tenant_calculation_method_raw = $this->settings->tenant_calculation_method ?? 'fixed';
        $default_monthly_dues = $this->settings->formatAmount($this->settings->tenant_monthly_dues_amount ?? 0);
        $grace_period_end_date = $this->calculateGracePeriodEndDate();
        $penalty_type = $this->getPenaltyType();
        $send_tenant_payment_reminders = $this->settings->send_tenant_payment_reminders ?? true;

        return view('admin.tenant-invoices.index', compact(
            'invoices',
            'statistics',
            'tenants',
            'propertyUnits',
            'periods',
            'nextDueDate'
        ))->with([
            'enable_tenant_invoicing'         => $this->settings->enable_tenant_invoicing,
            'tenant_monthly_dues_amount'      => $this->settings->formatAmount($this->settings->tenant_monthly_dues_amount ?? 0),
            'tenant_calculation_method'       => $this->settings->getTenantCalculationMethodText(),
            'auto_generate_tenant_invoices'   => $this->settings->auto_generate_tenant_invoices,
            'send_tenant_payment_reminders'   => $this->settings->send_tenant_payment_reminders,
            'tenant_grace_period_days'        => $this->settings->tenant_grace_period_days,
            'tenant_late_payment_percentage'  => $this->settings->tenant_late_payment_percentage,
            'tenant_fixed_penalty_amount'     => $this->settings->tenant_fixed_penalty_amount,
            'grace_period_end_date'           => $grace_period_end_date,
            'penalty_type'                    => $penalty_type,
            'settings'                        => $this->settings,
            'system_settings'                 => $this->settings,
            'calculation_method'              => $calculation_method,
            'tenant_calculation_method_raw'   => $tenant_calculation_method_raw,
            'default_monthly_dues'            => $default_monthly_dues,
            'default_due_date'                => $default_due_date,
            'send_tenant_payment_reminders'   => $send_tenant_payment_reminders,
        ]);
    }

    /**
     * Tenant-facing invoice list — never restricted by billing.
     * Tenants must always see what they owe so they can pay.
     */
    public function myInvoices(Request $request)
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only tenants can access this page.');
        }

        $tenant = auth()->user();

        if (!$this->settings->enable_tenant_invoicing) {
            return redirect()->route('dashboard')
                ->with('info', 'Tenant invoicing is currently disabled. Please check back later.');
        }

        $status = $request->get('status');
        $propertyUnitId = $request->get('property_unit_id');
        $period = $request->get('period');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = TenantInvoice::with(['propertyUnit.property'])
            ->where('tenant_id', $tenant->id);

        if ($status)         { $query->where('status', $status); }
        if ($propertyUnitId) { $query->where('property_unit_id', $propertyUnitId); }
        if ($period)         { $query->where('period', $period); }
        if ($fromDate)       { $query->whereDate('created_at', '>=', $fromDate); }
        if ($toDate)         { $query->whereDate('created_at', '<=', $toDate); }

        $invoices = $query->orderBy('period', 'desc')->paginate(15);
        $statistics = $this->getTenantStatistics($tenant->id);

        $propertyUnits = PropertyUnit::where('tenant_id', $tenant->id)
            ->with('property')
            ->get()
            ->map(function ($unit) {
                return [
                    'id'           => $unit->id,
                    'display_name' => $unit->property->property_name . ' - Unit ' . $unit->unit_number,
                ];
            });

        $periods = TenantInvoice::where('tenant_id', $tenant->id)
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        $nextPayment = $this->calculateNextPaymentForTenant($tenant->id);
        $gracePeriodEndDate = $this->calculateGracePeriodEndDate();
        $paymentInstructions = $this->getPaymentInstructions();

        return view('tenant.invoices.index', compact(
            'invoices',
            'statistics',
            'propertyUnits',
            'periods',
            'nextPayment',
            'gracePeriodEndDate',
            'paymentInstructions'
        ))->with([
            'enable_tenant_invoicing'        => $this->settings->enable_tenant_invoicing,
            'tenant_monthly_dues_amount'     => $this->settings->formatAmount($this->settings->tenant_monthly_dues_amount ?? 0),
            'tenant_calculation_method'      => $this->settings->getTenantCalculationMethodText(),
            'send_tenant_payment_reminders'  => $this->settings->send_tenant_payment_reminders,
            'tenant_grace_period_days'       => $this->settings->tenant_grace_period_days,
            'tenant_late_payment_percentage' => $this->settings->tenant_late_payment_percentage,
            'tenant_fixed_penalty_amount'    => $this->settings->tenant_fixed_penalty_amount,
            'currency_symbol'                => $this->settings->currency_symbol,
            'currency_position'              => $this->settings->currency_position,
            'system_settings'                => $this->settings,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
        }

        if (!$this->settings->enable_tenant_invoicing) {
            return redirect()->route('admin.tenant-invoices.index')
                ->with('warning', '⚠️ Tenant invoicing is disabled. Enable it in System Settings to create invoices.');
        }

        $tenants = User::where('type', User::TYPE_TENANT)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('propertyUnits', function ($query) {
                $query->where('tenant_status', 'approved');
            })
            ->with(['propertyUnits' => function ($query) {
                $query->with('property');
            }])
            ->orderBy('name')
            ->get();

        $defaultAmount = $this->calculateDefaultTenantDues();

        $tenantDuesData = [];
        foreach ($tenants as $tenant) {
            foreach ($tenant->propertyUnits as $unit) {
                $tenantDuesData[$tenant->id][$unit->id] = $this->calculateTenantDuesForUnit($unit);
            }
        }

        $tenantCalcMethodRaw  = $this->settings->tenant_calculation_method ?? 'fixed';
        $tenantCalcMethodText = $this->getTenantCalculationMethodText();

        return view('admin.tenant-invoices.create', compact('tenants', 'tenantDuesData'))->with([
            'default_monthly_dues'            => $this->settings->formatAmount($this->settings->tenant_monthly_dues_amount ?? 0),
            'calculation_method'              => $tenantCalcMethodText,
            'calculation_method_raw'          => $tenantCalcMethodRaw,
            'default_due_date'                => $this->calculateDefaultDueDate()->format('Y-m-d'),
            'grace_period_days'               => $this->settings->tenant_grace_period_days,
            'default_amount'                  => $defaultAmount,
            'currency_symbol'                 => $this->settings->currency_symbol,
            'system_settings'                 => $this->settings,
            'enable_tenant_invoicing'         => $this->settings->enable_tenant_invoicing,
            'tenant_monthly_dues_amount'      => $this->settings->tenant_monthly_dues_amount ?? 0,
            'tenant_calculation_method'       => $tenantCalcMethodText,
            'tenant_calculation_method_raw'   => $tenantCalcMethodRaw,
            'tenant_dues_percentage'          => $this->settings->tenant_dues_percentage ?? 50,
            'auto_generate_tenant_invoices'   => $this->settings->auto_generate_tenant_invoices,
            'send_tenant_payment_reminders'   => $this->settings->send_tenant_payment_reminders,
            'tenant_grace_period_days'        => $this->settings->tenant_grace_period_days,
            'tenant_late_payment_percentage'  => $this->settings->tenant_late_payment_percentage,
            'tenant_fixed_penalty_amount'     => $this->settings->tenant_fixed_penalty_amount,
            'currency_position'               => $this->settings->currency_position,
            'decimal_places'                  => $this->settings->decimal_places ?? 2,
            'system_name'                     => $this->settings->system_name,
        ]);
    }

    private function getTenantCalculationMethodText(): string
    {
        $method = $this->settings->tenant_calculation_method ?? 'fixed';
        $percentage = $this->settings->tenant_dues_percentage ?? 50;

        switch ($method) {
            case 'fixed':
                return 'Fixed Amount';
            case 'per_property_unit':
                return 'Per Property Unit';
            case 'percentage_of_landlord':
                return "Percentage of Landlord Dues ({$percentage}%)";
            default:
                return 'Fixed Amount';
        }
    }

    /* ============================================================
     | STORE — admin write operation
     * ============================================================ */

    public function store(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('create_tenant_invoice');

        if (!$this->settings->enable_tenant_invoicing) {
            return redirect()->back()
                ->with('error', '❌ Tenant invoicing is disabled. Enable it in System Settings to create invoices.');
        }

        $validator = Validator::make($request->all(), [
            'tenant_id'            => 'required|exists:users,id',
            'property_unit_id'     => 'required|exists:property_units,id',
            'period'               => 'required|date_format:Y-m',
            'due_date'             => 'required|date',
            'community_dues'       => 'required|numeric|min:0.01',
            'additional_charges'   => 'nullable|numeric|min:0',
            'description'          => 'nullable|string|max:500',
            'send_notification'    => 'nullable|boolean',
            'apply_penalty_rules'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $existing = TenantInvoice::where('tenant_id', $request->tenant_id)
                ->where('property_unit_id', $request->property_unit_id)
                ->where('period', $request->period)
                ->first();

            if ($existing) {
                return redirect()->back()
                    ->with('error', '❌ An invoice for this tenant, unit, and period already exists.')
                    ->withInput();
            }

            $propertyUnit = PropertyUnit::with('property')->findOrFail($request->property_unit_id);

            if ($propertyUnit->tenant_id != $request->tenant_id) {
                return redirect()->back()
                    ->with('error', '❌ This tenant is not assigned to the selected property unit.')
                    ->withInput();
            }

            $communityDues = $request->community_dues;
            $additionalCharges = $request->additional_charges ?? 0;

            if ($request->boolean('apply_penalty_rules')) {
                $validation = $this->validateAmountAgainstSettings($communityDues, $propertyUnit);
                if (!$validation['valid']) {
                    return redirect()->back()
                        ->with('warning', $validation['message'])
                        ->withInput();
                }
            }

            $totalAmount = $communityDues + $additionalCharges;

            DB::beginTransaction();

            $invoice = TenantInvoice::create([
                'tenant_id'          => $request->tenant_id,
                'property_unit_id'   => $request->property_unit_id,
                'period'             => $request->period,
                'due_date'           => Carbon::parse($request->due_date),
                'community_dues'     => $communityDues,
                'additional_charges' => $additionalCharges,
                'total_amount'       => $totalAmount,
                'balance'            => $totalAmount,
                'status'             => TenantInvoice::STATUS_PENDING,
                'description'        => $request->description,
                'created_by'         => auth()->id(),
                'metadata'           => [
                    'generation_method' => 'manual',
                    'generated_by'      => auth()->user()->name,
                    'generated_at'      => now()->toDateTimeString(),
                    'property_unit_details' => [
                        'unit_number'   => $propertyUnit->unit_number,
                        'property_name' => $propertyUnit->property->property_name,
                        'property_id'   => $propertyUnit->property->id,
                    ],
                    'system_settings_at_creation' => [
                        'enable_tenant_invoicing'        => $this->settings->enable_tenant_invoicing,
                        'tenant_monthly_dues_amount'     => $this->settings->tenant_monthly_dues_amount,
                        'tenant_calculation_method'      => $this->settings->tenant_calculation_method,
                        'tenant_grace_period_days'       => $this->settings->tenant_grace_period_days,
                        'tenant_late_payment_percentage' => $this->settings->tenant_late_payment_percentage,
                    ],
                ],
            ]);

            DB::commit();

            // ── Notify tenant if requested ──
            if ($request->boolean('send_notification') && $this->settings->send_tenant_payment_reminders) {
                try {
                    $result     = $this->notificationService->sendTenantInvoiceCreated($invoice);
                    $dispatched = $result['dispatched'] ?? [];
                    $sent       = array_keys(array_filter($dispatched));

                    if (!empty($sent)) {
                        session()->flash('notification_sent_via', implode(', ', $sent));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[TenantInvoiceController] Created notification failed', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $this->notifyAdminsAboutInvoiceCreation($invoice);

            return redirect()->route('admin.tenant-invoices.show', $invoice->id)
                ->with('success', '✅ Tenant invoice created successfully.')
                ->with('invoice_details', [
                    'total'    => $this->settings->formatAmount($totalAmount),
                    'due_date' => Carbon::parse($request->due_date)->format('M d, Y'),
                    'period'   => Carbon::parse($request->period . '-01')->format('F Y'),
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create tenant invoice: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', '❌ Failed to create invoice: ' . $e->getMessage())
                ->withInput();
        }
    }

    /* ============================================================
     | SHOW / PRINT — read-only
     * ============================================================ */

    public function show(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->route('tenant.invoices.show', $tenantInvoice->id)
                ->with('error', 'Unauthorized access.');
        }

        $tenantInvoice->load(['tenant', 'propertyUnit.property', 'creator', 'updater']);

        $penaltyInfo       = $this->calculatePenaltyInfo($tenantInvoice);
        $withinGracePeriod = $this->isWithinGracePeriod($tenantInvoice);
        $daysOverdue       = $this->calculateDaysOverdue($tenantInvoice);

        $invoice = $tenantInvoice;

        return view('admin.tenant-invoices.show', compact('invoice'))->with([
            'enable_tenant_invoicing'         => $this->settings->enable_tenant_invoicing,
            'currency_symbol'                 => $this->settings->currency_symbol,
            'currency_position'               => $this->settings->currency_position,
            'tenant_grace_period_days'        => $this->settings->tenant_grace_period_days,
            'tenant_late_payment_percentage'  => $this->settings->tenant_late_payment_percentage,
            'tenant_fixed_penalty_amount'     => $this->settings->tenant_fixed_penalty_amount,
            'penalty_info'                    => $penaltyInfo,
            'within_grace_period'             => $withinGracePeriod,
            'days_overdue'                    => $daysOverdue,
            'grace_period_end_date'           => $tenantInvoice->due_date->copy()->addDays($this->settings->tenant_grace_period_days ?? 7),
            'can_apply_auto_penalty'          => $this->canApplyAutoPenalty($tenantInvoice),
            'system_settings'                 => $this->settings,
        ]);
    }

    /**
     * Tenant-facing invoice detail — never restricted by billing.
     */
    public function showTenantInvoice(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isTenant() || auth()->id() !== $tenantInvoice->tenant_id) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
        }

        $tenantInvoice->load(['propertyUnit.property']);

        $paymentDetails      = $this->calculatePaymentDetails($tenantInvoice);
        $paymentInstructions = $this->getPaymentInstructions();

        return view('tenant.invoices.show', compact('tenantInvoice'))->with([
            'enable_tenant_invoicing'   => $this->settings->enable_tenant_invoicing,
            'currency_symbol'           => $this->settings->currency_symbol,
            'currency_position'         => $this->settings->currency_position,
            'payment_details'           => $paymentDetails,
            'payment_instructions'      => $paymentInstructions,
            'primary_payment_provider'  => $this->settings->primary_payment_provider,
            'payment_mobile_number'     => $this->settings->payment_mobile_number,
            'payment_account_name'      => $this->settings->payment_account_name,
            'payment_network'           => $this->settings->payment_network,
            'grace_period_end_date'     => $tenantInvoice->due_date->copy()->addDays($this->settings->tenant_grace_period_days ?? 7),
            'days_until_due'            => now()->startOfDay()->diffInDays($tenantInvoice->due_date, false),
            'system_settings'           => $this->settings,
        ]);
    }

    /* ============================================================
     | UPDATE — admin write operation
     * ============================================================ */

    /**
     * Update a tenant invoice (Admin only) — supports amount + due date changes.
     *
     * When `send_notification` is true and the amount or due date actually
     * changed, dispatches an update notification via every configured channel,
     * routing through the overdue channel set if the invoice is past due.
     */
    public function update(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can update invoices.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('update_tenant_invoice');

        if ($tenantInvoice->isPaid()) {
            return redirect()->back()->with('error', 'Cannot update a paid invoice.');
        }

        $validator = Validator::make($request->all(), [
            'community_dues'     => 'required|numeric|min:0.01',
            'additional_charges' => 'nullable|numeric|min:0',
            'due_date'           => 'required|date',
            'description'        => 'nullable|string|max:500',
            'notes'              => 'nullable|string',
            'send_notification'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $oldAmount  = (float) $tenantInvoice->total_amount;
            $oldDueDate = $tenantInvoice->due_date;
            $wasOverdue = ($tenantInvoice->status === TenantInvoice::STATUS_OVERDUE)
                || ($tenantInvoice->due_date && $tenantInvoice->due_date < now());

            $communityDues     = (float) $request->community_dues;
            $additionalCharges = (float) ($request->additional_charges ?? 0);
            $penaltyAmount     = (float) ($tenantInvoice->penalty_amount ?? 0);
            $newTotal          = $communityDues + $additionalCharges + $penaltyAmount;

            $alreadyPaid = (float) ($tenantInvoice->paid_amount ?? 0);
            $newBalance  = max(0, $newTotal - $alreadyPaid);

            $tenantInvoice->update([
                'community_dues'     => $communityDues,
                'additional_charges' => $additionalCharges,
                'total_amount'       => $newTotal,
                'balance'            => $newBalance,
                'due_date'           => Carbon::parse($request->due_date),
                'description'        => $request->description,
                'updated_by'         => auth()->id(),
            ]);

            if ($request->filled('notes')) {
                $metadata = $tenantInvoice->metadata ?? [];
                $metadata['update_notes'] = $metadata['update_notes'] ?? [];
                $metadata['update_notes'][] = [
                    'note'       => $request->notes,
                    'updated_by' => auth()->user()->name,
                    'updated_at' => now()->toDateTimeString(),
                ];
                $tenantInvoice->update(['metadata' => $metadata]);
            }

            DB::commit();

            // ── Notify tenant if amount or due date changed ──
            $amountChanged  = abs($oldAmount - $newTotal) > 0.001;
            $dueDateChanged = $oldDueDate && $oldDueDate->ne($tenantInvoice->due_date);
            $shouldNotify   = (bool) ($request->send_notification ?? false);

            if ($shouldNotify
                && $this->settings->send_tenant_payment_reminders
                && ($amountChanged || $dueDateChanged)) {

                $updateData = [
                    'invoice_number'       => $tenantInvoice->invoice_number,
                    'old_amount'           => $oldAmount,
                    'new_amount'           => $newTotal,
                    'old_due_date'         => $oldDueDate?->format('M d, Y'),
                    'new_due_date'         => $tenantInvoice->due_date->format('M d, Y'),
                    'formatted_old_amount' => $this->settings->formatAmount($oldAmount),
                    'formatted_new_amount' => $this->settings->formatAmount($newTotal),
                    'was_overdue'          => $wasOverdue,
                ];

                $this->sendInvoiceUpdateNotification($tenantInvoice->fresh(), $updateData, $wasOverdue);
            }

            return redirect()->back()->with('success', '✅ Invoice updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update tenant invoice: ' . $e->getMessage(), [
                'invoice_id' => $tenantInvoice->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', '❌ Failed to update invoice.');
        }
    }

    /* ============================================================
     | MARK AS PAID — admin write operation
     * ============================================================ */

    public function markAsPaid(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('mark_tenant_invoice_paid');

        if ($tenantInvoice->isPaid()) {
            return redirect()->back()->with('info', 'This invoice is already marked as paid.');
        }

        $validator = Validator::make($request->all(), [
            'payment_method'    => 'required|string|in:mtn_momo,telecel_cash,airteltigo_cash,paystack,bank_transfer,cash,other',
            'payment_reference' => 'nullable|string|max:255',
            'payment_date'      => 'required|date',
            'amount_paid'       => 'required|numeric|min:' . $tenantInvoice->total_amount,
            'notes'             => 'nullable|string',
            'send_confirmation' => 'nullable|boolean',
        ], [
            'amount_paid.min' => 'The payment amount must be at least the full invoice amount of '
                . $this->settings->formatAmount($tenantInvoice->total_amount)
                . '. Partial payments are not accepted.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $excessPayment = $request->amount_paid - $tenantInvoice->total_amount;
            $finalAmount   = $tenantInvoice->total_amount;

            $tenantInvoice->update([
                'status'            => TenantInvoice::STATUS_PAID,
                'paid_amount'       => $finalAmount,
                'balance'           => 0,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'payment_date'      => Carbon::parse($request->payment_date),
                'updated_by'        => auth()->id(),
            ]);

            $metadata = $tenantInvoice->metadata ?? [];
            $metadata['payments'] = $metadata['payments'] ?? [];
            $metadata['payments'][] = [
                'amount'      => $finalAmount,
                'method'      => $request->payment_method,
                'reference'   => $request->payment_reference,
                'date'        => $request->payment_date,
                'recorded_by' => auth()->user()->name,
                'recorded_at' => now()->toDateTimeString(),
                'notes'       => $request->notes,
            ];

            $metadata['fully_paid_at'] = now()->toDateTimeString();
            $metadata['fully_paid_by'] = auth()->user()->name;

            if ($excessPayment > 0) {
                $metadata['excess_payment'] = [
                    'amount'   => $excessPayment,
                    'notes'    => 'Payment exceeded invoice amount by ' . $this->settings->formatAmount($excessPayment),
                    'handling' => 'Recorded as credit for future invoices',
                ];
            }

            $tenantInvoice->update(['metadata' => $metadata]);

            DB::commit();

            // ── Notify tenant (payment confirmation) ──
            if ($request->boolean('send_confirmation') && $this->settings->send_tenant_payment_reminders) {
                try {
                    $result     = $this->notificationService->sendTenantPaymentConfirmation($tenantInvoice);
                    $dispatched = $result['dispatched'] ?? [];
                    $sent       = array_keys(array_filter($dispatched));

                    if (!empty($sent)) {
                        session()->flash('confirmation_sent_via', implode(', ', $sent));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[TenantInvoiceController] Payment confirmation failed', [
                        'invoice_id' => $tenantInvoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $this->notifyAdminsAboutPayment($tenantInvoice, $finalAmount);

            $message = '✅ Invoice marked as fully paid successfully.';
            if ($excessPayment > 0) {
                $message .= ' Note: Payment exceeded invoice amount by '
                    . $this->settings->formatAmount($excessPayment)
                    . '. This has been recorded as a credit for future invoices.';
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::RollBack();
            Log::error('Failed to mark tenant invoice as paid: ' . $e->getMessage());

            return redirect()->back()->with('error', '❌ Failed to mark invoice as paid.');
        }
    }

    /* ============================================================
     | GENERATION — admin write operations
     * ============================================================ */

    public function generateMonthlyInvoices(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('generate_monthly_tenant_invoices');

        if (!$this->settings->enable_tenant_invoicing) {
            return redirect()->back()
                ->with('warning', '⚠️ Tenant invoicing is disabled. Enable it in System Settings to generate invoices.');
        }

        if (!$this->settings->auto_generate_tenant_invoices && !$request->boolean('force')) {
            return redirect()->back()
                ->with('warning', '⚠️ Auto-generation of tenant invoices is disabled. Use "Force Generation" to override or enable it in System Settings.');
        }

        $period            = $request->period ?? now()->startOfMonth()->format('Y-m');
        $sendNotifications = $request->boolean('send_notifications', (bool) $this->settings->send_tenant_payment_reminders);
        $forceGeneration   = $request->boolean('force', false);

        $result = $this->tenantInvoiceService->generateMonthlyTenantInvoices(
            $period,
            $sendNotifications,
            'community_dues',
            $forceGeneration
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()->with('error', '❌ ' . ($result['message'] ?? 'Generation failed.'));
        }

        $generatedCount = (int) ($result['generated_count'] ?? $result['details']['generated_count'] ?? 0);
        $skippedCount   = (int) ($result['skipped_count']   ?? $result['details']['skipped_count']   ?? 0);
        $totalAmount    = (float) ($result['total_amount']  ?? $result['details']['total_amount']    ?? 0);
        $errors         = $result['errors'] ?? $result['details']['errors'] ?? [];

        Log::info('Monthly tenant invoices generated', [
            'period'                  => $period,
            'generated_by'            => auth()->id(),
            'generated_count'         => $generatedCount,
            'skipped_count'           => $skippedCount,
            'total_amount'            => $totalAmount,
            'errors_count'            => is_array($errors) ? count($errors) : 0,
            'auto_generation_enabled' => $this->settings->auto_generate_tenant_invoices,
        ]);

        $successMessage = $result['message'] ?? "Generated {$generatedCount} invoice(s).";

        if ($generatedCount > 0 && $totalAmount > 0) {
            $successMessage .= ' Total amount: ' . $this->settings->formatAmount($totalAmount);
        }

        if ($skippedCount > 0) {
            $successMessage .= " ({$skippedCount} already existed, skipped)";
        }

        return redirect()->back()
            ->with('success', '✅ ' . $successMessage)
            ->with('generation_details', [
                'generated_count' => $generatedCount,
                'skipped_count'   => $skippedCount,
                'total_amount'    => $totalAmount,
                'period'          => $period,
                'errors'          => $errors,
            ]);
    }

    /**
     * Console-friendly generator. Called from scheduled commands, NOT from HTTP.
     *
     * NOTE: console commands run outside the request lifecycle, so middleware
     * doesn't apply. The generated invoices are records of what tenants owe —
     * blocking them would prevent the collection of the very funds needed to
     * resolve the developer bill. So this method is intentionally NOT gated.
     * It DOES check the settings toggles (enable_tenant_invoicing,
     * auto_generate_tenant_invoices), which is where the developer controls it.
     */
    public function generateTenantInvoicesForPeriod(
        ?string $period = null,
        bool $sendNotifications = true,
        bool $force = false,
        bool $dryRun = false
    ): array {
        $period = $period ?: now()->startOfMonth()->format('Y-m');

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return [
                'success' => false,
                'message' => "Invalid period format '{$period}'. Expected Y-m.",
                'details' => [],
            ];
        }

        if (!$this->settings->enable_tenant_invoicing) {
            Log::info('[TenantInvoiceController] Generation skipped — tenant invoicing disabled', [
                'period' => $period,
            ]);

            return [
                'success' => false,
                'message' => 'Tenant invoicing is disabled in system settings.',
                'details' => ['skipped_reason' => 'enable_tenant_invoicing=false'],
            ];
        }

        if (!$this->settings->auto_generate_tenant_invoices && !$force) {
            Log::info('[TenantInvoiceController] Generation skipped — auto-generation disabled', [
                'period' => $period,
            ]);

            return [
                'success' => false,
                'message' => 'Auto-generation of tenant invoices is disabled. Pass --force to override.',
                'details' => ['skipped_reason' => 'auto_generate_tenant_invoices=false'],
            ];
        }

        try {
            $result = $this->tenantInvoiceService->generateMonthlyTenantInvoices(
                $period,
                $sendNotifications,
                'community_dues',
                $force,
                $dryRun
            );

            $generatedCount = (int) ($result['generated_count'] ?? $result['details']['generated_count'] ?? 0);
            $skippedCount   = (int) ($result['skipped_count']   ?? $result['details']['skipped_count']   ?? 0);
            $totalAmount    = (float) ($result['total_amount']  ?? $result['details']['total_amount']    ?? 0);
            $errors         = $result['errors'] ?? $result['details']['errors'] ?? [];

            $normalizedDetails = [
                'generated_count' => $generatedCount,
                'skipped_count'   => $skippedCount,
                'total_amount'    => $totalAmount,
                'errors'          => $errors,
                'period'          => $period,
                'dry_run'         => $dryRun,
            ];

            Log::info('[TenantInvoiceController] Console generation completed', [
                'period'          => $period,
                'force'           => $force,
                'dry_run'         => $dryRun,
                'success'         => $result['success'] ?? false,
                'generated_count' => $generatedCount,
                'skipped_count'   => $skippedCount,
                'total_amount'    => $totalAmount,
                'errors_count'    => is_array($errors) ? count($errors) : 0,
            ]);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? 'Completed.',
                'details' => $normalizedDetails,
            ];

        } catch (\Throwable $e) {
            Log::error('[TenantInvoiceController] Console generation failed', [
                'period' => $period,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /* ============================================================
     | REMINDERS — communication actions, not gated
     * ============================================================ */

    public function sendScheduledPaymentReminders(bool $dryRun = false): array
    {
        $results = [
            'sent'    => 0,
            'skipped' => 0,
            'failed'  => 0,
            'details' => [],
        ];

        if (!$this->settings->send_tenant_payment_reminders) {
            Log::info('[TenantInvoiceController] Scheduled reminders skipped — send_tenant_payment_reminders=false');
            return $results;
        }

        if (!$this->settings->enable_tenant_invoicing) {
            Log::info('[TenantInvoiceController] Scheduled reminders skipped — enable_tenant_invoicing=false');
            return $results;
        }

        $daysBefore = (int) ($this->settings->reminder_days_before ?? 7);
        if ($daysBefore < 1)  { $daysBefore = 1; }
        if ($daysBefore > 30) { $daysBefore = 30; }

        $windowStart = now()->startOfDay();
        $windowEnd   = now()->copy()->addDays($daysBefore)->endOfDay();

        $graceDays     = (int) ($this->settings->tenant_grace_period_days ?? 7);
        $overdueCutoff = now()->copy()->subDays($graceDays)->startOfDay();

        Log::info('[TenantInvoiceController] Scheduled reminder scan', [
            'window_start' => $windowStart->toDateTimeString(),
            'window_end'   => $windowEnd->toDateTimeString(),
            'days_before'  => $daysBefore,
            'dry_run'      => $dryRun,
        ]);

        $invoices = TenantInvoice::with(['tenant', 'propertyUnit.property'])
            ->where('status', TenantInvoice::STATUS_PENDING)
            ->where(function ($q) use ($windowStart, $windowEnd, $overdueCutoff) {
                $q->whereBetween('due_date', [$windowStart, $windowEnd])
                  ->orWhereBetween('due_date', [$overdueCutoff, $windowStart]);
            })
            ->orderBy('due_date')
            ->get();

        foreach ($invoices as $invoice) {
            $metadata = $invoice->metadata ?? [];
            $lastSent = $metadata['last_manual_reminder_sent_at']
                ?? $metadata['last_scheduled_reminder_sent_at']
                ?? null;

            if ($lastSent && Carbon::parse($lastSent)->isToday()) {
                $results['skipped']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'reason'     => 'already_sent_today',
                ];
                continue;
            }

            $tenant = $invoice->tenant;
            if (!$tenant || (empty($tenant->email) && empty($tenant->phone))) {
                $results['skipped']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'reason'     => 'tenant_has_no_contact',
                ];
                continue;
            }

            if ($dryRun) {
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'tenant'     => $tenant->name,
                    'due_date'   => optional($invoice->due_date)->format('Y-m-d'),
                    'amount'     => $invoice->total_amount,
                    'would_send' => true,
                ];
                continue;
            }

            try {
                $result     = $this->notificationService->sendTenantInvoiceReminder($invoice);
                $dispatched = $result['dispatched'] ?? [];
                $sent       = array_keys(array_filter($dispatched));

                $reminders = $metadata['reminders_sent'] ?? [];
                $reminders[] = [
                    'sent_at'  => now()->toDateTimeString(),
                    'type'     => 'scheduled',
                    'channel'  => 'auto',
                    'channels' => $sent,
                ];
                $metadata['reminders_sent'] = $reminders;
                $metadata['last_scheduled_reminder_sent_at'] = now()->toDateTimeString();
                $invoice->update(['metadata' => $metadata]);

                $results['sent']++;
                $results['details'][] = [
                    'invoice_id'    => $invoice->id,
                    'tenant'        => $tenant->name,
                    'due_date'      => optional($invoice->due_date)->format('Y-m-d'),
                    'sent'          => true,
                    'channels_used' => $sent,
                ];

            } catch (\Throwable $e) {
                $results['failed']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ];

                Log::error('[TenantInvoiceController] Scheduled reminder failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('[TenantInvoiceController] Scheduled reminder dispatch complete', [
            'sent'    => $results['sent'],
            'skipped' => $results['skipped'],
            'failed'  => $results['failed'],
            'dry_run' => $dryRun,
        ]);

        return $results;
    }

    /**
     * Admin-triggered reminder — communication, not gated.
     */
    public function sendReminder(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => 'Unauthorized'], 403)
                : redirect()->back()->with('error', 'Unauthorized');
        }

        if (!$this->settings->send_tenant_payment_reminders) {
            $msg = 'Payment reminders are disabled in System Settings.';
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : redirect()->back()->with('error', $msg);
        }

        try {
            $result     = $this->notificationService->sendTenantInvoiceReminder($tenantInvoice);
            $dispatched = $result['dispatched'] ?? [];
            $sent       = array_keys(array_filter($dispatched));

            $metadata = $tenantInvoice->metadata ?? [];
            $reminders = $metadata['reminders_sent'] ?? [];
            $reminders[] = [
                'sent_at'  => now()->toDateTimeString(),
                'type'     => 'manual',
                'sent_by'  => auth()->user()->name,
                'channels' => $sent,
            ];
            $metadata['reminders_sent'] = $reminders;
            $metadata['last_manual_reminder_sent_at'] = now()->toDateTimeString();
            $tenantInvoice->update(['metadata' => $metadata]);

            $message = !empty($sent)
                ? 'Reminder sent successfully via: ' . implode(', ', $sent)
                : 'Reminder dispatched but no channels reported success.';

            if ($request->ajax()) {
                return response()->json([
                    'success'       => !empty($sent),
                    'message'       => $message,
                    'channels_used' => $sent,
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to send reminder: ' . $e->getMessage(), [
                'invoice_id' => $tenantInvoice->id,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send reminder: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to send reminder');
        }
    }

    public function bulkSendReminders(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->settings->send_tenant_payment_reminders) {
            return response()->json([
                'success' => false,
                'message' => 'Payment reminders are disabled in System Settings.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'integer|exists:tenant_invoices,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $invoices = TenantInvoice::whereIn('id', $request->invoice_ids)
            ->with(['tenant'])
            ->get();

        $sentCount    = 0;
        $failedCount  = 0;
        $channelsUsed = [];

        foreach ($invoices as $invoice) {
            try {
                $result     = $this->notificationService->sendTenantInvoiceReminder($invoice);
                $dispatched = $result['dispatched'] ?? [];
                $sent       = array_keys(array_filter($dispatched));

                $metadata = $invoice->metadata ?? [];
                $reminders = $metadata['reminders_sent'] ?? [];
                $reminders[] = [
                    'sent_at'  => now()->toDateTimeString(),
                    'type'     => 'bulk_manual',
                    'sent_by'  => auth()->user()->name,
                    'channels' => $sent,
                ];
                $metadata['reminders_sent'] = $reminders;
                $metadata['last_manual_reminder_sent_at'] = now()->toDateTimeString();
                $invoice->update(['metadata' => $metadata]);

                if (!empty($sent)) {
                    $sentCount++;
                    $channelsUsed = array_values(array_unique(array_merge($channelsUsed, $sent)));
                } else {
                    $failedCount++;
                }

            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to send reminder for invoice {$invoice->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success'       => true,
            'sent_count'    => $sentCount,
            'failed_count'  => $failedCount,
            'channels_used' => $channelsUsed,
        ]);
    }

    /* ============================================================
     | NOTIFICATION HELPERS — internal
     * ============================================================ */

    private function sendInvoiceUpdateNotification(TenantInvoice $invoice, array $updateData, bool $wasOverdue = false): void
    {
        try {
            $result = $this->notificationService->sendTenantInvoiceUpdate($invoice, $updateData);

            Log::info('[TenantInvoiceController] Invoice update notification dispatched', [
                'invoice_id'  => $invoice->id,
                'was_overdue' => $wasOverdue,
                'channels'    => $result['channels']   ?? [],
                'dispatched'  => $result['dispatched'] ?? [],
                'skipped'     => $result['skipped']    ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send tenant invoice update notification: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    private function sendInvoiceNotification(TenantInvoice $invoice, string $type = 'created')
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send invoice notification: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                    'tenant_id'  => $invoice->tenant_id,
                ]);
                return;
            }

            if (!$this->settings->send_tenant_payment_reminders) {
                Log::info('Tenant payment reminders are disabled, skipping notification', [
                    'invoice_id' => $invoice->id,
                    'type'       => $type,
                ]);
                return;
            }

            switch ($type) {
                case 'created':
                    if (method_exists($this->notificationService, 'sendTenantInvoiceCreated')) {
                        $this->notificationService->sendTenantInvoiceCreated($invoice);
                    } else {
                        Mail::to($tenant->email)
                            ->send(new TenantInvoiceCreated($invoice, $this->settings));
                    }
                    break;

                case 'reminder':
                    $this->notificationService->sendTenantInvoiceReminder($invoice);
                    break;

                case 'overdue':
                    if (method_exists($this->notificationService, 'sendTenantInvoiceOverdue')) {
                        $this->notificationService->sendTenantInvoiceOverdue($invoice);
                    } else {
                        $this->notificationService->sendTenantInvoiceReminder($invoice);
                    }
                    break;

                default:
                    Log::warning('Unknown tenant invoice notification type', [
                        'invoice_id' => $invoice->id,
                        'type'       => $type,
                    ]);
                    return;
            }

            Log::info("Invoice {$type} notification dispatched", [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $invoice->tenant_id,
                'type'       => $type,
            ]);

        } catch (\Throwable $e) {
            Log::error("Failed to send invoice {$type} notification: " . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'type'       => $type,
            ]);
        }
    }

    private function sendPaymentConfirmation(TenantInvoice $invoice)
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send payment confirmation: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            if (!$this->settings->send_tenant_payment_reminders) {
                Log::info('Tenant payment reminders are disabled, skipping payment confirmation', [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            if (method_exists($this->notificationService, 'sendTenantPaymentConfirmation')) {
                $this->notificationService->sendTenantPaymentConfirmation($invoice);
            } else {
                Mail::to($tenant->email)
                    ->send(new TenantPaymentConfirmation($invoice, $this->settings));
            }

            Log::info('Payment confirmation dispatched', [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $invoice->tenant_id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    private function sendPenaltyNotification(TenantInvoice $invoice, float $penaltyAmount)
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send penalty notification: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            if (!$this->settings->send_tenant_payment_reminders) {
                Log::info('Tenant payment reminders are disabled, skipping penalty notification', [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            if (method_exists($this->notificationService, 'sendTenantPenaltyApplied')) {
                $this->notificationService->sendTenantPenaltyApplied($invoice, $penaltyAmount);
            } else {
                Log::warning('No sendTenantPenaltyApplied() method on NotificationService — penalty email skipped', [
                    'invoice_id'     => $invoice->id,
                    'penalty_amount' => $penaltyAmount,
                ]);
            }

            Log::info('Penalty notification dispatched', [
                'invoice_id'     => $invoice->id,
                'penalty_amount' => $penaltyAmount,
                'tenant_id'      => $invoice->tenant_id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to send penalty notification: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    private function notifyAdminsAboutInvoiceCreation(TenantInvoice $invoice)
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title'   => '📄 New Tenant Invoice Created',
                'message' => "Manual invoice created for {$invoice->tenant->name} for period " .
                             Carbon::parse($invoice->period . '-01')->format('M Y') .
                             " amounting to {$this->settings->formatAmount($invoice->total_amount)}",
                'icon'       => 'fas fa-file-invoice text-success',
                'category'   => 'tenant_invoices',
                'action_url' => route('admin.tenant-invoices.show', $invoice->id),
                'priority'   => 1,
                'data'       => [
                    'type'            => 'tenant_invoice_created',
                    'invoice_id'      => $invoice->id,
                    'tenant_id'       => $invoice->tenant_id,
                    'tenant_name'     => $invoice->tenant->name,
                    'amount'          => $invoice->total_amount,
                    'period'          => $invoice->period,
                    'created_by'      => auth()->id(),
                    'created_by_name' => auth()->user()->name,
                    'timestamp'       => now()->toISOString(),
                ],
            ];

            foreach ($admins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('Admins notified about invoice creation', [
                'invoice_id'  => $invoice->id,
                'admin_count' => $admins->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about invoice creation: ' . $e->getMessage());
        }
    }

    private function notifyAdminsAboutPayment(TenantInvoice $invoice, float $amountPaid)
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title'   => '💰 Invoice Paid',
                'message' => "Payment of {$this->settings->formatAmount($amountPaid)} received from {$invoice->tenant->name} for period " .
                             Carbon::parse($invoice->period . '-01')->format('M Y') .
                             ". Invoice is now fully paid.",
                'icon'       => 'fas fa-check-circle text-success',
                'category'   => 'tenant_payments',
                'action_url' => route('admin.tenant-invoices.show', $invoice->id),
                'priority'   => 2,
                'data'       => [
                    'type'            => 'tenant_payment_received',
                    'invoice_id'      => $invoice->id,
                    'tenant_id'       => $invoice->tenant_id,
                    'tenant_name'     => $invoice->tenant->name,
                    'amount_paid'     => $amountPaid,
                    'payment_method'  => $invoice->payment_method,
                    'recorded_by'     => auth()->id(),
                    'recorded_by_name' => auth()->user()->name,
                    'timestamp'       => now()->toISOString(),
                ],
            ];

            foreach ($admins as $admin) {
                $this->notifyUserWithData($admin, $notificationData);
            }

            Log::info('Admins notified about payment', [
                'invoice_id'  => $invoice->id,
                'amount_paid' => $amountPaid,
                'admin_count' => $admins->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about payment: ' . $e->getMessage());
        }
    }

    private function notifyAdminsAboutDeletion(TenantInvoice $invoice, Request $request): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $notificationData = [
                'title'   => '🗑️ Invoice Deleted',
                'message' => "Invoice #{$invoice->invoice_number} for period " .
                             Carbon::parse($invoice->period . '-01')->format('M Y') .
                             " was deleted by " . auth()->user()->name .
                             ". Amount: " . $this->settings->formatAmount($invoice->total_amount) .
                             ($request->input('reason') ? " Reason: {$request->input('reason')}" : ""),
                'icon'        => 'fas fa-trash-alt text-warning',
                'category'    => 'tenant_invoices',
                'action_url'  => route('admin.tenant-invoices.index', ['show_deleted' => true]),
                'priority'    => 2,
                'data'        => [
                    'type'             => 'tenant_invoice_deleted',
                    'invoice_id'       => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'tenant_id'        => $invoice->tenant_id,
                    'tenant_name'      => $invoice->tenant->name ?? 'Unknown',
                    'amount'           => $invoice->total_amount,
                    'period'           => $invoice->period,
                    'deleted_by'       => auth()->id(),
                    'deleted_by_name'  => auth()->user()->name,
                    'deleted_at'       => now()->toISOString(),
                    'deletion_reason'  => $request->input('reason', 'Manual deletion'),
                    'deletion_ip'      => $request->ip(),
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
     | PENALTIES — admin write operations
     * ============================================================ */

    public function applyAutomaticPenalties(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('apply_tenant_auto_penalties');

        $hasPercentagePenalty = ($this->settings->tenant_late_payment_percentage ?? 0) > 0;
        $hasFixedPenalty      = ($this->settings->tenant_fixed_penalty_amount ?? 0) > 0;

        if (!$hasPercentagePenalty && !$hasFixedPenalty) {
            return redirect()->back()
                ->with('info', 'ℹ️ No penalties are configured in system settings. Set up late payment penalties to enable automatic application.');
        }

        $validator = Validator::make($request->all(), [
            'apply_to' => 'required|in:all_overdue,selected_months',
            'months'   => 'required_if:apply_to,selected_months|array',
            'months.*' => 'date_format:Y-m',
            'dry_run'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $dryRun = $request->boolean('dry_run', false);

        try {
            $query = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)
                ->where('due_date', '<', now()->subDays($this->settings->tenant_grace_period_days ?? 7))
                ->where(function ($q) {
                    $q->whereNull('penalty_applied_at')
                      ->orWhere('penalty_amount', 0);
                });

            if ($request->apply_to === 'selected_months' && $request->has('months')) {
                $query->whereIn('period', $request->months);
            }

            $invoices = $query->get();
            $results = [
                'total_invoices'       => $invoices->count(),
                'processed'            => 0,
                'skipped'              => 0,
                'total_penalty_amount' => 0,
                'details'              => [],
            ];

            foreach ($invoices as $invoice) {
                $daysOverdue = now()->startOfDay()->diffInDays($invoice->due_date);

                $penaltyAmount = 0;

                if ($hasPercentagePenalty) {
                    $penaltyAmount = $invoice->total_amount * ($this->settings->tenant_late_payment_percentage / 100);
                } elseif ($hasFixedPenalty) {
                    $penaltyAmount = $this->settings->tenant_fixed_penalty_amount;
                }

                $penaltyAmount = round($penaltyAmount, $this->settings->decimal_places ?? 2);

                if ($penaltyAmount > 0 && !$dryRun) {
                    $invoice->update([
                        'penalty_amount'     => ($invoice->penalty_amount ?? 0) + $penaltyAmount,
                        'total_amount'       => $invoice->total_amount + $penaltyAmount,
                        'balance'            => $invoice->balance + $penaltyAmount,
                        'penalty_applied_at' => now(),
                        'metadata'           => array_merge($invoice->metadata ?? [], [
                            'last_penalty_applied' => [
                                'amount'       => $penaltyAmount,
                                'date'         => now()->toDateTimeString(),
                                'days_overdue' => $daysOverdue,
                                'applied_by'   => 'system',
                            ],
                        ]),
                    ]);

                    $results['processed']++;
                    $results['total_penalty_amount'] += $penaltyAmount;
                    $results['details'][] = [
                        'invoice_id'     => $invoice->id,
                        'tenant_name'    => $invoice->tenant->name,
                        'period'         => $invoice->period,
                        'days_overdue'   => $daysOverdue,
                        'penalty_amount' => $penaltyAmount,
                    ];
                } elseif ($penaltyAmount > 0) {
                    $results['details'][] = [
                        'invoice_id'     => $invoice->id,
                        'tenant_name'    => $invoice->tenant->name,
                        'period'         => $invoice->period,
                        'days_overdue'   => $daysOverdue,
                        'penalty_amount' => $penaltyAmount,
                        'would_apply'    => true,
                    ];
                } else {
                    $results['skipped']++;
                }
            }

            if ($dryRun) {
                return redirect()->back()
                    ->with('info', "🔍 DRY RUN: Would apply penalties to {$results['processed']} invoices totaling {$this->settings->formatAmount($results['total_penalty_amount'])}")
                    ->with('penalty_details', $results['details']);
            }

            Log::info('Automatic penalties applied', [
                'processed'    => $results['processed'],
                'total_amount' => $results['total_penalty_amount'],
                'applied_by'   => auth()->id(),
            ]);

            return redirect()->back()
                ->with('success', "✅ Applied penalties to {$results['processed']} invoices totaling {$this->settings->formatAmount($results['total_penalty_amount'])}")
                ->with('penalty_details', $results['details']);

        } catch (\Exception $e) {
            Log::error('Failed to apply automatic penalties: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', '❌ Failed to apply penalties: ' . $e->getMessage());
        }
    }

    public function applyPenalty(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('apply_tenant_penalty');

        if ($tenantInvoice->isPaid()) {
            return redirect()->back()
                ->with('error', '❌ Cannot apply penalty to a paid invoice.');
        }

        $validator = Validator::make($request->all(), [
            'penalty_amount'        => 'required|numeric|min:0.01',
            'reason'                => 'required|string|max:255',
            'apply_system_defaults' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $penaltyAmount = $request->penalty_amount;

            if ($request->boolean('apply_system_defaults')) {
                $hasPercentage = ($this->settings->tenant_late_payment_percentage ?? 0) > 0;
                $hasFixed = ($this->settings->tenant_fixed_penalty_amount ?? 0) > 0;

                if (!$hasPercentage && !$hasFixed) {
                    return redirect()->back()
                        ->with('warning', 'No penalty settings configured in system. Please enter a manual amount.');
                }

                if ($hasPercentage && $hasFixed) {
                    $calculatedPenalty = $tenantInvoice->total_amount * ($this->settings->tenant_late_payment_percentage / 100);
                    $penaltyAmount = round($calculatedPenalty, $this->settings->decimal_places ?? 2);
                } elseif ($hasPercentage) {
                    $calculatedPenalty = $tenantInvoice->total_amount * ($this->settings->tenant_late_payment_percentage / 100);
                    $penaltyAmount = round($calculatedPenalty, $this->settings->decimal_places ?? 2);
                } elseif ($hasFixed) {
                    $penaltyAmount = $this->settings->tenant_fixed_penalty_amount;
                }
            }

            $tenantInvoice->update([
                'penalty_amount'     => ($tenantInvoice->penalty_amount ?? 0) + $penaltyAmount,
                'total_amount'       => $tenantInvoice->total_amount + $penaltyAmount,
                'balance'            => $tenantInvoice->balance + $penaltyAmount,
                'penalty_applied_at' => now(),
            ]);

            $metadata = $tenantInvoice->metadata ?? [];
            $metadata['penalty_applied'] = $metadata['penalty_applied'] ?? [];
            $metadata['penalty_applied'][] = [
                'amount'                => $penaltyAmount,
                'reason'                => $request->reason,
                'applied_by'            => auth()->user()->name,
                'applied_at'            => now()->toDateTimeString(),
                'using_system_defaults' => $request->boolean('apply_system_defaults'),
                'system_percentage'     => $this->settings->tenant_late_payment_percentage,
                'system_fixed'          => $this->settings->tenant_fixed_penalty_amount,
            ];
            $tenantInvoice->update(['metadata' => $metadata]);

            DB::commit();

            if ($this->settings->send_tenant_payment_reminders) {
                $this->sendPenaltyNotification($tenantInvoice, $penaltyAmount);
            }

            return redirect()->back()
                ->with('success', "✅ Penalty of {$this->settings->formatAmount($penaltyAmount)} applied successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply penalty: ' . $e->getMessage());

            return redirect()->back()->with('error', '❌ Failed to apply penalty.');
        }
    }

    public function removePenalty(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('remove_tenant_penalty');

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $penaltyAmount = $tenantInvoice->penalty_amount ?? 0;

            if ($penaltyAmount <= 0) {
                return redirect()->back()->with('info', 'No penalty to remove from this invoice.');
            }

            $tenantInvoice->update([
                'penalty_amount'     => 0,
                'total_amount'       => $tenantInvoice->total_amount - $penaltyAmount,
                'balance'            => $tenantInvoice->balance - $penaltyAmount,
                'penalty_applied_at' => null,
            ]);

            $metadata = $tenantInvoice->metadata ?? [];
            $metadata['penalty_removed'] = $metadata['penalty_removed'] ?? [];
            $metadata['penalty_removed'][] = [
                'amount'     => $penaltyAmount,
                'reason'     => $request->reason,
                'removed_by' => auth()->user()->name,
                'removed_at' => now()->toDateTimeString(),
            ];
            $tenantInvoice->update(['metadata' => $metadata]);

            DB::commit();

            return redirect()->back()
                ->with('success', "✅ Penalty of {$this->settings->formatAmount($penaltyAmount)} removed successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove penalty: ' . $e->getMessage());

            return redirect()->back()->with('error', '❌ Failed to remove penalty.');
        }
    }

    /* ============================================================
     | DELETE / RESTORE — admin write operations
     * ============================================================ */

    public function destroy(Request $request, TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('delete_tenant_invoice');

        if (!$this->canDeleteInvoice($tenantInvoice)) {
            return redirect()->back()
                ->with('error', $this->getDeletionErrorMessage($tenantInvoice));
        }

        try {
            DB::beginTransaction();

            $archive = $this->createArchivalRecord($tenantInvoice, $request);

            Log::info('Tenant invoice soft deleted with archive', [
                'invoice_id'       => $tenantInvoice->id,
                'invoice_number'   => $tenantInvoice->invoice_number,
                'archive_id'       => $archive->id,
                'tenant_id'        => $tenantInvoice->tenant_id,
                'tenant_name'      => $tenantInvoice->tenant->name ?? 'Unknown',
                'period'           => $tenantInvoice->period,
                'amount'           => $tenantInvoice->total_amount,
                'status'           => $tenantInvoice->status,
                'deleted_by'       => auth()->id(),
                'deleted_by_name'  => auth()->user()->name,
                'deleted_at'       => now()->toDateTimeString(),
                'deletion_reason'  => $request->input('reason', 'Manual deletion'),
                'deletion_ip'      => $request->ip(),
                'deletion_user_agent' => $request->userAgent(),
                'system_settings_at_deletion' => [
                    'enable_tenant_invoicing'        => $this->settings->enable_tenant_invoicing,
                    'auto_generate_tenant_invoices'  => $this->settings->auto_generate_tenant_invoices,
                    'tenant_grace_period_days'       => $this->settings->tenant_grace_period_days,
                ],
            ]);

            $tenantInvoice->delete();

            $this->notifyAdminsAboutDeletion($tenantInvoice, $request);

            DB::commit();

            return redirect()->route('admin.tenant-invoices.index')
                ->with('success', '✅ Invoice deleted successfully. It has been archived and can be restored if needed.')
                ->with('deleted_invoice_id', $tenantInvoice->id)
                ->with('archive_id', $archive->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete tenant invoice: ' . $e->getMessage(), [
                'invoice_id' => $tenantInvoice->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', '❌ Failed to delete invoice. Please try again or contact support.');
        }
    }

    public function restore($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only Super Admins can restore invoices.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('restore_tenant_invoice');

        try {
            DB::beginTransaction();

            $invoice = TenantInvoice::withTrashed()->findOrFail($id);

            if (!$invoice->trashed()) {
                return redirect()->back()->with('info', 'Invoice is not deleted.');
            }

            $invoice->restore();

            DB::commit();

            return redirect()->route('admin.tenant-invoices.trash')
                ->with('success', "✅ Invoice #{$invoice->invoice_number} restored successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore invoice: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', '❌ Failed to restore invoice.');
        }
    }

    public function forceDelete($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only Super Admins can permanently delete invoices.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('force_delete_tenant_invoice');

        try {
            DB::beginTransaction();

            $invoice = TenantInvoice::withTrashed()->findOrFail($id);

            if (!$invoice->trashed()) {
                return redirect()->back()->with('error', 'Invoice is not deleted.');
            }

            $archive = TenantInvoiceArchive::where('original_invoice_id', $invoice->id)->first();

            Log::warning('Tenant invoice permanently deleted (archive retained)', [
                'invoice_id'             => $invoice->id,
                'invoice_number'         => $invoice->invoice_number,
                'archive_id'             => $archive->id ?? null,
                'archive_retained'       => $archive ? true : false,
                'tenant_id'              => $invoice->tenant_id,
                'tenant_name'            => $invoice->tenant->name ?? 'Unknown',
                'period'                 => $invoice->period,
                'amount'                 => $invoice->total_amount,
                'deleted_by'             => auth()->id(),
                'deleted_by_name'        => auth()->user()->name,
                'original_deleted_at'    => $invoice->deleted_at,
                'permanently_deleted_at' => now()->toDateTimeString(),
            ]);

            $invoice->forceDelete();

            DB::commit();

            return redirect()->route('admin.tenant-invoices.trash')
                ->with('success', '✅ Invoice permanently deleted. Archive record retained for audit purposes.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete invoice: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', '❌ Failed to permanently delete invoice.');
        }
    }

    public function trash(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $invoices = TenantInvoice::onlyTrashed()
            ->with(['tenant', 'propertyUnit.property'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        $system_settings   = $this->settings;
        $currency_symbol   = $this->settings->currency_symbol;
        $currency_position = $this->settings->currency_position;
        $decimal_places    = $this->settings->decimal_places ?? 2;

        return view('admin.tenant-invoices.trash', compact(
            'invoices',
            'system_settings',
            'currency_symbol',
            'currency_position',
            'decimal_places'
        ));
    }

    /**
     * CLI-only batch deletion — no middleware, checks run in the command.
     */
    public function permanentlyDeleteOldInvoices(int $months = 12): array
    {
        if (app()->runningInConsole() === false) {
            Log::warning('Attempted to call permanent deletion from non-console context');
            return ['success' => false, 'message' => 'This action can only be performed via CLI'];
        }

        try {
            $cutoffDate = now()->subMonths($months);

            $oldDeletedInvoices = TenantInvoice::onlyTrashed()
                ->where('deleted_at', '<', $cutoffDate)
                ->get();

            $count = 0;
            $totalAmount = 0;

            foreach ($oldDeletedInvoices as $invoice) {
                $totalAmount += $invoice->total_amount;
                $invoice->forceDelete();
                $count++;

                Log::info('Invoice permanently deleted after retention period', [
                    'invoice_id'             => $invoice->id,
                    'invoice_number'         => $invoice->invoice_number,
                    'original_deleted_at'    => $invoice->deleted_at,
                    'permanently_deleted_at' => now(),
                    'months_retained'        => $months,
                ]);
            }

            return [
                'success'         => true,
                'message'         => "Permanently deleted {$count} invoices older than {$months} months",
                'count'           => $count,
                'total_amount'    => $totalAmount,
                'formatted_total' => $this->settings->formatAmount($totalAmount),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to permanently delete old invoices: ' . $e->getMessage());

            return [
                'success'      => false,
                'message'      => 'Failed to permanently delete old invoices: ' . $e->getMessage(),
                'count'        => 0,
                'total_amount' => 0,
            ];
        }
    }

    /* ============================================================
     | ARCHIVES — read-only
     * ============================================================ */

    public function archives(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = TenantInvoiceArchive::with(['tenant', 'propertyUnit.property'])
            ->orderBy('deleted_at', 'desc');

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        if ($request->filled('tenant_name')) {
            $query->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('deleted_from')) {
            $query->whereDate('deleted_at', '>=', $request->deleted_from);
        }

        $archives = $query->paginate(20);

        $system_settings = $this->settings;

        return view('admin.tenant-invoices.archives', compact('archives', 'system_settings'));
    }

    public function exportArchive($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $archive = TenantInvoiceArchive::with(['propertyUnit.property'])->findOrFail($id);

        $filename = "archive_{$archive->invoice_number}_" . date('Y-m-d') . ".json";

        return response()->json($archive, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportArchivePDF($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $archive = TenantInvoiceArchive::with(['propertyUnit.property'])->findOrFail($id);
        $settings = SystemSetting::getSettings();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.archive-pdf', compact('archive', 'settings'));

        return $pdf->download("archive-{$archive->invoice_number}.pdf");
    }

    public function exportAllArchives(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $format = $request->get('format', 'csv');
        $query = TenantInvoiceArchive::with(['propertyUnit.property']);

        if ($request->filled('selected_ids')) {
            $selectedIds = explode(',', $request->selected_ids);
            $query->whereIn('id', $selectedIds);

            Log::info('Exporting selected archives', [
                'count'   => count($selectedIds),
                'ids'     => $selectedIds,
                'format'  => $format,
                'user_id' => auth()->id(),
            ]);
        } else {
            if ($request->filled('invoice_number')) {
                $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
            }

            if ($request->filled('tenant_name')) {
                $query->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
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

            if ($request->filled('deleted_by')) {
                $query->where('deleted_by_name', 'like', '%' . $request->deleted_by . '%');
            }

            Log::info('Exporting filtered archives', [
                'filters' => $request->all(),
                'format'  => $format,
                'user_id' => auth()->id(),
            ]);
        }

        $archives = $query->orderBy('deleted_at', 'desc')->get();
        $settings = SystemSetting::getSettings();

        if ($archives->isEmpty()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found to export.',
                ], 404);
            }

            return redirect()->back()->with('error', 'No records found to export.');
        }

        switch ($format) {
            case 'json':
                return $this->exportAsJSON($archives);
            case 'excel':
                return $this->exportAsExcel($archives, $settings);
            case 'pdf':
                return $this->exportAsPDF($archives, $settings);
            case 'csv':
            default:
                return $this->exportAsCSV($archives, $settings);
        }
    }

    private function exportAsJSON($archives)
    {
        $data = $archives->map(function ($archive) {
            return [
                'id'                 => $archive->id,
                'invoice_number'     => $archive->invoice_number,
                'tenant_name'        => $archive->tenant_name,
                'tenant_id'          => $archive->tenant_id,
                'period'             => $archive->period,
                'month_name'         => $archive->month_name,
                'due_date'           => $archive->due_date->format('Y-m-d'),
                'total_amount'       => $archive->total_amount,
                'community_dues'     => $archive->community_dues,
                'additional_charges' => $archive->additional_charges,
                'penalty_amount'     => $archive->penalty_amount,
                'paid_amount'        => $archive->paid_amount,
                'balance'            => $archive->balance,
                'status'             => $archive->status,
                'payment_method'     => $archive->payment_method,
                'payment_reference'  => $archive->payment_reference,
                'payment_date'       => $archive->payment_date ? $archive->payment_date->format('Y-m-d') : null,
                'deleted_at'         => $archive->deleted_at->format('Y-m-d H:i:s'),
                'deleted_by'         => $archive->deleted_by_name,
                'deleted_by_id'      => $archive->deleted_by,
                'deletion_reason'    => $archive->deletion_reason,
                'deletion_ip'        => $archive->deletion_ip,
                'property_name'      => $archive->propertyUnit->property->property_name ?? 'N/A',
                'unit_number'        => $archive->propertyUnit->unit_number ?? 'N/A',
                'grace_period_days'  => $archive->grace_period_days,
                'calculation_method' => $archive->calculation_method,
                'original_created_at' => $archive->original_created_at ? \Carbon\Carbon::parse($archive->original_created_at)->format('Y-m-d H:i:s') : null,
                'original_created_by' => $archive->original_created_by,
            ];
        });

        $filename = 'archive_export_' . date('Y-m-d_His') . '.json';

        return response()->json([
            'export_date'   => now()->format('Y-m-d H:i:s'),
            'total_records' => $data->count(),
            'data'          => $data,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportAsCSV($archives, $settings)
    {
        $filename = 'archive_export_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number',
            'Tenant Name',
            'Tenant ID',
            'Period',
            'Month',
            'Due Date',
            'Total Amount',
            'Community Dues',
            'Additional Charges',
            'Penalty Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Payment Method',
            'Payment Reference',
            'Payment Date',
            'Deleted At',
            'Deleted By',
            'Deletion Reason',
            'Deletion IP',
            'Property',
            'Unit Number',
            'Grace Period Days',
            'Calculation Method',
            'Original Created At',
            'Original Created By',
        ]);

        foreach ($archives as $archive) {
            fputcsv($handle, [
                $archive->invoice_number,
                $archive->tenant_name,
                $archive->tenant_id,
                $archive->period,
                $archive->month_name,
                $archive->due_date->format('Y-m-d'),
                $archive->total_amount,
                $archive->community_dues,
                $archive->additional_charges,
                $archive->penalty_amount,
                $archive->paid_amount,
                $archive->balance,
                $archive->status,
                $archive->payment_method,
                $archive->payment_reference,
                $archive->payment_date ? $archive->payment_date->format('Y-m-d') : '',
                $archive->deleted_at->format('Y-m-d H:i:s'),
                $archive->deleted_by_name,
                $archive->deletion_reason,
                $archive->deletion_ip,
                $archive->propertyUnit->property->property_name ?? 'N/A',
                $archive->propertyUnit->unit_number ?? 'N/A',
                $archive->grace_period_days,
                $archive->calculation_method,
                $archive->original_created_at ? \Carbon\Carbon::parse($archive->original_created_at)->format('Y-m-d H:i:s') : '',
                $archive->original_created_by,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    private function exportAsExcel($archives, $settings)
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            Log::error('PhpSpreadsheet not installed. Please run: composer require phpoffice/phpspreadsheet');
            return redirect()->back()->with('error', 'Excel export is not available. Please contact administrator.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Archive Export');

        $headers = [
            'A1' => 'Invoice Number',
            'B1' => 'Tenant Name',
            'C1' => 'Tenant ID',
            'D1' => 'Period',
            'E1' => 'Month',
            'F1' => 'Due Date',
            'G1' => 'Total Amount',
            'H1' => 'Community Dues',
            'I1' => 'Additional Charges',
            'J1' => 'Penalty Amount',
            'K1' => 'Paid Amount',
            'L1' => 'Balance',
            'M1' => 'Status',
            'N1' => 'Payment Method',
            'O1' => 'Payment Reference',
            'P1' => 'Payment Date',
            'Q1' => 'Deleted At',
            'R1' => 'Deleted By',
            'S1' => 'Deletion Reason',
            'T1' => 'Deletion IP',
            'U1' => 'Property',
            'V1' => 'Unit Number',
            'W1' => 'Grace Period Days',
            'X1' => 'Calculation Method',
            'Y1' => 'Original Created At',
            'Z1' => 'Original Created By',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        $row = 2;
        foreach ($archives as $archive) {
            $sheet->setCellValue('A' . $row, $archive->invoice_number);
            $sheet->setCellValue('B' . $row, $archive->tenant_name);
            $sheet->setCellValue('C' . $row, $archive->tenant_id);
            $sheet->setCellValue('D' . $row, $archive->period);
            $sheet->setCellValue('E' . $row, $archive->month_name);
            $sheet->setCellValue('F' . $row, $archive->due_date->format('Y-m-d'));
            $sheet->setCellValue('G' . $row, $archive->total_amount);
            $sheet->setCellValue('H' . $row, $archive->community_dues);
            $sheet->setCellValue('I' . $row, $archive->additional_charges);
            $sheet->setCellValue('J' . $row, $archive->penalty_amount);
            $sheet->setCellValue('K' . $row, $archive->paid_amount);
            $sheet->setCellValue('L' . $row, $archive->balance);
            $sheet->setCellValue('M' . $row, $archive->status);
            $sheet->setCellValue('N' . $row, $archive->payment_method);
            $sheet->setCellValue('O' . $row, $archive->payment_reference);
            $sheet->setCellValue('P' . $row, $archive->payment_date ? $archive->payment_date->format('Y-m-d') : '');
            $sheet->setCellValue('Q' . $row, $archive->deleted_at->format('Y-m-d H:i:s'));
            $sheet->setCellValue('R' . $row, $archive->deleted_by_name);
            $sheet->setCellValue('S' . $row, $archive->deletion_reason);
            $sheet->setCellValue('T' . $row, $archive->deletion_ip);
            $sheet->setCellValue('U' . $row, $archive->propertyUnit->property->property_name ?? 'N/A');
            $sheet->setCellValue('V' . $row, $archive->propertyUnit->unit_number ?? 'N/A');
            $sheet->setCellValue('W' . $row, $archive->grace_period_days);
            $sheet->setCellValue('X' . $row, $archive->calculation_method);
            $sheet->setCellValue('Y' . $row, $archive->original_created_at ? \Carbon\Carbon::parse($archive->original_created_at)->format('Y-m-d H:i:s') : '');
            $sheet->setCellValue('Z' . $row, $archive->original_created_by);
            $row++;
        }

        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $summarySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Summary');
        $spreadsheet->addSheet($summarySheet, 0);
        $summarySheet->setCellValue('A1', 'Export Summary');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summarySheet->setCellValue('A3', 'Export Date:');
        $summarySheet->setCellValue('B3', now()->format('Y-m-d H:i:s'));
        $summarySheet->setCellValue('A4', 'Total Records:');
        $summarySheet->setCellValue('B4', $archives->count());
        $summarySheet->setCellValue('A5', 'Total Amount:');
        $summarySheet->setCellValue('B5', $settings->formatAmount($archives->sum('total_amount')));
        $summarySheet->setCellValue('A6', 'Total Penalties:');
        $summarySheet->setCellValue('B6', $settings->formatAmount($archives->sum('penalty_amount')));
        $summarySheet->setCellValue('A7', 'Exported By:');
        $summarySheet->setCellValue('B7', auth()->user()->name);

        $spreadsheet->setActiveSheetIndex(1);

        $filename = 'archive_export_' . date('Y-m-d_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function getArchiveDetails($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $archive = TenantInvoiceArchive::with(['propertyUnit.property'])->findOrFail($id);

        $settings = SystemSetting::getSettings();

        return response()->json([
            'success' => true,
            'archive' => [
                'id'                 => $archive->id,
                'invoice_number'     => $archive->invoice_number,
                'tenant_id'          => $archive->tenant_id,
                'tenant_name'        => $archive->tenant_name,
                'period'             => $archive->period,
                'month_name'         => $archive->month_name,
                'due_date'           => $archive->due_date->format('Y-m-d'),
                'community_dues'     => $archive->community_dues,
                'additional_charges' => $archive->additional_charges,
                'total_amount'       => $archive->total_amount,
                'paid_amount'        => $archive->paid_amount,
                'balance'            => $archive->balance,
                'penalty_amount'     => $archive->penalty_amount,
                'status'             => $archive->status,
                'status_color'       => $archive->status,
                'property_name'      => $archive->propertyUnit->property->property_name ?? 'N/A',
                'unit_number'        => $archive->propertyUnit->unit_number ?? 'N/A',
                'deleted_at'         => $archive->deleted_at,
                'formatted_deleted_at' => $archive->formatted_deleted_at,
                'deleted_by_name'    => $archive->deleted_by_name,
                'deletion_reason'    => $archive->deletion_reason,
                'deletion_ip'        => $archive->deletion_ip,
                'formatted_original_created_at' => $archive->formatted_original_created_at,
                'calculation_details' => $archive->calculation_details,
                'metadata'            => $archive->metadata,
            ],
            'currency_symbol'   => $settings->currency_symbol,
            'currency_position' => $settings->currency_position,
        ]);
    }

    private function exportAsPDF($archives, $settings)
    {
        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            Log::error('Dompdf not installed. Please run: composer require barryvdh/laravel-dompdf');
            return redirect()->back()->with('error', 'PDF export is not available. Please contact administrator.');
        }

        $data = [
            'archives'        => $archives,
            'settings'        => $settings,
            'export_date'     => now()->format('Y-m-d H:i:s'),
            'total_records'   => $archives->count(),
            'total_amount'    => $archives->sum('total_amount'),
            'total_penalties' => $archives->sum('penalty_amount'),
            'exported_by'     => auth()->user()->name,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.archives-pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('archive_export_' . date('Y-m-d_His') . '.pdf');
    }

    /**
     * CLI-only cleanup. Not gated — scheduled maintenance of archives.
     */
    public function cleanupOldArchives(int $years = 7)
    {
        if (app()->runningInConsole() === false) {
            return ['success' => false, 'message' => 'CLI only'];
        }

        $cutoffDate = now()->subYears($years);

        $oldArchives = TenantInvoiceArchive::where('deleted_at', '<', $cutoffDate)->get();

        $count = 0;
        foreach ($oldArchives as $archive) {
            $archive->delete();
            $count++;
        }

        return [
            'success' => true,
            'message' => "Cleaned up {$count} archives older than {$years} years",
        ];
    }

    public function archiveCleanup()
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Only Super Admins can perform archive cleanup.');
        }

        $system_settings = SystemSetting::getSettings();

        $stats = [
            'total_archives'      => TenantInvoiceArchive::count(),
            'older_than_1_year'   => TenantInvoiceArchive::where('deleted_at', '<', now()->subYear())->count(),
            'older_than_2_years'  => TenantInvoiceArchive::where('deleted_at', '<', now()->subYears(2))->count(),
            'older_than_5_years'  => TenantInvoiceArchive::where('deleted_at', '<', now()->subYears(5))->count(),
            'older_than_7_years'  => TenantInvoiceArchive::where('deleted_at', '<', now()->subYears(7))->count(),
            'older_than_10_years' => TenantInvoiceArchive::where('deleted_at', '<', now()->subYears(10))->count(),
            'oldest_archive'      => TenantInvoiceArchive::orderBy('deleted_at', 'asc')->first(),
            'newest_archive'      => TenantInvoiceArchive::orderBy('deleted_at', 'desc')->first(),
            'total_amount'        => TenantInvoiceArchive::sum('total_amount'),
            'total_penalties'     => TenantInvoiceArchive::sum('penalty_amount'),
            'log_stats' => [
                'total_logs'          => \App\Models\ArchiveCleanupLog::count(),
                'older_than_1_year'   => \App\Models\ArchiveCleanupLog::where('created_at', '<', now()->subYear())->count(),
                'older_than_3_years'  => \App\Models\ArchiveCleanupLog::where('created_at', '<', now()->subYears(3))->count(),
                'older_than_5_years'  => \App\Models\ArchiveCleanupLog::where('created_at', '<', now()->subYears(5))->count(),
                'older_than_7_years'  => \App\Models\ArchiveCleanupLog::where('created_at', '<', now()->subYears(7))->count(),
                'older_than_10_years' => \App\Models\ArchiveCleanupLog::where('created_at', '<', now()->subYears(10))->count(),
                'oldest_log'          => \App\Models\ArchiveCleanupLog::orderBy('created_at', 'asc')->first(),
                'newest_log'          => \App\Models\ArchiveCleanupLog::orderBy('created_at', 'desc')->first(),
            ],
        ];

        return view('admin.tenant-invoices.archive-cleanup', compact('stats', 'system_settings'));
    }

    public function cleanupOldLogs(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('cleanup_archive_logs');

        $years = $request->get('years', 7);
        $cutoffDate = now()->subYears($years);

        $oldLogs = \App\Models\ArchiveCleanupLog::where('created_at', '<', $cutoffDate)->get();
        $count = $oldLogs->count();

        foreach ($oldLogs as $log) {
            $log->delete();
        }

        Log::info('Cleanup logs deleted', [
            'years'           => $years,
            'count'           => $count,
            'cutoff_date'     => $cutoffDate->format('Y-m-d'),
            'deleted_by'      => auth()->id(),
            'deleted_by_name' => auth()->user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$count} logs older than {$years} years.",
            'count'   => $count,
        ]);
    }

    public function deleteSelectedLogs(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only Super Admins can delete logs.',
            ], 403);
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('delete_archive_logs');

        $validator = Validator::make($request->all(), [
            'log_ids'   => 'required|array',
            'log_ids.*' => 'integer|exists:archive_cleanup_logs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid log IDs provided.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $logIds = $request->log_ids;
            $count  = count($logIds);

            $logs = \App\Models\ArchiveCleanupLog::whereIn('id', $logIds)->get();

            Log::warning('Selected cleanup logs deleted', [
                'log_ids'         => $logIds,
                'count'           => $count,
                'deleted_by'      => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'timestamp'       => now()->toDateTimeString(),
                'log_details'     => $logs->map(function ($log) {
                    return [
                        'id'              => $log->id,
                        'records_deleted' => $log->records_deleted,
                        'total_amount'    => $log->total_amount,
                        'created_at'      => $log->created_at,
                    ];
                }),
            ]);

            \App\Models\ArchiveCleanupLog::whereIn('id', $logIds)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$count} log record(s).",
                'count'   => $count,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete selected cleanup logs: ' . $e->getMessage(), [
                'log_ids' => $logIds ?? [],
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function previewLogCleanup(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $years = $request->get('years', 7);
        $cutoffDate = now()->subYears($years);

        $count = \App\Models\ArchiveCleanupLog::where('created_at', '<', $cutoffDate)->count();

        return response()->json([
            'success'     => true,
            'count'       => $count,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
        ]);
    }

    public function getAllRecordsForCleanup()
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $records = TenantInvoiceArchive::with(['propertyUnit.property'])
                ->orderBy('deleted_at', 'desc')
                ->get();

            $settings = SystemSetting::getSettings();

            $formattedRecords = $records->map(function ($record) use ($settings) {
                return [
                    'id'                   => $record->id,
                    'invoice_number'       => $record->invoice_number,
                    'tenant_name'          => $record->tenant_name,
                    'period'               => $record->period,
                    'month_name'           => $record->month_name,
                    'total_amount'         => $record->total_amount,
                    'formatted_amount'     => $settings->formatAmount($record->total_amount),
                    'deleted_at_formatted' => $record->deleted_at ? $record->deleted_at->format('M d, Y') : 'N/A',
                    'age_days'             => $record->deleted_at ? now()->diffInDays($record->deleted_at) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'records' => $formattedRecords,
                'total'   => $records->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get archive records: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load archive records: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportArchivesForCleanup(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $years       = $request->get('years', 7);
        $method      = $request->get('method', 'by_age');
        $selectedIds = $request->get('selected_ids', '');
        $format      = $request->get('format', 'csv');

        if ($method === 'by_selection' && !empty($selectedIds)) {
            $ids = explode(',', $selectedIds);
            $archives = TenantInvoiceArchive::whereIn('id', $ids)
                ->orderBy('deleted_at', 'asc')
                ->get();
        } else {
            $cutoffDate = now()->subYears($years);
            $archives = TenantInvoiceArchive::where('deleted_at', '<', $cutoffDate)
                ->orderBy('deleted_at', 'asc')
                ->get();
        }

        if ($archives->isEmpty()) {
            return redirect()->back()->with('error', 'No records found to export.');
        }

        $settings = SystemSetting::getSettings();

        $filename = "archive_cleanup_" . date('Y-m-d_His');

        switch ($format) {
            case 'json':
                return $this->exportCleanupAsJSON($archives, $filename);
            case 'csv':
            default:
                return $this->exportCleanupAsCSV($archives, $settings, $filename);
        }
    }

    private function exportCleanupAsCSV($archives, $settings, $filename)
    {
        $filename .= '.csv';
        $handle = fopen('php://temp', 'w+');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number',
            'Tenant Name',
            'Period',
            'Due Date',
            'Total Amount',
            'Status',
            'Deleted At',
            'Deleted By',
            'Deletion Reason',
            'Property',
            'Unit Number',
            'Days Since Deletion',
        ]);

        foreach ($archives as $archive) {
            fputcsv($handle, [
                $archive->invoice_number,
                $archive->tenant_name,
                $archive->month_name,
                $archive->due_date->format('Y-m-d'),
                $archive->total_amount,
                $archive->status,
                $archive->deleted_at->format('Y-m-d H:i:s'),
                $archive->deleted_by_name,
                $archive->deletion_reason,
                $archive->propertyUnit->property->property_name ?? 'N/A',
                $archive->propertyUnit->unit_number ?? 'N/A',
                now()->diffInDays($archive->deleted_at),
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

    private function exportCleanupAsJSON($archives, $filename)
    {
        $filename .= '.json';

        $data = [
            'export_info' => [
                'export_date'   => now()->format('Y-m-d H:i:s'),
                'exported_by'   => auth()->user()->name,
                'total_records' => $archives->count(),
                'total_amount'  => $archives->sum('total_amount'),
            ],
            'archives' => $archives->map(function ($archive) {
                $settings = SystemSetting::getSettings();
                return [
                    'id'              => $archive->id,
                    'invoice_number'  => $archive->invoice_number,
                    'tenant_name'     => $archive->tenant_name,
                    'period'          => $archive->period,
                    'month_name'      => $archive->month_name,
                    'due_date'        => $archive->due_date->format('Y-m-d'),
                    'total_amount'    => $archive->total_amount,
                    'formatted_amount' => $settings->formatAmount($archive->total_amount),
                    'status'          => $archive->status,
                    'deleted_at'      => $archive->deleted_at->format('Y-m-d H:i:s'),
                    'deleted_by'      => $archive->deleted_by_name,
                    'deletion_reason' => $archive->deletion_reason,
                    'property_name'   => $archive->propertyUnit->property->property_name ?? 'N/A',
                    'unit_number'     => $archive->propertyUnit->unit_number ?? 'N/A',
                ];
            }),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function performArchiveCleanup(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('perform_tenant_archive_cleanup');

        $years       = $request->get('years', 7);
        $method      = $request->get('method', 'by_age');
        $selectedIds = $request->get('selected_ids', '');
        $confirm     = $request->get('confirm', false);
        $exported    = $request->get('exported', false);

        if (!$confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Please confirm deletion by checking the confirmation box.',
            ]);
        }

        if (!$exported) {
            return response()->json([
                'success' => false,
                'message' => 'Please export the data first before deleting.',
            ]);
        }

        try {
            DB::beginTransaction();

            if ($method === 'by_selection' && !empty($selectedIds)) {
                $ids = explode(',', $selectedIds);
                $archives = TenantInvoiceArchive::whereIn('id', $ids)->get();
            } else {
                $cutoffDate = now()->subYears($years);
                $archives = TenantInvoiceArchive::where('deleted_at', '<', $cutoffDate)->get();
            }

            $count = $archives->count();
            $totalAmount = $archives->sum('total_amount');

            Log::warning('Archive cleanup performed', [
                'method'          => $method,
                'years'           => $years,
                'selected_ids'    => $selectedIds,
                'records_deleted' => $count,
                'total_amount'    => $totalAmount,
                'deleted_by'      => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'ip_address'      => $request->ip(),
                'timestamp'       => now()->toDateTimeString(),
            ]);

            \App\Models\ArchiveCleanupLog::create([
                'performed_by'      => auth()->id(),
                'performed_by_name' => auth()->user()->name,
                'method'            => $method,
                'years_old'         => $method === 'by_age' ? $years : null,
                'selected_count'    => $method === 'by_selection' ? $count : null,
                'cutoff_date'       => $method === 'by_age' ? now()->subYears($years) : null,
                'records_deleted'   => $count,
                'total_amount'      => $totalAmount,
                'ip_address'        => $request->ip(),
                'user_agent'        => $request->userAgent(),
            ]);

            foreach ($archives as $archive) {
                $archive->delete();
            }

            DB::commit();

            return response()->json([
                'success'          => true,
                'message'          => "Successfully deleted {$count} archive records.",
                'records_deleted'  => $count,
                'total_amount'     => $totalAmount,
                'formatted_amount' => SystemSetting::getSettings()->formatAmount($totalAmount),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Archive cleanup failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function previewCleanup(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $years = $request->get('years', 7);
        $cutoffDate = now()->subYears($years);

        $count       = TenantInvoiceArchive::where('deleted_at', '<', $cutoffDate)->count();
        $totalAmount = TenantInvoiceArchive::where('deleted_at', '<', $cutoffDate)->sum('total_amount');
        $settings    = SystemSetting::getSettings();

        return response()->json([
            'success'          => true,
            'count'            => $count,
            'total_amount'     => $totalAmount,
            'formatted_amount' => $settings->formatAmount($totalAmount),
            'cutoff_date'      => $cutoffDate->format('Y-m-d'),
        ]);
    }

    public function getCleanupLogs()
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $logs = \App\Models\ArchiveCleanupLog::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                $settings = SystemSetting::getSettings();
                $log->formatted_amount = $settings->formatAmount($log->total_amount);
                return $log;
            });

        return response()->json($logs);
    }

    /* ============================================================
     | PRINT / DOWNLOAD — read-only
     * ============================================================ */

    public function print(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $tenantInvoice->load(['tenant', 'propertyUnit.property', 'creator']);

        $settings = SystemSetting::getSettings();

        $coverageInfo = [];
        if (isset($tenantInvoice->is_bulk_payment) && $tenantInvoice->is_bulk_payment && $tenantInvoice->isPaid()) {
            $coverageInfo = [
                'is_active'       => true,
                'coverage_months' => $tenantInvoice->covers_periods ?? [],
            ];
        }

        return view('admin.tenant-invoices.print', compact(
            'tenantInvoice',
            'settings',
            'coverageInfo'
        ))->with([
            'system_settings' => $settings,
            'invoice'         => $tenantInvoice,
        ]);
    }

    public function download(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            $tenantInvoice->load(['tenant', 'propertyUnit.property']);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.pdf', compact('tenantInvoice'));

            return $pdf->download("invoice-{$tenantInvoice->invoice_number}.pdf");

        } catch (\Exception $e) {
            Log::error("Failed to download invoice: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download invoice.');
        }
    }

    public function printTenantInvoice(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isTenant() || auth()->id() !== $tenantInvoice->tenant_id) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $tenantInvoice->load(['propertyUnit.property']);

        $system_settings = SystemSetting::getSettings();
        $paymentInstructions = $this->getPaymentInstructions();

        $invoice  = $tenantInvoice;
        $settings = $system_settings;

        return view('tenant.invoices.print', compact(
            'invoice',
            'tenantInvoice',
            'system_settings',
            'settings',
            'paymentInstructions'
        ));
    }

    public function downloadTenantInvoice(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isTenant() || auth()->id() !== $tenantInvoice->tenant_id) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            $tenantInvoice->load(['propertyUnit.property']);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.invoices.pdf', compact('tenantInvoice'));

            return $pdf->download("invoice-{$tenantInvoice->invoice_number}.pdf");

        } catch (\Exception $e) {
            Log::error("Failed to download tenant invoice: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download invoice.');
        }
    }

    /* ============================================================
     | EXPORTS (INVOICES) — read-only
     * ============================================================ */

    public function exportUnpaidInvoices(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = TenantInvoice::where('status', '!=', TenantInvoice::STATUS_PAID)
            ->where('status', '!=', TenantInvoice::STATUS_CANCELLED)
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['tenant', 'propertyUnit.property']);

        if ($request->filled('year')) {
            $query->where(function ($q) use ($request) {
                $q->whereYear('created_at', $request->year)
                  ->orWhere('period', 'like', $request->year . '-%');
            });
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        $filename = 'unpaid_invoices_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number',
            'Tenant Name',
            'Tenant Email',
            'Period',
            'Due Date',
            'Total Amount',
            'Balance',
            'Status',
            'Original Year',
            'Property',
            'Unit Number',
        ]);

        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->invoice_number,
                $invoice->tenant->name ?? 'Unknown',
                $invoice->tenant->email ?? '',
                $invoice->month_name,
                $invoice->due_date->format('Y-m-d'),
                $invoice->total_amount,
                $invoice->balance,
                $invoice->status,
                $invoice->original_year ?? $invoice->created_at->year,
                $invoice->propertyUnit->property->property_name ?? 'N/A',
                $invoice->propertyUnit->unit_number ?? 'N/A',
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

        $ids = explode(',', $request->get('ids', ''));

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No invoices selected.');
        }

        $invoices = TenantInvoice::whereIn('id', $ids)
            ->with(['tenant', 'propertyUnit.property'])
            ->get();

        $filename = 'selected_invoices_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Invoice Number',
            'Tenant Name',
            'Period',
            'Due Date',
            'Total Amount',
            'Balance',
            'Status',
            'Property',
            'Unit Number',
        ]);

        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->invoice_number,
                $invoice->tenant->name ?? 'Unknown',
                $invoice->month_name,
                $invoice->due_date->format('Y-m-d'),
                $invoice->total_amount,
                $invoice->balance,
                $invoice->status,
                $invoice->propertyUnit->property->property_name ?? 'N/A',
                $invoice->propertyUnit->unit_number ?? 'N/A',
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
     | PDF EXPORTS — read-only
     * ============================================================ */

    public function exportInvoicePdf(TenantInvoice $tenantInvoice)
    {
        try {
            Log::info('PDF Export Started', [
                'invoice_id'     => $tenantInvoice->id,
                'invoice_number' => $tenantInvoice->invoice_number,
                'user_id'        => auth()->id(),
                'user_type'      => auth()->user()->type,
                'tenant_id'      => $tenantInvoice->tenant_id,
            ]);

            if (!auth()->user()->isTenant()) {
                Log::warning('PDF Export Failed - User not tenant', [
                    'user_id'   => auth()->id(),
                    'user_type' => auth()->user()->type,
                ]);
                return redirect()->back()->with('error', 'Only tenants can export invoices.');
            }

            if (auth()->id() !== $tenantInvoice->tenant_id) {
                Log::warning('PDF Export Failed - Unauthorized access', [
                    'user_id'           => auth()->id(),
                    'invoice_tenant_id' => $tenantInvoice->tenant_id,
                ]);
                return redirect()->back()->with('error', 'You can only export your own invoices.');
            }

            $tenantInvoice->load(['tenant', 'propertyUnit.property']);

            $settings = SystemSetting::getSettings();
            $paymentInstructions = $this->getPaymentInstructions();

            $companyInfo = [
                'name'    => $settings->system_name ?? 'Property Management System',
                'email'   => $settings->system_email ?? '',
                'phone'   => $settings->system_phone ?? '',
                'address' => $settings->system_address ?? '',
                'logo'    => $settings->system_logo ?? null,
            ];

            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                Log::error('DomPDF not installed');
                return redirect()->back()->with('error', 'PDF generation is not available. Please contact administrator.');
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.invoices.pdf-export', [
                'invoice'                         => $tenantInvoice,
                'settings'                        => $settings,
                'companyInfo'                     => $companyInfo,
                'paymentInstructions'             => $paymentInstructions,
                'tenant_late_payment_percentage'  => $settings->tenant_late_payment_percentage ?? 5,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = "invoice-{$tenantInvoice->invoice_number}.pdf";

            Log::info('PDF Generated Successfully', [
                'invoice_id' => $tenantInvoice->id,
                'filename'   => $filename,
            ]);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF Export Failed', [
                'invoice_id' => $tenantInvoice->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function bulkExportInvoicesPdf(Request $request)
    {
        try {
            Log::info('Bulk PDF Export Started', [
                'user_id'      => auth()->id(),
                'request_data' => $request->all(),
            ]);

            if (!auth()->user()->isTenant()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $invoiceIds = $request->input('invoice_ids');

            if (is_string($invoiceIds)) {
                $invoiceIds = json_decode($invoiceIds, true);
            }

            if (empty($invoiceIds) || !is_array($invoiceIds)) {
                return redirect()->back()->with('error', 'Please select at least one invoice to export.');
            }

            $validator = Validator::make(['invoice_ids' => $invoiceIds], [
                'invoice_ids'   => 'required|array',
                'invoice_ids.*' => 'integer|exists:tenant_invoices,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid invoice selection.');
            }

            $invoices = TenantInvoice::whereIn('id', $invoiceIds)
                ->where('tenant_id', auth()->id())
                ->with(['tenant', 'propertyUnit.property'])
                ->get();

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No valid invoices selected.');
            }

            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                throw new \Exception('DomPDF not installed');
            }

            if (!class_exists('\ZipArchive')) {
                throw new \Exception('ZipArchive not available on this server');
            }

            $settings = SystemSetting::getSettings();
            $paymentInstructions = $this->getPaymentInstructions();

            $zip = new \ZipArchive();
            $zipFileName = 'invoices_' . date('Y-m-d_His') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                throw new \Exception('Could not create zip file');
            }

            foreach ($invoices as $index => $invoice) {
                try {
                    $companyInfo = [
                        'name'    => $settings->system_name ?? 'Property Management System',
                        'email'   => $settings->system_email ?? '',
                        'phone'   => $settings->system_phone ?? '',
                        'address' => $settings->system_address ?? '',
                    ];

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.invoices.pdf-export', [
                        'invoice'                         => $invoice,
                        'settings'                        => $settings,
                        'companyInfo'                     => $companyInfo,
                        'paymentInstructions'             => $paymentInstructions,
                        'tenant_late_payment_percentage'  => $settings->tenant_late_payment_percentage ?? 5,
                    ]);

                    $pdfContent = $pdf->output();
                    $filename = "invoice-{$invoice->invoice_number}.pdf";
                    $zip->addFromString($filename, $pdfContent);

                } catch (\Exception $e) {
                    Log::error('Failed to add invoice to zip', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $zip->close();

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Bulk PDF Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDFs: ' . $e->getMessage());
        }
    }

    public function exportAdminInvoicePdf(TenantInvoice $tenantInvoice)
    {
        try {
            Log::info('Admin PDF Export Started', [
                'invoice_id'     => $tenantInvoice->id,
                'invoice_number' => $tenantInvoice->invoice_number,
                'user_id'        => auth()->id(),
                'user_name'      => auth()->user()->name,
                'user_type'      => auth()->user()->type,
            ]);

            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                Log::warning('Admin PDF Export Failed - Unauthorized', [
                    'user_id'   => auth()->id(),
                    'user_type' => auth()->user()->type,
                ]);
                return redirect()->back()->with('error', 'Unauthorized access. Only administrators can export invoices.');
            }

            $tenantInvoice->load(['tenant', 'propertyUnit.property', 'creator', 'updater']);

            $settings = SystemSetting::getSettings();

            $penaltyInfo    = $this->calculatePenaltyInfo($tenantInvoice);
            $paymentDetails = $this->calculatePaymentDetails($tenantInvoice);

            $companyInfo = [
                'name'                => $settings->system_name ?? 'Property Management System',
                'email'               => $settings->system_email ?? '',
                'phone'               => $settings->system_phone ?? '',
                'address'             => $settings->system_address ?? '',
                'logo'                => $settings->system_logo ?? null,
                'website'             => $settings->system_website ?? '',
                'tax_id'              => $settings->tax_id ?? '',
                'registration_number' => $settings->registration_number ?? '',
            ];

            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                throw new \Exception('DomPDF is not installed. Please run: composer require barryvdh/laravel-dompdf');
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.pdf-export', [
                'invoice'         => $tenantInvoice,
                'settings'        => $settings,
                'companyInfo'     => $companyInfo,
                'penaltyInfo'     => $penaltyInfo,
                'paymentDetails'  => $paymentDetails,
                'generated_at'    => now()->format('Y-m-d H:i:s'),
                'generated_by'    => auth()->user()->name,
                'is_admin_export' => true,
            ]);

            $pdf->setPaper('A4', 'portrait');
            $pdf->setOption('enable_php', true);
            $pdf->setOption('enable_javascript', true);
            $pdf->setOption('enable_html5_parser', true);
            $pdf->setOption('default_font', 'sans-serif');

            $filename = "invoice-{$tenantInvoice->invoice_number}-" . date('Y-m-d') . ".pdf";

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Admin PDF Export Failed', [
                'invoice_id' => $tenantInvoice->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function bulkExportAdminPdf(Request $request)
    {
        try {
            Log::info('Admin Bulk PDF Export Started', [
                'user_id'      => auth()->id(),
                'user_name'    => auth()->user()->name,
                'request_data' => $request->all(),
            ]);

            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                Log::warning('Admin Bulk PDF Export Failed - Unauthorized', [
                    'user_id'   => auth()->id(),
                    'user_type' => auth()->user()->type,
                ]);
                return redirect()->back()->with('error', 'Unauthorized access. Only administrators can perform bulk exports.');
            }

            $validator = Validator::make($request->all(), [
                'invoice_ids' => 'required|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid request: ' . $validator->errors()->first());
            }

            $invoiceIds = explode(',', $request->invoice_ids);

            if (empty($invoiceIds)) {
                return redirect()->back()->with('error', 'No invoices selected for export.');
            }

            $invoices = TenantInvoice::whereIn('id', $invoiceIds)
                ->with(['tenant', 'propertyUnit.property'])
                ->orderBy('period', 'desc')
                ->get();

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No valid invoices found.');
            }

            return $this->exportAdminAsPdfZip($invoices);

        } catch (\Exception $e) {
            Log::error('Admin Bulk Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to export invoices: ' . $e->getMessage());
        }
    }

    private function exportAdminAsPdfZip($invoices)
    {
        try {
            if (!class_exists('ZipArchive')) {
                throw new \Exception('ZipArchive is not available on this server. Please enable the PHP zip extension.');
            }

            $settings = SystemSetting::getSettings();
            $companyInfo = [
                'name'                => $settings->system_name ?? 'Property Management System',
                'email'               => $settings->system_email ?? '',
                'phone'               => $settings->system_phone ?? '',
                'address'             => $settings->system_address ?? '',
                'logo'                => $settings->system_logo ?? null,
                'website'             => $settings->system_website ?? '',
                'tax_id'              => $settings->tax_id ?? '',
                'registration_number' => $settings->registration_number ?? '',
            ];

            $tempDir = storage_path('app/temp/exports');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFileName = 'invoices_export_' . date('Y-m-d_His') . '.zip';
            $zipPath = $tempDir . '/' . $zipFileName;

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Could not create zip file. Check directory permissions.');
            }

            $successCount = 0;
            $failedInvoices = [];

            foreach ($invoices as $invoice) {
                try {
                    if (!$invoice->relationLoaded('tenant')) {
                        $invoice->load(['tenant', 'propertyUnit.property']);
                    }

                    $penaltyInfo    = $this->calculatePenaltyInfo($invoice);
                    $paymentDetails = $this->calculatePaymentDetails($invoice);

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.pdf-export', [
                        'invoice'         => $invoice,
                        'settings'        => $settings,
                        'companyInfo'     => $companyInfo,
                        'penaltyInfo'     => $penaltyInfo,
                        'paymentDetails'  => $paymentDetails,
                        'generated_at'    => now()->format('Y-m-d H:i:s'),
                        'generated_by'    => auth()->user()->name,
                        'is_admin_export' => true,
                        'is_bulk_export'  => true,
                    ]);

                    $pdf->setPaper('A4', 'portrait');
                    $pdf->setOption('enable_php', true);
                    $pdf->setOption('enable_javascript', true);
                    $pdf->setOption('enable_html5_parser', true);

                    $pdfContent = $pdf->output();

                    $safeTenantName = preg_replace('/[^a-zA-Z0-9]/', '_', $invoice->tenant->name ?? 'Unknown');
                    $filename = "invoice-{$invoice->invoice_number}-{$safeTenantName}.pdf";

                    $zip->addFromString($filename, $pdfContent);
                    $successCount++;

                } catch (\Exception $e) {
                    $failedInvoices[] = [
                        'id'     => $invoice->id,
                        'number' => $invoice->invoice_number,
                        'error'  => $e->getMessage(),
                    ];

                    Log::error('Failed to add invoice to zip', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $zip->close();

            Log::info('Admin Bulk PDF Export Completed', [
                'zip_file'       => $zipPath,
                'size'           => filesize($zipPath),
                'success_count'  => $successCount,
                'failed_count'   => count($failedInvoices),
                'failed_invoices' => $failedInvoices,
            ]);

            if ($successCount === 0) {
                unlink($zipPath);
                return redirect()->back()->with('error', 'Failed to generate any PDFs. Please try again.');
            }

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('PDF Zip Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDFs: ' . $e->getMessage());
        }
    }

    public function exportCurrentPagePdf(Request $request)
    {
        try {
            Log::info('Admin Export Current Page Started', [
                'user_id'   => auth()->id(),
                'user_name' => auth()->user()->name,
                'filters'   => $request->all(),
            ]);

            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $query = TenantInvoice::with(['tenant', 'propertyUnit.property']);

            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }

            if ($request->filled('property_unit_id')) {
                $query->where('property_unit_id', $request->property_unit_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('period')) {
                $query->where('period', $request->period);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage  = $request->get('per_page', 15);
            $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No invoices found on this page to export.');
            }

            $settings = SystemSetting::getSettings();
            $companyInfo = [
                'name'    => $settings->system_name ?? 'Property Management System',
                'email'   => $settings->system_email ?? '',
                'phone'   => $settings->system_phone ?? '',
                'address' => $settings->system_address ?? '',
                'logo'    => $settings->system_logo ?? null,
            ];

            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                throw new \Exception('DomPDF is not installed');
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.pdf-export-list', [
                'invoices'       => $invoices,
                'settings'       => $settings,
                'companyInfo'    => $companyInfo,
                'filters'        => $request->all(),
                'export_date'    => now()->format('Y-m-d H:i:s'),
                'exported_by'    => auth()->user()->name,
                'page_number'    => $invoices->currentPage(),
                'total_pages'    => $invoices->lastPage(),
                'total_invoices' => $invoices->total(),
            ]);

            $pdf->setPaper('A4', 'landscape');
            $pdf->setOption('enable_php', true);

            $filename = 'invoices_page_' . $invoices->currentPage() . '_' . date('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Admin Export Current Page Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to export current page: ' . $e->getMessage());
        }
    }

    public function exportAllFilteredPdf(Request $request)
    {
        try {
            Log::info('Admin Export All Filtered Started', [
                'user_id'   => auth()->id(),
                'user_name' => auth()->user()->name,
                'filters'   => $request->all(),
            ]);

            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $query = TenantInvoice::with(['tenant', 'propertyUnit.property']);

            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }

            if ($request->filled('property_unit_id')) {
                $query->where('property_unit_id', $request->property_unit_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('period')) {
                $query->where('period', $request->period);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $invoices = $query->orderBy('created_at', 'desc')->get();

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No invoices found to export with the current filters.');
            }

            $settings = SystemSetting::getSettings();
            $companyInfo = [
                'name'    => $settings->system_name ?? 'Property Management System',
                'email'   => $settings->system_email ?? '',
                'phone'   => $settings->system_phone ?? '',
                'address' => $settings->system_address ?? '',
                'logo'    => $settings->system_logo ?? null,
            ];

            $summary = [
                'total_invoices'    => $invoices->count(),
                'total_amount'      => $invoices->sum('total_amount'),
                'total_paid'        => $invoices->sum('paid_amount'),
                'total_balance'     => $invoices->sum('balance'),
                'total_penalties'   => $invoices->sum('penalty_amount'),
                'paid_invoices'     => $invoices->where('status', 'paid')->count(),
                'pending_invoices'  => $invoices->where('status', 'pending')->count(),
                'overdue_invoices'  => $invoices->where('status', 'overdue')->count(),
            ];

            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                throw new \Exception('DomPDF is not installed');
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.tenant-invoices.pdf-export-all', [
                'invoices'    => $invoices,
                'settings'    => $settings,
                'companyInfo' => $companyInfo,
                'summary'     => $summary,
                'filters'     => $request->all(),
                'export_date' => now()->format('Y-m-d H:i:s'),
                'exported_by' => auth()->user()->name,
            ]);

            $pdf->setPaper('A4', 'landscape');
            $pdf->setOption('enable_php', true);

            $filename = 'all_invoices_' . date('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Admin Export All Filtered Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to export invoices: ' . $e->getMessage());
        }
    }

    public function bulkPrintAdmin(Request $request)
    {
        try {
            Log::info('Admin Bulk Print Started', [
                'user_id'   => auth()->id(),
                'user_name' => auth()->user()->name,
            ]);

            if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
                Log::warning('Admin Bulk Print Failed - Unauthorized', [
                    'user_id'   => auth()->id(),
                    'user_type' => auth()->user()->type,
                ]);
                return redirect()->back()->with('error', 'Unauthorized access. Only administrators can perform bulk printing.');
            }

            $ids = explode(',', $request->get('ids', ''));

            if (empty($ids)) {
                return redirect()->back()->with('error', 'No invoices selected for printing.');
            }

            $invoices = TenantInvoice::whereIn('id', $ids)
                ->with(['tenant', 'propertyUnit.property'])
                ->orderBy('period', 'desc')
                ->get();

            if ($invoices->isEmpty()) {
                return redirect()->back()->with('error', 'No valid invoices found.');
            }

            $settings = SystemSetting::getSettings();
            $companyInfo = [
                'name'    => $settings->system_name ?? 'Property Management System',
                'email'   => $settings->system_email ?? '',
                'phone'   => $settings->system_phone ?? '',
                'address' => $settings->system_address ?? '',
                'logo'    => $settings->system_logo ?? null,
            ];

            return view('admin.tenant-invoices.bulk-print', [
                'invoices'    => $invoices,
                'settings'    => $settings,
                'companyInfo' => $companyInfo,
                'print_date'  => now()->format('Y-m-d H:i:s'),
                'printed_by'  => auth()->user()->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Bulk Print Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to prepare invoices for printing: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | YEAR-END ARCHIVE — read + write split
     * ============================================================ */

    public function yearEndManagement(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $archiveService = new YearEndArchiveService($this->settings, $this->notificationService);

        $stats = $archiveService->getYearEndStatistics();

        $stats = array_merge([
            'total_invoices'         => 0,
            'paid_invoices'          => 0,
            'unpaid_invoices'        => 0,
            'total_amount'           => 0,
            'paid_amount'            => 0,
            'unpaid_amount'          => 0,
            'years_processed'        => [],
            'current_year'           => now()->year,
            'archived_count'         => 0,
            'pending_archive_count'  => 0,
            'retention_months'       => $this->settings->paid_invoice_retention_months ?? 3,
            'auto_archive_enabled'   => $this->settings->auto_archive_paid_after_retention ?? false,
            'last_archive_run'       => null,
            'next_archive_due'       => null,
        ], $stats);

        $unpaidInvoices = TenantInvoice::where('status', '!=', TenantInvoice::STATUS_PAID)
            ->where('status', '!=', TenantInvoice::STATUS_CANCELLED)
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['tenant', 'propertyUnit.property'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $archiveLogs = [];
        if (class_exists(\App\Models\TenantInvoiceArchive::class)) {
            try {
                $archiveLogs = \App\Models\TenantInvoiceArchive::orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Could not fetch archive logs: ' . $e->getMessage());
            }
        }

        return view('admin.tenant-invoices.year-end-management', compact('stats', 'unpaidInvoices', 'archiveLogs'))
            ->with([
                'system_settings'        => $this->settings,
                'settings'               => $this->settings,
                'total_invoices'         => $stats['total_invoices'],
                'paid_invoices'          => $stats['paid_invoices'],
                'unpaid_invoices_count'  => $stats['unpaid_invoices'],
                'total_amount'           => $stats['total_amount'],
                'total_paid_amount'      => $stats['paid_amount'],
                'total_unpaid_amount'    => $stats['unpaid_amount'],
            ]);
    }

    public function getYearEndStatistics(Request $request, $year = null)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $year = $year ?? $request->input('year', now()->subYear()->year);

        $archiveService = new YearEndArchiveService($this->settings, app(NotificationService::class));
        $stats = $archiveService->getYearEndStatistics($year);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($stats);
        }

        return view('admin.tenant-invoices.year-end-stats', compact('stats'));
    }

    public function processYearEndArchive(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ✅ BILLING: block admin write when system billing is overdue
        $this->assertBillingAllowsWrite('process_tenant_year_end_archive');

        $year = $request->input('year', now()->subYear()->year);

        $archiveService = new YearEndArchiveService($this->settings, app(NotificationService::class));
        $results = $archiveService->processYearEndArchive($year);

        if ($results['success'] ?? true) {
            $message = sprintf(
                "Year-end archiving for %d completed. Archived: %d paid invoices. Kept: %d unpaid invoices.",
                $year,
                $results['paid_archived'],
                $results['unpaid_kept']
            );

            if ($results['errors'] > 0) {
                $message .= " Errors: {$results['errors']}";
            }

            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()->with('error', $results['message'] ?? 'Year-end archiving failed.');
    }

    public function sendYearEndReminders(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // ── Communication action, not gated.

        $archiveService = new YearEndArchiveService($this->settings, app(NotificationService::class));
        $results = $archiveService->sendYearEndReminders();

        return redirect()->back()->with(
            'success',
            "Sent {$results['reminders_sent']} reminders ({$results['paid_reminders']} for paid, {$results['unpaid_reminders']} for unpaid)"
        );
    }

    public function unpaidFromPreviousYears(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = TenantInvoice::where('status', '!=', TenantInvoice::STATUS_PAID)
            ->where('status', '!=', TenantInvoice::STATUS_CANCELLED)
            ->where(function ($q) {
                $q->whereYear('created_at', '<', now()->year)
                  ->orWhere('period', '<', now()->year . '-01');
            })
            ->whereNull('deleted_at')
            ->with(['tenant', 'propertyUnit.property']);

        if ($request->filled('year')) {
            $query->where(function ($q) use ($request) {
                $q->whereYear('created_at', $request->year)
                  ->orWhere('period', 'like', $request->year . '-%');
            });
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        $totalOutstanding = $query->sum('balance');
        $uniqueTenants    = $query->distinct('tenant_id')->count('tenant_id');
        $oldestYear       = $query->min('created_at');
        $oldestYear       = $oldestYear ? Carbon::parse($oldestYear)->year : null;

        $availableYears = TenantInvoice::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $tenants = User::where('type', User::TYPE_TENANT)
            ->whereHas('invoices', function ($q) {
                $q->where('status', '!=', TenantInvoice::STATUS_PAID)
                  ->where('status', '!=', TenantInvoice::STATUS_CANCELLED)
                  ->where(function ($sub) {
                      $sub->whereYear('created_at', '<', now()->year)
                        ->orWhere('period', '<', now()->year . '-01');
                  });
            })
            ->orderBy('name')
            ->get();

        $retentionMonths = $this->settings->paid_invoice_retention_months ?? 3;

        return view('admin.tenant-invoices.unpaid-previous-years', compact(
            'invoices',
            'totalOutstanding',
            'uniqueTenants',
            'oldestYear',
            'availableYears',
            'tenants',
            'retentionMonths'
        ))->with([
            'system_settings' => $this->settings,
            'settings'        => $this->settings,
        ]);
    }

    public function processPostPaymentArchive(): array
    {
        if (!$this->settings->auto_archive_paid_after_retention) {
            return [
                'success' => false,
                'message' => 'Post-payment archiving is disabled',
            ];
        }

        $retentionMonths = $this->settings->paid_invoice_retention_months ?? 3;

        $cutoffDate = now()->subMonths($retentionMonths);

        $invoices = TenantInvoice::where('status', TenantInvoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->with(['tenant', 'propertyUnit.property'])
            ->get();

        $results = [
            'processed_at' => now()->toDateTimeString(),
            'total'        => $invoices->count(),
            'archived'     => 0,
            'errors'       => 0,
            'details'      => [],
        ];

        foreach ($invoices as $invoice) {
            try {
                DB::beginTransaction();

                $year = $invoice->year_end_archive_year ?? $invoice->created_at->year;

                $archive = TenantInvoiceArchive::create([
                    'original_invoice_id' => $invoice->id,
                    'invoice_number'      => $invoice->invoice_number,
                    'tenant_id'           => $invoice->tenant_id,
                    'tenant_name'         => $invoice->tenant->name ?? 'Unknown',
                    'property_unit_id'    => $invoice->property_unit_id,
                    'period'              => $invoice->period,
                    'month_name'          => $invoice->month_name,
                    'due_date'            => $invoice->due_date,
                    'community_dues'      => $invoice->community_dues,
                    'additional_charges'  => $invoice->additional_charges ?? 0,
                    'total_amount'        => $invoice->total_amount,
                    'paid_amount'         => $invoice->paid_amount,
                    'balance'             => $invoice->balance,
                    'status'              => $invoice->status,
                    'payment_method'      => $invoice->payment_method,
                    'payment_reference'   => $invoice->payment_reference,
                    'payment_date'        => $invoice->payment_date,
                    'penalty_amount'      => $invoice->penalty_amount ?? 0,
                    'grace_period_days'   => $invoice->grace_period_days,
                    'calculation_method'  => $invoice->calculation_method,
                    'calculation_details' => $invoice->calculation_details,
                    'metadata'            => $invoice->metadata,
                    'original_created_at' => $invoice->created_at,
                    'original_created_by' => $invoice->created_by,
                    'deleted_at'          => now(),
                    'deleted_by'          => null,
                    'deleted_by_name'     => 'System (Post-Payment Archive)',
                    'deletion_reason'     => "Post-payment archiving after {$retentionMonths} months retention",
                    'archive_type'        => 'post_payment',
                    'archive_approved_by_tenant' => $invoice->archive_approved_by_tenant ?? false,
                ]);

                $invoice->update([
                    'archive_type' => 'post_payment',
                    'metadata'     => array_merge($invoice->metadata ?? [], [
                        'post_payment_archived' => true,
                        'archive_id'            => $archive->id,
                        'archived_at'           => now()->toDateTimeString(),
                    ]),
                ]);

                $invoice->delete();

                $results['archived']++;
                $results['details'][] = [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant_name'    => $invoice->tenant->name ?? 'Unknown',
                    'amount'         => $invoice->total_amount,
                    'original_year'  => $year,
                    'payment_date'   => $invoice->payment_date->format('Y-m-d'),
                ];

                DB::commit();

                $this->sendPostArchiveNotification($invoice);

            } catch (\Exception $e) {
                DB::rollBack();
                $results['errors']++;
                Log::error('Post-payment archiving failed: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id,
                ]);
            }
        }

        return $results;
    }

    private function sendPostArchiveNotification(TenantInvoice $invoice): void
    {
        try {
            if (!$this->settings->send_tenant_payment_reminders) {
                return;
            }

            if (method_exists($this->notificationService, 'sendTenantInvoiceArchived')) {
                $this->notificationService->sendTenantInvoiceArchived($invoice);
                return;
            }

            Log::info('Post-archive notification skipped (no NotificationService method)', [
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Post-archive notification failed: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    public function previewPostPaymentArchive(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $retentionMonths = $this->settings->paid_invoice_retention_months ?? 3;
        $cutoffDate = now()->subMonths($retentionMonths);

        $invoices = TenantInvoice::where('status', TenantInvoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->with(['tenant', 'propertyUnit.property'])
            ->get();

        $totalAmount = $invoices->sum('total_amount');
        $settings = $this->settings;

        return response()->json([
            'success' => true,
            'invoices' => $invoices->map(function ($invoice) use ($settings) {
                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'tenant_name'      => $invoice->tenant->name ?? 'Unknown',
                    'original_year'    => $invoice->original_year,
                    'payment_date'     => $invoice->payment_date->format('Y-m-d'),
                    'amount'           => $invoice->total_amount,
                    'formatted_amount' => $settings->formatAmount($invoice->total_amount),
                ];
            }),
            'total'            => $invoices->count(),
            'total_amount'     => $totalAmount,
            'formatted_total'  => $settings->formatAmount($totalAmount),
            'retention_months' => $retentionMonths,
        ]);
    }

    public function getEligibleForYearEndArchive(Request $request, $year)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoices = TenantInvoice::paidAndEligibleForYearEndArchive($year)
            ->with(['tenant', 'propertyUnit.property'])
            ->get();

        return response()->json([
            'success'  => true,
            'count'    => $invoices->count(),
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'tenant_name'      => $invoice->tenant->name,
                    'period'           => $invoice->period,
                    'amount'           => $invoice->total_amount,
                    'formatted_amount' => $this->settings->formatAmount($invoice->total_amount),
                ];
            }),
        ]);
    }

    public function getPendingArchiveApproval(Request $request)
    {
        if (!auth()->user()->isTenant()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoices = TenantInvoice::where('tenant_id', auth()->id())
            ->where('archive_status', TenantInvoice::ARCHIVE_STATUS_PENDING)
            ->whereNull('archived_at')
            ->with(['propertyUnit.property'])
            ->get();

        return response()->json([
            'success'  => true,
            'count'    => $invoices->count(),
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id'               => $invoice->id,
                    'invoice_number'   => $invoice->invoice_number,
                    'period'           => $invoice->period,
                    'month_name'       => $invoice->month_name,
                    'total_amount'     => $invoice->total_amount,
                    'formatted_amount' => $this->settings->formatAmount($invoice->total_amount),
                    'payment_date'     => $invoice->payment_date?->format('Y-m-d'),
                    'property_name'    => $invoice->propertyUnit->property->property_name ?? 'N/A',
                    'unit_number'      => $invoice->propertyUnit->unit_number ?? 'N/A',
                ];
            }),
        ]);
    }

    public function getArchiveInfo(TenantInvoice $tenantInvoice)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin() && auth()->id() !== $tenantInvoice->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id'                         => $tenantInvoice->id,
                'invoice_number'             => $tenantInvoice->invoice_number,
                'is_archived'                => !is_null($tenantInvoice->deleted_at),
                'is_year_end_archived'       => $tenantInvoice->isYearEndArchived(),
                'year_end_archive_year'      => $tenantInvoice->year_end_archive_year,
                'original_year'              => $tenantInvoice->original_year,
                'archive_status'             => $tenantInvoice->archive_status,
                'archive_type'               => $tenantInvoice->archive_type,
                'archive_reason'             => $tenantInvoice->archive_reason,
                'archived_at'                => $tenantInvoice->archived_at?->format('Y-m-d H:i:s'),
                'archive_approved_by_tenant' => $tenantInvoice->archive_approved_by_tenant,
                'archive_approved_at'        => $tenantInvoice->archive_approved_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function pendingArchiveApproval()
    {
        if (!auth()->user()->isTenant()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
        }

        $invoices = TenantInvoice::where('tenant_id', auth()->id())
            ->where('archive_status', TenantInvoice::ARCHIVE_STATUS_PENDING)
            ->whereNull('archived_at')
            ->with(['propertyUnit.property'])
            ->get();

        return view('tenant.invoices.pending-archive', compact('invoices'));
    }

    public function processArchiveApproval(Request $request, TenantInvoice $tenantInvoice)
    {
        if (auth()->id() !== $tenantInvoice->tenant_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'approved' => 'required|boolean',
            'reason'   => 'required_if:approved,false|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $archiveService = new YearEndArchiveService($this->settings, app(NotificationService::class));

        $result = $archiveService->processTenantApproval(
            $tenantInvoice,
            $request->approved,
            $request->reason
        );

        if ($request->ajax()) {
            return response()->json($result);
        }

        if ($result['success']) {
            $message = $request->approved
                ? 'Invoice archived successfully.'
                : 'Archive request rejected. The invoice will remain active.';

            return redirect()->route('tenant.invoices.my-invoices')
                ->with('success', $message);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /* ============================================================
     | REAL-TIME STATUS (API) — read-only
     * ============================================================ */

    public function getTenantDetails($tenantId)
    {
        try {
            $tenant = User::where('type', User::TYPE_TENANT)
                ->with(['propertyUnits' => function ($query) {
                    $query->with('property')
                        ->where('tenant_status', 'approved');
                }])
                ->findOrFail($tenantId);

            $propertyUnit = $tenant->propertyUnits->first();

            $defaultAmount = $this->calculateTenantDuesForUnit($propertyUnit);

            return response()->json([
                'success' => true,
                'tenant'  => [
                    'id'    => $tenant->id,
                    'name'  => $tenant->name,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'property_unit' => $propertyUnit ? [
                        'id'                     => $propertyUnit->id,
                        'unit_number'            => $propertyUnit->unit_number,
                        'property_id'            => $propertyUnit->property->id,
                        'property_name'          => $propertyUnit->property->property_name,
                        'house_number'           => $propertyUnit->property->house_number,
                        'street_name'            => $propertyUnit->property->street_name,
                        'default_community_dues' => $defaultAmount,
                        'formatted_default_dues' => $this->settings->formatAmount($defaultAmount),
                    ] : null,
                ],
                'system_settings' => [
                    'enable_tenant_invoicing'    => $this->settings->enable_tenant_invoicing,
                    'tenant_monthly_dues_amount' => $this->settings->tenant_monthly_dues_amount,
                    'tenant_calculation_method'  => $this->settings->tenant_calculation_method,
                    'currency_symbol'            => $this->settings->currency_symbol,
                    'grace_period_days'          => $this->settings->tenant_grace_period_days,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tenant details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenant details.',
            ], 500);
        }
    }

    public function getInvoiceStatistics()
    {
        try {
            $statistics = $this->getEnhancedStatistics();

            return response()->json([
                'success'    => true,
                'statistics' => $statistics,
                'system_settings' => [
                    'enable_tenant_invoicing'        => $this->settings->enable_tenant_invoicing,
                    'tenant_monthly_dues_amount'     => $this->settings->tenant_monthly_dues_amount,
                    'tenant_calculation_method'      => $this->settings->tenant_calculation_method,
                    'auto_generate_tenant_invoices'  => $this->settings->auto_generate_tenant_invoices,
                    'send_tenant_payment_reminders'  => $this->settings->send_tenant_payment_reminders,
                    'tenant_grace_period_days'       => $this->settings->tenant_grace_period_days,
                    'tenant_late_payment_percentage' => $this->settings->tenant_late_payment_percentage,
                    'tenant_fixed_penalty_amount'    => $this->settings->tenant_fixed_penalty_amount,
                    'currency_symbol'                => $this->settings->currency_symbol,
                    'currency_code'                  => $this->settings->currency_code,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice statistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function canDeleteInvoice(TenantInvoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            return false;
        }

        if (($invoice->paid_amount ?? 0) > 0) {
            return false;
        }

        if ($invoice->created_at < now()->subYear()) {
            return false;
        }

        if ($invoice->period === now()->format('Y-m') &&
            isset($invoice->metadata['generation_method']) &&
            $invoice->metadata['generation_method'] === 'auto') {
            return false;
        }

        return true;
    }

    private function getDeletionErrorMessage(TenantInvoice $invoice): string
    {
        if ($invoice->isPaid()) {
            return '❌ Cannot delete a paid invoice. Mark it as unpaid first or issue a refund.';
        }

        if (($invoice->paid_amount ?? 0) > 0) {
            return '❌ Cannot delete an invoice with partial payments. Process refunds first.';
        }

        if ($invoice->created_at < now()->subYear()) {
            return '❌ Cannot delete invoices older than 1 year. These are archived for audit purposes.';
        }

        if ($invoice->period === now()->format('Y-m') &&
            isset($invoice->metadata['generation_method']) &&
            $invoice->metadata['generation_method'] === 'auto') {
            return '❌ Cannot delete current month\'s auto-generated invoice. Please use the "Regenerate" option instead.';
        }

        return '❌ Invoice cannot be deleted due to system constraints.';
    }

    private function createArchivalRecord(TenantInvoice $invoice, Request $request): TenantInvoiceArchive
    {
        return TenantInvoiceArchive::create([
            'original_invoice_id' => $invoice->id,
            'invoice_number'      => $invoice->invoice_number,
            'tenant_id'           => $invoice->tenant_id,
            'tenant_name'         => $invoice->tenant->name ?? 'Unknown',
            'property_unit_id'    => $invoice->property_unit_id,
            'period'              => $invoice->period,
            'due_date'            => $invoice->due_date,
            'community_dues'      => $invoice->community_dues,
            'additional_charges'  => $invoice->additional_charges ?? 0,
            'total_amount'        => $invoice->total_amount,
            'paid_amount'         => $invoice->paid_amount ?? 0,
            'balance'             => $invoice->balance,
            'status'              => $invoice->status,
            'payment_method'      => $invoice->payment_method,
            'payment_reference'   => $invoice->payment_reference,
            'payment_date'        => $invoice->payment_date,
            'penalty_amount'      => $invoice->penalty_amount ?? 0,
            'penalty_applied_at'  => $invoice->penalty_applied_at,
            'grace_period_days'   => $invoice->grace_period_days,
            'calculation_method'  => $invoice->calculation_method,
            'calculation_details' => $invoice->calculation_details,
            'description'         => $invoice->description,
            'notes'               => $invoice->notes,
            'metadata'            => $invoice->metadata,
            'original_created_at' => $invoice->created_at,
            'original_created_by' => $invoice->created_by,
            'deleted_at'          => now(),
            'deleted_by'          => auth()->id(),
            'deleted_by_name'     => auth()->user()->name,
            'deletion_reason'     => $request->input('reason', 'Manual deletion'),
            'deletion_ip'         => $request->ip(),
            'deletion_user_agent' => $request->userAgent(),
        ]);
    }

    private function getEnhancedStatistics(): array
    {
        $stats = $this->tenantInvoiceService->getTenantInvoiceStatistics();

        $stats['system_settings'] = [
            'enable_tenant_invoicing'       => $this->settings->enable_tenant_invoicing,
            'tenant_monthly_dues_amount'    => $this->settings->tenant_monthly_dues_amount,
            'auto_generate_tenant_invoices' => $this->settings->auto_generate_tenant_invoices,
            'grace_period_days'             => $this->settings->tenant_grace_period_days,
        ];

        $activeTenants = User::where('type', User::TYPE_TENANT)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('propertyUnits', function ($q) {
                $q->where('tenant_status', 'approved');
            })
            ->count();

        $stats['projected_next_month'] = $activeTenants * ($this->settings->tenant_monthly_dues_amount ?? 0);
        $stats['formatted_projected']  = $this->settings->formatAmount($stats['projected_next_month']);

        $graceDays = $this->settings->tenant_grace_period_days ?? 7;

        $stats['overdue_before_grace'] = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)
            ->where('due_date', '<', now())
            ->where('due_date', '>=', now()->subDays($graceDays))
            ->count();

        $stats['overdue_after_grace'] = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)
            ->where('due_date', '<', now()->subDays($graceDays))
            ->count();

        return $stats;
    }

    private function getTenantStatistics(int $tenantId): array
    {
        $totalInvoices = TenantInvoice::where('tenant_id', $tenantId)->count();

        $paidInvoices = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PAID)
            ->count();

        $pendingInvoices = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PENDING)
            ->count();

        $overdueInvoices = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_OVERDUE)
            ->count();

        $totalPaid = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PAID)
            ->sum('total_amount');

        $totalOutstanding = TenantInvoice::where('tenant_id', $tenantId)
            ->whereIn('status', [TenantInvoice::STATUS_PENDING, TenantInvoice::STATUS_OVERDUE])
            ->sum('balance');

        $paymentHistory = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PAID)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'period'           => Carbon::parse($invoice->period . '-01')->format('M Y'),
                    'amount'           => $invoice->total_amount,
                    'payment_date'     => $invoice->payment_date ? $invoice->payment_date->format('Y-m-d') : null,
                    'formatted_amount' => $this->settings->formatAmount($invoice->total_amount),
                ];
            });

        $nextDue = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PENDING)
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->first();

        return [
            'total_invoices'        => $totalInvoices,
            'paid_invoices'         => $paidInvoices,
            'pending_invoices'      => $pendingInvoices,
            'overdue_invoices'      => $overdueInvoices,
            'payment_rate'          => $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100) : 0,
            'total_paid'            => $totalPaid,
            'formatted_total_paid'  => $this->settings->formatAmount($totalPaid),
            'total_outstanding'     => $totalOutstanding,
            'formatted_outstanding' => $this->settings->formatAmount($totalOutstanding),
            'payment_history'       => $paymentHistory,
            'next_due'              => $nextDue ? [
                'period'           => Carbon::parse($nextDue->period . '-01')->format('M Y'),
                'amount'           => $nextDue->total_amount,
                'formatted_amount' => $this->settings->formatAmount($nextDue->total_amount),
                'due_date'         => $nextDue->due_date->format('Y-m-d'),
                'days_until_due'   => now()->startOfDay()->diffInDays($nextDue->due_date, false),
            ] : null,
        ];
    }

    private function calculateNextDueDate(): Carbon
    {
        $settings = $this->settings;

        $nextMonth = now()->addMonth()->startOfMonth();

        $dueDay = $settings->invoice_due_day ?? 5;

        return Carbon::create($nextMonth->year, $nextMonth->month, $dueDay);
    }

    private function calculateDefaultDueDate(): Carbon
    {
        $dueDate = $this->calculateNextDueDate();

        if ($dueDate->isPast()) {
            $dueDate->addMonth();
        }

        return $dueDate;
    }

    private function calculateGracePeriodEndDate(): ?Carbon
    {
        $graceDays = $this->settings->tenant_grace_period_days ?? 7;
        return now()->addDays($graceDays);
    }

    private function getPenaltyType(): string
    {
        $percentage = $this->settings->tenant_late_payment_percentage;
        $fixed      = $this->settings->tenant_fixed_penalty_amount;

        if ($percentage > 0 && $fixed > 0) {
            return "Both {$percentage}% and {$this->settings->formatAmount($fixed)} fixed (system will use percentage)";
        } elseif ($percentage > 0) {
            return "{$percentage}% of outstanding amount";
        } elseif ($fixed > 0) {
            return "Fixed amount of {$this->settings->formatAmount($fixed)}";
        }

        return "No penalty configured";
    }

    private function calculateDefaultTenantDues(): float
    {
        $method = $this->settings->tenant_calculation_method ?? 'fixed';

        switch ($method) {
            case 'fixed':
                return $this->settings->tenant_monthly_dues_amount ?? 0;

            case 'percentage_of_landlord':
                $landlordDues = $this->settings->monthly_dues_amount ?? 0;
                $percentage = $this->settings->tenant_dues_percentage ?? 50;
                return $landlordDues * ($percentage / 100);

            case 'per_property_unit':
                return $this->settings->tenant_monthly_dues_amount ?? 0;

            default:
                return $this->settings->tenant_monthly_dues_amount ?? 0;
        }
    }

    private function calculateTenantDuesForUnit(?PropertyUnit $unit): float
    {
        if (!$unit) {
            return $this->calculateDefaultTenantDues();
        }

        $method = $this->settings->tenant_calculation_method ?? 'fixed';

        switch ($method) {
            case 'fixed':
                return $this->settings->tenant_monthly_dues_amount ?? 0;

            case 'per_property_unit':
                return $unit->tenant_monthly_dues ??
                       ($unit->monthly_dues * ($this->settings->tenant_dues_percentage ?? 50) / 100) ??
                       $this->settings->tenant_monthly_dues_amount ?? 0;

            case 'percentage_of_landlord':
                $landlordDues = $unit->monthly_dues ?? $unit->monthly_rent ?? $this->settings->monthly_dues_amount ?? 0;
                $percentage = $this->settings->tenant_dues_percentage ?? 50;
                return $landlordDues * ($percentage / 100);

            default:
                return $this->settings->tenant_monthly_dues_amount ?? 0;
        }
    }

    private function validateAmountAgainstSettings(float $amount, PropertyUnit $unit): array
    {
        $expectedAmount = $this->calculateTenantDuesForUnit($unit);
        $tolerance = 0.01;

        if (abs($amount - $expectedAmount) > $tolerance) {
            $method = $this->settings->tenant_calculation_method ?? 'fixed';
            $methodText = $this->settings->getTenantCalculationMethodText();

            return [
                'valid'   => false,
                'message' => "Amount ({$this->settings->formatAmount($amount)}) differs from system-calculated amount ({$this->settings->formatAmount($expectedAmount)}) based on '{$methodText}' method. Consider using the calculated amount.",
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Amount matches system settings.',
        ];
    }

    private function calculateNextPaymentForTenant(int $tenantId): array
    {
        $nextInvoice = TenantInvoice::where('tenant_id', $tenantId)
            ->where('status', TenantInvoice::STATUS_PENDING)
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->first();

        if (!$nextInvoice) {
            $lastInvoice = TenantInvoice::where('tenant_id', $tenantId)
                ->orderBy('period', 'desc')
                ->first();

            if ($lastInvoice) {
                $nextPeriod  = Carbon::parse($lastInvoice->period . '-01')->addMonth()->format('Y-m');
                $nextDueDate = $this->calculateNextDueDate();

                return [
                    'exists'           => false,
                    'expected_period'  => Carbon::parse($nextPeriod . '-01')->format('F Y'),
                    'expected_amount'  => $this->calculateDefaultTenantDues(),
                    'formatted_amount' => $this->settings->formatAmount($this->calculateDefaultTenantDues()),
                    'expected_due_date' => $nextDueDate->format('Y-m-d'),
                    'days_until_due'   => now()->startOfDay()->diffInDays($nextDueDate, false),
                ];
            }

            return [
                'exists'  => false,
                'message' => 'No payment history found',
            ];
        }

        return [
            'exists'           => true,
            'period'           => Carbon::parse($nextInvoice->period . '-01')->format('F Y'),
            'amount'           => $nextInvoice->total_amount,
            'formatted_amount' => $this->settings->formatAmount($nextInvoice->total_amount),
            'due_date'         => $nextInvoice->due_date->format('Y-m-d'),
            'days_until_due'   => now()->startOfDay()->diffInDays($nextInvoice->due_date, false),
            'invoice_id'       => $nextInvoice->id,
        ];
    }

    private function calculatePenaltyInfo(TenantInvoice $invoice): array
    {
        $percentage = $this->settings->tenant_late_payment_percentage ?? 0;
        $fixed      = $this->settings->tenant_fixed_penalty_amount ?? 0;
        $graceDays  = $this->settings->tenant_grace_period_days ?? 7;

        $daysOverdue     = $this->calculateDaysOverdue($invoice);
        $canApplyPenalty = $daysOverdue > $graceDays && !$invoice->isPaid();

        $potentialPercentagePenalty = $invoice->total_amount * ($percentage / 100);
        $potentialFixedPenalty      = $fixed;

        $penaltyType = $this->getPenaltyType();

        return [
            'days_overdue'                   => $daysOverdue,
            'grace_period'                   => $graceDays,
            'within_grace_period'            => $daysOverdue <= $graceDays,
            'can_apply_penalty'              => $canApplyPenalty,
            'penalty_type'                   => $penaltyType,
            'potential_percentage_penalty'   => $potentialPercentagePenalty,
            'formatted_percentage_penalty'   => $this->settings->formatAmount($potentialPercentagePenalty),
            'potential_fixed_penalty'        => $potentialFixedPenalty,
            'formatted_fixed_penalty'        => $this->settings->formatAmount($potentialFixedPenalty),
            'has_penalty_applied'            => ($invoice->penalty_amount ?? 0) > 0,
            'applied_penalty_amount'         => $invoice->penalty_amount ?? 0,
            'formatted_applied_penalty'      => $this->settings->formatAmount($invoice->penalty_amount ?? 0),
        ];
    }

    private function calculateDaysOverdue(TenantInvoice $invoice): int
    {
        if ($invoice->isPaid() || $invoice->due_date > now()) {
            return 0;
        }

        return now()->startOfDay()->diffInDays($invoice->due_date);
    }

    private function isWithinGracePeriod(TenantInvoice $invoice): bool
    {
        if ($invoice->isPaid() || $invoice->due_date > now()) {
            return false;
        }

        $graceDays = $this->settings->tenant_grace_period_days ?? 7;
        $gracePeriodEnd = $invoice->due_date->copy()->addDays($graceDays);

        return now() <= $gracePeriodEnd;
    }

    private function canApplyAutoPenalty(TenantInvoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            return false;
        }

        $daysOverdue = $this->calculateDaysOverdue($invoice);
        $graceDays   = $this->settings->tenant_grace_period_days ?? 7;
        $hasPenaltyConfigured = ($this->settings->tenant_late_payment_percentage ?? 0) > 0 ||
                                ($this->settings->tenant_fixed_penalty_amount ?? 0) > 0;

        return $daysOverdue > $graceDays && $hasPenaltyConfigured && ($invoice->penalty_amount ?? 0) == 0;
    }

    private function calculatePaymentDetails(TenantInvoice $invoice): array
    {
        $total   = $invoice->total_amount;
        $paid    = $invoice->paid_amount ?? 0;
        $balance = $invoice->balance ?? $total;
        $penalty = $invoice->penalty_amount ?? 0;

        $dueDate   = $invoice->due_date;
        $graceDays = $this->settings->tenant_grace_period_days ?? 7;
        $graceEnd  = $dueDate->copy()->addDays($graceDays);

        $status = $invoice->status;
        $statusColor = match ($status) {
            TenantInvoice::STATUS_PAID    => 'success',
            TenantInvoice::STATUS_PENDING => 'warning',
            TenantInvoice::STATUS_OVERDUE => 'danger',
            default                       => 'secondary',
        };

        return [
            'total'               => $total,
            'formatted_total'     => $this->settings->formatAmount($total),
            'paid'                => $paid,
            'formatted_paid'      => $this->settings->formatAmount($paid),
            'balance'             => $balance,
            'formatted_balance'   => $this->settings->formatAmount($balance),
            'penalty'             => $penalty,
            'formatted_penalty'   => $this->settings->formatAmount($penalty),
            'due_date'            => $dueDate->format('Y-m-d'),
            'formatted_due_date'  => $dueDate->format('F j, Y'),
            'days_until_due'      => now()->startOfDay()->diffInDays($dueDate, false),
            'grace_end_date'      => $graceEnd->format('Y-m-d'),
            'formatted_grace_end' => $graceEnd->format('F j, Y'),
            'days_in_grace'       => now()->startOfDay()->diffInDays($graceEnd, false),
            'status'              => $status,
            'status_color'        => $statusColor,
            'status_badge'        => "<span class='badge bg-{$statusColor}'>{$status}</span>",
        ];
    }

    private function getPaymentInstructions(): array
    {
        $settings = $this->settings;

        $instructions = [];

        if ($settings->payment_instructions) {
            $instructions['general'] = $settings->payment_instructions;
        }

        if ($settings->primary_payment_provider && $settings->payment_mobile_number) {
            $instructions['mobile_money'] = [
                'provider' => $settings->primary_payment_provider,
                'number'   => $settings->payment_mobile_number,
                'name'     => $settings->payment_account_name,
                'network'  => $settings->payment_network,
            ];
        }

        if ($settings->bank_account_number) {
            $instructions['bank'] = [
                'account_number' => $settings->bank_account_number,
                'account_name'   => $settings->bank_account_name,
                'bank_name'      => $settings->bank_name,
                'branch'         => $settings->bank_branch,
            ];
        }

        if ($settings->custom_payment_message) {
            $instructions['custom_message'] = $settings->custom_payment_message;
        }

        return $instructions;
    }
}