@extends('layouts.app')

@section('title', 'Rotation Groups')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-rotate text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-rotate mr-2" style="color: var(--primary);"></i>
                        Rotation Groups
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage rotating teams, shift patterns, and member assignments</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $stats['total_members'] ?? 0 }} total members</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-history mr-1"></i>
                        <span>{{ $stats['rotations_today'] ?? 0 }} rotations today</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.rotation-history.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-2"></i> Analytics
                </a>
                <a href="{{ route('admin.rotation-groups.create') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Group
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_groups'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Groups</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active_groups'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Groups</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['total_members'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Members</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['avg_group_size'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Avg Group Size</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-chart-bar" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            @php
                $needingRotation = is_numeric($stats['groups_needing_rotation'] ?? null) ? $stats['groups_needing_rotation'] : 0;
                $isUrgent = $needingRotation > 0;
                $statusColor = $isUrgent ? 'warning' : 'success';
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--{{ $statusColor }});">
                        {{ $needingRotation }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Need Rotation</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                     style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle" style="color: var(--{{ $statusColor }});"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Groups
        </h3>
        
        <form method="GET" action="{{ route('admin.rotation-groups.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Post Filter -->
            <div>
                <label for="post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-1" style="color: var(--primary);"></i> Post
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
            
            <!-- Shift Filter -->
            <div>
                <label for="shift_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Shift
                </label>
                <select id="shift_id"
                        name="shift_id"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Shifts</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>
                            {{ $shift->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Type Filter -->
            <div>
                <label for="type" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Group Type
                </label>
                <select id="type"
                        name="type"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Types</option>
                    <option value="day" {{ request('type') == 'day' ? 'selected' : '' }}>Day</option>
                    <option value="night" {{ request('type') == 'night' ? 'selected' : '' }}>Night</option>
                    <option value="evening" {{ request('type') == 'evening' ? 'selected' : '' }}>Evening</option>
                    <option value="rotating" {{ request('type') == 'rotating' ? 'selected' : '' }}>Rotating</option>
                    <option value="standby" {{ request('type') == 'standby' ? 'selected' : '' }}>Standby</option>
                </select>
            </div>
            
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
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>
            
            <!-- Search -->
            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <div class="relative">
                    <input type="text"
                           id="search"
                           name="search"
                           class="index-custom-input w-full pl-10"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Name, code, description..."
                           value="{{ request('search') }}">
                    <i class="fas fa-search absolute left-3 top-3" style="color: var(--text-secondary);"></i>
                </div>
            </div>
            
            <!-- Filter Actions -->
            <div class="md:col-span-5 flex justify-end space-x-3 mt-4">
                <a href="{{ route('admin.rotation-groups.index') }}"
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

    <!-- Groups Table Card -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Rotation Groups
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $groups->total() }} total
                </span>
            </h3>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $stats['upcoming_rotations'] ?? 0 }} upcoming rotations
                </span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Group</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post & Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Type</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Members</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Last Rotation</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Next Rotation</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        @php
                            // Safely get rotation dates
                            $lastRotation = null;
                            if ($group->last_rotated_at) {
                                $lastRotation = $group->last_rotated_at;
                            } elseif (isset($group->rotation_config['last_rotation_date']) && !is_array($group->rotation_config['last_rotation_date'])) {
                                try {
                                    $lastRotation = \Carbon\Carbon::parse($group->rotation_config['last_rotation_date']);
                                } catch (\Exception $e) {
                                    $lastRotation = null;
                                }
                            }
                            
                            $nextRotation = null;
                            if (isset($group->rotation_config['next_rotation_date']) && !is_array($group->rotation_config['next_rotation_date'])) {
                                try {
                                    $nextRotation = \Carbon\Carbon::parse($group->rotation_config['next_rotation_date']);
                                } catch (\Exception $e) {
                                    $nextRotation = null;
                                }
                            }
                            
                            $memberCount = 0;
                            try {
                                $memberCount = $group->members()->count();
                            } catch (\Exception $e) {
                                $memberCount = 0;
                            }
                            
                            $daysUntilNext = null;
                            $needsRotation = false;
                            if ($nextRotation) {
                                $daysUntilNext = now()->diffInDays($nextRotation, false);
                                $needsRotation = $daysUntilNext !== null && $daysUntilNext <= 2;
                            }
                            
                            $groupType = $group->group_type ?? 'unknown';
                            $status = $group->status ?? 'inactive';
                            
                            $typeColors = [
                                'day' => 'success',
                                'night' => 'info',
                                'evening' => 'warning',
                                'rotating' => 'primary',
                                'standby' => 'secondary'
                            ];
                            $typeColor = $typeColors[$groupType] ?? 'secondary';
                            
                            $statusColors = [
                                'active' => 'success',
                                'inactive' => 'danger',
                                'draft' => 'info'
                            ];
                            $statusColor = $statusColors[$status] ?? 'secondary';
                            
                            // Get shift details safely
                            $shiftName = $group->shift->name ?? 'N/A';
                            $shiftIsOvernight = $group->shift->is_overnight ?? false;
                            $shiftStart = !empty($group->shift->start_time) ? substr($group->shift->start_time, 0, 5) : '';
                            $shiftEnd = !empty($group->shift->end_time) ? substr($group->shift->end_time, 0, 5) : '';
                            
                            // Get post name safely
                            $postName = $group->post->name ?? 'N/A';
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);"
                            onclick="window.location='{{ route('admin.rotation-groups.show', $group->id) }}'"
                            style="cursor: pointer;">
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">{{ $group->name }}</div>
                                        @if($group->code)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-barcode mr-1"></i> {{ $group->code }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $postName }}</div>
                                @if($group->shift)
                                    <div class="text-xs mt-1">
                                        @php
                                            $badgeClass = $shiftIsOvernight ? 'badge-info' : 'badge-secondary';
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full {{ $badgeClass }}">
                                            <i class="fas fa-clock mr-1"></i> {{ $shiftName }}
                                            @if($shiftIsOvernight) 🌙 @endif
                                        </span>
                                    </div>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @php
                                    $typeIcon = $groupType === 'rotating' ? 'sync-alt' : 'tag';
                                @endphp
                                <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $typeColor }}">
                                    <i class="fas fa-{{ $typeIcon }} mr-1"></i>
                                    {{ ucfirst($groupType) }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <span class="text-lg font-semibold mr-2" style="color: var(--text-primary);">{{ $memberCount }}</span>
                                    @if($group->max_members)
                                        <span class="text-xs" style="color: var(--text-secondary);">/ {{ $group->max_members }}</span>
                                    @endif
                                </div>
                                @if($group->min_members && $memberCount < $group->min_members)
                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Below minimum
                                    </div>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($lastRotation)
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $lastRotation->format('M j, Y') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $lastRotation->diffForHumans() }}</div>
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">Never rotated</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($nextRotation)
                                    @php
                                        $nextDateColor = $needsRotation ? 'var(--warning)' : 'var(--text-primary)';
                                    @endphp
                                    <div class="font-medium" style="color: {{ $nextDateColor }};">
                                        {{ $nextRotation->format('M j, Y') }}
                                        @if($needsRotation)
                                            <i class="fas fa-exclamation-circle ml-1" style="color: var(--warning);"></i>
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        @if($daysUntilNext !== null)
                                            @if($daysUntilNext < 0)
                                                Overdue by {{ abs($daysUntilNext) }} days
                                            @elseif($daysUntilNext == 0)
                                                Today
                                            @else
                                                In {{ $daysUntilNext }} days
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">Not scheduled</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $statusColor }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 6px;"></i>
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6" onclick="event.stopPropagation();">
                                <div class="flex space-x-2">
                                    <a href="{{ route('admin.rotation-groups.show', $group->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    
                                    <a href="{{ route('admin.rotation-groups.edit', $group->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                       title="Edit Group">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    
                                    @if($groupType === 'rotating')
                                    <button onclick="showRotateGroupModal({{ $group->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                            title="Execute Rotation">
                                        <i class="fas fa-sync-alt text-sm"></i>
                                    </button>
                                    @endif
                                    
                                    <a href="{{ route('admin.rotation-history.group', $group->id) }}"
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);"
                                       title="View History">
                                        <i class="fas fa-history text-sm"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-rotate text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Rotation Groups Found</h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">Get started by creating your first rotation group</p>
                                    <a href="{{ route('admin.rotation-groups.create') }}" 
                                       class="btn-primary px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center">
                                        <i class="fas fa-plus mr-2"></i> Create New Group
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if(method_exists($groups, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Rotation Modal -->
<div id="rotateGroupModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('rotateGroupModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i>
                    Execute Group Rotation
                </h3>
                <button type="button" onclick="closeModal('rotateGroupModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="rotateGroupForm" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="group_id" id="rotateGroupId">
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Rotation Type
                    </label>
                    <select name="rotation_type" class="index-custom-dropdown w-full" required>
                        <option value="full">Full Rotation (All Members)</option>
                        <option value="partial">Partial Rotation (50%)</option>
                        <option value="staggered">Staggered Rotation</option>
                        <option value="full_swap">Full Swap</option>
                    </select>
                </div>
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> Start Date
                    </label>
                    <input type="date" name="start_date" class="index-custom-input w-full" 
                           value="{{ now()->format('Y-m-d') }}" required>
                </div>
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-check mr-1" style="color: var(--primary);"></i> End Date
                    </label>
                    <input type="date" name="end_date" class="index-custom-input w-full" 
                           value="{{ now()->addDays(30)->format('Y-m-d') }}" required>
                </div>
                
                <div class="space-y-3 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color);">
                    <label class="flex items-center">
                        <input type="checkbox" name="apply_to_schedules" class="index-custom-checkbox mr-3" checked>
                        <span style="color: var(--text-primary);">Apply to existing schedules</span>
                    </label>
                    
                    <label class="flex items-center">
                        <input type="checkbox" name="maintain_coverage" class="index-custom-checkbox mr-3" checked>
                        <span style="color: var(--text-primary);">Maintain post coverage</span>
                    </label>
                    
                    <label class="flex items-center">
                        <input type="checkbox" name="respect_preferences" class="index-custom-checkbox mr-3" checked>
                        <span style="color: var(--text-primary);">Respect member preferences</span>
                    </label>
                    
                    <label class="flex items-center">
                        <input type="checkbox" name="notify_members" class="index-custom-checkbox mr-3" checked>
                        <span style="color: var(--text-primary);">Notify affected members</span>
                    </label>
                </div>
            </form>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('rotateGroupModal')">
                    Cancel
                </button>
                <button type="button" class="btn-info px-4 py-2 rounded-lg" onclick="previewGroupRotation()">
                    <i class="fas fa-eye mr-2"></i> Preview
                </button>
                <button type="button" class="btn-primary px-4 py-2 rounded-lg" onclick="executeGroupRotation()">
                    <i class="fas fa-play mr-2"></i> Execute
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="rotationPreviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('rotationPreviewModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-2" style="color: var(--info);"></i>
                    Rotation Preview
                </h3>
                <button type="button" onclick="closeModal('rotationPreviewModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6" id="rotationPreviewContent">
                <!-- Preview content will be loaded here -->
            </div>
            
            <div class="p-6 border-t flex justify-end" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('rotationPreviewModal')">
                    Close
                </button>
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
</style>

<script>
function showRotateGroupModal(groupId) {
    document.getElementById('rotateGroupId').value = groupId;
    document.getElementById('rotateGroupModal').classList.remove('hidden');
    document.getElementById('rotateGroupModal').classList.add('modal-show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('modal-show');
}

async function previewGroupRotation() {
    const form = document.getElementById('rotateGroupForm');
    const formData = new FormData(form);
    const groupId = formData.get('group_id');
    
    // Show loading state
    const previewBtn = event.target;
    const originalText = previewBtn.innerHTML;
    previewBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
    previewBtn.disabled = true;
    
    try {
        const response = await fetch(`/admin/rotation-groups/${groupId}/preview-rotation`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                rotation_type: formData.get('rotation_type'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date'),
                respect_preferences: formData.get('respect_preferences') === 'on'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayRotationPreview(data.preview);
            closeModal('rotateGroupModal');
            document.getElementById('rotationPreviewModal').classList.remove('hidden');
        } else {
            alert(data.message || 'Failed to generate preview');
        }
    } catch (error) {
        console.error('Preview error:', error);
        alert('Failed to generate preview. Please try again.');
    } finally {
        previewBtn.innerHTML = originalText;
        previewBtn.disabled = false;
    }
}

function displayRotationPreview(preview) {
    const container = document.getElementById('rotationPreviewContent');
    
    let html = `
        <div class="space-y-6">
            <div class="grid grid-cols-3 gap-4">
                <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">${preview.affected_count || 0}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Affected Members</div>
                </div>
                <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--info);">${preview.total_days || 0}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Rotation Days</div>
                </div>
                <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <div class="text-2xl font-bold" style="color: var(--warning);">${preview.unique_members_involved || 0}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Unique Members</div>
                </div>
            </div>
            
            <div>
                <h4 class="font-medium mb-3" style="color: var(--text-primary);">Rotation Schedule</h4>
                <div class="space-y-2 max-h-80 overflow-y-auto pr-2">
    `;
    
    if (preview.sequence && preview.sequence.length > 0) {
        preview.sequence.forEach(day => {
            html += `
                <div class="p-3 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium" style="color: var(--primary);">${day.date}</span>
                        <span class="px-2 py-1 text-xs rounded-full badge-info">
                            ${day.member_count || 0} members
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
            `;
            
            if (day.member_details && day.member_details.length > 0) {
                day.member_details.forEach(member => {
                    html += `
                        <span class="px-2 py-1 text-xs rounded-full" 
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            ${member.name}
                        </span>
                    `;
                });
            } else {
                html += `<span class="text-xs" style="color: var(--text-secondary);">No members assigned</span>`;
            }
            
            html += `
                    </div>
                </div>
            `;
        });
    } else {
        html += `<p class="text-center py-4" style="color: var(--text-secondary);">No rotation data available</p>`;
    }
    
    html += `
                </div>
            </div>
            
            <div class="text-xs p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                This is a preview only. Execute rotation to apply changes to schedules.
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

async function executeGroupRotation() {
    if (!confirm('Execute rotation for this group? This will update schedules and notify affected members.')) return;
    
    const form = document.getElementById('rotateGroupForm');
    const formData = new FormData(form);
    const groupId = formData.get('group_id');
    
    // Show loading state
    const executeBtn = event.target;
    const originalText = executeBtn.innerHTML;
    executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Executing...';
    executeBtn.disabled = true;
    
    try {
        const response = await fetch(`/admin/rotation-groups/${groupId}/execute-rotation`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                rotation_type: formData.get('rotation_type'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date'),
                apply_to_schedules: formData.get('apply_to_schedules') === 'on',
                maintain_coverage: formData.get('maintain_coverage') === 'on',
                respect_preferences: formData.get('respect_preferences') === 'on',
                notify_members: formData.get('notify_members') === 'on'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Rotation executed successfully!');
            closeModal('rotateGroupModal');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to execute rotation');
        }
    } catch (error) {
        console.error('Execution error:', error);
        alert('Failed to execute rotation. Please try again.');
    } finally {
        executeBtn.innerHTML = originalText;
        executeBtn.disabled = false;
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('rotateGroupModal');
        closeModal('rotationPreviewModal');
    }
}
</script>
@endsection