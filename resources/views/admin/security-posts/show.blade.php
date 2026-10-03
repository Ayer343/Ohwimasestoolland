@extends('layouts.app')

@section('title', $securityPost->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        @php
                            $typeIcons = [
                                'main_gate' => 'fa-door-closed',
                                'internal_gate' => 'fa-door-open',
                                'checkpoint' => 'fa-shield-alt',
                                'patrol_route' => 'fa-route',
                                'observation_post' => 'fa-binoculars',
                                'control_room' => 'fa-tv',
                                'access_point' => 'fa-key',
                            ];
                            $typeIcon = $typeIcons[$securityPost->type] ?? 'fa-map-marker-alt';
                        @endphp
                        <i class="fas {{ $typeIcon }} text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> 
                        {{ $securityPost->name }}
                        <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium capitalize"
                              style="background-color: {{ 
                                  $securityPost->type === 'main_gate' ? 'rgba(var(--primary-rgb), 0.1)' : 
                                  ($securityPost->type === 'internal_gate' ? 'rgba(var(--info-rgb), 0.1)' : 
                                  ($securityPost->type === 'checkpoint' ? 'rgba(var(--success-rgb), 0.1)' :
                                  ($securityPost->type === 'patrol_route' ? 'rgba(var(--warning-rgb), 0.1)' :
                                  ($securityPost->type === 'observation_post' ? 'rgba(var(--danger-rgb), 0.1)' :
                                  ($securityPost->type === 'control_room' ? 'rgba(var(--purple-rgb), 0.1)' :
                                  ($securityPost->type === 'access_point' ? 'rgba(var(--pink-rgb), 0.1)' : 
                                  'rgba(var(--secondary-rgb), 0.1)' ))))) ) }}; 
                                 color: {{ 
                                     $securityPost->type === 'main_gate' ? 'var(--primary)' : 
                                     ($securityPost->type === 'internal_gate' ? 'var(--info)' : 
                                     ($securityPost->type === 'checkpoint' ? 'var(--success)' :
                                     ($securityPost->type === 'patrol_route' ? 'var(--warning)' :
                                     ($securityPost->type === 'observation_post' ? 'var(--danger)' :
                                     ($securityPost->type === 'control_room' ? 'var(--purple)' :
                                     ($securityPost->type === 'access_point' ? 'var(--pink)' : 
                                     'var(--secondary)' ))))) ) }};">
                            {{ str_replace('_', ' ', $securityPost->type) }}
                        </span>
                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: {{ $securityPost->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                     color: {{ $securityPost->is_active ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if($securityPost->requires_checkin)
                            <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-check-circle mr-1"></i> Check-in
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag mr-2"></i>
                        <span>Code: {{ $securityPost->code }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span>{{ Str::limit($securityPost->location, 40) }}</span>
                        @if($securityPost->digital_address)
                            <span class="mx-2">•</span>
                            <i class="fas fa-map-pin mr-2"></i>
                            <span>{{ $securityPost->digital_address }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="showHistory()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-history mr-2"></i> Activity
                </button>
                <a href="{{ route('admin.security-posts.edit', $securityPost) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-user-plus mr-2"></i> Assign
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Posts
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}?post_id={{ $securityPost->id }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Schedules
                </a>
                
                <a href="{{ route('admin.security-schedules.calendar') }}?post_id={{ $securityPost->id }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-calendar-alt mr-1"></i> Calendar
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Assignments -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_assignments'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Assignments</div>
                </div>
            </div>
        </div>
        
        <!-- Completed Shifts -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['completed_shifts'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Completed Shifts</div>
                </div>
            </div>
        </div>
        
        <!-- Active Today -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['active_today'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Today</div>
                </div>
            </div>
        </div>
        
        <!-- Staffing Rate -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['staffing_rate'] }}%</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Staffing Rate</div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="h-2 rounded-full" 
                             style="width: {{ min(100, $stats['staffing_rate']) }}%; 
                                    background-color: var(--warning);">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Today's Schedule Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i> 
                            Today's Schedule
                            <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                {{ $todaySchedules->count() }}/{{ $securityPost->max_personnel }}
                            </span>
                        </h3>
                        <div class="flex space-x-2">
                            @if($todaySchedules->count() < $securityPost->max_personnel)
                                <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}&date={{ now()->toDateString() }}" 
                                   class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </a>
                            @endif
                            <a href="{{ route('admin.security-schedules.index') }}?post_id={{ $securityPost->id }}&date={{ now()->toDateString() }}" 
                               class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                View All
                            </a>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    @if($todaySchedules->count() > 0)
                        <div class="space-y-3">
                            @foreach($todaySchedules as $schedule)
                                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                            </div>
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                                </div>
                                                <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $schedule->shift->getTimeRange() ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-4">
                                            <div>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                                      style="background-color: {{ 
                                                          $schedule->status === 'active' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                          ($schedule->status === 'completed' ? 'rgba(var(--info-rgb), 0.1)' : 
                                                          'rgba(var(--warning-rgb), 0.1)') 
                                                      }}; 
                                                             color: {{ 
                                                                 $schedule->status === 'active' ? 'var(--success)' : 
                                                                 ($schedule->status === 'completed' ? 'var(--info)' : 'var(--warning)') 
                                                             }};">
                                                    {{ $schedule->status }}
                                                </span>
                                            </div>
                                            @if($schedule->checkin_time)
                                            <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-sign-in-alt mr-1"></i>
                                                {{ $schedule->checkin_time->format('H:i') }}
                                            </div>
                                            @endif
                                            @if($schedule->securityUser)
                                            <a href="{{ route('admin.security-schedules.index') }}?search={{ $schedule->securityUser->name }}" 
                                               class="action-btn"
                                               title="View Schedules"
                                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-user-slash text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                                No assignments scheduled for today
                            </p>
                            <p class="mb-4" style="color: var(--text-secondary);">
                                Assign personnel to this post for today's shifts
                            </p>
                            <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}&date={{ now()->toDateString() }}" 
                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                <i class="fas fa-user-plus mr-2"></i> Assign Personnel
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Upcoming Schedule Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-week mr-2" style="color: var(--info);"></i> 
                            Upcoming Schedule (Next 7 Days)
                        </h3>
                        <a href="{{ route('admin.security-schedules.calendar') }}?post_id={{ $securityPost->id }}" 
                           class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-calendar-alt mr-1"></i> Calendar
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    @if($upcomingSchedules->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b" style="border-color: var(--border-color);">
                                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Date</th>
                                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Personnel</th>
                                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Shift</th>
                                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingSchedules as $schedule)
                                        <tr class="border-b transition-colors duration-150" style="border-color: var(--border-color);">
                                            <td class="py-3 px-4">
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $schedule->assignment_date->format('D, M j') }}
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    {{ $schedule->assignment_date->diffForHumans() }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 text-xs text-white"
                                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                                        {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                                    </div>
                                                    <div style="color: var(--text-primary);">
                                                        {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div style="color: var(--text-primary);">
                                                    {{ $schedule->shift->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    {{ $schedule->shift->getTimeRange() ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    {{ $schedule->status }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                @if($schedule->securityUser)
                                                <a href="{{ route('admin.security-schedules.index') }}?search={{ $schedule->securityUser->name }}&date={{ $schedule->assignment_date->format('Y-m-d') }}" 
                                                   class="action-btn"
                                                   title="View Schedules"
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                                No upcoming schedules
                            </p>
                            <p class="mb-4" style="color: var(--text-secondary);">
                                No personnel assigned for the next 7 days
                            </p>
                            <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}" 
                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                <i class="fas fa-user-plus mr-2"></i> Schedule Personnel
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Post Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> 
                        Post Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Code</div>
                            <div style="color: var(--text-primary);">{{ $securityPost->code }}</div>
                        </div>
                        
                        <div>
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Location</div>
                            <div style="color: var(--text-primary);">{{ $securityPost->location }}</div>
                            @if($securityPost->digital_address)
                                <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-pin mr-1"></i> {{ $securityPost->digital_address }}
                                </div>
                            @endif
                        </div>
                        
                        @if($securityPost->working_hours && is_array($securityPost->working_hours))
                        <div>
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Working Hours</div>
                            <div style="color: var(--text-primary);">
                                {{ $securityPost->working_hours['start'] ?? 'N/A' }} - {{ $securityPost->working_hours['end'] ?? 'N/A' }}
                            </div>
                        </div>
                        @endif
                        
                        <div>
                            <div class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Personnel Capacity</div>
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2 mr-3">
                                    @php
                                        $currentPersonnel = $securityPost->current_personnel ?? 0;
                                        $maxPersonnel = $securityPost->max_personnel ?? 1;
                                        $capacityPercentage = min(100, ($currentPersonnel / max(1, $maxPersonnel)) * 100);
                                        $capacityColor = $currentPersonnel >= $maxPersonnel ? 'var(--success)' : 
                                                         ($currentPersonnel > 0 ? 'var(--warning)' : 'var(--danger)');
                                    @endphp
                                    <div class="h-2 rounded-full" 
                                         style="width: {{ $capacityPercentage }}%; 
                                                background-color: {{ $capacityColor }};">
                                    </div>
                                </div>
                                <div class="text-sm font-medium" 
                                     style="color: {{ $capacityColor }};">
                                    {{ $currentPersonnel }}/{{ $maxPersonnel }}
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Status</div>
                            <div class="flex flex-wrap gap-2">
                                <span class="px-3 py-1 rounded-full text-sm font-medium"
                                      style="background-color: {{ $securityPost->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }}; 
                                             color: {{ $securityPost->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                    {{ $securityPost->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if($securityPost->requires_checkin)
                                    <span class="px-3 py-1 rounded-full text-sm font-medium"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-check-circle mr-1"></i> Check-in Required
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        @if($securityPost->description)
                        <div>
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Description</div>
                            <div class="p-3 rounded-lg border" 
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                                {{ $securityPost->description }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Equipment Card -->
            @if(count($equipment) > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tools mr-2" style="color: var(--success);"></i> 
                        Equipment
                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            {{ count($equipment) }} items
                        </span>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        @foreach($equipment as $item)
                            <div class="flex items-center p-3 rounded-lg border"
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
                                <span style="color: var(--text-primary);">{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Restrictions Card -->
            @if(count($restrictions) > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-ban mr-2" style="color: var(--danger);"></i> 
                        Restrictions
                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            {{ count($restrictions) }} items
                        </span>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        @foreach($restrictions as $restriction)
                            <div class="flex items-center p-3 rounded-lg border"
                                 style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <i class="fas fa-exclamation-circle mr-3" style="color: var(--danger);"></i>
                                <span style="color: var(--text-primary);">{{ $restriction }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> 
                        Quick Actions
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <a href="{{ route('admin.security-posts.edit', $securityPost) }}" 
                           class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="flex-grow">
                                <div class="font-medium" style="color: var(--text-primary);">Edit Post</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Modify post details and settings</div>
                            </div>
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        <a href="{{ route('admin.security-schedules.create') }}?post_id={{ $securityPost->id }}" 
                           class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="flex-grow">
                                <div class="font-medium" style="color: var(--text-primary);">Assign Personnel</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Schedule security personnel</div>
                            </div>
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        <a href="{{ route('admin.security-schedules.index') }}?post_id={{ $securityPost->id }}" 
                           class="flex items-center p-3 rounded-lg border transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="flex-grow">
                                <div class="font-medium" style="color: var(--text-primary);">View Schedules</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">All assignments for this post</div>
                            </div>
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        <button onclick="togglePostStatus()"
                                class="w-full flex items-center p-3 rounded-lg border transition-colors duration-200 text-left"
                                style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: {{ $securityPost->is_active ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)' }}; 
                                        color: {{ $securityPost->is_active ? 'var(--danger)' : 'var(--success)' }};">
                                <i class="fas fa-power-off"></i>
                            </div>
                            <div class="flex-grow">
                                <div class="font-medium" 
                                     style="color: {{ $securityPost->is_active ? 'var(--danger)' : 'var(--success)' }};">
                                    {{ $securityPost->is_active ? 'Deactivate' : 'Activate' }} Post
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $securityPost->is_active ? 'Temporarily disable this post' : 'Enable this post for assignments' }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity History Modal -->
<div id="historyModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('historyModal')"></div>
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title flex items-center">
                <i class="fas fa-history mr-2"></i> Recent Activity
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('historyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            @if($securityPost->schedules && $securityPost->schedules->count() > 0)
                <div class="space-y-3">
                    @foreach($securityPost->schedules->sortByDesc('assignment_date')->take(20) as $schedule)
                        <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                        {{ substr($schedule->securityUser->name ?? 'N/A', 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $schedule->securityUser->name ?? 'Unknown User' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            {{ $schedule->assignment_date->format('M j, Y') }}
                                            @if($schedule->shift)
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-clock mr-1"></i>
                                                {{ $schedule->shift->getTimeRange() ?? 'N/A' }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    @if($schedule->checkin_time)
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-sign-in-alt mr-1"></i>
                                        {{ $schedule->checkin_time->format('H:i') }}
                                    </div>
                                    @endif
                                    <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                          style="background-color: {{ 
                                              $schedule->status === 'completed' ? 'rgba(var(--success-rgb), 0.1)' : 
                                              ($schedule->status === 'active' ? 'rgba(var(--info-rgb), 0.1)' : 
                                              ($schedule->status === 'absent' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                              'rgba(var(--warning-rgb), 0.1)')) 
                                          }}; 
                                                 color: {{ 
                                                     $schedule->status === 'completed' ? 'var(--success)' : 
                                                     ($schedule->status === 'active' ? 'var(--info)' : 
                                                     ($schedule->status === 'absent' ? 'var(--danger)' : 'var(--warning)')) 
                                                 }};">
                                        {{ $schedule->status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-history text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">
                        No recent activity
                    </p>
                    <p style="color: var(--text-secondary);">
                        This post has no recent assignments
                    </p>
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('historyModal')">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Canvas variables (for future use if needed)
let canvas = null;
let ctx = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeActionButtons();
});

function togglePostStatus() {
    if (!confirm(`Are you sure you want to {{ $securityPost->is_active ? 'deactivate' : 'activate' }} this post?`)) {
        return;
    }
    
    showLoading('Updating post status...');
    
    fetch(`{{ route('admin.security-posts.toggle-activation', $securityPost) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

function showHistory() {
    openModal('historyModal');
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function initializeActionButtons() {
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Loading functions
function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay hidden';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
                <div class="text-white font-medium" id="loadingMessage">${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) {
        messageEl.textContent = message;
    }
    
    overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});
</script>

<style>
/* Apply the same CSS styles as index blade */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Form elements */
.form-input, .form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay .animate-spin {
    animation: spin 1s linear infinite;
    border-top-color: transparent;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Action button styles */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Quick action item styles */
.quick-action-item {
    transition: all 0.2s ease;
    cursor: pointer;
}

.quick-action-item:hover {
    transform: translateX(4px);
    border-color: var(--primary) !important;
}

/* Toast animations */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.toast-slide-in {
    animation: slideIn 0.3s ease forwards;
}

.toast-slide-out {
    animation: slideOut 0.3s ease forwards;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.3);
}

/* Focus states for accessibility */
.form-input:focus-visible,
.form-select:focus-visible,
button:focus-visible,
a:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.25rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
    }
    
    .text-4xl {
        font-size: 2rem !important;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Progress bar styling */
.progress-bar {
    transition: width 0.3s ease;
}

/* Required field indicator */
.required-field::after {
    content: " *";
    color: var(--danger);
}

/* Validation message */
.validation-message {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: none;
}

.error-field ~ .validation-message {
    display: block;
    color: var(--danger);
}

/* Error field styling */
.error-field {
    border-color: var(--danger) !important;
    background-color: rgba(var(--danger-rgb), 0.05) !important;
}

.error-field:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

/* Checkbox styling */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

input[type="checkbox"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Loading button animation */
.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-top: -8px;
    margin-left: -8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s linear infinite;
}

/* Empty state styling */
.text-center i {
    opacity: 0.7;
}

.text-center h4 {
    margin-top: 1rem;
}

.text-center p {
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Radio card selection */
input[type="radio"]:checked + div {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.1) !important;
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
</style>
@endsection