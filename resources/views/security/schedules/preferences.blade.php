@extends('layouts.secu')

@section('title', 'Rotation Group Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    
    @if(!$group)
    {{-- Show message when user is not in a rotation group --}}
    <div class="card p-12 text-center">
        <div class="flex flex-col items-center justify-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" 
                 style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-users-slash text-4xl" style="color: var(--warning);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">No Rotation Group Assigned</h3>
            <p class="text-center mb-4" style="color: var(--text-secondary); max-width: 400px;">
                You are not currently assigned to any rotation group. 
                Once assigned, your group details will appear here.
            </p>
            <a href="{{ route('security.schedules.index') }}" class="btn-primary">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Schedules
            </a>
        </div>
    </div>
    @else
    {{-- User is in a rotation group - show full content --}}
    
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <a href="{{ route('security.schedules.index') }}" 
                   class="mr-4 w-10 h-10 rounded-lg flex items-center justify-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users-cog mr-2" style="color: var(--primary);"></i>
                        {{ $group->name ?? 'N/A' }}
                    </h2>
                    <div class="flex items-center mt-1 space-x-4">
                        <span class="text-sm flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-building mr-1"></i> {{ $group->post->name ?? 'N/A' }}
                        </span>
                        <span class="text-sm flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i> {{ $group->shift->name ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div>
                <span class="px-3 py-1 text-sm rounded-full badge-info">
                    <i class="fas fa-sync-alt mr-1"></i> {{ ucfirst($group->group_type ?? 'Standard') }} Rotation
                </span>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalMembers ?? 0 }}</div>
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
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $totalRotations ?? 0 }}</div>
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
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $myScore ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">My Preference Score</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-star" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $myRotationCount ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">My Rotations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-chart-line" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Group Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Group Info & Members -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Group Information -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                    Group Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Rotation Pattern</div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                            <span style="color: var(--text-primary);">{{ $group->rotation_pattern ?? 'Weekly' }}</span>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Next Rotation</div>
                        <div class="flex items-center">
                            <i class="fas fa-hourglass-half mr-2" style="color: var(--warning);"></i>
                            <span style="color: var(--text-primary);">{{ $nextRotation ? $nextRotation->format('M j, Y') : 'Not scheduled' }}</span>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Post</div>
                        <div class="flex items-center">
                            <i class="fas fa-building mr-2" style="color: var(--info);"></i>
                            <span style="color: var(--text-primary);">{{ $group->post->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Shift</div>
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-2" style="color: var(--success);"></i>
                            <span style="color: var(--text-primary);">
                                {{ $group->shift->name ?? 'N/A' }} 
                                @if($group->shift && isset($group->shift->start_time))
                                ({{ substr($group->shift->start_time, 0, 5) }} - {{ substr($group->shift->end_time, 0, 5) }})
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Members -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                    Group Members
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Member</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Badge</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Preference Score</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Rotation Count</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members ?? [] as $member)
                            <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                                style="border-color: var(--border-color); background-color: {{ ($member->user_id ?? null) == auth()->id() ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--card-bg)' }};">
                                
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                            {{ substr($member->name ?? 'U', 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $member->name ?? 'Unknown' }}
                                                @if(($member->user_id ?? null) == auth()->id())
                                                    <span class="ml-2 text-xs badge-primary">You</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-3 px-4" style="color: var(--text-primary);">{{ $member->badge_number ?? 'N/A' }}</td>
                                
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <span class="mr-2" style="color: var(--text-primary);">{{ $member->preference_score ?? 0 }}</span>
                                        <div class="w-16 h-2 rounded-full" style="background-color: var(--border-color);">
                                            <div class="h-2 rounded-full" style="width: {{ (($member->preference_score ?? 0) / 10) * 100 }}%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs rounded-full badge-info">{{ $member->rotation_count ?? 0 }}</span>
                                </td>
                                
                                <td class="py-3 px-4" style="color: var(--text-secondary);">
                                    @if(isset($member->joined_at))
                                        {{ Carbon\Carbon::parse($member->joined_at)->format('M j, Y') }}
                                    @elseif(isset($member->created_at))
                                        {{ Carbon\Carbon::parse($member->created_at)->format('M j, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-8" style="color: var(--text-secondary);">
                                    No members found in this rotation group.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column - Rotation Schedule -->
        <div class="lg:col-span-1 space-y-6">
            <!-- My Position in Group -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--info);"></i>
                    My Position
                </h3>
                
                <div class="text-center mb-4">
                    <div class="inline-flex items-center justify-center w-24 h-24 rounded-full mb-3"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <span class="text-3xl font-bold">{{ $myScore ?? 0 }}</span>
                    </div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Preference Score</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">vs group average {{ round($avgScore ?? 0, 1) }}</p>
                </div>
                
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Rank in group:</span>
                        <span style="color: var(--text-primary);">
                            #{{ ($members ?? collect())->search(function($item) { return ($item->user_id ?? null) == auth()->id(); }) + 1 }} of {{ $totalMembers ?? 0 }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Rotations completed:</span>
                        <span style="color: var(--text-primary);">{{ $myRotationCount ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span style="color: var(--text-secondary);">Next rotation:</span>
                        <span style="color: var(--primary);">{{ $nextRotation ? $nextRotation->format('M j') : 'TBD' }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Rotations -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--warning);"></i>
                    Recent Rotations
                </h3>
                
                <div class="space-y-3">
                    @forelse(($rotationSchedule ?? collect())->take(5) as $rotation)
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $rotation->assignment_date ? $rotation->assignment_date->format('M j') : 'Unknown' }}
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $rotation->securityUser->name ?? 'Unknown' }}
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full badge-{{ ($rotation->security_user_id ?? null) == auth()->id() ? 'primary' : 'secondary' }}">
                                {{ ($rotation->security_user_id ?? null) == auth()->id() ? 'Me' : 'Other' }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4" style="color: var(--text-secondary);">
                        No recent rotations
                    </div>
                    @endforelse
                </div>
                
                @if(($rotationSchedule ?? collect())->count() > 5)
                <div class="mt-4 text-center">
                    <button class="text-sm" style="color: var(--primary);" onclick="viewAllRotations()">
                        View All Rotations <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
                @endif
            </div>

            <!-- Rotation Rules -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-ruler mr-2" style="color: var(--warning);"></i>
                    Rotation Rules
                </h3>
                
                <div class="space-y-2">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle mt-1 mr-2 text-sm" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-primary);">Rotations occur {{ $group->rotation_frequency ?? 'weekly' }}</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check-circle mt-1 mr-2 text-sm" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-primary);">Members rotate based on preference scores</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check-circle mt-1 mr-2 text-sm" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-primary);">Higher preference scores = more desirable shifts</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check-circle mt-1 mr-2 text-sm" style="color: var(--success);"></i>
                        <span class="text-sm" style="color: var(--text-primary);">Rotation attempts to balance workload fairly</span>
                    </div>
                </div>
                
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        Your preference score is based on attendance, punctuality, and availability.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    @endif
</div>

<style>
/* Reuse styles from my-schedules */
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}
</style>

<script>
function viewAllRotations() {
    // Implement view all rotations functionality
    alert('View all rotations - implement as needed');
}
</script>
@endsection