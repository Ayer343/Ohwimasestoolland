@extends('layouts.secu')

@section('title', 'My Supervisor Assignments')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-tie text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        My Supervisor Assignments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>{{ $assignments->total() }} total assignments</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <!-- FIXED: Changed to use the nested route structure -->
                <a href="{{ route('security.supervisor.assignments.current') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-2"></i> View Active
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Assignments List -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i>
                My Assignments
            </h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm" style="color: var(--text-secondary);">Sort by:</span>
                <select class="text-sm rounded-lg px-3 py-1.5" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);" onchange="window.location.href = this.value">
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => 'desc']) }}" {{ request('sort') == 'created_at' && request('order') == 'desc' ? 'selected' : '' }}>Newest First</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => 'asc']) }}" {{ request('sort') == 'created_at' && request('order') == 'asc' ? 'selected' : '' }}>Oldest First</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'start_date', 'order' => 'desc']) }}" {{ request('sort') == 'start_date' && request('order') == 'desc' ? 'selected' : '' }}>Start Date (Recent)</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'end_date', 'order' => 'asc']) }}" {{ request('sort') == 'end_date' && request('order') == 'asc' ? 'selected' : '' }}>End Date (Earliest)</option>
                </select>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Security Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Supervisor Type</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Duration</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Primary</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Permissions</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned On</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $assignment->post->name ?? 'All Posts' }}</div>
                                @if($assignment->post)
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $assignment->post->code }}</div>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @php
                                    $typeColors = [
                                        'post_supervisor' => 'primary',
                                        'shift_supervisor' => 'info',
                                        'area_supervisor' => 'success',
                                        'relief_supervisor' => 'warning',
                                        'training_supervisor' => 'secondary'
                                    ];
                                    $typeColor = $typeColors[$assignment->supervisor_type] ?? 'secondary';
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $typeColor }}">
                                    {{ $assignment->supervisor_type_name }}
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $assignment->start_date->format('M j, Y') }}</div>
                                @if($assignment->end_date)
                                    <div class="text-xs" style="color: var(--text-secondary);">to {{ $assignment->end_date->format('M j, Y') }}</div>
                                @else
                                    <div class="text-xs" style="color: var(--info);">Indefinite</div>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($assignment->is_current)
                                    @if($assignment->is_active)
                                        <span class="px-2 py-1 text-xs rounded-full badge-success">Active</span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full badge-warning">Inactive</span>
                                    @endif
                                @elseif($assignment->is_expired)
                                    <span class="px-2 py-1 text-xs rounded-full badge-secondary">Expired</span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full badge-info">Upcoming</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                @if($assignment->is_primary_supervisor)
                                    <span class="px-2 py-1 text-xs rounded-full badge-primary">
                                        <i class="fas fa-star mr-1"></i> Primary
                                    </span>
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    @php $visiblePermissions = array_slice($assignment->permissions_list, 0, 3); @endphp
                                    @foreach($visiblePermissions as $permission)
                                        <span class="inline-block w-2 h-2 rounded-full" style="background-color: var(--success);" title="{{ $permission }}"></span>
                                    @endforeach
                                    @if(count($assignment->permissions_list) > 3)
                                        <span class="text-xs" style="color: var(--text-secondary);">+{{ count($assignment->permissions_list) - 3 }}</span>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $assignment->created_at->format('M j, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $assignment->created_at->format('H:i') }}</div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <!-- FIXED: Changed admin route to supervisor assignment show route -->
                                    <a href="{{ route('security.supervisor.assignments.show', $assignment->id) }}" 
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    
                                    @if($assignment->is_current && $assignment->is_active)
                                        <!-- FIXED: Changed to use the correct team route -->
                                        <a href="{{ route('security.supervisor.team.today') }}" 
                                           class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                           title="View Team">
                                            <i class="fas fa-users text-sm"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center" style="color: var(--text-secondary);">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-clipboard-list text-4xl mb-3" style="color: var(--text-secondary);"></i>
                                    <p>You don't have any supervisor assignments yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(method_exists($assignments, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $assignments->appends(request()->query())->links() }}
            </div>
        @endif
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
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

/* Badge styles */
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
</style>
@endsection