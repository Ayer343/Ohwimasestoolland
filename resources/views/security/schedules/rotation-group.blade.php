@extends('layouts.secu')

@section('title', $group->name . ' - Rotation Group')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header with Group Info -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-lg flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-rotate text-2xl"></i>
                    </div>
                </div>
                <div>
                    <div class="flex items-center">
                        <h2 class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $group->name }}</h2>
                        <span class="ml-3 px-3 py-1 text-xs rounded-full badge-{{ $group->group_type == 'night' ? 'info' : ($group->group_type == 'day' ? 'warning' : 'primary') }}">
                            {{ ucfirst($group->group_type ?? 'rotating') }} Group
                        </span>
                        @if($group->auto_rotate)
                            <span class="ml-2 px-2 py-1 text-xs rounded-full badge-success">
                                <i class="fas fa-sync-alt mr-1"></i> Auto-rotate
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center mt-2 text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>{{ $group->post->name ?? 'No Post' }} ({{ $group->post->code ?? '' }})</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-2"></i>
                        <span>{{ $group->shift->name ?? 'No Shift' }}</span>
                        @if($group->shift)
                            <span class="ml-2 text-xs">({{ substr($group->shift->start_time, 0, 5) }} - {{ substr($group->shift->end_time, 0, 5) }})</span>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <a href="{{ route('security.schedules.rotation-groups') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Groups
                </a>
                <a href="{{ route('security.schedules.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                    <i class="fas fa-calendar-alt mr-2"></i> My Schedules
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalMembers }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Members</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $myScore }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">My Score</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-star" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $avgScore ? round($avgScore, 1) : 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Group Avg</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-chart-line" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $totalRotations }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Rotations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-sync-alt" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Group Members -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Group Members
                        <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">{{ $totalMembers }} members</span>
                    </h3>
                    
                    <div class="flex items-center space-x-2">
                        <select id="memberFilter" class="px-3 py-2 rounded-lg text-sm border"
                                style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                            <option value="all">All Members</option>
                            <option value="high">High Score (8+)</option>
                            <option value="medium">Medium Score (5-7)</option>
                            <option value="low">Low Score (below 5)</option>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Member</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Badge</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Preference Score</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Rotations</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Joined</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members as $member)
                                @php
                                    $isCurrentUser = $member->user_id == auth()->id();
                                    $scoreColor = $member->preference_score >= 8 ? 'success' : ($member->preference_score >= 5 ? 'warning' : 'info');
                                @endphp
                                <tr class="border-b hover:bg-opacity-50 transition-colors duration-200 member-row"
                                    style="border-color: var(--border-color); background-color: {{ $isCurrentUser ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--card-bg)' }};"
                                    data-score="{{ $member->preference_score }}">
                                    
                                    <td class="py-3 px-6">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium mr-3"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                {{ strtoupper(substr($member->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $member->name }}
                                                    @if($isCurrentUser)
                                                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full badge-primary">You</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">{{ $member->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <span style="color: var(--text-primary);">{{ $member->badge_number ?? 'N/A' }}</span>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $scoreColor }}">
                                            {{ $member->preference_score }}
                                        </span>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <span style="color: var(--text-primary);">{{ $member->rotation_count }}</span>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <span style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($member->joined_at ?? $member->user_created_at)->format('M j, Y') }}</span>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        @if(!$isCurrentUser)
                                            <button onclick="viewMember({{ $member->user_id }})"
                                                    class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                    title="View Member">
                                                <i class="fas fa-user text-sm"></i>
                                            </button>
                                        @else
                                            <span style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center" style="color: var(--text-secondary);">
                                        No active members found in this group
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Rotation Schedule -->
            <div class="card mt-6">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                        Rotation Schedule
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned To</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                <th class="text-left py-3 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rotationSchedule as $schedule)
                                @php
                                    $isToday = $schedule->assignment_date->isToday();
                                    $isUpcoming = $schedule->assignment_date->isFuture();
                                    $isCurrentUser = $schedule->security_user_id == auth()->id();
                                @endphp
                                <tr class="border-b hover:bg-opacity-50 transition-colors duration-200"
                                    style="border-color: var(--border-color); background-color: {{ $isToday ? 'rgba(var(--warning-rgb), 0.05)' : 'var(--card-bg)' }};">
                                    
                                    <td class="py-3 px-6">
                                        <div class="font-medium" style="color: var(--text-primary);">{{ $schedule->assignment_date->format('M j, Y') }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">{{ $schedule->assignment_date->format('l') }}</div>
                                        @if($isToday)
                                            <span class="text-xs px-2 py-0.5 rounded-full badge-warning mt-1">Today</span>
                                        @endif
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <span class="px-2 py-1 text-xs rounded-full badge-primary">{{ $schedule->shift->name ?? 'N/A' }}</span>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ $schedule->shift ? substr($schedule->shift->start_time, 0, 5) . ' - ' . substr($schedule->shift->end_time, 0, 5) : '' }}
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                {{ $schedule->securityUser ? strtoupper(substr($schedule->securityUser->name, 0, 1)) : '?' }}
                                            </div>
                                            <span style="color: var(--text-primary);">
                                                {{ $schedule->securityUser->name ?? 'Unassigned' }}
                                                @if($isCurrentUser)
                                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full badge-primary">You</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        @php
                                            $statusColors = [
                                                'scheduled' => 'info',
                                                'active' => 'success',
                                                'completed' => 'secondary',
                                                'absent' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$schedule->status] ?? 'secondary';
                                        @endphp
                                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $statusColor }}">
                                            {{ ucfirst($schedule->status) }}
                                        </span>
                                    </td>
                                    
                                    <td class="py-3 px-6">
                                        @if($isUpcoming || $isToday)
                                            <a href="{{ route('security.schedule.show', $schedule->id) }}"
                                               class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                               title="View Schedule">
                                                <i class="fas fa-eye text-sm"></i>
                                            </a>
                                        @else
                                            <span style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center" style="color: var(--text-secondary);">
                                        No rotation schedule found for this group
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Group Details & Stats -->
        <div class="lg:col-span-1">
            <!-- Next Rotation Card -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i>
                    Next Rotation
                </h3>
                
                @if($nextRotation)
                    <div class="text-center p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="text-3xl font-bold" style="color: var(--primary);">{{ $nextRotation->format('M j') }}</div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">{{ $nextRotation->format('l, Y') }}</div>
                        <div class="mt-3 text-sm" style="color: var(--text-primary);">
                            {{ $nextRotation->diffForHumans() }}
                        </div>
                    </div>
                @else
                    <div class="text-center py-4" style="color: var(--text-secondary);">
                        No upcoming rotation scheduled
                    </div>
                @endif
            </div>
            
            <!-- Rotation Pattern Card -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i>
                    Rotation Pattern
                </h3>
                
                @php
                    $rotationConfig = $group->rotation_config ?? [];
                    $pattern = $rotationConfig['type'] ?? 'standard';
                    $interval = $rotationConfig['interval_days'] ?? 7;
                    $sequence = $rotationConfig['sequence'] ?? ['Morning', 'Evening', 'Night', 'Off'];
                @endphp
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Pattern:</span>
                        <span class="font-medium" style="color: var(--text-primary);">{{ ucfirst($pattern) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Interval:</span>
                        <span class="font-medium" style="color: var(--text-primary);">Every {{ $interval }} days</span>
                    </div>
                    
                    @if(!empty($sequence))
                        <div class="mt-4">
                            <div class="text-sm mb-2" style="color: var(--text-secondary);">Rotation Sequence:</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($sequence as $seq)
                                    <span class="px-2 py-1 text-xs rounded-full badge-info">{{ $seq }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- My Stats Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>
                    My Statistics
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-secondary);">My Score:</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $myScore }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                            <div class="h-2 rounded-full" style="width: {{ ($myScore / 10) * 100 }}%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-secondary);">My Rotations:</span>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $myRotationCount }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                            @php
                                $maxRotations = $members->max('rotation_count') ?: 1;
                                $rotationPercentage = $maxRotations > 0 ? ($myRotationCount / $maxRotations) * 100 : 0;
                            @endphp
                            <div class="h-2 rounded-full" style="width: {{ $rotationPercentage }}%; background: linear-gradient(90deg, var(--success), var(--info));"></div>
                        </div>
                    </div>
                    
                    <div class="pt-3 mt-3 border-t" style="border-color: var(--border-color);">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Rank in Group:</span>
                            @php
                                $rank = $members->sortByDesc('preference_score')->pluck('user_id')->search(auth()->id()) + 1;
                            @endphp
                            <span class="font-bold" style="color: var(--primary);">#{{ $rank }} of {{ $totalMembers }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-2">
                            <span style="color: var(--text-secondary);">Above Average:</span>
                            @php
                                $aboveAvg = $myScore > $avgScore ? 'Yes' : 'No';
                                $avgColor = $myScore > $avgScore ? 'success' : 'warning';
                            @endphp
                            <span style="color: var(--{{ $avgColor }});">{{ $aboveAvg }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Member View Modal -->
<div id="memberModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('memberModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user mr-2" style="color: var(--info);"></i>
                    Member Details
                </h3>
            </div>
            
            <div class="p-6" id="memberContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentMemberId = null;

function viewMember(userId) {
    currentMemberId = userId;
    const modal = document.getElementById('memberModal');
    const content = document.getElementById('memberContent');
    
    modal.classList.remove('hidden');
    
    // Find member data from the table
    const members = @json($members);
    const member = members.find(m => m.user_id === userId);
    
    if (member) {
        displayMemberDetails(member);
    } else {
        content.innerHTML = '<div class="text-center py-4" style="color: var(--danger);">Member not found</div>';
    }
}

function displayMemberDetails(member) {
    const content = document.getElementById('memberContent');
    const scoreColor = member.preference_score >= 8 ? 'success' : (member.preference_score >= 5 ? 'warning' : 'info');
    
    let html = `
        <div class="text-center mb-4">
            <div class="w-20 h-20 mx-auto rounded-full flex items-center justify-center text-2xl font-bold mb-3"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                ${member.name ? member.name.charAt(0).toUpperCase() : '?'}
            </div>
            <h4 class="text-xl font-semibold" style="color: var(--text-primary);">${member.name}</h4>
            <div class="text-sm mt-1" style="color: var(--text-secondary);">${member.email}</div>
        </div>
        
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Badge Number:</span>
                <span style="color: var(--text-primary);">${member.badge_number || 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Phone:</span>
                <span style="color: var(--text-primary);">${member.phone || 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Preference Score:</span>
                <span class="px-2 py-1 text-xs rounded-full badge-${scoreColor}">${member.preference_score}</span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Rotation Count:</span>
                <span style="color: var(--text-primary);">${member.rotation_count}</span>
            </div>
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Joined:</span>
                <span style="color: var(--text-primary);">${new Date(member.joined_at || member.user_created_at).toLocaleDateString()}</span>
            </div>
        </div>
        
        <div class="mt-6">
            <button onclick="closeModal('memberModal')"
                    class="w-full px-4 py-2 rounded-lg text-sm font-medium"
                    style="background-color: var(--primary); color: white;">
                Close
            </button>
        </div>
    `;
    
    content.innerHTML = html;
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Filter members by score
document.getElementById('memberFilter')?.addEventListener('change', function() {
    const filter = this.value;
    const rows = document.querySelectorAll('.member-row');
    
    rows.forEach(row => {
        const score = parseFloat(row.dataset.score);
        
        if (filter === 'all') {
            row.style.display = '';
        } else if (filter === 'high' && score >= 8) {
            row.style.display = '';
        } else if (filter === 'medium' && score >= 5 && score < 8) {
            row.style.display = '';
        } else if (filter === 'low' && score < 5) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('memberModal');
    }
}
</script>

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
}

/* Badge styles */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }

/* Modal animations */
.fixed {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
@endsection