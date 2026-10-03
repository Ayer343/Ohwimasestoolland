{{-- resources/views/sanitation/settings/index.blade.php --}}

@php
    use App\Models\SanitationSetting;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // ✅ Safe array helper — handles null, string, and array inputs
    $safeArray = function ($value) {
        if (is_null($value)) return [];
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        return [];
    };

    // ✅ Canonical option lists — single source of truth
    $weekdays              = SanitationSetting::WEEKDAYS;
    $collectionFrequencies = SanitationSetting::COLLECTION_FREQUENCIES;
    $wasteTypes            = SanitationSetting::WASTE_TYPES;

    // ✅ Decode all JSON-ish fields up front
    $operationalDays   = array_values(array_intersect($weekdays, $safeArray($settings->operational_days)));
    $collectionDays    = array_values(array_intersect($weekdays, $safeArray($settings->default_collection_days)));
    $collectionFreqs   = array_values(array_intersect($collectionFrequencies, array_map('strtolower', $safeArray($settings->default_collection_frequencies))));
    $defaultWasteTypes = array_values(array_intersect($wasteTypes, $safeArray($settings->default_waste_types)));
    $serviceAreas      = $safeArray($settings->service_areas);
    $vehicleTypes      = $safeArray($settings->vehicle_types);
    $emergencyContacts = $safeArray($settings->emergency_contacts);

    // ✅ Branding
    $primaryColor   = $settings->primary_color   ?? '#10B981';
    $secondaryColor = $settings->secondary_color ?? '#059669';

    // ✅ Company initials
    $companyInitials = '';
    if ($settings->company_name) {
        foreach (explode(' ', $settings->company_name) as $word) {
            if (!empty($word)) {
                $companyInitials .= strtoupper(mb_substr($word, 0, 1));
            }
        }
        $companyInitials = mb_substr($companyInitials, 0, 2);
    }

    // -----------------------------------------------------------------
    // ✅ Operational status — driven by the model's single source of truth
    // -----------------------------------------------------------------
    $statusPayload = $settings->getOperationalStatusPayload();
    $statusText    = $statusPayload['status_text'];

    $statusBadgeClass = match (true) {
        !$statusPayload['is_active']            => 'settings-badge--danger',
        $statusPayload['status'] === 'open'     => 'settings-badge--success',
        $statusPayload['status'] === 'closed'   => 'settings-badge--warning',
        default                                 => 'settings-badge--neutral',
    };

    // ✅ Format operational hours
    $startRaw = $settings->operational_start_time;
    $endRaw   = $settings->operational_end_time;
    $startNorm = $startRaw ? $settings->normalizeOperationalTime($startRaw) : null;
    $endNorm   = $endRaw   ? $settings->normalizeOperationalTime($endRaw)   : null;

    // ✅ Currency formatter
    $ghs = fn ($amount) => 'GH₵ ' . number_format((float) $amount, 2);

    // ✅ Human label helper for frequencies
    $freqLabel = fn (string $f) => SanitationSetting::getFrequencyLabel($f);

    // ============================================================
    // ✅ Google Maps + Ghana Post GPS status
    // ------------------------------------------------------------
    // Priority:
    //   1. Controller-provided $googleMapsStatus (preferred)
    //   2. Fall back to config('services.google_maps.*')
    //
    // The fallback lets this blade render correctly even if the
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

    // -----------------------------------------------------------------
    // ✅ Aggregate maps readiness so the card can show a single
    //    headline status ("Ready" / "Partial" / "Not configured").
    // -----------------------------------------------------------------
    $mapsEnvWritable = (bool) ($googleMapsStatus['env_writable'] ?? false);

    $mapsPrimarySet   = !empty($googleMapsStatus['api_key_set']);
    $mapsBrowserSet   = !empty($googleMapsStatus['browser_key_set']);
    $mapsServerSet    = !empty($googleMapsStatus['server_key_set']);
    $mapsAnySet       = $mapsPrimarySet || $mapsBrowserSet || $mapsServerSet;
    $mapsBothSet      = $mapsBrowserSet && $mapsServerSet;

    $mapsHeadline = match (true) {
        $mapsBothSet => ['label' => 'Ready',           'class' => 'settings-badge--success', 'icon' => 'fa-check-circle'],
        $mapsAnySet  => ['label' => 'Partially configured', 'class' => 'settings-badge--warning', 'icon' => 'fa-exclamation-triangle'],
        default      => ['label' => 'Not configured',  'class' => 'settings-badge--neutral', 'icon' => 'fa-minus-circle'],
    };

    $ghanaPostEnabled = (bool) ($googleMapsStatus['ghana_post']['enabled'] ?? false);
    $ghanaPostKeySet  = (bool) ($googleMapsStatus['ghana_post']['api_key_set'] ?? false);
@endphp

@extends($layout)

@section('title', 'Sanitation Settings')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                    Sanitation Settings
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage your sanitation company settings and configuration
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sanitation.settings.edit') }}" class="btn-primary">
                    <i class="fas fa-edit mr-2"></i> Edit Settings
                </a>
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ session('error') }}
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- COMPANY OVERVIEW                                             -->
        <!-- ============================================================ -->
        <div class="card p-6 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-6">

                <!-- Logo -->
                <div class="flex-shrink-0">
                    @if($settings->logo_url)
                        <img src="{{ $settings->logo_url }}"
                             alt="{{ $settings->company_name ?? 'Company' }}"
                             class="w-24 h-24 rounded-lg object-cover border-2"
                             style="border-color: var(--border-color);">
                    @else
                        <div class="w-24 h-24 rounded-lg flex items-center justify-center text-3xl font-bold"
                             style="background: linear-gradient(135deg, {{ $primaryColor }}, {{ $secondaryColor }});
                                    color: white;">
                            {{ $companyInitials ?: 'SC' }}
                        </div>
                    @endif
                </div>

                <!-- Company Info -->
                <div class="flex-1">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $settings->company_name ?? 'Sanitation Company' }}
                    </h2>
                    @if($settings->company_short_name)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            {{ $settings->company_short_name }}
                        </p>
                    @endif
                    <div class="flex flex-wrap gap-4 mt-2">

                        {{-- Active/Inactive --}}
                        <span class="settings-badge @if($settings->is_active) settings-badge--success @else settings-badge--danger @endif">
                            <i class="fas fa-circle mr-1 text-[6px]"></i>
                            {{ $settings->is_active ? 'Active' : 'Inactive' }}
                        </span>

                        {{-- Operational status badge --}}
                        <span class="operational-status-badge settings-badge {{ $statusBadgeClass }}">
                            <i class="fas fa-clock mr-1"></i>
                            {{ $statusText }}
                        </span>

                        {{-- Workers per vehicle --}}
                        <span class="settings-badge settings-badge--info">
                            <i class="fas fa-users mr-1"></i>
                            {{ $settings->default_worker_count_per_vehicle ?? 2 }} Workers/Vehicle
                        </span>

                        {{-- Frequency summary pill --}}
                        @if(!empty($collectionFreqs))
                            <span class="settings-badge settings-badge--purple">
                                <i class="fas fa-redo mr-1"></i>
                                {{ count($collectionFreqs) }} Frequency Option{{ count($collectionFreqs) === 1 ? '' : 's' }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="flex-shrink-0 flex flex-col space-y-2">
                    <form method="POST"
                          action="{{ route('sanitation.settings.toggle-active') }}"
                          onsubmit="return confirm('Are you sure you want to change the status of these settings?');">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 w-full
                                    {{ $settings->is_active ? 'btn-warning' : 'btn-success' }}">
                            <i class="fas {{ $settings->is_active ? 'fa-pause' : 'fa-play' }} mr-2"></i>
                            {{ $settings->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                    <a href="{{ route('sanitation.settings.export') }}" class="btn-secondary btn-sm text-center">
                        <i class="fas fa-download mr-2"></i> Export Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SETTINGS GRID                                                -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Company Details -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                    Company Details
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Name</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->company_name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Email</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->company_email ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Phone</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->company_phone ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Address</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->company_address ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Registration Details -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-id-card mr-2" style="color: var(--info);"></i>
                    Registration Details
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Registration Number</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->registration_number ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Tax ID</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->tax_id ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">License Number</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->license_number ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Contact Person -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--success);"></i>
                    Contact Person
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Name</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->contact_person_name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Phone</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->contact_person_phone ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Email</dt>
                        <dd style="color: var(--text-primary);">{{ $settings->contact_person_email ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Operational Hours -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                    Operational Hours
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Hours</dt>
                        <dd style="color: var(--text-primary);">
                            @if($startNorm && $endNorm)
                                {{ \Carbon\Carbon::createFromFormat('H:i', $startNorm)->format('h:i A') }} -
                                {{ \Carbon\Carbon::createFromFormat('H:i', $endNorm)->format('h:i A') }}
                            @else
                                24/7
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Days</dt>
                        <dd style="color: var(--text-primary);">
                            @if(!empty($operationalDays))
                                {{ implode(', ', $operationalDays) }}
                            @else
                                All Days
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Status</dt>
                        <dd>
                            <span class="operational-status-badge settings-badge {{ $statusBadgeClass }}">
                                {{ $statusText }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Collection Schedule Defaults -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                    Collection Schedule
                </h3>
                <dl class="space-y-3 text-sm">

                    <div>
                        <dt class="font-medium mb-1" style="color: var(--text-secondary);">
                            Frequencies
                        </dt>
                        <dd>
                            @if(!empty($collectionFreqs))
                                <div class="flex flex-wrap gap-1">
                                    @foreach($collectionFreqs as $freq)
                                        <span class="settings-badge settings-badge--purple">
                                            {{ $freqLabel($freq) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-secondary);">Not configured</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium mb-1" style="color: var(--text-secondary);">
                            Typical Days
                        </dt>
                        <dd>
                            @if(!empty($collectionDays))
                                <div class="flex flex-wrap gap-1">
                                    @foreach($collectionDays as $day)
                                        <span class="settings-badge settings-badge--info">
                                            {{ $day }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-secondary);">Not configured</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium mb-1" style="color: var(--text-secondary);">
                            Waste Types
                        </dt>
                        <dd>
                            @if(!empty($defaultWasteTypes))
                                <div class="flex flex-wrap gap-1">
                                    @foreach($defaultWasteTypes as $type)
                                        <span class="settings-badge settings-badge--neutral">
                                            {{ ucfirst($type) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-secondary);">Not configured</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Pricing & Fees -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-money-bill mr-2" style="color: var(--danger);"></i>
                    Pricing & Fees
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Default Collection Fee</dt>
                        <dd style="color: var(--text-primary);">
                            {{ $settings->default_collection_fee ? $ghs($settings->default_collection_fee) : 'Not Set' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Emergency Collection Fee</dt>
                        <dd style="color: var(--text-primary);">
                            @if($settings->hasEmergencyConfigured())
                                @if($settings->getRawEmergencyFee() > 0)
                                    {{ $ghs($settings->getRawEmergencyFee()) }}
                                @else
                                    <span style="color: var(--text-secondary);">Configured per frequency</span>
                                @endif
                            @else
                                <span class="settings-badge settings-badge--neutral">Disabled</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Late Fee Percentage</dt>
                        <dd style="color: var(--text-primary);">
                            {{ $settings->late_fee_percentage ? number_format((float) $settings->late_fee_percentage, 2) . '%' : 'Not Set' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Service Areas -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-map-marked-alt mr-2" style="color: var(--info);"></i>
                    Service Areas
                </h3>
                @if(!empty($serviceAreas))
                    <div class="flex flex-wrap gap-2">
                        @foreach($serviceAreas as $area)
                            <span class="settings-badge settings-badge--neutral">
                                {{ $area }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm" style="color: var(--text-secondary);">No service areas configured</p>
                @endif
            </div>

            <!-- Vehicle Types -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-truck mr-2" style="color: var(--info);"></i>
                    Vehicle Types
                </h3>
                @if(!empty($vehicleTypes))
                    <div class="flex flex-wrap gap-2">
                        @foreach($vehicleTypes as $type)
                            <span class="settings-badge settings-badge--info">
                                {{ $type }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm" style="color: var(--text-secondary);">No vehicle types configured</p>
                @endif
            </div>

            <!-- Branding & Social Media -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-palette mr-2" style="color: var(--info);"></i>
                    Branding & Social Media
                </h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Primary Color</dt>
                        <dd>
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 rounded-full border"
                                     style="background-color: {{ $primaryColor }}; border-color: var(--border-color);"></div>
                                <span style="color: var(--text-primary);">{{ $primaryColor }}</span>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">Secondary Color</dt>
                        <dd>
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 rounded-full border"
                                     style="background-color: {{ $secondaryColor }}; border-color: var(--border-color);"></div>
                                <span style="color: var(--text-primary);">{{ $secondaryColor }}</span>
                            </div>
                        </dd>
                    </div>
                    @if($settings->website_url)
                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">Website</dt>
                            <dd style="color: var(--text-primary);">
                                <a href="{{ $settings->website_url }}" target="_blank" rel="noopener"
                                   class="text-blue-600 hover:underline break-all">
                                    {{ $settings->website_url }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($settings->facebook_url || $settings->twitter_url || $settings->instagram_url || $settings->linkedin_url)
                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">Social Media</dt>
                            <dd>
                                <div class="flex space-x-3 mt-1">
                                    @if($settings->facebook_url)
                                        <a href="{{ $settings->facebook_url }}" target="_blank" rel="noopener"
                                           class="text-blue-600 hover:text-blue-800" title="Facebook">
                                            <i class="fab fa-facebook fa-lg"></i>
                                        </a>
                                    @endif
                                    @if($settings->twitter_url)
                                        <a href="{{ $settings->twitter_url }}" target="_blank" rel="noopener"
                                           class="text-blue-400 hover:text-blue-600" title="Twitter">
                                            <i class="fab fa-twitter fa-lg"></i>
                                        </a>
                                    @endif
                                    @if($settings->instagram_url)
                                        <a href="{{ $settings->instagram_url }}" target="_blank" rel="noopener"
                                           class="text-pink-600 hover:text-pink-800" title="Instagram">
                                            <i class="fab fa-instagram fa-lg"></i>
                                        </a>
                                    @endif
                                    @if($settings->linkedin_url)
                                        <a href="{{ $settings->linkedin_url }}" target="_blank" rel="noopener"
                                           class="text-blue-700 hover:text-blue-900" title="LinkedIn">
                                            <i class="fab fa-linkedin fa-lg"></i>
                                        </a>
                                    @endif
                                </div>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- COMPANY DESCRIPTION                                          -->
        <!-- ============================================================ -->
        @if($settings->company_description)
        <div class="card p-6 mt-6">
            <h3 class="text-lg font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-align-left mr-2" style="color: var(--info);"></i>
                About Us
            </h3>
            <p style="color: var(--text-primary);">{{ $settings->company_description }}</p>
        </div>
        @endif

        <!-- ============================================================ -->
        <!-- EMERGENCY CONTACTS                                           -->
        <!-- ============================================================ -->
        @if(!empty($emergencyContacts))
        <div class="card p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-phone-alt mr-2" style="color: var(--danger);"></i>
                Emergency Contacts
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($emergencyContacts as $contact)
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <p class="font-medium" style="color: var(--text-primary);">{{ $contact['name'] ?? 'N/A' }}</p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-phone mr-2"></i>{{ $contact['phone'] ?? 'N/A' }}
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-tag mr-2"></i>{{ $contact['type'] ?? 'General' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- ============================================================ -->
        <!-- ✅ NEW: GOOGLE MAPS & LOCATION SERVICES                       -->
        <!-- ============================================================ -->
        <div class="card p-6 mt-6" id="google-maps-settings-card">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                        Google Maps & Location Services
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Credentials used by the coordinate picker on property pages and the server-side geocoder
                        for waste-collection routes.
                    </p>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    <span class="settings-badge {{ $mapsHeadline['class'] }}">
                        <i class="fas {{ $mapsHeadline['icon'] }} mr-1"></i>
                        {{ $mapsHeadline['label'] }}
                    </span>
                    <a href="{{ route('sanitation.settings.edit') }}#google-maps-settings-card"
                       class="btn-secondary btn-sm">
                        <i class="fas fa-cog mr-1"></i> Configure
                    </a>
                </div>
            </div>

            @if(!$mapsEnvWritable)
                <div class="p-3 mb-4 rounded border-l-4"
                     style="background: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <strong style="color: var(--text-primary);">The <code>.env</code> file is not writable.</strong>
                            Keys can't be saved from the browser. Ask your hosting provider to grant
                            write permission, or edit <code>.env</code> manually.
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- API Keys -->
                <div>
                    <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--info);"></i>
                        API Keys
                    </h4>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Master Key (fallback)
                            </dt>
                            <dd style="color: var(--text-primary);" class="font-mono break-all">
                                @if($mapsPrimarySet)
                                    {{ $googleMapsStatus['api_key_preview'] }}
                                @else
                                    <span class="settings-badge settings-badge--neutral">Not set</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Browser Key (map picker)
                            </dt>
                            <dd style="color: var(--text-primary);" class="font-mono break-all">
                                @if($mapsBrowserSet)
                                    {{ $googleMapsStatus['browser_key_preview'] }}
                                @else
                                    <span class="settings-badge settings-badge--neutral">Not set</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Server Key (geocoder)
                            </dt>
                            <dd style="color: var(--text-primary);" class="font-mono break-all">
                                @if($mapsServerSet)
                                    {{ $googleMapsStatus['server_key_preview'] }}
                                @else
                                    <span class="settings-badge settings-badge--neutral">Not set</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Default Map Behaviour -->
                <div>
                    <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map mr-2" style="color: var(--info);"></i>
                        Default Map Behaviour
                    </h4>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Default Centre
                            </dt>
                            <dd class="font-mono" style="color: var(--text-primary);">
                                {{ number_format($googleMapsStatus['default_center']['lat'], 4) }},
                                {{ number_format($googleMapsStatus['default_center']['lng'], 4) }}
                            </dd>
                        </div>

                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Default Zoom
                            </dt>
                            <dd class="font-mono" style="color: var(--text-primary);">
                                {{ $googleMapsStatus['default_zoom'] }}
                            </dd>
                        </div>

                        <div>
                            <dt class="font-medium" style="color: var(--text-secondary);">
                                Country / Region
                            </dt>
                            <dd class="font-mono uppercase" style="color: var(--text-primary);">
                                {{ $googleMapsStatus['default_country'] }} / {{ $googleMapsStatus['default_region'] }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Ghana Post GPS -->
            <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-map-pin mr-2" style="color: var(--warning);"></i>
                    Ghana Post GPS
                    @if($ghanaPostEnabled && $ghanaPostKeySet)
                        <span class="settings-badge settings-badge--success ml-2">
                            <i class="fas fa-check-circle mr-1"></i>Enabled
                        </span>
                    @elseif($ghanaPostEnabled && !$ghanaPostKeySet)
                        <span class="settings-badge settings-badge--warning ml-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Enabled but missing key
                        </span>
                    @else
                        <span class="settings-badge settings-badge--neutral ml-2">
                            Disabled
                        </span>
                    @endif
                </h4>

                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">
                            Service URL
                        </dt>
                        <dd class="font-mono break-all" style="color: var(--text-primary);">
                            {{ $googleMapsStatus['ghana_post']['url'] ?? 'N/A' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium" style="color: var(--text-secondary);">
                            API Key
                        </dt>
                        <dd class="font-mono break-all" style="color: var(--text-primary);">
                            @if($ghanaPostKeySet)
                                {{ $googleMapsStatus['ghana_post']['api_key_preview'] }}
                            @else
                                <span class="settings-badge settings-badge--neutral">Not set</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Env state footer -->
            <div class="mt-4 pt-4 border-t flex items-center justify-between text-xs"
                 style="border-color: var(--border-color); color: var(--text-secondary);">
                <span>
                    <i class="fas fa-{{ $mapsEnvWritable ? 'check-circle' : 'times-circle' }} mr-1"
                       style="color: {{ $mapsEnvWritable ? 'var(--success)' : 'var(--danger)' }};"></i>
                    .env {{ $mapsEnvWritable ? 'writable' : 'not writable' }}
                </span>
                <span>
                    <i class="fas fa-shield-alt mr-1" style="color: var(--info);"></i>
                    Values are never displayed in full
                </span>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SYSTEM INFORMATION                                           -->
        <!-- ============================================================ -->
        <div class="card p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                System Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">

                <div>
                    <span class="font-medium" style="color: var(--text-secondary);">Last Updated</span>
                    <p style="color: var(--text-primary);">
                        @if($settings->updated_at)
                            {{ $settings->updated_at->format('M j, Y g:i A') }}
                            @if($settings->updatedBy)
                                by {{ $settings->updatedBy->name ?? 'Unknown' }}
                            @endif
                        @else
                            N/A
                        @endif
                    </p>
                </div>

                <div>
                    <span class="font-medium" style="color: var(--text-secondary);">Status</span>
                    <p style="color: var(--text-primary);">
                        <span class="settings-badge @if($settings->is_active) settings-badge--success @else settings-badge--danger @endif">
                            {{ $settings->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ============================================================ */
    /* Settings badges — solid colors, guaranteed text contrast     */
    /* ============================================================ */
    .settings-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.25;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .settings-badge--success {
        background-color: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .settings-badge--danger {
        background-color: #fee2e2;
        color: #991b1b;
        border-color: #fca5a5;
    }

    .settings-badge--warning {
        background-color: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }

    .settings-badge--info {
        background-color: #dbeafe;
        color: #1e40af;
        border-color: #93c5fd;
    }

    .settings-badge--purple {
        background-color: #ede9fe;
        color: #5b21b6;
        border-color: #c4b5fd;
    }

    .settings-badge--neutral {
        background-color: #f1f5f9;
        color: #334155;
        border-color: #cbd5e1;
    }

    /* Dark mode overrides */
    [data-theme="dark"] .settings-badge--success {
        background-color: rgba(34, 197, 94, 0.15);
        color: #86efac;
        border-color: rgba(34, 197, 94, 0.35);
    }

    [data-theme="dark"] .settings-badge--danger {
        background-color: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.35);
    }

    [data-theme="dark"] .settings-badge--warning {
        background-color: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
        border-color: rgba(245, 158, 11, 0.35);
    }

    [data-theme="dark"] .settings-badge--info {
        background-color: rgba(59, 130, 246, 0.15);
        color: #93c5fd;
        border-color: rgba(59, 130, 246, 0.35);
    }

    [data-theme="dark"] .settings-badge--purple {
        background-color: rgba(139, 92, 246, 0.15);
        color: #c4b5fd;
        border-color: rgba(139, 92, 246, 0.35);
    }

    [data-theme="dark"] .settings-badge--neutral {
        background-color: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.35);
    }

    /* Legacy Tailwind utility overrides for dark mode */
    [data-theme="dark"] .bg-green-100  { background-color: rgba(34, 197, 94, 0.15)  !important; }
    [data-theme="dark"] .bg-red-100    { background-color: rgba(239, 68, 68, 0.15)  !important; }
    [data-theme="dark"] .bg-yellow-100 { background-color: rgba(245, 158, 11, 0.15) !important; }
    [data-theme="dark"] .bg-blue-100   { background-color: rgba(59, 130, 246, 0.15) !important; }
    [data-theme="dark"] .bg-purple-100 { background-color: rgba(139, 92, 246, 0.15) !important; }
    [data-theme="dark"] .bg-gray-100   { background-color: rgba(148, 163, 184, 0.15) !important; }

    [data-theme="dark"] .text-green-700  { color: #86efac !important; }
    [data-theme="dark"] .text-red-700    { color: #fca5a5 !important; }
    [data-theme="dark"] .text-yellow-700 { color: #fcd34d !important; }
    [data-theme="dark"] .text-blue-700   { color: #93c5fd !important; }
    [data-theme="dark"] .text-purple-700 { color: #c4b5fd !important; }
    [data-theme="dark"] .text-gray-700   { color: #cbd5e1 !important; }

    /* ✅ Coordinate picker — the mask preview stays monospace-safe */
    #google-maps-settings-card code {
        background-color: var(--bg-secondary);
        padding: 0.1rem 0.35rem;
        border-radius: 0.25rem;
        font-size: 0.85em;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* --------------------------------------------------------------- */
    /* Live operational status refresh every 60s                        */
    /* --------------------------------------------------------------- */
    const statusUrl = @json(route('sanitation.settings.api'));

    function pickBadgeClass(status, isActive) {
        if (!isActive || status === 'inactive') return 'settings-badge--danger';
        if (status === 'open')                  return 'settings-badge--success';
        if (status === 'closed')                return 'settings-badge--warning';
        return 'settings-badge--neutral';
    }

    function renderBadge(el, statusData) {
        const status   = statusData.status      ?? 'unknown';
        const text     = statusData.status_text ?? 'Unknown';
        const isActive = statusData.is_active   ?? true;

        el.className = 'operational-status-badge settings-badge ' + pickBadgeClass(status, isActive);

        const hasClockIcon = el.querySelector('.fa-clock') !== null;
        el.innerHTML = (hasClockIcon ? '<i class="fas fa-clock mr-1"></i>' : '') + text;
    }

    function applyStatus(statusData) {
        if (!statusData
            || typeof statusData !== 'object'
            || typeof statusData.status !== 'string'
            || typeof statusData.status_text !== 'string') {
            console.debug('Ignoring malformed operational_status payload:', statusData);
            return;
        }

        document.querySelectorAll('.operational-status-badge').forEach(el => {
            renderBadge(el, statusData);
        });
    }

    function refreshStatus() {
        fetch(statusUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (data && data.success && data.operational_status) {
                    applyStatus(data.operational_status);
                }
            })
            .catch(err => console.debug('Status refresh skipped:', err));
    }

    let intervalId = null;

    function startPolling() {
        if (intervalId) return;
        intervalId = setInterval(refreshStatus, 60000);
    }

    function stopPolling() {
        if (!intervalId) return;
        clearInterval(intervalId);
        intervalId = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            refreshStatus();
            startPolling();
        }
    });

    startPolling();
});
</script>
@endpush