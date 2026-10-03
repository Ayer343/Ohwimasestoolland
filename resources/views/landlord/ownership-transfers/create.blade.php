{{-- landlord/ownership-transfers/create.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\Property;
    use App\Models\User;
    use App\Models\SystemSetting;

    // Check if there's a pending transfer
    $pendingTransfer = $property->currentOwnershipTransfer ?? null;
    
    // Get document types
    $documentTypes = PropertyOwnershipTransfer::getDocumentTypes();
    $statuses = PropertyOwnershipTransfer::getStatuses();
    
    // Set page title
    $pageTitle = "Transfer Ownership - {$property->property_name}";
    
    // Check user permissions
    $user = auth()->user();
    $isLandlord = $user->isLandlord();
    $ownsProperty = $user->id === $property->landlord_id;
    $hasPendingTransfer = $pendingTransfer !== null;
    
    // Check all conditions for transfer eligibility
    $canTransfer = $isLandlord && $ownsProperty && !$hasPendingTransfer;
    
    // Form default values
    $oldInput = old();
    
    // Configuration
    $bulkTransferEnabled = config('ownership_transfer.enable_bulk_transfer', false);
    $digitalSignatureEnabled = config('ownership_transfer.require_digital_signature', true); // Changed to true by default
    $digitalSignatureThreshold = config('ownership_transfer.digital_signature_threshold', 1000000);
    $webhookEnabled = config('ownership_transfer.webhook_enabled', false);
    $maxFileSize = config('ownership_transfer.max_file_size', 5) * 1024 * 1024;
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $expiryDays = config('ownership_transfer.expiry_days', 90);
    $trashRetentionDays = config('ownership_transfer.trash_retention_days', 30);
    $minDaysBeforePermanentDelete = config('ownership_transfer.min_days_before_permanent_delete', 30);
    
    // Property details
    $propertyAddress = isset($property->street_name) && isset($property->zone) 
        ? "{$property->street_name}, {$property->zone}" 
        : 'Address not available';
    $registrationNo = $property->registration_pattern ?? 'Not available';
    $currentLandlord = $property->landlord ?? null;
    
    // Get existing landlords (users with type LANDLORD)
    $existingLandlords = User::where('type', User::TYPE_LANDLORD)
        ->where('id', '!=', $user->id) // Exclude current user
        ->where('status', User::STATUS_ACTIVE)
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'phone']);
    
    // Get units for readiness report
    $units = $property->units ?? collect();
    $unitsWithTenants = $units->filter(function($unit) {
        return !is_null($unit->tenant_id);
    });
    $hasTenants = $unitsWithTenants->isNotEmpty();
    
    // Get related properties for bulk transfer
    $relatedProperties = $bulkTransferEnabled && $canTransfer 
        ? $user->properties()
            ->where('id', '!=', $property->id)
            ->whereDoesntHave('currentOwnershipTransfer', function($query) {
                $query->whereIn('status', [
                    PropertyOwnershipTransfer::STATUS_PENDING,
                    PropertyOwnershipTransfer::STATUS_APPROVED
                ]);
            })
            ->select('id', 'property_name', 'registration_pattern', 'street_name', 'zone')
            ->withCount('units')
            ->get()
        : collect();
    
    // Check if bulk transfer is available
    $bulkTransferAvailable = $bulkTransferEnabled && $relatedProperties->isNotEmpty();
    
    // Workflow steps
    $workflowSteps = PropertyOwnershipTransfer::getWorkflowSteps();
    
    // Get recent transfers for this property
    $recentTransfers = PropertyOwnershipTransfer::where('property_id', $property->id)
        ->where('status', '!=', PropertyOwnershipTransfer::STATUS_PENDING)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    // Check if property has completed transfers
    $hasCompletedTransfers = PropertyOwnershipTransfer::where('property_id', $property->id)
        ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)
        ->exists();
    
    // Debug info (hidden by default)
    $showDebug = false;
    
    // Generate initial document reference
    $initialDocRef = 'TRANS-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Get system settings for support contact
    try {
        $systemSettings = SystemSetting::getSettings();
        $systemEmail = $systemSettings->system_email ?? 'support@example.com';
        $systemPhone = $systemSettings->system_phone ?? '+233 123 456 789';
        $systemPhoneClean = preg_replace('/[^0-9+]/', '', $systemPhone);
    } catch (\Exception $e) {
        $systemEmail = 'support@example.com';
        $systemPhone = '+233 123 456 789';
        $systemPhoneClean = '+233123456789';
    }
@endphp

@extends('layouts.landlord')

@section('title', $pageTitle)

{{-- Prevent duplicate navigation header --}}
@section('page-header')
@stop

@section('content')
<div class="ownership-transfer-container" style="padding-left: 0; margin-left: 0;">
    <div class="grid grid-cols-1 gap-6 mb-6">
        <!-- Header Card -->
        <div class="card">
            <div class="flex justify-between items-center p-6 flex-wrap gap-4">
                <div class="flex items-center">
                    <!-- Icon -->
                    <div class="mr-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <i class="fas fa-exchange-alt text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                            <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i> 
                            Transfer Property Ownership
                            @if($pendingTransfer)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Pending Request Exists
                            </span>
                            @endif
                            @if($hasCompletedTransfers)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-history mr-1"></i> Previous Transfers
                            </span>
                            @endif
                        </h2>
                        <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>Initiate ownership transfer for </span>
                            <span class="font-semibold" style="color: var(--primary);">{{ $property->property_name }}</span>
                            <span class="mx-1">•</span>
                            <i class="fas fa-building mr-1"></i>
                            <span>{{ $registrationNo }}</span>
                            @if($hasTenants)
                            <span class="mx-1">•</span>
                            <i class="fas fa-users mr-1"></i>
                            <span>{{ $unitsWithTenants->count() }} tenant(s)</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                    <div class="flex space-x-3 mt-2">
                        <a href="{{ route('properties.show', $property->id) }}" 
                           class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Property
                        </a>
                        <a href="{{ route('landlord.ownership-transfers.index') }}" 
                           class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-list mr-2"></i> View All Transfers
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Property Info Card -->
            <div class="mt-4 p-4 mx-6 rounded-lg mb-6" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-3 mt-0.5" style="color: var(--info);"></i>
                    <div class="flex-1">
                        <h3 class="font-medium" style="color: var(--text-primary);">Property Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Property Name</div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->property_name ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Address</div>
                                <div class="text-sm" style="color: var(--text-primary);">{{ $propertyAddress }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Registration</div>
                                <div class="text-sm" style="color: var(--text-primary);">{{ $registrationNo }}</div>
                            </div>
                            <div>
                                <div class="text-xs" style="color: var(--text-secondary);">Current Owner</div>
                                <div class="text-sm" style="color: var(--text-primary);">{{ $currentLandlord->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        @if($hasTenants)
                        <div class="mt-3 pt-3 border-t" style="border-color: rgba(var(--info-rgb), 0.2);">
                            <div class="flex items-center">
                                <i class="fas fa-users mr-2" style="color: var(--warning);"></i>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <strong class="text-warning">{{ $unitsWithTenants->count() }}</strong> active tenant(s) will be transferred with this property.
                                    Their tenancy agreements remain valid under the new owner.
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transfers Alert -->
        @if($recentTransfers->isNotEmpty())
        <div class="card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-history text-lg" style="color: var(--info);"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="font-semibold" style="color: var(--text-primary);">
                            Recent Transfer History
                        </h3>
                        <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                            <p>This property has had {{ $recentTransfers->count() }} recent transfer request(s):</p>
                            <div class="mt-2 space-y-1">
                                @foreach($recentTransfers as $recentTransfer)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full" 
                                          style="background-color: {{ $recentTransfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                                 color: {{ $recentTransfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED ? 'var(--success)' : 'var(--danger)' }};">
                                        {{ $recentTransfer->status_label }}
                                    </span>
                                    <span class="text-xs">{{ $recentTransfer->created_at->format('M j, Y') }}</span>
                                    <span class="text-xs">→ {{ $recentTransfer->newLandlord->name ?? $recentTransfer->new_owner_name }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Bulk Transfer Notice -->
        @if($bulkTransferAvailable)
        <div class="card" id="bulkTransferNotice">
            <div class="p-6">
                <div class="flex items-center flex-wrap gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-layer-group text-lg" style="color: var(--primary);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold" style="color: var(--text-primary);">
                            Bulk Transfer Available
                        </h3>
                        <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                            <p class="mb-2">
                                You have {{ $relatedProperties->count() }} other propert{{ $relatedProperties->count() === 1 ? 'y' : 'ies' }} that can be transferred to the same owner. 
                                Would you like to include them in this transfer?
                            </p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                <button type="button" 
                                        onclick="showBulkTransferModal()"
                                        class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                    <i class="fas fa-layer-group mr-2"></i> Yes, Transfer Multiple Properties
                                </button>
                                <button type="button" 
                                        onclick="hideBulkTransferNotice()"
                                        class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                    <i class="fas fa-times mr-2"></i> No, Single Property Only
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Pending Transfer Alert -->
        @if($pendingTransfer)
        <div class="card" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.3);">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="font-semibold" style="color: var(--text-primary);">
                            Pending Ownership Transfer Request
                        </h3>
                        <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                            <p class="mb-2">
                                There is already a pending ownership transfer request for this property. 
                                You cannot create a new request until the current one is resolved.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                <a href="{{ route('properties.ownership-transfers.show', [$property->id, $pendingTransfer->id]) }}" 
                                   class="index-custom-btn px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                                   style="background-color: var(--warning); color: white;">
                                    <i class="fas fa-eye mr-2"></i> View Pending Transfer
                                </a>
                                @if($pendingTransfer->can_be_cancelled && $pendingTransfer->current_landlord_id === $user->id)
                                <button type="button" 
                                        onclick="confirmCancelTransfer({{ $pendingTransfer->id }})"
                                        class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                    <i class="fas fa-times mr-2"></i> Cancel Request
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($canTransfer)
        <!-- Main Form Container -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Section -->
            <div class="lg:col-span-2" style="overflow-x: visible;">
                <div class="card p-6">
                    <!-- Form Header -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> New Owner Information
                        </h3>
                        <p class="text-sm mb-6" style="color: var(--text-secondary);">
                            Provide details of the new property owner. You can select an existing landlord or enter new details.
                        </p>

                        <!-- Progress Steps -->
                        <div class="mb-8">
                            <div class="flex items-center justify-between">
                                @foreach(['owner_details' => 'Owner Details', 'transfer_info' => 'Transfer Info', 'review' => 'Review'] as $key => $label)
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full text-white font-bold step-indicator {{ $key === 'owner_details' ? 'step-active' : '' }}"
                                         data-step="{{ $key }}">
                                        {{ array_search($key, array_keys(['owner_details' => 1, 'transfer_info' => 2, 'review' => 3])) + 1 }}
                                    </div>
                                    <div class="ml-3 hidden sm:block">
                                        <div class="text-sm font-medium step-label" style="color: {{ $key === 'owner_details' ? 'var(--text-primary)' : 'var(--text-secondary)' }};">
                                            {{ $label }}
                                        </div>
                                    </div>
                                </div>
                                
                                @if(!$loop->last)
                                <div class="flex-1 mx-4 hidden sm:block">
                                    <div class="h-1 rounded-full overflow-hidden step-connector">
                                        <div class="h-full step-progress" style="width: {{ $key === 'owner_details' ? '0%' : '33%' }}"></div>
                                    </div>
                                </div>
                                @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="ownershipTransferForm" 
                          action="{{ route('properties.ownership-transfers.store', $property->id) }}" 
                          method="POST" 
                          enctype="multipart/form-data"
                          data-is-bulk="false">
                        @csrf
                        <input type="hidden" name="bulk_transfer" value="false" id="bulkTransferFlag">
                        <input type="hidden" name="property_id" value="{{ $property->id }}" id="singlePropertyId">
                        <input type="hidden" name="has_tenants" value="{{ $hasTenants ? 'true' : 'false' }}" id="hasTenantsFlag">

                        <div class="space-y-8">
                            <!-- Section 1: New Owner Details -->
                            <div class="p-6 rounded-xl step-section active-section" data-section="owner_details" style="border: 1px solid var(--border-color);">
                                <h4 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i>
                                    New Property Owner Information
                                    <span class="ml-2 text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        Required
                                    </span>
                                </h4>
                                
                                <!-- Owner Selection Type Toggle -->
                                <div class="mb-6">
                                    <div class="flex border rounded-lg overflow-hidden" style="border-color: var(--border-color);">
                                        <button type="button" 
                                                id="selectExistingBtn" 
                                                class="flex-1 px-4 py-2 text-center transition-all owner-type-btn active"
                                                onclick="selectOwnerType('existing')">
                                            <i class="fas fa-users mr-2"></i> Select Existing Landlord
                                        </button>
                                        <button type="button" 
                                                id="enterNewBtn" 
                                                class="flex-1 px-4 py-2 text-center transition-all owner-type-btn"
                                                onclick="selectOwnerType('new')">
                                            <i class="fas fa-user-plus mr-2"></i> Enter New Landlord Details
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Existing Landlord Selection Section -->
                                <div id="existingLandlordSection" class="mb-6">
                                    <label for="existing_landlord_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Select Existing Landlord <span style="color: var(--danger);">*</span>
                                    </label>
                                    <select name="existing_landlord_id" 
                                            id="existing_landlord_id"
                                            class="w-full p-2 border rounded"
                                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                        <option value="">-- Select a landlord --</option>
                                        @foreach($existingLandlords as $landlord)
                                        <option value="{{ $landlord->id }}" 
                                                data-name="{{ $landlord->name }}"
                                                data-phone="{{ $landlord->phone }}"
                                                data-email="{{ $landlord->email }}"
                                                data-address="{{ $landlord->location ?? '' }}">
                                            {{ $landlord->name }} ({{ $landlord->email ?? $landlord->phone }})
                                        </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i> Select an existing landlord from the system
                                    </p>
                                </div>
                                
                                <!-- New Owner Details Section -->
                                <div id="newOwnerDetailsSection" class="hidden">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- New Owner Name -->
                                        <div>
                                            <label for="new_owner_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Full Name <span style="color: var(--danger);">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                    <i class="fas fa-user" style="color: var(--text-secondary);"></i>
                                                </div>
                                                <input type="text" 
                                                       name="new_owner_name" 
                                                       id="new_owner_name"
                                                       value="{{ old('new_owner_name') }}"
                                                       class="w-full p-2 border rounded pl-10 pr-3 @error('new_owner_name') border-red-500 @enderror"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="Enter full name"
                                                       autocomplete="off">
                                            </div>
                                            @error('new_owner_name')
                                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- New Owner Phone -->
                                        <div>
                                            <label for="new_owner_phone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Phone Number <span style="color: var(--danger);">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                    <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
                                                </div>
                                                <input type="tel" 
                                                       name="new_owner_phone" 
                                                       id="new_owner_phone"
                                                       value="{{ old('new_owner_phone') }}"
                                                       class="w-full p-2 border rounded pl-10 pr-10 @error('new_owner_phone') border-red-500 @enderror"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="e.g., +233595652410 or 0595652410">
                                                <div id="phone-validation" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                                    <i class="fas fa-check" id="phone-valid-icon" style="color: var(--success);"></i>
                                                    <i class="fas fa-times" id="phone-invalid-icon" style="color: var(--danger);"></i>
                                                </div>
                                            </div>
                                            @error('new_owner_phone')
                                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- New Owner Email -->
                                        <div>
                                            <label for="new_owner_email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Email Address
                                            </label>
                                            <div class="relative">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                    <i class="fas fa-envelope" style="color: var(--text-secondary);"></i>
                                                </div>
                                                <input type="email" 
                                                       name="new_owner_email" 
                                                       id="new_owner_email"
                                                       value="{{ old('new_owner_email') }}"
                                                       class="w-full p-2 border rounded pl-10 pr-3 @error('new_owner_email') border-red-500 @enderror"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="optional@example.com"
                                                       autocomplete="off">
                                            </div>
                                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-info-circle mr-1"></i> Email is optional but recommended for notifications
                                            </p>
                                            @error('new_owner_email')
                                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- New Owner Address -->
                                        <div>
                                            <label for="new_owner_address" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                Address
                                            </label>
                                            <div class="relative">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                    <i class="fas fa-map-marker-alt" style="color: var(--text-secondary);"></i>
                                                </div>
                                                <input type="text" 
                                                       name="new_owner_address" 
                                                       id="new_owner_address"
                                                       value="{{ old('new_owner_address') }}"
                                                       class="w-full p-2 border rounded pl-10 pr-3 @error('new_owner_address') border-red-500 @enderror"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                       placeholder="Optional - physical address">
                                            </div>
                                            @error('new_owner_address')
                                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end">
                                    <button type="button" 
                                            onclick="nextStep('transfer_info')"
                                            class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                        Continue to Transfer Details
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Section 2: Transfer Details -->
                            <div class="p-6 rounded-xl step-section" data-section="transfer_info" style="border: 1px solid var(--border-color); display: none;">
                                <h4 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i>
                                    Transfer Details & Documentation
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Transfer Date -->
                                    <div>
                                        <label for="transfer_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Transfer Date <span style="color: var(--danger);">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                <i class="fas fa-calendar-alt" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <input type="date" 
                                                   name="transfer_date" 
                                                   id="transfer_date"
                                                   value="{{ old('transfer_date', date('Y-m-d')) }}"
                                                   min="{{ date('Y-m-d', strtotime('-1 year')) }}"
                                                   max="{{ date('Y-m-d', strtotime('+1 year')) }}"
                                                   class="w-full p-2 border rounded pl-10 pr-3 @error('transfer_date') border-red-500 @enderror"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   required>
                                        </div>
                                        @error('transfer_date')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Sale Amount -->
                                    <div>
                                        <label for="sale_amount" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Sale Amount
                                            @if($digitalSignatureEnabled && $digitalSignatureThreshold > 0)
                                            <span class="text-xs ml-1" style="color: var(--warning);">
                                                (Digital signature required if ≥ GHS {{ number_format($digitalSignatureThreshold, 2) }})
                                            </span>
                                            @endif
                                        </label>
                                        <div class="relative">
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                <span class="font-medium" style="color: var(--text-secondary);">GHS</span>
                                            </div>
                                            <input type="number" 
                                                   name="sale_amount" 
                                                   id="sale_amount"
                                                   value="{{ old('sale_amount') }}"
                                                   min="0"
                                                   step="0.01"
                                                   class="w-full p-2 border rounded pl-12 pr-3 @error('sale_amount') border-red-500 @enderror"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="0.00">
                                        </div>
                                        @error('sale_amount')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Document Type -->
                                    <div>
                                        <label for="document_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Document Type <span style="color: var(--danger);">*</span>
                                        </label>
                                        <select name="document_type" 
                                                id="document_type"
                                                class="w-full p-2 border rounded @error('document_type') border-red-500 @enderror"
                                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                required>
                                            <option value="">Select document type</option>
                                            @foreach($documentTypes as $key => $type)
                                                <option value="{{ $key }}" {{ old('document_type') == $key ? 'selected' : '' }}>
                                                    {{ $type }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('document_type')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Document Reference -->
                                    <div>
                                        <label for="document_reference" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Document Reference Number <span style="color: var(--danger);">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                                <i class="fas fa-hashtag" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <input type="text" 
                                                   name="document_reference" 
                                                   id="document_reference"
                                                   value="{{ old('document_reference', $initialDocRef) }}"
                                                   class="w-full p-2 border rounded pl-10 pr-24 @error('document_reference') border-red-500 @enderror"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="e.g., DEED/123/2024"
                                                   required>
                                            <button type="button" 
                                                    onclick="generateDocumentReference()"
                                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-xs px-2 py-1 rounded"
                                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                Generate
                                            </button>
                                        </div>
                                        @error('document_reference')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Transfer Document Upload -->
                                    <div class="md:col-span-2">
                                        <label for="transfer_document" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Upload Transfer Document <span style="color: var(--danger);">*</span>
                                        </label>
                                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 rounded-xl hover:border-primary transition-colors"
                                             id="dropzone"
                                             style="border: 2px dashed var(--border-color);">
                                            <div class="space-y-2 text-center">
                                                <div class="mx-auto h-12 w-12">
                                                    <i class="fas fa-cloud-upload-alt text-3xl" style="color: var(--text-secondary);"></i>
                                                </div>
                                                <div class="flex text-sm justify-center" style="color: var(--text-secondary);">
                                                    <label for="transfer_document" class="relative cursor-pointer rounded-md font-medium hover:text-primary" style="color: var(--primary);">
                                                        <span>Upload a file</span>
                                                        <input id="transfer_document" 
                                                               name="transfer_document" 
                                                               type="file" 
                                                               class="sr-only"
                                                               accept=".pdf,.jpg,.jpeg,.png"
                                                               required>
                                                    </label>
                                                    <p class="pl-1">or drag and drop</p>
                                                </div>
                                                <p class="text-xs" style="color: var(--text-secondary);" id="fileRequirements">
                                                    PDF, JPG, PNG up to {{ config('ownership_transfer.max_file_size', 5) }}MB
                                                </p>
                                                <div class="mt-4" id="filePreview" style="display: none;">
                                                    <div class="flex items-center justify-center rounded-lg p-3" style="background-color: var(--bg-secondary);">
                                                        <i class="fas fa-file-pdf mr-2 text-lg" style="color: var(--danger);" id="fileIcon"></i>
                                                        <div class="text-left">
                                                            <p class="text-sm font-medium" style="color: var(--text-primary);" id="fileName"></p>
                                                            <p class="text-xs" style="color: var(--text-secondary);" id="fileSize"></p>
                                                        </div>
                                                        <button type="button" 
                                                                onclick="clearFile()" 
                                                                class="ml-3" style="color: var(--danger);">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @error('transfer_document')
                                            <p class="mt-2 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                
                                <!-- Digital Signature Section - FIXED: Added visible class for testing -->
                                @if($digitalSignatureEnabled)
                                <div class="mt-8 p-4 rounded-lg" id="digitalSignatureSection" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                    <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                        <i class="fas fa-signature mr-2" style="color: var(--warning);"></i>
                                        Digital Signature
                                        @if($digitalSignatureThreshold > 0)
                                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                            Required for amounts ≥ GHS {{ number_format($digitalSignatureThreshold, 2) }}
                                        </span>
                                        @endif
                                    </h5>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        <p class="mb-3">
                                            A digital signature provides legal validity to this transfer document.
                                            @if($digitalSignatureThreshold > 0)
                                            <strong>Note:</strong> If the sale amount is GHS {{ number_format($digitalSignatureThreshold, 2) }} or more, a digital signature is required.
                                            @endif
                                        </p>
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="apply_digital_signature" 
                                                   id="apply_digital_signature"
                                                   class="w-4 h-4 rounded mr-2"
                                                   value="1">
                                            <label for="apply_digital_signature" style="color: var(--text-primary);">
                                                I confirm that I will apply a digital signature to this document
                                            </label>
                                        </div>
                                        <div id="digitalSignatureFields" class="mt-3 hidden space-y-3">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="signature_token" class="block text-sm font-medium mb-1">Signature Token</label>
                                                    <input type="text" 
                                                           name="signature_token" 
                                                           id="signature_token"
                                                           class="w-full p-2 border rounded"
                                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                           placeholder="Enter signature token">
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        The unique token provided by your digital signature provider
                                                    </p>
                                                </div>
                                                <div>
                                                    <label for="signature_timestamp" class="block text-sm font-medium mb-1">Timestamp</label>
                                                    <input type="datetime-local" 
                                                           name="signature_timestamp" 
                                                           id="signature_timestamp"
                                                           class="w-full p-2 border rounded"
                                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                           value="{{ now()->format('Y-m-d\TH:i') }}">
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        The exact time when the signature was applied
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Webhook Callbacks -->
                                @if($webhookEnabled)
                                <div class="mt-8 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                    <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                        <i class="fas fa-bell mr-2" style="color: var(--info);"></i>
                                        Webhook Notifications (Optional)
                                    </h5>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        <p class="mb-3">Receive notifications via webhook for transfer status updates.</p>
                                        <div>
                                            <label for="webhook_url" class="block text-sm font-medium mb-1">Webhook URL</label>
                                            <input type="url" 
                                                   name="webhook_url" 
                                                   id="webhook_url"
                                                   class="w-full p-2 border rounded"
                                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                   placeholder="https://example.com/webhook">
                                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                                We'll send POST requests to this URL when transfer status changes
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                <div class="mt-6 flex justify-between">
                                    <button type="button" 
                                            onclick="prevStep('owner_details')"
                                            class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Owner Details
                                    </button>
                                    
                                    <button type="button" 
                                            onclick="nextStep('review')"
                                            class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                        Continue to Review
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Section 3: Additional Information & Review -->
                            <div class="p-6 rounded-xl step-section" data-section="review" style="border: 1px solid var(--border-color); display: none;">
                                <h4 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                                    Review & Submit
                                </h4>
                                
                                <div class="space-y-6">
                                    <!-- Reason for Transfer -->
                                    <div>
                                        <label for="reason_for_transfer" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Reason for Transfer (Optional)
                                        </label>
                                        <textarea name="reason_for_transfer" 
                                                  id="reason_for_transfer"
                                                  rows="3"
                                                  class="w-full p-2 border rounded @error('reason_for_transfer') border-red-500 @enderror"
                                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                  placeholder="e.g., Property sale, inheritance, gift, etc.">{{ old('reason_for_transfer') }}</textarea>
                                        @error('reason_for_transfer')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Additional Notes -->
                                    <div>
                                        <label for="notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Additional Notes (Optional)
                                        </label>
                                        <textarea name="notes" 
                                                  id="notes"
                                                  rows="3"
                                                  class="w-full p-2 border rounded @error('notes') border-red-500 @enderror"
                                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                  placeholder="Any additional information for the new owner or administrators">{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    
                                    <!-- Bulk Notes (for bulk transfers) -->
                                    <div id="bulkNotesSection" class="hidden">
                                        <label for="bulk_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Bulk Transfer Notes
                                        </label>
                                        <textarea name="bulk_notes" 
                                                  id="bulk_notes"
                                                  rows="2"
                                                  class="w-full p-2 border rounded"
                                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                  placeholder="Additional notes for all properties in this bulk transfer"></textarea>
                                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                            These notes will be applied to all properties in this bulk transfer
                                        </p>
                                    </div>

                                    <!-- Tenant Transfer Warning -->
                                    @if($hasTenants)
                                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                        <div class="flex items-start">
                                            <i class="fas fa-users mr-3 mt-0.5" style="color: var(--warning);"></i>
                                            <div>
                                                <h5 class="font-medium mb-1" style="color: var(--text-primary);">Tenant Transfer Notice</h5>
                                                <p class="text-sm" style="color: var(--text-secondary);">
                                                    This property has <strong>{{ $unitsWithTenants->count() }} active tenant(s)</strong> in {{ $unitsWithTenants->count() }} unit(s). 
                                                    Upon transfer completion, all tenants will automatically be transferred to the new landlord. 
                                                    Their tenancy agreements remain valid and continue under the new ownership.
                                                </p>
                                                <div class="mt-3">
                                                    <label class="flex items-center">
                                                        <input type="checkbox" 
                                                               name="confirm_tenant_transfer" 
                                                               id="confirm_tenant_transfer"
                                                               class="w-4 h-4 rounded mr-2"
                                                               style="border-color: var(--border-color); color: var(--primary);">
                                                        <span class="text-sm" style="color: var(--text-primary);">
                                                            I confirm that I understand the tenants will be transferred to the new landlord
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Review Summary -->
                                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                        <h5 class="font-medium mb-4" style="color: var(--text-primary);">Transfer Summary</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm" id="reviewSummary">
                                            <div>
                                                <div style="color: var(--text-secondary);">New Owner</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewOwnerName">-</div>
                                            </div>
                                            <div>
                                                <div style="color: var(--text-secondary);">Phone</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewPhone">-</div>
                                            </div>
                                            <div>
                                                <div style="color: var(--text-secondary);">Transfer Date</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewDate">-</div>
                                            </div>
                                            <div>
                                                <div style="color: var(--text-secondary);">Document Type</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewDocType">-</div>
                                            </div>
                                            <div>
                                                <div style="color: var(--text-secondary);">Sale Amount</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewAmount">-</div>
                                            </div>
                                            <div>
                                                <div style="color: var(--text-secondary);">Document Reference</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewDocRef">-</div>
                                            </div>
                                            <div class="md:col-span-2">
                                                <div style="color: var(--text-secondary);">Properties Included</div>
                                                <div class="font-medium" style="color: var(--text-primary);" id="reviewProperties">1 property</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Form Submission & Terms -->
                                <div class="mt-8 p-6 rounded-xl" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="flex items-start mb-6">
                                        <div class="flex items-center h-5">
                                            <input id="terms" 
                                                   name="terms" 
                                                   type="checkbox" 
                                                   class="w-4 h-4 rounded focus:ring-primary"
                                                   style="border-color: var(--border-color); color: var(--primary);"
                                                   required>
                                        </div>
                                        <label for="terms" class="ml-3 text-sm" style="color: var(--text-primary);">
                                            I confirm that:
                                            <ol class="mt-2 space-y-1 text-sm list-decimal list-inside" style="color: var(--text-secondary);">
                                                <li>All information provided is accurate and complete</li>
                                                <li>I have the legal authority to transfer ownership of this property</li>
                                                <li>I understand this request requires administrative approval</li>
                                                <li>The transfer will only be completed after the new owner accepts the invitation</li>
                                                <li>I will be notified of any fees or charges associated with this transfer</li>
                                                @if($hasTenants)
                                                <li>I acknowledge that all existing tenants will be transferred to the new landlord</li>
                                                @endif
                                            </ol>
                                        </label>
                                    </div>
                                    @error('terms')
                                        <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror

                                    <!-- Form Actions -->
                                    <div class="flex flex-col sm:flex-row justify-between items-center pt-6 border-t" style="border-color: var(--border-color);">
                                        <div class="mb-4 sm:mb-0">
                                            <button type="button" 
                                                    onclick="prevStep('transfer_info')"
                                                    class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                                <i class="fas fa-edit mr-2"></i> Edit Transfer Details
                                            </button>
                                        </div>
                                        
                                        <div class="flex space-x-3">
                                            <button type="button" 
                                                    onclick="previewTransfer()"
                                                    class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                                <i class="fas fa-eye mr-2"></i> Preview Request
                                            </button>
                                            
                                            <button type="submit" 
                                                    id="submitButton"
                                                    class="index-custom-btn btn-primary px-6 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                                <i class="fas fa-paper-plane mr-2"></i> Submit Transfer Request
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Information Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Selected Properties (for bulk) -->
                <div id="selectedPropertiesCard" class="card hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> 
                            Selected Properties
                            <span class="ml-2 text-xs px-2 py-1 rounded-full" 
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                  id="selectedCount">0</span>
                        </h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto" id="selectedPropertiesList">
                            <!-- Properties will be listed here -->
                        </div>
                        <button type="button" 
                                onclick="showBulkTransferModal()"
                                class="w-full mt-4 index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i> Edit Selection
                        </button>
                    </div>
                </div>

                <!-- Process Information -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sync-alt mr-2" style="color: var(--info);"></i> Transfer Process
                        </h3>
                        <ol class="space-y-3 text-sm" style="color: var(--text-secondary);">
                            <li class="flex items-start">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold mr-3"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">1</span>
                                Submit request with new owner details
                            </li>
                            <li class="flex items-start">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold mr-3"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">2</span>
                                Admin reviews and approves request
                            </li>
                            <li class="flex items-start">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold mr-3"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">3</span>
                                New owner receives invitation
                            </li>
                            <li class="flex items-start">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold mr-3"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">4</span>
                                New owner accepts and completes registration
                            </li>
                            <li class="flex items-start">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold mr-3"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">5</span>
                                Transfer completed and certificate generated
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Requirements Card -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clipboard-check mr-2" style="color: var(--success);"></i> Requirements
                        </h3>
                        <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                Valid government ID of new owner
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                Transfer/sale agreement
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                Proof of ownership (title deed)
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                Clearance certificates (if applicable)
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Timeline Card -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--warning);"></i> Processing Time
                        </h3>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p class="mb-3">Typical processing time: <span class="font-semibold" style="color: var(--text-primary);">7-14 working days</span></p>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Review & Approval</span>
                                    <span class="font-medium" style="color: var(--text-primary);">2-3 days</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>New Owner Registration</span>
                                    <span class="font-medium" style="color: var(--text-primary);">1-2 days</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Document Processing</span>
                                    <span class="font-medium" style="color: var(--text-primary);">4-7 days</span>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                                <div class="flex justify-between">
                                    <span>Transfer validity:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">{{ $expiryDays }} days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Card - Using System Settings -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-headset mr-2" style="color: var(--primary);"></i> Need Help?
                        </h3>
                        <div class="text-sm space-y-3" style="color: var(--text-secondary);">
                            <p>If you have questions about the transfer process:</p>
                            <ul class="space-y-2">
                                <li class="flex items-center">
                                    <i class="fas fa-envelope w-5" style="color: var(--primary);"></i>
                                    <a href="mailto:{{ $systemEmail }}" class="ml-2 hover:underline" style="color: var(--primary);">
                                        {{ $systemEmail }}
                                    </a>
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-phone w-5" style="color: var(--primary);"></i>
                                    <a href="tel:{{ $systemPhoneClean }}" class="ml-2 hover:underline" style="color: var(--primary);">
                                        {{ $systemPhone }}
                                    </a>
                                </li>
                            </ul>
                            <div class="mt-4 pt-3 border-t" style="border-color: var(--border-color);">
                                <div class="flex items-start">
                                    <i class="fas fa-clock mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <div>
                                        <p class="text-xs">Support Hours: <strong>Mon-Fri, 9:00 AM - 5:00 PM</strong></p>
                                        <p class="text-xs mt-1">Response time: <strong>Within 24 hours</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Unauthorized Message -->
        @if(!$canTransfer && !$pendingTransfer)
        <div class="card p-8 text-center">
            <div class="mx-auto max-w-md">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-ban text-2xl" style="color: var(--danger);"></i>
                </div>
                <h2 class="mt-6 text-2xl font-bold" style="color: var(--text-primary);">Access Denied</h2>
                
                @if(!$isLandlord)
                <p class="mt-2" style="color: var(--text-secondary);">
                    <i class="fas fa-user-tag mr-1"></i>
                    Only landlords can transfer property ownership. 
                    Your account type: <strong>{{ User::getTypeName($user->type) }}</strong>
                </p>
                @elseif(!$ownsProperty)
                <p class="mt-2" style="color: var(--text-secondary);">
                    <i class="fas fa-house-user mr-1"></i>
                    You can only transfer ownership of properties you own. 
                    This property is owned by another landlord.
                </p>
                @endif
                
                <div class="mt-6">
                    <a href="{{ route('properties.my-properties') }}" 
                       class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to My Properties
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Bulk Transfer Modal -->
<div id="bulkTransferModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkTransferModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> 
                    Select Properties for Bulk Transfer
                </h3>
                <button type="button" onclick="hideBulkTransferModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            
            <div class="modal-body space-y-6">
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Select additional properties to transfer to the same new owner. 
                                All selected properties will share the same transfer details.
                                <strong class="block mt-1">Current property will always be included.</strong>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <!-- Current property (always included, read-only) -->
                    <div class="flex items-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 2px solid var(--primary);">
                        <input type="checkbox" 
                               checked
                               disabled
                               class="w-4 h-4 rounded mr-3 opacity-50">
                        <div class="flex-1">
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $property->property_name }}
                                <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    Current Property
                                </span>
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                {{ $property->registration_pattern }} • {{ $property->street_name }}, {{ $property->zone }}
                            </div>
                        </div>
                    </div>
                    
                    @foreach($relatedProperties as $relatedProperty)
                    <div class="flex items-center p-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors property-item"
                         style="border: 1px solid var(--border-color);">
                        <input type="checkbox" 
                               name="bulk_property_ids[]" 
                               value="{{ $relatedProperty->id }}" 
                               id="property_{{ $relatedProperty->id }}"
                               class="w-4 h-4 rounded mr-3 property-checkbox"
                               data-property-name="{{ $relatedProperty->property_name }}"
                               data-property-registration="{{ $relatedProperty->registration_pattern }}"
                               data-property-address="{{ $relatedProperty->street_name }}, {{ $relatedProperty->zone }}"
                               data-property-units="{{ $relatedProperty->units_count }}">
                        <label for="property_{{ $relatedProperty->id }}" class="flex-1 cursor-pointer">
                            <div class="font-medium" style="color: var(--text-primary);">{{ $relatedProperty->property_name }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                {{ $relatedProperty->registration_pattern }} • {{ $relatedProperty->street_name }}, {{ $relatedProperty->zone }}
                            </div>
                        </label>
                        <div class="flex items-center gap-2">
                            <div class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-building mr-1"></i> {{ $relatedProperty->units_count }} unit(s)
                            </div>
                            <div class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                Available
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                @if($relatedProperties->isEmpty())
                <div class="text-center py-8">
                    <i class="fas fa-layer-group text-4xl mb-4" style="color: var(--text-secondary);"></i>
                    <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Additional Properties Available</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        All your other properties either have pending transfers or cannot be transferred at this time.
                    </p>
                </div>
                @endif
            </div>
            
            <div class="modal-footer">
                <div class="flex justify-between items-center w-full">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-1"></i>
                        <span id="selectedPropertiesCount">1</span> propert<span id="selectedPropertiesPlural">y</span> selected
                        (including current)
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="hideBulkTransferModal()" 
                                class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="button" onclick="confirmBulkSelection()" 
                                class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white">
                            <i class="fas fa-check mr-2"></i> Confirm Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hidePreview()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-2" style="color: var(--primary);"></i> Transfer Request Preview
                </h3>
                <button type="button" onclick="hidePreview()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            
            <div id="previewContent" class="modal-body space-y-6">
                <!-- Preview content will be inserted here -->
            </div>
            
            <div class="modal-footer">
                <div class="w-full p-4 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle mr-2" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">
                                Important Notice
                            </h4>
                            <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                                <p>
                                    After submission, this request will be reviewed by administrators. 
                                    You will be notified once it's approved and the new owner will receive an invitation.
                                </p>
                                @if($hasTenants)
                                <p class="mt-2">
                                    <strong>Tenant Transfer:</strong> All existing tenants will be automatically transferred to the new landlord.
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="hidePreview()" 
                            class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-edit mr-2"></i> Edit Request
                    </button>
                    <button type="button" onclick="submitForm()" 
                            class="index-custom-btn btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Transfer Modal -->
<div id="cancelTransferModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideCancelModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Transfer Request
                </h3>
                <button type="button" onclick="hideCancelModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to cancel this ownership transfer request? This action cannot be undone.
                </p>
                <form id="cancelTransferForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="mb-4">
                        <label for="cancel_notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for cancellation (Optional)
                        </label>
                        <textarea name="notes" 
                                  id="cancel_notes"
                                  rows="3"
                                  class="w-full p-2 border rounded"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Please provide a reason for cancellation..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <div class="flex space-x-3">
                    <button type="button" onclick="hideCancelModal()" 
                            class="index-custom-btn btn-secondary px-4 py-2 rounded-lg font-medium">
                        No, Go Back
                    </button>
                    <button type="button" onclick="submitCancelForm()" 
                            class="index-custom-btn px-4 py-2 rounded-lg font-medium text-white" style="background-color: var(--danger);">
                        <i class="fas fa-check mr-2"></i> Yes, Cancel Transfer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Configuration
const digitalSignatureThreshold = {{ $digitalSignatureThreshold }};
const digitalSignatureEnabled = {{ $digitalSignatureEnabled ? 'true' : 'false' }};
const hasTenants = {{ $hasTenants ? 'true' : 'false' }};
const currentPropertyId = {{ $property->id }};
const initialDocRef = '{{ $initialDocRef }}';
let selectedOwnerType = 'existing'; // 'existing' or 'new'

document.addEventListener('DOMContentLoaded', function() {
    // Initialize multi-step form
    initMultiStepForm();
    
    // File upload handling
    initFileUpload();
    
    // Phone validation
    initPhoneValidation();
    
    // Digital signature toggle
    initDigitalSignature();
    
    // Bulk transfer functionality
    initBulkTransfer();
    
    // Form validation
    initFormValidation();
    
    // Sale amount listener for digital signature
    initSaleAmountListener();
    
    // Initialize owner type selector
    initOwnerTypeSelector();
    
    // Set global current step variable
    window.currentStep = 'owner_details';
});

// Owner Type Selection Functions
function initOwnerTypeSelector() {
    const existingLandlordSelect = document.getElementById('existing_landlord_id');
    
    if (existingLandlordSelect) {
        existingLandlordSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const name = selectedOption.getAttribute('data-name');
                const phone = selectedOption.getAttribute('data-phone');
                const email = selectedOption.getAttribute('data-email');
                const address = selectedOption.getAttribute('data-address');
                
                // Populate hidden fields for submission
                document.getElementById('new_owner_name').value = name;
                document.getElementById('new_owner_phone').value = phone;
                document.getElementById('new_owner_email').value = email || '';
                document.getElementById('new_owner_address').value = address || '';
                
                // Update review section
                updateReviewSection();
            }
        });
    }
}

function selectOwnerType(type) {
    selectedOwnerType = type;
    
    const existingBtn = document.getElementById('selectExistingBtn');
    const newBtn = document.getElementById('enterNewBtn');
    const existingSection = document.getElementById('existingLandlordSection');
    const newSection = document.getElementById('newOwnerDetailsSection');
    
    if (type === 'existing') {
        existingBtn.classList.add('active');
        newBtn.classList.remove('active');
        existingSection.classList.remove('hidden');
        newSection.classList.add('hidden');
        
        // Make new owner fields not required when using existing landlord
        document.getElementById('new_owner_name').removeAttribute('required');
        document.getElementById('new_owner_phone').removeAttribute('required');
        
        // Trigger change on select to populate fields
        const select = document.getElementById('existing_landlord_id');
        if (select) {
            const event = new Event('change');
            select.dispatchEvent(event);
        }
    } else {
        existingBtn.classList.remove('active');
        newBtn.classList.add('active');
        existingSection.classList.add('hidden');
        newSection.classList.remove('hidden');
        
        // Make new owner fields required
        document.getElementById('new_owner_name').setAttribute('required', 'required');
        document.getElementById('new_owner_phone').setAttribute('required', 'required');
        
        // Clear existing selection
        document.getElementById('existing_landlord_id').value = '';
        
        // Clear fields
        document.getElementById('new_owner_name').value = '';
        document.getElementById('new_owner_phone').value = '';
        document.getElementById('new_owner_email').value = '';
        document.getElementById('new_owner_address').value = '';
    }
    
    // Update review section
    updateReviewSection();
}

// Multi-step form functionality
function initMultiStepForm() {
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const stepSections = document.querySelectorAll('.step-section');
    const stepProgress = document.querySelector('.step-progress');
    const stepLabels = document.querySelectorAll('.step-label');
    
    window.nextStep = function(nextStepName) {
        if (!validateCurrentStep(window.currentStep)) {
            return;
        }
        
        const currentSection = document.querySelector(`[data-section="${window.currentStep}"]`);
        if (currentSection) {
            currentSection.style.display = 'none';
            currentSection.classList.remove('active-section');
        }
        
        const nextSection = document.querySelector(`[data-section="${nextStepName}"]`);
        if (nextSection) {
            nextSection.style.display = 'block';
            nextSection.classList.add('active-section');
        }
        
        stepIndicators.forEach(indicator => {
            const step = indicator.getAttribute('data-step');
            indicator.classList.remove('step-active');
            if (step === nextStepName) {
                indicator.classList.add('step-active');
            }
        });
        
        const steps = ['owner_details', 'transfer_info', 'review'];
        const currentIndex = steps.indexOf(window.currentStep);
        const nextIndex = steps.indexOf(nextStepName);
        const progress = (nextIndex / (steps.length - 1)) * 100;
        if (stepProgress) {
            stepProgress.style.width = `${progress}%`;
        }
        
        stepLabels.forEach((label, index) => {
            if (index <= nextIndex) {
                label.style.color = 'var(--text-primary)';
            } else {
                label.style.color = 'var(--text-secondary)';
            }
        });
        
        if (nextStepName === 'review') {
            updateReviewSection();
        }
        
        setTimeout(() => {
            nextSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
        
        window.currentStep = nextStepName;
    };
    
    window.prevStep = function(prevStepName) {
        const currentSection = document.querySelector(`[data-section="${window.currentStep}"]`);
        if (currentSection) {
            currentSection.style.display = 'none';
            currentSection.classList.remove('active-section');
        }
        
        const prevSection = document.querySelector(`[data-section="${prevStepName}"]`);
        if (prevSection) {
            prevSection.style.display = 'block';
            prevSection.classList.add('active-section');
        }
        
        stepIndicators.forEach(indicator => {
            const step = indicator.getAttribute('data-step');
            indicator.classList.remove('step-active');
            if (step === prevStepName) {
                indicator.classList.add('step-active');
            }
        });
        
        const steps = ['owner_details', 'transfer_info', 'review'];
        const currentIndex = steps.indexOf(window.currentStep);
        const prevIndex = steps.indexOf(prevStepName);
        const progress = (prevIndex / (steps.length - 1)) * 100;
        if (stepProgress) {
            stepProgress.style.width = `${progress}%`;
        }
        
        stepLabels.forEach((label, index) => {
            if (index <= prevIndex) {
                label.style.color = 'var(--text-primary)';
            } else {
                label.style.color = 'var(--text-secondary)';
            }
        });
        
        setTimeout(() => {
            prevSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
        
        window.currentStep = prevStepName;
    };
}

function validateCurrentStep(step) {
    if (step === 'owner_details') {
        if (selectedOwnerType === 'new') {
            const nameInput = document.getElementById('new_owner_name');
            const phoneInput = document.getElementById('new_owner_phone');
            
            const name = nameInput?.value.trim();
            const phone = phoneInput?.value.trim();
            
            let errors = [];
            
            if (!name) {
                errors.push('Please enter the new owner\'s name');
                nameInput?.classList.add('border-red-500');
            } else {
                nameInput?.classList.remove('border-red-500');
            }
            
            if (!phone) {
                errors.push('Please enter the new owner\'s phone number');
                phoneInput?.classList.add('border-red-500');
            } else if (!validatePhoneNumber(phone)) {
                errors.push('Please enter a valid phone number');
                phoneInput?.classList.add('border-red-500');
            } else {
                phoneInput?.classList.remove('border-red-500');
            }
            
            if (errors.length > 0) {
                showError(errors.join('<br>'));
                return false;
            }
        } else if (selectedOwnerType === 'existing') {
            const select = document.getElementById('existing_landlord_id');
            if (!select?.value) {
                showError('Please select an existing landlord');
                select?.classList.add('border-red-500');
                return false;
            } else {
                select?.classList.remove('border-red-500');
            }
        }
    }
    
    // Validate transfer info step if needed
    if (step === 'transfer_info') {
        const documentType = document.getElementById('document_type')?.value;
        const documentReference = document.getElementById('document_reference')?.value;
        const transferDocument = document.getElementById('transfer_document')?.files[0];
        
        if (!documentType) {
            showError('Please select a document type');
            return false;
        }
        
        if (!documentReference) {
            showError('Please enter a document reference');
            return false;
        }
        
        if (!transferDocument) {
            showError('Please upload a transfer document');
            return false;
        }
    }
    
    return true;
}

function validatePhoneNumber(phone) {
    const cleaned = phone.trim();
    const plus233Regex = /^\+\d{1,4}\d{6,}$/;
    const localFormatRegex = /^0\d{9,}$/;
    const countryCodeNoPlusRegex = /^233\d{9,}$/;
    
    return plus233Regex.test(cleaned) || localFormatRegex.test(cleaned) || countryCodeNoPlusRegex.test(cleaned);
}

// File upload handling
function initFileUpload() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('transfer_document');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const fileRequirements = document.getElementById('fileRequirements');
    
    if (!dropzone || !fileInput) return;
    
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
        dropzone.style.borderColor = 'var(--primary)';
        dropzone.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
    }
    
    function unhighlight() {
        dropzone.style.borderColor = 'var(--border-color)';
        dropzone.style.backgroundColor = '';
    }
    
    dropzone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }
    
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            const maxSize = {{ $maxFileSize }};
            
            const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                showError('Please upload only PDF or image files (JPG, PNG)');
                return;
            }
            
            if (file.size > maxSize) {
                showError(`File size must be less than ${maxSize / (1024 * 1024)}MB`);
                return;
            }
            
            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = formatFileSize(file.size);
            
            if (fileIcon) {
                if (file.type === 'application/pdf') {
                    fileIcon.className = 'fas fa-file-pdf mr-2 text-lg';
                    fileIcon.style.color = 'var(--danger)';
                } else if (file.type.includes('image')) {
                    fileIcon.className = 'fas fa-file-image mr-2 text-lg';
                    fileIcon.style.color = 'var(--info)';
                }
            }
            
            if (filePreview) filePreview.style.display = 'block';
            if (fileRequirements) fileRequirements.style.display = 'none';
        }
    }
    
    window.clearFile = function() {
        if (fileInput) fileInput.value = '';
        if (filePreview) filePreview.style.display = 'none';
        if (fileRequirements) fileRequirements.style.display = 'block';
    };
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Phone validation
function initPhoneValidation() {
    const phoneInput = document.getElementById('new_owner_phone');
    const phoneValidation = document.getElementById('phone-validation');
    const phoneValidIcon = document.getElementById('phone-valid-icon');
    const phoneInvalidIcon = document.getElementById('phone-invalid-icon');
    
    if (!phoneInput) return;
    
    function updatePhoneValidation() {
        const phone = phoneInput.value.trim();
        
        if (phone === '') {
            if (phoneValidation) phoneValidation.classList.add('hidden');
            phoneInput.classList.remove('border-green-500', 'border-red-500');
            phoneInput.style.borderColor = 'var(--border-color)';
            return;
        }
        
        if (phoneValidation) phoneValidation.classList.remove('hidden');
        
        if (validatePhoneNumber(phone)) {
            if (phoneValidIcon) phoneValidIcon.classList.remove('hidden');
            if (phoneInvalidIcon) phoneInvalidIcon.classList.add('hidden');
            phoneInput.classList.remove('border-red-500');
            phoneInput.classList.add('border-green-500');
            phoneInput.style.borderColor = 'var(--success)';
        } else {
            if (phoneValidIcon) phoneValidIcon.classList.add('hidden');
            if (phoneInvalidIcon) phoneInvalidIcon.classList.remove('hidden');
            phoneInput.classList.remove('border-green-500');
            phoneInput.classList.add('border-red-500');
            phoneInput.style.borderColor = 'var(--danger)';
        }
    }
    
    phoneInput.addEventListener('input', updatePhoneValidation);
    phoneInput.addEventListener('blur', updatePhoneValidation);
    updatePhoneValidation();
}

// Digital signature - FIXED to show section properly
function initDigitalSignature() {
    const saleAmount = document.getElementById('sale_amount');
    const signatureSection = document.getElementById('digitalSignatureSection');
    const signatureCheckbox = document.getElementById('apply_digital_signature');
    const signatureFields = document.getElementById('digitalSignatureFields');
    
    function checkDigitalSignatureRequirement() {
        if (!digitalSignatureEnabled || !signatureSection) return;
        
        const amount = parseFloat(saleAmount?.value || 0);
        
        // Always show the section, but highlight requirement
        signatureSection.style.display = 'block';
        
        // If amount exceeds threshold, show warning and auto-check?
        if (digitalSignatureThreshold > 0 && amount >= digitalSignatureThreshold) {
            // Add a visual indicator that signature is required
            const warningSpan = signatureSection.querySelector('.text-xs');
            if (warningSpan) {
                warningSpan.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
                warningSpan.style.color = 'var(--danger)';
            }
        } else {
            const warningSpan = signatureSection.querySelector('.text-xs');
            if (warningSpan) {
                warningSpan.style.backgroundColor = '';
                warningSpan.style.color = 'var(--warning)';
            }
        }
    }
    
    // Check on sale amount change
    if (saleAmount) {
        saleAmount.addEventListener('input', checkDigitalSignatureRequirement);
        checkDigitalSignatureRequirement();
    }
    
    // Toggle signature fields when checkbox is checked
    if (signatureCheckbox) {
        signatureCheckbox.addEventListener('change', function() {
            if (this.checked) {
                signatureFields.classList.remove('hidden');
            } else {
                signatureFields.classList.add('hidden');
            }
        });
    }
    
    // Initial check
    checkDigitalSignatureRequirement();
}

function initSaleAmountListener() {
    const saleAmount = document.getElementById('sale_amount');
    if (saleAmount) {
        saleAmount.addEventListener('change', updateReviewSection);
        saleAmount.addEventListener('input', updateReviewSection);
    }
}

// Bulk transfer functionality
function initBulkTransfer() {
    const propertyCheckboxes = document.querySelectorAll('.property-checkbox');
    const selectedCountElement = document.getElementById('selectedPropertiesCount');
    const selectedPluralElement = document.getElementById('selectedPropertiesPlural');
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.property-checkbox:checked');
        const totalCount = 1 + checkedBoxes.length;
        
        if (selectedCountElement) {
            selectedCountElement.textContent = totalCount;
        }
        if (selectedPluralElement) {
            selectedPluralElement.textContent = totalCount === 1 ? 'y' : 'ies';
        }
    }
    
    propertyCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    updateSelectedCount();
    
    window.showBulkTransferModal = function() {
        const modal = document.getElementById('bulkTransferModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };
    
    window.hideBulkTransferModal = function() {
        const modal = document.getElementById('bulkTransferModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    };
    
    window.hideBulkTransferNotice = function() {
        const notice = document.getElementById('bulkTransferNotice');
        if (notice) {
            notice.style.display = 'none';
        }
    };
    
    window.confirmBulkSelection = function() {
        const checkedBoxes = document.querySelectorAll('.property-checkbox:checked');
        const propertyIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (propertyIds.length > 0) {
            const bulkFlag = document.getElementById('bulkTransferFlag');
            const form = document.querySelector('form');
            
            if (bulkFlag) bulkFlag.value = 'true';
            if (form) form.setAttribute('data-is-bulk', 'true');
            
            const singlePropertyId = document.getElementById('singlePropertyId');
            if (singlePropertyId) {
                singlePropertyId.remove();
            }
            
            const existingContainer = document.getElementById('propertyIdsContainer');
            if (existingContainer) {
                existingContainer.remove();
            }
            
            const propertyIdsInput = document.createElement('div');
            propertyIdsInput.id = 'propertyIdsContainer';
            
            const allPropertyIds = [currentPropertyId, ...propertyIds];
            
            allPropertyIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'property_ids[]';
                input.value = id;
                propertyIdsInput.appendChild(input);
            });
            
            const formElement = document.getElementById('ownershipTransferForm');
            if (formElement) {
                formElement.appendChild(propertyIdsInput);
            }
            
            updateSelectedPropertiesCard(checkedBoxes);
            
            const bulkNotesSection = document.getElementById('bulkNotesSection');
            if (bulkNotesSection) {
                bulkNotesSection.classList.remove('hidden');
            }
            
            const submitButton = document.getElementById('submitButton');
            if (submitButton) {
                submitButton.innerHTML = 
                    `<i class="fas fa-paper-plane mr-2"></i> Submit Bulk Transfer (${allPropertyIds.length} properties)`;
            }
        }
        
        hideBulkTransferModal();
    };
}

function updateSelectedPropertiesCard(checkedBoxes) {
    const selectedCard = document.getElementById('selectedPropertiesCard');
    const selectedList = document.getElementById('selectedPropertiesList');
    const selectedCount = document.getElementById('selectedCount');
    
    if (!selectedList || !selectedCard || !selectedCount) return;
    
    selectedList.innerHTML = '';
    const properties = [];
    
    properties.push({
        name: '{{ addslashes($property->property_name) }}',
        registration: '{{ addslashes($property->registration_pattern) }}',
        address: '{{ addslashes($property->street_name) }}, {{ addslashes($property->zone) }}',
        isCurrent: true
    });
    
    checkedBoxes.forEach(checkbox => {
        const property = {
            name: checkbox.getAttribute('data-property-name'),
            registration: checkbox.getAttribute('data-property-registration'),
            address: checkbox.getAttribute('data-property-address'),
            isCurrent: false
        };
        properties.push(property);
    });
    
    properties.forEach(property => {
        const div = document.createElement('div');
        div.className = 'flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800';
        div.innerHTML = `
            <div class="flex-shrink-0 mr-2">
                <i class="fas fa-building" style="color: ${property.isCurrent ? 'var(--primary)' : 'var(--text-secondary)'};"></i>
            </div>
            <div class="flex-1">
                <div class="text-sm font-medium" style="color: var(--text-primary);">
                    ${escapeHtml(property.name)}
                    ${property.isCurrent ? '<span class="text-xs ml-2 px-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">Current</span>' : ''}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">${escapeHtml(property.registration)}</div>
            </div>
        `;
        selectedList.appendChild(div);
    });
    
    selectedCount.textContent = properties.length;
    selectedCard.classList.remove('hidden');
}

// Form validation
function initFormValidation() {
    const form = document.getElementById('ownershipTransferForm');
    
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const isBulk = document.getElementById('bulkTransferFlag')?.value === 'true';
        
        if (isBulk) {
            const propertyIds = document.querySelectorAll('input[name="property_ids[]"]');
            if (propertyIds.length === 0) {
                e.preventDefault();
                showError('Please select at least one property for bulk transfer');
                return;
            }
        }
        
        if (hasTenants) {
            const tenantConfirm = document.getElementById('confirm_tenant_transfer');
            if (!tenantConfirm?.checked) {
                e.preventDefault();
                showError('Please confirm that you understand tenants will be transferred to the new landlord');
                if (tenantConfirm) {
                    tenantConfirm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }
        }
        
        // Validate based on owner type selection
        if (selectedOwnerType === 'existing') {
            const select = document.getElementById('existing_landlord_id');
            if (!select?.value) {
                e.preventDefault();
                showError('Please select an existing landlord');
                select?.classList.add('border-red-500');
                return;
            }
        } else {
            const nameInput = document.getElementById('new_owner_name');
            const phoneInput = document.getElementById('new_owner_phone');
            
            if (!nameInput?.value.trim()) {
                e.preventDefault();
                showError('Please enter the new owner\'s name');
                nameInput?.classList.add('border-red-500');
                return;
            }
            
            if (!phoneInput?.value.trim()) {
                e.preventDefault();
                showError('Please enter the new owner\'s phone number');
                phoneInput?.classList.add('border-red-500');
                return;
            }
            
            if (!validatePhoneNumber(phoneInput.value)) {
                e.preventDefault();
                showError('Please enter a valid phone number');
                phoneInput?.classList.add('border-red-500');
                return;
            }
        }
        
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim() && field.type !== 'checkbox') {
                field.style.borderColor = 'var(--danger)';
                isValid = false;
            } else if (field.type === 'checkbox' && !field.checked && field.id !== 'confirm_tenant_transfer') {
                field.style.outline = '2px solid var(--danger)';
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showError('Please fill in all required fields');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        const termsCheckbox = document.getElementById('terms');
        if (!termsCheckbox?.checked) {
            e.preventDefault();
            showError('You must accept the terms and conditions');
            if (termsCheckbox) {
                window.scrollTo({ 
                    top: termsCheckbox.offsetTop - 100, 
                    behavior: 'smooth' 
                });
            }
        }
        
        const fileInput = document.getElementById('transfer_document');
        if (!fileInput?.files || fileInput.files.length === 0) {
            e.preventDefault();
            showError('Please upload a transfer document');
            return;
        }
        
        // For existing landlord selection, ensure the hidden fields are populated
        if (selectedOwnerType === 'existing') {
            const select = document.getElementById('existing_landlord_id');
            const selectedOption = select.options[select.selectedIndex];
            
            // Create hidden inputs for the selected landlord data
            const existingNameInput = document.createElement('input');
            existingNameInput.type = 'hidden';
            existingNameInput.name = 'new_owner_name';
            existingNameInput.value = selectedOption.getAttribute('data-name');
            form.appendChild(existingNameInput);
            
            const existingPhoneInput = document.createElement('input');
            existingPhoneInput.type = 'hidden';
            existingPhoneInput.name = 'new_owner_phone';
            existingPhoneInput.value = selectedOption.getAttribute('data-phone');
            form.appendChild(existingPhoneInput);
            
            const existingEmailInput = document.createElement('input');
            existingEmailInput.type = 'hidden';
            existingEmailInput.name = 'new_owner_email';
            existingEmailInput.value = selectedOption.getAttribute('data-email') || '';
            form.appendChild(existingEmailInput);
            
            const existingAddressInput = document.createElement('input');
            existingAddressInput.type = 'hidden';
            existingAddressInput.name = 'new_owner_address';
            existingAddressInput.value = selectedOption.getAttribute('data-address') || '';
            form.appendChild(existingAddressInput);
        }
    });
}

// Document reference generation
window.generateDocumentReference = function() {
    const date = new Date().toISOString().split('T')[0].replace(/-/g, '');
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    const docRefInput = document.getElementById('document_reference');
    if (docRefInput) {
        docRefInput.value = `TRANS-${date}-${random}`;
        updateReviewSection();
    }
};

// Update review section
function updateReviewSection() {
    let ownerName = '';
    let phone = '';
    
    if (selectedOwnerType === 'existing') {
        const select = document.getElementById('existing_landlord_id');
        const selectedOption = select?.options[select.selectedIndex];
        if (selectedOption && select.value) {
            ownerName = selectedOption.getAttribute('data-name') || 'Not selected';
            phone = selectedOption.getAttribute('data-phone') || 'Not selected';
        } else {
            ownerName = 'Please select an existing landlord';
            phone = 'Not selected';
        }
    } else {
        const nameInput = document.getElementById('new_owner_name');
        const phoneInput = document.getElementById('new_owner_phone');
        ownerName = nameInput?.value || 'Not provided';
        phone = phoneInput?.value || 'Not provided';
    }
    
    const transferDateInput = document.getElementById('transfer_date');
    const docTypeSelect = document.getElementById('document_type');
    const saleAmountInput = document.getElementById('sale_amount');
    const docRefInput = document.getElementById('document_reference');
    
    const transferDate = transferDateInput?.value || 'Not set';
    const docType = docTypeSelect?.options[docTypeSelect.selectedIndex]?.text || 'Not specified';
    const saleAmount = saleAmountInput?.value ? `GHS ${parseFloat(saleAmountInput.value).toLocaleString(undefined, {minimumFractionDigits: 2})}` : 'Not specified';
    const docRef = docRefInput?.value || 'Not generated';
    
    let propertyCount = 1;
    const isBulk = document.getElementById('bulkTransferFlag')?.value === 'true';
    
    if (isBulk) {
        const propertyIdsContainer = document.getElementById('propertyIdsContainer');
        if (propertyIdsContainer) {
            propertyCount = propertyIdsContainer.querySelectorAll('input[name="property_ids[]"]').length;
        }
    }
    
    const reviewOwnerName = document.getElementById('reviewOwnerName');
    const reviewPhone = document.getElementById('reviewPhone');
    const reviewDate = document.getElementById('reviewDate');
    const reviewDocType = document.getElementById('reviewDocType');
    const reviewAmount = document.getElementById('reviewAmount');
    const reviewDocRef = document.getElementById('reviewDocRef');
    const reviewProperties = document.getElementById('reviewProperties');
    
    if (reviewOwnerName) reviewOwnerName.textContent = ownerName;
    if (reviewPhone) reviewPhone.textContent = phone;
    if (reviewDate) reviewDate.textContent = transferDate;
    if (reviewDocType) reviewDocType.textContent = docType;
    if (reviewAmount) reviewAmount.textContent = saleAmount;
    if (reviewDocRef) reviewDocRef.textContent = docRef;
    if (reviewProperties) reviewProperties.textContent = `${propertyCount} propert${propertyCount === 1 ? 'y' : 'ies'}${isBulk ? ' (Bulk Transfer)' : ''}`;
}

// Preview functionality
window.previewTransfer = function() {
    const form = document.getElementById('ownershipTransferForm');
    const previewContent = document.getElementById('previewContent');
    const documentTypes = @json($documentTypes);
    
    if (!form || !previewContent) return;
    
    let ownerName = '';
    let phone = '';
    let email = '';
    let address = '';
    
    if (selectedOwnerType === 'existing') {
        const select = document.getElementById('existing_landlord_id');
        const selectedOption = select?.options[select.selectedIndex];
        if (selectedOption && select.value) {
            ownerName = selectedOption.getAttribute('data-name') || 'Not selected';
            phone = selectedOption.getAttribute('data-phone') || 'Not selected';
            email = selectedOption.getAttribute('data-email') || 'Not provided';
            address = selectedOption.getAttribute('data-address') || 'Not provided';
        }
    } else {
        ownerName = document.getElementById('new_owner_name')?.value || 'Not provided';
        phone = document.getElementById('new_owner_phone')?.value || 'Not provided';
        email = document.getElementById('new_owner_email')?.value || 'Not provided';
        address = document.getElementById('new_owner_address')?.value || 'Not provided';
    }
    
    let propertyCount = 1;
    const isBulk = document.getElementById('bulkTransferFlag')?.value === 'true';
    if (isBulk) {
        const propertyIdsContainer = document.getElementById('propertyIdsContainer');
        if (propertyIdsContainer) {
            propertyCount = propertyIdsContainer.querySelectorAll('input[name="property_ids[]"]').length;
        }
    }
    
    let html = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                <h4 class="font-medium mb-3" style="color: var(--text-primary);">Transfer Details</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Type:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${isBulk ? 'Bulk Transfer' : 'Single Property'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Properties:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${propertyCount}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Transfer Date:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${document.getElementById('transfer_date')?.value || 'Not set'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Document Type:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${documentTypes[document.getElementById('document_type')?.value] || document.getElementById('document_type')?.value || 'Not specified'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Document Reference:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${escapeHtml(document.getElementById('document_reference')?.value || initialDocRef)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Sale Amount:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${document.getElementById('sale_amount')?.value ? `GHS ${parseFloat(document.getElementById('sale_amount').value).toLocaleString(undefined, {minimumFractionDigits: 2})}` : 'Not specified'}</span>
                    </div>
                </div>
            </div>
            
            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                <h4 class="font-medium mb-3" style="color: var(--text-primary);">New Owner Information</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Name:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${escapeHtml(ownerName)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Phone:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${escapeHtml(phone)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Email:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${escapeHtml(email)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Address:</span>
                        <span class="font-medium" style="color: var(--text-primary);">${escapeHtml(address)}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    if (hasTenants) {
        html += `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <div class="flex items-start">
                    <i class="fas fa-users mr-3 mt-0.5" style="color: var(--warning);"></i>
                    <div>
                        <h4 class="font-medium" style="color: var(--text-primary);">Tenant Transfer Notice</h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            This property has active tenants that will be automatically transferred to the new landlord.
                            Their tenancy agreements remain valid.
                        </p>
                    </div>
                </div>
            </div>
        `;
    }
    
    if (isBulk && document.getElementById('bulk_notes')?.value) {
        html += `
            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                <h4 class="font-medium mb-2" style="color: var(--text-primary);">Bulk Transfer Notes</h4>
                <p class="text-sm" style="color: var(--text-secondary);">${escapeHtml(document.getElementById('bulk_notes').value)}</p>
            </div>
        `;
    }
    
    if (document.getElementById('reason_for_transfer')?.value) {
        html += `
            <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                <h4 class="font-medium mb-2" style="color: var(--text-primary);">Reason for Transfer</h4>
                <p class="text-sm" style="color: var(--text-secondary);">${escapeHtml(document.getElementById('reason_for_transfer').value)}</p>
            </div>
        `;
    }
    
    previewContent.innerHTML = html;
    showPreview();
};

window.showPreview = function() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
};

window.hidePreview = function() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
};

window.submitForm = function() {
    const form = document.getElementById('ownershipTransferForm');
    if (form) {
        hidePreview();
        form.submit();
    }
};

// Cancel transfer functionality
window.confirmCancelTransfer = function(transferId) {
    const modal = document.getElementById('cancelTransferModal');
    const form = document.getElementById('cancelTransferForm');
    
    if (modal && form) {
        form.action = `{{ url('properties') }}/${currentPropertyId}/ownership-transfers/${transferId}`;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
};

window.hideCancelModal = function() {
    const modal = document.getElementById('cancelTransferModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
};

window.submitCancelForm = function() {
    const form = document.getElementById('cancelTransferForm');
    if (form) {
        form.submit();
    }
};

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Error notification
function showError(message) {
    const existingErrors = document.querySelectorAll('.error-message');
    existingErrors.forEach(error => error.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'error-message fixed top-4 right-4 z-50 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg';
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span>${message}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}
</script>

<style>
/* Multi-step form styling */
.step-indicator {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
}

.step-indicator.step-active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.step-connector {
    background-color: var(--border-color);
}

.step-progress {
    background-color: var(--primary);
    transition: width 0.3s ease;
}

.step-section {
    display: none;
}

.step-section.active-section {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Owner Type Selector Styling */
.owner-type-btn {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.owner-type-btn.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.owner-type-btn:first-child {
    border-radius: 8px 0 0 8px;
}

.owner-type-btn:last-child {
    border-radius: 0 8px 8px 0;
}

/* Selected properties styling */
#selectedPropertiesList {
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) transparent;
}

#selectedPropertiesList::-webkit-scrollbar {
    width: 6px;
}

#selectedPropertiesList::-webkit-scrollbar-track {
    background: transparent;
}

#selectedPropertiesList::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 3px;
}

/* Property selection checkbox */
.property-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Modal styling */
.modal-container {
    background: var(--card-bg);
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
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 10;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
    z-index: 10;
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.modal-close-btn:hover {
    background-color: rgba(var(--text-secondary-rgb), 0.1);
}

/* Form validation styling */
input:invalid, select:invalid, textarea:invalid {
    border-color: var(--danger) !important;
}

input:valid, select:valid, textarea:valid {
    border-color: var(--border-color) !important;
}

/* File upload dropzone */
#dropzone.drag-over {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

/* Error message styling */
.error-message {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Property item hover */
.property-item:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
    cursor: pointer;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .step-indicator {
        width: 32px;
        height: 32px;
        min-width: 32px;
        font-size: 14px;
    }
    
    .step-label {
        font-size: 11px;
    }
    
    .modal-container {
        margin: 1rem;
        max-height: calc(100vh - 2rem);
    }
    
    .owner-type-btn {
        font-size: 12px;
        padding: 8px 12px;
    }
}
</style>
@endsection