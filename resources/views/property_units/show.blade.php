{{-- property_units/show.blade.php --}}
@php
    use App\Models\PropertyUnit;

    $user = auth()->user();

    /* ============================================================
       ROLE / DASHBOARD RESOLUTION
       ============================================================ */
    $selectedRole = session('selected_role');

    $hasLandlordRole  = $user->isLandlord();
    $isLandlordLegacy = $user->type === \App\Models\User::TYPE_LANDLORD;
    $isLandlordByRole = $user->hasRole('landlord');
    $isLandlord       = $hasLandlordRole || $isLandlordLegacy || $isLandlordByRole;

    $isAdmin      = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper  = $user->isDeveloper();
    $isTenant     = $user->isTenant();

    $isOnAdminDashboard     = in_array($selectedRole, ['admin', 'super-admin'])
                              || (($isAdmin || $isSuperAdmin) && !$selectedRole);
    $isOnLandlordDashboard  = $selectedRole === 'landlord'
                              || ($isLandlord && !$selectedRole && !$isOnAdminDashboard);
    $isOnTenantDashboard    = $selectedRole === 'tenant' || ($isTenant && !$selectedRole);
    $isOnDeveloperDashboard = $selectedRole === 'developer' || ($isDeveloper && !$selectedRole);

    if ($isOnAdminDashboard) {
        $layout      = 'layouts.app';
        $routePrefix = 'admin.property-units';
        $isAdminView = true;
        $pageTitle   = 'Property Unit - Admin View';
    } elseif ($isOnLandlordDashboard) {
        $layout      = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
        $isAdminView = false;
        $pageTitle   = 'Property Unit Details';
    } elseif ($isOnTenantDashboard) {
        $layout      = 'layouts.tenant';
        $routePrefix = 'tenant.property-units';
        $isAdminView = false;
        $pageTitle   = 'My Unit Details';
    } elseif ($isOnDeveloperDashboard) {
        $layout      = 'layouts.app';
        $routePrefix = 'developer.property-units';
        $isAdminView = false;
        $pageTitle   = 'Property Unit - Developer';
    } else {
        $layout      = 'layouts.app';
        $routePrefix = 'property-units';
        $isAdminView = false;
        $pageTitle   = 'Property Unit';
    }

    /* ============================================================
       FAMILY-LINK FLAGS (with safe fallbacks)
       ============================================================ */
    $isOwner              = $isOwner              ?? ($isLandlord && $unit->property && (int) $unit->property->landlord_id === (int) $user->id);
    $isLinkedFamilyMember = $isLinkedFamilyMember ?? false;
    $hasManageUnitsAccess = $hasManageUnitsAccess ?? false;

    /* ============================================================
       PERMISSION DERIVATION
       ============================================================ */
    $canManageUnits = $isOwner || ($isLinkedFamilyMember && $hasManageUnitsAccess);
    $canDeleteUnit  = $isOwner;

    /* ============================================================
       DISPLAY STATUS — tenant approved ⇒ occupied
       ============================================================ */
    $isTenantApproved = $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED;
    $displayStatus = ($isTenantApproved && $unit->status === PropertyUnit::STATUS_OCCUPIED)
        ? PropertyUnit::STATUS_OCCUPIED
        : $unit->status;

    $statusBadge = $unit->getStatusBadgeAttribute($displayStatus);

    $isOccupied = $displayStatus === PropertyUnit::STATUS_OCCUPIED;

    /* ============================================================
       ACTION VISIBILITY
       ============================================================ */
    $canEdit   = $canManageUnits && !$isOccupied && !$isOnAdminDashboard;
    $canDelete = $canDeleteUnit  && !$isOccupied && !$isOnAdminDashboard;

    $showFinancialAndTenantInfo =
        ($isOwner && !$isOnAdminDashboard)
        || ($isLinkedFamilyMember && $hasManageUnitsAccess && !$isOnAdminDashboard);

    $showQuickStats =
        ($isOwner && !$isOnAdminDashboard)
        || ($isLinkedFamilyMember && $hasManageUnitsAccess && !$isOnAdminDashboard);

    $showManagementActions =
        $canManageUnits
        || $isOnAdminDashboard
        || $isOnDeveloperDashboard;

    // Is the current user the assigned tenant?
    $isAssignedTenant = $isTenant && (int) $unit->tenant_id === (int) $user->id;

    /* ============================================================
       PHOTO GALLERY DEFAULTS
       ------------------------------------------------------------
       The controller already passes $unitPhotos, $canManagePhotos,
       $photoUploadUrl, $photoDeleteUrlBase, $photoCoverUrlBase,
       $photoMaxCount, $photoMaxKb, $photoMimes. These fallbacks
       keep the view resilient if a route renders without them.
       ============================================================ */
    $canManagePhotos = $canManagePhotos ?? $canManageUnits;
    $unitPhotos      = $unitPhotos      ?? collect();
    $photoMaxCount   = $photoMaxCount   ?? 15;
    $photoMaxKb      = $photoMaxKb      ?? 10240;
    $photoMimes      = $photoMimes      ?? 'jpg,jpeg,png,webp';

    $photoUploadUrl     = $photoUploadUrl     ?? null;
    $photoDeleteUrlBase = $photoDeleteUrlBase ?? null;
    $photoCoverUrlBase  = $photoCoverUrlBase  ?? null;

    $coverPhoto = $unitPhotos->firstWhere('is_cover', true) ?? $unitPhotos->first();
    $hasPhotos  = $unitPhotos->isNotEmpty();

    /* ============================================================
       INLINE AMENITY LABELS
       ============================================================ */
    $amenityOptions = [
        'air_conditioning' => 'Air Conditioning',
        'balcony'          => 'Balcony',
        'parking'          => 'Parking',
        'swimming_pool'    => 'Swimming Pool',
        'gym'              => 'Gym',
        'laundry'          => 'Laundry',
        'security'         => 'Security',
        'elevator'         => 'Elevator',
        'wifi'             => 'WiFi',
        'furnished'        => 'Furnished',
        'garden'           => 'Garden',
        'playground'       => 'Playground',
        'pet_friendly'     => 'Pet Friendly',
        'dishwasher'       => 'Dishwasher',
        'microwave'        => 'Microwave',
        'oven'             => 'Oven',
        'refrigerator'     => 'Refrigerator',
        'washer'           => 'Washer',
        'dryer'            => 'Dryer',
        'heating'          => 'Heating',
        'fireplace'        => 'Fireplace',
        'cable_tv'         => 'Cable TV',
        'satellite_tv'     => 'Satellite TV',
    ];

    /* ============================================================
       DASHBOARD ROUTE
       ============================================================ */
    $dashboardRoute = '#';
    if ($isOnAdminDashboard) {
        $dashboardRoute = route('admin.dashboard');
    } elseif ($isOnLandlordDashboard) {
        $dashboardRoute = route('landlord.dashboard');
    } elseif ($isOnTenantDashboard) {
        $dashboardRoute = route('tenant.dashboard');
    } elseif ($isOnDeveloperDashboard) {
        $dashboardRoute = route('developer.dashboard');
    }
@endphp

@extends($layout)

@section('title', $unit->full_unit_identifier . ' - ' . $pageTitle)

@section('content')
<div id="property-unit-show-page" class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-fadeInUp">

    {{-- ============================================================
         MAIN CONTENT (3 cols)
         ============================================================ --}}
    <div class="lg:col-span-3 space-y-6">

        {{-- ==================== HEADER ==================== --}}
        <div class="card">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
                <div>
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center gradient-bg">
                            <i class="fas fa-building text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold" style="color: var(--text-primary);">
                                {{ $unit->full_unit_identifier }}
                            </h2>
                            <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>
                                Created {{ $unit->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                    @if($isOnAdminDashboard)
                        <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                              style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <i class="fas fa-eye mr-1"></i> Admin View
                        </span>
                    @elseif($isOnLandlordDashboard)
                        <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                              style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <i class="fas fa-eye mr-1"></i> Landlord View
                        </span>
                        @if($isLinkedFamilyMember && !$isOwner)
                            <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                  style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-user-friends mr-1"></i>
                                {{ $hasManageUnitsAccess ? 'Family-Linked · Manage' : 'Family-Linked · Read-only' }}
                            </span>
                        @endif
                    @endif

                    @if($isOnAdminDashboard && $isLandlord)
                        <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                              style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exchange-alt mr-1"></i> Multi-Role
                        </span>
                    @endif

                    <a href="{{ route($routePrefix . '.index') }}"
                       class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>

                    @if($canEdit)
                        <a href="{{ route('property-units.edit', $unit->id) }}"
                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                           style="background-color: var(--primary); color: white;">
                            <i class="fas fa-edit mr-2"></i> Edit Unit
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ==================== FAMILY-MEMBER BANNER ==================== --}}
        @if($isLinkedFamilyMember && !$isOwner && !$isOnAdminDashboard)
            <div class="card border-l-4"
                 style="border-left-color: var(--info); background-color: rgba(var(--info-rgb), 0.05);">
                <div class="p-4 flex items-start">
                    <i class="fas fa-user-friends text-xl mr-3 mt-0.5"
                       style="color: var(--info);"></i>
                    <div class="flex-1">
                        <p class="font-medium" style="color: var(--text-primary);">
                            You're viewing this unit as a linked family member.
                        </p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($hasManageUnitsAccess)
                                Your link grants <strong>manage units</strong> access on this property.
                                You can edit and manage this unit; every action is recorded in the audit log.
                            @else
                                You have read-only access to this unit. Contact the property owner if you
                                need to make changes.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== FLASH ==================== --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        {{-- ============================================================
             PHOTO GALLERY — professional presentation
             ============================================================ --}}
        <div class="card overflow-hidden" id="unit-photo-gallery">
            <div class="p-6 pb-4 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-images mr-2"></i> Photo Gallery
                    <span class="ml-2 text-xs font-medium px-2 py-0.5 rounded-full"
                          style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        {{ $unitPhotos->count() }} / {{ $photoMaxCount }}
                    </span>
                </h3>

                @if($canManagePhotos && $photoUploadUrl)
                    <button type="button"
                            id="btn-open-photo-picker"
                            class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-md hover:shadow-lg transition-all duration-200"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-cloud-upload-alt mr-2"></i> Upload Photos
                    </button>
                    <input type="file"
                           id="photo-picker"
                           accept="{{ str_replace(',', ',.', $photoMimes) }}"
                           multiple
                           class="hidden">
                @endif
            </div>

            @if($hasPhotos)
                {{-- HERO / COVER PHOTO --}}
                <div class="relative group">
                    <div class="aspect-video w-full overflow-hidden bg-gray-100"
                         style="background-color: rgba(0,0,0,0.04);">
                        <img src="{{ $coverPhoto['url'] }}"
                             alt="{{ $coverPhoto['caption'] ?? $unit->full_unit_identifier }}"
                             class="w-full h-full object-cover cursor-zoom-in transition-transform duration-300 group-hover:scale-[1.02]"
                             data-photo-index="0"
                             onclick="window.__PU_OPEN_LIGHTBOX({{ $unitPhotos->search($coverPhoto) }})">
                    </div>

                    <div class="absolute top-3 left-3 flex gap-2">
                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold inline-flex items-center shadow"
                              style="background: rgba(0,0,0,0.55); color: white; backdrop-filter: blur(4px);">
                            <i class="fas fa-star mr-1" style="color: #FFD166;"></i> Cover Photo
                        </span>
                        @if($canManagePhotos)
                            <span class="px-2.5 py-1 rounded-md text-xs font-medium inline-flex items-center shadow"
                                  style="background: rgba(0,0,0,0.55); color: white; backdrop-filter: blur(4px);">
                                <i class="fas fa-camera mr-1"></i> {{ $unitPhotos->count() }} {{ \Illuminate\Support\Str::plural('photo', $unitPhotos->count()) }}
                            </span>
                        @endif
                    </div>

                    <div class="absolute bottom-3 right-3">
                        <button type="button"
                                onclick="window.__PU_OPEN_LIGHTBOX({{ $unitPhotos->search($coverPhoto) }})"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center shadow-lg hover:shadow-xl transition-all"
                                style="background: rgba(255,255,255,0.92); color: var(--text-primary);">
                            <i class="fas fa-expand mr-1.5"></i> View Full Size
                        </button>
                    </div>
                </div>

                {{-- THUMBNAIL STRIP --}}
                @if($unitPhotos->count() > 1)
                    <div class="p-4 border-t" style="border-color: var(--border-color);">
                        <div class="flex gap-3 overflow-x-auto pb-2" id="photo-thumb-strip">
                            @foreach($unitPhotos as $index => $photo)
                                <div class="relative flex-shrink-0 group/thumb">
                                    <button type="button"
                                            class="block w-24 h-24 rounded-lg overflow-hidden border-2 transition-all duration-200 hover:opacity-90"
                                            data-photo-thumb
                                            data-photo-index="{{ $index }}"
                                            style="border-color: {{ $photo['is_cover'] ? 'var(--primary)' : 'transparent' }};"
                                            onclick="window.__PU_OPEN_LIGHTBOX({{ $index }})">
                                        <img src="{{ $photo['thumb_url'] }}"
                                             alt="{{ $photo['caption'] ?? 'Photo ' . ($index + 1) }}"
                                             class="w-full h-full object-cover">
                                    </button>

                                    @if($photo['is_cover'])
                                        <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center text-white text-[10px] shadow"
                                              style="background: var(--primary);" title="Cover photo">
                                            <i class="fas fa-star"></i>
                                        </span>
                                    @endif

                                    @if($canManagePhotos)
                                        <div class="absolute inset-0 rounded-lg opacity-0 group-hover/thumb:opacity-100 transition-opacity flex items-end justify-center pb-1 gap-1"
                                             style="background: linear-gradient(to top, rgba(0,0,0,0.55), transparent 60%);">
                                            @if(!$photo['is_cover'] && $photoCoverUrlBase)
                                                <button type="button"
                                                        class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs shadow"
                                                        style="background: rgba(0,0,0,0.55);"
                                                        title="Set as cover"
                                                        onclick="event.stopPropagation(); window.__PU_SET_COVER({{ $photo['id'] }})">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            @endif
                                            @if($photoDeleteUrlBase)
                                                <button type="button"
                                                        class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs shadow"
                                                        style="background: rgba(var(--danger-rgb), 0.85);"
                                                        title="Delete photo"
                                                        onclick="event.stopPropagation(); window.__PU_DELETE_PHOTO({{ $photo['id'] }})">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                {{-- EMPTY STATE --}}
                <div class="px-6 pb-8">
                    <div class="rounded-xl border-2 border-dashed flex flex-col items-center justify-center py-14"
                         style="border-color: var(--border-color); background-color: rgba(0,0,0,0.015);">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                             style="background: rgba(var(--primary-rgb), 0.08); color: var(--primary);">
                            <i class="fas fa-camera-retro text-2xl"></i>
                        </div>
                        <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                            No photos yet
                        </h4>
                        <p class="text-sm text-center max-w-md mb-4" style="color: var(--text-secondary);">
                            @if($canManagePhotos)
                                Add professional photos to help this unit stand out. You can upload up to
                                <strong>{{ $photoMaxCount }}</strong> images (max {{ round($photoMaxKb / 1024) }}MB each).
                            @else
                                The owner hasn't uploaded photos for this unit yet.
                            @endif
                        </p>
                        @if($canManagePhotos && $photoUploadUrl)
                            <button type="button"
                                    onclick="document.getElementById('photo-picker')?.click()"
                                    class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-md hover:shadow-lg transition-all"
                                    style="background-color: var(--primary); color: white;">
                                <i class="fas fa-cloud-upload-alt mr-2"></i> Upload First Photo
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Upload progress bar --}}
            @if($canManagePhotos && $photoUploadUrl)
                <div id="photo-upload-progress" class="hidden px-6 pb-4">
                    <div class="h-2 rounded-full overflow-hidden" style="background: rgba(0,0,0,0.06);">
                        <div id="photo-upload-bar" class="h-full transition-all duration-200"
                             style="width: 0%; background: var(--primary);"></div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin mr-1"></i>
                        <span id="photo-upload-label">Uploading…</span>
                    </p>
                </div>
            @endif
        </div>

        {{-- ==================== INFO CARDS ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- BASIC INFORMATION — everyone --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2"></i> Basic Information
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property
                            </label>
                            <a href="{{ route('properties.show', $unit->property_id) }}"
                               class="inline-flex items-center font-medium hover:underline"
                               style="color: var(--primary);">
                                <i class="fas fa-building mr-2"></i>
                                {{ $unit->property->property_name ?? 'N/A' }}
                            </a>
                            @if($isOnAdminDashboard)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-user mr-1"></i> {{ $unit->property->landlord->name ?? 'Unknown' }}
                                </span>
                            @elseif($isLinkedFamilyMember && !$isOwner)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-user-friends mr-1"></i> Linked Property
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Unit Type
                                </label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-tag mr-1"></i>
                                    {{ PropertyUnit::getTypeOptions()[$unit->unit_type] ?? $unit->unit_type }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Status
                                </label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                      style="background-color: {{ $statusBadge['color'] ?? 'rgba(var(--secondary-rgb), 0.1)' }}; color: white; border: 1px solid rgba(255,255,255,0.2);">
                                    <i class="{{ $statusBadge['icon'] ?? 'fas fa-question' }} mr-1"></i>
                                    {{ $statusBadge['text'] ?? ucfirst($displayStatus) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Available
                                </label>
                                @if($unit->is_available && !$isOccupied)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Yes
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-danger">
                                        <i class="fas fa-times-circle mr-1"></i> No
                                    </span>
                                @endif
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Furnished
                                </label>
                                @if($unit->is_furnished)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-info">
                                        <i class="fas fa-couch mr-1"></i> Yes
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-secondary">
                                        <i class="fas fa-couch mr-1"></i> No
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FINANCIAL / DETAILS — owner OR linked-with-manage_units --}}
            @if($showFinancialAndTenantInfo)
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-money-bill-wave mr-2"></i> Financial Information
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Monthly Rent
                                </label>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                    {{ $unit->rent_formatted ?? '₵' . number_format($unit->monthly_rent, 2) }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Security Deposit
                                </label>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $unit->security_deposit_formatted ?? ($unit->security_deposit ? '₵' . number_format($unit->security_deposit, 2) : 'Not set') }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Floor Area
                                </label>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $unit->floor_area_formatted ?? ($unit->floor_area ? number_format($unit->floor_area) . ' sq ft' : 'Not specified') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2"></i> Unit Details
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Floor Area
                                </label>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $unit->floor_area_formatted ?? ($unit->floor_area ? number_format($unit->floor_area) . ' sq ft' : 'Not specified') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 rounded-lg text-center"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--primary);">{{ $unit->bedrooms }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bed mr-1"></i> Bedrooms
                                    </p>
                                </div>

                                <div class="p-3 rounded-lg text-center"
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--success);">{{ $unit->bathrooms }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bath mr-1"></i> Bathrooms
                                    </p>
                                </div>
                            </div>

                            @if($isOnAdminDashboard)
                                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                    <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Financial details are only visible to the property owner
                                    </p>
                                </div>
                            @elseif($isLinkedFamilyMember && !$hasManageUnitsAccess)
                                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                    <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-lock mr-1"></i>
                                        Financial details require <strong>manage units</strong> permission
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ==================== DESCRIPTION ==================== --}}
        @if($unit->description)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-align-left mr-2"></i> Description
                    </h3>
                    <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ $unit->description }}</p>
                </div>
            </div>
        @endif

        {{-- ==================== TENANT INFO ==================== --}}
        @if($showFinancialAndTenantInfo)
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2"></i> Tenant Information
                        </h3>

                        @if($unit->tenant_status)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                  style="background-color: {{ $unit->tenant_status_badge['color'] ?? 'rgba(var(--secondary-rgb), 0.1)' }}; color: white; border: 1px solid rgba(255,255,255,0.2);">
                                <i class="{{ $unit->tenant_status_badge['icon'] ?? 'fas fa-question' }} mr-1"></i>
                                {{ $unit->tenant_status_badge['text'] ?? ucfirst(str_replace('_', ' ', $unit->tenant_status)) }}
                            </span>
                        @endif
                    </div>

                    @if($unit->tenant)
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-1">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0">
                                        @if($unit->tenant->profile_photo)
                                            <div class="w-16 h-16 rounded-full overflow-hidden border-2" style="border-color: var(--primary);">
                                                <img src="{{ Storage::url($unit->tenant->profile_photo) }}"
                                                     alt="{{ $unit->tenant->name }}"
                                                     class="w-full h-full object-cover">
                                            </div>
                                        @else
                                            <div class="w-16 h-16 rounded-full flex items-center justify-center avatar"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                <span class="text-white text-lg font-bold">
                                                    {{ substr($unit->tenant->name, 0, 1) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-grow">
                                        <h4 class="font-bold text-lg mb-1" style="color: var(--text-primary);">
                                            {{ $unit->tenant->name }}
                                        </h4>

                                        <div class="space-y-1">
                                            <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-2 flex-shrink-0"></i>
                                                <span class="truncate">{{ $unit->tenant->email }}</span>
                                            </p>

                                            @if($unit->tenant->phone)
                                                <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-2 flex-shrink-0"></i>
                                                    {{ $unit->tenant->phone }}
                                                </p>
                                            @endif

                                            @if($unit->tenant_move_in_date)
                                                <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                                                    <i class="fas fa-calendar-alt mr-2 flex-shrink-0"></i>
                                                    Moved in: {{ $unit->tenant_move_in_date->format('M d, Y') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($unit->tenant_approved_at)
                                    <div class="mt-4 p-4 rounded-lg"
                                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                        <div class="flex items-start space-x-3">
                                            <i class="fas fa-user-check text-lg mt-1" style="color: var(--info);"></i>
                                            <div>
                                                <h6 class="font-semibold mb-1" style="color: var(--text-primary);">
                                                    Tenant Approval Information
                                                </h6>
                                                <p class="text-sm" style="color: var(--text-secondary);">
                                                    Approved by: <strong>{{ $unit->approvedBy->name ?? 'Admin' }}</strong><br>
                                                    Approved on: {{ $unit->tenant_approved_at->format('F d, Y \a\t h:i A') }}
                                                    @if($unit->tenant_approval_notes)
                                                        <br>Notes: {{ $unit->tenant_approval_notes }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col gap-2 min-w-max">
                                @if($unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED && $canManageUnits)
                                    <a href="{{ route('property-units.mark-vacated-form', $unit->id) }}"
                                       class="px-4 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                                       style="background-color: var(--warning); color: white;">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center"
                                 style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                <i class="fas fa-user-slash text-2xl"></i>
                            </div>
                            <h4 class="font-semibold mb-2" style="color: var(--text-primary);">No Tenant Assigned</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                This unit currently has no tenant assigned.
                            </p>

                            @if($unit->is_available && !$isOccupied && $canManageUnits)
                                <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}"
                                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                                   style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-user-plus mr-2"></i> Assign Tenant
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @else
            @if($isOnAdminDashboard && !$isOwner)
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-user mr-2"></i> Tenant Information
                            </h3>
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-eye mr-1"></i> Admin View
                            </span>
                        </div>
                        <div class="text-center py-6">
                            <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center"
                                 style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-user-shield text-2xl" style="color: var(--info);"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Tenant information is only visible to the property owner
                            </p>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                This is an admin view - you cannot see tenant details
                            </p>
                        </div>
                    </div>
                </div>
            @elseif($isLinkedFamilyMember && !$hasManageUnitsAccess)
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2"></i> Tenant Information
                        </h3>
                        <div class="text-center py-6">
                            <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center"
                                 style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-lock text-2xl" style="color: var(--info);"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Tenant information requires <strong>manage units</strong> permission on this property
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- ==================== SPECS + AMENITIES ==================== --}}
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Unit Details & Amenities
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Specifications --}}
                    <div>
                        <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-ruler-combined mr-2"></i> Specifications
                        </h4>

                        <div class="space-y-3">
                            @if($unit->floor_area)
                                <div class="flex items-center justify-between p-3 rounded-lg"
                                     style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                                    <span class="text-sm font-medium" style="color: var(--text-secondary);">
                                        <i class="fas fa-ruler-combined mr-2"></i> Floor Area
                                    </span>
                                    <span class="font-semibold" style="color: var(--text-primary);">
                                        {{ $unit->floor_area }} sq ft
                                    </span>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-lg text-center"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--primary);">{{ $unit->bedrooms }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bed mr-1"></i> Bedrooms
                                    </p>
                                </div>

                                <div class="p-3 rounded-lg text-center"
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                    <p class="text-xl font-bold mb-1" style="color: var(--success);">{{ $unit->bathrooms }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-bath mr-1"></i> Bathrooms
                                    </p>
                                </div>
                            </div>

                            @if($unit->living_rooms > 0 || $unit->kitchens > 0)
                                <div class="grid grid-cols-2 gap-3 mt-3">
                                    @if($unit->living_rooms > 0)
                                        <div class="p-3 rounded-lg text-center"
                                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                            <p class="text-xl font-bold mb-1" style="color: var(--warning);">{{ $unit->living_rooms }}</p>
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-couch mr-1"></i> Living Rooms
                                            </p>
                                        </div>
                                    @endif

                                    @if($unit->kitchens > 0)
                                        <div class="p-3 rounded-lg text-center"
                                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                            <p class="text-xl font-bold mb-1" style="color: var(--info);">{{ $unit->kitchens }}</p>
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-utensils mr-1"></i> Kitchens
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Amenities --}}
                    <div>
                        <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-concierge-bell mr-2"></i> Amenities
                        </h4>

                        @if(!empty($unit->amenities))
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($unit->amenities as $amenity)
                                    @if(isset($amenityOptions[$amenity]))
                                        <div class="p-3 rounded-lg flex items-center"
                                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                            <i class="fas fa-check text-sm mr-2" style="color: var(--success);"></i>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ $amenityOptions[$amenity] }}
                                            </span>
                                        </div>
                                    @else
                                        <div class="p-3 rounded-lg flex items-center"
                                             style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                            <i class="fas fa-check text-sm mr-2" style="color: var(--secondary);"></i>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ ucfirst(str_replace('_', ' ', $amenity)) }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center"
                                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                    <i class="fas fa-slash text-xl"></i>
                                </div>
                                <p class="text-sm" style="color: var(--text-secondary);">No amenities specified</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         SIDEBAR (1 col)
         ============================================================ --}}
    <div class="space-y-6">

        {{-- QUICK STATISTICS — owner OR linked-with-manage_units --}}
        @if($showQuickStats)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2"></i> Quick Statistics
                    </h3>

                    <div class="space-y-4">
                        <div class="p-3 rounded-lg"
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                            <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Rental Agreements
                            </p>
                            <p class="text-xl font-bold" style="color: var(--primary);">
                                {{ $stats['total_rental_agreements'] ?? 0 }}
                            </p>
                        </div>

                        <div class="p-3 rounded-lg"
                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                            <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Active Maintenance
                            </p>
                            <p class="text-xl font-bold" style="color: var(--warning);">
                                {{ $stats['active_maintenance_requests'] ?? 0 }}
                            </p>
                        </div>

                        <div class="p-3 rounded-lg"
                             style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                            <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Outstanding Balance
                            </p>
                            <p class="text-xl font-bold" style="color: var(--danger);">
                                ₵{{ number_format($stats['outstanding_invoices'] ?? 0, 2) }}
                            </p>
                        </div>

                        <div class="p-3 rounded-lg"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                            <p class="text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Total Rent Collected
                            </p>
                            <p class="text-xl font-bold" style="color: var(--success);">
                                ₵{{ number_format($stats['total_rent_collected'] ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($isOnAdminDashboard || ($isLinkedFamilyMember && !$hasManageUnitsAccess))
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2"></i> Quick Statistics
                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-eye mr-1"></i> Restricted
                        </span>
                    </h3>
                    <div class="text-center py-6">
                        <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-user-shield text-2xl" style="color: var(--info);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Statistics are only visible to the property owner
                        </p>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            @if($isOnAdminDashboard)
                                This is an admin view - financial stats are hidden
                            @else
                                Linked access without <strong>manage units</strong> permission
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- QUICK ACTIONS — manage-capable users --}}
        @if($canManageUnits && !$isOnAdminDashboard)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2"></i> Quick Actions
                    </h3>

                    <div class="space-y-2">
                        @if($unit->currentLease)
                            <a href="{{ route('property-units.lease-management', $unit->id) }}"
                               class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-file-contract mr-2"></i> View Lease Agreement
                            </a>
                        @elseif($unit->tenant)
                            <a href="{{ route($routePrefix . '.create-lease.form', $unit->id) }}"
                               class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                               style="background-color: var(--success); color: white;">
                                <i class="fas fa-file-signature mr-2"></i> Create Lease Agreement
                            </a>
                        @endif

                        <a href="{{ route('property-units.maintenance-requests', $unit->id) }}"
                           class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-tools mr-2"></i> Maintenance Requests
                        </a>

                        <a href="{{ route('property-units.financials', $unit->id) }}"
                           class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-chart-line mr-2"></i> Financial Information
                        </a>

                        @if($canManagePhotos && $photoUploadUrl)
                            <button type="button"
                                    onclick="document.getElementById('photo-picker')?.click()"
                                    class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-camera mr-2"></i> Manage Photos
                            </button>
                        @endif

                        @if($isAssignedTenant)
                            <a href="{{ route('tenant.property-units.my-unit') }}"
                               class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                               style="background-color: var(--primary); color: white;">
                                <i class="fas fa-home mr-2"></i> My Unit Dashboard
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- UNIT MANAGEMENT --}}
        @if($showManagementActions)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-cog mr-2"></i> Unit Management
                        @if($isOnAdminDashboard)
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user-shield mr-1"></i> Admin
                            </span>
                        @elseif($isLinkedFamilyMember && !$isOwner)
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user-friends mr-1"></i> Family-Linked
                            </span>
                        @endif
                    </h3>

                    <div class="space-y-2">
                        @if($unit->tenant_status === PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                            @if($isAdmin || $isSuperAdmin || $canManageUnits)
                                <a href="{{ route('property-units.assignment-details', $unit->id) }}"
                                   class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-clipboard-check mr-2"></i> Review Assignment
                                </a>
                            @endif
                        @endif

                        @if($unit->is_available && !$isOccupied && $canManageUnits)
                            <a href="{{ route('property-units.assign-tenant.form', $unit->id) }}"
                               class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium shadow-lg hover:shadow-xl transition-all duration-200"
                               style="background-color: var(--primary); color: white;">
                                <i class="fas fa-user-plus mr-2"></i> Assign New Tenant
                            </a>
                        @endif

                        @if($isAdmin || $isSuperAdmin)
                            <a href="{{ route('admin.property-units.pending-approvals') }}"
                               class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-clock mr-2"></i> Pending Approvals
                            </a>
                        @endif

                        {{-- Delete — owner only, non-occupied --}}
                        @if($canDelete)
                            <button type="button"
                                    onclick="confirmDeleteUnit()"
                                    class="w-full px-3 py-2 rounded-lg inline-flex items-center text-sm font-medium mt-2"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-trash-alt mr-2"></i> Delete Unit
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- RECENT ACTIVITY --}}
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> Recent Activity
                </h3>

                <div class="activity-timeline">
                    @forelse($activities ?? [] as $activity)
                        <div class="activity-item mb-4 last:mb-0">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--{{ $activity['color'] ?? 'secondary' }}-rgb), 0.1); color: var(--{{ $activity['color'] ?? 'secondary' }});">
                                    <i class="{{ $activity['icon'] ?? 'fas fa-info' }} text-sm"></i>
                                </div>

                                <div class="flex-grow">
                                    <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">
                                        {{ $activity['description'] ?? 'Activity' }}
                                    </p>
                                    <div class="flex justify-between items-center">
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            By {{ $activity['user'] ?? 'System' }}
                                        </p>
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            {{ isset($activity['date']) ? $activity['date']->diffForHumans() : 'Just now' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <div class="w-12 h-12 rounded-full mx-auto mb-3 flex items-center justify-center"
                                 style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                <i class="fas fa-history"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     LIGHTBOX OVERLAY
     ============================================================ --}}
<div id="photo-lightbox"
     class="fixed inset-0 z-[9998] hidden items-center justify-center"
     style="background: rgba(0,0,0,0.92); backdrop-filter: blur(6px);">
    <button type="button"
            id="lightbox-close"
            class="absolute top-5 right-5 w-11 h-11 rounded-full flex items-center justify-center text-white text-xl hover:bg-white/10 transition-colors">
        <i class="fas fa-times"></i>
    </button>

    <button type="button"
            id="lightbox-prev"
            class="absolute left-4 md:left-8 w-12 h-12 rounded-full flex items-center justify-center text-white text-xl hover:bg-white/10 transition-colors">
        <i class="fas fa-chevron-left"></i>
    </button>

    <button type="button"
            id="lightbox-next"
            class="absolute right-4 md:right-8 w-12 h-12 rounded-full flex items-center justify-center text-white text-xl hover:bg-white/10 transition-colors">
        <i class="fas fa-chevron-right"></i>
    </button>

    <figure class="max-w-[92vw] max-h-[88vh] flex flex-col items-center">
        <img id="lightbox-image"
             src=""
             alt=""
             class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
        <figcaption id="lightbox-caption"
                    class="mt-4 text-center text-sm text-white/70"></figcaption>
        <p id="lightbox-counter" class="mt-1 text-center text-xs text-white/50"></p>
    </figure>
</div>
@endsection

@section('scripts')
<script>
    // ============================================================
    // SERVER FACTS
    // ============================================================
    window.__PU = {
        isOnAdminDashboard: @json($isOnAdminDashboard),
        isOnLandlordDashboard: @json($isOnLandlordDashboard),
        unitId: {{ $unit->id }},
        unitIdentifier: @json($unit->full_unit_identifier),
        indexUrl: @json(route($routePrefix . '.index')),

        // Photo subsystem
        canManagePhotos: @json((bool) $canManagePhotos),
        photoUploadUrl: @json($photoUploadUrl),
        photoDeleteUrlBase: @json($photoDeleteUrlBase),
        photoCoverUrlBase: @json($photoCoverUrlBase),
        photoMaxCount: {{ (int) $photoMaxCount }},
        photoMaxKb: {{ (int) $photoMaxKb }},
        photoMimes: @json($photoMimes),
        photos: @json($unitPhotos->values()),
        csrfToken: '{{ csrf_token() }}',
    };

    document.addEventListener('DOMContentLoaded', function () {
        autoHideFlash();
        initPhotoGallery();
    });

    function autoHideFlash() {
        ['bg-green-100', 'bg-red-100'].forEach(cls => {
            const el = document.querySelector('.' + cls);
            if (el) {
                setTimeout(() => { el.style.display = 'none'; }, 5000);
            }
        });
    }

    // ============================================================
    // PHOTO GALLERY / LIGHTBOX
    // ============================================================
    let lightboxIndex = 0;

    window.__PU_OPEN_LIGHTBOX = function (index) {
        const photos = window.__PU.photos || [];
        if (!photos.length) return;

        lightboxIndex = Math.max(0, Math.min(index, photos.length - 1));

        const overlay = document.getElementById('photo-lightbox');
        const img     = document.getElementById('lightbox-image');
        const caption = document.getElementById('lightbox-caption');
        const counter = document.getElementById('lightbox-counter');

        const photo = photos[lightboxIndex];
        img.src = photo.url;
        img.alt = photo.caption || window.__PU.unitIdentifier;
        caption.textContent = photo.caption || '';
        counter.textContent = `${lightboxIndex + 1} / ${photos.length}`;

        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.body.style.overflow = 'hidden';

        // Show/hide prev/next when only one photo
        document.getElementById('lightbox-prev').style.display = photos.length > 1 ? '' : 'none';
        document.getElementById('lightbox-next').style.display = photos.length > 1 ? '' : 'none';
    };

    function closeLightbox() {
        const overlay = document.getElementById('photo-lightbox');
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function stepLightbox(delta) {
        const photos = window.__PU.photos || [];
        if (photos.length < 2) return;
        lightboxIndex = (lightboxIndex + delta + photos.length) % photos.length;
        window.__PU_OPEN_LIGHTBOX(lightboxIndex);
    }

    // ============================================================
    // PHOTO UPLOAD / DELETE / SET-COVER
    // ============================================================
    function initPhotoGallery() {
        const picker = document.getElementById('photo-picker');
        const openBtn = document.getElementById('btn-open-photo-picker');

        if (openBtn && picker) {
            openBtn.addEventListener('click', () => picker.click());
        }

        if (picker) {
            picker.addEventListener('change', async (e) => {
                const files = Array.from(e.target.files || []);
                picker.value = ''; // allow re-selecting the same file
                if (!files.length) return;

                await uploadPhotos(files);
            });
        }

        // Lightbox controls
        document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
        document.getElementById('lightbox-prev')?.addEventListener('click', () => stepLightbox(-1));
        document.getElementById('lightbox-next')?.addEventListener('click', () => stepLightbox(1));

        document.getElementById('photo-lightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'photo-lightbox') closeLightbox();
        });

        document.addEventListener('keydown', (e) => {
            const overlay = document.getElementById('photo-lightbox');
            if (!overlay || overlay.classList.contains('hidden')) return;
            if (e.key === 'Escape')      closeLightbox();
            if (e.key === 'ArrowLeft')   stepLightbox(-1);
            if (e.key === 'ArrowRight')  stepLightbox(1);
        });
    }

    async function uploadPhotos(files) {
        const state = window.__PU;
        if (!state.photoUploadUrl) return;

        const remaining = state.photoMaxCount - (state.photos?.length || 0);
        if (remaining <= 0) {
            showToast('warning', `Maximum of ${state.photoMaxCount} photos reached.`);
            return;
        }
        if (files.length > remaining) {
            showToast('warning', `Only ${remaining} more photo(s) can be added. Extra files were skipped.`);
            files = files.slice(0, remaining);
        }

        const formData = new FormData();
        files.forEach(f => formData.append('photos[]', f));

        const progressWrap  = document.getElementById('photo-upload-progress');
        const progressBar   = document.getElementById('photo-upload-bar');
        const progressLabel = document.getElementById('photo-upload-label');

        if (progressWrap) {
            progressWrap.classList.remove('hidden');
            progressBar.style.width = '0%';
            progressLabel.textContent = `Uploading 0 of ${files.length}…`;
        }

        try {
            const xhr = new XMLHttpRequest();
            const promise = new Promise((resolve, reject) => {
                xhr.open('POST', state.photoUploadUrl, true);
                xhr.setRequestHeader('X-CSRF-TOKEN', state.csrfToken);
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.onprogress = (evt) => {
                    if (evt.lengthComputable && progressBar && progressLabel) {
                        const pct = Math.round((evt.loaded / evt.total) * 100);
                        progressBar.style.width = pct + '%';
                        progressLabel.textContent = `Uploading… ${pct}%`;
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try { resolve(JSON.parse(xhr.responseText || '{}')); }
                        catch { resolve({}); }
                    } else {
                        let msg = 'Upload failed';
                        try {
                            const data = JSON.parse(xhr.responseText || '{}');
                            msg = data.error || (data.errors ? Object.values(data.errors).flat()[0] : msg);
                        } catch {}
                        reject(new Error(msg));
                    }
                };
                xhr.onerror = () => reject(new Error('Network error during upload'));
                xhr.send(formData);
            });

            const data = await promise;

            if (progressLabel) progressLabel.textContent = 'Upload complete — refreshing…';
            showToast('success', 'Photos uploaded successfully.');
            setTimeout(() => window.location.reload(), 600);

            return data;
        } catch (err) {
            console.error('uploadPhotos error:', err);
            showToast('error', err.message || 'Upload failed. Please try again.');
            if (progressWrap) progressWrap.classList.add('hidden');
        }
    }

    window.__PU_DELETE_PHOTO = async function (photoId) {
        const state = window.__PU;
        if (!state.photoDeleteUrlBase) return;

        if (!confirm('Delete this photo? This cannot be undone.')) return;

        const url = state.photoDeleteUrlBase.replace('__ID__', photoId);

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': state.csrfToken,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) {
                throw new Error(data.error || 'Failed to delete photo');
            }
            showToast('success', 'Photo deleted.');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            console.error('deletePhoto error:', err);
            showToast('error', err.message || 'Could not delete photo.');
        }
    };

    window.__PU_SET_COVER = async function (photoId) {
        const state = window.__PU;
        if (!state.photoCoverUrlBase) return;

        const url = state.photoCoverUrlBase.replace('__ID__', photoId);

        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': state.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) {
                throw new Error(data.error || 'Failed to set cover');
            }
            showToast('success', 'Cover photo updated.');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            console.error('setCoverPhoto error:', err);
            showToast('error', err.message || 'Could not update cover photo.');
        }
    };

    // ============================================================
    // UNIT DELETE
    // ============================================================
    function confirmDeleteUnit() {
        const { unitId, unitIdentifier, isOnAdminDashboard, isOnLandlordDashboard, indexUrl } = window.__PU;

        if (!confirm(`Are you sure you want to delete unit "${unitIdentifier}"? This action cannot be undone.`)) {
            return;
        }

        const deleteUrl = isOnAdminDashboard
            ? `/admin/property-units/${unitId}`
            : isOnLandlordDashboard
                ? `/landlord/property-units/${unitId}`
                : `/property-units/${unitId}`;

        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete unit');
            }
            return data;
        })
        .then(data => {
            showToast('success', data.message || 'Unit deleted successfully');
            setTimeout(() => { window.location.href = indexUrl; }, 1500);
        })
        .catch(error => {
            console.error('confirmDeleteUnit error:', error);
            showToast('error', error.message || 'An error occurred while deleting the unit');
        });
    }

    // ============================================================
    // TOAST
    // ============================================================
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
            border-radius: 8px;
            color: white;
            font-size: 14px;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
        `;

        const colors = {
            success: 'var(--success)',
            error:   'var(--danger)',
            warning: 'var(--warning)',
            info:    'var(--info)',
        };
        toast.style.backgroundColor = colors[type] || colors.info;

        const iconMap = {
            success: 'check-circle',
            error:   'exclamation-circle',
            warning: 'exclamation-triangle',
            info:    'info-circle',
        };

        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${iconMap[type] || 'info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;

        const closeBtn = toast.querySelector('button');
        closeBtn.addEventListener('click', () => dismiss());

        const dismiss = () => {
            toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        };

        setTimeout(() => { if (toast.parentNode) dismiss(); }, 5000);

        toastContainer.appendChild(toast);
    }
</script>
@endsection

@push('styles')
<style>
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    @keyframes slideOutRight {
        from { transform: translateX(0);    opacity: 1; }
        to   { transform: translateX(100%); opacity: 0; }
    }

    .bg-green-100 { background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); }
    .bg-red-100   { background-color: rgba(var(--danger-rgb),  0.1); border-color: rgba(var(--danger-rgb),  0.3); }

    .animate-fadeInUp {
        animation: fadeInUp 0.3s ease-out;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .gradient-bg {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    /* Photo gallery */
    #photo-thumb-strip {
        scrollbar-width: thin;
        scrollbar-color: rgba(0,0,0,0.2) transparent;
    }
    #photo-thumb-strip::-webkit-scrollbar {
        height: 6px;
    }
    #photo-thumb-strip::-webkit-scrollbar-track {
        background: transparent;
    }
    #photo-thumb-strip::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.15);
        border-radius: 9999px;
    }

    #photo-lightbox {
        animation: fadeInUp 0.18s ease-out;
    }

    #photo-lightbox img {
        animation: lightboxZoom 0.22s ease-out;
    }

    @keyframes lightboxZoom {
        from { transform: scale(0.97); opacity: 0.7; }
        to   { transform: scale(1);    opacity: 1; }
    }

    @media (max-width: 640px) {
        #photo-lightbox figcaption,
        #photo-lightbox #lightbox-counter {
            max-width: 92vw;
            padding: 0 1rem;
        }
    }
</style>
@endpush