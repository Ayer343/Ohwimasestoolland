<!-- ============================================ -->
{{-- EXPRESSPAY PROVIDER CONFIGURATION --}}
{{-- ============================================ --}}
<div class="space-y-6 animate-fadeInUp">
    <!-- State-Aware Status Banner -->
    @if(session('payment_update_status') == 'processing' && session('payment_update_provider') == 'expresspay')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(0, 102, 204, 0.3); background: linear-gradient(to right, rgba(0, 102, 204, 0.05), rgba(0, 102, 204, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(0, 102, 204, 0.2);">
                    <i class="fas fa-sync-alt fa-spin text-lg" style="color: #0066CC;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #0066CC;">Configuration Update in Progress</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Your ExpressPay settings are being updated in the background. This may take a few moments.
                </p>
                <div class="mt-2 flex items-center text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    <span>You can continue using the system while updates are being processed</span>
                </div>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Success Message -->
    @if(session('success') && str_contains(session('success'), 'ExpressPay'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(40, 167, 69, 0.3); background: linear-gradient(to right, rgba(40, 167, 69, 0.05), rgba(40, 167, 69, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(40, 167, 69, 0.2);">
                    <i class="fas fa-check-circle text-lg" style="color: #28a745;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #28a745;">Configuration Updated Successfully!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
                <div class="mt-3 flex items-center">
                    <div class="h-1 flex-1 rounded-full" style="background-color: rgba(40, 167, 69, 0.3);">
                        <div class="h-full animate-progress rounded-full" style="background-color: #28a745;"></div>
                    </div>
                    <span class="ml-2 text-xs" style="color: var(--text-secondary);">Verifying connection...</span>
                </div>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error') && str_contains(session('error'), 'ExpressPay'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(220, 53, 69, 0.3); background: linear-gradient(to right, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(220, 53, 69, 0.2);">
                    <i class="fas fa-exclamation-circle text-lg" style="color: #dc3545;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #dc3545;">Configuration Update Failed</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                <div class="mt-3 flex space-x-2">
                    <button onclick="retryExpresspayUpdate()" 
                            class="rounded-lg px-3 py-1 text-sm font-medium transition-colors hover:opacity-80"
                            style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4);">
                        <i class="fas fa-redo mr-1"></i> Retry Update
                    </button>
                    <button onclick="viewErrorDetails()" 
                            class="rounded-lg px-3 py-1 text-sm font-medium transition-colors hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                        <i class="fas fa-bug mr-1"></i> View Details
                    </button>
                </div>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Form Validation Errors -->
    @if($errors->any())
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(255, 193, 7, 0.3); background: linear-gradient(to right, rgba(255, 193, 7, 0.05), rgba(255, 193, 7, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(255, 193, 7, 0.2);">
                    <i class="fas fa-exclamation-triangle text-lg" style="color: #ffc107;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #ffc107;">Please fix the following issues:</h4>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm" style="color: var(--text-primary);">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Real-time Status Dashboard -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Connection Status -->
        <div class="card overflow-hidden status-card" id="expresspayConnectionCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="expresspayConnectionStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Connection</div>
                    <div class="text-sm font-semibold mt-1" id="expresspayConnectionStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="expresspayConnectionStatusDetails">Initializing connection status...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="expresspayConnectionExtraInfo"></div>
            </div>
        </div>

        <!-- Configuration Status -->
        <div class="card overflow-hidden status-card" id="expresspayConfigCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="expresspayConfigStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Configuration</div>
                    <div class="text-sm font-semibold mt-1" id="expresspayConfigStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="expresspayConfigStatusDetails">Validating configuration...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="expresspayConfigExtraInfo"></div>
            </div>
        </div>

        <!-- Environment Status -->
        <div class="card overflow-hidden status-card" id="expresspayEnvironmentCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="expresspayEnvironmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Environment</div>
                    <div class="text-sm font-semibold mt-1" id="expresspayEnvironmentStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="expresspayEnvironmentStatusDetails">Detecting environment...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="expresspayEnvironmentExtraInfo"></div>
            </div>
        </div>

        <!-- Provider Status -->
        <div class="card overflow-hidden status-card" id="expresspayProviderStatusCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="expresspayProviderStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-power-off"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Provider Status</div>
                    <div class="text-sm font-semibold mt-1" id="expresspayProviderStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="expresspayProviderStatusDetails">Checking provider availability...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="flex space-x-2">
                    <button id="expresspayToggleProviderBtn" 
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                            onclick="toggleExpresspayProviderStatus()">
                        <i class="fas fa-power-off mr-1"></i>
                        <span id="expresspayToggleBtnText">Enable</span>
                    </button>
                    <button onclick="forceExpresspayStatusRefresh()" 
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80"
                            style="background-color: rgba(0, 102, 204, 0.1); color: #0066CC;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Progress Indicator -->
    <div id="expresspayLiveProgressIndicator" class="hidden overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(0, 102, 204, 0.3); background: linear-gradient(to right, rgba(0, 102, 204, 0.05), rgba(0, 102, 204, 0.1));">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-sync-alt fa-spin mr-2" style="color: #0066CC;"></i>
                <span class="font-medium" style="color: var(--text-primary);">Background Update in Progress</span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <span id="expresspayProgressStep">Initializing...</span>
            </div>
        </div>
        <div class="mb-2 h-2 w-full rounded-full" style="background-color: rgba(0, 102, 204, 0.3);">
            <div id="expresspayProgressBar" class="h-2 rounded-full transition-all duration-500" style="background-color: #0066CC; width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
            <span>Started</span>
            <span id="expresspayProgressTime">Just now</span>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="flex flex-wrap gap-2">
        <button onclick="testExpresspayConnection()" 
                id="expresspayTestConnectionBtn" 
                class="btn-primary inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                style="background-color: #0066CC;">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button onclick="verifyExpresspayEnvironment()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4);">
            <i class="fas fa-check-circle mr-2"></i>
            Verify Environment
        </button>
        
        <button onclick="resetExpresspayConfiguration()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <button onclick="forceExpresspayStatusRefresh()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Status
        </button>
    </div>

    <!-- Configuration Form -->
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(0, 102, 204, 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">ExpressPay Configuration</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">Configure your ExpressPay API credentials for payment processing in Ghana</p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" id="expresspayFormStatusBadge">
                        <span class="mr-1.5 h-2 w-2 rounded-full"></span>
                        Loading...
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="expresspayConfigForm" class="provider-form" method="POST" action="{{ route('admin.payment-providers.configure') }}" data-provider="expresspay">
                @csrf
                <input type="hidden" name="provider" value="expresspay">
                
                <div class="space-y-6">
                    <!-- Enable/Disable Toggle -->
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Enable ExpressPay</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Toggle to enable/disable ExpressPay payment processing</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" id="expresspayEnabledToggle" class="sr-only peer" 
                                {{ old('enabled', env('EXPRESSPAY_ENABLED', false)) ? 'checked' : '' }}
                                onchange="onExpresspayEnabledChange()">
                            <div class="toggle-bg block h-8 w-14 rounded-full transition-colors"></div>
                            <div class="toggle-dot absolute left-1 top-1 h-6 w-6 rounded-full transition-all transform"></div>
                        </label>
                    </div>

                    <!-- Environment Selection -->
                    <div>
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            Environment <span style="color: #dc3545;">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="expresspaySandboxLabel">
                                <input type="radio" name="environment" value="sandbox" class="sr-only" id="expresspaySandboxRadio"
                                    {{ (old('environment', env('EXPRESSPAY_ENVIRONMENT', 'sandbox')) === 'sandbox') ? 'checked' : '' }}
                                    onchange="onExpresspayEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="expresspaySandboxRadioIndicator">
                                        <div class="w-2 h-2 rounded-full"></div>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-flask mr-1" style="color: #ffc107;"></i> Sandbox (Test Mode)
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">Use test credentials for development</div>
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="expresspayProductionLabel">
                                <input type="radio" name="environment" value="production" class="sr-only" id="expresspayProductionRadio"
                                    {{ (old('environment', env('EXPRESSPAY_ENVIRONMENT', 'sandbox')) === 'production') ? 'checked' : '' }}
                                    onchange="onExpresspayEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="expresspayProductionRadioIndicator">
                                        <div class="w-2 h-2 rounded-full"></div>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-rocket mr-1" style="color: #28a745;"></i> Production (Live)
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">Process real payments</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> 
                            Sandbox mode uses test credentials and doesn't process real money
                        </p>
                    </div>

                    <!-- API Credentials Section -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Merchant ID -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Merchant ID</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="expresspayMerchantIdStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="text" 
                                   name="merchant_id" 
                                   id="expresspayMerchantIdInput"
                                   value="{{ old('merchant_id', env('EXPRESSPAY_MERCHANT_ID', '')) }}"
                                   class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-600 focus:border-blue-600 font-mono text-sm"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Enter your ExpressPay Merchant ID"
                                   oninput="validateExpresspayMerchantId()"
                                   autocomplete="off"
                                   required>
                            <div class="mt-1">
                                <div class="text-xs" id="expresspayMerchantIdValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-store mr-1"></i> Your merchant ID provided by ExpressPay
                            </p>
                            @error('merchant_id')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- API Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>API Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="expresspayApiKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="api_key" 
                                       id="expresspayApiKeyInput"
                                       value="{{ old('api_key', env('EXPRESSPAY_API_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-600 focus:border-blue-600 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your ExpressPay API Key"
                                       oninput="validateExpresspayApiKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="toggleExpresspayVisibility('expresspayApiKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="expresspayApiKeyValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-lock mr-1"></i> Your API key for authenticating with ExpressPay
                            </p>
                            @error('api_key')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Callback URL (Optional) -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Callback URL</span>
                                    <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">(Optional)</span>
                                    <div class="ml-2" id="expresspayCallbackUrlStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="text" 
                                   name="callback_url" 
                                   id="expresspayCallbackUrlInput"
                                   value="{{ old('callback_url', env('EXPRESSPAY_CALLBACK_URL', '')) }}"
                                   class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-600 focus:border-blue-600 font-mono text-sm"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="https://yourdomain.com/callback/expresspay"
                                   oninput="validateExpresspayCallbackUrl()">
                            <div class="mt-1">
                                <div class="text-xs" id="expresspayCallbackUrlValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-link mr-1"></i> Optional: Custom callback URL for payment notifications
                            </p>
                            @error('callback_url')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Webhook Configuration -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(40, 167, 69, 0.05); border-color: rgba(40, 167, 69, 0.2);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-webhook mr-2" style="color: #28a745;"></i>
                            Webhook Configuration
                        </h6>
                        <div class="space-y-3 text-sm">
                            <p style="color: var(--text-secondary);">Add the following URL to your ExpressPay Dashboard → Developers → Webhooks:</p>
                            <div class="flex items-center justify-between rounded-lg p-2 font-mono text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <code id="expresspayWebhookUrl" style="color: var(--text-primary);">{{ url('/api/payments/expresspay/webhook') }}</code>
                                <button type="button" onclick="copyExpresspayWebhookUrl()" class="ml-2 px-2 py-1 rounded hover:opacity-80" style="background-color: rgba(0, 102, 204, 0.1); color: #0066CC;">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(255, 193, 7, 0.1);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: #ffc107;"></i>
                                    <p class="text-xs" style="color: var(--text-primary);">
                                        <strong>Important:</strong> Webhooks are essential for automatic payment confirmation. 
                                        Configure the webhook URL above in your ExpressPay dashboard for the <code class="px-1 rounded" style="background-color: rgba(0,0,0,0.1);">payment.success</code> event.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Information Panel -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(var(--bg-secondary-rgb), 0.5); border-color: var(--border-color);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2" style="color: #0066CC;"></i>
                            Key Information
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Merchant ID</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Unique identifier for your merchant account</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">API Key</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Used for authenticating API requests</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #0066CC;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Test Credentials</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Available upon signup - no approval needed for sandbox</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #0066CC;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Production Access</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Requires business verification approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 p-2 rounded" style="background-color: rgba(220, 53, 69, 0.1);">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: #dc3545;"></i>
                                <span class="text-xs font-medium" style="color: #dc3545;">Never share your API Key publicly</span>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Summary -->
                    <div id="expresspayConfigSummary" class="hidden rounded-lg border p-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">Configuration Summary</h4>
                            <button type="button" onclick="hideExpresspayConfigSummary()" class="hover:opacity-70" style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Merchant ID:</div>
                                <div class="font-medium" id="expresspaySummaryMerchantId" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">API Key:</div>
                                <div class="font-medium" id="expresspaySummaryApiKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Callback URL:</div>
                                <div class="font-medium" id="expresspaySummaryCallbackUrl" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Environment:</div>
                                <div class="font-medium" id="expresspaySummaryEnvironment" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Status:</div>
                                <div class="font-medium" id="expresspaySummaryStatus" style="color: var(--text-primary);">Not set</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                        <div class="flex space-x-2">
                            <button type="button" 
                                    onclick="showExpresspayConfigSummary()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: #0066CC;">
                                <i class="fas fa-info-circle mr-1"></i>
                                Summary
                            </button>
                            <button type="button" 
                                    onclick="clearExpresspayForm()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: var(--text-secondary);">
                                <i class="fas fa-undo mr-1"></i>
                                Clear
                            </button>
                        </div>
                        <button type="submit" 
                                id="expresspaySubmitBtn"
                                class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                                style="background-color: #0066CC;">
                            <i class="fas fa-save mr-2"></i>
                            Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Test Results -->
    <div id="expresspayConnectionResult" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-plug mr-3 text-lg" style="color: #0066CC;"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Connection Test Results</h3>
                </div>
                <button onclick="closeExpresspayConnectionResult()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="expresspayConnectionResultContent"></div>
        </div>
    </div>

    <!-- Fee Information -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(40, 167, 69, 0.05));">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">ExpressPay Fees (Ghana)</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Mobile Money</span>
                        <span style="color: #28a745;">1.95% (min GHS 0.30)</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Card Payments</span>
                        <span style="color: #28a745;">1.95% (min GHS 0.30)</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Settlement Time</span>
                        <span style="color: #0066CC;">T+1 to T+3 business days</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium" style="color: var(--text-primary);">Minimum Payout</span>
                        <span style="color: var(--text-primary);">GHS 10</span>
                    </div>
                </div>
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(0, 102, 204, 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-calculator mr-2 mt-0.5" style="color: #0066CC;"></i>
                        <div>
                            <p class="font-medium text-sm" style="color: #0066CC;">Example Calculation (GHS 100)</p>
                            <p class="mt-1 text-xs" style="color: var(--text-primary);">Fee: 1.95% (GHS 1.95) | You receive: GHS 98.05</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for ExpressPay -->
<script>
// State Management
let expresspayCurrentState = {
    connection: 'checking',
    configuration: 'checking',
    environment: 'unknown',
    providerStatus: 'checking'
};

let expresspaySubmittedConfig = @json(session('submitted_config', null));
let expresspayIsPolling = false;
let expresspayPollInterval = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeExpresspayInterface();
    loadExpresspayInitialStatus();
    
    if (expresspaySubmittedConfig && expresspaySubmittedConfig.provider === 'expresspay') {
        applyExpresspaySubmittedConfig(expresspaySubmittedConfig);
        startExpresspayStatusPolling();
    }
    
    setupExpresspayFormValidation();
    initExpresspayToggleSwitch();
    initExpresspayStatusCards();
    initExpresspayEnvironmentRadios();
});

// Initialize environment radio styling
function initExpresspayEnvironmentRadios() {
    const sandboxRadio = document.getElementById('expresspaySandboxRadio');
    const productionRadio = document.getElementById('expresspayProductionRadio');
    
    updateExpresspayEnvironmentRadioStyle();
    
    sandboxRadio.addEventListener('change', updateExpresspayEnvironmentRadioStyle);
    productionRadio.addEventListener('change', updateExpresspayEnvironmentRadioStyle);
}

function updateExpresspayEnvironmentRadioStyle() {
    const sandboxRadio = document.getElementById('expresspaySandboxRadio');
    const productionRadio = document.getElementById('expresspayProductionRadio');
    const sandboxLabel = document.getElementById('expresspaySandboxLabel');
    const productionLabel = document.getElementById('expresspayProductionLabel');
    const sandboxIndicator = document.getElementById('expresspaySandboxRadioIndicator');
    const productionIndicator = document.getElementById('expresspayProductionRadioIndicator');
    
    if (sandboxRadio.checked) {
        sandboxLabel.style.borderColor = '#0066CC';
        sandboxLabel.style.backgroundColor = 'rgba(0, 102, 204, 0.05)';
        sandboxIndicator.style.borderColor = '#0066CC';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = '#0066CC';
        productionLabel.style.borderColor = 'var(--border-color)';
        productionLabel.style.backgroundColor = 'var(--bg-secondary)';
        productionIndicator.style.borderColor = 'var(--border-color)';
        productionIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    } else {
        productionLabel.style.borderColor = '#0066CC';
        productionLabel.style.backgroundColor = 'rgba(0, 102, 204, 0.05)';
        productionIndicator.style.borderColor = '#0066CC';
        productionIndicator.querySelector('.w-2').style.backgroundColor = '#0066CC';
        sandboxLabel.style.borderColor = 'var(--border-color)';
        sandboxLabel.style.backgroundColor = 'var(--bg-secondary)';
        sandboxIndicator.style.borderColor = 'var(--border-color)';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    }
}

// Initialize status cards
function initExpresspayStatusCards() {
    const cards = document.querySelectorAll('.status-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-2px)';
            card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = '';
        });
    });
}

// Initialize ExpressPay interface
function initializeExpresspayInterface() {
    const toggle = document.getElementById('expresspayEnabledToggle');
    const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateExpresspayToggleState(toggle.checked, toggleDot, toggleBg);
    
    toggle.addEventListener('change', function() {
        updateExpresspayToggleState(this.checked, toggleDot, toggleBg);
        onExpresspayEnabledChange();
    });
    
    // ✅ FORM SUBMISSION IS HANDLED BY THE MAIN PROVIDER FORM HANDLER
    // The form has class="provider-form" and data-provider="expresspay"
    
    validateExpresspayMerchantId();
    validateExpresspayApiKey();
    onExpresspayEnabledChange();
}

// Update toggle switch state
function updateExpresspayToggleState(isChecked, toggleDot, toggleBg) {
    if (isChecked) {
        toggleDot.style.transform = 'translateX(1.5rem)';
        toggleDot.style.backgroundColor = 'white';
        toggleBg.style.backgroundColor = '#28a745';
    } else {
        toggleDot.style.transform = 'translateX(0)';
        toggleDot.style.backgroundColor = 'white';
        toggleBg.style.backgroundColor = 'var(--border-color)';
    }
}

// Initialize toggle switch
function initExpresspayToggleSwitch() {
    const toggle = document.getElementById('expresspayEnabledToggle');
    const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateExpresspayToggleState(toggle.checked, toggleDot, toggleBg);
}

// Load initial status
function loadExpresspayInitialStatus() {
    showExpresspayLoadingState();
    fetchExpresspayImmediateStatus()
        .then(status => {
            expresspayCurrentState = status;
            updateExpresspayStatusDisplay(status);
            updateExpresspayFormBasedOnStatus(status);
            hideExpresspayLoadingState();
        })
        .catch(error => {
            console.error('Failed to load ExpressPay initial status:', error);
            fallbackToExpresspayFormStatus();
            hideExpresspayLoadingState();
        });
}

// Show loading state
function showExpresspayLoadingState() {
    const cards = ['expresspayConnectionCard', 'expresspayConfigCard', 'expresspayEnvironmentCard', 'expresspayProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.add('loading');
    });
}

// Hide loading state
function hideExpresspayLoadingState() {
    const cards = ['expresspayConnectionCard', 'expresspayConfigCard', 'expresspayEnvironmentCard', 'expresspayProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.remove('loading');
    });
}

// Fetch immediate status
function fetchExpresspayImmediateStatus() {
    return fetch('{{ route("admin.payment-providers.immediate-status") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'expresspay' })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response failed');
        return response.json();
    })
    .then(data => {
        if (data.success && data.data.expresspay) return data.data.expresspay;
        throw new Error('Invalid response format');
    });
}

// Update status display
function updateExpresspayStatusDisplay(status) {
    updateExpresspayConnectionStatus(status);
    updateExpresspayConfigurationStatus(status);
    updateExpresspayEnvironmentStatus(status);
    updateExpresspayProviderStatus(status);
    updateExpresspayFormStatusBadge(status);
    updateExpresspayTestButtonState(status);
    updateExpresspayProviderToggleButton(status);
}

// Update connection status
function updateExpresspayConnectionStatus(status) {
    const icon = document.getElementById('expresspayConnectionStatusIcon');
    const text = document.getElementById('expresspayConnectionStatusText');
    const details = document.getElementById('expresspayConnectionStatusDetails');
    const extraInfo = document.getElementById('expresspayConnectionExtraInfo');
    const card = document.getElementById('expresspayConnectionCard');
    
    if (status.enabled && status.configured) {
        icon.innerHTML = '<i class="fas fa-plug"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Connected</span>';
        details.textContent = 'API connection established';
        details.style.color = '#28a745';
        extraInfo.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Ready to process payments';
        extraInfo.style.color = '#28a745';
        card.style.borderLeft = '4px solid #28a745';
    } else if (status.enabled && !status.configured) {
        icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        icon.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
        icon.style.color = '#ffc107';
        text.innerHTML = '<span style="color: #ffc107;">Incomplete</span>';
        details.textContent = 'Missing API credentials';
        details.style.color = '#ffc107';
        extraInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Configure Merchant ID and API Key';
        extraInfo.style.color = '#ffc107';
        card.style.borderLeft = '4px solid #ffc107';
    } else {
        icon.innerHTML = '<i class="fas fa-times-circle"></i>';
        icon.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        icon.style.color = '#dc3545';
        text.innerHTML = '<span style="color: #dc3545;">Disabled</span>';
        details.textContent = 'Provider is disabled';
        details.style.color = '#dc3545';
        extraInfo.innerHTML = '<i class="fas fa-power-off mr-1"></i> Enable to activate';
        extraInfo.style.color = '#dc3545';
        card.style.borderLeft = '4px solid #dc3545';
    }
}

// Update configuration status
function updateExpresspayConfigurationStatus(status) {
    const icon = document.getElementById('expresspayConfigStatusIcon');
    const text = document.getElementById('expresspayConfigStatusText');
    const details = document.getElementById('expresspayConfigStatusDetails');
    const extraInfo = document.getElementById('expresspayConfigExtraInfo');
    const card = document.getElementById('expresspayConfigCard');
    
    if (status.configured) {
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Complete</span>';
        details.textContent = 'All required fields configured';
        details.style.color = '#28a745';
        
        const fields = [];
        if (status.merchant_id_configured) fields.push('Merchant ID');
        if (status.api_key_configured) fields.push('API Key');
        
        extraInfo.innerHTML = `<i class="fas fa-check mr-1"></i> Configured: ${fields.join(', ')}`;
        extraInfo.style.color = '#28a745';
        card.style.borderLeft = '4px solid #28a745';
    } else {
        icon.innerHTML = '<i class="fas fa-times-circle"></i>';
        icon.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        icon.style.color = '#dc3545';
        text.innerHTML = '<span style="color: #dc3545;">Incomplete</span>';
        
        const missing = status.missing_configuration || [];
        details.textContent = `Missing: ${missing.join(', ') || 'Required keys'}`;
        details.style.color = '#dc3545';
        
        if (missing.length > 0) {
            extraInfo.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i> Fill ${missing.length} required field(s)`;
        } else {
            extraInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Configuration required';
        }
        extraInfo.style.color = '#dc3545';
        card.style.borderLeft = '4px solid #dc3545';
    }
}

// Update environment status
function updateExpresspayEnvironmentStatus(status) {
    const icon = document.getElementById('expresspayEnvironmentStatusIcon');
    const text = document.getElementById('expresspayEnvironmentStatusText');
    const details = document.getElementById('expresspayEnvironmentStatusDetails');
    const extraInfo = document.getElementById('expresspayEnvironmentExtraInfo');
    const card = document.getElementById('expresspayEnvironmentCard');
    
    const environmentRadio = document.querySelector('input[name="environment"]:checked');
    const isProduction = environmentRadio && environmentRadio.value === 'production';
    
    if (isProduction) {
        icon.innerHTML = '<i class="fas fa-globe-africa"></i>';
        icon.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        icon.style.color = '#dc3545';
        text.innerHTML = '<span style="color: #dc3545;">Production</span>';
        details.textContent = 'Live payments enabled';
        details.style.color = '#dc3545';
        extraInfo.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Real transactions - Test carefully first';
        extraInfo.style.color = '#dc3545';
        card.style.borderLeft = '4px solid #dc3545';
    } else {
        icon.innerHTML = '<i class="fas fa-flask"></i>';
        icon.style.backgroundColor = 'rgba(0, 102, 204, 0.2)';
        icon.style.color = '#0066CC';
        text.innerHTML = '<span style="color: #0066CC;">Sandbox</span>';
        details.textContent = 'Test mode active';
        details.style.color = '#0066CC';
        extraInfo.innerHTML = '<i class="fas fa-check-circle mr-1"></i> No real money movement';
        extraInfo.style.color = '#0066CC';
        card.style.borderLeft = '4px solid #0066CC';
    }
}

// Update provider status
function updateExpresspayProviderStatus(status) {
    const icon = document.getElementById('expresspayProviderStatusIcon');
    const text = document.getElementById('expresspayProviderStatusText');
    const details = document.getElementById('expresspayProviderStatusDetails');
    const card = document.getElementById('expresspayProviderStatusCard');
    const enabled = status.enabled;
    
    if (enabled) {
        icon.innerHTML = '<i class="fas fa-play-circle"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Active</span>';
        details.textContent = 'Accepting payments';
        details.style.color = '#28a745';
        card.style.borderLeft = '4px solid #28a745';
    } else {
        icon.innerHTML = '<i class="fas fa-stop-circle"></i>';
        icon.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        icon.style.color = '#dc3545';
        text.innerHTML = '<span style="color: #dc3545;">Inactive</span>';
        details.textContent = 'Payments disabled';
        details.style.color = '#dc3545';
        card.style.borderLeft = '4px solid #dc3545';
    }
}

// Update provider toggle button
function updateExpresspayProviderToggleButton(status) {
    const button = document.getElementById('expresspayToggleProviderBtn');
    const buttonText = document.getElementById('expresspayToggleBtnText');
    const enabled = status.enabled;
    
    if (enabled) {
        buttonText.textContent = 'Disable';
        button.innerHTML = '<i class="fas fa-stop-circle mr-1"></i><span>Disable</span>';
        button.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        button.style.color = '#dc3545';
        button.style.border = '1px solid rgba(220, 53, 69, 0.4)';
    } else {
        buttonText.textContent = 'Enable';
        button.innerHTML = '<i class="fas fa-play-circle mr-1"></i><span>Enable</span>';
        button.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        button.style.color = '#28a745';
        button.style.border = '1px solid rgba(40, 167, 69, 0.4)';
    }
}

// Toggle provider status
function toggleExpresspayProviderStatus() {
    const enabledToggle = document.getElementById('expresspayEnabledToggle');
    const currentState = enabledToggle.checked;
    
    enabledToggle.checked = !currentState;
    
    const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    updateExpresspayToggleState(!currentState, toggleDot, toggleBg);
    
    onExpresspayEnabledChange();
    
    const action = currentState ? 'disabled' : 'enabled';
    showExpresspayToast(`ExpressPay will be ${action} when you save the configuration`, 'info');
}

// Update form status badge
function updateExpresspayFormStatusBadge(status) {
    const badge = document.getElementById('expresspayFormStatusBadge');
    const dot = badge.querySelector('.h-2');
    
    if (status.enabled && status.configured) {
        dot.style.backgroundColor = '#28a745';
        badge.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        badge.style.color = '#28a745';
        badge.innerHTML = '<span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: #28a745;"></span>Ready';
    } else if (status.enabled && !status.configured) {
        dot.style.backgroundColor = '#ffc107';
        badge.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
        badge.style.color = '#ffc107';
        badge.innerHTML = '<span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: #ffc107;"></span>Configuration Required';
    } else {
        dot.style.backgroundColor = '#dc3545';
        badge.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        badge.style.color = '#dc3545';
        badge.innerHTML = '<span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: #dc3545;"></span>Disabled';
    }
}

// Update test button state
function updateExpresspayTestButtonState(status) {
    const testBtn = document.getElementById('expresspayTestConnectionBtn');
    const isEnabled = status.enabled && status.configured;
    
    if (isEnabled) {
        testBtn.disabled = false;
        testBtn.style.opacity = '1';
        testBtn.style.cursor = 'pointer';
    } else {
        testBtn.disabled = true;
        testBtn.style.opacity = '0.5';
        testBtn.style.cursor = 'not-allowed';
    }
}

// Apply submitted config
function applyExpresspaySubmittedConfig(config) {
    const enabledToggle = document.getElementById('expresspayEnabledToggle');
    if (enabledToggle && config.enabled !== undefined) {
        enabledToggle.checked = config.enabled;
        const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateExpresspayToggleState(config.enabled, toggleDot, toggleBg);
    }
    onExpresspayEnabledChange();
}

// Start status polling
function startExpresspayStatusPolling() {
    if (expresspayIsPolling) return;
    
    expresspayIsPolling = true;
    let pollCount = 0;
    const maxPolls = 60;
    
    expresspayPollInterval = setInterval(() => {
        pollCount++;
        
        if (pollCount >= maxPolls) {
            stopExpresspayStatusPolling();
            showExpresspayToast('Status update timeout. Refresh the page to check current status.', 'warning');
            return;
        }
        
        fetchExpresspayImmediateStatus()
            .then(status => {
                expresspayCurrentState = status;
                updateExpresspayStatusDisplay(status);
                
                if (status.source !== 'cache_expected') {
                    stopExpresspayStatusPolling();
                    showExpresspayToast('Configuration update completed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('ExpressPay polling error:', error);
            });
    }, 1000);
}

// Stop status polling
function stopExpresspayStatusPolling() {
    if (expresspayPollInterval) {
        clearInterval(expresspayPollInterval);
        expresspayPollInterval = null;
    }
    expresspayIsPolling = false;
}

// Test connection
function testExpresspayConnection() {
    const testBtn = document.getElementById('expresspayTestConnectionBtn');
    const originalText = testBtn.innerHTML;
    
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Testing...';
    
    fetch('{{ route("admin.payment-providers.test-connection") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'expresspay' })
    })
    .then(response => response.json())
    .then(data => {
        showExpresspayConnectionResult(data.success, data.message, data.details);
        setTimeout(loadExpresspayInitialStatus, 1000);
    })
    .catch(error => {
        showExpresspayConnectionResult(false, 'Connection test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

// Reset configuration
function resetExpresspayConfiguration() {
    if (!confirm('⚠️ WARNING: This will clear all ExpressPay API credentials and disable the provider. Continue?')) {
        return;
    }
    
    fetch('{{ route("admin.payment-providers.reset") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'expresspay' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showExpresspayToast('Configuration reset successfully', 'success');
            clearExpresspayForm();
            setTimeout(loadExpresspayInitialStatus, 1000);
        } else {
            showExpresspayToast('Reset failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showExpresspayToast('Reset failed: ' + error.message, 'error');
    });
}

// Verify environment
function verifyExpresspayEnvironment() {
    const merchantId = document.getElementById('expresspayMerchantIdInput').value;
    const apiKey = document.getElementById('expresspayApiKeyInput').value;
    
    if (!merchantId || !apiKey) {
        showExpresspayToast('Please enter both Merchant ID and API Key first', 'warning');
        return;
    }
    
    fetch('{{ route("admin.payment-providers.verify-environment") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'expresspay' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = data.is_production ? 
                '🔴 PRODUCTION environment detected - Real payments will be processed' : 
                '🔵 SANDBOX environment detected - Test mode active';
            showExpresspayToast(message, data.is_production ? 'warning' : 'success');
            
            const status = expresspayCurrentState;
            status.environment = data.is_production ? 'production' : 'sandbox';
            updateExpresspayEnvironmentStatus(status);
        } else {
            showExpresspayToast('Environment verification failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showExpresspayToast('Verification failed: ' + error.message, 'error');
    });
}

// Show connection result
function showExpresspayConnectionResult(success, message, details = null) {
    const resultDiv = document.getElementById('expresspayConnectionResult');
    const contentDiv = document.getElementById('expresspayConnectionResultContent');
    
    const icon = success ? 'fa-check-circle' : 'fa-exclamation-circle';
    const iconColor = success ? '#28a745' : '#dc3545';
    const headerColor = success ? '#28a745' : '#dc3545';
    
    let detailsHtml = '';
    if (details) {
        detailsHtml = `
            <div class="mt-4">
                <h4 class="mb-2 text-sm font-medium" style="color: var(--text-primary);">Details:</h4>
                <pre class="overflow-x-auto rounded-lg p-3 text-xs" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">${JSON.stringify(details, null, 2)}</pre>
            </div>
        `;
    }
    
    contentDiv.innerHTML = `
        <div class="flex items-start">
            <i class="${icon} mr-3 mt-1 text-2xl" style="color: ${iconColor};"></i>
            <div class="flex-1">
                <h4 class="text-lg font-medium" style="color: ${headerColor};">${success ? 'Connection Successful' : 'Connection Failed'}</h4>
                <p class="mt-1" style="color: var(--text-primary);">${message}</p>
                ${detailsHtml}
                ${success ? '<div class="mt-3 p-2 rounded text-sm" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;"><i class="fas fa-check-circle mr-1"></i> Your ExpressPay integration is properly configured and ready to accept payments.</div>' : ''}
            </div>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
}

// Close connection result
function closeExpresspayConnectionResult() {
    document.getElementById('expresspayConnectionResult').classList.add('hidden');
}

// Show config summary
function showExpresspayConfigSummary() {
    const merchantId = document.getElementById('expresspayMerchantIdInput').value;
    const apiKey = document.getElementById('expresspayApiKeyInput').value;
    const callbackUrl = document.getElementById('expresspayCallbackUrlInput').value;
    const enabled = document.getElementById('expresspayEnabledToggle').checked;
    const environment = document.querySelector('input[name="environment"]:checked').value;
    
    document.getElementById('expresspaySummaryMerchantId').textContent = merchantId || 'Not set';
    document.getElementById('expresspaySummaryApiKey').textContent = apiKey ? '••••••••' + apiKey.slice(-4) : 'Not set';
    document.getElementById('expresspaySummaryCallbackUrl').textContent = callbackUrl || 'Not set';
    document.getElementById('expresspaySummaryEnvironment').textContent = environment === 'production' ? 'Production' : 'Sandbox';
    document.getElementById('expresspaySummaryStatus').textContent = enabled ? 'Enabled' : 'Disabled';
    
    document.getElementById('expresspayConfigSummary').classList.remove('hidden');
}

// Hide config summary
function hideExpresspayConfigSummary() {
    document.getElementById('expresspayConfigSummary').classList.add('hidden');
}

// Clear form
function clearExpresspayForm() {
    if (confirm('Clear all form fields?')) {
        document.getElementById('expresspayMerchantIdInput').value = '';
        document.getElementById('expresspayApiKeyInput').value = '';
        document.getElementById('expresspayCallbackUrlInput').value = '';
        document.getElementById('expresspayEnabledToggle').checked = false;
        document.getElementById('expresspaySandboxRadio').checked = true;
        
        const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateExpresspayToggleState(false, toggleDot, toggleBg);
        
        updateExpresspayEnvironmentRadioStyle();
        onExpresspayEnabledChange();
        validateExpresspayMerchantId();
        validateExpresspayApiKey();
        
        showExpresspayToast('Form cleared', 'info');
    }
}

// Toggle visibility
function toggleExpresspayVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Validate Merchant ID
function validateExpresspayMerchantId() {
    const input = document.getElementById('expresspayMerchantIdInput');
    const validation = document.getElementById('expresspayMerchantIdValidation');
    const icon = document.getElementById('expresspayMerchantIdStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Merchant ID is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (value.length < 5) {
        validation.textContent = 'Merchant ID appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ Merchant ID looks valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate API Key
function validateExpresspayApiKey() {
    const input = document.getElementById('expresspayApiKeyInput');
    const validation = document.getElementById('expresspayApiKeyValidation');
    const icon = document.getElementById('expresspayApiKeyStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'API Key is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (value.length < 20) {
        validation.textContent = 'API Key appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ API Key looks valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate Callback URL (optional)
function validateExpresspayCallbackUrl() {
    const input = document.getElementById('expresspayCallbackUrlInput');
    const validation = document.getElementById('expresspayCallbackUrlValidation');
    const icon = document.getElementById('expresspayCallbackUrlStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Optional - leave empty if not needed';
        validation.style.color = 'var(--text-secondary)';
        icon.innerHTML = '<i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>';
        return true;
    }
    
    try {
        new URL(value);
        if (value.startsWith('https://')) {
            validation.textContent = '✓ Valid HTTPS URL';
            validation.style.color = '#28a745';
            icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
        } else {
            validation.textContent = 'URL should use HTTPS for security';
            validation.style.color = '#ffc107';
            icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        }
        return true;
    } catch (e) {
        validation.textContent = 'Please enter a valid URL';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
}

// On enabled change
function onExpresspayEnabledChange() {
    const enabled = document.getElementById('expresspayEnabledToggle').checked;
    const statusText = document.getElementById('expresspayEnabledStatusText');
    
    if (enabled) {
        statusText.textContent = 'ExpressPay will be enabled for payment processing';
    } else {
        statusText.textContent = 'ExpressPay will be disabled';
    }
}

// On environment change
function onExpresspayEnvironmentChange() {
    updateExpresspayEnvironmentRadioStyle();
    updateExpresspayEnvironmentStatus(expresspayCurrentState);
}

// Set up form validation
function setupExpresspayFormValidation() {
    const form = document.getElementById('expresspayConfigForm');
    const submitBtn = document.getElementById('expresspaySubmitBtn');
    
    form.addEventListener('input', () => {
        const merchantIdValid = validateExpresspayMerchantId();
        const apiKeyValid = validateExpresspayApiKey();
        submitBtn.disabled = !(merchantIdValid && apiKeyValid);
    });
}

// Copy webhook URL
function copyExpresspayWebhookUrl() {
    const urlElement = document.getElementById('expresspayWebhookUrl');
    const url = urlElement.textContent;
    
    navigator.clipboard.writeText(url).then(() => {
        showExpresspayToast('Webhook URL copied to clipboard', 'success');
    }).catch(() => {
        showExpresspayToast('Failed to copy URL', 'error');
    });
}

// Show toast notification
function showExpresspayToast(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#0066CC'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 rounded-lg px-4 py-3 text-sm font-medium shadow-lg animate-slideInRight';
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.style.color = 'white';
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icons[type]} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Force status refresh
function forceExpresspayStatusRefresh() {
    loadExpresspayInitialStatus();
    showExpresspayToast('Status refreshed successfully', 'success');
}

// Fallback to form status
function fallbackToExpresspayFormStatus() {
    const merchantId = document.getElementById('expresspayMerchantIdInput').value.trim();
    const apiKey = document.getElementById('expresspayApiKeyInput').value.trim();
    const enabled = document.getElementById('expresspayEnabledToggle').checked;
    
    const fallbackStatus = {
        enabled: enabled,
        configured: !!(merchantId && apiKey),
        source: 'fallback'
    };
    
    updateExpresspayStatusDisplay(fallbackStatus);
}

// Update form based on status
function updateExpresspayFormBasedOnStatus(status) {
    if (status.source === 'cache_expected' || status.source === 'cache_actual') {
        return;
    }
    
    const enabledToggle = document.getElementById('expresspayEnabledToggle');
    if (enabledToggle.checked !== status.enabled) {
        enabledToggle.checked = status.enabled;
        const toggleDot = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#expresspayEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateExpresspayToggleState(status.enabled, toggleDot, toggleBg);
        onExpresspayEnabledChange();
    }
}

// Retry failed update
function retryExpresspayUpdate() {
    fetch('{{ route("admin.payment-providers.retry-update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'expresspay' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showExpresspayToast('Update retry initiated', 'success');
            startExpresspayStatusPolling();
        } else {
            showExpresspayToast(data.message || 'Retry failed', 'error');
        }
    })
    .catch(error => {
        showExpresspayToast('Retry failed: ' + error.message, 'error');
    });
}

// View error details
function viewErrorDetails() {
    showExpresspayToast('Error details functionality would show detailed logs', 'info');
}
</script>

<style>
/* ExpressPay-specific styles */
.animate-fadeInUp {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-progress {
    animation: progress 2s ease-in-out infinite;
}

@keyframes progress {
    0% { width: 0%; }
    100% { width: 100%; }
}

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.status-card {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    position: relative;
}

.status-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.status-card.loading {
    opacity: 0.7;
}

.status-card.loading .status-icon {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.toggle-bg {
    background-color: var(--border-color);
    transition: background-color 0.3s ease;
}

.toggle-dot {
    background-color: white;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
}

input:focus, select:focus {
    outline: none;
    border-color: #0066CC !important;
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

.animate-slideInRight {
    animation: slideInRight 0.3s ease-out;
}

code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .status-card {
        margin-bottom: 1rem;
    }
    
    .flex-col.sm\\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .space-x-3 {
        width: 100%;
        justify-content: space-between;
    }
    
    .space-x-3 button {
        flex: 1;
        text-align: center;
    }
}
</style>