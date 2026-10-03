{{-- property_units/create.blade.php --}}
@php
    // Dynamic title
    $pageTitle = 'Add New Property Unit';
    
    // Get any success/error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Determine user role and layout
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin();
    $isDeveloper = auth()->user()->isDeveloper();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    // Determine layout and route prefix
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
    } elseif ($isDeveloper) {
        $layout = 'layouts.app';
        $routePrefix = 'developer.property-units';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
    }
    
    // ============================================
    // ⭐ BEST METHOD: FILTER PROPERTIES
    // ============================================
    // Build the base query
    $propertiesQuery = \App\Models\Property::query();
    
    // Filter by landlord if landlord is viewing
    if ($isLandlord) {
        $propertiesQuery->where('landlord_id', auth()->id());
    }
    
    // ⭐ CRITICAL: Exclude vacant land properties using a scope or where clause
    // This ensures ONLY properties that can have units are shown
    $propertiesQuery->where(function($query) {
        // Include properties that are ACTIVE (regardless of construction details)
        $query->where('status', 'active')
              // OR properties that are UNDER CONSTRUCTION with construction details
              ->orWhere(function($q) {
                  $q->where('status', 'under_construction')
                    ->where(function($sub) {
                        // Must have at least ONE of these:
                        $sub->whereNotNull('property_type_id')
                            ->where('property_type_id', '!=', 0)
                            ->orWhereNotNull('construction_status')
                            ->orWhere('has_plans', true)
                            ->orWhereNotNull('construction_documents');
                    });
              });
    });
    
    // Also exclude any properties with status 'vacant' or null/empty (safety net)
    $propertiesQuery->where(function($query) {
        $query->where('status', '!=', 'vacant')
              ->whereNotNull('status')
              ->where('status', '!=', '');
    });
    
    // Get the filtered properties
    $properties = $propertiesQuery->orderBy('property_name')->get();
    
    // Get selected property details if provided
    $selectedPropertyDetails = null;
    $selectedPropertyId = request('property_id') ?? $selectedPropertyId ?? null;
    
    if ($selectedPropertyId) {
        $selectedPropertyDetails = \App\Models\Property::with(['landlord', 'units', 'propertyType'])
            ->find($selectedPropertyId);
        
        // Validate the selected property is eligible
        if ($selectedPropertyDetails) {
            $isValidProperty = $selectedPropertyDetails->status === 'active' ||
                              ($selectedPropertyDetails->status === 'under_construction' && 
                               ($selectedPropertyDetails->property_type_id !== null && $selectedPropertyDetails->property_type_id != 0) ||
                               $selectedPropertyDetails->construction_status !== null ||
                               $selectedPropertyDetails->has_plans === true ||
                               ($selectedPropertyDetails->construction_documents && count($selectedPropertyDetails->construction_documents) > 0));
            
            // If invalid, clear it and show error
            if (!$isValidProperty) {
                $selectedPropertyId = null;
                $selectedPropertyDetails = null;
                session()->flash('error', 'The selected property is vacant land and cannot have units. Please select an active property or a property under construction with plans.');
            }
        }
    }
    
    // Get existing units count for the selected property
    $existingUnitsCount = $selectedPropertyDetails ? $selectedPropertyDetails->units()->count() : 0;
    
    // Check if tenant data was passed from index page (Create & Assign button)
    $hasTenantData = request()->has('tenant_id');
    $tenantId = request('tenant_id');
    $tenantName = request('tenant_name');
    $tenantEmail = request('tenant_email');
    $tenantPhone = request('tenant_phone');
    $preferredMoveIn = request('preferred_move_in');
    $preferredRent = request('preferred_rent');
    
    // Default status for Create & Assign mode
    $defaultStatus = $hasTenantData ? 'occupied' : '';
    
    // Get available tenants for dropdown
    $availableTenants = collect();
    if (!$hasTenantData && ($isLandlord || $isAdmin)) {
        $assignedTenantIds = \App\Models\PropertyUnit::whereNotNull('tenant_id')
            ->whereIn('tenant_status', ['approved', 'pending_approval'])
            ->pluck('tenant_id')
            ->toArray();
        
        $availableTenants = \App\Models\User::where('type', 'tenant')
            ->when(!empty($assignedTenantIds), function($q) use ($assignedTenantIds) {
                $q->whereNotIn('id', $assignedTenantIds);
            })
            ->orderBy('name')
            ->get();
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

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-home text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-2" style="color: var(--primary);"></i> 
                        Add New Property Unit
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>Create a new property unit for rental management</span>
                        @if($selectedPropertyDetails)
                        <span class="mx-2">•</span>
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        <span>{{ $selectedPropertyDetails->property_name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> Step-by-step form
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if($errorMessage)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
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
                <a href="{{ route($routePrefix . '.index') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Property Units
                </a>
                <span class="text-xs px-3 py-1 rounded-full" 
                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-asterisk mr-1" style="color: var(--danger);"></i> Required fields
                </span>
            </div>
        </div>
    </div>

    <!-- Tenant Assignment Notification (Create & Assign Mode) -->
    @if($hasTenantData)
    <div class="card border-l-4" style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-user-check text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--success);"></i> 
                        Creating Unit for Pending Tenant Assignment
                    </h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        You're creating a unit for a pending tenant. After creating this unit, the tenant will be automatically assigned.
                        <strong class="block mt-2" style="color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Unit status will be automatically set to "Occupied" since you're assigning a tenant.
                        </strong>
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-lg" 
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Tenant Name</p>
                            <p class="font-semibold" style="color: var(--text-primary);">{{ $tenantName }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Email</p>
                            <p class="font-semibold" style="color: var(--text-primary);">{{ $tenantEmail }}</p>
                        </div>
                        @if($tenantPhone)
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Phone</p>
                            <p class="font-semibold" style="color: var(--text-primary);">{{ $tenantPhone }}</p>
                        </div>
                        @endif
                        @if($preferredMoveIn)
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Preferred Move-In</p>
                            <p class="font-semibold" style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($preferredMoveIn)->format('M d, Y') }}</p>
                        </div>
                        @endif
                        @if($preferredRent)
                        <div>
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Budget/Rent Range</p>
                            <p class="font-semibold" style="color: var(--text-primary);">{{ $preferredRent }}</p>
                        </div>
                        @endif
                    </div>
                    
                    <!-- Hidden inputs for tenant data -->
                    <input type="hidden" id="pending_tenant_id" value="{{ $tenantId }}">
                    <input type="hidden" id="pending_tenant_name" value="{{ $tenantName }}">
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Property Selection Card -->
    @if(!$selectedPropertyId || !$selectedPropertyDetails)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--info);"></i> 
                    Select Property
                </h3>
                <span class="text-sm px-3 py-1 rounded-full" 
                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-home mr-1"></i> {{ $properties->count() }} eligible properties
                </span>
            </div>
            
            @if($properties->isEmpty())
            <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mr-3 mt-0.5" style="color: var(--warning);"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">No eligible properties found</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            You need an active property or a property under construction with plans to add units.
                            @if($isLandlord)
                            <a href="{{ route('properties.create') }}" class="font-medium" style="color: var(--primary);">
                                Register a new property
                            </a>
                            @endif
                        </p>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Vacant land and vacant land under construction (without plans) cannot have units.
                        </p>
                    </div>
                </div>
            </div>
            @else
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Select an active property or a property under construction with plans. 
                Vacant land properties are not eligible for unit creation.
            </p>
            @endif
            
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="property_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-1"></i> Property <span class="text-red-500">*</span>
                    </label>
                    {{-- ✅ FIX: Removed name="property_id" from the select. The hidden input inside the form is now the source of truth. --}}
                    <select id="property_id" 
                            class="custom-dropdown w-full"
                            required
                            {{ $properties->isEmpty() ? 'disabled' : '' }}
                            onchange="updatePropertyInfo(this.value); validateField(this);">
                        <option value="">Select a Property</option>
                        @foreach($properties as $property)
                        @php
                            $propertyType = $property->propertyType;
                            $unitsCount = $property->units()->count();
                            $isUnderConstruction = $property->status === 'under_construction';
                        @endphp
                        <option value="{{ $property->id }}" 
                                {{ old('property_id', $selectedPropertyId) == $property->id ? 'selected' : '' }}
                                data-property-name="{{ $property->property_name }}"
                                data-property-type="{{ $propertyType ? $propertyType->name : ($property->custom_property_type ?? 'Not specified') }}"
                                data-property-address="{{ $property->digital_address }}"
                                data-property-landlord="{{ $property->landlord->name ?? 'Unknown' }}"
                                data-units-count="{{ $unitsCount }}"
                                data-property-status="{{ $property->status }}">
                            {{ $property->property_name }}
                            @if($isUnderConstruction)
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-hard-hat mr-1"></i>Under Construction
                                </span>
                            @endif
                            @if($property->landlord && ($isAdmin || $isDeveloper))
                                ({{ $property->landlord->name }})
                            @endif
                            @if($unitsCount > 0)
                                <span class="text-xs text-gray-400">({{ $unitsCount }} units)</span>
                            @endif
                        </option>
                        @endforeach
                    </select>
                    @error('property_id')
                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Info text about eligible properties -->
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <i class="fas fa-check-circle mr-1"></i> Active Properties
                        </span>
                        <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <i class="fas fa-hard-hat mr-1"></i> Under Construction (with plans)
                        </span>
                        <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <i class="fas fa-times-circle mr-1"></i> Vacant Land (excluded)
                        </span>
                    </div>
                </div>
                
                <div id="propertyInfoPreview" class="hidden p-4 rounded-lg" 
                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold" style="color: var(--text-primary);">Selected Property Preview</h4>
                        <i class="fas fa-building" style="color: var(--info);"></i>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Property Name</p>
                            <p id="previewPropertyName" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Property Type</p>
                            <p id="previewPropertyType" class="font-medium" style="color: var(--info);"></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Address</p>
                            <p id="previewPropertyAddress" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Landlord</p>
                            <p id="previewPropertyLandlord" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--info-rgb), 0.2);">
                        <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            This property has <span id="previewUnitsCount" class="font-semibold" style="color: var(--info);"></span> existing units.
                            <span id="previewPropertyStatus" class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Form -->
    <form action="{{ route('property-units.store') }}" method="POST" id="unitForm">
        @csrf
        
        {{-- ✅ FIX: Hidden input is now ALWAYS rendered. This guarantees property_id is submitted even when the user selects it client-side. --}}
        <input type="hidden" name="property_id" id="hidden_property_id" value="{{ $selectedPropertyId ?? '' }}">
        
        @if($hasTenantData)
        <input type="hidden" name="assign_tenant_id" value="{{ $tenantId }}">
        <input type="hidden" name="tenant_name" value="{{ $tenantName }}">
        <input type="hidden" name="tenant_email" value="{{ $tenantEmail }}">
        @endif

        <!-- Property Information Section -->
        @if($selectedPropertyId && $selectedPropertyDetails)
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i> 
                        Selected Property
                    </h3>
                    <div class="flex items-center space-x-2">
                        @if($properties->count() > 1)
                        <a href="{{ route($routePrefix . '.create', $hasTenantData ? [
                            'tenant_id' => $tenantId,
                            'tenant_name' => $tenantName,
                            'tenant_email' => $tenantEmail,
                            'tenant_phone' => $tenantPhone,
                            'preferred_move_in' => $preferredMoveIn,
                            'preferred_rent' => $preferredRent
                        ] : []) }}" 
                           class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-exchange-alt mr-1"></i> Change
                        </a>
                        @endif
                        <a href="{{ route('properties.show', $selectedPropertyId) }}" 
                           class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-external-link-alt mr-1"></i> View Details
                        </a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="space-y-4">
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property Name
                            </label>
                            <p class="font-semibold text-lg" style="color: var(--text-primary);">
                                {{ $selectedPropertyDetails->property_name }}
                            </p>
                            @if($selectedPropertyDetails->status === 'under_construction')
                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                <i class="fas fa-hard-hat mr-1"></i> Under Construction
                            </span>
                            @endif
                        </div>
                        
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property Type
                            </label>
                            <div class="flex items-center space-x-2 mt-2">
                                @if($selectedPropertyDetails->propertyType)
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--info-rgb), 0.1); border: 2px solid rgba(var(--info-rgb), 0.3);">
                                        @if($selectedPropertyDetails->propertyType->icon)
                                            <i class="{{ $selectedPropertyDetails->propertyType->icon }}" 
                                               style="color: var(--info);"></i>
                                        @else
                                            <i class="fas fa-tag" 
                                               style="color: var(--info);"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-semibold" style="color: var(--info);">
                                            {{ $selectedPropertyDetails->propertyType->name }}
                                        </p>
                                        @if($selectedPropertyDetails->custom_property_type)
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Custom: {{ $selectedPropertyDetails->custom_property_type }}
                                            </p>
                                        @endif
                                    </div>
                                @elseif($selectedPropertyDetails->custom_property_type)
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--info-rgb), 0.1); border: 2px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-tag" style="color: var(--info);"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold" style="color: var(--info);">
                                            {{ $selectedPropertyDetails->custom_property_type }}
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Custom property type
                                        </p>
                                    </div>
                                @else
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 2px solid rgba(var(--warning-rgb), 0.3);">
                                        <i class="fas fa-question-circle" style="color: var(--warning);"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold" style="color: var(--warning);">
                                            Not specified
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            No property type assigned
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider flex items-center" style="color: var(--text-secondary);">
                                <i class="fas fa-map-marker-alt mr-1"></i> Address
                            </label>
                            <div>
                                <p class="font-medium mb-1" style="color: var(--text-primary);">
                                    {{ $selectedPropertyDetails->digital_address ?? 'Digital Address not specified' }}
                                </p>
                                @if($selectedPropertyDetails->landmark)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Landmark: {{ $selectedPropertyDetails->landmark }}
                                </p>
                                @endif
                            </div>
                        </div>
                        
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Location Details
                            </label>
                            <div class="space-y-1">
                                @if($selectedPropertyDetails->zone)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    Zone: {{ $selectedPropertyDetails->zone }}
                                </p>
                                @endif
                                @if($selectedPropertyDetails->section)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    Section: {{ $selectedPropertyDetails->section }}
                                </p>
                                @endif
                                @if($selectedPropertyDetails->community)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    Community: {{ $selectedPropertyDetails->community }}
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property Statistics
                            </label>
                            <div class="grid grid-cols-2 gap-3 mt-2">
                                <div class="text-center p-2 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <p class="text-xl font-bold" style="color: var(--primary);">
                                        {{ $existingUnitsCount }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Existing Units</p>
                                </div>
                                
                                @php
                                    $availableUnits = $selectedPropertyDetails->units()->where('status', 'available')->count();
                                @endphp
                                <div class="text-center p-2 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                    <p class="text-xl font-bold" style="color: var(--success);">
                                        {{ $availableUnits }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Available</p>
                                </div>
                            </div>
                        </div>
                        
                        @if($selectedPropertyDetails->landlord)
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider flex items-center" style="color: var(--text-secondary);">
                                <i class="fas fa-user-tie mr-1"></i> Property Owner
                            </label>
                            <div class="flex items-center space-x-3 mt-2">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $selectedPropertyDetails->landlord->name }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $selectedPropertyDetails->landlord->email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Basic Information Section -->
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> 
                        Unit Information
                    </h3>
                    <span class="text-xs px-3 py-1 rounded-full badge-secondary">
                        <i class="fas fa-asterisk mr-1"></i> Required fields
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="unit_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-hashtag mr-1"></i> Unit Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="unit_number" 
                               id="unit_number" 
                               class="custom-input w-full"
                               value="{{ old('unit_number') }}"
                               required
                               placeholder="e.g., A-101, G-02, BLDG-3A"
                               maxlength="50"
                               oninput="generateUnitNameSuggestion(); validateField(this);">
                        @error('unit_number')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            Unique identifier for this unit
                        </p>
                    </div>

                    <div>
                        <label for="unit_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-signature mr-1"></i> Unit Name (Optional)
                            <button type="button" 
                                    onclick="generateUnitNameSuggestion()" 
                                    class="ml-2 text-xs px-2 py-0.5 rounded" 
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-magic mr-1"></i> Suggest
                            </button>
                        </label>
                        <input type="text" 
                               name="unit_name" 
                               id="unit_name" 
                               class="custom-input w-full"
                               value="{{ old('unit_name') }}"
                               placeholder="e.g., Garden View Apartment, Penthouse Suite"
                               maxlength="255">
                        @error('unit_name')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            Optional descriptive name
                        </p>
                    </div>

                    <div>
                        <label for="unit_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1"></i> Unit Type <span class="text-red-500">*</span>
                        </label>
                        <select name="unit_type" 
                                id="unit_type" 
                                class="custom-dropdown w-full"
                                required
                                onchange="validateField(this)">
                            <option value="">Select Unit Type</option>
                            @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" {{ old('unit_type') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                        @error('unit_type')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-circle mr-1"></i> Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" 
                                id="status" 
                                class="custom-dropdown w-full"
                                required
                                onchange="updateAvailability(); validateField(this);">
                            <option value="">Select Status</option>
                            @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" 
                                    {{ old('status', $defaultStatus) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                        @if($hasTenantData)
                        <p class="mt-1 text-xs flex items-center" style="color: var(--success);">
                            <i class="fas fa-info-circle mr-1"></i> 
                            Since you're assigning a tenant, status is automatically set to "Occupied".
                        </p>
                        @endif
                        @error('status')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-align-left mr-1"></i> Description (Optional)
                    </label>
                    <textarea name="description" 
                              id="description" 
                              class="custom-textarea w-full"
                              rows="3"
                              placeholder="Describe the unit's features, layout, view, or any special characteristics...">{{ old('description') }}</textarea>
                    @error('description')
                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    <div class="mt-1 flex justify-between items-center">
                        <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Max 1000 characters
                        </p>
                        <span id="charCount" class="text-xs" style="color: var(--text-secondary);">0/1000</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Specifications Section -->
        <div class="card mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-ruler-combined mr-2" style="color: var(--primary);"></i> 
                    Specifications
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="floor_area" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-expand-arrows-alt mr-1"></i> Floor Area (sqft)
                        </label>
                        <input type="number" 
                               name="floor_area" 
                               id="floor_area" 
                               class="custom-input w-full"
                               value="{{ old('floor_area') }}"
                               min="0"
                               step="0.01"
                               placeholder="e.g., 1200">
                        @error('floor_area')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="floor_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-layer-group mr-1"></i> Floor Number
                        </label>
                        <input type="number" 
                               name="floor_number" 
                               id="floor_number" 
                               class="custom-input w-full"
                               value="{{ old('floor_number') }}"
                               min="-10"
                               max="200"
                               placeholder="e.g., 3 (or -1 for basement)">
                        @error('floor_number')
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
                               class="custom-input w-full"
                               value="{{ old('bedrooms', 0) }}"
                               min="0"
                               max="20"
                               placeholder="0">
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
                               class="custom-input w-full"
                               value="{{ old('bathrooms', 0) }}"
                               min="0"
                               max="20"
                               step="0.5"
                               placeholder="0">
                        @error('bathrooms')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label for="living_rooms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-couch mr-1"></i> Living Rooms
                        </label>
                        <input type="number" 
                               name="living_rooms" 
                               id="living_rooms" 
                               class="custom-input w-full"
                               value="{{ old('living_rooms', 0) }}"
                               min="0"
                               max="10"
                               placeholder="0">
                        @error('living_rooms')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kitchens" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-utensils mr-1"></i> Kitchens
                        </label>
                        <input type="number" 
                               name="kitchens" 
                               id="kitchens" 
                               class="custom-input w-full"
                               value="{{ old('kitchens', 0) }}"
                               min="0"
                               max="10"
                               placeholder="0">
                        @error('kitchens')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Information Section -->
        <div class="card mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i> 
                    Financial Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="monthly_rent" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-1"></i> Monthly Rent <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 text-sm font-medium">GHS</span>
                            </div>
                            <input type="number" 
                                   name="monthly_rent" 
                                   id="monthly_rent" 
                                   class="custom-input w-full pl-12 pr-3"
                                   value="{{ old('monthly_rent', $preferredRent ? (float) filter_var($preferredRent, FILTER_SANITIZE_NUMBER_FLOAT) : '') }}"
                                   required
                                   min="0.01"
                                   step="0.01"
                                   placeholder="0.00"
                                   oninput="validateField(this)">
                        </div>
                        @error('monthly_rent')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        @if($preferredRent)
                        <p class="mt-1 text-xs" style="color: var(--success);">
                            <i class="fas fa-info-circle mr-1"></i> Suggested based on tenant's budget: {{ $preferredRent }}
                        </p>
                        @endif
                    </div>

                    <div>
                        <label for="security_deposit" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-shield-alt mr-1"></i> Security Deposit
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 text-sm font-medium">GHS</span>
                            </div>
                            <input type="number" 
                                   name="security_deposit" 
                                   id="security_deposit" 
                                   class="custom-input w-full pl-12 pr-3"
                                   value="{{ old('security_deposit') }}"
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00">
                        </div>
                        @error('security_deposit')
                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            Typically 1-3 months rent
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="flex items-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <input type="checkbox" 
                               name="is_furnished" 
                               id="is_furnished" 
                               value="1"
                               class="custom-checkbox mr-3"
                               {{ old('is_furnished') ? 'checked' : '' }}>
                        <div>
                            <label for="is_furnished" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-couch mr-2"></i> Unit is Fully Furnished
                            </label>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Includes furniture, appliances, and necessary amenities
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center p-3 rounded-lg" 
                         style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <input type="checkbox" 
                               name="is_available" 
                               id="is_available" 
                               value="1"
                               class="custom-checkbox mr-3"
                               {{ old('is_available', !$hasTenantData) ? 'checked' : '' }}
                               {{ $hasTenantData ? 'disabled' : '' }}>
                        <div>
                            <label for="is_available" class="text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-check-circle mr-2"></i> Available for Rent
                            </label>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Unit is currently available for new tenants
                            </p>
                            @if($hasTenantData)
                            <p class="text-xs mt-1" style="color: var(--warning);">
                                <i class="fas fa-info-circle mr-1"></i> Disabled because tenant is being assigned
                            </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6" id="available_from_section" style="{{ (!$hasTenantData && old('is_available', true)) ? '' : 'display: none;' }}">
                    <label for="available_from" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-day mr-1"></i> Available From Date
                    </label>
                    <input type="date" 
                           name="available_from" 
                           id="available_from" 
                           class="custom-input w-full max-w-xs"
                           value="{{ old('available_from', $preferredMoveIn ?? date('Y-m-d')) }}">
                    @error('available_from')
                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    @if($preferredMoveIn)
                    <p class="mt-1 text-xs" style="color: var(--success);">
                        <i class="fas fa-info-circle mr-1"></i> Tenant's preferred move-in date: {{ \Carbon\Carbon::parse($preferredMoveIn)->format('M d, Y') }}
                    </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Amenities Section -->
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-concierge-bell mr-2" style="color: var(--primary);"></i> 
                        Amenities & Features
                    </h3>
                    <button type="button" 
                            onclick="toggleAllAmenities()" 
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-check-double mr-1"></i> Toggle All
                    </button>
                </div>
                
                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                    Select all amenities and features available in this unit:
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($amenityOptions as $value => $label)
                    <label class="amenity-checkbox">
                        <input type="checkbox" 
                               name="amenities[]" 
                               value="{{ $value }}"
                               class="hidden"
                               {{ in_array($value, old('amenities', [])) ? 'checked' : '' }}>
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
                            <div class="amenity-details">
                                <span class="amenity-label">{{ $label }}</span>
                            </div>
                            <div class="amenity-check">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>
                
                <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                    <label for="custom_amenities" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-plus-circle mr-1"></i> Additional Features (Optional)
                    </label>
                    <textarea name="custom_amenities" 
                              id="custom_amenities" 
                              class="custom-textarea w-full"
                              rows="2"
                              placeholder="List any additional features not listed above, separated by commas...">{{ old('custom_amenities') }}</textarea>
                    @error('custom_amenities')
                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="p-6">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-lightbulb mr-1"></i> Review all information before submitting
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route($routePrefix . '.index', $hasTenantData ? ['tenant_id' => $tenantId] : []) }}" 
                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        
                        <button type="button" 
                                onclick="resetForm()" 
                                class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-redo mr-2"></i> Reset Form
                        </button>
                        
                        <button type="submit" 
                                class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                                style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                            <i class="fas fa-plus-circle mr-2"></i> 
                            @if($hasTenantData)
                                Create Unit & Assign Tenant
                            @else
                                Create Property Unit
                            @endif
                        </button>
                    </div>
                </div>
                
                <!-- Form Validation Summary -->
                <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <p class="text-sm font-medium flex items-center mb-2" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i> Form Validation Checklist
                    </p>
                    <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                        <li class="flex items-start validation-item" id="validation-property">
                            <i class="fas fa-times-circle text-xs mr-2 mt-1" style="color: var(--danger);"></i>
                            <span>Select a property for the unit</span>
                        </li>
                        <li class="flex items-start validation-item" id="validation-unit-number">
                            <i class="fas fa-times-circle text-xs mr-2 mt-1" style="color: var(--danger);"></i>
                            <span>Unit number is required and must be unique</span>
                        </li>
                        <li class="flex items-start validation-item" id="validation-unit-type">
                            <i class="fas fa-times-circle text-xs mr-2 mt-1" style="color: var(--danger);"></i>
                            <span>Select a unit type</span>
                        </li>
                        <li class="flex items-start validation-item" id="validation-status">
                            <i class="fas fa-times-circle text-xs mr-2 mt-1" style="color: var(--danger);"></i>
                            <span>Select unit status</span>
                        </li>
                        <li class="flex items-start validation-item" id="validation-rent">
                            <i class="fas fa-times-circle text-xs mr-2 mt-1" style="color: var(--danger);"></i>
                            <span>Enter valid monthly rent amount</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ✅ FIX: Sync hidden property_id from the select on page load
    (function syncHiddenPropertyOnLoad() {
        const hidden = document.getElementById('hidden_property_id');
        const select = document.getElementById('property_id');
        if (!hidden) return;
        if (hidden.value && hidden.value !== '') return; // already set from Blade
        if (select && select.value) {
            hidden.value = select.value;
        }
    })();

    // Initialize form validation
    initializeValidation();
    
    // Update availability section based on status
    updateAvailability();
    
    // Toggle available from section based on is_available checkbox
    const isAvailableCheckbox = document.getElementById('is_available');
    const availableFromSection = document.getElementById('available_from_section');
    
    if (isAvailableCheckbox && availableFromSection) {
        isAvailableCheckbox.addEventListener('change', function() {
            // When in tenant assignment mode, is_available should be unchecked
            @if($hasTenantData)
            if (this.checked) {
                this.checked = false;
                showToast('info', 'When assigning a tenant, the unit becomes occupied immediately.');
                return;
            }
            @endif
            availableFromSection.style.display = this.checked ? 'block' : 'none';
        });
        
        // Initialize available_from section visibility
        @if($hasTenantData)
        if (isAvailableCheckbox) {
            isAvailableCheckbox.checked = false;
            isAvailableCheckbox.disabled = true;
            if (availableFromSection) {
                availableFromSection.style.display = 'none';
            }
        }
        @else
        availableFromSection.style.display = isAvailableCheckbox.checked ? 'block' : 'none';
        @endif
    }
    
    // If in tenant assignment mode, disable is_available checkbox
    @if($hasTenantData)
    const isAvailableCheckboxElem = document.getElementById('is_available');
    if (isAvailableCheckboxElem) {
        isAvailableCheckboxElem.disabled = true;
        isAvailableCheckboxElem.checked = false;
    }
    @endif
    
    // Amenity checkbox styling
    document.querySelectorAll('.amenity-checkbox input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const content = this.closest('.amenity-checkbox').querySelector('.amenity-content');
            if (this.checked) {
                content.classList.add('checked');
            } else {
                content.classList.remove('checked');
            }
        });
        
        if (checkbox.checked) {
            checkbox.closest('.amenity-checkbox').querySelector('.amenity-content').classList.add('checked');
        }
    });
    
    // Real-time form validation event listeners
    const unitNumberField = document.getElementById('unit_number');
    if (unitNumberField) {
        unitNumberField.addEventListener('input', function() { validateField(this); });
        unitNumberField.addEventListener('blur', function() { validateField(this); });
    }
    
    const unitTypeField = document.getElementById('unit_type');
    if (unitTypeField) {
        unitTypeField.addEventListener('change', function() { validateField(this); });
    }
    
    const statusField = document.getElementById('status');
    if (statusField) {
        statusField.addEventListener('change', function() { validateField(this); });
    }
    
    const rentField = document.getElementById('monthly_rent');
    if (rentField) {
        rentField.addEventListener('input', function() { validateField(this); });
        rentField.addEventListener('blur', function() { validateField(this); });
    }
    
    const propertySelect = document.getElementById('property_id');
    if (propertySelect) {
        propertySelect.addEventListener('change', function() {
            validateField(this);
            updatePropertyInfo(this.value);
        });
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
        
        const initialLength = descriptionTextarea.value.length;
        charCountElement.textContent = `${initialLength}/1000`;
    }
    
    // Auto-format currency inputs
    document.querySelectorAll('input[type="number"][id*="rent"], input[type="number"][id*="deposit"]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
    
    // Calculate deposit suggestion
    const monthlyRentInput = document.getElementById('monthly_rent');
    const securityDepositInput = document.getElementById('security_deposit');
    
    if (monthlyRentInput && securityDepositInput) {
        monthlyRentInput.addEventListener('blur', function() {
            if (this.value && !securityDepositInput.value) {
                const suggestedDeposit = parseFloat(this.value) * 2;
                
                const tooltip = document.createElement('div');
                tooltip.className = 'deposit-suggestion';
                tooltip.innerHTML = `
                    <i class="fas fa-lightbulb mr-1"></i>
                    Suggested deposit: GHS ${suggestedDeposit.toFixed(2)} (2 months rent)
                    <button type="button" onclick="applySuggestedDeposit(${suggestedDeposit})" class="ml-2 text-xs underline" style="color: var(--primary);">
                        Apply
                    </button>
                `;
                tooltip.style.cssText = `
                    margin-top: 4px;
                    padding: 8px;
                    background-color: rgba(var(--info-rgb), 0.1);
                    border: 1px solid rgba(var(--info-rgb), 0.2);
                    border-radius: 6px;
                    font-size: 12px;
                    color: var(--info);
                `;
                
                const existingTooltip = securityDepositInput.parentNode.querySelector('.deposit-suggestion');
                if (existingTooltip) existingTooltip.remove();
                
                securityDepositInput.parentNode.appendChild(tooltip);
            }
        });
    }
    
    // Auto-hide messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => { successMessage.style.display = 'none'; }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
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
    
    // Show tenant data notification if present
    @if($hasTenantData)
    showTenantNotification();
    @endif
});

// Toast notification function
function showToast(type, message) {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.cssText = `
        padding: 12px 16px;
        margin-bottom: 10px;
        border-radius: 12px;
        font-size: 14px;
        animation: slideInRight 0.3s ease-out;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        background-color: rgba(var(--info-rgb), 0.2);
        border-color: rgba(var(--info-rgb), 0.4);
        color: var(--info);
    `;
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-lg"></i>
            <span>${message}</span>
        </div>
        <button type="button" class="ml-4 opacity-70 hover:opacity-100 transition-opacity" style="background: none; border: none; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => {
        toast.remove();
    });
    
    setTimeout(() => {
        if (toast.parentNode) toast.remove();
    }, 5000);
    
    toastContainer.appendChild(toast);
}

// Function to show tenant data notification
function showTenantNotification() {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 z-50 shadow-lg animate-slide-in';
    notification.style.cssText = `
        background-color: rgba(var(--success-rgb), 0.95);
        border: 1px solid rgba(var(--success-rgb), 0.3);
        color: white;
        padding: 12px 16px;
        border-radius: 12px;
        z-index: 9999;
        max-width: 350px;
    `;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-user-check mr-2 text-white text-lg"></i>
            <div>
                <span class="font-bold block">Create & Assign Mode Active</span>
                <span class="text-sm opacity-90">Unit will be created with status "Occupied" and tenant will be assigned.</span>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.body.appendChild(notification);
    setTimeout(() => { notification.remove(); }, 8000);
}

// Validate a single field - handles all field types correctly
function validateField(field) {
    if (!field) return false;
    
    // Map field ID to validation item ID
    let validationId = field.id;
    if (field.id === 'property_id') {
        validationId = 'property';
    } else if (field.id === 'unit_type') {
        validationId = 'unit-type';
    } else if (field.id === 'monthly_rent') {
        validationId = 'rent';
    } else if (field.id === 'unit_number') {
        validationId = 'unit-number';
    }
    
    const validationItem = document.getElementById(`validation-${validationId}`);
    if (!validationItem) return true;
    
    const icon = validationItem.querySelector('i');
    const textSpan = validationItem.querySelector('span');
    
    let isValid = false;
    let message = '';
    
    switch(field.id) {
        case 'property_id':
            // ✅ FIX: Read from the hidden input (source of truth) if available
            const hiddenProp = document.getElementById('hidden_property_id');
            let propertyValue = '';
            if (hiddenProp && hiddenProp.value) {
                propertyValue = hiddenProp.value;
            } else if (field.tagName === 'SELECT') {
                const selectedOption = field.options[field.selectedIndex];
                propertyValue = selectedOption ? selectedOption.value : '';
            } else {
                propertyValue = field.value;
            }
            isValid = propertyValue && propertyValue.trim().length > 0;
            message = isValid ? '✓ Property selected' : 'Select a property';
            break;
            
        case 'unit_number':
            isValid = field.value && field.value.trim().length > 0;
            message = isValid ? `✓ Unit number: ${field.value.trim()}` : 'Unit number is required';
            break;
            
        case 'unit_type':
            isValid = field.value && field.value.trim().length > 0;
            const selectedText = field.options[field.selectedIndex]?.text || '';
            message = isValid ? `✓ ${selectedText} selected` : 'Select a unit type';
            break;
            
        case 'status':
            isValid = field.value && field.value.trim().length > 0;
            const statusText = field.options[field.selectedIndex]?.text || '';
            message = isValid ? `✓ ${statusText}` : 'Select unit status';
            break;
            
        case 'monthly_rent':
            const rentValue = parseFloat(field.value);
            isValid = !isNaN(rentValue) && rentValue > 0;
            message = isValid ? `✓ GHS ${rentValue.toFixed(2)}` : 'Enter valid monthly rent';
            break;
            
        default:
            return true;
    }
    
    // Update validation item UI
    if (isValid) {
        icon.className = 'fas fa-check-circle text-xs mr-2 mt-1';
        icon.style.color = 'var(--success)';
        if (textSpan) {
            textSpan.style.color = 'var(--success)';
            textSpan.innerHTML = message;
        }
        field.style.borderColor = 'var(--success)';
        field.style.backgroundColor = 'rgba(var(--success-rgb), 0.05)';
    } else {
        icon.className = 'fas fa-times-circle text-xs mr-2 mt-1';
        icon.style.color = 'var(--danger)';
        if (textSpan) {
            textSpan.style.color = 'var(--danger)';
            textSpan.innerHTML = message;
        }
        field.style.borderColor = 'var(--danger)';
        field.style.backgroundColor = 'rgba(var(--danger-rgb), 0.02)';
    }
    
    return isValid;
}

// Validate hidden property input (when property is pre-selected)
function validateHiddenProperty() {
    const validationItem = document.getElementById('validation-property');
    if (!validationItem) return true;
    
    const icon = validationItem.querySelector('i');
    const textSpan = validationItem.querySelector('span');
    
    // ✅ FIX: Prefer the hidden input as the source of truth
    const hiddenProperty = document.getElementById('hidden_property_id');
    const isValid = hiddenProperty && hiddenProperty.value;
    
    if (isValid) {
        icon.className = 'fas fa-check-circle text-xs mr-2 mt-1';
        icon.style.color = 'var(--success)';
        if (textSpan) {
            textSpan.style.color = 'var(--success)';
            textSpan.innerHTML = '✓ Property selected';
        }
    } else {
        icon.className = 'fas fa-times-circle text-xs mr-2 mt-1';
        icon.style.color = 'var(--danger)';
        if (textSpan) {
            textSpan.style.color = 'var(--danger)';
            textSpan.innerHTML = 'Select a property';
        }
    }
    
    return isValid;
}

// Initialize all validations
function initializeValidation() {
    // Set initial icons to danger state
    document.querySelectorAll('.validation-item i').forEach(icon => {
        icon.className = 'fas fa-times-circle text-xs mr-2 mt-1';
        icon.style.color = 'var(--danger)';
    });
    
    // Validate property (handle both select and hidden)
    const propertySelect = document.getElementById('property_id');
    if (propertySelect) {
        validateField(propertySelect);
    } else {
        validateHiddenProperty();
    }
    
    // Validate other fields
    const unitNumberField = document.getElementById('unit_number');
    if (unitNumberField) validateField(unitNumberField);
    
    const unitTypeField = document.getElementById('unit_type');
    if (unitTypeField) validateField(unitTypeField);
    
    const statusField = document.getElementById('status');
    if (statusField) validateField(statusField);
    
    const rentField = document.getElementById('monthly_rent');
    if (rentField) validateField(rentField);
}

// Update property info preview
function updatePropertyInfo(propertyId) {
    const propertySelect = document.getElementById('property_id');
    const previewContainer = document.getElementById('propertyInfoPreview');
    const hiddenPropertyInput = document.getElementById('hidden_property_id');

    // ✅ FIX: Sync the hidden input that actually gets submitted
    if (hiddenPropertyInput) {
        hiddenPropertyInput.value = propertyId || '';
    }

    if (!propertySelect || !previewContainer) return;
    
    const selectedOption = propertySelect.options[propertySelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        previewContainer.classList.remove('hidden');
        document.getElementById('previewPropertyName').textContent = selectedOption.getAttribute('data-property-name') || 'Not specified';
        document.getElementById('previewPropertyType').textContent = selectedOption.getAttribute('data-property-type') || 'Not specified';
        document.getElementById('previewPropertyAddress').textContent = selectedOption.getAttribute('data-property-address') || 'Not specified';
        document.getElementById('previewPropertyLandlord').textContent = selectedOption.getAttribute('data-property-landlord') || 'Not specified';
        document.getElementById('previewUnitsCount').textContent = selectedOption.getAttribute('data-units-count') || '0';
        
        // Show property status
        const statusEl = document.getElementById('previewPropertyStatus');
        const status = selectedOption.getAttribute('data-property-status');
        if (statusEl && status) {
            statusEl.textContent = status === 'under_construction' ? 'Under Construction' : status.charAt(0).toUpperCase() + status.slice(1);
            statusEl.style.backgroundColor = status === 'under_construction' ? 'rgba(var(--warning-rgb), 0.2)' : 'rgba(var(--success-rgb), 0.2)';
            statusEl.style.color = status === 'under_construction' ? 'var(--warning)' : 'var(--success)';
        }
    } else {
        previewContainer.classList.add('hidden');
    }
}

// Generate unit name suggestion
function generateUnitNameSuggestion() {
    const unitNumberInput = document.getElementById('unit_number');
    const unitTypeSelect = document.getElementById('unit_type');
    const unitNameInput = document.getElementById('unit_name');
    
    if (!unitNumberInput || !unitTypeSelect || !unitNameInput) return;
    
    const unitNumber = unitNumberInput.value.trim();
    const unitType = unitTypeSelect.options[unitTypeSelect.selectedIndex]?.text;
    
    if (unitNumber && unitType && unitType !== 'Select Unit Type') {
        const suggestions = [
            `${unitNumber} ${unitType}`,
            `Unit ${unitNumber}`,
            `${unitType} ${unitNumber}`,
            `${getRandomDescriptor()} ${unitType} ${unitNumber}`
        ];
        
        if (!unitNameInput.value || unitNameInput.value === unitNumber) {
            unitNameInput.value = suggestions[0];
        }
    }
}

function getRandomDescriptor() {
    const descriptors = ['Luxury', 'Modern', 'Spacious', 'Cozy', 'Bright', 'Premium', 'Executive', 'Family', 'Studio', 'Deluxe', 'Standard', 'Superior'];
    return descriptors[Math.floor(Math.random() * descriptors.length)];
}

// Toggle all amenities
function toggleAllAmenities() {
    const amenityCheckboxes = document.querySelectorAll('.amenity-checkbox input[type="checkbox"]');
    const allChecked = Array.from(amenityCheckboxes).every(checkbox => checkbox.checked);
    amenityCheckboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        checkbox.dispatchEvent(new Event('change'));
    });
}

// Apply suggested deposit
window.applySuggestedDeposit = function(amount) {
    const securityDepositInput = document.getElementById('security_deposit');
    if (securityDepositInput) {
        securityDepositInput.value = amount.toFixed(2);
        const tooltip = securityDepositInput.parentNode.querySelector('.deposit-suggestion');
        if (tooltip) tooltip.remove();
        validateField(securityDepositInput);
    }
};

// Update availability section based on status
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

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('unitForm').reset();
        updateAvailability();
        
        // ✅ FIX: Clear the hidden property_id on reset unless a Blade-provided default exists
        const hiddenProperty = document.getElementById('hidden_property_id');
        if (hiddenProperty) {
            hiddenProperty.value = @json($selectedPropertyId ?? '');
        }
        
        const previewContainer = document.getElementById('propertyInfoPreview');
        if (previewContainer) previewContainer.classList.add('hidden');
        
        document.querySelectorAll('.validation-item').forEach(item => {
            const icon = item.querySelector('i');
            const textSpan = item.querySelector('span');
            icon.className = 'fas fa-times-circle text-xs mr-2 mt-1';
            icon.style.color = 'var(--danger)';
            if (textSpan) {
                textSpan.style.color = 'var(--text-secondary)';
                const originalText = textSpan.innerHTML.replace('✓ ', '');
                textSpan.innerHTML = originalText;
            }
        });
        
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.style.borderColor = 'var(--border-color)';
            field.style.backgroundColor = '';
        });
        
        document.querySelectorAll('.amenity-content').forEach(content => {
            content.classList.remove('checked');
        });
        
        const charCountElement = document.getElementById('charCount');
        if (charCountElement) {
            charCountElement.textContent = '0/1000';
            charCountElement.style.color = 'var(--text-secondary)';
        }
        
        // Re-validate after reset
        initializeValidation();
        
        // For tenant assignment mode, re-enforce occupied status
        @if($hasTenantData)
        const statusField = document.getElementById('status');
        if (statusField) {
            statusField.value = 'occupied';
            validateField(statusField);
        }
        @endif
    }
}
</script>

<style>
/* Custom dropdown styles */
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

[data-theme="dark"] .custom-dropdown option {
    background-color: #2a2a3c;
    color: #e4e4e4;
}

[data-theme="light"] .custom-dropdown option {
    background-color: #ffffff;
    color: #4b4b4b;
}

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

.custom-input:disabled {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

#monthly_rent, #security_deposit {
    padding-left: 3rem !important;
}

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

.property-select-card {
    display: block;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
}

.property-select-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.amenity-checkbox {
    display: block;
    cursor: pointer;
}

.amenity-content {
    display: flex;
    align-items: center;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    transition: all 0.3s ease;
    position: relative;
    min-height: 60px;
}

.amenity-content:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.amenity-content.checked {
    border-color: var(--success);
    background-color: rgba(var(--success-rgb), 0.05);
}

.amenity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.amenity-content.checked .amenity-icon {
    background-color: var(--success);
    color: white;
}

.amenity-details {
    flex-grow: 1;
    min-width: 0;
}

.amenity-label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    transition: color 0.3s ease;
    margin-bottom: 2px;
}

.amenity-check {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--border-color);
    color: transparent;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.amenity-content.checked .amenity-check {
    background-color: var(--success);
    color: white;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#propertyInfoPreview {
    animation: slideDown 0.3s ease-out;
}

.deposit-suggestion {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.bg-green-100 {
    background-color: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.3);
}

.bg-red-100 {
    background-color: rgba(var(--danger-rgb), 0.1);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.custom-input::placeholder,
.custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.custom-dropdown:focus-visible,
.custom-input:focus-visible,
.custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

[data-theme="dark"] #monthly_rent + .absolute span,
[data-theme="dark"] #security_deposit + .absolute span {
    color: #a0a0a0;
}

[data-theme="light"] #monthly_rent + .absolute span,
[data-theme="light"] #security_deposit + .absolute span {
    color: #6b7280;
}

.absolute.inset-y-0.left-0.pl-3 {
    pointer-events: none;
    z-index: 10;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-1.md\:grid-cols-3,
    .grid.grid-cols-1.md\:grid-cols-4,
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .p-6 {
        padding: 1rem;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .amenity-content {
        padding: 10px;
    }
    
    .amenity-icon {
        width: 36px;
        height: 36px;
        margin-right: 10px;
    }
    
    #monthly_rent, #security_deposit {
        padding-left: 2.75rem !important;
    }
}

@media (max-width: 640px) {
    input, select, textarea {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .text-lg {
        font-size: 16px;
    }
    
    .amenity-content {
        flex-direction: column;
        text-align: center;
        padding: 16px 12px;
    }
    
    .amenity-icon {
        margin-right: 0;
        margin-bottom: 8px;
        width: 32px;
        height: 32px;
    }
    
    .amenity-details {
        margin-bottom: 8px;
    }
    
    .amenity-check {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
    }
    
    #monthly_rent, #security_deposit {
        padding-left: 2.5rem !important;
    }
}

textarea::-webkit-scrollbar {
    width: 6px;
}

textarea::-webkit-scrollbar-track {
    background: var(--card-bg);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

.text-red-500 {
    color: var(--danger) !important;
}

#charCount {
    transition: color 0.3s ease;
}
</style>
@endsection