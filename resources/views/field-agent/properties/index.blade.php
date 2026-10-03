@extends('layouts.field')

@section('title', 'My Properties & Assignments')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                My Properties & Assignments
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('field-agent.properties.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-plus mr-2"></i> Register New Property
                </a>
                <div class="flex items-center px-3 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <i class="fas fa-user-check mr-2"></i>
                    Field Agent - Registration Access
                </div>
            </div>
        </div>
    </div>

    <!-- Field Agent Notice -->
    <div class="card p-6 border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-green-500 text-xl mr-3"></i>
            <div>
                <h3 class="font-semibold text-green-800 dark:text-green-300">Property Registration Access</h3>
                <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                    You can register new properties and edit properties you've registered. You also have access to view all properties in your assigned registration plans, including those created by administrators.
                </p>
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

    @php
        $user = auth()->user();
        $myRegisteredProperties = \App\Models\Property::where('registered_by', $user->id)->count();
        $myActiveProperties = \App\Models\Property::where('registered_by', $user->id)->where('status', 'active')->count();
        $myPropertiesWithDigitalAddress = \App\Models\Property::where('registered_by', $user->id)->hasDigitalAddress()->count();
        $myUniqueZones = \App\Models\Property::where('registered_by', $user->id)->distinct('zone')->count('zone');
        
        // Get all properties in assigned plans (including those created by admins)
        $assignedPlanIds = $user->activePlanAssignments()->pluck('plan_id');
        $totalInAssignedPlans = \App\Models\Property::whereIn('registration_plan_id', $assignedPlanIds)->count();
        $activeInAssignedPlans = \App\Models\Property::whereIn('registration_plan_id', $assignedPlanIds)->where('status', 'active')->count();
        $withDigitalInAssignedPlans = \App\Models\Property::whereIn('registration_plan_id', $assignedPlanIds)->hasDigitalAddress()->count();
    @endphp

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- My Registered Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-user-edit text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">My Registered Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $myRegisteredProperties }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Properties I registered
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Properties in My Plans -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-tasks text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Properties in My Plans</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalInAssignedPlans }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Total in assigned plans
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Active Properties -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Active Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $activeInAssignedPlans }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Active in assigned plans
                    </p>
                </div>
            </div>
        </div>
        
        <!-- With Digital Address -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-teal-100 text-teal-600 mr-4">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">With Digital Address</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $withDigitalInAssignedPlans }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        In assigned plans
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Progress Section -->
    @php
        $activeAssignments = $user->activePlanAssignments()->with('plan')->get();
    @endphp
    
    @if($activeAssignments->count() > 0)
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tasks mr-2 text-blue-500"></i>My Assignment Progress
            </h3>
            <span class="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                {{ $activeAssignments->count() }} Active Assignment(s)
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($activeAssignments as $assignment)
            @php
                $propertiesInPlan = \App\Models\Property::where('registration_plan_id', $assignment->plan_id)->count();
                $progress = $assignment->plan->estimated_houses > 0 
                    ? min(100, ($propertiesInPlan / $assignment->plan->estimated_houses) * 100)
                    : 0;
                $progressColor = $progress >= 80 ? 'bg-green-600' : ($progress >= 50 ? 'bg-blue-600' : 'bg-yellow-600');
            @endphp
            <div class="border rounded-lg p-4 hover:shadow-md transition-shadow" style="border-color: var(--border-color);">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-medium" style="color: var(--text-primary);">
                        {{ $assignment->plan->zone }} 
                        @if($assignment->plan->section)
                            - {{ $assignment->plan->section }}
                        @endif
                    </h4>
                    <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                        {{ $propertiesInPlan }} registered
                    </span>
                </div>
                <p class="text-sm mb-3" style="color: var(--text-secondary);">
                    Pattern: <code class="text-xs bg-gray-100 px-1 rounded">{{ $assignment->plan->naming_pattern }}</code>
                </p>
                <div class="w-full bg-gray-200 rounded-full h-2.5" style="background-color: var(--bg-tertiary);">
                    <div class="h-2.5 rounded-full {{ $progressColor }}" style="width: {{ $progress }}%"></div>
                </div>
                <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                    <span>Progress: {{ number_format($progress, 1) }}%</span>
                    <span>{{ $propertiesInPlan }}/{{ $assignment->plan->estimated_houses }}</span>
                </div>
                <div class="mt-3 flex space-x-2">
                    <a href="{{ route('field-agent.properties.create', ['registration_plan_id' => $assignment->plan->id]) }}" 
                       class="flex-1 text-center py-2 rounded text-sm btn-primary">
                        <i class="fas fa-plus mr-1"></i> Add Property
                    </a>
                    <a href="{{ route('field-agent.properties.index', ['registration_plan_id' => $assignment->plan->id]) }}" 
                       class="flex-1 text-center py-2 rounded text-sm btn-secondary">
                        <i class="fas fa-list mr-1"></i> View
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="card p-6 text-center">
        <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
            <i class="fas fa-tasks text-4xl mb-4 opacity-50"></i>
            <p class="text-lg font-medium mb-2">No Active Assignments</p>
            <p class="text-sm mb-4">You don't have any active registration plan assignments yet.</p>
            <a href="{{ route('field-agent.registration-plans.index') }}" class="btn-primary flex items-center">
                <i class="fas fa-search mr-2"></i> Browse Available Plans
            </a>
        </div>
    </div>
    @endif

    <!-- Quick Actions Card -->
    @if($activeAssignments->count() > 0)
    <div class="card p-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-bolt text-yellow-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Quick Actions</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Quickly access your most used features
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($activeAssignments->take(3) as $assignment)
                <a href="{{ route('field-agent.properties.create', ['registration_plan_id' => $assignment->plan->id]) }}" 
                   class="btn-primary flex items-center text-sm py-2 px-3"
                   title="Register property for {{ $assignment->plan->zone }} - {{ $assignment->plan->section ?? 'No Section' }}">
                    <i class="fas fa-plus mr-1"></i> {{ $assignment->plan->zone }}
                </a>
                @endforeach
                <a href="{{ route('field-agent.properties.create') }}" 
                   class="btn-secondary flex items-center text-sm py-2 px-3">
                    <i class="fas fa-plus-circle mr-1"></i> New Property
                </a>
                @if($activeAssignments->count() > 3)
                <a href="{{ route('field-agent.registration-plans.index') }}" 
                   class="btn-outline flex items-center text-sm py-2 px-3">
                    <i class="fas fa-list-alt mr-1"></i> All Plans
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('field-agent.properties.index') }}" id="filterForm">
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
                        <option value="vacant" {{ request('status') == 'vacant' ? 'selected' : '' }}>Vacant</option>
                    </select>
                </div>
                <div>
                    <select name="has_digital_address" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Properties</option>
                        <option value="1" {{ request('has_digital_address') == '1' ? 'selected' : '' }}>Has Digital Address</option>
                        <option value="0" {{ request('has_digital_address') == '0' ? 'selected' : '' }}>No Digital Address</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('field-agent.properties.index') }}" class="flex-1 p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Advanced Filters -->
            <div id="advancedFilters" class="mt-4 hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <input type="text" name="house_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="House Number" value="{{ request('house_number') }}">
                </div>
                <div>
                    <input type="text" name="zone" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by zone..." value="{{ request('zone') }}">
                </div>
                <div>
                    <input type="text" name="digital_address" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by digital address..." value="{{ request('digital_address') }}">
                </div>
                <div>
                    <select name="registration_plan_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Registration Plans</option>
                        @foreach($registrationPlans as $plan)
                            <option value="{{ $plan->id }}" {{ request('registration_plan_id') == $plan->id ? 'selected' : '' }}>
                                {{ $plan->zone }} - {{ $plan->section ?? 'No Section' }} ({{ $plan->naming_pattern }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <input type="text" name="block_number" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Block Number" value="{{ request('block_number') }}">
                </div>
                <div>
                    <input type="text" name="property_name" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Property Name" value="{{ request('property_name') }}">
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button type="button" id="toggleFilters" class="text-sm" style="color: var(--primary);">
                    <i class="fas fa-caret-down mr-1"></i> Advanced Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Properties Table Card -->
    <div class="card p-6">
        <!-- Results Count and Actions -->
        <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $properties->firstItem() ?? 0 }} to {{ $properties->lastItem() ?? 0 }} of {{ $properties->total() }} properties
                @if(request('registration_plan_id'))
                    @php
                        $selectedPlan = $registrationPlans->firstWhere('id', request('registration_plan_id'));
                    @endphp
                    in <strong>{{ $selectedPlan ? $selectedPlan->zone . ' - ' . ($selectedPlan->section ?? 'No Section') : 'Selected Plan' }}</strong>
                @endif
            </p>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    Includes properties in your assigned plans
                </span>
            </div>
        </div>

        <!-- Active Filters -->
        @if(request()->hasAny(['street_name', 'status', 'has_digital_address', 'zone', 'digital_address', 'registration_plan_id', 'house_number', 'block_number', 'property_name']))
        <div class="mb-4 flex items-center flex-wrap gap-2">
            <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
            @if(request('street_name'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Street: {{ request('street_name') }}
            </span>
            @endif
            @if(request('status'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Status: {{ ucfirst(request('status')) }}
            </span>
            @endif
            @if(request('has_digital_address') !== null && request('has_digital_address') !== '')
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Digital Address: {{ request('has_digital_address') ? 'Has Address' : 'No Address' }}
            </span>
            @endif
            @if(request('zone'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Zone: {{ request('zone') }}
            </span>
            @endif
            @if(request('digital_address'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Digital: {{ request('digital_address') }}
            </span>
            @endif
            @if(request('registration_plan_id'))
            @php
                $selectedPlan = $registrationPlans->firstWhere('id', request('registration_plan_id'));
            @endphp
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Plan: {{ $selectedPlan ? $selectedPlan->zone . ' - ' . ($selectedPlan->section ?? 'No Section') : 'N/A' }}
            </span>
            @endif
            @if(request('house_number'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                House: {{ request('house_number') }}
            </span>
            @endif
            @if(request('block_number'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Block: {{ request('block_number') }}
            </span>
            @endif
            @if(request('property_name'))
            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                Name: {{ Str::limit(request('property_name'), 15) }}
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
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Registration Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($properties as $property)
                    <!-- REMOVED hover:bg-gray-50 dark:hover:bg-gray-700 classes to eliminate white hover effect -->
                    <tr class="border-b transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center 
                                    {{ $property->registered_by === auth()->id() ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600' }}">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</p>
                                    <div class="flex items-center space-x-2 mt-1">
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
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-road mr-1"></i>{{ $property->street_name }}
                                    </p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        @if($property->registered_by === auth()->id())
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                            <i class="fas fa-user-check mr-1"></i> Registered by You
                                        </span>
                                        @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                            <i class="fas fa-users mr-1"></i> In Your Plan
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            @if($property->zone && $property->section)
                                <p class="font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>
                                    {{ $property->zone }} - {{ $property->section }}
                                </p>
                            @endif
                            @if($property->digital_address)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> {{ $property->digital_address }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs mt-1" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> No Digital Address
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($property->registrationPlan)
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-map mr-1 text-blue-500"></i>
                                    {{ $property->registrationPlan->zone }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Pattern: <span class="font-mono font-bold">{{ $property->registration_pattern }}</span>
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Plan: {{ $property->registrationPlan->naming_pattern }}
                                </p>
                            @else
                                <span class="text-xs text-red-500">No Registration Plan</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'active' => ['bg' => 'success', 'icon' => 'check-circle'],
                                    'inactive' => ['bg' => 'secondary', 'icon' => 'pause-circle'],
                                    'under_maintenance' => ['bg' => 'warning', 'icon' => 'tools'],
                                    'vacant' => ['bg' => 'info', 'icon' => 'door-open']
                                ];
                                $statusConfig = $statusColors[$property->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2); color: var(--{{ $statusConfig['bg'] }});">
                                <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $property->status)) }}
                            </span>
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
                            <div class="flex space-x-1">
                                <!-- View Details Button -->
                                <a href="{{ route('field-agent.properties.show', $property->id) }}" class="p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button - Only for properties they registered -->
                                @if($property->registered_by === auth()->id())
                                <a href="{{ route('field-agent.properties.edit', $property->id) }}" class="p-2 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Edit Property">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @else
                                <span class="p-2 rounded-lg opacity-50 cursor-not-allowed" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" title="Can only edit properties you registered">
                                    <i class="fas fa-edit"></i>
                                </span>
                                @endif
                            </div>
                            <div class="mt-2 flex space-x-1">
                                @if($property->digital_address)
                                    <button type="button" class="p-1 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Copy Digital Address" onclick="copyToClipboard('{{ $property->digital_address }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-building text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No properties found</p>
                                <p class="text-sm mb-4">No properties match your current filters in your assigned plans.</p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('field-agent.properties.index') }}" class="btn-secondary flex items-center">
                                        <i class="fas fa-sync mr-2"></i> Clear Filters
                                    </a>
                                    <a href="{{ route('field-agent.properties.create') }}" class="btn-primary flex items-center">
                                        <i class="fas fa-plus mr-2"></i> Register Property
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($properties->hasPages())
        <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
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
    // Toggle advanced filters
    const toggleFiltersBtn = document.getElementById('toggleFilters');
    const advancedFilters = document.getElementById('advancedFilters');
    
    if (toggleFiltersBtn && advancedFilters) {
        toggleFiltersBtn.addEventListener('click', function() {
            if (advancedFilters.classList.contains('hidden')) {
                advancedFilters.classList.remove('hidden');
                toggleFiltersBtn.innerHTML = '<i class="fas fa-caret-up mr-1"></i> Hide Advanced Filters';
            } else {
                advancedFilters.classList.add('hidden');
                toggleFiltersBtn.innerHTML = '<i class="fas fa-caret-down mr-1"></i> Advanced Filters';
            }
        });
    }

    // Copy to clipboard function
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            const originalTitle = event.target.title;
            event.target.title = 'Copied!';
            event.target.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
            event.target.style.color = 'var(--success)';
            
            setTimeout(() => {
                event.target.title = originalTitle;
                event.target.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                event.target.style.color = 'var(--success)';
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            alert('Failed to copy to clipboard');
        });
    };
    
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
    
    // Show advanced filters if any advanced filter is already filled
    const advancedFilterInputs = [
        'input[name="zone"]',
        'input[name="digital_address"]',
        'select[name="registration_plan_id"]',
        'input[name="house_number"]',
        'input[name="block_number"]',
        'input[name="property_name"]'
    ];
    
    let hasAdvancedFilters = false;
    advancedFilterInputs.forEach(selector => {
        const element = document.querySelector(selector);
        if (element && element.value) {
            hasAdvancedFilters = true;
        }
    });
    
    if (hasAdvancedFilters && advancedFilters) {
        advancedFilters.classList.remove('hidden');
        if (toggleFiltersBtn) {
            toggleFiltersBtn.innerHTML = '<i class="fas fa-caret-up mr-1"></i> Hide Advanced Filters';
        }
    }
});
</script>
@endsection