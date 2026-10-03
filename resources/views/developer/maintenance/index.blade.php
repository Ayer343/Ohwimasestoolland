@extends('layouts.dev')

@section('title', 'Maintenance Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Maintenance Management</h2>
            <div class="flex space-x-2">
                <a href="{{ route('developer.maintenance.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-calendar-plus mr-2"></i> Schedule Maintenance
                </a>
                <a href="{{ route('developer.maintenance.statistics') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-chart-bar mr-2"></i> View Statistics
                </a>
            </div>
        </div>
    </div>

    <!-- Active Maintenance Banner -->
    @php
        $activeMaintenance = \App\Models\Maintenance::active()->first();
    @endphp
    
    @if($activeMaintenance)
    <div class="card p-6" style="border-left: 6px solid var(--warning); box-shadow: 0 10px 30px rgba(var(--warning-rgb), 0.15);">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between">
            <div class="flex items-center mb-4 lg:mb-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-yellow-400 to-orange-500 animate-pulse mr-4">
                    <i class="fas fa-tools text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold" style="color: var(--text-primary);">🚧 SYSTEM MAINTENANCE IN PROGRESS</h3>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-500 text-white flex items-center">
                            <i class="fas fa-sync-alt mr-1"></i>
                            IN PROGRESS
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium text-white"
                              style="background: {{ $activeMaintenance->impact_level == 'critical' ? 'var(--danger)' : ($activeMaintenance->impact_level == 'high' ? 'var(--warning)' : 'var(--info)') }};">
                            <i class="fas fa-exclamation mr-1"></i>
                            {{ strtoupper($activeMaintenance->impact_level) }} IMPACT
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-500 text-white flex items-center">
                            <i class="fas fa-tag mr-1"></i>
                            {{ strtoupper(str_replace('_', ' ', $activeMaintenance->maintenance_type)) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex space-x-2">
                <form action="{{ route('developer.maintenance.complete', $activeMaintenance->id) }}" method="POST" onsubmit="return confirmCompleteMaintenance()">
                    @csrf
                    <input type="hidden" name="post_checks_passed" value="1">
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Complete Maintenance
                    </button>
                </form>
                <button onclick="showMaintenanceDetails({{ $activeMaintenance->id }})" 
                        class="btn-secondary flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    View Details
                </button>
            </div>
        </div>
        
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeMaintenance->actual_start ? $activeMaintenance->actual_start->diffInMinutes(now()) : 0 }} min elapsed
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    Estimated: {{ $activeMaintenance->estimated_duration_minutes }} minutes
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeMaintenance->affectedUsers()->count() }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ implode(', ', array_map(fn($type) => App\Models\User::getTypeLabel($type), $activeMaintenance->affected_user_types)) }}
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Started By</div>
                <div class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-shield mr-2"></i>
                    {{ $activeMaintenance->creator->name ?? 'System' }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ $activeMaintenance->actual_start ? $activeMaintenance->actual_start->format('M d, H:i') : 'N/A' }}
                </div>
            </div>
        </div>
        
        <div class="mt-6 p-4 rounded-lg" style="background: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
            <div class="flex items-start">
                <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--warning);"></i>
                <div class="flex-1">
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">User Impact:</h4>
                    <p style="color: var(--text-secondary);">{{ $activeMaintenance->user_impact_description }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Maintenance Statistics Cards -->
    @php
        $statistics = \App\Models\Maintenance::getStatistics();
        $trends = \App\Models\Maintenance::getTrends();
    @endphp
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Maintenance</div>
                    <div class="text-2xl font-semibold">{{ $statistics['total'] ?? 0 }}</div>
                </div>
                <i class="fas fa-server text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Average Duration</div>
                    <div class="text-2xl font-semibold">{{ $statistics['avg_duration'] ?? 0 }} min</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Currently Active</div>
                    <div class="text-2xl font-semibold">
                        {{ $activeMaintenance ? '1 Active' : '0 Active' }}
                    </div>
                </div>
                <i class="fas fa-sync-alt text-2xl opacity-70 @if($activeMaintenance) animate-spin @endif"></i>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6">
            <form action="{{ route('developer.maintenance.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                        <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Status</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Type</label>
                        <select name="type" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Types</option>
                            <option value="planned" {{ request('type') == 'planned' ? 'selected' : '' }}>Planned</option>
                            <option value="emergency" {{ request('type') == 'emergency' ? 'selected' : '' }}>Emergency</option>
                            <option value="hotfix" {{ request('type') == 'hotfix' ? 'selected' : '' }}>Hotfix</option>
                            <option value="upgrade" {{ request('type') == 'upgrade' ? 'selected' : '' }}>Upgrade</option>
                            <option value="security" {{ request('type') == 'security' ? 'selected' : '' }}>Security</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Impact Level</label>
                        <select name="impact" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Impact Levels</option>
                            <option value="low" {{ request('impact') == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ request('impact') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ request('impact') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="critical" {{ request('impact') == 'critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               class="w-full p-2 border rounded" 
                               placeholder="Search title, description..." 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">From Date</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">To Date</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.maintenance.index') }}" class="btn-secondary w-full md:w-auto">
                            <i class="fas fa-times mr-2"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance List -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2"></i> All Maintenance Events
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $maintenances->firstItem() ?? 0 }} to {{ $maintenances->lastItem() ?? 0 }} of {{ $maintenances->total() }} records
                    </p>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $maintenances->total() }} total records
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: var(--bg-secondary);">
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Reference ID</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Title</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Type</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Impact</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Schedule</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                    <tr class="border-b hover:bg-opacity-5 transition-colors duration-200" style="border-color: var(--border-color); {{ $maintenance->status === 'in_progress' ? 'background: rgba(var(--warning-rgb), 0.03);' : '' }}">
                        <td class="p-3" style="color: var(--text-primary);">
                            <div class="font-mono text-sm">{{ $maintenance->reference_id }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $maintenance->created_at->format('M d, Y') }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium" style="color: var(--text-primary);">{{ Str::limit($maintenance->title, 50) }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ Str::limit($maintenance->description, 70) }}
                            </div>
                            @if($maintenance->is_emergency)
                            <span class="inline-block px-2 py-0.5 mt-1 text-xs rounded-full bg-red-100 text-red-800">
                                Emergency
                            </span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $maintenance->maintenance_type === 'emergency' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         ($maintenance->maintenance_type === 'hotfix' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         'rgba(var(--info-rgb), 0.1)') }};
                                       color: {{ $maintenance->maintenance_type === 'emergency' ? 'var(--danger)' : 
                                               ($maintenance->maintenance_type === 'hotfix' ? 'var(--warning)' : 'var(--info)') }};
                                       border: 1px solid {{ $maintenance->maintenance_type === 'emergency' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         ($maintenance->maintenance_type === 'hotfix' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         'rgba(var(--info-rgb), 0.3)') }};">
                                {{ str_replace('_', ' ', $maintenance->maintenance_type) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $maintenance->impact_level === 'critical' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         ($maintenance->impact_level === 'high' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         ($maintenance->impact_level === 'medium' ? 'rgba(var(--warning-rgb), 0.05)' : 
                                                         'rgba(var(--success-rgb), 0.1)')) }};
                                       color: {{ $maintenance->impact_level === 'critical' ? 'var(--danger)' : 
                                               ($maintenance->impact_level === 'high' ? 'var(--warning)' : 
                                               ($maintenance->impact_level === 'medium' ? 'var(--warning)' : 'var(--success)')) }};
                                       border: 1px solid {{ $maintenance->impact_level === 'critical' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         ($maintenance->impact_level === 'high' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         ($maintenance->impact_level === 'medium' ? 'rgba(var(--warning-rgb), 0.2)' : 
                                                         'rgba(var(--success-rgb), 0.3)')) }};">
                                {{ $maintenance->impact_level }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $maintenance->status === 'in_progress' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         ($maintenance->status === 'completed' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                         ($maintenance->status === 'cancelled' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         'rgba(var(--info-rgb), 0.1)')) }};
                                       color: {{ $maintenance->status === 'in_progress' ? 'var(--warning)' : 
                                               ($maintenance->status === 'completed' ? 'var(--success)' : 
                                               ($maintenance->status === 'cancelled' ? 'var(--danger)' : 'var(--info)')) }};
                                       border: 1px solid {{ $maintenance->status === 'in_progress' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         ($maintenance->status === 'completed' ? 'rgba(var(--success-rgb), 0.3)' : 
                                                         ($maintenance->status === 'cancelled' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         'rgba(var(--info-rgb), 0.3)')) }};">
                                {{ str_replace('_', ' ', $maintenance->status) }}
                                @if($maintenance->status === 'scheduled')
                                <br>
                                <span class="text-xs opacity-75">
                                    {{ $maintenance->scheduled_start->diffForHumans() }}
                                </span>
                                @endif
                            </span>
                        </td>
                        <td class="p-3" style="color: var(--text-primary);">
                            @if($maintenance->status === 'in_progress' && $maintenance->actual_start)
                            <div class="text-sm">Started: {{ $maintenance->actual_start->format('M d, H:i') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $maintenance->actual_start->diffForHumans() }}
                            </div>
                            @elseif($maintenance->scheduled_start)
                            <div class="text-sm">{{ $maintenance->scheduled_start->format('M d, H:i') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $maintenance->scheduled_start->diffForHumans() }}
                            </div>
                            @else
                            <span class="text-sm" style="color: var(--text-secondary);">Not scheduled</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Button -->
                                <a href="{{ route('developer.maintenance.show', $maintenance->id) }}" 
                                   class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button (only for draft/scheduled) -->
                                @if(in_array($maintenance->status, ['draft', 'scheduled']))
                                <a href="{{ route('developer.maintenance.edit', $maintenance->id) }}" 
                                   class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif
                                
                                <!-- Approve Button (only for draft) -->
                                @if($maintenance->status === 'draft')
                                <a href="{{ route('developer.maintenance.approve', $maintenance->id) }}" 
                                   onclick="return confirm('Approve this maintenance schedule?')"
                                   class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                   title="Approve">
                                    <i class="fas fa-check"></i>
                                </a>
                                @endif
                                
                                <!-- Start Button (only for scheduled) -->
                                @if($maintenance->status === 'scheduled')
                                <form action="{{ route('developer.maintenance.start', $maintenance->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('Start this maintenance now?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                            title="Start Now">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </form>
                                @endif
                                
                                <!-- Complete Button (only for in_progress) -->
                                @if($maintenance->status === 'in_progress')
                                <form action="{{ route('developer.maintenance.complete', $maintenance->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirmCompleteMaintenance()"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                            title="Complete">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                @endif
                                
                                <!-- Cancel Button (only for draft/scheduled) -->
                                @if(in_array($maintenance->status, ['draft', 'scheduled']))
                                <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('Cancel this maintenance?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                            title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                                
                                <!-- Delete Button (only for draft) -->
                                @if($maintenance->status === 'draft')
                                <form action="{{ route('developer.maintenance.destroy', $maintenance->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            onclick="return confirm('Are you sure you want to delete this maintenance schedule?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                            title="Delete Permanently">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--text-secondary), 0.1);">
                                <i class="fas fa-tools" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                            </div>
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Maintenance Found</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                {{ request()->hasAny(['status', 'type', 'impact', 'search', 'date_from', 'date_to']) ? 'No maintenance events match your filters.' : 'No maintenance events have been scheduled yet.' }}
                            </p>
                            @if(request()->hasAny(['status', 'type', 'impact', 'search', 'date_from', 'date_to']))
                            <a href="{{ route('developer.maintenance.index') }}" class="btn-primary">
                                <i class="fas fa-times mr-2"></i> Clear Filters
                            </a>
                            @else
                            <a href="{{ route('developer.maintenance.create') }}" class="btn-primary">
                                <i class="fas fa-plus mr-2"></i> Schedule Maintenance
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($maintenances->hasPages())
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            {{ $maintenances->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Complete Maintenance Modal -->
<div id="completeMaintenanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                Complete Maintenance
            </h3>
            
            <form id="completeMaintenanceForm" method="POST">
                @csrf
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Completion Notes <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="completion_notes" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe what was done during maintenance..."
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Actual Duration (minutes) <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="actual_duration_minutes" 
                               min="1" max="1440"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Actual Affected Users <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="actual_affected_users" 
                               min="0"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Downtime Minutes (if any)
                        </label>
                        <input type="number" name="downtime_minutes" 
                               min="0"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Estimated vs Actual
                            </label>
                            <select name="estimated_vs_actual" 
                                    class="w-full p-2 border rounded"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    required>
                                <option value="within_estimate">Within Estimate</option>
                                <option value="under_estimate">Under Estimate</option>
                                <option value="over_estimate">Over Estimate</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="post_checks_passed" name="post_checks_passed" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);">
                        <label for="post_checks_passed" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Confirm post-maintenance checks have passed <span style="color: var(--danger);">*</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('completeMaintenanceModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Complete Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup filter form auto-submit on select changes
    document.querySelectorAll('select[name="status"], select[name="type"], select[name="impact"]').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});

function showMaintenanceDetails(maintenanceId) {
    window.location.href = `/developer/maintenance/${maintenanceId}`;
}

function openCompleteModal(maintenanceId) {
    const form = document.getElementById('completeMaintenanceForm');
    form.action = `/developer/maintenance/${maintenanceId}/complete`;
    document.getElementById('completeMaintenanceModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function confirmCompleteMaintenance() {
    return confirm('Are you sure you want to complete this maintenance? This action cannot be undone.');
}

// Close modal when clicking outside
document.querySelectorAll('.fixed.inset-0').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('completeMaintenanceModal');
    }
});
</script>

<style>
/* Additional styling */
.btn-primary, .btn-secondary, .btn-warning {
    transition: all 0.2s ease-in-out;
}

.btn-primary:hover, .btn-secondary:hover, .btn-warning:hover {
    transform: translateY(-1px);
}

/* Table row hover effect */
tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* Status badge improvements */
.px-2.py-1.rounded-full {
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 80px;
}

/* Action buttons styling */
.flex.gap-1 a,
.flex.gap-1 button {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.flex.gap-1 a:hover,
.flex.gap-1 button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Delete button specific styling */
.flex.gap-1 form button[title="Delete Permanently"]:hover {
    background-color: rgba(var(--danger-rgb), 0.3) !important;
    border-color: rgba(var(--danger-rgb), 0.5) !important;
}

/* Loading spinner animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .overflow-x-auto {
        font-size: 0.875rem;
    }
    
    .overflow-x-auto th,
    .overflow-x-auto td {
        padding: 0.75rem 0.5rem;
    }
    
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .flex.space-x-2 {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    /* Adjust action buttons for mobile */
    .flex.gap-1 {
        gap: 0.25rem;
    }
    
    .flex.gap-1 a,
    .flex.gap-1 button {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
}
</style>
@endsection