<div id="scheduleMaintenanceModal" class="modal-overlay hidden">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Schedule Maintenance</h3>
            <button onclick="this.closest('.modal-overlay').classList.add('hidden')" 
                    class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="scheduleMaintenanceForm" onsubmit="submitScheduleMaintenance(event)">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">Reason for Maintenance *</label>
                    <textarea name="reason" rows="3" 
                              class="dev-textarea" 
                              placeholder="Describe the purpose of this maintenance"
                              required></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" 
                               name="scheduled_start" 
                               class="dev-input"
                               min="{{ date('Y-m-d\TH:i') }}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Date & Time *</label>
                        <input type="datetime-local" 
                               name="scheduled_end" 
                               class="dev-input"
                               min="{{ date('Y-m-d\TH:i', strtotime('+1 hour')) }}"
                               required>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Estimated Duration</label>
                        <div class="mt-1 text-sm text-[var(--text-primary)]" id="durationDisplay">--</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Impact Level *</label>
                        <select name="impact_level" class="dev-select" required>
                            <option value="">Select impact</option>
                            <option value="low">Low - Minor inconvenience</option>
                            <option value="medium">Medium - Some features unavailable</option>
                            <option value="high">High - Major service disruption</option>
                            <option value="critical">Critical - Complete outage</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Affected Modules (Optional)</label>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="affected_modules[]" value="database" class="dev-checkbox">
                            <span>Database</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="affected_modules[]" value="api" class="dev-checkbox">
                            <span>API</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="affected_modules[]" value="web" class="dev-checkbox">
                            <span>Web Interface</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="notify_users" class="dev-checkbox" checked>
                        <span class="text-sm text-[var(--text-primary)]">Notify users in advance</span>
                    </label>
                    <p class="form-hint">Users will be notified 24 hours before maintenance starts</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" 
                        onclick="this.closest('.modal-overlay').classList.add('hidden')"
                        class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Schedule Maintenance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Calculate and display duration
function updateDurationDisplay() {
    const startInput = document.querySelector('input[name="scheduled_start"]');
    const endInput = document.querySelector('input[name="scheduled_end"]');
    const display = document.getElementById('durationDisplay');
    
    if (startInput.value && endInput.value) {
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);
        const diffMs = end - start;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        if (diffMs < 0) {
            display.textContent = 'End time must be after start time';
            display.style.color = 'var(--danger)';
        } else {
            display.textContent = `${diffHours}h ${diffMinutes}m`;
            display.style.color = 'var(--text-primary)';
        }
    } else {
        display.textContent = '--';
        display.style.color = 'var(--text-secondary)';
    }
}

// Add event listeners for datetime inputs
document.addEventListener('DOMContentLoaded', function() {
    const startInput = document.querySelector('input[name="scheduled_start"]');
    const endInput = document.querySelector('input[name="scheduled_end"]');
    
    if (startInput) {
        startInput.addEventListener('change', updateDurationDisplay);
    }
    if (endInput) {
        endInput.addEventListener('change', updateDurationDisplay);
    }
});

function submitScheduleMaintenance(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Get checked modules
    const affectedModules = [];
    form.querySelectorAll('input[name="affected_modules[]"]:checked').forEach(checkbox => {
        affectedModules.push(checkbox.value);
    });
    
    fetch('/developer/maintenance/schedule', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            ...data,
            affected_modules: affectedModules,
            notify_users: data.notify_users === 'on'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Maintenance scheduled successfully', 'success');
            document.getElementById('scheduleMaintenanceModal').classList.add('hidden');
            form.reset();
            location.reload();
        } else {
            showNotification('Failed to schedule maintenance: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to schedule maintenance: ' + error.message, 'error');
    });
}
</script>