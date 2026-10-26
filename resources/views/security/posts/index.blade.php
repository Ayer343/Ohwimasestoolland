@extends('layouts.secu')

@section('title', 'Security Posts')

@php
    $isHoS = isset($isAreaSupervisor) && $isAreaSupervisor === true;
    // Defensive: hide QR links if the supervisor QR routes are missing.
    $qrRoutesReady = \Illuminate\Support\Facades\Route::has('security.posts.qr-codes.index');
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
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
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                        <span class="mx-2">•</span>
                        <span class="text-xs">
                            <i class="fas fa-layer-group mr-1"></i>
                            {{ $posts->total() ?? 0 }} posts
                        </span>
                        <span class="mx-2">•</span>
                        <span class="text-xs" style="color: {{ $isHoS ? 'var(--success)' : 'var(--text-secondary)' }};">
                            <i class="fas fa-{{ $isHoS ? 'user-tie' : 'user' }} mr-1"></i>
                            {{ $isHoS ? 'Head of Security' : 'Security Personnel' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                @if($isHoS)
                    <a href="{{ route('security.posts.create') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-plus mr-2"></i> Create Post
                    </a>
                    <button type="button" onclick="showExportModal()"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>

                    @if(!empty($stats['trashed_posts']))
                        <a href="{{ route('security.posts.trash') }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-trash mr-2"></i> Trash
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ $stats['trashed_posts'] }}
                            </span>
                        </a>
                    @else
                        <a href="{{ route('security.posts.trash') }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-trash mr-2"></i> Trash
                        </a>
                    @endif
                @endif

                <a href="{{ route('security.posts.my-post') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-user-check mr-2"></i> My Post
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         NAVIGATION  (Head of Security only)
         ============================================================ --}}
    @if($isHoS)
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 flex-wrap">
                <a href="{{ route('security.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Security Dashboard
                </a>

                <a href="{{ route('security.schedules.index') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Schedules
                </a>

                <a href="{{ route('security.posts.staffing.all') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-users mr-1"></i> Staffing Overview
                </a>

                <a href="{{ route('security.posts.coverage-report') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Coverage Report
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         STATS  (Head of Security only — full admin parity)
         ============================================================ --}}
    @if($isHoS && isset($stats))
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-6">
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
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--purple-rgb, 128, 0, 128), 0.1); color: var(--purple, #800080);">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--purple, #800080);" data-qr-stats="total">
                        {{ $qrCodeStats['total'] ?? 0 }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">QR Codes</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);" data-qr-stats="active">
                        {{ $qrCodeStats['active'] ?? 0 }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active QR</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         STAFFING ALERTS  (Head of Security only)
         ============================================================ --}}
    @if($isHoS && isset($staffingAlerts) && $staffingAlerts->count() > 0)
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
                        <div class="text-right">
                            <div class="text-lg font-bold" style="color: var(--warning);">
                                {{ $post->current_personnel }}/{{ $post->max_personnel }}
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Short by {{ $post->max_personnel - $post->current_personnel }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('security.schedules.create') }}?post_id={{ $post->id }}"
                       class="btn-primary btn-sm w-full flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                    </a>
                </div>
                @endforeach
            </div>
            @if($staffingAlerts->count() > 3)
            <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                <a href="{{ route('security.posts.index') }}?staffing_status=understaffed"
                   class="text-sm inline-flex items-center"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-arrow-right mr-1"></i>
                    View all {{ $staffingAlerts->count() }} understaffed posts
                </a>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ============================================================
         FILTERS
         ============================================================ --}}
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
            <form method="GET" action="{{ route('security.posts.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" name="search"
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, code, or location..."
                               value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Type</label>
                        <select name="type" class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Types</option>
                            <option value="main_gate"        {{ request('type') == 'main_gate'        ? 'selected' : '' }}>Main Gate</option>
                            <option value="internal_gate"    {{ request('type') == 'internal_gate'    ? 'selected' : '' }}>Internal Gate</option>
                            <option value="checkpoint"       {{ request('type') == 'checkpoint'       ? 'selected' : '' }}>Checkpoint</option>
                            <option value="patrol_route"     {{ request('type') == 'patrol_route'     ? 'selected' : '' }}>Patrol Route</option>
                            <option value="observation_post" {{ request('type') == 'observation_post' ? 'selected' : '' }}>Observation Post</option>
                            <option value="control_room"     {{ request('type') == 'control_room'     ? 'selected' : '' }}>Control Room</option>
                            <option value="access_point"     {{ request('type') == 'access_point'     ? 'selected' : '' }}>Access Point</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Status</option>
                            <option value="active"   {{ request('status') == 'active'   ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Staffing Status</label>
                        <select name="staffing_status" class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Staffing</option>
                            <option value="fully_staffed" {{ request('staffing_status') == 'fully_staffed' ? 'selected' : '' }}>Fully Staffed</option>
                            <option value="understaffed"  {{ request('staffing_status') == 'understaffed'  ? 'selected' : '' }}>Understaffed</option>
                            <option value="unstaffed"     {{ request('staffing_status') == 'unstaffed'     ? 'selected' : '' }}>Unstaffed</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap space-x-2">
                    <button type="submit" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('security.posts.index') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>

                    @if($isHoS)
                        @if(request()->boolean('show_trashed'))
                        <a href="{{ route('security.posts.index', request()->except('show_trashed')) }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-eye mr-2"></i> Hide Trashed
                        </a>
                        @else
                        <a href="{{ route('security.posts.index', array_merge(request()->all(), ['show_trashed' => 'true'])) }}"
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-eye mr-2"></i> Show Trashed
                        </a>
                        @endif
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================
         BULK ACTIONS  (Head of Security only)
         ============================================================ --}}
    @if($isHoS && $posts->count() > 0)
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
                    <button type="button" onclick="selectAllPosts()"
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" onclick="deselectAllPosts()"
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <select id="bulkActionSelect" class="form-input p-3 rounded-lg border col-span-1 md:col-span-2"
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
                <button type="button" onclick="performBulkAction()" id="bulkActionBtn"
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         POSTS
         ============================================================ --}}
    @if($isHoS)
        {{-- ============ TABLE (admin parity) ============ --}}
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
                        <span class="ml-2" style="color: var(--warning);">
                            ({{ $stats['trashed_posts'] ?? 0 }} in trash)
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
                                    $isTrashed = $post->deleted_at !== null;
                                    $qrCount = $post->qr_codes_count ?? 0;
                                    $activeQrCount = $post->active_qr_codes_count ?? 0;
                                    $currentPersonnel = $post->current_personnel ?? 0;
                                    $maxPersonnel = $post->max_personnel ?? 1;
                                    $staffingPercentage = min(100, ($currentPersonnel / max(1, $maxPersonnel)) * 100);
                                    $staffingColor = $currentPersonnel >= $maxPersonnel
                                        ? 'var(--success)'
                                        : ($currentPersonnel > 0 ? 'var(--warning)' : 'var(--danger)');
                                    $typeIcons = [
                                        'main_gate'        => ['icon' => 'fa-door-closed', 'color' => 'var(--primary)'],
                                        'internal_gate'    => ['icon' => 'fa-door-open',   'color' => 'var(--info)'],
                                        'checkpoint'       => ['icon' => 'fa-shield-alt',  'color' => 'var(--success)'],
                                        'patrol_route'     => ['icon' => 'fa-route',       'color' => 'var(--warning)'],
                                        'observation_post' => ['icon' => 'fa-binoculars',  'color' => 'var(--danger)'],
                                        'control_room'     => ['icon' => 'fa-tv',          'color' => 'var(--purple)'],
                                        'access_point'     => ['icon' => 'fa-key',         'color' => 'var(--pink)'],
                                    ];
                                    $typeIcon = $typeIcons[$post->type] ?? ['icon' => 'fa-map-marker-alt', 'color' => 'var(--secondary)'];
                                    $typeColors = [
                                        'main_gate'        => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)'],
                                        'internal_gate'    => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'text' => 'var(--info)'],
                                        'checkpoint'       => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)'],
                                        'patrol_route'     => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                                        'observation_post' => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)'],
                                        'control_room'     => ['bg' => 'rgba(var(--purple-rgb), 0.1)',  'text' => 'var(--purple)'],
                                        'access_point'     => ['bg' => 'rgba(var(--pink-rgb), 0.1)',    'text' => 'var(--pink)'],
                                    ];
                                    $typeColor = $typeColors[$post->type] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)'];
                                @endphp
                                <tr class="border-b transition-colors duration-150"
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
                                            <div class="mr-2" style="color: var(--danger);" title="This post is in trash">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                            @endif
                                            <div class="post-icon mr-3">
                                                <i class="fas {{ $typeIcon['icon'] }}" style="color: {{ $typeIcon['color'] }};"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $post->name }}
                                                    @if($isTrashed)
                                                    <span class="ml-1 text-xs" style="color: var(--danger);">(Deleted)</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Code: {{ $post->code }}
                                                    @if($isTrashed && $post->deleted_at)
                                                    <br>
                                                    <span style="color: var(--danger);">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Deleted {{ $post->deleted_at->diffForHumans() }}
                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                              style="background-color: {{ $typeColor['bg'] }}; color: {{ $typeColor['text'] }};">
                                            {{ str_replace('_', ' ', $post->type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ \Illuminate\Support\Str::limit($post->location, 30) }}
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
                                                <div class="h-2 rounded-full"
                                                     style="width: {{ $staffingPercentage }}%; background-color: {{ $staffingColor }};"></div>
                                            </div>
                                            <div class="text-sm font-medium" style="color: {{ $staffingColor }};">
                                                {{ $currentPersonnel }}/{{ $maxPersonnel }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if(!$isTrashed && $qrRoutesReady)
                                        {{-- SUPERVISOR QR link (was admin) --}}
                                        <a href="{{ route('security.posts.qr-codes.index', $post) }}"
                                           class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                                           style="background-color: {{ $qrCount > 0 ? 'rgba(var(--primary-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.05)' }};
                                                  color: {{ $qrCount > 0 ? 'var(--primary)' : 'var(--text-secondary)' }};">
                                            <i class="fas fa-qrcode mr-1"></i>
                                            <span class="qr-count">{{ $qrCount }}</span>
                                            @if($activeQrCount > 0)
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs qr-active-count"
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
                                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-trash mr-1"></i> Trashed
                                            </span>
                                            @else
                                            <span class="px-3 py-1 rounded-full text-xs font-medium toggle-status-btn cursor-pointer"
                                                  data-post-id="{{ $post->id }}"
                                                  data-current-status="{{ $post->is_active ? 'active' : 'inactive' }}"
                                                  style="background-color: {{ $post->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};
                                                         color: {{ $post->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                                {{ $post->is_active ? 'Active' : 'Inactive' }}
                                            </span>
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
                                                <button type="button"
                                                        onclick="restorePost({{ $post->id }}, @js($post->name))"
                                                        class="action-btn" title="Restore"
                                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="showForceDeleteModal({{ $post->id }}, @js($post->name))"
                                                        class="action-btn" title="Permanently Delete"
                                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <a href="{{ route('security.posts.show', $post) }}"
                                                   class="action-btn" title="View Details"
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('security.posts.show', $post) }}"
                                                   class="action-btn" title="View Details"
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('security.posts.edit', $post) }}"
                                                   class="action-btn" title="Edit"
                                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if($qrRoutesReady)
                                                {{-- SUPERVISOR QR link (was admin) --}}
                                                <a href="{{ route('security.posts.qr-codes.index', $post) }}"
                                                   class="action-btn" title="Manage QR Codes"
                                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                    <i class="fas fa-qrcode"></i>
                                                </a>
                                                @endif
                                                <a href="{{ route('security.schedules.index') }}?post_id={{ $post->id }}"
                                                   class="action-btn" title="View Schedules"
                                                   style="background-color: rgba(var(--purple-rgb, 128, 0, 128), 0.1); color: var(--purple, #800080);">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </a>
                                                <button type="button"
                                                        onclick="showDeleteModal({{ $post->id }}, @js($post->name))"
                                                        class="action-btn" title="Delete"
                                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $posts->count() > 0 ? '8' : '7' }}" class="py-8 px-4 text-center"
                                        style="color: var(--text-secondary);">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-map-marker-alt text-4xl mb-3" style="opacity: 0.5;"></i>
                                            @if(request()->boolean('show_trashed'))
                                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">Trash is empty</p>
                                            <p>No deleted security posts found</p>
                                            <div class="mt-4">
                                                <a href="{{ route('security.posts.index') }}"
                                                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                    <i class="fas fa-arrow-left mr-2"></i> Back to Active Posts
                                                </a>
                                            </div>
                                            @else
                                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No security posts found</p>
                                            <p>Try adjusting your filters or create a new post</p>
                                            <div class="mt-4">
                                                <a href="{{ route('security.posts.create') }}"
                                                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                    <i class="fas fa-plus mr-2"></i> Create First Post
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

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
                                       class="px-3 py-2 rounded border text-sm"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                        <i class="fas fa-chevron-left mr-1"></i> Previous
                                    </a>
                                @endif
                                @foreach($posts->getUrlRange(max(1, $posts->currentPage() - 2), min($posts->lastPage(), $posts->currentPage() + 2)) as $page => $url)
                                    @if($page == $posts->currentPage())
                                        <span class="px-3 py-2 rounded text-sm font-medium"
                                              style="background-color: var(--primary); color: white;">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}"
                                           class="px-3 py-2 rounded border text-sm"
                                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                            {{ $page }}
                                        </a>
                                    @endif
                                @endforeach
                                @if($posts->hasMorePages())
                                    <a href="{{ $posts->nextPageUrl() }}"
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
            </div>
        </div>
    @else
        {{-- ============ CARD GRID (regular personnel — unchanged) ============ --}}
        @if(isset($posts) && $posts->count() > 0)
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-layer-group mr-1"></i>
                Showing {{ $posts->firstItem() ?? 0 }} - {{ $posts->lastItem() ?? 0 }} of {{ $posts->total() ?? 0 }}
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
                @foreach($posts as $post)
                    @php
                        $currentPersonnel = $post->current_personnel ?? 0;
                        $status = $currentPersonnel >= $post->max_personnel
                            ? 'fully_staffed'
                            : ($currentPersonnel > 0 ? 'understaffed' : 'unstaffed');
                        $statusColors = ['fully_staffed' => 'success', 'understaffed' => 'warning', 'unstaffed' => 'danger'];
                        $statusLabels = ['fully_staffed' => 'Full', 'understaffed' => 'Short', 'unstaffed' => 'Empty'];
                    @endphp
                    <div class="card p-6 hover:shadow-lg transition-shadow duration-300 relative">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 flex-shrink-0"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 18px; font-weight: 600;">
                                    {{ strtoupper(substr($post->name, 0, 1)) }}
                                </div>
                                <div>
                                    <h4 class="font-semibold" style="color: var(--text-primary);">{{ $post->name }}</h4>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-tag mr-1"></i> {{ $post->code ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-1"></i> {{ $post->location ?? 'No location' }}
                                    </div>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full badge-{{ $statusColors[$status] }}">
                                {{ $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center space-x-4">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-users mr-1"></i> {{ $currentPersonnel }}/{{ $post->max_personnel }}
                            </span>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i> {{ $post->working_hours_info['display'] ?? '24/7' }}
                            </span>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-tag mr-1"></i> {{ ucfirst(str_replace('_', ' ', $post->type)) }}
                            </span>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <a href="{{ route('security.posts.show', $post->id) }}"
                               class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium"
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="{{ route('security.posts.schedule', ['securityPost' => $post->id]) }}"
                               class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-calendar-alt mr-1"></i> Schedule
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $posts->withQueryString()->links() }}</div>
        @else
            <div class="card p-12 text-center">
                <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-building text-4xl" style="color: var(--info);"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Posts Found</h3>
                <p class="mb-6" style="color: var(--text-secondary);">
                    {{ request()->has('search') ? 'No posts match your search criteria.' : 'No active security posts available.' }}
                </p>
                @if(request()->hasAny(['search', 'type', 'staffing_status', 'status']))
                    <a href="{{ route('security.posts.index') }}"
                       class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-undo mr-2"></i> Clear Filters
                    </a>
                @endif
            </div>
        @endif
    @endif
</div>

{{-- ============================================================
     MODALS  (Head of Security only)
     ============================================================ --}}
@if($isHoS)
<div id="exportModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('exportModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Export Security Posts</h3>
            <button type="button" class="modal-close" onclick="closeModal('exportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('security.posts.export') }}" method="GET">
            <div class="modal-body space-y-4">
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
                        @if(!empty($stats['trashed_posts']))
                        <label class="flex items-center">
                            <input type="checkbox" name="include_trashed" value="1" class="mr-2">
                            <span style="color: var(--text-secondary);">Include trashed posts</span>
                        </label>
                        @endif
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> The export applies your current search and filter settings.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 rounded-lg font-medium"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('exportModal')">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </form>
    </div>
</div>

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
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deletePostName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);" id="deleteWarningText"></p>
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
            <button type="button" class="px-4 py-2 rounded-lg font-medium"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteModal')">Cancel</button>
            <button type="button" id="confirmDeleteBtn"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--warning);">
                <i class="fas fa-trash mr-2"></i> Move to Trash
            </button>
        </div>
    </div>
</div>

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
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeletePostName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action will <strong class="text-red-600">permanently delete</strong> the security post and all its data. This cannot be undone!
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
            <button type="button" class="px-4 py-2 rounded-lg font-medium"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('forceDeleteModal')">Cancel</button>
            <button type="button" id="confirmForceDeleteBtn"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger);">
                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>
@endif

<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<style>
.btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: #fff; border: 1px solid var(--primary); transition: all .2s ease; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), .3); }
.btn-primary:disabled { opacity: .7; cursor: not-allowed; transform: none; }

.card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }

.badge-success { background-color: rgba(var(--success-rgb), .1); color: var(--success); border: 1px solid rgba(var(--success-rgb), .3); }
.badge-warning { background-color: rgba(var(--warning-rgb), .1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), .3); }
.badge-danger  { background-color: rgba(var(--danger-rgb),  .1); color: var(--danger);  border: 1px solid rgba(var(--danger-rgb),  .3); }

.form-input, .form-select {
    background-color: var(--card-bg); color: var(--text-primary);
    border: 1px solid var(--border-color); border-radius: 8px;
}
.form-input:focus, .form-select:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), .1);
}

.bulk-checkbox { width: 18px; height: 18px; border-radius: 4px; cursor: pointer; accent-color: var(--primary); }

.modal { position: fixed; inset: 0; z-index: 9999; }
.modal-overlay { position: absolute; inset: 0; background-color: rgba(0,0,0,.5); backdrop-filter: blur(5px); }
.modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2); border: 1px solid var(--border-color); animation: modalFadeIn .3s ease-out; }
.modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: .25rem; border-radius: 6px; }
.modal-close:hover { background-color: rgba(var(--primary-rgb), .1); color: var(--text-primary); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: .75rem; background-color: var(--bg-secondary); border-radius: 0 0 16px 16px; }

@keyframes modalFadeIn { from { opacity: 0; transform: scale(.9) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.action-btn { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all .2s ease; }
.action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,.1); }

.post-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background-color: rgba(var(--primary-rgb), .1); }

.stats-updated { animation: statsPulse .5s ease; }
@keyframes statsPulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); color: var(--primary); } 100% { transform: scale(1); } }

.loading-spinner { width: 60px; height: 60px; border: 4px solid rgba(255,255,255,.1); border-top: 4px solid #fff; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-8 { grid-template-columns: repeat(4, 1fr); }
    .grid.grid-cols-1.md\:grid-cols-3, .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    table th, table td { padding: .5rem; font-size: .875rem; }
    .modal-container { margin: 1rem; }
}
</style>

<script>
// ============================================================
// STATE
// ============================================================
const isHoS = {{ $isHoS ? 'true' : 'false' }};
const CSRF  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
const URL_BASE_POSTS  = '{{ url("security/posts") }}';
const URL_BASE_TRASH  = '{{ url("security/posts/trash") }}';
const URL_BULK_UPDATE = '{{ route("security.posts.api.bulk-update") }}';
const URL_QR_STATS    = '{{ route("security.posts.statistics.qr-codes") }}';

// ============================================================
// HELPERS
// ============================================================
function openModal(id)  { const m = document.getElementById(id); if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; } }

async function parseJsonSafe(response) {
    const raw = await response.text();
    let clean = raw;
    if (clean.charCodeAt(0) === 0xFEFF) clean = clean.slice(1);
    if (clean.trim().startsWith('<!DOCTYPE') || clean.trim().startsWith('<html')) {
        throw new Error('Server returned HTML instead of JSON.');
    }
    let body;
    try { body = JSON.parse(clean); }
    catch (e) {
        if (response.ok) return { success: true, message: 'OK', raw };
        throw new Error(e.message || `HTTP ${response.status}`);
    }
    if (!response.ok) throw new Error(body.message || `HTTP ${response.status}`);
    return body;
}

function showToast(message, type = 'info') {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; c.className = 'fixed top-4 right-4 z-50 space-y-2'; document.body.appendChild(c); }
    const t = document.createElement('div');
    t.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error'   ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                             'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    const m = document.createElement('span'); m.className = 'text-sm font-medium flex-1'; m.textContent = message;
    const b = document.createElement('button'); b.className = 'ml-4'; b.innerHTML = '<i class="fas fa-times"></i>';
    b.onclick = () => { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); };
    t.append(m, b); c.appendChild(t);
    requestAnimationFrame(() => { t.classList.remove('translate-x-full'); t.classList.add('translate-x-0'); });
    setTimeout(() => { if (t.parentNode === c) { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); } }, 5000);
}

function showLoading(msg = 'Processing...') {
    hideLoading();
    const o = document.createElement('div');
    o.id = 'loadingOverlay';
    o.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center;z-index:99999;backdrop-filter:blur(4px);';
    o.innerHTML = `<div class="text-center">
        <div class="inline-block"><div class="loading-spinner"></div></div>
        <div class="text-white font-medium mt-4 text-lg">${msg}</div>
        <div class="text-white/50 text-sm mt-2">Please wait...</div>
    </div>`;
    document.body.appendChild(o);
    document.body.style.overflow = 'hidden';
}
function hideLoading() {
    const o = document.getElementById('loadingOverlay');
    if (o) { o.remove(); document.body.style.overflow = 'auto'; }
}

// ============================================================
// QR STATS  (Head of Security only)
// ============================================================
async function loadQrCodeStats() {
    if (!isHoS) return;
    try {
        const r = await fetch(URL_QR_STATS, { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (!d.success) return;
        const total = document.querySelector('[data-qr-stats="total"]');
        const active = document.querySelector('[data-qr-stats="active"]');
        if (total)  { total.textContent  = d.stats.total;  total.classList.add('stats-updated');  setTimeout(() => total.classList.remove('stats-updated'),  500); }
        if (active) { active.textContent = d.stats.active; active.classList.add('stats-updated'); setTimeout(() => active.classList.remove('stats-updated'), 500); }
    } catch (e) { console.warn('QR stats failed:', e.message); }
}

// ============================================================
// BULK SELECTION  (Head of Security only)
// ============================================================
function updateSelectedCount() {
    const n = document.querySelectorAll('.post-checkbox:checked').length;
    const b = document.getElementById('bulkActionBtn');
    if (!b) return;
    b.disabled = n === 0;
    b.innerHTML = `<i class="fas fa-play mr-2"></i> Apply${n ? ` (${n} selected)` : ''}`;
}
function selectAllPosts()   { document.querySelectorAll('.post-checkbox').forEach(cb => cb.checked = true);  updateSelectedCount(); }
function deselectAllPosts() { document.querySelectorAll('.post-checkbox').forEach(cb => cb.checked = false); updateSelectedCount(); }

// ============================================================
// STATUS TOGGLE  (Head of Security only)
// ============================================================
document.addEventListener('click', async (e) => {
    const el = e.target.closest('.toggle-status-btn');
    if (!el || !isHoS) return;
    const id = el.dataset.postId;
    if (!confirm(`Toggle status of this post?`)) return;
    try {
        const r = await fetch(`${URL_BASE_POSTS}/${id}/toggle-activation`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const d = await parseJsonSafe(r);
        if (d.success) {
            showToast(d.message || 'Status updated.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(d.message || 'Failed.', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
});

// ============================================================
// BULK ACTION  (Head of Security only)
// ============================================================
async function performBulkAction() {
    const sel = document.getElementById('bulkActionSelect');
    const btn = document.getElementById('bulkActionBtn');
    const action = sel?.value;
    if (!action) { showToast('Please select an action.', 'warning'); return; }

    const ids = Array.from(document.querySelectorAll('.post-checkbox:checked')).map(cb => cb.value);
    if (!ids.length) { showToast('Select at least one post.', 'warning'); return; }

    if (['trash','force_delete'].includes(action) && !confirm(`Are you sure you want to ${action === 'trash' ? 'move to trash' : 'permanently delete'} ${ids.length} post(s)?`)) return;

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Processing...`;
    try {
        const r = await fetch(URL_BULK_UPDATE, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_ids: ids, action, data: {} })
        });
        const d = await parseJsonSafe(r);
        if (d.success) {
            showToast(d.message || 'Bulk action done.', 'success');
            deselectAllPosts();
            if (sel) sel.value = '';
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(d.message || 'Bulk action failed.', 'error');
            btn.disabled = false; btn.innerHTML = orig;
        }
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false; btn.innerHTML = orig;
    }
}

// ============================================================
// DELETE / FORCE-DELETE / RESTORE  (Head of Security only)
// ============================================================
let _deleteId = null, _forceDeleteId = null;
async function checkPostSchedules(postId) {
    try {
        const r = await fetch(`${URL_BASE_POSTS}/${postId}/availability`, { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        return d?.data?.scheduled_personnel > 0;
    } catch { return true; }
}
function showDeleteModal(id, name) {
    _deleteId = id;
    document.getElementById('deletePostName').textContent = name;
    document.getElementById('deleteWarningText').textContent = 'Are you sure you want to move this security post to trash? You can restore it later.';
    const warn = document.getElementById('activeSchedulesWarning');
    warn.classList.add('hidden');
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = false;
    btn.onclick = () => executeDelete(id);
    openModal('deleteModal');
    checkPostSchedules(id).then(has => {
        if (has) {
            warn.classList.remove('hidden');
            document.getElementById('schedulesCountText').textContent = 'This post has active schedules. Reassign or cancel them first.';
            btn.disabled = true;
        }
    });
}
async function executeDelete(id) {
    const btn = document.getElementById('confirmDeleteBtn');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    try {
        const r = await fetch(`${URL_BASE_POSTS}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const d = await parseJsonSafe(r);
        if (d.success) { showToast(d.message || 'Deleted.', 'success'); setTimeout(() => location.reload(), 800); }
        else { showToast(d.message, 'error'); btn.disabled = false; btn.innerHTML = orig; }
    } catch (err) { showToast(err.message, 'error'); btn.disabled = false; btn.innerHTML = orig; }
}
function showForceDeleteModal(id, name) {
    _forceDeleteId = id;
    document.getElementById('forceDeletePostName').textContent = name;
    document.getElementById('confirmForceDeleteBtn').onclick = () => executeForceDelete(id);
    openModal('forceDeleteModal');
}
async function executeForceDelete(id) {
    const btn = document.getElementById('confirmForceDeleteBtn');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    try {
        const r = await fetch(`${URL_BASE_TRASH}/${id}/force-delete`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const d = await parseJsonSafe(r);
        if (d.success) { showToast(d.message || 'Deleted.', 'success'); setTimeout(() => location.reload(), 800); }
        else { showToast(d.message, 'error'); btn.disabled = false; btn.innerHTML = orig; }
    } catch (err) { showToast(err.message, 'error'); btn.disabled = false; btn.innerHTML = orig; }
}
async function restorePost(id, name) {
    if (!confirm(`Restore "${name}" from trash?`)) return;
    try {
        const r = await fetch(`${URL_BASE_TRASH}/${id}/restore`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const d = await parseJsonSafe(r);
        if (d.success) { showToast(d.message || 'Restored.', 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(d.message, 'error');
    } catch (err) { showToast(err.message, 'error'); }
}

// ============================================================
// EXPORT MODAL
// ============================================================
function showExportModal() { openModal('exportModal'); }

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    if (!isHoS) return;
    loadQrCodeStats();
    const selAll = document.getElementById('selectAllCheckbox');
    if (selAll) selAll.addEventListener('change', function() {
        document.querySelectorAll('.post-checkbox').forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });
    document.querySelectorAll('.post-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));
    updateSelectedCount();
});

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        const m = e.target.closest('.modal');
        if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal:not(.hidden)').forEach(m => {
        m.classList.add('hidden'); document.body.style.overflow = 'auto';
    });
});
</script>
@endsection