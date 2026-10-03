<!-- ============================================ -->
{{-- HUBTEL PROVIDER CONFIGURATION --}}
{{-- ============================================ --}}
<div class="space-y-6 animate-fadeInUp">
    <!-- State-Aware Status Banner -->
    @if(session('payment_update_status') == 'processing' && session('payment_update_provider') == 'hubtel')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(37, 99, 235, 0.3); background: linear-gradient(to right, rgba(37, 99, 235, 0.05), rgba(37, 99, 235, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(37, 99, 235, 0.2);">
                    <i class="fas fa-sync-alt fa-spin text-lg" style="color: #2563EB;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #2563EB;">Configuration Update in Progress</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Your Hubtel settings are being updated in the background. This may take a few moments.
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
    @if(session('success') && str_contains(session('success'), 'Hubtel'))
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
    @if(session('error') && str_contains(session('error'), 'Hubtel'))
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
                    <button onclick="retryHubtelUpdate()" 
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
        <div class="card overflow-hidden status-card" id="hubtelConnectionCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="hubtelConnectionStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Connection</div>
                    <div class="text-sm font-semibold mt-1" id="hubtelConnectionStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="hubtelConnectionStatusDetails">Initializing connection status...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="hubtelConnectionExtraInfo"></div>
            </div>
        </div>

        <!-- Configuration Status -->
        <div class="card overflow-hidden status-card" id="hubtelConfigCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="hubtelConfigStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Configuration</div>
                    <div class="text-sm font-semibold mt-1" id="hubtelConfigStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="hubtelConfigStatusDetails">Validating configuration...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="hubtelConfigExtraInfo"></div>
            </div>
        </div>

        <!-- Environment Status -->
        <div class="card overflow-hidden status-card" id="hubtelEnvironmentCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="hubtelEnvironmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Environment</div>
                    <div class="text-sm font-semibold mt-1" id="hubtelEnvironmentStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="hubtelEnvironmentStatusDetails">Detecting environment...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="hubtelEnvironmentExtraInfo"></div>
            </div>
        </div>

        <!-- Provider Status -->
        <div class="card overflow-hidden status-card" id="hubtelProviderStatusCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="hubtelProviderStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-power-off"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Provider Status</div>
                    <div class="text-sm font-semibold mt-1" id="hubtelProviderStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="hubtelProviderStatusDetails">Checking provider availability...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="flex space-x-2">
                    <button id="hubtelToggleProviderBtn" 
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                            onclick="toggleHubtelProviderStatus()">
                        <i class="fas fa-power-off mr-1"></i>
                        <span id="hubtelToggleBtnText">Enable</span>
                    </button>
                    <button onclick="forceHubtelStatusRefresh()" 
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80"
                            style="background-color: rgba(37, 99, 235, 0.1); color: #2563EB;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Progress Indicator -->
    <div id="hubtelLiveProgressIndicator" class="hidden overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(37, 99, 235, 0.3); background: linear-gradient(to right, rgba(37, 99, 235, 0.05), rgba(37, 99, 235, 0.1));">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-sync-alt fa-spin mr-2" style="color: #2563EB;"></i>
                <span class="font-medium" style="color: var(--text-primary);">Background Update in Progress</span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <span id="hubtelProgressStep">Initializing...</span>
            </div>
        </div>
        <div class="mb-2 h-2 w-full rounded-full" style="background-color: rgba(37, 99, 235, 0.3);">
            <div id="hubtelProgressBar" class="h-2 rounded-full transition-all duration-500" style="background-color: #2563EB; width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
            <span>Started</span>
            <span id="hubtelProgressTime">Just now</span>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="flex flex-wrap gap-2">
        <button onclick="testHubtelConnection()" 
                id="hubtelTestConnectionBtn" 
                class="btn-primary inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                style="background-color: #2563EB;">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button onclick="verifyHubtelEnvironment()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4);">
            <i class="fas fa-check-circle mr-2"></i>
            Verify Environment
        </button>
        
        <button onclick="resetHubtelConfiguration()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <button onclick="forceHubtelStatusRefresh()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Status
        </button>
    </div>

    <!-- Configuration Form -->
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(37, 99, 235, 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Hubtel Configuration</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">Configure your Hubtel API credentials for payment processing in Ghana</p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" id="hubtelFormStatusBadge">
                        <span class="mr-1.5 h-2 w-2 rounded-full"></span>
                        Loading...
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="hubtelConfigForm" class="provider-form" method="POST" action="{{ route('admin.payment-providers.configure') }}" data-provider="hubtel">
                @csrf
                <input type="hidden" name="provider" value="hubtel">
                
                <div class="space-y-6">
                    <!-- Enable/Disable Toggle -->
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Enable Hubtel</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Toggle to enable/disable Hubtel payment processing</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" id="hubtelEnabledToggle" class="sr-only peer" 
                                {{ old('enabled', env('HUBTEL_ENABLED', false)) ? 'checked' : '' }}
                                onchange="onHubtelEnabledChange()">
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
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="hubtelSandboxLabel">
                                <input type="radio" name="environment" value="sandbox" class="sr-only" id="hubtelSandboxRadio"
                                    {{ (old('environment', env('HUBTEL_ENVIRONMENT', 'sandbox')) === 'sandbox') ? 'checked' : '' }}
                                    onchange="onHubtelEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="hubtelSandboxRadioIndicator">
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
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="hubtelProductionLabel">
                                <input type="radio" name="environment" value="production" class="sr-only" id="hubtelProductionRadio"
                                    {{ (old('environment', env('HUBTEL_ENVIRONMENT', 'sandbox')) === 'production') ? 'checked' : '' }}
                                    onchange="onHubtelEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="hubtelProductionRadioIndicator">
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
                        <!-- Client ID -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Client ID</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="hubtelClientIdStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="client_id" 
                                       id="hubtelClientIdInput"
                                       value="{{ old('client_id', env('HUBTEL_CLIENT_ID', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Hubtel Client ID"
                                       oninput="validateHubtelClientId()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="toggleHubtelVisibility('hubtelClientIdInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="hubtelClientIdValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-id-card mr-1"></i> Your client ID from Hubtel dashboard
                            </p>
                            @error('client_id')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Client Secret -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Client Secret</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="hubtelClientSecretStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="client_secret" 
                                       id="hubtelClientSecretInput"
                                       value="{{ old('client_secret', env('HUBTEL_CLIENT_SECRET', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Enter your Hubtel Client Secret"
                                       oninput="validateHubtelClientSecret()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="toggleHubtelVisibility('hubtelClientSecretInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="hubtelClientSecretValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-lock mr-1"></i> Your client secret for authenticating with Hubtel
                            </p>
                            @error('client_secret')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Additional Configuration -->
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Merchant Account (Optional) -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Merchant Account</span>
                                    <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">(Optional)</span>
                                    <div class="ml-2" id="hubtelMerchantAccountStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="text" 
                                   name="merchant_account" 
                                   id="hubtelMerchantAccountInput"
                                   value="{{ old('merchant_account', env('HUBTEL_MERCHANT_ACCOUNT', '')) }}"
                                   class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Enter your Hubtel Merchant Account (optional)"
                                   oninput="validateHubtelMerchantAccount()">
                            <div class="mt-1">
                                <div class="text-xs" id="hubtelMerchantAccountValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-store mr-1"></i> Optional: Specific merchant account for your payments
                            </p>
                            @error('merchant_account')
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
                            <p style="color: var(--text-secondary);">Add the following URL to your Hubtel Dashboard → Developers → Webhooks:</p>
                            <div class="flex items-center justify-between rounded-lg p-2 font-mono text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <code id="hubtelWebhookUrl" style="color: var(--text-primary);">{{ url('/api/payments/hubtel/webhook') }}</code>
                                <button type="button" onclick="copyHubtelWebhookUrl()" class="ml-2 px-2 py-1 rounded hover:opacity-80" style="background-color: rgba(37, 99, 235, 0.1); color: #2563EB;">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(255, 193, 7, 0.1);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: #ffc107;"></i>
                                    <p class="text-xs" style="color: var(--text-primary);">
                                        <strong>Important:</strong> Webhooks are essential for automatic payment confirmation. 
                                        Configure the webhook URL above in your Hubtel dashboard and set up the <code class="px-1 rounded" style="background-color: rgba(0,0,0,0.1);">payment.received</code> event.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Information Panel -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(var(--bg-secondary-rgb), 0.5); border-color: var(--border-color);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2" style="color: #2563EB;"></i>
                            Key Information
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Client ID</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Public identifier for your integration</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Client Secret</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Used for authenticating API requests</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #2563EB;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Sandbox Credentials</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Available upon signing up - no approval needed</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #2563EB;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Production Access</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Requires business verification approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 p-2 rounded" style="background-color: rgba(220, 53, 69, 0.1);">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: #dc3545;"></i>
                                <span class="text-xs font-medium" style="color: #dc3545;">Never share your Client Secret publicly</span>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Summary -->
                    <div id="hubtelConfigSummary" class="hidden rounded-lg border p-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">Configuration Summary</h4>
                            <button type="button" onclick="hideHubtelConfigSummary()" class="hover:opacity-70" style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Client ID:</div>
                                <div class="font-medium" id="hubtelSummaryClientId" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Client Secret:</div>
                                <div class="font-medium" id="hubtelSummaryClientSecret" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Merchant Account:</div>
                                <div class="font-medium" id="hubtelSummaryMerchantAccount" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Environment:</div>
                                <div class="font-medium" id="hubtelSummaryEnvironment" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Status:</div>
                                <div class="font-medium" id="hubtelSummaryStatus" style="color: var(--text-primary);">Not set</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                        <div class="flex space-x-2">
                            <button type="button" 
                                    onclick="showHubtelConfigSummary()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: #2563EB;">
                                <i class="fas fa-info-circle mr-1"></i>
                                Summary
                            </button>
                            <button type="button" 
                                    onclick="clearHubtelForm()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: var(--text-secondary);">
                                <i class="fas fa-undo mr-1"></i>
                                Clear
                            </button>
                        </div>
                        <button type="submit" 
                                id="hubtelSubmitBtn"
                                class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                                style="background-color: #2563EB;">
                            <i class="fas fa-save mr-2"></i>
                            Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Test Results -->
    <div id="hubtelConnectionResult" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-plug mr-3 text-lg" style="color: #2563EB;"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Connection Test Results</h3>
                </div>
                <button onclick="closeHubtelConnectionResult()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="hubtelConnectionResultContent"></div>
        </div>
    </div>

    <!-- Fee Information -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(40, 167, 69, 0.05));">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Hubtel Fees (Ghana)</h3>
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
                        <span style="color: #2563EB;">T+1 to T+3 business days</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium" style="color: var(--text-primary);">Minimum Payout</span>
                        <span style="color: var(--text-primary);">GHS 10</span>
                    </div>
                </div>
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(37, 99, 235, 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-calculator mr-2 mt-0.5" style="color: #2563EB;"></i>
                        <div>
                            <p class="font-medium text-sm" style="color: #2563EB;">Example Calculation (GHS 100)</p>
                            <p class="mt-1 text-xs" style="color: var(--text-primary);">Fee: 1.95% (GHS 1.95) | You receive: GHS 98.05</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for Hubtel -->
<script>
// State Management
let hubtelCurrentState = {
    connection: 'checking',
    configuration: 'checking',
    environment: 'unknown',
    providerStatus: 'checking'
};

let hubtelSubmittedConfig = @json(session('submitted_config', null));
let hubtelIsPolling = false;
let hubtelPollInterval = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeHubtelInterface();
    loadHubtelInitialStatus();
    
    if (hubtelSubmittedConfig && hubtelSubmittedConfig.provider === 'hubtel') {
        applyHubtelSubmittedConfig(hubtelSubmittedConfig);
        startHubtelStatusPolling();
    }
    
    setupHubtelFormValidation();
    initHubtelToggleSwitch();
    initHubtelStatusCards();
    initHubtelEnvironmentRadios();
});

// Initialize environment radio styling
function initHubtelEnvironmentRadios() {
    const sandboxRadio = document.getElementById('hubtelSandboxRadio');
    const productionRadio = document.getElementById('hubtelProductionRadio');
    
    updateHubtelEnvironmentRadioStyle();
    
    sandboxRadio.addEventListener('change', updateHubtelEnvironmentRadioStyle);
    productionRadio.addEventListener('change', updateHubtelEnvironmentRadioStyle);
}

function updateHubtelEnvironmentRadioStyle() {
    const sandboxRadio = document.getElementById('hubtelSandboxRadio');
    const productionRadio = document.getElementById('hubtelProductionRadio');
    const sandboxLabel = document.getElementById('hubtelSandboxLabel');
    const productionLabel = document.getElementById('hubtelProductionLabel');
    const sandboxIndicator = document.getElementById('hubtelSandboxRadioIndicator');
    const productionIndicator = document.getElementById('hubtelProductionRadioIndicator');
    
    if (sandboxRadio.checked) {
        sandboxLabel.style.borderColor = '#2563EB';
        sandboxLabel.style.backgroundColor = 'rgba(37, 99, 235, 0.05)';
        sandboxIndicator.style.borderColor = '#2563EB';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = '#2563EB';
        productionLabel.style.borderColor = 'var(--border-color)';
        productionLabel.style.backgroundColor = 'var(--bg-secondary)';
        productionIndicator.style.borderColor = 'var(--border-color)';
        productionIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    } else {
        productionLabel.style.borderColor = '#2563EB';
        productionLabel.style.backgroundColor = 'rgba(37, 99, 235, 0.05)';
        productionIndicator.style.borderColor = '#2563EB';
        productionIndicator.querySelector('.w-2').style.backgroundColor = '#2563EB';
        sandboxLabel.style.borderColor = 'var(--border-color)';
        sandboxLabel.style.backgroundColor = 'var(--bg-secondary)';
        sandboxIndicator.style.borderColor = 'var(--border-color)';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    }
}

// Initialize status cards
function initHubtelStatusCards() {
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

// Initialize Hubtel interface
function initializeHubtelInterface() {
    const toggle = document.getElementById('hubtelEnabledToggle');
    const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateHubtelToggleState(toggle.checked, toggleDot, toggleBg);
    
    toggle.addEventListener('change', function() {
        updateHubtelToggleState(this.checked, toggleDot, toggleBg);
        onHubtelEnabledChange();
    });
    
    // ✅ FORM SUBMISSION IS HANDLED BY THE MAIN PROVIDER FORM HANDLER
    // The form has class="provider-form" and data-provider="hubtel"
    
    validateHubtelClientId();
    validateHubtelClientSecret();
    onHubtelEnabledChange();
}

// Update toggle switch state
function updateHubtelToggleState(isChecked, toggleDot, toggleBg) {
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
function initHubtelToggleSwitch() {
    const toggle = document.getElementById('hubtelEnabledToggle');
    const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateHubtelToggleState(toggle.checked, toggleDot, toggleBg);
}

// Load initial status
function loadHubtelInitialStatus() {
    showHubtelLoadingState();
    fetchHubtelImmediateStatus()
        .then(status => {
            hubtelCurrentState = status;
            updateHubtelStatusDisplay(status);
            updateHubtelFormBasedOnStatus(status);
            hideHubtelLoadingState();
        })
        .catch(error => {
            console.error('Failed to load Hubtel initial status:', error);
            fallbackToHubtelFormStatus();
            hideHubtelLoadingState();
        });
}

// Show loading state
function showHubtelLoadingState() {
    const cards = ['hubtelConnectionCard', 'hubtelConfigCard', 'hubtelEnvironmentCard', 'hubtelProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.add('loading');
    });
}

// Hide loading state
function hideHubtelLoadingState() {
    const cards = ['hubtelConnectionCard', 'hubtelConfigCard', 'hubtelEnvironmentCard', 'hubtelProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.remove('loading');
    });
}

// Fetch immediate status
function fetchHubtelImmediateStatus() {
    return fetch('{{ route("admin.payment-providers.immediate-status") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'hubtel' })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response failed');
        return response.json();
    })
    .then(data => {
        if (data.success && data.data.hubtel) return data.data.hubtel;
        throw new Error('Invalid response format');
    });
}

// Update status display
function updateHubtelStatusDisplay(status) {
    updateHubtelConnectionStatus(status);
    updateHubtelConfigurationStatus(status);
    updateHubtelEnvironmentStatus(status);
    updateHubtelProviderStatus(status);
    updateHubtelFormStatusBadge(status);
    updateHubtelTestButtonState(status);
    updateHubtelProviderToggleButton(status);
}

// Update connection status
function updateHubtelConnectionStatus(status) {
    const icon = document.getElementById('hubtelConnectionStatusIcon');
    const text = document.getElementById('hubtelConnectionStatusText');
    const details = document.getElementById('hubtelConnectionStatusDetails');
    const extraInfo = document.getElementById('hubtelConnectionExtraInfo');
    const card = document.getElementById('hubtelConnectionCard');
    
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
        extraInfo.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Configure Client ID and Secret';
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
function updateHubtelConfigurationStatus(status) {
    const icon = document.getElementById('hubtelConfigStatusIcon');
    const text = document.getElementById('hubtelConfigStatusText');
    const details = document.getElementById('hubtelConfigStatusDetails');
    const extraInfo = document.getElementById('hubtelConfigExtraInfo');
    const card = document.getElementById('hubtelConfigCard');
    
    if (status.configured) {
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Complete</span>';
        details.textContent = 'All required fields configured';
        details.style.color = '#28a745';
        
        const fields = [];
        if (status.client_id_configured) fields.push('Client ID');
        if (status.client_secret_configured) fields.push('Client Secret');
        
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
function updateHubtelEnvironmentStatus(status) {
    const icon = document.getElementById('hubtelEnvironmentStatusIcon');
    const text = document.getElementById('hubtelEnvironmentStatusText');
    const details = document.getElementById('hubtelEnvironmentStatusDetails');
    const extraInfo = document.getElementById('hubtelEnvironmentExtraInfo');
    const card = document.getElementById('hubtelEnvironmentCard');
    
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
        icon.style.backgroundColor = 'rgba(37, 99, 235, 0.2)';
        icon.style.color = '#2563EB';
        text.innerHTML = '<span style="color: #2563EB;">Sandbox</span>';
        details.textContent = 'Test mode active';
        details.style.color = '#2563EB';
        extraInfo.innerHTML = '<i class="fas fa-check-circle mr-1"></i> No real money movement';
        extraInfo.style.color = '#2563EB';
        card.style.borderLeft = '4px solid #2563EB';
    }
}

// Update provider status
function updateHubtelProviderStatus(status) {
    const icon = document.getElementById('hubtelProviderStatusIcon');
    const text = document.getElementById('hubtelProviderStatusText');
    const details = document.getElementById('hubtelProviderStatusDetails');
    const card = document.getElementById('hubtelProviderStatusCard');
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
function updateHubtelProviderToggleButton(status) {
    const button = document.getElementById('hubtelToggleProviderBtn');
    const buttonText = document.getElementById('hubtelToggleBtnText');
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
function toggleHubtelProviderStatus() {
    const enabledToggle = document.getElementById('hubtelEnabledToggle');
    const currentState = enabledToggle.checked;
    
    enabledToggle.checked = !currentState;
    
    const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    updateHubtelToggleState(!currentState, toggleDot, toggleBg);
    
    onHubtelEnabledChange();
    
    const action = currentState ? 'disabled' : 'enabled';
    showHubtelToast(`Hubtel will be ${action} when you save the configuration`, 'info');
}

// Update form status badge
function updateHubtelFormStatusBadge(status) {
    const badge = document.getElementById('hubtelFormStatusBadge');
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
function updateHubtelTestButtonState(status) {
    const testBtn = document.getElementById('hubtelTestConnectionBtn');
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
function applyHubtelSubmittedConfig(config) {
    const enabledToggle = document.getElementById('hubtelEnabledToggle');
    if (enabledToggle && config.enabled !== undefined) {
        enabledToggle.checked = config.enabled;
        const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateHubtelToggleState(config.enabled, toggleDot, toggleBg);
    }
    onHubtelEnabledChange();
}

// Start status polling
function startHubtelStatusPolling() {
    if (hubtelIsPolling) return;
    
    hubtelIsPolling = true;
    let pollCount = 0;
    const maxPolls = 60;
    
    hubtelPollInterval = setInterval(() => {
        pollCount++;
        
        if (pollCount >= maxPolls) {
            stopHubtelStatusPolling();
            showHubtelToast('Status update timeout. Refresh the page to check current status.', 'warning');
            return;
        }
        
        fetchHubtelImmediateStatus()
            .then(status => {
                hubtelCurrentState = status;
                updateHubtelStatusDisplay(status);
                
                if (status.source !== 'cache_expected') {
                    stopHubtelStatusPolling();
                    showHubtelToast('Configuration update completed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Hubtel polling error:', error);
            });
    }, 1000);
}

// Stop status polling
function stopHubtelStatusPolling() {
    if (hubtelPollInterval) {
        clearInterval(hubtelPollInterval);
        hubtelPollInterval = null;
    }
    hubtelIsPolling = false;
}

// Test connection
function testHubtelConnection() {
    const testBtn = document.getElementById('hubtelTestConnectionBtn');
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
        body: JSON.stringify({ provider: 'hubtel' })
    })
    .then(response => response.json())
    .then(data => {
        showHubtelConnectionResult(data.success, data.message, data.details);
        setTimeout(loadHubtelInitialStatus, 1000);
    })
    .catch(error => {
        showHubtelConnectionResult(false, 'Connection test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

// Reset configuration
function resetHubtelConfiguration() {
    if (!confirm('⚠️ WARNING: This will clear all Hubtel API credentials and disable the provider. Continue?')) {
        return;
    }
    
    fetch('{{ route("admin.payment-providers.reset") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'hubtel' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showHubtelToast('Configuration reset successfully', 'success');
            clearHubtelForm();
            setTimeout(loadHubtelInitialStatus, 1000);
        } else {
            showHubtelToast('Reset failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showHubtelToast('Reset failed: ' + error.message, 'error');
    });
}

// Verify environment
function verifyHubtelEnvironment() {
    const clientId = document.getElementById('hubtelClientIdInput').value;
    const clientSecret = document.getElementById('hubtelClientSecretInput').value;
    
    if (!clientId || !clientSecret) {
        showHubtelToast('Please enter both Client ID and Secret first', 'warning');
        return;
    }
    
    fetch('{{ route("admin.payment-providers.verify-environment") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'hubtel' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = data.is_production ? 
                '🔴 PRODUCTION environment detected - Real payments will be processed' : 
                '🔵 SANDBOX environment detected - Test mode active';
            showHubtelToast(message, data.is_production ? 'warning' : 'success');
            
            const status = hubtelCurrentState;
            status.environment = data.is_production ? 'production' : 'sandbox';
            updateHubtelEnvironmentStatus(status);
        } else {
            showHubtelToast('Environment verification failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showHubtelToast('Verification failed: ' + error.message, 'error');
    });
}

// Show connection result
function showHubtelConnectionResult(success, message, details = null) {
    const resultDiv = document.getElementById('hubtelConnectionResult');
    const contentDiv = document.getElementById('hubtelConnectionResultContent');
    
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
                ${success ? '<div class="mt-3 p-2 rounded text-sm" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;"><i class="fas fa-check-circle mr-1"></i> Your Hubtel integration is properly configured and ready to accept payments.</div>' : ''}
            </div>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
}

// Close connection result
function closeHubtelConnectionResult() {
    document.getElementById('hubtelConnectionResult').classList.add('hidden');
}

// Show config summary
function showHubtelConfigSummary() {
    const clientId = document.getElementById('hubtelClientIdInput').value;
    const clientSecret = document.getElementById('hubtelClientSecretInput').value;
    const merchantAccount = document.getElementById('hubtelMerchantAccountInput').value;
    const enabled = document.getElementById('hubtelEnabledToggle').checked;
    const environment = document.querySelector('input[name="environment"]:checked').value;
    
    document.getElementById('hubtelSummaryClientId').textContent = clientId ? 
        '••••••••' + clientId.slice(-4) : 'Not set';
    document.getElementById('hubtelSummaryClientSecret').textContent = clientSecret ? 
        '••••••••' + clientSecret.slice(-4) : 'Not set';
    document.getElementById('hubtelSummaryMerchantAccount').textContent = merchantAccount || 'Not set';
    document.getElementById('hubtelSummaryEnvironment').textContent = environment === 'production' ? 'Production' : 'Sandbox';
    document.getElementById('hubtelSummaryStatus').textContent = enabled ? 'Enabled' : 'Disabled';
    
    document.getElementById('hubtelConfigSummary').classList.remove('hidden');
}

// Hide config summary
function hideHubtelConfigSummary() {
    document.getElementById('hubtelConfigSummary').classList.add('hidden');
}

// Clear form
function clearHubtelForm() {
    if (confirm('Clear all form fields?')) {
        document.getElementById('hubtelClientIdInput').value = '';
        document.getElementById('hubtelClientSecretInput').value = '';
        document.getElementById('hubtelMerchantAccountInput').value = '';
        document.getElementById('hubtelEnabledToggle').checked = false;
        document.getElementById('hubtelSandboxRadio').checked = true;
        
        const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateHubtelToggleState(false, toggleDot, toggleBg);
        
        updateHubtelEnvironmentRadioStyle();
        onHubtelEnabledChange();
        validateHubtelClientId();
        validateHubtelClientSecret();
        
        showHubtelToast('Form cleared', 'info');
    }
}

// Toggle visibility
function toggleHubtelVisibility(inputId) {
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

// Validate Client ID
function validateHubtelClientId() {
    const input = document.getElementById('hubtelClientIdInput');
    const validation = document.getElementById('hubtelClientIdValidation');
    const icon = document.getElementById('hubtelClientIdStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Client ID is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (value.length < 10) {
        validation.textContent = 'Client ID appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ Client ID looks valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate Client Secret
function validateHubtelClientSecret() {
    const input = document.getElementById('hubtelClientSecretInput');
    const validation = document.getElementById('hubtelClientSecretValidation');
    const icon = document.getElementById('hubtelClientSecretStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Client Secret is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (value.length < 10) {
        validation.textContent = 'Client Secret appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ Client Secret looks valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate Merchant Account (optional)
function validateHubtelMerchantAccount() {
    const input = document.getElementById('hubtelMerchantAccountInput');
    const validation = document.getElementById('hubtelMerchantAccountValidation');
    const icon = document.getElementById('hubtelMerchantAccountStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Optional - leave empty if not needed';
        validation.style.color = 'var(--text-secondary)';
        icon.innerHTML = '<i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>';
        return true;
    }
    
    validation.textContent = '✓ Merchant account configured';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// On enabled change
function onHubtelEnabledChange() {
    const enabled = document.getElementById('hubtelEnabledToggle').checked;
    const statusText = document.getElementById('hubtelEnabledStatusText');
    
    if (enabled) {
        statusText.textContent = 'Hubtel will be enabled for payment processing';
    } else {
        statusText.textContent = 'Hubtel will be disabled';
    }
}

// On environment change
function onHubtelEnvironmentChange() {
    updateHubtelEnvironmentRadioStyle();
    updateHubtelEnvironmentStatus(hubtelCurrentState);
}

// Set up form validation
function setupHubtelFormValidation() {
    const form = document.getElementById('hubtelConfigForm');
    const submitBtn = document.getElementById('hubtelSubmitBtn');
    
    form.addEventListener('input', () => {
        const clientIdValid = validateHubtelClientId();
        const clientSecretValid = validateHubtelClientSecret();
        submitBtn.disabled = !(clientIdValid && clientSecretValid);
    });
}

// Copy webhook URL
function copyHubtelWebhookUrl() {
    const urlElement = document.getElementById('hubtelWebhookUrl');
    const url = urlElement.textContent;
    
    navigator.clipboard.writeText(url).then(() => {
        showHubtelToast('Webhook URL copied to clipboard', 'success');
    }).catch(() => {
        showHubtelToast('Failed to copy URL', 'error');
    });
}

// Show toast notification
function showHubtelToast(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#2563EB'
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
function forceHubtelStatusRefresh() {
    loadHubtelInitialStatus();
    showHubtelToast('Status refreshed successfully', 'success');
}

// Fallback to form status
function fallbackToHubtelFormStatus() {
    const clientId = document.getElementById('hubtelClientIdInput').value.trim();
    const clientSecret = document.getElementById('hubtelClientSecretInput').value.trim();
    const enabled = document.getElementById('hubtelEnabledToggle').checked;
    
    const fallbackStatus = {
        enabled: enabled,
        configured: !!(clientId && clientSecret),
        source: 'fallback'
    };
    
    updateHubtelStatusDisplay(fallbackStatus);
}

// Update form based on status
function updateHubtelFormBasedOnStatus(status) {
    if (status.source === 'cache_expected' || status.source === 'cache_actual') {
        return;
    }
    
    const enabledToggle = document.getElementById('hubtelEnabledToggle');
    if (enabledToggle.checked !== status.enabled) {
        enabledToggle.checked = status.enabled;
        const toggleDot = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#hubtelEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateHubtelToggleState(status.enabled, toggleDot, toggleBg);
        onHubtelEnabledChange();
    }
}

// Retry failed update
function retryHubtelUpdate() {
    fetch('{{ route("admin.payment-providers.retry-update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'hubtel' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showHubtelToast('Update retry initiated', 'success');
            startHubtelStatusPolling();
        } else {
            showHubtelToast(data.message || 'Retry failed', 'error');
        }
    })
    .catch(error => {
        showHubtelToast('Retry failed: ' + error.message, 'error');
    });
}

// View error details
function viewErrorDetails() {
    showHubtelToast('Error details functionality would show detailed logs', 'info');
}
</script>

<style>
/* Hubtel-specific styles */
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
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
}

input:focus, select:focus {
    outline: none;
    border-color: #2563EB !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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