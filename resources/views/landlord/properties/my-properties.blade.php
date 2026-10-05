@extends('layouts.landlord')

@section('title', 'My Property')

@php
    // ✅ UPDATED: Pre-compute family-link counts across all statuses
    // so badges and stat chips reflect the two-stage approval workflow.
    $familyLinkStats = [
        'total'               => 0,
        'awaiting_landlord'   => 0,
        'awaiting_admin'      => 0,
        'pending'             => 0,   // awaiting_landlord + awaiting_admin + legacy pending
        'approved'            => 0,
        'rejected'            => 0,
        'revoked'             => 0,
        'cancelled'           => 0,
    ];
    $propertyFamilyLinkCounts = [];

    if (class_exists(\App\Models\PropertyFamilyLink::class)) {
        $propertyIds = $properties->pluck('id')->all();

        if (!empty($propertyIds)) {
            $counts = \App\Models\PropertyFamilyLink::query()
                ->whereIn('property_id', $propertyIds)
                ->whereIn('status', [
                    'pending_landlord_confirmation',
                    'pending_admin_review',
                    'pending',
                    'approved',
                    'rejected',
                    'revoked',
                    'cancelled',
                ])
                ->selectRaw('property_id, status, COUNT(*) as count')
                ->groupBy('property_id', 'status')
                ->get();

            foreach ($counts as $row) {
                $status = $row->status;
                $count  = (int) $row->count;

                $propertyFamilyLinkCounts[$row->property_id][$status] = $count;

                // Aggregate per status
                if (array_key_exists($status, $familyLinkStats)) {
                    $familyLinkStats[$status] += $count;
                }

                // Roll pending stages into a single "pending" total
                if (in_array($status, ['pending_landlord_confirmation', 'pending_admin_review', 'pending'], true)) {
                    $familyLinkStats['pending'] += $count;
                }

                $familyLinkStats['total'] += $count;
            }
        }
    }
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">My Property</h2>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> View and manage your registered properties

                {{-- ✅ UPDATED: Global family-links indicator with two-stage stats --}}
                @if($familyLinkStats['total'] > 0)
                    <span class="ml-3 inline-flex items-center px-2 py-1 rounded-full text-xs"
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-user-friends mr-1"></i>
                        {{ $familyLinkStats['total'] }} family link{{ $familyLinkStats['total'] > 1 ? 's' : '' }}

                        @if($familyLinkStats['awaiting_landlord'] > 0)
                            <span class="ml-1 px-1.5 py-0.5 rounded-full"
                                  style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);"
                                  title="Awaiting your confirmation">
                                {{ $familyLinkStats['awaiting_landlord'] }} awaiting you
                            </span>
                        @endif

                        @if($familyLinkStats['awaiting_admin'] > 0)
                            <span class="ml-1 px-1.5 py-0.5 rounded-full"
                                  style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);"
                                  title="Awaiting admin review">
                                {{ $familyLinkStats['awaiting_admin'] }} with admin
                            </span>
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ⭐ STATISTICS CARDS - 6 CARDS                 -->
    <!-- ============================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <!-- 1. Total Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-3">
                    <i class="fas fa-building text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Total</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['total'] ?? $properties->count() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Active Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-3">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Active</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['active'] ?? $properties->where('status', 'active')->count() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. Under Construction -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-3">
                    <i class="fas fa-hard-hat text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Under Construction</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['under_construction'] ?? 0 }}
                    </p>
                    @if(($stats['with_plans'] ?? 0) > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-file mr-1"></i> {{ $stats['with_plans'] }} have plans
                    </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- 4. Vacant Land -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-3">
                    <i class="fas fa-tree text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Vacant Land</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['vacant_land'] ?? 0 }}
                    </p>
                    @if(($stats['vacant_land_with_plans'] ?? 0) > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-file mr-1"></i> {{ $stats['vacant_land_with_plans'] }} have plans
                    </p>
                    @endif
                    @if(($stats['vacant_land_no_plans'] ?? 0) > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> {{ $stats['vacant_land_no_plans'] }} no plans
                    </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- 5. Rented Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-pink-100 text-pink-600 mr-3">
                    <i class="fas fa-users text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Rented</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['rented'] ?? $properties->where('is_rented', true)->count() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 6. With Digital Address -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-cyan-100 text-cyan-600 mr-3">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">GPS Address</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['with_digital_address'] ?? $properties->whereNotNull('digital_address')->count() }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Properties Table Card -->
    <div class="card p-6">
        <div class="mb-4 flex justify-between items-center flex-wrap gap-2">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $properties->count() }} of {{ $properties->total() ?? $properties->count() }} properties
            </p>
            @if($properties->count() > 0)
            <div class="flex items-center space-x-2 flex-wrap gap-1">
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-tree mr-1"></i> Vacant: {{ $stats['vacant_land'] ?? 0 }}
                </span>
                @if(($stats['vacant_land_no_plans'] ?? 0) > 0)
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-clock mr-1"></i> No Plans: {{ $stats['vacant_land_no_plans'] ?? 0 }}
                </span>
                @endif
                @if(($stats['vacant_land_with_plans'] ?? 0) > 0)
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-file mr-1"></i> With Plans: {{ $stats['vacant_land_with_plans'] ?? 0 }}
                </span>
                @endif
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-hard-hat mr-1"></i> Construction: {{ $stats['under_construction'] ?? 0 }}
                </span>

                {{-- ✅ UPDATED: Family Links chip with two-stage counts --}}
                @if($familyLinkStats['total'] > 0)
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-user-friends mr-1"></i> Family Links: {{ $familyLinkStats['total'] }}
                    @if($familyLinkStats['awaiting_landlord'] > 0)
                        <span class="ml-1" style="color: var(--primary); font-weight: 600;">
                            • {{ $familyLinkStats['awaiting_landlord'] }} awaiting you
                        </span>
                    @endif
                    @if($familyLinkStats['awaiting_admin'] > 0)
                        <span class="ml-1">• {{ $familyLinkStats['awaiting_admin'] }} with admin</span>
                    @endif
                </span>
                @endif
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Info</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Location</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Construction Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Registration Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="propertiesTable">
                    @forelse($properties as $property)
                    @php
                        // ============================================
                        // ⭐ FIXED: ROBUST CONSTRUCTION STATUS DETECTION
                        // ============================================

                        // Helper: Check if status is empty
                        $statusIsEmpty = empty($property->status) || $property->status === '';

                        // Check if property_type_id is truly empty
                        $hasPropertyType = $property->property_type_id !== null &&
                                           $property->property_type_id !== '' &&
                                           $property->property_type_id != 0;

                        // Check if construction_status is truly empty or 'vacant'
                        $hasConstructionStatus = $property->construction_status !== null &&
                                                 $property->construction_status !== '' &&
                                                 $property->construction_status !== 'vacant';

                        // Check if property has plans
                        $hasPlans = $property->has_plans === true ||
                                    $property->has_plans === 1 ||
                                    $property->has_plans === '1' ||
                                    $property->has_plans === 'yes';

                        // Check if property has construction documents
                        $hasConstructionDocs = $property->construction_documents &&
                                               is_array($property->construction_documents) &&
                                               count($property->construction_documents) > 0;

                        // Check if property has any construction details
                        $hasConstructionDetails = $hasPropertyType ||
                                                  $hasConstructionStatus ||
                                                  $hasPlans ||
                                                  $hasConstructionDocs;

                        // ✅ VACANT LAND DETECTION
                        $isVacant = ($property->status === 'vacant' || $statusIsEmpty) &&
                                   (!$hasConstructionDetails ||
                                    ($hasConstructionDetails && $property->construction_status === 'vacant'));

                        // ✅ UNDER CONSTRUCTION DETECTION
                        $isUnderConstruction = $property->status === 'under_construction' ||
                                              $property->construction_status === 'under_construction' ||
                                              ($property->construction_status === 'active' && $hasConstructionDetails);

                        // Check if property is active (completed construction)
                        $isActive = $property->status === 'active' &&
                                   ($property->construction_status === 'active' ||
                                    $property->construction_status === 'completed' ||
                                    $property->construction_status === null);

                        // Check if property has active construction contract
                        $hasActiveContract = false;
                        if (class_exists('App\Models\ConstructionContract')) {
                            $hasActiveContract = $property->constructionContracts()
                                ->whereIn('status', ['pending_approval', 'approved', 'in_progress'])
                                ->exists();
                        }

                        // Check if property has a completed contract
                        $hasCompletedContract = false;
                        if (class_exists('App\Models\ConstructionContract')) {
                            $hasCompletedContract = $property->constructionContracts()
                                ->where('status', 'completed')
                                ->exists();
                        }

                        // ✅ UPDATED: Family-link counts for THIS property (two-stage aware)
                        $flCounts         = $propertyFamilyLinkCounts[$property->id] ?? [];
                        $flAwaitingYou    = $flCounts['pending_landlord_confirmation'] ?? 0;
                        $flAwaitingAdmin  = $flCounts['pending_admin_review']          ?? 0;
                        $flLegacyPending  = $flCounts['pending']                       ?? 0;
                        $flApproved       = $flCounts['approved']                      ?? 0;
                        $flPending        = $flAwaitingYou + $flAwaitingAdmin + $flLegacyPending;
                        $flTotal          = $flPending + $flApproved;

                        // Determine display status
                        if ($isVacant) {
                            $displayStatus = 'vacant';
                            $statusIcon = 'fa-tree';
                            $statusColor = 'info';
                            $statusLabel = 'Vacant Land';
                        } elseif ($isUnderConstruction) {
                            $displayStatus = 'under_construction';
                            $statusIcon = 'fa-hard-hat';
                            $statusColor = 'warning';
                            $statusLabel = 'Under Construction';
                        } elseif ($isActive) {
                            $displayStatus = 'active';
                            $statusIcon = 'fa-check-circle';
                            $statusColor = 'success';
                            $statusLabel = 'Active';
                        } else {
                            $displayStatus = $property->status ?? 'unknown';
                            $statusIcon = 'fa-question-circle';
                            $statusColor = 'secondary';
                            $statusLabel = ucfirst(str_replace('_', ' ', $displayStatus));
                        }
                    @endphp
                    <tr class="border-b transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-blue-600"></i>
                                </div>
                                <div>
                                    @if($property->property_name)
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</p>
                                    @endif
                                    <div class="flex items-center space-x-2 mt-1 flex-wrap">
                                        @if($property->house_number)
                                            <span class="text-sm px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                #{{ $property->house_number }}
                                            </span>
                                        @endif
                                        @if($property->block_number)
                                            <span class="text-sm px-2 py-1 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                Block {{ $property->block_number }}
                                            </span>
                                        @endif
                                        @if($property->plot_number)
                                            <span class="text-sm px-2 py-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                Plot {{ $property->plot_number }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-road mr-1"></i>{{ $property->street_name ?? 'Street not specified' }}
                                    </p>
                                    @if($property->registered_by)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1 bg-green-100 text-green-800">
                                        <i class="fas fa-user-check mr-1"></i>
                                        Registered by: {{ $property->registeredBy->name ?? 'Field Agent' }}
                                    </span>
                                    @endif

                                    {{-- ✅ UPDATED: Inline family-links indicator with two-stage states --}}
                                    @if($flTotal > 0)
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs"
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-user-friends mr-1"></i>
                                            {{ $flTotal }} family link{{ $flTotal > 1 ? 's' : '' }}
                                            @if($flAwaitingYou > 0)
                                                <span class="ml-1 font-semibold" style="color: var(--primary);">
                                                    • {{ $flAwaitingYou }} awaiting you
                                                </span>
                                            @endif
                                            @if($flAwaitingAdmin > 0)
                                                <span class="ml-1" style="color: var(--warning);">
                                                    • {{ $flAwaitingAdmin }} with admin
                                                </span>
                                            @endif
                                        </span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            @if($property->zone && $property->section)
                                <p class="font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>
                                    {{ $property->zone }} - {{ $property->section }}
                                </p>
                            @elseif($property->zone)
                                <p class="font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>
                                    {{ $property->zone }}
                                </p>
                            @else
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-marker-alt mr-1"></i> Location not specified
                                </span>
                            @endif

                            @if($property->digital_address)
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i> {{ $property->digital_address }}
                                    </span>
                                    <button type="button" class="p-1 text-xs rounded ml-1" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Copy Digital Address" onclick="copyToClipboard('{{ $property->digital_address }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            @endif
                        </td>
                        <td class="p-3">
                            {{-- Construction Details Display --}}
                            @if($hasPropertyType && $property->propertyType)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                    <i class="fas fa-building mr-1"></i> {{ $property->propertyType->name ?? 'Property' }}
                                </span>
                                @if($property->custom_property_type)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--secondary);">
                                        <i class="fas fa-tag mr-1"></i> {{ $property->custom_property_type }}
                                    </span>
                                @endif
                            @elseif($isVacant)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-tree mr-1"></i> Vacant Land
                                </span>

                                @if($hasConstructionDetails)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i> Plans Provided
                                    </span>
                                    @if($hasPlans)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                        <i class="fas fa-file-pdf mr-1"></i> Docs Uploaded
                                    </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i> No Plans Yet
                                    </span>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--secondary);">
                                    <i class="fas fa-question-circle mr-1"></i> Not Specified
                                </span>
                            @endif

                            @if($hasConstructionStatus)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-hard-hat mr-1"></i>
                                    {{ ucfirst(str_replace('_', ' ', $property->construction_status)) }}
                                </p>
                            @endif

                            @if($property->estimated_completion)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar-check mr-1"></i>
                                    Est. Completion: {{ \Carbon\Carbon::parse($property->estimated_completion)->format('M d, Y') }}
                                </p>
                            @endif

                            @if($property->bedrooms)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-bed mr-1"></i> {{ $property->bedrooms }} bedroom(s)
                                </p>
                            @endif

                            @if($property->bathrooms)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-bath mr-1"></i> {{ $property->bathrooms }} bathroom(s)
                                </p>
                            @endif

                            {{-- Construction Contract Status Badge --}}
                            @if($hasActiveContract)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                    <i class="fas fa-file-contract mr-1"></i> Contract Active
                                </span>
                            @endif
                            @if($hasCompletedContract)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> Contract Completed
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($property->registrationPlan)
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map mr-1 text-blue-500"></i>
                                    {{ $property->registrationPlan->zone ?? 'N/A' }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($property->registration_pattern)
                                        Pattern: <span class="font-mono font-bold">{{ $property->registration_pattern }}</span>
                                    @endif
                                </p>
                            @else
                                <span class="text-xs text-red-500">No Registration Plan</span>
                            @endif

                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ \Carbon\Carbon::parse($property->registration_date)->format('M d, Y') }}
                            </p>
                        </td>
                        <td class="p-3">
                            {{-- ⭐ FIXED: STATUS COLUMN --}}
                            @php
                                // Get status color config
                                $statusColors = [
                                    'vacant' => ['bg' => 'info', 'icon' => 'fa-tree'],
                                    'under_construction' => ['bg' => 'warning', 'icon' => 'fa-hard-hat'],
                                    'active' => ['bg' => 'success', 'icon' => 'fa-check-circle'],
                                    'inactive' => ['bg' => 'secondary', 'icon' => 'fa-pause-circle'],
                                    'under_maintenance' => ['bg' => 'warning', 'icon' => 'fa-tools'],
                                    'unknown' => ['bg' => 'secondary', 'icon' => 'fa-question-circle']
                                ];

                                // Get the correct config based on display status
                                $statusConfig = $statusColors[$displayStatus] ?? $statusColors['unknown'];

                                // Build status label with additional info
                                $statusLabelExtra = '';
                                if ($isVacant) {
                                    if ($hasConstructionDetails) {
                                        $statusLabelExtra = ' (Has Plans)';
                                    } else {
                                        $statusLabelExtra = ' (No Plans)';
                                    }
                                }
                            @endphp

                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium"
                                  style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.15);
                                         color: var(--{{ $statusConfig['bg'] }});
                                         border: 1px solid rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2);">
                                <i class="fas {{ $statusConfig['icon'] }} mr-1.5"></i>
                                {{ $statusLabel }}{{ $statusLabelExtra }}
                            </span>

                            @if($isVacant && !$hasConstructionDetails)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1"
                                      style="background-color: rgba(var(--warning-rgb), 0.15);
                                             color: var(--warning);
                                             border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                    <i class="fas fa-clock mr-1"></i> Ready for Development
                                </span>
                            @endif

                            @if($isUnderConstruction)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1"
                                      style="background-color: rgba(var(--warning-rgb), 0.15);
                                             color: var(--warning);
                                             border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                    <i class="fas fa-hard-hat mr-1"></i> In Progress
                                </span>
                            @endif

                            @if($property->is_rented)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1"
                                      style="background-color: rgba(var(--success-rgb), 0.15);
                                             color: var(--success);
                                             border: 1px solid rgba(var(--success-rgb), 0.2);">
                                    <i class="fas fa-users mr-1"></i> Rented
                                </span>
                            @endif

                            {{-- Show completion progress for under construction --}}
                            @if($isUnderConstruction && $property->estimated_completion)
                                @php
                                    $now = \Carbon\Carbon::now();
                                    $completion = \Carbon\Carbon::parse($property->estimated_completion);
                                    $totalDays = $now->diffInDays($completion);
                                    // Simple progress estimation (if created_at is available)
                                    $progress = 0;
                                    if ($property->created_at) {
                                        $created = \Carbon\Carbon::parse($property->created_at);
                                        $elapsed = $now->diffInDays($created);
                                        $progress = min(round(($elapsed / ($totalDays + $elapsed)) * 100), 95);
                                    }
                                @endphp
                                @if($progress > 0)
                                <div class="mt-1 w-24">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-1.5" style="background-color: var(--bg-secondary);">
                                            <div class="h-1.5 rounded-full" style="width: {{ $progress }}%; background-color: var(--warning);"></div>
                                        </div>
                                        <span class="text-xs ml-1" style="color: var(--text-secondary);">{{ $progress }}%</span>
                                    </div>
                                </div>
                                @endif
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Details Button -->
                                <a href="{{ route('properties.show', $property->id) }}"
                                   class="p-2 rounded-lg"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- ✅ UPDATED: Family Links button with dual badges --}}
                                <a href="{{ route('landlord.properties.family-links', $property->id) }}"
                                   class="p-2 rounded-lg position-relative"
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                   title="Manage Family Links">
                                    <i class="fas fa-user-friends"></i>

                                    {{-- Primary badge: awaiting YOUR confirmation (needs action) --}}
                                    @if($flAwaitingYou > 0)
                                        <span class="absolute -top-1 -right-1 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center"
                                              style="background-color: var(--primary); font-size: 10px;"
                                              title="{{ $flAwaitingYou }} awaiting your confirmation">
                                            {{ $flAwaitingYou > 9 ? '9+' : $flAwaitingYou }}
                                        </span>
                                    {{-- Fallback: pending awaiting admin --}}
                                    @elseif($flAwaitingAdmin > 0)
                                        <span class="absolute -top-1 -right-1 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center"
                                              style="background-color: var(--warning); font-size: 10px;"
                                              title="{{ $flAwaitingAdmin }} awaiting admin review">
                                            {{ $flAwaitingAdmin > 9 ? '9+' : $flAwaitingAdmin }}
                                        </span>
                                    @endif
                                </a>

                                <!-- CONSTRUCTION UPDATE BUTTON -->
                                @if($isVacant)
                                <button type="button"
                                        class="p-2 rounded-lg construction-update-btn"
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                        title="{{ $hasConstructionDetails ? 'Update Construction Details' : 'Add Construction Details' }}"
                                        data-property-id="{{ $property->id }}"
                                        data-property-name="{{ $property->property_name }}"
                                        data-plot-number="{{ $property->plot_number ?? 'N/A' }}"
                                        data-has-construction="{{ $hasConstructionDetails ? 'true' : 'false' }}"
                                        data-property-type="{{ $property->property_type_id }}"
                                        data-custom-property-type="{{ $property->custom_property_type }}"
                                        data-construction-status="{{ $property->construction_status }}"
                                        data-bedrooms="{{ $property->bedrooms }}"
                                        data-estimated-completion="{{ $property->estimated_completion ? \Carbon\Carbon::parse($property->estimated_completion)->format('Y-m-d') : '' }}"
                                        data-has-plans="{{ $property->has_plans ? 'yes' : 'no' }}">
                                    <i class="fas fa-hard-hat"></i>
                                    @if(!$hasConstructionDetails)
                                        <span class="text-xs ml-1" style="color: var(--warning);">Add Plans</span>
                                    @else
                                        <span class="text-xs ml-1" style="color: var(--warning);">Update</span>
                                    @endif
                                </button>
                                @endif

                                <!-- CONSTRUCTION CONTRACT CREATE BUTTON -->
                                @if($isVacant && $hasConstructionDetails && !$hasActiveContract)
                                <a href="{{ route('landlord.construction.contract.create', ['property_id' => $property->id]) }}"
                                   class="p-2 rounded-lg"
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                   title="Create Construction Contract">
                                    <i class="fas fa-file-signature"></i>
                                    <span class="text-xs ml-1" style="color: var(--primary);">Contract</span>
                                </a>
                                @endif

                                <!-- MARK AS ACTIVE / COMPLETED BUTTON -->
                                @if($isUnderConstruction && $hasConstructionDetails)
                                    <button type="button"
                                            class="p-2 rounded-lg mark-active-btn"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                            title="Mark construction as complete and activate property"
                                            data-property-id="{{ $property->id }}"
                                            data-property-name="{{ $property->property_name }}"
                                            data-completion-date="{{ $property->estimated_completion ? \Carbon\Carbon::parse($property->estimated_completion)->format('M d, Y') : 'Not set' }}">
                                        <i class="fas fa-check-circle"></i>
                                        <span class="text-xs ml-1" style="color: var(--success);">Mark Active</span>
                                    </button>
                                @endif

                                <!-- Make Payment Button -->
                                @if($isActive || $isUnderConstruction || ($isVacant && $hasConstructionDetails))
@php
    // Collect the payable invoice IDs for this property so the payment form
    // can pre-select them. Only pending/overdue/processing and not soft-deleted.
    $payableInvoiceIds = \App\Models\Invoice::where('property_id', $property->id)
        ->whereIn('status', ['pending', 'overdue', 'processing'])
        ->whereNull('deleted_at')
        ->pluck('id')
        ->toArray();
@endphp
<a href="{{ route('landlord.payments.create', [
        'propertyId' => $property->id,
        'invoice_ids' => $payableInvoiceIds,
        'pre_selected' => 'true',
    ]) }}"
   class="p-2 rounded-lg"
   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
   title="Make Payment">
    <i class="fas fa-credit-card"></i>
</a>
@endif
                            </div>

                            <!-- Quick Actions -->
                            <div class="mt-2 flex flex-wrap gap-1">
                                @if($property->digital_address)
                                    <button type="button" class="p-1 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Copy Digital Address" onclick="copyToClipboard('{{ $property->digital_address }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                @endif
                                @if($property->landlord && $property->landlord->phone)
                                    <a href="tel:{{ $property->landlord->phone }}" class="p-1 text-xs rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Call Support">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-building text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No properties found</p>
                                <p class="text-sm">You haven't been assigned any properties yet.</p>
                                <p class="text-sm mt-2">Contact administration if you believe this is an error.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if(isset($properties) && method_exists($properties, 'hasPages') && $properties->hasPages())
        <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
            {{ $properties->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ============================================ --}}
{{-- MODALS — unchanged from previous version    --}}
{{-- ============================================ --}}

<!-- MARK AS ACTIVE CONFIRMATION MODAL -->
<div id="markActiveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--card-bg, #ffffff); border-radius: 0.75rem; width: 100%; max-width: 500px; position: relative;">
        <!-- Modal Header -->
        <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color, #e9ecef); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--text-primary, #1a1a2e);">
                <i class="fas fa-check-circle" style="color: var(--success, #10b981);"></i>
                Mark Property as Active
            </h3>
            <button id="closeMarkActiveModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary, #6c757d); padding: 0.5rem; min-width: 44px; min-height: 44px; border-radius: 0.5rem;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 1.5rem;">
            <div style="padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; background: rgba(var(--warning-rgb, 245, 158, 11), 0.1); border: 1px solid rgba(var(--warning-rgb, 245, 158, 11), 0.3); color: var(--warning, #f59e0b);">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>You are about to mark this property as <strong>Active/Completed</strong>. This action will change the property status from "Under Construction" to "Active".</span>
            </div>

            <div style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
                <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: var(--text-primary, #1a1a2e);">Property Details:</p>
                <p style="margin: 0.25rem 0; font-size: 0.9rem; color: var(--text-primary, #1a1a2e);">
                    <strong>Name:</strong> <span id="markActivePropertyName">-</span>
                </p>
                <p style="margin: 0.25rem 0; font-size: 0.9rem; color: var(--text-primary, #1a1a2e);">
                    <strong>Estimated Completion:</strong> <span id="markActiveCompletionDate">-</span>
                </p>
            </div>

            <form id="markActiveForm" action="{{ route('properties.mark-active') }}" method="POST">
                @csrf
                <input type="hidden" name="property_id" id="markActivePropertyId" value="">

                <div style="margin-bottom: 1rem;">
                    <label for="mark_active_notes" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                        Completion Notes (Optional)
                    </label>
                    <textarea id="mark_active_notes" name="completion_notes" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 80px; resize: vertical;" rows="3" placeholder="Any notes about the construction completion..."></textarea>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem;">
                    <input type="checkbox" id="mark_active_declaration" name="declaration" value="1" required style="width: 20px; height: 20px; cursor: pointer; margin-top: 0.15rem; flex-shrink: 0; min-width: 20px; min-height: 20px;">
                    <label for="mark_active_declaration" style="margin-bottom: 0; cursor: pointer; font-size: 0.9rem; color: var(--text-primary, #1a1a2e);">
                        I confirm that the construction is complete and the property is ready for occupancy. <span style="color: var(--danger, #ef4444);">*</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color, #e9ecef);">
                    <button type="button" id="cancelMarkActiveBtn" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; border: 1px solid var(--border-color, #e9ecef); background: var(--bg-secondary, #f8f9fa); color: var(--text-primary, #1a1a2e); min-height: 44px; font-size: 0.95rem;">
                        Cancel
                    </button>
                    <button type="submit" id="submitMarkActiveBtn" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; border: none; background: linear-gradient(135deg, var(--success, #10b981), var(--info, #06b6d4)); color: white; min-height: 44px; font-size: 0.95rem;">
                        <i class="fas fa-check-circle"></i> Confirm Active
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CONSTRUCTION DETAILS UPDATE MODAL -->
<div id="constructionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--card-bg, #ffffff); border-radius: 0.75rem; width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; position: relative;">
        <!-- Modal Header -->
        <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color, #e9ecef); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--card-bg, #ffffff); z-index: 10;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--text-primary, #1a1a2e);">
                <i class="fas fa-hard-hat" style="color: var(--warning, #f59e0b);"></i>
                <span id="modalTitle">Update Construction Details</span>
            </h3>
            <button id="closeConstructionModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary, #6c757d); padding: 0.5rem; min-width: 44px; min-height: 44px; border-radius: 0.5rem;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 1.5rem;">
            <!-- Info Alert -->
            <div style="padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; background: rgba(var(--info-rgb, 6, 182, 212), 0.1); border: 1px solid rgba(var(--info-rgb, 6, 182, 212), 0.3); color: var(--info, #06b6d4);">
                <i class="fas fa-info-circle"></i>
                <span id="propertyInfoText">Providing construction details for your vacant land.</span>
            </div>

            <form id="constructionUpdateForm" action="{{ route('properties.update-construction') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="property_id" id="constructionPropertyId" value="">

                <!-- Construction Details Section -->
                <div style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                        Construction Details
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <label for="property_type" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Planned Property Type <span style="color: var(--danger, #ef4444);">*</span>
                            </label>
                            <select id="property_type" name="property_type_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;">
                                <option value="">Select property type</option>
                                @foreach($propertyTypes ?? [] as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                                <option value="other">Other (Specify below)</option>
                            </select>
                            <span class="error-message" id="propertyTypeError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                        </div>
                        <div style="margin-bottom: 0.75rem; display: none;" id="customPropertyTypeGroup">
                            <label for="custom_property_type" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Specify Property Type
                            </label>
                            <input type="text" id="custom_property_type" name="custom_property_type" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;" placeholder="e.g., Duplex, Townhouse">
                            <span class="error-message" id="customPropertyTypeError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <label for="construction_status" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Construction Status <span style="color: var(--danger, #ef4444);">*</span>
                            </label>
                            <select id="construction_status" name="construction_status" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;">
                                <option value="under_construction">Under Construction</option>
                                <option value="active">Active/Completed</option>
                                <option value="vacant">Vacant Land</option>
                                <option value="inactive">Inactive/Paused</option>
                            </select>
                            <span class="error-message" id="constructionStatusError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label for="estimated_bedrooms" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Estimated Bedrooms
                            </label>
                            <input type="number" id="estimated_bedrooms" name="estimated_bedrooms" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;" min="1" max="50" placeholder="e.g., 4">
                            <span class="error-message" id="bedroomsError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <label for="estimated_completion" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Estimated Completion Date
                            </label>
                            <input type="date" id="estimated_completion" name="estimated_completion" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;">
                            <span class="error-message" id="completionError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label for="has_plans" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                                Do you have architectural plans?
                            </label>
                            <select id="has_plans" name="has_plans" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 44px;">
                                <option value="">Select...</option>
                                <option value="yes">Yes, I have plans</option>
                                <option value="no">No, not yet</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label for="construction_notes" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                            Additional Notes
                        </label>
                        <textarea id="construction_notes" name="construction_notes" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; background: var(--bg-primary, #ffffff); color: var(--text-primary, #1a1a2e); font-size: 0.95rem; min-height: 80px; resize: vertical;" rows="3" placeholder="Any additional information about the construction plans..."></textarea>
                        <span class="error-message" id="notesError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                    </div>
                </div>

                <!-- Documents Section -->
                <div style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                        Construction Documents
                    </h4>
                    <div style="margin-bottom: 0.75rem;">
                        <label for="construction_documents" style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.85rem; color: var(--text-primary, #1a1a2e);">
                            Upload Construction Plans/Documents
                        </label>
                        <div id="constructionDocUploadArea" style="border: 2px dashed var(--border-color, #e9ecef); border-radius: 0.5rem; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease; min-height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary, #3b82f6); margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0; font-size: 0.9rem; color: var(--text-primary, #1a1a2e);">Click or drag to upload construction plans, architectural drawings</p>
                            <p style="font-size: 0.7rem; color: var(--text-secondary, #6c757d); margin-top: 0.25rem;">PDF, JPG, JPEG, PNG (Max 10MB each)</p>
                            <input type="file" name="construction_documents[]" id="construction_documents" style="display: none;" multiple accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div id="constructionDocPreview" style="margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem; display: none;"></div>
                        <small style="font-size: 0.75rem; color: var(--text-secondary, #6c757d);">
                            <i class="fas fa-info-circle mr-1"></i>
                            You can upload multiple documents. Existing documents will be preserved.
                        </small>
                    </div>
                </div>

                <!-- Declaration -->
                <div style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-top: 0.5rem;">
                        <input type="checkbox" id="declaration_construction" name="declaration" value="1" required style="width: 20px; height: 20px; cursor: pointer; margin-top: 0.15rem; flex-shrink: 0; min-width: 20px; min-height: 20px;">
                        <label for="declaration_construction" style="margin-bottom: 0; cursor: pointer; font-size: 0.9rem; color: var(--text-primary, #1a1a2e);">
                            I hereby declare that the construction information provided is true and correct. <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                    </div>
                    <span class="error-message" id="declarationError" style="color: var(--danger, #ef4444); font-size: 0.75rem; margin-top: 0.25rem; display: block;"></span>
                </div>

                <!-- Form Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color, #e9ecef); flex-wrap: wrap;">
                    <button type="button" id="cancelConstructionBtn" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; border: 1px solid var(--border-color, #e9ecef); background: var(--bg-secondary, #f8f9fa); color: var(--text-primary, #1a1a2e); min-height: 44px; font-size: 0.95rem;">
                        Cancel
                    </button>
                    <button type="submit" id="submitConstructionBtn" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; border: none; background: linear-gradient(135deg, var(--primary, #3b82f6), var(--secondary, #8b5cf6)); color: white; min-height: 44px; font-size: 0.95rem;">
                        <i class="fas fa-save"></i> Save Construction Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Overlay Styles -->
<style>
/* Modal Styles - Inline for reliability */
#constructionModal, #markActiveModal {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.5) !important;
    z-index: 9999 !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1rem !important;
}

#constructionModal.active, #markActiveModal.active {
    display: flex !important;
}

#constructionModal .error-message, #markActiveModal .error-message {
    display: block !important;
}

/* Dark theme support */
[data-theme="dark"] #constructionModal > div,
[data-theme="dark"] #markActiveModal > div {
    background: #1e293b !important;
}

[data-theme="dark"] #constructionModal .form-control,
[data-theme="dark"] #markActiveModal .form-control {
    background: #1a1a2e !important;
    color: #f1f5f9 !important;
    border-color: #334155 !important;
}

[data-theme="dark"] #constructionModal .form-section,
[data-theme="dark"] #markActiveModal .form-section {
    background: #16213e !important;
}

@media (max-width: 768px) {
    #constructionModal > div,
    #markActiveModal > div {
        max-width: 100% !important;
        max-height: 95vh !important;
        border-radius: 0.5rem !important;
    }

    #constructionModal .form-row,
    #markActiveModal .form-row {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
    }

    #constructionModal .form-actions,
    #markActiveModal .form-actions {
        flex-direction: column-reverse !important;
    }

    #constructionModal .form-actions .btn,
    #markActiveModal .form-actions .btn {
        width: 100% !important;
        justify-content: center !important;
    }
}
</style>
@endsection

@section('scripts')
<script>
// ============================================
// COMPLETE FIXED CONSTRUCTION MODAL SCRIPT
// WITH MARK AS ACTIVE FUNCTIONALITY
// ============================================
(function() {
    'use strict';

    console.log('🚀 Construction & Mark Active Script Initialized');

    // ============================================
    // TOAST SYSTEM
    // ============================================
    const Toast = {
        container: null,

        init() {
            if (this.container) return;
            this.container = document.createElement('div');
            this.container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 16px;
                z-index: 9999;
                max-width: 90%;
                width: 400px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);

            if (!document.getElementById('toastStyles')) {
                const style = document.createElement('style');
                style.id = 'toastStyles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        },

        show(message, type = 'info', duration = 5000) {
            this.init();

            const toast = document.createElement('div');
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            toast.style.cssText = `
                background: var(--card-bg, #ffffff);
                border-left: 4px solid ${colors[type] || colors.info};
                border-radius: 0.5rem;
                padding: 1rem 1.25rem;
                margin-bottom: 0.5rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                cursor: pointer;
                font-size: 0.9rem;
                color: var(--text-primary, #1a1a2e);
                pointer-events: auto;
            `;

            toast.innerHTML = `
                <i class="fas ${icons[type] || icons.info}" style="color: ${colors[type] || colors.info}; font-size: 1.2rem;"></i>
                <span>${message}</span>
            `;

            toast.addEventListener('click', function() {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(function() {
                    if (toast.parentNode) toast.remove();
                }, 300);
            });

            this.container.appendChild(toast);

            setTimeout(function() {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(function() {
                        if (toast.parentNode) toast.remove();
                    }, 300);
                }
            }, duration);
        },

        success(msg, duration) { this.show(msg, 'success', duration); },
        error(msg, duration) { this.show(msg, 'error', duration); },
        warning(msg, duration) { this.show(msg, 'warning', duration); },
        info(msg, duration) { this.show(msg, 'info', duration); }
    };

    // ============================================
    // COPY TO CLIPBOARD
    // ============================================
    window.copyToClipboard = function(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                Toast.success('Digital address copied to clipboard!');
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            Toast.success('Digital address copied to clipboard!');
        } catch (err) {
            Toast.error('Failed to copy to clipboard');
        }
        document.body.removeChild(textarea);
    }

    // ============================================
    // MARK AS ACTIVE MODAL
    // ============================================
    const markActiveModal = document.getElementById('markActiveModal');
    const closeMarkActiveModal = document.getElementById('closeMarkActiveModal');
    const cancelMarkActiveBtn = document.getElementById('cancelMarkActiveBtn');
    const markActiveForm = document.getElementById('markActiveForm');

    function openMarkActiveModal(button) {
        console.log('📂 Opening Mark Active modal for property:', {
            propertyId: button.dataset.propertyId,
            propertyName: button.dataset.propertyName
        });

        if (!markActiveModal) {
            console.error('❌ Mark Active modal not found!');
            Toast.error('Modal not found. Please refresh the page.');
            return;
        }

        const propertyId = button.dataset.propertyId;
        const propertyName = button.dataset.propertyName || 'Unknown Property';
        const completionDate = button.dataset.completionDate || 'Not set';

        // Set property details
        document.getElementById('markActivePropertyId').value = propertyId;
        document.getElementById('markActivePropertyName').textContent = propertyName;
        document.getElementById('markActiveCompletionDate').textContent = completionDate;

        // Reset form
        document.getElementById('mark_active_notes').value = '';
        document.getElementById('mark_active_declaration').checked = false;

        // Show modal
        markActiveModal.classList.add('active');
        markActiveModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeMarkActiveModalFunc() {
        if (markActiveModal) {
            markActiveModal.classList.remove('active');
            markActiveModal.style.display = 'none';
            document.body.style.overflow = '';
            console.log('❌ Mark Active modal closed');
        }
    }

    if (closeMarkActiveModal) {
        closeMarkActiveModal.addEventListener('click', closeMarkActiveModalFunc);
    }

    if (cancelMarkActiveBtn) {
        cancelMarkActiveBtn.addEventListener('click', closeMarkActiveModalFunc);
    }

    if (markActiveModal) {
        markActiveModal.addEventListener('click', function(e) {
            if (e.target === markActiveModal) {
                closeMarkActiveModalFunc();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (markActiveModal && markActiveModal.classList.contains('active')) {
                closeMarkActiveModalFunc();
            }
            if (constructionModal && constructionModal.classList.contains('active')) {
                closeConstructionModalFunc();
            }
        }
    });

    // ============================================
    // MARK AS ACTIVE FORM SUBMISSION
    // ============================================
    if (markActiveForm) {
        markActiveForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            console.log('📤 Mark Active form submission started');

            const declarationCheckbox = document.getElementById('mark_active_declaration');
            if (!declarationCheckbox || !declarationCheckbox.checked) {
                Toast.warning('You must confirm that the construction is complete.');
                return;
            }

            const submitBtn = document.getElementById('submitMarkActiveBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submit';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            }

            const formData = new FormData(this);

            try {
                const url = this.action;
                console.log('🌐 Submitting to:', url);

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                const rawText = await response.text();
                console.log('📄 Raw response:', rawText.substring(0, 500));

                let data;
                let isJson = false;

                if (contentType && contentType.includes('application/json')) {
                    try {
                        data = JSON.parse(rawText);
                        isJson = true;
                    } catch (e) {
                        const jsonMatch = rawText.match(/\{.*\}/s);
                        if (jsonMatch) {
                            try {
                                data = JSON.parse(jsonMatch[0]);
                                isJson = true;
                            } catch (e2) {}
                        }
                    }
                } else {
                    const jsonMatch = rawText.match(/\{.*\}/s);
                    if (jsonMatch) {
                        try {
                            data = JSON.parse(jsonMatch[0]);
                            isJson = true;
                        } catch (e) {}
                    }
                }

                if (isJson && data.success === true) {
                    Toast.success(data.message || 'Property marked as Active successfully!');
                    closeMarkActiveModalFunc();
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else if (isJson && data.success === false) {
                    Toast.error(data.message || 'Failed to mark property as active. Please try again.');
                } else {
                    Toast.error('Unexpected response from server. Please try again.');
                }

            } catch (error) {
                console.error('❌ Error:', error);
                Toast.error('Network error. Please check your connection and try again.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    // ============================================
    // ATTACH MARK ACTIVE BUTTON LISTENERS
    // ============================================
    function attachMarkActiveListeners() {
        const buttons = document.querySelectorAll('.mark-active-btn');
        console.log(`🔍 Found ${buttons.length} mark-active-btn elements`);

        buttons.forEach(function(btn) {
            btn.removeEventListener('click', btn._markActiveHandler);

            const handler = function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🖱️ Mark Active button clicked:', this.dataset.propertyId);
                openMarkActiveModal(this);
            };

            btn._markActiveHandler = handler;
            btn.addEventListener('click', handler);
        });
    }

    // ============================================
    // CONSTRUCTION MODAL FUNCTIONS
    // ============================================
    const constructionModal = document.getElementById('constructionModal');
    const closeConstructionModal = document.getElementById('closeConstructionModal');
    const cancelConstructionBtn = document.getElementById('cancelConstructionBtn');
    const constructionForm = document.getElementById('constructionUpdateForm');

    function openConstructionModal(button) {
        console.log('📂 Opening construction modal for property:', {
            propertyId: button.dataset.propertyId,
            propertyName: button.dataset.propertyName
        });

        if (!constructionModal) {
            console.error('❌ Modal element not found!');
            Toast.error('Modal not found. Please refresh the page.');
            return;
        }

        const propertyId = button.dataset.propertyId;
        const propertyName = button.dataset.propertyName || 'Unknown Property';
        const plotNumber = button.dataset.plotNumber || 'N/A';
        const hasConstruction = button.dataset.hasConstruction === 'true';
        const propertyType = button.dataset.propertyType;
        const customPropertyType = button.dataset.customPropertyType || '';
        const constructionStatus = button.dataset.constructionStatus || '';
        const bedrooms = button.dataset.bedrooms || '';
        const estimatedCompletion = button.dataset.estimatedCompletion || '';
        const hasPlans = button.dataset.hasPlans || '';

        const modalTitle = document.getElementById('modalTitle');
        if (modalTitle) {
            modalTitle.textContent = hasConstruction ? 'Update Construction Details' : 'Add Construction Details';
        }

        document.getElementById('constructionPropertyId').value = propertyId;
        document.getElementById('propertyInfoText').textContent = `Providing construction details for "${propertyName}" (Plot: ${plotNumber})`;

        // Reset form
        document.getElementById('construction_notes').value = '';
        document.getElementById('declaration_construction').checked = false;
        document.getElementById('construction_documents').value = '';
        document.getElementById('constructionDocPreview').style.display = 'none';
        document.getElementById('constructionDocPreview').innerHTML = '';
        clearErrors();

        // Set values
        const propertyTypeSelect = document.getElementById('property_type');
        const customPropertyTypeGroup = document.getElementById('customPropertyTypeGroup');
        const customPropertyTypeInput = document.getElementById('custom_property_type');
        const constructionStatusSelect = document.getElementById('construction_status');
        const estimatedBedroomsInput = document.getElementById('estimated_bedrooms');
        const estimatedCompletionInput = document.getElementById('estimated_completion');
        const hasPlansSelect = document.getElementById('has_plans');

        if (propertyTypeSelect) {
            if (propertyType && propertyType !== 'null' && propertyType !== '') {
                const options = propertyTypeSelect.options;
                let found = false;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value == propertyType) {
                        propertyTypeSelect.value = options[i].value;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    propertyTypeSelect.value = 'other';
                    customPropertyTypeGroup.style.display = 'block';
                    customPropertyTypeInput.value = customPropertyType || 'Residential';
                }
            } else {
                propertyTypeSelect.value = '';
                customPropertyTypeGroup.style.display = 'none';
                customPropertyTypeInput.value = '';
            }
        }

        if (constructionStatusSelect) {
            constructionStatusSelect.value = constructionStatus || 'under_construction';
        }

        if (estimatedBedroomsInput) {
            estimatedBedroomsInput.value = bedrooms || '';
        }

        if (estimatedCompletionInput) {
            estimatedCompletionInput.value = estimatedCompletion || '';
        }

        if (hasPlansSelect) {
            hasPlansSelect.value = hasPlans || '';
        }

        constructionModal.classList.add('active');
        constructionModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeConstructionModalFunc() {
        if (constructionModal) {
            constructionModal.classList.remove('active');
            constructionModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    if (closeConstructionModal) {
        closeConstructionModal.addEventListener('click', closeConstructionModalFunc);
    }

    if (cancelConstructionBtn) {
        cancelConstructionBtn.addEventListener('click', closeConstructionModalFunc);
    }

    if (constructionModal) {
        constructionModal.addEventListener('click', function(e) {
            if (e.target === constructionModal) {
                closeConstructionModalFunc();
            }
        });
    }

    // ============================================
    // ATTACH CONSTRUCTION BUTTON LISTENERS
    // ============================================
    function attachConstructionListeners() {
        const buttons = document.querySelectorAll('.construction-update-btn');
        console.log(`🔍 Found ${buttons.length} construction-update-btn elements`);

        buttons.forEach(function(btn) {
            btn.removeEventListener('click', btn._clickHandler);

            const clickHandler = function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🖱️ Construction button clicked:', this.dataset.propertyId);
                openConstructionModal(this);
            };

            btn._clickHandler = clickHandler;
            btn.addEventListener('click', clickHandler);
        });
    }

    // ============================================
    // PROPERTY TYPE CHANGE
    // ============================================
    const propertyTypeSelect = document.getElementById('property_type');
    const customPropertyTypeGroup = document.getElementById('customPropertyTypeGroup');
    const customPropertyTypeInput = document.getElementById('custom_property_type');

    if (propertyTypeSelect) {
        propertyTypeSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                customPropertyTypeGroup.style.display = 'block';
            } else {
                customPropertyTypeGroup.style.display = 'none';
                customPropertyTypeInput.value = '';
            }
        });
    }

    // ============================================
    // FILE UPLOAD
    // ============================================
    const constructionDocUploadArea = document.getElementById('constructionDocUploadArea');
    const constructionDocInput = document.getElementById('construction_documents');
    const constructionDocPreview = document.getElementById('constructionDocPreview');

    if (constructionDocUploadArea && constructionDocInput) {
        constructionDocUploadArea.addEventListener('click', function() {
            constructionDocInput.click();
        });

        constructionDocUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--primary)';
        });

        constructionDocUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border-color)';
        });

        constructionDocUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border-color)';
            const files = e.dataTransfer.files;
            constructionDocInput.files = files;
            updateFilePreview(constructionDocInput, constructionDocPreview);
        });

        constructionDocInput.addEventListener('change', function() {
            updateFilePreview(this, constructionDocPreview);
        });
    }

    function updateFilePreview(fileInput, previewElement) {
        if (!previewElement) return;
        const files = Array.from(fileInput.files);
        if (files.length === 0) {
            previewElement.style.display = 'none';
            previewElement.innerHTML = '';
            return;
        }

        previewElement.style.display = 'flex';
        previewElement.innerHTML = files.map(function(file) {
            const icon = file.type.startsWith('image/') ? 'fa-image' : 'fa-file-pdf';
            return `
                <div style="background: var(--bg-primary, #ffffff); border: 1px solid var(--border-color, #e9ecef); border-radius: 0.5rem; padding: 0.5rem 0.75rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; min-height: 40px;">
                    <i class="fas ${icon}"></i>
                    <span>${file.name.substring(0, 30)}${file.name.length > 30 ? '...' : ''}</span>
                    <button type="button" style="background: none; border: none; color: var(--danger, #ef4444); cursor: pointer; padding: 0.25rem; min-width: 30px; min-height: 30px;" onclick="this.parentElement.remove(); if(document.getElementById('construction_documents').files.length === 0) document.getElementById('constructionDocPreview').style.display = 'none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }).join('');
    }

    // ============================================
    // CLEAR ERRORS
    // ============================================
    function clearErrors() {
        document.querySelectorAll('#constructionUpdateForm .error-message').forEach(function(el) {
            el.textContent = '';
        });
        document.querySelectorAll('#constructionUpdateForm .form-control').forEach(function(el) {
            el.classList.remove('error');
        });
    }

    // ============================================
    // VALIDATE CONSTRUCTION FORM
    // ============================================
    function validateConstructionForm() {
        let isValid = true;
        clearErrors();

        const propertyTypeSelect = document.getElementById('property_type');
        const constructionStatusSelect = document.getElementById('construction_status');
        const estimatedBedroomsInput = document.getElementById('estimated_bedrooms');
        const declarationCheckbox = document.getElementById('declaration_construction');
        const customPropertyTypeInput = document.getElementById('custom_property_type');

        if (!propertyTypeSelect || !propertyTypeSelect.value) {
            const errorEl = document.getElementById('propertyTypeError');
            if (errorEl) errorEl.textContent = 'Please select a property type';
            if (propertyTypeSelect) propertyTypeSelect.classList.add('error');
            isValid = false;
        }

        if (propertyTypeSelect && propertyTypeSelect.value === 'other') {
            if (!customPropertyTypeInput || !customPropertyTypeInput.value.trim()) {
                const errorEl = document.getElementById('customPropertyTypeError');
                if (errorEl) errorEl.textContent = 'Please specify the property type';
                if (customPropertyTypeInput) customPropertyTypeInput.classList.add('error');
                isValid = false;
            }
        }

        if (!constructionStatusSelect || !constructionStatusSelect.value) {
            const errorEl = document.getElementById('constructionStatusError');
            if (errorEl) errorEl.textContent = 'Please select construction status';
            if (constructionStatusSelect) constructionStatusSelect.classList.add('error');
            isValid = false;
        }

        if (estimatedBedroomsInput && estimatedBedroomsInput.value) {
            const val = parseInt(estimatedBedroomsInput.value);
            if (isNaN(val) || val < 1 || val > 50) {
                const errorEl = document.getElementById('bedroomsError');
                if (errorEl) errorEl.textContent = 'Please enter a valid number of bedrooms (1-50)';
                estimatedBedroomsInput.classList.add('error');
                isValid = false;
            }
        }

        if (!declarationCheckbox || !declarationCheckbox.checked) {
            const errorEl = document.getElementById('declarationError');
            if (errorEl) errorEl.textContent = 'You must accept the declaration to proceed';
            isValid = false;
        }

        return isValid;
    }

    // ============================================
    // CONSTRUCTION FORM SUBMISSION
    // ============================================
    if (constructionForm) {
        constructionForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!validateConstructionForm()) {
                Toast.warning('Please fix the errors above before submitting.');
                return;
            }

            const submitBtn = document.getElementById('submitConstructionBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submit';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Saving...';
            }

            const formData = new FormData(this);

            try {
                const url = this.action;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                const rawText = await response.text();

                let data;
                let isJson = false;

                if (contentType && contentType.includes('application/json')) {
                    try {
                        data = JSON.parse(rawText);
                        isJson = true;
                    } catch (e) {
                        const jsonMatch = rawText.match(/\{.*\}/s);
                        if (jsonMatch) {
                            try {
                                data = JSON.parse(jsonMatch[0]);
                                isJson = true;
                            } catch (e2) {}
                        }
                    }
                } else {
                    const jsonMatch = rawText.match(/\{.*\}/s);
                    if (jsonMatch) {
                        try {
                            data = JSON.parse(jsonMatch[0]);
                            isJson = true;
                        } catch (e) {}
                    }
                }

                if (isJson && data.success === true) {
                    Toast.success(data.message || 'Construction details updated successfully!');
                    closeConstructionModalFunc();
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else if (isJson && data.success === false) {
                    if (data.errors) {
                        for (let key in data.errors) {
                            const errors = data.errors[key];
                            if (Array.isArray(errors)) {
                                Toast.error(errors[0]);
                            } else if (typeof errors === 'string') {
                                Toast.error(errors);
                            }
                            break;
                        }
                    } else {
                        Toast.error(data.message || 'Failed to update construction details. Please try again.');
                    }
                } else {
                    Toast.error('Unexpected response from server. Please try again.');
                }

            } catch (error) {
                console.error('❌ Error:', error);
                Toast.error('Network error. Please check your connection and try again.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    // ============================================
    // INITIALIZE
    // ============================================
    attachConstructionListeners();
    attachMarkActiveListeners();

    // Auto-hide messages
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(function() {
            successMessage.style.display = 'none';
        }, 5000);
    }

    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(function() {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    console.log('✅ Construction & Mark Active Script Initialized Successfully');

})();
</script>
@endsection