{{-- developer/super-admins/modals/bulk-actions.blade.php --}}
<!-- Bulk Actions Modal -->
<div id="bulkActionsModal" class="modal hidden">
    <div class="modal-overlay" onclick="hideBulkActionsModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <div class="flex items-center">
                <div class="modal-icon">
                    <i class="fas fa-layer-group" style="color: var(--info);"></i>
                </div>
                <div>
                    <h3 class="modal-title">Bulk Actions</h3>
                    <p class="modal-subtitle">Perform actions on multiple Super Admins</p>
                </div>
            </div>
            <button type="button" onclick="hideBulkActionsModal()" class="modal-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <!-- Action Selection -->
            <div class="mb-6">
                <label class="form-label">
                    <i class="fas fa-tasks mr-2" style="color: var(--info);"></i> Select Action
                </label>
                <select id="bulkActionSelect" class="custom-select w-full">
                    <option value="">Choose an action...</option>
                    <option value="activate" data-icon="check-circle" data-color="success">Activate Selected</option>
                    <option value="suspend" data-icon="ban" data-color="warning">Suspend Selected</option>
                    <option value="deactivate" data-icon="minus-circle" data-color="secondary">Deactivate Selected</option>
                    <option value="send_invitation" data-icon="paper-plane" data-color="primary">Send Invitation</option>
                    <option value="delete" data-icon="trash-alt" data-color="danger">Delete Selected</option>
                </select>
            </div>
            
            <!-- Invitation Options (Hidden by default) -->
            <div id="bulkInvitationOptions" class="hidden space-y-4">
                <div>
                    <label class="form-label">
                        <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> Invitation Channels
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach(['email', 'sms', 'whatsapp'] as $channel)
                        <div class="channel-option">
                            <input type="checkbox" 
                                   id="bulkChannel{{ ucfirst($channel) }}" 
                                   name="invitation_channels[]" 
                                   value="{{ $channel }}"
                                   class="channel-checkbox bulk-channel-checkbox"
                                   {{ $channel == 'email' ? 'checked' : '' }}
                                   {{ !($communicationStatus[$channel]['enabled'] ?? false) ? 'disabled' : '' }}>
                            <label for="bulkChannel{{ ucfirst($channel) }}" class="channel-label">
                                <div class="channel-icon">
                                    @if($channel == 'email')
                                        <i class="fas fa-envelope"></i>
                                    @elseif($channel == 'sms')
                                        <i class="fas fa-sms"></i>
                                    @else
                                        <i class="fab fa-whatsapp"></i>
                                    @endif
                                </div>
                                <div class="channel-text">
                                    <span class="channel-name">{{ ucfirst($channel) }}</span>
                                    @if(!($communicationStatus[$channel]['enabled'] ?? false))
                                    <span class="channel-status text-danger text-xs">
                                        <i class="fas fa-exclamation-circle mr-1"></i> Offline
                                    </span>
                                    @else
                                    <span class="channel-status text-success text-xs">
                                        <i class="fas fa-check-circle mr-1"></i> Online
                                    </span>
                                    @endif
                                </div>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar mr-2" style="color: var(--primary);"></i> Expires In (Days)
                    </label>
                    <div class="relative">
                        <input type="number" 
                               id="bulkExpiresIn" 
                               name="expires_in_days" 
                               value="7" 
                               min="1" 
                               max="30"
                               class="custom-input w-full pl-10">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-calendar-day" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Invitation will expire after the specified number of days
                    </p>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-comment-alt mr-2" style="color: var(--primary);"></i> Custom Message (Optional)
                    </label>
                    <textarea id="bulkCustomMessage" 
                              name="custom_message" 
                              rows="2" 
                              class="custom-textarea"
                              placeholder="Add a personal message to the invitation..."></textarea>
                </div>
            </div>
            
            <!-- Selected Users Info -->
            <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-users mr-2" style="color: var(--info);"></i> Selected Users
                        </h4>
                        <p class="text-sm" style="color: var(--text-secondary);" id="selectedCountText">
                            No users selected
                        </p>
                    </div>
                    <div class="text-right">
                        <button type="button" onclick="toggleBulkSelect()" class="btn btn-info text-xs">
                            <i class="fas fa-edit mr-1"></i> Change Selection
                        </button>
                    </div>
                </div>
                
                <!-- Selected Users List -->
                <div id="selectedUsersList" class="mt-3 max-h-32 overflow-y-auto hidden">
                    <div class="space-y-2">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>
            
            <!-- Warning Messages -->
            <div id="bulkWarning" class="hidden mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--warning);">Warning</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);" id="warningMessage">
                            This action will affect all selected users.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Delete Warning -->
            <div id="bulkDeleteWarning" class="hidden mt-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">Irreversible Action</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Deleting Super Admins cannot be undone. All data will be permanently removed.
                        </p>
                        <div class="mt-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="bulkDeleteConfirm" class="mr-2">
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    I understand this action cannot be reversed
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" onclick="hideBulkActionsModal()" 
                    class="btn btn-secondary">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <button type="button" onclick="performBulkAction()" 
                    id="bulkActionBtn"
                    class="btn btn-primary disabled"
                    disabled>
                <i class="fas fa-play mr-2"></i> Execute Action
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initBulkActionsModal();
});

function initBulkActionsModal() {
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const bulkInvitationOptions = document.getElementById('bulkInvitationOptions');
    const bulkWarning = document.getElementById('bulkWarning');
    const bulkDeleteWarning = document.getElementById('bulkDeleteWarning');
    const bulkDeleteConfirm = document.getElementById('bulkDeleteConfirm');
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const action = this.value;
            const icon = selectedOption.dataset.icon;
            const color = selectedOption.dataset.color;
            
            // Reset all sections
            bulkInvitationOptions.classList.add('hidden');
            bulkWarning.classList.add('hidden');
            bulkDeleteWarning.classList.add('hidden');
            
            // Update button based on action
            if (action) {
                bulkActionBtn.disabled = false;
                bulkActionBtn.classList.remove('disabled');
                bulkActionBtn.className = `btn btn-${color}`;
                bulkActionBtn.innerHTML = `<i class="fas fa-${icon} mr-2"></i> ${selectedOption.text}`;
            } else {
                bulkActionBtn.disabled = true;
                bulkActionBtn.classList.add('disabled');
                bulkActionBtn.className = 'btn btn-primary disabled';
                bulkActionBtn.innerHTML = '<i class="fas fa-play mr-2"></i> Execute Action';
            }
            
            // Show specific sections based on action
            switch (action) {
                case 'send_invitation':
                    bulkInvitationOptions.classList.remove('hidden');
                    bulkWarning.classList.remove('hidden');
                    document.getElementById('warningMessage').textContent = 
                        'Invitations will be sent to all selected users via selected channels.';
                    break;
                    
                case 'delete':
                    bulkDeleteWarning.classList.remove('hidden');
                    bulkWarning.classList.remove('hidden');
                    document.getElementById('warningMessage').textContent = 
                        'This will permanently delete all selected Super Admins. This action cannot be undone.';
                    break;
                    
                case 'activate':
                case 'suspend':
                case 'deactivate':
                    bulkWarning.classList.remove('hidden');
                    document.getElementById('warningMessage').textContent = 
                        `This will ${action} all selected Super Admins.`;
                    break;
            }
            
            // Update selected users list
            updateSelectedUsersList();
        });
    }
    
    // Handle delete confirmation checkbox
    if (bulkDeleteConfirm) {
        bulkDeleteConfirm.addEventListener('change', function() {
            if (bulkActionSelect.value === 'delete') {
                bulkActionBtn.disabled = !this.checked;
                bulkActionBtn.classList.toggle('disabled', !this.checked);
            }
        });
    }
    
    // Handle channel checkbox changes
    document.querySelectorAll('.bulk-channel-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (bulkActionSelect.value === 'send_invitation') {
                const checkedChannels = document.querySelectorAll('.bulk-channel-checkbox:checked');
                bulkActionBtn.disabled = checkedChannels.length === 0;
                bulkActionBtn.classList.toggle('disabled', checkedChannels.length === 0);
            }
        });
    });
}

function updateSelectedUsersList() {
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    const selectedUsersList = document.getElementById('selectedUsersList');
    const listContainer = selectedUsersList?.querySelector('.space-y-2');
    
    if (!selectedUsersList || !listContainer) return;
    
    listContainer.innerHTML = '';
    
    if (selectedCheckboxes.length > 0) {
        selectedUsersList.classList.remove('hidden');
        
        selectedCheckboxes.forEach(checkbox => {
            const userId = checkbox.value;
            const userName = checkbox.dataset.name || 'User';
            const userEmail = checkbox.dataset.email || '';
            
            const userItem = document.createElement('div');
            userItem.className = 'flex items-center justify-between p-2 rounded';
            userItem.style.backgroundColor = 'rgba(var(--info-rgb), 0.05)';
            userItem.style.border = '1px solid rgba(var(--info-rgb), 0.1)';
            
            userItem.innerHTML = `
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-user text-xs"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">${userName}</p>
                        ${userEmail ? `<p class="text-xs" style="color: var(--text-secondary);">${userEmail}</p>` : ''}
                    </div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full" 
                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                      ID: ${userId}
                </span>
            `;
            
            listContainer.appendChild(userItem);
        });
        
        // Limit display to 5 items with "show more" option
        if (selectedCheckboxes.length > 5) {
            const moreItem = document.createElement('div');
            moreItem.className = 'text-center py-2';
            moreItem.innerHTML = `
                <span class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-ellipsis-h mr-1"></i>
                    And ${selectedCheckboxes.length - 5} more users...
                </span>
            `;
            listContainer.appendChild(moreItem);
        }
    } else {
        selectedUsersList.classList.add('hidden');
    }
}
</script>
@endpush

@push('styles')
<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
    z-index: 1001;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.modal-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.channel-option {
    position: relative;
}

.channel-checkbox {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.channel-label {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: var(--card-bg);
}

.channel-checkbox:checked + .channel-label {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.channel-checkbox:disabled + .channel-label {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: rgba(var(--danger-rgb), 0.05);
}

.channel-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

#bulkChannelEmail:checked + .channel-label .channel-icon {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

#bulkChannelSms:checked + .channel-label .channel-icon {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

#bulkChannelWhatsapp:checked + .channel-label .channel-icon {
    background-color: rgba(37, 211, 102, 0.1);
    color: #25D366;
}

.channel-text {
    flex: 1;
}

.channel-name {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
}

.channel-status {
    display: block;
    font-size: 0.75rem;
}

.custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.75rem;
    width: 100%;
    font-size: 0.875rem;
    resize: vertical;
    min-height: 80px;
    transition: all 0.2s ease;
}

.custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.btn.disabled:hover {
    transform: none !important;
}

@media (max-width: 640px) {
    .modal-container {
        width: 95%;
        max-height: 85vh;
        margin: 0.5rem;
    }
    
    .modal-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .modal-close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}
</style>
@endpush