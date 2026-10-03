<!-- ============================================ -->
{{-- FLUTTERWAVE PROVIDER CONFIGURATION --}}
{{-- ============================================ --}}
<div class="space-y-6 animate-fadeInUp">
    <!-- State-Aware Status Banner -->
    @if(session('payment_update_status') == 'processing' && session('payment_update_provider') == 'flutterwave')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(249, 115, 22, 0.3); background: linear-gradient(to right, rgba(249, 115, 22, 0.05), rgba(249, 115, 22, 0.1));">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(249, 115, 22, 0.2);">
                    <i class="fas fa-sync-alt fa-spin text-lg" style="color: #F97316;"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: #F97316;">Configuration Update in Progress</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    Your Flutterwave settings are being updated in the background. This may take a few moments.
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
    @if(session('success') && str_contains(session('success'), 'Flutterwave'))
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
    @if(session('error') && str_contains(session('error'), 'Flutterwave'))
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
                    <button onclick="retryFlutterwaveUpdate()" 
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
        <div class="card overflow-hidden status-card" id="flutterwaveConnectionCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="flutterwaveConnectionStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Connection</div>
                    <div class="text-sm font-semibold mt-1" id="flutterwaveConnectionStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="flutterwaveConnectionStatusDetails">Initializing connection status...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="flutterwaveConnectionExtraInfo"></div>
            </div>
        </div>

        <!-- Configuration Status -->
        <div class="card overflow-hidden status-card" id="flutterwaveConfigCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="flutterwaveConfigStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Configuration</div>
                    <div class="text-sm font-semibold mt-1" id="flutterwaveConfigStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="flutterwaveConfigStatusDetails">Validating configuration...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="flutterwaveConfigExtraInfo"></div>
            </div>
        </div>

        <!-- Environment Status -->
        <div class="card overflow-hidden status-card" id="flutterwaveEnvironmentCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="flutterwaveEnvironmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Environment</div>
                    <div class="text-sm font-semibold mt-1" id="flutterwaveEnvironmentStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="flutterwaveEnvironmentStatusDetails">Detecting environment...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="text-xs" style="color: var(--text-secondary);" id="flutterwaveEnvironmentExtraInfo"></div>
            </div>
        </div>

        <!-- Provider Status -->
        <div class="card overflow-hidden status-card" id="flutterwaveProviderStatusCard">
            <div class="flex items-center p-4">
                <div class="mr-3">
                    <div id="flutterwaveProviderStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full status-icon">
                        <i class="fas fa-power-off"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-secondary);">Provider Status</div>
                    <div class="text-sm font-semibold mt-1" id="flutterwaveProviderStatusText">Checking...</div>
                    <div class="text-xs mt-1" id="flutterwaveProviderStatusDetails">Checking provider availability...</div>
                </div>
            </div>
            <div class="px-4 pb-3">
                <div class="flex space-x-2">
                    <button id="flutterwaveToggleProviderBtn" 
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed"
                            onclick="toggleFlutterwaveProviderStatus()">
                        <i class="fas fa-power-off mr-1"></i>
                        <span id="flutterwaveToggleBtnText">Enable</span>
                    </button>
                    <button onclick="forceFlutterwaveStatusRefresh()" 
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:opacity-80"
                            style="background-color: rgba(249, 115, 22, 0.1); color: #F97316;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Progress Indicator -->
    <div id="flutterwaveLiveProgressIndicator" class="hidden overflow-hidden rounded-xl border p-4 shadow-sm" style="border-color: rgba(249, 115, 22, 0.3); background: linear-gradient(to right, rgba(249, 115, 22, 0.05), rgba(249, 115, 22, 0.1));">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-sync-alt fa-spin mr-2" style="color: #F97316;"></i>
                <span class="font-medium" style="color: var(--text-primary);">Background Update in Progress</span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <span id="flutterwaveProgressStep">Initializing...</span>
            </div>
        </div>
        <div class="mb-2 h-2 w-full rounded-full" style="background-color: rgba(249, 115, 22, 0.3);">
            <div id="flutterwaveProgressBar" class="h-2 rounded-full transition-all duration-500" style="background-color: #F97316; width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
            <span>Started</span>
            <span id="flutterwaveProgressTime">Just now</span>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="flex flex-wrap gap-2">
        <button onclick="testFlutterwaveConnection()" 
                id="flutterwaveTestConnectionBtn" 
                class="btn-primary inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                style="background-color: #F97316;">
            <i class="fas fa-plug mr-2"></i>
            Test Connection
        </button>
        
        <button onclick="verifyFlutterwaveEnvironment()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4);">
            <i class="fas fa-check-circle mr-2"></i>
            Verify Environment
        </button>
        
        <button onclick="resetFlutterwaveConfiguration()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4);">
            <i class="fas fa-trash-alt mr-2"></i>
            Reset Configuration
        </button>
        
        <button onclick="forceFlutterwaveStatusRefresh()" 
                class="inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-medium transition-colors hover:shadow-md"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Status
        </button>
    </div>

    <!-- Configuration Form -->
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(249, 115, 22, 0.05));">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Flutterwave Configuration</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">Configure your Flutterwave API credentials for payment processing across Africa</p>
                </div>
                <div class="flex items-center space-x-2 mt-2 lg:mt-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" id="flutterwaveFormStatusBadge">
                        <span class="mr-1.5 h-2 w-2 rounded-full"></span>
                        Loading...
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="flutterwaveConfigForm" class="provider-form" method="POST" action="{{ route('admin.payment-providers.configure') }}" data-provider="flutterwave">
                @csrf
                <input type="hidden" name="provider" value="flutterwave">
                
                <div class="space-y-6">
                    <!-- Enable/Disable Toggle -->
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">Enable Flutterwave</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Toggle to enable/disable Flutterwave payment processing</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" id="flutterwaveEnabledToggle" class="sr-only peer" 
                                {{ old('enabled', env('FLUTTERWAVE_ENABLED', false)) ? 'checked' : '' }}
                                onchange="onFlutterwaveEnabledChange()">
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
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="flutterwaveSandboxLabel">
                                <input type="radio" name="environment" value="sandbox" class="sr-only" id="flutterwaveSandboxRadio"
                                    {{ (old('environment', env('FLUTTERWAVE_ENVIRONMENT', 'sandbox')) === 'sandbox') ? 'checked' : '' }}
                                    onchange="onFlutterwaveEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="flutterwaveSandboxRadioIndicator">
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
                            <label class="flex items-center p-3 rounded-lg cursor-pointer transition-all" style="border: 1px solid var(--border-color); background-color: var(--bg-secondary);" id="flutterwaveProductionLabel">
                                <input type="radio" name="environment" value="production" class="sr-only" id="flutterwaveProductionRadio"
                                    {{ (old('environment', env('FLUTTERWAVE_ENVIRONMENT', 'sandbox')) === 'production') ? 'checked' : '' }}
                                    onchange="onFlutterwaveEnvironmentChange()">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center mr-3" id="flutterwaveProductionRadioIndicator">
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
                        <!-- Public Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Public Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="flutterwavePublicKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="public_key" 
                                       id="flutterwavePublicKeyInput"
                                       value="{{ old('public_key', env('FLUTTERWAVE_PUBLIC_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="FLWPUBK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       oninput="validateFlutterwavePublicKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="toggleFlutterwaveVisibility('flutterwavePublicKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="flutterwavePublicKeyValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-key mr-1"></i> Format: FLWPUBK-xxxxxxxx (starts with FLWPUBK-)
                            </p>
                            @error('public_key')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Secret Key</span>
                                    <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                                    <div class="ml-2" id="flutterwaveSecretKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="secret_key" 
                                       id="flutterwaveSecretKeyInput"
                                       value="{{ old('secret_key', env('FLUTTERWAVE_SECRET_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="FLWSECK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       oninput="validateFlutterwaveSecretKey()"
                                       autocomplete="off"
                                       required>
                                <button type="button" 
                                        onclick="toggleFlutterwaveVisibility('flutterwaveSecretKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="flutterwaveSecretKeyValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-lock mr-1"></i> Format: FLWSECK-xxxxxxxx (starts with FLWSECK-)
                            </p>
                            @error('secret_key')
                            <p class="mt-1 text-xs" style="color: #dc3545;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Additional Configuration -->
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Encryption Key (Optional) -->
                        <div>
                            <label class="mb-2 block text-sm font-medium" style="color: var(--text-primary);">
                                <div class="flex items-center">
                                    <span>Encryption Key</span>
                                    <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">(Optional)</span>
                                    <div class="ml-2" id="flutterwaveEncryptionKeyStatusIcon">
                                        <i class="fas fa-circle text-xs"></i>
                                    </div>
                                </div>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       name="encryption_key" 
                                       id="flutterwaveEncryptionKeyInput"
                                       value="{{ old('encryption_key', env('FLUTTERWAVE_ENCRYPTION_KEY', '')) }}"
                                       class="w-full rounded-lg border px-4 py-3 transition-colors focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-mono text-sm"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="FLWSECK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                       oninput="validateFlutterwaveEncryptionKey()"
                                       autocomplete="off">
                                <button type="button" 
                                        onclick="toggleFlutterwaveVisibility('flutterwaveEncryptionKeyInput')"
                                        class="absolute right-3 top-3 hover:opacity-80"
                                        style="color: var(--text-secondary);">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs" id="flutterwaveEncryptionKeyValidation" style="color: var(--text-secondary);"></div>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-1"></i> Optional: Used for additional security when encrypting payloads
                            </p>
                            @error('encryption_key')
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
                            <p style="color: var(--text-secondary);">Add the following URL to your Flutterwave Dashboard → Settings → Webhooks:</p>
                            <div class="flex items-center justify-between rounded-lg p-2 font-mono text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <code id="flutterwaveWebhookUrl" style="color: var(--text-primary);">{{ url('/api/payments/flutterwave/webhook') }}</code>
                                <button type="button" onclick="copyFlutterwaveWebhookUrl()" class="ml-2 px-2 py-1 rounded hover:opacity-80" style="background-color: rgba(249, 115, 22, 0.1); color: #F97316;">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background-color: rgba(255, 193, 7, 0.1);">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: #ffc107;"></i>
                                    <p class="text-xs" style="color: var(--text-primary);">
                                        <strong>Important:</strong> Webhooks are essential for automatic payment confirmation. 
                                        Configure the webhook URL above in your Flutterwave dashboard for the <code class="px-1 rounded" style="background-color: rgba(0,0,0,0.1);">charge.completed</code> event.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Information Panel -->
                    <div class="rounded-lg border p-4" style="background-color: rgba(var(--bg-secondary-rgb), 0.5); border-color: var(--border-color);">
                        <h6 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2" style="color: #F97316;"></i>
                            Key Information
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Public Key</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Starts with FLWPUBK- - Used for client-side</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: #28a745;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Secret Key</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Starts with FLWSECK- - Used for server-side</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #F97316;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Sandbox Keys</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Available upon signup - no approval needed</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: #F97316;"></i>
                                <div>
                                    <span style="color: var(--text-primary);">Production Access</span>
                                    <p class="text-xs" style="color: var(--text-secondary);">Requires business verification approval</p>
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

                    <!-- Configuration Summary -->
                    <div id="flutterwaveConfigSummary" class="hidden rounded-lg border p-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium" style="color: var(--text-primary);">Configuration Summary</h4>
                            <button type="button" onclick="hideFlutterwaveConfigSummary()" class="hover:opacity-70" style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Public Key:</div>
                                <div class="font-medium" id="flutterwaveSummaryPublicKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Secret Key:</div>
                                <div class="font-medium" id="flutterwaveSummarySecretKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Encryption Key:</div>
                                <div class="font-medium" id="flutterwaveSummaryEncryptionKey" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Environment:</div>
                                <div class="font-medium" id="flutterwaveSummaryEnvironment" style="color: var(--text-primary);">Not set</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div style="color: var(--text-secondary);">Status:</div>
                                <div class="font-medium" id="flutterwaveSummaryStatus" style="color: var(--text-primary);">Not set</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t pt-6 gap-4" style="border-color: var(--border-color);">
                        <div class="flex space-x-2">
                            <button type="button" 
                                    onclick="showFlutterwaveConfigSummary()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: #F97316;">
                                <i class="fas fa-info-circle mr-1"></i>
                                Summary
                            </button>
                            <button type="button" 
                                    onclick="clearFlutterwaveForm()"
                                    class="text-sm font-medium hover:opacity-80 flex items-center"
                                    style="color: var(--text-secondary);">
                                <i class="fas fa-undo mr-1"></i>
                                Clear
                            </button>
                        </div>
                        <button type="submit" 
                                id="flutterwaveSubmitBtn"
                                class="btn-primary rounded-lg px-6 py-2.5 text-sm font-medium transition-colors hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-none"
                                style="background-color: #F97316;">
                            <i class="fas fa-save mr-2"></i>
                            Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Test Results -->
    <div id="flutterwaveConnectionResult" class="card hidden overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-plug mr-3 text-lg" style="color: #F97316;"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Connection Test Results</h3>
                </div>
                <button onclick="closeFlutterwaveConnectionResult()" class="hover:opacity-70" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="flutterwaveConnectionResultContent"></div>
        </div>
    </div>

    <!-- Fee Information -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b p-6" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(40, 167, 69, 0.05));">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Flutterwave Fees (Varies by Country)</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Mobile Money (Ghana)</span>
                        <span style="color: #28a745;">~1.5% + Fixed Fee</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Card Payments</span>
                        <span style="color: #28a745;">2.9% + Fixed Fee</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b" style="border-color: var(--border-color);">
                        <span class="font-medium" style="color: var(--text-primary);">Settlement Time</span>
                        <span style="color: #F97316;">T+1 to T+3 business days</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-medium" style="color: var(--text-primary);">Minimum Payout</span>
                        <span style="color: var(--text-primary);">Varies by country</span>
                    </div>
                </div>
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(249, 115, 22, 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-calculator mr-2 mt-0.5" style="color: #F97316;"></i>
                        <div>
                            <p class="font-medium text-sm" style="color: #F97316;">Contact Flutterwave for Exact Pricing</p>
                            <p class="mt-1 text-xs" style="color: var(--text-primary);">Fees vary by country, payment method, and volume. Contact Flutterwave sales for your specific rates.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for Flutterwave -->
<script>
// State Management
let flutterwaveCurrentState = {
    connection: 'checking',
    configuration: 'checking',
    environment: 'unknown',
    providerStatus: 'checking'
};

let flutterwaveSubmittedConfig = @json(session('submitted_config', null));
let flutterwaveIsPolling = false;
let flutterwavePollInterval = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeFlutterwaveInterface();
    loadFlutterwaveInitialStatus();
    
    if (flutterwaveSubmittedConfig && flutterwaveSubmittedConfig.provider === 'flutterwave') {
        applyFlutterwaveSubmittedConfig(flutterwaveSubmittedConfig);
        startFlutterwaveStatusPolling();
    }
    
    setupFlutterwaveFormValidation();
    initFlutterwaveToggleSwitch();
    initFlutterwaveStatusCards();
    initFlutterwaveEnvironmentRadios();
});

// Initialize environment radio styling
function initFlutterwaveEnvironmentRadios() {
    const sandboxRadio = document.getElementById('flutterwaveSandboxRadio');
    const productionRadio = document.getElementById('flutterwaveProductionRadio');
    
    updateFlutterwaveEnvironmentRadioStyle();
    
    sandboxRadio.addEventListener('change', updateFlutterwaveEnvironmentRadioStyle);
    productionRadio.addEventListener('change', updateFlutterwaveEnvironmentRadioStyle);
}

function updateFlutterwaveEnvironmentRadioStyle() {
    const sandboxRadio = document.getElementById('flutterwaveSandboxRadio');
    const productionRadio = document.getElementById('flutterwaveProductionRadio');
    const sandboxLabel = document.getElementById('flutterwaveSandboxLabel');
    const productionLabel = document.getElementById('flutterwaveProductionLabel');
    const sandboxIndicator = document.getElementById('flutterwaveSandboxRadioIndicator');
    const productionIndicator = document.getElementById('flutterwaveProductionRadioIndicator');
    
    if (sandboxRadio.checked) {
        sandboxLabel.style.borderColor = '#F97316';
        sandboxLabel.style.backgroundColor = 'rgba(249, 115, 22, 0.05)';
        sandboxIndicator.style.borderColor = '#F97316';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = '#F97316';
        productionLabel.style.borderColor = 'var(--border-color)';
        productionLabel.style.backgroundColor = 'var(--bg-secondary)';
        productionIndicator.style.borderColor = 'var(--border-color)';
        productionIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    } else {
        productionLabel.style.borderColor = '#F97316';
        productionLabel.style.backgroundColor = 'rgba(249, 115, 22, 0.05)';
        productionIndicator.style.borderColor = '#F97316';
        productionIndicator.querySelector('.w-2').style.backgroundColor = '#F97316';
        sandboxLabel.style.borderColor = 'var(--border-color)';
        sandboxLabel.style.backgroundColor = 'var(--bg-secondary)';
        sandboxIndicator.style.borderColor = 'var(--border-color)';
        sandboxIndicator.querySelector('.w-2').style.backgroundColor = 'transparent';
    }
}

// Initialize status cards
function initFlutterwaveStatusCards() {
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

// Initialize Flutterwave interface
function initializeFlutterwaveInterface() {
    const toggle = document.getElementById('flutterwaveEnabledToggle');
    const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateFlutterwaveToggleState(toggle.checked, toggleDot, toggleBg);
    
    toggle.addEventListener('change', function() {
        updateFlutterwaveToggleState(this.checked, toggleDot, toggleBg);
        onFlutterwaveEnabledChange();
    });
    
    // ✅ FORM SUBMISSION IS HANDLED BY THE MAIN PROVIDER FORM HANDLER
    // The form has class="provider-form" and data-provider="flutterwave"
    
    validateFlutterwavePublicKey();
    validateFlutterwaveSecretKey();
    onFlutterwaveEnabledChange();
}

// Update toggle switch state
function updateFlutterwaveToggleState(isChecked, toggleDot, toggleBg) {
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
function initFlutterwaveToggleSwitch() {
    const toggle = document.getElementById('flutterwaveEnabledToggle');
    const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    
    updateFlutterwaveToggleState(toggle.checked, toggleDot, toggleBg);
}

// Load initial status
function loadFlutterwaveInitialStatus() {
    showFlutterwaveLoadingState();
    fetchFlutterwaveImmediateStatus()
        .then(status => {
            flutterwaveCurrentState = status;
            updateFlutterwaveStatusDisplay(status);
            updateFlutterwaveFormBasedOnStatus(status);
            hideFlutterwaveLoadingState();
        })
        .catch(error => {
            console.error('Failed to load Flutterwave initial status:', error);
            fallbackToFlutterwaveFormStatus();
            hideFlutterwaveLoadingState();
        });
}

// Show loading state
function showFlutterwaveLoadingState() {
    const cards = ['flutterwaveConnectionCard', 'flutterwaveConfigCard', 'flutterwaveEnvironmentCard', 'flutterwaveProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.add('loading');
    });
}

// Hide loading state
function hideFlutterwaveLoadingState() {
    const cards = ['flutterwaveConnectionCard', 'flutterwaveConfigCard', 'flutterwaveEnvironmentCard', 'flutterwaveProviderStatusCard'];
    cards.forEach(id => {
        const card = document.getElementById(id);
        if (card) card.classList.remove('loading');
    });
}

// Fetch immediate status
function fetchFlutterwaveImmediateStatus() {
    return fetch('{{ route("admin.payment-providers.immediate-status") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'flutterwave' })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response failed');
        return response.json();
    })
    .then(data => {
        if (data.success && data.data.flutterwave) return data.data.flutterwave;
        throw new Error('Invalid response format');
    });
}

// Update status display
function updateFlutterwaveStatusDisplay(status) {
    updateFlutterwaveConnectionStatus(status);
    updateFlutterwaveConfigurationStatus(status);
    updateFlutterwaveEnvironmentStatus(status);
    updateFlutterwaveProviderStatus(status);
    updateFlutterwaveFormStatusBadge(status);
    updateFlutterwaveTestButtonState(status);
    updateFlutterwaveProviderToggleButton(status);
}

// Update connection status
function updateFlutterwaveConnectionStatus(status) {
    const icon = document.getElementById('flutterwaveConnectionStatusIcon');
    const text = document.getElementById('flutterwaveConnectionStatusText');
    const details = document.getElementById('flutterwaveConnectionStatusDetails');
    const extraInfo = document.getElementById('flutterwaveConnectionExtraInfo');
    const card = document.getElementById('flutterwaveConnectionCard');
    
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
function updateFlutterwaveConfigurationStatus(status) {
    const icon = document.getElementById('flutterwaveConfigStatusIcon');
    const text = document.getElementById('flutterwaveConfigStatusText');
    const details = document.getElementById('flutterwaveConfigStatusDetails');
    const extraInfo = document.getElementById('flutterwaveConfigExtraInfo');
    const card = document.getElementById('flutterwaveConfigCard');
    
    if (status.configured) {
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        icon.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
        icon.style.color = '#28a745';
        text.innerHTML = '<span style="color: #28a745;">Complete</span>';
        details.textContent = 'All required fields configured';
        details.style.color = '#28a745';
        
        const fields = [];
        if (status.public_key_configured) fields.push('Public Key');
        if (status.secret_key_configured) fields.push('Secret Key');
        
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
function updateFlutterwaveEnvironmentStatus(status) {
    const icon = document.getElementById('flutterwaveEnvironmentStatusIcon');
    const text = document.getElementById('flutterwaveEnvironmentStatusText');
    const details = document.getElementById('flutterwaveEnvironmentStatusDetails');
    const extraInfo = document.getElementById('flutterwaveEnvironmentExtraInfo');
    const card = document.getElementById('flutterwaveEnvironmentCard');
    
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
        icon.style.backgroundColor = 'rgba(249, 115, 22, 0.2)';
        icon.style.color = '#F97316';
        text.innerHTML = '<span style="color: #F97316;">Sandbox</span>';
        details.textContent = 'Test mode active';
        details.style.color = '#F97316';
        extraInfo.innerHTML = '<i class="fas fa-check-circle mr-1"></i> No real money movement';
        extraInfo.style.color = '#F97316';
        card.style.borderLeft = '4px solid #F97316';
    }
}

// Update provider status
function updateFlutterwaveProviderStatus(status) {
    const icon = document.getElementById('flutterwaveProviderStatusIcon');
    const text = document.getElementById('flutterwaveProviderStatusText');
    const details = document.getElementById('flutterwaveProviderStatusDetails');
    const card = document.getElementById('flutterwaveProviderStatusCard');
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
function updateFlutterwaveProviderToggleButton(status) {
    const button = document.getElementById('flutterwaveToggleProviderBtn');
    const buttonText = document.getElementById('flutterwaveToggleBtnText');
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
function toggleFlutterwaveProviderStatus() {
    const enabledToggle = document.getElementById('flutterwaveEnabledToggle');
    const currentState = enabledToggle.checked;
    
    enabledToggle.checked = !currentState;
    
    const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
    const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
    updateFlutterwaveToggleState(!currentState, toggleDot, toggleBg);
    
    onFlutterwaveEnabledChange();
    
    const action = currentState ? 'disabled' : 'enabled';
    showFlutterwaveToast(`Flutterwave will be ${action} when you save the configuration`, 'info');
}

// Update form status badge
function updateFlutterwaveFormStatusBadge(status) {
    const badge = document.getElementById('flutterwaveFormStatusBadge');
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
function updateFlutterwaveTestButtonState(status) {
    const testBtn = document.getElementById('flutterwaveTestConnectionBtn');
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
function applyFlutterwaveSubmittedConfig(config) {
    const enabledToggle = document.getElementById('flutterwaveEnabledToggle');
    if (enabledToggle && config.enabled !== undefined) {
        enabledToggle.checked = config.enabled;
        const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateFlutterwaveToggleState(config.enabled, toggleDot, toggleBg);
    }
    onFlutterwaveEnabledChange();
}

// Start status polling
function startFlutterwaveStatusPolling() {
    if (flutterwaveIsPolling) return;
    
    flutterwaveIsPolling = true;
    let pollCount = 0;
    const maxPolls = 60;
    
    flutterwavePollInterval = setInterval(() => {
        pollCount++;
        
        if (pollCount >= maxPolls) {
            stopFlutterwaveStatusPolling();
            showFlutterwaveToast('Status update timeout. Refresh the page to check current status.', 'warning');
            return;
        }
        
        fetchFlutterwaveImmediateStatus()
            .then(status => {
                flutterwaveCurrentState = status;
                updateFlutterwaveStatusDisplay(status);
                
                if (status.source !== 'cache_expected') {
                    stopFlutterwaveStatusPolling();
                    showFlutterwaveToast('Configuration update completed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Flutterwave polling error:', error);
            });
    }, 1000);
}

// Stop status polling
function stopFlutterwaveStatusPolling() {
    if (flutterwavePollInterval) {
        clearInterval(flutterwavePollInterval);
        flutterwavePollInterval = null;
    }
    flutterwaveIsPolling = false;
}

// Test connection
function testFlutterwaveConnection() {
    const testBtn = document.getElementById('flutterwaveTestConnectionBtn');
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
        body: JSON.stringify({ provider: 'flutterwave' })
    })
    .then(response => response.json())
    .then(data => {
        showFlutterwaveConnectionResult(data.success, data.message, data.details);
        setTimeout(loadFlutterwaveInitialStatus, 1000);
    })
    .catch(error => {
        showFlutterwaveConnectionResult(false, 'Connection test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

// Reset configuration
function resetFlutterwaveConfiguration() {
    if (!confirm('⚠️ WARNING: This will clear all Flutterwave API credentials and disable the provider. Continue?')) {
        return;
    }
    
    fetch('{{ route("admin.payment-providers.reset") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'flutterwave' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showFlutterwaveToast('Configuration reset successfully', 'success');
            clearFlutterwaveForm();
            setTimeout(loadFlutterwaveInitialStatus, 1000);
        } else {
            showFlutterwaveToast('Reset failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showFlutterwaveToast('Reset failed: ' + error.message, 'error');
    });
}

// Verify environment
function verifyFlutterwaveEnvironment() {
    const publicKey = document.getElementById('flutterwavePublicKeyInput').value;
    const secretKey = document.getElementById('flutterwaveSecretKeyInput').value;
    
    if (!publicKey || !secretKey) {
        showFlutterwaveToast('Please enter both Public and Secret keys first', 'warning');
        return;
    }
    
    fetch('{{ route("admin.payment-providers.verify-environment") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'flutterwave' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = data.is_production ? 
                '🔴 PRODUCTION environment detected - Real payments will be processed' : 
                '🔵 SANDBOX environment detected - Test mode active';
            showFlutterwaveToast(message, data.is_production ? 'warning' : 'success');
            
            const status = flutterwaveCurrentState;
            status.environment = data.is_production ? 'production' : 'sandbox';
            updateFlutterwaveEnvironmentStatus(status);
        } else {
            showFlutterwaveToast('Environment verification failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showFlutterwaveToast('Verification failed: ' + error.message, 'error');
    });
}

// Show connection result
function showFlutterwaveConnectionResult(success, message, details = null) {
    const resultDiv = document.getElementById('flutterwaveConnectionResult');
    const contentDiv = document.getElementById('flutterwaveConnectionResultContent');
    
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
                ${success ? '<div class="mt-3 p-2 rounded text-sm" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;"><i class="fas fa-check-circle mr-1"></i> Your Flutterwave integration is properly configured and ready to accept payments.</div>' : ''}
            </div>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
}

// Close connection result
function closeFlutterwaveConnectionResult() {
    document.getElementById('flutterwaveConnectionResult').classList.add('hidden');
}

// Show config summary
function showFlutterwaveConfigSummary() {
    const publicKey = document.getElementById('flutterwavePublicKeyInput').value;
    const secretKey = document.getElementById('flutterwaveSecretKeyInput').value;
    const encryptionKey = document.getElementById('flutterwaveEncryptionKeyInput').value;
    const enabled = document.getElementById('flutterwaveEnabledToggle').checked;
    const environment = document.querySelector('input[name="environment"]:checked').value;
    
    document.getElementById('flutterwaveSummaryPublicKey').textContent = publicKey ? 
        '••••••••' + publicKey.slice(-4) : 'Not set';
    document.getElementById('flutterwaveSummarySecretKey').textContent = secretKey ? 
        '••••••••' + secretKey.slice(-4) : 'Not set';
    document.getElementById('flutterwaveSummaryEncryptionKey').textContent = encryptionKey ? 
        '••••••••' + encryptionKey.slice(-4) : 'Not set';
    document.getElementById('flutterwaveSummaryEnvironment').textContent = environment === 'production' ? 'Production' : 'Sandbox';
    document.getElementById('flutterwaveSummaryStatus').textContent = enabled ? 'Enabled' : 'Disabled';
    
    document.getElementById('flutterwaveConfigSummary').classList.remove('hidden');
}

// Hide config summary
function hideFlutterwaveConfigSummary() {
    document.getElementById('flutterwaveConfigSummary').classList.add('hidden');
}

// Clear form
function clearFlutterwaveForm() {
    if (confirm('Clear all form fields?')) {
        document.getElementById('flutterwavePublicKeyInput').value = '';
        document.getElementById('flutterwaveSecretKeyInput').value = '';
        document.getElementById('flutterwaveEncryptionKeyInput').value = '';
        document.getElementById('flutterwaveEnabledToggle').checked = false;
        document.getElementById('flutterwaveSandboxRadio').checked = true;
        
        const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateFlutterwaveToggleState(false, toggleDot, toggleBg);
        
        updateFlutterwaveEnvironmentRadioStyle();
        onFlutterwaveEnabledChange();
        validateFlutterwavePublicKey();
        validateFlutterwaveSecretKey();
        
        showFlutterwaveToast('Form cleared', 'info');
    }
}

// Toggle visibility
function toggleFlutterwaveVisibility(inputId) {
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

// Validate Public Key
function validateFlutterwavePublicKey() {
    const input = document.getElementById('flutterwavePublicKeyInput');
    const validation = document.getElementById('flutterwavePublicKeyValidation');
    const icon = document.getElementById('flutterwavePublicKeyStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Public Key is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (!value.startsWith('FLWPUBK-')) {
        validation.textContent = 'Public Key must start with FLWPUBK-';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (value.length < 20) {
        validation.textContent = 'Public Key appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ Public Key format valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate Secret Key
function validateFlutterwaveSecretKey() {
    const input = document.getElementById('flutterwaveSecretKeyInput');
    const validation = document.getElementById('flutterwaveSecretKeyValidation');
    const icon = document.getElementById('flutterwaveSecretKeyStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Secret Key is required';
        validation.style.color = '#dc3545';
        icon.innerHTML = '<i class="fas fa-times-circle text-xs" style="color: #dc3545;"></i>';
        return false;
    }
    
    if (!value.startsWith('FLWSECK-')) {
        validation.textContent = 'Secret Key must start with FLWSECK-';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    if (value.length < 20) {
        validation.textContent = 'Secret Key appears too short';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return false;
    }
    
    validation.textContent = '✓ Secret Key format valid';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// Validate Encryption Key (optional)
function validateFlutterwaveEncryptionKey() {
    const input = document.getElementById('flutterwaveEncryptionKeyInput');
    const validation = document.getElementById('flutterwaveEncryptionKeyValidation');
    const icon = document.getElementById('flutterwaveEncryptionKeyStatusIcon');
    
    const value = input.value.trim();
    
    if (!value) {
        validation.textContent = 'Optional - leave empty if not needed';
        validation.style.color = 'var(--text-secondary)';
        icon.innerHTML = '<i class="fas fa-circle text-xs" style="color: var(--text-secondary);"></i>';
        return true;
    }
    
    if (value.length > 0 && !value.startsWith('FLWSECK-')) {
        validation.textContent = 'Warning: Encryption key should start with FLWSECK-';
        validation.style.color = '#ffc107';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-xs" style="color: #ffc107;"></i>';
        return true;
    }
    
    validation.textContent = '✓ Encryption key configured';
    validation.style.color = '#28a745';
    icon.innerHTML = '<i class="fas fa-check-circle text-xs" style="color: #28a745;"></i>';
    return true;
}

// On enabled change
function onFlutterwaveEnabledChange() {
    const enabled = document.getElementById('flutterwaveEnabledToggle').checked;
    const statusText = document.getElementById('flutterwaveEnabledStatusText');
    
    if (enabled) {
        statusText.textContent = 'Flutterwave will be enabled for payment processing';
    } else {
        statusText.textContent = 'Flutterwave will be disabled';
    }
}

// On environment change
function onFlutterwaveEnvironmentChange() {
    updateFlutterwaveEnvironmentRadioStyle();
    updateFlutterwaveEnvironmentStatus(flutterwaveCurrentState);
}

// Set up form validation
function setupFlutterwaveFormValidation() {
    const form = document.getElementById('flutterwaveConfigForm');
    const submitBtn = document.getElementById('flutterwaveSubmitBtn');
    
    form.addEventListener('input', () => {
        const publicKeyValid = validateFlutterwavePublicKey();
        const secretKeyValid = validateFlutterwaveSecretKey();
        submitBtn.disabled = !(publicKeyValid && secretKeyValid);
    });
}

// Copy webhook URL
function copyFlutterwaveWebhookUrl() {
    const urlElement = document.getElementById('flutterwaveWebhookUrl');
    const url = urlElement.textContent;
    
    navigator.clipboard.writeText(url).then(() => {
        showFlutterwaveToast('Webhook URL copied to clipboard', 'success');
    }).catch(() => {
        showFlutterwaveToast('Failed to copy URL', 'error');
    });
}

// Show toast notification
function showFlutterwaveToast(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#F97316'
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
function forceFlutterwaveStatusRefresh() {
    loadFlutterwaveInitialStatus();
    showFlutterwaveToast('Status refreshed successfully', 'success');
}

// Fallback to form status
function fallbackToFlutterwaveFormStatus() {
    const publicKey = document.getElementById('flutterwavePublicKeyInput').value.trim();
    const secretKey = document.getElementById('flutterwaveSecretKeyInput').value.trim();
    const enabled = document.getElementById('flutterwaveEnabledToggle').checked;
    
    const fallbackStatus = {
        enabled: enabled,
        configured: !!(publicKey && secretKey),
        source: 'fallback'
    };
    
    updateFlutterwaveStatusDisplay(fallbackStatus);
}

// Update form based on status
function updateFlutterwaveFormBasedOnStatus(status) {
    if (status.source === 'cache_expected' || status.source === 'cache_actual') {
        return;
    }
    
    const enabledToggle = document.getElementById('flutterwaveEnabledToggle');
    if (enabledToggle.checked !== status.enabled) {
        enabledToggle.checked = status.enabled;
        const toggleDot = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-dot');
        const toggleBg = document.querySelector('#flutterwaveEnabledToggle').closest('.relative').querySelector('.toggle-bg');
        updateFlutterwaveToggleState(status.enabled, toggleDot, toggleBg);
        onFlutterwaveEnabledChange();
    }
}

// Retry failed update
function retryFlutterwaveUpdate() {
    fetch('{{ route("admin.payment-providers.retry-update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ provider: 'flutterwave' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showFlutterwaveToast('Update retry initiated', 'success');
            startFlutterwaveStatusPolling();
        } else {
            showFlutterwaveToast(data.message || 'Retry failed', 'error');
        }
    })
    .catch(error => {
        showFlutterwaveToast('Retry failed: ' + error.message, 'error');
    });
}

// View error details
function viewErrorDetails() {
    showFlutterwaveToast('Error details functionality would show detailed logs', 'info');
}
</script>

<style>
/* Flutterwave-specific styles */
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
    box-shadow: 0 6px 20px rgba(249, 115, 22, 0.3);
}

input:focus, select:focus {
    outline: none;
    border-color: #F97316 !important;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
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