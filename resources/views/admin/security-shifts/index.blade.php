@extends('layouts.app')

@section('title', 'Security Shifts')

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
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> 
                        Security Shifts
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i>
                        <span>Manage all security shift patterns and configurations</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                @if(request()->hasAny(['search', 'status', 'category', 'rotation_type', 'personnel']))
                <a href="{{ route('admin.security-shifts.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-undo mr-2"></i> Clear Filters
                </a>
                @endif
                
                <!-- Trash Link -->
                <a href="{{ route('admin.security-shifts.trash.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash Bin
                    @php
                        $trashedCount = \App\Models\SecurityShift::onlyTrashed()->count();
                    @endphp
                    @if($trashedCount > 0)
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $trashedCount }}
                    </span>
                    @endif
                </a>
                
                <a href="{{ route('admin.security-shifts.create') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-plus mr-2"></i> Create Shift
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Main Dashboard
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Schedules
                </a>
                
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-map-marker-alt mr-1"></i> Posts
                </a>
                
                <a href="{{ route('admin.users.index') }}?type=6" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-users mr-1"></i> Personnel
                </a>
                
                <a href="{{ route('admin.security-reports.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <!-- Total Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalShifts }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Shifts</div>
                </div>
            </div>
        </div>
        
        <!-- Active Today -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $activeToday }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Today</div>
                </div>
            </div>
        </div>
        
        <!-- Day Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-sun"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $dayShifts }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Day Shifts</div>
                </div>
            </div>
        </div>
        
        <!-- Night Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-moon"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $nightShifts }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Night Shifts</div>
                </div>
            </div>
        </div>
        
        <!-- Rotating Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--purple-rgb), 0.1); color: var(--purple);">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--purple);">{{ $rotatingShifts }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Rotating Shifts</div>
                </div>
            </div>
        </div>
        
        <!-- Total Personnel -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--teal-rgb), 0.1); color: var(--teal);">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--teal);">{{ $totalPersonnel }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Personnel</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                </h3>
                @if(request()->hasAny(['search', 'status', 'category', 'rotation_type', 'personnel']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, code, or description..."
                               value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active Only</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Category</label>
                        <select name="category" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Categories</option>
                            @foreach($shiftCategories as $value => $label)
                                <option value="{{ $value }}" {{ request('category') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Rotation Type</label>
                        <select name="rotation_type" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Types</option>
                            @foreach($rotationTypes as $value => $label)
                                <option value="{{ $value }}" {{ request('rotation_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <!-- Advanced Filters (Collapsible) -->
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-sliders-h mr-2"></i> Advanced Filters
                        </h4>
                        <button type="button" 
                                id="toggleAdvancedBtn"
                                class="text-xs flex items-center hover:text-primary transition-colors duration-200"
                                style="color: var(--text-secondary);">
                            <span>Show More</span>
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                    </div>
                    
                    <div id="advancedFilters" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Personnel Filter -->
                        <div>
                            <label class="block mb-1 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-users mr-1"></i> Required Personnel
                            </label>
                            <select name="personnel" 
                                    class="form-input w-full p-2 rounded-lg border"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="">Any Personnel</option>
                                <option value="1" {{ request('personnel') == '1' ? 'selected' : '' }}>1 Personnel</option>
                                <option value="2" {{ request('personnel') == '2' ? 'selected' : '' }}>2 Personnel</option>
                                <option value="3" {{ request('personnel') == '3' ? 'selected' : '' }}>3+ Personnel</option>
                            </select>
                        </div>

                        <!-- Sort By -->
                        <div>
                            <label class="block mb-1 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-sort mr-1"></i> Sort By
                            </label>
                            <select name="sort_by" 
                                    class="form-input w-full p-2 rounded-lg border"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="start_time" {{ request('sort_by', 'start_time') == 'start_time' ? 'selected' : '' }}>Start Time</option>
                                <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Name</option>
                                <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>Recently Created</option>
                                <option value="duration_hours" {{ request('sort_by') == 'duration_hours' ? 'selected' : '' }}>Duration</option>
                                <option value="required_personnel" {{ request('sort_by') == 'required_personnel' ? 'selected' : '' }}>Personnel Required</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label class="block mb-1 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-sort-amount-down mr-1"></i> Order
                            </label>
                            <select name="sort_order" 
                                    class="form-input w-full p-2 rounded-lg border"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="asc" {{ request('sort_order', 'asc') == 'asc' ? 'selected' : '' }}>Ascending</option>
                                <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Descending</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2 pt-4">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-shifts.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear All
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($shifts->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select shifts to perform actions on multiple items
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAllShifts()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAllShifts()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <select id="bulkActionSelect" 
                        class="form-input p-3 rounded-lg border col-span-1 md:col-span-2"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Choose action...</option>
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="button" 
                        onclick="performBulkAction()" 
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        id="bulkActionBtn">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
            
            <div id="bulkActionStatus" class="mt-3 hidden">
                <!-- Status messages will appear here -->
            </div>
        </div>
    </div>
    @endif

    <!-- Shifts Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Security Shifts
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $shifts->count() }} of {{ $shifts->total() }} shifts
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($shifts->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift Details</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Timing</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Schedule Type</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shifts as $shift)
                            <tr class="border-b transition-colors duration-150" 
                                style="border-color: var(--border-color);">
                                @if($shifts->count() > 0)
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="shift-checkbox bulk-checkbox" 
                                           value="{{ $shift->id }}">
                                </td>
                                @endif
                                <td class="py-3 px-4">
                                    <div class="flex items-start">
                                        <div class="p-2 rounded-lg mr-3 mt-1 shift-icon"
                                             style="background-color: {{ $shift->category == 'day' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                                    ($shift->category == 'night' ? 'rgba(var(--primary-rgb), 0.1)' : 
                                                                    ($shift->category == 'evening' ? 'rgba(var(--info-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)')) }};
                                                    color: {{ $shift->category == 'day' ? 'var(--warning)' : 
                                                            ($shift->category == 'night' ? 'var(--primary)' : 
                                                            ($shift->category == 'evening' ? 'var(--info)' : 'var(--secondary)')) }};">
                                            @if($shift->category == 'day')
                                                <i class="fas fa-sun"></i>
                                            @elseif($shift->category == 'night')
                                                <i class="fas fa-moon"></i>
                                            @elseif($shift->category == 'evening')
                                                <i class="fas fa-sunset"></i>
                                            @else
                                                <i class="fas fa-star"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-semibold flex items-center" style="color: var(--text-primary);">
                                                {{ $shift->name }}
                                                @if($shift->rotation_type === 'rotating')
                                                    <span class="ml-2 px-1.5 py-0.5 text-xs rounded-full" 
                                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        <i class="fas fa-sync-alt text-xs mr-1"></i>Rotating
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-hashtag mr-1 text-xs"></i> 
                                                <code>{{ $shift->code }}</code>
                                            </div>
                                            @if($shift->description)
                                                <div class="text-xs mt-1" style="color: var(--text-secondary); max-width: 300px;">
                                                    {{ Str::limit($shift->description, 80) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Timing -->
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }} - 
                                        {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }}
                                    </div>
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        {{ $shift->duration_hours }} hours
                                        @if($shift->is_overnight)
                                            <span class="ml-2 px-1.5 py-0.5 rounded text-xs" 
                                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                Overnight
                                            </span>
                                        @endif
                                    </div>
                                    @if($shift->break_schedule && $shift->break_schedule['has_break'])
                                        <div class="text-xs mt-1 flex items-center" style="color: var(--info);">
                                            <i class="fas fa-coffee mr-1"></i>
                                            {{ $shift->break_schedule['total_break_minutes'] ?? 0 }}min break(s)
                                        </div>
                                    @endif
                                </td>
                                
                                <!-- Schedule Type -->
                                <td class="py-3 px-4">
                                    @php
                                        $dayTypeLabels = [
                                            'all_days' => 'All Days',
                                            'weekday' => 'Weekdays',
                                            'weekend' => 'Weekends',
                                            'custom' => 'Custom Days'
                                        ];
                                    @endphp
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $dayTypeLabels[$shift->day_type] ?? ucfirst($shift->day_type) }}
                                    </div>
                                    @if($shift->day_type === 'custom' && $shift->applicable_days)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            @php
                                                $days = [
                                                    1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
                                                    5 => 'Fri', 6 => 'Sat', 7 => 'Sun'
                                                ];
                                                $applicable = array_map(function($day) use ($days) {
                                                    return $days[$day] ?? $day;
                                                }, $shift->applicable_days);
                                            @endphp
                                            {{ implode(', ', $applicable) }}
                                        </div>
                                    @endif
                                    @if($shift->rotation_type === 'rotating' && $shift->rotation_config)
                                        <div class="text-xs mt-1 flex items-center" style="color: var(--info);">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Rotates every {{ $shift->rotation_config['rotation_days'] }} days
                                        </div>
                                    @endif
                                </td>
                                
                                <!-- Personnel -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="text-lg font-semibold mr-2" style="color: var(--text-primary);">
                                            {{ $shift->required_personnel }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            personnel
                                        </div>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        @if($shift->schedules_count > 0)
                                            <i class="fas fa-calendar-check mr-1" style="color: var(--success);"></i>
                                            {{ $shift->schedules_count }} schedule(s)
                                        @else
                                            <i class="fas fa-calendar-times mr-1" style="color: var(--secondary);"></i>
                                            No schedules
                                        @endif
                                    </div>
                                </td>
                                
                                <!-- Status -->
                                <td class="py-3 px-4">
                                    <div class="relative">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium toggle-status-btn cursor-pointer"
                                              data-shift-id="{{ $shift->id }}"
                                              data-current-status="{{ $shift->is_active ? 'active' : 'inactive' }}"
                                              style="background-color: {{ $shift->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                                     color: {{ $shift->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                            {{ $shift->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <div class="status-tooltip hidden absolute top-full left-0 mt-1 p-2 rounded shadow-lg z-10"
                                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                Click to toggle status
                                            </div>
                                        </div>
                                    </div>
                                    @if($shift->handover_config && $shift->handover_config['has_handover'])
                                        <div class="text-xs mt-1 flex items-center" style="color: var(--info);">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            {{ $shift->handover_config['handover_duration'] }}min handover
                                        </div>
                                    @endif
                                </td>
                                
                                <!-- Actions -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <!-- View Button -->
                                        <a href="{{ route('admin.security-shifts.show', $shift) }}" 
                                           class="action-btn" 
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        
                                        <!-- Edit Button -->
                                        <a href="{{ route('admin.security-shifts.edit', $shift) }}" 
                                           class="action-btn" 
                                           title="Edit"
                                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        
                                        <!-- Delete Button -->
                                        <button onclick="showDeleteModal({{ $shift->id }}, '{{ addslashes($shift->name) }}')"
                                                class="action-btn" 
                                                title="Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $shifts->count() > 0 ? '7' : '6' }}" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-clock text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No security shifts found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters or create a new shift</p>
                                        <div class="mt-4">
                                            <a href="{{ route('admin.security-shifts.create') }}" 
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                <i class="fas fa-plus mr-2"></i> Create First Shift
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
            @if($shifts->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $shifts->firstItem() }} to {{ $shifts->lastItem() }} of {{ $shifts->total() }} shifts
                        </div>
                        <div class="flex space-x-2">
                            @if($shifts->onFirstPage())
                                <span class="px-3 py-2 rounded border text-sm" 
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            @else
                                <a href="{{ $shifts->previousPageUrl() }}" 
                                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            @endif

                            @foreach($shifts->getUrlRange(max(1, $shifts->currentPage() - 2), min($shifts->lastPage(), $shifts->currentPage() + 2)) as $page => $url)
                                @if($page == $shifts->currentPage())
                                    <span class="px-3 py-2 rounded text-sm font-medium"
                                          style="background-color: var(--primary); color: white;">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" 
                                       class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach

                            @if($shifts->hasMorePages())
                                <a href="{{ $shifts->nextPageUrl() }}" 
                                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            @else
                                <span class="px-3 py-2 rounded border text-sm" 
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Shift</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deleteShiftName">
                    <!-- Shift name will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);" id="deleteWarningText">
                    <!-- Warning text will be inserted here -->
                </p>
                <div id="activeSchedulesWarning" class="hidden p-3 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                        <span style="color: var(--danger);" id="schedulesCountText"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteModal')">
                Cancel
            </button>
            <form id="deleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmDelete()">
                <i class="fas fa-trash mr-2"></i> Delete Shift
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Bulk selection variables
let selectedShiftIds = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Status toggle functionality
    const toggleButtons = document.querySelectorAll('.toggle-status-btn');
    
    toggleButtons.forEach(button => {
        // Tooltip functionality
        button.addEventListener('mouseenter', function() {
            const tooltip = this.nextElementSibling;
            if (tooltip) {
                tooltip.classList.remove('hidden');
            }
        });
        
        button.addEventListener('mouseleave', function() {
            const tooltip = this.nextElementSibling;
            if (tooltip) {
                tooltip.classList.add('hidden');
            }
        });
        
        // Click to toggle status
        button.addEventListener('click', async function() {
            const shiftId = this.getAttribute('data-shift-id');
            const currentStatus = this.getAttribute('data-current-status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this shift?`)) {
                return;
            }
            
            try {
                showLoading(`Updating shift status...`);
                
                const response = await fetch(`{{ url('admin/security-shifts') }}/${shiftId}/toggle-status`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                hideLoading();
                
                if (data.success) {
                    // Update button appearance
                    this.setAttribute('data-current-status', newStatus);
                    
                    if (newStatus === 'active') {
                        this.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                        this.style.color = 'var(--success)';
                        this.textContent = 'Active';
                    } else {
                        this.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                        this.style.color = 'var(--danger)';
                        this.textContent = 'Inactive';
                    }
                    
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                hideLoading();
                console.error('Error toggling status:', error);
                showToast('Network error occurred', 'error');
            }
        });
    });
    
    // Toggle advanced filters
    const toggleAdvancedBtn = document.getElementById('toggleAdvancedBtn');
    if (toggleAdvancedBtn) {
        toggleAdvancedBtn.addEventListener('click', function() {
            const advancedFilters = document.getElementById('advancedFilters');
            const icon = this.querySelector('i');
            const text = this.querySelector('span');
            
            if (advancedFilters.classList.contains('hidden')) {
                advancedFilters.classList.remove('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                text.textContent = 'Show Less';
            } else {
                advancedFilters.classList.add('hidden');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                text.textContent = 'Show More';
            }
        });
    }
    
    // Auto-submit filters on change for better UX
    document.querySelectorAll('#filterForm select').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    // Initialize action buttons
    initializeActionButtons();
    
    // Bulk selection checkboxes
    initializeBulkSelection();
});

// Bulk selection functions
function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const shiftCheckboxes = document.querySelectorAll('.shift-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            shiftCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    shiftCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
        });
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.shift-checkbox:checked');
    const selectedCount = checkboxes.length;
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    if (selectedCount > 0) {
        bulkActionBtn.disabled = false;
        bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply (${selectedCount} selected)`;
    } else {
        bulkActionBtn.disabled = true;
        bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply`;
    }
}

function selectAllShifts() {
    const checkboxes = document.querySelectorAll('.shift-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
    
    updateSelectedCount();
}

function deselectAllShifts() {
    const checkboxes = document.querySelectorAll('.shift-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    
    updateSelectedCount();
}

async function performBulkAction() {
    const actionSelect = document.getElementById('bulkActionSelect');
    const selectedAction = actionSelect.value;
    
    if (!selectedAction) {
        showToast('Please select an action first', 'warning');
        return;
    }
    
    const checkboxes = document.querySelectorAll('.shift-checkbox:checked');
    if (checkboxes.length === 0) {
        showToast('Please select at least one shift', 'warning');
        return;
    }
    
    const shiftIds = Array.from(checkboxes).map(cb => cb.value);
    
    // Confirmations for destructive actions
    let confirmMessage = '';
    switch(selectedAction) {
        case 'delete':
            confirmMessage = `Are you sure you want to move ${shiftIds.length} shift(s) to trash?`;
            break;
        default:
            confirmMessage = `Are you sure you want to perform this action on ${shiftIds.length} shift(s)?`;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        showLoading(`Processing ${shiftIds.length} shift(s)...`);
        
        const response = await fetch(`{{ route('admin.security-shifts.bulk-action') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: selectedAction,
                ids: shiftIds
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            // Show success message
            showToast(data.message, 'success');
            
            // Clear selection
            deselectAllShifts();
            actionSelect.value = '';
            
            // Refresh page after successful bulk action
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            showToast(data.message || 'Failed to perform bulk action', 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Bulk action error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function showDeleteModal(shiftId, shiftName) {
    // Set shift name
    document.getElementById('deleteShiftName').textContent = shiftName;
    
    // Check if shift has active schedules
    showLoading('Checking shift usage...');
    
    try {
        const response = await fetch(`{{ route('admin.security-shifts.check-in-use') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ids: [shiftId]
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.in_use_count > 0) {
            document.getElementById('deleteWarningText').textContent = 
                'This shift cannot be deleted because it is currently in use by schedules or templates.';
            document.getElementById('activeSchedulesWarning').classList.remove('hidden');
            
            let usageDetails = [];
            if (data.used_shifts && data.used_shifts.length > 0) {
                const shift = data.used_shifts[0];
                usageDetails = [`Used in ${shift.schedules_count || 0} schedule(s)`];
            }
            
            document.getElementById('schedulesCountText').textContent = 
                `This shift is currently in use. ${usageDetails.join(' ')}`;
            
            // Disable delete button
            const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
            deleteBtn.disabled = true;
            deleteBtn.style.opacity = '0.5';
            deleteBtn.style.cursor = 'not-allowed';
        } else {
            document.getElementById('deleteWarningText').textContent = 
                'Are you sure you want to move this shift to trash? You can restore it later from the trash bin.';
            document.getElementById('activeSchedulesWarning').classList.add('hidden');
            
            // Enable delete button
            const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
            deleteBtn.disabled = false;
            deleteBtn.style.opacity = '1';
            deleteBtn.style.cursor = 'pointer';
        }
    } catch (error) {
        hideLoading();
        showToast('Error checking shift usage', 'error');
    }
    
    // Update delete form action
    const deleteForm = document.getElementById('deleteForm');
    deleteForm.action = `{{ url('admin/security-shifts') }}/${shiftId}`;
    
    openModal('deleteModal');
}

function confirmDelete() {
    const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
    if (!deleteBtn.disabled) {
        const deleteForm = document.getElementById('deleteForm');
        deleteForm.submit();
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function initializeActionButtons() {
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Loading functions
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay hidden';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
                <div class="text-white font-medium" id="loadingMessage">${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) {
        messageEl.textContent = message;
    }
    
    overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Keyboard shortcuts for bulk actions
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + A to select all shifts
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        if (document.querySelector('.shift-checkbox')) {
            selectAllShifts();
        }
    }
    
    // Esc to deselect all
    if (e.key === 'Escape') {
        const anySelected = document.querySelector('.shift-checkbox:checked');
        if (anySelected) {
            e.preventDefault();
            deselectAllShifts();
        }
    }
});
</script>

<style>
/* Bulk selection styles */
.bulk-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s ease;
}

.bulk-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.bulk-checkbox:checked::after {
    content: '✓';
    color: white;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

/* Shift icon */
.shift-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Card styles */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
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

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Form elements */
.form-input, .form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay .animate-spin {
    animation: spin 1s linear infinite;
    border-top-color: transparent;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

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

/* Action button styles */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.status-tooltip {
    min-width: 120px;
    white-space: nowrap;
}

.toggle-status-btn:hover {
    transform: scale(1.05);
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

/* Toast animations */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.toast-slide-in {
    animation: slideIn 0.3s ease forwards;
}

.toast-slide-out {
    animation: slideOut 0.3s ease forwards;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.3);
}

/* Focus states for accessibility */
.form-input:focus-visible,
.form-select:focus-visible,
button:focus-visible,
a:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-6 {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .grid.grid-cols-1.md\:grid-cols-3,
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-6 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.25rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
    }
    
    .text-4xl {
        font-size: 2rem !important;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Required field indicator */
.required-field::after {
    content: " *";
    color: var(--danger);
}

/* Validation message */
.validation-message {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: none;
}

.error-field ~ .validation-message {
    display: block;
    color: var(--danger);
}

/* Error field styling */
.error-field {
    border-color: var(--danger) !important;
    background-color: rgba(var(--danger-rgb), 0.05) !important;
}

.error-field:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

/* Checkbox styling */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

input[type="checkbox"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Loading button animation */
.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-top: -8px;
    margin-left: -8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s linear infinite;
}

/* Empty state styling */
.text-center i {
    opacity: 0.7;
}

.text-center h4 {
    margin-top: 1rem;
}

.text-center p {
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}
</style>