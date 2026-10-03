@extends('layouts.secu')

@section('title', 'Security Posts')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-building text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        Security Posts
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-layer-group mr-1"></i>
                            {{ $posts->total() ?? 0 }} posts
                        </span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: {{ isset($isAreaSupervisor) && $isAreaSupervisor ? 'var(--success)' : 'var(--text-secondary)' }};">
                            <i class="fas fa-{{ isset($isAreaSupervisor) && $isAreaSupervisor ? 'user-tie' : 'user' }} mr-1"></i>
                            {{ isset($isAreaSupervisor) && $isAreaSupervisor ? 'Area Supervisor' : 'Security Personnel' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                {{-- ✅ CREATE POST BUTTON - Only for Area Supervisors --}}
                @if(isset($isAreaSupervisor) && $isAreaSupervisor === true)
                    <a href="{{ route('security.posts.create') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary"
                       title="Create a new security post">
                        <i class="fas fa-plus-circle mr-2"></i> Create Post
                    </a>
                @endif

                {{-- ✅ TRASH LINK - Only for Area Supervisors --}}
                @if(isset($isAreaSupervisor) && $isAreaSupervisor === true)
                    <a href="{{ route('security.posts.trash') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-trash mr-2"></i> Trash
                        @php
                            $trashCount = \App\Models\SecurityPost::onlyTrashed()->count();
                        @endphp
                        @if($trashCount > 0)
                            <span class="ml-1 px-2 py-0.5 text-xs rounded-full" style="background-color: var(--danger); color: white;">
                                {{ $trashCount }}
                            </span>
                        @endif
                    </a>
                @endif

                <a href="{{ route('security.posts.my-post') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-user-check mr-2"></i> My Post
                </a>

                @if(isset($isAreaSupervisor) && $isAreaSupervisor)
                    <a href="{{ route('security.supervisor.dashboard') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-user-tie mr-2"></i> Supervisor Dashboard
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="card p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <form method="GET" action="{{ route('security.posts.index') }}" class="flex flex-1 flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search by name, code, or location..."
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

                    {{-- ✅ STAFFING STATUS FILTER --}}
                    <select name="staffing_status" class="px-4 py-2 rounded-lg"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <option value="">All Staffing</option>
                        <option value="fully_staffed" {{ request('staffing_status') == 'fully_staffed' ? 'selected' : '' }}>Fully Staffed</option>
                        <option value="understaffed" {{ request('staffing_status') == 'understaffed' ? 'selected' : '' }}>Understaffed</option>
                        <option value="unstaffed" {{ request('staffing_status') == 'unstaffed' ? 'selected' : '' }}>Unstaffed</option>
                    </select>

                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    @if(request()->has('search') || request()->has('type') || request()->has('staffing_status'))
                        <a href="{{ route('security.posts.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium"
                           style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </form>

            {{-- ✅ QUICK CREATE BUTTON (Mobile) - Only for Area Supervisors --}}
            @if(isset($isAreaSupervisor) && $isAreaSupervisor === true)
                <a href="{{ route('security.posts.create') }}"
                   class="md:hidden px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center text-white btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Post
                </a>
            @endif
        </div>
    </div>

    <!-- Stats Cards -->
    @if(isset($stats))
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_posts'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Posts</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-building" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['fully_staffed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Fully Staffed</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['understaffed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Understaffed</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['unstaffed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Unstaffed</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-user-slash" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Posts Grid -->
    @if(isset($posts) && $posts->count() > 0)
        {{-- ✅ ACTION BAR - Only for Area Supervisors --}}
        @if(isset($isAreaSupervisor) && $isAreaSupervisor === true)
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <a href="{{ route('security.posts.create') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary transition-all duration-200 hover:scale-105"
                       title="Create a new security post">
                        <i class="fas fa-plus-circle mr-2"></i> Create New Post
                    </a>
                    
                    @if(isset($trashedCount) && $trashedCount > 0)
                        <a href="{{ route('security.posts.trash') }}"
                           class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                           style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash mr-2"></i> Trash
                            <span class="ml-1 px-2 py-0.5 text-xs rounded-full" style="background-color: var(--danger); color: white;">
                                {{ $trashedCount }}
                            </span>
                        </a>
                    @endif
                </div>
                
                {{-- View count --}}
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-layer-group mr-1"></i>
                    Showing {{ $posts->firstItem() ?? 0 }} - {{ $posts->lastItem() ?? 0 }} of {{ $posts->total() ?? 0 }}
                </div>
            </div>
        @else
            {{-- For regular personnel, just show count --}}
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-layer-group mr-1"></i>
                Showing {{ $posts->firstItem() ?? 0 }} - {{ $posts->lastItem() ?? 0 }} of {{ $posts->total() ?? 0 }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
            @foreach($posts as $post)
                <div class="card p-6 hover:shadow-lg transition-shadow duration-300 relative">
                    {{-- ✅ SUPERVISOR BADGE - Show if user is Area Supervisor AND has edit permission --}}
                    @if(isset($isAreaSupervisor) && $isAreaSupervisor === true && isset($editPermissions[$post->id]) && $editPermissions[$post->id] === true)
                        <div class="absolute top-3 right-3">
                            <span class="px-2 py-1 text-xs rounded-full" 
                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-edit mr-1"></i> Can Edit
                            </span>
                        </div>
                    @endif

                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 flex-shrink-0"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 18px; font-weight: 600;">
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
                        @php
                            $currentPersonnel = $post->current_personnel ?? 0;
                            $status = $currentPersonnel >= $post->max_personnel ? 'fully_staffed' : ($currentPersonnel > 0 ? 'understaffed' : 'unstaffed');
                            $statusColors = [
                                'fully_staffed' => 'success',
                                'understaffed' => 'warning',
                                'unstaffed' => 'danger'
                            ];
                            $statusLabels = [
                                'fully_staffed' => 'Full',
                                'understaffed' => 'Short',
                                'unstaffed' => 'Empty'
                            ];
                        @endphp
                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $statusColors[$status] }}">
                            {{ $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
                        </span>
                    </div>
                    
                    <div class="mt-3 flex items-center space-x-4">
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-users mr-1"></i>
                            {{ $currentPersonnel }}/{{ $post->max_personnel }}
                        </span>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i>
                            {{ $post->working_hours_info['display'] ?? '24/7' }}
                        </span>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-tag mr-1"></i>
                            {{ ucfirst(str_replace('_', ' ', $post->type)) }}
                        </span>
                    </div>
                    
                    <div class="mt-4 flex space-x-2">
                        <a href="{{ route('security.posts.show', $post->id) }}"
                           class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-eye mr-1"></i> View
                        </a>
                        <a href="{{ route('security.posts.schedule', ['securityPost' => $post->id]) }}"
                           class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-calendar-alt mr-1"></i> Schedule
                        </a>
                        
                        {{-- ✅ EDIT BUTTON - Only if user is Area Supervisor AND has edit permission --}}
                        @if(isset($isAreaSupervisor) && $isAreaSupervisor === true && isset($editPermissions[$post->id]) && $editPermissions[$post->id] === true)
                            <a href="{{ route('security.posts.edit', $post->id) }}"
                               class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        @endif

                        {{-- ✅ DELETE BUTTON - Only if user is Area Supervisor AND has delete permission --}}
                        @if(isset($isAreaSupervisor) && $isAreaSupervisor === true && isset($editPermissions[$post->id]) && $editPermissions[$post->id] === true)
                            <button type="button" 
                                    onclick="confirmDeletePost({{ $post->id }}, '{{ addslashes($post->name) }}')"
                                    class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-6">
            {{ $posts->withQueryString()->links() }}
        </div>
    @else
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-building text-4xl" style="color: var(--info);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Posts Found</h3>
            <p class="mb-6" style="color: var(--text-secondary);">
                {{ request()->has('search') ? 'No posts match your search criteria.' : 'No active security posts available.' }}
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                @if(request()->has('search') || request()->has('type') || request()->has('staffing_status'))
                    <a href="{{ route('security.posts.index') }}" class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-undo mr-2"></i> Clear Filters
                    </a>
                @endif
                
                {{-- ✅ CREATE POST BUTTON IN EMPTY STATE - Only for Area Supervisors --}}
                @if(isset($isAreaSupervisor) && $isAreaSupervisor === true)
                    <a href="{{ route('security.posts.create') }}" class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-plus-circle mr-2"></i> Create First Post
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- ✅ SHOW TRASH LINK AT BOTTOM (Only for supervisors) --}}
    @if(isset($isAreaSupervisor) && $isAreaSupervisor === true && isset($trashedCount) && $trashedCount > 0)
        <div class="mt-4 text-center">
            <a href="{{ route('security.posts.trash') }}" 
               class="text-sm" style="color: var(--danger);">
                <i class="fas fa-trash mr-1"></i>
                {{ $trashedCount }} post(s) in trash
            </a>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center" style="color: var(--danger);">
                <i class="fas fa-exclamation-triangle mr-2"></i> Confirm Delete
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p style="color: var(--text-primary); font-weight: 500;" id="deleteModalMessage">
                            Are you sure you want to delete this post?
                        </p>
                        <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                            This will move the post to the trash. You can restore it later from the trash.
                        </p>
                        <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <strong>Note:</strong> Posts with active or upcoming schedules cannot be deleted.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('deleteModal')">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" id="confirmDeleteBtn" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger);">
                <i class="fas fa-trash mr-2"></i> Move to Trash
            </button>
        </div>
    </div>
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.card {
    transition: all 0.2s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-2px);
}

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }

input, select {
    transition: border-color 0.2s ease;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .btn-primary {
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}
</style>

<script>
let deletePostId = null;

function confirmDeletePost(postId, postName) {
    deletePostId = postId;
    const message = document.getElementById('deleteModalMessage');
    message.innerHTML = `Are you sure you want to delete <strong>${postName}</strong>? This will move it to the trash.`;
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        executeDeletePost(postId);
    };
    
    openModal('deleteModal');
}

function executeDeletePost(postId) {
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const originalText = deleteBtn.innerHTML;
    
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    // Get CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!csrfToken) {
        showToast('CSRF token not found. Please refresh the page.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        return;
    }
    
    // Get the base URL
    const baseUrl = window.location.origin;
    const deleteUrl = `/security/posts/${postId}`;
    
    console.log('🔍 Delete URL:', deleteUrl);
    console.log('🔍 CSRF Token:', csrfToken);
    
    fetch(deleteUrl, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('🔍 Response status:', response.status);
        console.log('🔍 Response headers:', response.headers);
        
        // Try to parse JSON response
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || `HTTP error ${response.status}`);
            }
            return data;
        }).catch(err => {
            // If JSON parsing fails, but response is OK
            if (response.ok) {
                return { success: true, message: 'Post deleted successfully.' };
            }
            throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
        });
    })
    .then(data => {
        console.log('🔍 Response data:', data);
        
        if (data.success) {
            showToast(data.message || 'Post moved to trash successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to delete post.', 'error');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            closeModal('deleteModal');
        }
    })
    .catch(error => {
        console.error('❌ Delete error:', error);
        showToast(error.message || 'An error occurred while deleting the post.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        closeModal('deleteModal');
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

// Also handle escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal:not(.hidden)');
        openModals.forEach(modal => {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
});
</script>
@endsection