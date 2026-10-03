<!-- Emergency Mode Modal -->
<div id="emergencyModeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900 flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🚨 Activate Emergency Mode</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Activate system-wide emergency protocols</p>
                    </div>
                </div>
                <button onclick="closeModal('emergencyModeModal')" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <!-- Warning Alert -->
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-red-800 dark:text-red-200 mb-1">⚠️ WARNING: Emergency Mode</h4>
                        <p class="text-sm text-red-700 dark:text-red-300">
                            Emergency mode will:
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Restrict system access for non-admin users</li>
                                <li>Enable maintenance mode</li>
                                <li>Send emergency alerts to all administrators</li>
                                <li>Log all system activities at DEBUG level</li>
                                <li>May cause temporary service disruption</li>
                            </ul>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Emergency Details Form -->
            <form id="emergencyModeForm" onsubmit="activateEmergencyMode(event)">
                @csrf
                
                <!-- Severity Level -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Emergency Severity Level
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label class="emergency-severity-option">
                            <input type="radio" name="severity" value="low" class="sr-only">
                            <div class="p-3 border rounded-lg text-center cursor-pointer hover:border-blue-500 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-info-circle text-green-500"></i>
                                </div>
                                <span class="block font-medium text-gray-900 dark:text-white">Low</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Minor Issue</span>
                            </div>
                        </label>
                        <label class="emergency-severity-option">
                            <input type="radio" name="severity" value="medium" class="sr-only">
                            <div class="p-3 border rounded-lg text-center cursor-pointer hover:border-blue-500 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                </div>
                                <span class="block font-medium text-gray-900 dark:text-white">Medium</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Moderate Impact</span>
                            </div>
                        </label>
                        <label class="emergency-severity-option">
                            <input type="radio" name="severity" value="high" class="sr-only" checked>
                            <div class="p-3 border rounded-lg text-center cursor-pointer hover:border-blue-500 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-exclamation-circle text-orange-500"></i>
                                </div>
                                <span class="block font-medium text-gray-900 dark:text-white">High</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Serious Issue</span>
                            </div>
                        </label>
                        <label class="emergency-severity-option">
                            <input type="radio" name="severity" value="critical" class="sr-only">
                            <div class="p-3 border rounded-lg text-center cursor-pointer hover:border-blue-500 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-skull-crossbones text-red-500"></i>
                                </div>
                                <span class="block font-medium text-gray-900 dark:text-white">Critical</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">System Down</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Emergency Reason -->
                <div class="mb-6">
                    <label for="emergencyReason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Reason for Emergency Mode *
                    </label>
                    <textarea 
                        id="emergencyReason" 
                        name="reason" 
                        rows="4"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Describe the emergency situation..."
                        required
                    ></textarea>
                </div>

                <!-- Affected Components -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Affected System Components
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach(['Database', 'Payment Gateway', 'Email Service', 'SMS Service', 'API Gateway', 'Cache Service', 'File Storage', 'Queue System', 'Web Server'] as $component)
                        <label class="flex items-center">
                            <input type="checkbox" name="affected_components[]" value="{{ strtolower(str_replace(' ', '_', $component)) }}" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $component }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Actions Taken -->
                <div class="mb-6">
                    <label for="actionTaken" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Immediate Actions Taken
                    </label>
                    <textarea 
                        id="actionTaken" 
                        name="action_taken" 
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Describe any immediate actions you've taken..."
                    ></textarea>
                </div>

                <!-- Recovery Options -->
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-tools text-blue-500 mt-0.5 mr-3"></i>
                        <div class="flex-1">
                            <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Recovery Options</h4>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="requires_manual_recovery" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-blue-700 dark:text-blue-300">Requires manual recovery (system won't auto-recover)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="notify_admins" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-blue-700 dark:text-blue-300">Notify all administrators immediately</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_maintenance_mode" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-blue-700 dark:text-blue-300">Enable maintenance mode for non-admin users</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation -->
                <div class="mb-6">
                    <label class="flex items-start">
                        <input type="checkbox" name="confirmation" required class="mt-1 rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-red-700 dark:text-red-300">
                            I understand that activating emergency mode may disrupt services and affect users. I have the authority to make this decision.
                        </span>
                    </label>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">You are logged in as: <strong>{{ Auth::user()->name }}</strong></span>
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeModal('emergencyModeModal')" 
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" form="emergencyModeForm"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-bolt mr-2"></i> Activate Emergency Mode
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.emergency-severity-option input:checked + div {
    @apply border-blue-500 ring-2 ring-blue-200 dark:ring-blue-800 bg-blue-50 dark:bg-blue-900/20;
}
</style>

<script>
// JavaScript for emergency mode modal
function showEmergencyModeModal() {
    const modal = document.getElementById('emergencyModeModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Initialize severity selection
document.querySelectorAll('.emergency-severity-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.emergency-severity-option input').forEach(input => {
            input.checked = false;
        });
        this.querySelector('input').checked = true;
        
        // Update all option styles
        document.querySelectorAll('.emergency-severity-option > div').forEach(div => {
            div.classList.remove('border-blue-500', 'ring-2', 'ring-blue-200', 'dark:ring-blue-800', 'bg-blue-50', 'dark:bg-blue-900/20');
        });
        
        this.querySelector('div').classList.add('border-blue-500', 'ring-2', 'ring-blue-200', 'dark:ring-blue-800', 'bg-blue-50', 'dark:bg-blue-900/20');
    });
});

// Activate emergency mode
async function activateEmergencyMode(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Convert affected_components to array
    data.affected_components = formData.getAll('affected_components[]');
    
    // Convert checkboxes to booleans
    data.requires_manual_recovery = data.requires_manual_recovery === '1';
    data.notify_admins = data.notify_admins === '1';
    data.enable_maintenance_mode = data.enable_maintenance_mode === '1';
    
    try {
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Activating...';
        submitBtn.disabled = true;
        
        // Send request
        const response = await fetch('{{ route("developer.emergency.start") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success notification
            showNotification('🚨 Emergency mode activated successfully', 'success');
            
            // Close modal
            closeModal('emergencyModeModal');
            
            // Reload page to show emergency banner
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(`Failed to activate emergency mode: ${result.message}`, 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        showNotification('Failed to activate emergency mode: Network error', 'error');
        console.error('Emergency mode activation error:', error);
        
        // Reset button
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-bolt mr-2"></i> Activate Emergency Mode';
        submitBtn.disabled = false;
    }
}

// Global show notification function (should be defined elsewhere)
function showNotification(message, type = 'success') {
    // Use existing notification function from your main script
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback notification
        alert(`${type.toUpperCase()}: ${message}`);
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal('emergencyModeModal');
    }
});

// Close modal when clicking outside
document.getElementById('emergencyModeModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal('emergencyModeModal');
    }
});
</script>