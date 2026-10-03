@php
    // Safe access to Arkesel config — never falls back to "PropertyReg"
    $arkeselConfig = config('sms.providers.arkesel', []);
    $isEnabled     = filter_var($arkeselConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Role: developers can edit; super-admins get read-only access.
    $isDeveloper = $isDeveloper ?? false;

    // Provider metadata from the controller (already resolved)
    $providerMeta       = $providers['arkesel'] ?? [];
    $effectiveSenderId  = $providerMeta['effective_sender_id'] ?? null;
    $senderIdSource     = $providerMeta['sender_id_source']    ?? 'fallback';
    $providerSenderId   = $arkeselConfig['sender_id'] ?? '';   // developer's per-provider value

    // What value should the input show?
    //   - old() if validation failed
    //   - the developer's per-provider value if set
    //   - otherwise, the effective (admin/global) value as a fallback
    $currentApiKey   = old('api_key',   $arkeselConfig['api_key']   ?? '');
    $currentSenderId = old('sender_id', $providerSenderId ?: ($effectiveSenderId ?? ''));
    $currentBaseUrl  = old('base_url',  $arkeselConfig['base_url']  ?? 'https://sms.arkesel.com');

    // Placeholder shown when there is no sender ID yet
    $senderIdPlaceholder = $currentSenderId ?: 'e.g. MyBrand';

    // Is the displayed value inherited from the admin's global setting?
    $isInheritedFromGlobal = (
        $senderIdSource === 'global'
        && empty($providerSenderId)
        && !empty($effectiveSenderId)
    );

    // Can the developer clear the sender ID to fall back to the global default?
    // Yes — but only if a global default exists. Otherwise clearing would leave
    // the provider with no sender, so we guide the user instead.
    $hasGlobalFallback = !empty($effectiveSenderId);
@endphp

<form action="{{ route('admin.sms-providers.configure') }}"
      method="POST"
      class="space-y-6 provider-form"
      id="arkeselConfigForm"
      data-provider="arkesel">
    @csrf
    <input type="hidden" name="provider" value="arkesel">

    <!-- Provider Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div class="flex-1">
            <h4 class="text-lg font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                Arkesel SMS Configuration
                @if(!$isDeveloper)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full flex items-center"
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-lock mr-1"></i> Read-Only
                    </span>
                @endif
            </h4>
            <p class="text-sm" style="color: var(--text-secondary);">
                @if($isDeveloper)
                    Configure your Arkesel SMS gateway settings
                @else
                    View your Arkesel SMS gateway configuration
                @endif
            </p>
        </div>
        <div class="flex items-center">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox"
                       name="enabled"
                       value="1"
                       class="sr-only peer toggle-provider-checkbox"
                       id="enabledToggle"
                       data-provider="arkesel"
                       {{ $isEnabled ? 'checked' : '' }}
                       {{ !$isDeveloper ? 'disabled' : '' }}>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-500
                       {{ !$isDeveloper ? 'opacity-50 cursor-not-allowed' : '' }}"></div>
                <span class="ml-3 text-sm font-medium" id="toggleStatus" style="color: var(--text-primary);">
                    {{ $isEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- EFFECTIVE SENDER ID BANNER (always visible)               -->
    <!-- ========================================================== -->
    <div class="p-4 rounded-lg mb-6"
         style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-comment-dots mt-1 mr-3 text-lg" style="color: var(--info);"></i>
            <div class="flex-1">
                <p class="text-xs font-medium mb-1" style="color: var(--info);">
                    Effective Sender ID (what will actually be used when sending)
                </p>
                <p class="text-lg font-mono font-semibold" style="color: var(--text-primary);">
                    {{ $effectiveSenderId ?: '— not set —' }}
                </p>

                @if($senderIdSource === 'provider')
                    <p class="text-xs mt-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-user-cog mr-1" style="color: var(--primary);"></i>
                        Set by a developer on this page (overrides the global default)
                    </p>
                @elseif($senderIdSource === 'global')
                    <p class="text-xs mt-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-globe mr-1" style="color: var(--info);"></i>
                        Inherited from
                        <a href="{{ route('admin.system-settings.edit') }}#general"
                           class="text-blue-500 hover:underline ml-1 font-medium">
                            System Settings
                        </a>
                        — a developer can override this below.
                    </p>
                @else
                    <p class="text-xs mt-2 flex items-center" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        No sender ID configured yet.
                        @if($isDeveloper)
                            Set one below, or ask an admin to set a default in
                            <a href="{{ route('admin.system-settings.edit') }}#general"
                               class="text-blue-500 hover:underline ml-1">
                                System Settings
                            </a>.
                        @else
                            Ask a developer to configure this, or set a default in
                            <a href="{{ route('admin.system-settings.edit') }}#general"
                               class="text-blue-500 hover:underline ml-1">
                                System Settings
                            </a>.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Configuration Status -->
    <div id="arkeselStatus" class="p-4 rounded-lg mb-6 border-l-4" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--info); display: none;">
        <!-- Status will be dynamically loaded -->
    </div>

    <!-- Configuration Fields -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="configFields">

        <!-- API Key -->
        <div class="form-group">
            <label for="api_key" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                API Key <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <div class="relative">
                <input type="password"
                       id="api_key"
                       name="api_key"
                       value="{{ $currentApiKey }}"
                       class="form-input w-full p-3 pr-10 rounded-lg config-field"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="{{ $isDeveloper ? 'Enter your Arkesel API key' : '••••••••' }}"
                       autocomplete="off"
                       {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                       {{ !$isDeveloper ? 'readonly' : '' }}>
                @if($isDeveloper)
                    <button type="button"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center toggle-password"
                            data-target="api_key">
                        <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                    </button>
                @endif
            </div>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                @if($isDeveloper)
                    Get your API key from Arkesel dashboard
                @else
                    Managed by developers — contact them to change
                @endif
            </p>
        </div>

        <!-- Sender ID — NEVER required (falls back to global default) -->
        <div class="form-group">
            <label for="sender_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Sender ID
                @if($hasGlobalFallback)
                    <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">
                        (optional — inherits global default if empty)
                    </span>
                @endif
            </label>
            <input type="text"
                   id="sender_id"
                   name="sender_id"
                   value="{{ $currentSenderId }}"
                   class="form-input w-full p-3 rounded-lg config-field sender-id-input"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="{{ $senderIdPlaceholder }}"
                   maxlength="11"
                   pattern="[A-Za-z0-9]*"
                   title="Optional. Max 11 alphanumeric characters. Leave empty to use the global default."
                   autocomplete="off"
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <div class="flex items-center justify-between mt-1">
                <p class="text-xs" style="color: var(--text-secondary);">
                    @if($hasGlobalFallback)
                        Leave empty to use the global default from System Settings.
                    @else
                        Max 11 characters, alphanumeric only.
                    @endif
                </p>
                <span class="text-xs font-medium sender-id-counter" style="color: var(--text-secondary);">
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
                            Save this form to pin a provider-specific value, or clear the field to keep inheriting the global default.
                        @endif
                    </span>
                </div>
            @endif

            {{-- Warning when no fallback exists and field is empty --}}
            @if(!$hasGlobalFallback && empty($currentSenderId) && $isDeveloper)
                <div class="mt-2 p-2 rounded text-xs flex items-start"
                     style="background-color: rgba(var(--warning-rgb), 0.05); color: var(--text-secondary);">
                    <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                    <span>
                        No global fallback is configured, so this field is effectively required.
                        Set a value here, or ask an admin to set a default in
                        <a href="{{ route('admin.system-settings.edit') }}#general"
                           class="text-blue-500 hover:underline">System Settings</a>.
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

        <!-- Base URL -->
        <div class="md:col-span-2 form-group">
            <label for="base_url" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Base URL
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <input type="url"
                   id="base_url"
                   name="base_url"
                   value="{{ $currentBaseUrl }}"
                   class="form-input w-full p-3 rounded-lg config-field"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="https://sms.arkesel.com"
                   autocomplete="off"
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Arkesel SMS API endpoint. Only change if using a custom endpoint.
            </p>
        </div>
    </div>

    <!-- Current Configuration Summary -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-cog mr-2" style="color: var(--info);"></i> Current Configuration
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
                                  title="No value configured">
                                unset
                            </span>
                        @endif
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
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Base URL:</span>
                    <span class="font-medium" style="color: var(--text-primary);">{{ $currentBaseUrl }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Tips -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: var(--info);">
            <i class="fas fa-info-circle mr-2"></i> Configuration Tips
        </h4>
        <ul class="text-xs space-y-1" style="color: var(--info);">
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Get your API key from
                <a href="https://sms.arkesel.com" target="_blank" rel="noopener"
                   class="underline hover:opacity-80 ml-1" style="color: var(--info);">Arkesel SMS Dashboard</a>
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                <strong>Sender ID must be approved by Arkesel</strong> before use.
            </li>
            @if($isDeveloper)
                <li class="flex items-start">
                    <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                    A Sender ID set here <strong>overrides</strong> the global default.
                    Clear the field to <strong>inherit</strong> the global default instead.
                </li>
            @else
                <li class="flex items-start">
                    <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                    To change the default Sender ID, use
                    <a href="{{ route('admin.system-settings.edit') }}#general"
                       class="underline hover:opacity-80 ml-1" style="color: var(--info);">System Settings</a>.
                </li>
            @endif
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Balance and connection status updates automatically every 5 minutes
            </li>
        </ul>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pt-6 border-t gap-4" style="border-color: var(--border-color);">
        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="test-connection flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="arkesel"
                        id="testConnectionBtn">
                    <i class="fas fa-plug mr-2"></i> Test Connection
                </button>

                <button type="button"
                        class="send-test-sms flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="arkesel"
                        id="sendTestSmsBtn">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test SMS
                </button>
            @endif

            <button type="button"
                    class="refresh-status flex items-center px-4 py-2 rounded-lg btn-secondary"
                    data-provider="arkesel"
                    id="refreshStatusBtn">
                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="reset-config flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="arkesel"
                        id="resetConfigBtn">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit"
                        class="save-config flex items-center px-4 py-2 rounded-lg btn-modern"
                        id="saveConfigBtn">
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

<!-- Test SMS Modal -->
@if($isDeveloper)
<div id="testSmsModal"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md"
         style="background-color: var(--card-bg); border-color: var(--border-color);">
        <div class="mt-3">
            <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Send Test SMS</h3>

            <div class="mb-4">
                <label for="test_phone" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Phone Number <span class="required">*</span>
                </label>
                <input type="text"
                       id="test_phone"
                       class="form-input w-full p-3 rounded-lg"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="+233123456789"
                       value="{{ config('sms.test_phone', '+233595652410') }}"
                       required>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Format: +233XXXXXXXXX</p>
            </div>

            <div class="mb-4">
                <label for="test_message" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Test Message <span class="required">*</span>
                </label>
                <textarea id="test_message"
                          class="form-textarea w-full p-3 rounded-lg"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                          placeholder="This is a test message from your SMS configuration"
                          rows="3"
                          required>Test SMS from {{ $currentSenderId ?: 'your brand' }} - Arkesel connection successful!</textarea>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-xs" style="color: var(--text-secondary);"><span id="modalCharCount">0</span>/160 characters</p>
                    <span class="text-xs font-medium" id="currentSenderId" style="color: var(--info);">
                        Sender: <span id="dynamicSenderId">{{ $currentSenderId ?: 'Not set' }}</span>
                    </span>
                </div>
            </div>

            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <p class="text-xs flex items-start" style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i>
                    <span>Test SMS will be sent using your current Sender ID and API credentials</span>
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        id="cancelTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-secondary">
                    Cancel
                </button>
                <button type="button"
                        id="confirmSendTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-modern">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Connection Test Results -->
<div id="connectionTestResults" class="mt-4 hidden">
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

@if (session('success') && session('provider') == 'arkesel')
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
.required { color: var(--danger) !important; }

.sender-id-input { transition: all 0.3s ease; }
.sender-id-input:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid transparent;
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

.input-valid   { border-color: var(--success) !important; background-color: rgba(var(--success-rgb), 0.05) !important; }
.input-invalid { border-color: var(--danger)  !important; background-color: rgba(var(--danger-rgb), 0.05)  !important; }

#testSmsModal { backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
.form-group { margin-bottom: 1rem; }

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    #testSmsModal .relative { width: 95%; margin: 1rem auto; max-width: 95%; }
    .flex.justify-between { flex-direction: column; gap: 1rem; }
    .flex.flex-wrap { justify-content: space-between; width: 100%; }
}

a { color: var(--info); transition: opacity 0.2s ease; }
a:hover { opacity: 0.8; }

.fas, .fab { color: inherit; }

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg-primary); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

::placeholder { color: var(--text-secondary); opacity: 0.7; }
:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

button:disabled, select:disabled, input:disabled, textarea:disabled {
    opacity: 0.5; cursor: not-allowed;
}

.toggle-password {
    background: none; border: none; cursor: pointer; padding: 0;
    display: flex; align-items: center; justify-content: center; width: 40px;
}
.toggle-password:hover i { color: var(--primary); }

[data-theme="dark"] ::placeholder { opacity: 0.5; }
[data-theme="dark"] input,
[data-theme="dark"] textarea,
[data-theme="dark"] select {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
}
[data-theme="dark"] input:focus,
[data-theme="dark"] textarea:focus,
[data-theme="dark"] select:focus {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
}
</style>

<script>
// Role flag for the partial's JS — developers can write, others can't.
window.isDeveloper = {{ $isDeveloper ? 'true' : 'false' }};

// Whether the sender ID value shown is inherited from the global default.
window.arkeselSenderIdInherited = {{ $isInheritedFromGlobal ? 'true' : 'false' }};

// Whether a global fallback exists at all.
window.arkeselHasGlobalFallback = {{ $hasGlobalFallback ? 'true' : 'false' }};

document.addEventListener('DOMContentLoaded', function () {
    initializeArkeselProvider();
});

// Track whether form submission is currently being processed
let arkeselFormSubmitting = false;

function initializeArkeselProvider() {
    const provider           = 'arkesel';
    const toggleCheckbox     = document.getElementById('enabledToggle');
    const senderIdInput      = document.getElementById('sender_id');
    const senderIdCounter    = document.querySelector('.sender-id-counter');
    const testBtn            = document.getElementById('testConnectionBtn');
    const sendTestBtn        = document.getElementById('sendTestSmsBtn');
    const resetBtn           = document.getElementById('resetConfigBtn');
    const refreshBtn         = document.getElementById('refreshStatusBtn');
    const form               = document.getElementById('arkeselConfigForm');
    const saveBtn            = document.getElementById('saveConfigBtn');

    loadProviderStatus(provider);

    /* ---------- Sender ID counter + validation (developer only) ---------- */
    if (senderIdInput && senderIdCounter && window.isDeveloper) {
        senderIdInput.addEventListener('input', function () {
            senderIdCounter.textContent = `${this.value.length}/11`;
            validateSenderId(this.value);
            updateCurrentConfigDisplay();
            syncModalSenderId();
        });
        senderIdInput.addEventListener('blur', function () {
            validateSenderId(this.value, true);
        });

        senderIdCounter.textContent = `${senderIdInput.value.length}/11`;
        validateSenderId(senderIdInput.value);
    } else if (senderIdCounter && senderIdInput) {
        senderIdCounter.textContent = `${senderIdInput.value.length}/11`;
    }

    /* ---------- Password show/hide (developer only) ---------- */
    if (window.isDeveloper) {
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input    = document.getElementById(targetId);
                const icon     = this.querySelector('i');

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

    /* ---------- Enable/disable toggle (developer only) ---------- */
    if (toggleCheckbox && window.isDeveloper) {
        toggleCheckbox.addEventListener('change', function () {
            updateUIToggleState(this.checked);
            toggleProvider(provider, this.checked, this);
        });
    }

    /* ---------- Action buttons ---------- */
    testBtn?.addEventListener('click', function () { testConnection(provider, this); });
    sendTestBtn?.addEventListener('click', function () { openTestSmsModal(provider); });
    refreshBtn?.addEventListener('click', function () {
        loadProviderStatus(provider);
        showToast('Status refreshed', 'success');
    });
    resetBtn?.addEventListener('click', function () { resetConfiguration(provider, this); });

    /* ---------- Form submission (developer only) ---------- */
    if (form && window.isDeveloper) {
        form.addEventListener('submit', function (e) {
            if (arkeselFormSubmitting) {
                e.preventDefault();
                return;
            }

            // Sender ID is OPTIONAL. Only block if a value is present AND invalid.
            if (senderIdInput && !validateSenderId(senderIdInput.value, true)) {
                e.preventDefault();
                showToast('Please fix Sender ID validation errors', 'error');
                senderIdInput.focus();
                return;
            }

            arkeselFormSubmitting = true;

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            }

            setTimeout(() => {
                arkeselFormSubmitting = false;
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Configuration';
                }
            }, 15000);
        });
    }

    if (window.isDeveloper) {
        initializeTestSmsModal();
    }

    if (toggleCheckbox) {
        updateUIToggleState(toggleCheckbox.checked);
    }
}

/* ============================================================
 |  Sender ID validation
 |
 |  Sender ID is OPTIONAL. It only becomes "required" in the UI
 |  sense if the provider is enabled AND there is no global fallback
 |  configured. In that specific case, we surface a warning but
 |  still don't block submission (the backend validation handles it).
 * ============================================================ */
function validateSenderId(senderId, showErrors = false) {
    const senderIdInput      = document.getElementById('sender_id');
    const senderIdValidation = document.getElementById('senderIdValidation');
    const senderIdError      = document.getElementById('senderIdError');
    if (!senderIdInput || !window.isDeveloper) return true;

    let isValid = true;
    let errorMessage = '';

    if (senderId.length > 11) {
        isValid = false;
        errorMessage = 'Sender ID cannot exceed 11 characters';
    } else if (senderId && !/^[A-Za-z0-9]+$/.test(senderId)) {
        isValid = false;
        errorMessage = 'Only letters and numbers allowed (no spaces or special characters)';
    } else {
        // Value is either empty or valid. If empty, check for a fallback.
        const isEnabled = document.getElementById('enabledToggle')?.checked;
        const hasFallback = window.arkeselHasGlobalFallback === true;
        if (isEnabled && !senderId.trim() && !hasFallback) {
            // Empty AND no fallback AND enabled → this is a soft warning, not a hard error.
            // We still return false to surface it, but form submission is allowed
            // (the backend will decide based on required_fields).
            isValid = false;
            errorMessage = 'No sender ID set and no global fallback configured';
        }
    }

    if (senderIdValidation && senderIdError) {
        const isFocused = document.activeElement === senderIdInput;
        if (!isValid && (showErrors || isFocused)) {
            senderIdValidation.classList.remove('hidden');
            senderIdError.textContent = errorMessage;
            senderIdInput.classList.add('input-invalid');
            senderIdInput.classList.remove('input-valid');
        } else if (isValid && senderId.trim()) {
            senderIdValidation.classList.add('hidden');
            senderIdInput.classList.add('input-valid');
            senderIdInput.classList.remove('input-invalid');
        } else {
            senderIdValidation.classList.add('hidden');
            senderIdInput.classList.remove('input-valid', 'input-invalid');
        }
    }

    return isValid;
}

/* ============================================================
 |  Live summary update
 * ============================================================ */
function updateCurrentConfigDisplay() {
    const input   = document.getElementById('sender_id');
    const display = document.getElementById('currentSenderIdDisplay');
    if (input && display) {
        const existingBadge = display.querySelector('span');
        const badgeMarkup = existingBadge ? existingBadge.outerHTML : '';
        display.innerHTML = (input.value || 'Not set') + ' ' + badgeMarkup;
    }
}

/* ============================================================
 |  Sync Sender ID into modal when it changes
 * ============================================================ */
function syncModalSenderId() {
    const senderIdInput   = document.getElementById('sender_id');
    const dynamicSenderId = document.getElementById('dynamicSenderId');
    const messageInput    = document.getElementById('test_message');
    const charCount       = document.getElementById('modalCharCount');

    if (!senderIdInput) return;

    const senderId = senderIdInput.value || 'your brand';
    if (dynamicSenderId) dynamicSenderId.textContent = senderIdInput.value || 'Not set';

    if (messageInput && !messageInput.dataset.userModified) {
        messageInput.value = `Test SMS from ${senderId} - Arkesel connection successful!`;
        if (charCount) charCount.textContent = messageInput.value.length;
    }
}

/* ============================================================
 |  Provider status loading
 * ============================================================ */
function loadProviderStatus(provider) {
    const statusContainer = document.getElementById('arkeselStatus');
    if (!statusContainer) return;

    statusContainer.innerHTML = `
        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-3" style="color: var(--secondary);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Loading Status...</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Checking provider configuration</p>
                </div>
            </div>
            <span class="status-indicator status-disabled">Loading</span>
        </div>
    `;
    statusContainer.style.display = 'block';

    fetch(window.smsProviderConfig?.routes?.status || '/admin/sms-providers/status', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.providers[provider]) {
            updateProviderStatusUI(provider, data.data.providers[provider]);
        }
    })
    .catch(error => {
        console.error('Error loading provider status:', error);
        statusContainer.innerHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">Status Check Failed</p>
                        <p class="text-xs" style="color: var(--danger);">Unable to load provider status</p>
                    </div>
                </div>
                <span class="status-indicator status-inactive">Error</span>
            </div>
        `;
    });
}

function updateProviderStatusUI(provider, status) {
    const statusContainer = document.getElementById('arkeselStatus');
    if (!statusContainer) return;

    let statusHTML = '';
    let statusClass = 'config-status';

    if (!status.enabled) {
        statusHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-toggle-off mr-3" style="color: var(--secondary);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Provider Disabled</p>
                        <p class="text-xs" style="color: var(--text-secondary);">Enable to configure and test</p>
                    </div>
                </div>
                <span class="status-indicator status-disabled">Disabled</span>
            </div>
        `;
    } else if (!status.configured) {
        statusHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--warning);">Incomplete Configuration</p>
                        <p class="text-xs" style="color: var(--warning);">
                            Missing: ${status.missing_configuration?.join(', ') || 'Required fields'}
                        </p>
                    </div>
                </div>
                <span class="status-indicator status-inactive">Incomplete</span>
            </div>
        `;
        statusClass = 'config-status warning';
    } else {
        statusHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--success);">Ready to Send SMS</p>
                        <p class="text-xs" style="color: var(--success);">
                            Provider is properly configured and enabled
                        </p>
                    </div>
                </div>
                <span class="status-indicator status-active">Active</span>
            </div>
        `;
        statusClass = 'config-status success';
    }

    statusContainer.innerHTML = statusHTML;
    statusContainer.className = statusClass;
    statusContainer.style.display = 'block';
}

/* ============================================================
 |  Toggle UI state
 * ============================================================ */
function updateUIToggleState(isEnabled) {
    const toggleStatus = document.getElementById('toggleStatus');
    const testBtn      = document.getElementById('testConnectionBtn');
    const sendTestBtn  = document.getElementById('sendTestSmsBtn');
    const senderIdInput= document.getElementById('sender_id');
    const apiKeyInput  = document.getElementById('api_key');

    if (toggleStatus) {
        toggleStatus.textContent = isEnabled ? 'Enabled' : 'Disabled';
        toggleStatus.style.color = isEnabled ? 'var(--success)' : 'var(--secondary)';
    }

    if (window.isDeveloper) {
        // Only the API key becomes required when enabled. Sender ID stays optional
        // so the developer can clear it to fall back to the global default.
        [apiKeyInput].forEach(field => {
            if (!field) return;
            if (isEnabled) field.setAttribute('required', 'required');
            else           field.removeAttribute('required');
        });

        if (senderIdInput) validateSenderId(senderIdInput.value, true);
    }

    if (testBtn)     testBtn.disabled = !isEnabled || !window.isDeveloper;
    if (sendTestBtn) sendTestBtn.disabled = !isEnabled || !window.isDeveloper;

    loadProviderStatus('arkesel');
}

/* ============================================================
 |  Test SMS modal (developer only)
 * ============================================================ */
function initializeTestSmsModal() {
    const modal      = document.getElementById('testSmsModal');
    const cancelBtn  = document.getElementById('cancelTestSms');
    const confirmBtn = document.getElementById('confirmSendTestSms');
    const messageInput = document.getElementById('test_message');
    const charCount  = document.getElementById('modalCharCount');

    if (!modal) return;

    syncModalSenderId();

    if (messageInput && charCount) {
        messageInput.addEventListener('input', function () {
            this.dataset.userModified = 'true';
            charCount.textContent = this.value.length;
            if (this.value.length > 160) {
                charCount.style.color = 'var(--danger)';
                charCount.classList.add('font-bold');
            } else {
                charCount.style.color = 'var(--text-secondary)';
                charCount.classList.remove('font-bold');
            }
        });
        charCount.textContent = messageInput.value.length;
    }

    cancelBtn?.addEventListener('click', () => modal.classList.add('hidden'));

    confirmBtn?.addEventListener('click', function () {
        const phone   = document.getElementById('test_phone').value;
        const message = document.getElementById('test_message').value;

        if (!phone)   { showToast('Please enter a phone number', 'error'); return; }
        if (!message) { showToast('Please enter a message', 'error'); return; }
        if (message.length > 160) { showToast('Message cannot exceed 160 characters', 'error'); return; }

        sendTestSMS('arkesel', phone, message, this);
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) modal.classList.add('hidden');
    });
}

function openTestSmsModal(provider) {
    const modal        = document.getElementById('testSmsModal');
    const confirmBtn   = document.getElementById('confirmSendTestSms');
    const dynamicSenderId = document.getElementById('dynamicSenderId');
    const senderIdInput   = document.getElementById('sender_id');

    if (!modal || !confirmBtn) return;

    if (dynamicSenderId && senderIdInput) {
        dynamicSenderId.textContent = senderIdInput.value || 'Not set';
    }

    confirmBtn.setAttribute('data-provider', provider);
    modal.classList.remove('hidden');

    document.getElementById('test_phone')?.focus();
}

/* ============================================================
 |  Connection test results
 * ============================================================ */
function showConnectionTestResults(provider, result) {
    const container = document.getElementById('connectionTestResults');
    if (!container) return;

    let html = '';
    if (result.success) {
        html = `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--success);">Connection Test Successful</h4>
                </div>
                <p class="mt-1 text-sm" style="color: var(--success);">${result.message}</p>
                ${result.details ? `
                <div class="mt-2 text-xs space-y-1" style="color: var(--success);">
                    <p><strong>Balance:</strong> ${result.details.balance || 'N/A'}</p>
                    <p><strong>Sender ID:</strong> ${result.details.sender_id || 'N/A'}</p>
                    <p><strong>Status:</strong> ${result.details.api_status || 'N/A'}</p>
                    ${result.details.response_time ? `<p><strong>Response Time:</strong> ${result.details.response_time}ms</p>` : ''}
                </div>` : ''}
            </div>
        `;
    } else {
        html = `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--danger);">Connection Test Failed</h4>
                </div>
                <p class="mt-1 text-sm" style="color: var(--danger);">${result.message}</p>
                ${result.details ? `
                <div class="mt-2 text-xs space-y-1" style="color: var(--danger);">
                    <p><strong>Error:</strong> ${result.details.error || 'Unknown error'}</p>
                    <p><strong>Status:</strong> ${result.details.api_status || 'N/A'}</p>
                    ${result.details.suggestion ? `<p><strong>Suggestion:</strong> ${result.details.suggestion}</p>` : ''}
                </div>` : ''}
            </div>
        `;
    }

    container.innerHTML = html;
    container.classList.remove('hidden');

    setTimeout(() => container.classList.add('hidden'), 10000);
}

/* ============================================================
 |  Global exports
 * ============================================================ */
window.initializeArkeselProvider  = initializeArkeselProvider;
window.showConnectionTestResults  = showConnectionTestResults;
window.validateSenderId           = validateSenderId;
window.loadProviderStatus         = loadProviderStatus;
window.updateCurrentConfigDisplay = updateCurrentConfigDisplay;
</script>