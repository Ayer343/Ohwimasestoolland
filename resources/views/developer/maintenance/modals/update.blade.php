<div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-edit mr-2" style="color: var(--info);"></i>
                Update Maintenance
            </h3>
            
            <form action="{{ route('developer.maintenance.update', $maintenance->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Update Notes <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="update_notes" rows="3" 
                                  class="w-full p-3 border rounded" 
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="What changes are being made and why?..."
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Title <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="text" name="title" 
                               value="{{ $maintenance->title }}"
                               class="w-full p-2 border rounded"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Description <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="description" rows="3"
                                  class="w-full p-2 border rounded"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  required>{{ $maintenance->description }}</textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Scheduled Start <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="datetime-local" name="scheduled_start" 
                                   value="{{ $maintenance->scheduled_start->format('Y-m-d\TH:i') }}"
                                   class="w-full p-2 border rounded"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Scheduled End <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="datetime-local" name="scheduled_end" 
                                   value="{{ $maintenance->scheduled_end->format('Y-m-d\TH:i') }}"
                                   class="w-full p-2 border rounded"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg" 
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-primary);">
                                Updating schedule may trigger notifications to affected users.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('updateModal')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary flex items-center">
                        <i class="fas fa-save mr-2"></i> Update Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>