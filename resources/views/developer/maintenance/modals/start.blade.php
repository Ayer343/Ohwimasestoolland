<div id="startModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-play mr-2"></i> Start Maintenance
                </h3>
                <button type="button" onclick="document.getElementById('startModal').classList.add('hidden')" 
                        class="p-2 rounded-lg hover:bg-opacity-10"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="{{ route('developer.maintenance.start', $maintenance->id) }}" method="POST">
                @csrf
                
                <div class="mb-4">
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        You are about to start this maintenance. Please confirm that all pre-maintenance checks have been completed.
                    </p>
                    
                    <div class="mb-4">
                        <label for="start_notes" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Start Notes (Optional)
                        </label>
                        <textarea id="start_notes" name="start_notes" rows="3"
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Add any notes about starting this maintenance..."></textarea>
                    </div>
                    
                    <div class="flex items-center mb-4">
                        <input type="checkbox" id="confirm_pre_checks" name="confirm_pre_checks" value="1"
                               class="h-4 w-4 rounded focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);"
                               required>
                        <label for="confirm_pre_checks" class="ml-2 text-sm" style="color: var(--text-primary);">
                            I confirm that all pre-maintenance checks have been completed
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('startModal').classList.add('hidden')"
                            class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-play mr-2"></i> Start Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>