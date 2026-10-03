{{-- resources/views/property_units/bulk-approval.blade.php --}}
@php
    // Dynamic title
    $pageTitle = 'Bulk Tenant Approval';
    
    // Get any success/error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Determine user role
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    // Can view financial info (ONLY landlords)
    $canViewFinancialInfo = $isLandlord;
    
    // Determine selected IDs
    $selectedIds = request('selected_ids', []);
    if (!is_array($selectedIds)) {
        $selectedIds = explode(',', $selectedIds);
    }
    $selectedIds = array_filter($selectedIds);
    
    // Collect unit data for JavaScript (filter rent amounts for non-landlords)
    $unitData = [];
    foreach ($pendingUnits as $unit) {
        $data = [
            'id' => $unit->id,
            'property_name' => $unit->property->property_name ?? 'N/A',
            'unit_number' => $unit->unit_number,
            'tenant_type' => $unit->tenant_type,
            'move_in_date' => $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : null,
            'requested_date' => $unit->tenant_requested_at->format('M d, Y'),
            'tenant_name' => $unit->requestedBy->name ?? 'N/A',
            'is_landlord' => $isLandlord
        ];
        
        // Only include rent information for landlords
        if ($canViewFinancialInfo) {
            $data['proposed_rent'] = $unit->proposed_rent ?? $unit->monthly_rent ?? 0;
            $data['base_rent'] = $unit->monthly_rent ?? 0;
        } else {
            // For admins, set rent to 0 (will be hidden in UI)
            $data['proposed_rent'] = 0;
            $data['base_rent'] = 0;
        }
        
        $unitData[$unit->id] = $data;
    }
@endphp

@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-users-cog text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users-cog mr-2" style="color: var(--primary);"></i> 
                        Bulk Tenant Approval
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>Approve multiple tenant applications at once</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-layer-group mr-1"></i>
                        <span>{{ $pendingUnits->total() }} pending applications</span>
                        <span class="mx-2">•</span>
                        @if($isAdmin || $isSuperAdmin)
                        <i class="fas fa-user-shield mr-1"></i>
                        <span>Admin View - Tenants Only (No Lease Creation)</span>
                        @elseif($isLandlord)
                        <i class="fas fa-user-tie mr-1"></i>
                        <span>Landlord View</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('property-units.pending-approvals') }}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Individual Approvals
                </a>
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

    <!-- Role Information Banner -->
    @if($isAdmin || $isSuperAdmin)
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            <div>
                <p class="font-bold">Administrator Notice</p>
                <p class="text-sm mt-1">
                    You are approving tenant assignments only. Move-in dates and rent amounts are set by landlords.
                    @if($selectedIds)
                        <a href="{{ route('property-units.assignment-details', $selectedIds[0]) }}" class="ml-1 font-medium">
                            View individual assignment details →
                        </a>
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Filter Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-filter mr-2" style="color: var(--info);"></i> 
                            Filter Units
                        </h3>
                        <span class="text-sm px-3 py-1 rounded-full" 
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-sliders-h mr-1"></i> Quick Filter
                        </span>
                    </div>
                    
                    <form id="filterForm" method="GET" action="{{ route('property-units.bulk-approval') }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Property Filter -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-building mr-1"></i> Property
                                </label>
                                <select name="property_id" class="form-select w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" onchange="loadFilteredUnits()">
                                    <option value="">All Properties</option>
                                    @foreach($properties as $property)
                                        <option value="{{ $property->id }}" 
                                            {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                            {{ $property->property_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- Tenant Type Filter -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-user-tag mr-1"></i> Tenant Type
                                </label>
                                <select name="tenant_type" class="form-select w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" onchange="loadFilteredUnits()">
                                    <option value="">All Types</option>
                                    <option value="new" {{ request('tenant_type') == 'new' ? 'selected' : '' }}>
                                        New Tenants
                                    </option>
                                    <option value="existing" {{ request('tenant_type') == 'existing' ? 'selected' : '' }}>
                                        Existing Tenants
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Date Range -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-1"></i> From Date
                                </label>
                                <input type="date" name="date_from" class="form-input w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" 
                                       value="{{ request('date_from') }}" onchange="loadFilteredUnits()">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-1"></i> To Date
                                </label>
                                <input type="date" name="date_to" class="form-input w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" 
                                       value="{{ request('date_to') }}" onchange="loadFilteredUnits()">
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                            <button type="button" 
                                    onclick="clearFilters()" 
                                    class="px-4 py-2 rounded-lg text-sm font-medium mr-3" 
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                <i class="fas fa-redo mr-2"></i> Clear Filters
                            </button>
                            <button type="button" 
                                    onclick="loadFilteredUnits()" 
                                    class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                                    style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                                <i class="fas fa-search mr-2"></i> Filter Units
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Unit Selection Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold flex items-center mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-list-check mr-2" style="color: var(--success);"></i> 
                                Select Units for Approval
                            </h3>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                <span id="selectedCount">0</span> units selected of 
                                <span id="totalCount">{{ $pendingUnits->total() }}</span> available
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button type="button" 
                                    onclick="selectAllUnits()" 
                                    class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-check-double mr-1"></i> Select All
                            </button>
                            <button type="button" 
                                    onclick="deselectAllUnits()" 
                                    class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times-circle mr-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" class="hidden">
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-spinner fa-spin text-xl" style="color: var(--info);"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Loading units...
                            </p>
                        </div>
                    </div>
                    
                    <!-- Units List -->
                    <div id="unitsList" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        @foreach($pendingUnits as $unit)
                            <div class="unit-item border rounded-lg p-4 transition-all duration-200 hover:shadow-md" 
                                 data-unit-id="{{ $unit->id }}"
                                 style="border-color: var(--border-color); background-color: var(--card-bg);">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="selected_units[]" 
                                           value="{{ $unit->id }}"
                                           class="unit-checkbox form-checkbox h-5 w-5"
                                           {{ in_array($unit->id, $selectedIds) ? 'checked' : '' }}
                                           onchange="updateSelection()"
                                           style="color: var(--primary);">
                                    <div class="ml-3 flex-grow">
                                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                                            <div class="mb-2 md:mb-0">
                                                <h4 class="font-semibold text-base" style="color: var(--text-primary);">
                                                    {{ $unit->property->property_name ?? 'N/A' }} - Unit {{ $unit->unit_number ?? 'N/A' }}
                                                </h4>
                                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                                    <!-- Tenant Type Badge -->
                                                    @php
                                                        $typeColor = $unit->tenant_type === 'new' ? 'info' : 'success';
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $typeColor }}">
                                                        <i class="fas fa-user mr-1"></i>
                                                        {{ ucfirst($unit->tenant_type) }} Tenant
                                                    </span>
                                                    
                                                    <!-- Rent Info - Only visible to landlords -->
                                                    @if($canViewFinancialInfo)
                                                    <span class="text-sm" style="color: var(--text-secondary);">
                                                        <i class="fas fa-money-bill-wave mr-1"></i>
                                                        GHS {{ number_format($unit->proposed_rent ?? $unit->monthly_rent, 2) }}
                                                    </span>
                                                    @endif
                                                    
                                                    <!-- Move-in Date -->
                                                    @if($unit->tenant_move_in_date)
                                                    <span class="text-sm" style="color: var(--text-secondary);">
                                                        <i class="fas fa-calendar-day mr-1"></i>
                                                        Move-in: {{ $unit->tenant_move_in_date->format('M d, Y') }}
                                                    </span>
                                                    @endif
                                                    
                                                    <!-- Request Date -->
                                                    <span class="text-sm" style="color: var(--text-secondary);">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        {{ $unit->tenant_requested_at->format('M d, Y') }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Requested By -->
                                            <div class="text-right">
                                                <p class="text-xs" style="color: var(--text-secondary);">Requested by</p>
                                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                    {{ $unit->requestedBy->name ?? 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    @if($pendingUnits->isEmpty())
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-inbox text-xl" style="color: var(--secondary);"></i>
                            </div>
                            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Pending Units</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                No units require approval with the current filters.
                            </p>
                        </div>
                    @elseif($pendingUnits->hasPages())
                        <div class="pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                            {{ $pendingUnits->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Summary Card -->
            <div class="card" id="selectionSummaryCard">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> 
                        Selection Summary
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Total Units -->
                        <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Units Selected</p>
                                    <p class="text-2xl font-bold" style="color: var(--primary);" id="summaryTotal">0</p>
                                </div>
                                <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-building text-lg" style="color: var(--primary);"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- New Tenants -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <i class="fas fa-user-plus" style="color: var(--info);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">New Tenants</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--info);" id="summaryNew">0</p>
                            </div>
                            
                            <!-- Existing Tenants -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--success-rgb), 0.1);">
                                        <i class="fas fa-user-check" style="color: var(--success);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Existing Tenants</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--success);" id="summaryExisting">0</p>
                            </div>
                            
                            <!-- Total Rent - Only visible to landlords -->
                            @if($canViewFinancialInfo)
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                                        <i class="fas fa-money-bill-wave" style="color: var(--warning);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Total Rent</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--warning);" id="summaryRent">GHS 0.00</p>
                            </div>
                            
                            <!-- Avg Rent - Only visible to landlords -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-calculator" style="color: var(--secondary);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Avg. Rent</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--secondary);" id="summaryAvgRent">GHS 0.00</p>
                            </div>
                            @else
                            <!-- Placeholder for admins - showing restricted message -->
                            <div class="col-span-2 p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <i class="fas fa-lock" style="color: var(--info);"></i>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Financial Information</span>
                                        <p class="text-sm mt-1" style="color: var(--text-primary);">
                                            Rent details are only visible to landlords
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Approve Button -->
                    <div class="pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <button type="button" 
                                onclick="openBulkApprovalModal()" 
                                id="approveButton"
                                class="w-full px-4 py-3 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200 opacity-50 cursor-not-allowed"
                                disabled
                                style="background-color: var(--success); border: 2px solid var(--success);">
                            <i class="fas fa-user-check mr-2"></i>
                            Approve Tenants Only
                        </button>
                        <p class="text-xs text-center mt-2" style="color: var(--text-secondary);">
                            Select at least 1 unit to enable bulk approval
                            <br><span class="text-blue-600">(Lease creation will be done by landlords separately)</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="card" id="statsCard">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--info);"></i> 
                        Pending Applications Overview
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Total Pending -->
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Total Pending</p>
                                <p class="text-lg font-bold" style="color: var(--warning);">{{ $stats['total_pending'] ?? 0 }}</p>
                            </div>
                        </div>
                        
                        <!-- New Tenants -->
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">New Tenants</p>
                                <p class="text-lg font-bold" style="color: var(--info);">{{ $stats['new_tenants'] ?? 0 }}</p>
                            </div>
                        </div>
                        
                        <!-- Existing Tenants -->
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Existing Tenants</p>
                                <p class="text-lg font-bold" style="color: var(--success);">{{ $stats['existing_tenants'] ?? 0 }}</p>
                            </div>
                        </div>
                        
                        <!-- Total Proposed Rent - Only visible to landlords -->
                        @if($canViewFinancialInfo)
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Total Proposed Rent</p>
                                <p class="text-lg font-bold" style="color: var(--primary);">
                                    GHS {{ number_format($stats['total_proposed_rent'] ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                        @else
                        <!-- Placeholder for admins - shows restricted message -->
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Total Proposed Rent</p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-lock mr-1"></i> Restricted to landlords
                                </p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Approval Modal -->
<div id="bulkApprovalModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeBulkApprovalModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-users-cog mr-2" style="color: var(--success);"></i> 
                    Configure Bulk Approval Settings
                </h3>
                <button type="button" 
                        onclick="closeBulkApprovalModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div id="bulkApprovalFormContainer" class="theme-modal-body-compact" style="max-height: 500px; overflow-y: auto;">
                <!-- Form will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div id="resultsModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeResultsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact" style="max-width: 800px;">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-check mr-2" style="color: var(--info);"></i> 
                    Bulk Approval Results
                </h3>
                <button type="button" 
                        onclick="closeResultsModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div id="resultsContent" class="theme-modal-body-compact" style="max-height: 500px; overflow-y: auto;">
                <!-- Results will be displayed here -->
            </div>
            
            <!-- Modal Footer -->
            <div class="theme-modal-footer-compact">
                <button onclick="closeResultsModal()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium" 
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-check mr-2"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize units data from PHP
    window.unitsData = @json($unitData);
    window.selectedUnits = new Set(@json($selectedIds));
    window.canViewFinancialInfo = @json($canViewFinancialInfo);
    
    // Auto-hide messages after 5 seconds
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
    
    updateSelection();
    updateSummary();
    
    // Make the Selection Summary card sticky with proper z-index
    const summaryCard = document.getElementById('selectionSummaryCard');
    const statsCard = document.getElementById('statsCard');
    
    if (summaryCard && statsCard) {
        // Calculate initial positions
        const calculatePositions = () => {
            const summaryRect = summaryCard.getBoundingClientRect();
            const statsRect = statsCard.getBoundingClientRect();
            const scrollY = window.scrollY;
            const windowHeight = window.innerHeight;
            
            // Make summary card sticky only when there's room
            if (windowHeight > summaryRect.height + statsRect.height + 100) {
                summaryCard.classList.add('sticky');
                summaryCard.style.top = '1.5rem';
                summaryCard.style.zIndex = '10';
            } else {
                summaryCard.classList.remove('sticky');
                summaryCard.style.top = '';
                summaryCard.style.zIndex = '';
            }
        };
        
        // Initial calculation
        calculatePositions();
        
        // Recalculate on scroll and resize
        window.addEventListener('scroll', calculatePositions);
        window.addEventListener('resize', calculatePositions);
    }
});

// Load filtered units via AJAX - FIXED VERSION
function loadFilteredUnits() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    showLoading(true);
    
    fetch(`{{ route('property-units.bulk-selection') }}?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateUnitsList(data.units);
            // Update unitsData with new data
            window.unitsData = {};
            data.units.forEach(unit => {
                window.unitsData[unit.id] = unit;
            });
            
            // Clear selection after successful reload
            selectedUnits.clear();
            updateSelection();
            updateSummary();
            
            // Show success message if no units left
            if (data.units.length === 0) {
                const container = document.getElementById('unitsList');
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-circle text-2xl" style="color: var(--success);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">All Done!</h4>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            No pending units found. All selected applications have been approved.
                        </p>
                        <a href="{{ route('property-units.index') }}" 
                           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium"
                           style="background-color: var(--primary); color: white;">
                            <i class="fas fa-building mr-2"></i> View Property Units
                        </a>
                    </div>
                `;
                document.getElementById('totalCount').textContent = '0';
            }
        } else {
            console.error('Failed to load units:', data);
            showError('Failed to load units: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error loading units:', error);
        // Don't show error if it's just that no units are available
        if (error.message && (error.message.includes('404') || error.message.includes('500'))) {
            // Handle 404/500 gracefully - show empty state
            const container = document.getElementById('unitsList');
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-info-circle text-2xl" style="color: var(--info);"></i>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        No pending units found
                    </p>
                    <a href="{{ route('property-units.index') }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium mt-4"
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-building mr-2"></i> View Property Units
                    </a>
                </div>
            `;
            document.getElementById('totalCount').textContent = '0';
        } else {
            showError('Error loading units. Please refresh the page.');
        }
    })
    .finally(() => {
        showLoading(false);
    });
}

// Update units list with fetched data
function updateUnitsList(units) {
    const container = document.getElementById('unitsList');
    const totalCount = document.getElementById('totalCount');
    
    if (units.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--secondary-rgb), 0.1);">
                    <i class="fas fa-inbox text-xl" style="color: var(--secondary);"></i>
                </div>
                <p class="text-sm" style="color: var(--text-secondary);">
                    No pending units found with current filters
                </p>
            </div>
        `;
        totalCount.textContent = '0';
        return;
    }
    
    let html = '';
    units.forEach(unit => {
        const isSelected = selectedUnits.has(unit.id);
        const typeColor = unit.tenant_type === 'new' ? 'info' : 'success';
        
        // Build HTML with conditional rent display
        let rentHtml = '';
        if (window.canViewFinancialInfo) {
            rentHtml = `
                <span class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-money-bill-wave mr-1"></i>
                    GHS ${parseFloat(unit.proposed_rent).toFixed(2)}
                </span>
            `;
        }
        
        html += `
            <div class="unit-item border rounded-lg p-4 transition-all duration-200 hover:shadow-md ${isSelected ? 'selected' : ''}" 
                 data-unit-id="${unit.id}"
                 style="border-color: var(--border-color); background-color: var(--card-bg); ${isSelected ? 'border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.05);' : ''}">
                <div class="flex items-center">
                    <input type="checkbox" 
                           name="selected_units[]" 
                           value="${unit.id}"
                           class="unit-checkbox form-checkbox h-5 w-5"
                           ${isSelected ? 'checked' : ''}
                           onchange="updateSelection()"
                           style="color: var(--primary);">
                    <div class="ml-3 flex-grow">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-2 md:mb-0">
                                <h4 class="font-semibold text-base" style="color: var(--text-primary);">
                                    ${unit.property_name} - Unit ${unit.unit_number}
                                </h4>
                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-${typeColor}">
                                        <i class="fas fa-user mr-1"></i>
                                        ${unit.tenant_type.charAt(0).toUpperCase() + unit.tenant_type.slice(1)} Tenant
                                    </span>
                                    ${rentHtml}
                                    ${unit.move_in_date ? `
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        Move-in: ${new Date(unit.move_in_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs" style="color: var(--text-secondary);">Requested by</p>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    ${unit.tenant_name}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    totalCount.textContent = units.length;
    updateSelectedCount();
}

// Update selection state
function updateSelection() {
    const checkboxes = document.querySelectorAll('.unit-checkbox');
    const approveButton = document.getElementById('approveButton');
    
    selectedUnits.clear();
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedUnits.add(parseInt(checkbox.value));
        }
    });
    
    // Update selected count
    updateSelectedCount();
    
    // Update button state
    if (selectedUnits.size > 0) {
        approveButton.disabled = false;
        approveButton.classList.remove('opacity-50', 'cursor-not-allowed');
        approveButton.classList.add('opacity-100', 'cursor-pointer');
        approveButton.style.backgroundColor = 'var(--success)';
        approveButton.style.borderColor = 'var(--success)';
    } else {
        approveButton.disabled = true;
        approveButton.classList.add('opacity-50', 'cursor-not-allowed');
        approveButton.classList.remove('opacity-100', 'cursor-pointer');
        approveButton.style.backgroundColor = 'var(--success)';
        approveButton.style.borderColor = 'var(--success)';
    }
    
    // Update item styling
    document.querySelectorAll('.unit-item').forEach(item => {
        const unitId = parseInt(item.dataset.unitId);
        if (selectedUnits.has(unitId)) {
            item.style.borderColor = 'var(--primary)';
            item.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            item.classList.add('selected');
        } else {
            item.style.borderColor = 'var(--border-color)';
            item.style.backgroundColor = 'var(--card-bg)';
            item.classList.remove('selected');
        }
    });
    
    updateSummary();
}

// Update selected count display
function updateSelectedCount() {
    document.getElementById('selectedCount').textContent = selectedUnits.size;
}

// Update summary panel
function updateSummary() {
    let newCount = 0;
    let existingCount = 0;
    let totalRent = 0;
    let earliestMoveInDate = null;
    
    selectedUnits.forEach(unitId => {
        const unit = unitsData[unitId];
        if (unit) {
            if (unit.tenant_type === 'new') {
                newCount++;
            } else {
                existingCount++;
            }
            // Only calculate rent if user can view financial info
            if (window.canViewFinancialInfo) {
                totalRent += parseFloat(unit.proposed_rent) || 0;
            }
            
            // Get earliest move-in date
            if (unit.move_in_date) {
                const moveInDate = new Date(unit.move_in_date);
                if (!earliestMoveInDate || moveInDate < earliestMoveInDate) {
                    earliestMoveInDate = moveInDate;
                }
            }
        }
    });
    
    const avgRent = selectedUnits.size > 0 && window.canViewFinancialInfo ? totalRent / selectedUnits.size : 0;
    
    document.getElementById('summaryTotal').textContent = selectedUnits.size;
    document.getElementById('summaryNew').textContent = newCount;
    document.getElementById('summaryExisting').textContent = existingCount;
    
    // Only update rent displays if user can view financial info
    if (window.canViewFinancialInfo) {
        document.getElementById('summaryRent').textContent = `GHS ${totalRent.toFixed(2)}`;
        document.getElementById('summaryAvgRent').textContent = `GHS ${avgRent.toFixed(2)}`;
    }
    
    // Store earliest move-in date for use in the modal
    window.earliestMoveInDate = earliestMoveInDate;
}

// Select all units
function selectAllUnits() {
    document.querySelectorAll('.unit-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelection();
}

// Deselect all units
function deselectAllUnits() {
    document.querySelectorAll('.unit-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelection();
}

// Clear filters
function clearFilters() {
    document.getElementById('filterForm').reset();
    loadFilteredUnits();
}

// Show/hide loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loadingIndicator');
    const list = document.getElementById('unitsList');
    if (show) {
        indicator.classList.remove('hidden');
        list.classList.add('hidden');
    } else {
        indicator.classList.add('hidden');
        list.classList.remove('hidden');
    }
}

// Show error message
function showError(message) {
    alert(message);
}

// Open bulk approval modal - FIXED VERSION
function openBulkApprovalModal() {
    if (selectedUnits.size === 0) return;
    
    const formContainer = document.getElementById('bulkApprovalFormContainer');
    const unitIds = Array.from(selectedUnits);
    
    // Determine earliest move-in date from selected units
    const moveInDate = window.earliestMoveInDate ? 
        window.earliestMoveInDate.toISOString().split('T')[0] : 
        new Date().toISOString().split('T')[0];
    
    // Create form HTML for bulk approval
    let formHtml = `
        <form id="bulkApprovalForm">
            <input type="hidden" name="unit_ids" value='${JSON.stringify(unitIds)}'>
            
            <div class="space-y-4">
                <!-- Role-specific notice -->
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(${window.canViewFinancialInfo ? '--success' : '--info'}-rgb), 0.1); border: 1px solid rgba(var(${window.canViewFinancialInfo ? '--success' : '--info'}-rgb), 0.3);">
                    <div class="flex items-center">
                        <i class="fas ${window.canViewFinancialInfo ? 'fa-user-tie' : 'fa-user-shield'} mr-3 text-lg" style="color: var(${window.canViewFinancialInfo ? '--success' : '--info'});"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                ${window.canViewFinancialInfo ? 'Landlord Approval' : 'Administrator Approval'}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                ${window.canViewFinancialInfo 
                                    ? 'You are approving tenant assignments. Move-in dates and rent can be set by you.' 
                                    : 'You are approving tenant assignments only. Move-in dates and rent are set by landlords.'}
                            </p>
                        </div>
                    </div>
                </div>
    `;
    
    // ============================================================
    // FIXED: Move-in Date - Hidden for Admins, Editable for Landlords
    // ============================================================
    if (window.canViewFinancialInfo) {
        // LANDLORDS: Can set move-in date
        formHtml += `
                <!-- Move-in Date (Editable by Landlords) -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-day mr-1"></i> Move-in Date *
                    </label>
                    <input type="date" name="move_in_date" required
                           class="form-input w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary"
                           value="${moveInDate}"
                           min="${new Date().toISOString().split('T')[0]}"
                           style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> This is the date tenants can move into their units
                    </p>
                </div>
        `;
    }
    // ADMINS: Move-in date is NOT shown - it's set by landlords and hidden from admins
    
    // ============================================================
    // Rent Settings - Only for landlords (hidden from admins)
    // ============================================================
    if (window.canViewFinancialInfo) {
        // Calculate average rent for default value
        let totalRent = 0;
        selectedUnits.forEach(unitId => {
            const unit = unitsData[unitId];
            if (unit) {
                totalRent += parseFloat(unit.proposed_rent) || 0;
            }
        });
        const avgRent = selectedUnits.size > 0 ? totalRent / selectedUnits.size : 0;
        
        formHtml += `
                <!-- Final Rent Amount (Landlords only) -->
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-2"></i> Final Rent Amount
                    </h4>
                    <div class="space-y-3">
                        <label class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <input type="radio" name="final_rent_amount_type" value="proposed" class="form-radio mr-3" checked>
                            <div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Use proposed rent for each unit</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Individual rent amounts as requested by landlord</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <input type="radio" name="final_rent_amount_type" value="base" class="form-radio mr-3">
                            <div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Use base rent for each unit</span>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Standard rent amounts from property settings</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <input type="radio" name="final_rent_amount_type" value="custom" class="form-radio mr-3">
                            <div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Set custom amount for all units</span>
                                <div id="customRentContainer" class="mt-2 hidden">
                                    <div class="flex items-center">
                                        <input type="number" name="custom_rent_amount" 
                                               class="form-input w-32 mr-2 bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" 
                                               value="${avgRent.toFixed(2)}"
                                               step="0.01" min="0"
                                               style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">
                                        <span class="text-sm" style="color: var(--text-secondary);">GHS per month</span>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Security Deposit (Optional) - Landlords only -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-1"></i> Security Deposit (Optional)
                    </label>
                    <div class="flex items-center">
                        <input type="number" name="security_deposit" 
                               class="form-input w-32 mr-2 bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary" 
                               value=""
                               placeholder="0.00"
                               step="0.01" min="0"
                               style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">
                        <span class="text-sm" style="color: var(--text-secondary);">GHS (leave empty to use unit default)</span>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Optional: Set a security deposit amount for all units
                    </p>
                </div>
        `;
    } else {
        // ADMINS: Hidden fields with default values - these are NOT shown in the UI
        formHtml += `
                <input type="hidden" name="final_rent_amount_type" value="proposed">
                <input type="hidden" name="security_deposit" value="">
        `;
    }
    
    formHtml += `
                <!-- Invitations for New Tenants -->
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex items-center mb-4">
                        <input type="checkbox" name="send_invitations" id="sendInvitations" class="form-checkbox mr-3" value="1"
                               style="color: var(--primary);">
                        <label for="sendInvitations" class="text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-1"></i> Send Invitations to New Tenants
                        </label>
                    </div>
                    
                    <div id="bulkInvitationFields" class="space-y-2 pl-6 hidden">
                        <p class="text-sm mb-2" style="color: var(--text-secondary);">Select invitation channels:</p>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="invitation_channels[]" class="form-checkbox mr-3" value="email" checked
                                       style="color: var(--primary);">
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-envelope mr-1"></i> Email
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="invitation_channels[]" class="form-checkbox mr-3" value="sms"
                                       style="color: var(--primary);">
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-sms mr-1"></i> SMS
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="invitation_channels[]" class="form-checkbox mr-3" value="whatsapp"
                                       style="color: var(--primary);">
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                                </span>
                            </label>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Invitations will be sent to new tenants only
                        </p>
                    </div>
                </div>
                
                <!-- Approval Notes -->
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-check mr-1"></i> Approval Notes *
                    </label>
                    <textarea name="approval_notes" rows="3"
                              class="form-textarea w-full bg-bg-input dark:bg-dark-bg-input border-border-color dark:border-dark-border-color text-text-primary dark:text-dark-text-primary"
                              placeholder="Enter approval notes or comments..."
                              required
                              style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);"></textarea>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Required: Add notes explaining this approval decision
                    </p>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                <button type="button" 
                        onclick="closeBulkApprovalModal()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium mr-3" 
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                    Cancel
                </button>
                <button type="button" 
                        onclick="submitBulkApproval()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                        style="background-color: var(--success); color: white; border: 2px solid var(--success);">
                    <i class="fas fa-user-check mr-2"></i> Approve Tenants Only
                </button>
            </div>
        </form>
    `;
    
    formContainer.innerHTML = formHtml;
    
    // Show custom rent input when custom option is selected (only for landlords)
    if (window.canViewFinancialInfo) {
        document.querySelectorAll('input[name="final_rent_amount_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customContainer = document.getElementById('customRentContainer');
                if (this.value === 'custom') {
                    customContainer.classList.remove('hidden');
                } else {
                    customContainer.classList.add('hidden');
                }
            });
        });
    }
    
    // Toggle invitation fields
    const invitationCheckbox = document.getElementById('sendInvitations');
    const invitationFields = document.getElementById('bulkInvitationFields');
    
    if (invitationCheckbox && invitationFields) {
        function toggleInvitationFields() {
            invitationFields.style.display = invitationCheckbox.checked ? 'block' : 'none';
        }
        
        invitationCheckbox.addEventListener('change', toggleInvitationFields);
        toggleInvitationFields();
    }
    
    document.getElementById('bulkApprovalModal').classList.remove('hidden');
}

// Close bulk approval modal
function closeBulkApprovalModal() {
    document.getElementById('bulkApprovalModal').classList.add('hidden');
}

// Submit bulk approval - FIXED VERSION
function submitBulkApproval() {
    const form = document.getElementById('bulkApprovalForm');
    const formData = new FormData(form);
    
    // Log form data for debugging
    console.log('Submitting bulk approval with data:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validate form
    const moveInDate = formData.get('move_in_date');
    const approvalNotes = formData.get('approval_notes');
    
    if (!moveInDate) {
        showError('Move-in date is required');
        return;
    }
    
    if (!approvalNotes || approvalNotes.trim().length < 10) {
        showError('Please enter approval notes (minimum 10 characters)');
        return;
    }
    
    // Check invitation channels if send_invitations is checked
    const sendInvitations = formData.get('send_invitations');
    if (sendInvitations === '1') {
        const channels = formData.getAll('invitation_channels[]');
        if (channels.length === 0) {
            showError('Please select at least one invitation channel');
            return;
        }
    }
    
    // Show loading state
    const submitButton = document.querySelector('#bulkApprovalModal button[onclick="submitBulkApproval()"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    // Convert FormData to JSON, handling array fields properly
    const jsonData = {};
    formData.forEach((value, key) => {
        // Handle array fields (like invitation_channels[])
        if (key === 'invitation_channels[]') {
            if (!jsonData['invitation_channels']) {
                jsonData['invitation_channels'] = [];
            }
            jsonData['invitation_channels'].push(value);
        } else {
            jsonData[key] = value;
        }
    });
    
    console.log('Sending JSON data:', jsonData);
    
    fetch('{{ route("admin.property-units.bulk-approval.process") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(data => {
        console.log('Success response:', data);
        if (data.success) {
            showResults(data);
            // Clear selection
            selectedUnits.clear();
            updateSelection();
            updateSummary();
            
            // Check if there are any units left after approval
            const remainingUnits = Object.keys(unitsData).filter(id => {
                // Filter out approved units
                return !data.results.details?.some(d => d.unit_id == id && d.success);
            }).length;
            
            if (remainingUnits === 0) {
                // If no units left, show empty state
                const container = document.getElementById('unitsList');
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-circle text-2xl" style="color: var(--success);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">All Done!</h4>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            All pending applications have been processed.
                        </p>
                        <a href="{{ route('property-units.index') }}" 
                           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium"
                           style="background-color: var(--primary); color: white;">
                            <i class="fas fa-building mr-2"></i> View Property Units
                        </a>
                    </div>
                `;
                document.getElementById('totalCount').textContent = '0';
                document.getElementById('selectedCount').textContent = '0';
            } else {
                // Only reload if there are still units
                loadFilteredUnits();
            }
            
            closeBulkApprovalModal();
        } else {
            showError(data.message || 'Bulk approval failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred during bulk approval: ' + (error.message || JSON.stringify(error)));
    })
    .finally(() => {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// Show results modal - UPDATED with navigation buttons
function showResults(data) {
    const resultsContent = document.getElementById('resultsContent');
    
    let html = `
        <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--success-rgb), 0.2);">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
                <div>
                    <h4 class="font-bold" style="color: var(--success);">Bulk Tenant Approval Completed</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        ${data.message || 'Tenant approval process completed successfully'}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <p class="text-2xl font-bold" style="color: var(--info);">${data.results.total}</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Total Units</p>
            </div>
            <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <p class="text-2xl font-bold" style="color: var(--success);">${data.results.successful}</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Successful</p>
            </div>
            <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <p class="text-2xl font-bold" style="color: var(--danger);">${data.results.failed}</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Failed</p>
            </div>
        </div>
        
        <!-- Next Steps Note -->
        <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-info-circle mr-3 text-lg" style="color: var(--info);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Next Steps:</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Tenants have been approved. Landlords can now create lease agreements for their properties.
                        ${data.results.invitation_sent ? '<br>Invitations have been sent to new tenants.' : ''}
                    </p>
                </div>
            </div>
        </div>
    `;
    
    // Add detailed results if available
    if (data.results.details && data.results.details.length > 0) {
        html += `
            <div class="border rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b" style="border-color: var(--border-color); background-color: rgba(var(--secondary-rgb), 0.05);">
                    <h5 class="font-medium text-sm" style="color: var(--text-primary);">Detailed Results</h5>
                </div>
                <div class="max-h-64 overflow-y-auto">
                    <table class="min-w-full divide-y" style="border-color: var(--border-color);">
                        <thead>
                            <tr style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--border-color);">
        `;
        
        data.results.details.forEach(detail => {
            const unit = unitsData[detail.unit_id] || {};
            const statusClass = detail.success ? 'success' : 'danger';
            html += `
                <tr>
                    <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">
                        ${unit.property_name || 'N/A'} - Unit ${unit.unit_number || detail.unit_id}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-${statusClass}">
                            ${detail.success ? 'Success' : 'Failed'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">${detail.message}</td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    // Add navigation buttons
    html += `
        <div class="mt-6 flex justify-center space-x-3">
            <a href="{{ route('property-units.index') }}" 
               class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="background-color: var(--primary); color: white;">
                <i class="fas fa-building mr-2"></i> View All Units
            </a>
            <button onclick="closeResultsModalAndRefresh()" 
                    class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                <i class="fas fa-sync-alt mr-2"></i> Refresh List
            </button>
        </div>
    `;
    
    resultsContent.innerHTML = html;
    document.getElementById('resultsModal').classList.remove('hidden');
}

// Add this new function to handle results modal close and refresh
function closeResultsModalAndRefresh() {
    closeResultsModal();
    loadFilteredUnits(); // Try to reload units
}

// Close results modal
function closeResultsModal() {
    document.getElementById('resultsModal').classList.add('hidden');
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBulkApprovalModal();
        closeResultsModal();
    }
});

// Close modals on outside click
document.getElementById('bulkApprovalModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBulkApprovalModal();
    }
});

document.getElementById('resultsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeResultsModal();
    }
});
</script>

<style>
.unit-item.selected {
    background-color: rgba(var(--primary-rgb), 0.05) !important;
    border-color: var(--primary) !important;
}

#unitsList::-webkit-scrollbar {
    width: 6px;
}

#unitsList::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

#unitsList::-webkit-scrollbar-thumb {
    background: var(--text-secondary);
    border-radius: 3px;
}

#unitsList::-webkit-scrollbar-thumb:hover {
    background: var(--text-primary);
}

/* Sticky card behavior */
#selectionSummaryCard.sticky {
    position: sticky;
    top: 1.5rem;
    z-index: 10;
}

/* Add space to prevent overlap */
#statsCard {
    position: relative;
    z-index: 1;
}

/* Ensure proper stacking context */
@media (max-height: 800px) {
    #selectionSummaryCard {
        position: relative !important;
        top: auto !important;
        z-index: 1 !important;
    }
}

/* Badge Styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Modal Styles */
.theme-modal-compact {
    width: 95vw;
    max-width: 600px;
    margin: 1rem auto;
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.theme-modal-header-compact {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--header-bg);
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.theme-modal-header-compact h3 {
    color: var(--text-primary) !important;
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    margin: 0;
}

.theme-modal-body-compact {
    padding: 1.5rem;
    background-color: var(--card-bg);
}

.theme-modal-footer-compact {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Form Styles */
.form-input, .form-select, .form-textarea {
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-input:disabled, .form-select:disabled, .form-textarea:disabled {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    cursor: pointer;
    transition: all 0.2s;
}

.form-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.form-checkbox:checked::after {
    content: '✓';
    color: white;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-radio {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    cursor: pointer;
    transition: all 0.2s;
    appearance: none;
    -webkit-appearance: none;
}

.form-radio:checked {
    background-color: var(--primary);
    border-color: var(--primary);
    box-shadow: inset 0 0 0 3px var(--bg-input);
}

/* Dropdown styles for dark/light mode */
select.bg-bg-input {
    background-color: var(--bg-input) !important;
}

select.border-border-color {
    border-color: var(--border-color) !important;
}

select.text-text-primary {
    color: var(--text-primary) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: 1;
    }
    
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
    }
    
    .theme-modal-compact {
        width: 95vw;
        max-width: 95vw;
        margin: 0.5rem;
    }
    
    /* Disable sticky on mobile */
    #selectionSummaryCard {
        position: relative !important;
        top: auto !important;
        z-index: 1 !important;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .space-y-6 {
        gap: 1rem;
    }
    
    .p-6 {
        padding: 1rem;
    }
}
</style>
@endsection