<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\RentalAgreement;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Models\ActivityLog;
use App\Mail\LeaseInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class LeaseManagementController extends Controller
{
    public function index($id)
    {
        $unit = PropertyUnit::with([
            'currentLease',
            'rentalAgreements' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();
        
        $canCreateLease = $user->isLandlord() && 
                         $unit->property->landlord_id === $user->id && 
                         $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED &&
                         (!$unit->currentLease || $unit->currentLease->status !== 'active');

        $canRenewLease = $user->isLandlord() && 
                        $unit->property->landlord_id === $user->id &&
                        $unit->currentLease && 
                        $unit->currentLease->status === 'active' &&
                        $unit->currentLease->end_date->diffInDays(now()) <= 60;

        $canTerminateLease = $user->isLandlord() && 
                            $unit->property->landlord_id === $user->id &&
                            $unit->currentLease && 
                            $unit->currentLease->status === 'active';

        return view('property_units.lease-management', compact(
            'unit',
            'canCreateLease',
            'canRenewLease',
            'canTerminateLease'
        ));
    }

    public function create($id)
    {
        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
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

        $leaseTemplates = $this->getLeaseTemplates();
        $paymentTerms = $this->getPaymentTerms();
        $penaltyTerms = $this->getPenaltyTerms();
        $maintenanceTerms = $this->getMaintenanceTerms();

        return view('property_units.create-lease', compact(
            'unit',
            'leaseTemplates',
            'paymentTerms',
            'penaltyTerms',
            'maintenanceTerms'
        ));
    }

    public function store(Request $request, $id)
    {
        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
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

        $validator = Validator::make($request->all(), [
            'lease_type' => 'required|in:fixed,month_to_month',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required_if:lease_type,fixed|date|after:start_date',
            'duration_months' => 'required_if:lease_type,fixed|integer|min:1|max:60',
            'monthly_rent' => 'required|numeric|min:0.01',
            'security_deposit' => 'required|numeric|min:0',
            'utility_deposit' => 'nullable|numeric|min:0',
            'late_fee_percentage' => 'required|numeric|min:0|max:50',
            'late_fee_fixed' => 'nullable|numeric|min:0',
            'grace_period_days' => 'required|integer|min:0|max:15',
            'payment_due_day' => 'required|integer|min:1|max:28',
            'notice_period_days' => 'required|integer|min:15|max:90',
            'early_termination_fee' => 'nullable|numeric|min:0',
            'renewal_terms' => 'nullable|string|max:1000',
            'special_terms' => 'nullable|string|max:2000',
            'include_standard_terms' => 'boolean',
            'send_to_tenant' => 'boolean',
            'invitation_channels' => 'nullable|required_if:send_to_tenant,true|array|min:1',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
            'tenant_signature_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $endDate = $request->end_date ?? Carbon::parse($request->start_date)
                ->addMonths($request->duration_months);

            $leaseTerms = $this->generateLeaseTerms($request, $unit);

            $lease = RentalAgreement::create([
                'unit_id' => $unit->id,
                'tenant_id' => $unit->tenant_id,
                'property_id' => $unit->property_id,
                'landlord_id' => $user->id,
                'lease_type' => $request->lease_type,
                'monthly_rent' => $request->monthly_rent,
                'security_deposit' => $request->security_deposit,
                'utility_deposit' => $request->utility_deposit,
                'late_fee_percentage' => $request->late_fee_percentage,
                'late_fee_fixed' => $request->late_fee_fixed,
                'grace_period_days' => $request->grace_period_days,
                'payment_due_day' => $request->payment_due_day,
                'notice_period_days' => $request->notice_period_days,
                'early_termination_fee' => $request->early_termination_fee,
                'start_date' => $request->start_date,
                'end_date' => $endDate,
                'duration_months' => $request->duration_months ?? null,
                'terms' => $leaseTerms,
                'special_terms' => $request->special_terms,
                'status' => 'draft',
                'created_by' => $user->id,
                'created_by_type' => 'landlord',
                'notes' => 'Lease created by landlord ' . $user->name,
            ]);

            $unit->current_lease_id = $lease->id;
            $unit->current_rent_amount = $request->monthly_rent;
            $unit->security_deposit = $request->security_deposit;
            $unit->save();

            $invitationResult = null;
            if ($request->send_to_tenant) {
                $invitationResult = $this->sendLeaseToTenant($lease, $unit, $request->invitation_channels);
            }

            $this->logActivity(
                $user->id,
                'lease_created',
                'Lease agreement created',
                $unit->id,
                [
                    'lease_id' => $lease->id,
                    'lease_type' => $request->lease_type,
                    'duration_months' => $request->duration_months,
                    'monthly_rent' => $request->monthly_rent,
                    'sent_to_tenant' => $request->send_to_tenant ?? false
                ]
            );

            Cache::forget('property_unit_' . $id . '_with_relations');
            Cache::forget('unit_stats_' . $id);

            DB::commit();

            return redirect()->route('property-units.lease-management', $unit->id)
                ->with('success', 'Lease created successfully.' . 
                    ($request->send_to_tenant ? ' Sent to tenant for review.' : ''));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating lease: ' . $e->getMessage(), [
                'unit_id' => $id,
                'user_id' => $user->id,
                'user_type' => $user->type
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create lease: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function renew(Request $request, $id)
    {
        $unit = PropertyUnit::with(['currentLease'])->findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()
                    ->with('error', 'Only the property owner can renew leases.')
                    ->withInput();
            }
        } else {
            return redirect()->back()
                ->with('error', 'Only property owners can renew leases.')
                ->withInput();
        }

        if (!$unit->currentLease || $unit->currentLease->status !== 'active') {
            return redirect()->back()
                ->with('error', 'No active lease to renew.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'renewal_start_date' => 'required|date|after_or_equal:' . $unit->currentLease->end_date->format('Y-m-d'),
            'renewal_duration_months' => 'required|integer|min:1|max:60',
            'new_monthly_rent' => 'required|numeric|min:' . ($unit->current_rent_amount * 0.9) . '|max:' . ($unit->current_rent_amount * 1.2),
            'adjust_security_deposit' => 'boolean',
            'new_security_deposit' => 'nullable|required_if:adjust_security_deposit,true|numeric|min:0',
            'renewal_terms' => 'nullable|string|max:2000',
            'send_for_signature' => 'boolean',
            'invitation_channels' => 'nullable|required_if:send_for_signature,true|array|min:1',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $unit->currentLease->update([
                'status' => 'completed',
                'end_date' => Carbon::parse($request->renewal_start_date)->subDay(),
            ]);

            $newLease = RentalAgreement::create([
                'unit_id' => $unit->id,
                'tenant_id' => $unit->tenant_id,
                'property_id' => $unit->property_id,
                'landlord_id' => $unit->property->landlord_id,
                'lease_type' => $unit->currentLease->lease_type,
                'monthly_rent' => $request->new_monthly_rent,
                'security_deposit' => $request->adjust_security_deposit ? $request->new_security_deposit : $unit->security_deposit,
                'late_fee_percentage' => $unit->currentLease->late_fee_percentage,
                'late_fee_fixed' => $unit->currentLease->late_fee_fixed,
                'grace_period_days' => $unit->currentLease->grace_period_days,
                'payment_due_day' => $unit->currentLease->payment_due_day,
                'notice_period_days' => $unit->currentLease->notice_period_days,
                'early_termination_fee' => $unit->currentLease->early_termination_fee,
                'start_date' => $request->renewal_start_date,
                'end_date' => Carbon::parse($request->renewal_start_date)->addMonths($request->renewal_duration_months),
                'duration_months' => $request->renewal_duration_months,
                'terms' => $request->renewal_terms ?? $unit->currentLease->terms,
                'special_terms' => $unit->currentLease->special_terms,
                'status' => 'draft',
                'created_by' => $user->id,
                'created_by_type' => 'landlord',
                'notes' => 'Lease renewal',
                'previous_lease_id' => $unit->currentLease->id,
            ]);

            $unit->current_lease_id = $newLease->id;
            $unit->lease_start_date = $request->renewal_start_date;
            $unit->lease_end_date = $newLease->end_date;
            $unit->current_rent_amount = $request->new_monthly_rent;
            if ($request->adjust_security_deposit) {
                $unit->security_deposit = $request->new_security_deposit;
            }
            $unit->save();

            if ($request->send_for_signature) {
                $this->sendLeaseToTenant($newLease, $unit, $request->invitation_channels);
            }

            $this->logActivity(
                $user->id,
                'lease_renewed',
                'Lease agreement renewed',
                $unit->id,
                [
                    'new_lease_id' => $newLease->id,
                    'duration_months' => $request->renewal_duration_months,
                    'new_monthly_rent' => $request->new_monthly_rent,
                    'sent_for_signature' => $request->send_for_signature ?? false
                ]
            );

            Cache::forget('property_unit_' . $id . '_with_relations');

            DB::commit();

            return redirect()->route('property-units.lease-management', $unit->id)
                ->with('success', 'Lease renewed successfully.' . 
                    ($request->send_for_signature ? ' Sent to tenant for signature.' : ''));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error renewing lease: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to renew lease: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show($unitId, $leaseId)
    {
        $unit = PropertyUnit::with(['property'])->findOrFail($unitId);
        $lease = RentalAgreement::with(['tenant', 'landlord', 'unit'])
            ->where('unit_id', $unitId)
            ->findOrFail($leaseId);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();
        if ($user->isLandlord() && $unit->property->landlord_id !== $user->id) {
            abort(403, 'You can only view leases for your own properties.');
        }
        if ($user->isTenant() && $lease->tenant_id !== $user->id) {
            abort(403, 'You can only view your own leases.');
        }

        return view('property_units.lease-details', compact('unit', 'lease'));
    }

    public function downloadPdf($unitId, $leaseId)
    {
        $unit = PropertyUnit::with(['property'])->findOrFail($unitId);
        $lease = RentalAgreement::with(['tenant', 'landlord', 'unit'])
            ->where('unit_id', $unitId)
            ->findOrFail($leaseId);

        $this->checkUnitAccessWithError($unit);

        $user = auth()->user();
        if ($user->isLandlord() && $unit->property->landlord_id !== $user->id) {
            abort(403, 'You can only view leases for your own properties.');
        }

        $data = [
            'lease' => $lease,
            'unit' => $unit,
            'property' => $unit->property,
            'tenant' => $lease->tenant,
            'landlord' => $lease->landlord,
            'generated_date' => now()->format('F j, Y'),
        ];

        $pdf = Pdf::loadView('pdf.lease-agreement', $data);
        
        return $pdf->download("Lease-Agreement-{$unit->property->property_name}-{$unit->unit_number}-{$lease->id}.pdf");
    }

    public function landlordSign(Request $request, $unitId, $leaseId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);
        $user = auth()->user();

        if ($user->isLandlord() && $unit->property->landlord_id === $user->id) {
            $validator = Validator::make($request->all(), [
                'signature_data' => 'required|string',
                'signature_type' => 'required|in:digital,upload',
                'signed_at' => 'required|date|before_or_equal:now',
                'witness_name' => 'nullable|string|max:255',
                'witness_signature_data' => 'nullable|required_with:witness_name|string',
                'witness_signature_type' => 'nullable|required_with:witness_name|in:digital,upload',
                'witness_relationship' => 'nullable|required_with:witness_name|string|max:100',
                'witness_email' => 'nullable|email|max:255',
                'witness_phone' => 'nullable|string|max:20',
                'witness_role' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
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
                        'name' => $request->witness_name,
                        'relationship' => $request->witness_relationship,
                        'role' => $request->witness_role,
                        'email' => $request->witness_email,
                        'phone' => $request->witness_phone,
                        'signature' => $request->witness_signature_data,
                        'signature_type' => $request->witness_signature_type,
                        'signed_at' => now(),
                        'witness_for' => 'landlord',
                        'witnessed_by_user_id' => $user->id,
                        'witnessed_by_user_name' => $user->name,
                    ];
                    
                    $lease->update([
                        'landlord_witness_signature' => $witnessSignatureData,
                        'landlord_witness_signed_at' => now(),
                    ]);
                    
                    $this->logActivity(
                        $user->id,
                        'landlord_witness_signed',
                        'Landlord witness signed lease agreement',
                        $unit->id,
                        [
                            'lease_id' => $lease->id,
                            'witness_name' => $witnessSignatureData['name'],
                            'witness_role' => $witnessSignatureData['role']
                        ]
                    );
                }

                $this->logActivity(
                    $user->id,
                    'landlord_signed_lease',
                    'Landlord signed lease agreement' . ($witnessSignatureData ? ' with witness' : ''),
                    $unit->id,
                    [
                        'lease_id' => $lease->id,
                        'has_witness' => !empty($witnessSignatureData),
                        'witness_name' => $witnessSignatureData['name'] ?? null
                    ]
                );

                if ($lease->tenant) {
                    $notificationData = [
                        'type' => 'landlord_signed_lease',
                        'unit_id' => $unit->id,
                        'lease_id' => $lease->id,
                        'landlord_id' => $user->id,
                        'signed_at' => $request->signed_at,
                        'has_witness' => !empty($witnessSignatureData),
                    ];
                    
                    if ($witnessSignatureData) {
                        $notificationData['witness_name'] = $witnessSignatureData['name'];
                        $notificationData['witness_role'] = $witnessSignatureData['role'];
                    }
                    
                    $lease->tenant->notify(new \App\Notifications\GeneralNotification(
                        title: 'Lease Signed by Landlord',
                        message: "Landlord {$user->name} has signed the lease for Unit {$unit->unit_number}." . 
                                ($witnessSignatureData ? " Witnessed by {$witnessSignatureData['name']} ({$witnessSignatureData['role']})." : ""),
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
                            $witnessSignatureData['name'],
                            $user->name,
                            $unit,
                            $lease,
                            'landlord',
                            $witnessSignatureData['role']
                        ));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send landlord witness confirmation email: ' . $e->getMessage());
                    }
                }

                $admins = User::whereIn('type', ['admin', 'super_admin'])->get();
                if ($admins->count() > 0) {
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\GeneralNotification(
                            title: 'Lease Signed by Landlord',
                            message: "Landlord {$user->name} has signed the lease for {$unit->property->property_name}, Unit {$unit->unit_number}.",
                            icon: 'fas fa-signature text-info',
                            category: 'lease_signed_admin',
                            actionUrl: route('admin.property-units.lease-details', [$unit->id, $lease->id]),
                            priority: 2,
                            data: [
                                'type' => 'landlord_signed_lease_admin',
                                'unit_id' => $unit->id,
                                'lease_id' => $lease->id,
                                'landlord_id' => $user->id,
                                'tenant_id' => $lease->tenant_id,
                                'signed_at' => $request->signed_at,
                                'has_witness' => !empty($witnessSignatureData),
                            ]
                        ));
                    }
                }

                DB::commit();

                return redirect()->route('property-units.lease-details', [$unit->id, $lease->id])
                    ->with('success', 'Lease signed successfully.' . 
                        ($witnessSignatureData ? ' Witness signature recorded.' : '') . ' ' .
                        ($lease->tenant_signed_at ? 'Lease is now active.' : 'Waiting for tenant signature.'));

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error signing lease as landlord: ' . $e->getMessage(), [
                    'unit_id' => $unitId,
                    'lease_id' => $leaseId,
                    'landlord_id' => $user->id
                ]);

                return redirect()->back()
                    ->with('error', 'Failed to sign lease: ' . $e->getMessage());
            }
        }

        abort(403, 'Only the property owner can sign leases.');
    }

    public function tenantSign(Request $request, $unitId, $leaseId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);
        $user = auth()->user();

        if (!$user->isTenant()) {
            abort(403, 'Only tenants can sign their own leases.');
        }

        if ($lease->tenant_id !== $user->id) {
            abort(403, 'You can only sign your own lease.');
        }

        if ($lease->tenant_signed_at) {
            return redirect()->back()
                ->with('error', 'You have already signed this lease.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'signature_data' => 'required|string',
            'signature_type' => 'required|in:digital,upload',
            'signed_at' => 'required|date|before_or_equal:now',
            'agreement_check' => 'required|accepted',
            'witness_name' => 'nullable|string|max:255',
            'witness_signature_data' => 'nullable|required_with:witness_name|string',
            'witness_signature_type' => 'nullable|required_with:witness_name|in:digital,upload',
            'witness_relationship' => 'nullable|required_with:witness_name|string|max:100',
            'witness_email' => 'nullable|email|max:255',
            'witness_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
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
                    'name' => $request->witness_name,
                    'relationship' => $request->witness_relationship,
                    'email' => $request->witness_email,
                    'phone' => $request->witness_phone,
                    'signature' => $request->witness_signature_data,
                    'signature_type' => $request->witness_signature_type,
                    'signed_at' => now(),
                    'witness_for' => 'tenant'
                ];
                
                $lease->update([
                    'tenant_witness_signature' => $witnessSignatureData,
                    'tenant_witness_signed_at' => now(),
                ]);
            }

            $this->logActivity(
                $user->id,
                'tenant_signed_lease',
                'Tenant signed lease agreement' . ($witnessSignatureData ? ' with witness' : ''),
                $unit->id,
                [
                    'lease_id' => $lease->id,
                    'has_witness' => !empty($witnessSignatureData),
                    'witness_name' => $witnessSignatureData['name'] ?? null
                ]
            );

            if ($lease->landlord) {
                $notificationData = [
                    'type' => 'tenant_signed_lease',
                    'unit_id' => $unit->id,
                    'lease_id' => $lease->id,
                    'tenant_id' => $user->id,
                    'signed_at' => $request->signed_at,
                    'has_witness' => !empty($witnessSignatureData),
                ];
                
                if ($witnessSignatureData) {
                    $notificationData['witness_name'] = $witnessSignatureData['name'];
                }
                
                $lease->landlord->notify(new \App\Notifications\GeneralNotification(
                    title: 'Lease Signed by Tenant',
                    message: "Tenant {$user->name} has signed the lease for Unit {$unit->unit_number}." . 
                            ($witnessSignatureData ? " Witnessed by {$witnessSignatureData['name']}." : ""),
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
                        $witnessSignatureData['name'],
                        $user->name,
                        $unit,
                        $lease,
                        'tenant'
                    ));
                } catch (\Exception $e) {
                    Log::warning('Failed to send witness confirmation email: ' . $e->getMessage());
                }
            }

            DB::commit();

            return redirect()->route('tenant.property-units.lease-details', [$unit->id, $lease->id])
                ->with('success', 'Lease signed successfully.' . 
                      ($witnessSignatureData ? ' Witness signature recorded.' : '') .
                      ($lease->landlord_signed_at ? ' Lease is now active.' : ' Waiting for landlord signature.'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error signing lease as tenant: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'lease_id' => $leaseId,
                'tenant_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to sign lease: ' . $e->getMessage());
        }
    }

    public function terminate(Request $request, $unitId, $leaseId)
    {
        $unit = PropertyUnit::findOrFail($unitId);
        $lease = RentalAgreement::where('unit_id', $unitId)->findOrFail($leaseId);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()
                    ->with('error', 'Only the property owner can terminate leases.')
                    ->withInput();
            }
        } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return redirect()->back()
                ->with('error', 'Only administrators can terminate leases.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'termination_date' => 'required|date|after_or_equal:today',
            'termination_reason' => 'required|string|max:500',
            'notice_given_date' => 'required|date|before_or_equal:today',
            'penalty_applied' => 'nullable|numeric|min:0',
            'refund_amount' => 'nullable|numeric|min:0|max:' . $lease->security_deposit,
            'final_settlement_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $lease->update([
                'status' => 'terminated',
                'termination_date' => $request->termination_date,
                'termination_reason' => $request->termination_reason,
                'termination_details' => [
                    'initiated_by' => $user->id,
                    'initiated_by_type' => 'landlord',
                    'notice_given_date' => $request->notice_given_date,
                    'penalty_applied' => $request->penalty_applied,
                    'refund_amount' => $request->refund_amount,
                    'final_settlement_notes' => $request->final_settlement_notes,
                    'terminated_at' => now()->toISOString(),
                ],
            ]);

            $unit->tenant_status = PropertyUnit::TENANT_STATUS_TERMINATED;
            $unit->tenant_move_out_date = $request->termination_date;
            $unit->tenant_vacate_reason = $request->termination_reason;
            $unit->status = PropertyUnit::STATUS_UNDER_MAINTENANCE;
            $unit->is_available = false;
            $unit->available_from = Carbon::parse($request->termination_date)->addDays(14);
            $unit->save();

            $this->logActivity(
                $user->id,
                'lease_terminated',
                'Lease agreement terminated',
                $unit->id,
                [
                    'lease_id' => $lease->id,
                    'termination_date' => $request->termination_date,
                    'reason' => $request->termination_reason
                ]
            );

            $this->clearUnitCaches($user->id);
            Cache::forget('property_unit_' . $unitId . '_with_relations');

            DB::commit();

            return redirect()->route('property-units.lease-management', $unit->id)
                ->with('success', 'Lease terminated successfully. Unit is now under maintenance.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error terminating lease: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to terminate lease: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== PRIVATE METHODS ==========

    private function sendLeaseToTenant(RentalAgreement $lease, PropertyUnit $unit, array $channels): array
    {
        $tenant = $unit->tenant;
        
        try {
            $invitation = TenantInvitation::create([
                'user_id' => $tenant->id,
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'lease_id' => $lease->id,
                'invited_by' => auth()->id(),
                'invitation_type' => 'lease_review',
                'channels' => $channels,
                'status' => TenantInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(14),
                'metadata' => [
                    'lease_id' => $lease->id,
                    'property_name' => $unit->property->property_name,
                    'unit_number' => $unit->unit_number,
                    'landlord_name' => $unit->property->landlord->name,
                    'monthly_rent' => $lease->monthly_rent,
                    'lease_start_date' => $lease->start_date->format('Y-m-d'),
                    'lease_end_date' => $lease->end_date->format('Y-m-d'),
                    'purpose' => 'lease_review_and_signature'
                ]
            ]);

            $results = [];
            $successCount = 0;
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
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage()
                    ];
                }
            }

            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
            } else {
                $invitation->markAsFailed('All communication channels failed');
            }

            $lease->update(['status' => 'pending_signature']);

            $this->logActivity(
                auth()->id(),
                'lease_sent_for_signature',
                'Lease sent to tenant for signature',
                $unit->id,
                [
                    'lease_id' => $lease->id,
                    'tenant_id' => $tenant->id,
                    'channels' => $channelsSuccessful,
                    'success_count' => $successCount
                ]
            );

            return [
                'success' => $successCount > 0,
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'channels_successful' => $channelsSuccessful,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send lease to tenant: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send lease: ' . $e->getMessage()
            ];
        }
    }

    private function sendLeaseInvitationViaChannel(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation, string $channel): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendLeaseEmailInvitation($tenant, $unit, $lease, $invitation);
            case 'sms':
                return $this->sendLeaseSmsInvitation($tenant, $unit, $lease, $invitation);
            case 'whatsapp':
                return $this->sendLeaseWhatsAppInvitation($tenant, $unit, $lease, $invitation);
            default:
                return [
                    'success' => false,
                    'message' => "Unknown channel: {$channel}"
                ];
        }
    }

    private function sendLeaseEmailInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
    {
        try {
            if (!$invitation->invitation_token) {
                $invitation->invitation_token = $invitation->token ?? Str::random(64);
                $invitation->save();
            }
            
            $landlord = $unit->property->landlord ?? null;
            
            Mail::to($tenant->email)
                ->send(new LeaseInvitationMail($tenant, $unit, $lease, $invitation, $landlord));
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to send lease email invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    private function sendLeaseSmsInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
    {
        try {
            $message = "📋 *LEASE AGREEMENT FOR REVIEW*\n\n" .
                      "Hello {$tenant->name},\n\n" .
                      "A lease agreement has been prepared for:\n" .
                      "📍 {$unit->property->property_name}, Unit {$unit->unit_number}\n" .
                      "📅 Lease Term: {$lease->start_date->format('M j, Y')} to {$lease->end_date->format('M j, Y')}\n" .
                      "💰 Monthly Rent: GHS {$lease->monthly_rent}\n" .
                      "⚖️ Security Deposit: GHS {$lease->security_deposit}\n\n" .
                      "Please review and sign the lease agreement:\n" .
                      $invitation->getInvitationUrl() . "\n\n" .
                      "Link expires: {$invitation->expires_at->format('M j, Y')}\n" .
                      "Contact landlord for questions.";

            $result = app(\App\Services\SmsService::class)->sendSMS(
                app(\App\Services\SmsService::class)->getDefaultProvider() ?? 'arkesel',
                $tenant->phone,
                $message
            );

            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Lease SMS sent'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send lease SMS: ' . $e->getMessage()
            ];
        }
    }

    private function sendLeaseWhatsAppInvitation(User $tenant, PropertyUnit $unit, RentalAgreement $lease, TenantInvitation $invitation): array
    {
        try {
            $message = "📋 *LEASE AGREEMENT FOR REVIEW*\n\n" .
                      "Hello {$tenant->name},\n\n" .
                      "A lease agreement has been prepared for:\n" .
                      "📍 {$unit->property->property_name}, Unit {$unit->unit_number}\n" .
                      "📅 Lease Term: {$lease->start_date->format('M j, Y')} to {$lease->end_date->format('M j, Y')}\n" .
                      "💰 Monthly Rent: GHS {$lease->monthly_rent}\n" .
                      "⚖️ Security Deposit: GHS {$lease->security_deposit}\n\n" .
                      "Please review and sign the lease agreement:\n" .
                      $invitation->getInvitationUrl() . "\n\n" .
                      "Link expires: {$invitation->expires_at->format('M j, Y')}\n" .
                      "Contact landlord for questions.";

            $whatsappService = app(\App\Services\WhatsAppService::class);
            
            if (method_exists($whatsappService, 'send')) {
                $result = $whatsappService->send($tenant->phone, $message);
            } else if (method_exists($whatsappService, 'sendMessage')) {
                $result = $whatsappService->sendMessage($tenant->phone, $message);
            } else {
                return $this->sendLeaseSmsInvitation($tenant, $unit, $lease, $invitation);
            }
            
            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'WhatsApp message sent'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send lease WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    private function generateLeaseTerms(Request $request, PropertyUnit $unit): string
    {
        $terms = [];

        if ($request->include_standard_terms) {
            $terms[] = "1. PARTIES: This Lease Agreement is made between {$unit->property->landlord->name} (Landlord) and {$unit->tenant->name} (Tenant).";
            $terms[] = "2. PROPERTY: {$unit->property->property_name}, Unit {$unit->unit_number}.";
            $terms[] = "3. TERM: From {$request->start_date} to " . ($request->end_date ?? Carbon::parse($request->start_date)->addMonths($request->duration_months)->format('Y-m-d')) . ".";
            $terms[] = "4. RENT: GHS {$request->monthly_rent} per month, due on the {$request->payment_due_day}th of each month.";
            $terms[] = "5. SECURITY DEPOSIT: GHS {$request->security_deposit} to be held by Landlord.";
            $terms[] = "6. LATE FEE: {$request->late_fee_percentage}% of monthly rent if rent is not paid within {$request->grace_period_days} days of due date.";
            $terms[] = "7. UTILITIES: Tenant responsible for all utilities unless otherwise specified.";
            $terms[] = "8. MAINTENANCE: Landlord responsible for major repairs, Tenant responsible for minor repairs and upkeep.";
            $terms[] = "9. TERMINATION: {$request->notice_period_days} days written notice required for termination.";
        }

        if ($request->filled('special_terms')) {
            $terms[] = "\nSPECIAL TERMS:\n" . $request->special_terms;
        }

        return implode("\n\n", $terms);
    }

    private function getLeaseTemplates(): array
    {
        return [
            'standard_12_month' => 'Standard 12-Month Lease',
            'month_to_month' => 'Month-to-Month Agreement',
            'commercial' => 'Commercial Lease',
            'student' => 'Student Housing Agreement',
            'furnished' => 'Furnished Unit Lease',
        ];
    }

    private function getPaymentTerms(): array
    {
        return [
            '1' => '1st of each month',
            '5' => '5th of each month',
            '10' => '10th of each month',
            '15' => '15th of each month',
            '20' => '20th of each month',
            '25' => '25th of each month',
        ];
    }

    private function getPenaltyTerms(): array
    {
        return [
            '5' => '5% Late Fee',
            '10' => '10% Late Fee',
            '15' => '15% Late Fee',
            '20' => '20% Late Fee',
            'fixed_50' => 'Fixed GHS 50 Late Fee',
            'fixed_100' => 'Fixed GHS 100 Late Fee',
        ];
    }

    private function getMaintenanceTerms(): array
    {
        return [
            'landlord_all' => 'Landlord responsible for all maintenance',
            'tenant_minor' => 'Tenant responsible for minor repairs (< GHS 100)',
            'shared' => 'Shared maintenance responsibilities',
            'tenant_all' => 'Tenant responsible for all maintenance',
        ];
    }

    private function checkUnitAccessWithError(PropertyUnit $unit): void
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }

        if ($user->isDeveloper()) {
            abort(403, 'Developers do not have access to property units.');
        }

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id === $user->id) {
                return;
            }
            abort(403, 'You can only view units in your own properties.');
        }

        if ($user->isTenant()) {
            if ($unit->tenant_id === $user->id && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED) {
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
                'user_id' => $userId,
                'type' => $type,
                'description' => $description,
                'unit_id' => $unitId,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }

    private function clearUnitCaches(int $userId): void
    {
        Cache::forget('property_unit_stats_' . $userId);
        Cache::forget('dashboard_stats_' . $userId);
        Cache::forget('tenant_unit_' . $userId);
    }
}