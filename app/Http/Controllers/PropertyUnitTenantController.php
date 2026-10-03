<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\User;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Models\RentalAgreement;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Mail\TenantInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon; 
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Cache;

use App\Notifications\TenantApprovalRequested;
use App\Notifications\TenantApproved;
use App\Notifications\TenantAssigned;

class PropertyUnitTenantController extends Controller
{
    protected $multiChannelInvitationService;
    protected $smsService;
    protected $emailService;
    protected $whatsappService;

    public function __construct(
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService
    ) {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
    }

    // ========== TENANT ASSIGNMENT ==========

    public function showAssignTenantForm($id)
    {
        $unit = PropertyUnit::with(['property'])->findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->route('property-units.show', $id)
                    ->with('error', 'Only the property owner can assign tenants.');
            }
        } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'Only property owners and administrators can assign tenants.');
        }

        if (!$unit->is_available || $unit->status !== PropertyUnit::STATUS_AVAILABLE) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'Unit is not available for tenant assignment.');
        }
        
        $availableTenants = User::where('type', User::TYPE_TENANT)
            ->where('status', 'active')
            ->whereDoesntHave('propertyUnits', function ($query) {
                $query->whereIn('tenant_status', [
                    PropertyUnit::TENANT_STATUS_APPROVED,
                    PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
                ]);
            })
            ->orderBy('name')
            ->get();

        $propertyTenants = User::where('type', User::TYPE_TENANT)
            ->where('status', 'active')
            ->whereHas('propertyUnits.property', function($query) use ($user) {
                if ($user->isLandlord()) {
                    $query->where('landlord_id', $user->id);
                }
            })
            ->orderBy('name')
            ->get();

        $currentlyAssigned = PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->whereNotNull('tenant_id')
            ->where('id', '!=', $id)
            ->when($user->isLandlord(), function ($query) use ($user) {
                return $query->whereHas('property', function ($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                });
            })
            ->with('tenant')
            ->get()
            ->pluck('tenant')
            ->filter()
            ->unique('id');

        $allowedDocuments = ['id_card', 'passport', 'drivers_license', 'employment_letter', 'bank_statement', 'reference_letter'];
        $maxFileSize = config('filesystems.max_upload_size', 5120);

        return view('property_units.assign-tenant', [
            'unit' => $unit,
            'availableTenants' => $availableTenants,
            'propertyTenants' => $propertyTenants,
            'currentlyAssigned' => $currentlyAssigned,
            'allowedDocuments' => $allowedDocuments,
            'maxFileSize' => $maxFileSize,
        ]);
    }

    public function assignTenant(Request $request, $id)
    {
        $unit = PropertyUnit::findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()
                    ->with('error', 'Only the property owner can assign tenants to units.')
                    ->withInput();
            }
        } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized to assign tenants.');
        }

        if (!$unit->is_available || $unit->status !== PropertyUnit::STATUS_AVAILABLE) {
            return redirect()->back()
                ->with('error', 'Unit is not available for tenant assignment.')
                ->withInput();
        }

        if ($unit->tenant_id && in_array($unit->tenant_status, [
            PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
            PropertyUnit::TENANT_STATUS_APPROVED
        ])) {
            return redirect()->back()
                ->with('error', 'This unit already has a tenant assigned or pending assignment.')
                ->withInput();
        }

        if ($request->filled('tenant_id')) {
            return $this->assignExistingTenant($request, $unit);
        } else {
            return $this->addNewTenant($request, $unit);
        }
    }

    /**
     * Assign an existing tenant to a unit
     * UPDATED: Made proposed_rent optional
     */
    private function assignExistingTenant(Request $request, PropertyUnit $unit)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:users,id',
            'move_in_date' => 'required|date|after_or_equal:today',
            'proposed_rent' => 'nullable|numeric|min:0', // CHANGED: removed required and min/max constraints
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . config('filesystems.max_upload_size', 5120),
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $tenant = User::find($request->tenant_id);
        if (!$tenant->isTenant()) {
            return redirect()->back()
                ->with('error', 'Selected user is not a registered tenant.')
                ->withInput();
        }

        $alreadyAssigned = PropertyUnit::where('tenant_id', $tenant->id)
            ->whereIn('tenant_status', [
                PropertyUnit::TENANT_STATUS_APPROVED,
                PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
            ])
            ->exists();

        if ($alreadyAssigned) {
            return redirect()->back()
                ->with('error', 'This tenant is already assigned or pending assignment to another unit.')
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $documents = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store("tenant_documents/{$tenant->id}", 'private');
                    $documents[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'type' => $file->getClientMimeType(),
                        'uploaded_at' => now()->toISOString()
                    ];
                }
            }

            $unit->tenant_id = $tenant->id;
            $unit->tenant_status = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
            $unit->tenant_type = 'existing';
            $unit->tenant_requested_by = auth()->id();
            $unit->tenant_requested_at = now();
            $unit->tenant_documents = $documents;
            $unit->proposed_rent = $request->proposed_rent ?? $unit->monthly_rent; // CHANGED: Use unit rent if not provided
            $unit->tenant_approval_notes = $request->notes;
            $unit->tenant_move_in_date = Carbon::parse($request->move_in_date);
            $unit->status = PropertyUnit::STATUS_RESERVED;
            $unit->is_available = false;
            
            $unit->save();

            $this->createTenantAssignmentNotification($unit, $tenant, $request->notes, 'existing');
            $this->createTenantAssignmentNotificationForTenant($unit, $tenant);

            $this->logActivity(
                auth()->id(),
                'tenant_assignment_requested',
                'Existing tenant assignment requested',
                $unit->id,
                [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'proposed_rent' => $unit->proposed_rent,
                    'move_in_date' => $request->move_in_date
                ]
            );

            $this->clearUnitCaches(auth()->id());

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Tenant assignment request submitted successfully. Awaiting admin approval.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error requesting existing tenant assignment: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to request tenant assignment: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Add a new tenant to a unit
     * UPDATED: Made monthly_income, tenant_background, proposed_rent, and documents optional
     */
    private function addNewTenant(Request $request, PropertyUnit $unit)
    {
        $validator = Validator::make($request->all(), [
            'tenant_name' => 'required|string|max:255',
            'tenant_email' => 'required|email|unique:users,email',
            'tenant_phone' => 'required|string|max:15|unique:users,phone',
            'tenant_gender' => 'required|in:male,female,other',
            'tenant_national_id' => 'nullable|string|max:50',
            'move_in_date' => 'required|date|after_or_equal:today',
            'proposed_rent' => 'nullable|numeric|min:0', // CHANGED: removed required and min/max constraints
            'employment_status' => 'required|in:employed,self_employed,student,unemployed,retired',
            'monthly_income' => 'nullable|numeric|min:0', // CHANGED: removed required and min constraint
            'company_name' => 'nullable|string|max:255', // Keep as nullable
            'job_title' => 'nullable|string|max:255', // Keep as nullable
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:15',
            'emergency_contact_relationship' => 'required|string|max:100',
            'tenant_background' => 'nullable|string|max:1000', // CHANGED: removed required and min length
            'previous_landlord_reference' => 'nullable|string|max:500',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . config('filesystems.max_upload_size', 5120), // CHANGED: removed required
            'invitation_channels' => 'required|array|min:1',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $tenantData = [
                'name' => $request->tenant_name,
                'email' => $request->tenant_email,
                'phone' => $request->tenant_phone,
                'gender' => $request->tenant_gender,
                'national_id' => $request->tenant_national_id,
                'employment_status' => $request->employment_status,
                'monthly_income' => $request->monthly_income, // Now nullable
                'company_name' => $request->company_name,
                'job_title' => $request->job_title,
                'emergency_contact' => [
                    'name' => $request->emergency_contact_name,
                    'phone' => $request->emergency_contact_phone,
                    'relationship' => $request->emergency_contact_relationship,
                ],
                'background' => $request->tenant_background, // Now nullable
                'previous_landlord_reference' => $request->previous_landlord_reference,
                'invitation_channels' => $request->invitation_channels,
                'submitted_at' => now()->toISOString(),
                'submitted_by' => auth()->id(),
            ];

            $documents = [];
            if ($request->hasFile('documents')) { // CHANGED: Check if files exist
                foreach ($request->file('documents') as $file) {
                    $path = $file->store("pending_tenants/{$request->tenant_email}", 'private');
                    $documents[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'type' => $file->getClientMimeType(),
                        'uploaded_at' => now()->toISOString()
                    ];
                }
            }

            $unit->tenant_type = 'new';
            $unit->tenant_status = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
            $unit->tenant_requested_by = auth()->id();
            $unit->tenant_requested_at = now();
            $unit->tenant_details = $tenantData;
            $unit->tenant_documents = $documents; // Now may be empty array
            $unit->proposed_rent = $request->proposed_rent ?? $unit->monthly_rent; // CHANGED: Use unit rent if not provided
            $unit->tenant_approval_notes = $request->notes;
            $unit->tenant_move_in_date = Carbon::parse($request->move_in_date);
            $unit->invitation_channels = $request->invitation_channels;
            $unit->status = PropertyUnit::STATUS_RESERVED;
            $unit->is_available = false;
            
            $unit->save();

            $this->createNewTenantAssignmentNotification($unit, $tenantData, $request->notes);

            $this->logActivity(
                auth()->id(),
                'new_tenant_assignment_requested',
                'New tenant assignment requested',
                $unit->id,
                [
                    'tenant_email' => $request->tenant_email,
                    'tenant_phone' => $request->tenant_phone,
                    'tenant_name' => $request->tenant_name,
                    'proposed_rent' => $unit->proposed_rent,
                ]
            );

            $this->clearUnitCaches(auth()->id());

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'New tenant application submitted successfully. Awaiting admin approval.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error requesting new tenant assignment: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to submit new tenant application: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== TENANT APPROVAL ==========

    public function pendingApprovals(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to pending approvals.');
        }

        try {
            $cacheKey = 'pending_approvals_' . $user->id . '_' . md5($request->fullUrl());
            
            $data = Cache::remember($cacheKey, 3600, function () use ($user, $request) {
                $query = PropertyUnit::with(['property', 'tenant', 'requestedBy'])
                    ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                    ->orderBy('tenant_requested_at', 'asc');

                if ($user->isLandlord()) {
                    $query->whereHas('property', function ($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    });
                }

                if ($request->filled('tenant_type')) {
                    $query->where('tenant_type', $request->tenant_type);
                }

                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('unit_number', 'like', "%{$search}%")
                            ->orWhere('unit_name', 'like', "%{$search}%")
                            ->orWhere('tenant_email', 'like', "%{$search}%")
                            ->orWhereHas('property', function ($q) use ($search) {
                                $q->where('property_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('tenant', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                }

                if ($request->filled('property_id')) {
                    $query->where('property_id', $request->property_id);
                }

                $pendingUnits = $query->paginate(15);
                
                $properties = collect();
                if ($user->isAdmin() || $user->isSuperAdmin()) {
                    $properties = Property::orderBy('property_name')->get();
                } elseif ($user->isLandlord()) {
                    $properties = Property::where('landlord_id', $user->id)
                        ->orderBy('property_name')
                        ->get();
                }

                $stats = [
                    'total_pending' => $pendingUnits->total(),
                    'new_tenants' => $query->clone()->where('tenant_type', 'new')->count(),
                    'existing_tenants' => $query->clone()->where('tenant_type', 'existing')->count(),
                    'today_requests' => $query->clone()->whereDate('tenant_requested_at', today())->count(),
                ];

                return compact('pendingUnits', 'properties', 'stats');
            });

            return view('property_units.pending-approvals', array_merge($data, ['request' => $request, 'user' => $user]));

        } catch (\Exception $e) {
            Log::error('Error loading pending approvals: ' . $e->getMessage());
            
            return redirect()->route('property-units.index')
                ->with('error', 'Unable to load pending approvals. Please try again.');
        }
    }

    /**
 * Approve a tenant assignment
 * 
 * UPDATED: Only landlord can propose move_in_date and final_rent_amount
 * Admin approval should NOT modify these values - they are fixed as set by landlord
 */
public function approveTenant(Request $request, $id)
{
    $user = auth()->user();
    
    // Only admins can approve (landlords can't approve)
    if (!$user->isAdmin() && !$user->isSuperAdmin()) {
        return redirect()->back()
            ->with('error', 'Only administrators can approve tenant assignments.')
            ->withInput();
    }

    $unit = PropertyUnit::with(['property'])->findOrFail($id);

    if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
        return redirect()->back()
            ->with('error', 'This tenant assignment is not pending approval.')
            ->withInput();
    }

    // ============================================================
    // FIXED: Move-in date and final rent are set by landlord during assignment
    // Admin should NOT modify these values - they are fixed
    // ============================================================
    $validator = Validator::make($request->all(), [
        'approval_notes' => 'required|string|min:20|max:500',
        // FIXED: Removed move_in_date and final_rent_amount from validation
        'send_invitation' => 'nullable|boolean',
        'invitation_channels' => 'nullable|required_if:send_invitation,1|array|min:1',
        'invitation_channels.*' => 'in:sms,email,whatsapp',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    DB::beginTransaction();
    try {
        if ($unit->tenant_type === 'existing') {
            $this->approveExistingTenant($unit, $request);
        } else {
            $this->approveNewTenant($unit, $request);
        }

        $this->clearUnitCaches($unit->tenant_requested_by ?? $unit->property->landlord_id);

        DB::commit();

        return redirect()->route('property-units.show', $unit->id)
            ->with('success', 'Tenant assignment approved successfully.' . 
                ($request->send_invitation ? ' Invitation sent to tenant.' : ''));

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error approving tenant assignment: ' . $e->getMessage());

        return redirect()->back()
            ->with('error', 'Failed to approve tenant assignment: ' . $e->getMessage())
            ->withInput();
    }
}

/**
 * Approve an existing tenant
 * 
 * UPDATED: Uses the unit's existing values for move_in_date and rent
 * These were set by the landlord during assignment
 */
private function approveExistingTenant(PropertyUnit $unit, Request $request): void
{
    $tenant = User::find($unit->tenant_id);

    $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
    $unit->approved_by = auth()->id();
    $unit->tenant_approved_at = now();
    $unit->tenant_approval_notes = $request->approval_notes;
    
    // FIXED: Use existing values from the unit (set by landlord)
    // Do NOT override with request values
    // $unit->tenant_move_in_date = Carbon::parse($request->move_in_date); // REMOVED
    // $unit->current_rent_amount = $request->final_rent_amount; // REMOVED
    
    // Keep the values that were set by the landlord
    $unit->status = PropertyUnit::STATUS_OCCUPIED;
    $unit->is_available = false;
    $unit->save();

    $this->createTenantApprovalNotification($unit, 'existing', false, $request->approval_notes);

    $this->logActivity(
        auth()->id(),
        'tenant_approved',
        'Existing tenant approved for unit',
        $unit->id,
        [
            'tenant_id' => $tenant->id,
            'final_rent' => $unit->current_rent_amount ?? $unit->monthly_rent,
            'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
        ]
    );
}

/**
 * Approve a new tenant
 * 
 * UPDATED: Uses the unit's existing values for move_in_date and rent
 * These were set by the landlord during assignment
 */
private function approveNewTenant(PropertyUnit $unit, Request $request): void
{
    $tenantDetails = $unit->tenant_details;

    try {
        $tenant = User::create([
            'name' => $tenantDetails['name'],
            'email' => $tenantDetails['email'],
            'phone' => $tenantDetails['phone'],
            'gender' => $tenantDetails['gender'] ?? 'other',
            'national_id' => $tenantDetails['national_id'] ?? null,
            'type' => User::TYPE_TENANT,
            'status' => User::STATUS_PENDING,
            'password' => Hash::make(Str::random(12)),
            'employment_status' => $tenantDetails['employment_status'] ?? 'employed',
            'monthly_income' => $tenantDetails['monthly_income'] ?? null,
            'company_name' => $tenantDetails['company_name'] ?? null,
            'job_title' => $tenantDetails['job_title'] ?? null,
            'emergency_contact' => $tenantDetails['emergency_contact'] ?? [],
            'created_by' => auth()->id(),
        ]);

        $userInvitation = \App\Models\UserInvitation::create([
            'user_id' => $tenant->id,
            'token' => Str::random(64),
            'invited_by' => auth()->id(),
            'purpose' => 'tenant_registration',
            'channels' => $request->invitation_channels ?? $unit->invitation_channels ?? ['email'],
            'status' => 'pending',
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
            'metadata' => [
                'property_name' => $unit->property->property_name ?? 'Unknown',
                'unit_number' => $unit->unit_number ?? 'Unknown',
                'landlord_name' => $unit->property->landlord->name ?? 'Unknown',
                // FIXED: Use existing values from the unit
                'monthly_rent' => $unit->proposed_rent ?? $unit->monthly_rent ?? 0,
                'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
            ]
        ]);

        $tenantInvitation = TenantInvitation::create([
            'user_id' => $tenant->id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'invited_by' => auth()->id(),
            'user_invitation_id' => $userInvitation->id,
            'channels' => $request->invitation_channels ?? $unit->invitation_channels ?? ['email'],
            'status' => TenantInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
            'token' => Str::random(64),
            'metadata' => [
                'property_name' => $unit->property->property_name,
                'unit_number' => $unit->unit_number,
                'landlord_name' => $unit->property->landlord->name ?? 'N/A',
                // FIXED: Use existing values from the unit
                'monthly_rent' => $unit->proposed_rent ?? $unit->monthly_rent ?? 0,
                'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                'purpose' => 'new_tenant_registration',
                'tenant_details' => $tenantDetails
            ]
        ]);

        // Handle documents - move from pending to tenant storage
        $newDocuments = [];
        if (!empty($unit->tenant_documents)) {
            foreach ($unit->tenant_documents as $document) {
                if (isset($document['path'])) {
                    $newPath = str_replace(
                        "pending_tenants/" . ($tenantDetails['email'] ?? 'unknown'),
                        "tenant_documents/{$tenant->id}",
                        $document['path']
                    );
                    
                    if (Storage::disk('private')->exists($document['path'])) {
                        Storage::disk('private')->move($document['path'], $newPath);
                    }
                    
                    $newDocuments[] = [
                        'name' => $document['name'] ?? 'document',
                        'path' => $newPath,
                        'type' => $document['type'] ?? 'application/pdf',
                        'uploaded_at' => $document['uploaded_at'] ?? now()->toISOString()
                    ];
                }
            }
        }

        // FIXED: Use existing values from the unit (set by landlord)
        $unit->tenant_id = $tenant->id;
        $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
        $unit->approved_by = auth()->id();
        $unit->tenant_approved_at = now();
        $unit->tenant_approval_notes = $request->approval_notes;
        
        // Do NOT override with request values - keep landlord-set values
        // $unit->tenant_move_in_date = Carbon::parse($request->move_in_date); // REMOVED
        // $unit->current_rent_amount = $request->final_rent_amount; // REMOVED
        
        // Keep the values that were set by the landlord
        $unit->current_rent_amount = $unit->proposed_rent ?? $unit->monthly_rent;
        $unit->status = PropertyUnit::STATUS_OCCUPIED;
        $unit->is_available = false;
        $unit->tenant_documents = $newDocuments;
        $unit->security_deposit = $unit->security_deposit; // Keep existing
        $unit->save();

        // Send invitations if requested
        if ($request->send_invitation) {
            $invitationResult = $this->sendTenantInvitation(
                $tenant, 
                $unit, 
                $request->invitation_channels ?? $unit->invitation_channels ?? ['email'], 
                $tenantInvitation
            );
            
            if ($invitationResult['success']) {
                $tenantInvitation->update([
                    'status' => TenantInvitation::STATUS_SENT,
                    'sent_at' => now(),
                    'channels_successful' => $invitationResult['channels_successful'] ?? []
                ]);
                
                $userInvitation->update([
                    'status' => 'sent',
                    'sent_at' => now()
                ]);
            }
        }

        $this->createTenantApprovalNotification(
            $unit, 
            'new', 
            $request->send_invitation ?? false, 
            $request->approval_notes
        );

        if (!$request->send_invitation && $tenant->email) {
            try {
                Mail::to($tenant->email)->send(new \App\Mail\TenantApprovalNotification(
                    $tenant,
                    $unit,
                    $unit->property->landlord
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send approval notification: ' . $e->getMessage());
            }
        }

        $this->logActivity(
            auth()->id(),
            'new_tenant_created_approved',
            'New tenant created and approved',
            $unit->id,
            [
                'tenant_id' => $tenant->id,
                'final_rent' => $unit->current_rent_amount,
                'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                'invitation_sent' => $request->send_invitation ?? false,
                'invitation_channels' => $request->invitation_channels ?? [],
            ]
        );

    } catch (\Exception $e) {
        Log::error('Error approving new tenant: ' . $e->getMessage());
        throw $e;
    }
}

public function getBulkSelection(Request $request)
{
    $user = auth()->user();
    
    if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    $query = PropertyUnit::with(['property', 'tenant', 'requestedBy'])
        ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
        ->orderBy('tenant_requested_at', 'asc');
    
    // Filter by property
    if ($request->filled('property_id')) {
        $query->where('property_id', $request->property_id);
    }
    
    // Filter by tenant type
    if ($request->filled('tenant_type')) {
        $query->where('tenant_type', $request->tenant_type);
    }
    
    // Filter by date range
    if ($request->filled('date_from')) {
        $query->whereDate('tenant_requested_at', '>=', $request->date_from);
    }
    
    if ($request->filled('date_to')) {
        $query->whereDate('tenant_requested_at', '<=', $request->date_to);
    }
    
    // For landlords, filter by their properties
    if ($user->isLandlord()) {
        $query->whereHas('property', function ($q) use ($user) {
            $q->where('landlord_id', $user->id);
        });
    }
    
    $units = $query->get();
    
    $unitData = [];
    foreach ($units as $unit) {
        $data = [
            'id' => $unit->id,
            'property_name' => $unit->property->property_name ?? 'N/A',
            'unit_number' => $unit->unit_number,
            'tenant_type' => $unit->tenant_type,
            'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
            'requested_date' => $unit->tenant_requested_at->format('M d, Y'),
            'tenant_name' => $unit->requestedBy->name ?? 'N/A',
            'is_landlord' => $user->isLandlord()
        ];
        
        // Only include rent for landlords
        if ($user->isLandlord()) {
            $data['proposed_rent'] = $unit->proposed_rent ?? $unit->monthly_rent ?? 0;
            $data['base_rent'] = $unit->monthly_rent ?? 0;
        }
        
        $unitData[] = $data;
    }
    
    return response()->json([
        'success' => true,
        'units' => $unitData
    ]);
}

    // ========== TENANT VACATE/TERMINATE ==========

    public function markVacatedForm($id)
    {
        $unit = PropertyUnit::findOrFail($id);
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isSuperAdmin() && 
            ($user->isLandlord() && $unit->property->landlord_id !== $user->id)) {
            abort(403, 'You do not have permission to access this page.');
        }
        
        if (!$unit->tenant) {
            return redirect()->route('property-units.show', $unit->id)
                ->with('error', 'This unit does not have a tenant assigned.');
        }
        
        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
            return redirect()->route('property-units.show', $unit->id)
                ->with('error', 'Tenant must be approved before marking as vacated.');
        }
        
        return view('property_units.mark-vacated-form', compact('unit'));
    }

    /**
 * Mark a tenant as vacated from a unit
 * UPDATED: Fully aligned with the mark-vacated-form.blade.php
 * 
 * @param Request $request
 * @param int $id
 * @return \Illuminate\Http\RedirectResponse
 */
public function markVacated(Request $request, $id)
{
    $unit = PropertyUnit::with(['tenant', 'property'])->findOrFail($id);
    $user = auth()->user();

    // Authorization checks
    if ($user->isLandlord()) {
        if ($unit->property->landlord_id !== $user->id) {
            return redirect()->back()
                ->with('error', 'You can only mark tenants as vacated in your own properties.')
                ->withInput();
        }
    } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
        return redirect()->back()
            ->with('error', 'Unauthorized access.')
            ->withInput();
    }

    // Pre-condition checks
    if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
        return redirect()->back()
            ->with('error', 'Unit does not have an approved tenant to vacate.')
            ->withInput();
    }

    if (!$unit->tenant) {
        return redirect()->back()
            ->with('error', 'No tenant found for this unit.')
            ->withInput();
    }

    // UPDATED Validation rules to match form fields
    $validator = Validator::make($request->all(), [
        // Move-out date - matches form input
        'move_out_date' => 'required|date|before_or_equal:today',
        
        // Reason from dropdown - required
        'reason' => 'required|string|max:100',
        
        // Additional notes - optional
        'notes' => 'nullable|string|max:500',
        
        // Security deposit refund checkbox
        'refund_deposit' => 'nullable|boolean',
        
        // Refund amount - required if refund checked
        'deposit_refund_amount' => 'nullable|required_if:refund_deposit,1|numeric|min:0|max:' . ($unit->security_deposit ?? 0),
        
        // Refund notes - maps to deduction_reason
        'refund_notes' => 'nullable|required_if:refund_deposit,1|string|max:500',
        
        // Property condition - matches form radio options
        'property_condition' => 'required|in:good,fair,poor',
        
        // Extra fields from form (optional, for record keeping)
        'keys_returned' => 'nullable|boolean',
        'inspection_completed' => 'nullable|boolean',
        
        // Confirmation checkbox - must be checked
        'confirm_action' => 'required|accepted',
    ], [
        'confirm_action.accepted' => 'You must confirm that the tenant has vacated the unit.',
        'reason.required' => 'Please select a reason for vacating.',
        'move_out_date.before_or_equal' => 'Move-out date cannot be in the future.',
        'deposit_refund_amount.max' => 'Refund amount cannot exceed the security deposit of ₵' . number_format($unit->security_deposit ?? 0, 2),
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    DB::beginTransaction();
    try {
        // COMBINE reason and notes into vacate_reason
        $vacateReason = $request->reason;
        if ($request->filled('notes')) {
            $vacateReason .= ': ' . $request->notes;
        }

        // Map property condition to match controller expectations
        // Form uses 'good', 'fair', 'poor' - controller expects these values
        $propertyCondition = $request->property_condition;

        // Determine if cleaning is required based on property condition
        // This is a business logic decision - you can adjust as needed
        $cleaningRequired = ($request->property_condition === 'poor' || $request->property_condition === 'fair');
        
        // Set damages noted based on property condition
        // You can make this more sophisticated if needed
        $damagesNoted = null;
        if ($request->property_condition === 'poor') {
            $damagesNoted = 'Significant damage reported requiring major repair.';
        } elseif ($request->property_condition === 'fair') {
            $damagesNoted = 'Minor damage requiring some repair.';
        } elseif ($request->property_condition === 'good' && $request->filled('notes')) {
            $damagesNoted = $request->notes;
        }

        // Update unit with vacated information
        $unit->tenant_status = PropertyUnit::TENANT_STATUS_VACATED;
        $unit->tenant_move_out_date = Carbon::parse($request->move_out_date);
        $unit->tenant_vacate_reason = $vacateReason;
        $unit->property_condition = $propertyCondition;
        $unit->cleaning_required = $cleaningRequired;
        $unit->damages_noted = $damagesNoted;
        $unit->status = PropertyUnit::STATUS_UNDER_MAINTENANCE;
        $unit->is_available = false;
        $unit->available_from = Carbon::parse($request->move_out_date)->addDays(7);
        
        // Store extra form fields in metadata for record keeping
        $unit->metadata = array_merge($unit->metadata ?? [], [
            'keys_returned' => $request->has('keys_returned'),
            'inspection_completed' => $request->has('inspection_completed'),
            'vacated_by' => auth()->id(),
            'vacated_at' => now()->toISOString(),
        ]);

        $unit->save();

        // Process deposit refund if requested
        if ($request->has('refund_deposit') && $request->refund_deposit) {
            $this->processDepositRefund(
                $unit, 
                $request->deposit_refund_amount, 
                $request->refund_notes // Maps to deduction_reason
            );
        }

        // Terminate active lease if exists
        if ($unit->currentLease) {
            $unit->currentLease->update([
                'status' => 'terminated',
                'end_date' => $request->move_out_date,
                'termination_reason' => $vacateReason,
                'terminated_by' => auth()->id(),
                'terminated_at' => now(),
            ]);
        }

        // Create notifications
        $this->createTenantVacatedNotification($unit, $vacateReason, $request->deposit_refund_amount ?? 0);

        // Log activity with all form data
        $this->logActivity(
            $user->id,
            'tenant_vacated',
            'Tenant marked as vacated',
            $unit->id,
            [
                'tenant_id' => $unit->tenant_id,
                'tenant_name' => $unit->tenant->name,
                'move_out_date' => $request->move_out_date,
                'reason' => $request->reason,
                'property_condition' => $request->property_condition,
                'refund_amount' => $request->deposit_refund_amount ?? 0,
                'keys_returned' => $request->has('keys_returned'),
                'inspection_completed' => $request->has('inspection_completed'),
            ]
        );

        // Clear relevant caches
        $this->clearUnitCaches($user->id);
        Cache::forget('property_unit_' . $unit->id);
        Cache::forget('property_unit_stats_' . $unit->property_id);

        DB::commit();

        // Success message with summary
        $message = 'Tenant marked as vacated successfully. Unit is now under maintenance.';
        if ($request->has('refund_deposit') && $request->refund_deposit) {
            $message .= ' Refund of ₵' . number_format($request->deposit_refund_amount, 2) . ' processed.';
        }

        return redirect()->route('property-units.show', $unit->id)
            ->with('success', $message);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error marking tenant as vacated: ' . $e->getMessage(), [
            'unit_id' => $unit->id,
            'user_id' => auth()->id(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to mark tenant as vacated: ' . $e->getMessage())
            ->withInput();
    }
}

public function showForTenant($id)
{
    $user = auth()->user();
    
    if (!$user->isTenant()) {
        abort(403, 'This view is only available for tenants.');
    }

    try {
        $cacheKey = 'property_unit_' . $id . '_with_tenant_relations';
        $unit = Cache::remember($cacheKey, 3600, function () use ($id, $user) {
            return PropertyUnit::with([
                'property', 
                'tenant', 
                'currentLease',
                'property.landlord',
                'maintenanceRequests' => function ($query) use ($user) {
                    $query->where(function($q) use ($user) {
                        $q->where('tenant_id', $user->id)
                          ->orWhere('created_by', $user->id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(10);
                },
                'invoices' => function ($query) use ($user) {
                    $query->where('tenant_id', $user->id)
                        ->orderBy('due_date', 'desc')
                        ->limit(10);
                }
            ])->findOrFail($id);
        });

        if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
            abort(403, 'You can only view your assigned unit.');
        }

        $statsCacheKey = 'unit_stats_tenant_' . $id . '_' . $user->id;
        $stats = Cache::remember($statsCacheKey, 3600, function () use ($unit, $user) {
            return [
                'total_invoices' => $unit->invoices()->where('tenant_id', $user->id)->count(),
                'paid_invoices' => $unit->invoices()
                    ->where('tenant_id', $user->id)
                    ->where('status', 'paid')
                    ->count(),
                'pending_invoices' => $unit->invoices()
                    ->where('tenant_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'overdue_invoices' => $unit->invoices()
                    ->where('tenant_id', $user->id)
                    ->where('status', 'overdue')
                    ->count(),
                'total_rent_paid' => $unit->invoices()
                    ->where('tenant_id', $user->id)
                    ->where('status', 'paid')
                    ->sum('amount'),
                'active_maintenance_requests' => $unit->maintenanceRequests()
                    ->where(function ($query) use ($user) {
                        $query->where('tenant_id', $user->id)
                            ->orWhere('created_by', $user->id);
                    })
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count(),
                'outstanding_balance' => $unit->invoices()
                    ->where('tenant_id', $user->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->sum('amount'),
            ];
        });

        $amenityOptions = $this->getAmenityOptions();
        
        $leaseDetails = null;
        if ($unit->currentLease) {
            $leaseDetails = [
                'start_date' => $unit->currentLease->start_date->format('M d, Y'),
                'end_date' => $unit->currentLease->end_date->format('M d, Y'),
                'monthly_rent' => $unit->currentLease->monthly_rent,
                'security_deposit' => $unit->currentLease->security_deposit,
                'status' => $unit->currentLease->status,
                'signed_by_landlord' => !empty($unit->currentLease->landlord_signed_at),
                'signed_by_tenant' => !empty($unit->currentLease->tenant_signed_at),
            ];
        }

        $sidebarUnreadCount = 0;

        // FORCE USE TENANT VIEW - Always use tenant view for tenants
        return view('tenant.property_units.show', compact(
            'unit', 
            'stats', 
            'amenityOptions',
            'leaseDetails',
            'user',
            'sidebarUnreadCount'
        ));

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->route('dashboard')
            ->with('error', 'Property unit not found or you no longer have access.');
    }
}


    public function tenantUnitView()
    {
        $user = auth()->user();
        
        if (!$user->isTenant()) {
            return redirect()->route('dashboard')
                ->with('error', 'This feature is only available for tenants.');
        }
        
        $cacheKey = 'tenant_unit_' . $user->id;
        $unit = Cache::remember($cacheKey, 3600, function () use ($user) {
            return PropertyUnit::where('tenant_id', $user->id)
                ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
                ->with(['property', 'currentLease', 'property.landlord'])
                ->first();
        });

        if (!$unit) {
            $pendingUnit = PropertyUnit::where('tenant_id', $user->id)
                ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                ->first();
            
            if ($pendingUnit) {
                return view('tenant.dashboard-pending-unit', [
                    'user' => $user,
                    'pendingUnit' => $pendingUnit,
                    'pageTitle' => 'Unit Assignment Pending'
                ]);
            }
            
            return view('tenant.dashboard-no-unit', [
                'user' => $user,
                'pageTitle' => 'No Unit Assigned'
            ]);
        }

        $data = [
            'user' => $user,
            'unit' => $unit,
            'isTenant' => true,
            'pageTitle' => $unit ? 'My Unit - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number : 'My Unit'
        ];
        
        if ($unit) {
            $recentActivities = \App\Models\ActivityLog::where('user_id', $user->id)
                ->orWhere(function($query) use ($unit) {
                    $query->where('unit_id', $unit->id)
                          ->whereIn('type', ['maintenance_request_created', 'invoice_generated']);
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            $data['recentActivities'] = $recentActivities;
        }
        
        return view('tenant.property_units.my-unit', $data);
    }

    // ========== HELPER METHODS ==========

    private function sendTenantInvitation(User $tenant, PropertyUnit $unit, array $channels, TenantInvitation $invitation): array
    {
        try {
            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];
            
            foreach ($channels as $channel) {
                try {
                    $result = $this->sendInvitationViaChannel($tenant, $unit, $invitation, $channel);
                    
                    $results[$channel] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending invitation via {$channel}: " . $e->getMessage());
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage()
                    ];
                }
            }

            $this->logActivity(
                auth()->id(),
                'tenant_invitation_sent',
                'Tenant invitation sent',
                $unit->id,
                [
                    'tenant_id' => $tenant->id,
                    'channels' => $channelsSuccessful,
                    'success_count' => $successCount
                ]
            );

            return [
                'success' => $successCount > 0,
                'invitation_id' => $invitation->id,
                'user_invitation_id' => $invitation->user_invitation_id,
                'token' => $invitation->token ?? $invitation->id,
                'invitation_url' => $invitation->getInvitationUrl(),
                'channels_successful' => $channelsSuccessful,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ];
        }
    }

    private function sendInvitationViaChannel(User $tenant, PropertyUnit $unit, TenantInvitation $invitation, string $channel): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendTenantEmailInvitation($tenant, $unit, $invitation);
            case 'sms':
                return $this->sendTenantSmsInvitation($tenant, $unit, $invitation);
            case 'whatsapp':
                return $this->sendTenantWhatsAppInvitation($tenant, $unit, $invitation);
            default:
                return [
                    'success' => false,
                    'message' => "Unknown channel: {$channel}"
                ];
        }
    }

    private function sendTenantEmailInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $userInvitation = $invitation->userInvitation;
            
            if (!$userInvitation) {
                $userInvitation = \App\Models\UserInvitation::create([
                    'user_id' => $tenant->id,
                    'token' => Str::random(64),
                    'invited_by' => auth()->id(),
                    'purpose' => 'tenant_registration',
                    'channels' => ['email'],
                    'status' => 'pending',
                    'sent_at' => now(),
                    'expires_at' => now()->addDays(7),
                    'metadata' => [
                        'property_name' => $unit->property->property_name,
                        'unit_number' => $unit->unit_number,
                        'tenant_invitation_id' => $invitation->id
                    ]
                ]);
                
                $invitation->update(['user_invitation_id' => $userInvitation->id]);
            }
            
            Mail::to($tenant->email)
                ->send(new TenantInvitationMail($tenant, $unit->property, $invitation));
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send tenant email invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    private function sendTenantSmsInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $message = "You have been invited to rent Unit {$unit->unit_number} at {$unit->property->property_name}. Click here to accept: " . $invitation->getInvitationUrl();
            
            $result = $this->smsService->send($tenant->phone, $message);
            
            return [
                'success' => $result,
                'message' => $result ? 'SMS sent successfully' : 'Failed to send SMS'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send tenant SMS invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    private function sendTenantWhatsAppInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $message = "You have been invited to rent Unit {$unit->unit_number} at {$unit->property->property_name}. Click here to accept: " . $invitation->getInvitationUrl();
            
            $result = $this->whatsappService->send($tenant->phone, $message);
            
            return [
                'success' => $result,
                'message' => $result ? 'WhatsApp message sent successfully' : 'Failed to send WhatsApp message'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send tenant WhatsApp invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp message: ' . $e->getMessage()
            ];
        }
    }

    private function getAmenityOptions(): array
    {
        return [
            'parking' => 'Parking Space',
            'balcony' => 'Balcony',
            'air_conditioning' => 'Air Conditioning',
            'furnished' => 'Fully Furnished',
            'wifi' => 'Wi-Fi',
            'security' => '24/7 Security',
            'gym' => 'Gym Access',
            'pool' => 'Swimming Pool',
            'laundry' => 'Laundry Facility',
            'elevator' => 'Elevator',
            'generator' => 'Backup Generator',
            'cctv' => 'CCTV Surveillance',
            'fire_safety' => 'Fire Safety System',
            'water_heater' => 'Water Heater',
            'kitchen_appliances' => 'Kitchen Appliances',
        ];
    }

    /**
 * Process security deposit refund
 * 
 * @param PropertyUnit $unit
 * @param float $amount
 * @param string|null $deductionReason
 * @return void
 */
private function processDepositRefund(PropertyUnit $unit, float $amount, ?string $deductionReason = null): void
{
    // Calculate deduction if any
    $originalDeposit = $unit->security_deposit ?? 0;
    $deduction = $originalDeposit - $amount;
    
    $this->logActivity(
        auth()->id(),
        'deposit_refund_processed',
        'Security deposit refund processed',
        $unit->id,
        [
            'amount' => $amount,
            'original_deposit' => $originalDeposit,
            'deduction' => $deduction > 0 ? $deduction : 0,
            'deduction_reason' => $deductionReason,
            'refund_date' => now()->toISOString(),
        ]
    );

    // Store refund information
    $unit->security_deposit_refunded = $amount;
    $unit->security_deposit_deduction = $deduction > 0 ? $deduction : 0;
    $unit->security_deposit_deduction_reason = $deductionReason;
    $unit->security_deposit_refunded_at = now();
    $unit->save();

    // TODO: Integrate with payment system to process actual refund
    // This would typically call a payment gateway or accounting system
}

    private function clearUnitCaches(int $userId): void
    {
        Cache::forget('property_unit_stats_' . $userId);
        Cache::forget('dashboard_stats_' . $userId);
        Cache::forget('tenant_unit_' . $userId);
    }

    /**
     * Create notification for tenant about assignment request
     */
    private function createTenantAssignmentNotificationForTenant(PropertyUnit $unit, User $tenant): void
    {
        try {
            Notification::send($tenant, new \App\Notifications\TenantAssignmentRequested(
                unit: $unit,
                requestedBy: auth()->user()
            ));
            
            Log::info('Tenant assignment notification sent to tenant', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create tenant assignment notification for tenant: ' . $e->getMessage());
        }
    }

    /**
     * Create notification for tenant assignment request (existing tenant)
     */
    private function createTenantAssignmentNotification(PropertyUnit $unit, User $tenant, ?string $notes, string $type): void
    {
        try {
            $admins = User::whereIn('type', ['admin', 'super_admin'])->get();
            
            Notification::send($admins, new \App\Notifications\TenantApprovalRequested(
                unit: $unit,
                tenantType: $type,
                tenantName: $tenant->name,
                requestedBy: auth()->user(),
                notes: $notes
            ));
            
            Log::info('Tenant approval request notification sent to admins', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'tenant_type' => $type,
                'admin_count' => $admins->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create tenant assignment notification: ' . $e->getMessage(), [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create notification for new tenant assignment request
     */
    private function createNewTenantAssignmentNotification(PropertyUnit $unit, array $tenantData, ?string $notes): void
    {
        try {
            $admins = User::whereIn('type', ['admin', 'super_admin'])->get();
            
            Notification::send($admins, new \App\Notifications\TenantApprovalRequested(
                unit: $unit,
                tenantType: 'new',
                tenantName: $tenantData['name'] ?? 'New Applicant',
                requestedBy: auth()->user(),
                notes: $notes
            ));
            
            Log::info('New tenant application notification sent to admins', [
                'unit_id' => $unit->id,
                'tenant_email' => $tenantData['email'] ?? null,
                'admin_count' => $admins->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create new tenant assignment notification: ' . $e->getMessage(), [
                'unit_id' => $unit->id,
                'tenant_email' => $tenantData['email'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
 * Create notification for tenant approval
 */
private function createTenantApprovalNotification(PropertyUnit $unit, string $tenantType, bool $invitationSent = false, ?string $approvalNotes = null): void
{
    try {
        // Notify landlord about approval
        if ($unit->property->landlord) {
            Notification::send($unit->property->landlord, new \App\Notifications\TenantApproved(
                unit: $unit,
                tenantType: $tenantType,
                approvedBy: auth()->user(),
                invitationSent: $invitationSent,
                approvalNotes: $approvalNotes
            ));
            
            Log::info('Tenant approval notification sent to landlord', [
                'unit_id' => $unit->id,
                'landlord_id' => $unit->property->landlord_id,
                'tenant_type' => $tenantType,
                'invitation_sent' => $invitationSent
            ]);
        }

        // FIXED: Notify tenant if existing tenant
        // Check if the class exists before using it
        if ($tenantType === 'existing' && $unit->tenant) {
            try {
                // Check if the notification class exists
                if (class_exists(\App\Notifications\TenantAssigned::class)) {
                    Notification::send($unit->tenant, new \App\Notifications\TenantAssigned(
                        unit: $unit,
                        landlord: $unit->property->landlord,
                        approvedBy: auth()->user()
                    ));
                    
                    Log::info('Tenant assignment notification sent to existing tenant', [
                        'unit_id' => $unit->id,
                        'tenant_id' => $unit->tenant_id
                    ]);
                } else {
                    // Fallback to a generic notification if TenantAssigned doesn't exist
                    // Use the existing notification or log a notice
                    Log::warning('TenantAssigned notification class not found, using fallback for tenant', [
                        'unit_id' => $unit->id,
                        'tenant_id' => $unit->tenant_id
                    ]);
                    
                    // Send a simple mail notification as fallback
                    if ($unit->tenant->email) {
                        Mail::to($unit->tenant->email)->send(new \App\Mail\TenantApprovalNotification(
                            $unit->tenant,
                            $unit,
                            $unit->property->landlord
                        ));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to send tenant notification: ' . $e->getMessage(), [
                    'unit_id' => $unit->id,
                    'tenant_id' => $unit->tenant_id
                ]);
            }
        }
        
    } catch (\Exception $e) {
        Log::error('Failed to create tenant approval notification: ' . $e->getMessage(), [
            'unit_id' => $unit->id,
            'tenant_type' => $tenantType,
            'error' => $e->getMessage()
        ]);
    }
}

    /**
 * Create notification for tenant vacated
 * UPDATED to include more form data
 */
private function createTenantVacatedNotification(PropertyUnit $unit, ?string $reason, ?float $refundAmount): void
{
    try {
        $landlord = $unit->property->landlord;
        $tenant = $unit->tenant;
        
        // Prepare notification data
        $notificationData = [
            'type' => 'tenant_vacated',
            'unit_id' => $unit->id,
            'unit_identifier' => $unit->full_unit_identifier,
            'property_name' => $unit->property->property_name,
            'move_out_date' => $unit->tenant_move_out_date ? $unit->tenant_move_out_date->format('Y-m-d') : null,
            'reason' => $reason,
            'refund_amount' => $refundAmount,
            'property_condition' => $unit->property_condition,
            'cleaning_required' => $unit->cleaning_required,
            'vacated_at' => now()->toISOString(),
        ];

        // Notify landlord
        if ($landlord) {
            Notification::send($landlord, new \App\Notifications\GeneralNotification(
                title: 'Tenant Vacated',
                message: "Tenant {$tenant->name} has vacated Unit {$unit->unit_number} at {$unit->property->property_name}.\n\nReason: {$reason}\nRefund Amount: ₵" . ($refundAmount ?? 0),
                icon: 'fas fa-door-open text-warning',
                category: 'tenant_vacated',
                actionUrl: route('property-units.show', $unit->id),
                priority: 1,
                data: $notificationData
            ));
            
            Log::info('Tenant vacated notification sent to landlord', [
                'unit_id' => $unit->id,
                'landlord_id' => $landlord->id
            ]);
        }

        // Notify tenant if they still exist in system
        if ($tenant) {
            Notification::send($tenant, new \App\Notifications\GeneralNotification(
                title: 'Unit Vacated',
                message: "You have been marked as vacated from Unit {$unit->unit_number} at {$unit->property->property_name}.\n\nMove-out Date: " . ($unit->tenant_move_out_date ? $unit->tenant_move_out_date->format('M d, Y') : 'N/A') . "\nRefund Amount: ₵" . ($refundAmount ?? 0),
                icon: 'fas fa-door-open text-info',
                category: 'unit_vacated',
                actionUrl: route('property-units.show', $unit->id),
                priority: 1,
                data: $notificationData
            ));
            
            Log::info('Unit vacated notification sent to tenant', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id
            ]);
        }

        // Notify admins about vacancy for re-listing
        $admins = User::whereIn('type', ['admin', 'super_admin'])->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new \App\Notifications\GeneralNotification(
                title: 'Unit Vacated - Available for Rent',
                message: "Unit {$unit->unit_number} at {$unit->property->property_name} has been vacated and will be available for rent from " . ($unit->available_from ? $unit->available_from->format('M d, Y') : 'soon'),
                icon: 'fas fa-home text-success',
                category: 'unit_available',
                actionUrl: route('property-units.show', $unit->id),
                priority: 2,
                data: $notificationData
            ));
        }
        
    } catch (\Exception $e) {
        Log::error('Failed to create tenant vacated notification: ' . $e->getMessage(), [
            'unit_id' => $unit->id,
            'error' => $e->getMessage()
        ]);
    }
}

    /**
 * View assignment details for a pending tenant
 * 
 * @param int $id
 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
 */
public function viewAssignmentDetails($id)
{
    $user = auth()->user();
    
    // Check if user has permission to view assignment details
    if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
        abort(403, 'You do not have permission to view assignment details.');
    }
    
    try {
        $unit = PropertyUnit::with([
            'property',
            'tenant',
            'requestedBy',
            'property.landlord'
        ])->findOrFail($id);
        
        // If landlord, verify they own this property
        if ($user->isLandlord() && $unit->property->landlord_id !== $user->id) {
            abort(403, 'You can only view assignment details for your own properties.');
        }
        
        // Check if this unit actually has a pending tenant assignment
        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
            return redirect()->route('property-units.show', $unit->id)
                ->with('error', 'This unit does not have a pending tenant assignment.');
        }
        
        // Get tenant details based on tenant type
        $tenantDetails = [];
        $documents = [];
        
        if ($unit->tenant_type === 'existing' && $unit->tenant) {
            // Existing tenant
            $tenantDetails = [
                'name' => $unit->tenant->name,
                'email' => $unit->tenant->email,
                'phone' => $unit->tenant->phone,
                'gender' => $unit->tenant->gender,
                'national_id' => $unit->tenant->national_id,
                'employment_status' => $unit->tenant->employment_status,
                'monthly_income' => $unit->tenant->monthly_income,
                'company_name' => $unit->tenant->company_name,
                'job_title' => $unit->tenant->job_title,
                'emergency_contact' => $unit->tenant->emergency_contact,
                'type' => 'existing'
            ];
        } elseif ($unit->tenant_type === 'new' && $unit->tenant_details) {
            // New tenant application
            $tenantDetails = $unit->tenant_details;
            $tenantDetails['type'] = 'new';
        }
        
        // Get documents if any
        if (!empty($unit->tenant_documents)) {
            $documents = $unit->tenant_documents;
        }
        
        return view('property_units.assignment-details', compact('unit', 'tenantDetails', 'documents', 'user'));
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->route('property-units.index')
            ->with('error', 'Property unit not found.');
    } catch (\Exception $e) {
        Log::error('Error viewing assignment details: ' . $e->getMessage());
        return redirect()->route('property-units.index')
            ->with('error', 'Failed to load assignment details: ' . $e->getMessage());
    }
}
/**
 * Show the bulk approval page
 * 
 * @return \Illuminate\View\View
 */
public function showBulkApproval()
{
    $user = auth()->user();
    
    // Check if user has permission (only admins/super admins)
    if (!$user->isAdmin() && !$user->isSuperAdmin()) {
        return redirect()->route('dashboard')
            ->with('error', 'You do not have permission to access bulk approval.');
    }
    
    // Get all pending tenant approvals
    $pendingUnits = PropertyUnit::with(['property', 'tenant', 'requestedBy'])
        ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
        ->when($user->isAdmin() && !$user->isSuperAdmin(), function ($query) {
            // Optional: Add any admin-specific filters
            return $query;
        })
        ->orderBy('tenant_requested_at', 'asc')
        ->paginate(20);
    
    // Get properties for filtering
    $properties = Property::orderBy('property_name')->get();
    
    // Calculate stats
    $stats = [
        'total_pending' => $pendingUnits->total(),
        'new_tenants' => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
            ->where('tenant_type', 'new')
            ->count(),
        'existing_tenants' => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
            ->where('tenant_type', 'existing')
            ->count(),
        'today_requests' => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
            ->whereDate('tenant_requested_at', today())
            ->count(),
    ];
    
    return view('property_units.bulk-approval', compact('pendingUnits', 'properties', 'stats'));
}

/**
 * Process bulk approval
 * 
 * @param Request $request
 * @return \Illuminate\Http\RedirectResponse
 */

/**
 * Process bulk approval
 * 
 * UPDATED: Move-in date can be today or any date (not just future)
 * For new tenants, it's recommended to be today or future
 * For existing tenants, it can be any date
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function bulkApprove(Request $request)
{
    $user = auth()->user();
    
    // Check permission
    if (!$user->isAdmin() && !$user->isSuperAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform bulk approvals.'
        ], 403);
    }
    
    // Log the request for debugging
    Log::info('Bulk approval request received', [
        'user_id' => $user->id,
        'is_landlord' => $user->isLandlord(),
        'request_data' => $request->all()
    ]);
    
    // ============================================================
    // Parse unit_ids - handle both string and array formats
    // ============================================================
    $unitIds = $request->unit_ids;
    
    // If it's a string, try to decode it
    if (is_string($unitIds)) {
        $decoded = json_decode($unitIds, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $unitIds = $decoded;
        } else {
            // Try parsing as comma-separated values
            $unitIds = array_map('trim', explode(',', $unitIds));
        }
    }
    
    // Ensure we have an array of integers
    $unitIds = array_filter(array_map('intval', (array) $unitIds));
    
    if (empty($unitIds)) {
        return response()->json([
            'success' => false,
            'message' => 'No valid units selected. Please select at least one unit.',
            'debug' => [
                'received' => $request->unit_ids,
                'parsed' => $unitIds
            ]
        ], 422);
    }
    
    // ============================================================
    // FIXED: Build validation rules based on user role
    // ============================================================
    $rules = [
        'approval_notes' => 'required|string|min:10|max:500',
        'send_invitations' => 'nullable|boolean',
        'invitation_channels' => 'nullable|array|required_if:send_invitations,1',
        'invitation_channels.*' => 'in:sms,email,whatsapp',
    ];
    
    // FIXED: Only landlords can set move_in_date
    // Allow any date (today or past) for move-in date
    // Landlords should be able to set move-in dates retroactively
    if ($user->isLandlord()) {
        // FIXED: Changed from 'after_or_equal:today' to just 'date'
        // This allows both past and future dates
        $rules['move_in_date'] = 'required|date';
        $rules['final_rent_amount_type'] = 'nullable|in:proposed,base,custom';
        $rules['custom_rent_amount'] = 'nullable|numeric|min:0|required_if:final_rent_amount_type,custom';
        $rules['security_deposit'] = 'nullable|numeric|min:0';
    } else {
        // Admins don't need move_in_date - it uses existing unit values
        if ($request->has('move_in_date')) {
            $rules['move_in_date'] = 'nullable|date';
        }
        // Set default values for admin
        if (!$request->has('final_rent_amount_type')) {
            $request->merge(['final_rent_amount_type' => 'proposed']);
        }
        if (!$request->has('security_deposit')) {
            $request->merge(['security_deposit' => null]);
        }
    }
    
    $validator = Validator::make($request->all(), $rules);
    
    if ($validator->fails()) {
        Log::warning('Bulk approval validation failed', [
            'errors' => $validator->errors()->toArray(),
            'request_data' => $request->all(),
            'is_landlord' => $user->isLandlord()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
            'errors' => $validator->errors()->toArray()
        ], 422);
    }
    
    DB::beginTransaction();
    try {
        $successCount = 0;
        $failedCount = 0;
        $details = [];
        $invitationSent = false;
        
        foreach ($unitIds as $unitId) {
            $unit = PropertyUnit::with(['property'])->find($unitId);
            
            if (!$unit) {
                $failedCount++;
                $details[] = [
                    'unit_id' => $unitId,
                    'success' => false,
                    'message' => 'Unit not found'
                ];
                continue;
            }
            
            // Skip if not pending
            if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
                $failedCount++;
                $details[] = [
                    'unit_id' => $unitId,
                    'success' => false,
                    'message' => 'Unit is not pending approval (current status: ' . $unit->tenant_status . ')'
                ];
                continue;
            }
            
            try {
                // ============================================================
                // Determine move_in_date and rent based on user role
                // ============================================================
                if ($user->isLandlord() && $request->has('move_in_date')) {
                    // Landlord can set these values - allow any date
                    $moveInDate = Carbon::parse($request->move_in_date);
                    
                    // Determine final rent amount
                    $finalRentAmount = null;
                    if ($request->final_rent_amount_type === 'proposed') {
                        $finalRentAmount = $unit->proposed_rent ?? $unit->monthly_rent;
                    } elseif ($request->final_rent_amount_type === 'base') {
                        $finalRentAmount = $unit->monthly_rent;
                    } elseif ($request->final_rent_amount_type === 'custom') {
                        $finalRentAmount = $request->custom_rent_amount ?? $unit->monthly_rent;
                    } else {
                        $finalRentAmount = $unit->proposed_rent ?? $unit->monthly_rent;
                    }
                    
                    $securityDeposit = $request->security_deposit ?? $unit->security_deposit;
                } else {
                    // Admin: Use existing values from the unit (set by landlord)
                    $moveInDate = $unit->tenant_move_in_date ?? now();
                    $finalRentAmount = $unit->proposed_rent ?? $unit->monthly_rent;
                    $securityDeposit = $unit->security_deposit;
                }
                
                if ($unit->tenant_type === 'existing') {
                    // Approve existing tenant
                    $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
                    $unit->approved_by = $user->id;
                    $unit->tenant_approved_at = now();
                    $unit->tenant_approval_notes = $request->approval_notes;
                    $unit->tenant_move_in_date = $moveInDate;
                    $unit->current_rent_amount = $finalRentAmount;
                    $unit->status = PropertyUnit::STATUS_OCCUPIED;
                    $unit->is_available = false;
                    $unit->security_deposit = $securityDeposit;
                    $unit->save();
                    
                    // Notify tenant if exists
                    if ($unit->tenant) {
                        try {
                            Notification::send($unit->tenant, new \App\Notifications\TenantApproved(
                                unit: $unit,
                                tenantType: 'existing',
                                approvedBy: $user,
                                invitationSent: false,
                                approvalNotes: $request->approval_notes
                            ));
                        } catch (\Exception $e) {
                            Log::warning('Failed to notify existing tenant: ' . $e->getMessage());
                        }
                    }
                    
                    $successCount++;
                    $details[] = [
                        'unit_id' => $unitId,
                        'success' => true,
                        'message' => 'Existing tenant approved successfully'
                    ];
                    
                } elseif ($unit->tenant_type === 'new') {
                    // Approve new tenant
                    $tenantDetails = $unit->tenant_details;
                    
                    if (empty($tenantDetails)) {
                        throw new \Exception('Tenant details not found');
                    }
                    
                    // Create tenant user
                    $tenant = User::create([
                        'name' => $tenantDetails['name'] ?? 'New Tenant',
                        'email' => $tenantDetails['email'] ?? null,
                        'phone' => $tenantDetails['phone'] ?? null,
                        'gender' => $tenantDetails['gender'] ?? 'other',
                        'national_id' => $tenantDetails['national_id'] ?? null,
                        'type' => User::TYPE_TENANT,
                        'status' => User::STATUS_PENDING,
                        'password' => Hash::make(Str::random(12)),
                        'employment_status' => $tenantDetails['employment_status'] ?? 'employed',
                        'monthly_income' => $tenantDetails['monthly_income'] ?? null,
                        'company_name' => $tenantDetails['company_name'] ?? null,
                        'job_title' => $tenantDetails['job_title'] ?? null,
                        'emergency_contact' => $tenantDetails['emergency_contact'] ?? [],
                        'created_by' => $user->id,
                    ]);
                    
                    // Create user invitation
                    $userInvitation = \App\Models\UserInvitation::create([
                        'user_id' => $tenant->id,
                        'token' => Str::random(64),
                        'invited_by' => $user->id,
                        'purpose' => 'tenant_registration',
                        'channels' => ($request->send_invitations && $request->has('invitation_channels')) ? 
                            $request->invitation_channels : [],
                        'status' => $request->send_invitations ? 'sent' : 'pending',
                        'sent_at' => $request->send_invitations ? now() : null,
                        'expires_at' => now()->addDays(7),
                        'metadata' => [
                            'property_name' => $unit->property->property_name ?? 'Unknown',
                            'unit_number' => $unit->unit_number ?? 'Unknown',
                            'landlord_name' => $unit->property->landlord->name ?? 'Unknown',
                            'monthly_rent' => $finalRentAmount,
                            'move_in_date' => $moveInDate->format('Y-m-d'),
                        ]
                    ]);
                    
                    // Create tenant invitation
                    $tenantInvitation = TenantInvitation::create([
                        'user_id' => $tenant->id,
                        'property_id' => $unit->property_id,
                        'unit_id' => $unit->id,
                        'invited_by' => $user->id,
                        'user_invitation_id' => $userInvitation->id,
                        'channels' => ($request->send_invitations && $request->has('invitation_channels')) ? 
                            $request->invitation_channels : [],
                        'status' => $request->send_invitations ? TenantInvitation::STATUS_SENT : TenantInvitation::STATUS_PENDING,
                        'expires_at' => now()->addDays(7),
                        'token' => Str::random(64),
                        'metadata' => [
                            'property_name' => $unit->property->property_name,
                            'unit_number' => $unit->unit_number,
                            'landlord_name' => $unit->property->landlord->name ?? 'N/A',
                            'monthly_rent' => $finalRentAmount,
                            'move_in_date' => $moveInDate->format('Y-m-d'),
                            'purpose' => 'new_tenant_registration',
                            'tenant_details' => $tenantDetails
                        ]
                    ]);
                    
                    // Handle documents
                    $newDocuments = [];
                    if (!empty($unit->tenant_documents)) {
                        foreach ($unit->tenant_documents as $document) {
                            if (isset($document['path'])) {
                                $newPath = str_replace(
                                    "pending_tenants/" . ($tenantDetails['email'] ?? 'unknown'),
                                    "tenant_documents/{$tenant->id}",
                                    $document['path']
                                );
                                
                                if (Storage::disk('private')->exists($document['path'])) {
                                    Storage::disk('private')->move($document['path'], $newPath);
                                }
                                
                                $newDocuments[] = [
                                    'name' => $document['name'] ?? 'document',
                                    'path' => $newPath,
                                    'type' => $document['type'] ?? 'application/pdf',
                                    'uploaded_at' => $document['uploaded_at'] ?? now()->toISOString()
                                ];
                            }
                        }
                    }
                    
                    // Update unit
                    $unit->tenant_id = $tenant->id;
                    $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
                    $unit->approved_by = $user->id;
                    $unit->tenant_approved_at = now();
                    $unit->tenant_approval_notes = $request->approval_notes;
                    $unit->tenant_move_in_date = $moveInDate;
                    $unit->current_rent_amount = $finalRentAmount;
                    $unit->status = PropertyUnit::STATUS_OCCUPIED;
                    $unit->is_available = false;
                    $unit->tenant_documents = $newDocuments;
                    $unit->security_deposit = $securityDeposit;
                    $unit->save();
                    
                    // Send invitations if requested
                    if ($request->send_invitations && !empty($request->invitation_channels)) {
                        try {
                            $invitationResult = $this->sendTenantInvitation(
                                $tenant, 
                                $unit, 
                                $request->invitation_channels, 
                                $tenantInvitation
                            );
                            
                            if ($invitationResult['success']) {
                                $tenantInvitation->update([
                                    'status' => TenantInvitation::STATUS_SENT,
                                    'sent_at' => now(),
                                    'channels_successful' => $invitationResult['channels_successful'] ?? []
                                ]);
                                
                                $userInvitation->update([
                                    'status' => 'sent',
                                    'sent_at' => now()
                                ]);
                                
                                $invitationSent = true;
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to send invitation: ' . $e->getMessage());
                        }
                    }
                    
                    $successCount++;
                    $details[] = [
                        'unit_id' => $unitId,
                        'success' => true,
                        'message' => 'New tenant approved and ' . 
                            ($request->send_invitations ? 'invitation sent' : 'created')
                    ];
                }
                
                // Log activity
                $this->logActivity(
                    $user->id,
                    'bulk_tenant_approval',
                    'Tenant approved via bulk approval',
                    $unit->id,
                    [
                        'tenant_type' => $unit->tenant_type,
                        'tenant_id' => $unit->tenant_id,
                        'move_in_date' => $moveInDate->format('Y-m-d'),
                        'final_rent' => $finalRentAmount,
                        'is_landlord' => $user->isLandlord(),
                    ]
                );
                
            } catch (\Exception $e) {
                $failedCount++;
                $details[] = [
                    'unit_id' => $unitId,
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
                Log::error('Bulk approval error for unit ' . $unitId . ': ' . $e->getMessage());
            }
        }
        
        // Clear caches
        $this->clearUnitCaches($user->id);
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => "Bulk approval completed: {$successCount} approved, {$failedCount} failed.",
            'results' => [
                'total' => $successCount + $failedCount,
                'successful' => $successCount,
                'failed' => $failedCount,
                'details' => $details,
                'invitation_sent' => $invitationSent
            ]
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error in bulk approval: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to process bulk approval: ' . $e->getMessage()
        ], 500);
    }
}


/**
 * Handle bulk approval requests - redirects to bulkApprove
 * This maintains backward compatibility
 */
public function processBulkApproval(Request $request)
{
    Log::info('Redirecting from processBulkApproval to bulkApprove');
    return $this->bulkApprove($request);
}

/**
 * Log activity to the database
 * 
 * @param int $userId
 * @param string $type
 * @param string $description
 * @param int|null $unitId
 * @param array $metadata
 * @return void
 */
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
        // Log the error but don't break the main process
        Log::error('Failed to log activity: ' . $e->getMessage(), [
            'user_id' => $userId,
            'type' => $type,
            'error' => $e->getMessage()
        ]);
    }
}
    
}