{{-- resources/views/developer/api/modals/config.blade.php --}}
<div id="apiConfigModal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-container max-w-2xl">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-cog mr-2" style="color: var(--info);"></i>
                    API Configuration
                </h3>
                <button type="button" class="modal-close" onclick="hideApiConfigModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <form id="apiConfigForm" method="POST" action="{{ route('developer.api-management.configure') }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="space-y-6">
                        <!-- API Status -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-toggle-on mr-2"></i> API Status
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="api_enabled" value="1" 
                                           class="form-radio h-4 w-4" 
                                           {{ ($settings->api_enabled ?? false) ? 'checked' : '' }}>
                                    <span class="ml-2">Enabled</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="api_enabled" value="0" 
                                           class="form-radio h-4 w-4" 
                                           {{ !($settings->api_enabled ?? true) ? 'checked' : '' }}>
                                    <span class="ml-2">Disabled</span>
                                </label>
                            </div>
                            <p class="form-help">Enable or disable the API for all users</p>
                        </div>

                        <!-- API Version -->
                        <div class="form-group">
                            <label class="form-label" for="api_version">
                                <i class="fas fa-code-branch mr-2"></i> API Version
                            </label>
                            <input type="text" 
                                   id="api_version" 
                                   name="api_version" 
                                   value="{{ $settings->api_version ?? '1.0.0' }}"
                                   class="form-input"
                                   placeholder="e.g., 1.0.0">
                            <p class="form-help">Current API version number</p>
                        </div>

                        <!-- Base URL -->
                        <div class="form-group">
                            <label class="form-label" for="api_base_url">
                                <i class="fas fa-link mr-2"></i> Base URL
                            </label>
                            <input type="url" 
                                   id="api_base_url" 
                                   name="api_base_url" 
                                   value="{{ $settings->api_base_url ?? url('/api') }}"
                                   class="form-input"
                                   placeholder="https://api.example.com">
                            <p class="form-help">Base URL for all API endpoints</p>
                        </div>

                        <!-- Rate Limits -->
                        <div class="form-group">
                            <label class="form-label" for="api_rate_limits">
                                <i class="fas fa-tachometer-alt mr-2"></i> Rate Limits
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                        Requests per Minute
                                    </label>
                                    <input type="number" 
                                           name="rate_limit_per_minute" 
                                           value="{{ $rateLimits['per_minute'] ?? 60 }}"
                                           class="form-input"
                                           min="1" max="1000">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                                        Requests per Hour
                                    </label>
                                    <input type="number" 
                                           name="rate_limit_per_hour" 
                                           value="{{ $rateLimits['per_hour'] ?? 1000 }}"
                                           class="form-input"
                                           min="10" max="10000">
                                </div>
                            </div>
                            <p class="form-help">Set API rate limits to prevent abuse</p>
                        </div>

                        <!-- Allowed Origins -->
                        <div class="form-group">
                            <label class="form-label" for="api_allowed_origins">
                                <i class="fas fa-globe mr-2"></i> Allowed Origins (CORS)
                            </label>
                            <textarea id="api_allowed_origins" 
                                      name="api_allowed_origins" 
                                      class="form-textarea"
                                      rows="3"
                                      placeholder="Enter one origin per line or comma-separated">{{ $settings->api_allowed_origins ?? '*' }}</textarea>
                            <p class="form-help">Domains allowed to make API requests (use * for all)</p>
                        </div>

                        <!-- Documentation URL -->
                        <div class="form-group">
                            <label class="form-label" for="api_documentation_url">
                                <i class="fas fa-book mr-2"></i> Documentation URL
                            </label>
                            <input type="url" 
                                   id="api_documentation_url" 
                                   name="api_documentation_url" 
                                   value="{{ $settings->api_documentation_url ?? '' }}"
                                   class="form-input"
                                   placeholder="https://docs.example.com">
                            <p class="form-help">Link to your API documentation</p>
                        </div>

                        <!-- Response Format -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-code mr-2"></i> Default Response Format
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="default_response_format" value="json" 
                                           class="form-radio h-4 w-4" checked>
                                    <span class="ml-2">JSON</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="default_response_format" value="xml" 
                                           class="form-radio h-4 w-4">
                                    <span class="ml-2">XML</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideApiConfigModal()">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" form="apiConfigForm" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>