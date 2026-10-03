<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);" id="deleteModalLabel">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                    Confirm Deletion
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="flex items-center mb-4">
                    <div class="p-3 rounded-full mr-3" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                    </div>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Are you sure you want to delete this shift?</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            This action cannot be undone. All schedules using this shift will need to be reassigned.
                        </p>
                    </div>
                </div>
                
                <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid var(--border-color);">
                    <p class="text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                        <strong>Warning:</strong> If this shift is currently in use by schedules or templates, it cannot be deleted.
                    </p>
                </div>
                
                <div id="shiftDetails" class="text-sm" style="color: var(--text-secondary);">
                    <!-- Shift details will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 rounded-lg border transition-all duration-200"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                        data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Shift
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>