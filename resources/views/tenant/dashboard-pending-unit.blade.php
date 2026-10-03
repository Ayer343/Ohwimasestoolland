{{-- tenant/property_units/my-unit.blade.php --}}
@php
    $layout = 'layouts.tenant'; // Tenant layout
    $user = auth()->user();
    $isTenant = $user->isTenant();
    
    // Use a public constant or hardcode the value (3600 seconds = 1 hour)
    $cacheTtl = 3600; // 1 hour
    
    // Get tenant's unit from cache if available
    $cacheKey = 'tenant_unit_' . $user->id;
    $unit = Cache::remember($cacheKey, $cacheTtl, function () use ($user) {
        return \App\Models\PropertyUnit::where('tenant_id', $user->id)
            ->where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
            ->with(['property', 'currentLease', 'property.landlord'])
            ->first();
    });
    
    $pageTitle = 'My Unit';
    if ($unit) {
        $pageTitle .= ' - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number;
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">My Unit</h2>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> 
                View and manage your assigned property unit
            </div>
        </div>
    </div>

    @if(!$unit)
        <!-- No Unit Assigned State -->
        <div class="card">
            <div class="p-6 text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--warning-rgb, 251, 191, 36), 0.1);">
                    <i class="fas fa-home text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Unit Assigned</h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    You don't have a unit assigned to you yet. Please contact your landlord or administrator to get assigned to a unit.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('tenant.contact-landlord') }}" 
                       class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-envelope mr-2"></i> Contact Landlord
                    </a>
                    <a href="{{ route('tenant.profile.show') }}" 
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-user mr-2"></i> Update Profile
                    </a>
                </div>
            </div>
        </div>
    @else
        <!-- Success Message -->
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        <!-- Error Message -->
        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        <!-- Unit Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Rent Card -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Monthly Rent</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            GHS {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Lease Card -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-file-contract text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Lease Status</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            @if($unit->currentLease)
                                @php
                                    $leaseStatusColor = '';
                                    if($unit->currentLease->status === 'active') {
                                        $leaseStatusColor = 'text-green-600';
                                    } elseif($unit->currentLease->status === 'pending_signature') {
                                        $leaseStatusColor = 'text-yellow-600';
                                    } elseif($unit->currentLease->status === 'expired') {
                                        $leaseStatusColor = 'text-red-600';
                                    } else {
                                        $leaseStatusColor = 'text-gray-600';
                                    }
                                @endphp
                                <span class="{{ $leaseStatusColor }} capitalize">
                                    {{ str_replace('_', ' ', $unit->currentLease->status) }}
                                </span>
                            @else
                                <span class="text-yellow-600">No Lease</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Property Card -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                        <i class="fas fa-building text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Property</p>
                        <p class="text-2xl font-bold truncate" style="color: var(--text-primary);" title="{{ $unit->property->property_name }}">
                            {{ \Illuminate\Support\Str::limit($unit->property->property_name, 15) }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Move In Card -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                        <i class="fas fa-calendar-alt text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Move In Date</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            @if($unit->tenant_move_in_date)
                                {{ $unit->tenant_move_in_date->format('M d') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Details Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Unit Information Card -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-home mr-2"></i> Unit Information
                        </h3>
                        <a href="{{ route('tenant.property-units.show', $unit->id) }}" 
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center text-sm">
                            <i class="fas fa-external-link-alt mr-2"></i> View Full Details
                        </a>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Unit Header -->
                        <div class="flex items-start space-x-4 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="flex-shrink-0">
                                @php
                                    $unitIcon = '';
                                    if($unit->unit_type === 'apartment') {
                                        $unitIcon = 'fa-building';
                                    } elseif($unit->unit_type === 'house') {
                                        $unitIcon = 'fa-home';
                                    } elseif($unit->unit_type === 'studio') {
                                        $unitIcon = 'fa-cube';
                                    } elseif($unit->unit_type === 'commercial') {
                                        $unitIcon = 'fa-store';
                                    } else {
                                        $unitIcon = 'fa-home';
                                    }
                                    
                                    $unitColor = '';
                                    if($unit->status === 'available') {
                                        $unitColor = 'success';
                                    } elseif($unit->status === 'occupied') {
                                        $unitColor = 'primary';
                                    } elseif($unit->status === 'under_maintenance') {
                                        $unitColor = 'warning';
                                    } elseif($unit->status === 'vacant') {
                                        $unitColor = 'secondary';
                                    } else {
                                        $unitColor = 'info';
                                    }
                                @endphp
                                <div class="w-16 h-16 rounded-lg flex items-center justify-center"
                                     style="background-color: rgba(var(--{{ $unitColor }}-rgb), 0.1); color: var(--{{ $unitColor }});">
                                    <i class="fas {{ $unitIcon }} text-2xl"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                    Unit {{ $unit->unit_number }}
                                    @if($unit->unit_name)
                                        <span class="text-base font-normal" style="color: var(--text-secondary);">- {{ $unit->unit_name }}</span>
                                    @endif
                                </h4>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $unitColor }}">
                                        <i class="fas fa-circle mr-1 text-xs"></i>
                                        {{ ucfirst(str_replace('_', ' ', $unit->status)) }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-secondary">
                                        <i class="fas fa-tag mr-1"></i>
                                        {{ $unit->unit_type }}
                                    </span>
                                    @if($unit->is_furnished)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-success">
                                            <i class="fas fa-couch mr-1"></i> Furnished
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $unit->description ?? 'No description available.' }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Unit Specifications Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @if($unit->bedrooms)
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <i class="fas fa-bed text-xl mb-2" style="color: var(--primary);"></i>
                                <div class="text-sm" style="color: var(--text-secondary);">Bedrooms</div>
                                <div class="font-bold text-lg" style="color: var(--text-primary);">{{ $unit->bedrooms }}</div>
                            </div>
                            @endif
                            
                            @if($unit->bathrooms)
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <i class="fas fa-bath text-xl mb-2" style="color: var(--primary);"></i>
                                <div class="text-sm" style="color: var(--text-secondary);">Bathrooms</div>
                                <div class="font-bold text-lg" style="color: var(--text-primary);">{{ $unit->bathrooms }}</div>
                            </div>
                            @endif
                            
                            @if($unit->living_rooms)
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <i class="fas fa-couch text-xl mb-2" style="color: var(--primary);"></i>
                                <div class="text-sm" style="color: var(--text-secondary);">Living Rooms</div>
                                <div class="font-bold text-lg" style="color: var(--text-primary);">{{ $unit->living_rooms }}</div>
                            </div>
                            @endif
                            
                            @if($unit->floor_area)
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <i class="fas fa-ruler-combined text-xl mb-2" style="color: var(--primary);"></i>
                                <div class="text-sm" style="color: var(--text-secondary);">Floor Area</div>
                                <div class="font-bold text-lg" style="color: var(--text-primary);">{{ $unit->floor_area }} sqft</div>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Amenities -->
                        @if(!empty($unit->amenities))
                        <div>
                            <h5 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-star mr-2"></i> Amenities
                            </h5>
                            <div class="flex flex-wrap gap-2">
                                @foreach($unit->amenities as $amenity)
                                    @php
                                        $amenityIcons = [
                                            'parking' => 'fa-parking',
                                            'balcony' => 'fa-umbrella-beach',
                                            'air_conditioning' => 'fa-snowflake',
                                            'furnished' => 'fa-couch',
                                            'wifi' => 'fa-wifi',
                                            'security' => 'fa-shield-alt',
                                            'gym' => 'fa-dumbbell',
                                            'pool' => 'fa-swimming-pool',
                                            'laundry' => 'fa-tshirt',
                                            'elevator' => 'fa-elevator',
                                            'generator' => 'fa-bolt',
                                            'cctv' => 'fa-video',
                                            'fire_safety' => 'fa-fire-extinguisher',
                                            'water_heater' => 'fa-shower',
                                            'kitchen_appliances' => 'fa-blender',
                                        ];
                                        
                                        $amenityLabels = [
                                            'parking' => 'Parking Space',
                                            'balcony' => 'Balcony',
                                            'air_conditioning' => 'Air Conditioning',
                                            'furnished' => 'Furnished',
                                            'wifi' => 'Wi-Fi',
                                            'security' => '24/7 Security',
                                            'gym' => 'Gym Access',
                                            'pool' => 'Swimming Pool',
                                            'laundry' => 'Laundry Facility',
                                            'elevator' => 'Elevator',
                                            'generator' => 'Backup Generator',
                                            'cctv' => 'CCTV Surveillance',
                                            'fire_safety' => 'Fire Safety System',
                                            'water_heater' => 'Water Heater',
                                            'kitchen_appliances' => 'Kitchen Appliances',
                                        ];
                                        
                                        $icon = $amenityIcons[$amenity] ?? 'fa-check';
                                        $label = $amenityLabels[$amenity] ?? ucfirst(str_replace('_', ' ', $amenity));
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm badge-info">
                                        <i class="fas {{ $icon }} mr-2"></i>
                                        {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Property & Contact Info Card -->
            <div>
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2"></i> Property Details
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Property Info -->
                        <div>
                            <h5 class="font-medium mb-2" style="color: var(--text-primary);">{{ $unit->property->property_name }}</h5>
                            <div class="space-y-1 text-sm" style="color: var(--text-secondary);">
                                @if($unit->property->address)
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt mr-2 mt-0.5"></i>
                                    <span>{{ $unit->property->address }}</span>
                                </div>
                                @endif
                                
                                @if($unit->property->city)
                                <div class="flex items-center">
                                    <i class="fas fa-city mr-2"></i>
                                    <span>{{ $unit->property->city }}</span>
                                </div>
                                @endif
                                
                                @if($unit->property->zone)
                                <div class="flex items-center">
                                    <i class="fas fa-map mr-2"></i>
                                    <span>{{ $unit->property->zone }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Landlord Contact -->
                        @if($unit->property->landlord)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-2"></i> Landlord Contact
                            </h5>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 avatar-sm"
                                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <span style="color: var(--text-primary);">{{ $unit->property->landlord->name }}</span>
                                </div>
                                
                                @if($unit->property->landlord->phone)
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-phone mr-2" style="color: var(--text-secondary);"></i>
                                    <a href="tel:{{ $unit->property->landlord->phone }}" 
                                       class="hover:underline" style="color: var(--primary);">
                                        {{ $unit->property->landlord->phone }}
                                    </a>
                                </div>
                                @endif
                                
                                @if($unit->property->landlord->email)
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope mr-2" style="color: var(--text-secondary);"></i>
                                    <a href="mailto:{{ $unit->property->landlord->email }}" 
                                       class="hover:underline" style="color: var(--primary);">
                                        {{ $unit->property->landlord->email }}
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                        
                        <!-- Emergency Contact -->
                        @if($user->emergency_contact)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-ambulance mr-2"></i> Your Emergency Contact
                            </h5>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2" style="color: var(--text-secondary);"></i>
                                    @php
                                        $emergencyName = is_array($user->emergency_contact) ? ($user->emergency_contact['name'] ?? 'Not set') : 'Not set';
                                    @endphp
                                    <span style="color: var(--text-primary);">{{ $emergencyName }}</span>
                                </div>
                                
                                @php
                                    $emergencyPhone = is_array($user->emergency_contact) ? ($user->emergency_contact['phone'] ?? null) : null;
                                @endphp
                                @if($emergencyPhone)
                                <div class="flex items-center">
                                    <i class="fas fa-phone mr-2" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--primary);">{{ $emergencyPhone }}</span>
                                </div>
                                @endif
                                
                                @php
                                    $emergencyRelationship = is_array($user->emergency_contact) ? ($user->emergency_contact['relationship'] ?? null) : null;
                                @endphp
                                @if($emergencyRelationship)
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-2" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--text-secondary);">{{ ucfirst($emergencyRelationship) }}</span>
                                </div>
                                @endif
                            </div>
                            <a href="{{ route('tenant.profile.edit') }}" class="text-xs text-primary hover:underline mt-2 inline-block">
                                <i class="fas fa-edit mr-1"></i> Update Contact
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Lease Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Quick Actions Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Maintenance Request -->
                    <a href="{{ route('tenant.property-units.maintenance-requests', $unit->id) }}" 
                       class="p-4 rounded-lg flex items-center space-x-3 transition-colors group"
                       style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div>
                            <h4 class="font-medium group-hover:underline" style="color: var(--text-primary);">Maintenance</h4>
                            <p class="text-xs" style="color: var(--text-secondary);">Request repairs</p>
                        </div>
                        <div class="ml-auto">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </a>
                    
                    <!-- Financials -->
                    <a href="{{ route('tenant.property-units.financials', $unit->id) }}" 
                       class="p-4 rounded-lg flex items-center space-x-3 transition-colors group"
                       style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h4 class="font-medium group-hover:underline" style="color: var(--text-primary);">Financials</h4>
                            <p class="text-xs" style="color: var(--text-secondary);">View payments & invoices</p>
                        </div>
                        <div class="ml-auto">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </a>
                    
                    <!-- Lease Details -->
                    @if($unit->currentLease)
                    <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $unit->currentLease->id]) }}" 
                       class="p-4 rounded-lg flex items-center space-x-3 transition-colors group"
                       style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div>
                            <h4 class="font-medium group-hover:underline" style="color: var(--text-primary);">Lease Agreement</h4>
                            <p class="text-xs" style="color: var(--text-secondary);">View & download</p>
                        </div>
                        <div class="ml-auto">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </a>
                    @endif
                    
                    <!-- Documents -->
                    <a href="{{ route('tenant.property-units.documents', $unit->id) }}" 
                       class="p-4 rounded-lg flex items-center space-x-3 transition-colors group"
                       style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-medium group-hover:underline" style="color: var(--text-primary);">Documents</h4>
                            <p class="text-xs" style="color: var(--text-secondary);">Important files</p>
                        </div>
                        <div class="ml-auto">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </a>
                </div>
                
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2 text-warning"></i> Important Notes
                    </h4>
                    <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>Always report maintenance issues promptly</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>
                                Rent is due on the 
                                @if($unit->currentLease && $unit->currentLease->payment_due_day)
                                    {{ $unit->currentLease->payment_due_day }}
                                @else
                                    1st
                                @endif
                                 of each month
                            </span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>Contact landlord for emergency issues after hours</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Lease Information Card -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2"></i> Lease Information
                    </h3>
                    @if($unit->currentLease)
                        @php
                            $leaseBadgeClass = $unit->currentLease->status === 'active' ? 'badge-success' : 'badge-warning';
                        @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ $leaseBadgeClass }}">
                            {{ ucfirst($unit->currentLease->status) }}
                        </span>
                    @endif
                </div>
                
                @if($unit->currentLease)
                <div class="space-y-4">
                    <!-- Lease Period -->
                    <div>
                        <h5 class="font-medium mb-2" style="color: var(--text-primary);">Lease Period</h5>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <div class="text-sm" style="color: var(--text-secondary);">Start Date</div>
                                <div class="font-bold" style="color: var(--text-primary);">
                                    {{ $unit->currentLease->start_date->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                                <div class="text-sm" style="color: var(--text-secondary);">End Date</div>
                                <div class="font-bold" style="color: var(--text-primary);">
                                    {{ $unit->currentLease->end_date->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rent Details -->
                    <div>
                        <h5 class="font-medium mb-2" style="color: var(--text-primary);">Rent Details</h5>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Monthly Rent:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    GHS {{ number_format($unit->currentLease->monthly_rent, 2) }}
                                </span>
                            </div>
                            @if($unit->currentLease->security_deposit)
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Security Deposit:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    GHS {{ number_format($unit->currentLease->security_deposit, 2) }}
                                </span>
                            </div>
                            @endif
                            @if($unit->currentLease->late_fee_percentage)
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Late Fee:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    {{ $unit->currentLease->late_fee_percentage }}%
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Lease Actions -->
                    <div class="pt-4 border-t" style="border-color: var(--border-color);">
                        <div class="flex space-x-3">
                            <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $unit->currentLease->id]) }}" 
                               class="btn-primary flex-1 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                                <i class="fas fa-eye mr-2"></i> View Details
                            </a>
                            <a href="{{ route('tenant.property-units.lease-download-pdf', [$unit->id, $unit->currentLease->id]) }}" 
                               class="btn-secondary flex-1 px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                <i class="fas fa-download mr-2"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-file-contract text-2xl" style="color: var(--warning);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Active Lease</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        You don't have an active lease agreement for this unit.
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Please contact your landlord to create a lease agreement.
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- Recent Activity -->
        @php
            // Get recent tenant-specific activities
            $recentActivities = \App\Models\ActivityLog::where('user_id', $user->id)
                ->orWhere(function($query) use ($unit) {
                    $query->where('unit_id', $unit->id)
                          ->whereIn('type', ['maintenance_request_created', 'invoice_generated']);
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        @endphp
        
        @if($recentActivities->count() > 0)
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2"></i> Recent Activity
            </h3>
            
            <div class="space-y-3">
                @foreach($recentActivities as $activity)
                    @php
                        $activityIcons = [
                            'maintenance_request_created' => ['icon' => 'fa-tools', 'color' => 'warning'],
                            'invoice_generated' => ['icon' => 'fa-money-bill-wave', 'color' => 'success'],
                            'tenant_vacated' => ['icon' => 'fa-sign-out-alt', 'color' => 'danger'],
                            'tenant_approved' => ['icon' => 'fa-user-check', 'color' => 'success'],
                            'lease_created' => ['icon' => 'fa-file-contract', 'color' => 'info'],
                            'unit_assigned' => ['icon' => 'fa-home', 'color' => 'primary'],
                        ];
                        
                        $icon = $activityIcons[$activity->type] ?? ['icon' => 'fa-circle', 'color' => 'secondary'];
                    @endphp
                    
                    <div class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--{{ $icon['color'] }}-rgb), 0.1); color: var(--{{ $icon['color'] }});">
                                <i class="fas {{ $icon['icon'] }}"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                {{ $activity->description }}
                            </div>
                            <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>
                                {{ $activity->created_at->diffForHumans() }}
                            </div>
                        </div>
                        @if($activity->type === 'maintenance_request_created')
                        <a href="{{ route('tenant.property-units.maintenance-requests', $unit->id) }}" 
                           class="text-xs px-2 py-1 rounded badge-info">
                            View
                        </a>
                        @endif
                    </div>
                @endforeach
            </div>
            
            @if($recentActivities->count() > 5)
            <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                <a href="#" class="text-primary hover:underline text-sm">
                    <i class="fas fa-history mr-1"></i> View All Activity
                </a>
            </div>
            @endif
        </div>
        @endif
    @endif
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Add tooltips to truncated property names
    const truncatedElements = document.querySelectorAll('[title]');
    truncatedElements.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed z-50 px-2 py-1 text-xs rounded-lg shadow-lg';
            tooltip.style.backgroundColor = 'var(--bg-secondary)';
            tooltip.style.color = 'var(--text-primary)';
            tooltip.style.border = '1px solid var(--border-color)';
            tooltip.textContent = this.title;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + 'px';
            tooltip.style.top = (rect.bottom + 5) + 'px';
            
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
    
    // Refresh page every 30 minutes to update cached data
    setTimeout(() => {
        window.location.reload();
    }, 30 * 60 * 1000); // 30 minutes
});
</script>

<style>
/* Card styles */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.2);
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.2);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.2);
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.2);
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.2);
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.2);
}

/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    transition: background-color 0.2s;
    border: none;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    transition: background-color 0.2s;
    border: none;
}

.btn-secondary:hover {
    background-color: var(--secondary-dark);
}

/* Avatar styles */
.avatar-sm {
    width: 32px;
    height: 32px;
}

.avatar-md {
    width: 40px;
    height: 40px;
}

.avatar-lg {
    width: 48px;
    height: 48px;
}

/* Success and error message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
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

/* Quick action card hover effects */
.quick-action-card {
    transition: all 0.2s;
}

.quick-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.items-start.space-x-4 {
        flex-direction: column;
    }
    
    .flex-shrink-0 {
        margin-bottom: 1rem;
    }
    
    .grid.grid-cols-2.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.sm\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}
</style>
@endsection
@endsection