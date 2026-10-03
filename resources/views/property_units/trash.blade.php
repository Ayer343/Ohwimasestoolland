{{-- property_units/trash.blade.php --}}
@php
    // Dynamic layout based on user role
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
    }
    
    // Get statistics
    $totalTrashed = $trashedUnits->total();
    $emptyTrashUrl = route($routePrefix . '.empty-trash');
    
    // Check if there are any success or error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $warningMessage = session('warning');
@endphp

@extends($layout)

@section('title', 'Trash / Recycle Bin')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #ff6b6b 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash mr-2" style="color: var(--danger);"></i> 
                        Trash / Recycle Bin
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage deleted property units. Items are automatically deleted after 30 days.</span>
                        @if($totalTrashed > 0)
                        <span class="mx-2">•</span>
                        <i class="fas fa-box mr-1"></i>
                        <span>{{ $totalTrashed }} deleted unit{{ $totalTrashed > 1 ? 's' : '' }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route($routePrefix . '.index') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> Back to Units
                </a>
            </div>
        </div>
    </div>

    <!-- Success Messages -->
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

    <!-- Error Messages -->
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

    @if($warningMessage)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">Warning!</span>
            <span class="ml-2">{{ $warningMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Warning Alert -->
    <div class="card border-l-4 mb-6" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Important Information
                    </h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        Items in the trash bin are temporarily stored and will be automatically <strong>permanently deleted after 30 days</strong>. 
                        You can restore items or permanently delete them immediately.
                    </p>
                    <div class="flex items-center space-x-4 text-sm">
                        <span class="flex items-center">
                            <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i>
                            <span style="color: var(--text-secondary);">Restore: Bring back to active units</span>
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                            <span style="color: var(--text-secondary);">Permanent Delete: Cannot be undone</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($totalTrashed > 0)
    <div class="card mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-tasks mr-2"></i> Bulk Actions
                    </h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Perform actions on all deleted units at once.
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="showEmptyTrashConfirmation()" 
                            class="px-4 py-2 rounded-lg font-medium text-white btn-danger">
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                    </button>
                    <button onclick="restoreAll()" 
                            class="px-4 py-2 rounded-lg font-medium btn-success">
                        <i class="fas fa-undo-alt mr-2"></i> Restore All
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route($routePrefix . '.trash') }}" class="space-y-4">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ request('search') }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Unit number, name, tenant..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Property Filter -->
                        @if($properties && $properties->count() > 0)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <!-- Unit Type Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-home mr-1"></i> Unit Type
                            </label>
                            <select name="unit_type" class="index-custom-dropdown w-full">
                                <option value="">All Types</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" {{ request('unit_type') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Sorting -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort" class="index-custom-dropdown w-full">
                                <option value="deleted_at" {{ request('sort', 'deleted_at') == 'deleted_at' ? 'selected' : '' }}>Deletion Date</option>
                                <option value="unit_number" {{ request('sort') == 'unit_number' ? 'selected' : '' }}>Unit Number</option>
                                <option value="monthly_rent" {{ request('sort') == 'monthly_rent' ? 'selected' : '' }}>Monthly Rent</option>
                                <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Creation Date</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-sort-amount-down mr-1"></i> Sort Direction
                            </label>
                            <select name="direction" class="index-custom-dropdown w-full">
                                <option value="desc" {{ request('direction', 'desc') == 'desc' ? 'selected' : '' }}>Newest First</option>
                                <option value="asc" {{ request('direction') == 'asc' ? 'selected' : '' }}>Oldest First</option>
                            </select>
                        </div>

                        <!-- Quick Actions -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" 
                                        class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route($routePrefix . '.trash') }}" 
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <!-- View Toggle -->
                                <div class="flex items-center justify-center pt-2" style="background-color: rgba(var(--secondary-rgb), 0.1); border-radius: 0.375rem; padding: 0.25rem;">
                                    <button id="tableViewBtn" class="px-3 py-1 rounded-md" style="background-color: var(--card-bg);">
                                        <i class="fas fa-table" style="color: var(--primary);"></i>
                                    </button>
                                    <button id="cardViewBtn" class="px-3 py-1 rounded-md">
                                        <i class="fas fa-th-large" style="color: var(--text-secondary);"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Units Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <!-- Table Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Deleted Units ({{ $totalTrashed }})
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($totalTrashed > 0)
                                Showing {{ $trashedUnits->firstItem() }} to {{ $trashedUnits->lastItem() }} of {{ $totalTrashed }} deleted units
                            @else
                                No deleted units found
                            @endif
                        </p>
                    </div>
                    
                    @if($totalTrashed > 0)
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        <!-- Items per page -->
                        <div class="flex items-center">
                            <span class="text-sm mr-2" style="color: var(--text-secondary);">Show:</span>
                            <select onchange="updatePerPage(this.value)" 
                                    class="index-custom-dropdown text-sm pl-3 pr-8 py-1 rounded-lg appearance-none">
                                <option value="10" {{ request('per_page', '20') == '10' ? 'selected' : '' }}>10</option>
                                <option value="20" {{ request('per_page', '20') == '20' ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>
                    @endif
                </div>

                @if($totalTrashed == 0)
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-trash text-2xl" style="color: var(--danger);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            Trash Bin is Empty
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            No deleted property units found. Items you delete will appear here before being permanently removed.
                        </p>
                        <div class="space-x-3">
                            <a href="{{ route($routePrefix . '.index') }}" 
                               class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-building mr-2"></i> View Active Units
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Table View -->
                    <div id="tableView" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Unit Details</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Property</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted By</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted At</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trashedUnits as $unit)
                                    <tr>
                                        <!-- Unit Details -->
                                        <td class="p-3">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                    <i class="fas fa-home" style="color: var(--danger);"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">
                                                        {{ $unit->unit_number }} 
                                                        @if($unit->unit_name)
                                                            - {{ $unit->unit_name }}
                                                        @endif
                                                    </div>
                                                    <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-home mr-1"></i>
                                                        <span class="mr-2">{{ $unit->unit_type_label ?? $unit->unit_type }}</span>
                                                        <i class="fas fa-money-bill-wave mr-1 ml-2"></i>
                                                        <span>GHS {{ number_format($unit->monthly_rent, 2) }}</span>
                                                    </div>
                                                    @if($unit->tenant)
                                                    <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <span>Tenant: {{ $unit->tenant->name }}</span>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Property -->
                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->property->property_name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $unit->property->address ?? '' }}
                                            </div>
                                        </td>
                                        
                                        <!-- Deleted By -->
                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->creator->name ?? 'System' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                @if($unit->creator)
                                                    {{ $unit->creator->email }}
                                                @endif
                                            </div>
                                        </td>
                                        
                                        <!-- Deleted At -->
                                        <td class="p-3">
                                            <div style="color: var(--text-primary);">
                                                {{ $unit->deleted_at->format('M d, Y') }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $unit->deleted_at->format('h:i A') }}
                                                @php
                                                    $daysDeleted = $unit->deleted_at->diffInDays(now());
                                                @endphp
                                                <div class="mt-1">
                                                    @if($daysDeleted < 7)
                                                        <span class="px-2 py-1 rounded-full text-xs badge-success">
                                                            Deleted {{ $daysDeleted }} day{{ $daysDeleted != 1 ? 's' : '' }} ago
                                                        </span>
                                                    @elseif($daysDeleted < 30)
                                                        <span class="px-2 py-1 rounded-full text-xs badge-warning">
                                                            Deleted {{ $daysDeleted }} days ago
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-1 rounded-full text-xs badge-danger">
                                                            Deleted {{ $daysDeleted }} days ago
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="p-3">
                                            <div class="action-buttons">
                                                <!-- Restore Button -->
                                                <button onclick="showRestoreConfirmation({{ $unit->id }}, '{{ addslashes($unit->unit_number) }}')" 
                                                        class="action-btn restore"
                                                        data-tooltip="Restore this unit">
                                                    <i class="fas fa-undo-alt"></i>
                                                    <span class="hidden md:inline">Restore</span>
                                                </button>
                                                
                                                <!-- Permanent Delete Button -->
                                                <button onclick="showForceDeleteConfirmation({{ $unit->id }}, '{{ addslashes($unit->unit_number) }}')" 
                                                        class="action-btn delete"
                                                        data-tooltip="Permanently delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                    <span class="hidden md:inline">Delete</span>
                                                </button>
                                                
                                                <!-- View Details -->
                                                <a href="{{ route($routePrefix . '.show', $unit->id) }}" 
                                                   class="action-btn view"
                                                   target="_blank"
                                                   data-tooltip="View details">
                                                    <i class="fas fa-eye"></i>
                                                    <span class="hidden md:inline">View</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Card View (Hidden by default) -->
                    <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($trashedUnits as $unit)
                            <div class="card border" style="border-color: var(--border-color);">
                                <div class="p-4">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                                <i class="fas fa-home" style="color: var(--danger);"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $unit->unit_number }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    {{ $unit->property->property_name ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                        @php
                                            $daysDeleted = $unit->deleted_at->diffInDays(now());
                                        @endphp
                                        @if($daysDeleted < 7)
                                            <span class="px-2 py-1 rounded-full text-xs badge-success">
                                                {{ $daysDeleted }}d
                                            </span>
                                        @elseif($daysDeleted < 30)
                                            <span class="px-2 py-1 rounded-full text-xs badge-warning">
                                                {{ $daysDeleted }}d
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs badge-danger">
                                                {{ $daysDeleted }}d
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-home mr-2"></i>
                                            {{ $unit->unit_type_label ?? $unit->unit_type }}
                                        </div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-money-bill-wave mr-2"></i>
                                            GHS {{ number_format($unit->monthly_rent, 2) }}
                                        </div>
                                        @if($unit->tenant)
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-2"></i>
                                            {{ $unit->tenant->name }}
                                        </div>
                                        @endif
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-trash mr-2"></i>
                                            {{ $unit->deleted_at->format('M d, Y') }}
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button onclick="showRestoreConfirmation({{ $unit->id }}, '{{ addslashes($unit->unit_number) }}')" 
                                                class="action-btn restore">
                                            <i class="fas fa-undo-alt"></i> Restore
                                        </button>
                                        <button onclick="showForceDeleteConfirmation({{ $unit->id }}, '{{ addslashes($unit->unit_number) }}')" 
                                                class="action-btn delete">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $trashedUnits->firstItem() }} to {{ $trashedUnits->lastItem() }} of {{ $totalTrashed }} entries
                        </div>
                        <div class="pagination">
                            {{ $trashedUnits->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Empty Trash Confirmation Modal -->
<div id="emptyTrashModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEmptyTrashModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Empty Trash Bin
                </h3>
                <button type="button" onclick="hideEmptyTrashModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="{{ $emptyTrashUrl }}" id="emptyTrashForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold" style="color: var(--danger);">Warning!</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    You are about to permanently delete ALL items in the trash bin.
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="font-medium text-center mb-2" style="color: var(--danger);">
                                This action will affect {{ $totalTrashed }} unit{{ $totalTrashed > 1 ? 's' : '' }}
                            </p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>All deleted units will be permanently removed</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>This action cannot be undone</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Associated data (invoices, maintenance requests) will also be deleted</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="mb-4">
                            <label for="empty_confirmation" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Type "EMPTY TRASH" to confirm:
                            </label>
                            <input type="text" 
                                   id="empty_confirmation" 
                                   name="confirm_empty_trash" 
                                   class="index-custom-input w-full"
                                   placeholder="Type EMPTY TRASH here"
                                   oninput="checkEmptyConfirmation(this)">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);" id="emptyConfirmationMessage">
                                Type exactly "EMPTY TRASH" (without quotes) to enable the button
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideEmptyTrashModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="confirmEmptyBtn"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white opacity-50 cursor-not-allowed"
                            disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRestoreModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i> Restore Unit
                </h3>
                <button type="button" onclick="hideRestoreModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="" id="restoreForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-undo-alt text-lg" style="color: var(--success);"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold" style="color: var(--success);">Restore Unit</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    You are about to restore a deleted unit.
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <p class="font-medium text-center mb-2" id="restoreUnitNumber" style="color: var(--info);">
                                <!-- Will be populated by JavaScript -->
                            </p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <span>Unit will be restored to active status</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <span>Any existing tenant assignments will be preserved</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                    <span>Unit will appear in the main units list</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p class="mb-2">Are you sure you want to restore this unit?</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRestoreModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-undo-alt mr-2"></i> Restore Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Force Delete Confirmation Modal -->
<div id="forceDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideForceDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Permanent Deletion
                </h3>
                <button type="button" onclick="hideForceDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="" id="forceDeleteForm">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                <i class="fas fa-exclamation-triangle text-lg" style="color: var(--danger);"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold" style="color: var(--danger);">Permanent Deletion</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    You are about to permanently delete a unit.
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="font-medium text-center mb-2" id="deleteUnitNumber" style="color: var(--danger);">
                                <!-- Will be populated by JavaScript -->
                            </p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>This action cannot be undone</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>All unit data will be permanently deleted</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Associated invoices, maintenance requests, and documents will be deleted</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Tenant data associated with this unit will be preserved</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="mb-4">
                            <label for="force_delete_confirmation" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Type "DELETE PERMANENTLY" to confirm:
                            </label>
                            <input type="text" 
                                   id="force_delete_confirmation" 
                                   name="confirm_force_delete" 
                                   class="index-custom-input w-full"
                                   placeholder="Type DELETE PERMANENTLY here"
                                   oninput="checkForceDeleteConfirmation(this)">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);" id="forceDeleteConfirmationMessage">
                                Type exactly "DELETE PERMANENTLY" (without quotes) to enable the button
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideForceDeleteModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="confirmForceDeleteBtn"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white opacity-50 cursor-not-allowed"
                            disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize table/card view toggle
    initViewToggle();
    
    // Initialize tooltips
    initTooltips();
    
    // Auto-hide success and error messages
    autoHideMessages();
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideEmptyTrashModal();
            hideRestoreModal();
            hideForceDeleteModal();
        }
    });
});

function initViewToggle() {
    const tableViewBtn = document.getElementById('tableViewBtn');
    const cardViewBtn = document.getElementById('cardViewBtn');
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    
    if (tableViewBtn && cardViewBtn) {
        // Check localStorage for saved view preference
        const savedView = localStorage.getItem('propertyUnitsTrashView') || 'table';
        
        if (savedView === 'card') {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            tableViewBtn.style.backgroundColor = 'transparent';
            tableViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            cardViewBtn.style.backgroundColor = 'var(--card-bg)';
            cardViewBtn.querySelector('i').style.color = 'var(--primary)';
        } else {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            tableViewBtn.style.backgroundColor = 'var(--card-bg)';
            tableViewBtn.querySelector('i').style.color = 'var(--primary)';
            cardViewBtn.style.backgroundColor = 'transparent';
            cardViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
        }
        
        tableViewBtn.addEventListener('click', function() {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            tableViewBtn.style.backgroundColor = 'var(--card-bg)';
            tableViewBtn.querySelector('i').style.color = 'var(--primary)';
            cardViewBtn.style.backgroundColor = 'transparent';
            cardViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            localStorage.setItem('propertyUnitsTrashView', 'table');
        });
        
        cardViewBtn.addEventListener('click', function() {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            tableViewBtn.style.backgroundColor = 'transparent';
            tableViewBtn.querySelector('i').style.color = 'var(--text-secondary)';
            cardViewBtn.style.backgroundColor = 'var(--card-bg)';
            cardViewBtn.querySelector('i').style.color = 'var(--primary)';
            localStorage.setItem('propertyUnitsTrashView', 'card');
        });
    }
}

function initTooltips() {
    // Simple tooltip implementation
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    elementsWithTooltip.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed z-50 px-2 py-1 text-xs rounded-lg';
            tooltip.style.backgroundColor = 'var(--card-bg)';
            tooltip.style.color = 'var(--text-primary)';
            tooltip.style.border = '1px solid var(--border-color)';
            tooltip.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.id = 'tooltip-' + Date.now();
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
            tooltip.style.top = (rect.bottom + 5) + 'px';
            
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
}

function autoHideMessages() {
    // Auto-hide success and error messages after 5 seconds
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.bg-green-100');
        successMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
        
        const errorMessages = document.querySelectorAll('.bg-red-100');
        errorMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
        
        const warningMessages = document.querySelectorAll('.bg-yellow-100');
        warningMessages.forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.display = 'none';
            }
        });
    }, 5000);
}

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page'); // Go back to first page
    window.location.href = url.toString();
}

// Empty Trash Modal Functions
function showEmptyTrashConfirmation() {
    const modal = document.getElementById('emptyTrashModal');
    const confirmationInput = document.getElementById('empty_confirmation');
    const confirmBtn = document.getElementById('confirmEmptyBtn');
    
    // Reset form
    confirmationInput.value = '';
    confirmBtn.disabled = true;
    confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Focus on confirmation input
    setTimeout(() => {
        confirmationInput.focus();
    }, 100);
}

function hideEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function checkEmptyConfirmation(input) {
    const confirmBtn = document.getElementById('confirmEmptyBtn');
    const messageElement = document.getElementById('emptyConfirmationMessage');
    
    if (confirmBtn && messageElement) {
        if (input.value === 'EMPTY TRASH') {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--success)';
            messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now empty trash.';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--danger)';
            messageElement.innerHTML = `Type exactly "EMPTY TRASH" (without quotes) to enable the button. You typed: "${input.value}"`;
        }
    }
}

// Restore Modal Functions
function showRestoreConfirmation(unitId, unitNumber) {
    const modal = document.getElementById('restoreModal');
    const form = document.getElementById('restoreForm');
    const unitNumberElement = document.getElementById('restoreUnitNumber');
    
    // Set the correct route
    @if($isLandlord)
        form.action = '/landlord/property-units/' + unitId + '/restore-from-trash';
    @else
        form.action = '/property-units/' + unitId + '/restore-from-trash';
    @endif
    
    // Update unit number display
    if (unitNumberElement) {
        unitNumberElement.textContent = 'Unit: ' + unitNumber;
    }
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRestoreModal() {
    const modal = document.getElementById('restoreModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Force Delete Modal Functions
function showForceDeleteConfirmation(unitId, unitNumber) {
    const modal = document.getElementById('forceDeleteModal');
    const form = document.getElementById('forceDeleteForm');
    const confirmationInput = document.getElementById('force_delete_confirmation');
    const confirmBtn = document.getElementById('confirmForceDeleteBtn');
    const unitNumberElement = document.getElementById('deleteUnitNumber');
    
    // Set the correct route
    @if($isLandlord)
        form.action = '/landlord/property-units/' + unitId + '/force-delete';
    @else
        form.action = '/property-units/' + unitId + '/force-delete';
    @endif
    
    // Update unit number display
    if (unitNumberElement) {
        unitNumberElement.textContent = 'Unit: ' + unitNumber;
    }
    
    // Reset form
    confirmationInput.value = '';
    confirmBtn.disabled = true;
    confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Focus on confirmation input
    setTimeout(() => {
        confirmationInput.focus();
    }, 100);
}

function hideForceDeleteModal() {
    const modal = document.getElementById('forceDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function checkForceDeleteConfirmation(input) {
    const confirmBtn = document.getElementById('confirmForceDeleteBtn');
    const messageElement = document.getElementById('forceDeleteConfirmationMessage');
    
    if (confirmBtn && messageElement) {
        if (input.value === 'DELETE PERMANENTLY') {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--success)';
            messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now delete permanently.';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            messageElement.style.color = 'var(--danger)';
            messageElement.innerHTML = `Type exactly "DELETE PERMANENTLY" (without quotes) to enable the button. You typed: "${input.value}"`;
        }
    }
}

// Bulk Actions
function restoreAll() {
    if (confirm('Are you sure you want to restore ALL deleted units?')) {
        // This would require a new endpoint for bulk restore
        // For now, we'll show a message
        alert('Bulk restore feature is not yet implemented. Please restore units individually.');
    }
}
</script>

<style>
/* Index-specific form control styles with dark mode support */
.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Update SVG icon color for dark/light mode */
[data-theme="dark"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

[data-theme="light"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown option {
    background-color: var(--card-bg);
    color: var(--text-primary);
}

/* Dark mode dropdown options */
[data-theme="dark"] .index-custom-dropdown option {
    background-color: #2a2a3c;
    color: #e4e4e4;
}

/* Light mode dropdown options */
[data-theme="light"] .index-custom-dropdown option {
    background-color: #ffffff;
    color: #4b4b4b;
}

/* Custom input styles for index page */
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-input:disabled {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Custom textarea styles for index page */
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom checkbox styles for index page */
.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.index-custom-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Placeholder color for inputs */
.index-custom-input::placeholder,
.index-custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Focus states for better accessibility */
.index-custom-dropdown:focus-visible,
.index-custom-input:focus-visible,
.index-custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Table styles - COMPLETELY REMOVE ALL WHITE HOVER EFFECTS */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

table tr:last-child td {
    border-bottom: none;
}

/* REMOVE ALL WHITE HOVER EFFECTS from table rows */
table tr {
    background-color: var(--card-bg) !important;
}

table tr:hover td,
table tr:hover {
    background-color: var(--card-bg) !important;
}

/* Card view styles */
#cardView .card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background-color: var(--card-bg);
}

#cardView .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

/* Badge styles */
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

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    border: 1px solid var(--success) !important;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #28a745 !important;
    transform: translateY(-1px);
}

.btn-modern {
    background: linear-gradient(to right, var(--primary), var(--secondary)) !important;
    color: white !important;
    border: none !important;
    transition: all 0.2s ease;
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

/* Modal styles */
.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-close-btn {
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: background-color 0.2s;
    cursor: pointer;
    background: none;
    border: none;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Action buttons in table */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
}

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}

.action-btn.view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.edit {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.action-btn.edit:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.assign:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.restore {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.restore:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn.vacate {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.action-btn.vacate:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.3);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.5);
}

/* Pagination styles */
.pagination .pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 0.25rem;
}

.pagination .pagination li a,
.pagination .pagination li span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.pagination .pagination li a:hover:not(.disabled) {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.pagination .pagination li.active span {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .pagination li.disabled span {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .lg\\:col-span-1 {
        margin-bottom: 1.5rem;
    }
    
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    #cardView {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: flex-start;
    }
    
    .action-btn span {
        display: inline !important;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
}

/* Animation for card view */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#cardView .card {
    animation: fadeIn 0.3s ease-out;
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Status indicator */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-indicator::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    display: inline-block;
}

.status-available {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-available::before {
    background-color: var(--success);
}

.status-occupied {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.status-occupied::before {
    background-color: var(--primary);
}

.status-under_maintenance {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-under_maintenance::before {
    background-color: var(--warning);
}

.status-unavailable {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-unavailable::before {
    background-color: var(--danger);
}
</style>
@endsection