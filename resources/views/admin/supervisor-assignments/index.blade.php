@extends('layouts.app')

@section('title', 'Supervisor Assignments')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Security Supervisor Assignments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage supervisor assignments, permissions, and post oversight</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $stats['total_assignments'] ?? 0 }} total assignments</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-check-circle mr-1"></i>
                        <span>{{ $stats['active_assignments'] ?? 0 }} active</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.trash.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                    @php
                        try {
                            $trashCount = \App\Models\SecuritySupervisorAssignment::onlyTrashed()->count();
                        } catch (\Exception $e) {
                            $trashCount = 0;
                        }
                    @endphp
                    @if($trashCount > 0)
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full" style="background-color: var(--warning); color: white;">{{ $trashCount }}</span>
                    @endif
                </a>
                
                <a href="{{ route('admin.supervisor-assignments.export', request()->query()) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-download mr-2"></i> Export
                </a>
                
                <a href="{{ route('admin.supervisor-assignments.create') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Assignment
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Assignments</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-clipboard-list" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Assignments</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['expiring_soon'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Expiring Soon</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['expired_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Expired</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-hourglass-end" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            @php
                $eligibleCount = \App\Models\User::where('type', \App\Models\User::TYPE_SECURITY_PERSONNEL)
                    ->where('can_be_supervisor', true)
                    ->where('status', \App\Models\User::STATUS_ACTIVE)
                    ->count();
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--secondary);">{{ $eligibleCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Eligible Supervisors</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                    <i class="fas fa-user-check" style="color: var(--secondary);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Type Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        @foreach($supervisorTypes as $key => $label)
            @php
                $count = $stats['by_type'][$key] ?? 0;
                $typeColors = [
                    'post_supervisor' => 'primary',
                    'shift_supervisor' => 'info',
                    'area_supervisor' => 'success',
                    'relief_supervisor' => 'warning',
                    'training_supervisor' => 'secondary'
                ];
                $color = $typeColors[$key] ?? 'secondary';
            @endphp
            <div class="card p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ $label }}</span>
                    <span class="text-sm font-bold" style="color: var(--{{ $color }});">{{ $count }}</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full" 
                         style="width: {{ $stats['total_assignments'] > 0 ? ($count / $stats['total_assignments']) * 100 : 0 }}%; background-color: var(--{{ $color }});"></div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Assignments
        </h3>
        
        <form method="GET" action="{{ route('admin.supervisor-assignments.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <!-- Status Filter -->
            <div>
                <label for="status" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-power-off mr-1" style="color: var(--primary);"></i> Status
                </label>
                <select id="status"
                        name="status"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            
            <!-- Supervisor Type Filter -->
            <div>
                <label for="supervisor_type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Supervisor Type
                </label>
                <select id="supervisor_type"
                        name="supervisor_type"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Types</option>
                    @foreach($supervisorTypes as $key => $label)
                        <option value="{{ $key }}" {{ request('supervisor_type') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Post Filter -->
            <div>
                <label for="post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-1" style="color: var(--primary);"></i> Security Post
                </label>
                <select id="post_id"
                        name="post_id"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Posts</option>
                    @foreach($posts as $post)
                        <option value="{{ $post->id }}" {{ request('post_id') == $post->id ? 'selected' : '' }}>
                            {{ $post->name }} ({{ $post->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Supervisor Filter -->
            <div>
                <label for="user_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-user mr-1" style="color: var(--primary);"></i> Supervisor
                </label>
                <select id="user_id"
                        name="user_id"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Supervisors</option>
                    @foreach($supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" {{ request('user_id') == $supervisor->id ? 'selected' : '' }}>
                            {{ $supervisor->name }}
                            @if($supervisor->can_be_supervisor)
                                <span class="text-xs text-green-500">(Eligible)</span>
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Date Range - Start -->
            <div>
                <label for="start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> From Date
                </label>
                <input type="date"
                       id="start_date"
                       name="start_date"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('start_date') }}">
            </div>
            
            <!-- Date Range - End -->
            <div>
                <label for="end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-check mr-1" style="color: var(--primary);"></i> To Date
                </label>
                <input type="date"
                       id="end_date"
                       name="end_date"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('end_date') }}">
            </div>
            
            <!-- Search - Full Width on next line -->
            <div class="md:col-span-4">
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <div class="relative">
                    <input type="text"
                           id="search"
                           name="search"
                           class="index-custom-input w-full pl-10"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Search by supervisor name, email, or post name..."
                           value="{{ request('search') }}">
                    <i class="fas fa-search absolute left-3 top-3" style="color: var(--text-secondary);"></i>
                </div>
            </div>
            
            <!-- Sort Options -->
            <div>
                <label for="sort_by" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-sort mr-1" style="color: var(--primary);"></i> Sort By
                </label>
                <select id="sort_by"
                        name="sort_by"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="created_at" {{ request('sort_by', 'created_at') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                    <option value="start_date" {{ request('sort_by') == 'start_date' ? 'selected' : '' }}>Start Date</option>
                    <option value="end_date" {{ request('sort_by') == 'end_date' ? 'selected' : '' }}>End Date</option>
                    <option value="supervisor_type" {{ request('sort_by') == 'supervisor_type' ? 'selected' : '' }}>Supervisor Type</option>
                </select>
            </div>
            
            <!-- Sort Order -->
            <div>
                <label for="sort_order" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-arrow-up-wide-short mr-1" style="color: var(--primary);"></i> Order
                </label>
                <select id="sort_order"
                        name="sort_order"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="desc" {{ request('sort_order', 'desc') == 'desc' ? 'selected' : '' }}>Descending</option>
                    <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Ascending</option>
                </select>
            </div>
            
            <!-- Filter Actions -->
            <div class="md:col-span-6 flex justify-end space-x-3 mt-4">
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Assignments Table Card -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Supervisor Assignments
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $assignments->total() }} total
                </span>
            </h3>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-week mr-1"></i> 
                    {{ now()->format('F j, Y') }}
                </span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Supervisor</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post & Type</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assignment Period</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Coverage</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Permissions</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
    @forelse($assignments as $assignment)
        @php
            $isActive = $assignment->is_current;
            $isExpiringSoon = method_exists($assignment, 'isExpiringSoon') ? $assignment->isExpiringSoon() : false;
            $isExpired = $assignment->isExpired ?: ($assignment->end_date && $assignment->end_date < now());
            
            $statusColor = 'secondary';
            $statusText = 'Unknown';
            
            if ($isActive) {
                if ($isExpiringSoon) {
                    $statusColor = 'warning';
                    $statusText = 'Expiring Soon';
                } else {
                    $statusColor = 'success';
                    $statusText = 'Active';
                }
            } elseif ($isExpired) {
                $statusColor = 'danger';
                $statusText = 'Expired';
            } elseif (!$assignment->is_active) {
                $statusColor = 'secondary';
                $statusText = 'Inactive';
            }
            
            $typeColors = [
                'post_supervisor' => 'primary',
                'shift_supervisor' => 'info',
                'area_supervisor' => 'success',
                'relief_supervisor' => 'warning',
                'training_supervisor' => 'secondary'
            ];
            $typeColor = $typeColors[$assignment->supervisor_type] ?? 'secondary';
            
            $daysRemaining = null;
            if ($assignment->end_date && !$isExpired) {
                $daysRemaining = now()->diffInDays($assignment->end_date, false);
            }
            
            // Safe permission count
            $permissionCount = 0;
            $totalPermissions = 10;
            
            if (method_exists($assignment, 'getPermissions')) {
                $permissions = $assignment->getPermissions();
                $permissionCount = collect($permissions)->filter()->count();
                $totalPermissions = count($permissions);
            } else {
                $permissionFields = [
                    'can_override_checkins', 'can_approve_swaps', 'can_approve_overtime',
                    'can_review_incidents', 'can_verify_checkins', 'can_request_backup',
                    'can_approve_breaks', 'can_escalate_issues', 'can_view_all_schedules',
                    'can_edit_schedules'
                ];
                foreach ($permissionFields as $field) {
                    if ($assignment->$field) $permissionCount++;
                }
            }
            
            $coverageInfo = [];
            if (method_exists($assignment, 'getCoverageDescription')) {
                $coverageInfo = $assignment->getCoverageDescription();
            } else {
                $coverageInfo = [
                    'shifts' => $assignment->shift_ids ? 'Multiple shifts' : 'All shifts',
                    'days' => $assignment->applicable_days ? 'Selected days' : 'All days'
                ];
            }
            
            // ✅ SAFE: Check if user exists and is eligible
            $user = $assignment->user;
            $isEligible = $user && $user->can_be_supervisor ?? false;
            $userName = optional($user)->name ?? 'Deleted User';
            $userEmail = optional($user)->email ?? 'No email';
            
            // ✅ SAFE: Check if post exists
            $post = $assignment->post;
            $postName = optional($post)->name ?? 'Deleted Post';
            $postCode = optional($post)->code ?? 'N/A';
            $hasPost = !is_null($post);
            
            // ✅ SAFE: Check if assigned by exists
            $assignedBy = $assignment->assignedBy;
            $assignedByName = optional($assignedBy)->name ?? 'System';
        @endphp
        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
            style="border-color: var(--border-color); background-color: var(--card-bg);"
            onclick="window.location='{{ route('admin.supervisor-assignments.show', $assignment->id) }}'"
            style="cursor: pointer;">
            
            <td class="py-4 px-6">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                            {{ $userName }}
                            @if($isEligible && $user)
                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Eligible for supervisor role">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            @endif
                            @if(!$user)
                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="User deleted">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-envelope mr-1"></i> {{ $userEmail }}
                            @if($user)
                                <span class="mx-2">•</span>
                                <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: {{ $isEligible ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $isEligible ? 'var(--success)' : 'var(--warning)' }};">
                                    {{ $isEligible ? 'Eligible' : 'Not Eligible' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            
            <td class="py-4 px-6">
                @if($hasPost && $post)
                    <div class="font-medium" style="color: var(--text-primary);">{{ $postName }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-code mr-1"></i> {{ $postCode }}
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
                @if($assignment->metadata['assignment_type'] ?? false)
                    <div class="mt-1">
                        <span class="px-2 py-0.5 text-xs rounded-full badge-info">
                            <i class="fas fa-{{ $assignment->metadata['assignment_type'] === 'role_only' ? 'user-tag' : 'building' }} mr-1"></i>
                            {{ $assignment->metadata['assignment_type'] === 'role_only' ? 'Role Only' : 'Post Assigned' }}
                        </span>
                    </div>
                @endif
            </td>
            
            <td class="py-4 px-6">
                <div class="flex flex-col">
                    <div class="flex items-center">
                        <i class="fas fa-play-circle text-xs mr-1" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $assignment->start_date instanceof \Carbon\Carbon ? $assignment->start_date->format('M j, Y') : \Carbon\Carbon::parse($assignment->start_date)->format('M j, Y') }}</span>
                    </div>
                    <div class="flex items-center mt-1">
                        <i class="fas fa-stop-circle text-xs mr-1" style="color: {{ $assignment->end_date ? 'var(--warning)' : 'var(--info)' }};"></i>
                        <span class="text-sm" style="color: var(--text-primary);">
                            {{ $assignment->end_date ? (\Carbon\Carbon::parse($assignment->end_date)->format('M j, Y')) : 'Indefinite' }}
                        </span>
                    </div>
                    @if($daysRemaining !== null && $daysRemaining > 0)
                        <div class="text-xs mt-1" style="color: var(--info);">
                            <i class="fas fa-hourglass-half mr-1"></i> {{ $daysRemaining }} days remaining
                        </div>
                    @elseif($daysRemaining !== null && $daysRemaining < 0)
                        <div class="text-xs mt-1" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> Expired {{ abs($daysRemaining) }} days ago
                        </div>
                    @endif
                </div>
            </td>
            
            <td class="py-4 px-6">
                <div class="space-y-2">
                    @if($assignment->shift_ids)
                        <div class="text-xs">
                            <span class="font-medium" style="color: var(--text-primary);">Shifts:</span>
                            <span class="ml-1" style="color: var(--text-secondary);">{{ $coverageInfo['shifts'] ?? 'Multiple shifts' }}</span>
                        </div>
                    @endif
                    
                    @if($assignment->applicable_days)
                        <div class="text-xs">
                            <span class="font-medium" style="color: var(--text-primary);">Days:</span>
                            <span class="ml-1" style="color: var(--text-secondary);">{{ $coverageInfo['days'] ?? 'All days' }}</span>
                        </div>
                    @endif
                    
                    @if($assignment->is_primary_supervisor)
                        <span class="px-2 py-0.5 text-xs rounded-full badge-primary">
                            <i class="fas fa-star mr-1"></i> Primary Supervisor
                        </span>
                    @endif
                </div>
            </td>
            
            <td class="py-4 px-6">
                <div class="flex flex-col space-y-1">
                    <div class="flex items-center">
                        <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                            <div class="h-1.5 rounded-full" 
                                 style="width: {{ $totalPermissions > 0 ? ($permissionCount / $totalPermissions) * 100 : 0 }}%; background-color: var(--primary);"></div>
                        </div>
                        <span class="text-xs font-medium" style="color: var(--text-primary);">{{ $permissionCount }}/{{ $totalPermissions }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @if($assignment->can_verify_checkins)
                            <span class="px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Can verify check-ins">
                                <i class="fas fa-check-circle"></i>
                            </span>
                        @endif
                        @if($assignment->can_approve_swaps)
                            <span class="px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="Can approve swaps">
                                <i class="fas fa-exchange-alt"></i>
                            </span>
                        @endif
                        @if($assignment->can_approve_overtime)
                            <span class="px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Can approve overtime">
                                <i class="fas fa-clock"></i>
                            </span>
                        @endif
                        @if($assignment->can_review_incidents)
                            <span class="px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" title="Can review incidents">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                        @endif
                        @if($assignment->can_edit_schedules)
                            <span class="px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Can edit schedules">
                                <i class="fas fa-pen"></i>
                            </span>
                        @endif
                        @if($permissionCount > 5)
                            <span class="text-xs" style="color: var(--text-secondary);">+{{ $permissionCount - 5 }} more</span>
                        @endif
                    </div>
                </div>
            </td>
            
            <td class="py-4 px-6">
                <div class="flex flex-col space-y-2">
                    <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $statusColor }}">
                        <i class="fas fa-circle mr-1" style="font-size: 6px;"></i>
                        {{ $statusText }}
                    </span>
                    <div class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-user-check mr-1"></i> by {{ $assignedByName }}
                    </div>
                </div>
            </td>
            
            <td class="py-4 px-6" onclick="event.stopPropagation();">
                <div class="flex space-x-2">
                    <a href="{{ route('admin.supervisor-assignments.show', $assignment->id) }}" 
                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                       title="View Details">
                        <i class="fas fa-eye text-sm"></i>
                    </a>
                    
                    <a href="{{ route('admin.supervisor-assignments.edit', $assignment->id) }}" 
                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                       title="Edit Assignment">
                        <i class="fas fa-edit text-sm"></i>
                    </a>
                    
                    @if($isActive && !$isExpired)
                        <button onclick="showExtendModal({{ $assignment->id }}, '{{ addslashes($userName) }}', '{{ $assignment->end_date ? $assignment->end_date->format('Y-m-d') : '' }}')"
                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                title="Extend Assignment">
                            <i class="fas fa-calendar-plus text-sm"></i>
                        </button>
                        
                        <button onclick="showTerminateModal({{ $assignment->id }}, '{{ addslashes($userName) }}')"
                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                title="Terminate Early">
                            <i class="fas fa-ban text-sm"></i>
                        </button>
                    @endif
                    
                    <button onclick="toggleActive({{ $assignment->id }}, {{ $assignment->is_active ? 'true' : 'false' }})"
                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                            style="background-color: rgba(var(--{{ $assignment->is_active ? 'danger' : 'success' }}-rgb), 0.1); color: var(--{{ $assignment->is_active ? 'danger' : 'success' }});"
                            title="{{ $assignment->is_active ? 'Deactivate' : 'Activate' }}">
                        <i class="fas fa-{{ $assignment->is_active ? 'pause' : 'play' }} text-sm"></i>
                    </button>
                    
                    <button onclick="confirmDelete({{ $assignment->id }}, '{{ addslashes($userName) }}')"
                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                            title="Delete Assignment">
                        <i class="fas fa-trash text-sm"></i>
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
                        <i class="fas fa-user-shield text-3xl" style="color: var(--text-secondary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Supervisor Assignments Found</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        {{ request()->anyFilled(['status', 'supervisor_type', 'post_id', 'user_id', 'start_date', 'end_date', 'search']) 
                            ? 'Try adjusting your filters' 
                            : 'Get started by creating your first supervisor assignment' }}
                    </p>
                    @if(request()->anyFilled(['status', 'supervisor_type', 'post_id', 'user_id', 'start_date', 'end_date', 'search']))
                        <a href="{{ route('admin.supervisor-assignments.index') }}" 
                           class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                            <i class="fas fa-times mr-2"></i> Clear Filters
                        </a>
                    @else
                        <a href="{{ route('admin.supervisor-assignments.create') }}" 
                           class="btn-primary px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Create New Assignment
                        </a>
                    @endif
                </div>
            </td>
        </tr>
    @endforelse
</tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if(method_exists($assignments, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $assignments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Extend Assignment Modal -->
<div id="extendModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('extendModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-plus mr-2" style="color: var(--primary);"></i>
                    Extend Assignment
                </h3>
                <button type="button" onclick="closeModal('extendModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="extendForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span style="color: var(--text-primary);" id="extendSupervisorName"></span>
                        </div>
                        <div class="mt-2 text-sm" style="color: var(--text-secondary);" id="currentEndDate"></div>
                    </div>
                    
                    <div>
                        <label for="new_end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> New End Date
                        </label>
                        <input type="date" 
                               name="new_end_date" 
                               id="new_end_date"
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label for="reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-comment mr-1" style="color: var(--primary);"></i> Reason for Extension
                        </label>
                        <textarea name="reason" 
                                  id="reason"
                                  rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Please provide a reason for extending this assignment..."></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('extendModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg">
                        <i class="fas fa-calendar-plus mr-2"></i> Extend Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate Modal -->
<div id="terminateModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('terminateModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-ban mr-2" style="color: var(--danger);"></i>
                    Terminate Assignment
                </h3>
                <button type="button" onclick="closeModal('terminateModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="terminateForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            <span style="color: var(--text-primary);" id="terminateSupervisorName"></span>
                        </div>
                        <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                            This will end the assignment immediately. This action can be reversed by creating a new assignment.
                        </p>
                    </div>
                    
                    <div>
                        <label for="terminate_reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-comment mr-1" style="color: var(--primary);"></i> Reason for Termination
                        </label>
                        <textarea name="reason" 
                                  id="terminate_reason"
                                  rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Please provide a reason for terminating this assignment..."
                                  required></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('terminateModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-ban mr-2"></i> Terminate Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('deleteModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash mr-2" style="color: var(--danger);"></i>
                    Delete Assignment
                </h3>
                <button type="button" onclick="closeModal('deleteModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p style="color: var(--text-primary);" id="deleteSupervisorName"></p>
                <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                    Are you sure you want to delete this assignment? This will move it to the trash.
                </p>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('deleteModal')">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" action="" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-trash mr-2"></i> Move to Trash
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
    cursor: pointer;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
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

/* Progress bar */
.progress-bar {
    transition: width 0.3s ease;
}

/* Custom scrollbar for dropdowns */
.index-custom-dropdown, .index-custom-input {
    transition: all 0.2s ease;
}

.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
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

.btn-info {
    background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--info-rgb), 0.3);
}
</style>

<script>
// Modal functions
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('modal-show');
}

function showExtendModal(assignmentId, supervisorName, currentEndDate) {
    document.getElementById('extendSupervisorName').innerHTML = `Extending assignment for <strong>${supervisorName}</strong>`;
    document.getElementById('currentEndDate').innerHTML = `Current end date: <strong>${currentEndDate || 'Indefinite'}</strong>`;
    document.getElementById('extendForm').action = `/admin/supervisor-assignments/${assignmentId}/extend`;
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('new_end_date').min = tomorrowStr;
    
    if (currentEndDate) {
        document.getElementById('new_end_date').value = currentEndDate;
    }
    
    document.getElementById('extendModal').classList.remove('hidden');
    document.getElementById('extendModal').classList.add('modal-show');
}

function showTerminateModal(assignmentId, supervisorName) {
    document.getElementById('terminateSupervisorName').innerHTML = `Terminating assignment for <strong>${supervisorName}</strong>`;
    document.getElementById('terminateForm').action = `/admin/supervisor-assignments/${assignmentId}/terminate`;
    
    document.getElementById('terminateModal').classList.remove('hidden');
    document.getElementById('terminateModal').classList.add('modal-show');
}

function confirmDelete(assignmentId, supervisorName) {
    document.getElementById('deleteSupervisorName').innerHTML = `Delete assignment for <strong>${supervisorName}</strong>`;
    document.getElementById('deleteForm').action = `/admin/supervisor-assignments/${assignmentId}`;
    
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('modal-show');
}

async function toggleActive(assignmentId, isActive) {
    const action = isActive ? 'deactivate' : 'activate';
    
    if (!confirm(`Are you sure you want to ${action} this assignment?`)) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/supervisor-assignments/${assignmentId}/toggle-active`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || `Failed to ${action} assignment`);
        }
    } catch (error) {
        console.error('Toggle error:', error);
        alert(`Failed to ${action} assignment. Please try again.`);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('extendModal');
        closeModal('terminateModal');
        closeModal('deleteModal');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    
    const startDate = document.getElementById('start_date');
    if (startDate && !startDate.value) {
        // Optional: set default date range
    }
    
    const endDate = document.getElementById('end_date');
    if (endDate && !endDate.value) {
        // endDate.value = today;
    }
});
</script>
@endsection