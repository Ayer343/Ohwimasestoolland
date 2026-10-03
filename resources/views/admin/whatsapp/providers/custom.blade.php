<form action="{{ route('admin.whatsapp-providers.configure') }}" method="POST" class="provider-form space-y-6" data-provider="custom">
    @csrf
    <input type="hidden" name="provider" value="custom">
    
    <!-- Configuration Status -->
    <div class="rounded-lg p-4" style="background-color: rgba(156, 39, 176, 0.05); border: 1px solid rgba(156, 39, 176, 0.2);">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-lg" style="color: #9C27B0;"></i>
            </div>
            <div class="ml-3">
                <h4 class="font-semibold" style="color: var(--text-primary);">Custom WhatsApp API Configuration</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Integrate with custom or self-hosted WhatsApp API solutions.
                </p>
            </div>
        </div>
    </div>

    <!-- API Base URL -->
    <div>
        <label for="whatsapp_api_url" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-globe mr-1" style="color: #9C27B0;"></i>
            API Base URL <span class="required"></span>
        </label>
        <div class="relative">
            <input type="url" 
                   id="whatsapp_api_url" 
                   name="whatsapp_api_url" 
                   value="{{ old('whatsapp_api_url', env('WHATSAPP_API_URL')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="https://api.example.com/whatsapp"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-link" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Full URL to your custom WhatsApp API endpoint
        </p>
        @error('whatsapp_api_url')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- API Key -->
    <div>
        <label for="whatsapp_api_key" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-key mr-1" style="color: #9C27B0;"></i>
            API Key / Access Token <span class="required"></span>
        </label>
        <div class="relative">
            <input type="password" 
                   id="whatsapp_api_key" 
                   name="whatsapp_api_key" 
                   value="{{ old('whatsapp_api_key', env('WHATSAPP_API_KEY')) }}"
                   class="w-full pl-10 pr-10 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="Enter your API key or access token"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-key" style="color: var(--text-secondary);"></i>
            </div>
            <button type="button" 
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    onclick="togglePasswordVisibility('whatsapp_api_key', this)">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Authentication key for your custom API
        </p>
        @error('whatsapp_api_key')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- WhatsApp From Number/ID -->
    <div>
        <label for="whatsapp_from" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i>
            WhatsApp From (Optional)
        </label>
        <div class="relative">
            <input type="text" 
                   id="whatsapp_from" 
                   name="whatsapp_from" 
                   value="{{ old('whatsapp_from', env('WHATSAPP_FROM')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="whatsapp:+233XXXXXXXXX or phone number ID">
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Sender identifier (phone number, WhatsApp ID, or sender name)
        </p>
        @error('whatsapp_from')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- HTTP Method -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-exchange-alt mr-1" style="color: #9C27B0;"></i>
            HTTP Method
        </label>
        <div class="grid grid-cols-3 gap-2">
            <div class="relative">
                <input type="radio" 
                       id="http_method_post" 
                       name="http_method" 
                       value="POST" 
                       class="sr-only"
                       @if(empty(env('WHATSAPP_HTTP_METHOD')) || env('WHATSAPP_HTTP_METHOD') === 'POST') checked @endif>
                <label for="http_method_post" 
                       class="flex flex-col items-center justify-center p-3 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <span class="font-medium">POST</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Standard</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio" 
                       id="http_method_get" 
                       name="http_method" 
                       value="GET" 
                       class="sr-only"
                       @if(env('WHATSAPP_HTTP_METHOD') === 'GET') checked @endif>
                <label for="http_method_get" 
                       class="flex flex-col items-center justify-center p-3 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <span class="font-medium">GET</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Query params</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio" 
                       id="http_method_put" 
                       name="http_method" 
                       value="PUT" 
                       class="sr-only"
                       @if(env('WHATSAPP_HTTP_METHOD') === 'PUT') checked @endif>
                <label for="http_method_put" 
                       class="flex flex-col items-center justify-center p-3 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <span class="font-medium">PUT</span>
                    <span class="text-xs mt-1" style="color: var(--text-secondary);">Update</span>
                </label>
            </div>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            HTTP method used by your custom API
        </p>
    </div>

    <!-- Authentication Type -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-user-shield mr-1" style="color: #9C27B0;"></i>
            Authentication Type
        </label>
        <div class="grid grid-cols-2 gap-2">
            <div class="relative">
                <input type="radio" 
                       id="auth_type_bearer" 
                       name="auth_type" 
                       value="bearer" 
                       class="sr-only"
                       @if(empty(env('WHATSAPP_AUTH_TYPE')) || env('WHATSAPP_AUTH_TYPE') === 'bearer') checked @endif>
                <label for="auth_type_bearer" 
                       class="flex items-center justify-center p-3 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span>Bearer Token</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio" 
                       id="auth_type_basic" 
                       name="auth_type" 
                       value="basic" 
                       class="sr-only"
                       @if(env('WHATSAPP_AUTH_TYPE') === 'basic') checked @endif>
                <label for="auth_type_basic" 
                       class="flex items-center justify-center p-3 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-user-lock mr-2"></i>
                    <span>Basic Auth</span>
                </label>
            </div>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            How authentication is sent to your API
        </p>
    </div>

    <!-- Headers Configuration (Optional) -->
    <div>
        <label for="custom_headers" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-heading mr-1" style="color: #9C27B0;"></i>
            Custom Headers (Optional)
        </label>
        <textarea id="custom_headers" 
                  name="custom_headers" 
                  rows="3"
                  class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                  style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                  placeholder="X-Custom-Header: value
Another-Header: another-value">{{ old('custom_headers', env('WHATSAPP_CUSTOM_HEADERS')) }}</textarea>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Additional HTTP headers (one per line in "Header: value" format)
        </p>
        @error('custom_headers')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- WhatsApp Enabled -->
    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: rgba(156, 39, 176, 0.05); border: 1px solid rgba(156, 39, 176, 0.2);">
        <div>
            <label class="font-medium" style="color: var(--text-primary);">
                <i class="fas fa-toggle-on mr-2" style="color: #9C27B0;"></i>
                Enable Custom WhatsApp API
            </label>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Enable this provider to send WhatsApp messages via custom API
            </p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" 
                   name="whatsapp_enabled" 
                   value="1" 
                   class="sr-only"
                   @if(env('WHATSAPP_PROVIDER') === 'custom') checked @endif>
            <div class="toggle-switch-large">
                <div class="toggle-slider-large"></div>
            </div>
        </label>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 pt-6 border-t" style="border-color: var(--border-color);">
        <button type="submit" 
                class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                style="background-color: rgba(156, 39, 176, 0.1); color: #9C27B0; border: 1px solid rgba(156, 39, 176, 0.3);">
            <i class="fas fa-save mr-2"></i>
            Save Configuration
        </button>
        
        <button type="button" 
                class="test-connection inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="custom"
                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button type="button" 
                class="reset-config inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="custom"
                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <button type="button" 
                onclick="showApiDocumentation()"
                class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-question-circle mr-2"></i>
            API Requirements
        </button>
    </div>

    <!-- API Requirements -->
    <div id="apiRequirements" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <div class="flex items-start justify-between">
            <div>
                <h5 class="font-medium mb-2" style="color: var(--info);">
                    <i class="fas fa-code mr-1"></i> Custom API Requirements
                </h5>
                <p class="text-sm mb-3" style="color: var(--text-secondary);">
                    Your custom API should implement these endpoints:
                </p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="hideApiDocumentation()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="space-y-3">
            <div class="p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <div class="flex items-center">
                    <span class="font-mono text-sm px-2 py-1 rounded mr-2" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">POST</span>
                    <span class="font-medium" style="color: var(--text-primary);">Send Message</span>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Endpoint to send WhatsApp messages
                </p>
                <div class="mt-2 text-xs font-mono" style="color: var(--text-secondary);">
                    Expected payload: {"to": "whatsapp:+1234567890", "message": "Hello"}
                </div>
            </div>
            
            <div class="p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <div class="flex items-center">
                    <span class="font-mono text-sm px-2 py-1 rounded mr-2" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">GET</span>
                    <span class="font-medium" style="color: var(--text-primary);">Health Check</span>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Endpoint to verify API connectivity
                </p>
                <div class="mt-2 text-xs font-mono" style="color: var(--text-secondary);">
                    Expected response: {"status": "ok", "connected": true}
                </div>
            </div>
            
            <div class="p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                <div class="flex items-center">
                    <span class="font-mono text-sm px-2 py-1 rounded mr-2" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">POST</span>
                    <span class="font-medium" style="color: var(--text-primary);">Delivery Reports</span>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Webhook for message delivery status
                </p>
                <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                    Webhook URL: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ route('webhook.whatsapp') }}</code>
                </div>
            </div>
        </div>
        
        <div class="mt-4 pt-3 border-t" style="border-color: rgba(var(--info-rgb), 0.2);">
            <p class="text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-lightbulb mr-1"></i>
                Your API should return appropriate HTTP status codes (200 for success, 4xx/5xx for errors)
            </p>
        </div>
    </div>

    <!-- Popular Custom Solutions -->
    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--info);">
            <i class="fas fa-tools mr-1"></i> Popular Custom WhatsApp Solutions
        </h5>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="https://github.com/pedroslopez/whatsapp-web.js" 
               target="_blank" 
               class="p-3 rounded hover:opacity-90 transition-opacity"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fab fa-github text-lg mr-2" style="color: #333;"></i>
                    <div>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">whatsapp-web.js</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Node.js library</div>
                    </div>
                </div>
            </a>
            <a href="https://github.com/adiwajshing/Baileys" 
               target="_blank" 
               class="p-3 rounded hover:opacity-90 transition-opacity"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fab fa-github text-lg mr-2" style="color: #333;"></i>
                    <div>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">Baileys</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Typescript library</div>
                    </div>
                </div>
            </a>
            <a href="https://github.com/tgalopin/whatsapp-api" 
               target="_blank" 
               class="p-3 rounded hover:opacity-90 transition-opacity"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fab fa-github text-lg mr-2" style="color: #333;"></i>
                    <div>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">WhatsApp API Gateway</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Self-hosted gateway</div>
                    </div>
                </div>
            </a>
            <a href="https://github.com/churchtools/churchcrm-whatsapp" 
               target="_blank" 
               class="p-3 rounded hover:opacity-90 transition-opacity"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fab fa-github text-lg mr-2" style="color: #333;"></i>
                    <div>
                        <div class="font-medium text-sm" style="color: var(--text-primary);">ChurchCRM WhatsApp</div>
                        <div class="text-xs" style="color: var(--text-secondary);">PHP integration</div>
                    </div>
                </div>
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

function showApiDocumentation() {
    document.getElementById('apiRequirements').classList.remove('hidden');
}

function hideApiDocumentation() {
    document.getElementById('apiRequirements').classList.add('hidden');
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
    background-color: rgba(156, 39, 176, 0.2);
}

input[type="checkbox"]:checked + .toggle-switch-large .toggle-slider-large {
    background-color: #9C27B0;
    transform: translateX(30px);
}

input:invalid {
    border-color: var(--danger) !important;
}

input:invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

input[type="radio"]:checked + label {
    border-color: #9C27B0 !important;
    background-color: rgba(156, 39, 176, 0.05) !important;
}

#apiRequirements {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>