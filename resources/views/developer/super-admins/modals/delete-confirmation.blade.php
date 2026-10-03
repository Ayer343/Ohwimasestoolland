{{-- developer/super-admins/modals/delete-confirmation.blade.php --}}
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="hideDeleteModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <div class="flex items-center">
                <div class="modal-icon" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div>
                    <h3 class="modal-title">Confirm Deletion</h3>
                    <p class="modal-subtitle">Delete Super Admin account</p>
                </div>
            </div>
            <button type="button" onclick="hideDeleteModal()" class="modal-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="deleteForm" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <!-- User Information -->
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <div class="flex items-center">
                        <div class="user-avatar-large mr-3" style="background: linear-gradient(135deg, var(--danger), #dc3545);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold truncate" style="color: var(--text-primary);" id="deleteUserName">
                                Loading...
                            </h4>
                            <p class="text-sm truncate" style="color: var(--text-secondary);" id="deleteUserEmail">
                                Loading...
                            </p>
                            <p class="text-xs mt-1 truncate" style="color: var(--text-secondary);" id="deleteUserRole">
                                Super Administrator
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Warning Messages -->
                <div class="space-y-4">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div>
                                <h4 class="font-medium mb-1" style="color: var(--warning);">Irreversible Action</h4>
                                <ul class="text-sm space-y-1 mt-2" style="color: var(--text-secondary);">
                                    <li class="flex items-start">
                                        <i class="fas fa-circle text-xs mr-2 mt-1" style="color: var(--warning);"></i>
                                        <span>This action cannot be undone</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-circle text-xs mr-2 mt-1" style="color: var(--warning);"></i>
                                        <span>All Super Admin data will be permanently deleted</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-circle text-xs mr-2 mt-1" style="color: var(--warning);"></i>
                                        <span>Associated records may be affected</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Critical Relations Warning -->
                    <div id="criticalRelationsWarning" class="hidden p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-ban mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div>
                                <h4 class="font-medium mb-1" style="color: var(--danger);">Cannot Delete</h4>
                                <p class="text-sm" style="color: var(--text-secondary);" id="criticalRelationsMessage">
                                    This Super Admin has critical relationships that prevent deletion.
                                </p>
                                <div class="mt-2">
                                    <a href="#" onclick="hideDeleteModal()" class="text-primary hover:underline text-sm">
                                        <i class="fas fa-info-circle mr-1"></i> View details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Safe to Delete Message -->
                    <div id="safeToDeleteMessage" class="hidden p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <div>
                                <h4 class="font-medium mb-1" style="color: var(--success);">Safe to Delete</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    No critical relationships found. This Super Admin can be safely deleted.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deletion Reason -->
                    <div>
                        <label class="form-label">
                            <i class="fas fa-clipboard mr-2" style="color: var(--danger);"></i> Deletion Reason (Optional)
                        </label>
                        <textarea name="deletion_reason" 
                                  rows="2" 
                                  class="custom-textarea"
                                  placeholder="Optional: Add a reason for deletion for audit purposes..."></textarea>
                    </div>
                    
                    <!-- Confirmation -->
                    <div>
                        <label class="form-label">
                            <i class="fas fa-shield-alt mr-2" style="color: var(--danger);"></i> Confirmation
                        </label>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="text-sm mb-3" style="color: var(--text-secondary);" id="confirmationMessage">
                                Type exactly "DELETE" (without quotes) to confirm deletion of this Super Admin.
                            </p>
                            <div class="relative">
                                <input type="text" 
                                       id="delete_confirmation" 
                                       name="delete_confirmation" 
                                       class="custom-input w-full pl-10"
                                       placeholder="Type DELETE here"
                                       oninput="checkDeleteConfirmation(this)">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-key" style="color: var(--danger);"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <p class="text-xs" style="color: var(--text-secondary);" id="confirmationFeedback">
                                    <!-- Feedback will appear here -->
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alternative Actions -->
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1"></i> Alternative Actions
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button type="button" onclick="hideDeleteModal()" class="btn btn-secondary text-sm py-2">
                                <i class="fas fa-times mr-2"></i> Cancel Deletion
                            </button>
                            <button type="button" onclick="deactivateInstead()" class="btn btn-warning text-sm py-2">
                                <i class="fas fa-user-minus mr-2"></i> Deactivate Instead
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="hideDeleteModal()" 
                        class="btn btn-secondary">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" 
                        id="confirmDeleteBtn"
                        class="btn btn-danger disabled"
                        disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Delete Super Admin
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
let currentUserId = null;
let currentUserName = null;

function initDeleteModal() {
    const deleteConfirmationInput = document.getElementById('delete_confirmation');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const confirmationMessage = document.getElementById('confirmationMessage');
    const confirmationFeedback = document.getElementById('confirmationFeedback');
    
    if (deleteConfirmationInput) {
        deleteConfirmationInput.addEventListener('input', function(e) {
            checkDeleteConfirmation(this);
        });
        
        // Clear input when modal is shown
        deleteConfirmationInput.value = '';
    }
    
    // Form submission handler
    const deleteForm = document.getElementById('deleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirmDeleteBtn.disabled) {
                // Show loading state
                const originalText = confirmDeleteBtn.innerHTML;
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
                
                // Submit form
                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new FormData(this)
                })
                .then(response => {
                    if (response.status === 419) {
                        // CSRF token expired
                        showToast('Session expired. Please refresh the page.', 'error');
                        location.reload();
                        return;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        showToast('Super Admin deleted successfully!', 'success');
                        setTimeout(() => {
                            hideDeleteModal();
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else {
                                location.reload();
                            }
                        }, 1500);
                    } else if (data) {
                        showToast('Error: ' + (data.message || 'Failed to delete'), 'error');
                        confirmDeleteBtn.innerHTML = originalText;
                        confirmDeleteBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to delete Super Admin. Please try again.', 'error');
                    confirmDeleteBtn.innerHTML = originalText;
                    confirmDeleteBtn.disabled = false;
                });
            }
        });
    }
}

function checkDeleteConfirmation(input) {
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const confirmationFeedback = document.getElementById('confirmationFeedback');
    const criticalRelationsWarning = document.getElementById('criticalRelationsWarning');
    const safeToDeleteMessage = document.getElementById('safeToDeleteMessage');
    
    if (!confirmDeleteBtn || !confirmationFeedback) return;
    
    const inputValue = input.value.trim();
    
    if (inputValue === 'DELETE') {
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.classList.remove('disabled');
        confirmationFeedback.innerHTML = 
            '<i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> ' +
            '<span style="color: var(--success);">Confirmation matches. Ready to delete.</span>';
        
        // Check if safe to delete
        if (criticalRelationsWarning.classList.contains('hidden')) {
            safeToDeleteMessage.classList.remove('hidden');
        }
    } else {
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.classList.add('disabled');
        
        if (inputValue === '') {
            confirmationFeedback.innerHTML = 
                '<i class="fas fa-info-circle mr-1" style="color: var(--info);"></i> ' +
                '<span style="color: var(--info);">Type exactly "DELETE" to confirm deletion</span>';
        } else {
            confirmationFeedback.innerHTML = 
                '<i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i> ' +
                `<span style="color: var(--danger);">Incorrect: "${inputValue}". Type exactly "DELETE"</span>`;
        }
        
        safeToDeleteMessage.classList.add('hidden');
    }
}

function deactivateInstead() {
    if (currentUserId && confirm(`Deactivate Super Admin "${currentUserName}" instead of deleting?`)) {
        window.location.href = `/developer/super-admins/${currentUserId}/deactivate`;
    }
}

function loadUserDataForDeletion(userId, userName) {
    currentUserId = userId;
    currentUserName = userName;
    
    // Update user info in modal
    document.getElementById('deleteUserName').textContent = userName || 'Super Admin';
    
    // Load user details
    fetch(`/developer/super-admins/${userId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('deleteUserEmail').textContent = data.user.email || 'No email';
                
                // Check relations
                checkUserRelations(userId);
            }
        })
        .catch(error => {
            console.error('Error loading user details:', error);
        });
}

function checkUserRelations(userId) {
    const criticalRelationsWarning = document.getElementById('criticalRelationsWarning');
    const safeToDeleteMessage = document.getElementById('safeToDeleteMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    fetch(`/developer/super-admins/${userId}/check-relations`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.has_critical_relations) {
                    // Show critical relations warning
                    criticalRelationsWarning.classList.remove('hidden');
                    safeToDeleteMessage.classList.add('hidden');
                    
                    const relationsList = data.critical_relations.map(rel => 
                        `<li class="flex items-start">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5 text-xs" style="color: var(--danger);"></i>
                            <span class="text-sm">${rel}</span>
                        </li>`
                    ).join('');
                    
                    document.getElementById('criticalRelationsMessage').innerHTML = 
                        `This Super Admin has ${data.critical_relations.length} critical relationship(s):`;
                    
                    // Disable delete button
                    confirmDeleteBtn.disabled = true;
                    confirmDeleteBtn.classList.add('disabled');
                    
                    // Update confirmation message
                    document.getElementById('confirmationMessage').innerHTML = 
                        '<span class="text-danger">Cannot delete:</span> Critical relationships prevent deletion.';
                    
                } else {
                    // Show safe to delete message
                    criticalRelationsWarning.classList.add('hidden');
                    safeToDeleteMessage.classList.remove('hidden');
                    
                    // Update confirmation message
                    document.getElementById('confirmationMessage').innerHTML = 
                        'Type exactly "DELETE" (without quotes) to confirm deletion of this Super Admin.';
                }
            }
        })
        .catch(error => {
            console.error('Error checking relations:', error);
            showToast('Failed to check user relations.', 'error');
        });
}

// Initialize when modal is shown
document.addEventListener('modal-shown', function(e) {
    if (e.detail && e.detail.modalId === 'deleteModal') {
        initDeleteModal();
    }
});
</script>
@endpush

@push('styles')
<style>
/* Additional styles for delete confirmation modal */
.user-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Danger button styles */
.btn-danger {
    background-color: var(--danger);
    color: white;
    border: 1px solid var(--danger);
    transition: all 0.2s ease;
}

.btn-danger:hover:not(:disabled):not(.disabled) {
    background-color: #dc3545;
    border-color: #dc3545;
    transform: translateY(-1px);
}

.btn-danger.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: var(--danger);
}

.btn-danger.disabled:hover {
    transform: none;
}

/* Warning button styles */
.btn-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

/* Confirmation feedback styles */
#confirmationFeedback {
    min-height: 1.5rem;
    display: flex;
    align-items: center;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .modal-container {
        width: 95%;
        margin: 0.5rem;
    }
    
    .user-avatar-large {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .modal-footer {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}
</style>
@endpush