@extends('layouts.secu')

@section('title', 'My Rotation Groups')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-users-cog text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-rotate mr-2" style="color: var(--primary);"></i>
                        My Rotation Groups
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $rotationGroups->count() }} active groups</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.schedules.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-2"></i> Back to Schedules
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalGroups ?? $rotationGroups->count() }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Groups</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        @php
            $totalMembers = 0;
            $totalRotations = 0;
            foreach($rotationGroups as $group) {
                $totalMembers += $group->members_count ?? 0;
                $totalRotations += $group->total_rotations ?? 0;
            }
        @endphp
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $totalMembers }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Team Members</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-user-friends" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $totalRotations }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Rotations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-sync-alt" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">
                        @php
                            $avgScore = 0;
                            $groupCount = 0;
                            foreach($rotationGroups as $group) {
                                if(isset($group->avg_score)) {
                                    $avgScore += $group->avg_score;
                                    $groupCount++;
                                }
                            }
                            $overallAvg = $groupCount > 0 ? round($avgScore / $groupCount, 1) : 0;
                        @endphp
                        {{ $overallAvg }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Avg Score</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-star" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Rotation Groups -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-rotate mr-2" style="color: var(--primary);"></i>
                Active Rotation Groups
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $rotationGroups->count() }} groups
                </span>
            </h3>
            
            <div class="flex items-center space-x-2">
                <select id="groupFilter" class="px-3 py-2 rounded-lg text-sm border" 
                        style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <option value="all">All Types</option>
                    <option value="day">Day Groups</option>
                    <option value="night">Night Groups</option>
                    <option value="rotating">Rotating</option>
                </select>
            </div>
        </div>
        
        @if($rotationGroups->isEmpty())
            <div class="p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users-cog text-3xl" style="color: var(--primary);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Rotation Groups</h4>
                <p style="color: var(--text-secondary);">You are not currently a member of any rotation groups.</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">When you're added to a group, it will appear here.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                @foreach($rotationGroups as $group)
                    <div class="group-card rounded-lg overflow-hidden transition-all duration-300 hover:shadow-lg"
                         style="border: 1px solid var(--border-color); background-color: var(--card-bg);"
                         data-group-type="{{ $group->group_type ?? 'rotating' }}">
                        
                        <!-- Group Header with Gradient -->
                        <div class="p-4" style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--secondary-rgb), 0.1) 100%); border-bottom: 1px solid var(--border-color);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                        <i class="fas fa-rotate"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $group->name }}</h4>
                                        <div class="flex items-center mt-1">
                                            <span class="text-xs px-2 py-0.5 rounded-full badge-{{ $group->group_type == 'night' ? 'info' : ($group->group_type == 'day' ? 'warning' : 'primary') }}">
                                                {{ ucfirst($group->group_type ?? 'rotating') }} Group
                                            </span>
                                            @if($group->auto_rotate)
                                                <span class="text-xs ml-2" style="color: var(--success);">
                                                    <i class="fas fa-sync-alt mr-1"></i> Auto-rotate
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                @php
                                    $myMember = $group->members->firstWhere('user_id', auth()->id());
                                    $myScore = $myMember ? $myMember->preference_score : 0;
                                    $scoreColor = $myScore >= 8 ? 'success' : ($myScore >= 6 ? 'warning' : 'info');
                                @endphp
                                
                                <div class="text-center">
                                    <div class="text-2xl font-bold" style="color: var(--{{ $scoreColor }});">{{ $myScore }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">My Score</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Group Details -->
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <div class="text-xs mb-1" style="color: var(--text-secondary);">Post</div>
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $group->post->name ?? 'N/A' }}</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $group->post->code ?? '' }}</div>
                                </div>
                                
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <div class="text-xs mb-1" style="color: var(--text-secondary);">Shift</div>
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $group->shift->name ?? 'N/A' }}</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $group->shift ? substr($group->shift->start_time, 0, 5) . ' - ' . substr($group->shift->end_time, 0, 5) : '' }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Member Stats -->
                            <div class="flex justify-between items-center mb-4 p-2 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                                <div class="text-center flex-1">
                                    <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $group->members->count() }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Members</div>
                                </div>
                                <div class="text-center flex-1" style="border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color);">
                                    <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ round($group->members->avg('preference_score') ?? 0, 1) }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Avg Score</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $group->members->sum('rotation_count') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Rotations</div>
                                </div>
                            </div>
                            
                            <!-- Rotation Schedule Preview -->
                            @php
                                $nextRotation = null;
                                if($group->rotation_config && isset($group->rotation_config['next_rotation_date'])) {
                                    $nextRotation = \Carbon\Carbon::parse($group->rotation_config['next_rotation_date']);
                                }
                            @endphp
                            
                            @if($nextRotation)
                            <div class="mb-4 p-3 rounded-lg flex items-center" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                                <i class="fas fa-calendar-day mr-3" style="color: var(--info);"></i>
                                <div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Next Rotation</div>
                                    <div style="color: var(--text-primary);">{{ $nextRotation->format('M j, Y') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $nextRotation->diffForHumans() }}</div>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Member Avatars Preview -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex -space-x-2">
                                    @foreach($group->members->take(5) as $member)
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium border-2"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); border-color: var(--card-bg);"
                                             title="{{ $member->name }}">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                    @endforeach
                                    @if($group->members->count() > 5)
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs border-2"
                                             style="background-color: var(--bg-secondary); color: var(--text-secondary); border-color: var(--card-bg);">
                                            +{{ $group->members->count() - 5 }}
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-chart-line mr-1" style="color: var(--success);"></i>
                                    {{ $group->members->where('preference_score', '>=', 8)->count() }} top performers
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                <a href="{{ route('security.rotation-group', $group->id) }}" 
                                   class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-center transition-all duration-200 hover:translate-y-[-2px]"
                                   style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-eye mr-2"></i> View Group
                                </a>
                                
                                @if($group->rotation_schedule ?? false)
                                <button onclick="viewSchedule({{ $group->id }})"
                                        class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-200 hover:translate-y-[-2px]"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                        title="View Schedule">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Footer with Rotation Pattern -->
                        <div class="px-4 py-2 text-xs border-t flex justify-between items-center" 
                             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-sync-alt mr-1" style="color: var(--info);"></i>
                                Pattern: {{ $group->rotation_config['type'] ?? 'standard' }}
                            </span>
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                                Interval: {{ $group->rotation_config['interval_days'] ?? 7 }} days
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Pagination if needed -->
            @if(method_exists($rotationGroups, 'links'))
                <div class="p-6 border-t" style="border-color: var(--border-color);">
                    {{ $rotationGroups->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- Rotation Statistics -->
    @if($rotationGroups->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Rotation Frequency Chart (placeholder) -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>
                Rotation Distribution
            </h3>
            
            @php
                $dayGroups = $rotationGroups->where('group_type', 'day')->count();
                $nightGroups = $rotationGroups->where('group_type', 'night')->count();
                $rotatingGroups = $rotationGroups->where('group_type', 'rotating')->count();
                $totalGroups = $rotationGroups->count();
            @endphp
            
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Day Groups</span>
                        <span style="color: var(--text-primary);">{{ $dayGroups }} ({{ $totalGroups > 0 ? round(($dayGroups/$totalGroups)*100, 1) : 0 }}%)</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                        <div class="h-2 rounded-full" style="width: {{ $totalGroups > 0 ? ($dayGroups/$totalGroups)*100 : 0 }}%; background: linear-gradient(90deg, var(--warning), var(--primary));"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Night Groups</span>
                        <span style="color: var(--text-primary);">{{ $nightGroups }} ({{ $totalGroups > 0 ? round(($nightGroups/$totalGroups)*100, 1) : 0 }}%)</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                        <div class="h-2 rounded-full" style="width: {{ $totalGroups > 0 ? ($nightGroups/$totalGroups)*100 : 0 }}%; background: linear-gradient(90deg, var(--info), var(--primary));"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: var(--text-secondary);">Rotating Groups</span>
                        <span style="color: var(--text-primary);">{{ $rotatingGroups }} ({{ $totalGroups > 0 ? round(($rotatingGroups/$totalGroups)*100, 1) : 0 }}%)</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                        <div class="h-2 rounded-full" style="width: {{ $totalGroups > 0 ? ($rotatingGroups/$totalGroups)*100 : 0 }}%; background: linear-gradient(90deg, var(--success), var(--primary));"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Rotations -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-calendar-week mr-2" style="color: var(--primary);"></i>
                Upcoming Rotations
            </h3>
            
            @php
                $upcomingRotations = collect();
                foreach($rotationGroups as $group) {
                    if($group->rotation_config && isset($group->rotation_config['next_rotation_date'])) {
                        $upcomingRotations->push([
                            'group' => $group->name,
                            'date' => \Carbon\Carbon::parse($group->rotation_config['next_rotation_date']),
                            'type' => $group->group_type ?? 'rotating'
                        ]);
                    }
                }
                $upcomingRotations = $upcomingRotations->sortBy('date')->take(5);
            @endphp
            
            @if($upcomingRotations->isEmpty())
                <div class="text-center py-4" style="color: var(--text-secondary);">
                    No upcoming rotations scheduled
                </div>
            @else
                <div class="space-y-3">
                    @foreach($upcomingRotations as $rotation)
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $rotation['group'] }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $rotation['date']->format('l, F j, Y') }}</div>
                            </div>
                            <div>
                                <span class="text-xs px-2 py-1 rounded-full badge-{{ $rotation['type'] == 'night' ? 'info' : ($rotation['type'] == 'day' ? 'warning' : 'primary') }}">
                                    {{ ucfirst($rotation['type']) }}
                                </span>
                                <div class="text-xs mt-1 text-right" style="color: var(--text-secondary);">
                                    {{ $rotation['date']->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif
</div>

<!-- Filter Script -->
<script>
document.getElementById('groupFilter')?.addEventListener('change', function() {
    const filter = this.value;
    const groups = document.querySelectorAll('.group-card');
    
    groups.forEach(group => {
        const groupType = group.dataset.groupType;
        if (filter === 'all' || groupType === filter) {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
        }
    });
});

function viewSchedule(groupId) {
    // This would typically open a modal or redirect to group schedule
    window.location.href = `/security/rotation-groups/${groupId}/schedule`;
}
</script>

<style>
.group-card {
    transition: all 0.3s ease;
}

.group-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px -10px rgba(var(--primary-rgb), 0.3);
}

/* Animation for cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group-card {
    animation: fadeInUp 0.4s ease forwards;
}

/* Stagger animation for multiple cards */
.group-card:nth-child(1) { animation-delay: 0.1s; }
.group-card:nth-child(2) { animation-delay: 0.2s; }
.group-card:nth-child(3) { animation-delay: 0.3s; }
.group-card:nth-child(4) { animation-delay: 0.4s; }
.group-card:nth-child(5) { animation-delay: 0.5s; }
.group-card:nth-child(6) { animation-delay: 0.6s; }

/* Badge styles - matching the dashboard */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
</style>
@endsection