@extends('layouts.app')

@section('title', 'Trash - Security Schedules')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--secondary) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-restore-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Security Schedules - Trash Bin
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i>
                        <span>Restore or permanently delete soft-deleted schedules</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <!-- Back to Active Schedules -->
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                </a>
                
                <!-- Empty Trash Button -->
                @if(isset($trashedSchedules) && $trashedSchedules->total() > 0)
                <button type="button" 
                        onclick="emptyTrash()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-broom mr-2"></i> Empty Trash
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
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
                </div>
            </div>
        </div>
        
        <!-- Trashed Today -->
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
                </div>
            </div>
        </div>
        
        <!-- Trashed This Week -->
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
                </div>
            </div>
        </div>
        
        <!-- Status Indicator -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">
                        @if(($trashStats['total_trashed'] ?? 0) > 0)
                            {{ $trashStats['total_trashed'] }} items in trash
                        @else
                            Trash is empty
                        @endif
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i>
                        Oldest: {{ $trashStats['oldest_trashed'] ?? 'N/A' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Trash Filters
                </h3>
                @if(request()->hasAny(['search', 'date', 'post_id', 'shift_id', 'security_user_id', 'status']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('admin.security-schedules.trash') }}" class="space-y-4" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search personnel, post, shift..."
                               value="{{ request('search') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date</label>
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
                                    {{ $post->name }} @if($post->code)({{ $post->code }})@endif
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
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>📅 Scheduled</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>🟢 Active</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>✅ Completed</option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>❌ Absent</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>🚫 Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Shift Category</label>
                        <select name="shift_category" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Categories</option>
                            <option value="morning" {{ request('shift_category') == 'morning' ? 'selected' : '' }}>🌅 Morning</option>
                            <option value="evening" {{ request('shift_category') == 'evening' ? 'selected' : '' }}>🌆 Evening</option>
                            <option value="night" {{ request('shift_category') == 'night' ? 'selected' : '' }}>🌙 Night</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2 pt-4">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--danger); border: 1px solid var(--danger);">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-schedules.trash') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear All
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if(isset($trashedSchedules) && $trashedSchedules->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select items to restore or permanently delete multiple schedules
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAllTrashed()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAllTrashed()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <select id="trashBulkActionSelect" 
                        class="form-input p-3 rounded-lg border col-span-1 md:col-span-3"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Choose action...</option>
                    <option value="restore">Restore Selected</option>
                    <option value="force_delete">Permanently Delete Selected</option>
                </select>
                <button type="button" 
                        onclick="performTrashBulkAction()" 
                        class="p-3 rounded-lg font-medium inline-flex items-center justify-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger);"
                        id="trashBulkActionBtn">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
            
            <div id="trashBulkActionStatus" class="mt-3 hidden">
                <!-- Status messages will appear here -->
            </div>
        </div>
    </div>
    @endif

    <!-- Trashed Schedules Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Trashed Security Schedules
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $trashedSchedules->firstItem() ?? 0 }} to {{ $trashedSchedules->lastItem() ?? 0 }} of {{ $trashedSchedules->total() ?? 0 }} trashed schedules
                </div>
            </div>
        </div>
        <div class="p-6">
            @if(isset($trashedSchedules) && $trashedSchedules->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllTrashedCheckbox" class="bulk-checkbox">
                            </th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Date & Post</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Deleted Info</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedSchedules as $schedule)
                            @php
                                $statusInfo = $schedule->getStatusWithColor();
                                $deletedBy = null; // You would need to add deleted_by column to get this
                            @endphp
                            <tr class="border-b transition-colors duration-150 hover:bg-gray-50" 
                                style="border-color: var(--border-color); background-color: rgba(var(--danger-rgb), 0.02);">
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="trashed-checkbox bulk-checkbox" 
                                           value="{{ $schedule->id }}">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-start">
                                        <div class="p-2 rounded-lg mr-3 mt-1"
                                             style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-calendar-alt text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold flex items-center" style="color: var(--text-primary);">
                                                {{ $schedule->post->name ?? 'Deleted Post' }}
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded-full" 
                                                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-trash text-xs mr-1"></i>Trashed
                                                </span>
                                            </div>
                                            <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-day mr-1 text-xs"></i> 
                                                {{ $schedule->assignment_date ? $schedule->assignment_date->format('M j, Y') : 'N/A' }}
                                            </div>
                                            @if($schedule->post && $schedule->post->code)
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Post Code: {{ $schedule->post->code }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Shift -->
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $schedule->shift->name ?? 'Deleted Shift' }}
                                    </div>
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i>
                                        @if($schedule->shift)
                                            {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                    @if($schedule->shift && $schedule->shift->is_overnight)
                                        <span class="text-xs px-2 py-0.5 rounded-full mt-1 inline-block"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-moon mr-1"></i> Overnight
                                        </span>
                                    @endif
                                </td>
                                
                                <!-- Personnel -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); color: white; font-weight: 600;">
                                            {{ strtoupper(substr($schedule->securityUser->name ?? 'NA', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $schedule->securityUser->name ?? 'Deleted User' }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $schedule->securityUser->phone ?? 'No phone' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Status -->
                                <td class="py-3 px-4">
                                    <span class="px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: {{ $statusInfo['bg'] ?? 'rgba(var(--secondary-rgb), 0.1)' }}; color: {{ $statusInfo['color'] ?? 'var(--text-secondary)' }};">
                                        <i class="fas fa-{{ $statusInfo['icon'] ?? 'circle' }} mr-1"></i>
                                        {{ $statusInfo['label'] ?? ucfirst($schedule->status) }}
                                    </span>
                                    @if($schedule->handover_info)
                                        <div class="text-xs mt-1" style="color: var(--info);">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            Handover configured
                                        </div>
                                    @endif
                                </td>
                                
                                <!-- Deleted Info -->
                                <td class="py-3 px-4">
                                    <div class="text-sm" style="color: var(--danger);">
                                        <i class="fas fa-trash mr-1"></i>
                                        {{ $schedule->deleted_at ? $schedule->deleted_at->diffForHumans() : 'Unknown' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $schedule->deleted_at ? $schedule->deleted_at->format('M j, Y h:i A') : 'N/A' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        @if($schedule->assignedBy)
                                            Deleted by: {{ $schedule->assignedBy->name ?? 'System' }}
                                        @endif
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-plus mr-1"></i>
                                        Created: {{ $schedule->created_at ? $schedule->created_at->format('M j, Y') : 'N/A' }}
                                    </div>
                                </td>
                                
                                <!-- Actions -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <!-- Restore Button -->
                                        <button onclick="showRestoreModal({{ $schedule->id }}, '{{ addslashes($schedule->post->name ?? 'Unknown Post') }}', '{{ $schedule->assignment_date ? $schedule->assignment_date->format('Y-m-d') : 'N/A' }}')"
                                                class="action-btn" 
                                                title="Restore Schedule"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-trash-restore text-sm"></i>
                                        </button>
                                        
                                        <!-- Permanent Delete Button -->
                                        <button onclick="showPermanentDeleteModal({{ $schedule->id }}, '{{ addslashes($schedule->post->name ?? 'Unknown Post') }}', '{{ $schedule->assignment_date ? $schedule->assignment_date->format('Y-m-d') : 'N/A' }}')"
                                                class="action-btn" 
                                                title="Permanently Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-times-circle text-sm"></i>
                                        </button>
                                        
                                        <!-- View Details Button -->
                                        <a href="{{ route('admin.security-schedules.show', $schedule->id) }}" 
                                           class="action-btn"
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                           target="_blank">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($trashedSchedules->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    {{ $trashedSchedules->withQueryString()->links() }}
                </div>
            @endif
            
            @else
            <!-- Empty Trash State -->
            <div class="text-center py-12">
                <div class="mb-4">
                    <i class="fas fa-trash-alt text-6xl" style="color: var(--text-secondary); opacity: 0.3;"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Trash Bin is Empty</h3>
                <p class="mb-6" style="color: var(--text-secondary); max-width: 400px; margin: 0 auto;">
                    No security schedules have been moved to trash. Deleted schedules will appear here for recovery.
                </p>
                <div class="space-x-2">
                    <a href="{{ route('admin.security-schedules.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                       style="background-color: var(--primary); border: 1px solid var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                    </a>
                    <a href="{{ route('admin.security-schedules.create') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-plus mr-2"></i> Create New Schedule
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Restore Schedule</h3>
            <button type="button" class="modal-close" onclick="closeModal('restoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-trash-restore text-5xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="restoreScheduleTitle">
                    <!-- Schedule title will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);" id="restoreScheduleDetails">
                    <!-- Schedule details will be inserted here -->
                </p>
                <div id="conflictWarning" class="hidden p-3 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--warning-rgb), 0.1); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        <span style="color: var(--warning);" id="conflictWarningText"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('restoreModal')">
                Cancel
            </button>
            <form id="restoreForm" method="POST" style="display: inline;">
                @csrf
                @method('PATCH')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--success); border: 1px solid var(--success);"
                    onclick="confirmRestore()">
                <i class="fas fa-trash-restore mr-2"></i> Restore Schedule
            </button>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('permanentDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete Schedule</h3>
            <button type="button" class="modal-close" onclick="closeModal('permanentDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-circle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="permanentDeleteScheduleTitle">
                    <!-- Schedule title will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);" id="permanentDeleteScheduleDetails">
                    <!-- Schedule details will be inserted here -->
                </p>
                <div class="p-3 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                        <span class="font-medium" style="color: var(--danger);">This action cannot be undone!</span>
                    </div>
                    <div class="text-sm text-left" style="color: var(--text-secondary);">
                        <p class="mb-1">• Schedule will be permanently removed from database</p>
                        <p class="mb-1">• All associated data will be lost</p>
                        <p>• This action is irreversible</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('permanentDeleteModal')">
                Cancel
            </button>
            <form id="permanentDeleteForm" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmPermanentDelete()">
                <i class="fas fa-times-circle mr-2"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Empty Trash Bin</h3>
            <button type="button" class="modal-close" onclick="closeModal('emptyTrashModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-trash-alt text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--danger);">Empty All Trash?</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    You are about to permanently delete <strong>{{ $trashStats['total_trashed'] ?? 0 }}</strong> schedule(s) from trash. This action will:
                </p>
                <div class="p-3 mb-4 rounded-lg border text-left" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <p class="mb-1 flex items-center">
                            <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i>
                            Permanently delete all trashed schedules
                        </p>
                        <p class="mb-1 flex items-center">
                            <i class="fas fa-calendar-times mr-2" style="color: var(--danger);"></i>
                            Remove all schedule records permanently
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            This action cannot be undone!
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('emptyTrashModal')">
                Cancel
            </button>
            <form id="emptyTrashForm" method="POST" action="{{ route('admin.security-schedules.clear-old-trash') }}" style="display: inline;">
                @csrf
                @method('DELETE')
                <input type="hidden" name="days" value="0" id="emptyTrashDays">
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmEmptyTrash()">
                <i class="fas fa-broom mr-2"></i> Empty Trash
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
<script>
// ============================================================================
// SECURITY SCHEDULES TRASH MANAGEMENT
// ============================================================================

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeActionButtons();
    initializeTrashBulkSelection();
    initializeAutoSubmit();
});

// ----------------------------------------------------------------------------
// BULK SELECTION FUNCTIONS
// ----------------------------------------------------------------------------

function initializeTrashBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllTrashedCheckbox');
    const trashedCheckboxes = document.querySelectorAll('.trashed-checkbox');
    
    if (selectAllCheckbox && trashedCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            trashedCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateTrashSelectedCount();
        });
    }
    
    trashedCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateTrashSelectedCount();
            
            // Update select all checkbox state
            if (selectAllCheckbox) {
                const allChecked = Array.from(trashedCheckboxes).every(cb => cb.checked);
                const anyChecked = Array.from(trashedCheckboxes).some(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = anyChecked && !allChecked;
            }
        });
    });
    
    updateTrashSelectedCount();
}

function updateTrashSelectedCount() {
    const checkboxes = document.querySelectorAll('.trashed-checkbox:checked');
    const selectedCount = checkboxes.length;
    const bulkActionBtn = document.getElementById('trashBulkActionBtn');
    
    if (bulkActionBtn) {
        if (selectedCount > 0) {
            bulkActionBtn.disabled = false;
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply (${selectedCount} selected)`;
        } else {
            bulkActionBtn.disabled = true;
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply`;
        }
    }
}

function selectAllTrashed() {
    const checkboxes = document.querySelectorAll('.trashed-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllTrashedCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    }
    
    updateTrashSelectedCount();
}

function deselectAllTrashed() {
    const checkboxes = document.querySelectorAll('.trashed-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllTrashedCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    
    updateTrashSelectedCount();
}

// ----------------------------------------------------------------------------
// BULK ACTIONS - FIXED ROUTES
// ----------------------------------------------------------------------------

async function performTrashBulkAction() {
    const actionSelect = document.getElementById('trashBulkActionSelect');
    const selectedAction = actionSelect.value;
    
    if (!selectedAction) {
        showToast('Please select an action first', 'warning');
        return;
    }
    
    const checkboxes = document.querySelectorAll('.trashed-checkbox:checked');
    if (checkboxes.length === 0) {
        showToast('Please select at least one schedule', 'warning');
        return;
    }
    
    const scheduleIds = Array.from(checkboxes).map(cb => cb.value);
    
    // Confirmations
    let confirmMessage = '';
    let routeUrl = '';
    
    switch(selectedAction) {
        case 'restore':
            confirmMessage = `Are you sure you want to restore ${scheduleIds.length} schedule(s)?`;
            routeUrl = '{{ route("admin.security-schedules.bulk-restore") }}';
            break;
        case 'force_delete':
            confirmMessage = `Are you sure you want to permanently delete ${scheduleIds.length} schedule(s)? This action cannot be undone!`;
            routeUrl = '{{ route("admin.security-schedules.bulk-force-delete") }}';
            break;
        default:
            return;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        showLoading(`Processing ${scheduleIds.length} schedule(s)...`);
        
        const response = await fetch(routeUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                schedule_ids: scheduleIds
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            showToast(data.message, 'success');
            
            // Clear selection
            deselectAllTrashed();
            actionSelect.value = '';
            
            // Refresh page after successful action
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to perform bulk action', 'error');
            
            // Show conflict details if available
            if (data.conflict_details) {
                console.log('Conflict details:', data.conflict_details);
                // You could display these details in a modal
            }
        }
    } catch (error) {
        hideLoading();
        console.error('Trash bulk action error:', error);
        showToast('Network error occurred. Please try again.', 'error');
    }
}

// ----------------------------------------------------------------------------
// RESTORE FUNCTIONS - FIXED ROUTES
// ----------------------------------------------------------------------------

async function showRestoreModal(scheduleId, postName, scheduleDate) {
    // Set schedule details
    document.getElementById('restoreScheduleTitle').textContent = postName;
    document.getElementById('restoreScheduleDetails').textContent = 
        `Date: ${scheduleDate}`;
    
    // Update restore form action - FIXED: Using PATCH route
    const restoreForm = document.getElementById('restoreForm');
    restoreForm.action = '{{ route("admin.security-schedules.restore", ":id") }}'.replace(':id', scheduleId);
    restoreForm.method = 'POST'; // Laravel uses POST with PATCH method spoofing
    
    // Ensure method spoofing is present
    let methodInput = restoreForm.querySelector('input[name="_method"]');
    if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'PATCH';
        restoreForm.appendChild(methodInput);
    } else {
        methodInput.value = 'PATCH';
    }
    
    // Check for conflicts before showing modal
    showLoading('Checking for conflicts...');
    
    try {
        // You can implement conflict checking here if needed
        // For now, just hide the warning
        document.getElementById('conflictWarning').classList.add('hidden');
        
        hideLoading();
        openModal('restoreModal');
    } catch (error) {
        hideLoading();
        console.error('Error checking conflicts:', error);
        // Still allow restore, but show warning
        openModal('restoreModal');
    }
}

function confirmRestore() {
    const restoreForm = document.getElementById('restoreForm');
    restoreForm.submit();
}

// ----------------------------------------------------------------------------
// PERMANENT DELETE FUNCTIONS - FIXED ROUTES
// ----------------------------------------------------------------------------

function showPermanentDeleteModal(scheduleId, postName, scheduleDate) {
    // Set schedule details
    document.getElementById('permanentDeleteScheduleTitle').textContent = postName;
    document.getElementById('permanentDeleteScheduleDetails').textContent = 
        `Date: ${scheduleDate}`;
    
    // Update delete form action - FIXED: Using force-delete route
    const deleteForm = document.getElementById('permanentDeleteForm');
    deleteForm.action = '{{ route("admin.security-schedules.force-delete", ":id") }}'.replace(':id', scheduleId);
    deleteForm.method = 'POST'; // Laravel uses POST with DELETE method spoofing
    
    // Ensure method spoofing is present
    let methodInput = deleteForm.querySelector('input[name="_method"]');
    if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        deleteForm.appendChild(methodInput);
    } else {
        methodInput.value = 'DELETE';
    }
    
    openModal('permanentDeleteModal');
}

function confirmPermanentDelete() {
    const deleteForm = document.getElementById('permanentDeleteForm');
    deleteForm.submit();
}

// ----------------------------------------------------------------------------
// EMPTY TRASH FUNCTIONS - FIXED ROUTES
// ----------------------------------------------------------------------------

function emptyTrash() {
    // Get total count from stats
    const totalTrashed = {{ $trashStats['total_trashed'] ?? 0 }};
    
    if (totalTrashed === 0) {
        showToast('Trash is already empty', 'info');
        return;
    }
    
    openModal('emptyTrashModal');
}

function confirmEmptyTrash() {
    const emptyTrashForm = document.getElementById('emptyTrashForm');
    
    // Set days to 0 to clear all trash immediately
    document.getElementById('emptyTrashDays').value = '0';
    
    showLoading('Emptying trash...');
    emptyTrashForm.submit();
}

// ----------------------------------------------------------------------------
// AUTO-SUBMIT FILTERS
// ----------------------------------------------------------------------------

function initializeAutoSubmit() {
    // Auto-submit on select changes (but not for the bulk action select)
    document.querySelectorAll('#filterForm select:not(#trashBulkActionSelect)').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
}

// ----------------------------------------------------------------------------
// UI UTILITIES
// ----------------------------------------------------------------------------

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

// ----------------------------------------------------------------------------
// TOAST NOTIFICATIONS
// ----------------------------------------------------------------------------

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

// ----------------------------------------------------------------------------
// LOADING INDICATOR
// ----------------------------------------------------------------------------

function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
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

// ----------------------------------------------------------------------------
// EVENT LISTENERS
// ----------------------------------------------------------------------------

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
    // Ctrl/Cmd + A to select all trashed schedules
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        const activeElement = document.activeElement;
        // Don't override if typing in input/textarea
        if (!activeElement.matches('input, textarea, [contenteditable]')) {
            e.preventDefault();
            if (document.querySelector('.trashed-checkbox')) {
                selectAllTrashed();
            }
        }
    }
    
    // Esc to deselect all
    if (e.key === 'Escape') {
        const anySelected = document.querySelector('.trashed-checkbox:checked');
        if (anySelected) {
            e.preventDefault();
            deselectAllTrashed();
        }
    }
});
</script>
@endpush

@push('styles')
<style>
/* ============================================================================
   MODAL STYLES
   ============================================================================ */
.modal {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background-color: white;
    border-radius: 12px;
    max-width: 90%;
    max-height: 90%;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.modal-close:hover {
    background-color: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* ============================================================================
   UTILITY CLASSES
   ============================================================================ */
.hidden {
    display: none !important;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    font-size: 0.875rem;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.bulk-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--danger);
}

/* ============================================================================
   TABLE STYLES
   ============================================================================ */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

table td {
    font-size: 0.875rem;
}

/* ============================================================================
   RESPONSIVE STYLES
   ============================================================================ */
@media (max-width: 768px) {
    .modal-container {
        margin: 1rem;
        max-height: 85vh;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
    
    .bulk-checkbox {
        width: 20px;
        height: 20px;
    }
}

/* ============================================================================
   DARK MODE SUPPORT
   ============================================================================ */
[data-theme="dark"] .modal-container {
    background-color: #1f2937;
    border: 1px solid #374151;
}

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer {
    border-color: #374151;
}

[data-theme="dark"] .modal-title {
    color: #f9fafb;
}

[data-theme="dark"] .modal-close {
    color: #9ca3af;
}

[data-theme="dark"] .modal-close:hover {
    background-color: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

[data-theme="dark"] table tr:hover {
    background-color: rgba(59, 130, 246, 0.1) !important;
}
</style>
@endpush