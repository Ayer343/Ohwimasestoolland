<form action="{{ route('admin.whatsapp-providers.configure') }}" method="POST" class="provider-form space-y-6" data-provider="vumaapi">
    @csrf
    <input type="hidden" name="provider" value="vumaapi">

    <!-- Configuration Status -->
    <div class="rounded-lg p-4" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-lg" style="color: var(--primary);"></i>
            </div>
            <div class="ml-3">
                <h4 class="font-semibold" style="color: var(--text-primary);">VumaAPI WhatsApp Configuration</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure VumaAPI WhatsApp credentials for Ghana.
                    <a href="https://www.vumacloud.com/api/ghana" target="_blank" class="underline hover:opacity-80" style="color: var(--primary);">
                        View documentation
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- VumaAPI API Key -->
    <div>
        <label for="vumaapi_api_key" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-id-card mr-1" style="color: var(--primary);"></i>
            API Key <span class="required"></span>
        </label>
        <div class="relative">
            <input type="text"
                   id="vumaapi_api_key"
                   name="vumaapi_api_key"
                   value="{{ old('vumaapi_api_key', env('VUMAAPI_API_KEY')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="Your VumaAPI API Key"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-key" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your VumaAPI API Key from the developer dashboard
        </p>
        @error('vumaapi_api_key')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- VumaAPI API Secret (if applicable) -->
    <div>
        <label for="vumaapi_api_secret" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-lock mr-1" style="color: var(--primary);"></i>
            API Secret
        </label>
        <div class="relative">
            <input type="password"
                   id="vumaapi_api_secret"
                   name="vumaapi_api_secret"
                   value="{{ old('vumaapi_api_secret', env('VUMAAPI_API_SECRET')) }}"
                   class="w-full pl-10 pr-10 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="Enter your API secret (if required)">
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-key" style="color: var(--text-secondary);"></i>
            </div>
            <button type="button"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    onclick="togglePasswordVisibility('vumaapi_api_secret', this)">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your VumaAPI API Secret (keep this secure)
        </p>
        @error('vumaapi_api_secret')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- VumaAPI WhatsApp Sender ID -->
    <div>
        <label for="vumaapi_whatsapp_from" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i>
            WhatsApp Sender ID <span class="required"></span>
        </label>
        <div class="relative">
            <input type="text"
                   id="vumaapi_whatsapp_from"
                   name="vumaapi_whatsapp_from"
                   value="{{ old('vumaapi_whatsapp_from', env('VUMAAPI_WHATSAPP_FROM')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="e.g., whatsapp:+233XXXXXXXXX"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-phone" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your approved WhatsApp Business sender number (e.g., whatsapp:+233XXXXXXXXX)
        </p>
        @error('vumaapi_whatsapp_from')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- VumaAPI Base URL -->
    <div>
        <label for="vumaapi_api_url" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-server mr-1" style="color: var(--primary);"></i>
            API Base URL <span class="required"></span>
        </label>
        <div class="relative">
            <input type="url"
                   id="vumaapi_api_url"
                   name="vumaapi_api_url"
                   value="{{ old('vumaapi_api_url', env('VUMAAPI_API_URL', 'https://api.vumacloud.com')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="https://api.vumacloud.com"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-globe" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            VumaAPI base URL (default: https://api.vumacloud.com)
        </p>
        @error('vumaapi_api_url')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- Environment Selector -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-server mr-1" style="color: var(--primary);"></i>
            Environment
        </label>
        <div class="grid grid-cols-2 gap-3">
            <div class="relative">
                <input type="radio"
                       id="vumaapi_env_sandbox"
                       name="vumaapi_environment"
                       value="sandbox"
                       class="sr-only"
                       @if(env('VUMAAPI_ENVIRONMENT', 'sandbox') === 'sandbox') checked @endif>
                <label for="vumaapi_env_sandbox"
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-flask text-2xl mb-2" style="color: var(--primary);"></i>
                    <span class="font-medium">Sandbox</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Test environment</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio"
                       id="vumaapi_env_production"
                       name="vumaapi_environment"
                       value="production"
                       class="sr-only"
                       @if(env('VUMAAPI_ENVIRONMENT') === 'production') checked @endif>
                <label for="vumaapi_env_production"
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-rocket text-2xl mb-2" style="color: var(--success);"></i>
                    <span class="font-medium">Production</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Live environment</span>
                </label>
            </div>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            VumaAPI includes a sandbox environment with every account. Test for free before going live [citation:2][citation:3].
        </p>
    </div>

    <!-- WhatsApp Enabled -->
    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
        <div>
            <label class="font-medium" style="color: var(--text-primary);">
                <i class="fas fa-toggle-on mr-2" style="color: var(--primary);"></i>
                Enable VumaAPI WhatsApp
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
                   @if(env('WHATSAPP_PROVIDER') === 'vumaapi') checked @endif>
            <div class="toggle-switch-large">
                <div class="toggle-slider-large"></div>
            </div>
        </label>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 pt-6 border-t" style="border-color: var(--border-color);">
        <button type="submit"
                class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
            <i class="fas fa-save mr-2"></i>
            Save Configuration
        </button>

        <button type="button"
                class="test-connection inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="vumaapi"
                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>

        <button type="button"
                class="reset-config inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="vumaapi"
                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>

        <a href="https://www.vumacloud.com/api/ghana"
           target="_blank"
           class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-external-link-alt mr-2"></i>
            Open VumaAPI Dashboard
        </a>
    </div>

    <!-- Documentation Links -->
    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--info);">
            <i class="fas fa-book mr-1"></i> Quick Links
        </h5>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="https://www.vumacloud.com/api/ghana"
               target="_blank"
               class="text-sm hover:opacity-80 flex items-center"
               style="color: var(--primary);">
                <i class="fas fa-link mr-2 text-xs"></i> VumaAPI Ghana Documentation
            </a>
            <a href="https://www.vumacloud.com/"
               target="_blank"
               class="text-sm hover:opacity-80 flex items-center"
               style="color: var(--primary);">
                <i class="fas fa-home mr-2 text-xs"></i> VumaCloud Homepage
            </a>
            <a href="https://www.vumacloud.com/api"
               target="_blank"
               class="text-sm hover:opacity-80 flex items-center"
               style="color: var(--primary);">
                <i class="fas fa-code mr-2 text-xs"></i> All Africa APIs
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
    background-color: rgba(var(--primary-rgb), 0.2);
}

input[type="checkbox"]:checked + .toggle-switch-large .toggle-slider-large {
    background-color: var(--primary);
    transform: translateX(30px);
}

input:invalid {
    border-color: var(--danger) !important;
}

input:invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

input[type="radio"]:checked + label {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}
</style>