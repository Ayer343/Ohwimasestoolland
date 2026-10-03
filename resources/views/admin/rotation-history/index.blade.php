@extends('layouts.app')

@section('title', 'Rotation Analytics Dashboard')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--info);"></i> 
                        Rotation Analytics Dashboard
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Track rotation patterns, effectiveness, and personnel performance</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>{{ $startDate->format('M j, Y') }} - {{ $endDate->format('M j, Y') }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-sync-alt mr-1"></i>
                        <span>{{ $statistics['overall']['total_rotations'] ?? 0 }} total rotations</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex space-x-2">
                    <a href="{{ route('admin.rotation-history.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d'), 'format' => 'csv']) }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-2"></i> CSV
                    </a>
                    <a href="{{ route('admin.rotation-history.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d'), 'format' => 'excel']) }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-file-excel mr-2"></i> Excel
                    </a>
                    <a href="{{ route('admin.rotation-history.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d'), 'format' => 'pdf']) }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                       style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-file-pdf mr-2"></i> PDF
                    </a>
                </div>
                <a href="{{ route('admin.rotation-groups.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Groups
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--info);"></i> Date Range Filter
        </h3>
        
        <form method="GET" action="{{ route('admin.rotation-history.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-1" style="color: var(--info);"></i> Start Date
                </label>
                <input type="date" 
                       id="start_date"
                       name="start_date" 
                       value="{{ $startDate->format('Y-m-d') }}" 
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
            </div>
            
            <div class="flex-1 min-w-[200px]">
                <label for="end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-check mr-1" style="color: var(--info);"></i> End Date
                </label>
                <input type="date" 
                       id="end_date"
                       name="end_date" 
                       value="{{ $endDate->format('Y-m-d') }}" 
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
            </div>
            
            <div>
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Overview Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">
                        {{ $statistics['overall']['total_rotations'] ?? 0 }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Rotations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-sync-alt" style="color: var(--primary);"></i>
                </div>
            </div>
            @php
                $uniquePersonnel = $statistics['overall']['unique_personnel'] ?? 0;
                $uniquePosts = $statistics['overall']['unique_posts'] ?? 0;
            @endphp
            @if($uniquePersonnel > 0 || $uniquePosts > 0)
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                {{ $uniquePersonnel }} personnel • {{ $uniquePosts }} posts
            </div>
            @endif
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">
                        {{ $statistics['overall']['success_rate'] ?? 0 }}%
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Success Rate</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
            @php
                $successfulRotations = $statistics['overall']['successful_rotations'] ?? 0;
            @endphp
            @if($successfulRotations > 0)
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                {{ $successfulRotations }} successful rotations
            </div>
            @endif
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">
                        {{ number_format($statistics['overall']['avg_preference_score'] ?? 0, 1) }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Avg Preference Score</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-star" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">
                        {{ $statistics['overall']['rotation_rate'] ?? 0 }}%
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Rotation Rate</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-chart-line" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Effectiveness Comparison Card -->
    @if(isset($statistics['effectiveness']) && !empty($statistics['effectiveness']))
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-2" style="color: var(--info);"></i> Rotation Effectiveness
            </h3>
            @php
                $rotatedCount = $statistics['effectiveness']['rotated']['count'] ?? 0;
                $nonRotatedCount = $statistics['effectiveness']['non_rotated']['count'] ?? 0;
            @endphp
            <span class="px-3 py-1 text-xs rounded-full badge-info">
                {{ $rotatedCount + $nonRotatedCount }} total shifts
            </span>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Rotated vs Non-Rotated Comparison -->
                <div>
                    <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Performance Comparison
                    </h4>
                    
                    @php
                        $rotatedCompletion = $statistics['effectiveness']['rotated']['completion_rate'] ?? 0;
                        $nonRotatedCompletion = $statistics['effectiveness']['non_rotated']['completion_rate'] ?? 0;
                        $rotatedLateRate = $statistics['effectiveness']['rotated']['late_rate'] ?? 0;
                        $nonRotatedLateRate = $statistics['effectiveness']['non_rotated']['late_rate'] ?? 0;
                        $rotatedLateMinutes = $statistics['effectiveness']['rotated']['avg_late_minutes'] ?? 0;
                        $nonRotatedLateMinutes = $statistics['effectiveness']['non_rotated']['avg_late_minutes'] ?? 0;
                        $completionImprovement = $statistics['effectiveness']['improvement']['completion_rate'] ?? 0;
                        $lateImprovement = $statistics['effectiveness']['improvement']['lateness'] ?? 0;
                    @endphp
                    
                    <div class="space-y-6">
                        <!-- Completion Rate Comparison -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span style="color: var(--text-secondary);">Completion Rate</span>
                                <div>
                                    <span class="font-medium" style="color: var(--success);">{{ $rotatedCompletion }}%</span>
                                    <span class="mx-1" style="color: var(--text-secondary);">vs</span>
                                    <span class="font-medium" style="color: var(--text-secondary);">{{ $nonRotatedCompletion }}%</span>
                                </div>
                            </div>
                            <div class="flex h-2 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div style="width: {{ $rotatedCompletion }}%; background-color: var(--success);"></div>
                                <div style="width: {{ $nonRotatedCompletion }}%; background-color: rgba(var(--secondary-rgb), 0.5);"></div>
                            </div>
                            @if($completionImprovement > 0)
                            <div class="text-xs mt-1" style="color: var(--success);">
                                <i class="fas fa-arrow-up mr-1"></i> +{{ number_format($completionImprovement, 1) }}% improvement
                            </div>
                            @endif
                        </div>

                        <!-- Late Rate Comparison -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span style="color: var(--text-secondary);">Late Rate</span>
                                <div>
                                    <span class="font-medium" style="color: var(--warning);">{{ $rotatedLateRate }}%</span>
                                    <span class="mx-1" style="color: var(--text-secondary);">vs</span>
                                    <span class="font-medium" style="color: var(--text-secondary);">{{ $nonRotatedLateRate }}%</span>
                                </div>
                            </div>
                            <div class="flex h-2 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div style="width: {{ $rotatedLateRate }}%; background-color: var(--warning);"></div>
                                <div style="width: {{ $nonRotatedLateRate }}%; background-color: rgba(var(--secondary-rgb), 0.5);"></div>
                            </div>
                            @if($lateImprovement < 0)
                            <div class="text-xs mt-1" style="color: var(--success);">
                                <i class="fas fa-arrow-down mr-1"></i> {{ number_format(abs($lateImprovement), 1) }}% fewer late arrivals
                            </div>
                            @endif
                        </div>

                        <!-- Average Late Minutes -->
                        <div class="flex justify-between items-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <span style="color: var(--text-secondary);">Average Late Minutes</span>
                            <div>
                                <span class="font-medium" style="color: var(--warning);">{{ $rotatedLateMinutes }} min</span>
                                <span class="mx-1" style="color: var(--text-secondary);">vs</span>
                                <span class="font-medium" style="color: var(--text-secondary);">{{ $nonRotatedLateMinutes }} min</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timing Statistics -->
                <div>
                    <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--info);"></i> Rotation Timing
                    </h4>
                    
                    @if(isset($statistics['timing']) && !empty($statistics['timing']))
                        @php
                            $peakHour = $statistics['timing']['peak_hour'] ?? 'N/A';
                            $peakDay = $statistics['timing']['peak_day'] ?? 'N/A';
                            $avgHour = $statistics['timing']['avg_hour'] ?? 'N/A';
                            $byHour = $statistics['timing']['by_hour'] ?? [];
                            $maxCount = !empty($byHour) ? max($byHour) : 1;
                        @endphp
                        
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                                <div class="text-sm" style="color: var(--text-secondary);">Peak Hour</div>
                                <div class="text-xl font-bold" style="color: var(--primary);">{{ $peakHour }}:00</div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.05);">
                                <div class="text-sm" style="color: var(--text-secondary);">Peak Day</div>
                                <div class="text-xl font-bold" style="color: var(--info);">{{ $peakDay }}</div>
                            </div>
                        </div>

                        <!-- Hour Distribution Chart -->
                        <div>
                            <p class="text-sm mb-3" style="color: var(--text-secondary);">Distribution by Hour</p>
                            <div class="flex h-32 items-end space-x-1">
                                @foreach(range(0, 23, 2) as $hour)
                                    @php
                                        $hourKey = str_pad($hour, 2, '0', STR_PAD_LEFT);
                                        $count = $byHour[$hourKey] ?? 0;
                                        $height = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                                    @endphp
                                    <div class="flex-1 flex flex-col items-center group">
                                        <div class="w-full bg-info bg-opacity-20 rounded-t transition-all duration-200 group-hover:bg-opacity-40" 
                                             style="height: {{ $height }}%; background-color: rgba(var(--info-rgb), 0.3);"></div>
                                        <span class="text-xxs mt-1" style="color: var(--text-secondary);">{{ $hour }}</span>
                                        @if($count > 0)
                                        <div class="absolute hidden group-hover:block bg-black bg-opacity-75 text-white text-xs rounded px-2 py-1 -mt-16">
                                            {{ $count }} rotations
                                        </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-8 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-clock text-3xl mb-2 opacity-50"></i>
                            <p>No timing data available for this period</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tabs Card -->
    <div class="card">
        <div class="border-b" style="border-color: var(--border-color);">
            <div class="flex overflow-x-auto">
                <button class="tab-button active px-6 py-4 text-sm font-medium whitespace-nowrap" 
                        data-tab="recent" 
                        onclick="switchTab('recent')" 
                        style="color: var(--primary); border-bottom: 2px solid var(--primary);">
                    <i class="fas fa-history mr-2"></i> Recent Rotations
                </button>
                <button class="tab-button px-6 py-4 text-sm font-medium whitespace-nowrap" 
                        data-tab="groups" 
                        onclick="switchTab('groups')" 
                        style="color: var(--text-secondary);">
                    <i class="fas fa-users mr-2"></i> Top Groups
                </button>
                <button class="tab-button px-6 py-4 text-sm font-medium whitespace-nowrap" 
                        data-tab="personnel" 
                        onclick="switchTab('personnel')" 
                        style="color: var(--text-secondary);">
                    <i class="fas fa-user mr-2"></i> Top Personnel
                </button>
                <button class="tab-button px-6 py-4 text-sm font-medium whitespace-nowrap" 
                        data-tab="trends" 
                        onclick="switchTab('trends')" 
                        style="color: var(--text-secondary);">
                    <i class="fas fa-chart-line mr-2"></i> Trends
                </button>
            </div>
        </div>

        <div class="p-6">
            <!-- Recent Rotations Tab -->
            <div id="tab-recent" class="tab-content">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date & Time</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Schedule Date</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">From</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">To</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Group</th>
                                <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRotations ?? [] as $rotation)
                                @php
                                    $rotationDate = isset($rotation['date']) ? \Carbon\Carbon::parse($rotation['date']) : null;
                                    $scheduleDate = $rotation['schedule_date'] ?? null;
                                    $post = $rotation['post'] ?? 'N/A';
                                    $shift = $rotation['shift'] ?? 'N/A';
                                    $fromUser = $rotation['from_user'] ?? 'N/A';
                                    $toUser = $rotation['to_user'] ?? 'N/A';
                                    $group = $rotation['group'] ?? null;
                                    $groupId = $rotation['group_id'] ?? null;
                                    $reason = $rotation['reason'] ?? 'Scheduled rotation';
                                @endphp
                                <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                                    style="border-color: var(--border-color); background-color: var(--card-bg);">
                                    <td class="py-3 px-4">
                                        @if($rotationDate)
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $rotationDate->format('M j, Y') }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $rotationDate->format('H:i') }}</div>
                                        @else
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $scheduleDate }}</td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $post }}</td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $shift }}</td>
                                    <td class="py-3 px-4" style="color: var(--text-primary);">{{ $fromUser }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 text-xs rounded-full badge-success">
                                            {{ $toUser }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($group && $groupId)
                                            <a href="{{ route('admin.rotation-groups.show', $groupId) }}" 
                                               class="text-xs hover:underline" style="color: var(--info);">
                                                {{ $group }}
                                            </a>
                                        @else
                                            <span style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="text-xs" style="color: var(--text-secondary);">{{ $reason }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-16 h-16 rounded-full flex items-center justify-center mb-3"
                                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                                <i class="fas fa-history text-2xl" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <p class="text-sm" style="color: var(--text-secondary);">No recent rotations found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Groups Tab -->
            <div id="tab-groups" class="tab-content hidden">
                @if(isset($topGroups) && count($topGroups) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($topGroups as $group)
                            @php
                                $groupName = $group['name'] ?? 'Unnamed Group';
                                $groupType = $group['type'] ?? 'unknown';
                                $rotationCount = $group['rotation_count'] ?? 0;
                                $memberCount = $group['member_count'] ?? 0;
                                $groupId = $group['id'] ?? null;
                                
                                $typeColors = [
                                    'day' => 'success',
                                    'night' => 'info',
                                    'evening' => 'warning',
                                    'rotating' => 'primary',
                                    'standby' => 'secondary'
                                ];
                                $typeColor = $typeColors[$groupType] ?? 'secondary';
                            @endphp
                            <div class="border rounded-lg p-4 hover:shadow-md transition-shadow duration-200" 
                                 style="border-color: var(--border-color); background-color: var(--card-bg);">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold" style="color: var(--text-primary);">{{ $groupName }}</h4>
                                        <div class="flex items-center mt-1">
                                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $typeColor }}">
                                                {{ ucfirst($groupType) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $rotationCount }}</div>
                                </div>
                                <div class="flex justify-between text-sm py-2 border-t" style="border-color: var(--border-color);">
                                    <span style="color: var(--text-secondary);">Members:</span>
                                    <span class="font-medium">{{ $memberCount }}</span>
                                </div>
                                @if($groupId)
                                <div class="mt-3">
                                    <a href="{{ route('admin.rotation-groups.show', $groupId) }}" 
                                       class="text-sm inline-flex items-center hover:underline" style="color: var(--info);">
                                        View Details <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                    </a>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-users text-2xl" style="color: var(--text-secondary);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No group data available</p>
                    </div>
                @endif
            </div>

            <!-- Top Personnel Tab -->
            <div id="tab-personnel" class="tab-content hidden">
                @if(isset($topPersonnel) && count($topPersonnel) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($topPersonnel as $person)
                            @php
                                $personName = $person->name ?? 'Unknown';
                                $personId = $person->id ?? null;
                                $rotationCount = $person->rotation_count ?? 0;
                                $preferenceScore = $person->preference_score ?? null;
                                $badgeNumber = $person->badge_number ?? null;
                                $initials = substr($personName, 0, 2);
                            @endphp
                            <div class="border rounded-lg p-4 hover:shadow-md transition-shadow duration-200" 
                                 style="border-color: var(--border-color); background-color: var(--card-bg);">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                        {{ strtoupper($initials) }}
                                    </div>
                                    <div>
                                        <h4 class="font-semibold" style="color: var(--text-primary);">{{ $personName }}</h4>
                                        @if($badgeNumber)
                                        <p class="text-xs" style="color: var(--text-secondary);">Badge: {{ $badgeNumber }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span style="color: var(--text-secondary);">Rotations:</span>
                                        <span class="font-bold" style="color: var(--primary);">{{ $rotationCount }}</span>
                                    </div>
                                    @if($preferenceScore)
                                    <div class="flex justify-between text-sm">
                                        <span style="color: var(--text-secondary);">Preference Score:</span>
                                        <span class="font-medium" style="color: var(--info);">{{ number_format($preferenceScore, 1) }}</span>
                                    </div>
                                    @endif
                                </div>
                                @if($personId)
                                <div class="mt-3 pt-2 border-t" style="border-color: var(--border-color);">
                                    <a href="{{ route('admin.rotation-history.user', $personId) }}" 
                                       class="text-sm inline-flex items-center hover:underline" style="color: var(--info);">
                                        View History <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                    </a>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-user text-2xl" style="color: var(--text-secondary);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No personnel data available</p>
                    </div>
                @endif
            </div>

            <!-- Trends Tab -->
            <div id="tab-trends" class="tab-content hidden">
                @if(isset($trends) && count($trends) > 0)
                    <div class="h-80 relative">
                        <canvas id="trendsChart"></canvas>
                    </div>
                    
                    @php
                        $trendsCollection = collect($trends);
                        $avgRotations = $trendsCollection->avg('rotations') ?? 0;
                        $maxRotations = $trendsCollection->max('rotations') ?? 0;
                        $daysWithRotations = $trendsCollection->where('rotations', '>', 0)->count();
                    @endphp
                    
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">
                                {{ number_format($avgRotations, 1) }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Daily Average</div>
                        </div>
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--success);">
                                {{ $maxRotations }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Peak Day</div>
                        </div>
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="text-2xl font-bold" style="color: var(--info);">
                                {{ $daysWithRotations }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">Days with Rotations</div>
                        </div>
                    </div>
                @else
                    <div class="py-12 text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-chart-line text-2xl" style="color: var(--text-secondary);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">No trend data available for this period</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
/* Tab button styles */
.tab-button {
    transition: all 0.2s ease;
    background: none;
    border: none;
    cursor: pointer;
}

.tab-button:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
}

/* Avatar styles */
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

/* Badge styles (reusing from index) */
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

/* Tooltip styles */
.group:hover .absolute {
    display: block;
}

/* Text size utilities */
.text-xxs {
    font-size: 0.625rem;
}

/* Table hover effect */
tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.style.color = 'var(--text-secondary)';
        btn.style.borderBottom = 'none';
    });
    
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.style.color = 'var(--primary)';
        activeBtn.style.borderBottom = '2px solid var(--primary)';
    }
    
    // Show selected tab
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeTab = document.getElementById(`tab-${tabName}`);
    if (activeTab) {
        activeTab.classList.remove('hidden');
    }
}

// Initialize trends chart
document.addEventListener('DOMContentLoaded', function() {
    @if(isset($trends) && count($trends) > 0)
        const trendsData = @json($trends);
        
        if (trendsData && trendsData.length > 0) {
            const ctx = document.getElementById('trendsChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trendsData.map(t => t.date || ''),
                        datasets: [
                            {
                                label: 'Rotations',
                                data: trendsData.map(t => t.rotations || 0),
                                borderColor: 'var(--primary)',
                                backgroundColor: 'rgba(var(--primary-rgb), 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: 'var(--primary)',
                                pointBorderColor: 'white',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            },
                            {
                                label: '7-Day Average',
                                data: trendsData.map(t => t.moving_avg || null),
                                borderColor: 'var(--warning)',
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: 'var(--text-secondary)',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'var(--card-bg)',
                                titleColor: 'var(--text-primary)',
                                bodyColor: 'var(--text-secondary)',
                                borderColor: 'var(--border-color)',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(var(--secondary-rgb), 0.1)'
                                },
                                ticks: {
                                    color: 'var(--text-secondary)',
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: 'var(--text-secondary)',
                                    maxTicksLimit: 10,
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                });
            }
        }
    @endif
});
</script>
@endsection