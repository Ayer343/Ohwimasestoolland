@extends('layouts.app')

@section('title', 'Security Posts')

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
                        <i class="fas fa-shield-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> 
                        Security Posts
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i>
                        <span>Manage all security posts and their configurations</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.security-posts.create') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-plus mr-2"></i> Create Post
                </a>
                <button onclick="showExportModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
                @if($stats['trashed_posts'] > 0)
                <a href="{{ route('admin.security-posts.trash.index') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $stats['trashed_posts'] }}
                    </span>
                </a>
                @else
                <a href="{{ route('admin.security-posts.trash.index') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                </a>
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
                
                <a href="{{ route('admin.security-reports.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>

                <!-- QR Code Stats Link -->
                <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $posts->first()?->id ?? 0]) }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-qrcode mr-1"></i> QR Codes
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-6">
        <!-- Total Posts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_posts'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Posts</div>
                </div>
            </div>
        </div>
        
        <!-- Active Posts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active_posts'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Posts</div>
                </div>
            </div>
        </div>
        
        <!-- Main Gates -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-door-closed"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['main_gates'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Main Gates</div>
                </div>
            </div>
        </div>
        
        <!-- Internal Gates -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-door-open"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['internal_gates'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Internal Gates</div>
                </div>
            </div>
        </div>
        
        <!-- Fully Staffed -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['fully_staffed'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Fully Staffed</div>
                </div>
            </div>
        </div>
        
        <!-- Understaffed -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['understaffed'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Understaffed</div>
                </div>
            </div>
        </div>

        <!-- Total QR Codes -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--purple-rgb, 128, 0, 128), 0.1); color: var(--purple, #800080);">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--purple, #800080);">{{ $qrCodeStats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">QR Codes</div>
                </div>
            </div>
        </div>

        <!-- Active QR Codes -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $qrCodeStats['active'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active QR</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staffing Alerts -->
    @if($staffingAlerts->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Staffing Alerts</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" 
                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    {{ $staffingAlerts->count() }} posts need attention
                </span>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($staffingAlerts->take(3) as $post)
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--text-primary);">{{ $post->name }}</h4>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $post->code }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold" style="color: var(--warning);">
                                {{ $post->current_personnel }}/{{ $post->max_personnel }}
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Short by {{ $post->max_personnel - $post->current_personnel }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $post->id }}" 
                       class="btn-primary btn-sm w-full flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                    </a>
                </div>
                @endforeach
            </div>
            @if($staffingAlerts->count() > 3)
            <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                <a href="{{ route('admin.security-schedules.index') }}?staffing_status=understaffed" 
                   class="text-sm inline-flex items-center hover:text-primary transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-arrow-right mr-1"></i>
                    View all {{ $staffingAlerts->count() }} understaffed posts
                </a>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                </h3>
                @if(request()->hasAny(['search', 'type', 'status', 'staffing_status']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, code, or location..."
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
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Staffing Status</label>
                        <select name="staffing_status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Staffing</option>
                            <option value="fully_staffed" {{ request('staffing_status') == 'fully_staffed' ? 'selected' : '' }}>Fully Staffed</option>
                            <option value="understaffed" {{ request('staffing_status') == 'understaffed' ? 'selected' : '' }}>Understaffed</option>
                            <option value="unstaffed" {{ request('staffing_status') == 'unstaffed' ? 'selected' : '' }}>Unstaffed</option>
                        </select>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.security-posts.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                    @if(request()->boolean('show_trashed'))
                    <a href="{{ route('admin.security-posts.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-eye mr-2"></i> Hide Trashed
                    </a>
                    @else
                    <a href="{{ route('admin.security-posts.index') }}?show_trashed=true" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-eye mr-2"></i> Show Trashed
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($posts->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select posts to perform actions on multiple items
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
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    <option value="trash">Move to Trash</option>
                    @if(request()->boolean('show_trashed'))
                    <option value="restore">Restore from Trash</option>
                    <option value="force_delete">Permanently Delete</option>
                    @endif
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

    <!-- Posts Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Security Posts
                    @if(request()->boolean('show_trashed'))
                    <span class="ml-2 px-2 py-1 text-xs rounded-full" 
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        Showing Trashed Posts
                    </span>
                    @endif
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $posts->count() }} of {{ $posts->total() }} posts
                    @if(request()->boolean('show_trashed'))
                    <span class="ml-2 text-warning">
                        ({{ $stats['trashed_posts'] }} in trash)
                    </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($posts->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Post</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Type</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Location</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Staffing</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">QR Codes</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                            @php
                                $isTrashed = property_exists($post, 'deleted_at') && $post->deleted_at;
                                $qrCount = $post->qr_codes_count ?? 0;
                                $activeQrCount = $post->active_qr_codes_count ?? 0;
                            @endphp
                            <tr class="border-b transition-colors duration-150 {{ $isTrashed ? 'bg-red-50' : '' }}" 
                                style="border-color: var(--border-color); {{ $isTrashed ? 'background-color: rgba(var(--danger-rgb), 0.05);' : '' }}">
                                @if($posts->count() > 0)
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="post-checkbox bulk-checkbox" 
                                           value="{{ $post->id }}"
                                           {{ $isTrashed ? 'data-trashed="true"' : '' }}>
                                </td>
                                @endif
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        @if($isTrashed)
                                        <div class="mr-2 text-red-500" title="This post is in trash">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                        @endif
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
                                                @if($isTrashed)
                                                <span class="ml-1 text-xs text-red-500">(Deleted)</span>
                                                @endif
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Code: {{ $post->code }}
                                                @if($isTrashed && $post->deleted_at)
                                                <br>
                                                <span class="text-red-500">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Deleted {{ $post->deleted_at->diffForHumans() }}
                                                </span>
                                                @endif
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
                                        {{ Str::limit($post->location, 30) }}
                                    </div>
                                    @if($post->digital_address)
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-pin mr-1"></i> {{ $post->digital_address }}
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                                            @php
                                                $currentPersonnel = $post->current_personnel ?? 0;
                                                $maxPersonnel = $post->max_personnel ?? 1;
                                                $staffingPercentage = min(100, ($currentPersonnel / max(1, $maxPersonnel)) * 100);
                                                $staffingColor = $currentPersonnel >= $maxPersonnel ? 'var(--success)' : 
                                                                 ($currentPersonnel > 0 ? 'var(--warning)' : 'var(--danger)');
                                            @endphp
                                            <div class="h-2 rounded-full" 
                                                 style="width: {{ $staffingPercentage }}%; 
                                                        background-color: {{ $staffingColor }};">
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium" 
                                             style="color: {{ $staffingColor }};">
                                            {{ $currentPersonnel }}/{{ $maxPersonnel }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if(!$isTrashed)
                                    <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $post->id]) }}" 
                                       class="inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors duration-150 hover:scale-105"
                                       style="background-color: {{ $qrCount > 0 ? 'rgba(var(--primary-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.05)' }}; 
                                              color: {{ $qrCount > 0 ? 'var(--primary)' : 'var(--text-secondary)' }};">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        <span>{{ $qrCount }}</span>
                                        @if($activeQrCount > 0)
                                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs"
                                              style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                            {{ $activeQrCount }} active
                                        </span>
                                        @endif
                                    </a>
                                    @else
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-qrcode mr-1"></i> Not available
                                    </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        @if($isTrashed)
                                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                                              style="background-color: rgba(var(--danger-rgb), 0.1); 
                                                     color: var(--danger);">
                                            <i class="fas fa-trash mr-1"></i> Trashed
                                        </span>
                                        @else
                                        <div class="relative">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium toggle-status-btn cursor-pointer"
                                                  data-post-id="{{ $post->id }}"
                                                  data-current-status="{{ $post->is_active ? 'active' : 'inactive' }}"
                                                  style="background-color: {{ $post->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                                         color: {{ $post->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                                {{ $post->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <div class="status-tooltip hidden absolute top-full left-0 mt-1 p-2 rounded shadow-lg z-10"
                                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    Click to toggle status
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @if($post->requires_checkin && !$isTrashed)
                                        <div class="ml-2 text-xs" style="color: var(--info);" title="Requires Check-in">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        @if($isTrashed)
                                            <!-- Actions for trashed posts -->
                                            <button onclick="restorePost({{ $post->id }}, '{{ $post->name }}')"
                                                    class="action-btn" 
                                                    title="Restore"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button onclick="showForceDeleteModal({{ $post->id }}, '{{ $post->name }}')"
                                                    class="action-btn" 
                                                    title="Permanently Delete"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <a href="{{ route('admin.security-posts.show', $post) }}" 
                                               class="action-btn" 
                                               title="View Details"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @else
                                            <!-- Actions for active posts -->
                                            <a href="{{ route('admin.security-posts.show', $post) }}" 
                                               class="action-btn" 
                                               title="View Details"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.security-posts.edit', $post) }}" 
                                               class="action-btn" 
                                               title="Edit"
                                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $post->id]) }}" 
                                               class="action-btn" 
                                               title="Manage QR Codes"
                                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-qrcode"></i>
                                            </a>
                                            <a href="{{ route('admin.security-schedules.index') }}?post_id={{ $post->id }}" 
                                               class="action-btn" 
                                               title="View Schedules"
                                               style="background-color: rgba(var(--purple-rgb, 128, 0, 128), 0.1); color: var(--purple, #800080);">
                                                <i class="fas fa-calendar-alt"></i>
                                            </a>
                                            <button onclick="showDeleteModal({{ $post->id }}, '{{ $post->name }}')"
                                                    class="action-btn" 
                                                    title="Delete"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $posts->count() > 0 ? '8' : '7' }}" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-map-marker-alt text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        @if(request()->boolean('show_trashed'))
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">Trash is empty</p>
                                        <p style="color: var(--text-secondary);">No deleted security posts found</p>
                                        <div class="mt-4">
                                            <a href="{{ route('admin.security-posts.index') }}" 
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                <i class="fas fa-arrow-left mr-2"></i> Back to Active Posts
                                            </a>
                                        </div>
                                        @else
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No security posts found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters or create a new post</p>
                                        <div class="mt-4">
                                            <a href="{{ route('admin.security-posts.create') }}" 
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                <i class="fas fa-plus mr-2"></i> Create First Post
                                            </a>
                                            @if($stats['trashed_posts'] > 0)
                                            <a href="{{ route('admin.security-posts.trash.index') }}" 
                                               class="ml-2 px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                <i class="fas fa-trash mr-2"></i> View Trash ({{ $stats['trashed_posts'] }})
                                            </a>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($posts->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $posts->firstItem() }} to {{ $posts->lastItem() }} of {{ $posts->total() }} posts
                        </div>
                        <div class="flex space-x-2">
                            @if($posts->onFirstPage())
                                <span class="px-3 py-2 rounded border text-sm" 
                                      style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            @else
                                <a href="{{ $posts->previousPageUrl() }}" 
                                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 transition-colors duration-150"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            @endif

                            @foreach($posts->getUrlRange(max(1, $posts->currentPage() - 2), min($posts->lastPage(), $posts->currentPage() + 2)) as $page => $url)
                                @if($page == $posts->currentPage())
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

                            @if($posts->hasMorePages())
                                <a href="{{ $posts->nextPageUrl() }}" 
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

<!-- Export Modal -->
<div id="exportModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('exportModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Export Security Posts</h3>
            <button type="button" class="modal-close" onclick="closeModal('exportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('admin.security-posts.export') }}" method="GET">
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Format</label>
                        <div class="flex space-x-2">
                            <label class="flex-1">
                                <input type="radio" name="format" value="csv" checked class="hidden">
                                <div class="p-3 border rounded-lg cursor-pointer text-center export-format-option"
                                     style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <i class="fas fa-file-csv text-2xl mb-2" style="color: var(--success);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">CSV</div>
                                </div>
                            </label>
                            <label class="flex-1">
                                <input type="radio" name="format" value="pdf" disabled class="hidden">
                                <div class="p-3 border rounded-lg cursor-not-allowed text-center opacity-50"
                                     style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <i class="fas fa-file-pdf text-2xl mb-2" style="color: var(--danger);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">PDF</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">(Coming Soon)</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Include</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="include_schedules" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include current schedules</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_equipment" value="1" class="mr-2" checked>
                                <span style="color: var(--text-secondary);">Include equipment list</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_statistics" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include staffing statistics</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_qr_codes" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include QR code information</span>
                            </label>
                            @if($stats['trashed_posts'] > 0)
                            <label class="flex items-center">
                                <input type="checkbox" name="include_trashed" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include trashed posts</span>
                            </label>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Apply Current Filters</label>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            The export will apply your current search and filter settings
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('exportModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal (Soft Delete - Move to Trash) -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Move to Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-trash text-5xl mb-4" style="color: var(--warning);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deletePostName">
                    <!-- Post name will be inserted here -->
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
                    style="background-color: var(--warning); border: 1px solid var(--warning);"
                    onclick="confirmDelete()">
                <i class="fas fa-trash mr-2"></i> Move to Trash
            </button>
        </div>
    </div>
</div>

<!-- Force Delete Modal (Permanently Delete from Trash) -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete</h3>
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
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action will <strong class="text-red-600">permanently delete</strong> the security post and all its data.
                    This cannot be undone!
                </p>
                <div class="p-3 mb-4 rounded-lg border" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Warning: Any related schedules and QR codes will also be permanently deleted.
                    </div>
                </div>
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
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
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
// Canvas variables (for future use if needed)
let canvas = null;
let ctx = null;

// Bulk selection variables
let selectedPostIds = [];
let showingTrashed = {{ request()->boolean('show_trashed') ? 'true' : 'false' }};

// QR Code statistics
let qrCodeStats = {
    total: 0,
    active: 0
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load QR code statistics
    loadQrCodeStats();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Status toggle functionality
    initializeStatusToggles();
    
    // Export format selection
    initializeExportFormat();
    
    // Initialize action buttons
    initializeActionButtons();
    
    // Bulk selection checkboxes
    initializeBulkSelection();
    
    // Initialize QR code badges
    initializeQrBadges();
});

// QR Code Statistics Functions
async function loadQrCodeStats() {
    try {
        const response = await fetch('{{ route("admin.security-posts.api.qr-code-statistics") }}');
        const data = await response.json();
        
        if (data.success) {
            qrCodeStats = data.stats;
            
            // Update statistics cards
            const totalQrElement = document.querySelector('[data-qr-stats="total"]');
            const activeQrElement = document.querySelector('[data-qr-stats="active"]');
            
            if (totalQrElement) {
                totalQrElement.textContent = data.stats.total;
                totalQrElement.classList.add('stats-updated');
                setTimeout(() => totalQrElement.classList.remove('stats-updated'), 500);
            }
            
            if (activeQrElement) {
                activeQrElement.textContent = data.stats.active;
                activeQrElement.classList.add('stats-updated');
                setTimeout(() => activeQrElement.classList.remove('stats-updated'), 500);
            }
            
            // Update QR badges with active counts
            updateQrBadges(data.stats.by_post || {});
        }
    } catch (error) {
        console.error('Failed to load QR code statistics:', error);
    }
}

function updateQrBadges(postStats) {
    const qrBadges = document.querySelectorAll('.qr-badge');
    qrBadges.forEach(badge => {
        const postId = badge.getAttribute('data-post-id');
        if (postId && postStats[postId]) {
            const stats = postStats[postId];
            const countSpan = badge.querySelector('.qr-count');
            const activeSpan = badge.querySelector('.qr-active-count');
            
            if (countSpan) countSpan.textContent = stats.total || 0;
            if (activeSpan) activeSpan.textContent = stats.active || 0;
            
            // Update badge appearance based on active count
            if (stats.active > 0) {
                badge.classList.add('has-active');
            } else {
                badge.classList.remove('has-active');
            }
        }
    });
}

// Initialize QR badges
function initializeQrBadges() {
    const qrBadges = document.querySelectorAll('.qr-badge');
    qrBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
        
        badge.addEventListener('click', function(e) {
            e.stopPropagation();
            const postId = this.getAttribute('data-post-id');
            if (postId) {
                window.location.href = `{{ url('admin/security-posts') }}/${postId}/qr-codes`;
            }
        });
    });
}

// Initialize tooltips
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = this.nextElementSibling;
            if (tooltip && tooltip.classList.contains('status-tooltip')) {
                tooltip.classList.remove('hidden');
            }
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.nextElementSibling;
            if (tooltip && tooltip.classList.contains('status-tooltip')) {
                tooltip.classList.add('hidden');
            }
        });
    });
}

// Initialize status toggles
function initializeStatusToggles() {
    const toggleButtons = document.querySelectorAll('.toggle-status-btn');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const postId = this.getAttribute('data-post-id');
            const currentStatus = this.getAttribute('data-current-status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this post?`)) {
                return;
            }
            
            try {
                showLoading(`Updating post status...`);
                
                const response = await fetch(`{{ url('admin/security-posts') }}/${postId}/toggle-activation`, {
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
                    
                    // Reload QR stats as they might be affected
                    loadQrCodeStats();
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
}

// Initialize export format
function initializeExportFormat() {
    const exportFormatOptions = document.querySelectorAll('.export-format-option');
    exportFormatOptions.forEach(option => {
        option.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input[type="radio"]');
            if (input && !input.disabled) {
                input.checked = true;
                
                // Update visual selection
                exportFormatOptions.forEach(opt => {
                    opt.style.borderColor = 'var(--border-color)';
                    opt.style.backgroundColor = 'var(--bg-secondary)';
                });
                
                this.style.borderColor = 'var(--primary)';
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
        });
    });
}

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

/**
 * Enhanced confirmation dialog for destructive operations
 */
function showEnhancedConfirmation(title, description, points, inputLabel, requiredInput) {
    return new Promise((resolve) => {
        // Create modal elements
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-70 z-[10000] flex items-center justify-center';
        modalOverlay.id = 'enhancedConfirmationModal';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto';
        
        modalContent.innerHTML = `
            <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-600 dark:text-red-400"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2 text-red-600 dark:text-red-400">${title}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">${description}</p>
                
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-4 text-left">
                    <ul class="text-sm text-red-700 dark:text-red-300 space-y-1">
                        ${points.map(point => `<li class="flex items-start">
                            <i class="fas fa-times mr-2 mt-1 text-red-500 dark:text-red-400"></i>
                            <span>${point}</span>
                        </li>`).join('')}
                    </ul>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">${inputLabel}</label>
                    <input type="text" 
                           id="confirmationInput" 
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
                           placeholder="Type ${requiredInput} to confirm"
                           autocomplete="off">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">This helps prevent accidental deletion</p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" 
                            id="cancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors">
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
            if (document.body.contains(modalOverlay)) {
                document.body.removeChild(modalOverlay);
                document.body.style.overflow = 'auto';
            }
        }
    });
}

/**
 * Perform bulk action on selected posts
 * Fixed version with proper error handling and network error recovery
 */
async function performBulkAction() {
    const actionSelect = document.getElementById('bulkActionSelect');
    const selectedAction = actionSelect.value;
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    // Validate action selection
    if (!selectedAction) {
        showToast('Please select an action first', 'warning');
        return;
    }
    
    // Get selected posts
    const checkboxes = document.querySelectorAll('.post-checkbox:checked');
    if (checkboxes.length === 0) {
        showToast('Please select at least one post', 'warning');
        return;
    }
    
    const postIds = Array.from(checkboxes).map(cb => cb.value);
    const selectedCount = postIds.length;
    
    // Enhanced confirmation for destructive actions
    let isDestructive = false;
    let confirmMessage = '';
    let requiresSpecialConfirmation = false;
    const isTrashPage = window.location.pathname.includes('/trash');
    
    switch(selectedAction) {
        case 'force_delete':
            if (isTrashPage) {
                const confirmed = await showEnhancedConfirmation(
                    `⚠️ PERMANENT DELETE ${selectedCount} POST(S)`,
                    `You are about to permanently delete ${selectedCount} post(s) from trash. This action:`,
                    [
                        'Cannot be undone',
                        'Will delete all associated schedules and assignments',
                        'Will remove all related logs and activity records',
                        'Will make post codes available for reuse'
                    ],
                    'Type "DELETE" to confirm:',
                    'DELETE'
                );
                
                if (!confirmed) {
                    return;
                }
                requiresSpecialConfirmation = true;
            } else {
                confirmMessage = `⚠️ Are you sure you want to PERMANENTLY DELETE ${selectedCount} post(s)? This cannot be undone!`;
                isDestructive = true;
            }
            break;
            
        case 'trash':
            confirmMessage = `Are you sure you want to move ${selectedCount} post(s) to trash?`;
            isDestructive = true;
            break;
            
        case 'restore':
            if (!isTrashPage) {
                showToast('Restore action is only available from the trash page', 'warning');
                return;
            }
            confirmMessage = `Are you sure you want to restore ${selectedCount} post(s) from trash?`;
            break;
            
        case 'activate':
            confirmMessage = `Are you sure you want to activate ${selectedCount} post(s)?`;
            break;
            
        case 'deactivate':
            confirmMessage = `Are you sure you want to deactivate ${selectedCount} post(s)?`;
            break;
            
        default:
            confirmMessage = `Are you sure you want to perform this action on ${selectedCount} post(s)?`;
    }
    
    // Show regular confirmation for non-special cases
    if (!requiresSpecialConfirmation && (isDestructive || selectedAction === 'restore')) {
        if (!confirm(confirmMessage)) {
            return;
        }
    }
    
    // Disable button and show processing state
    const originalButtonText = bulkActionBtn.innerHTML;
    bulkActionBtn.disabled = true;
    bulkActionBtn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Processing ${selectedCount} post(s)...`;
    
    // Show loading overlay
    showLoading(`Processing ${selectedCount} post(s)... This may take a moment.`);
    
    try {
        // Determine which endpoint to use
        let endpoint = '';
        let requestBody = {};
        
        // Check if we're on the trash page
        const isTrashPageCheck = window.location.pathname.includes('/trash');
        
        if (selectedAction === 'restore' && isTrashPageCheck) {
            // Use the dedicated bulk restore endpoint for trash page
            endpoint = '{{ route("admin.security-posts.trash.bulk-restore") }}';
            requestBody = { post_ids: postIds };
        } else if (selectedAction === 'force_delete' && isTrashPageCheck) {
            // For force delete on trash page, use bulk-update with force_delete action
            endpoint = '{{ route("admin.security-posts.api.bulk-update") }}';
            requestBody = {
                post_ids: postIds,
                action: 'force_delete',
                data: {}
            };
        } else {
            // Use bulk-update for all other actions
            endpoint = '{{ route("admin.security-posts.api.bulk-update") }}';
            requestBody = {
                post_ids: postIds,
                action: selectedAction,
                data: {}
            };
        }
        
        // Log the request for debugging
        console.log('Sending request to:', endpoint);
        console.log('Request body:', requestBody);
        
        // Set a timeout for the fetch request (30 seconds)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000);
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        // Hide loading overlay immediately
        hideLoading();
        
        // Log response status
        console.log('Response status:', response.status);
        
        // Check if response is OK
        if (!response.ok) {
            let errorMessage = `Server error (${response.status})`;
            
            try {
                const errorText = await response.text();
                console.log('Error response text:', errorText);
                
                // Try to parse as JSON
                try {
                    const errorData = JSON.parse(errorText);
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // If not JSON, use the text
                    if (errorText) {
                        errorMessage = errorText.substring(0, 200); // Limit length
                    }
                }
            } catch (e) {
                errorMessage = response.statusText || errorMessage;
            }
            
            throw new Error(errorMessage);
        }
        
        // Get response as text first
        const rawText = await response.text();
        console.log('Raw response received');
        
        // Check if response is HTML instead of JSON (common error)
        if (rawText.trim().startsWith('<!DOCTYPE') || rawText.trim().startsWith('<html')) {
            console.error('Received HTML instead of JSON. This usually means an error occurred.');
            throw new Error('Server returned HTML instead of JSON. Please check the server logs.');
        }
        
        // Parse response - handle BOM if present
        let data;
        try {
            // Remove BOM if present (U+FEFF)
            let cleanText = rawText;
            if (rawText.charCodeAt(0) === 0xFEFF) {
                cleanText = rawText.slice(1);
                console.log('Removed BOM from response');
            }
            
            data = JSON.parse(cleanText);
            console.log('Response parsed successfully:', data);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Raw text that failed to parse:', rawText.substring(0, 500));
            throw new Error('Invalid JSON response from server. Please check the server logs.');
        }
        
        // Handle success
        if (data.success) {
            // Build success message based on action
            let successMessage = data.message || `Successfully processed ${selectedCount} post(s).`;
            
            // Add specific details for different actions
            if (selectedAction === 'force_delete') {
                const deletedCount = data.updated_count || 0;
                successMessage = `✅ Successfully deleted ${deletedCount} post(s) permanently.`;
                
                if (data.failed_posts && data.failed_posts.length > 0) {
                    const failedNames = data.failed_posts.map(p => p.name).join(', ');
                    successMessage += ` Failed to delete: ${failedNames}`;
                }
            } else if (selectedAction === 'restore') {
                const restoredCount = data.restored_count || 0;
                successMessage = `✅ Successfully restored ${restoredCount} post(s) from trash.`;
                
                if (data.failed_posts && data.failed_posts.length > 0) {
                    const failedNames = data.failed_posts.map(p => p.name).join(', ');
                    successMessage += ` Failed to restore: ${failedNames}`;
                }
            } else if (selectedAction === 'trash') {
                successMessage = `✅ Successfully moved ${selectedCount} post(s) to trash.`;
            } else if (selectedAction === 'activate') {
                successMessage = `✅ Successfully activated ${selectedCount} post(s).`;
            } else if (selectedAction === 'deactivate') {
                successMessage = `✅ Successfully deactivated ${selectedCount} post(s).`;
            }
            
            // Show toast with appropriate type
            let toastType = 'success';
            if (data.failed_posts && data.failed_posts.length > 0) {
                toastType = 'warning';
                console.warn('Failed operations:', data.failed_posts);
            }
            
            showToast(successMessage, toastType);
            
            // Clear selection
            deselectAllPosts();
            actionSelect.value = '';
            
            // Hide bulk delete warning if shown
            const bulkDeleteWarning = document.getElementById('bulkDeleteWarning');
            if (bulkDeleteWarning) {
                bulkDeleteWarning.classList.add('hidden');
            }
            
            // Reset button state
            bulkActionBtn.disabled = false;
            bulkActionBtn.innerHTML = originalButtonText;
            
            // Refresh QR stats if needed
            if (typeof loadQrCodeStats === 'function') {
                loadQrCodeStats();
            }
            
            // Refresh the page after a delay to show updated state
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            // Server returned success=false
            showToast(data.message || 'Failed to perform bulk action', 'error');
            
            // Reset button state
            bulkActionBtn.disabled = false;
            bulkActionBtn.innerHTML = originalButtonText;
        }
        
    } catch (error) {
        // Hide loading overlay
        hideLoading();
        
        // Handle different types of errors
        let errorMessage = 'An unexpected error occurred';
        
        if (error.name === 'AbortError') {
            errorMessage = 'Request timed out. The server is taking too long to respond. Please try again.';
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        // Log error for debugging
        console.error('Bulk action error:', error);
        
        // Show error toast
        showToast(`❌ ${errorMessage}`, 'error');
        
        // Reset button state
        bulkActionBtn.disabled = false;
        bulkActionBtn.innerHTML = originalButtonText;
        
        // Offer retry option for network errors
        if (error.name === 'AbortError' || error.message.includes('Network') || error.message.includes('network') || error.message.includes('HTML')) {
            if (confirm('Would you like to retry the operation?')) {
                // Re-enable button and let user try again
                bulkActionBtn.disabled = false;
                bulkActionBtn.innerHTML = originalButtonText;
            }
        }
    }
}

function showExportModal() {
    openModal('exportModal');
}

async function showDeleteModal(postId, postName) {
    // Set post name
    document.getElementById('deletePostName').textContent = postName;
    
    // Check for active schedules
    showLoading('Checking for active schedules...');
    
    try {
        const hasActiveSchedules = await checkPostSchedules(postId);
        
        hideLoading();
        
        if (hasActiveSchedules) {
            document.getElementById('deleteWarningText').textContent = 
                'This post cannot be moved to trash because it has active or upcoming schedules.';
            document.getElementById('activeSchedulesWarning').classList.remove('hidden');
            document.getElementById('schedulesCountText').textContent = 
                'This post has active schedules. Please reassign or cancel them first.';
            
            // Disable delete button
            const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
            deleteBtn.disabled = true;
            deleteBtn.style.opacity = '0.5';
            deleteBtn.style.cursor = 'not-allowed';
        } else {
            document.getElementById('deleteWarningText').textContent = 
                'Are you sure you want to move this security post to trash? You can restore it later if needed.';
            document.getElementById('activeSchedulesWarning').classList.add('hidden');
            
            // Enable delete button
            const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
            deleteBtn.disabled = false;
            deleteBtn.style.opacity = '1';
            deleteBtn.style.cursor = 'pointer';
        }
    } catch (error) {
        hideLoading();
        showToast('Error checking schedules', 'error');
    }
    
    // Update delete form action
    const deleteForm = document.getElementById('deleteForm');
    deleteForm.action = `{{ url('admin/security-posts') }}/${postId}`;
    
    openModal('deleteModal');
}

function showForceDeleteModal(postId, postName) {
    // Set post name
    document.getElementById('forceDeletePostName').textContent = postName;
    
    // Update force delete form action
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.action = `{{ url('admin/security-posts/trash') }}/${postId}/force-delete`;
    
    openModal('forceDeleteModal');
}

async function restorePost(postId, postName) {
    if (!confirm(`Are you sure you want to restore "${postName}"?`)) {
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
            
            // Redirect to regular index page after a short delay
            setTimeout(() => {
                window.location.href = '{{ route("admin.security-posts.index") }}';
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

async function checkPostSchedules(postId) {
    try {
        const response = await fetch(`{{ url('admin/security-schedules') }}?post_id=${postId}&status=scheduled,active&limit=1`);
        const html = await response.text();
        
        // Simple check - if the response contains schedule data
        return html.includes('schedule-item') || html.includes('No schedules') === false;
    } catch (error) {
        console.error('Error checking schedules:', error);
        return true; // Assume has schedules to be safe
    }
}

function confirmDelete() {
    const deleteBtn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
    if (!deleteBtn.disabled) {
        const deleteForm = document.getElementById('deleteForm');
        deleteForm.submit();
    }
}

function confirmForceDelete() {
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.submit();
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
    // Remove any existing overlay first
    const existingOverlay = document.getElementById('loadingOverlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }
    
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        backdrop-filter: blur(4px);
    `;
    
    overlay.innerHTML = `
        <div class="text-center">
            <div class="inline-block">
                <div class="loading-spinner"></div>
            </div>
            <div class="text-white font-medium mt-4 text-lg" id="loadingMessage">${message}</div>
            <div class="text-white/50 text-sm mt-2">Please wait...</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
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

// Auto-refresh QR stats every 30 seconds
setInterval(loadQrCodeStats, 30000);
</script>

<style>
/* Loading Spinner */
.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top: 4px solid #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

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

/* Trash indicator */
.trash-indicator {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
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

/* QR Code Badge Styles */
.qr-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.2);
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.qr-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(var(--primary-rgb), 0.2);
    background-color: rgba(var(--primary-rgb), 0.15);
}

.qr-badge.has-active {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.qr-badge.has-active:hover {
    background-color: rgba(var(--success-rgb), 0.15);
    box-shadow: 0 4px 8px rgba(var(--success-rgb), 0.2);
}

.qr-badge .qr-count {
    font-weight: 600;
    margin-right: 0.25rem;
}

.qr-badge .qr-active-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.15rem 0.4rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background-color: rgba(var(--success-rgb), 0.2);
    color: var(--success);
    margin-left: 0.35rem;
}

.qr-badge i {
    font-size: 0.7rem;
    margin-right: 0.25rem;
}

/* Statistics update animation */
.stats-updated {
    animation: statsPulse 0.5s ease;
}

@keyframes statsPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); color: var(--primary); }
    100% { transform: scale(1); }
}

/* Post icon styles */
.post-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(var(--primary-rgb), 0.1);
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
    .grid.grid-cols-1.md\:grid-cols-8 {
        grid-template-columns: repeat(4, 1fr);
    }
    
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
    
    .qr-badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-8,
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
</style>
@endsection