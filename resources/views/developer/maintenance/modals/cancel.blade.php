<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i>
                Cancel Maintenance
            </h3>
            
            <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST">
                @csrf
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Cancellation Reason <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="cancellation_reason" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why is this maintenance being cancelled?..."
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>
                    
                    <div class="p-4 rounded-lg" 
                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            <span class="text-sm" style="color: var(--text-primary);">
                                <strong>Warning:</strong> Cancelling this maintenance is irreversible. 
                                If scheduled, users will be notified of the cancellation.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('cancelModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-danger flex items-center">
                        <i class="fas fa-times mr-2"></i> Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>