<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\PropertyCollection;
use App\Http\Resources\PhotoResource;
use App\Http\Traits\PropertyAuthorizationTrait;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\RegistrationPlan;
use App\Models\User;
use App\Services\PropertyRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PropertyApiController extends Controller
{
    use PropertyAuthorizationTrait;

    protected PropertyRegistrationService $registrationService;

    public function __construct(PropertyRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    // =================================================================
    // LIST / INDEX
    // GET /api/properties
    // =================================================================
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            return $this->jsonError('Unauthorized. Admin access required.', 403);
        }

        $query = Property::with(['landlord', 'registrationPlan', 'registeredBy', 'propertyType'])
            ->withCount(['photos', 'tenants']);

        $this->applyFilters($query, $request);

        $properties = $query->latest()->paginate($request->integer('per_page', 20));

        // Compute stats across all filtered rows (not just this page).
        $allForStats = (clone $query)->get();
        $stats = $this->buildStats($allForStats);

        return (new PropertyCollection($properties))->additional([
            'stats' => $stats,
        ]);
    }

    // =================================================================
    // STORE
    // POST /api/properties
    // =================================================================
    public function store(Request $request)
    {
        if (!$this->canCreateProperty($request)) {
            return $this->jsonError('Only administrators can register site allocations.', 403);
        }

        $validator = $this->validateStore($request);
        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        try {
            DB::beginTransaction();

            $landlordId = $this->resolveLandlord($request);
            if ($landlordId instanceof \Illuminate\Http\JsonResponse) {
                return $landlordId; // early error
            }

            $propertyData = [
                'property_name'    => $request->property_name,
                'street_name'      => $request->street_name,
                'house_number'     => $request->house_number,
                'digital_address'  => $request->digital_address,
            ];

            if ($this->isDuplicateProperty($landlordId, $propertyData)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This property already exists for this landlord.',
                    'similar_properties' => $this->getSimilarProperties($landlordId, $propertyData),
                    'duplicate_prevention' => true,
                ], 409);
            }

            $request->merge(['landlord_id' => $landlordId]);

            $result = $this->registrationService->createProperty($request->all(), $request->user());

            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to create property');
            }

            $property = Property::find($result['site_allocation']['id']);

            if ($property && $request->hasFile('property_photos')) {
                $this->handlePhotoUploads($request, $property);
            }

            DB::commit();

            return (new PropertyResource($property->fresh(['landlord', 'propertyType', 'photos'])))
                ->additional(['message' => 'Site allocation registered successfully.'])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to create property', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return $this->jsonError('Failed to create property.', 500, config('app.debug') ? $e->getMessage() : null);
        }
    }

    // =================================================================
    // SHOW
    // GET /api/properties/{property}
    // =================================================================
    public function show(Request $request, Property $property)
    {
        if (!$this->canViewProperty($property)) {
            return $this->jsonError('Unauthorized to view this site allocation.', 403);
        }

        $property->load([
            'landlord', 'registrationPlan.continuedFromPlan', 'registeredBy',
            'propertyType', 'tenants', 'photos', 'units',
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

        return (new PropertyResource($property))->additional([
            'all_tenants'         => $allTenants,
            'pending_assignments' => $pendingAssignments,
            'unit_assignments'    => $unitAssignments,
        ]);
    }

    // =================================================================
    // UPDATE
    // PUT /api/properties/{property}
    // =================================================================
    public function update(Request $request, Property $property)
    {
        if (!$this->canUpdateProperty($property)) {
            return $this->jsonError('Unauthorized to update this site allocation.', 403);
        }

        $validator = $this->validateUpdate($request);
        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        // Duplicate check on landlord change
        if ($request->landlord_id != $property->landlord_id) {
            $propertyData = [
                'property_name'   => $request->property_name ?? $property->property_name,
                'street_name'     => $request->street_name ?? $property->street_name,
                'house_number'    => $request->house_number ?? $property->house_number,
                'digital_address' => $request->digital_address ?? $property->digital_address,
            ];

            if ($this->isDuplicateProperty($request->landlord_id, $propertyData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This property already exists for the new landlord.',
                    'similar_properties' => $this->getSimilarProperties($request->landlord_id, $propertyData),
                    'duplicate_prevention' => true,
                ], 409);
            }
        }

        DB::beginTransaction();

        try {
            $updateData = $validator->validated();

            $customType = PropertyType::where('slug', 'custom')->first();
            $updateData['custom_property_type'] = ($request->property_type_id == ($customType->id ?? null))
                ? $request->custom_property_type
                : null;

            if ($request->filled('construction_status')) {
                $updateData['status'] = match ($request->construction_status) {
                    'under_construction' => 'under_construction',
                    'active', 'completed' => 'active',
                    'vacant'             => 'vacant',
                    'inactive', 'paused' => 'inactive',
                    default              => $property->status,
                };
            }

            $property->update($updateData);
            $this->handlePhotoManagement($request, $property);

            DB::commit();

            return (new PropertyResource(
                $property->fresh(['landlord', 'registrationPlan', 'propertyType', 'tenants', 'photos'])
            ))->additional(['message' => 'Site allocation updated successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update property', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to update site allocation.', 500);
        }
    }

    // =================================================================
    // DESTROY (soft delete)
    // DELETE /api/properties/{property}
    // =================================================================
    public function destroy(Request $request, Property $property)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return $this->jsonError('Unauthorized. Only administrators can delete site allocations.', 403);
        }

        DB::beginTransaction();

        try {
            foreach ($property->photos as $photo) {
                $photo->deletePhotoFile();
            }
            $property->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Site allocation deleted successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to delete property', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to delete site allocation.', 500);
        }
    }

    // =================================================================
    // TRASH / SOFT-DELETED
    // =================================================================

    // GET /api/properties/trash
public function trashed(Request $request)
{
    $user = $request->user();
    if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) {
        return $this->jsonError('Unauthorized.', 403);
    }

    $perPage = $request->integer('per_page', 20);

    $query = Property::onlyTrashed()
        ->with(['landlord', 'registrationPlan', 'registeredBy', 'propertyType'])
        ->withCount(['photos', 'tenants']);

    // ✅ NEW: Apply optional filters
    if ($request->filled('search')) {
        $s = $request->string('search');
        $query->where(function ($q) use ($s) {
            $q->where('property_name', 'like', "%{$s}%")
              ->orWhere('street_name', 'like', "%{$s}%")
              ->orWhere('digital_address', 'like', "%{$s}%")
              ->orWhere('house_number', 'like', "%{$s}%");
        });
    }

    if ($request->filled('landlord_id')) {
        $query->where('landlord_id', $request->integer('landlord_id'));
    }

    if ($request->filled('deleted_from')) {
        $query->whereDate('deleted_at', '>=', $request->date('deleted_from'));
    }

    if ($request->filled('deleted_to')) {
        $query->whereDate('deleted_at', '<=', $request->date('deleted_to'));
    }

    $properties = $query->latest('deleted_at')->paginate($perPage);

    return new PropertyCollection($properties);
}

    // GET /api/properties/trash/count
    public function trashCount(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            return $this->jsonError('Unauthorized.', 403);
        }

        return response()->json([
            'success' => true,
            'count'   => Property::onlyTrashed()->count(),
        ]);
    }

    // POST /api/properties/trash/restore-all
    public function restoreAll(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return $this->jsonError('Unauthorized. Only super admins can bulk restore.', 403);
        }

        try {
            $count = Property::onlyTrashed()->restore();

            return response()->json([
                'success'        => true,
                'restored_count' => $count,
                'message'        => "{$count} propert" . ($count === 1 ? 'y' : 'ies') . ' restored.',
            ]);
        } catch (\Exception $e) {
            Log::error('API: Bulk restore failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to restore properties.', 500);
        }
    }

    // DELETE /api/properties/trash/empty
    public function emptyTrash(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return $this->jsonError('Unauthorized. Only super admins can empty the trash.', 403);
        }

        DB::beginTransaction();

        try {
            Property::onlyTrashed()->with('photos')->chunk(50, function ($properties) {
                foreach ($properties as $property) {
                    foreach ($property->photos as $photo) {
                        try {
                            $photo->deletePhotoFile();
                        } catch (\Exception $e) {
                            Log::warning('Failed to delete photo file', [
                                'photo_id' => $photo->id,
                                'error'    => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

            $count = Property::onlyTrashed()->forceDelete();

            DB::commit();

            return response()->json([
                'success'       => true,
                'deleted_count' => $count,
                'message'       => "{$count} propert" . ($count === 1 ? 'y' : 'ies') . ' permanently deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Empty trash failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to empty trash.', 500);
        }
    }

    // POST /api/properties/{property}/restore
    public function restore(Request $request, Property $property)
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            return $this->jsonError('Unauthorized.', 403);
        }

        if (!$property->trashed()) {
            return $this->jsonError('This property is not in the trash.', 422);
        }

        try {
            $property->restore();

            return (new PropertyResource($property->fresh(['landlord', 'propertyType'])))
                ->additional(['message' => 'Property restored successfully.']);
        } catch (\Exception $e) {
            Log::error('API: Restore failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to restore property.', 500);
        }
    }

    // DELETE /api/properties/{property}/force-delete
    public function forceDelete(Request $request, Property $property)
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return $this->jsonError('Unauthorized. Only super admins can permanently delete.', 403);
        }

        DB::beginTransaction();

        try {
            foreach ($property->photos as $photo) {
                try {
                    $photo->deletePhotoFile();
                } catch (\Exception $e) {
                    Log::warning('Failed to delete photo file', [
                        'photo_id' => $photo->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            $property->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property permanently deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Force delete failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to permanently delete property.', 500);
        }
    }

    // =================================================================
    // LANDLORD: MY PROPERTIES
    // GET /api/my-properties
    // =================================================================
    public function myProperties(Request $request)
    {
        $user = $request->user();

        $allProperties = Property::with([
                'landlord',
                'registrationPlan',
                'registeredBy',
                'propertyType',
                'tenants',
                'photos',
            ])
            ->where('landlord_id', $user->id)
            ->get();

        $stats = $this->buildStats($allProperties);

        $query = Property::with([
                'landlord',
                'registrationPlan',
                'registeredBy',
                'propertyType',
                'tenants',
                'photos',
            ])
            ->where('landlord_id', $user->id)
            ->withCount('photos');

        $this->applyFilters($query, $request);
        $properties = $query->latest()->paginate($request->integer('per_page', 20));

        return (new PropertyCollection($properties))->additional([
            'stats' => $stats,
        ]);
    }

    // =================================================================
    // DUPLICATE CHECK
    // POST /api/properties/check-duplicate
    // =================================================================
    public function checkDuplicate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'landlord_id'     => 'required|exists:users,id',
            'property_name'   => 'required|string|max:255',
            'street_name'     => 'nullable|string|max:255',
            'house_number'    => 'nullable|string|max:50',
            'digital_address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        $propertyData = $request->only(['property_name', 'street_name', 'house_number', 'digital_address']);
        $isDuplicate = $this->isDuplicateProperty($request->landlord_id, $propertyData);

        return response()->json([
            'success' => true,
            'is_duplicate' => $isDuplicate,
            'similar_properties' => $isDuplicate
                ? $this->getSimilarProperties($request->landlord_id, $propertyData)
                : [],
            'message' => $isDuplicate ? 'Similar property found.' : 'No duplicates found.',
        ]);
    }

    // =================================================================
    // CONSTRUCTION
    // POST /api/properties/construction
    // =================================================================
    public function updateConstruction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id'             => 'required|exists:properties,id',
            'property_type_id'        => 'required|string',
            'custom_property_type'    => 'nullable|string|max:100',
            'construction_status'     => 'required|in:under_construction,active,vacant,inactive',
            'estimated_bedrooms'      => 'nullable|integer|min:1|max:50',
            'estimated_completion'    => 'nullable|date|after:today',
            'has_plans'               => 'nullable|in:yes,no',
            'construction_notes'      => 'nullable|string|max:1000',
            'construction_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'declaration'             => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        DB::beginTransaction();

        try {
            $property = Property::findOrFail($request->property_id);

            $isOwner = $property->landlord_id === $request->user()->id;
            $isAdmin = $request->user()->isAdmin() || $request->user()->isSuperAdmin();

            if (!$isOwner && !$isAdmin) {
                DB::rollBack();
                return $this->jsonError('You do not have permission to update this property.', 403);
            }

            $service = app(\App\Services\PropertyConstructionService::class);
            $result = $service->update($property, $request->all(), $request->user());

            if (!$result['success']) {
                DB::rollBack();
                return $this->jsonError($result['message'], 400);
            }

            DB::commit();

            return (new PropertyResource($property->fresh(['propertyType'])))->additional([
                'message' => 'Construction details updated successfully.',
                'status_changes' => $result['status_changes'] ?? [],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update construction details', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to update construction details.', 500);
        }
    }

    // =================================================================
    // ✅ NEW: RESTful alias for updateConstruction
    // PUT /api/properties/{property}/construction
    //
    // Mirrors web controller's `updateConstructionWithProperty()`.
    // Injects the route-bound property id into the request and
    // delegates to updateConstruction().
    // =================================================================
    public function updateConstructionWithProperty(Request $request, Property $property)
    {
        $request->merge(['property_id' => $property->id]);

        return $this->updateConstruction($request);
    }

    // =================================================================
    // MARK ACTIVE
    // POST /api/properties/{property}/mark-active
    // =================================================================
    public function markActive(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [
            'completion_notes' => 'nullable|string|max:500',
            'declaration'      => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        if ($property->landlord_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->jsonError('You do not own this property.', 403);
        }

        if ($property->status !== 'under_construction') {
            return $this->jsonError('This property is not under construction.', 400);
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
                    'Property marked active by ' . $request->user()->name
                    . ($request->completion_notes ? ' | ' . $request->completion_notes : ''));
            }

            DB::commit();

            return (new PropertyResource($property->fresh()))
                ->additional(['message' => 'Property marked as Active successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to mark active', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to mark property as active.', 500);
        }
    }

    // =================================================================
    // PHOTOS
    // =================================================================

    // POST /api/properties/{property}/photos
    public function uploadPhotos(Request $request, Property $property)
    {
        if (!$this->canUpdateProperty($property)) {
            return $this->jsonError('Unauthorized.', 403);
        }

        $field = $request->hasFile('property_photos') ? 'property_photos' : 'photos';

        $validator = Validator::make($request->all(), [
            $field        => 'required|array|max:10',
            "{$field}.*"  => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        try {
            $files = $request->file($field);

            $results = collect($files)->map(function ($file) use ($property) {
                $isPrimary = $property->photos()->count() === 0;
                $photo = $property->addPhoto($file, $isPrimary);
                return new PhotoResource($photo);
            });

            return response()->json([
                'success' => true,
                'message' => $results->count() . ' photo(s) uploaded successfully.',
                'photos'  => $results,
            ], 201);

        } catch (\Exception $e) {
            Log::error('API: Photo upload failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to upload photos.', 500);
        }
    }

    // DELETE /api/properties/{property}/photos/{photo}
    public function deletePhoto(Request $request, Property $property, $photo)
    {
        if (!$this->canUpdateProperty($property)) {
            return $this->jsonError('Unauthorized.', 403);
        }

        $photoId = $photo instanceof \App\Models\PropertyPhoto
            ? $photo->id
            : (int) $photo;

        $record = $property->photos()->find($photoId);
        if (!$record) {
            return $this->jsonError('Photo not found.', 404);
        }

        try {
            $record->deletePhotoFile();
            $record->delete();
        } catch (\Exception $e) {
            Log::error('API: Photo delete failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to delete photo.', 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully.',
        ]);
    }

    // POST /api/properties/{property}/photos/{photo}/primary
    public function setPrimaryPhoto(Request $request, Property $property, $photo)
    {
        if (!$this->canUpdateProperty($property)) {
            return $this->jsonError('Unauthorized.', 403);
        }

        $photoId = $photo instanceof \App\Models\PropertyPhoto
            ? $photo->id
            : (int) $photo;

        $success = $property->setPrimaryPhoto($photoId);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Primary photo updated.' : 'Photo not found.',
        ], $success ? 200 : 404);
    }

    // GET /api/properties/{property}/photos
    public function getPhotoGallery(Request $request, Property $property)
    {
        if (!$this->canViewProperty($property)) {
            return $this->jsonError('Unauthorized.', 403);
        }

        $photos = $property->photos()
            ->orderBy('is_primary', 'desc')
            ->orderBy('sort_order')
            ->get();

        return PhotoResource::collection($photos)->additional([
            'total' => $photos->count(),
            'has_photos' => $photos->isNotEmpty(),
        ]);
    }

    // =================================================================
    // LOOKUPS
    // GET /api/landlords
    // =================================================================
    public function getAvailableLandlords(Request $request)
    {
        $search = $request->get('search', '');

        $query = User::query()
            ->where(fn ($q) => $q->where('type', User::TYPE_LANDLORD)
                ->orWhereHas('roles', fn ($r) => $r->where('slug', 'landlord')))
            ->where('status', User::STATUS_ACTIVE);

        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        return response()->json([
            'success' => true,
            'landlords' => $query->orderBy('name')->limit(20)->get(),
        ]);
    }

    // =================================================================
    // OWNERSHIP TRANSFER
    // POST /api/properties/{property}/transfer
    // =================================================================
    public function transferOwnership(Request $request, Property $property)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return $this->jsonError('Only administrators can transfer ownership.', 403);
        }

        $validator = Validator::make($request->all(), [
            'new_landlord_id' => 'required|exists:users,id',
            'transfer_reason' => 'nullable|string|max:500',
            'effective_date'  => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->jsonValidationError($validator);
        }

        $newLandlord = User::findOrFail($request->new_landlord_id);
        if (!$newLandlord->isLandlord()) {
            return $this->jsonError('Selected user does not have landlord privileges.', 422);
        }

        // ✅ NEW: Duplicate prevention on transfer (matches web controller)
        $propertyData = [
            'property_name'   => $property->property_name,
            'street_name'     => $property->street_name,
            'house_number'    => $property->house_number,
            'digital_address' => $property->digital_address,
        ];

        if ($this->isDuplicateProperty($newLandlord->id, $propertyData)) {
            return response()->json([
                'success' => false,
                'message' => 'Property already exists for the new landlord.',
                'similar_properties' => $this->getSimilarProperties($newLandlord->id, $propertyData),
                'duplicate_prevention' => true,
            ], 409);
        }

        DB::beginTransaction();

        try {
            $oldLandlordId = $property->landlord_id;
            $oldLandlord = User::find($oldLandlordId);

            $property->update([
                'landlord_id' => $newLandlord->id,
                'metadata' => array_merge($property->metadata ?? [], [
                    'ownership_transferred' => [
                        'from_landlord_id'   => $oldLandlordId,
                        'from_landlord_name' => $oldLandlord?->name,
                        'to_landlord_id'     => $newLandlord->id,
                        'to_landlord_name'   => $newLandlord->name,
                        'transferred_at'     => now()->toISOString(),
                        'transferred_by'     => $user->id,
                        'transfer_reason'    => $request->transfer_reason,
                        'effective_date'     => $request->effective_date ?? now()->toDateString(),
                    ],
                ]),
            ]);

            Log::info('API: Property ownership transferred', [
                'property_id'     => $property->id,
                'old_landlord_id' => $oldLandlordId,
                'new_landlord_id' => $newLandlord->id,
                'transferred_by'  => $user->id,
            ]);

            DB::commit();

            return (new PropertyResource($property->fresh(['landlord'])))->additional([
                'message' => "Ownership transferred to {$newLandlord->name}.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Ownership transfer failed', ['error' => $e->getMessage()]);
            return $this->jsonError('Failed to transfer ownership.', 500);
        }
    }

    // =================================================================
    // HELPERS
    // =================================================================

    private function resolveLandlord(Request $request): int|\Illuminate\Http\JsonResponse
    {
        if ($request->filled('landlord_id')) {
            return (int) $request->landlord_id;
        }

        $phone = $request->input('landlord_phones.0');
        if (!$phone) {
            return $this->jsonError('Landlord phone number is required.', 422);
        }

        $existing = User::findByAnyPhoneFormat($phone);
        if ($existing) {
            if (!$existing->isLandlord()) {
                $existing->assignRole('landlord', [
                    'assigned_by' => $request->user()->id,
                    'assignment_reason' => 'Auto-assigned via API',
                    'assigned_at' => now(),
                ]);
            }
            return $existing->id;
        }

        $landlord = User::create([
            'name' => $request->landlord_name,
            'phone' => $phone,
            'email' => $request->landlord_email,
            'type' => User::TYPE_LANDLORD,
            'status' => User::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => Hash::make(Str::random(16)),
        ]);

        $landlord->assignRole('landlord', [
            'assigned_by' => $request->user()->id,
            'assignment_reason' => 'Created via API',
            'assigned_at' => now(),
        ]);

        return $landlord->id;
    }

    private function buildStats($properties): array
    {
        return [
            'total'              => $properties->count(),
            'active'             => $properties->where('status', 'active')->count(),
            'under_construction' => $properties->filter(fn ($p) =>
                $p->status === 'under_construction' || $p->construction_status === 'under_construction'
            )->count(),
            'vacant_land'        => $properties->filter(fn ($p) =>
                $p->status === 'vacant' || empty($p->status)
            )->count(),
            'vacant_land_with_plans' => $properties->filter(fn ($p) =>
                ($p->status === 'vacant' || empty($p->status)) &&
                ($p->has_plans === true ||
                 (is_array($p->construction_documents) && count($p->construction_documents) > 0))
            )->count(),
            'vacant_land_no_plans' => $properties->filter(fn ($p) =>
                ($p->status === 'vacant' || empty($p->status)) &&
                !($p->has_plans === true) &&
                (!is_array($p->construction_documents) || count($p->construction_documents) === 0)
            )->count(),
            'with_plans'         => $properties->filter(fn ($p) =>
                $p->has_plans === true ||
                (is_array($p->construction_documents) && count($p->construction_documents) > 0)
            )->count(),
            'with_digital_address' => $properties->whereNotNull('digital_address')->count(),
            'rented'               => $properties->where('is_rented', true)->count(),
        ];
    }

    /**
     * ✅ NEW: Has construction details? Matches web controller intent
     * but with correct operator precedence (the web version has a bug
     * where `||` binds too loosely).
     */
    private function hasConstructionDetails(Property $property): bool
    {
        return ($property->property_type_id !== null && $property->property_type_id != 0)
            || ($property->construction_status !== null && $property->construction_status !== 'vacant')
            || $property->has_plans === true
            || (is_array($property->construction_documents) && count($property->construction_documents) > 0);
    }

    /**
     * ✅ NEW: Vacant land with no construction details.
     */
    private function isVacantLand(Property $property): bool
    {
        return ($property->status === 'vacant' || empty($property->status))
            && !$this->hasConstructionDetails($property);
    }

    /**
     * ✅ NEW: Under construction detection.
     */
    private function isUnderConstruction(Property $property): bool
    {
        return $property->status === 'under_construction'
            || $property->construction_status === 'under_construction'
            || ($property->construction_status === 'active' && $this->hasConstructionDetails($property));
    }

    private function applyFilters($query, Request $request): void
    {
        $filters = [
            'street_name'          => 'like',
            'landlord_id'          => 'exact',
            'zone'                 => 'like',
            'status'               => 'exact',
            'digital_address'      => 'like',
            'registration_plan_id' => 'exact',
            'house_number'         => 'like',
            'block_number'         => 'like',
            'property_type_id'     => 'exact',
            'custom_property_type' => 'like',
        ];

        foreach ($filters as $field => $type) {
            if ($request->filled($field)) {
                $type === 'like'
                    ? $query->where($field, 'like', "%{$request->$field}%")
                    : $query->where($field, $request->$field);
            }
        }

        if ($request->filled('has_photos')) {
            $request->has_photos === '1' ? $query->has('photos') : $query->doesntHave('photos');
        }

        if ($request->filled('is_rented')) {
            $request->is_rented == '1' ? $query->whereHas('tenants') : $query->whereDoesntHave('tenants');
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            $request->has_digital_address === '1'
                ? $query->hasDigitalAddress()
                : $query->missingDigitalAddress();
        }
    }

    private function validateStore(Request $request)
    {
        return Validator::make($request->all(), [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'property_type_id'     => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name'        => 'required|string|max:255',
            'landlord_id'          => 'nullable|required_without_all:landlord_phones,landlord_name|exists:users,id',
            'landlord_phones'      => 'nullable|array|min:1',
            'landlord_phones.*'    => 'nullable|string|max:15',
            'landlord_name'        => 'nullable|required_without:landlord_id|string|max:255',
            'landlord_email'       => 'nullable|email|max:255',
            'house_number'         => 'nullable|string|max:50',
            'street_name'          => 'required|string|max:255',
            'block_number'         => 'nullable|string|max:50',
            'digital_address'      => 'nullable|string|max:255',
            'zone'                 => 'nullable|string|max:100',
            'section'              => 'nullable|string|max:100',
            'description'          => 'nullable|string',
            'status'               => 'sometimes|in:active,inactive,under_maintenance,vacant',
            'registration_date'    => 'required|date',
            'property_photos'      => 'nullable|array|max:10',
            'property_photos.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);
    }

    private function validateUpdate(Request $request)
    {
        return Validator::make($request->all(), [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'property_type_id'     => 'required|exists:property_types,id',
            'custom_property_type' => 'nullable|string|max:100',
            'property_name'        => 'required|string|max:255',
            'landlord_id'          => 'required|exists:users,id',
            'house_number'         => 'nullable|string|max:50',
            'street_name'          => 'required|string|max:255',
            'block_number'         => 'nullable|string|max:50',
            'digital_address'      => 'nullable|string|max:255',
            'zone'                 => 'nullable|string|max:100',
            'section'              => 'nullable|string|max:100',
            'description'          => 'nullable|string',
            'status'               => 'sometimes|in:active,inactive,under_maintenance,vacant',
            'construction_status'  => 'nullable|in:under_construction,active,vacant,inactive,completed,paused',
            'has_plans'            => 'nullable|boolean',
            'estimated_completion' => 'nullable|date',
            'construction_notes'   => 'nullable|string|max:1000',
            'property_photos'      => 'nullable|array|max:10',
            'property_photos.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'delete_photos'        => 'nullable|string',
            'primary_photo_id'     => 'nullable|exists:property_photos,id',
        ]);
    }

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

    private function getAllTenantsForProperty(Property $property)
    {
        $direct = $property->tenants;

        $unitIds = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'approved')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $pendingIds = \App\Models\PropertyUnit::where('property_id', $property->id)
            ->where('tenant_status', 'pending_approval')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $unitTenants = $unitIds->isNotEmpty() ? User::whereIn('id', $unitIds)->get() : collect();
        $pendingTenants = $pendingIds->isNotEmpty() ? User::whereIn('id', $pendingIds)->get() : collect();

        return $direct->merge($unitTenants)->merge($pendingTenants)->unique('id');
    }

    private function handlePhotoUploads(Request $request, Property $property): void
    {
        if (!$request->hasFile('property_photos')) return;

        foreach ($request->file('property_photos') as $file) {
            $isPrimary = $property->photos()->count() === 0;
            $property->addPhoto($file, $isPrimary);
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

        if (!empty($data['street_name']) && !empty($data['house_number']) &&
            Property::where('street_name', $data['street_name'])
                ->where('house_number', $data['house_number'])
                ->where('landlord_id', $landlordId)->exists()) {
            return true;
        }

        return false;
    }

    private function getSimilarProperties($landlordId, array $data): array
    {
        if (!$landlordId || empty($data['property_name'])) return [];

        $properties = Property::where('landlord_id', $landlordId)
            ->where('property_name', 'LIKE', '%' . $data['property_name'] . '%')
            ->limit(10)
            ->get();

        $similar = [];

        foreach ($properties as $property) {
            similar_text(
                strtolower($property->property_name),
                strtolower($data['property_name']),
                $score
            );

            if ($score > 60) {
                $similar[] = [
                    'id'             => $property->id,
                    'property_name'  => $property->property_name,
                    'digital_address' => $property->digital_address,
                    'street_name'    => $property->street_name,
                    'house_number'   => $property->house_number,
                    'similarity'     => round($score, 2),
                    'has_photo'      => $property->has_photos,
                ];
            }
        }

        usort($similar, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similar, 0, 5);
    }
}