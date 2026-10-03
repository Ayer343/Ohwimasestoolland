{{-- resources/views/developer/api/modals/api-key.blade.php --}}
<div id="apiKeyModal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-container max-w-lg">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header border-b-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-key text-xl" style="color: var(--success);"></i>
                </div>
                <h3 class="modal-title text-center">
                    New API Key Generated
                </h3>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">
                                Important Security Notice
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>This is your new API key. Copy it now and store it securely. It will not be shown again.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-key mr-2"></i> Your New API Key
                    </label>
                    <div class="relative">
                        <input type="text" 
                               id="newApiKey" 
                               class="form-input font-mono"
                               readonly
                               value="Loading...">
                        <button type="button" 
                                onclick="copyApiKey()"
                                class="absolute inset-y-0 right-0 px-3 flex items-center"
                                style="background-color: rgba(var(--primary-rgb), 0.1); border-left: 1px solid var(--border-color);">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <h4 class="text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Usage Instructions:
                    </h4>
                    <ul class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <li>• Include this key in the <code class="bg-gray-100 px-1 rounded">Authorization</code> header</li>
                        <li>• Format: <code class="bg-gray-100 px-1 rounded">Bearer YOUR_API_KEY</code></li>
                        <li>• Keep this key secret and do not share it</li>
                        <li>• Rotate keys regularly for security</li>
                    </ul>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="copyApiKey()">
                    <i class="fas fa-copy mr-2"></i> Copy API Key
                </button>
                <button type="button" class="btn btn-success" onclick="hideApiKeyModal()">
                    <i class="fas fa-check mr-2"></i> I've Copied It
                </button>
            </div>
        </div>
    </div>
</div>