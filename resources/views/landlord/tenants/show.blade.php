{{-- resources/views/landlord/tenants/show.blade.php --}}
@php
    // Dynamic title
    $pageTitle = 'Tenant Details: ' . $tenant->name;
    $isLandlord = auth()->user()->isLandlord();
    
    // Get any success/error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Determine route prefix
    $routePrefix = 'landlord';
    
    // Get assigned units
    $assignedUnits = $tenant->propertyUnits ?? collect();
    
    // Calculate statistics
    $stats = [
        'total_invoices' => $tenant->invoices->count() ?? 0,
        'paid_invoices' => $tenant->invoices->where('status', 'paid')->count() ?? 0,
        'pending_invoices' => $tenant->invoices->whereIn('status', ['pending', 'overdue'])->count() ?? 0,
        'total_maintenance' => $tenant->maintenanceRequests->count() ?? 0,
        'active_maintenance' => $tenant->maintenanceRequests->whereIn('status', ['pending', 'in_progress'])->count() ?? 0,
    ];
@endphp

@extends('layouts.landlord')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Tenant Avatar -->
                <div class="mr-4">
                    @if($tenant->photo)
                        <img class="w-16 h-16 rounded-full object-cover border-2" 
                             src="{{ Storage::url($tenant->photo) }}" 
                             alt="{{ $tenant->name }}"
                             style="border-color: var(--primary);">
                    @else
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            {{ substr($tenant->name, 0, 2) }}
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2" style="color: var(--primary);"></i> 
                        {{ $tenant->name }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-id-card mr-2"></i>
                        <span>Tenant ID: {{ $tenant->id }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Joined {{ $tenant->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('landlord.tenants.index') }}" 
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Tenants
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

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Tenant Information Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> 
                            Tenant Information
                        </h3>
                        <button onclick="toggleEditMode()" 
                                class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium" 
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-edit mr-2"></i> Edit Details
                        </button>
                    </div>
                    
                    <form id="tenantForm" method="POST" action="{{ route('landlord.tenants.update', $tenant->id) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Personal Information -->
                            <div class="space-y-4">
                                <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                                    <i class="fas fa-user mr-2"></i> Personal Details
                                </h4>
                                
                                <!-- Name -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-signature mr-1"></i> Full Name
                                    </label>
                                    <input type="text" 
                                           name="name" 
                                           value="{{ old('name', $tenant->name) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter full name"
                                           required>
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-envelope mr-1"></i> Email Address
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           value="{{ old('email', $tenant->email) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter email address"
                                           required>
                                </div>
                                
                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-phone mr-1"></i> Phone Number
                                    </label>
                                    <input type="text" 
                                           name="phone" 
                                           value="{{ old('phone', $tenant->phone) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter phone number"
                                           required>
                                </div>
                                
                                <!-- Gender -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-venus-mars mr-1"></i> Gender
                                    </label>
                                    <select name="gender" class="form-select w-full">
                                        <option value="male" {{ old('gender', $tenant->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender', $tenant->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('gender', $tenant->gender) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Additional Information -->
                            <div class="space-y-4">
                                <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                                    <i class="fas fa-id-card mr-2"></i> Additional Details
                                </h4>
                                
                                <!-- National ID -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-id-card-alt mr-1"></i> National ID
                                    </label>
                                    <input type="text" 
                                           name="national_id" 
                                           value="{{ old('national_id', $tenant->national_id) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter national ID">
                                </div>
                                
                                <!-- Digital Address -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-map-marker-alt mr-1"></i> Digital Address
                                    </label>
                                    <input type="text" 
                                           name="digital_address" 
                                           value="{{ old('digital_address', $tenant->digital_address) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter digital address">
                                </div>
                                
                                <!-- Employment Status -->
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-briefcase mr-1"></i> Employment Status
                                    </label>
                                    <select name="employment_status" class="form-select w-full">
                                        <option value="employed" {{ old('employment_status', $tenant->employment_status) == 'employed' ? 'selected' : '' }}>Employed</option>
                                        <option value="self_employed" {{ old('employment_status', $tenant->employment_status) == 'self_employed' ? 'selected' : '' }}>Self Employed</option>
                                        <option value="student" {{ old('employment_status', $tenant->employment_status) == 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="unemployed" {{ old('employment_status', $tenant->employment_status) == 'unemployed' ? 'selected' : '' }}>Unemployed</option>
                                        <option value="retired" {{ old('employment_status', $tenant->employment_status) == 'retired' ? 'selected' : '' }}>Retired</option>
                                    </select>
                                </div>
                                
                                <!-- Monthly Income -->
                                @if($tenant->employment_status == 'employed' || $tenant->employment_status == 'self_employed')
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-money-bill-wave mr-1"></i> Monthly Income (GHS)
                                    </label>
                                    <input type="number" 
                                           name="monthly_income" 
                                           value="{{ old('monthly_income', $tenant->monthly_income) }}" 
                                           class="form-input w-full"
                                           placeholder="Enter monthly income"
                                           step="0.01"
                                           min="0">
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Emergency Contact -->
                        <div class="pt-6 border-t" style="border-color: var(--border-color);">
                            <h4 class="font-medium text-sm uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                                <i class="fas fa-phone-emergency mr-2"></i> Emergency Contact
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Contact Name
                                    </label>
                                    <input type="text" 
                                           name="emergency_contact_name" 
                                           value="{{ old('emergency_contact_name', $tenant->emergency_contact['name'] ?? '') }}" 
                                           class="form-input w-full"
                                           placeholder="Emergency contact name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Contact Phone
                                    </label>
                                    <input type="text" 
                                           name="emergency_contact_phone" 
                                           value="{{ old('emergency_contact_phone', $tenant->emergency_contact['phone'] ?? '') }}" 
                                           class="form-input w-full"
                                           placeholder="Emergency contact phone">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Relationship
                                    </label>
                                    <input type="text" 
                                           name="emergency_contact_relationship" 
                                           value="{{ old('emergency_contact_relationship', $tenant->emergency_contact['relationship'] ?? '') }}" 
                                           class="form-input w-full"
                                           placeholder="Relationship">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                            <button type="button" 
                                    onclick="toggleEditMode()" 
                                    class="px-4 py-2 rounded-lg text-sm font-medium mr-3" 
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg hover:shadow-xl transition-all duration-200" 
                                    style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assigned Units Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-home mr-2" style="color: var(--success);"></i> 
                            Assigned Property Units
                        </h3>
                        <span class="text-sm px-3 py-1 rounded-full" 
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-building mr-1"></i> {{ $assignedUnits->count() }} units
                        </span>
                    </div>
                    
                    @if($assignedUnits->count() > 0)
                        <div class="space-y-4">
                            @foreach($assignedUnits as $unit)
                                <div class="border rounded-lg p-4 transition-all duration-200 hover:shadow-md" 
                                     style="border-color: var(--border-color); background-color: var(--card-bg);">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div class="mb-3 md:mb-0">
                                            <div class="flex items-center mb-2">
                                                <h4 class="font-semibold text-base mr-3" style="color: var(--text-primary);">
                                                    <a href="{{ route('property-units.show', $unit->id) }}" 
                                                       class="hover:underline hover:text-primary">
                                                        Unit {{ $unit->unit_number }}
                                                        @if($unit->unit_name)
                                                            <span class="font-normal">({{ $unit->unit_name }})</span>
                                                        @endif
                                                    </a>
                                                </h4>
                                                @php
                                                    $statusColor = match($unit->tenant_status) {
                                                        \App\Models\PropertyUnit::TENANT_STATUS_APPROVED => 'success',
                                                        \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL => 'warning',
                                                        \App\Models\PropertyUnit::TENANT_STATUS_REJECTED => 'danger',
                                                        \App\Models\PropertyUnit::TENANT_STATUS_VACATED => 'secondary',
                                                        default => 'info'
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $statusColor }}">
                                                    {{ ucfirst(str_replace('_', ' ', $unit->tenant_status)) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-building mr-2"></i>
                                                <span>{{ $unit->property->property_name ?? 'N/A' }}</span>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-money-bill-wave mr-1"></i>
                                                <span>GHS {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}/month</span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if($unit->tenant_move_in_date)
                                            <div class="text-sm" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                Move-in: {{ $unit->tenant_move_in_date->format('M d, Y') }}
                                            </div>
                                            @endif
                                            <a href="{{ route('property-units.show', $unit->id) }}" 
                                               class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                <i class="fas fa-eye mr-1"></i> View Unit
                                            </a>
                                        </div>
                                    </div>
                                    
                                    @if($unit->currentLease)
                                    <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                                        <div class="flex items-center justify-between text-sm">
                                            <div>
                                                <span class="font-medium" style="color: var(--text-primary);">
                                                    <i class="fas fa-file-contract mr-1"></i> Current Lease
                                                </span>
                                                <span style="color: var(--text-secondary);">
                                                    {{ $unit->currentLease->start_date->format('M d, Y') }} - {{ $unit->currentLease->end_date->format('M d, Y') }}
                                                </span>
                                            </div>
                                            <span class="px-2 py-1 rounded-full text-xs badge-success">
                                                {{ $unit->currentLease->status }}
                                            </span>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-home text-xl" style="color: var(--secondary);"></i>
                            </div>
                            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Units Assigned</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                This tenant is not currently assigned to any property unit.
                            </p>
                            <a href="{{ route('landlord.property-units.index') }}" 
                               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium" 
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-plus mr-2"></i> Assign to Unit
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-history mr-2" style="color: var(--info);"></i> 
                            Recent Activity
                        </h3>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-sync-alt mr-1"></i> Last 30 days
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        @php
                            $activities = $tenant->activities()->latest()->take(5)->get();
                        @endphp
                        
                        @if($activities->count() > 0)
                            @foreach($activities as $activity)
                                <div class="flex items-start space-x-3 p-3 rounded-lg border" 
                                     style="border-color: var(--border-color); background-color: var(--card-bg);">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            @switch($activity->type)
                                                @case('payment_made')
                                                    <i class="fas fa-money-bill-wave"></i>
                                                    @break
                                                @case('maintenance_request')
                                                    <i class="fas fa-tools"></i>
                                                    @break
                                                @case('lease_signed')
                                                    <i class="fas fa-file-signature"></i>
                                                    @break
                                                @default
                                                    <i class="fas fa-bell"></i>
                                            @endswitch
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $activity->description }}
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $activity->created_at->diffForHumans() }}
                                        </p>
                                        @if($activity->metadata)
                                        <div class="mt-2 text-xs p-2 rounded-lg" 
                                             style="background-color: rgba(var(--secondary-rgb), 0.05); color: var(--text-secondary);">
                                            {{ json_encode($activity->metadata) }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-8">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                                     style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    <i class="fas fa-history text-xl" style="color: var(--secondary);"></i>
                                </div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    No recent activity found for this tenant.
                                </p>
                            </div>
                        @endif
                    </div>
                    
                    @if($activities->count() > 0)
                    <div class="text-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <a href="{{ route('landlord.tenants.history', $tenant->id) }}" 
                           class="text-sm font-medium inline-flex items-center" 
                           style="color: var(--primary);">
                            <i class="fas fa-list mr-2"></i> View Full Activity History
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Statistics Card -->
            <div class="card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> 
                            Tenant Statistics
                        </h3>
                        <i class="fas fa-info-circle text-sm" style="color: var(--text-secondary);"></i>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Total Invoices -->
                        <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Invoices</p>
                                    <p class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_invoices'] }}</p>
                                </div>
                                <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-file-invoice text-lg" style="color: var(--primary);"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Paid Invoices -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--success-rgb), 0.1);">
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Paid</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--success);">{{ $stats['paid_invoices'] }}</p>
                            </div>
                            
                            <!-- Pending Invoices -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                                        <i class="fas fa-clock" style="color: var(--warning);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Pending</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--warning);">{{ $stats['pending_invoices'] }}</p>
                            </div>
                            
                            <!-- Maintenance Requests -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <i class="fas fa-tools" style="color: var(--info);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Maintenance</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--info);">{{ $stats['total_maintenance'] }}</p>
                            </div>
                            
                            <!-- Active Maintenance -->
                            <div class="p-4 rounded-lg transition-all duration-200 hover:shadow-md" 
                                 style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                                        <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">Active</span>
                                </div>
                                <p class="text-xl font-bold" style="color: var(--danger);">{{ $stats['active_maintenance'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-rocket mr-2" style="color: var(--primary);"></i> 
                        Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- Send Message -->
                        <button onclick="showCommunicationModal()"
                                class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border w-full text-left"
                                style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div>
                                <span class="font-medium block">Send Message</span>
                                <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                    Email, SMS, or WhatsApp
                                </span>
                            </div>
                            <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                        </button>
                        
                        <!-- Create Invoice -->
                        <a href="{{ route('invoices.create', ['tenant_id' => $tenant->id]) }}" 
                           class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                           style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <span class="font-medium block">Create Invoice</span>
                                <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                    Generate new invoice
                                </span>
                            </div>
                            <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        <!-- View Documents -->
                        <button onclick="showDocumentsModal()"
                                class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border w-full text-left"
                                style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <span class="font-medium block">View Documents</span>
                                <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                    ID, Contracts, etc.
                                </span>
                            </div>
                            <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                        </button>
                        
                        <!-- View Financials -->
                        <a href="{{ route('landlord.tenants.financials', $tenant->id) }}" 
                           class="flex items-center p-3 rounded-lg transition-all duration-200 hover:shadow-md border"
                           style="border-color: var(--border-color); color: var(--text-primary); background-color: var(--card-bg);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <span class="font-medium block">Financial Report</span>
                                <span class="text-xs block mt-1" style="color: var(--text-secondary);">
                                    Payment history & reports
                                </span>
                            </div>
                            <i class="fas fa-chevron-right ml-auto" style="color: var(--text-secondary);"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tenant Status Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-check mr-2" style="color: var(--success);"></i> 
                        Tenant Status
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Current Status -->
                        @php
                            $primaryUnit = $assignedUnits->first();
                            $statusColor = $primaryUnit ? match($primaryUnit->tenant_status) {
                                \App\Models\PropertyUnit::TENANT_STATUS_APPROVED => 'success',
                                \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL => 'warning',
                                \App\Models\PropertyUnit::TENANT_STATUS_REJECTED => 'danger',
                                \App\Models\PropertyUnit::TENANT_STATUS_VACATED => 'secondary',
                                default => 'info'
                            } : 'secondary';
                        @endphp
                        
                        <div class="p-4 rounded-lg text-center" 
                             style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); border: 1px solid rgba(var(--{{ $statusColor }}-rgb), 0.3);">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                                 style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2);">
                                <i class="fas fa-2x fa-user-check" style="color: var(--{{ $statusColor }});"></i>
                            </div>
                            <h4 class="text-lg font-bold mb-2" style="color: var(--{{ $statusColor }});">
                                {{ $primaryUnit ? ucfirst(str_replace('_', ' ', $primaryUnit->tenant_status)) : 'Not Assigned' }}
                            </h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @if($primaryUnit)
                                    @if($primaryUnit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                                        Tenant is active and paying rent
                                    @elseif($primaryUnit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
                                        Awaiting admin approval
                                    @elseif($primaryUnit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_VACATED)
                                        Tenant has vacated the property
                                    @elseif($primaryUnit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_REJECTED)
                                        Tenant application was rejected
                                    @endif
                                @else
                                    No property unit assigned
                                @endif
                            </p>
                        </div>
                        
                        <!-- Last Updated -->
                        <div class="text-center">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i>
                                Last updated {{ $tenant->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        
                        <!-- Status Actions -->
                        @if($primaryUnit && $primaryUnit->tenant_status == \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="space-y-2">
                                <a href="{{ route('property-units.mark-vacated-form', $primaryUnit->id) }}" 
                                   class="block w-full px-4 py-2 rounded-lg text-sm font-medium text-center"
                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-address-book mr-2" style="color: var(--info);"></i> 
                        Contact Information
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Email -->
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Email Address</p>
                                <p class="text-sm truncate" style="color: var(--text-secondary);">{{ $tenant->email }}</p>
                            </div>
                            <a href="mailto:{{ $tenant->email }}" 
                               class="p-2 rounded-lg" 
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                               title="Send Email">
                                <i class="fas fa-paper-plane"></i>
                            </a>
                        </div>
                        
                        <!-- Phone -->
                        @if($tenant->phone)
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Phone Number</p>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $tenant->phone }}</p>
                            </div>
                            <a href="tel:{{ $tenant->phone }}" 
                               class="p-2 rounded-lg" 
                               style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                               title="Call">
                                <i class="fas fa-phone-alt"></i>
                            </a>
                        </div>
                        @endif
                        
                        <!-- Digital Address -->
                        @if($tenant->digital_address)
                        <div class="flex items-center p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Digital Address</p>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $tenant->digital_address }}</p>
                            </div>
                            <a href="https://maps.google.com/?q={{ urlencode($tenant->digital_address) }}" 
                               target="_blank"
                               class="p-2 rounded-lg" 
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                               title="View on Map">
                                <i class="fas fa-map-marked-alt"></i>
                            </a>
                        </div>
                        @endif
                        
                        <!-- Emergency Contact -->
                        @if($tenant->emergency_contact)
                        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-phone-emergency mr-2" style="color: var(--danger);"></i> 
                                Emergency Contact
                            </h4>
                            <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $tenant->emergency_contact['name'] ?? 'N/A' }}
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-phone mr-1"></i>
                                    {{ $tenant->emergency_contact['phone'] ?? 'N/A' }}
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-user-friends mr-1"></i>
                                    {{ $tenant->emergency_contact['relationship'] ?? 'N/A' }}
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

<!-- Communication Modal -->
<div id="communicationModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-comment mr-2" style="color: var(--primary);"></i> 
                    Send Message to {{ $tenant->name }}
                </h3>
                <button type="button" 
                        onclick="closeModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="communicationForm" method="POST" action="{{ route('landlord.tenants.send-message', $tenant->id) }}" class="theme-modal-body-compact">
                @csrf
                
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

<!-- Documents Modal -->
<div id="documentsModal" class="fixed inset-0 z-50 hidden" style="padding: 1rem;">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeDocumentsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="theme-modal-compact">
            <!-- Modal Header -->
            <div class="theme-modal-header-compact">
                <h3 class="flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-alt mr-2" style="color: var(--info);"></i> 
                    Tenant Documents
                </h3>
                <button type="button" 
                        onclick="closeDocumentsModal()" 
                        style="color: var(--text-secondary); background: none; border: none; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="theme-modal-body-compact" style="max-height: 400px; overflow-y: auto;">
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-folder-open text-xl" style="color: var(--info);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Documents</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        View and manage tenant documents.
                    </p>
                    <!-- Documents list will be loaded here -->
                    <p class="text-sm italic" style="color: var(--text-secondary);">
                        No documents uploaded yet.
                    </p>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="theme-modal-footer-compact">
                <button onclick="closeDocumentsModal()" 
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
    // Initialize form in read-only mode
    disableFormEditing();
    
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
    
    // Handle form submission
    const tenantForm = document.getElementById('tenantForm');
    if (tenantForm) {
        tenantForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            
            fetch(this.action, {
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
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update tenant'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                alert('Error updating tenant');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                console.error('Error:', error);
            });
        });
    }
});

let isEditMode = false;

function toggleEditMode() {
    const form = document.getElementById('tenantForm');
    const inputs = form.querySelectorAll('input, select, textarea');
    const editBtn = document.querySelector('button[onclick="toggleEditMode()"]');
    
    isEditMode = !isEditMode;
    
    if (isEditMode) {
        // Enable editing
        inputs.forEach(input => {
            input.disabled = false;
            input.readOnly = false;
            input.classList.remove('bg-gray-100');
        });
        
        editBtn.innerHTML = '<i class="fas fa-times mr-2"></i> Cancel Edit';
        editBtn.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
        editBtn.style.color = 'var(--danger)';
        editBtn.style.borderColor = 'rgba(var(--danger-rgb), 0.3)';
    } else {
        // Disable editing
        disableFormEditing();
        
        editBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Edit Details';
        editBtn.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
        editBtn.style.color = 'var(--primary)';
        editBtn.style.borderColor = 'rgba(var(--primary-rgb), 0.3)';
    }
}

function disableFormEditing() {
    const form = document.getElementById('tenantForm');
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.disabled = true;
        input.readOnly = true;
        input.classList.add('bg-gray-100');
    });
}

// Communication modal functions
function showCommunicationModal() {
    document.getElementById('communicationModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('communicationModal').classList.add('hidden');
    document.getElementById('communicationForm').reset();
    document.body.style.overflow = 'auto';
}

// Documents modal functions
function showDocumentsModal() {
    document.getElementById('documentsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeDocumentsModal() {
    document.getElementById('documentsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDocumentsModal();
    }
});

// Close modals on outside click
document.getElementById('communicationModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('documentsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDocumentsModal();
    }
});

// Handle communication form submission
document.getElementById('communicationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    
    fetch(this.action, {
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
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        alert('Error sending message');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error('Error:', error);
    });
});
</script>

<style>
/* Consistent modal styling */
#communicationModal, #documentsModal {
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

.form-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: 1;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
    }
    
    .card .p-6 {
        padding: 1rem;
    }
    
    .text-lg {
        font-size: 1rem;
    }
    
    .text-xl {
        font-size: 1.125rem;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
    
    /* Stack contact info on mobile */
    .flex.items-center.p-3.rounded-lg {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .flex.items-center.p-3.rounded-lg > div {
        margin-bottom: 0.5rem;
    }
    
    .flex.items-center.p-3.rounded-lg > a {
        align-self: flex-end;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .space-y-6 {
        gap: 1rem;
    }
    
    .border.p-5 {
        padding: 1rem;
    }
    
    /* Reduce padding on cards */
    .p-4.rounded-lg {
        padding: 0.75rem;
    }
    
    /* Make avatar smaller on mobile */
    .w-16.h-16 {
        width: 3rem;
        height: 3rem;
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

[data-theme="dark"] .form-input:disabled,
[data-theme="dark"] .form-select:disabled,
[data-theme="dark"] .form-textarea:disabled {
    background-color: rgba(var(--text-secondary-rgb), 0.1);
    color: var(--text-secondary);
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

/* Hover shadow effects */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.hover\:shadow-xl:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

/* Read-only form styling */
.bg-gray-100 {
    background-color: var(--bg-secondary) !important;
}

/* Gradient backgrounds */
.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--primary), var(--secondary));
}
</style>
@endsection