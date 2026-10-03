<div id="extendMaintenanceModal" class="modal-overlay hidden">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Extend Maintenance</h3>
            <button onclick="this.closest('.modal-overlay').classList.add('hidden')" 
                    class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="extendMaintenanceForm" onsubmit="submitExtendMaintenance(event)">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">Additional Time (minutes) *</label>
                    <select name="additional_minutes" class="dev-select" required>
                        <option value="">Select additional time</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="120">2 hours</option>
                        <option value="180">3 hours</option>
                        <option value="240">4 hours</option>
                    </select>
                    <p class="form-hint">Add additional time to the current maintenance window</p>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Extension</label>
                    <textarea name="reason" rows="3" 
                              class="dev-textarea" 
                              placeholder="Why is additional time needed?"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="notify_users" class="dev-checkbox" checked>
                        <span class="text-sm text-[var(--text-primary)]">Notify users about extension</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" 
                        onclick="this.closest('.modal-overlay').classList.add('hidden')"
                        class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-warning">
                    <i class="fas fa-clock mr-2"></i>
                    Extend Maintenance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function submitExtendMaintenance(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const maintenanceId = document.getElementById('extendMaintenanceModal').dataset.maintenanceId;
    
    fetch(`/developer/maintenance/${maintenanceId}/extend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            ...data,
            notify_users: data.notify_users === 'on',
            additional_minutes: parseInt(data.additional_minutes)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Maintenance extended successfully', 'success');
            document.getElementById('extendMaintenanceModal').classList.add('hidden');
            form.reset();
            location.reload();
        } else {
            showNotification('Failed to extend maintenance: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to extend maintenance: ' + error.message, 'error');
    });
}
</script>