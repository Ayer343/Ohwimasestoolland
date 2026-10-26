@extends('layouts.secu')

@section('title', 'Trash - Security Shifts')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--secondary) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-restore-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                        Security Shifts — Trash Bin
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i>
                        <span>Restore or permanently delete soft-deleted shifts</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 flex-wrap gap-2">
                <a href="{{ route('security.security-shifts.index') }}"
                   class="px-3 py-2 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Shifts
                </a>

                @if(isset($trashedShifts) && $trashedShifts->total() > 0)
                <button type="button"
                        onclick="emptyTrash()"
                        class="px-3 py-2 rounded-lg text-xs font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-broom mr-1"></i> Empty Trash
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashStats['trashed_today'] ?? 0 }}</div>
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
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $trashStats['trashed_this_week'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">This Week</div>
                </div>
            </div>
        </div>

        <!-- Info -->
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
                            {{ $trashStats['total_trashed'] }} items
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
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Trash Filters
                </h3>
                @if(request()->hasAny(['search', 'category']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('security.security-shifts.trash.index') }}" class="space-y-4" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                        </label>
                        <input type="text"
                               name="search"
                               class="index-custom-input w-full"
                               placeholder="Search by name, code, or description..."
                               value="{{ request('search') }}">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Category
                        </label>
                        <select name="category" class="index-custom-dropdown w-full">
                            <option value="">All Categories</option>
                            @foreach($shiftCategories as $value => $label)
                                <option value="{{ $value }}" {{ request('category') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex space-x-2 pt-4 flex-wrap gap-2">
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('security.security-shifts.trash.index') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-times mr-2"></i> Clear All
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if(isset($trashedShifts) && $trashedShifts->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select shifts to restore or permanently delete multiple at once
                    </p>
                </div>
                <div class="flex items-center space-x-2 flex-wrap gap-2">
                    <button type="button" onclick="selectAllTrashed()"
                            class="px-3 py-2 text-xs rounded-lg inline-flex items-center font-medium"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" onclick="deselectAllTrashed()"
                            class="px-3 py-2 text-xs rounded-lg inline-flex items-center font-medium"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <select id="trashBulkActionSelect" class="index-custom-dropdown md:col-span-3">
                    <option value="">Choose action...</option>
                    <option value="restore">Restore Selected</option>
                    <option value="force_delete">Permanently Delete Selected</option>
                </select>
                <button type="button" onclick="performTrashBulkAction()"
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        id="trashBulkActionBtn" disabled>
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Trashed Shifts Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Trashed Security Shifts
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $trashedShifts->firstItem() ?? 0 }} to {{ $trashedShifts->lastItem() ?? 0 }} of {{ $trashedShifts->total() ?? 0 }} trashed shifts
                </div>
            </div>
        </div>
        <div class="p-6">
            @if(isset($trashedShifts) && $trashedShifts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllTrashedCheckbox" class="index-custom-checkbox">
                            </th>
                            <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Shift Details</th>
                            <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Timing</th>
                            <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Schedule Type</th>
                            <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Deleted Info</th>
                            <th class="text-left py-3 px-4 text-xs uppercase font-semibold" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedShifts as $shift)
                            <tr class="border-b transition-colors duration-150"
                                style="border-color: var(--border-color); background-color: rgba(var(--danger-rgb), 0.02);">
                                <td class="py-3 px-4">
                                    <input type="checkbox"
                                           class="trashed-checkbox index-custom-checkbox"
                                           value="{{ $shift->id }}">
                                </td>

                                <!-- Shift Details -->
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
                                            <div class="font-semibold flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                                {{ $shift->name }}
                                                <span class="px-1.5 py-0.5 text-xs rounded-full badge-danger">
                                                    <i class="fas fa-trash text-xs mr-1"></i>Trashed
                                                </span>
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
                                            <span class="ml-2 px-1.5 py-0.5 rounded text-xs badge-primary">
                                                Overnight
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Schedule Type -->
                                <td class="py-3 px-4">
                                    @php
                                        $dayTypeLabels = [
                                            'all_days' => 'All Days',
                                            'weekday'  => 'Weekdays',
                                            'weekend'  => 'Weekends',
                                            'custom'   => 'Custom Days',
                                        ];
                                    @endphp
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $dayTypeLabels[$shift->day_type] ?? ucfirst($shift->day_type) }}
                                    </div>
                                    @if($shift->rotation_type === 'rotating')
                                        <div class="text-xs mt-1 flex items-center" style="color: var(--info);">
                                            <i class="fas fa-sync-alt mr-1"></i> Rotating
                                        </div>
                                    @else
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-lock mr-1"></i> Fixed
                                        </div>
                                    @endif
                                </td>

                                <!-- Deleted Info -->
                                <td class="py-3 px-4">
                                    <div class="text-sm" style="color: var(--danger);">
                                        <i class="fas fa-trash mr-1"></i>
                                        {{ $shift->deleted_at ? $shift->deleted_at->diffForHumans() : 'Unknown' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $shift->deleted_at ? $shift->deleted_at->format('M j, Y h:i A') : 'N/A' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-plus mr-1"></i>
                                        Created: {{ $shift->created_at ? $shift->created_at->format('M j, Y') : 'N/A' }}
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <button type="button"
                                                onclick="showRestoreModal({{ $shift->id }}, @js($shift->name))"
                                                class="action-btn"
                                                title="Restore Shift"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-trash-restore text-sm"></i>
                                        </button>

                                        <button type="button"
                                                onclick="showPermanentDeleteModal({{ $shift->id }}, @js($shift->name))"
                                                class="action-btn"
                                                title="Permanently Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-times-circle text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($trashedShifts->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center flex-wrap gap-3">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $trashedShifts->firstItem() }} to {{ $trashedShifts->lastItem() }} of {{ $trashedShifts->total() }} trashed shifts
                        </div>
                        <div class="flex space-x-2 flex-wrap gap-2">
                            @if($trashedShifts->onFirstPage())
                                <span class="px-3 py-2 rounded border text-sm"
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            @else
                                <a href="{{ $trashedShifts->previousPageUrl() }}"
                                   class="px-3 py-2 rounded border text-sm"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            @endif

                            @foreach($trashedShifts->getUrlRange(max(1, $trashedShifts->currentPage() - 2), min($trashedShifts->lastPage(), $trashedShifts->currentPage() + 2)) as $page => $url)
                                @if($page == $trashedShifts->currentPage())
                                    <span class="px-3 py-2 rounded text-sm font-medium"
                                          style="background-color: var(--primary); color: white;">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}"
                                       class="px-3 py-2 rounded border text-sm"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach

                            @if($trashedShifts->hasMorePages())
                                <a href="{{ $trashedShifts->nextPageUrl() }}"
                                   class="px-3 py-2 rounded border text-sm"
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

            @else
            <!-- Empty Trash State -->
            <div class="text-center py-12">
                <div class="mb-4">
                    <i class="fas fa-trash-alt text-6xl" style="color: var(--text-secondary); opacity: 0.3;"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Trash Bin is Empty</h3>
                <p class="mb-6" style="color: var(--text-secondary); max-width: 400px; margin: 0 auto;">
                    No security shifts have been moved to trash. Deleted shifts will appear here for recovery.
                </p>
                <div class="flex justify-center space-x-2 flex-wrap gap-2">
                    <a href="{{ route('security.security-shifts.index') }}"
                       class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Shifts
                    </a>
                    <a href="{{ route('security.security-shifts.create') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create New Shift
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
            <h3 class="modal-title">Restore Shift</h3>
            <button type="button" class="modal-close" onclick="closeModal('restoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-trash-restore text-5xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="restoreShiftTitle"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This shift will be restored and become available again.
                </p>
                <div class="p-3 rounded-lg border text-left"
                     style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        <p class="mb-1 flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            Shift is returned to the active list
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            It will be marked Inactive until you activate it
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    onclick="closeModal('restoreModal')">
                Cancel
            </button>
            <form id="restoreForm" method="POST" style="display: none;">
                @csrf
            </form>
            <button type="button" id="confirmRestoreBtn"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--success); border: 1px solid var(--success);"
                    onclick="confirmRestore()">
                <i class="fas fa-trash-restore mr-2"></i> Restore Shift
            </button>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('permanentDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete Shift</h3>
            <button type="button" class="modal-close" onclick="closeModal('permanentDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-circle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="permanentDeleteShiftTitle"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action cannot be undone. The shift will be removed permanently.
                </p>
                <div class="p-3 mb-4 rounded-lg border"
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                        <span class="font-medium" style="color: var(--danger);">This action cannot be undone!</span>
                    </div>
                    <div class="text-sm text-left" style="color: var(--text-secondary);">
                        <p class="mb-1">• Shift will be permanently removed from database</p>
                        <p class="mb-1">• All associated data will be lost</p>
                        <p>• Any schedules referencing it must be removed first</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    onclick="closeModal('permanentDeleteModal')">
                Cancel
            </button>
            <form id="permanentDeleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" id="confirmPermanentDeleteBtn"
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
                    You are about to permanently delete <strong>{{ $trashStats['total_trashed'] ?? 0 }}</strong> shift(s) from trash.
                </p>
                <div class="p-3 mb-4 rounded-lg border text-left"
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <p class="mb-1 flex items-center">
                            <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i>
                            Permanently delete all trashed shifts
                        </p>
                        <p class="mb-1 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                            Shifts referenced by any schedule will be skipped
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-ban mr-2" style="color: var(--danger);"></i>
                            This action cannot be undone!
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    onclick="closeModal('emptyTrashModal')">
                Cancel
            </button>
            <button type="button" id="confirmEmptyTrashBtn"
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

@push('styles')
<style>
/* ========== FORM CONTROLS (shared with index/create) ========== */
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

[data-theme="dark"] .index-custom-dropdown option { background-color: #2a2a3c; color: #e4e4e4; }
[data-theme="light"] .index-custom-dropdown option { background-color: #ffffff; color: #4b4b4b; }

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

.index-custom-input::placeholder { color: var(--text-secondary); opacity: 0.7; }

.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
    accent-color: var(--danger);
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* ========== CARDS ========== */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* ========== BUTTONS ========== */
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
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

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

/* ========== BADGES ========== */
.badge-success { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger  { background-color: rgba(var(--danger-rgb), 0.1)  !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb), 0.3)  !important; }
.badge-info    { background-color: rgba(var(--info-rgb), 0.1)    !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb), 0.3)    !important; }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }

/* ========== SHIFT ICON ========== */
.shift-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ========== ACTION BUTTONS ========== */
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

/* ========== MODAL ========== */
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }

.modal-overlay {
    position: absolute;
    inset: 0;
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
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }

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
.modal-close:hover { background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); }

.modal-body { padding: 1.5rem; }

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
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* ========== LOADING OVERLAY ========== */
.loading-overlay {
    position: fixed;
    inset: 0;
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
    to   { transform: rotate(360deg); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
    .modal-container { margin: 1rem; }
}
@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    .text-5xl { font-size: 2rem !important; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const CSRF_TOKEN = '{{ csrf_token() }}';
    const TRASH_BASE = '{{ url("security/security-shifts/trash") }}';

    // ========== BULK SELECTION ==========
    const selectAllCheckbox = document.getElementById('selectAllTrashedCheckbox');
    const trashedCheckboxes = document.querySelectorAll('.trashed-checkbox');
    const bulkActionBtn     = document.getElementById('trashBulkActionBtn');

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.trashed-checkbox:checked').length;
        if (bulkActionBtn) {
            bulkActionBtn.disabled = selected === 0;
            bulkActionBtn.innerHTML = selected > 0
                ? `<i class="fas fa-play mr-2"></i> Apply (${selected} selected)`
                : `<i class="fas fa-play mr-2"></i> Apply`;
        }
    }

    selectAllCheckbox?.addEventListener('change', function() {
        trashedCheckboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });

    trashedCheckboxes.forEach(cb => cb.addEventListener('change', function() {
        if (selectAllCheckbox) {
            const total   = trashedCheckboxes.length;
            const checked = document.querySelectorAll('.trashed-checkbox:checked').length;
            selectAllCheckbox.checked = checked === total;
            selectAllCheckbox.indeterminate = checked > 0 && checked < total;
        }
        updateSelectedCount();
    }));

    updateSelectedCount();

    window.selectAllTrashed = function() {
        trashedCheckboxes.forEach(cb => cb.checked = true);
        if (selectAllCheckbox) { selectAllCheckbox.checked = true; selectAllCheckbox.indeterminate = false; }
        updateSelectedCount();
    };

    window.deselectAllTrashed = function() {
        trashedCheckboxes.forEach(cb => cb.checked = false);
        if (selectAllCheckbox) { selectAllCheckbox.checked = false; selectAllCheckbox.indeterminate = false; }
        updateSelectedCount();
    };

    // ========== BULK ACTION ==========
    window.performTrashBulkAction = async function() {
        const actionSelect   = document.getElementById('trashBulkActionSelect');
        const selectedAction = actionSelect.value;

        if (!selectedAction) { showToast('Please select an action first', 'warning'); return; }

        const ids = Array.from(document.querySelectorAll('.trashed-checkbox:checked')).map(cb => cb.value);
        if (ids.length === 0) { showToast('Please select at least one shift', 'warning'); return; }

        const confirmMessage = selectedAction === 'restore'
            ? `Are you sure you want to restore ${ids.length} shift(s)?`
            : `Are you sure you want to permanently delete ${ids.length} shift(s)? This action cannot be undone!`;

        if (!confirm(confirmMessage)) return;

        try {
            showLoading(`Processing ${ids.length} shift(s)...`);

            const response = await fetch(`{{ route('security.security-shifts.trash.bulk') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: selectedAction, ids }),
            });

            const data = await response.json();
            hideLoading();

            if (data.success) {
                showToast(data.message, 'success');
                window.deselectAllTrashed();
                actionSelect.value = '';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to perform bulk action', 'error');
            }
        } catch (error) {
            hideLoading();
            console.error('Bulk action error:', error);
            showToast('Network error occurred', 'error');
        }
    };

    // ========== RESTORE ==========
    window.showRestoreModal = function(shiftId, shiftName) {
        document.getElementById('restoreShiftTitle').textContent = shiftName;
        document.getElementById('restoreForm').action = `${TRASH_BASE}/${shiftId}/restore`;
        openModal('restoreModal');
    };

    window.confirmRestore = function() {
        document.getElementById('restoreForm').submit();
    };

    // ========== PERMANENT DELETE ==========
    window.showPermanentDeleteModal = function(shiftId, shiftName) {
        document.getElementById('permanentDeleteShiftTitle').textContent = shiftName;
        const form = document.getElementById('permanentDeleteForm');
        form.action = `${TRASH_BASE}/${shiftId}`;
        openModal('permanentDeleteModal');
    };

    window.confirmPermanentDelete = function() {
        document.getElementById('permanentDeleteForm').submit();
    };

    // ========== EMPTY TRASH ==========
    window.emptyTrash = function() {
        const total = {{ $trashStats['total_trashed'] ?? 0 }};
        if (total === 0) {
            showToast('Trash is already empty', 'info');
            return;
        }
        openModal('emptyTrashModal');
    };

    window.confirmEmptyTrash = async function() {
        // Collect all trashed IDs currently visible on the page.
        // (If you want to empty ALL trash across pages, use a dedicated
        // "clear all" route instead — here we target the current page's IDs.)
        const ids = Array.from(document.querySelectorAll('.trashed-checkbox')).map(cb => cb.value);

        if (ids.length === 0) {
            showToast('No items to delete', 'warning');
            closeModal('emptyTrashModal');
            return;
        }

        try {
            showLoading('Emptying trash...');

            const response = await fetch(`{{ route('security.security-shifts.trash.bulk') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'force_delete', ids }),
            });

            const data = await response.json();
            hideLoading();

            if (data.success) {
                showToast(data.message, 'success');
                closeModal('emptyTrashModal');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to empty trash', 'error');
            }
        } catch (error) {
            hideLoading();
            console.error('Empty trash error:', error);
            showToast('Network error occurred', 'error');
        }
    };

    // ========== AUTO-SUBMIT FILTERS ==========
    document.querySelectorAll('#filterForm select').forEach(select => {
        select.addEventListener('change', () => document.getElementById('filterForm').submit());
    });

    // ========== MODAL HELPERS ==========
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    };

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal:not(.hidden)').forEach(modal => {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            if (document.querySelector('.trashed-checkbox:checked')) {
                window.deselectAllTrashed();
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            const active = document.activeElement;
            if (!active.matches('input, textarea, [contenteditable]')) {
                if (document.querySelector('.trashed-checkbox')) {
                    e.preventDefault();
                    window.selectAllTrashed();
                }
            }
        }
    });

    // ========== TOASTS ==========
    window.showToast = function(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            type === 'error'   ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                                 'bg-blue-100 text-blue-800 border border-blue-200'
        }`;

        const messageEl = document.createElement('span');
        messageEl.className = 'text-sm font-medium flex-1';
        messageEl.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'ml-4';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.onclick = () => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        };

        toast.appendChild(messageEl);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);

        setTimeout(() => {
            if (toast.parentNode === container) {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    };

    // ========== LOADING ==========
    window.showLoading = function(message = 'Processing...') {
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
        const msgEl = document.getElementById('loadingMessage');
        if (msgEl) msgEl.textContent = message;
        overlay.classList.remove('hidden');
    };

    window.hideLoading = function() {
        document.getElementById('loadingOverlay')?.classList.add('hidden');
    };
});
</script>
@endpush