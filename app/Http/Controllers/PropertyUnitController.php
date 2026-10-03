<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\Property;
use App\Models\User;
use App\Models\RentalAgreement;
use App\Models\MaintenanceRequest;
use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Models\TenantInvitation;
use App\Notifications\TenantAssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyUnitController extends Controller
{
    private const CACHE_TTL = 3600;
    private const CACHE_STATS_KEY = 'property_unit_stats_';
    private const CACHE_UNITS_KEY = 'property_units_';

    // ========== INDEX/DASHBOARD ==========

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isLandlord()) {
            $query = PropertyUnit::with(['property', 'tenant', 'creator'])
                ->whereHas('property', function ($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                });

            $properties = Property::where('landlord_id', $user->id)
                ->orderBy('property_name')
                ->get();

            $baseQuery = PropertyUnit::whereHas('property', function ($q) use ($user) {
                $q->where('landlord_id', $user->id);
            })->withoutTrashed();

            $stats = [
                'total_units' => $baseQuery->count(),
                'available_units' => (clone $baseQuery)->where('status', 'available')->count(),
                'occupied_units' => (clone $baseQuery)->where('status', 'occupied')->count(),
                'pending_approval' => (clone $baseQuery)->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)->count(),
            ];

        } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
            $query = PropertyUnit::with(['property', 'tenant', 'creator']);
            $properties = Property::orderBy('property_name')->get();

            $baseQuery = PropertyUnit::withoutTrashed();

            $stats = [
                'total_units' => $baseQuery->count(),
                'available_units' => (clone $baseQuery)->where('status', 'available')->count(),
                'occupied_units' => (clone $baseQuery)->where('status', 'occupied')->count(),
                'pending_approval' => (clone $baseQuery)->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)->count(),
            ];

        } elseif ($user->isTenant()) {
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
                'isTenant' => true,
            ]);
        }

        $query->withoutTrashed();

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSortFields = ['unit_number', 'created_at', 'monthly_rent', 'status'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results
        $perPage = $request->input('per_page', 20);
        $units = $query->paginate($perPage);

        // Get status options for filter
        $statusOptions = PropertyUnit::getStatusOptions();
        $tenantStatusOptions = PropertyUnit::getTenantStatusOptions();
        $typeOptions = PropertyUnit::getTypeOptions();

        // Get properties with pending tenants for alert - FILTER OUT ALREADY ASSIGNED TENANTS
        if ($user->isLandlord() || $user->isAdmin() || $user->isSuperAdmin()) {
            $assignedTenantIds = PropertyUnit::whereNotNull('tenant_id')
                ->whereIn('tenant_status', [
                    PropertyUnit::TENANT_STATUS_APPROVED,
                    PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
                ])
                ->pluck('tenant_id')
                ->toArray();

            if (!empty($assignedTenantIds)) {
                $propertiesWithPendingTenants = Property::whereHas('tenants', function ($q) use ($assignedTenantIds) {
                        $q->where('users.type', User::TYPE_TENANT)
                          ->whereNotIn('users.id', $assignedTenantIds);
                    })
                    ->when($user->isLandlord(), function ($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    })
                    ->with(['tenants' => function ($q) use ($assignedTenantIds) {
                        $q->where('users.type', User::TYPE_TENANT)
                          ->whereNotIn('users.id', $assignedTenantIds);
                    }])
                    ->get();
            } else {
                $propertiesWithPendingTenants = Property::whereHas('tenants', function ($q) {
                        $q->where('users.type', User::TYPE_TENANT);
                    })
                    ->when($user->isLandlord(), function ($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    })
                    ->with(['tenants' => function ($q) {
                        $q->where('users.type', User::TYPE_TENANT);
                    }])
                    ->get();
            }

            $propertiesWithPendingTenants = $propertiesWithPendingTenants->filter(function ($property) {
                return $property->tenants->isNotEmpty();
            })->values();

        } else {
            $propertiesWithPendingTenants = collect();
        }

        return view('property_units.index', [
            'units' => $units,
            'properties' => $properties ?? collect(),
            'stats' => $stats ?? [],
            'statusOptions' => $statusOptions,
            'tenantStatusOptions' => $tenantStatusOptions,
            'typeOptions' => $typeOptions,
            'propertiesWithPendingTenants' => $propertiesWithPendingTenants,
            'isTenant' => false,
        ]);
    }

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
                'rentalAgreements' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                },
                'maintenanceRequests' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                }
            ])->findOrFail($id);

            $this->checkUnitAccessWithError($unit);

            $statsCacheKey = 'unit_stats_' . $id;
            $stats = Cache::remember($statsCacheKey, self::CACHE_TTL, function () use ($unit) {
                return [
                    'total_rental_agreements' => $unit->rentalAgreements()->count(),
                    'active_maintenance_requests' => $unit->maintenanceRequests()
                        ->whereIn('status', ['pending', 'in_progress'])
                        ->count(),
                    'total_rent_collected' => $this->calculateTotalRentCollected($unit),
                    'outstanding_invoices' => $unit->invoices()
                        ->whereIn('status', ['pending', 'overdue'])
                        ->sum('amount'),
                ];
            });

            $activities = $this->getUnitActivities($unit);
            $amenityOptions = $this->getAmenityOptions();

            $userRole = $user->type;
            $isOwner = $user->isLandlord() && $unit->property->landlord_id === $user->id;
            $isAssignedTenant = $user->isTenant() && $unit->tenant_id === $user->id && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED;

            $isOccupied = ($unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED &&
                          $unit->status === PropertyUnit::STATUS_OCCUPIED) ||
                          $unit->status === PropertyUnit::STATUS_OCCUPIED;

            $canEdit = $user->isLandlord() && $isOwner && !$isOccupied;

            $displayStatus = $isOccupied ? PropertyUnit::STATUS_OCCUPIED : $unit->status;

            return view('property_units.show', compact(
                'unit',
                'stats',
                'activities',
                'userRole',
                'isOwner',
                'isAssignedTenant',
                'canEdit',
                'amenityOptions',
                'displayStatus'
            ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('property-units.index')
                ->with('error', 'Property unit not found or has been removed.');
        }
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        if (!$user->isLandlord()) {
            return redirect()->route('dashboard')->with('error', 'Only landlords can create property units.');
        }

        $properties = $this->getAvailableProperties();

        if ($properties->isEmpty()) {
            return redirect()->route('properties.index')
                ->with('error', 'You need to create a property first before adding units.');
        }

        $selectedPropertyId = $request->input('property_id');
        $statusOptions = PropertyUnit::getStatusOptions();
        $typeOptions = PropertyUnit::getTypeOptions();
        $amenityOptions = $this->getAmenityOptions();

        return view('property_units.create', compact(
            'properties',
            'selectedPropertyId',
            'statusOptions',
            'typeOptions',
            'amenityOptions'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isLandlord()) {
            return redirect()->back()
                ->with('error', 'Only landlords can create property units.')
                ->withInput();
        }

        $validator = $this->validateUnitRequest($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $property = Property::find($request->property_id);
        if ($property->landlord_id !== $user->id) {
            return redirect()->back()
                ->with('error', 'You can only add units to your own properties.')
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $unit = new PropertyUnit();

            // Save basic unit data
            $unit->property_id = $request->property_id;
            $unit->unit_number = $request->unit_number;
            $unit->unit_name = $request->unit_name;
            $unit->unit_type = $request->unit_type;
            $unit->description = $request->description;
            $unit->floor_area = $request->floor_area;
            $unit->bedrooms = $request->bedrooms;
            $unit->bathrooms = $request->bathrooms;
            $unit->living_rooms = $request->living_rooms;
            $unit->kitchens = $request->kitchens;
            $unit->monthly_rent = $request->monthly_rent;
            $unit->security_deposit = $request->security_deposit;
            $unit->is_furnished = $request->boolean('is_furnished');
            $unit->status = $request->status;
            $unit->is_available = $request->boolean('is_available');
            $unit->available_from = $request->available_from;
            $unit->amenities = $request->amenities;
            $unit->created_by = $user->id;

            $tenantWasAssigned = false;
            $tenant = null;

            // Handle tenant assignment if tenant ID is provided
            if ($request->has('assign_tenant_id') && !empty($request->assign_tenant_id)) {
                $tenant = User::find($request->assign_tenant_id);

                if ($tenant && $tenant->isTenant()) {
                    // Check if tenant is already assigned to another unit
                    $existingAssignment = PropertyUnit::where('tenant_id', $tenant->id)
                        ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
                        ->exists();

                    if ($existingAssignment) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', 'This tenant is already assigned to another unit.')
                            ->withInput();
                    }

                    // Assign tenant to this unit
                    $unit->tenant_id = $tenant->id;
                    $unit->tenant_status = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
                    $unit->tenant_type = PropertyUnit::TENANT_TYPE_EXISTING;
                    $unit->tenant_requested_by = $user->id;
                    $unit->tenant_requested_at = now();
                    $unit->tenant_move_in_date = $request->available_from;

                    $unit->proposed_rent = $unit->monthly_rent;

                    $unit->tenant_details = json_encode([
                        'name' => $tenant->name,
                        'email' => $tenant->email,
                        'phone' => $tenant->phone,
                        'assigned_at' => now()->toDateTimeString(),
                        'assigned_by' => $user->name,
                        'assigned_by_id' => $user->id,
                        'preferred_move_in' => $request->preferred_move_in ?? null,
                        'preferred_rent' => $request->preferred_rent ?? null,
                    ]);

                    // Auto-approve if landlord is creating the unit
                    if ($user->isLandlord()) {
                        $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->tenant_approved_by = $user->id;
                        $unit->tenant_approved_at = now();
                        $unit->tenant_approval_notes = 'Auto-approved by landlord during unit creation';

                        $unit->status = PropertyUnit::STATUS_OCCUPIED;
                    }

                    $tenantWasAssigned = true;

                    $this->logActivity(
                        $user->id,
                        'property_unit_created_with_tenant',
                        'Property unit created and tenant ' . ($user->isLandlord() ? 'assigned' : 'requested'),
                        null,
                        [
                            'unit_number' => $unit->unit_number,
                            'property_id' => $unit->property_id,
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'tenant_status' => $unit->tenant_status
                        ]
                    );
                } else {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'Invalid tenant selected for assignment.')
                        ->withInput();
                }
            } else {
                // No tenant assignment
                $unit->tenant_id = null;
                $unit->tenant_status = null;
                $unit->tenant_type = null;
                $unit->tenant_requested_by = null;
                $unit->tenant_requested_at = null;
                $unit->tenant_documents = null;
                $unit->proposed_rent = null;
                $unit->tenant_approval_notes = null;
                $unit->tenant_move_in_date = null;
                $unit->invitation_channels = null;
                $unit->tenant_details = null;
            }

            $unit->save();

            // ✅ FIX: Send notification via the unified helper (deduplicated DB writes)
            if ($tenantWasAssigned && $tenant) {
                $this->notifyTenantAssignment($unit, $tenant, $user);
            }

            // Clear caches
            $this->clearUnitCaches($user->id);

            $admins = $this->getAdminUsers();
            foreach ($admins as $admin) {
                $this->clearUnitCaches($admin->id);
            }

            Cache::forget('property_units_' . $property->id);
            Cache::forget('property_stats_' . $property->id);
            Cache::forget('all_property_units_count');
            Cache::forget('available_property_units_count');

            DB::commit();

            $successMessage = 'Property unit created successfully!';
            if ($tenantWasAssigned) {
                $tenantStatus = $user->isLandlord() ? 'approved and assigned' : 'pending approval';
                $successMessage = "Property unit created successfully! The tenant has been {$tenantStatus} to this unit.";
            } else {
                $successMessage .= ' The unit is now available for tenant assignment.';
            }

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating property unit: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['_token'])
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create property unit: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        if (!is_numeric($id)) {
            return redirect()->route('property-units.index')
                ->with('error', 'Invalid property unit ID.');
        }

        $unit = PropertyUnit::with(['property', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->route('property-units.show', $id)
                    ->with('error', 'Only the property owner can edit this unit.');
            }
        } else {
            abort(403, 'Only landlords can edit property units.');
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->route('property-units.show', $id)
                ->with('error', 'You cannot edit an occupied unit. Please vacate the tenant first.');
        }

        $properties = $this->getAvailableProperties();
        $statusOptions = PropertyUnit::getStatusOptions();
        $typeOptions = PropertyUnit::getTypeOptions();
        $amenityOptions = $this->getAmenityOptions();

        return view('property_units.edit', compact(
            'unit',
            'properties',
            'statusOptions',
            'typeOptions',
            'amenityOptions'
        ));
    }

    public function update(Request $request, $id)
    {
        $unit = PropertyUnit::findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()
                    ->with('error', 'Only the property owner can update this unit.')
                    ->withInput();
            }
        } else {
            abort(403, 'Only landlords can update property units.');
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->back()
                ->with('error', 'You cannot edit an occupied unit. Please vacate the tenant first.')
                ->withInput();
        }

        $validator = $this->validateUnitRequest($request, $unit);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $oldData = $unit->toArray();

            // ✅ NEW: Track whether tenant_id changes so we can notify admins afterward
            $previousTenantId = $unit->tenant_id;

            $unit->property_id = $request->property_id;
            $unit->unit_number = $request->unit_number;
            $unit->unit_name = $request->unit_name;
            $unit->unit_type = $request->unit_type;
            $unit->description = $request->description;
            $unit->floor_area = $request->floor_area;
            $unit->bedrooms = $request->bedrooms;
            $unit->bathrooms = $request->bathrooms;
            $unit->living_rooms = $request->living_rooms;
            $unit->kitchens = $request->kitchens;
            $unit->monthly_rent = $request->monthly_rent;
            $unit->security_deposit = $request->security_deposit;
            $unit->is_furnished = $request->boolean('is_furnished');
            $unit->status = $request->status;
            $unit->is_available = $request->boolean('is_available');
            $unit->available_from = $request->available_from;
            $unit->amenities = $request->amenities;

            // ✅ NEW: Handle tenant assignment on update
            $newlyAssignedTenant = null;
            if ($request->filled('assign_tenant_id') && $request->assign_tenant_id != $previousTenantId) {
                $tenant = User::find($request->assign_tenant_id);

                if ($tenant && $tenant->isTenant()) {
                    // Ensure tenant isn't already assigned elsewhere
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

                    $unit->tenant_id = $tenant->id;
                    $unit->tenant_status = PropertyUnit::TENANT_STATUS_PENDING_APPROVAL;
                    $unit->tenant_type = PropertyUnit::TENANT_TYPE_EXISTING;
                    $unit->tenant_requested_by = $user->id;
                    $unit->tenant_requested_at = now();
                    $unit->tenant_move_in_date = $request->available_from ?? now();

                    if (empty($unit->proposed_rent)) {
                        $unit->proposed_rent = $unit->monthly_rent;
                    }

                    $existingDetails = $unit->tenant_details ? json_decode($unit->tenant_details, true) : [];
                    $unit->tenant_details = json_encode(array_merge($existingDetails, [
                        'name' => $tenant->name,
                        'email' => $tenant->email,
                        'phone' => $tenant->phone,
                        'assigned_at' => now()->toDateTimeString(),
                        'assigned_by' => $user->name,
                        'assigned_by_id' => $user->id,
                    ]));

                    // Landlord assigning = auto-approve
                    if ($user->isLandlord()) {
                        $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
                        $unit->tenant_approved_by = $user->id;
                        $unit->tenant_approved_at = now();
                        $unit->tenant_approval_notes = 'Auto-approved by landlord during unit update';

                        $unit->status = PropertyUnit::STATUS_OCCUPIED;
                    }

                    $newlyAssignedTenant = $tenant;
                }
            }

            if ($request->status === PropertyUnit::STATUS_AVAILABLE && $unit->tenant_id) {
                $unit->tenant_id = null;
                $unit->tenant_status = null;
                $unit->current_rent_amount = $unit->monthly_rent;
            }

            $unit->save();

            // ✅ FIX: Notify admins when a tenant is newly assigned via update()
            if ($newlyAssignedTenant) {
                $this->notifyTenantAssignment($unit, $newlyAssignedTenant, $user);
            }

            $changes = $this->getChangedFields($oldData, $unit->toArray());
            if (!empty($changes)) {
                $this->logActivity(
                    $user->id,
                    'property_unit_updated',
                    'Property unit updated',
                    $unit->id,
                    ['changes' => $changes]
                );
            }

            $this->clearUnitCaches($user->id);
            Cache::forget('property_unit_' . $id . '_with_relations');
            Cache::forget('unit_stats_' . $id);

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Property unit updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating property unit: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update property unit: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $unit = PropertyUnit::findOrFail($id);
        $user = auth()->user();

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id !== $user->id) {
                return redirect()->back()->with('error', 'Only the property owner can delete this unit.');
            }
        } else {
            abort(403, 'Only landlords can delete property units.');
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return redirect()->back()
                ->with('error', 'Cannot delete an occupied unit. Please vacate the tenant first.');
        }

        DB::beginTransaction();
        try {
            $unitData = $unit->toArray();
            $unit->delete();

            $this->logActivity(
                $user->id,
                'property_unit_deleted',
                'Property unit deleted',
                $id,
                ['unit_data' => $unitData]
            );

            $this->clearAllUnitCaches();

            DB::commit();

            return redirect()->route('property-units.index')
                ->with('success', 'Property unit deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting property unit: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to delete property unit: ' . $e->getMessage());
        }
    }

    // ========== TRASH/RECYCLE BIN ==========

    public function trash(Request $request)
    {
        $user = auth()->user();

        if (!$user->isLandlord()) {
            return redirect()->route('dashboard')->with('error', 'Only landlords can access deleted units.');
        }

        $query = PropertyUnit::onlyTrashed()
            ->with(['property', 'tenant', 'creator'])
            ->whereHas('property', function ($q) use ($user) {
                $q->where('landlord_id', $user->id);
            });

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%")
                    ->orWhereHas('property', function ($q) use ($search) {
                        $q->where('property_name', 'like', "%{$search}%");
                    });
            });
        }

        $sortField = $request->input('sort', 'deleted_at');
        $sortDirection = $request->input('direction', 'desc');

        if (in_array($sortField, ['unit_number', 'deleted_at', 'monthly_rent', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->input('per_page', 20);
        $trashedUnits = $query->paginate($perPage);

        $properties = Property::where('landlord_id', $user->id)
            ->orderBy('property_name')
            ->get();

        $typeOptions = PropertyUnit::getTypeOptions();

        return view('property_units.trash', compact(
            'trashedUnits',
            'properties',
            'typeOptions',
            'request'
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

        if ($unit->property->landlord_id !== $user->id) {
            return redirect()->route('property-units.trash')
                ->with('error', 'You can only restore units from your own properties.');
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

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error restoring property unit from trash: ' . $e->getMessage());

            return redirect()->route('property-units.trash')
                ->with('error', 'Failed to restore property unit: ' . $e->getMessage());
        }
    }

    // ========== TENANT APPROVAL WORKFLOW (NEW) ==========

    /**
     * ✅ NEW: Admin approves a pending tenant assignment.
     */
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
            $unit->tenant_status = PropertyUnit::TENANT_STATUS_APPROVED;
            $unit->tenant_approved_by = $user->id;
            $unit->tenant_approved_at = now();
            $unit->tenant_approval_notes = $request->input('notes', 'Approved by admin');
            $unit->status = PropertyUnit::STATUS_OCCUPIED;
            $unit->save();

            $this->logActivity(
                $user->id,
                'tenant_assignment_approved',
                'Tenant assignment approved by admin',
                $unit->id,
                ['tenant_id' => $unit->tenant_id]
            );

            // ✅ Notify landlord + tenant of approval
            if ($unit->tenant) {
                $this->notifyTenantAssignmentApproved($unit, $unit->tenant, $user);
            }

            $this->clearAllUnitCaches();

            DB::commit();

            return redirect()->back()->with('success', 'Tenant assignment approved successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving tenant assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to approve tenant assignment.');
        }
    }

    /**
     * ✅ NEW: Admin rejects a pending tenant assignment.
     */
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

            $unit->tenant_status = PropertyUnit::TENANT_STATUS_REJECTED;
            $unit->tenant_approved_by = $user->id;
            $unit->tenant_approved_at = now();
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

        } catch (\Exception $e) {
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
            $query->whereHas('property', function ($q) use ($user) {
                $q->where('landlord_id', $user->id);
            });
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
                    ->orWhereHas('property', function ($q) use ($search) {
                        $q->where('property_name', 'like', "%{$search}%");
                    })
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

        if ($format === 'csv') {
            return $this->exportToCsv($units);
        } elseif ($format === 'pdf') {
            return $this->exportToPdf($units);
        } else {
            return $this->exportToCsv($units);
        }
    }

    private function exportToCsv($units)
    {
        $filename = 'property_units_export_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
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
                $tenantName = $unit->tenant ? $unit->tenant->name : 'N/A';
                $tenantEmail = $unit->tenant ? $unit->tenant->email : 'N/A';
                $tenantPhone = $unit->tenant ? $unit->tenant->phone : 'N/A';
                $tenantStatus = $unit->tenant_status ? ucfirst(str_replace('_', ' ', $unit->tenant_status)) : 'N/A';
                $moveInDate = $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : 'N/A';

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
                    $tenantName,
                    $tenantEmail,
                    $tenantPhone,
                    $tenantStatus,
                    $moveInDate,
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
            'units' => $units,
            'exported_at' => Carbon::now()->format('F j, Y H:i:s'),
            'total_units' => $units->count(),
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
            if (!isset($summary[$status])) {
                $summary[$status] = 0;
            }
            $summary[$status]++;
        }
        return $summary;
    }

    // ========== HELPER METHODS ==========

    private function validateUnitRequest(Request $request, PropertyUnit $unit = null): \Illuminate\Validation\Validator
    {
        $rules = [
            'property_id' => 'required|exists:properties,id',
            'unit_number' => 'required|string|max:50',
            'unit_name' => 'nullable|string|max:255',
            'unit_type' => 'required|string|in:' . implode(',', array_keys(PropertyUnit::getTypeOptions())),
            'description' => 'nullable|string',
            'floor_area' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'living_rooms' => 'nullable|integer|min:0',
            'kitchens' => 'nullable|integer|min:0',
            'monthly_rent' => 'required|numeric|min:0.01',
            'security_deposit' => 'nullable|numeric|min:0',
            'is_furnished' => 'boolean',
            'status' => 'required|string|in:' . implode(',', array_keys(PropertyUnit::getStatusOptions())),
            'is_available' => 'boolean',
            'available_from' => 'nullable|date',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'assign_tenant_id' => 'nullable|exists:users,id',
            'tenant_name' => 'nullable|string|max:255',
            'tenant_email' => 'nullable|email|max:255',
            'preferred_move_in' => 'nullable|date',
            'preferred_rent' => 'nullable|string|max:100',
        ];

        if ($unit) {
            $rules['unit_number'] .= '|unique:property_units,unit_number,' . $unit->id . ',id,property_id,' . $request->property_id;
        } else {
            $rules['unit_number'] .= '|unique:property_units,unit_number,NULL,id,property_id,' . $request->property_id;
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            if ($request->has('assign_tenant_id') && !empty($request->assign_tenant_id)) {
                $tenant = User::find($request->assign_tenant_id);
                if (!$tenant || !$tenant->isTenant()) {
                    $validator->errors()->add('assign_tenant_id', 'The selected tenant is not a valid tenant.');
                }
            }
        });

        return $validator;
    }

    private function getAvailableProperties()
    {
        $user = auth()->user();

        if ($user->isLandlord()) {
            return Property::where('landlord_id', $user->id)
                ->orderBy('property_name')
                ->get();
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return Property::orderBy('property_name')->get();
        }

        return collect();
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

        if ($user->isDeveloper() || $user->isFieldAgent() || $user->isSecurityCheckpoint()) {
            abort(403, 'You do not have access to property units.');
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
                    'type' => 'created',
                    'description' => 'Unit created',
                    'user' => $unit->creator->name,
                    'date' => $unit->created_at,
                    'icon' => 'fas fa-plus-circle',
                    'color' => 'success'
                ];
            }

            if ($unit->tenant_requested_at) {
                $activities[] = [
                    'type' => 'tenant_requested',
                    'description' => 'Tenant assignment requested',
                    'user' => $unit->requestedBy->name ?? 'Unknown',
                    'date' => $unit->tenant_requested_at,
                    'icon' => 'fas fa-user-plus',
                    'color' => 'info'
                ];
            }

            if ($unit->tenant_approved_at) {
                $activities[] = [
                    'type' => 'tenant_approved',
                    'description' => 'Tenant assignment ' . ($unit->tenant_status === 'approved' ? 'approved' : 'rejected'),
                    'user' => $unit->approvedBy->name ?? 'Admin',
                    'date' => $unit->tenant_approved_at,
                    'icon' => $unit->tenant_status === 'approved' ? 'fas fa-check-circle' : 'fas fa-times-circle',
                    'color' => $unit->tenant_status === 'approved' ? 'success' : 'danger'
                ];
            }

            usort($activities, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return $activities;
        });
    }

    private function getChangedFields(array $oldData, array $newData): array
    {
        $changes = [];
        foreach ($oldData as $key => $value) {
            if (array_key_exists($key, $newData) && $oldData[$key] != $newData[$key]) {
                $changes[$key] = [
                    'from' => $oldData[$key],
                    'to' => $newData[$key]
                ];
            }
        }
        return $changes;
    }

    private function logActivity(int $userId, string $type, string $description, ?int $unitId = null, array $metadata = []): void
    {
        try {
            \App\Models\ActivityLog::create([
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

    /**
     * ✅ NEW: Get all admin + super admin users.
     */
    private function getAdminUsers()
    {
        $adminTypes = [];
        if (defined('App\Models\User::TYPE_ADMIN')) {
            $adminTypes[] = User::TYPE_ADMIN;
        } else {
            $adminTypes[] = 'admin';
        }
        if (defined('App\Models\User::TYPE_SUPER_ADMIN')) {
            $adminTypes[] = User::TYPE_SUPER_ADMIN;
        } else {
            $adminTypes[] = 'super_admin';
        }

        return User::whereIn('type', $adminTypes)->get();
    }

    /**
     * ✅ NEW: Build the notification payload once, reuse everywhere.
     */
    private function buildTenantAssignmentPayload(PropertyUnit $unit, User $tenant, User $assignedBy): array
    {
        $tenantDetails = $unit->tenant_details ? json_decode($unit->tenant_details, true) : [];

        return [
            'unit_id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'unit_name' => $unit->unit_name ?? 'N/A',
            'property_id' => $unit->property_id,
            'property_name' => $unit->property->property_name ?? 'N/A',
            'property_address' => $unit->property->address ?? 'N/A',
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'tenant_phone' => $tenant->phone ?? 'N/A',
            'assigned_by_id' => $assignedBy->id,
            'assigned_by_name' => $assignedBy->name,
            'assigned_by_email' => $assignedBy->email,
            'assigned_at' => now()->toDateTimeString(),
            'monthly_rent' => $unit->monthly_rent,
            'security_deposit' => $unit->security_deposit ?? 0,
            'unit_type' => $unit->unit_type ?? 'N/A',
            'bedrooms' => $unit->bedrooms ?? 0,
            'bathrooms' => $unit->bathrooms ?? 0,
            'status' => $unit->tenant_status,
            'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->toDateString() : 'Not specified',
            'preferred_move_in' => $tenantDetails['preferred_move_in'] ?? null,
            'preferred_rent' => $tenantDetails['preferred_rent'] ?? null,
            'requires_approval' => $unit->tenant_status === PropertyUnit::TENANT_STATUS_PENDING_APPROVAL,
            'approval_url' => route('property-units.show', $unit->id),
            'action_url' => route('property-units.show', $unit->id),
        ];
    }

    /**
     * ✅ FIX: Unified notification method.
     * - Sends Laravel notification to admins + tenant (via TenantAssignmentNotification if available).
     * - Falls back to a SINGLE DB write per recipient (no duplicates).
     * - Uses the `database` channel via notify() OR direct insert — never both.
     */
    private function notifyTenantAssignment(PropertyUnit $unit, User $tenant, User $assignedBy): void
    {
        try {
            $notificationData = $this->buildTenantAssignmentPayload($unit, $tenant, $assignedBy);

            Log::info('Sending tenant assignment notification', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'assigned_by' => $assignedBy->id,
                'status' => $unit->tenant_status,
            ]);

            $admins = $this->getAdminUsers();
            $notificationClass = '\App\Notifications\TenantAssignmentNotification';
            $hasNotificationClass = class_exists($notificationClass);

            // --- Notify admins ---
            foreach ($admins as $admin) {
                if ($hasNotificationClass) {
                    $admin->notify(new $notificationClass($notificationData));
                } elseif (method_exists($admin, 'notifications')) {
                    // Fallback: write exactly one DB row
                    DB::table('notifications')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'type' => 'tenant_assignment',
                        'notifiable_type' => get_class($admin),
                        'notifiable_id' => $admin->id,
                        'data' => json_encode($notificationData),
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // --- Notify tenant ---
            $tenantData = array_merge($notificationData, ['for_tenant' => true]);
            if ($hasNotificationClass) {
                $tenant->notify(new $notificationClass($tenantData));
            } elseif (method_exists($tenant, 'notifications')) {
                DB::table('notifications')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'tenant_assignment',
                    'notifiable_type' => get_class($tenant),
                    'notifiable_id' => $tenant->id,
                    'data' => json_encode($tenantData),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Tenant assignment notification sent successfully', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send tenant assignment notification: ' . $e->getMessage(), [
                'unit_id' => $unit->id ?? null,
                'tenant_id' => $tenant->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    /**
     * ✅ NEW: Backwards-compatible alias for older callers.
     */
    private function sendTenantAssignmentNotification(PropertyUnit $unit, User $tenant, User $assignedBy): void
    {
        $this->notifyTenantAssignment($unit, $tenant, $assignedBy);
    }

    /**
     * ✅ NEW: Notify landlord + tenant when an admin approves the assignment.
     */
    private function notifyTenantAssignmentApproved(PropertyUnit $unit, User $tenant, User $approvedBy): void
    {
        try {
            $data = $this->buildTenantAssignmentPayload($unit, $tenant, $approvedBy);
            $data['approval_status'] = 'approved';
            $data['approved_by_name'] = $approvedBy->name;

            $notificationClass = '\App\Notifications\TenantAssignmentNotification';
            $hasNotificationClass = class_exists($notificationClass);

            // Notify landlord
            $landlord = $unit->property->landlord ?? null;
            if ($landlord) {
                if ($hasNotificationClass) {
                    $landlord->notify(new $notificationClass($data));
                } elseif (method_exists($landlord, 'notifications')) {
                    DB::table('notifications')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'type' => 'tenant_assignment_approved',
                        'notifiable_type' => get_class($landlord),
                        'notifiable_id' => $landlord->id,
                        'data' => json_encode($data),
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Notify tenant
            if ($hasNotificationClass) {
                $tenant->notify(new $notificationClass(array_merge($data, ['for_tenant' => true])));
            } elseif (method_exists($tenant, 'notifications')) {
                DB::table('notifications')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'tenant_assignment_approved',
                    'notifiable_type' => get_class($tenant),
                    'notifiable_id' => $tenant->id,
                    'data' => json_encode(array_merge($data, ['for_tenant' => true])),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send tenant assignment approved notification: ' . $e->getMessage(), [
                'unit_id' => $unit->id ?? null,
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
            $admins = $this->getAdminUsers();
            foreach ($admins as $admin) {
                $this->clearUnitCaches($admin->id);
            }
        }
    }
}