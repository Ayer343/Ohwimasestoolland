<form action="{{ route('admin.whatsapp-providers.configure') }}" method="POST" class="provider-form space-y-6" data-provider="360dialog">
    @csrf
    <input type="hidden" name="provider" value="360dialog">
    
    <!-- Configuration Status -->
    <div class="rounded-lg p-4" style="background-color: rgba(37, 211, 102, 0.05); border: 1px solid rgba(37, 211, 102, 0.2);">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fab fa-whatsapp text-lg" style="color: #25D366;"></i>
            </div>
            <div class="ml-3">
                <h4 class="font-semibold" style="color: var(--text-primary);">360Dialog WhatsApp Configuration</h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure 360Dialog WhatsApp Business API credentials. 
                    <a href="https://docs.360dialog.com/whatsapp-api/" target="_blank" class="underline hover:opacity-80" style="color: #25D366;">
                        View documentation
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- 360Dialog API Key -->
    <div>
        <label for="dialog_api_key" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-key mr-1" style="color: #25D366;"></i>
            API Key <span class="required"></span>
        </label>
        <div class="relative">
            <input type="password" 
                   id="dialog_api_key" 
                   name="dialog_api_key" 
                   value="{{ old('dialog_api_key', env('DIALOG_API_KEY')) }}"
                   class="w-full pl-10 pr-10 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="Enter your 360Dialog API Key"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-key" style="color: var(--text-secondary);"></i>
            </div>
            <button type="button" 
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    onclick="togglePasswordVisibility('dialog_api_key', this)">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Get this from your 360Dialog dashboard under API Settings
        </p>
        @error('dialog_api_key')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- Phone Number ID -->
    <div>
        <label for="dialog_phone_number_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-phone mr-1" style="color: #25D366;"></i>
            Phone Number ID <span class="required"></span>
        </label>
        <div class="relative">
            <input type="text" 
                   id="dialog_phone_number_id" 
                   name="dialog_phone_number_id" 
                   value="{{ old('dialog_phone_number_id', env('DIALOG_PHONE_NUMBER_ID')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="e.g., 123456789012345"
                   required>
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-hashtag" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Find this in your 360Dialog account → Phone Numbers
        </p>
        @error('dialog_phone_number_id')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- Business ID (Optional) -->
    <div>
        <label for="dialog_business_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-building mr-1" style="color: #25D366;"></i>
            Business ID (Optional)
        </label>
        <div class="relative">
            <input type="text" 
                   id="dialog_business_id" 
                   name="dialog_business_id" 
                   value="{{ old('dialog_business_id', env('DIALOG_BUSINESS_ID')) }}"
                   class="w-full pl-10 pr-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                   placeholder="e.g., 987654321098765">
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <i class="fas fa-id-card" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        <p class="text-xs mt-1" style="color: var(--text-secondary);">
            Your WhatsApp Business Account ID (WABA ID). Required for some features.
        </p>
        @error('dialog_business_id')
            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
        @enderror
    </div>

    <!-- Environment Selector -->
    <div>
        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
            <i class="fas fa-server mr-1" style="color: #25D366;"></i>
            Service Type
        </label>
        <div class="grid grid-cols-2 gap-3">
            <div class="relative">
                <input type="radio" 
                       id="dialog_service_standard" 
                       name="dialog_service_type" 
                       value="standard" 
                       class="sr-only"
                       @if(!env('DIALOG_API_KEY') || strpos(env('DIALOG_API_KEY'), 'test') === false) checked @endif>
                <label for="dialog_service_standard" 
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-rocket text-2xl mb-2" style="color: #25D366;"></i>
                    <span class="font-medium">Production</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Live WhatsApp API</span>
                </label>
            </div>
            <div class="relative">
                <input type="radio" 
                       id="dialog_service_sandbox" 
                       name="dialog_service_type" 
                       value="sandbox" 
                       class="sr-only"
                       @if(env('DIALOG_API_KEY') && strpos(env('DIALOG_API_KEY'), 'test') !== false) checked @endif>
                <label for="dialog_service_sandbox" 
                       class="flex flex-col items-center justify-center p-4 rounded-lg border cursor-pointer transition-all duration-200"
                       style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-flask text-2xl mb-2" style="color: var(--primary);"></i>
                    <span class="font-medium">Sandbox</span>
                    <span class="text-xs text-center mt-1" style="color: var(--text-secondary);">Test environment</span>
                </label>
            </div>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            Select sandbox for testing or production for real messages
        </p>
    </div>

    <!-- WhatsApp Enabled -->
    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: rgba(37, 211, 102, 0.05); border: 1px solid rgba(37, 211, 102, 0.2);">
        <div>
            <label class="font-medium" style="color: var(--text-primary);">
                <i class="fas fa-toggle-on mr-2" style="color: #25D366;"></i>
                Enable 360Dialog WhatsApp
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
                   @if(env('WHATSAPP_PROVIDER') === '360dialog') checked @endif>
            <div class="toggle-switch-large">
                <div class="toggle-slider-large"></div>
            </div>
        </label>
    </div>

    <!-- Test Connection Section -->
    <div class="rounded-lg p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-3" style="color: var(--info);">
            <i class="fas fa-plug mr-1"></i> Test Connection
        </h5>
        
        <div class="space-y-3">
            <!-- Test Phone Number -->
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                    Test Phone Number
                </label>
                <div class="relative">
                    <input type="text" 
                           id="test_number_360dialog" 
                           class="w-full pl-10 pr-4 py-2 rounded-lg border"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="whatsapp:+233XXXXXXXXX"
                           value="whatsapp:+233000000000">
                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <i class="fas fa-mobile-alt" style="color: var(--text-secondary);"></i>
                    </div>
                </div>
            </div>

            <!-- Test Message -->
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                    Test Message
                </label>
                <div class="relative">
                    <textarea id="test_message_360dialog" 
                              class="w-full pl-10 pr-4 py-2 rounded-lg border"
                              style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); min-height: 80px;"
                              placeholder="Enter test message...">🔧 360Dialog WhatsApp test from Property Management System</textarea>
                    <div class="absolute left-3 top-3">
                        <i class="fas fa-comment" style="color: var(--text-secondary);"></i>
                    </div>
                </div>
            </div>

            <!-- Test Buttons -->
            <div class="flex flex-wrap gap-2">
                <button type="button" 
                        class="test-connection inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:opacity-90"
                        data-provider="360dialog"
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-bolt mr-2"></i>
                    Test Connection
                </button>
                
                <button type="button" 
                        class="send-test inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:opacity-90"
                        data-provider="360dialog"
                        style="background-color: rgba(37, 211, 102, 0.1); color: #25D366; border: 1px solid rgba(37, 211, 102, 0.3);">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Test Message
                </button>
            </div>

            <!-- Test Result -->
            <div id="test_result_360dialog" class="hidden">
                <div class="p-3 rounded-lg mt-2" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 test-result-icon">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium test-result-message" style="color: var(--text-primary);">
                                Testing connection...
                            </p>
                        </div>
                    </div>
                    <div class="test-result-details mt-2 hidden">
                        <pre class="text-xs p-2 rounded" style="background-color: rgba(0, 0, 0, 0.05); color: var(--text-secondary); overflow: auto; max-height: 150px;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhook Information -->
    <div class="rounded-lg p-4" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--warning);">
            <i class="fas fa-link mr-1"></i> Webhook Configuration
        </h5>
        <p class="text-sm mb-3" style="color: var(--text-secondary);">
            For delivery receipts and incoming messages, configure webhook in your 360Dialog dashboard:
        </p>
        <div class="flex items-center gap-2">
            <div class="flex-grow">
                <div class="relative">
                    <input type="text" 
                           value="{{ route('webhook.whatsapp', [], false) }}"
                           class="w-full pl-10 pr-10 py-2 rounded-lg border text-sm"
                           style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                           readonly>
                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <i class="fas fa-link" style="color: var(--text-secondary);"></i>
                    </div>
                    <button type="button" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 copy-webhook-url"
                            data-url="{{ route('webhook.whatsapp', [], false) }}">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <button type="button" 
                    class="copy-webhook-url inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:opacity-90"
                    data-url="{{ route('webhook.whatsapp', [], false) }}"
                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <i class="fas fa-copy mr-1"></i> Copy
            </button>
        </div>
        <p class="text-xs mt-2" style="color: var(--text-secondary);">
            Copy this URL to your 360Dialog dashboard → Webhook Settings
        </p>
    </div>

    <!-- Pricing Information -->
    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--success);">
            <i class="fas fa-tag mr-1"></i> Pricing Information
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h6 class="font-medium mb-2" style="color: #25D366;">Cost Effective</h6>
                <ul class="space-y-1">
                    <li class="flex items-center text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-check-circle mr-2" style="color: #25D366; font-size: 0.8rem;"></i>
                        ~$0.0058 per message
                    </li>
                    <li class="flex items-center text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-check-circle mr-2" style="color: #25D366; font-size: 0.8rem;"></i>
                        No monthly conversation fees
                    </li>
                    <li class="flex items-center text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-check-circle mr-2" style="color: #25D366; font-size: 0.8rem;"></i>
                        Pay only for messages sent
                    </li>
                </ul>
            </div>
            <div>
                <h6 class="font-medium mb-2" style="color: var(--text-primary);">Estimated Costs</h6>
                <ul class="space-y-1">
                    <li class="flex justify-between text-sm" style="color: var(--text-secondary);">
                        <span>100 messages:</span>
                        <span class="font-medium" style="color: var(--success);">~$0.58</span>
                    </li>
                    <li class="flex justify-between text-sm" style="color: var(--text-secondary);">
                        <span>1,000 messages:</span>
                        <span class="font-medium" style="color: var(--success);">~$5.80</span>
                    </li>
                    <li class="flex justify-between text-sm" style="color: var(--text-secondary);">
                        <span>5,000 messages:</span>
                        <span class="font-medium" style="color: var(--success);">~$29.00</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 pt-6 border-t" style="border-color: var(--border-color);">
        <button type="submit" 
                class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                style="background-color: rgba(37, 211, 102, 0.1); color: #25D366; border: 1px solid rgba(37, 211, 102, 0.3);">
            <i class="fas fa-save mr-2"></i>
            Save Configuration
        </button>
        
        <button type="button" 
                class="reset-config inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
                data-provider="360dialog"
                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <a href="https://dashboard.360dialog.com/" 
           target="_blank" 
           class="inline-flex items-center rounded-lg px-5 py-2.5 font-medium transition-colors hover:opacity-90"
           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-external-link-alt mr-2"></i>
            Open 360Dialog Dashboard
        </a>
    </div>

    <!-- Documentation Links -->
    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h5 class="font-medium mb-2" style="color: var(--info);">
            <i class="fas fa-book mr-1"></i> Quick Links
        </h5>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="https://docs.360dialog.com/whatsapp-api/" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #25D366;">
                <i class="fas fa-link mr-2 text-xs"></i> API Documentation
            </a>
            <a href="https://docs.360dialog.com/docs/getting-started" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #25D366;">
                <i class="fas fa-play-circle mr-2 text-xs"></i> Getting Started
            </a>
            <a href="https://docs.360dialog.com/docs/whatsapp-message-templates" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #25D366;">
                <i class="fas fa-envelope mr-2 text-xs"></i> Message Templates
            </a>
            <a href="https://360dialog.com/pricing" 
               target="_blank" 
               class="text-sm hover:opacity-80 flex items-center"
               style="color: #25D366;">
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

document.addEventListener('DOMContentLoaded', function() {
    const provider = '360dialog';
    
    // Copy Webhook URL
    document.querySelectorAll('.copy-webhook-url').forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                }, 2000);
            });
        });
    });

    // Test Connection
    document.querySelector(`[data-provider="${provider}"].test-connection`).addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        const resultDiv = document.getElementById('test_result_360dialog');
        const icon = resultDiv.querySelector('.test-result-icon');
        const message = resultDiv.querySelector('.test-result-message');
        const details = resultDiv.querySelector('.test-result-details');
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
        resultDiv.classList.remove('hidden');
        icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        message.textContent = 'Testing 360Dialog connection...';
        details.classList.add('hidden');
        
        fetch('{{ route("admin.whatsapp-providers.test-connection") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                provider: provider
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: #25D366;"></i>';
                message.textContent = data.message;
                resultDiv.querySelector('.test-result-details pre').textContent = JSON.stringify(data.details || {}, null, 2);
                details.classList.remove('hidden');
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
                message.textContent = data.message;
                resultDiv.querySelector('.test-result-details pre').textContent = JSON.stringify(data.details || {}, null, 2);
                details.classList.remove('hidden');
            }
        })
        .catch(error => {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
            message.textContent = 'Connection test failed: ' + error.message;
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-bolt mr-2"></i> Test Connection';
        });
    });

    // Send Test Message
    document.querySelector(`[data-provider="${provider}"].send-test`).addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        const phoneNumber = document.getElementById('test_number_360dialog').value;
        const messageText = document.getElementById('test_message_360dialog').value;
        const resultDiv = document.getElementById('test_result_360dialog');
        const icon = resultDiv.querySelector('.test-result-icon');
        const message = resultDiv.querySelector('.test-result-message');
        const details = resultDiv.querySelector('.test-result-details');
        
        if (!phoneNumber || !messageText) {
            alert('Please enter both phone number and message');
            return;
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
        resultDiv.classList.remove('hidden');
        icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        message.textContent = 'Sending test message...';
        details.classList.add('hidden');
        
        fetch('{{ route("admin.whatsapp-providers.send-test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                provider: provider,
                phone_number: phoneNumber,
                message: messageText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: #25D366;"></i>';
                message.textContent = data.message;
                resultDiv.querySelector('.test-result-details pre').textContent = JSON.stringify(data.details || {}, null, 2);
                details.classList.remove('hidden');
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
                message.textContent = data.message;
                resultDiv.querySelector('.test-result-details pre').textContent = JSON.stringify(data.details || {}, null, 2);
                details.classList.remove('hidden');
            }
        })
        .catch(error => {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
            message.textContent = 'Send failed: ' + error.message;
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Test Message';
        });
    });

    // Reset Configuration
    document.querySelector(`[data-provider="${provider}"].reset-config`).addEventListener('click', function() {
        if (!confirm('Are you sure you want to reset 360Dialog configuration? This will clear all API settings.')) {
            return;
        }
        
        const button = this;
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting...';
        
        fetch('{{ route("admin.whatsapp-providers.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                provider: provider
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Reset failed: ' + error.message);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        });
    });
});
</script>

<style>
.required::after {
    content: " *";
    color: var(--danger);
}

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
    background-color: rgba(37, 211, 102, 0.2);
}

input[type="checkbox"]:checked + .toggle-switch-large .toggle-slider-large {
    background-color: #25D366;
    transform: translateX(30px);
}

input:invalid {
    border-color: var(--danger) !important;
}

input:invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

input[type="radio"]:checked + label {
    border-color: #25D366 !important;
    background-color: rgba(37, 211, 102, 0.05) !important;
}

input[type="radio"]:checked + label #dialog_service_sandbox {
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

/* Form focus styles */
input:focus, textarea:focus {
    border-color: #25D366 !important;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1) !important;
    outline: none;
}

/* Hover effects for buttons */
button:hover, a:hover {
    opacity: 0.9;
    transition: opacity 0.2s;
}

/* Custom scrollbar for details */
pre::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

pre::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

pre::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

pre::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}
</style>