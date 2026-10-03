<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\PropertyCollection;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Models\LandlordInvitation;
use App\Models\TenantInvitation;
use App\Models\PropertyUnit;
use App\Services\PropertyRegistrationService;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class FieldAgentPropertyApiController extends Controller
{
    protected PropertyRegistrationService $registrationService;
    protected MultiChannelInvitationService $invitationService;
    protected SmsService $smsService;
    protected SmsTemplateService $smsTemplateService;

    public function __construct(
        PropertyRegistrationService $registrationService,
        MultiChannelInvitationService $invitationService,
        SmsService $smsService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->registrationService = $registrationService;
        $this->invitationService = $invitationService;
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
    }

    // =================================================================
    // INDEX — GET /api/v1/field-agent/properties
    // =================================================================
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->scopeQuery($user)
            ->with(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ->withCount('photos');

        $this->applyFilters($query, $request);

        $properties = $query->latest()->paginate($request->integer('per_page', 20));

        return new PropertyCollection($properties);
    }

    // =================================================================
    // STORE — POST /api/v1/field-agent/properties
    // =================================================================
    public function store(Request $request)
    {
        $user = $request->user();

        // ── Verify plan assignment ──
        if ($request->filled('registration_plan_id')) {
            $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
                ->where('plan_id', $request->registration_plan_id)
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return $this->jsonError('You are not assigned to this registration plan.', 403);
            }
        }

        $validator = $this->validateCreate($request);
        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        try {
            DB::beginTransaction();

            // ── Resolve landlord ──
            $landlordId = $request->landlord_id;
            if (empty($landlordId) && !empty($request->landlord_name)) {
                $result = $this->createOrGetLandlord($request);
                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
                $landlordId = $result['landlord_id'];
                $request->merge(['landlord_id' => $landlordId]);

                // Duplicate check
                if ($this->isDuplicateProperty($landlordId, [
                    'property_name'   => $request->property_name,
                    'street_name'     => $request->street_name,
                    'house_number'    => $request->house_number,
                    'digital_address' => $request->digital_address,
                ])) {
                    DB::rollBack();
                    return $this->jsonError('This property already exists for this landlord.', 409);
                }
            }

            // ── Custom property type ──
            $propertyTypeId = $request->property_type_id;
            $customPropertyType = null;
            $customType = PropertyType::where('slug', 'custom')->first();
            if ($customType && $propertyTypeId == $customType->id) {
                $customPropertyType = $request->custom_property_type;
                if (empty($customPropertyType)) {
                    throw new \Exception('Custom property type name is required.');
                }
            }

            // ── Build payload ──
            $propertyData = [
                'registration_plan_id'      => $request->registration_plan_id,
                'property_type_id'          => $propertyTypeId,
                'custom_property_type'      => $customPropertyType,
                'property_name'             => $request->property_name,
                'registration_pattern'      => $request->registration_pattern,
                'house_number'              => $request->house_number,
                'street_name'               => $request->street_name,
                'block_number'              => $request->block_number,
                'digital_address'           => $request->digital_address,
                'zone'                      => $request->zone ?? $request->manual_zone,
                'section'                   => $request->section ?? $request->manual_section,
                'description'               => $request->description,
                'registration_date'         => $request->registration_date,
                'status'                    => $request->status,
                'registered_by'             => $user->id,
                'landlord_id'               => $landlordId,
                'verification_status'       => 'pending',
                'is_field_agent_registered' => true,
                'field_agent_registered_at' => now(),
            ];

            $result = $this->registrationService->createProperty($propertyData, $user);
            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to create property');
            }

            $property = Property::find($result['site_allocation']['id']);

            // ── Photos ──
            if ($request->hasFile('property_photos')) {
                $this->handlePhotoUploads($request, $property);
            }

            // ── Landlord invitation (non-fatal) ──
            $invitationInfo = null;
            if ($request->input('send_invitation') == '1' && $request->has('invitation_channels')) {
                try {
                    $invitationInfo = $this->sendLandlordInvitation(
                        $property->landlord,
                        $property,
                        (array) $request->input('invitation_channels'),
                        $request->input('custom_message')
                    );
                } catch (\Exception $e) {
                    Log::warning('Landlord invitation failed but property was created', [
                        'property_id' => $property->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ── Tenant invitations (non-fatal) ──
            $tenantInfo = null;
            if ($request->input('is_rented') == '1' && $request->has('tenants')) {
                try {
                    $tenantInfo = $this->handleTenantInvitations((array) $request->input('tenants'), $property);
                } catch (\Exception $e) {
                    Log::warning('Tenant invitations failed', [
                        'property_id' => $property->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // ── Update agent assignment progress ──
            $this->updateAgentAssignmentProgress($user->id, $request->registration_plan_id);

            return (new PropertyResource($property->fresh(['landlord', 'propertyType', 'photos'])))
                ->additional([
                    'message'             => 'Property registered successfully! Awaiting verification.',
                    'verification_status' => 'pending',
                    'landlord_invitation' => $invitationInfo,
                    'tenant_invitations'  => $tenantInfo,
                ])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to create property (API)', [
                'error'    => $e->getMessage(),
                'agent_id' => $user->id,
            ]);
            return $this->jsonError('Failed to create property.', 500,
                config('app.debug') ? $e->getMessage() : null);
        }
    }

    // =================================================================
    // SHOW — GET /api/v1/field-agent/properties/{property}
    // =================================================================
    public function show(Request $request, Property $property)
    {
        if (!$this->agentCanAccess($property, $request->user())) {
            return $this->jsonError('Unauthorized to view this property.', 403);
        }

        $property->load([
            'landlord',
            'registrationPlan.continuedFromPlan',
            'registeredBy',
            'propertyType',
            'tenants',
            'photos',
            'units',
        ]);

        $allTenants = $this->getAllTenantsForProperty($property);

        $pendingAssignments = PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->with(['tenant', 'requestedBy'])
            ->get();

        $unitAssignments = PropertyUnit::where('property_id', $property->id)
            ->whereNotNull('tenant_id')
            ->with(['tenant', 'approvedBy', 'requestedBy'])
            ->get();

        return (new PropertyResource($property))->additional([
            'all_tenants'         => $allTenants,
            'pending_assignments' => $pendingAssignments,
            'unit_assignments'    => $unitAssignments,
            'counts'              => [
                'pending_assignments'  => $this->getPendingAssignmentsCount($property),
                'approved_assignments' => $this->getApprovedAssignmentsCount($property),
                'vacated_assignments'  => $this->getVacatedAssignmentsCount($property),
                'occupied_units'       => $this->getOccupiedUnitsCount($property),
            ],
        ]);
    }

    // =================================================================
    // UPDATE — PUT /api/v1/field-agent/properties/{property}
    // =================================================================
    public function update(Request $request, Property $property)
    {
        $user = $request->user();

        if (!$this->agentCanAccess($property, $user)) {
            return $this->jsonError('Unauthorized to update this property.', 403);
        }

        if ($property->verification_status === 'verified') {
            return $this->jsonError('Verified properties cannot be edited.', 422);
        }

        // Plan change → must be assigned
        if ($request->filled('registration_plan_id') && $request->registration_plan_id != $property->registration_plan_id) {
            $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
                ->where('plan_id', $request->registration_plan_id)
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return $this->jsonError('You are not assigned to this registration plan.', 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'property_name'         => 'required|string|max:255',
            'street_name'           => 'required|string|max:255',
            'house_number'          => 'nullable|string|max:50',
            'digital_address'       => 'nullable|string|max:255',
            'property_type_id'      => 'required|exists:property_types,id',
            'custom_property_type'  => 'nullable|string|max:100',
            'registration_plan_id'  => 'required|exists:registration_plans,id',
            'description'           => 'nullable|string',
            'zone'                  => 'nullable|string|max:255',
            'section'               => 'nullable|string|max:255',
            'status'                => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        try {
            DB::beginTransaction();

            $oldPlanId  = $property->registration_plan_id;
            $updateData = $validator->validated();

            $customType = PropertyType::where('slug', 'custom')->first();
            $updateData['custom_property_type'] = ($request->property_type_id == ($customType->id ?? null))
                ? $request->custom_property_type
                : null;

            $property->update($updateData);
            $this->handlePhotoManagement($request, $property);

            if ($property->registration_plan_id != $oldPlanId) {
                $this->updateAgentAssignmentProgress($user->id, $oldPlanId);
                $this->updateAgentAssignmentProgress($user->id, $property->registration_plan_id);
            }

            DB::commit();

            return (new PropertyResource(
                $property->fresh(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ))->additional(['message' => 'Property updated successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to update property (API)', [
                'error'       => $e->getMessage(),
                'property_id' => $property->id,
            ]);
            return $this->jsonError('Failed to update property.', 500);
        }
    }

    // =================================================================
    // DESTROY — DELETE /api/v1/field-agent/properties/{property}
    // =================================================================
    public function destroy(Request $request, Property $property)
    {
        $user = $request->user();

        if (!$this->agentCanAccess($property, $user)) {
            return $this->jsonError('Unauthorized to delete this property.', 403);
        }

        if ($property->verification_status === 'verified') {
            return $this->jsonError('Verified properties cannot be deleted.', 422);
        }

        try {
            DB::beginTransaction();

            $planId = $property->registration_plan_id;

            foreach ($property->photos as $photo) {
                $photo->deletePhotoFile();
            }

            $property->delete();
            $this->updateAgentAssignmentProgress($user->id, $planId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property deleted successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to delete property (API)', [
                'error'       => $e->getMessage(),
                'property_id' => $property->id,
            ]);
            return $this->jsonError('Failed to delete property.', 500);
        }
    }

    // =================================================================
    // BY PLAN — GET /api/v1/field-agent/properties/by-plan/{plan}
    // =================================================================
    public function getByRegistrationPlan(Request $request, int $planId)
    {
        $user = $request->user();

        $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->exists();

        if (!$isAssigned) {
            return $this->jsonError('You are not assigned to this registration plan.', 403);
        }

        $query = Property::with(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ->where('registration_plan_id', $planId)
            ->withCount('photos');

        $this->applyFilters($query, $request);

        $properties = $query->latest()->paginate($request->integer('per_page', 20));
        $plan = RegistrationPlan::findOrFail($planId);

        return (new PropertyCollection($properties))->additional([
            'plan' => $plan,
        ]);
    }

    // =================================================================
    // TYPE STATS — GET /api/v1/field-agent/properties/type-stats
    // =================================================================
    public function getPropertyTypeStats(Request $request)
    {
        $user = $request->user();

        $total = Property::where('registered_by', $user->id)->count();

        $stats = Property::where('registered_by', $user->id)
            ->select('property_type_id', DB::raw('count(*) as total'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($total) {
                return [
                    'type_id'    => $item->property_type_id,
                    'type_name'  => $item->propertyType?->name ?? 'Unknown',
                    'type_slug'  => $item->propertyType?->slug ?? 'unknown',
                    'total'      => $item->total,
                    'percentage' => $total > 0 ? round(($item->total / $total) * 100, 1) : 0,
                ];
            });

        $customStats = Property::where('registered_by', $user->id)
            ->whereNotNull('custom_property_type')
            ->select('custom_property_type', DB::raw('count(*) as total'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($total) {
                return [
                    'type_id'    => null,
                    'type_name'  => $item->custom_property_type,
                    'type_slug'  => Str::slug($item->custom_property_type),
                    'total'      => $item->total,
                    'percentage' => $total > 0 ? round(($item->total / $total) * 100, 1) : 0,
                    'is_custom'  => true,
                ];
            });

        return response()->json([
            'success'          => true,
            'statistics'       => $stats->concat($customStats)->values(),
            'total_properties' => $total,
        ]);
    }

    // =================================================================
    // BY TYPE — GET /api/v1/field-agent/properties/by-type/{slug}
    // =================================================================
    public function getByPropertyType(Request $request, string $typeSlug)
    {
        $user = $request->user();

        $isCustom = !PropertyType::where('slug', $typeSlug)->exists();

        $query = Property::with(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ->where('registered_by', $user->id)
            ->withCount('photos');

        if ($isCustom) {
            $query->where('custom_property_type', str_replace('-', ' ', $typeSlug));
            $typeName = str_replace('-', ' ', $typeSlug);
        } else {
            $propertyType = PropertyType::where('slug', $typeSlug)->firstOrFail();
            $query->where('property_type_id', $propertyType->id);
            $typeName = $propertyType->name;
        }

        $this->applyFilters($query, $request);
        $properties = $query->latest()->paginate($request->integer('per_page', 20));

        return (new PropertyCollection($properties))->additional([
            'type_name' => $typeName,
            'type_slug' => $typeSlug,
        ]);
    }

    // =================================================================
    // MY PROPERTIES — GET /api/v1/field-agent/my-properties (alias)
    // =================================================================
    public function myProperties(Request $request)
    {
        return $this->index($request);
    }

    // =================================================================
    // PERFORMANCE — GET /api/v1/field-agent/performance
    // =================================================================
    public function performance(Request $request)
    {
        $user = $request->user();
        $selectedPlanId = $request->input('registration_plan_id');

        // ── Unfiltered assignments for card counts ──
        $allAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->with('registrationPlan')->get();

        $activeAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->with('registrationPlan')->get();

        $completedAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_completed', true)
            ->with('registrationPlan')->get();

        // ── Filtered property query ──
        $propertyQuery   = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $totalProperties = $propertyQuery->count();

        // ── Filtered assignments ──
        $allAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id);
        if ($selectedPlanId) $allAssignmentsQuery->where('plan_id', $selectedPlanId);
        $allAssignments = $allAssignmentsQuery->with('registrationPlan')->get();

        $activeAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true);
        if ($selectedPlanId) $activeAssignmentsQuery->where('plan_id', $selectedPlanId);
        $activeAssignments = $activeAssignmentsQuery->with('registrationPlan')->get();

        $completedAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_completed', true);
        if ($selectedPlanId) $completedAssignmentsQuery->where('plan_id', $selectedPlanId);
        $completedAssignments = $completedAssignmentsQuery->with('registrationPlan')->get();

        // ── Stats ──
        $totalPlansAssigned = $allAssignmentsTotal->count();
        $activePlans        = $activeAssignmentsTotal->count();
        $completedPlans     = $completedAssignmentsTotal->count();
        $completionRate     = $totalPlansAssigned > 0
            ? round(($completedPlans / $totalPlansAssigned) * 100, 1) : 0;
        $averagePerPlan     = $activePlans > 0
            ? round($totalProperties / $activePlans, 1) : 0;

        // ── Property type stats ──
        $propertyTypeStatsQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $propertyTypeStats = $propertyTypeStatsQuery
            ->select('property_type_id', DB::raw('count(*) as total'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object)[
                    'type_name'  => $item->propertyType?->name ?? 'Unknown',
                    'total'      => $item->total,
                    'percentage' => $totalProperties > 0
                        ? round(($item->total / $totalProperties) * 100, 1) : 0,
                ];
            });

        $customTypeStatsQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $customTypeStats = $customTypeStatsQuery
            ->whereNotNull('custom_property_type')
            ->select('custom_property_type', DB::raw('count(*) as total'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object)[
                    'type_name'  => $item->custom_property_type . ' (Custom)',
                    'total'      => $item->total,
                    'percentage' => $totalProperties > 0
                        ? round(($item->total / $totalProperties) * 100, 1) : 0,
                    'is_custom'  => true,
                ];
            });

        $allPropertyTypeStats = $propertyTypeStats->concat($customTypeStats);

        // ── Monthly data ──
        $monthlyQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $monthlyData = $monthlyQuery
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($item) {
                return (object)[
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count,
                ];
            })
            ->reverse()->values();

        // ── Recent properties ──
        $recentQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $recentProperties = $recentQuery
            ->with(['propertyType', 'registrationPlan', 'landlord'])
            ->latest()->limit(10)->get();

        // ── Achievements ──
        $achievements = collect();
        if ($totalProperties >= 1) {
            $achievements->push((object)[
                'icon' => 'fa-house', 'title' => 'First Property Registered',
                'description' => 'Registered your first property', 'earned_at' => now(),
            ]);
        }
        if ($totalProperties >= 10) {
            $achievements->push((object)[
                'icon' => 'fa-star', 'title' => 'Double Digits',
                'description' => 'Registered 10 properties', 'earned_at' => now(),
            ]);
        }
        if ($totalProperties >= 50) {
            $achievements->push((object)[
                'icon' => 'fa-trophy', 'title' => 'Top Performer',
                'description' => 'Registered 50 properties', 'earned_at' => now(),
            ]);
        }
        if ($completedPlans >= 1) {
            $achievements->push((object)[
                'icon' => 'fa-check-circle', 'title' => 'Plan Completed',
                'description' => 'Completed your first registration plan', 'earned_at' => now(),
            ]);
        }

        // ── Selected plan details ──
        $selectedPlan = null;
        $planDetails  = null;
        if ($selectedPlanId) {
            $selectedPlan = RegistrationPlan::find($selectedPlanId);
            if ($selectedPlan) {
                $totalInPlan = Property::where('registered_by', $user->id)
                    ->where('registration_plan_id', $selectedPlanId)->count();
                $totalPlanProperties = Property::where('registration_plan_id', $selectedPlanId)->count();
                $estimatedTotal = $selectedPlan->estimated_houses ?? $selectedPlan->total_properties ?? 0;

                $planDetails = (object)[
                    'id'                    => $selectedPlan->id,
                    'name'                  => $selectedPlan->name
                        ?? $selectedPlan->zone . ($selectedPlan->section ? ' - ' . $selectedPlan->section : ''),
                    'zone'                  => $selectedPlan->zone,
                    'section'               => $selectedPlan->section,
                    'total_properties'      => $totalInPlan,
                    'total_plan_properties' => $totalPlanProperties,
                    'estimated_total'       => $estimatedTotal,
                    'completion_percentage' => $estimatedTotal > 0
                        ? round(($totalInPlan / $estimatedTotal) * 100, 1) : 0,
                    'status'                => $selectedPlan->status,
                    'created_at'            => $selectedPlan->created_at,
                    'start_date'            => $selectedPlan->registration_start_date,
                    'end_date'              => $selectedPlan->registration_end_date,
                ];
            }
        }

        // ── Available plans ──
        $availablePlans = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->with('registrationPlan')
            ->get()
            ->map(fn($a) => $a->registrationPlan)
            ->filter()->unique('id')->values();

        // ── Plan performance table ──
        $planPerformance = $activeAssignmentsTotal->map(function ($assignment) use ($user) {
            $plan = $assignment->registrationPlan;
            if (!$plan) return null;

            $total = $plan->estimated_houses ?? $plan->total_properties ?? 0;
            $registeredCount = Property::where('registered_by', $user->id)
                ->where('registration_plan_id', $plan->id)->count();

            return (object)[
                'plan_name'             => $plan->name
                    ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                'total_properties'      => $total,
                'registered_count'      => $registeredCount,
                'completion_percentage' => $total > 0
                    ? round(($registeredCount / $total) * 100, 1) : 0,
                'days_left'             => $plan->registration_end_date
                    ? now()->diffInDays($plan->registration_end_date, false) : null,
            ];
        })->filter()->values();

        // ── Additional metrics ──
        $verificationQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $verificationStats = [
            'verified' => (clone $verificationQuery)->where('verification_status', 'verified')->count(),
            'pending'  => (clone $verificationQuery)->where('verification_status', 'pending')->count(),
            'rejected' => (clone $verificationQuery)->where('verification_status', 'rejected')->count(),
        ];

        $monthQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $thisMonthCount = (clone $monthQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = now()->subMonth();
        $lastMonthQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $lastMonthCount = (clone $lastMonthQuery)
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();

        $monthlyChange = $lastMonthCount > 0
            ? round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1)
            : ($thisMonthCount > 0 ? 100 : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_properties'      => $totalProperties,
                    'active_plans'          => $activePlans,
                    'completed_plans'       => $completedPlans,
                    'total_plans_assigned'  => $totalPlansAssigned,
                    'completion_rate'       => $completionRate,
                    'average_per_plan'      => $averagePerPlan,
                    'this_month_count'      => $thisMonthCount,
                    'last_month_count'      => $lastMonthCount,
                    'monthly_change'        => $monthlyChange,
                ],
                'verification_stats'  => $verificationStats,
                'property_type_stats' => $allPropertyTypeStats->values(),
                'monthly_data'        => $monthlyData,
                'recent_properties'   => $recentProperties,
                'achievements'        => $achievements,
                'selected_plan'       => $selectedPlan,
                'selected_plan_id'    => $selectedPlanId,
                'plan_details'        => $planDetails,
                'available_plans'     => $availablePlans,
                'plan_performance'    => $planPerformance,
            ],
        ]);
    }

    // =================================================================
    // STATISTICS — GET /api/v1/field-agent/statistics
    // =================================================================
    public function statistics(Request $request)
    {
        $user = $request->user();

        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        $query = $this->getAgentPropertyQuery($user);
        if ($fromDate) $query->whereDate('created_at', '>=', $fromDate);
        if ($toDate)   $query->whereDate('created_at', '<=', $toDate);

        $totalProperties = $query->count();

        // This month
        $thisMonthCount = Property::where('registered_by', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $thisMonthPlanCount = Property::whereHas('registrationPlan', function ($planQuery) use ($user) {
            $planQuery->whereHas('planAssignments', function ($aq) use ($user) {
                $aq->where('agent_id', $user->id)->where('is_active', true);
            });
        })
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

        $totalThisMonth = $thisMonthCount + $thisMonthPlanCount;

        // Verification breakdown
        $verifiedCount = (clone $query)->where('verification_status', 'verified')->count();
        $pendingCount  = (clone $query)->where('verification_status', 'pending')->count();
        $rejectedCount = (clone $query)->where('verification_status', 'rejected')->count();

        // Assignments
        $allAssignments = PlanAgentAssignment::where('agent_id', $user->id)
            ->with('registrationPlan')->get();

        $activeAssignments = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->with('registrationPlan')->get()
            ->filter(function ($a) {
                $p = $a->registrationPlan;
                return $p && in_array($p->status, ['assigned', 'in_progress', 'active']);
            });

        $completedAssignments = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_completed', true)
            ->with('registrationPlan')->get()
            ->filter(function ($a) {
                $p = $a->registrationPlan;
                return $p && $p->status === 'completed';
            });

        $activePlans        = $activeAssignments->count();
        $completedPlans     = $completedAssignments->count();
        $totalPlansAssigned = $allAssignments->count();

        $completionRate = $totalPlansAssigned > 0
            ? round(($completedPlans / $totalPlansAssigned) * 100, 1) : 0;
        $averagePerPlan = $activePlans > 0
            ? round($totalProperties / $activePlans, 1) : 0;

        // Monthly trend (last 12 months)
        $monthlyTrendQuery = $this->getAgentPropertyQuery($user);
        $monthlyTrend = $monthlyTrendQuery
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return (object)[
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count,
                ];
            });

        // Property type stats
        $propertyTypeStatsQuery = clone $query;
        $propertyTypeStats = $propertyTypeStatsQuery
            ->select('property_type_id', DB::raw('count(*) as count'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object)[
                    'type_name'  => $item->propertyType?->name ?? 'Unknown',
                    'count'      => $item->count,
                    'percentage' => $totalProperties > 0
                        ? round(($item->count / $totalProperties) * 100, 1) : 0,
                ];
            });

        $customTypeStatsQuery = clone $query;
        $customTypeStats = $customTypeStatsQuery
            ->whereNotNull('custom_property_type')
            ->select('custom_property_type', DB::raw('count(*) as count'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object)[
                    'type_name'  => $item->custom_property_type . ' (Custom)',
                    'count'      => $item->count,
                    'percentage' => $totalProperties > 0
                        ? round(($item->count / $totalProperties) * 100, 1) : 0,
                    'is_custom'  => true,
                ];
            });

        $allPropertyTypeStats = $propertyTypeStats->concat($customTypeStats);

        // Plan performance
        $planPerformance = $activeAssignments->map(function ($assignment) use ($user) {
            $plan = $assignment->registrationPlan;
            if (!$plan) return null;

            $total = $plan->estimated_houses ?? $plan->total_properties ?? 0;
            $registeredCount = Property::where('registered_by', $user->id)
                ->where('registration_plan_id', $plan->id)->count();
            $planTotalRegistered = Property::where('registration_plan_id', $plan->id)->count();

            return (object)[
                'plan_name'             => $plan->name
                    ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                'total_properties'      => $total,
                'registered_count'      => $registeredCount,
                'plan_total_registered' => $planTotalRegistered,
                'completion_percentage' => $total > 0
                    ? round(($registeredCount / $total) * 100, 1) : 0,
                'days_left'             => $plan->registration_end_date
                    ? now()->diffInDays($plan->registration_end_date, false) : null,
                'status'                => $plan->status,
            ];
        })->filter()->values();

        // Status distribution
        $statusQuery = clone $query;
        $statusDistribution = $statusQuery
            ->select('verification_status as status', DB::raw('count(*) as count'))
            ->groupBy('verification_status')
            ->get()
            ->map(fn($item) => (object)[
                'status' => ucfirst($item->status ?? 'Unknown'),
                'count'  => $item->count,
            ]);

        // Top landlords
        $topLandlordsQuery = clone $query;
        $topLandlords = $topLandlordsQuery
            ->select('landlord_id', DB::raw('count(*) as property_count'))
            ->with('landlord')
            ->groupBy('landlord_id')
            ->orderBy('property_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($item) => (object)[
                'name'           => $item->landlord?->name ?? 'Unknown',
                'property_count' => $item->property_count,
            ]);

        // Recent activity
        $recentQuery = clone $query;
        $recentActivity = $recentQuery
            ->with(['propertyType', 'landlord', 'registrationPlan'])
            ->latest()->limit(10)->get()
            ->map(function ($property) {
                return (object)[
                    'id'                   => $property->id,
                    'property_name'        => $property->property_name,
                    'registration_pattern' => $property->registration_pattern,
                    'status'               => $property->status,
                    'verification_status'  => $property->verification_status,
                    'created_at'           => $property->created_at,
                    'property_type'        => $property->propertyType?->name
                        ?? $property->custom_property_type ?? 'Unknown',
                    'landlord_name'        => $property->landlord?->name ?? 'Unknown',
                    'plan_name'            => $property->registrationPlan?->zone ?? 'N/A',
                ];
            });

        // Extra metrics
        $thisWeekQuery = clone $query;
        $thisWeekCount = (clone $thisWeekQuery)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $lastWeekQuery = clone $query;
        $lastWeekCount = (clone $lastWeekQuery)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();

        $weeklyChange = $lastWeekCount > 0
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
            : ($thisWeekCount > 0 ? 100 : 0);

        $photosQuery = clone $query;
        $propertiesWithPhotos = (clone $photosQuery)->has('photos')->count();
        $photoCoverage = $totalProperties > 0
            ? round(($propertiesWithPhotos / $totalProperties) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'core' => [
                    'total_properties'    => $totalProperties,
                    'active_plans'        => $activePlans,
                    'completed_plans'     => $completedPlans,
                    'total_plans_assigned'=> $totalPlansAssigned,
                    'this_month_count'    => $totalThisMonth,
                    'verified_count'      => $verifiedCount,
                    'pending_count'       => $pendingCount,
                    'rejected_count'      => $rejectedCount,
                    'completion_rate'     => $completionRate,
                    'average_per_plan'    => $averagePerPlan,
                ],
                'charts' => [
                    'monthly_trend'       => $monthlyTrend,
                    'property_type_stats' => $allPropertyTypeStats->values(),
                    'status_distribution' => $statusDistribution,
                ],
                'lists' => [
                    'plan_performance' => $planPerformance,
                    'top_landlords'    => $topLandlords,
                    'recent_activity'  => $recentActivity,
                ],
                'metrics' => [
                    'this_week_count'        => $thisWeekCount,
                    'last_week_count'        => $lastWeekCount,
                    'weekly_change'          => $weeklyChange,
                    'properties_with_photos' => $propertiesWithPhotos,
                    'photo_coverage'         => $photoCoverage,
                ],
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                ],
            ],
        ]);
    }

    // =================================================================
    // UNIT COUNT HELPERS — GET /api/v1/field-agent/properties/{property}/counts
    // =================================================================
    public function getAssignmentCounts(Request $request, Property $property)
    {
        if (!$this->agentCanAccess($property, $request->user())) {
            return $this->jsonError('Unauthorized to view this property.', 403);
        }

        return response()->json([
            'success' => true,
            'counts'  => [
                'pending_assignments'  => $this->getPendingAssignmentsCount($property),
                'approved_assignments' => $this->getApprovedAssignmentsCount($property),
                'vacated_assignments'  => $this->getVacatedAssignmentsCount($property),
                'occupied_units'       => $this->getOccupiedUnitsCount($property),
            ],
        ]);
    }

    // =================================================================
    // SCOPE HELPERS
    // =================================================================

    /**
     * Properties visible to this agent:
     *   • registered by them
     *   • OR inside a plan they're actively assigned to
     */
    private function scopeQuery($user)
    {
        return Property::query()
            ->where(function ($q) use ($user) {
                $q->where('registered_by', $user->id)
                  ->orWhereHas('registrationPlan', function ($planQuery) use ($user) {
                      $planQuery->whereHas('planAssignments', function ($a) use ($user) {
                          $a->where('agent_id', $user->id)
                            ->where('is_active', true);
                      });
                  });
            });
    }

    /**
     * Shared query builder used by performance/statistics.
     */
    private function getAgentPropertyQuery($user, $selectedPlanId = null)
    {
        $query = Property::query()
            ->where(function ($q) use ($user) {
                $q->where('registered_by', $user->id)
                  ->orWhereHas('registrationPlan', function ($planQuery) use ($user) {
                      $planQuery->whereHas('planAssignments', function ($a) use ($user) {
                          $a->where('agent_id', $user->id)->where('is_active', true);
                      });
                  });
            });

        if ($selectedPlanId) {
            $query->where('registration_plan_id', $selectedPlanId);
        }

        return $query;
    }

    /**
     * Single-record access check.
     */
    private function agentCanAccess(Property $property, $user): bool
    {
        if ($property->registered_by === $user->id) {
            return true;
        }

        if ($property->registrationPlan) {
            return $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();
        }

        return false;
    }

    /**
     * Alias for agentCanAccess — matches web controller naming.
     */
    private function canViewProperty(Property $property, $user): bool
    {
        return $this->agentCanAccess($property, $user);
    }

    // =================================================================
    // FILTERS / VALIDATION
    // =================================================================

    private function applyFilters($query, Request $request): void
    {
        $map = [
            'street_name'          => 'like',
            'zone'                 => 'like',
            'registration_plan_id' => 'exact',
            'property_type_id'     => 'exact',
            'verification_status'  => 'exact',
        ];

        foreach ($map as $field => $type) {
            if ($request->filled($field)) {
                $type === 'like'
                    ? $query->where($field, 'like', "%{$request->$field}%")
                    : $query->where($field, $request->$field);
            }
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            $request->has_digital_address === '1'
                ? $query->hasDigitalAddress()
                : $query->missingDigitalAddress();
        }
    }

    private function validateCreate(Request $request)
    {
        $rules = [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'registration_pattern' => 'required|string',
            'property_type_id'     => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name'        => 'required|string|max:255',
            'house_number'         => 'nullable|string|max:50',
            'street_name'          => 'required|string|max:255',
            'block_number'         => 'nullable|string|max:50',
            'digital_address'      => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'registration_date'    => 'required|date',
            'status'               => 'nullable|string|in:active,inactive,under_maintenance,vacant',
            'zone'                 => 'nullable|string|max:255',
            'section'              => 'nullable|string|max:255',
            'manual_zone'          => 'nullable|string|max:255',
            'manual_section'       => 'nullable|string|max:255',
            'property_photos'      => 'nullable|array|max:10',
            'property_photos.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'send_invitation'      => 'nullable|in:0,1',
            'invitation_channels'  => 'nullable|array',
            'invitation_channels.*'=> 'string|in:sms,email,whatsapp',
            'is_rented'            => 'nullable|in:0,1',
            'tenants'              => 'nullable|array',
            'tenants.*.name'       => 'required_with:tenants|string|max:255',
            'tenants.*.phone'      => 'required_with:tenants|string|max:20',
            'tenants.*.email'      => 'nullable|email|max:255',
            'tenants.*.gender'     => 'nullable|string|in:male,female,other',
            'tenants.*.channels'   => 'nullable|array',
        ];

        if (empty($request->landlord_id)) {
            $rules['landlord_name']     = 'required|string|max:255';
            $rules['landlord_phones']   = 'required|array|min:1';
            $rules['landlord_phones.*'] = 'string|max:20';
            $rules['landlord_email']    = 'nullable|email|max:255';
        } else {
            $rules['landlord_id'] = 'required|exists:users,id';
        }

        return Validator::make($request->all(), $rules);
    }

    // =================================================================
    // LANDLORD / TENANT HELPERS
    // =================================================================

    /**
     * Create or fetch landlord — matches web controller behaviour,
     * including extra phone numbers and multi-format phone lookup.
     */
    private function createOrGetLandlord(Request $request): array
    {
        $phone = $request->landlord_phones[0] ?? null;
        if (!$phone) {
            return ['success' => false, 'message' => 'Phone number is required'];
        }

        $existing = $this->findUserByPhone($phone);
        if ($existing) {
            if (!$existing->isLandlord()) {
                $existing->assignRole('landlord', [
                    'assigned_by'       => auth()->id(),
                    'assignment_reason' => 'Auto-assigned via field agent (API)',
                    'assigned_at'       => now(),
                ]);
            }

            // Additional phone numbers
            if (count($request->landlord_phones) > 1) {
                $primaryStd = $this->standardizePhoneNumber($phone);
                foreach (array_slice($request->landlord_phones, 1) as $additionalPhone) {
                    if (empty($additionalPhone)) continue;
                    $std = $this->standardizePhoneNumber($additionalPhone);
                    if ($std === $primaryStd) continue;

                    if (!$existing->phones()->where('phone_number', $std)->exists()) {
                        $existing->phones()->create([
                            'phone_number' => $std,
                            'is_active'    => true,
                            'is_primary'   => false,
                            'verified_at'  => now(),
                        ]);
                    }
                }
            }

            return ['success' => true, 'landlord_id' => $existing->id, 'was_existing' => true];
        }

        $standardized = $this->standardizePhoneNumber($phone);
        $tempPassword = Str::random(16);

        $landlord = User::create([
            'name'               => $request->landlord_name,
            'phone'              => $standardized,
            'email'              => $request->landlord_email,
            'type'               => User::TYPE_LANDLORD,
            'status'             => User::STATUS_ACTIVE,
            'created_by'         => auth()->id(),
            'email_verified_at'  => null,
            'phone_verified_at'  => now(),
            'password'           => Hash::make($tempPassword),
        ]);

        $landlord->assignRole('landlord', [
            'assigned_by'       => auth()->id(),
            'assignment_reason' => 'Created via field agent (API)',
            'assigned_at'       => now(),
        ]);

        // Additional phone numbers
        if (count($request->landlord_phones) > 1) {
            $primaryStd = $standardized;
            foreach (array_slice($request->landlord_phones, 1) as $additionalPhone) {
                if (empty($additionalPhone)) continue;
                $std = $this->standardizePhoneNumber($additionalPhone);
                if ($std === $primaryStd) continue;

                $landlord->phones()->create([
                    'phone_number' => $std,
                    'is_active'    => true,
                    'is_primary'   => false,
                    'verified_at'  => now(),
                ]);
            }
        }

        return [
            'success'      => true,
            'landlord_id'  => $landlord->id,
            'was_existing' => false,
            'temporary_password' => $tempPassword,
        ];
    }

    private function createOrGetTenant(array $tenantData, Property $property)
    {
        $phone = $this->standardizePhoneNumber($tenantData['phone']);

        $tenant = $this->findUserByPhone($phone);

        if (!$tenant) {
            $tenant = User::create([
                'name'       => $tenantData['name'],
                'phone'      => $phone,
                'email'      => $tenantData['email'] ?? null,
                'type'       => User::TYPE_TENANT,
                'status'     => User::STATUS_ACTIVE,
                'created_by' => auth()->id(),
                'password'   => Hash::make(Str::random(16)),
            ]);
            $tenant->assignRole('tenant', [
                'assigned_by'       => auth()->id(),
                'assignment_reason' => 'Created during property registration (API)',
                'assigned_at'       => now(),
            ]);
        }

        if (!$property->tenants()->where('user_id', $tenant->id)->exists()) {
            $property->tenants()->attach($tenant->id, [
                'added_by'   => auth()->id(),
                'added_at'   => now(),
                'status'     => 'active',
                'start_date' => now()->toDateString(),
                'notes'      => 'Added by field agent during property registration',
            ]);
        }

        return $tenant;
    }

    // =================================================================
    // INVITATIONS
    // =================================================================

    private function sendLandlordInvitation($landlord, $property, array $channels, $customMessage = null): array
    {
        $available = $this->invitationService->getAvailableChannels($landlord);
        $channels = array_intersect($channels, $available);

        if (empty($channels)) {
            return ['success' => false, 'message' => 'No available communication channels for this landlord.'];
        }

        $invitation = LandlordInvitation::create([
            'property_id'     => $property->id,
            'landlord_id'     => $landlord->id,
            'invited_by'      => auth()->id(),
            'channels'        => $channels,
            'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
            'custom_message'  => $customMessage,
            'expires_at'      => now()->addDays(7),
            'metadata'        => ['is_field_agent' => true, 'agent_id' => auth()->id()],
        ]);

        $successChannels = [];
        foreach ($channels as $channel) {
            try {
                $r = $this->sendInvitationViaChannel($landlord, $property, $invitation, $channel);
                if (!empty($r['success'])) $successChannels[] = $channel;
            } catch (\Exception $e) {
                Log::error("Failed invitation channel {$channel}: " . $e->getMessage());
            }
        }

        if (!empty($successChannels)) {
            $invitation->markAsSent($successChannels);
            return [
                'success'             => true,
                'message'             => 'Invitation sent via ' . implode(', ', $successChannels),
                'invitation_url'      => $invitation->getInvitationUrl(),
                'channels_successful' => $successChannels,
            ];
        }

        $invitation->markAsFailed('All channels failed');
        return ['success' => false, 'message' => 'Failed to send invitation via all channels'];
    }

    private function sendInvitationViaChannel($landlord, $property, $invitation, string $channel): array
    {
        return match ($channel) {
            'email'    => $this->sendEmailInvitation($landlord, $property, $invitation),
            'sms'      => $this->sendSmsInvitation($landlord, $property, $invitation),
            'whatsapp' => $this->sendWhatsAppInvitation($landlord, $property, $invitation),
            default    => ['success' => false, 'message' => "Unknown channel: {$channel}"],
        };
    }

    private function sendEmailInvitation($landlord, $property, $invitation): array
    {
        if (empty($landlord->email) || !filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Landlord does not have a valid email address'];
        }

        try {
            \Mail::to($landlord->email)
                ->send(new \App\Mail\LandlordInvitationMail($landlord, $property, $invitation));
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendSmsInvitation($landlord, $property, $invitation): array
    {
        if (empty($landlord->phone) || !$this->isValidPhoneNumber($landlord->phone)) {
            return ['success' => false, 'message' => 'Landlord does not have a valid phone number'];
        }

        try {
            $status = $this->smsService->getSystemStatus();
            if (!$status['system_ready'] || !$status['can_send_sms']) {
                return ['success' => false, 'message' => 'SMS service is not ready'];
            }

            $message = $this->smsTemplateService->generateLandlordInvitationMessage(
                $landlord,
                $property,
                $invitation->getInvitationUrl(),
                $invitation->token
            );

            $provider = $this->smsService->getDefaultProvider() ?? 'arkesel';
            $r = $this->smsService->sendSMS($provider, $landlord->phone, $message, [
                'category'      => 'landlord_invitation',
                'invitation_id' => $invitation->id,
                'property_id'   => $property->id,
                'is_field_agent'=> true,
            ]);

            return [
                'success'  => $r['success'] ?? false,
                'message'  => $r['message'] ?? 'SMS sending completed',
                'provider' => $r['provider'] ?? $provider,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendWhatsAppInvitation($landlord, $property, $invitation): array
    {
        if (empty($landlord->phone)) {
            return ['success' => false, 'message' => 'Landlord does not have a phone number'];
        }

        $message = $this->generateWhatsAppMessage($landlord, $property, $invitation);

        if (class_exists(\App\Services\WhatsAppService::class)) {
            try {
                $r = app(\App\Services\WhatsAppService::class)->send($landlord->phone, $message);
                return [
                    'success' => $r['success'] ?? false,
                    'message' => $r['message'] ?? ($r['success'] ? 'WhatsApp sent' : 'Failed'),
                ];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // Fallback to SMS
        return $this->sendSmsInvitation($landlord, $property, $invitation);
    }

    private function generateWhatsAppMessage($landlord, $property, $invitation): string
    {
        $propertyType = $property->propertyType->name
            ?? ($property->custom_property_type ?? 'Property');

        return "🏠 *Property Registration*\n\n" .
               "Hello {$landlord->name},\n\n" .
               "Your *{$propertyType}* has been registered successfully.\n\n" .
               "📍 *Location:* {$property->street_name}, " . ($property->zone ?? 'N/A') . "\n" .
               "🔢 *Registration ID:* {$property->registration_pattern}\n" .
               "📅 *Registered:* " . ($property->registration_date?->format('M j, Y') ?? 'Today') . "\n\n" .
               "Complete registration here:\n{$invitation->getInvitationUrl()}\n\n" .
               "⏰ *Link expires:* " . $invitation->expires_at->format('F j, Y') . "\n\n" .
               "Thank you!";
    }

    private function handleTenantInvitations(array $tenants, Property $property): array
    {
        $ok = 0;
        $errors = [];

        foreach ($tenants as $data) {
            try {
                $channels = $data['channels'] ?? ['sms'];
                if (is_string($channels)) {
                    $channels = json_decode($channels, true) ?? [$channels];
                }

                $tenant = $this->createOrGetTenant($data, $property);

                $invitation = TenantInvitation::create([
                    'property_id'     => $property->id,
                    'tenant_id'       => $tenant->id,
                    'invited_by'      => auth()->id(),
                    'channels'        => $channels,
                    'invitation_type' => 'property_access',
                    'expires_at'      => now()->addDays(7),
                    'metadata'        => ['is_field_agent' => true, 'agent_id' => auth()->id()],
                ]);

                foreach ($channels as $channel) {
                    try {
                        $this->sendTenantInvitationViaChannel($tenant, $property, $invitation, $channel);
                    } catch (\Exception $e) {
                        Log::warning("Tenant invitation via {$channel} failed: " . $e->getMessage());
                    }
                }
                $ok++;
            } catch (\Exception $e) {
                $errors[] = ['tenant' => $data['name'] ?? 'Unknown', 'error' => $e->getMessage()];
            }
        }

        return ['success' => $ok > 0, 'success_count' => $ok, 'total' => count($tenants), 'errors' => $errors];
    }

    private function sendTenantInvitationViaChannel($tenant, $property, $invitation, string $channel): void
    {
        switch ($channel) {
            case 'email':
                if (!empty($tenant->email)) {
                    \Mail::to($tenant->email)->send(
                        new \App\Mail\TenantInvitationMail($tenant, $property, $invitation)
                    );
                }
                break;
            case 'sms':
                if (!empty($tenant->phone)) {
                    $msg = "Hello {$tenant->name}, you have been invited to access your property portal for {$property->property_name}. Link: " . $invitation->getInvitationUrl();
                    $this->smsService->sendSMS('arkesel', $tenant->phone, $msg, ['category' => 'tenant_invitation']);
                }
                break;
            case 'whatsapp':
                if (!empty($tenant->phone) && class_exists(\App\Services\WhatsAppService::class)) {
                    $msg = "🏠 Property Portal Access\n\nHello {$tenant->name}, you have been invited to access your property portal for {$property->property_name}. Link: " . $invitation->getInvitationUrl();
                    app(\App\Services\WhatsAppService::class)->send($tenant->phone, $msg);
                }
                break;
        }
    }

    // =================================================================
    // PHONE HELPERS
    // =================================================================

    private function standardizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        if (preg_match('/^0/', $cleaned)) {
            $cleaned = '+233' . substr($cleaned, 1);
        }
        if (preg_match('/^233/', $cleaned) && !preg_match('/^\+/', $cleaned)) {
            $cleaned = '+' . $cleaned;
        }
        return preg_replace('/[^0-9+]/', '', $cleaned);
    }

    private function findUserByPhone(string $phone): ?User
    {
        $std = $this->standardizePhoneNumber($phone);
        $formats = array_unique(array_filter([
            $std,
            ltrim($std, '+'),
            preg_replace('/^\+233/', '0', $std),
            preg_replace('/^0/', '', $std),
            preg_replace('/[^0-9]/', '', $std),
        ]));

        foreach ($formats as $f) {
            $u = User::where('phone', $f)->first()
                ?? User::whereHas('phones', fn($q) => $q->where('phone_number', $f))->first();
            if ($u) return $u;
        }

        return null;
    }

    private function isValidPhoneNumber(string $phone): bool
    {
        $std = $this->standardizePhoneNumber($phone);
        return (bool) preg_match('/^(\+233|0)[0-9]{9}$/', $std)
            || (bool) preg_match('/^(\+233|0)[0-9]{9}$/', $phone);
    }

    // =================================================================
    // PROPERTY HELPERS
    // =================================================================

    private function getAllTenantsForProperty(Property $property)
    {
        $direct = $property->tenants;

        $approvedIds = PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $pendingIds = PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $approved = $approvedIds->isNotEmpty() ? User::whereIn('id', $approvedIds)->get() : collect();
        $pending  = $pendingIds->isNotEmpty()  ? User::whereIn('id', $pendingIds)->get()  : collect();

        return $direct->merge($approved)->merge($pending)->unique('id');
    }

    private function isDuplicateProperty($landlordId, array $data): bool
    {
        if (!$landlordId) return false;

        if (!empty($data['digital_address']) &&
            Property::where('digital_address', $data['digital_address'])
                ->where('landlord_id', $landlordId)->exists()) {
            return true;
        }

        if (!empty($data['property_name']) &&
            Property::where('property_name', $data['property_name'])
                ->where('landlord_id', $landlordId)->exists()) {
            return true;
        }

        return false;
    }

    private function handlePhotoUploads(Request $request, Property $property): void
    {
        if (!$request->hasFile('property_photos')) return;

        foreach ($request->file('property_photos') as $file) {
            if ($file && $file->isValid()) {
                $isPrimary = $property->photos()->count() === 0;
                $property->addPhoto($file, $isPrimary);
            }
        }
    }

    private function handlePhotoManagement(Request $request, Property $property): void
    {
        if ($request->filled('delete_photos')) {
            foreach (explode(',', $request->delete_photos) as $id) {
                if (is_numeric($id)) $property->deletePhoto((int) $id);
            }
        }

        if ($request->filled('primary_photo_id')) {
            $property->setPrimaryPhoto($request->primary_photo_id);
        }

        if ($request->hasFile('property_photos')) {
            $current = $property->photos()->count();
            $slots = max(0, 10 - $current);

            foreach (array_slice($request->file('property_photos'), 0, $slots) as $file) {
                $isPrimary = $property->photos()->count() === 0;
                $property->addPhoto($file, $isPrimary);
            }
        }
    }

    private function updateAgentAssignmentProgress($agentId, $planId): void
    {
        if (!$planId) return;

        $assignment = PlanAgentAssignment::where('agent_id', $agentId)
            ->where('plan_id', $planId)
            ->first();

        if (!$assignment) return;

        $registeredCount = Property::where('registered_by', $agentId)
            ->where('registration_plan_id', $planId)
            ->count();

        $plan = RegistrationPlan::find($planId);
        $total = $plan->estimated_houses ?? 0;

        $assignment->properties_registered = $registeredCount;
        $assignment->completion_percentage = $total > 0
            ? round(($registeredCount / $total) * 100, 1)
            : 0;

        if ($total > 0 && $registeredCount >= $total) {
            $assignment->is_completed = true;
            $assignment->completed_at = now();
        }

        $assignment->save();
    }

    // =================================================================
    // UNIT COUNT HELPERS
    // =================================================================

    public function getPendingAssignmentsCount(Property $property): int
    {
        return PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->count();
    }

    public function getApprovedAssignmentsCount(Property $property): int
    {
        return PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->count();
    }

    public function getVacatedAssignmentsCount(Property $property): int
    {
        return PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'vacated')
            ->count();
    }

    public function getOccupiedUnitsCount(Property $property): int
    {
        return PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->count();
    }

    // =================================================================
    // RESPONSE HELPERS
    // =================================================================

    private function jsonError(string $message, int $status = 400, ?string $debug = null)
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'error'   => $debug,
        ]), $status);
    }

    private function jsonValidationError($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422);
    }

    /**
 * GET /api/v1/field-agent/registration-plans
 *
 * Lists the plans the authenticated agent is actively assigned to.
 * Reuses the same pagination shape as the admin index, but the query
 * is scoped via PlanAgentAssignment so an agent cannot enumerate
 * plans they aren't assigned to.
 */
public function myRegistrationPlans(Request $request): JsonResponse
{
    $user = $request->user();

    $query = RegistrationPlan::query()
        ->whereHas('planAssignments', function ($q) use ($user) {
            $q->where('agent_id', $user->id)->where('is_active', true);
        })
        ->with(['assignedAgents.agent'])
        ->withCount('properties')
        ->orderByDesc('created_at');

    if ($request->filled('status') && $request->status !== 'all') {
        $query->where('status', $request->status);
    }
    if ($request->filled('zone')) {
        $query->where('zone', 'like', '%' . $request->zone . '%');
    }
    if ($request->filled('section')) {
        $query->where('section', 'like', '%' . $request->section . '%');
    }

    $plans = $query->paginate($request->integer('per_page', 20));

    return response()->json([
        'data' => \App\Http\Resources\RegistrationPlanResource::collection($plans->items()),
        'meta' => [
            'current_page' => $plans->currentPage(),
            'last_page'    => $plans->lastPage(),
            'per_page'     => $plans->perPage(),
            'total'        => $plans->total(),
        ],
    ]);
}

/**
 * GET /api/v1/field-agent/registration-plans/{id}/property-counts
 *
 * Returns plan-wide and per-agent property counts for a single plan.
 * The agent must be actively assigned to the plan.
 */
public function myPlanPropertyCounts(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    $isAssigned = PlanAgentAssignment::where('plan_id', $id)
        ->where('agent_id', $user->id)
        ->where('is_active', true)
        ->exists();

    if (!$isAssigned) {
        return $this->jsonError('You are not assigned to this registration plan.', 403);
    }

    $total = Property::where('registration_plan_id', $id)->count();
    $mine  = Property::where('registration_plan_id', $id)
        ->where('registered_by', $user->id)
        ->count();

    return response()->json([
        'total'            => $total,
        'registered_by_me' => $mine,
    ]);
}

}