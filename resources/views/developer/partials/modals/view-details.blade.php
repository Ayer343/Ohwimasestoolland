<div id="viewDetailsModal" class="modal-overlay hidden">
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Maintenance Details</h3>
            <button onclick="this.closest('.modal-overlay').classList.add('hidden')" 
                    class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="maintenanceDetails" class="space-y-4">
                <div class="text-center py-8">
                    <div class="spinner mx-auto"></div>
                    <p class="mt-2 text-[var(--text-secondary)]">Loading details...</p>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" 
                    onclick="this.closest('.modal-overlay').classList.add('hidden')"
                    class="btn-secondary">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function loadMaintenanceDetails() {
    const modal = document.getElementById('viewDetailsModal');
    const maintenanceId = modal.dataset.maintenanceId;
    const detailsContainer = document.getElementById('maintenanceDetails');
    
    if (!maintenanceId) {
        detailsContainer.innerHTML = '<div class="text-center py-8 text-[var(--text-danger)]">Error: No maintenance ID provided</div>';
        return;
    }
    
    fetch(`/developer/maintenance/${maintenanceId}`)
    .then(response => response.json())
    .then(data => {
        const statusColors = {
            'active': 'warning',
            'scheduled': 'primary',
            'completed': 'success',
            'cancelled': 'danger'
        };
        
        const statusText = {
            'active': 'Active',
            'scheduled': 'Scheduled',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        
        const color = statusColors[data.status] || 'secondary';
        const computedStyle = getComputedStyle(document.body);
        const colorValue = computedStyle.getPropertyValue(`--${color}`).trim();
        
        detailsContainer.innerHTML = `
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="text-lg font-medium text-[var(--text-primary)]">${data.reason || 'No reason provided'}</h4>
                        <div class="flex items-center space-x-2 mt-1">
                            <span class="px-2 py-1 text-xs rounded-full" style="
                                background-color: ${hexToRgba(colorValue, 0.1)};
                                color: ${colorValue};
                                border: 1px solid ${hexToRgba(colorValue, 0.3)};
                            ">
                                ${statusText[data.status] || data.status}
                            </span>
                            <span class="px-2 py-1 text-xs rounded-full" style="
                                background-color: ${hexToRgba(colorValue, 0.1)};
                                color: ${colorValue};
                                border: 1px solid ${hexToRgba(colorValue, 0.3)};
                            ">
                                ${(data.impact_level || 'medium').toUpperCase()} Impact
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-[var(--text-secondary)]">ID: ${data.id}</div>
                        <div class="text-xs text-[var(--text-secondary)]">Created: ${new Date(data.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-sm text-[var(--text-secondary)]">Scheduled Start</div>
                        <div class="font-medium text-[var(--text-primary)]">${new Date(data.scheduled_start).toLocaleString()}</div>
                    </div>
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-sm text-[var(--text-secondary)]">Scheduled End</div>
                        <div class="font-medium text-[var(--text-primary)]">${new Date(data.scheduled_end).toLocaleString()}</div>
                    </div>
                </div>
                
                ${data.actual_start ? `
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-sm text-[var(--text-secondary)]">Actual Start</div>
                        <div class="font-medium text-[var(--text-primary)]">${new Date(data.actual_start).toLocaleString()}</div>
                    </div>
                    ${data.actual_end ? `
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-sm text-[var(--text-secondary)]">Actual End</div>
                        <div class="font-medium text-[var(--text-primary)]">${new Date(data.actual_end).toLocaleString()}</div>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
                
                ${data.affected_modules && data.affected_modules.length > 0 ? `
                <div>
                    <div class="text-sm font-medium text-[var(--text-primary)] mb-2">Affected Modules</div>
                    <div class="flex flex-wrap gap-2">
                        ${data.affected_modules.map(module => `
                            <span class="px-2 py-1 text-xs rounded-full" style="
                                background-color: ${hexToRgba(colorValue, 0.1)};
                                color: ${colorValue};
                                border: 1px solid ${hexToRgba(colorValue, 0.3)};
                            ">
                                ${module}
                            </span>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div>
                    <div class="text-sm font-medium text-[var(--text-primary)] mb-2">Additional Information</div>
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="text-sm text-[var(--text-secondary)]">Scheduled By</div>
                        <div class="font-medium text-[var(--text-primary)]">${data.scheduled_by_name || 'System'}</div>
                        
                        ${data.last_notified_at ? `
                        <div class="mt-2 text-sm text-[var(--text-secondary)]">Last Notified</div>
                        <div class="font-medium text-[var(--text-primary)]">${new Date(data.last_notified_at).toLocaleString()}</div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    })
    .catch(error => {
        detailsContainer.innerHTML = `
            <div class="text-center py-8 text-[var(--text-danger)]">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <p>Failed to load maintenance details</p>
                <p class="text-sm mt-1">${error.message}</p>
            </div>
        `;
    });
}

// Listen for modal show event
document.getElementById('viewDetailsModal').addEventListener('show', loadMaintenanceDetails);
</script>