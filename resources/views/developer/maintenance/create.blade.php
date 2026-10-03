@extends('layouts.dev')

@section('title', 'Schedule New Maintenance')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            <i class="fas fa-calendar-plus mr-2"></i> Schedule New Maintenance
        </h1>
        <p class="text-sm" style="color: var(--text-secondary);">
            Plan and schedule system maintenance with detailed impact analysis and user notifications.
        </p>
    </div>

    <!-- Progress Steps -->
    <div class="card mb-8">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-blue-500 text-white mr-3">
                        <i class="fas fa-pencil-alt text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold" style="color: var(--text-primary);">Maintenance Planning</h3>
                        <p class="text-xs" style="color: var(--text-secondary);">Step 1 of 4: Basic Information</p>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <div class="text-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mb-1"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid var(--primary);">
                            1
                        </div>
                        <span class="text-xs" style="color: var(--primary);">Plan</span>
                    </div>
                    <div class="text-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mb-1"
                             style="background-color: rgba(var(--text-secondary), 0.1); color: var(--text-secondary); border: 2px solid var(--border-color);">
                            2
                        </div>
                        <span class="text-xs" style="color: var(--text-secondary);">Schedule</span>
                    </div>
                    <div class="text-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mb-1"
                             style="background-color: rgba(var(--text-secondary), 0.1); color: var(--text-secondary); border: 2px solid var(--border-color);">
                            3
                        </div>
                        <span class="text-xs" style="color: var(--text-secondary);">Impact</span>
                    </div>
                    <div class="text-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mb-1"
                             style="background-color: rgba(var(--text-secondary), 0.1); color: var(--text-secondary); border: 2px solid var(--border-color);">
                            4
                        </div>
                        <span class="text-xs" style="color: var(--text-secondary);">Review</span>
                    </div>
                </div>
            </div>
            
            <div class="h-2 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                <div class="h-full rounded-full" style="background-color: var(--primary); width: 25%;"></div>
            </div>
        </div>
    </div>

    <form action="{{ route('developer.maintenance.store') }}" method="POST" id="maintenanceForm">
        @csrf
        
        <!-- Basic Information Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Provide essential details about this maintenance event
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Maintenance Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" 
                           value="{{ old('title') }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Database Optimization, Security Patch Update, System Upgrade"
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
                              required minlength="20">{{ old('description') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Minimum 20 characters
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="descriptionCounter">
                            {{ strlen(old('description') ?? '') }}/500
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
                              placeholder="Technical specifications, database changes, API modifications, etc.">{{ old('technical_details') }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal use only - not shown to users
                    </div>
                </div>
                
                <!-- Maintenance Type and Impact -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Maintenance Type -->
                    <div>
                        <label for="maintenance_type" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Maintenance Type <span class="text-red-500">*</span>
                        </label>
                        <select id="maintenance_type" name="maintenance_type"
                                class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Type</option>
                            @foreach($maintenanceTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('maintenance_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('maintenance_type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        
                        <!-- Emergency Maintenance Options -->
                        <div id="emergencyOptions" class="mt-4 p-4 rounded-lg border {{ old('is_emergency') ? '' : 'hidden' }}"
                             style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-start">
                                <input type="checkbox" id="is_emergency" name="is_emergency" value="1"
                                       {{ old('is_emergency') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3">
                                    <label for="is_emergency" class="text-sm font-medium" style="color: var(--warning);">
                                        Emergency Maintenance
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Check this if this is unplanned emergency maintenance
                                    </p>
                                    
                                    <div id="emergencyFields" class="mt-3 space-y-3 {{ old('is_emergency') ? '' : 'hidden' }}">
                                        <div>
                                            <label for="emergency_reason" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                Emergency Reason <span class="text-red-500">*</span>
                                            </label>
                                            <textarea id="emergency_reason" name="emergency_reason" rows="2"
                                                      class="w-full p-2 text-sm border rounded"
                                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                      placeholder="Why is this emergency maintenance necessary?">{{ old('emergency_reason') }}</textarea>
                                        </div>
                                        
                                        <div>
                                            <label for="related_emergency_id" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                Link to Active Emergency (Optional)
                                            </label>
                                            <select id="related_emergency_id" name="related_emergency_id"
                                                    class="w-full p-2 text-sm border rounded"
                                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                                <option value="">No Emergency Link</option>
                                                @foreach($activeEmergencies as $emergency)
                                                    <option value="{{ $emergency->id }}" {{ old('related_emergency_id') == $emergency->id ? 'selected' : '' }}>
                                                        {{ $emergency->reference_id }} - {{ $emergency->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Impact Level -->
                    <div>
                        <label for="impact_level" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Impact Level <span class="text-red-500">*</span>
                        </label>
                        <select id="impact_level" name="impact_level"
                                class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Impact Level</option>
                            @foreach($impactLevels as $value => $label)
                                <option value="{{ $value }}" {{ old('impact_level') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('impact_level')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        
                        <!-- Impact Level Descriptions -->
                        <div class="mt-4 space-y-2">
                            <div class="text-xs" id="impactDescription">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduling Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2"></i> Scheduling
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Set the maintenance window and estimated duration
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
                        <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                               value="{{ old('scheduled_start') }}"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Must be in the future
                        </div>
                        @error('scheduled_start')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Scheduled End -->
                    <div>
                        <label for="scheduled_end" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            End Date & Time <span class="text-red-500">*</span>
                        </label>
                        <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                               value="{{ old('scheduled_end') }}"
                               class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Must be after start time
                        </div>
                        @error('scheduled_end')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Duration -->
                <div>
                    <label for="estimated_duration_minutes" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Estimated Duration (minutes) <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center space-x-4">
                        <input type="range" id="duration_slider" name="duration_slider" 
                               min="1" max="1440" value="{{ old('estimated_duration_minutes', 60) }}"
                               class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                               style="background-color: var(--border-color);">
                        <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes"
                               value="{{ old('estimated_duration_minutes', 60) }}"
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
                    Define what will be affected during maintenance
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- User Impact Description -->
                <div>
                    <label for="user_impact_description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        User Impact Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="user_impact_description" name="user_impact_description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Describe the impact from the user's perspective. What will they experience?"
                              required minlength="20">{{ old('user_impact_description') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Minimum 20 characters - This will be shown to users
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="impactCounter">
                            {{ strlen(old('user_impact_description') ?? '') }}/500
                        </div>
                    </div>
                    @error('user_impact_description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Affected Modules -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($defaultModules as $value => $label)
                        <div class="flex items-center">
                            <input type="checkbox" id="module_{{ $value }}" name="affected_modules[]"
                                   value="{{ $value }}"
                                   {{ in_array($value, old('affected_modules', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="module_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                {{ $label }}
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @error('affected_modules')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    
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
                        <button type="button" onclick="selectCriticalModules()" class="text-xs px-3 py-1 rounded border hover:bg-opacity-10"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Critical Only
                        </button>
                    </div>
                </div>
                
                <!-- Affected User Types -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected User Types <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($userTypes as $value => $label)
                        <div class="flex items-center">
                            <input type="checkbox" id="user_type_{{ $value }}" name="affected_user_types[]"
                                   value="{{ $value }}"
                                   {{ in_array($value, old('affected_user_types', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="user_type_{{ $value }}" class="ml-2 text-sm" style="color: var(--text-primary);">
                                {{ $label }}
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @error('affected_user_types')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    
                    <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Estimated affected users: <span id="estimatedUsersCount">0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Settings Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2"></i> Notification Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure how users will be notified about this maintenance
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Notify Users -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_users" name="notify_users" value="1"
                               {{ old('notify_users', true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="notify_users" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Notify users about this maintenance
                        </label>
                    </div>
                    <div class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        Recommended
                    </div>
                </div>
                
                <!-- Notification Channels -->
                <div id="notificationChannels" class="{{ old('notify_users', true) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Notification Channels
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_email" name="notification_channels[]"
                                   value="email"
                                   {{ in_array('email', old('notification_channels', ['email', 'in_app'])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_email" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_sms" name="notification_channels[]"
                                   value="sms"
                                   {{ in_array('sms', old('notification_channels', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_sms" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-comment-alt mr-1"></i> SMS
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_whatsapp" name="notification_channels[]"
                                   value="whatsapp"
                                   {{ in_array('whatsapp', old('notification_channels', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_whatsapp" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_in_app" name="notification_channels[]"
                                   value="in_app"
                                   {{ in_array('in_app', old('notification_channels', ['email', 'in_app'])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-blue-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_in_app" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-mobile-alt mr-1"></i> In-App
                            </label>
                        </div>
                    </div>
                    
                    <!-- Notification Preview -->
                    <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                        <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-eye mr-2"></i> Notification Preview
                        </h4>
                        <div class="text-sm space-y-2" style="color: var(--text-secondary);" id="notificationPreview">
                            <div><span class="font-medium">Title:</span> <span id="previewTitle">System Maintenance</span></div>
                            <div><span class="font-medium">Schedule:</span> <span id="previewSchedule">Not set</span></div>
                            <div><span class="font-medium">Impact:</span> <span id="previewImpact">Not specified</span></div>
                            <div><span class="font-medium">Channels:</span> <span id="previewChannels">Email, In-App</span></div>
                        </div>
                    </div>
                </div>
                
                <!-- Rollback Plan -->
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" id="has_rollback_plan" name="has_rollback_plan" value="1"
                               {{ old('has_rollback_plan') ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="has_rollback_plan" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Has rollback plan
                        </label>
                    </div>
                    <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                        Check this if there's a documented plan to rollback changes in case of issues
                    </p>
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
                    Review all information before submitting
                </p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Maintenance Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Title:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryTitle">{{ old('title') ?: 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Type:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryType">{{ old('maintenance_type') ? ucfirst(old('maintenance_type')) : 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Impact Level:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryImpact">{{ old('impact_level') ? ucfirst(old('impact_level')) : 'Not set' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Schedule</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Start:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryStart">{{ old('scheduled_start') ? date('M d, Y H:i', strtotime(old('scheduled_start'))) : 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Duration:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryDuration">{{ old('estimated_duration_minutes', 60) }} minutes</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Affected Users:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryUsers">0 estimated</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-medium mb-2" style="color: var(--warning);">Important Notice</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                After creating this maintenance schedule, it will need to be approved before it becomes active. 
                                You will be able to manage and monitor it from the maintenance dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="p-6 flex justify-between items-center">
                <div>
                    <a href="{{ route('developer.maintenance.index') }}" class="btn-secondary flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="resetForm()" class="btn-secondary flex items-center">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-save mr-2"></i> Create Maintenance Schedule
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-eye mr-2"></i> Maintenance Preview
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
                    Close Preview
                </button>
                <button type="button" onclick="submitForm()" class="btn-primary">
                    <i class="fas fa-check mr-2"></i> Create Maintenance
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
    
    // Update summary and preview
    updateSummary();
    updateNotificationPreview();
    
    // Load estimated users count
    loadEstimatedUsersCount();
});

function setupEventListeners() {
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
    
    // Emergency checkbox
    const emergencyCheckbox = document.getElementById('is_emergency');
    const emergencyFields = document.getElementById('emergencyFields');
    if (emergencyCheckbox && emergencyFields) {
        emergencyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                emergencyFields.classList.remove('hidden');
            } else {
                emergencyFields.classList.add('hidden');
            }
        });
    }
    
    // Impact level description
    const impactLevelSelect = document.getElementById('impact_level');
    const impactDescription = document.getElementById('impactDescription');
    if (impactLevelSelect && impactDescription) {
        impactLevelSelect.addEventListener('change', function() {
            updateImpactDescription();
            updateSummary();
            updateNotificationPreview();
        });
        updateImpactDescription(); // Initial call
    }
    
    // Maintenance type change
    const maintenanceTypeSelect = document.getElementById('maintenance_type');
    if (maintenanceTypeSelect) {
        maintenanceTypeSelect.addEventListener('change', function() {
            updateSummary();
            updateNotificationPreview();
            
            // Show emergency options for emergency type
            const emergencyOptions = document.getElementById('emergencyOptions');
            if (this.value === 'emergency') {
                emergencyOptions.classList.remove('hidden');
                document.getElementById('is_emergency').checked = true;
                emergencyFields.classList.remove('hidden');
            } else {
                emergencyOptions.classList.add('hidden');
                document.getElementById('is_emergency').checked = false;
                emergencyFields.classList.add('hidden');
            }
        });
    }
    
    // Date and time inputs
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    if (startInput && endInput) {
        // Set min date to current time
        const now = new Date();
        const localDateTime = now.toISOString().slice(0, 16);
        startInput.min = localDateTime;
        
        startInput.addEventListener('change', function() {
            endInput.min = this.value;
            updateDurationFromDates();
            updateSummary();
            updateNotificationPreview();
        });
        
        endInput.addEventListener('change', function() {
            updateDurationFromDates();
            updateSummary();
            updateNotificationPreview();
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
            updateNotificationPreview();
        });
        
        durationInput.addEventListener('input', function() {
            durationSlider.value = this.value;
            updateEndTimeFromDuration();
            updateSummary();
            updateNotificationPreview();
        });
    }
    
    // Notify users checkbox
    const notifyCheckbox = document.getElementById('notify_users');
    const channelsDiv = document.getElementById('notificationChannels');
    if (notifyCheckbox && channelsDiv) {
        notifyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                channelsDiv.classList.remove('hidden');
            } else {
                channelsDiv.classList.add('hidden');
            }
            updateNotificationPreview();
        });
    }
    
    // Notification channels change
    document.querySelectorAll('input[name="notification_channels[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateNotificationPreview);
    });
    
    // All form fields that affect summary
    document.querySelectorAll('#maintenanceForm input, #maintenanceForm select, #maintenanceForm textarea').forEach(el => {
        if (!el.type || !['checkbox', 'radio'].includes(el.type)) {
            el.addEventListener('input', updateSummary);
        }
    });
    
    // Affected user types checkboxes
    document.querySelectorAll('input[name="affected_user_types[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', loadEstimatedUsersCount);
    });
    
    // Affected modules checkboxes
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSummary);
    });
}

function updateCounters() {
    const descriptionEl = document.getElementById('description');
    const impactEl = document.getElementById('user_impact_description');
    
    if (descriptionEl) {
        document.getElementById('descriptionCounter').textContent = descriptionEl.value.length + '/500';
    }
    
    if (impactEl) {
        document.getElementById('impactCounter').textContent = impactEl.value.length + '/500';
    }
}

function updateImpactDescription() {
    const impactLevel = document.getElementById('impact_level').value;
    const descriptionEl = document.getElementById('impactDescription');
    
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
    document.getElementById('duration_slider').value = minutes;
    document.getElementById('estimated_duration_minutes').value = minutes;
    updateEndTimeFromDuration();
    updateSummary();
    updateNotificationPreview();
}

function updateDurationFromDates() {
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    const durationInput = document.getElementById('estimated_duration_minutes');
    const durationSlider = document.getElementById('duration_slider');
    
    if (startInput.value && endInput.value) {
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);
        const durationMinutes = Math.round((end - start) / (1000 * 60));
        
        if (durationMinutes > 0) {
            durationInput.value = durationMinutes;
            durationSlider.value = durationMinutes;
        }
    }
}

function updateEndTimeFromDuration() {
    const startInput = document.getElementById('scheduled_start');
    const endInput = document.getElementById('scheduled_end');
    const durationInput = document.getElementById('estimated_duration_minutes');
    
    if (startInput.value && durationInput.value) {
        const start = new Date(startInput.value);
        const end = new Date(start.getTime() + (parseInt(durationInput.value) * 60000));
        endInput.value = end.toISOString().slice(0, 16);
    }
}

async function loadEstimatedUsersCount() {
    const selectedUserTypes = Array.from(document.querySelectorAll('input[name="affected_user_types[]"]:checked'))
        .map(checkbox => checkbox.value);
    
    if (selectedUserTypes.length === 0) {
        document.getElementById('estimatedUsersCount').textContent = '0';
        document.getElementById('summaryUsers').textContent = '0 estimated';
        return;
    }
    
    try {
        const response = await fetch('/developer/maintenance/estimate-users', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ user_types: selectedUserTypes })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const count = data.estimated_users || 0;
            document.getElementById('estimatedUsersCount').textContent = count.toLocaleString();
            document.getElementById('summaryUsers').textContent = count.toLocaleString() + ' estimated';
        } else {
            throw new Error(data.message || 'Failed to load estimate');
        }
    } catch (error) {
        console.error('Error loading user estimate:', error);
        document.getElementById('estimatedUsersCount').textContent = 'Error';
        document.getElementById('summaryUsers').textContent = 'Error estimating';
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

function selectCriticalModules() {
    const criticalModules = ['user_management', 'authentication', 'database', 'payment_processing'];
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.checked = criticalModules.includes(checkbox.value);
    });
    updateSummary();
}

function updateSummary() {
    // Title
    const title = document.getElementById('title').value || 'Not set';
    document.getElementById('summaryTitle').textContent = title;
    document.getElementById('previewTitle').textContent = title;
    
    // Type
    const typeSelect = document.getElementById('maintenance_type');
    const type = typeSelect.options[typeSelect.selectedIndex]?.text || 'Not set';
    document.getElementById('summaryType').textContent = type;
    
    // Impact
    const impactSelect = document.getElementById('impact_level');
    const impact = impactSelect.options[impactSelect.selectedIndex]?.text || 'Not set';
    document.getElementById('summaryImpact').textContent = impact;
    document.getElementById('previewImpact').textContent = impact;
    
    // Schedule
    const start = document.getElementById('scheduled_start').value;
    const duration = document.getElementById('estimated_duration_minutes').value;
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
        document.getElementById('previewSchedule').textContent = formattedStart + ' for ' + duration + ' minutes';
    } else {
        document.getElementById('summaryStart').textContent = 'Not set';
        document.getElementById('previewSchedule').textContent = 'Not set';
    }
    
    // Duration
    document.getElementById('summaryDuration').textContent = duration + ' minutes';
    
    // Affected modules count
    const moduleCount = document.querySelectorAll('input[name="affected_modules[]"]:checked').length;
    document.getElementById('summaryModules').textContent = moduleCount + ' modules';
}

function updateNotificationPreview() {
    const notifyUsers = document.getElementById('notify_users').checked;
    const channels = Array.from(document.querySelectorAll('input[name="notification_channels[]"]:checked'))
        .map(checkbox => {
            const label = checkbox.nextElementSibling?.textContent?.trim() || checkbox.value;
            return label.replace(/^\s*[\w-]+\s+/, ''); // Remove icon and spacing
        });
    
    document.getElementById('previewChannels').textContent = notifyUsers ? channels.join(', ') : 'No notifications';
}

function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('maintenanceForm').reset();
        updateCounters();
        updateSummary();
        updateNotificationPreview();
        loadEstimatedUsersCount();
    }
}

function showPreview() {
    // Validate form first
    if (!validateForm()) {
        return;
    }
    
    // Collect all form data
    const formData = new FormData(document.getElementById('maintenanceForm'));
    const data = Object.fromEntries(formData);
    
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
    
    // Get selected modules
    const selectedModules = data['affected_modules[]'] || [];
    const moduleLabels = selectedModules.map(module => {
        return Object.entries(@json($defaultModules)).find(([key, label]) => key === module)?.[1] || module;
    });
    
    // Get selected user types
    const selectedUserTypes = data['affected_user_types[]'] || [];
    const userTypeLabels = selectedUserTypes.map(type => {
        return @json($userTypes)[type] || type;
    });
    
    // Get notification channels
    const notificationChannels = data['notification_channels[]'] || [];
    const channelLabels = notificationChannels.map(channel => {
        return channel.charAt(0).toUpperCase() + channel.slice(1).replace('_', ' ');
    });
    
    // Build preview HTML
    const previewHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">${data.title || 'No title'}</h4>
                <p class="text-sm" style="color: var(--text-secondary);">${data.description || 'No description'}</p>
            </div>
            
            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Type & Impact</h5>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Type:</span>
                            <span style="color: var(--text-primary); font-weight: 500;">${data.maintenance_type ? data.maintenance_type.charAt(0).toUpperCase() + data.maintenance_type.slice(1) : 'Not set'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Impact:</span>
                            <span style="color: var(--text-primary); font-weight: 500;">${data.impact_level ? data.impact_level.charAt(0).toUpperCase() + data.impact_level.slice(1) : 'Not set'}</span>
                        </div>
                        ${data.is_emergency ? `
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Emergency:</span>
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Yes</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div>
                    <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Schedule</h5>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Start:</span>
                            <span style="color: var(--text-primary); font-weight: 500;">${formatDate(data.scheduled_start)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Duration:</span>
                            <span style="color: var(--text-primary); font-weight: 500;">${data.estimated_duration_minutes} minutes</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Impact -->
            <div>
                <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">User Impact</h5>
                <div class="p-3 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-primary);">${data.user_impact_description || 'No impact description'}</p>
                </div>
            </div>
            
            <!-- Affected Areas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Affected Modules</h5>
                    <div class="space-y-1">
                        ${moduleLabels.length > 0 ? moduleLabels.map(label => `
                            <div class="flex items-center text-sm">
                                <i class="fas fa-cube mr-2" style="color: var(--text-secondary);"></i>
                                <span style="color: var(--text-primary);">${label}</span>
                            </div>
                        `).join('') : '<span class="text-sm" style="color: var(--text-secondary);">No modules selected</span>'}
                    </div>
                </div>
                
                <div>
                    <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Affected User Types</h5>
                    <div class="space-y-1">
                        ${userTypeLabels.length > 0 ? userTypeLabels.map(label => `
                            <div class="flex items-center text-sm">
                                <i class="fas fa-user mr-2" style="color: var(--text-secondary);"></i>
                                <span style="color: var(--text-primary);">${label}</span>
                            </div>
                        `).join('') : '<span class="text-sm" style="color: var(--text-secondary);">No user types selected</span>'}
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <div>
                <h5 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Notifications</h5>
                <div class="p-3 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-primary);">
                            ${data.notify_users ? 'Users will be notified via:' : 'Users will not be notified'}
                        </span>
                        ${data.notify_users ? `
                        <div class="flex space-x-2">
                            ${channelLabels.map(channel => `
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">${channel}</span>
                            `).join('')}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Show preview modal
    document.getElementById('previewContent').innerHTML = previewHTML;
    document.getElementById('previewModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function validateForm() {
    const requiredFields = [
        'title', 'description', 'maintenance_type', 'impact_level',
        'scheduled_start', 'scheduled_end', 'estimated_duration_minutes',
        'user_impact_description'
    ];
    
    let isValid = true;
    let firstError = null;
    
    requiredFields.forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            let value = field.value;
            
            // Handle checkboxes/radio differently
            if (field.type === 'checkbox' || field.type === 'radio') {
                const checked = document.querySelectorAll(`[name="${fieldName}"]:checked`).length > 0;
                if (!checked) {
                    isValid = false;
                    if (!firstError) firstError = field;
                }
            } else if (field.type === 'select-one') {
                if (!value) {
                    isValid = false;
                    if (!firstError) firstError = field;
                }
            } else {
                if (!value.trim()) {
                    isValid = false;
                    if (!firstError) firstError = field;
                }
            }
        }
    });
    
    // Check affected modules
    const affectedModules = document.querySelectorAll('input[name="affected_modules[]"]:checked');
    if (affectedModules.length === 0) {
        isValid = false;
        alert('Please select at least one affected module.');
        return false;
    }
    
    // Check affected user types
    const affectedUserTypes = document.querySelectorAll('input[name="affected_user_types[]"]:checked');
    if (affectedUserTypes.length === 0) {
        isValid = false;
        alert('Please select at least one affected user type.');
        return false;
    }
    
    // Check if end date is after start date
    const startDate = new Date(document.getElementById('scheduled_start').value);
    const endDate = new Date(document.getElementById('scheduled_end').value);
    if (endDate <= startDate) {
        isValid = false;
        alert('End date must be after start date.');
        return false;
    }
    
    // Check emergency fields if emergency is checked
    if (document.getElementById('is_emergency')?.checked) {
        const emergencyReason = document.getElementById('emergency_reason').value.trim();
        if (!emergencyReason || emergencyReason.length < 10) {
            isValid = false;
            alert('Emergency reason is required and must be at least 10 characters.');
            return false;
        }
    }
    
    if (!isValid && firstError) {
        firstError.focus();
        alert('Please fill in all required fields.');
        return false;
    }
    
    return isValid;
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
    }
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        submitForm();
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

/* Form field focus styles */
input:focus, select:focus, textarea:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom checkbox styling */
input[type="checkbox"] {
    transition: all 0.2s ease;
}

input[type="checkbox"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

/* Loading animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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