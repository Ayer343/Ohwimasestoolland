@extends('layouts.app')

@section('title', 'Trashed Supervisor Assignments')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%); color: white; font-weight: 600; border-color: var(--warning);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i>
                        Trashed Supervisor Assignments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage and restore soft-deleted supervisor assignments</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-trash-restore mr-1"></i>
                        <span>{{ $assignments->total() }} items in trash</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
                </a>
                
                @if($assignments->total() > 0)
                <button onclick="showBulkRestoreModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-trash-restore mr-2"></i> Bulk Restore
                </button>
                
                <button onclick="showBulkForceDeleteModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Bulk Delete Permanently
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $assignments->total() }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Trashed</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-trash-alt" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            @php
                $postSupervisors = $assignments->filter(function($item) {
                    return $item->supervisor_type === 'post_supervisor';
                })->count();
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $postSupervisors }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Post Supervisors</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-flag" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            @php
                $shiftSupervisors = $assignments->filter(function($item) {
                    return $item->supervisor_type === 'shift_supervisor';
                })->count();
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $shiftSupervisors }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Shift Supervisors</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            @php
                $areaSupervisors = $assignments->filter(function($item) {
                    return $item->supervisor_type === 'area_supervisor';
                })->count();
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $areaSupervisors }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Area Supervisors</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-map-marked-alt" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--warning);"></i> Filter Trashed Assignments
        </h3>
        
        <form method="GET" action="{{ route('admin.supervisor-assignments.trash.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Supervisor Type Filter -->
            <div>
                <label for="supervisor_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-tag mr-1" style="color: var(--warning);"></i> Supervisor Type
                </label>
                <select id="supervisor_type"
                        name="supervisor_type"
                        class="trash-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Types</option>
                    @foreach($supervisorTypes ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ request('supervisor_type') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Post Filter -->
            <div>
                <label for="post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-1" style="color: var(--warning);"></i> Security Post
                </label>
                <select id="post_id"
                        name="post_id"
                        class="trash-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Posts</option>
                    @foreach($posts ?? [] as $post)
                        <option value="{{ $post->id }}" {{ request('post_id') == $post->id ? 'selected' : '' }}>
                            {{ $post->name }} ({{ $post->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Deleted Date Range - Start -->
            <div>
                <label for="deleted_from" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-1" style="color: var(--warning);"></i> Deleted From
                </label>
                <input type="date"
                       id="deleted_from"
                       name="deleted_from"
                       class="trash-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('deleted_from') }}">
            </div>
            
            <!-- Deleted Date Range - To -->
            <div>
                <label for="deleted_to" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-check mr-1" style="color: var(--warning);"></i> Deleted To
                </label>
                <input type="date"
                       id="deleted_to"
                       name="deleted_to"
                       class="trash-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('deleted_to') }}">
            </div>
            
            <!-- Search -->
            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--warning);"></i> Search
                </label>
                <div class="relative">
                    <input type="text"
                           id="search"
                           name="search"
                           class="trash-custom-input w-full pl-10"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Search by supervisor or post..."
                           value="{{ request('search') }}">
                    <i class="fas fa-search absolute left-3 top-3" style="color: var(--text-secondary);"></i>
                </div>
            </div>
            
            <!-- Filter Actions -->
            <div class="md:col-span-5 flex justify-end space-x-3 mt-4">
                <a href="{{ route('admin.supervisor-assignments.trash.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
                <button type="submit"
                        class="btn-warning px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Trashed Assignments Table Card -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i> Trashed Assignments
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-warning">
                    {{ $assignments->total() }} items
                </span>
            </h3>
            
            <div class="flex items-center">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" 
                           id="selectAll" 
                           class="rounded border-gray-300 text-warning focus:ring-warning"
                           style="accent-color: var(--warning);">
                    <span class="text-sm" style="color: var(--text-secondary);">Select All</span>
                </label>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary); width: 40px;">
                            <i class="fas fa-check-square"></i>
                        </th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Supervisor</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post & Type</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assignment Period</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Deleted By</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php
                            $deletedBy = null;
                            $deletedAt = $assignment->deleted_at ? \Carbon\Carbon::parse($assignment->deleted_at) : null;
                            
                            if ($assignment->metadata && isset($assignment->metadata['deleted_by'])) {
                                $deletedBy = \App\Models\User::find($assignment->metadata['deleted_by']);
                            }
                            
                            $daysInTrash = $deletedAt ? $deletedAt->diffInDays(now()) : 0;
                            
                            $typeColors = [
                                'post_supervisor' => 'primary',
                                'shift_supervisor' => 'info',
                                'area_supervisor' => 'success',
                                'relief_supervisor' => 'warning',
                                'training_supervisor' => 'secondary'
                            ];
                            $typeColor = $typeColors[$assignment->supervisor_type] ?? 'secondary';
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200 trash-row" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);"
                            data-id="{{ $assignment->id }}">
                            
                            <td class="py-4 px-6" onclick="event.stopPropagation();">
                                <input type="checkbox" 
                                       class="row-select rounded border-gray-300 text-warning focus:ring-warning"
                                       style="accent-color: var(--warning);"
                                       value="{{ $assignment->id }}">
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%); color: white;">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $assignment->user->name ?? 'Unknown Supervisor' }}
                                        </div>
                                        <div class="flex items-center text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-envelope mr-1"></i> 
                                            {{ $assignment->user->email ?? 'No email' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($assignment->post)
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $assignment->post->name }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-code mr-1"></i> {{ $assignment->post->code }}
                                    </div>
                                @else
                                    <div class="font-medium" style="color: var(--text-primary);">All Posts</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Area-wide supervision</div>
                                @endif
                                <div class="mt-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-{{ $typeColor }}">
                                        <i class="fas fa-{{ $assignment->supervisor_type === 'post_supervisor' ? 'flag' : ($assignment->supervisor_type === 'shift_supervisor' ? 'clock' : 'user-tag') }} mr-1"></i>
                                        {{ $supervisorTypes[$assignment->supervisor_type] ?? ucfirst(str_replace('_', ' ', $assignment->supervisor_type)) }}
                                    </span>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex flex-col">
                                    <div class="flex items-center">
                                        <i class="fas fa-play-circle text-xs mr-1" style="color: var(--success);"></i>
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ $assignment->start_date instanceof \Carbon\Carbon ? $assignment->start_date->format('M j, Y') : \Carbon\Carbon::parse($assignment->start_date)->format('M j, Y') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <i class="fas fa-stop-circle text-xs mr-1" style="color: {{ $assignment->end_date ? 'var(--warning)' : 'var(--info)' }};"></i>
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ $assignment->end_date ? (\Carbon\Carbon::parse($assignment->end_date)->format('M j, Y')) : 'Indefinite' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $deletedAt ? $deletedAt->format('M j, Y g:i A') : 'Unknown' }}
                                    </span>
                                    <span class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i> {{ $daysInTrash }} days in trash
                                    </span>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($deletedBy)
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                            <i class="fas fa-user-cog text-xs" style="color: var(--text-secondary);"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm" style="color: var(--text-primary);">{{ $deletedBy->name }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $deletedBy->email }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">Unknown</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6" onclick="event.stopPropagation();">
                                <div class="flex space-x-2">
                                    <button onclick="restoreSingle({{ $assignment->id }}, '{{ addslashes($assignment->user->name ?? 'Unknown') }}')"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                            title="Restore Assignment">
                                        <i class="fas fa-trash-restore text-sm"></i>
                                    </button>
                                    
                                    <button onclick="forceDeleteSingle({{ $assignment->id }}, '{{ addslashes($assignment->user->name ?? 'Unknown') }}')"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                            title="Delete Permanently">
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
                                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                                        <i class="fas fa-trash-alt text-3xl" style="color: var(--warning);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Trash is Empty</h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        {{ request()->anyFilled(['supervisor_type', 'post_id', 'deleted_from', 'deleted_to', 'search']) 
                                            ? 'No trashed assignments match your filters' 
                                            : 'No supervisor assignments have been deleted yet' }}
                                    </p>
                                    @if(request()->anyFilled(['supervisor_type', 'post_id', 'deleted_from', 'deleted_to', 'search']))
                                        <a href="{{ route('admin.supervisor-assignments.trash.index') }}" 
                                           class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                                            <i class="fas fa-times mr-2"></i> Clear Filters
                                        </a>
                                    @else
                                        <a href="{{ route('admin.supervisor-assignments.index') }}" 
                                           class="btn-primary px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center">
                                            <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Actions Bar -->
        @if($assignments->total() > 0)
        <div class="p-4 border-t" style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.02);">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span class="text-sm font-medium" style="color: var(--text-primary);" id="selectedCount">0 items selected</span>
                    
                    <button onclick="restoreSelected()"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);"
                            id="restoreSelectedBtn"
                            disabled>
                        <i class="fas fa-trash-restore mr-2"></i> Restore Selected
                    </button>
                    
                    <button onclick="forceDeleteSelected()"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                            id="forceDeleteSelectedBtn"
                            disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </div>
                
                <div class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Items in trash for more than 30 days are automatically deleted
                </div>
            </div>
        </div>
        @endif
        
        <!-- Pagination -->
        @if(method_exists($assignments, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $assignments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Restore Single Modal -->
<div id="restoreModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('restoreModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-restore mr-2" style="color: var(--success);"></i>
                    Restore Assignment
                </h3>
                <button type="button" onclick="closeModal('restoreModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p style="color: var(--text-primary);" id="restoreSupervisorName"></p>
                <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                    Are you sure you want to restore this assignment? It will become active again.
                </p>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('restoreModal')">
                    Cancel
                </button>
                <form id="restoreForm" method="POST" action="" class="inline">
                    @csrf
                    @method('POST')
                    <button type="submit" class="btn-success px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-restore mr-2"></i> Restore Assignment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Force Delete Single Modal -->
<div id="forceDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('forceDeleteModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                    Permanently Delete Assignment
                </h3>
                <button type="button" onclick="closeModal('forceDeleteModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <p style="color: var(--text-primary);" id="forceDeleteSupervisorName"></p>
                    <p class="mt-2 text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Warning: This action cannot be undone. The assignment will be permanently deleted.
                    </p>
                </div>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('forceDeleteModal')">
                    Cancel
                </button>
                <form id="forceDeleteForm" method="POST" action="" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ✅ FIXED: Bulk Restore Modal with dynamic hidden inputs -->
<div id="bulkRestoreModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('bulkRestoreModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-restore mr-2" style="color: var(--success);"></i>
                    Bulk Restore Assignments
                </h3>
                <button type="button" onclick="closeModal('bulkRestoreModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p style="color: var(--text-primary);" id="bulkRestoreCount"></p>
                <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                    Are you sure you want to restore these assignments? They will become active again.
                </p>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('bulkRestoreModal')">
                    Cancel
                </button>
                <form id="bulkRestoreForm" method="POST" action="{{ route('admin.supervisor-assignments.bulk.restore') }}" class="inline">
                    @csrf
                    @method('POST')
                    <!-- ✅ FIXED: Container for dynamic hidden inputs -->
                    <div id="bulkRestoreIdsContainer"></div>
                    <button type="submit" class="btn-success px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-restore mr-2"></i> Restore Selected
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ✅ FIXED: Bulk Force Delete Modal with dynamic hidden inputs -->
<div id="bulkForceDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('bulkForceDeleteModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                    Bulk Permanently Delete
                </h3>
                <button type="button" onclick="closeModal('bulkForceDeleteModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <p style="color: var(--text-primary);" id="bulkForceDeleteCount"></p>
                    <p class="mt-2 text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Warning: This action cannot be undone. Selected assignments will be permanently deleted.
                    </p>
                </div>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('bulkForceDeleteModal')">
                    Cancel
                </button>
                <form id="bulkForceDeleteForm" method="POST" action="{{ route('admin.supervisor-assignments.bulk.force-delete') }}" class="inline">
                    @csrf
                    @method('POST')
                    <!-- ✅ FIXED: Container for dynamic hidden inputs -->
                    <div id="bulkForceDeleteIdsContainer"></div>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Action button styles */
.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Table row hover effect */
tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--warning-rgb), 0.02) !important;
}

/* Modal animations */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-show {
    animation: modalFadeIn 0.2s ease-out;
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
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--warning-rgb), 0.3);
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

.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3);
}

/* Custom scrollbar for dropdowns */
.trash-custom-dropdown, .trash-custom-input {
    transition: all 0.2s ease;
}

.trash-custom-dropdown:focus, .trash-custom-input:focus {
    border-color: var(--warning) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--warning-rgb), 0.2);
}
</style>

<script>
let selectedIds = new Set();

// Modal functions
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('modal-show');
}

// Single restore
function restoreSingle(id, supervisorName) {
    document.getElementById('restoreSupervisorName').innerHTML = `Restore assignment for <strong>${supervisorName}</strong>`;
    document.getElementById('restoreForm').action = `/admin/supervisor-assignments/trash/restore/${id}`;
    
    document.getElementById('restoreModal').classList.remove('hidden');
    document.getElementById('restoreModal').classList.add('modal-show');
}

// Single force delete
function forceDeleteSingle(id, supervisorName) {
    document.getElementById('forceDeleteSupervisorName').innerHTML = `Permanently delete assignment for <strong>${supervisorName}</strong>`;
    document.getElementById('forceDeleteForm').action = `/admin/supervisor-assignments/trash/force-delete/${id}`;
    
    document.getElementById('forceDeleteModal').classList.remove('hidden');
    document.getElementById('forceDeleteModal').classList.add('modal-show');
}

// ✅ FIXED: Bulk restore with AJAX
function restoreSelected() {
    if (selectedIds.size === 0) {
        alert('Please select at least one item to restore.');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ${selectedIds.size} selected assignment(s)?`)) {
        return;
    }
    
    // Build form data
    const formData = new FormData();
    selectedIds.forEach(id => {
        formData.append('ids[]', id);
    });
    formData.append('_token', '{{ csrf_token() }}');
    
    // Show loading state
    const restoreBtn = document.getElementById('restoreSelectedBtn');
    const originalText = restoreBtn.innerHTML;
    restoreBtn.disabled = true;
    restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
    
    // Send AJAX request
    fetch('{{ route('admin.supervisor-assignments.bulk.restore') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        restoreBtn.innerHTML = originalText;
        restoreBtn.disabled = false;
        
        if (data.success) {
            alert(data.message || 'Assignments restored successfully!');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to restore assignments.');
        }
    })
    .catch(error => {
        restoreBtn.innerHTML = originalText;
        restoreBtn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred while restoring assignments.');
    });
}

// ✅ FIXED: Bulk force delete with AJAX
function forceDeleteSelected() {
    if (selectedIds.size === 0) {
        alert('Please select at least one item to permanently delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to permanently delete ${selectedIds.size} selected assignment(s)? This action cannot be undone.`)) {
        return;
    }
    
    // Build form data
    const formData = new FormData();
    selectedIds.forEach(id => {
        formData.append('ids[]', id);
    });
    formData.append('_token', '{{ csrf_token() }}');
    
    // Show loading state
    const deleteBtn = document.getElementById('forceDeleteSelectedBtn');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    // Send AJAX request
    fetch('{{ route('admin.supervisor-assignments.bulk.force-delete') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
        
        if (data.success) {
            alert(data.message || 'Assignments permanently deleted!');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to delete assignments.');
        }
    })
    .catch(error => {
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred while deleting assignments.');
    });
}

// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.row-select');
    checkboxes.forEach(checkbox => {
        checkbox.checked = e.target.checked;
        const id = parseInt(checkbox.value);
        if (e.target.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
    });
    updateSelectedCount();
    updateBulkButtons();
});

// Individual checkbox selection
document.querySelectorAll('.row-select').forEach(checkbox => {
    checkbox.addEventListener('change', function(e) {
        e.stopPropagation();
        const id = parseInt(this.value);
        
        if (this.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
            
            // Uncheck select all if any checkbox is unchecked
            document.getElementById('selectAll').checked = false;
        }
        
        updateSelectedCount();
        updateBulkButtons();
    });
});

// Update selected count display
function updateSelectedCount() {
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = `${selectedIds.size} item${selectedIds.size !== 1 ? 's' : ''} selected`;
    }
}

// Update bulk buttons state
function updateBulkButtons() {
    const restoreBtn = document.getElementById('restoreSelectedBtn');
    const deleteBtn = document.getElementById('forceDeleteSelectedBtn');
    
    if (restoreBtn && deleteBtn) {
        const hasSelection = selectedIds.size > 0;
        restoreBtn.disabled = !hasSelection;
        deleteBtn.disabled = !hasSelection;
        
        if (hasSelection) {
            restoreBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            restoreBtn.classList.add('opacity-50', 'cursor-not-allowed');
            deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('restoreModal');
        closeModal('forceDeleteModal');
        closeModal('bulkRestoreModal');
        closeModal('bulkForceDeleteModal');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
    updateBulkButtons();
    
    // Prevent row click when clicking checkboxes
    document.querySelectorAll('.row-select').forEach(checkbox => {
        checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>
@endsection