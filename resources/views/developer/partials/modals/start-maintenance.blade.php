<div id="startMaintenanceModal" class="modal-overlay hidden">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Start Immediate Maintenance</h3>
            <button onclick="this.closest('.modal-overlay').classList.add('hidden')" 
                    class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="startMaintenanceForm" onsubmit="submitStartMaintenance(event)">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">Reason for Maintenance *</label>
                    <textarea name="reason" rows="3" 
                              class="dev-textarea" 
                              placeholder="Describe why immediate maintenance is required"
                              required></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Estimated Duration (minutes) *</label>
                        <select name="estimated_duration" class="dev-select" required>
                            <option value="">Select duration</option>
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="60">1 hour</option>
                            <option value="120">2 hours</option>
                            <option value="240">4 hours</option>
                            <option value="480">8 hours</option>
                        </select>
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
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="affected_modules[]" value="payments" class="dev-checkbox">
                            <span>Payments</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="notify_users" class="dev-checkbox" checked>
                        <span class="text-sm text-[var(--text-primary)]">Notify all users about this maintenance</span>
                    </label>
                    <p class="form-hint">Users will be notified via email, SMS, and in-app notifications</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" 
                        onclick="this.closest('.modal-overlay').classList.add('hidden')"
                        class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-warning">
                    <i class="fas fa-play-circle mr-2"></i>
                    Start Maintenance Now
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function submitStartMaintenance(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Get checked modules
    const affectedModules = [];
    form.querySelectorAll('input[name="affected_modules[]"]:checked').forEach(checkbox => {
        affectedModules.push(checkbox.value);
    });
    
    fetch('/developer/maintenance/start-now', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            ...data,
            affected_modules: affectedModules,
            notify_users: data.notify_users === 'on',
            estimated_duration: parseInt(data.estimated_duration)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Maintenance started successfully', 'success');
            document.getElementById('startMaintenanceModal').classList.add('hidden');
            form.reset();
            location.reload();
        } else {
            showNotification('Failed to start maintenance: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to start maintenance: ' + error.message, 'error');
    });
}
</script>