@extends('layouts.dev')

@section('title', 'Edit Maintenance Schedule')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-edit mr-2"></i> Edit Maintenance Schedule
                </h1>
                <div class="flex items-center space-x-4 text-sm">
                    <span class="px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        ID: {{ $maintenance->reference_id }}
                    </span>
                    <span class="px-2 py-1 rounded capitalize" 
                          style="background-color: {{ $maintenance->status_color }}; color: white;">
                        {{ str_replace('_', ' ', $maintenance->status) }}
                    </span>
                    @if($maintenance->is_emergency)
                        <span class="px-2 py-1 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Emergency
                        </span>
                    @endif
                </div>
            </div>
            
            <div class="text-sm" style="color: var(--text-secondary);">
                Created: {{ $maintenance->created_at->format('M d, Y H:i') }}
                @if($maintenance->updated_at)
                    <br>Last Updated: {{ $maintenance->updated_at->format('M d, Y H:i') }}
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
    <div class="card mb-6">
        <div class="p-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-lightbulb mr-3 text-lg" style="color: var(--warning);"></i>
                <div>
                    <h3 class="font-medium" style="color: var(--text-primary);">Quick Actions</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Available actions for this maintenance schedule
                    </p>
                </div>
            </div>
            
            <div class="flex space-x-2">
                @if($maintenance->status === App\Models\Maintenance::STATUS_DRAFT)
                    <a href="{{ route('developer.maintenance.approve', $maintenance->id) }}" 
                       class="btn-success flex items-center"
                       onclick="return confirm('Are you sure you want to approve this maintenance schedule?')">
                        <i class="fas fa-check-circle mr-2"></i> Approve
                    </a>
                @endif
                
                @if($maintenance->status === App\Models\Maintenance::STATUS_SCHEDULED)
                    <a href="{{ route('developer.maintenance.start', $maintenance->id) }}" 
                       class="btn-warning flex items-center"
                       onclick="return confirm('Are you sure you want to start this maintenance? This will notify users.')">
                        <i class="fas fa-play mr-2"></i> Start Now
                    </a>
                @endif
                
                <button type="button" onclick="showCancelModal()" class="btn-danger flex items-center">
                    <i class="fas fa-times-circle mr-2"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Status Alert -->
    @if(!in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
    <div class="card mb-6" style="border-left: 4px solid var(--warning);">
        <div class="p-4 flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-lg" style="color: var(--warning);"></i>
            <div>
                <h3 class="font-medium" style="color: var(--warning);">Limited Editing</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    This maintenance schedule is {{ str_replace('_', ' ', $maintenance->status) }} and cannot be edited. 
                    You can only view the details or cancel if applicable.
                </p>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('developer.maintenance.update', $maintenance->id) }}" method="POST" id="maintenanceForm">
        @csrf
        @method('PUT')
        
        <!-- Update Notes (Always visible) -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2"></i> Update Notes
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Explain what changes you're making (required for audit trail)
                </p>
            </div>
            <div class="p-6">
                <textarea id="update_notes" name="update_notes" rows="3"
                          class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Describe the changes you're making and why..."
                          required minlength="10">{{ old('update_notes') }}</textarea>
                <div class="flex justify-between mt-1">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        Minimum 10 characters - This will be logged in the maintenance history
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary);" id="notesCounter">
                        {{ strlen(old('update_notes') ?? '') }}/500
                    </div>
                </div>
                @error('update_notes')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Basic Information Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Update essential details about this maintenance event
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Maintenance Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" 
                           value="{{ old('title', $maintenance->title) }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Database Optimization, Security Patch Update, System Upgrade"
                           {{ !in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]) ? 'disabled' : '' }}
                           required maxlength="255">
                    @error('title')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Provide a brief description of what this maintenance entails..."
                              {{ !in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]) ? 'disabled' : '' }}
                              required minlength="20">{{ old('description', $maintenance->description) }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Minimum 20 characters
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="descriptionCounter">
                            {{ strlen(old('description', $maintenance->description) ?? '') }}/500
                        </div>
                    </div>
                    @error('description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Technical Details -->
                <div>
                    <label for="technical_details" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Technical Details (Optional)
                    </label>
                    <textarea id="technical_details" name="technical_details" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Technical specifications, database changes, API modifications, etc."
                              {{ !in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]) ? 'disabled' : '' }}>{{ old('technical_details', $maintenance->technical_details) }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal use only - not shown to users
                    </div>
                </div>
                
                <!-- Maintenance Type and Impact -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Maintenance Type (Read-only if not draft/scheduled) -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Maintenance Type
                        </label>
                        @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                            <select id="maintenance_type" name="maintenance_type"
                                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @foreach($maintenanceTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('maintenance_type', $maintenance->maintenance_type) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ $maintenanceTypes[$maintenance->maintenance_type] ?? ucfirst($maintenance->maintenance_type) }}
                            </div>
                        @endif
                    </div>
                    
                    <!-- Impact Level (Read-only if not draft/scheduled) -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Impact Level
                        </label>
                        @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                            <select id="impact_level" name="impact_level"
                                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                @foreach($impactLevels as $value => $label)
                                    <option value="{{ $value }}" {{ old('impact_level', $maintenance->impact_level) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <div class="p-3 border rounded-lg capitalize" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ $impactLevels[$maintenance->impact_level] ?? ucfirst($maintenance->impact_level) }}
                            </div>
                        @endif
                        
                        <!-- Impact Level Description -->
                        @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                        <div class="mt-4 space-y-2">
                            <div class="text-xs" id="impactDescription">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Emergency Status (Read-only) -->
                @if($maintenance->is_emergency)
                <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--danger);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--danger);">
                                <i class="fas fa-bolt mr-1"></i> Emergency Maintenance
                            </h4>
                            @if($maintenance->emergency_reason)
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <strong>Reason:</strong> {{ $maintenance->emergency_reason }}
                                </p>
                            @endif
                            @if($maintenance->relatedEmergency)
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    <strong>Linked to Emergency:</strong> 
                                    {{ $maintenance->relatedEmergency->reference_id }} - {{ $maintenance->relatedEmergency->name }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Scheduling Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2"></i> Scheduling
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Update the maintenance window and estimated duration
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Date and Time -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Scheduled Start -->
                    <div>
                        <label for="scheduled_start" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Start Date & Time <span class="text-red-500">*</span>
                        </label>
                        @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                            <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                                   value="{{ old('scheduled_start', \Carbon\Carbon::parse($maintenance->scheduled_start)->format('Y-m-d\TH:i')) }}"
                                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Current: {{ $maintenance->scheduled_start->format('M d, Y H:i') }}
                            </div>
                        @else
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ $maintenance->scheduled_start->format('M d, Y H:i') }}
                                @if($maintenance->actual_start)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Actual Start: {{ $maintenance->actual_start->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('scheduled_start')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Scheduled End -->
                    <div>
                        <label for="scheduled_end" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            End Date & Time <span class="text-red-500">*</span>
                        </label>
                        @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                            <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                                   value="{{ old('scheduled_end', \Carbon\Carbon::parse($maintenance->scheduled_end)->format('Y-m-d\TH:i')) }}"
                                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Current: {{ $maintenance->scheduled_end->format('M d, Y H:i') }}
                            </div>
                        @else
                            <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                {{ $maintenance->scheduled_end->format('M d, Y H:i') }}
                                @if($maintenance->actual_end)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Actual End: {{ $maintenance->actual_end->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('scheduled_end')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Duration -->
                @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                <div>
                    <label for="estimated_duration_minutes" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Estimated Duration (minutes) <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center space-x-4">
                        <input type="range" id="duration_slider" name="duration_slider" 
                               min="1" max="1440" value="{{ old('estimated_duration_minutes', $maintenance->estimated_duration_minutes) }}"
                               class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                               style="background-color: var(--border-color);">
                        <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes"
                               value="{{ old('estimated_duration_minutes', $maintenance->estimated_duration_minutes) }}"
                               class="w-24 p-2 border rounded-lg text-center"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="1" max="1440" required>
                        <span class="text-sm" style="color: var(--text-secondary);">minutes</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs" style="color: var(--text-secondary);">Quick select:</span>
                        <div class="space-x-2">
                            <button type="button" onclick="setDuration(30)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                30 min
                            </button>
                            <button type="button" onclick="setDuration(60)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                1 hour
                            </button>
                            <button type="button" onclick="setDuration(120)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                2 hours
                            </button>
                            <button type="button" onclick="setDuration(240)" class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                4 hours
                            </button>
                        </div>
                    </div>
                    @error('estimated_duration_minutes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Duration
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">Estimated</div>
                            <div class="font-medium">{{ $maintenance->estimated_duration_minutes }} minutes</div>
                        </div>
                        @if($maintenance->actual_duration_minutes)
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">Actual</div>
                            <div class="font-medium">{{ $maintenance->actual_duration_minutes }} minutes</div>
                            @if($maintenance->completed_within_estimate)
                                <div class="text-xs text-green-500 mt-1">Within estimate</div>
                            @else
                                <div class="text-xs text-red-500 mt-1">Over estimate</div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                
                <!-- Timezone Info -->
                <div class="p-3 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                    <div class="flex items-center">
                        <i class="fas fa-globe-americas mr-2" style="color: var(--info);"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">
                            All times are displayed in <span class="font-medium">{{ config('app.timezone') }}</span> timezone
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Impact Assessment Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Impact Assessment
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review what will be affected during maintenance
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- User Impact Description -->
                <div>
                    <label for="user_impact_description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        User Impact Description <span class="text-red-500">*</span>
                    </label>
                    @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                        <textarea id="user_impact_description" name="user_impact_description" rows="3"
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe the impact from the user's perspective. What will they experience?"
                                  required minlength="20">{{ old('user_impact_description', $maintenance->user_impact_description) }}</textarea>
                        <div class="flex justify-between mt-1">
                            <div class="text-xs" style="color: var(--text-secondary);">
                                Minimum 20 characters - This will be shown to users
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);" id="impactCounter">
                                {{ strlen(old('user_impact_description', $maintenance->user_impact_description) ?? '') }}/500
                            </div>
                        </div>
                    @else
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            {{ $maintenance->user_impact_description }}
                        </div>
                    @endif
                    @error('user_impact_description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Affected Modules -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules
                    </label>
                    @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($defaultModules as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox" id="module_{{ $value }}" name="affected_modules[]"
                                       value="{{ $value }}"
                                       {{ in_array($value, old('affected_modules', $maintenance->affected_modules ?? [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="module_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        
                        <!-- Quick Select Buttons -->
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" onclick="selectAllModules()" class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-check-square mr-1"></i> Select All
                            </button>
                            <button type="button" onclick="deselectAllModules()" class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times-circle mr-1"></i> Deselect All
                            </button>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($defaultModules as $value => $label)
                                @if(in_array($value, $maintenance->affected_modules ?? []))
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                    <span style="color: var(--text-primary);">{{ $label }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    @error('affected_modules')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Affected User Types -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected User Types
                    </label>
                    @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($userTypes as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox" id="user_type_{{ $value }}" name="affected_user_types[]"
                                       value="{{ $value }}"
                                       {{ in_array($value, old('affected_user_types', $maintenance->affected_user_types ?? [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="user_type_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Estimated affected users: <span id="estimatedUsersCount">{{ $maintenance->estimated_affected_users }}</span>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($userTypes as $value => $label)
                                @if(in_array($value, $maintenance->affected_user_types ?? []))
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);">{{ $label }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        
                        @if($maintenance->actual_affected_users)
                        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-chart-bar mr-1"></i>
                            Actual affected users: {{ $maintenance->actual_affected_users }}
                        </div>
                        @endif
                    @endif
                    @error('affected_user_types')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Notification Settings Card (Read-only for non-draft/scheduled) -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2"></i> Notification Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Notification configuration for this maintenance
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Notify Users -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            User Notifications
                        </label>
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="flex items-center">
                                @if($maintenance->notify_users)
                                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                    <span>Users will be notified</span>
                                @else
                                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i>
                                    <span>Users will not be notified</span>
                                @endif
                            </div>
                            @if($maintenance->notification_sent_at)
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Notifications sent: {{ $maintenance->notification_sent_at->format('M d, Y H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Notification Channels -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Notification Channels
                        </label>
                        <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @if($maintenance->notification_channels && count($maintenance->notification_channels) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($maintenance->notification_channels as $channel)
                                    <span class="px-2 py-1 text-xs rounded-full capitalize" 
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        @switch($channel)
                                            @case('email') <i class="fas fa-envelope mr-1"></i> Email @break
                                            @case('sms') <i class="fas fa-comment-alt mr-1"></i> SMS @break
                                            @case('whatsapp') <i class="fab fa-whatsapp mr-1"></i> WhatsApp @break
                                            @case('in_app') <i class="fas fa-mobile-alt mr-1"></i> In-App @break
                                            @default {{ $channel }}
                                        @endswitch
                                    </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">No channels configured</span>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Rollback Plan -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Rollback Plan
                    </label>
                    <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <div class="flex items-center">
                            @if($maintenance->has_rollback_plan)
                                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                <span>Rollback plan exists</span>
                            @else
                                <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i>
                                <span>No rollback plan</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-check mr-2"></i> Summary
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review all information before updating
                </p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Maintenance Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Reference:</span>
                                <span style="color: var(--text-primary); font-weight: 500;">{{ $maintenance->reference_id }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Status:</span>
                                <span class="px-2 py-1 text-xs rounded-full capitalize" 
                                      style="background-color: {{ $maintenance->status_color }}; color: white;">
                                    {{ str_replace('_', ' ', $maintenance->status) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Type:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryType">
                                    {{ $maintenanceTypes[$maintenance->maintenance_type] ?? ucfirst($maintenance->maintenance_type) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Impact Level:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryImpact">
                                    {{ $impactLevels[$maintenance->impact_level] ?? ucfirst($maintenance->impact_level) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Schedule</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Start:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryStart">
                                    {{ $maintenance->scheduled_start->format('M d, Y H:i') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Duration:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryDuration">
                                    {{ $maintenance->estimated_duration_minutes }} minutes
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Affected Users:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryUsers">
                                    {{ number_format($maintenance->estimated_affected_users) }} estimated
                                </span>
                            </div>
                            @if($maintenance->actual_affected_users)
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Actual Affected:</span>
                                <span style="color: var(--text-primary); font-weight: 500;">
                                    {{ number_format($maintenance->actual_affected_users) }}
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Change Warning -->
                @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_SCHEDULED]))
                <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--warning);">Update Warning</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Changing a scheduled maintenance will trigger notifications to affected users about the schedule change. 
                                Only make changes if absolutely necessary.
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="p-6 flex justify-between items-center">
                <div class="flex space-x-3">
                    <a href="{{ route('developer.maintenance.show', $maintenance->id) }}" class="btn-secondary flex items-center">
                        <i class="fas fa-eye mr-2"></i> View Details
                    </a>
                    <a href="{{ route('developer.maintenance.index') }}" class="btn-secondary flex items-center">
                        <i class="fas fa-list mr-2"></i> Back to List
                    </a>
                </div>
                
                @if(in_array($maintenance->status, [App\Models\Maintenance::STATUS_DRAFT, App\Models\Maintenance::STATUS_SCHEDULED]))
                <div class="flex space-x-3">
                    <button type="button" onclick="showPreview()" class="btn-secondary flex items-center">
                        <i class="fas fa-eye mr-2"></i> Preview Changes
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-save mr-2"></i> Update Maintenance
                    </button>
                </div>
                @else
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-lock mr-1"></i> Editing disabled for completed/cancelled maintenance
                </div>
                @endif
            </div>
        </div>
    </form>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Maintenance
                </h3>
                <button type="button" onclick="closeModal('cancelModal')" 
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-6">
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to cancel this maintenance schedule? 
                    This action cannot be undone.
                </p>
                
                <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST" id="cancelForm">
                    @csrf
                    <div class="mb-4">
                        <label for="cancellation_reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Cancellation Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea id="cancellation_reason" name="cancellation_reason" rows="3"
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you cancelling this maintenance?"
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Minimum 10 characters
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('cancelModal')" class="btn-secondary">
                            Keep Schedule
                        </button>
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-times mr-2"></i> Cancel Maintenance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-2"></i> Changes Preview
                </h3>
                <button type="button" onclick="closeModal('previewModal')" 
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="previewContent">
                <!-- Preview content will be inserted here -->
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('previewModal')" class="btn-secondary mr-3">
                    Back to Editing
                </button>
                <button type="button" onclick="submitForm()" class="btn-primary">
                    <i class="fas fa-check mr-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize counters
    updateCounters();
    
    // Setup event listeners
    setupEventListeners();
    
    // Update impact description
    updateImpactDescription();
    
    // Update summary
    updateSummary();
});

function setupEventListeners() {
    // Notes counter
    const notesEl = document.getElementById('update_notes');
    const notesCounter = document.getElementById('notesCounter');
    if (notesEl && notesCounter) {
        notesEl.addEventListener('input', function() {
            notesCounter.textContent = this.value.length + '/500';
        });
    }
    
    // Description counter
    const descriptionEl = document.getElementById('description');
    const descriptionCounter = document.getElementById('descriptionCounter');
    if (descriptionEl && descriptionCounter) {
        descriptionEl.addEventListener('input', function() {
            descriptionCounter.textContent = this.value.length + '/500';
            updateSummary();
        });
    }
    
    // Impact description counter
    const impactEl = document.getElementById('user_impact_description');
    const impactCounter = document.getElementById('impactCounter');
    if (impactEl && impactCounter) {
        impactEl.addEventListener('input', function() {
            impactCounter.textContent = this.value.length + '/500';
            updateSummary();
        });
    }
    
    // Impact level description
    const impactLevelSelect = document.getElementById('impact_level');
    const impactDescription = document.getElementById('impactDescription');
    if (impactLevelSelect && impactDescription) {
        impactLevelSelect.addEventListener('change', function() {
            updateImpactDescription();
            updateSummary();
        });
    }
    
    // Maintenance type change
    const maintenanceTypeSelect = document.getElementById('maintenance_type');
    if (maintenanceTypeSelect) {
        maintenanceTypeSelect.addEventListener('change', updateSummary);
    }
    
    // Date and time inputs
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    if (startInput && endInput) {
        startInput.addEventListener('change', function() {
            endInput.min = this.value;
            updateDurationFromDates();
            updateSummary();
        });
        
        endInput.addEventListener('change', function() {
            updateDurationFromDates();
            updateSummary();
        });
    }
    
    // Duration slider and input sync
    const durationSlider = document.getElementById('duration_slider');
    const durationInput = document.getElementById('estimated_duration_minutes');
    if (durationSlider && durationInput) {
        durationSlider.addEventListener('input', function() {
            durationInput.value = this.value;
            updateEndTimeFromDuration();
            updateSummary();
        });
        
        durationInput.addEventListener('input', function() {
            durationSlider.value = this.value;
            updateEndTimeFromDuration();
            updateSummary();
        });
    }
    
    // Affected user types checkboxes
    document.querySelectorAll('input[name="affected_user_types[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSummary);
    });
    
    // Affected modules checkboxes
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSummary);
    });
}

function updateCounters() {
    const notesEl = document.getElementById('update_notes');
    const descriptionEl = document.getElementById('description');
    const impactEl = document.getElementById('user_impact_description');
    
    if (notesEl) {
        document.getElementById('notesCounter').textContent = notesEl.value.length + '/500';
    }
    
    if (descriptionEl) {
        document.getElementById('descriptionCounter').textContent = descriptionEl.value.length + '/500';
    }
    
    if (impactEl) {
        document.getElementById('impactCounter').textContent = impactEl.value.length + '/500';
    }
}

function updateImpactDescription() {
    const impactLevelSelect = document.getElementById('impact_level');
    const descriptionEl = document.getElementById('impactDescription');
    
    if (!impactLevelSelect || !descriptionEl) return;
    
    const impactLevel = impactLevelSelect.value;
    
    const descriptions = {
        'low': 'Minimal impact. Most features remain available. Users may experience minor performance issues.',
        'medium': 'Moderate impact. Some features will be unavailable. Users should plan accordingly.',
        'high': 'Significant impact. Many features will be unavailable. Consider rescheduling critical tasks.',
        'critical': 'Maximum impact. System will be mostly unavailable. Essential operations only.'
    };
    
    if (descriptions[impactLevel]) {
        descriptionEl.innerHTML = `
            <div class="flex items-start p-3 rounded-lg" style="background-color: ${
                impactLevel === 'critical' ? 'rgba(var(--danger-rgb), 0.05)' :
                impactLevel === 'high' ? 'rgba(var(--warning-rgb), 0.05)' :
                impactLevel === 'medium' ? 'rgba(var(--warning-rgb), 0.03)' :
                'rgba(var(--success-rgb), 0.05)'
            }; border-left: 4px solid ${
                impactLevel === 'critical' ? 'var(--danger)' :
                impactLevel === 'high' ? 'var(--warning)' :
                impactLevel === 'medium' ? 'var(--warning)' : 'var(--success)'
            };">
                <i class="fas fa-info-circle mt-0.5 mr-2" style="color: ${
                    impactLevel === 'critical' ? 'var(--danger)' :
                    impactLevel === 'high' ? 'var(--warning)' :
                    impactLevel === 'medium' ? 'var(--warning)' : 'var(--success)'
                };"></i>
                <span style="color: var(--text-secondary);">${descriptions[impactLevel]}</span>
            </div>
        `;
    } else {
        descriptionEl.innerHTML = '';
    }
}

function setDuration(minutes) {
    const durationSlider = document.getElementById('duration_slider');
    const durationInput = document.getElementById('estimated_duration_minutes');
    
    if (durationSlider && durationInput) {
        durationSlider.value = minutes;
        durationInput.value = minutes;
        updateEndTimeFromDuration();
        updateSummary();
    }
}

function updateDurationFromDates() {
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    const durationInput = document.getElementById('estimated_duration_minutes');
    const durationSlider = document.getElementById('duration_slider');
    
    if (startInput && endInput && startInput.value && endInput.value) {
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);
        const durationMinutes = Math.round((end - start) / (1000 * 60));
        
        if (durationMinutes > 0 && durationInput && durationSlider) {
            durationInput.value = durationMinutes;
            durationSlider.value = durationMinutes;
        }
    }
}

function updateEndTimeFromDuration() {
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    const durationInput = document.getElementById('estimated_duration_minutes');
    
    if (startInput && endInput && durationInput && startInput.value && durationInput.value) {
        const start = new Date(startInput.value);
        const end = new Date(start.getTime() + (parseInt(durationInput.value) * 60000));
        endInput.value = end.toISOString().slice(0, 16);
    }
}

function selectAllModules() {
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSummary();
}

function deselectAllModules() {
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSummary();
}

function updateSummary() {
    // Title
    const title = document.getElementById('title')?.value || '{{ $maintenance->title }}';
    document.getElementById('summaryTitle').textContent = title;
    
    // Type
    const typeSelect = document.getElementById('maintenance_type');
    const type = typeSelect ? typeSelect.options[typeSelect.selectedIndex]?.text : '{{ $maintenanceTypes[$maintenance->maintenance_type] ?? ucfirst($maintenance->maintenance_type) }}';
    document.getElementById('summaryType').textContent = type;
    
    // Impact
    const impactSelect = document.getElementById('impact_level');
    const impact = impactSelect ? impactSelect.options[impactSelect.selectedIndex]?.text : '{{ $impactLevels[$maintenance->impact_level] ?? ucfirst($maintenance->impact_level) }}';
    document.getElementById('summaryImpact').textContent = impact;
    
    // Schedule
    const start = document.getElementById('scheduled_start')?.value;
    const duration = document.getElementById('estimated_duration_minutes')?.value || {{ $maintenance->estimated_duration_minutes }};
    if (start) {
        const startDate = new Date(start);
        const formattedStart = startDate.toLocaleString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        document.getElementById('summaryStart').textContent = formattedStart;
    } else {
        document.getElementById('summaryStart').textContent = '{{ $maintenance->scheduled_start->format("M d, Y H:i") }}';
    }
    
    // Duration
    document.getElementById('summaryDuration').textContent = duration + ' minutes';
    
    // Affected modules count
    const moduleCount = document.querySelectorAll('input[name="affected_modules[]"]:checked').length;
    document.getElementById('summaryModules').textContent = moduleCount + ' modules';
}

function showCancelModal() {
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function showPreview() {
    // Validate form first
    if (!validateForm()) {
        return;
    }
    
    // Collect all form data
    const formData = new FormData(document.getElementById('maintenanceForm'));
    const data = Object.fromEntries(formData);
    
    // Get original values for comparison
    const original = {
        title: '{{ $maintenance->title }}',
        description: '{{ $maintenance->description }}',
        scheduled_start: '{{ $maintenance->scheduled_start->format("Y-m-d H:i:s") }}',
        scheduled_end: '{{ $maintenance->scheduled_end->format("Y-m-d H:i:s") }}',
        estimated_duration_minutes: {{ $maintenance->estimated_duration_minutes }},
        user_impact_description: '{{ $maintenance->user_impact_description }}'
    };
    
    // Format dates
    const formatDate = (dateString) => {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };
    
    // Build preview HTML with change highlighting
    const previewHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">Changes Preview</h4>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Review the changes you're about to make. Changes are highlighted in <span class="text-green-600">green</span>.
                </p>
            </div>
            
            <!-- Changes Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background-color: var(--bg-secondary);">
                            <th class="p-3 text-left" style="color: var(--text-secondary);">Field</th>
                            <th class="p-3 text-left" style="color: var(--text-secondary);">Original Value</th>
                            <th class="p-3 text-left" style="color: var(--text-secondary);">New Value</th>
                            <th class="p-3 text-left" style="color: var(--text-secondary);">Changed</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${getChangeRow('Title', original.title, data.title)}
                        ${getChangeRow('Description', original.description, data.description)}
                        ${getChangeRow('Start Date', formatDate(original.scheduled_start), formatDate(data.scheduled_start))}
                        ${getChangeRow('End Date', formatDate(original.scheduled_end), formatDate(data.scheduled_end))}
                        ${getChangeRow('Duration', original.estimated_duration_minutes + ' minutes', data.estimated_duration_minutes + ' minutes')}
                        ${getChangeRow('User Impact', original.user_impact_description, data.user_impact_description)}
                    </tbody>
                </table>
            </div>
            
            <!-- Update Notes -->
            <div>
                <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Update Notes</h5>
                <div class="p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <p class="text-sm">${data.update_notes || 'No update notes provided'}</p>
                </div>
            </div>
            
            <!-- Warning -->
            <div class="p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                    <div>
                        <h4 class="font-medium mb-2" style="color: var(--warning);">Important</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            ${'{{ $maintenance->status }}' === 'scheduled' 
                                ? 'Updating a scheduled maintenance will trigger notifications to affected users about the schedule change.' 
                                : 'These changes will be logged in the maintenance history.'}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Show preview modal
    document.getElementById('previewContent').innerHTML = previewHTML;
    document.getElementById('previewModal').classList.remove('hidden');
}

function getChangeRow(field, original, current) {
    const isChanged = original !== current;
    return `
        <tr style="border-bottom: 1px solid var(--border-color);">
            <td class="p-3" style="color: var(--text-primary);">${field}</td>
            <td class="p-3" style="color: var(--text-secondary);">${original || '-'}</td>
            <td class="p-3 ${isChanged ? 'text-green-600 font-medium' : ''}" style="color: ${isChanged ? 'var(--success)' : 'var(--text-primary)'};">
                ${current || '-'}
            </td>
            <td class="p-3">
                ${isChanged 
                    ? '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Changed</span>' 
                    : '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">No Change</span>'}
            </td>
        </tr>
    `;
}

function validateForm() {
    const updateNotes = document.getElementById('update_notes').value.trim();
    if (!updateNotes || updateNotes.length < 10) {
        alert('Update notes are required and must be at least 10 characters.');
        document.getElementById('update_notes').focus();
        return false;
    }
    
    // Check if any changes were made (optional, for user feedback)
    const originalTitle = '{{ $maintenance->title }}';
    const currentTitle = document.getElementById('title').value;
    
    const originalStart = '{{ $maintenance->scheduled_start->format("Y-m-d H:i:s") }}';
    const currentStart = document.getElementById('scheduled_start').value;
    
    if (originalTitle === currentTitle && 
        originalStart === currentStart.replace('T', ' ') + ':00') {
        if (!confirm('No significant changes detected. Are you sure you want to update?')) {
            return false;
        }
    }
    
    return true;
}

function submitForm() {
    if (validateForm()) {
        document.getElementById('maintenanceForm').submit();
    }
}

// Close modal when clicking outside
document.querySelectorAll('.fixed.inset-0').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('previewModal');
        closeModal('cancelModal');
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

/* Disabled field styling */
input:disabled, select:disabled, textarea:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    background-color: rgba(var(--text-secondary), 0.05);
}

/* Change highlight animation */
@keyframes highlight {
    0% {
        background-color: rgba(var(--success-rgb), 0.2);
    }
    100% {
        background-color: transparent;
    }
}

.highlight-change {
    animation: highlight 2s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

/* Smooth transitions */
.btn-primary, .btn-secondary, .card, input, select, textarea {
    transition: all 0.2s ease-in-out;
}

.btn-primary:hover, .btn-secondary:hover {
    transform: translateY(-1px);
}
</style>
@endsection