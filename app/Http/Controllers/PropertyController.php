<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PropertyPhoto;
use App\Models\SystemSetting;
use App\Services\PropertyRegistrationService;
use App\Services\GoogleMapsService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Http\Traits\PropertyAuthorizationTrait;
use App\Http\Traits\PropertyViewTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    use PropertyAuthorizationTrait, PropertyViewTrait;

    protected $registrationService;
    protected $googleMapsService;

    public function __construct(
        PropertyRegistrationService $registrationService,
        GoogleMapsService $googleMapsService
    ) {
        $this->registrationService = $registrationService;
        $this->googleMapsService   = $googleMapsService;
    }

    // ================================================================
    //  CHANNEL STATUS RESOLUTION
    // ================================================================

    /**
     * Resolve the system-ready flags for SMS, WhatsApp, and Email.
     */
    protected function resolveChannelStatuses(): array
    {
        // --------------------------------------------------------------
        // SMS
        // --------------------------------------------------------------
        $smsStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'SMS service unavailable'];

        try {
            if (class_exists(SmsService::class)) {
                $smsService = app(SmsService::class);
                if (method_exists($smsService, 'getSystemStatus')) {
                    $resolved = $smsService->getSystemStatus();
                    if (is_array($resolved)) {
                        $smsStatus = $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve SMS system status: ' . $e->getMessage());
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
            Log::warning('Failed to resolve WhatsApp system status: ' . $e->getMessage());
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
        // Email
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
            Log::warning('Failed to resolve Email system status: ' . $e->getMessage());
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
     * Return true if the given channel status array indicates readiness.
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
     * Get the Google Maps browser API key for the map picker.
     *
     * Prefers `services.google_maps.browser_key` (restricted key meant
     * for browser use), falls back to `services.google_maps.key`.
     */
    protected function getGoogleMapsBrowserKey(): ?string
    {
        $key = config('services.google_maps.browser_key')
            ?? config('services.google_maps.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * ✅ NEW: Attach coordinates to the given property data array.
     *
     * Priority:
     *   1. Coordinates already in the payload (map picker / hidden inputs).
     *   2. Server-side geocoding via GoogleMapsService using the digital address.
     *
     * Returns the same array with `latitude`, `longitude`, `city` set when possible.
     */
    protected function resolveCoordinates(array $data, bool $onlyIfMissing = false): array
    {
        $hasLat = isset($data['latitude'])  && $data['latitude']  !== '' && $data['latitude']  !== null;
        $hasLng = isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null;

        // If both exist and we only care about missing values, do nothing.
        if ($onlyIfMissing && $hasLat && $hasLng) {
            return $data;
        }

        // If neither is provided but digital address is, try to geocode.
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
                Log::warning('Coordinate geocoding failed — saving without coordinates', [
                    'digital_address' => $data['digital_address'] ?? null,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        // Normalize empty strings to null so the model's mutators behave.
        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Display a listing of properties
     */
    public function index(Request $request)
    {
        $query = Property::with(['landlord', 'registrationPlan', 'registeredBy', 'propertyType', 'tenants', 'photos']);

        // Apply filters
        $this->applyFilters($query, $request);

        // Add photo count to the query
        $query->withCount('photos');

        $properties = $query->latest()->paginate(20);

        // Get data for filter dropdowns
        $registrationPlans = RegistrationPlan::whereNull('deleted_at')->get();
        $landlords = User::where(function($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function($q) {
                      $q->where('slug', 'landlord');
                  });
        })->get();
        $propertyTypes = PropertyType::active()->ordered()->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'site_allocations' => $properties,
                'message' => 'Site allocations retrieved successfully'
            ]);
        }

        return view($this->getView('properties.index'), compact('properties', 'registrationPlans', 'landlords', 'propertyTypes'));
    }

    /**
     * Show the form for creating a new property
     */
    public function create()
    {
        $landlords = User::where(function($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function($q) {
                      $q->where('slug', 'landlord');
                  });
        })->orderBy('name')->get();

        $registrationPlans = RegistrationPlan::whereNull('deleted_at')
            ->whereIn('status', ['assigned', 'in_progress', 'draft'])
            ->get();

        $propertyTypes = PropertyType::active()->ordered()->get();

        // Resolve channel readiness so the view can correctly gate the
        // invitation channels (SMS / WhatsApp / Email).
        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        // ✅ NEW: Google Maps browser key for the coordinate picker
        $googleMapsKey = $this->getGoogleMapsBrowserKey();

        return view($this->getView('properties.create'), compact(
            'landlords',
            'registrationPlans',
            'propertyTypes',
            'smsStatus',
            'whatsappStatus',
            'emailStatus',
            'googleMapsKey'
        ));
    }

    /**
     * Store a newly created property
     */
    public function store(Request $request)
    {
        // Validate authorization
        if (!$this->canCreateProperty($request)) {
            return $this->unauthorizedResponse($request, 'Only administrators can register site allocations.');
        }

        // Validate request
        $validator = $this->validatePropertyCreation($request);
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        // Determine if we're dealing with existing or new landlord
        $landlordId = $request->landlord_id;
        $isNewLandlord = empty($landlordId) && !empty($request->landlord_phones);

        try {
            DB::beginTransaction();

            // Handle new landlord creation first if needed
            if ($isNewLandlord) {
                $landlordResult = $this->createOrGetLandlord($request);
                if (!$landlordResult['success']) {
                    throw new \Exception($landlordResult['message']);
                }
                $landlordId = $landlordResult['landlord_id'];
                $request->merge(['landlord_id' => $landlordId]);

                // After creating/finding landlord, check for duplicates
                $propertyData = [
                    'property_name' => $request->property_name,
                    'street_name' => $request->street_name,
                    'house_number' => $request->house_number,
                    'digital_address' => $request->digital_address,
                ];

                if ($this->isDuplicateProperty($landlordId, $propertyData)) {
                    $similarProperties = $this->getSimilarProperties($landlordId, $propertyData);

                    DB::rollBack();

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This property already exists for this landlord. Duplicate prevented.',
                            'similar_properties' => $similarProperties,
                            'duplicate_prevention' => true
                        ], 409);
                    }

                    return redirect()->back()
                        ->with('error', 'This property already exists for this landlord. Please check the properties below:')
                        ->with('similar_properties', $similarProperties)
                        ->withInput();
                }
            } else {
                // Check for duplicates with existing landlord BEFORE creation
                $propertyData = [
                    'property_name' => $request->property_name,
                    'street_name' => $request->street_name,
                    'house_number' => $request->house_number,
                    'digital_address' => $request->digital_address,
                ];

                if ($this->isDuplicateProperty($landlordId, $propertyData)) {
                    $similarProperties = $this->getSimilarProperties($landlordId, $propertyData);

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This property may already exist for this landlord.',
                            'similar_properties' => $similarProperties,
                            'duplicate_prevention' => true
                        ], 409);
                    }

                    return redirect()->back()
                        ->with('error', 'This property appears to already exist. Please check the properties below:')
                        ->with('similar_properties', $similarProperties)
                        ->withInput();
                }
            }

            // ✅ NEW: resolve coordinates BEFORE handing off to the service
            $payload = $request->all();
            $payload = $this->resolveCoordinates($payload, /* onlyIfMissing */ false);

            // Use registration service to create property
            $result = $this->registrationService->createProperty(
                $payload,
                auth()->user()
            );

            if ($result['success']) {
                // ✅ Safety net: ensure coordinates persisted even if the service
                // did not whitelist them. Only writes if the column is empty.
                $createdProperty = Property::find($result['site_allocation']['id'] ?? null);

                if ($createdProperty && $createdProperty->has_coordinates === false) {
                    $patched = $this->resolveCoordinates([
                        'digital_address' => $createdProperty->digital_address,
                        'latitude'        => $payload['latitude']  ?? null,
                        'longitude'       => $payload['longitude'] ?? null,
                        'city'            => $payload['city']      ?? null,
                    ], /* onlyIfMissing */ true);

                    $coordUpdate = array_filter([
                        'latitude'  => $patched['latitude']  ?? null,
                        'longitude' => $patched['longitude'] ?? null,
                        'city'      => $patched['city']      ?? null,
                    ], fn ($v) => $v !== null);

                    if (!empty($coordUpdate)) {
                        $createdProperty->update($coordUpdate);
                    }
                }

                // Handle photo uploads if present
                if ($request->hasFile('property_photos')) {
                    $property = $createdProperty ?? Property::find($result['site_allocation']['id']);
                    if ($property) {
                        $this->handlePhotoUploads($request, $property);
                    }
                }

                DB::commit();
                return $this->successResponse($request, $result);
            } else {
                throw new \Exception($result['message'] ?? 'Failed to create property');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create property: ' . $e->getMessage(), [
                'request_data' => $request->except(['password', 'password_confirmation']),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * Display the specified property
     */
    public function show(Property $property, Request $request)
    {
        // Authorization check
        if (!$this->canViewProperty($property)) {
            return $this->unauthorizedResponse($request, 'Unauthorized to view this site allocation.');
        }

        // Load relationships including units
        $property->load([
            'landlord',
            'registrationPlan.continuedFromPlan',
            'registeredBy',
            'propertyType',
            'tenants',
            'photos',
            'units'
        ]);

        // Get all tenants (direct + through units)
        $allTenants = $this->getAllTenantsForProperty($property);

        // Also get pending tenant assignments for this property
        $pendingAssignments = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->with(['tenant', 'requestedBy'])
            ->get();

        // Get all unit assignments with tenant info for this property
        $unitAssignments = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->whereNotNull('tenant_id')
            ->with(['tenant', 'approvedBy', 'requestedBy'])
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'site_allocation' => $property,
                'all_tenants' => $allTenants,
                'pending_assignments' => $pendingAssignments,
                'unit_assignments' => $unitAssignments,
                'message' => 'Site allocation details retrieved successfully'
            ]);
        }

        return view($this->getView('properties.show'), compact(
            'property',
            'allTenants',
            'pendingAssignments',
            'unitAssignments'
        ));
    }

    /**
     * Get all tenants for a property (direct + through units)
     */
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

        $allTenants = $directTenants->merge($unitTenants)->merge($pendingTenants)->unique('id');

        return $allTenants;
    }

    /**
     * Show the form for editing the specified property
     */
    public function edit(Property $property)
    {
        if (!$this->canUpdateProperty($property)) {
            abort(403, 'Unauthorized to edit this site allocation.');
        }

        $landlords = User::where(function($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function($q) {
                      $q->where('slug', 'landlord');
                  });
        })->orderBy('name')->get();

        $registrationPlans = RegistrationPlan::whereNull('deleted_at')
            ->whereIn('status', ['assigned', 'in_progress', 'draft'])
            ->get();

        $propertyTypes = PropertyType::active()->ordered()->get();
        $customType = PropertyType::where('slug', 'custom')->first();

        $property->load('photos');

        // Resolve channel readiness so the edit view can also gate
        // invitation channels if it uses them.
        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        // ✅ NEW: Google Maps browser key for the coordinate picker
        $googleMapsKey = $this->getGoogleMapsBrowserKey();

        return view($this->getView('properties.edit'), compact(
            'property',
            'landlords',
            'registrationPlans',
            'propertyTypes',
            'customType',
            'smsStatus',
            'whatsappStatus',
            'emailStatus',
            'googleMapsKey'
        ));
    }

    /**
     * Update the specified property
     * ✅ FIXED: Added support for construction status
     * ✅ NEW: Preserves/updates coordinates
     */
    public function update(Request $request, Property $property)
    {
        if (!$this->canUpdateProperty($property)) {
            return $this->unauthorizedResponse($request, 'Unauthorized to update this site allocation.');
        }

        $validator = $this->validatePropertyUpdate($request);
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        if ($request->landlord_id != $property->landlord_id) {
            $propertyData = [
                'property_name' => $request->property_name ?? $property->property_name,
                'street_name' => $request->street_name ?? $property->street_name,
                'house_number' => $request->house_number ?? $property->house_number,
                'digital_address' => $request->digital_address ?? $property->digital_address,
            ];

            if ($this->isDuplicateProperty($request->landlord_id, $propertyData)) {
                $similarProperties = $this->getSimilarProperties($request->landlord_id, $propertyData);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This property already exists for the new landlord.',
                        'similar_properties' => $similarProperties,
                        'duplicate_prevention' => true
                    ], 409);
                }

                return redirect()->back()
                    ->with('error', 'This property already exists for the selected landlord.')
                    ->with('similar_properties', $similarProperties)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $updateData = $validator->validated();

            $customType = PropertyType::where('slug', 'custom')->first();
            if ($request->property_type_id == ($customType->id ?? null)) {
                $updateData['custom_property_type'] = $request->custom_property_type;
            } else {
                $updateData['custom_property_type'] = null;
            }

            // ✅ NEW: coordinate resolution for updates
            //   1. If the form submitted coordinates, honour them.
            //   2. If the digital address changed and no coordinates were
            //      submitted, re-geocode.
            //   3. If neither, leave the existing coordinates untouched.
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
                // Digital address changed, existing coordinates present — refresh silently
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

            // ✅ FIXED: Handle construction status if provided
            if ($request->has('construction_status')) {
                $updateData['construction_status'] = $request->construction_status;

                if ($request->construction_status === 'under_construction') {
                    $updateData['status'] = 'under_construction';
                } elseif ($request->construction_status === 'active' || $request->construction_status === 'completed') {
                    $updateData['status'] = 'active';
                } elseif ($request->construction_status === 'vacant') {
                    $updateData['status'] = 'vacant';
                } elseif ($request->construction_status === 'inactive') {
                    $updateData['status'] = 'inactive';
                }
            }

            $property->update($updateData);
            $this->handlePhotoManagement($request, $property);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Site allocation updated successfully',
                    'site_allocation' => $property->fresh()->load(['landlord', 'registrationPlan', 'propertyType', 'tenants', 'photos'])
                ]);
            }

            return redirect()->route($this->getRoute('properties.show'), $property->id)
                ->with('success', 'Site allocation updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update property: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to update site allocation. Please try again.');
        }
    }

    /**
     * ✅ NEW: Update coordinates only (admin / backfill endpoint)
     *
     * Accepts:
     *   PUT/POST /properties/{property}/coordinates
     *   { latitude, longitude, city? }
     *
     * Falls back to geocoding via the digital address if coords are omitted.
     */
    public function updateCoordinates(Request $request, Property $property)
    {
        if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized to update coordinates.');
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

            // If coordinates were omitted, attempt geocoding from digital address.
            $patched = $this->resolveCoordinates(
                array_merge([
                    'digital_address' => $property->digital_address,
                ], $data),
                /* onlyIfMissing */ true
            );

            $updatePayload = array_filter([
                'latitude'  => $patched['latitude']  ?? $property->latitude,
                'longitude' => $patched['longitude'] ?? $property->longitude,
                'city'      => $patched['city']      ?? $property->city,
            ], fn ($v) => $v !== null);

            if (empty($updatePayload)) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'No coordinates could be resolved.'], 422)
                    : redirect()->back()->with('error', 'No coordinates could be resolved.');
            }

            $property->update($updatePayload);

            Log::info('Property coordinates updated', [
                'property_id' => $property->id,
                'latitude'    => $property->latitude,
                'longitude'   => $property->longitude,
                'updated_by'  => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => true,
                    'message'   => 'Coordinates updated successfully.',
                    'property'  => $property->fresh(),
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

    /**
     * Remove the specified property (soft delete)
     */
    public function destroy(Property $property, Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can delete site allocations.');
        }

        DB::beginTransaction();

        try {
            foreach ($property->photos as $photo) {
                $photo->deletePhotoFile();
            }
            $property->delete();

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Site allocation deleted successfully'
                ]);
            }

            return redirect()->route('properties.index')
                ->with('success', 'Site allocation deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete property: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to delete site allocation. Please try again.');
        }
    }

    // ================================================================
    // ⭐⭐⭐ myProperties with Statistics ⭐⭐⭐
    // ================================================================

    public function myProperties(Request $request)
    {
        $user = auth()->user();

        $allProperties = Property::with(['registrationPlan', 'propertyType', 'tenants', 'photos'])
            ->where('landlord_id', $user->id)
            ->get();

        $stats = [
            'total' => $allProperties->count(),
            'active' => $allProperties->where('status', 'active')->count(),

            'under_construction' => $allProperties->filter(function($property) {
                return $property->status === 'under_construction' ||
                       $property->construction_status === 'under_construction';
            })->count(),

            'vacant_land' => $allProperties->filter(function($property) {
                return $property->status === 'vacant' || empty($property->status);
            })->count(),

            'vacant_land_with_plans' => $allProperties->filter(function($property) {
                $isVacant = $property->status === 'vacant' || empty($property->status);

                $hasPropertyType = $property->property_type_id !== null && $property->property_type_id != 0;
                $hasConstructionStatus = $property->construction_status !== null &&
                                         $property->construction_status !== '' &&
                                         $property->construction_status !== 'vacant';
                $hasPlans = $property->has_plans === true ||
                            $property->has_plans === 1 ||
                            $property->has_plans === '1' ||
                            $property->has_plans === 'yes';
                $hasConstructionDocs = $property->construction_documents &&
                                       is_array($property->construction_documents) &&
                                       count($property->construction_documents) > 0;
                $hasConstructionDetails = $hasPropertyType || $hasConstructionStatus || $hasPlans || $hasConstructionDocs;

                return $isVacant && $hasConstructionDetails;
            })->count(),

            'vacant_land_no_plans' => $allProperties->filter(function($property) {
                $isVacant = $property->status === 'vacant' || empty($property->status);

                $hasPropertyType = $property->property_type_id !== null && $property->property_type_id != 0;
                $hasConstructionStatus = $property->construction_status !== null &&
                                         $property->construction_status !== '' &&
                                         $property->construction_status !== 'vacant';
                $hasPlans = $property->has_plans === true ||
                            $property->has_plans === 1 ||
                            $property->has_plans === '1' ||
                            $property->has_plans === 'yes';
                $hasConstructionDocs = $property->construction_documents &&
                                       is_array($property->construction_documents) &&
                                       count($property->construction_documents) > 0;
                $hasConstructionDetails = $hasPropertyType || $hasConstructionStatus || $hasPlans || $hasConstructionDocs;

                return $isVacant && !$hasConstructionDetails;
            })->count(),

            'with_plans' => $allProperties->filter(function($property) {
                return $property->has_plans === true ||
                       ($property->construction_documents && count($property->construction_documents) > 0);
            })->count(),

            'with_digital_address' => $allProperties->whereNotNull('digital_address')->count(),

            // ✅ NEW: coordinate coverage stats
            'with_coordinates'     => $allProperties->filter(fn ($p) => $p->has_coordinates)->count(),
            'without_coordinates'  => $allProperties->filter(fn ($p) => !$p->has_coordinates)->count(),

            'rented' => $allProperties->where('is_rented', true)->count(),
        ];

        $query = Property::with(['registrationPlan', 'propertyType', 'tenants', 'photos'])
            ->where('landlord_id', $user->id)
            ->withCount('photos');

        $this->applyFilters($query, $request);
        $properties = $query->latest()->paginate(20);

        $registrationPlans = RegistrationPlan::active()->get();
        $propertyTypes = PropertyType::active()->ordered()->get();

        return view('landlord.properties.my-properties', compact(
            'properties',
            'stats',
            'registrationPlans',
            'propertyTypes'
        ));
    }

    public function myRoleBasedProperties(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('landlord')) {
            abort(403, 'Unauthorized. You do not have landlord privileges.');
        }

        $query = Property::with(['registrationPlan', 'propertyType', 'tenants', 'photos'])
            ->where('landlord_id', $user->id)
            ->withCount('photos');

        $this->applyFilters($query, $request);
        $properties = $query->latest()->paginate(20);

        $stats = [
            'total' => $properties->count(),
            'active' => $properties->where('status', 'active')->count(),

            'under_construction' => $properties->filter(function($property) {
                return $property->status === 'under_construction' ||
                       ($property->construction_status === 'under_construction') ||
                       ($property->construction_status === 'active' && (
                           $property->has_plans === true ||
                           ($property->construction_documents && count($property->construction_documents) > 0)
                       ));
            })->count(),

            'vacant_land' => $properties->filter(function($property) {
                return $property->status === 'vacant' &&
                       ($property->construction_status === null || $property->construction_status === 'vacant') &&
                       ($property->property_type_id === null || $property->property_type_id == '') &&
                       $property->has_plans !== true &&
                       (!$property->construction_documents || count($property->construction_documents) === 0);
            })->count(),

            'with_plans' => $properties->filter(function($property) {
                return $property->has_plans === true ||
                       ($property->construction_documents && count($property->construction_documents) > 0);
            })->count(),

            'with_digital_address' => $properties->whereNotNull('digital_address')->count(),

            // ✅ NEW: coordinate coverage stats
            'with_coordinates'     => $properties->filter(fn ($p) => $p->has_coordinates)->count(),
            'without_coordinates'  => $properties->filter(fn ($p) => !$p->has_coordinates)->count(),

            'rented' => $properties->where('is_rented', true)->count(),
        ];

        $registrationPlans = RegistrationPlan::active()->get();
        $propertyTypes = PropertyType::active()->ordered()->get();
        $userRoles = $user->roles->pluck('name')->toArray();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'properties' => $properties,
                'stats' => $stats,
                'user_roles' => $userRoles,
                'message' => 'Your properties retrieved successfully'
            ]);
        }

        return view('landlord.properties.my-properties', compact(
            'properties',
            'stats',
            'registrationPlans',
            'propertyTypes',
            'userRoles'
        ));
    }

    public function getAvailableLandlords(Request $request)
    {
        $search = $request->get('search', '');

        $query = User::where(function($q) {
            $q->where('type', User::TYPE_LANDLORD)
              ->orWhereHas('roles', function($roleQ) {
                  $roleQ->where('slug', 'landlord');
              });
        })->where('status', User::STATUS_ACTIVE);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $landlords = $query->orderBy('name')->limit(20)->get();

        $landlords->each(function($landlord) {
            $landlord->role_names = $landlord->role_names;
            $landlord->is_landlord_by_role = $landlord->hasRole('landlord');
        });

        return response()->json([
            'success' => true,
            'landlords' => $landlords
        ]);
    }

    public function transferOwnership(Request $request, Property $property)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can transfer property ownership.');
        }

        $validator = Validator::make($request->all(), [
            'new_landlord_id' => 'required|exists:users,id',
            'transfer_reason' => 'nullable|string|max:500',
            'effective_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        $newLandlord = User::find($request->new_landlord_id);

        if (!$newLandlord->isLandlord()) {
            return $this->unauthorizedResponse($request, 'The selected user does not have landlord privileges.');
        }

        $propertyData = [
            'property_name' => $property->property_name,
            'street_name' => $property->street_name,
            'house_number' => $property->house_number,
            'digital_address' => $property->digital_address,
        ];

        if ($this->isDuplicateProperty($newLandlord->id, $propertyData)) {
            $similarProperties = $this->getSimilarProperties($newLandlord->id, $propertyData);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property already exists for the new landlord.',
                    'similar_properties' => $similarProperties
                ], 409);
            }

            return redirect()->back()
                ->with('error', 'This property already exists for the selected landlord.')
                ->with('similar_properties', $similarProperties);
        }

        DB::beginTransaction();

        try {
            $oldLandlordId = $property->landlord_id;
            $oldLandlord = User::find($oldLandlordId);

            $property->update([
                'landlord_id' => $newLandlord->id,
                'metadata' => array_merge($property->metadata ?? [], [
                    'ownership_transferred' => [
                        'from_landlord_id' => $oldLandlordId,
                        'from_landlord_name' => $oldLandlord?->name,
                        'to_landlord_id' => $newLandlord->id,
                        'to_landlord_name' => $newLandlord->name,
                        'transferred_at' => now()->toISOString(),
                        'transferred_by' => auth()->id(),
                        'transfer_reason' => $request->transfer_reason,
                        'effective_date' => $request->effective_date ?? now()->toDateString(),
                    ]
                ])
            ]);

            Log::info('Property ownership transferred', [
                'property_id' => $property->id,
                'old_landlord_id' => $oldLandlordId,
                'new_landlord_id' => $newLandlord->id,
                'transferred_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Property ownership transferred to {$newLandlord->name} successfully",
                    'property' => $property->fresh(['landlord'])
                ]);
            }

            return redirect()->route($this->getRoute('properties.show'), $property->id)
                ->with('success', "Property ownership transferred to {$newLandlord->name} successfully");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to transfer property ownership: ' . $e->getMessage());
            return $this->errorResponse($request, 'Failed to transfer property ownership.');
        }
    }

    public function checkDuplicate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'landlord_id' => 'required|exists:users,id',
            'property_name' => 'required|string|max:255',
            'street_name' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $propertyData = [
            'property_name' => $request->property_name,
            'street_name' => $request->street_name,
            'house_number' => $request->house_number,
            'digital_address' => $request->digital_address,
        ];

        $isDuplicate = $this->isDuplicateProperty($request->landlord_id, $propertyData);
        $similarProperties = [];

        if ($isDuplicate) {
            $similarProperties = $this->getSimilarProperties($request->landlord_id, $propertyData);
        }

        return response()->json([
            'success' => true,
            'is_duplicate' => $isDuplicate,
            'similar_properties' => $similarProperties,
            'message' => $isDuplicate ? 'Similar property found for this landlord' : 'No duplicates found'
        ]);
    }

    // ================================================================
    // ⭐⭐⭐ CONSTRUCTION DETAILS UPDATE METHODS ⭐⭐⭐
    // ================================================================

    public function updateConstruction(Request $request)
    {
        Log::info('updateConstruction method called', [
            'all_data' => $request->all(),
            'user_id' => auth()->id(),
            'user_type' => auth()->user()?->type,
            'is_ajax' => $request->ajax(),
            'expects_json' => $request->expectsJson(),
            'method' => $request->method(),
        ]);

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'property_type_id' => 'required|string',
            'custom_property_type' => 'nullable|string|max:100',
            'construction_status' => 'required|in:under_construction,active,vacant,inactive',
            'estimated_bedrooms' => 'nullable|integer|min:1|max:50',
            'estimated_completion' => 'nullable|date|after:today',
            'has_plans' => 'nullable|in:yes,no',
            'construction_notes' => 'nullable|string|max:1000',
            'construction_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'declaration' => 'required|accepted',
        ], [
            'estimated_completion.after' => 'Estimated completion date must be in the future.',
            'property_type_id.required' => 'Please select a property type.',
            'construction_status.required' => 'Please select a construction status.',
            'declaration.accepted' => 'You must accept the declaration to proceed.',
            'construction_documents.*.max' => 'Each document must not exceed 10MB.',
            'construction_documents.*.mimes' => 'Documents must be PDF, JPG, JPEG, or PNG files.',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for construction update', [
                'errors' => $validator->errors()->toArray()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed. Please check your input.'
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $property = Property::findOrFail($request->property_id);
            Log::info('Property found', [
                'property_id' => $property->id,
                'property_name' => $property->property_name,
                'landlord_id' => $property->landlord_id,
                'current_user_id' => auth()->id(),
                'current_status' => $property->status,
                'current_construction_status' => $property->construction_status,
                'current_property_type_id' => $property->property_type_id,
            ]);

            $isOwner = $property->landlord_id === auth()->id();
            $isAdmin = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin();

            if (!$isOwner && !$isAdmin) {
                Log::warning('Unauthorized access to construction update', [
                    'property_id' => $property->id,
                    'user_id' => auth()->id(),
                    'is_owner' => $isOwner,
                    'is_admin' => $isAdmin
                ]);

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to update this property'
                    ], 403);
                }

                return redirect()->back()->with('error', 'You do not have permission to update this property.');
            }

            $hasPropertyType = $property->property_type_id !== null &&
                               $property->property_type_id !== '' &&
                               $property->property_type_id != 0;

            $hasConstructionStatus = $property->construction_status !== null &&
                                     $property->construction_status !== '' &&
                                     $property->construction_status !== 'vacant';

            $hasPlans = $property->has_plans === true ||
                        $property->has_plans === 1 ||
                        $property->has_plans === '1' ||
                        $property->has_plans === 'yes';

            $hasConstructionDocs = $property->construction_documents &&
                                   is_array($property->construction_documents) &&
                                   count($property->construction_documents) > 0;

            $hasConstructionDetails = $hasPropertyType ||
                                      $hasConstructionStatus ||
                                      $hasPlans ||
                                      $hasConstructionDocs;

            $isVacantLand = !$hasConstructionDetails &&
                           ($property->status === 'vacant' ||
                            $property->status === 'under_construction' ||
                            $property->status === null) &&
                           (empty($property->construction_status) ||
                            $property->construction_status === 'vacant') &&
                           (empty($property->property_type_id) ||
                            $property->property_type_id == 0);

            $isUnderConstruction = $property->status === 'under_construction' ||
                                  $property->construction_status === 'under_construction' ||
                                  ($property->construction_status === 'active' && $hasConstructionDetails);

            $isActiveWithConstruction = $property->status === 'active' &&
                                        $property->construction_status === 'active' &&
                                        $hasPropertyType;

            $canUpdate = $isVacantLand || $isUnderConstruction || $isActiveWithConstruction;

            Log::info('Eligibility check results', [
                'property_id' => $property->id,
                'hasPropertyType' => $hasPropertyType,
                'hasConstructionStatus' => $hasConstructionStatus,
                'hasPlans' => $hasPlans,
                'hasConstructionDocs' => $hasConstructionDocs,
                'hasConstructionDetails' => $hasConstructionDetails,
                'isVacantLand' => $isVacantLand,
                'isUnderConstruction' => $isUnderConstruction,
                'isActiveWithConstruction' => $isActiveWithConstruction,
                'canUpdate' => $canUpdate,
            ]);

            if (!$canUpdate && !$isAdmin) {
                Log::warning('Property is not eligible for construction update', [
                    'property_id' => $property->id,
                    'property_type_id' => $property->property_type_id,
                    'status' => $property->status,
                    'construction_status' => $property->construction_status,
                    'is_vacant_land' => $isVacantLand,
                    'is_under_construction' => $isUnderConstruction,
                    'is_active_with_construction' => $isActiveWithConstruction,
                    'has_construction_details' => $hasConstructionDetails,
                ]);

                $message = 'This property is not eligible for construction updates. ';
                if ($property->status === 'active' && !$hasConstructionDetails) {
                    $message .= 'This is an active property without construction details. Please contact administration.';
                } else {
                    $message .= 'Current status: ' . ucfirst($property->status) . '.';
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'property' => [
                            'id' => $property->id,
                            'status' => $property->status,
                            'construction_status' => $property->construction_status,
                            'has_construction_details' => $hasConstructionDetails,
                            'is_vacant_land' => $isVacantLand,
                        ]
                    ], 400);
                }

                return redirect()->back()->with('error', $message);
            }

            $propertyTypeId = null;
            $customPropertyTypeValue = null;
            $typeValue = $request->property_type_id;

            $stringTypes = ['residential', 'apartment', 'commercial', 'mixed', 'other'];

            if (in_array($typeValue, $stringTypes)) {
                if ($typeValue === 'other') {
                    $customPropertyTypeValue = $request->custom_property_type;

                    if (empty($customPropertyTypeValue)) {
                        if ($request->expectsJson() || $request->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Please specify the property type',
                                'errors' => ['custom_property_type' => ['Please specify the property type']]
                            ], 422);
                        }
                        return redirect()->back()->with('error', 'Please specify the property type');
                    }

                    $propertyType = PropertyType::where('slug', 'custom')
                        ->where('name', $customPropertyTypeValue)
                        ->first();

                    if (!$propertyType) {
                        $propertyType = PropertyType::create([
                            'name' => $customPropertyTypeValue,
                            'slug' => Str::slug($customPropertyTypeValue),
                            'is_active' => true,
                            'created_by' => auth()->id()
                        ]);
                    }
                    $propertyTypeId = $propertyType->id;
                } else {
                    $typeMap = [
                        'residential' => ['name' => 'Residential House', 'slug' => 'residential'],
                        'apartment' => ['name' => 'Apartment Building', 'slug' => 'apartment'],
                        'commercial' => ['name' => 'Commercial Property', 'slug' => 'commercial'],
                        'mixed' => ['name' => 'Mixed Use Property', 'slug' => 'mixed'],
                    ];

                    $typeData = $typeMap[$typeValue] ?? null;

                    if ($typeData) {
                        $propertyType = PropertyType::where('slug', $typeData['slug'])->first();

                        if (!$propertyType) {
                            $propertyType = PropertyType::create([
                                'name' => $typeData['name'],
                                'slug' => $typeData['slug'],
                                'is_active' => true,
                                'created_by' => auth()->id()
                            ]);
                        }
                        $propertyTypeId = $propertyType->id;
                    }
                }
            } elseif (is_numeric($typeValue)) {
                $propertyType = PropertyType::find($typeValue);
                if ($propertyType) {
                    $propertyTypeId = $propertyType->id;
                }
            }

            $documents = $property->construction_documents ?? [];
            if ($request->hasFile('construction_documents')) {
                foreach ($request->file('construction_documents') as $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('construction-documents/' . $property->id, 'public');
                        $documents[] = $path;
                    }
                }
            }

            $constructionStatus = $request->construction_status;
            $hasPlans = $request->has_plans === 'yes';
            $hasDocuments = count($documents) > 0;
            $hasPropertyType = $propertyTypeId !== null;

            if ($constructionStatus === 'vacant') {
                $propertyStatus = 'vacant';
            } elseif ($constructionStatus === 'under_construction') {
                $propertyStatus = 'under_construction';
            } elseif ($constructionStatus === 'active' || $constructionStatus === 'completed') {
                $propertyStatus = 'active';
            } elseif ($constructionStatus === 'inactive' || $constructionStatus === 'paused') {
                $propertyStatus = 'inactive';
            } else {
                $propertyStatus = $constructionStatus;
            }

            $updateData = [
                'property_type_id' => $propertyTypeId,
                'custom_property_type' => $customPropertyTypeValue,
                'construction_status' => $constructionStatus,
                'bedrooms' => $request->estimated_bedrooms,
                'estimated_completion' => $request->estimated_completion,
                'has_plans' => $hasPlans,
                'construction_notes' => $request->construction_notes,
                'construction_documents' => $documents,
                'status' => $propertyStatus,
            ];

            if ($constructionStatus === 'vacant') {
                $updateData['property_type_id'] = null;
                $updateData['custom_property_type'] = null;
                $updateData['bedrooms'] = null;
                $updateData['estimated_completion'] = null;
                $updateData['has_plans'] = false;
            }

            $property->update($updateData);

            if (method_exists($property, 'logActivity')) {
                $typeLabel = $request->property_type_id === 'other'
                    ? $request->custom_property_type
                    : ($request->property_type_id ?? 'Not specified');

                $property->logActivity('construction_details_updated',
                    'Construction details updated by: ' . auth()->user()->name .
                    ' | Status: ' . $constructionStatus .
                    ' | Type: ' . $typeLabel .
                    ' | Has Plans: ' . ($hasPlans ? 'Yes' : 'No') .
                    ' | Documents: ' . count($documents)
                );
            }

            DB::commit();

            Log::info('Construction details updated successfully', [
                'property_id' => $property->id,
                'property_name' => $property->property_name,
                'updated_by' => auth()->id(),
                'construction_status' => $constructionStatus,
                'property_status' => $propertyStatus,
                'property_type_id' => $propertyTypeId,
                'has_plans' => $hasPlans,
                'documents_count' => count($documents),
                'was_vacant_land' => $isVacantLand,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Construction details updated successfully!',
                    'property' => $property->fresh(['propertyType']),
                    'status_changes' => [
                        'previous_status' => $property->getOriginal('status'),
                        'new_status' => $propertyStatus,
                        'previous_construction_status' => $property->getOriginal('construction_status'),
                        'new_construction_status' => $constructionStatus,
                        'was_vacant_land' => $isVacantLand,
                        'is_now_under_construction' => $propertyStatus === 'under_construction',
                    ]
                ]);
            }

            return redirect()->back()->with('success', 'Construction details updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update construction details: ' . $e->getMessage(), [
                'property_id' => $request->property_id ?? null,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update construction details. Please try again.',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update construction details. Please try again.');
        }
    }

    public function updateConstructionWithProperty(Request $request, Property $property)
    {
        $request->merge(['property_id' => $property->id]);
        return $this->updateConstruction($request);
    }

    // ==================== PHOTO MANAGEMENT METHODS ====================

    private function handlePhotoUploads(Request $request, Property $property): void
    {
        if ($request->hasFile('property_photos')) {
            $files = $request->file('property_photos');
            $isFirstPhoto = true;

            foreach ($files as $file) {
                $isPrimary = $isFirstPhoto && $property->photos()->count() === 0;
                $property->addPhoto($file, $isPrimary);
                $isFirstPhoto = false;
            }
        }
    }

    private function handlePhotoManagement(Request $request, Property $property): void
    {
        if ($request->filled('delete_photos')) {
            $photoIds = explode(',', $request->delete_photos);
            foreach ($photoIds as $photoId) {
                if (is_numeric($photoId)) {
                    $property->deletePhoto((int)$photoId);
                }
            }
        }

        if ($request->filled('primary_photo_id')) {
            $property->setPrimaryPhoto($request->primary_photo_id);
        }

        if ($request->hasFile('property_photos')) {
            $files = $request->file('property_photos');
            $currentPhotoCount = $property->photos()->count();
            $maxPhotos = 10;
            $availableSlots = $maxPhotos - $currentPhotoCount;

            if ($availableSlots > 0) {
                $filesToUpload = array_slice($files, 0, $availableSlots);
                $isFirstNewPhoto = $currentPhotoCount === 0;

                foreach ($filesToUpload as $file) {
                    $isPrimary = $isFirstNewPhoto && $property->photos()->count() === 0;
                    $property->addPhoto($file, $isPrimary);
                    $isFirstNewPhoto = false;
                }
            }
        }
    }

    public function uploadPhotos(Request $request, Property $property)
    {
        if (!$this->canUpdateProperty($property)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|max:10',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $files = $request->file('photos');
            $results = [];

            foreach ($files as $file) {
                $isPrimary = $property->photos()->count() === 0;
                $photo = $property->addPhoto($file, $isPrimary);
                $results[] = [
                    'success' => true,
                    'photo_id' => $photo->id,
                    'photo_url' => $photo->photo_url,
                    'thumbnail_url' => $photo->thumbnail_url,
                    'is_primary' => $photo->is_primary
                ];
            }

            return response()->json([
                'success' => true,
                'message' => count($files) . ' photo(s) uploaded successfully',
                'photos' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload photos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to upload photos'], 500);
        }
    }

    public function setPrimaryPhoto(Request $request, Property $property, $photoId)
    {
        if (!$this->canUpdateProperty($property)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $success = $property->setPrimaryPhoto($photoId);
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Primary photo updated successfully' : 'Photo not found'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to set primary photo'], 500);
        }
    }

    public function deletePhoto(Request $request, Property $property, $photoId)
    {
        if (!$this->canUpdateProperty($property)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $success = $property->deletePhoto($photoId);
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Photo deleted successfully' : 'Photo not found'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete photo'], 500);
        }
    }

    public function getPhotoGallery(Property $property)
    {
        if (!$this->canViewProperty($property)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $photos = $property->photos()
                ->orderBy('is_primary', 'desc')
                ->orderBy('sort_order', 'asc')
                ->get()
                ->map(function($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => $photo->photo_url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'medium_url' => $photo->medium_url,
                        'is_primary' => $photo->is_primary,
                        'sort_order' => $photo->sort_order,
                        'caption' => $photo->caption,
                        'file_size_formatted' => $photo->file_size_formatted,
                        'created_at' => $photo->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'photos' => $photos,
                'total' => $photos->count(),
                'has_photos' => $photos->count() > 0,
                'primary_photo' => $photos->firstWhere('is_primary', true)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to retrieve photos'], 500);
        }
    }

    public function landlordUploadPhotos(Request $request, Property $property)
    {
        if ($property->landlord_id !== auth()->id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this property.'
                ], 403);
            }
            return redirect()->back()->with('error', 'You do not own this property.');
        }

        $currentCount = $property->photos()->count();
        if ($currentCount >= 10) {
            $message = 'Maximum 10 photos allowed per property.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->back()->with('error', $message);
        }

        $maxAllowed = 10 - $currentCount;

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|max:' . $maxAllowed,
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $files = $request->file('photos');
            $uploaded = [];

            foreach ($files as $file) {
                if ($property->photos()->count() >= 10) {
                    break;
                }

                $isPrimary = $property->photos()->count() === 0;
                $photo = $property->addPhoto($file, $isPrimary);
                $uploaded[] = $photo;
            }

            $message = count($uploaded) . ' photo(s) uploaded successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'photos' => $uploaded,
                    'total' => $property->photos()->count()
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to upload photos: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'user_id' => auth()->id()
            ]);

            $errorMessage = 'Failed to upload photos. Please try again.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    public function landlordDeletePhoto(Request $request, Property $property, $photoId)
    {
        if ($property->landlord_id !== auth()->id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this property.'
                ], 403);
            }
            return redirect()->back()->with('error', 'You do not own this property.');
        }

        try {
            $photo = $property->photos()->find($photoId);

            if (!$photo) {
                $message = 'Photo not found.';
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 404);
                }
                return redirect()->back()->with('error', $message);
            }

            if ($photo->is_primary && $property->photos()->count() > 1) {
                $newPrimary = $property->photos()->where('id', '!=', $photoId)->first();
                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true]);
                }
            }

            $photo->deletePhotoFile();
            $photo->delete();

            $message = 'Photo deleted successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'remaining' => $property->photos()->count()
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to delete photo: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'photo_id' => $photoId,
                'user_id' => auth()->id()
            ]);

            $errorMessage = 'Failed to delete photo. Please try again.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    public function landlordSetPrimaryPhoto(Request $request, Property $property, $photoId)
    {
        if ($property->landlord_id !== auth()->id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this property.'
                ], 403);
            }
            return redirect()->back()->with('error', 'You do not own this property.');
        }

        try {
            $photo = $property->photos()->find($photoId);

            if (!$photo) {
                $message = 'Photo not found.';
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 404);
                }
                return redirect()->back()->with('error', $message);
            }

            $success = $property->setPrimaryPhoto($photoId);

            $message = $success ? 'Primary photo updated successfully.' : 'Failed to set primary photo.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => $success,
                    'message' => $message
                ]);
            }

            return redirect()->back()->with($success ? 'success' : 'error', $message);

        } catch (\Exception $e) {
            Log::error('Failed to set primary photo: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'photo_id' => $photoId,
                'user_id' => auth()->id()
            ]);

            $errorMessage = 'Failed to set primary photo. Please try again.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    public function landlordGetPhotoGallery(Property $property)
    {
        if ($property->landlord_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this property.'
            ], 403);
        }

        try {
            $photos = $property->photos()
                ->orderBy('is_primary', 'desc')
                ->orderBy('sort_order', 'asc')
                ->get()
                ->map(function($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => $photo->photo_url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'medium_url' => $photo->medium_url,
                        'is_primary' => $photo->is_primary,
                        'sort_order' => $photo->sort_order,
                        'caption' => $photo->caption,
                        'file_size_formatted' => $photo->file_size_formatted,
                        'created_at' => $photo->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'photos' => $photos,
                'total' => $photos->count(),
                'has_photos' => $photos->count() > 0,
                'primary_photo' => $photos->firstWhere('is_primary', true)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve photo gallery: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve photos'
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    private function applyFilters($query, Request $request)
    {
        $filters = [
            'street_name' => 'like',
            'landlord_id' => 'exact',
            'zone' => 'like',
            'status' => 'exact',
            'digital_address' => 'like',
            'registration_plan_id' => 'exact',
            'house_number' => 'like',
            'block_number' => 'like',
            'property_type_id' => 'exact',
            'custom_property_type' => 'like'
        ];

        foreach ($filters as $field => $type) {
            if ($request->filled($field)) {
                if ($type === 'like') {
                    $query->where($field, 'like', '%' . $request->$field . '%');
                } else {
                    $query->where($field, $request->$field);
                }
            }
        }

        if ($request->filled('has_photos')) {
            if ($request->has_photos === '1') {
                $query->has('photos');
            } elseif ($request->has_photos === '0') {
                $query->doesntHave('photos');
            }
        }

        if ($request->filled('is_rented')) {
            if ($request->is_rented == '1') {
                $query->whereHas('tenants');
            } else {
                $query->whereDoesntHave('tenants');
            }
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            if ($request->has_digital_address === '1') {
                $query->hasDigitalAddress();
            } else if ($request->has_digital_address === '0') {
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
            'property_type_id' => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name' => 'required|string|max:255',
            'landlord_id' => 'nullable|required_without_all:landlord_phones,landlord_name|exists:users,id',
            'landlord_phones' => 'nullable|array|min:1',
            'landlord_phones.*' => 'nullable|string|max:15',
            'landlord_name' => 'nullable|required_without:landlord_id|string|max:255',
            'landlord_email' => 'nullable|email|max:255',
            'house_number' => 'nullable|string|max:50',
            'street_name' => 'required|string|max:255',
            'block_number' => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'section' => 'nullable|string|max:100',
            // ✅ NEW: coordinates + city
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,under_maintenance,vacant',
            'registration_date' => 'required|date',
            'property_photos' => 'nullable|array|max:10',
        ];

        $messages = [
            'registration_plan_id.required' => 'Please select a registration plan.',
            'registration_plan_id.exists' => 'The selected registration plan is invalid.',
            'property_type_id.required' => 'Please select a property type.',
            'property_type_id.exists' => 'The selected property type is invalid.',
            'property_name.required' => 'Property name is required.',
            'street_name.required' => 'Street name is required.',
            'registration_date.required' => 'Registration date is required.',
            'registration_date.date' => 'Please provide a valid registration date.',
            'property_photos.max' => 'You can upload a maximum of 10 photos.',
            'landlord_name.required_without' => 'Landlord name is required when not selecting an existing landlord.',
            'landlord_phones.min' => 'At least one phone number is required for the landlord.',
            'landlord_phones.*.max' => 'Each phone number must not exceed 15 characters.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        $validator->after(function ($validator) use ($request) {
            if ($request->hasFile('property_photos')) {
                $files = $request->file('property_photos');

                $validFiles = [];
                foreach ($files as $index => $file) {
                    if ($file !== null && $file->isValid()) {
                        $validFiles[] = $file;
                    }
                }

                if (!empty($validFiles)) {
                    foreach ($validFiles as $index => $file) {
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                        $mimeType = $file->getMimeType();

                        if (!in_array($mimeType, $allowedMimes)) {
                            $validator->errors()->add(
                                "property_photos.{$index}",
                                "The file must be an image of type: JPEG, PNG, JPG, GIF, or WebP."
                            );
                        }

                        $maxSize = 5 * 1024 * 1024;
                        if ($file->getSize() > $maxSize) {
                            $validator->errors()->add(
                                "property_photos.{$index}",
                                "The file must not be larger than 5MB."
                            );
                        }

                        if ($file->getError() !== UPLOAD_ERR_OK) {
                            $validator->errors()->add(
                                "property_photos.{$index}",
                                "File upload failed. Please try again."
                            );
                        }
                    }
                }
            }
        });

        return $validator;
    }

    private function validatePropertyUpdate(Request $request)
    {
        return Validator::make($request->all(), [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'property_type_id' => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name' => 'required|string|max:255',
            'landlord_id' => 'required|exists:users,id',
            'house_number' => 'nullable|string|max:50',
            'street_name' => 'required|string|max:255',
            'block_number' => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'section' => 'nullable|string|max:100',
            // ✅ NEW: coordinates + city
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,under_maintenance,vacant',
            'construction_status' => 'nullable|in:under_construction,active,vacant,inactive,completed,paused',
            'has_plans' => 'nullable|boolean',
            'estimated_completion' => 'nullable|date',
            'construction_notes' => 'nullable|string|max:1000',
            'property_photos' => 'nullable|array|max:10',
            'property_photos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'delete_photos' => 'nullable|string',
            'primary_photo_id' => 'nullable|exists:property_photos,id',
        ]);
    }

    private function hasConstructionDetails(Property $property): bool
    {
        return $property->property_type_id !== null &&
               $property->property_type_id != 0 &&
               ($property->construction_status !== null && $property->construction_status !== 'vacant') ||
               $property->has_plans === true ||
               ($property->construction_documents && count($property->construction_documents) > 0);
    }

    private function isVacantLand(Property $property): bool
    {
        return $property->status === 'vacant' &&
               ($property->construction_status === null || $property->construction_status === 'vacant') &&
               ($property->property_type_id === null || $property->property_type_id == '') &&
               $property->has_plans !== true &&
               (!$property->construction_documents || count($property->construction_documents) === 0);
    }

    private function isUnderConstruction(Property $property): bool
    {
        return $property->status === 'under_construction' ||
               $property->construction_status === 'under_construction' ||
               ($property->construction_status === 'active' && $this->hasConstructionDetails($property));
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

        if (!empty($propertyData['street_name']) && !empty($propertyData['house_number'])) {
            if (Property::where('street_name', $propertyData['street_name'])
                ->where('house_number', $propertyData['house_number'])
                ->where('landlord_id', $landlordId)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function getSimilarProperties($landlordId, array $propertyData): array
    {
        if (!$landlordId || empty($propertyData['property_name'])) {
            return [];
        }

        $similar = [];
        $properties = Property::where('landlord_id', $landlordId)
            ->where('property_name', 'LIKE', '%' . $propertyData['property_name'] . '%')
            ->limit(10)
            ->get();

        foreach ($properties as $property) {
            similar_text(
                strtolower($property->property_name),
                strtolower($propertyData['property_name']),
                $similarity
            );

            if ($similarity > 60) {
                $similar[] = [
                    'id' => $property->id,
                    'property_name' => $property->property_name,
                    'digital_address' => $property->digital_address,
                    'street_name' => $property->street_name,
                    'house_number' => $property->house_number,
                    'similarity' => round($similarity, 2),
                    'has_photo' => $property->has_photos,
                ];
            }
        }

        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similar, 0, 5);
    }

    private function createOrGetLandlord(Request $request): array
    {
        $phone = $request->landlord_phones[0] ?? null;

        if (!$phone) {
            return ['success' => false, 'message' => 'Phone number is required'];
        }

        $existingLandlord = User::findByAnyPhoneFormat($phone);

        if ($existingLandlord) {
            if (!$existingLandlord->isLandlord()) {
                $existingLandlord->assignRole('landlord', [
                    'assigned_by' => auth()->id(),
                    'assignment_reason' => 'Auto-assigned during property registration',
                    'assigned_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'landlord_id' => $existingLandlord->id,
                'was_existing' => true
            ];
        }

        $temporaryPassword = Str::random(16);

        $landlord = User::create([
            'name' => $request->landlord_name,
            'phone' => $phone,
            'email' => $request->landlord_email,
            'type' => User::TYPE_LANDLORD,
            'status' => User::STATUS_ACTIVE,
            'created_by' => auth()->id(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => Hash::make($temporaryPassword),
        ]);

        $landlord->assignRole('landlord', [
            'assigned_by' => auth()->id(),
            'assignment_reason' => 'Created during property registration',
            'assigned_at' => now(),
        ]);

        return [
            'success' => true,
            'landlord_id' => $landlord->id,
            'was_existing' => false,
            'temporary_password' => $temporaryPassword
        ];
    }

    // ==================== RESPONSE METHODS ====================

    private function unauthorizedResponse(Request $request, $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        return redirect()->back()->with('error', $message);
    }

    private function validationErrorResponse(Request $request, $validator)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        return redirect()->back()->withErrors($validator)->withInput();
    }

    private function successResponse(Request $request, $result)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, 201);
        }

        $successMessage = 'Site allocation registered successfully!';

        return back()
            ->with('success', $successMessage)
            ->with('new_property_id', $result['site_allocation']['id'] ?? null)
            ->withInput();
    }

    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 500);
        }
        return redirect()->back()->with('error', $message)->withInput();
    }

    public function markActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'completion_notes' => 'nullable|string|max:500',
            'declaration' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $property = Property::findOrFail($request->property_id);

        if ($property->landlord_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this property.'
            ], 403);
        }

        if ($property->status !== 'under_construction') {
            return response()->json([
                'success' => false,
                'message' => 'This property is not under construction.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $property->update([
                'status' => 'active',
                'construction_status' => 'completed',
                'completed_at' => now(),
                'completion_notes' => $request->completion_notes,
            ]);

            if (method_exists($property, 'logActivity')) {
                $property->logActivity('marked_active',
                    'Property marked as active/completed by: ' . auth()->user()->name .
                    ($request->completion_notes ? ' | Notes: ' . $request->completion_notes : '')
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property marked as Active successfully!',
                'property' => $property->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark property as active: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark property as active. Please try again.'
            ], 500);
        }
    }
    public function geocodeDigitalAddress(Request $request)
{
    $validator = Validator::make($request->all(), [
        'digital_address' => 'required|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'message' => 'Invalid digital address.'], 422);
    }

    try {
        $coords = app(\App\Services\GoogleMapsService::class)
            ->geocodeDigitalAddress((string) $request->digital_address);

        if ($coords && isset($coords['lat'], $coords['lng'])) {
            return response()->json([
                'success'   => true,
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
                'city'      => $coords['city'] ?? null,
                'source'    => $coords['source'] ?? 'geocoder',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Could not resolve the digital address. Please drag the pin manually.',
        ], 422);

    } catch (\Throwable $e) {
        Log::warning('Geocode request failed', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => 'Geocoding failed.'], 500);
    }
}

}