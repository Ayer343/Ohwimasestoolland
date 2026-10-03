<div id="completeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-flag-checkered mr-2" style="color: var(--success);"></i>
                Complete Maintenance
            </h3>
            
            <form action="{{ route('developer.maintenance.complete', $maintenance->id) }}" method="POST">
                @csrf
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Completion Notes <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="completion_notes" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe what was done during maintenance..."
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Actual Duration (minutes) <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="actual_duration_minutes" 
                               min="1" max="1440"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Actual Affected Users <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="actual_affected_users" 
                               min="0"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Downtime Minutes (if any)
                        </label>
                        <input type="number" name="downtime_minutes" 
                               min="0"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Estimated vs Actual
                            </label>
                            <select name="estimated_vs_actual" 
                                    class="w-full p-2 border rounded"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    required>
                                <option value="within_estimate">Within Estimate</option>
                                <option value="under_estimate">Under Estimate</option>
                                <option value="over_estimate">Over Estimate</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="post_checks_passed" name="post_checks_passed" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);">
                        <label for="post_checks_passed" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Confirm post-maintenance checks have passed <span style="color: var(--danger);">*</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('completeModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Complete Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>