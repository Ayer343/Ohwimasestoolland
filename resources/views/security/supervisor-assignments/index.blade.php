@extends('layouts.secu')

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
                        Supervisor Assignments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage supervisor assignments within your area</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $stats['total_assignments'] ?? 0 }} total assignments</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-check-circle mr-1"></i>
                        <span>{{ $stats['active_assignments'] ?? 0 }} active</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor-assignments.export', request()->query()) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-download mr-2"></i> Export
                </a>
                <a href="{{ route('security.supervisor-assignments.create') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Assignment
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
    </div>

    <!-- Type Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($supervisorTypes as $key => $label)
            @php
                $count = $stats['by_type'][$key] ?? 0;
                $typeColors = [
                    'post_supervisor' => 'primary',
                    'shift_supervisor' => 'info',
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
        
        <form method="GET" action="{{ route('security.supervisor-assignments.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                    @foreach($assignableSupervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" {{ request('user_id') == $supervisor->id ? 'selected' : '' }}>
                            {{ $supervisor->name }} 
                            @if($supervisor->can_be_supervisor)
                                <span class="text-xs text-green-500">(Eligible)</span>
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Search -->
            <div class="md:col-span-2">
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
            
            <!-- Filter Actions -->
            <div class="md:col-span-5 flex justify-end space-x-3 mt-4">
                <a href="{{ route('security.supervisor-assignments.index') }}"
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
                                'relief_supervisor' => 'warning',
                                'training_supervisor' => 'secondary'
                            ];
                            $typeColor = $typeColors[$assignment->supervisor_type] ?? 'secondary';
                            
                            $daysRemaining = null;
                            if ($assignment->end_date && !$isExpired) {
                                $daysRemaining = now()->diffInDays($assignment->end_date, false);
                            }
                            
                            $canEdit = $assignment->supervisor_type !== 'area_supervisor';
                            
                            // ✅ SAFE: Get user with null check
                            $user = $assignment->user;
                            $userName = optional($user)->name ?? 'Deleted User';
                            $userEmail = optional($user)->email ?? 'No email';
                            $isEligible = $user && $user->can_be_supervisor ?? false;
                            
                            // ✅ SAFE: Get post with null check
                            $post = $assignment->post;
                            $hasPost = !is_null($post);
                            $postName = optional($post)->name ?? 'Unknown Post';
                            $postCode = optional($post)->code ?? 'N/A';
                            
                            // ✅ SAFE: Get assigned by
                            $assignedBy = $assignment->assignedBy;
                            $assignedByName = optional($assignedBy)->name ?? 'System';
                            
                            // ✅ Check assignment type from metadata
                            $isRoleOnly = ($assignment->metadata['assignment_type'] ?? '') === 'role_only' || !$hasPost;
                            $assignmentType = $isRoleOnly ? 'Role Only' : 'Post Specific';
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);"
                            onclick="window.location='{{ route('security.supervisor-assignments.show', $assignment->id) }}'"
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
                                            @if($isEligible)
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
                                    <div class="font-medium" style="color: var(--text-primary);">Role Only</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">No post assigned</div>
                                @endif
                                <div class="mt-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-{{ $typeColor }}">
                                        <i class="fas fa-{{ $assignment->supervisor_type === 'post_supervisor' ? 'flag' : ($assignment->supervisor_type === 'shift_supervisor' ? 'clock' : 'user-tag') }} mr-1"></i>
                                        {{ $supervisorTypes[$assignment->supervisor_type] ?? ucfirst(str_replace('_', ' ', $assignment->supervisor_type)) }}
                                    </span>
                                </div>
                                <div class="mt-1">
                                    <span class="px-2 py-0.5 text-xs rounded-full badge-info">
                                        <i class="fas fa-{{ $isRoleOnly ? 'user-tag' : 'building' }} mr-1"></i>
                                        {{ $assignmentType }}
                                    </span>
                                </div>
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
                                    @if($assignment->is_primary_supervisor)
                                        <span class="px-2 py-0.5 text-xs rounded-full badge-primary">
                                            <i class="fas fa-star mr-1"></i> Primary Supervisor
                                        </span>
                                    @endif
                                    @if($assignment->shift_ids)
                                        <div class="text-xs">
                                            <span class="font-medium" style="color: var(--text-primary);">Shifts:</span>
                                            <span class="ml-1" style="color: var(--text-secondary);">Multiple shifts</span>
                                        </div>
                                    @endif
                                    @if($assignment->applicable_days)
                                        <div class="text-xs">
                                            <span class="font-medium" style="color: var(--text-primary);">Days:</span>
                                            <span class="ml-1" style="color: var(--text-secondary);">Selected days</span>
                                        </div>
                                    @endif
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
                                    <a href="{{ route('security.supervisor-assignments.show', $assignment->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    
                                    @if($canEdit)
                                        <a href="{{ route('security.supervisor-assignments.edit', $assignment->id) }}" 
                                           class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                           title="Edit Assignment">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                    @endif
                                    
                                    @if($isActive && !$isExpired && $canEdit)
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
                                    
                                    @if($canEdit)
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
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Supervisor Assignments Found</h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        {{ request()->anyFilled(['status', 'supervisor_type', 'post_id', 'user_id', 'search']) 
                                            ? 'Try adjusting your filters' 
                                            : 'Get started by creating your first supervisor assignment' }}
                                    </p>
                                    @if(request()->anyFilled(['status', 'supervisor_type', 'post_id', 'user_id', 'search']))
                                        <a href="{{ route('security.supervisor-assignments.index') }}" 
                                           class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                                            <i class="fas fa-times mr-2"></i> Clear Filters
                                        </a>
                                    @else
                                        <a href="{{ route('security.supervisor-assignments.create') }}" 
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

<!-- Extend Modal -->
<div id="extendModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Extend Assignment</h3>
            </div>
            <form id="extendForm" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">New End Date</label>
                        <input type="date" name="new_end_date" id="extend_end_date" 
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reason</label>
                        <textarea name="reason" rows="3" 
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for extending..."></textarea>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeExtendModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg">Extend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate Modal -->
<div id="terminateModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Terminate Assignment</h3>
            </div>
            <form id="terminateForm" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reason</label>
                        <textarea name="reason" rows="3" 
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for termination..."
                                  required></textarea>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeTerminateModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">Terminate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Delete Assignment</h3>
            </div>
            <div class="p-6">
                <p id="deleteSupervisorName" style="color: var(--text-primary);"></p>
                <p class="mt-2 text-sm" style="color: var(--text-secondary);">This will move the assignment to trash.</p>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <form id="deleteForm" method="POST" action="" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
    cursor: pointer;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}
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
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
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

<script>
let currentAssignmentId = null;

function closeExtendModal() {
    document.getElementById('extendModal').classList.add('hidden');
}

function closeTerminateModal() {
    document.getElementById('terminateModal').classList.add('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function showExtendModal(id, name, endDate) {
    currentAssignmentId = id;
    document.getElementById('extendForm').action = `/security/supervisor-assignments/${id}/extend`;
    document.getElementById('extend_end_date').value = endDate || '';
    document.getElementById('extendModal').classList.remove('hidden');
}

function showTerminateModal(id, name) {
    currentAssignmentId = id;
    document.getElementById('terminateForm').action = `/security/supervisor-assignments/${id}/terminate`;
    document.getElementById('terminateModal').classList.remove('hidden');
}

function confirmDelete(id, name) {
    currentAssignmentId = id;
    document.getElementById('deleteSupervisorName').innerHTML = `Delete assignment for <strong>${name}</strong>`;
    document.getElementById('deleteForm').action = `/security/supervisor-assignments/${id}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

async function toggleActive(id, isActive) {
    const action = isActive ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} this assignment?`)) return;
    
    try {
        const response = await fetch(`/security/supervisor-assignments/${id}/toggle-active`, {
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

window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeExtendModal();
        closeTerminateModal();
        closeDeleteModal();
    }
}
</script>
@endsection