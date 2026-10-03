{{-- resources/views/developer/api/modals/delete-confirmation.blade.php --}}
<div id="deleteLogModal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-container max-w-md">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header border-b-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                </div>
                <h3 class="modal-title text-center">
                    Delete API Log
                </h3>
            </div>

            <!-- Modal Body -->
            <div class="modal-body text-center">
                <p style="color: var(--text-primary);" class="mb-4">
                    Are you sure you want to delete this API log entry?
                </p>
                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                    This action cannot be undone. The log entry will be permanently removed.
                </p>
                
                <form id="deleteLogForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="space-y-4">
                        <div>
                            <label for="delete_confirmation_log" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                Type "DELETE" to confirm:
                            </label>
                            <input type="text" 
                                   id="delete_confirmation_log" 
                                   name="confirmation"
                                   class="form-input text-center"
                                   placeholder="DELETE"
                                   oninput="checkDeleteLogConfirmation(this)">
                            <p id="confirmationMessageLog" class="text-sm mt-2"></p>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteLogModal()">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" 
                        form="deleteLogForm" 
                        id="confirmDeleteLogBtn" 
                        class="btn btn-danger disabled"
                        disabled>
                    <i class="fas fa-trash mr-2"></i> Delete Log
                </button>
            </div>
        </div>
    </div>
</div>