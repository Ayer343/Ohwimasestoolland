<!-- ============================================ -->
{{-- DEVELOPER PAYSTACK CONFIGURATION --}}
{{-- ============================================ --}}
<div class="space-y-6 animate-fadeInUp">
    
    <!-- ============================================ -->
    {{-- DEVELOPER ACCESS BANNER --}}
    {{-- ============================================ --}}
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" 
         style="border-color: rgba(var(--info-rgb), 0.3); background: linear-gradient(135deg, rgba(var(--info-rgb), 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-12 w-12 items-center justify-center rounded-full" 
                     style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.2), rgba(118, 75, 162, 0.2));">
                    <i class="fas fa-credit-card text-xl" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--info);">
                    💳 Your Paystack Credentials
                </h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Configure your <strong>OWN Paystack credentials</strong> to bill admins for app usage.
                    <span class="block mt-2 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> 
                        <span class="px-2 py-0.5 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            🔑 Your Keys
                        </span> 
                        - These are separate from system payment credentials
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- SUCCESS/ERROR MESSAGES --}}
    {{-- ============================================ --}}
    @if(session('success') && str_contains(session('success'), 'Paystack'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" 
         style="border-color: rgba(var(--success-rgb), 0.3); background: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--success-rgb), 0.2);">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--success);">✅ Success!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" 
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('error') && str_contains(session('error'), 'Paystack'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" 
         style="border-color: rgba(var(--danger-rgb), 0.3); background: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--danger-rgb), 0.2);">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--danger);">❌ Error!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" 
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    {{-- DEVELOPER PAYSTACK STATUS CARDS --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Your Connection Status -->
        <div class="card overflow-hidden status-card" id="devPaystackConnectionCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="devPaystackConnectionStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon"
                         style="background-color: rgba(var(--text-secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-plug"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Your Connection</div>
                    <div class="text-sm font-semibold mt-1" id="devPaystackConnectionStatusText" style="color: var(--text-secondary);">Checking...</div>
                    <div class="text-xs mt-1" id="devPaystackConnectionStatusDetails" style="color: var(--text-secondary);">Initialize...</div>
                </div>
            </div>
        </div>

        <!-- Your Configuration Status -->
        <div class="card overflow-hidden status-card" id="devPaystackConfigCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="devPaystackConfigStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon"
                         style="background-color: rgba(var(--text-secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Your Config</div>
                    <div class="text-sm font-semibold mt-1" id="devPaystackConfigStatusText" style="color: var(--text-secondary);">Checking...</div>
                    <div class="text-xs mt-1" id="devPaystackConfigStatusDetails" style="color: var(--text-secondary);">Validate...</div>
                </div>
            </div>
        </div>

        <!-- Environment Status -->
        <div class="card overflow-hidden status-card" id="devPaystackEnvironmentCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="devPaystackEnvironmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon"
                         style="background-color: rgba(var(--text-secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Environment</div>
                    <div class="text-sm font-semibold mt-1" id="devPaystackEnvironmentStatusText" style="color: var(--text-secondary);">Checking...</div>
                    <div class="text-xs mt-1" id="devPaystackEnvironmentStatusDetails" style="color: var(--text-secondary);">Detecting...</div>
                </div>
            </div>
        </div>

        <!-- Billing Status -->
        <div class="card overflow-hidden status-card" id="devPaystackBillingCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="devPaystackBillingStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon"
                         style="background-color: rgba(var(--text-secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Billing Status</div>
                    <div class="text-sm font-semibold mt-1" id="devPaystackBillingStatusText" style="color: var(--text-secondary);">Checking...</div>
                    <div class="text-xs mt-1" id="devPaystackBillingStatusDetails" style="color: var(--text-secondary);">Checking...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- QUICK ACTIONS --}}
    {{-- ============================================ --}}
    <div class="flex flex-wrap gap-2">
        <button onclick="devTestPaystackConnection()" 
                id="devPaystackTestBtn" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                style="background-color: var(--info); color: white;">
            <i class="fas fa-vial mr-2"></i>
            Test Your Credentials
        </button>
        
        <button onclick="devVerifyPaystackEnvironment()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.4);">
            <i class="fas fa-check-circle mr-2"></i>
            Verify Environment
        </button>
        
        <button onclick="devRefreshPaystackStatus()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Status
        </button>
        
        <button onclick="devViewPaystackDebug()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.4);">
            <i class="fas fa-bug mr-2"></i>
            Debug Info
        </button>
    </div>

    <!-- ============================================ -->
    {{-- YOUR CREDENTIALS FORM (EDITABLE) --}}
    {{-- ============================================ --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--info-rgb), 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--info);"></i>
                        Your Paystack Credentials
                    </h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                        Enter your own Paystack API credentials to bill admins for app usage
                    </p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" 
                          style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);" id="devPaystackFormBadge">
                        <span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: var(--info);"></span>
                        Your Credentials
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="devPaystackConfigForm" method="POST" action="{{ route('developer.payment-providers.configure') }}" data-provider="paystack">
                @csrf
                <input type="hidden" name="provider" value="paystack">
                
                <div class="space-y-6">
                    <!-- API Credentials -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Secret Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Your Secret Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="devPaystackSecretKeyStatusIcon">
                                        <i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="secret_key" 
                                       id="devPaystackSecretKeyInput"
                                       value="{{ old('secret_key', env('DEVELOPER_PAYSTACK_SECRET_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       oninput="devValidateSecretKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="devToggleVisibility('devPaystackSecretKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <div class="text-xs" id="devPaystackSecretKeyValidation" style="color: var(--text-secondary);"></div>
                                <div class="text-xs" id="devPaystackSecretKeyLength" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Format: sk_live_XXXX (live) or sk_test_XXXX (test)
                            </p>
                        </div>

                        <!-- Public Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Your Public Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="devPaystackPublicKeyStatusIcon">
                                        <i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="public_key" 
                                       id="devPaystackPublicKeyInput"
                                       value="{{ old('public_key', env('DEVELOPER_PAYSTACK_PUBLIC_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       oninput="devValidatePublicKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="devToggleVisibility('devPaystackPublicKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <div class="text-xs" id="devPaystackPublicKeyValidation" style="color: var(--text-secondary);"></div>
                                <div class="text-xs" id="devPaystackPublicKeyLength" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> 
                                Format: pk_live_XXXX (live) or pk_test_XXXX (test)
                            </p>
                        </div>
                    </div>

                    <!-- Webhook Configuration -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-color: rgba(var(--info-rgb), 0.2);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-webhook mr-2" style="color: var(--info);"></i>
                            Webhook URL
                        </h6>
                        <div class="space-y-3 text-sm">
                            <p style="color: var(--text-secondary);">Add this URL to your Paystack Dashboard → Settings → Webhooks:</p>
                            <div class="flex items-center justify-between rounded-lg p-2 font-mono text-xs" 
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <code id="devPaystackWebhookUrl" style="color: var(--text-primary); word-break: break-all;">
                                    {{ url('/api/payments/paystack/webhook') }}
                                </code>
                                <button type="button" onclick="devCopyPaystackWebhookUrl()" 
                                        class="ml-2 px-2 py-1 rounded hover:opacity-80 flex-shrink-0"
                                        style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                                    <p class="text-xs" style="color: var(--text-primary);">
                                        <strong>Important:</strong> Webhooks are essential for automatic payment confirmation.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Status Toggle -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Billing Status
                            </label>
                            <div class="space-y-3">
                                <label class="flex cursor-pointer items-center">
                                    <div class="relative">
                                        <input type="checkbox" 
                                               name="enabled" 
                                               value="1"
                                               id="devPaystackEnabledToggle"
                                               class="sr-only"
                                               {{ old('enabled', env('DEVELOPER_PAYSTACK_ENABLED', false)) ? 'checked' : '' }}
                                               onchange="devOnEnabledChange()">
                                        <div class="toggle-bg block h-8 w-14 rounded-full transition-colors"></div>
                                        <div class="toggle-dot absolute left-1 top-1 h-6 w-6 rounded-full transition-all transform"></div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="font-medium" style="color: var(--text-primary);" id="devPaystackEnabledLabel">
                                            {{ env('DEVELOPER_PAYSTACK_ENABLED', false) ? 'Enabled' : 'Disabled' }}
                                        </div>
                                        <div class="text-xs" id="devPaystackEnabledText" style="color: var(--text-secondary);">
                                            {{ env('DEVELOPER_PAYSTACK_ENABLED', false) ? 'You can bill admins' : 'Billing is disabled' }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                        <div class="flex space-x-2">
                            <button type="button" 
                                    onclick="devClearPaystackForm()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center px-3 py-1.5 rounded"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-undo mr-1"></i>
                                Clear
                            </button>
                        </div>
                        <button type="submit" 
                                id="devPaystackSubmitBtn"
                                class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                                style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                            <i class="fas fa-save mr-2"></i>
                            Save Your Credentials
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- BILL ADMIN SECTION --}}
    {{-- ============================================ --}}
    @php
        $isConfigured = !empty(env('DEVELOPER_PAYSTACK_SECRET_KEY')) && !empty(env('DEVELOPER_PAYSTACK_PUBLIC_KEY'));
    @endphp
    
    <div class="card overflow-hidden" id="devBillingSection">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--success-rgb), 0.05));">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-receipt mr-2" style="color: var(--success);"></i>
                Bill Admin for App Usage
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Use your configured credentials to bill the admin
            </p>
        </div>
        <div class="p-6">
            @if(!$isConfigured)
            <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                <p class="mt-2 font-medium" style="color: var(--text-primary);">Please configure your credentials first</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Enter your Paystack API keys above to enable billing</p>
            </div>
            @else
            <form id="devBillingForm" method="POST" action="{{ route('developer.payment-providers.bill-admin') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Amount (GHS)
                            <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                        </label>
                        <input type="number" 
                               name="amount" 
                               id="devBillingAmount"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="100.00"
                               step="0.01"
                               min="1"
                               required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Admin Email
                            <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                        </label>
                        <input type="email" 
                               name="email" 
                               id="devBillingEmail"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="admin@example.com"
                               required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                            Description
                            <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                        </label>
                        <input type="text" 
                               name="description" 
                               id="devBillingDescription"
                               class="w-full rounded-lg border px-4 py-2.5 transition-colors focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="App usage fee - {{ date('F Y') }}"
                               required>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" 
                            id="devBillingSubmitBtn"
                            class="rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                            style="background: linear-gradient(135deg, var(--success), var(--info)); color: white;">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Invoice
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>

    <!-- ============================================ -->
    {{-- DEBUG PANEL --}}
    {{-- ============================================ --}}
    <div id="devPaystackDebugPanel" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-bug mr-3 text-lg" style="color: var(--warning);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Debug Information</h3>
                </div>
                <button onclick="devClosePaystackDebugPanel()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Your Secret Key:</span>
                    <span style="color: var(--text-primary);" id="devDebugSecretKey">Not set</span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Your Public Key:</span>
                    <span style="color: var(--text-primary);" id="devDebugPublicKey">Not set</span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Billing Enabled:</span>
                    <span style="color: var(--text-primary);" id="devDebugBillingEnabled">No</span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Environment:</span>
                    <span style="color: var(--text-primary);" id="devDebugEnvironment">Unknown</span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">System Paystack Enabled:</span>
                    <span style="color: var(--text-primary);" id="devDebugSystemEnabled">{{ env('PAYSTACK_ENABLED', false) ? 'Yes' : 'No' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Last Updated:</span>
                    <span style="color: var(--text-primary);" id="devDebugTimestamp">{{ now()->toDateTimeString() }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    {{-- CONNECTION TEST RESULTS --}}
    {{-- ============================================ --}}
    <div id="devPaystackConnectionResult" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-vial mr-3 text-lg" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Connection Test Results</h3>
                </div>
                <button onclick="devClosePaystackConnectionResult()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="devPaystackConnectionResultContent"></div>
        </div>
    </div>
</div>

<!-- ============================================ -->
{{-- DEVELOPER PAYSTACK JAVASCRIPT --}}
{{-- ============================================ --}}
<script>
// ============================================
// DEVELOPER PAYSTACK CONTROLLER
// ============================================

let devPaystackCurrentState = {
    connection: 'checking',
    configuration: 'checking',
    environment: 'unknown',
    billingStatus: 'checking'
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    devLoadPaystackStatus();
    devSetupAutoRefresh();
    devInitToggle();
    devInitBillingForm();
});

// ============================================
// LOAD STATUS
// ============================================
function devLoadPaystackStatus() {
    devShowLoadingState();
    
    fetchJSON('{{ route("developer.payment-providers.status") }}', {
        method: 'GET'
    })
    .then(data => {
        if (data.success && data.data.paystack) {
            devPaystackCurrentState = data.data.paystack;
            devUpdateStatusDisplay(data.data.paystack);
            devUpdateDebugInfo(data.data.paystack);
        } else {
            devFallbackStatus();
        }
        devHideLoadingState();
    })
    .catch(error => {
        console.error('Failed to load Paystack status:', error);
        devFallbackStatus();
        devHideLoadingState();
    });
}

// ============================================
// UPDATE STATUS DISPLAY
// ============================================
function devUpdateStatusDisplay(status) {
    devUpdateConnectionStatus(status);
    devUpdateConfigStatus(status);
    devUpdateEnvironmentStatus(status);
    devUpdateBillingStatus(status);
}

function devUpdateConnectionStatus(status) {
    const icon = document.getElementById('devPaystackConnectionStatusIcon');
    const text = document.getElementById('devPaystackConnectionStatusText');
    const details = document.getElementById('devPaystackConnectionStatusDetails');
    const card = document.getElementById('devPaystackConnectionCard');
    
    const isConfigured = status.developer_configured || false;
    const isEnabled = status.developer_config?.enabled || false;
    
    if (isConfigured && isEnabled) {
        icon.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
        icon.style.color = 'var(--success)';
        text.innerHTML = '<span style="color: var(--success);">Connected</span>';
        details.textContent = 'Your credentials are valid';
        details.style.color = 'var(--success)';
        card.style.borderLeft = '4px solid var(--success)';
    } else if (isConfigured && !isEnabled) {
        icon.style.backgroundColor = 'rgba(var(--warning-rgb), 0.2)';
        icon.style.color = 'var(--warning)';
        text.innerHTML = '<span style="color: var(--warning);">Disabled</span>';
        details.textContent = 'Billing is disabled';
        details.style.color = 'var(--warning)';
        card.style.borderLeft = '4px solid var(--warning)';
    } else {
        icon.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
        icon.style.color = 'var(--danger)';
        text.innerHTML = '<span style="color: var(--danger);">Not Configured</span>';
        details.textContent = 'Configure your credentials above';
        details.style.color = 'var(--danger)';
        card.style.borderLeft = '4px solid var(--danger)';
    }
}

function devUpdateConfigStatus(status) {
    const icon = document.getElementById('devPaystackConfigStatusIcon');
    const text = document.getElementById('devPaystackConfigStatusText');
    const details = document.getElementById('devPaystackConfigStatusDetails');
    const card = document.getElementById('devPaystackConfigCard');
    
    const isConfigured = status.developer_configured || false;
    
    if (isConfigured) {
        icon.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
        icon.style.color = 'var(--success)';
        text.innerHTML = '<span style="color: var(--success);">Complete</span>';
        details.textContent = 'Both keys configured';
        details.style.color = 'var(--success)';
        card.style.borderLeft = '4px solid var(--success)';
    } else {
        icon.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
        icon.style.color = 'var(--danger)';
        text.innerHTML = '<span style="color: var(--danger);">Incomplete</span>';
        details.textContent = 'Missing Secret/Public key';
        details.style.color = 'var(--danger)';
        card.style.borderLeft = '4px solid var(--danger)';
    }
}

function devUpdateEnvironmentStatus(status) {
    const icon = document.getElementById('devPaystackEnvironmentStatusIcon');
    const text = document.getElementById('devPaystackEnvironmentStatusText');
    const details = document.getElementById('devPaystackEnvironmentStatusDetails');
    const card = document.getElementById('devPaystackEnvironmentCard');
    
    const secretKey = document.getElementById('devPaystackSecretKeyInput').value;
    const publicKey = document.getElementById('devPaystackPublicKeyInput').value;
    
    if (secretKey.startsWith('sk_live_') || publicKey.startsWith('pk_live_')) {
        icon.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
        icon.style.color = 'var(--danger)';
        text.innerHTML = '<span style="color: var(--danger);">Production</span>';
        details.textContent = '🔴 Live payments will be processed';
        details.style.color = 'var(--danger)';
        card.style.borderLeft = '4px solid var(--danger)';
    } else if (secretKey.startsWith('sk_test_') || publicKey.startsWith('pk_test_')) {
        icon.style.backgroundColor = 'rgba(var(--info-rgb), 0.2)';
        icon.style.color = 'var(--info)';
        text.innerHTML = '<span style="color: var(--info);">Test</span>';
        details.textContent = '🧪 Test mode active';
        details.style.color = 'var(--info)';
        card.style.borderLeft = '4px solid var(--info)';
    } else {
        icon.style.backgroundColor = 'rgba(var(--warning-rgb), 0.2)';
        icon.style.color = 'var(--warning)';
        text.innerHTML = '<span style="color: var(--warning);">Unknown</span>';
        details.textContent = 'Configure your API keys';
        details.style.color = 'var(--warning)';
        card.style.borderLeft = '4px solid var(--warning)';
    }
}

function devUpdateBillingStatus(status) {
    const icon = document.getElementById('devPaystackBillingStatusIcon');
    const text = document.getElementById('devPaystackBillingStatusText');
    const details = document.getElementById('devPaystackBillingStatusDetails');
    const card = document.getElementById('devPaystackBillingCard');
    
    const isConfigured = status.developer_configured || false;
    const isEnabled = status.developer_config?.enabled || false;
    const canBill = status.can_bill || false;
    
    if (isConfigured && isEnabled && canBill) {
        icon.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
        icon.style.color = 'var(--success)';
        text.innerHTML = '<span style="color: var(--success);">Ready</span>';
        details.textContent = 'You can bill admins';
        details.style.color = 'var(--success)';
        card.style.borderLeft = '4px solid var(--success)';
    } else if (!canBill) {
        icon.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
        icon.style.color = 'var(--danger)';
        text.innerHTML = '<span style="color: var(--danger);">Disabled</span>';
        details.textContent = 'Billing disabled by admin';
        details.style.color = 'var(--danger)';
        card.style.borderLeft = '4px solid var(--danger)';
    } else if (isConfigured && !isEnabled) {
        icon.style.backgroundColor = 'rgba(var(--warning-rgb), 0.2)';
        icon.style.color = 'var(--warning)';
        text.innerHTML = '<span style="color: var(--warning);">Disabled</span>';
        details.textContent = 'Enable billing above';
        details.style.color = 'var(--warning)';
        card.style.borderLeft = '4px solid var(--warning)';
    } else {
        icon.style.backgroundColor = 'rgba(var(--danger-rgb), 0.2)';
        icon.style.color = 'var(--danger)';
        text.innerHTML = '<span style="color: var(--danger);">Not Ready</span>';
        details.textContent = 'Configure your credentials';
        details.style.color = 'var(--danger)';
        card.style.borderLeft = '4px solid var(--danger)';
    }
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================
function devValidateSecretKey() {
    const input = document.getElementById('devPaystackSecretKeyInput');
    const validation = document.getElementById('devPaystackSecretKeyValidation');
    const lengthDisplay = document.getElementById('devPaystackSecretKeyLength');
    const icon = document.getElementById('devPaystackSecretKeyStatusIcon');
    
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
        validation.textContent = 'Must start with sk_live_ or sk_test_';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (length < 30) {
        validation.textContent = 'Key appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = value.startsWith('sk_live_') ? '✅ Live key' : '✅ Test key';
    validation.style.color = value.startsWith('sk_live_') ? '#dc3545' : '#007bff';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

function devValidatePublicKey() {
    const input = document.getElementById('devPaystackPublicKeyInput');
    const validation = document.getElementById('devPaystackPublicKeyValidation');
    const lengthDisplay = document.getElementById('devPaystackPublicKeyLength');
    const icon = document.getElementById('devPaystackPublicKeyStatusIcon');
    
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
        validation.textContent = 'Must start with pk_live_ or pk_test_';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (length < 30) {
        validation.textContent = 'Key appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = value.startsWith('pk_live_') ? '✅ Live key' : '✅ Test key';
    validation.style.color = value.startsWith('pk_live_') ? '#dc3545' : '#007bff';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// ============================================
// TOGGLE FUNCTIONS
// ============================================
function devToggleVisibility(inputId) {
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

function devOnEnabledChange() {
    const enabled = document.getElementById('devPaystackEnabledToggle').checked;
    const label = document.getElementById('devPaystackEnabledLabel');
    const text = document.getElementById('devPaystackEnabledText');
    
    if (enabled) {
        label.textContent = 'Enabled';
        text.textContent = 'You can bill admins';
    } else {
        label.textContent = 'Disabled';
        text.textContent = 'Billing is disabled';
    }
    
    const toggleDot = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    if (enabled) {
        toggleDot.style.transform = 'translateX(1.5rem)';
        toggleBg.style.backgroundColor = '#28a745';
    } else {
        toggleDot.style.transform = 'translateX(0)';
        toggleBg.style.backgroundColor = 'var(--border-color)';
    }
}

function devInitToggle() {
    const toggle = document.getElementById('devPaystackEnabledToggle');
    if (toggle) {
        const toggleDot = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        
        if (toggle.checked) {
            toggleDot.style.transform = 'translateX(1.5rem)';
            toggleBg.style.backgroundColor = '#28a745';
        }
    }
}

// ============================================
// TEST CONNECTION
// ============================================
function devTestPaystackConnection() {
    const testBtn = document.getElementById('devPaystackTestBtn');
    const originalText = testBtn.innerHTML;
    
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Testing...';
    
    const secretKey = document.getElementById('devPaystackSecretKeyInput').value.trim();
    const publicKey = document.getElementById('devPaystackPublicKeyInput').value.trim();
    
    if (!secretKey || !publicKey) {
        devShowToast('Please enter both Secret and Public keys first', 'warning');
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
        return;
    }
    
    fetchJSON('{{ route("developer.payment-providers.test-developer-connection") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ provider: 'paystack' })
    })
    .then(data => {
        devShowConnectionResult(data.success, data.message, data.details);
        if (data.success) {
            devLoadPaystackStatus();
        }
    })
    .catch(error => {
        devShowConnectionResult(false, 'Test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

// ============================================
// CONNECTION RESULT
// ============================================
function devShowConnectionResult(success, message, details = null) {
    const resultDiv = document.getElementById('devPaystackConnectionResult');
    const contentDiv = document.getElementById('devPaystackConnectionResultContent');
    
    const icon = success ? 'fa-check-circle' : 'fa-exclamation-circle';
    const iconColor = success ? 'var(--success)' : 'var(--danger)';
    const headerColor = success ? 'var(--success)' : 'var(--danger)';
    
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
                <h4 class="text-lg font-medium" style="color: ${headerColor};">${success ? '✅ Connection Successful' : '❌ Connection Failed'}</h4>
                <p class="mt-1" style="color: var(--text-primary);">${message}</p>
                ${detailsHtml}
            </div>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
}

function devClosePaystackConnectionResult() {
    document.getElementById('devPaystackConnectionResult').classList.add('hidden');
}

// ============================================
// VERIFY ENVIRONMENT
// ============================================
function devVerifyPaystackEnvironment() {
    fetchJSON('{{ route("developer.payment-providers.environment") }}?provider=paystack', {
        method: 'GET'
    })
    .then(data => {
        if (data.success) {
            const env = data.environment;
            const isProd = env.is_production;
            const message = isProd ? 
                '🔴 PRODUCTION environment detected' : 
                '🔵 TEST environment detected';
            devShowToast(message, isProd ? 'warning' : 'success');
            
            const status = devPaystackCurrentState;
            status.environment = isProd ? 'production' : 'test';
            devUpdateEnvironmentStatus(status);
        } else {
            devShowToast('Environment verification failed', 'error');
        }
    })
    .catch(error => {
        devShowToast('Verification failed: ' + error.message, 'error');
    });
}

// ============================================
// DEBUG INFO
// ============================================
function devViewPaystackDebug() {
    const panel = document.getElementById('devPaystackDebugPanel');
    panel.classList.toggle('hidden');
    
    if (!panel.classList.contains('hidden')) {
        devLoadPaystackStatus();
        document.getElementById('devDebugTimestamp').textContent = new Date().toLocaleString();
    }
}

function devClosePaystackDebugPanel() {
    document.getElementById('devPaystackDebugPanel').classList.add('hidden');
}

function devUpdateDebugInfo(status) {
    const secretKey = document.getElementById('devPaystackSecretKeyInput').value;
    const publicKey = document.getElementById('devPaystackPublicKeyInput').value;
    
    document.getElementById('devDebugSecretKey').textContent = secretKey ? '••••••••' + secretKey.slice(-4) : 'Not set';
    document.getElementById('devDebugPublicKey').textContent = publicKey ? '••••••••' + publicKey.slice(-4) : 'Not set';
    document.getElementById('devDebugBillingEnabled').textContent = status.developer_config?.enabled ? 'Yes' : 'No';
    document.getElementById('devDebugEnvironment').textContent = status.environment || 'Unknown';
}

// ============================================
// REFRESH STATUS
// ============================================
function devRefreshPaystackStatus() {
    const btn = event?.target?.closest('button');
    const original = btn?.innerHTML || 'Refresh';
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
        btn.disabled = true;
    }
    
    devLoadPaystackStatus();
    
    setTimeout(() => {
        if (btn) {
            btn.innerHTML = original;
            btn.disabled = false;
            devShowToast('Status refreshed', 'success');
        }
    }, 1500);
}

// ============================================
// CLEAR FORM
// ============================================
function devClearPaystackForm() {
    if (!confirm('Clear all form fields?')) return;
    
    document.getElementById('devPaystackSecretKeyInput').value = '';
    document.getElementById('devPaystackPublicKeyInput').value = '';
    document.getElementById('devPaystackEnabledToggle').checked = false;
    
    const toggleDot = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#devPaystackEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    toggleDot.style.transform = 'translateX(0)';
    toggleBg.style.backgroundColor = 'var(--border-color)';
    
    document.getElementById('devPaystackEnabledLabel').textContent = 'Disabled';
    document.getElementById('devPaystackEnabledText').textContent = 'Billing is disabled';
    
    devShowToast('Form cleared', 'info');
}

// ============================================
// COPY WEBHOOK URL
// ============================================
function devCopyPaystackWebhookUrl() {
    const urlElement = document.getElementById('devPaystackWebhookUrl');
    const url = urlElement.textContent.trim();
    
    navigator.clipboard.writeText(url).then(() => {
        devShowToast('Webhook URL copied to clipboard', 'success');
    }).catch(() => {
        devShowToast('Failed to copy URL', 'error');
    });
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function devShowToast(message, type = 'info') {
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full';
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.innerHTML = `<div class="flex items-center"><i class="fas ${icons[type]} mr-2"></i><span>${message}</span></div>`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// ============================================
// BILLING FORM HANDLER
// ============================================
function devInitBillingForm() {
    const billingForm = document.getElementById('devBillingForm');
    if (billingForm) {
        billingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('devBillingSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            const formData = new FormData(this);
            
            fetchJSON(this.action, {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    devShowToast('✅ ' + data.message, 'success');
                    if (data.redirect_url) {
                        window.open(data.redirect_url, '_blank');
                    }
                    document.getElementById('devBillingAmount').value = '';
                    document.getElementById('devBillingDescription').value = '';
                } else {
                    devShowToast('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                devShowToast('❌ Error: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// ============================================
// HELPERS
// ============================================
function fetchJSON(url, options = {}) {
    options.headers = {
        ...options.headers,
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
    };
    options.credentials = 'same-origin';
    
    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            if (text.charCodeAt(0) === 0xFEFF || text.substring(0, 1) === '\uFEFF') {
                text = text.substring(1);
            }
            text = text.trim();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', text.substring(0, 200));
                throw new Error('Invalid JSON response');
            }
        });
}

function devShowLoadingState() {
    const cards = ['devPaystackConnectionCard', 'devPaystackConfigCard', 'devPaystackEnvironmentCard', 'devPaystackBillingCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.add('loading');
    });
}

function devHideLoadingState() {
    const cards = ['devPaystackConnectionCard', 'devPaystackConfigCard', 'devPaystackEnvironmentCard', 'devPaystackBillingCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.remove('loading');
    });
}

function devFallbackStatus() {
    const secretKey = document.getElementById('devPaystackSecretKeyInput').value.trim();
    const publicKey = document.getElementById('devPaystackPublicKeyInput').value.trim();
    
    const fallbackStatus = {
        developer_configured: !!(secretKey && publicKey),
        developer_config: {
            enabled: document.getElementById('devPaystackEnabledToggle')?.checked || false
        },
        can_bill: true,
        environment: secretKey.startsWith('sk_live_') ? 'production' : 'test',
        source: 'fallback'
    };
    
    devUpdateStatusDisplay(fallbackStatus);
}

function devSetupAutoRefresh() {
    setInterval(() => {
        if (!document.querySelector('.toast-notification')) {
            devLoadPaystackStatus();
        }
    }, 30000);
}
</script>

<style>
/* ============================================ */
/* DEVELOPER PAYSTACK STYLES */
/* ============================================ */

.animate-fadeInUp {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
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

.toast-notification {
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    border-radius: 0.75rem;
}

code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    word-break: break-all;
}

@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .status-card {
        margin-bottom: 0.5rem;
    }
    
    .toast-notification {
        min-width: 280px;
        max-width: 320px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        right: auto;
    }
}

@media (max-width: 480px) {
    .grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>