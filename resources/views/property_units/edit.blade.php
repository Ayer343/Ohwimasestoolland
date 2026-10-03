{{-- property_units/edit.blade.php --}}
@php
    $layout = auth()->user()->isLandlord() ? 'layouts.landlord' : 'layouts.app';
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $isOccupied = $unit->status === \App\Models\PropertyUnit::STATUS_OCCUPIED;
    
    // ============================================
    // ⭐ BEST METHOD: FILTER PROPERTIES FOR EDIT
    // ============================================
    // Build the base query - include ALL properties initially
    $propertiesQuery = \App\Models\Property::query();
    
    // Filter by landlord if landlord is viewing
    if ($isLandlord) {
        $propertiesQuery->where('landlord_id', auth()->id());
    }
    
    // ⭐ CRITICAL: Include the current property even if vacant (for editing)
    // But exclude other vacant land properties
    $currentPropertyId = $unit->property_id;
    
    $propertiesQuery->where(function($query) use ($currentPropertyId) {
        // Include the current property (even if vacant)
        $query->where('id', $currentPropertyId)
              // OR include active properties
              ->orWhere('status', 'active')
              // OR include under construction with details
              ->orWhere(function($q) {
                  $q->where('status', 'under_construction')
                    ->where(function($sub) {
                        $sub->whereNotNull('property_type_id')
                            ->where('property_type_id', '!=', 0)
                            ->orWhereNotNull('construction_status')
                            ->orWhere('has_plans', true)
                            ->orWhereNotNull('construction_documents');
                    });
              });
    });
    
    // Safety net: exclude vacant properties (except current)
    $propertiesQuery->where(function($query) use ($currentPropertyId) {
        $query->where('id', $currentPropertyId)
              ->orWhere(function($q) {
                  $q->where('status', '!=', 'vacant')
                    ->whereNotNull('status')
                    ->where('status', '!=', '');
              });
    });
    
    // Get the filtered properties
    $properties = $propertiesQuery->orderBy('property_name')->get();
    
    // Verify the current property is in the list (should be)
    $currentPropertyInList = $properties->contains('id', $currentPropertyId);
    if (!$currentPropertyInList) {
        // Force add the current property if it was filtered out
        $currentProperty = \App\Models\Property::find($currentPropertyId);
        if ($currentProperty) {
            $properties->push($currentProperty);
        }
    }
    
    // Type options for dropdown
    $typeOptions = [
        'apartment' => 'Apartment',
        'house' => 'House',
        'townhouse' => 'Townhouse',
        'studio' => 'Studio',
        'condo' => 'Condo',
        'duplex' => 'Duplex',
        'bungalow' => 'Bungalow',
        'office' => 'Office Space',
        'commercial' => 'Commercial Space',
        'retail' => 'Retail Space',
        'warehouse' => 'Warehouse',
        'other' => 'Other',
    ];
    
    // Status options for dropdown
    $statusOptions = [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'maintenance' => 'Under Maintenance',
        'renovation' => 'Renovation',
        'pending' => 'Pending',
    ];
    
    // Amenities options
    $amenityOptions = [
        'parking' => 'Parking',
        'balcony' => 'Balcony/Terrace',
        'air_conditioning' => 'Air Conditioning',
        'furnished' => 'Fully Furnished',
        'wifi' => 'Wi-Fi Ready',
        'security' => 'Security System',
        'gym' => 'Gym/Fitness Center',
        'pool' => 'Swimming Pool',
        'laundry' => 'Laundry Facility',
        'elevator' => 'Elevator',
        'generator' => 'Generator Backup',
        'cctv' => 'CCTV Surveillance',
        'fire_safety' => 'Fire Safety System',
        'water_heater' => 'Water Heater',
        'kitchen_appliances' => 'Kitchen Appliances',
        'garden' => 'Garden/Yard',
    ];
@endphp

@extends($layout)

@section('title', 'Edit Property Unit: ' . $unit->unit_number)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-2" style="color: var(--primary);"></i> 
                        Edit Property Unit: <span class="text-primary ml-1">{{ $unit->unit_number }}</span>
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
                    </div>
                </div>
            </div>
            <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> 
                <span>Last updated: {{ $unit->updated_at->format('M d, Y') }}</span>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ session('success') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ session('error') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Navigation Card -->
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

    <!-- Main Form Card -->
    <div class="card">
        <div class="p-6">
            <form action="{{ route('property-units.update', $unit->id) }}" method="POST" id="editUnitForm">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Property Selection -->
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
                                    $isCurrentProperty = $property->id == $unit->property_id;
                                    $isVacant = $property->status === 'vacant' || empty($property->status);
                                    $isUnderConstruction = $property->status === 'under_construction';
                                    $hasDetails = ($property->property_type_id !== null && $property->property_type_id != 0) ||
                                                  $property->construction_status !== null ||
                                                  $property->has_plans === true ||
                                                  ($property->construction_documents && count($property->construction_documents) > 0);
                                    $isEligible = $property->status === 'active' || 
                                                  ($isUnderConstruction && $hasDetails) ||
                                                  $isCurrentProperty;
                                @endphp
                                <option value="{{ $property->id }}" 
                                    {{ old('property_id', $unit->property_id) == $property->id ? 'selected' : '' }}
                                    data-property-status="{{ $property->status }}"
                                    data-is-vacant="{{ $isVacant ? 'true' : 'false' }}">
                                    {{ $property->property_name }}
                                    @if($property->status === 'under_construction')
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                            <i class="fas fa-hard-hat mr-1"></i>Under Construction
                                        </span>
                                    @endif
                                    @if($isCurrentProperty)
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                            <i class="fas fa-check mr-1"></i>Current
                                        </span>
                                    @endif
                                    @if(!$isEligible && !$isCurrentProperty)
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                            <i class="fas fa-times mr-1"></i>Vacant Land
                                        </span>
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

                        <!-- Unit Number -->
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

                        <!-- Unit Name -->
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

                        <!-- Unit Type -->
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
                                <option value="{{ $value }}" {{ old('unit_type', $unit->unit_type) == $value ? 'selected' : '' }}>
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

                        <!-- Specifications Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Floor Area -->
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

                            <!-- Bedrooms -->
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

                            <!-- Bathrooms -->
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

                            <!-- Living Rooms -->
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

                        <!-- Kitchens -->
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

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Amenities -->
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
                                                @case('parking')
                                                    <i class="fas fa-parking"></i>
                                                    @break
                                                @case('balcony')
                                                    <i class="fas fa-umbrella-beach"></i>
                                                    @break
                                                @case('air_conditioning')
                                                    <i class="fas fa-snowflake"></i>
                                                    @break
                                                @case('furnished')
                                                    <i class="fas fa-couch"></i>
                                                    @break
                                                @case('wifi')
                                                    <i class="fas fa-wifi"></i>
                                                    @break
                                                @case('security')
                                                    <i class="fas fa-shield-alt"></i>
                                                    @break
                                                @case('gym')
                                                    <i class="fas fa-dumbbell"></i>
                                                    @break
                                                @case('pool')
                                                    <i class="fas fa-swimming-pool"></i>
                                                    @break
                                                @case('laundry')
                                                    <i class="fas fa-tshirt"></i>
                                                    @break
                                                @case('elevator')
                                                    <i class="fas fa-elevator"></i>
                                                    @break
                                                @case('generator')
                                                    <i class="fas fa-bolt"></i>
                                                    @break
                                                @case('cctv')
                                                    <i class="fas fa-video"></i>
                                                    @break
                                                @case('fire_safety')
                                                    <i class="fas fa-fire-extinguisher"></i>
                                                    @break
                                                @case('water_heater')
                                                    <i class="fas fa-shower"></i>
                                                    @break
                                                @case('kitchen_appliances')
                                                    <i class="fas fa-blender"></i>
                                                    @break
                                                @default
                                                    <i class="fas fa-star"></i>
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

                        <!-- Description -->
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
                                <span id="charCount" class="text-xs" style="color: var(--text-secondary);">{{ strlen(old('description', $unit->description)) }}/1000</span>
                            </div>
                            @error('description')
                            <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Financial Information -->
                        <div class="space-y-4">
                            <h4 class="text-md font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-money-bill-wave mr-2"></i> Financial Information
                            </h4>
                            
                            <!-- Monthly Rent -->
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

                            <!-- Security Deposit -->
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

                        <!-- Status and Availability -->
                        <div class="space-y-4">
                            <!-- Status -->
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
                                    <option value="{{ $value }}" {{ old('status', $unit->status) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('status')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Available From -->
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

                            <!-- Checkboxes -->
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

                <!-- Important Notice -->
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
                                    To make changes to occupied unit details, please contact an administrator or vacate the tenant first.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Form Actions -->
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

    <!-- Danger Zone (Admin Only) -->
    @if($isAdmin || $isSuperAdmin)
    <div class="card border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
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
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--warning);">Warning: Unit has assigned tenant</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Deleting this unit will also remove the tenant assignment for {{ $unit->tenant->name }}
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md" style="border: 1px solid var(--border-color);">
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
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">This tenant assignment will also be removed</p>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize amenity checkboxes
    document.querySelectorAll('.amenity-checkbox input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const content = this.closest('.amenity-checkbox').querySelector('.amenity-content');
            if (this.checked) {
                content.classList.add('checked');
            } else {
                content.classList.remove('checked');
            }
        });
        
        // Set initial checked state
        if (checkbox.checked) {
            checkbox.closest('.amenity-checkbox').querySelector('.amenity-content').classList.add('checked');
        }
    });
    
    // Update status based on availability
    updateAvailability();
    
    // Toggle available from section based on is_available checkbox
    const isAvailableCheckbox = document.getElementById('is_available');
    const availableFromSection = document.getElementById('available_from_section');
    
    if (isAvailableCheckbox && availableFromSection) {
        isAvailableCheckbox.addEventListener('change', function() {
            availableFromSection.style.display = this.checked ? 'block' : 'none';
        });
        
        // Set initial state
        availableFromSection.style.display = isAvailableCheckbox.checked ? 'block' : 'none';
    }
    
    // Character count for description
    const descriptionTextarea = document.getElementById('description');
    const charCountElement = document.getElementById('charCount');
    
    if (descriptionTextarea && charCountElement) {
        descriptionTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCountElement.textContent = `${length}/1000`;
            
            if (length > 1000) {
                charCountElement.style.color = 'var(--danger)';
            } else if (length > 900) {
                charCountElement.style.color = 'var(--warning)';
            } else {
                charCountElement.style.color = 'var(--text-secondary)';
            }
        });
    }
    
    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }
    
    // Set minimum date for available_from field
    const availableFromInput = document.getElementById('available_from');
    if (availableFromInput) {
        const today = new Date().toISOString().split('T')[0];
        availableFromInput.min = today;
        if (!availableFromInput.value) {
            availableFromInput.value = today;
        }
    }
    
    // Validate property selection on change
    const propertySelect = document.getElementById('property_id');
    if (propertySelect) {
        propertySelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const isVacant = selectedOption?.getAttribute('data-is-vacant') === 'true';
            
            if (isVacant) {
                // Show warning but allow selection (it's the current property)
                const isCurrent = selectedOption?.text?.includes('Current');
                if (!isCurrent) {
                    alert('Warning: You are selecting a vacant land property. This may cause issues with unit management.');
                }
            }
        });
    }
});

// Function to toggle all amenities
function toggleAllAmenities() {
    const amenityCheckboxes = document.querySelectorAll('.amenity-checkbox input[type="checkbox"]');
    const allChecked = Array.from(amenityCheckboxes).every(checkbox => checkbox.checked);
    
    amenityCheckboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        checkbox.dispatchEvent(new Event('change'));
    });
}

// Function to update availability section based on status
function updateAvailability() {
    const statusSelect = document.getElementById('status');
    const isAvailableCheckbox = document.getElementById('is_available');
    const availableFromSection = document.getElementById('available_from_section');
    
    if (statusSelect && isAvailableCheckbox && availableFromSection) {
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
}

// Initialize form validation
function initializeFormValidation() {
    const form = document.getElementById('editUnitForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Check for occupied unit restrictions
            const isOccupied = {{ $isOccupied ? 'true' : 'false' }};
            if (isOccupied) {
                const originalMonthlyRent = parseFloat('{{ $unit->monthly_rent }}');
                const newMonthlyRent = parseFloat(document.getElementById('monthly_rent').value);
                
                if (originalMonthlyRent !== newMonthlyRent) {
                    e.preventDefault();
                    alert('Cannot change monthly rent for occupied units');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            submitBtn.disabled = true;
        });
    }
}

// Delete modal functions
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

// Close modal when clicking outside
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<style>
/* Custom dropdown styles for dark/light mode */
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

/* Update SVG icon color for dark/light mode */
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

/* Dark mode dropdown options */
[data-theme="dark"] .custom-dropdown option {
    background-color: #2a2a3c;
    color: #e4e4e4;
}

/* Light mode dropdown options */
[data-theme="light"] .custom-dropdown option {
    background-color: #ffffff;
    color: #4b4b4b;
}

/* Custom input styles */
.custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.custom-input:focus {
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

/* Custom textarea styles */
.custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom checkbox styles */
.custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.custom-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Amenities Grid */
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

.amenity-checkbox {
    display: block;
    cursor: pointer;
}

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

.amenity-content:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
}

.amenity-content.checked {
    border-color: var(--success);
    background-color: rgba(var(--success-rgb), 0.05);
}

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

.amenity-content.checked .amenity-icon {
    background-color: var(--success);
    color: white;
}

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

.amenity-content.checked .amenity-check {
    background-color: var(--success);
    color: white;
}

/* Custom scrollbar for amenities grid */
.amenities-grid::-webkit-scrollbar {
    width: 6px;
}

.amenities-grid::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

.amenities-grid::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.amenities-grid::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Custom scrollbar for textareas */
textarea::-webkit-scrollbar {
    width: 6px;
}

textarea::-webkit-scrollbar-track {
    background: var(--bg-input);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Required field indicator */
.text-red-500 {
    color: var(--danger) !important;
}

/* Placeholder color for inputs */
.custom-input::placeholder,
.custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Focus states for better accessibility */
.custom-dropdown:focus-visible,
.custom-input:focus-visible,
.custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .p-6 {
        padding: 16px;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 12px;
    }
    
    .flex.space-x-3 {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .flex.space-x-3 > * {
        margin-bottom: 8px;
    }
    
    .amenities-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        max-height: 250px;
    }
}

@media (max-width: 640px) {
    .custom-input,
    .custom-dropdown,
    .custom-textarea {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .text-lg {
        font-size: 16px;
    }
    
    .amenities-grid {
        grid-template-columns: 1fr;
    }
    
    .amenity-content {
        padding: 8px;
    }
    
    .amenity-icon {
        width: 24px;
        height: 24px;
        margin-right: 8px;
    }
    
    .amenity-label {
        font-size: 11px;
    }
}

/* Message close button */
.absolute.top-0.bottom-0.right-0.px-4.py-3 {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.absolute.top-0.bottom-0.right-0.px-4.py-3:hover {
    opacity: 1;
}

/* Success and error message styles */
.bg-green-100 {
    background-color: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.3);
}

.bg-red-100 {
    background-color: rgba(var(--danger-rgb), 0.1);
    border-color: rgba(var(--danger-rgb), 0.3);
}

/* Modal styling */
#deleteModal {
    z-index: 9999;
}

#deleteModal .fixed.inset-0.bg-black.bg-opacity-50 {
    backdrop-filter: blur(2px);
}

#deleteModal .bg-white.dark\:bg-gray-800 {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color);
}

/* Currency input styling */
#monthly_rent,
#security_deposit {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

#monthly_rent:focus,
#security_deposit:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}
</style>
@endsection