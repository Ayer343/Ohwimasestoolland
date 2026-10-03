<div id="approveModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check mr-2"></i> Approve Maintenance
                </h3>
                <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" 
                        class="p-2 rounded-lg hover:bg-opacity-10"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="{{ route('developer.maintenance.approve', $maintenance->id) }}" method="POST">
                @csrf
                
                <div class="mb-4">
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        You are about to approve this maintenance schedule. Once approved, notifications will be sent to all affected users.
                    </p>
                    
                    <div class="p-3 rounded-lg mb-3" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                            This action cannot be undone. Please review all details before proceeding.
                        </p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="approval_notes" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Approval Notes (Optional)
                    </label>
                    <textarea id="approval_notes" name="approval_notes" rows="3"
                              class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Add any notes or comments about this approval..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')"
                            class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check mr-2"></i> Approve Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>