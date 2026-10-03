{{-- resources/views/property_units/assign-tenant.blade.php --}}
@php
    $layout = auth()->user()->isLandlord() ? 'layouts.landlord' : 'layouts.app';
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin();
    
    // Safely access variables with fallback
    $availableTenants = $availableTenants ?? collect();
    $propertyTenants = $propertyTenants ?? collect();
    $currentlyAssigned = $currentlyAssigned ?? collect();
    
    // Page title
    $pageTitle = "Assign Tenant to Unit {$unit->unit_number}";
    
    // Success/error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $validationErrors = $errors ?? collect();
    
    // Max file size for uploads
    $maxFileSize = $maxFileSize ?? 5120; // Default 5MB
    $allowedDocuments = $allowedDocuments ?? ['id_card', 'passport', 'drivers_license', 'employment_letter', 'bank_statement', 'reference_letter'];
    
    // Document type labels
    $documentLabels = [
        'id_card' => 'Government ID Card',
        'passport' => 'Passport',
        'drivers_license' => "Driver's License", 
        'employment_letter' => 'Employment Letter',
        'bank_statement' => 'Bank Statement',
        'reference_letter' => 'Reference Letter',
        'other' => 'Other Document'
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
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-check mr-2" style="color: var(--primary);"></i> 
                        Assign Tenant
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>{{ $unit->property->property_name }} - Unit {{ $unit->unit_number }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-money-bill-wave mr-1"></i>
                        <span>GHS {{ number_format($unit->monthly_rent, 2) }}/month</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
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
                <a href="{{ route('property-units.show', $unit->id) }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Unit Details
                </a>
                
                <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                    <span class="px-2 py-1 rounded-full mr-2" 
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-info-circle mr-1"></i> Requires Admin Approval
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Unit Information Card -->
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-home mr-2"></i> Unit Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Property</div>
                    <div class="font-semibold flex items-center" style="color: var(--primary);">
                        <i class="fas fa-building mr-2"></i> {{ $unit->property->property_name }}
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Unit</div>
                    <div class="font-semibold flex items-center" style="color: var(--success);">
                        <i class="fas fa-hashtag mr-2"></i> {{ $unit->unit_number }}
                        @if($unit->unit_name)
                            <span class="ml-2 text-sm">({{ $unit->unit_name }})</span>
                        @endif
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Monthly Rent</div>
                    <div class="font-bold text-lg flex items-center" style="color: var(--warning);">
                        <i class="fas fa-money-bill-wave mr-2"></i> GHS {{ number_format($unit->monthly_rent, 2) }}
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Specifications</div>
                    <div class="font-semibold flex items-center" style="color: var(--info);">
                        <i class="fas fa-bed mr-2"></i> {{ $unit->bedrooms }}B{{ $unit->bathrooms }}B
                        @if($unit->floor_area)
                            <span class="ml-2"><i class="fas fa-ruler-combined"></i> {{ $unit->floor_area }}m²</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Form -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-edit mr-2"></i> Tenant Assignment Form
                    </h3>

                    <form action="{{ route('property-units.assign-tenant', $unit->id) }}" 
                          method="POST" enctype="multipart/form-data" id="tenantAssignmentForm">
                        @csrf

                        <!-- Assignment Type Selection -->
                        <div class="mb-8">
                            <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-user-tag mr-2"></i> Select Assignment Type
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="assignment-type-card" data-type="existing" onclick="selectAssignmentType('existing')">
                                    <input type="radio" id="type_existing" name="assignment_type" value="existing" 
                                           class="hidden" {{ old('assignment_type') !== 'new' ? 'checked' : '' }}>
                                    <div class="card-body text-center">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-user-friends text-xl"></i>
                                        </div>
                                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">Existing Tenant</h5>
                                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                                            Assign a tenant already registered in the system
                                        </p>
                                        <div class="text-xs px-2 py-1 rounded-full inline-flex items-center" 
                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-users mr-1"></i> {{ $availableTenants->count() }} available
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="assignment-type-card" data-type="new" onclick="selectAssignmentType('new')">
                                    <input type="radio" id="type_new" name="assignment_type" value="new" 
                                           class="hidden" {{ old('assignment_type') === 'new' ? 'checked' : '' }}>
                                    <div class="card-body text-center">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-user-plus text-xl"></i>
                                        </div>
                                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">New Tenant</h5>
                                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                                            Add a new tenant who is not yet registered
                                        </p>
                                        <div class="text-xs px-2 py-1 rounded-full inline-flex items-center" 
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-envelope mr-1"></i> Invitation Required
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EXISTING TENANT SECTION -->
                        <div id="existingTenantSection" class="space-y-6" style="display: {{ old('assignment_type') === 'new' ? 'none' : 'block' }};">
                            <!-- Tenant Selection -->
                            <div>
                                <label for="tenant_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-user mr-1"></i> Select Existing Tenant <span class="text-red-500">*</span>
                                </label>
                                
                                @if($availableTenants->isNotEmpty())
                                <div class="relative">
                                    <select name="tenant_id" 
                                            id="tenant_id" 
                                            class="index-custom-dropdown w-full"
                                            onchange="showTenantInfo(this)">
                                        <option value="">Choose a tenant...</option>
                                        
                                        <!-- Available tenants (not assigned anywhere) -->
                                        @if($availableTenants->count() > 0)
                                        <optgroup label="Available Tenants">
                                            @foreach($availableTenants as $tenant)
                                                <option value="{{ $tenant->id }}"
                                                        data-email="{{ $tenant->email }}"
                                                        data-phone="{{ $tenant->phone ?? 'N/A' }}"
                                                        data-status="{{ $tenant->status }}"
                                                        data-income="{{ $tenant->monthly_income ?? 0 }}"
                                                        data-gender="{{ $tenant->gender ?? 'other' }}"
                                                        {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                                    {{ $tenant->name }} 
                                                    @if($tenant->phone)
                                                        ({{ $tenant->phone }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                        @endif
                                        
                                        <!-- All tenants from landlord's properties -->
                                        @if($propertyTenants->count() > 0)
                                        <optgroup label="All Tenants (From Your Properties)">
                                            @foreach($propertyTenants as $tenant)
                                                {{-- Skip tenants already in available category --}}
                                                @if(!$availableTenants->contains('id', $tenant->id))
                                                    <option value="{{ $tenant->id }}" 
                                                            data-email="{{ $tenant->email }}"
                                                            data-phone="{{ $tenant->phone ?? 'N/A' }}"
                                                            data-status="{{ $tenant->status }}"
                                                            data-income="{{ $tenant->monthly_income ?? 0 }}"
                                                            data-gender="{{ $tenant->gender ?? 'other' }}"
                                                            {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                                        {{ $tenant->name }} 
                                                        @if($tenant->phone)
                                                            ({{ $tenant->phone }})
                                                        @endif
                                                        <small class="text-xs" style="color: var(--text-secondary);">
                                                            (Registered in your properties)
                                                        </small>
                                                    </option>
                                                @endif
                                            @endforeach
                                        </optgroup>
                                        @endif
                                        
                                        <!-- Currently assigned tenants (already in other units) -->
                                        @if($currentlyAssigned->count() > 0)
                                        <optgroup label="Currently Assigned to Other Units">
                                            @foreach($currentlyAssigned as $tenant)
                                                <option value="{{ $tenant->id }}" 
                                                        disabled
                                                        data-email="{{ $tenant->email }}"
                                                        data-phone="{{ $tenant->phone ?? 'N/A' }}"
                                                        data-status="{{ $tenant->status }}"
                                                        data-income="{{ $tenant->monthly_income ?? 0 }}"
                                                        data-gender="{{ $tenant->gender ?? 'other' }}">
                                                    {{ $tenant->name }} (Already assigned to another unit)
                                                </option>
                                            @endforeach
                                        </optgroup>
                                        @endif
                                    </select>
                                </div>
                                
                                <!-- Selected Tenant Information -->
                                <div id="tenantInfo" class="mt-4 hidden p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--info);">
                                        <i class="fas fa-user-circle mr-2"></i> Tenant Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Email:</span>
                                            <span id="tenantEmail" style="color: var(--text-primary);" class="ml-2"></span>
                                        </div>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Phone:</span>
                                            <span id="tenantPhone" style="color: var(--text-primary);" class="ml-2"></span>
                                        </div>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Status:</span>
                                            <span id="tenantStatus" style="color: var(--text-primary);" class="ml-2"></span>
                                        </div>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Gender:</span>
                                            <span id="tenantGender" style="color: var(--text-primary);" class="ml-2"></span>
                                        </div>
                                        <div>
                                            <span class="font-medium" style="color: var(--text-secondary);">Monthly Income:</span>
                                            <span id="tenantIncome" style="color: var(--text-primary);" class="ml-2"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                @error('tenant_id')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                                @else
                                <div class="p-4 rounded-lg text-center" 
                                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    <i class="fas fa-users-slash text-2xl mb-2" style="color: var(--warning);"></i>
                                    <p class="font-medium mb-2" style="color: var(--warning);">No Tenants Found</p>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        You don't have any available tenants in the system.
                                    </p>
                                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                        Consider adding a new tenant instead.
                                    </p>
                                </div>
                                @endif
                            </div>

                            <!-- Common Fields for Existing Tenant -->
                            <div class="space-y-6" id="existingTenantCommonFields">
                                <!-- Move-in Date -->
                                <div>
                                    <label for="move_in_date_existing" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-calendar-day mr-1"></i> Move-in Date <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="date" 
                                               name="move_in_date" 
                                               id="move_in_date_existing" 
                                               required
                                               value="{{ old('move_in_date', date('Y-m-d')) }}"
                                               class="index-custom-input w-full"
                                               min="{{ date('Y-m-d') }}">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <i class="fas fa-calendar-alt" style="color: var(--text-secondary);"></i>
                                        </div>
                                    </div>
                                    @error('move_in_date')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i> Date when tenant will start occupying
                                    </p>
                                </div>

                                <!-- Proposed Rent -->
                                <div>
                                    <label for="proposed_rent_existing" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-money-bill-wave mr-1"></i> Proposed Rent
                                    </label>
                                    <div class="relative">
                                        <div class="flex items-center">
                                            <span class="px-3 py-2 rounded-l-lg" style="background-color: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-secondary);">GHS</span>
                                            <input type="number" 
                                                   name="proposed_rent" 
                                                   id="proposed_rent_existing" 
                                                   step="0.01"
                                                   min="0"
                                                   value="{{ old('proposed_rent', $unit->monthly_rent) }}"
                                                   class="index-custom-input w-full rounded-l-none">
                                        </div>
                                    </div>
                                    @error('proposed_rent')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Optional. If not specified, unit's monthly rent will be used.
                                    </p>
                                </div>

                                <!-- Documents (Optional) -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-file-upload mr-1"></i> Additional Documents (Optional)
                                    </label>
                                    <div class="border-2 border-dashed rounded-lg p-4 transition-colors"
                                         style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.02);"
                                         id="documentDropzoneExisting">
                                        <div class="text-center">
                                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-cloud-upload-alt text-xl"></i>
                                            </div>
                                            <p class="text-sm mb-3" style="color: var(--text-secondary);">
                                                Upload additional documents (if any)
                                            </p>
                                            <input type="file" 
                                                   name="documents[]" 
                                                   multiple
                                                   accept=".pdf,.jpg,.jpeg,.png"
                                                   class="hidden" 
                                                   id="documentUploadExisting">
                                            <label for="documentUploadExisting" 
                                                   class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium cursor-pointer"
                                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                                <i class="fas fa-upload mr-2"></i> Choose Files
                                            </label>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-xs mb-2 flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Allowed: PDF, JPG, JPEG, PNG (Max: {{ round($maxFileSize / 1024, 1) }}MB each)
                                            </p>
                                            <div id="fileListExisting" class="mt-2 space-y-2">
                                                <!-- Files will be listed here -->
                                            </div>
                                        </div>
                                    </div>
                                    @error('documents.*')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label for="notes_existing" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-sticky-note mr-1"></i> Notes (Optional)
                                    </label>
                                    <textarea name="notes" 
                                              id="notes_existing" 
                                              rows="3"
                                              class="index-custom-textarea w-full"
                                              placeholder="Any additional notes or special instructions...">{{ old('notes') }}</textarea>
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        These notes will be visible to administrators during approval
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- NEW TENANT SECTION -->
                        <div id="newTenantSection" class="space-y-6" style="display: {{ old('assignment_type') === 'new' ? 'block' : 'none' }};">
                            <!-- New Tenant Details -->
                            <div class="p-4 rounded-lg mb-6" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <div class="flex items-center mb-3">
                                    <div class="flex-shrink-0 mr-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium" style="color: var(--text-primary);">New Tenant Application</h4>
                                        <p class="text-sm" style="color: var(--text-secondary);">
                                            Fill in tenant details. Fields marked with <span class="text-red-500">*</span> are required.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="tenant_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="tenant_name" 
                                           id="tenant_name" 
                                           required
                                           value="{{ old('tenant_name') }}"
                                           class="index-custom-input"
                                           placeholder="Enter tenant's full name">
                                    @error('tenant_name')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="tenant_email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Email Address <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" 
                                           name="tenant_email" 
                                           id="tenant_email" 
                                           required
                                           value="{{ old('tenant_email') }}"
                                           class="index-custom-input"
                                           placeholder="tenant@example.com">
                                    @error('tenant_email')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="tenant_phone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Phone Number <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="tel" 
                                               name="tenant_phone" 
                                               id="tenant_phone" 
                                               required
                                               value="{{ old('tenant_phone') }}"
                                               class="index-custom-input pr-10"
                                               placeholder="+233 XX XXX XXXX or 0XX XXX XXXX"
                                               onblur="validatePhoneNumber(this)">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
                                        </div>
                                    </div>
                                    <div id="phoneValidation" class="mt-1 text-xs hidden">
                                        <i class="fas mr-1"></i> <span></span>
                                    </div>
                                    @error('tenant_phone')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Enter with or without country code (+233 or 0)
                                    </p>
                                </div>

                                <div>
                                    <label for="tenant_gender" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Gender <span class="text-red-500">*</span>
                                    </label>
                                    <select name="tenant_gender" id="tenant_gender" required class="index-custom-dropdown dark-dropdown">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('tenant_gender') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('tenant_gender') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('tenant_gender') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('tenant_gender')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="tenant_national_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        National ID / Passport
                                    </label>
                                    <input type="text" 
                                           name="tenant_national_id" 
                                           id="tenant_national_id" 
                                           value="{{ old('tenant_national_id') }}"
                                           class="index-custom-input"
                                           placeholder="Optional">
                                    @error('tenant_national_id')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="move_in_date_new" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Move-in Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" 
                                           name="move_in_date" 
                                           id="move_in_date_new" 
                                           required
                                           value="{{ old('move_in_date', date('Y-m-d')) }}"
                                           class="index-custom-input"
                                           min="{{ date('Y-m-d') }}">
                                    @error('move_in_date')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-briefcase mr-2"></i> Employment Information
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="employment_status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Employment Status <span class="text-red-500">*</span>
                                        </label>
                                        <select name="employment_status" id="employment_status" required class="index-custom-dropdown dark-dropdown" onchange="toggleEmploymentFields()">
                                            <option value="">Select Status</option>
                                            <option value="employed" {{ old('employment_status') == 'employed' ? 'selected' : '' }}>Employed</option>
                                            <option value="self_employed" {{ old('employment_status') == 'self_employed' ? 'selected' : '' }}>Self-Employed</option>
                                            <option value="student" {{ old('employment_status') == 'student' ? 'selected' : '' }}>Student</option>
                                            <option value="unemployed" {{ old('employment_status') == 'unemployed' ? 'selected' : '' }}>Unemployed</option>
                                            <option value="retired" {{ old('employment_status') == 'retired' ? 'selected' : '' }}>Retired</option>
                                        </select>
                                        @error('employment_status')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="monthly_income" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Monthly Income (GHS)
                                        </label>
                                        <input type="number" 
                                               name="monthly_income" 
                                               id="monthly_income" 
                                               min="0"
                                               step="0.01"
                                               value="{{ old('monthly_income') }}"
                                               class="index-custom-input"
                                               placeholder="Optional">
                                        @error('monthly_income')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Optional. Helps assess affordability.
                                        </p>
                                    </div>

                                    <div id="company_name_field" style="display: {{ old('employment_status') == 'employed' ? 'block' : 'none' }};">
                                        <label for="company_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Company Name
                                        </label>
                                        <input type="text" 
                                               name="company_name" 
                                               id="company_name" 
                                               value="{{ old('company_name') }}"
                                               class="index-custom-input"
                                               placeholder="Optional">
                                        @error('company_name')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div id="job_title_field" style="display: {{ old('employment_status') == 'employed' ? 'block' : 'none' }};">
                                        <label for="job_title" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Job Title
                                        </label>
                                        <input type="text" 
                                               name="job_title" 
                                               id="job_title" 
                                               value="{{ old('job_title') }}"
                                               class="index-custom-input"
                                               placeholder="Optional">
                                        @error('job_title')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-phone-alt mr-2"></i> Emergency Contact
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="emergency_contact_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Contact Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="emergency_contact_name" 
                                               id="emergency_contact_name" 
                                               required
                                               value="{{ old('emergency_contact_name') }}"
                                               class="index-custom-input"
                                               placeholder="Full name">
                                        @error('emergency_contact_name')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="emergency_contact_phone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Contact Phone <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="tel" 
                                                   name="emergency_contact_phone" 
                                                   id="emergency_contact_phone" 
                                                   required
                                                   value="{{ old('emergency_contact_phone') }}"
                                                   class="index-custom-input pr-10"
                                                   placeholder="+233 XX XXX XXXX or 0XX XXX XXXX"
                                                   onblur="validateEmergencyPhoneNumber(this)">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
                                            </div>
                                        </div>
                                        <div id="emergencyPhoneValidation" class="mt-1 text-xs hidden">
                                            <i class="fas mr-1"></i> <span></span>
                                        </div>
                                        @error('emergency_contact_phone')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Enter with or without country code (+233 or 0)
                                        </p>
                                    </div>

                                    <div>
                                        <label for="emergency_contact_relationship" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Relationship <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="emergency_contact_relationship" 
                                               id="emergency_contact_relationship" 
                                               required
                                               value="{{ old('emergency_contact_relationship') }}"
                                               class="index-custom-input"
                                               placeholder="e.g., Spouse, Parent, Sibling, Friend">
                                        @error('emergency_contact_relationship')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Background Information -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-history mr-2"></i> Background Information
                                </h4>
                                
                                <div>
                                    <label for="tenant_background" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Tenant Background
                                    </label>
                                    <textarea name="tenant_background" 
                                              id="tenant_background" 
                                              rows="4"
                                              class="index-custom-textarea"
                                              placeholder="Optional background information about the tenant">{{ old('tenant_background') }}</textarea>
                                    <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Optional. Include employment history, reason for moving, rental history, etc.
                                    </p>
                                    @error('tenant_background')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mt-4">
                                    <label for="previous_landlord_reference" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Previous Landlord Reference (Optional)
                                    </label>
                                    <textarea name="previous_landlord_reference" 
                                              id="previous_landlord_reference" 
                                              rows="3"
                                              class="index-custom-textarea"
                                              placeholder="Previous landlord contact and reference if available">{{ old('previous_landlord_reference') }}</textarea>
                                    @error('previous_landlord_reference')
                                    <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Proposed Rent for New Tenant -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-money-check-alt mr-2"></i> Rental Information
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="proposed_rent_new" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Proposed Rent
                                        </label>
                                        <div class="flex items-center">
                                            <span class="px-3 py-2 rounded-l-lg" style="background-color: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-secondary);">GHS</span>
                                            <input type="number" 
                                                   name="proposed_rent" 
                                                   id="proposed_rent_new" 
                                                   step="0.01"
                                                   min="0"
                                                   value="{{ old('proposed_rent', $unit->monthly_rent) }}"
                                                   class="index-custom-input w-full rounded-l-none">
                                        </div>
                                        @error('proposed_rent')
                                        <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Optional. If not specified, unit's monthly rent will be used.
                                        </p>
                                    </div>
                                    
                                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                        <p class="text-xs mb-1" style="color: var(--text-secondary);">Income Requirement</p>
                                        <div class="flex items-center">
                                            <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <p class="text-sm font-medium" style="color: var(--info);">Minimum: GHS <span id="minimumIncome">0.00</span></p>
                                                <p class="text-xs" style="color: var(--text-secondary);">(2.5× proposed rent if income provided)</p>
                                            </div>
                                        </div>
                                        <div id="incomeValidation" class="mt-2 text-xs hidden">
                                            <i class="fas mr-1"></i> <span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents for New Tenant - UPDATED to make fields optional -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-file-contract mr-2"></i> Supporting Documents
                                </h4>
                                
                                <div class="mb-4 p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <p class="text-sm font-medium mb-2 flex items-center" style="color: var(--info);">
                                        <i class="fas fa-info-circle mr-2"></i> Document Upload (Optional)
                                    </p>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        You can upload supporting documents to help with the application review:
                                    </p>
                                    <ul class="mt-2 text-sm space-y-1" style="color: var(--text-secondary);">
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                            <span>Government-issued ID (optional)</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                            <span>Proof of income (optional)</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                            <span>Employment letter (optional)</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="border-2 border-dashed rounded-lg p-6 transition-colors"
                                     style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.02);"
                                     id="documentDropzoneNew">
                                    <div class="text-center">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-file-upload text-2xl"></i>
                                        </div>
                                        <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">
                                            Upload Tenant Documents (Optional)
                                        </p>
                                        <p class="text-sm mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                                            Drag & drop files here or click to browse. All documents are optional.
                                        </p>
                                        
                                        <input type="file" 
                                               name="documents[]" 
                                               multiple
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               class="hidden" 
                                               id="documentUploadNew">
                                        <label for="documentUploadNew" 
                                               class="inline-flex items-center px-6 py-3 rounded-lg font-medium cursor-pointer btn-primary">
                                            <i class="fas fa-cloud-upload-alt mr-3"></i> Browse Files
                                        </label>
                                        
                                        <p class="text-xs mt-4" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Allowed: PDF, JPG, JPEG, PNG (Max: {{ round($maxFileSize / 1024, 1) }}MB each)
                                        </p>
                                    </div>
                                    
                                    <div class="mt-8">
                                        <!-- Document categories - all optional now -->
                                        @foreach(['id_card', 'passport', 'employment_letter', 'bank_statement', 'reference_letter', 'other'] as $docType)
                                        <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--bg-input), 0.5); border: 1px solid var(--border-color);">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-file"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium" style="color: var(--text-primary);">
                                                            {{ $documentLabels[$docType] ?? ucfirst(str_replace('_', ' ', $docType)) }}
                                                        </p>
                                                        <p class="text-xs" style="color: var(--text-secondary);">
                                                            Optional
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    
                                    <div id="fileListNew" class="mt-6 space-y-3">
                                        <!-- Files will be listed here -->
                                    </div>
                                </div>
                                @error('documents')
                                <p class="mt-2 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                                @error('documents.*')
                                <p class="mt-2 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Invitation Channels -->
                            <div class="pt-6 border-t" style="border-color: var(--border-color);">
                                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-paper-plane mr-2"></i> Invitation Channels <span class="text-red-500">*</span>
                                </h4>
                                
                                <div class="mb-4 p-4 rounded-lg" 
                                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mr-3">
                                            <i class="fas fa-info-circle text-lg mt-1" style="color: var(--info);"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium mb-2" style="color: var(--info);">Phone Number Format for SMS</p>
                                            <p class="text-sm" style="color: var(--text-secondary);">
                                                SMS can be sent to both formats:
                                            </p>
                                            <ul class="mt-2 text-sm space-y-1" style="color: var(--text-secondary);">
                                                <li class="flex items-start">
                                                    <i class="fas fa-check text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                                    <span>With country code: <code>+233XXXXXXXXX</code></span>
                                                </li>
                                                <li class="flex items-start">
                                                    <i class="fas fa-check text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                                    <span>Without country code: <code>0XXXXXXXXX</code></span>
                                                </li>
                                            </ul>
                                            <p class="text-xs mt-2 italic" style="color: var(--text-secondary);">
                                                System will automatically convert to international format for SMS delivery.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="invitation-channel-card" onclick="toggleChannel('email')">
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="invitation_channels[]" 
                                                   id="channel_email" 
                                                   value="email"
                                                   class="index-custom-checkbox mr-3"
                                                   {{ in_array('email', old('invitation_channels', [])) ? 'checked' : '' }}>
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                     style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium" style="color: var(--text-primary);">Email</p>
                                                    <p class="text-xs" style="color: var(--text-secondary);">Send invitation via email</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="invitation-channel-card" onclick="toggleChannel('sms')">
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="invitation_channels[]" 
                                                   id="channel_sms" 
                                                   value="sms"
                                                   class="index-custom-checkbox mr-3"
                                                   {{ in_array('sms', old('invitation_channels', [])) ? 'checked' : '' }}
                                                   onchange="validateSMSChannel(this)">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    <i class="fas fa-sms"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium" style="color: var(--text-primary);">SMS</p>
                                                    <p class="text-xs" style="color: var(--text-secondary);">Send invitation via SMS</p>
                                                    <div id="smsValidation" class="text-xs mt-1 hidden">
                                                        <i class="fas mr-1"></i> <span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="invitation-channel-card" onclick="toggleChannel('whatsapp')">
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="invitation_channels[]" 
                                                   id="channel_whatsapp" 
                                                   value="whatsapp"
                                                   class="index-custom-checkbox mr-3"
                                                   {{ in_array('whatsapp', old('invitation_channels', [])) ? 'checked' : '' }}
                                                   onchange="validateWhatsAppChannel(this)">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fab fa-whatsapp"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium" style="color: var(--text-primary);">WhatsApp</p>
                                                    <p class="text-xs" style="color: var(--text-secondary);">Send invitation via WhatsApp</p>
                                                    <div id="whatsappValidation" class="text-xs mt-1 hidden">
                                                        <i class="fas mr-1"></i> <span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-3 rounded-lg hidden" id="smsWarning" 
                                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    <p class="text-sm flex items-center" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span>Please ensure the phone number is valid for SMS delivery.</span>
                                    </p>
                                </div>
                                
                                @error('invitation_channels')
                                <p class="mt-2 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notes for New Tenant -->
                            <div>
                                <label for="notes_new" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-sticky-note mr-1"></i> Notes for Administrator (Optional)
                                </label>
                                <textarea name="notes" 
                                          id="notes_new" 
                                          rows="3"
                                          class="index-custom-textarea w-full"
                                          placeholder="Any additional notes for the administrator reviewing this application...">{{ old('notes') }}</textarea>
                                <p class="mt-1 text-xs flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    These notes will help administrators during the review process
                                </p>
                                @error('notes')
                                <p class="mt-1 text-xs" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col md:flex-row justify-between items-center pt-8 mt-8 border-t" 
                             style="border-color: var(--border-color);">
                            <div class="text-sm mb-4 md:mb-0 flex items-center" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-2"></i>
                                <span>All submissions require administrative review</span>
                            </div>
                            
                            <div class="flex space-x-3">
                                <a href="{{ route('property-units.show', $unit->id) }}" 
                                   class="inline-flex items-center px-5 py-2.5 rounded-lg font-medium btn-secondary">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </a>
                                
                                <button type="submit" 
                                        class="inline-flex items-center px-6 py-2.5 rounded-lg font-medium text-white btn-primary"
                                        id="submitBtn">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit for Approval
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Information & Statistics -->
        <div class="space-y-6">
            <!-- Tenant Statistics -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2"></i> Tenant Statistics
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--success);">Available Tenants</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Not assigned to any unit</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold" style="color: var(--success);">{{ $availableTenants->count() }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--primary);">Your Tenants</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">From your properties</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold" style="color: var(--primary);">{{ $propertyTenants->count() }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--warning);">Assigned Elsewhere</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">In other units</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold" style="color: var(--warning);">{{ $currentlyAssigned->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Available Tenants -->
            @if($availableTenants->isNotEmpty())
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center justify-between" style="color: var(--text-primary);">
                        <span class="flex items-center">
                            <i class="fas fa-user-clock mr-2"></i> Recent Available Tenants
                        </span>
                        <span class="text-sm px-2 py-1 rounded-full" 
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            {{ $availableTenants->count() }}
                        </span>
                    </h3>
                    
                    <div class="space-y-3">
                        @foreach($availableTenants->take(3) as $tenant)
                        <div class="flex items-center p-3 rounded-lg hover:shadow-sm transition-all"
                             style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    @if($tenant->gender === 'male')
                                        <i class="fas fa-male"></i>
                                    @elseif($tenant->gender === 'female')
                                        <i class="fas fa-female"></i>
                                    @else
                                        <i class="fas fa-user"></i>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate" style="color: var(--text-primary);">{{ $tenant->name }}</p>
                                <div class="flex items-center text-xs space-x-3 mt-1" style="color: var(--text-secondary);">
                                    <span class="flex items-center">
                                        <i class="fas fa-envelope mr-1"></i> {{ substr($tenant->email, 0, 15) }}...
                                    </span>
                                    @if($tenant->phone)
                                    <span class="flex items-center">
                                        <i class="fas fa-phone mr-1"></i> {{ $tenant->phone }}
                                    </span>
                                    @endif
                                </div>
                                @if($tenant->monthly_income)
                                <div class="mt-1 text-xs flex items-center" style="color: var(--success);">
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    Income: GHS {{ number_format($tenant->monthly_income, 2) }}/month
                                </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full font-medium" 
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    Available
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    @if($availableTenants->count() > 3)
                    <div class="mt-4 text-center">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Showing 3 of {{ $availableTenants->count() }} available tenants
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Unit Rental Information -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-2"></i> Unit Rental Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <p class="text-sm mb-1" style="color: var(--text-secondary);">Base Monthly Rent</p>
                            <p class="text-xl font-bold" style="color: var(--warning);">GHS {{ number_format($unit->monthly_rent, 2) }}</p>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <p class="text-sm mb-1" style="color: var(--text-secondary);">Suggested Rent Range</p>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--primary);">Minimum</p>
                                    <p class="text-lg" style="color: var(--text-primary);">GHS {{ number_format($unit->monthly_rent * 0.9, 2) }}</p>
                                </div>
                                <div class="text-center px-4">
                                    <i class="fas fa-arrows-alt-h text-xl" style="color: var(--text-secondary);"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--primary);">Maximum</p>
                                    <p class="text-lg" style="color: var(--text-primary);">GHS {{ number_format($unit->monthly_rent * 1.2, 2) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <p class="text-sm mb-2 flex items-center" style="color: var(--info);">
                                <i class="fas fa-info-circle mr-2"></i> Income Guideline
                            </p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                For reference only: Tenant's monthly income should ideally be at least <strong>2.5x</strong> the proposed rent.
                            </p>
                            <p class="text-xs mt-1 italic" style="color: var(--text-secondary);">
                                For GHS {{ number_format($unit->monthly_rent, 2) }}/month, suggested minimum income would be GHS {{ number_format($unit->monthly_rent * 2.5, 2) }}/month
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Process Timeline -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-list-ol mr-2"></i> Assignment Process
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: var(--primary); color: white; font-weight: bold;">
                                    1
                                </div>
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Submit Application</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Landlord submits tenant assignment request
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); font-weight: bold;">
                                    2
                                </div>
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Admin Review</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Administrator reviews documents and information
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); font-weight: bold;">
                                    3
                                </div>
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">
                                    @if($isLandlord)
                                        Admin Approval
                                    @else
                                        Approval/Rejection
                                    @endif
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Administrator approves or rejects the assignment
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); font-weight: bold;">
                                    4
                                </div>
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Tenant Notification</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($isLandlord)
                                        New tenants receive invitation to register
                                    @else
                                        Tenant is notified of assignment
                                    @endif
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); font-weight: bold;">
                                    5
                                </div>
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Move-in</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Tenant can move in on the specified date
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize assignment type selection
    initAssignmentTypeSelection();
    
    // Initialize file uploads
    initFileUploads();
    
    // Initialize form validation
    initFormValidation();
    
    // Auto-hide messages
    autoHideMessages();
    
    // Show tenant info if pre-selected
    const tenantSelect = document.getElementById('tenant_id');
    if (tenantSelect && tenantSelect.value) {
        showTenantInfo(tenantSelect);
    }
    
    // Initialize invitation channels - FIX: Set default state
    initializeInvitationChannels();
    
    // Validate phone numbers on page load if they have values
    const tenantPhone = document.getElementById('tenant_phone');
    if (tenantPhone && tenantPhone.value) {
        validatePhoneNumber(tenantPhone);
    }
    
    const emergencyPhone = document.getElementById('emergency_contact_phone');
    if (emergencyPhone && emergencyPhone.value) {
        validateEmergencyPhoneNumber(emergencyPhone);
    }
    
    // Update minimum income display
    validateIncome();
});

function initAssignmentTypeSelection() {
    const existingCard = document.querySelector('.assignment-type-card[data-type="existing"]');
    const newCard = document.querySelector('.assignment-type-card[data-type="new"]');
    const existingSection = document.getElementById('existingTenantSection');
    const newSection = document.getElementById('newTenantSection');
    
    function selectType(type) {
        // Update radio buttons
        document.getElementById(`type_${type}`).checked = true;
        
        // Update card styles
        if (type === 'existing') {
            existingCard.style.borderColor = 'var(--primary)';
            existingCard.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            newCard.style.borderColor = 'var(--border-color)';
            newCard.style.backgroundColor = 'transparent';
            
            // Show/hide sections
            existingSection.style.display = 'block';
            newSection.style.display = 'none';
            
            // Update required fields
            updateRequiredFields('existing');
            
        } else {
            existingCard.style.borderColor = 'var(--border-color)';
            existingCard.style.backgroundColor = 'transparent';
            newCard.style.borderColor = 'var(--primary)';
            newCard.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            
            // Show/hide sections
            existingSection.style.display = 'none';
            newSection.style.display = 'block';
            
            // Update required fields
            updateRequiredFields('new');
        }
    }
    
    // Set initial state based on old input or default
    const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value || 'existing';
    selectType(assignmentType);
    
    // Add click handlers
    existingCard?.addEventListener('click', () => selectType('existing'));
    newCard?.addEventListener('click', () => selectType('new'));
}

function selectAssignmentType(type) {
    // Trigger click on the corresponding card
    const card = document.querySelector(`.assignment-type-card[data-type="${type}"]`);
    if (card) {
        card.click();
    }
}

function updateRequiredFields(type) {
    // Update all required fields based on assignment type
    const existingFields = document.querySelectorAll('#existingTenantSection [required]');
    const newFields = document.querySelectorAll('#newTenantSection [required]');
    
    if (type === 'existing') {
        existingFields.forEach(field => field.required = true);
        newFields.forEach(field => field.required = false);
    } else {
        existingFields.forEach(field => field.required = false);
        newFields.forEach(field => field.required = true);
    }
}

function initFileUploads() {
    // Initialize both file upload zones
    ['Existing', 'New'].forEach(type => {
        const dropzone = document.getElementById(`documentDropzone${type}`);
        const uploadInput = document.getElementById(`documentUpload${type}`);
        const fileList = document.getElementById(`fileList${type}`);
        
        if (dropzone && uploadInput) {
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropzone.style.borderColor = type === 'New' ? 'var(--primary)' : 'var(--border-color)';
                dropzone.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            }
            
            function unhighlight() {
                dropzone.style.borderColor = type === 'New' ? 'var(--border-color)' : 'var(--border-color)';
                dropzone.style.backgroundColor = type === 'New' ? 'rgba(var(--primary-rgb), 0.02)' : 'rgba(var(--primary-rgb), 0.02)';
            }
            
            dropzone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                uploadInput.files = files;
                handleFileSelect(uploadInput, fileList);
            }
            
            // File input change handler
            uploadInput.addEventListener('change', () => handleFileSelect(uploadInput, fileList));
        }
    });
}

function handleFileSelect(input, fileListContainer) {
    const files = input.files;
    
    if (fileListContainer) {
        fileListContainer.innerHTML = '';
        
        if (files.length > 0) {
            // Show file counter
            const fileCounter = document.createElement('div');
            fileCounter.className = 'text-sm mb-3 font-medium';
            fileCounter.style.color = 'var(--text-primary)';
            fileCounter.innerHTML = `<i class="fas fa-folder-open mr-2"></i> ${files.length} file(s) selected`;
            fileListContainer.appendChild(fileCounter);
            
            // List files
            Array.from(files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between p-3 mb-2 rounded-lg';
                fileItem.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
                fileItem.style.border = '1px solid rgba(var(--primary-rgb), 0.1)';
                
                const fileSize = (file.size / 1024).toFixed(2);
                const maxSize = {{ $maxFileSize }};
                const isOverLimit = file.size > (maxSize * 1024);
                
                fileItem.innerHTML = `
                    <div class="flex items-center flex-1 min-w-0">
                        <i class="fas fa-file ${isOverLimit ? 'text-red-500' : 'text-blue-500'} mr-3 text-lg"></i>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate ${isOverLimit ? 'text-red-500' : ''}" style="color: var(--text-primary);">${file.name}</p>
                            <div class="flex items-center text-xs mt-1" style="color: var(--text-secondary);">
                                <span class="mr-3">${fileSize} KB</span>
                                <span>${file.type}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        ${isOverLimit ? 
                            '<span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">Too Large</span>' : 
                            '<span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">OK</span>'
                        }
                        <button type="button" onclick="removeFile(${index}, '${input.id}')" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                fileListContainer.appendChild(fileItem);
            });
            
            // Add remove all button
            const removeAllBtn = document.createElement('button');
            removeAllBtn.type = 'button';
            removeAllBtn.className = 'mt-2 text-sm px-4 py-2 rounded-lg font-medium inline-flex items-center';
            removeAllBtn.style.cssText = 'background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);';
            removeAllBtn.innerHTML = '<i class="fas fa-trash mr-2"></i> Remove All Files';
            removeAllBtn.onclick = function() {
                input.value = '';
                fileListContainer.innerHTML = '';
            };
            fileListContainer.appendChild(removeAllBtn);
        }
    }
}

function removeFile(index, inputId) {
    const input = document.getElementById(inputId);
    const files = Array.from(input.files);
    files.splice(index, 1);
    
    // Create new FileList
    const dataTransfer = new DataTransfer();
    files.forEach(file => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
    
    // Update file list display
    const fileListContainer = document.getElementById(`fileList${inputId.replace('documentUpload', '')}`);
    handleFileSelect(input, fileListContainer);
}

function toggleEmploymentFields() {
    const employmentStatus = document.getElementById('employment_status').value;
    const companyNameField = document.getElementById('company_name_field');
    const jobTitleField = document.getElementById('job_title_field');
    
    if (employmentStatus === 'employed') {
        companyNameField.style.display = 'block';
        jobTitleField.style.display = 'block';
    } else {
        companyNameField.style.display = 'none';
        jobTitleField.style.display = 'none';
    }
}

// FIX: Initialize invitation channels on page load
function initializeInvitationChannels() {
    const channelCards = document.querySelectorAll('.invitation-channel-card');
    
    channelCards.forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const isChecked = checkbox.checked;
        
        // Apply initial styling based on checkbox state
        if (isChecked) {
            card.style.borderColor = 'var(--primary)';
            card.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        } else {
            card.style.borderColor = 'var(--border-color)';
            card.style.backgroundColor = 'transparent';
        }
    });
}

// FIX: Improved toggleChannel function with better event handling
function toggleChannel(channel) {
    const checkbox = document.getElementById(`channel_${channel}`);
    if (!checkbox) return;
    
    // Toggle the checkbox state
    checkbox.checked = !checkbox.checked;
    
    // Find the parent card element
    const card = checkbox.closest('.invitation-channel-card');
    if (!card) return;
    
    // Update the card styling based on the new checkbox state
    if (checkbox.checked) {
        card.style.borderColor = 'var(--primary)';
        card.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        
        // Validate the channel if it's SMS or WhatsApp
        if (channel === 'sms') {
            validateSMSChannel(checkbox);
        } else if (channel === 'whatsapp') {
            validateWhatsAppChannel(checkbox);
        }
    } else {
        card.style.borderColor = 'var(--border-color)';
        card.style.backgroundColor = 'transparent';
        
        // Clear validation messages
        if (channel === 'sms') {
            clearValidationMessage('smsValidation');
        } else if (channel === 'whatsapp') {
            clearValidationMessage('whatsappValidation');
        }
    }
    
    // FIX: Prevent event bubbling to parent elements
    event.stopPropagation();
    
    // Update SMS warning visibility
    updateSMSWarningVisibility();
}

// FIX: Add event listeners to checkboxes for direct clicks
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.invitation-channel-card input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function(event) {
            // Stop event from bubbling to the card's click handler
            event.stopPropagation();
            
            // Find the parent card element
            const card = this.closest('.invitation-channel-card');
            if (!card) return;
            
            // Update the card styling based on the new checkbox state
            if (this.checked) {
                card.style.borderColor = 'var(--primary)';
                card.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
                
                // Validate the channel if it's SMS or WhatsApp
                const channel = this.value;
                if (channel === 'sms') {
                    validateSMSChannel(this);
                } else if (channel === 'whatsapp') {
                    validateWhatsAppChannel(this);
                }
            } else {
                card.style.borderColor = 'var(--border-color)';
                card.style.backgroundColor = 'transparent';
                
                // Clear validation messages
                const channel = this.value;
                if (channel === 'sms') {
                    clearValidationMessage('smsValidation');
                } else if (channel === 'whatsapp') {
                    clearValidationMessage('whatsappValidation');
                }
            }
            
            // Update SMS warning visibility
            updateSMSWarningVisibility();
        });
    });
});

function showTenantInfo(selectElement) {
    const tenantInfo = document.getElementById('tenantInfo');
    const selectedOption = selectElement.selectedOptions[0];
    
    if (selectedOption.value && selectedOption.dataset.email) {
        // Basic tenant info
        document.getElementById('tenantEmail').textContent = selectedOption.dataset.email;
        document.getElementById('tenantPhone').textContent = selectedOption.dataset.phone || 'N/A';
        document.getElementById('tenantStatus').textContent = selectedOption.dataset.status || 'N/A';
        document.getElementById('tenantGender').textContent = selectedOption.dataset.gender || 'N/A';
        
        const income = selectedOption.dataset.income;
        document.getElementById('tenantIncome').textContent = 
            income > 0 ? 'GHS ' + parseFloat(income).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) : 'N/A';
        
        tenantInfo.classList.remove('hidden');
    } else {
        tenantInfo.classList.add('hidden');
    }
}

// ========== PHONE NUMBER VALIDATION FUNCTIONS ==========

/**
 * Validate and format phone number for tenant
 */
function validatePhoneNumber(input) {
    const phoneNumber = input.value.trim();
    const validationDiv = document.getElementById('phoneValidation');
    
    if (!phoneNumber) {
        clearValidation(validationDiv);
        return false;
    }
    
    // Clean the phone number
    const cleanedPhone = cleanPhoneNumber(phoneNumber);
    const isValid = isValidPhoneNumber(cleanedPhone);
    const formattedPhone = formatPhoneNumber(cleanedPhone);
    
    // Update validation message
    if (isValid) {
        showValidationSuccess(validationDiv, `✓ Valid phone number (${formattedPhone})`);
        input.classList.remove('border-red-500');
        input.classList.add('border-green-500');
    } else {
        showValidationError(validationDiv, '✗ Invalid phone number format. Use +233XXXXXXXXX or 0XXXXXXXXX');
        input.classList.remove('border-green-500');
        input.classList.add('border-red-500');
    }
    
    return isValid;
}

/**
 * Validate and format phone number for emergency contact
 */
function validateEmergencyPhoneNumber(input) {
    const phoneNumber = input.value.trim();
    const validationDiv = document.getElementById('emergencyPhoneValidation');
    
    if (!phoneNumber) {
        clearValidation(validationDiv);
        return false;
    }
    
    // Clean the phone number
    const cleanedPhone = cleanPhoneNumber(phoneNumber);
    const isValid = isValidPhoneNumber(cleanedPhone);
    const formattedPhone = formatPhoneNumber(cleanedPhone);
    
    // Update validation message
    if (isValid) {
        showValidationSuccess(validationDiv, `✓ Valid phone number (${formattedPhone})`);
        input.classList.remove('border-red-500');
        input.classList.add('border-green-500');
    } else {
        showValidationError(validationDiv, '✗ Invalid phone number format. Use +233XXXXXXXXX or 0XXXXXXXXX');
        input.classList.remove('border-green-500');
        input.classList.add('border-red-500');
    }
    
    return isValid;
}

/**
 * Clean phone number by removing non-numeric characters
 */
function cleanPhoneNumber(phone) {
    // Remove all non-numeric characters except leading +
    let cleaned = phone.replace(/[^0-9+]/g, '');
    
    // If starts with 00, replace with +
    if (cleaned.startsWith('00')) {
        cleaned = '+' + cleaned.substring(2);
    }
    
    // If starts with 0, replace with +233 (Ghana country code)
    if (cleaned.startsWith('0')) {
        cleaned = '+233' + cleaned.substring(1);
    }
    
    // If starts with 233 but no +, add +
    if (cleaned.startsWith('233') && !cleaned.startsWith('+233')) {
        cleaned = '+' + cleaned;
    }
    
    return cleaned;
}

/**
 * Check if phone number is valid for Ghana
 */
function isValidPhoneNumber(phone) {
    // Accept both formats:
    // 1. International: +233XXXXXXXXX (12 digits total)
    // 2. Local: 0XXXXXXXXX (10 digits total)
    
    // Remove all non-numeric characters for validation
    const numericOnly = phone.replace(/\D/g, '');
    
    // Check if it's a valid Ghana number
    if (phone.startsWith('+233')) {
        // International format: +233 followed by 9 digits = 13 characters total
        return phone.length === 13 && /^\+233[0-9]{9}$/.test(phone);
    } else if (phone.startsWith('0')) {
        // Local format: 0 followed by 9 digits = 10 characters total
        return phone.length === 10 && /^0[0-9]{9}$/.test(phone);
    } else if (numericOnly.startsWith('233') && numericOnly.length === 12) {
        // 233XXXXXXXXX format (12 digits)
        return true;
    }
    
    return false;
}

/**
 * Format phone number for display
 */
function formatPhoneNumber(phone) {
    if (phone.startsWith('+233')) {
        // Format: +233 XX XXX XXXX
        const digits = phone.substring(4);
        return `+233 ${digits.substring(0, 2)} ${digits.substring(2, 5)} ${digits.substring(5)}`;
    } else if (phone.startsWith('0')) {
        // Format: 0XX XXX XXXX
        const digits = phone.substring(1);
        return `0${digits.substring(0, 2)} ${digits.substring(2, 5)} ${digits.substring(5)}`;
    }
    return phone;
}

/**
 * Show validation success message
 */
function showValidationSuccess(element, message) {
    element.innerHTML = `<i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> <span style="color: var(--success);">${message}</span>`;
    element.classList.remove('hidden');
}

/**
 * Show validation error message
 */
function showValidationError(element, message) {
    element.innerHTML = `<i class="fas fa-exclamation-circle mr-1" style="color: var(--danger);"></i> <span style="color: var(--danger);">${message}</span>`;
    element.classList.remove('hidden');
}

/**
 * Clear validation message
 */
function clearValidation(element) {
    element.innerHTML = '';
    element.classList.add('hidden');
}

/**
 * Clear validation message by ID
 */
function clearValidationMessage(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '';
        element.classList.add('hidden');
    }
}

// ========== INCOME VALIDATION FUNCTION ==========

/**
 * Validate income meets minimum requirement (informational only)
 */
function validateIncome() {
    const proposedRentInput = document.getElementById('proposed_rent_new');
    const monthlyIncomeInput = document.getElementById('monthly_income');
    const minimumIncomeSpan = document.getElementById('minimumIncome');
    const validationDiv = document.getElementById('incomeValidation');
    
    if (!proposedRentInput || !monthlyIncomeInput || !minimumIncomeSpan) return;
    
    const proposedRent = parseFloat(proposedRentInput.value) || 0;
    const monthlyIncome = parseFloat(monthlyIncomeInput.value) || 0;
    const minimumIncome = proposedRent * 2.5;
    
    // Update minimum income display
    minimumIncomeSpan.textContent = minimumIncome.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    if (monthlyIncome > 0 && proposedRent > 0) {
        if (monthlyIncome >= minimumIncome) {
            showValidationSuccess(validationDiv, `✓ Income meets guideline (${(monthlyIncome/proposedRent).toFixed(1)}× rent)`);
            monthlyIncomeInput.classList.remove('border-red-500');
            monthlyIncomeInput.classList.add('border-green-500');
        } else {
            showValidationError(validationDiv, `✗ Income below guideline. Guideline suggests GHS ${minimumIncome.toFixed(2)} (${(monthlyIncome/proposedRent).toFixed(1)}× rent)`);
            monthlyIncomeInput.classList.remove('border-green-500');
            monthlyIncomeInput.classList.add('border-yellow-500');
        }
    } else {
        clearValidation(validationDiv);
        monthlyIncomeInput.classList.remove('border-red-500', 'border-green-500', 'border-yellow-500');
    }
}

// ========== SMS & WHATSAPP CHANNEL VALIDATION ==========

/**
 * Validate SMS channel selection
 */
function validateSMSChannel(checkbox) {
    const validationDiv = document.getElementById('smsValidation');
    const tenantPhone = document.getElementById('tenant_phone');
    
    if (!checkbox.checked) {
        clearValidationMessage('smsValidation');
        return;
    }
    
    if (!tenantPhone || !tenantPhone.value.trim()) {
        showValidationError(validationDiv, '✗ Phone number required for SMS');
        return;
    }
    
    // Validate phone number
    const phoneNumber = tenantPhone.value.trim();
    const cleanedPhone = cleanPhoneNumber(phoneNumber);
    const isValid = isValidPhoneNumber(cleanedPhone);
    
    if (isValid) {
        const formattedPhone = formatPhoneNumber(cleanedPhone);
        showValidationSuccess(validationDiv, `✓ SMS will be sent to ${formattedPhone}`);
    } else {
        showValidationError(validationDiv, '✗ Invalid phone number for SMS');
    }
}

/**
 * Validate WhatsApp channel selection
 */
function validateWhatsAppChannel(checkbox) {
    const validationDiv = document.getElementById('whatsappValidation');
    const tenantPhone = document.getElementById('tenant_phone');
    
    if (!checkbox.checked) {
        clearValidationMessage('whatsappValidation');
        return;
    }
    
    if (!tenantPhone || !tenantPhone.value.trim()) {
        showValidationError(validationDiv, '✗ Phone number required for WhatsApp');
        return;
    }
    
    // Validate phone number
    const phoneNumber = tenantPhone.value.trim();
    const cleanedPhone = cleanPhoneNumber(phoneNumber);
    const isValid = isValidPhoneNumber(cleanedPhone);
    
    if (isValid) {
        const formattedPhone = formatPhoneNumber(cleanedPhone);
        showValidationSuccess(validationDiv, `✓ WhatsApp will be sent to ${formattedPhone}`);
    } else {
        showValidationError(validationDiv, '✗ Invalid phone number for WhatsApp');
    }
}

/**
 * Update SMS warning visibility
 */
function updateSMSWarningVisibility() {
    const smsCheckbox = document.getElementById('channel_sms');
    const whatsappCheckbox = document.getElementById('channel_whatsapp');
    const smsWarning = document.getElementById('smsWarning');
    
    if (!smsWarning) return;
    
    if ((smsCheckbox && smsCheckbox.checked) || (whatsappCheckbox && whatsappCheckbox.checked)) {
        smsWarning.classList.remove('hidden');
    } else {
        smsWarning.classList.add('hidden');
    }
}

// ========== FORM VALIDATION ==========

function initFormValidation() {
    const form = document.getElementById('tenantAssignmentForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            let errors = [];
            
            // Get assignment type
            const assignmentType = document.querySelector('input[name="assignment_type"]:checked')?.value;
            
            if (!assignmentType) {
                isValid = false;
                errors.push('Please select an assignment type');
            }
            
            // Validate based on assignment type
            if (assignmentType === 'existing') {
                const tenantSelect = document.getElementById('tenant_id');
                if (!tenantSelect.value || tenantSelect.disabled) {
                    isValid = false;
                    errors.push('Please select a valid existing tenant');
                }
                
                // Validate file sizes for existing tenant
                const fileInput = document.getElementById('documentUploadExisting');
                if (fileInput.files.length > 0) {
                    let fileErrors = validateFiles(fileInput);
                    if (fileErrors.length > 0) {
                        isValid = false;
                        errors = errors.concat(fileErrors);
                    }
                }
                
            } else if (assignmentType === 'new') {
                // Validate required fields for new tenant
                const requiredFields = [
                    'tenant_name', 'tenant_email', 'tenant_phone', 'tenant_gender',
                    'move_in_date_new', 'employment_status',
                    'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'
                ];
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && (!field.value || field.value.trim() === '')) {
                        isValid = false;
                        const label = field.previousElementSibling?.textContent || field.name;
                        errors.push(`${label.replace('*', '').trim()} is required`);
                    }
                });
                
                // Validate email format
                const emailField = document.getElementById('tenant_email');
                if (emailField && emailField.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailField.value)) {
                        isValid = false;
                        errors.push('Please enter a valid email address');
                    }
                }
                
                // Validate phone number format
                const phoneField = document.getElementById('tenant_phone');
                if (phoneField && phoneField.value) {
                    const cleanedPhone = cleanPhoneNumber(phoneField.value);
                    if (!isValidPhoneNumber(cleanedPhone)) {
                        isValid = false;
                        errors.push('Please enter a valid phone number (+233XXXXXXXXX or 0XXXXXXXXX)');
                    }
                }
                
                // Validate emergency contact phone number
                const emergencyPhoneField = document.getElementById('emergency_contact_phone');
                if (emergencyPhoneField && emergencyPhoneField.value) {
                    const cleanedEmergencyPhone = cleanPhoneNumber(emergencyPhoneField.value);
                    if (!isValidPhoneNumber(cleanedEmergencyPhone)) {
                        isValid = false;
                        errors.push('Please enter a valid emergency contact phone number (+233XXXXXXXXX or 0XXXXXXXXX)');
                    }
                }
                
                // Validate file sizes for new tenant (if any files uploaded)
                const fileInput = document.getElementById('documentUploadNew');
                if (fileInput.files.length > 0) {
                    let fileErrors = validateFiles(fileInput);
                    if (fileErrors.length > 0) {
                        isValid = false;
                        errors = errors.concat(fileErrors);
                    }
                }
                
                // Validate at least one invitation channel is selected
                const invitationChannels = document.querySelectorAll('input[name="invitation_channels[]"]:checked');
                if (invitationChannels.length === 0) {
                    isValid = false;
                    errors.push('Please select at least one invitation channel');
                }
                
                // Validate SMS/WhatsApp channel phone numbers
                const smsChecked = document.getElementById('channel_sms')?.checked;
                const whatsappChecked = document.getElementById('channel_whatsapp')?.checked;
                
                if (smsChecked || whatsappChecked) {
                    const phoneField = document.getElementById('tenant_phone');
                    if (!phoneField || !phoneField.value.trim()) {
                        isValid = false;
                        errors.push('Phone number is required for SMS/WhatsApp invitations');
                    } else {
                        const cleanedPhone = cleanPhoneNumber(phoneField.value);
                        if (!isValidPhoneNumber(cleanedPhone)) {
                            isValid = false;
                            errors.push('Please enter a valid phone number for SMS/WhatsApp delivery');
                        }
                    }
                }
            }
            
            if (!isValid) {
                // Show errors in an alert
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            submitBtn.disabled = true;
            
            // Submit form
            form.submit();
        });
    }
}

function validateFiles(fileInput) {
    const errors = [];
    const maxSize = {{ $maxFileSize }} * 1024; // Convert to bytes
    
    Array.from(fileInput.files).forEach((file, index) => {
        if (file.size > maxSize) {
            errors.push(`${file.name} exceeds size limit ({{ round($maxFileSize / 1024, 1) }}MB)`);
        }
        
        // Check file type
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            errors.push(`${file.name} has invalid file type. Allowed: PDF, JPG, JPEG, PNG`);
        }
    });
    
    return errors;
}

function autoHideMessages() {
    setTimeout(() => {
        const successMessage = document.querySelector('.bg-green-100');
        if (successMessage) {
            successMessage.style.display = 'none';
        }
        
        const errorMessage = document.querySelector('.bg-red-100');
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
    }, 5000);
}
</script>

<style>
/* Assignment Type Cards */
.assignment-type-card {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: transparent;
}

.assignment-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.assignment-type-card.selected {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Invitation Channel Cards */
.invitation-channel-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: transparent;
    position: relative;
}

.invitation-channel-card:hover {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.02);
}

.invitation-channel-card.selected {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* FIX: Ensure checkbox is clickable and properly aligned */
.invitation-channel-card input[type="checkbox"] {
    position: relative;
    z-index: 2;
    cursor: pointer;
}

.invitation-channel-card label {
    cursor: pointer;
    display: flex;
    align-items: center;
    width: 100%;
}

/* Process Timeline Connectors */
.flex.items-start:not(:last-child) {
    position: relative;
}

.flex.items-start:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 1rem;
    top: 2rem;
    width: 2px;
    height: calc(100% - 1rem);
    background-color: var(--border-color);
}

/* Custom scrollbar for file lists */
#fileListExisting,
#fileListNew {
    max-height: 200px;
    overflow-y: auto;
}

#fileListExisting::-webkit-scrollbar,
#fileListNew::-webkit-scrollbar {
    width: 6px;
}

#fileListExisting::-webkit-scrollbar-track,
#fileListNew::-webkit-scrollbar-track {
    background: var(--bg-input);
    border-radius: 3px;
}

#fileListExisting::-webkit-scrollbar-thumb,
#fileListNew::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
    }
    
    .flex.space-x-3 {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .flex.space-x-3 > * {
        margin-bottom: 0.5rem;
    }
}

/* Animation for loading spinner */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

/* Button Styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-2px);
}

/* Index-specific form control styles with DARK DROPDOWN support */
.index-custom-dropdown,
.index-custom-input,
.index-custom-textarea {
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-dropdown:focus,
.index-custom-input:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Enhanced dropdown with dark theme support */
.index-custom-dropdown {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
    cursor: pointer;
}

/* Dark theme dropdown styling */
[data-theme="dark"] .index-custom-dropdown {
    background-color: #1f2937 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

/* Dark dropdown options styling */
[data-theme="dark"] .index-custom-dropdown option {
    background-color: #1f2937 !important;
    color: #f3f4f6 !important;
    padding: 10px;
}

/* Dark dropdown optgroup styling */
[data-theme="dark"] .index-custom-dropdown optgroup {
    background-color: #111827 !important;
    color: #9ca3af !important;
    font-weight: bold;
    padding: 5px;
}

/* Hover state for dark dropdown */
[data-theme="dark"] .index-custom-dropdown:hover {
    border-color: var(--primary) !important;
}

/* Focus state for dark dropdown */
[data-theme="dark"] .index-custom-dropdown:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2) !important;
}

/* Specific dark dropdown class for explicit dark styling */
.dark-dropdown {
    background-color: #1f2937 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

.dark-dropdown option {
    background-color: #1f2937 !important;
    color: #f3f4f6 !important;
}

.dark-dropdown optgroup {
    background-color: #111827 !important;
    color: #9ca3af !important;
}

/* Checkbox styles */
.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    z-index: 2;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-position: center;
    background-repeat: no-repeat;
    background-size: 0.75rem;
}

/* Dark theme checkbox */
[data-theme="dark"] .index-custom-checkbox {
    border-color: #6b7280;
    background-color: #374151;
}

[data-theme="dark"] .index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Additional dark theme form control styles */
[data-theme="dark"] .index-custom-input,
[data-theme="dark"] .index-custom-textarea {
    background-color: #1f2937 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

[data-theme="dark"] .index-custom-input::placeholder,
[data-theme="dark"] .index-custom-textarea::placeholder {
    color: #9ca3af !important;
}

/* File upload zone dark theme */
[data-theme="dark"] #documentDropzoneExisting,
[data-theme="dark"] #documentDropzoneNew {
    background-color: rgba(31, 41, 55, 0.5) !important;
    border-color: #4b5563 !important;
}

/* Card dark theme adjustments */
[data-theme="dark"] .card {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
}

/* Validation styling */
.border-red-500 {
    border-color: #ef4444 !important;
}

.border-green-500 {
    border-color: #10b981 !important;
}

.border-yellow-500 {
    border-color: #f59e0b !important;
}
</style>
@endsection