@extends('layouts.secu')

@section('title', 'Trash - Security Posts')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash mr-2" style="color: var(--danger);"></i>
                        Trash - Deleted Security Posts
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-tie mr-2"></i>
                        <span>{{ auth()->user()->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-trash mr-1"></i>
                            {{ $trashedPosts->total() ?? 0 }} deleted posts
                        </span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: var(--danger);">
                            <i class="fas fa-user-tie mr-1"></i>
                            Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.posts.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Posts
                </a>
                
                @if($trashedPosts->total() > 0)
                    <button type="button" onclick="confirmEmptyTrash()"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center text-white"
                            style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    @if(isset($trashStats))
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $trashStats['total_trashed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Deleted</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashStats['recently_deleted'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted This Week</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $trashStats['trashed_this_month'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted This Month</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">
                        {{ count($trashStats['by_type'] ?? []) }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Post Types</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Search & Filters -->
    <div class="card p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <form method="GET" action="{{ route('security.posts.trash') }}" class="flex flex-1 flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search deleted posts by name, code, or location..."
                               class="w-full pl-10 pr-4 py-2 rounded-lg"
                               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <select name="type" class="px-4 py-2 rounded-lg"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <option value="">All Types</option>
                        <option value="main_gate" {{ request('type') == 'main_gate' ? 'selected' : '' }}>Main Gate</option>
                        <option value="internal_gate" {{ request('type') == 'internal_gate' ? 'selected' : '' }}>Internal Gate</option>
                        <option value="checkpoint" {{ request('type') == 'checkpoint' ? 'selected' : '' }}>Checkpoint</option>
                        <option value="patrol_route" {{ request('type') == 'patrol_route' ? 'selected' : '' }}>Patrol Route</option>
                        <option value="observation_post" {{ request('type') == 'observation_post' ? 'selected' : '' }}>Observation Post</option>
                        <option value="control_room" {{ request('type') == 'control_room' ? 'selected' : '' }}>Control Room</option>
                        <option value="access_point" {{ request('type') == 'access_point' ? 'selected' : '' }}>Access Point</option>
                    </select>

                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    @if(request()->has('search') || request()->has('type'))
                        <a href="{{ route('security.posts.trash') }}" class="px-3 py-2 rounded-lg text-sm font-medium"
                           style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Trashed Posts Grid -->
    @if(isset($trashedPosts) && $trashedPosts->count() > 0)
        <div class="flex justify-between items-center">
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-layer-group mr-1"></i>
                Showing {{ $trashedPosts->firstItem() ?? 0 }} - {{ $trashedPosts->lastItem() ?? 0 }} of {{ $trashedPosts->total() ?? 0 }} deleted posts
            </div>
            
            {{-- Bulk Actions --}}
            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleAllCheckboxes()"
                        class="px-3 py-1 rounded-lg text-xs font-medium"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-check-double mr-1"></i> Select All
                </button>
                <button type="button" onclick="bulkRestore()"
                        class="px-3 py-1 rounded-lg text-xs font-medium"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo mr-1"></i> Restore Selected
                </button>
                <button type="button" onclick="bulkPermanentDelete()"
                        class="px-3 py-1 rounded-lg text-xs font-medium"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-times-circle mr-1"></i> Delete Selected
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
            @foreach($trashedPosts as $post)
                <div class="card p-6 hover:shadow-lg transition-shadow duration-300 relative border-l-4" 
                     style="border-left-color: var(--danger);">
                    
                    {{-- Checkbox for bulk actions --}}
                    <div class="absolute top-3 left-3">
                        <input type="checkbox" 
                               class="post-checkbox rounded" 
                               data-post-id="{{ $post->id }}"
                               style="border-color: var(--border-color);">
                    </div>

                    <div class="flex items-start justify-between ml-8">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 flex-shrink-0"
                                 style="background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%); color: white; font-size: 18px; font-weight: 600;">
                                {{ substr($post->name, 0, 1) }}
                            </div>
                            <div>
                                <h4 class="font-semibold" style="color: var(--text-primary);">{{ $post->name }}</h4>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-tag mr-1"></i>
                                    {{ $post->code ?? 'N/A' }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    {{ $post->location ?? 'No location' }}
                                </div>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full" 
                              style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash mr-1"></i> Deleted
                        </span>
                    </div>
                    
                    <div class="mt-3 flex items-center space-x-4 ml-8">
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-users mr-1"></i>
                            {{ $post->total_assignments ?? 0 }} assignments
                        </span>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i>
                            {{ $post->getWorkingHoursFormatted() ?? '24/7' }}
                        </span>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-tag mr-1"></i>
                            {{ ucfirst(str_replace('_', ' ', $post->type)) }}
                        </span>
                    </div>
                    
                    {{-- Deletion Info --}}
                    <div class="mt-3 p-3 rounded-lg border" 
                         style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.1);">
                        <div class="flex items-center justify-between text-xs">
                            <div>
                                <span style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i>
                                    Deleted by: 
                                    <span style="color: var(--text-primary);">
                                        {{ $post->deleter ? $post->deleter->name : 'System' }}
                                    </span>
                                </span>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <span style="color: var(--text-primary);">
                                        {{ $post->deleted_at ? $post->deleted_at->format('M j, Y g:i A') : 'Unknown' }}
                                    </span>
                                </span>
                            </div>
                        </div>
                        @if($post->deletion_reason ?? false)
                            <div class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Reason: <span style="color: var(--text-primary);">{{ $post->deletion_reason }}</span>
                            </div>
                        @endif
                    </div>
                    
                    <div class="mt-4 flex space-x-2 ml-8">
                        <button type="button" onclick="restorePost({{ $post->id }})"
                                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-undo mr-1"></i> Restore
                        </button>
                        <button type="button" onclick="confirmForceDelete({{ $post->id }}, '{{ addslashes($post->name) }}')"
                                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times-circle mr-1"></i> Permanently Delete
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-6">
            {{ $trashedPosts->withQueryString()->links() }}
        </div>
    @else
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                <i class="fas fa-check-circle text-4xl" style="color: var(--success);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">Trash is Empty</h3>
            <p class="mb-6" style="color: var(--text-secondary);">
                {{ request()->has('search') ? 'No deleted posts match your search criteria.' : 'There are no deleted security posts in the trash.' }}
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                @if(request()->has('search') || request()->has('type'))
                    <a href="{{ route('security.posts.trash') }}" class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-undo mr-2"></i> Clear Filters
                    </a>
                @endif
                
                <a href="{{ route('security.posts.index') }}" class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Posts
                </a>
            </div>
        </div>
    @endif

    {{-- Quick Actions --}}
    @if(isset($trashedPosts) && $trashedPosts->count() > 0)
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Quick Actions</h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Manage deleted security posts
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <a href="{{ route('security.posts.index') }}" 
                       class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200 no-underline"
                       style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="font-medium" style="color: var(--text-primary);">Active Posts</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">View active security posts</div>
                    </a>
                    
                    <a href="{{ route('security.posts.create') }}" 
                       class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200 no-underline"
                       style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="font-medium" style="color: var(--text-primary);">Create Post</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Create a new security post</div>
                    </a>
                    
                    <button type="button" onclick="confirmEmptyTrash()"
                            class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200"
                            style="border-color: var(--border-color); background-color: var(--bg-secondary); cursor: pointer;">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <div class="font-medium" style="color: var(--text-primary);">Empty Trash</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Permanently delete all</div>
                    </button>
                    
                    <button type="button" onclick="restoreAllPosts()"
                            class="border rounded-lg p-4 text-center hover-lift transition-colors duration-200"
                            style="border-color: var(--border-color); background-color: var(--bg-secondary); cursor: pointer;">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-undo-alt"></i>
                        </div>
                        <div class="font-medium" style="color: var(--text-primary);">Restore All</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Restore all deleted posts</div>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Confirmation Modals -->
<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center">
                <i class="fas fa-undo mr-2" style="color: var(--success);"></i> Restore Post
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('restoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-primary);" id="restoreModalMessage">
                Are you sure you want to restore this post?
            </p>
            <div class="mt-4 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--warning);"></i>
                    <span>The post will be restored and activated. You can deactivate it if needed.</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('restoreModal')">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" id="confirmRestoreBtn" class="btn-primary">
                <i class="fas fa-undo mr-2"></i> Restore Post
            </button>
        </div>
    </div>
</div>

<!-- Force Delete Confirmation Modal -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center" style="color: var(--danger);">
                <i class="fas fa-exclamation-triangle mr-2"></i> Permanently Delete
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p style="color: var(--text-primary); font-weight: 500;" id="forceDeleteMessage">
                            Are you sure you want to permanently delete this post?
                        </p>
                        <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                            <strong>This action cannot be undone.</strong> All associated data including schedules and assignments will be permanently removed.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Deletion Reason (Optional)</label>
                <textarea id="forceDeleteReason" 
                          rows="2"
                          class="form-textarea w-full p-3 rounded-lg border"
                          style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                          placeholder="Enter reason for permanent deletion..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" id="confirmForceDeleteBtn" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger);">
                <i class="fas fa-times-circle mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<!-- Empty Trash Confirmation Modal -->
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center" style="color: var(--danger);">
                <i class="fas fa-trash-alt mr-2"></i> Empty Trash
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('emptyTrashModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p style="color: var(--text-primary); font-weight: 500;">
                            Are you sure you want to empty the trash?
                        </p>
                        <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                            <strong>This action cannot be undone.</strong> All {{ $trashedPosts->total() ?? 0 }} deleted posts will be permanently removed.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Confirmation</label>
                <input type="text" 
                       id="emptyTrashConfirmation" 
                       class="form-input w-full p-3 rounded-lg border"
                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                       placeholder='Type "empty_all_trash" to confirm'
                       onkeyup="validateEmptyTrashConfirmation()">
                <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                    Type <strong class="text-red-500">empty_all_trash</strong> to confirm
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('emptyTrashModal')">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" id="confirmEmptyTrashBtn" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); opacity: 0.5; cursor: not-allowed;"
                    disabled>
                <i class="fas fa-trash-alt mr-2"></i> Empty Trash
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white !important;
    border: 1px solid transparent !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    padding: 0.625rem 1.25rem;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border-radius: 0.5rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    border: 1px solid var(--border-color);
    cursor: pointer;
    text-decoration: none;
}

.btn-secondary:hover {
    background-color: var(--border-color);
    transform: translateY(-1px);
}

.form-input, .form-select, .form-textarea {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.form-label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
}

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

.hover-lift {
    transition: transform 0.2s, box-shadow 0.2s;
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.no-underline {
    text-decoration: none !important;
}

.no-underline:hover {
    text-decoration: none !important;
}

/* Checkbox styling */
.post-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.post-checkbox:checked {
    accent-color: var(--primary);
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2,
    .grid.grid-cols-1.md\:grid-cols-3,
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .p-6 {
        padding: 1rem !important;
    }
}
</style>

<script>
let currentPostId = null;
let currentAction = null;

// ==================== RESTORE FUNCTIONS ====================

function restorePost(postId) {
    currentPostId = postId;
    currentAction = 'restore';
    
    const message = document.getElementById('restoreModalMessage');
    message.textContent = 'Are you sure you want to restore this post? It will be activated automatically.';
    
    document.getElementById('confirmRestoreBtn').onclick = function() {
        executeRestore(postId);
    };
    
    openModal('restoreModal');
}

function executeRestore(postId) {
    const restoreBtn = document.getElementById('confirmRestoreBtn');
    const originalText = restoreBtn.innerHTML;
    
    restoreBtn.disabled = true;
    restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = originalText;
        return;
    }
    
    const restoreUrl = `/security/posts/${postId}/restore`;
    
    console.log('🔍 Restore URL:', restoreUrl);
    console.log('🔍 CSRF Token:', csrfToken);
    
    fetch(restoreUrl, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Content-Type:', response.headers.get('content-type'));
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('🔍 Response data:', data);
        
        if (data.success) {
            showToast(data.message || 'Post restored and activated successfully!', 'success');
            
            setTimeout(function() {
                window.location.href = data.redirect_url || '{{ route("security.posts.index") }}';
            }, 1500);
        } else {
            showToast(data.message || 'Failed to restore post.', 'error');
            restoreBtn.disabled = false;
            restoreBtn.innerHTML = originalText;
            closeModal('restoreModal');
        }
    })
    .catch(error => {
        console.error('❌ Restore error:', error);
        showToast(error.message || 'An error occurred while restoring the post.', 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = originalText;
        closeModal('restoreModal');
    });
}

// ==================== FORCE DELETE FUNCTIONS ====================

function confirmForceDelete(postId, postName) {
    currentPostId = postId;
    currentAction = 'forceDelete';
    
    const message = document.getElementById('forceDeleteMessage');
    message.innerHTML = `Are you sure you want to permanently delete <strong>${postName}</strong>?`;
    
    document.getElementById('forceDeleteReason').value = '';
    
    document.getElementById('confirmForceDeleteBtn').onclick = function() {
        executeForceDelete(postId);
    };
    
    openModal('forceDeleteModal');
}

function executeForceDelete(postId) {
    const deleteBtn = document.getElementById('confirmForceDeleteBtn');
    const originalText = deleteBtn.innerHTML;
    const reason = document.getElementById('forceDeleteReason').value;
    
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        return;
    }
    
    const deleteUrl = `/security/posts/${postId}/force-delete`;
    
    console.log('🔍 Force Delete URL:', deleteUrl);
    console.log('🔍 CSRF Token:', csrfToken);
    
    fetch(deleteUrl, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ deletion_reason: reason })
    })
    .then(response => {
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Content-Type:', response.headers.get('content-type'));
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('🔍 Response data:', data);
        
        if (data.success) {
            showToast(data.message || 'Post permanently deleted successfully!', 'success');
            // ✅ Stay on trash page after permanent delete
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to delete post.', 'error');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            closeModal('forceDeleteModal');
        }
    })
    .catch(error => {
        console.error('❌ Force Delete error:', error);
        showToast(error.message || 'An error occurred while deleting the post.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        closeModal('forceDeleteModal');
    });
}

// ==================== EMPTY TRASH FUNCTIONS ====================

function confirmEmptyTrash() {
    document.getElementById('emptyTrashConfirmation').value = '';
    document.getElementById('confirmEmptyTrashBtn').disabled = true;
    document.getElementById('confirmEmptyTrashBtn').style.opacity = '0.5';
    document.getElementById('confirmEmptyTrashBtn').style.cursor = 'not-allowed';
    
    document.getElementById('confirmEmptyTrashBtn').onclick = function() {
        executeEmptyTrash();
    };
    
    openModal('emptyTrashModal');
}

function validateEmptyTrashConfirmation() {
    const input = document.getElementById('emptyTrashConfirmation');
    const btn = document.getElementById('confirmEmptyTrashBtn');
    
    if (input.value === 'empty_all_trash') {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    } else {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
    }
}

function executeEmptyTrash() {
    const btn = document.getElementById('confirmEmptyTrashBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Emptying...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    fetch('{{ route("security.posts.empty-trash") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || `HTTP error ${response.status}`);
            }
            return data;
        });
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Trash emptied successfully!', 'success');
            // ✅ Stay on trash page after empty
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to empty trash.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
            closeModal('emptyTrashModal');
        }
    })
    .catch(error => {
        console.error('❌ Empty Trash error:', error);
        showToast(error.message || 'An error occurred while emptying trash.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeModal('emptyTrashModal');
    });
}

// ==================== BULK FUNCTIONS ====================

function toggleAllCheckboxes() {
    const checkboxes = document.querySelectorAll('.post-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
}

function getSelectedPostIds() {
    const checkboxes = document.querySelectorAll('.post-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.dataset.postId));
}

function bulkRestore() {
    const postIds = getSelectedPostIds();
    
    if (postIds.length === 0) {
        showToast('Please select at least one post to restore.', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ${postIds.length} post(s)?`)) {
        return;
    }
    
    const restoreBtn = document.querySelector('button[onclick="bulkRestore()"]');
    const originalText = restoreBtn.innerHTML;
    restoreBtn.disabled = true;
    restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Restoring...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = originalText;
        return;
    }
    
    const bulkRestoreUrl = '{{ route("security.posts.bulk-restore") }}';
    
    fetch(bulkRestoreUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ post_ids: postIds })
    })
    .then(response => {
        // Check content type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || `Successfully restored ${data.restored_count || postIds.length} post(s).`, 'success');
            setTimeout(function() {
                window.location.href = data.redirect_url || '{{ route("security.posts.index") }}';
            }, 1500);
        } else {
            showToast(data.message || 'Failed to restore posts.', 'error');
            restoreBtn.disabled = false;
            restoreBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('❌ Fetch error:', error);
        showToast(error.message || 'An error occurred while restoring posts.', 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = originalText;
    });
}

function bulkPermanentDelete() {
    const postIds = getSelectedPostIds();
    
    if (postIds.length === 0) {
        showToast('Please select at least one post to delete.', 'warning');
        return;
    }
    
    if (!confirm(`⚠️ Are you sure you want to permanently delete ${postIds.length} post(s)? This action cannot be undone!`)) {
        return;
    }
    
    const deleteBtn = document.querySelector('button[onclick="bulkPermanentDelete()"]');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        return;
    }
    
    const bulkDeleteUrl = '{{ route("security.posts.bulk-permanent-delete") }}';
    
    console.log('🔍 Bulk Delete URL:', bulkDeleteUrl);
    console.log('🔍 Post IDs:', postIds);
    console.log('🔍 CSRF Token:', csrfToken);
    
    fetch(bulkDeleteUrl, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ post_ids: postIds })
    })
    .then(response => {
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Content-Type:', response.headers.get('content-type'));
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || `HTTP error ${response.status}`);
            }
            return data;
        });
    })
    .then(data => {
        console.log('🔍 Response data:', data);
        
        if (data.success) {
            showToast(data.message || `Successfully deleted ${data.deleted_count || postIds.length} post(s).`, 'success');
            // ✅ Stay on trash page after permanent delete
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to delete posts.', 'error');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('❌ Bulk Delete error:', error);
        showToast(error.message || 'An error occurred while deleting posts.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
    });
}

function restoreAllPosts() {
    const postIds = @json($trashedPosts->pluck('id')->toArray() ?? []);
    
    if (postIds.length === 0) {
        showToast('No posts to restore.', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore all ${postIds.length} deleted post(s)?`)) {
        return;
    }
    
    const btn = document.querySelector('button[onclick="restoreAllPosts()"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Restoring All...';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    const bulkRestoreUrl = '{{ route("security.posts.bulk-restore") }}';
    
    console.log('🔍 Restore All URL:', bulkRestoreUrl);
    console.log('🔍 Post IDs:', postIds);
    console.log('🔍 CSRF Token:', csrfToken);
    
    fetch(bulkRestoreUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ post_ids: postIds })
    })
    .then(response => {
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Content-Type:', response.headers.get('content-type'));
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Please check logs.');
            });
        }
        
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || `HTTP error ${response.status}`);
            }
            return data;
        });
    })
    .then(data => {
        console.log('🔍 Response data:', data);
        
        if (data.success) {
            showToast(data.message || `Successfully restored ${data.restored_count || postIds.length} post(s).`, 'success');
            // ✅ Redirect to index page after restore all
            setTimeout(function() {
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.href = '{{ route("security.posts.index") }}';
                }
            }, 1500);
        } else {
            showToast(data.message || 'Failed to restore posts.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('❌ Restore All error:', error);
        showToast(error.message || 'An error occurred while restoring posts.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ==================== MODAL FUNCTIONS ====================

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
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = function() {
        toast.classList.add('translate-x-full');
        setTimeout(function() {
            toast.remove();
        }, 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(function() {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(function() {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }
    }, 5000);
}

// Handle escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal:not(.hidden)');
        openModals.forEach(function(modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
});
</script>
@endsection