@extends('layouts.dev')

@section('title', 'Maintenance Details - ' . $maintenance->reference_id)

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header with Status -->
    <div class="mb-8">
        <div class="flex justify-between items-start mb-4">
            <div>
                <div class="flex items-center mb-2">
                    <h1 class="text-2xl font-bold mr-3" style="color: var(--text-primary);">
                        {{ $maintenance->title }}
                    </h1>
                    @include('developer.maintenance.partials.status-badge', ['status' => $maintenance->status])
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-hashtag text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm font-mono" style="color: var(--text-secondary);">
                            {{ $maintenance->reference_id }}
                        </span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-user-circle text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Created by {{ $maintenance->creator->name ?? 'Unknown' }}
                        </span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar text-sm mr-2" style="color: var(--text-secondary);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            {{ $maintenance->created_at->format('M d, Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-2">
                @if($maintenance->status === 'draft')
                    <button onclick="editMaintenance()" class="btn-secondary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </button>
                    <button onclick="approveModal()" class="btn-primary">
                        <i class="fas fa-check mr-2"></i> Approve
                    </button>
                @elseif($maintenance->status === 'scheduled')
                    <button onclick="startModal()" class="btn-primary">
                        <i class="fas fa-play mr-2"></i> Start
                    </button>
                    <button onclick="updateModal()" class="btn-secondary">
                        <i class="fas fa-pencil-alt mr-2"></i> Update
                    </button>
                    <button onclick="cancelModal()" class="btn-danger">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                @elseif($maintenance->status === 'in_progress')
                    <button onclick="completeModal()" class="btn-primary">
                        <i class="fas fa-flag-checkered mr-2"></i> Complete
                    </button>
                    <button onclick="updateProgress()" class="btn-secondary">
                        <i class="fas fa-sync mr-2"></i> Update
                    </button>
                @elseif($maintenance->status === 'completed')
                    <button onclick="viewReport()" class="btn-secondary">
                        <i class="fas fa-chart-bar mr-2"></i> Report
                    </button>
                    <button onclick="duplicateMaintenance()" class="btn-secondary">
                        <i class="fas fa-copy mr-2"></i> Duplicate
                    </button>
                @endif
                
                <div class="relative group">
                    <button class="p-2 rounded-lg hover:bg-opacity-10"
                            style="background-color: rgba(var(--text-secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg z-10 hidden group-hover:block"
                         style="background-color: var(--bg-primary); border: 1px solid var(--border-color);">
                        <div class="py-1">
                            <a href="{{ route('developer.maintenance.history', $maintenance->id) }}" 
                               class="block px-4 py-2 text-sm hover:bg-opacity-10"
                               style="color: var(--text-primary);">
                                <i class="fas fa-history mr-2"></i> View History
                            </a>
                            <button onclick="exportMaintenance()" 
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                    style="color: var(--text-primary);">
                                <i class="fas fa-download mr-2"></i> Export Details
                            </button>
                            @if($maintenance->status !== 'cancelled' && $maintenance->status !== 'completed')
                            <button onclick="sendReminder()" 
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                    style="color: var(--text-primary);">
                                <i class="fas fa-bell mr-2"></i> Send Reminder
                            </button>
                            @endif
                            @if(auth()->user()->isSuperAdmin())
                            <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                            <button onclick="deleteModal()" 
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                    style="color: var(--danger);">
                                <i class="fas fa-trash mr-2"></i> Delete
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timeline Progress -->
        <div class="card mb-6">
            <div class="p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-stream mr-2"></i> Maintenance Timeline
                    </h3>
                    @if($maintenance->status === 'in_progress')
                        <div class="flex items-center">
                            <div class="h-2 w-2 rounded-full animate-pulse bg-green-500 mr-2"></div>
                            <span class="text-sm" style="color: var(--success);">
                                In progress for {{ $maintenance->current_duration }}
                            </span>
                        </div>
                    @endif
                </div>
                
                <div class="relative">
                    <!-- Progress Line -->
                    <div class="absolute left-0 top-1/2 h-0.5 w-full -translate-y-1/2" 
                         style="background-color: var(--border-color);"></div>
                    
                    <!-- Timeline Steps -->
                    <div class="relative flex justify-between">
                        <!-- Draft -->
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status !== 'draft') bg-green-500 text-white
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-pencil-alt text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Draft</span>
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->created_at->format('M d') }}
                            </span>
                        </div>
                        
                        <!-- Approved -->
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if(in_array($maintenance->status, ['scheduled', 'in_progress', 'completed'])) bg-green-500 text-white
                                @elseif($maintenance->status === 'draft') bg-gray-300 text-gray-500
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Approved</span>
                            @if($maintenance->approved_at)
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->approved_at->format('M d') }}
                            </span>
                            @endif
                        </div>
                        
                        <!-- Scheduled -->
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if(in_array($maintenance->status, ['in_progress', 'completed'])) bg-green-500 text-white
                                @elseif(in_array($maintenance->status, ['draft', 'cancelled'])) bg-gray-300 text-gray-500
                                @else bg-blue-500 text-white @endif">
                                <i class="fas fa-clock text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Scheduled</span>
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->scheduled_start->format('M d') }}
                            </span>
                        </div>
                        
                        <!-- In Progress -->
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status === 'completed') bg-green-500 text-white
                                @elseif($maintenance->status === 'in_progress') bg-blue-500 text-white
                                @else bg-gray-300 text-gray-500 @endif">
                                <i class="fas fa-play text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">In Progress</span>
                            @if($maintenance->actual_start)
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->actual_start->format('M d') }}
                            </span>
                            @endif
                        </div>
                        
                        <!-- Completed -->
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 mb-2
                                @if($maintenance->status === 'completed') bg-green-500 text-white
                                @else bg-gray-300 text-gray-500 @endif">
                                <i class="fas fa-flag-checkered text-sm"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Completed</span>
                            @if($maintenance->completed_at)
                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $maintenance->completed_at->format('M d') }}
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Details & Schedule -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2"></i> Maintenance Details
                    </h3>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Description -->
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Description
                        </h4>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $maintenance->description }}
                        </p>
                    </div>
                    
                    <!-- User Impact -->
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            User Impact Description
                        </h4>
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); color: var(--text-primary);">
                            <p class="text-sm">{{ $maintenance->user_impact_description }}</p>
                        </div>
                    </div>
                    
                    <!-- Technical Details -->
                    @if($maintenance->technical_details)
                    <div>
                        <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Technical Details
                        </h4>
                        <div class="p-4 rounded-lg" 
                             style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            <p class="text-sm whitespace-pre-wrap">{{ $maintenance->technical_details }}</p>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Key Information Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-tag mr-2"></i> Classification
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Type:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ ucfirst($maintenance->maintenance_type) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Impact Level:</span>
                                    <span class="font-medium">
                                        @include('developer.maintenance.partials.impact-badge', ['impact' => $maintenance->impact_level])
                                    </span>
                                </div>
                                @if($maintenance->is_emergency)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Emergency:</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                        Yes - {{ $maintenance->emergency_reason }}
                                    </span>
                                </div>
                                @endif
                                @if($maintenance->related_emergency_id)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Linked Emergency:</span>
                                    <a href="{{ route('developer.emergencies.show', $maintenance->related_emergency_id) }}"
                                       class="text-blue-500 hover:underline">
                                        {{ $maintenance->emergencyMode->reference_id ?? 'N/A' }}
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-user-cog mr-2"></i> Responsibility
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Created By:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ $maintenance->creator->name ?? 'Unknown' }}
                                    </span>
                                </div>
                                @if($maintenance->approver)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Approved By:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ $maintenance->approver->name }} 
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            ({{ $maintenance->approved_at->format('M d, Y H:i') }})
                                        </span>
                                    </span>
                                </div>
                                @endif
                                @if($maintenance->completer)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Completed By:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ $maintenance->completer->name }}
                                    </span>
                                </div>
                                @endif
                                @if($maintenance->cancelled_by)
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Cancelled By:</span>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        {{ \App\Models\User::find($maintenance->cancelled_by)->name ?? 'Unknown' }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule & Timing Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2"></i> Schedule & Timing
                    </h3>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Scheduled Timeline -->
                        <div>
                            <h4 class="text-sm font-medium mb-4" style="color: var(--text-secondary);">
                                Scheduled Timeline
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Start Time</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $maintenance->scheduled_start->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full bg-blue-500" style="width: 100%;"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Estimated End</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $maintenance->scheduled_end->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full bg-green-500" 
                                             style="width: {{ min(100, ($maintenance->estimated_duration_minutes / 1440) * 100) }}%;"></div>
                                    </div>
                                </div>
                                
                                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                    <div class="flex items-center">
                                        <i class="fas fa-hourglass-half mr-2" style="color: var(--info);"></i>
                                        <span class="text-sm" style="color: var(--text-secondary);">
                                            Estimated Duration: 
                                            <span class="font-medium" style="color: var(--text-primary);">
                                                {{ $maintenance->estimated_duration_minutes }} minutes
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actual Timeline (if started/completed) -->
                        <div>
                            <h4 class="text-sm font-medium mb-4" style="color: var(--text-secondary);">
                                Actual Timeline
                                @if($maintenance->status === 'in_progress')
                                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        LIVE
                                    </span>
                                @endif
                            </h4>
                            
                            @if($maintenance->actual_start || $maintenance->actual_end)
                            <div class="space-y-4">
                                @if($maintenance->actual_start)
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Actual Start</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $maintenance->actual_start->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full bg-blue-500" style="width: 100%;"></div>
                                    </div>
                                </div>
                                @endif
                                
                                @if($maintenance->actual_end)
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm" style="color: var(--text-secondary);">Actual End</span>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $maintenance->actual_end->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full bg-green-500" style="width: 100%;"></div>
                                    </div>
                                </div>
                                
                                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <span class="text-xs block" style="color: var(--text-secondary);">
                                                Actual Duration
                                            </span>
                                            <span class="font-medium" style="color: var(--text-primary);">
                                                {{ $maintenance->actual_duration_minutes ?? 'N/A' }} minutes
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-xs block" style="color: var(--text-secondary);">
                                                Downtime
                                            </span>
                                            <span class="font-medium" style="color: var(--text-primary);">
                                                {{ $maintenance->downtime_minutes ?? '0' }} minutes
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            @else
                            <div class="flex items-center justify-center h-full p-8">
                                <div class="text-center">
                                    <i class="fas fa-clock text-4xl mb-3" style="color: var(--text-secondary);"></i>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        Actual timeline will appear here<br>once maintenance starts
                                    </p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Time Until Display -->
                    @if($maintenance->status === 'scheduled')
                    <div class="mt-6 p-4 rounded-lg border" 
                         style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium mb-1" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-2"></i> 
                                    Maintenance starts in
                                </h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $maintenance->time_until_start }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-bold" style="color: var(--warning);">
                                    {{ $maintenance->scheduled_start->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Impact Assessment Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Impact Assessment
                        </h3>
                        @if($maintenance->notify_users && $maintenance->notification_sent_at)
                        <span class="text-xs px-3 py-1 rounded-full bg-green-100 text-green-800">
                            <i class="fas fa-bell mr-1"></i>
                            Notified {{ $maintenance->notification_sent_at->diffForHumans() }}
                        </span>
                        @endif
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Affected Modules -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Affected Modules
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($maintenance->affected_modules_list as $module)
                            <span class="px-3 py-1 text-xs rounded-full border" 
                                  style="color: var(--text-primary); border-color: var(--border-color);">
                                <i class="fas fa-cube mr-1"></i> {{ $module }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Affected User Types -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Affected User Types
                            <span class="ml-2 text-xs font-normal" style="color: var(--text-secondary);">
                                ({{ $affectedUsersCount }} users affected)
                            </span>
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($maintenance->affected_user_types as $type)
                            <div class="flex items-center p-2 rounded-lg border"
                                 style="border-color: var(--border-color);">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    @switch($type)
                                        @case('0') <i class="fas fa-crown"></i> @break
                                        @case('1') <i class="fas fa-user-tie"></i> @break
                                        @case('2') <i class="fas fa-building"></i> @break
                                        @case('3') <i class="fas fa-home"></i> @break
                                        @case('4') <i class="fas fa-user-check"></i> @break
                                        @case('5') <i class="fas fa-code"></i> @break
                                        @case('6') <i class="fas fa-shield-alt"></i> @break
                                    @endswitch
                                </div>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    {{ \App\Models\User::getTypeName((int)$type) }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Allowed Operations -->
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Allowed Operations During Maintenance
                        </h4>
                        <div class="p-4 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); color: var(--text-primary);">
                            <div class="flex flex-wrap gap-2">
                                @foreach($maintenance->allowed_operations as $operation)
                                <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    {{ str_replace('_', ' ', ucfirst($operation)) }}
                                </span>
                                @endforeach
                            </div>
                            <p class="text-xs mt-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                These operations will remain available during the maintenance window.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Stats & Actions -->
        <div class="space-y-6">
            <!-- Statistics Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2"></i> Statistics
                    </h3>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Impact Metrics -->
                        @if($impactMetrics)
                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                Impact Metrics
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            Estimated vs Actual Users
                                        </span>
                                        <span class="text-xs font-medium" 
                                              style="color: {{ $impactMetrics['user_impact']['accuracy_percentage'] >= 90 ? 'var(--success)' : 
                                                             ($impactMetrics['user_impact']['accuracy_percentage'] >= 70 ? 'var(--warning)' : 'var(--danger)') }};">
                                            {{ round($impactMetrics['user_impact']['accuracy_percentage'], 1) }}%
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full"
                                             style="width: {{ min(100, $impactMetrics['user_impact']['accuracy_percentage']) }}%;
                                                    background-color: {{ $impactMetrics['user_impact']['accuracy_percentage'] >= 90 ? 'var(--success)' : 
                                                                        ($impactMetrics['user_impact']['accuracy_percentage'] >= 70 ? 'var(--warning)' : 'var(--danger)') }};">
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs mt-1">
                                        <span style="color: var(--text-secondary);">
                                            Est: {{ number_format($impactMetrics['user_impact']['estimated_users']) }}
                                        </span>
                                        <span style="color: var(--text-secondary);">
                                            Act: {{ number_format($impactMetrics['user_impact']['actual_users']) }}
                                        </span>
                                    </div>
                                </div>
                                
                                @if($maintenance->status === 'completed' && $impactMetrics['duration_metrics'])
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            Duration Accuracy
                                        </span>
                                        <span class="text-xs font-medium"
                                              style="color: {{ $impactMetrics['duration_metrics']['within_estimate'] ? 'var(--success)' : 'var(--warning)' }};">
                                            {{ $impactMetrics['duration_metrics']['accuracy_percentage'] }}%
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full"
                                             style="width: {{ min(100, $impactMetrics['duration_metrics']['accuracy_percentage']) }}%;
                                                    background-color: {{ $impactMetrics['duration_metrics']['within_estimate'] ? 'var(--success)' : 'var(--warning)' }};">
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                        
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="text-center p-3 rounded-lg border"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <div class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                                    {{ $affectedUsersCount }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Affected Users
                                </div>
                            </div>
                            
                            <div class="text-center p-3 rounded-lg border"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <div class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                                    {{ count($maintenance->affected_modules) }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Modules Affected
                                </div>
                            </div>
                        </div>
                        
                        <!-- Financial Impact -->
                        @if(isset($impactMetrics['financial_impact']))
                        <div class="p-3 rounded-lg border" 
                             style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.05);">
                            <h4 class="text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-chart-line mr-2"></i> Financial Impact
                            </h4>
                            <div class="text-lg font-bold mb-1" style="color: var(--warning);">
                                ${{ number_format($impactMetrics['financial_impact']['estimated_cost'], 2) }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                Estimated cost based on {{ $impactMetrics['financial_impact']['affected_users'] }} users
                                and {{ $impactMetrics['financial_impact']['downtime_hours'] }} hours downtime
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Related Maintenances -->
            @if($relatedMaintenances && $relatedMaintenances->count() > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-link mr-2"></i> Related Maintenances
                    </h3>
                </div>
                
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($relatedMaintenances as $related)
                        <div class="p-3 rounded-lg border hover:border-blue-300 transition-colors"
                             style="border-color: var(--border-color);">
                            <a href="{{ route('developer.maintenance.show', $related->id) }}" 
                               class="block">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ Str::limit($related->title, 40) }}
                                    </span>
                                    @include('developer.maintenance.partials.status-badge', ['status' => $related->status])
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    <div class="flex items-center mb-1">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $related->scheduled_start->format('M d, H:i') }}
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        @include('developer.maintenance.partials.impact-badge', ['impact' => $related->impact_level])
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Recent Activity -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-history mr-2"></i> Recent Activity
                        </h3>
                        <a href="{{ route('developer.maintenance.history', $maintenance->id) }}"
                           class="text-sm hover:underline" style="color: var(--primary);">
                            View All
                        </a>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        @forelse($recentLogs as $log)
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 mt-1"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                @switch($log->action)
                                    @case('created') <i class="fas fa-plus"></i> @break
                                    @case('approved') <i class="fas fa-check"></i> @break
                                    @case('started') <i class="fas fa-play"></i> @break
                                    @case('completed') <i class="fas fa-flag-checkered"></i> @break
                                    @case('cancelled') <i class="fas fa-times"></i> @break
                                    @case('updated') <i class="fas fa-edit"></i> @break
                                    @default <i class="fas fa-info-circle"></i>
                                @endswitch
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $log->details }}
                                </p>
                                @if($log->performed_by)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ \App\Models\User::find($log->performed_by)->name ?? 'System' }}
                                </p>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4">
                            <i class="fas fa-history text-2xl mb-2" style="color: var(--text-secondary);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                No activity recorded yet
                            </p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2"></i> Quick Actions
                    </h3>
                </div>
                
                <div class="p-6">
                    <div class="space-y-3">
                        @if($maintenance->notify_users)
                        <button onclick="resendNotifications()" class="w-full btn-secondary text-left">
                            <i class="fas fa-paper-plane mr-2"></i> Resend Notifications
                        </button>
                        @endif
                        
                        @if($maintenance->status === 'scheduled' || $maintenance->status === 'in_progress')
                        <button onclick="extendMaintenance()" class="w-full btn-secondary text-left">
                            <i class="fas fa-clock mr-2"></i> Extend Duration
                        </button>
                        @endif
                        
                        <button onclick="exportMaintenance()" class="w-full btn-secondary text-left">
                            <i class="fas fa-file-export mr-2"></i> Export Details
                        </button>
                        
                        <button onclick="viewSystemHealth()" class="w-full btn-secondary text-left">
                            <i class="fas fa-heartbeat mr-2"></i> System Health
                        </button>
                        
                        <a href="#" 
                           class="block w-full btn-secondary text-left">
                            <i class="fas fa-chart-pie mr-2"></i> View Statistics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
@include('developer.maintenance.modals.approve')
@include('developer.maintenance.modals.start')
@include('developer.maintenance.modals.complete')
@include('developer.maintenance.modals.cancel')
@include('developer.maintenance.modals.update')
@include('developer.maintenance.modals.delete')

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh for active maintenance
    @if($maintenance->status === 'in_progress')
    setInterval(function() {
        location.reload();
    }, 60000); // Refresh every minute
    @endif
    
    // Initialize tooltips
    initializeTooltips();
    
    // Setup action handlers
    setupActionHandlers();
});

function initializeTooltips() {
    // Initialize any tooltips if using a library like Popper.js
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        // Tooltip implementation
    });
}

function setupActionHandlers() {
    // Approval handler
    window.approveModal = function() {
        const modal = document.getElementById('approveModal');
        if (modal) modal.classList.remove('hidden');
    }
    
    // Start maintenance handler
    window.startModal = function() {
        const modal = document.getElementById('startModal');
        if (modal) modal.classList.remove('hidden');
    }
    
    // Complete maintenance handler
    window.completeModal = function() {
        const modal = document.getElementById('completeModal');
        if (modal) modal.classList.remove('hidden');
    }
    
    // Cancel maintenance handler
    window.cancelModal = function() {
        const modal = document.getElementById('cancelModal');
        if (modal) modal.classList.remove('hidden');
    }
    
    // Update maintenance handler
    window.updateModal = function() {
        const modal = document.getElementById('updateModal');
        if (modal) modal.classList.remove('hidden');
    }
    
    // Delete maintenance handler
    window.deleteModal = function() {
        const modal = document.getElementById('deleteModal');
        if (modal) modal.classList.remove('hidden');
    }
}

function editMaintenance() {
    window.location.href = "{{ route('developer.maintenance.edit', $maintenance->id) }}";
}

function duplicateMaintenance() {
    if (confirm('Create a copy of this maintenance schedule?')) {
        fetch("{{ route('developer.maintenance.duplicate', $maintenance->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = "{{ route('developer.maintenance.index') }}";
            } else {
                alert('Failed to duplicate: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to duplicate maintenance schedule');
        });
    }
}

function exportMaintenance() {
    // Show loading state
    const exportBtn = event.target;
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
    exportBtn.disabled = true;
    
    fetch("{{ route('developer.maintenance.export', $maintenance->id) }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
    })
    .then(response => response.blob())
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `maintenance-{{ $maintenance->reference_id }}-${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Reset button
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Failed to export maintenance details');
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

function resendNotifications() {
    if (confirm('Resend notifications to all affected users?')) {
        fetch("{{ route('developer.maintenance.resend-notifications', $maintenance->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notifications have been resent successfully');
                location.reload();
            } else {
                alert('Failed to resend notifications: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to resend notifications');
        });
    }
}

function extendMaintenance() {
    const hours = prompt('Extend maintenance by how many hours?', '1');
    if (hours && !isNaN(hours) && hours > 0) {
        fetch("{{ route('developer.maintenance.extend', $maintenance->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ hours: parseFloat(hours) })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Maintenance extended successfully');
                location.reload();
            } else {
                alert('Failed to extend maintenance: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to extend maintenance');
        });
    }
}

function sendReminder() {
    if (confirm('Send reminder notification to affected users?')) {
        fetch("{{ route('developer.maintenance.send-reminder', $maintenance->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder sent successfully');
            } else {
                alert('Failed to send reminder: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send reminder');
        });
    }
}

function viewSystemHealth() {
    window.open("#", '_blank');
}

function updateProgress() {
    const notes = prompt('Enter progress update notes:');
    if (notes) {
        fetch("{{ route('developer.maintenance.update-progress', $maintenance->id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notes: notes })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Progress updated successfully');
                location.reload();
            } else {
                alert('Failed to update progress: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update progress');
        });
    }
}

function viewReport() {
    window.location.href = "{{ route('developer.maintenance.report', $maintenance->id) }}";
}

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.add('hidden');
        });
    }
});

// Format dates for display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<style>
/* Custom animations */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Status badge colors */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-draft {
    background-color: rgba(59, 130, 246, 0.1);
    color: rgb(59, 130, 246);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.status-scheduled {
    background-color: rgba(245, 158, 11, 0.1);
    color: rgb(245, 158, 11);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-in_progress {
    background-color: rgba(16, 185, 129, 0.1);
    color: rgb(16, 185, 129);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-completed {
    background-color: rgba(5, 150, 105, 0.1);
    color: rgb(5, 150, 105);
    border: 1px solid rgba(5, 150, 105, 0.2);
}

.status-cancelled {
    background-color: rgba(239, 68, 68, 0.1);
    color: rgb(239, 68, 68);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Impact badge colors */
.impact-low {
    background-color: rgba(16, 185, 129, 0.1);
    color: rgb(16, 185, 129);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.impact-medium {
    background-color: rgba(245, 158, 11, 0.1);
    color: rgb(245, 158, 11);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.impact-high {
    background-color: rgba(239, 68, 68, 0.1);
    color: rgb(239, 68, 68);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.impact-critical {
    background-color: rgba(220, 38, 38, 0.1);
    color: rgb(220, 38, 38);
    border: 1px solid rgba(220, 38, 38, 0.2);
}

/* Card hover effects */
.card {
    transition: all 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Button hover effects */
.btn-primary:hover, .btn-secondary:hover, .btn-danger:hover {
    transform: translateY(-1px);
}

/* Timeline connector lines */
.timeline-connector {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    transform: translateY(-50%);
    z-index: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-2.md\:grid-cols-3 {
        grid-template-columns: 1fr 1fr;
    }
    
    .grid-cols-4.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    
    a {
        text-decoration: none !important;
        color: #000 !important;
    }
    
    .btn-primary, .btn-secondary, .btn-danger {
        display: none !important;
    }
}
</style>
@endsection