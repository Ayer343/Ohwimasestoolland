@extends('layouts.secu')

@section('title', 'Deleted Security Personnel')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                        Deleted Security Personnel
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage soft-deleted security personnel. Restore or permanently delete accounts.</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $personnel->total() ?? 0 }} deleted personnel</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if(($stats['total_trashed'] ?? 0) > 0)
                <button onclick="confirmEmptyTrash()" 
                        class="btn-danger px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: #dc2626; color: white;">
                    <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                </button>
                @endif
                <a href="{{ route('security.personnel.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
            <span style="color: var(--text-primary);">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
            <span style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    <!-- Info Alert -->
    <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-lg mr-3 mt-0.5" style="color: var(--warning);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Deleted Personnel Management</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Personnel in trash have been soft-deleted. You can <strong>restore</strong> them to reactivate their accounts,
                    or <strong>permanently delete</strong> them to remove all data. Users deleted over 30 days ago are eligible for permanent deletion.
                </p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['total_trashed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Deleted</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash-alt" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['trashed_this_week'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted This Week</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-calendar-week" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['trashed_this_month'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted This Month</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-alt" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['old_deleted_count'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Over 30 Days Old</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--danger);"></i>
                </div>
            </div>
            @if(($stats['old_deleted_count'] ?? 0) > 0)
            <div class="mt-2 text-xs">
                <span style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Eligible for permanent deletion
                </span>
            </div>
            @endif
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Deleted Personnel
        </h3>
        
        <form method="GET" action="{{ route('security.personnel.trash') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <input type="text"
                       id="search"
                       name="search"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Search by name or email..."
                       value="{{ request('search') }}">
            </div>
            
            <div>
                <label for="deleted_by" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-user-cog mr-1" style="color: var(--primary);"></i> Deleted By
                </label>
                <select id="deleted_by"
                        name="deleted_by"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Users</option>
                    @foreach($deletedByUsers ?? [] as $user)
                        <option value="{{ $user->id }}" {{ request('deleted_by') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label for="filter" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Time Filter
                </label>
                <select id="filter"
                        name="filter"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Time</option>
                    <option value="today" {{ request('filter') == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ request('filter') == 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ request('filter') == 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="old" {{ request('filter') == 'old' ? 'selected' : '' }}>Over 30 Days Old</option>
                </select>
            </div>
            
            <div class="flex items-end space-x-3">
                <a href="{{ route('security.personnel.trash') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Personnel Table -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Deleted Personnel
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-danger">
                    {{ $personnel->total() }} deleted
                </span>
                @if(request('filter') == 'old')
                    <span class="ml-2 px-2.5 py-1 text-xs rounded-full badge-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Eligible for permanent deletion
                    </span>
                @endif
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i> Last updated: {{ now()->format('H:i') }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Contact</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Deleted</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Deleted By</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reason</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personnel as $person)
                        @php
                            // Get deletion metadata
                            $metadata = [];
                            if ($person->metadata) {
                                if (is_string($person->metadata)) {
                                    $metadata = json_decode($person->metadata, true) ?? [];
                                } elseif (is_array($person->metadata)) {
                                    $metadata = $person->metadata;
                                }
                            }
                            
                            $deletionInfo = $metadata['deletion_info'] ?? [];
                            $deletedByName = $deletionInfo['deleted_by_name'] ?? 'System';
                            $deletionReason = $deletionInfo['deletion_reason'] ?? 'Not specified';
                            $deletedAt = $person->deleted_at ? Carbon\Carbon::parse($person->deleted_at) : null;
                            $daysDeleted = $deletedAt ? $deletedAt->diffInDays(now()) : null;
                            $isOld = $daysDeleted !== null && $daysDeleted > 30;
                            
                            // Format phone
                            $formattedPhone = $person->phone;
                            if ($person->phone) {
                                if (preg_match('/^\+233(\d{9})$/', $person->phone, $matches)) {
                                    $formattedPhone = '0' . $matches[1];
                                }
                            }
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200 {{ $isOld ? 'bg-opacity-5' : '' }}" 
                            style="border-color: var(--border-color); background-color: var(--card-bg); {{ $isOld ? 'background-color: rgba(var(--warning-rgb), 0.03);' : '' }}">
                            
                            <td class="py-4 px-6">
                                <input type="checkbox" class="user-checkbox rounded border-gray-300" value="{{ $person->id }}">
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white;">
                                        {{ substr($person->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            {{ $person->name }}
                                            @if($isOld)
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full" 
                                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ $daysDeleted }} days
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-id-badge mr-1"></i> ID: {{ $person->id }}
                                            <span class="ml-2">
                                                <i class="fas fa-user-tag mr-1"></i> 
                                                {{ ucfirst(str_replace('_', ' ', $person->type)) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">{{ $person->email }}</div>
                                <div class="flex items-center mt-1">
                                    @if($person->phone)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i> 
                                            <span style="color: var(--text-primary);">{{ $formattedPhone }}</span>
                                        </span>
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i> No phone
                                        </span>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    {{ $deletedAt ? $deletedAt->format('M j, Y H:i') : 'Unknown' }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $deletedAt ? $deletedAt->diffForHumans() : 'Unknown' }}
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">{{ $deletedByName }}</div>
                                @if($deletionInfo['deleted_by'] ?? false)
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-id-badge mr-1"></i> ID: {{ $deletionInfo['deleted_by'] }}
                                    </div>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    {{ Str::limit($deletionReason, 50) }}
                                </div>
                                @if(strlen($deletionReason) > 50)
                                    <button onclick="showFullReason('{{ addslashes($deletionReason) }}')" 
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-expand mr-1"></i> View full
                                    </button>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    <!-- Restore Button -->
                                    <button onclick="restorePersonnel({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                            title="Restore Personnel">
                                        <i class="fas fa-undo-alt text-sm"></i>
                                    </button>
                                    
                                    <!-- Permanent Delete Button -->
                                    <button onclick="permanentDelete({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                            title="Permanently Delete">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-trash-alt text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Deleted Personnel Found</h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        @if(request('search') || request('deleted_by') || request('filter'))
                                            No results match your filters. 
                                            <a href="{{ route('security.personnel.trash') }}" style="color: var(--primary);" class="hover:underline">
                                                Clear all filters
                                            </a>
                                        @else
                                            The trash is empty. All deleted personnel have been restored or permanently removed.
                                        @endif
                                    </p>
                                    <a href="{{ route('security.personnel.index') }}" class="btn-primary px-4 py-2 rounded-lg">
                                        <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Actions -->
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-sm" style="color: var(--text-secondary);">Bulk Actions:</span>
                    <select id="bulk-action" class="index-custom-dropdown px-3 py-1.5 rounded-lg text-sm"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">Choose Action</option>
                        <option value="restore">Restore Selected</option>
                        <option value="permanent_delete">Permanently Delete Selected</option>
                    </select>
                    <button onclick="performBulkAction()" class="btn-primary px-4 py-1.5 rounded-lg text-sm">
                        <i class="fas fa-play mr-2"></i> Apply
                    </button>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <span id="selected-count">0</span> selected
                </div>
            </div>
        </div>
        
        @if(method_exists($personnel, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $personnel->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Restore Personnel Modal -->
<div id="restorePersonnelModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Restore Personnel</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="restorePersonnelName" class="mb-4"></p>
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--success);"></i>
                        Restoring this personnel will reactivate their account and set their status to <strong>Pending</strong>.
                        Any previous assignments or schedules will need to be reassigned if needed.
                    </p>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeRestoreModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="confirmRestorePersonnel()" class="btn-success px-4 py-2 rounded-lg" id="restoreConfirmBtn">
                    <i class="fas fa-undo-alt mr-2"></i> Restore Personnel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Permanently Delete Personnel</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="permanentDeleteName" class="mb-4"></p>
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                        <strong>Warning:</strong> This action is <strong>permanent</strong> and cannot be undone. 
                        All associated data including schedules, assignments, and profile information will be permanently removed.
                    </p>
                </div>
                <div class="mt-4">
                    <label for="permanent_delete_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Reason for Permanent Deletion (Optional)
                    </label>
                    <textarea id="permanent_delete_reason" rows="2" 
                              class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Enter reason for permanent deletion..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closePermanentDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="confirmPermanentDelete()" class="btn-danger px-4 py-2 rounded-lg" id="permanentDeleteConfirmBtn">
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Full Reason Modal -->
<div id="fullReasonModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Deletion Reason</h3>
                </div>
            </div>
            <div class="p-6">
                <p class="text-sm" style="color: var(--text-primary);" id="fullReasonContent"></p>
            </div>
            <div class="p-6 border-t flex justify-end" style="border-color: var(--border-color);">
                <button onclick="closeFullReasonModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
.action-btn {
    transition: all 0.2s ease;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
tbody tr {
    transition: background-color 0.2s ease;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3);
}
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.index-custom-dropdown, .index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}
</style>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// ==================== RESTORE PERSONNEL ====================
let restoreUserId = null;
let restoreUserName = null;

function restorePersonnel(id, name) {
    restoreUserId = id;
    restoreUserName = name;
    document.getElementById('restorePersonnelName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Restore <strong>${name}</strong> from trash?
    `;
    document.getElementById('restorePersonnelModal').classList.remove('hidden');
}

function closeRestoreModal() {
    document.getElementById('restorePersonnelModal').classList.add('hidden');
    restoreUserId = null;
    restoreUserName = null;
}

function confirmRestorePersonnel() {
    if (!restoreUserId) return;
    
    const confirmBtn = document.getElementById('restoreConfirmBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
    
    fetch(`/security/personnel/${restoreUserId}/restore`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel restored successfully!');
            closeRestoreModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to restore personnel.');
        }
    })
    .catch(error => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Restore error:', error);
    });
}

// ==================== PERMANENT DELETE ====================
let permanentDeleteUserId = null;
let permanentDeleteUserName = null;

function permanentDelete(id, name) {
    permanentDeleteUserId = id;
    permanentDeleteUserName = name;
    document.getElementById('permanentDeleteName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Permanently delete <strong>${name}</strong>?
    `;
    document.getElementById('permanent_delete_reason').value = '';
    document.getElementById('permanentDeleteModal').classList.remove('hidden');
}

function closePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
    permanentDeleteUserId = null;
    permanentDeleteUserName = null;
}

function confirmPermanentDelete() {
    if (!permanentDeleteUserId) return;
    
    const reason = document.getElementById('permanent_delete_reason').value;
    const confirmBtn = document.getElementById('permanentDeleteConfirmBtn');
    const originalText = confirmBtn.innerHTML;
    
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    fetch(`/security/personnel/${permanentDeleteUserId}/force-delete`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ deletion_reason: reason || 'Permanent deletion from trash' })
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel permanently deleted successfully!');
            closePermanentDeleteModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to permanently delete personnel.');
        }
    })
    .catch(error => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Permanent delete error:', error);
    });
}

// ==================== FULL REASON MODAL ====================
let fullReasonText = '';

function showFullReason(reason) {
    fullReasonText = reason;
    document.getElementById('fullReasonContent').textContent = reason;
    document.getElementById('fullReasonModal').classList.remove('hidden');
}

function closeFullReasonModal() {
    document.getElementById('fullReasonModal').classList.add('hidden');
}

// ==================== BULK ACTIONS ====================
function performBulkAction() {
    const action = document.getElementById('bulk-action').value;
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    
    if (!action) {
        showToast('warning', 'Please select an action.');
        return;
    }
    
    if (selectedUsers.length === 0) {
        showToast('warning', 'Please select at least one user.');
        return;
    }
    
    let confirmMessage = '';
    if (action === 'restore') {
        confirmMessage = `Restore ${selectedUsers.length} selected personnel?`;
    } else if (action === 'permanent_delete') {
        confirmMessage = `⚠️ PERMANENTLY DELETE ${selectedUsers.length} selected personnel? This cannot be undone!`;
    }
    
    if (!confirm(confirmMessage)) return;
    
    let endpoint = '';
    let method = 'POST';
    let body = { user_ids: selectedUsers };
    
    if (action === 'restore') {
        endpoint = '/security/personnel/bulk/restore';
    } else if (action === 'permanent_delete') {
        endpoint = '/security/personnel/bulk/permanent-delete';
        body.deletion_reason = 'Bulk permanent deletion from trash';
    }
    
    showToast('info', 'Processing bulk action...');
    
    fetch(endpoint, {
        method: method,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Bulk action completed successfully!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Bulk action failed.');
        }
    })
    .catch(error => {
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Bulk action error:', error);
    });
}

// ==================== SELECT ALL ====================
document.getElementById('select-all')?.addEventListener('change', function(e) {
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
    updateSelectedCount();
});

// ==================== UPDATE SELECTED COUNT ====================
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('user-checkbox')) {
        updateSelectedCount();
    }
});

function updateSelectedCount() {
    const count = document.querySelectorAll('.user-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
}

// ==================== EMPTY TRASH ====================
function confirmEmptyTrash() {
    if (confirm('⚠️ WARNING: This will permanently delete ALL users in the trash. This action cannot be undone. Continue?')) {
        const confirmation = prompt('Type "empty_all_trash" to confirm permanent deletion:');
        if (confirmation === 'empty_all_trash') {
            showToast('info', 'Emptying trash...');
            
            fetch('/security/personnel/empty-trash', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ confirmation: 'empty_all_trash' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showToast('error', data.message || 'Failed to empty trash.');
                }
            })
            .catch(error => {
                showToast('error', 'An error occurred: ' + error.message);
                console.error('Empty trash error:', error);
            });
        } else {
            showToast('warning', 'Trash empty cancelled.');
        }
    }
}

// ==================== TOAST NOTIFICATIONS ====================
function showToast(type, message) {
    const existingToast = document.querySelector('.custom-toast');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 16px 24px;
        border-radius: 12px;
        background: var(--card-bg);
        border-left: 4px solid ${colors[type] || 'var(--info)'};
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border: 1px solid var(--border-color);
    `;
    
    toast.innerHTML = `
        <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 1.2rem;"></i>
        <span style="color: var(--text-primary); font-size: 0.9rem;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; margin-left: 8px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// ==================== MODAL CLOSE ON OUTSIDE CLICK ====================
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeRestoreModal();
        closePermanentDeleteModal();
        closeFullReasonModal();
    }
}

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRestoreModal();
        closePermanentDeleteModal();
        closeFullReasonModal();
    }
});

// Add animation keyframes if not already present
if (!document.getElementById('toast-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'toast-styles';
    styleSheet.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    `;
    document.head.appendChild(styleSheet);
}
</script>
@endsection