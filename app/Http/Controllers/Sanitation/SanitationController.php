<?php
// app/Http/Controllers/Sanitation/SanitationController.php

namespace App\Http\Controllers\Sanitation;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SanitationPersonnel;
use App\Models\SanitationWorker;
use App\Models\WasteCollectionRequest;
use App\Models\CollectionZone;
use App\Models\User;
use App\Models\SanitationServiceRequest;
use App\Services\SanitationService;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SanitationController extends Controller
{
    protected $sanitationService;

    public function __construct(SanitationService $sanitationService)
    {
        $this->sanitationService = $sanitationService;
    }

    // ================================================================ //
    // 🔐 LINKING AUTHORIZATION                                        //
    // ================================================================ //

    /**
     * ✅ FIXED: Allow linking if the current user is either:
     *   (a) An admin / super admin, OR
     *   (b) A sanitation personnel who is a SUPERVISOR (root or sub) AND
     *       whose creator is an admin OR another sanitation supervisor.
     *
     * This permits:
     *   - Admin → root supervisor → links ✅
     *   - Admin → root supervisor → sub supervisor → links ✅
     *   - Supervisor → sub supervisor → links ✅
     *   - Supervisor → worker/driver → does NOT link ❌
     *   - Worker/driver (any creator) → does NOT link ❌
     */
    private function canCurrentUserLinkProperties(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Admins always retain linking rights.
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $personnel = $user->sanitationPersonnel;

        if (!$personnel) {
            return false;
        }

        // Must be a supervisor — root OR sub. Workers/drivers are excluded.
        if (!method_exists($personnel, 'isSupervisor') || !$personnel->isSupervisor()) {
            return false;
        }

        // Resolve creator from metadata (works with array or JSON-string casts).
        $createdById = $this->resolveMetadataValue($personnel, 'created_by');

        if (!$createdById) {
            return false;
        }

        $creator = User::find($createdById);

        if (!$creator) {
            return false;
        }

        // ✅ Creator may be an admin…
        if ($creator->isAdmin() || $creator->isSuperAdmin()) {
            return true;
        }

        // ✅ …OR another sanitation supervisor.
        $creatorPersonnel = $creator->sanitationPersonnel;

        return $creatorPersonnel
            && method_exists($creatorPersonnel, 'isSupervisor')
            && $creatorPersonnel->isSupervisor();
    }

    /**
     * ✅ NEW: Defensive metadata reader. Handles both array-cast and
     *         raw-JSON-string metadata columns.
     */
    private function resolveMetadataValue($model, string $key)
    {
        if (!$model || !isset($model->metadata)) {
            return null;
        }

        $metadata = $model->metadata;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        if (!is_array($metadata)) {
            return null;
        }

        return $metadata[$key] ?? null;
    }

    /**
     * ✅ FIXED: Abort 403 unless the current user may link properties.
     *          Handles both JSON and non-JSON requests gracefully.
     */
    private function authorizePropertyLinking(Request $request): void
    {
        if ($this->canCurrentUserLinkProperties()) {
            return;
        }

        $message = 'Only sanitation supervisors created by an administrator or another supervisor can manage property linking.';

        if ($request->expectsJson() || $request->ajax()) {
            abort(response()->json([
                'success' => false,
                'message' => $message,
            ], 403));
        }

        abort(403, $message);
    }

    // ================================================================ //
    // 📊 DASHBOARD                                                    //
    // ================================================================ //

    /**
     * Show the sanitation dashboard.
     */
    public function dashboard(Request $request)
    {
        $user      = auth()->user();
        $personnel = $user->sanitationPersonnel;

        // ---------------------------------------------------------
        // 1. System-wide stats
        // ---------------------------------------------------------
        $systemStats = [
            'total_properties'     => Property::count(),
            'linked_properties'    => Property::whereHas('wasteCollectionRequests')->count(),
            'available_properties' => Property::whereDoesntHave('wasteCollectionRequests')
                ->where('status', 'active')
                ->count(),
            'total_requests'       => WasteCollectionRequest::count(),
            'pending_requests'     => WasteCollectionRequest::where('status', 'pending')->count(),
            'active_requests'      => WasteCollectionRequest::whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])->count(),
            'completed_today'      => WasteCollectionRequest::whereDate('created_at', today())
                ->where('status', 'completed')
                ->count(),
            'total_weight_today'   => WasteCollectionRequest::whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('waste_weight_kg'),
            'total_personnel'      => SanitationPersonnel::count(),
            'active_personnel'     => SanitationPersonnel::where('status', 'active')->count(),
            'total_workers'        => SanitationWorker::count(),
            'active_workers'       => SanitationWorker::where('status', 'active')->count(),
            'pending_approvals'    => WasteCollectionRequest::where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)->count(),
            'approved_today'       => WasteCollectionRequest::whereDate('approval_responded_at', today())
                ->where('approval_status', WasteCollectionRequest::APPROVAL_APPROVED)
                ->count(),
            'expired_approvals'    => WasteCollectionRequest::where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)
                ->where('approval_expires_at', '<', now())
                ->count(),
        ];

        // ---------------------------------------------------------
        // 2. Personnel-scoped stats
        // ---------------------------------------------------------
        $personnelStats        = [];
        $personnelRequests     = collect();
        $personnelPerformance  = [];
        $activeJobs            = 0;
        $completedToday        = 0;
        $pendingRequests       = 0;
        $pendingApprovals      = 0;

        if ($personnel) {
            $personnelStats = [
                'total_requests'      => $personnel->assignedRequests()->count(),
                'completed'           => $personnel->assignedRequests()->where('status', 'completed')->count(),
                'active'              => $personnel->activeRequests()->count(),
                'pending'             => $personnel->assignedRequests()->where('status', 'pending')->count(),
                'today'               => $personnel->assignedRequests()->whereDate('created_at', today())->count(),
                'completed_today'     => $personnel->assignedRequests()
                    ->where('status', 'completed')
                    ->whereDate('completed_at', today())
                    ->count(),
                'total_weight'        => $personnel->assignedRequests()
                    ->where('status', 'completed')
                    ->sum('waste_weight_kg'),
                'avg_completion_time' => $personnel->assignedRequests()
                    ->where('status', 'completed')
                    ->avg('completion_time'),
                'completion_rate'     => $this->calculateCompletionRate($personnel),
                'workers_count'       => $personnel->workers()->count(),
                'active_workers'      => $personnel->workers()->where('status', 'active')->count(),
                'pending_approvals'   => $personnel->assignedRequests()
                    ->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)
                    ->count(),
            ];

            $activeJobs       = $personnelStats['active'] ?? 0;
            $completedToday   = $personnelStats['completed_today'] ?? 0;
            $pendingRequests  = $personnelStats['pending'] ?? 0;
            $pendingApprovals = $personnelStats['pending_approvals'] ?? 0;

            $personnelPerformance = $this->calculatePerformanceMetrics($personnel);

            $personnelRequests = $personnel->assignedRequests()
                ->with(['property', 'requestedBy'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // ---------------------------------------------------------
        // 3. Shared request + trend data
        // ---------------------------------------------------------
        $recentRequests = WasteCollectionRequest::with(['property', 'assignedTo', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $weeklyTrends  = $this->getWeeklyTrends();
        $mapData       = $this->getMapData();
        $topPerformers = $this->getTopPerformers();

        $quickStats = [
            'active_jobs'       => $activeJobs,
            'completed_today'   => $completedToday,
            'pending_requests'  => $pendingRequests,
            'pending_approvals' => $pendingApprovals,
        ];

        // =========================================================
        // 4. ROLE-SPECIFIC DATA
        // =========================================================
        $role         = $personnel?->role;
        $isDriver     = $role === 'driver';
        $isSupervisor = $personnel && $personnel->isSupervisor();
        $isWorker     = $role === 'worker';

        // ✅ Expose linking-rights flag to the dashboard view too.
        $canLinkProperties = $this->canCurrentUserLinkProperties();

        $driverRoute   = null;
        $driverVehicle = null;
        $teamOverview  = null;

        // ---------- Driver: route planner ----------
        if ($isDriver) {
            try {
                $driverRoute   = $this->buildDriverRoute($personnel);
                $driverVehicle = [
                    'number' => $personnel->vehicle_number,
                    'type'   => $personnel->vehicle_type,
                    'zone'   => $personnel->assigned_zone,
                ];
            } catch (\Throwable $e) {
                Log::error('Driver route planner failed', [
                    'driver_id' => $personnel->id,
                    'error'     => $e->getMessage(),
                ]);

                $driverRoute = [
                    'stops'        => collect(),
                    'stop_count'   => 0,
                    'total_weight' => 0.0,
                    'zone'         => $personnel->assigned_zone,
                    'map_center'   => ['lat' => null, 'lng' => null],
                    'generated_at' => now()->toISOString(),
                ];
                $driverVehicle = [
                    'number' => $personnel->vehicle_number,
                    'type'   => $personnel->vehicle_type,
                    'zone'   => $personnel->assigned_zone,
                ];
            }
        }

        // ---------- Supervisor: team overview ----------
        if ($isSupervisor) {
            $teamOverview = $this->buildTeamOverview($personnel);
        }

        // ---------------------------------------------------------
        // 5. JSON response
        // ---------------------------------------------------------
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'role'                   => $role,
                    'can_link_properties'    => $canLinkProperties,
                    'system_stats'           => $systemStats,
                    'personnel_stats'        => $personnelStats,
                    'personnel_performance'  => $personnelPerformance,
                    'recent_requests'        => $recentRequests,
                    'weekly_trends'          => $weeklyTrends,
                    'map_data'               => $mapData,
                    'top_performers'         => $topPerformers,
                    'quick_stats'            => $quickStats,
                    'driver_route'           => $this->serializeDriverRoute($driverRoute),
                    'driver_vehicle'         => $driverVehicle,
                    'team_overview'          => $teamOverview,
                ],
            ]);
        }

        // ---------------------------------------------------------
        // 6. Blade view
        // ---------------------------------------------------------
        return view('sanitation.dashboard', compact(
            'systemStats',
            'personnelStats',
            'personnelPerformance',
            'personnelRequests',
            'recentRequests',
            'weeklyTrends',
            'mapData',
            'topPerformers',
            'quickStats',
            'personnel',
            'role',
            'isDriver',
            'isSupervisor',
            'isWorker',
            'driverRoute',
            'driverVehicle',
            'teamOverview',
            'canLinkProperties'
        ));
    }

    // ================================================================ //
    // 🚚 DRIVER ROUTE PLANNER HELPERS                                 //
    // ================================================================ //

    private function buildDriverRoute(SanitationPersonnel $driver): array
    {
        $query = WasteCollectionRequest::query()
            ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ])
            ->with([
                'property:id,property_name,digital_address,latitude,longitude,city,zone,landlord_id',
                'property.landlord:id,name,phone',
            ]);

        $query->where(function ($q) use ($driver) {
            $q->where('assigned_to', $driver->id);

            if (!empty($driver->assigned_zone)) {
                $q->orWhereHas('property', function ($pq) use ($driver) {
                    $pq->where('zone', $driver->assigned_zone);
                });
            }
        });

        $stops = $query->get();

        $stops = $stops->filter(function ($stop) {
            [$lat, $lng] = $this->resolveStopCoordinates($stop);
            return $lat !== null && $lng !== null;
        })->values();

        $priorityRank = [
            'emergency' => 0,
            'high'      => 1,
            'medium'    => 2,
            'low'       => 3,
        ];

        $stops = $stops->sortBy(function ($stop) use ($priorityRank) {
            return $priorityRank[$stop->priority] ?? 4;
        })->values();

        if ($driver->latitude && $driver->longitude) {
            $stops = $this->orderByProximity(
                $stops,
                (float) $driver->latitude,
                (float) $driver->longitude
            );
        }

        $totalWeight = $stops->sum(function ($stop) {
            return (float) ($stop->waste_weight_kg ?? 0);
        });

        $mapCenter = $this->computeMapCenter($stops, $driver);

        return [
            'stops'        => $stops,
            'stop_count'   => $stops->count(),
            'total_weight' => round($totalWeight, 2),
            'zone'         => $driver->assigned_zone,
            'map_center'   => $mapCenter,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function resolveStopCoordinates($stop): array
    {
        $lat = $stop->latitude  ?? $stop->property->latitude  ?? null;
        $lng = $stop->longitude ?? $stop->property->longitude ?? null;

        return [
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ];
    }

    private function orderByProximity($stops, float $startLat, float $startLng): \Illuminate\Support\Collection
    {
        $remaining  = $stops->values()->all();
        $ordered    = [];
        $currentLat = $startLat;
        $currentLng = $startLng;

        while (!empty($remaining)) {
            $nearestIdx  = 0;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $i => $stop) {
                [$lat, $lng] = $this->resolveStopCoordinates($stop);
                if ($lat === null || $lng === null) {
                    continue;
                }

                $d = $this->haversineKm($currentLat, $currentLng, $lat, $lng);
                if ($d < $nearestDist) {
                    $nearestDist = $d;
                    $nearestIdx  = $i;
                }
            }

            $next = $remaining[$nearestIdx];
            $ordered[] = $next;

            [$nextLat, $nextLng] = $this->resolveStopCoordinates($next);
            if ($nextLat !== null && $nextLng !== null) {
                $currentLat = $nextLat;
                $currentLng = $nextLng;
            }

            array_splice($remaining, $nearestIdx, 1);
        }

        return collect($ordered);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function computeMapCenter($stops, SanitationPersonnel $driver): array
    {
        $latitudes  = collect();
        $longitudes = collect();

        if ($driver->latitude !== null)  $latitudes->push((float) $driver->latitude);
        if ($driver->longitude !== null) $longitudes->push((float) $driver->longitude);

        foreach ($stops as $stop) {
            [$lat, $lng] = $this->resolveStopCoordinates($stop);
            if ($lat !== null) $latitudes->push($lat);
            if ($lng !== null) $longitudes->push($lng);
        }

        $lats = $latitudes->filter(fn ($v) => $v !== null)->values();
        $lngs = $longitudes->filter(fn ($v) => $v !== null)->values();

        if ($lats->isEmpty() || $lngs->isEmpty()) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => round($lats->avg(), 6),
            'lng' => round($lngs->avg(), 6),
        ];
    }

    private function serializeDriverRoute(?array $route): ?array
    {
        if ($route === null) {
            return null;
        }

        $stops = collect($route['stops'] ?? [])->map(function ($stop) {
            [$lat, $lng] = $this->resolveStopCoordinates($stop);

            return [
                'id'               => $stop->id,
                'property_id'      => $stop->property_id,
                'property_name'    => $stop->property->property_name ?? null,
                'digital_address'  => $stop->property->digital_address ?? null,
                'city'             => $stop->property->city ?? null,
                'latitude'         => $lat,
                'longitude'        => $lng,
                'priority'         => $stop->priority,
                'status'           => $stop->status,
                'waste_type'       => $stop->waste_type,
                'waste_weight_kg'  => $stop->waste_weight_kg,
                'landlord_name'    => $stop->property->landlord->name ?? null,
                'landlord_phone'   => $stop->property->landlord->phone ?? null,
            ];
        })->values()->all();

        return [
            'stops'        => $stops,
            'stop_count'   => $route['stop_count'] ?? count($stops),
            'total_weight' => $route['total_weight'] ?? 0,
            'zone'         => $route['zone'] ?? null,
            'map_center'   => $route['map_center'] ?? ['lat' => null, 'lng' => null],
            'generated_at' => $route['generated_at'] ?? null,
        ];
    }

    // ================================================================ //
    // 🧑‍💼 SUPERVISOR TEAM OVERVIEW                                     //
    // ================================================================ //

    private function buildTeamOverview(SanitationPersonnel $supervisor): array
    {
        $isRoot = is_null($supervisor->supervisor_id);

        if ($isRoot) {
            $teamIds = $this->collectDescendantIds($supervisor);
        } else {
            $teamIds = $supervisor->subordinates()->pluck('id');
        }

        $team = SanitationPersonnel::with('user:id,name')
            ->whereIn('id', $teamIds)
            ->get();

        $workers = $team->where('role', 'worker')->values();
        $drivers = $team->where('role', 'driver')->values();

        $activeJobs = WasteCollectionRequest::whereIn('assigned_to', $teamIds)
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ])
            ->count();

        $pendingApprovals = WasteCollectionRequest::whereIn('assigned_to', $teamIds)
            ->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)
            ->count();

        return [
            'is_root'           => $isRoot,
            'subordinate_count' => $teamIds->count(),
            'workers'           => $workers,
            'drivers'           => $drivers,
            'active_jobs'       => $activeJobs,
            'pending_approvals' => $pendingApprovals,
        ];
    }

    // ================================================================ //
    // 📋 PROPERTY MANAGEMENT                                          //
    // ================================================================ //

    public function availableProperties(Request $request)
    {
        $user = auth()->user();

        if (config('app.debug')) {
            Log::debug('Available Properties Query Started', [
                'user_id' => $user->id,
                'filters' => $request->all(),
            ]);
        }

        $query = Property::with(['landlord', 'propertyType']);
        $query->withCount('wasteCollectionRequests');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('property_name', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%")
                  ->orWhere('street_name', 'like', "%{$search}%")
                  ->orWhereHas('landlord', function ($lq) use ($search) {
                      $lq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        if ($request->filled('landlord_id')) {
            $query->where('landlord_id', $request->landlord_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('collection_status')) {
            if ($request->collection_status === 'linked') {
                $query->whereHas('wasteCollectionRequests');
            } elseif ($request->collection_status === 'available') {
                $query->whereDoesntHave('wasteCollectionRequests');
            } elseif ($request->collection_status === 'pending_approval') {
                $query->whereHas('wasteCollectionRequests', function ($q) {
                    $q->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING);
                });
            }
        }

        if (config('app.debug')) {
            Log::debug('Available Properties Query - Final count', [
                'count'    => $query->count(),
                'sql'      => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);
        }

        $properties = $query->orderBy('property_name')->paginate(20);

        $zones = Property::whereNotNull('zone')->distinct()->pluck('zone');

        $landlords = User::where(function ($q) {
            $q->where('type', User::TYPE_LANDLORD)
              ->orWhereHas('roles', function ($roleQ) {
                  $roleQ->where('slug', 'landlord');
              });
        })->where('status', User::STATUS_ACTIVE)->get();

        $debugInfo = [
            'total_properties'            => Property::count(),
            'active_properties'           => Property::where('status', 'active')->count(),
            'properties_with_requests'    => Property::whereHas('wasteCollectionRequests')->count(),
            'properties_without_requests' => Property::whereDoesntHave('wasteCollectionRequests')->count(),
            'properties_pending_approval' => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING);
            })->count(),
        ];

        // ✅ Pass linking-rights flag to the Blade.
        $canLinkProperties = $this->canCurrentUserLinkProperties();

        if ($request->expectsJson()) {
            return response()->json([
                'success'             => true,
                'properties'          => $properties,
                'zones'               => $zones,
                'landlords'           => $landlords,
                'debug'               => $debugInfo,
                'can_link_properties' => $canLinkProperties,
            ]);
        }

        return view('sanitation.properties.available', compact(
            'properties',
            'zones',
            'landlords',
            'debugInfo',
            'canLinkProperties'
        ));
    }

    public function linkPropertyForm(Property $property)
    {
        $this->authorizePropertyLinking(request());

        $existingRequest = $property->wasteCollectionRequests()
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_PENDING,
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ])
            ->first();

        if ($existingRequest) {
            return redirect()->route('sanitation.properties.show', $property)
                ->with('info', 'This property already has a waste collection request. Status: ' . $existingRequest->approval_status_label);
        }

        $zones     = CollectionZone::active()->get();
        $personnel = SanitationPersonnel::available()->get();
        $workers   = SanitationWorker::active()->get();

        return view('sanitation.properties.link', compact('property', 'zones', 'personnel', 'workers'));
    }

    public function linkProperty(Request $request, Property $property)
    {
        $this->authorizePropertyLinking($request);

        $personnelTable = (new SanitationPersonnel)->getTable();
        $personnelKey   = (new SanitationPersonnel)->getKeyName();

        $workerTable = (new SanitationWorker)->getTable();
        $workerKey   = (new SanitationWorker)->getKeyName();

        $validator = Validator::make($request->all(), [
            'collection_zone_id'   => 'nullable|exists:collection_zones,id',
            'collection_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'collection_days'      => 'nullable|array',
            'collection_days.*'    => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'preferred_time'       => 'nullable|string',
            'waste_types'          => 'nullable|array',
            'waste_types.*'        => 'in:general,recyclable,organic,hazardous,bulk',

            'assigned_personnel_id' => [
                'nullable',
                'integer',
                Rule::exists($personnelTable, $personnelKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'assigned_worker_id' => [
                'nullable',
                'integer',
                Rule::exists($workerTable, $workerKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'special_instructions' => 'nullable|string|max:1000',
            'emergency_contact'    => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:1000',
            'declaration'          => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $existingRequest = $property->wasteCollectionRequests()
                ->whereIn('approval_status', [
                    WasteCollectionRequest::APPROVAL_PENDING,
                    WasteCollectionRequest::APPROVAL_APPROVED,
                    WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
                ])
                ->first();

            if ($existingRequest) {
                throw new \Exception('This property already has a pending or approved waste collection request.');
            }

            $landlord = $property->landlord;
            if (!$landlord) {
                throw new \Exception('This property does not have a landlord assigned.');
            }

            $landlord->ensureLandlordRole(auth()->id(), 'Waste collection approval');

            $approvalToken = Str::random(64);

            $hasZoneFk = Schema::hasColumn('waste_collection_requests', 'collection_zone_id');

            $createPayload = [
                'property_id'         => $property->id,
                'requested_by'        => auth()->id(),
                'waste_type'          => $request->waste_types[0] ?? 'general',
                'priority'            => 'medium',
                'status'              => 'pending',
                'approval_status'     => WasteCollectionRequest::APPROVAL_PENDING,
                'digital_address'     => $property->digital_address,
                'latitude'            => $property->latitude,
                'longitude'           => $property->longitude,
                'approval_expires_at' => now()->addDays(WasteCollectionRequest::APPROVAL_EXPIRY_DAYS),
                'metadata' => [
                    'collection_linked'          => true,
                    'collection_zone_id'         => $request->collection_zone_id,
                    'collection_frequency'       => $request->collection_frequency,
                    'collection_days'            => $request->collection_days,
                    'preferred_time'             => $request->preferred_time,
                    'waste_types'                => $request->waste_types,
                    'assigned_personnel_id'      => $request->assigned_personnel_id,
                    'assigned_worker_id'         => $request->assigned_worker_id,
                    'special_instructions'       => $request->special_instructions,
                    'emergency_contact'          => $request->emergency_contact,
                    'notes'                      => $request->notes,
                    'linked_by'                  => auth()->id(),
                    'linked_by_name'             => auth()->user()->name,
                    'linked_at'                  => now()->toISOString(),
                    'requires_landlord_approval' => true,
                    'landlord_id'                => $landlord->id,
                    'landlord_name'              => $landlord->name,
                    'landlord_phone'             => $landlord->phone,
                    'landlord_email'             => $landlord->email,
                    'approval_token'             => $approvalToken,
                ],
            ];

            if ($hasZoneFk && $request->filled('collection_zone_id')) {
                $createPayload['collection_zone_id'] = $request->collection_zone_id;
            }

            $collectionRequest = WasteCollectionRequest::create($createPayload);

            if ($request->filled('collection_zone_id')
                && Schema::hasColumn('properties', 'collection_zone_id')) {
                $property->update(['collection_zone_id' => $request->collection_zone_id]);
            }

            if ($request->filled('assigned_personnel_id')) {
                $collectionRequest->assignTo($request->assigned_personnel_id);
            }

            if ($request->filled('assigned_worker_id')) {
                $collectionRequest->assignWorker($request->assigned_worker_id);
            }

            $collectionRequest->requestApproval(auth()->id());

            $this->sendLandlordApprovalNotification($landlord, $collectionRequest, $property, $approvalToken);

            Log::info('Waste collection request created - awaiting landlord approval', [
                'request_id'          => $collectionRequest->id,
                'property_id'         => $property->id,
                'collection_zone_id'  => $collectionRequest->collection_zone_id ?? null,
                'landlord_id'         => $landlord->id,
                'requested_by'        => auth()->id(),
                'approval_expires_at' => $collectionRequest->approval_expires_at,
            ]);

            DB::commit();

            return redirect()->route('sanitation.properties.show', $property)
                ->with('success', 'Waste collection request submitted! The landlord has been notified and must approve before collection services begin.')
                ->with('approval_pending', true)
                ->with('request_id', $collectionRequest->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to link property: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to link property: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function linkedProperties(Request $request)
    {
        $query = Property::with(['landlord', 'propertyType'])
            ->whereHas('wasteCollectionRequests')
            ->withCount(['wasteCollectionRequests' => function ($q) {
                $q->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress']);
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('property_name', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%")
                  ->orWhereHas('landlord', function ($lq) use ($search) {
                      $lq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->whereHas('wasteCollectionRequests', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        if ($request->filled('approval_status')) {
            $query->whereHas('wasteCollectionRequests', function ($q) use ($request) {
                $q->where('approval_status', $request->approval_status);
            });
        }

        $properties = $query->orderBy('property_name')->paginate(20);

        $stats = [
            'total'              => Property::whereHas('wasteCollectionRequests')->count(),
            'pending_collection' => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('status', 'pending');
            })->count(),
            'active'             => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress']);
            })->count(),
            'completed_today'    => WasteCollectionRequest::whereDate('created_at', today())
                ->where('status', 'completed')
                ->distinct('property_id')
                ->count(),
            'pending_approvals'  => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING);
            })->count(),
            'approved'           => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('approval_status', WasteCollectionRequest::APPROVAL_APPROVED);
            })->count(),
            'auto_approved'      => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('approval_status', WasteCollectionRequest::APPROVAL_AUTO_APPROVED);
            })->count(),
            'rejected'           => Property::whereHas('wasteCollectionRequests', function ($q) {
                $q->where('approval_status', WasteCollectionRequest::APPROVAL_REJECTED);
            })->count(),
        ];

        $approvalStatuses = WasteCollectionRequest::getApprovalStatuses();

        $canLinkProperties = $this->canCurrentUserLinkProperties();

        return view('sanitation.properties.linked', compact(
            'properties',
            'stats',
            'approvalStatuses',
            'canLinkProperties'
        ));
    }

    public function showProperty(Property $property)
    {
        $property->load(['landlord', 'propertyType']);

        $collectionHistory = $property->wasteCollectionRequests()
            ->with(['assignedTo', 'worker', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $activeRequests = $property->wasteCollectionRequests()
            ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingApproval = $property->wasteCollectionRequests()
            ->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)
            ->first();

        $latestRequest = $property->wasteCollectionRequests()
            ->orderBy('created_at', 'desc')
            ->first();

        $metadata = $property->metadata ?? [];
        $collectionSettings = $metadata['waste_collection'] ?? [];

        $stats = [
            'total'          => $property->wasteCollectionRequests()->count(),
            'completed'      => $property->wasteCollectionRequests()->where('status', 'completed')->count(),
            'pending'        => $property->wasteCollectionRequests()->where('status', 'pending')->count(),
            'active'         => $property->wasteCollectionRequests()
                ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
                ->count(),
            'total_weight'   => $property->wasteCollectionRequests()
                ->where('status', 'completed')
                ->sum('waste_weight_kg'),
            'last_collection' => $property->wasteCollectionRequests()
                ->where('status', 'completed')
                ->latest('completed_at')
                ->first(),

            'approval_status' => $pendingApproval?->approval_status_label
                ?? $latestRequest?->approval_status_label
                ?? 'Not Requested',

            'approval_responded_at' => $pendingApproval?->approval_responded_at
                ?? $latestRequest?->approval_responded_at,

            'approval_expires_at' => $pendingApproval?->approval_expires_at,
            'days_until_expiry'   => $pendingApproval?->days_until_expiry,
        ];

        $canLinkProperties = $this->canCurrentUserLinkProperties();

        return view('sanitation.properties.show', compact(
            'property',
            'collectionHistory',
            'activeRequests',
            'pendingApproval',
            'latestRequest',
            'collectionSettings',
            'stats',
            'canLinkProperties'
        ));
    }

    public function unlinkProperty(Request $request, Property $property)
    {
        $this->authorizePropertyLinking($request);

        try {
            DB::beginTransaction();

            $activeRequests = $property->wasteCollectionRequests()
                ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
                ->count();

            if ($activeRequests > 0) {
                return redirect()->back()
                    ->with('error', "Cannot unlink property with {$activeRequests} active collection request(s).");
            }

            $updateData = [];
            if (Schema::hasColumn('properties', 'collection_zone_id')) {
                $updateData['collection_zone_id'] = null;
            }

            $metadata = $property->metadata ?? [];
            unset($metadata['waste_collection']);
            $updateData['metadata'] = $metadata;

            $property->update($updateData);

            Log::info('Property unlinked from waste collection', [
                'property_id'   => $property->id,
                'property_name' => $property->property_name,
                'unlinked_by'   => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('sanitation.properties.available')
                ->with('success', 'Property unlinked from waste collection successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unlink property: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to unlink property: ' . $e->getMessage());
        }
    }

    public function updateCollectionSettings(Request $request, Property $property)
    {
        $this->authorizePropertyLinking($request);

        $personnelTable = (new SanitationPersonnel)->getTable();
        $personnelKey   = (new SanitationPersonnel)->getKeyName();

        $workerTable = (new SanitationWorker)->getTable();
        $workerKey   = (new SanitationWorker)->getKeyName();

        $validator = Validator::make($request->all(), [
            'collection_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'collection_days'      => 'nullable|array',
            'collection_days.*'    => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'preferred_time'       => 'nullable|string',
            'waste_types'          => 'nullable|array',

            'collection_zone_id' => 'nullable|exists:collection_zones,id',

            'assigned_personnel_id' => [
                'nullable',
                'integer',
                Rule::exists($personnelTable, $personnelKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'assigned_worker_id' => [
                'nullable',
                'integer',
                Rule::exists($workerTable, $workerKey)
                    ->where(fn ($q) => $q->where('status', 'active')),
            ],

            'special_instructions' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $metadata = $property->metadata ?? [];
            $metadata['waste_collection'] = array_merge(
                $metadata['waste_collection'] ?? [],
                [
                    'collection_frequency'  => $request->collection_frequency,
                    'collection_days'       => $request->collection_days,
                    'preferred_time'        => $request->preferred_time,
                    'waste_types'           => $request->waste_types,
                    'collection_zone_id'    => $request->collection_zone_id,
                    'assigned_personnel_id' => $request->assigned_personnel_id,
                    'assigned_worker_id'    => $request->assigned_worker_id,
                    'special_instructions'  => $request->special_instructions,
                    'updated_at'            => now()->toISOString(),
                    'updated_by'            => auth()->id(),
                    'updated_by_name'       => auth()->user()->name,
                ]
            );

            $propertyUpdate = ['metadata' => $metadata];

            if ($request->filled('collection_zone_id')
                && Schema::hasColumn('properties', 'collection_zone_id')) {
                $propertyUpdate['collection_zone_id'] = $request->collection_zone_id;
            }

            $property->update($propertyUpdate);

            $latestRequest = $property->wasteCollectionRequests()->latest()->first();
            if ($latestRequest) {
                $requestMetadata = $latestRequest->metadata ?? [];
                $requestMetadata['collection_settings'] = [
                    'collection_frequency' => $request->collection_frequency,
                    'collection_days'      => $request->collection_days,
                    'preferred_time'       => $request->preferred_time,
                    'waste_types'          => $request->waste_types,
                    'updated_at'           => now()->toISOString(),
                ];
                $latestRequestUpdate = ['metadata' => $requestMetadata];

                if ($request->filled('collection_zone_id')
                    && Schema::hasColumn('waste_collection_requests', 'collection_zone_id')) {
                    $latestRequestUpdate['collection_zone_id'] = $request->collection_zone_id;
                }

                $latestRequest->update($latestRequestUpdate);
            }

            DB::commit();

            return redirect()->route('sanitation.properties.show', $property)
                ->with('success', 'Collection settings updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update collection settings: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update collection settings: ' . $e->getMessage());
        }
    }

    // ================================================================ //
    // ✅ LANDLORD APPROVAL METHODS                                    //
    // ================================================================ //

    public function pendingApprovals(Request $request)
    {
        $user = auth()->user();
        $personnel = $user->sanitationPersonnel;

        $query = WasteCollectionRequest::with(['property', 'property.landlord', 'requestedBy', 'assignedTo'])
            ->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING);

        if ($personnel && !$user->isSuperAdmin() && !$user->isAdmin()) {
            $query->where(function ($q) use ($personnel, $user) {
                $q->where('assigned_to', $personnel->id)
                  ->orWhere('requested_by', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('property', function ($q) use ($search) {
                $q->where('property_name', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('expiring_soon')) {
            $query->where('approval_expires_at', '<=', now()->addDays(2));
        }

        $pendingRequests = $query->orderBy('approval_expires_at', 'asc')
            ->paginate(20);

        $stats = [
            'pending'             => WasteCollectionRequest::where('approval_status', 'pending')->count(),
            'expiring_soon'       => WasteCollectionRequest::where('approval_status', 'pending')
                ->where('approval_expires_at', '<=', now()->addDays(2))
                ->count(),
            'expired'             => WasteCollectionRequest::where('approval_status', 'pending')
                ->where('approval_expires_at', '<', now())
                ->count(),
            'approved_today'      => WasteCollectionRequest::where('approval_status', 'approved')
                ->whereDate('approval_responded_at', today())
                ->count(),
            'auto_approved_today' => WasteCollectionRequest::where('approval_status', 'auto_approved')
                ->whereDate('approval_responded_at', today())
                ->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $pendingRequests,
                'stats'   => $stats,
            ]);
        }

        return view('sanitation.approvals.pending', compact('pendingRequests', 'stats'));
    }

    public function landlordApproval(Request $request, $token)
    {
        $collectionRequest = WasteCollectionRequest::where('metadata->approval_token', $token)
            ->firstOrFail();

        if ($collectionRequest->approval_status !== WasteCollectionRequest::APPROVAL_PENDING) {
            return view('sanitation.approvals.already_responded', [
                'request' => $collectionRequest,
                'status'  => $collectionRequest->approval_status_label,
            ]);
        }

        if ($collectionRequest->is_approval_expired) {
            $collectionRequest->autoApprove();

            return view('sanitation.approvals.expired', [
                'request'       => $collectionRequest,
                'auto_approved' => true,
            ]);
        }

        $property = $collectionRequest->property;
        $landlord = $property->landlord;
        $collectionDetails = $collectionRequest->metadata ?? [];

        return view('sanitation.approvals.landlord', compact(
            'collectionRequest',
            'property',
            'landlord',
            'collectionDetails'
        ));
    }

    public function handleLandlordApproval(Request $request, WasteCollectionRequest $collectionRequest)
    {
        $validator = Validator::make($request->all(), [
            'action'           => 'required|in:approve,reject',
            'notes'            => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
            'declaration'      => 'required|accepted',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            if ($request->action === 'approve') {
                $collectionRequest->approve(auth()->id(), $request->notes);

                $property = $collectionRequest->property;
                $metadata = $property->metadata ?? [];
                $metadata['waste_collection'] = array_merge(
                    $metadata['waste_collection'] ?? [],
                    [
                        'approved_at'      => now()->toISOString(),
                        'approved_by'      => auth()->id(),
                        'approved_by_name' => auth()->user()->name,
                    ]
                );
                $property->update(['metadata' => $metadata]);

                $message = '✅ You have approved waste collection for this property. The sanitation team will begin services shortly.';
                $status = 'approved';

                $this->sendApprovalResponseNotification($collectionRequest, 'approved');

            } else {
                $collectionRequest->reject(auth()->id(), $request->rejection_reason);
                $message = '❌ You have rejected waste collection for this property.';
                $status = 'rejected';

                $this->sendApprovalResponseNotification($collectionRequest, 'rejected');
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'status'  => $status,
                    'request' => $collectionRequest->fresh(),
                ]);
            }

            return redirect()->route('sanitation.approvals.thankyou', $collectionRequest)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process landlord approval: ' . $e->getMessage(), [
                'request_id' => $collectionRequest->id,
                'action'     => $request->action,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process your response. Please try again.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to process your response. Please try again.');
        }
    }

    public function approvalThankYou(WasteCollectionRequest $collectionRequest)
    {
        return view('sanitation.approvals.thankyou', [
            'request'      => $collectionRequest,
            'property'     => $collectionRequest->property,
            'status'       => $collectionRequest->approval_status_label,
            'status_badge' => $collectionRequest->approval_badge,
        ]);
    }

    public function resendApproval(Request $request, WasteCollectionRequest $collectionRequest)
    {
        if ($collectionRequest->approval_status !== WasteCollectionRequest::APPROVAL_PENDING) {
            return redirect()->back()
                ->with('error', 'This request is no longer pending approval.');
        }

        try {
            $collectionRequest->requestApproval(auth()->id());

            Log::info('Approval request resent to landlord', [
                'request_id'  => $collectionRequest->id,
                'property_id' => $collectionRequest->property_id,
                'resent_by'   => auth()->id(),
            ]);

            return redirect()->back()
                ->with('success', 'Approval request resent to landlord successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to resend approval: ' . $e->getMessage(), [
                'request_id' => $collectionRequest->id,
            ]);

            return redirect()->back()
                ->with('error', 'Failed to resend approval request. Please try again.');
        }
    }

    public function bulkApprove(Request $request)
    {
        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            return $this->errorResponse($request, 'Unauthorized. Only administrators can perform bulk approvals.');
        }

        $validator = Validator::make($request->all(), [
            'request_ids'   => 'required|array',
            'request_ids.*' => 'exists:waste_collection_requests,id',
            'notes'         => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            DB::beginTransaction();

            $count = 0;
            foreach ($request->request_ids as $id) {
                $collectionRequest = WasteCollectionRequest::find($id);
                if ($collectionRequest && $collectionRequest->canApprove) {
                    $collectionRequest->approve(auth()->id(), $request->notes ?? 'Bulk approval by admin');
                    $count++;
                }
            }

            DB::commit();

            $message = "Successfully approved {$count} request(s).";

            if ($request->expectsJson()) {
                return response()->json([
                    'success'        => true,
                    'message'        => $message,
                    'approved_count' => $count,
                ]);
            }

            return redirect()->route('sanitation.approvals.pending')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk approve: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to bulk approve requests.');
        }
    }

    // ================================================================ //
    // ✅ NOTIFICATION METHODS                                          //
    // ================================================================ //

    protected function sendLandlordApprovalNotification($landlord, $collectionRequest, $property, $approvalToken)
    {
        try {
            $approvalUrl = route('sanitation.approvals.landlord', $approvalToken);

            $landlord->notify(new GeneralNotification(
                title: '🏠 Waste Collection Approval Required',
                message: "A waste collection request has been submitted for your property: {$property->property_name}. Please review and approve or reject the request.",
                icon: 'fas fa-trash-alt text-primary',
                category: 'waste_collection',
                priority: 2,
                actionUrl: $approvalUrl,
                data: [
                    'request_id'           => $collectionRequest->id,
                    'property_id'          => $property->id,
                    'property_name'        => $property->property_name,
                    'digital_address'      => $property->digital_address,
                    'collection_frequency' => $collectionRequest->metadata['collection_frequency'] ?? 'weekly',
                    'waste_types'          => $collectionRequest->metadata['waste_types'] ?? ['general'],
                    'requested_by'         => auth()->user()->name,
                    'requested_at'         => now()->toISOString(),
                    'approval_token'       => $approvalToken,
                    'approval_expires_at'  => $collectionRequest->approval_expires_at->toISOString(),
                    'days_to_respond'      => WasteCollectionRequest::APPROVAL_EXPIRY_DAYS,
                ],
                roles: ['landlord']
            ));

            $landlord->notify(new GeneralNotification(
                title: '⚠️ Action Required: Waste Collection Approval',
                message: "You have {$collectionRequest->approval_expires_at->diffInDays(now())} days to respond. If you don't respond within {$collectionRequest->approval_expires_at->diffInDays(now())} days, the request will be automatically approved.",
                icon: 'fas fa-clock text-warning',
                category: 'waste_collection_reminder',
                priority: 1,
                actionUrl: $approvalUrl,
                data: [
                    'request_id'    => $collectionRequest->id,
                    'property_id'   => $property->id,
                    'property_name' => $property->property_name,
                    'expires_at'    => $collectionRequest->approval_expires_at->toISOString(),
                    'days_left'     => $collectionRequest->approval_expires_at->diffInDays(now()),
                    'is_reminder'   => true,
                ],
                roles: ['landlord']
            ));

            Log::info('Landlord approval notification sent', [
                'landlord_id'  => $landlord->id,
                'request_id'   => $collectionRequest->id,
                'property_id'  => $property->id,
                'approval_url' => $approvalUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send landlord approval notification: ' . $e->getMessage(), [
                'landlord_id' => $landlord->id,
                'request_id'  => $collectionRequest->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    protected function sendApprovalResponseNotification($collectionRequest, $response)
    {
        try {
            $personnel = $collectionRequest->assignedTo;
            $property  = $collectionRequest->property;

            if ($response === 'approved') {
                $personnel?->user?->notify(new GeneralNotification(
                    title: '✅ Waste Collection Approved',
                    message: "The landlord has approved waste collection for {$property->property_name}. You can now proceed with the collection.",
                    icon: 'fas fa-check-circle text-success',
                    category: 'waste_collection_approved',
                    priority: 2,
                    actionUrl: route('sanitation.requests.show', $collectionRequest),
                    data: [
                        'request_id'    => $collectionRequest->id,
                        'property_name' => $property->property_name,
                        'approved_at'   => now()->toISOString(),
                    ],
                    roles: ['sanitation-personnel', 'admin']
                ));
            } else {
                $personnel?->user?->notify(new GeneralNotification(
                    title: '❌ Waste Collection Rejected',
                    message: "The landlord has rejected waste collection for {$property->property_name}. Reason: {$collectionRequest->rejection_reason}",
                    icon: 'fas fa-times-circle text-danger',
                    category: 'waste_collection_rejected',
                    priority: 1,
                    actionUrl: route('sanitation.requests.show', $collectionRequest),
                    data: [
                        'request_id'       => $collectionRequest->id,
                        'property_name'    => $property->property_name,
                        'rejection_reason' => $collectionRequest->rejection_reason,
                        'rejected_at'      => now()->toISOString(),
                    ],
                    roles: ['sanitation-personnel', 'admin']
                ));
            }

            Log::info('Approval response notification sent', [
                'request_id'   => $collectionRequest->id,
                'response'     => $response,
                'personnel_id' => $personnel?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send approval response notification: ' . $e->getMessage(), [
                'request_id' => $collectionRequest->id,
                'response'   => $response,
            ]);
        }
    }

    /**
     * Display all waste collection requests.
     *
     * Scope:
     *   - Admins & super-admins → everything
     *   - Root supervisors       → entire hierarchy
     *   - Sub-supervisors        → themselves + direct reports
     *   - Regular personnel      → only their own
     */
    public function listRequests(Request $request)
    {
        $user      = auth()->user();
        $personnel = $user->sanitationPersonnel;

        $query = WasteCollectionRequest::with([
            'property', 'assignedTo', 'requestedBy', 'worker', 'approvedBy',
        ]);

        $validStatuses = array_keys(WasteCollectionRequest::getStatuses());
        if ($request->filled('status') && in_array($request->status, $validStatuses, true)) {
            $query->where('status', $request->status);
        }

        $validApprovals = array_keys(WasteCollectionRequest::getApprovalStatuses());
        if ($request->filled('approval_status') && in_array($request->approval_status, $validApprovals, true)) {
            $query->where('approval_status', $request->approval_status);
        }

        $validPriorities = array_keys(WasteCollectionRequest::getPriorities());
        if ($request->filled('priority') && in_array($request->priority, $validPriorities, true)) {
            $query->where('priority', $request->priority);
        }

        $validWasteTypes = array_keys(WasteCollectionRequest::getWasteTypes());
        if ($request->filled('waste_type') && in_array($request->waste_type, $validWasteTypes, true)) {
            $query->where('waste_type', $request->waste_type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('property', function ($pq) use ($search) {
                    $pq->where('property_name', 'like', "%{$search}%")
                       ->orWhere('digital_address', 'like', "%{$search}%");
                })->orWhere('id', 'like', "%{$search}%");
            });
        }

        $isAdmin          = $user->isSuperAdmin() || $user->isAdmin();
        $isSupervisor     = $personnel && $personnel->isSupervisor();
        $isRootSupervisor = $isSupervisor && is_null($personnel->supervisor_id);

        if (!$isAdmin && $personnel) {
            if ($isRootSupervisor) {
                $descendantIds = $this->collectDescendantIds($personnel)->push($personnel->id);
                $query->whereIn('assigned_to', $descendantIds);
            } elseif ($isSupervisor) {
                $subordinateIds = $personnel->subordinates()->pluck('id')->push($personnel->id);
                $query->whereIn('assigned_to', $subordinateIds);
            } else {
                $query->where('assigned_to', $personnel->id);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        $statuses         = WasteCollectionRequest::getStatuses();
        $priorities       = WasteCollectionRequest::getPriorities();
        $wasteTypes       = WasteCollectionRequest::getWasteTypes();
        $approvalStatuses = WasteCollectionRequest::getApprovalStatuses();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $requests,
            ]);
        }

        return view('sanitation.requests.index', compact(
            'requests',
            'statuses',
            'priorities',
            'wasteTypes',
            'approvalStatuses'
        ));
    }

    public function pendingRequests(Request $request)
    {
        $user      = auth()->user();
        $personnel = $user->sanitationPersonnel;

        $baseQuery = WasteCollectionRequest::query()
            ->where('status', 'pending')
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ]);

        $isAdmin          = $user->isSuperAdmin() || $user->isAdmin();
        $isRootSupervisor = $personnel
            && $personnel->isSupervisor()
            && is_null($personnel->supervisor_id);

        if (!$isAdmin && !$isRootSupervisor && $personnel) {
            if ($personnel->isSupervisor()) {
                $subordinateIds = $personnel->subordinates()->pluck('id')->push($personnel->id);
                $baseQuery->whereIn('assigned_to', $subordinateIds);
            } else {
                $baseQuery->where('assigned_to', $personnel->id);
            }
        }

        $priorityCounts = [
            'emergency' => (clone $baseQuery)->where('priority', 'emergency')->count(),
            'high'      => (clone $baseQuery)->where('priority', 'high')->count(),
            'medium'    => (clone $baseQuery)->where('priority', 'medium')->count(),
            'low'       => (clone $baseQuery)->where('priority', 'low')->count(),
        ];

        $unassignedCount = (clone $baseQuery)->whereNull('assigned_to')->count();

        $requests = $baseQuery
            ->with(['property', 'requestedBy', 'assignedTo'])
            ->orderByRaw("CASE WHEN assigned_to IS NULL THEN 0 ELSE 1 END")
            ->orderByRaw("CASE
                WHEN priority = 'emergency' THEN 0
                WHEN priority = 'high'      THEN 1
                WHEN priority = 'medium'    THEN 2
                ELSE 3
            END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        $availablePersonnel = $this->getAssignablePersonnelFor($user, $personnel);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $requests,
            ]);
        }

        return view('sanitation.requests.pending', compact(
            'requests',
            'availablePersonnel',
            'priorityCounts',
            'unassignedCount'
        ));
    }

    private function getAssignablePersonnelFor($user, $personnel)
    {
        $query = SanitationPersonnel::active()->with('user');

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query->orderBy('first_name')->get();
        }

        if (!$personnel || !$personnel->isSupervisor()) {
            return collect();
        }

        if (is_null($personnel->supervisor_id)) {
            $descendantIds = $this->collectDescendantIds($personnel);
            return $query->whereIn('id', $descendantIds->push($personnel->id))
                ->orderBy('first_name')
                ->get();
        }

        $subordinateIds = $personnel->subordinates()->pluck('id')->push($personnel->id);
        return $query->whereIn('id', $subordinateIds)->orderBy('first_name')->get();
    }

    private function collectDescendantIds(SanitationPersonnel $personnel): \Illuminate\Support\Collection
    {
        $ids = collect();
        $queue = $personnel->subordinates()->pluck('id');

        while ($queue->isNotEmpty()) {
            $ids = $ids->merge($queue);

            $queue = SanitationPersonnel::query()
                ->whereIn('supervisor_id', $queue)
                ->pluck('id');
        }

        return $ids->unique()->values();
    }

    public function assignRequest(Request $request, WasteCollectionRequest $collectionRequest)
    {
        if (!in_array($collectionRequest->approval_status, [
            WasteCollectionRequest::APPROVAL_APPROVED,
            WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
        ])) {
            return $this->errorResponse($request, 'This request has not been approved by the landlord yet.');
        }

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
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            $personnel = SanitationPersonnel::findOrFail($request->personnel_id);

            if ($personnel->status !== 'active') {
                return $this->errorResponse($request, 'This personnel is not available for assignment.');
            }

            DB::beginTransaction();

            $collectionRequest->assignTo($personnel->id);
            $this->notifyPersonnelAssignment($collectionRequest, $personnel);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request assigned successfully!',
                    'request' => $collectionRequest->load(['assignedTo']),
                ]);
            }

            return redirect()->back()
                ->with('success', 'Request assigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign request: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to assign request: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, WasteCollectionRequest $collectionRequest)
    {
        if (!in_array($collectionRequest->approval_status, [
            WasteCollectionRequest::APPROVAL_APPROVED,
            WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
        ]) && !in_array($request->status, ['cancelled'])) {
            return $this->errorResponse($request, 'This request has not been approved by the landlord.');
        }

        $requiresWeight = $request->status === 'completed'
            && $collectionRequest->status === 'in_progress';

        $validator = Validator::make($request->all(), [
            'status'           => 'required|in:en_route,arrived,in_progress,completed,cancelled',
            'completion_notes' => 'nullable|string|max:500',
            'waste_weight_kg'  => ($requiresWeight ? 'required' : 'nullable') . '|numeric|min:0',
            'after_photo'      => 'nullable|image|max:5120',
            'before_photo'     => 'nullable|image|max:5120',
        ], [
            'waste_weight_kg.required' => 'Please record the collected waste weight before completing this stop.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            DB::beginTransaction();

            $status = $request->status;
            $updateData = [];

            if ($request->hasFile('before_photo')) {
                $path = $request->file('before_photo')->store('waste-collection/before', 'public');
                $updateData['before_photo'] = $path;
            }

            if ($request->hasFile('after_photo')) {
                $path = $request->file('after_photo')->store('waste-collection/after', 'public');
                $updateData['after_photo'] = $path;
            }

            switch ($status) {
                case 'en_route':
                    $collectionRequest->markEnRoute();
                    break;

                case 'arrived':
                    $collectionRequest->markArrived();
                    break;

                case 'in_progress':
                    $collectionRequest->markStarted();
                    break;

                case 'completed':
                    $collectionRequest->markCompleted(
                        $request->completion_notes,
                        $request->waste_weight_kg,
                        $updateData['after_photo'] ?? null
                    );
                    $this->updatePropertyStats($collectionRequest->property);
                    break;

                case 'cancelled':
                    $collectionRequest->cancel($request->completion_notes);
                    break;
            }

            if (!empty($updateData)) {
                $collectionRequest->update($updateData);
            }

            $this->logStatusChange($collectionRequest, $status);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request status updated successfully!',
                    'request' => $collectionRequest->fresh(),
                ]);
            }

            return redirect()->back()
                ->with('success', 'Request status updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update request status: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to update status: ' . $e->getMessage());
        }
    }

    public function showRequest(Request $request, WasteCollectionRequest $collectionRequest)
    {
        $user      = auth()->user();
        $personnel = $user->sanitationPersonnel;

        $isAdmin          = $user->isSuperAdmin() || $user->isAdmin();
        $isSupervisor     = $personnel && $personnel->isSupervisor();
        $isRootSupervisor = $isSupervisor && is_null($personnel->supervisor_id);

        if (!$isAdmin) {
            $allowedIds = collect();

            if ($isRootSupervisor) {
                $allowedIds = $this->collectDescendantIds($personnel)->push($personnel->id);
            } elseif ($isSupervisor) {
                $allowedIds = $personnel->subordinates()->pluck('id')->push($personnel->id);
            } elseif ($personnel) {
                $allowedIds = collect([$personnel->id]);
            }

            if ($allowedIds->isEmpty() || !$allowedIds->contains($collectionRequest->assigned_to)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to view this request.',
                    ], 403);
                }
                return redirect()->route('sanitation.requests.index')
                    ->with('error', 'You are not authorized to view this request.');
            }
        }

        $collectionRequest->load([
            'property',
            'property.landlord',
            'property.propertyType',
            'requestedBy',
            'assignedTo',
            'worker',
            'approvedBy',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $collectionRequest,
            ]);
        }

        $view = view()->exists('sanitation.requests.show')
            ? 'sanitation.requests.show'
            : 'sanitation.requests.index';

        return view($view, [
            'request'           => $collectionRequest,
            'collectionRequest' => $collectionRequest,
        ]);
    }

    // ================================================================ //
    // 📊 STATISTICS & REPORTS                                          //
    // ================================================================ //

    public function statistics(Request $request)
    {
        $stats                = $this->sanitationService->getSystemStats();
        $dailyStats           = $this->getDailyStats();
        $personnelPerformance = $this->getPersonnelPerformance();

        $approvalStats = [
            'pending'       => WasteCollectionRequest::where('approval_status', 'pending')->count(),
            'approved'      => WasteCollectionRequest::where('approval_status', 'approved')->count(),
            'rejected'      => WasteCollectionRequest::where('approval_status', 'rejected')->count(),
            'auto_approved' => WasteCollectionRequest::where('approval_status', 'auto_approved')->count(),
            'expired'       => WasteCollectionRequest::where('approval_status', 'expired')->count(),
            'average_response_time' => WasteCollectionRequest::whereNotNull('approval_responded_at')
                ->whereIn('approval_status', ['approved', 'rejected'])
                ->avg(DB::raw('TIMESTAMPDIFF(HOUR, approval_requested_at, approval_responded_at)')),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'system_stats'          => $stats,
                    'daily_stats'           => $dailyStats,
                    'personnel_performance' => $personnelPerformance,
                    'approval_stats'        => $approvalStats,
                ],
            ]);
        }

        return view('sanitation.statistics', compact('stats', 'dailyStats', 'personnelPerformance', 'approvalStats'));
    }

    public function reports(Request $request)
    {
        $type = $request->get('type', 'daily');
        $date = $request->get('date', now()->toDateString());

        $reportData = $this->generateReport($type, $date);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $reportData,
            ]);
        }

        return view('sanitation.reports', compact('reportData', 'type', 'date'));
    }

    // ================================================================ //
    // 👤 PERSONNEL & WORKER MANAGEMENT                                //
    // ================================================================ //

    public function getAvailablePersonnel(Request $request)
    {
        $personnel = SanitationPersonnel::available()
            ->with('user')
            ->get()
            ->map(function ($person) {
                return [
                    'id'              => $person->id,
                    'name'            => $person->full_name,
                    'role'            => $person->role,
                    'active_jobs'     => $person->active_jobs_count,
                    'completed_today' => $person->completed_jobs_today,
                    'status'          => $person->status,
                    'location'        => $person->current_location,
                ];
            });

        return response()->json([
            'success'   => true,
            'personnel' => $personnel,
        ]);
    }

    public function getMapMarkers(Request $request)
    {
        $user = auth()->user();

        $requests = WasteCollectionRequest::whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ])
            ->with(['property', 'assignedTo'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $personnel = SanitationPersonnel::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $workers = [];
        if ($user->isSanitationPersonnel() || $user->isAdmin() || $user->isSuperAdmin()) {
            $workers = SanitationWorker::active()
                ->with('supervisor')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();
        }

        $markers = [
            'requests'  => $this->formatRequestMarkers($requests),
            'personnel' => $this->formatPersonnelMarkers($personnel),
            'workers'   => $this->formatWorkerMarkers($workers),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'markers' => $markers,
            ]);
        }

        return $markers;
    }

    // ================================================================ //
    // 🆕 BULK OPERATIONS                                              //
    // ================================================================ //

    public function bulkLinkProperties(Request $request)
    {
        $this->authorizePropertyLinking($request);

        $validator = Validator::make($request->all(), [
            'property_ids'   => 'required|array|min:1',
            'property_ids.*' => 'exists:properties,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $linked  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($request->property_ids as $id) {
            $property = Property::with('landlord')->find($id);

            if (!$property) {
                $skipped++;
                continue;
            }

            $alreadyLinked = $property->wasteCollectionRequests()
                ->whereIn('approval_status', [
                    WasteCollectionRequest::APPROVAL_PENDING,
                    WasteCollectionRequest::APPROVAL_APPROVED,
                    WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
                ])
                ->exists();

            if ($alreadyLinked) {
                $skipped++;
                continue;
            }

            if (!$property->landlord) {
                $skipped++;
                $errors[] = "{$property->property_name}: no landlord assigned";
                continue;
            }

            try {
                DB::beginTransaction();

                $approvalToken = Str::random(64);
                $landlord      = $property->landlord;

                $collectionRequest = WasteCollectionRequest::create([
                    'property_id'         => $property->id,
                    'requested_by'        => auth()->id(),
                    'waste_type'          => 'general',
                    'priority'            => 'medium',
                    'status'              => 'pending',
                    'approval_status'     => WasteCollectionRequest::APPROVAL_PENDING,
                    'digital_address'     => $property->digital_address,
                    'latitude'            => $property->latitude,
                    'longitude'           => $property->longitude,
                    'approval_expires_at' => now()->addDays(WasteCollectionRequest::APPROVAL_EXPIRY_DAYS),
                    'metadata' => [
                        'collection_linked'          => true,
                        'collection_frequency'       => 'weekly',
                        'linked_by'                  => auth()->id(),
                        'linked_by_name'             => auth()->user()->name,
                        'linked_at'                  => now()->toISOString(),
                        'linked_via'                 => 'bulk_link',
                        'requires_landlord_approval' => true,
                        'landlord_id'                => $landlord->id,
                        'landlord_name'              => $landlord->name,
                        'approval_token'             => $approvalToken,
                    ],
                ]);

                $collectionRequest->requestApproval(auth()->id());
                $this->sendLandlordApprovalNotification($landlord, $collectionRequest, $property, $approvalToken);

                DB::commit();
                $linked++;

            } catch (\Exception $e) {
                DB::rollBack();
                $skipped++;
                $errors[] = "{$property->property_name}: {$e->getMessage()}";
                Log::warning('Bulk-link skipped a property', [
                    'property_id' => $property->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $message = "Linked {$linked} propert" . ($linked === 1 ? 'y' : 'ies') . ".";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped}.";
        }
        if (!empty($errors)) {
            $message .= ' (' . implode('; ', array_slice($errors, 0, 3)) . ')';
        }

        return redirect()->back()->with('success', $message);
    }

    public function bulkApproveLandlordRequests(Request $request)
    {
        $this->authorizePropertyLinking($request);

        $validator = Validator::make($request->all(), [
            'property_ids'   => 'required|array|min:1',
            'property_ids.*' => 'exists:properties,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $approved = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($request->property_ids as $id) {
            $property = Property::with('landlord')->find($id);

            if (!$property) {
                $skipped++;
                continue;
            }

            $serviceRequest = SanitationServiceRequest::query()
                ->forProperty($id)
                ->pending()
                ->first();

            if (!$serviceRequest) {
                $skipped++;
                continue;
            }

            if (!$property->landlord) {
                $skipped++;
                $errors[] = "{$property->property_name}: no landlord assigned";
                continue;
            }

            try {
                DB::beginTransaction();

                $landlord      = $property->landlord;
                $approvalToken = Str::random(64);
                $frequency     = $serviceRequest->collection_frequency ?? 'weekly';

                $collectionRequest = WasteCollectionRequest::create([
                    'property_id'         => $property->id,
                    'requested_by'        => auth()->id(),
                    'waste_type'          => 'general',
                    'priority'            => 'medium',
                    'status'              => 'pending',
                    'approval_status'     => WasteCollectionRequest::APPROVAL_PENDING,
                    'digital_address'     => $property->digital_address,
                    'latitude'            => $property->latitude,
                    'longitude'           => $property->longitude,
                    'approval_expires_at' => now()->addDays(WasteCollectionRequest::APPROVAL_EXPIRY_DAYS),
                    'metadata' => [
                        'collection_linked'          => true,
                        'collection_frequency'       => $frequency,
                        'linked_by'                  => auth()->id(),
                        'linked_by_name'             => auth()->user()->name,
                        'linked_at'                  => now()->toISOString(),
                        'linked_via'                 => 'bulk_approve_service_request',
                        'service_request_id'         => $serviceRequest->id,
                        'quoted_monthly_fee'         => $serviceRequest->quoted_monthly_fee,
                        'requires_landlord_approval' => true,
                        'landlord_id'                => $landlord->id,
                        'landlord_name'              => $landlord->name,
                        'approval_token'             => $approvalToken,
                    ],
                ]);

                $collectionRequest->requestApproval(auth()->id());
                $this->sendLandlordApprovalNotification($landlord, $collectionRequest, $property, $approvalToken);

                $serviceRequest->update([
                    'status'       => SanitationServiceRequest::STATUS_APPROVED,
                    'responded_at' => now(),
                    'responded_by' => auth()->id(),
                ]);

                DB::commit();
                $approved++;

            } catch (\Exception $e) {
                DB::rollBack();
                $skipped++;
                $errors[] = "{$property->property_name}: {$e->getMessage()}";
                Log::warning('Bulk-approve skipped a service request', [
                    'property_id' => $property->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $message = "Approved {$approved} landlord request(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped}.";
        }
        if (!empty($errors)) {
            $message .= ' (' . implode('; ', array_slice($errors, 0, 3)) . ')';
        }

        return redirect()->back()->with('success', $message);
    }

    // ================================================================ //
    // 🔧 PRIVATE HELPER METHODS                                       //
    // ================================================================ //

    private function calculateCompletionRate($personnel): float
    {
        $total     = $personnel->assignedRequests()->count();
        $completed = $personnel->assignedRequests()->where('status', 'completed')->count();

        if ($total === 0) {
            return 0;
        }

        return round(($completed / $total) * 100, 2);
    }

    private function calculatePerformanceMetrics($personnel): array
    {
        $requests = $personnel->assignedRequests()
            ->where('status', 'completed')
            ->get();

        if ($requests->isEmpty()) {
            return [
                'avg_completion_time'    => 0,
                'total_weight'           => 0,
                'total_requests'         => 0,
                'avg_weight_per_request' => 0,
                'efficiency_score'       => 0,
            ];
        }

        $avgCompletionTime   = $requests->avg('completion_time') ?? 0;
        $totalWeight         = $requests->sum('waste_weight_kg') ?? 0;
        $totalRequests       = $requests->count();
        $avgWeightPerRequest = $totalRequests > 0 ? $totalWeight / $totalRequests : 0;

        $efficiencyScore = 0;
        if ($avgCompletionTime > 0 && $avgWeightPerRequest > 0) {
            $efficiencyScore = round(($avgWeightPerRequest / ($avgCompletionTime / 60)) * 10, 2);
            $efficiencyScore = min($efficiencyScore, 100);
        }

        return [
            'avg_completion_time'    => round($avgCompletionTime, 2),
            'total_weight'           => round($totalWeight, 2),
            'total_requests'         => $totalRequests,
            'avg_weight_per_request' => round($avgWeightPerRequest, 2),
            'efficiency_score'       => $efficiencyScore,
        ];
    }

    private function getWeeklyTrends(): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trends[] = [
                'date'      => $date->format('Y-m-d'),
                'day'       => $date->format('D'),
                'requests'  => WasteCollectionRequest::whereDate('created_at', $date)->count(),
                'completed' => WasteCollectionRequest::whereDate('completed_at', $date)->where('status', 'completed')->count(),
                'weight'    => WasteCollectionRequest::whereDate('completed_at', $date)
                    ->where('status', 'completed')
                    ->sum('waste_weight_kg'),
                'approvals' => WasteCollectionRequest::whereDate('approval_responded_at', $date)
                    ->whereIn('approval_status', ['approved', 'auto_approved'])
                    ->count(),
            ];
        }

        return $trends;
    }

    private function getMapData(): array
    {
        $requests = WasteCollectionRequest::whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
            ->whereIn('approval_status', [
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('property')
            ->limit(50)
            ->get();

        return [
            'requests' => $requests->map(function ($request) {
                return [
                    'id'              => $request->id,
                    'lat'             => $request->latitude,
                    'lng'             => $request->longitude,
                    'title'           => $request->property->property_name ?? 'Unknown',
                    'status'          => $request->status,
                    'priority'        => $request->priority,
                    'approval_status' => $request->approval_status_label,
                ];
            }),
        ];
    }

    private function getTopPerformers(): array
    {
        return SanitationPersonnel::withCount(['assignedRequests' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->having('assigned_requests_count', '>', 0)
            ->orderBy('assigned_requests_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($person) {
                return [
                    'id'              => $person->id,
                    'name'            => $person->full_name,
                    'completed'       => $person->assigned_requests_count,
                    'total'           => $person->assignedRequests()->count(),
                    'completion_rate' => $this->calculateCompletionRate($person),
                ];
            })
            ->toArray();
    }

    private function getDailyStats(): array
    {
        $stats = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $stats[] = [
                'date'      => $date->format('Y-m-d'),
                'requests'  => WasteCollectionRequest::whereDate('created_at', $date)->count(),
                'completed' => WasteCollectionRequest::whereDate('completed_at', $date)->where('status', 'completed')->count(),
                'weight'    => WasteCollectionRequest::whereDate('completed_at', $date)
                    ->where('status', 'completed')
                    ->sum('waste_weight_kg'),
                'approvals' => WasteCollectionRequest::whereDate('approval_responded_at', $date)
                    ->whereIn('approval_status', ['approved', 'auto_approved'])
                    ->count(),
            ];
        }

        return $stats;
    }

    private function getPersonnelPerformance(): array
    {
        return SanitationPersonnel::active()
            ->withCount(['assignedRequests' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get()
            ->map(function ($person) {
                return [
                    'id'              => $person->id,
                    'name'            => $person->full_name,
                    'completed'       => $person->assigned_requests_count,
                    'total'           => $person->assignedRequests()->count(),
                    'completion_rate' => $this->calculateCompletionRate($person),
                    'avg_time'        => $person->assignedRequests()
                        ->where('status', 'completed')
                        ->avg('completion_time'),
                ];
            })
            ->toArray();
    }

    private function generateReport(string $type, string $date): array
    {
        $report = [
            'type'         => $type,
            'date'         => $date,
            'generated_at' => now()->toISOString(),
        ];

        switch ($type) {
            case 'daily':
                $report['data'] = $this->getDailyReportData($date);
                break;
            case 'weekly':
                $report['data'] = $this->getWeeklyReportData($date);
                break;
            case 'monthly':
                $report['data'] = $this->getMonthlyReportData($date);
                break;
        }

        return $report;
    }

    private function getDailyReportData(string $date): array
    {
        $dateObj = \Carbon\Carbon::parse($date);

        return [
            'total_requests' => WasteCollectionRequest::whereDate('created_at', $dateObj)->count(),
            'completed'      => WasteCollectionRequest::whereDate('completed_at', $dateObj)->where('status', 'completed')->count(),
            'pending'        => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('status', 'pending')->count(),
            'total_weight'   => WasteCollectionRequest::whereDate('completed_at', $dateObj)
                ->where('status', 'completed')
                ->sum('waste_weight_kg'),
            'by_priority' => [
                'low'       => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('priority', 'low')->count(),
                'medium'    => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('priority', 'medium')->count(),
                'high'      => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('priority', 'high')->count(),
                'emergency' => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('priority', 'emergency')->count(),
            ],
            'by_waste_type' => [
                'general'    => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('waste_type', 'general')->count(),
                'recyclable' => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('waste_type', 'recyclable')->count(),
                'organic'    => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('waste_type', 'organic')->count(),
                'hazardous'  => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('waste_type', 'hazardous')->count(),
                'bulk'       => WasteCollectionRequest::whereDate('created_at', $dateObj)->where('waste_type', 'bulk')->count(),
            ],
            'approvals' => [
                'pending'       => WasteCollectionRequest::whereDate('created_at', $dateObj)
                    ->where('approval_status', 'pending')->count(),
                'approved'      => WasteCollectionRequest::whereDate('approval_responded_at', $dateObj)
                    ->where('approval_status', 'approved')->count(),
                'rejected'      => WasteCollectionRequest::whereDate('approval_responded_at', $dateObj)
                    ->where('approval_status', 'rejected')->count(),
                'auto_approved' => WasteCollectionRequest::whereDate('approval_responded_at', $dateObj)
                    ->where('approval_status', 'auto_approved')->count(),
            ],
        ];
    }

    private function getWeeklyReportData(string $date): array
    {
        $startDate = \Carbon\Carbon::parse($date)->startOfWeek();
        $endDate   = \Carbon\Carbon::parse($date)->endOfWeek();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'total_requests' => WasteCollectionRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
            'completed'      => WasteCollectionRequest::whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count(),
            'total_weight'   => WasteCollectionRequest::whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('waste_weight_kg'),
            'daily_breakdown' => $this->getDailyStats(),
            'approval_summary' => [
                'approved'      => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'approved')->count(),
                'rejected'      => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'rejected')->count(),
                'auto_approved' => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'auto_approved')->count(),
            ],
        ];
    }

    private function getMonthlyReportData(string $date): array
    {
        $startDate = \Carbon\Carbon::parse($date)->startOfMonth();
        $endDate   = \Carbon\Carbon::parse($date)->endOfMonth();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'total_requests' => WasteCollectionRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
            'completed'      => WasteCollectionRequest::whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count(),
            'total_weight'   => WasteCollectionRequest::whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('waste_weight_kg'),
            'top_personnel'  => $this->getTopPerformers(),
            'approval_summary' => [
                'approved'      => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'approved')->count(),
                'rejected'      => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'rejected')->count(),
                'auto_approved' => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->where('approval_status', 'auto_approved')->count(),
                'average_response_time_hours' => WasteCollectionRequest::whereBetween('approval_responded_at', [$startDate, $endDate])
                    ->whereIn('approval_status', ['approved', 'rejected'])
                    ->avg(DB::raw('TIMESTAMPDIFF(HOUR, approval_requested_at, approval_responded_at)')),
            ],
        ];
    }

    private function formatRequestMarkers($requests)
    {
        return $requests->map(function ($request) {
            return [
                'id'              => $request->id,
                'lat'             => $request->latitude,
                'lng'             => $request->longitude,
                'title'           => $request->property->property_name ?? 'Unknown',
                'address'         => $request->digital_address ?? $request->property->digital_address,
                'status'          => $request->status,
                'status_label'    => $request->status_label,
                'status_badge'    => $request->status_badge,
                'priority'        => $request->priority,
                'priority_badge'  => $request->priority_badge,
                'waste_type'      => $request->waste_type,
                'assigned_to'     => $request->assignedTo?->full_name,
                'requested_by'    => $request->requestedBy?->name,
                'created_at'      => $request->created_at->diffForHumans(),
                'approval_status' => $request->approval_status_label,
                'approval_badge'  => $request->approval_badge,
            ];
        });
    }

    private function formatPersonnelMarkers($personnel)
    {
        return $personnel->map(function ($person) {
            return [
                'id'           => $person->id,
                'lat'          => $person->latitude,
                'lng'          => $person->longitude,
                'name'         => $person->full_name,
                'role'         => $person->role,
                'status'       => $person->status,
                'status_badge' => $person->status_badge,
                'vehicle'      => $person->vehicle_number,
                'active_jobs'  => $person->active_jobs_count,
                'last_update'  => $person->last_location_update?->diffForHumans(),
            ];
        });
    }

    private function formatWorkerMarkers($workers)
    {
        return $workers->map(function ($worker) {
            return [
                'id'         => $worker->id,
                'name'       => $worker->full_name,
                'supervisor' => $worker->supervisor->full_name ?? 'Unknown',
                'status'     => $worker->status,
            ];
        });
    }

    private function updatePropertyStats($property)
    {
        $stats = $this->sanitationService->getPropertyStats($property);

        $property->update([
            'last_waste_collection'  => $stats['last_collection'],
            'total_waste_collected'  => $stats['total_weight'],
            'waste_collection_count' => $stats['total_requests'],
        ]);
    }

    private function logStatusChange($request, $status)
    {
        Log::info('Waste collection request status changed', [
            'request_id'      => $request->id,
            'old_status'      => $request->getOriginal('status'),
            'new_status'      => $status,
            'updated_by'      => auth()->id(),
            'updated_by_name' => auth()->user()->name,
            'timestamp'       => now()->toISOString(),
        ]);
    }

    private function notifyPersonnelAssignment($request, $personnel)
    {
        Log::info('Waste collection request assigned to personnel', [
            'request_id'     => $request->id,
            'personnel_id'   => $personnel->id,
            'personnel_name' => $personnel->full_name,
        ]);
    }

    // ================================================================ //
    // 📋 RESPONSE METHODS                                              //
    // ================================================================ //

    private function validationErrorResponse($request, $validator)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }
        return redirect()->back()->withErrors($validator)->withInput();
    }

    private function errorResponse($request, $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
        return redirect()->back()->with('error', $message);
    }

    // ================================================================ //
    // 📊 STATIC STATUS LOOKUPS                                        //
    // ================================================================ //

    public static function getStatuses(): array
    {
        return WasteCollectionRequest::getStatuses();
    }

    public static function getPriorities(): array
    {
        return WasteCollectionRequest::getPriorities();
    }

    public static function getWasteTypes(): array
    {
        return WasteCollectionRequest::getWasteTypes();
    }

    public static function getApprovalStatuses(): array
    {
        return WasteCollectionRequest::getApprovalStatuses();
    }
}