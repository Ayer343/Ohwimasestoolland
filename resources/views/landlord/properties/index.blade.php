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
                <a href="{{ route('properties.trash.index') }}" class="btn-warning flex items-center">
                    <i class="fas fa-trash mr-2"></i> Trash ({{ \App\Models\Property::onlyTrashed()->count() }})
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
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Statistics Cards - Updated to include Under Construction count -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-building text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ \App\Models\Property::count() }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Active</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ \App\Models\Property::where('status', 'active')->count() }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-hard-hat text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Under Construction</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ \App\Models\Property::where('status', 'under_construction')->count() }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">With Digital Address</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ \App\Models\Property::hasDigitalAddress()->count() }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- ==================== FIXED: UNIQUE LANDLORDS CARD ==================== -->
        <!-- Counts BOTH legacy landlords (type=2) AND users with landlord role -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                    <i class="fas fa-users text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Unique Landlords</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @php
                            // FIX: Count landlords by BOTH legacy type AND role
                            $uniqueLandlordsCount = \App\Models\User::where(function($query) {
                                $query->where('type', \App\Models\User::TYPE_LANDLORD) // Legacy landlords
                                      ->orWhereHas('roles', function($q) {              // Role-based landlords
                                          $q->where('slug', 'landlord');
                                      });
                            })->count();
                        @endphp
                        {{ $uniqueLandlordsCount }}
                    </p>
                    @php
                        // Get breakdown for tooltip
                        $legacyCount = \App\Models\User::where('type', \App\Models\User::TYPE_LANDLORD)
                            ->whereDoesntHave('roles', function($q) {
                                $q->where('slug', 'landlord');
                            })->count();
                        
                        $roleBasedCount = \App\Models\User::whereHas('roles', function($q) {
                            $q->where('slug', 'landlord');
                        })->count();
                        
                        $multiRoleLandlords = \App\Models\User::whereHas('roles', function($q) {
                            $q->where('slug', 'landlord');
                        })->where('type', '!=', \App\Models\User::TYPE_LANDLORD)->count();
                    @endphp
                    <p class="text-xs mt-1" style="color: var(--text-secondary);" title="Legacy: {{ $legacyCount }} | Role-based: {{ $roleBasedCount }} | Multi-role: {{ $multiRoleLandlords }}">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ $roleBasedCount }} with role, {{ $legacyCount }} legacy
                        @if($multiRoleLandlords > 0)
                            <span class="text-purple-600">({{ $multiRoleLandlords }} multi-role)</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- SIMPLIFIED FILTERS CARD -->
    <div class="card p-6">
        <form method="GET" action="{{ route('properties.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

        @if(request()->hasAny(['street_name', 'status', 'has_digital_address']))
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
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Info</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Location</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Registration Details</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Landlord</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($properties as $property)
                    <tr class="border-b transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    @if($property->status === 'under_construction')
                                        <i class="fas fa-hard-hat text-yellow-600"></i>
                                    @else
                                        <i class="fas fa-home text-blue-600"></i>
                                    @endif
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
                            
                            <!-- Show construction details if under construction -->
                            @if($property->status === 'under_construction' && $property->construction_status)
                                <p class="text-xs mt-2" style="color: var(--warning);">
                                    <i class="fas fa-hard-hat mr-1"></i> Construction: {{ ucfirst(str_replace('_', ' ', $property->construction_status)) }}
                                </p>
                                @if($property->estimated_completion)
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Est. Completion: {{ \Carbon\Carbon::parse($property->estimated_completion)->format('M d, Y') }}
                                    </p>
                                @endif
                            @endif
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
                                    <!-- Show role badge if landlord has multiple roles -->
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
                            @php
                                $statusColors = [
                                    'active' => ['bg' => 'success', 'icon' => 'check-circle'],
                                    'inactive' => ['bg' => 'secondary', 'icon' => 'pause-circle'],
                                    'under_maintenance' => ['bg' => 'warning', 'icon' => 'tools'],
                                    'vacant' => ['bg' => 'info', 'icon' => 'door-open'],
                                    'under_construction' => ['bg' => 'warning', 'icon' => 'hard-hat']
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
                            
                            <!-- Show tenant count if any -->
                            @if($property->tenants_count > 0)
                            <p class="text-xs mt-1" style="color: var(--info);">
                                <i class="fas fa-users mr-1"></i>
                                {{ $property->tenants_count }} tenant(s)
                                @if($property->active_tenant_count)
                                    ({{ $property->active_tenant_count }} active)
                                @endif
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-1">
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
                            <div class="mt-2 flex space-x-1">
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
                                @if($property->status === 'under_construction')
                                    <a href="{{ route('properties.construction-update', $property->id) }}" class="p-1 text-xs rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Update Construction Status">
                                        <i class="fas fa-hard-hat"></i>
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

    // =============================================
    // TRASH COUNT LIVE UPDATE FUNCTIONALITY
    // =============================================
    
    // Function to update trash count badge
    function updateTrashCount() {
        // Check if trash count badge exists
        const trashBadge = document.querySelector('.trash-count-badge');
        if (!trashBadge) return;
        
        // Get the CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        fetch('/properties/trash/count', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count;
                
                // Update badge text
                trashBadge.textContent = count;
                
                // Show/hide badge based on count
                if (count > 0) {
                    trashBadge.style.display = 'inline-flex';
                    trashBadge.style.opacity = '1';
                    
                    // Add pulse animation if there are new items
                    if (parseInt(trashBadge.getAttribute('data-previous-count') || 0) < count) {
                        trashBadge.classList.add('animate-pulse');
                        setTimeout(() => {
                            trashBadge.classList.remove('animate-pulse');
                        }, 1000);
                    }
                    
                    // Store current count for next comparison
                    trashBadge.setAttribute('data-previous-count', count);
                } else {
                    trashBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error updating trash count:', error);
        });
    }
    
    // Initial trash count update
    updateTrashCount();
    
    // Update trash count every 30 seconds
    let updateInterval = setInterval(updateTrashCount, 30000);
    
    // Optional: Update trash count when page becomes visible again (user returns to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateTrashCount();
        }
    });
    
    // Optional: Update trash count after restore/delete actions
    // Listen for custom events from restore/delete operations
    document.addEventListener('trashUpdated', function() {
        updateTrashCount();
    });
    
    // Clean up interval when page is unloaded
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
});
</script>

<style>
/* Trash badge styles */
.trash-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 8px;
    font-size: 11px;
    font-weight: 600;
    background-color: var(--danger, #dc2626);
    color: white;
    border-radius: 9999px;
    transition: all 0.2s ease;
}

/* Pulse animation for new items */
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-pulse {
    animation: pulse 0.5s ease-in-out;
}
</style>
@endsection