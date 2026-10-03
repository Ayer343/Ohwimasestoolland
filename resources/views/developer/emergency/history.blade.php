@extends('layouts.dev')

@section('title', 'Emergency Mode History')

@section('content')
<div class="grid grid-cols-1 gap-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row md:items-center justify-between p-6">
            <div>
                <h2 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> Emergency Mode History
                </h2>
                <div class="flex items-center space-x-4 text-sm" style="color: var(--text-secondary);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>{{ $emergencyMode->name }}</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-hashtag mr-2"></i>
                        <span class="font-mono">{{ $emergencyMode->reference_id }}</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span>{{ $emergencyMode->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 md:mt-0 flex space-x-2">
                <a href="{{ route('developer.emergency.show', $emergencyMode->id) }}" 
                   class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                </a>
                <a href="{{ route('developer.emergency.index') }}" 
                   class="btn-primary flex items-center">
                    <i class="fas fa-list mr-2"></i> All Emergencies
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Bar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Logs</div>
                    <div class="text-xl font-semibold" style="color: var(--text-primary);">
                        {{ $logs ? $logs->total() : 0 }}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Unique Users</div>
                    <div class="text-xl font-semibold" style="color: var(--text-primary);">
                        {{ $logs ? $logs->unique('performed_by')->count() : 0 }}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4"
                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-calendar"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Time Span</div>
                    <div class="text-xl font-semibold" style="color: var(--text-primary);">
                        @if($logs && $logs->count() > 0)
                            {{ $logs->first()->created_at->diffInDays($logs->last()->created_at) + 1 }} days
                        @else
                            0 days
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Last Activity</div>
                    <div class="text-xl font-semibold" style="color: var(--text-primary);">
                        @if($logs && $logs->count() > 0)
                            {{ $logs->first()->created_at->diffForHumans() }}
                        @else
                            Never
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6">
            <form action="{{ route('developer.emergency.history', $emergencyMode->id) }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Action Type</label>
                        <select name="action" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Actions</option>
                            <option value="created" {{ request('action') == 'created' ? 'selected' : '' }}>Created</option>
                            <option value="updated" {{ request('action') == 'updated' ? 'selected' : '' }}>Updated</option>
                            <option value="activation_started" {{ request('action') == 'activation_started' ? 'selected' : '' }}>Activation Started</option>
                            <option value="activated" {{ request('action') == 'activated' ? 'selected' : '' }}>Activated</option>
                            <option value="activation_failed" {{ request('action') == 'activation_failed' ? 'selected' : '' }}>Activation Failed</option>
                            <option value="deactivation_started" {{ request('action') == 'deactivation_started' ? 'selected' : '' }}>Deactivation Started</option>
                            <option value="deactivated" {{ request('action') == 'deactivated' ? 'selected' : '' }}>Deactivated</option>
                            <option value="deactivation_failed" {{ request('action') == 'deactivation_failed' ? 'selected' : '' }}>Deactivation Failed</option>
                            <option value="extended" {{ request('action') == 'extended' ? 'selected' : '' }}>Extended</option>
                            <option value="cancelled" {{ request('action') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">User</label>
                        <select name="user" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Users</option>
                            @php
                                $uniqueUsers = $logs ? $logs->pluck('performer')->unique()->filter() : collect();
                            @endphp
                            @foreach($uniqueUsers as $user)
                                <option value="{{ $user->id }}" {{ request('user') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}" class="btn-secondary w-full md:w-auto">
                            <i class="fas fa-times mr-2"></i> Clear
                        </a>
                    </div>
                </div>
                
                @if(request()->hasAny(['action', 'user', 'date_from']))
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        Showing {{ $logs ? $logs->total() : 0 }} logs matching your filters
                    </div>
                </div>
                @endif
            </form>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-stream mr-2"></i> Activity Timeline
                <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                    ({{ $logs ? $logs->count() : 0 }} activities)
                </span>
            </h3>
        </div>
        
        @if($logs && $logs->count() > 0)
        <div class="p-6">
            <!-- Timeline -->
            <div class="relative">
                <!-- Timeline line -->
                <div class="absolute left-4 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                
                <div class="space-y-8 ml-10">
                    @foreach($logs as $log)
                    <div class="relative">
                        <!-- Timeline dot -->
                        <div class="absolute -left-6 top-0 w-8 h-8 rounded-full flex items-center justify-center border-2 border-white shadow"
                             style="background-color: {{ $log->action === 'activated' || $log->action === 'activation_started' ? 'var(--warning)' : 
                                                       ($log->action === 'deactivated' ? 'var(--success)' : 
                                                       ($log->action === 'activation_failed' || $log->action === 'deactivation_failed' ? 'var(--danger)' : 
                                                       ($log->action === 'created' ? 'var(--info)' : 
                                                       ($log->action === 'extended' ? 'var(--primary)' : 'var(--text-secondary)')))) }};">
                            <i class="fas {{ $log->action === 'activated' || $log->action === 'activation_started' ? 'fa-play' : 
                                          ($log->action === 'deactivated' ? 'fa-stop' : 
                                          ($log->action === 'activation_failed' || $log->action === 'deactivation_failed' ? 'fa-exclamation-triangle' : 
                                          ($log->action === 'created' ? 'fa-plus' : 
                                          ($log->action === 'extended' ? 'fa-clock' : 'fa-info-circle')))) }} text-white text-sm"></i>
                        </div>
                        
                        <!-- Log Card -->
                        <div class="card hover:shadow-md transition-shadow duration-200">
                            <div class="p-4">
                                <div class="flex flex-col md:flex-row md:items-center justify-between mb-3">
                                    <div class="flex items-center mb-2 md:mb-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                             style="background-color: rgba(var(--{{ $log->severity_class }}-rgb), 0.1); color: var(--{{ $log->severity_class }});">
                                            <i class="fas fa-{{ in_array($log->action, ['activated', 'activation_started']) ? 'play' : 
                                                               (in_array($log->action, ['deactivated', 'deactivation_started']) ? 'stop' : 
                                                               ($log->action == 'extended' ? 'clock' : 'info-circle')) }}"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold" style="color: var(--text-primary);">
                                                {{ $log->action_name }}
                                            </div>
                                            <div class="text-xs flex items-center" style="color: var(--text-secondary);">
                                                <i class="far fa-clock mr-1"></i>
                                                {{ $log->created_at->format('M d, Y H:i:s') }}
                                                <span class="mx-2">•</span>
                                                {{ $log->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        @if($log->performer)
                                        <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-2"></i>
                                            {{ $log->performer->name }}
                                        </div>
                                        @endif
                                        
                                        <span class="px-2 py-1 text-xs rounded-full capitalize"
                                            style="background-color: {{ $log->action === 'activated' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                                     ($log->action === 'deactivated' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                                     ($log->action === 'activation_failed' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                                     ($log->action === 'deactivation_failed' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                                     ($log->action === 'created' ? 'rgba(var(--info-rgb), 0.1)' : 
                                                                     ($log->action === 'extended' ? 'rgba(var(--primary-rgb), 0.1)' : 
                                                                     'rgba(var(--secondary-rgb), 0.1)'))))) }};
                                                   color: {{ $log->action === 'activated' ? 'var(--warning)' : 
                                                           ($log->action === 'deactivated' ? 'var(--success)' : 
                                                           ($log->action === 'activation_failed' ? 'var(--danger)' : 
                                                           ($log->action === 'deactivation_failed' ? 'var(--danger)' : 
                                                           ($log->action === 'created' ? 'var(--info)' : 
                                                           ($log->action === 'extended' ? 'var(--primary)' : 'var(--text-secondary)'))))) }};">
                                            {{ $log->action }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Details -->
                                <div class="mb-3">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $log->details }}</div>
                                </div>
                                
                                <!-- Metadata -->
                                @if($log->metadata && count($log->metadata) > 0)
                                <div class="border rounded-lg overflow-hidden" style="border-color: var(--border-color);">
                                    <div class="px-3 py-2 text-xs font-medium" 
                                         style="background-color: var(--bg-secondary); color: var(--text-secondary); border-bottom: 1px solid var(--border-color);">
                                        <i class="fas fa-info-circle mr-1"></i> Additional Information
                                    </div>
                                    <div class="p-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                            @foreach($log->metadata as $key => $value)
                                            <div class="flex">
                                                <div class="w-32 flex-shrink-0 font-medium" style="color: var(--text-secondary);">
                                                    {{ ucfirst(str_replace('_', ' ', $key)) }}:
                                                </div>
                                                <div class="flex-1" style="color: var(--text-primary);">
                                                    @if(is_array($value))
                                                        @if(isset($value['step']))
                                                            <!-- For activation/deactivation logs -->
                                                            <div class="space-y-2">
                                                                <div class="font-medium">{{ $value['step'] ?? 'Step' }}</div>
                                                                @if(isset($value['status']))
                                                                <span class="inline-block px-2 py-0.5 text-xs rounded" 
                                                                      style="background-color: {{ $value['status'] == 'completed' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                                                               ($value['status'] == 'failed' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                                                               'rgba(var(--warning-rgb), 0.1)') }};
                                                                             color: {{ $value['status'] == 'completed' ? 'var(--success)' : 
                                                                                     ($value['status'] == 'failed' ? 'var(--danger)' : 'var(--warning)') }};">
                                                                    {{ $value['status'] }}
                                                                </span>
                                                                @endif
                                                                @if(isset($value['timestamp']))
                                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                                    {{ $value['timestamp'] }}
                                                                </div>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="space-y-1">
                                                                @foreach($value as $subKey => $subValue)
                                                                <div>
                                                                    <span class="font-medium">{{ $subKey }}:</span>
                                                                    <span>{{ is_array($subValue) ? json_encode($subValue) : $subValue }}</span>
                                                                </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                <!-- Technical Details -->
                                <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                                        @if($log->ip_address)
                                        <div class="flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-network-wired mr-2"></i>
                                            <span class="truncate">{{ $log->ip_address }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($log->user_agent)
                                        <div class="md:col-span-2 flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-desktop mr-2"></i>
                                            <span class="truncate">{{ Str::limit($log->user_agent, 50) }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($log->performer && $log->performer->id)
                                        <div class="flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-id-badge mr-2"></i>
                                            <span>User ID: {{ $log->performer->id }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Pagination -->
            @if($logs->hasPages())
            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                        Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} entries
                    </div>
                    {{ $logs->links() }}
                </div>
            </div>
            @endif
        </div>
        @else
        <div class="p-12 text-center">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center"
                 style="background-color: rgba(var(--text-secondary), 0.1);">
                <i class="fas fa-history text-3xl" style="color: var(--text-secondary);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Activity Logs Found</h3>
            <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                @if(request()->hasAny(['action', 'user', 'date_from']))
                No activity logs match your current filters. Try adjusting your filter criteria.
                @else
                No activity has been recorded for this emergency mode yet. Activity logs will appear here when actions are taken on this emergency mode.
                @endif
            </p>
            @if(request()->hasAny(['action', 'user', 'date_from']))
            <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}" class="btn-primary">
                <i class="fas fa-times mr-2"></i> Clear Filters
            </a>
            @endif
        </div>
        @endif
    </div>

    <!-- Statistics Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2"></i> Activity Statistics
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Action Distribution -->
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">Action Distribution</h4>
                    @php
                        $actionGroups = $logs ? $logs->groupBy('action')->map->count() : collect();
                        $totalActions = $logs ? $logs->count() : 0;
                    @endphp
                    
                    @if($totalActions > 0)
                    <div class="space-y-3">
                        @foreach($actionGroups as $action => $count)
                        @php
                            $percentage = ($count / $totalActions) * 100;
                            $color = $action === 'activated' ? 'warning' : 
                                    ($action === 'deactivated' ? 'success' : 
                                    ($action === 'activation_failed' ? 'danger' : 
                                    ($action === 'deactivation_failed' ? 'danger' : 
                                    ($action === 'created' ? 'info' : 
                                    ($action === 'extended' ? 'primary' : 'secondary')))));
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-primary);">
                                    {{ ucfirst(str_replace('_', ' ', $action)) }}
                                </span>
                                <span style="color: var(--text-secondary);">
                                    {{ $count }} ({{ number_format($percentage, 1) }}%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full" 
                                     style="background-color: var(--{{ $color }}); width: {{ $percentage }}%;">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-8">
                        <i class="fas fa-chart-bar text-3xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No data available</p>
                    </div>
                    @endif
                </div>
                
                <!-- User Activity -->
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">User Activity</h4>
                    @php
                        $userGroups = $logs ? $logs->groupBy('performed_by')->map->count()->sortDesc() : collect();
                    @endphp
                    
                    @if($userGroups->count() > 0)
                    <div class="space-y-4">
                        @foreach($userGroups->take(5) as $userId => $count)
                        @php
                            $user = $logs ? $logs->firstWhere('performed_by', $userId)->performer : null;
                            $percentage = ($count / $totalActions) * 100;
                        @endphp
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                @if($user && $user->profile_photo_path)
                                <img src="{{ asset($user->profile_photo_path) }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full">
                                @else
                                <i class="fas fa-user text-gray-500"></i>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between mb-1">
                                    <span style="color: var(--text-primary);">
                                        {{ $user ? $user->name : 'User #' . $userId }}
                                    </span>
                                    <span style="color: var(--text-secondary);">{{ $count }} actions</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-blue-500" style="width: {{ $percentage }}%;"></div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        
                        @if($userGroups->count() > 5)
                        <div class="text-center pt-2">
                            <a href="#" class="text-sm inline-flex items-center hover:underline" style="color: var(--primary);">
                                View all {{ $userGroups->count() }} users
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="text-center py-8">
                        <i class="fas fa-users text-3xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No user activity recorded</p>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Timeline Chart -->
            <div class="mt-8 pt-8 border-t" style="border-color: var(--border-color);">
                <h4 class="font-medium mb-4" style="color: var(--text-primary);">Activity Timeline</h4>
                @if($logs && $logs->count() > 0)
                <div class="overflow-x-auto">
                    <div class="min-w-max">
                        @php
                            $groupedByDate = $logs->groupBy(function($log) {
                                return $log->created_at->format('Y-m-d');
                            })->map->count();
                            
                            $dates = collect();
                            $startDate = $logs->last()->created_at->copy()->startOfDay();
                            $endDate = $logs->first()->created_at->copy()->endOfDay();
                            $diffDays = $startDate->diffInDays($endDate);
                            
                            for($i = 0; $i <= min($diffDays, 30); $i++) {
                                $date = $startDate->copy()->addDays($i)->format('Y-m-d');
                                $dates[$date] = $groupedByDate[$date] ?? 0;
                            }
                        @endphp
                        
                        <div class="h-48 flex items-end space-x-1">
                            @foreach($dates as $date => $count)
                            @php
                                $maxCount = max(1, $dates->max());
                                $height = ($count / $maxCount) * 100;
                                $dateObj = \Carbon\Carbon::parse($date);
                            @endphp
                            <div class="flex flex-col items-center">
                                <div class="w-8 rounded-t-md transition-all duration-300 hover:opacity-80 cursor-pointer"
                                     style="background-color: var(--{{ $count > 0 ? 'primary' : 'border-color' }}); height: {{ $height }}%;"
                                     title="{{ $dateObj->format('M d, Y') }}: {{ $count }} activities">
                                </div>
                                <div class="text-xs mt-2 {{ $dateObj->day % 7 === 0 ? 'font-semibold' : '' }}" 
                                     style="color: var(--text-secondary); transform: rotate(-45deg) translateX(-10px);">
                                    {{ $dateObj->format('d') }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4 text-xs text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Shows activity distribution over the last {{ min($diffDays + 1, 30) }} days
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-8">
                    <i class="fas fa-chart-line text-3xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No timeline data available</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Export Section -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-download mr-2"></i> Export Activity Logs
                    </h3>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Export activity logs for analysis or record keeping.
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0 flex space-x-2">
                    <button onclick="exportAsCSV()" class="btn-secondary flex items-center">
                        <i class="fas fa-file-csv mr-2"></i> Export as CSV
                    </button>
                    <button onclick="exportAsJSON()" class="btn-primary flex items-center">
                        <i class="fas fa-file-code mr-2"></i> Export as JSON
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modals -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-download mr-2"></i> Export Activity Logs
            </h3>
            
            <div class="mb-6">
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Preparing export for <strong>{{ $emergencyMode->name }}</strong> ({{ $logs ? $logs->total() : 0 }} logs).
                </p>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="checkbox" id="includeMetadata" checked
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="includeMetadata" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Include metadata
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="includeTechnical" checked
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="includeTechnical" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Include technical details (IP, User Agent)
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="includeAllPages"
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="includeAllPages" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Export all pages (not just current view)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal('exportModal')" class="btn-secondary">
                    Cancel
                </button>
                <button id="confirmExport" class="btn-primary flex items-center">
                    <i class="fas fa-download mr-2"></i> Download Export
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let exportFormat = 'csv';

function exportAsCSV() {
    exportFormat = 'csv';
    document.getElementById('exportModal').classList.remove('hidden');
}

function exportAsJSON() {
    exportFormat = 'json';
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Handle export confirmation
document.getElementById('confirmExport')?.addEventListener('click', function() {
    const includeMetadata = document.getElementById('includeMetadata').checked;
    const includeTechnical = document.getElementById('includeTechnical').checked;
    const includeAllPages = document.getElementById('includeAllPages').checked;
    
    // Build export URL
    let exportUrl = `/developer/emergency/{{ $emergencyMode->id }}/export/${exportFormat}`;
    const params = new URLSearchParams({
        metadata: includeMetadata ? '1' : '0',
        technical: includeTechnical ? '1' : '0',
        all: includeAllPages ? '1' : '0'
    });
    
    exportUrl += '?' + params.toString();
    
    // Download the file
    window.location.href = exportUrl;
    
    // Close modal
    closeModal('exportModal');
});

// Auto-submit filters on select changes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name="action"], select[name="user"]').forEach(select => {
        select.addEventListener('change', function() {
            if (this.value) {
                this.closest('form').submit();
            }
        });
    });
    
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
            closeModal('exportModal');
        }
    });
    
    // Filter auto-submit for date input
    const dateInput = document.querySelector('input[name="date_from"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            if (this.value) {
                this.closest('form').submit();
            }
        });
    }
});
</script>

<style>
/* Timeline styling */
.relative .absolute {
    z-index: 10;
}

.card.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Progress bar animation */
@keyframes growWidth {
    from { width: 0%; }
    to { width: 100%; }
}

.w-full.bg-gray-200 .h-2 {
    animation: growWidth 1s ease-out;
}

/* Date picker styling */
input[type="date"] {
    position: relative;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 0.6;
    cursor: pointer;
}

/* Pagination styling */
.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
}

.pagination li {
    margin: 0 2px;
}

.pagination li a,
.pagination li span {
    display: block;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    text-decoration: none;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.pagination li a:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
}

.pagination li.active span {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination li.disabled span {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: var(--bg-secondary);
}

/* Scrollbar styling for overflow */
.overflow-x-auto {
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) var(--bg-secondary);
}

.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background-color: var(--text-secondary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1 {
        grid-template-columns: 1fr;
    }
    
    .md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .ml-10 {
        margin-left: 2.5rem;
    }
    
    .text-xl {
        font-size: 1.25rem;
    }
    
    .p-6 {
        padding: 1rem;
    }
}

/* Hover effects for timeline items */
.relative:hover .absolute {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}

/* Print styles */
@media print {
    .btn-primary, .btn-secondary, .btn-warning, .btn-danger,
    .pagination, #exportModal, .card:last-child {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
    
    h1, h2, h3, h4 {
        break-after: avoid;
    }
}
</style>
@endsection