@extends('layouts.app')

@section('title', 'Trashed Security Schedules')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Trashed Schedules
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i>
                        <span>Recover or permanently delete deleted schedules</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                </a>
                @if(isset($trashedSchedules) && $trashedSchedules->total() > 0)
                <button onclick="showBulkRestoreModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-2"></i> Bulk Restore
                </button>
                <button onclick="showClearTrashModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-broom mr-2"></i> Clear Old
                </button>
                @endif
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
                
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-map-marker-alt mr-1"></i> Security Posts
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Active Schedules
                </a>
                
                <a href="{{ route('admin.security-posts.trash.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash mr-1"></i> Posts Trash
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Trash Statistics Cards -->
    @if(isset($trashStats))
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Trashed -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $trashStats['total_trashed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Trashed</div>
                    @if(isset($trashStats['oldest_trashed']))
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> Oldest: {{ $trashStats['oldest_trashed'] }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Deleted Today -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashStats['trashed_by_period']['today'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted Today</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ now()->format('M j, Y') }}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- This Week -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $trashStats['trashed_by_period']['this_week'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">This Week</div>
                    @php
                        $weekPercentage = ($trashStats['total_trashed'] ?? 0) > 0 
                            ? round(($trashStats['trashed_by_period']['this_week'] ?? 0) / $trashStats['total_trashed'] * 100) 
                            : 0;
                    @endphp
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $weekPercentage }}% of total
                    </div>
                </div>
            </div>
        </div>
        
        <!-- This Month -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $trashStats['trashed_by_period']['this_month'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">This Month</div>
                    @php
                        $daysInMonth = now()->daysInMonth;
                        $avgPerDay = $daysInMonth > 0 
                            ? round(($trashStats['trashed_by_period']['this_month'] ?? 0) / $daysInMonth, 1) 
                            : 0;
                    @endphp
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $avgPerDay }}/day avg
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Auto-delete Notice -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-xl" style="color: var(--info);"></i>
            <div>
                <p class="text-sm font-medium" style="color: var(--text-primary);">Auto-delete Information</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Schedules are automatically permanently deleted after 30 days in trash. 
                    You can restore them anytime before that or manually delete them permanently.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--danger);"></i> Filters
                </h3>
                @if(request()->hasAny(['date', 'post_id', 'shift_id', 'status', 'security_user_id', 'search']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('admin.security-schedules.trash') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Deleted Date</label>
                        <input type="date" 
                               name="date" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Security Post</label>
                        <select name="post_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Posts</option>
                            @foreach($securityPosts as $post)
                                <option value="{{ $post->id }}" {{ request('post_id') == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }} ({{ $post->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Shift</label>
                        <select name="shift_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Shifts</option>
                            @foreach($securityShifts as $shift)
                                <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>
                                    {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Security Personnel</label>
                        <select name="security_user_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Personnel</option>
                            @foreach($securityPersonnel as $person)
                                <option value="{{ $person->id }}" {{ request('security_user_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }} - {{ $person->phone }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, post, shift..."
                               value="{{ request('search') }}">
                    </div>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); border: none;">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-schedules.trash') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if(isset($trashedSchedules) && $trashedSchedules->count() > 0)
    <div class="card" style="background-color: rgba(var(--danger-rgb), 0.05); border-left: 4px solid var(--danger);">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select schedules to restore or permanently delete
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAllSchedules()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAllSchedules()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            
            <div class="mt-4 flex items-center space-x-3">
                <span id="selectedCount" class="text-sm px-3 py-2 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                    0 items selected
                </span>
                <button onclick="bulkRestore()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-2"></i> Restore Selected
                </button>
                <button onclick="bulkForceDelete()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                </button>
            </div>
            
            <div id="bulkActionStatus" class="mt-3 hidden">
                <!-- Status messages will appear here -->
            </div>
        </div>
    </div>
    @endif

    <!-- Trashed Schedules Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Deleted Schedules
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $trashedSchedules->firstItem() ?? 0 }} to {{ $trashedSchedules->lastItem() ?? 0 }} of {{ $trashedSchedules->total() }} deleted schedules
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($trashedSchedules->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Date</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Post</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Deleted At</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Deleted By</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Original Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trashedSchedules as $schedule)
                            @php
                                $deletedAt = \Carbon\Carbon::parse($schedule->deleted_at);
                                $daysInTrash = $deletedAt->diffInDays(now());
                                $autoDeleteDays = 30;
                                $daysRemaining = $autoDeleteDays - $daysInTrash;
                                $statusInfo = $schedule->getStatusInfo();
                            @endphp
                            <tr class="border-b transition-colors duration-150 hover:bg-gray-50" 
                                style="border-color: var(--border-color);">
                                @if($trashedSchedules->count() > 0)
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="schedule-checkbox bulk-checkbox" 
                                           value="{{ $schedule->id }}">
                                </td>
                                @endif
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->assignment_date->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $schedule->assignment_date->format('l') }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->post->name ?? 'N/A' }}
                                            </div>
                                            @if($schedule->post->code ?? false)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Code: {{ $schedule->post->code }}
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->shift->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : 'N/A' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="avatar mr-3 flex-shrink-0"
                                             style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">
                                            {{ strtoupper(substr($schedule->securityUser->name ?? 'N/A', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->securityUser->name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $schedule->securityUser->phone ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--danger);">
                                        {{ $deletedAt->format('M j, Y H:i') }}
                                    </div>
                                    <div class="text-xs mt-1">
                                        <span class="flex items-center" style="color: {{ $daysRemaining < 7 ? 'var(--danger)' : 'var(--warning)' }};">
                                            <i class="fas fa-hourglass-half mr-1"></i>
                                            @if($daysRemaining <= 0)
                                                Pending auto-delete
                                            @else
                                                {{ $daysRemaining }} days left
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->deletedBy->name ?? 'System' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        ID: {{ $schedule->deleted_by ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: {{ $statusInfo['bg'] }}; color: {{ $statusInfo['color'] }};">
                                        <i class="fas fa-{{ $statusInfo['icon'] }} mr-1"></i>
                                        {{ $statusInfo['label'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="restoreSchedule({{ $schedule->id }})"
                                                class="action-btn" 
                                                title="Restore Schedule"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        
                                        <a href="{{ route('admin.security-schedules.show', $schedule->id) }}" 
                                           class="action-btn" 
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button onclick="showForceDeleteModal({{ $schedule->id }})"
                                                class="action-btn" 
                                                title="Delete Permanently"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-trash-alt text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">Trash is empty</p>
                                        <p style="color: var(--text-secondary);">No deleted schedules found</p>
                                        <div class="mt-4">
                                            <a href="{{ route('admin.security-schedules.index') }}" 
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                                               style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%); border: none;">
                                                <i class="fas fa-arrow-left mr-2"></i> Back to Active Schedules
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
            @if($trashedSchedules->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $trashedSchedules->firstItem() }} to {{ $trashedSchedules->lastItem() }} of {{ $trashedSchedules->total() }} entries
                        </div>
                        <div class="flex space-x-2">
                            @if($trashedSchedules->onFirstPage())
                                <span class="px-3 py-2 rounded border text-sm" 
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            @else
                                <a href="{{ $trashedSchedules->previousPageUrl() }}" 
                                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            @endif

                            @foreach($trashedSchedules->getUrlRange(max(1, $trashedSchedules->currentPage() - 2), min($trashedSchedules->lastPage(), $trashedSchedules->currentPage() + 2)) as $page => $url)
                                @if($page == $trashedSchedules->currentPage())
                                    <span class="px-3 py-2 rounded text-sm font-medium"
                                          style="background-color: var(--danger); color: white;">
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

                            @if($trashedSchedules->hasMorePages())
                                <a href="{{ $trashedSchedules->nextPageUrl() }}" 
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

    <!-- Deletion Activity (Optional) -->
    @if(isset($trashStats['deleted_by_users']) && count($trashStats['deleted_by_users']) > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--info);"></i>
                Deletion Activity
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($trashStats['deleted_by_users'] as $date => $count)
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                    <div class="flex justify-between items-center">
                        <span style="color: var(--text-primary); font-weight: 500;">{{ $date }}</span>
                        <span class="text-lg font-bold" style="color: var(--danger);">{{ $count }}</span>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">schedules deleted</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Bulk Restore Modal -->
<div id="bulkRestoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkRestoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Restore Schedules</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkRestoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-undo-alt text-5xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="bulkRestoreMessage">
                    Restore <span id="restoreCount">0</span> selected schedules?
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This will return them to the active schedules list. Conflicts may prevent some schedules from being restored.
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm" style="color: var(--warning);">
                            Schedules with date/shift conflicts cannot be restored
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('bulkRestoreModal')">
                Cancel
            </button>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%); border: none;"
                    onclick="confirmBulkRestore()">
                <i class="fas fa-undo-alt mr-2"></i> Restore All
            </button>
        </div>
    </div>
</div>

<!-- Clear Old Trash Modal -->
<div id="clearTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('clearTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Clear Old Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('clearTrashModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('admin.security-schedules.clear-old-trash') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-broom text-5xl mb-4" style="color: var(--warning);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Permanently delete old schedules</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        Select how many days old the schedules should be before permanent deletion.
                    </p>
                    
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Delete schedules older than:
                        </label>
                        <select name="days" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                required>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30" selected>30 days (default)</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                        </select>
                        <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            This action cannot be undone. Schedules will be permanently deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('clearTrashModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); border: none;">
                    <i class="fas fa-broom mr-2"></i> Clear Old Trash
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Force Delete Modal -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete Schedule</h3>
            <button type="button" class="modal-close" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeleteScheduleInfo">
                    <!-- Schedule info will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action will <strong class="text-red-600">permanently delete</strong> this schedule. This cannot be undone!
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('forceDeleteModal')">
                Cancel
            </button>
            <form id="forceDeleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); border: none;"
                    onclick="confirmForceDelete()">
                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
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
let selectedScheduleIds = [];
let currentScheduleId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkSelection();
    initializeActionButtons();
});

// Bulk selection functions
function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            scheduleCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    scheduleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            
            // Update select all checkbox state
            if (selectAllCheckbox) {
                const allCheckboxes = document.querySelectorAll('.schedule-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.schedule-checkbox:checked');
                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            }
        });
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    const selectedCount = checkboxes.length;
    const countElement = document.getElementById('selectedCount');
    
    if (countElement) {
        countElement.textContent = selectedCount + ' item' + (selectedCount !== 1 ? 's' : '') + ' selected';
    }
}

function selectAllSchedules() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
    
    updateSelectedCount();
}

function deselectAllSchedules() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    
    updateSelectedCount();
}

function getSelectedScheduleIds() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// Bulk restore
function showBulkRestoreModal() {
    const selectedIds = getSelectedScheduleIds();
    if (selectedIds.length === 0) {
        showToast('Please select at least one schedule to restore', 'warning');
        return;
    }
    
    document.getElementById('restoreCount').textContent = selectedIds.length;
    openModal('bulkRestoreModal');
}

async function confirmBulkRestore() {
    const selectedIds = getSelectedScheduleIds();
    if (selectedIds.length === 0) return;
    
    try {
        showLoading(`Restoring ${selectedIds.length} schedule(s)...`);
        
        const response = await fetch(`{{ route('admin.security-schedules.bulk-restore') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ schedule_ids: selectedIds })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            closeModal('bulkRestoreModal');
            showToast(data.message, 'success');
            
            // Show conflict warnings if any
            if (data.failed_count > 0) {
                let conflictMessage = `${data.failed_count} schedule(s) could not be restored due to conflicts.`;
                showToast(conflictMessage, 'warning');
            }
            
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore schedules', 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Bulk restore error:', error);
        showToast('Network error occurred', 'error');
    }
}

// Bulk force delete
function bulkForceDelete() {
    const selectedIds = getSelectedScheduleIds();
    if (selectedIds.length === 0) {
        showToast('Please select at least one schedule to delete', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to permanently delete ${selectedIds.length} schedule(s)? This action cannot be undone.`)) {
        return;
    }
    
    performBulkForceDelete(selectedIds);
}

async function performBulkForceDelete(scheduleIds) {
    try {
        showLoading(`Deleting ${scheduleIds.length} schedule(s)...`);
        
        const response = await fetch(`{{ route('admin.security-schedules.bulk-force-delete') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ schedule_ids: scheduleIds })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to delete schedules', 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Bulk force delete error:', error);
        showToast('Network error occurred', 'error');
    }
}

// Single schedule actions - FIXED: Use PATCH method with method spoofing
function restoreSchedule(scheduleId) {
    if (!confirm('Are you sure you want to restore this schedule?')) return;
    
    // Create a form with POST + method spoofing for PATCH
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `{{ url('admin/security-schedules') }}/${scheduleId}/restore`;
    form.style.display = 'none';
    
    // Add CSRF token
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    // Add method spoofing for PATCH
    const methodField = document.createElement('input');
    methodField.type = 'hidden';
    methodField.name = '_method';
    methodField.value = 'PATCH';
    form.appendChild(methodField);
    
    document.body.appendChild(form);
    form.submit();
}

// FIXED: Force delete with correct URL (removed 'trash' from path)
function showForceDeleteModal(scheduleId) {
    currentScheduleId = scheduleId;
    document.getElementById('forceDeleteScheduleInfo').textContent = 'Delete Schedule #' + scheduleId;
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.action = `{{ url('admin/security-schedules') }}/${scheduleId}/force-delete`;
    openModal('forceDeleteModal');
}

function confirmForceDelete() {
    document.getElementById('forceDeleteForm').submit();
}

// Clear old trash modal
function showClearTrashModal() {
    openModal('clearTrashModal');
}

// Action buttons hover effect
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

// Modal functions
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

// Toast notification
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
    
    toast.innerHTML = `
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
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

// Loading overlay
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
                <div class="text-white font-medium" id="loadingMessage">${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) messageEl.textContent = message;
    
    overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('hidden');
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        if (document.querySelector('.schedule-checkbox')) {
            selectAllSchedules();
        }
    }
    
    if (e.key === 'Escape') {
        const anySelected = document.querySelector('.schedule-checkbox:checked');
        if (anySelected) {
            e.preventDefault();
            deselectAllSchedules();
        }
    }
});
</script>

<style>
/* Custom styles for trash page - matching the main template */
:root {
    --primary: #3b82f6;
    --primary-rgb: 59, 130, 246;
    --secondary: #6b7280;
    --secondary-rgb: 107, 114, 128;
    --success: #10b981;
    --success-rgb: 16, 185, 129;
    --danger: #ef4444;
    --danger-rgb: 239, 68, 68;
    --warning: #f59e0b;
    --warning-rgb: 245, 158, 11;
    --info: #3b82f6;
    --info-rgb: 59, 130, 246;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --bg-secondary: #f9fafb;
    --border-color: #e5e7eb;
    --card-bg: #ffffff;
}

/* Card styling */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Button styles */
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%) !important;
    color: white !important;
    border: none !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3) !important;
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Action button */
.action-btn {
    width: 36px;
    height: 36px;
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

/* Form elements */
.form-input, select, textarea {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, select:focus, textarea:focus {
    outline: none !important;
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

/* Avatar styling */
.avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
}

/* Bulk selection checkbox */
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
    background-color: var(--danger);
    border-color: var(--danger);
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
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem;
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
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
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

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

table td {
    font-size: 0.875rem;
}

/* Hidden class */
.hidden {
    display: none !important;
}

/* Animation keyframes */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
    
    .avatar {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
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

/* Dark mode support */
[data-theme="dark"] {
    --text-primary: #f9fafb;
    --text-secondary: #9ca3af;
    --bg-secondary: #1f2937;
    --border-color: #374151;
    --card-bg: #1f2937;
}

[data-theme="dark"] table tr:hover {
    background-color: rgba(239, 68, 68, 0.1) !important;
}

[data-theme="dark"] .btn-secondary {
    background-color: #374151 !important;
    color: #f9fafb !important;
    border-color: #4b5563 !important;
}

[data-theme="dark"] .btn-secondary:hover {
    background-color: #4b5563 !important;
}

[data-theme="dark"] .modal-container {
    background-color: #1f2937;
    border-color: #374151;
}

[data-theme="dark"] .modal-footer {
    background-color: rgba(55, 65, 81, 0.5);
}

[data-theme="dark"] select, 
[data-theme="dark"] input, 
[data-theme="dark"] textarea {
    background-color: #111827 !important;
    border-color: #374151 !important;
    color: #f9fafb !important;
}
</style>
@endsection