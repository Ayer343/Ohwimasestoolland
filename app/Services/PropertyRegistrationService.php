<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Services\LandlordInvitationService;
use App\Services\TenantInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PropertyRegistrationService
{
    protected $landlordInvitationService;
    protected $tenantInvitationService;

    public function __construct(
        LandlordInvitationService $landlordInvitationService,
        TenantInvitationService $tenantInvitationService
    ) {
        $this->landlordInvitationService = $landlordInvitationService;
        $this->tenantInvitationService = $tenantInvitationService;
    }

    /**
     * Create a new property with all related data
     */
    public function createProperty(array $data, User $creator)
    {
        DB::beginTransaction();

        try {
            // Get the registration plan with lock
            $registrationPlan = RegistrationPlan::where('id', $data['registration_plan_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Check if plan is active
            if (in_array($registrationPlan->status, ['completed', 'cancelled'])) {
                throw new \Exception('Cannot register property for a completed or cancelled registration plan.');
            }

            // Handle landlord assignment/creation
            $landlordId = $this->handleLandlordAssignment($data);

            // ✅ Ensure landlord has proper role (for multi-role users)
            if ($landlordId) {
                $landlord = User::find($landlordId);
                if (!$landlord) {
                    throw new \Exception('Landlord not found.');
                }

                // ✅ CRITICAL: Ensure landlord role is assigned (without duplication)
                $this->ensureLandlordRole($landlord, $creator);

                // Verify landlord has landlord privileges
                if (!$landlord->isLandlord()) {
                    throw new \Exception('The specified user does not have landlord privileges.');
                }
            }

            // ✅ Check for duplicate property BEFORE creation
            if ($landlordId) {
                $propertyData = [
                    'property_name' => $data['property_name'],
                    'street_name' => $data['street_name'] ?? null,
                    'house_number' => $data['house_number'] ?? null,
                    'digital_address' => $data['digital_address'] ?? null,
                ];

                if ($this->isDuplicateProperty($landlordId, $propertyData)) {
                    $similarProperties = $this->getSimilarProperties($landlordId, $propertyData);

                    $exception = new \Exception('Duplicate property detected. This landlord already owns a similar property.');
                    $exception->similar_properties = $similarProperties;
                    throw $exception;
                }
            }

            // Generate registration pattern
            $registrationPattern = $this->generateRegistrationPattern($registrationPlan);

            if (!$registrationPattern) {
                throw new \Exception('Failed to generate registration pattern. Please check the registration plan configuration.');
            }

            // Check pattern uniqueness
            $existingProperty = $this->checkPatternUniqueness($registrationPlan, $registrationPattern);

            if ($existingProperty) {
                throw new \Exception(
                    "Registration pattern '{$registrationPattern}' already exists in this global sequence. " .
                    "This pattern might have been used in a previous plan. " .
                    "Please refresh the page and try again."
                );
            }

            // Use manual zone/section if provided
            $zone = $data['manual_zone'] ?? $data['zone'] ?? null;
            $section = $data['manual_section'] ?? $data['section'] ?? null;
            $propertyName = $data['property_name'];

            // Prepare property data
            $propertyData = $this->preparePropertyData($data, $landlordId, $registrationPattern, $zone, $section, $creator);

            // ✅ NEW: Log if we're about to persist coordinates
            Log::info('Property data prepared for creation', [
                'has_coordinates' => isset($propertyData['latitude'], $propertyData['longitude'])
                    && $propertyData['latitude'] !== null
                    && $propertyData['longitude'] !== null,
                'latitude'        => $propertyData['latitude']  ?? null,
                'longitude'       => $propertyData['longitude'] ?? null,
                'digital_address' => $propertyData['digital_address'] ?? null,
                'creator_id'      => $creator->id,
            ]);

            // Create the property
            $property = Property::create($propertyData);

            // Update registration plan progress
            $this->updateRegistrationPlanProgress($registrationPlan, $registrationPattern);

            // Update agent assignment progress for field agents
            if ($creator->isFieldAgent()) {
                $this->updateAgentAssignmentProgress($creator->id, $registrationPlan->id);
            }

            // Handle landlord invitation if requested
            $landlordInvitationResult = null;
            if (isset($data['send_invitation']) && $data['send_invitation'] && $landlordId) {
                $landlord = User::find($landlordId);
                if ($landlord) {
                    $landlordInvitationResult = $this->landlordInvitationService->sendInvitation(
                        $landlord,
                        $property,
                        $data
                    );
                }
            }

            // Handle tenant creation and invitation
            $tenantInvitationResults = [];
            if (isset($data['is_rented']) && $data['is_rented'] && !empty($data['tenants'])) {
                $tenantInvitationResults = $this->tenantInvitationService->createAndInviteTenants(
                    $data['tenants'],
                    $property,
                    $creator
                );
            }

            DB::commit();

            return $this->prepareSuccessResponse($property, $registrationPattern, $registrationPlan, $landlordInvitationResult, $tenantInvitationResults);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ✅ NEW: Ensure landlord role is assigned to user (for multi-role users)
     * This prevents duplicate user creation and properly assigns landlord role
     */
    private function ensureLandlordRole(User $user, User $assigner): void
    {
        // Check if user already has landlord role (by role system)
        if ($user->hasRole('landlord')) {
            Log::info('User already has landlord role', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'existing_roles' => $user->getRoleSlugsAttribute()
            ]);
            return;
        }

        // Check legacy type (for backward compatibility)
        if ($user->type == User::TYPE_LANDLORD) {
            Log::info('User has legacy landlord type but not role - adding role', [
                'user_id' => $user->id,
                'user_name' => $user->name
            ]);
        }

        // Assign landlord role
        $user->assignRole('landlord', [
            'assigned_by' => $assigner->id,
            'assigned_at' => now(),
            'assignment_reason' => 'Auto-assigned: User owns property in community',
            'notes' => 'Landlord role automatically assigned due to property ownership',
            'metadata' => [
                'assigned_via' => 'property_registration_service',
                'property_creation_context' => 'auto_assignment',
                'assigner_name' => $assigner->name,
                'assigner_type' => $assigner->type
            ]
        ]);

        Log::info('Landlord role assigned to multi-role user', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_type' => $user->type,
            'user_roles_before' => $user->roles->pluck('slug')->toArray(),
            'assigned_by' => $assigner->id,
            'assigned_by_name' => $assigner->name,
            'assigned_by_type' => $assigner->type
        ]);
    }

    /**
     * ✅ NEW: Check if property is a duplicate for a landlord
     */
    private function isDuplicateProperty($landlordId, array $propertyData): bool
    {
        if (!$landlordId) {
            return false;
        }

        $query = Property::where('landlord_id', $landlordId);

        // Check by exact digital address (most reliable)
        if (!empty($propertyData['digital_address'])) {
            $existingByDigitalAddress = Property::where('digital_address', $propertyData['digital_address'])
                ->where('landlord_id', $landlordId)
                ->exists();

            if ($existingByDigitalAddress) {
                Log::info('Duplicate property detected by digital address', [
                    'landlord_id' => $landlordId,
                    'digital_address' => $propertyData['digital_address']
                ]);
                return true;
            }
        }

        // Check by exact property name (case-insensitive)
        if (!empty($propertyData['property_name'])) {
            $existingByName = Property::whereRaw('LOWER(property_name) = ?', [strtolower($propertyData['property_name'])])
                ->where('landlord_id', $landlordId)
                ->exists();

            if ($existingByName) {
                Log::info('Duplicate property detected by exact name', [
                    'landlord_id' => $landlordId,
                    'property_name' => $propertyData['property_name']
                ]);
                return true;
            }
        }

        // Check by location combination (street + house number)
        if (!empty($propertyData['street_name']) && !empty($propertyData['house_number'])) {
            $existingByLocation = Property::where('street_name', $propertyData['street_name'])
                ->where('house_number', $propertyData['house_number'])
                ->where('landlord_id', $landlordId)
                ->exists();

            if ($existingByLocation) {
                Log::info('Duplicate property detected by location', [
                    'landlord_id' => $landlordId,
                    'street_name' => $propertyData['street_name'],
                    'house_number' => $propertyData['house_number']
                ]);
                return true;
            }
        }

        // Fuzzy check for similar property names
        if (!empty($propertyData['property_name'])) {
            $existingProperties = Property::where('landlord_id', $landlordId)
                ->select('id', 'property_name')
                ->limit(20)
                ->get();

            foreach ($existingProperties as $existing) {
                similar_text(
                    strtolower($existing->property_name),
                    strtolower($propertyData['property_name']),
                    $similarity
                );

                if ($similarity > 85) {
                    Log::info('Duplicate property detected by fuzzy name match', [
                        'landlord_id' => $landlordId,
                        'property_name' => $propertyData['property_name'],
                        'similar_to' => $existing->property_name,
                        'similarity_percent' => $similarity
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ✅ NEW: Get similar existing properties for suggestion
     */
    private function getSimilarProperties($landlordId, array $propertyData): array
    {
        if (!$landlordId) {
            return [];
        }

        $similar = [];
        $propertyName = $propertyData['property_name'] ?? '';

        if (empty($propertyName)) {
            return [];
        }

        $properties = Property::where('landlord_id', $landlordId)
            ->where('property_name', 'LIKE', '%' . $propertyName . '%')
            ->limit(10)
            ->get();

        foreach ($properties as $property) {
            similar_text(
                strtolower($property->property_name),
                strtolower($propertyName),
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
                    'created_at' => $property->created_at?->toISOString(),
                ];
            }
        }

        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similar, 0, 5);
    }

    /**
     * Handle landlord assignment/creation
     */
    private function handleLandlordAssignment(array $data)
    {
        // If existing landlord is selected, use that
        if (!empty($data['landlord_id'])) {
            return $data['landlord_id'];
        }

        // If new landlord info is provided, create a new landlord
        if (!empty($data['landlord_phones']) && !empty($data['landlord_name'])) {
            $cleanedPhones = $this->validateAndCleanPhones($data['landlord_phones']);

            if (empty($cleanedPhones)) {
                throw new \Exception('Please provide at least one valid phone number.');
            }

            $primaryPhone = $cleanedPhones[0] ?? null;
            return $this->findOrCreateLandlordByPhone($primaryPhone, $data['landlord_name'], $data['landlord_email'] ?? null, $cleanedPhones);
        }

        throw new \Exception('Either select an existing landlord or provide phone number and name for a new landlord.');
    }

    /**
     * Find or create landlord by phone number
     */
    private function findOrCreateLandlordByPhone($primaryPhone, $name, $email, $allPhones = [])
    {
        $cleanPrimaryPhone = preg_replace('/[^0-9]/', '', $primaryPhone);

        // Try to find existing landlord (by phone)
        $landlord = User::findByAnyPhoneFormat($cleanPrimaryPhone);

        if ($landlord) {
            // ✅ FIXED: If user exists but doesn't have landlord role, add it
            if (!$landlord->isLandlord()) {
                Log::info('Existing user found without landlord role - will assign role', [
                    'user_id' => $landlord->id,
                    'user_name' => $landlord->name,
                    'current_type' => $landlord->type,
                    'current_roles' => $landlord->roles->pluck('slug')->toArray()
                ]);

                // Assign landlord role to existing user
                $landlord->assignRole('landlord', [
                    'assigned_by' => auth()->id(),
                    'assignment_reason' => 'Auto-assigned during property registration',
                    'assigned_at' => now(),
                    'notes' => 'User was found by phone number and granted landlord role'
                ]);

                Log::info('Landlord role assigned to existing user', [
                    'user_id' => $landlord->id,
                    'user_name' => $landlord->name,
                    'new_roles' => $landlord->roles->pluck('slug')->toArray()
                ]);
            }

            $this->updateLandlordPhones($landlord, $allPhones);
            return $landlord->id;
        }

        // Create new landlord
        if ($name && $primaryPhone) {
            $landlordData = [
                'name' => $name,
                'phone' => $cleanPrimaryPhone,
                'email' => $email ?: ($cleanPrimaryPhone . '@propertyportal.com'),
                'type' => User::TYPE_LANDLORD,
                'password' => bcrypt(Str::random(12)),
                'email_verified_at' => null,
                'status' => User::STATUS_ACTIVE,
            ];

            $landlord = User::create($landlordData);

            // ✅ Assign landlord role to new user
            $landlord->assignRole('landlord', [
                'assigned_by' => auth()->id(),
                'assignment_reason' => 'Created during property registration',
                'assigned_at' => now(),
            ]);

            $this->updateLandlordPhones($landlord, $allPhones);

            Log::info('New landlord created and role assigned', [
                'user_id' => $landlord->id,
                'user_name' => $landlord->name
            ]);

            return $landlord->id;
        }

        throw new \Exception('Failed to find or create landlord. Please provide valid phone number and name.');
    }

    /**
     * Update landlord's additional phones
     */
    private function updateLandlordPhones(User $landlord, $phones)
    {
        try {
            if (!$landlord->isLandlord() || !method_exists($landlord, 'phones')) {
                return;
            }

            $landlord->updatePhones($phones);
        } catch (\Exception $e) {
            Log::error('Error updating landlord phones: ' . $e->getMessage());
        }
    }

    /**
     * Validate and clean phone numbers array
     */
    private function validateAndCleanPhones(array $phones): array
    {
        $cleanedPhones = [];

        foreach ($phones as $phone) {
            if (!empty(trim($phone))) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($cleanPhone) >= 10) {
                    $cleanedPhones[] = $cleanPhone;
                }
            }
        }

        return array_unique($cleanedPhones);
    }

    /**
     * Generate registration pattern
     */
    private function generateRegistrationPattern(RegistrationPlan $plan)
    {
        // For global sequences that continue from another plan
        if ($plan->is_global_sequence && $plan->continues_from_plan_id) {
            return $this->getNextGlobalSequencePattern($plan);
        }

        $nextAvailableName = $plan->next_available_name ?? $plan->starting_point;

        if (!$nextAvailableName) {
            return null;
        }

        return $this->generatePatternFromName($plan->naming_pattern, $nextAvailableName);
    }

    /**
     * Get next pattern for global sequence plan
     */
    private function getNextGlobalSequencePattern(RegistrationPlan $plan)
    {
        $continuedFromPlan = RegistrationPlan::find($plan->continues_from_plan_id);

        if (!$continuedFromPlan) {
            $nextAvailableName = $plan->next_available_name ?? $plan->starting_point;
            return $this->generatePatternFromName($plan->naming_pattern, $nextAvailableName);
        }

        // Get the maximum pattern from both plans
        $maxPattern = $this->getMaxPatternInSequence($continuedFromPlan, $plan);

        if ($maxPattern) {
            $lastName = $this->extractNameFromPattern($maxPattern, $plan->naming_pattern);
            $nextName = $this->generateNextName($lastName, $plan->naming_pattern, $plan->sequence_type);
            $nextPattern = $this->generatePatternFromName($plan->naming_pattern, $nextName);

            // Check if pattern exists (max 5 attempts)
            $attempt = 0;
            $maxAttempts = 5;

            while ($this->patternExistsInSequence($nextPattern, $continuedFromPlan, $plan) && $attempt < $maxAttempts) {
                $nextName = $this->generateNextName($nextName, $plan->naming_pattern, $plan->sequence_type);
                $nextPattern = $this->generatePatternFromName($plan->naming_pattern, $nextName);
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    Log::warning('Exceeded max attempts for unique pattern in global sequence');
                    break;
                }
            }

            return $nextPattern;
        } else {
            $startingName = $plan->starting_point ?? 'A1';
            return $this->generatePatternFromName($plan->naming_pattern, $startingName);
        }
    }

    /**
     * Get maximum pattern in global sequence
     */
    private function getMaxPatternInSequence(RegistrationPlan $fromPlan, RegistrationPlan $toPlan): ?string
    {
        $patterns = Property::whereIn('registration_plan_id', [$fromPlan->id, $toPlan->id])
            ->select('registration_pattern')
            ->get()
            ->pluck('registration_pattern')
            ->toArray();

        if (empty($patterns)) {
            return null;
        }

        // For simple patterns like A1, B2, etc.
        if ($toPlan->naming_pattern && strpos($toPlan->naming_pattern, '{number}') !== false) {
            $maxPattern = null;
            $maxNumber = 0;

            foreach ($patterns as $pattern) {
                if (preg_match('/(\d+)$/', $pattern, $matches)) {
                    $number = (int)$matches[1];
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                        $maxPattern = $pattern;
                    }
                }
            }

            return $maxPattern;
        }

        // Fallback: sort alphabetically
        sort($patterns);
        return end($patterns);
    }

    /**
     * Check if pattern exists in sequence
     */
    private function patternExistsInSequence(string $pattern, RegistrationPlan $fromPlan, RegistrationPlan $toPlan): bool
    {
        return Property::whereIn('registration_plan_id', [$fromPlan->id, $toPlan->id])
            ->where('registration_pattern', $pattern)
            ->exists();
    }

    /**
     * Check pattern uniqueness
     */
    private function checkPatternUniqueness(RegistrationPlan $plan, string $pattern): ?Property
    {
        $planIds = [$plan->id];

        if ($plan->is_global_sequence && $plan->continues_from_plan_id) {
            $planIds[] = $plan->continues_from_plan_id;
        }

        return Property::whereIn('registration_plan_id', $planIds)
            ->where('registration_pattern', $pattern)
            ->first();
    }

    /**
     * Prepare property data
     *
     * ✅ UPDATED: Now persists `latitude`, `longitude`, and `city` so the
     * downstream sanitation flow (linkProperty → waste collection request →
     * driver route planner) has coordinates to work with.
     *
     * Coordinate resolution order:
     *   1. Values already in `$data` (map picker / hidden inputs).
     *   2. `null` — the controller layer is responsible for geocoding fallback.
     */
    private function preparePropertyData(array $data, $landlordId, $registrationPattern, $zone, $section, User $creator)
    {
        $propertyData = [
            'registration_plan_id' => $data['registration_plan_id'],
            'property_type_id' => $data['property_type_id'],
            'landlord_id' => $landlordId,
            'property_name' => $data['property_name'],
            'registration_pattern' => $registrationPattern,
            'house_number' => $data['house_number'] ?? null,
            'street_name' => $data['street_name'],
            'block_number' => $data['block_number'] ?? null,
            'digital_address' => $data['digital_address'] ?? null,

            // ✅ NEW: coordinates + city
            'latitude'  => $this->normalizeCoordinate($data['latitude']  ?? null, 'latitude'),
            'longitude' => $this->normalizeCoordinate($data['longitude'] ?? null, 'longitude'),
            'city'      => $data['city'] ?? null,

            'zone' => $zone,
            'section' => $section,
            'registration_date' => $data['registration_date'],
            'status' => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null,
            'last_inspection_date' => $data['last_inspection_date'] ?? null,
            'created_by' => $creator->id,
        ];

        // Handle custom property type
        $customType = PropertyType::where('slug', 'custom')->first();
        if ($data['property_type_id'] == $customType->id && isset($data['custom_property_type'])) {
            $propertyData['custom_property_type'] = $data['custom_property_type'];
        }

        // Set registered_by for field agents
        if ($creator->isFieldAgent()) {
            $propertyData['registered_by'] = $creator->id;
        }

        return $propertyData;
    }

    /**
     * ✅ NEW: Normalize a coordinate value.
     *
     * - Empty string / null → null
     * - Numeric string / int / float → float
     * - Anything else → null (with a warning log)
     */
    private function normalizeCoordinate($value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            Log::warning("Non-numeric coordinate dropped: {$label}", [
                'value' => $value,
            ]);
            return null;
        }

        return (float) $value;
    }

    /**
     * Update registration plan progress
     */
    private function updateRegistrationPlanProgress(RegistrationPlan $plan, $usedPattern = null)
    {
        DB::transaction(function () use ($plan, $usedPattern) {
            $freshPlan = RegistrationPlan::where('id', $plan->id)
                ->lockForUpdate()
                ->first();

            if (!$freshPlan) return;

            // Update registered count
            $registeredSites = Property::where('registration_plan_id', $freshPlan->id)->count();

            // Update next available name
            $this->updateNextAvailableName($freshPlan);

            // Update registered count
            $freshPlan->houses_registered = $registeredSites;
            $freshPlan->save();

            // For global sequences, also update the parent plan's progress
            if ($freshPlan->is_global_sequence && $freshPlan->continues_from_plan_id) {
                $this->updateParentPlanProgress($freshPlan);
            }

            // Auto-complete plan if all estimated houses are registered
            if ($registeredSites >= $freshPlan->estimated_houses && $freshPlan->status !== 'completed') {
                $freshPlan->status = 'completed';
                $freshPlan->save();
            }

            $freshPlan->refresh();
        }, 5);
    }

    /**
     * Update parent plan progress for global sequences
     */
    private function updateParentPlanProgress(RegistrationPlan $currentPlan)
    {
        try {
            $parentPlan = RegistrationPlan::find($currentPlan->continues_from_plan_id);

            if (!$parentPlan) return;

            $childPlanIds = RegistrationPlan::where('continues_from_plan_id', $parentPlan->id)
                ->pluck('id')
                ->toArray();

            $allPlanIds = array_merge([$parentPlan->id], $childPlanIds);

            $totalProperties = Property::whereIn('registration_plan_id', $allPlanIds)->count();

            $parentPlan->houses_registered = $totalProperties;
            $parentPlan->save();

        } catch (\Exception $e) {
            Log::error('Error updating parent plan progress: ' . $e->getMessage());
        }
    }

    /**
     * Update next available name
     */
    private function updateNextAvailableName(RegistrationPlan $plan)
    {
        try {
            $currentName = $plan->next_available_name ?? $plan->starting_point;
            $pattern = $plan->naming_pattern;
            $sequenceType = $plan->sequence_type;

            // Generate next name
            $nextName = $this->generateNextName($currentName, $pattern, $sequenceType);

            // For global sequences, do a quick uniqueness check
            if ($plan->is_global_sequence) {
                $nextName = $this->quickUniqueCheck($plan, $nextName, $pattern, $sequenceType);
            }

            DB::table('registration_plans')
                ->where('id', $plan->id)
                ->update(['next_available_name' => $nextName]);

            $plan->refresh();

        } catch (\Exception $e) {
            Log::error('Error in updateNextAvailableName: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Quick uniqueness check for global sequences
     */
    private function quickUniqueCheck(RegistrationPlan $plan, $proposedName, $pattern, $sequenceType)
    {
        $maxChecks = 3;
        $currentName = $proposedName;

        for ($i = 0; $i < $maxChecks; $i++) {
            $exists = Property::where('registration_plan_id', $plan->id)
                ->where('registration_pattern', $currentName)
                ->exists();

            if (!$exists) {
                return $currentName;
            }

            $currentName = $this->generateNextName($currentName, $pattern, $sequenceType);
        }

        Log::warning("Quick unique check failed for plan {$plan->id}, using: {$proposedName}");
        return $proposedName;
    }

    /**
     * Pattern generation helper methods
     */
    private function generatePatternFromName($pattern, $name)
    {
        $generated = $pattern;

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            // Try number+letter format first (e.g., 1A, 2B)
            if (preg_match('/(\d+)([A-Za-z]+)/', $name, $matches)) {
                $number = $matches[1];
                $letter = $matches[2];
                $generated = str_replace(['{number}', '{letter}'], [$number, $letter], $pattern);
            }
            // Try letter+number format (e.g., A1, B2)
            elseif (preg_match('/([A-Za-z]+)(\d+)/', $name, $matches)) {
                $letter = $matches[1];
                $number = $matches[2];
                $generated = str_replace(['{letter}', '{number}'], [$letter, $number], $pattern);
            } else {
                return null;
            }
        } elseif (strpos($pattern, '{letter}') !== false) {
            if (preg_match('/^[a-zA-Z]+$/', $name)) {
                $generated = str_replace('{letter}', $name, $pattern);
            } else {
                return null;
            }
        } elseif (strpos($pattern, '{number}') !== false) {
            if (preg_match('/^\d+$/', $name)) {
                $generated = str_replace('{number}', $name, $pattern);
            } else {
                return null;
            }
        }

        return $generated;
    }

    private function extractNameFromPattern(string $pattern, string $namingPattern): string
    {
        return $pattern;
    }

    /**
     * Generate the next name in sequence
     */
    private function generateNextName($currentName, $pattern, $sequenceType)
    {
        if (empty($currentName)) {
            return $this->getStartingName($pattern, $sequenceType);
        }

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $this->generateCombinedNextName($currentName, $sequenceType);
        } elseif (strpos($pattern, '{letter}') !== false) {
            return $this->generateLetterNextName($currentName);
        } elseif (strpos($pattern, '{number}') !== false) {
            return $this->generateNumberNextName($currentName, $sequenceType);
        }

        return $this->generateSimpleNextName($currentName);
    }

    /**
     * Get starting name based on pattern and sequence type
     */
    private function getStartingName($pattern, $sequenceType)
    {
        // Detect pattern order
        $isNumberThenLetter = strpos($pattern, '{number}') !== false &&
                              strpos($pattern, '{letter}') !== false &&
                              strpos($pattern, '{number}') < strpos($pattern, '{letter}');

        if ($isNumberThenLetter) {
            // Number+Letter pattern (e.g., 1A, 2B)
            return $sequenceType === 'even_only' ? '2A' : ($sequenceType === 'odd_only' ? '1A' : '1A');
        } elseif (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            // Letter+Number pattern (e.g., A1, B2)
            return $sequenceType === 'even_only' ? 'A2' : ($sequenceType === 'odd_only' ? 'A1' : 'A1');
        } elseif (strpos($pattern, '{letter}') !== false) {
            return 'A';
        } elseif (strpos($pattern, '{number}') !== false) {
            return $sequenceType === 'even_only' ? '2' : '1';
        }

        return '001';
    }

    /**
     * Generate next name for combined letter-number patterns
     * Supports both {letter}{number} (A1) and {number}{letter} (2A) formats
     */
    private function generateCombinedNextName($currentName, $sequenceType)
    {
        // Check if pattern is number+letter format (e.g., 1A, 2B)
        if (preg_match('/(\d+)([A-Za-z]+)/', $currentName, $matches)) {
            $number = (int)$matches[1];
            $letter = $matches[2];

            // Increment number first
            $number += 1;

            // Handle sequence types for numbers
            switch ($sequenceType) {
                case 'even_only':
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case 'odd_only':
                    if ($number % 2 === 0) $number += 1;
                    break;
            }

            // If number exceeds 99, increment letter and reset number
            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
            }

            return $number . $letter;
        }
        // Check if pattern is letter+number format (e.g., A1, B2)
        elseif (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
            $letter = $matches[1];
            $number = (int)$matches[2];

            // Increment number first
            $number += 1;

            // Handle sequence types for numbers
            switch ($sequenceType) {
                case 'even_only':
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case 'odd_only':
                    if ($number % 2 === 0) $number += 1;
                    break;
            }

            // If number exceeds 99, increment letter and reset number
            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
            }

            return $letter . $number;
        }

        return $this->generateSimpleNextName($currentName);
    }

    /**
     * Increment letters (A->B, Z->AA, etc.)
     */
    private function incrementLetters($letters)
    {
        $length = strlen($letters);
        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return $letters;
            }
            $letters[$i] = 'A';
        }
        return 'A' . $letters;
    }

    /**
     * Generate next name for letter-only patterns
     */
    private function generateLetterNextName($currentName)
    {
        return $this->incrementLetters($currentName);
    }

    /**
     * Generate next name for number-only patterns
     */
    private function generateNumberNextName($currentName, $sequenceType)
    {
        $number = (int)$currentName;

        switch ($sequenceType) {
            case 'even_only':
                return $number % 2 === 0 ? $number + 2 : $number + 1;
            case 'odd_only':
                return $number % 2 === 1 ? $number + 2 : $number + 1;
            default:
                return $number + 1;
        }
    }

    /**
     * Generate next name for simple string patterns
     */
    private function generateSimpleNextName($currentName)
    {
        if (preg_match('/(.*?)(\d+)$/', $currentName, $matches)) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            return $prefix . ($number + 1);
        }

        return $currentName . '-1';
    }

    /**
     * Update agent assignment progress
     */
    private function updateAgentAssignmentProgress($agentId, $planId)
    {
        try {
            $assignment = PlanAgentAssignment::where('agent_id', $agentId)
                ->where('plan_id', $planId)
                ->where('is_active', true)
                ->first();

            if ($assignment) {
                $propertiesRegistered = Property::where('registration_plan_id', $planId)
                    ->where('registered_by', $agentId)
                    ->count();

                $assignment->update([
                    'properties_registered' => $propertiesRegistered,
                    'last_activity_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating agent assignment progress: ' . $e->getMessage());
        }
    }

    /**
     * Prepare success response
     *
     * ✅ UPDATED: Now exposes `has_coordinates` on the created site so the
     * caller (PropertyController / landlord UI) can prompt for a map pin if
     * the geocoder could not resolve the digital address.
     */
    private function prepareSuccessResponse($property, $registrationPattern, $registrationPlan, $landlordInvitationResult, $tenantInvitationResults)
    {
        // ✅ Refresh to pick up the coordinate columns
        $property = $property->fresh(['landlord', 'registrationPlan', 'propertyType', 'tenants']);

        $response = [
            'success' => true,
            'message' => 'Site allocation registered successfully',
            'site_allocation' => $property,
            'registration_pattern' => $registrationPattern,
            'next_available_pattern' => $registrationPlan->fresh()->next_available_name,
            'is_global_sequence' => $registrationPlan->is_global_sequence,
            'continues_from_plan_id' => $registrationPlan->continues_from_plan_id,

            // ✅ NEW: coordinate context for the caller
            'coordinate_context' => [
                'has_coordinates' => $property->has_coordinates,
                'latitude'        => $property->latitude,
                'longitude'       => $property->longitude,
                'city'            => $property->city,
                'digital_address' => $property->digital_address,
            ],
        ];

        if ($landlordInvitationResult) {
            $response['landlord_invitation_result'] = $landlordInvitationResult;

            if ($landlordInvitationResult['success']) {
                $response['message'] .= ' Landlord invitation sent successfully.';
            } else {
                $response['message'] .= ' But failed to send landlord invitation.';
            }
        }

        if (!empty($tenantInvitationResults)) {
            $response['tenant_invitation_results'] = $tenantInvitationResults;

            $successfulTenantInvitations = array_filter($tenantInvitationResults, function($result) {
                return $result['success'];
            });

            if (count($successfulTenantInvitations) > 0) {
                $response['message'] .= ' ' . count($successfulTenantInvitations) . ' tenant(s) invited successfully.';
            }
        }

        return $response;
    }
}