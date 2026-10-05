@extends('layouts.app')

@section('title', 'Edit Property')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability
     |--------------------------------------------------------------------------
     | Priority for each channel:
     |   1. The status array passed by the controller (preferred).
     |   2. A live service call, if the variable wasn't passed.
     |   3. A per-service fallback key (e.g. `configured`) when
     |      `system_ready` is missing or null.
     |
     | NOTE: the codebase has THREE different key conventions:
     |   - SmsService::getSystemStatus()                 → `system_ready`
     |   - WhatsAppService::getSystemStatus()            → `system_ready`
     |   - SystemSetting::getEmailConfigurationStatus()  → `configured`
     */

    // ---------- SMS ----------
    if (!isset($smsStatus) || !is_array($smsStatus)) {
        try {
            $smsStatus = app(\App\Services\SmsService::class)->getSystemStatus();
        } catch (\Throwable $e) {
            $smsStatus = ['system_ready' => false];
        }
    }
    $smsReady = (bool) (
        $smsStatus['system_ready']
        ?? $smsStatus['can_send_sms']
        ?? $smsStatus['ready']
        ?? false
    );

    // ---------- WhatsApp ----------
    if (!isset($whatsappStatus) || !is_array($whatsappStatus)) {
        try {
            $whatsappStatus = app(\App\Services\WhatsAppService::class)->getSystemStatus();
        } catch (\Throwable $e) {
            $whatsappStatus = ['system_ready' => false];
        }
    }
    $whatsappReady = (bool) (
        $whatsappStatus['system_ready']
        ?? $whatsappStatus['can_send']
        ?? $whatsappStatus['configured']
        ?? false
    );

    // ---------- Email ----------
    if (!isset($emailStatus) || !is_array($emailStatus)) {
        try {
            $emailStatus = \App\Models\SystemSetting::getSettings()
                ->getEmailConfigurationStatus();
            if (!is_array($emailStatus)) {
                $emailStatus = [];
            }
        } catch (\Throwable $e) {
            $emailStatus = [];
        }
    }

    $emailReady = (bool) (
        $emailStatus['system_ready']
        ?? $emailStatus['can_send']
        ?? $emailStatus['configured']
        ?? false
    );

    // Normalize so downstream markup / JS always sees a consistent shape.
    $emailStatus['system_ready'] = $emailReady;
    $emailStatus['can_send']     = $emailReady;
    if (!isset($emailStatus['health'])) {
        $emailStatus['health'] = $emailReady ? 'healthy' : 'degraded';
    }
    if (!isset($emailStatus['message'])) {
        $emailStatus['message'] = $emailReady
            ? 'Email service is operational'
            : 'Email service is not configured or not ready';
    }
    if (!isset($emailStatus['provider'])) {
        $emailStatus['provider'] = $emailStatus['system_email'] ?? 'N/A';
    }

    // ---------- Rollup ----------
    $anyChannelReady = $smsReady || $whatsappReady || $emailReady;

    $oldInvitationChannels = old('invitation_channels', []);
    if (!is_array($oldInvitationChannels)) {
        $oldInvitationChannels = [];
    }
    $oldInvitationChannels = array_values(array_filter(
        $oldInvitationChannels,
        function ($c) use ($smsReady, $whatsappReady, $emailReady) {
            if ($c === 'sms')      return $smsReady;
            if ($c === 'whatsapp') return $whatsappReady;
            if ($c === 'email')    return $emailReady;
            return false;
        }
    ));

    // Pre-compute session-driven banners so we don't nest @php inside HTML.
    $landlordInvitationResult = session('invitation_result');
    $tenantInvitationResult   = session('tenant_invitation_result');
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                Edit Property: {{ $property->property_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('properties.show', $property->id) }}" class="btn-secondary flex items-center">
                    <i class="fas fa-eye mr-2"></i> View Property
                </a>
                <a href="{{ route('properties.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Properties
                </a>
            </div>
        </div>
    </div>

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

    <!-- Invitation Result Message -->
    @if($landlordInvitationResult)
        @if($landlordInvitationResult['success'])
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Invitation Sent!</strong>
            <span class="block sm:inline">
                Landlord invitation sent successfully via {{ implode(', ', $landlordInvitationResult['channels_successful']) }}.
                {{ $landlordInvitationResult['success_count'] }} of {{ $landlordInvitationResult['total_channels'] }} channel(s) successful.
            </span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @else
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Invitation Notice</strong>
            <span class="block sm:inline">{{ $landlordInvitationResult['message'] }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif
    @endif

    <!-- Tenant Invitation Result Message -->
    @if($tenantInvitationResult)
        @if($tenantInvitationResult['success'])
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Tenant Invitations Sent!</strong>
            <span class="block sm:inline">
                {{ $tenantInvitationResult['success_count'] }} tenant(s) invited successfully via {{ implode(', ', $tenantInvitationResult['channels_successful'] ?? []) }}.
            </span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @else
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Tenant Invitation Notice</strong>
            <span class="block sm:inline">{{ $tenantInvitationResult['message'] }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif
    @endif

    <!-- Form Card -->
    <div class="card p-6">
        <form action="{{ route('properties.update', $property->id) }}" method="POST" id="property-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column - Property Identification -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Property Identification</h3>

                    <!-- Registration Plan Selection -->
                    <div>
                        <label for="registration_plan_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Registration Plan *</label>
                        <select name="registration_plan_id" id="registration_plan_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Registration Plan</option>
                            @foreach($registrationPlans as $plan)
                                <option value="{{ $plan->id }}"
                                        data-pattern="{{ $plan->naming_pattern }}"
                                        data-starting-point="{{ $plan->starting_point }}"
                                        data-sequence-type="{{ $plan->sequence_type }}"
                                        data-next-available="{{ $plan->next_available_name ?? $plan->starting_point }}"
                                        data-zone="{{ $plan->zone }}"
                                        data-section="{{ $plan->section }}"
                                        data-estimated-houses="{{ $plan->estimated_houses }}"
                                        data-registered-houses="{{ $plan->houses_registered ?? 0 }}"
                                        data-plan-status="{{ $plan->status }}"
                                        data-is-global-sequence="{{ $plan->is_global_sequence ? '1' : '0' }}"
                                        data-continues-from-plan-id="{{ $plan->continues_from_plan_id }}"
                                        {{ old('registration_plan_id', $property->registration_plan_id) == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->zone }} - {{ $plan->section }}
                                    ({{ $plan->naming_pattern }})
                                    @if($plan->next_available_name)
                                        - Next: {{ $plan->next_available_name }}
                                    @endif
                                    @if($plan->is_global_sequence)
                                        🌍
                                    @endif
                                    - {{ ucfirst($plan->status) }}
                                </option>
                            @endforeach
                        </select>

                        @if($registrationPlans->isEmpty())
                            <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded">
                                <p class="text-yellow-700 text-sm">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    No active registration plans available.
                                    <a href="{{ route('registration-plans.create') }}" class="underline">Create a registration plan first</a>.
                                </p>
                            </div>
                        @else
                            <p class="text-xs text-gray-500 mt-1">
                                Select the geographic plan this property belongs to
                            </p>
                        @endif

                        @error('registration_plan_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Enhanced Property Type Selection -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Property Type *</label>

                        <!-- Property Type Cards Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3" id="property-type-cards">
                            @foreach($propertyTypes as $type)
                                @if($type->slug !== 'custom')
                                <div class="property-type-card border-2 rounded-lg p-3 cursor-pointer transition-all duration-200 hover:shadow-md"
                                     data-type-id="{{ $type->id }}"
                                     data-type-name="{{ $type->name }}"
                                     data-type-description="{{ $type->description }}"
                                     data-type-icon="{{ $type->icon }}"
                                     data-type-color="{{ $type->color }}"
                                     data-is-custom="false"
                                     style="border-color: var(--border-color); background-color: var(--bg-secondary);"
                                     onclick="selectPropertyType(this, {{ $type->id }}, '{{ $type->slug }}')">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: {{ $type->color }}20; border: 1px solid {{ $type->color }}40;">
                                            <i class="{{ $type->icon }} text-sm" style="color: {{ $type->color }};"></i>
                                        </div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $type->name }}</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $type->description }}</p>
                                </div>
                                @endif
                            @endforeach

                            <!-- Custom Property Type Card -->
                            @php $customType = $propertyTypes->where('slug', 'custom')->first(); @endphp
                            @if($customType)
                            <div class="property-type-card border-2 rounded-lg p-3 cursor-pointer transition-all duration-200 hover:shadow-md"
                                 data-type-id="{{ $customType->id }}"
                                 data-type-name="{{ $customType->name }}"
                                 data-type-description="{{ $customType->description }}"
                                 data-type-icon="{{ $customType->icon }}"
                                 data-type-color="{{ $customType->color }}"
                                 data-is-custom="true"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);"
                                 onclick="selectPropertyType(this, {{ $customType->id }}, 'custom')">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: {{ $customType->color }}20; border: 1px solid {{ $customType->color }}40;">
                                        <i class="{{ $customType->icon }} text-sm" style="color: {{ $customType->color }};"></i>
                                    </div>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $customType->name }}</span>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $customType->description }}</p>
                            </div>
                            @endif
                        </div>

                        <!-- Hidden select for form submission -->
                        <select name="property_type_id" id="property_type_id" class="hidden" required>
                            <option value="">Select Property Type</option>
                            @foreach($propertyTypes as $type)
                                <option value="{{ $type->id }}"
                                        {{ old('property_type_id', $property->property_type_id) == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>

                        <!-- Selected Property Type Display -->
                        <div id="selected-property-type" class="hidden mt-3 p-4 border-2 rounded-lg" style="border-color: var(--primary); background-color: var(--bg-secondary);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div id="selected-type-icon" class="w-12 h-12 rounded-full flex items-center justify-center">
                                        <!-- Icon will be inserted here -->
                                    </div>
                                    <div>
                                        <div id="selected-type-name" class="font-semibold text-lg" style="color: var(--text-primary);"></div>
                                        <div id="selected-type-description" class="text-sm" style="color: var(--text-secondary);"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="clearPropertyTypeSelection()" class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors">
                                    <i class="fas fa-times text-lg"></i>
                                </button>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 mt-1">Select the type of property or choose "Custom" to define your own</p>
                        @error('property_type_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Custom Property Type Input (shown only when custom is selected) -->
                    <div id="custom_property_type_container" class="hidden mt-4 p-4 border-2 rounded-lg" style="border-color: #6B7280; background-color: var(--bg-secondary);">
                        <div class="flex items-center space-x-2 mb-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: #6B728020; border: 1px solid #6B728040;">
                                <i class="fas fa-edit text-sm" style="color: #6B7280;"></i>
                            </div>
                            <div>
                                <label for="custom_property_type" class="block text-sm font-medium" style="color: var(--text-primary);">
                                    Define Your Custom Property Type *
                                </label>
                                <p class="text-xs" style="color: var(--text-secondary);">Enter a name for your custom property type</p>
                            </div>
                        </div>
                        <input type="text" name="custom_property_type" id="custom_property_type"
                               class="w-full p-3 border rounded-lg"
                               style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('custom_property_type', $property->custom_property_type) }}"
                               placeholder="e.g., Hotel, Community Center, Gym, Library, Shopping Mall, Restaurant..."
                               maxlength="100">
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-xs text-gray-500">This will be saved as your custom property type</p>
                            <span id="custom-type-chars" class="text-xs text-gray-400">0/100</span>
                        </div>
                        @error('custom_property_type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- ==================== PROPERTY PHOTOS SECTION ==================== -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Property Photos
                            <span class="text-xs font-normal text-gray-500 ml-1">(Optional - Max 10 photos)</span>
                        </label>

                        <!-- Existing Photos Display -->
                        @if($property->photos && $property->photos->count() > 0)
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Existing Photos</label>
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3" id="existing-photos-grid">
                                @foreach($property->photos as $photo)
                                <div class="existing-photo-item relative rounded-lg overflow-hidden border-2"
                                     style="border-color: {{ $photo->is_primary ? 'var(--primary)' : 'var(--border-color)' }};"
                                     data-photo-id="{{ $photo->id }}">
                                    <img class="w-full h-32 object-cover" src="{{ Storage::url($photo->photo_path) }}" alt="Property photo">
                                    <div class="absolute top-2 right-2 space-x-1">
                                        @if(!$photo->is_primary)
                                        <button type="button" class="make-primary-existing-btn bg-blue-500 text-white rounded-full p-1 w-7 h-7 text-xs hover:bg-blue-600 transition-colors"
                                                data-photo-id="{{ $photo->id }}" title="Make Primary">
                                            <i class="fas fa-star"></i>
                                        </button>
                                        @endif
                                        <button type="button" class="remove-existing-photo-btn bg-red-500 text-white rounded-full p-1 w-7 h-7 text-xs hover:bg-red-600 transition-colors"
                                                data-photo-id="{{ $photo->id }}" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs text-center py-1 {{ $photo->is_primary ? '' : 'hidden' }} primary-badge">
                                        <i class="fas fa-star mr-1"></i> Primary
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Photo Upload Area -->
                        <div class="photo-upload-area border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-all duration-200"
                             style="border-color: var(--border-color); background-color: var(--bg-secondary);"
                             id="photo-upload-area">
                            <input type="file" name="property_photos[]" id="property_photos"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   multiple
                                   class="hidden"
                                   onchange="handlePhotoSelection(this)">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-cloud-upload-alt text-4xl mb-2" style="color: var(--text-secondary);"></i>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Click or drag photos here
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Upload up to 10 photos (JPG, PNG, GIF, WebP - Max 5MB each)
                                </p>
                            </div>
                        </div>

                        <!-- New Photo Preview Grid -->
                        <div id="photo-preview-grid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mt-4">
                            <!-- New photo previews will be inserted here -->
                        </div>

                        <!-- Hidden inputs for photo management -->
                        <div id="photo-management-inputs">
                            <input type="hidden" name="delete_photos" id="delete_photos" value="">
                            <input type="hidden" name="primary_photo_id" id="primary_photo_id" value="{{ $property->photos->where('is_primary', true)->first()?->id ?? '' }}">
                        </div>

                        @error('property_photos')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @error('property_photos.*')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Global Sequence Information -->
                    <div id="global-sequence-info" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded p-3">
                            <div class="flex items-center">
                                <i class="fas fa-globe-americas text-blue-600 mr-2"></i>
                                <span class="font-medium text-blue-800">Global Sequence Plan</span>
                            </div>
                            <div id="sequence-continuation-info" class="text-sm text-blue-700 mt-1 hidden">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Registration Pattern Section -->
                    <div class="border rounded p-4" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div class="flex justify-between items-center mb-2">
                            <label for="registration_pattern" class="block text-sm font-medium" style="color: var(--text-primary);">
                                Registration Pattern *
                            </label>
                            <div class="flex items-center space-x-2">
                                <button type="button" id="refresh-pattern" class="text-blue-600 hover:text-blue-800 text-sm flex items-center hidden">
                                    <i class="fas fa-sync-alt mr-1"></i>Refresh Pattern
                                </button>
                                <span id="global-sequence-badge" class="hidden bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    <i class="fas fa-globe-americas mr-1"></i>Global
                                </span>
                            </div>
                        </div>

                        <input type="text" name="registration_pattern" id="registration_pattern"
                               class="w-full p-2 border rounded font-mono text-lg font-bold text-center"
                               style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('registration_pattern', $property->registration_pattern) }}"
                               readonly
                               required
                               placeholder="Select a plan to generate pattern">

                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between text-xs">
                                <span id="naming-pattern-info" class="text-gray-500">
                                    @if($registrationPlans->isEmpty())
                                        No registration plan selected
                                    @else
                                        Select a registration plan to see naming pattern
                                    @endif
                                </span>
                                <span id="pattern-status" class="hidden">
                                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                    <span class="text-green-600">Pattern available</span>
                                </span>
                            </div>

                            <!-- Pattern Loading Message -->
                            <div id="pattern-loading" class="text-xs hidden">
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                <span class="text-blue-600">Generating pattern...</span>
                            </div>

                            <!-- Pattern Validation Message -->
                            <div id="pattern-validation" class="text-xs hidden">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <span id="validation-message"></span>
                            </div>

                            <!-- Pattern Error Message -->
                            <div id="pattern-error" class="text-xs hidden">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <span id="error-message" class="text-red-600"></span>
                            </div>

                            <!-- Sequence Type Info -->
                            <div id="sequence-type-info" class="text-xs hidden">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span id="sequence-type-text"></span>
                            </div>

                            <!-- Next Pattern Preview -->
                            <div id="next-pattern-preview" class="text-xs hidden">
                                <i class="fas fa-eye mr-1"></i>
                                <span style="color: var(--text-secondary);">Next pattern preview:</span>
                                <span id="next-pattern-text" class="font-mono ml-1"></span>
                            </div>
                        </div>

                        @error('registration_pattern')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Property Name (Manual Input) -->
                    <div>
                        <label for="property_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Property Name *
                            <span class="text-xs font-normal text-gray-500 ml-1">(Site allocation name that belongs to landlord)</span>
                        </label>
                        <input type="text" name="property_name" id="property_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('property_name', $property->property_name) }}" placeholder="e.g., Samuel Mensah's House, Site Allocation Name" required>
                        <p class="text-xs text-gray-500 mt-1">This is the site allocation name that will be shown throughout the system</p>
                        @error('property_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="house_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">House Number</label>
                        <input type="text" name="house_number" id="house_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('house_number', $property->house_number) }}">
                        <p class="text-xs text-gray-500 mt-1">Physical house number (optional if using naming pattern)</p>
                        @error('house_number')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Street Name *</label>
                        <input type="text" name="street_name" id="street_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('street_name', $property->street_name) }}" required>
                        @error('street_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Right Column - Location & Status -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Location & Status</h3>

                    <!-- Registration Plan Status Alert -->
                    <div id="plan-status-alert" class="hidden">
                        <div class="p-3 rounded border" id="plan-status-content">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Registration Plan Progress -->
                    <div id="plan-progress-container" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded p-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-blue-800">Registration Plan Progress</span>
                                <span id="progress-percentage" class="text-sm font-bold text-blue-800">0%</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-2">
                                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-blue-600 mt-1">
                                <span id="progress-text">Registered: 0 / 0 houses</span>
                                <span id="completion-status">Incomplete</span>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-filled Zone and Section -->
                    <div id="auto-filled-fields">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Zone</label>
                            <div class="p-2 border rounded bg-gray-50" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <span id="zone-display" class="text-gray-500">{{ $property->zone ?: 'Select a registration plan' }}</span>
                                <input type="hidden" name="zone" id="zone" value="{{ old('zone', $property->zone) }}">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Section</label>
                            <div class="p-2 border rounded bg-gray-50" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <span id="section-display" class="text-gray-500">{{ $property->section ?: 'Select a registration plan' }}</span>
                                <input type="hidden" name="section" id="section" value="{{ old('section', $property->section) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Manual Zone/Section Override -->
                    <div id="manual-location-container" class="hidden">
                        <div>
                            <label for="manual_zone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Zone *</label>
                            <input type="text" name="manual_zone" id="manual_zone" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('manual_zone', $property->zone) }}" placeholder="Enter zone name">
                        </div>

                        <div>
                            <label for="manual_section" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Section</label>
                            <input type="text" name="manual_section" id="manual_section" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('manual_section', $property->section) }}" placeholder="Enter section name">
                        </div>
                    </div>

                    <!-- Location Override Toggle -->
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Not matching the plan's zone/section?</span>
                        <button type="button" id="toggle-location-override" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit mr-1"></i>Override
                        </button>
                    </div>

                    <div>
                        <label for="block_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Block Number</label>
                        <input type="text" name="block_number" id="block_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('block_number', $property->block_number) }}">
                        @error('block_number')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="digital_address" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Digital Address</label>
                        <input type="text" name="digital_address" id="digital_address" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="e.g., GA-543-9876" value="{{ old('digital_address', $property->digital_address) }}">
                        @error('digital_address')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="registration_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Registration Date *</label>
                        <input type="date" name="registration_date" id="registration_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('registration_date', $property->registration_date->format('Y-m-d')) }}" required>
                        @error('registration_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Property Status</label>
                        <select name="status" id="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="active" {{ old('status', $property->status) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status', $property->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="under_maintenance" {{ old('status', $property->status) == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                            <option value="vacant" {{ old('status', $property->status) == 'vacant' ? 'selected' : '' }}>Vacant</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Naming Pattern Preview -->
                    <div id="naming-preview-container" class="hidden">
                        <div class="bg-gray-50 p-4 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Naming Pattern Information</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Pattern:</span>
                                    <span class="font-mono font-bold" id="pattern-display"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Sequence Type:</span>
                                    <span id="sequence-info" class="font-medium"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Next Available:</span>
                                    <span class="font-mono font-bold text-green-600" id="next-available-info"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Starting Point:</span>
                                    <span class="font-mono" id="starting-point-info"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Plan Status:</span>
                                    <span id="plan-status-info" class="font-medium"></span>
                                </div>
                                <div class="flex justify-between" id="global-sequence-row">
                                    <span style="color: var(--text-secondary);">Global Sequence:</span>
                                    <span id="global-sequence-status" class="font-medium text-blue-600"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Landlord Information Section -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Landlord Information</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">
                    <!-- Existing Landlord -->
                    <div class="mb-4">
                        <label for="landlord_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Select Existing Landlord</label>
                        <select name="landlord_id" id="landlord_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Existing Landlord</option>
                            @foreach($landlords as $landlord)
                                <option value="{{ $landlord->id }}"
                                        data-phones="{{ $landlord->phones ? $landlord->phones->pluck('phone_number')->implode(',') : '' }}"
                                        data-email="{{ $landlord->email }}"
                                        data-has-phone="{{ !empty($landlord->phone) ? '1' : '0' }}"
                                        data-has-email="{{ !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL) ? '1' : '0' }}"
                                        {{ old('landlord_id', $property->landlord_id) == $landlord->id ? 'selected' : '' }}>
                                    {{ $landlord->name }}
                                    @if($landlord->phones && $landlord->phones->count() > 0)
                                        - {{ $landlord->phones->first()->phone_number }}
                                        @if($landlord->phones->count() > 1)
                                            +{{ $landlord->phones->count() - 1 }} more
                                        @endif
                                    @endif
                                    - {{ $landlord->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('landlord_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center my-6">
                        <div class="flex-1 border-t" style="border-color: var(--border-color);"></div>
                        <span class="px-3 text-sm font-medium" style="color: var(--text-secondary);">OR CREATE NEW LANDLORD</span>
                        <div class="flex-1 border-t" style="border-color: var(--border-color);"></div>
                    </div>

                    <!-- New Landlord Information -->
                    <div class="space-y-4">
                        <!-- Landlord Name -->
                        <div>
                            <label for="landlord_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Full Name *</label>
                            <input type="text" name="landlord_name" id="landlord_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Enter full name" value="{{ old('landlord_name') }}">
                            @error('landlord_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone Numbers Section -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Phone Numbers *
                                <span class="text-xs font-normal text-gray-500 ml-1">(At least one phone number is required)</span>
                            </label>

                            <!-- Phone Numbers Container -->
                            <div id="phone-numbers-container" class="space-y-3">
                                <!-- Primary Phone Number -->
                                <div class="phone-number-group">
                                    <div class="flex items-center space-x-2">
                                        <input type="text"
                                               name="landlord_phones[]"
                                               class="w-full p-2 border rounded phone-input"
                                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                               placeholder="e.g., 024 123 4567 (Primary)"
                                               value="{{ old('landlord_phones.0') }}"
                                               data-index="0">
                                        <button type="button" class="remove-phone-btn text-red-500 hover:text-red-700 p-2 hidden" title="Remove phone number">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Another Phone Button -->
                            <button type="button" id="add-phone-btn" class="mt-2 text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                <i class="fas fa-plus-circle mr-1"></i> Add Another Phone Number
                            </button>

                            <p class="text-xs text-gray-500 mt-2">Enter at least one phone number. You can add multiple numbers for the same landlord.</p>
                            @error('landlord_phones')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            @error('landlord_phones.*')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email (Optional) -->
                        <div>
                            <label for="landlord_email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Email Address</label>
                            <input type="email" name="landlord_email" id="landlord_email" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="e.g., landlord@example.com" value="{{ old('landlord_email') }}">
                            @error('landlord_email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Landlord Invitation Section -->
            <div class="mt-6" id="landlord-invitation-section">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Landlord Invitation</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">

                    {{-- ============================================================ --}}
                    {{-- ALL CHANNELS UNAVAILABLE BANNER --}}
                    {{-- ============================================================ --}}
                    @if(!$anyChannelReady)
                    <div class="mb-4 p-4 rounded border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    Landlord invitations cannot be sent until at least one channel is set up.
                                    You can still save the property without sending an invitation.
                                </p>
                                @if(auth()->user()->isAdmin())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.system-settings.index') }}#whatsapp-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure WhatsApp
                                    </a>
                                    <a href="{{ route('admin.system-settings.index') }}#sms-configuration" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure SMS
                                    </a>
                                    <a href="{{ route('admin.email-config.index') }}" class="btn-secondary btn-sm flex items-center">
                                        <i class="fas fa-cog mr-2"></i> Configure Email
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Modern Radio Button for Invitation Toggle -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Send Invitation to Landlord
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Send Invitation Option -->
                            <div class="modern-radio-option {{ !$anyChannelReady ? 'modern-radio-option--disabled' : '' }}">
                                <input type="radio" name="send_invitation" id="send_invitation_yes" value="1"
                                       class="modern-radio-input"
                                       {{ $anyChannelReady ? '' : 'disabled' }}
                                       {{ old('send_invitation') ? 'checked' : '' }}>
                                <label for="send_invitation_yes" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Send Invitation</div>
                                            <div class="modern-radio-description">
                                                @if($anyChannelReady)
                                                    Send registration invitation to landlord
                                                @else
                                                    <span class="text-red-500">No delivery channels available</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Don't Send Invitation Option -->
                            <div class="modern-radio-option">
                                <input type="radio" name="send_invitation" id="send_invitation_no" value="0"
                                       class="modern-radio-input"
                                       {{ !old('send_invitation') ? 'checked' : '' }}>
                                <label for="send_invitation_no" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-ban"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Don't Send</div>
                                            <div class="modern-radio-description">Skip invitation for now</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 ml-1">
                            Choose whether to send registration invitation to landlord via selected communication channels
                        </p>
                    </div>

                    <!-- Invitation Channels - Modern Radio Buttons -->
                    <div id="invitation-channels-container" class="hidden space-y-4">
                        <label class="block text-sm font-medium" style="color: var(--text-primary);">
                            Invitation Channels *
                            <span class="text-xs font-normal text-gray-500 ml-1">(Select at least one channel)</span>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- SMS CHANNEL --}}
                            <div class="modern-radio-option channel-option {{ !$smsReady ? 'channel-disabled' : '' }}"
                                 data-channel="sms"
                                 @if(!$smsReady) aria-disabled="true" title="SMS service not configured" @endif>
                                <input type="checkbox" name="invitation_channels[]" value="sms"
                                       id="channel_sms"
                                       class="modern-radio-input channel-checkbox"
                                       {{ $smsReady ? '' : 'disabled' }}
                                       {{ in_array('sms', $oldInvitationChannels, true) ? 'checked' : '' }}>
                                <label for="channel_sms" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon bg-green-100 text-green-600">
                                            <i class="fas fa-sms"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">SMS</div>
                                            <div class="modern-radio-description">
                                                @if($smsReady)
                                                    Text message
                                                @else
                                                    <span class="text-red-500">Not available</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            {{-- EMAIL CHANNEL --}}
                            <div class="modern-radio-option channel-option {{ !$emailReady ? 'channel-disabled' : '' }}"
                                 data-channel="email"
                                 @if(!$emailReady) aria-disabled="true" title="Email service not configured" @endif>
                                <input type="checkbox" name="invitation_channels[]" value="email"
                                       id="channel_email"
                                       class="modern-radio-input channel-checkbox"
                                       {{ $emailReady ? '' : 'disabled' }}
                                       {{ in_array('email', $oldInvitationChannels, true) ? 'checked' : '' }}>
                                <label for="channel_email" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon bg-blue-100 text-blue-600">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Email</div>
                                            <div class="modern-radio-description">
                                                @if($emailReady)
                                                    Email message
                                                @else
                                                    <span class="text-red-500">Not available</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            {{-- WHATSAPP CHANNEL --}}
                            <div class="modern-radio-option channel-option {{ !$whatsappReady ? 'channel-disabled' : '' }}"
                                 data-channel="whatsapp"
                                 @if(!$whatsappReady) aria-disabled="true" title="WhatsApp service not configured" @endif>
                                <input type="checkbox" name="invitation_channels[]" value="whatsapp"
                                       id="channel_whatsapp"
                                       class="modern-radio-input channel-checkbox"
                                       {{ $whatsappReady ? '' : 'disabled' }}
                                       {{ in_array('whatsapp', $oldInvitationChannels, true) ? 'checked' : '' }}>
                                <label for="channel_whatsapp" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon bg-green-100 text-green-600">
                                            <i class="fab fa-whatsapp"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">WhatsApp</div>
                                            <div class="modern-radio-description">
                                                @if($whatsappReady)
                                                    WhatsApp message
                                                @else
                                                    <span class="text-red-500">Not available</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Available Channels Info -->
                        <div id="available-channels-info" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-700">
                                    Available channels: <span id="available-channels-list"></span>
                                </span>
                            </div>
                        </div>

                        <!-- No Channels Warning -->
                        <div id="no-channels-warning" class="hidden mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                <span class="text-sm text-yellow-700">
                                    No communication channels available for this landlord. Please ensure the landlord has at least one valid phone number or email address.
                                </span>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 mt-2">
                            The landlord will receive a registration link to set up their password and access the landlord portal.
                        </p>
                        @error('invitation_channels')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @error('invitation_channels.*')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Invitation Preview -->
                    <div id="invitation-preview" class="hidden mt-4 p-4 rounded border"
                         style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Invitation Preview</h4>
                        <div class="text-sm whitespace-pre-wrap" id="invitation-message-preview" style="color: var(--text-secondary);">
                            <!-- Preview content will be generated here -->
                        </div>
                        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Landlord will receive a secure invitation link to set their password and access the landlord portal.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Invitation Section (Only for Rented Houses) -->
            <div class="mt-6" id="tenant-invitation-section">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tenant Information & Invitation</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">
                    <!-- Is Property Rented? Radio -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Is this property rented to tenants?
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Yes, Property is Rented -->
                            <div class="modern-radio-option">
                                <input type="radio" name="is_rented" id="is_rented_yes" value="1"
                                       class="modern-radio-input"
                                       {{ old('is_rented', $property->tenants->count() > 0 ? 1 : 0) ? 'checked' : '' }}>
                                <label for="is_rented_yes" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Yes, Property is Rented</div>
                                            <div class="modern-radio-description">Manage tenants for this property</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- No, Not Rented -->
                            <div class="modern-radio-option">
                                <input type="radio" name="is_rented" id="is_rented_no" value="0"
                                       class="modern-radio-input"
                                       {{ !old('is_rented', $property->tenants->count() > 0 ? 1 : 0) ? 'checked' : '' }}>
                                <label for="is_rented_no" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">No, Not Rented</div>
                                            <div class="modern-radio-description">Property is not currently rented</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tenant Invitation Container -->
                    <div id="tenant-invitation-container" class="hidden mt-4">
                        <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-700">
                                    This property is marked as rented. You can manage tenants for this property.
                                </span>
                            </div>
                        </div>

                        <!-- Existing Tenants Display -->
                        @if($property->tenants && $property->tenants->count() > 0)
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Existing Tenants</label>
                            <div class="space-y-2" id="existing-tenants-list">
                                @foreach($property->tenants as $tenant)
                                <div class="flex items-center justify-between p-3 border rounded existing-tenant-item"
                                     style="background-color: var(--bg-secondary); border-color: var(--border-color);"
                                     data-tenant-id="{{ $tenant->id }}">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $tenant->name }}</div>
                                            <div class="text-sm" style="color: var(--text-secondary);">{{ $tenant->phone }}</div>
                                            @if($tenant->email)
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $tenant->email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">
                                            {{ $tenant->channels ?? 'sms' }}
                                        </span>
                                        <button type="button" onclick="removeExistingTenant({{ $tenant->id }})" class="text-red-500 hover:text-red-700 p-1">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Add Tenants Button -->
                        <button type="button"
                                id="add-tenants-btn"
                                class="btn-primary flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Add New Tenants
                        </button>

                        <!-- New Tenants Summary (will be populated by JavaScript) -->
                        <div id="tenants-summary" class="mt-4 hidden">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">New Tenants to Add</h4>
                            <div class="space-y-2" id="tenants-list">
                                <!-- Tenant items will be added here -->
                            </div>
                            <div class="mt-2 text-sm text-gray-600" id="tenants-count">
                                <!-- Count will be updated here -->
                            </div>
                        </div>

                        <!-- Hidden inputs for form submission -->
                        <div id="tenant-hidden-inputs">
                            <!-- Tenant data will be added here by JavaScript -->
                        </div>
                    </div>

                    <!-- Warning for switching to not rented -->
                    <div id="rented-warning" class="hidden mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <span class="text-sm text-yellow-700">
                                Switching this property to "Not Rented" will remove all tenant associations. This action cannot be undone.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Additional Information</h3>

                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Description</label>
                    <textarea name="description" id="description" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" rows="3" placeholder="Enter any additional details about the property...">{{ old('description', $property->description) }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Property Metadata (Read-only) -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Property Metadata</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="p-3 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Created:</span>
                        <p style="color: var(--text-secondary);">{{ $property->created_at->format('M j, Y g:i A') }}</p>
                        <p style="color: var(--text-secondary);">by {{ $property->creator->name ?? 'System' }}</p>
                    </div>

                    <div class="p-3 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Last Updated:</span>
                        <p style="color: var(--text-secondary);">{{ $property->updated_at->format('M j, Y g:i A') }}</p>
                        <p style="color: var(--text-secondary);">by {{ $property->updater->name ?? 'System' }}</p>
                    </div>

                    <div class="p-3 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Property ID:</span>
                        <p style="color: var(--text-secondary);">{{ $property->id }}</p>
                        <p style="color: var(--text-secondary);">Pattern: {{ $property->registration_pattern ?? 'N/A' }}</p>
                        @if($property->registered_by)
                            <p style="color: var(--text-secondary);">
                                Registered by: {{ $property->registrant->name ?? 'Field Agent' }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between items-center mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <div>
                    <a href="{{ route('properties.show', $property->id) }}" class="btn-secondary mr-2">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <a href="{{ route('properties.index') }}" class="btn-secondary">
                        <i class="fas fa-list mr-2"></i> All Properties
                    </a>
                </div>

                <div class="flex space-x-4">
                    <!-- Delete Button -->
                    <button type="button" onclick="confirmDelete()" class="btn-danger flex items-center">
                        <i class="fas fa-trash mr-2"></i> Delete Property
                    </button>

                    <button type="submit" class="btn-primary flex items-center" id="submit-button" {{ $registrationPlans->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>

        <!-- Delete Form (hidden) -->
        <form id="delete-form" action="{{ route('properties.destroy', $property->id) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

<!-- Tenant Invitation Modal -->
<div id="tenant-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-lg" style="background-color: var(--bg-primary); border-color: var(--border-color);">
        <div class="flex justify-between items-center mb-6 pb-4 border-b" style="border-color: var(--border-color);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-user-plus mr-2"></i>Add New Tenants
            </h3>
            <button type="button" onclick="closeTenantModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Number of Tenants *
            </label>
            <select id="tenant-count" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                <option value="">Select number of tenants</option>
                @for($i = 1; $i <= 10; $i++)
                    <option value="{{ $i }}">{{ $i }} tenant{{ $i > 1 ? 's' : '' }}</option>
                @endfor
            </select>
            <p class="text-xs text-gray-500 mt-1">How many tenants are living in this property?</p>
        </div>

        <!-- Tenant Forms Container -->
        <div id="tenant-forms-container" class="space-y-6 max-h-96 overflow-y-auto pr-2">
            <!-- Tenant forms will be dynamically added here -->
        </div>

        <!-- Modal Actions -->
        <div class="flex justify-end space-x-4 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
            <button type="button" onclick="closeTenantModal()" class="btn-secondary">
                Cancel
            </button>
            <button type="button" onclick="saveTenants()" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Save Tenants
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Add this hidden element for URLs -->
<div id="app-urls"
     data-create-plan="{{ route('registration-plans.create') }}"
     data-get-channels-url="{{ route('landlord.channels', ['landlord' => 'LANDLORD_ID']) }}"
     style="display: none;">
</div>

<script>
// ==================== GLOBAL CHANNEL AVAILABILITY ====================
// Server-rendered state (source of truth)
const SMS_SYSTEM_READY      = {{ $smsReady ? 'true' : 'false' }};
const WHATSAPP_SYSTEM_READY = {{ $whatsappReady ? 'true' : 'false' }};
const EMAIL_SYSTEM_READY    = {{ $emailReady ? 'true' : 'false' }};
const ANY_CHANNEL_READY     = SMS_SYSTEM_READY || WHATSAPP_SYSTEM_READY || EMAIL_SYSTEM_READY;

function isChannelGloballyReady(channel) {
    if (channel === 'sms')      return SMS_SYSTEM_READY;
    if (channel === 'whatsapp') return WHATSAPP_SYSTEM_READY;
    if (channel === 'email')    return EMAIL_SYSTEM_READY;
    return false;
}

// ==================== PHOTO UPLOAD FUNCTIONALITY ====================

let selectedPhotos = [];
let primaryPhotoIndex = -1;
let photosToDelete = [];
const MAX_PHOTOS = 10;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];

function handlePhotoSelection(input) {
    const files = Array.from(input.files);
    const existingPhotosCount = {{ $property->photos ? $property->photos->count() : 0 }};
    const totalPhotos = existingPhotosCount + selectedPhotos.length + files.length;

    if (totalPhotos > MAX_PHOTOS) {
        alert(`You can only upload up to ${MAX_PHOTOS} photos total. You currently have ${existingPhotosCount} existing photos and ${selectedPhotos.length} new photos selected.`);
        input.value = '';
        return;
    }

    files.forEach(file => {
        if (!ALLOWED_TYPES.includes(file.type)) {
            alert(`File "${file.name}" is not an allowed image type. Please upload JPG, PNG, GIF, or WebP images.`);
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            alert(`File "${file.name}" exceeds the 5MB limit.`);
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const isPrimary = (existingPhotosCount + selectedPhotos.length === 0) &&
                             !document.querySelector('.existing-photo-item .primary-badge:not(.hidden)');
            selectedPhotos.push({
                file: file,
                preview: e.target.result,
                isPrimary: isPrimary
            });

            if (isPrimary) {
                primaryPhotoIndex = selectedPhotos.length - 1;
            }

            updatePhotoPreviewGrid();
        };
        reader.readAsDataURL(file);
    });

    input.value = '';
}

function updatePhotoPreviewGrid() {
    const grid = document.getElementById('photo-preview-grid');
    if (!grid) return;

    grid.innerHTML = '';

    if (selectedPhotos.length === 0) {
        const emptyHint = document.createElement('div');
        emptyHint.className = 'col-span-full text-center py-4';
        emptyHint.style.color = 'var(--text-secondary)';
        emptyHint.innerHTML = '<i class="fas fa-camera text-2xl mb-1 block"></i><p class="text-sm">No new photos selected</p>';
        grid.appendChild(emptyHint);
        return;
    }

    selectedPhotos.forEach((photo, index) => {
        const photoItem = document.createElement('div');
        photoItem.className = 'photo-preview-item relative rounded-lg overflow-hidden border-2';
        photoItem.style.borderColor = photo.isPrimary ? 'var(--primary)' : 'var(--border-color)';
        photoItem.style.backgroundColor = 'var(--bg-secondary)';

        photoItem.innerHTML = `
            <img class="w-full h-32 object-cover" src="${photo.preview}" alt="New photo ${index + 1}">
            <div class="absolute top-2 right-2 space-x-1">
                <button type="button" class="make-primary-btn bg-blue-500 text-white rounded-full p-1 w-7 h-7 text-xs hover:bg-blue-600 transition-colors"
                        data-index="${index}" title="Make Primary" ${photo.isPrimary ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="remove-photo-btn bg-red-500 text-white rounded-full p-1 w-7 h-7 text-xs hover:bg-red-600 transition-colors"
                        data-index="${index}" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs text-center py-1 ${photo.isPrimary ? '' : 'hidden'} primary-badge">
                <i class="fas fa-star mr-1"></i> Primary
            </div>
        `;

        grid.appendChild(photoItem);
    });

    updateFileInput();

    document.querySelectorAll('.make-primary-btn').forEach(btn => {
        btn.removeEventListener('click', handleMakePrimary);
        btn.addEventListener('click', handleMakePrimary);
    });

    document.querySelectorAll('.remove-photo-btn').forEach(btn => {
        btn.removeEventListener('click', handleRemovePhoto);
        btn.addEventListener('click', handleRemovePhoto);
    });
}

function updateFileInput() {
    const fileInput = document.getElementById('property_photos');
    const dataTransfer = new DataTransfer();

    selectedPhotos.forEach(photo => {
        dataTransfer.items.add(photo.file);
    });

    fileInput.files = dataTransfer.files;
}

function handleMakePrimary(e) {
    const index = parseInt(e.currentTarget.dataset.index);
    if (!isNaN(index) && index !== primaryPhotoIndex) {
        primaryPhotoIndex = index;

        selectedPhotos.forEach((photo, i) => {
            photo.isPrimary = (i === index);
        });

        updatePhotoPreviewGrid();

        document.querySelectorAll('.existing-photo-item').forEach(item => {
            const badge = item.querySelector('.primary-badge');
            if (badge) badge.classList.add('hidden');
            item.style.borderColor = 'var(--border-color)';
        });

        document.getElementById('primary_photo_id').value = '';
    }
}

function handleRemovePhoto(e) {
    const index = parseInt(e.currentTarget.dataset.index);
    if (!isNaN(index)) {
        selectedPhotos.splice(index, 1);

        if (primaryPhotoIndex === index) {
            primaryPhotoIndex = selectedPhotos.length > 0 ? 0 : -1;
            if (primaryPhotoIndex >= 0 && selectedPhotos[primaryPhotoIndex]) {
                selectedPhotos[primaryPhotoIndex].isPrimary = true;
            }
        } else if (primaryPhotoIndex > index) {
            primaryPhotoIndex--;
        }

        updatePhotoPreviewGrid();
    }
}

function setupExistingPhotoHandlers() {
    document.querySelectorAll('.remove-existing-photo-btn').forEach(btn => {
        btn.removeEventListener('click', handleRemoveExistingPhoto);
        btn.addEventListener('click', handleRemoveExistingPhoto);
    });

    document.querySelectorAll('.make-primary-existing-btn').forEach(btn => {
        btn.removeEventListener('click', handleMakePrimaryExisting);
        btn.addEventListener('click', handleMakePrimaryExisting);
    });
}

function handleRemoveExistingPhoto(e) {
    const photoId = e.currentTarget.dataset.photoId;
    if (photoId && confirm('Are you sure you want to remove this photo?')) {
        photosToDelete.push(parseInt(photoId));
        const photoItem = e.currentTarget.closest('.existing-photo-item');
        if (photoItem) {
            photoItem.remove();
            updateDeletePhotosInput();

            const wasPrimary = photoItem.querySelector('.primary-badge:not(.hidden)');
            if (wasPrimary) {
                document.getElementById('primary_photo_id').value = '';
                const firstRemaining = document.querySelector('.existing-photo-item');
                if (firstRemaining) {
                    const makePrimaryBtn = firstRemaining.querySelector('.make-primary-existing-btn');
                    if (makePrimaryBtn) {
                        makePrimaryBtn.click();
                    }
                }
            }
        }
    }
}

function handleMakePrimaryExisting(e) {
    const photoId = e.currentTarget.dataset.photoId;
    if (photoId) {
        document.getElementById('primary_photo_id').value = photoId;

        document.querySelectorAll('.existing-photo-item').forEach(item => {
            const badge = item.querySelector('.primary-badge');
            if (badge) badge.classList.add('hidden');
            item.style.borderColor = 'var(--border-color)';

            const btn = item.querySelector('.make-primary-existing-btn');
            if (btn) btn.style.display = 'inline-flex';
        });

        const selectedItem = e.currentTarget.closest('.existing-photo-item');
        if (selectedItem) {
            const badge = selectedItem.querySelector('.primary-badge');
            if (badge) badge.classList.remove('hidden');
            selectedItem.style.borderColor = 'var(--primary)';
            e.currentTarget.style.display = 'none';
        }

        selectedPhotos.forEach(photo => {
            photo.isPrimary = false;
        });
        primaryPhotoIndex = -1;
        updatePhotoPreviewGrid();
    }
}

function updateDeletePhotosInput() {
    document.getElementById('delete_photos').value = photosToDelete.join(',');
}

function setupPhotoUpload() {
    const uploadArea = document.getElementById('photo-upload-area');
    const fileInput = document.getElementById('property_photos');

    if (!uploadArea || !fileInput) return;

    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary)';
        uploadArea.style.backgroundColor = 'rgba(114, 103, 240, 0.05)';
    });

    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--border-color)';
        uploadArea.style.backgroundColor = 'var(--bg-secondary)';
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--border-color)';
        uploadArea.style.backgroundColor = 'var(--bg-secondary)';

        const files = Array.from(e.dataTransfer.files).filter(file =>
            ALLOWED_TYPES.includes(file.type)
        );

        const existingPhotosCount = {{ $property->photos ? $property->photos->count() : 0 }};
        const totalPhotos = existingPhotosCount + selectedPhotos.length + files.length;

        if (totalPhotos > MAX_PHOTOS) {
            alert(`You can only upload up to ${MAX_PHOTOS} photos total.`);
            return;
        }

        files.forEach(file => {
            if (file.size > MAX_FILE_SIZE) {
                alert(`File "${file.name}" exceeds the 5MB limit.`);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const isPrimary = (existingPhotosCount + selectedPhotos.length === 0) &&
                                 !document.querySelector('.existing-photo-item .primary-badge:not(.hidden)');
                selectedPhotos.push({
                    file: file,
                    preview: e.target.result,
                    isPrimary: isPrimary
                });

                if (isPrimary) {
                    primaryPhotoIndex = selectedPhotos.length - 1;
                }

                updatePhotoPreviewGrid();
            };
            reader.readAsDataURL(file);
        });
    });
}

// ==================== PHONE NUMBER MANAGEMENT ====================

let phoneCounter = 1;

function initializePhoneNumbers() {
    const oldPhones = @json(old('landlord_phones', []));
    if (oldPhones && oldPhones.length > 1) {
        for (let i = 1; i < oldPhones.length; i++) {
            if (oldPhones[i]) {
                addPhoneNumber(oldPhones[i]);
            }
        }
    }
}

function addPhoneNumber(value = '') {
    const container = document.getElementById('phone-numbers-container');
    const phoneGroup = document.createElement('div');
    phoneGroup.className = 'phone-number-group';
    phoneGroup.innerHTML = `
        <div class="flex items-center space-x-2">
            <input type="text"
                   name="landlord_phones[]"
                   class="w-full p-2 border rounded phone-input"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                   placeholder="e.g., 024 765 4321"
                   value="${escapeHtml(value)}"
                   data-index="${phoneCounter}">
            <button type="button" class="remove-phone-btn text-red-500 hover:text-red-700 p-2" title="Remove phone number">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(phoneGroup);
    phoneCounter++;

    const newInput = phoneGroup.querySelector('.phone-input');
    newInput.addEventListener('input', formatPhoneNumber);

    const removeBtn = phoneGroup.querySelector('.remove-phone-btn');
    removeBtn.addEventListener('click', function() {
        removePhoneNumber(this);
    });

    updateRemoveButtons();
}

function removePhoneNumber(button) {
    const phoneGroup = button.closest('.phone-number-group');
    if (phoneGroup) {
        phoneGroup.remove();
        updateRemoveButtons();
    }
}

function updateRemoveButtons() {
    const removeButtons = document.querySelectorAll('.remove-phone-btn');
    const phoneGroups = document.querySelectorAll('.phone-number-group');

    if (phoneGroups.length > 1) {
        removeButtons.forEach(btn => btn.classList.remove('hidden'));
    } else {
        removeButtons.forEach(btn => btn.classList.add('hidden'));
    }
}

function formatPhoneNumber(e) {
    let value = e.target.value.replace(/\D/g, '');

    if (value.length > 0) {
        if (value.length <= 3) {
            value = value;
        } else if (value.length <= 6) {
            value = value.slice(0, 3) + ' ' + value.slice(3);
        } else {
            value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
        }
    }

    e.target.value = value;
}

function validatePhoneNumbers() {
    const phoneInputs = document.querySelectorAll('.phone-input');
    let hasValidPhone = false;

    for (let input of phoneInputs) {
        const cleanPhone = input.value.replace(/\D/g, '');
        if (cleanPhone.length >= 10) {
            hasValidPhone = true;
            break;
        }
    }

    return hasValidPhone;
}

// ==================== PROPERTY TYPE MANAGEMENT ====================

function selectPropertyType(cardElement, typeId, slug) {
    document.querySelectorAll('.property-type-card').forEach(card => {
        card.style.borderColor = 'var(--border-color)';
        card.style.backgroundColor = 'var(--bg-secondary)';
        card.style.boxShadow = '';
    });

    const typeColor = cardElement.getAttribute('data-type-color');
    cardElement.style.borderColor = typeColor;
    cardElement.style.backgroundColor = typeColor + '10';
    cardElement.style.boxShadow = `0 0 0 2px ${typeColor}40`;

    const propertyTypeSelect = document.getElementById('property_type_id');
    propertyTypeSelect.value = typeId;

    const selectedDisplay = document.getElementById('selected-property-type');
    const selectedIcon = document.getElementById('selected-type-icon');
    const selectedName = document.getElementById('selected-type-name');
    const selectedDescription = document.getElementById('selected-type-description');

    const typeIcon = cardElement.getAttribute('data-type-icon');
    const typeName = cardElement.getAttribute('data-type-name');
    const typeDescription = cardElement.getAttribute('data-type-description');

    selectedIcon.innerHTML = `<i class="${typeIcon} text-lg" style="color: ${typeColor};"></i>`;
    selectedIcon.style.backgroundColor = typeColor + '20';
    selectedIcon.style.border = `2px solid ${typeColor}40`;

    selectedName.textContent = typeName;
    selectedDescription.textContent = typeDescription;

    selectedDisplay.classList.remove('hidden');

    document.getElementById('property-type-cards').classList.add('hidden');

    const customContainer = document.getElementById('custom_property_type_container');
    const customInput = document.getElementById('custom_property_type');

    if (slug === 'custom') {
        customContainer.classList.remove('hidden');
        customInput.setAttribute('required', 'required');

        setTimeout(() => {
            customInput.focus();
        }, 100);
    } else {
        customContainer.classList.add('hidden');
        customInput.removeAttribute('required');
    }

    updateCustomTypeCharCounter();
}

function clearPropertyTypeSelection() {
    document.querySelectorAll('.property-type-card').forEach(card => {
        card.style.borderColor = 'var(--border-color)';
        card.style.backgroundColor = 'var(--bg-secondary)';
        card.style.boxShadow = '';
    });

    document.getElementById('property_type_id').value = '';

    document.getElementById('selected-property-type').classList.add('hidden');

    document.getElementById('property-type-cards').classList.remove('hidden');

    document.getElementById('custom_property_type_container').classList.add('hidden');
    document.getElementById('custom_property_type').removeAttribute('required');
    document.getElementById('custom_property_type').value = '';
}

function updateCustomTypeCharCounter() {
    const customInput = document.getElementById('custom_property_type');
    const charCounter = document.getElementById('custom-type-chars');

    if (customInput && charCounter) {
        const length = customInput.value.length;
        charCounter.textContent = `${length}/100`;

        if (length > 80) {
            charCounter.style.color = '#ef4444';
        } else if (length > 60) {
            charCounter.style.color = '#f59e0b';
        } else {
            charCounter.style.color = '#6b7280';
        }
    }
}

function initializePropertyTypeSelection() {
    const propertyTypeSelect = document.getElementById('property_type_id');
    const selectedValue = propertyTypeSelect.value;

    if (selectedValue) {
        const cardElement = document.querySelector(`.property-type-card[data-type-id="${selectedValue}"]`);
        if (cardElement) {
            const isCustom = cardElement.getAttribute('data-is-custom') === 'true';
            const slug = isCustom ? 'custom' : 'standard';
            selectPropertyType(cardElement, selectedValue, slug);
        }
    }
}

// ==================== LANDLORD INVITATION FUNCTIONALITY ====================

function toggleInvitationSection() {
    const sendInvitationYes = document.getElementById('send_invitation_yes');
    const channelsContainer = document.getElementById('invitation-channels-container');
    const invitationPreview = document.getElementById('invitation-preview');

    if (sendInvitationYes && sendInvitationYes.checked) {
        if (channelsContainer) channelsContainer.classList.remove('hidden');
        checkAvailableChannels();
        updateInvitationPreview();
    } else {
        if (channelsContainer) channelsContainer.classList.add('hidden');
        if (invitationPreview) invitationPreview.classList.add('hidden');
    }
}

async function checkAvailableChannels() {
    const landlordId = document.getElementById('landlord_id')?.value;
    const landlordName = document.getElementById('landlord_name')?.value;
    const availableChannelsInfo = document.getElementById('available-channels-info');
    const noChannelsWarning = document.getElementById('no-channels-warning');
    const availableChannelsList = document.getElementById('available-channels-list');

    if (availableChannelsInfo) availableChannelsInfo.classList.add('hidden');
    if (noChannelsWarning) noChannelsWarning.classList.add('hidden');

    if (landlordId) {
        try {
            const urlsDiv = document.getElementById('app-urls');
            const urlTemplate = urlsDiv?.dataset.getChannelsUrl;
            if (urlTemplate) {
                const url = urlTemplate.replace('LANDLORD_ID', landlordId);
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    const availableChannels = data.available_channels;
                    updateChannelOptions(availableChannels);

                    if (availableChannels.length > 0 && availableChannelsInfo && availableChannelsList) {
                        availableChannelsInfo.classList.remove('hidden');
                        availableChannelsList.textContent = availableChannels.map(channel =>
                            channel.charAt(0).toUpperCase() + channel.slice(1)
                        ).join(', ');
                    } else if (noChannelsWarning) {
                        noChannelsWarning.classList.remove('hidden');
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching available channels:', error);
            checkBasicChannelAvailability(landlordId);
        }
    } else if (landlordName) {
        checkBasicChannelAvailability(null);
    }
}

function checkBasicChannelAvailability(landlordId) {
    const availableChannelsInfo = document.getElementById('available-channels-info');
    const noChannelsWarning = document.getElementById('no-channels-warning');
    const availableChannelsList = document.getElementById('available-channels-list');

    let availableChannels = [];

    if (landlordId) {
        const selectedOption = document.querySelector(`#landlord_id option[value="${landlordId}"]`);
        if (selectedOption) {
            const hasPhone = selectedOption.dataset.hasPhone === '1';
            const hasEmail = selectedOption.dataset.hasEmail === '1';

            if (hasPhone && SMS_SYSTEM_READY)      availableChannels.push('sms');
            if (hasPhone && WHATSAPP_SYSTEM_READY) availableChannels.push('whatsapp');
            if (hasEmail && EMAIL_SYSTEM_READY)    availableChannels.push('email');
        }
    } else {
        const hasValidPhone = validatePhoneNumbers();
        const landlordEmail = document.getElementById('landlord_email')?.value || '';
        const hasValidEmail = landlordEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(landlordEmail);

        if (hasValidPhone && SMS_SYSTEM_READY)      availableChannels.push('sms');
        if (hasValidPhone && WHATSAPP_SYSTEM_READY) availableChannels.push('whatsapp');
        if (hasValidEmail && EMAIL_SYSTEM_READY)    availableChannels.push('email');
    }

    updateChannelOptions(availableChannels);

    if (availableChannels.length > 0 && availableChannelsInfo && availableChannelsList) {
        availableChannelsInfo.classList.remove('hidden');
        availableChannelsList.textContent = availableChannels.map(channel =>
            channel.charAt(0).toUpperCase() + channel.slice(1)
        ).join(', ');
    } else if (noChannelsWarning) {
        noChannelsWarning.classList.remove('hidden');
    }
}

function updateChannelOptions(availableChannels) {
    const channelOptions = document.querySelectorAll('.channel-option');

    channelOptions.forEach(option => {
        const channel = option.dataset.channel;
        const checkbox = option.querySelector('.channel-checkbox');
        if (!checkbox) return;

        const globallyReady = isChannelGloballyReady(channel);
        const selectable = globallyReady && availableChannels.includes(channel);

        if (selectable) {
            option.style.opacity = '1';
            option.style.cursor = 'pointer';
            option.classList.remove('channel-disabled', 'opacity-50', 'cursor-not-allowed');
            option.removeAttribute('aria-disabled');
            checkbox.disabled = false;

            updateCheckboxVisualState(checkbox);
        } else {
            option.style.opacity = '0.5';
            option.style.cursor = 'not-allowed';
            option.classList.add('channel-disabled', 'opacity-50', 'cursor-not-allowed');
            option.setAttribute('aria-disabled', 'true');
            checkbox.disabled = true;
            checkbox.checked = false;
            option.classList.remove('modern-radio-option--checked');
        }
    });
}

function updateInvitationPreview() {
    const invitationPreview = document.getElementById('invitation-preview');
    const invitationMessage = document.getElementById('invitation-message-preview');

    if (!invitationPreview || !invitationMessage) return;

    const landlordName = document.getElementById('landlord_name')?.value ||
                        document.querySelector('#landlord_id option:checked')?.text?.split(' - ')[0] ||
                        'Landlord';

    const propertyName = document.getElementById('property_name')?.value || 'Your Property';
    const streetName = document.getElementById('street_name')?.value || 'Unknown Street';
    const zone = document.getElementById('zone')?.value || document.getElementById('manual_zone')?.value || 'Unknown Zone';
    const registrationPattern = document.getElementById('registration_pattern')?.value || 'Unknown Pattern';

    const message = `Hello ${landlordName}!

Your property has been registered in our system:
🏠 Property: ${propertyName}
📍 Location: ${streetName}, ${zone}
🔢 Registration: ${registrationPattern}

To complete your registration and access your landlord portal, please set your password using the link we've sent you.

Thank you for registering with us!`;

    invitationMessage.textContent = message;
    invitationPreview.classList.remove('hidden');
}

function setupChannelOptions() {
    const channelOptions = document.querySelectorAll('.channel-option');

    channelOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            if (!this.classList.contains('channel-disabled') &&
                this.style.opacity !== '0.5' &&
                e.target.type !== 'checkbox') {
                const checkbox = this.querySelector('.channel-checkbox');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    updateCheckboxVisualState(checkbox);
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        });
    });
}

// ==================== MODERN RADIO BUTTON FUNCTIONALITY ====================

function setupModernRadios() {
    const invitationRadios = document.querySelectorAll('input[name="send_invitation"]');
    invitationRadios.forEach(radio => {
        updateRadioVisualState(radio);

        radio.addEventListener('change', function() {
            invitationRadios.forEach(r => updateRadioVisualState(r));
            toggleInvitationSection();
        });
    });

    const channelCheckboxes = document.querySelectorAll('.channel-checkbox');
    channelCheckboxes.forEach(checkbox => {
        updateCheckboxVisualState(checkbox);

        checkbox.addEventListener('change', function() {
            updateCheckboxVisualState(this);
        });

        const option = checkbox.closest('.modern-radio-option');
        if (option) {
            option.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox' && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        }
    });

    initializeAllVisualStates();
}

function updateRadioVisualState(radio) {
    if (!radio) return;
    const option = radio.closest('.modern-radio-option');
    if (!option) return;
    if (radio.checked) {
        option.classList.add('modern-radio-option--checked');
    } else {
        option.classList.remove('modern-radio-option--checked');
    }
}

function updateCheckboxVisualState(checkbox) {
    if (!checkbox) return;
    const option = checkbox.closest('.modern-radio-option');
    if (!option) return;
    if (checkbox.disabled) {
        option.classList.remove('modern-radio-option--checked');
        return;
    }
    if (checkbox.checked) {
        option.classList.add('modern-radio-option--checked');
    } else {
        option.classList.remove('modern-radio-option--checked');
    }
}

function initializeAllVisualStates() {
    document.querySelectorAll('input[name="send_invitation"]').forEach(radio => {
        updateRadioVisualState(radio);
    });

    document.querySelectorAll('.channel-checkbox').forEach(checkbox => {
        updateCheckboxVisualState(checkbox);
    });
}

// ==================== TENANT MANAGEMENT ====================

let newTenants = [];
let tenantsToDelete = [];

function toggleTenantInvitation() {
    const isRentedYes = document.getElementById('is_rented_yes');
    const tenantContainer = document.getElementById('tenant-invitation-container');
    const rentedWarning = document.getElementById('rented-warning');

    if (isRentedYes && isRentedYes.checked && tenantContainer) {
        tenantContainer.classList.remove('hidden');
        if (rentedWarning) rentedWarning.classList.add('hidden');

        const tenantRadios = document.querySelectorAll('input[name="is_rented"]');
        tenantRadios.forEach(radio => {
            updateRadioVisualState(radio);
        });
    } else if (tenantContainer) {
        if (hasExistingTenants()) {
            if (rentedWarning) rentedWarning.classList.remove('hidden');
        } else {
            tenantContainer.classList.add('hidden');
        }
    }
}

function hasExistingTenants() {
    const existingTenantsList = document.getElementById('existing-tenants-list');
    return existingTenantsList && existingTenantsList.children.length > 0;
}

function openTenantModal() {
    const modal = document.getElementById('tenant-modal');
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    const formsContainer = document.getElementById('tenant-forms-container');
    const tenantCountSelect = document.getElementById('tenant-count');

    if (formsContainer) formsContainer.innerHTML = '';
    if (tenantCountSelect) tenantCountSelect.value = '';
}

function closeTenantModal() {
    const modal = document.getElementById('tenant-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function generateTenantForms(count) {
    const container = document.getElementById('tenant-forms-container');
    if (!container) return;

    container.innerHTML = '';

    for (let i = 0; i < count; i++) {
        const existingTenant = newTenants[i];
        const defaultChannels = existingTenant?.channels || ['sms'];

        const tenantForm = document.createElement('div');
        tenantForm.className = 'border rounded-lg p-4';
        tenantForm.style.backgroundColor = 'var(--bg-secondary)';
        tenantForm.style.borderColor = 'var(--border-color)';

        tenantForm.innerHTML = `
            <div class="flex items-center justify-between mb-4 pb-2 border-b" style="border-color: var(--border-color);">
                <h4 class="font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-user mr-2"></i>Tenant ${i + 1}
                </h4>
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: var(--primary); color: white;">
                    Required
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Full Name *
                    </label>
                    <input type="text"
                           class="w-full p-2 border rounded tenant-name"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Kwame Osei"
                           data-index="${i}"
                           value="${escapeHtml(existingTenant?.name || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Phone Number *
                    </label>
                    <input type="text"
                           class="w-full p-2 border rounded tenant-phone"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., 024 123 4567"
                           data-index="${i}"
                           value="${escapeHtml(existingTenant?.phone || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Email Address
                    </label>
                    <input type="email"
                           class="w-full p-2 border rounded tenant-email"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., tenant@example.com"
                           data-index="${i}"
                           value="${escapeHtml(existingTenant?.email || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Gender
                    </label>
                    <select class="w-full p-2 border rounded tenant-gender"
                            style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                            data-index="${i}">
                        <option value="">Select Gender</option>
                        <option value="male" ${existingTenant?.gender === 'male' ? 'selected' : ''}>Male</option>
                        <option value="female" ${existingTenant?.gender === 'female' ? 'selected' : ''}>Female</option>
                        <option value="other" ${existingTenant?.gender === 'other' ? 'selected' : ''}>Other</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Invitation Channels *
                    </label>
                    <div class="flex space-x-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox"
                                   class="tenant-channel"
                                   value="sms"
                                   data-index="${i}"
                                   ${defaultChannels.includes('sms') ? 'checked' : ''}
                                   ${SMS_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">SMS${SMS_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox"
                                   class="tenant-channel"
                                   value="whatsapp"
                                   data-index="${i}"
                                   ${defaultChannels.includes('whatsapp') ? 'checked' : ''}
                                   ${WHATSAPP_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">WhatsApp${WHATSAPP_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox"
                                   class="tenant-channel"
                                   value="email"
                                   data-index="${i}"
                                   ${defaultChannels.includes('email') ? 'checked' : ''}
                                   ${EMAIL_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">Email${EMAIL_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Notes (Optional)
                </label>
                <textarea class="w-full p-2 border rounded tenant-notes"
                          style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                          rows="2"
                          placeholder="Any additional notes about this tenant..."
                          data-index="${i}">${escapeHtml(existingTenant?.notes || '')}</textarea>
            </div>
        `;

        container.appendChild(tenantForm);

        const phoneInput = tenantForm.querySelector('.tenant-phone');
        if (phoneInput) phoneInput.addEventListener('input', formatPhoneNumber);
    }
}

function saveTenants() {
    const tenantCount = parseInt(document.getElementById('tenant-count')?.value || 0);
    const nameInputs = document.querySelectorAll('.tenant-name');
    const phoneInputs = document.querySelectorAll('.tenant-phone');
    const emailInputs = document.querySelectorAll('.tenant-email');
    const genderSelects = document.querySelectorAll('.tenant-gender');
    const noteInputs = document.querySelectorAll('.tenant-notes');

    let validTenants = [];
    let hasErrors = false;

    for (let i = 0; i < tenantCount; i++) {
        const name = nameInputs[i]?.value.trim();
        const phone = phoneInputs[i]?.value.trim();
        const email = emailInputs[i]?.value.trim();
        const gender = genderSelects[i]?.value;
        const notes = noteInputs[i]?.value.trim();

        const channels = [];
        const channelCheckboxes = document.querySelectorAll(`.tenant-channel[data-index="${i}"]:checked`);
        channelCheckboxes.forEach(cb => {
            if (!cb.disabled && isChannelGloballyReady(cb.value)) {
                channels.push(cb.value);
            }
        });

        if (channels.length === 0) channels.push('sms');

        if (!name || !phone) {
            hasErrors = true;
            if (!name && nameInputs[i]) nameInputs[i].style.borderColor = '#ef4444';
            if (!phone && phoneInputs[i]) phoneInputs[i].style.borderColor = '#ef4444';
            continue;
        }

        const cleanPhone = phone.replace(/\D/g, '');
        if (cleanPhone.length < 10) {
            hasErrors = true;
            if (phoneInputs[i]) phoneInputs[i].style.borderColor = '#ef4444';
            continue;
        }

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            hasErrors = true;
            if (emailInputs[i]) emailInputs[i].style.borderColor = '#ef4444';
            continue;
        }

        if (nameInputs[i]) nameInputs[i].style.borderColor = '';
        if (phoneInputs[i]) phoneInputs[i].style.borderColor = '';
        if (emailInputs[i]) emailInputs[i].style.borderColor = '';

        validTenants.push({
            name,
            phone: cleanPhone,
            email,
            gender,
            channels,
            notes
        });
    }

    if (hasErrors) {
        alert('Please fill in all required fields (Name and Phone are required for each tenant)');
        return;
    }

    if (validTenants.length === 0) {
        alert('No valid tenant information provided');
        return;
    }

    newTenants.push(...validTenants);

    updateNewTenantsSummary();
    updateTenantHiddenInputs();
    closeTenantModal();
}

function updateNewTenantsSummary() {
    const summaryContainer = document.getElementById('tenants-summary');
    const tenantsList = document.getElementById('tenants-list');
    const tenantsCount = document.getElementById('tenants-count');

    if (!summaryContainer) return;

    if (newTenants.length === 0) {
        summaryContainer.classList.add('hidden');
        return;
    }

    if (tenantsList) tenantsList.innerHTML = '';

    newTenants.forEach((tenant, index) => {
        const tenantItem = document.createElement('div');
        tenantItem.className = 'flex items-center justify-between p-3 border rounded';
        tenantItem.style.backgroundColor = 'var(--bg-secondary)';
        tenantItem.style.borderColor = 'var(--border-color)';

        tenantItem.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-user text-blue-600"></i>
                </div>
                <div>
                    <div class="font-medium" style="color: var(--text-primary);">${escapeHtml(tenant.name)}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">${escapeHtml(tenant.phone)}</div>
                    ${tenant.email ? `<div class="text-xs" style="color: var(--text-secondary);">${escapeHtml(tenant.email)}</div>` : ''}
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">
                    ${tenant.channels.join(', ')}
                </span>
                <button type="button" onclick="removeNewTenant(${index})" class="text-red-500 hover:text-red-700 p-1">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        if (tenantsList) tenantsList.appendChild(tenantItem);
    });

    if (tenantsCount) tenantsCount.textContent = `${newTenants.length} new tenant(s) to add`;
    summaryContainer.classList.remove('hidden');
}

function removeNewTenant(index) {
    if (confirm('Are you sure you want to remove this tenant?')) {
        newTenants.splice(index, 1);
        updateNewTenantsSummary();
        updateTenantHiddenInputs();

        if (newTenants.length === 0) {
            const summaryContainer = document.getElementById('tenants-summary');
            if (summaryContainer) summaryContainer.classList.add('hidden');
        }
    }
}

function removeExistingTenant(tenantId) {
    if (confirm('Are you sure you want to remove this tenant?')) {
        tenantsToDelete.push(tenantId);
        const tenantItem = document.querySelector(`.existing-tenant-item[data-tenant-id="${tenantId}"]`);
        if (tenantItem) {
            tenantItem.remove();
        }
        updateTenantHiddenInputs();
    }
}

function updateTenantHiddenInputs() {
    const container = document.getElementById('tenant-hidden-inputs');
    if (!container) return;

    container.innerHTML = '';

    tenantsToDelete.forEach(tenantId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_tenants[]';
        input.value = tenantId;
        container.appendChild(input);
    });

    newTenants.forEach((tenant, index) => {
        const tenantData = document.createElement('div');
        let channelsHTML = '';
        tenant.channels.forEach((channel, channelIndex) => {
            channelsHTML += `<input type="hidden" name="new_tenants[${index}][channels][${channelIndex}]" value="${escapeHtml(channel)}">`;
        });

        tenantData.innerHTML = `
            <input type="hidden" name="new_tenants[${index}][name]" value="${escapeHtml(tenant.name)}">
            <input type="hidden" name="new_tenants[${index}][phone]" value="${tenant.phone}">
            <input type="hidden" name="new_tenants[${index}][email]" value="${tenant.email || ''}">
            <input type="hidden" name="new_tenants[${index}][gender]" value="${tenant.gender || ''}">
            ${channelsHTML}
            <input type="hidden" name="new_tenants[${index}][notes]" value="${escapeHtml(tenant.notes || '')}">
        `;
        container.appendChild(tenantData);
    });
}

// ==================== NAMING PATTERN & LOCATION FUNCTIONS ====================

function updateNamingPreview(planOption, nextAvailableName = null) {
    if (!planOption || !planOption.value) {
        const namingPreviewContainer = document.getElementById('naming-preview-container');
        const namingPatternInfo = document.getElementById('naming-pattern-info');
        const patternStatus = document.getElementById('pattern-status');

        if (namingPreviewContainer) namingPreviewContainer.classList.add('hidden');
        if (namingPatternInfo) namingPatternInfo.textContent = 'Select a registration plan to see naming pattern';
        if (patternStatus) patternStatus.classList.add('hidden');
        return;
    }

    const pattern = planOption.dataset.pattern;
    const startingPoint = planOption.dataset.startingPoint;
    const nextAvailable = nextAvailableName || planOption.dataset.nextAvailable;
    const sequenceType = planOption.dataset.sequenceType;
    const planStatus = planOption.dataset.planStatus;
    const isGlobalSequence = planOption.dataset.isGlobalSequence === '1';

    const patternDisplay = document.getElementById('pattern-display');
    const sequenceInfo = document.getElementById('sequence-info');
    const nextAvailableInfo = document.getElementById('next-available-info');
    const startingPointInfo = document.getElementById('starting-point-info');
    const planStatusInfo = document.getElementById('plan-status-info');
    const namingPreviewContainer = document.getElementById('naming-preview-container');
    const namingPatternInfo = document.getElementById('naming-pattern-info');
    const patternStatus = document.getElementById('pattern-status');
    const globalSequenceStatus = document.getElementById('global-sequence-status');
    const globalSequenceRow = document.getElementById('global-sequence-row');

    if (patternDisplay) patternDisplay.textContent = pattern;
    if (sequenceInfo) sequenceInfo.textContent = sequenceType.charAt(0).toUpperCase() + sequenceType.slice(1).replace('_', ' ');
    if (nextAvailableInfo) nextAvailableInfo.textContent = nextAvailable;
    if (startingPointInfo) startingPointInfo.textContent = startingPoint;
    if (planStatusInfo) planStatusInfo.textContent = planStatus.charAt(0).toUpperCase() + planStatus.slice(1).replace('_', ' ');

    if (globalSequenceStatus) {
        globalSequenceStatus.textContent = isGlobalSequence ? 'Yes - Part of global sequence' : 'No - Plan-specific sequence';
    }
    if (globalSequenceRow) {
        globalSequenceRow.classList.toggle('hidden', !isGlobalSequence);
    }

    if (namingPreviewContainer) namingPreviewContainer.classList.remove('hidden');
    if (namingPatternInfo) namingPatternInfo.textContent = `Using pattern: ${pattern}`;
    if (patternStatus) patternStatus.classList.remove('hidden');
}

function updatePlanProgress(planOption) {
    const planProgressContainer = document.getElementById('plan-progress-container');
    const progressBar = document.getElementById('progress-bar');
    const progressPercentage = document.getElementById('progress-percentage');
    const progressText = document.getElementById('progress-text');
    const completionStatus = document.getElementById('completion-status');

    if (!planOption || !planOption.value) {
        if (planProgressContainer) planProgressContainer.classList.add('hidden');
        return;
    }

    const estimatedHouses = parseInt(planOption.dataset.estimatedHouses) || 0;
    const registeredHouses = parseInt(planOption.dataset.registeredHouses) || 0;
    const progress = estimatedHouses > 0 ? Math.round((registeredHouses / estimatedHouses) * 100) : 0;

    if (progressBar) progressBar.style.width = `${progress}%`;
    if (progressPercentage) progressPercentage.textContent = `${progress}%`;
    if (progressText) progressText.textContent = `Registered: ${registeredHouses} / ${estimatedHouses} houses`;
    if (completionStatus) completionStatus.textContent = progress >= 100 ? 'Complete' : 'Incomplete';

    if (progressBar) {
        if (progress >= 100) {
            progressBar.classList.remove('bg-blue-600', 'bg-yellow-600');
            progressBar.classList.add('bg-green-600');
        } else if (progress >= 80) {
            progressBar.classList.remove('bg-blue-600', 'bg-green-600');
            progressBar.classList.add('bg-yellow-600');
        } else {
            progressBar.classList.remove('bg-yellow-600', 'bg-green-600');
            progressBar.classList.add('bg-blue-600');
        }
    }

    if (planProgressContainer) planProgressContainer.classList.remove('hidden');
}

function updateZoneAndSection(planOption) {
    const zoneDisplay = document.getElementById('zone-display');
    const sectionDisplay = document.getElementById('section-display');
    const zoneInput = document.getElementById('zone');
    const sectionInput = document.getElementById('section');

    if (!planOption || !planOption.value) {
        if (zoneDisplay) zoneDisplay.textContent = 'Select a registration plan';
        if (sectionDisplay) sectionDisplay.textContent = 'Select a registration plan';
        return;
    }

    const zoneValue = planOption.dataset.zone;
    const sectionValue = planOption.dataset.section;

    if (zoneDisplay) zoneDisplay.textContent = zoneValue || 'Not specified';
    if (sectionDisplay) sectionDisplay.textContent = sectionValue || 'Not specified';
    if (zoneInput) zoneInput.value = zoneValue || '';
    if (sectionInput) sectionInput.value = sectionValue || '';
}

function updateSequenceTypeInfo(sequenceType) {
    const sequenceTypeInfo = document.getElementById('sequence-type-info');
    const sequenceTypeText = document.getElementById('sequence-type-text');

    if (!sequenceType) {
        if (sequenceTypeInfo) sequenceTypeInfo.classList.add('hidden');
        return;
    }

    let sequenceDescription = '';
    switch (sequenceType) {
        case 'sequential':
            sequenceDescription = 'Sequential numbering (1, 2, 3, 4, ...)';
            break;
        case 'even_only':
            sequenceDescription = 'Even numbers only (2, 4, 6, 8, ...)';
            break;
        case 'odd_only':
            sequenceDescription = 'Odd numbers only (1, 3, 5, 7, ...)';
            break;
        default:
            sequenceDescription = 'Custom sequence';
    }

    if (sequenceTypeText) sequenceTypeText.textContent = sequenceDescription;
    if (sequenceTypeInfo) sequenceTypeInfo.classList.remove('hidden');
}

// ==================== FORM VALIDATION ====================

function debugFormSubmission() {
    const form = document.getElementById('property-form');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        console.log('Form submitted. New tenants:', newTenants);
        console.log('Tenants to delete:', tenantsToDelete);
        console.log('Photos to delete:', photosToDelete);
        console.log('New photos:', selectedPhotos.length);
        return true;
    });
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete this property? This action cannot be undone and will remove all associated data including photos and tenant records.')) {
        document.getElementById('delete-form').submit();
    }
}

// ==================== DOM CONTENT LOADED ====================

document.addEventListener('DOMContentLoaded', function() {
    setupPhotoUpload();
    setupExistingPhotoHandlers();
    initializePhoneNumbers();

    const addPhoneBtn = document.getElementById('add-phone-btn');
    if (addPhoneBtn) {
        addPhoneBtn.addEventListener('click', function() {
            addPhoneNumber();
        });
    }

    document.querySelectorAll('.phone-input').forEach(input => {
        input.addEventListener('input', formatPhoneNumber);
    });

    document.querySelectorAll('.remove-phone-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            removePhoneNumber(this);
        });
    });

    updateRemoveButtons();

    initializePropertyTypeSelection();

    const customInput = document.getElementById('custom_property_type');
    if (customInput) {
        customInput.addEventListener('input', updateCustomTypeCharCounter);
        updateCustomTypeCharCounter();
    }

    setupModernRadios();
    toggleInvitationSection();
    setupChannelOptions();

    const tenantRadios = document.querySelectorAll('input[name="is_rented"]');
    tenantRadios.forEach(radio => {
        updateRadioVisualState(radio);
        radio.addEventListener('change', function() {
            tenantRadios.forEach(r => updateRadioVisualState(r));
            toggleTenantInvitation();
        });
    });

    const addTenantsBtn = document.getElementById('add-tenants-btn');
    if (addTenantsBtn) {
        addTenantsBtn.addEventListener('click', openTenantModal);
    }

    const tenantCountSelect = document.getElementById('tenant-count');
    if (tenantCountSelect) {
        tenantCountSelect.addEventListener('change', function() {
            const count = parseInt(this.value);
            if (count > 0) {
                generateTenantForms(count);
            } else {
                const formsContainer = document.getElementById('tenant-forms-container');
                if (formsContainer) formsContainer.innerHTML = '';
            }
        });
    }

    const sendInvitationYes = document.getElementById('send_invitation_yes');
    if (sendInvitationYes && sendInvitationYes.checked) {
        setTimeout(() => {
            checkAvailableChannels();
            updateInvitationPreview();
        }, 100);
    }

    const landlordIdSelect = document.getElementById('landlord_id');
    const landlordNameInput = document.getElementById('landlord_name');
    const landlordEmailInput = document.getElementById('landlord_email');

    if (landlordIdSelect) {
        landlordIdSelect.addEventListener('change', function() {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                checkAvailableChannels();
                updateInvitationPreview();
            }
        });
    }

    if (landlordNameInput) {
        landlordNameInput.addEventListener('input', function() {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                checkAvailableChannels();
                updateInvitationPreview();
            }
        });
    }

    if (landlordEmailInput) {
        landlordEmailInput.addEventListener('input', function() {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                checkAvailableChannels();
            }
        });
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList && e.target.classList.contains('phone-input')) {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                checkAvailableChannels();
            }
        }
    });

    ['property_name', 'street_name', 'registration_pattern'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
                if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                    updateInvitationPreview();
                }
            });
        }
    });

    const registrationPlanSelect = document.getElementById('registration_plan_id');
    const manualZoneInput = document.getElementById('manual_zone');

    if (registrationPlanSelect) {
        registrationPlanSelect.addEventListener('change', function() {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                setTimeout(updateInvitationPreview, 100);
            }

            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const pattern = selectedOption.dataset.nextAvailable || selectedOption.dataset.startingPoint;
                const registrationPatternInput = document.getElementById('registration_pattern');
                const refreshPatternButton = document.getElementById('refresh-pattern');

                if (registrationPatternInput) registrationPatternInput.value = pattern || '';
                if (refreshPatternButton) refreshPatternButton.classList.remove('hidden');

                updateNamingPreview(selectedOption);
                updatePlanProgress(selectedOption);
                updateZoneAndSection(selectedOption);
                updateSequenceTypeInfo(selectedOption.dataset.sequenceType);
            } else {
                const registrationPatternInput = document.getElementById('registration_pattern');
                const refreshPatternButton = document.getElementById('refresh-pattern');

                if (refreshPatternButton) refreshPatternButton.classList.add('hidden');
                updateZoneAndSection(null);
            }
        });

        if (registrationPlanSelect.value) {
            const selectedOption = registrationPlanSelect.options[registrationPlanSelect.selectedIndex];
            const pattern = selectedOption?.dataset?.nextAvailable || selectedOption?.dataset?.startingPoint || '';
            const registrationPatternInput = document.getElementById('registration_pattern');
            if (registrationPatternInput && pattern) registrationPatternInput.value = pattern;
            updateNamingPreview(selectedOption);
            updatePlanProgress(selectedOption);
            updateZoneAndSection(selectedOption);
            updateSequenceTypeInfo(selectedOption?.dataset?.sequenceType);
        }
    }

    if (manualZoneInput) {
        manualZoneInput.addEventListener('input', function() {
            const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
            if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                updateInvitationPreview();
            }
        });
    }

    const toggleLocationOverride = document.getElementById('toggle-location-override');
    const autoFilledFields = document.getElementById('auto-filled-fields');
    const manualLocationContainer = document.getElementById('manual-location-container');
    const zoneInput = document.getElementById('zone');
    const sectionInput = document.getElementById('section');
    const manualSectionInput = document.getElementById('manual_section');

    if (toggleLocationOverride) {
        toggleLocationOverride.addEventListener('click', function() {
            const isManual = manualLocationContainer?.classList.contains('hidden');

            if (isManual && autoFilledFields && manualLocationContainer) {
                autoFilledFields.classList.add('hidden');
                manualLocationContainer.classList.remove('hidden');
                this.innerHTML = '<i class="fas fa-undo mr-1"></i>Use Plan Location';

                if (manualZoneInput && zoneInput) manualZoneInput.value = zoneInput.value;
                if (manualSectionInput && sectionInput) manualSectionInput.value = sectionInput.value;
                if (manualZoneInput) manualZoneInput.setAttribute('required', 'required');
            } else if (autoFilledFields && manualLocationContainer) {
                autoFilledFields.classList.remove('hidden');
                manualLocationContainer.classList.add('hidden');
                this.innerHTML = '<i class="fas fa-edit mr-1"></i>Override';

                if (manualZoneInput) manualZoneInput.value = '';
                if (manualSectionInput) manualSectionInput.value = '';
                if (manualZoneInput) manualZoneInput.removeAttribute('required');
            }
        });
    }

    const landlordIdSelectForToggle = document.getElementById('landlord_id');
    const landlordNameInputForToggle = document.getElementById('landlord_name');
    const landlordEmailInputForToggle = document.getElementById('landlord_email');

    if (landlordIdSelectForToggle) {
        landlordIdSelectForToggle.addEventListener('change', function() {
            if (this.value) {
                if (landlordNameInputForToggle) landlordNameInputForToggle.value = '';
                if (landlordEmailInputForToggle) landlordEmailInputForToggle.value = '';

                const phoneGroups = document.querySelectorAll('.phone-number-group');
                phoneGroups.forEach((group, index) => {
                    if (index > 0) group.remove();
                });

                const firstPhoneInput = document.querySelector('.phone-input');
                if (firstPhoneInput) firstPhoneInput.value = '';

                updateRemoveButtons();
                if (landlordNameInputForToggle) landlordNameInputForToggle.removeAttribute('required');

                const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
                if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                    checkAvailableChannels();
                    updateInvitationPreview();
                }
            }
        });
    }

    if (landlordNameInputForToggle) {
        landlordNameInputForToggle.addEventListener('input', function() {
            if (this.value) {
                if (landlordIdSelectForToggle) landlordIdSelectForToggle.value = '';
                this.setAttribute('required', 'required');

                const sendInvitationYesLocal = document.getElementById('send_invitation_yes');
                if (sendInvitationYesLocal && sendInvitationYesLocal.checked) {
                    checkAvailableChannels();
                    updateInvitationPreview();
                }
            } else {
                this.removeAttribute('required');
            }
        });
    }

    // Form validation
    const form = document.getElementById('property-form');
    const submitButton = document.getElementById('submit-button');
    const propertyTypeSelect = document.getElementById('property_type_id');
    const customPropertyTypeInput = document.getElementById('custom_property_type');
    const registrationPatternInput = document.getElementById('registration_pattern');
    const propertyNameInput = document.getElementById('property_name');

    if (form) {
        form.addEventListener('submit', function(e) {
            const landlordId = landlordIdSelectForToggle?.value;
            const landlordName = landlordNameInputForToggle?.value;
            const propertyTypeId = propertyTypeSelect?.value;
            const customPropertyType = customPropertyTypeInput?.value;
            const sendInvitation = document.getElementById('send_invitation_yes')?.checked;
            const invitationChannels = Array.from(document.querySelectorAll('input[name="invitation_channels[]"]:checked'))
                .filter(cb => !cb.disabled);
            const isRented = document.getElementById('is_rented_yes')?.checked;

            if (!propertyTypeId) {
                e.preventDefault();
                alert('Please select a property type.');
                return false;
            }

            const selectedCard = document.querySelector('.property-type-card[style*="border-color"]');
            if (selectedCard && selectedCard.getAttribute('data-is-custom') === 'true' && !customPropertyType) {
                e.preventDefault();
                alert('Please enter a custom property type name.');
                return false;
            }

            if (!landlordId && !landlordName) {
                e.preventDefault();
                alert('Please either select an existing landlord or provide name for a new landlord.');
                return false;
            }

            if (!landlordId && landlordName && !validatePhoneNumbers()) {
                e.preventDefault();
                alert('Please enter at least one valid phone number (at least 10 digits).');
                return false;
            }

            if (!registrationPatternInput?.value) {
                e.preventDefault();
                alert('Please select a registration plan to generate a registration pattern.');
                return false;
            }

            if (!propertyNameInput?.value) {
                e.preventDefault();
                alert('Please enter a property name.');
                return false;
            }

            const manualLocationContainerCheck = document.getElementById('manual-location-container');
            const zoneInputCheck = document.getElementById('zone');
            const manualZoneInputCheck = document.getElementById('manual_zone');

            if (manualLocationContainerCheck?.classList.contains('hidden')) {
                if (!zoneInputCheck?.value) {
                    e.preventDefault();
                    alert('Please select a registration plan to auto-fill the zone information.');
                    return false;
                }
            } else {
                if (!manualZoneInputCheck?.value) {
                    e.preventDefault();
                    alert('Please enter a zone name.');
                    return false;
                }
            }

            if (sendInvitation && invitationChannels.length === 0) {
                e.preventDefault();
                alert('Please select at least one invitation channel for the landlord.');
                return false;
            }

            for (const cb of invitationChannels) {
                if (!isChannelGloballyReady(cb.value)) {
                    e.preventDefault();
                    alert(`${cb.value} service is not available. Please select another channel.`);
                    return false;
                }
            }

            if (isRented) {
                if (newTenants.length === 0 && !hasExistingTenants()) {
                    e.preventDefault();
                    alert('Please add at least one tenant for this rented property.');
                    return false;
                }

                for (let i = 0; i < newTenants.length; i++) {
                    if (!newTenants[i].name || !newTenants[i].phone) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1} is missing required information (name and phone).`);
                        return false;
                    }
                    if (newTenants[i].phone.replace(/\D/g, '').length < 10) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1} has an invalid phone number.`);
                        return false;
                    }
                    if (!newTenants[i].channels || newTenants[i].channels.length === 0) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1}: Please select at least one invitation channel.`);
                        return false;
                    }
                    for (const ch of newTenants[i].channels) {
                        if (!isChannelGloballyReady(ch)) {
                            e.preventDefault();
                            alert(`Tenant ${i + 1}: ${ch} service is not available. Please select another channel.`);
                            return false;
                        }
                    }
                }
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving Changes...';
            }

            return true;
        });
    }

    debugFormSubmission();

    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }

    const errorMessageAlert = document.querySelector('.bg-red-100');
    if (errorMessageAlert) {
        setTimeout(() => {
            errorMessageAlert.style.display = 'none';
        }, 8000);
    }
});
</script>
@endsection

{{-- ============================================================ --}}
{{-- STYLES — moved OUT of @section('scripts') into @push('styles') --}}
{{-- so they render in <head> via the layout's @stack('styles'),    --}}
{{-- and don't leak into the header/sidebar.                        --}}
{{-- The global `.hidden` rule has been scoped to only the elements --}}
{{-- this page controls, and focus/transition rules are scoped to   --}}
{{-- #property-form so they don't affect the header's search input. --}}
{{-- ============================================================ --}}
@push('styles')
<style>
/* ==================== PHOTO UPLOAD STYLES ==================== */

.photo-upload-area {
    transition: all 0.3s ease;
}

.photo-upload-area:hover {
    border-color: var(--primary) !important;
    background-color: rgba(114, 103, 240, 0.05) !important;
}

.photo-preview-item {
    transition: all 0.3s ease;
    position: relative;
}

.photo-preview-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.photo-preview-item .make-primary-btn,
.photo-preview-item .remove-photo-btn,
.existing-photo-item .make-primary-existing-btn,
.existing-photo-item .remove-existing-photo-btn {
    transition: all 0.2s ease;
    cursor: pointer;
}

.photo-preview-item .make-primary-btn:hover:not(:disabled),
.photo-preview-item .remove-photo-btn:hover,
.existing-photo-item .make-primary-existing-btn:hover,
.existing-photo-item .remove-existing-photo-btn:hover {
    transform: scale(1.05);
}

/* ==================== PROPERTY TYPE CARD STYLES ==================== */

.property-type-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.property-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.property-type-card.active {
    border-color: var(--primary);
    background-color: var(--primary) + '10';
}

/* ==================== MODERN RADIO BUTTON STYLES ==================== */

.modern-radio-option {
    position: relative;
    transition: all 0.3s ease;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background-color: var(--bg-secondary);
    cursor: pointer;
    overflow: hidden;
}

.modern-radio-option:hover:not(.channel-disabled):not(.modern-radio-option--disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

.modern-radio-option--checked {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(114, 103, 240, 0.1), rgba(114, 103, 240, 0.05)) !important;
    box-shadow: 0 8px 25px rgba(114, 103, 240, 0.15) !important;
    transform: translateY(-2px);
}

/* Disabled channel — greyed out, hatched, non-interactive */
.channel-disabled,
.modern-radio-option--disabled {
    cursor: not-allowed !important;
    opacity: 0.55;
    border-style: dashed !important;
    background-color: rgba(var(--secondary-rgb, 148, 163, 184), 0.08) !important;
}

.channel-disabled::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(
        45deg,
        rgba(0, 0, 0, 0.03),
        rgba(0, 0, 0, 0.03) 10px,
        transparent 10px,
        transparent 20px
    );
    pointer-events: none;
    border-radius: inherit;
}

.channel-disabled input,
.modern-radio-option--disabled input {
    cursor: not-allowed;
}

.modern-radio-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.modern-radio-label {
    display: block;
    cursor: pointer;
    padding: 0;
    margin: 0;
}

.modern-radio-content {
    display: flex;
    align-items: center;
    padding: 20px;
    position: relative;
}

.modern-radio-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    font-size: 20px;
    transition: all 0.3s ease;
    background: var(--bg-tertiary);
}

.modern-radio-option--checked .modern-radio-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    color: white !important;
    transform: scale(1.1);
}

.modern-radio-text {
    flex: 1;
}

.modern-radio-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.modern-radio-description {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.modern-radio-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    background: white;
}

.modern-radio-option--checked .modern-radio-check {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: white !important;
    transform: scale(1.1);
}

.modern-radio-check i {
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-radio-option--checked .modern-radio-check i {
    opacity: 1 !important;
}

/* Channel-specific icon colors */
.modern-radio-option[data-channel="sms"] .modern-radio-icon,
.modern-radio-option[data-channel="whatsapp"] .modern-radio-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
}

.modern-radio-option[data-channel="email"] .modern-radio-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.modern-radio-option--checked[data-channel="sms"] .modern-radio-icon,
.modern-radio-option--checked[data-channel="whatsapp"] .modern-radio-icon {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    color: white !important;
}

.modern-radio-option--checked[data-channel="email"] .modern-radio-icon {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8) !important;
    color: white !important;
}

.modern-radio-option:first-of-type .modern-radio-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
}

.modern-radio-option:last-of-type .modern-radio-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
}

.modern-radio-option--checked:first-of-type .modern-radio-icon {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    color: white !important;
}

.modern-radio-option--checked:last-of-type .modern-radio-icon {
    background: linear-gradient(135deg, #EF4444, #DC2626) !important;
    color: white !important;
}

/* ==================== CUSTOM CONTAINER STYLES ==================== */

#custom_property_type_container {
    border-left: 4px solid #6B7280;
    transition: all 0.3s ease-in-out;
    animation: slideDown 0.3s ease-out;
}

#selected-property-type {
    border-left: 4px solid;
    animation: slideDown 0.3s ease-out;
}

/* ==================== PHONE NUMBER STYLES ==================== */

.phone-number-group {
    transition: all 0.3s ease;
}

.remove-phone-btn {
    transition: all 0.2s ease;
}

.remove-phone-btn:hover {
    background-color: rgba(239, 68, 68, 0.1);
    border-radius: 0.25rem;
}

/* ==================== SECTION BORDER STYLES ==================== */

#landlord-invitation-section {
    border-left: 4px solid #8B5CF6;
}

#invitation-channels-container {
    transition: all 0.3s ease-in-out;
}

#tenant-invitation-section {
    border-left: 4px solid #10B981;
}

#tenant-invitation-container {
    transition: all 0.3s ease-in-out;
}

#available-channels-info {
    border-left: 4px solid #3B82F6;
}

#no-channels-warning {
    border-left: 4px solid #F59E0B;
}

#invitation-preview {
    border-left: 4px solid #3B82F6;
    background-color: rgba(var(--info-rgb), 0.1) !important;
    border-color: rgba(var(--info-rgb), 0.3) !important;
}

#naming-preview-container {
    border-left: 4px solid var(--primary);
}

#global-sequence-info {
    border-left: 4px solid #3b82f6;
}

/* ==================== ANIMATIONS ==================== */

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

/* ==================== PROGRESS BAR STYLES ==================== */

#progress-bar {
    transition: width 0.5s ease-in-out;
}

#custom-type-chars {
    transition: color 0.3s ease;
}

/* ==================== BUTTON STYLES ==================== */

.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
}

.btn-primary:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.btn-secondary:hover {
    background-color: var(--bg-tertiary);
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
}

/* ==================== CARD STYLES ==================== */

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
}

/* ==================== SECTION HEADER ==================== */

h3 {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary);
}

/* ==================== MESSAGE STYLES ==================== */

.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

/* ==================== PLAN STATUS ALERT STYLES ==================== */

#plan-status-alert .bg-yellow-50 {
    background-color: rgba(254, 252, 232, 0.9);
    border-color: rgba(250, 204, 21, 0.3);
}

#plan-status-alert .bg-red-50 {
    background-color: rgba(254, 242, 242, 0.9);
    border-color: rgba(248, 113, 113, 0.3);
}

#plan-status-alert .bg-blue-50 {
    background-color: rgba(239, 246, 255, 0.9);
    border-color: rgba(147, 197, 253, 0.3);
}

#plan-status-alert .bg-green-50 {
    background-color: rgba(240, 253, 244, 0.9);
    border-color: rgba(134, 239, 172, 0.3);
}

/* ==================== PATTERN STYLES ==================== */

#registration_pattern {
    font-weight: 600;
    background-color: var(--bg-tertiary) !important;
    letter-spacing: 0.5px;
}

#refresh-pattern {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.75rem;
    transition: color 0.2s;
}

#refresh-pattern:hover {
    color: var(--primary);
}

#pattern-status {
    transition: all 0.3s ease;
}

#pattern-validation {
    transition: all 0.3s ease;
}

#next-pattern-preview {
    color: var(--text-secondary);
    font-style: italic;
}

#next-pattern-text {
    color: var(--text-primary);
    font-weight: 600;
}

#global-sequence-badge {
    font-size: 0.7rem;
    font-weight: 600;
}

/* ==================== FOCUS STYLES ==================== */

.property-type-card:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.modern-radio-option:focus-within {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Scoped to this form so it doesn't affect the header's search input */
#property-form input:focus,
#property-form select:focus,
#property-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* ==================== RESPONSIVE DESIGN ==================== */

@media (max-width: 768px) {
    .modern-radio-content {
        padding: 16px;
    }

    .modern-radio-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
        margin-right: 12px;
    }

    .modern-radio-title {
        font-size: 14px;
    }

    .modern-radio-description {
        font-size: 12px;
    }
}

@media (max-width: 640px) {
    #property-type-cards {
        grid-template-columns: 1fr;
    }

    .modern-radio-option {
        margin-bottom: 0.5rem;
    }
}

/* ==================== PRINT STYLES ==================== */

@media print {
    .btn-primary, .btn-secondary, .btn-danger, #refresh-pattern, #toggle-location-override, #add-phone-btn {
        display: none !important;
    }

    .card {
        box-shadow: none;
        border: 1px solid #000;
    }

    .modern-radio-option {
        border: 1px solid #000 !important;
    }
}

/* ==================== SCOPED .hidden OVERRIDE ==================== */
/* The previous GLOBAL `.hidden { display: none; }` rule was leaking
 * out of this view and hiding the header's search bar (and any other
 * .hidden-using component). Now scoped to only the IDs this page
 * controls. Tailwind's own .hidden still applies elsewhere. */

#custom_property_type_container.hidden,
#selected-property-type.hidden,
#invitation-channels-container.hidden,
#invitation-preview.hidden,
#available-channels-info.hidden,
#no-channels-warning.hidden,
#tenant-invitation-container.hidden,
#tenants-summary.hidden,
#tenant-modal.hidden,
#naming-preview-container.hidden,
#plan-progress-container.hidden,
#plan-status-alert.hidden,
#global-sequence-info.hidden,
#manual-location-container.hidden,
#auto-filled-fields.hidden,
#pattern-status.hidden,
#pattern-validation.hidden,
#pattern-error.hidden,
#pattern-loading.hidden,
#sequence-type-info.hidden,
#next-pattern-preview.hidden,
#delete-form.hidden,
#global-sequence-row.hidden,
#global-sequence-badge.hidden,
#refresh-pattern.hidden,
#remove-phone-btn.hidden,
.remove-phone-btn.hidden,
.phone-number-group.hidden,
#photo-preview-grid.hidden,
#existing-photos-grid.hidden,
.existing-photo-item.hidden,
.photo-preview-item.hidden {
    display: none !important;
}

/* ==================== UTILITY CLASSES ==================== */

.form-group {
    margin-bottom: 1rem;
}

.required::after {
    content: " *";
    color: var(--danger);
}

/* Scoped transition rule so we don't affect the header's elements */
#property-form input,
#property-form select,
#property-form textarea,
#property-form button,
#property-form a,
.property-type-card,
.modern-radio-option {
    transition: all 0.2s ease-in-out;
}
</style>
@endpush