{{-- resources/views/landlord/tenants/index.blade.php --}}
@php
    // Dynamic title
    $pageTitle = 'My Tenants';
    $isLandlord = auth()->user()->isLandlord();
    
    // Get any success/error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Determine route prefix
    $routePrefix = 'landlord';
    
    // CRITICAL FIX: Ensure $stats variable exists and has all required keys
    $stats = $stats ?? [];
    
    // Define default stats structure
    $defaultStats = [
        'total_tenants' => 0,
        'approved_tenants' => 0,
        'pending_approvals' => 0,
        'vacated_tenants' => 0,
        'rejected_tenants' => 0,
        'terminated_tenants' => 0,
        'filtered_tenants' => 0
    ];
    
    // Merge defaults with actual stats, ensuring all keys exist
    foreach ($defaultStats as $key => $defaultValue) {
        if (!isset($stats[$key])) {
            $stats[$key] = $defaultValue;
        }
    }
    
    // Ensure all values are integers
    foreach ($stats as $key => $value) {
        $stats[$key] = (int) $value;
    }
    
    // FALLBACK: If $properties is not set, create empty collection
    $properties = $properties ?? collect();
    
    // FALLBACK: If $tenants is not set, create empty collection
    $tenants = $tenants ?? collect();
    
    // FALLBACK: If $tenantStatusOptions is not set, create default
    $tenantStatusOptions = $tenantStatusOptions ?? [
        'approved' => 'Approved',
        'pending_approval' => 'Pending Approval',
        'rejected' => 'Rejected',
        'vacated' => 'Vacated',
        'terminated' => 'Terminated'
    ];
@endphp

@extends('layouts.landlord')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-users mr-2" style="color: var(--primary);"></i> My Tenants
            </h2>
            <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-users mr-2"></i> 
                <span>{{ number_format($stats['total_tenants']) }} tenant(s)</span>
                <span class="ml-1">across your properties</span>
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

    @if($tenants->isEmpty())
        <!-- Empty State -->
        <div class="card">
            <div class="text-center py-16">
                <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6" 
                     style="background-color: rgba(var(--secondary-rgb), 0.1); border: 2px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-users text-4xl" style="color: var(--secondary);"></i>
                </div>
                <h4 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Tenants Found</h4>
                <p class="mb-8 max-w-md mx-auto text-sm" style="color: var(--text-secondary);">
                    @if(request()->hasAny(['search', 'property_id', 'tenant_status', 'phone', 'email']))
                        No tenants match your filter criteria. Try adjusting your filters.
                    @else
                        You don't have any tenants assigned to your properties yet.
                    @endif
                </p>
                @if(!request()->hasAny(['search', 'property_id', 'tenant_status']))
                <!-- Removed: Add Properties Button -->
                @endif
            </div>
        </div>
    @else
        <!-- Quick Actions Bar -->
        <div class="card mb-6">
            <div class="p-4">
                <div class="flex flex-wrap gap-3 items-center justify-between">
                    <div>
                        <h4 class="font-medium flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i>
                            Quick Actions for Approved Tenants
                        </h4>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Manage your active tenants with these quick actions
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('landlord.property-units.pending-approvals') }}" 
                           class="px-3 py-2 rounded-lg inline-flex items-center text-xs font-medium" 
                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-user-clock mr-1"></i> Pending Approvals ({{ $stats['pending_approvals'] }})
                        </a>
                        <a href="{{ route('landlord.property-units.index', ['tenant_status' => 'approved']) }}" 
                           class="px-3 py-2 rounded-lg inline-flex items-center text-xs font-medium" 
                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> View Active Tenants
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2"></i> Filters & Search
                    </h3>
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Use filters to narrow down results
                    </div>
                </div>
                
                <form method="GET" action="{{ route('landlord.tenants.index') }}" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Search Input - FIXED VERSION -->
                        <div>
                            <label for="search" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Search
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       id="search" 
                                       value="{{ request('search') }}" 
                                       class="form-input w-full pl-10 pr-3"
                                       placeholder="Search by name, email, phone..."
                                       style="padding-left: 2.5rem;">
                                @if(request('search'))
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" 
                                                onclick="document.getElementById('search').value = ''; this.closest('form').submit()"
                                                class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Property Filter -->
                        <div>
                            <label for="property_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Property
                            </label>
                            <select name="property_id" 
                                    id="property_id" 
                                    class="form-select w-full">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="tenant_status" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Status
                            </label>
                            <select name="tenant_status" 
                                    id="tenant_status" 
                                    class="form-select w-full">
                                <option value="">All Statuses</option>
                                @foreach($tenantStatusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ request('tenant_status') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Additional Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Phone Filter -->
                        <div>
                            <label for="phone" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Phone Number
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone" style="color: var(--text-secondary); font-size: 0.875rem;"></i>
                                </div>
                                <input type="text" 
                                       name="phone" 
                                       id="phone" 
                                       value="{{ request('phone') }}" 
                                       class="form-input w-full pl-10"
                                       placeholder="Filter by phone number..."
                                       style="padding-left: 2.5rem;">
                            </div>
                        </div>

                        <!-- Email Filter -->
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope" style="color: var(--text-secondary); font-size: 0.875rem;"></i>
                                </div>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       value="{{ request('email') }}" 
                                       class="form-input w-full pl-10"
                                       placeholder="Filter by email..."
                                       style="padding-left: 2.5rem;">
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Registration Date
                            </label>
                            <div class="flex space-x-2">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-calendar-alt" style="color: var(--text-secondary); font-size: 0.875rem;"></i>
                                    </div>
                                    <input type="date" 
                                           name="start_date" 
                                           value="{{ request('start_date') }}" 
                                           class="form-input w-full pl-10"
                                           placeholder="From"
                                           style="padding-left: 2.5rem;">
                                </div>
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-calendar-alt" style="color: var(--text-secondary); font-size: 0.875rem;"></i>
                                    </div>
                                    <input type="date" 
                                           name="end_date" 
                                           value="{{ request('end_date') }}" 
                                           class="form-input w-full pl-10"
                                           placeholder="To"
                                           style="padding-left: 2.5rem;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="flex flex-wrap gap-3 mb-4 md:mb-0">
                            <!-- Removed: Add Properties Button -->
                            
                            <a href="{{ route('landlord.tenants.export', request()->all()) }}" 
                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-file-export mr-2"></i> Export List
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('landlord.tenants.index') }}" 
                               class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-redo mr-2"></i> Reset Filters
                            </a>
                            
                            <button type="submit" 
                                    class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                                    style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                                <i class="fas fa-search mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tenants List -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="p-6">
                        <!-- Results Count -->
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Showing <span class="font-bold">{{ $tenants->firstItem() ?? 0 }}</span> to 
                                    <span class="font-bold">{{ $tenants->lastItem() ?? 0 }}</span> of 
                                    <span class="font-bold">{{ $tenants->total() ?? 0 }}</span> tenants
                                </p>
                            </div>
                            <div class="text-xs px-3 py-1 rounded-full" 
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-chart-bar mr-1"></i> {{ $stats['approved_tenants'] }} Active
                            </div>
                        </div>

                        <div class="space-y-6">
                            @foreach($tenants as $tenant)
                                @if(is_null($tenant))
                                    @continue
                                @endif
                                
                                @php
                                    $unit = $tenant->propertyUnits->first();
                                    $status = $unit ? $unit->tenant_status : null;
                                    $statusColor = match($status) {
                                        \App\Models\PropertyUnit::TENANT_STATUS_APPROVED => 'success',
                                        \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL => 'warning',
                                        \App\Models\PropertyUnit::TENANT_STATUS_REJECTED => 'danger',
                                        \App\Models\PropertyUnit::TENANT_STATUS_VACATED => 'secondary',
                                        default => 'info'
                                    };
                                    
                                    // Check if tenant can have lease
                                    $canCreateLease = $unit && 
                                                     $unit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_APPROVED &&
                                                     (!$unit->currentLease || $unit->currentLease->status !== 'active');
                                @endphp
                                
                                <div class="border rounded-lg p-5 transition-all duration-200 hover:border-primary hover:shadow-md" 
                                     style="border-color: var(--border-color); background-color: var(--card-bg); border-left: 4px solid var(--{{ $statusColor }});">
                                    <!-- Tenant Header -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                        <div class="mb-3 md:mb-0">
                                            <div class="flex items-center mb-2">
                                                <div class="flex-shrink-0 mr-3">
                                                    @if($tenant->photo)
                                                        <img class="w-12 h-12 rounded-full object-cover" 
                                                             src="{{ Storage::url($tenant->photo) }}" 
                                                             alt="{{ $tenant->name }}">
                                                    @else
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600;">
                                                            {{ substr($tenant->name, 0, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold text-lg mr-3" style="color: var(--text-primary);">
                                                        <a href="{{ route('landlord.tenants.show', $tenant->id) }}" 
                                                           class="hover:underline hover:text-primary">
                                                            {{ $tenant->name }}
                                                        </a>
                                                    </h4>
                                                    <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                                        <i class="fas fa-id-card mr-2"></i>
                                                        <span>ID: {{ $tenant->id }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ $statusColor }}">
                                                <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                                {{ $status ? ucfirst(str_replace('_', ' ', $status)) : 'Not Assigned' }}
                                            </span>
                                            @if($unit && $unit->current_rent_amount)
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-info">
                                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                                    GHS {{ number_format($unit->current_rent_amount) }}/month
                                                </span>
                                            @endif
                                            @if($unit && $unit->currentLease && $unit->currentLease->status === 'active')
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-success">
                                                    <i class="fas fa-file-contract mr-1"></i>
                                                    Active Lease
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Details Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <!-- Contact Information -->
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                                    <i class="fas fa-address-card mr-1"></i> Contact Information
                                                </label>
                                                <div class="space-y-3">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                            <i class="fas fa-envelope"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                                {{ $tenant->email }}
                                                            </div>
                                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                Email Address
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                            <i class="fas fa-phone"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                                {{ $tenant->phone ?? 'Not provided' }}
                                                            </div>
                                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                Phone Number
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    @if($tenant->digital_address)
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                                {{ $tenant->digital_address }}
                                                            </div>
                                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                Digital Address
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Property & Unit Details -->
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-xs font-medium mb-2 uppercase tracking-wider" style="color: var(--text-secondary);">
                                                    <i class="fas fa-home mr-1"></i> Property & Unit
                                                </label>
                                                @if($unit)
                                                <div class="space-y-3">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                            <i class="fas fa-building"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                                                <a href="{{ route('properties.show', $unit->property_id) }}" 
                                                                   class="hover:underline hover:text-primary">
                                                                    {{ $unit->property->property_name ?? 'N/A' }}
                                                                </a>
                                                            </div>
                                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                Property
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="pt-3 border-t" style="border-color: rgba(var(--{{ $statusColor }}-rgb), 0.2);">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <p class="text-sm font-medium" style="color: var(--primary);">
                                                                    <i class="fas fa-door-closed mr-1"></i>
                                                                    Assigned Unit
                                                                </p>
                                                                <p class="text-lg font-bold mt-1" style="color: var(--primary);">
                                                                    Unit {{ $unit->unit_number }}
                                                                    @if($unit->unit_name)
                                                                        <span class="text-sm font-normal">({{ $unit->unit_name }})</span>
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            @if($unit->tenant_move_in_date)
                                                            <div class="text-right">
                                                                <p class="text-xs font-medium" style="color: var(--text-secondary);">
                                                                    Move-in Date
                                                                </p>
                                                                <p class="text-sm" style="color: var(--text-primary);">
                                                                    {{ $unit->tenant_move_in_date->format('M d, Y') }}
                                                                </p>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                @else
                                                <div class="text-center py-4">
                                                    <p class="text-sm italic" style="color: var(--text-secondary);">
                                                        <i class="fas fa-home-slash mr-1"></i>
                                                        No property unit assigned
                                                    </p>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-wrap justify-end gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                                        <!-- CREATE LEASE BUTTON (Only for approved tenants without active lease) -->
                                        @if($canCreateLease)
                                        <a href="{{ route('landlord.property-units.create-lease.form', $unit->id) }}" 
                                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                           style="background: linear-gradient(135deg, var(--success) 0%, rgba(var(--success-rgb), 0.9) 100%); color: white; border: 1px solid var(--success);"
                                           title="Create lease agreement for this tenant">
                                            <i class="fas fa-file-contract mr-2"></i> Create Lease
                                        </a>
                                        @endif

                                        <!-- VIEW LEASE BUTTON (If has active lease) -->
                                        @if($unit && $unit->currentLease && $unit->currentLease->status === 'active')
                                        <a href="{{ route('landlord.property-units.lease-management', $unit->id) }}" 
                                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                           title="View and manage lease">
                                            <i class="fas fa-file-alt mr-2"></i> View Lease
                                        </a>
                                        @endif
                                        
                                        <!-- Communication Button -->
                                        <button onclick="showCommunicationModal({{ $tenant->id }}, '{{ addslashes($tenant->name) }}')"
                                                class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);"
                                                title="Send Message">
                                            <i class="fas fa-comment mr-2"></i> Message
                                        </button>
                                        
                                        <!-- View Details Button -->
                                        <a href="{{ route('landlord.tenants.show', $tenant->id) }}" 
                                           class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                            <i class="fas fa-eye mr-2"></i> View Details
                                        </a>
                                        
                                        <!-- Quick Actions Dropdown -->
                                        <div class="relative inline-block">
                                            <button type="button"
                                                    onclick="toggleDropdown('tenant-actions-{{ $tenant->id }}')"
                                                    class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium hover:shadow-md transition-all duration-200"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                <i class="fas fa-ellipsis-v mr-2"></i> More
                                            </button>
                                            <div id="tenant-actions-{{ $tenant->id }}" 
                                                 class="hidden absolute right-0 mt-2 w-48 dropdown-menu z-10">
                                                <div class="py-1">
                                                    @if($unit && $unit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                                                    <a href="{{ route('property-units.mark-vacated-form', $unit->id) }}"
                                                       class="dropdown-item">
                                                        <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                                                    </a>
                                                    <a href="{{ route('invoices.create', ['tenant_id' => $tenant->id]) }}"
                                                       class="dropdown-item">
                                                        <i class="fas fa-file-invoice-dollar mr-2"></i> Create Invoice
                                                    </a>
                                                    @endif
                                                    <button type="button"
                                                            onclick="showTenantHistory({{ $tenant->id }})"
                                                            class="dropdown-item w-full text-left">
                                                        <i class="fas fa-history mr-2"></i> View History
                                                    </button>
                                                    @if($unit)
                                                    <a href="{{ route('landlord.property-units.financials', $unit->id) }}"
                                                       class="dropdown-item">
                                                        <i class="fas fa-chart-line mr-2"></i> Financials
                                                    </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="flex flex-col md:flex-row items-center justify-between pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                            <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                                <span class="font-medium">Showing</span> 
                                <span class="font-bold">{{ $tenants->firstItem() ?? 0 }}</span> 
                                <span class="font-medium">to</span> 
                                <span class="font-bold">{{ $tenants->lastItem() ?? 0 }}</span> 
                                <span class="font-medium">of</span> 
                                <span class="font-bold">{{ $tenants->total() ?? 0 }}</span> 
                                <span class="font-medium">entries</span>
                            </div>
                            <div class="pagination">
                                {{ $tenants->appends(request()->except('page'))->links('vendor.pagination.tailwind') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Statistics Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-chart-pie mr-2"></i> Tenant Statistics
                            </h3>
                            <i class="fas fa-info-circle text-sm" style="color: var(--text-secondary);"></i>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Total Tenants -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Tenants</p>
                                        <p class="text-3xl font-bold" style="color: var(--primary);">{{ number_format($stats['total_tenants']) }}</p>
                                    </div>
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                                        <i class="fas fa-users text-xl" style="color: var(--primary);"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stats Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Active Tenants -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--success-rgb), 0.1);">
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Active Tenants</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--success);">{{ number_format($stats['approved_tenants']) }}</p>
                                </div>
                                
                                <!-- Pending Approvals -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                                            <i class="fas fa-clock" style="color: var(--warning);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Pending Approvals</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--warning);">{{ number_format($stats['pending_approvals']) }}</p>
                                </div>
                                
                                <!-- Vacated Tenants -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                            <i class="fas fa-home" style="color: var(--secondary);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Vacated Tenants</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--secondary);">{{ number_format($stats['vacated_tenants']) }}</p>
                                </div>
                                
                                <!-- Rejected Tenants -->
                                <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                                            <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--text-secondary);">Rejected Tenants</span>
                                    </div>
                                    <p class="text-2xl font-bold" style="color: var(--danger);">{{ number_format($stats['rejected_tenants']) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Lease Actions Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-file-contract mr-2" style="color: var(--success);"></i> Lease Management
                            </h3>
                            @if($stats['approved_tenants'] > 0)
                            <span class="text-xs px-2 py-1 rounded-full" 
                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                {{ $stats['approved_tenants'] }} can have leases
                            </span>
                            @endif
                        </div>
                        
                        <div class="space-y-3">
                            <!-- Create New Lease -->
                            <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--success-rgb), 0.05);">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium" style="color: var(--success);">Create New Lease</p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            For approved tenants without active lease
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Available on each approved tenant card
                                </div>
                            </div>
                            
                            <!-- View Active Leases -->
                            <a href="{{ route('landlord.property-units.index', ['has_active_lease' => true]) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                               style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <span class="font-medium block">View Active Leases</span>
                                    <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                        Manage all active lease agreements
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                            </a>
                            
                            <!-- Lease Templates -->
                            <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.05);">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-file-download"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium" style="color: var(--primary);">Lease Templates</p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Standard lease agreement templates
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <button onclick="showLeaseTemplates()"
                                            class="text-xs px-3 py-1 rounded-lg w-full"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-eye mr-1"></i> View Templates
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Filters Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-bolt mr-2"></i> Quick Filters
                            </h3>
                            <button type="button" onclick="resetFilters()" 
                                    class="text-xs px-3 py-1 rounded-lg" 
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-redo mr-1"></i> Reset
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <!-- Active Tenants Filter -->
                            <a href="{{ route('landlord.tenants.index', ['tenant_status' => 'approved']) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('tenant_status') == 'approved' ? 'border-success bg-success text-white' : 'border' }}"
                               style="{{ request('tenant_status') == 'approved' ? 'border-color: var(--success);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('tenant_status') == 'approved' ? 'bg-white/20' : '' }}"
                                     style="{{ request('tenant_status') != 'approved' ? 'background-color: rgba(var(--success-rgb), 0.1); color: var(--success);' : 'color: white;' }}">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">Active Tenants</span>
                                    <span class="text-xs block mt-1" style="{{ request('tenant_status') == 'approved' ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['approved_tenants'] }} tenants
                                    </span>
                                </div>
                                @if(request('tenant_status') == 'approved')
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                            
                            <!-- Pending Approvals Filter -->
                            <a href="{{ route('landlord.tenants.index', ['tenant_status' => 'pending_approval']) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('tenant_status') == 'pending_approval' ? 'border-warning bg-warning text-white' : 'border' }}"
                               style="{{ request('tenant_status') == 'pending_approval' ? 'border-color: var(--warning);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('tenant_status') == 'pending_approval' ? 'bg-white/20' : '' }}"
                                     style="{{ request('tenant_status') != 'pending_approval' ? 'background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);' : 'color: white;' }}">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">Pending Approvals</span>
                                    <span class="text-xs block mt-1" style="{{ request('tenant_status') == 'pending_approval' ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['pending_approvals'] }} pending
                                    </span>
                                </div>
                                @if(request('tenant_status') == 'pending_approval')
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                            
                            <!-- Vacated Tenants Filter -->
                            <a href="{{ route('landlord.tenants.index', ['tenant_status' => 'vacated']) }}" 
                               class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md {{ request('tenant_status') == 'vacated' ? 'border-secondary bg-secondary text-white' : 'border' }}"
                               style="{{ request('tenant_status') == 'vacated' ? 'border-color: var(--secondary);' : 'border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);' }}">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 {{ request('tenant_status') == 'vacated' ? 'bg-white/20' : '' }}"
                                     style="{{ request('tenant_status') != 'vacated' ? 'background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);' : 'color: white;' }}">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">Vacated Tenants</span>
                                    <span class="text-xs block mt-1" style="{{ request('tenant_status') == 'vacated' ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">
                                        {{ $stats['vacated_tenants'] }} tenants
                                    </span>
                                </div>
                                @if(request('tenant_status') == 'vacated')
                                    <i class="fas fa-check text-white"></i>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Communication Modal -->
<div id="communicationModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-comment mr-2" style="color: var(--primary);"></i> 
                    Send Message to <span id="tenant-name"></span>
                </h3>
                <button type="button" 
                        id="closeModalBtn" 
                        onclick="closeModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="communicationForm" method="POST" action="#" class="theme-modal-body-compact">
                @csrf
                <input type="hidden" id="tenant-id" name="tenant_id">
                
                <!-- Message Type -->
                <div class="mb-6">
                    <label for="message_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Message Type
                    </label>
                    <select name="message_type" 
                            id="message_type" 
                            class="form-select w-full">
                        <option value="payment_reminder">Payment Reminder</option>
                        <option value="maintenance_update">Maintenance Update</option>
                        <option value="general" selected>General Message</option>
                    </select>
                </div>
                
                <!-- Channels -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Communication Channels
                    </label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="channels[]" 
                                   value="email" 
                                   class="form-checkbox" 
                                   checked>
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="channels[]" 
                                   value="sms" 
                                   class="form-checkbox" 
                                   checked>
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-sms mr-1"></i> SMS
                            </span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="channels[]" 
                                   value="whatsapp" 
                                   class="form-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Message -->
                <div class="mb-6">
                    <label for="message" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Message <span class="text-red-500">*</span>
                    </label>
                    <textarea name="message" 
                              id="message" 
                              required 
                              rows="5"
                              class="form-textarea w-full"
                              placeholder="Type your message here..."
                              style="background-color: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);"></textarea>
                </div>
                
                <!-- Action Buttons -->
                <div class="theme-modal-footer-compact">
                    <button type="button" 
                            onclick="closeModal()" 
                            class="px-4 py-2 rounded-lg text-sm font-medium" 
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                            style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="historyModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeHistoryModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--info);"></i> 
                    Tenant History
                </h3>
                <button type="button" 
                        id="closeHistoryModalBtn" 
                        onclick="closeHistoryModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div id="historyContent" class="theme-modal-body-compact" style="max-height: 400px; overflow-y: auto;">
                <!-- History will be loaded here -->
            </div>
            
            <!-- Modal Footer -->
            <div class="theme-modal-footer-compact">
                <button onclick="closeHistoryModal()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium" 
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lease Templates Modal -->
<div id="leaseTemplatesModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeLeaseTemplatesModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact" style="max-width: 600px;">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-download mr-2" style="color: var(--success);"></i> 
                    Lease Agreement Templates
                </h3>
                <button type="button" 
                        onclick="closeLeaseTemplatesModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="theme-modal-body-compact">
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Select a template to download or use as reference when creating new leases:
                </p>
                
                <div class="space-y-3">
                    <!-- Standard Residential Lease -->
                    <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Standard Residential Lease</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        12-month fixed term agreement
                                    </p>
                                </div>
                            </div>
                            <button onclick="downloadTemplate('standard')"
                                    class="text-xs px-3 py-1 rounded-lg"
                                    style="background-color: var(--success); color: white;">
                                <i class="fas fa-download mr-1"></i> Download
                            </button>
                        </div>
                    </div>
                    
                    <!-- Month-to-Month Agreement -->
                    <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Month-to-Month Agreement</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Flexible monthly rental agreement
                                    </p>
                                </div>
                            </div>
                            <button onclick="downloadTemplate('month_to_month')"
                                    class="text-xs px-3 py-1 rounded-lg"
                                    style="background-color: var(--info); color: white;">
                                <i class="fas fa-download mr-1"></i> Download
                            </button>
                        </div>
                    </div>
                    
                    <!-- Furnished Unit Lease -->
                    <div class="p-3 rounded-lg border" style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-couch"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Furnished Unit Lease</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Includes furniture inventory
                                    </p>
                                </div>
                            </div>
                            <button onclick="downloadTemplate('furnished')"
                                    class="text-xs px-3 py-1 rounded-lg"
                                    style="background-color: var(--warning); color: white;">
                                <i class="fas fa-download mr-1"></i> Download
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        These templates are for reference only. Always customize the lease to fit your specific needs and local laws.
                    </p>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="theme-modal-footer-compact">
                <button onclick="closeLeaseTemplatesModal()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium" 
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close button hover effect for modals
    const closeButton = document.getElementById('closeModalBtn');
    const closeHistoryButton = document.getElementById('closeHistoryModalBtn');
    
    function setupCloseButtonHover(button) {
        if (button) {
            button.addEventListener('mouseenter', function() {
                this.style.color = 'var(--text-primary)';
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            });
            button.addEventListener('mouseleave', function() {
                this.style.color = 'var(--text-secondary)';
                this.style.backgroundColor = 'transparent';
            });
        }
    }
    
    setupCloseButtonHover(closeButton);
    setupCloseButtonHover(closeHistoryButton);
    
    // Auto-hide success and error messages after 5 seconds
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
    
    // Clear search button functionality
    const searchInput = document.getElementById('search');
    const clearSearchBtn = searchInput?.parentElement.querySelector('.absolute.right-0 button');
    
    if (searchInput && clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            searchInput.closest('form').submit();
        });
        
        // Show/hide clear button based on input
        searchInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                clearSearchBtn.style.display = 'flex';
            } else {
                clearSearchBtn.style.display = 'none';
            }
        });
        
        // Initialize visibility
        if (searchInput.value.trim() === '') {
            clearSearchBtn.style.display = 'none';
        }
    }
});

// Toggle dropdown
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    dropdown.classList.toggle('hidden');
    
    // Close other dropdowns
    document.querySelectorAll('.relative .dropdown-menu.hidden:not(#' + dropdownId + ')').forEach(el => {
        el.classList.add('hidden');
    });
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.relative button')) {
        document.querySelectorAll('.dropdown-menu').forEach(el => {
            el.classList.add('hidden');
        });
    }
});

// Communication modal functions
function showCommunicationModal(tenantId, tenantName) {
    document.getElementById('tenant-id').value = tenantId;
    document.getElementById('tenant-name').textContent = tenantName;
    document.getElementById('communicationModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('communicationModal').classList.add('hidden');
    document.getElementById('communicationForm').reset();
    document.body.style.overflow = 'auto';
}

// History modal functions
function showTenantHistory(tenantId) {
    // Show loading
    document.getElementById('historyContent').innerHTML = `
        <div class="flex justify-center py-12">
            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
            <span class="ml-2 text-sm" style="color: var(--text-secondary);">Loading history...</span>
        </div>
    `;
    
    document.getElementById('historyModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load tenant history via AJAX
    fetch(`/landlord/tenants/${tenantId}/history`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let historyHtml = '';
                if (data.history && data.history.length > 0) {
                    data.history.forEach(item => {
                        historyHtml += `
                            <div class="p-4 mb-3 rounded-lg border" style="border-color: var(--border-color); background: var(--card-bg);">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-medium" style="color: var(--text-primary);">${item.title || 'Activity'}</h4>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">${item.description || ''}</p>
                                    </div>
                                    <div class="text-xs ml-4 whitespace-nowrap" style="color: var(--text-secondary);">
                                        ${item.timestamp || ''}
                                    </div>
                                </div>
                                ${item.details ? `<p class="text-xs mt-2 p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); color: var(--text-secondary);">${item.details}</p>` : ''}
                            </div>
                        `;
                    });
                } else {
                    historyHtml = `
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-history text-xl" style="color: var(--secondary);"></i>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">No history found for this tenant</p>
                        </div>
                    `;
                }
                document.getElementById('historyContent').innerHTML = historyHtml;
            } else {
                document.getElementById('historyContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--danger);"></i>
                        <p class="text-sm" style="color: var(--danger);">Failed to load history</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">${data.message || 'Please try again'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('historyContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2" style="color: var(--danger);"></i>
                    <p class="text-sm" style="color: var(--danger);">Error loading history</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Please check your connection</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Lease Templates Modal
function showLeaseTemplates() {
    document.getElementById('leaseTemplatesModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLeaseTemplatesModal() {
    document.getElementById('leaseTemplatesModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function downloadTemplate(templateType) {
    // This would typically make an API call to generate/download a template
    alert('Downloading ' + templateType + ' lease template...');
    // In production, this would be: window.location.href = `/landlord/lease-templates/download/${templateType}`;
}

function resetFilters() {
    window.location.href = "{{ route('landlord.tenants.index') }}";
}

// Handle communication form submission
document.getElementById('communicationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    
    // You need to implement the actual API endpoint for sending messages
    fetch('/landlord/tenants/send-message', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Message sent successfully!');
            closeModal();
        } else {
            alert('Error: ' + (data.message || 'Failed to send message'));
        }
    })
    .catch(error => {
        alert('Error sending message');
        console.error('Error:', error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Search input debouncing
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (e.target.value.length === 0 || e.target.value.length >= 2) {
            e.target.closest('form').submit();
        }
    }, 500);
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeHistoryModal();
        closeLeaseTemplatesModal();
    }
});

// Close modals on outside click
document.getElementById('communicationModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('historyModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeHistoryModal();
    }
});

document.getElementById('leaseTemplatesModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLeaseTemplatesModal();
    }
});

// Export confirmation
document.querySelector('a[href*="export"]')?.addEventListener('click', function(e) {
    if (!confirm('Export tenant data to CSV?')) {
        e.preventDefault();
    }
});

// Highlight create lease button on hover
document.querySelectorAll('a[href*="create-lease"]').forEach(button => {
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 8px 25px rgba(var(--success-rgb), 0.3)';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
    });
});
</script>

<style>
/* Consistent modal styling with property units blade */
#communicationModal, #historyModal, #leaseTemplatesModal {
    animation: modalFadeIn 0.3s ease-out;
    z-index: 9999;
}

.theme-modal-compact {
    width: 95vw;
    max-width: 500px;
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

/* Create Lease Button Gradient */
.create-lease-btn {
    background: linear-gradient(135deg, var(--success) 0%, rgba(var(--success-rgb), 0.9) 100%);
    color: white;
    border: 1px solid var(--success);
    transition: all 0.3s ease;
}

.create-lease-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(var(--success-rgb), 0.3);
}

/* Form Styles - Fixed search icon positioning */
.form-input, .form-select, .form-textarea {
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

/* Specifically style search inputs with icons */
.form-input[style*="padding-left"] {
    padding-left: 2.5rem !important;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Position icons inside inputs */
.relative .absolute.inset-y-0.left-0 {
    padding-left: 0.75rem;
    z-index: 10;
}

.relative .absolute.inset-y-0.left-0 .fas {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Clear search button */
.relative .absolute.inset-y-0.right-0 {
    padding-right: 0.75rem;
    z-index: 10;
}

.relative .absolute.inset-y-0.right-0 button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-secondary);
    transition: color 0.2s;
}

.relative .absolute.inset-y-0.right-0 button:hover {
    color: var(--danger);
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

/* Dropdown menu styles */
.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background-color: var(--card-bg);
    min-width: 200px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    z-index: 1000;
    border: 1px solid var(--border-color);
    overflow: hidden;
    margin-top: 8px;
    backdrop-filter: blur(10px);
}

.dropdown-menu.show {
    display: block;
    animation: fadeIn 0.2s ease;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    color: var(--text-primary);
    transition: all 0.3s;
    font-size: 14px;
    border-bottom: 1px solid rgba(var(--primary-rgb), 0.1);
    cursor: pointer;
    text-decoration: none;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    padding-left: 20px;
}

/* Pagination Styling */
.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-input);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination .page-link:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination .page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: var(--bg-secondary);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: 1;
    }
    
    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .flex.flex-wrap {
        gap: 0.5rem;
    }
    
    .card .p-6 {
        padding: 1rem;
    }
    
    .text-lg {
        font-size: 1rem;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
    
    .text-3xl {
        font-size: 1.75rem;
    }
    
    /* Stack action buttons on mobile */
    .flex.flex-wrap.justify-end.gap-3 {
        justify-content: stretch;
    }
    
    .flex.flex-wrap.justify-end.gap-3 button,
    .flex.flex-wrap.justify-end.gap-3 a {
        flex: 1;
        min-width: 0;
        justify-content: center;
        margin-bottom: 0.5rem;
    }
    
    /* Make dropdown button full width on mobile */
    .relative.inline-block {
        width: 100%;
    }
    
    .relative.inline-block button {
        width: 100%;
    }
    
    /* Adjust icon padding for mobile */
    .form-input[style*="padding-left"] {
        padding-left: 2.25rem !important;
    }
    
    .relative .absolute.inset-y-0.left-0 {
        padding-left: 0.5rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .space-y-6 {
        gap: 1rem;
    }
    
    .border.p-5 {
        padding: 1rem;
    }
    
    /* Stack buttons vertically on very small screens */
    .flex.flex-wrap.gap-3 {
        flex-direction: column;
    }
    
    .flex.flex-wrap.gap-3 > * {
        width: 100%;
    }
    
    /* Make stats cards more compact */
    .p-4.rounded-lg {
        padding: 0.75rem;
    }
    
    /* Adjust create lease button for mobile */
    .create-lease-btn {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }
}

/* Dark theme adjustments */
[data-theme="dark"] .form-input,
[data-theme="dark"] .form-select,
[data-theme="dark"] .form-textarea {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

[data-theme="dark"] .form-input:focus,
[data-theme="dark"] .form-select:focus,
[data-theme="dark"] .form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
}

/* Ensure all text is visible in dark mode */
[data-theme="dark"] .text-sm,
[data-theme="dark"] .text-xs,
[data-theme="dark"] .text-lg,
[data-theme="dark"] .text-xl {
    color: inherit !important;
}

/* Animation keyframes */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Highlight animation for new features */
@keyframes highlightPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(var(--success-rgb), 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(var(--success-rgb), 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(var(--success-rgb), 0);
    }
}

.highlight-pulse {
    animation: highlightPulse 2s infinite;
}

/* Transition utilities */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.duration-200 {
    transition-duration: 200ms;
}

.duration-300 {
    transition-duration: 300ms;
}

/* Hover shadow effects */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.hover\:shadow-xl:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

/* Lease specific styling */
.lease-card {
    position: relative;
    overflow: hidden;
}

.lease-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--success) 0%, rgba(var(--success-rgb), 0.5) 100%);
}

/* Tooltip styling */
[title] {
    position: relative;
}

[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    border: 1px solid var(--border-color);
    margin-bottom: 4px;
}
</style>
@endsection