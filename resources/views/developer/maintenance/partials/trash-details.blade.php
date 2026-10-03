<div class="space-y-6">
    <!-- Header -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border-left: 4px solid var(--danger);">
        <h4 class="font-semibold mb-2" style="color: var(--danger);">
            <i class="fas fa-trash-alt mr-2"></i> Deleted Maintenance Schedule
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span style="color: var(--text-secondary);">Reference:</span>
                <span class="font-mono font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->reference_id }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Deleted:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->deleted_at->format('M d, Y H:i') }}
                    ({{ $maintenance->deleted_at->diffForHumans() }})
                </span>
            </div>
        </div>
    </div>
    
    <!-- Basic Information -->
    <div>
        <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Basic Information</h5>
        <div class="space-y-3">
            <div>
                <span style="color: var(--text-secondary);">Title:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">{{ $maintenance->title }}</span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Description:</span>
                <p class="mt-1 p-2 rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    {{ $maintenance->description }}
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <span style="color: var(--text-secondary);">Type:</span>
                    <span class="font-medium ml-2" style="color: var(--text-primary);">
                        {{ str_replace('_', ' ', $maintenance->maintenance_type) }}
                    </span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Impact Level:</span>
                    <span class="font-medium ml-2 capitalize" style="color: var(--text-primary);">
                        {{ $maintenance->impact_level }}
                    </span>
                </div>
            </div>
            @if($maintenance->is_emergency && $maintenance->emergency_reason)
            <div>
                <span style="color: var(--text-secondary);">Emergency Reason:</span>
                <p class="mt-1 p-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.05); color: var(--text-primary);">
                    {{ $maintenance->emergency_reason }}
                </p>
            </div>
            @endif
        </div>
    </div>
    
    <!-- Schedule Information -->
    <div>
        <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Schedule</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <span style="color: var(--text-secondary);">Scheduled Start:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->scheduled_start->format('M d, Y H:i') }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Scheduled End:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->scheduled_end->format('M d, Y H:i') }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Duration:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->estimated_duration_minutes }} minutes
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Created:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->created_at->format('M d, Y H:i') }}
                </span>
            </div>
        </div>
    </div>
    
    <!-- User Impact -->
    <div>
        <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">User Impact</h5>
        <div class="p-3 rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
            {{ $maintenance->user_impact_description }}
        </div>
    </div>
    
    <!-- Affected Modules & Users -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Affected Modules</h5>
            <div class="space-y-2">
                @if($maintenance->affected_modules && count($maintenance->affected_modules) > 0)
                    @foreach($maintenance->affected_modules as $module)
                    <div class="flex items-center text-sm">
                        <i class="fas fa-cube mr-2" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">{{ $module }}</span>
                    </div>
                    @endforeach
                @else
                    <span class="text-sm" style="color: var(--text-secondary);">No modules specified</span>
                @endif
            </div>
        </div>
        
        <div>
            <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Affected User Types</h5>
            <div class="space-y-2">
                @if($maintenance->affected_user_types && count($maintenance->affected_user_types) > 0)
                    @php
                        $userTypes = [
                            '0' => 'Super Admin',
                            '1' => 'Admin',
                            '2' => 'Landlord',
                            '3' => 'Tenant',
                            '4' => 'Field Agent',
                            '5' => 'Developer',
                            '6' => 'Security Checkpoint',
                        ];
                    @endphp
                    @foreach($maintenance->affected_user_types as $type)
                    <div class="flex items-center text-sm">
                        <i class="fas fa-user mr-2" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">{{ $userTypes[$type] ?? $type }}</span>
                    </div>
                    @endforeach
                @else
                    <span class="text-sm" style="color: var(--text-secondary);">No user types specified</span>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Additional Information -->
    <div>
        <h5 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Additional Information</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <span style="color: var(--text-secondary);">Created By:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->creator->name ?? 'System' }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Deleted By:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->deletedBy->name ?? 'System' }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Estimated Users:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ number_format($maintenance->estimated_affected_users) }}
                </span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Notifications:</span>
                <span class="font-medium ml-2" style="color: var(--text-primary);">
                    {{ $maintenance->notify_users ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
        </div>
    </div>
    
    <!-- Auto-deletion Warning -->
    <div class="p-4 rounded-lg border" style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
        <div class="flex items-start">
            <i class="fas fa-clock mt-1 mr-3" style="color: var(--warning);"></i>
            <div>
                <h5 class="font-medium mb-2" style="color: var(--warning);">Auto-deletion Notice</h5>
                <p class="text-sm" style="color: var(--text-secondary);">
                    This item will be automatically and permanently deleted on 
                    <strong>{{ $maintenance->deleted_at->addDays(30)->format('F j, Y') }}</strong> 
                    ({{ $maintenance->deleted_at->addDays(30)->diffForHumans() }}).
                </p>
            </div>
        </div>
    </div>
</div>