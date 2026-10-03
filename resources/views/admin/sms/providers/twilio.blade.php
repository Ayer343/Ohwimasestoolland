@php
    // Safe access to Twilio config — no env() bypassing config cache
    $twilioConfig  = config('sms.providers.twilio', []);
    $isEnabled     = filter_var($twilioConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Role: developers can edit; super-admins get read-only access.
    $isDeveloper = $isDeveloper ?? false;

    // Provider metadata from the controller (already resolved)
    $providerMeta      = $providers['twilio'] ?? [];
    $effectiveSenderId = $providerMeta['effective_sender_id'] ?? null;   // for display only
    $senderIdSource    = $providerMeta['sender_id_source']    ?? 'fallback';

    // old() first (validation failures), then config, then empty
    $currentAccountSid = old('account_sid', $twilioConfig['account_sid'] ?? '');
    $currentAuthToken  = old('auth_token',  $twilioConfig['auth_token']  ?? '');
    $currentFromNumber = old('from_number', $twilioConfig['from_number'] ?? '');

    // Twilio's effective sender is the from_number, NOT the global sender ID.
    // We surface the global sender ID (if set) as informational only.
    $hasGlobalSenderId = !empty($effectiveSenderId);
@endphp

<form action="{{ route('admin.sms-providers.configure') }}"
      method="POST"
      class="space-y-6 provider-form"
      id="twilioConfigForm"
      data-provider="twilio">
    @csrf
    <input type="hidden" name="provider" value="twilio">

    <!-- Provider Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div class="flex-1">
            <h4 class="text-lg font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                <i class="fab fa-twilio mr-2" style="color: #F22F46;"></i>
                Twilio Configuration
                @if(!$isDeveloper)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full flex items-center"
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-lock mr-1"></i> Read-Only
                    </span>
                @endif
            </h4>
            <p class="text-sm" style="color: var(--text-secondary);">
                @if($isDeveloper)
                    Configure your Twilio SMS gateway settings
                @else
                    View your Twilio SMS gateway configuration
                @endif
            </p>
        </div>
        <div class="flex items-center">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox"
                       name="enabled"
                       value="1"
                       class="sr-only peer toggle-provider-checkbox"
                       id="twilioEnabledToggle"
                       data-provider="twilio"
                       {{ $isEnabled ? 'checked' : '' }}
                       {{ !$isDeveloper ? 'disabled' : '' }}>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-500
                       {{ !$isDeveloper ? 'opacity-50 cursor-not-allowed' : '' }}"></div>
                <span class="ml-3 text-sm font-medium" id="twilioToggleStatus" style="color: var(--text-primary);">
                    {{ $isEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- TWILIO EFFECTIVE SENDER — the from_number, not the global ID -->
    <!-- ========================================================== -->
    <div class="p-4 rounded-lg mb-6"
         style="background-color: rgba(242, 47, 70, 0.05); border-left: 4px solid #F22F46;">
        <div class="flex items-start">
            <i class="fas fa-phone-alt mt-1 mr-3 text-lg" style="color: #F22F46;"></i>
            <div class="flex-1">
                <p class="text-xs font-medium mb-1" style="color: #F22F46;">
                    Effective Sender (Twilio sends from this phone number)
                </p>
                <p class="text-lg font-mono font-semibold" style="color: var(--text-primary);">
                    {{ $currentFromNumber ?: '— not set —' }}
                </p>

                @if($currentFromNumber)
                    <p class="text-xs mt-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        This is the purchased Twilio number that will appear as the SMS sender.
                    </p>
                @else
                    <p class="text-xs mt-2 flex items-center" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Set a From Number below to enable sending.
                    </p>
                @endif

                {{-- Explanation: the global sender ID is not used by Twilio --}}
                @if($hasGlobalSenderId)
                    <div class="mt-3 p-2 rounded text-xs flex items-start"
                         style="background-color: rgba(var(--info-rgb), 0.05); color: var(--text-secondary);">
                        <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--info);"></i>
                        <span>
                            Twilio uses a <strong>phone number</strong> as the sender, so the global SMS Sender ID
                            (<span class="font-mono">{{ $effectiveSenderId }}</span>)
                            is <strong>not applied</strong> here. It's used by other providers like Arkesel, Hubtel,
                            and Nalo Solutions.
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Configuration Status -->
    <div id="twilioStatus" class="p-4 rounded-lg mb-6 border-l-4" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--info); display: none;">
        <!-- Status will be dynamically loaded -->
    </div>

    <!-- Configuration Fields -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="twilioConfigFields">

        <!-- Account SID -->
        <div class="form-group">
            <label for="twilio_account_sid" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Account SID <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <input type="text"
                   id="twilio_account_sid"
                   name="account_sid"
                   value="{{ $currentAccountSid }}"
                   class="form-input w-full p-3 rounded-lg config-field"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="{{ $isDeveloper ? 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' : '••••••••••••••••••••••••••••••••' }}"
                   autocomplete="off"
                   pattern="^AC[a-fA-F0-9]{32}$"
                   title="Twilio Account SID must start with 'AC' followed by 32 hex characters"
                   {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Find this in your Twilio console
            </p>
            <div id="accountSidValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="accountSidError"></span>
                </p>
            </div>
        </div>

        <!-- Auth Token -->
        <div class="form-group">
            <label for="twilio_auth_token" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Auth Token <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <div class="relative">
                <input type="password"
                       id="twilio_auth_token"
                       name="auth_token"
                       value="{{ $currentAuthToken }}"
                       class="form-input w-full p-3 pr-10 rounded-lg config-field"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="{{ $isDeveloper ? 'Enter your Twilio Auth Token' : '••••••••••••••••' }}"
                       autocomplete="off"
                       minlength="10"
                       {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                       {{ !$isDeveloper ? 'readonly' : '' }}>
                @if($isDeveloper)
                    <button type="button"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center toggle-password"
                            data-target="twilio_auth_token">
                        <i class="fas fa-eye" style="color: var(--text-secondary);"></i>
                    </button>
                @endif
            </div>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Keep this secure and confidential
            </p>
            <div id="authTokenValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="authTokenError"></span>
                </p>
            </div>
        </div>

        <!-- From Number -->
        <div class="md:col-span-2 form-group">
            <label for="twilio_from_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                From Number <span class="required">*</span>
                @if(!$isDeveloper)
                    <i class="fas fa-lock ml-1 text-xs" style="color: var(--warning);"></i>
                @endif
            </label>
            <input type="text"
                   id="twilio_from_number"
                   name="from_number"
                   value="{{ $currentFromNumber }}"
                   class="form-input w-full p-3 rounded-lg config-field"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                   placeholder="+1234567890"
                   autocomplete="off"
                   pattern="^\+\d{10,15}$"
                   title="Enter phone number in E.164 format (e.g., +1234567890)"
                   {{ $isEnabled && $isDeveloper ? 'required' : '' }}
                   {{ !$isDeveloper ? 'readonly' : '' }}>
            <div class="flex items-center justify-between mt-1">
                <p class="text-xs" style="color: var(--text-secondary);">
                    Your Twilio phone number with country code (E.164 format)
                </p>
                <span class="text-xs font-medium" id="fromNumberCounter" style="color: var(--text-secondary);">
                    {{ strlen($currentFromNumber) }}/15
                </span>
            </div>

            {{-- Twilio-specific note: the global sender ID is not applicable --}}
            <div class="mt-2 p-2 rounded text-xs flex items-start"
                 style="background-color: rgba(242, 47, 70, 0.05); color: var(--text-secondary);">
                <i class="fas fa-phone-alt mt-0.5 mr-2" style="color: #F22F46;"></i>
                <span>
                    Twilio uses a purchased phone number as the sender.
                    This is <strong>not</strong> the same as the global SMS Sender ID (which is an alphanumeric
                    name used by other providers).
                </span>
            </div>

            <div id="fromNumberValidation" class="hidden mt-1">
                <p class="text-xs flex items-center" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="fromNumberError"></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Current Configuration Summary -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
            <i class="fab fa-twilio mr-2" style="color: #F22F46;"></i> Current Configuration
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
                    <span style="color: var(--text-secondary);">Account SID:</span>
                    <span class="font-medium" id="currentAccountSidDisplay" style="color: var(--text-primary);">
                        {{ $currentAccountSid ? 'Configured' : 'Not set' }}
                    </span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span style="color: var(--text-secondary);">Auth Token:</span>
                    <span class="font-medium"
                          style="{{ !empty($currentAuthToken) ? 'color: var(--success);' : 'color: var(--danger);' }}">
                        {{ !empty($currentAuthToken) ? 'Configured' : 'Not set' }}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span style="color: var(--text-secondary);">From Number:</span>
                    <span class="font-medium flex items-center gap-2" id="currentFromNumberDisplay" style="color: var(--text-primary);">
                        {{ $currentFromNumber ?: 'Not set' }}
                        @if($currentFromNumber)
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  style="background-color: rgba(242, 47, 70, 0.1); color: #F22F46;"
                                  title="Twilio uses this phone number as the sender">
                                sender
                            </span>
                        @else
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                  title="Required for Twilio">
                                unset
                            </span>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Tips -->
    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: #F22F46;">
            <i class="fab fa-twilio mr-2"></i> Configuration Tips
        </h4>
        <ul class="text-xs space-y-1" style="color: var(--info);">
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Get credentials from
                <a href="https://twilio.com/console" target="_blank" rel="noopener"
                   class="underline hover:opacity-80 ml-1" style="color: var(--info);">Twilio Console</a>
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                You need a purchased phone number in Twilio
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Verify your account for international SMS
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Account SID starts with "AC" followed by 32 hex characters
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                Phone number must be in E.164 format (e.g., +1234567890)
            </li>
            <li class="flex items-start">
                <i class="fas fa-chevron-right text-xs mt-1 mr-2"></i>
                <strong>The global SMS Sender ID does not apply to Twilio.</strong>
            </li>
        </ul>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pt-6 border-t gap-4" style="border-color: var(--border-color);">
        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="test-connection flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="twilio"
                        id="twilioTestConnectionBtn">
                    <i class="fas fa-plug mr-2"></i> Test Connection
                </button>

                <button type="button"
                        class="send-test-sms flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="twilio"
                        id="twilioSendTestSmsBtn">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test SMS
                </button>
            @endif

            <button type="button"
                    class="refresh-status flex items-center px-4 py-2 rounded-lg btn-secondary"
                    data-provider="twilio"
                    id="twilioRefreshStatusBtn">
                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($isDeveloper)
                <button type="button"
                        class="reset-config flex items-center px-4 py-2 rounded-lg btn-secondary"
                        data-provider="twilio"
                        id="twilioResetConfigBtn">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit"
                        class="save-config flex items-center px-4 py-2 rounded-lg btn-modern"
                        id="twilioSaveConfigBtn">
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

<!-- Twilio Test SMS Modal -->
@if($isDeveloper)
<div id="twilioTestSmsModal"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md"
         style="background-color: var(--card-bg); border-color: var(--border-color);">
        <div class="mt-3">
            <h3 class="text-lg font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fab fa-twilio mr-2" style="color: #F22F46;"></i> Send Twilio Test SMS
            </h3>

            <div class="mb-4">
                <label for="twilio_test_phone" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Phone Number <span class="required">*</span>
                </label>
                <input type="text"
                       id="twilio_test_phone"
                       class="form-input w-full p-3 rounded-lg"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                       placeholder="+1234567890"
                       value="{{ config('sms.test_phone', '+233595652410') }}"
                       required
                       pattern="^\+\d{10,15}$">
                <p class="text-xs mt-1" style="color: var(--text-secondary);">Format: +XXXXXXXXXXX (E.164 format)</p>
            </div>

            <div class="mb-4">
                <label for="twilio_test_message" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Test Message <span class="required">*</span>
                </label>
                <textarea id="twilio_test_message"
                          class="form-textarea w-full p-3 rounded-lg"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                          placeholder="This is a test message from your Twilio configuration"
                          rows="3"
                          required>Test SMS via Twilio - Connection successful!</textarea>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <span id="twilioModalCharCount">0</span>/160 characters
                    </p>
                    <span class="text-xs font-medium" id="twilioCurrentFromNumber" style="color: var(--info);">
                        From: <span id="dynamicFromNumber">{{ $currentFromNumber ?: 'Not set' }}</span>
                    </span>
                </div>
            </div>

            <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <p class="text-xs flex items-start" style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i>
                    <span>Test SMS will be sent using your configured From Number and Twilio credentials</span>
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        id="twilioCancelTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-secondary">
                    Cancel
                </button>
                <button type="button"
                        id="twilioConfirmSendTestSms"
                        class="flex items-center px-4 py-2 rounded-lg btn-modern">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Connection Test Results -->
<div id="twilioConnectionTestResults" class="mt-4 hidden">
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

@if (session('success') && session('provider') == 'twilio')
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
.fa-twilio { color: #F22F46 !important; }

.input-valid   { border-color: var(--success) !important; background-color: rgba(var(--success-rgb), 0.05) !important; }
.input-invalid { border-color: var(--danger)  !important; background-color: rgba(var(--danger-rgb), 0.05)  !important; }

#fromNumberCounter { transition: color 0.3s ease; }

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

#twilioTestSmsModal { backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }

.form-group { margin-bottom: 1rem; }

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    #twilioTestSmsModal .relative { width: 95%; margin: 1rem auto; max-width: 95%; }
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

document.addEventListener('DOMContentLoaded', function () {
    initializeTwilioProvider();
});

let twilioFormSubmitting = false;

function initializeTwilioProvider() {
    const provider        = 'twilio';
    const toggleCheckbox  = document.getElementById('twilioEnabledToggle');
    const accountSidInput = document.getElementById('twilio_account_sid');
    const authTokenInput  = document.getElementById('twilio_auth_token');
    const fromNumberInput = document.getElementById('twilio_from_number');
    const fromNumberCounter = document.getElementById('fromNumberCounter');
    const testBtn         = document.getElementById('twilioTestConnectionBtn');
    const sendTestBtn     = document.getElementById('twilioSendTestSmsBtn');
    const resetBtn        = document.getElementById('twilioResetConfigBtn');
    const refreshBtn      = document.getElementById('twilioRefreshStatusBtn');
    const form            = document.getElementById('twilioConfigForm');
    const saveBtn         = document.getElementById('twilioSaveConfigBtn');

    loadTwilioProviderStatus(provider);

    /* ---------- From Number (developer only for listeners) ---------- */
    if (fromNumberInput && fromNumberCounter) {
        if (window.isDeveloper) {
            fromNumberInput.addEventListener('input', function () {
                // Light sanitisation — keep leading +, digits only
                let v = this.value;
                if (v && !v.startsWith('+')) v = '+' + v.replace(/\D/g, '');
                else if (v.startsWith('+')) v = '+' + v.substring(1).replace(/\D/g, '');
                if (this.value !== v) this.value = v;

                fromNumberCounter.textContent = `${this.value.length}/15`;
                validateTwilioPhoneNumber(this.value);
                updateCurrentConfigDisplay();
                syncModalFromNumber();
            });
            fromNumberInput.addEventListener('blur', () => validateTwilioPhoneNumber(fromNumberInput.value, true));
            validateTwilioPhoneNumber(fromNumberInput.value);
        }
        fromNumberCounter.textContent = `${fromNumberInput.value.length}/15`;
    }

    /* ---------- Account SID (developer only) ---------- */
    if (accountSidInput && window.isDeveloper) {
        accountSidInput.addEventListener('blur',  () => validateAccountSid(accountSidInput.value, true));
        accountSidInput.addEventListener('input', () => validateAccountSid(accountSidInput.value));
        validateAccountSid(accountSidInput.value);
    }

    /* ---------- Auth Token (developer only) ---------- */
    if (authTokenInput && window.isDeveloper) {
        authTokenInput.addEventListener('blur',  () => validateAuthToken(authTokenInput.value, true));
        authTokenInput.addEventListener('input', () => validateAuthToken(authTokenInput.value));
        validateAuthToken(authTokenInput.value);
    }

    /* ---------- Password toggle (developer only) ---------- */
    if (window.isDeveloper) {
        document.querySelectorAll('#twilioConfigForm .toggle-password').forEach(button => {
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
            updateTwilioUIToggleState(this.checked);
            toggleProvider(provider, this.checked, this);
        });
    }

    /* ---------- Action buttons ---------- */
    testBtn?.addEventListener('click', function () { testConnection(provider, this); });
    sendTestBtn?.addEventListener('click', function () { openTwilioTestSmsModal(provider); });
    refreshBtn?.addEventListener('click', function () {
        loadTwilioProviderStatus(provider);
        showToast('Status refreshed', 'success');
    });
    resetBtn?.addEventListener('click', function () { resetConfiguration(provider, this); });

    /* ---------- Form submit (developer only) ---------- */
    if (form && window.isDeveloper) {
        form.addEventListener('submit', function (e) {
            if (twilioFormSubmitting) { e.preventDefault(); return; }

            const sidOk    = validateAccountSid(accountSidInput?.value || '', true);
            const tokenOk  = validateAuthToken(authTokenInput?.value || '', true);
            const phoneOk  = validateTwilioPhoneNumber(fromNumberInput?.value || '', true);

            if (!sidOk || !tokenOk || !phoneOk) {
                e.preventDefault();
                showToast('Please fix validation errors before saving', 'error');
                return;
            }

            twilioFormSubmitting = true;

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            }

            showToast('Updating Twilio configuration...', 'info');

            setTimeout(() => {
                twilioFormSubmitting = false;
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Configuration';
                }
            }, 15000);
        });
    }

    if (window.isDeveloper) {
        initializeTwilioTestSmsModal();
    }

    if (toggleCheckbox) {
        updateTwilioUIToggleState(toggleCheckbox.checked);
    }
}

/* ============================================================
 |  Validation
 * ============================================================ */
function validateTwilioPhoneNumber(phoneNumber, showErrors = false) {
    const input = document.getElementById('twilio_from_number');
    const wrap  = document.getElementById('fromNumberValidation');
    const err   = document.getElementById('fromNumberError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    const isEnabled = document.getElementById('twilioEnabledToggle')?.checked;

    if (!phoneNumber.trim()) {
        if (isEnabled) { valid = false; msg = 'From Number is required when provider is enabled'; }
    } else if (!/^\+\d{10,15}$/.test(phoneNumber)) {
        valid = false; msg = 'Must be in E.164 format (e.g., +1234567890)';
    }

    toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors);
    return valid;
}

function validateAccountSid(accountSid, showErrors = false) {
    const input = document.getElementById('twilio_account_sid');
    const wrap  = document.getElementById('accountSidValidation');
    const err   = document.getElementById('accountSidError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    const isEnabled = document.getElementById('twilioEnabledToggle')?.checked;

    if (!accountSid.trim()) {
        if (isEnabled) { valid = false; msg = 'Account SID is required when provider is enabled'; }
    } else if (!accountSid.startsWith('AC')) {
        valid = false; msg = 'Account SID must start with "AC"';
    } else if (!/^AC[a-fA-F0-9]{32}$/.test(accountSid)) {
        valid = false; msg = 'Account SID must be 34 characters (AC + 32 hex)';
    }

    toggleFieldErrorUI(input, wrap, err, valid, msg, showErrors);
    return valid;
}

function validateAuthToken(token, showErrors = false) {
    const input = document.getElementById('twilio_auth_token');
    const wrap  = document.getElementById('authTokenValidation');
    const err   = document.getElementById('authTokenError');
    if (!input || !window.isDeveloper) return true;

    let valid = true, msg = '';
    const isEnabled = document.getElementById('twilioEnabledToggle')?.checked;

    if (!token.trim()) {
        if (isEnabled) { valid = false; msg = 'Auth Token is required when provider is enabled'; }
    } else if (token.length < 10) {
        valid = false; msg = 'Auth Token must be at least 10 characters';
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
 |  Live display + modal sync
 * ============================================================ */
function updateCurrentConfigDisplay() {
    const fromNumberInput = document.getElementById('twilio_from_number');
    const fromNumberDisp  = document.getElementById('currentFromNumberDisplay');
    if (fromNumberInput && fromNumberDisp) {
        // Preserve the source badge if it exists
        const existingBadge = fromNumberDisp.querySelector('span');
        const badgeMarkup = existingBadge ? existingBadge.outerHTML : '';
        fromNumberDisp.innerHTML = (fromNumberInput.value || 'Not set') + ' ' + badgeMarkup;
    }
}

function updateFromNumberDisplay() {
    const fromNumberInput = document.getElementById('twilio_from_number');
    const dynamic         = document.getElementById('dynamicFromNumber');
    if (fromNumberInput && dynamic) {
        dynamic.textContent = fromNumberInput.value || 'Not set';
    }
}

function syncModalFromNumber() {
    const fromNumberInput = document.getElementById('twilio_from_number');
    const dynamic         = document.getElementById('dynamicFromNumber');
    const messageInput    = document.getElementById('twilio_test_message');
    const charCount       = document.getElementById('twilioModalCharCount');

    if (!fromNumberInput) return;

    if (dynamic) dynamic.textContent = fromNumberInput.value || 'Not set';

    if (messageInput && !messageInput.dataset.userModified) {
        const from = fromNumberInput.value || 'your Twilio number';
        messageInput.value = `Test SMS via Twilio from ${from} - Connection successful!`;
        if (charCount) charCount.textContent = messageInput.value.length;
    }
}

/* ============================================================
 |  Provider status
 * ============================================================ */
function loadTwilioProviderStatus(provider) {
    const statusContainer = document.getElementById('twilioStatus');
    if (!statusContainer) return;

    statusContainer.innerHTML = `
        <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-3" style="color: var(--secondary);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Loading Status...</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Checking Twilio configuration</p>
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
            updateTwilioProviderStatusUI(provider, data.data.providers[provider]);
        }
    })
    .catch(error => {
        console.error('Error loading Twilio status:', error);
        statusContainer.innerHTML = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">Status Check Failed</p>
                        <p class="text-xs" style="color: var(--danger);">Unable to load Twilio status</p>
                    </div>
                </div>
                <span class="status-indicator status-inactive">Error</span>
            </div>
        `;
    });
}

function updateTwilioProviderStatusUI(provider, status) {
    const statusContainer = document.getElementById('twilioStatus');
    if (!statusContainer) return;

    let html = '', cls = 'config-status';

    if (!status.enabled) {
        html = `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                <div class="flex items-center">
                    <i class="fab fa-twilio mr-3" style="color: var(--secondary);"></i>
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
                    <i class="fab fa-twilio mr-3" style="color: var(--warning);"></i>
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
                    <i class="fab fa-twilio mr-3" style="color: var(--success);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--success);">Ready to Send SMS</p>
                        <p class="text-xs" style="color: var(--success);">Twilio is properly configured and enabled</p>
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
function updateTwilioUIToggleState(isEnabled) {
    const toggleStatus    = document.getElementById('twilioToggleStatus');
    const accountSidInput = document.getElementById('twilio_account_sid');
    const authTokenInput  = document.getElementById('twilio_auth_token');
    const fromNumberInput = document.getElementById('twilio_from_number');
    const testBtn         = document.getElementById('twilioTestConnectionBtn');
    const sendTestBtn     = document.getElementById('twilioSendTestSmsBtn');

    if (toggleStatus) {
        toggleStatus.textContent = isEnabled ? 'Enabled' : 'Disabled';
        toggleStatus.style.color = isEnabled ? 'var(--success)' : 'var(--secondary)';
    }

    if (window.isDeveloper) {
        [accountSidInput, authTokenInput, fromNumberInput].forEach(field => {
            if (!field) return;
            if (isEnabled) field.setAttribute('required', 'required');
            else           field.removeAttribute('required');
        });

        if (accountSidInput) validateAccountSid(accountSidInput.value, true);
        if (authTokenInput)  validateAuthToken(authTokenInput.value, true);
        if (fromNumberInput) validateTwilioPhoneNumber(fromNumberInput.value, true);
    }

    if (testBtn)     testBtn.disabled = !isEnabled || !window.isDeveloper;
    if (sendTestBtn) sendTestBtn.disabled = !isEnabled || !window.isDeveloper;

    loadTwilioProviderStatus('twilio');
}

/* ============================================================
 |  Test SMS modal (developer only)
 * ============================================================ */
function initializeTwilioTestSmsModal() {
    const modal        = document.getElementById('twilioTestSmsModal');
    const cancelBtn    = document.getElementById('twilioCancelTestSms');
    const confirmBtn   = document.getElementById('twilioConfirmSendTestSms');
    const messageInput = document.getElementById('twilio_test_message');
    const charCount    = document.getElementById('twilioModalCharCount');
    const phoneInput   = document.getElementById('twilio_test_phone');

    if (!modal) return;

    syncModalFromNumber();

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
        const phone   = document.getElementById('twilio_test_phone').value;
        const message = document.getElementById('twilio_test_message').value;

        if (!phone) { showToast('Please enter a phone number', 'error'); return; }
        if (!/^\+\d{10,15}$/.test(phone)) { showToast('Please enter a valid phone number in E.164 format', 'error'); return; }
        if (!message) { showToast('Please enter a message', 'error'); return; }
        if (message.length > 160) { showToast('Message cannot exceed 160 characters', 'error'); return; }

        sendTestSMS('twilio', phone, message, this);
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) modal.classList.add('hidden');
    });
}

function openTwilioTestSmsModal(provider) {
    const modal       = document.getElementById('twilioTestSmsModal');
    const confirmBtn  = document.getElementById('twilioConfirmSendTestSms');
    const dynamicFrom = document.getElementById('dynamicFromNumber');
    const fromInput   = document.getElementById('twilio_from_number');

    if (!modal || !confirmBtn) return;

    if (dynamicFrom && fromInput) {
        dynamicFrom.textContent = fromInput.value || 'Not set';
    }

    confirmBtn.setAttribute('data-provider', provider);
    modal.classList.remove('hidden');
    document.getElementById('twilio_test_phone')?.focus();
}

/* ============================================================
 |  Connection test results
 * ============================================================ */
function showTwilioConnectionTestResults(provider, result) {
    const container = document.getElementById('twilioConnectionTestResults');
    if (!container) return;

    const html = result.success
        ? `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fab fa-twilio mr-2" style="color: var(--success);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--success);">Twilio Connection Test Successful</h4>
                </div>
                <p class="mt-1 text-sm" style="color: var(--success);">${result.message}</p>
                ${result.details ? `
                <div class="mt-2 text-xs space-y-1" style="color: var(--success);">
                    <p><strong>Account SID:</strong> ${result.details.account_sid ? result.details.account_sid.substring(0, 10) + '…' : 'N/A'}</p>
                    <p><strong>From Number:</strong> ${result.details.from_number || 'N/A'}</p>
                    <p><strong>Status:</strong> ${result.details.api_status || 'N/A'}</p>
                    ${result.details.response_time ? `<p><strong>Response Time:</strong> ${result.details.response_time}ms</p>` : ''}
                </div>` : ''}
            </div>`
        : `
            <div class="p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-center">
                    <i class="fab fa-twilio mr-2" style="color: var(--danger);"></i>
                    <h4 class="text-sm font-semibold" style="color: var(--danger);">Twilio Connection Test Failed</h4>
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
window.initializeTwilioProvider            = initializeTwilioProvider;
window.showTwilioConnectionTestResults     = showTwilioConnectionTestResults;
window.validateTwilioPhoneNumber           = validateTwilioPhoneNumber;
window.validateAccountSid                  = validateAccountSid;
window.validateAuthToken                   = validateAuthToken;
window.loadTwilioProviderStatus            = loadTwilioProviderStatus;
window.updateFromNumberDisplay             = updateFromNumberDisplay;
</script>