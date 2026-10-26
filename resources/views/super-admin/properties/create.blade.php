@extends('layouts.app')

@section('title', 'Add New Property')

@php
    /*
     |--------------------------------------------------------------------------
     | Communication-channel availability
     |--------------------------------------------------------------------------
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

    $landlordInvitationResult = session('invitation_result');
    $tenantInvitationResult   = session('tenant_invitation_result');

    // ============================================================
    // ✅ Duplicate-prevention payload
    // ============================================================
    // The controller may deliver this several ways:
    //   1. session('duplicate_property')           (normal flash)
    //   2. session()->get('duplicate_property')    (retrieved flash)
    //   3. $errors->first('duplicate_property')    (if it was ever validated)
    //   4. legacy: session('error') containing "duplicate"
    //
    // We try each, normalize to an array, and only render the banner
    // if we actually have something meaningful to show.
    $duplicateProperty = session('duplicate_property')
        ?? session()->get('duplicate_property')
        ?? null;

    // Defensive: if it came back as a JSON string, decode it.
    if (is_string($duplicateProperty) && $duplicateProperty !== '') {
        $decoded = json_decode($duplicateProperty, true);
        if (is_array($decoded)) {
            $duplicateProperty = $decoded;
        }
    }

    // If nothing landed in session, look for it in the validation error bag.
    if (empty($duplicateProperty) && isset($errors) && $errors->has('duplicate_property')) {
        $raw = $errors->first('duplicate_property');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $duplicateProperty = $decoded;
        }
    }

    $similarProperties = session('similar_properties')
        ?? session()->get('similar_properties')
        ?? [];
    if (!is_array($similarProperties)) {
        $similarProperties = [];
    }

    // Normalize: ensure `id` + at least a name exist before rendering.
    $hasDuplicateBanner = is_array($duplicateProperty)
        && (!empty($duplicateProperty['id']) || !empty($duplicateProperty['property_name']));

    // Fallback: if the controller only flashed a plain error string that
    // mentions "duplicate", show a lightweight banner so the user isn't
    // left wondering why the save failed.
    $plainDuplicateError = null;
    if (!$hasDuplicateBanner) {
        $err = session('error');
        if (is_string($err) && $err !== '' && stripos($err, 'duplicate') !== false) {
            $plainDuplicateError = $err;
            $hasDuplicateBanner  = true;
        }
    }

    // ============================================================
    // ✅ Google Maps key for the coordinate picker
    // ============================================================
    $googleMapsKey = $googleMapsKey
        ?? config('services.google_maps.browser_key')
        ?? config('services.google_maps.key');

    // ============================================================
    // ✅ Region list for the region picker
    // ============================================================
    if (!isset($regions) || !is_array($regions)) {
        $regions = config('ghana.regions', [
            'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern',
            'Greater Accra', 'North East', 'Northern', 'Oti', 'Savannah',
            'Upper East', 'Upper West', 'Volta', 'Western', 'Western North',
        ]);
    }

    // ============================================================
    // ✅ Landlord mode — decides which block is visible on first paint
    // ============================================================
    $oldLandlordPhones = old('landlord_phones', []);
    if (!is_array($oldLandlordPhones)) {
        $oldLandlordPhones = [];
    }
    $oldLandlordPhones = array_values(array_filter($oldLandlordPhones, fn ($v) => $v !== null && $v !== ''));

    $initialLandlordMode = 'choose';
    if (!empty(old('landlord_id'))) {
        $initialLandlordMode = 'existing';
    } elseif (!empty($oldLandlordPhones) || old('landlord_name') || old('landlord_email')) {
        $initialLandlordMode = 'new';
    }
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                Add New Property
            </h2>
            <div>
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
    @if(session('error') && !$hasDuplicateBanner)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- ✅ DUPLICATE PROPERTY BANNER                                 --}}
    {{-- Renders whenever PropertyController::store() redirects back   --}}
    {{-- with `duplicate_property` + `similar_properties` in session.  --}}
    {{-- Falls back to a lightweight banner if only an `error` string  --}}
    {{-- mentioning "duplicate" was flashed.                           --}}
    {{-- ============================================================ --}}
    @if($hasDuplicateBanner)
        <div id="duplicate-property-banner"
             class="border-2 rounded-lg p-5 mb-4"
             style="background-color: rgba(var(--danger-rgb, 239, 68, 68), 0.08); border-color: var(--danger, #ef4444);"
             role="alert">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger, #ef4444);"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-semibold mb-1" style="color: var(--text-primary);">
                        Duplicate property prevented
                    </h3>

                    @if($plainDuplicateError)
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            {{ $plainDuplicateError }}
                        </p>
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            The new record was <strong>not</strong> created. Your previously-entered
                            form data has been restored below.
                        </p>
                    @else
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            A property matching this landlord already exists. The new record was
                            <strong>not</strong> created. Review the match below before trying again.
                        </p>

                        {{-- Matched property card --}}
                        <div class="rounded-lg p-4 mb-3"
                             style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="font-semibold" style="color: var(--text-primary);">
                                            {{ $duplicateProperty['property_name'] ?? 'Unnamed property' }}
                                        </span>
                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                              style="background-color: rgba(var(--danger-rgb, 239, 68, 68), 0.15); color: var(--danger, #ef4444);">
                                            <i class="fas fa-copy mr-1"></i>
                                            Match: {{ str_replace('_', ' ', $duplicateProperty['match_reason'] ?? 'unknown') }}
                                        </span>
                                    </div>
                                    <div class="text-sm space-y-0.5" style="color: var(--text-secondary);">
                                        @if(!empty($duplicateProperty['digital_address']))
                                            <div><i class="fas fa-map-pin mr-1 w-4 text-center"></i>{{ $duplicateProperty['digital_address'] }}</div>
                                        @endif
                                        @if(!empty($duplicateProperty['street_name']) || !empty($duplicateProperty['house_number']))
                                            <div>
                                                <i class="fas fa-road mr-1 w-4 text-center"></i>
                                                {{ trim(($duplicateProperty['house_number'] ?? '') . ' ' . ($duplicateProperty['street_name'] ?? '')) }}
                                            </div>
                                        @endif
                                        @if(!empty($duplicateProperty['area']) || !empty($duplicateProperty['region']))
                                            <div>
                                                <i class="fas fa-location-dot mr-1 w-4 text-center"></i>
                                                {{ trim(($duplicateProperty['area'] ?? '') . ', ' . ($duplicateProperty['region'] ?? ''), ', ') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if(!empty($duplicateProperty['id']))
                                <a href="{{ route('properties.show', $duplicateProperty['id']) }}"
                                   target="_blank"
                                   class="btn-secondary text-sm flex items-center whitespace-nowrap">
                                    <i class="fas fa-external-link-alt mr-1"></i> View property
                                </a>
                                @endif
                            </div>
                        </div>

                        {{-- Similar properties (fuzzy matches) --}}
                        @if(!empty($similarProperties))
                            <details class="mt-2">
                                <summary class="text-sm cursor-pointer font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-list mr-1"></i>
                                    {{ count($similarProperties) }} similar propert{{ count($similarProperties) === 1 ? 'y' : 'ies' }} found
                                </summary>
                                <ul class="mt-2 space-y-1 text-sm" style="color: var(--text-secondary);">
                                    @foreach($similarProperties as $similar)
                                        <li class="flex items-center justify-between gap-2 py-1 border-b"
                                            style="border-color: var(--border-color);">
                                            <span>
                                                {{ $similar['property_name'] ?? 'Unnamed' }}
                                                @if(!empty($similar['area']) || !empty($similar['region']))
                                                    — <em>{{ trim(($similar['area'] ?? '') . ', ' . ($similar['region'] ?? ''), ', ') }}</em>
                                                @endif
                                            </span>
                                            @if(!empty($similar['id']))
                                                <a href="{{ route('properties.show', $similar['id']) }}"
                                                   target="_blank"
                                                   class="text-xs underline"
                                                   style="color: var(--primary);">
                                                    Open
                                                    @if(!empty($similar['similarity']))
                                                        ({{ $similar['similarity'] }}% match)
                                                    @endif
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        <p class="text-xs mt-3" style="color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1"></i>
                            If this is genuinely a different property, change the property name or house number
                            (or pick a different landlord) and resubmit. All previously-entered form data has been
                            restored below.
                        </p>
                    @endif
                </div>
                <button type="button"
                        class="text-gray-400 hover:text-gray-600"
                        onclick="document.getElementById('duplicate-property-banner').style.display='none'"
                        aria-label="Dismiss">
                    <i class="fas fa-times"></i>
                </button>
            </div>
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
        <form action="{{ route('properties.store') }}" method="POST" id="property-form" enctype="multipart/form-data">
            @csrf

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
                                        data-region="{{ $plan->region ?? '' }}"
                                        data-area="{{ $plan->area ?? '' }}"
                                        data-estimated-houses="{{ $plan->estimated_houses }}"
                                        data-registered-houses="{{ $plan->houses_registered ?? 0 }}"
                                        data-plan-status="{{ $plan->status }}"
                                        data-is-global-sequence="{{ $plan->is_global_sequence ? '1' : '0' }}"
                                        data-continues-from-plan-id="{{ $plan->continues_from_plan_id }}"
                                        {{ old('registration_plan_id') == $plan->id ? 'selected' : '' }}>
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

                        <select name="property_type_id" id="property_type_id" class="hidden" required>
                            <option value="">Select Property Type</option>
                            @foreach($propertyTypes as $type)
                                <option value="{{ $type->id }}"
                                        {{ old('property_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>

                        <div id="selected-property-type" class="hidden mt-3 p-4 border-2 rounded-lg" style="border-color: var(--primary); background-color: var(--bg-secondary);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div id="selected-type-icon" class="w-12 h-12 rounded-full flex items-center justify-center"></div>
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
                               value="{{ old('custom_property_type') }}"
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

                        <div id="photo-preview-grid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mt-4"></div>

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
                            <div id="sequence-continuation-info" class="text-sm text-blue-700 mt-1 hidden"></div>
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
                               value="{{ old('registration_pattern') }}"
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

                            <div id="pattern-loading" class="text-xs hidden">
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                <span class="text-blue-600">Generating pattern...</span>
                            </div>

                            <div id="pattern-validation" class="text-xs hidden">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <span id="validation-message"></span>
                            </div>

                            <div id="pattern-error" class="text-xs hidden">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <span id="error-message" class="text-red-600"></span>
                            </div>

                            <div id="sequence-type-info" class="text-xs hidden">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span id="sequence-type-text"></span>
                            </div>

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
                        <input type="text" name="property_name" id="property_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('property_name') }}" placeholder="e.g., Samuel Mensah's House, Site Allocation Name" required>
                        <p class="text-xs text-gray-500 mt-1">This is the site allocation name that will be shown throughout the system</p>
                        @error('property_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="house_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">House Number</label>
                        <input type="text" name="house_number" id="house_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('house_number') }}">
                        <p class="text-xs text-gray-500 mt-1">Physical house number (optional if using naming pattern)</p>
                        @error('house_number')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="street_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Street Name *</label>
                        <input type="text" name="street_name" id="street_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('street_name') }}" required>
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
                        <div class="p-3 rounded border" id="plan-status-content"></div>
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

                    {{-- Regional Location --}}
                    <div class="border rounded p-4" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-globe-africa mr-1" style="color: var(--primary);"></i>
                            Regional Location
                            <span class="text-xs font-normal text-gray-500 ml-1">(where the property sits in the region)</span>
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="region" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Region *</label>
                                <select name="region" id="region"
                                        class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="">Select Region</option>
                                    @foreach($regions as $regionOption)
                                        <option value="{{ $regionOption }}" {{ old('region') == $regionOption ? 'selected' : '' }}>
                                            {{ $regionOption }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('region')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="area" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Area / Neighbourhood *</label>
                                <input type="text" name="area" id="area"
                                       class="w-full p-2 border rounded"
                                       style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('area') }}"
                                       placeholder="e.g., Madina, East Legon, Tema Community 5"
                                       maxlength="150"
                                       required>
                                <p class="text-xs text-gray-500 mt-1">The neighbourhood or suburb within the region where the property is located.</p>
                                @error('area')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Auto-filled Zone and Section -->
                    <div id="auto-filled-fields">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Zone <span class="text-xs font-normal text-gray-500">(community-level)</span>
                            </label>
                            <div class="p-2 border rounded bg-gray-50" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <span id="zone-display" class="text-gray-500">Select a registration plan</span>
                                <input type="hidden" name="zone" id="zone" value="{{ old('zone') }}">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Section <span class="text-xs font-normal text-gray-500">(community-level)</span>
                            </label>
                            <div class="p-2 border rounded bg-gray-50" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <span id="section-display" class="text-gray-500">Select a registration plan</span>
                                <input type="hidden" name="section" id="section" value="{{ old('section') }}">
                            </div>
                        </div>
                    </div>

                    <!-- Manual Zone/Section Override -->
                    <div id="manual-location-container" class="hidden">
                        <div>
                            <label for="manual_zone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Zone *</label>
                            <input type="text" name="manual_zone" id="manual_zone" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('manual_zone') }}" placeholder="Enter zone name">
                        </div>

                        <div>
                            <label for="manual_section" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Section</label>
                            <input type="text" name="manual_section" id="manual_section" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('manual_section') }}" placeholder="Enter section name">
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
                        <input type="text" name="block_number" id="block_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('block_number') }}">
                        @error('block_number')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="digital_address" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Digital Address</label>
                        <input type="text" name="digital_address" id="digital_address" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="e.g., GA-543-9876" value="{{ old('digital_address') }}">
                        @error('digital_address')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Coordinate Picker --}}
                    <div class="border-2 rounded-lg p-4" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="block text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i>
                                    Property Coordinates
                                    <span class="text-xs font-normal text-gray-500 ml-1">(Recommended for waste collection)</span>
                                </label>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    Drag the pin to the exact location, or click <strong>Find on map</strong> after typing the digital address.
                                </p>
                            </div>
                        </div>

                        @if($googleMapsKey)
                            <input type="hidden" name="latitude"  id="latitude"  value="{{ old('latitude',  '') }}">
                            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', '') }}">
                            <input type="hidden" name="city"      id="city"      value="{{ old('city',      '') }}">

                            <div id="property-map"
                                 class="rounded-lg overflow-hidden border"
                                 style="height: 280px; border-color: var(--border-color); background-color: var(--bg-tertiary);">
                            </div>

                            <div class="flex flex-wrap gap-2 mt-3">
                                <button type="button"
                                        onclick="findDigitalAddressOnMap()"
                                        class="btn-secondary text-sm flex items-center">
                                    <i class="fas fa-search-location mr-1"></i> Find on map
                                </button>

                                <button type="button"
                                        onclick="clearCoordinateSelection()"
                                        class="btn-secondary text-sm flex items-center"
                                        id="clear-coords-btn"
                                        style="display: none;">
                                    <i class="fas fa-times mr-1"></i> Clear location
                                </button>

                                <span id="coordinate-status"
                                      class="hidden text-xs px-3 py-2 rounded-full flex items-center"
                                      style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <span id="coordinate-status-text">Location confirmed</span>
                                </span>
                            </div>

                            <div id="coordinate-readout" class="hidden mt-2 text-xs font-mono p-2 rounded"
                                 style="background-color: var(--bg-tertiary); color: var(--text-secondary);">
                                <i class="fas fa-crosshairs mr-1"></i>
                                Lat: <span id="readout-lat">—</span> ·
                                Lng: <span id="readout-lng">—</span>
                            </div>

                            <script async
                                src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=initPropertyCoordinatePicker">
                            </script>
                        @else
                            <div class="p-3 rounded"
                                 style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                                <div class="flex items-start gap-2">
                                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                                    <div>
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            Map picker not available
                                        </div>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            No Google Maps browser key is configured. Coordinates will be
                                            geocoded from the digital address on save, if possible.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label for="registration_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Registration Date *</label>
                        <input type="date" name="registration_date" id="registration_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" value="{{ old('registration_date', date('Y-m-d')) }}" required>
                        @error('registration_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Property Status</label>
                        <select name="status" id="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="under_maintenance" {{ old('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                            <option value="vacant" {{ old('status') == 'vacant' ? 'selected' : '' }}>Vacant</option>
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

            <!-- ============================================================ -->
            <!-- ✅ LANDLORD INFORMATION — mutually-exclusive blocks          -->
            <!-- ============================================================ -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Landlord Information</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">

                    {{-- Existing landlord block --}}
                    <div id="existing-landlord-block"
                         class="{{ $initialLandlordMode === 'new' ? 'hidden' : '' }}">
                        <div class="mb-2">
                            <label for="landlord_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Select Existing Landlord</label>
                            <select name="landlord_id" id="landlord_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Select Existing Landlord</option>
                                @foreach($landlords as $landlord)
                                    <option value="{{ $landlord->id }}"
                                            data-phones="{{ $landlord->phones ? $landlord->phones->pluck('phone_number')->implode(',') : '' }}"
                                            data-email="{{ $landlord->email }}"
                                            data-has-phone="{{ !empty($landlord->phone) ? '1' : '0' }}"
                                            data-has-email="{{ !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL) ? '1' : '0' }}"
                                            {{ old('landlord_id') == $landlord->id ? 'selected' : '' }}>
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

                        <div class="flex items-center my-4">
                            <div class="flex-1 border-t" style="border-color: var(--border-color);"></div>
                            <span class="px-3 text-xs font-medium" style="color: var(--text-secondary);">OR</span>
                            <div class="flex-1 border-t" style="border-color: var(--border-color);"></div>
                        </div>

                        <button type="button"
                                id="switch-to-new-landlord"
                                class="btn-secondary w-full flex items-center justify-center"
                                onclick="setLandlordMode('new')">
                            <i class="fas fa-user-plus mr-2"></i>
                            Create a New Landlord Instead
                        </button>
                    </div>

                    {{-- New landlord block --}}
                    <div id="new-landlord-block"
                         class="{{ $initialLandlordMode === 'new' ? '' : 'hidden' }}">

                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full"
                                      style="background-color: rgba(var(--primary-rgb, 114, 103, 240), 0.15); color: var(--primary);">
                                    <i class="fas fa-user-plus text-sm"></i>
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--text-primary);">
                                    Create New Landlord
                                </span>
                            </div>

                            @if($landlords->isNotEmpty())
                                <button type="button"
                                        class="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                                        onclick="setLandlordMode('existing')">
                                    <i class="fas fa-arrow-left mr-1"></i>
                                    Use an Existing Landlord
                                </button>
                            @endif
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="landlord_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Full Name *</label>
                                <input type="text" name="landlord_name" id="landlord_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Enter full name" value="{{ old('landlord_name') }}">
                                @error('landlord_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Phone Numbers *
                                    <span class="text-xs font-normal text-gray-500 ml-1">(At least one phone number is required)</span>
                                </label>

                                <div id="phone-numbers-container" class="space-y-3">
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

                            <div>
                                <label for="landlord_email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Email Address</label>
                                <input type="email" name="landlord_email" id="landlord_email" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="e.g., landlord@example.com" value="{{ old('landlord_email') }}">
                                @error('landlord_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Helper hint shown in "choose" mode --}}
                    <p id="landlord-mode-hint"
                       class="text-xs text-gray-500 mt-4 {{ $initialLandlordMode === 'choose' ? '' : 'hidden' }}">
                        <i class="fas fa-info-circle mr-1"></i>
                        Pick an existing landlord, or click <strong>Create a New Landlord</strong> to add one.
                    </p>
                </div>
            </div>

            <!-- Landlord Invitation Section -->
            <div class="mt-6" id="landlord-invitation-section">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Landlord Invitation</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">

                    @if(!$anyChannelReady)
                    <div class="mb-4 p-4 rounded border-l-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left-color: var(--danger);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <h4 class="font-medium mb-1" style="color: var(--danger);">No Delivery Channels Available</h4>
                                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                    None of the communication services (SMS, WhatsApp, Email) are configured.
                                    Landlord invitations cannot be sent until at least one channel is set up.
                                    You can still register the property without sending an invitation.
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

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Send Invitation to Landlord
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                    <div id="invitation-channels-container" class="hidden space-y-4">
                        <label class="block text-sm font-medium" style="color: var(--text-primary);">
                            Invitation Channels *
                            <span class="text-xs font-normal text-gray-500 ml-1">(Select at least one channel)</span>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- SMS --}}
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

                            {{-- EMAIL --}}
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

                            {{-- WHATSAPP --}}
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

                        <div id="available-channels-info" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-700">
                                    Available channels: <span id="available-channels-list"></span>
                                </span>
                            </div>
                        </div>

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
                </div>
            </div>

            <!-- Tenant Invitation Section -->
            <div class="mt-6" id="tenant-invitation-section">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tenant Invitation</h3>

                <div class="border rounded p-4" style="border-color: var(--border-color);">
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Is this property rented to tenants?
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="modern-radio-option">
                                <input type="radio" name="is_rented" id="is_rented_yes" value="1"
                                       class="modern-radio-input"
                                       {{ old('is_rented') ? 'checked' : '' }}>
                                <label for="is_rented_yes" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">Yes, Property is Rented</div>
                                            <div class="modern-radio-description">Invite tenants to the system</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="modern-radio-option">
                                <input type="radio" name="is_rented" id="is_rented_no" value="0"
                                       class="modern-radio-input"
                                       {{ !old('is_rented') ? 'checked' : '' }}>
                                <label for="is_rented_no" class="modern-radio-label">
                                    <div class="modern-radio-content">
                                        <div class="modern-radio-icon">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="modern-radio-text">
                                            <div class="modern-radio-title">No, Not Rented</div>
                                            <div class="modern-radio-description">Skip tenant invitations</div>
                                        </div>
                                        <div class="modern-radio-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="tenant-invitation-container" class="hidden mt-4">
                        <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-700">
                                    This property is marked as rented. You can now invite tenants to join the system.
                                </span>
                            </div>
                        </div>

                        <button type="button"
                                id="add-tenants-btn"
                                class="btn-primary flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Add & Invite Tenants
                        </button>

                        <div id="tenants-summary" class="mt-4 hidden">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Tenants to Invite</h4>
                            <div class="space-y-2" id="tenants-list"></div>
                            <div class="mt-2 text-sm text-gray-600" id="tenants-count"></div>
                        </div>

                        <div id="tenant-hidden-inputs"></div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Additional Information</h3>

                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Description</label>
                    <textarea name="description" id="description" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" rows="3" placeholder="Enter any additional details about the property...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-4 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <a href="{{ route('properties.index') }}" class="btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-primary flex items-center" id="submit-button" {{ $registrationPlans->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-save mr-2"></i> Save Property
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tenant Invitation Modal -->
<div id="tenant-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-lg" style="background-color: var(--bg-primary); border-color: var(--border-color);">
        <div class="flex justify-between items-center mb-6 pb-4 border-b" style="border-color: var(--border-color);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-user-plus mr-2"></i>Add & Invite Tenants
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

        <div id="tenant-forms-container" class="space-y-6 max-h-96 overflow-y-auto pr-2"></div>

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
<div id="app-urls"
     data-create-plan="{{ route('registration-plans.create') }}"
     data-get-channels-url="{{ route('landlord.channels', ['landlord' => 'LANDLORD_ID']) }}"
     data-geocode-url="{{ route('properties.geocode') }}"
     style="display: none;">
</div>

<script>
// ==================== GLOBAL CHANNEL AVAILABILITY ====================
const SMS_SYSTEM_READY      = {{ $smsReady ? 'true' : 'false' }};
const WHATSAPP_SYSTEM_READY = {{ $whatsappReady ? 'true' : 'false' }};
const EMAIL_SYSTEM_READY    = {{ $emailReady ? 'true' : 'false' }};
const ANY_CHANNEL_READY     = SMS_SYSTEM_READY || WHATSAPP_SYSTEM_READY || EMAIL_SYSTEM_READY;

const INITIAL_LANDLORD_MODE = @json($initialLandlordMode);

function isChannelGloballyReady(channel) {
    if (channel === 'sms')      return SMS_SYSTEM_READY;
    if (channel === 'whatsapp') return WHATSAPP_SYSTEM_READY;
    if (channel === 'email')    return EMAIL_SYSTEM_READY;
    return false;
}

// ============================================================
// ✅ LANDLORD MODE — mutually-exclusive blocks
// ============================================================

function setLandlordMode(mode, opts = {}) {
    const { clearOther = true } = opts;

    const existingBlock = document.getElementById('existing-landlord-block');
    const newBlock      = document.getElementById('new-landlord-block');
    const hint          = document.getElementById('landlord-mode-hint');
    const landlordSelect = document.getElementById('landlord_id');

    if (!existingBlock || !newBlock) return;

    if (mode === 'new') {
        existingBlock.classList.add('hidden');
        newBlock.classList.remove('hidden');
        if (hint) hint.classList.add('hidden');

        if (clearOther && landlordSelect) {
            landlordSelect.value = '';
            if (typeof checkAvailableChannels === 'function') checkAvailableChannels();
        }

        const first = document.getElementById('landlord_name');
        if (first && !first.value) setTimeout(() => first.focus(), 50);
    } else {
        existingBlock.classList.remove('hidden');
        newBlock.classList.add('hidden');

        if (hint) {
            if (mode === 'choose') hint.classList.remove('hidden');
            else hint.classList.add('hidden');
        }

        if (clearOther && mode === 'existing') {
            const nameInput  = document.getElementById('landlord_name');
            const emailInput = document.getElementById('landlord_email');
            if (nameInput)  nameInput.value  = '';
            if (emailInput) emailInput.value = '';

            const phoneContainer = document.getElementById('phone-numbers-container');
            if (phoneContainer) {
                const groups = phoneContainer.querySelectorAll('.phone-number-group');
                groups.forEach((group, index) => {
                    if (index > 0) group.remove();
                    else {
                        const input = group.querySelector('.phone-input');
                        if (input) input.value = '';
                    }
                });
                if (typeof phoneCounter !== 'undefined') phoneCounter = 1;
                if (typeof updateRemoveButtons === 'function') updateRemoveButtons();
            }

            if (typeof checkAvailableChannels === 'function') checkAvailableChannels();
        }
    }
}

// ==================== ✅ PROPERTY COORDINATE PICKER ====================

const DEFAULT_MAP_CENTER = { lat: 5.6037, lng: -0.1870 };
let propertyMap        = null;
let propertyMarker     = null;
let currentCoordinates = { lat: null, lng: null };

function initPropertyCoordinatePicker() {
    const container = document.getElementById('property-map');
    if (!container || typeof google === 'undefined') return;

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const startLat = parseFloat(latInput?.value || '') || DEFAULT_MAP_CENTER.lat;
    const startLng = parseFloat(lngInput?.value || '') || DEFAULT_MAP_CENTER.lng;
    const hasStart = !!(latInput?.value && lngInput?.value);

    propertyMap = new google.maps.Map(container, {
        center: { lat: startLat, lng: startLng },
        zoom: hasStart ? 17 : 12,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });

    propertyMarker = new google.maps.Marker({
        map: propertyMap,
        position: { lat: startLat, lng: startLng },
        draggable: true,
        animation: google.maps.Animation.DROP,
        visible: hasStart,
        title: 'Drag to adjust the exact property location',
    });

    propertyMap.addListener('click', (event) => {
        propertyMarker.setPosition(event.latLng);
        propertyMarker.setVisible(true);
        syncCoordinatesFromMarker();
    });

    propertyMarker.addListener('dragend', () => {
        syncCoordinatesFromMarker();
    });

    if (hasStart) {
        currentCoordinates = { lat: startLat, lng: startLng };
        updateCoordinateUI(true);
    }
}

function syncCoordinatesFromMarker() {
    if (!propertyMarker) return;

    const position = propertyMarker.getPosition();
    const lat = position.lat();
    const lng = position.lng();

    currentCoordinates = { lat, lng };

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (latInput) latInput.value = lat.toFixed(7);
    if (lngInput) lngInput.value = lng.toFixed(7);

    updateCoordinateUI(true);
}

function updateCoordinateUI(hasCoordinates) {
    const statusEl   = document.getElementById('coordinate-status');
    const statusText = document.getElementById('coordinate-status-text');
    const readout    = document.getElementById('coordinate-readout');
    const readoutLat = document.getElementById('readout-lat');
    const readoutLng = document.getElementById('readout-lng');
    const clearBtn   = document.getElementById('clear-coords-btn');

    if (hasCoordinates && currentCoordinates.lat !== null) {
        statusEl?.classList.remove('hidden');
        readout?.classList.remove('hidden');
        clearBtn?.style && (clearBtn.style.display = 'inline-flex');

        if (statusText) statusText.textContent = 'Location confirmed';
        if (readoutLat) readoutLat.textContent = currentCoordinates.lat.toFixed(7);
        if (readoutLng) readoutLng.textContent = currentCoordinates.lng.toFixed(7);
    } else {
        statusEl?.classList.add('hidden');
        readout?.classList.add('hidden');
        if (clearBtn) clearBtn.style.display = 'none';
    }
}

async function findDigitalAddressOnMap() {
    const digitalAddress = document.getElementById('digital_address')?.value?.trim();
    if (!digitalAddress) {
        alert('Please enter a digital address first.');
        return;
    }

    if (!propertyMap || !propertyMarker) {
        alert('Map picker is not available on this page.');
        return;
    }

    const button = event?.currentTarget;
    const originalHtml = button?.innerHTML;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Finding…';
    }

    try {
        const geocodeUrl = document.getElementById('app-urls')?.dataset.geocodeUrl;
        if (geocodeUrl) {
            const url = new URL(geocodeUrl, window.location.origin);
            url.searchParams.set('digital_address', digitalAddress);

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.latitude && data.longitude) {
                    placeMarkerAndSync(data.latitude, data.longitude);
                    if (data.city) {
                        const cityInput = document.getElementById('city');
                        if (cityInput) cityInput.value = data.city;
                    }
                    if (data.source) {
                        console.info('Coordinates resolved via:', data.source);
                    }
                    return;
                }
            }
        }

        if (typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode(
                { address: `${digitalAddress}, Ghana` },
                (results, status) => {
                    if (status === 'OK' && results && results[0]) {
                        const location = results[0].geometry.location;
                        placeMarkerAndSync(location.lat(), location.lng());
                    } else {
                        alert('Could not locate that digital address. Please drag the pin manually.');
                    }
                }
            );
        } else {
            alert('Could not locate that address. Please drag the pin manually.');
        }
    } catch (e) {
        console.error('Geocoding failed:', e);
        alert('Could not locate that address. Please drag the pin manually.');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }
}

function placeMarkerAndSync(lat, lng) {
    const numLat = parseFloat(lat);
    const numLng = parseFloat(lng);
    if (isNaN(numLat) || isNaN(numLng)) return;

    const position = { lat: numLat, lng: numLng };
    propertyMap.setCenter(position);
    propertyMap.setZoom(17);
    propertyMarker.setPosition(position);
    propertyMarker.setVisible(true);
    syncCoordinatesFromMarker();
}

function clearCoordinateSelection() {
    currentCoordinates = { lat: null, lng: null };

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const cityInput = document.getElementById('city');
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
    if (cityInput) cityInput.value = '';

    if (propertyMarker) propertyMarker.setVisible(false);
    updateCoordinateUI(false);
}

// ==================== PHOTO UPLOAD FUNCTIONALITY ====================

let selectedPhotos = [];
let primaryPhotoIndex = -1;
const MAX_PHOTOS = 10;
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];

function handlePhotoSelection(input) {
    const files = Array.from(input.files);
    const totalPhotos = selectedPhotos.length + files.length;

    if (totalPhotos > MAX_PHOTOS) {
        alert(`You can only upload up to ${MAX_PHOTOS} photos. You currently have ${selectedPhotos.length} photos selected.`);
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
            selectedPhotos.push({
                file: file,
                preview: e.target.result,
                isPrimary: selectedPhotos.length === 0 && primaryPhotoIndex === -1
            });

            if (selectedPhotos.length === 1 && primaryPhotoIndex === -1) {
                primaryPhotoIndex = 0;
                selectedPhotos[0].isPrimary = true;
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
        emptyHint.className = 'col-span-full text-center py-8';
        emptyHint.style.color = 'var(--text-secondary)';
        emptyHint.innerHTML = '<i class="fas fa-camera text-3xl mb-2 block"></i><p>No photos selected. Click the upload area above to add property photos.</p>';
        grid.appendChild(emptyHint);
        return;
    }

    selectedPhotos.forEach((photo, index) => {
        const photoItem = document.createElement('div');
        photoItem.className = 'photo-preview-item relative rounded-lg overflow-hidden border-2';
        photoItem.style.borderColor = photo.isPrimary ? 'var(--primary)' : 'var(--border-color)';
        photoItem.style.backgroundColor = 'var(--bg-secondary)';

        photoItem.innerHTML = `
            <img class="w-full h-32 object-cover" src="${photo.preview}" alt="Property photo ${index + 1}">
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
    if (!fileInput) return;
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
        selectedPhotos.forEach((photo, i) => { photo.isPrimary = (i === index); });
        updatePhotoPreviewGrid();
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

function setupPhotoUpload() {
    const uploadArea = document.getElementById('photo-upload-area');
    const fileInput = document.getElementById('property_photos');

    if (!uploadArea || !fileInput) return;

    uploadArea.addEventListener('click', () => fileInput.click());

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

        const files = Array.from(e.dataTransfer.files).filter(file => ALLOWED_TYPES.includes(file.type));
        const totalPhotos = selectedPhotos.length + files.length;
        if (totalPhotos > MAX_PHOTOS) {
            alert(`You can only upload up to ${MAX_PHOTOS} photos. You currently have ${selectedPhotos.length} photos selected.`);
            return;
        }

        files.forEach(file => {
            if (file.size > MAX_FILE_SIZE) {
                alert(`File "${file.name}" exceeds the 5MB limit.`);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                selectedPhotos.push({
                    file: file,
                    preview: e.target.result,
                    isPrimary: selectedPhotos.length === 0 && primaryPhotoIndex === -1
                });

                if (selectedPhotos.length === 1 && primaryPhotoIndex === -1) {
                    primaryPhotoIndex = 0;
                    selectedPhotos[0].isPrimary = true;
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
            if (oldPhones[i]) addPhoneNumber(oldPhones[i]);
        }
    }
}

function addPhoneNumber(value = '') {
    const container = document.getElementById('phone-numbers-container');
    if (!container) return;
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
    if (newInput) newInput.addEventListener('input', formatPhoneNumber);

    const removeBtn = phoneGroup.querySelector('.remove-phone-btn');
    if (removeBtn) removeBtn.addEventListener('click', function() { removePhoneNumber(this); });

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
    for (let input of phoneInputs) {
        const cleanPhone = input.value.replace(/\D/g, '');
        if (cleanPhone.length >= 10) return true;
    }
    return false;
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
    if (propertyTypeSelect) propertyTypeSelect.value = typeId;

    const selectedDisplay = document.getElementById('selected-property-type');
    const selectedIcon = document.getElementById('selected-type-icon');
    const selectedName = document.getElementById('selected-type-name');
    const selectedDescription = document.getElementById('selected-type-description');

    const typeIcon = cardElement.getAttribute('data-type-icon');
    const typeName = cardElement.getAttribute('data-type-name');
    const typeDescription = cardElement.getAttribute('data-type-description');

    if (selectedIcon) {
        selectedIcon.innerHTML = `<i class="${typeIcon} text-lg" style="color: ${typeColor};"></i>`;
        selectedIcon.style.backgroundColor = typeColor + '20';
        selectedIcon.style.border = `2px solid ${typeColor}40`;
    }

    if (selectedName) selectedName.textContent = typeName;
    if (selectedDescription) selectedDescription.textContent = typeDescription;
    if (selectedDisplay) selectedDisplay.classList.remove('hidden');

    const cardsGrid = document.getElementById('property-type-cards');
    if (cardsGrid) cardsGrid.classList.add('hidden');

    const customContainer = document.getElementById('custom_property_type_container');
    const customInput = document.getElementById('custom_property_type');

    if (slug === 'custom') {
        if (customContainer) customContainer.classList.remove('hidden');
        if (customInput) {
            customInput.setAttribute('required', 'required');
            setTimeout(() => customInput.focus(), 100);
        }
    } else {
        if (customContainer) customContainer.classList.add('hidden');
        if (customInput) {
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }

    updateCustomTypeCharCounter();
}

function clearPropertyTypeSelection() {
    document.querySelectorAll('.property-type-card').forEach(card => {
        card.style.borderColor = 'var(--border-color)';
        card.style.backgroundColor = 'var(--bg-secondary)';
        card.style.boxShadow = '';
    });

    const propertyTypeSelect = document.getElementById('property_type_id');
    if (propertyTypeSelect) propertyTypeSelect.value = '';

    const selectedDisplay = document.getElementById('selected-property-type');
    if (selectedDisplay) selectedDisplay.classList.add('hidden');

    const cardsGrid = document.getElementById('property-type-cards');
    if (cardsGrid) cardsGrid.classList.remove('hidden');

    const customContainer = document.getElementById('custom_property_type_container');
    const customInput = document.getElementById('custom_property_type');
    if (customContainer) customContainer.classList.add('hidden');
    if (customInput) {
        customInput.removeAttribute('required');
        customInput.value = '';
    }
}

function updateCustomTypeCharCounter() {
    const customInput = document.getElementById('custom_property_type');
    const charCounter = document.getElementById('custom-type-chars');

    if (customInput && charCounter) {
        const length = customInput.value.length;
        charCounter.textContent = `${length}/100`;

        if (length > 80) charCounter.style.color = '#ef4444';
        else if (length > 60) charCounter.style.color = '#f59e0b';
        else charCounter.style.color = '#6b7280';
    }
}

function initializePropertyTypeSelection() {
    const propertyTypeSelect = document.getElementById('property_type_id');
    if (!propertyTypeSelect) return;
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

    if (sendInvitationYes && sendInvitationYes.checked) {
        if (channelsContainer) channelsContainer.classList.remove('hidden');
        checkAvailableChannels();
    } else {
        if (channelsContainer) channelsContainer.classList.add('hidden');
    }
}

async function checkAvailableChannels() {
    const landlordId = document.getElementById('landlord_id')?.value;
    const availableChannelsInfo = document.getElementById('available-channels-info');
    const noChannelsWarning = document.getElementById('no-channels-warning');
    const availableChannelsList = document.getElementById('available-channels-list');

    if (availableChannelsInfo) availableChannelsInfo.classList.add('hidden');
    if (noChannelsWarning) noChannelsWarning.classList.add('hidden');

    if (landlordId) {
        try {
            const response = await fetch(`/landlords/${landlordId}/channels`);
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
        } catch (error) {
            console.error('Error fetching available channels:', error);
            checkBasicChannelAvailability(landlordId);
        }
    } else {
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
    if (radio.checked) option.classList.add('modern-radio-option--checked');
    else option.classList.remove('modern-radio-option--checked');
}

function updateCheckboxVisualState(checkbox) {
    if (!checkbox) return;
    const option = checkbox.closest('.modern-radio-option');
    if (!option) return;
    if (checkbox.disabled) {
        option.classList.remove('modern-radio-option--checked');
        return;
    }
    if (checkbox.checked) option.classList.add('modern-radio-option--checked');
    else option.classList.remove('modern-radio-option--checked');
}

function initializeAllVisualStates() {
    document.querySelectorAll('input[name="send_invitation"]').forEach(updateRadioVisualState);
    document.querySelectorAll('.channel-checkbox').forEach(updateCheckboxVisualState);
}

// ==================== TENANT MANAGEMENT ====================

let tenants = [];

function toggleTenantInvitation() {
    const isRentedYes = document.getElementById('is_rented_yes');
    const tenantContainer = document.getElementById('tenant-invitation-container');

    if (isRentedYes && isRentedYes.checked && tenantContainer) {
        tenantContainer.classList.remove('hidden');
        const tenantRadios = document.querySelectorAll('input[name="is_rented"]');
        tenantRadios.forEach(radio => updateRadioVisualState(radio));
    } else if (tenantContainer) {
        tenantContainer.classList.add('hidden');
        tenants = [];
        updateTenantSummary();
        clearTenantHiddenInputs();
    }
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

    if (tenants.length > 0 && tenantCountSelect) {
        tenantCountSelect.value = tenants.length;
        generateTenantForms(tenants.length);
        prefillTenantForms();
    }
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
        const existingTenant = tenants[i];
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
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Full Name *</label>
                    <input type="text" class="w-full p-2 border rounded tenant-name"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Kwame Osei" data-index="${i}"
                           value="${escapeHtml(existingTenant?.name || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Phone Number *</label>
                    <input type="text" class="w-full p-2 border rounded tenant-phone"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., 024 123 4567" data-index="${i}"
                           value="${escapeHtml(existingTenant?.phone || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Email Address</label>
                    <input type="email" class="w-full p-2 border rounded tenant-email"
                           style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., tenant@example.com" data-index="${i}"
                           value="${escapeHtml(existingTenant?.email || '')}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Gender</label>
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
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Invitation Channels *</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="tenant-channel" value="sms" data-index="${i}"
                                   ${defaultChannels.includes('sms') ? 'checked' : ''}
                                   ${SMS_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">SMS${SMS_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="tenant-channel" value="whatsapp" data-index="${i}"
                                   ${defaultChannels.includes('whatsapp') ? 'checked' : ''}
                                   ${WHATSAPP_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">WhatsApp${WHATSAPP_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="tenant-channel" value="email" data-index="${i}"
                                   ${defaultChannels.includes('email') ? 'checked' : ''}
                                   ${EMAIL_SYSTEM_READY ? '' : 'disabled'}>
                            <span class="text-sm" style="color: var(--text-primary);">Email${EMAIL_SYSTEM_READY ? '' : ' (unavailable)'}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Notes (Optional)</label>
                <textarea class="w-full p-2 border rounded tenant-notes"
                          style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);"
                          rows="2" placeholder="Any additional notes about this tenant..." data-index="${i}">${escapeHtml(existingTenant?.notes || '')}</textarea>
            </div>
        `;

        container.appendChild(tenantForm);
        const phoneInput = tenantForm.querySelector('.tenant-phone');
        if (phoneInput) phoneInput.addEventListener('input', formatPhoneNumber);
    }
}

function prefillTenantForms() {
    tenants.forEach((tenant, index) => {
        const nameInput = document.querySelector(`.tenant-name[data-index="${index}"]`);
        const phoneInput = document.querySelector(`.tenant-phone[data-index="${index}"]`);
        const emailInput = document.querySelector(`.tenant-email[data-index="${index}"]`);
        const genderSelect = document.querySelector(`.tenant-gender[data-index="${index}"]`);
        const notesInput = document.querySelector(`.tenant-notes[data-index="${index}"]`);

        if (nameInput) nameInput.value = tenant.name || '';
        if (phoneInput) phoneInput.value = tenant.phone || '';
        if (emailInput) emailInput.value = tenant.email || '';
        if (genderSelect) genderSelect.value = tenant.gender || '';
        if (notesInput) notesInput.value = tenant.notes || '';

        const channelCheckboxes = document.querySelectorAll(`.tenant-channel[data-index="${index}"]`);
        channelCheckboxes.forEach(checkbox => {
            if (!isChannelGloballyReady(checkbox.value)) {
                checkbox.checked = false;
                checkbox.disabled = true;
                return;
            }
            checkbox.checked = tenant.channels?.includes(checkbox.value) || false;
        });
    });
}

function saveTenants() {
    const tenantCount = parseInt(document.getElementById('tenant-count')?.value || 0);
    const nameInputs = document.querySelectorAll('.tenant-name');
    const phoneInputs = document.querySelectorAll('.tenant-phone');
    const emailInputs = document.querySelectorAll('.tenant-email');
    const genderSelects = document.querySelectorAll('.tenant-gender');
    const noteInputs = document.querySelectorAll('.tenant-notes');

    tenants = [];
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
            if (!cb.disabled && isChannelGloballyReady(cb.value)) channels.push(cb.value);
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

        tenants.push({ name, phone: cleanPhone, email, gender, channels, notes });
    }

    if (hasErrors) {
        alert('Please fill in all required fields (Name and Phone are required for each tenant)');
        return;
    }

    if (tenants.length === 0) {
        alert('No valid tenant information provided');
        return;
    }

    updateTenantSummary();
    updateTenantHiddenInputs();
    closeTenantModal();
}

function updateTenantSummary() {
    const summaryContainer = document.getElementById('tenants-summary');
    const tenantsList = document.getElementById('tenants-list');
    const tenantsCount = document.getElementById('tenants-count');

    if (!summaryContainer) return;

    if (tenants.length === 0) {
        summaryContainer.classList.add('hidden');
        return;
    }

    if (tenantsList) tenantsList.innerHTML = '';

    tenants.forEach((tenant, index) => {
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
                <button type="button" onclick="removeTenant(${index})" class="text-red-500 hover:text-red-700 p-1">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        if (tenantsList) tenantsList.appendChild(tenantItem);
    });

    if (tenantsCount) tenantsCount.textContent = `${tenants.length} tenant(s) added`;
    summaryContainer.classList.remove('hidden');
}

function removeTenant(index) {
    if (confirm('Are you sure you want to remove this tenant?')) {
        tenants.splice(index, 1);
        updateTenantSummary();
        updateTenantHiddenInputs();

        if (tenants.length === 0) {
            const summaryContainer = document.getElementById('tenants-summary');
            if (summaryContainer) summaryContainer.classList.add('hidden');
        }
    }
}

function updateTenantHiddenInputs() {
    const container = document.getElementById('tenant-hidden-inputs');
    if (!container) return;

    container.innerHTML = '';

    tenants.forEach((tenant, index) => {
        const tenantData = document.createElement('div');
        let channelsHTML = '';
        tenant.channels.forEach((channel, channelIndex) => {
            channelsHTML += `<input type="hidden" name="tenants[${index}][channels][${channelIndex}]" value="${escapeHtml(channel)}">`;
        });

        tenantData.innerHTML = `
            <input type="hidden" name="tenants[${index}][name]" value="${escapeHtml(tenant.name)}">
            <input type="hidden" name="tenants[${index}][phone]" value="${tenant.phone}">
            <input type="hidden" name="tenants[${index}][email]" value="${tenant.email || ''}">
            <input type="hidden" name="tenants[${index}][gender]" value="${tenant.gender || ''}">
            ${channelsHTML}
            <input type="hidden" name="tenants[${index}][notes]" value="${escapeHtml(tenant.notes || '')}">
        `;
        container.appendChild(tenantData);
    });

    if (tenants.length > 0) {
        const flagInput = document.createElement('input');
        flagInput.type = 'hidden';
        flagInput.name = 'has_tenants';
        flagInput.value = '1';
        container.appendChild(flagInput);
    }
}

function clearTenantHiddenInputs() {
    const container = document.getElementById('tenant-hidden-inputs');
    if (container) container.innerHTML = '';
}

function initializeTenants() {
    const oldTenants = @json(old('tenants', []));

    if (oldTenants && oldTenants.length > 0) {
        oldTenants.forEach(tenant => {
            if (tenant.name && tenant.phone) {
                let channels = ['sms'];
                if (Array.isArray(tenant.channels)) channels = tenant.channels;
                else if (typeof tenant.channels === 'string') {
                    try { channels = JSON.parse(tenant.channels); }
                    catch (e) { channels = tenant.channels.split(','); }
                }

                channels = channels.filter(c => isChannelGloballyReady(c));
                if (channels.length === 0 && SMS_SYSTEM_READY) channels = ['sms'];

                tenants.push({
                    name: tenant.name,
                    phone: tenant.phone,
                    email: tenant.email || '',
                    gender: tenant.gender || '',
                    channels: channels,
                    notes: tenant.notes || ''
                });
            }
        });

        if (tenants.length > 0) {
            const isRentedYes = document.getElementById('is_rented_yes');
            if (isRentedYes) isRentedYes.checked = true;
            toggleTenantInvitation();
            updateTenantSummary();
            updateTenantHiddenInputs();
        }
    }
}

// ==================== NAMING PATTERN & LOCATION ====================

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

    const patternDisplay = document.getElementById('pattern-display');
    const sequenceInfo = document.getElementById('sequence-info');
    const nextAvailableInfo = document.getElementById('next-available-info');
    const startingPointInfo = document.getElementById('starting-point-info');
    const planStatusInfo = document.getElementById('plan-status-info');
    const namingPreviewContainer = document.getElementById('naming-preview-container');
    const namingPatternInfo = document.getElementById('naming-pattern-info');
    const patternStatus = document.getElementById('pattern-status');

    if (patternDisplay) patternDisplay.textContent = pattern;
    if (sequenceInfo) sequenceInfo.textContent = sequenceType.charAt(0).toUpperCase() + sequenceType.slice(1).replace('_', ' ');
    if (nextAvailableInfo) nextAvailableInfo.textContent = nextAvailable;
    if (startingPointInfo) startingPointInfo.textContent = startingPoint;
    if (planStatusInfo) planStatusInfo.textContent = planStatus.charAt(0).toUpperCase() + planStatus.slice(1).replace('_', ' ');

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
        if (zoneInput) zoneInput.value = '';
        if (sectionInput) sectionInput.value = '';
        return;
    }

    const zoneValue = planOption.dataset.zone;
    const sectionValue = planOption.dataset.section;

    if (zoneDisplay) zoneDisplay.textContent = zoneValue || 'Not specified';
    if (sectionDisplay) sectionDisplay.textContent = sectionValue || 'Not specified';
    if (zoneInput) zoneInput.value = zoneValue || '';
    if (sectionInput) sectionInput.value = sectionValue || '';
}

// ==================== REGION / AREA AUTO-FILL ====================

function applyRegionAreaFromPlan(planOption) {
    if (!planOption || !planOption.value) return;

    const planRegion = (planOption.dataset.region || '').trim();
    const planArea   = (planOption.dataset.area   || '').trim();

    const regionSelect = document.getElementById('region');
    const areaInput    = document.getElementById('area');

    if (planRegion && regionSelect && !regionSelect.value) {
        const match = Array.from(regionSelect.options).find(
            opt => opt.value.toLowerCase() === planRegion.toLowerCase()
        );
        if (match) {
            regionSelect.value = match.value;
        }
    }

    if (planArea && areaInput && !areaInput.value) {
        areaInput.value = planArea;
    }
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
        case 'sequential': sequenceDescription = 'Sequential numbering (1, 2, 3, 4, ...)'; break;
        case 'even_only':  sequenceDescription = 'Even numbers only (2, 4, 6, 8, ...)'; break;
        case 'odd_only':   sequenceDescription = 'Odd numbers only (1, 3, 5, 7, ...)'; break;
        default:           sequenceDescription = 'Custom sequence';
    }

    if (sequenceTypeText) sequenceTypeText.textContent = sequenceDescription;
    if (sequenceTypeInfo) sequenceTypeInfo.classList.remove('hidden');
}

function autoGeneratePropertyName(pattern, landlordName = null) {
    if (!pattern) return null;
    if (landlordName) return `${landlordName}'s House - ${pattern}`;
    return `Site Allocation ${pattern}`;
}

function handleLandlordChange() {
    const registrationPlanSelect = document.getElementById('registration_plan_id');
    const propertyNameInput = document.getElementById('property_name');
    const registrationPatternInput = document.getElementById('registration_pattern');
    const landlordNameInput = document.getElementById('landlord_name');
    const landlordIdSelect = document.getElementById('landlord_id');

    const selectedPlan = registrationPlanSelect?.options[registrationPlanSelect.selectedIndex];
    if (!selectedPlan || !selectedPlan.value || !propertyNameInput) return;

    const landlordName = landlordNameInput?.value ||
                       (landlordIdSelect?.options[landlordIdSelect.selectedIndex]?.text?.split(' - ')[0]) ||
                       null;

    if (!propertyNameInput.value && registrationPatternInput?.value) {
        const suggestedName = autoGeneratePropertyName(registrationPatternInput.value, landlordName);
        if (suggestedName) propertyNameInput.placeholder = `e.g., ${suggestedName}`;
    }
}

// ==================== FORM RESET ====================

function resetFormFields() {
    const registrationPlanSelect = document.getElementById('registration_plan_id');
    if (registrationPlanSelect) registrationPlanSelect.value = '';

    const propertyNameInput = document.getElementById('property_name');
    if (propertyNameInput) propertyNameInput.value = '';

    const houseNumberInput = document.getElementById('house_number');
    if (houseNumberInput) houseNumberInput.value = '';

    const streetNameInput = document.getElementById('street_name');
    if (streetNameInput) streetNameInput.value = '';

    const blockNumberInput = document.getElementById('block_number');
    if (blockNumberInput) blockNumberInput.value = '';

    const digitalAddressInput = document.getElementById('digital_address');
    if (digitalAddressInput) digitalAddressInput.value = '';

    const regionSelect = document.getElementById('region');
    if (regionSelect) regionSelect.value = '';

    const areaInput = document.getElementById('area');
    if (areaInput) areaInput.value = '';

    const descriptionInput = document.getElementById('description');
    if (descriptionInput) descriptionInput.value = '';

    const statusSelect = document.getElementById('status');
    if (statusSelect) statusSelect.value = 'active';

    setLandlordMode('choose', { clearOther: true });

    const landlordIdSelect = document.getElementById('landlord_id');
    if (landlordIdSelect) landlordIdSelect.value = '';

    const landlordNameInputField = document.getElementById('landlord_name');
    if (landlordNameInputField) landlordNameInputField.value = '';

    const landlordEmailInputField = document.getElementById('landlord_email');
    if (landlordEmailInputField) landlordEmailInputField.value = '';

    const registrationDateInput = document.getElementById('registration_date');
    if (registrationDateInput) registrationDateInput.value = new Date().toISOString().split('T')[0];

    const registrationPatternInput = document.getElementById('registration_pattern');
    if (registrationPatternInput) registrationPatternInput.value = '';

    clearCoordinateSelection();

    clearPropertyTypeSelection();

    selectedPhotos = [];
    primaryPhotoIndex = -1;
    updatePhotoPreviewGrid();
    const propertyPhotosInput = document.getElementById('property_photos');
    if (propertyPhotosInput) propertyPhotosInput.value = '';

    const phoneContainer = document.getElementById('phone-numbers-container');
    if (phoneContainer) {
        const phoneGroups = phoneContainer.querySelectorAll('.phone-number-group');
        phoneGroups.forEach((group, index) => {
            if (index > 0) group.remove();
            else {
                const phoneInput = group.querySelector('.phone-input');
                if (phoneInput) phoneInput.value = '';
            }
        });
        phoneCounter = 1;
        updateRemoveButtons();
    }

    const autoFilledFields = document.getElementById('auto-filled-fields');
    const manualLocationContainer = document.getElementById('manual-location-container');
    const toggleLocationOverride = document.getElementById('toggle-location-override');

    if (autoFilledFields && manualLocationContainer && toggleLocationOverride) {
        if (!autoFilledFields.classList.contains('hidden')) {
            const zoneDisplay = document.getElementById('zone-display');
            const sectionDisplay = document.getElementById('section-display');
            const zoneInput = document.getElementById('zone');
            const sectionInput = document.getElementById('section');

            if (zoneDisplay) zoneDisplay.textContent = 'Select a registration plan';
            if (sectionDisplay) sectionDisplay.textContent = 'Select a registration plan';
            if (zoneInput) zoneInput.value = '';
            if (sectionInput) sectionInput.value = '';
        } else {
            autoFilledFields.classList.remove('hidden');
            manualLocationContainer.classList.add('hidden');
            toggleLocationOverride.innerHTML = '<i class="fas fa-edit mr-1"></i>Override';

            const manualZoneInput = document.getElementById('manual_zone');
            const manualSectionInput = document.getElementById('manual_section');
            if (manualZoneInput) manualZoneInput.value = '';
            if (manualSectionInput) manualSectionInput.value = '';
        }
    }

    const sendInvitationNo = document.getElementById('send_invitation_no');
    if (sendInvitationNo) {
        sendInvitationNo.checked = true;
        sendInvitationNo.dispatchEvent(new Event('change'));
    }

    document.querySelectorAll('.channel-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        updateCheckboxVisualState(checkbox);
    });

    const isRentedNo = document.getElementById('is_rented_no');
    if (isRentedNo) {
        isRentedNo.checked = true;
        isRentedNo.dispatchEvent(new Event('change'));
    }

    tenants = [];
    updateTenantSummary();
    clearTenantHiddenInputs();

    const namingPreviewContainer = document.getElementById('naming-preview-container');
    if (namingPreviewContainer) namingPreviewContainer.classList.add('hidden');

    const planProgressContainer = document.getElementById('plan-progress-container');
    if (planProgressContainer) planProgressContainer.classList.add('hidden');

    const globalSequenceInfo = document.getElementById('global-sequence-info');
    if (globalSequenceInfo) globalSequenceInfo.classList.add('hidden');

    const namingPatternInfo = document.getElementById('naming-pattern-info');
    if (namingPatternInfo) namingPatternInfo.textContent = 'Select a registration plan to see naming pattern';

    const patternStatus = document.getElementById('pattern-status');
    if (patternStatus) patternStatus.classList.add('hidden');
}

function showTemporaryNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-4 py-3 rounded shadow-lg transition-all duration-300 transform translate-x-full`;
    notification.style.backgroundColor = type === 'success' ? '#10B981' : '#3B82F6';
    notification.style.color = 'white';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);

    setTimeout(() => { notification.style.transform = 'translateX(0)'; }, 100);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function addResetButton() {
    const formActions = document.querySelector('.flex.justify-end.space-x-4.mt-8.pt-6');
    if (formActions && !document.getElementById('reset-form-btn')) {
        const resetButton = document.createElement('button');
        resetButton.id = 'reset-form-btn';
        resetButton.type = 'button';
        resetButton.className = 'btn-secondary flex items-center';
        resetButton.innerHTML = '<i class="fas fa-undo-alt mr-2"></i> Reset Form';
        resetButton.onclick = function() {
            if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                resetFormFields();
                showTemporaryNotification('Form has been reset', 'info');
            }
        };

        const cancelButton = formActions.querySelector('.btn-secondary:not(#reset-form-btn)');
        if (cancelButton) formActions.insertBefore(resetButton, cancelButton);
        else formActions.prepend(resetButton);
    }
}

// ==================== DOM CONTENT LOADED ====================

document.addEventListener('DOMContentLoaded', function() {
    setupPhotoUpload();
    initializePhoneNumbers();
    initializeTenants();

    const addPhoneBtn = document.getElementById('add-phone-btn');
    if (addPhoneBtn) addPhoneBtn.addEventListener('click', () => addPhoneNumber());

    document.querySelectorAll('.phone-input').forEach(input => {
        input.addEventListener('input', formatPhoneNumber);
    });

    document.querySelectorAll('.remove-phone-btn').forEach(btn => {
        btn.addEventListener('click', function() { removePhoneNumber(this); });
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
    if (addTenantsBtn) addTenantsBtn.addEventListener('click', openTenantModal);

    const tenantCountSelect = document.getElementById('tenant-count');
    if (tenantCountSelect) {
        tenantCountSelect.addEventListener('change', function() {
            const count = parseInt(this.value);
            if (count > 0) {
                generateTenantForms(count);
                if (tenants.length > 0) prefillTenantForms();
            } else {
                const formsContainer = document.getElementById('tenant-forms-container');
                if (formsContainer) formsContainer.innerHTML = '';
            }
        });
    }

    const sendInvitationYes = document.getElementById('send_invitation_yes');
    if (sendInvitationYes && sendInvitationYes.checked) {
        setTimeout(() => checkAvailableChannels(), 100);
    }

    const landlordIdSelect = document.getElementById('landlord_id');
    const landlordNameInput = document.getElementById('landlord_name');
    const landlordEmailInput = document.getElementById('landlord_email');

    if (landlordIdSelect) {
        landlordIdSelect.addEventListener('change', function() {
            if (this.value) {
                setLandlordMode('existing');
            } else if (INITIAL_LANDLORD_MODE !== 'new') {
                setLandlordMode('choose');
            }

            if (sendInvitationYes && sendInvitationYes.checked) {
                checkAvailableChannels();
            }
            handleLandlordChange();
        });
    }

    if (landlordNameInput) {
        landlordNameInput.addEventListener('input', function() {
            if (this.value.trim()) {
                setLandlordMode('new');
            }
            if (sendInvitationYes && sendInvitationYes.checked) {
                checkAvailableChannels();
            }
            handleLandlordChange();
        });
    }

    if (landlordEmailInput) {
        landlordEmailInput.addEventListener('input', function() {
            if (this.value.trim()) {
                setLandlordMode('new');
            }
            if (sendInvitationYes && sendInvitationYes.checked) checkAvailableChannels();
        });
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList && e.target.classList.contains('phone-input')) {
            if (e.target.value.replace(/\D/g, '').length > 0) {
                setLandlordMode('new');
            }
            if (sendInvitationYes && sendInvitationYes.checked) checkAvailableChannels();
        }
    });

    const registrationPlanSelect = document.getElementById('registration_plan_id');
    const manualZoneInput = document.getElementById('manual_zone');

    if (registrationPlanSelect) {
        registrationPlanSelect.addEventListener('change', function() {
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

                applyRegionAreaFromPlan(selectedOption);
            } else {
                const registrationPatternInput = document.getElementById('registration_pattern');
                const refreshPatternButton = document.getElementById('refresh-pattern');

                if (registrationPatternInput) registrationPatternInput.value = '';
                if (refreshPatternButton) refreshPatternButton.classList.add('hidden');
                updateZoneAndSection(null);
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

    if (document.getElementById('property-map') && typeof google !== 'undefined' && google.maps) {
        initPropertyCoordinatePicker();
    }

    setLandlordMode(INITIAL_LANDLORD_MODE || 'choose', { clearOther: false });

    // ---- Submit validation ----
    const form = document.getElementById('property-form');
    const submitButton = document.getElementById('submit-button');
    const propertyTypeSelect = document.getElementById('property_type_id');
    const customPropertyTypeInput = document.getElementById('custom_property_type');
    const registrationPatternInput = document.getElementById('registration_pattern');
    const propertyNameInput = document.getElementById('property_name');

    if (form) {
        form.addEventListener('submit', function(e) {
            const landlordId = landlordIdSelect?.value;
            const landlordName = landlordNameInput?.value;
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
                alert('Please either select an existing landlord or provide a name for a new landlord.');
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

            const regionSelect = document.getElementById('region');
            const areaInput   = document.getElementById('area');

            if (!regionSelect?.value) {
                e.preventDefault();
                alert('Please select the region where the property is located.');
                if (regionSelect) regionSelect.focus();
                return false;
            }

            if (!areaInput?.value?.trim()) {
                e.preventDefault();
                alert('Please enter the area/neighbourhood of the property.');
                if (areaInput) areaInput.focus();
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

            const digitalAddress = document.getElementById('digital_address')?.value?.trim();
            const hasCoords = !!(
                document.getElementById('latitude')?.value &&
                document.getElementById('longitude')?.value
            );

            if (digitalAddress && !hasCoords) {
                const proceed = confirm(
                    'You entered a digital address but haven\'t confirmed a location on the map.\n\n' +
                    'The system will try to geocode the address on save. This may fail for Ghana Post digital addresses.\n\n' +
                    'Proceed anyway?'
                );
                if (!proceed) {
                    e.preventDefault();
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
                if (tenants.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one tenant for this rented property.');
                    return false;
                }

                for (let i = 0; i < tenants.length; i++) {
                    if (!tenants[i].name || !tenants[i].phone) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1} is missing required information (name and phone).`);
                        return false;
                    }
                    if (tenants[i].phone.replace(/\D/g, '').length < 10) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1} has an invalid phone number.`);
                        return false;
                    }
                    if (!tenants[i].channels || tenants[i].channels.length === 0) {
                        e.preventDefault();
                        alert(`Tenant ${i + 1}: Please select at least one invitation channel.`);
                        return false;
                    }
                    for (const ch of tenants[i].channels) {
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
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving Property...';
            }

            return true;
        });
    }

    const registrationDateInput = document.getElementById('registration_date');
    if (registrationDateInput && !registrationDateInput.value) {
        registrationDateInput.value = new Date().toISOString().split('T')[0];
    }

    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) setTimeout(() => { successMessage.style.display = 'none'; }, 5000);

    const errorMessageAlert = document.querySelector('.bg-red-100');
    if (errorMessageAlert) setTimeout(() => { errorMessageAlert.style.display = 'none'; }, 8000);

    addResetButton();
});
</script>
@endsection

@push('styles')
<style>
/* ==================== PHOTO UPLOAD STYLES ==================== */
.photo-upload-area { transition: all 0.3s ease; }
.photo-upload-area:hover {
    border-color: var(--primary) !important;
    background-color: rgba(114, 103, 240, 0.05) !important;
}
.photo-preview-item { transition: all 0.3s ease; position: relative; }
.photo-preview-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.photo-preview-item .make-primary-btn,
.photo-preview-item .remove-photo-btn { transition: all 0.2s ease; cursor: pointer; }
.photo-preview-item .make-primary-btn:hover:not(:disabled),
.photo-preview-item .remove-photo-btn:hover { transform: scale(1.05); }

/* ==================== PROPERTY TYPE CARD STYLES ==================== */
.property-type-card { transition: all 0.3s ease; border: 2px solid transparent; cursor: pointer; }
.property-type-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
.property-type-card.active { border-color: var(--primary); background-color: var(--primary) + '10'; }

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
.modern-radio-option--disabled input { cursor: not-allowed; }
.modern-radio-input { position: absolute; opacity: 0; width: 0; height: 0; }
.modern-radio-label { display: block; cursor: pointer; padding: 0; margin: 0; }
.modern-radio-content { display: flex; align-items: center; padding: 20px; position: relative; }
.modern-radio-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-right: 16px; font-size: 20px; transition: all 0.3s ease;
    background: var(--bg-tertiary);
}
.modern-radio-option--checked .modern-radio-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    color: white !important;
    transform: scale(1.1);
}
.modern-radio-text { flex: 1; }
.modern-radio-title { font-weight: 600; font-size: 16px; color: var(--text-primary); margin-bottom: 4px; }
.modern-radio-description { font-size: 14px; color: var(--text-secondary); line-height: 1.4; }
.modern-radio-check {
    width: 24px; height: 24px; border-radius: 50%;
    border: 2px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s ease; background: white;
}
.modern-radio-option--checked .modern-radio-check {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: white !important;
    transform: scale(1.1);
}
.modern-radio-check i { font-size: 12px; opacity: 0; transition: opacity 0.3s ease; }
.modern-radio-option--checked .modern-radio-check i { opacity: 1 !important; }

/* ==================== MAP PICKER STYLES ==================== */
#property-map { background-color: var(--bg-tertiary); border-radius: 0.5rem; }
#coordinate-status { transition: all 0.3s ease-in-out; }
#coordinate-readout { transition: all 0.3s ease-in-out; }
#clear-coords-btn { transition: all 0.2s ease; }

/* ==================== CUSTOM CONTAINER STYLES ==================== */
#custom_property_type_container {
    border-left: 4px solid #6B7280;
    transition: all 0.3s ease-in-out;
    animation: slideDown 0.3s ease-out;
}
#selected-property-type { border-left: 4px solid; animation: slideDown 0.3s ease-out; }

/* ==================== PHONE NUMBER STYLES ==================== */
.phone-number-group { transition: all 0.3s ease; }
.remove-phone-btn { transition: all 0.2s ease; }
.remove-phone-btn:hover { background-color: rgba(239, 68, 68, 0.1); border-radius: 0.25rem; }

/* ==================== SECTION BORDER STYLES ==================== */
#landlord-invitation-section { border-left: 4px solid #8B5CF6; }
#invitation-channels-container { transition: all 0.3s ease-in-out; }
#tenant-invitation-section { border-left: 4px solid #10B981; }
#tenant-invitation-container { transition: all 0.3s ease-in-out; }
#available-channels-info { border-left: 4px solid #3B82F6; }
#no-channels-warning { border-left: 4px solid #F59E0B; }
#naming-preview-container { border-left: 4px solid var(--primary); }
#global-sequence-info { border-left: 4px solid #3b82f6; }

/* ==================== DUPLICATE BANNER ==================== */
#duplicate-property-banner { animation: slideDown 0.3s ease-out; }
#duplicate-property-banner summary { user-select: none; }
#duplicate-property-banner summary::-webkit-details-marker { display: none; }
#duplicate-property-banner summary::before {
    content: "▸ ";
    display: inline-block;
    transition: transform 0.2s ease;
    margin-right: 0.25rem;
}
#duplicate-property-banner details[open] summary::before { transform: rotate(90deg); }

/* ==================== LANDLORD BLOCK TRANSITIONS ==================== */
#existing-landlord-block,
#new-landlord-block { animation: slideDown 0.25s ease-out; }

/* ==================== ANIMATIONS ==================== */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.fa-spinner { animation: spin 1s linear infinite; }

/* ==================== PROGRESS BAR STYLES ==================== */
#progress-bar { transition: width 0.5s ease-in-out; }
#custom-type-chars { transition: color 0.3s ease; }

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
.btn-primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}
.btn-secondary:hover { background-color: var(--bg-tertiary); transform: translateY(-1px); }

/* ==================== CARD STYLES ==================== */
.card { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid var(--border-color); border-radius: 0.75rem; }

/* ==================== SECTION HEADER ==================== */
h3 { font-weight: 600; color: var(--text-primary); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--primary); }

/* ==================== MESSAGE STYLES ==================== */
.bg-green-100 { background-color: rgba(209, 250, 229, 0.9); border-color: rgba(16, 185, 129, 0.3); }
.bg-red-100 { background-color: rgba(254, 226, 226, 0.9); border-color: rgba(239, 68, 68, 0.3); }

/* ==================== PLAN STATUS ALERT STYLES ==================== */
#plan-status-alert .bg-yellow-50 { background-color: rgba(254, 252, 232, 0.9); border-color: rgba(250, 204, 21, 0.3); }
#plan-status-alert .bg-red-50 { background-color: rgba(254, 242, 242, 0.9); border-color: rgba(248, 113, 113, 0.3); }
#plan-status-alert .bg-blue-50 { background-color: rgba(239, 246, 255, 0.9); border-color: rgba(147, 197, 253, 0.3); }
#plan-status-alert .bg-green-50 { background-color: rgba(240, 253, 244, 0.9); border-color: rgba(134, 239, 172, 0.3); }

/* ==================== PATTERN STYLES ==================== */
#registration_pattern { font-weight: 600; background-color: var(--bg-tertiary) !important; letter-spacing: 0.5px; }
#refresh-pattern { background: none; border: none; cursor: pointer; font-size: 0.75rem; transition: color 0.2s; }
#refresh-pattern:hover { color: var(--primary); }
#pattern-status { transition: all 0.3s ease; }
#pattern-validation { transition: all 0.3s ease; }
#next-pattern-preview { color: var(--text-secondary); font-style: italic; }
#next-pattern-text { color: var(--text-primary); font-weight: 600; }
#global-sequence-badge { font-size: 0.7rem; font-weight: 600; }

/* ==================== FOCUS STYLES ==================== */
.property-type-card:focus { outline: 2px solid var(--primary); outline-offset: 2px; }
.modern-radio-option:focus-within { outline: 2px solid var(--primary); outline-offset: 2px; }
#property-form input:focus,
#property-form select:focus,
#property-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 768px) {
    .modern-radio-content { padding: 16px; }
    .modern-radio-icon { width: 40px; height: 40px; font-size: 18px; margin-right: 12px; }
    .modern-radio-title { font-size: 14px; }
    .modern-radio-description { font-size: 12px; }
    #property-map { height: 220px !important; }
}
@media (max-width: 640px) {
    #property-type-cards { grid-template-columns: 1fr; }
    .modern-radio-option { margin-bottom: 0.5rem; }
}

/* ==================== PRINT STYLES ==================== */
@media print {
    .btn-primary, .btn-secondary, #refresh-pattern, #toggle-location-override, #add-phone-btn, #property-map { display: none !important; }
    .card { box-shadow: none; border: 1px solid #000; }
    .modern-radio-option { border: 1px solid #000 !important; }
}

/* ==================== SCOPED .hidden OVERRIDE ==================== */
#custom_property_type_container.hidden,
#selected-property-type.hidden,
#invitation-channels-container.hidden,
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
#phone-formatted-info.hidden,
#clear-coords-btn.hidden,
#coordinate-status.hidden,
#coordinate-readout.hidden,
#photo-preview-grid.hidden,
#global-sequence-row.hidden,
#global-sequence-badge.hidden,
#refresh-pattern.hidden,
#remove-phone-btn.hidden,
.remove-phone-btn.hidden,
.phone-number-group.hidden,
#existing-landlord-block.hidden,
#new-landlord-block.hidden,
#landlord-mode-hint.hidden,
#duplicate-property-banner.hidden {
    display: none !important;
}

/* ==================== UTILITY CLASSES ==================== */
.form-group { margin-bottom: 1rem; }
.required::after { content: " *"; color: var(--danger); }

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