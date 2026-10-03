@extends('layouts.app')

@section('title', 'Properties Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                Properties Management
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('properties.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-plus mr-2"></i> Register New Property
                </a>
                <a href="{{ route('properties.trash.index') }}" 
                   class="flex items-center px-4 py-2 rounded-lg trash-link" 
                   style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); transition: all 0.2s;"
                   onmouseover="this.style.backgroundColor='rgba(var(--warning-rgb), 0.25)'"
                   onmouseout="this.style.backgroundColor='rgba(var(--warning-rgb), 0.15)'">
                    <i class="fas fa-trash mr-2"></i> Trash 
                    <span class="trash-count-badge" 
                          id="trash-count-badge"
                          data-previous-count="{{ \App\Models\Property::onlyTrashed()->count() }}">
                        {{ \App\Models\Property::onlyTrashed()->count() }}
                    </span>
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="alert-success flex items-center justify-between px-4 py-3 rounded relative" role="alert">
        <div>
            <i class="fas fa-check-circle mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" class="text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="alert-error flex items-center justify-between px-4 py-3 rounded relative" role="alert">
        <div>
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- ⭐ UPDATED STATISTICS CARDS                   -->
    <!-- ============================================ -->
    @php
        // Get all properties for statistics
        $allProperties = \App\Models\Property::all();
        
        // Calculate construction stats using the SAME logic as landlord blade
        $underConstructionCount = $allProperties->filter(function($property) {
            return $property->status === 'under_construction' ||
                   $property->construction_status === 'under_construction' ||
                   ($property->construction_status === 'active' && (
                       $property->has_plans === true ||
                       ($property->construction_documents && count($property->construction_documents) > 0) ||
                       ($property->property_type_id !== null && $property->property_type_id != 0)
                   ));
        })->count();
        
        // ✅ Vacant Land - ALL vacant land (with AND without plans)
        $vacantLandCount = $allProperties->filter(function($property) {
            $statusIsEmpty = empty($property->status) || $property->status === '';
            
            $hasPropertyType = $property->property_type_id !== null && 
                               $property->property_type_id !== '' && 
                               $property->property_type_id != 0;
            $hasConstructionStatus = $property->construction_status !== null && 
                                     $property->construction_status !== '' &&
                                     $property->construction_status !== 'vacant';
            $hasPlans = $property->has_plans === true || 
                        $property->has_plans === 1 ||
                        $property->has_plans === '1' ||
                        $property->has_plans === 'yes';
            $hasConstructionDocs = $property->construction_documents && 
                                   is_array($property->construction_documents) && 
                                   count($property->construction_documents) > 0;
            $hasConstructionDetails = $hasPropertyType || $hasConstructionStatus || $hasPlans || $hasConstructionDocs;
            
            return ($property->status === 'vacant' || $statusIsEmpty) && 
                   (!$hasConstructionDetails || 
                    ($hasConstructionDetails && $property->construction_status === 'vacant'));
        })->count();
        
        // ✅ Vacant Land WITH Plans (has construction details)
        $vacantLandWithPlansCount = $allProperties->filter(function($property) {
            $statusIsEmpty = empty($property->status) || $property->status === '';
            $isVacant = $property->status === 'vacant' || $statusIsEmpty;
            
            $hasPropertyType = $property->property_type_id !== null && 
                               $property->property_type_id !== '' && 
                               $property->property_type_id != 0;
            $hasConstructionStatus = $property->construction_status !== null && 
                                     $property->construction_status !== '' &&
                                     $property->construction_status !== 'vacant';
            $hasPlans = $property->has_plans === true || 
                        $property->has_plans === 1 ||
                        $property->has_plans === '1' ||
                        $property->has_plans === 'yes';
            $hasConstructionDocs = $property->construction_documents && 
                                   is_array($property->construction_documents) && 
                                   count($property->construction_documents) > 0;
            $hasConstructionDetails = $hasPropertyType || $hasConstructionStatus || $hasPlans || $hasConstructionDocs;
            
            return $isVacant && $hasConstructionDetails;
        })->count();
        
        // ✅ Vacant Land WITHOUT Plans
        $vacantLandNoPlansCount = $allProperties->filter(function($property) {
            $statusIsEmpty = empty($property->status) || $property->status === '';
            $isVacant = $property->status === 'vacant' || $statusIsEmpty;
            
            $hasPropertyType = $property->property_type_id !== null && 
                               $property->property_type_id !== '' && 
                               $property->property_type_id != 0;
            $hasConstructionStatus = $property->construction_status !== null && 
                                     $property->construction_status !== '' &&
                                     $property->construction_status !== 'vacant';
            $hasPlans = $property->has_plans === true || 
                        $property->has_plans === 1 ||
                        $property->has_plans === '1' ||
                        $property->has_plans === 'yes';
            $hasConstructionDocs = $property->construction_documents && 
                                   is_array($property->construction_documents) && 
                                   count($property->construction_documents) > 0;
            $hasConstructionDetails = $hasPropertyType || $hasConstructionStatus || $hasPlans || $hasConstructionDocs;
            
            return $isVacant && !$hasConstructionDetails;
        })->count();
        
        // ✅ Active properties (completed construction)
        $activeCount = $allProperties->filter(function($property) {
            return $property->status === 'active' && 
                   ($property->construction_status === 'active' || 
                    $property->construction_status === 'completed' ||
                    $property->construction_status === null);
        })->count();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <!-- 1. Total Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-3">
                    <i class="fas fa-building text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $allProperties->count() }}
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
                        {{ $activeCount }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- 3. Under Construction (using new logic) -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-3">
                    <i class="fas fa-hard-hat text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Under Construction</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $underConstructionCount }}
                    </p>
                    @php
                        $withPlansCount = $allProperties->filter(function($property) {
                            return $property->has_plans === true || 
                                   ($property->construction_documents && count($property->construction_documents) > 0);
                        })->count();
                    @endphp
                    @if($withPlansCount > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-file mr-1"></i> {{ $withPlansCount }} have plans
                    </p>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- 4. Vacant Land (ALL vacant land) -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-3">
                    <i class="fas fa-tree text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Vacant Land</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $vacantLandCount }}
                    </p>
                    @if($vacantLandWithPlansCount > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-file mr-1"></i> {{ $vacantLandWithPlansCount }} have plans
                    </p>
                    @endif
                    @if($vacantLandNoPlansCount > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> {{ $vacantLandNoPlansCount }} no plans
                    </p>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- 5. With Digital Address -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-cyan-100 text-cyan-600 mr-3">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">GPS Address</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ \App\Models\Property::hasDigitalAddress()->count() }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- 6. Unique Landlords -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-3">
                    <i class="fas fa-users text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-medium" style="color: var(--text-secondary);">Landlords</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @php
                            $uniqueLandlordsCount = \App\Models\User::where(function($query) {
                                $query->where('type', \App\Models\User::TYPE_LANDLORD)
                                      ->orWhereHas('roles', function($q) {
                                          $q->where('slug', 'landlord');
                                      });
                            })->count();
                        @endphp
                        {{ $uniqueLandlordsCount }}
                    </p>
                    @php
                        $legacyCount = \App\Models\User::where('type', \App\Models\User::TYPE_LANDLORD)
                            ->whereDoesntHave('roles', function($q) {
                                $q->where('slug', 'landlord');
                            })->count();
                        $roleBasedCount = \App\Models\User::whereHas('roles', function($q) {
                            $q->where('slug', 'landlord');
                        })->count();
                    @endphp
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ $roleBasedCount }} with role, {{ $legacyCount }} legacy
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- SIMPLIFIED FILTERS CARD -->
    <div class="card p-6">
        <form method="GET" action="{{ route('properties.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <input type="text" name="street_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search street name..." value="{{ request('street_name') }}">
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="under_maintenance" {{ request('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                        <option value="vacant" {{ request('status') == 'vacant' ? 'selected' : '' }}>Vacant Land</option>
                        <option value="under_construction" {{ request('status') == 'under_construction' ? 'selected' : '' }}>Under Construction</option>
                    </select>
                </div>
                <div>
                    <select name="has_digital_address" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Properties</option>
                        <option value="1" {{ request('has_digital_address') == '1' ? 'selected' : '' }}>Has Digital Address</option>
                        <option value="0" {{ request('has_digital_address') == '0' ? 'selected' : '' }}>No Digital Address</option>
                    </select>
                </div>
                <div>
                    <select name="landlord_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Landlords</option>
                        @foreach($landlords ?? [] as $landlord)
                            <option value="{{ $landlord->id }}" {{ request('landlord_id') == $landlord->id ? 'selected' : '' }}>
                                {{ $landlord->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <!-- Filter Buttons Row -->
            <div class="mt-4 flex space-x-2">
                <button type="submit" class="px-4 py-2 rounded flex items-center justify-center" style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="{{ route('properties.index') }}" class="px-4 py-2 rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-sync mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Properties Table Card -->
    <div class="card p-6">
        <!-- Results Count -->
        <div class="mb-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $properties->firstItem() ?? 0 }} to {{ $properties->lastItem() ?? 0 }} of {{ $properties->total() }} results
            </p>
        </div>

        @if(request()->hasAny(['street_name', 'status', 'has_digital_address', 'landlord_id']))
        <div class="mb-4 flex items-center flex-wrap gap-2">
            <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
            @if(request('street_name'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Street: {{ request('street_name') }}
            </span>
            @endif
            @if(request('status'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
            </span>
            @endif
            @if(request('has_digital_address') !== null && request('has_digital_address') !== '')
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Digital Address: {{ request('has_digital_address') ? 'Has Address' : 'No Address' }}
            </span>
            @endif
            @if(request('landlord_id'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Landlord ID: {{ request('landlord_id') }}
            </span>
            @endif
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Info</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Location</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Construction Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Registration Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Landlord</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($properties as $property)
                    @php
                        // ============================================
                        // ⭐ SAME ROBUST STATUS DETECTION AS LANDLORD BLADE
                        // ============================================
                        
                        $statusIsEmpty = empty($property->status) || $property->status === '';
                        
                        $hasPropertyType = $property->property_type_id !== null && 
                                           $property->property_type_id !== '' && 
                                           $property->property_type_id != 0;
                        
                        $hasConstructionStatus = $property->construction_status !== null && 
                                                 $property->construction_status !== '' &&
                                                 $property->construction_status !== 'vacant';
                        
                        $hasPlans = $property->has_plans === true || 
                                    $property->has_plans === 1 ||
                                    $property->has_plans === '1' ||
                                    $property->has_plans === 'yes';
                        
                        $hasConstructionDocs = $property->construction_documents && 
                                               is_array($property->construction_documents) && 
                                               count($property->construction_documents) > 0;
                        
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
                        
                        // ✅ ACTIVE DETECTION (completed construction)
                        $isActive = $property->status === 'active' && 
                                   ($property->construction_status === 'active' || 
                                    $property->construction_status === 'completed' ||
                                    $property->construction_status === null);
                        
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
                        
                        // Build status label with extra info
                        $statusLabelExtra = '';
                        if ($isVacant) {
                            if ($hasConstructionDetails) {
                                $statusLabelExtra = ' (Has Plans)';
                            } else {
                                $statusLabelExtra = ' (No Plans)';
                            }
                        }
                        
                        // Get status color config
                        $statusColors = [
                            'vacant' => ['bg' => 'info', 'icon' => 'fa-tree'],
                            'under_construction' => ['bg' => 'warning', 'icon' => 'fa-hard-hat'],
                            'active' => ['bg' => 'success', 'icon' => 'fa-check-circle'],
                            'inactive' => ['bg' => 'secondary', 'icon' => 'fa-pause-circle'],
                            'under_maintenance' => ['bg' => 'warning', 'icon' => 'fa-tools'],
                            'unknown' => ['bg' => 'secondary', 'icon' => 'fa-question-circle']
                        ];
                        $statusConfig = $statusColors[$displayStatus] ?? $statusColors['unknown'];
                    @endphp
                    <tr class="border-b transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</p>
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
                                        <i class="fas fa-road mr-1"></i>{{ $property->street_name }}
                                    </p>
                                    @if($property->registered_by)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1 bg-green-100 text-green-800">
                                        <i class="fas fa-user-check mr-1"></i> 
                                        Registered by: {{ $property->registeredBy->name ?? 'Field Agent' }}
                                    </span>
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
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> No Digital Address
                                </span>
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
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Plan: {{ $property->registrationPlan->naming_pattern ?? 'N/A' }}
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
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-purple-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $property->landlord->name ?? 'N/A' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-phone mr-1"></i>{{ $property->landlord->phone ?? 'N/A' }}
                                    </p>
                                    @if($property->landlord && $property->landlord->roles->count() > 1)
                                        <span class="inline-flex items-center mt-1 text-xs text-purple-600">
                                            <i class="fas fa-tags mr-1"></i>
                                            {{ $property->landlord->roles->pluck('name')->implode(', ') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            {{-- ⭐ FIXED STATUS COLUMN --}}
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
                                @if($property->estimated_completion)
                                    @php
                                        $now = \Carbon\Carbon::now();
                                        $completion = \Carbon\Carbon::parse($property->estimated_completion);
                                        $totalDays = $now->diffInDays($completion);
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
                            @endif
                            
                            @if($property->is_rented)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" 
                                      style="background-color: rgba(var(--success-rgb), 0.15); 
                                             color: var(--success); 
                                             border: 1px solid rgba(var(--success-rgb), 0.2);">
                                    <i class="fas fa-users mr-1"></i> Rented
                                </span>
                            @endif
                            
                            @if($property->tenants_count > 0)
                                <p class="text-xs mt-1" style="color: var(--info);">
                                    <i class="fas fa-users mr-1"></i>
                                    {{ $property->tenants_count }} tenant(s)
                                    @if($property->active_tenant_count)
                                        ({{ $property->active_tenant_count }} active)
                                    @endif
                                </p>
                            @endif
                            
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ \Carbon\Carbon::parse($property->registration_date)->format('M d, Y') }}
                            </p>
                            @if($property->last_inspection_date)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-search mr-1"></i>
                                Inspected: {{ \Carbon\Carbon::parse($property->last_inspection_date)->format('M d, Y') }}
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Details Button -->
                                <a href="{{ route('properties.show', $property->id) }}" class="p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button -->
                                <a href="{{ route('properties.edit', $property->id) }}" class="p-2 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Edit Property">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Delete Button -->
                                <form action="{{ route('properties.destroy', $property->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this property? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Delete Property">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="mt-2 flex flex-wrap gap-1">
                                @if($property->digital_address)
                                    <button type="button" class="p-1 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Copy Digital Address" onclick="copyToClipboard('{{ $property->digital_address }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                @endif
                                @if($property->landlord && $property->landlord->phone)
                                    <a href="tel:{{ $property->landlord->phone }}" class="p-1 text-xs rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Call Landlord">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                @endif
                                @if($isVacant || $isUnderConstruction)
                                    <a href="{{ route('properties.edit', $property->id) }}#construction" class="p-1 text-xs rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Update Construction Status">
                                        <i class="fas fa-hard-hat"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-building text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No properties found</p>
                                <p class="text-sm mb-4">Try adjusting your filters or add a new property.</p>
                                <a href="{{ route('properties.create') }}" class="btn-primary flex items-center">
                                    <i class="fas fa-plus mr-2"></i> Add Your First Property
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($properties->hasPages())
        <div class="flex justify-between items-center mt-6">
            <p class="text-sm" style="color: var(--text-secondary);">
                Page {{ $properties->currentPage() }} of {{ $properties->lastPage() }}
            </p>
            <div class="flex space-x-2">
                {{ $properties->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // ALERT MESSAGES AUTO-HIDE
    // =============================================
    const successMessage = document.querySelector('.alert-success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.alert-error');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    // =============================================
    // COPY TO CLIPBOARD FUNCTION
    // =============================================
    window.copyToClipboard = function(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const btn = event?.target?.closest?.('button') || document.activeElement;
                if (btn) {
                    const originalTitle = btn.title;
                    btn.title = 'Copied!';
                    btn.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
                    btn.style.color = 'var(--success)';
                    setTimeout(() => {
                        btn.title = originalTitle;
                        btn.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                        btn.style.color = 'var(--success)';
                    }, 2000);
                }
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy to clipboard');
            });
        } else {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('Copied to clipboard!');
            } catch (err) {
                alert('Failed to copy to clipboard');
            }
            document.body.removeChild(textarea);
        }
    };

    // =============================================
    // TRASH COUNT LIVE UPDATE FUNCTIONALITY
    // =============================================
    
    function updateTrashCount() {
        const trashBadge = document.getElementById('trash-count-badge');
        if (!trashBadge) return;
        
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Use named route for better maintainability
        const url = '{{ route("properties.trash.count") }}';
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const count = data.count;
                const prevCount = parseInt(trashBadge.getAttribute('data-previous-count') || 0);
                
                // Update badge text
                trashBadge.textContent = count;
                trashBadge.setAttribute('data-previous-count', count);
                
                // Show/hide badge
                if (count > 0) {
                    trashBadge.style.display = 'inline-flex';
                    trashBadge.style.opacity = '1';
                    
                    // Add pulse animation if new items added
                    if (prevCount < count) {
                        trashBadge.classList.add('animate-pulse');
                        setTimeout(() => {
                            trashBadge.classList.remove('animate-pulse');
                        }, 1000);
                    }
                } else {
                    trashBadge.style.display = 'none';
                }
                
                // Update the trash link appearance based on count
                const trashLink = document.querySelector('.trash-link');
                if (trashLink) {
                    if (count > 0) {
                        trashLink.style.borderColor = 'var(--warning)';
                        trashLink.style.color = 'var(--warning)';
                    } else {
                        trashLink.style.borderColor = 'var(--border-color)';
                        trashLink.style.color = 'var(--text-secondary)';
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error updating trash count:', error);
            // Don't show error to user, just log it
        });
    }
    
    // =============================================
    // INITIALIZATION
    // =============================================
    
    // Update immediately on page load
    updateTrashCount();
    
    // Update every 30 seconds
    let updateInterval = setInterval(updateTrashCount, 30000);
    
    // Update when page becomes visible again (e.g., after switching tabs)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateTrashCount();
        }
    });
    
    // Update when user comes back online
    window.addEventListener('online', function() {
        updateTrashCount();
    });
    
    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
});
</script>

<style>
/* ============================================
   ALERT MESSAGES
   ============================================ */
.alert-success {
    background-color: rgba(209, 250, 229, 0.9);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #065f46;
}

.alert-error {
    background-color: rgba(254, 226, 226, 0.9);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #991b1b;
}

/* ============================================
   TRASH BADGE
   ============================================ */
.trash-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 8px;
    font-size: 11px;
    font-weight: 700;
    background-color: var(--danger, #dc2626);
    color: white;
    border-radius: 9999px;
    transition: all 0.3s ease;
    line-height: 1;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.trash-count-badge:empty {
    display: none;
}

.trash-count-badge.badge-hidden {
    display: none !important;
}

/* Pulse animation for new items */
@keyframes trashPulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-pulse {
    animation: trashPulse 0.5s ease-in-out;
}

/* ============================================
   TRASH LINK
   ============================================ */
.trash-link {
    transition: all 0.2s ease;
    text-decoration: none !important;
}

.trash-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);
}

.trash-link:hover .trash-count-badge {
    transform: scale(1.1);
}

/* When trash is empty */
.trash-empty .trash-count-badge {
    background-color: var(--text-secondary);
}

/* ============================================
   BUTTON STYLES
   ============================================ */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    color: white;
    text-decoration: none;
}

/* ============================================
   CARD STYLING
   ============================================ */
.card {
    background-color: var(--bg-secondary);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.card:hover {
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
}

/* ============================================
   TABLE STYLES
   ============================================ */
table {
    border-collapse: collapse;
}

thead th {
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0.75rem;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
}

/* ============================================
   RESPONSIVE ADJUSTMENTS
   ============================================ */
@media (max-width: 768px) {
    .flex-space-x-2 {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .card {
        padding: 1rem;
    }
    
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   SCROLLBAR STYLING
   ============================================ */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-primary);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
@endsection