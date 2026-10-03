<div id="maintenanceScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Schedule Maintenance</h3>
                <button onclick="closeMaintenanceModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="maintenanceScheduleForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maintenance Type</label>
                        <select name="type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="planned">Planned</option>
                            <option value="emergency">Emergency</option>
                            <option value="security">Security</option>
                            <option value="performance">Performance</option>
                            <option value="database">Database</option>
                            <option value="upgrade">Upgrade</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Impact Level</label>
                        <select name="impact_level" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                    <input type="text" name="title" 
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="e.g., Database Optimization">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reason</label>
                    <textarea name="reason" rows="3" 
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Why is this maintenance needed?"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="3" 
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Detailed description of the maintenance..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scheduled Start</label>
                        <input type="datetime-local" name="scheduled_start" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Estimated Duration (minutes)</label>
                        <input type="number" name="estimated_duration_minutes" min="1" max="480"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Affected Modules</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['application', 'api', 'database', 'payment', 'sms', 'email', 'storage'] as $module)
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="affected_modules[]" value="{{ $module }}" class="rounded border-gray-300">
                            <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($module) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Affected User Types</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            ['value' => '0', 'label' => 'Super Admins'],
                            ['value' => '1', 'label' => 'Admins'],
                            ['value' => '2', 'label' => 'Landlords'],
                            ['value' => '3', 'label' => 'Tenants'],
                            ['value' => '4', 'label' => 'Field Agents'],
                            ['value' => '5', 'label' => 'Developers'],
                            ['value' => '6', 'label' => 'Security']
                        ] as $group)
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="affected_user_types[]" value="{{ $group['value'] }}" class="rounded border-gray-300">
                            <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ $group['label'] }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notification Channels</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            ['value' => 'email', 'label' => 'Email', 'icon' => 'envelope'],
                            ['value' => 'sms', 'label' => 'SMS', 'icon' => 'comment-alt'],
                            ['value' => 'in_app', 'label' => 'In-App', 'icon' => 'bell'],
                            ['value' => 'system_banner', 'label' => 'System Banner', 'icon' => 'exclamation-triangle']
                        ] as $channel)
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="notification_channels[]" value="{{ $channel['value'] }}" checked class="rounded border-gray-300">
                            <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-{{ $channel['icon'] }} mr-1"></i> {{ $channel['label'] }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="notify_users" checked class="rounded border-gray-300">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Notify users about this maintenance</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                    <button type="button" onclick="closeMaintenanceModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg">
                        <i class="fas fa-calendar-plus mr-1"></i> Schedule
                    </button>
                    <button type="button" onclick="startMaintenanceNow()" 
                        class="px-4 py-2 text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600 rounded-lg">
                        <i class="fas fa-play mr-1"></i> Start Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showMaintenanceModal() {
    // Set default values
    const now = new Date();
    const future = new Date(now.getTime() + 24 * 60 * 60 * 1000); // Tomorrow
    
    document.querySelector('input[name="scheduled_start"]').value = 
        future.toISOString().slice(0, 16);
    document.querySelector('input[name="estimated_duration_minutes"]').value = 60;
    
    document.getElementById('maintenanceScheduleModal').classList.remove('hidden');
}

function closeMaintenanceModal() {
    document.getElementById('maintenanceScheduleModal').classList.add('hidden');
}

function startMaintenanceNow() {
    const form = document.getElementById('maintenanceScheduleForm');
    const formData = new FormData(form);
    
    const data = {
        reason: formData.get('reason'),
        estimated_duration: parseInt(formData.get('estimated_duration_minutes')) || 30,
        impact_level: formData.get('impact_level'),
        notify_users: formData.get('notify_users') === 'on'
    };
    
    if (!data.reason) {
        showNotification('Please provide a reason for maintenance', 'warning');
        return;
    }
    
    fetch('{{ route("developer.maintenance.start-now") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Maintenance started successfully', 'success');
            closeMaintenanceModal();
            location.reload();
        } else {
            showNotification('Failed to start maintenance: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to start maintenance: ' + error.message, 'error');
    });
}

document.getElementById('maintenanceScheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const scheduledStart = new Date(formData.get('scheduled_start'));
    const now = new Date();
    
    if (scheduledStart <= now) {
        showNotification('Scheduled start must be in the future', 'warning');
        return;
    }
    
    const data = {
        title: formData.get('title'),
        reason: formData.get('reason'),
        description: formData.get('description'),
        type: formData.get('type'),
        impact_level: formData.get('impact_level'),
        scheduled_start: formData.get('scheduled_start'),
        estimated_duration_minutes: parseInt(formData.get('estimated_duration_minutes')),
        affected_modules: formData.getAll('affected_modules[]'),
        affected_user_types: formData.getAll('affected_user_types[]').map(Number),
        notification_channels: formData.getAll('notification_channels[]'),
        notify_users: formData.get('notify_users') === 'on'
    };
    
    fetch('{{ route("developer.maintenance.schedule") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Maintenance scheduled successfully', 'success');
            closeMaintenanceModal();
            loadMaintenanceSchedule();
            this.reset();
        } else {
            showNotification('Failed to schedule maintenance: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to schedule maintenance: ' + error.message, 'error');
    });
});
</script>