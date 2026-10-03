<form action="{{ route('admin.whatsapp-providers.configure') }}" method="POST" class="provider-form space-y-6" data-provider="wati">
    @csrf
    <input type="hidden" name="provider" value="wati">
    
    <!-- Configuration Status -->
    <div class="rounded-lg p-4" style="background-color: rgba(255, 87, 34, 0.05); border: 1px solid rgba(255, 87, 34, 0.2);">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-lg" style="color: #FF5722;"></i>
            </div>
            <div class="ml-3">
                <h4 class="font-semibold" style="color: var(--text-primary);">WATI WhatsApp Configuration</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    WATI provides WhatsApp Business API with CRM features.
                    <a href="https://docs.wati.io/" target="_blank" class="underline hover:opacity-80" style="color: #FF5722;">
                        View documentation
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- WATI API Key -->
    <div>
        <label for="wati_api_key" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-key mr-1" style="color: #FF5722;"></i>
            API Key <span class="required"></span>
        </label>
        <div class="relative">
            <input type="password" 
                   id="wati_api_key" 
                   name="wati_api_key" 
                   value="{{ old('wati_api_key', env('WATI_API_KEY')) }}"
                   class="w-full pl-10 pr-10 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="Enter your WATI API key"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-key" style="color: var(--text-secondary);"></i>
            </div>
            <button type="button" 
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    onclick="togglePasswordVisibility('wati_api_key', this)">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your WATI API key from the dashboard
        </p>
        @error('wati_api_key')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- WATI API URL -->
    <div>
        <label for="wati_api_url" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-globe mr-1" style="color: #FF5722;"></i>
            API Base URL <span class="required"></span>
        </label>
        <div class="relative">
            <input type="url" 
                   id="wati_api_url" 
                   name="wati_api_url" 
                   value="{{ old('wati_api_url', env('WATI_API_URL', 'https://api.wati.io')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="https://api.wati.io"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-link" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Default: https://api.wati.io (for enterprise, use your custom domain)
        </p>
        @error('wati_api_url')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- WhatsApp Number (Optional) -->
    <div>
        <label for="wati_whatsapp_number" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i>
            WhatsApp Number (Optional)
        </label>
        <div class="relative">
            <input type="text" 
                   id="wati_whatsapp_number" 
                   name="wati_whatsapp_number" 
                   value="{{ old('wati_whatsapp_number', env('WATI_WHATSAPP_NUMBER')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="+233XXXXXXXXX">
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your registered WhatsApp Business number (optional, can be set per message)
        </p>
        @error('wati_whatsapp_number')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- Webhook URL (Read-only) -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-broadcast-tower mr-1" style="color: #FF5722;"></i>
            Webhook URL
        </label>
        <div class="relative">
            <input type="text" 
                   value="{{ route('webhook.whatsapp') }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);"
                   readonly>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-link" style="color: var(--text-secondary);"></i>
            </div>
            <button type="button" 
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    onclick="copyToClipboard('{{ route('webhook.whatsapp') }}')">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Set this URL in your WATI dashboard for message delivery reports
        </p>
    </div>

    <!-- Environment Selector -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-server mr-1" style="color: #FF5722;"></i>
            Environment
        </label>
        <div class="grid grid-cols-2 gap-3">
            <div class="relative">
                <input type="radio" 
                       id="wati_env_test" 
                       name="wati_environment" 
                       value="test" 
                       class="sr-only"
                       @if(!env('WATI_API_KEY') || strpos(env('WATI_API_URL'), 'test') !== false) checked @endif>
                <label for="wati_env_test" 
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-flask text-2xl mb-2" style="color: var(--primary);"></i>
                    <span class="font-medium">Test</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Test environment</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio" 
                       id="wati_env_production" 
                       name="wati_environment" 
                       value="production" 
                       class="sr-only"
                       @if(env('WATI_API_KEY') && strpos(env('WATI_API_URL'), 'test') === false) checked @endif>
                <label for="wati_env_production" 
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-rocket text-2xl mb-2" style="color: var(--success);"></i>
                    <span class="font-medium">Production</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Live environment</span>
                </label>
            </div>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            WATI provides separate test and production environments
        </p>
    </div>

    <!-- WhatsApp Enabled -->
    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: rgba(255, 87, 34, 0.05); border: 1px solid rgba(255, 87, 34, 0.2);">
        <div>
            <label class="font-medium" style="color: var(--text-primary);">
                <i class="fas fa-toggle-on mr-2" style="color: #FF5722;"></i>
                Enable WATI WhatsApp
            </label>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Enable this provider to send WhatsApp messages
            </p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" 
                   name="whatsapp_enabled" 
                   value="1" 
                   class="sr-only"
                   @if(env('WHATSAPP_PROVIDER') === 'wati') checked @endif>
            <div class="toggle-switch-large">
                <div class="toggle-slider-large"></div>
            </div>
        </label>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 pt-6 border-t" style="border-color: var(--border-color);">
        <button type="submit" 
                class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                style="background-color: rgba(255, 87, 34, 0.1); color: #FF5722; border: 1px solid rgba(255, 87, 34, 0.3);">
            <i class="fas fa-save mr-2"></i>
            Save Configuration
        </button>
        
        <button type="button" 
                class="test-connection inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="wati"
                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button type="button" 
                class="reset-config inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="wati"
                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <a href="https://app.wati.io/" 
           target="_blank" 
           class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-external-link-alt mr-2"></i>
            Open WATI Dashboard
        </a>
    </div>

    <!-- WATI Features -->
    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--info);">
            <i class="fas fa-star mr-1"></i> WATI Features
        </h5>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-1">
                    <i class="fas fa-check-circle text-sm" style="color: var(--success);"></i>
                </div>
                <div class="ml-2">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">CRM Integration</span>
                    <p class="text-xs" style="color: var(--text-secondary);">Built-in contact management</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-1">
                    <i class="fas fa-check-circle text-sm" style="color: var(--success);"></i>
                </div>
                <div class="ml-2">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">Broadcast Messages</span>
                    <p class="text-xs" style="color: var(--text-secondary);">Send to multiple contacts</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-1">
                    <i class="fas fa-check-circle text-sm" style="color: var(--success);"></i>
                </div>
                <div class="ml-2">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">Template Management</span>
                    <p class="text-xs" style="color: var(--text-secondary);">Approved message templates</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-1">
                    <i class="fas fa-check-circle text-sm" style="color: var(--success);"></i>
                </div>
                <div class="ml-2">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">Analytics Dashboard</span>
                    <p class="text-xs" style="color: var(--text-secondary);">Message delivery reports</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentation Links -->
    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--info);">
            <i class="fas fa-book mr-1"></i> Quick Links
        </h5>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="https://docs.wati.io/" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #FF5722;">
                <i class="fas fa-link mr-2 text-xs"></i> API Documentation
            </a>
            <a href="https://docs.wati.io/docs/quick-start/" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #FF5722;">
                <i class="fas fa-rocket mr-2 text-xs"></i> Quick Start Guide
            </a>
            <a href="https://docs.wati.io/docs/configuration/" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #FF5722;">
                <i class="fas fa-cog mr-2 text-xs"></i> Configuration Guide
            </a>
            <a href="https://wati.io/pricing/" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #FF5722;">
                <i class="fas fa-dollar-sign mr-2 text-xs"></i> Pricing
            </a>
        </div>
    </div>
</form>

<script>
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show success message
        const button = event.target.closest('button');
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.style.color = 'var(--success)';
        
        setTimeout(() => {
            button.innerHTML = originalIcon;
            button.style.color = '';
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
    });
}
</script>

<style>
.toggle-switch-large {
    width: 60px;
    height: 30px;
    background-color: var(--bg-secondary);
    border-radius: 15px;
    position: relative;
    cursor: pointer;
    transition: background-color 0.3s;
    border: 1px solid var(--border-color);
}

.toggle-slider-large {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 26px;
    height: 26px;
    background-color: var(--danger);
    border-radius: 50%;
    transition: transform 0.3s, background-color 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

input[type="checkbox"]:checked + .toggle-switch-large {
    background-color: rgba(255, 87, 34, 0.2);
}

input[type="checkbox"]:checked + .toggle-switch-large .toggle-slider-large {
    background-color: #FF5722;
    transform: translateX(30px);
}

input:invalid {
    border-color: var(--danger) !important;
}

input:invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

input[type="radio"]:checked + label {
    border-color: #FF5722 !important;
    background-color: rgba(255, 87, 34, 0.05) !important;
}

input[type="radio"]:checked + label #wati_env_production {
    border-color: var(--success) !important;
    background-color: rgba(var(--success-rgb), 0.05) !important;
}

input[readonly] {
    cursor: not-allowed;
}
</style>