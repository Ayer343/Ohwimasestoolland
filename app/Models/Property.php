<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    // Site allocation status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    const STATUS_VACANT = 'vacant';
    const STATUS_UNDER_CONSTRUCTION = 'under_construction';

    protected $fillable = [
        'landlord_id',
        'registration_plan_id',
        'property_name',
        'registration_pattern',
        'house_number',
        'street_name',
        'block_number',
        'digital_address',
        'zone',
        'section',
        // ✅ NEW: coordinate + city columns
        'latitude',
        'longitude',
        'city',
        'description',
        'status',
        'registration_date',
        'last_inspection_date',
        'created_by',
        'registered_by',
        'property_type_id',
        'custom_property_type',
        'is_rented',
        'sequence_position',
        'is_global_sequence',
        'is_field_agent_registered',
        'field_agent_registered_at',
        'tenant_count',
        'active_tenant_count',
        'construction_status',
        'estimated_completion',
        'bedrooms',
        'bathrooms',
        // ⭐ NEW: Construction columns
        'has_plans',
        'construction_documents',
        'construction_notes',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'last_inspection_date' => 'datetime',
        'field_agent_registered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_rented' => 'boolean',
        'is_field_agent_registered' => 'boolean',
        'is_global_sequence' => 'boolean',
        'sequence_position' => 'integer',
        'tenant_count' => 'integer',
        'active_tenant_count' => 'integer',
        'estimated_completion' => 'date',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        // ⭐ FIX: Add JSON casting for construction_documents
        'has_plans' => 'boolean',
        'construction_documents' => 'array',  // ✅ Converts JSON string to array
        'construction_notes' => 'string',
        // ✅ NEW: Coordinate casts — float keeps Haversine clean and null-safe
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = [
        'full_address',
        'physical_address',
        'has_digital_address',
        'site_allocation_identifier',
        'ownership_info',
        'site_status_badge',
        'is_global_sequence',
        'global_sequence_info',
        'display_property_type',
        'property_type_icon',
        'property_type_color',
        'is_custom_type',
        'property_type_badge',
        'is_field_agent_registered_badge',
        'field_agent_info',
        'rental_status_badge',
        'sequence_info',
        'is_rented_with_tenants',
        'tenants_count',
        'active_tenants_count',
        'current_tenant_info',
        'tenant_stats_summary',
        'construction_progress',
        'is_under_construction',
        'construction_status_badge',
        'formatted_construction_status',
        'primary_photo_url',
        'primary_photo_thumbnail_url',
        'photos_count',
        'has_photos',
        'photo_gallery',
        // ✅ NEW
        'has_coordinates',
    ];


    // ============================================================
    // FIX: Set default values for construction_status
    // ============================================================
    public function __construct(array $attributes = [])
    {
        // Set default value for construction_status if not provided
        if (!isset($attributes['construction_status'])) {
            $attributes['construction_status'] = null;
        }

        // Set default value for has_plans if not provided
        if (!isset($attributes['has_plans'])) {
            $attributes['has_plans'] = false;
        }

        // Set default value for construction_documents if not provided
        if (!isset($attributes['construction_documents'])) {
            $attributes['construction_documents'] = [];
        }

        parent::__construct($attributes);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->casts;
    }

    // ============================================================
    // ✅ NEW: Coordinate mutators
    // ============================================================

    /**
     * Normalise latitude on set. Empty strings / non-numerics become null.
     */
    public function setLatitudeAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['latitude'] = null;
            return;
        }

        $this->attributes['latitude'] = is_numeric($value) ? (float) $value : null;
    }

    /**
     * Normalise longitude on set. Empty strings / non-numerics become null.
     */
    public function setLongitudeAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['longitude'] = null;
            return;
        }

        $this->attributes['longitude'] = is_numeric($value) ? (float) $value : null;
    }

    /**
     * ✅ NEW: Simple boolean — true only when both coordinates exist.
     */
    public function getHasCoordinatesAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

     // ============================================================
    // ⭐ FIX: Accessor to ensure construction_documents is always an array
    // ============================================================
    public function getConstructionDocumentsAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Set the construction_documents attribute
     */
    public function setConstructionDocumentsAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['construction_documents'] = json_encode([]);
        } elseif (is_array($value)) {
            $this->attributes['construction_documents'] = json_encode($value);
        } elseif (is_string($value)) {
            // If it's already a JSON string, keep it
            if (json_decode($value) !== null) {
                $this->attributes['construction_documents'] = $value;
            } else {
                $this->attributes['construction_documents'] = json_encode([$value]);
            }
        } else {
            $this->attributes['construction_documents'] = json_encode([]);
        }
    }

    // ============================================================
    // ⭐ NEW: Helper method to add construction document
    // ============================================================
    public function addConstructionDocument($filePath)
    {
        $documents = $this->construction_documents ?? [];
        if (!in_array($filePath, $documents)) {
            $documents[] = $filePath;
            $this->construction_documents = $documents;
            $this->save();
        }
        return $this;
    }

    /**
     * ⭐ NEW: Helper method to remove construction document
     */
    public function removeConstructionDocument($filePath)
    {
        $documents = $this->construction_documents ?? [];
        $documents = array_filter($documents, function($doc) use ($filePath) {
            return $doc !== $filePath;
        });
        $this->construction_documents = array_values($documents);
        $this->save();
        return $this;
    }

    /**
     * ⭐ NEW: Helper method to check if property has construction documents
     */
    public function hasConstructionDocuments(): bool
    {
        $documents = $this->construction_documents ?? [];
        return count($documents) > 0;
    }

    /**
     * ⭐ NEW: Helper method to get construction document count
     */
    public function getConstructionDocumentCount(): int
    {
        $documents = $this->construction_documents ?? [];
        return count($documents);
    }

   // Validation rules
    protected static $rules = [
        'property_name' => 'required|string|max:255',
        'registration_plan_id' => 'required|exists:registration_plans,id',
        'property_type_id' => 'nullable|exists:property_types,id', // ⭐ FIX: Made nullable for vacant land
        'custom_property_type' => 'nullable|required_if:property_type_id,custom_type|string|max:100',
        'landlord_id' => 'nullable|exists:users,id',
        'house_number' => 'nullable|string|max:50',
        'street_name' => 'required|string|max:255',
        'block_number' => 'nullable|string|max:50',
        'digital_address' => 'nullable|string|max:255',
        'zone' => 'nullable|string|max:100',
        'section' => 'nullable|string|max:100',
        // ✅ NEW: coordinate validation
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'city' => 'nullable|string|max:100',
        'description' => 'nullable|string',
        'status' => 'sometimes|in:active,inactive,under_maintenance,vacant,under_construction',
        'registration_date' => 'required|date',
        'last_inspection_date' => 'nullable|date',
        'is_rented' => 'boolean',
        'is_field_agent_registered' => 'boolean',
        'is_global_sequence' => 'boolean',
        'sequence_position' => 'nullable|integer|min:1',
        // ✅ NEW: Tenant validation rules
        'tenant_count' => 'nullable|integer|min:0',
        'active_tenant_count' => 'nullable|integer|min:0',
        // ✅ NEW: Construction validation rules
        'construction_status' => 'nullable|in:not_started,planned,under_construction,completed,on_hold,vacant',
        'estimated_completion' => 'nullable|date|after:today',
        'bedrooms' => 'nullable|integer|min:1|max:20',
        'bathrooms' => 'nullable|integer|min:1|max:20',
        // ✅ NEW: Construction columns validation
        'has_plans' => 'boolean',
        'construction_documents' => 'nullable|array',
        'construction_notes' => 'nullable|string|max:1000',
    ];

  /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($property) {
            // ============================================================
            // FIX: Set default values for vacant land
            // ============================================================
            // If construction_status is empty string or not set, set to null
            if (!isset($property->construction_status) || $property->construction_status === '') {
                $property->construction_status = null;
            }

            // If property_type_id is empty, 0, or not set, set to null (for vacant land)
            if (!isset($property->property_type_id) || $property->property_type_id === '' || $property->property_type_id === 0) {
                $property->property_type_id = null;
            }

            // If status is 'vacant' and construction_status is null, set construction_status to 'vacant'
            if ($property->status === self::STATUS_VACANT && $property->construction_status === null) {
                $property->construction_status = 'vacant';
            }

            // ⭐ FIX: Ensure has_plans is set
            if (!isset($property->has_plans) || $property->has_plans === null) {
                $property->has_plans = false;
            }

            // ⭐ FIX: Ensure construction_documents is set as array
            if (!isset($property->construction_documents) || $property->construction_documents === null) {
                $property->construction_documents = [];
            }

            // Generate property name if not provided and registration plan exists
            if (empty($property->property_name) && $property->registration_plan_id) {
                $property->property_name = $property->generateSiteAllocationName();
            }

            // Generate registration pattern if not provided and registration plan exists
            if (empty($property->registration_pattern) && $property->registration_plan_id) {
                $property->registration_pattern = $property->generateRegistrationPattern();
            }

            // Set created_by if not set
            if (empty($property->created_by) && auth()->check()) {
                $property->created_by = auth()->id();
            }

            // Set field agent registration flags
            if (auth()->check() && auth()->user()->isFieldAgent()) {
                $property->registered_by = auth()->id();
                $property->is_field_agent_registered = true;
                $property->field_agent_registered_at = now();
            }

            // Set zone and section from registration plan if not provided
            if (empty($property->zone) && $property->registration_plan_id) {
                $plan = $property->registrationPlan;
                if ($plan) {
                    $property->zone = $plan->zone;
                    if (empty($property->section)) {
                        $property->section = $plan->section;
                    }
                }
            }

            // Set is_global_sequence from registration plan
            if ($property->registration_plan_id) {
                $plan = $property->registrationPlan;
                if ($plan) {
                    $property->is_global_sequence = $plan->is_global_sequence;

                    // Set sequence position if it's a global sequence
                    if ($plan->is_global_sequence) {
                        $property->sequence_position = $property->getNextSequencePosition($plan->id);
                    }
                }
            }

            // ⭐ FIX: REMOVED - Don't set default property type for vacant land
            // The old code would set default property type to 'resident'
            // which caused the issue for vacant land. Now we only set it
            // if explicitly provided or if it's NOT vacant land.

            // Validate custom property type logic
            if ($property->property_type_id) {
                $propertyType = PropertyType::find($property->property_type_id);
                if ($propertyType && $propertyType->slug === 'custom' && empty($property->custom_property_type)) {
                    throw new \Exception('Custom property type name is required when selecting custom type.');
                }

                // Clear custom property type if not custom type
                if ($propertyType && $propertyType->slug !== 'custom') {
                    $property->custom_property_type = null;
                }
            }

            // Default values for new fields
            $property->is_rented = $property->is_rented ?? false;
            $property->is_field_agent_registered = $property->is_field_agent_registered ?? false;
            $property->is_global_sequence = $property->is_global_sequence ?? false;
            $property->tenant_count = $property->tenant_count ?? 0;
            $property->active_tenant_count = $property->active_tenant_count ?? 0;
            $property->has_plans = $property->has_plans ?? false;
            $property->construction_documents = $property->construction_documents ?? [];

            // Auto-set status based on rental status
            if ($property->is_rented) {
                $property->status = self::STATUS_ACTIVE;
            }
        });

        static::created(function ($property) {
            // Update registration plan progress and next available name
            if ($property->registration_plan_id) {
                $property->updateRegistrationPlanProgress();
                $property->updateNextAvailableName();

                // Log global sequence activity
                if ($property->registrationPlan && $property->registrationPlan->is_global_sequence) {
                    Log::info("Property {$property->id} created in global sequence plan {$property->registration_plan_id} with pattern: {$property->registration_pattern}", [
                        'sequence_position' => $property->sequence_position
                    ]);
                }
            }

            // ✅ CONSOLIDATED: mark plan as in-progress for the registering field agent
            if ($property->registration_plan_id && $property->registered_by) {
                $plan = RegistrationPlan::find($property->registration_plan_id);
                if ($plan && method_exists($plan, 'markInProgressIfAssigned')) {
                    $plan->markInProgressIfAssigned((int) $property->registered_by);
                }
            }

            // Log property creation with all details
            Log::info("Property {$property->id} created", [
                'type' => $property->display_property_type,
                'is_rented' => $property->is_rented,
                'is_field_agent_registered' => $property->is_field_agent_registered,
                'registered_by' => $property->registered_by,
                'is_global_sequence' => $property->is_global_sequence,
                'sequence_position' => $property->sequence_position,
                'registration_pattern' => $property->registration_pattern,
                'status' => $property->status,
                'property_type_id' => $property->property_type_id,
                'construction_status' => $property->construction_status,
                'has_plans' => $property->has_plans,
                'construction_documents_count' => $property->getConstructionDocumentCount(),
                'has_coordinates' => $property->has_coordinates,
            ]);
        });

        static::updated(function ($property) {
            // Update registration plan progress if plan was changed
            if ($property->isDirty('registration_plan_id')) {
                $oldPlanId = $property->getOriginal('registration_plan_id');
                $newPlanId = $property->registration_plan_id;

                // Update old plan progress
                if ($oldPlanId) {
                    $oldPlan = RegistrationPlan::find($oldPlanId);
                    if ($oldPlan) {
                        $property->updatePlanProgress($oldPlan);
                        $property->updateNextAvailableNameForPlan($oldPlan);
                    }
                }

                // Update new plan progress
                if ($newPlanId) {
                    $property->updateRegistrationPlanProgress();
                    $property->updateNextAvailableName();
                }

                // Update is_global_sequence flag
                $newPlan = RegistrationPlan::find($newPlanId);
                if ($newPlan) {
                    $property->is_global_sequence = $newPlan->is_global_sequence;
                    $property->saveQuietly();
                }
            }

            // Regenerate registration pattern if registration plan changed
            if ($property->isDirty('registration_plan_id') && $property->registration_plan_id) {
                $property->registration_pattern = $property->generateRegistrationPattern();
                $property->saveQuietly(); // Save without triggering events
            }

            // Update sequence position if it's a global sequence property
            if ($property->isDirty('is_global_sequence') && $property->is_global_sequence) {
                $property->sequence_position = $property->getNextSequencePosition($property->registration_plan_id);
                $property->saveQuietly();
            }

            // Clear custom property type if property type is changed to non-custom
            if ($property->isDirty('property_type_id')) {
                $newPropertyType = PropertyType::find($property->property_type_id);
                if ($newPropertyType && $newPropertyType->slug !== 'custom') {
                    $property->custom_property_type = null;
                    $property->saveQuietly();
                }

                // Log property type change
                Log::info("Property {$property->id} property type changed to: {$property->display_property_type}", [
                    'old_type_id' => $property->getOriginal('property_type_id'),
                    'new_type_id' => $property->property_type_id
                ]);
            }

            // Log field agent registration changes
            if ($property->isDirty('is_field_agent_registered')) {
                Log::info("Property {$property->id} field agent registration changed", [
                    'old' => $property->getOriginal('is_field_agent_registered'),
                    'new' => $property->is_field_agent_registered,
                    'agent_id' => $property->registered_by
                ]);
            }

            // ✅ NEW: Log coordinate changes — useful for the driver route planner pipeline
            if ($property->isDirty('latitude') || $property->isDirty('longitude')) {
                Log::info("Property {$property->id} coordinates changed", [
                    'old_lat' => $property->getOriginal('latitude'),
                    'old_lng' => $property->getOriginal('longitude'),
                    'new_lat' => $property->latitude,
                    'new_lng' => $property->longitude,
                ]);
            }

            // Log rental status changes and update tenant counts
            if ($property->isDirty('is_rented')) {
                Log::info("Property {$property->id} rental status changed", [
                    'old' => $property->getOriginal('is_rented'),
                    'new' => $property->is_rented
                ]);

                // Auto-update property status based on rental status
                if ($property->is_rented) {
                    $property->status = self::STATUS_ACTIVE;
                    $property->saveQuietly();
                } elseif (!$property->hasActiveTenants()) {
                    // Only mark as vacant if no active tenants
                    $property->status = self::STATUS_VACANT;
                    $property->saveQuietly();
                }
            }

            // Log custom property type updates
            if ($property->isDirty('custom_property_type') && $property->custom_property_type) {
                Log::info("Property {$property->id} custom property type updated to: {$property->custom_property_type}");
            }

            // Log registration pattern changes
            if ($property->isDirty('registration_pattern')) {
                Log::info("Property {$property->id} registration pattern changed", [
                    'old' => $property->getOriginal('registration_pattern'),
                    'new' => $property->registration_pattern
                ]);
            }

            // Log tenant count changes
            if ($property->isDirty('tenant_count')) {
                Log::info("Property {$property->id} tenant count changed", [
                    'old' => $property->getOriginal('tenant_count'),
                    'new' => $property->tenant_count
                ]);
            }

            if ($property->isDirty('active_tenant_count')) {
                Log::info("Property {$property->id} active tenant count changed", [
                    'old' => $property->getOriginal('active_tenant_count'),
                    'new' => $property->active_tenant_count
                ]);
            }

            // Log construction status changes
            if ($property->isDirty('status') && $property->status === self::STATUS_UNDER_CONSTRUCTION) {
                Log::info("Property {$property->id} marked as under construction", [
                    'old_status' => $property->getOriginal('status'),
                    'construction_status' => $property->construction_status,
                    'estimated_completion' => $property->estimated_completion,
                ]);
            }

            if ($property->isDirty('construction_status')) {
                Log::info("Property {$property->id} construction status changed", [
                    'old' => $property->getOriginal('construction_status'),
                    'new' => $property->construction_status
                ]);
            }

            // ⭐ NEW: Log has_plans changes
            if ($property->isDirty('has_plans')) {
                Log::info("Property {$property->id} has_plans changed", [
                    'old' => $property->getOriginal('has_plans'),
                    'new' => $property->has_plans
                ]);
            }

            // ⭐ NEW: Log construction_documents changes
            if ($property->isDirty('construction_documents')) {
                Log::info("Property {$property->id} construction_documents changed", [
                    'old_count' => $property->getOriginal('construction_documents') ? count(json_decode($property->getOriginal('construction_documents'), true) ?? []) : 0,
                    'new_count' => $property->getConstructionDocumentCount()
                ]);
            }

            // Log global sequence updates
            if ($property->registrationPlan && $property->registrationPlan->is_global_sequence) {
                Log::info("Property {$property->id} updated in global sequence plan {$property->registration_plan_id}", [
                    'sequence_position' => $property->sequence_position
                ]);
            }
        });

        static::deleted(function ($property) {
            // Update registration plan progress after deletion
            if ($property->registration_plan_id && !$property->isForceDeleting()) {
                $property->updateRegistrationPlanProgress();
                $property->updateNextAvailableName();

                // Log global sequence deletion
                if ($property->registrationPlan && $property->registrationPlan->is_global_sequence) {
                    Log::info("Property {$property->id} deleted from global sequence plan {$property->registration_plan_id}", [
                        'registration_pattern' => $property->registration_pattern,
                        'sequence_position' => $property->sequence_position
                    ]);
                }
            }

            // Log property deletion with all details
            Log::info("Property {$property->id} deleted", [
                'type' => $property->display_property_type,
                'is_rented' => $property->is_rented,
                'tenants_count' => $property->tenants_count,
                'is_field_agent_registered' => $property->is_field_agent_registered,
                'is_global_sequence' => $property->is_global_sequence,
                'registration_pattern' => $property->registration_pattern,
                'status' => $property->status,
            ]);
        });

        // ✅ NEW: Soft-delete related waste collection requests when a property is trashed
        // (Never runs when force-deleting — DB constraints should handle that case)
        static::deleting(function ($property) {
            if ($property->isForceDeleting()) {
                return;
            }

            try {
                $property->wasteCollectionRequests()->each(function ($request) {
                    // Only soft-delete if the request model supports it
                    if (method_exists($request, 'delete')) {
                        $request->delete();
                    }
                });

                Log::info("Property {$property->id} soft-deleted along with its waste collection requests");
            } catch (\Exception $e) {
                Log::warning("Failed to cascade-soft-delete waste collection requests for property {$property->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::restored(function ($property) {
            // Update registration plan progress after restoration
            if ($property->registration_plan_id) {
                $property->updateRegistrationPlanProgress();
                $property->updateNextAvailableName();

                // Log global sequence restoration
                if ($property->registrationPlan && $property->registrationPlan->is_global_sequence) {
                    Log::info("Property {$property->id} restored in global sequence plan {$property->registration_plan_id}", [
                        'registration_pattern' => $property->registration_pattern,
                        'sequence_position' => $property->sequence_position
                    ]);
                }
            }

            // Log property restoration with all details
            Log::info("Property {$property->id} restored", [
                'type' => $property->display_property_type,
                'is_rented' => $property->is_rented,
                'is_field_agent_registered' => $property->is_field_agent_registered,
                'is_global_sequence' => $property->is_global_sequence,
                'registration_pattern' => $property->registration_pattern,
                'status' => $property->status,
            ]);
        });
    }

    // ==================== PHOTO RELATIONSHIPS ====================

    /**
     * Relationship to property photos
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class, 'property_id');
    }

    /**
     * Get the waste collection requests for this property.
     */
    public function wasteCollectionRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class);
    }

    /**
     * Get the active waste collection requests for this property.
     */
    public function activeWasteRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class)
            ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress']);
    }

    /**
     * Get the completed waste collection requests for this property.
     */
    public function completedWasteRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class)
            ->where('status', 'completed');
    }

    /**
     * Get the total waste collected from this property.
     */
    public function getTotalWasteCollectedAttribute()
    {
        return $this->wasteCollectionRequests()
            ->where('status', 'completed')
            ->sum('waste_weight_kg');
    }

    /**
     * Get the last waste collection date for this property.
     */
    public function getLastWasteCollectionAttribute()
    {
        $last = $this->wasteCollectionRequests()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
        return $last ? $last->completed_at : null;
    }

    /**
     * Get the waste collection status for this property.
     */
    public function getWasteStatusAttribute()
    {
        $pending = $this->activeWasteRequests()->count();
        if ($pending > 0) {
            return 'pending';
        }
        $last = $this->getLastWasteCollectionAttribute();
        if ($last && $last->diffInDays(now()) < 7) {
            return 'collected_recently';
        }
        return 'needs_collection';
    }

    /**
     * Check if this property has a pending waste collection request.
     */
    public function hasPendingWasteRequest(): bool
    {
        return $this->activeWasteRequests()->exists();
    }

    /**
     * Request waste collection for this property.
     *
     * ✅ UPDATED: Now sets the approval workflow fields the sanitation
     * controller expects, so a request created via this method flows through
     * landlord approval exactly like one created via `linkProperty()`.
     */
    public function requestWasteCollection($requestedBy, array $data = [])
    {
        // Lazily pull the approval-workflow class if present
        $approvalExpiryDays = class_exists(WasteCollectionRequest::class) && defined(WasteCollectionRequest::class . '::APPROVAL_EXPIRY_DAYS')
            ? WasteCollectionRequest::APPROVAL_EXPIRY_DAYS
            : 7;

        $approvalToken = Str::random(64);

        return WasteCollectionRequest::create([
            'property_id'         => $this->id,
            'unit_id'             => $data['unit_id'] ?? null,
            'requested_by'        => $requestedBy,
            'waste_type'          => $data['waste_type'] ?? 'general',
            'priority'            => $data['priority'] ?? 'medium',
            'description'         => $data['description'] ?? null,
            'special_instructions'=> $data['special_instructions'] ?? null,
            'digital_address'     => $this->digital_address,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'status'              => 'pending',

            // ✅ NEW: approval workflow fields
            'approval_status'     => WasteCollectionRequest::APPROVAL_PENDING ?? 'pending',
            'approval_expires_at' => now()->addDays($approvalExpiryDays),

            // Keep metadata consistent with the sanitation flow
            'metadata' => [
                'collection_linked'          => true,
                'requires_landlord_approval' => true,
                'landlord_id'                => $this->landlord_id,
                'landlord_name'              => $this->landlord?->name,
                'linked_by'                  => $requestedBy,
                'linked_at'                  => now()->toISOString(),
                'linked_via'                 => 'property_model_helper',
                'approval_token'             => $approvalToken,
                'coordinates_source'         => $this->has_coordinates ? 'property' : 'none',
            ],
        ]);
    }

    /**
     * Get the construction contracts for this property
     */
    public function constructionContracts()
    {
        return $this->hasMany(ConstructionContract::class, 'property_id');
    }

    /**
     * Relationship to primary property photo
     */
    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(PropertyPhoto::class, 'property_id')->where('is_primary', true);
    }

    /**
     * Get the primary photo URL attribute
     */
    public function getPrimaryPhotoUrlAttribute(): ?string
    {
        $primaryPhoto = $this->photos()->where('is_primary', true)->first();
        if ($primaryPhoto) {
            return $primaryPhoto->photo_url;
        }

        // Return first photo if no primary is set
        $firstPhoto = $this->photos()->first();
        return $firstPhoto ? $firstPhoto->photo_url : null;
    }

    /**
     * Get the primary photo thumbnail URL attribute
     */
    public function getPrimaryPhotoThumbnailUrlAttribute(): ?string
    {
        $primaryPhoto = $this->photos()->where('is_primary', true)->first();
        if ($primaryPhoto) {
            return $primaryPhoto->thumbnail_url;
        }

        // Return first photo if no primary is set
        $firstPhoto = $this->photos()->first();
        return $firstPhoto ? $firstPhoto->thumbnail_url : null;
    }

    /**
     * Get the count of photos attribute
     */
    public function getPhotosCountAttribute(): int
    {
        if (array_key_exists('photos_count', $this->attributes)) {
            return $this->attributes['photos_count'];
        }
        return $this->photos()->count();
    }

    /**
     * Check if property has photos attribute
     */
    public function getHasPhotosAttribute(): bool
    {
        return $this->photos_count > 0;
    }

    /**
     * Get photo gallery attribute (array of photo URLs for API)
     */
    public function getPhotoGalleryAttribute(): array
    {
        return $this->photos()
            ->orderBy('is_primary', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
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
                    'created_at' => $photo->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();
    }

    /**
     * Get photo gallery for display in views
     */
    public function getPhotosForDisplay(): array
    {
        $photos = $this->photos()
            ->orderBy('is_primary', 'desc')
            ->orderBy('sort_order', 'asc')
            ->get();

        return [
            'primary' => $photos->where('is_primary', true)->first(),
            'others' => $photos->where('is_primary', false),
            'count' => $photos->count(),
            'has_photos' => $photos->count() > 0,
        ];
    }

    /**
     * Add a photo to the property
     */
    public function addPhoto($photoFile, $isPrimary = false, $caption = null, $sortOrder = null): PropertyPhoto
    {
        $sortOrder = $sortOrder ?? ($this->photos()->max('sort_order') + 1);

        // If this is the first photo, make it primary
        if ($this->photos()->count() === 0) {
            $isPrimary = true;
        }

        // If setting as primary, remove primary flag from other photos
        if ($isPrimary) {
            $this->photos()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $propertyPhoto = new PropertyPhoto([
            'property_id' => $this->id,
            'is_primary' => $isPrimary,
            'caption' => $caption,
            'sort_order' => $sortOrder,
        ]);

        $propertyPhoto->uploadPhoto($photoFile);
        $propertyPhoto->save();

        Log::info("Photo added to property {$this->id}", [
            'photo_id' => $propertyPhoto->id,
            'is_primary' => $isPrimary
        ]);

        return $propertyPhoto;
    }

    /**
     * Add multiple photos to the property
     */
    public function addMultiplePhotos(array $photoFiles): array
    {
        $results = [];
        $isFirstPrimary = $this->photos()->count() === 0;

        foreach ($photoFiles as $index => $photoFile) {
            $isPrimary = $isFirstPrimary && $index === 0;
            $result = $this->addPhoto($photoFile, $isPrimary);
            $results[] = [
                'success' => true,
                'photo_id' => $result->id,
                'is_primary' => $result->is_primary,
            ];
        }

        Log::info("Multiple photos added to property {$this->id}", [
            'count' => count($photoFiles),
            'photos_count' => $this->photos()->count()
        ]);

        return $results;
    }

    /**
     * Set a specific photo as primary
     */
    public function setPrimaryPhoto($photoId): bool
    {
        $photo = $this->photos()->find($photoId);
        if (!$photo) {
            return false;
        }

        // Remove primary flag from all photos
        $this->photos()->where('is_primary', true)->update(['is_primary' => false]);

        // Set the new primary photo
        $photo->is_primary = true;
        $photo->save();

        Log::info("Primary photo set for property {$this->id}", [
            'photo_id' => $photoId
        ]);

        return true;
    }

    /**
     * Delete a photo from the property
     */
    public function deletePhoto($photoId): bool
    {
        $photo = $this->photos()->find($photoId);
        if (!$photo) {
            return false;
        }

        $wasPrimary = $photo->is_primary;
        $photo->delete();

        // If deleted photo was primary and there are other photos, set another as primary
        if ($wasPrimary) {
            $newPrimary = $this->photos()->first();
            if ($newPrimary) {
                $newPrimary->is_primary = true;
                $newPrimary->save();
            }
        }

        Log::info("Photo deleted from property {$this->id}", [
            'photo_id' => $photoId,
            'was_primary' => $wasPrimary
        ]);

        return true;
    }

    /**
     * Delete multiple photos from the property
     */
    public function deleteMultiplePhotos(array $photoIds): array
    {
        $results = [];

        foreach ($photoIds as $photoId) {
            $success = $this->deletePhoto($photoId);
            $results[] = [
                'photo_id' => $photoId,
                'success' => $success,
            ];
        }

        Log::info("Multiple photos deleted from property {$this->id}", [
            'count' => count($photoIds),
            'remaining_photos' => $this->photos()->count()
        ]);

        return $results;
    }

    /**
     * Reorder photos by setting sort order
     */
    public function reorderPhotos(array $photoOrders): bool
    {
        foreach ($photoOrders as $order) {
            $photo = $this->photos()->find($order['id']);
            if ($photo) {
                $photo->sort_order = $order['sort_order'];
                $photo->save();
            }
        }

        Log::info("Photos reordered for property {$this->id}");

        return true;
    }

    /**
     * Update photo caption
     */
    public function updatePhotoCaption($photoId, $caption): bool
    {
        $photo = $this->photos()->find($photoId);
        if (!$photo) {
            return false;
        }

        $photo->caption = $caption;
        $photo->save();

        Log::info("Photo caption updated for property {$this->id}", [
            'photo_id' => $photoId
        ]);

        return true;
    }

    /**
     * Get photo statistics
     */
    public function getPhotoStatistics(): array
    {
        $totalPhotos = $this->photos()->count();
        $primaryPhoto = $this->photos()->where('is_primary', true)->first();

        return [
            'total_photos' => $totalPhotos,
            'has_photos' => $totalPhotos > 0,
            'has_primary_photo' => !is_null($primaryPhoto),
            'primary_photo_id' => $primaryPhoto?->id,
            'primary_photo_url' => $primaryPhoto?->photo_url,
            'primary_photo_thumbnail_url' => $primaryPhoto?->thumbnail_url,
            'photos_by_format' => $this->photos()
                ->selectRaw('photo_format, count(*) as count')
                ->groupBy('photo_format')
                ->pluck('count', 'photo_format')
                ->toArray(),
            'total_size_bytes' => $this->photos()->sum('file_size'),
            'total_size_mb' => round($this->photos()->sum('file_size') / (1024 * 1024), 2),
        ];
    }

    // ==================== OTHER RELATIONSHIPS ====================

    /**
     * Relationship to landlord (user)
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Relationship to creator (user)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship to field agent who registered the property
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Alias for field agent relationship
     */
    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Relationship to registration plan
     */
    public function registrationPlan(): BelongsTo
    {
        return $this->belongsTo(RegistrationPlan::class, 'registration_plan_id')
            ->with(['continuedFromPlan', 'continuedByPlans']);
    }

    /**
     * Relationship to property type
     */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

   /**
     * Relationship to property units (apartments, rooms, etc.)
     */
    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id');
    }

    /**
     * Get approved units with tenants
     */
    public function approvedUnits(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id')
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id');
    }

     /**
     * Get pending units with tenants
     */
    public function pendingUnits(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id')
            ->where('tenant_status', 'pending_approval')
            ->whereNotNull('tenant_id');
    }

    /**
     * Get occupied units
     */
    public function occupiedUnits(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id')
            ->where('status', 'occupied');
    }

    /**
     * Get available units
     */
    public function availableUnits(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id')
            ->where('status', 'available');
    }

    /**
     * Get all tenants including those assigned through units
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllTenants()
    {
        // Get tenants directly assigned to property (via pivot table)
        $directTenants = $this->tenants;

        // Get tenants through units (approved tenants only)
        $unitTenantIds = $this->units()
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->toArray();

        $unitTenants = collect();
        if (!empty($unitTenantIds)) {
            $unitTenants = User::whereIn('id', $unitTenantIds)->get();
        }

        // Get pending tenants through units
        $pendingUnitTenantIds = $this->units()
            ->where('tenant_status', 'pending_approval')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->toArray();

        $pendingTenants = collect();
        if (!empty($pendingUnitTenantIds)) {
            $pendingTenants = User::whereIn('id', $pendingUnitTenantIds)->get();
        }

        // Merge all tenants and remove duplicates
        $allTenants = $directTenants->merge($unitTenants)->merge($pendingTenants)->unique('id');

        return $allTenants;
    }

    /**
     * Get all approved tenants (direct + through units)
     */
    public function getAllApprovedTenants()
    {
        $directTenants = $this->tenants()->wherePivot('status', 'active')->get();

        $unitTenantIds = $this->units()
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id')
            ->toArray();

        $unitTenants = collect();
        if (!empty($unitTenantIds)) {
            $unitTenants = User::whereIn('id', $unitTenantIds)->get();
        }

        return $directTenants->merge($unitTenants)->unique('id');
    }

    /**
     * Get tenant count including those assigned through units
     */
    public function getTotalTenantCount(): int
    {
        $directCount = $this->tenants()->count();
        $unitCount = $this->units()
            ->whereNotNull('tenant_id')
            ->whereIn('tenant_status', ['approved', 'pending_approval'])
            ->distinct('tenant_id')
            ->count('tenant_id');

        return $directCount + $unitCount;
    }

    /**
     * Get approved tenant count including those assigned through units
     */
    public function getApprovedTenantCount(): int
    {
        $directCount = $this->tenants()->wherePivot('status', 'active')->count();
        $unitCount = $this->units()
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->distinct('tenant_id')
            ->count('tenant_id');

        return $directCount + $unitCount;
    }

    /**
     * ✅ UPDATED: Relationship to tenant users (User model) through property_tenant pivot table
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_tenant', 'property_id', 'user_id')
                    ->withPivot('added_by', 'added_at', 'notes', 'status', 'start_date', 'end_date')
                    ->withTimestamps()
                    ->where('type', User::TYPE_TENANT); // Filter only tenant users
    }

    /**
     * ✅ UPDATED: Relationship to active tenant users
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('status', 'active');
    }

    /**
     * ✅ UPDATED: Relationship to registration tenants (from registration process)
     */
    public function registrationTenants(): HasMany
    {
        return $this->hasMany(RegistrationTenant::class, 'property_id');
    }

    /**
     * ✅ UPDATED: Relationship to approved registration tenants
     */
    public function approvedRegistrationTenants(): HasMany
    {
        return $this->registrationTenants()->where('status', 'approved');
    }

    /**
     * ✅ UPDATED: Relationship to tenant invitations
     */
    public function tenantInvitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * ✅ NEW: Relationship to sent tenant invitations
     */
    public function sentTenantInvitations(): HasMany
    {
        return $this->tenantInvitations()->where('status', TenantInvitation::STATUS_SENT);
    }

    /**
     * ✅ NEW: Relationship to accepted tenant invitations
     */
    public function acceptedTenantInvitations(): HasMany
    {
        return $this->tenantInvitations()->where('status', TenantInvitation::STATUS_ACCEPTED);
    }

    /**
     * Relationship to landlord invitations
     */
    public function landlordInvitations(): HasMany
    {
        return $this->hasMany(LandlordInvitation::class);
    }

    /**
     * Relationship to invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Relationship to payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Relationship to maintenance requests
     */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * Relationship to ownership transfers
     */
    public function ownershipTransfers(): HasMany
    {
        return $this->hasMany(PropertyOwnershipTransfer::class);
    }

    /**
     * FIXED: Get current ownership transfer - Changed return type from HasMany to HasOne
     */
    public function currentOwnershipTransfer(): HasOne  // FIXED: Changed from HasMany to HasOne
    {
        return $this->hasOne(PropertyOwnershipTransfer::class)
                    ->whereIn('status', [
                        PropertyOwnershipTransfer::STATUS_PENDING,
                        PropertyOwnershipTransfer::STATUS_APPROVED
                    ])
                    ->latest();
    }

    /**
     * Get previous ownership transfers
     */
    public function previousOwnershipTransfers(): HasMany
    {
        return $this->hasMany(PropertyOwnershipTransfer::class)
                    ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
                    ->latest();
    }

    // ==================== SCOPES ====================

    /**
     * Scope for active site allocations
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for vacant site allocations
     */
    public function scopeVacant($query)
    {
        return $query->where('status', self::STATUS_VACANT);
    }

    /**
     * Scope for site allocations under maintenance
     */
    public function scopeUnderMaintenance($query)
    {
        return $query->where('status', self::STATUS_UNDER_MAINTENANCE);
    }

    /**
     * ✅ NEW: Scope for site allocations under construction
     */
    public function scopeUnderConstruction($query)
    {
        return $query->where('status', self::STATUS_UNDER_CONSTRUCTION);
    }

    /**
     * ✅ NEW: Scope for properties that have coordinates set
     */
    public function scopeHasCoordinates($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * ✅ NEW: Scope for properties missing coordinates
     */
    public function scopeMissingCoordinates($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('latitude')->orWhereNull('longitude');
        });
    }

    /**
     * Scope for site allocations in global sequences
     */
    public function scopeGlobalSequence($query)
    {
        return $query->where('is_global_sequence', true);
    }

    /**
     * Scope for rented properties
     */
    public function scopeRented($query)
    {
        return $query->where('is_rented', true);
    }

    /**
     * Scope for non-rented properties
     */
    public function scopeNotRented($query)
    {
        return $query->where('is_rented', false);
    }

    /**
     * Scope for properties by property type
     */
    public function scopeByPropertyType($query, $typeSlug)
    {
        return $query->whereHas('propertyType', function($q) use ($typeSlug) {
            $q->where('slug', $typeSlug);
        });
    }

    /**
     * Scope for properties with custom types
     */
    public function scopeCustomType($query)
    {
        return $query->whereHas('propertyType', function($q) {
            $q->where('slug', 'custom');
        })->whereNotNull('custom_property_type');
    }

    /**
     * Scope for properties by multiple property types
     */
    public function scopeByPropertyTypes($query, array $typeSlugs)
    {
        return $query->whereHas('propertyType', function($q) use ($typeSlugs) {
            $q->whereIn('slug', $typeSlugs);
        });
    }

    /**
     * Scope for properties by property type ID
     */
    public function scopeByPropertyTypeId($query, $typeId)
    {
        return $query->where('property_type_id', $typeId);
    }

    /**
     * Scope for residential properties
     */
    public function scopeResidential($query)
    {
        return $query->whereHas('propertyType', function($q) {
            $q->whereIn('slug', ['resident', 'apartment', 'house', 'bungalow']);
        });
    }

    /**
     * Scope for commercial properties
     */
    public function scopeCommercial($query)
    {
        return $query->whereHas('propertyType', function($q) {
            $q->whereIn('slug', ['commercial', 'shop', 'office', 'restaurant', 'hotel', 'mall']);
        });
    }

    /**
     * Scope for institutional properties
     */
    public function scopeInstitutional($query)
    {
        return $query->whereHas('propertyType', function($q) {
            $q->whereIn('slug', ['school', 'hospital', 'police-station', 'government-building']);
        });
    }

    /**
     * Scope for religious properties
     */
    public function scopeReligious($query)
    {
        return $query->whereHas('propertyType', function($q) {
            $q->whereIn('slug', ['church', 'mosque', 'temple', 'religious-building']);
        });
    }

    /**
     * Scope for field agent registered properties
     */
    public function scopeFieldAgentRegistered($query)
    {
        return $query->where('is_field_agent_registered', true);
    }

    /**
     * Scope for admin registered properties
     */
    public function scopeAdminRegistered($query)
    {
        return $query->where('is_field_agent_registered', false);
    }

    /**
     * Scope for properties registered by specific field agent
     */
    public function scopeRegisteredBy($query, $agentId)
    {
        return $query->where('registered_by', $agentId);
    }

    /**
     * Scope for properties with specific sequence position
     */
    public function scopeBySequencePosition($query, $position)
    {
        return $query->where('sequence_position', $position);
    }

    /**
     * Scope for properties with sequence position range
     */
    public function scopeSequencePositionBetween($query, $min, $max)
    {
        return $query->whereBetween('sequence_position', [$min, $max]);
    }

    /**
     * Scope for properties registered by field agent in date range
     */
    public function scopeFieldAgentRegisteredBetween($query, $startDate, $endDate)
    {
        return $query->where('is_field_agent_registered', true)
                     ->whereBetween('field_agent_registered_at', [$startDate, $endDate]);
    }

    /**
     * ✅ UPDATED: Scope for properties with tenants (User model)
     */
    public function scopeWithTenants($query)
    {
        return $query->whereHas('tenants');
    }

    /**
     * ✅ UPDATED: Scope for properties without tenants (User model)
     */
    public function scopeWithoutTenants($query)
    {
        return $query->whereDoesntHave('tenants');
    }

    /**
     * ✅ UPDATED: Scope for properties with active tenants (User model)
     */
    public function scopeWithActiveTenants($query)
    {
        return $query->whereHas('activeTenants');
    }

    /**
     * ✅ NEW: Scope for properties with photos
     */
    public function scopeWithPhotos($query)
    {
        return $query->whereHas('photos');
    }

    /**
     * ✅ NEW: Scope for properties without photos
     */
    public function scopeWithoutPhotos($query)
    {
        return $query->whereDoesntHave('photos');
    }

    /**
     * ✅ NEW: Scope for properties with primary photo
     */
    public function scopeWithPrimaryPhoto($query)
    {
        return $query->whereHas('primaryPhoto');
    }

    /**
     * ✅ NEW: Scope for properties with registration tenants (from registration process)
     */
    public function scopeWithRegistrationTenants($query)
    {
        return $query->whereHas('registrationTenants');
    }

    /**
     * ✅ NEW: Scope for properties with approved registration tenants
     */
    public function scopeWithApprovedRegistrationTenants($query)
    {
        return $query->whereHas('registrationTenants', function($q) {
            $q->where('status', 'approved');
        });
    }

    /**
     * ✅ NEW: Scope for properties with pending registration tenants
     */
    public function scopeWithPendingRegistrationTenants($query)
    {
        return $query->whereHas('registrationTenants', function($q) {
            $q->where('status', 'pending');
        });
    }

    /**
     * Scope for properties rented but without tenants
     */
    public function scopeRentedWithoutTenants($query)
    {
        return $query->where('is_rented', true)->whereDoesntHave('tenants');
    }

    /**
     * Scope for properties assigned to field agent (via plan assignments)
     */
    public function scopeAssignedToAgent($query, $agentId)
    {
        return $query->whereHas('registrationPlan.planAssignments', function($q) use ($agentId) {
            $q->where('agent_id', $agentId)->where('is_active', true);
        });
    }

    /**
     * Scope for site allocations by street
     */
    public function scopeByStreet($query, $streetName)
    {
        return $query->where('street_name', 'like', "%{$streetName}%");
    }

    /**
     * Scope for site allocations by zone
     */
    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', 'like', "%{$zone}%");
    }

    /**
     * Scope for site allocations by section
     */
    public function scopeBySection($query, $section)
    {
        return $query->where('section', 'like', "%{$section}%");
    }

    /**
     * Scope for site allocations by landlord
     */
    public function scopeByLandlord($query, $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    /**
     * Scope for site allocations by digital address
     */
    public function scopeByDigitalAddress($query, $digitalAddress)
    {
        return $query->where('digital_address', 'like', "%{$digitalAddress}%");
    }

    /**
     * Scope for site allocations with digital address
     */
    public function scopeHasDigitalAddress($query)
    {
        return $query->whereNotNull('digital_address')->where('digital_address', '!=', '');
    }

    /**
     * Scope for site allocations without digital address
     */
    public function scopeMissingDigitalAddress($query)
    {
        return $query->where(function($q) {
            $q->whereNull('digital_address')->orWhere('digital_address', '');
        });
    }

    /**
     * Scope for site allocations by registration plan
     */
    public function scopeByRegistrationPlan($query, $planId)
    {
        return $query->where('registration_plan_id', $planId);
    }

    /**
     * Scope for site allocations by property name
     */
    public function scopeByPropertyName($query, $propertyName)
    {
        return $query->where('property_name', 'like', "%{$propertyName}%");
    }

    /**
     * Scope for site allocations by registration pattern
     */
    public function scopeByRegistrationPattern($query, $pattern)
    {
        return $query->where('registration_pattern', 'like', "%{$pattern}%");
    }

    /**
     * Scope for site allocations created by specific user
     */
    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Get site allocations with due for inspection
     */
    public function scopeDueForInspection($query, $months = 6)
    {
        $cutoffDate = now()->subMonths($months);
        return $query->where(function($q) use ($cutoffDate) {
            $q->whereNull('last_inspection_date')
              ->orWhere('last_inspection_date', '<=', $cutoffDate);
        });
    }

    /**
     * Scope for properties with recent activity
     */
    public function scopeWithRecentActivity($query, $days = 7)
    {
        return $query->where(function($q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days))
              ->orWhere('updated_at', '>=', now()->subDays($days))
              ->orWhere('last_inspection_date', '>=', now()->subDays($days));
        });
    }

    // ==================== ATTRIBUTES ====================

    /**
     * Get full address attribute
     */
    public function getFullAddressAttribute(): string
    {
        $address = [];

        if ($this->property_name) $address[] = $this->property_name;
        if ($this->registration_pattern) $address[] = "Pattern: {$this->registration_pattern}";
        if ($this->house_number) $address[] = "House {$this->house_number}";
        if ($this->street_name) $address[] = $this->street_name;
        if ($this->block_number) $address[] = "Block {$this->block_number}";
        if ($this->digital_address) $address[] = "Digital Address: {$this->digital_address}";
        if ($this->zone) $address[] = "Zone: {$this->zone}";
        if ($this->section) $address[] = "Section: {$this->section}";

        return implode(', ', $address);
    }

    /**
     * Get physical address attribute (without digital address)
     */
    public function getPhysicalAddressAttribute(): string
    {
        $address = [];

        if ($this->property_name) $address[] = $this->property_name;
        if ($this->registration_pattern) $address[] = "Pattern: {$this->registration_pattern}";
        if ($this->house_number) $address[] = "House {$this->house_number}";
        if ($this->street_name) $address[] = $this->street_name;
        if ($this->block_number) $address[] = "Block {$this->block_number}";
        if ($this->zone) $address[] = "Zone: {$this->zone}";
        if ($this->section) $address[] = "Section: {$this->section}";

        return implode(', ', $address);
    }

    /**
     * Check if site allocation has digital address
     */
    public function getHasDigitalAddressAttribute(): bool
    {
        return !empty($this->digital_address);
    }

    /**
     * Get site allocation identifier (combination of property name and registration pattern)
     */
    public function getSiteAllocationIdentifierAttribute(): string
    {
        if ($this->property_name && $this->registration_pattern) {
            $identifier = "{$this->property_name} ({$this->registration_pattern})";

            // Add global sequence badge if applicable
            if ($this->is_global_sequence) {
                $identifier .= " 🌐"; // Global sequence indicator
            }

            return $identifier;
        } elseif ($this->property_name) {
            return $this->property_name;
        } elseif ($this->registration_pattern) {
            return "Site Allocation {$this->registration_pattern}";
        } else {
            return 'Site Allocation-' . $this->id;
        }
    }

    /**
     * Get ownership information
     */
    public function getOwnershipInfoAttribute(): string
    {
        if ($this->landlord) {
            return "Owned by {$this->landlord->name}";
        }
        return "Ownership not assigned";
    }

    /**
     * Get site status badge information
     */
    public function getSiteStatusBadgeAttribute(): array
    {
        $statusInfo = [
            'class' => '',
            'text' => ucfirst(str_replace('_', ' ', $this->status)),
            'icon' => 'fas fa-circle'
        ];

        switch ($this->status) {
            case self::STATUS_ACTIVE:
                $statusInfo['class'] = 'success';
                $statusInfo['icon'] = 'fas fa-check-circle';
                break;
            case self::STATUS_VACANT:
                $statusInfo['class'] = 'info';
                $statusInfo['icon'] = 'fas fa-door-open';
                break;
            case self::STATUS_UNDER_MAINTENANCE:
                $statusInfo['class'] = 'warning';
                $statusInfo['icon'] = 'fas fa-tools';
                break;
            case self::STATUS_UNDER_CONSTRUCTION:
                $statusInfo['class'] = 'warning';
                $statusInfo['icon'] = 'fas fa-hard-hat';
                $statusInfo['text'] = 'Under Construction';
                break;
            case self::STATUS_INACTIVE:
                $statusInfo['class'] = 'secondary';
                $statusInfo['icon'] = 'fas fa-pause-circle';
                break;
        }

        return $statusInfo;
    }

    /**
     * Check if this property is part of a global sequence
     */
    public function getIsGlobalSequenceAttribute(): bool
    {
        return $this->attributes['is_global_sequence'] ?? ($this->registrationPlan && $this->registrationPlan->is_global_sequence);
    }

    /**
     * Get global sequence information
     */
    public function getGlobalSequenceInfoAttribute(): array
    {
        if (!$this->is_global_sequence) {
            return [
                'is_global_sequence' => false,
                'message' => 'Not part of a global sequence'
            ];
        }

        $plan = $this->registrationPlan;
        return [
            'is_global_sequence' => true,
            'plan_id' => $plan ? $plan->id : null,
            'zone' => $plan ? $plan->zone : $this->zone,
            'section' => $plan ? $plan->section : $this->section,
            'naming_pattern' => $plan ? $plan->naming_pattern : 'N/A',
            'continues_from_plan_id' => $plan ? $plan->continues_from_plan_id : null,
            'continued_from_plan' => $plan && $plan->continuedFromPlan ? [
                'id' => $plan->continuedFromPlan->id,
                'zone' => $plan->continuedFromPlan->zone,
                'section' => $plan->continuedFromPlan->section
            ] : null,
            'sequence_position' => $this->sequence_position ?? $this->getSequencePosition(),
            'total_in_sequence' => $plan ? $plan->properties()->count() : 0,
            'is_continuation' => $plan ? !is_null($plan->continues_from_plan_id) : false,
        ];
    }

    /**
     * Get display property type (with custom handling)
     */
    public function getDisplayPropertyTypeAttribute(): string
    {
        if ($this->custom_property_type) {
            return $this->custom_property_type;
        }

        return $this->propertyType ? $this->propertyType->name : 'Not Specified';
    }

    /**
     * Get property type icon
     */
    public function getPropertyTypeIconAttribute(): string
    {
        if ($this->propertyType) {
            return $this->propertyType->icon;
        }

        // Default icon based on custom type or fallback
        if ($this->custom_property_type) {
            return '🔧'; // Custom type icon
        }

        return '🏠'; // Default property icon
    }

    /**
     * Get property type color
     */
    public function getPropertyTypeColorAttribute(): string
    {
        if ($this->propertyType) {
            return $this->propertyType->color;
        }

        return '#6b7280'; // Default gray color
    }

    /**
     * Check if property has custom type
     */
    public function getIsCustomTypeAttribute(): bool
    {
        return !empty($this->custom_property_type);
    }

    /**
     * Get property type badge for UI display
     */
    public function getPropertyTypeBadgeAttribute(): array
    {
        $badge = [
            'name' => $this->display_property_type,
            'icon' => $this->property_type_icon,
            'color' => $this->property_type_color,
            'is_custom' => $this->is_custom_type,
            'class' => 'property-type-badge'
        ];

        // Add specific classes based on property type
        if ($this->isResidential()) {
            $badge['class'] .= ' residential-badge';
        } elseif ($this->isCommercial()) {
            $badge['class'] .= ' commercial-badge';
        } elseif ($this->isInstitutional()) {
            $badge['class'] .= ' institutional-badge';
        } elseif ($this->isReligious()) {
            $badge['class'] .= ' religious-badge';
        }

        return $badge;
    }

    /**
     * Get field agent registration badge
     */
    public function getIsFieldAgentRegisteredBadgeAttribute(): array
    {
        return [
            'is_registered' => $this->is_field_agent_registered,
            'text' => $this->is_field_agent_registered ? 'Field Agent Registered' : 'Admin Registered',
            'class' => $this->is_field_agent_registered ? 'info' : 'secondary',
            'icon' => $this->is_field_agent_registered ? 'fas fa-user-tie' : 'fas fa-user-cog',
            'agent_name' => $this->fieldAgent ? $this->fieldAgent->name : null,
            'registered_at' => $this->field_agent_registered_at?->format('M d, Y H:i'),
            'registered_at_human' => $this->field_agent_registered_at?->diffForHumans(),
        ];
    }

    /**
     * Get field agent information
     */
    public function getFieldAgentInfoAttribute(): ?array
    {
        if (!$this->is_field_agent_registered || !$this->registered_by) {
            return null;
        }

        return [
            'agent_id' => $this->registered_by,
            'agent_name' => $this->fieldAgent ? $this->fieldAgent->name : 'Unknown Agent',
            'phone' => $this->fieldAgent ? $this->fieldAgent->phone : null,
            'email' => $this->fieldAgent ? $this->fieldAgent->email : null,
            'registered_at' => $this->field_agent_registered_at?->format('M d, Y H:i'),
            'registered_at_human' => $this->field_agent_registered_at?->diffForHumans(),
            'is_active_agent' => $this->fieldAgent ? $this->fieldAgent->is_active : false
        ];
    }

    /**
     * Get rental status badge
     */
    public function getRentalStatusBadgeAttribute(): array
    {
        return [
            'is_rented' => $this->is_rented,
            'text' => $this->is_rented ? 'Rented' : 'Not Rented',
            'class' => $this->is_rented ? 'success' : 'secondary',
            'icon' => $this->is_rented ? 'fas fa-home' : 'fas fa-home',
            'has_tenants' => $this->is_rented && $this->hasTenants(),
            'tenants_count' => $this->tenants_count,
            'active_tenants_count' => $this->active_tenants_count,
        ];
    }

    /**
     * Get sequence information
     */
    public function getSequenceInfoAttribute(): array
    {
        return [
            'sequence_position' => $this->sequence_position,
            'is_global_sequence' => $this->is_global_sequence,
            'total_in_sequence' => $this->registrationPlan ? $this->registrationPlan->properties()->count() : 0,
            'position_in_sequence' => $this->getSequencePosition(),
            'next_position' => $this->getNextSequencePosition(),
            'prev_position' => $this->getPrevSequencePosition()
        ];
    }

    /**
     * Check if property is rented and has tenants
     */
    public function getIsRentedWithTenantsAttribute(): bool
    {
        return $this->is_rented && $this->hasTenants();
    }

    /**
     * ✅ UPDATED: Get tenants count attribute (User model)
     */
    public function getTenantsCountAttribute(): int
    {
        if (array_key_exists('tenants_count', $this->attributes)) {
            return $this->attributes['tenants_count'];
        }
        return $this->tenants()->count();
    }

    /**
     * ✅ UPDATED: Get active tenants count attribute (User model)
     */
    public function getActiveTenantsCountAttribute(): int
    {
        if (array_key_exists('active_tenant_count', $this->attributes)) {
            return $this->attributes['active_tenant_count'];
        }
        return $this->activeTenants()->count();
    }

    /**
     * ✅ NEW: Get registration tenants count (from registration process)
     */
    public function getRegistrationTenantsCountAttribute(): int
    {
        return $this->registrationTenants()->count();
    }

    /**
     * ✅ NEW: Get approved registration tenants count
     */
    public function getApprovedRegistrationTenantsCountAttribute(): int
    {
        return $this->registrationTenants()->where('status', 'approved')->count();
    }

    /**
     * ✅ NEW: Get pending registration tenants count
     */
    public function getPendingRegistrationTenantsCountAttribute(): int
    {
        return $this->registrationTenants()->where('status', 'pending')->count();
    }

    /**
     * Get current tenant information (User model)
     */
    public function getCurrentTenantInfoAttribute(): ?array
    {
        $currentTenant = $this->getCurrentTenant();
        if (!$currentTenant) {
            return null;
        }

        return [
            'id' => $currentTenant->id,
            'name' => $currentTenant->name,
            'phone' => $currentTenant->phone,
            'email' => $currentTenant->email,
            'start_date' => $currentTenant->pivot->start_date?->format('M d, Y'),
            'notes' => $currentTenant->pivot->notes,
        ];
    }

    /**
     * Get tenant stats summary
     */
    public function getTenantStatsSummaryAttribute(): array
    {
        return [
            'total_tenants' => $this->tenants_count,
            'active_tenants' => $this->active_tenants_count,
            'inactive_tenants' => $this->tenants_count - $this->active_tenants_count,
            'has_tenants' => $this->hasTenants(),
            'is_rented' => $this->is_rented,
            'vacancy_status' => $this->active_tenants_count > 0 ? 'occupied' : 'vacant',
            'registration_tenants_count' => $this->registration_tenants_count,
            'approved_registration_tenants' => $this->approved_registration_tenants_count,
            'pending_registration_tenants' => $this->pending_registration_tenants_count,
        ];
    }

    /**
     * ✅ NEW: Check if property is under construction
     */
    public function getIsUnderConstructionAttribute(): bool
    {
        return $this->status === self::STATUS_UNDER_CONSTRUCTION;
    }

    /**
     * ✅ NEW: Get formatted construction status
     */
    public function getFormattedConstructionStatusAttribute(): string
    {
        if (!$this->construction_status) {
            return 'Not Started';
        }

        $statusMap = [
            'not_started' => 'Not Started',
            'planned' => 'Planned',
            'under_construction' => 'Under Construction',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
        ];

        return $statusMap[$this->construction_status] ?? ucfirst(str_replace('_', ' ', $this->construction_status));
    }

    /**
     * ✅ NEW: Get construction status badge
     */
    public function getConstructionStatusBadgeAttribute(): array
    {
        $badge = [
            'status' => $this->construction_status,
            'text' => $this->formatted_construction_status,
            'icon' => 'fas fa-hard-hat',
            'class' => 'secondary',
        ];

        switch ($this->construction_status) {
            case 'under_construction':
                $badge['class'] = 'warning';
                $badge['icon'] = 'fas fa-hard-hat';
                break;
            case 'completed':
                $badge['class'] = 'success';
                $badge['icon'] = 'fas fa-check-circle';
                break;
            case 'planned':
                $badge['class'] = 'info';
                $badge['icon'] = 'fas fa-calendar-alt';
                break;
            case 'on_hold':
                $badge['class'] = 'danger';
                $badge['icon'] = 'fas fa-pause-circle';
                break;
            default:
                $badge['class'] = 'secondary';
                $badge['icon'] = 'fas fa-question-circle';
        }

        return $badge;
    }

    /**
     * ✅ NEW: Get construction progress summary
     */
    public function getConstructionProgressAttribute(): array
    {
        $progress = [
            'status' => $this->status,
            'is_under_construction' => $this->is_under_construction,
            'construction_status' => $this->construction_status,
            'formatted_construction_status' => $this->formatted_construction_status,
            'estimated_completion' => $this->estimated_completion?->format('M d, Y'),
            'estimated_completion_human' => $this->estimated_completion?->diffForHumans(),
            'days_remaining' => $this->estimated_completion ? now()->diffInDays($this->estimated_completion, false) : null,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'badge' => $this->construction_status_badge,
        ];

        // Calculate completion percentage based on status
        $completionPercentage = match($this->construction_status) {
            'completed' => 100,
            'under_construction' => 50,
            'planned' => 25,
            'on_hold' => 25,
            default => 0,
        };

        $progress['completion_percentage'] = $completionPercentage;

        return $progress;
    }

    /**
     * Get ownership history
     */
    public function getOwnershipHistoryAttribute()
    {
        $history = collect();

        // Current landlord
        if ($this->landlord) {
            $history->push([
                'landlord_id' => $this->landlord_id,
                'landlord_name' => $this->landlord->name,
                'start_date' => $this->registration_date,
                'end_date' => null,
                'is_current' => true,
                'transfer_type' => 'initial_registration'
            ]);
        }

        // Add completed transfers
        foreach ($this->previousOwnershipTransfers as $transfer) {
            $history->push([
                'landlord_id' => $transfer->current_landlord_id,
                'landlord_name' => $transfer->currentLandlord->name ?? 'Previous Owner',
                'start_date' => $transfer->property->registration_date,
                'end_date' => $transfer->transfer_date,
                'is_current' => false,
                'transfer_type' => 'ownership_transfer',
                'transfer_id' => $transfer->id,
                'new_landlord_id' => $transfer->new_landlord_id,
                'new_landlord_name' => $transfer->newLandlord->name ?? $transfer->new_owner_name
            ]);
        }

        return $history->sortByDesc('start_date')->values();
    }

    // ==================== TENANT MANAGEMENT METHODS ====================

    /**
     * ✅ UPDATED: Check if property has tenants (User model)
     */
    public function hasTenants(): bool
    {
        return $this->tenants()->exists();
    }

    /**
     * ✅ NEW: Check if property has registration tenants (from registration process)
     */
    public function hasRegistrationTenants(): bool
    {
        return $this->registrationTenants()->exists();
    }

    /**
     * Check if property is rented
     */
    public function isRented(): bool
    {
        return $this->is_rented === true;
    }

    /**
     * ✅ UPDATED: Attach tenant (User) to property
     */
    public function attachTenant(User $tenant, $addedBy = null, $notes = null, $status = 'active'): void
    {
        // Ensure user is a tenant
        if ($tenant->type !== User::TYPE_TENANT) {
            throw new \Exception('User must be a tenant to attach to property');
        }

        $this->tenants()->attach($tenant->id, [
            'added_by' => $addedBy ?? auth()->id(),
            'added_at' => now(),
            'notes' => $notes,
            'status' => $status,
            'start_date' => $status === 'active' ? now() : null,
        ]);

        // Auto-mark property as rented if tenant is attached
        if (!$this->is_rented) {
            $this->update(['is_rented' => true]);
        }

        // Update tenant counts
        $this->updateTenantCounts();

        Log::info("Tenant {$tenant->id} attached to property {$this->id}", [
            'property_id' => $this->id,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'added_by' => $addedBy ?? auth()->id(),
            'status' => $status
        ]);
    }

    /**
     * ✅ UPDATED: Detach tenant (User) from property
     */
    public function detachTenant(User $tenant, $endDate = null): void
    {
        $this->tenants()->updateExistingPivot($tenant->id, [
            'status' => 'terminated',
            'end_date' => $endDate ?? now(),
        ]);

        // Update tenant counts
        $this->updateTenantCounts();

        // Check if property should still be marked as rented
        if ($this->active_tenants_count === 0) {
            $this->update(['is_rented' => false]);
        }

        Log::info("Tenant {$tenant->id} detached from property {$this->id}", [
            'property_id' => $this->id,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'end_date' => $endDate ?? now()
        ]);
    }

    /**
     * ✅ NEW: Update tenant counts
     */
    public function updateTenantCounts(): void
    {
        $this->update([
            'tenant_count' => $this->tenants()->count(),
            'active_tenant_count' => $this->activeTenants()->count(),
        ]);
    }

    /**
     * ✅ UPDATED: Get tenant statistics
     */
    public function getTenantStats(): array
    {
        return [
            'total_tenants' => $this->tenants_count,
            'active_tenants' => $this->active_tenants_count,
            'inactive_tenants' => $this->tenants_count - $this->active_tenants_count,
            'has_tenants' => $this->hasTenants(),
            'is_rented' => $this->is_rented,
            'vacancy_status' => $this->active_tenants_count > 0 ? 'occupied' : 'vacant',
            'registration_tenants' => [
                'total' => $this->registration_tenants_count,
                'approved' => $this->approved_registration_tenants_count,
                'pending' => $this->pending_registration_tenants_count,
            ],
        ];
    }

    /**
     * ✅ UPDATED: Get current tenant (first active tenant user)
     */
    public function getCurrentTenant(): ?User
    {
        return $this->activeTenants()->first();
    }

    /**
     * Check if property is vacant
     */
    public function isVacant(): bool
    {
        // Check property status first
        if ($this->status !== self::STATUS_VACANT) {
            // Property is not marked as vacant, but check if it has no active tenants
            return !$this->hasActiveTenants();
        }

        // Property is marked as vacant AND has no active tenants
        return !$this->hasActiveTenants();
    }

    /**
     * ✅ UPDATED: Check if property has active tenants
     */
    public function hasActiveTenants(): bool
    {
        return $this->activeTenants()->exists();
    }

    /**
     * ✅ UPDATED: Get tenant history (User model)
     */
    public function getTenantHistory()
    {
        return $this->tenants()
            ->withPivot('added_at', 'added_by', 'notes', 'status', 'start_date', 'end_date')
            ->orderBy('property_tenant.added_at', 'desc')
            ->get()
            ->map(function ($tenant) {
                return [
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                    ],
                    'added_at' => $tenant->pivot->added_at,
                    'added_by' => $tenant->pivot->added_by,
                    'notes' => $tenant->pivot->notes,
                    'status' => $tenant->pivot->status,
                    'start_date' => $tenant->pivot->start_date,
                    'end_date' => $tenant->pivot->end_date,
                ];
            });
    }

    /**
     * ✅ NEW: Get registration tenant history (from registration process)
     */
    public function getRegistrationTenantHistory()
    {
        return $this->registrationTenants()
            ->with(['approver', 'user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'notes' => $tenant->notes,
                    'status' => $tenant->status,
                    'status_label' => $tenant->status_label,
                    'status_badge_class' => $tenant->status_badge_class,
                    'approved_by' => $tenant->approver?->name,
                    'approved_at' => $tenant->approved_at?->format('Y-m-d H:i:s'),
                    'user_account' => $tenant->user ? [
                        'id' => $tenant->user->id,
                        'name' => $tenant->user->name,
                        'email' => $tenant->user->email,
                    ] : null,
                    'created_at' => $tenant->created_at->format('Y-m-d H:i:s'),
                ];
            });
    }

    /**
     * ✅ UPDATED: Get tenant invitations for this property
     */
    public function getTenantInvitations()
    {
        return $this->tenantInvitations()
            ->with(['tenant', 'invitedBy'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'tenant' => [
                        'id' => $invitation->tenant->id,
                        'name' => $invitation->tenant->name,
                        'phone' => $invitation->tenant->phone,
                        'email' => $invitation->tenant->email,
                    ],
                    'status' => $invitation->status,
                    'status_label' => $invitation->status_label,
                    'status_badge_class' => $invitation->status_badge_class,
                    'channels' => $invitation->channels,
                    'invitation_url' => $invitation->getInvitationUrl(),
                    'sent_at' => $invitation->sent_at?->format('Y-m-d H:i:s'),
                    'accepted_at' => $invitation->accepted_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
                    'is_expired' => $invitation->isExpired(),
                    'is_active' => $invitation->isActive(),
                    'invited_by' => $invitation->invitedBy?->name,
                ];
            });
    }

    /**
     * ✅ UPDATED: Check if tenant (User) is currently attached to property
     */
    public function hasTenant(User $tenant): bool
    {
        return $this->tenants()->where('user_id', $tenant->id)->exists();
    }

    /**
     * ✅ UPDATED: Check if tenant (User) is active on property
     */
    public function hasActiveTenant(User $tenant): bool
    {
        return $this->activeTenants()->where('user_id', $tenant->id)->exists();
    }

    /**
     * ✅ NEW: Check if registration tenant is attached to property
     */
    public function hasRegistrationTenant(RegistrationTenant $tenant): bool
    {
        return $this->registrationTenants()->where('id', $tenant->id)->exists();
    }

    /**
     * ✅ UPDATED: Add multiple tenants at once
     */
    public function attachTenants(array $tenants, $addedBy = null): array
    {
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                if ($tenant instanceof User) {
                    $this->attachTenant($tenant, $addedBy);
                    $results[] = [
                        'success' => true,
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'message' => 'Tenant attached successfully'
                    ];
                } elseif (is_array($tenant) && isset($tenant['id'])) {
                    $user = User::find($tenant['id']);
                    if ($user) {
                        $this->attachTenant($user, $addedBy, $tenant['notes'] ?? null, $tenant['status'] ?? 'active');
                        $results[] = [
                            'success' => true,
                            'tenant_id' => $user->id,
                            'tenant_name' => $user->name,
                            'message' => 'Tenant attached successfully'
                        ];
                    }
                }
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'tenant_id' => $tenant['id'] ?? 'unknown',
                    'tenant_name' => $tenant['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * ✅ NEW: Create tenant user from registration tenant
     */
    public function createTenantUserFromRegistration(RegistrationTenant $registrationTenant, $password = null): User
    {
        // Check if tenant already has a user account
        if ($registrationTenant->user_id) {
            throw new \Exception('This registration tenant already has a user account.');
        }

        // Create user account
        $user = User::create([
            'name' => $registrationTenant->name,
            'email' => $registrationTenant->email,
            'phone' => $registrationTenant->phone,
            'password' => $password ? Hash::make($password) : Hash::make(Str::random(16)),
            'type' => User::TYPE_TENANT,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $registrationTenant->email ? now() : null,
        ]);

        // Link registration tenant to user
        $registrationTenant->update([
            'user_id' => $user->id,
            'status' => 'approved'
        ]);

        // Attach user to property
        $this->attachTenant($user, auth()->id(), 'Created from registration tenant');

        Log::info("Tenant user created from registration tenant", [
            'registration_tenant_id' => $registrationTenant->id,
            'user_id' => $user->id,
            'property_id' => $this->id,
        ]);

        return $user;
    }

    /**
     * ✅ NEW: Bulk create tenant users from registration tenants
     */
    public function createTenantUsersFromRegistration(array $registrationTenantIds): array
    {
        $results = [];

        foreach ($registrationTenantIds as $id) {
            try {
                $registrationTenant = RegistrationTenant::find($id);
                if (!$registrationTenant) {
                    throw new \Exception("Registration tenant not found: {$id}");
                }

                if ($registrationTenant->property_id != $this->id) {
                    throw new \Exception("Registration tenant does not belong to this property");
                }

                $user = $this->createTenantUserFromRegistration($registrationTenant);

                $results[] = [
                    'success' => true,
                    'registration_tenant_id' => $id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'message' => 'Tenant user created successfully'
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'registration_tenant_id' => $id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    // ==================== CONSTRUCTION MANAGEMENT METHODS ====================

    /**
     * ✅ NEW: Mark property as under construction
     */
    public function markAsUnderConstruction(array $data = []): bool
    {
        $updateData = [
            'status' => self::STATUS_UNDER_CONSTRUCTION,
            'construction_status' => 'under_construction',
        ];

        if (isset($data['estimated_completion'])) {
            $updateData['estimated_completion'] = $data['estimated_completion'];
        }

        if (isset($data['bedrooms'])) {
            $updateData['bedrooms'] = $data['bedrooms'];
        }

        if (isset($data['bathrooms'])) {
            $updateData['bathrooms'] = $data['bathrooms'];
        }

        $result = $this->update($updateData);

        Log::info("Property {$this->id} marked as under construction", $data);

        return $result;
    }

    /**
     * ✅ NEW: Mark construction as completed
     */
    public function markConstructionCompleted(): bool
    {
        $result = $this->update([
            'construction_status' => 'completed',
            'status' => self::STATUS_VACANT, // Or STATUS_ACTIVE if rented immediately
        ]);

        Log::info("Property {$this->id} construction completed");

        return $result;
    }

    /**
     * ✅ NEW: Update construction progress
     */
    public function updateConstructionProgress(string $status, array $data = []): bool
    {
        $updateData = [
            'construction_status' => $status,
        ];

        if ($status === 'completed') {
            $updateData['status'] = self::STATUS_VACANT;
        }

        if ($status === 'on_hold') {
            // Keep status as under construction
        }

        if (isset($data['estimated_completion'])) {
            $updateData['estimated_completion'] = $data['estimated_completion'];
        }

        if (isset($data['bedrooms'])) {
            $updateData['bedrooms'] = $data['bedrooms'];
        }

        if (isset($data['bathrooms'])) {
            $updateData['bathrooms'] = $data['bathrooms'];
        }

        $result = $this->update($updateData);

        Log::info("Property {$this->id} construction progress updated to {$status}", $data);

        return $result;
    }

    // ==================== OTHER METHODS ====================

    /**
     * Check if property was registered by specific field agent
     */
    public function isRegisteredBy($agentId): bool
    {
        return $this->registered_by == $agentId;
    }

    /**
     * Check if property is assigned to specific field agent
     */
    public function isAssignedToAgent($agentId): bool
    {
        return $this->registrationPlan && $this->registrationPlan->planAssignments()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if field agent can access this property
     */
    public function canBeAccessedByAgent($agentId): bool
    {
        return $this->isRegisteredBy($agentId) || $this->isAssignedToAgent($agentId);
    }

    /**
     * Check if property has specific property type
     */
    public function hasPropertyType($typeSlug): bool
    {
        return $this->propertyType && $this->propertyType->slug === $typeSlug;
    }

    /**
     * Check if property is residential
     */
    public function isResidential(): bool
    {
        return $this->hasPropertyType('resident') ||
               $this->hasPropertyType('apartment') ||
               $this->hasPropertyType('house') ||
               $this->hasPropertyType('bungalow');
    }

    /**
     * Check if property is commercial
     */
    public function isCommercial(): bool
    {
        return $this->hasPropertyType('commercial') ||
               $this->hasPropertyType('shop') ||
               $this->hasPropertyType('office') ||
               $this->hasPropertyType('restaurant') ||
               $this->hasPropertyType('hotel') ||
               $this->hasPropertyType('mall');
    }

    /**
     * Check if property is institutional
     */
    public function isInstitutional(): bool
    {
        return $this->hasPropertyType('school') ||
               $this->hasPropertyType('hospital') ||
               $this->hasPropertyType('police-station') ||
               $this->hasPropertyType('government-building');
    }

    /**
     * Check if property is religious
     */
    public function isReligious(): bool
    {
        return $this->hasPropertyType('church') ||
               $this->hasPropertyType('mosque') ||
               $this->hasPropertyType('temple') ||
               $this->hasPropertyType('religious-building');
    }

    /**
     * Check if property is industrial
     */
    public function isIndustrial(): bool
    {
        return $this->hasPropertyType('factory') ||
               $this->hasPropertyType('warehouse') ||
               $this->hasPropertyType('industrial-building');
    }

    /**
     * Check if property is agricultural
     */
    public function isAgricultural(): bool
    {
        return $this->hasPropertyType('farm') ||
               $this->hasPropertyType('agricultural-land');
    }

    /**
     * Get property type category
     */
    public function getPropertyTypeCategory(): string
    {
        if ($this->isResidential()) return 'residential';
        if ($this->isCommercial()) return 'commercial';
        if ($this->isInstitutional()) return 'institutional';
        if ($this->isReligious()) return 'religious';
        if ($this->isIndustrial()) return 'industrial';
        if ($this->isAgricultural()) return 'agricultural';

        return 'other';
    }

    /**
     * Generate site allocation name based on registration plan naming pattern
     */
    public function generateSiteAllocationName(): string
    {
        if (!$this->registration_plan_id) {
            return 'Site Allocation-' . Str::random(6);
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            return 'Site Allocation-' . Str::random(6);
        }

        $pattern = $plan->naming_pattern;
        $currentName = $plan->next_available_name ?? $plan->starting_point;

        if (empty($pattern) || empty($currentName)) {
            return 'Site Allocation-' . Str::random(6);
        }

        try {
            $generatedName = $pattern;

            // Replace placeholders with actual values
            if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
                // Combined pattern like {letter}{number}
                if (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
                    $letter = $matches[1];
                    $number = $matches[2];
                    $generatedName = str_replace('{letter}', $letter, $generatedName);
                    $generatedName = str_replace('{number}', $number, $generatedName);
                }
            } elseif (strpos($pattern, '{letter}') !== false) {
                // Letter-only pattern
                $generatedName = str_replace('{letter}', $currentName, $generatedName);
            } elseif (strpos($pattern, '{number}') !== false) {
                // Number-only pattern
                $generatedName = str_replace('{number}', $currentName, $generatedName);
            }

            return $generatedName;
        } catch (\Exception $e) {
            Log::error('Error generating site allocation name: ' . $e->getMessage());
            return 'Site Allocation-' . Str::random(6);
        }
    }

    /**
     * Generate registration pattern based on naming pattern
     */
    public function generateRegistrationPattern(): string
    {
        if (!$this->registration_plan_id) {
            return 'SA-' . Str::random(6);
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            return 'SA-' . Str::random(6);
        }

        $pattern = $plan->naming_pattern;
        $currentName = $plan->next_available_name ?? $plan->starting_point;

        if (empty($pattern) || empty($currentName)) {
            return 'SA-' . Str::random(6);
        }

        try {
            $generatedPattern = $pattern;

            // Replace placeholders with actual values
            if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
                // Combined pattern like {letter}{number}
                if (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
                    $letter = $matches[1];
                    $number = $matches[2];
                    $generatedPattern = str_replace('{letter}', $letter, $generatedPattern);
                    $generatedPattern = str_replace('{number}', $number, $generatedPattern);
                }
            } elseif (strpos($pattern, '{letter}') !== false) {
                // Letter-only pattern
                $generatedPattern = str_replace('{letter}', $currentName, $generatedPattern);
            } elseif (strpos($pattern, '{number}') !== false) {
                // Number-only pattern
                $generatedPattern = str_replace('{number}', $currentName, $generatedPattern);
            }

            return $generatedPattern;
        } catch (\Exception $e) {
            Log::error('Error generating registration pattern: ' . $e->getMessage());
            return 'SA-' . Str::random(6);
        }
    }

    /**
     * Get this property's position in the global sequence
     */
    public function getSequencePosition(): int
    {
        if (!$this->registration_plan_id) {
            return 0;
        }

        return Property::where('registration_plan_id', $this->registration_plan_id)
            ->where('id', '<=', $this->id)
            ->orderBy('id')
            ->count();
    }

    /**
     * Get next sequence position
     */
    public function getNextSequencePosition($planId = null): int
    {
        $planId = $planId ?? $this->registration_plan_id;
        if (!$planId) {
            return 1;
        }

        $maxPosition = Property::where('registration_plan_id', $planId)
            ->max('sequence_position');

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Get previous sequence position
     */
    public function getPrevSequencePosition(): ?int
    {
        if (!$this->sequence_position || $this->sequence_position <= 1) {
            return null;
        }

        return $this->sequence_position - 1;
    }

    /**
     * Generate unique site allocation code
     */
    public function generateSiteAllocationCode(): string
    {
        $prefix = strtoupper(substr($this->zone ?: 'GEN', 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(4));

        return "SITE-{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Get the count of properties for this landlord
     */
    public function getSiteAllocationCount(): int
    {
        return self::where('landlord_id', $this->landlord_id)->count();
    }

    /**
     * Get property count for a specific landlord or overall
     */
    public static function getPropertyCount($landlordId = null): int
    {
        if ($landlordId) {
            return self::where('landlord_id', $landlordId)->count();
        }

        return self::count();
    }

    /**
     * Get property count by status
     */
    public static function getPropertyCountByStatus($status, $landlordId = null): int
    {
        $query = self::where('status', $status);

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * Get property count by property type
     */
    public static function getPropertyCountByPropertyType($typeSlug, $landlordId = null): int
    {
        $query = self::whereHas('propertyType', function($q) use ($typeSlug) {
            $q->where('slug', $typeSlug);
        });

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * Get property count by property type ID
     */
    public static function getPropertyCountByPropertyTypeId($typeId, $landlordId = null): int
    {
        $query = self::where('property_type_id', $typeId);

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * Get rented properties count
     */
    public static function getRentedPropertyCount($landlordId = null): int
    {
        $query = self::rented();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * Get field agent registered properties count
     */
    public static function getFieldAgentRegisteredCount($agentId = null): int
    {
        $query = self::fieldAgentRegistered();

        if ($agentId) {
            $query->where('registered_by', $agentId);
        }

        return $query->count();
    }

    /**
     * Get global sequence properties count
     */
    public static function getGlobalSequencePropertyCount($landlordId = null): int
    {
        $query = self::globalSequence();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * ✅ NEW: Get properties under construction count
     */
    public static function getUnderConstructionCount($landlordId = null): int
    {
        $query = self::underConstruction();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * ✅ UPDATED: Get properties with tenants count
     */
    public static function getPropertiesWithTenantsCount($landlordId = null): int
    {
        $query = self::withTenants();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * ✅ NEW: Get properties with registration tenants count
     */
    public static function getPropertiesWithRegistrationTenantsCount($landlordId = null): int
    {
        $query = self::withRegistrationTenants();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * ✅ NEW: Get properties with photos count
     */
    public static function getPropertiesWithPhotosCount($landlordId = null): int
    {
        $query = self::withPhotos();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->count();
    }

    /**
     * Get property count by registration plan
     */
    public static function getPropertyCountByRegistrationPlan($planId): int
    {
        return self::where('registration_plan_id', $planId)->count();
    }

    /**
     * Get property count by zone
     */
    public static function getPropertyCountByZone($zone): int
    {
        return self::where('zone', $zone)->count();
    }

    /**
     * Get property count by section
     */
    public static function getPropertyCountBySection($section): int
    {
        return self::where('section', $section)->count();
    }

    /**
     * Update registration plan progress and next available name
     */
    public function updateRegistrationPlanProgress(): void
    {
        if ($this->registration_plan_id) {
            $plan = $this->registrationPlan;
            if ($plan) {
                $this->updatePlanProgress($plan);
            }
        }
    }

    /**
     * Update specific plan progress
     */
    public function updatePlanProgress(RegistrationPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            try {
                $registeredSites = self::where('registration_plan_id', $plan->id)->count();
                $plan->update(['houses_registered' => $registeredSites]);

                // Auto-complete plan if all estimated site allocations are registered
                if ($registeredSites >= $plan->estimated_houses && $plan->status !== 'completed') {
                    $plan->markAsCompleted();

                    // Log global sequence completion
                    if ($plan->is_global_sequence) {
                        Log::info("Global sequence plan {$plan->id} completed with {$registeredSites} properties");
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error updating plan progress: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Update next available name for the current registration plan
     */
    public function updateNextAvailableName(): void
    {
        if ($this->registration_plan_id) {
            $plan = $this->registrationPlan;
            if ($plan) {
                $this->updateNextAvailableNameForPlan($plan);
            }
        }
    }

    /**
     * Update next available name for specific plan
     */
    public function updateNextAvailableNameForPlan(RegistrationPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            try {
                $currentName = $plan->next_available_name ?? $plan->starting_point;
                $pattern = $plan->naming_pattern;
                $sequenceType = $plan->sequence_type;

                // Generate next name based on pattern and sequence type
                $nextName = $this->generateNextName($currentName, $pattern, $sequenceType);

                // Ensure the next name is unique
                $nextName = $this->ensureUniqueNextName($plan, $nextName, $pattern, $sequenceType);

                $plan->update(['next_available_name' => $nextName]);

                // Log global sequence progression
                if ($plan->is_global_sequence) {
                    Log::info("Global sequence plan {$plan->id} progressed from {$currentName} to {$nextName}");
                }
            } catch (\Exception $e) {
                Log::error('Error updating next available name: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Ensure the next available name is unique
     */
    private function ensureUniqueNextName(RegistrationPlan $plan, $proposedName, $pattern, $sequenceType, $maxAttempts = 100): string
    {
        $attempt = 0;
        $currentName = $proposedName;

        while ($attempt < $maxAttempts) {
            // Check if this name already exists as a registration pattern
            $existingProperty = self::where('registration_plan_id', $plan->id)
                ->where('registration_pattern', $currentName)
                ->exists();

            if (!$existingProperty) {
                return $currentName;
            }

            // Generate next name in sequence
            $currentName = $this->generateNextName($currentName, $pattern, $sequenceType);
            $attempt++;
        }

        throw new \Exception("Unable to generate a unique next available name after {$maxAttempts} attempts");
    }

    /**
     * Get the next available name for the registration plan
     */
    public function getNextAvailableName(): string
    {
        if (!$this->registration_plan_id) {
            return '';
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            return '';
        }

        $currentName = $plan->next_available_name ?? $plan->starting_point;
        $pattern = $plan->naming_pattern;
        $sequenceType = $plan->sequence_type;

        return $this->generateNextName($currentName, $pattern, $sequenceType);
    }

    /**
     * Generate the next name in sequence
     */
    private function generateNextName($currentName, $pattern, $sequenceType): string
    {
        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $this->generateCombinedNextName($currentName, $sequenceType);
        } elseif (strpos($pattern, '{letter}') !== false) {
            return $this->generateLetterNextName($currentName);
        } elseif (strpos($pattern, '{number}') !== false) {
            return $this->generateNumberNextName($currentName, $sequenceType);
        }

        return $currentName . '-1';
    }

    /**
     * Generate next name for combined letter-number patterns
     */
    private function generateCombinedNextName($currentName, $sequenceType): string
    {
        if (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
            $letter = $matches[1];
            $number = (int)$matches[2];

            // Handle sequence types for numbers
            switch ($sequenceType) {
                case 'even_only':
                    $number = ($number % 2 === 0) ? $number + 2 : $number + 1;
                    break;
                case 'odd_only':
                    $number = ($number % 2 === 1) ? $number + 2 : $number + 1;
                    break;
                default: // sequential
                    $number += 1;
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
     * Generate next name for number-only patterns
     */
    private function generateNumberNextName($currentName, $sequenceType): string
    {
        $number = (int)$currentName;

        switch ($sequenceType) {
            case 'even_only':
                return ($number % 2 === 0) ? $number + 2 : $number + 1;
            case 'odd_only':
                return ($number % 2 === 1) ? $number + 2 : $number + 1;
            default: // sequential
                return $number + 1;
        }
    }

    /**
     * Increment letters (A->B, Z->AA, etc.)
     */
    private function incrementLetters($letters): string
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
    private function generateLetterNextName($currentName): string
    {
        return $this->incrementLetters($currentName);
    }

    /**
     * Generate next name for simple string patterns
     */
    private function generateSimpleNextName($currentName): string
    {
        if (preg_match('/(.*?)(\d+)$/', $currentName, $matches)) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            return $prefix . ($number + 1);
        }

        return $currentName . '-1';
    }

    /**
     * Check if site allocation is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if site allocation is under maintenance
     */
    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_UNDER_MAINTENANCE;
    }

    /**
     * Check if property was registered by field agent
     */
    public function isFieldAgentRegistered(): bool
    {
        return $this->is_field_agent_registered === true;
    }

    /**
     * Get all status options
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            self::STATUS_VACANT => 'Vacant',
            self::STATUS_UNDER_CONSTRUCTION => 'Under Construction',
        ];
    }

    /**
     * Mark site allocation as inspected
     */
    public function markAsInspected(): void
    {
        $this->update(['last_inspection_date' => now()]);
    }

    /**
     * Get the age of the site allocation in months
     */
    public function getAgeInMonths(): int
    {
        return $this->registration_date ? $this->registration_date->diffInMonths(now()) : 0;
    }

    /**
     * Get the next inspection due date
     */
    public function getNextInspectionDueDate(): ?string
    {
        if (!$this->last_inspection_date) {
            return null;
        }
        return $this->last_inspection_date->addMonths(6)->format('Y-m-d');
    }

    /**
     * Check if inspection is overdue
     */
    public function isInspectionOverdue($months = 6): bool
    {
        if (!$this->last_inspection_date) {
            return true;
        }
        return $this->last_inspection_date->addMonths($months)->isPast();
    }

    /**
     * Get property type statistics for dashboard
     */
    public static function getPropertyTypeStatistics(): array
    {
        $propertyTypes = PropertyType::active()->withCount('properties')->get();

        $stats = [];
        $totalProperties = self::count();

        foreach ($propertyTypes as $type) {
            $stats[$type->slug] = [
                'id' => $type->id,
                'name' => $type->name,
                'icon' => $type->icon,
                'color' => $type->color,
                'description' => $type->description,
                'count' => $type->properties_count,
                'percentage' => $totalProperties > 0 ? round(($type->properties_count / $totalProperties) * 100, 2) : 0
            ];
        }

        // Add custom types count
        $customCount = self::customType()->count();
        $stats['custom'] = [
            'id' => 'custom',
            'name' => 'Custom Types',
            'icon' => 'fas fa-edit',
            'color' => '#6b7280',
            'description' => 'User-defined property types',
            'count' => $customCount,
            'percentage' => $totalProperties > 0 ? round(($customCount / $totalProperties) * 100, 2) : 0
        ];

        return $stats;
    }

    /**
     * Get property type statistics by category
     */
    public static function getPropertyTypeStatisticsByCategory(): array
    {
        $categories = [
            'residential' => [
                'name' => 'Residential',
                'icon' => '🏠',
                'color' => '#3b82f6',
                'types' => ['resident', 'apartment', 'house', 'bungalow']
            ],
            'commercial' => [
                'name' => 'Commercial',
                'icon' => '🏢',
                'color' => '#10b981',
                'types' => ['commercial', 'shop', 'office', 'restaurant', 'hotel', 'mall']
            ],
            'institutional' => [
                'name' => 'Institutional',
                'icon' => '🏛️',
                'color' => '#f59e0b',
                'types' => ['school', 'hospital', 'police-station', 'government-building']
            ],
            'religious' => [
                'name' => 'Religious',
                'icon' => '⛪',
                'color' => '#8b5cf6',
                'types' => ['church', 'mosque', 'temple', 'religious-building']
            ],
            'industrial' => [
                'name' => 'Industrial',
                'icon' => '🏭',
                'color' => '#ef4444',
                'types' => ['factory', 'warehouse', 'industrial-building']
            ],
            'agricultural' => [
                'name' => 'Agricultural',
                'icon' => '🚜',
                'color' => '#84cc16',
                'types' => ['farm', 'agricultural-land']
            ]
        ];

        $stats = [];
        $totalProperties = self::count();

        foreach ($categories as $categorySlug => $category) {
            $count = self::whereHas('propertyType', function($q) use ($category) {
                $q->whereIn('slug', $category['types']);
            })->count();

            $stats[$categorySlug] = [
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'count' => $count,
                'percentage' => $totalProperties > 0 ? round(($count / $totalProperties) * 100, 2) : 0
            ];
        }

        // Add custom category
        $customCount = self::customType()->count();
        $stats['custom'] = [
            'name' => 'Custom',
            'icon' => '🔧',
            'color' => '#6b7280',
            'count' => $customCount,
            'percentage' => $totalProperties > 0 ? round(($customCount / $totalProperties) * 100, 2) : 0
        ];

        // Add under construction count
        $underConstructionCount = self::underConstruction()->count();
        $stats['under_construction'] = [
            'name' => 'Under Construction',
            'icon' => '🏗️',
            'color' => '#f59e0b',
            'count' => $underConstructionCount,
            'percentage' => $totalProperties > 0 ? round(($underConstructionCount / $totalProperties) * 100, 2) : 0
        ];

        return $stats;
    }

    /**
     * Get site allocations statistics for dashboard
     */
    public static function getDashboardStatistics(): array
    {
        return [
            'total_site_allocations' => self::count(),
            'active_site_allocations' => self::active()->count(),
            'vacant_site_allocations' => self::vacant()->count(),
            'under_maintenance' => self::underMaintenance()->count(),
            'under_construction' => self::underConstruction()->count(),
            'site_allocations_with_digital_address' => self::hasDigitalAddress()->count(),
            'site_allocations_without_digital_address' => self::missingDigitalAddress()->count(),
            'global_sequence_allocations' => self::globalSequence()->count(),
            'rented_properties' => self::rented()->count(),
            'field_agent_registered' => self::fieldAgentRegistered()->count(),
            'recently_registered' => self::where('created_at', '>=', now()->subDays(30))->count(),
            'properties_with_tenants' => self::withTenants()->count(),
            'properties_without_tenants' => self::withoutTenants()->count(),
            'properties_with_active_tenants' => self::withActiveTenants()->count(),
            'properties_with_registration_tenants' => self::withRegistrationTenants()->count(),
            // ✅ NEW: Coordinate coverage
            'properties_with_coordinates' => self::hasCoordinates()->count(),
            'properties_missing_coordinates' => self::missingCoordinates()->count(),
            // ✅ NEW: Photo statistics
            'properties_with_photos' => self::withPhotos()->count(),
            'properties_without_photos' => self::withoutPhotos()->count(),
            'properties_with_primary_photo' => self::withPrimaryPhoto()->count(),
            'property_type_stats' => self::getPropertyTypeStatistics(),
            'property_type_category_stats' => self::getPropertyTypeStatisticsByCategory(),
        ];
    }

    /**
     * Check if site allocation belongs to specific registration plan
     */
    public function belongsToPlan($planId): bool
    {
        return $this->registration_plan_id == $planId;
    }

    /**
     * Get site allocations by multiple criteria
     */
    public static function search($criteria)
    {
        $query = self::query();

        if (isset($criteria['property_name'])) {
            $query->byPropertyName($criteria['property_name']);
        }

        if (isset($criteria['registration_pattern'])) {
            $query->byRegistrationPattern($criteria['registration_pattern']);
        }

        if (isset($criteria['street_name'])) {
            $query->byStreet($criteria['street_name']);
        }

        if (isset($criteria['zone'])) {
            $query->byZone($criteria['zone']);
        }

        if (isset($criteria['landlord_id'])) {
            $query->byLandlord($criteria['landlord_id']);
        }

        if (isset($criteria['registration_plan_id'])) {
            $query->byRegistrationPlan($criteria['registration_plan_id']);
        }

        // Property type filtering
        if (isset($criteria['property_type'])) {
            $query->byPropertyType($criteria['property_type']);
        }

        if (isset($criteria['property_type_id'])) {
            $query->byPropertyTypeId($criteria['property_type_id']);
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        // Rental status filtering
        if (isset($criteria['is_rented'])) {
            $query->where('is_rented', $criteria['is_rented']);
        }

        // Field agent registration filtering
        if (isset($criteria['is_field_agent_registered'])) {
            $query->where('is_field_agent_registered', $criteria['is_field_agent_registered']);
        }

        // Global sequence filtering
        if (isset($criteria['is_global_sequence'])) {
            $query->where('is_global_sequence', $criteria['is_global_sequence']);
        }

        // Sequence position filtering
        if (isset($criteria['sequence_position'])) {
            $query->where('sequence_position', $criteria['sequence_position']);
        }

        // Registered by filtering
        if (isset($criteria['registered_by'])) {
            $query->where('registered_by', $criteria['registered_by']);
        }

        // ✅ UPDATED: Tenant-related filtering
        if (isset($criteria['has_tenants'])) {
            if ($criteria['has_tenants'] === '1') {
                $query->withTenants();
            } elseif ($criteria['has_tenants'] === '0') {
                $query->withoutTenants();
            }
        }

        // ✅ NEW: Coordinate filtering
        if (isset($criteria['has_coordinates'])) {
            if ($criteria['has_coordinates'] === '1') {
                $query->hasCoordinates();
            } elseif ($criteria['has_coordinates'] === '0') {
                $query->missingCoordinates();
            }
        }

        // ✅ NEW: Photo-related filtering
        if (isset($criteria['has_photos'])) {
            if ($criteria['has_photos'] === '1') {
                $query->withPhotos();
            } elseif ($criteria['has_photos'] === '0') {
                $query->withoutPhotos();
            }
        }

        // ✅ NEW: Construction status filtering
        if (isset($criteria['under_construction'])) {
            if ($criteria['under_construction'] === '1') {
                $query->underConstruction();
            }
        }

        // ✅ NEW: Registration tenant filtering
        if (isset($criteria['has_registration_tenants'])) {
            if ($criteria['has_registration_tenants'] === '1') {
                $query->withRegistrationTenants();
            } elseif ($criteria['has_registration_tenants'] === '0') {
                $query->whereDoesntHave('registrationTenants');
            }
        }

        if (isset($criteria['has_digital_address'])) {
            if ($criteria['has_digital_address'] === '1') {
                $query->hasDigitalAddress();
            } elseif ($criteria['has_digital_address'] === '0') {
                $query->missingDigitalAddress();
            }
        }

        if (isset($criteria['global_sequence'])) {
            if ($criteria['global_sequence'] === '1') {
                $query->globalSequence();
            }
        }

        // Property type category filtering
        if (isset($criteria['property_category'])) {
            switch ($criteria['property_category']) {
                case 'residential':
                    $query->residential();
                    break;
                case 'commercial':
                    $query->commercial();
                    break;
                case 'institutional':
                    $query->institutional();
                    break;
                case 'religious':
                    $query->religious();
                    break;
                case 'industrial':
                    $query->industrial();
                    break;
                case 'agricultural':
                    $query->agricultural();
                    break;
                case 'custom':
                    $query->customType();
                    break;
            }
        }

        return $query->get();
    }

    /**
     * Get site allocation summary for landlord
     */
    public function getSiteAllocationSummary(): array
    {
        $summary = [
            'site_allocation_name' => $this->property_name,
            'registration_pattern' => $this->registration_pattern,
            'owner' => $this->landlord ? $this->landlord->name : 'Not assigned',
            'location' => $this->physical_address,
            'status' => $this->status,
            'status_badge' => $this->site_status_badge,
            'registration_date' => $this->registration_date?->format('M d, Y'),
            'last_inspection' => $this->last_inspection_date?->format('M d, Y'),
            'has_digital_address' => $this->has_digital_address,
            'digital_address' => $this->digital_address,
            // ✅ NEW: coordinates
            'has_coordinates' => $this->has_coordinates,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'total_tenants' => $this->tenants_count,
            'active_tenants' => $this->active_tenants_count,
            'zone' => $this->zone,
            'section' => $this->section,
            'registration_plan' => $this->registrationPlan ? $this->registrationPlan->name : 'Not assigned',
            'registered_by' => $this->registeredBy ? $this->registeredBy->name : 'System',
            'property_type' => $this->display_property_type,
            'property_type_icon' => $this->property_type_icon,
            'property_type_color' => $this->property_type_color,
            'property_type_category' => $this->getPropertyTypeCategory(),
            'is_custom_type' => $this->is_custom_type,
            'is_rented' => $this->is_rented,
            'rental_status_badge' => $this->rental_status_badge,
            'is_field_agent_registered' => $this->is_field_agent_registered,
            'field_agent_info' => $this->field_agent_info,
            'is_global_sequence' => $this->is_global_sequence,
            'global_sequence_info' => $this->global_sequence_info,
            'sequence_position' => $this->sequence_position,
            'registration_tenants_count' => $this->registration_tenants_count,
            'approved_registration_tenants' => $this->approved_registration_tenants_count,
            // ✅ NEW: Photo info
            'has_photos' => $this->has_photos,
            'photos_count' => $this->photos_count,
            'primary_photo_url' => $this->primary_photo_url,
            'primary_photo_thumbnail_url' => $this->primary_photo_thumbnail_url,
            // ✅ NEW: Construction info
            'construction_progress' => $this->construction_progress,
            'is_under_construction' => $this->is_under_construction,
            'construction_status' => $this->construction_status,
            'formatted_construction_status' => $this->formatted_construction_status,
            'construction_status_badge' => $this->construction_status_badge,
            'estimated_completion' => $this->estimated_completion?->format('M d, Y'),
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
        ];

        // Add field agent info if applicable
        if ($this->is_field_agent_registered && $this->fieldAgent) {
            $summary['field_agent'] = [
                'name' => $this->fieldAgent->name,
                'phone' => $this->fieldAgent->phone,
                'registered_at' => $this->field_agent_registered_at?->format('M d, Y H:i'),
            ];
        }

        // Add tenant stats if applicable
        if ($this->hasTenants()) {
            $summary['tenant_stats'] = $this->getTenantStats();
            $summary['current_tenant'] = $this->getCurrentTenantInfo();
        }

        // Add registration tenant info if applicable
        if ($this->hasRegistrationTenants()) {
            $summary['registration_tenant_stats'] = [
                'total' => $this->registration_tenants_count,
                'approved' => $this->approved_registration_tenants_count,
                'pending' => $this->pending_registration_tenants_count,
            ];
        }

        // Add global sequence info if applicable
        if ($this->is_global_sequence) {
            $summary['global_sequence'] = true;
            $summary['sequence_position'] = $this->sequence_position;
            $summary['is_continuation'] = $this->registrationPlan ? $this->registrationPlan->continues_from_plan_id !== null : false;
        }

        return $summary;
    }

    /**
     * Get pending maintenance requests count
     */
    public function getPendingMaintenanceCount(): int
    {
        return $this->maintenanceRequests()->where('status', 'pending')->count();
    }

    /**
     * Get recent activity for site allocation
     */
    public function getRecentActivity($limit = 5): array
    {
        $activities = [];

        // Add recent tenants
        $recentTenants = $this->tenants()->latest()->take($limit)->get();
        foreach ($recentTenants as $tenant) {
            $activities[] = [
                'type' => 'tenant',
                'message' => "New tenant registered: {$tenant->name}",
                'date' => $tenant->pivot->added_at ?? $tenant->created_at,
                'icon' => 'fas fa-user-plus'
            ];
        }

        // Add recent registration tenants
        $recentRegistrationTenants = $this->registrationTenants()->latest()->take($limit)->get();
        foreach ($recentRegistrationTenants as $tenant) {
            $activities[] = [
                'type' => 'registration_tenant',
                'message' => "New registration tenant: {$tenant->name}",
                'date' => $tenant->created_at,
                'icon' => 'fas fa-user-check'
            ];
        }

        // Add recent maintenance requests
        $recentMaintenance = $this->maintenanceRequests()->latest()->take($limit)->get();
        foreach ($recentMaintenance as $maintenance) {
            $activities[] = [
                'type' => 'maintenance',
                'message' => "Maintenance request: {$maintenance->title}",
                'date' => $maintenance->created_at,
                'icon' => 'fas fa-tools'
            ];
        }

        // Add recent tenant invitations
        $recentInvitations = $this->tenantInvitations()->latest()->take($limit)->get();
        foreach ($recentInvitations as $invitation) {
            $activities[] = [
                'type' => 'invitation',
                'message' => "Tenant invitation sent via " . implode(', ', $invitation->channels),
                'date' => $invitation->created_at,
                'icon' => 'fas fa-envelope'
            ];
        }

        // Add recent landlord invitations
        $recentLandlordInvitations = $this->landlordInvitations()->latest()->take($limit)->get();
        foreach ($recentLandlordInvitations as $invitation) {
            $activities[] = [
                'type' => 'landlord_invitation',
                'message' => "Landlord invitation sent via " . implode(', ', $invitation->channels),
                'date' => $invitation->created_at,
                'icon' => 'fas fa-user-tie'
            ];
        }

        // Sort by date and return limited results
        usort($activities, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Validate if the property can be created with the given registration plan
     */
    public function validateWithRegistrationPlan(): array
    {
        $errors = [];

        if (!$this->registration_plan_id) {
            $errors[] = 'Registration plan is required';
            return $errors;
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            $errors[] = 'Selected registration plan not found';
            return $errors;
        }

        // Check if plan is active
        if ($plan->status === 'completed') {
            $errors[] = 'Cannot create site allocation for completed registration plan';
        }

        // Check if plan is cancelled
        if ($plan->status === 'cancelled') {
            $errors[] = 'Cannot create site allocation for cancelled registration plan';
        }

        // Validate zone and section if provided
        if ($this->zone && $plan->zone && $this->zone !== $plan->zone) {
            $errors[] = 'Zone does not match the selected registration plan';
        }

        if ($this->section && $plan->section && $this->section !== $plan->section) {
            $errors[] = 'Section does not match the selected registration plan';
        }

        return $errors;
    }

    /**
     * Get formatted registration information
     */
    public function getRegistrationInfo(): array
    {
        $info = [
            'plan_name' => $this->registrationPlan ? $this->registrationPlan->name : 'N/A',
            'pattern' => $this->registration_pattern,
            'zone' => $this->zone,
            'section' => $this->section,
            'registration_date' => $this->registration_date?->format('F j, Y'),
            'registered_by' => $this->registeredBy ? $this->registeredBy->name : 'System',
            'property_type' => $this->display_property_type,
            'property_type_icon' => $this->property_type_icon,
            'property_type_color' => $this->property_type_color,
            'property_type_category' => $this->getPropertyTypeCategory(),
            'is_rented' => $this->is_rented,
            'rental_status' => $this->rental_status_badge,
            'is_field_agent_registered' => $this->is_field_agent_registered,
            'field_agent_info' => $this->field_agent_info,
            'is_global_sequence' => $this->is_global_sequence,
            'global_sequence_info' => $this->global_sequence_info,
            'sequence_position' => $this->sequence_position,
            'registration_tenants_count' => $this->registration_tenants_count,
            'approved_registration_tenants' => $this->approved_registration_tenants_count,
            // ✅ NEW: Coordinate info
            'has_coordinates' => $this->has_coordinates,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            // ✅ NEW: Photo info
            'has_photos' => $this->has_photos,
            'photos_count' => $this->photos_count,
            'primary_photo_url' => $this->primary_photo_url,
            // ✅ NEW: Construction info
            'construction_progress' => $this->construction_progress,
            'is_under_construction' => $this->is_under_construction,
            'construction_status' => $this->construction_status,
            'formatted_construction_status' => $this->formatted_construction_status,
            'estimated_completion' => $this->estimated_completion?->format('M d, Y'),
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
        ];

        // Add tenant info if applicable
        if ($this->hasTenants()) {
            $info['tenant_stats'] = $this->getTenantStats();
            $info['current_tenant'] = $this->getCurrentTenantInfo();
        }

        // Add global sequence info if applicable
        if ($this->is_global_sequence) {
            $info['global_sequence'] = true;
            $info['sequence_chain'] = $this->getGlobalSequenceChain();
        }

        return $info;
    }

    /**
     * Get global sequence chain information
     */
    public function getGlobalSequenceChain(): array
    {
        if (!$this->is_global_sequence) {
            return [];
        }

        $chain = [];
        $currentPlan = $this->registrationPlan;

        while ($currentPlan) {
            $chain[] = [
                'id' => $currentPlan->id,
                'zone' => $currentPlan->zone,
                'section' => $currentPlan->section,
                'naming_pattern' => $currentPlan->naming_pattern,
                'starting_point' => $currentPlan->starting_point,
                'next_available_name' => $currentPlan->next_available_name,
                'is_global_sequence' => $currentPlan->is_global_sequence,
            ];

            $currentPlan = $currentPlan->continuedFromPlan;
        }

        return array_reverse($chain); // Return in chronological order
    }

    /**
     * Check if the property needs zone/section update from registration plan
     */
    public function needsLocationUpdate(): bool
    {
        if (!$this->registration_plan_id) {
            return false;
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            return false;
        }

        return ($this->zone !== $plan->zone) || ($this->section !== $plan->section);
    }

    /**
     * Update location from registration plan
     */
    public function updateLocationFromPlan(): bool
    {
        if (!$this->registration_plan_id) {
            return false;
        }

        $plan = $this->registrationPlan;
        if (!$plan) {
            return false;
        }

        $this->zone = $plan->zone;
        $this->section = $plan->section;

        return $this->save();
    }

    /**
     * Get comprehensive property statistics
     */
    public static function getPropertyStatistics($landlordId = null): array
    {
        $query = $landlordId ? self::where('landlord_id', $landlordId) : self::query();

        return [
            'total' => $query->count(),
            'active' => $query->clone()->active()->count(),
            'vacant' => $query->clone()->vacant()->count(),
            'under_maintenance' => $query->clone()->underMaintenance()->count(),
            'under_construction' => $query->clone()->underConstruction()->count(),
            'inactive' => $query->clone()->where('status', self::STATUS_INACTIVE)->count(),
            'with_digital_address' => $query->clone()->hasDigitalAddress()->count(),
            'without_digital_address' => $query->clone()->missingDigitalAddress()->count(),
            // ✅ NEW: coordinate counts
            'with_coordinates' => $query->clone()->hasCoordinates()->count(),
            'without_coordinates' => $query->clone()->missingCoordinates()->count(),
            'due_for_inspection' => $query->clone()->dueForInspection()->count(),
            'global_sequence' => $query->clone()->globalSequence()->count(),
            'rented' => $query->clone()->rented()->count(),
            'field_agent_registered' => $query->clone()->fieldAgentRegistered()->count(),
            'with_tenants' => $query->clone()->withTenants()->count(),
            'with_active_tenants' => $query->clone()->withActiveTenants()->count(),
            'with_registration_tenants' => $query->clone()->withRegistrationTenants()->count(),
            // ✅ NEW: Photo statistics
            'with_photos' => $query->clone()->withPhotos()->count(),
            'without_photos' => $query->clone()->withoutPhotos()->count(),
            'by_property_type' => self::getPropertyTypeStatisticsByLandlord($landlordId),
            'by_property_category' => self::getPropertyCategoryStatisticsByLandlord($landlordId),
        ];
    }

    /**
     * Get property type statistics by landlord
     */
    public static function getPropertyTypeStatisticsByLandlord($landlordId = null): array
    {
        $query = $landlordId ? self::where('landlord_id', $landlordId) : self::query();

        $propertyTypes = PropertyType::active()->get();
        $stats = [];

        foreach ($propertyTypes as $type) {
            $count = $query->clone()->where('property_type_id', $type->id)->count();
            if ($count > 0) {
                $stats[$type->slug] = [
                    'name' => $type->name,
                    'icon' => $type->icon,
                    'color' => $type->color,
                    'count' => $count
                ];
            }
        }

        // Add custom types
        $customCount = $query->clone()->customType()->count();
        if ($customCount > 0) {
            $stats['custom'] = [
                'name' => 'Custom Types',
                'icon' => 'fas fa-edit',
                'color' => '#6b7280',
                'count' => $customCount
            ];
        }

        return $stats;
    }

    /**
     * Get property category statistics by landlord
     */
    public static function getPropertyCategoryStatisticsByLandlord($landlordId = null): array
    {
        $query = $landlordId ? self::where('landlord_id', $landlordId) : self::query();

        $categories = [
            'residential' => $query->clone()->residential()->count(),
            'commercial' => $query->clone()->commercial()->count(),
            'institutional' => $query->clone()->institutional()->count(),
            'religious' => $query->clone()->religious()->count(),
            'industrial' => $query->clone()->industrial()->count(),
            'agricultural' => $query->clone()->agricultural()->count(),
            'custom' => $query->clone()->customType()->count(),
        ];

        return $categories;
    }

    /**
     * Get properties grouped by zone
     */
    public static function getPropertiesByZone(): array
    {
        return self::select('zone', \DB::raw('count(*) as count'))
            ->groupBy('zone')
            ->get()
            ->pluck('count', 'zone')
            ->toArray();
    }

    /**
     * Get properties grouped by status
     */
    public static function getPropertiesByStatus(): array
    {
        return self::select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get properties grouped by property type
     */
    public static function getPropertiesByPropertyType(): array
    {
        return self::with('propertyType')
            ->select('property_type_id', \DB::raw('count(*) as count'))
            ->groupBy('property_type_id')
            ->get()
            ->mapWithKeys(function($item) {
                $typeName = $item->propertyType ? $item->propertyType->name : 'Unknown';
                return [$typeName => $item->count];
            })
            ->toArray();
    }

    /**
     * Get properties grouped by property category
     */
    public static function getPropertiesByPropertyCategory(): array
    {
        $properties = self::with('propertyType')->get();

        $categories = [
            'residential' => 0,
            'commercial' => 0,
            'institutional' => 0,
            'religious' => 0,
            'industrial' => 0,
            'agricultural' => 0,
            'custom' => 0,
            'other' => 0
        ];

        foreach ($properties as $property) {
            $category = $property->getPropertyTypeCategory();
            if (isset($categories[$category])) {
                $categories[$category]++;
            } else {
                $categories['other']++;
            }
        }

        return $categories;
    }

    /**
     * Get properties grouped by rental status
     */
    public static function getPropertiesByRentalStatus(): array
    {
        return self::select('is_rented', \DB::raw('count(*) as count'))
            ->groupBy('is_rented')
            ->get()
            ->mapWithKeys(function($item) {
                $status = $item->is_rented ? 'Rented' : 'Not Rented';
                return [$status => $item->count];
            })
            ->toArray();
    }

    /**
     * Get properties grouped by field agent registration
     */
    public static function getPropertiesByFieldAgentRegistration(): array
    {
        return self::select('is_field_agent_registered', \DB::raw('count(*) as count'))
            ->groupBy('is_field_agent_registered')
            ->get()
            ->mapWithKeys(function($item) {
                $status = $item->is_field_agent_registered ? 'Field Agent Registered' : 'Admin Registered';
                return [$status => $item->count];
            })
            ->toArray();
    }

    /**
     * Get properties grouped by global sequence status
     */
    public static function getPropertiesByGlobalSequenceStatus(): array
    {
        return self::select('is_global_sequence', \DB::raw('count(*) as count'))
            ->groupBy('is_global_sequence')
            ->get()
            ->mapWithKeys(function($item) {
                $status = $item->is_global_sequence ? 'Global Sequence' : 'Regular';
                return [$status => $item->count];
            })
            ->toArray();
    }

    /**
     * Get properties grouped by tenant status
     */
    public static function getPropertiesByTenantStatus(): array
    {
        $withTenants = self::withTenants()->count();
        $withoutTenants = self::withoutTenants()->count();
        $withActiveTenants = self::withActiveTenants()->count();

        return [
            'With Tenants' => $withTenants,
            'Without Tenants' => $withoutTenants,
            'With Active Tenants' => $withActiveTenants,
        ];
    }

    /**
     * Get recent properties with pagination
     */
    public static function getRecentProperties($limit = 10)
    {
        return self::with(['landlord', 'registrationPlan.continuedFromPlan', 'registeredBy', 'propertyType', 'activeTenants', 'photos'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Get global sequence statistics
     */
    public static function getGlobalSequenceStatistics(): array
    {
        $globalSequencePlans = RegistrationPlan::where('is_global_sequence', true)->get();

        $statistics = [
            'total_global_sequence_plans' => $globalSequencePlans->count(),
            'total_properties_in_global_sequences' => self::globalSequence()->count(),
            'longest_chain_length' => 0,
            'most_used_pattern' => null,
            'zones_with_global_sequences' => [],
            'total_sequence_positions' => self::globalSequence()->sum('sequence_position'),
            'average_sequence_position' => self::globalSequence()->avg('sequence_position'),
        ];

        // Calculate longest chain
        $longestChain = 0;
        $patternUsage = [];

        foreach ($globalSequencePlans as $plan) {
            // Calculate chain length
            $chainLength = 1;
            $currentPlan = $plan;
            while ($currentPlan->continuedFromPlan) {
                $chainLength++;
                $currentPlan = $currentPlan->continuedFromPlan;
            }
            $longestChain = max($longestChain, $chainLength);

            // Track pattern usage
            $pattern = $plan->naming_pattern;
            $patternUsage[$pattern] = ($patternUsage[$pattern] ?? 0) + 1;

            // Track zones
            if (!in_array($plan->zone, $statistics['zones_with_global_sequences'])) {
                $statistics['zones_with_global_sequences'][] = $plan->zone;
            }
        }

        $statistics['longest_chain_length'] = $longestChain;
        $statistics['most_used_pattern'] = array_keys($patternUsage, max($patternUsage))[0] ?? null;

        return $statistics;
    }

    /**
     * Get field agent performance statistics
     */
    public static function getFieldAgentPerformance($agentId = null): array
    {
        $query = self::query();

        if ($agentId) {
            $query->where('registered_by', $agentId);
        } else {
            $query->whereNotNull('registered_by');
        }

        $totalProperties = $query->count();
        $propertiesWithDigitalAddress = $query->clone()->hasDigitalAddress()->count();
        $activeProperties = $query->clone()->active()->count();
        $underConstruction = $query->clone()->underConstruction()->count();
        $recentProperties = $query->clone()->where('created_at', '>=', now()->subDays(30))->count();
        $rentedProperties = $query->clone()->rented()->count();
        $globalSequenceProperties = $query->clone()->globalSequence()->count();
        $propertiesWithTenants = $query->clone()->withTenants()->count();
        // ✅ NEW: Coordinate coverage metrics
        $propertiesWithCoordinates = $query->clone()->hasCoordinates()->count();
        // ✅ NEW: Photo-related metrics
        $propertiesWithPhotos = $query->clone()->withPhotos()->count();

        return [
            'total_properties_registered' => $totalProperties,
            'properties_with_digital_address' => $propertiesWithDigitalAddress,
            'properties_with_coordinates' => $propertiesWithCoordinates,
            'active_properties' => $activeProperties,
            'under_construction' => $underConstruction,
            'recent_properties' => $recentProperties,
            'rented_properties' => $rentedProperties,
            'global_sequence_properties' => $globalSequenceProperties,
            'properties_with_tenants' => $propertiesWithTenants,
            'properties_with_photos' => $propertiesWithPhotos,
            'digital_address_completion_rate' => $totalProperties > 0 ? round(($propertiesWithDigitalAddress / $totalProperties) * 100, 2) : 0,
            'coordinate_completion_rate' => $totalProperties > 0 ? round(($propertiesWithCoordinates / $totalProperties) * 100, 2) : 0,
            'photo_upload_rate' => $totalProperties > 0 ? round(($propertiesWithPhotos / $totalProperties) * 100, 2) : 0,
            'property_type_distribution' => self::getPropertyTypeStatisticsByLandlord($agentId),
        ];
    }

    /**
     * Get properties by field agent with statistics
     */
    public static function getPropertiesByFieldAgent($agentId)
    {
        return self::with(['landlord', 'registrationPlan', 'propertyType', 'activeTenants', 'photos'])
            ->registeredBy($agentId)
            ->get()
            ->map(function($property) {
                return [
                    'property' => $property,
                    'statistics' => [
                        'has_digital_address' => $property->has_digital_address,
                        'has_coordinates' => $property->has_coordinates,
                        'is_active' => $property->isActive(),
                        'is_under_construction' => $property->is_under_construction,
                        'is_rented' => $property->is_rented,
                        'is_global_sequence' => $property->is_global_sequence,
                        'tenants_count' => $property->tenants_count,
                        'active_tenants_count' => $property->active_tenants_count,
                        'maintenance_requests_count' => $property->maintenanceRequests()->count(),
                        'property_type' => $property->display_property_type,
                        'property_type_icon' => $property->property_type_icon,
                        'property_type_color' => $property->property_type_color,
                        'property_type_category' => $property->getPropertyTypeCategory(),
                        'sequence_position' => $property->sequence_position,
                        'registered_at' => $property->field_agent_registered_at?->format('M d, Y'),
                        // ✅ NEW: Photo statistics
                        'has_photos' => $property->has_photos,
                        'photos_count' => $property->photos_count,
                        'primary_photo_url' => $property->primary_photo_url,
                        'construction_status' => $property->construction_status,
                        'estimated_completion' => $property->estimated_completion?->format('M d, Y'),
                    ]
                ];
            });
    }

    /**
     * Get property type suggestions for auto-complete
     */
    public static function getPropertyTypeSuggestions($query = ''): array
    {
        $propertyTypes = PropertyType::active()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get()
            ->map(function($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                    'color' => $type->color,
                    'description' => $type->description,
                    'is_custom' => false
                ];
            })
            ->toArray();

        // Add custom type option
        if (strpos('custom', strtolower($query)) !== false || empty($query)) {
            $customType = PropertyType::where('slug', 'custom')->first();
            if ($customType) {
                $propertyTypes[] = [
                    'id' => $customType->id,
                    'name' => 'Custom Property Type',
                    'slug' => 'custom',
                    'icon' => 'fas fa-edit',
                    'color' => '#6b7280',
                    'description' => 'Define your own property type',
                    'is_custom' => true
                ];
            }
        }

        return $propertyTypes;
    }

    /**
     * Validate property type data
     */
    public function validatePropertyType(): array
    {
        $errors = [];

        if (!$this->property_type_id) {
            $errors[] = 'Property type is required';
            return $errors;
        }

        $propertyType = PropertyType::find($this->property_type_id);
        if (!$propertyType) {
            $errors[] = 'Selected property type not found';
            return $errors;
        }

        if ($propertyType->slug === 'custom' && empty($this->custom_property_type)) {
            $errors[] = 'Custom property type name is required when selecting custom type';
        }

        if ($this->custom_property_type && strlen($this->custom_property_type) > 100) {
            $errors[] = 'Custom property type name must not exceed 100 characters';
        }

        return $errors;
    }

    /**
     * Get property type display data for API
     */
    public function getPropertyTypeDisplay(): array
    {
        return [
            'id' => $this->property_type_id,
            'name' => $this->display_property_type,
            'icon' => $this->property_type_icon,
            'color' => $this->property_type_color,
            'is_custom' => $this->is_custom_type,
            'category' => $this->getPropertyTypeCategory(),
            'badge' => $this->property_type_badge
        ];
    }

    /**
     * Get comprehensive property data for API
     */
    public function getComprehensiveData(): array
    {
        return [
            'id' => $this->id,
            'property_name' => $this->property_name,
            'registration_pattern' => $this->registration_pattern,
            'full_address' => $this->full_address,
            'physical_address' => $this->physical_address,
            'has_digital_address' => $this->has_digital_address,
            'digital_address' => $this->digital_address,
            // ✅ NEW: coordinates
            'has_coordinates' => $this->has_coordinates,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'status' => $this->status,
            'status_badge' => $this->site_status_badge,
            'registration_date' => $this->registration_date?->format('Y-m-d'),
            'last_inspection_date' => $this->last_inspection_date?->format('Y-m-d H:i:s'),
            'is_rented' => $this->is_rented,
            'rental_status_badge' => $this->rental_status_badge,
            'is_field_agent_registered' => $this->is_field_agent_registered,
            'field_agent_info' => $this->field_agent_info,
            'is_global_sequence' => $this->is_global_sequence,
            'global_sequence_info' => $this->global_sequence_info,
            'sequence_info' => $this->sequence_info,
            'property_type' => $this->getPropertyTypeDisplay(),
            'landlord' => $this->landlord ? [
                'id' => $this->landlord->id,
                'name' => $this->landlord->name,
                'email' => $this->landlord->email,
                'phone' => $this->landlord->phone,
            ] : null,
            'registration_plan' => $this->registrationPlan ? [
                'id' => $this->registrationPlan->id,
                'name' => $this->registrationPlan->name,
                'zone' => $this->registrationPlan->zone,
                'section' => $this->registrationPlan->section,
                'naming_pattern' => $this->registrationPlan->naming_pattern,
                'is_global_sequence' => $this->registrationPlan->is_global_sequence,
            ] : null,
            'creator' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'registered_by' => $this->registeredBy ? [
                'id' => $this->registeredBy->id,
                'name' => $this->registeredBy->name,
            ] : null,
            // ✅ NEW: Photo gallery data
            'photos' => [
                'total_count' => $this->photos_count,
                'has_photos' => $this->has_photos,
                'primary_photo_url' => $this->primary_photo_url,
                'primary_photo_thumbnail_url' => $this->primary_photo_thumbnail_url,
                'gallery' => $this->photo_gallery,
            ],
            'statistics' => [
                'tenants_count' => $this->tenants_count,
                'active_tenants_count' => $this->active_tenants_count,
                'registration_tenants_count' => $this->registration_tenants_count,
                'approved_registration_tenants' => $this->approved_registration_tenants_count,
                'units_count' => $this->units()->count(),
                'maintenance_requests_count' => $this->maintenanceRequests()->count(),
                'pending_maintenance_requests_count' => $this->maintenanceRequests()->where('status', 'pending')->count(),
                'invoices_count' => $this->invoices()->count(),
                'payments_count' => $this->payments()->count(),
                'tenant_stats' => $this->getTenantStats(),
                // ✅ NEW: Construction statistics
                'is_under_construction' => $this->is_under_construction,
                'construction_status' => $this->construction_status,
                'formatted_construction_status' => $this->formatted_construction_status,
                'estimated_completion' => $this->estimated_completion?->format('Y-m-d'),
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'construction_progress' => $this->construction_progress,
            ],
            'current_tenant' => $this->current_tenant_info,
            'timestamps' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
                'field_agent_registered_at' => $this->field_agent_registered_at?->format('Y-m-d H:i:s'),
            ],
            'description' => $this->description,
        ];
    }

    /**
     * Mark property as rented
     */
    public function markAsRented(): bool
    {
        $this->is_rented = true;
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    /**
     * Mark property as not rented
     */
    public function markAsNotRented(): bool
    {
        $this->is_rented = false;
        // Only change status to vacant if there are no active tenants
        if ($this->active_tenants_count === 0) {
            $this->status = self::STATUS_VACANT;
        }
        return $this->save();
    }

    /**
     * Mark as field agent registered
     */
    public function markAsFieldAgentRegistered($agentId): bool
    {
        $this->registered_by = $agentId;
        $this->is_field_agent_registered = true;
        $this->field_agent_registered_at = now();
        return $this->save();
    }

    /**
     * Mark as admin registered
     */
    public function markAsAdminRegistered(): bool
    {
        $this->registered_by = null;
        $this->is_field_agent_registered = false;
        $this->field_agent_registered_at = null;
        return $this->save();
    }

    /**
     * Update sequence position
     */
    public function updateSequencePosition(): bool
    {
        if (!$this->is_global_sequence || !$this->registration_plan_id) {
            return false;
        }

        $this->sequence_position = $this->getNextSequencePosition($this->registration_plan_id);
        return $this->save();
    }

    /**
     * Check if property has associated data that would prevent deletion
     */
    public function hasAssociatedData(): array
    {
        $hasData = [
            'photos' => $this->photos()->exists(),
            'tenants' => $this->tenants()->exists(),
            'registration_tenants' => $this->registrationTenants()->exists(),
            'units' => $this->units()->exists(),
            'invoices' => $this->invoices()->exists(),
            'payments' => $this->payments()->exists(),
            'maintenance_requests' => $this->maintenanceRequests()->exists(),
            'tenant_invitations' => $this->tenantInvitations()->exists(),
            'landlord_invitations' => $this->landlordInvitations()->exists(),
        ];

        $hasData['total'] = in_array(true, $hasData, true);

        return $hasData;
    }

    /**
     * Get associated data counts
     */
    public function getAssociatedDataCounts(): array
    {
        return [
            'photos' => $this->photos()->count(),
            'tenants' => $this->tenants()->count(),
            'registration_tenants' => $this->registrationTenants()->count(),
            'units' => $this->units()->count(),
            'invoices' => $this->invoices()->count(),
            'payments' => $this->payments()->count(),
            'maintenance_requests' => $this->maintenanceRequests()->count(),
            'tenant_invitations' => $this->tenantInvitations()->count(),
            'landlord_invitations' => $this->landlordInvitations()->count(),
        ];
    }

    /**
     * Validate the property model data
     */
    public static function validateData(array $data, array $rules = []): \Illuminate\Validation\Validator
    {
        return Validator::make($data, array_merge(self::$rules, $rules));
    }

    /**
     * Check if property is available for new tenants
     */
    public function isAvailableForTenants(): bool
    {
        return $this->isActive() &&
               ($this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_VACANT) &&
               !$this->isUnderMaintenance();
    }

    /**
     * Get occupancy rate (for multi-unit properties)
     */
    public function getOccupancyRate(): float
    {
        $totalUnits = $this->units()->count();
        if ($totalUnits === 0) {
            return $this->hasActiveTenants() ? 100.0 : 0.0;
        }

        $occupiedUnits = $this->units()->whereHas('tenants')->count();
        return round(($occupiedUnits / $totalUnits) * 100, 2);
    }

    /**
     * Get property age category
     */
    public function getAgeCategory(): string
    {
        $ageInMonths = $this->getAgeInMonths();

        if ($ageInMonths < 6) return 'new';
        if ($ageInMonths < 24) return 'recent';
        if ($ageInMonths < 60) return 'established';
        return 'old';
    }

    /**
     * Check if property needs maintenance
     */
    public function needsMaintenance(): bool
    {
        // Check if there are pending maintenance requests
        if ($this->getPendingMaintenanceCount() > 0) {
            return true;
        }

        // Check if last inspection was more than 12 months ago
        if ($this->last_inspection_date && $this->last_inspection_date->diffInMonths(now()) > 12) {
            return true;
        }

        return false;
    }

    /**
     * Get property value estimation (simplified)
     */
    public function estimateValue(): array
    {
        $baseValue = 100000; // Base value in currency

        // Adjust based on property type
        $typeMultiplier = 1.0;
        if ($this->isCommercial()) $typeMultiplier = 1.5;
        if ($this->isResidential()) $typeMultiplier = 1.2;
        if ($this->isIndustrial()) $typeMultiplier = 1.8;

        // Adjust based on age
        $ageMultiplier = 1.0;
        $ageCategory = $this->getAgeCategory();
        if ($ageCategory === 'new') $ageMultiplier = 1.3;
        if ($ageCategory === 'recent') $ageMultiplier = 1.1;
        if ($ageCategory === 'old') $ageMultiplier = 0.7;

        // Adjust based on occupancy
        $occupancyMultiplier = $this->getOccupancyRate() / 100;

        // Adjust based on construction status
        $constructionMultiplier = 1.0;
        if ($this->is_under_construction) {
            $constructionMultiplier = 0.8; // Under construction properties are worth less
        }

        // Adjust based on photos
        $photoMultiplier = 1.0;
        if ($this->has_photos) {
            $photoMultiplier = min(1.1, 1 + ($this->photos_count * 0.02)); // Up to 10% bonus for photos
        }

        $estimatedValue = $baseValue * $typeMultiplier * $ageMultiplier * $occupancyMultiplier * $constructionMultiplier * $photoMultiplier;

        return [
            'estimated_value' => round($estimatedValue, 2),
            'base_value' => $baseValue,
            'multipliers' => [
                'type' => $typeMultiplier,
                'age' => $ageMultiplier,
                'occupancy' => $occupancyMultiplier,
                'construction' => $constructionMultiplier,
                'photos' => $photoMultiplier,
            ],
            'age_category' => $ageCategory,
            'occupancy_rate' => $this->getOccupancyRate(),
            'property_type' => $this->display_property_type,
            'is_under_construction' => $this->is_under_construction,
            'has_photos' => $this->has_photos,
            'photos_count' => $this->photos_count,
        ];
    }

    /**
     * Get property performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'occupancy_rate' => $this->getOccupancyRate(),
            'maintenance_score' => $this->getMaintenanceScore(),
            'inspection_score' => $this->getInspectionScore(),
            'tenant_satisfaction' => $this->getTenantSatisfactionScore(),
            'financial_performance' => $this->getFinancialPerformance(),
            'photo_completeness' => $this->getPhotoCompletenessScore(),
        ];
    }

    /**
     * Get maintenance score (0-100)
     */
    private function getMaintenanceScore(): int
    {
        $pendingCount = $this->getPendingMaintenanceCount();
        $totalCount = $this->maintenanceRequests()->count();

        if ($totalCount === 0) return 100;

        $score = 100 - (($pendingCount / $totalCount) * 100);
        return (int) max(0, min(100, $score));
    }

    /**
     * Get inspection score (0-100)
     */
    private function getInspectionScore(): int
    {
        if (!$this->last_inspection_date) return 0;

        $monthsSinceInspection = $this->last_inspection_date->diffInMonths(now());

        if ($monthsSinceInspection <= 6) return 100;
        if ($monthsSinceInspection <= 12) return 70;
        if ($monthsSinceInspection <= 18) return 40;
        return 10;
    }

    /**
     * Get tenant satisfaction score (simplified)
     */
    private function getTenantSatisfactionScore(): int
    {
        $activeTenants = $this->active_tenants_count;
        $totalTenants = $this->tenants_count;

        if ($totalTenants === 0) return 0;

        // Simple calculation: active tenants vs total tenants
        $score = ($activeTenants / $totalTenants) * 100;
        return (int) round($score);
    }

    /**
     * Get financial performance
     */
    private function getFinancialPerformance(): array
    {
        $totalInvoices = $this->invoices()->sum('amount');
        $totalPayments = $this->payments()->sum('amount');

        return [
            'total_invoiced' => $totalInvoices,
            'total_paid' => $totalPayments,
            'outstanding' => $totalInvoices - $totalPayments,
            'collection_rate' => $totalInvoices > 0 ? round(($totalPayments / $totalInvoices) * 100, 2) : 0,
        ];
    }

    /**
     * Get photo completeness score (0-100)
     */
    private function getPhotoCompletenessScore(): int
    {
        if (!$this->has_photos) return 0;

        $score = 30; // Base score for having any photos

        // Bonus for having a primary photo
        if ($this->primary_photo_url) {
            $score += 20;
        }

        // Bonus for multiple photos
        if ($this->photos_count >= 5) {
            $score += 30;
        } elseif ($this->photos_count >= 3) {
            $score += 20;
        } elseif ($this->photos_count >= 2) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get property health status
     */
    public function getHealthStatus(): array
    {
        $metrics = $this->getPerformanceMetrics();

        $totalScore = (
            $metrics['occupancy_rate'] +
            $metrics['maintenance_score'] +
            $metrics['inspection_score'] +
            $metrics['tenant_satisfaction'] +
            $metrics['financial_performance']['collection_rate'] +
            $metrics['photo_completeness']
        ) / 6;

        $status = 'healthy';
        $color = 'success';

        if ($totalScore < 50) {
            $status = 'critical';
            $color = 'danger';
        } elseif ($totalScore < 70) {
            $status = 'warning';
            $color = 'warning';
        } elseif ($totalScore < 85) {
            $status = 'fair';
            $color = 'info';
        }

        return [
            'score' => round($totalScore, 2),
            'status' => $status,
            'color' => $color,
            'metrics' => $metrics,
        ];
    }

    /**
     * Get property timeline events
     */
    public function getTimelineEvents(): array
    {
        $events = [];

        // Creation event
        $events[] = [
            'date' => $this->created_at,
            'type' => 'created',
            'title' => 'Property Created',
            'description' => 'Property was registered in the system',
            'icon' => 'fas fa-plus-circle',
            'color' => 'primary',
        ];

        // Field agent registration event
        if ($this->field_agent_registered_at) {
            $events[] = [
                'date' => $this->field_agent_registered_at,
                'type' => 'field_agent_registered',
                'title' => 'Field Agent Registration',
                'description' => 'Property was registered by field agent',
                'icon' => 'fas fa-user-tie',
                'color' => 'info',
            ];
        }

        // Last inspection event
        if ($this->last_inspection_date) {
            $events[] = [
                'date' => $this->last_inspection_date,
                'type' => 'inspection',
                'title' => 'Last Inspection',
                'description' => 'Property was last inspected',
                'icon' => 'fas fa-clipboard-check',
                'color' => 'warning',
            ];
        }

        // ✅ NEW: Coordinate geocoding event
        if ($this->has_coordinates) {
            $events[] = [
                'date' => $this->updated_at,
                'type' => 'coordinates_set',
                'title' => 'Coordinates Recorded',
                'description' => "Location captured at {$this->latitude}, {$this->longitude}",
                'icon' => 'fas fa-map-marker-alt',
                'color' => 'info',
            ];
        }

        // ✅ NEW: Photo upload events
        $photoUploads = $this->photos()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($photo) {
                return [
                    'date' => $photo->created_at,
                    'type' => 'photo_uploaded',
                    'title' => 'Photo Uploaded',
                    'description' => $photo->is_primary ? 'Primary photo uploaded' : 'Property photo uploaded',
                    'icon' => 'fas fa-camera',
                    'color' => 'info',
                    'photo_id' => $photo->id,
                    'photo_url' => $photo->photo_url,
                ];
            });

        $events = array_merge($events, $photoUploads->toArray());

        // ✅ NEW: Under construction event
        if ($this->status === self::STATUS_UNDER_CONSTRUCTION && $this->created_at) {
            $events[] = [
                'date' => $this->updated_at,
                'type' => 'under_construction',
                'title' => 'Under Construction',
                'description' => 'Property is under construction',
                'icon' => 'fas fa-hard-hat',
                'color' => 'warning',
            ];
        }

        // Construction status change events
        if ($this->construction_status && $this->updated_at) {
            $events[] = [
                'date' => $this->updated_at,
                'type' => 'construction_status_changed',
                'title' => 'Construction Status Changed',
                'description' => "Construction status changed to {$this->formatted_construction_status}",
                'icon' => 'fas fa-hard-hat',
                'color' => 'info',
            ];
        }

        // Tenant events (User model)
        $tenantEvents = $this->tenants()
            ->withPivot('added_at', 'status')
            ->orderBy('property_tenant.added_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($tenant) {
                return [
                    'date' => $tenant->pivot->added_at,
                    'type' => 'tenant_' . $tenant->pivot->status,
                    'title' => ucfirst($tenant->pivot->status) . ' Tenant',
                    'description' => "Tenant {$tenant->name} was {$tenant->pivot->status}",
                    'icon' => $tenant->pivot->status === 'active' ? 'fas fa-user-plus' : 'fas fa-user-minus',
                    'color' => $tenant->pivot->status === 'active' ? 'success' : 'secondary',
                ];
            });

        $events = array_merge($events, $tenantEvents->toArray());

        // Registration tenant events
        $registrationTenantEvents = $this->registrationTenants()
            ->latest()
            ->take(5)
            ->get()
            ->map(function($tenant) {
                return [
                    'date' => $tenant->created_at,
                    'type' => 'registration_tenant_' . $tenant->status,
                    'title' => 'Registration Tenant ' . ucfirst($tenant->status),
                    'description' => "Registration tenant {$tenant->name} was {$tenant->status}",
                    'icon' => 'fas fa-user-check',
                    'color' => $tenant->status === 'approved' ? 'success' : ($tenant->status === 'pending' ? 'warning' : 'danger'),
                ];
            });

        $events = array_merge($events, $registrationTenantEvents->toArray());

        // Sort by date descending
        usort($events, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return $events;
    }

    /**
     * Scope to get properties eligible for construction contracts
     * ✅ BEST PRACTICE: Reusable query scope
     */
    public function scopeEligibleForContract($query)
    {
        return $query->where(function($q) {
            $q->where('status', 'vacant')
              ->orWhere('status', 'under_construction')
              ->orWhere(function($subQ) {
                  $subQ->where(function($emptyQ) {
                      $emptyQ->whereNull('status')
                             ->orWhere('status', '');
                  })->where('construction_status', 'under_construction');
              });
        });
    }

    /**
     * Get the display status for dropdown (helper method)
     */
    public function getContractDisplayStatus(): string
    {
        if ($this->status === 'vacant') {
            return 'Vacant Land';
        }

        if ($this->status === 'under_construction') {
            return 'Under Construction';
        }

        if ((empty($this->status) || $this->status === null) && $this->construction_status === 'under_construction') {
            return 'Vacant Land (Under Construction)';
        }

        return 'Unknown';
    }

    protected static function booted()
    {
        parent::booted();
        // NOTE: The `created` listener for markInProgressIfAssigned
        // has been consolidated into `boot()` above to avoid duplicate listeners.
    }
}