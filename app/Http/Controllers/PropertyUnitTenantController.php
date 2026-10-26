<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Models\User;
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
        $this->smsService                    = $smsService;
        $this->emailService                  = $emailService;
        $this->whatsappService               = $whatsappService;
    }

    /* ================================================================
       FAMILY-LINK HELPERS
       ================================================================ */

    /**
     * Three-way family-link flags for a property.
     *
     * @return array{isOwner: bool, isLinkedFamilyMember: bool, hasManageUnitsAccess: bool, hasManageTenantsAccess: bool}
     */
    private function familyLinkFlagsFor(?Property $property, ?User $user = null): array
    {
        $user = $user ?? auth()->user();

        $isOwner = $property && (int) $property->landlord_id === (int) $user->id;

        if ($isOwner || !$property) {
            return [
                'isOwner'                => $isOwner,
                'isLinkedFamilyMember'   => false,
                'hasManageUnitsAccess'   => false,
                'hasManageTenantsAccess' => false,
            ];
        }

        $link = PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->first();

        if (!$link) {
            return [
                'isOwner'                => false,
                'isLinkedFamilyMember'   => false,
                'hasManageUnitsAccess'   => false,
                'hasManageTenantsAccess' => false,
            ];
        }

        $perms = is_array($link->permissions) ? $link->permissions : [];

        return [
            'isOwner'                => false,
            'isLinkedFamilyMember'   => true,
            'hasManageUnitsAccess'   => in_array('manage_units', $perms, true),
            'hasManageTenantsAccess' => in_array('manage_tenants', $perms, true),
        ];
    }

    /* ================================================================
       TENANT ASSIGNMENT
       ================================================================ */

    /**
     * ✅ FIX: Use the policy instead of an ownership comparison.
     *         Owner OR admin OR linked member with `manage_tenants`.
     */
    public function showAssignTenantForm($id)
    {
        $unit = PropertyUnit::with(['property'])->findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            if (!$user->can('manageTenants', $unit->property)) {
                return redirect()->route('property-units.show', $id)
                    ->with('error', 'You do not have permission to assign tenants to this unit.');
            }
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
                    PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
                ]);
            })
            ->orderBy('name')
            ->get();

        /* ✅ FIX: scope property tenants via accessibleBy so linked
           members see tenants on the properties they can act on. */
        $propertyTenants = User::where('type', User::TYPE_TENANT)
            ->where('status', 'active')
            ->whereHas('propertyUnits.property', function ($query) use ($user) {
                if ($user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin()) {
                    $accessiblePropertyIds = Property::query()
                        ->accessibleBy($user)
                        ->pluck('id')
                        ->all();
                    $query->whereIn('properties.id', $accessiblePropertyIds);
                }
            })
            ->orderBy('name')
            ->get();

        $currentlyAssigned = PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->whereNotNull('tenant_id')
            ->where('id', '!=', $id)
            ->when(
                $user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin(),
                function ($query) use ($user) {
                    $accessiblePropertyIds = Property::query()
                        ->accessibleBy($user)
                        ->pluck('id')
                        ->all();
                    return $query->whereIn('property_id', $accessiblePropertyIds);
                }
            )
            ->with('tenant')
            ->get()
            ->pluck('tenant')
            ->filter()
            ->unique('id');

        $allowedDocuments = ['id_card', 'passport', 'drivers_license', 'employment_letter', 'bank_statement', 'reference_letter'];
        $maxFileSize      = config('filesystems.max_upload_size', 5120);

        /* ✅ NEW: family-link flags for the blade banner and route prefix. */
        $flags = $this->familyLinkFlagsFor($unit->property, $user);

        $routePrefix = $user->isLandlord()
            ? 'landlord.property-units'
            : ($user->isAdmin() || $user->isSuperAdmin()
                ? 'admin.property-units'
                : 'property-units');

        return view('property_units.assign-tenant', [
            'unit'                   => $unit,
            'availableTenants'       => $availableTenants,
            'propertyTenants'        => $propertyTenants,
            'currentlyAssigned'      => $currentlyAssigned,
            'allowedDocuments'       => $allowedDocuments,
            'maxFileSize'            => $maxFileSize,
            'isOwner'                => $flags['isOwner'],
            'isLinkedFamilyMember'   => $flags['isLinkedFamilyMember'],
            'hasManageUnitsAccess'   => $flags['hasManageUnitsAccess'],
            'hasManageTenantsAccess' => $flags['hasManageTenantsAccess'],
            'routePrefix'            => $routePrefix,
        ]);
    }

    public function assignTenant(Request $request, $id)
    {
        $unit = PropertyUnit::with('property')->findOrFail($id);
        $user = auth()->user();

        // ✅ FIX: policy-based authorization.
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            if (!$user->can('manageTenants', $unit->property)) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to assign tenants to this unit.')
                    ->withInput();
            }
        }

        if (!$unit->is_available || $unit->status !== PropertyUnit::STATUS_AVAILABLE) {
            return redirect()->back()
                ->with('error', 'Unit is not available for tenant assignment.')
                ->withInput();
        }

        if ($unit->tenant_id && in_array($unit->tenant_status, [
            PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
            PropertyUnit::TENANT_STATUS_APPROVED,
        ])) {
            return redirect()->back()
                ->with('error', 'This unit already has a tenant assigned or pending assignment.')
                ->withInput();
        }

        if ($request->filled('tenant_id')) {
            return $this->assignExistingTenant($request, $unit);
        }

        return $this->addNewTenant($request, $unit);
    }

    private function assignExistingTenant(Request $request, PropertyUnit $unit)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id'     => 'required|exists:users,id',
            'move_in_date'  => 'required|date|after_or_equal:today',
            'proposed_rent' => 'nullable|numeric|min:0',
            'documents.*'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . config('filesystems.max_upload_size', 5120),
            'notes'         => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $tenant = User::find($request->tenant_id);
        if (!$tenant || !$tenant->isTenant()) {
            return redirect()->back()
                ->with('error', 'Selected user is not a registered tenant.')
                ->withInput();
        }

        $alreadyAssigned = PropertyUnit::where('tenant_id', $tenant->id)
            ->whereIn('tenant_status', [
                PropertyUnit::TENANT_STATUS_APPROVED,
                PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
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
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'type'        => $file->getClientMimeType(),
                        'uploaded_at' => now()->toISOString(),
                    ];
                }
            }

            $unit->tenant_id             = $tenant->id;
            $unit->tenant_status         = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
            $unit->tenant_type           = 'existing';
            $unit->tenant_requested_by   = $user = auth()->id();
            $unit->tenant_requested_at   = now();
            $unit->tenant_documents      = $documents;
            $unit->proposed_rent         = $request->proposed_rent ?? $unit->monthly_rent;
            $unit->tenant_approval_notes = $request->notes;
            $unit->tenant_move_in_date   = Carbon::parse($request->move_in_date);
            $unit->status                = PropertyUnit::STATUS_RESERVED;
            $unit->is_available          = false;

            $unit->save();

            $this->createTenantAssignmentNotification($unit, $tenant, $request->notes, 'existing');
            $this->createTenantAssignmentNotificationForTenant($unit, $tenant);

            $this->logActivity(
                auth()->id(),
                'tenant_assignment_requested',
                'Existing tenant assignment requested',
                $unit->id,
                [
                    'tenant_id'                    => $tenant->id,
                    'tenant_name'                  => $tenant->name,
                    'proposed_rent'                => $unit->proposed_rent,
                    'move_in_date'                 => $request->move_in_date,
                    'submitted_by_was_family_member' => (int) $unit->property->landlord_id !== (int) auth()->id(),
                ]
            );

            $this->clearUnitCaches(auth()->id());

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Tenant assignment request submitted successfully. Awaiting admin approval.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error requesting existing tenant assignment: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $unit->id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to request tenant assignment: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function addNewTenant(Request $request, PropertyUnit $unit)
    {
        $validator = Validator::make($request->all(), [
            'tenant_name'                    => 'required|string|max:255',
            'tenant_email'                   => 'required|email|unique:users,email',
            'tenant_phone'                   => 'required|string|max:15|unique:users,phone',
            'tenant_gender'                  => 'required|in:male,female,other',
            'tenant_national_id'             => 'nullable|string|max:50',
            'move_in_date'                   => 'required|date|after_or_equal:today',
            'proposed_rent'                  => 'nullable|numeric|min:0',
            'employment_status'              => 'required|in:employed,self_employed,student,unemployed,retired',
            'monthly_income'                 => 'nullable|numeric|min:0',
            'company_name'                   => 'nullable|string|max:255',
            'job_title'                      => 'nullable|string|max:255',
            'emergency_contact_name'         => 'required|string|max:255',
            'emergency_contact_phone'        => 'required|string|max:15',
            'emergency_contact_relationship' => 'required|string|max:100',
            'tenant_background'              => 'nullable|string|max:1000',
            'previous_landlord_reference'    => 'nullable|string|max:500',
            'documents.*'                    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . config('filesystems.max_upload_size', 5120),
            'invitation_channels'            => 'required|array|min:1',
            'invitation_channels.*'          => 'in:sms,email,whatsapp',
            'notes'                          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $tenantData = [
                'name'                        => $request->tenant_name,
                'email'                       => $request->tenant_email,
                'phone'                       => $request->tenant_phone,
                'gender'                      => $request->tenant_gender,
                'national_id'                 => $request->tenant_national_id,
                'employment_status'           => $request->employment_status,
                'monthly_income'              => $request->monthly_income,
                'company_name'                => $request->company_name,
                'job_title'                   => $request->job_title,
                'emergency_contact'           => [
                    'name'         => $request->emergency_contact_name,
                    'phone'        => $request->emergency_contact_phone,
                    'relationship' => $request->emergency_contact_relationship,
                ],
                'background'                  => $request->tenant_background,
                'previous_landlord_reference' => $request->previous_landlord_reference,
                'invitation_channels'         => $request->invitation_channels,
                'submitted_at'                => now()->toISOString(),
                'submitted_by'                => auth()->id(),
            ];

            $documents = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store("pending_tenants/{$request->tenant_email}", 'private');
                    $documents[] = [
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'type'        => $file->getClientMimeType(),
                        'uploaded_at' => now()->toISOString(),
                    ];
                }
            }

            $unit->tenant_type           = 'new';
            $unit->tenant_status         = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
            $unit->tenant_requested_by   = auth()->id();
            $unit->tenant_requested_at   = now();
            $unit->tenant_details        = $tenantData;
            $unit->tenant_documents      = $documents;
            $unit->proposed_rent         = $request->proposed_rent ?? $unit->monthly_rent;
            $unit->tenant_approval_notes = $request->notes;
            $unit->tenant_move_in_date   = Carbon::parse($request->move_in_date);
            $unit->invitation_channels   = $request->invitation_channels;
            $unit->status                = PropertyUnit::STATUS_RESERVED;
            $unit->is_available          = false;

            $unit->save();

            $this->createNewTenantAssignmentNotification($unit, $tenantData, $request->notes);

            $this->logActivity(
                auth()->id(),
                'new_tenant_assignment_requested',
                'New tenant assignment requested',
                $unit->id,
                [
                    'tenant_email'                 => $request->tenant_email,
                    'tenant_phone'                 => $request->tenant_phone,
                    'tenant_name'                  => $request->tenant_name,
                    'proposed_rent'                => $unit->proposed_rent,
                    'submitted_by_was_family_member'=> (int) $unit->property->landlord_id !== (int) auth()->id(),
                ]
            );

            $this->clearUnitCaches(auth()->id());

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'New tenant application submitted successfully. Awaiting admin approval.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error requesting new tenant assignment: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $unit->id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to submit new tenant application: ' . $e->getMessage())
                ->withInput();
        }
    }

    /* ================================================================
       TENANT APPROVAL — PENDING QUEUE
       ================================================================ */

    /**
     * ✅ FIX: scope by accessibleBy() for landlords so linked family
     *         members see pending assignments on their linked properties.
     *         Also passes family-link flags to the blade.
     *
     * ✅ FIX: pass BOTH index-compatible and pending-specific stats
     *         keys so the blade's stat cards don't blow up.
     */
    public function pendingApprovals(Request $request)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have access to pending approvals.');
        }

        try {
            $cacheKey = 'pending_approvals_' . $user->id . '_' . md5($request->fullUrl());

            $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
                $query = PropertyUnit::with(['property', 'tenant', 'requestedBy'])
                    ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                    ->orderBy('tenant_requested_at', 'asc');

                // ✅ Scope by accessibleBy for landlords (owned + linked).
                if ($user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin()) {
                    $accessiblePropertyIds = Property::query()
                        ->accessibleBy($user)
                        ->pluck('id')
                        ->all();

                    $query->whereIn('property_id', $accessiblePropertyIds);
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
                    $properties = Property::query()
                        ->accessibleBy($user)
                        ->orderBy('property_name')
                        ->get();
                }

                $stats = [
                    'total_pending'    => $pendingUnits->total(),
                    'new_tenants'      => (clone $query)->where('tenant_type', 'new')->count(),
                    'existing_tenants' => (clone $query)->where('tenant_type', 'existing')->count(),
                    'today_requests'   => (clone $query)->whereDate('tenant_requested_at', today())->count(),

                    // Index-compatible keys for shared stat-card partials.
                    'total_units'      => PropertyUnit::withoutTrashed()->count(),
                    'available_units'  => PropertyUnit::withoutTrashed()->where('status', 'available')->count(),
                    'occupied_units'   => PropertyUnit::withoutTrashed()->where('status', 'occupied')->count(),
                    'pending_approval' => PropertyUnit::withoutTrashed()
                        ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)->count(),
                ];

                return compact('pendingUnits', 'properties', 'stats');
            });

            /* ✅ Family-link flags for the blade */
            $linkedPropertyIds      = collect();
            $manageTenantsPropertyIds = collect();

            if ($user->isLandlord()) {
                $approvedLinks = PropertyFamilyLink::query()
                    ->where('linked_user_id', $user->id)
                    ->where('status', PropertyFamilyLink::STATUS_APPROVED)
                    ->get(['id', 'property_id', 'permissions']);

                $linkedPropertyIds = $approvedLinks->pluck('property_id')->unique()->values();

                $manageTenantsPropertyIds = $approvedLinks
                    ->filter(function ($link) {
                        $perms = is_array($link->permissions) ? $link->permissions : [];
                        return in_array('manage_tenants', $perms, true);
                    })
                    ->pluck('property_id')
                    ->unique()
                    ->values();
            }

            return view('property_units.pending-approvals', array_merge($data, [
                'request'                  => $request,
                'user'                     => $user,
                'linkedPropertyIds'        => $linkedPropertyIds,
                'manageTenantsPropertyIds' => $manageTenantsPropertyIds,
            ]));

        } catch (\Throwable $e) {
            Log::error('Error loading pending approvals: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id'   => $user->id,
            ]);

            return redirect()->route('property-units.index')
                ->with('error', 'Unable to load pending approvals. Please try again.');
        }
    }

    /**
     * Approve a tenant assignment.
     *
     * Admin-only by design. Landlords submit; admins confirm.
     * Move-in date and final rent are set by the landlord at
     * submission time and are NOT modified by the admin.
     */
    public function approveTenant(Request $request, $id)
    {
        $user = auth()->user();

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

        $validator = Validator::make($request->all(), [
            'approval_notes'        => 'required|string|min:20|max:500',
            'send_invitation'       => 'nullable|boolean',
            'invitation_channels'   => 'nullable|required_if:send_invitation,1|array|min:1',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
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

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error approving tenant assignment: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $unit->id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to approve tenant assignment: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function approveExistingTenant(PropertyUnit $unit, Request $request): void
    {
        $tenant = User::find($unit->tenant_id);

        $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
        $unit->approved_by           = auth()->id();
        $unit->tenant_approved_at    = now();
        $unit->tenant_approval_notes = $request->approval_notes;
        $unit->status                = PropertyUnit::STATUS_OCCUPIED;
        $unit->is_available          = false;
        $unit->save();

        $this->createTenantApprovalNotification($unit, 'existing', false, $request->approval_notes);

        $this->logActivity(
            auth()->id(),
            'tenant_approved',
            'Existing tenant approved for unit',
            $unit->id,
            [
                'tenant_id'     => $tenant->id,
                'final_rent'    => $unit->current_rent_amount ?? $unit->monthly_rent,
                'move_in_date'  => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
            ]
        );
    }

    private function approveNewTenant(PropertyUnit $unit, Request $request): void
    {
        $tenantDetails = $unit->tenant_details;

        try {
            $tenant = User::create([
                'name'              => $tenantDetails['name'],
                'email'             => $tenantDetails['email'],
                'phone'             => $tenantDetails['phone'],
                'gender'            => $tenantDetails['gender'] ?? 'other',
                'national_id'       => $tenantDetails['national_id'] ?? null,
                'type'              => User::TYPE_TENANT,
                'status'            => User::STATUS_PENDING,
                'password'          => Hash::make(Str::random(12)),
                'employment_status' => $tenantDetails['employment_status'] ?? 'employed',
                'monthly_income'    => $tenantDetails['monthly_income'] ?? null,
                'company_name'      => $tenantDetails['company_name'] ?? null,
                'job_title'         => $tenantDetails['job_title'] ?? null,
                'emergency_contact' => $tenantDetails['emergency_contact'] ?? [],
                'created_by'        => auth()->id(),
            ]);

            $userInvitation = \App\Models\UserInvitation::create([
                'user_id'    => $tenant->id,
                'token'      => Str::random(64),
                'invited_by' => auth()->id(),
                'purpose'    => 'tenant_registration',
                'channels'   => $request->invitation_channels ?? $unit->invitation_channels ?? ['email'],
                'status'     => 'pending',
                'sent_at'    => now(),
                'expires_at' => now()->addDays(7),
                'metadata'   => [
                    'property_name' => $unit->property->property_name ?? 'Unknown',
                    'unit_number'   => $unit->unit_number ?? 'Unknown',
                    'landlord_name' => $unit->property->landlord->name ?? 'Unknown',
                    'monthly_rent'  => $unit->proposed_rent ?? $unit->monthly_rent ?? 0,
                    'move_in_date'  => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                ],
            ]);

            $tenantInvitation = TenantInvitation::create([
                'user_id'            => $tenant->id,
                'property_id'        => $unit->property_id,
                'unit_id'            => $unit->id,
                'invited_by'         => auth()->id(),
                'user_invitation_id' => $userInvitation->id,
                'channels'           => $request->invitation_channels ?? $unit->invitation_channels ?? ['email'],
                'status'             => TenantInvitation::STATUS_PENDING,
                'expires_at'         => now()->addDays(7),
                'token'              => Str::random(64),
                'metadata'           => [
                    'property_name' => $unit->property->property_name,
                    'unit_number'   => $unit->unit_number,
                    'landlord_name' => $unit->property->landlord->name ?? 'N/A',
                    'monthly_rent'  => $unit->proposed_rent ?? $unit->monthly_rent ?? 0,
                    'move_in_date'  => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                    'purpose'       => 'new_tenant_registration',
                    'tenant_details'=> $tenantDetails,
                ],
            ]);

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
                            'name'        => $document['name'] ?? 'document',
                            'path'        => $newPath,
                            'type'        => $document['type'] ?? 'application/pdf',
                            'uploaded_at' => $document['uploaded_at'] ?? now()->toISOString(),
                        ];
                    }
                }
            }

            $unit->tenant_id             = $tenant->id;
            $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
            $unit->approved_by           = auth()->id();
            $unit->tenant_approved_at    = now();
            $unit->tenant_approval_notes = $request->approval_notes;
            $unit->current_rent_amount   = $unit->proposed_rent ?? $unit->monthly_rent;
            $unit->status                = PropertyUnit::STATUS_OCCUPIED;
            $unit->is_available          = false;
            $unit->tenant_documents      = $newDocuments;
            $unit->save();

            if ($request->send_invitation) {
                $invitationResult = $this->sendTenantInvitation(
                    $tenant,
                    $unit,
                    $request->invitation_channels ?? $unit->invitation_channels ?? ['email'],
                    $tenantInvitation
                );

                if ($invitationResult['success']) {
                    $tenantInvitation->update([
                        'status'              => TenantInvitation::STATUS_SENT,
                        'sent_at'             => now(),
                        'channels_successful' => $invitationResult['channels_successful'] ?? [],
                    ]);

                    $userInvitation->update([
                        'status'  => 'sent',
                        'sent_at' => now(),
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
                } catch (\Throwable $e) {
                    Log::error('Failed to send approval notification: ' . $e->getMessage());
                }
            }

            $this->logActivity(
                auth()->id(),
                'new_tenant_created_approved',
                'New tenant created and approved',
                $unit->id,
                [
                    'tenant_id'           => $tenant->id,
                    'final_rent'          => $unit->current_rent_amount,
                    'move_in_date'        => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                    'invitation_sent'     => $request->send_invitation ?? false,
                    'invitation_channels' => $request->invitation_channels ?? [],
                ]
            );

        } catch (\Throwable $e) {
            Log::error('Error approving new tenant: ' . $e->getMessage(), [
                'unit_id'   => $unit->id,
                'exception' => $e,
            ]);
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

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('tenant_type')) {
            $query->where('tenant_type', $request->tenant_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tenant_requested_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tenant_requested_at', '<=', $request->date_to);
        }

        if ($user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $accessiblePropertyIds = Property::query()
                ->accessibleBy($user)
                ->pluck('id')
                ->all();

            $query->whereIn('property_id', $accessiblePropertyIds);
        }

        $units = $query->get();

        $unitData = [];
        foreach ($units as $unit) {
            $data = [
                'id'             => $unit->id,
                'property_name'  => $unit->property->property_name ?? 'N/A',
                'unit_number'    => $unit->unit_number,
                'tenant_type'    => $unit->tenant_type,
                'move_in_date'   => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
                'requested_date' => $unit->tenant_requested_at->format('M d, Y'),
                'tenant_name'    => $unit->requestedBy->name ?? 'N/A',
                'is_landlord'    => $user->isLandlord(),
            ];

            if ($user->isLandlord()) {
                $data['proposed_rent'] = $unit->proposed_rent ?? $unit->monthly_rent ?? 0;
                $data['base_rent']     = $unit->monthly_rent ?? 0;
            }

            $unitData[] = $data;
        }

        return response()->json([
            'success' => true,
            'units'   => $unitData,
        ]);
    }

    /* ================================================================
       TENANT VACATE / TERMINATE
       ================================================================ */

    /**
     * ✅ FIX: policy-based authorization.
     */
    public function markVacatedForm($id)
    {
        $unit = PropertyUnit::with('property')->findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            if (!$user->can('manageTenants', $unit->property)) {
                abort(403, 'You do not have permission to access this page.');
            }
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

    public function markVacated(Request $request, $id)
    {
        $unit = PropertyUnit::with(['tenant', 'property'])->findOrFail($id);
        $user = auth()->user();

        // ✅ FIX: policy-based authorization.
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            if (!$user->can('manageTenants', $unit->property)) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to mark tenants as vacated on this property.')
                    ->withInput();
            }
        }

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

        $validator = Validator::make($request->all(), [
            'move_out_date'           => 'required|date|before_or_equal:today',
            'reason'                  => 'required|string|max:100',
            'notes'                   => 'nullable|string|max:500',
            'refund_deposit'          => 'nullable|boolean',
            'deposit_refund_amount'   => 'nullable|required_if:refund_deposit,1|numeric|min:0|max:' . ($unit->security_deposit ?? 0),
            'refund_notes'            => 'nullable|required_if:refund_deposit,1|string|max:500',
            'property_condition'      => 'required|in:good,fair,poor',
            'keys_returned'           => 'nullable|boolean',
            'inspection_completed'    => 'nullable|boolean',
            'confirm_action'          => 'required|accepted',
        ], [
            'confirm_action.accepted'        => 'You must confirm that the tenant has vacated the unit.',
            'reason.required'                => 'Please select a reason for vacating.',
            'move_out_date.before_or_equal'  => 'Move-out date cannot be in the future.',
            'deposit_refund_amount.max'      => 'Refund amount cannot exceed the security deposit of ₵' . number_format($unit->security_deposit ?? 0, 2),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $vacateReason = $request->reason;
            if ($request->filled('notes')) {
                $vacateReason .= ': ' . $request->notes;
            }

            $propertyCondition = $request->property_condition;
            $cleaningRequired  = in_array($propertyCondition, ['poor', 'fair'], true);

            $damagesNoted = null;
            if ($propertyCondition === 'poor') {
                $damagesNoted = 'Significant damage reported requiring major repair.';
            } elseif ($propertyCondition === 'fair') {
                $damagesNoted = 'Minor damage requiring some repair.';
            } elseif ($propertyCondition === 'good' && $request->filled('notes')) {
                $damagesNoted = $request->notes;
            }

            $unit->tenant_status           = PropertyUnit::TENANT_STATUS_VACATED;
            $unit->tenant_move_out_date    = Carbon::parse($request->move_out_date);
            $unit->tenant_vacate_reason    = $vacateReason;
            $unit->property_condition      = $propertyCondition;
            $unit->cleaning_required       = $cleaningRequired;
            $unit->damages_noted           = $damagesNoted;
            $unit->status                  = PropertyUnit::STATUS_UNDER_MAINTENANCE;
            $unit->is_available            = false;
            $unit->available_from          = Carbon::parse($request->move_out_date)->addDays(7);

            $unit->metadata = array_merge($unit->metadata ?? [], [
                'keys_returned'        => $request->has('keys_returned'),
                'inspection_completed' => $request->has('inspection_completed'),
                'vacated_by'           => auth()->id(),
                'vacated_at'           => now()->toISOString(),
            ]);

            $unit->save();

            if ($request->has('refund_deposit') && $request->refund_deposit) {
                $this->processDepositRefund(
                    $unit,
                    $request->deposit_refund_amount,
                    $request->refund_notes
                );
            }

            if ($unit->currentLease) {
                $unit->currentLease->update([
                    'status'             => 'terminated',
                    'end_date'           => $request->move_out_date,
                    'termination_reason' => $vacateReason,
                    'terminated_by'      => auth()->id(),
                    'terminated_at'      => now(),
                ]);
            }

            $this->createTenantVacatedNotification($unit, $vacateReason, $request->deposit_refund_amount ?? 0);

            $this->logActivity(
                $user->id,
                'tenant_vacated',
                'Tenant marked as vacated',
                $unit->id,
                [
                    'tenant_id'            => $unit->tenant_id,
                    'tenant_name'          => $unit->tenant->name,
                    'move_out_date'        => $request->move_out_date,
                    'reason'               => $request->reason,
                    'property_condition'   => $request->property_condition,
                    'refund_amount'        => $request->deposit_refund_amount ?? 0,
                    'keys_returned'        => $request->has('keys_returned'),
                    'inspection_completed' => $request->has('inspection_completed'),
                    'vacated_by_was_family_member' => (int) $unit->property->landlord_id !== (int) $user->id,
                ]
            );

            $this->clearUnitCaches($user->id);
            Cache::forget('property_unit_' . $unit->id);
            Cache::forget('property_unit_stats_' . $unit->property_id);

            DB::commit();

            $message = 'Tenant marked as vacated successfully. Unit is now under maintenance.';
            if ($request->has('refund_deposit') && $request->refund_deposit) {
                $message .= ' Refund of ₵' . number_format($request->deposit_refund_amount, 2) . ' processed.';
            }

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', $message);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error marking tenant as vacated: ' . $e->getMessage(), [
                'unit_id'   => $unit->id,
                'user_id'   => auth()->id(),
                'exception' => $e,
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
                        $query->where(function ($q) use ($user) {
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
                    },
                ])->findOrFail($id);
            });

            if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                abort(403, 'You can only view your assigned unit.');
            }

            $statsCacheKey = 'unit_stats_tenant_' . $id . '_' . $user->id;
            $stats = Cache::remember($statsCacheKey, 3600, function () use ($unit, $user) {
                return [
                    'total_invoices'              => $unit->invoices()->where('tenant_id', $user->id)->count(),
                    'paid_invoices'               => $unit->invoices()->where('tenant_id', $user->id)->where('status', 'paid')->count(),
                    'pending_invoices'            => $unit->invoices()->where('tenant_id', $user->id)->where('status', 'pending')->count(),
                    'overdue_invoices'            => $unit->invoices()->where('tenant_id', $user->id)->where('status', 'overdue')->count(),
                    'total_rent_paid'             => $unit->invoices()->where('tenant_id', $user->id)->where('status', 'paid')->sum('amount'),
                    'active_maintenance_requests' => $unit->maintenanceRequests()
                        ->where(function ($query) use ($user) {
                            $query->where('tenant_id', $user->id)
                                ->orWhere('created_by', $user->id);
                        })
                        ->whereIn('status', ['pending', 'in_progress'])
                        ->count(),
                    'outstanding_balance'         => $unit->invoices()
                        ->where('tenant_id', $user->id)
                        ->whereIn('status', ['pending', 'overdue'])
                        ->sum('amount'),
                ];
            });

            $amenityOptions = $this->getAmenityOptions();

            $leaseDetails = null;
            if ($unit->currentLease) {
                $leaseDetails = [
                    'start_date'         => $unit->currentLease->start_date->format('M d, Y'),
                    'end_date'           => $unit->currentLease->end_date->format('M d, Y'),
                    'monthly_rent'       => $unit->currentLease->monthly_rent,
                    'security_deposit'   => $unit->currentLease->security_deposit,
                    'status'             => $unit->currentLease->status,
                    'signed_by_landlord' => !empty($unit->currentLease->landlord_signed_at),
                    'signed_by_tenant'   => !empty($unit->currentLease->tenant_signed_at),
                ];
            }

            $sidebarUnreadCount = 0;

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
                    'user'        => $user,
                    'pendingUnit' => $pendingUnit,
                    'pageTitle'   => 'Unit Assignment Pending',
                ]);
            }

            return view('tenant.dashboard-no-unit', [
                'user'      => $user,
                'pageTitle' => 'No Unit Assigned',
            ]);
        }

        $data = [
            'user'      => $user,
            'unit'      => $unit,
            'isTenant'  => true,
            'pageTitle' => $unit
                ? 'My Unit - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number
                : 'My Unit',
        ];

        if ($unit) {
            $recentActivities = ActivityLog::where('user_id', $user->id)
                ->orWhere(function ($query) use ($unit) {
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

    /* ================================================================
       HELPER METHODS
       ================================================================ */

    private function sendTenantInvitation(User $tenant, PropertyUnit $unit, array $channels, TenantInvitation $invitation): array
    {
        try {
            $results            = [];
            $successCount       = 0;
            $channelsSuccessful = [];

            foreach ($channels as $channel) {
                try {
                    $result = $this->sendInvitationViaChannel($tenant, $unit, $invitation, $channel);

                    $results[$channel] = $result;

                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error sending invitation via {$channel}: " . $e->getMessage());
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage(),
                    ];
                }
            }

            $this->logActivity(
                auth()->id(),
                'tenant_invitation_sent',
                'Tenant invitation sent',
                $unit->id,
                [
                    'tenant_id'     => $tenant->id,
                    'channels'      => $channelsSuccessful,
                    'success_count' => $successCount,
                ]
            );

            return [
                'success'             => $successCount > 0,
                'invitation_id'       => $invitation->id,
                'user_invitation_id'  => $invitation->user_invitation_id,
                'token'               => $invitation->token ?? $invitation->id,
                'invitation_url'      => $invitation->getInvitationUrl(),
                'channels_successful' => $channelsSuccessful,
                'results'             => $results,
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage(),
            ];
        }
    }

    private function sendInvitationViaChannel(User $tenant, PropertyUnit $unit, TenantInvitation $invitation, string $channel): array
    {
        return match ($channel) {
            'email'    => $this->sendTenantEmailInvitation($tenant, $unit, $invitation),
            'sms'      => $this->sendTenantSmsInvitation($tenant, $unit, $invitation),
            'whatsapp' => $this->sendTenantWhatsAppInvitation($tenant, $unit, $invitation),
            default    => ['success' => false, 'message' => "Unknown channel: {$channel}"],
        };
    }

    private function sendTenantEmailInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $userInvitation = $invitation->userInvitation;

            if (!$userInvitation) {
                $userInvitation = \App\Models\UserInvitation::create([
                    'user_id'    => $tenant->id,
                    'token'      => Str::random(64),
                    'invited_by' => auth()->id(),
                    'purpose'    => 'tenant_registration',
                    'channels'   => ['email'],
                    'status'     => 'pending',
                    'sent_at'    => now(),
                    'expires_at' => now()->addDays(7),
                    'metadata'   => [
                        'property_name'        => $unit->property->property_name,
                        'unit_number'          => $unit->unit_number,
                        'tenant_invitation_id' => $invitation->id,
                    ],
                ]);

                $invitation->update(['user_invitation_id' => $userInvitation->id]);
            }

            Mail::to($tenant->email)->send(new TenantInvitationMail($tenant, $unit->property, $invitation));

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Throwable $e) {
            Log::error('Failed to send tenant email invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }

    private function sendTenantSmsInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $message = "You have been invited to rent Unit {$unit->unit_number} at {$unit->property->property_name}. Click here to accept: " . $invitation->getInvitationUrl();

            $result = $this->smsService->send($tenant->phone, $message);

            return [
                'success' => (bool) $result,
                'message' => $result ? 'SMS sent successfully' : 'Failed to send SMS',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to send tenant SMS invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    private function sendTenantWhatsAppInvitation(User $tenant, PropertyUnit $unit, TenantInvitation $invitation): array
    {
        try {
            $message = "You have been invited to rent Unit {$unit->unit_number} at {$unit->property->property_name}. Click here to accept: " . $invitation->getInvitationUrl();

            $result = $this->whatsappService->send($tenant->phone, $message);

            return [
                'success' => (bool) $result,
                'message' => $result ? 'WhatsApp message sent successfully' : 'Failed to send WhatsApp message',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to send tenant WhatsApp invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send WhatsApp message: ' . $e->getMessage()];
        }
    }

    private function getAmenityOptions(): array
    {
        return [
            'parking'            => 'Parking Space',
            'balcony'            => 'Balcony',
            'air_conditioning'   => 'Air Conditioning',
            'furnished'          => 'Fully Furnished',
            'wifi'               => 'Wi-Fi',
            'security'           => '24/7 Security',
            'gym'                => 'Gym Access',
            'pool'               => 'Swimming Pool',
            'laundry'            => 'Laundry Facility',
            'elevator'           => 'Elevator',
            'generator'          => 'Backup Generator',
            'cctv'               => 'CCTV Surveillance',
            'fire_safety'        => 'Fire Safety System',
            'water_heater'       => 'Water Heater',
            'kitchen_appliances' => 'Kitchen Appliances',
        ];
    }

    private function processDepositRefund(PropertyUnit $unit, float $amount, ?string $deductionReason = null): void
    {
        $originalDeposit = $unit->security_deposit ?? 0;
        $deduction       = $originalDeposit - $amount;

        $this->logActivity(
            auth()->id(),
            'deposit_refund_processed',
            'Security deposit refund processed',
            $unit->id,
            [
                'amount'           => $amount,
                'original_deposit' => $originalDeposit,
                'deduction'        => $deduction > 0 ? $deduction : 0,
                'deduction_reason' => $deductionReason,
                'refund_date'      => now()->toISOString(),
            ]
        );

        $unit->security_deposit_refunded            = $amount;
        $unit->security_deposit_deduction           = $deduction > 0 ? $deduction : 0;
        $unit->security_deposit_deduction_reason    = $deductionReason;
        $unit->security_deposit_refunded_at         = now();
        $unit->save();
    }

    private function clearUnitCaches(int $userId): void
    {
        Cache::forget('property_unit_stats_' . $userId);
        Cache::forget('dashboard_stats_' . $userId);
        Cache::forget('tenant_unit_' . $userId);
    }

    private function createTenantAssignmentNotificationForTenant(PropertyUnit $unit, User $tenant): void
    {
        try {
            Notification::send($tenant, new \App\Notifications\TenantAssignmentRequested(
                unit: $unit,
                requestedBy: auth()->user()
            ));

            Log::info('Tenant assignment notification sent to tenant', [
                'unit_id'   => $unit->id,
                'tenant_id' => $tenant->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create tenant assignment notification for tenant: ' . $e->getMessage());
        }
    }

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
                'unit_id'     => $unit->id,
                'tenant_id'   => $tenant->id,
                'tenant_type' => $type,
                'admin_count' => $admins->count(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to create tenant assignment notification: ' . $e->getMessage(), [
                'unit_id'   => $unit->id,
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

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
                'unit_id'       => $unit->id,
                'tenant_email'  => $tenantData['email'] ?? null,
                'admin_count'   => $admins->count(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to create new tenant assignment notification: ' . $e->getMessage(), [
                'unit_id'       => $unit->id,
                'tenant_email'  => $tenantData['email'] ?? null,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function createTenantApprovalNotification(
        PropertyUnit $unit,
        string $tenantType,
        bool $invitationSent = false,
        ?string $approvalNotes = null
    ): void {
        try {
            if ($unit->property->landlord) {
                Notification::send($unit->property->landlord, new \App\Notifications\TenantApproved(
                    unit: $unit,
                    tenantType: $tenantType,
                    approvedBy: auth()->user(),
                    invitationSent: $invitationSent,
                    approvalNotes: $approvalNotes
                ));

                Log::info('Tenant approval notification sent to landlord', [
                    'unit_id'         => $unit->id,
                    'landlord_id'     => $unit->property->landlord_id,
                    'tenant_type'     => $tenantType,
                    'invitation_sent' => $invitationSent,
                ]);
            }

            if ($tenantType === 'existing' && $unit->tenant) {
                try {
                    if (class_exists(\App\Notifications\TenantAssigned::class)) {
                        Notification::send($unit->tenant, new \App\Notifications\TenantAssigned(
                            unit: $unit,
                            landlord: $unit->property->landlord,
                            approvedBy: auth()->user()
                        ));

                        Log::info('Tenant assignment notification sent to existing tenant', [
                            'unit_id'   => $unit->id,
                            'tenant_id' => $unit->tenant_id,
                        ]);
                    } else {
                        Log::warning('TenantAssigned notification class not found, using fallback for tenant', [
                            'unit_id'   => $unit->id,
                            'tenant_id' => $unit->tenant_id,
                        ]);

                        if ($unit->tenant->email) {
                            Mail::to($unit->tenant->email)->send(new \App\Mail\TenantApprovalNotification(
                                $unit->tenant,
                                $unit,
                                $unit->property->landlord
                            ));
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to send tenant notification: ' . $e->getMessage(), [
                        'unit_id'   => $unit->id,
                        'tenant_id' => $unit->tenant_id,
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to create tenant approval notification: ' . $e->getMessage(), [
                'unit_id'     => $unit->id,
                'tenant_type' => $tenantType,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function createTenantVacatedNotification(PropertyUnit $unit, ?string $reason, ?float $refundAmount): void
    {
        try {
            $landlord = $unit->property->landlord;
            $tenant   = $unit->tenant;

            $notificationData = [
                'type'               => 'tenant_vacated',
                'unit_id'            => $unit->id,
                'unit_identifier'    => $unit->full_unit_identifier,
                'property_name'      => $unit->property->property_name,
                'move_out_date'      => $unit->tenant_move_out_date ? $unit->tenant_move_out_date->format('Y-m-d') : null,
                'reason'             => $reason,
                'refund_amount'      => $refundAmount,
                'property_condition' => $unit->property_condition,
                'cleaning_required'  => $unit->cleaning_required,
                'vacated_at'         => now()->toISOString(),
            ];

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
                    'unit_id'     => $unit->id,
                    'landlord_id' => $landlord->id,
                ]);
            }

            if ($tenant) {
                Notification::send($tenant, new \App\Notifications\GeneralNotification(
                    title: 'Unit Vacated',
                    message: "You have been marked as vacated from Unit {$unit->unit_number} at {$unit->property->property_name}.\n\nMove-out Date: " .
                             ($unit->tenant_move_out_date ? $unit->tenant_move_out_date->format('M d, Y') : 'N/A') .
                             "\nRefund Amount: ₵" . ($refundAmount ?? 0),
                    icon: 'fas fa-door-open text-info',
                    category: 'unit_vacated',
                    actionUrl: route('property-units.show', $unit->id),
                    priority: 1,
                    data: $notificationData
                ));

                Log::info('Unit vacated notification sent to tenant', [
                    'unit_id'   => $unit->id,
                    'tenant_id' => $tenant->id,
                ]);
            }

            $admins = User::whereIn('type', ['admin', 'super_admin'])->get();
            if ($admins->count() > 0) {
                Notification::send($admins, new \App\Notifications\GeneralNotification(
                    title: 'Unit Vacated - Available for Rent',
                    message: "Unit {$unit->unit_number} at {$unit->property->property_name} has been vacated and will be available for rent from " .
                             ($unit->available_from ? $unit->available_from->format('M d, Y') : 'soon'),
                    icon: 'fas fa-home text-success',
                    category: 'unit_available',
                    actionUrl: route('property-units.show', $unit->id),
                    priority: 2,
                    data: $notificationData
                ));
            }

        } catch (\Throwable $e) {
            Log::error('Failed to create tenant vacated notification: ' . $e->getMessage(), [
                'unit_id' => $unit->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function viewAssignmentDetails($id)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
            abort(403, 'You do not have permission to view assignment details.');
        }

        try {
            $unit = PropertyUnit::with([
                'property',
                'tenant',
                'requestedBy',
                'property.landlord',
            ])->findOrFail($id);

            // ✅ FIX: policy-based authorization for landlords.
            if ($user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin()) {
                if (!$user->can('viewLeases', $unit->property)
                    && !$user->can('manageTenants', $unit->property)) {
                    abort(403, 'You can only view assignment details for properties you can access.');
                }
            }

            if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
                return redirect()->route('property-units.show', $unit->id)
                    ->with('error', 'This unit does not have a pending tenant assignment.');
            }

            $tenantDetails = [];
            $documents     = [];

            if ($unit->tenant_type === 'existing' && $unit->tenant) {
                $tenantDetails = [
                    'name'              => $unit->tenant->name,
                    'email'             => $unit->tenant->email,
                    'phone'             => $unit->tenant->phone,
                    'gender'            => $unit->tenant->gender,
                    'national_id'       => $unit->tenant->national_id,
                    'employment_status' => $unit->tenant->employment_status,
                    'monthly_income'    => $unit->tenant->monthly_income,
                    'company_name'      => $unit->tenant->company_name,
                    'job_title'         => $unit->tenant->job_title,
                    'emergency_contact' => $unit->tenant->emergency_contact,
                    'type'              => 'existing',
                ];
            } elseif ($unit->tenant_type === 'new' && $unit->tenant_details) {
                $tenantDetails        = $unit->tenant_details;
                $tenantDetails['type'] = 'new';
            }

            if (!empty($unit->tenant_documents)) {
                $documents = $unit->tenant_documents;
            }

            return view('property_units.assignment-details', compact('unit', 'tenantDetails', 'documents', 'user'));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('property-units.index')
                ->with('error', 'Property unit not found.');
        } catch (\Throwable $e) {
            Log::error('Error viewing assignment details: ' . $e->getMessage());
            return redirect()->route('property-units.index')
                ->with('error', 'Failed to load assignment details: ' . $e->getMessage());
        }
    }

    public function showBulkApproval()
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access bulk approval.');
        }

        $pendingUnits = PropertyUnit::with(['property', 'tenant', 'requestedBy'])
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
            ->orderBy('tenant_requested_at', 'asc')
            ->paginate(20);

        $properties = Property::orderBy('property_name')->get();

        // Both pending-specific and index-compatible stats keys.
        $stats = [
            'total_pending'    => $pendingUnits->total(),
            'new_tenants'      => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                ->where('tenant_type', 'new')->count(),
            'existing_tenants' => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                ->where('tenant_type', 'existing')->count(),
            'today_requests'   => PropertyUnit::where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                ->whereDate('tenant_requested_at', today())->count(),

            'total_units'      => PropertyUnit::withoutTrashed()->count(),
            'available_units'  => PropertyUnit::withoutTrashed()->where('status', PropertyUnit::STATUS_AVAILABLE)->count(),
            'occupied_units'   => PropertyUnit::withoutTrashed()->where('status', PropertyUnit::STATUS_OCCUPIED)->count(),
            'pending_approval' => PropertyUnit::withoutTrashed()
                ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)->count(),
        ];

        return view('property_units.bulk-approval', compact('pendingUnits', 'properties', 'stats'));
    }

    public function bulkApprove(Request $request)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform bulk approvals.',
            ], 403);
        }

        Log::info('Bulk approval request received', [
            'user_id'      => $user->id,
            'is_landlord'  => $user->isLandlord(),
            'request_data' => $request->all(),
        ]);

        $unitIds = $request->unit_ids;

        if (is_string($unitIds)) {
            $decoded = json_decode($unitIds, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $unitIds = $decoded;
            } else {
                $unitIds = array_map('trim', explode(',', $unitIds));
            }
        }

        $unitIds = array_filter(array_map('intval', (array) $unitIds));

        if (empty($unitIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid units selected. Please select at least one unit.',
                'debug'   => [
                    'received' => $request->unit_ids,
                    'parsed'   => $unitIds,
                ],
            ], 422);
        }

        $rules = [
            'approval_notes'       => 'required|string|min:10|max:500',
            'send_invitations'     => 'nullable|boolean',
            'invitation_channels'  => 'nullable|array|required_if:send_invitations,1',
            'invitation_channels.*'=> 'in:sms,email,whatsapp',
        ];

        if ($user->isLandlord()) {
            $rules['move_in_date']              = 'required|date';
            $rules['final_rent_amount_type']    = 'nullable|in:proposed,base,custom';
            $rules['custom_rent_amount']        = 'nullable|numeric|min:0|required_if:final_rent_amount_type,custom';
            $rules['security_deposit']          = 'nullable|numeric|min:0';
        } else {
            if ($request->has('move_in_date')) {
                $rules['move_in_date'] = 'nullable|date';
            }
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
                'errors'       => $validator->errors()->toArray(),
                'request_data' => $request->all(),
                'is_landlord'  => $user->isLandlord(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors'  => $validator->errors()->toArray(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $successCount   = 0;
            $failedCount    = 0;
            $details        = [];
            $invitationSent = false;

            foreach ($unitIds as $unitId) {
                $unit = PropertyUnit::with(['property'])->find($unitId);

                if (!$unit) {
                    $failedCount++;
                    $details[] = ['unit_id' => $unitId, 'success' => false, 'message' => 'Unit not found'];
                    continue;
                }

                if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
                    $failedCount++;
                    $details[] = [
                        'unit_id' => $unitId,
                        'success' => false,
                        'message' => 'Unit is not pending approval (current status: ' . $unit->tenant_status . ')',
                    ];
                    continue;
                }

                try {
                    if ($user->isLandlord() && $request->has('move_in_date')) {
                        $moveInDate = Carbon::parse($request->move_in_date);

                        $finalRentAmount = match ($request->final_rent_amount_type) {
                            'base'   => $unit->monthly_rent,
                            'custom' => $request->custom_rent_amount ?? $unit->monthly_rent,
                            default  => $unit->proposed_rent ?? $unit->monthly_rent,
                        };

                        $securityDeposit = $request->security_deposit ?? $unit->security_deposit;
                    } else {
                        $moveInDate      = $unit->tenant_move_in_date ?? now();
                        $finalRentAmount = $unit->proposed_rent ?? $unit->monthly_rent;
                        $securityDeposit = $unit->security_deposit;
                    }

                    if ($unit->tenant_type === 'existing') {
                        $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->approved_by           = $user->id;
                        $unit->tenant_approved_at    = now();
                        $unit->tenant_approval_notes = $request->approval_notes;
                        $unit->tenant_move_in_date   = $moveInDate;
                        $unit->current_rent_amount   = $finalRentAmount;
                        $unit->status                = PropertyUnit::STATUS_OCCUPIED;
                        $unit->is_available          = false;
                        $unit->security_deposit      = $securityDeposit;
                        $unit->save();

                        if ($unit->tenant) {
                            try {
                                Notification::send($unit->tenant, new \App\Notifications\TenantApproved(
                                    unit: $unit,
                                    tenantType: 'existing',
                                    approvedBy: $user,
                                    invitationSent: false,
                                    approvalNotes: $request->approval_notes
                                ));
                            } catch (\Throwable $e) {
                                Log::warning('Failed to notify existing tenant: ' . $e->getMessage());
                            }
                        }

                        $successCount++;
                        $details[] = ['unit_id' => $unitId, 'success' => true, 'message' => 'Existing tenant approved successfully'];

                    } elseif ($unit->tenant_type === 'new') {
                        $tenantDetails = $unit->tenant_details;

                        if (empty($tenantDetails)) {
                            throw new \Exception('Tenant details not found');
                        }

                        $tenant = User::create([
                            'name'              => $tenantDetails['name'] ?? 'New Tenant',
                            'email'             => $tenantDetails['email'] ?? null,
                            'phone'             => $tenantDetails['phone'] ?? null,
                            'gender'            => $tenantDetails['gender'] ?? 'other',
                            'national_id'       => $tenantDetails['national_id'] ?? null,
                            'type'              => User::TYPE_TENANT,
                            'status'            => User::STATUS_PENDING,
                            'password'          => Hash::make(Str::random(12)),
                            'employment_status' => $tenantDetails['employment_status'] ?? 'employed',
                            'monthly_income'    => $tenantDetails['monthly_income'] ?? null,
                            'company_name'      => $tenantDetails['company_name'] ?? null,
                            'job_title'         => $tenantDetails['job_title'] ?? null,
                            'emergency_contact' => $tenantDetails['emergency_contact'] ?? [],
                            'created_by'        => $user->id,
                        ]);

                        $userInvitation = \App\Models\UserInvitation::create([
                            'user_id'    => $tenant->id,
                            'token'      => Str::random(64),
                            'invited_by' => $user->id,
                            'purpose'    => 'tenant_registration',
                            'channels'   => ($request->send_invitations && $request->has('invitation_channels'))
                                ? $request->invitation_channels : [],
                            'status'     => $request->send_invitations ? 'sent' : 'pending',
                            'sent_at'    => $request->send_invitations ? now() : null,
                            'expires_at' => now()->addDays(7),
                            'metadata'   => [
                                'property_name' => $unit->property->property_name ?? 'Unknown',
                                'unit_number'   => $unit->unit_number ?? 'Unknown',
                                'landlord_name' => $unit->property->landlord->name ?? 'Unknown',
                                'monthly_rent'  => $finalRentAmount,
                                'move_in_date'  => $moveInDate->format('Y-m-d'),
                            ],
                        ]);

                        $tenantInvitation = TenantInvitation::create([
                            'user_id'            => $tenant->id,
                            'property_id'        => $unit->property_id,
                            'unit_id'            => $unit->id,
                            'invited_by'         => $user->id,
                            'user_invitation_id' => $userInvitation->id,
                            'channels'           => ($request->send_invitations && $request->has('invitation_channels'))
                                ? $request->invitation_channels : [],
                            'status'             => $request->send_invitations
                                ? TenantInvitation::STATUS_SENT
                                : TenantInvitation::STATUS_PENDING,
                            'expires_at'         => now()->addDays(7),
                            'token'              => Str::random(64),
                            'metadata'           => [
                                'property_name' => $unit->property->property_name,
                                'unit_number'   => $unit->unit_number,
                                'landlord_name' => $unit->property->landlord->name ?? 'N/A',
                                'monthly_rent'  => $finalRentAmount,
                                'move_in_date'  => $moveInDate->format('Y-m-d'),
                                'purpose'       => 'new_tenant_registration',
                                'tenant_details'=> $tenantDetails,
                            ],
                        ]);

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
                                        'name'        => $document['name'] ?? 'document',
                                        'path'        => $newPath,
                                        'type'        => $document['type'] ?? 'application/pdf',
                                        'uploaded_at' => $document['uploaded_at'] ?? now()->toISOString(),
                                    ];
                                }
                            }
                        }

                        $unit->tenant_id             = $tenant->id;
                        $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->approved_by           = $user->id;
                        $unit->tenant_approved_at    = now();
                        $unit->tenant_approval_notes = $request->approval_notes;
                        $unit->tenant_move_in_date   = $moveInDate;
                        $unit->current_rent_amount   = $finalRentAmount;
                        $unit->status                = PropertyUnit::STATUS_OCCUPIED;
                        $unit->is_available          = false;
                        $unit->tenant_documents      = $newDocuments;
                        $unit->security_deposit      = $securityDeposit;
                        $unit->save();

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
                                        'status'              => TenantInvitation::STATUS_SENT,
                                        'sent_at'             => now(),
                                        'channels_successful' => $invitationResult['channels_successful'] ?? [],
                                    ]);

                                    $userInvitation->update([
                                        'status'  => 'sent',
                                        'sent_at' => now(),
                                    ]);

                                    $invitationSent = true;
                                }
                            } catch (\Throwable $e) {
                                Log::error('Failed to send invitation: ' . $e->getMessage());
                            }
                        }

                        $successCount++;
                        $details[] = [
                            'unit_id' => $unitId,
                            'success' => true,
                            'message' => 'New tenant approved and ' . ($request->send_invitations ? 'invitation sent' : 'created'),
                        ];
                    }

                    $this->logActivity(
                        $user->id,
                        'bulk_tenant_approval',
                        'Tenant approved via bulk approval',
                        $unit->id,
                        [
                            'tenant_type'  => $unit->tenant_type,
                            'tenant_id'    => $unit->tenant_id,
                            'move_in_date' => $moveInDate->format('Y-m-d'),
                            'final_rent'   => $finalRentAmount,
                            'is_landlord'  => $user->isLandlord(),
                        ]
                    );

                } catch (\Throwable $e) {
                    $failedCount++;
                    $details[] = [
                        'unit_id' => $unitId,
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                    Log::error('Bulk approval error for unit ' . $unitId . ': ' . $e->getMessage());
                }
            }

            $this->clearUnitCaches($user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk approval completed: {$successCount} approved, {$failedCount} failed.",
                'results' => [
                    'total'           => $successCount + $failedCount,
                    'successful'      => $successCount,
                    'failed'          => $failedCount,
                    'details'         => $details,
                    'invitation_sent' => $invitationSent,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in bulk approval: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk approval: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function processBulkApproval(Request $request)
    {
        Log::info('Redirecting from processBulkApproval to bulkApprove');
        return $this->bulkApprove($request);
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
        } catch (\Throwable $e) {
            Log::error('Failed to log activity: ' . $e->getMessage(), [
                'user_id' => $userId,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}