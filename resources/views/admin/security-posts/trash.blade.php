@extends('layouts.app')

@section('title', 'Security Posts Trash')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash mr-2" style="color: var(--danger);"></i> 
                        Security Posts Trash
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-trash-restore mr-2"></i>
                        <span>Manage deleted security posts. <strong class="text-danger">Permanent deletion cannot be undone!</strong></span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Posts
                </a>
                @if($trashedPosts->count() > 0)
                <button onclick="showEmptyTrashModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger);">
                    <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Safety Warning Banner -->
    <div class="card border-l-4" style="border-left-color: var(--danger);">
        <div class="p-4">
            <div class="flex items-start">
                <div class="mr-3 mt-1">
                    <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                </div>
                <div>
                    <h4 class="font-semibold mb-1" style="color: var(--danger);">
                        <i class="fas fa-shield-alt mr-2"></i> Important Safety Notice
                    </h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Posts in trash are <strong>soft-deleted</strong> and can be restored. 
                        <strong class="text-danger">Permanent deletion</strong> removes all data permanently. 
                        This includes all related schedules, logs, and associated data. 
                        <strong>There is no recovery option after permanent deletion.</strong>
                    </p>
                    <div class="flex items-center mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-lightbulb mr-2 text-warning"></i>
                        <span>Tip: Consider restoring posts first if unsure. You can always delete them later.</span>
                    </div>
                </div>
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
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-shield-alt mr-1"></i> Active Posts
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Schedules
                </a>
                
                <a href="{{ route('admin.users.index') }}?type=6" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-users mr-1"></i> Personnel
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i> Items deleted more than 30 days ago may be auto-deleted
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
                        <i class="fas fa-trash"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $trashStats['total_trashed'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total in Trash</div>
                </div>
            </div>
        </div>
        
        <!-- Recently Deleted -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashStats['recently_deleted'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Deleted Last 7 Days</div>
                </div>
            </div>
        </div>
        
        <!-- Old Items -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-history"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--secondary);">{{ $trashStats['old'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Older than 30 Days</div>
                </div>
            </div>
        </div>
        
        <!-- Main Gates in Trash -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-door-closed"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $trashStats['by_type']['main_gate'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Main Gates</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Type Distribution -->
    @if(!empty($trashStats['by_type']))
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> 
                Deleted Posts by Type
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($trashStats['by_type'] as $type => $count)
                    @php
                        $typeColors = [
                            'main_gate' => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)'],
                            'internal_gate' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)'],
                            'checkpoint' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)'],
                            'patrol_route' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                            'observation_post' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)'],
                            'control_room' => ['bg' => 'rgba(var(--purple-rgb), 0.1)', 'text' => 'var(--purple)'],
                            'access_point' => ['bg' => 'rgba(var(--pink-rgb), 0.1)', 'text' => 'var(--pink)'],
                        ];
                        
                        $typeName = str_replace('_', ' ', $type);
                        $typeName = ucwords($typeName);
                        $typeColor = $typeColors[$type] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)'];
                    @endphp
                    <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $typeName }}</div>
                                <div class="text-2xl font-bold mt-1" style="color: {{ $typeColor['text'] }};">
                                    {{ $count }}
                                </div>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $typeColor['bg'] }}; color: {{ $typeColor['text'] }};">
                                @switch($type)
                                    @case('main_gate')<i class="fas fa-door-closed"></i>@break
                                    @case('internal_gate')<i class="fas fa-door-open"></i>@break
                                    @case('checkpoint')<i class="fas fa-shield-alt"></i>@break
                                    @case('patrol_route')<i class="fas fa-route"></i>@break
                                    @case('observation_post')<i class="fas fa-binoculars"></i>@break
                                    @case('control_room')<i class="fas fa-tv"></i>@break
                                    @case('access_point')<i class="fas fa-key"></i>@break
                                    @default<i class="fas fa-map-marker-alt"></i>
                                @endswitch
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
            </h3>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search deleted posts..."
                               value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Type</label>
                        <select name="type" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Types</option>
                            <option value="main_gate" {{ request('type') == 'main_gate' ? 'selected' : '' }}>Main Gate</option>
                            <option value="internal_gate" {{ request('type') == 'internal_gate' ? 'selected' : '' }}>Internal Gate</option>
                            <option value="checkpoint" {{ request('type') == 'checkpoint' ? 'selected' : '' }}>Checkpoint</option>
                            <option value="patrol_route" {{ request('type') == 'patrol_route' ? 'selected' : '' }}>Patrol Route</option>
                            <option value="observation_post" {{ request('type') == 'observation_post' ? 'selected' : '' }}>Observation Post</option>
                            <option value="control_room" {{ request('type') == 'control_room' ? 'selected' : '' }}>Control Room</option>
                            <option value="access_point" {{ request('type') == 'access_point' ? 'selected' : '' }}>Access Point</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Deleted Time</label>
                        <select name="deleted_time" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">Any Time</option>
                            <option value="today" {{ request('deleted_time') == 'today' ? 'selected' : '' }}>Today</option>
                            <option value="week" {{ request('deleted_time') == 'week' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="month" {{ request('deleted_time') == 'month' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="older" {{ request('deleted_time') == 'older' ? 'selected' : '' }}>Older than 30 Days</option>
                        </select>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-posts.trash.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($trashedPosts->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select items to restore or permanently delete multiple posts
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAllPosts()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAllPosts()" 
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
                    <option value="restore">Restore Selected</option>
                    <option value="force_delete" class="text-danger">Permanently Delete Selected</option>
                </select>
                <button type="button" 
                        onclick="performBulkAction()" 
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        id="bulkActionBtn">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
            
            <div class="mt-4 p-3 rounded-lg border hidden" 
                 style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);"
                 id="bulkDeleteWarning">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-2" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">
                            <strong>Warning:</strong> Permanent deletion cannot be undone!
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            All selected posts and their associated schedules will be permanently removed from the database.
                        </p>
                    </div>
                </div>
            </div>
            
            <div id="bulkActionStatus" class="mt-3 hidden">
                <!-- Status messages will appear here -->
            </div>
        </div>
    </div>
    @endif

    <!-- Trashed Posts Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Deleted Security Posts
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $trashedPosts->count() }} of {{ $trashedPosts->total() }} deleted posts
                </div>
            </div>
        </div>
        <div class="p-6">
            @if($trashedPosts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Post</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Type</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Location</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Deleted</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedPosts as $post)
                            @php
                                $deletedAt = $post->deleted_at;
                                $isOld = $deletedAt && $deletedAt->diffInDays(now()) > 30;
                                $deletedTimeClass = $isOld ? 'text-danger' : 'text-warning';
                            @endphp
                            <tr class="border-b transition-colors duration-150 hover:bg-red-50" 
                                style="border-color: var(--border-color); background-color: rgba(var(--danger-rgb), 0.05);">
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="post-checkbox bulk-checkbox" 
                                           value="{{ $post->id }}"
                                           data-trashed="true">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="mr-2 text-red-500" title="This post is in trash">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                        <div class="post-icon mr-3">
                                            @php
                                                // Define post type icons
                                                $typeIcons = [
                                                    'main_gate' => ['icon' => 'fa-door-closed', 'color' => 'var(--primary)'],
                                                    'internal_gate' => ['icon' => 'fa-door-open', 'color' => 'var(--info)'],
                                                    'checkpoint' => ['icon' => 'fa-shield-alt', 'color' => 'var(--success)'],
                                                    'patrol_route' => ['icon' => 'fa-route', 'color' => 'var(--warning)'],
                                                    'observation_post' => ['icon' => 'fa-binoculars', 'color' => 'var(--danger)'],
                                                    'control_room' => ['icon' => 'fa-tv', 'color' => 'var(--purple)'],
                                                    'access_point' => ['icon' => 'fa-key', 'color' => 'var(--pink)'],
                                                ];
                                                
                                                $typeIcon = $typeIcons[$post->type] ?? ['icon' => 'fa-map-marker-alt', 'color' => 'var(--secondary)'];
                                            @endphp
                                            <i class="fas {{ $typeIcon['icon'] }}" style="color: {{ $typeIcon['color'] }};"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $post->name }}
                                                <span class="ml-1 text-xs text-red-500">(Deleted)</span>
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Code: {{ $post->code }}
                                                <br>
                                                <span class="{{ $deletedTimeClass }}">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Deleted {{ $deletedAt ? $deletedAt->diffForHumans() : 'Unknown' }}
                                                    @if($isOld)
                                                    <span class="ml-1 text-xs bg-red-100 text-red-800 px-1 rounded">Old</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        // Define post type colors for badges
                                        $typeColors = [
                                            'main_gate' => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)'],
                                            'internal_gate' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)'],
                                            'checkpoint' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)'],
                                            'patrol_route' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                                            'observation_post' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)'],
                                            'control_room' => ['bg' => 'rgba(var(--purple-rgb), 0.1)', 'text' => 'var(--purple)'],
                                            'access_point' => ['bg' => 'rgba(var(--pink-rgb), 0.1)', 'text' => 'var(--pink)'],
                                        ];
                                        
                                        $typeColor = $typeColors[$post->type] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)'];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                          style="background-color: {{ $typeColor['bg'] }}; 
                                                 color: {{ $typeColor['text'] }};">
                                        {{ str_replace('_', ' ', $post->type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ Str::limit($post->location, 25) }}
                                    </div>
                                    @if($post->digital_address)
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-pin mr-1"></i> {{ Str::limit($post->digital_address, 20) }}
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="space-y-1">
                                        @if($deletedAt)
                                        <div class="text-sm {{ $deletedTimeClass }}">
                                            <i class="fas fa-calendar-times mr-1"></i>
                                            {{ $deletedAt->format('M d, Y') }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $deletedAt->format('h:i A') }}
                                        </div>
                                        <div class="text-xs mt-1 {{ $deletedTimeClass }}">
                                            {{ $deletedAt->diffForHumans() }}
                                        </div>
                                        @else
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-question-circle mr-1"></i>
                                            Unknown
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="restorePost({{ $post->id }}, '{{ $post->name }}', {{ $post->schedules_count ?? 0 }})"
                                                class="action-btn" 
                                                title="Restore this post"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button onclick="showForceDeleteModal({{ $post->id }}, '{{ $post->name }}', {{ $post->schedules_count ?? 0 }})"
                                                class="action-btn" 
                                                title="Permanently delete this post"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <a href="{{ route('admin.security-posts.show', $post) }}" 
                                           class="action-btn" 
                                           title="View post details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($post->schedules_count > 0)
                                        <div class="relative group">
                                            <div class="action-btn cursor-help"
                                                 title="Has {{ $post->schedules_count }} schedule(s) - These will also be deleted"
                                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                                    {{ $post->schedules_count }}
                                                </span>
                                            </div>
                                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-10">
                                                {{ $post->schedules_count }} schedule(s) will also be deleted
                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($trashedPosts->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $trashedPosts->firstItem() }} to {{ $trashedPosts->lastItem() }} of {{ $trashedPosts->total() }} deleted posts
                        </div>
                        <div class="flex space-x-2">
                            @if($trashedPosts->onFirstPage())
                                <span class="px-3 py-2 rounded border text-sm" 
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            @else
                                <a href="{{ $trashedPosts->previousPageUrl() }}" 
                                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            @endif

                            @foreach($trashedPosts->getUrlRange(max(1, $trashedPosts->currentPage() - 2), min($trashedPosts->lastPage(), $trashedPosts->currentPage() + 2)) as $page => $url)
                                @if($page == $trashedPosts->currentPage())
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

                            @if($trashedPosts->hasMorePages())
                                <a href="{{ $trashedPosts->nextPageUrl() }}" 
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
            @else
            <!-- Empty Trash State -->
            <div class="py-12 text-center">
                <div class="flex flex-col items-center justify-center">
                    <div class="w-32 h-32 rounded-full flex items-center justify-center mb-6"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle text-5xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Trash is Empty!</h3>
                    <p class="text-lg mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                        All deleted security posts have been cleaned up. Your trash is currently empty.
                    </p>
                    <div class="flex space-x-3">
                        <a href="{{ route('admin.security-posts.index') }}" 
                           class="px-6 py-3 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Security Posts
                        </a>
                        <a href="{{ route('admin.dashboard') }}" 
                           class="px-6 py-3 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-home mr-2"></i> Go to Dashboard
                        </a>
                    </div>
                    <div class="mt-8 pt-8 border-t w-full max-w-md" style="border-color: var(--border-color);">
                        <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Trash Tips</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-2 text-primary"></i>
                                Deleted posts stay in trash for 30 days
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-undo mr-2 text-success"></i>
                                You can restore posts at any time
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-2 text-warning"></i>
                                Posts with active schedules can't be deleted
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-2 text-danger"></i>
                                Old posts are automatically cleaned up
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Empty Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('emptyTrashModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    Permanently Delete All Posts
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This will <strong class="text-red-600">permanently delete</strong> all 
                    <strong>{{ $trashedPosts->total() }}</strong> posts in the trash.
                </p>
                
                <div class="p-4 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Warning:</strong> This action cannot be undone! All related schedules will also be deleted.
                    </div>
                </div>
                
                <!-- Additional Warning Section -->
                <div class="text-left text-sm mt-4 p-3 rounded-lg border" 
                     style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <h5 class="font-semibold mb-2 flex items-center" style="color: var(--warning);">
                        <i class="fas fa-shield-alt mr-2"></i> What will be deleted:
                    </h5>
                    <ul class="space-y-1">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1 text-danger" style="font-size: 0.75rem;"></i>
                            <span>All {{ $trashedPosts->total() }} posts in trash</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1 text-danger" style="font-size: 0.75rem;"></i>
                            <span>All associated schedules and assignments</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1 text-danger" style="font-size: 0.75rem;"></i>
                            <span>All related logs and activity records</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1 text-danger" style="font-size: 0.75rem;"></i>
                            <span>Post configuration and settings</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Safety Check -->
                <div class="mt-4 p-3 rounded-lg border" 
                     style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-question-circle mr-2 mt-1" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                Are you absolutely sure?
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Consider downloading a backup or checking if any posts should be restored first.
                            </p>
                        </div>
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
            <form id="emptyTrashForm" method="POST" action="{{ route('admin.security-posts.trash.empty') }}" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmEmptyTrash()">
                <i class="fas fa-trash-alt mr-2"></i> Empty Trash
            </button>
        </div>
    </div>
</div>

<!-- Enhanced Force Delete Modal (Permanently Delete from Trash) -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete Post</h3>
            <button type="button" class="modal-close" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeletePostName">
                    <!-- Post name will be inserted here -->
                </h4>
                
                <!-- Schedule Count Warning -->
                <div id="scheduleCountWarning" class="hidden p-3 mb-3 rounded-lg border" 
                     style="background-color: rgba(var(--warning-rgb), 0.1); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--warning);"></i>
                        <span class="text-sm font-medium" style="color: var(--warning);">
                            This post has <span id="scheduleCount">0</span> associated schedule(s) that will also be deleted!
                        </span>
                    </div>
                </div>
                
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action will <strong class="text-red-600">permanently delete</strong> the security post and all its data.
                    <strong>This cannot be undone!</strong>
                </p>
                
                <!-- Danger Zone -->
                <div class="p-4 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle mr-2 mt-1" style="color: var(--danger);"></i>
                        <div>
                            <h5 class="font-semibold mb-1" style="color: var(--danger);">DANGER ZONE</h5>
                            <div class="text-sm" style="color: var(--danger);">
                                <p>This action will:</p>
                                <ul class="mt-1 space-y-1">
                                    <li class="flex items-start">
                                        <i class="fas fa-times mr-2 mt-1" style="font-size: 0.75rem;"></i>
                                        <span>Permanently remove the post from database</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times mr-2 mt-1" style="font-size: 0.75rem;"></i>
                                        <span>Delete all related schedules and assignments</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times mr-2 mt-1" style="font-size: 0.75rem;"></i>
                                        <span>Remove all associated logs and activity records</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times mr-2 mt-1" style="font-size: 0.75rem;"></i>
                                        <span>Make the post code available for reuse</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Safety Check -->
                <div class="mt-4 p-3 rounded-lg border" 
                     style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-lightbulb mr-2 mt-1" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                Consider restoring instead?
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                You can restore this post now and delete it later if needed. 
                                Restoration is reversible, permanent deletion is not.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Final Confirmation -->
                <div class="mt-4">
                    <div class="flex items-center justify-center space-x-2">
                        <input type="checkbox" id="confirmIrreversible" class="h-4 w-4">
                        <label for="confirmIrreversible" class="text-sm" style="color: var(--text-secondary);">
                            I understand this action is <strong class="text-danger">irreversible</strong>
                        </label>
                    </div>
                    <div class="flex items-center justify-center space-x-2 mt-2">
                        <input type="checkbox" id="confirmNoBackup" class="h-4 w-4">
                        <label for="confirmNoBackup" class="text-sm" style="color: var(--text-secondary);">
                            I have verified no backup is needed
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" 
                    onclick="restoreFromModal()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);"
                    id="restoreFromModalBtn">
                <i class="fas fa-undo mr-2"></i> Restore Instead
            </button>
            <form id="forceDeleteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger); opacity: 0.5;"
                    id="forceDeleteBtn"
                    onclick="confirmForceDelete()"
                    disabled>
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
let selectedPostIds = [];
let currentPostId = null;
let currentPostName = null;
let currentScheduleCount = 0;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Initialize action buttons
    initializeActionButtons();
    
    // Bulk selection checkboxes
    initializeBulkSelection();
    
    // Auto-select old posts warning
    highlightOldPosts();
    
    // Bulk action select change listener
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const bulkDeleteWarning = document.getElementById('bulkDeleteWarning');
    
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            if (this.value === 'force_delete') {
                bulkDeleteWarning.classList.remove('hidden');
            } else {
                bulkDeleteWarning.classList.add('hidden');
            }
        });
    }
    
    // Force delete modal checkboxes
    const confirmIrreversible = document.getElementById('confirmIrreversible');
    const confirmNoBackup = document.getElementById('confirmNoBackup');
    const forceDeleteBtn = document.getElementById('forceDeleteBtn');
    
    if (confirmIrreversible && confirmNoBackup && forceDeleteBtn) {
        const updateForceDeleteBtn = () => {
            if (confirmIrreversible.checked && confirmNoBackup.checked) {
                forceDeleteBtn.disabled = false;
                forceDeleteBtn.style.opacity = '1';
            } else {
                forceDeleteBtn.disabled = true;
                forceDeleteBtn.style.opacity = '0.5';
            }
        };
        
        confirmIrreversible.addEventListener('change', updateForceDeleteBtn);
        confirmNoBackup.addEventListener('change', updateForceDeleteBtn);
    }
});

// Bulk selection functions
function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const postCheckboxes = document.querySelectorAll('.post-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            postCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    postCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
        });
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.post-checkbox:checked');
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

function selectAllPosts() {
    const checkboxes = document.querySelectorAll('.post-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
    
    updateSelectedCount();
}

function deselectAllPosts() {
    const checkboxes = document.querySelectorAll('.post-checkbox');
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
    
    const checkboxes = document.querySelectorAll('.post-checkbox:checked');
    if (checkboxes.length === 0) {
        showToast('Please select at least one post', 'warning');
        return;
    }
    
    const postIds = Array.from(checkboxes).map(cb => cb.value);
    
    // Enhanced confirmation for destructive actions
    if (selectedAction === 'force_delete') {
        const confirmed = await showEnhancedConfirmation(
            `PERMANENTLY DELETE ${postIds.length} POST(S)`,
            `You are about to permanently delete ${postIds.length} post(s). This action:`,
            [
                'Cannot be undone',
                'Will delete all associated schedules',
                'Will remove all related data',
                'Is irreversible'
            ],
            'Type "DELETE" to confirm:',
            'DELETE'
        );
        
        if (!confirmed) return;
    } else if (selectedAction === 'restore') {
        if (!confirm(`Are you sure you want to restore ${postIds.length} post(s) from trash?`)) {
            return;
        }
    }
    
    try {
        showLoading(`Processing ${postIds.length} post(s)...`);
        
        // Determine which endpoint to use
        let endpoint = '';
        let requestBody = {};
        
        if (selectedAction === 'restore') {
            endpoint = '{{ route("admin.security-posts.trash.bulk-restore") }}';
            requestBody = {
                post_ids: postIds
            };
        } else if (selectedAction === 'force_delete') {
            endpoint = '{{ route("admin.security-posts.api.bulk-update") }}';
            requestBody = {
                post_ids: postIds,
                action: 'force_delete',
                data: {}
            };
        } else {
            endpoint = '{{ route("admin.security-posts.api.bulk-update") }}';
            requestBody = {
                post_ids: postIds,
                action: selectedAction,
                data: {}
            };
        }
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });
        
        // Get the raw text response first
        const rawText = await response.text();
        
        // Remove BOM if present (U+FEFF)
        let cleanText = rawText;
        if (rawText.charCodeAt(0) === 0xFEFF) {
            cleanText = rawText.slice(1);
        }
        
        // Parse the cleaned JSON
        const data = JSON.parse(cleanText);
        
        hideLoading();
        
        if (data.success) {
            let successMessage = data.message;
            let toastType = 'success';
            
            if (data.failed_posts && data.failed_posts.length > 0) {
                const failedNames = data.failed_posts.map(p => p.name).join(', ');
                successMessage += ` Failed posts: ${failedNames}`;
                toastType = (data.restored_count > 0 || data.updated_count > 0) ? 'warning' : 'error';
            }
            
            showToast(successMessage, toastType);
            
            // Clear selection
            deselectAllPosts();
            actionSelect.value = '';
            
            const bulkDeleteWarning = document.getElementById('bulkDeleteWarning');
            if (bulkDeleteWarning) {
                bulkDeleteWarning.classList.add('hidden');
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            showToast(data.message || 'Failed to perform bulk action', 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Bulk action error:', error);
        showToast(`Network error: ${error.message || 'Unknown error occurred'}`, 'error');
    }
}

function showEmptyTrashModal() {
    openModal('emptyTrashModal');
}

function showForceDeleteModal(postId, postName, scheduleCount = 0) {
    // Store current post info
    currentPostId = postId;
    currentPostName = postName;
    currentScheduleCount = scheduleCount;
    
    // Set post name
    document.getElementById('forceDeletePostName').textContent = postName;
    
    // Show/hide schedule count warning
    const scheduleWarning = document.getElementById('scheduleCountWarning');
    const scheduleCountEl = document.getElementById('scheduleCount');
    
    if (scheduleCount > 0) {
        scheduleWarning.classList.remove('hidden');
        scheduleCountEl.textContent = scheduleCount;
    } else {
        scheduleWarning.classList.add('hidden');
    }
    
    // Update force delete form action
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.action = `{{ url('admin/security-posts/trash') }}/${postId}/force-delete`;
    
    // Update restore button
    const restoreBtn = document.getElementById('restoreFromModalBtn');
    restoreBtn.onclick = () => {
        closeModal('forceDeleteModal');
        restorePost(postId, postName, scheduleCount);
    };
    
    // Reset checkboxes
    const confirmIrreversible = document.getElementById('confirmIrreversible');
    const confirmNoBackup = document.getElementById('confirmNoBackup');
    const forceDeleteBtn = document.getElementById('forceDeleteBtn');
    
    if (confirmIrreversible) confirmIrreversible.checked = false;
    if (confirmNoBackup) confirmNoBackup.checked = false;
    if (forceDeleteBtn) {
        forceDeleteBtn.disabled = true;
        forceDeleteBtn.style.opacity = '0.5';
    }
    
    openModal('forceDeleteModal');
}

async function restorePost(postId, postName, scheduleCount = 0) {
    let confirmMessage = `Are you sure you want to restore "${postName}"?`;
    
    if (scheduleCount > 0) {
        confirmMessage += `\n\nThis post has ${scheduleCount} associated schedule(s) that will also be restored.`;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        showLoading(`Restoring "${postName}"...`);
        
        const response = await fetch(`{{ url('admin/security-posts/trash') }}/${postId}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        hideLoading();
        
        if (response.ok) {
            showToast('Post restored successfully! It has been deactivated by default.', 'success');
            
            // Refresh page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            const data = await response.json();
            showToast(data.message || 'Failed to restore post', 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Restore error:', error);
        showToast('Network error occurred', 'error');
    }
}

function restoreFromModal() {
    if (currentPostId && currentPostName) {
        closeModal('forceDeleteModal');
        restorePost(currentPostId, currentPostName, currentScheduleCount);
    }
}

function confirmForceDelete() {
    const confirmIrreversible = document.getElementById('confirmIrreversible');
    const confirmNoBackup = document.getElementById('confirmNoBackup');
    
    if (!confirmIrreversible || !confirmNoBackup || !confirmIrreversible.checked || !confirmNoBackup.checked) {
        showToast('Please confirm both safety checks before permanent deletion.', 'warning');
        return;
    }
    
    // Final warning
    if (!confirm(`FINAL WARNING: Are you absolutely sure you want to PERMANENTLY delete "${currentPostName}"? This is your last chance to cancel.`)) {
        return;
    }
    
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.submit();
}

function confirmEmptyTrash() {
    // Enhanced confirmation for emptying trash
    if (!confirm(`FINAL CONFIRMATION: Are you absolutely sure you want to PERMANENTLY delete ALL {{ $trashedPosts->total() }} posts from trash? This cannot be undone!`)) {
        return;
    }
    
    const emptyTrashForm = document.getElementById('emptyTrashForm');
    emptyTrashForm.submit();
}

// Enhanced confirmation dialog
async function showEnhancedConfirmation(title, description, points, inputLabel, requiredInput) {
    return new Promise((resolve) => {
        // Create modal elements
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
        modalOverlay.id = 'enhancedConfirmationModal';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4';
        
        modalContent.innerHTML = `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-4xl mb-4 text-red-500"></i>
                <h3 class="text-lg font-semibold mb-2">${title}</h3>
                <p class="text-sm text-gray-600 mb-4">${description}</p>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-left">
                    <ul class="text-sm text-red-700 space-y-1">
                        ${points.map(point => `<li class="flex items-start">
                            <i class="fas fa-times mr-2 mt-1"></i>
                            <span>${point}</span>
                        </li>`).join('')}
                    </ul>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">${inputLabel}</label>
                    <input type="text" 
                           id="confirmationInput" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                           placeholder="Type ${requiredInput} to confirm">
                    <p class="text-xs text-gray-500 mt-1">This helps prevent accidental deletion</p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" 
                            id="cancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
                        Cancel
                    </button>
                    <button type="button" 
                            id="confirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        Confirm Delete
                    </button>
                </div>
            </div>
        `;
        
        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);
        document.body.style.overflow = 'hidden';
        
        // Get elements
        const confirmationInput = modalContent.querySelector('#confirmationInput');
        const confirmBtn = modalContent.querySelector('#confirmBtn');
        const cancelBtn = modalContent.querySelector('#cancelBtn');
        
        // Input validation
        confirmationInput.addEventListener('input', function() {
            confirmBtn.disabled = this.value !== requiredInput;
        });
        
        // Enter key support
        confirmationInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && this.value === requiredInput) {
                resolve(true);
                removeModal();
            }
        });
        
        // Button handlers
        confirmBtn.addEventListener('click', function() {
            if (confirmationInput.value === requiredInput) {
                resolve(true);
                removeModal();
            }
        });
        
        cancelBtn.addEventListener('click', function() {
            resolve(false);
            removeModal();
        });
        
        // Close on overlay click
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                resolve(false);
                removeModal();
            }
        });
        
        // Focus input
        setTimeout(() => confirmationInput.focus(), 100);
        
        function removeModal() {
            document.body.removeChild(modalOverlay);
            document.body.style.overflow = 'auto';
        }
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

function highlightOldPosts() {
    // Add visual indication for posts older than 30 days
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const timeText = row.querySelector('.text-danger');
        if (timeText && timeText.textContent.includes('Old')) {
            row.style.animation = 'pulse 3s infinite';
        }
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
    
    // Add icon based on type
    const icon = type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-times-circle' :
                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1 flex items-center';
    messageEl.innerHTML = `<i class="fas ${icon} mr-2"></i> ${message}`;
    
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
        overlay.className = 'fixed inset-0 bg-black bg-opacity-70 z-[9999] flex items-center justify-center';
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
    // Ctrl/Cmd + A to select all posts
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        if (document.querySelector('.post-checkbox')) {
            selectAllPosts();
        }
    }
    
    // Esc to deselect all
    if (e.key === 'Escape') {
        const anySelected = document.querySelector('.post-checkbox:checked');
        if (anySelected) {
            e.preventDefault();
            deselectAllPosts();
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

/* Trash specific styles */
.trash-indicator {
    animation: pulse 2s infinite;
}

.old-post {
    animation: pulse 3s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
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
    position: relative;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.post-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(var(--primary-rgb), 0.1);
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

/* Danger select option */
select option.text-danger {
    color: var(--danger) !important;
    font-weight: 600;
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
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
    
    .modal-footer {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .modal-footer button {
        flex: 1;
        min-width: 120px;
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
    
    .text-5xl {
        font-size: 3rem !important;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Tooltip styles */
.group:hover .group-hover\:opacity-100 {
    opacity: 1 !important;
}

/* Enhanced modal styles */
#enhancedConfirmationModal {
    animation: fadeIn 0.3s ease;
}

#enhancedConfirmationModal .bg-white {
    animation: modalFadeIn 0.3s ease;
}

/* Schedule count badge */
.action-btn .absolute {
    font-size: 0.6rem;
    line-height: 1;
}
</style>