<!-- ============================================ -->
{{-- PAYSTACK PROVIDER CONFIGURATION --}}
{{-- ============================================ --}}
<div class="space-y-6 animate-fadeInUp">
    <!-- State-Aware Status Banner -->
    @if(session('payment_update_status') == 'processing' && session('payment_update_provider') == 'paystack')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(0, 123, 255, 0.3); background: linear-gradient(to right, rgba(0, 123, 255, 0.05), rgba(0, 123, 255, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(0, 123, 255, 0.2);">
                    <i class="fas fa-sync-alt fa-spin text-lg" style="color: #007bff;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #007bff;">Configuration Update in Progress</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Your Paystack settings are being updated in the background. This may take a few moments.
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
    @if(session('success') && str_contains(session('success'), 'Paystack'))
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
    @if(session('error') && str_contains(session('error'), 'Paystack'))
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
                    <button onclick="retryPaystackUpdate()" 
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
        <div class="card overflow-hidden status-card" id="paystackConnectionCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="paystackConnectionStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Connection</div>
                    <div class="text-sm font-semibold mt-1" id="paystackConnectionStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="paystackConnectionStatusDetails">Initializing connection status...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="paystackConnectionExtraInfo"></div>
            </div>
        </div>

        <!-- Configuration Status -->
        <div class="card overflow-hidden status-card" id="paystackConfigCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="paystackConfigStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Configuration</div>
                    <div class="text-sm font-semibold mt-1" id="paystackConfigStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="paystackConfigStatusDetails">Validating configuration...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="paystackConfigExtraInfo"></div>
            </div>
        </div>

        <!-- Environment Status -->
        <div class="card overflow-hidden status-card" id="paystackEnvironmentCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="paystackEnvironmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Environment</div>
                    <div class="text-sm font-semibold mt-1" id="paystackEnvironmentStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="paystackEnvironmentStatusDetails">Detecting environment...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="paystackEnvironmentExtraInfo"></div>
            </div>
        </div>

        <!-- Provider Status -->
        <div class="card overflow-hidden status-card" id="paystackProviderStatusCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="paystackProviderStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-power-off"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Provider Status</div>
                    <div class="text-sm font-semibold mt-1" id="paystackProviderStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="paystackProviderStatusDetails">Checking provider availability...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="flex space-x-2">
                    <button id="paystackToggleProviderBtn" 
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                            onclick="togglePaystackProviderStatus()">
                        <i class="fas fa-power-off mr-1"></i>
                        <span id="paystackToggleBtnText">Enable</span>
                    </button>
                    <button onclick="forcePaystackStatusRefresh()" 
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80"
                            style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Progress Indicator -->
    <div id="paystackLiveProgressIndicator" class="hidden overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(0, 123, 255, 0.3); background: linear-gradient(to right, rgba(0, 123, 255, 0.05), rgba(0, 123, 255, 0.1));">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-sync-alt fa-spin mr-2" style="color: #007bff;"></i>
                <span class="font-medium" style="color: var(--text-primary);">Background Update in Progress</span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <span id="paystackProgressStep">Initializing...</span>
            </div>
        </div>
        <div class="mb-2 h-2 w-full rounded-full" style="background-color: rgba(0, 123, 255, 0.3);">
            <div id="paystackProgressBar" class="h-2 rounded-full transition-all duration-500" style="background-color: #007bff; width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
            <span>Started</span>
            <span id="paystackProgressTime">Just now</span>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="flex flex-wrap gap-2">
        <button onclick="testPaystackConnection()" 
                id="paystackTestConnectionBtn" 
                class="btn-primary inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                style="background-color: #007bff;">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button onclick="verifyPaystackEnvironment()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4);">
            <i class="fas fa-check-circle mr-2"></i>
            Verify Environment
        </button>
        
        <button onclick="resetPaystackConfiguration()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <button onclick="forcePaystackStatusRefresh()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Status
        </button>
    </div>

    <!-- Configuration Form -->
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(0, 123, 255, 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Paystack Configuration</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">Configure your Paystack API credentials for payment processing in Ghana</p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" id="paystackFormStatusBadge">
                        <span class="mr-1.5 h-2 w-2 rounded-full"></span>
                        Loading...
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="paystackConfigForm" class="provider-form" method="POST" action="{{ route('admin.payment-providers.configure') }}" data-provider="paystack">
                @csrf
                <input type="hidden" name="provider" value="paystack">
                
                <div class="space-y-6">
                    <!-- API Credentials Section -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Secret Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Secret Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="paystackSecretKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="secret_key" 
                                       id="paystackSecretKeyInput"
                                       value="{{ old('secret_key', env('PAYSTACK_SECRET_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Paystack secret key"
                                       oninput="validatePaystackSecretKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="togglePaystackVisibility('paystackSecretKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <div class="text-xs" id="paystackSecretKeyValidation" style="color: var(--text-secondary);"></div>
                                <div class="text-xs" id="paystackSecretKeyLength" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Format: starts with the live or test prefix. Length: ~56 characters
                            </p>
                            @error('secret_key')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Public Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Public Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="paystackPublicKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="public_key" 
                                       id="paystackPublicKeyInput"
                                       value="{{ old('public_key', env('PAYSTACK_PUBLIC_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Paystack public key"
                                       oninput="validatePaystackPublicKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="togglePaystackVisibility('paystackPublicKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <div class="text-xs" id="paystackPublicKeyValidation" style="color: var(--text-secondary);"></div>
                                <div class="text-xs" id="paystackPublicKeyLength" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Format: starts with the live or test prefix. Length: ~56 characters
                            </p>
                            @error('public_key')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Merchant ID (Optional) -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Merchant ID</span>
                                    <div class="ml-2" id="paystackMerchantIdStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="text" 
                                   name="merchant_id" 
                                   id="paystackMerchantIdInput"
                                   value="{{ old('merchant_id', env('PAYSTACK_MERCHANT_ID', '')) }}"
                                   class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Optional: Your Paystack merchant ID"
                                   oninput="validatePaystackMerchantId()">
                            <div class="mt-1">
                                <div class="text-xs" id="paystackMerchantIdValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            @error('merchant_id')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Webhook Secret (Optional) -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Webhook Secret</span>
                                    <div class="ml-2" id="paystackWebhookSecretStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="webhook_secret" 
                                       id="paystackWebhookSecretInput"
                                       value="{{ old('webhook_secret', env('PAYSTACK_WEBHOOK_SECRET', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Optional: Webhook signature verification"
                                       autocomplete="off">
                                <button type="button" 
                                        onclick="togglePaystackVisibility('paystackWebhookSecretInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="paystackWebhookSecretValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            @error('webhook_secret')
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
                            <p style="color: var(--text-secondary);">Add the following URL to your Paystack Dashboard → Settings → Webhooks:</p>
                            <div class="flex items-center justify-between rounded-lg p-2 font-mono text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <code id="paystackWebhookUrl" style="color: var(--text-primary);">{{ url('/api/payments/paystack/webhook') }}</code>
                                <button type="button" onclick="copyPaystackWebhookUrl()" class="ml-2 px-2 py-1 rounded hover:opacity-80" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(255, 193, 7, 0.1);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: #ffc107;"></i>
                                    <p class="text-xs" style="color: var(--text-primary);">
                                        <strong>Important:</strong> Webhooks are essential for automatic payment confirmation. 
                                        Set the webhook URL above in your Paystack dashboard.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Information Panel -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(var(--bg-secondary-rgb), 0.5); border-color: var(--border-color);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2" style="color: #007bff;"></i>
                            Key Information
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Secret Key</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Used for server-side API calls</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Public Key</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Used for client-side integration</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #007bff;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Test Keys</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Use test mode keys from your Paystack dashboard</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #007bff;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Live Keys</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Use live mode keys when you're ready to accept real payments</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 p-2 rounded" style="background-color: rgba(220, 53, 69, 0.1);">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: #dc3545;"></i>
                                <span class="text-xs font-medium" style="color: #dc3545;">Never share your Secret Key publicly</span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Toggle -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Provider Status
                            </label>
                            <div class="space-y-3">
                                <label class="flex cursor-pointer items-center">
                                    <div class="relative">
                                        <input type="checkbox" 
                                               name="enabled" 
                                               value="1"
                                               id="paystackEnabledToggle"
                                               class="sr-only"
                                               {{ old('enabled', env('PAYSTACK_ENABLED', false) ? 'checked' : '') }}
                                               onchange="onPaystackEnabledChange()">
                                        <div class="toggle-bg block h-8 w-14 rounded-full transition-colors"></div>
                                        <div class="toggle-dot absolute left-1 top-1 h-6 w-6 rounded-full transition-all transform"></div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="font-medium" style="color: var(--text-primary);" id="paystackEnabledStatusLabel">Enabled</div>
                                        <div class="text-xs" id="paystackEnabledStatusText" style="color: var(--text-secondary);">
                                            {{ env('PAYSTACK_ENABLED', false) ? 'Paystack is active for payments' : 'Paystack is disabled' }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Summary -->
                    <div id="paystackConfigSummary" class="hidden rounded-lg border p-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">Configuration Summary</h4>
                            <button type="button" onclick="hidePaystackConfigSummary()" class="hover:opacity-70" style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Secret Key:</div>
                                <div class="font-medium" id="paystackSummarySecretKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Public Key:</div>
                                <div class="font-medium" id="paystackSummaryPublicKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Merchant ID:</div>
                                <div class="font-medium" id="paystackSummaryMerchantId" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Key Type:</div>
                                <div class="font-medium" id="paystackSummaryKeyType" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Status:</div>
                                <div class="font-medium" id="paystackSummaryStatus" style="color: var(--text-primary);">Not set</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                        <div class="flex space-x-2">
                            <button type="button" 
                                    onclick="showPaystackConfigSummary()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: #007bff;">
                                <i class="fas fa-info-circle mr-1"></i>
                                Summary
                            </button>
                            <button type="button" 
                                    onclick="clearPaystackForm()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: var(--text-secondary);">
                                <i class="fas fa-undo mr-1"></i>
                                Clear
                            </button>
                        </div>
                        <button type="submit" 
                                id="paystackSubmitBtn"
                                class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                                style="background-color: #007bff;">
                            <i class="fas fa-save mr-2"></i>
                            Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Test Results -->
    <div id="paystackConnectionResult" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-plug mr-3 text-lg" style="color: #007bff;"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Connection Test Results</h3>
                </div>
                <button onclick="closePaystackConnectionResult()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="paystackConnectionResultContent"></div>
        </div>
    </div>

    <!-- Fee Information -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(40, 167, 69, 0.05));">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Paystack Fees (Ghana)</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Mobile Money</span>
                        <span style="color: #28a745;">1.95% + GHS 0.50</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Card Payments</span>
                        <span style="color: #28a745;">1.95% + GHS 0.50</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Bank Transfer</span>
                        <span style="color: #28a745;">1.95% + GHS 0.50</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Settlement Time</span>
                        <span style="color: #007bff;">Next business day (T+1)</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium" style="color: var(--text-primary);">Minimum Payout</span>
                        <span style="color: var(--text-primary);">GHS 50 to bank</span>
                    </div>
                </div>
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(0, 123, 255, 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-calculator mr-2 mt-0.5" style="color: #007bff;"></i>
                        <div>
                            <p class="font-medium text-sm" style="color: #007bff;">Example Calculation (GHS 100)</p>
                            <p class="mt-1 text-xs" style="color: var(--text-primary);">Fee: 1.95% (GHS 1.95) + GHS 0.50 = GHS 2.45 | You receive: GHS 97.55</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for Paystack -->
<script>
// State Management
let paystackCurrentState = {
    connection: 'checking',
    configuration: 'checking',
    environment: 'unknown',
    providerStatus: 'checking'
};

let paystackSubmittedConfig = @json(session('submitted_config', null));
let paystackIsPolling = false;
let paystackPollInterval = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializePaystackInterface();
    loadPaystackInitialStatus();
    
    if (paystackSubmittedConfig && paystackSubmittedConfig.provider === 'paystack') {
        applyPaystackSubmittedConfig(paystackSubmittedConfig);
        startPaystackStatusPolling();
    }
    
    setupPaystackFormValidation();
    initPaystackToggleSwitch();
    initPaystackStatusCards();
});

// Initialize status cards
function initPaystackStatusCards() {
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

// Initialize Paystack interface
function initializePaystackInterface() {
    const toggle = document.getElementById('paystackEnabledToggle');
    const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updatePaystackToggleState(toggle.checked, toggleDot, toggleBg);
    
    toggle.addEventListener('change', function() {
        updatePaystackToggleState(this.checked, toggleDot, toggleBg);
        onPaystackEnabledChange();
    });
    
    // ✅ FORM SUBMISSION IS HANDLED BY THE MAIN PROVIDER FORM HANDLER
    // The form has class="provider-form" and data-provider="paystack"
    
    validatePaystackSecretKey();
    validatePaystackPublicKey();
    validatePaystackMerchantId();
    onPaystackEnabledChange();
}

// Update toggle switch state
function updatePaystackToggleState(isChecked, toggleDot, toggleBg) {
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
function initPaystackToggleSwitch() {
    const toggle = document.getElementById('paystackEnabledToggle');
    const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    updatePaystackToggleState(toggle.checked, toggleDot, toggleBg);
}

// Load initial status from server
function loadPaystackInitialStatus() {
    showPaystackLoadingState();
    fetchPaystackImmediateStatus()
        .then(status => {
            paystackCurrentState = status;
            updatePaystackStatusDisplay(status);
            updatePaystackFormBasedOnStatus(status);
            hidePaystackLoadingState();
        })
        .catch(error => {
            console.error('Failed to load Paystack initial status:', error);
            fallbackToPaystackFormStatus();
            hidePaystackLoadingState();
        });
}

// Show loading state
function showPaystackLoadingState() {
    const cards = ['paystackConnectionCard', 'paystackConfigCard', 'paystackEnvironmentCard', 'paystackProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) {
            card.classList.add('loading');
        }
    });
}

// Hide loading state
function hidePaystackLoadingState() {
    const cards = ['paystackConnectionCard', 'paystackConfigCard', 'paystackEnvironmentCard', 'paystackProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) {
            card.classList.remove('loading');
        }
    });
}

// Fetch immediate status from server
function fetchPaystackImmediateStatus() {
    return fetch('{{ route("admin.payment-providers.immediate-status") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response failed');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.data.paystack) {
            return data.data.paystack;
        }
        throw new Error('Invalid response format');
    });
}

// Update status display
function updatePaystackStatusDisplay(status) {
    updatePaystackConnectionStatus(status);
    updatePaystackConfigurationStatus(status);
    updatePaystackEnvironmentStatus(status);
    updatePaystackProviderStatus(status);
    updatePaystackFormStatusBadge(status);
    updatePaystackTestButtonState(status);
    updatePaystackProviderToggleButton(status);
}

// Update connection status
function updatePaystackConnectionStatus(status) {
    const icon = document.getElementById('paystackConnectionStatusIcon');
    const text = document.getElementById('paystackConnectionStatusText');
    const details = document.getElementById('paystackConnectionStatusDetails');
    const extraInfo = document.getElementById('paystackConnectionExtraInfo');
    const card = document.getElementById('paystackConnectionCard');
    
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
        extraInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Configure API keys';
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
function updatePaystackConfigurationStatus(status) {
    const icon = document.getElementById('paystackConfigStatusIcon');
    const text = document.getElementById('paystackConfigStatusText');
    const details = document.getElementById('paystackConfigStatusDetails');
    const extraInfo = document.getElementById('paystackConfigExtraInfo');
    const card = document.getElementById('paystackConfigCard');
    
    if (status.configured) {
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Complete</span>';
        details.textContent = 'All required fields configured';
        details.style.color = '#28a745';
        
        const fields = [];
        if (status.secret_key_configured) fields.push('Secret Key');
        if (status.public_key_configured) fields.push('Public Key');
        
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
function updatePaystackEnvironmentStatus(status) {
    const icon = document.getElementById('paystackEnvironmentStatusIcon');
    const text = document.getElementById('paystackEnvironmentStatusText');
    const details = document.getElementById('paystackEnvironmentStatusDetails');
    const extraInfo = document.getElementById('paystackEnvironmentExtraInfo');
    const card = document.getElementById('paystackEnvironmentCard');
    
    const secretKey = document.getElementById('paystackSecretKeyInput').value;
    const publicKey = document.getElementById('paystackPublicKeyInput').value;
    
    if (secretKey.startsWith('sk_live_') || publicKey.startsWith('pk_live_')) {
        icon.innerHTML = '<i class="fas fa-globe-africa"></i>';
        icon.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
        icon.style.color = '#dc3545';
        text.innerHTML = '<span style="color: #dc3545;">Production</span>';
        details.textContent = 'Live payments enabled';
        details.style.color = '#dc3545';
        extraInfo.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Real transactions - Test carefully first';
        extraInfo.style.color = '#dc3545';
        card.style.borderLeft = '4px solid #dc3545';
    } else if (secretKey.startsWith('sk_test_') || publicKey.startsWith('pk_test_')) {
        icon.innerHTML = '<i class="fas fa-flask"></i>';
        icon.style.backgroundColor = 'rgba(0, 123, 255, 0.2)';
        icon.style.color = '#007bff';
        text.innerHTML = '<span style="color: #007bff;">Test</span>';
        details.textContent = 'Test mode active';
        details.style.color = '#007bff';
        extraInfo.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Use test cards: 4242 4242 4242 4242';
        extraInfo.style.color = '#007bff';
        card.style.borderLeft = '4px solid #007bff';
    } else {
        icon.innerHTML = '<i class="fas fa-question-circle"></i>';
        icon.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
        icon.style.color = '#ffc107';
        text.innerHTML = '<span style="color: #ffc107;">Unknown</span>';
        details.textContent = 'Configure API keys';
        details.style.color = '#ffc107';
        extraInfo.innerHTML = '<i class="fas fa-info-circle mr-1"></i> Set valid API keys';
        extraInfo.style.color = '#ffc107';
        card.style.borderLeft = '4px solid #ffc107';
    }
}

// Update provider status
function updatePaystackProviderStatus(status) {
    const icon = document.getElementById('paystackProviderStatusIcon');
    const text = document.getElementById('paystackProviderStatusText');
    const details = document.getElementById('paystackProviderStatusDetails');
    const card = document.getElementById('paystackProviderStatusCard');
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
function updatePaystackProviderToggleButton(status) {
    const button = document.getElementById('paystackToggleProviderBtn');
    const buttonText = document.getElementById('paystackToggleBtnText');
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
function togglePaystackProviderStatus() {
    const enabledToggle = document.getElementById('paystackEnabledToggle');
    const currentState = enabledToggle.checked;
    
    enabledToggle.checked = !currentState;
    
    const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    updatePaystackToggleState(!currentState, toggleDot, toggleBg);
    
    onPaystackEnabledChange();
    
    const action = currentState ? 'disabled' : 'enabled';
    showPaystackToast(`Paystack will be ${action} when you save the configuration`, 'info');
}

// Update form status badge
function updatePaystackFormStatusBadge(status) {
    const badge = document.getElementById('paystackFormStatusBadge');
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
function updatePaystackTestButtonState(status) {
    const testBtn = document.getElementById('paystackTestConnectionBtn');
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

// Apply submitted configuration
function applyPaystackSubmittedConfig(config) {
    const enabledToggle = document.getElementById('paystackEnabledToggle');
    
    if (enabledToggle && config.enabled !== undefined) {
        enabledToggle.checked = config.enabled;
        const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updatePaystackToggleState(config.enabled, toggleDot, toggleBg);
    }
    
    onPaystackEnabledChange();
}

// Start status polling
function startPaystackStatusPolling() {
    if (paystackIsPolling) return;
    
    paystackIsPolling = true;
    let pollCount = 0;
    const maxPolls = 60;
    
    paystackPollInterval = setInterval(() => {
        pollCount++;
        
        if (pollCount >= maxPolls) {
            stopPaystackStatusPolling();
            showPaystackToast('Status update timeout. Refresh the page to check current status.', 'warning');
            return;
        }
        
        fetchPaystackImmediateStatus()
            .then(status => {
                paystackCurrentState = status;
                updatePaystackStatusDisplay(status);
                
                if (status.source !== 'cache_expected') {
                    stopPaystackStatusPolling();
                    showPaystackToast('Configuration update completed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Paystack polling error:', error);
            });
    }, 1000);
}

// Stop status polling
function stopPaystackStatusPolling() {
    if (paystackPollInterval) {
        clearInterval(paystackPollInterval);
        paystackPollInterval = null;
    }
    paystackIsPolling = false;
}

// Validate form
function validatePaystackForm() {
    const secretKeyValid = validatePaystackSecretKey();
    const publicKeyValid = validatePaystackPublicKey();
    return secretKeyValid && publicKeyValid;
}

// Test Paystack connection
function testPaystackConnection() {
    const testBtn = document.getElementById('paystackTestConnectionBtn');
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
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(response => response.json())
    .then(data => {
        showPaystackConnectionResult(data.success, data.message, data.details);
        setTimeout(loadPaystackInitialStatus, 1000);
    })
    .catch(error => {
        showPaystackConnectionResult(false, 'Connection test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

// Reset Paystack configuration
function resetPaystackConfiguration() {
    if (!confirm('⚠️ WARNING: This will clear all Paystack API credentials and disable the provider. Continue?')) {
        return;
    }
    
    fetch('{{ route("admin.payment-providers.reset") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showPaystackToast('Configuration reset successfully', 'success');
            clearPaystackForm();
            setTimeout(loadPaystackInitialStatus, 1000);
        } else {
            showPaystackToast('Reset failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showPaystackToast('Reset failed: ' + error.message, 'error');
    });
}

// Verify environment
function verifyPaystackEnvironment() {
    const secretKey = document.getElementById('paystackSecretKeyInput').value;
    const publicKey = document.getElementById('paystackPublicKeyInput').value;
    
    if (!secretKey || !publicKey) {
        showPaystackToast('Please enter both Secret and Public keys first', 'warning');
        return;
    }
    
    fetch('{{ route("admin.payment-providers.verify-environment") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = data.is_production ? 
                '🔴 PRODUCTION environment detected - Real payments will be processed' : 
                '🔵 TEST environment detected - Use test cards';
            showPaystackToast(message, data.is_production ? 'warning' : 'success');
            
            const status = paystackCurrentState;
            status.environment = data.is_production ? 'production' : 'test';
            updatePaystackEnvironmentStatus(status);
        } else {
            showPaystackToast('Environment verification failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showPaystackToast('Verification failed: ' + error.message, 'error');
    });
}

// Show connection result
function showPaystackConnectionResult(success, message, details = null) {
    const resultDiv = document.getElementById('paystackConnectionResult');
    const contentDiv = document.getElementById('paystackConnectionResultContent');
    
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
                ${success ? '<div class="mt-3 p-2 rounded text-sm" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;"><i class="fas fa-check-circle mr-1"></i> Your Paystack integration is properly configured and ready to accept payments.</div>' : ''}
            </div>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
}

// Close connection result
function closePaystackConnectionResult() {
    document.getElementById('paystackConnectionResult').classList.add('hidden');
}

// Show config summary
function showPaystackConfigSummary() {
    const secretKey = document.getElementById('paystackSecretKeyInput').value;
    const publicKey = document.getElementById('paystackPublicKeyInput').value;
    const merchantId = document.getElementById('paystackMerchantIdInput').value;
    const enabled = document.getElementById('paystackEnabledToggle').checked;
    
    document.getElementById('paystackSummarySecretKey').textContent = secretKey ? 
        '••••••••' + secretKey.slice(-4) : 
        'Not set';
    document.getElementById('paystackSummaryPublicKey').textContent = publicKey ? 
        '••••••••' + publicKey.slice(-4) : 
        'Not set';
    document.getElementById('paystackSummaryMerchantId').textContent = merchantId || 'Not set';
    
    let keyType = 'Unknown';
    if (secretKey.startsWith('sk_live_') || publicKey.startsWith('pk_live_')) {
        keyType = 'Production (Live)';
    } else if (secretKey.startsWith('sk_test_') || publicKey.startsWith('pk_test_')) {
        keyType = 'Test (Sandbox)';
    }
    document.getElementById('paystackSummaryKeyType').textContent = keyType;
    
    document.getElementById('paystackSummaryStatus').textContent = enabled ? 'Enabled' : 'Disabled';
    
    document.getElementById('paystackConfigSummary').classList.remove('hidden');
}

// Hide config summary
function hidePaystackConfigSummary() {
    document.getElementById('paystackConfigSummary').classList.add('hidden');
}

// Clear form
function clearPaystackForm() {
    if (confirm('Clear all form fields?')) {
        document.getElementById('paystackSecretKeyInput').value = '';
        document.getElementById('paystackPublicKeyInput').value = '';
        document.getElementById('paystackMerchantIdInput').value = '';
        document.getElementById('paystackWebhookSecretInput').value = '';
        document.getElementById('paystackEnabledToggle').checked = false;
        
        const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updatePaystackToggleState(false, toggleDot, toggleBg);
        
        onPaystackEnabledChange();
        validatePaystackSecretKey();
        validatePaystackPublicKey();
        
        showPaystackToast('Form cleared', 'info');
    }
}

// Toggle visibility
function togglePaystackVisibility(inputId) {
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

// Validate Secret Key
function validatePaystackSecretKey() {
    const input = document.getElementById('paystackSecretKeyInput');
    const validation = document.getElementById('paystackSecretKeyValidation');
    const lengthDisplay = document.getElementById('paystackSecretKeyLength');
    const icon = document.getElementById('paystackSecretKeyStatusIcon');
    
    const value = input.value.trim();
    const length = value.length;
    
    lengthDisplay.textContent = length > 0 ? `${length} chars` : '';
    
    if (!value) {
        validation.textContent = 'Secret Key is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (!value.startsWith('sk_live_') && !value.startsWith('sk_test_')) {
        validation.textContent = 'Key must start with sk_live_ or sk_test_';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (length < 40) {
        validation.textContent = 'Key appears too short (should be ~56 chars)';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (value.startsWith('sk_live_')) {
        validation.textContent = '✓ Live Secret Key (production)';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #dc3545;"></i>';
    } else if (value.startsWith('sk_test_')) {
        validation.textContent = '✓ Test Secret Key (sandbox)';
        validation.style.color = '#007bff';
        icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #007bff;"></i>';
    }
    
    return true;
}

// Validate Public Key
function validatePaystackPublicKey() {
    const input = document.getElementById('paystackPublicKeyInput');
    const validation = document.getElementById('paystackPublicKeyValidation');
    const lengthDisplay = document.getElementById('paystackPublicKeyLength');
    const icon = document.getElementById('paystackPublicKeyStatusIcon');
    
    const value = input.value.trim();
    const length = value.length;
    
    lengthDisplay.textContent = length > 0 ? `${length} chars` : '';
    
    if (!value) {
        validation.textContent = 'Public Key is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (!value.startsWith('pk_live_') && !value.startsWith('pk_test_')) {
        validation.textContent = 'Key must start with pk_live_ or pk_test_';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (length < 40) {
        validation.textContent = 'Key appears too short (should be ~56 chars)';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (value.startsWith('pk_live_')) {
        validation.textContent = '✓ Live Public Key (production)';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #dc3545;"></i>';
    } else if (value.startsWith('pk_test_')) {
        validation.textContent = '✓ Test Public Key (sandbox)';
        validation.style.color = '#007bff';
        icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #007bff;"></i>';
    }
    
    return true;
}

// Validate Merchant ID
function validatePaystackMerchantId() {
    const input = document.getElementById('paystackMerchantIdInput');
    const validation = document.getElementById('paystackMerchantIdValidation');
    const icon = document.getElementById('paystackMerchantIdStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Optional - used for multi-merchant setups';
        validation.style.color = 'var(--text-secondary)';
        icon.innerHTML = '<i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>';
        return true;
    }
    
    if (value.length > 0 && value.length < 20) {
        validation.textContent = '✓ Merchant ID configured';
        validation.style.color = '#28a745';
        icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
        return true;
    }
    
    return true;
}

// On enabled change
function onPaystackEnabledChange() {
    const enabled = document.getElementById('paystackEnabledToggle').checked;
    const statusText = document.getElementById('paystackEnabledStatusText');
    const label = document.getElementById('paystackEnabledStatusLabel');
    
    if (enabled) {
        statusText.textContent = 'Paystack will be enabled for payment processing';
        label.textContent = 'Enabled';
    } else {
        statusText.textContent = 'Paystack will be disabled';
        label.textContent = 'Disabled';
    }
}

// Set up form validation
function setupPaystackFormValidation() {
    const form = document.getElementById('paystackConfigForm');
    const submitBtn = document.getElementById('paystackSubmitBtn');
    
    form.addEventListener('input', () => {
        const secretKeyValid = validatePaystackSecretKey();
        const publicKeyValid = validatePaystackPublicKey();
        submitBtn.disabled = !(secretKeyValid && publicKeyValid);
    });
}

// Copy webhook URL
function copyPaystackWebhookUrl() {
    const urlElement = document.getElementById('paystackWebhookUrl');
    const url = urlElement.textContent;
    
    navigator.clipboard.writeText(url).then(() => {
        showPaystackToast('Webhook URL copied to clipboard', 'success');
    }).catch(() => {
        showPaystackToast('Failed to copy URL', 'error');
    });
}

// Show toast notification
function showPaystackToast(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#007bff'
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
function forcePaystackStatusRefresh() {
    loadPaystackInitialStatus();
    showPaystackToast('Status refreshed successfully', 'success');
}

// Fallback to form status
function fallbackToPaystackFormStatus() {
    const secretKey = document.getElementById('paystackSecretKeyInput').value.trim();
    const publicKey = document.getElementById('paystackPublicKeyInput').value.trim();
    const enabled = document.getElementById('paystackEnabledToggle').checked;
    
    const fallbackStatus = {
        enabled: enabled,
        configured: !!(secretKey && publicKey),
        source: 'fallback'
    };
    
    updatePaystackStatusDisplay(fallbackStatus);
}

// Update form based on status
function updatePaystackFormBasedOnStatus(status) {
    if (status.source === 'cache_expected' || status.source === 'cache_actual') {
        return;
    }
    
    const enabledToggle = document.getElementById('paystackEnabledToggle');
    if (enabledToggle.checked !== status.enabled) {
        enabledToggle.checked = status.enabled;
        const toggleDot = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#paystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updatePaystackToggleState(status.enabled, toggleDot, toggleBg);
        onPaystackEnabledChange();
    }
}

// Retry failed update
function retryPaystackUpdate() {
    fetch('{{ route("admin.payment-providers.retry-update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showPaystackToast('Update retry initiated', 'success');
            startPaystackStatusPolling();
        } else {
            showPaystackToast(data.message || 'Retry failed', 'error');
        }
    })
    .catch(error => {
        showPaystackToast('Retry failed: ' + error.message, 'error');
    });
}

// View error details
function viewErrorDetails() {
    showPaystackToast('Error details functionality would show detailed logs', 'info');
}
</script>

<style>
/* Paystack-specific styles */
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
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

input:focus, select:focus {
    outline: none;
    border-color: #007bff !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
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