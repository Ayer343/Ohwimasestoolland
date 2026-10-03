{{-- developer/super-admins/modals/invitation.blade.php --}}
<!-- Invitation Modal -->
<div id="invitationModal" class="modal hidden">
    <div class="modal-overlay" onclick="hideInvitationModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <div class="flex items-center">
                <div class="modal-icon">
                    <i class="fas fa-paper-plane" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h3 class="modal-title">Send Invitation</h3>
                    <p class="modal-subtitle">Send account invitation to Super Admin</p>
                </div>
            </div>
            <button type="button" onclick="hideInvitationModal()" class="modal-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="invitationForm" method="POST" action="">
            @csrf
            <div class="modal-body">
                <!-- User Information -->
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <div class="flex items-center">
                        <div class="user-avatar-large mr-3">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);" id="inviteUserName">
                                Loading...
                            </h4>
                            <p class="text-sm" style="color: var(--text-secondary);" id="inviteUserEmail">
                                Loading...
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);" id="inviteUserPhone">
                                Loading...
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Communication Channels -->
                <div class="mb-6">
                    <label class="form-label">
                        <i class="fas fa-broadcast-tower mr-2" style="color: var(--primary);"></i> Communication Channels
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="invitationChannels">
                        @foreach(['email', 'sms', 'whatsapp'] as $channel)
                        <div class="channel-option">
                            <input type="checkbox" 
                                   id="channel{{ ucfirst($channel) }}" 
                                   name="invitation_channels[]" 
                                   value="{{ $channel }}"
                                   class="channel-checkbox"
                                   {{ $channel == 'email' ? 'checked' : '' }}
                                   {{ !($communicationStatus[$channel]['enabled'] ?? false) ? 'disabled' : '' }}>
                            <label for="channel{{ ucfirst($channel) }}" class="channel-label">
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
                    
                    <!-- Channel Selection Error -->
                    <div id="channelError" class="hidden mt-2 p-2 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                            <span class="text-xs" style="color: var(--danger);" id="channelErrorMessage">
                                Please select at least one channel
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Invitation Settings -->
                <div class="space-y-4">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-calendar mr-2" style="color: var(--primary);"></i> Expiration Period
                        </label>
                        <div class="flex items-center space-x-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="number" 
                                           name="expires_in_days" 
                                           value="7" 
                                           min="1" 
                                           max="30"
                                           class="custom-input w-full pl-10">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-calendar-day" style="color: var(--text-secondary);"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm whitespace-nowrap" style="color: var(--text-secondary);">
                                days
                            </div>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            The invitation link will expire after this period
                        </p>
                    </div>
                    
                    <div>
                        <label class="form-label">
                            <i class="fas fa-comment-alt mr-2" style="color: var(--primary);"></i> Custom Message (Optional)
                        </label>
                        <textarea name="custom_message" 
                                  rows="3" 
                                  class="custom-textarea"
                                  placeholder="Add a personal message to welcome the Super Admin..."></textarea>
                    </div>
                </div>
                
                <!-- Preview Information -->
                <div id="invitationPreview" class="hidden mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <h4 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-eye mr-2" style="color: var(--info);"></i> Invitation Preview
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Channels:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);" id="previewChannels">
                                Email
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Expires In:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);" id="previewExpiry">
                                7 days
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm" style="color: var(--text-secondary);">Status:</span>
                            <span class="text-sm font-medium badge badge-success" id="previewStatus">
                                Ready to send
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Service Status -->
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                        <span>Service Status:</span>
                        <div class="flex items-center space-x-3">
                            <span class="flex items-center">
                                <i class="fas fa-envelope mr-1 {{ $emailEnabled ? 'text-success' : 'text-danger' }}"></i>
                                Email
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-sms mr-1 {{ $smsEnabled ? 'text-success' : 'text-danger' }}"></i>
                                SMS
                            </span>
                            <span class="flex items-center">
                                <i class="fab fa-whatsapp mr-1 {{ $whatsappEnabled ? 'text-success' : 'text-danger' }}"></i>
                                WhatsApp
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="hideInvitationModal()" 
                        class="btn btn-secondary">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" 
                        id="sendInvitationBtn"
                        class="btn btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initInvitationModal();
});

function initInvitationModal() {
    const invitationForm = document.getElementById('invitationForm');
    const invitationPreview = document.getElementById('invitationPreview');
    const channelCheckboxes = document.querySelectorAll('#invitationChannels .channel-checkbox');
    const expiresInInput = document.querySelector('input[name="expires_in_days"]');
    const channelError = document.getElementById('channelError');
    const sendInvitationBtn = document.getElementById('sendInvitationBtn');
    
    // Update preview when inputs change
    function updatePreview() {
        const selectedChannels = Array.from(channelCheckboxes)
            .filter(cb => cb.checked && !cb.disabled)
            .map(cb => cb.value.charAt(0).toUpperCase() + cb.value.slice(1));
        
        const expiresIn = expiresInInput.value;
        
        // Update preview elements
        document.getElementById('previewChannels').textContent = selectedChannels.join(', ') || 'None';
        document.getElementById('previewExpiry').textContent = `${expiresIn} days`;
        
        // Validate channels
        if (selectedChannels.length === 0) {
            channelError.classList.remove('hidden');
            sendInvitationBtn.disabled = true;
            sendInvitationBtn.classList.add('disabled');
            document.getElementById('previewStatus').innerHTML = 
                '<span class="badge badge-danger">Select channels</span>';
            invitationPreview.classList.remove('hidden');
        } else {
            channelError.classList.add('hidden');
            sendInvitationBtn.disabled = false;
            sendInvitationBtn.classList.remove('disabled');
            document.getElementById('previewStatus').innerHTML = 
                '<span class="badge badge-success">Ready to send</span>';
            invitationPreview.classList.remove('hidden');
        }
    }
    
    // Add event listeners
    channelCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updatePreview);
    });
    
    if (expiresInInput) {
        expiresInInput.addEventListener('input', updatePreview);
        expiresInInput.addEventListener('change', updatePreview);
    }
    
    // Form submission
    if (invitationForm) {
        invitationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedChannels = Array.from(channelCheckboxes)
                .filter(cb => cb.checked && !cb.disabled)
                .map(cb => cb.value);
            
            if (selectedChannels.length === 0) {
                channelError.classList.remove('hidden');
                document.getElementById('channelErrorMessage').textContent = 
                    'Please select at least one communication channel';
                return;
            }
            
            // Show loading state
            const originalText = sendInvitationBtn.innerHTML;
            sendInvitationBtn.disabled = true;
            sendInvitationBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
            
            // Submit form
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Invitation sent successfully!', 'success');
                    setTimeout(() => {
                        hideInvitationModal();
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + data.message, 'error');
                    sendInvitationBtn.innerHTML = originalText;
                    sendInvitationBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to send invitation. Please try again.', 'error');
                sendInvitationBtn.innerHTML = originalText;
                sendInvitationBtn.disabled = false;
            });
        });
    }
    
    // Initial preview update
    setTimeout(updatePreview, 100);
}

// Function to load user data into modal
function loadUserDataForInvitation(userId) {
    fetch(`/developer/super-admins/${userId}/invitation-status`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update user info
                document.getElementById('inviteUserName').textContent = data.user.name || 'Super Admin';
                document.getElementById('inviteUserEmail').textContent = data.user.email || 'No email';
                document.getElementById('inviteUserPhone').textContent = 
                    data.user.phone ? `Phone: ${data.user.phone}` : 'No phone number';
                
                // Update available channels
                const availableChannels = data.available_channels || [];
                document.querySelectorAll('#invitationChannels .channel-checkbox').forEach(checkbox => {
                    const channel = checkbox.value;
                    const isAvailable = availableChannels.includes(channel);
                    const isEnabled = {{ $channel == 'email' ? 'true' : 'false' }}; // Use PHP variables
                    
                    checkbox.disabled = !isAvailable;
                    checkbox.checked = isAvailable && checkbox.value === 'email';
                    
                    const label = checkbox.nextElementSibling;
                    const statusSpan = label.querySelector('.channel-status');
                    
                    if (!isAvailable) {
                        statusSpan.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Not available';
                        statusSpan.className = 'channel-status text-danger text-xs';
                    } else if (!isEnabled) {
                        statusSpan.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Service offline';
                        statusSpan.className = 'channel-status text-warning text-xs';
                    } else {
                        statusSpan.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Available';
                        statusSpan.className = 'channel-status text-success text-xs';
                    }
                });
                
                // Update preview
                if (typeof updatePreview === 'function') {
                    updatePreview();
                }
            }
        })
        .catch(error => {
            console.error('Error loading user data:', error);
            showToast('Failed to load user information', 'error');
        });
}
</script>
@endpush

@push('styles')
<style>
.user-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Custom styles for invitation modal */
#invitationPreview .badge-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.3);
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

#invitationPreview .badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive adjustments for invitation modal */
@media (max-width: 640px) {
    .user-avatar-large {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .channel-label {
        padding: 0.5rem;
    }
    
    .channel-icon {
        width: 32px;
        height: 32px;
        margin-right: 0.5rem;
    }
}
</style>
@endpush