<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\Property;
use App\Models\User;
use App\Models\PropertyFamilyLink;
use App\Models\RentalAgreement;
use App\Models\MaintenanceRequest;
use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Models\TenantInvitation;
use App\Models\UnitPhoto;                       // ========== PHOTO
use App\Notifications\TenantAssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;                     // ========== PHOTO
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyUnitController extends Controller
{
    private const CACHE_TTL = 3600;
    private const CACHE_STATS_KEY = 'property_unit_stats_';
    private const CACHE_UNITS_KEY = 'property_units_';

    // ========== PHOTO CONSTANTS ==========
    private const PHOTO_DISK          = 'public';        // or 's3'
    private const PHOTO_DIR           = 'unit_photos';
    private const PHOTO_MAX_COUNT     = 15;              // hard cap per unit
    private const PHOTO_MAX_KB        = 10240;           // 10 MB
    private const PHOTO_THUMB_WIDTH   = 400;
    private const PHOTO_MAIN_WIDTH    = 1920;
    private const PHOTO_MIMES         = 'jpg,jpeg,png,webp';

    /** @var \Illuminate\Support\Collection<int, User>|null */
    private $adminUsersCache = null;

    // ========== INDEX / DASHBOARD ==========

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isTenant()) {
            $tenantUnit = PropertyUnit::with(['property', 'tenant', 'creator'])
                ->where('tenant_id', $user->id)
                ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
                ->withoutTrashed()
                ->first();

            if (!$tenantUnit) {
                return view('property_units.tenant-no-unit');
            }

            return view('property_units.index', [
                'tenantUnit' => $tenantUnit,
                'isTenant'   => true,
            ]);
        }

        if ($user->isLandlord()) {
            $accessiblePropertyIds = Property::query()
                ->accessibleBy($user)
                ->pluck('id')
                ->all();

            $query = PropertyUnit::with(['property', 'tenant', 'creator'])
                ->whereIn('property_id', $accessiblePropertyIds);

            $properties = Property::query()
                ->accessibleBy($user)
                ->orderBy('property_name')
                ->get();

            $baseQuery = PropertyUnit::query()
                ->whereIn('property_id', $accessiblePropertyIds)
                ->withoutTrashed();

        } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
            $query = PropertyUnit::with(['property', 'tenant', 'creator']);
            $properties = Property::orderBy('property_name')->get();
            $baseQuery = PropertyUnit::withoutTrashed();

        } else {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have access to property units.');
        }

        $stats = [
            'total_units'      => $baseQuery->count(),
            'available_units'  => (clone $baseQuery)->where('status', 'available')->count(),
            'occupied_units'   => (clone $baseQuery)->where('status', 'occupied')->count(),
            'pending_approval' => (clone $baseQuery)->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)->count(),
        ];

        $query->withoutTrashed();
        $this->applyFilters($query, $request);

        $sortField     = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSortFields = ['unit_number', 'created_at', 'monthly_rent', 'status'];

        if (in_array($sortField, $allowedSortFields, true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        $units = $query->paginate($perPage);

        $statusOptions       = PropertyUnit::getStatusOptions();
        $tenantStatusOptions = PropertyUnit::getTenantStatusOptions();
        $typeOptions         = PropertyUnit::getTypeOptions();

        if ($user->isLandlord() || $user->isAdmin() || $user->isSuperAdmin()) {
            $assignedTenantIds = PropertyUnit::whereNotNull('tenant_id')
                ->whereIn('tenant_status', [
                    PropertyUnit::TENANT_STATUS_APPROVED,
                    PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
                ])
                ->pluck('tenant_id')
                ->toArray();

            $accessiblePropertyIds = $user->isLandlord()
                ? Property::query()->accessibleBy($user)->pluck('id')->all()
                : null;

            $propertiesWithPendingTenants = Property::query()
                ->when($user->isLandlord(), fn ($q) =>
                    $q->whereIn('id', $accessiblePropertyIds)
                )
                ->whereHas('tenants', function ($q) use ($assignedTenantIds) {
                    $q->where('users.type', User::TYPE_TENANT);
                    if (!empty($assignedTenantIds)) {
                        $q->whereNotIn('users.id', $assignedTenantIds);
                    }
                })
                ->with(['tenants' => function ($q) use ($assignedTenantIds) {
                    $q->where('users.type', User::TYPE_TENANT);
                    if (!empty($assignedTenantIds)) {
                        $q->whereNotIn('users.id', $assignedTenantIds);
                    }
                }])
                ->get()
                ->filter(fn ($property) => $property->tenants->isNotEmpty())
                ->values();
        } else {
            $propertiesWithPendingTenants = collect();
        }

        $linkedPropertyIds = collect();
        $manageUnitsPropertyIds = collect();

        if ($user->isLandlord()) {
            [$linkedPropertyIds, $manageUnitsPropertyIds] = $this->familyLinkSetsFor($user);
        }

        $isOwner = $user->isLandlord() && Property::query()
            ->where('landlord_id', $user->id)
            ->whereIn('id', $properties->pluck('id'))
            ->exists();

        $isLinkedFamilyMember = $linkedPropertyIds->isNotEmpty();
        $hasManageUnitsAccess = $manageUnitsPropertyIds->isNotEmpty();

        $routePrefix = $user->isLandlord()
            ? 'landlord.property-units'
            : 'admin.property-units';

        return view('property_units.index', [
            'units'                        => $units,
            'properties'                   => $properties ?? collect(),
            'stats'                        => $stats ?? [],
            'statusOptions'                => $statusOptions,
            'tenantStatusOptions'          => $tenantStatusOptions,
            'typeOptions'                  => $typeOptions,
            'propertiesWithPendingTenants' => $propertiesWithPendingTenants,
            'isTenant'                     => false,
            'isOwner'                      => $isOwner,
            'isLinkedFamilyMember'         => $isLinkedFamilyMember,
            'hasManageUnitsAccess'         => $hasManageUnitsAccess,
            'linkedPropertyIds'            => $linkedPropertyIds,
            'manageUnitsPropertyIds'       => $manageUnitsPropertyIds,
            'routePrefix'                  => $routePrefix,
        ]);
    }

    // ========== SHOW ==========

    public function show($id)
    {
        $user = auth()->user();

        if ($user->isTenant()) {
            return app(PropertyUnitTenantController::class)->showForTenant($id);
        }

        try {
            $unit = PropertyUnit::with([
                'property',
                'tenant',
                'creator',
                'approvedBy',
                'requestedBy',
                'currentLease',
                'photos',                                   // ========== PHOTO
                'rentalAgreements' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(5),
                'maintenanceRequests' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(5),
            ])->findOrFail($id);

            $this->checkUnitAccessWithError($unit);

            $statsCacheKey = 'unit_stats_' . $id;
            $stats = Cache::remember($statsCacheKey, self::CACHE_TTL, function () use ($unit) {
                return [
                    'total_rental_agreements'     => $unit->rentalAgreements()->count(),
                    'active_maintenance_requests' => $unit->maintenanceRequests()
                        ->whereIn('status', ['pending', 'in_progress'])
                        ->count(),
                    'total_rent_collected'        => $this->calculateTotalRentCollected($unit),
                    'outstanding_invoices'        => $unit->invoices()
                        ->whereIn('status', ['pending', 'overdue'])
                        ->sum('amount'),
                ];
            });

            $activities     = $this->getUnitActivities($unit);
            $amenityOptions = $this->getAmenityOptions();
            $userRole       = $user->type;

            $flags = $this->familyLinkFlagsFor($unit->property, $user);

            $isOwner              = $flags['isOwner'];
            $isLinkedFamilyMember = $flags['isLinkedFamilyMember'];
            $hasManageUnitsAccess = $flags['hasManageUnitsAccess'];

            $canManageUnits = $isOwner || ($isLinkedFamilyMember && $hasManageUnitsAccess);
            $canDeleteUnit  = $isOwner;

            $isAssignedTenant = $user->isTenant()
                && (int) $unit->tenant_id === (int) $user->id
                && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED;

            $isOccupied = ($unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED
                            && $unit->status === PropertyUnit::STATUS_OCCUPIED)
                || $unit->status === PropertyUnit::STATUS_OCCUPIED;

            $canEdit = $canManageUnits && !$isOccupied;

            // ========== PHOTO: gallery permissions ==========
            $canManagePhotos = $canManageUnits;
            $unitPhotos      = $unit->photos()
                ->ordered()
                ->get()
                ->map(fn ($p) => [
                    'id'         => $p->id,
                    'url'        => Storage::disk($p->disk)->url($p->path),
                    'thumb_url'  => $p->thumbnail_path
                        ? Storage::disk($p->disk)->url($p->thumbnail_path)
                        : Storage::disk($p->disk)->url($p->path),
                    'caption'    => $p->caption,
                    'is_cover'   => (bool) $p->is_cover,
                    'size_bytes' => $p->size_bytes,
                    'uploaded_by'=> $p->uploaded_by,
                    'created_at' => optional($p->created_at)->toDateTimeString(),
                ]);

            $photoMaxCount = self::PHOTO_MAX_COUNT;
            $photoMaxKb    = self::PHOTO_MAX_KB;
            $photoMimes    = self::PHOTO_MIMES;
            $photoUploadUrl = route('property-units.photos.store', $unit->id);
            $photoDeleteUrlBase = route('property-units.photos.destroy', ['id' => $unit->id, 'photo' => '__ID__']);
            $photoCoverUrlBase  = route('property-units.photos.cover',  ['id' => $unit->id, 'photo' => '__ID__']);

            $displayStatus = $isOccupied ? PropertyUnit::STATUS_OCCUPIED : $unit->status;

            $routePrefix = $user->isLandlord()
                ? 'landlord.property-units'
                : 'admin.property-units';

            return view('property_units.show', compact(
                'unit',
                'stats',
                'activities',
                'userRole',
                'isOwner',
                'isLinkedFamilyMember',
                'hasManageUnitsAccess',
                'canManageUnits',
                'canDeleteUnit',
                'isAssignedTenant',
                'canEdit',
                'amenityOptions',
                'displayStatus',
                'routePrefix',
                // ========== PHOTO ==========
                'canManagePhotos',
                'unitPhotos',
                'photoMaxCount',
                'photoMaxKb',
                'photoMimes',
                'photoUploadUrl',
                'photoDeleteUrlBase',
                'photoCoverUrlBase'
            ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('property-units.index')
                ->with('error', 'Property unit not found or has been removed.');
        }
    }

    // ========== CREATE ==========

    public function create(Request $request)
    {
        $user = auth()->user();

        $canReachCreateForm =
            $user->isLandlord()
            || $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->isDeveloper();

        if (!$canReachCreateForm) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to create property units.');
        }

        $properties = $this->getAvailableProperties();

        if ($properties->isEmpty()) {
            $isLinkedOnly = PropertyFamilyLink::query()
                ->where('linked_user_id', $user->id)
                ->where('status', PropertyFamilyLink::STATUS_APPROVED)
                ->exists();

            $errorMessage = $isLinkedOnly
                ? 'None of your linked properties are eligible for unit creation yet. Ask the owner to add construction details.'
                : 'You need to create a property first before adding units.';

            return redirect()->route('properties.index')->with('error', $errorMessage);
        }

        $selectedPropertyId = $request->input('property_id');

        if ($selectedPropertyId) {
            $isInAccessibleSet = $properties->contains(
                fn ($p) => (int) $p->id === (int) $selectedPropertyId
            );

            if (!$isInAccessibleSet) {
                $selectedPropertyId = null;

                if ($request->filled('property_id')) {
                    session()->flash(
                        'error',
                        'The selected property is not accessible or does not exist. Pick one from the list.'
                    );
                }
            }
        }

        $statusOptions  = PropertyUnit::getStatusOptions();
        $typeOptions    = PropertyUnit::getTypeOptions();
        $amenityOptions = $this->getAmenityOptions();

        $isOwnerOfAnyVisibleProperty = $properties->contains(
            fn ($p) => (int) $p->landlord_id === (int) $user->id
        );

        $isOwner = $isOwnerOfAnyVisibleProperty;

        $isLinkedFamilyMember = !$isOwnerOfAnyVisibleProperty
            && $properties->isNotEmpty();

        $hasManageUnitsAccess = $isLinkedFamilyMember
            && $user->can('manageUnits', $properties->first());

        $isActingAsFamilyMember = $isLinkedFamilyMember;

        if ($user->isLandlord()) {
            $routePrefix = 'landlord.property-units';
        } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
            $routePrefix = 'admin.property-units';
        } elseif ($user->isDeveloper()) {
            $routePrefix = 'developer.property-units';
        } else {
            $routePrefix = 'property-units';
        }

        // ========== PHOTO: pass limits to create view (optional display only) ==========
        $photoMaxCount = self::PHOTO_MAX_COUNT;
        $photoMaxKb    = self::PHOTO_MAX_KB;
        $photoMimes    = self::PHOTO_MIMES;

        return view('property_units.create', compact(
            'properties',
            'selectedPropertyId',
            'statusOptions',
            'typeOptions',
            'amenityOptions',
            'isActingAsFamilyMember',
            'isOwner',
            'isLinkedFamilyMember',
            'hasManageUnitsAccess',
            'routePrefix',
            'photoMaxCount',
            'photoMaxKb',
            'photoMimes'
        ));
    }

    // ========== STORE ==========

    public function store(Request $request)
    {
        $user = auth()->user();

        $canCreateUnits =
            $user->isLandlord()
            || $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->isDeveloper();

        if (!$canCreateUnits) {
            return redirect()->back()
                ->with('error', 'You do not have permission to create property units.')
                ->withInput();
        }

        $validator = $this->validateUnitRequest($request);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $propertyQuery = Property::query();

        if (!($user->isAdmin() || $user->isSuperAdmin() || $user->isDeveloper())) {
            $propertyQuery->accessibleBy($user);
        }

        $property = $propertyQuery->findOrFail($request->property_id);

        if (!$user->can('manageUnits', $property)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to create units on this property.')
                ->withInput();
        }

        $flags = $this->familyLinkFlagsFor($property, $user);

        DB::beginTransaction();
        try {
            $unit = new PropertyUnit();

            $unit->property_id      = $request->property_id;
            $unit->unit_number      = $request->unit_number;
            $unit->unit_name        = $request->unit_name;
            $unit->unit_type        = $request->unit_type;
            $unit->description      = $request->description;
            $unit->floor_area       = $request->floor_area;
            $unit->bedrooms         = $request->bedrooms;
            $unit->bathrooms        = $request->bathrooms;
            $unit->living_rooms     = $request->living_rooms;
            $unit->kitchens         = $request->kitchens;
            $unit->monthly_rent     = $request->monthly_rent;
            $unit->security_deposit = $request->security_deposit;
            $unit->is_furnished     = $request->boolean('is_furnished');
            $unit->status           = $request->status;
            $unit->is_available     = $request->boolean('is_available');
            $unit->available_from   = $request->available_from;
            $unit->amenities        = $request->amenities;
            $unit->created_by       = $user->id;

            $tenantWasAssigned = false;
            $tenant            = null;
            $isOwnerOrAdmin    = false;

            if ($request->filled('assign_tenant_id')) {
                $tenant = User::find($request->assign_tenant_id);

                if ($tenant && $tenant->isTenant()) {
                    $existingAssignment = PropertyUnit::where('tenant_id', $tenant->id)
                        ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
                        ->exists();

                    if ($existingAssignment) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', 'This tenant is already assigned to another unit.')
                            ->withInput();
                    }

                    $unit->tenant_id           = $tenant->id;
                    $unit->tenant_status       = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
                    $unit->tenant_type         = PropertyUnit::TENANT_TYPE_EXISTING;
                    $unit->tenant_requested_by = $user->id;
                    $unit->tenant_requested_at = now();
                    $unit->tenant_move_in_date = $request->available_from;
                    $unit->proposed_rent       = $unit->monthly_rent;

                    $unit->tenant_details = json_encode([
                        'name'              => $tenant->name,
                        'email'             => $tenant->email,
                        'phone'             => $tenant->phone,
                        'assigned_at'       => now()->toDateTimeString(),
                        'assigned_by'       => $user->name,
                        'assigned_by_id'    => $user->id,
                        'preferred_move_in' => $request->preferred_move_in ?? null,
                        'preferred_rent'    => $request->preferred_rent ?? null,
                    ]);

                    $isOwnerOrAdmin =
                        $flags['isOwner']
                        || $user->isAdmin()
                        || $user->isSuperAdmin()
                        || ($flags['isLinkedFamilyMember'] && $flags['hasManageUnitsAccess']);

                    if ($isOwnerOrAdmin) {
                        $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->tenant_approved_by    = $user->id;
                        $unit->tenant_approved_at    = now();
                        $unit->tenant_approval_notes = 'Auto-approved during unit creation';
                        $unit->status                = PropertyUnit::STATUS_OCCUPIED;
                    }

                    $tenantWasAssigned = true;

                    $this->logActivity(
                        $user->id,
                        'property_unit_created_with_tenant',
                        'Property unit created and tenant ' . ($isOwnerOrAdmin ? 'assigned' : 'requested'),
                        null,
                        [
                            'unit_number'                  => $unit->unit_number,
                            'property_id'                  => $unit->property_id,
                            'tenant_id'                    => $tenant->id,
                            'tenant_name'                  => $tenant->name,
                            'tenant_status'                => $unit->tenant_status,
                            'created_by_was_family_member' => !$flags['isOwner'],
                        ]
                    );
                } else {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Invalid tenant selected for assignment.')
                        ->withInput();
                }
            } else {
                $unit->tenant_id             = null;
                $unit->tenant_status         = null;
                $unit->tenant_type           = null;
                $unit->tenant_requested_by   = null;
                $unit->tenant_requested_at   = null;
                $unit->tenant_documents      = null;
                $unit->proposed_rent         = null;
                $unit->tenant_approval_notes = null;
                $unit->tenant_move_in_date   = null;
                $unit->invitation_channels   = null;
                $unit->tenant_details        = null;
            }

            $unit->save();

            // ========== PHOTO: persist uploaded photos (after unit exists) ==========
            if ($request->hasFile('photos')) {
                $this->storeUnitPhotos($unit, $request->file('photos'), $user);
            }

            if ($tenantWasAssigned && $tenant) {
                $this->notifyTenantAssignment($unit, $tenant, $user);
            }

            $this->invalidateUnitCachesAfterChange($property, $user->id);
            Cache::forget('unit_photos_' . $unit->id);   // ========== PHOTO

            DB::commit();

            $successMessage = 'Property unit created successfully!';
            if ($tenantWasAssigned) {
                $tenantStatus   = $isOwnerOrAdmin ? 'approved and assigned' : 'pending approval';
                $successMessage = "Property unit created successfully! The tenant has been {$tenantStatus} to this unit.";
            } else {
                $successMessage .= ' The unit is now available for tenant assignment.';
            }

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', $successMessage);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating property unit: ' . $e->getMessage(), [
                'exception'    => $e,
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create property unit: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== EDIT ==========

    public function edit($id)
    {
        $user = auth()->user();
        $unit = PropertyUnit::with(['property', 'tenant', 'photos'])->findOrFail($id);   // ========== PHOTO

        if (!$user->can('manageUnits', $unit->property)) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'You do not have permission to edit this unit.');
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'You cannot edit an occupied unit.');
        }

        $flags = $this->familyLinkFlagsFor($unit->property, $user);

        $routePrefix = $user->isLandlord()
            ? 'landlord.property-units'
            : ($user->isAdmin() || $user->isSuperAdmin()
                ? 'admin.property-units'
                : 'property-units');

        // ========== PHOTO ==========
        $unitPhotos    = $unit->photos()->ordered()->get();
        $photoMaxCount = self::PHOTO_MAX_COUNT;
        $photoMaxKb    = self::PHOTO_MAX_KB;
        $photoMimes    = self::PHOTO_MIMES;

        return view('property_units.edit', [
            'unit'                  => $unit,
            'isOwner'               => $flags['isOwner'],
            'isLinkedFamilyMember'  => $flags['isLinkedFamilyMember'],
            'hasManageUnitsAccess'  => $flags['hasManageUnitsAccess'],
            'isOnLandlordDashboard' => $user->isLandlord(),
            'routePrefix'           => $routePrefix,
            'unitPhotos'            => $unitPhotos,
            'photoMaxCount'         => $photoMaxCount,
            'photoMaxKb'            => $photoMaxKb,
            'photoMimes'            => $photoMimes,
        ]);
    }

    // ========== UPDATE ==========

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $unit = PropertyUnit::findOrFail($id);

        if (!$user->can('manageUnits', $unit->property)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to update this unit.')
                ->withInput();
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->back()
                ->with('error', 'You cannot edit an occupied unit. Please vacate the tenant first.')
                ->withInput();
        }

        $validator = $this->validateUnitRequest($request, $unit);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($request->filled('property_id') && (int) $request->property_id !== (int) $unit->property_id) {
            $targetProperty = Property::query()
                ->when(
                    !($user->isAdmin() || $user->isSuperAdmin() || $user->isDeveloper()),
                    fn ($q) => $q->accessibleBy($user)
                )
                ->find($request->property_id);

            if (!$targetProperty || !$user->can('manageUnits', $targetProperty)) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to move this unit to the selected property.')
                    ->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $oldData            = $unit->toArray();
            $previousPropertyId = $unit->property_id;
            $previousOwnerId    = (int) $unit->property->landlord_id;

            $unit->property_id      = $request->property_id;
            $unit->unit_number      = $request->unit_number;
            $unit->unit_name        = $request->unit_name;
            $unit->unit_type        = $request->unit_type;
            $unit->description      = $request->description;
            $unit->floor_area       = $request->floor_area;
            $unit->bedrooms         = $request->bedrooms;
            $unit->bathrooms        = $request->bathrooms;
            $unit->living_rooms     = $request->living_rooms;
            $unit->kitchens         = $request->kitchens;
            $unit->monthly_rent     = $request->monthly_rent;
            $unit->security_deposit = $request->security_deposit;
            $unit->is_furnished     = $request->boolean('is_furnished');
            $unit->status           = $request->status;
            $unit->is_available     = $request->boolean('is_available');
            $unit->available_from   = $request->available_from;
            $unit->amenities        = $request->amenities;

            if ((int) $previousPropertyId !== (int) $unit->property_id) {
                $unit->load('property');
            }

            $newlyAssignedTenant = null;
            if ($request->filled('assign_tenant_id') && $request->assign_tenant_id != $unit->tenant_id) {
                $tenant = User::find($request->assign_tenant_id);

                if ($tenant && $tenant->isTenant()) {
                    $existingAssignment = PropertyUnit::where('tenant_id', $tenant->id)
                        ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
                        ->where('id', '!=', $unit->id)
                        ->exists();

                    if ($existingAssignment) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', 'This tenant is already assigned to another unit.')
                            ->withInput();
                    }

                    $unit->tenant_id           = $tenant->id;
                    $unit->tenant_status       = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
                    $unit->tenant_type         = PropertyUnit::TENANT_TYPE_EXISTING;
                    $unit->tenant_requested_by = $user->id;
                    $unit->tenant_requested_at = now();
                    $unit->tenant_move_in_date = $request->available_from ?? now();

                    if (empty($unit->proposed_rent)) {
                        $unit->proposed_rent = $unit->monthly_rent;
                    }

                    $existingDetails = $unit->tenant_details
                        ? json_decode($unit->tenant_details, true)
                        : [];

                    $unit->tenant_details = json_encode(array_merge($existingDetails, [
                        'name'           => $tenant->name,
                        'email'          => $tenant->email,
                        'phone'          => $tenant->phone,
                        'assigned_at'    => now()->toDateTimeString(),
                        'assigned_by'    => $user->name,
                        'assigned_by_id' => $user->id,
                    ]));

                    $flags = $this->familyLinkFlagsFor($unit->property, $user);
                    $isOwnerOrAdmin =
                        $flags['isOwner']
                        || $user->isAdmin()
                        || $user->isSuperAdmin()
                        || ($flags['isLinkedFamilyMember'] && $flags['hasManageUnitsAccess']);

                    if ($isOwnerOrAdmin) {
                        $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->tenant_approved_by    = $user->id;
                        $unit->tenant_approved_at    = now();
                        $unit->tenant_approval_notes = 'Auto-approved during unit update';
                        $unit->status                = PropertyUnit::STATUS_OCCUPIED;
                    }

                    $newlyAssignedTenant = $tenant;
                }
            }

            if ($request->status === PropertyUnit::STATUS_AVAILABLE && $unit->tenant_id) {
                $unit->tenant_id           = null;
                $unit->tenant_status       = null;
                $unit->current_rent_amount = $unit->monthly_rent;
            }

            $unit->save();

            // ========== PHOTO: delete selected photos ==========
            if ($request->filled('remove_photo_ids') && is_array($request->remove_photo_ids)) {
                $this->deleteUnitPhotoFiles($unit, $request->remove_photo_ids);
            }

            // ========== PHOTO: append newly uploaded photos ==========
            if ($request->hasFile('photos')) {
                $this->storeUnitPhotos($unit, $request->file('photos'), $user);
            }

            if ($newlyAssignedTenant) {
                $this->notifyTenantAssignment($unit, $newlyAssignedTenant, $user);
            }

            $changes = $this->getChangedFields($oldData, $unit->toArray());
            if (!empty($changes) || $request->hasFile('photos') || $request->filled('remove_photo_ids')) {
                $this->logActivity(
                    $user->id,
                    'property_unit_updated',
                    'Property unit updated',
                    $unit->id,
                    [
                        'changes'                      => $changes,
                        'photos_added'                 => $request->hasFile('photos') ? count($request->file('photos')) : 0,
                        'photos_removed'               => $request->filled('remove_photo_ids') ? count((array) $request->remove_photo_ids) : 0,
                        'updated_by_was_family_member' => $previousOwnerId !== (int) $user->id,
                    ]
                );
            }

            $this->invalidateUnitCachesAfterChange($unit->property, $user->id);

            if ($previousOwnerId && $previousOwnerId !== (int) $user->id) {
                $this->clearUnitCaches($previousOwnerId);
            }

            Cache::forget('property_unit_' . $id . '_with_relations');
            Cache::forget('unit_stats_' . $id);
            Cache::forget('unit_photos_' . $id);         // ========== PHOTO

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Property unit updated successfully!');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating property unit: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update property unit: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== DESTROY ==========

    public function destroy($id)
    {
        $user = auth()->user();
        $unit = PropertyUnit::findOrFail($id);

        $isOwner = (int) $unit->property->landlord_id === (int) $user->id;

        if (!$isOwner || !$user->can('delete', $unit->property)) {
            return redirect()->back()
                ->with('error', 'Only the property owner can delete this unit.');
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->back()
                ->with('error', 'Cannot delete an occupied unit. Please vacate the tenant first.');
        }

        DB::beginTransaction();
        try {
            $unitData   = $unit->toArray();
            $propertyId = $unit->property_id;
            $ownerId    = (int) $unit->property->landlord_id;

            // ========== PHOTO: cascade-delete photo files + rows ==========
            $this->deleteUnitPhotoFiles($unit, null, true);

            $unit->delete();

            $this->logActivity(
                $user->id,
                'property_unit_deleted',
                'Property unit deleted',
                $id,
                [
                    'unit_data'            => $unitData,
                    'deleted_by_was_owner' => $ownerId === (int) $user->id,
                ]
            );

            $this->clearAllUnitCaches();

            Cache::forget('property_units_' . $propertyId);
            Cache::forget('property_stats_' . $propertyId);
            Cache::forget('unit_photos_' . $id);         // ========== PHOTO

            DB::commit();

            return redirect()->route('property-units.index')
                ->with('success', 'Property unit deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error deleting property unit: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete property unit: ' . $e->getMessage());
        }
    }

    // ========== PHOTO UPLOAD (AJAX, per unit) ==========

    /**
     * POST /property-units/{id}/photos
     * Accepts 1..N images. Returns JSON with the created photo records.
     * Gated by the same `manageUnits` ability as store/update.
     */
    public function uploadPhotos(Request $request, $id)
    {
        $user = auth()->user();
        $unit = PropertyUnit::with('property')->findOrFail($id);

        if (!$user->can('manageUnits', $unit->property)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $existingCount = $unit->photos()->count();
        if ($existingCount >= self::PHOTO_MAX_COUNT) {
            return response()->json([
                'error' => 'This unit already has the maximum of ' . self::PHOTO_MAX_COUNT . ' photos.',
            ], 422);
        }

        $remaining = self::PHOTO_MAX_COUNT - $existingCount;

        $validator = Validator::make($request->all(), [
            'photos'   => 'required|array|max:' . $remaining,
            'photos.*' => 'required|image|mimes:' . self::PHOTO_MIMES . '|max:' . self::PHOTO_MAX_KB,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $created = $this->storeUnitPhotos($unit, $request->file('photos'), $user);
            DB::commit();

            Cache::forget('unit_photos_' . $unit->id);

            return response()->json([
                'success' => true,
                'photos'  => collect($created)->map(fn ($p) => [
                    'id'        => $p->id,
                    'url'       => Storage::disk($p->disk)->url($p->path),
                    'thumb_url' => $p->thumbnail_path
                        ? Storage::disk($p->disk)->url($p->thumbnail_path)
                        : Storage::disk($p->disk)->url($p->path),
                    'is_cover'  => (bool) $p->is_cover,
                ]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Photo upload failed: ' . $e->getMessage(), [
                'unit_id'   => $unit->id,
                'user_id'   => $user->id,
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Upload failed. Please try again.'], 500);
        }
    }

    /**
     * DELETE /property-units/{id}/photos/{photo}
     * Gated by `manageUnits`. Soft-deletes the row and removes files.
     */
    public function deletePhoto($id, $photoId)
    {
        $user = auth()->user();
        $unit = PropertyUnit::with('property')->findOrFail($id);
        $photo = UnitPhoto::where('property_unit_id', $unit->id)->findOrFail($photoId);

        if (!$user->can('manageUnits', $unit->property)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $wasCover = (bool) $photo->is_cover;

            if ($photo->path && Storage::disk($photo->disk)->exists($photo->path)) {
                Storage::disk($photo->disk)->delete($photo->path);
            }
            if ($photo->thumbnail_path && Storage::disk($photo->disk)->exists($photo->thumbnail_path)) {
                Storage::disk($photo->disk)->delete($photo->thumbnail_path);
            }
            $photo->delete();

            // If the deleted photo was the cover, promote the next oldest photo.
            if ($wasCover) {
                $next = $unit->photos()->ordered()->first();
                if ($next) {
                    $next->is_cover = true;
                    $next->save();
                }
            }

            $this->logActivity(
                $user->id,
                'unit_photo_deleted',
                'Unit photo deleted',
                $unit->id,
                ['photo_id' => $photoId]
            );

            Cache::forget('unit_photos_' . $unit->id);
            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Photo delete failed: ' . $e->getMessage(), [
                'unit_id'  => $unit->id,
                'photo_id' => $photoId,
            ]);
            return response()->json(['error' => 'Delete failed.'], 500);
        }
    }

    /**
     * PATCH /property-units/{id}/photos/{photo}/cover
     * Marks the given photo as the unit's cover image.
     */
    public function setCoverPhoto($id, $photoId)
    {
        $user = auth()->user();
        $unit = PropertyUnit::with('property')->findOrFail($id);
        $photo = UnitPhoto::where('property_unit_id', $unit->id)->findOrFail($photoId);

        if (!$user->can('manageUnits', $unit->property)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            UnitPhoto::where('property_unit_id', $unit->id)
                ->where('id', '!=', $photo->id)
                ->update(['is_cover' => false]);

            $photo->is_cover = true;
            $photo->save();

            $this->logActivity(
                $user->id,
                'unit_photo_cover_changed',
                'Unit cover photo changed',
                $unit->id,
                ['photo_id' => $photoId]
            );

            Cache::forget('unit_photos_' . $unit->id);
            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Set cover photo failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to set cover.'], 500);
        }
    }

    // ========== TRASH / RECYCLE BIN ==========

    public function trash(Request $request)
    {
        $user = auth()->user();

        if (!$user->isLandlord()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only landlords can access deleted units.');
        }

        $accessiblePropertyIds = Property::query()
            ->accessibleBy($user)
            ->pluck('id')
            ->all();

        $query = PropertyUnit::onlyTrashed()
            ->with(['property', 'tenant', 'creator'])
            ->whereIn('property_id', $accessiblePropertyIds);

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%")
                    ->orWhereHas('property', fn ($q) => $q->where('property_name', 'like', "%{$search}%"));
            });
        }

        $sortField     = $request->input('sort', 'deleted_at');
        $sortDirection = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortField, ['unit_number', 'deleted_at', 'monthly_rent', 'created_at'], true)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        $trashedUnits = $query->paginate($perPage);

        $properties = Property::query()
            ->accessibleBy($user)
            ->orderBy('property_name')
            ->get();

        $typeOptions = PropertyUnit::getTypeOptions();

        $ownedPropertyIds = Property::query()
            ->where('landlord_id', $user->id)
            ->pluck('id')
            ->all();

        $isOwner              = !empty(array_intersect($ownedPropertyIds, $accessiblePropertyIds));
        $isLinkedFamilyMember = empty($ownedPropertyIds) && !empty($accessiblePropertyIds);
        $hasManageUnitsAccess = false;

        return view('property_units.trash', compact(
            'trashedUnits',
            'properties',
            'typeOptions',
            'request',
            'isOwner',
            'isLinkedFamilyMember',
            'hasManageUnitsAccess',
        ));
    }

    public function restoreFromTrash($id)
    {
        $user = auth()->user();

        if (!$user->isLandlord()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only landlords can restore deleted units.');
        }

        $unit = PropertyUnit::onlyTrashed()
            ->with(['property'])
            ->findOrFail($id);

        $isOwner = $unit->property
            && (int) $unit->property->landlord_id === (int) $user->id;

        if (!$isOwner) {
            return redirect()->route('property-units.trash')
                ->with('error', 'Only the property owner can restore this unit.');
        }

        DB::beginTransaction();
        try {
            $duplicateCheck = PropertyUnit::where('property_id', $unit->property_id)
                ->where('unit_number', $unit->unit_number)
                ->where('id', '!=', $unit->id)
                ->exists();

            if ($duplicateCheck) {
                return redirect()->back()
                    ->with('error', 'Cannot restore unit. Another unit with the same number exists in this property.');
            }

            $unit->restore();

            $unit->update([
                'is_archived' => false,
                'archived_at' => null,
                'archived_by' => null,
            ]);

            $this->logActivity(
                $user->id,
                'property_unit_restored_trash',
                'Property unit restored from trash',
                $unit->id,
                ['unit_number' => $unit->unit_number]
            );

            $this->clearUnitCaches($user->id);

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Property unit restored successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error restoring property unit from trash: ' . $e->getMessage(), [
                'exception' => $e,
                'unit_id'   => $id,
            ]);

            return redirect()->route('property-units.trash')
                ->with('error', 'Failed to restore property unit: ' . $e->getMessage());
        }
    }

    // ========== TENANT APPROVAL WORKFLOW (admin-only) ==========

    public function approveTenant(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'Only admins can approve tenant assignments.');
        }

        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);

        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
            return redirect()->back()->with('error', 'This unit does not have a pending tenant assignment.');
        }

        DB::beginTransaction();
        try {
            $unit->tenant_status         = PropertyUnit::TENANT_STATUS_APPROVED;
            $unit->tenant_approved_by    = $user->id;
            $unit->tenant_approved_at    = now();
            $unit->tenant_approval_notes = $request->input('notes', 'Approved by admin');
            $unit->status                = PropertyUnit::STATUS_OCCUPIED;
            $unit->save();

            $this->logActivity(
                $user->id,
                'tenant_assignment_approved',
                'Tenant assignment approved by admin',
                $unit->id,
                ['tenant_id' => $unit->tenant_id]
            );

            if ($unit->tenant) {
                $this->notifyTenantAssignmentApproved($unit, $unit->tenant, $user);
            }

            $this->clearAllUnitCaches();

            DB::commit();

            return redirect()->back()->with('success', 'Tenant assignment approved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error approving tenant assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to approve tenant assignment.');
        }
    }

    public function rejectTenant(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'Only admins can reject tenant assignments.');
        }

        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);

        if ($unit->tenant_status !== PropertyUnit::TENANT_STATUS_PENDING_APPROVAL) {
            return redirect()->back()->with('error', 'This unit does not have a pending tenant assignment.');
        }

        DB::beginTransaction();
        try {
            $rejectedTenantId = $unit->tenant_id;

            $unit->tenant_status         = PropertyUnit::TENANT_STATUS_REJECTED;
            $unit->tenant_approved_by    = $user->id;
            $unit->tenant_approved_at    = now();
            $unit->tenant_approval_notes = $request->input('notes', 'Rejected by admin');
            $unit->save();

            $this->logActivity(
                $user->id,
                'tenant_assignment_rejected',
                'Tenant assignment rejected by admin',
                $unit->id,
                ['tenant_id' => $rejectedTenantId]
            );

            $this->clearAllUnitCaches();

            DB::commit();

            return redirect()->back()->with('success', 'Tenant assignment rejected.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error rejecting tenant assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to reject tenant assignment.');
        }
    }

    // ========== EXPORT ==========

    public function export(Request $request)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isLandlord()) {
            return redirect()->back()->with('error', 'You do not have permission to export data.');
        }

        $query = PropertyUnit::with(['property', 'tenant', 'creator'])
            ->withoutTrashed();

        if ($user->isLandlord()) {
            $accessiblePropertyIds = Property::query()
                ->accessibleBy($user)
                ->pluck('id')
                ->all();

            $query->whereIn('property_id', $accessiblePropertyIds);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant_status')) {
            $query->where('tenant_status', $request->tenant_status);
        }
        if ($request->filled('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%")
                    ->orWhereHas('property', fn ($q) => $q->where('property_name', 'like', "%{$search}%"))
                    ->orWhereHas('tenant', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $units = $query->get();

        if ($units->isEmpty()) {
            return redirect()->back()->with('error', 'No data available to export.');
        }

        $format = $request->input('format', 'csv');

        return match ($format) {
            'pdf'   => $this->exportToPdf($units),
            default => $this->exportToCsv($units),
        };
    }

    private function exportToCsv($units)
    {
        $filename = 'property_units_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($units) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Unit ID', 'Unit Number', 'Unit Name', 'Unit Type',
                'Property ID', 'Property Name', 'Landlord',
                'Status', 'Availability',
                'Monthly Rent (GHS)', 'Security Deposit (GHS)',
                'Bedrooms', 'Bathrooms', 'Living Rooms', 'Kitchens',
                'Floor Area (sq ft)', 'Furnished',
                'Tenant ID', 'Tenant Name', 'Tenant Email', 'Tenant Phone',
                'Tenant Status', 'Move-in Date',
                'Created At', 'Updated At',
            ]);

            foreach ($units as $unit) {
                fputcsv($file, [
                    $unit->id,
                    $unit->unit_number,
                    $unit->unit_name ?? 'N/A',
                    $unit->unit_type ?? 'N/A',
                    $unit->property_id,
                    $unit->property->property_name ?? 'N/A',
                    $unit->property->landlord->name ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $unit->status)),
                    $unit->is_available ? 'Available' : 'Not Available',
                    number_format($unit->monthly_rent ?? 0, 2),
                    number_format($unit->security_deposit ?? 0, 2),
                    $unit->bedrooms ?? 0,
                    $unit->bathrooms ?? 0,
                    $unit->living_rooms ?? 0,
                    $unit->kitchens ?? 0,
                    $unit->floor_area ?? 'N/A',
                    $unit->is_furnished ? 'Yes' : 'No',
                    $unit->tenant_id ?? 'N/A',
                    $unit->tenant->name ?? 'N/A',
                    $unit->tenant->email ?? 'N/A',
                    $unit->tenant->phone ?? 'N/A',
                    $unit->tenant_status ? ucfirst(str_replace('_', ' ', $unit->tenant_status)) : 'N/A',
                    $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : 'N/A',
                    $unit->created_at->format('Y-m-d H:i:s'),
                    $unit->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToPdf($units)
    {
        $data = [
            'units'          => $units,
            'exported_at'    => Carbon::now()->format('F j, Y H:i:s'),
            'total_units'    => $units->count(),
            'status_summary' => $this->getStatusSummary($units),
        ];

        $pdf = Pdf::loadView('property_units.exports.pdf', $data);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'property_units_export_' . Carbon::now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    private function getStatusSummary($units)
    {
        $summary = [];
        foreach ($units as $unit) {
            $status = $unit->status ?? 'unknown';
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }
        return $summary;
    }

    // ========== PHOTO HELPERS ==========

    /**
     * Validation rules for photo uploads (reused by store/update/uploadPhotos).
     */
    private function photoRules(bool $required = false): array
    {
        return [
            'photos'   => ($required ? 'required' : 'sometimes') . '|array|max:' . self::PHOTO_MAX_COUNT,
            'photos.*' => 'required|image|mimes:' . self::PHOTO_MIMES . '|max:' . self::PHOTO_MAX_KB,
        ];
    }

    /**
     * Persist uploaded photos for a unit.
     * Returns the created UnitPhoto collection.
     *
     * @param  \Illuminate\Http\UploadedFile[]  $files
     * @return \Illuminate\Support\Collection<int, UnitPhoto>
     */
    private function storeUnitPhotos(PropertyUnit $unit, array $files, User $user)
    {
        $created = collect();

        $existingCount = $unit->photos()->count();
        $isFirstPhoto  = $existingCount === 0;

        $files = array_slice($files, 0, max(0, self::PHOTO_MAX_COUNT - $existingCount));

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $extension  = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $basename   = Str::uuid()->toString();
            $directory  = self::PHOTO_DIR . '/' . $unit->id;
            $mainPath   = $directory . '/' . $basename . '.' . $extension;
            $thumbPath  = $directory . '/thumb_' . $basename . '.jpg';

            // Read bytes once; strip EXIF by re-encoding.
            $raw = file_get_contents($file->getRealPath());

            $this->writeResizedImage($raw, self::PHOTO_DISK, $mainPath, self::PHOTO_MAIN_WIDTH, $extension);
            $this->writeResizedImage($raw, self::PHOTO_DISK, $thumbPath, self::PHOTO_THUMB_WIDTH, 'jpg');

            $photo = UnitPhoto::create([
                'property_unit_id' => $unit->id,
                'uploaded_by'      => $user->id,
                'disk'             => self::PHOTO_DISK,
                'path'             => $mainPath,
                'thumbnail_path'   => $thumbPath,
                'mime'             => $file->getMimeType(),
                'size_bytes'       => $file->getSize(),
                'is_cover'         => $isFirstPhoto && $existingCount === 0 && $created->isEmpty(),
                'sort_order'       => $existingCount + $created->count(),
            ]);

            $created->push($photo);
        }

        if ($created->isNotEmpty()) {
            $this->logActivity(
                $user->id,
                'unit_photos_uploaded',
                'Photos uploaded to unit',
                $unit->id,
                ['count' => $created->count()]
            );
        }

        return $created;
    }

    /**
     * Delete photo files and rows for a unit.
     * If $photoIds is null, deletes ALL photos for the unit (used by destroy()).
     */
    private function deleteUnitPhotoFiles(PropertyUnit $unit, ?array $photoIds = null, bool $deleteAll = false): void
    {
        $query = $unit->photos();

        if (!$deleteAll && is_array($photoIds)) {
            $query->whereIn('id', $photoIds);
        }

        $photos = $query->get();
        if ($photos->isEmpty()) {
            return;
        }

        $hadCover = $photos->contains(fn ($p) => (bool) $p->is_cover);

        foreach ($photos as $photo) {
            if ($photo->path && Storage::disk($photo->disk)->exists($photo->path)) {
                Storage::disk($photo->disk)->delete($photo->path);
            }
            if ($photo->thumbnail_path && Storage::disk($photo->disk)->exists($photo->thumbnail_path)) {
                Storage::disk($photo->disk)->delete($photo->thumbnail_path);
            }
            $photo->delete();
        }

        if ($hadCover) {
            $next = $unit->photos()->ordered()->first();
            if ($next) {
                $next->is_cover = true;
                $next->save();
            }
        }
    }

    /**
     * Resize and write an image using GD (no external dependency).
     * Strips EXIF as a side-effect of re-encoding.
     */
    private function writeResizedImage(string $raw, string $disk, string $path, int $maxWidth, string $extension): void
    {
        $image = @imagecreatefromstring($raw);
        if (!$image) {
            // Fallback: store raw bytes (defensive — should not happen for validated images).
            Storage::disk($disk)->put($path, $raw);
            return;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $ratio  = $width > 0 ? min(1, $maxWidth / $width) : 1;
        $newW   = max(1, (int) round($width * $ratio));
        $newH   = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newW, $newH);
        // Preserve transparency for PNG/WEBP.
        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);

        ob_start();
        switch ($extension) {
            case 'png':
                imagepng($resized, null, 8);
                break;
            case 'webp':
                imagewebp($resized, null, 82);
                break;
            default:
                imagejpeg($resized, null, 82);
        }
        $output = ob_get_clean();

        imagedestroy($resized);
        imagedestroy($image);

        Storage::disk($disk)->put($path, $output);
    }

    // ========== HELPER METHODS (unchanged) ==========

    private function familyLinkFlagsFor(?Property $property, ?User $user = null): array
    {
        $user = $user ?? auth()->user();

        $isOwner = $property && (int) $property->landlord_id === (int) $user->id;

        if ($isOwner || !$property) {
            return [
                'isOwner'              => $isOwner,
                'isLinkedFamilyMember' => false,
                'hasManageUnitsAccess' => false,
            ];
        }

        $link = PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->first();

        if (!$link) {
            return [
                'isOwner'              => false,
                'isLinkedFamilyMember' => false,
                'hasManageUnitsAccess' => false,
            ];
        }

        $perms = is_array($link->permissions) ? $link->permissions : [];

        return [
            'isOwner'              => false,
            'isLinkedFamilyMember' => true,
            'hasManageUnitsAccess' => in_array('manage_units', $perms, true),
        ];
    }

    private function familyLinkSetsFor(User $user): array
    {
        $approvedLinks = PropertyFamilyLink::query()
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->get(['id', 'property_id', 'permissions']);

        $linked = $approvedLinks->pluck('property_id')->unique()->values();

        $manage = $approvedLinks
            ->filter(function ($link) {
                $perms = is_array($link->permissions) ? $link->permissions : [];
                return in_array('manage_units', $perms, true);
            })
            ->pluck('property_id')
            ->unique()
            ->values();

        return [$linked, $manage];
    }

    private function validateUnitRequest(Request $request, PropertyUnit $unit = null): \Illuminate\Validation\Validator
    {
        $rules = [
            'property_id'      => 'required|exists:properties,id',
            'unit_number'      => 'required|string|max:50',
            'unit_name'        => 'nullable|string|max:255',
            'unit_type'        => 'required|string|in:' . implode(',', array_keys(PropertyUnit::getTypeOptions())),
            'description'      => 'nullable|string',
            'floor_area'       => 'nullable|numeric|min:0',
            'bedrooms'         => 'nullable|integer|min:0',
            'bathrooms'        => 'nullable|integer|min:0',
            'living_rooms'     => 'nullable|integer|min:0',
            'kitchens'         => 'nullable|integer|min:0',
            'monthly_rent'     => 'required|numeric|min:0.01',
            'security_deposit' => 'nullable|numeric|min:0',
            'is_furnished'     => 'boolean',
            'status'           => 'required|string|in:' . implode(',', array_keys(PropertyUnit::getStatusOptions())),
            'is_available'     => 'boolean',
            'available_from'   => 'nullable|date',
            'amenities'        => 'nullable|array',
            'amenities.*'      => 'string',
            'assign_tenant_id' => 'nullable|exists:users,id',
            'tenant_name'      => 'nullable|string|max:255',
            'tenant_email'     => 'nullable|email|max:255',
            'preferred_move_in'=> 'nullable|date',
            'preferred_rent'   => 'nullable|string|max:100',
        ];

        // ========== PHOTO: merge photo rules ==========
        $rules = array_merge($rules, $this->photoRules(false));

        if ($unit) {
            $rules['unit_number'] .= '|unique:property_units,unit_number,' . $unit->id . ',id,property_id,' . $request->property_id;
        } else {
            $rules['unit_number'] .= '|unique:property_units,unit_number,NULL,id,property_id,' . $request->property_id;
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            if ($request->filled('assign_tenant_id')) {
                $tenant = User::find($request->assign_tenant_id);
                if (!$tenant || !$tenant->isTenant()) {
                    $validator->errors()->add('assign_tenant_id', 'The selected tenant is not a valid tenant.');
                }
            }

            // ========== PHOTO: enforce combined cap on update ==========
            if ($request->hasFile('photos')) {
                $existing = 0;
                if ($request->route('id')) {
                    $existing = UnitPhoto::where('property_unit_id', $request->route('id'))->count();
                }
                if (($existing + count($request->file('photos'))) > self::PHOTO_MAX_COUNT) {
                    $validator->errors()->add(
                        'photos',
                        'A unit can have at most ' . self::PHOTO_MAX_COUNT . ' photos in total.'
                    );
                }
            }
        });

        return $validator;
    }

    private function getAvailableProperties()
    {
        $user = auth()->user();

        $query = Property::query();

        if (!($user->isAdmin() || $user->isSuperAdmin() || $user->isDeveloper())) {
            $query->accessibleBy($user);
        }

        $query->where(function ($q) {
            $q->where('status', 'active')
              ->orWhere(function ($sub) {
                  $sub->where('status', 'under_construction')
                      ->where(function ($details) {
                          $details->where(function ($t) {
                                  $t->whereNotNull('property_type_id')
                                    ->where('property_type_id', '!=', 0);
                              })
                              ->orWhereNotNull('construction_status')
                              ->orWhere('has_plans', true)
                              ->orWhereNotNull('construction_documents');
                      });
              });
        });

        $query->where(function ($q) {
            $q->where('status', '!=', 'vacant')
              ->whereNotNull('status')
              ->where('status', '!=', '');
        });

        return $query
            ->with(['landlord', 'propertyType'])
            ->orderBy('property_name')
            ->get();
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

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant_status')) {
            $query->where('tenant_status', $request->tenant_status);
        }
        if ($request->filled('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }
        if ($request->filled('is_available')) {
            $query->where('is_available', $request->is_available === 'true');
        }
        if ($request->filled('min_rent')) {
            $query->where('monthly_rent', '>=', $request->min_rent);
        }
        if ($request->filled('max_rent')) {
            $query->where('monthly_rent', '<=', $request->max_rent);
        }
        if (!$request->filled('include_archived')) {
            $query->where('is_archived', false);
        }
    }

    private function checkUnitAccessWithError(PropertyUnit $unit): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }

        if ($user->isLandlord()) {
            $flags = $this->familyLinkFlagsFor($unit->property, $user);

            if ($flags['isOwner'] || $flags['isLinkedFamilyMember']) {
                return;
            }

            abort(403, 'You can only view units in properties you own or are linked to.');
        }

        if ($user->isTenant()) {
            if ((int) $unit->tenant_id === (int) $user->id
                && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED) {
                return;
            }
            abort(403, 'You can only view your assigned unit.');
        }

        abort(403, 'Unauthorized access to property unit.');
    }

    private function calculateTotalRentCollected(PropertyUnit $unit): float
    {
        if ($unit->currentLease) {
            $monthsOccupied = Carbon::parse($unit->currentLease->start_date)->diffInMonths(now());
            return $monthsOccupied * $unit->current_rent_amount;
        }
        return 0;
    }

    private function getUnitActivities(PropertyUnit $unit): array
    {
        $cacheKey = 'unit_activities_' . $unit->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($unit) {
            $activities = [];

            if ($unit->creator) {
                $activities[] = [
                    'type'        => 'created',
                    'description' => 'Unit created',
                    'user'        => $unit->creator->name,
                    'date'        => $unit->created_at,
                    'icon'        => 'fas fa-plus-circle',
                    'color'       => 'success',
                ];
            }

            if ($unit->tenant_requested_at) {
                $activities[] = [
                    'type'        => 'tenant_requested',
                    'description' => 'Tenant assignment requested',
                    'user'        => $unit->requestedBy->name ?? 'Unknown',
                    'date'        => $unit->tenant_requested_at,
                    'icon'        => 'fas fa-user-plus',
                    'color'       => 'info',
                ];
            }

            if ($unit->tenant_approved_at) {
                $activities[] = [
                    'type'        => 'tenant_approved',
                    'description' => 'Tenant assignment ' . ($unit->tenant_status === 'approved' ? 'approved' : 'rejected'),
                    'user'        => $unit->approvedBy->name ?? 'Admin',
                    'date'        => $unit->tenant_approved_at,
                    'icon'        => $unit->tenant_status === 'approved' ? 'fas fa-check-circle' : 'fas fa-times-circle',
                    'color'       => $unit->tenant_status === 'approved' ? 'success' : 'danger',
                ];
            }

            usort($activities, fn ($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            return $activities;
        });
    }

    private function getChangedFields(array $oldData, array $newData): array
    {
        $changes = [];
        foreach ($oldData as $key => $value) {
            if (array_key_exists($key, $newData) && $oldData[$key] != $newData[$key]) {
                $changes[$key] = ['from' => $oldData[$key], 'to' => $newData[$key]];
            }
        }
        return $changes;
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
            Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }

    private function getAdminUsers()
    {
        if ($this->adminUsersCache !== null) {
            return $this->adminUsersCache;
        }

        $adminTypes = [
            defined('App\Models\User::TYPE_ADMIN')       ? User::TYPE_ADMIN       : 'admin',
            defined('App\Models\User::TYPE_SUPER_ADMIN') ? User::TYPE_SUPER_ADMIN : 'super_admin',
        ];

        return $this->adminUsersCache = User::whereIn('type', $adminTypes)->get();
    }

    private function buildTenantAssignmentPayload(PropertyUnit $unit, User $tenant, User $assignedBy): array
    {
        $tenantDetails = $unit->tenant_details
            ? json_decode($unit->tenant_details, true)
            : [];

        return [
            'unit_id'           => $unit->id,
            'unit_number'       => $unit->unit_number,
            'unit_name'         => $unit->unit_name ?? 'N/A',
            'property_id'       => $unit->property_id,
            'property_name'     => $unit->property->property_name ?? 'N/A',
            'property_address'  => $unit->property->address ?? 'N/A',
            'tenant_id'         => $tenant->id,
            'tenant_name'       => $tenant->name,
            'tenant_email'      => $tenant->email,
            'tenant_phone'      => $tenant->phone ?? 'N/A',
            'assigned_by_id'    => $assignedBy->id,
            'assigned_by_name'  => $assignedBy->name,
            'assigned_by_email' => $assignedBy->email,
            'assigned_at'       => now()->toDateTimeString(),
            'monthly_rent'      => $unit->monthly_rent,
            'security_deposit'  => $unit->security_deposit ?? 0,
            'unit_type'         => $unit->unit_type ?? 'N/A',
            'bedrooms'          => $unit->bedrooms ?? 0,
            'bathrooms'         => $unit->bathrooms ?? 0,
            'status'            => $unit->tenant_status,
            'move_in_date'      => $unit->tenant_move_in_date
                ? $unit->tenant_move_in_date->toDateString()
                : 'Not specified',
            'preferred_move_in' => $tenantDetails['preferred_move_in'] ?? null,
            'preferred_rent'    => $tenantDetails['preferred_rent'] ?? null,
            'requires_approval' => $unit->tenant_status === PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
            'approval_url'      => route('property-units.show', $unit->id),
            'action_url'        => route('property-units.show', $unit->id),
        ];
    }

    private function notifyTenantAssignment(PropertyUnit $unit, User $tenant, User $assignedBy): void
    {
        try {
            $notificationData = $this->buildTenantAssignmentPayload($unit, $tenant, $assignedBy);

            Log::info('Sending tenant assignment notification', [
                'unit_id'     => $unit->id,
                'tenant_id'   => $tenant->id,
                'assigned_by' => $assignedBy->id,
                'status'      => $unit->tenant_status,
            ]);

            $notificationClass    = '\App\Notifications\TenantAssignmentNotification';
            $hasNotificationClass = class_exists($notificationClass);

            foreach ($this->getAdminUsers() as $admin) {
                if ($hasNotificationClass) {
                    $admin->notify(new $notificationClass($notificationData));
                } elseif (method_exists($admin, 'notifications')) {
                    DB::table('notifications')->insert([
                        'id'              => (string) \Illuminate\Support\Str::uuid(),
                        'type'            => 'tenant_assignment',
                        'notifiable_type' => get_class($admin),
                        'notifiable_id'   => $admin->id,
                        'data'            => json_encode($notificationData),
                        'read_at'         => null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            $tenantData = array_merge($notificationData, ['for_tenant' => true]);
            if ($hasNotificationClass) {
                $tenant->notify(new $notificationClass($tenantData));
            } elseif (method_exists($tenant, 'notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'tenant_assignment',
                    'notifiable_type' => get_class($tenant),
                    'notifiable_id'   => $tenant->id,
                    'data'            => json_encode($tenantData),
                    'read_at'         => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Failed to send tenant assignment notification: ' . $e->getMessage(), [
                'unit_id'   => $unit->id ?? null,
                'tenant_id' => $tenant->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    private function notifyTenantAssignmentApproved(PropertyUnit $unit, User $tenant, User $approvedBy): void
    {
        try {
            $data                     = $this->buildTenantAssignmentPayload($unit, $tenant, $approvedBy);
            $data['approval_status']  = 'approved';
            $data['approved_by_name'] = $approvedBy->name;

            $notificationClass    = '\App\Notifications\TenantAssignmentNotification';
            $hasNotificationClass = class_exists($notificationClass);

            $landlord = $unit->property->landlord ?? null;
            if ($landlord) {
                if ($hasNotificationClass) {
                    $landlord->notify(new $notificationClass($data));
                } elseif (method_exists($landlord, 'notifications')) {
                    DB::table('notifications')->insert([
                        'id'              => (string) \Illuminate\Support\Str::uuid(),
                        'type'            => 'tenant_assignment_approved',
                        'notifiable_type' => get_class($landlord),
                        'notifiable_id'   => $landlord->id,
                        'data'            => json_encode($data),
                        'read_at'         => null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            if ($hasNotificationClass) {
                $tenant->notify(new $notificationClass(array_merge($data, ['for_tenant' => true])));
            } elseif (method_exists($tenant, 'notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'tenant_assignment_approved',
                    'notifiable_type' => get_class($tenant),
                    'notifiable_id'   => $tenant->id,
                    'data'            => json_encode(array_merge($data, ['for_tenant' => true])),
                    'read_at'         => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Failed to send tenant assignment approved notification: ' . $e->getMessage(), [
                'unit_id'   => $unit->id ?? null,
                'tenant_id' => $tenant->id ?? null,
            ]);
        }
    }

    private function clearUnitCaches(int $userId): void
    {
        Cache::forget(self::CACHE_STATS_KEY . $userId);
        Cache::forget('dashboard_stats_' . $userId);
        Cache::forget('tenant_unit_' . $userId);
    }

    private function clearAllUnitCaches(): void
    {
        $user = auth()->user();
        $this->clearUnitCaches($user->id);

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            foreach ($this->getAdminUsers() as $admin) {
                $this->clearUnitCaches($admin->id);
            }
        }
    }

    private function invalidateUnitCachesAfterChange(Property $property, int $actorId): void
    {
        $this->clearUnitCaches($actorId);

        $ownerId = (int) $property->landlord_id;
        if ($ownerId && $ownerId !== $actorId) {
            $this->clearUnitCaches($ownerId);
        }

        foreach ($this->getAdminUsers() as $admin) {
            if ((int) $admin->id !== $actorId && (int) $admin->id !== $ownerId) {
                $this->clearUnitCaches($admin->id);
            }
        }

        Cache::forget('property_units_' . $property->id);
        Cache::forget('property_stats_' . $property->id);
        Cache::forget('all_property_units_count');
        Cache::forget('available_property_units_count');
    }
}