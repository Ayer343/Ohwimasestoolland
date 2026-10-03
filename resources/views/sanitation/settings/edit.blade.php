{{-- resources/views/sanitation/settings/edit.blade.php --}}

@php
    use App\Models\SanitationSetting;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // ✅ Canonical option lists from the model — single source of truth
    $weekdays              = SanitationSetting::WEEKDAYS;
    $collectionFrequencies = SanitationSetting::COLLECTION_FREQUENCIES;
    $wasteTypes            = SanitationSetting::WASTE_TYPES;

    // ✅ Effective values (fall back to sane defaults when settings are empty)
    $effectiveFrequencies = $settings->default_collection_frequencies
        ?: ['weekly'];
    $effectiveCollectionDays = $settings->default_collection_days
        ?: ($settings->operational_days ?: ['Monday']);
    $effectiveWasteTypes = $settings->default_waste_types
        ?: ['general'];

    // ✅ Operational days — normalize to canonical case once
    $operationalDays = $settings->operational_days ?: [];
    $operationalDays = array_values(array_intersect(SanitationSetting::WEEKDAYS, $operationalDays));

    // ============================================================
    // ✅ Google Maps + Ghana Post GPS status
    // ------------------------------------------------------------
    // Priority:
    //   1. Controller-provided $googleMapsStatus (preferred)
    //   2. Fall back to config('services.google_maps.*')
    //
    // The fallback is defensive so this blade renders even if the
    // controller hasn't been updated yet.
    // ============================================================
    if (!isset($googleMapsStatus) || !is_array($googleMapsStatus)) {
        $masked = function (?string $key): ?string {
            if (empty($key)) {
                return null;
            }
            $len = strlen($key);
            if ($len <= 8) {
                return str_repeat('•', $len);
            }
            return substr($key, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($key, -4);
        };

        $googleMapsStatus = [
            'api_key_set'         => !empty(config('services.google_maps.key')),
            'browser_key_set'     => !empty(config('services.google_maps.browser_key')),
            'server_key_set'      => !empty(config('services.google_maps.server_key')),

            'api_key_preview'     => $masked(config('services.google_maps.key')),
            'browser_key_preview' => $masked(config('services.google_maps.browser_key')),
            'server_key_preview'  => $masked(config('services.google_maps.server_key')),

            'default_center' => [
                'lat' => (float) config('services.google_maps.maps.default_center.lat', 5.6037),
                'lng' => (float) config('services.google_maps.maps.default_center.lng', -0.1870),
            ],
            'default_zoom'    => (int) config('services.google_maps.maps.default_zoom', 14),
            'default_country' => config('services.google_maps.maps.default_country', 'GH'),
            'default_region'  => config('services.google_maps.maps.default_region', 'GH'),

            'ghana_post' => [
                'enabled'         => (bool) config('services.google_maps.geocoding.ghana_post_enabled', false),
                'url'             => config('services.google_maps.geocoding.ghana_post_url', 'https://api.ghanapostgps.com/v1/address'),
                'api_key_set'     => !empty(config('services.google_maps.geocoding.ghana_post_api_key')),
                'api_key_preview' => $masked(config('services.google_maps.geocoding.ghana_post_api_key')),
            ],

            'env_writable' => file_exists(base_path('.env')) && is_writable(base_path('.env')),
        ];
    }

    $envWritable = (bool) ($googleMapsStatus['env_writable'] ?? false);
@endphp

@extends($layout)

@section('title', 'Edit Sanitation Settings')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-edit mr-2" style="color: var(--primary);"></i>
                    Edit Sanitation Settings
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Update your sanitation company settings and configuration
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sanitation.settings.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Validation summary -->
        @if($errors->any())
            <div class="card p-4 mb-6 border-l-4" style="border-left-color: var(--danger);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-xl mr-3" style="color: var(--danger);"></i>
                    <div>
                        <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                            Please fix the following:
                        </h4>
                        <ul class="text-sm list-disc list-inside" style="color: var(--text-secondary);">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Form -->
        <div class="card p-6">
            <form method="POST"
                  action="{{ route('sanitation.settings.store') }}"
                  enctype="multipart/form-data"
                  id="sanitation-settings-form">
                @csrf

                <div class="space-y-8">

                    {{-- ========================================================= --}}
                    {{-- COMPANY DETAILS                                            --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                            Company Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="company_name" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Company Name *
                                </label>
                                <input type="text" name="company_name" id="company_name"
                                       value="{{ old('company_name', $settings->company_name) }}"
                                       class="w-full p-2 border rounded-lg @error('company_name') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       required>
                                @error('company_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="company_short_name" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Short Name
                                </label>
                                <input type="text" name="company_short_name" id="company_short_name"
                                       value="{{ old('company_short_name', $settings->company_short_name) }}"
                                       class="w-full p-2 border rounded-lg @error('company_short_name') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('company_short_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="company_email" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Company Email
                                </label>
                                <input type="email" name="company_email" id="company_email"
                                       value="{{ old('company_email', $settings->company_email) }}"
                                       class="w-full p-2 border rounded-lg @error('company_email') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('company_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="company_phone" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Company Phone
                                </label>
                                <input type="text" name="company_phone" id="company_phone"
                                       value="{{ old('company_phone', $settings->company_phone) }}"
                                       class="w-full p-2 border rounded-lg @error('company_phone') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('company_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="company_address" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Company Address
                            </label>
                            <textarea name="company_address" id="company_address" rows="3"
                                      class="w-full p-2 border rounded-lg @error('company_address') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Enter full company address">{{ old('company_address', $settings->company_address) }}</textarea>
                            @error('company_address')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <label for="company_logo" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Company Logo
                            </label>
                            <div class="flex items-center space-x-4">
                                @if($settings->logo_url)
                                    <img src="{{ $settings->logo_url }}" alt="Current Logo"
                                         class="w-20 h-20 rounded-lg object-cover border"
                                         style="border-color: var(--border-color);">
                                @endif
                                <div class="flex-1">
                                    <input type="file" name="company_logo" id="company_logo"
                                           class="w-full p-2 border rounded-lg @error('company_logo') border-red-500 @enderror"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Max size: 2MB. Supported formats: JPEG, PNG, JPG, GIF, SVG
                                    </p>
                                </div>
                            </div>
                            @error('company_logo')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- REGISTRATION DETAILS                                       --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-id-card mr-2" style="color: var(--info);"></i>
                            Registration Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="registration_number" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Registration Number
                                </label>
                                <input type="text" name="registration_number" id="registration_number"
                                       value="{{ old('registration_number', $settings->registration_number) }}"
                                       class="w-full p-2 border rounded-lg @error('registration_number') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('registration_number')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tax_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Tax ID
                                </label>
                                <input type="text" name="tax_id" id="tax_id"
                                       value="{{ old('tax_id', $settings->tax_id) }}"
                                       class="w-full p-2 border rounded-lg @error('tax_id') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('tax_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="license_number" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    License Number
                                </label>
                                <input type="text" name="license_number" id="license_number"
                                       value="{{ old('license_number', $settings->license_number) }}"
                                       class="w-full p-2 border rounded-lg @error('license_number') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('license_number')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- CONTACT PERSON                                             --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user-tie mr-2" style="color: var(--success);"></i>
                            Contact Person
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="contact_person_name" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Name
                                </label>
                                <input type="text" name="contact_person_name" id="contact_person_name"
                                       value="{{ old('contact_person_name', $settings->contact_person_name) }}"
                                       class="w-full p-2 border rounded-lg @error('contact_person_name') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('contact_person_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="contact_person_phone" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Phone
                                </label>
                                <input type="text" name="contact_person_phone" id="contact_person_phone"
                                       value="{{ old('contact_person_phone', $settings->contact_person_phone) }}"
                                       class="w-full p-2 border rounded-lg @error('contact_person_phone') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('contact_person_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="contact_person_email" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Email
                                </label>
                                <input type="email" name="contact_person_email" id="contact_person_email"
                                       value="{{ old('contact_person_email', $settings->contact_person_email) }}"
                                       class="w-full p-2 border rounded-lg @error('contact_person_email') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('contact_person_email')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- OPERATIONAL HOURS                                          --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                            Operational Hours
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="operational_start_time" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Start Time
                                </label>
                                <input type="time" name="operational_start_time" id="operational_start_time"
                                       value="{{ old('operational_start_time', $settings->operational_start_time ? \Carbon\Carbon::parse($settings->operational_start_time)->format('H:i') : '') }}"
                                       class="w-full p-2 border rounded-lg @error('operational_start_time') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @error('operational_start_time')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="operational_end_time" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    End Time
                                </label>
                                <input type="time" name="operational_end_time" id="operational_end_time"
                                       value="{{ old('operational_end_time', $settings->operational_end_time ? \Carbon\Carbon::parse($settings->operational_end_time)->format('H:i') : '') }}"
                                       class="w-full p-2 rounded-lg @error('operational_end_time') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                @error('operational_end_time')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Operational Days
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach($weekdays as $day)
                                    @php
                                        $dayLower = strtolower($day);
                                        $checked  = is_array(old('operational_days'))
                                            ? in_array($day, old('operational_days'))
                                            : in_array($day, $operationalDays);
                                    @endphp
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox"
                                               name="operational_days[]"
                                               value="{{ $day }}"
                                               {{ $checked ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500"
                                               style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $day }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Days the sanitation company is open for business.
                            </p>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- ✅ COLLECTION SCHEDULE DEFAULTS                             --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                            Collection Schedule Defaults
                        </h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            These defaults pre-fill the "Link Property" form when sanitation personnel
                            set up waste collection for a new property.
                        </p>

                        {{-- Collection Frequencies --}}
                        <div class="mb-6">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Default Collection Frequencies
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach($collectionFrequencies as $freq)
                                    @php
                                        $checked = is_array(old('default_collection_frequencies'))
                                            ? in_array($freq, old('default_collection_frequencies'))
                                            : in_array($freq, $effectiveFrequencies);
                                    @endphp
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox"
                                               name="default_collection_frequencies[]"
                                               value="{{ $freq }}"
                                               {{ $checked ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500"
                                               style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ ucfirst($freq) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('default_collection_frequencies')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Which frequencies personnel can choose from.
                            </p>
                        </div>

                        {{-- Collection Days --}}
                        <div class="mb-6">
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Default Collection Days
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach($weekdays as $day)
                                    @php
                                        $checked = is_array(old('default_collection_days'))
                                            ? in_array($day, old('default_collection_days'))
                                            : in_array($day, $effectiveCollectionDays);
                                    @endphp
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox"
                                               name="default_collection_days[]"
                                               value="{{ $day }}"
                                               {{ $checked ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500"
                                               style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $day }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('default_collection_days')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Days collections are typically scheduled.
                                <strong>Must be within operational days.</strong>
                            </p>
                        </div>

                        {{-- Default Waste Types --}}
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Default Waste Types
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                @foreach($wasteTypes as $type)
                                    @php
                                        $checked = is_array(old('default_waste_types'))
                                            ? in_array($type, old('default_waste_types'))
                                            : in_array($type, $effectiveWasteTypes);
                                    @endphp
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox"
                                               name="default_waste_types[]"
                                               value="{{ $type }}"
                                               {{ $checked ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500"
                                               style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ ucfirst($type) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('default_waste_types')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Waste categories accepted by default.
                            </p>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- SERVICE AREAS                                              --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-map-marked-alt mr-2" style="color: var(--info);"></i>
                            Service Areas
                        </h3>
                        <div>
                            <label for="service_areas" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Service Areas (comma separated)
                            </label>
                            <input type="text" name="service_areas" id="service_areas"
                                   value="{{ old('service_areas', is_array($settings->service_areas) ? implode(', ', $settings->service_areas) : $settings->service_areas) }}"
                                   class="w-full p-2 border rounded-lg @error('service_areas') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Zone A, Zone B, Central District">
                            @error('service_areas')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Enter service areas separated by commas
                            </p>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- PRICING & FEES                                             --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-money-bill mr-2" style="color: var(--danger);"></i>
                            Pricing & Fees
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="default_collection_fee" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Default Collection Fee (GH₵)
                                </label>
                                <input type="number" name="default_collection_fee" id="default_collection_fee"
                                       value="{{ old('default_collection_fee', $settings->default_collection_fee) }}"
                                       step="0.01" min="0"
                                       class="w-full p-2 border rounded-lg @error('default_collection_fee') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="0.00">
                                @error('default_collection_fee')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="emergency_collection_fee" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Emergency Collection Fee (GH₵)
                                </label>
                                <input type="number" name="emergency_collection_fee" id="emergency_collection_fee"
                                       value="{{ old('emergency_collection_fee', $settings->emergency_collection_fee) }}"
                                       step="0.01" min="0"
                                       class="w-full p-2 border rounded-lg @error('emergency_collection_fee') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="0.00">
                                @error('emergency_collection_fee')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="late_fee_percentage" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Late Fee Percentage (%)
                                </label>
                                <input type="number" name="late_fee_percentage" id="late_fee_percentage"
                                       value="{{ old('late_fee_percentage', $settings->late_fee_percentage) }}"
                                       step="0.01" min="0" max="100"
                                       class="w-full p-2 border rounded-lg @error('late_fee_percentage') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="0.00">
                                @error('late_fee_percentage')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- VEHICLE FLEET                                              --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-truck mr-2" style="color: var(--info);"></i>
                            Vehicle Fleet
                        </h3>
                        <div>
                            <label for="vehicle_types" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Vehicle Types (comma separated)
                            </label>
                            <input type="text" name="vehicle_types" id="vehicle_types"
                                   value="{{ old('vehicle_types', is_array($settings->vehicle_types) ? implode(', ', $settings->vehicle_types) : $settings->vehicle_types) }}"
                                   class="w-full p-2 border rounded-lg @error('vehicle_types') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Truck, Van, Compactor">
                            @error('vehicle_types')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Enter vehicle types separated by commas
                            </p>
                        </div>

                        <div class="mt-4">
                            <label for="default_worker_count_per_vehicle" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Default Workers Per Vehicle
                            </label>
                            <input type="number" name="default_worker_count_per_vehicle" id="default_worker_count_per_vehicle"
                                   value="{{ old('default_worker_count_per_vehicle', $settings->default_worker_count_per_vehicle ?? 2) }}"
                                   min="1" max="10"
                                   class="w-full p-2 border rounded-lg @error('default_worker_count_per_vehicle') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @error('default_worker_count_per_vehicle')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- BRANDING                                                   --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-palette mr-2" style="color: var(--info);"></i>
                            Branding
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="primary_color" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Primary Color
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" id="primary_color"
                                           value="{{ old('primary_color', $settings->primary_color ?? '#10B981') }}"
                                           class="w-12 h-12 p-1 border rounded cursor-pointer"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    {{-- ✅ Single submitted field, synced from the picker --}}
                                    <input type="text" name="primary_color" id="primary_color_text"
                                           value="{{ old('primary_color', $settings->primary_color ?? '#10B981') }}"
                                           class="flex-1 p-2 border rounded @error('primary_color') border-red-500 @enderror"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="#10B981">
                                </div>
                                @error('primary_color')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Enter a valid hex color code (e.g., #10B981 or #1a2b3c)
                                </p>
                            </div>

                            <div>
                                <label for="secondary_color" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Secondary Color
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" id="secondary_color"
                                           value="{{ old('secondary_color', $settings->secondary_color ?? '#059669') }}"
                                           class="w-12 h-12 p-1 border rounded cursor-pointer"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <input type="text" name="secondary_color" id="secondary_color_text"
                                           value="{{ old('secondary_color', $settings->secondary_color ?? '#059669') }}"
                                           class="flex-1 p-2 border rounded @error('secondary_color') border-red-500 @enderror"
                                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                           placeholder="#059669">
                                </div>
                                @error('secondary_color')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Enter a valid hex color code (e.g., #059669 or #2d3748)
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="company_description" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Company Description
                            </label>
                            <textarea name="company_description" id="company_description" rows="4"
                                      class="w-full p-2 border rounded-lg @error('company_description') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Describe your sanitation company...">{{ old('company_description', $settings->company_description) }}</textarea>
                            @error('company_description')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <label for="website_url" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Website URL
                            </label>
                            <input type="url" name="website_url" id="website_url"
                                   value="{{ old('website_url', $settings->website_url) }}"
                                   class="w-full p-2 border rounded-lg @error('website_url') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="https://www.example.com">
                            @error('website_url')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- SOCIAL MEDIA                                               --}}
                    {{-- ========================================================= --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-share-alt mr-2" style="color: var(--info);"></i>
                            Social Media
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="facebook_url" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fab fa-facebook mr-2" style="color: #1877F2;"></i>
                                    Facebook URL
                                </label>
                                <input type="url" name="facebook_url" id="facebook_url"
                                       value="{{ old('facebook_url', $settings->facebook_url) }}"
                                       class="w-full p-2 border rounded-lg @error('facebook_url') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="https://www.facebook.com/yourpage">
                                @error('facebook_url')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="twitter_url" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fab fa-twitter mr-2" style="color: #1DA1F2;"></i>
                                    Twitter URL
                                </label>
                                <input type="url" name="twitter_url" id="twitter_url"
                                       value="{{ old('twitter_url', $settings->twitter_url) }}"
                                       class="w-full p-2 border rounded-lg @error('twitter_url') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="https://twitter.com/yourhandle">
                                @error('twitter_url')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="instagram_url" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fab fa-instagram mr-2" style="color: #E4405F;"></i>
                                    Instagram URL
                                </label>
                                <input type="url" name="instagram_url" id="instagram_url"
                                       value="{{ old('instagram_url', $settings->instagram_url) }}"
                                       class="w-full p-2 border rounded-lg @error('instagram_url') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="https://www.instagram.com/yourpage">
                                @error('instagram_url')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="linkedin_url" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fab fa-linkedin mr-2" style="color: #0A66C2;"></i>
                                    LinkedIn URL
                                </label>
                                <input type="url" name="linkedin_url" id="linkedin_url"
                                       value="{{ old('linkedin_url', $settings->linkedin_url) }}"
                                       class="w-full p-2 border rounded-lg @error('linkedin_url') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="https://www.linkedin.com/company/yourcompany">
                                @error('linkedin_url')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ========================================================= --}}
                    {{-- SUBMIT                                                     --}}
                    {{-- ========================================================= --}}
                    <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                        <a href="{{ route('sanitation.settings.index') }}" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary" id="save-settings-btn">
                            <i class="fas fa-save mr-2"></i> Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- ============================================================= --}}
        {{-- ✅ NEW: GOOGLE MAPS & LOCATION SERVICES                        --}}
        {{-- ------------------------------------------------------------- --}}
        {{-- Separate form → posts to `sanitation.settings.environment.update` --}}
        {{-- Writes whitelisted keys to `.env` and clears config cache.    --}}
        {{-- ============================================================= --}}
        <div class="card p-6 mt-6" id="google-maps-settings-card">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                        Google Maps & Location Services
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Credentials used by the coordinate picker on property create/edit pages,
                        and by the server-side geocoder for waste-collection routes.
                    </p>
                </div>

                @if($envWritable)
                    <span class="text-xs px-3 py-1 rounded-full flex items-center gap-1"
                          style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i> .env writable
                    </span>
                @else
                    <span class="text-xs px-3 py-1 rounded-full flex items-center gap-1"
                          style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-times-circle"></i> .env not writable
                    </span>
                @endif
            </div>

            @if(!$envWritable)
                <div class="p-4 mb-4 rounded border-l-4"
                     style="background: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-exclamation-triangle text-xl" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-medium text-sm" style="color: var(--text-primary);">
                                Environment file is not writable
                            </h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                The <code>.env</code> file could not be updated from the browser.
                                Ask your hosting provider to grant write permission on <code>.env</code>, or
                                edit the file manually. You can still fill in the form below to keep a
                                record of the values you plan to use — they just won't be persisted.
                            </p>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <strong>Linux / macOS:</strong>
                                <code>chmod 664 .env &amp;&amp; chown www-data:www-data .env</code>
                                <br>
                                <strong>Windows (XAMPP):</strong>
                                <code>icacls "C:\xampp\htdocs\Hilltop\.env" /grant "Everyone:F"</code>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('sanitation.settings.environment.update') }}"
                  id="google-maps-env-form"
                  autocomplete="off">
                @csrf

                {{-- ===================================================== --}}
                {{-- API KEYS                                              --}}
                {{-- ===================================================== --}}
                <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-key mr-2" style="color: var(--primary);"></i>
                    API Keys
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Master key --}}
                    <div>
                        <label for="GOOGLE_MAPS_API_KEY" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            GOOGLE_MAPS_API_KEY
                            <span class="text-xs font-normal text-gray-500">(master fallback)</span>
                        </label>
                        <input type="text" name="GOOGLE_MAPS_API_KEY" id="GOOGLE_MAPS_API_KEY"
                               value="{{ old('GOOGLE_MAPS_API_KEY') }}"
                               placeholder="{{ $googleMapsStatus['api_key_preview'] ?? 'not set' }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Leave blank to keep the current value.
                        </p>
                    </div>

                    {{-- Browser key --}}
                    <div>
                        <label for="GOOGLE_MAPS_BROWSER_KEY" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            GOOGLE_MAPS_BROWSER_KEY
                            <span class="text-xs font-normal text-gray-500">(domain-restricted)</span>
                        </label>
                        <input type="text" name="GOOGLE_MAPS_BROWSER_KEY" id="GOOGLE_MAPS_BROWSER_KEY"
                               value="{{ old('GOOGLE_MAPS_BROWSER_KEY') }}"
                               placeholder="{{ $googleMapsStatus['browser_key_preview'] ?? 'not set' }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Restrict to your domain in Google Cloud Console.
                        </p>
                    </div>

                    {{-- Server key --}}
                    <div>
                        <label for="GOOGLE_MAPS_SERVER_KEY" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            GOOGLE_MAPS_SERVER_KEY
                            <span class="text-xs font-normal text-gray-500">(server-only)</span>
                        </label>
                        <input type="text" name="GOOGLE_MAPS_SERVER_KEY" id="GOOGLE_MAPS_SERVER_KEY"
                               value="{{ old('GOOGLE_MAPS_SERVER_KEY') }}"
                               placeholder="{{ $googleMapsStatus['server_key_preview'] ?? 'not set' }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Restrict to the Geocoding API only.
                        </p>
                    </div>
                </div>

                {{-- ===================================================== --}}
                {{-- DEFAULT MAP BEHAVIOUR                                 --}}
                {{-- ===================================================== --}}
                <h4 class="text-sm font-semibold mt-6 mb-3 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-map mr-2" style="color: var(--info);"></i>
                    Default Map Behaviour
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="GOOGLE_MAPS_DEFAULT_LAT" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Default Latitude
                        </label>
                        <input type="text" name="GOOGLE_MAPS_DEFAULT_LAT" id="GOOGLE_MAPS_DEFAULT_LAT"
                               value="{{ old('GOOGLE_MAPS_DEFAULT_LAT', $googleMapsStatus['default_center']['lat']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="5.6037">
                    </div>

                    <div>
                        <label for="GOOGLE_MAPS_DEFAULT_LNG" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Default Longitude
                        </label>
                        <input type="text" name="GOOGLE_MAPS_DEFAULT_LNG" id="GOOGLE_MAPS_DEFAULT_LNG"
                               value="{{ old('GOOGLE_MAPS_DEFAULT_LNG', $googleMapsStatus['default_center']['lng']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="-0.1870">
                    </div>

                    <div>
                        <label for="GOOGLE_MAPS_DEFAULT_ZOOM" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Default Zoom
                        </label>
                        <input type="number" name="GOOGLE_MAPS_DEFAULT_ZOOM" id="GOOGLE_MAPS_DEFAULT_ZOOM"
                               min="1" max="22"
                               value="{{ old('GOOGLE_MAPS_DEFAULT_ZOOM', $googleMapsStatus['default_zoom']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="14">
                    </div>

                    <div>
                        <label for="GOOGLE_MAPS_DEFAULT_COUNTRY" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Default Country
                        </label>
                        <input type="text" name="GOOGLE_MAPS_DEFAULT_COUNTRY" id="GOOGLE_MAPS_DEFAULT_COUNTRY"
                               maxlength="2"
                               value="{{ old('GOOGLE_MAPS_DEFAULT_COUNTRY', $googleMapsStatus['default_country']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm uppercase"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="GH">
                    </div>

                    <div>
                        <label for="GOOGLE_MAPS_DEFAULT_REGION" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Default Region
                        </label>
                        <input type="text" name="GOOGLE_MAPS_DEFAULT_REGION" id="GOOGLE_MAPS_DEFAULT_REGION"
                               maxlength="2"
                               value="{{ old('GOOGLE_MAPS_DEFAULT_REGION', $googleMapsStatus['default_region']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm uppercase"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="GH">
                    </div>
                </div>

                {{-- ===================================================== --}}
                {{-- GHANA POST GPS                                        --}}
                {{-- ===================================================== --}}
                <h4 class="text-sm font-semibold mt-6 mb-3 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-map-pin mr-2" style="color: var(--warning);"></i>
                    Ghana Post GPS
                    <span class="text-xs font-normal text-gray-500 ml-2">(optional — resolves GH digital addresses)</span>
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="GHANA_POST_GPS_ENABLED" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Enable Ghana Post GPS
                        </label>
                        <select name="GHANA_POST_GPS_ENABLED" id="GHANA_POST_GPS_ENABLED"
                                class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="false" {{ !$googleMapsStatus['ghana_post']['enabled'] ? 'selected' : '' }}>
                                Disabled
                            </option>
                            <option value="true" {{ $googleMapsStatus['ghana_post']['enabled'] ? 'selected' : '' }}>
                                Enabled
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="GHANA_POST_GPS_URL" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Ghana Post GPS API URL
                        </label>
                        <input type="url" name="GHANA_POST_GPS_URL" id="GHANA_POST_GPS_URL"
                               value="{{ old('GHANA_POST_GPS_URL', $googleMapsStatus['ghana_post']['url']) }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="https://api.ghanapostgps.com/v1/address">
                    </div>

                    <div class="md:col-span-3">
                        <label for="GHANA_POST_GPS_API_KEY" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Ghana Post GPS API Key
                        </label>
                        <input type="text" name="GHANA_POST_GPS_API_KEY" id="GHANA_POST_GPS_API_KEY"
                               value="{{ old('GHANA_POST_GPS_API_KEY') }}"
                               placeholder="{{ $googleMapsStatus['ghana_post']['api_key_preview'] ?? 'not set' }}"
                               class="w-full p-2 border rounded-lg font-mono text-sm"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Leave blank to keep the current value.
                        </p>
                    </div>
                </div>

                {{-- ===================================================== --}}
                {{-- SECURITY NOTE + SUBMIT                                --}}
                {{-- ===================================================== --}}
                <div class="mt-6 p-3 rounded"
                     style="background: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                    <div class="flex items-start gap-2 text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-shield-alt mt-0.5" style="color: var(--info);"></i>
                        <div>
                            <strong style="color: var(--text-primary);">Security note:</strong>
                            Saving this form writes the values to <code>.env</code> on the server and then
                            clears the config cache. Only whitelisted keys are accepted; anything else is
                            ignored. The keys are never logged in plain text.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="submit"
                            class="btn-primary"
                            id="save-env-btn"
                            {{ !$envWritable ? 'disabled' : '' }}
                            @if(!$envWritable) title=".env is not writable on this server" @endif>
                        <i class="fas fa-save mr-2"></i> Save Environment Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* --------------------------------------------------------------- */
    /* Color pickers ↔ text inputs                                      */
    /* --------------------------------------------------------------- */
    const colorPairs = [
        { picker: 'primary_color',   text: 'primary_color_text'   },
        { picker: 'secondary_color', text: 'secondary_color_text' },
    ];

    colorPairs.forEach(({ picker, text }) => {
        const pickerEl = document.getElementById(picker);
        const textEl   = document.getElementById(text);
        if (!pickerEl || !textEl) return;

        // Picker → text
        pickerEl.addEventListener('input', () => {
            textEl.value = pickerEl.value;
        });

        // Text → picker (valid hex only)
        textEl.addEventListener('input', () => {
            const v = textEl.value.trim();
            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(v)) {
                pickerEl.value = v;
            }
        });
    });

    /* --------------------------------------------------------------- */
    /* Cross-field check: collection days ⊆ operational days            */
    /* --------------------------------------------------------------- */
    function getCheckedValues(name) {
        return Array.from(document.querySelectorAll(`input[name="${name}[]"]:checked`))
            .map(el => el.value);
    }

    function validateCollectionDaysWithinOperational() {
        const operational = getCheckedValues('operational_days');
        const collection  = getCheckedValues('default_collection_days');

        if (operational.length === 0) {
            return { ok: true }; // Operational days are optional
        }

        const offDays = collection.filter(d => !operational.includes(d));

        return offDays.length === 0
            ? { ok: true }
            : { ok: false, offDays };
    }

    /* --------------------------------------------------------------- */
    /* Settings form submit validation                                  */
    /* --------------------------------------------------------------- */
    const form = document.getElementById('sanitation-settings-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            // 1. Color sanity (paranoia layer — server validates anyway)
            const hexRe = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
            for (const id of ['primary_color_text', 'secondary_color_text']) {
                const el = document.getElementById(id);
                if (el && el.value && !hexRe.test(el.value.trim())) {
                    e.preventDefault();
                    alert(`Please enter a valid hex color code for ${id.replace('_text','').replace('_',' ')}.`);
                    el.focus();
                    return;
                }
            }

            // 2. Collection days ⊆ operational days
            const check = validateCollectionDaysWithinOperational();
            if (!check.ok) {
                e.preventDefault();
                alert(
                    'These collection days are not within operational days:\n\n' +
                    check.offDays.join(', ') +
                    '\n\nPlease uncheck them, or mark them as operational days first.'
                );
                return;
            }
        });
    }

    /* --------------------------------------------------------------- */
    /* Optional: live visual feedback while user toggles checkboxes     */
    /* --------------------------------------------------------------- */
    document.querySelectorAll('input[name="default_collection_days[]"]').forEach(cb => {
        cb.addEventListener('change', () => {
            const operational = getCheckedValues('operational_days');
            const label = cb.closest('label');
            if (!label) return;

            if (operational.length > 0 && !operational.includes(cb.value)) {
                label.style.opacity = cb.checked ? '0.6' : '1';
                label.title = cb.checked
                    ? 'Warning: not an operational day'
                    : '';
            } else {
                label.style.opacity = '1';
                label.title = '';
            }
        });
    });

    /* --------------------------------------------------------------- */
    /* ✅ NEW: Google Maps .env form — client-side UX helpers            */
    /* --------------------------------------------------------------- */
    const envForm = document.getElementById('google-maps-env-form');
    const envBtn  = document.getElementById('save-env-btn');
    const envWritable = {{ $envWritable ? 'true' : 'false' }};

    if (envForm && envBtn) {

        // Guard: if .env is not writable, refuse the submit gracefully
        envForm.addEventListener('submit', function (e) {
            if (!envWritable) {
                e.preventDefault();
                alert(
                    'The .env file is not writable on this server.\n\n' +
                    'Please ask your hosting provider to grant write permission, ' +
                    'or edit .env manually. See the warning above this form.'
                );
                return;
            }

            // Confirm — writing to .env is a real side-effect
            const confirmed = confirm(
                'Save these values to the server\'s .env file?\n\n' +
                'The config cache will be cleared automatically so the new values take effect.'
            );

            if (!confirmed) {
                e.preventDefault();
                return;
            }

            // Show in-flight state
            envBtn.disabled = true;
            envBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving…';
        });
    }

    /* --------------------------------------------------------------- */
    /* ✅ Small nicety: uppercase 2-letter country/region inputs         */
    /* --------------------------------------------------------------- */
    ['GOOGLE_MAPS_DEFAULT_COUNTRY', 'GOOGLE_MAPS_DEFAULT_REGION'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => {
            const start = el.selectionStart;
            el.value = el.value.toUpperCase().slice(0, 2);
            el.setSelectionRange(start, start);
        });
    });
});
</script>
@endpush