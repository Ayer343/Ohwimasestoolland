@php
    $user = auth()->user();
    $layout = 'layouts.field';
    
    // Get all tenants with proper relationship loading
    $allTenants = isset($allTenants) ? $allTenants : $property->getAllTenants();
@endphp

@extends($layout)

@section('title', $property->property_name . ' - Property Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">{{ $property->property_name }}</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-house-user mr-1"></i>Site Allocation • {{ $property->landlord->name }}'s Property
                </p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    {{ $property->house_number }} {{ $property->street_name }}
                    @if($property->block_number) • Block {{ $property->block_number }} @endif
                    @if($property->zone) • {{ $property->zone }} @endif
                </p>
                
                <!-- Property Type Badge -->
                <div class="flex items-center mt-2">
                    @if($property->propertyType)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" 
                              style="background-color: {{ $property->propertyType->color }}20; color: {{ $property->propertyType->color }}; border: 1px solid {{ $property->propertyType->color }}30;">
                            <i class="{{ $property->propertyType->icon }} mr-1"></i>
                            {{ $property->propertyType->name }}
                        </span>
                    @endif
                    
                    @if($property->custom_property_type)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ml-2" 
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-tag mr-1"></i>
                            {{ $property->custom_property_type }}
                        </span>
                    @endif
                    
                    <!-- Rented Status Badge - Now using getAllTenants count -->
                    @if($allTenants->count() > 0)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ml-2" 
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-users mr-1"></i>
                            Rented ({{ $allTenants->count() }} tenant(s))
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ml-2" 
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-home mr-1"></i>
                            Vacant
                        </span>
                    @endif
                </div>
                
                <!-- Field Agent Info -->
                @if($property->isRegisteredBy(auth()->id()))
                <p class="text-xs mt-1" style="color: var(--success);">
                    <i class="fas fa-user-check mr-1"></i>You registered this property
                </p>
                @elseif($property->registeredBy)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-user-check mr-1"></i>Registered by: {{ $property->registeredBy->name }}
                </p>
                @endif
            </div>
            <div class="flex space-x-2">
                @can('update', $property)
                    <a href="{{ route('field-agent.properties.edit', $property->id) }}" class="btn-primary flex items-center">
                        <i class="fas fa-edit mr-2"></i> Edit Property
                    </a>
                @endcan
                
                <a href="{{ route('field-agent.properties.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Properties
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @include('partials.messages')

    <!-- Property Photos Gallery Section -->
    @if($property->photos && $property->photos->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            <i class="fas fa-images mr-2"></i>Property Photos
            <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">({{ $property->photos->count() }} photo(s))</span>
        </h3>
        
        <!-- Photo Gallery Grid -->
        <div class="photo-gallery">
            <!-- Primary Photo Highlight -->
            @php
                $primaryPhoto = $property->photos->where('is_primary', true)->first();
                $otherPhotos = $property->photos->where('is_primary', false);
            @endphp
            
            @if($primaryPhoto)
            <div class="primary-photo-container mb-6">
                <div class="relative rounded-xl overflow-hidden shadow-lg" style="background-color: var(--bg-secondary);">
                    <img id="primaryPhotoImg" 
                         src="{{ Storage::url($primaryPhoto->photo_path) }}" 
                         alt="Primary property photo" 
                         class="w-full h-96 object-cover cursor-pointer"
                         onclick="openPhotoModal('{{ Storage::url($primaryPhoto->photo_path) }}', 'Primary Photo')">
                    <div class="absolute bottom-4 left-4 bg-black bg-opacity-60 text-white px-3 py-1 rounded-lg text-sm">
                        <i class="fas fa-star text-yellow-400 mr-1"></i> Primary Photo
                    </div>
                    @if($otherPhotos->count() > 0)
                    <div class="absolute bottom-4 right-4 bg-black bg-opacity-60 text-white px-3 py-1 rounded-lg text-sm">
                        <i class="fas fa-images mr-1"></i> {{ $otherPhotos->count() }} more photo(s)
                    </div>
                    @endif
                </div>
            </div>
            @endif
            
            <!-- Thumbnail Gallery -->
            @if($otherPhotos->count() > 0)
            <div class="thumbnail-gallery">
                <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-th-large mr-1"></i>All Photos
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    @foreach($otherPhotos as $photo)
                    <div class="thumbnail-item relative rounded-lg overflow-hidden cursor-pointer group"
                         style="border: 2px solid var(--border-color); background-color: var(--bg-secondary);"
                         onclick="openPhotoModal('{{ Storage::url($photo->photo_path) }}', 'Property Photo')">
                        <img src="{{ Storage::url($photo->photo_path) }}" 
                             alt="Property photo" 
                             class="w-full h-32 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
                            <i class="fas fa-search-plus text-white text-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        
        <!-- Download All Photos Button -->
        @if($property->photos->count() > 1)
        <div class="mt-4 text-right">
            <button onclick="downloadAllPhotos()" class="btn-secondary text-sm">
                <i class="fas fa-download mr-1"></i> Download All Photos ({{ $property->photos->count() }})
            </button>
        </div>
        @endif
    </div>
    @else
    <!-- No Photos Placeholder -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            <i class="fas fa-images mr-2"></i>Property Photos
        </h3>
        <div class="text-center py-12">
            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" 
                 style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-camera text-4xl" style="color: var(--text-secondary);"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-secondary);">No Photos Uploaded</h4>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">No photos have been uploaded for this property yet.</p>
            @can('update', $property)
                <a href="{{ route('field-agent.properties.edit', $property->id) }}" class="btn-primary inline-flex items-center">
                    <i class="fas fa-upload mr-2"></i> Upload Photos
                </a>
            @endcan
        </div>
    </div>
    @endif

    <!-- Photo Modal (Lightbox) -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center" style="backdrop-filter: blur(5px);">
        <div class="relative max-w-7xl w-full mx-4">
            <!-- Close Button -->
            <button onclick="closePhotoModal()" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-3xl transition-colors">
                <i class="fas fa-times"></i>
            </button>
            
            <!-- Download Button -->
            <button onclick="downloadCurrentPhoto()" class="absolute -top-12 right-12 text-white hover:text-gray-300 text-2xl transition-colors">
                <i class="fas fa-download"></i>
            </button>
            
            <!-- Modal Image -->
            <img id="modalImage" src="" alt="Property photo" class="w-full h-auto max-h-[85vh] object-contain rounded-lg shadow-2xl">
            
            <!-- Caption -->
            <div id="modalCaption" class="absolute bottom-4 left-0 right-0 text-center text-white text-sm bg-black bg-opacity-50 py-2 rounded-lg mx-auto w-64">
                Loading...
            </div>
            
            <!-- Navigation Buttons (if multiple photos) -->
            <div class="photo-nav-buttons">
                <button onclick="previousPhoto()" class="nav-btn prev-btn">
                    <i class="fas fa-chevron-left text-3xl"></i>
                </button>
                <button onclick="nextPhoto()" class="nav-btn next-btn">
                    <i class="fas fa-chevron-right text-3xl"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Property Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Property Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-home mr-2"></i>Site Allocation Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <div class="property-field">
                            <label class="field-label">Site Allocation Name</label>
                            <p class="field-value">{{ $property->property_name }}</p>
                            <p class="field-subtext">
                                <i class="fas fa-user-tie mr-1"></i>Belongs to {{ $property->landlord->name }}
                            </p>
                        </div>
                        
                        <!-- Property Type Information -->
                        <div class="property-field">
                            <label class="field-label">Property Type</label>
                            @if($property->propertyType)
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center" style="background-color: {{ $property->propertyType->color }}20;">
                                        <i class="{{ $property->propertyType->icon }} text-xs" style="color: {{ $property->propertyType->color }};"></i>
                                    </div>
                                    <p class="field-value">{{ $property->propertyType->name }}</p>
                                </div>
                                @if($property->custom_property_type)
                                    <p class="field-subtext">Custom Type: {{ $property->custom_property_type }}</p>
                                @endif
                                @if($property->propertyType->description)
                                    <p class="field-subtext">{{ $property->propertyType->description }}</p>
                                @endif
                            @else
                                <p class="field-value" style="color: var(--warning);">No property type assigned</p>
                            @endif
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Registration Pattern</label>
                            <p class="field-value font-mono bg-gray-100 px-2 py-1 rounded inline-block" style="background-color: var(--bg-secondary);">
                                {{ $property->registration_pattern ?? 'N/A' }}
                            </p>
                            <p class="field-subtext">Auto-generated identification pattern</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">House Number</label>
                            <p class="field-value">{{ $property->house_number ?? 'N/A' }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Street Name</label>
                            <p class="field-value">{{ $property->street_name }}</p>
                        </div>
                        
                        <!-- Rented Status -->
                        <div class="property-field">
                            <label class="field-label">Rented Status</label>
                            <div class="flex items-center">
                                @if($allTenants->count() > 0)
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold" 
                                          style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Rented ({{ $allTenants->count() }} tenant(s))
                                    </span>
                                    <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-users mr-1"></i>
                                        Occupied
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold" 
                                          style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                        <i class="fas fa-home mr-1"></i>
                                        Vacant
                                    </span>
                                    <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-user-plus mr-1"></i>
                                        Available for rent
                                    </span>
                                @endif
                            </div>
                            <p class="field-subtext">
                                @if($allTenants->count() > 0)
                                    {{ $allTenants->count() }} tenant(s) currently residing
                                @else
                                    No tenants assigned to this property
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <!-- Location Information -->
                    <div class="space-y-4">
                        <div class="property-field">
                            <label class="field-label">Zone</label>
                            <p class="field-value">{{ $property->zone ?? 'N/A' }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Section</label>
                            <p class="field-value">{{ $property->section ?? 'N/A' }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Block Number</label>
                            <p class="field-value">{{ $property->block_number ?? 'N/A' }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Registration Date</label>
                            <p class="field-value">{{ $property->registration_date->format('M d, Y') }}</p>
                            <p class="field-subtext">{{ $property->registration_date->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Status and Digital Address Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="property-field">
                        <label class="field-label">Site Status</label>
                        @php
                            $statusColors = [
                                'active' => 'success',
                                'inactive' => 'secondary',
                                'under_maintenance' => 'warning',
                                'vacant' => 'info'
                            ];
                            $statusColor = $statusColors[$property->status] ?? 'secondary';
                        @endphp
                        <div class="flex items-center mt-1">
                            <span class="px-3 py-2 rounded-full text-sm font-semibold" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="fas fa-circle mr-2 text-xs"></i>
                                {{ ucfirst(str_replace('_', ' ', $property->status)) }}
                            </span>
                        </div>
                        <p class="field-subtext mt-1">Current status of this site allocation</p>
                    </div>
                    
                    <div class="property-field">
                        <label class="field-label">Digital Address</label>
                        @if($property->digital_address)
                            <div class="flex items-center justify-between p-3 rounded-lg mt-1" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <div>
                                    <p class="field-value">{{ $property->digital_address }}</p>
                                    <p class="field-subtext" style="color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i> Verified digital address
                                    </p>
                                </div>
                                <button onclick="copyDigitalAddress()" class="px-3 py-1 rounded-full text-xs font-semibold" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); border: none; cursor: pointer;">
                                    <i class="fas fa-copy mr-1"></i> Copy
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-between p-3 rounded-lg mt-1" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <div>
                                    <p class="field-value" style="color: var(--warning);">Not assigned</p>
                                    <p class="field-subtext" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> No digital address assigned
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    Missing
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Description -->
                @if($property->description)
                <div class="mt-6 property-field">
                    <label class="field-label">Site Description</label>
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <p class="text-sm" style="color: var(--text-primary); line-height: 1.6;">{{ $property->description }}</p>
                    </div>
                </div>
                @endif
            </div>

            <!-- ====================================================== -->
            <!-- ENHANCED TENANTS SECTION WITH UNIT ASSIGNMENT          -->
            <!-- ====================================================== -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-users mr-2"></i>Tenants Information
                    <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">
                        ({{ $allTenants->count() }} tenant{{ $allTenants->count() > 1 ? 's' : '' }})
                    </span>
                    @if($property->units()->count() > 0)
                        <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">
                            • {{ $property->units()->count() }} unit(s)
                        </span>
                    @endif
                </h3>
                
                @if($allTenants->count() > 0)
                    <!-- Tenants List -->
                    <div class="space-y-4">
                        @foreach($allTenants as $tenant)
                        @php
                            // Check if tenant is assigned to a unit
                            $assignedUnit = \App\Models\PropertyUnit::where('tenant_id', $tenant->id)
                                ->where('property_id', $property->id)
                                ->with(['property', 'approvedBy', 'requestedBy'])
                                ->first();
                            $isAssigned = $assignedUnit !== null;
                            $unitStatus = $assignedUnit ? $assignedUnit->tenant_status : null;
                            $unitNumber = $assignedUnit ? $assignedUnit->unit_number : null;
                            $unitName = $assignedUnit ? $assignedUnit->unit_name : null;
                            
                            // Determine tenant assignment status
                            $assignmentStatus = 'pending';
                            $statusBadgeClass = 'badge-warning';
                            $statusIcon = 'fa-clock';
                            $statusText = 'Pending Assignment';
                            $statusDescription = 'Tenant has not been assigned to any unit yet';
                            
                            if ($isAssigned) {
                                switch ($unitStatus) {
                                    case 'pending_approval':
                                        $assignmentStatus = 'pending_approval';
                                        $statusBadgeClass = 'badge-warning';
                                        $statusIcon = 'fa-hourglass-half';
                                        $statusText = 'Pending Admin Approval';
                                        $statusDescription = 'Tenant assignment is awaiting admin approval';
                                        break;
                                    case 'approved':
                                        $assignmentStatus = 'approved';
                                        $statusBadgeClass = 'badge-success';
                                        $statusIcon = 'fa-check-circle';
                                        $statusText = 'Approved & Occupying';
                                        $statusDescription = 'Tenant is approved and currently occupying the unit';
                                        break;
                                    case 'vacated':
                                        $assignmentStatus = 'vacated';
                                        $statusBadgeClass = 'badge-danger';
                                        $statusIcon = 'fa-sign-out-alt';
                                        $statusText = 'Vacated';
                                        $statusDescription = 'Tenant has vacated the unit';
                                        break;
                                    case 'rejected':
                                        $assignmentStatus = 'rejected';
                                        $statusBadgeClass = 'badge-danger';
                                        $statusIcon = 'fa-times-circle';
                                        $statusText = 'Rejected';
                                        $statusDescription = 'Tenant assignment was rejected by admin';
                                        break;
                                    default:
                                        $assignmentStatus = 'assigned';
                                        $statusBadgeClass = 'badge-info';
                                        $statusIcon = 'fa-home';
                                        $statusText = 'Assigned';
                                        $statusDescription = 'Tenant is assigned to a unit';
                                }
                            }
                            
                            // Get approval details if available
                            $approvedByName = $assignedUnit && $assignedUnit->approvedBy ? $assignedUnit->approvedBy->name : null;
                            $approvedAt = $assignedUnit && $assignedUnit->tenant_approved_at ? \Carbon\Carbon::parse($assignedUnit->tenant_approved_at)->format('M d, Y h:i A') : null;
                            $requestedByName = $assignedUnit && $assignedUnit->requestedBy ? $assignedUnit->requestedBy->name : null;
                            $requestedAt = $assignedUnit && $assignedUnit->tenant_requested_at ? \Carbon\Carbon::parse($assignedUnit->tenant_requested_at)->format('M d, Y h:i A') : null;
                            
                            // Get move-in date
                            $moveInDate = $assignedUnit && $assignedUnit->tenant_move_in_date ? \Carbon\Carbon::parse($assignedUnit->tenant_move_in_date)->format('M d, Y') : null;
                            
                            // Get tenant details from JSON
                            $tenantDetails = null;
                            $preferredMoveIn = null;
                            $preferredRent = null;
                            
                            if ($assignedUnit && $assignedUnit->tenant_details) {
                                if (is_array($assignedUnit->tenant_details)) {
                                    $tenantDetails = $assignedUnit->tenant_details;
                                } elseif (is_string($assignedUnit->tenant_details)) {
                                    $tenantDetails = json_decode($assignedUnit->tenant_details, true);
                                }
                                $preferredMoveIn = $tenantDetails && isset($tenantDetails['preferred_move_in']) ? $tenantDetails['preferred_move_in'] : null;
                                $preferredRent = $tenantDetails && isset($tenantDetails['preferred_rent']) ? $tenantDetails['preferred_rent'] : null;
                            }
                            
                            // Check if this is a primary tenant
                            $isPrimary = $assignedUnit && ($assignedUnit->is_primary_tenant ?? false);
                            
                            // Check if tenant has direct property assignment (pivot)
                            $hasDirectAssignment = $property->tenants->contains($tenant->id);
                        @endphp
                        <div class="tenant-card p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                                <div class="flex items-start space-x-4 w-full md:w-auto">
                                    <!-- Tenant Avatar -->
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" 
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        @if($tenant->gender === 'male')
                                            <i class="fas fa-male text-lg" style="color: var(--info);"></i>
                                        @elseif($tenant->gender === 'female')
                                            <i class="fas fa-female text-lg" style="color: var(--info);"></i>
                                        @else
                                            <i class="fas fa-user text-lg" style="color: var(--info);"></i>
                                        @endif
                                    </div>
                                    
                                    <!-- Tenant Details -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center flex-wrap gap-2">
                                            <h4 class="font-semibold text-lg" style="color: var(--text-primary);">
                                                {{ $tenant->name }}
                                            </h4>
                                            @if($tenant->gender)
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    {{ ucfirst($tenant->gender) }}
                                                </span>
                                            @endif
                                            
                                            <!-- Primary Tenant Badge -->
                                            @if($isPrimary)
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                                <i class="fas fa-crown mr-1"></i> Primary
                                            </span>
                                            @endif
                                            
                                            <!-- Assignment Status Badge -->
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold flex items-center {{ $statusBadgeClass }}">
                                                <i class="fas {{ $statusIcon }} mr-1"></i>
                                                {{ $statusText }}
                                            </span>
                                            
                                            <!-- Direct Assignment Badge -->
                                            @if($hasDirectAssignment)
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                  style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                <i class="fas fa-link mr-1"></i> Direct
                                            </span>
                                            @endif
                                        </div>
                                        
                                        <!-- Contact Information -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-phone text-sm" style="color: var(--text-secondary);"></i>
                                                <span class="text-sm" style="color: var(--text-primary);">{{ $tenant->phone }}</span>
                                                @if($tenant->phone_verified_at)
                                                    <i class="fas fa-check-circle text-xs" style="color: var(--success);" title="Phone verified"></i>
                                                @endif
                                            </div>
                                            
                                            @if($tenant->email)
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-envelope text-sm" style="color: var(--text-secondary);"></i>
                                                <span class="text-sm truncate" style="color: var(--text-primary);">{{ $tenant->email }}</span>
                                                @if($tenant->email_verified_at)
                                                    <i class="fas fa-check-circle text-xs" style="color: var(--success);" title="Email verified"></i>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Status Description -->
                                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            {{ $statusDescription }}
                                            @if($isAssigned && $assignedUnit)
                                                <span class="ml-2">• Unit: <strong>{{ $unitNumber }}</strong></span>
                                                @if($unitName)
                                                    <span class="ml-1">({{ $unitName }})</span>
                                                @endif
                                            @endif
                                        </div>
                                        
                                        <!-- Unit Assignment Information -->
                                        @if($isAssigned && $assignedUnit)
                                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-door-open mr-2" style="color: var(--primary);"></i>
                                                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Unit:</span>
                                                    <span class="text-sm font-semibold ml-2" style="color: var(--primary);">
                                                        {{ $unitNumber }}
                                                        @if($unitName)
                                                            <span class="font-normal text-xs" style="color: var(--text-secondary);">({{ $unitName }})</span>
                                                        @endif
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center">
                                                    <i class="fas fa-tag mr-2" style="color: var(--text-secondary);"></i>
                                                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Status:</span>
                                                    <span class="text-sm font-semibold ml-2 {{ $statusBadgeClass }}">
                                                        {{ $statusText }}
                                                    </span>
                                                </div>
                                                
                                                @if($moveInDate)
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar-check mr-2" style="color: var(--success);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Move-in:</span>
                                                    <span class="text-sm ml-2" style="color: var(--text-primary);">
                                                        {{ $moveInDate }}
                                                    </span>
                                                </div>
                                                @endif
                                                
                                                @if($assignedUnit->monthly_rent)
                                                <div class="flex items-center">
                                                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Rent:</span>
                                                    <span class="text-sm font-semibold ml-2" style="color: var(--success);">
                                                        ₵{{ number_format($assignedUnit->monthly_rent, 2) }}
                                                    </span>
                                                </div>
                                                @endif
                                                
                                                @if($assignedUnit->security_deposit)
                                                <div class="flex items-center">
                                                    <i class="fas fa-shield-alt mr-2" style="color: var(--info);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Deposit:</span>
                                                    <span class="text-sm font-semibold ml-2" style="color: var(--info);">
                                                        ₵{{ number_format($assignedUnit->security_deposit, 2) }}
                                                    </span>
                                                </div>
                                                @endif
                                                
                                                @if($assignedUnit->unit_type)
                                                <div class="flex items-center">
                                                    <i class="fas fa-building mr-2" style="color: var(--text-secondary);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Type:</span>
                                                    <span class="text-sm ml-2" style="color: var(--text-primary);">
                                                        {{ ucfirst(str_replace('_', ' ', $assignedUnit->unit_type)) }}
                                                    </span>
                                                </div>
                                                @endif
                                                
                                                @if($assignedUnit->bedrooms)
                                                <div class="flex items-center">
                                                    <i class="fas fa-bed mr-2" style="color: var(--text-secondary);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Bedrooms:</span>
                                                    <span class="text-sm ml-2" style="color: var(--text-primary);">
                                                        {{ $assignedUnit->bedrooms }}
                                                    </span>
                                                </div>
                                                @endif
                                                
                                                @if($assignedUnit->bathrooms)
                                                <div class="flex items-center">
                                                    <i class="fas fa-bath mr-2" style="color: var(--text-secondary);"></i>
                                                    <span class="text-sm" style="color: var(--text-secondary);">Bathrooms:</span>
                                                    <span class="text-sm ml-2" style="color: var(--text-primary);">
                                                        {{ $assignedUnit->bathrooms }}
                                                    </span>
                                                </div>
                                                @endif
                                            </div>
                                            
                                            <!-- Approval/Action Timeline -->
                                            <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--primary-rgb), 0.2);">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                    @if($requestedByName && $requestedAt)
                                                    <div class="flex items-center">
                                                        <i class="fas fa-user-plus mr-1" style="color: var(--info);"></i>
                                                        <span style="color: var(--text-secondary);">Requested by:</span>
                                                        <span class="ml-1 font-medium" style="color: var(--text-primary);">{{ $requestedByName }}</span>
                                                        <span class="ml-1" style="color: var(--text-secondary);">on {{ $requestedAt }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($approvedByName && $approvedAt)
                                                    <div class="flex items-center">
                                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                                        <span style="color: var(--text-secondary);">Approved by:</span>
                                                        <span class="ml-1 font-medium" style="color: var(--text-primary);">{{ $approvedByName }}</span>
                                                        <span class="ml-1" style="color: var(--text-secondary);">on {{ $approvedAt }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($preferredMoveIn)
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar-alt mr-1" style="color: var(--warning);"></i>
                                                        <span style="color: var(--text-secondary);">Preferred move-in:</span>
                                                        <span class="ml-1" style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($preferredMoveIn)->format('M d, Y') }}</span>
                                                    </div>
                                                    @endif
                                                    
                                                    @if($preferredRent)
                                                    <div class="flex items-center">
                                                        <i class="fas fa-hand-holding-usd mr-1" style="color: var(--warning);"></i>
                                                        <span style="color: var(--text-secondary);">Preferred rent:</span>
                                                        <span class="ml-1 font-medium" style="color: var(--text-primary);">{{ $preferredRent }}</span>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <!-- Approval Notes -->
                                            @if($assignedUnit->tenant_approval_notes)
                                            <div class="mt-2 p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                                <div class="flex items-start">
                                                    <i class="fas fa-sticky-note mr-1 mt-0.5" style="color: var(--text-secondary);"></i>
                                                    <div>
                                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Notes:</span>
                                                        <span class="text-xs" style="color: var(--text-primary);">{{ $assignedUnit->tenant_approval_notes }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                            
                                            <!-- Additional Unit Notes -->
                                            @if($assignedUnit->notes)
                                            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-sticky-note mr-1"></i>
                                                {{ $assignedUnit->notes }}
                                            </div>
                                            @endif
                                        </div>
                                        @else
                                        <!-- No Unit Assigned -->
                                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                            <div class="flex items-center flex-wrap gap-2">
                                                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                                                <span class="text-sm" style="color: var(--text-secondary);">
                                                    <span class="font-medium" style="color: var(--warning);">No unit assigned yet.</span>
                                                </span>
                                                @can('update', $property)
                                                    @if($property->isRegisteredBy(auth()->id()))
                                                        @php
                                                            $createUnitRoute = route('property-units.create', ['property_id' => $property->id, 'tenant_id' => $tenant->id]);
                                                        @endphp
                                                        <a href="{{ $createUnitRoute }}" 
                                                           class="text-sm font-medium hover:underline inline-flex items-center" style="color: var(--primary);">
                                                            <i class="fas fa-plus-circle mr-1"></i> Create Unit & Assign
                                                        </a>
                                                    @endif
                                                @endcan
                                            </div>
                                        </div>
                                        @endif
                                        
                                        <!-- Tenant Registration & Status -->
                                        <div class="flex items-center flex-wrap gap-2 mt-3">
                                            <div class="flex items-center space-x-1">
                                                @if($tenant->email_verified_at)
                                                    <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                          style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1"></i> Registered
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                          style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                        <i class="fas fa-clock mr-1"></i> Pending Registration
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <div class="flex items-center space-x-1">
                                                @if($tenant->status === 'active')
                                                    <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                          style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                        <i class="fas fa-circle mr-1 text-xs"></i> Active
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                                                          style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                        <i class="fas fa-circle mr-1 text-xs"></i> {{ ucfirst($tenant->status) }}
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            @if($tenant->created_at)
                                            <div class="flex items-center space-x-1">
                                                <span class="px-2 py-1 rounded-full text-xs" 
                                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--text-secondary);">
                                                    <i class="fas fa-calendar-plus mr-1"></i> 
                                                    {{ $tenant->created_at->format('M d, Y') }}
                                                </span>
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Pivot Information -->
                                        @if($tenant->pivot)
                                        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                                            <p>
                                                <i class="fas fa-calendar-plus mr-1"></i>
                                                Added on: {{ $tenant->pivot->added_at ? \Carbon\Carbon::parse($tenant->pivot->added_at)->format('M d, Y h:i A') : 'N/A' }}
                                                @if($tenant->pivot->notes)
                                                    • <i class="fas fa-sticky-note ml-2 mr-1"></i> {{ $tenant->pivot->notes }}
                                                @endif
                                            </p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Action Buttons for Field Agent -->
                                @can('update', $property)
                                <div class="flex flex-wrap gap-2 md:flex-col md:min-w-[120px]">
                                    <!-- Contact Tenant -->
                                    <a href="tel:{{ $tenant->phone }}" 
                                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                       title="Call tenant">
                                        <i class="fas fa-phone mr-1"></i> Call
                                    </a>
                                    
                                    @if($tenant->email)
                                    <a href="mailto:{{ $tenant->email }}" 
                                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);"
                                       title="Email tenant">
                                        <i class="fas fa-envelope mr-1"></i> Email
                                    </a>
                                    @endif
                                    
                                    @if($property->isRegisteredBy(auth()->id()))
                                        <!-- Resend Invitation -->
                                        <button onclick="resendTenantInvitation({{ $tenant->id }})" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                                title="Resend invitation">
                                            <i class="fas fa-paper-plane mr-1"></i> Resend
                                        </button>
                                        
                                        <!-- Remove Tenant -->
                                        <button onclick="confirmRemoveTenant({{ $tenant->id }}, '{{ $tenant->name }}')" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                                title="Remove tenant">
                                            <i class="fas fa-user-minus mr-1"></i> Remove
                                        </button>
                                        
                                        @if(!$isAssigned)
                                        <!-- Create Unit & Assign -->
                                        @php
                                            $createUnitRoute = route('property-units.create', ['property_id' => $property->id, 'tenant_id' => $tenant->id]);
                                        @endphp
                                        <a href="{{ $createUnitRoute }}" 
                                           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                            <i class="fas fa-plus-circle mr-1"></i> Assign Unit
                                        </a>
                                        @endif
                                        
                                        @if($isAssigned && $unitStatus === 'pending_approval')
                                        <!-- Approve Unit Assignment (Field Agent can also approve if they have permission) -->
                                        @can('approve', $assignedUnit)
                                        <button onclick="approveUnitAssignment({{ $assignedUnit->id }}, '{{ $tenant->name }}')" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                            <i class="fas fa-check-circle mr-1"></i> Approve
                                        </button>
                                        @endcan
                                        @endif
                                        
                                        @if($isAssigned && $unitStatus === 'approved')
                                        <!-- Vacate Tenant -->
                                        <button onclick="showVacateModal({{ $assignedUnit->id }}, '{{ $unitNumber }}', '{{ $tenant->name }}', {{ $assignedUnit->security_deposit ?? 0 }})" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                            <i class="fas fa-sign-out-alt mr-1"></i> Vacate
                                        </button>
                                        @endif
                                        
                                        @if($isAssigned && $unitStatus === 'vacated')
                                        <!-- Reassign Tenant -->
                                        <button onclick="reassignTenant({{ $tenant->id }}, {{ $assignedUnit->id }})" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                            <i class="fas fa-sync-alt mr-1"></i> Reassign
                                        </button>
                                        @endif
                                    @endif
                                    
                                    @if($isAssigned && $unitStatus === 'approved')
                                    <!-- View Unit Details -->
                                    <a href="{{ route('property-units.show', $assignedUnit->id) }}" 
                                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center w-full"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-door-open mr-1"></i> View Unit
                                    </a>
                                    @endif
                                </div>
                                @endcan
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <!-- Tenant Summary -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <div class="text-xl font-bold" style="color: var(--success);">
                                {{ $allTenants->where('email_verified_at', '!==', null)->count() }}
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Registered Tenants</p>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <div class="text-xl font-bold" style="color: var(--warning);">
                                {{ $allTenants->where('email_verified_at', null)->count() }}
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Pending Tenants</p>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="text-xl font-bold" style="color: var(--info);">
                                {{ $allTenants->where('status', 'active')->count() }}
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Active Tenants</p>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <div class="text-xl font-bold" style="color: var(--primary);">
                                {{ $property->units()->where('tenant_status', 'approved')->count() }}
                            </div>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Occupied Units</p>
                        </div>
                    </div>
                    
                    <!-- Add Tenant Button -->
                    @can('update', $property)
                    @if($property->isRegisteredBy(auth()->id()))
                    <div class="mt-6">
                        <button onclick="showAddTenantModal()" class="btn-primary w-full flex items-center justify-center">
                            <i class="fas fa-user-plus mr-2"></i> Add New Tenant
                        </button>
                    </div>
                    @endif
                    @endcan
                    
                @else
                    <!-- No Tenants -->
                    <div class="text-center py-8">
                        <i class="fas fa-users text-4xl mb-4" style="color: var(--text-secondary);"></i>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-secondary);">No Tenants Assigned</h4>
                        <p class="mb-4" style="color: var(--text-secondary);">This property is currently vacant. Add tenants to track occupancy.</p>
                        
                        @can('update', $property)
                        @if($property->isRegisteredBy(auth()->id()))
                            <button onclick="showAddTenantModal()" class="btn-primary inline-flex items-center">
                                <i class="fas fa-user-plus mr-2"></i> Add Tenant
                            </button>
                        @endif
                        @endcan
                    </div>
                @endif
            </div>
            <!-- ====================================================== -->
            <!-- END ENHANCED TENANTS SECTION                           -->
            <!-- ====================================================== -->

            <!-- Units Overview Section -->
            @if($property->units()->count() > 0)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-door-open mr-2"></i>Units Overview
                    <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">
                        ({{ $property->units()->count() }} unit(s))
                    </span>
                </h3>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="text-2xl font-bold" style="color: var(--info);">{{ $property->units()->count() }}</div>
                        <p class="text-xs" style="color: var(--text-secondary);">Total Units</p>
                    </div>
                    
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div class="text-2xl font-bold" style="color: var(--success);">{{ $property->units()->where('tenant_status', 'approved')->count() }}</div>
                        <p class="text-xs" style="color: var(--text-secondary);">Occupied</p>
                    </div>
                    
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ $property->units()->where('tenant_status', 'pending_approval')->count() }}</div>
                        <p class="text-xs" style="color: var(--text-secondary);">Pending Approval</p>
                    </div>
                    
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="text-2xl font-bold" style="color: var(--danger);">{{ $property->units()->where('tenant_status', 'vacated')->count() }}</div>
                        <p class="text-xs" style="color: var(--text-secondary);">Vacated</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Registration Plan Information -->
            @if($property->registrationPlan)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-map-marked-alt mr-2"></i>Registration Plan Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="property-field">
                            <label class="field-label">Plan Reference</label>
                            <p class="field-value">#{{ $property->registrationPlan->id }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Zone</label>
                            <p class="field-value">{{ $property->registrationPlan->zone }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Section</label>
                            <p class="field-value">{{ $property->registrationPlan->section ?? 'No Section' }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Starting Point</label>
                            <p class="field-value">{{ $property->registrationPlan->starting_point }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="property-field">
                            <label class="field-label">Naming Pattern</label>
                            <p class="field-value font-mono">{{ $property->registrationPlan->naming_pattern }}</p>
                            <p class="field-subtext">Used to generate registration patterns</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Sequence Type</label>
                            <p class="field-value capitalize">{{ str_replace('_', ' ', $property->registrationPlan->sequence_type) }}</p>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Plan Status</label>
                            @php
                                $planStatusColors = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'draft' => 'warning',
                                    'completed' => 'info'
                                ];
                                $planStatusColor = $planStatusColors[$property->registrationPlan->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $planStatusColor }}-rgb), 0.2); color: var(--{{ $planStatusColor }});">
                                {{ ucfirst(str_replace('_', ' ', $property->registrationPlan->status)) }}
                            </span>
                        </div>
                        
                        <div class="property-field">
                            <label class="field-label">Registration Progress</label>
                            <div class="space-y-2">
                                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                    @php
                                        $progress = ($property->registrationPlan->houses_registered / max($property->registrationPlan->estimated_houses, 1)) * 100;
                                    @endphp
                                    <div class="h-2 rounded-full transition-all duration-300" style="width: {{ $progress }}%; background-color: var(--success);"></div>
                                </div>
                                <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
                                    <span>{{ $property->registrationPlan->houses_registered }} registered</span>
                                    <span>{{ $property->registrationPlan->estimated_houses }} estimated</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Your Assignment Progress -->
                @if($property->registrationPlan->planAssignments->where('agent_id', auth()->id())->where('is_active', true)->first())
                @php
                    $assignment = $property->registrationPlan->planAssignments->where('agent_id', auth()->id())->where('is_active', true)->first();
                @endphp
                <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <label class="field-label" style="color: var(--primary);">Your Assignment Progress</label>
                    <div class="space-y-3 mt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Properties Registered</span>
                            <span class="font-semibold" style="color: var(--primary);">{{ $assignment->properties_registered }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Last Activity</span>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                {{ $assignment->last_activity_at ? $assignment->last_activity_at->diffForHumans() : 'Never' }}
                            </span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <!-- Inspection History -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-clipboard-check mr-2"></i>Inspection History
                </h3>
                
                <div class="space-y-4">
                    <div class="property-field">
                        <label class="field-label">Last Inspection Date</label>
                        @if($property->last_inspection_date)
                            <p class="field-value">{{ $property->last_inspection_date->format('M d, Y') }}</p>
                            <p class="field-subtext">{{ $property->last_inspection_date->diffForHumans() }}</p>
                            
                            @if($property->isInspectionOverdue())
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                <p class="text-sm" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Inspection overdue
                                </p>
                            </div>
                            @endif
                        @else
                            <p class="field-value" style="color: var(--warning);">No inspections recorded</p>
                            <p class="field-subtext">This site allocation has not been inspected yet</p>
                        @endif
                    </div>
                    
                    @can('update', $property)
                    <div class="mt-4 p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <label class="field-label">Update Inspection Date</label>
                        <form action="{{ route('field-agent.properties.update-inspection-date', $property->id) }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="date" name="last_inspection_date" class="flex-1 p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('last_inspection_date', $property->last_inspection_date ? $property->last_inspection_date->format('Y-m-d') : '') }}">
                            <button type="submit" class="btn-secondary whitespace-nowrap">
                                <i class="fas fa-calendar-check mr-2"></i> Update
                            </button>
                        </form>
                    </div>
                    @endcan
                </div>
            </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions Card -->
            @can('update', $property)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-bolt mr-2"></i>Quick Actions
                </h3>
                
                <div class="space-y-3">
                    <a href="{{ route('field-agent.properties.edit', $property->id) }}" class="action-button btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit Site Details
                    </a>
                    
                    @if($property->photos && $property->photos->count() > 0)
                        <button onclick="openPhotoGallerySlideshow()" class="action-button btn-secondary w-full">
                            <i class="fas fa-play mr-2"></i> View Slideshow
                        </button>
                    @endif
                    
                    @if($property->isRegisteredBy(auth()->id()))
                        @if($allTenants->count() > 0)
                            <button onclick="showAddTenantModal()" class="action-button btn-secondary w-full">
                                <i class="fas fa-user-plus mr-2"></i> Add Another Tenant
                            </button>
                        @else
                            <button onclick="showAddTenantModal()" class="action-button btn-secondary w-full">
                                <i class="fas fa-user-plus mr-2"></i> Add Tenant
                            </button>
                        @endif
                    @endif
                    
                    @if($property->digital_address)
                        <button onclick="copyDigitalAddress()" class="action-button btn-secondary w-full">
                            <i class="fas fa-copy mr-2"></i> Copy Digital Address
                        </button>
                    @endif
                    
                    <a href="https://maps.google.com/?q={{ urlencode($property->street_name . ' ' . $property->house_number . ' ' . $property->zone) }}" 
                       target="_blank" class="action-button btn-secondary">
                        <i class="fas fa-map-marker-alt mr-2"></i> View on Google Maps
                    </a>
                    
                    @if($property->isRegisteredBy(auth()->id()))
                    <form action="{{ route('field-agent.properties.destroy', $property->id) }}" method="POST" class="w-full">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-button btn-danger w-full" onclick="return confirm('Are you sure you want to delete this site allocation? This action cannot be undone.')">
                            <i class="fas fa-trash mr-2"></i> Delete Site Allocation
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endcan

            <!-- Tenants Summary Card -->
            @if($allTenants->count() > 0)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-users mr-2"></i>Tenants Overview
                </h3>
                
                <div class="space-y-4">
                    <!-- Occupancy Rate -->
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <div class="text-2xl font-bold" style="color: var(--success);">
                            {{ $allTenants->count() }}
                        </div>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">Current Tenants</p>
                        <div class="mt-2 w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                            @php
                                $maxCapacity = $property->propertyType->max_capacity ?? 5;
                                $occupancy = min(100, ($allTenants->count() / max($maxCapacity, 1)) * 100);
                            @endphp
                            <div class="h-2 rounded-full transition-all duration-300" style="width: {{ $occupancy }}%; background-color: var(--success);"></div>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ round($occupancy) }}% occupancy
                        </p>
                    </div>
                    
                    <!-- Tenant Status Breakdown -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>Registered
                            </span>
                            <span class="font-semibold" style="color: var(--success);">
                                {{ $allTenants->where('email_verified_at', '!==', null)->count() }}
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>Pending
                            </span>
                            <span class="font-semibold" style="color: var(--warning);">
                                {{ $allTenants->where('email_verified_at', null)->count() }}
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-circle mr-2" style="color: var(--success);"></i>Active
                            </span>
                            <span class="font-semibold" style="color: var(--success);">
                                {{ $allTenants->where('status', 'active')->count() }}
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-door-open mr-2" style="color: var(--primary);"></i>Occupied Units
                            </span>
                            <span class="font-semibold" style="color: var(--primary);">
                                {{ $property->units()->where('tenant_status', 'approved')->count() }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Quick Contact -->
                    <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                        <label class="field-label mb-2">Quick Contact</label>
                        <div class="space-y-2">
                            @foreach($allTenants->take(2) as $tenant)
                            <div class="flex items-center justify-between p-2 rounded" style="background-color: var(--bg-secondary);">
                                <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $tenant->name }}</span>
                                <div class="flex space-x-1">
                                    <a href="tel:{{ $tenant->phone }}" class="p-1 rounded" title="Call" style="color: var(--info);">
                                        <i class="fas fa-phone text-xs"></i>
                                    </a>
                                    @if($tenant->email)
                                    <a href="mailto:{{ $tenant->email }}" class="p-1 rounded" title="Email" style="color: var(--primary);">
                                        <i class="fas fa-envelope text-xs"></i>
                                    </a>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                            @if($allTenants->count() > 2)
                            <p class="text-xs text-center" style="color: var(--text-secondary);">
                                +{{ $allTenants->count() - 2 }} more tenants
                            </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Property Type Details Card -->
            @if($property->propertyType)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-tags mr-2"></i>Property Type Details
                </h3>
                
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3" 
                             style="background-color: {{ $property->propertyType->color }}20; border: 2px solid {{ $property->propertyType->color }}30;">
                            <i class="{{ $property->propertyType->icon }} text-2xl" style="color: {{ $property->propertyType->color }};"></i>
                        </div>
                        <h4 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $property->propertyType->name }}</h4>
                        
                        @if($property->propertyType->is_custom)
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold mt-1" 
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-pencil-alt mr-1"></i> Custom Type
                            </span>
                        @else
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold mt-1" 
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-star mr-1"></i> Standard Type
                            </span>
                        @endif
                    </div>
                    
                    @if($property->custom_property_type)
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <label class="field-label mb-2">Custom Property Type</label>
                        <p class="font-semibold text-center" style="color: var(--info);">{{ $property->custom_property_type }}</p>
                    </div>
                    @endif
                    
                    @if($property->propertyType->description)
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <label class="field-label mb-2">Description</label>
                        <p class="text-sm" style="color: var(--text-primary); line-height: 1.5;">{{ $property->propertyType->description }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Landlord Information -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-user-tie mr-2"></i>Property Owner
                </h3>
                
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-user text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $property->landlord->name }}</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-house-user mr-1"></i>Site Allocation Owner
                        </p>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-2">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-phone mr-2"></i>Phone
                            </span>
                            <a href="tel:{{ $property->landlord->phone }}" class="font-semibold transition-colors" style="color: var(--text-primary);">
                                {{ $property->landlord->phone }}
                            </a>
                        </div>
                        
                        <div class="flex items-center justify-between p-2">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </span>
                            <a href="mailto:{{ $property->landlord->email }}" class="font-semibold text-sm transition-colors" style="color: var(--text-primary);">
                                {{ $property->landlord->email }}
                            </a>
                        </div>
                    </div>
                    
                    <!-- Site Allocation Count -->
                    <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                        <div class="text-center">
                            <p class="text-sm" style="color: var(--text-secondary);">Total Site Allocations</p>
                            <p class="text-xl font-bold" style="color: var(--primary);">
                                {{ $property->landlord->properties_count ?? $property->landlord->properties->count() }}
                            </p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                        <div class="grid grid-cols-2 gap-2">
                            <a href="mailto:{{ $property->landlord->email }}" class="btn-secondary text-center text-sm py-2">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </a>
                            <a href="tel:{{ $property->landlord->phone }}" class="btn-secondary text-center text-sm py-2">
                                <i class="fas fa-phone mr-1"></i> Call
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Property Metadata -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-info-circle mr-2"></i>Site Metadata
                </h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Site ID</span>
                        <span class="font-mono text-sm px-2 py-1 rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">#{{ $property->id }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Registration Pattern</span>
                        <span class="font-mono text-xs px-2 py-1 rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            {{ $property->registration_pattern ?? 'N/A' }}
                        </span>
                    </div>
                    
                    <!-- Property Type in Metadata -->
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Property Type</span>
                        <span class="text-sm font-semibold" style="color: var(--text-primary);">
                            @if($property->propertyType)
                                {{ $property->propertyType->name }}
                            @else
                                <span style="color: var(--warning);">Not set</span>
                            @endif
                        </span>
                    </div>
                    
                    <!-- Tenants Count -->
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Current Tenants</span>
                        <div class="text-right">
                            <span class="text-sm font-semibold" style="color: {{ $allTenants->count() > 0 ? 'var(--success)' : 'var(--warning)' }};">
                                {{ $allTenants->count() }}
                            </span>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $allTenants->count() > 0 ? 'Occupied' : 'Vacant' }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Units Count -->
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Units</span>
                        <div class="text-right">
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">
                                {{ $property->units()->count() }}
                            </span>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $property->units()->where('tenant_status', 'approved')->count() }} occupied
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Created</span>
                        <div class="text-right">
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $property->created_at->format('M d, Y') }}</span>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $property->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Last Updated</span>
                        <div class="text-right">
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $property->updated_at->format('M d, Y') }}</span>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $property->updated_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    
                    @if($property->isRegisteredBy(auth()->id()))
                    <div class="flex justify-between items-center p-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Registered By</span>
                        <span class="text-sm font-semibold" style="color: var(--success);">You</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Status Overview -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="fas fa-chart-bar mr-2"></i>Site Overview
                </h3>
                
                <div class="space-y-4">
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--{{ $property->status === 'active' ? 'success' : 'secondary' }}-rgb), 0.1);">
                        <div class="text-2xl font-bold mb-1" style="color: var(--{{ $property->status === 'active' ? 'success' : 'secondary' }});">
                            @if($property->status === 'active')
                                <i class="fas fa-check-circle"></i>
                            @else
                                <i class="fas fa-pause-circle"></i>
                            @endif
                        </div>
                        <p class="text-sm font-semibold capitalize" style="color: var(--{{ $property->status === 'active' ? 'success' : 'secondary' }});">
                            {{ $property->status }} Site
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--info);">{{ $property->registration_date->format('Y') }}</div>
                            <p class="text-xs" style="color: var(--info);">Registered</p>
                        </div>
                        
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--purple-rgb, 128, 90, 213), 0.1);">
                            <div class="text-lg font-bold" style="color: var(--purple, #805AD5);">
                                {{ $property->photos ? $property->photos->count() : 0 }}
                            </div>
                            <p class="text-xs" style="color: var(--purple, #805AD5);">Photos</p>
                        </div>
                    </div>

                    <!-- Your Registration Badge -->
                    @if($property->isRegisteredBy(auth()->id()))
                    <div class="text-center p-3 rounded-lg border" style="border-color: rgba(var(--success-rgb), 0.3); background-color: rgba(var(--success-rgb), 0.1);">
                        <div class="text-sm font-semibold" style="color: var(--success);">
                            <i class="fas fa-user-check mr-1"></i>You Registered This Property
                        </div>
                    </div>
                    @endif

                    <!-- Global Sequence Badge -->
                    @if($property->is_global_sequence)
                    <div class="text-center p-3 rounded-lg border" style="border-color: rgba(var(--warning-rgb), 0.3); background-color: rgba(var(--warning-rgb), 0.1);">
                        <div class="text-sm font-semibold" style="color: var(--warning);">
                            <i class="fas fa-globe mr-1"></i>Global Sequence Property
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Position: {{ $property->getSequencePosition() }}
                        </p>
                    </div>
                    @endif

                    <!-- Ownership Badge -->
                    <div class="text-center p-3 rounded-lg border" style="border-color: rgba(var(--primary-rgb), 0.3); background-color: rgba(var(--primary-rgb), 0.1);">
                        <div class="text-sm font-semibold" style="color: var(--primary);">
                            <i class="fas fa-house-user mr-1"></i>Owned by {{ $property->landlord->name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Tenant Modal -->
@can('update', $property)
@if($property->isRegisteredBy(auth()->id()))
<div id="addTenantModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-card);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-user-plus mr-2"></i>Add New Tenant
        </h3>
        
        <form id="addTenantForm" method="POST" action="{{ route('field-agent.properties.add-tenants', $property->id) }}">
            @csrf
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Tenant Name *</label>
                    <input type="text" name="tenants[0][name]" required 
                           class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Phone Number *</label>
                    <input type="tel" name="tenants[0][phone]" required 
                           class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="+233XXXXXXXXX">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Email Address</label>
                    <input type="email" name="tenants[0][email]" 
                           class="w-full p-2 border rounded" 
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Gender</label>
                    <select name="tenants[0][gender]" 
                            class="w-full p-2 border rounded"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Invitation Channels *</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="tenants[0][channels][]" value="sms" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-primary);">SMS</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="tenants[0][channels][]" value="email" class="mr-2">
                            <span class="text-sm" style="color: var(--text-primary);">Email</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="tenants[0][channels][]" value="whatsapp" class="mr-2">
                            <span class="text-sm" style="color: var(--text-primary);">WhatsApp</span>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Notes</label>
                    <textarea name="tenants[0][notes]" rows="3" 
                              class="w-full p-2 border rounded"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="hideAddTenantModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium text-white transition-colors"
                        style="background-color: var(--primary);">
                    <i class="fas fa-user-plus mr-2"></i> Add Tenant
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan

<!-- Vacate Modal -->
@can('update', $property)
@if($property->isRegisteredBy(auth()->id()))
<div id="vacateModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideVacateModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto" style="background-color: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="modal-header flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sign-out-alt mr-2" style="color: var(--warning);"></i> Mark Tenant as Vacated
                </h3>
                <button type="button" onclick="hideVacateModal()" class="modal-close-btn p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            
            <form id="vacateForm" method="POST" action="">
                @csrf
                @method('PUT')
                
                <div class="modal-body p-6 space-y-6">
                    <!-- Unit Info Preview -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Unit Information</h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Unit:</span>
                                <span class="ml-2 font-medium" style="color: var(--text-primary);" id="modalUnitIdentifier"></span>
                            </div>
                            <div>
                                <span class="text-xs" style="color: var(--text-secondary);">Tenant:</span>
                                <span class="ml-2 font-medium" style="color: var(--text-primary);" id="modalTenantName"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Move-out Date -->
                    <div>
                        <label for="modal_move_out_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Move-out Date <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar" style="color: var(--text-secondary);"></i>
                            </div>
                            <input type="date" 
                                   id="modal_move_out_date" 
                                   name="move_out_date" 
                                   value="{{ date('Y-m-d') }}"
                                   class="index-custom-input pl-10 w-full"
                                   required
                                   max="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- Reason for Vacating -->
                    <div>
                        <label for="modal_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Vacating <span class="text-danger ml-1">*</span>
                        </label>
                        <select id="modal_reason" name="reason" class="index-custom-dropdown w-full" required>
                            <option value="">Select reason...</option>
                            <option value="lease_ended">Lease Ended</option>
                            <option value="tenant_requested">Tenant Requested Early Move-out</option>
                            <option value="mutual_agreement">Mutual Agreement</option>
                            <option value="eviction">Eviction</option>
                            <option value="transfer">Company Transfer</option>
                            <option value="personal_reasons">Personal Reasons</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Additional Notes -->
                    <div>
                        <label for="modal_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Notes
                        </label>
                        <textarea id="modal_notes" name="notes" rows="2" 
                                  class="index-custom-textarea w-full"
                                  placeholder="Any additional information..."></textarea>
                    </div>

                    <!-- Confirmation Checkbox -->
                    <div class="p-4 rounded-lg border" 
                         style="background: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <input type="checkbox" 
                                   id="modal_confirm_action" 
                                   name="confirm_action" 
                                   value="1"
                                   class="h-4 w-4 mt-1 rounded focus:ring-2 focus:ring-primary"
                                   style="color: var(--danger);"
                                   required>
                            <label for="modal_confirm_action" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-exclamation-triangle mr-1 text-danger"></i>
                                <span class="font-medium">I confirm that the tenant has vacated the unit.</span>
                                <span class="text-danger ml-1 text-xs block mt-1">* Required</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer p-6 border-t flex justify-end gap-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="hideVacateModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white"
                            style="background: linear-gradient(to right, var(--warning), #ff9f43);">
                        <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

@endsection

@section('scripts')
<script>
// ==================== PHOTO GALLERY FUNCTIONALITY ====================

let currentPhotoIndex = 0;
let photosArray = [];

@if($property->photos && $property->photos->count() > 0)
    photosArray = @json($property->photos->map(function($photo) {
        return [
            'url' => Storage::url($photo->photo_path),
            'caption' => $photo->is_primary ? 'Primary Photo' : 'Property Photo'
        ];
    }));
    
    // Ensure primary photo is first in the array for gallery
    const primaryIndex = photosArray.findIndex(p => p.caption === 'Primary Photo');
    if (primaryIndex > 0) {
        const primaryPhoto = photosArray[primaryIndex];
        photosArray.splice(primaryIndex, 1);
        photosArray.unshift(primaryPhoto);
    }
@endif

function openPhotoModal(imageUrl, caption) {
    const modal = document.getElementById('photoModal');
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    
    // Find the index of the clicked photo
    currentPhotoIndex = photosArray.findIndex(p => p.url === imageUrl);
    if (currentPhotoIndex === -1) {
        currentPhotoIndex = 0;
    }
    
    modalImage.src = imageUrl;
    modalCaption.textContent = caption + (photosArray.length > 1 ? ` (${currentPhotoIndex + 1} of ${photosArray.length})` : '');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Update navigation buttons visibility
    updateNavButtons();
}

function closePhotoModal() {
    const modal = document.getElementById('photoModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function nextPhoto() {
    if (currentPhotoIndex < photosArray.length - 1) {
        currentPhotoIndex++;
        updateModalPhoto();
    }
}

function previousPhoto() {
    if (currentPhotoIndex > 0) {
        currentPhotoIndex--;
        updateModalPhoto();
    }
}

function updateModalPhoto() {
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    const photo = photosArray[currentPhotoIndex];
    
    modalImage.src = photo.url;
    modalCaption.textContent = photo.caption + ` (${currentPhotoIndex + 1} of ${photosArray.length})`;
    updateNavButtons();
}

function updateNavButtons() {
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    
    if (photosArray.length <= 1) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        return;
    }
    
    if (prevBtn) {
        prevBtn.style.display = currentPhotoIndex === 0 ? 'none' : 'flex';
    }
    if (nextBtn) {
        nextBtn.style.display = currentPhotoIndex === photosArray.length - 1 ? 'none' : 'flex';
    }
}

function downloadCurrentPhoto() {
    const currentPhoto = photosArray[currentPhotoIndex];
    if (currentPhoto && currentPhoto.url) {
        const link = document.createElement('a');
        link.href = currentPhoto.url;
        link.download = currentPhoto.url.split('/').pop() || 'property-photo.jpg';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('Download started!', 'success');
    }
}

function downloadAllPhotos() {
    if (photosArray.length === 0) return;
    
    showNotification('Preparing download...', 'info');
    
    photosArray.forEach((photo, index) => {
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = photo.url;
            link.download = `property-photo-${index + 1}.jpg`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }, index * 500);
    });
    
    setTimeout(() => {
        showNotification(`Download started for ${photosArray.length} photos`, 'success');
    }, 500);
}

function openPhotoGallerySlideshow() {
    if (photosArray.length > 0) {
        openPhotoModal(photosArray[0].url, photosArray[0].caption);
        let slideInterval = setInterval(() => {
            if (currentPhotoIndex < photosArray.length - 1) {
                nextPhoto();
            } else {
                clearInterval(slideInterval);
            }
        }, 3000);
        
        const modal = document.getElementById('photoModal');
        const originalClose = closePhotoModal;
        window.closePhotoModal = function() {
            clearInterval(slideInterval);
            originalClose();
        };
    }
}

document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('photoModal');
    if (modal && !modal.classList.contains('hidden')) {
        if (e.key === 'Escape') {
            closePhotoModal();
        } else if (e.key === 'ArrowRight') {
            nextPhoto();
        } else if (e.key === 'ArrowLeft') {
            previousPhoto();
        }
    }
});

// ==================== TENANT MANAGEMENT FUNCTIONS ====================

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
});

function copyDigitalAddress() {
    const digitalAddress = '{{ $property->digital_address }}';
    if (!digitalAddress) {
        showNotification('No digital address to copy', 'error');
        return;
    }
    
    navigator.clipboard.writeText(digitalAddress).then(function() {
        showNotification('Digital address copied to clipboard!', 'success');
    }).catch(function(err) {
        console.error('Failed to copy digital address: ', err);
        showNotification('Failed to copy address!', 'error');
    });
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transition-opacity duration-300';
    
    if (type === 'success') {
        notification.style.backgroundColor = 'var(--success)';
        notification.innerHTML = '<i class="fas fa-check-circle mr-2"></i> ' + message;
    } else if (type === 'error') {
        notification.style.backgroundColor = 'var(--danger)';
        notification.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i> ' + message;
    } else {
        notification.style.backgroundColor = 'var(--info)';
        notification.innerHTML = '<i class="fas fa-info-circle mr-2"></i> ' + message;
    }
    
    notification.style.color = 'white';
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Tenant Management Functions
function showAddTenantModal() {
    const modal = document.getElementById('addTenantModal');
    if (modal) modal.classList.remove('hidden');
}

function hideAddTenantModal() {
    const modal = document.getElementById('addTenantModal');
    if (modal) modal.classList.add('hidden');
}

function resendTenantInvitation(tenantId) {
    if (confirm('Resend invitation to this tenant?')) {
        fetch(`/field-agent/properties/{{ $property->id }}/tenants/${tenantId}/resend-invitation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                channels: ['sms', 'email', 'whatsapp']
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Invitation resent successfully!', 'success');
            } else {
                showNotification('Failed to resend invitation: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while resending invitation.', 'error');
        });
    }
}

function confirmRemoveTenant(tenantId, tenantName) {
    if (confirm(`Are you sure you want to remove ${tenantName} from this property?`)) {
        fetch(`/field-agent/properties/{{ $property->id }}/tenants/${tenantId}/remove`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Tenant removed successfully!', 'success');
                location.reload();
            } else {
                showNotification('Failed to remove tenant: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while removing tenant.', 'error');
        });
    }
}

// Approve Unit Assignment
function approveUnitAssignment(unitId, tenantName) {
    if (confirm(`Are you sure you want to approve the unit assignment for ${tenantName}?`)) {
        fetch(`/field-agent/property-units/${unitId}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Unit assignment approved successfully!', 'success');
                location.reload();
            } else {
                showNotification('Failed to approve: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while approving.', 'error');
        });
    }
}

// Reassign Tenant
function reassignTenant(tenantId, oldUnitId) {
    if (confirm('Do you want to reassign this tenant to a new unit?')) {
        window.location.href = '{{ route('property-units.create') }}?tenant_id=' + tenantId + '&property_id={{ $property->id }}&reassign=true&old_unit=' + oldUnitId;
    }
}

// Vacate Modal Functions
function showVacateModal(unitId, unitNumber, tenantName, securityDeposit) {
    const modal = document.getElementById('vacateModal');
    const form = document.getElementById('vacateForm');
    
    // Set the correct route for field agent
    form.action = '/field-agent/property-units/' + unitId + '/mark-vacated';
    
    // Set unit info
    document.getElementById('modalUnitIdentifier').textContent = unitNumber;
    document.getElementById('modalTenantName').textContent = tenantName;
    
    // Reset form
    if (form) form.reset();
    
    // Set today's date as default for move-out
    const moveOutInput = document.getElementById('modal_move_out_date');
    if (moveOutInput) {
        const today = new Date().toISOString().split('T')[0];
        moveOutInput.value = today;
    }
    
    // Uncheck confirmation checkbox
    const confirmCheckbox = document.getElementById('modal_confirm_action');
    if (confirmCheckbox) confirmCheckbox.checked = false;
    
    // Show modal
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideVacateModal() {
    const modal = document.getElementById('vacateModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Close modals when clicking outside
const addTenantModal = document.getElementById('addTenantModal');
if (addTenantModal) {
    addTenantModal.addEventListener('click', function(e) {
        if (e.target === this) {
            hideAddTenantModal();
        }
    });
}

const photoModal = document.getElementById('photoModal');
if (photoModal) {
    photoModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closePhotoModal();
        }
    });
}

const vacateModal = document.getElementById('vacateModal');
if (vacateModal) {
    vacateModal.addEventListener('click', function(e) {
        if (e.target === this) {
            hideVacateModal();
        }
    });
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideAddTenantModal();
        hideVacateModal();
        closePhotoModal();
    }
});
</script>

<style>
/* ==================== PHOTO GALLERY STYLES ==================== */

.photo-gallery {
    width: 100%;
}

.primary-photo-container {
    position: relative;
}

.primary-photo-container img {
    transition: transform 0.3s ease;
}

.primary-photo-container img:hover {
    transform: scale(1.01);
}

.thumbnail-item {
    transition: all 0.3s ease;
    position: relative;
}

.thumbnail-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Photo Modal/Navigation Styles */
.photo-nav-buttons {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    pointer-events: none;
}

.nav-btn {
    position: absolute;
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    pointer-events: auto;
    backdrop-filter: blur(5px);
}

.nav-btn:hover {
    background-color: rgba(0, 0, 0, 0.8);
    transform: scale(1.1);
}

.prev-btn {
    left: 20px;
}

.next-btn {
    right: 20px;
}

/* Property Field Styles */
.property-field {
    margin-bottom: 1rem;
}

.field-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.field-value {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.field-subtext {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin: 0.25rem 0 0 0;
}

/* Tenant Status Badge Styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.15) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.15) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.15) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.15) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

/* Button Styles */
.action-button {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    width: 100%;
    text-align: center;
}

.action-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    background-color: var(--bg-tertiary);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Card Styles */
.card {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    background-color: var(--bg-card);
}

.tenant-card {
    transition: all 0.2s;
}

.tenant-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Modal Styles */
.modal-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-close-btn {
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: background-color 0.2s;
    cursor: pointer;
    background: none;
    border: none;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Index Custom Input Styles */
.index-custom-input {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-textarea {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .flex.justify-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .flex.space-x-2 {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .tenant-card .flex-col.md\:flex-row {
        flex-direction: column;
    }
    
    .tenant-card .flex-wrap.gap-2 {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .tenant-card .md\:flex-col {
        flex-direction: row;
        flex-wrap: wrap;
        width: 100%;
    }
    
    .tenant-card .md\:flex-col .w-full {
        width: auto;
        flex: 1;
        min-width: 80px;
    }
    
    .nav-btn {
        width: 40px;
        height: 40px;
    }
    
    .prev-btn {
        left: 10px;
    }
    
    .next-btn {
        right: 10px;
    }
    
    .nav-btn i {
        font-size: 1.5rem;
    }
}

/* Loading Animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

/* Modal Animation */
#photoModal {
    transition: opacity 0.3s ease;
}

#photoModal img {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
@endsection