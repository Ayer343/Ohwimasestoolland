<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                Delete Maintenance
            </h3>
            
            <form action="{{ route('developer.maintenance.destroy', $maintenance->id) }}" method="POST">
                @csrf
                @method('DELETE')
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Confirmation
                        </label>
                        <p class="text-sm mb-4" style="color: var(--text-primary);">
                            Are you sure you want to delete maintenance 
                            <strong>"{{ $maintenance->title }}"</strong>?
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            Reference: {{ $maintenance->reference_id }}<br>
                            Type: {{ ucfirst($maintenance->maintenance_type) }}<br>
                            Status: {{ ucfirst(str_replace('_', ' ', $maintenance->status)) }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Delete Reason (Optional)
                        </label>
                        <textarea name="delete_reason" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you deleting this maintenance?..."></textarea>
                    </div>
                    
                    <div class="p-4 rounded-lg" 
                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            <span class="text-sm" style="color: var(--text-primary);">
                                <strong>This action cannot be undone.</strong> 
                                All maintenance data, logs, and affected user records will be permanently deleted.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('deleteModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-danger flex items-center">
                        <i class="fas fa-trash mr-2"></i> Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>