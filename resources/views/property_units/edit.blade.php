{{-- property_units/edit.blade.php --}}
@php
    use App\Models\Property;
    use App\Models\PropertyUnit;

    $user = auth()->user();

    /* ============================================================
       FLAGS FROM THE CONTROLLER (with safe fallbacks so this view
       still renders if it's included from an older controller).
       ============================================================ */
    $isLandlord            = $isLandlord            ?? $user->isLandlord();
    $isAdmin               = $isAdmin               ?? $user->isAdmin();
    $isSuperAdmin          = $isSuperAdmin          ?? $user->isSuperAdmin();
    $isOnAdminDashboard    = $isOnAdminDashboard    ?? false;
    $isOnLandlordDashboard = $isOnLandlordDashboard ?? $isLandlord;

    $isOwner               = $isOwner               ?? ($isLandlord && $unit->property && (int) $unit->property->landlord_id === (int) $user->id);
    $isLinkedFamilyMember  = $isLinkedFamilyMember  ?? false;
    $hasManageUnitsAccess  = $hasManageUnitsAccess  ?? false;
    $routePrefix           = $routePrefix           ?? ($isLandlord ? 'landlord.property-units' : 'property-units');

    /* ------------------------------------------------------------
       PERMISSIONS
       ------------------------------------------------------------ */
    $canManageUnits = $isOwner || ($isLinkedFamilyMember && $hasManageUnitsAccess);
    $canDeleteUnit  = $isOwner;

    /* ------------------------------------------------------------
       LAYOUT
       ------------------------------------------------------------ */
    $layout = $isOnLandlordDashboard ? 'layouts.landlord' : 'layouts.app';

    /* ------------------------------------------------------------
       UNIT STATE
       ------------------------------------------------------------ */
    $isOccupied = $unit->status === PropertyUnit::STATUS_OCCUPIED;

    /* ============================================================
       PROPERTY DROPDOWN FOR EDIT
       ------------------------------------------------------------
       Owners: owned + linked (approved) properties.
       Linked-with-manage_units: linked properties they can manage.
       Always include the current property so it can stay selected,
       even if it's now ineligible (vacant, archived, etc.).
       ============================================================ */
    $currentPropertyId = $unit->property_id;

    $propertiesQuery = Property::query();

    if (!$isAdmin && !$isSuperAdmin) {
        // Everyone else scoped to what they can act on.
        $propertiesQuery->accessibleBy($user);
    }

    $propertiesQuery->where(function ($query) use ($currentPropertyId) {
        // Always include the current property.
        $query->where('id', $currentPropertyId)
              // Or active properties.
              ->orWhere('status', 'active')
              // Or under construction with construction details.
              ->orWhere(function ($q) {
                  $q->where('status', 'under_construction')
                    ->where(function ($sub) {
                        $sub->where(function ($t) {
                                $t->whereNotNull('property_type_id')
                                  ->where('property_type_id', '!=', 0);
                            })
                            ->orWhereNotNull('construction_status')
                            ->orWhere('has_plans', true)
                            ->orWhereNotNull('construction_documents');
                    });
              });
    });

    // Safety net: exclude vacant/null/empty except the current property.
    $propertiesQuery->where(function ($query) use ($currentPropertyId) {
        $query->where('id', $currentPropertyId)
              ->orWhere(function ($q) {
                  $q->where('status', '!=', 'vacant')
                    ->whereNotNull('status')
                    ->where('status', '!=', '');
              });
    });

    $properties = $propertiesQuery->orderBy('property_name')->get();

    // Belt-and-braces: force-include the current property if it slipped through.
    if (!$properties->contains('id', $currentPropertyId)) {
        $currentProperty = Property::find($currentPropertyId);
        if ($currentProperty) {
            $properties->push($currentProperty);
        }
    }

    /* ============================================================
       OPTION LISTS
       ============================================================ */
    $typeOptions = [
        'apartment'  => 'Apartment',
        'house'      => 'House',
        'townhouse'  => 'Townhouse',
        'studio'     => 'Studio',
        'condo'      => 'Condo',
        'duplex'     => 'Duplex',
        'bungalow'   => 'Bungalow',
        'office'     => 'Office Space',
        'commercial' => 'Commercial Space',
        'retail'     => 'Retail Space',
        'warehouse'  => 'Warehouse',
        'other'      => 'Other',
    ];

    $statusOptions = [
        'available'   => 'Available',
        'occupied'    => 'Occupied',
        'maintenance' => 'Under Maintenance',
        'renovation'  => 'Renovation',
        'pending'     => 'Pending',
    ];

    $amenityOptions = [
        'parking'            => 'Parking',
        'balcony'            => 'Balcony/Terrace',
        'air_conditioning'   => 'Air Conditioning',
        'furnished'          => 'Fully Furnished',
        'wifi'               => 'Wi-Fi Ready',
        'security'           => 'Security System',
        'gym'                => 'Gym/Fitness Center',
        'pool'               => 'Swimming Pool',
        'laundry'            => 'Laundry Facility',
        'elevator'           => 'Elevator',
        'generator'          => 'Generator Backup',
        'cctv'               => 'CCTV Surveillance',
        'fire_safety'        => 'Fire Safety System',
        'water_heater'       => 'Water Heater',
        'kitchen_appliances' => 'Kitchen Appliances',
        'garden'             => 'Garden/Yard',
    ];

    /* ============================================================
       PHOTO STATE (from controller, with safe fallbacks)
       ============================================================ */
    $photoMaxCount = $photoMaxCount ?? 15;
    $photoMaxKb    = $photoMaxKb    ?? 10240;
    $photoMimes    = $photoMimes    ?? 'jpg,jpeg,png,webp';
    $photoMaxMb    = round($photoMaxKb / 1024, 1);

    $unitPhotos       = $unitPhotos       ?? collect();
    $existingPhotoCnt = $unitPhotos->count();
    $remainingSlots   = max(0, $photoMaxCount - $existingPhotoCnt);

    // Photo AJAX endpoints (gated by manage_units in the controller).
    $photoUploadUrl     = $photoUploadUrl     ?? route('property-units.photos.store', $unit->id);
    $photoDeleteUrlBase = $photoDeleteUrlBase ?? route('property-units.photos.destroy', ['id' => $unit->id, 'photo' => '__ID__']);
    $photoCoverUrlBase  = $photoCoverUrlBase  ?? route('property-units.photos.cover',   ['id' => $unit->id, 'photo' => '__ID__']);
@endphp

@extends($layout)

@section('title', 'Edit Property Unit: ' . $unit->unit_number)

@section('content')
<div id="property-unit-edit-page" class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-2" style="color: var(--primary);"></i>
                        Edit Property Unit:
                        <span class="text-primary ml-1">{{ $unit->unit_number }}</span>
                        @if($unit->unit_name)
                            <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                                ({{ $unit->unit_name }})
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>Update property unit information</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <span>{{ $unit->property->property_name }}</span>

                        @if($isLinkedFamilyMember && !$isOwner)
                            <span class="mx-2">•</span>
                            <i class="fas fa-user-friends mr-1"></i>
                            <span>Linked property</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                <span>Last updated: {{ $unit->updated_at->format('M d, Y') }}</span>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FAMILY-MEMBER CONTEXT BANNER
         ============================================================ --}}
    @if($isLinkedFamilyMember && !$isOwner)
        <div class="card border-l-4"
             style="border-left-color: var(--info); background-color: rgba(var(--info-rgb), 0.05);">
            <div class="p-4 flex items-start">
                <i class="fas fa-user-friends text-xl mr-3 mt-0.5"
                   style="color: var(--info);"></i>
                <div class="flex-1">
                    <p class="font-medium" style="color: var(--text-primary);">
                        You're editing this unit as a linked family member.
                    </p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        @if($hasManageUnitsAccess)
                            Your link grants <strong>manage units</strong> access to this property.
                            Changes you save are recorded in the audit log for the property owner.
                        @else
                            You have read-only access to this unit. Contact the property owner if
                            you need to make changes.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         FLASH MESSAGES
         ============================================================ --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-bold">Success!</span>
                <span class="ml-2">{{ session('success') }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                    onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span class="font-bold">Error!</span>
                <span class="ml-2">{{ session('error') }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                    onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- ============================================================
         NAVIGATION
         ============================================================ --}}
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('property-units.show', $unit->id) }}"
                       class="inline-flex items-center text-sm font-medium"
                       style="color: var(--primary);">
                        <i class="fas fa-eye mr-2"></i> View Unit Details
                    </a>
                    <a href="{{ route('property-units.index') }}"
                       class="inline-flex items-center text-sm font-medium"
                       style="color: var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Units
                    </a>
                </div>
                <span class="text-xs px-3 py-1 rounded-full"
                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-asterisk mr-1" style="color: var(--danger);"></i> Required fields
                </span>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MAIN FORM
         ============================================================ --}}
    <div class="card">
        <div class="p-6">
            <form action="{{ route('property-units.update', $unit->id) }}" method="POST" id="editUnitForm">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- ============================================
                         LEFT COLUMN
                         ============================================ --}}
                    <div class="space-y-6">

                        {{-- Property Selection --}}
                        <div>
                            <label for="property_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property <span class="text-red-500">*</span>
                            </label>
                            <select name="property_id"
                                    id="property_id"
                                    class="custom-dropdown w-full"
                                    required
                                    {{ $isOccupied ? 'disabled' : '' }}>
                                <option value="">Select Property</option>
                                @foreach($properties as $property)
                                    @php
                                        $isCurrentProperty    = (int) $property->id === (int) $unit->property_id;
                                        $isVacant             = $property->status === 'vacant' || empty($property->status);
                                        $isUnderConstruction  = $property->status === 'under_construction';
                                        $hasDetails = ($property->property_type_id !== null && $property->property_type_id != 0)
                                            || $property->construction_status !== null
                                            || $property->has_plans === true
                                            || ($property->construction_documents && count($property->construction_documents) > 0);
                                        $isEligible = $property->status === 'active'
                                            || ($isUnderConstruction && $hasDetails)
                                            || $isCurrentProperty;
                                        $propertyIsLinked = $isLinkedFamilyMember
                                            && !((int) $property->landlord_id === (int) $user->id);
                                    @endphp
                                    <option value="{{ $property->id }}"
                                        {{ old('property_id', $unit->property_id) == $property->id ? 'selected' : '' }}
                                        data-property-status="{{ $property->status }}"
                                        data-is-vacant="{{ $isVacant ? 'true' : 'false' }}">
                                        {{ $property->property_name }}
                                        @if($isUnderConstruction)
                                            — Under Construction
                                        @endif
                                        @if($isCurrentProperty)
                                            — Current
                                        @endif
                                        @if(!$isEligible && !$isCurrentProperty)
                                            — Vacant Land
                                        @endif
                                        @if($propertyIsLinked)
                                            — family linked
                                        @endif
                                        @if($property->landlord)
                                            - {{ $property->landlord->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @if($isOccupied)
                                <input type="hidden" name="property_id" value="{{ $unit->property_id }}">
                            @endif

                            @error('property_id')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror

                            @if($isOccupied)
                                <p class="mt-1 text-xs flex items-center" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Cannot be changed for occupied units
                                </p>
                            @endif

                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Only active properties or properties under construction with plans can be selected.
                                Vacant land properties are not eligible.
                            </p>
                        </div>

                        {{-- Unit Number --}}
                        <div>
                            <label for="unit_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-hashtag mr-1"></i> Unit Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="unit_number"
                                   id="unit_number"
                                   class="custom-input w-full"
                                   value="{{ old('unit_number', $unit->unit_number) }}"
                                   placeholder="e.g., A1, B2, Ground Floor"
                                   required
                                   {{ $isOccupied ? 'readonly' : '' }}>
                            @error('unit_number')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                            @if($isOccupied)
                                <p class="mt-1 text-xs flex items-center" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Cannot be changed for occupied units
                                </p>
                            @endif
                        </div>

                        {{-- Unit Name --}}
                        <div>
                            <label for="unit_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-signature mr-1"></i> Unit Name (Optional)
                            </label>
                            <input type="text"
                                   name="unit_name"
                                   id="unit_name"
                                   class="custom-input w-full"
                                   value="{{ old('unit_name', $unit->unit_name) }}"
                                   placeholder="e.g., Master Suite, Penthouse">
                            @error('unit_name')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Unit Type --}}
                        <div>
                            <label for="unit_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Unit Type <span class="text-red-500">*</span>
                            </label>
                            <select name="unit_type"
                                    id="unit_type"
                                    class="custom-dropdown w-full"
                                    required
                                    {{ $isOccupied ? 'disabled' : '' }}>
                                <option value="">Select Unit Type</option>
                                @foreach($typeOptions as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('unit_type', $unit->unit_type) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            @if($isOccupied)
                                <input type="hidden" name="unit_type" value="{{ $unit->unit_type }}">
                            @endif

                            @error('unit_type')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror

                            @if($isOccupied)
                                <p class="mt-1 text-xs flex items-center" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Cannot be changed for occupied units
                                </p>
                            @endif
                        </div>

                        {{-- Specs Grid --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="floor_area" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-expand-arrows-alt mr-1"></i> Floor Area (sqm)
                                </label>
                                <input type="number"
                                       name="floor_area"
                                       id="floor_area"
                                       step="0.01"
                                       min="0"
                                       class="custom-input w-full"
                                       value="{{ old('floor_area', $unit->floor_area) }}"
                                       placeholder="e.g., 85.50">
                                @error('floor_area')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bedrooms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-bed mr-1"></i> Bedrooms
                                </label>
                                <input type="number"
                                       name="bedrooms"
                                       id="bedrooms"
                                       min="0"
                                       class="custom-input w-full"
                                       value="{{ old('bedrooms', $unit->bedrooms) }}">
                                @error('bedrooms')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bathrooms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-bath mr-1"></i> Bathrooms
                                </label>
                                <input type="number"
                                       name="bathrooms"
                                       id="bathrooms"
                                       min="0"
                                       class="custom-input w-full"
                                       value="{{ old('bathrooms', $unit->bathrooms) }}">
                                @error('bathrooms')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="living_rooms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-couch mr-1"></i> Living Rooms
                                </label>
                                <input type="number"
                                       name="living_rooms"
                                       id="living_rooms"
                                       min="0"
                                       class="custom-input w-full"
                                       value="{{ old('living_rooms', $unit->living_rooms) }}">
                                @error('living_rooms')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Kitchens --}}
                        <div>
                            <label for="kitchens" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-utensils mr-1"></i> Kitchens
                            </label>
                            <input type="number"
                                   name="kitchens"
                                   id="kitchens"
                                   min="0"
                                   class="custom-input w-full"
                                   value="{{ old('kitchens', $unit->kitchens) }}">
                            @error('kitchens')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ============================================
                         RIGHT COLUMN
                         ============================================ --}}
                    <div class="space-y-6">

                        {{-- Amenities --}}
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-concierge-bell mr-1"></i> Amenities
                                </label>
                                <button type="button"
                                        onclick="toggleAllAmenities()"
                                        class="px-2 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-check-double mr-1"></i> Toggle All
                                </button>
                            </div>
                            <div class="amenities-grid">
                                @foreach($amenityOptions as $value => $label)
                                    <label class="amenity-checkbox">
                                        <input type="checkbox"
                                               name="amenities[]"
                                               value="{{ $value }}"
                                               id="amenity_{{ $value }}"
                                               class="hidden"
                                               {{ in_array($value, old('amenities', $unit->amenities ?? [])) ? 'checked' : '' }}>
                                        <div class="amenity-content">
                                            <div class="amenity-icon">
                                                @switch($value)
                                                    @case('parking')            <i class="fas fa-parking"></i>            @break
                                                    @case('balcony')            <i class="fas fa-umbrella-beach"></i>    @break
                                                    @case('air_conditioning')   <i class="fas fa-snowflake"></i>         @break
                                                    @case('furnished')          <i class="fas fa-couch"></i>             @break
                                                    @case('wifi')               <i class="fas fa-wifi"></i>              @break
                                                    @case('security')           <i class="fas fa-shield-alt"></i>        @break
                                                    @case('gym')                <i class="fas fa-dumbbell"></i>          @break
                                                    @case('pool')               <i class="fas fa-swimming-pool"></i>     @break
                                                    @case('laundry')            <i class="fas fa-tshirt"></i>            @break
                                                    @case('elevator')           <i class="fas fa-elevator"></i>          @break
                                                    @case('generator')          <i class="fas fa-bolt"></i>              @break
                                                    @case('cctv')               <i class="fas fa-video"></i>             @break
                                                    @case('fire_safety')        <i class="fas fa-fire-extinguisher"></i> @break
                                                    @case('water_heater')       <i class="fas fa-shower"></i>            @break
                                                    @case('kitchen_appliances') <i class="fas fa-blender"></i>           @break
                                                    @default                    <i class="fas fa-star"></i>
                                                @endswitch
                                            </div>
                                            <span class="amenity-label">{{ $label }}</span>
                                            <div class="amenity-check">
                                                <i class="fas fa-check"></i>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('amenities')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-align-left mr-1"></i> Description
                            </label>
                            <textarea name="description"
                                      id="description"
                                      rows="4"
                                      class="custom-textarea w-full"
                                      placeholder="Describe the unit features, location within property, views, etc.">{{ old('description', $unit->description) }}</textarea>
                            <div class="mt-1 flex justify-between items-center">
                                <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> Max 1000 characters
                                </p>
                                <span id="charCount" class="text-xs" style="color: var(--text-secondary);">
                                    {{ strlen(old('description', $unit->description ?? '')) }}/1000
                                </span>
                            </div>
                            @error('description')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Financial Information --}}
                        <div class="space-y-4">
                            <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-money-bill-wave mr-2"></i> Financial Information
                            </h4>

                            <div>
                                <label for="monthly_rent" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-1"></i> Monthly Rent <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="monthly_rent"
                                       id="monthly_rent"
                                       step="0.01"
                                       min="0.01"
                                       class="custom-input w-full"
                                       value="{{ old('monthly_rent', $unit->monthly_rent) }}"
                                       required
                                       {{ $isOccupied ? 'readonly' : '' }}>
                                @error('monthly_rent')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                                @if($isOccupied)
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Cannot be changed for occupied units
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label for="security_deposit" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-shield-alt mr-1"></i> Security Deposit
                                </label>
                                <input type="number"
                                       name="security_deposit"
                                       id="security_deposit"
                                       step="0.01"
                                       min="0"
                                       class="custom-input w-full"
                                       value="{{ old('security_deposit', $unit->security_deposit) }}">
                                @error('security_deposit')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                    Typically 1-3 months rent
                                </p>
                            </div>
                        </div>

                        {{-- Status and Availability --}}
                        <div class="space-y-4">
                            <div>
                                <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-circle mr-1"></i> Status <span class="text-red-500">*</span>
                                </label>
                                <select name="status"
                                        id="status"
                                        class="custom-dropdown w-full"
                                        required
                                        onchange="updateAvailability()">
                                    <option value="">Select Status</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ old('status', $unit->status) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="available_from_section">
                                <label for="available_from" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-day mr-1"></i> Available From
                                </label>
                                <input type="date"
                                       name="available_from"
                                       id="available_from"
                                       class="custom-input w-full max-w-xs"
                                       value="{{ old('available_from', $unit->available_from ? $unit->available_from->format('Y-m-d') : '') }}">
                                @error('available_from')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center p-3 rounded-lg"
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                    <input type="checkbox"
                                           name="is_available"
                                           id="is_available"
                                           value="1"
                                           class="custom-checkbox mr-3"
                                           {{ old('is_available', $unit->is_available) ? 'checked' : '' }}>
                                    <div>
                                        <label for="is_available" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            Unit is Available for Rent
                                        </label>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Unit is currently available for new tenants
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center p-3 rounded-lg"
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <input type="checkbox"
                                           name="is_furnished"
                                           id="is_furnished"
                                           value="1"
                                           class="custom-checkbox mr-3"
                                           {{ old('is_furnished', $unit->is_furnished) ? 'checked' : '' }}>
                                    <div>
                                        <label for="is_furnished" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-couch mr-2"></i>
                                            Unit is Furnished
                                        </label>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Includes furniture, appliances, and necessary amenities
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Occupied unit notice --}}
                @if($isOccupied)
                    <div class="mt-6 p-4 rounded-lg border-l-4"
                         style="background-color: rgba(var(--warning-rgb), 0.05); border-left-color: var(--warning);">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-lg mt-1" style="color: var(--warning);"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium mb-2" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Important Notice
                                </h4>
                                <div class="text-sm space-y-2" style="color: var(--text-primary);">
                                    <p>
                                        This unit is currently occupied. Some fields cannot be modified to maintain data integrity.
                                    </p>
                                    <p>
                                        @if($isOwner)
                                            To make changes to occupied unit details, please vacate the tenant first.
                                        @else
                                            To make changes to occupied unit details, please contact the property owner
                                            to vacate the tenant first.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Form Actions --}}
                <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t"
                     style="border-color: var(--border-color);">
                    <div class="text-sm mb-4 md:mb-0 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-lightbulb mr-2"></i>
                        <span>Make sure all information is accurate before saving</span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('property-units.show', $unit->id) }}"
                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>

                        <button type="submit"
                                class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200"
                                style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                            <i class="fas fa-save mr-2"></i> Update Property Unit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================
         PHOTO MANAGEMENT
         ------------------------------------------------------------
         Photos are managed via AJAX (own routes) so they work even
         on occupied units, gated only by `manage_units`. The main
         form never touches photos.
         ============================================================ --}}
    @if($canManageUnits)
        <div class="card" id="unitPhotoManager">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-camera mr-2" style="color: var(--primary);"></i>
                            Unit Photos
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Add, remove, or reorder photos. The first photo is the cover.
                            Changes save immediately.
                        </p>
                    </div>
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-images mr-1"></i>
                        <span id="photoCountDisplay">{{ $existingPhotoCnt }}</span> / {{ $photoMaxCount }}
                    </span>
                </div>

                @if($isLinkedFamilyMember && !$isOwner)
                    <p class="text-xs mb-3" style="color: var(--info);">
                        <i class="fas fa-user-friends mr-1"></i>
                        Uploads here are attributed to you in the owner's audit log.
                    </p>
                @endif

                {{-- Existing + new photo grid --}}
                <div id="photoGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    @foreach($unitPhotos as $photo)
                        <div class="photo-tile relative rounded-lg overflow-hidden border group"
                             data-photo-id="{{ $photo['id'] }}"
                             style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <div class="w-full aspect-square bg-gray-100 overflow-hidden">
                                <img src="{{ $photo['thumb_url'] }}"
                                     alt="{{ $photo['caption'] ?? 'Unit photo' }}"
                                     class="w-full h-full object-cover">
                            </div>

                            @if(!empty($photo['is_cover']))
                                <span class="absolute top-1 left-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                      style="background-color: var(--success); color: white;">
                                    COVER
                                </span>
                            @endif

                            {{-- Remove --}}
                            <button type="button"
                                    class="photo-remove-btn absolute top-1 right-1 w-6 h-6 rounded-full flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity"
                                    style="background-color: rgba(220,53,69,0.9); color: white;"
                                    title="Remove photo"
                                    data-photo-id="{{ $photo['id'] }}">
                                <i class="fas fa-times text-xs"></i>
                            </button>

                            {{-- Set cover --}}
                            @if(empty($photo['is_cover']))
                                <button type="button"
                                        class="photo-cover-btn absolute bottom-1 right-1 w-6 h-6 rounded-full flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity"
                                        style="background-color: rgba(0,0,0,0.65); color: white;"
                                        title="Set as cover"
                                        data-photo-id="{{ $photo['id'] }}">
                                    <i class="fas fa-star text-xs"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Empty state --}}
                <p id="photoEmptyState"
                   class="text-sm text-center py-6 hidden"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-image mr-1"></i>
                    No photos yet. Add a few to help tenants see what they're renting.
                </p>

                {{-- Add new photos --}}
                @if($remainingSlots > 0)
                    <label for="editPhotosInput"
                           id="editPhotoDropZone"
                           class="block border-2 border-dashed rounded-xl p-6 text-center cursor-pointer mt-4 transition-all"
                           style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.02);">
                        <i class="fas fa-cloud-upload-alt text-2xl mb-1" style="color: var(--primary);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            Drag photos here or click to add
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            JPG, PNG, WEBP · Max {{ $photoMaxMb }} MB each · {{ $remainingSlots }} slot(s) remaining
                        </p>
                        <input id="editPhotosInput"
                               type="file"
                               multiple
                               accept="image/{{ str_replace(',', ',image/', $photoMimes) }}"
                               class="hidden">
                    </label>
                @else
                    <p class="text-xs mt-4" style="color: var(--warning);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Maximum of {{ $photoMaxCount }} photos reached. Remove one to add another.
                    </p>
                @endif

                {{-- Client-side error surface --}}
                <p id="photoClientError" class="mt-2 text-xs hidden" style="color: var(--danger);"></p>

                {{-- Pending queue (before upload) --}}
                <div id="photoPendingQueue" class="mt-4 space-y-2 hidden"></div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         DANGER ZONE — owner only.
         ============================================================ --}}
    @if($canDeleteUnit)
        <div class="card border-l-4"
             style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold flex items-center mb-2" style="color: var(--danger);">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Danger Zone
                        </h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            Once deleted, all data associated with this unit will be permanently removed.
                            This action cannot be undone.
                        </p>

                        @if($unit->tenant)
                            <div class="mb-4 p-3 rounded-lg"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                                    <div>
                                        <p class="text-sm font-medium" style="color: var(--warning);">
                                            Warning: Unit has assigned tenant
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Deleting this unit will also remove the tenant assignment for
                                            {{ $unit->tenant->name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <button type="button"
                            onclick="showDeleteModal()"
                            class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                            style="background-color: var(--danger); color: white; border: 2px solid var(--danger);">
                        <i class="fas fa-trash mr-2"></i> Delete Unit
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ============================================================
     DELETE MODAL
     ============================================================ --}}
@if($canDeleteUnit)
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeDeleteModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md"
                 style="border: 1px solid var(--border-color);">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4"
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-trash text-lg" style="color: var(--danger);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Delete Unit</h3>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $unit->unit_number }}</p>
                        </div>
                    </div>

                    <form action="{{ route('property-units.destroy', $unit->id) }}" method="POST" id="deleteForm">
                        @csrf
                        @method('DELETE')

                        <div class="mb-6">
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                Are you sure you want to delete this property unit?
                            </p>

                            @if($unit->tenant)
                                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-3" style="color: var(--warning);"></i>
                                        <div>
                                            <p class="text-sm font-medium" style="color: var(--warning);">Assigned Tenant</p>
                                            <p class="text-xs" style="color: var(--text-primary);">{{ $unit->tenant->name }}</p>
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                This tenant assignment will also be removed
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="p-3 rounded-lg"
                                 style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <p class="text-sm font-medium flex items-center" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    This action cannot be undone
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    All data associated with this unit will be permanently deleted
                                </p>
                            </div>
                        </div>

                        <div class="flex space-x-3">
                            <button type="button" onclick="closeDeleteModal()"
                                    class="flex-1 p-2 rounded-lg text-sm font-medium"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="flex-1 p-2 rounded-lg text-sm font-medium"
                                    style="background-color: var(--danger); color: white;">
                                Delete Unit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    initializeFormValidation();
    initAmenityCheckboxes();
    updateAvailability();
    initAvailabilityToggle();
    initDescriptionCounter();
    initAutoHideFlash();
    initAvailableFromDate();
    initPropertySelectWarning();

    // ============================================================
    // PHOTO MANAGER (only initialises if the section is rendered)
    // ============================================================
    initPhotoManager({
        uploadUrl:      @json($photoUploadUrl),
        deleteUrlBase:  @json($photoDeleteUrlBase),
        coverUrlBase:   @json($photoCoverUrlBase),
        maxCount:       @json($photoMaxCount),
        maxKb:          @json($photoMaxKb),
        mimesCsv:       @json($photoMimes),
        initialCount:   @json($existingPhotoCnt),
        csrf:           '{{ csrf_token() }}',
    });
});

/* ------------------------------------------------------------
   AMENITY CHECKBOXES
   ------------------------------------------------------------ */
function initAmenityCheckboxes() {
    document.querySelectorAll('.amenity-checkbox input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const content = this.closest('.amenity-checkbox').querySelector('.amenity-content');
            content.classList.toggle('checked', this.checked);
        });

        if (checkbox.checked) {
            checkbox.closest('.amenity-checkbox')
                .querySelector('.amenity-content')
                .classList.add('checked');
        }
    });
}

function toggleAllAmenities() {
    const amenityCheckboxes = document.querySelectorAll('.amenity-checkbox input[type="checkbox"]');
    const allChecked = Array.from(amenityCheckboxes).every(c => c.checked);
    amenityCheckboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        checkbox.dispatchEvent(new Event('change'));
    });
}

/* ------------------------------------------------------------
   AVAILABILITY TOGGLE
   ------------------------------------------------------------ */
function updateAvailability() {
    const statusSelect = document.getElementById('status');
    const isAvailableCheckbox = document.getElementById('is_available');
    const availableFromSection = document.getElementById('available_from_section');

    if (!statusSelect || !isAvailableCheckbox || !availableFromSection) return;

    const status = statusSelect.value;

    if (status === 'available') {
        isAvailableCheckbox.checked = true;
        isAvailableCheckbox.disabled = false;
        availableFromSection.style.display = 'block';
    } else if (status === 'occupied') {
        isAvailableCheckbox.checked = false;
        isAvailableCheckbox.disabled = true;
        availableFromSection.style.display = 'none';
    } else {
        isAvailableCheckbox.disabled = false;
        availableFromSection.style.display = isAvailableCheckbox.checked ? 'block' : 'none';
    }
}

function initAvailabilityToggle() {
    const isAvailableCheckbox = document.getElementById('is_available');
    const availableFromSection = document.getElementById('available_from_section');
    if (!isAvailableCheckbox || !availableFromSection) return;

    isAvailableCheckbox.addEventListener('change', function () {
        availableFromSection.style.display = this.checked ? 'block' : 'none';
    });

    availableFromSection.style.display = isAvailableCheckbox.checked ? 'block' : 'none';
}

/* ------------------------------------------------------------
   DESCRIPTION CHARACTER COUNTER
   ------------------------------------------------------------ */
function initDescriptionCounter() {
    const descriptionTextarea = document.getElementById('description');
    const charCountElement = document.getElementById('charCount');
    if (!descriptionTextarea || !charCountElement) return;

    const update = () => {
        const length = descriptionTextarea.value.length;
        charCountElement.textContent = `${length}/1000`;
        charCountElement.style.color =
            length > 1000 ? 'var(--danger)' :
            length > 900  ? 'var(--warning)' :
                            'var(--text-secondary)';
    };

    descriptionTextarea.addEventListener('input', update);
    update();
}

/* ------------------------------------------------------------
   FLASH AUTO-HIDE
   ------------------------------------------------------------ */
function initAutoHideFlash() {
    ['bg-green-100', 'bg-red-100'].forEach(cls => {
        const el = document.querySelector('.' + cls);
        if (el) {
            setTimeout(() => { el.style.display = 'none'; }, 5000);
        }
    });
}

/* ------------------------------------------------------------
   AVAILABLE-FROM MIN DATE
   ------------------------------------------------------------ */
function initAvailableFromDate() {
    const input = document.getElementById('available_from');
    if (!input) return;
    const today = new Date().toISOString().split('T')[0];
    input.min = today;
    if (!input.value) input.value = today;
}

/* ------------------------------------------------------------
   PROPERTY SELECT WARNING
   ------------------------------------------------------------ */
function initPropertySelectWarning() {
    const propertySelect = document.getElementById('property_id');
    if (!propertySelect) return;

    propertySelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (!option) return;

        const isVacant = option.getAttribute('data-is-vacant') === 'true';
        const isCurrent = option.textContent.includes('Current');

        if (isVacant && !isCurrent) {
            alert('Warning: You are selecting a vacant land property. This may cause issues with unit management.');
        }
    });
}

/* ------------------------------------------------------------
   FORM SUBMIT VALIDATION
   ------------------------------------------------------------ */
function initializeFormValidation() {
    const form = document.getElementById('editUnitForm');
    if (!form) return;

    const isOccupied = {{ $isOccupied ? 'true' : 'false' }};

    form.addEventListener('submit', function (e) {
        if (isOccupied) {
            const original = parseFloat('{{ $unit->monthly_rent }}');
            const current  = parseFloat(document.getElementById('monthly_rent').value);
            if (original !== current) {
                e.preventDefault();
                alert('Cannot change monthly rent for occupied units');
                return false;
            }
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            submitBtn.disabled = true;
        }
    });
}

/* ------------------------------------------------------------
   DELETE MODAL
   ------------------------------------------------------------ */
function showDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

document.getElementById('deleteModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDeleteModal();
});

/* ============================================================
   PHOTO MANAGER
   ------------------------------------------------------------
   Handles three independent AJAX operations, all gated server-
   side by `manage_units`:
     1. Upload new photos (append)    → property-units.photos.store
     2. Delete one photo              → property-units.photos.destroy
     3. Set a photo as cover          → property-units.photos.cover
   ============================================================ */
function initPhotoManager(opts) {
    const grid       = document.getElementById('photoGrid');
    const emptyState = document.getElementById('photoEmptyState');
    const input      = document.getElementById('editPhotosInput');
    const dropZone   = document.getElementById('editPhotoDropZone');
    const errEl      = document.getElementById('photoClientError');
    const countEl    = document.getElementById('photoCountDisplay');
    const queueEl    = document.getElementById('photoPendingQueue');

    if (!grid) return; // photo section not rendered for this user

    const allowedMimes = opts.mimesCsv.split(',').map(m => 'image/' + m.trim().toLowerCase());
    const maxBytes     = opts.maxKb * 1024;
    const maxCount     = Math.max(1, parseInt(opts.maxCount, 10) || 15);

    let currentCount   = parseInt(opts.initialCount, 10) || 0;

    function updateEmptyState() {
        if (!emptyState) return;
        emptyState.classList.toggle('hidden', currentCount > 0);
    }

    function updateCountDisplay() {
        if (countEl) countEl.textContent = currentCount;
        updateEmptyState();
    }

    function showError(msg) {
        if (!errEl) { alert(msg); return; }
        errEl.textContent = msg;
        errEl.classList.remove('hidden');
        setTimeout(() => errEl.classList.add('hidden'), 5000);
    }

    function refreshCountFromDom() {
        currentCount = grid.querySelectorAll('.photo-tile').length;
        updateCountDisplay();
    }

    updateCountDisplay();

    // ------------------------------------------------------------
    // ADD PENDING ITEM UI (before upload completes)
    // ------------------------------------------------------------
    function addPendingItem(file) {
        if (!queueEl) return null;
        queueEl.classList.remove('hidden');

        const row = document.createElement('div');
        row.className = 'flex items-center justify-between p-2 rounded-lg';
        row.style.cssText = 'background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);';
        row.innerHTML = `
            <div class="flex items-center min-w-0">
                <i class="fas fa-image mr-2" style="color: var(--info);"></i>
                <span class="text-xs truncate" style="color: var(--text-primary);">${file.name}</span>
            </div>
            <span class="text-xs ml-3" style="color: var(--text-secondary);">Uploading…</span>
        `;
        queueEl.appendChild(row);
        return row;
    }

    function removePendingItem(row) {
        if (!row) return;
        row.remove();
        if (queueEl && queueEl.children.length === 0) {
            queueEl.classList.add('hidden');
        }
    }

    // ------------------------------------------------------------
    // UPLOAD ONE FILE
    // ------------------------------------------------------------
    function uploadOne(file) {
        if (currentCount >= maxCount) {
            showError(`You can have up to ${maxCount} photos.`);
            return Promise.resolve(null);
        }
        if (!allowedMimes.includes(file.type)) {
            showError(`"${file.name}" is not a supported image type.`);
            return Promise.resolve(null);
        }
        if (file.size > maxBytes) {
            showError(`"${file.name}" exceeds the ${Math.round(maxBytes / 1024 / 1024)} MB limit.`);
            return Promise.resolve(null);
        }

        const pendingRow = addPendingItem(file);
        const fd = new FormData();
        fd.append('photos[]', file);
        fd.append('_token', opts.csrf);

        return fetch(opts.uploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': opts.csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
        })
            .then(async (res) => {
                removePendingItem(pendingRow);
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    const msg = data?.errors?.photos?.[0]
                        || data?.error
                        || `Upload failed (${res.status})`;
                    showError(msg);
                    return null;
                }
                const data = await res.json().catch(() => ({}));
                return data?.photos?.[0] ?? null;
            })
            .catch((err) => {
                removePendingItem(pendingRow);
                console.error('Upload error', err);
                showError('Network error while uploading.');
                return null;
            });
    }

    // ------------------------------------------------------------
    // RENDER NEW TILE FROM SERVER RESPONSE
    // ------------------------------------------------------------
    function renderNewTile(photo) {
        if (!photo) return;
        const tile = document.createElement('div');
        tile.className = 'photo-tile relative rounded-lg overflow-hidden border group';
        tile.dataset.photoId = photo.id;
        tile.style.cssText = 'border-color: var(--border-color); background-color: var(--card-bg);';

        tile.innerHTML = `
            <div class="w-full aspect-square bg-gray-100 overflow-hidden">
                <img src="${photo.thumb_url || photo.url}" alt="Unit photo" class="w-full h-full object-cover">
            </div>
            <button type="button"
                    class="photo-remove-btn absolute top-1 right-1 w-6 h-6 rounded-full flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity"
                    style="background-color: rgba(220,53,69,0.9); color: white;"
                    title="Remove photo"
                    data-photo-id="${photo.id}">
                <i class="fas fa-times text-xs"></i>
            </button>
            <button type="button"
                    class="photo-cover-btn absolute bottom-1 right-1 w-6 h-6 rounded-full flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity"
                    style="background-color: rgba(0,0,0,0.65); color: white;"
                    title="Set as cover"
                    data-photo-id="${photo.id}">
                <i class="fas fa-star text-xs"></i>
            </button>
        `;

        grid.appendChild(tile);
        currentCount++;
        updateCountDisplay();
        wireTileEvents(tile);
    }

    // ------------------------------------------------------------
    // TILE EVENT WIRING (remove / set-cover)
    // ------------------------------------------------------------
    function wireTileEvents(tile) {
        const removeBtn = tile.querySelector('.photo-remove-btn');
        const coverBtn  = tile.querySelector('.photo-cover-btn');

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const photoId = removeBtn.dataset.photoId;
                if (!photoId) return;
                if (!confirm('Remove this photo?')) return;

                const url = opts.deleteUrlBase.replace('__ID__', photoId);

                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': opts.csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(async (res) => {
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            showError(data?.error || 'Delete failed.');
                            return;
                        }
                        tile.remove();
                        refreshCountFromDom();
                    })
                    .catch(() => showError('Network error while deleting.'));
            });
        }

        if (coverBtn) {
            coverBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const photoId = coverBtn.dataset.photoId;
                if (!photoId) return;

                const url = opts.coverUrlBase.replace('__ID__', photoId);

                fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': opts.csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(async (res) => {
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            showError(data?.error || 'Failed to set cover.');
                            return;
                        }

                        // Update UI: clear COVER badges and cover buttons,
                        // then mark this tile as cover.
                        grid.querySelectorAll('.photo-tile').forEach(t => {
                            t.querySelector('.photo-cover-badge')?.remove();
                            const cb = t.querySelector('.photo-cover-btn');
                            if (cb) cb.remove();
                        });

                        const badge = document.createElement('span');
                        badge.className = 'photo-cover-badge absolute top-1 left-1 text-[10px] font-semibold px-2 py-0.5 rounded-full';
                        badge.style.cssText = 'background-color: var(--success); color: white;';
                        badge.textContent = 'COVER';
                        tile.appendChild(badge);

                        // Re-add a cover button to every OTHER tile.
                        grid.querySelectorAll('.photo-tile').forEach(t => {
                            if (t !== tile) addCoverButtonToTile(t);
                        });
                    })
                    .catch(() => showError('Network error while setting cover.'));
            });
        }
    }

    function addCoverButtonToTile(tile) {
        if (tile.querySelector('.photo-cover-btn')) return;
        const photoId = tile.dataset.photoId;
        if (!photoId) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'photo-cover-btn absolute bottom-1 right-1 w-6 h-6 rounded-full flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity';
        btn.style.cssText = 'background-color: rgba(0,0,0,0.65); color: white;';
        btn.title = 'Set as cover';
        btn.dataset.photoId = photoId;
        btn.innerHTML = '<i class="fas fa-star text-xs"></i>';
        tile.appendChild(btn);
        wireTileEvents(tile);
    }

    // Wire existing tiles from the server-rendered list.
    grid.querySelectorAll('.photo-tile').forEach(wireTileEvents);

    // ------------------------------------------------------------
    // FILE INPUT + DRAG-DROP
    // ------------------------------------------------------------
    if (input) {
        input.addEventListener('change', async function () {
            const files = Array.from(this.files || []);
            this.value = ''; // allow re-picking the same file
            for (const file of files) {
                const photo = await uploadOne(file);
                if (photo) renderNewTile(photo);
            }
        });
    }

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = 'var(--primary)';
                dropZone.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = 'var(--border-color)';
                dropZone.style.backgroundColor = 'rgba(var(--info-rgb), 0.02)';
            });
        });
        dropZone.addEventListener('drop', async (e) => {
            if (!e.dataTransfer || !e.dataTransfer.files) return;
            const files = Array.from(e.dataTransfer.files);
            for (const file of files) {
                const photo = await uploadOne(file);
                if (photo) renderNewTile(photo);
            }
        });
    }
}
</script>
@endsection

{{-- ============================================================
     STYLES — scoped so they don't leak into the layout
     ============================================================ --}}
@push('styles')
<style>
    .custom-dropdown {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        width: 100%;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }

    [data-theme="dark"] .custom-dropdown {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    }

    [data-theme="light"] .custom-dropdown {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    }

    .custom-dropdown:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    .custom-dropdown option {
        background-color: var(--card-bg);
        color: var(--text-primary);
    }

    [data-theme="dark"]  .custom-dropdown option { background-color: #2a2a3c; color: #e4e4e4; }
    [data-theme="light"] .custom-dropdown option { background-color: #ffffff; color: #4b4b4b; }

    .custom-input,
    .custom-textarea {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        width: 100%;
        transition: all 0.3s ease;
    }

    .custom-textarea { resize: vertical; }

    .custom-input:focus,
    .custom-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    .custom-input:disabled,
    .custom-input:read-only {
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    .custom-checkbox {
        width: 1rem;
        height: 1rem;
        border-radius: 0.25rem;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        cursor: pointer;
        transition: all 0.2s;
    }

    .custom-checkbox:checked  { background-color: var(--primary); border-color: var(--primary); }
    .custom-checkbox:disabled { opacity: 0.5; cursor: not-allowed; }

    .amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 8px;
        padding: 12px;
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
    }

    .amenity-checkbox { display: block; cursor: pointer; }

    .amenity-content {
        display: flex;
        align-items: center;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        transition: all 0.3s ease;
        position: relative;
        min-height: 50px;
    }

    .amenity-content:hover         { border-color: var(--primary); transform: translateY(-1px); }
    .amenity-content.checked       { border-color: var(--success); background-color: rgba(var(--success-rgb), 0.05); }

    .amenity-icon {
        width: 28px;
        height: 28px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        transition: all 0.3s ease;
    }

    .amenity-content.checked .amenity-icon  { background-color: var(--success); color: white; }

    .amenity-label {
        flex-grow: 1;
        font-size: 12px;
        font-weight: 500;
        color: var(--text-primary);
        transition: color 0.3s ease;
    }

    .amenity-check {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--border-color);
        color: transparent;
        transition: all 0.3s ease;
    }

    .amenity-content.checked .amenity-check { background-color: var(--success); color: white; }

    .amenities-grid::-webkit-scrollbar       { width: 6px; }
    .amenities-grid::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 3px; }
    .amenities-grid::-webkit-scrollbar-thumb { background: var(--primary);      border-radius: 3px; }
    .amenities-grid::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

    textarea::-webkit-scrollbar       { width: 6px; }
    textarea::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 3px; }
    textarea::-webkit-scrollbar-thumb { background: var(--primary);      border-radius: 3px; }
    textarea::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

    .text-red-500 { color: var(--danger) !important; }

    .custom-input::placeholder,
    .custom-textarea::placeholder { color: var(--text-secondary); opacity: 0.7; }

    .custom-dropdown:focus-visible,
    .custom-input:focus-visible,
    .custom-textarea:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

    .bg-green-100 { background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); }
    .bg-red-100   { background-color: rgba(var(--danger-rgb),  0.1); border-color: rgba(var(--danger-rgb),  0.3); }

    #deleteModal { z-index: 9999; }
    #deleteModal .fixed.inset-0.bg-black.bg-opacity-50 { backdrop-filter: blur(2px); }
    #deleteModal .bg-white.dark\:bg-gray-800 {
        background-color: var(--card-bg) !important;
        border: 1px solid var(--border-color);
    }

    .absolute.top-0.bottom-0.right-0.px-4.py-3 { cursor: pointer; opacity: 0.7; transition: opacity 0.2s; }
    .absolute.top-0.bottom-0.right-0.px-4.py-3:hover { opacity: 1; }

    /* Photo tile polish */
    .photo-tile { transition: box-shadow 0.2s ease, transform 0.2s ease; }
    .photo-tile:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.12); }

    @media (max-width: 768px) {
        .grid.grid-cols-1.lg\:grid-cols-2,
        .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }

        .p-6 { padding: 16px; }

        .flex.flex-col.md\:flex-row { flex-direction: column; gap: 12px; }

        .flex.space-x-3 { flex-wrap: wrap; gap: 8px; }
        .flex.space-x-3 > * { margin-bottom: 8px; }

        .amenities-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); max-height: 250px; }
    }

    @media (max-width: 640px) {
        .custom-input,
        .custom-dropdown,
        .custom-textarea { padding: 8px 10px; font-size: 13px; }

        .text-lg { font-size: 16px; }

        .amenities-grid { grid-template-columns: 1fr; }

        .amenity-content { padding: 8px; }
        .amenity-icon    { width: 24px; height: 24px; margin-right: 8px; }
        .amenity-label   { font-size: 11px; }
    }

    /* Scoped .hidden override so the modal doesn't leak display:none
       into the layout when hidden. */
    #property-unit-edit-page #deleteModal.hidden,
    #deleteModal.hidden {
        display: none !important;
    }
</style>
@endpush