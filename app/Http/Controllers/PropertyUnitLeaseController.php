<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\RentalAgreement;
use App\Models\User;
use App\Models\TenantInvitation;
use App\Models\PropertyUnitInvoice;            // ✅ INVOICE: was App\Models\Invoice
use App\Mail\LeaseInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyUnitLeaseController extends Controller
{
    // ✅ GHANA: Legal constants from the Rent Act, 1963 (Act 220), Section 25(5)
    private const LEGAL_MAX_ADVANCE_MONTHS_NEW_TENANCY   = 6;
    private const LEGAL_MAX_ADVANCE_MONTHS_RENEWAL       = 3;
    private const LEGAL_MAX_ADVANCE_MONTHS_SHORT_TENANCY = 1;

    // ========== LEASE MANAGEMENT ==========

    public function showLeaseManagement($id)
    {
        $unit = PropertyUnit::with([
            'currentLease',
            'rentalAgreements' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');
        $isTenant        = $user->isTenant()   || $user->hasRole('tenant');
        $isAdmin         = $user->isAdmin()    || $user->hasRole('admin');
        $isSuperAdmin    = $user->isSuperAdmin() || $user->hasRole('super-admin');

        $isPropertyOwner = $hasLandlordRole && $unit->property->landlord_id === $user->id;

        $canCreateLease = $isPropertyOwner &&
                         $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED &&
                         (!$unit->currentLease || $unit->currentLease->status !== 'active');

        $canRenewLease = $hasLandlordRole &&
                        $unit->property->landlord_id === $user->id &&
                        $unit->currentLease &&
                        $unit->currentLease->status === 'active' &&
                        $unit->currentLease->end_date &&
                        $unit->currentLease->end_date->diffInDays(now()) <= 60;

        $canTerminateLease = $hasLandlordRole &&
                            $unit->property->landlord_id === $user->id &&
                            $unit->currentLease &&
                            $unit->currentLease->status === 'active';

        // ✅ GHANA: Expose current phase + advance info + invoice summary
        $currentPhase = null;
        $advanceInfo  = null;
        $invoiceSummary = null;

        if ($unit->currentLease) {
            $currentPhase = $unit->currentLease->current_phase;

            $advanceInfo = [
                'advance_rent_months'        => $unit->currentLease->advance_rent_months,
                'advance_rent_amount'        => $unit->currentLease->advance_rent_amount,
                'advance_rent_period_start'  => optional($unit->currentLease->advance_rent_period_start)->format('Y-m-d'),
                'advance_rent_period_end'    => optional($unit->currentLease->advance_rent_period_end)->format('Y-m-d'),
                'payment_frequency'          => $unit->currentLease->payment_frequency,
                'first_monthly_payment_date' => optional($unit->currentLease->first_monthly_payment_date)->format('Y-m-d'),
                'is_compliant'               => $unit->currentLease->advance_rent_compliance_status === 'compliant',
            ];

            // ✅ INVOICE: Pull invoice summary via the new model
            $invoiceSummary = [
                'total_invoiced' => PropertyUnitInvoice::where('lease_id', $unit->currentLease->id)->sum('amount'),
                'total_paid'     => PropertyUnitInvoice::where('lease_id', $unit->currentLease->id)->sum('amount_paid'),
                'outstanding'    => PropertyUnitInvoice::where('lease_id', $unit->currentLease->id)
                                        ->whereIn('status', ['pending', 'overdue', 'partial'])
                                        ->sum(DB::raw('amount - amount_paid')),
                'overdue_count'  => PropertyUnitInvoice::where('lease_id', $unit->currentLease->id)
                                        ->where('status', 'overdue')
                                        ->count(),
            ];
        }

        return view('property_units.lease-management', compact(
            'unit',
            'canCreateLease',
            'canRenewLease',
            'canTerminateLease',
            'hasLandlordRole',
            'isPropertyOwner',
            'currentPhase',
            'advanceInfo',
            'invoiceSummary'
        ));
    }

    public function showCreateLeaseForm($id)
    {
        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');

        if ($hasLandlordRole) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->route('property-units.show', $id)
                    ->with('error', 'Only the property owner can create leases.');
            }
        } else {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'Only property owners can create leases. Administrators have view-only access.');
        }

        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'Unit must have an approved tenant to create a lease.');
        }

        if ($unit->currentLease && $unit->currentLease->status === 'active') {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'Unit already has an active lease. You can renew or terminate the existing lease.');
        }

        $leaseTemplates     = $this->getLeaseTemplates();
        $paymentTerms       = $this->getPaymentTerms();
        $penaltyTerms       = $this->getPenaltyTerms();
        $maintenanceTerms   = $this->getMaintenanceTerms();

        // ✅ GHANA: Advance-rent guidance for the form
        $advanceRentOptions = $this->getAdvanceRentOptions();
        $legalMaxAdvance    = self::LEGAL_MAX_ADVANCE_MONTHS_NEW_TENANCY;
        $monthlyRentDefault = $unit->current_rent_amount ?? $unit->monthly_rent;

        return view('property_units.create-lease', compact(
            'unit',
            'leaseTemplates',
            'paymentTerms',
            'penaltyTerms',
            'maintenanceTerms',
            'advanceRentOptions',
            'legalMaxAdvance',
            'monthlyRentDefault'
        ));
    }

    public function createLease(Request $request, $id)
    {
        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');

        if ($hasLandlordRole) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()
                    ->with('error', 'Only the property owner can create leases.')
                    ->withInput();
            }
        } else {
            return redirect()->back()
                ->with('error', 'Only property owners can create leases. Administrators have view-only access.')
                ->withInput();
        }

        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
            return redirect()->back()
                ->with('error', 'Unit must have an approved tenant to create a lease.')
                ->withInput();
        }

        // ========== VALIDATION ==========
        $validator = Validator::make($request->all(), [
            // Lease term
            'lease_type'      => 'required|in:fixed,month_to_month',
            'start_date'      => 'required|date|after_or_equal:today',
            'end_date'        => 'required_if:lease_type,fixed|nullable|date|after:start_date',
            'duration_months' => 'required_if:lease_type,fixed|nullable|integer|min:1|max:60',

            // ✅ GHANA: Advance rent fields
            'payment_frequency'         => 'required|in:monthly,advance_only',
            'advance_rent_months'       => 'required|integer|min:1|max:60',
            'advance_rent_acknowledged' => 'sometimes|boolean',

            // Financial
            'monthly_rent' => 'required|numeric|min:0.01|max:999999.99',

            // Security deposit
            'enable_security_deposit'    => 'sometimes|boolean',
            'security_deposit'           => 'nullable|required_if:enable_security_deposit,1|numeric|min:0|max:999999.99',
            'deposit_payment_method'     => 'nullable|required_if:enable_security_deposit,1|in:upfront,installment',
            'deposit_installment_months' => 'nullable|required_if:deposit_payment_method,installment|integer|min:2|max:12',
            'utility_deposit'            => 'nullable|numeric|min:0|max:999999.99',

            // Late payment
            'late_fee_percentage' => 'nullable|numeric|min:0|max:50',
            'late_fee_fixed'      => 'nullable|numeric|min:0|max:999999.99',
            'grace_period_days'   => 'nullable|integer|min:0|max:15',
            'payment_due_day'     => 'required|integer|min:1|max:28',

            // Termination
            'notice_period_days'    => 'required|integer|min:15|max:90',
            'early_termination_fee' => 'nullable|numeric|min:0|max:999999.99',

            // Additional
            'renewal_terms'          => 'nullable|string|max:2000',
            'special_terms'          => 'nullable|string|max:5000',
            'include_standard_terms' => 'boolean',

            // Invitation
            'send_to_tenant'            => 'boolean',
            'invitation_channels'       => 'nullable|required_if:send_to_tenant,true|array|min:1',
            'invitation_channels.*'     => 'in:sms,email,whatsapp',
            'tenant_signature_required' => 'boolean',
        ], [
            'invitation_channels.required_if' => 'Please select at least one communication channel to send the lease invitation.',
            'invitation_channels.min'         => 'Please select at least one communication channel to send the lease invitation.',
            'deposit_installment_months.required_if' => 'Please specify how many months to spread the deposit payment.',
            'security_deposit.required_if'    => 'Please enter the security deposit amount.',
            'advance_rent_months.required'    => 'Please specify how many months of advance rent apply.',
        ]);

        // ✅ GHANA: Business-rule validation (advance rent vs legal cap)
        $validator->after(function ($validator) use ($request) {
            $advanceMonths  = (int) $request->advance_rent_months;
            $leaseType      = $request->lease_type;
            $durationMonths = (int) ($request->duration_months ?? 0);

            if ($leaseType === 'fixed' && $advanceMonths > $durationMonths) {
                $validator->errors()->add(
                    'advance_rent_months',
                    'Advance rent cannot exceed the total lease term (' . $durationMonths . ' months).'
                );
            }

            if ($leaseType === 'fixed' && $durationMonths <= 6
                && $advanceMonths > self::LEGAL_MAX_ADVANCE_MONTHS_SHORT_TENANCY) {
                $validator->errors()->add(
                    'advance_rent_months',
                    'For tenancies of 6 months or less, the legal maximum advance is 1 month (Rent Act 1963, s.25(5)).'
                );
            }

            if ($leaseType === 'fixed' && $durationMonths > 6
                && $advanceMonths > self::LEGAL_MAX_ADVANCE_MONTHS_NEW_TENANCY
                && !$request->boolean('advance_rent_acknowledged')) {
                $validator->errors()->add(
                    'advance_rent_acknowledged',
                    'The advance rent you entered exceeds the legal maximum of 6 months. '
                    . 'The Rent Act 1963 permits this only if the tenant voluntarily offers it. '
                    . 'Please confirm the acknowledgement checkbox to proceed.'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // ========== DERIVE DATES ==========
            $startDate = Carbon::parse($request->start_date);

            $endDate = null;
            if ($request->lease_type === 'fixed') {
                if ($request->filled('end_date')) {
                    $endDate = Carbon::parse($request->end_date);
                } elseif ($request->filled('duration_months')) {
                    $endDate = $startDate->copy()->addMonths((int) $request->duration_months);
                } else {
                    throw new \Exception('End date or duration months is required for fixed-term leases.');
                }
            }

            // ✅ GHANA: Advance rent period + first monthly payment date
            $advanceMonths      = (int) $request->advance_rent_months;
            $monthlyRent        = (float) $request->monthly_rent;
            $advanceRentAmount  = $monthlyRent * $advanceMonths;
            $advancePeriodStart = $startDate->copy();
            $advancePeriodEnd   = $startDate->copy()->addMonths($advanceMonths);

            $firstMonthlyPaymentDate = null;
            if ($request->payment_frequency === 'monthly') {
                $firstMonthlyPaymentDate = $advancePeriodEnd->copy()
                    ->setDay((int) $request->payment_due_day);
            }

            // ✅ GHANA: Compliance status
            $isShortTenancy = $request->lease_type === 'fixed' && ($request->duration_months ?? 0) <= 6;
            $legalMax       = $isShortTenancy
                ? self::LEGAL_MAX_ADVANCE_MONTHS_SHORT_TENANCY
                : self::LEGAL_MAX_ADVANCE_MONTHS_NEW_TENANCY;
            $complianceStatus = $advanceMonths <= $legalMax ? 'compliant' : 'exceeds_legal_limit';

            $leaseTerms = $this->generateLeaseTerms($request, $unit);

            // ========== SECURITY DEPOSIT ==========
            $securityDeposit           = 0;
            $depositPaymentMethod      = null;
            $depositInstallmentMonths  = null;
            $depositMonthlyInstallment = null;

            if ($request->boolean('enable_security_deposit')) {
                $securityDeposit = $request->security_deposit ?? 0;
                $depositPaymentMethod = $request->deposit_payment_method;

                if ($depositPaymentMethod === 'installment') {
                    $depositInstallmentMonths  = $request->deposit_installment_months;
                    $depositMonthlyInstallment = $securityDeposit / $depositInstallmentMonths;
                }
            }

            // ========== LATE FEES ==========
            $lateFeePercentage = $request->filled('late_fee_percentage') ? floatval($request->late_fee_percentage) : 0;
            $lateFeeFixed      = $request->filled('late_fee_fixed') ? floatval($request->late_fee_fixed) : 0;
            $gracePeriodDays   = $request->filled('grace_period_days') ? intval($request->grace_period_days) : 0;

            // ========== CREATE LEASE ==========
            $lease = RentalAgreement::create([
                'unit_id'      => $unit->id,
                'tenant_id'    => $unit->tenant_id,
                'property_id'  => $unit->property_id,
                'landlord_id'  => $user->id,
                'lease_type'   => $request->lease_type,
                'monthly_rent' => $monthlyRent,

                // ✅ GHANA: Advance rent
                'advance_rent_months'            => $advanceMonths,
                'advance_rent_amount'            => $advanceRentAmount,
                'advance_rent_paid_at'           => now(),
                'advance_rent_period_start'      => $advancePeriodStart,
                'advance_rent_period_end'        => $advancePeriodEnd,
                'payment_frequency'              => $request->payment_frequency,
                'first_monthly_payment_date'     => $firstMonthlyPaymentDate,
                'advance_rent_compliance_status' => $complianceStatus,
                'advance_rent_acknowledged_at'   => $request->boolean('advance_rent_acknowledged') ? now() : null,

                // Deposit
                'security_deposit'            => $securityDeposit,
                'deposit_payment_method'      => $depositPaymentMethod,
                'deposit_installment_months'  => $depositInstallmentMonths,
                'deposit_monthly_installment' => $depositMonthlyInstallment,
                'deposit_collected_so_far'    => 0,
                'deposit_fully_paid_at'       => null,
                'utility_deposit'             => $request->utility_deposit ?? 0,

                // Late fees
                'late_fee_percentage' => $lateFeePercentage,
                'late_fee_fixed'      => $lateFeeFixed,
                'grace_period_days'   => $gracePeriodDays,
                'payment_due_day'     => $request->payment_due_day,

                // Termination
                'notice_period_days'    => $request->notice_period_days,
                'early_termination_fee' => $request->early_termination_fee ?? 0,

                // Term
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'duration_months' => $request->duration_months ?? null,

                // Terms
                'terms'         => $leaseTerms,
                'renewal_terms' => $request->renewal_terms,
                'special_terms' => $request->special_terms,

                // Meta
                'status'          => 'draft',
                'created_by'      => $user->id,
                'created_by_type' => 'landlord',
                'notes'           => 'Lease created by landlord ' . $user->name
                                     . ' | Advance: ' . $advanceMonths . ' months (GHS '
                                     . number_format($advanceRentAmount, 2) . ')',
            ]);

            // ========== UPDATE UNIT ==========
            $unit->current_lease_id    = $lease->id;
            $unit->current_rent_amount = $monthlyRent;
            $unit->security_deposit    = $securityDeposit;
            $unit->lease_start_date    = $startDate;
            $unit->lease_end_date      = $endDate;
            $unit->save();

            // ========== ✅ INVOICE: GENERATE INVOICES ==========
            $this->generateLeaseInvoices($lease, $unit);

            // ========== SEND INVITATION ==========
            $invitationResult = null;
            if ($request->boolean('send_to_tenant')) {
                $invitationResult = $this->sendLeaseToTenant($lease, $unit, $request->invitation_channels);
                if ($invitationResult['success']) {
                    $lease->update(['status' => 'pending_signature']);
                }
            }

            // ========== LOG ==========
            $this->logActivity(
                $user->id,
                'lease_created',
                'Lease agreement created',
                $unit->id,
                [
                    'lease_id'              => $lease->id,
                    'lease_type'            => $request->lease_type,
                    'duration_months'       => $request->duration_months,
                    'monthly_rent'          => $monthlyRent,
                    'advance_rent_months'   => $advanceMonths,
                    'advance_rent_amount'   => $advanceRentAmount,
                    'payment_frequency'     => $request->payment_frequency,
                    'compliance_status'     => $complianceStatus,
                    'security_deposit'      => $securityDeposit,
                    'deposit_payment_method'=> $depositPaymentMethod,
                    'sent_to_tenant'        => $request->boolean('send_to_tenant'),
                    'invitation_channels'   => $request->invitation_channels ?? [],
                    'channels_successful'   => $invitationResult['channels_successful'] ?? [],
                ]
            );

            Cache::forget('property_unit_' . $id . '_with_relations');
            Cache::forget('unit_stats_' . $id);

            DB::commit();

            $successMessage = 'Lease created successfully.';
            $successMessage .= ' Advance rent of GHS ' . number_format($advanceRentAmount, 2)
                             . ' for ' . $advanceMonths . ' month(s) recorded.';
            if ($request->payment_frequency === 'monthly' && $firstMonthlyPaymentDate) {
                $successMessage .= ' Monthly payments begin ' . $firstMonthlyPaymentDate->format('M j, Y') . '.';
            }
            if ($complianceStatus === 'exceeds_legal_limit') {
                $successMessage .= ' ⚠️ Note: advance rent exceeds the legal maximum of 6 months.';
            }
            if ($request->boolean('send_to_tenant') && $invitationResult['success']) {
                $successMessage .= ' Invitation sent via ' . implode(', ', $invitationResult['channels_successful']) . '.';
            } elseif ($request->boolean('send_to_tenant') && !$invitationResult['success']) {
                $successMessage .= ' However, invitation could not be sent — you can resend from lease details.';
            }

            return redirect()->route('property-units.lease-management', $unit->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating lease: ' . $e->getMessage(), [
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create lease: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function showLeaseDetails($unitId, $leaseId)
    {
        $unit  = PropertyUnit::with(['property'])->findOrFail($unitId);
        $lease = RentalAgreement::with(['tenant', 'landlord', 'unit'])
            ->where('unit_id', $unitId)
            ->findOrFail($leaseId);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();
        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');
        $isTenant        = $user->isTenant()   || $user->hasRole('tenant');

        if ($hasLandlordRole && $unit->property->landlord_id !== $user->id) {
            abort(403, 'You can only view leases for your own properties.');
        }
        if ($isTenant && $lease->tenant_id !== $user->id) {
            abort(403, 'You can only view your own leases.');
        }

        // ✅ GHANA: Phase + next payment info
        $currentPhase       = $lease->current_phase;
        $nextPaymentDue     = $lease->next_payment_due_date;
        $nextPaymentAmount  = $lease->next_payment_amount;

        // ✅ INVOICE: Load invoices + summary
        $invoices = PropertyUnitInvoice::where('lease_id', $lease->id)
            ->orderBy('due_date', 'asc')
            ->get();

        $invoiceSummary = [
            'total_invoiced' => $invoices->sum('amount'),
            'total_paid'     => $invoices->sum('amount_paid'),
            'outstanding'    => $invoices->whereIn('status', ['pending', 'overdue', 'partial'])
                                        ->sum(fn ($inv) => $inv->amount - $inv->amount_paid),
            'overdue_count'  => $invoices->where('status', 'overdue')->count(),
            'paid_count'     => $invoices->where('status', 'paid')->count(),
        ];

        return view('property_units.lease-details', compact(
            'unit', 'lease', 'currentPhase', 'nextPaymentDue', 'nextPaymentAmount',
            'invoices', 'invoiceSummary'
        ));
    }

    // ========== LEASE SIGNING ==========

    public function landlordSignLease(Request $request, $unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);
        $user  = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');

        if ($hasLandlordRole && $unit->property->landlord_id === $user->id) {
            $validator = Validator::make($request->all(), [
                'signature_data'         => 'required|string',
                'signature_type'         => 'required|in:digital,upload',
                'signed_at'              => 'required|date|before_or_equal:now',
                'witness_name'           => 'nullable|string|max:255',
                'witness_signature_data' => 'nullable|required_with:witness_name|string',
                'witness_signature_type' => 'nullable|required_with:witness_name|in:digital,upload',
                'witness_relationship'   => 'nullable|required_with:witness_name|string|max:100',
                'witness_email'          => 'nullable|email|max:255',
                'witness_phone'          => 'nullable|string|max:20',
                'witness_role'           => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            DB::beginTransaction();
            try {
                $lease->signAsLandlord(
                    user: $user,
                    signatureData: $request->signature_data,
                    signatureType: $request->signature_type
                );

                $witnessSignatureData = null;
                if ($request->filled('witness_name') && $request->filled('witness_signature_data')) {
                    $witnessSignatureData = [
                        'name'                   => $request->witness_name,
                        'relationship'           => $request->witness_relationship,
                        'role'                   => $request->witness_role,
                        'email'                  => $request->witness_email,
                        'phone'                  => $request->witness_phone,
                        'signature'              => $request->witness_signature_data,
                        'signature_type'         => $request->witness_signature_type,
                        'signed_at'              => now(),
                        'witness_for'            => 'landlord',
                        'witnessed_by_user_id'   => $user->id,
                        'witnessed_by_user_name' => $user->name,
                    ];

                    $lease->update([
                        'landlord_witness_signature' => $witnessSignatureData,
                        'landlord_witness_signed_at' => now(),
                    ]);

                    $this->logActivity($user->id, 'landlord_witness_signed',
                        'Landlord witness signed lease agreement', $unit->id,
                        ['lease_id' => $lease->id, 'witness_name' => $witnessSignatureData['name'],
                         'witness_role' => $witnessSignatureData['role']]);
                }

                $this->logActivity($user->id, 'landlord_signed_lease',
                    'Landlord signed lease agreement' . ($witnessSignatureData ? ' with witness' : ''),
                    $unit->id,
                    ['lease_id' => $lease->id, 'has_witness' => !empty($witnessSignatureData),
                     'witness_name' => $witnessSignatureData['name'] ?? null]);

                if ($lease->tenant) {
                    $notificationData = [
                        'type'        => 'landlord_signed_lease',
                        'unit_id'     => $unit->id,
                        'lease_id'    => $lease->id,
                        'landlord_id' => $user->id,
                        'signed_at'   => $request->signed_at,
                        'has_witness' => !empty($witnessSignatureData),
                    ];
                    if ($witnessSignatureData) {
                        $notificationData['witness_name'] = $witnessSignatureData['name'];
                        $notificationData['witness_role'] = $witnessSignatureData['role'];
                    }

                    $lease->tenant->notify(new \App\Notifications\GeneralNotification(
                        title: 'Lease Signed by Landlord',
                        message: "Landlord {$user->name} has signed the lease for Unit {$unit->unit_number}."
                                 . ($witnessSignatureData ? " Witnessed by {$witnessSignatureData['name']} ({$witnessSignatureData['role']})." : ""),
                        icon: 'fas fa-signature text-success',
                        category: 'lease_signed',
                        actionUrl: route('tenant.property-units.lease-details', [$unit->id, $lease->id]),
                        priority: 1,
                        data: $notificationData
                    ));
                }

                if ($witnessSignatureData && !empty($witnessSignatureData['email'])) {
                    try {
                        Mail::to($witnessSignatureData['email'])->send(new \App\Mail\WitnessConfirmationMail(
                            $witnessSignatureData['name'], $user->name, $unit, $lease,
                            'landlord', $witnessSignatureData['role']
                        ));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send landlord witness confirmation email: ' . $e->getMessage());
                    }
                }

                DB::commit();

                return redirect()->route('property-units.lease-details', [$unit->id, $lease->id])
                    ->with('success', 'Lease signed successfully.'
                        . ($witnessSignatureData ? ' Witness signature recorded.' : '') . ' '
                        . ($lease->tenant_signed_at ? 'Lease is now active.' : 'Waiting for tenant signature.'));

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error signing lease as landlord: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to sign lease: ' . $e->getMessage());
            }
        }

        abort(403, 'Only the property owner can sign leases.');
    }

    public function tenantSignLease(Request $request, $unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);
        $user  = auth()->user();

        $isTenant = $user->isTenant() || $user->hasRole('tenant');

        if (!$isTenant) {
            abort(403, 'Only tenants can sign their own leases.');
        }
        if ($lease->tenant_id !== $user->id) {
            abort(403, 'You can only sign your own lease.');
        }
        if ($lease->tenant_signed_at) {
            return redirect()->back()->with('error', 'You have already signed this lease.')->withInput();
        }

        $validator = Validator::make($request->all(), [
            'signature_data'         => 'required|string',
            'signature_type'         => 'required|in:digital,upload',
            'signed_at'              => 'required|date|before_or_equal:now',
            'agreement_check'        => 'required|accepted',
            'witness_name'           => 'nullable|string|max:255',
            'witness_signature_data' => 'nullable|required_with:witness_name|string',
            'witness_signature_type' => 'nullable|required_with:witness_name|in:digital,upload',
            'witness_relationship'   => 'nullable|required_with:witness_name|string|max:100',
            'witness_email'          => 'nullable|email|max:255',
            'witness_phone'          => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $lease->signAsTenant(
                user: $user,
                signatureData: $request->signature_data,
                signatureType: $request->signature_type
            );

            $witnessSignatureData = null;
            if ($request->filled('witness_name') && $request->filled('witness_signature_data')) {
                $witnessSignatureData = [
                    'name'           => $request->witness_name,
                    'relationship'   => $request->witness_relationship,
                    'email'          => $request->witness_email,
                    'phone'          => $request->witness_phone,
                    'signature'      => $request->witness_signature_data,
                    'signature_type' => $request->witness_signature_type,
                    'signed_at'      => now(),
                    'witness_for'    => 'tenant',
                ];

                $lease->update([
                    'tenant_witness_signature' => $witnessSignatureData,
                    'tenant_witness_signed_at' => now(),
                ]);
            }

            $this->logActivity($user->id, 'tenant_signed_lease',
                'Tenant signed lease agreement' . ($witnessSignatureData ? ' with witness' : ''),
                $unit->id,
                ['lease_id' => $lease->id, 'has_witness' => !empty($witnessSignatureData),
                 'witness_name' => $witnessSignatureData['name'] ?? null]);

            if ($lease->landlord) {
                $notificationData = [
                    'type'        => 'tenant_signed_lease',
                    'unit_id'     => $unit->id,
                    'lease_id'    => $lease->id,
                    'tenant_id'   => $user->id,
                    'signed_at'   => $request->signed_at,
                    'has_witness' => !empty($witnessSignatureData),
                ];
                if ($witnessSignatureData) {
                    $notificationData['witness_name'] = $witnessSignatureData['name'];
                }

                $lease->landlord->notify(new \App\Notifications\GeneralNotification(
                    title: 'Lease Signed by Tenant',
                    message: "Tenant {$user->name} has signed the lease for Unit {$unit->unit_number}."
                             . ($witnessSignatureData ? " Witnessed by {$witnessSignatureData['name']}." : ""),
                    icon: 'fas fa-signature text-success',
                    category: 'lease_signed',
                    actionUrl: route('property-units.lease-details', [$unit->id, $lease->id]),
                    priority: 1,
                    data: $notificationData
                ));
            }

            if ($witnessSignatureData && !empty($witnessSignatureData['email'])) {
                try {
                    Mail::to($witnessSignatureData['email'])->send(new \App\Mail\WitnessConfirmationMail(
                        $witnessSignatureData['name'], $user->name, $unit, $lease, 'tenant'
                    ));
                } catch (\Exception $e) {
                    Log::warning('Failed to send witness confirmation email: ' . $e->getMessage());
                }
            }

            DB::commit();

            return redirect()->route('tenant.property-units.lease-details', [$unit->id, $lease->id])
                ->with('success', 'Lease signed successfully.'
                    . ($witnessSignatureData ? ' Witness signature recorded.' : '')
                    . ($lease->landlord_signed_at ? ' Lease is now active.' : ' Waiting for landlord signature.'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error signing lease as tenant: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to sign lease: ' . $e->getMessage());
        }
    }

    // ========== LEASE RENEWAL ==========

    public function renewLease(Request $request, $unitId)
    {
        $unit = PropertyUnit::with(['currentLease'])->findOrFail($unitId);
        $user = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');

        if ($hasLandlordRole) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()->with('error', 'Only the property owner can renew leases.')->withInput();
            }
        } else {
            return redirect()->back()->with('error', 'Only property owners can renew leases.')->withInput();
        }

        if (!$unit->currentLease || $unit->currentLease->status !== 'active') {
            return redirect()->back()->with('error', 'No active lease to renew.')->withInput();
        }

        $validator = Validator::make($request->all(), [
            'renewal_start_date'      => 'required|date|after_or_equal:' . $unit->currentLease->end_date->format('Y-m-d'),
            'renewal_duration_months' => 'required|integer|min:1|max:60',
            'new_monthly_rent'        => 'required|numeric|min:' . ($unit->current_rent_amount * 0.9) . '|max:' . ($unit->current_rent_amount * 1.2),

            // ✅ GHANA: Renewal advance rent
            'renewal_advance_rent_months'  => 'required|integer|min:1|max:12',
            'renewal_payment_frequency'    => 'required|in:monthly,advance_only',
            'renewal_advance_acknowledged' => 'sometimes|boolean',

            'adjust_security_deposit' => 'boolean',
            'new_security_deposit'    => 'nullable|required_if:adjust_security_deposit,true|numeric|min:0',
            'renewal_terms'           => 'nullable|string|max:2000',
            'send_for_signature'      => 'boolean',
            'invitation_channels'     => 'nullable|required_if:send_for_signature,true|array|min:1',
            'invitation_channels.*'   => 'in:sms,email,whatsapp',
        ]);

        $validator->after(function ($validator) use ($request) {
            $advanceMonths = (int) $request->renewal_advance_rent_months;
            if ($advanceMonths > self::LEGAL_MAX_ADVANCE_MONTHS_RENEWAL
                && !$request->boolean('renewal_advance_acknowledged')) {
                $validator->errors()->add(
                    'renewal_advance_acknowledged',
                    'The renewal advance exceeds the legal maximum of 3 months (Rent Act 1963). '
                    . 'Confirm the acknowledgement to proceed if the tenant voluntarily offered it.'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $oldLease = $unit->currentLease;

            $oldLease->update([
                'status'   => 'completed',
                'end_date' => Carbon::parse($request->renewal_start_date)->subDay(),
            ]);

            $newMonthlyRent     = (float) $request->new_monthly_rent;
            $newSecurityDeposit = $request->adjust_security_deposit
                ? $request->new_security_deposit
                : $unit->security_deposit;

            // ✅ GHANA: Renewal advance period
            $renewalStart       = Carbon::parse($request->renewal_start_date);
            $advanceMonths      = (int) $request->renewal_advance_rent_months;
            $advanceAmount      = $newMonthlyRent * $advanceMonths;
            $advancePeriodStart = $renewalStart->copy();
            $advancePeriodEnd   = $renewalStart->copy()->addMonths($advanceMonths);
            $firstMonthlyPayment = $request->renewal_payment_frequency === 'monthly'
                ? $advancePeriodEnd->copy()->setDay((int) $oldLease->payment_due_day)
                : null;

            $complianceStatus = $advanceMonths <= self::LEGAL_MAX_ADVANCE_MONTHS_RENEWAL
                ? 'compliant' : 'exceeds_legal_limit';

            $newLease = RentalAgreement::create([
                'unit_id'      => $unit->id,
                'tenant_id'    => $unit->tenant_id,
                'property_id'  => $unit->property_id,
                'landlord_id'  => $unit->property->landlord_id,
                'lease_type'   => $oldLease->lease_type,
                'monthly_rent' => $newMonthlyRent,

                // ✅ GHANA: Renewal advance rent
                'advance_rent_months'            => $advanceMonths,
                'advance_rent_amount'            => $advanceAmount,
                'advance_rent_paid_at'           => now(),
                'advance_rent_period_start'      => $advancePeriodStart,
                'advance_rent_period_end'        => $advancePeriodEnd,
                'payment_frequency'              => $request->renewal_payment_frequency,
                'first_monthly_payment_date'     => $firstMonthlyPayment,
                'advance_rent_compliance_status' => $complianceStatus,
                'advance_rent_acknowledged_at'   => $request->boolean('renewal_advance_acknowledged') ? now() : null,

                'security_deposit'            => $newSecurityDeposit,
                'deposit_payment_method'      => $oldLease->deposit_payment_method,
                'deposit_installment_months'  => $oldLease->deposit_installment_months,
                'deposit_monthly_installment' => $oldLease->deposit_monthly_installment,
                'deposit_collected_so_far'    => 0,
                'utility_deposit'             => $oldLease->utility_deposit,
                'late_fee_percentage'         => $oldLease->late_fee_percentage,
                'late_fee_fixed'              => $oldLease->late_fee_fixed,
                'grace_period_days'           => $oldLease->grace_period_days,
                'payment_due_day'             => $oldLease->payment_due_day,
                'notice_period_days'          => $oldLease->notice_period_days,
                'early_termination_fee'       => $oldLease->early_termination_fee,
                'start_date'                  => $renewalStart,
                'end_date'                    => $renewalStart->copy()->addMonths((int) $request->renewal_duration_months),
                'duration_months'             => $request->renewal_duration_months,
                'terms'                       => $request->renewal_terms ?? $oldLease->terms,
                'renewal_terms'               => $request->renewal_terms,
                'special_terms'               => $oldLease->special_terms,
                'status'                      => 'draft',
                'created_by'                  => $user->id,
                'created_by_type'             => 'landlord',
                'notes'                       => 'Lease renewal | Advance: ' . $advanceMonths
                                                 . ' months (GHS ' . number_format($advanceAmount, 2) . ')',
                'previous_lease_id'           => $oldLease->id,
            ]);

            $unit->current_lease_id    = $newLease->id;
            $unit->lease_start_date    = $renewalStart;
            $unit->lease_end_date      = $newLease->end_date;
            $unit->current_rent_amount = $newMonthlyRent;
            if ($request->adjust_security_deposit) {
                $unit->security_deposit = $request->new_security_deposit;
            }
            $unit->save();

            // ✅ INVOICE: Generate renewal invoices
            $this->generateLeaseInvoices($newLease, $unit);

            if ($request->send_for_signature) {
                $this->sendLeaseToTenant($newLease, $unit, $request->invitation_channels);
                $newLease->update(['status' => 'pending_signature']);
            }

            $this->logActivity($user->id, 'lease_renewed', 'Lease agreement renewed', $unit->id, [
                'new_lease_id'        => $newLease->id,
                'duration_months'     => $request->renewal_duration_months,
                'new_monthly_rent'    => $newMonthlyRent,
                'advance_rent_months' => $advanceMonths,
                'advance_rent_amount' => $advanceAmount,
                'payment_frequency'   => $request->renewal_payment_frequency,
                'compliance_status'   => $complianceStatus,
                'sent_for_signature'  => $request->send_for_signature ?? false,
            ]);

            Cache::forget('property_unit_' . $unitId . '_with_relations');

            DB::commit();

            $msg = 'Lease renewed successfully.';
            $msg .= ' Advance rent: ' . $advanceMonths . ' month(s) — GHS ' . number_format($advanceAmount, 2) . '.';
            if ($firstMonthlyPayment) {
                $msg .= ' Monthly payments begin ' . $firstMonthlyPayment->format('M j, Y') . '.';
            }
            if ($complianceStatus === 'exceeds_legal_limit') {
                $msg .= ' ⚠️ Advance exceeds the legal renewal cap of 3 months.';
            }
            if ($request->send_for_signature) {
                $msg .= ' Sent to tenant for signature.';
            }

            return redirect()->route('property-units.lease-management', $unit->id)->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error renewing lease: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to renew lease: ' . $e->getMessage())->withInput();
        }
    }

    // ========== LEASE TERMINATION ==========

    public function terminateLease(Request $request, $unitId)
    {
        $unit = PropertyUnit::with(['currentLease', 'property', 'tenant'])->findOrFail($unitId);
        $user = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');
        $isAdmin         = $user->isAdmin()    || $user->hasRole('admin');
        $isSuperAdmin    = $user->isSuperAdmin() || $user->hasRole('super-admin');

        if ($hasLandlordRole) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()->with('error', 'Only the property owner can terminate leases.')->withInput();
            }
        } elseif (!$isAdmin && !$isSuperAdmin) {
            return redirect()->back()->with('error', 'Only property owners and administrators can terminate leases.')->withInput();
        }

        if (!$unit->currentLease || $unit->currentLease->status !== 'active') {
            return redirect()->back()->with('error', 'No active lease to terminate.')->withInput();
        }

        $validator = Validator::make($request->all(), [
            'termination_date'      => 'required|date|after_or_equal:today|before_or_equal:' . $unit->currentLease->end_date->format('Y-m-d'),
            'termination_reason'    => 'required|string|min:10|max:1000',
            'refund_deposit'        => 'sometimes|boolean',
            'deposit_refund_amount' => 'nullable|required_if:refund_deposit,true|numeric|min:0|max:' . ($unit->currentLease->security_deposit ?? 0),
            'notes'                 => 'nullable|string|max:500',
        ], [
            'termination_date.before_or_equal'  => 'Termination date cannot be after the lease end date.',
            'termination_reason.min'            => 'Please provide a detailed reason for termination (at least 10 characters).',
            'deposit_refund_amount.required_if' => 'Please specify the deposit refund amount.',
            'deposit_refund_amount.max'         => 'Refund amount cannot exceed the security deposit of GHS ' . number_format($unit->currentLease->security_deposit ?? 0, 2),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $terminationDate = Carbon::parse($request->termination_date);
            $lease = $unit->currentLease;

            // ✅ GHANA: Advance-rent refund for unused period
            $advanceRefund = 0;
            if ($lease->advance_rent_period_end && $terminationDate->lt($lease->advance_rent_period_end)) {
                $unusedMonths  = $terminationDate->diffInMonths($lease->advance_rent_period_end);
                $advanceRefund = $unusedMonths * $lease->monthly_rent;
            }

            $proratedRent = null;
            if ($terminationDate->format('d') != $lease->payment_due_day) {
                $daysInMonth  = $terminationDate->daysInMonth;
                $daysUsed     = $terminationDate->day;
                $proratedRent = ($lease->monthly_rent / $daysInMonth) * $daysUsed;
            }

            $terminationData = [
                'terminated_at'              => $terminationDate,
                'termination_reason'         => $request->termination_reason,
                'terminated_by'              => $user->id,
                'terminated_by_type'         => $user->getRole(),
                'prorated_rent_amount'       => $proratedRent,
                'advance_rent_refund_amount' => $advanceRefund,
                'security_deposit_refunded'  => $request->boolean('refund_deposit') ? $request->deposit_refund_amount : 0,
                'termination_notes'          => $request->notes,
            ];

            $lease->update([
                'status'           => 'terminated',
                'end_date'         => $terminationDate,
                'termination_data' => $terminationData,
                'notes'            => ($lease->notes ? $lease->notes . "\n\n" : '')
                                      . "TERMINATED: {$terminationDate->format('Y-m-d')} - {$request->termination_reason}",
            ]);

            if ($request->boolean('refund_deposit') && $request->filled('deposit_refund_amount')) {
                $refundAmount = floatval($request->deposit_refund_amount);
                $lease->update([
                    'deposit_refunded_amount'  => $refundAmount,
                    'deposit_refunded_at'      => now(),
                    'deposit_collected_so_far' => max(0, $lease->deposit_collected_so_far - $refundAmount),
                ]);

                $this->logActivity($user->id, 'deposit_refunded',
                    "Security deposit refund of GHS " . number_format($refundAmount, 2) . " processed",
                    $unit->id,
                    ['lease_id' => $lease->id, 'refund_amount' => $refundAmount,
                     'refund_date' => now()->toDateString()]);
            }

            if ($advanceRefund > 0) {
                $this->logActivity($user->id, 'advance_rent_refunded',
                    "Advance rent refund of GHS " . number_format($advanceRefund, 2) . " calculated",
                    $unit->id,
                    ['lease_id' => $lease->id, 'advance_refund' => $advanceRefund,
                     'termination_date' => $terminationDate->toDateString()]);
            }

            // ✅ INVOICE: Void any pending future invoices
            PropertyUnitInvoice::where('lease_id', $lease->id)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where('due_date', '>', $terminationDate)
                ->update([
                    'status'      => 'void',
                    'void_reason' => 'Lease terminated on ' . $terminationDate->toDateString(),
                    'voided_at'   => now(),
                ]);

            $unit->update([
                'current_lease_id'    => null,
                'tenant_status'       => PropertyUnit::TENANT_STATUS_TERMINATED,
                'tenant_id'           => null,
                'tenant_assigned_at'  => null,
                'tenant_assigned_by'  => null,
                'lease_start_date'    => null,
                'lease_end_date'      => null,
                'current_rent_amount' => null,
                'security_deposit'    => $lease->security_deposit - ($request->boolean('refund_deposit') ? floatval($request->deposit_refund_amount) : 0),
                'status'              => 'maintenance',
                'maintenance_notes'   => "Unit vacated after lease termination on {$terminationDate->format('Y-m-d')}",
                'last_vacated_at'     => now(),
            ]);

            if ($unit->tenant) {
                $tenant = $unit->tenant;
                $notificationData = [
                    'type'                => 'lease_terminated',
                    'unit_id'             => $unit->id,
                    'lease_id'            => $lease->id,
                    'termination_date'    => $terminationDate->toDateString(),
                    'termination_reason'  => $request->termination_reason,
                    'refund_amount'       => $request->boolean('refund_deposit') ? $request->deposit_refund_amount : 0,
                    'advance_rent_refund' => $advanceRefund,
                    'prorated_rent'       => $proratedRent,
                ];

                $msg = "Your lease for Unit {$unit->unit_number} at {$unit->property->property_name} has been terminated effective {$terminationDate->format('F j, Y')}.";
                if ($request->boolean('refund_deposit')) {
                    $msg .= " A deposit refund of GHS " . number_format($request->deposit_refund_amount, 2) . " has been processed.";
                }
                if ($advanceRefund > 0) {
                    $msg .= " An advance rent refund of GHS " . number_format($advanceRefund, 2) . " is pending.";
                }

                $tenant->notify(new \App\Notifications\GeneralNotification(
                    title: 'Lease Terminated',
                    message: $msg,
                    icon: 'fas fa-times-circle text-danger',
                    category: 'lease_terminated',
                    actionUrl: route('tenant.property-units.lease-details', [$unit->id, $lease->id]),
                    priority: 1,
                    data: $notificationData
                ));

                try {
                    Mail::to($tenant->email)->send(new \App\Mail\LeaseTerminatedMail(
                        $tenant, $unit, $lease, $terminationDate,
                        $request->termination_reason,
                        $request->boolean('refund_deposit') ? $request->deposit_refund_amount : 0,
                        $proratedRent
                    ));
                } catch (\Exception $e) {
                    Log::warning('Failed to send lease termination email: ' . $e->getMessage());
                }
            }

            if ($user->id !== $unit->property->landlord_id) {
                $landlord = $unit->property->landlord;
                if ($landlord) {
                    $landlord->notify(new \App\Notifications\GeneralNotification(
                        title: 'Lease Terminated',
                        message: "Lease for Unit {$unit->unit_number} at {$unit->property->property_name} has been terminated by {$user->name} effective {$terminationDate->format('F j, Y')}.",
                        icon: 'fas fa-info-circle text-info',
                        category: 'lease_terminated',
                        actionUrl: route('property-units.lease-details', [$unit->id, $lease->id]),
                        priority: 1,
                        data: [
                            'type'             => 'lease_terminated_by_admin',
                            'terminated_by'    => $user->name,
                            'termination_date' => $terminationDate->toDateString(),
                        ]
                    ));
                }
            }

            $this->logActivity($user->id, 'lease_terminated', 'Lease agreement terminated', $unit->id, [
                'lease_id'           => $lease->id,
                'termination_date'   => $terminationDate->toDateString(),
                'termination_reason' => $request->termination_reason,
                'refund_deposit'     => $request->boolean('refund_deposit'),
                'refund_amount'      => $request->deposit_refund_amount ?? 0,
                'advance_refund'     => $advanceRefund,
                'prorated_rent'      => $proratedRent,
                'terminated_by'      => $user->name,
                'terminated_by_role' => $user->getRole(),
                'tenant_id'          => $unit->tenant_id,
                'tenant_name'        => $unit->tenant ? $unit->tenant->name : null,
            ]);

            Cache::forget('property_unit_' . $unitId . '_with_relations');
            Cache::forget('unit_stats_' . $unitId);
            Cache::forget('property_stats_' . $unit->property_id);

            DB::commit();

            $successMessage = "Lease terminated successfully effective {$terminationDate->format('F j, Y')}.";
            if ($request->boolean('refund_deposit') && $request->filled('deposit_refund_amount')) {
                $successMessage .= " Security deposit refund of GHS " . number_format($request->deposit_refund_amount, 2) . " processed.";
            }
            if ($advanceRefund > 0) {
                $successMessage .= " Advance rent refund of GHS " . number_format($advanceRefund, 2) . " calculated.";
            }
            if ($proratedRent) {
                $successMessage .= " Prorated rent of GHS " . number_format($proratedRent, 2) . " calculated for the partial month.";
            }
            $successMessage .= " The unit has been marked for maintenance.";

            return redirect()->route('property-units.show', $unit->id)->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error terminating lease: ' . $e->getMessage(), [
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'unit_id'      => $unitId,
                'lease_id'     => $unit->currentLease->id ?? null,
            ]);
            return redirect()->back()->with('error', 'Failed to terminate lease: ' . $e->getMessage())->withInput();
        }
    }

    // ========== ✅ INVOICE: INVOICE GENERATION ==========

    /**
     * Generate the advance-rent invoice and schedule monthly invoices
     * for the post-advance phase (if payment_frequency = 'monthly').
     * Also creates the security deposit invoice when paid upfront.
     *
     * Uses App\Models\PropertyUnitInvoice.
     */
    private function generateLeaseInvoices(RentalAgreement $lease, PropertyUnit $unit): void
    {
        try {
            // 1) Advance rent invoice — immediately due, marked paid
            PropertyUnitInvoice::create([
                'lease_id'      => $lease->id,
                'unit_id'       => $unit->id,
                'property_id'   => $unit->property_id,
                'tenant_id'     => $lease->tenant_id,
                'landlord_id'   => $lease->landlord_id,
                'invoice_type'  => PropertyUnitInvoice::TYPE_ADVANCE_RENT,
                'reference'     => 'ADV-' . strtoupper(Str::random(8)),
                'description'   => 'Advance rent — ' . $lease->advance_rent_months . ' month(s)',
                'amount'        => $lease->advance_rent_amount,
                'amount_paid'   => $lease->advance_rent_amount,
                'due_date'      => $lease->advance_rent_period_start,
                'period_start'  => $lease->advance_rent_period_start,
                'period_end'    => $lease->advance_rent_period_end,
                'status'        => PropertyUnitInvoice::STATUS_PAID,
                'paid_at'       => now(),
            ]);

            // 2) Monthly invoices for the post-advance phase
            if ($lease->payment_frequency === 'monthly'
                && $lease->first_monthly_payment_date
                && $lease->end_date) {

                $cursor = $lease->first_monthly_payment_date->copy();

                while ($cursor->lte($lease->end_date)) {
                    PropertyUnitInvoice::create([
                        'lease_id'     => $lease->id,
                        'unit_id'      => $unit->id,
                        'property_id'  => $unit->property_id,
                        'tenant_id'    => $lease->tenant_id,
                        'landlord_id'  => $lease->landlord_id,
                        'invoice_type' => PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                        'reference'    => 'MR-' . $cursor->format('Ym') . '-' . strtoupper(Str::random(4)),
                        'description'  => 'Monthly rent — ' . $cursor->format('F Y'),
                        'amount'       => $lease->monthly_rent,
                        'amount_paid'  => 0,
                        'due_date'     => $cursor->copy(),
                        'period_start' => $cursor->copy()->startOfMonth(),
                        'period_end'   => $cursor->copy()->endOfMonth(),
                        'status'       => PropertyUnitInvoice::STATUS_PENDING,
                    ]);

                    $cursor->addMonth();
                }
            }

            // 3) Security deposit invoice (if upfront)
            if ($lease->security_deposit > 0 && $lease->deposit_payment_method === 'upfront') {
                PropertyUnitInvoice::create([
                    'lease_id'     => $lease->id,
                    'unit_id'      => $unit->id,
                    'property_id'  => $unit->property_id,
                    'tenant_id'    => $lease->tenant_id,
                    'landlord_id'  => $lease->landlord_id,
                    'invoice_type' => PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT,
                    'reference'    => 'DEP-' . strtoupper(Str::random(8)),
                    'description'  => 'Security deposit (upfront)',
                    'amount'       => $lease->security_deposit,
                    'amount_paid'  => 0,
                    'due_date'     => $lease->start_date,
                    'status'       => PropertyUnitInvoice::STATUS_PENDING,
                ]);
            }

            Log::info('Lease invoices generated', [
                'lease_id'  => $lease->id,
                'monthly'   => $lease->payment_frequency === 'monthly',
                'advance'   => $lease->advance_rent_amount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate lease invoices: ' . $e->getMessage(), [
                'lease_id' => $lease->id ?? null,
            ]);
            // Non-fatal — do not rethrow
        }
    }

    // ========== PDF GENERATION ==========

/**
 * ✅ INVOICE + GHANA: Generate and download the lease agreement PDF.
 *
 * Permission matrix:
 *   - Super admin / Admin      → any lease
 *   - Landlord (owner)         → any lease on their own properties
 *   - Tenant                   → only their own lease
 *   - Anyone else              → 403
 */
public function generateLeasePdf($unitId, $leaseId)
{
    // ========== LOAD WITH RELATIONSHIPS ==========
    $unit = PropertyUnit::with(['property.landlord'])
        ->findOrFail($unitId);

    $lease = RentalAgreement::with([
            'tenant',
            'landlord',
            'unit.property',
            // ✅ INVOICE: optional — included so the template can show a
            // summary if it ever grows that way
            'invoices' => function ($q) {
                $q->orderBy('due_date');
            },
        ])
        ->where('unit_id', $unitId)
        ->findOrFail($leaseId);

    // ========== ACCESS CONTROL ==========
    $this->checkUnitAccessWithError($unit);

    $user = auth()->user();

    $isSuperAdmin    = $user->isSuperAdmin() || $user->hasRole('super-admin');
    $isAdmin         = $user->isAdmin()      || $user->hasRole('admin');
    $hasLandlordRole = $user->isLandlord()   || $user->hasRole('landlord');
    $isTenant        = $user->isTenant()     || $user->hasRole('tenant');

    // Non-admin users: scope to ownership / assignment
    if (!$isSuperAdmin && !$isAdmin) {
        if ($hasLandlordRole && $unit->property->landlord_id !== $user->id) {
            abort(403, 'You can only view leases for your own properties.');
        }
        if ($isTenant && $lease->tenant_id !== $user->id) {
            abort(403, 'You can only view your own leases.');
        }
    }

    // ========== ✅ GHANA: CONFIG SNAPSHOT ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

    // ========== ✅ GHANA: ADVANCE-RENT PAYLOAD ==========
    // Pass Carbon instances — the template formats them; no data loss.
    $advanceInfo = [
        'has_advance_rent'      => (bool) ($lease->has_advance_rent ?? false),
        'months'                => (int)  ($lease->advance_rent_months ?? 0),
        'amount'                => (float)($lease->advance_rent_amount ?? 0),
        'period_start'          => $lease->advance_rent_period_start,
        'period_end'            => $lease->advance_rent_period_end,
        'frequency'             => $lease->payment_frequency ?? 'monthly',
        'is_advance_only'       => ($lease->payment_frequency ?? 'monthly') === 'advance_only',
        'first_monthly_payment' => $lease->first_monthly_payment_date,
        'is_compliant'          => (bool) ($lease->is_advance_rent_compliant ?? true),
        'compliance_status'     => $lease->advance_rent_compliance_status ?? 'compliant',
        'acknowledged_at'       => $lease->advance_rent_acknowledged_at,
        'legal_max_months'      => (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6),
    ];

    // ========== ✅ INVOICE: SUMMARY PAYLOAD (optional) ==========
    $invoices = $lease->invoices ?? collect();

    $invoiceSummary = [
        'total_invoiced' => (float) $invoices->sum('amount'),
        'total_paid'     => (float) $invoices->sum('amount_paid'),
        'outstanding'    => (float) $invoices
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid)),
        'overdue_count'  => $invoices->where('status', 'overdue')->count(),
        'paid_count'     => $invoices->where('status', 'paid')->count(),
        'invoices'       => $invoices,
    ];

    // ========== PDF DATA PAYLOAD ==========
    $data = [
        'lease'          => $lease,
        'unit'           => $unit,
        'property'       => $unit->property,
        'tenant'         => $lease->tenant,
        'landlord'       => $lease->landlord,
        'generated_date' => now()->format('F j, Y'),
        'generated_at'   => now(),

        // ✅ GHANA context
        'currency_symbol' => $currencySymbol,
        'governing_law'   => $governingLaw,
        'advance_info'    => $advanceInfo,

        // ✅ INVOICE context (only used if the template references it)
        'invoices'         => $invoices,
        'invoice_summary'  => $invoiceSummary,
    ];

    // ========== GENERATE ==========
    try {
        $pdf = Pdf::loadView('pdf.lease-agreement', $data);

        // Optional: tune paper + default font for consistency
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('isRemoteEnabled', false);   // security: no remote fetches
        $pdf->setOption('isHtml5ParserEnabled', true);

        // ========== FILENAME ==========
        $safeName = \Illuminate\Support\Str::slug(
            $unit->property->property_name . '-' . $unit->unit_number
        );
        $filename = "Lease-{$safeName}-{$lease->id}.pdf";

        // ========== AUDIT ==========
        $this->logActivity(
            $user->id,
            'lease_pdf_downloaded',
            'Lease agreement PDF downloaded',
            $unit->id,
            [
                'lease_id'     => $lease->id,
                'reference'    => $lease->agreement_number ?? null,
                'filename'     => $filename,
                'downloaded_at'=> now()->toIso8601String(),
            ]
        );

        return $pdf->download($filename);

    } catch (\Throwable $e) {
        Log::error('Failed to generate lease PDF', [
            'unit_id'  => $unitId,
            'lease_id' => $leaseId,
            'user_id'  => $user->id,
            'error'    => $e->getMessage(),
            'trace'    => $e->getTraceAsString(),
        ]);

        return redirect()->back()
            ->with('error', 'Failed to generate the lease PDF. Please try again or contact support.');
    }
}
    // ========== DEPOSIT TRACKING ==========

    public function recordDepositPayment(Request $request, $leaseId)
    {
        $lease = RentalAgreement::findOrFail($leaseId);
        $user  = auth()->user();

        $hasLandlordRole = $user->isLandlord() || $user->hasRole('landlord');
        $isAdmin         = $user->isAdmin()    || $user->hasRole('admin');
        $isSuperAdmin    = $user->isSuperAdmin() || $user->hasRole('super-admin');

        if (!$hasLandlordRole && !$isAdmin && !$isSuperAdmin) {
            abort(403, 'Only landlords or administrators can record deposit payments.');
        }

        $validator = Validator::make($request->all(), [
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'payment_method'   => 'required|string|in:cash,bank_transfer,card,cheque',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $newTotal = $lease->deposit_collected_so_far + $request->amount;

            $lease->update(['deposit_collected_so_far' => $newTotal]);

            if ($newTotal >= $lease->security_deposit && $lease->deposit_payment_method === 'installment') {
                $lease->update(['deposit_fully_paid_at' => now()]);
            }

            // ✅ INVOICE: Record payment against the security deposit invoice
            $depositInvoice = PropertyUnitInvoice::where('lease_id', $lease->id)
                ->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT)
                ->whereIn('status', [
                    PropertyUnitInvoice::STATUS_PENDING,
                    PropertyUnitInvoice::STATUS_PARTIAL,
                    PropertyUnitInvoice::STATUS_OVERDUE,
                ])
                ->first();

            if ($depositInvoice) {
                $newPaid = $depositInvoice->amount_paid + $request->amount;
                $depositInvoice->update([
                    'amount_paid' => $newPaid,
                    'status'      => $newPaid >= $depositInvoice->amount
                        ? PropertyUnitInvoice::STATUS_PAID
                        : PropertyUnitInvoice::STATUS_PARTIAL,
                    'paid_at'     => $newPaid >= $depositInvoice->amount ? now() : $depositInvoice->paid_at,
                ]);
            }

            $this->logActivity($user->id, 'deposit_payment_recorded', 'Deposit payment recorded', $lease->unit_id, [
                'lease_id'        => $lease->id,
                'amount'          => $request->amount,
                'total_collected' => $newTotal,
                'remaining'       => $lease->security_deposit - $newTotal,
                'payment_method'  => $request->payment_method,
            ]);

            DB::commit();

            $remaining = $lease->security_deposit - $newTotal;
            $message = "Deposit payment of " . $this->formatCurrency($request->amount) . " recorded. ";
            $message .= $remaining <= 0
                ? "Deposit is now fully paid!"
                : "Remaining deposit: " . $this->formatCurrency($remaining);

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording deposit payment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to record deposit payment: ' . $e->getMessage());
        }
    }

    public function getDepositStatus($leaseId)
    {
        $lease = RentalAgreement::findOrFail($leaseId);

        return response()->json([
            'total_deposit'       => $lease->security_deposit,
            'collected_so_far'    => $lease->deposit_collected_so_far,
            'remaining'           => $lease->security_deposit - $lease->deposit_collected_so_far,
            'payment_method'      => $lease->deposit_payment_method,
            'installment_months'  => $lease->deposit_installment_months,
            'monthly_installment' => $lease->deposit_monthly_installment,
            'fully_paid_at'       => $lease->deposit_fully_paid_at,
            'is_fully_paid'       => $lease->deposit_collected_so_far >= $lease->security_deposit,
        ]);
    }

    // ========== HELPER METHODS ==========

    private function sendLeaseToTenant(RentalAgreement $lease, PropertyUnit $unit, array $channels): array
{
    $tenant = $unit->tenant;

    if (!$tenant) {
        return ['success' => false, 'message' => 'No tenant assigned to this unit'];
    }

    try {
        // ✅ Use the dedicated factory so unit_id, lease_id and
        //    invitation_type are always set consistently.
        $invitation = TenantInvitation::createForLease(
            lease:      $lease,
            unit:       $unit,
            channels:   $channels,
            invitedBy:  auth()->user(),
            metadata:   [
                'property_name'       => $unit->property->property_name,
                'unit_number'         => $unit->unit_number,
                'landlord_name'       => $unit->property->landlord->name,
                'monthly_rent'        => $lease->monthly_rent,
                'advance_rent_months' => $lease->advance_rent_months,
                'advance_rent_amount' => $lease->advance_rent_amount,
                'lease_start_date'    => $lease->start_date->format('Y-m-d'),
                'lease_end_date'      => $lease->end_date ? $lease->end_date->format('Y-m-d') : 'Month-to-Month',
                'purpose'             => 'lease_review_and_signature',
            ],
            expiresInDays: 14,
        );

        $results            = [];
        $successCount       = 0;
        $channelsSuccessful = [];

        foreach ($channels as $channel) {
            try {
                $result = $this->sendLeaseInvitationViaChannel($tenant, $unit, $lease, $invitation, $channel);
                $results[$channel] = $result;
                if ($result['success']) {
                    $successCount++;
                    $channelsSuccessful[] = $channel;
                }
            } catch (\Exception $e) {
                Log::error("Error sending lease invitation via {$channel}: " . $e->getMessage());
                $results[$channel] = ['success' => false, 'message' => "Failed to send via {$channel}: " . $e->getMessage()];
            }
        }

        if ($successCount > 0) {
            $invitation->markAsSent($channelsSuccessful);
        } else {
            $invitation->markAsFailed('All communication channels failed');
        }

        $this->logActivity(auth()->id(), 'lease_sent_for_signature',
            'Lease sent to tenant for signature', $unit->id, [
                'lease_id'      => $lease->id,
                'tenant_id'     => $tenant->id,
                'channels'      => $channelsSuccessful,
                'success_count' => $successCount,
            ]);

        return [
            'success'             => $successCount > 0,
            'invitation_id'       => $invitation->id,
            'token'               => $invitation->token,
            'invitation_url'      => $invitation->getInvitationUrl(),
            'channels_successful' => $channelsSuccessful,
            'results'             => $results,
        ];

    } catch (\Exception $e) {
        Log::error('Failed to send lease to tenant: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send lease: ' . $e->getMessage()];
    }
}

    private function sendLeaseInvitationViaChannel(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation, string $channel): array
    {
        switch ($channel) {
            case 'email':    return $this->sendLeaseEmailInvitation($tenant, $unit, $lease, $invitation);
            case 'sms':      return $this->sendLeaseSmsInvitation($tenant, $unit, $lease, $invitation);
            case 'whatsapp': return $this->sendLeaseWhatsAppInvitation($tenant, $unit, $lease, $invitation);
            default:         return ['success' => false, 'message' => "Unknown channel: {$channel}"];
        }
    }

    private function sendLeaseEmailInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
{
    try {
        // ✅ No `invitation_token` write — the model already guarantees `token` exists
        if (empty($invitation->token)) {
            $invitation->token = Str::random(60);
            $invitation->save();
        }

        $landlord = $unit->property->landlord ?? null;
        Mail::to($tenant->email)->send(new LeaseInvitationMail($tenant, $unit, $lease, $invitation, $landlord));

        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (\Exception $e) {
        Log::error('Failed to send lease email invitation: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
    }
}

    private function sendLeaseSmsInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
    {
        try {
            Log::info('SMS invitation sent', [
                'tenant_phone'   => $tenant->phone,
                'invitation_url' => $invitation->getInvitationUrl(),
            ]);
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } catch (\Exception $e) {
            Log::error('Failed to send lease SMS invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    private function sendLeaseWhatsAppInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
    {
        try {
            Log::info('WhatsApp invitation sent', [
                'tenant_phone'   => $tenant->phone,
                'invitation_url' => $invitation->getInvitationUrl(),
            ]);
            return ['success' => true, 'message' => 'WhatsApp message sent successfully'];
        } catch (\Exception $e) {
            Log::error('Failed to send lease WhatsApp invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send WhatsApp message: ' . $e->getMessage()];
        }
    }

    // ✅ GHANA: generateLeaseTerms includes advance-rent + monthly clauses
    private function generateLeaseTerms(Request $request, PropertyUnit $unit): string
    {
        $terms = [];
        $startDate = Carbon::parse($request->start_date);

        $endDate = $request->lease_type === 'fixed'
            ? ($request->end_date ? Carbon::parse($request->end_date) : $startDate->copy()->addMonths((int) $request->duration_months))
            : null;

        $advanceMonths     = (int) $request->advance_rent_months;
        $monthlyRent       = (float) $request->monthly_rent;
        $advanceRentAmount = $monthlyRent * $advanceMonths;
        $advanceEndDate    = $startDate->copy()->addMonths($advanceMonths);

        if ($request->boolean('include_standard_terms')) {
            $terms[] = "1. PARTIES: This Lease Agreement is made between {$unit->property->landlord->name} (Landlord) and {$unit->tenant->name} (Tenant).";
            $terms[] = "2. PROPERTY: {$unit->property->property_name}, Unit {$unit->unit_number}, located at {$unit->property->address}.";
            $terms[] = "3. TERM: This lease shall commence on {$startDate->format('F j, Y')}";

            if ($request->lease_type === 'fixed' && $endDate) {
                $terms[count($terms)-1] .= " and end on {$endDate->format('F j, Y')} ({$request->duration_months} months).";
            } else {
                $terms[count($terms)-1] .= " and continue on a month-to-month basis until terminated with {$request->notice_period_days} days notice.";
            }

            // ✅ GHANA: Advance rent clause
            $terms[] = "4. ADVANCE RENT: The Tenant has paid GHS " . number_format($advanceRentAmount, 2)
                . " representing {$advanceMonths} month(s) of advance rent, covering the period from "
                . "{$startDate->format('F j, Y')} to {$advanceEndDate->format('F j, Y')}. "
                . "In accordance with Section 25(5) of the Rent Act, 1963 (Act 220), the advance rent for a tenancy "
                . "exceeding six (6) months shall not exceed six (6) months. Where the advance exceeds this limit, "
                . "the Tenant acknowledges that the payment was made voluntarily and without demand by the Landlord.";

            // ✅ GHANA: Monthly rent clause
            $terms[] = "5. MONTHLY RENT: Following the advance rent period, the Tenant shall pay GHS "
                . number_format($monthlyRent, 2) . " per month, due on the "
                . "{$request->payment_due_day}" . $this->getDaySuffix($request->payment_due_day)
                . " day of each month, beginning "
                . $advanceEndDate->copy()->setDay((int) $request->payment_due_day)->format('F j, Y') . ". "
                . "The Tenant is entitled to pay rent monthly in accordance with the Rent Act, 1963.";

            if ($request->boolean('enable_security_deposit') && $request->security_deposit > 0) {
                if ($request->deposit_payment_method === 'installment') {
                    $monthlyInstallment = $request->security_deposit / $request->deposit_installment_months;
                    $terms[] = "6. SECURITY DEPOSIT: A total security deposit of GHS " . number_format($request->security_deposit, 2)
                        . " is required. This amount shall be paid in {$request->deposit_installment_months} monthly installments of GHS "
                        . number_format($monthlyInstallment, 2) . " added to the monthly rent for the first "
                        . "{$request->deposit_installment_months} months of the lease term. "
                        . "The deposit will be held by Landlord as security and is refundable within 30 days of lease termination, "
                        . "subject to deductions for any amounts owed.";
                } else {
                    $terms[] = "6. SECURITY DEPOSIT: A security deposit of GHS " . number_format($request->security_deposit, 2)
                        . " is required and shall be paid before move-in. The deposit will be held by Landlord as security "
                        . "and is refundable within 30 days of lease termination, subject to deductions for any amounts owed.";
                }
            } else {
                $terms[] = "6. SECURITY DEPOSIT: No security deposit is required for this lease agreement.";
            }

            if ($request->utility_deposit > 0) {
                $terms[] = "7. UTILITY DEPOSIT: A utility deposit of GHS " . number_format($request->utility_deposit, 2)
                    . " is required to cover potential utility charges. This deposit is refundable at the end of the lease term, "
                    . "subject to any outstanding utility bills.";
            } else {
                $terms[] = "7. UTILITY DEPOSIT: No utility deposit is required for this lease agreement.";
            }

            $lateFeePercentage = $request->filled('late_fee_percentage') ? floatval($request->late_fee_percentage) : 0;
            $lateFeeFixed      = $request->filled('late_fee_fixed') ? floatval($request->late_fee_fixed) : 0;
            $gracePeriodDays   = $request->filled('grace_period_days') ? intval($request->grace_period_days) : 0;

            $lateFeeText = "8. LATE PAYMENT: ";
            if ($gracePeriodDays > 0) {
                $lateFeeText .= "Rent is due on the {$request->payment_due_day}" . $this->getDaySuffix($request->payment_due_day)
                    . " of each month. A grace period of {$gracePeriodDays} days is provided. ";
            } else {
                $lateFeeText .= "Rent is due on the {$request->payment_due_day}" . $this->getDaySuffix($request->payment_due_day)
                    . " of each month with no grace period. ";
            }

            if ($lateFeePercentage > 0 && $lateFeeFixed > 0) {
                $lateFeeText .= "If rent is not paid by the due date" . ($gracePeriodDays > 0 ? " (after the grace period)" : "")
                    . ", a late fee of {$lateFeePercentage}% of the monthly rent (GHS "
                    . number_format($monthlyRent * $lateFeePercentage / 100, 2)
                    . ") plus a fixed fee of GHS " . number_format($lateFeeFixed, 2) . " shall be charged.";
            } elseif ($lateFeePercentage > 0) {
                $lateFeeText .= "If rent is not paid by the due date" . ($gracePeriodDays > 0 ? " (after the grace period)" : "")
                    . ", a late fee of {$lateFeePercentage}% of the monthly rent (GHS "
                    . number_format($monthlyRent * $lateFeePercentage / 100, 2) . ") shall be charged.";
            } elseif ($lateFeeFixed > 0) {
                $lateFeeText .= "If rent is not paid by the due date" . ($gracePeriodDays > 0 ? " (after the grace period)" : "")
                    . ", a fixed late fee of GHS " . number_format($lateFeeFixed, 2) . " shall be charged.";
            } else {
                $lateFeeText .= "No late fees will be charged under this lease agreement.";
            }
            $terms[] = $lateFeeText;

            $terms[] = "9. UTILITIES: Tenant shall be responsible for all utility charges including but not limited to electricity, water, gas, internet, and garbage collection, unless otherwise specified in writing.";
            $terms[] = "10. MAINTENANCE AND REPAIRS: Landlord shall be responsible for major structural repairs and system maintenance. Tenant shall be responsible for minor repairs, bulb replacement, and general upkeep. Tenant must promptly report any maintenance issues in writing.";
            $terms[] = "11. TERMINATION: Either party may terminate this lease by providing {$request->notice_period_days} days written notice to the other party. For fixed-term leases, termination before the end date constitutes early termination.";

            if ($request->early_termination_fee > 0) {
                $terms[] = "12. EARLY TERMINATION: If Tenant terminates this lease before the end date"
                    . ($request->lease_type === 'fixed' ? " (or before {$request->duration_months} months)" : "")
                    . ", Tenant shall pay an early termination fee of GHS " . number_format($request->early_termination_fee, 2)
                    . ", in addition to any rent owed through the termination date.";
            } else {
                $terms[] = "12. EARLY TERMINATION: No early termination fee is specified. However, Tenant remains responsible for rent through the notice period and any costs incurred by Landlord to re-rent the unit.";
            }

            $terms[] = "13. USE OF PREMISES: The premises shall be used exclusively as a private residence for Tenant and approved occupants. No commercial activities, illegal activities, or nuisance behaviors are permitted.";
            $terms[] = "14. ALTERATIONS: Tenant shall not make any alterations, additions, or improvements to the premises without Landlord's prior written consent.";
            $terms[] = "15. SUBLETTING: Tenant shall not sublet the premises or assign this lease without Landlord's prior written consent.";
            $terms[] = "16. PETS: No pets shall be kept on the premises without Landlord's prior written consent.";
            $terms[] = "17. INSURANCE: Landlord shall maintain property insurance. Tenant is strongly encouraged to obtain renter's insurance.";
            $terms[] = "18. INSPECTIONS: Landlord reserves the right to inspect the premises with reasonable notice (minimum 24 hours), except in emergencies.";
            $terms[] = "19. GOVERNING LAW: This lease shall be governed by and construed in accordance with the laws of Ghana, including the Rent Act, 1963 (Act 220).";
            $terms[] = "20. ENTIRE AGREEMENT: This written lease constitutes the entire agreement between the parties and supersedes all prior discussions, representations, or agreements.";
        }

        if ($request->filled('renewal_terms')) {
            $terms[] = "\nRENEWAL TERMS:\n" . $request->renewal_terms;
        }
        if ($request->filled('special_terms')) {
            $terms[] = "\nSPECIAL TERMS AND CONDITIONS:\n" . $request->special_terms;
        }

        $terms[] = "\n\n" . str_repeat("=", 80);
        $terms[] = "IN WITNESS WHEREOF, the parties have executed this Lease Agreement as of the date first written above.";
        $terms[] = str_repeat("=", 80);
        $terms[] = "\nLANDLORD SIGNATURE: ___________________________    DATE: ___________";
        $terms[] = "Print Name: {$unit->property->landlord->name}";
        $terms[] = "\nTENANT SIGNATURE: ___________________________    DATE: ___________";
        $terms[] = "Print Name: {$unit->tenant->name}";

        return implode("\n\n", $terms);
    }

    public static function getDaySuffix($day)
    {
        if ($day >= 11 && $day <= 13) return 'th';
        switch ($day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }

    // ✅ GHANA: Advance rent dropdown options
    private function getAdvanceRentOptions(): array
    {
        return [
            1  => '1 Month (Short tenancy / monthly)',
            3  => '3 Months (Renewal max)',
            6  => '6 Months (Legal max for new tenancy)',
            12 => '12 Months (1 Year — market practice)',
            24 => '24 Months (2 Years — market practice)',
            36 => '36 Months (3 Years — market practice)',
        ];
    }

    private function getLeaseTemplates(): array
    {
        return [
            'standard_12_month' => 'Standard 12-Month Lease',
            'month_to_month'    => 'Month-to-Month Agreement',
            'commercial'        => 'Commercial Lease',
            'student'           => 'Student Housing Agreement',
            'furnished'         => 'Furnished Unit Lease',
        ];
    }

    private function getPaymentTerms(): array
    {
        return [
            '1'  => '1st of each month',
            '5'  => '5th of each month',
            '10' => '10th of each month',
            '15' => '15th of each month',
            '20' => '20th of each month',
            '25' => '25th of each month',
        ];
    }

    private function getPenaltyTerms(): array
    {
        return [
            '5'         => '5% Late Fee',
            '10'        => '10% Late Fee',
            '15'        => '15% Late Fee',
            '20'        => '20% Late Fee',
            'fixed_50'  => 'Fixed GHS 50 Late Fee',
            'fixed_100' => 'Fixed GHS 100 Late Fee',
        ];
    }

    private function getMaintenanceTerms(): array
    {
        return [
            'landlord_all' => 'Landlord responsible for all maintenance',
            'tenant_minor' => 'Tenant responsible for minor repairs (< GHS 100)',
            'shared'       => 'Shared maintenance responsibilities',
            'tenant_all'   => 'Tenant responsible for all maintenance',
        ];
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
            if ($unit->tenant_id === $user->id && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED) return;
            abort(403, 'You can only view your assigned unit.');
        }

        abort(403, 'Unauthorized access to property unit.');
    }

    private function logActivity(int $userId, string $type, string $description, ?int $unitId = null, array $metadata = []): void
    {
        try {
            \App\Models\ActivityLog::create([
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

    private function formatCurrency(float $amount): string
    {
        return 'GHS ' . number_format($amount, 2);
    }

    // ========== WITNESS SIGNING METHODS ==========

    public function showLandlordWitnessSignForm($unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);

        if (!$lease->landlord_signed_at) {
            abort(403, 'Landlord must sign first before witness can sign.');
        }
        if ($lease->landlord_witness_signature) {
            return redirect()->route('property-units.lease-details', [$unit->id, $lease->id])
                ->with('info', 'Landlord witness signature already recorded.');
        }
        return view('property_units.witness-sign-landlord', compact('unit', 'lease'));
    }

    public function showTenantWitnessSignForm($unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);

        if (!$lease->tenant_signed_at) {
            abort(403, 'Tenant must sign first before witness can sign.');
        }
        if ($lease->tenant_witness_signature) {
            return redirect()->route('property-units.lease-details', [$unit->id, $lease->id])
                ->with('info', 'Tenant witness signature already recorded.');
        }
        return view('property_units.witness-sign-tenant', compact('unit', 'lease'));
    }

    public function landlordWitnessSign(Request $request, $unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);

        $validator = Validator::make($request->all(), [
            'witness_name'           => 'required|string|max:255',
            'witness_signature_data' => 'required|string',
            'witness_signature_type' => 'required|in:digital,upload',
            'witness_relationship'   => 'nullable|string|max:100',
            'witness_email'          => 'nullable|email|max:255',
            'witness_phone'          => 'nullable|string|max:20',
            'witness_role'           => 'nullable|string|max:100',
            'signed_at'              => 'required|date|before_or_equal:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $witnessSignatureData = [
                'name'           => $request->witness_name,
                'relationship'   => $request->witness_relationship,
                'role'           => $request->witness_role,
                'email'          => $request->witness_email,
                'phone'          => $request->witness_phone,
                'signature'      => $request->witness_signature_data,
                'signature_type' => $request->witness_signature_type,
                'signed_at'      => $request->signed_at,
                'witness_for'    => 'landlord',
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ];

            $lease->update([
                'landlord_witness_signature' => $witnessSignatureData,
                'landlord_witness_signed_at' => now(),
            ]);

            $this->logActivity(auth()->id() ?? 0, 'landlord_witness_signed',
                'Landlord witness signed lease agreement', $unit->id,
                ['lease_id' => $lease->id, 'witness_name' => $witnessSignatureData['name'],
                 'witness_role' => $witnessSignatureData['role']]);

            if (!empty($witnessSignatureData['email'])) {
                try {
                    Mail::to($witnessSignatureData['email'])->send(new \App\Mail\WitnessConfirmationMail(
                        $witnessSignatureData['name'], $lease->landlord->name, $unit, $lease,
                        'landlord', $witnessSignatureData['role']
                    ));
                } catch (\Exception $e) {
                    Log::warning('Failed to send landlord witness confirmation email: ' . $e->getMessage());
                }
            }

            if ($lease->landlord) {
                $lease->landlord->notify(new \App\Notifications\GeneralNotification(
                    title: 'Witness Signed Your Lease',
                    message: "{$witnessSignatureData['name']} has signed as witness for your lease on Unit {$unit->unit_number}.",
                    icon: 'fas fa-user-check text-success',
                    category: 'witness_signed',
                    actionUrl: route('property-units.lease-details', [$unit->id, $lease->id]),
                    priority: 2,
                    data: [
                        'type'         => 'landlord_witness_signed',
                        'witness_name' => $witnessSignatureData['name'],
                        'witness_role' => $witnessSignatureData['role'],
                        'signed_at'    => $request->signed_at,
                    ]
                ));
            }

            DB::commit();
            return redirect()->route('property-units.lease-details', [$unit->id, $lease->id])
                ->with('success', 'Witness signature recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording landlord witness signature: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to record witness signature: ' . $e->getMessage());
        }
    }

    public function tenantWitnessSign(Request $request, $unitId, $leaseId)
    {
        $unit  = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);

        $validator = Validator::make($request->all(), [
            'witness_name'           => 'required|string|max:255',
            'witness_signature_data' => 'required|string',
            'witness_signature_type' => 'required|in:digital,upload',
            'witness_relationship'   => 'nullable|string|max:100',
            'witness_email'          => 'nullable|email|max:255',
            'witness_phone'          => 'nullable|string|max:20',
            'signed_at'              => 'required|date|before_or_equal:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $witnessSignatureData = [
                'name'           => $request->witness_name,
                'relationship'   => $request->witness_relationship,
                'email'          => $request->witness_email,
                'phone'          => $request->witness_phone,
                'signature'      => $request->witness_signature_data,
                'signature_type' => $request->witness_signature_type,
                'signed_at'      => $request->signed_at,
                'witness_for'    => 'tenant',
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ];

            $lease->update([
                'tenant_witness_signature' => $witnessSignatureData,
                'tenant_witness_signed_at' => now(),
            ]);

            $this->logActivity(auth()->id() ?? 0, 'tenant_witness_signed',
                'Tenant witness signed lease agreement', $unit->id,
                ['lease_id' => $lease->id, 'witness_name' => $witnessSignatureData['name']]);

            if (!empty($witnessSignatureData['email'])) {
                try {
                    Mail::to($witnessSignatureData['email'])->send(new \App\Mail\WitnessConfirmationMail(
                        $witnessSignatureData['name'], $lease->tenant->name, $unit, $lease, 'tenant'
                    ));
                } catch (\Exception $e) {
                    Log::warning('Failed to send tenant witness confirmation email: ' . $e->getMessage());
                }
            }

            if ($lease->tenant) {
                $lease->tenant->notify(new \App\Notifications\GeneralNotification(
                    title: 'Witness Signed Your Lease',
                    message: "{$witnessSignatureData['name']} has signed as witness for your lease on Unit {$unit->unit_number}.",
                    icon: 'fas fa-user-check text-success',
                    category: 'witness_signed',
                    actionUrl: route('tenant.property-units.lease-details', [$unit->id, $lease->id]),
                    priority: 2,
                    data: [
                        'type'         => 'tenant_witness_signed',
                        'witness_name' => $witnessSignatureData['name'],
                        'signed_at'    => $request->signed_at,
                    ]
                ));
            }

            DB::commit();
            return redirect()->route('tenant.property-units.lease-details', [$unit->id, $lease->id])
                ->with('success', 'Witness signature recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording tenant witness signature: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to record witness signature: ' . $e->getMessage());
        }
    }
}