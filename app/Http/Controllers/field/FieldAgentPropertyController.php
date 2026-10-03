<?php

namespace App\Http\Controllers\field;

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Models\LandlordInvitation;
use App\Models\TenantInvitation;
use App\Services\PropertyRegistrationService;
use App\Services\MultiChannelInvitationService;
use App\Services\GoogleMapsService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use App\Services\WhatsAppService;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;

class FieldAgentPropertyController extends Controller
{
    protected $registrationService;
    protected $multiChannelInvitationService;
    protected $smsService;
    protected $smsTemplateService;
    protected $googleMapsService;

    public function __construct(
        PropertyRegistrationService $registrationService,
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        SmsTemplateService $smsTemplateService,
        GoogleMapsService $googleMapsService
    ) {
        $this->registrationService = $registrationService;
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
        $this->googleMapsService   = $googleMapsService;
    }

    // ================================================================
    //  CHANNEL STATUS RESOLUTION
    // ================================================================

    /**
     * Resolve the system-ready flags for SMS, WhatsApp, and Email.
     *
     * @return array{smsStatus: array, whatsappStatus: array, emailStatus: array}
     */
    protected function resolveChannelStatuses(): array
    {
        // --------------------------------------------------------------
        // SMS
        // --------------------------------------------------------------
        $smsStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'SMS service unavailable'];

        try {
            if ($this->smsService) {
                $resolved = $this->smsService->getSystemStatus();
                if (is_array($resolved)) {
                    $smsStatus = $resolved;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve SMS system status for field-agent property view: ' . $e->getMessage());
        }

        $smsReady = (bool) (
            $smsStatus['system_ready']
            ?? $smsStatus['can_send_sms']
            ?? $smsStatus['ready']
            ?? false
        );

        $smsStatus['system_ready'] = $smsReady;
        $smsStatus['can_send']     = $smsStatus['can_send'] ?? $smsReady;
        if (!isset($smsStatus['health'])) {
            $smsStatus['health'] = $smsReady ? 'healthy' : 'degraded';
        }
        if (!isset($smsStatus['message'])) {
            $smsStatus['message'] = $smsReady
                ? 'SMS service is operational'
                : 'SMS service is not configured or not ready';
        }

        // --------------------------------------------------------------
        // WhatsApp
        // --------------------------------------------------------------
        $whatsappStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'WhatsApp service unavailable'];

        try {
            if (class_exists(WhatsAppService::class)) {
                $whatsappService = app(WhatsAppService::class);

                if (method_exists($whatsappService, 'getSystemStatus')) {
                    $resolved = $whatsappService->getSystemStatus();
                    if (is_array($resolved)) {
                        $whatsappStatus = $resolved;
                    }
                } else {
                    $whatsappStatus = [
                        'system_ready' => false,
                        'health'       => 'not_configured',
                        'message'      => 'WhatsApp service does not expose a status API',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve WhatsApp system status for field-agent property view: ' . $e->getMessage());
        }

        $whatsappReady = (bool) (
            $whatsappStatus['system_ready']
            ?? $whatsappStatus['can_send']
            ?? $whatsappStatus['configured']
            ?? false
        );

        $whatsappStatus['system_ready'] = $whatsappReady;
        $whatsappStatus['can_send']     = $whatsappStatus['can_send'] ?? $whatsappReady;
        if (!isset($whatsappStatus['health'])) {
            $whatsappStatus['health'] = $whatsappReady ? 'healthy' : 'not_configured';
        }
        if (!isset($whatsappStatus['message'])) {
            $whatsappStatus['message'] = $whatsappReady
                ? 'WhatsApp service is operational'
                : 'WhatsApp service is not configured';
        }

        // --------------------------------------------------------------
        // Email — the tricky one
        // --------------------------------------------------------------
        $emailStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'Email service unavailable'];

        try {
            if (class_exists(SystemSetting::class)) {
                $settings = SystemSetting::getSettings();

                if ($settings && method_exists($settings, 'getEmailConfigurationStatus')) {
                    $resolved = $settings->getEmailConfigurationStatus();
                    if (is_array($resolved)) {
                        $emailStatus = $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve Email system status for field-agent property view: ' . $e->getMessage());
        }

        $emailReady = (bool) (
            $emailStatus['system_ready']
            ?? $emailStatus['can_send']
            ?? $emailStatus['configured']
            ?? false
        );

        if (
            $emailReady === false
            && !array_key_exists('system_ready', $emailStatus)
            && !array_key_exists('can_send', $emailStatus)
            && !array_key_exists('configured', $emailStatus)
        ) {
            try {
                $settings = $settings ?? SystemSetting::getSettings();
                if (method_exists($settings, 'canSendEmails')) {
                    $check      = $settings->canSendEmails();
                    $emailReady = (bool) ($check['can_send'] ?? false);

                    if (!$emailReady && !empty($check['issues'])) {
                        $emailStatus['issues'] = array_merge(
                            $emailStatus['issues'] ?? [],
                            $check['issues']
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Email fallback canSendEmails() check failed: ' . $e->getMessage());
            }
        }

        $emailStatus['system_ready'] = $emailReady;
        $emailStatus['can_send']     = $emailStatus['can_send']     ?? $emailReady;
        $emailStatus['configured']   = $emailStatus['configured']   ?? $emailReady;

        if (!isset($emailStatus['health'])) {
            $emailStatus['health'] = $emailReady ? 'healthy' : 'degraded';
        }
        if (!isset($emailStatus['message'])) {
            $emailStatus['message'] = $emailReady
                ? 'Email service is operational'
                : 'Email service is not configured or not ready';
        }
        if (!isset($emailStatus['provider'])) {
            $emailStatus['provider'] = $emailStatus['system_email'] ?? 'N/A';
        }
        if (!isset($emailStatus['statistics'])) {
            $emailStatus['statistics'] = ['success_rate' => $emailReady ? 100 : 0];
        }
        if (!isset($emailStatus['limits'])) {
            $emailStatus['limits'] = [
                'daily_limit' => [
                    'used_today'         => 0,
                    'max_emails_per_day' => 0,
                ],
            ];
        }

        return [
            'smsStatus'      => $smsStatus,
            'whatsappStatus' => $whatsappStatus,
            'emailStatus'    => $emailStatus,
        ];
    }

    /**
     * Return true if the given channel is ready system-wide.
     */
    protected function isChannelReady(array $status): bool
    {
        return (bool) (
            $status['system_ready']
            ?? $status['can_send']
            ?? $status['can_send_sms']
            ?? $status['ready']
            ?? $status['configured']
            ?? false
        );
    }

    // ================================================================
    //  ✅ NEW: COORDINATE HELPERS
    // ================================================================

    /**
     * Browser-restricted Google Maps key for the map picker.
     */
    protected function getGoogleMapsBrowserKey(): ?string
    {
        $key = config('services.google_maps.browser_key')
            ?? config('services.google_maps.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Attach coordinates to a payload.
     *
     *  1. Uses submitted lat/lng when present.
     *  2. Falls back to geocoding the digital address via GoogleMapsService.
     *  3. Normalizes empty strings to null.
     */
    protected function resolveCoordinates(array $data, bool $onlyIfMissing = false): array
    {
        $hasLat = isset($data['latitude'])  && $data['latitude']  !== '' && $data['latitude']  !== null;
        $hasLng = isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null;

        if ($onlyIfMissing && $hasLat && $hasLng) {
            return $data;
        }

        $needsGeocoding = (!$hasLat || !$hasLng);

        if ($needsGeocoding && !empty($data['digital_address'])) {
            try {
                $coords = $this->googleMapsService
                    ->geocodeDigitalAddress((string) $data['digital_address']);

                if (is_array($coords) && isset($coords['lat'], $coords['lng'])) {
                    $data['latitude']  = (float) $coords['lat'];
                    $data['longitude'] = (float) $coords['lng'];

                    if (empty($data['city']) && !empty($coords['city'])) {
                        $data['city'] = $coords['city'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Field agent coordinate geocoding failed — saving without coordinates', [
                    'digital_address' => $data['digital_address'] ?? null,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    // ================================================================
    //  INDEX
    // ================================================================

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Property::with(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ->withCount('photos')
            ->where(function ($q) use ($user) {
                $q->where('registered_by', $user->id)
                  ->orWhereHas('registrationPlan', function ($planQuery) use ($user) {
                      $planQuery->whereHas('planAssignments', function ($assignmentQuery) use ($user) {
                          $assignmentQuery->where('agent_id', $user->id)
                                          ->where('is_active', true);
                      });
                  });
            });

        $this->applyFilters($query, $request);

        $properties = $query->latest()->paginate(20);

        $planIds = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->pluck('plan_id')
            ->toArray();

        $registrationPlans = RegistrationPlan::whereIn('id', $planIds)->get();
        $propertyTypes = PropertyType::active()->ordered()->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'properties' => $properties,
                'message' => 'Properties retrieved successfully'
            ]);
        }

        return view('field-agent.properties.index', compact('properties', 'registrationPlans', 'propertyTypes'));
    }

    // ================================================================
    //  CREATE
    // ================================================================

    public function create()
    {
        $user = auth()->user();

        $activeAssignments = $user->activePlanAssignments()->count();
        if ($activeAssignments === 0) {
            return redirect()->route('field-agent.properties.index')
                ->with('error', 'You need to be assigned to an active registration plan before creating properties.');
        }

        $planIds = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->pluck('plan_id')
            ->toArray();

        Log::info('Step 1 - Plan IDs from assignments', [
            'agent_id' => $user->id,
            'plan_ids' => $planIds,
            'count' => count($planIds)
        ]);

        $registrationPlans = RegistrationPlan::whereIn('id', $planIds)
            ->whereNull('deleted_at')
            ->whereIn('status', ['assigned', 'in_progress'])
            ->get();

        $registrationPlans->each(function ($plan) {
            $plan->properties_count = $plan->properties()->count();
            $plan->next_available_name = $this->getNextAvailableName($plan);
        });

        Log::info('Step 2 - Registration plans found', [
            'count' => $registrationPlans->count(),
            'plans' => $registrationPlans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'zone' => $plan->zone,
                    'section' => $plan->section,
                    'status' => $plan->status,
                    'naming_pattern' => $plan->naming_pattern,
                    'starting_point' => $plan->starting_point,
                    'sequence_type' => $plan->sequence_type,
                    'next_available_name' => $plan->next_available_name,
                    'estimated_houses' => $plan->estimated_houses,
                    'properties_count' => $plan->properties_count,
                    'is_global_sequence' => $plan->is_global_sequence ?? false,
                    'continues_from_plan_id' => $plan->continues_from_plan_id
                ];
            })
        ]);

        $propertyTypes = PropertyType::active()->ordered()->get();

        if ($registrationPlans->isEmpty()) {
            Log::warning('No eligible plans found for agent', [
                'agent_id' => $user->id,
                'plan_ids_from_assignments' => $planIds
            ]);

            return redirect()->route('field-agent.properties.index')
                ->with('error', 'You are assigned to plans, but none are active or eligible for registration. Please contact administrator.');
        }

        $assignedPlans = $registrationPlans;
        $assignedPlanIds = $planIds;

        $landlords = User::where(function ($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function ($q) {
                      $q->where('slug', 'landlord');
                  });
        })
        ->with(['phones' => function ($query) {
            $query->where('is_active', true)->orderBy('is_primary', 'desc');
        }])
        ->orderBy('name')
        ->get()
        ->map(function ($landlord) {
            $landlord->has_phone = $landlord->phones->isNotEmpty() || !empty($landlord->phone);
            $landlord->has_email = !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL);
            $landlord->phones_list = $landlord->phones->pluck('phone_number')->implode(',');
            return $landlord;
        });

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        // ✅ NEW: Google Maps browser key for the coordinate picker
        $googleMapsKey = $this->getGoogleMapsBrowserKey();

        Log::info('Step 3 - Data being passed to view', [
            'registrationPlans_count' => $registrationPlans->count(),
            'assignedPlans_count' => $assignedPlans->count(),
            'assignedPlanIds' => $assignedPlanIds,
            'assignedPlanIds_count' => count($assignedPlanIds),
            'propertyTypes_count' => $propertyTypes->count(),
            'landlords_count' => $landlords->count(),
            'has_landlord_data' => $landlords->isNotEmpty(),
            'sms_ready'      => $this->isChannelReady($smsStatus),
            'whatsapp_ready' => $this->isChannelReady($whatsappStatus),
            'email_ready'    => $this->isChannelReady($emailStatus),
            'has_google_maps_key' => !empty($googleMapsKey),
            'first_plan_details' => $registrationPlans->first() ? [
                'id' => $registrationPlans->first()->id,
                'has_next_available' => isset($registrationPlans->first()->next_available_name),
                'has_properties_count' => isset($registrationPlans->first()->properties_count)
            ] : null
        ]);

        return view('field-agent.properties.create', compact(
            'registrationPlans',
            'propertyTypes',
            'assignedPlans',
            'assignedPlanIds',
            'landlords',
            'smsStatus',
            'whatsappStatus',
            'emailStatus',
            'googleMapsKey'
        ));
    }

    // ================================================================
    //  STORE
    // ================================================================

    /**
     * Store a newly created property.
     *
     * ✅ NEW: resolves coordinates (submitted values or geocoded from the
     * digital address) before handing the payload to the registration
     * service, and patches them after create if the service dropped them.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($request->filled('registration_plan_id')) {
            $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
                ->where('plan_id', $request->registration_plan_id)
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return $this->errorResponse($request, 'You are not assigned to this registration plan.');
            }
        }

        $validator = $this->validatePropertyCreation($request);
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            DB::beginTransaction();

            $landlordId = $request->landlord_id;
            $isNewLandlord = empty($landlordId) && !empty($request->landlord_name);

            if ($isNewLandlord) {
                $landlordResult = $this->createOrGetLandlord($request);
                if (!$landlordResult['success']) {
                    throw new \Exception($landlordResult['message']);
                }
                $landlordId = $landlordResult['landlord_id'];
                $request->merge(['landlord_id' => $landlordId]);

                $propertyData = [
                    'property_name' => $request->property_name,
                    'street_name' => $request->street_name,
                    'house_number' => $request->house_number,
                    'digital_address' => $request->digital_address,
                ];

                if ($this->isDuplicateProperty($landlordId, $propertyData)) {
                    DB::rollBack();
                    return $this->errorResponse($request, 'This property already exists for this landlord.');
                }
            }

            $propertyTypeId = $request->property_type_id;
            $customPropertyType = null;

            $customType = PropertyType::where('slug', 'custom')->first();
            if ($customType && $propertyTypeId == $customType->id) {
                $customPropertyType = $request->custom_property_type;
                if (empty($customPropertyType)) {
                    throw new \Exception('Custom property type name is required.');
                }
            }

            // ✅ NEW: resolve coordinates before passing to the service
            $coordinatePayload = $this->resolveCoordinates([
                'digital_address' => $request->digital_address,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
                'city'            => $request->city,
            ], /* onlyIfMissing */ false);

            $propertyData = [
                'registration_plan_id' => $request->registration_plan_id,
                'property_type_id' => $propertyTypeId,
                'custom_property_type' => $customPropertyType,
                'property_name' => $request->property_name,
                'registration_pattern' => $request->registration_pattern,
                'house_number' => $request->house_number,
                'street_name' => $request->street_name,
                'block_number' => $request->block_number,
                'digital_address' => $request->digital_address,

                // ✅ NEW: coordinate fields
                'latitude'  => $coordinatePayload['latitude']  ?? null,
                'longitude' => $coordinatePayload['longitude'] ?? null,
                'city'      => $coordinatePayload['city']      ?? $request->city,

                'zone' => $request->zone ?? $request->manual_zone,
                'section' => $request->section ?? $request->manual_section,
                'description' => $request->description,
                'registration_date' => $request->registration_date,
                'status' => $request->status,
                'registered_by' => $user->id,
                'landlord_id' => $landlordId,
                'verification_status' => 'pending',
                'is_field_agent_registered' => true,
                'field_agent_registered_at' => now(),
            ];

            $result = $this->registrationService->createProperty($propertyData, $user);

            if ($result['success']) {
                $property = Property::find($result['site_allocation']['id']);

                // ✅ Safety net: ensure coordinates actually persisted even if
                // the registration service dropped them.
                if ($property && !$property->has_coordinates) {
                    $patch = array_filter([
                        'latitude'  => $coordinatePayload['latitude']  ?? null,
                        'longitude' => $coordinatePayload['longitude'] ?? null,
                        'city'      => $coordinatePayload['city']      ?? $request->city,
                    ], fn ($v) => $v !== null && $v !== '');

                    if (!empty($patch)) {
                        $property->update($patch);
                        Log::info('Field agent property coordinates patched after create', [
                            'property_id' => $property->id,
                            'latitude'    => $property->latitude,
                            'longitude'   => $property->longitude,
                        ]);
                    }
                }

                if ($request->hasFile('property_photos')) {
                    $this->handlePhotoUploads($request, $property);
                }

                // SEND LANDLORD INVITATION
                if ($request->send_invitation == '1' && $request->has('invitation_channels')) {
                    $invitationResult = $this->sendLandlordInvitation(
                        $property->landlord,
                        $property,
                        $request->invitation_channels,
                        $request->custom_message ?? null
                    );

                    if (!$invitationResult['success']) {
                        Log::warning('Landlord invitation failed but property was created', [
                            'property_id' => $property->id,
                            'error' => $invitationResult['message']
                        ]);
                    }
                }

                // SEND TENANT INVITATIONS
                if ($request->is_rented == '1' && $request->has('tenants')) {
                    $tenantResult = $this->handleTenantInvitations($request->tenants, $property);

                    if (!$tenantResult['success']) {
                        Log::warning('Some tenant invitations failed', [
                            'property_id' => $property->id,
                            'errors' => $tenantResult['errors'] ?? []
                        ]);
                    }
                }

                DB::commit();

                $this->updateAgentAssignmentProgress($user->id, $request->registration_plan_id);

                // Auto-advance the plan to `in_progress` the first time an
                // actively-assigned field agent registers under `assigned`.
                if (!empty($request->registration_plan_id)) {
                    try {
                        $plan = RegistrationPlan::find($request->registration_plan_id);
                        if ($plan) {
                            $plan->markInProgressIfAssigned((int) $user->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to auto-advance plan to in_progress', [
                            'plan_id' => $request->registration_plan_id,
                            'agent_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $successMessage = 'Property "' . $property->property_name . '" registered successfully! Awaiting verification.';
                if ($request->send_invitation == '1') {
                    $successMessage .= ' Invitation sent to landlord.';
                }
                if ($request->is_rented == '1') {
                    $successMessage .= ' Tenant invitations processed.';
                }

                $this->clearFormFields($request);

                $preservedData = [
                    'preserve_filters' => true,
                    'last_registered_plan_id' => $request->registration_plan_id,
                    'success' => $successMessage . ' You can now register another property.',
                    'selected_plan_id' => $request->registration_plan_id,
                ];

                if ($request->has('batch_register_same_landlord') && $request->batch_register_same_landlord == '1') {
                    $preservedData['batch_landlord_id'] = $landlordId;
                    $preservedData['success'] .= ' Landlord information preserved for next registration.';
                }

                return redirect()->route('field-agent.properties.create')
                    ->with($preservedData);

            } else {
                throw new \Exception($result['message'] ?? 'Failed to create property');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to create property: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token', 'property_photos'])
            ]);
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // ================================================================
    //  CLEAR FORM FIELDS
    // ================================================================

    private function clearFormFields(Request $request)
    {
        $fieldsToClear = [
            'property_name',
            'house_number',
            'street_name',
            'block_number',
            'digital_address',
            'latitude',
            'longitude',
            'city',
            'description',
            'property_photos',
            'send_invitation',
            'invitation_channels',
            'is_rented',
            'tenants'
        ];

        foreach ($fieldsToClear as $field) {
            $request->request->remove($field);
        }

        if ($request->has('batch_register_same_landlord') && $request->batch_register_same_landlord == '1') {
            session()->put('batch_landlord_id', $request->landlord_id);
        } else {
            $request->request->remove('landlord_id');
            $request->request->remove('landlord_name');
            $request->request->remove('landlord_phones');
            $request->request->remove('landlord_email');
        }

        if ($request->has('custom_property_type')) {
            $request->request->remove('custom_property_type');
        }
    }

    // ================================================================
    //  SHOW
    // ================================================================

    public function show(Property $property)
    {
        $user = auth()->user();

        $isInAssignedPlan = $property->registrationPlan &&
            $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

        if ($property->registered_by !== $user->id && !$isInAssignedPlan) {
            abort(403, 'Unauthorized to view this property.');
        }

        $property->load([
            'landlord',
            'registrationPlan.continuedFromPlan',
            'registeredBy',
            'propertyType',
            'tenants',
            'photos',
            'units'
        ]);

        $allTenants = $this->getAllTenantsForProperty($property);

        $pendingAssignments = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->with(['tenant', 'requestedBy'])
            ->get();

        $unitAssignments = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->whereNotNull('tenant_id')
            ->with(['tenant', 'approvedBy', 'requestedBy'])
            ->get();

        return view('field-agent.properties.show', compact(
            'property',
            'allTenants',
            'pendingAssignments',
            'unitAssignments'
        ));
    }

    private function getAllTenantsForProperty(Property $property)
    {
        $directTenants = $property->tenants;

        $unitTenantIds = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->toArray();

        $unitTenants = collect();
        if (!empty($unitTenantIds)) {
            $unitTenants = \App\Models\User::whereIn('id', $unitTenantIds)->get();
        }

        $pendingUnitTenantIds = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->toArray();

        $pendingTenants = collect();
        if (!empty($pendingUnitTenantIds)) {
            $pendingTenants = \App\Models\User::whereIn('id', $pendingUnitTenantIds)->get();
        }

        return $directTenants->merge($unitTenants)->merge($pendingTenants)->unique('id');
    }

    private function canViewProperty(Property $property, $user)
    {
        if ($property->registered_by === $user->id) {
            return true;
        }

        if ($property->registrationPlan) {
            $isAssigned = $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if ($isAssigned) {
                return true;
            }
        }

        return false;
    }

    public function getPendingAssignmentsCount(Property $property)
    {
        return \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->count();
    }

    public function getApprovedAssignmentsCount(Property $property)
    {
        return \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->count();
    }

    public function getVacatedAssignmentsCount(Property $property)
    {
        return \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'vacated')
            ->count();
    }

    public function getOccupiedUnitsCount(Property $property)
    {
        return \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->count();
    }

    // ================================================================
    //  EDIT
    // ================================================================

    public function edit(Property $property)
    {
        $user = auth()->user();

        $isInAssignedPlan = $property->registrationPlan &&
            $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

        if ($property->registered_by !== $user->id && !$isInAssignedPlan) {
            abort(403, 'Unauthorized to edit this property.');
        }

        if ($property->verification_status === 'verified') {
            return redirect()->route('field-agent.properties.index')
                ->with('error', 'Verified properties cannot be edited. Please contact an administrator.');
        }

        $planIds = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->pluck('plan_id')
            ->toArray();

        $registrationPlans = RegistrationPlan::whereIn('id', $planIds)
            ->whereNull('deleted_at')
            ->whereIn('status', ['assigned', 'in_progress'])
            ->get();

        $registrationPlans->each(function ($plan) {
            $plan->properties_count = $plan->properties()->count();
            $plan->next_available_name = $this->getNextAvailableName($plan);
        });

        $propertyTypes = PropertyType::active()->ordered()->get();
        $customType = PropertyType::where('slug', 'custom')->first();

        $property->load('photos', 'tenants');

        $landlords = User::where(function ($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function ($q) {
                      $q->where('slug', 'landlord');
                  });
        })
        ->with(['phones' => function ($query) {
            $query->orderBy('is_primary', 'desc');
        }])
        ->orderBy('name')
        ->get()
        ->map(function ($landlord) {
            $landlord->has_phone = $landlord->phones->isNotEmpty() || !empty($landlord->phone);
            $landlord->has_email = !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL);
            $landlord->phones_list = $landlord->phones->pluck('phone_number')->implode(',');
            return $landlord;
        });

        $assignedPlans = $registrationPlans;
        $assignedPlanIds = $planIds;

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        // ✅ NEW: Google Maps browser key for the coordinate picker
        $googleMapsKey = $this->getGoogleMapsBrowserKey();

        return view('field-agent.properties.edit', compact(
            'property',
            'registrationPlans',
            'propertyTypes',
            'customType',
            'assignedPlans',
            'assignedPlanIds',
            'landlords',
            'smsStatus',
            'whatsappStatus',
            'emailStatus',
            'googleMapsKey'
        ));
    }

    // ================================================================
    //  UPDATE
    // ================================================================

    public function update(Request $request, Property $property)
    {
        $user = auth()->user();

        $isInAssignedPlan = $property->registrationPlan &&
            $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

        if ($property->registered_by !== $user->id && !$isInAssignedPlan) {
            return $this->errorResponse($request, 'Unauthorized to update this property.');
        }

        if ($property->verification_status === 'verified') {
            return $this->errorResponse($request, 'Verified properties cannot be edited.');
        }

        if ($request->filled('registration_plan_id') && $request->registration_plan_id != $property->registration_plan_id) {
            $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
                ->where('plan_id', $request->registration_plan_id)
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return $this->errorResponse($request, 'You are not assigned to this registration plan.');
            }
        }

        $validator = Validator::make($request->all(), [
            'property_name' => 'required|string|max:255',
            'street_name' => 'required|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',
            'property_type_id' => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'description' => 'nullable|string',
            'zone' => 'nullable|string|max:255',
            'section' => 'nullable|string|max:255',
            'status' => 'nullable|string',

            // ✅ NEW: coordinate validation
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            DB::beginTransaction();

            $oldPlanId = $property->registration_plan_id;
            $updateData = $validator->validated();

            $customType = PropertyType::where('slug', 'custom')->first();
            if ($request->property_type_id == ($customType->id ?? null)) {
                $updateData['custom_property_type'] = $request->custom_property_type;
            } else {
                $updateData['custom_property_type'] = null;
            }

            // ✅ NEW: coordinate resolution for updates
            $hasSubmittedCoords = $request->filled('latitude') && $request->filled('longitude');
            $digitalChanged     = $request->filled('digital_address')
                && $request->digital_address !== $property->digital_address;

            if ($hasSubmittedCoords) {
                $updateData['latitude']  = (float) $request->latitude;
                $updateData['longitude'] = (float) $request->longitude;
                if ($request->filled('city')) {
                    $updateData['city'] = $request->city;
                }
            } elseif ($digitalChanged && !$property->has_coordinates) {
                $patched = $this->resolveCoordinates([
                    'digital_address' => $request->digital_address,
                    'latitude'        => null,
                    'longitude'       => null,
                ], /* onlyIfMissing */ true);

                if (!empty($patched['latitude']) && !empty($patched['longitude'])) {
                    $updateData['latitude']  = $patched['latitude'];
                    $updateData['longitude'] = $patched['longitude'];
                    if (!empty($patched['city']) && empty($updateData['city'])) {
                        $updateData['city'] = $patched['city'];
                    }
                }
            } elseif ($digitalChanged && $property->has_coordinates) {
                // Refresh coordinates for a changed digital address
                $patched = $this->resolveCoordinates([
                    'digital_address' => $request->digital_address,
                    'latitude'        => null,
                    'longitude'       => null,
                ], /* onlyIfMissing */ false);

                if (!empty($patched['latitude']) && !empty($patched['longitude'])) {
                    $updateData['latitude']  = $patched['latitude'];
                    $updateData['longitude'] = $patched['longitude'];
                    if (!empty($patched['city']) && empty($updateData['city'])) {
                        $updateData['city'] = $patched['city'];
                    }
                }
            }

            $property->update($updateData);
            $this->handlePhotoManagement($request, $property);

            if ($property->registration_plan_id != $oldPlanId) {
                $this->updateAgentAssignmentProgress($user->id, $oldPlanId);
                $this->updateAgentAssignmentProgress($user->id, $property->registration_plan_id);
            }

            DB::commit();

            return redirect()->route('field-agent.properties.show', $property->id)
                ->with('success', 'Property updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to update property: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to update property.');
        }
    }

    // ================================================================
    //  ✅ NEW: UPDATE COORDINATES ONLY
    // ================================================================

    public function updateCoordinates(Request $request, Property $property)
    {
        $user = auth()->user();

        $isInAssignedPlan = $property->registrationPlan &&
            $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

        $isAdmin = $user->isAdmin() || $user->isSuperAdmin();

        if ($property->registered_by !== $user->id && !$isInAssignedPlan && !$isAdmin) {
            return $this->errorResponse($request, 'Unauthorized to update coordinates for this property.');
        }

        $validator = Validator::make($request->all(), [
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        try {
            $data = $validator->validated();

            $patched = $this->resolveCoordinates(
                array_merge(['digital_address' => $property->digital_address], $data),
                /* onlyIfMissing */ true
            );

            $updatePayload = array_filter([
                'latitude'  => $patched['latitude']  ?? $property->latitude,
                'longitude' => $patched['longitude'] ?? $property->longitude,
                'city'      => $patched['city']      ?? $property->city,
            ], fn ($v) => $v !== null && $v !== '');

            if (empty($updatePayload)) {
                return $this->errorResponse($request, 'No coordinates could be resolved.');
            }

            $property->update($updatePayload);

            Log::info('Field agent updated property coordinates', [
                'property_id' => $property->id,
                'latitude'    => $property->latitude,
                'longitude'   => $property->longitude,
                'updated_by'  => $user->id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'Coordinates updated successfully.',
                    'property' => $property->fresh(),
                ]);
            }

            return redirect()->back()->with('success', 'Coordinates updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update coordinates: ' . $e->getMessage(), [
                'property_id' => $property->id,
            ]);

            return $this->errorResponse($request, 'Failed to update coordinates. Please try again.');
        }
    }

    // ================================================================
    //  DESTROY
    // ================================================================

    public function destroy(Property $property, Request $request)
    {
        $user = auth()->user();

        $isInAssignedPlan = $property->registrationPlan &&
            $property->registrationPlan->planAssignments()
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->exists();

        if ($property->registered_by !== $user->id && !$isInAssignedPlan) {
            return $this->errorResponse($request, 'Unauthorized to delete this property.');
        }

        if ($property->verification_status === 'verified') {
            return $this->errorResponse($request, 'Verified properties cannot be deleted.');
        }

        $isOwnProperty = $property->registered_by === $user->id;

        try {
            DB::beginTransaction();

            $planId = $property->registration_plan_id;

            foreach ($property->photos as $photo) {
                $photo->deletePhotoFile();
            }

            $property->delete();
            $this->updateAgentAssignmentProgress($user->id, $planId);

            DB::commit();

            $message = $isOwnProperty
                ? 'Property deleted successfully.'
                : 'Property from your assigned plan has been deleted successfully.';

            return redirect()->route('field-agent.properties.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Field agent failed to delete property: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to delete property.');
        }
    }

    // ================================================================
    //  GET BY PLAN / TYPE / STATS
    // ================================================================

    public function getByRegistrationPlan($planId, Request $request)
    {
        $user = auth()->user();

        $isAssigned = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->exists();

        if (!$isAssigned) {
            abort(403, 'You are not assigned to this registration plan');
        }

        $query = Property::with(['landlord', 'registrationPlan', 'propertyType', 'photos'])
            ->where('registration_plan_id', $planId)
            ->withCount('photos');

        $this->applyFilters($query, $request);

        $properties = $query->latest()->paginate(20);
        $plan = RegistrationPlan::findOrFail($planId);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'properties' => $properties,
                'plan' => $plan
            ]);
        }

        return view('field-agent.properties.by-plan', compact('properties', 'plan'));
    }

    public function getPropertyTypeStats(Request $request)
    {
        $user = auth()->user();

        $stats = Property::where('registered_by', $user->id)
            ->select('property_type_id', DB::raw('count(*) as total'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($user) {
                $totalProperties = Property::where('registered_by', $user->id)->count();
                return [
                    'type_id' => $item->property_type_id,
                    'type_name' => $item->propertyType?->name ?? 'Unknown',
                    'type_slug' => $item->propertyType?->slug ?? 'unknown',
                    'total' => $item->total,
                    'percentage' => $totalProperties > 0 ? round(($item->total / $totalProperties) * 100, 1) : 0
                ];
            });

        $customStats = Property::where('registered_by', $user->id)
            ->whereNotNull('custom_property_type')
            ->select('custom_property_type', DB::raw('count(*) as total'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($user) {
                $totalProperties = Property::where('registered_by', $user->id)->count();
                return [
                    'type_id' => null,
                    'type_name' => $item->custom_property_type,
                    'type_slug' => Str::slug($item->custom_property_type),
                    'total' => $item->total,
                    'percentage' => $totalProperties > 0 ? round(($item->total / $totalProperties) * 100, 1) : 0,
                    'is_custom' => true
                ];
            });

        $allStats = $stats->concat($customStats);
        $totalProperties = Property::where('registered_by', $user->id)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'statistics' => $allStats,
                'total_properties' => $totalProperties
            ]);
        }

        return view('field-agent.properties.type-stats', compact('allStats', 'totalProperties'));
    }

    public function getByPropertyType($typeSlug, Request $request)
    {
        $user = auth()->user();

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

        $properties = $query->latest()->paginate(20);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'properties' => $properties,
                'type_name' => $typeName,
                'type_slug' => $typeSlug
            ]);
        }

        return view('field-agent.properties.by-type', compact('properties', 'typeName', 'typeSlug'));
    }

    public function myProperties(Request $request)
    {
        return $this->index($request);
    }

    // ================================================================
    //  INVITATION METHODS
    // ================================================================

    private function sendLandlordInvitation($landlord, $property, array $channels, $customMessage = null): array
    {
        try {
            Log::info('Sending landlord invitation', [
                'landlord_id' => $landlord->id,
                'property_id' => $property->id,
                'channels' => $channels
            ]);

            $availableChannels = $this->multiChannelInvitationService->getAvailableChannels($landlord);
            $channels = array_intersect($channels, $availableChannels);

            $systemChannelStatuses = $this->resolveChannelStatuses();
            $channels = array_values(array_filter($channels, function ($channel) use ($systemChannelStatuses) {
                switch ($channel) {
                    case 'sms':
                        return $this->isChannelReady($systemChannelStatuses['smsStatus']);
                    case 'whatsapp':
                        return $this->isChannelReady($systemChannelStatuses['whatsappStatus']);
                    case 'email':
                        return $this->isChannelReady($systemChannelStatuses['emailStatus']);
                    default:
                        return false;
                }
            }));

            if (empty($channels)) {
                return [
                    'success' => false,
                    'message' => 'No available communication channels for this landlord (or the corresponding services are not configured).'
                ];
            }

            $invitation = LandlordInvitation::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                'custom_message' => $customMessage,
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'is_field_agent' => true,
                    'agent_id' => auth()->id()
                ]
            ]);

            $successCount = 0;
            $channelsSuccessful = [];

            foreach ($channels as $channel) {
                try {
                    $result = $this->sendInvitationViaChannel($landlord, $property, $invitation, $channel);

                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to send via {$channel}: " . $e->getMessage());
                }
            }

            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
                return [
                    'success' => true,
                    'message' => "Invitation sent via " . implode(', ', $channelsSuccessful),
                    'invitation_url' => $invitation->getInvitationUrl(),
                    'channels_successful' => $channelsSuccessful
                ];
            } else {
                $invitation->markAsFailed('All channels failed');
                return [
                    'success' => false,
                    'message' => 'Failed to send invitation via all channels'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to send landlord invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send landlord invitation: ' . $e->getMessage()
            ];
        }
    }

    private function sendInvitationViaChannel($landlord, $property, $invitation, $channel): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmailInvitation($landlord, $property, $invitation);
            case 'sms':
                return $this->sendSmsInvitation($landlord, $property, $invitation);
            case 'whatsapp':
                return $this->sendWhatsAppInvitation($landlord, $property, $invitation);
            default:
                return ['success' => false, 'message' => "Unknown channel: {$channel}"];
        }
    }

    private function sendEmailInvitation($landlord, $property, $invitation): array
    {
        try {
            if (empty($landlord->email) || !filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Landlord does not have a valid email address'];
            }

            \Mail::to($landlord->email)->send(new \App\Mail\LandlordInvitationMail($landlord, $property, $invitation));

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Exception $e) {
            Log::error('Failed to send email invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }

    private function sendSmsInvitation($landlord, $property, $invitation): array
    {
        try {
            $smsStatus = $this->smsService->getSystemStatus();

            if (!$this->isChannelReady($smsStatus)) {
                return ['success' => false, 'message' => 'SMS service is not configured or ready'];
            }

            $phone = $landlord->phone ?: ($landlord->phones->first()->phone_number ?? null);
            if (empty($phone) || !$this->isValidPhoneNumber($phone)) {
                return ['success' => false, 'message' => 'Landlord does not have a valid phone number'];
            }

            $message = $this->smsTemplateService->generateLandlordInvitationMessage(
                $landlord,
                $property,
                $invitation->getInvitationUrl(),
                $invitation->token
            );

            $defaultProvider = $this->smsService->getDefaultProvider() ?? 'arkesel';

            $smsResult = $this->smsService->sendSMS(
                $defaultProvider,
                $phone,
                $message,
                [
                    'is_test' => false,
                    'category' => 'landlord_invitation',
                    'invitation_id' => $invitation->id,
                    'property_id' => $property->id,
                    'is_field_agent' => true
                ]
            );

            return [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? 'SMS sending completed',
                'provider' => $smsResult['provider'] ?? $defaultProvider
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send SMS invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    private function sendWhatsAppInvitation($landlord, $property, $invitation): array
    {
        try {
            $phone = $landlord->phone ?: ($landlord->phones->first()->phone_number ?? null);

            if (empty($phone)) {
                return ['success' => false, 'message' => 'Landlord does not have a phone number'];
            }

            if (!class_exists(WhatsAppService::class)) {
                return ['success' => false, 'message' => 'WhatsApp service is not available'];
            }

            $whatsappService = app(WhatsAppService::class);

            if (method_exists($whatsappService, 'getSystemStatus')) {
                $status = $whatsappService->getSystemStatus();
                if (!$this->isChannelReady($status)) {
                    return ['success' => false, 'message' => 'WhatsApp service is not configured or ready'];
                }
            }

            $message = $this->generateWhatsAppMessage($landlord, $property, $invitation);

            $result = $whatsappService->send($phone, $message);

            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp message sent' : 'Failed to send WhatsApp')
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp invitation: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send WhatsApp: ' . $e->getMessage()];
        }
    }

    private function generateWhatsAppMessage($landlord, $property, $invitation): string
    {
        $propertyType = $property->propertyType->name ?? ($property->custom_property_type ?? 'Property');
        $invitationUrl = $invitation->getInvitationUrl();

        return "🏠 *Property Registration*\n\n" .
               "Hello {$landlord->name},\n\n" .
               "Your *{$propertyType}* has been registered successfully.\n\n" .
               "📍 *Location:* {$property->street_name}, " . ($property->zone ?? 'N/A') . "\n" .
               "🔢 *Registration ID:* {$property->registration_pattern}\n" .
               "📅 *Registered:* " . ($property->registration_date?->format('M j, Y') ?? 'Today') . "\n\n" .
               "Complete registration here:\n" .
               "{$invitationUrl}\n\n" .
               "⏰ *Link expires:* " . $invitation->expires_at->format('F j, Y') . "\n\n" .
               "Thank you for using Property Portal!";
    }

    private function handleTenantInvitations(array $tenants, Property $property): array
    {
        $successCount = 0;
        $errors = [];

        foreach ($tenants as $index => $tenantData) {
            try {
                Log::info('Processing tenant invitation', [
                    'property_id' => $property->id,
                    'tenant_name' => $tenantData['name'],
                    'channels' => $tenantData['channels'] ?? []
                ]);

                $channels = $tenantData['channels'] ?? ['sms'];
                if (is_string($channels)) {
                    $channels = json_decode($channels, true) ?? [$channels];
                }

                $systemChannelStatuses = $this->resolveChannelStatuses();
                $channels = array_values(array_filter($channels, function ($channel) use ($systemChannelStatuses) {
                    switch ($channel) {
                        case 'sms':
                            return $this->isChannelReady($systemChannelStatuses['smsStatus']);
                        case 'whatsapp':
                            return $this->isChannelReady($systemChannelStatuses['whatsappStatus']);
                        case 'email':
                            return $this->isChannelReady($systemChannelStatuses['emailStatus']);
                        default:
                            return false;
                    }
                }));

                if (empty($channels)) {
                    $errors[] = [
                        'tenant' => $tenantData['name'] ?? 'Unknown',
                        'error' => 'No available invitation channels for this tenant'
                    ];
                    continue;
                }

                $tenant = $this->createOrGetTenant($tenantData, $property);

                if ($tenant) {
                    $invitation = TenantInvitation::create([
                        'property_id' => $property->id,
                        'tenant_id' => $tenant->id,
                        'invited_by' => auth()->id(),
                        'channels' => $channels,
                        'invitation_type' => 'property_access',
                        'expires_at' => now()->addDays(7),
                        'metadata' => [
                            'is_field_agent' => true,
                            'agent_id' => auth()->id()
                        ]
                    ]);

                    foreach ($channels as $channel) {
                        $this->sendTenantInvitationViaChannel($tenant, $property, $invitation, $channel);
                    }

                    $successCount++;
                }

            } catch (\Exception $e) {
                Log::error('Failed to process tenant invitation: ' . $e->getMessage(), [
                    'tenant_data' => $tenantData
                ]);
                $errors[] = [
                    'tenant' => $tenantData['name'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $successCount > 0,
            'success_count' => $successCount,
            'total' => count($tenants),
            'errors' => $errors
        ];
    }

    private function createOrGetTenant(array $tenantData, Property $property)
    {
        try {
            $phone = $this->standardizePhoneNumber($tenantData['phone']);

            Log::info('Creating/getting tenant', [
                'name' => $tenantData['name'],
                'phone' => $phone,
                'property_id' => $property->id
            ]);

            $tenant = User::where('phone', $phone)->first();

            if (!$tenant) {
                $temporaryPassword = Str::random(16);

                $tenant = User::create([
                    'name' => $tenantData['name'],
                    'phone' => $phone,
                    'email' => $tenantData['email'] ?? null,
                    'type' => User::TYPE_TENANT,
                    'status' => User::STATUS_ACTIVE,
                    'created_by' => auth()->id(),
                    'password' => Hash::make($temporaryPassword),
                ]);

                $tenant->assignRole('tenant', [
                    'assigned_by' => auth()->id(),
                    'assignment_reason' => 'Created during property registration by field agent',
                    'assigned_at' => now(),
                ]);

                Log::info('New tenant created', [
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name
                ]);
            } else {
                Log::info('Existing tenant found', [
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name
                ]);
            }

            if (!$property->tenants()->where('user_id', $tenant->id)->exists()) {
                $pivotData = [
                    'added_by' => auth()->id(),
                    'added_at' => now(),
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'notes' => 'Added by field agent during property registration'
                ];

                $property->tenants()->attach($tenant->id, $pivotData);

                Log::info('Tenant associated with property', [
                    'property_id' => $property->id,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'pivot_data' => $pivotData
                ]);
            } else {
                Log::info('Tenant already associated with property', [
                    'property_id' => $property->id,
                    'tenant_id' => $tenant->id
                ]);
            }

            return $tenant;

        } catch (\Exception $e) {
            Log::error('Failed to create/get tenant: ' . $e->getMessage(), [
                'tenant_data' => $tenantData,
                'property_id' => $property->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function sendTenantInvitationViaChannel($tenant, $property, $invitation, $channel): void
    {
        try {
            switch ($channel) {
                case 'email':
                    if (!empty($tenant->email)) {
                        \Mail::to($tenant->email)->send(new \App\Mail\TenantInvitationMail($tenant, $property, $invitation));
                    }
                    break;
                case 'sms':
                    if (!empty($tenant->phone)) {
                        $message = "Hello {$tenant->name},\n\nYou have been invited to access your property portal for {$property->property_name}.\n\nClick here: " . $invitation->getInvitationUrl();
                        $defaultProvider = $this->smsService->getDefaultProvider() ?? 'arkesel';
                        $this->smsService->sendSMS($defaultProvider, $tenant->phone, $message, ['category' => 'tenant_invitation']);
                    }
                    break;
                case 'whatsapp':
                    if (!empty($tenant->phone) && class_exists(WhatsAppService::class)) {
                        $message = "🏠 *Property Portal Access*\n\nHello {$tenant->name},\n\nYou have been invited to access your property portal for *{$property->property_name}*.\n\nClick here: " . $invitation->getInvitationUrl();
                        app(WhatsAppService::class)->send($tenant->phone, $message);
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send tenant invitation via ' . $channel . ': ' . $e->getMessage());
        }
    }

    // ================================================================
    //  PHONE HELPERS
    // ================================================================

    private function standardizePhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        if (preg_match('/^0/', $cleaned)) {
            $cleaned = '+233' . substr($cleaned, 1);
        }

        if (preg_match('/^233/', $cleaned) && !preg_match('/^\+/', $cleaned)) {
            $cleaned = '+' . $cleaned;
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $cleaned);

        return $cleaned;
    }

    private function findUserByPhone($phone): ?User
    {
        $standardizedPhone = $this->standardizePhoneNumber($phone);

        $formats = [
            $standardizedPhone,
            ltrim($standardizedPhone, '+'),
            preg_replace('/^\+233/', '0', $standardizedPhone),
            preg_replace('/^0/', '', $standardizedPhone),
            preg_replace('/[^0-9]/', '', $standardizedPhone),
        ];

        $formats = array_unique(array_filter($formats));

        foreach ($formats as $format) {
            $user = User::where('phone', $format)->first();
            if ($user) {
                Log::info('Found user by phone format', [
                    'input' => $phone,
                    'matched_format' => $format,
                    'user_id' => $user->id,
                    'user_phone' => $user->phone
                ]);
                return $user;
            }

            $user = User::whereHas('phones', function ($query) use ($format) {
                $query->where('phone_number', $format);
            })->first();

            if ($user) {
                Log::info('Found user by phones relationship', [
                    'input' => $phone,
                    'matched_format' => $format,
                    'user_id' => $user->id
                ]);
                return $user;
            }
        }

        return null;
    }

    private function isValidPhoneNumber($phone): bool
    {
        $standardized = $this->standardizePhoneNumber($phone);

        $pattern = '/^(\+233|0)[0-9]{9}$/';

        return preg_match($pattern, $standardized) || preg_match($pattern, $phone);
    }

    // ================================================================
    //  PRIVATE HELPERS
    // ================================================================

    private function getNextAvailableName($plan)
    {
        if (isset($plan->next_available_name) && $plan->next_available_name) {
            return $plan->next_available_name;
        }

        $lastProperty = $plan->properties()->orderBy('registration_pattern', 'desc')->first();

        if (!$lastProperty) {
            return $plan->starting_point ?? $plan->naming_pattern . '001';
        }

        $pattern = $plan->naming_pattern;
        $lastPattern = $lastProperty->registration_pattern;
        $number = str_replace($pattern, '', $lastPattern);
        $nextNumber = intval($number) + 1;
        $nextNumberFormatted = str_pad($nextNumber, strlen($number), '0', STR_PAD_LEFT);

        return $pattern . $nextNumberFormatted;
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('street_name')) {
            $query->where('street_name', 'like', '%' . $request->street_name . '%');
        }

        if ($request->filled('zone')) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }

        if ($request->filled('registration_plan_id')) {
            $query->where('registration_plan_id', $request->registration_plan_id);
        }

        if ($request->filled('property_type_id')) {
            $query->where('property_type_id', $request->property_type_id);
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            if ($request->has_digital_address === '1') {
                $query->hasDigitalAddress();
            } elseif ($request->has_digital_address === '0') {
                $query->missingDigitalAddress();
            }
        }

        // ✅ NEW: coordinate filters
        if ($request->has('has_coordinates') && $request->has_coordinates !== '') {
            if ($request->has_coordinates === '1') {
                $query->hasCoordinates();
            } elseif ($request->has_coordinates === '0') {
                $query->missingCoordinates();
            }
        }
    }

    private function validatePropertyCreation(Request $request)
    {
        $rules = [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'registration_pattern' => 'required|string',
            'property_type_id' => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name' => 'required|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'street_name' => 'required|string|max:255',
            'block_number' => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',

            // ✅ NEW: coordinate validation
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',

            'description' => 'nullable|string',
            'registration_date' => 'required|date',
            'status' => 'nullable|string|in:active,inactive,under_maintenance,vacant',
            'zone' => 'nullable|string|max:255',
            'section' => 'nullable|string|max:255',
            'manual_zone' => 'nullable|string|max:255',
            'manual_section' => 'nullable|string|max:255',
            'property_photos' => 'nullable|array|max:10',
            'send_invitation' => 'nullable|in:0,1',
            'invitation_channels' => 'nullable|array',
            'invitation_channels.*' => 'string|in:sms,email,whatsapp',
            'is_rented' => 'nullable|in:0,1',
            'tenants' => 'nullable|array',
            'tenants.*.name' => 'required_with:tenants|string|max:255',
            'tenants.*.phone' => 'required_with:tenants|string|max:20',
            'tenants.*.email' => 'nullable|email|max:255',
            'tenants.*.gender' => 'nullable|string|in:male,female,other',
            'tenants.*.channels' => 'nullable|array',
        ];

        $messages = [
            'registration_pattern.required' => 'Registration pattern is required. Please select a valid registration plan.',
            'property_type_id.required' => 'Please select a property type.',
            'property_name.required' => 'Property name is required.',
            'street_name.required' => 'Street name is required.',
            'registration_date.required' => 'Registration date is required.',
            'property_photos.max' => 'You can upload a maximum of 10 photos.',
            'tenants.*.name.required_with' => 'Tenant name is required.',
            'tenants.*.phone.required_with' => 'Tenant phone number is required.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ];

        if (empty($request->landlord_id)) {
            $rules['landlord_name'] = 'required|string|max:255';
            $rules['landlord_phones'] = 'required|array|min:1';
            $rules['landlord_phones.*'] = 'string|max:20';
            $rules['landlord_email'] = 'nullable|email|max:255';

            $messages['landlord_name.required'] = 'Landlord name is required when not selecting an existing landlord.';
            $messages['landlord_phones.required'] = 'At least one phone number is required for the landlord.';
        } else {
            $rules['landlord_id'] = 'required|exists:users,id';
        }

        if ($request->hasFile('property_photos')) {
            $files = $request->file('property_photos');
            $validFiles = array_filter($files, function ($file) {
                return $file !== null && $file->isValid();
            });

            if (!empty($validFiles)) {
                foreach ($validFiles as $index => $file) {
                    $rules["property_photos.{$index}"] = [
                        'nullable',
                        'file',
                        'mimes:jpeg,png,jpg,gif,webp',
                        'max:5120'
                    ];
                }
            }
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        $validator->after(function ($validator) use ($request) {
            if ($request->hasFile('property_photos')) {
                foreach ($request->file('property_photos') as $index => $file) {
                    if ($file !== null && !$file->isValid()) {
                        $validator->errors()->add(
                            "property_photos.{$index}",
                            "File upload failed. Please try again with a valid image file."
                        );
                    }

                    if ($file !== null && $file->isValid()) {
                        $mimeType = $file->getMimeType();
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                        if (!in_array($mimeType, $allowedMimes)) {
                            $validator->errors()->add(
                                "property_photos.{$index}",
                                "The file must be an image of type: JPEG, PNG, GIF, or WebP."
                            );
                        }
                    }
                }
            }
        });

        return $validator;
    }

    private function createOrGetLandlord(Request $request): array
    {
        $phone = $request->landlord_phones[0] ?? null;

        if (!$phone) {
            return ['success' => false, 'message' => 'Phone number is required'];
        }

        $existingLandlord = $this->findUserByPhone($phone);

        if ($existingLandlord) {
            Log::info('Found existing landlord', [
                'id' => $existingLandlord->id,
                'name' => $existingLandlord->name,
                'phone' => $existingLandlord->phone,
                'input_phone' => $phone
            ]);

            if (!$existingLandlord->isLandlord()) {
                $existingLandlord->assignRole('landlord', [
                    'assigned_by' => auth()->id(),
                    'assignment_reason' => 'Auto-assigned during property registration by field agent',
                    'assigned_at' => now(),
                ]);
            }

            if (count($request->landlord_phones) > 1) {
                $standardizedPrimary = $this->standardizePhoneNumber($phone);
                foreach (array_slice($request->landlord_phones, 1) as $additionalPhone) {
                    if (!empty($additionalPhone)) {
                        $standardizedAdditional = $this->standardizePhoneNumber($additionalPhone);

                        if ($standardizedAdditional === $standardizedPrimary) {
                            continue;
                        }

                        if (!$existingLandlord->phones()->where('phone_number', $standardizedAdditional)->exists()) {
                            $existingLandlord->phones()->create([
                                'phone_number' => $standardizedAdditional,
                                'is_active' => true,
                                'is_primary' => false,
                                'verified_at' => now(),
                            ]);
                        }
                    }
                }
            }

            return [
                'success' => true,
                'landlord_id' => $existingLandlord->id,
                'was_existing' => true
            ];
        }

        $standardizedPhone = $this->standardizePhoneNumber($phone);
        $temporaryPassword = Str::random(16);

        Log::info('Creating new landlord', [
            'name' => $request->landlord_name,
            'phone' => $standardizedPhone,
            'email' => $request->landlord_email
        ]);

        $landlord = User::create([
            'name' => $request->landlord_name,
            'phone' => $standardizedPhone,
            'email' => $request->landlord_email,
            'type' => User::TYPE_LANDLORD,
            'status' => User::STATUS_ACTIVE,
            'created_by' => auth()->id(),
            'email_verified_at' => null,
            'phone_verified_at' => now(),
            'password' => Hash::make($temporaryPassword),
        ]);

        $landlord->assignRole('landlord', [
            'assigned_by' => auth()->id(),
            'assignment_reason' => 'Created during property registration by field agent',
            'assigned_at' => now(),
        ]);

        if (count($request->landlord_phones) > 1) {
            $standardizedPrimary = $standardizedPhone;
            foreach (array_slice($request->landlord_phones, 1) as $additionalPhone) {
                if (!empty($additionalPhone)) {
                    $standardizedAdditional = $this->standardizePhoneNumber($additionalPhone);

                    if ($standardizedAdditional === $standardizedPrimary) {
                        continue;
                    }

                    $landlord->phones()->create([
                        'phone_number' => $standardizedAdditional,
                        'is_active' => true,
                        'is_primary' => false,
                        'verified_at' => now(),
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'landlord_id' => $landlord->id,
            'was_existing' => false,
            'temporary_password' => $temporaryPassword
        ];
    }

    private function handlePhotoUploads(Request $request, Property $property): void
    {
        if ($request->hasFile('property_photos')) {
            $files = $request->file('property_photos');

            $validFiles = [];
            foreach ($files as $index => $file) {
                if ($file !== null && $file->isValid()) {
                    $validFiles[] = $file;
                }
            }

            if (empty($validFiles)) {
                Log::warning('No valid files to upload', [
                    'property_id' => $property->id,
                    'files_count' => count($files)
                ]);
                return;
            }

            foreach ($validFiles as $file) {
                try {
                    $isPrimary = $property->photos()->count() === 0;
                    $property->addPhoto($file, $isPrimary);
                    Log::info('Photo uploaded successfully', [
                        'property_id' => $property->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'is_primary' => $isPrimary
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to upload photo: ' . $e->getMessage(), [
                        'property_id' => $property->id,
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        }
    }

    private function handlePhotoManagement(Request $request, Property $property): void
    {
        if ($request->filled('delete_photos')) {
            $photoIds = explode(',', $request->delete_photos);
            foreach ($photoIds as $photoId) {
                if (is_numeric($photoId)) {
                    $property->deletePhoto((int) $photoId);
                }
            }
        }

        if ($request->filled('primary_photo_id')) {
            $property->setPrimaryPhoto($request->primary_photo_id);
        }

        if ($request->hasFile('property_photos')) {
            $currentCount = $property->photos()->count();
            $maxPhotos = 10;
            $availableSlots = $maxPhotos - $currentCount;

            if ($availableSlots > 0) {
                $filesToUpload = array_slice($request->file('property_photos'), 0, $availableSlots);
                foreach ($filesToUpload as $file) {
                    $isPrimary = $property->photos()->count() === 0;
                    $property->addPhoto($file, $isPrimary);
                }
            }
        }
    }

    private function updateAgentAssignmentProgress($agentId, $planId): void
    {
        if (!$planId) return;

        $assignment = PlanAgentAssignment::where('agent_id', $agentId)
            ->where('plan_id', $planId)
            ->first();

        if ($assignment) {
            $registeredCount = Property::where('registered_by', $agentId)
                ->where('registration_plan_id', $planId)
                ->count();

            $plan = RegistrationPlan::find($planId);
            $totalProperties = $plan->estimated_houses ?? 0;

            $assignment->properties_registered = $registeredCount;
            $assignment->completion_percentage = $totalProperties > 0
                ? round(($registeredCount / $totalProperties) * 100, 1)
                : 0;

            if ($registeredCount >= $totalProperties && $totalProperties > 0) {
                $assignment->is_completed = true;
                $assignment->completed_at = now();
            }

            $assignment->save();
        }
    }

    private function isDuplicateProperty($landlordId, array $propertyData): bool
    {
        if (!$landlordId) return false;

        if (!empty($propertyData['digital_address'])) {
            if (Property::where('digital_address', $propertyData['digital_address'])
                ->where('landlord_id', $landlordId)
                ->exists()) {
                return true;
            }
        }

        if (!empty($propertyData['property_name'])) {
            if (Property::where('property_name', $propertyData['property_name'])
                ->where('landlord_id', $landlordId)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 500);
        }
        return redirect()->back()->with('error', $message)->withInput();
    }

    private function validationErrorResponse(Request $request, $validator)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        return redirect()->back()->withErrors($validator)->withInput();
    }

    // ================================================================
    //  PERFORMANCE
    // ================================================================

    public function performance(Request $request)
    {
        $user = auth()->user();
        $selectedPlanId = $request->input('registration_plan_id');

        $allAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->with('registrationPlan')
            ->get();

        $activeAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->with('registrationPlan')
            ->get();

        $completedAssignmentsTotal = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_completed', true)
            ->with('registrationPlan')
            ->get();

        $propertyQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $totalProperties = $propertyQuery->count();

        $allAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id);
        if ($selectedPlanId) {
            $allAssignmentsQuery->where('plan_id', $selectedPlanId);
        }
        $allAssignments = $allAssignmentsQuery->with('registrationPlan')->get();

        $activeAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true);
        if ($selectedPlanId) {
            $activeAssignmentsQuery->where('plan_id', $selectedPlanId);
        }
        $activeAssignments = $activeAssignmentsQuery->with('registrationPlan')->get();

        $completedAssignmentsQuery = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_completed', true);
        if ($selectedPlanId) {
            $completedAssignmentsQuery->where('plan_id', $selectedPlanId);
        }
        $completedAssignments = $completedAssignmentsQuery->with('registrationPlan')->get();

        $totalPlansAssigned = $allAssignmentsTotal->count();
        $activePlans = $activeAssignmentsTotal->count();
        $completedPlans = $completedAssignmentsTotal->count();

        $displayActivePlans = $selectedPlanId ? $activeAssignments->count() : $activePlans;
        $displayCompletedPlans = $selectedPlanId ? $completedAssignments->count() : $completedPlans;
        $displayTotalProperties = $selectedPlanId ? $totalProperties : $totalProperties;

        $completionRate = $totalPlansAssigned > 0
            ? round(($completedPlans / $totalPlansAssigned) * 100, 1)
            : 0;

        $averagePerPlan = $activePlans > 0
            ? round($totalProperties / $activePlans, 1)
            : 0;

        $propertyTypeStatsQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $propertyTypeStats = $propertyTypeStatsQuery
            ->select('property_type_id', DB::raw('count(*) as total'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object) [
                    'type_name' => $item->propertyType?->name ?? 'Unknown',
                    'total' => $item->total,
                    'percentage' => $totalProperties > 0
                        ? round(($item->total / $totalProperties) * 100, 1)
                        : 0
                ];
            });

        $customTypeStatsQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $customTypeStats = $customTypeStatsQuery
            ->whereNotNull('custom_property_type')
            ->select('custom_property_type', DB::raw('count(*) as total'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object) [
                    'type_name' => $item->custom_property_type . ' (Custom)',
                    'total' => $item->total,
                    'percentage' => $totalProperties > 0
                        ? round(($item->total / $totalProperties) * 100, 1)
                        : 0,
                    'is_custom' => true
                ];
            });

        $allPropertyTypeStats = $propertyTypeStats->concat($customTypeStats);

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
                return (object) [
                    'month' => \Carbon\Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count
                ];
            })
            ->reverse()
            ->values();

        $recentQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $recentProperties = $recentQuery
            ->with(['propertyType', 'registrationPlan', 'landlord'])
            ->latest()
            ->limit(10)
            ->get();

        $achievements = collect();

        if ($totalProperties >= 1) {
            $achievements->push((object) [
                'icon' => 'fa-house',
                'title' => 'First Property Registered',
                'description' => 'Registered your first property',
                'earned_at' => now()
            ]);
        }

        if ($totalProperties >= 10) {
            $achievements->push((object) [
                'icon' => 'fa-star',
                'title' => 'Double Digits',
                'description' => 'Registered 10 properties',
                'earned_at' => now()
            ]);
        }

        if ($totalProperties >= 50) {
            $achievements->push((object) [
                'icon' => 'fa-trophy',
                'title' => 'Top Performer',
                'description' => 'Registered 50 properties',
                'earned_at' => now()
            ]);
        }

        if ($completedPlans >= 1) {
            $achievements->push((object) [
                'icon' => 'fa-check-circle',
                'title' => 'Plan Completed',
                'description' => 'Completed your first registration plan',
                'earned_at' => now()
            ]);
        }

        $selectedPlan = null;
        $planDetails = null;

        if ($selectedPlanId) {
            $selectedPlan = RegistrationPlan::find($selectedPlanId);

            if ($selectedPlan) {
                $totalInPlan = Property::where('registered_by', $user->id)
                    ->where('registration_plan_id', $selectedPlanId)
                    ->count();

                $totalPlanProperties = Property::where('registration_plan_id', $selectedPlanId)->count();

                $estimatedTotal = $selectedPlan->estimated_houses ?? $selectedPlan->total_properties ?? 0;

                $planDetails = (object) [
                    'id' => $selectedPlan->id,
                    'name' => $selectedPlan->name ?? $selectedPlan->zone . ($selectedPlan->section ? ' - ' . $selectedPlan->section : ''),
                    'zone' => $selectedPlan->zone,
                    'section' => $selectedPlan->section,
                    'total_properties' => $totalInPlan,
                    'total_plan_properties' => $totalPlanProperties,
                    'estimated_total' => $estimatedTotal,
                    'completion_percentage' => $estimatedTotal > 0
                        ? round(($totalInPlan / $estimatedTotal) * 100, 1)
                        : 0,
                    'status' => $selectedPlan->status,
                    'created_at' => $selectedPlan->created_at,
                    'start_date' => $selectedPlan->registration_start_date,
                    'end_date' => $selectedPlan->registration_end_date,
                ];
            }
        }

        $availablePlans = PlanAgentAssignment::where('agent_id', $user->id)
            ->where('is_active', true)
            ->with('registrationPlan')
            ->get()
            ->map(function ($assignment) {
                return $assignment->registrationPlan;
            })
            ->filter()
            ->unique('id')
            ->values();

        $planPerformance = $activeAssignmentsTotal->map(function ($assignment) use ($user) {
            $plan = $assignment->registrationPlan;
            if (!$plan) return null;

            $totalProperties = $plan->estimated_houses ?? $plan->total_properties ?? 0;
            $registeredCount = Property::where('registered_by', $user->id)
                ->where('registration_plan_id', $plan->id)
                ->count();

            return (object) [
                'plan_name' => $plan->name ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                'total_properties' => $totalProperties,
                'registered_count' => $registeredCount,
                'completion_percentage' => $totalProperties > 0
                    ? round(($registeredCount / $totalProperties) * 100, 1)
                    : 0,
                'days_left' => $plan->registration_end_date
                    ? now()->diffInDays($plan->registration_end_date, false)
                    : null
            ];
        })->filter()->values();

        $verificationQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $verificationStats = [
            'verified' => (clone $verificationQuery)->where('verification_status', 'verified')->count(),
            'pending' => (clone $verificationQuery)->where('verification_status', 'pending')->count(),
            'rejected' => (clone $verificationQuery)->where('verification_status', 'rejected')->count(),
        ];

        $currentMonth = now()->month;
        $currentYear = now()->year;
        $monthQuery = $this->getAgentPropertyQuery($user, $selectedPlanId);
        $thisMonthCount = (clone $monthQuery)
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
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

        \Log::info('Performance data for agent', [
            'agent_id' => $user->id,
            'totalPlansAssigned' => $totalPlansAssigned,
            'activePlans' => $activePlans,
            'completedPlans' => $completedPlans,
            'totalProperties' => $totalProperties,
            'completionRate' => $completionRate,
            'propertyTypeStats_count' => $allPropertyTypeStats->count(),
            'monthlyData_count' => $monthlyData->count(),
            'recentProperties_count' => $recentProperties->count(),
            'achievements_count' => $achievements->count(),
            'availablePlans_count' => $availablePlans->count(),
            'planPerformance_count' => $planPerformance->count(),
            'selectedPlanId' => $selectedPlanId
        ]);

        return view('field-agent.performance', [
            'totalProperties' => $totalProperties,
            'activePlans' => $activePlans,
            'completedPlans' => $completedPlans,
            'totalPlansAssigned' => $totalPlansAssigned,
            'completionRate' => $completionRate,
            'averagePerPlan' => $averagePerPlan,

            'displayActivePlans' => $displayActivePlans,
            'displayCompletedPlans' => $displayCompletedPlans,
            'displayTotalProperties' => $displayTotalProperties,

            'propertyTypeStats' => $allPropertyTypeStats,
            'monthlyData' => $monthlyData,

            'recentProperties' => $recentProperties,
            'achievements' => $achievements,

            'selectedPlan' => $selectedPlan,
            'selectedPlanId' => $selectedPlanId,
            'availablePlans' => $availablePlans,
            'planDetails' => $planDetails,
            'planPerformance' => $planPerformance,

            'verificationStats' => $verificationStats,
            'thisMonthCount' => $thisMonthCount,
            'lastMonthCount' => $lastMonthCount,
            'monthlyChange' => $monthlyChange,
        ]);
    }

    private function getAgentPropertyQuery($user, $selectedPlanId = null)
    {
        $query = Property::query()
            ->where(function ($q) use ($user) {
                $q->where('registered_by', $user->id)
                  ->orWhereHas('registrationPlan', function ($planQuery) use ($user) {
                      $planQuery->whereHas('planAssignments', function ($assignmentQuery) use ($user) {
                          $assignmentQuery->where('agent_id', $user->id)
                                         ->where('is_active', true);
                      });
                  });
            });

        if ($selectedPlanId) {
            $query->where('registration_plan_id', $selectedPlanId);
        }

        return $query;
    }

    public function statistics(Request $request)
    {
        $user = auth()->user();

        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        // ------------------------------------------------------------------
        // Base query — reusable everywhere, respects date filters
        // ------------------------------------------------------------------
        $baseQuery = $this->getAgentPropertyQuery($user);

        if ($fromDate) {
            $baseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $baseQuery->whereDate('created_at', '<=', $toDate);
        }

        $totalProperties = (clone $baseQuery)->count();

        // ------------------------------------------------------------------
        // This-month count (own + plan-scoped, deduplicated via closure)
        // ------------------------------------------------------------------
        $thisMonthCount = (clone $baseQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // ------------------------------------------------------------------
        // Verification status counts
        // ------------------------------------------------------------------
        $verifiedCount = (clone $baseQuery)->where('verification_status', 'verified')->count();
        $pendingCount  = (clone $baseQuery)->where('verification_status', 'pending')->count();
        $rejectedCount = (clone $baseQuery)->where('verification_status', 'rejected')->count();

        // ------------------------------------------------------------------
        // Plan assignments
        // ------------------------------------------------------------------
        $allAssignments = PlanAgentAssignment::where('agent_id', $user->id)
            ->with('registrationPlan')
            ->get();

        $activeAssignments = $allAssignments->filter(function ($assignment) {
            $plan = $assignment->registrationPlan;
            return $assignment->is_active
                && $plan
                && in_array($plan->status, ['assigned', 'in_progress', 'active'], true);
        })->values();

        $completedAssignments = $allAssignments->filter(function ($assignment) {
            $plan = $assignment->registrationPlan;
            return $assignment->is_completed
                && $plan
                && $plan->status === 'completed';
        })->values();

        $activePlans         = $activeAssignments->count();
        $completedPlans      = $completedAssignments->count();
        $totalPlansAssigned  = $allAssignments->count();

        $completionRate = $totalPlansAssigned > 0
            ? round(($completedPlans / $totalPlansAssigned) * 100, 1)
            : 0;

        $averagePerPlan = $activePlans > 0
            ? round($totalProperties / $activePlans, 1)
            : 0;

        // ------------------------------------------------------------------
        // Verification rate (verified / total) — the blade reads this
        // ------------------------------------------------------------------
        $verificationRate = $totalProperties > 0
            ? round(($verifiedCount / $totalProperties) * 100, 1)
            : 0;

        // ------------------------------------------------------------------
        // Monthly trend (last 12 months)
        // ------------------------------------------------------------------
        $monthlyTrend = (clone $baseQuery)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->whereNotNull('created_at')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                try {
                    $label = \Carbon\Carbon::createFromFormat('Y-m', $item->month)->format('M Y');
                } catch (\Throwable $e) {
                    $label = (string) $item->month;
                }
                return (object) [
                    'month' => $label,
                    'count' => (int) $item->count,
                ];
            })
            ->values();

        // ------------------------------------------------------------------
        // Property type stats (standard + custom)
        // ------------------------------------------------------------------
        $propertyTypeStats = (clone $baseQuery)
            ->whereNotNull('property_type_id')
            ->select('property_type_id', DB::raw('count(*) as count'))
            ->with('propertyType')
            ->groupBy('property_type_id')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object) [
                    'type_id'    => $item->property_type_id,
                    'type_name'  => $item->propertyType?->name ?? 'Unknown',
                    'count'      => (int) $item->count,
                    'percentage' => $totalProperties > 0
                        ? round(($item->count / $totalProperties) * 100, 1)
                        : 0,
                    'is_custom'  => false,
                ];
            });

        $customTypeStats = (clone $baseQuery)
            ->whereNotNull('custom_property_type')
            ->where('custom_property_type', '!=', '')
            ->select('custom_property_type', DB::raw('count(*) as count'))
            ->groupBy('custom_property_type')
            ->get()
            ->map(function ($item) use ($totalProperties) {
                return (object) [
                    'type_id'    => null,
                    'type_name'  => $item->custom_property_type . ' (Custom)',
                    'count'      => (int) $item->count,
                    'percentage' => $totalProperties > 0
                        ? round(($item->count / $totalProperties) * 100, 1)
                        : 0,
                    'is_custom'  => true,
                ];
            });

        $allPropertyTypeStats = $propertyTypeStats
            ->concat($customTypeStats)
            ->sortByDesc('count')
            ->values();

        // ------------------------------------------------------------------
        // Per-plan performance
        // ------------------------------------------------------------------
        $planPerformance = $activeAssignments->map(function ($assignment) use ($user) {
            $plan = $assignment->registrationPlan;
            if (!$plan) return null;

            $totalForPlan   = (int) ($plan->estimated_houses ?? 0);
            $registeredHere = Property::where('registered_by', $user->id)
                ->where('registration_plan_id', $plan->id)
                ->count();

            $planTotalRegistered = Property::where('registration_plan_id', $plan->id)->count();

            return (object) [
                'plan_name'             => $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                'total_properties'      => $totalForPlan,
                'registered_count'      => $registeredHere,
                'plan_total_registered' => $planTotalRegistered,
                'completion_percentage' => $totalForPlan > 0
                    ? round(($registeredHere / $totalForPlan) * 100, 1)
                    : 0,
                'days_left'             => $plan->registration_end_date
                    ? now()->diffInDays($plan->registration_end_date, false)
                    : null,
                'status'                => $plan->status,
            ];
        })->filter()->values();

        // ------------------------------------------------------------------
        // Status distribution (verification_status)
        // ------------------------------------------------------------------
        $statusDistribution = (clone $baseQuery)
            ->select('verification_status as status', DB::raw('count(*) as count'))
            ->whereNotNull('verification_status')
            ->groupBy('verification_status')
            ->get()
            ->map(function ($item) {
                return (object) [
                    'status' => ucfirst($item->status ?? 'Unknown'),
                    'count'  => (int) $item->count,
                ];
            })
            ->values();

        // ------------------------------------------------------------------
        // Top landlords
        // ------------------------------------------------------------------
        $topLandlords = (clone $baseQuery)
            ->whereNotNull('landlord_id')
            ->select('landlord_id', DB::raw('count(*) as property_count'))
            ->with('landlord')
            ->groupBy('landlord_id')
            ->orderByDesc('property_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return (object) [
                    'name'           => $item->landlord?->name ?? 'Unknown',
                    'property_count' => (int) $item->property_count,
                ];
            })
            ->values();

        // ------------------------------------------------------------------
        // Recent activity
        // ------------------------------------------------------------------
        $recentActivity = (clone $baseQuery)
            ->with(['propertyType', 'landlord', 'registrationPlan'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($property) {
                return (object) [
                    'id'                    => $property->id,
                    'property_name'         => $property->property_name ?? 'Unnamed',
                    'registration_pattern'  => $property->registration_pattern,
                    'status'                => $property->status,
                    'verification_status'   => $property->verification_status,
                    'created_at'            => $property->created_at,
                    'property_type'         => $property->propertyType?->name
                                                ?? $property->custom_property_type
                                                ?? 'Unknown',
                    'landlord_name'         => $property->landlord?->name ?? 'Unknown',
                    'plan_name'             => $property->registrationPlan?->zone ?? 'N/A',
                ];
            })
            ->values();

        // ------------------------------------------------------------------
        // Week-over-week activity
        // ------------------------------------------------------------------
        $thisWeekCount = (clone $baseQuery)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $lastWeekCount = (clone $baseQuery)
            ->whereBetween('created_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])
            ->count();

        $weeklyChange = $lastWeekCount > 0
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
            : ($thisWeekCount > 0 ? 100 : 0);

        // ------------------------------------------------------------------
        // Photo coverage
        // ------------------------------------------------------------------
        $propertiesWithPhotos = (clone $baseQuery)->has('photos')->count();

        $photoCoverage = $totalProperties > 0
            ? round(($propertiesWithPhotos / $totalProperties) * 100, 1)
            : 0;

        // ------------------------------------------------------------------
        // Logging
        // ------------------------------------------------------------------
        \Log::info('Statistics data for agent', [
            'agent_id'                   => $user->id,
            'totalProperties'            => $totalProperties,
            'thisMonthCount'             => $thisMonthCount,
            'verifiedCount'              => $verifiedCount,
            'completionRate'             => $completionRate,
            'verificationRate'           => $verificationRate,
            'activePlans'                => $activePlans,
            'completedPlans'             => $completedPlans,
            'totalPlansAssigned'         => $totalPlansAssigned,
            'propertyTypeStats_count'    => $allPropertyTypeStats->count(),
            'monthlyTrend_count'         => $monthlyTrend->count(),
            'planPerformance_count'      => $planPerformance->count(),
            'statusDistribution_count'   => $statusDistribution->count(),
            'topLandlords_count'         => $topLandlords->count(),
            'recentActivity_count'       => $recentActivity->count(),
            'fromDate'                   => $fromDate,
            'toDate'                     => $toDate,
        ]);

        return view('field-agent.statistics', [
            'totalProperties'      => $totalProperties,
            'activePlans'          => $activePlans,
            'completedPlans'       => $completedPlans,
            'totalPlansAssigned'   => $totalPlansAssigned,
            'thisMonthCount'       => $thisMonthCount,
            'verifiedCount'        => $verifiedCount,
            'pendingCount'         => $pendingCount,
            'rejectedCount'        => $rejectedCount,
            'completionRate'       => $completionRate,
            'verificationRate'     => $verificationRate,
            'averagePerPlan'       => $averagePerPlan,

            'monthlyTrend'         => $monthlyTrend,
            'propertyTypeStats'    => $allPropertyTypeStats,

            'planPerformance'      => $planPerformance,
            'statusDistribution'   => $statusDistribution,
            'topLandlords'         => $topLandlords,
            'recentActivity'       => $recentActivity,

            'thisWeekCount'        => $thisWeekCount,
            'lastWeekCount'        => $lastWeekCount,
            'weeklyChange'         => $weeklyChange,
            'propertiesWithPhotos' => $propertiesWithPhotos,
            'photoCoverage'        => $photoCoverage,

            'fromDate'             => $fromDate,
            'toDate'               => $toDate,
        ]);
    }
}