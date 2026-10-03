@php
    // Safe access to Africa's Talking config — NEVER fall back to a hardcoded API key
    $atConfig   = config('sms.providers.africastalking', []);
    $isEnabled  = filter_var($atConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Role: developers can edit; super-admins get read-only access.
    $isDeveloper = $isDeveloper ?? false;

    // Provider metadata from the controller (already resolved)
    $providerMeta      = $providers['africastalking'] ?? [];
    $effectiveSenderId = $providerMeta['effective_sender_id'] ?? null;
    $senderIdSource    = $providerMeta['sender_id_source']    ?? 'fallback';
    $providerSenderId  = $atConfig['sender_id'] ?? '';   // developer's per-provider value

    // old() first (for validation failures), then saved config, then empty
    $currentApiKey   = old('api_key',   $atConfig['api_key']   ?? '');
    $currentUsername = old('username',  $atConfig['username']  ?? 'sandbox');
    // Sender ID: prefer what the developer typed / saved, else inherit the global default
    $currentSenderId = old('sender_id', $providerSenderId ?: ($effectiveSenderId ?? ''));

    // Environment derivation (username === 'sandbox' → sandbox)
    $isSandbox = strtolower(trim($currentUsername)) === 'sandbox';

    // Is the displayed sender ID inherited from the admin's global setting?
    $isInheritedFromGlobal = (
        $senderIdSource === 'global'
        && empty($providerSenderId)
        && !empty($effectiveSenderId)
    );
@endphp

<form action="{{ route('admin.sms-providers.configure') }}"
      method="POST"
      class="space-y-6 provider-form"
      id="africastalkingConfigForm"
      data-provider="africastalking">
    @csrf
    <input type="hidden" name="provider" value="africastalking">

    <!-- Provider Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div class="flex-1">
            <h4 class="text-lg font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-globe-africa mr-2" style="color: #00A859;"></i>
                Africa's Talking Configuration
                @if(!$isDeveloper)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full flex items-center"
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-lock mr-1"></i> Read-Only
                    </span>
                @endif
            </h4>
            <p class="text-sm" style="color: var(--text-secondary);">
                @if($isDeveloper)
                    Configure your Africa's Talking SMS gateway settings
                @else
                    View your Africa's Talking SMS gateway configuration
                @endif
            </p>
        </div>
        <div class="flex items-center">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox"
                       name="enabled"
                       value="1"
                       class="sr-only peer toggle-provider-checkbox"
                       id="africastalkingEnabledToggle"
                       data-provider="africastalking"
                       {{ $isEnabled ? 'checked' : '' }}
                       {{ !$isDeveloper ? 'disabled' : '' }}>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-500
                       {{ !$isDeveloper ? 'opacity-50 cursor-not-allowed' : '' }}"></div>
                <span class="ml-3 text-sm font-medium" id="africastalkingToggleStatus" style="color: var(--text-primary);">
                    {{ $isEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- EFFECTIVE SENDER ID BANNER                                -->
    <!-- ========================================================== -->
    <div class="p-4 rounded-lg mb-6"
         style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-comment-dots mt-1 mr-3 text-lg" style="color: var(--info);"></i>
            <div class="flex-1">
                <p class="text-xs font-medium mb-1" style="color: var(--info);">
                    Effective Sender ID (used when Africa's Talking sends SMS)
                </p>
                <p class="text-lg font-mono font-semibold" style="color: var(--text-primary);">
                    {{ $effectiveSenderId ?: '— not set —' }}
                </p>

                @if($senderIdSource === 'provider')
                    <p class="text-xs mt-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-user-cog mr-1" style="color: var(--primary);"></i>
                        Set by a developer on this page
                    </p>
                @elseif($senderIdSource === 'global')
                    <p class="text-xs mt-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-globe mr-1" style="color: var(--info);"></i>
                        Inherited from
                        <a href="{{ route('admin.system-settings.edit') }}#general"
                           class="text-blue-500 hover:underline ml-1 font-medium">
                            System Settings
                        </a>
                        @if($isDeveloper)
                            — save the form below to pin a provider-specific value.
                        @endif
                    </p>
                @else
                    <p class="text-xs mt-2 flex items-center" style="color: var(--warning);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Sender ID is <strong>optional</strong> for Africa's Talking.
                        Messages will be sent from the shared short code when no Sender ID is set.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Configuration Status -->
    <div id="africastalkingStatus" class="p-4 rounded-lg mb-6 border-l-4" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--info); display: none;">
        <!-- Status will be dynamically loaded -->
    </div>

    <!-- Configuration Fields -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="africastalkingConfigFields">

        <!-- API Key -->
        <div class="form-group">
            <label for="africastalking_api_key" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                API Key <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <div class="relative">
                <input type="password"
                       id="africastalking_api_key"
                       name="api_key"
                       value="{{ $currentApiKey }}"
                       class="form-input w-full p-3 pr-10 rounded-lg config-field"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="{{ $isDeveloper ? 'atsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' : '••••••••••••••••' }}"
                       autocomplete="off"
                       minlength="10"
                       pattern="^atsk_[a-fA-F0-9]+$"
                       title="Africa's Talking API key must start with 'atsk_' followed by alphanumeric characters"
                       {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                       {{ !$isDeveloper ? 'readonly' : '' }}>
                @if($isDeveloper)
                    <button type="button"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center toggle-password"
                            data-target="africastalking_api_key">
                        <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                    </button>
                @endif
            </div>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                @if($isDeveloper)
                    Get your API key from Africa's Talking dashboard
                @else
                    Managed by developers — contact them to change
                @endif
            </p>
            <div id="apiKeyValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="apiKeyError"></span>
                </p>
            </div>
        </div>

        <!-- Username -->
        <div class="form-group">
            <label for="africastalking_username" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Username <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <input type="text"
                   id="africastalking_username"
                   name="username"
                   value="{{ $currentUsername }}"
                   class="form-input w-full p-3 rounded-lg config-field"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="sandbox"
                   autocomplete="off"
                   pattern="^[a-zA-Z0-9_-]+$"
                   title="Username can only contain letters, numbers, hyphens, and underscores"
                   {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <div class="flex items-center justify-between mt-1">
                <p class="text-xs" style="color: var(--text-secondary);">
                    Use 'sandbox' for testing, your app name for production
                </p>
                <span class="text-xs font-medium" id="usernameCounter" style="color: var(--text-secondary);">
                    {{ strlen($currentUsername) }}/30
                </span>
            </div>
            <div id="usernameValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="usernameError"></span>
                </p>
            </div>
        </div>

        <!-- Sender ID -->
        <div class="md:col-span-2 form-group">
            <label for="africastalking_sender_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Sender ID (Optional)
            </label>
            <input type="text"
                   id="africastalking_sender_id"
                   name="sender_id"
                   value="{{ $currentSenderId }}"
                   class="form-input w-full p-3 rounded-lg config-field"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="Optional sender ID"
                   autocomplete="off"
                   maxlength="11"
                   pattern="[A-Za-z0-9]+"
                   title="Only alphanumeric characters allowed"
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <div class="flex items-center justify-between mt-1">
                <p class="text-xs" style="color: var(--text-secondary);">
                    Alphanumeric, max 11 characters (optional)
                </p>
                <span class="text-xs font-medium" id="senderIdCounter" style="color: var(--text-secondary);">
                    {{ strlen($currentSenderId) }}/11
                </span>
            </div>

            {{-- Inherited-from-global hint --}}
            @if($isInheritedFromGlobal)
                <div class="mt-2 p-2 rounded text-xs flex items-start"
                     style="background-color: rgba(var(--info-rgb), 0.05); color: var(--text-secondary);">
                    <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--info);"></i>
                    <span>
                        This field is pre-filled with the <strong>global default</strong>
                        (<span class="font-mono">{{ $effectiveSenderId }}</span>)
                        from
                        <a href="{{ route('admin.system-settings.edit') }}#general"
                           class="text-blue-500 hover:underline">System Settings</a>.
                        @if($isDeveloper)
                            Save this form to pin a provider-specific value that overrides the global default.
                        @endif
                    </span>
                </div>
            @elseif($senderIdSource === 'fallback' && empty($providerSenderId))
                <div class="mt-2 p-2 rounded text-xs flex items-start"
                     style="background-color: rgba(var(--warning-rgb), 0.05); color: var(--text-secondary);">
                    <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--warning);"></i>
                    <span>
                        Sender ID is optional for Africa's Talking.
                        @if($isDeveloper)
                            Leave blank to use the shared short code, or set a value here to override.
                        @endif
                    </span>
                </div>
            @endif

            <div id="senderIdValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="senderIdError"></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Environment Indicator -->
    <div id="environmentIndicator" class="p-4 rounded-lg transition-colors duration-300"
         style="background-color: rgba(var(--{{ $isSandbox ? 'warning' : 'success' }}-rgb), 0.05); border: 1px solid rgba(var(--{{ $isSandbox ? 'warning' : 'success' }}-rgb), 0.2);">
        <div class="flex items-center mb-1">
            <i class="fas {{ $isSandbox ? 'fa-exclamation-triangle' : 'fa-check-circle' }} mr-2"
               id="environmentIcon"
               style="color: var(--{{ $isSandbox ? 'warning' : 'success' }});"></i>
            <span class="text-sm font-medium" id="environmentStatusText" style="color: var(--text-primary);">
                Current Environment:
                <span id="environmentStatus" class="font-bold" style="color: var(--{{ $isSandbox ? 'warning' : 'success' }});">
                    {{ $isSandbox ? 'SANDBOX (Testing)' : 'PRODUCTION' }}
                </span>
            </span>
        </div>
        <p id="environmentDescription" class="text-xs mt-1" style="color: var(--text-secondary);">
            {{ $isSandbox
                ? 'You are using sandbox mode. Switch to your production app name for live SMS.'
                : 'You are in production mode. All SMS will be sent to real phone numbers.' }}
        </p>
    </div>

    <!-- Current Configuration Summary -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-globe-africa mr-2" style="color: #00A859;"></i> Current Configuration
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Status:</span>
                    <span class="font-medium"
                          style="{{ $isEnabled ? 'color: var(--success);' : 'color: var(--danger);' }}">
                        {{ $isEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Username:</span>
                    <span class="font-medium" id="currentUsernameDisplay" style="color: var(--text-primary);">
                        {{ $currentUsername ?: 'sandbox' }}
                    </span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">API Key:</span>
                    <span class="font-medium"
                          style="{{ !empty($currentApiKey) ? 'color: var(--success);' : 'color: var(--danger);' }}">
                        {{ !empty($currentApiKey) ? 'Configured' : 'Not set' }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">Sender ID:</span>
                    <span class="font-medium flex items-center gap-2" id="currentSenderIdDisplay" style="color: var(--text-primary);">
                        {{ $currentSenderId ?: 'Not set' }}
                        @if($senderIdSource === 'provider')
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                  title="Set by developer on this page">
                                provider
                            </span>
                        @elseif($senderIdSource === 'global')
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                  title="Inherited from System Settings">
                                global
                            </span>
                        @else
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                  title="Optional — uses shared short code if unset">
                                unset
                            </span>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Tips -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: #00A859;">
            <i class="fas fa-globe-africa mr-2"></i> Configuration Tips
        </h4>
        <ul class="text-xs space-y-1" style="color: var(--success);">
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Register at
                <a href="https://africastalking.com" target="_blank" rel="noopener"
                   class="underline hover:opacity-80 ml-1" style="color: var(--success);">Africa's Talking</a>
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Use 'sandbox' username for testing environment
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Sender ID must be registered and approved
            </li>
            @if($isDeveloper)
                <li class="flex items-start">
                    <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                    A Sender ID set here <strong>overrides</strong> the global default from System Settings.
                </li>
            @else
                <li class="flex items-start">
                    <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                    To change the default Sender ID, use
                    <a href="{{ route('admin.system-settings.edit') }}#general"
                       class="underline hover:opacity-80 ml-1" style="color: var(--success);">System Settings</a>.
                </li>
            @endif
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                API keys start with 'atsk_' followed by alphanumeric characters
            </li>
        </ul>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pt-6 border-t gap-4" style="border-color: var(--border-color);">
        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="test-connection flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="africastalking"
                        id="africastalkingTestConnectionBtn">
                    <i class="fas fa-plug mr-2"></i> Test Connection
                </button>

                <button type="button"
                        class="send-test-sms flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="africastalking"
                        id="africastalkingSendTestSmsBtn">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test SMS
                </button>
            @endif

            <button type="button"
                    class="refresh-status flex items-center px-4 py-2 rounded-lg btn-secondary"
                    data-provider="africastalking"
                    id="africastalkingRefreshStatusBtn">
                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="reset-config flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="africastalking"
                        id="africastalkingResetConfigBtn">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit"
                        class="save-config flex items-center px-4 py-2 rounded-lg btn-modern"
                        id="africastalkingSaveConfigBtn">
                    <i class="fas fa-save mr-2"></i> Save Configuration
                </button>
            @else
                <span class="text-xs flex items-center px-3 py-2 rounded-lg"
                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-lock mr-1"></i> Contact a developer to modify API credentials
                </span>
            @endif
        </div>
    </div>
</form>

<!-- Africa's Talking Test SMS Modal -->
@if($isDeveloper)
<div id="africastalkingTestSmsModal"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md"
         style="background-color: var(--card-bg); border-color: var(--border-color);">
        <div class="mt-3">
            <h3 class="text-lg font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-globe-africa mr-2" style="color: #00A859;"></i> Send Africa's Talking Test SMS
            </h3>

            <div class="mb-4">
                <label for="africastalking_test_phone" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Phone Number <span class="required">*</span>
                </label>
                <input type="text"
                       id="africastalking_test_phone"
                       class="form-input w-full p-3 rounded-lg"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="+233123456789"
                       value="{{ config('sms.test_phone', '+233595652410') }}"
                       required
                       pattern="^\+\d{10,15}$">
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Format: +XXXXXXXXXXX (E.164 format)</p>
            </div>

            <div class="mb-4">
                <label for="africastalking_test_message" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Test Message <span class="required">*</span>
                </label>
                <textarea id="africastalking_test_message"
                          class="form-textarea w-full p-3 rounded-lg"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                          placeholder="This is a test message from your Africa's Talking configuration"
                          rows="3"
                          required>Test SMS via Africa's Talking - Connection successful!</textarea>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <span id="africastalkingModalCharCount">0</span>/160 characters
                    </p>
                    <span class="text-xs font-medium" id="africastalkingCurrentSenderId" style="color: var(--info);">
                        From: <span id="dynamicAfricasTalkingSenderId">{{ $currentSenderId ?: 'Not set' }}</span>
                    </span>
                </div>
            </div>

            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <p class="text-xs flex items-start" style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i>
                    <span>Test SMS will be sent using your configured Sender ID and Africa's Talking credentials</span>
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        id="africastalkingCancelTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-secondary">
                    Cancel
                </button>
                <button type="button"
                        id="africastalkingConfirmSendTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-modern">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Connection Test Results -->
<div id="africastalkingConnectionTestResults" class="mt-4 hidden">
    <!-- Results will be dynamically loaded -->
</div>

@if ($errors->any())
<div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
    <div class="flex items-center">
        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
        <h4 class="text-sm font-semibold" style="color: var(--danger);">Configuration Errors</h4>
    </div>
    <ul class="mt-2 text-xs space-y-1" style="color: var(--danger);">
        @foreach ($errors->all() as $error)
        <li class="flex items-start">
            <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
            {{ $error }}
        </li>
        @endforeach
    </ul>
</div>
@endif

@if (session('success') && session('provider') == 'africastalking')
<div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
    <div class="flex items-center">
        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
        <h4 class="text-sm font-semibold" style="color: var(--success);">Success</h4>
    </div>
    <p class="mt-1 text-sm" style="color: var(--success);">{{ session('success') }}</p>
    @if(session('config_update_status') == 'processing')
    <div class="mt-2 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
        <p class="text-xs flex items-center" style="color: var(--info);">
            <i class="fas fa-sync-alt animate-spin mr-2"></i>
            Configuration is being updated in the background...
        </p>
    </div>
    @endif
</div>
@endif

<style>
.fa-globe-africa { color: #00A859 !important; }

.input-valid   { border-color: var(--success) !important; background-color: rgba(var(--success-rgb), 0.05) !important; }
.input-invalid { border-color: var(--danger)  !important; background-color: rgba(var(--danger-rgb), 0.05)  !important; }

#usernameCounter,
#senderIdCounter { transition: color 0.3s ease; }

.required { color: var(--danger) !important; }

.toggle-password {
    background: none; border: none; cursor: pointer; padding: 0;
    display: flex; align-items: center; justify-content: center; width: 40px;
}
.toggle-password:hover i { color: var(--primary); }

.status-indicator {
    display: inline-flex; align-items: center;
    padding: 0.25rem 0.75rem; border-radius: 9999px;
    font-size: 0.75rem; font-weight: 500; border: 1px solid transparent;
}
.status-active   { background-color: rgba(var(--success-rgb), 0.1);   color: var(--success);   border-color: rgba(var(--success-rgb), 0.3); }
.status-inactive { background-color: rgba(var(--warning-rgb), 0.1);   color: var(--warning);   border-color: rgba(var(--warning-rgb), 0.3); }
.status-disabled { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border-color: rgba(var(--secondary-rgb), 0.3); }

.config-status { transition: all 0.3s ease; }
.config-status.success { border-left-color: var(--success); }
.config-status.warning { border-left-color: var(--warning); }
.config-status.error   { border-left-color: var(--danger); }

.btn-loading { position: relative; color: transparent !important; }
.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px; height: 16px;
    top: 50%; left: 50%;
    margin-left: -8px; margin-top: -8px;
    border: 2px solid var(--text-primary);
    border-radius: 50%;
    border-right-color: transparent;
    animation: spin 0.75s linear infinite;
}

#africastalkingTestSmsModal { backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }

.form-group { margin-bottom: 1rem; }

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    #africastalkingTestSmsModal .relative { width: 95%; margin: 1rem auto; max-width: 95%; }
    .flex.justify-between { flex-direction: column; gap: 1rem; }
    .flex.flex-wrap { justify-content: space-between; width: 100%; }
}

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

::placeholder { color: var(--text-secondary); opacity: 0.7; }

:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

button:disabled, select:disabled, input:disabled, textarea:disabled {
    opacity: 0.5; cursor: not-allowed;
}
</style>

<script>
// Role flag for the partial — developers can write, others can't.
window.isDeveloper = {{ $isDeveloper ? 'true' : 'false' }};

// Whether the sender ID value shown is inherited from the global default.
window.africastalkingSenderIdInherited = {{ $isInheritedFromGlobal ? 'true' : 'false' }};

document.addEventListener('DOMContentLoaded', function () {
    initializeAfricasTalkingProvider();
});

let africasTalkingFormSubmitting = false;

function initializeAfricasTalkingProvider() {
    const provider        = 'africastalking';
    const toggleCheckbox  = document.getElementById('africastalkingEnabledToggle');
    const apiKeyInput     = document.getElementById('africastalking_api_key');
    const usernameInput   = document.getElementById('africastalking_username');
    const senderIdInput   = document.getElementById('africastalking_sender_id');
    const usernameCounter = document.getElementById('usernameCounter');
    const senderIdCounter = document.getElementById('senderIdCounter');
    const testBtn         = document.getElementById('africastalkingTestConnectionBtn');
    const sendTestBtn     = document.getElementById('africastalkingSendTestSmsBtn');
    const resetBtn        = document.getElementById('africastalkingResetConfigBtn');
    const refreshBtn      = document.getElementById('africastalkingRefreshStatusBtn');
    const form            = document.getElementById('africastalkingConfigForm');
    const saveBtn         = document.getElementById('africastalkingSaveConfigBtn');

    loadAfricasTalkingProviderStatus(provider);

    /* ---------- Username input ---------- */
    if (usernameInput && usernameCounter) {
        if (window.isDeveloper) {
            usernameInput.addEventListener('input', function () {
                usernameCounter.textContent = `${this.value.length}/30`;
                validateUsername(this.value);
                updateAfricasTalkingEnvironmentIndicator();
                updateCurrentConfigDisplay();
            });
            usernameInput.addEventListener('blur', function () {
                validateUsername(this.value, true);
                updateAfricasTalkingEnvironmentIndicator();
            });
            validateUsername(usernameInput.value);
        }
        usernameCounter.textContent = `${usernameInput.value.length}/30`;
        updateAfricasTalkingEnvironmentIndicator();
    }

    /* ---------- Sender ID input ---------- */
    if (senderIdInput && senderIdCounter) {
        if (window.isDeveloper) {
            senderIdInput.addEventListener('input', function () {
                senderIdCounter.textContent = `${this.value.length}/11`;
                validateAfricasTalkingSenderId(this.value);
                updateAfricasTalkingSenderIdDisplay();
                updateCurrentConfigDisplay();
                syncModalSenderId();
            });
            senderIdInput.addEventListener('blur', function () {
                validateAfricasTalkingSenderId(this.value, true);
            });
            validateAfricasTalkingSenderId(senderIdInput.value);
        }
        senderIdCounter.textContent = `${senderIdInput.value.length}/11`;
        updateAfricasTalkingSenderIdDisplay();
    }

    /* ---------- API Key input ---------- */
    if (apiKeyInput && window.isDeveloper) {
        apiKeyInput.addEventListener('blur', () => validateApiKey(apiKeyInput.value, true));
        apiKeyInput.addEventListener('input', () => validateApiKey(apiKeyInput.value));
        validateApiKey(apiKeyInput.value);
    }

    /* ---------- Password toggle (developer only) ---------- */
    if (window.isDeveloper) {
        document.querySelectorAll('#africastalkingConfigForm .toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.getAttribute('data-target'));
                const icon  = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                    icon.style.color = 'var(--primary)';
                } else {
                    input.type = 'password';
                    icon.className = 'fas fa-eye';
                    icon.style.color = 'var(--text-secondary)';
                }
            });
        });
    }

    /* ---------- Toggle (developer only) ---------- */
    if (toggleCheckbox && window.isDeveloper) {
        toggleCheckbox.addEventListener('change', function () {
            updateAfricasTalkingUIToggleState(this.checked);
            toggleProvider(provider, this.checked, this);
        });
    }

    /* ---------- Action buttons ---------- */
    testBtn?.addEventListener('click', function () { testConnection(provider, this); });
    sendTestBtn?.addEventListener('click', function () { openAfricasTalkingTestSmsModal(provider); });
    refreshBtn?.addEventListener('click', function () {
        loadAfricasTalkingProviderStatus(provider);
        showToast('Status refreshed', 'success');
    });
    resetBtn?.addEventListener('click', function () { resetConfiguration(provider, this); });

    /* ---------- Form submit (developer only) ---------- */
    if (form && window.isDeveloper) {
        form.addEventListener('submit', function (e) {
            if (africasTalkingFormSubmitting) { e.preventDefault(); return; }

            const apiOk      = validateApiKey(apiKeyInput?.value || '', true);
            const usernameOk = validateUsername(usernameInput?.value || '', true);
            const senderOk   = validateAfricasTalkingSenderId(senderIdInput?.value || '', true);

            if (!apiOk || !usernameOk || !senderOk) {
                e.preventDefault();
                showToast('Please fix validation errors before saving', 'error');
                return;
            }

            // Confirm production switch
            const originalUsername = @json($currentUsername);
            const newUsername      = (usernameInput?.value || '').trim();

            if (originalUsername.toLowerCase() === 'sandbox' && newUsername.toLowerCase() !== 'sandbox') {
                if (!confirm('You are switching from sandbox to production mode. All SMS will be sent to real numbers. Continue?')) {
                    e.preventDefault();
                    return;
                }
            }

            africasTalkingFormSubmitting = true;

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            }

            showToast("Updating Africa's Talking configuration...", 'info');

            setTimeout(() => {
                africasTalkingFormSubmitting = false;
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Configuration';
                }
            }, 15000);
        });
    }

    if (window.isDeveloper) {
        initializeAfricasTalkingTestSmsModal();
    }

    if (toggleCheckbox) {
        updateAfricasTalkingUIToggleState(toggleCheckbox.checked);
    }
}

/* ============================================================
 |  Field validation
 * ============================================================ */
function validateAfricasTalkingSenderId(senderId, showErrors = false) {
    const input = document.getElementById('africastalking_sender_id');
    const wrap  = document.getElementById('senderIdValidation');
    const err   = document.getElementById('senderIdError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    if (senderId.trim()) {
        if (senderId.length > 11) { valid = false; msg = 'Sender ID cannot exceed 11 characters'; }
        else if (!/^[A-Za-z0-9]+$/.test(senderId)) { valid = false; msg = 'Only letters and numbers allowed (no spaces or special characters)'; }
    }
    // Sender ID is optional for Africa's Talking — no "required" check.

    toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors);
    return valid;
}

function validateUsername(username, showErrors = false) {
    const input = document.getElementById('africastalking_username');
    const wrap  = document.getElementById('usernameValidation');
    const err   = document.getElementById('usernameError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    if (username.length > 30) { valid = false; msg = 'Username cannot exceed 30 characters'; }
    else if (username && !/^[a-zA-Z0-9_-]+$/.test(username)) { valid = false; msg = 'Only letters, numbers, hyphens, and underscores allowed'; }
    else {
        const isEnabled = document.getElementById('africastalkingEnabledToggle')?.checked;
        if (isEnabled && !username.trim()) { valid = false; msg = 'Username is required when provider is enabled'; }
    }

    toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors);
    return valid;
}

function validateApiKey(apiKey, showErrors = false) {
    const input = document.getElementById('africastalking_api_key');
    const wrap  = document.getElementById('apiKeyValidation');
    const err   = document.getElementById('apiKeyError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    if (apiKey.length > 0 && apiKey.length < 10) { valid = false; msg = 'API Key must be at least 10 characters'; }
    else if (apiKey && !/^atsk_/.test(apiKey)) { valid = false; msg = 'API Key must start with "atsk_"'; }
    else {
        const isEnabled = document.getElementById('africastalkingEnabledToggle')?.checked;
        if (isEnabled && !apiKey.trim()) { valid = false; msg = 'API Key is required when provider is enabled'; }
    }

    toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors);
    return valid;
}

function toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors) {
    if (!wrap || !err) return;
    const focused = document.activeElement === input;

    if (!valid && (showErrors || focused)) {
        wrap.classList.remove('hidden');
        err.textContent = msg;
        input.classList.add('input-invalid');
        input.classList.remove('input-valid');
    } else if (valid && input.value.trim()) {
        wrap.classList.add('hidden');
        input.classList.add('input-valid');
        input.classList.remove('input-invalid');
    } else {
        wrap.classList.add('hidden');
        input.classList.remove('input-valid', 'input-invalid');
    }
}

/* ============================================================
 |  Live summary + environment
 * ============================================================ */
function updateCurrentConfigDisplay() {
    const usernameInput = document.getElementById('africastalking_username');
    const senderInput   = document.getElementById('africastalking_sender_id');
    const usernameDisp  = document.getElementById('currentUsernameDisplay');
    const senderDisp    = document.getElementById('currentSenderIdDisplay');

    if (usernameInput && usernameDisp) usernameDisp.textContent = usernameInput.value || 'sandbox';

    if (senderInput && senderDisp) {
        // Preserve the source badge if it exists
        const existingBadge = senderDisp.querySelector('span');
        const badgeMarkup = existingBadge ? existingBadge.outerHTML : '';
        senderDisp.innerHTML = (senderInput.value || 'Not set') + ' ' + badgeMarkup;
    }
}

function updateAfricasTalkingSenderIdDisplay() {
    const senderIdInput   = document.getElementById('africastalking_sender_id');
    const dynamicSenderId = document.getElementById('dynamicAfricasTalkingSenderId');
    if (senderIdInput && dynamicSenderId) {
        dynamicSenderId.textContent = senderIdInput.value || 'Not set';
    }
}

function updateAfricasTalkingEnvironmentIndicator() {
    const usernameInput        = document.getElementById('africastalking_username');
    const environmentIndicator = document.getElementById('environmentIndicator');
    const environmentIcon      = document.getElementById('environmentIcon');
    const environmentStatus    = document.getElementById('environmentStatus');
    const environmentDesc      = document.getElementById('environmentDescription');

    if (!usernameInput || !environmentIndicator) return;

    const isSandbox = usernameInput.value.trim().toLowerCase() === 'sandbox';

    environmentStatus.textContent = isSandbox ? 'SANDBOX (Testing)' : 'PRODUCTION';
    environmentStatus.style.color = isSandbox ? 'var(--warning)' : 'var(--success)';
    environmentIcon.className    = isSandbox ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
    environmentIcon.style.color  = isSandbox ? 'var(--warning)' : 'var(--success)';
    environmentDesc.textContent  = isSandbox
        ? 'You are using sandbox mode. Switch to your production app name for live SMS.'
        : 'You are in production mode. All SMS will be sent to real phone numbers.';

    environmentIndicator.style.backgroundColor = isSandbox
        ? 'rgba(var(--warning-rgb), 0.05)'
        : 'rgba(var(--success-rgb), 0.05)';
    environmentIndicator.style.borderColor = isSandbox
        ? 'rgba(var(--warning-rgb), 0.2)'
        : 'rgba(var(--success-rgb), 0.2)';
}

function syncModalSenderId() {
    const senderIdInput   = document.getElementById('africastalking_sender_id');
    const dynamicSenderId = document.getElementById('dynamicAfricasTalkingSenderId');
    const messageInput    = document.getElementById('africastalking_test_message');
    const charCount       = document.getElementById('africastalkingModalCharCount');

    if (!senderIdInput) return;

    if (dynamicSenderId) dynamicSenderId.textContent = senderIdInput.value || 'Not set';

    if (messageInput && !messageInput.dataset.userModified) {
        const sender = senderIdInput.value || 'your brand';
        messageInput.value = `Test SMS via Africa's Talking from ${sender} - Connection successful!`;
        if (charCount) charCount.textContent = messageInput.value.length;
    }
}

/* ============================================================
 |  Provider status
 * ============================================================ */
function loadAfricasTalkingProviderStatus(provider) {
    const statusContainer = document.getElementById('africastalkingStatus');
    if (!statusContainer) return;

    statusContainer.innerHTML = `
        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-3" style="color: var(--secondary);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Loading Status...</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Checking Africa's Talking configuration</p>
                </div>
            </div>
            <span class="status-indicator status-disabled">Loading</span>
        </div>
    `;
    statusContainer.style.display = 'block';

    fetch(window.smsProviderConfig?.routes?.status || '/admin/sms-providers/status', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.providers[provider]) {
            updateAfricasTalkingProviderStatusUI(provider, data.data.providers[provider]);
        }
    })
    .catch(error => {
        console.error('Error loading Africa\'s Talking status:', error);
        statusContainer.innerHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">Status Check Failed</p>
                        <p class="text-xs" style="color: var(--danger);">Unable to load Africa's Talking status</p>
                    </div>
                </div>
                <span class="status-indicator status-inactive">Error</span>
            </div>
        `;
    });
}

function updateAfricasTalkingProviderStatusUI(provider, status) {
    const statusContainer = document.getElementById('africastalkingStatus');
    if (!statusContainer) return;

    let html = '', cls = 'config-status';

    if (!status.enabled) {
        html = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-globe-africa mr-3" style="color: var(--secondary);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Provider Disabled</p>
                        <p class="text-xs" style="color: var(--text-secondary);">Enable to configure and test</p>
                    </div>
                </div>
                <span class="status-indicator status-disabled">Disabled</span>
            </div>`;
    } else if (!status.configured) {
        html = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-globe-africa mr-3" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--warning);">Incomplete Configuration</p>
                        <p class="text-xs" style="color: var(--warning);">Missing: ${status.missing_configuration?.join(', ') || 'Required fields'}</p>
                    </div>
                </div>
                <span class="status-indicator status-inactive">Incomplete</span>
            </div>`;
        cls = 'config-status warning';
    } else {
        html = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-globe-africa mr-3" style="color: var(--success);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--success);">Ready to Send SMS</p>
                        <p class="text-xs" style="color: var(--success);">Africa's Talking is properly configured and enabled</p>
                    </div>
                </div>
                <span class="status-indicator status-active">Active</span>
            </div>`;
        cls = 'config-status success';
    }

    statusContainer.innerHTML = html;
    statusContainer.className = cls;
    statusContainer.style.display = 'block';
}

/* ============================================================
 |  Toggle UI
 * ============================================================ */
function updateAfricasTalkingUIToggleState(isEnabled) {
    const toggleStatus = document.getElementById('africastalkingToggleStatus');
    const apiKeyInput  = document.getElementById('africastalking_api_key');
    const usernameInput= document.getElementById('africastalking_username');
    const testBtn      = document.getElementById('africastalkingTestConnectionBtn');
    const sendTestBtn  = document.getElementById('africastalkingSendTestSmsBtn');

    if (toggleStatus) {
        toggleStatus.textContent = isEnabled ? 'Enabled' : 'Disabled';
        toggleStatus.style.color = isEnabled ? 'var(--success)' : 'var(--secondary)';
    }

    if (window.isDeveloper) {
        [apiKeyInput, usernameInput].forEach(field => {
            if (!field) return;
            if (isEnabled) field.setAttribute('required', 'required');
            else           field.removeAttribute('required');
        });

        if (apiKeyInput)   validateApiKey(apiKeyInput.value, true);
        if (usernameInput) validateUsername(usernameInput.value, true);
    }

    if (testBtn)     testBtn.disabled = !isEnabled || !window.isDeveloper;
    if (sendTestBtn) sendTestBtn.disabled = !isEnabled || !window.isDeveloper;

    updateAfricasTalkingEnvironmentIndicator();
    loadAfricasTalkingProviderStatus('africastalking');
}

/* ============================================================
 |  Test SMS modal (developer only)
 * ============================================================ */
function initializeAfricasTalkingTestSmsModal() {
    const modal        = document.getElementById('africastalkingTestSmsModal');
    const cancelBtn    = document.getElementById('africastalkingCancelTestSms');
    const confirmBtn   = document.getElementById('africastalkingConfirmSendTestSms');
    const messageInput = document.getElementById('africastalking_test_message');
    const charCount    = document.getElementById('africastalkingModalCharCount');
    const phoneInput   = document.getElementById('africastalking_test_phone');

    if (!modal) return;

    syncModalSenderId();

    if (messageInput && charCount) {
        messageInput.addEventListener('input', function () {
            this.dataset.userModified = 'true';
            charCount.textContent = this.value.length;
            charCount.style.color = this.value.length > 160 ? 'var(--danger)' : 'var(--text-secondary)';
        });
        charCount.textContent = messageInput.value.length;
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const value = this.value;
            if (value && !value.startsWith('+')) {
                this.value = '+' + value.replace(/[^\d]/g, '');
            } else {
                this.value = '+' + value.substring(1).replace(/[^\d]/g, '');
            }
        });
    }

    cancelBtn?.addEventListener('click', () => modal.classList.add('hidden'));

    confirmBtn?.addEventListener('click', function () {
        const phone   = document.getElementById('africastalking_test_phone').value;
        const message = document.getElementById('africastalking_test_message').value;

        if (!phone) { showToast('Please enter a phone number', 'error'); return; }
        if (!/^\+\d{10,15}$/.test(phone)) { showToast('Please enter a valid phone number in E.164 format', 'error'); return; }
        if (!message) { showToast('Please enter a message', 'error'); return; }
        if (message.length > 160) { showToast('Message cannot exceed 160 characters', 'error'); return; }

        sendTestSMS('africastalking', phone, message, this);
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) modal.classList.add('hidden');
    });
}

function openAfricasTalkingTestSmsModal(provider) {
    const modal         = document.getElementById('africastalkingTestSmsModal');
    const confirmBtn    = document.getElementById('africastalkingConfirmSendTestSms');
    const dynamicSender = document.getElementById('dynamicAfricasTalkingSenderId');
    const senderIdInput = document.getElementById('africastalking_sender_id');

    if (!modal || !confirmBtn) return;

    if (dynamicSender && senderIdInput) {
        dynamicSender.textContent = senderIdInput.value || 'Not set';
    }

    confirmBtn.setAttribute('data-provider', provider);
    modal.classList.remove('hidden');
    document.getElementById('africastalking_test_phone')?.focus();
}

/* ============================================================
 |  Connection test results
 * ============================================================ */
function showAfricasTalkingConnectionTestResults(provider, result) {
    const container = document.getElementById('africastalkingConnectionTestResults');
    if (!container) return;

    const html = result.success
        ? `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-globe-africa mr-2" style="color: var(--success);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--success);">Africa's Talking Connection Test Successful</h4>
                </div>
                <p class="mt-1 text-sm" style="color: var(--success);">${result.message}</p>
                ${result.details ? `
                <div class="mt-2 text-xs space-y-1" style="color: var(--success);">
                    <p><strong>Username:</strong> ${result.details.username || 'N/A'}</p>
                    <p><strong>Balance:</strong> ${result.details.balance || 'N/A'}</p>
                    <p><strong>Sender ID:</strong> ${result.details.sender_id || 'N/A'}</p>
                    <p><strong>Status:</strong> ${result.details.api_status || 'N/A'}</p>
                    ${result.details.response_time ? `<p><strong>Response Time:</strong> ${result.details.response_time}ms</p>` : ''}
                </div>` : ''}
            </div>`
        : `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-globe-africa mr-2" style="color: var(--danger);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--danger);">Africa's Talking Connection Test Failed</h4>
                </div>
                <p class="mt-1 text-sm" style="color: var(--danger);">${result.message}</p>
                ${result.details ? `
                <div class="mt-2 text-xs space-y-1" style="color: var(--danger);">
                    <p><strong>Error:</strong> ${result.details.error || 'Unknown error'}</p>
                    <p><strong>Status:</strong> ${result.details.api_status || 'N/A'}</p>
                    ${result.details.suggestion ? `<p><strong>Suggestion:</strong> ${result.details.suggestion}</p>` : ''}
                </div>` : ''}
            </div>`;

    container.innerHTML = html;
    container.classList.remove('hidden');
    setTimeout(() => container.classList.add('hidden'), 10000);
}

/* ============================================================
 |  Global exports
 * ============================================================ */
window.initializeAfricasTalkingProvider            = initializeAfricasTalkingProvider;
window.showAfricasTalkingConnectionTestResults     = showAfricasTalkingConnectionTestResults;
window.validateAfricasTalkingSenderId              = validateAfricasTalkingSenderId;
window.validateUsername                            = validateUsername;
window.validateApiKey                              = validateApiKey;
window.loadAfricasTalkingProviderStatus            = loadAfricasTalkingProviderStatus;
window.updateAfricasTalkingSenderIdDisplay         = updateAfricasTalkingSenderIdDisplay;
window.updateAfricasTalkingEnvironmentIndicator     = updateAfricasTalkingEnvironmentIndicator;
</script>