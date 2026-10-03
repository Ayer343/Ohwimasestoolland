<div id="systemAlertModal" class="modal-overlay hidden">
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-[var(--text-primary)]">Send System Alert</h3>
            <button onclick="closeModal('systemAlertModal')" 
                    class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="systemAlertForm" onsubmit="submitSystemAlert(event)">
            <div class="modal-body space-y-6">
                <!-- Alert Type & Urgency -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Alert Type *</label>
                        <select name="alert_type" class="dev-select" required onchange="updateAlertTemplate(this.value)">
                            <option value="">Select alert type</option>
                            <option value="maintenance">Maintenance Notification</option>
                            <option value="emergency">Emergency Alert</option>
                            <option value="issue">System Issue</option>
                            <option value="security">Security Alert</option>
                            <option value="performance">Performance Notice</option>
                            <option value="custom">Custom Alert</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Urgency Level *</label>
                        <select name="urgency" class="dev-select urgency-select" required>
                            <option value="">Select urgency</option>
                            <option value="info">Info - General information</option>
                            <option value="warning">Warning - Important notice</option>
                            <option value="urgent">Urgent - Immediate attention</option>
                            <option value="critical">Critical - Emergency</option>
                        </select>
                    </div>
                </div>
                
                <!-- Alert Message -->
                <div class="form-group">
                    <label class="form-label">Alert Message *</label>
                    <textarea name="message" 
                              rows="4" 
                              class="dev-textarea message-textarea" 
                              placeholder="Enter the alert message..."
                              required
                              maxlength="1000"></textarea>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-xs text-[var(--text-secondary)]">Maximum 1000 characters</span>
                        <span class="text-xs text-[var(--text-secondary)] message-counter">0/1000</span>
                    </div>
                </div>
                
                <!-- Delivery Channels -->
                <div class="form-group">
                    <label class="form-label mb-2">Delivery Channels *</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="channel-checkbox">
                            <input type="checkbox" name="channels[]" value="email" checked>
                            <span class="channel-label">
                                <i class="fas fa-envelope mr-2"></i>
                                Email
                            </span>
                        </label>
                        <label class="channel-checkbox">
                            <input type="checkbox" name="channels[]" value="sms">
                            <span class="channel-label">
                                <i class="fas fa-sms mr-2"></i>
                                SMS
                            </span>
                        </label>
                        <label class="channel-checkbox">
                            <input type="checkbox" name="channels[]" value="push" checked>
                            <span class="channel-label">
                                <i class="fas fa-bell mr-2"></i>
                                Push Notification
                            </span>
                        </label>
                        <label class="channel-checkbox">
                            <input type="checkbox" name="channels[]" value="in_app" checked>
                            <span class="channel-label">
                                <i class="fas fa-inbox mr-2"></i>
                                In-App
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Target Users -->
                <div class="form-group">
                    <label class="form-label mb-2">Target Users *</label>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="radio" id="allUsers" name="target_type" value="all" checked class="mr-2">
                            <label for="allUsers" class="cursor-pointer">
                                <span class="font-medium text-[var(--text-primary)]">All Active Users</span>
                                <p class="text-xs text-[var(--text-secondary)]">Send to all currently active users</p>
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="radio" id="userGroups" name="target_type" value="groups" class="mr-2">
                            <label for="userGroups" class="cursor-pointer">
                                <span class="font-medium text-[var(--text-primary)]">Specific User Groups</span>
                                <p class="text-xs text-[var(--text-secondary)]">Select specific user types</p>
                            </label>
                        </div>
                        
                        <div id="userGroupsSection" class="pl-6 hidden space-y-2">
                            <div class="flex flex-wrap gap-3">
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="super_admin">
                                    <span>Super Admin</span>
                                </label>
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="admin">
                                    <span>Admin</span>
                                </label>
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="landlord">
                                    <span>Landlord</span>
                                </label>
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="tenant">
                                    <span>Tenant</span>
                                </label>
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="field_agent">
                                    <span>Field Agent</span>
                                </label>
                                <label class="group-checkbox">
                                    <input type="checkbox" name="user_groups[]" value="security">
                                    <span>Security</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="radio" id="specificUsers" name="target_type" value="specific" class="mr-2">
                            <label for="specificUsers" class="cursor-pointer">
                                <span class="font-medium text-[var(--text-primary)]">Specific Users</span>
                                <p class="text-xs text-[var(--text-secondary)]">Select individual users</p>
                            </label>
                        </div>
                        
                        <div id="specificUsersSection" class="pl-6 hidden">
                            <div class="mb-2">
                                <input type="text" 
                                       class="dev-input" 
                                       placeholder="Search users by name or email..."
                                       id="userSearch">
                            </div>
                            <div id="userList" class="max-h-40 overflow-y-auto border rounded-lg p-2" 
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                <!-- User list will be populated dynamically -->
                                <div class="text-center py-4 text-[var(--text-secondary)]">
                                    <i class="fas fa-search mb-2"></i>
                                    <p>Search for users to select</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Schedule Options -->
                <div class="form-group">
                    <label class="form-label mb-2">Schedule Options</label>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="radio" id="sendNow" name="schedule" value="now" checked class="mr-2">
                            <label for="sendNow" class="cursor-pointer">
                                <span class="font-medium text-[var(--text-primary)]">Send Immediately</span>
                                <p class="text-xs text-[var(--text-secondary)]">Send the alert right now</p>
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="radio" id="scheduleLater" name="schedule" value="later" class="mr-2">
                            <label for="scheduleLater" class="cursor-pointer">
                                <span class="font-medium text-[var(--text-primary)]">Schedule for Later</span>
                                <p class="text-xs text-[var(--text-secondary)]">Send at a specific time</p>
                            </label>
                        </div>
                        
                        <div id="scheduleTimeSection" class="pl-6 hidden">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="form-label">Date</label>
                                    <input type="date" 
                                           name="scheduled_date" 
                                           class="dev-input"
                                           min="{{ date('Y-m-d') }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Time</label>
                                    <input type="time" 
                                           name="scheduled_time" 
                                           class="dev-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Section -->
                <div class="form-group">
                    <div class="flex justify-between items-center mb-2">
                        <label class="form-label">Alert Preview</label>
                        <button type="button" 
                                onclick="updateAlertPreview()" 
                                class="text-xs flex items-center text-[var(--primary)] hover:underline">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Update Preview
                        </button>
                    </div>
                    <div id="alertPreview" class="p-4 rounded-lg border preview-container">
                        <div class="preview-header mb-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 bg-[var(--primary)] text-white">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold preview-title">System Alert</div>
                                        <div class="text-xs text-[var(--text-secondary)]">Just now</div>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full urgency-badge">INFO</span>
                            </div>
                        </div>
                        <div class="preview-message text-[var(--text-primary)]">
                            Your alert message will appear here...
                        </div>
                        <div class="mt-3 text-xs text-[var(--text-secondary)]">
                            <i class="fas fa-info-circle mr-1"></i>
                            This is how the alert will appear to users
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" 
                        onclick="closeModal('systemAlertModal')"
                        class="btn-secondary">
                    Cancel
                </button>
                <button type="button" 
                        onclick="testAlert()"
                        class="btn-info">
                    <i class="fas fa-vial mr-2"></i>
                    Send Test
                </button>
                <button type="submit" 
                        class="btn-primary"
                        id="sendAlertButton">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Alert
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* System Alert Specific Styles */
.urgency-select option[value="info"] { color: var(--info); }
.urgency-select option[value="warning"] { color: var(--warning); }
.urgency-select option[value="urgent"] { color: var(--warning); font-weight: bold; }
.urgency-select option[value="critical"] { color: var(--danger); font-weight: bold; }

.channel-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    transition: all 0.3s ease;
}

.channel-checkbox:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
}

.channel-checkbox input:checked + .channel-label {
    color: var(--primary);
    font-weight: 500;
}

.channel-checkbox input:checked ~ .channel-label::before {
    content: '✓ ';
    margin-right: 0.25rem;
}

.channel-label {
    display: flex;
    align-items: center;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.group-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.group-checkbox:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
}

.group-checkbox input:checked + span {
    color: var(--primary);
    font-weight: 500;
}

.group-checkbox input:checked ~ span::before {
    content: '✓ ';
    margin-right: 0.25rem;
}

.preview-container {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
}

.preview-title {
    color: var(--text-primary);
    font-size: 0.875rem;
}

.urgency-badge {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
    font-weight: 600;
}

.urgency-badge.warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.3);
}

.urgency-badge.urgent {
    background-color: rgba(var(--warning-rgb), 0.2);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.4);
    animation: pulse 2s infinite;
}

.urgency-badge.critical {
    background-color: rgba(var(--danger-rgb), 0.2);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.4);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.btn-info {
    background: linear-gradient(to right, var(--info), rgba(var(--info-rgb), 0.8));
    color: white;
    padding: 0.5rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--info-rgb), 0.3);
}

.message-textarea {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.5;
}

/* User list styling */
.user-list-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 0.5rem;
    margin-bottom: 0.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-list-item:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
}

.user-list-item.selected {
    background-color: rgba(var(--primary-rgb), 0.15);
    border: 1px solid var(--primary);
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 0.75rem;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.user-email {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.user-type {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}
</style>

<script>
// Alert templates for different types
const alertTemplates = {
    maintenance: {
        title: "🚧 System Maintenance Notification",
        message: "Our system will undergo scheduled maintenance to improve performance and reliability. Some features may be temporarily unavailable during this period.\n\nMaintenance Window: [Start Time] to [End Time]\nImpact Level: Low\n\nWe apologize for any inconvenience and appreciate your understanding."
    },
    emergency: {
        title: "🚨 EMERGENCY SYSTEM ALERT",
        message: "CRITICAL SYSTEM ISSUE DETECTED\n\nOur technical team is investigating an urgent system issue that may affect service availability. We are working to resolve this as quickly as possible.\n\nPlease avoid performing critical operations until further notice.\n\nWe will provide updates as soon as more information is available."
    },
    issue: {
        title: "⚠️ System Issue Notification",
        message: "We are currently experiencing technical difficulties with certain system features. Our team is actively working on a resolution.\n\nAffected Services: [List affected services]\nEstimated Resolution: [Time]\n\nThank you for your patience while we resolve this issue."
    },
    security: {
        title: "🛡️ Security Alert",
        message: "IMPORTANT SECURITY NOTICE\n\nWe have detected unusual activity on our systems. As a precautionary measure, we recommend changing your password immediately.\n\nNo data breaches have been confirmed, but we are taking proactive steps to ensure system security.\n\nIf you notice any suspicious activity, please contact support immediately."
    },
    performance: {
        title: "⚡ Performance Notice",
        message: "System Performance Advisory\n\nWe are currently experiencing higher than usual system load, which may result in slower response times. Our team is monitoring the situation and working to optimize performance.\n\nWe appreciate your patience and will restore normal operations as soon as possible."
    },
    custom: {
        title: "System Alert",
        message: ""
    }
};

// Sample users for demo (in production, fetch from API)
const sampleUsers = [
    { id: 1, name: "John Doe", email: "john@example.com", type: "admin", initials: "JD" },
    { id: 2, name: "Jane Smith", email: "jane@example.com", type: "landlord", initials: "JS" },
    { id: 3, name: "Bob Johnson", email: "bob@example.com", type: "tenant", initials: "BJ" },
    { id: 4, name: "Alice Brown", email: "alice@example.com", type: "field_agent", initials: "AB" },
    { id: 5, name: "Charlie Wilson", email: "charlie@example.com", type: "security", initials: "CW" },
    { id: 6, name: "David Miller", email: "david@example.com", type: "super_admin", initials: "DM" }
];

let selectedUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    initializeSystemAlertModal();
});

function initializeSystemAlertModal() {
    // Character counter for message textarea
    const messageTextarea = document.querySelector('.message-textarea');
    const messageCounter = document.querySelector('.message-counter');
    
    if (messageTextarea && messageCounter) {
        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            messageCounter.textContent = `${length}/1000`;
            
            if (length > 1000) {
                messageCounter.style.color = 'var(--danger)';
            } else if (length > 800) {
                messageCounter.style.color = 'var(--warning)';
            } else {
                messageCounter.style.color = 'var(--text-secondary)';
            }
        });
    }
    
    // Target type selection
    document.querySelectorAll('input[name="target_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const value = this.value;
            const groupsSection = document.getElementById('userGroupsSection');
            const specificSection = document.getElementById('specificUsersSection');
            
            if (groupsSection) groupsSection.classList.add('hidden');
            if (specificSection) specificSection.classList.add('hidden');
            
            if (value === 'groups') {
                if (groupsSection) groupsSection.classList.remove('hidden');
            } else if (value === 'specific') {
                if (specificSection) specificSection.classList.remove('hidden');
                loadUserList();
            }
        });
    });
    
    // Schedule options
    document.querySelectorAll('input[name="schedule"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const scheduleSection = document.getElementById('scheduleTimeSection');
            if (scheduleSection) {
                if (this.value === 'later') {
                    scheduleSection.classList.remove('hidden');
                } else {
                    scheduleSection.classList.add('hidden');
                }
            }
        });
    });
    
    // User search
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
        userSearch.addEventListener('input', debounce(function(e) {
            searchUsers(e.target.value);
        }, 300));
    }
    
    // Initialize preview
    updateAlertPreview();
}

function updateAlertTemplate(alertType) {
    const template = alertTemplates[alertType];
    if (template) {
        const messageTextarea = document.querySelector('.message-textarea');
        if (messageTextarea) {
            messageTextarea.value = template.message;
            messageTextarea.dispatchEvent(new Event('input'));
        }
        updateAlertPreview();
    }
}

function loadUserList(searchTerm = '') {
    const userList = document.getElementById('userList');
    if (!userList) return;
    
    // Filter users based on search term
    const filteredUsers = sampleUsers.filter(user => 
        user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.type.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filteredUsers.length === 0) {
        userList.innerHTML = `
            <div class="text-center py-4 text-[var(--text-secondary)]">
                <i class="fas fa-users-slash mb-2"></i>
                <p>No users found</p>
            </div>
        `;
        return;
    }
    
    userList.innerHTML = filteredUsers.map(user => `
        <div class="user-list-item ${selectedUsers.includes(user.id) ? 'selected' : ''}" 
             onclick="toggleUserSelection(${user.id})">
            <div class="user-avatar">${user.initials}</div>
            <div class="user-info">
                <div class="user-name">${user.name}</div>
                <div class="user-email">${user.email}</div>
            </div>
            <div class="user-type">${user.type.replace('_', ' ')}</div>
        </div>
    `).join('');
}

function searchUsers(searchTerm) {
    loadUserList(searchTerm);
}

function toggleUserSelection(userId) {
    const index = selectedUsers.indexOf(userId);
    if (index === -1) {
        selectedUsers.push(userId);
    } else {
        selectedUsers.splice(index, 1);
    }
    loadUserList(document.getElementById('userSearch')?.value || '');
}

function updateAlertPreview() {
    const urgency = document.querySelector('select[name="urgency"]')?.value || 'info';
    const message = document.querySelector('.message-textarea')?.value || 'Your alert message will appear here...';
    const alertType = document.querySelector('select[name="alert_type"]')?.value || 'custom';
    
    const preview = document.getElementById('alertPreview');
    if (!preview) return;
    
    const title = alertTemplates[alertType]?.title || 'System Alert';
    const urgencyBadge = preview.querySelector('.urgency-badge');
    const previewTitle = preview.querySelector('.preview-title');
    const previewMessage = preview.querySelector('.preview-message');
    
    if (urgencyBadge) {
        urgencyBadge.textContent = urgency.toUpperCase();
        urgencyBadge.className = 'px-2 py-1 text-xs rounded-full urgency-badge';
        
        switch(urgency) {
            case 'warning':
                urgencyBadge.classList.add('warning');
                break;
            case 'urgent':
                urgencyBadge.classList.add('urgent');
                break;
            case 'critical':
                urgencyBadge.classList.add('critical');
                break;
            default:
                // 'info' - default styling
                break;
        }
    }
    
    if (previewTitle) {
        previewTitle.textContent = title;
    }
    
    if (previewMessage) {
        // Convert line breaks to HTML
        const formattedMessage = message.replace(/\n/g, '<br>');
        previewMessage.innerHTML = formattedMessage;
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function testAlert() {
    const form = document.getElementById('systemAlertForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Validate required fields
    if (!data.message || !data.urgency || !data.alert_type) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    if (!data.channels || data.channels.length === 0) {
        showNotification('Please select at least one delivery channel', 'warning');
        return;
    }
    
    // Show test alert preview
    showNotification('Test alert would be sent to your account', 'info');
    
    // Simulate API call
    setTimeout(() => {
        showNotification('Test alert sent successfully! Check your notifications.', 'success');
    }, 1000);
}

function submitSystemAlert(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Get selected channels
    const channels = Array.from(form.querySelectorAll('input[name="channels[]"]:checked'))
        .map(checkbox => checkbox.value);
    
    // Get selected user groups
    const userGroups = Array.from(form.querySelectorAll('input[name="user_groups[]"]:checked'))
        .map(checkbox => checkbox.value);
    
    // Validate
    if (!data.message || !data.urgency || !data.alert_type) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }
    
    if (channels.length === 0) {
        showNotification('Please select at least one delivery channel', 'error');
        return;
    }
    
    if (data.target_type === 'groups' && userGroups.length === 0) {
        showNotification('Please select at least one user group', 'error');
        return;
    }
    
    if (data.target_type === 'specific' && selectedUsers.length === 0) {
        showNotification('Please select at least one user', 'error');
        return;
    }
    
    // Prepare payload
    const payload = {
        message: data.message,
        urgency: data.urgency,
        alert_type: data.alert_type,
        channels: channels,
        target_type: data.target_type,
        user_groups: userGroups,
        user_ids: data.target_type === 'specific' ? selectedUsers : null,
        schedule: data.schedule,
        scheduled_date: data.scheduled_date || null,
        scheduled_time: data.scheduled_time || null
    };
    
    // Show loading state
    const submitButton = document.getElementById('sendAlertButton');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    submitButton.disabled = true;
    
    // Send to API
    fetch('/developer/alerts/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Alert sent successfully!', 'success');
            
            // Close modal and reset form after delay
            setTimeout(() => {
                closeModal('systemAlertModal');
                form.reset();
                selectedUsers = [];
                updateAlertPreview();
            }, 1500);
        } else {
            showNotification('Failed to send alert: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to send alert: ' + error.message, 'error');
    })
    .finally(() => {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// Helper function to show notifications
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification';
    
    let bgColor = 'var(--info)';
    let icon = 'fas fa-info-circle';
    
    switch(type) {
        case 'success':
            bgColor = 'var(--success)';
            icon = 'fas fa-check-circle';
            notification.classList.add('success');
            break;
        case 'error':
            bgColor = 'var(--danger)';
            icon = 'fas fa-exclamation-circle';
            notification.classList.add('error');
            break;
        case 'warning':
            bgColor = 'var(--warning)';
            icon = 'fas fa-exclamation-triangle';
            notification.classList.add('warning');
            break;
    }
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 16px;
        border-radius: 8px;
        background-color: ${bgColor};
        color: white;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        min-width: 300px;
        max-width: 400px;
    `;
    
    notification.innerHTML = `
        <i class="${icon} mr-3"></i>
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" 
                class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Remove any existing notifications
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Add CSS for notification animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
if (!document.querySelector('style[data-notification-animations]')) {
    style.setAttribute('data-notification-animations', 'true');
    document.head.appendChild(style);
}
</script>