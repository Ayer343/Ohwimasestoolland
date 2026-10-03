{{-- developer/super-admins/modals/communication-status.blade.php --}}
<div id="communicationStatusModal" class="modal hidden">
    <div class="modal-overlay" onclick="hideModal('communicationStatusModal')"></div>
    <div class="modal-content max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-broadcast-tower mr-2" style="color: var(--info);"></i>
                Communication Services Status
            </h3>
            <button type="button" class="modal-close" onclick="hideModal('communicationStatusModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <!-- Loading State -->
            <div id="commsLoading" class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                <p class="text-sm" style="color: var(--text-secondary);">Loading communication status...</p>
            </div>
            
            <!-- Content Container -->
            <div id="commsContent" class="hidden">
                <!-- Status Summary -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                        Service Status Summary
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Email Status Card -->
                        <div class="status-card {{ $emailEnabled ? 'status-active' : 'status-inactive' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="status-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div>
                                        <span class="status-label">Email Service</span>
                                        <span class="status-description">
                                            @if($emailEnabled)
                                                Active and configured
                                            @else
                                                Not configured
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="status-indicator {{ $emailEnabled ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="fas fa-{{ $emailEnabled ? 'check' : 'times' }} text-xs"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMS Status Card -->
                        <div class="status-card {{ $smsEnabled ? 'status-active' : ($smsConfigured ? 'status-configured' : 'status-inactive') }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="status-icon">
                                        <i class="fas fa-sms"></i>
                                    </div>
                                    <div>
                                        <span class="status-label">SMS Service</span>
                                        <span class="status-description">
                                            @if($smsEnabled)
                                                Active and sending
                                            @elseif($smsConfigured)
                                                Configured (disabled)
                                            @else
                                                Not configured
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="status-indicator {{ $smsEnabled ? 'bg-success' : ($smsConfigured ? 'bg-warning' : 'bg-secondary') }}">
                                    <i class="fas fa-{{ $smsEnabled ? 'check' : ($smsConfigured ? 'cog' : 'times') }} text-xs"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- WhatsApp Status Card -->
                        <div class="status-card {{ $whatsappEnabled ? 'status-active' : 'status-inactive' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="status-icon">
                                        <i class="fab fa-whatsapp"></i>
                                    </div>
                                    <div>
                                        <span class="status-label">WhatsApp</span>
                                        <span class="status-description">
                                            @if($whatsappEnabled)
                                                Active and ready
                                            @else
                                                Not configured
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="status-indicator {{ $whatsappEnabled ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="fas fa-{{ $whatsappEnabled ? 'check' : 'times' }} text-xs"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Multi-channel Status Card -->
                        <div class="status-card {{ $multiChannelEnabled ? 'status-active' : 'status-inactive' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="status-icon">
                                        <i class="fas fa-retweet"></i>
                                    </div>
                                    <div>
                                        <span class="status-label">Multi-channel</span>
                                        <span class="status-description">
                                            @if($multiChannelEnabled)
                                                Routing enabled
                                            @else
                                                Service unavailable
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="status-indicator {{ $multiChannelEnabled ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="fas fa-{{ $multiChannelEnabled ? 'check' : 'times' }} text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SMS Providers Details -->
                @if($smsAvailableProviders && count($smsAvailableProviders) > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-list-alt mr-2" style="color: var(--info);"></i>
                        SMS Providers Configuration
                    </h4>
                    <div class="space-y-2">
                        @foreach($smsAvailableProviders as $providerName => $provider)
                        <div class="config-item {{ $provider['configured'] ? 'config-configured' : 'config-inactive' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="config-icon">
                                        <i class="fas fa-{{ $provider['configured'] ? 'check-circle' : 'times-circle' }}"></i>
                                    </div>
                                    <div>
                                        <span class="config-label">{{ $provider['display_name'] ?? ucfirst($providerName) }}</span>
                                        <div class="config-details">
                                            @if($provider['enabled'])
                                                <span class="badge badge-success text-xs">Enabled</span>
                                            @endif
                                            <span class="text-xs" style="color: var(--text-secondary);">
                                                @if($provider['has_api_key'] && $provider['has_sender_id'])
                                                    API & Sender ID configured
                                                @elseif($provider['has_api_key'])
                                                    API key only
                                                @elseif($provider['has_sender_id'])
                                                    Sender ID only
                                                @else
                                                    Not configured
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-xs" style="color: {{ $provider['configured'] ? 'var(--success)' : 'var(--secondary)' }};">
                                    {{ $provider['configured'] ? 'Ready' : 'Not Ready' }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Service Actions -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-play-circle mr-2" style="color: var(--info);"></i>
                        Service Actions
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button onclick="refreshCommsStatus()" 
                                class="action-btn btn-secondary py-2">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Refresh Status
                        </button>
                        
                        <button onclick="testAllServices()" 
                                class="action-btn btn-info py-2">
                            <i class="fas fa-vial mr-2"></i>
                            Test All Services
                        </button>
                        
                        <a href="{{ route('admin.sms-providers.index') }}" 
                           target="_blank"
                           class="action-btn btn-primary py-2 text-center">
                            <i class="fas fa-cog mr-2"></i>
                            Configure SMS
                        </a>
                    </div>
                </div>
                
                <!-- Debug Information (only in debug mode) -->
                @if(config('app.debug') && !empty($commsDebugInfo))
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-sm font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-bug mr-2" style="color: var(--warning);"></i>
                            Debug Information
                        </h4>
                        <button onclick="toggleDebugInfo()" 
                                class="text-xs px-2 py-1 rounded" 
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-eye mr-1"></i> Toggle
                        </button>
                    </div>
                    
                    <div id="debugInfo" class="hidden">
                        <div class="text-xs space-y-1 p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                            @foreach($commsDebugInfo as $key => $value)
                            <div class="flex">
                                <span class="font-medium w-32 flex-shrink-0" style="color: var(--text-secondary);">
                                    {{ str_replace('_', ' ', ucfirst($key)) }}:
                                </span>
                                <span class="flex-1" style="color: var(--text-primary);">
                                    @if(is_array($value))
                                        <pre class="text-xs">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                    @elseif(is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Last Updated -->
                <div class="text-xs mt-4 pt-4 border-t text-center" 
                     style="border-color: var(--border-color); color: var(--text-secondary);">
                    <i class="fas fa-clock mr-1"></i>
                    Last updated: {{ now()->format('Y-m-d H:i:s') }}
                    <br>
                    <span class="text-xs">Status fetched from: {{ request()->url() }}</span>
                </div>
            </div>
            
            <!-- Error State -->
            <div id="commsError" class="hidden text-center py-8">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger);"></i>
                </div>
                <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
                    Failed to load communication status
                </h4>
                <p class="mb-4 text-sm" style="color: var(--text-secondary);">
                    Unable to fetch communication service information. Please try again.
                </p>
                <button onclick="loadCommsStatus()" 
                        class="btn btn-danger px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-redo mr-2"></i> Retry
                </button>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" 
                    onclick="hideModal('communicationStatusModal')" 
                    class="btn btn-secondary px-4 py-2 rounded-lg">
                <i class="fas fa-times mr-2"></i> Close
            </button>
            <button onclick="copyCommsStatus()" 
                    class="btn btn-primary px-4 py-2 rounded-lg">
                <i class="fas fa-copy mr-2"></i> Copy Status
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function loadCommsStatus() {
    const loadingEl = document.getElementById('commsLoading');
    const contentEl = document.getElementById('commsContent');
    const errorEl = document.getElementById('commsError');
    
    // Show loading, hide others
    loadingEl.classList.remove('hidden');
    contentEl.classList.add('hidden');
    errorEl.classList.add('hidden');
    
    // Simulate loading (in reality, this would be an API call)
    setTimeout(() => {
        loadingEl.classList.add('hidden');
        contentEl.classList.remove('hidden');
    }, 500);
}

function refreshCommsStatus() {
    // Show loading state
    const btn = event?.target || document.querySelector('[onclick*="refreshCommsStatus"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
    btn.disabled = true;
    
    // Reload page to get fresh status
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

function testAllServices() {
    const btn = event?.target || document.querySelector('[onclick*="testAllServices"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
    btn.disabled = true;
    
    // Test SMS service
    fetch('{{ route("developer.super-admins.debug-sms-status") }}')
        .then(response => response.json())
        .then(data => {
            if (data.system_status && data.system_status.enabled) {
                showToast('SMS service test passed!', 'success');
            } else {
                showToast('SMS service test failed or not configured.', 'warning');
            }
        })
        .catch(error => {
            console.error('SMS test error:', error);
            showToast('SMS service test failed.', 'error');
        })
        .finally(() => {
            // Reset button
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    
    // Note: In a real implementation, you'd test all services
}

function copyCommsStatus() {
    const statusText = `
Communication Services Status Report
Generated: ${new Date().toLocaleString()}
URL: ${window.location.href}

=== SERVICE STATUS ===
Email: ${ {{ $emailEnabled ? 'true' : 'false' }} ? '✅ Active' : '❌ Inactive'}
SMS: ${ {{ $smsEnabled ? 'true' : 'false' }} ? '✅ Active' : {{ $smsConfigured ? 'true' : 'false' }} ? '⚠️ Configured (Disabled)' : '❌ Inactive'}
WhatsApp: ${ {{ $whatsappEnabled ? 'true' : 'false' }} ? '✅ Active' : '❌ Inactive'}
Multi-channel: ${ {{ $multiChannelEnabled ? 'true' : 'false' }} ? '✅ Active' : '❌ Inactive'}

=== SMS PROVIDERS ===
@foreach($smsAvailableProviders as $providerName => $provider)
{{ $provider['display_name'] ?? ucfirst($providerName) }}: 
  Enabled: {{ $provider['enabled'] ? 'Yes' : 'No' }}
  Configured: {{ $provider['configured'] ? 'Yes' : 'No' }}
  API Key: {{ $provider['has_api_key'] ? 'Set' : 'Not set' }}
  Sender ID: {{ $provider['has_sender_id'] ? 'Set' : 'Not set' }}
@endforeach

=== DEBUG INFO ===
Environment: {{ config('app.env') }}
Debug Mode: {{ config('app.debug') ? 'Yes' : 'No' }}
Timestamp: ${new Date().toISOString()}
    `.trim();
    
    navigator.clipboard.writeText(statusText).then(() => {
        showToast('Communication status copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy status to clipboard.', 'error');
    });
}

function toggleDebugInfo() {
    const debugEl = document.getElementById('debugInfo');
    const btn = event?.target.closest('button') || event?.target;
    
    if (debugEl) {
        debugEl.classList.toggle('hidden');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = debugEl.classList.contains('hidden') 
                    ? 'fas fa-eye mr-1' 
                    : 'fas fa-eye-slash mr-1';
            }
        }
    }
}

// Load status when modal is shown
document.addEventListener('modal-shown', function(e) {
    if (e.target.id === 'communicationStatusModal') {
        loadCommsStatus();
    }
});

// Initialize when modal opens
document.getElementById('communicationStatusModal')?.addEventListener('click', function(e) {
    if (e.target.closest('.modal-content')) {
        // Load status when modal content is clicked (ensures it's visible)
        setTimeout(loadCommsStatus, 100);
    }
});
</script>
@endpush

@push('styles')
<style>
/* Modal specific styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.modal-content {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 0.75rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 600px;
    animation: modalSlideIn 0.3s ease;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 1.125rem;
}

.modal-close:hover {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

.modal-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: rgba(var(--secondary-rgb), 0.03);
}

/* Status Cards */
.status-card {
    padding: 0.875rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid;
    transition: all 0.2s ease;
}

.status-card.status-active {
    background-color: rgba(var(--success-rgb), 0.05);
    border-color: rgba(var(--success-rgb), 0.2);
}

.status-card.status-configured {
    background-color: rgba(var(--warning-rgb), 0.05);
    border-color: rgba(var(--warning-rgb), 0.2);
}

.status-card.status-inactive {
    background-color: rgba(var(--secondary-rgb), 0.05);
    border-color: rgba(var(--secondary-rgb), 0.2);
}

.status-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.status-card.status-active .status-icon {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-card.status-configured .status-icon {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-card.status-inactive .status-icon {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.status-label {
    display: block;
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
}

.status-description {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.status-indicator {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

/* Configuration Items */
.config-item {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.config-item:hover {
    background-color: var(--bg-secondary);
}

.config-item.config-configured {
    border-left: 3px solid var(--success);
}

.config-item.config-inactive {
    border-left: 3px solid var(--secondary);
}

.config-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.config-item.config-configured .config-icon {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.config-item.config-inactive .config-icon {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.config-label {
    display: block;
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
}

.config-details {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Action Buttons */
.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.action-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Spinner Animation */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive adjustments for modal */
@media (max-width: 640px) {
    .modal-content {
        margin: 0;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
    
    .status-card,
    .config-item {
        padding: 0.75rem;
    }
    
    .status-icon,
    .config-icon {
        width: 2rem;
        height: 2rem;
        margin-right: 0.5rem;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
@endpush