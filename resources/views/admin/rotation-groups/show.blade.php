@extends('layouts.app')

@section('title', 'Rotation Group: ' . $group->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card with Group Info -->
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
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $group->name }}</h2>
                        @if($group->code)
                            <span class="px-2 py-1 text-xs rounded-full badge-secondary">
                                <i class="fas fa-barcode mr-1"></i> {{ $group->code }}
                            </span>
                        @endif
                        <span class="px-3 py-1 text-sm rounded-full badge-{{ $group->status === 'active' ? 'success' : ($group->status === 'inactive' ? 'danger' : 'info') }}">
                            <i class="fas fa-circle mr-1" style="font-size: 6px;"></i>
                            {{ ucfirst($group->status) }}
                        </span>
                    </div>
                    <div class="text-sm flex items-center flex-wrap gap-3" style="color: var(--text-secondary);">
                        <span><i class="fas fa-calendar-alt mr-1"></i> Created: {{ $group->created_at->format('M j, Y') }}</span>
                        <span>•</span>
                        <span><i class="fas fa-user mr-1"></i> By: {{ $group->createdBy?->name ?? 'System' }}</span>
                        @if($group->last_rotated_at)
                            <span>•</span>
                            <span><i class="fas fa-history mr-1"></i> Last Rotation: {{ $group->last_rotated_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <a href="{{ route('admin.rotation-groups.edit', $group->id) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-warning">
                    <i class="fas fa-edit mr-2"></i> Edit Group
                </a>
                @if($group->group_type === 'rotating' && $readiness['ready'] ?? false)
                    <button onclick="showRotateGroupModal({{ $group->id }})"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                        <i class="fas fa-sync-alt mr-2"></i> Execute Rotation
                    </button>
                @endif
            </div>
        </div>
        
        <!-- Description if exists -->
        @if($group->description)
            <div class="px-6 pb-6 border-t pt-4" style="border-color: var(--border-color);">
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">{{ $group->description }}</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Members Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Total Members</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <div class="text-3xl font-bold" style="color: var(--text-primary);">{{ $group->members_count ?? $group->members->count() }}</div>
                    @if($group->max_members)
                        <div class="text-xs" style="color: var(--text-secondary);">Max capacity: {{ $group->max_members }}</div>
                    @endif
                </div>
                @if($group->min_members && ($group->members_count ?? $group->members->count()) < $group->min_members)
                    <span class="px-2 py-1 text-xs rounded-full badge-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Below minimum
                    </span>
                @endif
            </div>
        </div>

        <!-- Post & Shift Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Post & Shift</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-building" style="color: var(--info);"></i>
                </div>
            </div>
            <div>
                <div class="font-medium" style="color: var(--text-primary);">{{ $group->post->name ?? 'N/A' }}</div>
                @if($group->shift)
                    <div class="text-sm mt-1">
                        <span class="px-2 py-0.5 rounded-full {{ $group->shift->is_overnight ? 'badge-info' : 'badge-secondary' }}">
                            <i class="fas fa-clock mr-1"></i> {{ $group->shift->name }}
                            @if($group->shift->is_overnight) 🌙 @endif
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Rotation Info Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Rotation Schedule</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-calendar-alt" style="color: var(--warning);"></i>
                </div>
            </div>
            <div>
                @if($group->group_type === 'rotating')
                    <div class="font-medium" style="color: var(--text-primary);">
                        Every {{ $group->rotation_config['interval_days'] ?? '?' }} days
                    </div>
                    <div class="text-xs mt-1 flex items-center gap-2">
                        @if($group->auto_rotate)
                            <span class="badge-success px-2 py-0.5 rounded-full text-xs">
                                <i class="fas fa-check-circle mr-1"></i> Auto
                            </span>
                            <span style="color: var(--text-secondary);">{{ ucfirst($group->auto_rotate_schedule) }}</span>
                        @else
                            <span class="badge-secondary px-2 py-0.5 rounded-full text-xs">Manual</span>
                        @endif
                    </div>
                @else
                    <div class="font-medium" style="color: var(--text-primary);">{{ ucfirst($group->group_type) }} Shift</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Fixed (non-rotating)</div>
                @endif
            </div>
        </div>

        <!-- Next Rotation Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Next Rotation</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-forward" style="color: var(--success);"></i>
                </div>
            </div>
            <div>
                @php
                    $nextRotation = $readiness['next_rotation'] ?? null;
                    $daysUntil = $readiness['days_until'] ?? null;
                    $isDue = $readiness['is_due'] ?? false;
                @endphp
                
                @if($nextRotation)
                    <div class="font-medium" style="color: {{ $isDue ? 'var(--warning)' : 'var(--text-primary)' }};">
                        {{ Carbon\Carbon::parse($nextRotation)->format('M j, Y') }}
                        @if($isDue)
                            <i class="fas fa-exclamation-circle ml-1" style="color: var(--warning);"></i>
                        @endif
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        @if($daysUntil < 0)
                            Overdue by {{ abs($daysUntil) }} days
                        @elseif($daysUntil == 0)
                            Today
                        @else
                            In {{ $daysUntil }} days
                        @endif
                    </div>
                @else
                    <div class="font-medium" style="color: var(--text-secondary);">Not scheduled</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Group Details & Settings -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Group Type Card -->
            <div class="card">
                <div class="p-4 border-b" style="border-color: var(--border-color);">
                    <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-2" style="color: var(--primary);"></i>
                        Group Configuration
                    </h3>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-secondary);">Group Type:</span>
                        @php
                            $typeColors = [
                                'day' => 'success',
                                'night' => 'info',
                                'evening' => 'warning',
                                'rotating' => 'primary',
                                'standby' => 'secondary'
                            ];
                            $typeColor = $typeColors[$group->group_type] ?? 'secondary';
                            $typeIcon = $group->group_type === 'rotating' ? 'sync-alt' : 'clock';
                        @endphp
                        <span class="px-3 py-1 text-sm rounded-full badge-{{ $typeColor }}">
                            <i class="fas fa-{{ $typeIcon }} mr-1"></i>
                            {{ ucfirst($group->group_type) }}
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-secondary);">Auto Rotation:</span>
                        @if($group->auto_rotate)
                            <span class="badge-success px-3 py-1 text-sm rounded-full">
                                <i class="fas fa-check mr-1"></i> Enabled
                            </span>
                        @else
                            <span class="badge-secondary px-3 py-1 text-sm rounded-full">
                                <i class="fas fa-times mr-1"></i> Disabled
                            </span>
                        @endif
                    </div>
                    
                    @if($group->auto_rotate)
                        <div class="flex items-center justify-between">
                            <span style="color: var(--text-secondary);">Schedule:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ ucfirst($group->auto_rotate_schedule) }}
                                @if($group->auto_rotate_time)
                                    at {{ substr($group->auto_rotate_time, 0, 5) }}
                                @endif
                            </span>
                        </div>
                    @endif
                    
                    <div class="flex items-center justify-between">
                        <span style="color: var(--text-secondary);">Member Limits:</span>
                        <span style="color: var(--text-primary);">
                            @if($group->min_members && $group->max_members)
                                {{ $group->min_members }} - {{ $group->max_members }}
                            @elseif($group->min_members)
                                Min: {{ $group->min_members }}
                            @elseif($group->max_members)
                                Max: {{ $group->max_members }}
                            @else
                                No limits
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Rotation Configuration Card (for rotating groups) -->
            @if($group->group_type === 'rotating' && $group->rotation_config)
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                            Rotation Settings
                        </h3>
                    </div>
                    <div class="p-4 space-y-4">
                        @php
                            $config = $group->rotation_config;
                        @endphp
                        
                        <div class="flex items-center justify-between">
                            <span style="color: var(--text-secondary);">Pattern Type:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ ucfirst(str_replace('_', ' ', $config['type'] ?? 'sequential')) }}
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span style="color: var(--text-secondary);">Interval:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                Every {{ $config['interval_days'] ?? 7 }} days
                            </span>
                        </div>
                        
                        @if(!empty($config['sequence']))
                            <div>
                                <span style="color: var(--text-secondary);">Sequence:</span>
                                <div class="flex gap-2 mt-2">
                                    @foreach($config['sequence'] as $seq)
                                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium"
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            {{ $seq }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        @if(!empty($config['rotation_stats']))
                            <div class="pt-2 border-t" style="border-color: var(--border-color);">
                                <h4 class="text-sm font-medium mb-2" style="color: var(--text-primary);">Statistics</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.05);">
                                        <div class="text-lg font-bold" style="color: var(--info);">{{ $config['rotation_stats']['total_rotations'] ?? 0 }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Total</div>
                                    </div>
                                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05);">
                                        <div class="text-lg font-bold" style="color: var(--success);">{{ $config['rotation_stats']['successful_rotations'] ?? 0 }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Success</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Preference Weights Card -->
            @if($group->preference_weights)
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-weight-hanging mr-2" style="color: var(--primary);"></i>
                            Preference Weights
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        @php
                            $weights = $group->preference_weights;
                            $totalWeight = array_sum($weights);
                        @endphp
                        
                        @foreach($weights as $key => $weight)
                            @php
                                $percentage = $totalWeight > 0 ? round(($weight / $totalWeight) * 100) : 0;
                                $labels = [
                                    'seniority' => 'Seniority',
                                    'performance' => 'Performance',
                                    'availability' => 'Availability',
                                    'preferred_shift' => 'Preferred Shift',
                                    'rotation_willingness' => 'Willingness'
                                ];
                                $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                            @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span style="color: var(--text-secondary);">{{ $label }}</span>
                                    <span style="color: var(--text-primary);">{{ $weight }} ({{ $percentage }}%)</span>
                                </div>
                                <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                    <div class="h-2 rounded-full" style="width: {{ $percentage }}%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Group Settings Card -->
            @if($group->settings)
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-sliders-h mr-2" style="color: var(--primary);"></i>
                            Group Settings
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        @php
                            $settings = $group->settings;
                        @endphp
                        
                        @if(isset($settings['require_handover']))
                            <div class="flex items-center justify-between">
                                <span style="color: var(--text-secondary);">Require Handover:</span>
                                <span class="{{ $settings['require_handover'] ? 'badge-success' : 'badge-secondary' }} px-2 py-0.5 text-xs rounded-full">
                                    {{ $settings['require_handover'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        @endif
                        
                        @if(isset($settings['notify_on_rotate']))
                            <div class="flex items-center justify-between">
                                <span style="color: var(--text-secondary);">Notify on Rotation:</span>
                                <span class="{{ $settings['notify_on_rotate'] ? 'badge-success' : 'badge-secondary' }} px-2 py-0.5 text-xs rounded-full">
                                    {{ $settings['notify_on_rotate'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        @endif
                        
                        @if(isset($settings['maintain_coverage']))
                            <div class="flex items-center justify-between">
                                <span style="color: var(--text-secondary);">Maintain Coverage:</span>
                                <span class="{{ $settings['maintain_coverage'] ? 'badge-success' : 'badge-secondary' }} px-2 py-0.5 text-xs rounded-full">
                                    {{ $settings['maintain_coverage'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        @endif
                        
                        @if(isset($settings['allow_swaps']))
                            <div class="flex items-center justify-between">
                                <span style="color: var(--text-secondary);">Allow Swaps:</span>
                                <span class="{{ $settings['allow_swaps'] ? 'badge-success' : 'badge-secondary' }} px-2 py-0.5 text-xs rounded-full">
                                    {{ $settings['allow_swaps'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Column - Members, History, Schedules -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Members Card -->
            <div class="card">
                <div class="p-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Group Members ({{ $group->members->count() }})
                    </h3>
                    <button onclick="showAssignMembersModal({{ $group->id }})"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                        <i class="fas fa-user-plus mr-1"></i> Assign Members
                    </button>
                </div>
                
                @if($group->members->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Member</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Role</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Preference</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Rotations</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Joined</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group->members->sortByDesc('preference_score') as $member)
                                    <tr class="border-b" style="border-color: var(--border-color);">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                    <i class="fas fa-user text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">{{ $member->user->name }}</div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $member->user->badge_number ?? 'No badge' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @php
                                                $roleColors = [
                                                    'leader' => 'primary',
                                                    'deputy' => 'info',
                                                    'member' => 'secondary'
                                                ];
                                                $roleColor = $roleColors[$member->role] ?? 'secondary';
                                            @endphp
                                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $roleColor }}">
                                                {{ ucfirst($member->role) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center">
                                                <span class="font-medium mr-2" style="color: var(--text-primary);">{{ $member->preference_score ?? 'N/A' }}</span>
                                                @if($member->preference_score)
                                                    @php
                                                        $scorePercent = ($member->preference_score / 10) * 100;
                                                    @endphp
                                                    <div class="w-16 h-1.5 rounded-full" style="background-color: var(--border-color);">
                                                        <div class="h-1.5 rounded-full" 
                                                             style="width: {{ $scorePercent }}%; background-color: {{ $scorePercent >= 70 ? 'var(--success)' : ($scorePercent >= 40 ? 'var(--warning)' : 'var(--danger)') }};"></div>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-3 px-4" style="color: var(--text-primary);">{{ $member->rotation_count ?? 0 }}</td>
                                        <td class="py-3 px-4">
                                            <div style="color: var(--text-primary);">{{ $member->joined_at->format('M j, Y') }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $member->joined_at->diffForHumans() }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <button onclick="removeMember({{ $group->id }}, {{ $member->id }})"
                                                    class="text-xs px-2 py-1 rounded-lg btn-danger"
                                                    title="Remove from group">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-users text-2xl" style="color: var(--text-secondary);"></i>
                        </div>
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">No members assigned to this group yet.</p>
                        <button onclick="showAssignMembersModal({{ $group->id }})"
                                class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Assign First Member
                        </button>
                    </div>
                @endif
            </div>

            <!-- Current Assignments Card -->
            @if(isset($currentAssignments) && $currentAssignments->count() > 0)
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i>
                            Current & Upcoming Assignments
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Member</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($currentAssignments as $schedule)
                                    <tr class="border-b" style="border-color: var(--border-color);">
                                        <td class="py-3 px-4">
                                            <div style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->assignment_date->diffForHumans() }}</div>
                                        </td>
                                        <td class="py-3 px-4" style="color: var(--text-primary);">{{ $schedule->securityUser->name ?? 'N/A' }}</td>
                                        <td class="py-3 px-4" style="color: var(--text-primary);">{{ $schedule->post->name ?? 'N/A' }}</td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 text-xs rounded-full {{ $schedule->shift->is_overnight ?? false ? 'badge-info' : 'badge-secondary' }}">
                                                {{ $schedule->shift->name ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            @php
                                                $statusColors = [
                                                    'scheduled' => 'info',
                                                    'active' => 'success',
                                                    'completed' => 'secondary',
                                                    'cancelled' => 'danger'
                                                ];
                                                $statusColor = $statusColors[$schedule->status] ?? 'secondary';
                                            @endphp
                                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $statusColor }}">
                                                {{ ucfirst($schedule->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Rotation History Card -->
            @if(isset($history) && !empty($history))
                <div class="card">
                    <div class="p-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                            Rotation History
                        </h3>
                        <a href="{{ route('admin.rotation-history.group', $group->id) }}" 
                           class="text-sm" style="color: var(--info);">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="divide-y" style="border-color: var(--border-color);">
                        @foreach(array_slice($history, 0, 5) as $rotation)
                            <div class="p-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                {{ Carbon\Carbon::parse($rotation['date'])->format('M j, Y H:i') }}
                                            </span>
                                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $rotation['type'] === 'schedule_rotation' ? 'info' : 'primary' }}">
                                                {{ $rotation['type'] === 'schedule_rotation' ? 'Schedule' : 'Group' }}
                                            </span>
                                        </div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            @if(isset($rotation['from_user']))
                                                {{ $rotation['from_user'] }} → {{ $rotation['to_user'] }}
                                            @else
                                                {{ $rotation['affected_members'] ?? 0 }} members rotated
                                            @endif
                                        </div>
                                    </div>
                                    @if(isset($rotation['executed_by_name']))
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            by {{ $rotation['executed_by_name'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Performance Metrics Card -->
            @if(isset($metrics) && !empty($metrics))
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>
                            Group Performance Metrics
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $metrics['stability'] ?? 0 }}%</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Stability</div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05);">
                                <div class="text-2xl font-bold" style="color: var(--success);">{{ $metrics['efficiency'] ?? 0 }}%</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Efficiency</div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.05);">
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $metrics['satisfaction'] ?? 0 }}%</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Satisfaction</div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.05);">
                                <div class="text-2xl font-bold" style="color: var(--info);">{{ $metrics['coverage'] ?? 0 }}%</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Coverage</div>
                            </div>
                        </div>
                        
                        @if(isset($metrics['readiness']))
                            <div class="mt-4 p-3 rounded-lg flex items-center justify-between"
                                 style="background-color: rgba(var({{ $metrics['readiness'] >= 70 ? '--success-rgb' : '--warning-rgb' }}), 0.1);">
                                <span style="color: var(--text-primary);">Rotation Readiness</span>
                                <span class="font-bold" style="color: {{ $metrics['readiness'] >= 70 ? 'var(--success)' : 'var(--warning)' }};">
                                    {{ $metrics['readiness'] }}%
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Assign Members Modal -->
<div id="assignMembersModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('assignMembersModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>
                    Assign Members to Group
                </h3>
                <button type="button" onclick="closeModal('assignMembersModal')" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="assignMembersForm" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="group_id" id="assignGroupId">
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-1" style="color: var(--primary);"></i> Select Members
                    </label>
                    <select name="user_ids[]" multiple class="index-custom-dropdown w-full" size="5" required>
                        <!-- Options will be loaded dynamically -->
                    </select>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Hold Ctrl/Cmd to select multiple</p>
                </div>
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Role
                    </label>
                    <select name="role" class="index-custom-dropdown w-full">
                        <option value="member">Member</option>
                        <option value="deputy">Deputy</option>
                        <option value="leader">Leader</option>
                    </select>
                </div>
            </form>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="btn-secondary px-4 py-2 rounded-lg" onclick="closeModal('assignMembersModal')">
                    Cancel
                </button>
                <button type="button" class="btn-primary px-4 py-2 rounded-lg" onclick="assignMembers()">
                    <i class="fas fa-check mr-2"></i> Assign Members
                </button>
            </div>
        </div>
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
function showAssignMembersModal(groupId) {
    document.getElementById('assignGroupId').value = groupId;
    loadAvailableMembers(groupId);
    document.getElementById('assignMembersModal').classList.remove('hidden');
    document.getElementById('assignMembersModal').classList.add('modal-show');
}

function showRotateGroupModal(groupId) {
    document.getElementById('rotateGroupId').value = groupId;
    document.getElementById('rotateGroupModal').classList.remove('hidden');
    document.getElementById('rotateGroupModal').classList.add('modal-show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('modal-show');
}

async function loadAvailableMembers(groupId) {
    try {
        const response = await fetch(`/admin/rotation-groups/${groupId}/available-members`, {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.querySelector('#assignMembersForm select[name="user_ids[]"]');
            select.innerHTML = '';
            
            data.members.forEach(member => {
                const option = document.createElement('option');
                option.value = member.id;
                option.textContent = `${member.name} (${member.badge_number || 'No badge'})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading members:', error);
        alert('Failed to load available members');
    }
}

async function assignMembers() {
    const form = document.getElementById('assignMembersForm');
    const formData = new FormData(form);
    const groupId = formData.get('group_id');
    
    // Show loading state
    const assignBtn = event.target;
    const originalText = assignBtn.innerHTML;
    assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Assigning...';
    assignBtn.disabled = true;
    
    try {
        const response = await fetch(`/admin/rotation-groups/${groupId}/assign-members`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeModal('assignMembersModal');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to assign members');
        }
    } catch (error) {
        console.error('Error assigning members:', error);
        alert('Failed to assign members. Please try again.');
    } finally {
        assignBtn.innerHTML = originalText;
        assignBtn.disabled = false;
    }
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

async function removeMember(groupId, memberId) {
    if (!confirm('Are you sure you want to remove this member from the group?')) return;
    
    try {
        const response = await fetch(`/admin/rotation-groups/${groupId}/members/${memberId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Member removed successfully');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to remove member');
        }
    } catch (error) {
        console.error('Error removing member:', error);
        alert('Failed to remove member. Please try again.');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('assignMembersModal');
        closeModal('rotateGroupModal');
        closeModal('rotationPreviewModal');
    }
}
</script>
@endsection