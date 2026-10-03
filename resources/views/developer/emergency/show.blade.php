@extends('layouts.dev')

@section('title', 'Emergency Mode Details')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Emergency Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Header Card -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <div class="flex items-center mb-2">
                            <h2 class="text-xl font-semibold mr-4" style="color: var(--text-primary);">
                                {{ $emergencyMode->name }}
                            </h2>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                    style="background-color: {{ $emergencyMode->is_active ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                             ($emergencyMode->status === 'scheduled' ? 'rgba(var(--info-rgb), 0.1)' : 
                                                             'rgba(var(--success-rgb), 0.1)') }};
                                           color: {{ $emergencyMode->is_active ? 'var(--danger)' : 
                                                   ($emergencyMode->status === 'scheduled' ? 'var(--info)' : 'var(--success)') }};
                                           border: 1px solid {{ $emergencyMode->is_active ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                             ($emergencyMode->status === 'scheduled' ? 'rgba(var(--info-rgb), 0.3)' : 
                                                             'rgba(var(--success-rgb), 0.3)') }};">
                                    {{ str_replace('_', ' ', $emergencyMode->status) }}
                                </span>
                                
                                <span class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                    style="background-color: {{ $emergencyMode->severity_level === 'critical' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                             ($emergencyMode->severity_level === 'high' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                             ($emergencyMode->severity_level === 'medium' ? 'rgba(var(--warning-rgb), 0.05)' : 
                                                             'rgba(var(--success-rgb), 0.1)')) }};
                                           color: {{ $emergencyMode->severity_level === 'critical' ? 'var(--danger)' : 
                                                   ($emergencyMode->severity_level === 'high' ? 'var(--warning)' : 
                                                   ($emergencyMode->severity_level === 'medium' ? 'var(--warning)' : 'var(--success)')) }};
                                           border: 1px solid {{ $emergencyMode->severity_level === 'critical' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                             ($emergencyMode->severity_level === 'high' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                             ($emergencyMode->severity_level === 'medium' ? 'rgba(var(--warning-rgb), 0.2)' : 
                                                             'rgba(var(--success-rgb), 0.3)')) }};">
                                    {{ $emergencyMode->severity_level }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 text-sm" style="color: var(--text-secondary);">
                            <div class="flex items-center">
                                <i class="fas fa-hashtag mr-2"></i>
                                <span class="font-mono">{{ $emergencyMode->reference_id }}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Created: {{ $emergencyMode->created_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 md:mt-0 flex space-x-2">
                        <a href="{{ route('developer.emergency.index') }}" class="btn-secondary flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        
                        @if(!$emergencyMode->is_active && $emergencyMode->status === 'inactive')
                        <form action="{{ route('developer.emergency.activate', $emergencyMode->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Activate this emergency mode?')"
                                    class="btn-warning flex items-center">
                                <i class="fas fa-power-off mr-2"></i> Activate Now
                            </button>
                        </form>
                        @endif
                        
                        @if($emergencyMode->is_active)
                        <button onclick="showDeactivationModal()" 
                                class="btn-danger flex items-center">
                            <i class="fas fa-stop-circle mr-2"></i>
                            Deactivate
                        </button>
                        @endif
                        
                        @if($emergencyMode->is_active)
                        <button onclick="showExtensionModal()" 
                                class="btn-primary flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            Extend
                        </button>
                        @endif
                        
                        @if($emergencyMode->isScheduled())
                        <form action="{{ route('developer.emergency.cancel', $emergencyMode->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Cancel this scheduled emergency mode?')"
                                    class="btn-danger flex items-center">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            
            @if($emergencyMode->is_active)
            <div class="p-6" style="background-color: rgba(var(--danger-rgb), 0.03);">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                        <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                            {{ $emergencyMode->current_duration }} minutes
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($emergencyMode->activated_at)
                            Started: {{ $emergencyMode->activated_at->format('M d, H:i') }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                        <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                            {{ $emergencyMode->affected_users_count ?? 0 }}
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            <a href="#affected-users" class="hover:underline">View affected users →</a>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="text-sm" style="color: var(--text-secondary);">Status</div>
                        <div class="text-2xl font-semibold flex items-center" style="color: var(--danger);">
                            @if($emergencyMode->is_active)
                            <i class="fas fa-exclamation-triangle mr-2 animate-pulse"></i>
                            ACTIVE
                            @else
                            <i class="fas fa-check-circle mr-2"></i>
                            INACTIVE
                            @endif
                        </div>
                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($emergencyMode->last_extended_at)
                            Last extended: {{ $emergencyMode->last_extended_at->format('M d, H:i') }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Emergency Details Card -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Emergency Details
                </h3>
            </div>
            <div class="p-6 space-y-6">
                <!-- Reason -->
                <div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                        Emergency Reason
                    </h4>
                    <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                        <p style="color: var(--text-primary);">{{ $emergencyMode->reason }}</p>
                    </div>
                </div>
                
                <!-- Description -->
                @if($emergencyMode->description)
                <div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-align-left mr-2"></i>
                        Description
                    </h4>
                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <p style="color: var(--text-primary);">{{ $emergencyMode->description }}</p>
                    </div>
                </div>
                @endif
                
                <!-- Affected Modules -->
                <div>
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-cogs mr-2"></i>
                        Affected Modules
                        <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                            ({{ count($emergencyMode->affected_modules ?? []) }} modules)
                        </span>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($emergencyMode->affected_modules_list as $module)
                        <div class="p-3 rounded-lg border flex items-center" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                <i class="fas fa-ban"></i>
                            </div>
                            <span style="color: var(--text-primary);">{{ $module }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Restricted Features -->
                @if(!empty($emergencyMode->restricted_features))
                <div>
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-lock mr-2"></i>
                        Restricted Features
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($emergencyMode->restricted_features as $feature)
                        <span class="px-3 py-1 rounded-full text-sm"
                              style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-times-circle mr-1"></i>
                            {{ str_replace('_', ' ', ucfirst($feature)) }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Allowed Operations -->
                @if(!empty($emergencyMode->allowed_operations))
                <div>
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                        Allowed Operations
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($emergencyMode->allowed_operations as $operation)
                        <span class="px-3 py-1 rounded-full text-sm"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-check mr-1"></i>
                            {{ str_replace('_', ' ', ucfirst($operation)) }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Notification Settings -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-2"></i>
                            Notification Settings
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: {{ $emergencyMode->notify_users ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)' }};">
                                    <i class="fas {{ $emergencyMode->notify_users ? 'fa-check text-green-500' : 'fa-times text-gray-400' }} text-sm"></i>
                                </div>
                                <span style="color: var(--text-primary);">
                                    Notify Users: {{ $emergencyMode->notify_users ? 'Yes' : 'No' }}
                                </span>
                            </div>
                            
                            @if($emergencyMode->notify_users && !empty($emergencyMode->notification_channels))
                            <div class="ml-9">
                                <span class="text-sm" style="color: var(--text-secondary);">Channels:</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($emergencyMode->notification_channels as $channel)
                                    <span class="px-2 py-0.5 rounded text-xs"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ $channel }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Recovery Settings
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: {{ $emergencyMode->auto_recovery ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--secondary-rgb), 0.1)' }};">
                                    <i class="fas {{ $emergencyMode->auto_recovery ? 'fa-check text-green-500' : 'fa-times text-gray-400' }} text-sm"></i>
                                </div>
                                <span style="color: var(--text-primary);">
                                    Auto-Recovery: {{ $emergencyMode->auto_recovery ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            
                            @if($emergencyMode->auto_recovery && !empty($emergencyMode->recovery_checks))
                            <div class="ml-9">
                                <span class="text-sm" style="color: var(--text-secondary);">Checks:</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($emergencyMode->recovery_checks as $check)
                                    <span class="px-2 py-0.5 rounded text-xs"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ $check }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2"></i>
                        Emergency Timeline
                    </h4>
                    <div class="relative">
                        <!-- Timeline line -->
                        <div class="absolute left-4 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                        
                        <div class="space-y-6 ml-10">
                            <!-- Created -->
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-0"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 2px solid white; z-index: 10;">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">Emergency Created</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $emergencyMode->created_at->format('M d, Y H:i') }}
                                    </div>
                                    @if($emergencyMode->createdBy)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        By: {{ $emergencyMode->createdBy->name ?? 'System' }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Activated -->
                            @if($emergencyMode->activated_at)
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-0"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 2px solid white; z-index: 10;">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">Emergency Activated</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $emergencyMode->activated_at->format('M d, Y H:i') }}
                                    </div>
                                    @if($emergencyMode->activatedByUser)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        By: {{ $emergencyMode->activatedByUser->name }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            <!-- Last Extended -->
                            @if($emergencyMode->last_extended_at)
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-0"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid white; z-index: 10;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">Duration Extended</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $emergencyMode->last_extended_at->format('M d, Y H:i') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Total extensions: {{ $emergencyMode->extensions_count }}
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Deactivated -->
                            @if($emergencyMode->deactivated_at)
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-0"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 2px solid white; z-index: 10;">
                                    <i class="fas fa-stop"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">Emergency Deactivated</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $emergencyMode->deactivated_at->format('M d, Y H:i') }}
                                    </div>
                                    @if($emergencyMode->deactivatedByUser)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        By: {{ $emergencyMode->deactivatedByUser->name }}
                                    </div>
                                    @endif
                                    @if($emergencyMode->deactivation_reason)
                                    <div class="text-xs mt-1 p-2 rounded border" style="color: var(--text-secondary); border-color: var(--border-color);">
                                        Reason: {{ $emergencyMode->deactivation_reason }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Logs -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2"></i> Recent Activity Logs
                    </h3>
                    <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}" 
                       class="text-sm flex items-center hover:underline" style="color: var(--primary);">
                        View All Logs <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="p-6">
                @if($recentLogs && $recentLogs->count() > 0)
                <div class="space-y-4">
                    @foreach($recentLogs as $log)
                    <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--{{ $log->severity_class }}-rgb), 0.1); color: var(--{{ $log->severity_class }});">
                                    <i class="fas fa-{{ $log->action == 'activated' ? 'play' : 
                                                       ($log->action == 'deactivated' ? 'stop' : 
                                                       ($log->action == 'extended' ? 'clock' : 'info')) }}"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $log->action_name }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $log->created_at->format('M d, Y H:i:s') }}
                                    </div>
                                </div>
                            </div>
                            @if($log->performer)
                            <div class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                {{ $log->performer->name }}
                            </div>
                            @endif
                        </div>
                        
                        <div class="text-sm mt-2" style="color: var(--text-primary);">
                            {{ $log->details }}
                        </div>
                        
                        @if($log->metadata)
                        <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="text-xs" style="color: var(--text-secondary);">Details:</div>
                            <div class="text-xs mt-1 space-y-1">
                                @foreach($log->metadata as $key => $value)
                                <div>
                                    <span class="font-medium" style="color: var(--text-secondary);">
                                        {{ ucfirst(str_replace('_', ' ', $key)) }}:
                                    </span>
                                    <span style="color: var(--text-primary);">
                                        @if(is_array($value))
                                            {{ json_encode($value) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--text-secondary), 0.1);">
                        <i class="fas fa-history" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                    </div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Activity Logs</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        No activity has been recorded for this emergency mode yet.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Sidebar -->
    <div class="space-y-6">
        <!-- Status Card -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i> Status Summary
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Current Status -->
                <div>
                    <div class="text-sm mb-2" style="color: var(--text-secondary);">Current Status</div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2 
                            @if($emergencyMode->is_active) bg-red-500 animate-pulse
                            @elseif($emergencyMode->status === 'scheduled') bg-blue-500
                            @else bg-green-500 @endif">
                        </div>
                        <span class="font-medium capitalize" style="color: var(--text-primary);">
                            {{ str_replace('_', ' ', $emergencyMode->status) }}
                        </span>
                    </div>
                </div>
                
                <!-- Impact Metrics -->
                @if(isset($impactMetrics) && !empty($impactMetrics))
                <div>
                    <div class="text-sm mb-2" style="color: var(--text-secondary);">Impact Summary</div>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-secondary);">Affected Users</span>
                                <span style="color: var(--text-primary);">{{ $impactMetrics['total_affected'] ?? 0 }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" 
                                     style="width: {{ min(100, ($impactMetrics['affected_percentage'] ?? 0)) }}%;">
                                </div>
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $impactMetrics['affected_percentage'] ?? 0 }}% of active users
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div class="p-2 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $impactMetrics['duration_minutes'] ?? 0 }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Minutes</div>
                            </div>
                            <div class="p-2 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ round($impactMetrics['duration_minutes'] / 60, 1) }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">Hours</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Quick Actions -->
                <div>
                    <div class="text-sm mb-3" style="color: var(--text-secondary);">Quick Actions</div>
                    <div class="space-y-2">
                        @if($emergencyMode->is_active)
                        <button onclick="showDeactivationModal()" 
                                class="w-full p-3 rounded-lg border flex items-center justify-center hover:bg-opacity-10 transition-colors"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-power-off mr-2"></i>
                            Deactivate Emergency
                        </button>
                        
                        <button onclick="showExtensionModal()" 
                                class="w-full p-3 rounded-lg border flex items-center justify-center hover:bg-opacity-10 transition-colors"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-clock mr-2"></i>
                            Extend Duration
                        </button>
                        @elseif($emergencyMode->status === 'inactive')
                        <form action="{{ route('developer.emergency.activate', $emergencyMode->id) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Activate this emergency mode?')"
                                    class="w-full p-3 rounded-lg border flex items-center justify-center hover:bg-opacity-10 transition-colors"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-play mr-2"></i>
                                Activate Now
                            </button>
                        </form>
                        @endif
                        
                        <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}" 
                           class="w-full p-3 rounded-lg border flex items-center justify-center hover:bg-opacity-10 transition-colors"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border-color: rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-history mr-2"></i>
                            View Full History
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Affected Users -->
        <div id="affected-users" class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2"></i> Affected Users
                    </h3>
                    <span class="text-sm px-2 py-1 rounded-full" 
                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        {{ $affectedUsersCount ?? 0 }} users
                    </span>
                </div>
            </div>
            <div class="p-6">
                @if($emergencyMode->affectedUsers && $emergencyMode->affectedUsers->count() > 0)
                <div class="space-y-3">
                    @foreach($emergencyMode->affectedUsers->take(5) as $affectedUser)
                    <div class="p-3 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-gray-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $affectedUser->user->name ?? 'User #' . $affectedUser->user_id }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $affectedUser->user_type_name }}
                                    </div>
                                </div>
                            </div>
                            @if($affectedUser->notified_at)
                            <div class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-check mr-1"></i> Notified
                            </div>
                            @endif
                        </div>
                        
                        @if(!empty($affectedUser->affected_features))
                        <div class="text-xs mt-2">
                            <span style="color: var(--text-secondary);">Affected features:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($affectedUser->affected_features as $feature)
                                <span class="px-1.5 py-0.5 rounded border text-xs"
                                      style="background-color: rgba(var(--warning-rgb), 0.05); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.2);">
                                    {{ str_replace('_', ' ', $feature) }}
                                </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                    
                    @if($affectedUsersCount > 5)
                    <div class="text-center">
                        <a href="#" onclick="showAllUsersModal()" 
                           class="text-sm inline-flex items-center hover:underline" style="color: var(--primary);">
                            View all {{ $affectedUsersCount }} affected users
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    @endif
                </div>
                @else
                <div class="text-center py-6">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--text-secondary), 0.1);">
                        <i class="fas fa-users" style="color: var(--text-secondary);"></i>
                    </div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        No users affected by this emergency mode.
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- System Information -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-server mr-2"></i> System Information
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Created</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->created_at->format('M d, Y H:i') }}</div>
                    @if($emergencyMode->createdBy)
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        By: {{ $emergencyMode->createdBy->name }}
                    </div>
                    @endif
                </div>
                
                @if($emergencyMode->activated_at)
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Activated</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->activated_at->format('M d, Y H:i') }}</div>
                    @if($emergencyMode->activatedByUser)
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        By: {{ $emergencyMode->activatedByUser->name }}
                    </div>
                    @endif
                </div>
                @endif
                
                @if($emergencyMode->deactivated_at)
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Deactivated</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->deactivated_at->format('M d, Y H:i') }}</div>
                    @if($emergencyMode->deactivatedByUser)
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        By: {{ $emergencyMode->deactivatedByUser->name }}
                    </div>
                    @endif
                </div>
                @endif
                
                @if($emergencyMode->scheduled_start)
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Scheduled Start</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->scheduled_start->format('M d, Y H:i') }}</div>
                </div>
                @endif
                
                @if($emergencyMode->scheduled_end)
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Scheduled End</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->scheduled_end->format('M d, Y H:i') }}</div>
                </div>
                @endif
                
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Last Updated</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->updated_at->format('M d, Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deactivation Modal -->
<div id="deactivationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-power-off mr-2" style="color: var(--danger);"></i>
                Deactivate Emergency Mode
            </h3>
            
            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex">
                    <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div>
                        <h4 class="font-medium mb-1" style="color: var(--danger);">Warning</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            This will deactivate the emergency mode and restore normal system operations.
                        </p>
                    </div>
                </div>
            </div>
            
            <form action="{{ route('developer.emergency.deactivate', $emergencyMode->id) }}" method="POST">
                @csrf
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Deactivation Reason <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="deactivation_reason" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you deactivating this emergency mode?"
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Minimum 10 characters
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="confirmDeactivation" name="confirm" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="confirmDeactivation" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            I confirm that I want to deactivate this emergency mode <span style="color: var(--danger);">*</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('deactivationModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-danger flex items-center">
                        <i class="fas fa-power-off mr-2"></i> Deactivate Emergency Mode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extension Modal -->
<div id="extensionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                Extend Emergency Mode
            </h3>
            
            <form action="{{ route('developer.emergency.extend', $emergencyMode->id) }}" method="POST">
                @csrf
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Extension Reason <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="extension_reason" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why do you need to extend this emergency mode?"
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Minimum 10 characters
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Additional Minutes <span style="color: var(--danger);">*</span>
                        </label>
                        <div class="flex items-center space-x-4">
                            <input type="range" id="extension_slider" name="extension_slider" 
                                   min="15" max="1440" value="60"
                                   class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                                   style="background-color: var(--border-color);">
                            <input type="number" id="extension_minutes" name="extension_minutes"
                                   value="60"
                                   class="w-24 p-2 border rounded-lg text-center"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   min="15" max="1440" required>
                            <span class="text-sm" style="color: var(--text-secondary);">minutes</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-xs" style="color: var(--text-secondary);">Quick select:</span>
                            <div class="space-x-2">
                                <button type="button" onclick="setExtension(30)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                    30 min
                                </button>
                                <button type="button" onclick="setExtension(60)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                    1 hour
                                </button>
                                <button type="button" onclick="setExtension(120)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                    2 hours
                                </button>
                                <button type="button" onclick="setExtension(240)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                    4 hours
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('extensionModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-clock mr-2"></i> Extend Emergency Mode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- All Users Modal -->
<div id="allUsersModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-4xl max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-users mr-2"></i>
                All Affected Users ({{ $affectedUsersCount ?? 0 }})
            </h3>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height: calc(80vh - 120px);">
            <!-- Content would be loaded via AJAX or modal content -->
            <div class="text-center py-8">
                <i class="fas fa-users text-4xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p style="color: var(--text-secondary);">
                    User details would be loaded here. This would typically
                    be implemented with pagination or search functionality.
                </p>
            </div>
        </div>
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end">
                <button type="button" onclick="closeModal('allUsersModal')" class="btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function showDeactivationModal() {
    document.getElementById('deactivationModal').classList.remove('hidden');
}

function showExtensionModal() {
    document.getElementById('extensionModal').classList.remove('hidden');
}

function showAllUsersModal() {
    document.getElementById('allUsersModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function setExtension(minutes) {
    document.getElementById('extension_slider').value = minutes;
    document.getElementById('extension_minutes').value = minutes;
}

// Initialize extension slider sync
document.addEventListener('DOMContentLoaded', function() {
    const extensionSlider = document.getElementById('extension_slider');
    const extensionInput = document.getElementById('extension_minutes');
    if (extensionSlider && extensionInput) {
        extensionSlider.addEventListener('input', function() {
            extensionInput.value = this.value;
        });
        
        extensionInput.addEventListener('input', function() {
            extensionSlider.value = this.value;
        });
    }
    
    // Close modals when clicking outside
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
            closeModal('deactivationModal');
            closeModal('extensionModal');
            closeModal('allUsersModal');
        }
    });
    
    // Form validation for deactivation
    const deactivationForm = document.querySelector('form[action*="deactivate"]');
    if (deactivationForm) {
        deactivationForm.addEventListener('submit', function(e) {
            const textarea = this.querySelector('textarea[name="deactivation_reason"]');
            const checkbox = this.querySelector('input[name="confirm"]');
            
            if (!textarea.value.trim() || textarea.value.trim().length < 10) {
                e.preventDefault();
                alert('Please provide a deactivation reason with at least 10 characters.');
                textarea.focus();
                return;
            }
            
            if (!checkbox.checked) {
                e.preventDefault();
                alert('Please confirm that you want to deactivate this emergency mode.');
                checkbox.focus();
                return;
            }
        });
    }
    
    // Form validation for extension
    const extensionForm = document.querySelector('form[action*="extend"]');
    if (extensionForm) {
        extensionForm.addEventListener('submit', function(e) {
            const textarea = this.querySelector('textarea[name="extension_reason"]');
            const minutesInput = this.querySelector('input[name="extension_minutes"]');
            
            if (!textarea.value.trim() || textarea.value.trim().length < 10) {
                e.preventDefault();
                alert('Please provide an extension reason with at least 10 characters.');
                textarea.focus();
                return;
            }
            
            const minutes = parseInt(minutesInput.value);
            if (isNaN(minutes) || minutes < 15 || minutes > 1440) {
                e.preventDefault();
                alert('Please enter a valid duration between 15 and 1440 minutes.');
                minutesInput.focus();
                return;
            }
        });
    }
});
</script>

<style>
/* Custom range slider styling */
input[type="range"] {
    -webkit-appearance: none;
    height: 6px;
    border-radius: 3px;
    outline: none;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Status indicator animations */
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Button styles */
.btn-primary, .btn-secondary, .btn-warning, .btn-danger {
    transition: all 0.2s ease-in-out;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    border: 1px solid var(--warning);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    border: 1px solid var(--danger);
}

.btn-primary:hover, .btn-secondary:hover, .btn-warning:hover, .btn-danger:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}

/* Card styling */
.card {
    background-color: var(--bg-primary);
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    border: 1px solid var(--border-color);
}

/* Responsive design */
@media (max-width: 768px) {
    .grid-cols-1 {
        grid-template-columns: 1fr;
    }
    
    .lg\:col-span-2 {
        grid-column: span 1;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
    
    .p-6 {
        padding: 1rem;
    }
}

/* Hover effects */
.hover\:bg-opacity-10:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

.hover\:underline:hover {
    text-decoration: underline;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
@endsection