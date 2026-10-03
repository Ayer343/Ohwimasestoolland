<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\PropertyUnitInvoice;
use App\Models\RentalAgreement;
use App\Models\User;
use App\Models\ActivityLog;
use App\Mail\InvoiceNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyUnitFinancialController extends Controller
{
    // ✅ GHANA: Legal constants (mirror of PropertyUnitLeaseController)
    private const LEGAL_MAX_ADVANCE_MONTHS_NEW_TENANCY   = 6;
    private const LEGAL_MAX_ADVANCE_MONTHS_RENEWAL       = 3;
    private const LEGAL_MAX_ADVANCE_MONTHS_SHORT_TENANCY = 1;

    // ========== FINANCIAL MANAGEMENT ==========

    public function showFinancials($id)
    {
        $unit = PropertyUnit::with([
            'property.landlord',
            'tenant',
            'currentLease',
            'invoices' => function ($query) {
                $query->orderBy('due_date', 'desc');
            },
        ])->findOrFail($id);

        $this->checkUnitAccessWithError($unit);

        // ========== ✅ INVOICE: SINGLE SOURCE OF TRUTH ==========
        $invoices = $unit->invoices;

        $invoiceSummary = [
            'total_invoiced' => (float) $invoices->sum('amount'),
            'total_paid'     => (float) $invoices->sum('amount_paid'),
            'outstanding'    => (float) $invoices
                ->whereIn('status', [
                    PropertyUnitInvoice::STATUS_PENDING,
                    PropertyUnitInvoice::STATUS_PARTIAL,
                    PropertyUnitInvoice::STATUS_OVERDUE,
                ])
                ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid)),
            'overdue_count'  => $invoices->where('status', PropertyUnitInvoice::STATUS_OVERDUE)->count(),
            'paid_count'     => $invoices->where('status', PropertyUnitInvoice::STATUS_PAID)->count(),
            'void_count'     => $invoices->where('status', PropertyUnitInvoice::STATUS_VOID)->count(),
        ];

        $financials = [
            'total_rent_collected'       => $invoiceSummary['total_paid'],
            'outstanding_balance'        => $invoiceSummary['outstanding'],
            'average_rent_payment_days'  => $this->calculateAveragePaymentDays($unit),
            'next_payment_due'           => $this->resolveNextPaymentDue($unit),
            'total_invoiced'             => $invoiceSummary['total_invoiced'],
            'overdue_count'              => $invoiceSummary['overdue_count'],
            'paid_count'                 => $invoiceSummary['paid_count'],
        ];

        // ========== ✅ GHANA: Phase + advance info ==========
        $currentPhase = $unit->currentLease?->current_phase;
        $advanceInfo  = null;

        if ($unit->currentLease) {
            $lease = $unit->currentLease;
            $advanceInfo = [
                'advance_rent_months'        => $lease->advance_rent_months,
                'advance_rent_amount'        => $lease->advance_rent_amount,
                'advance_rent_period_start'  => optional($lease->advance_rent_period_start)->toDateString(),
                'advance_rent_period_end'    => optional($lease->advance_rent_period_end)->toDateString(),
                'payment_frequency'          => $lease->payment_frequency,
                'first_monthly_payment_date' => optional($lease->first_monthly_payment_date)->toDateString(),
                'is_compliant'               => $lease->advance_rent_compliance_status === 'compliant',
            ];
        }

        // ========== ✅ INVOICE: Category breakdown ==========
        $advanceInvoices = $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT);
        $monthlyInvoices = $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT);
        $depositInvoices = $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT);
        $lateFeeInvoices = $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_LATE_FEE);

        return view('property_units.financials', compact(
            'unit',
            'financials',
            'invoices',
            'invoiceSummary',
            'currentPhase',
            'advanceInfo',
            'advanceInvoices',
            'monthlyInvoices',
            'depositInvoices',
            'lateFeeInvoices'
        ));
    }

    /**
     * ✅ INVOICE: Generate an invoice against a unit using PropertyUnitInvoice.
     */
    public function generateInvoice(Request $request, $id)
    {
        $unit = PropertyUnit::with(['tenant', 'property', 'currentLease'])->findOrFail($id);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to generate invoices for this unit.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'invoice_date' => 'nullable|date',
            'issue_date'   => 'nullable|date',
            'due_date'     => 'required|date',
            'amount'       => 'required|numeric|min:0.01|max:999999.99',
            'description'  => 'required|string|max:500',
            'invoice_type' => 'required|string|in:' . implode(',', [
                PropertyUnitInvoice::TYPE_ADVANCE_RENT,
                PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT,
                PropertyUnitInvoice::TYPE_LATE_FEE,
                PropertyUnitInvoice::TYPE_EARLY_TERMINATION,
                PropertyUnitInvoice::TYPE_OTHER,
                'rent', 'maintenance', 'penalty',
            ]),
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $validator->after(function ($validator) use ($request) {
            $issueDate = $request->input('issue_date') ?? $request->input('invoice_date');
            if ($issueDate && $request->due_date && Carbon::parse($request->due_date)->lt(Carbon::parse($issueDate))) {
                $validator->errors()->add('due_date', 'Due date must be on or after the issue date.');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $invoiceType = $this->normalizeInvoiceType($request->invoice_type);

            $issueDate = $request->filled('issue_date')
                ? Carbon::parse($request->issue_date)
                : ($request->filled('invoice_date')
                    ? Carbon::parse($request->invoice_date)
                    : now());

            $dueDate = Carbon::parse($request->due_date);

            $lease = $unit->currentLease;
            $isAdvance = $invoiceType === PropertyUnitInvoice::TYPE_ADVANCE_RENT;

            $invoice = PropertyUnitInvoice::create([
                'lease_id'             => $lease?->id,
                'unit_id'              => $unit->id,
                'property_id'          => $unit->property_id,
                'tenant_id'            => $unit->tenant_id,
                'landlord_id'          => $unit->property->landlord_id,
                'invoice_type'         => $invoiceType,
                'reference'            => $this->generateInvoiceReference($invoiceType),
                'description'          => $request->description,
                'amount'               => $request->amount,
                'amount_paid'          => 0,
                'issue_date'           => $issueDate,
                'due_date'             => $dueDate,
                'period_start'         => $request->period_start,
                'period_end'           => $request->period_end,
                'status'               => PropertyUnitInvoice::STATUS_PENDING,
                'notes'                => $request->notes,
                'advance_rent_months'  => $isAdvance ? ($lease?->advance_rent_months) : null,
                'is_advance_rent'      => $isAdvance,
            ]);

            $this->logActivity(
                $user->id,
                'invoice_generated',
                'Invoice generated for unit',
                $unit->id,
                [
                    'invoice_id'   => $invoice->id,
                    'reference'    => $invoice->reference,
                    'amount'       => $invoice->amount,
                    'invoice_type' => $invoice->invoice_type,
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Invoice {$invoice->reference} generated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating invoice: ' . $e->getMessage(), [
                'unit_id' => $unit->id,
                'request' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to generate invoice: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== ✅ NEW: SHOW SINGLE INVOICE ==========

    /**
     * ✅ NEW: Show a single invoice (JSON for AJAX or full view).
     *
     * Returns JSON when the request expects JSON; otherwise renders the
     * invoice detail view.
     */
    public function showInvoice(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($unitId);
        $invoice = PropertyUnitInvoice::with(['lease', 'tenant', 'landlord'])
            ->where('unit_id', $unitId)
            ->findOrFail($invoiceId);

        $this->checkUnitAccessWithError($unit);

        // Tenant can only see their own invoice
        $user = auth()->user();
        if (($user->isTenant() || $user->hasRole('tenant')) && $invoice->tenant_id !== $user->id) {
            abort(403, 'You can only view your own invoices.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'invoice' => [
                    'id'              => $invoice->id,
                    'reference'       => $invoice->reference,
                    'invoice_type'    => $invoice->invoice_type,
                    'description'     => $invoice->description,
                    'amount'          => (float) $invoice->amount,
                    'amount_paid'     => (float) $invoice->amount_paid,
                    'balance'         => $invoice->balance,
                    'status'          => $invoice->status,
                    'issue_date'      => optional($invoice->issue_date)->toDateString(),
                    'due_date'        => optional($invoice->due_date)->toDateString(),
                    'period_start'    => optional($invoice->period_start)->toDateString(),
                    'period_end'      => optional($invoice->period_end)->toDateString(),
                    'paid_at'         => optional($invoice->paid_at)->toIso8601String(),
                    'last_payment_at' => optional($invoice->last_payment_at)->toIso8601String(),
                    'payment_method'  => $invoice->payment_method,
                    'notes'           => $invoice->notes,
                    'void_reason'     => $invoice->void_reason,
                    'voided_at'       => optional($invoice->voided_at)->toIso8601String(),
                    'created_at'      => $invoice->created_at->toIso8601String(),
                    'updated_at'      => $invoice->updated_at->toIso8601String(),
                ],
            ]);
        }

        return view('property_units.invoices.show', compact('unit', 'invoice'));
    }

    // ========== ✅ NEW: UPDATE INVOICE ==========

    /**
     * ✅ NEW: Update an invoice's non-financial fields.
     * Amount changes are rejected — void and recreate instead.
     */
    public function updateInvoice(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $invoice = PropertyUnitInvoice::where('unit_id', $unitId)->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'You are not authorized to update this invoice.');
        }

        if ($invoice->status === PropertyUnitInvoice::STATUS_VOID) {
            return redirect()->back()->with('error', 'Cannot update a voided invoice.');
        }
        if ($invoice->status === PropertyUnitInvoice::STATUS_PAID) {
            return redirect()->back()->with('error', 'Cannot update a fully paid invoice.');
        }

        $validator = Validator::make($request->all(), [
            'description'  => 'sometimes|string|max:500',
            'due_date'     => 'sometimes|date',
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $before = $invoice->only(['description', 'due_date', 'period_start', 'period_end', 'notes']);
            $invoice->update($validator->validated());
            $after = $invoice->only(['description', 'due_date', 'period_start', 'period_end', 'notes']);

            $this->logActivity(
                $user->id,
                'invoice_updated',
                'Invoice updated',
                $unit->id,
                [
                    'invoice_id' => $invoice->id,
                    'reference'  => $invoice->reference,
                    'changes'    => $this->diffChanges($before, $after),
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Invoice {$invoice->reference} updated.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating invoice: ' . $e->getMessage(), [
                'unit_id'    => $unitId,
                'invoice_id' => $invoiceId,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update invoice: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== PAYMENT ==========

    /**
     * ✅ INVOICE: Record a payment (full or partial) against an invoice.
     */
    public function recordPayment(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $invoice = PropertyUnitInvoice::where('unit_id', $unitId)->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to record payments for this unit.')
                ->withInput();
        }

        if ($invoice->status === PropertyUnitInvoice::STATUS_VOID) {
            return redirect()->back()->with('error', 'Cannot record a payment against a voided invoice.');
        }
        if ($invoice->status === PropertyUnitInvoice::STATUS_PAID) {
            return redirect()->back()->with('error', 'This invoice is already fully paid.');
        }

        $outstanding = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);

        $validator = Validator::make($request->all(), [
            'payment_date'     => 'required|date|before_or_equal:today',
            'payment_amount'   => 'required|numeric|min:0.01|max:' . $outstanding,
            'payment_method'   => 'required|in:cash,bank_transfer,mobile_money,card,cheque,other',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
            'receipt_file'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'payment_amount.max' => 'Payment amount cannot exceed the outstanding balance of '
                . number_format($outstanding, 2) . '.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $receiptPath = null;
            if ($request->hasFile('receipt_file')) {
                $receiptPath = $request->file('receipt_file')
                    ->store("invoice_receipts/{$invoice->id}", 'private');
            }

            $newAmountPaid = (float) $invoice->amount_paid + (float) $request->payment_amount;
            $isFullyPaid = $newAmountPaid >= (float) $invoice->amount;

            $updateData = [
                'amount_paid'       => $newAmountPaid,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->reference_number,
                'last_payment_at'   => Carbon::parse($request->payment_date),
                'status'            => $isFullyPaid
                    ? PropertyUnitInvoice::STATUS_PAID
                    : PropertyUnitInvoice::STATUS_PARTIAL,
                'paid_at'           => $isFullyPaid ? now() : $invoice->paid_at,
            ];

            if ($receiptPath) {
                $metadata = $invoice->metadata ?? [];
                $metadata['receipt_path'] = $receiptPath;
                $updateData['metadata'] = $metadata;
            }

            $invoice->update($updateData);

            $this->logActivity(
                $user->id,
                'payment_recorded',
                'Payment recorded for invoice',
                $unit->id,
                [
                    'invoice_id'     => $invoice->id,
                    'reference'      => $invoice->reference,
                    'payment_amount' => $request->payment_amount,
                    'payment_method' => $request->payment_method,
                    'is_fully_paid'  => $isFullyPaid,
                    'remaining'      => max(0, (float) $invoice->amount - $newAmountPaid),
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);
            $currency    = config('leases.ghana.currency.symbol', 'GH₵');

            $message = $isFullyPaid
                ? "Payment recorded. Invoice {$invoice->reference} is now fully paid."
                : "Partial payment recorded. Remaining balance: {$currency} "
                    . number_format(max(0, (float) $invoice->amount - $newAmountPaid), 2);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording payment: ' . $e->getMessage(), [
                'unit_id'    => $unitId,
                'invoice_id' => $invoiceId,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to record payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== VOID ==========

    /**
     * ✅ INVOICE: Void an invoice.
     */
    public function voidInvoice(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $invoice = PropertyUnitInvoice::where('unit_id', $unitId)->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        if ($invoice->status === PropertyUnitInvoice::STATUS_PAID) {
            return redirect()->back()->with('error', 'Cannot void a fully paid invoice.');
        }
        if ($invoice->status === PropertyUnitInvoice::STATUS_VOID) {
            return redirect()->back()->with('error', 'Invoice is already voided.');
        }

        $validator = Validator::make($request->all(), [
            'void_reason' => 'required|string|min:5|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $invoice->update([
                'status'      => PropertyUnitInvoice::STATUS_VOID,
                'void_reason' => $request->void_reason,
                'voided_at'   => now(),
                'voided_by'   => $user->id,
            ]);

            $this->logActivity(
                $user->id,
                'invoice_voided',
                'Invoice voided',
                $unit->id,
                [
                    'invoice_id'  => $invoice->id,
                    'reference'   => $invoice->reference,
                    'void_reason' => $request->void_reason,
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Invoice {$invoice->reference} voided successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error voiding invoice: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to void invoice: ' . $e->getMessage());
        }
    }

    // ========== ✅ NEW: BULK VOID ==========

    /**
     * ✅ NEW: Void multiple invoices at once.
     */
    public function bulkVoid(Request $request, $unitId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:property_unit_invoices,id',
            'void_reason'   => 'required|string|min:5|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $voided = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($request->invoice_ids as $id) {
                $invoice = PropertyUnitInvoice::where('unit_id', $unit->id)->find($id);
                if (!$invoice) { $skipped++; continue; }
                if ($invoice->status === PropertyUnitInvoice::STATUS_PAID
                    || $invoice->status === PropertyUnitInvoice::STATUS_VOID) {
                    $skipped++;
                    continue;
                }

                $invoice->update([
                    'status'      => PropertyUnitInvoice::STATUS_VOID,
                    'void_reason' => $request->void_reason,
                    'voided_at'   => now(),
                    'voided_by'   => $user->id,
                ]);
                $voided++;
            }

            $this->logActivity(
                $user->id,
                'invoices_bulk_voided',
                "{$voided} invoice(s) voided in bulk",
                $unit->id,
                [
                    'voided_count'  => $voided,
                    'skipped_count' => $skipped,
                    'void_reason'   => $request->void_reason,
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "{$voided} invoice(s) voided. {$skipped} skipped.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk-voiding invoices: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to void invoices: ' . $e->getMessage());
        }
    }

    // ========== ✅ NEW: APPLY LATE FEE ==========

    /**
     * ✅ NEW: Apply the lease's late fee rules to an overdue invoice.
     * Creates a new TYPE_LATE_FEE invoice linked to the original.
     */
    public function applyLateFee(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::with('currentLease')->findOrFail($unitId);
        $invoice = PropertyUnitInvoice::with('lease')
            ->where('unit_id', $unitId)
            ->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        if ($invoice->status !== PropertyUnitInvoice::STATUS_OVERDUE) {
            return redirect()->back()->with('error', 'Only overdue invoices can have late fees applied.');
        }

        $lease = $invoice->lease ?? $unit->currentLease;
        if (!$lease) {
            return redirect()->back()->with('error', 'This invoice is not linked to a lease.');
        }

        // Prevent duplicate late fees for the same invoice
        $existing = PropertyUnitInvoice::where('lease_id', $lease->id)
            ->where('invoice_type', PropertyUnitInvoice::TYPE_LATE_FEE)
            ->where('description', 'like', "%{$invoice->reference}%")
            ->exists();

        if ($existing) {
            return redirect()->back()->with('error', 'A late fee has already been applied to this invoice.');
        }

        $lateFee = method_exists($lease, 'calculateLateFee')
            ? $lease->calculateLateFee((float) $invoice->amount)
            : 0;

        if ($lateFee <= 0) {
            return redirect()->back()->with('info', 'No late fee is configured on this lease.');
        }

        DB::beginTransaction();
        try {
            $lateInvoice = PropertyUnitInvoice::create([
                'lease_id'      => $lease->id,
                'unit_id'       => $invoice->unit_id,
                'property_id'   => $invoice->property_id,
                'tenant_id'     => $invoice->tenant_id,
                'landlord_id'   => $invoice->landlord_id,
                'invoice_type'  => PropertyUnitInvoice::TYPE_LATE_FEE,
                'reference'     => $this->generateInvoiceReference(PropertyUnitInvoice::TYPE_LATE_FEE),
                'description'   => "Late fee for invoice {$invoice->reference}",
                'amount'        => $lateFee,
                'amount_paid'   => 0,
                'due_date'      => now()->addDays((int) ($lease->grace_period_days ?? 0) ?: 7),
                'status'        => PropertyUnitInvoice::STATUS_PENDING,
                'notes'         => 'Auto-generated late fee',
            ]);

            $this->logActivity(
                $user->id,
                'invoice_late_fee_applied',
                'Late fee applied to overdue invoice',
                $unit->id,
                [
                    'original_invoice_id' => $invoice->id,
                    'original_reference'  => $invoice->reference,
                    'late_fee_invoice_id' => $lateInvoice->id,
                    'late_fee_reference'  => $lateInvoice->reference,
                    'late_fee_amount'     => $lateFee,
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);
            $currency    = config('leases.ghana.currency.symbol', 'GH₵');

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Late fee of {$currency} " . number_format($lateFee, 2) . " applied.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error applying late fee: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
            ]);
            return redirect()->back()->with('error', 'Failed to apply late fee: ' . $e->getMessage());
        }
    }

    // ========== ✅ NEW: SEND TO TENANT ==========

    /**
     * ✅ NEW: Email the invoice to its tenant.
     */
    public function sendInvoiceToTenant(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::with('property')->findOrFail($unitId);
        $invoice = PropertyUnitInvoice::with(['tenant', 'lease'])
            ->where('unit_id', $unitId)
            ->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        if (!$invoice->tenant || !$invoice->tenant->email) {
            return redirect()->back()->with('error', 'This invoice has no tenant email on file.');
        }

        if ($invoice->status === PropertyUnitInvoice::STATUS_VOID) {
            return redirect()->back()->with('error', 'Cannot send a voided invoice.');
        }

        try {
            if (class_exists(InvoiceNotificationMail::class)) {
                Mail::to($invoice->tenant->email)->send(
                    new InvoiceNotificationMail($invoice, $unit)
                );
            } else {
                Log::info('InvoiceNotificationMail class not found — logging instead', [
                    'invoice_id' => $invoice->id,
                    'to'         => $invoice->tenant->email,
                ]);
            }

            $this->logActivity(
                $user->id,
                'invoice_sent_to_tenant',
                'Invoice sent to tenant via email',
                $unit->id,
                [
                    'invoice_id' => $invoice->id,
                    'reference'  => $invoice->reference,
                    'sent_to'    => $invoice->tenant->email,
                ]
            );

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Invoice {$invoice->reference} sent to {$invoice->tenant->email}.");

        } catch (\Exception $e) {
            Log::warning('Failed to send invoice email: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
            ]);
            return redirect()->back()->with('error', 'Failed to send invoice email: ' . $e->getMessage());
        }
    }

    // ========== ✅ NEW: MARK OVERDUE / RECALCULATE STATUS ==========

    /**
     * ✅ NEW: Mark past-due pending/partial invoices as overdue.
     *
     * Safe to run on-demand. Returns counts so the UI can report.
     */
    public function markOverdue(Request $request, $unitId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $cutoffDays = (int) config('leases.invoice.mark_overdue_after_days', 1);
        $cutoff     = now()->subDays($cutoffDays)->startOfDay();

        $count = PropertyUnitInvoice::where('unit_id', $unit->id)
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $cutoff)
            ->update(['status' => PropertyUnitInvoice::STATUS_OVERDUE]);

        $this->logActivity(
            $user->id,
            'invoices_marked_overdue',
            "{$count} invoice(s) marked overdue",
            $unit->id,
            ['marked_count' => $count, 'cutoff_date' => $cutoff->toDateString()]
        );

        $routePrefix = $this->routePrefixFor($user);

        return redirect()
            ->route($routePrefix . '.property-units.financials', $unit->id)
            ->with('success', "{$count} invoice(s) marked as overdue.");
    }

    /**
     * ✅ NEW: Recompute an invoice's status from amount_paid.
     * Useful after external writes or data repair.
     */
    public function recalculateStatus(Request $request, $unitId, $invoiceId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $invoice = PropertyUnitInvoice::where('unit_id', $unitId)->findOrFail($invoiceId);
        $user = auth()->user();

        if (!$this->canManageUnitFinancials($unit, $user)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        if ($invoice->status === PropertyUnitInvoice::STATUS_VOID) {
            return redirect()->back()->with('info', 'Voided invoices are not recalculated.');
        }

        DB::beginTransaction();
        try {
            $amount = (float) $invoice->amount;
            $paid   = (float) $invoice->amount_paid;

            if ($paid >= $amount) {
                $newStatus = PropertyUnitInvoice::STATUS_PAID;
                $paidAt    = $invoice->paid_at ?? now();
            } elseif ($paid > 0) {
                $newStatus = PropertyUnitInvoice::STATUS_PARTIAL;
                $paidAt    = null;
            } elseif ($invoice->due_date && $invoice->due_date->isPast()) {
                $newStatus = PropertyUnitInvoice::STATUS_OVERDUE;
                $paidAt    = null;
            } else {
                $newStatus = PropertyUnitInvoice::STATUS_PENDING;
                $paidAt    = null;
            }

            $oldStatus = $invoice->status;
            $invoice->update([
                'status'  => $newStatus,
                'paid_at' => $paidAt,
            ]);

            $this->logActivity(
                $user->id,
                'invoice_status_recalculated',
                'Invoice status recalculated',
                $unit->id,
                [
                    'invoice_id' => $invoice->id,
                    'reference'  => $invoice->reference,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]
            );

            DB::commit();

            $routePrefix = $this->routePrefixFor($user);

            return redirect()
                ->route($routePrefix . '.property-units.financials', $unit->id)
                ->with('success', "Invoice {$invoice->reference} status: {$oldStatus} → {$newStatus}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recalculating invoice status: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to recalculate status.');
        }
    }

    // ========== FINANCIAL REPORTS ==========

    /**
     * ✅ INVOICE: Export a CSV of all invoices for a unit.
     */
    public function exportFinancialReport($id)
    {
        $unit = PropertyUnit::with(['invoices', 'tenant', 'property', 'currentLease'])->findOrFail($id);

        $this->checkUnitAccessWithError($unit);

        $invoices = $unit->invoices()
            ->orderBy('due_date', 'desc')
            ->get();

        $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
        $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

        $fileName = 'financial_report_unit_' . $unit->unit_number . '_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($unit, $invoices, $currencySymbol, $governingLaw) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Unit Financial Report']);
            fputcsv($file, ['']);
            fputcsv($file, ['Generated At', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Governing Law', $governingLaw]);
            fputcsv($file, ['Currency', $currencySymbol]);
            fputcsv($file, ['']);

            fputcsv($file, ['Unit Information']);
            fputcsv($file, ['Property', $unit->property->property_name ?? 'N/A']);
            fputcsv($file, ['Unit Number', $unit->unit_number]);
            fputcsv($file, ['Current Rent', $unit->current_rent_amount ?? $unit->monthly_rent]);
            fputcsv($file, ['Tenant', $unit->tenant->name ?? 'N/A']);

            $lease = $unit->currentLease;
            if ($lease && $lease->has_advance_rent) {
                fputcsv($file, ['Advance Rent Months', $lease->advance_rent_months]);
                fputcsv($file, ['Advance Rent Amount', $lease->advance_rent_amount]);
                fputcsv($file, ['Advance Period Start', optional($lease->advance_rent_period_start)->toDateString()]);
                fputcsv($file, ['Advance Period End', optional($lease->advance_rent_period_end)->toDateString()]);
                fputcsv($file, ['Payment Frequency', $lease->payment_frequency]);
                fputcsv($file, ['Compliance', $lease->advance_rent_compliance_status]);
            }

            fputcsv($file, ['']);

            $totalInvoiced    = $invoices->sum('amount');
            $totalCollected   = $invoices->sum('amount_paid');
            $totalOutstanding = $invoices
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid));

            fputcsv($file, ['Financial Summary']);
            fputcsv($file, ['Total Invoices',    $invoices->count()]);
            fputcsv($file, ['Total Invoiced',    $totalInvoiced]);
            fputcsv($file, ['Total Collected',   $totalCollected]);
            fputcsv($file, ['Total Outstanding', $totalOutstanding]);
            fputcsv($file, ['Overdue Count',     $invoices->where('status', 'overdue')->count()]);
            fputcsv($file, ['Paid Count',        $invoices->where('status', 'paid')->count()]);
            fputcsv($file, ['Void Count',        $invoices->where('status', 'void')->count()]);
            fputcsv($file, ['']);
            fputcsv($file, ['']);

            fputcsv($file, ['Invoice Details']);
            fputcsv($file, [
                'Reference', 'Type', 'Description',
                'Issue Date', 'Due Date',
                'Period Start', 'Period End',
                'Amount', 'Amount Paid', 'Balance',
                'Status', 'Paid At',
                'Payment Method', 'Payment Reference',
                'Notes',
            ]);

            foreach ($invoices as $invoice) {
                $balance = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);

                fputcsv($file, [
                    $invoice->reference,
                    $invoice->invoice_type,
                    $invoice->description,
                    optional($invoice->issue_date)->format('Y-m-d') ?? 'N/A',
                    optional($invoice->due_date)->format('Y-m-d') ?? 'N/A',
                    optional($invoice->period_start)->format('Y-m-d') ?? 'N/A',
                    optional($invoice->period_end)->format('Y-m-d') ?? 'N/A',
                    number_format((float) $invoice->amount, 2, '.', ''),
                    number_format((float) $invoice->amount_paid, 2, '.', ''),
                    number_format($balance, 2, '.', ''),
                    $invoice->status,
                    optional($invoice->paid_at)->format('Y-m-d H:i:s') ?? 'N/A',
                    $invoice->payment_method ?? 'N/A',
                    $invoice->payment_reference ?? 'N/A',
                    $invoice->notes ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ========== PDF EXPORT ==========

    public function downloadInvoicePdf($unitId, $invoiceId)
    {
        $unit = PropertyUnit::with(['property.landlord', 'tenant', 'currentLease'])->findOrFail($unitId);
        $invoice = PropertyUnitInvoice::where('unit_id', $unitId)->findOrFail($invoiceId);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();
        $isTenant = $user->isTenant() || $user->hasRole('tenant');
        if ($isTenant && $invoice->tenant_id !== $user->id) {
            abort(403, 'You can only download your own invoices.');
        }

        $data = [
            'invoice'         => $invoice,
            'unit'            => $unit,
            'property'        => $unit->property,
            'tenant'          => $unit->tenant,
            'landlord'        => $unit->property->landlord,
            'generated_date'  => now()->format('F j, Y'),
            'currency_symbol' => config('leases.ghana.currency.symbol', 'GH₵'),
            'governing_law'   => config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)'),
        ];

        try {
            $pdf = Pdf::loadView('pdf.invoice', $data);
            $pdf->setPaper('A4', 'portrait');

            $safeRef  = preg_replace('/[^A-Za-z0-9\-_]/', '-', $invoice->reference);
            $filename = "Invoice-{$safeRef}-{$unit->unit_number}.pdf";

            $this->logActivity(
                $user->id,
                'invoice_pdf_downloaded',
                'Invoice PDF downloaded',
                $unit->id,
                ['invoice_id' => $invoice->id, 'reference' => $invoice->reference]
            );

            return $pdf->download($filename);

        } catch (\Throwable $e) {
    Log::error('Failed to generate invoice PDF', [
        'invoice_id'    => $invoiceId,
        'invoice_ref'   => $invoice->reference ?? null,
        'unit_id'       => $unitId,
        'user_id'       => $user->id,
        'error'         => $e->getMessage(),
        'error_class'   => get_class($e),
    ]);

    // Distinguish missing-view from other failures
    $message = str_contains($e->getMessage(), 'View [')
        ? 'Invoice PDF template is missing. Please contact support.'
        : 'Failed to generate invoice PDF. Please try again.';

    return redirect()->back()->with('error', $message);
}
    }

    // ========== HELPER METHODS ==========

    /**
     * ✅ INVOICE: Generate a unique reference string for an invoice.
     */
    private function generateInvoiceReference(string $type): string
    {
        $prefixMap = [
            PropertyUnitInvoice::TYPE_ADVANCE_RENT      => 'ADV',
            PropertyUnitInvoice::TYPE_MONTHLY_RENT      => 'MR',
            PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT  => 'DEP',
            PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT   => 'UTIL',
            PropertyUnitInvoice::TYPE_LATE_FEE          => 'LATE',
            PropertyUnitInvoice::TYPE_EARLY_TERMINATION => 'TERM',
            PropertyUnitInvoice::TYPE_OTHER             => 'INV',
        ];
        $prefix = $prefixMap[$type] ?? 'INV';

        return $prefix . '-' . now()->format('Ym') . '-' . strtoupper(Str::random(6));
    }

    /**
     * ✅ INVOICE: Map legacy invoice_type values onto the new TYPE_* constants.
     */
    private function normalizeInvoiceType(string $type): string
    {
        return match ($type) {
            'rent'        => PropertyUnitInvoice::TYPE_MONTHLY_RENT,
            'maintenance' => PropertyUnitInvoice::TYPE_OTHER,
            'penalty'     => PropertyUnitInvoice::TYPE_LATE_FEE,
            default       => $type,
        };
    }

    /**
     * ✅ GHANA: Resolve the next payment due date across active invoices
     * and the lease's own next-payment info.
     */
    private function resolveNextPaymentDue(PropertyUnit $unit): ?string
    {
        if ($unit->currentLease) {
            $fromLease = $unit->currentLease->next_payment_due_date;
            if ($fromLease) {
                return $fromLease->toDateString();
            }
        }

        $fromInvoices = $unit->invoices()
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->orderBy('due_date', 'asc')
            ->value('due_date');

        return $fromInvoices ? Carbon::parse($fromInvoices)->toDateString() : null;
    }

    /**
     * ✅ INVOICE: Average days between invoice due_date and payment.
     */
    private function calculateAveragePaymentDays(PropertyUnit $unit): ?float
    {
        $paidInvoices = $unit->invoices()
            ->where('status', PropertyUnitInvoice::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereNotNull('due_date')
            ->get();

        if ($paidInvoices->isEmpty()) {
            return null;
        }

        $totalDays = 0;
        foreach ($paidInvoices as $invoice) {
            $days = $invoice->due_date->diffInDays($invoice->paid_at, false);
            $totalDays += max($days, 0);
        }

        return round($totalDays / $paidInvoices->count(), 1);
    }

    /**
     * ✅ INVOICE: Compute a simple before/after diff for activity logging.
     */
    private function diffChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'from' => $oldValue instanceof \DateTimeInterface ? $oldValue->format('Y-m-d') : $oldValue,
                    'to'   => $newValue instanceof \DateTimeInterface ? $newValue->format('Y-m-d') : $newValue,
                ];
            }
        }
        return $changes;
    }

    /**
     * ✅ INVOICE: Permission helper — landlord-owner, admin, or super admin.
     */
    private function canManageUnitFinancials(PropertyUnit $unit, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('super-admin')) return true;
        if ($user->isAdmin()      || $user->hasRole('admin'))       return true;
        if ($user->isLandlord()   || $user->hasRole('landlord')) {
            return $unit->property->landlord_id === $user->id;
        }
        return false;
    }

    /**
     * ✅ INVOICE: Route prefix based on role.
     */
    private function routePrefixFor(User $user): string
    {
        if ($user->isLandlord()   || $user->hasRole('landlord'))    return 'landlord';
        if ($user->isAdmin()      || $user->hasRole('admin'))       return 'admin';
        if ($user->isSuperAdmin() || $user->hasRole('super-admin')) return 'super-admin';
        if ($user->isTenant()     || $user->hasRole('tenant'))      return 'tenant';
        return '';
    }

    private function checkUnitAccessWithError(PropertyUnit $unit): void
    {
        $user = auth()->user();

        $isSuperAdmin    = $user->isSuperAdmin() || $user->hasRole('super-admin');
        $isAdmin         = $user->isAdmin()    || $user->hasRole('admin');
        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');
        $isTenant        = $user->isTenant()   || $user->hasRole('tenant');

        if ($isSuperAdmin || $isAdmin) return;

        if ($hasLandlordRole) {
            if ($unit->property->landlord_id === $user->id) return;
            abort(403, 'You can only view units in your own properties.');
        }

        if ($isTenant) {
            if ($unit->tenant_id === $user->id
                && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED) {
                return;
            }
            abort(403, 'You can only view your assigned unit.');
        }

        abort(403, 'Unauthorized access to property unit.');
    }

    private function logActivity(int $userId, string $type, string $description, ?int $unitId = null, array $metadata = []): void
    {
        try {
            ActivityLog::create([
                'user_id'     => $userId,
                'type'        => $type,
                'description' => $description,
                'unit_id'     => $unitId,
                'metadata'    => $metadata,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }
}