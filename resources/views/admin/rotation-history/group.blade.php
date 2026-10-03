@extends('layouts.app')

@section('title', 'Rotation History: ' . $group['name'])

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-2xl font-semibold" style="color: var(--text-primary);">Rotation History: {{ $group['name'] }}</h2>
                        @if($group['code'] ?? null)
                            <span class="px-2 py-1 text-xs rounded-full badge-secondary">
                                <i class="fas fa-barcode mr-1"></i> {{ $group['code'] }}
                            </span>
                        @endif
                        <span class="px-3 py-1 text-sm rounded-full badge-{{ $group['type'] === 'rotating' ? 'primary' : 'info' }}">
                            {{ ucfirst($group['type'] ?? 'standard') }}
                        </span>
                    </div>
                    <div class="text-sm flex items-center flex-wrap gap-3" style="color: var(--text-secondary);">
                        <span><i class="fas fa-building mr-1"></i> Post: {{ $group['post'] ?? 'N/A' }}</span>
                        <span>•</span>
                        <span><i class="fas fa-clock mr-1"></i> Shift: {{ $group['shift'] ?? 'N/A' }}</span>
                        <span>•</span>
                        <span><i class="fas fa-users mr-1"></i> Members: {{ $group['member_count'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.rotation-groups.show', $group['id']) }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-info">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Group
                </a>
                <a href="{{ route('admin.rotation-history.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-chart-line mr-2"></i> History Dashboard
                </a>
            </div>
        </div>
        
        <!-- Period Info -->
        <div class="px-6 pb-6">
            <div class="p-4 rounded-lg flex items-center justify-between" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt mr-3 text-lg" style="color: var(--info);"></i>
                    <div>
                        <span class="text-sm" style="color: var(--text-secondary);">Showing history from</span>
                        <span class="font-medium mx-1" style="color: var(--text-primary);">{{ $period['start'] }}</span>
                        <span style="color: var(--text-secondary);">to</span>
                        <span class="font-medium mx-1" style="color: var(--text-primary);">{{ $period['end'] }}</span>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-sm mr-3" style="color: var(--text-secondary);">Total Events:</span>
                    <span class="text-2xl font-bold" style="color: var(--primary);">{{ $total_events }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Total Events</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-history" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $statistics['total_events'] ?? 0 }}</div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Unique Dates</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar-alt" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $statistics['unique_dates'] ?? 0 }}</div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Personnel Involved</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $statistics['unique_personnel'] ?? 0 }}</div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">Avg Per Day</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-chart-line" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $statistics['avg_per_day'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Event Type Breakdown -->
    <div class="card">
        <div class="p-4 border-b" style="border-color: var(--border-color);">
            <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>
                Event Type Breakdown
            </h3>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($statistics['by_type'] ?? [] as $type => $count)
                    @php
                        $typeColors = [
                            'group_rotation' => 'primary',
                            'schedule_rotation' => 'info',
                            'historical' => 'secondary',
                            'unknown' => 'warning'
                        ];
                        $color = $typeColors[$type] ?? 'secondary';
                        $total = $statistics['total_events'] ?? 1;
                        $percentage = round(($count / $total) * 100, 1);
                    @endphp
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--{{ $color }}-rgb), 0.05);">
                        <div class="text-lg font-bold" style="color: var(--{{ $color }});">{{ $count }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ str_replace('_', ' ', ucfirst($type)) }}</div>
                        <div class="text-xs mt-1 font-medium" style="color: var(--{{ $color }});">{{ $percentage }}%</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Rotation Timeline -->
    <div class="card">
        <div class="p-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-timeline mr-2" style="color: var(--primary);"></i>
                Rotation Timeline
            </h3>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-2 py-1 rounded-full badge-primary">Group Rotation</span>
                <span class="text-xs px-2 py-1 rounded-full badge-info">Schedule Rotation</span>
                <span class="text-xs px-2 py-1 rounded-full badge-secondary">Historical</span>
            </div>
        </div>
        
        <div class="p-4 max-h-96 overflow-y-auto">
            @if(count($history) > 0)
                <div class="relative">
                    <!-- Vertical line -->
                    <div class="absolute left-4 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                    
                    @foreach($history as $index => $event)
                        @php
                            $eventType = $event['type'] ?? 'unknown';
                            $badgeColor = match($eventType) {
                                'group_rotation' => 'primary',
                                'schedule_rotation' => 'info',
                                'historical' => 'secondary',
                                default => 'warning'
                            };
                            
                            $date = \Carbon\Carbon::parse($event['date'] ?? now());
                            $isEven = $index % 2 == 0;
                        @endphp
                        
                        <div class="relative flex mb-4 {{ $isEven ? 'flex-row' : 'flex-row-reverse' }}">
                            <!-- Timeline dot -->
                            <div class="absolute left-4 w-4 h-4 rounded-full transform -translate-x-1.5 mt-1.5 z-10"
                                 style="background-color: var(--{{ $badgeColor }}); border: 2px solid var(--card-bg);">
                            </div>
                            
                            <!-- Content -->
                            <div class="ml-8 w-full">
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $badgeColor }}">
                                                {{ str_replace('_', ' ', ucfirst($eventType)) }}
                                            </span>
                                            <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                                {{ $date->format('M j, Y H:i') }}
                                            </span>
                                        </div>
                                        @if($event['schedule_date'] ?? null)
                                            <span class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar mr-1"></i> {{ $event['schedule_date'] }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            @if(isset($event['from_user_name']))
                                                <span class="text-sm" style="color: var(--text-primary);">{{ $event['from_user_name'] }}</span>
                                                <i class="fas fa-arrow-right mx-2 text-xs" style="color: var(--text-secondary);"></i>
                                            @endif
                                            <span class="text-sm font-medium" style="color: var(--primary);">
                                                {{ $event['to_user_name'] ?? 'Unknown' }}
                                            </span>
                                        </div>
                                        
                                        @if(isset($event['preference_score']))
                                            <span class="text-xs px-2 py-0.5 rounded-full" 
                                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                Score: {{ $event['preference_score'] }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if(isset($event['reason']) && $event['reason'] !== 'Scheduled rotation')
                                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-quote-left mr-1"></i> {{ $event['reason'] }}
                                        </div>
                                    @endif
                                    
                                    @if(isset($event['post']) || isset($event['shift']))
                                        <div class="mt-2 flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                                            @if(isset($event['post']))
                                                <span><i class="fas fa-building mr-1"></i> {{ $event['post'] }}</span>
                                            @endif
                                            @if(isset($event['shift']))
                                                <span><i class="fas fa-clock mr-1"></i> {{ $event['shift'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if(isset($event['schedule_id']))
                                        <div class="mt-2 text-right">
                                            <a href="{{ route('admin.schedules.show', $event['schedule_id']) }}" 
                                               class="text-xs" style="color: var(--info);">
                                                View Schedule <i class="fas fa-external-link-alt ml-1"></i>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-history text-2xl" style="color: var(--text-secondary);"></i>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">No rotation history found for this group in the selected period.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Detailed History Table -->
    <div class="card">
        <div class="p-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                Detailed History
            </h3>
            <div class="flex items-center space-x-2">
                <button onclick="exportHistory()" class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-download mr-1"></i> Export
                </button>
                <button onclick="refreshHistory()" class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center btn-info">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date/Time</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Type</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Schedule Date</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">From User</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">To User</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post/Shift</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reason</th>
                        <th class="text-left py-3 px-4 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $event)
                        @php
                            $eventType = $event['type'] ?? 'unknown';
                            $rowColor = match($eventType) {
                                'group_rotation' => 'rgba(var(--primary-rgb), 0.02)',
                                'schedule_rotation' => 'rgba(var(--info-rgb), 0.02)',
                                default => 'transparent'
                            };
                        @endphp
                        <tr class="border-b hover:bg-opacity-50" 
                            style="border-color: var(--border-color); background-color: {{ $rowColor }};">
                            <td class="py-3 px-4">
                                <div style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($event['date'])->format('M j, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($event['date'])->format('H:i:s') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-xs rounded-full badge-{{ match($eventType) {
                                    'group_rotation' => 'primary',
                                    'schedule_rotation' => 'info',
                                    'historical' => 'secondary',
                                    default => 'warning'
                                } }}">
                                    {{ str_replace('_', ' ', ucfirst($eventType)) }}
                                </span>
                            </td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">{{ $event['schedule_date'] ?? 'N/A' }}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">{{ $event['from_user_name'] ?? 'N/A' }}</td>
                            <td class="py-3 px-4">
                                <span class="font-medium" style="color: var(--primary);">{{ $event['to_user_name'] ?? 'N/A' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                @if(isset($event['post']) || isset($event['shift']))
                                    <div style="color: var(--text-primary);">{{ $event['post'] ?? '' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $event['shift'] ?? '' }}</div>
                                @else
                                    <span style="color: var(--text-secondary);">N/A</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm" style="color: var(--text-secondary);">{{ $event['reason'] ?? 'N/A' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                @if(isset($event['schedule_id']))
                                    <a href="{{ route('admin.schedules.show', $event['schedule_id']) }}" 
                                       class="text-xs px-2 py-1 rounded-lg" style="color: var(--info);">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center" style="color: var(--text-secondary);">
                                No rotation history available
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Timeline styles */
.timeline-dot {
    transition: transform 0.2s ease;
}

.timeline-dot:hover {
    transform: scale(1.2);
}

/* Table row hover */
tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

/* Badge styles */
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}
</style>

<script>
function refreshHistory() {
    window.location.reload();
}

function exportHistory() {
    const groupId = {{ $group['id'] }};
    const startDate = '{{ $period['start'] }}';
    const endDate = '{{ $period['end'] }}';
    
    window.location.href = `/admin/rotation-history/export?group_id=${groupId}&start_date=${startDate}&end_date=${endDate}&format=csv`;
}

// Add date range filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('dateRangeForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                window.location.href = `/admin/rotation-history/group/{{ $group['id'] }}?start_date=${startDate}&end_date=${endDate}`;
            }
        });
    }
});
</script>
@endsection