<?php
// app/Http/Controllers/Sanitation/CollectionZoneController.php

namespace App\Http\Controllers\Sanitation;

use App\Http\Controllers\Controller;
use App\Models\CollectionZone;
use App\Models\SanitationPersonnel;
use App\Models\User;
use App\Models\Property;
use App\Models\WasteCollectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;   // ✅ NEW: needed for Rule::exists()

class CollectionZoneController extends Controller
{
    /**
     * Fields the `sort` query param is allowed to use.
     * Prevents arbitrary column names from reaching the SQL layer.
     */
    private const ALLOWED_SORT_FIELDS = [
        'name', 'code', 'is_active', 'priority',
        'region', 'district', 'created_at', 'updated_at',
    ];

    /**
     * Statuses considered "active work in progress" for a request.
     */
    private const ACTIVE_REQUEST_STATUSES = [
        'pending', 'assigned', 'en_route', 'arrived', 'in_progress',
    ];

    // ================================================================ //
    // 📋 CRUD                                                         //
    // ================================================================ //

public function index(Request $request)
{
    $query = CollectionZone::with(['assignedPersonnel', 'creator'])
        ->withCount([
            'properties',
            'properties as linked_properties_count' => function ($q) {
                $q->whereHas('wasteCollectionRequests');
            },
            'wasteCollectionRequests',
            'wasteCollectionRequests as active_requests_count' => function ($q) {
                $q->whereIn('status', self::ACTIVE_REQUEST_STATUSES);
            },
        ]);

    // Search
    if ($request->filled('search')) {
        $search = trim($request->search);
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Status — whitelisted
    if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
        $query->where('is_active', $request->status === 'active');
    }

    // Assigned personnel — column existence cached
    if ($request->filled('assigned_personnel_id')) {
        $hasColumn = Cache::remember(
            'collection_zones.has_assigned_personnel_col',
            now()->addMinutes(5),
            fn () => Schema::hasColumn('collection_zones', 'assigned_personnel_id')
        );

        if ($hasColumn) {
            $query->where('assigned_personnel_id', $request->assigned_personnel_id);
        }
    }

    // Sort — whitelisted
    $sortField = $request->get('sort', 'name');
    if (!in_array($sortField, self::ALLOWED_SORT_FIELDS, true)) {
        $sortField = 'name';
    }
    $sortOrder = strtolower($request->get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
    $query->orderBy($sortField, $sortOrder);

    // Paginate — clamp per_page to a sane range
    $perPage = (int) $request->get('per_page', 20);
    if ($perPage < 5 || $perPage > 100) {
        $perPage = 20;
    }
    $zones = $query->paginate($perPage);

    $stats     = $this->getZoneStats();
    $personnel = SanitationPersonnel::active()->with('user')->get();
    $statuses  = ['active', 'inactive'];

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'data'    => $zones,
            'stats'   => $stats,
            'filters' => [
                'statuses'  => $statuses,
                'personnel' => $personnel,
            ],
        ]);
    }

    return view('sanitation.zones.index', compact('zones', 'stats', 'statuses', 'personnel'));
}

    /**
     * Show the form for creating a new collection zone.
     */
    public function create()
    {
        $personnel = SanitationPersonnel::active()->with('user')->get();

        return view('sanitation.zones.create', compact('personnel'));
    }

    /**
     * Store a newly created collection zone.
     */
    public function store(Request $request)
    {
        $validator = $this->validateZone($request);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Generate code if not provided
            $code = $request->code ?? $this->generateZoneCode($request->name);

            // Prepare data - ONLY use columns that exist
            $zoneData = [
                'name' => $request->name,
                'code' => $code,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => auth()->id(),
                'metadata' => [
                    'created_by_name' => auth()->user()->name,
                    'created_at' => now()->toISOString(),
                    'ip_address' => $request->ip(),
                ],
            ];

            // Handle coordinates
            if ($request->has('coordinates') && Schema::hasColumn('collection_zones', 'coordinates')) {
                $zoneData['coordinates'] = $request->coordinates ? json_decode($request->coordinates, true) : null;
            }

            // Handle boundaries
            if ($request->has('boundaries') && Schema::hasColumn('collection_zones', 'boundaries')) {
                $zoneData['boundaries'] = $request->boundaries ? json_decode($request->boundaries, true) : null;
            }

            // Only add assigned_personnel_id if column exists
            if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
                $zoneData['assigned_personnel_id'] = $request->assigned_personnel_id;
            }

            // Handle additional fields only if they exist
            $additionalFields = ['region', 'district', 'latitude', 'longitude', 'boundary_coordinates', 'priority', 'notes'];
            foreach ($additionalFields as $field) {
                if ($request->has($field) && Schema::hasColumn('collection_zones', $field)) {
                    if (in_array($field, ['boundary_coordinates', 'coordinates'])) {
                        $zoneData[$field] = $request->$field ? json_decode($request->$field, true) : null;
                    } else {
                        $zoneData[$field] = $request->$field;
                    }
                }
            }

            $zone = CollectionZone::create($zoneData);

            Log::info('Collection zone created', [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            $message = 'Collection zone created successfully!';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $zone->load(['assignedPersonnel', 'creator']),
                ], 201);
            }

            return redirect()->route('sanitation.zones.show', $zone)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create collection zone: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create collection zone: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create collection zone: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified collection zone.
     *
     * Loads the zone with eager-loaded counts + relations, then paginates
     * the properties and requests tied to it. All stats are computed from
     * the preloaded count attributes so no additional queries run in the view.
     */
    public function show(CollectionZone $zone, Request $request)
    {
        // -----------------------------------------------------------------
        // 1. Eager-load relations + count subqueries
        // -----------------------------------------------------------------
        $zone->load(['assignedPersonnel.user', 'creator'])
            ->loadCount([
                'properties',
                'properties as linked_properties_count' => function ($q) {
                    $q->whereHas('wasteCollectionRequests');
                },
                'properties as active_properties_count' => function ($q) {
                    $q->where('status', 'active');
                },
                'wasteCollectionRequests',
                'wasteCollectionRequests as pending_requests_count' => function ($q) {
                    $q->where('status', 'pending');
                },
                'wasteCollectionRequests as completed_requests_count' => function ($q) {
                    $q->where('status', 'completed');
                },
                'wasteCollectionRequests as active_requests_count' => function ($q) {
                    $q->whereIn('status', self::ACTIVE_REQUEST_STATUSES);
                },
            ]);

        // -----------------------------------------------------------------
        // 2. Paginated properties in this zone
        // -----------------------------------------------------------------
        $properties = $zone->properties()
            ->with(['landlord', 'propertyType'])
            ->orderBy('property_name')
            ->paginate(10);

        // -----------------------------------------------------------------
        // 3. Paginated waste collection requests in this zone
        // -----------------------------------------------------------------
        $requests = $zone->wasteCollectionRequests()
            ->with(['property', 'assignedTo', 'worker'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // -----------------------------------------------------------------
        // 4. Personnel available for assignment (for the dropdown)
        //    Excludes personnel already assigned to another zone.
        // -----------------------------------------------------------------
        $personnelQuery = SanitationPersonnel::active()->with('user');

        if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            $assignedIds = CollectionZone::whereNotNull('assigned_personnel_id')
                ->where('id', '!=', $zone->id)   // keep the current zone's personnel selectable
                ->pluck('assigned_personnel_id')
                ->toArray();

            if (!empty($assignedIds)) {
                $personnelQuery->whereNotIn('id', $assignedIds);
            }
        }

        $personnel = $personnelQuery->orderBy('first_name')->get();

        // -----------------------------------------------------------------
        // 5. Stats — all values come from the preloaded count attributes,
        //    so no extra queries run.
        // -----------------------------------------------------------------
        $stats = [
            'total_properties'    => (int) ($zone->properties_count ?? 0),
            'linked_properties'   => (int) ($zone->linked_properties_count ?? 0),
            'active_properties'   => (int) ($zone->active_properties_count ?? 0),
            'total_requests'      => (int) ($zone->waste_collection_requests_count ?? 0),
            'pending_requests'    => (int) ($zone->pending_requests_count ?? 0),
            'completed_requests'  => (int) ($zone->completed_requests_count ?? 0),
            'active_requests'     => (int) ($zone->active_requests_count ?? 0),
            'assigned_personnel'  => $zone->assignedPersonnel?->full_name ?? 'Unassigned',
            'is_active'           => (bool) $zone->is_active,
        ];

        // -----------------------------------------------------------------
        // 6. JSON response for API / AJAX
        // -----------------------------------------------------------------
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'zone'       => $zone,
                    'properties' => $properties,
                    'requests'   => $requests,
                    'personnel'  => $personnel->map(fn ($p) => [
                        'id'          => $p->id,
                        'name'        => $p->full_name,
                        'role'        => $p->role,
                        'employee_id' => $p->employee_id,
                        'phone'       => $p->phone,
                        'status'      => $p->status,
                    ])->values(),
                    'stats'      => $stats,
                ],
            ]);
        }

        // -----------------------------------------------------------------
        // 7. HTML response
        // -----------------------------------------------------------------
        return view('sanitation.zones.show', compact(
            'zone',
            'properties',
            'requests',
            'personnel',
            'stats'
        ));
    }

    /**
     * Show the form for editing the specified collection zone.
     */
    public function edit(CollectionZone $zone)
    {
        $personnel = SanitationPersonnel::active()->with('user')->get();

        return view('sanitation.zones.edit', compact('zone', 'personnel'));
    }

    /**
     * Update the specified collection zone.
     */
    public function update(Request $request, CollectionZone $zone)
    {
        $validator = $this->validateZone($request, $zone->id);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Prepare update data - ONLY use columns that exist
            $zoneData = [
                'name' => $request->name,
                'code' => $request->code ?? $zone->code,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', $zone->is_active),
            ];

            // Handle coordinates
            if ($request->has('coordinates') && Schema::hasColumn('collection_zones', 'coordinates')) {
                $zoneData['coordinates'] = $request->coordinates ? json_decode($request->coordinates, true) : null;
            }

            // Handle boundaries
            if ($request->has('boundaries') && Schema::hasColumn('collection_zones', 'boundaries')) {
                $zoneData['boundaries'] = $request->boundaries ? json_decode($request->boundaries, true) : null;
            }

            // ✅ FIX: assigned_personnel_id — enforce uniqueness just like assignPersonnel()
            if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
                $newPersonnelId = $request->assigned_personnel_id;

                if ($newPersonnelId) {
                    $conflict = CollectionZone::where('assigned_personnel_id', $newPersonnelId)
                        ->where('id', '!=', $zone->id)
                        ->first();

                    if ($conflict) {
                        DB::rollBack();

                        $msg = "This personnel is already assigned to zone: {$conflict->name}";

                        if ($request->expectsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => $msg,
                            ], 422);
                        }
                        return redirect()->back()
                            ->with('error', $msg)
                            ->withInput();
                    }
                }

                $zoneData['assigned_personnel_id'] = $newPersonnelId;
            }

            // Handle additional fields only if they exist
            $additionalFields = ['region', 'district', 'latitude', 'longitude', 'boundary_coordinates', 'priority', 'notes'];
            foreach ($additionalFields as $field) {
                if ($request->has($field) && Schema::hasColumn('collection_zones', $field)) {
                    if (in_array($field, ['boundary_coordinates', 'coordinates'])) {
                        $zoneData[$field] = $request->$field ? json_decode($request->$field, true) : null;
                    } else {
                        $zoneData[$field] = $request->$field;
                    }
                }
            }

            // Update metadata
            $metadata = $zone->metadata ?? [];
            $metadata['updated_by'] = auth()->id();
            $metadata['updated_by_name'] = auth()->user()->name;
            $metadata['updated_at'] = now()->toISOString();
            $zoneData['metadata'] = $metadata;

            $zone->update($zoneData);

            Log::info('Collection zone updated', [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Collection zone updated successfully!',
                    'data' => $zone->load(['assignedPersonnel', 'creator']),
                ]);
            }

            return redirect()->route('sanitation.zones.show', $zone)
                ->with('success', 'Collection zone updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update collection zone: ' . $e->getMessage(), [
                'zone_id' => $zone->id,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update collection zone: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update collection zone: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
 * Remove the specified collection zone.
 *
 * ✅ Guards (default behaviour):
 *    - Blocks if the zone has active properties
 *    - Blocks if the zone has active waste collection requests
 *
 * ✅ Force override (`force=1` + admin/super-admin only):
 *    - Detaches all properties from the zone (sets collection_zone_id = null)
 *    - Detaches all waste collection requests from the zone
 *    - Then deletes the zone
 */
public function destroy(CollectionZone $zone, Request $request)
{
    // ================================================================
    // 1. Resolve the force flag and check authorization for it
    // ================================================================
    $force        = $request->boolean('force');
    $currentUser  = $request->user();

    $canForce = $currentUser
        && ($currentUser->isSuperAdmin() || $currentUser->isAdmin());

    // If force was requested but the user isn't allowed to use it,
    // ignore the flag rather than error — the guards will still run.
    if ($force && !$canForce) {
        $force = false;
    }

    try {
        DB::beginTransaction();

        // ================================================================
        // 2. Guard: active properties
        // ================================================================
        $activeProperties = $zone->properties()
            ->where('status', 'active')
            ->count();

        if ($activeProperties > 0 && !$force) {
            DB::rollBack();

            $message = "Cannot delete zone with {$activeProperties} active property(s). "
                . ($canForce ? 'Use force delete to detach them first.' : 'Deactivate or reassign them first.');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'can_force' => $canForce,
                    'active_properties' => $activeProperties,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        // ================================================================
        // 3. Guard: active requests
        // ================================================================
        $activeRequests = $zone->wasteCollectionRequests()
            ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
            ->count();

        if ($activeRequests > 0 && !$force) {
            DB::rollBack();

            $message = "Cannot delete zone with {$activeRequests} active request(s). "
                . ($canForce ? 'Use force delete to detach them first.' : 'Complete or cancel them first.');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'can_force' => $canForce,
                    'active_requests' => $activeRequests,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        // ================================================================
        // 4. Force mode — detach child records before deleting
        // ================================================================
        $detached = [
            'properties' => 0,
            'requests'   => 0,
        ];

        if ($force) {
            // Detach every property in this zone (active or not)
            $detached['properties'] = $zone->properties()
                ->update(['collection_zone_id' => null]);

            // Detach every request routed through this zone
            $detached['requests'] = $zone->wasteCollectionRequests()
                ->update(['collection_zone_id' => null]);

            Log::warning('Force-deleting collection zone — child records detached', [
                'zone_id'              => $zone->id,
                'zone_name'            => $zone->name,
                'detached_properties'  => $detached['properties'],
                'detached_requests'    => $detached['requests'],
                'force_deleted_by'     => $currentUser->id,
                'force_deleted_by_name' => $currentUser->name,
                'active_properties_at_force' => $activeProperties,
                'active_requests_at_force'   => $activeRequests,
            ]);
        }

        // ================================================================
        // 5. Delete the zone
        // ================================================================
        $zoneName = $zone->name;
        $zoneId   = $zone->id;

        $zone->delete();

        Log::info('Collection zone deleted', [
            'zone_id'     => $zoneId,
            'zone_name'   => $zoneName,
            'deleted_by'  => $currentUser->id,
            'force'       => $force,
            'detached'    => $force ? $detached : null,
        ]);

        DB::commit();

        // ================================================================
        // 6. Response
        // ================================================================
        $message = $force
            ? "Zone \"{$zoneName}\" force-deleted. "
                . "Detached {$detached['properties']} propert"
                . ($detached['properties'] === 1 ? 'y' : 'ies')
                . " and {$detached['requests']} request(s)."
            : "Collection zone \"{$zoneName}\" deleted successfully!";

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => $message,
                'force'    => $force,
                'detached' => $force ? $detached : null,
            ]);
        }

        return redirect()->route('sanitation.zones.index')
            ->with('success', $message);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to delete collection zone: ' . $e->getMessage(), [
            'zone_id' => $zone->id,
            'force'   => $force,
            'trace'   => $e->getTraceAsString(),
        ]);

        $message = 'Failed to delete collection zone: ' . $e->getMessage();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }

        return redirect()->back()->with('error', $message);
    }
}

    // ================================================================ //
    // 🔄 STATUS & ASSIGNMENT                                          //
    // ================================================================ //

    /**
     * Toggle zone active status.
     */
    public function toggleActive(CollectionZone $zone, Request $request)
    {
        try {
            $zone->is_active = !$zone->is_active;
            $zone->save();

            $status = $zone->is_active ? 'activated' : 'deactivated';

            Log::info('Collection zone ' . $status, [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'action_by' => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Collection zone {$status} successfully!",
                    'is_active' => $zone->is_active,
                ]);
            }

            return redirect()->back()
                ->with('success', "Collection zone {$status} successfully!");

        } catch (\Exception $e) {
            Log::error('Failed to toggle zone status: ' . $e->getMessage(), [
                'zone_id' => $zone->id,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to toggle zone status: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to toggle zone status: ' . $e->getMessage());
        }
    }

    /**
     * Assign personnel to a zone.
     * ✅ FIX: dynamic table lookup + active-only enforcement.
     */
    public function assignPersonnel(Request $request, CollectionZone $zone)
    {
        // Check if column exists
        if (!Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned_personnel_id column does not exist. Please run the migration.',
                ], 500);
            }
            return redirect()->back()
                ->with('error', 'The assigned_personnel_id column does not exist. Please run the migration.');
        }

        // ✅ Resolve real table + PK from the model (defends against naming drift)
        $personnelTable = (new SanitationPersonnel)->getTable();
        $personnelKey   = (new SanitationPersonnel)->getKeyName();

        $validator = Validator::make($request->all(), [
            'personnel_id' => [
                'required',
                'integer',
                Rule::exists($personnelTable, $personnelKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],
        ], [
            'personnel_id.required' => 'Please select a personnel to assign.',
            'personnel_id.exists'   => 'The selected personnel does not exist or is not active.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $personnel = SanitationPersonnel::findOrFail($request->personnel_id);

            // Check if personnel is already assigned to another zone
            $existingZone = CollectionZone::where('assigned_personnel_id', $request->personnel_id)
                ->where('id', '!=', $zone->id)
                ->first();

            if ($existingZone) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "This personnel is already assigned to zone: {$existingZone->name}",
                    ], 422);
                }
                return redirect()->back()
                    ->with('error', "This personnel is already assigned to zone: {$existingZone->name}");
            }

            $zone->assigned_personnel_id = $request->personnel_id;
            $zone->save();

            Log::info('Personnel assigned to zone', [
                'zone_id' => $zone->id,
                'personnel_id' => $request->personnel_id,
                'assigned_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Personnel assigned to zone successfully!',
                    'data' => $zone->load(['assignedPersonnel']),
                ]);
            }

            return redirect()->route('sanitation.zones.show', $zone)
                ->with('success', 'Personnel assigned to zone successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign personnel to zone: ' . $e->getMessage(), [
                'zone_id' => $zone->id,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign personnel: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to assign personnel: ' . $e->getMessage());
        }
    }

    /**
     * Remove assigned personnel from a zone.
     */
    public function unassignPersonnel(CollectionZone $zone, Request $request)
    {
        // Check if column exists
        if (!Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned_personnel_id column does not exist.',
                ], 500);
            }
            return redirect()->back()
                ->with('error', 'The assigned_personnel_id column does not exist.');
        }

        try {
            DB::beginTransaction();

            $zone->assigned_personnel_id = null;
            $zone->save();

            Log::info('Personnel unassigned from zone', [
                'zone_id' => $zone->id,
                'unassigned_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Personnel unassigned from zone successfully!',
                ]);
            }

            return redirect()->route('sanitation.zones.show', $zone)
                ->with('success', 'Personnel unassigned from zone successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unassign personnel from zone: ' . $e->getMessage(), [
                'zone_id' => $zone->id,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unassign personnel: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to unassign personnel: ' . $e->getMessage());
        }
    }

    // ================================================================ //
    // 🔍 AJAX / DROPDOWNS                                             //
    // ================================================================ //

    /**
     * Get available personnel for assignment (AJAX).
     */
    public function getAvailablePersonnel(Request $request)
    {
        $query = SanitationPersonnel::active()->with('user');

        // Only exclude assigned personnel if column exists
        if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            $assignedIds = CollectionZone::whereNotNull('assigned_personnel_id')
                ->pluck('assigned_personnel_id')
                ->toArray();

            if (!empty($assignedIds)) {
                $query->whereNotIn('id', $assignedIds);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $personnel = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'personnel' => $personnel->map(function ($person) {
                return [
                    'id' => $person->id,
                    'name' => $person->full_name,
                    'role' => $person->role,
                    'employee_id' => $person->employee_id,
                    'phone' => $person->phone,
                    'status' => $person->status,
                ];
            }),
        ]);
    }

    /**
     * Get zones for dropdown (AJAX).
     */
    public function getZonesForDropdown(Request $request)
    {
        $query = CollectionZone::where('is_active', true)->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $zones = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'zones' => $zones->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'code' => $zone->code,
                    'assigned_personnel' => $zone->assignedPersonnel?->full_name,
                ];
            }),
        ]);
    }

    // ================================================================ //
    // 📤 EXPORT                                                       //
    // ================================================================ //

    /**
     * Export zones to CSV.
     * Uses withCount to avoid N+1 during export.
     */
    public function export(Request $request)
    {
        $zones = CollectionZone::with(['assignedPersonnel', 'creator'])
            ->withCount([
                'properties',
                'properties as linked_properties_count' => function ($q) {
                    $q->whereHas('wasteCollectionRequests');
                },
                'wasteCollectionRequests',
            ])
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'active') {
                    $q->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->orderBy('name')
            ->get();

        $filename = 'collection_zones_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($zones) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'ID',
                'Name',
                'Code',
                'Description',
                'Status',
                'Assigned Personnel',
                'Total Properties',
                'Linked Properties',
                'Requests Count',
                'Created By',
                'Created At',
                'Updated At',
            ]);

            foreach ($zones as $zone) {
                fputcsv($file, [
                    $zone->id,
                    $zone->name,
                    $zone->code,
                    $zone->description,
                    $zone->is_active ? 'Active' : 'Inactive',
                    $zone->assignedPersonnel?->full_name ?? 'Unassigned',
                    $zone->properties_count,
                    $zone->linked_properties_count,
                    $zone->waste_collection_requests_count,
                    $zone->creator?->name ?? 'System',
                    $zone->created_at->format('Y-m-d H:i:s'),
                    $zone->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ================================================================ //
    // 🔧 PRIVATE HELPERS                                              //
    // ================================================================ //

    /**
     * Get zone statistics.
     */
    private function getZoneStats(): array
    {
        $stats = [
            'total' => CollectionZone::count(),
            'active' => CollectionZone::where('is_active', true)->count(),
            'inactive' => CollectionZone::where('is_active', false)->count(),
            'with_assigned' => 0,
            'without_assigned' => 0,
            'total_properties' => Property::count(),
            'linked_properties' => Property::whereNotNull('collection_zone_id')->count(),
            'total_requests' => WasteCollectionRequest::count(),
            'active_requests' => WasteCollectionRequest::whereIn('status', self::ACTIVE_REQUEST_STATUSES)->count(),
        ];

        // Check if assigned_personnel_id column exists
        if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            $stats['with_assigned'] = CollectionZone::whereNotNull('assigned_personnel_id')->count();
            $stats['without_assigned'] = CollectionZone::whereNull('assigned_personnel_id')->count();
        }

        return $stats;
    }

    /**
     * Validate zone data.
     * ✅ FIX: dynamic table name + custom messages.
     */
    private function validateZone(Request $request, $zoneId = null)
    {
        // ✅ Resolve real table + PK from the model
        $personnelTable = (new SanitationPersonnel)->getTable();
        $personnelKey   = (new SanitationPersonnel)->getKeyName();

        $rules = [
            'name'        => 'required|string|max:255|unique:collection_zones,name' . ($zoneId ? ',' . $zoneId : ''),
            'code'        => 'nullable|string|max:50|unique:collection_zones,code' . ($zoneId ? ',' . $zoneId : ''),
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
            'coordinates' => 'nullable|json',
            'boundaries'  => 'nullable|json',

            // ✅ FIXED: correct table name, resolved dynamically
            'assigned_personnel_id' => [
                'nullable',
                'integer',
                Rule::exists($personnelTable, $personnelKey),
            ],

            'region'               => 'nullable|string|max:100',
            'district'             => 'nullable|string|max:100',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'boundary_coordinates' => 'nullable|json',
            'priority'             => 'nullable|integer|min:1|max:100',
            'notes'                => 'nullable|string|max:1000',
        ];

        $messages = [
            'name.required'                => 'Zone name is required.',
            'name.unique'                  => 'A zone with this name already exists.',
            'code.unique'                  => 'A zone with this code already exists.',
            'assigned_personnel_id.exists' => 'The selected supervisor does not exist.',
            'latitude.between'             => 'Latitude must be between -90 and 90.',
            'longitude.between'            => 'Longitude must be between -180 and 180.',
            'priority.min'                 => 'Priority must be at least 1.',
            'priority.max'                 => 'Priority must not exceed 100.',
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Generate a zone code.
     */
    private function generateZoneCode($name)
    {
        $code = strtoupper(Str::slug($name, ''));
        $code = substr($code, 0, 10);

        $suffix = 1;
        $originalCode = $code;
        while (CollectionZone::where('code', $code)->exists()) {
            $code = $originalCode . $suffix;
            $suffix++;
        }

        return $code;
    }
}