<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: var(--text-primary);" id="bulkDeleteModalLabel">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                    Bulk Delete Confirmation
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
                        <p class="font-medium" style="color: var(--text-primary);">Delete Selected Shifts</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Are you sure you want to delete <span id="selectedCount" class="font-bold">0</span> shift(s)?
                        </p>
                    </div>
                </div>
                
                <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid var(--border-color);">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--warning);"></i>
                        <strong>Important:</strong>
                    </p>
                    <ul class="text-sm space-y-1" style="color: var(--text-secondary); padding-left: 20px;">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-xs mr-2" style="color: var(--success);"></i>
                            Only shifts not in use can be deleted
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-xs mr-2" style="color: var(--success);"></i>
                            This action cannot be undone
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-xs mr-2" style="color: var(--success);"></i>
                            Schedules using deleted shifts will be affected
                        </li>
                    </ul>
                </div>
                
                <div id="inUseWarning" class="p-3 rounded-lg mb-3 hidden" 
                     style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
                    <p class="text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span id="inUseMessage"></span>
                    </p>
                </div>
                
                <div id="selectedShiftsList" class="max-h-40 overflow-y-auto text-sm" style="color: var(--text-secondary);">
                    <!-- Selected shifts will be listed here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 rounded-lg border transition-all duration-200"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                        data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" 
                        id="confirmBulkDelete"
                        class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: var(--danger); color: white;"
                        disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>