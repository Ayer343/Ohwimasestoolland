@extends('layouts.dev')

@section('title', 'Emergency Mode Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Emergency Mode Management</h2>
            <div class="flex space-x-2">
                <a href="{{ route('developer.emergency.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> New Emergency Mode
                </a>
                <a href="{{ route('developer.emergency.statistics') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-chart-bar mr-2"></i> View Statistics
                </a>
            </div>
        </div>
    </div>

    <!-- Active Emergency Banner -->
    @php
        $activeEmergency = \App\Models\EmergencyMode::active()->first();
    @endphp
    
    @if($activeEmergency)
    <div class="card p-6" style="border-left: 6px solid var(--danger); box-shadow: 0 10px 30px rgba(var(--danger-rgb), 0.15);">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between">
            <div class="flex items-center mb-4 lg:mb-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-red-500 to-red-700 animate-pulse mr-4">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold" style="color: var(--text-primary);">🚨 SYSTEM EMERGENCY ACTIVE</h3>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-red-500 text-white flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            ACTIVE
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium text-white"
                              style="background: {{ $activeEmergency->severity_level == 'critical' ? 'var(--danger)' : ($activeEmergency->severity_level == 'high' ? 'var(--warning)' : 'var(--info)') }};">
                            <i class="fas fa-bolt mr-1"></i>
                            {{ strtoupper($activeEmergency->severity_level) }} SEVERITY
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-500 text-white flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            {{ $activeEmergency->current_duration }} minutes
                        </span>
                    </div>
                </div>
            </div>
            <button onclick="showDeactivationModal({{ $activeEmergency->id }})" 
                    class="btn-primary flex items-center">
                <i class="fas fa-power-off mr-2"></i>
                Deactivate Emergency
            </button>
        </div>
        
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeEmergency->current_duration }} minutes
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    Started: {{ $activeEmergency->activated_at->format('M d, H:i') }}
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeEmergency->affected_users_count }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ implode(', ', array_map(fn($module) => str_replace('_', ' ', ucfirst($module)), $activeEmergency->affected_modules)) }}
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Activated By</div>
                <div class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-shield mr-2"></i>
                    {{ $activeEmergency->activatedByUser->name ?? 'System' }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ $activeEmergency->activated_at->format('M d, H:i') }}
                </div>
            </div>
        </div>
        
        <div class="mt-6 p-4 rounded-lg" style="background: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                <div class="flex-1">
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">Emergency Reason:</h4>
                    <p style="color: var(--text-secondary);">{{ $activeEmergency->reason }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Emergency Statistics Cards -->
    @php
        $statistics = \App\Models\EmergencyMode::getStatistics();
    @endphp
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Emergencies</div>
                    <div class="text-2xl font-semibold">{{ $statistics['total'] ?? 0 }}</div>
                </div>
                <i class="fas fa-shield-alt text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Currently Active</div>
                    <div class="text-2xl font-semibold">
                        {{ $activeEmergency ? '1 Active' : '0 Active' }}
                    </div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70 @if($activeEmergency) animate-pulse @endif"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Average Duration</div>
                    <div class="text-2xl font-semibold">{{ $statistics['avg_duration'] ?? 0 }} min</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Critical Severity</div>
                    <div class="text-2xl font-semibold">{{ $statistics['critical_count'] ?? 0 }}</div>
                </div>
                <i class="fas fa-skull-crossbones text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6">
            <form action="{{ route('developer.emergency.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                        <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Status</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="activating" {{ request('status') == 'activating' ? 'selected' : '' }}>Activating</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="deactivating" {{ request('status') == 'deactivating' ? 'selected' : '' }}>Deactivating</option>
                            <option value="recovering" {{ request('status') == 'recovering' ? 'selected' : '' }}>Recovering</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Severity Level</label>
                        <select name="severity" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Severities</option>
                            <option value="low" {{ request('severity') == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ request('severity') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ request('severity') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="critical" {{ request('severity') == 'critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               class="w-full p-2 border rounded" 
                               placeholder="Search name, reference ID, reason..." 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.emergency.index') }}" class="btn-secondary w-full md:w-auto">
                            <i class="fas fa-times mr-2"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Emergency Modes List -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2"></i> All Emergency Modes
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $emergencies->firstItem() ?? 0 }} to {{ $emergencies->lastItem() ?? 0 }} of {{ $emergencies->total() }} records
                    </p>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $emergencies->total() }} total records
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: var(--bg-secondary);">
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Reference ID</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Name</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Severity</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Created</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emergencies as $emergency)
                    <tr class="border-b hover:bg-opacity-5 transition-colors duration-200" 
                        style="border-color: var(--border-color); 
                               {{ $emergency->is_active ? 'background: rgba(var(--danger-rgb), 0.03);' : '' }}">
                        <td class="p-3" style="color: var(--text-primary);">
                            <div class="font-mono text-sm">{{ $emergency->reference_id }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $emergency->created_at->format('M d, Y') }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium" style="color: var(--text-primary);">{{ Str::limit($emergency->name, 50) }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ Str::limit($emergency->reason, 70) }}
                            </div>
                            @if($emergency->scheduled_start)
                            <span class="inline-block px-2 py-0.5 mt-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ $emergency->scheduled_start->format('M d, H:i') }}
                            </span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $emergency->severity_level === 'critical' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         ($emergency->severity_level === 'high' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         ($emergency->severity_level === 'medium' ? 'rgba(var(--warning-rgb), 0.05)' : 
                                                         'rgba(var(--success-rgb), 0.1)')) }};
                                       color: {{ $emergency->severity_level === 'critical' ? 'var(--danger)' : 
                                               ($emergency->severity_level === 'high' ? 'var(--warning)' : 
                                               ($emergency->severity_level === 'medium' ? 'var(--warning)' : 'var(--success)')) }};
                                       border: 1px solid {{ $emergency->severity_level === 'critical' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         ($emergency->severity_level === 'high' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         ($emergency->severity_level === 'medium' ? 'rgba(var(--warning-rgb), 0.2)' : 
                                                         'rgba(var(--success-rgb), 0.3)')) }};">
                                {{ $emergency->severity_level }}
                            </span>
                            @if($emergency->affected_users_count > 0)
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-users mr-1"></i>
                                {{ $emergency->affected_users_count }} users
                            </div>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $emergency->is_active ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         ($emergency->status === 'activating' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         ($emergency->status === 'deactivating' ? 'rgba(var(--warning-rgb), 0.05)' : 
                                                         'rgba(var(--success-rgb), 0.1)')) }};
                                       color: {{ $emergency->is_active ? 'var(--danger)' : 
                                               ($emergency->status === 'activating' ? 'var(--warning)' : 
                                               ($emergency->status === 'deactivating' ? 'var(--warning)' : 'var(--success)')) }};
                                       border: 1px solid {{ $emergency->is_active ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         ($emergency->status === 'activating' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         ($emergency->status === 'deactivating' ? 'rgba(var(--warning-rgb), 0.2)' : 
                                                         'rgba(var(--success-rgb), 0.3)')) }};">
                                {{ str_replace('_', ' ', $emergency->status) }}
                                @if($emergency->activated_at)
                                <br>
                                <span class="text-xs opacity-75">
                                    {{ $emergency->activated_at->format('M d, H:i') }}
                                </span>
                                @endif
                            </span>
                        </td>
                        <td class="p-3" style="color: var(--text-primary);">
                            <div class="text-sm">{{ $emergency->created_at->format('M d, Y') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $emergency->created_at->format('H:i') }}
                            </div>
                            @if($emergency->createdBy)
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user mr-1"></i>
                                {{ $emergency->createdBy->name }}
                            </div>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <a href="{{ route('developer.emergency.show', $emergency->id) }}" 
                                   class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @if(!$emergency->is_active && $emergency->status === 'inactive')
                                <form action="{{ route('developer.emergency.activate', $emergency->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('Activate this emergency mode?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                            title="Activate Now">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                @endif
                                
                                @if($emergency->is_active)
                                <button onclick="showDeactivationModal({{ $emergency->id }})" 
                                        class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                        title="Deactivate">
                                    <i class="fas fa-stop-circle"></i>
                                </button>
                                @endif
                                
                                @if($emergency->is_active)
                                <button onclick="showExtensionModal({{ $emergency->id }})" 
                                        class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                        title="Extend Duration">
                                    <i class="fas fa-clock"></i>
                                </button>
                                @endif
                                
                                @if($emergency->isScheduled())
                                <form action="{{ route('developer.emergency.cancel', $emergency->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('Cancel this scheduled emergency mode?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                            title="Cancel Scheduled">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--text-secondary), 0.1);">
                                <i class="fas fa-shield-alt" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                            </div>
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Emergency Modes Found</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                {{ request()->hasAny(['status', 'severity', 'search']) ? 'No emergency modes match your filters.' : 'No emergency modes have been created yet.' }}
                            </p>
                            @if(request()->hasAny(['status', 'severity', 'search']))
                            <a href="{{ route('developer.emergency.index') }}" class="btn-primary">
                                <i class="fas fa-times mr-2"></i> Clear Filters
                            </a>
                            @else
                            <a href="{{ route('developer.emergency.create') }}" class="btn-primary">
                                <i class="fas fa-plus-circle mr-2"></i> Create Emergency Mode
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($emergencies->hasPages())
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            {{ $emergencies->links() }}
        </div>
        @endif
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
            
            <form id="deactivationForm" method="POST">
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
                    <button type="submit" class="btn-primary flex items-center" style="background-color: var(--danger);">
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
            
            <form id="extensionForm" method="POST">
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup filter form auto-submit on select changes
    document.querySelectorAll('select[name="status"], select[name="severity"]').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Setup extension slider sync
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
});

function showDeactivationModal(emergencyId) {
    const form = document.getElementById('deactivationForm');
    form.action = `/developer/emergency/${emergencyId}/deactivate`;
    document.getElementById('deactivationModal').classList.remove('hidden');
}

function showExtensionModal(emergencyId) {
    const form = document.getElementById('extensionForm');
    form.action = `/developer/emergency/${emergencyId}/extend`;
    document.getElementById('extensionModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function setExtension(minutes) {
    document.getElementById('extension_slider').value = minutes;
    document.getElementById('extension_minutes').value = minutes;
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
        closeModal('deactivationModal');
        closeModal('extensionModal');
    }
});

// Form validation for deactivation
document.getElementById('deactivationForm')?.addEventListener('submit', function(e) {
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

// Form validation for extension
document.getElementById('extensionForm')?.addEventListener('submit', function(e) {
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
        opacity: 0.5;
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
}

/* Button hover effects */
.btn-primary, .btn-secondary {
    transition: all 0.2s ease-in-out;
}

.btn-primary:hover, .btn-secondary:hover {
    transform: translateY(-1px);
}

/* Table row hover effect */
tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}
</style>
@endsection