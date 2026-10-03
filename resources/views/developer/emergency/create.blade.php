@extends('layouts.dev')

@section('title', 'Create New Emergency Mode')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            <i class="fas fa-exclamation-triangle mr-2"></i> Create New Emergency Mode
        </h1>
        <p class="text-sm" style="color: var(--text-secondary);">
            Configure emergency response procedures to handle system incidents and disruptions.
        </p>
    </div>

    <!-- Emergency Mode Creation Form -->
    <form action="{{ route('developer.emergency.store') }}" method="POST" id="emergencyForm">
        @csrf
        
        <!-- Basic Information Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Basic Information
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Provide essential details about this emergency mode
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Emergency Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" 
                           value="{{ old('name') }}"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Security Breach Response, System Outage Protocol"
                           required maxlength="255">
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Description (Optional)
                    </label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Detailed description of this emergency mode configuration...">{{ old('description') }}</textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Internal documentation only
                    </div>
                </div>
                
                <!-- Reason -->
                <div>
                    <label for="reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Emergency Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="reason" name="reason" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Explain why this emergency mode is necessary..."
                              required minlength="10">{{ old('reason') }}</textarea>
                    <div class="flex justify-between mt-1">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Minimum 10 characters
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);" id="reasonCounter">
                            {{ strlen(old('reason') ?? '') }}/500
                        </div>
                    </div>
                    @error('reason')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Severity Level -->
                <div>
                    <label for="severity_level" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Severity Level <span class="text-red-500">*</span>
                    </label>
                    <select id="severity_level" name="severity_level"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                            required>
                        <option value="">Select Severity Level</option>
                        @foreach($severityLevels as $value => $label)
                            <option value="{{ $value }}" {{ old('severity_level') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('severity_level')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    
                    <!-- Severity Level Descriptions -->
                    <div class="mt-4 space-y-2">
                        <div class="text-xs" id="severityDescription">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Impact Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-cogs mr-2"></i> System Impact
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Define which system modules will be affected
                </p>
            </div>
            
            <div class="p-6">
                <!-- Affected Modules -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Affected Modules <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($defaultModules as $value => $label)
                        <div class="flex items-center p-2 rounded-lg border hover:border-red-300 transition-colors"
                             style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <input type="checkbox" id="module_{{ $value }}" name="affected_modules[]"
                                   value="{{ $value }}"
                                   {{ in_array($value, old('affected_modules', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="module_{{ $value }}" class="ml-2 text-sm flex-1" style="color: var(--text-primary);">
                                {{ $label }}
                            </label>
                            <i class="fas fa-info-circle text-xs" style="color: var(--text-secondary);"
                               title="{{ $moduleDescriptions[$value] ?? 'System module' }}"></i>
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
                            <i class="fas fa-exclamation-triangle mr-1"></i> Critical Systems Only
                        </button>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Restricted Features -->
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Restricted Features (Optional)
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="restrict_registration" name="restricted_features[]"
                                       value="user_registration"
                                       {{ in_array('user_registration', old('restricted_features', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="restrict_registration" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Disable User Registration
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="restrict_property_creation" name="restricted_features[]"
                                       value="property_creation"
                                       {{ in_array('property_creation', old('restricted_features', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="restrict_property_creation" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Disable Property Creation
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="restrict_payments" name="restricted_features[]"
                                       value="payment_processing"
                                       {{ in_array('payment_processing', old('restricted_features', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="restrict_payments" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Disable Payment Processing
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="restrict_emails" name="restricted_features[]"
                                       value="email_notifications"
                                       {{ in_array('email_notifications', old('restricted_features', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="restrict_emails" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Disable Non-Critical Emails
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="restrict_api_write" name="restricted_features[]"
                                       value="api_write_operations"
                                       {{ in_array('api_write_operations', old('restricted_features', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="restrict_api_write" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Disable API Write Operations
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allowed Operations -->
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            Allowed Operations (Optional)
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="allow_view_dashboard" name="allowed_operations[]"
                                       value="view_dashboard"
                                       {{ in_array('view_dashboard', old('allowed_operations', ['view_dashboard', 'basic_read'])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="allow_view_dashboard" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    View Dashboard
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="allow_basic_read" name="allowed_operations[]"
                                       value="basic_read"
                                       {{ in_array('basic_read', old('allowed_operations', ['view_dashboard', 'basic_read'])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="allow_basic_read" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Basic Read Operations
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="allow_read_profile" name="allowed_operations[]"
                                       value="read_profile"
                                       {{ in_array('read_profile', old('allowed_operations', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="allow_read_profile" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Read User Profile
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="allow_read_only" name="allowed_operations[]"
                                       value="read_only"
                                       {{ in_array('read_only', old('allowed_operations', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="allow_read_only" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Read-Only Access
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="allow_emergency_read" name="allowed_operations[]"
                                       value="emergency_read"
                                       {{ in_array('emergency_read', old('allowed_operations', [])) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <label for="allow_emergency_read" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Emergency Read Access
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activation Settings Card -->
        <div class="card mb-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i> Activation Settings
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure when and how this emergency mode should be activated
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Activation Options -->
                <div>
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        Activation Method <span class="text-red-500">*</span>
                    </label>
                    
                    <div class="space-y-4">
                        <!-- Immediate Activation -->
                        <div class="p-4 rounded-lg border transition-colors" id="immediateOption"
                             style="background-color: {{ old('activate_immediately') ? 'rgba(var(--warning-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                    border-color: {{ old('activate_immediately') ? 'rgba(var(--warning-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <input type="checkbox" id="activate_immediately" name="activate_immediately" value="1"
                                       {{ old('activate_immediately') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <label for="activate_immediately" class="text-sm font-medium" style="color: var(--warning);">
                                            Activate Immediately
                                        </label>
                                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                            Recommended for emergencies
                                        </span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Emergency mode will be activated immediately after saving. All system restrictions will be applied.
                                    </p>
                                    
                                    <div id="immediateWarning" class="mt-3 p-3 rounded-lg {{ old('activate_immediately') ? '' : 'hidden' }}"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                        <div class="flex">
                                            <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                                            <div>
                                                <h4 class="text-sm font-medium mb-1" style="color: var(--warning);">Warning</h4>
                                                <p class="text-xs" style="color: var(--text-secondary);">
                                                    This will immediately put the system into emergency mode. Users will be notified and system features will be restricted.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Scheduled Activation -->
                        <div class="p-4 rounded-lg border transition-colors" id="scheduledOption"
                             style="background-color: {{ old('schedule_for_later') ? 'rgba(var(--info-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                    border-color: {{ old('schedule_for_later') ? 'rgba(var(--info-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <input type="checkbox" id="schedule_for_later" name="schedule_for_later" value="1"
                                       {{ old('schedule_for_later') ? 'checked' : '' }}
                                       class="h-4 w-4 mt-1 rounded focus:ring-red-500"
                                       style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="ml-3 flex-1">
                                    <label for="schedule_for_later" class="text-sm font-medium" style="color: var(--info);">
                                        Schedule for Later
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Schedule emergency mode activation for a future date and time.
                                    </p>
                                    
                                    <div id="scheduleFields" class="mt-3 space-y-3 {{ old('schedule_for_later') ? '' : 'hidden' }}">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="scheduled_start" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                    Start Date & Time <span class="text-red-500">*</span>
                                                </label>
                                                <input type="datetime-local" id="scheduled_start" name="scheduled_start"
                                                       value="{{ old('scheduled_start') }}"
                                                       class="w-full p-2 text-sm border rounded"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                            
                                            <div>
                                                <label for="scheduled_end" class="block text-xs mb-1" style="color: var(--text-secondary);">
                                                    End Date & Time (Optional)
                                                </label>
                                                <input type="datetime-local" id="scheduled_end" name="scheduled_end"
                                                       value="{{ old('scheduled_end') }}"
                                                       class="w-full p-2 text-sm border rounded"
                                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                            </div>
                                        </div>
                                        
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Scheduled emergency modes can be cancelled before activation.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inactive Configuration -->
                        <div class="p-4 rounded-lg border" id="inactiveOption"
                             style="background-color: {{ !old('activate_immediately') && !old('schedule_for_later') ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-secondary)' }}; 
                                    border-color: {{ !old('activate_immediately') && !old('schedule_for_later') ? 'rgba(var(--primary-rgb), 0.3)' : 'var(--border-color)' }};">
                            <div class="flex items-start">
                                <div class="flex-1">
                                    <label class="text-sm font-medium" style="color: var(--primary);">
                                        Save as Inactive Configuration
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Save the configuration without activating it. You can activate it manually later.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Auto Recovery -->
                    <div class="mt-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="auto_recovery" name="auto_recovery" value="1"
                                   {{ old('auto_recovery', true) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="auto_recovery" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                                Enable Auto-Recovery
                            </label>
                        </div>
                        <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                            System will run recovery checks before deactivating emergency mode
                        </p>
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
                    Configure how users will be notified about this emergency
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Notify Users -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_users" name="notify_users" value="1"
                               {{ old('notify_users', true) ? 'checked' : '' }}
                               class="h-4 w-4 rounded focus:ring-red-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="notify_users" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            Notify users about this emergency
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
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_email" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_sms" name="notification_channels[]"
                                   value="sms"
                                   {{ in_array('sms', old('notification_channels', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_sms" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-comment-alt mr-1"></i> SMS
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_whatsapp" name="notification_channels[]"
                                   value="whatsapp"
                                   {{ in_array('whatsapp', old('notification_channels', [])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
                                   style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <label for="channel_whatsapp" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="channel_in_app" name="notification_channels[]"
                                   value="in_app"
                                   {{ in_array('in_app', old('notification_channels', ['email', 'in_app'])) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded focus:ring-red-500"
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
                            <div><span class="font-medium">Title:</span> <span id="previewTitle">System Emergency</span></div>
                            <div><span class="font-medium">Severity:</span> <span id="previewSeverity">Not specified</span></div>
                            <div><span class="font-medium">Channels:</span> <span id="previewChannels">Email, In-App</span></div>
                            <div><span class="font-medium">Status:</span> <span id="previewStatus">Saving configuration</span></div>
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
                    Review all information before submitting
                </p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Emergency Details</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Name:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryName">{{ old('name') ?: 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Severity:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summarySeverity">{{ old('severity_level') ? ucfirst(old('severity_level')) : 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Modules:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryModules">0 selected</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Activation</h4>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Method:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryMethod">
                                    @if(old('activate_immediately'))
                                        Immediate Activation
                                    @elseif(old('schedule_for_later'))
                                        Scheduled
                                    @else
                                        Inactive
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Auto-Recovery:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryRecovery">
                                    {{ old('auto_recovery', true) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Notifications:</span>
                                <span style="color: var(--text-primary); font-weight: 500;" id="summaryNotifications">
                                    {{ old('notify_users', true) ? 'Enabled' : 'Disabled' }}
                                </span>
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
                                @if(old('activate_immediately'))
                                <strong>This emergency mode will be activated immediately after saving.</strong> System restrictions will be applied and users will be notified.
                                @elseif(old('schedule_for_later'))
                                This emergency mode is scheduled for activation. You can cancel it before the scheduled time.
                                @else
                                This emergency mode will be saved as an inactive configuration. You can activate it manually when needed.
                                @endif
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
                    <a href="{{ route('developer.emergency.index') }}" class="btn-secondary flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="resetForm()" class="btn-secondary flex items-center">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <button type="submit" class="btn-primary flex items-center" style="background-color: var(--danger);">
                        <i class="fas fa-save mr-2"></i> Create Emergency Mode
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Confirmation Modal for Immediate Activation -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-red-500 text-white mr-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Confirm Immediate Activation</h3>
            </div>
            
            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    You are about to activate emergency mode immediately. This will:
                </p>
                <ul class="text-sm mt-2 space-y-1" style="color: var(--text-secondary);">
                    <li class="flex items-start">
                        <i class="fas fa-ban mt-0.5 mr-2 text-red-500"></i>
                        <span>Apply system restrictions to selected modules</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-bell mt-0.5 mr-2 text-red-500"></i>
                        <span>Notify affected users</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-2 text-red-500"></i>
                        <span>Potentially disrupt normal system operations</span>
                    </li>
                </ul>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal('confirmationModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="button" onclick="submitForm()" class="btn-primary" style="background-color: var(--danger);">
                    <i class="fas fa-bolt mr-2"></i> Confirm & Activate
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
});

function setupEventListeners() {
    // Reason counter
    const reasonEl = document.getElementById('reason');
    const reasonCounter = document.getElementById('reasonCounter');
    if (reasonEl && reasonCounter) {
        reasonEl.addEventListener('input', function() {
            reasonCounter.textContent = this.value.length + '/500';
            updateSummary();
        });
    }
    
    // Severity level description
    const severitySelect = document.getElementById('severity_level');
    const severityDescription = document.getElementById('severityDescription');
    if (severitySelect && severityDescription) {
        severitySelect.addEventListener('change', function() {
            updateSeverityDescription();
            updateSummary();
            updateNotificationPreview();
        });
        updateSeverityDescription(); // Initial call
    }
    
    // Name input updates summary
    document.getElementById('name').addEventListener('input', function() {
        updateSummary();
        updateNotificationPreview();
    });
    
    // Affected modules checkboxes
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSummary);
    });
    
    // Activation method toggles
    const immediateCheckbox = document.getElementById('activate_immediately');
    const scheduleCheckbox = document.getElementById('schedule_for_later');
    const immediateOption = document.getElementById('immediateOption');
    const scheduleOption = document.getElementById('scheduledOption');
    const inactiveOption = document.getElementById('inactiveOption');
    const immediateWarning = document.getElementById('immediateWarning');
    const scheduleFields = document.getElementById('scheduleFields');
    
    if (immediateCheckbox && scheduleCheckbox) {
        immediateCheckbox.addEventListener('change', function() {
            if (this.checked) {
                scheduleCheckbox.checked = false;
                immediateOption.style.backgroundColor = 'rgba(var(--warning-rgb), 0.05)';
                immediateOption.style.borderColor = 'rgba(var(--warning-rgb), 0.3)';
                scheduleOption.style.backgroundColor = 'var(--bg-secondary)';
                scheduleOption.style.borderColor = 'var(--border-color)';
                inactiveOption.style.backgroundColor = 'var(--bg-secondary)';
                inactiveOption.style.borderColor = 'var(--border-color)';
                immediateWarning.classList.remove('hidden');
                scheduleFields.classList.add('hidden');
            } else {
                immediateOption.style.backgroundColor = 'var(--bg-secondary)';
                immediateOption.style.borderColor = 'var(--border-color)';
                immediateWarning.classList.add('hidden');
            }
            updateSummary();
            updateNotificationPreview();
        });
        
        scheduleCheckbox.addEventListener('change', function() {
            if (this.checked) {
                immediateCheckbox.checked = false;
                scheduleOption.style.backgroundColor = 'rgba(var(--info-rgb), 0.05)';
                scheduleOption.style.borderColor = 'rgba(var(--info-rgb), 0.3)';
                immediateOption.style.backgroundColor = 'var(--bg-secondary)';
                immediateOption.style.borderColor = 'var(--border-color)';
                inactiveOption.style.backgroundColor = 'var(--bg-secondary)';
                inactiveOption.style.borderColor = 'var(--border-color)';
                scheduleFields.classList.remove('hidden');
                immediateWarning.classList.add('hidden');
            } else {
                scheduleOption.style.backgroundColor = 'var(--bg-secondary)';
                scheduleOption.style.borderColor = 'var(--border-color)';
                scheduleFields.classList.add('hidden');
            }
            updateSummary();
            updateNotificationPreview();
        });
        
        // Set initial states
        if (immediateCheckbox.checked) {
            immediateOption.style.backgroundColor = 'rgba(var(--warning-rgb), 0.05)';
            immediateOption.style.borderColor = 'rgba(var(--warning-rgb), 0.3)';
            immediateWarning.classList.remove('hidden');
        } else if (scheduleCheckbox.checked) {
            scheduleOption.style.backgroundColor = 'rgba(var(--info-rgb), 0.05)';
            scheduleOption.style.borderColor = 'rgba(var(--info-rgb), 0.3)';
            scheduleFields.classList.remove('hidden');
        } else {
            inactiveOption.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            inactiveOption.style.borderColor = 'rgba(var(--primary-rgb), 0.3)';
        }
    }
    
    // Date and time inputs
    const startInput = document.getElementById('scheduled_start');
    if (startInput) {
        // Set min date to current time
        const now = new Date();
        const localDateTime = now.toISOString().slice(0, 16);
        startInput.min = localDateTime;
        
        startInput.addEventListener('change', function() {
            const endInput = document.getElementById('scheduled_end');
            if (endInput) {
                endInput.min = this.value;
            }
            updateSummary();
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
            updateSummary();
            updateNotificationPreview();
        });
    }
    
    // Notification channels change
    document.querySelectorAll('input[name="notification_channels[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateNotificationPreview);
    });
    
    // Auto recovery checkbox
    document.getElementById('auto_recovery')?.addEventListener('change', updateSummary);
    
    // All form fields that affect summary
    document.querySelectorAll('#emergencyForm input, #emergencyForm select, #emergencyForm textarea').forEach(el => {
        if (!el.type || !['checkbox', 'radio'].includes(el.type)) {
            el.addEventListener('input', updateSummary);
        }
    });
    
    // Form submission handler
    const emergencyForm = document.getElementById('emergencyForm');
    if (emergencyForm) {
        emergencyForm.addEventListener('submit', handleFormSubmit);
    }
}

function updateCounters() {
    const reasonEl = document.getElementById('reason');
    if (reasonEl) {
        document.getElementById('reasonCounter').textContent = reasonEl.value.length + '/500';
    }
}

function updateSeverityDescription() {
    const severityLevel = document.getElementById('severity_level').value;
    const descriptionEl = document.getElementById('severityDescription');
    
    const descriptions = {
        'low': 'Minor impact. Limited features affected. Users may experience reduced functionality.',
        'medium': 'Moderate impact. Several features affected. Users should expect some disruption.',
        'high': 'Significant impact. Major features affected. System performance will be degraded.',
        'critical': 'Critical impact. System-wide disruption. Essential operations only.'
    };
    
    if (descriptions[severityLevel]) {
        descriptionEl.innerHTML = `
            <div class="flex items-start p-3 rounded-lg" style="background-color: ${
                severityLevel === 'critical' ? 'rgba(var(--danger-rgb), 0.05)' :
                severityLevel === 'high' ? 'rgba(var(--warning-rgb), 0.05)' :
                severityLevel === 'medium' ? 'rgba(var(--warning-rgb), 0.03)' :
                'rgba(var(--success-rgb), 0.05)'
            }; border-left: 4px solid ${
                severityLevel === 'critical' ? 'var(--danger)' :
                severityLevel === 'high' ? 'var(--warning)' :
                severityLevel === 'medium' ? 'var(--warning)' : 'var(--success)'
            };">
                <i class="fas fa-info-circle mt-0.5 mr-2" style="color: ${
                    severityLevel === 'critical' ? 'var(--danger)' :
                    severityLevel === 'high' ? 'var(--warning)' :
                    severityLevel === 'medium' ? 'var(--warning)' : 'var(--success)'
                };"></i>
                <span style="color: var(--text-secondary);">${descriptions[severityLevel]}</span>
            </div>
        `;
    } else {
        descriptionEl.innerHTML = '';
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
    const criticalModules = ['user_management', 'authentication', 'payment_processing', 'database'];
    document.querySelectorAll('input[name="affected_modules[]"]').forEach(checkbox => {
        checkbox.checked = criticalModules.includes(checkbox.value);
    });
    updateSummary();
}

function updateSummary() {
    // Name
    const name = document.getElementById('name').value || 'Not set';
    document.getElementById('summaryName').textContent = name;
    document.getElementById('previewTitle').textContent = name;
    
    // Severity
    const severitySelect = document.getElementById('severity_level');
    const severity = severitySelect.options[severitySelect.selectedIndex]?.text || 'Not set';
    document.getElementById('summarySeverity').textContent = severity;
    document.getElementById('previewSeverity').textContent = severity;
    
    // Modules count
    const moduleCount = document.querySelectorAll('input[name="affected_modules[]"]:checked').length;
    document.getElementById('summaryModules').textContent = moduleCount + ' selected';
    
    // Activation method
    const immediateChecked = document.getElementById('activate_immediately').checked;
    const scheduleChecked = document.getElementById('schedule_for_later').checked;
    
    let method = 'Inactive';
    let status = 'Saving configuration';
    
    if (immediateChecked) {
        method = 'Immediate Activation';
        status = 'Activating immediately';
    } else if (scheduleChecked) {
        const startInput = document.getElementById('scheduled_start');
        if (startInput && startInput.value) {
            const startDate = new Date(startInput.value);
            method = `Scheduled for ${startDate.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}`;
        } else {
            method = 'Scheduled (no date)';
        }
        status = 'Scheduled activation';
    }
    
    document.getElementById('summaryMethod').textContent = method;
    document.getElementById('previewStatus').textContent = status;
    
    // Auto-recovery
    const autoRecovery = document.getElementById('auto_recovery').checked;
    document.getElementById('summaryRecovery').textContent = autoRecovery ? 'Enabled' : 'Disabled';
    
    // Notifications
    const notifyUsers = document.getElementById('notify_users').checked;
    document.getElementById('summaryNotifications').textContent = notifyUsers ? 'Enabled' : 'Disabled';
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
        document.getElementById('emergencyForm').reset();
        updateCounters();
        updateSummary();
        updateNotificationPreview();
        
        // Reset visual states
        const immediateOption = document.getElementById('immediateOption');
        const scheduleOption = document.getElementById('scheduledOption');
        const inactiveOption = document.getElementById('inactiveOption');
        const immediateWarning = document.getElementById('immediateWarning');
        const scheduleFields = document.getElementById('scheduleFields');
        const channelsDiv = document.getElementById('notificationChannels');
        
        if (immediateOption) {
            immediateOption.style.backgroundColor = 'var(--bg-secondary)';
            immediateOption.style.borderColor = 'var(--border-color)';
        }
        if (scheduleOption) {
            scheduleOption.style.backgroundColor = 'var(--bg-secondary)';
            scheduleOption.style.borderColor = 'var(--border-color)';
        }
        if (inactiveOption) {
            inactiveOption.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            inactiveOption.style.borderColor = 'rgba(var(--primary-rgb), 0.3)';
        }
        if (immediateWarning) immediateWarning.classList.add('hidden');
        if (scheduleFields) scheduleFields.classList.add('hidden');
        if (channelsDiv) channelsDiv.classList.remove('hidden');
        
        updateSeverityDescription();
    }
}

function handleFormSubmit(event) {
    // Validate form first
    if (!validateForm()) {
        event.preventDefault();
        return;
    }
    
    // Check if immediate activation is selected
    const immediateActivation = document.getElementById('activate_immediately').checked;
    
    if (immediateActivation) {
        event.preventDefault();
        showConfirmationModal();
    }
    // Otherwise, let the form submit normally
}

function validateForm() {
    // Required fields
    const requiredFields = ['name', 'reason', 'severity_level', 'affected_modules'];
    let isValid = true;
    let firstError = null;
    
    requiredFields.forEach(fieldName => {
        if (fieldName === 'affected_modules') {
            const checkedModules = document.querySelectorAll('input[name="affected_modules[]"]:checked').length;
            if (checkedModules === 0) {
                isValid = false;
                if (!firstError) {
                    firstError = { element: document.querySelector('input[name="affected_modules[]"]'), message: 'Please select at least one affected module.' };
                }
            }
        } else {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                if (!field.value.trim()) {
                    isValid = false;
                    if (!firstError) {
                        firstError = { element: field, message: `Please fill in the ${fieldName.replace('_', ' ')} field.` };
                    }
                }
            }
        }
    });
    
    // Check reason length
    const reasonField = document.getElementById('reason');
    if (reasonField && reasonField.value.trim().length < 10) {
        isValid = false;
        if (!firstError) {
            firstError = { element: reasonField, message: 'Emergency reason must be at least 10 characters long.' };
        }
    }
    
    // Check scheduled activation dates
    const scheduleChecked = document.getElementById('schedule_for_later').checked;
    if (scheduleChecked) {
        const startInput = document.getElementById('scheduled_start');
        if (startInput && !startInput.value) {
            isValid = false;
            if (!firstError) {
                firstError = { element: startInput, message: 'Please select a start date and time for scheduled activation.' };
            }
        }
        
        if (startInput && startInput.value) {
            const startDate = new Date(startInput.value);
            const now = new Date();
            if (startDate <= now) {
                isValid = false;
                if (!firstError) {
                    firstError = { element: startInput, message: 'Scheduled start time must be in the future.' };
                }
            }
        }
        
        const endInput = document.getElementById('scheduled_end');
        if (endInput && endInput.value && startInput && startInput.value) {
            const startDate = new Date(startInput.value);
            const endDate = new Date(endInput.value);
            if (endDate <= startDate) {
                isValid = false;
                if (!firstError) {
                    firstError = { element: endInput, message: 'End time must be after start time.' };
                }
            }
        }
    }
    
    if (!isValid && firstError) {
        alert(firstError.message);
        firstError.element.focus();
        if (firstError.element.type === 'checkbox') {
            firstError.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
    
    return true;
}

function showConfirmationModal() {
    document.getElementById('confirmationModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function submitForm() {
    document.getElementById('emergencyForm').submit();
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
        closeModal('confirmationModal');
    }
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        handleFormSubmit({ preventDefault: () => {} });
    }
});
</script>

<style>
/* Custom checkbox styling */
input[type="checkbox"] {
    transition: all 0.2s ease;
}

input[type="checkbox"]:checked {
    background-color: var(--danger);
    border-color: var(--danger);
}

/* Form field focus styles */
input:focus, select:focus, textarea:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1);
}

/* Activation option hover effects */
#immediateOption:hover, #scheduledOption:hover, #inactiveOption:hover {
    border-color: rgba(var(--primary-rgb), 0.3);
    cursor: pointer;
}

/* Module selection hover */
input[name="affected_modules[]"] + label:hover {
    cursor: pointer;
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

/* Emergency-specific colors */
.btn-primary {
    background-color: var(--danger);
}

.btn-primary:hover {
    background-color: rgba(var(--danger-rgb), 0.9);
}
</style>
@endsection