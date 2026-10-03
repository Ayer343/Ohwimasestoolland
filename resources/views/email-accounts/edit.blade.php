{{-- email-accounts/edit.blade.php --}}
@php
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperAdmin();
    $isAdmin      = $user->isAdmin();
    $isDeveloper  = $user->isDeveloper();
    $isLandlord   = $user->isLandlord();
    $isTenant     = $user->isTenant();

    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath         = 'super-admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Edit Email Account - Super Admin';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'Edit Email Account - Developer';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'Edit Email Account';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'Edit Email Account';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Edit Email Account';
    }

    $account   = $emailAccount ?? null;
    $backRoute = route($routeNamePrefix . '.index');

    if (!$account) {
        abort(404);
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-edit mr-2" style="color: var(--primary);"></i>
                        Edit Email Account
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-envelope mr-2"></i>
                        <span>{{ $account->email }}</span>
                        @if($account->is_primary)
                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            <i class="fas fa-star mr-1"></i> Primary
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <a href="{{ $backRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Accounts
                </a>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card max-w-4xl mx-auto">
        <div class="p-6">
            <form id="emailAccountForm" method="POST" action="{{ route($routeNamePrefix . '.update', $account->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- ✅ Hidden provider field (kept in sync with the account's stored provider) -->
                <input type="hidden" name="provider" id="provider" value="{{ old('provider', $account->provider ?? 'custom') }}">

                <!-- Email and Password -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-envelope mr-1"></i> Email Address *
                        </label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email', $account->email) }}"
                               class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                               style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                               required>
                        @error('email')
                        <p class="text-xs mt-1 text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-lock mr-1"></i> Password
                            <span class="text-xs font-normal" style="color: var(--text-secondary);">(Leave blank to keep current)</span>
                        </label>
                        <input type="password"
                               name="password"
                               id="password"
                               class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                               style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                               placeholder="Enter new password or leave blank">
                        <div class="flex items-center mt-1">
                            <input type="checkbox"
                                   id="showPasswordToggle"
                                   onclick="togglePassword()"
                                   class="rounded border-gray-300 dark:border-gray-600"
                                   style="accent-color: var(--primary);">
                            <label for="showPasswordToggle" class="ml-2 text-xs" style="color: var(--text-secondary);">Show password</label>
                        </div>
                        @error('password')
                        <p class="text-xs mt-1 text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Display Name -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-1"></i> Display Name
                    </label>
                    <input type="text"
                           name="display_name"
                           value="{{ old('display_name', $account->display_name) }}"
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="Your display name for outgoing emails">
                </div>

                <!-- IMAP Settings -->
                <div class="space-y-4">
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-download mr-2" style="color: var(--primary);"></i> IMAP Settings (Receiving)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    IMAP Host *
                                </label>
                                <input type="text"
                                       name="imap_host"
                                       value="{{ old('imap_host', $account->imap_host) }}"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    IMAP Port
                                </label>
                                <input type="number"
                                       name="imap_port"
                                       value="{{ old('imap_port', $account->imap_port) }}"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    IMAP Encryption
                                </label>
                                <select name="imap_encryption"
                                        class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                        style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="ssl" {{ $account->imap_encryption == 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="tls" {{ $account->imap_encryption == 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="none" {{ $account->imap_encryption == 'none' ? 'selected' : '' }}>None</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Settings -->
                    <div>
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-upload mr-2" style="color: var(--primary);"></i> SMTP Settings (Sending)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    SMTP Host *
                                </label>
                                <input type="text"
                                       name="smtp_host"
                                       value="{{ old('smtp_host', $account->smtp_host) }}"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    SMTP Port
                                </label>
                                <input type="number"
                                       name="smtp_port"
                                       value="{{ old('smtp_port', $account->smtp_port) }}"
                                       class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    SMTP Encryption
                                </label>
                                <select name="smtp_encryption"
                                        class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                        style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="tls" {{ $account->smtp_encryption == 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="ssl" {{ $account->smtp_encryption == 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="none" {{ $account->smtp_encryption == 'none' ? 'selected' : '' }}>None</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sync Settings -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-sync mr-1"></i> Sync Frequency
                    </label>
                    <select name="sync_frequency"
                            class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                            style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="realtime" {{ $account->sync_frequency == 'realtime' ? 'selected' : '' }}>Real-time</option>
                        <option value="every_minute" {{ $account->sync_frequency == 'every_minute' ? 'selected' : '' }}>Every Minute</option>
                        <option value="every_five_minutes" {{ $account->sync_frequency == 'every_five_minutes' ? 'selected' : '' }}>Every 5 Minutes</option>
                        <option value="every_fifteen_minutes" {{ $account->sync_frequency == 'every_fifteen_minutes' ? 'selected' : '' }}>Every 15 Minutes</option>
                        <option value="every_thirty_minutes" {{ $account->sync_frequency == 'every_thirty_minutes' ? 'selected' : '' }}>Every 30 Minutes</option>
                        <option value="hourly" {{ $account->sync_frequency == 'hourly' ? 'selected' : '' }}>Hourly</option>
                        <option value="manual" {{ $account->sync_frequency == 'manual' ? 'selected' : '' }}>Manual Only</option>
                    </select>
                </div>

                <!-- Signature -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-pen mr-1"></i> Email Signature
                    </label>
                    <textarea name="signature"
                              rows="3"
                              class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                              style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                              placeholder="Add your email signature...">{{ old('signature', $account->signature) }}</textarea>
                    <div class="flex items-center mt-1">
                        <input type="checkbox"
                               name="enable_signature"
                               id="enable_signature"
                               value="1"
                               {{ $account->enable_signature ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-gray-600"
                               style="accent-color: var(--primary);">
                        <label for="enable_signature" class="ml-2 text-sm" style="color: var(--text-primary);">
                            Enable signature on outgoing emails
                        </label>
                    </div>
                </div>

                <!-- Auto Reply -->
                <div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox"
                               name="enable_auto_reply"
                               id="enable_auto_reply"
                               value="1"
                               {{ $account->enable_auto_reply ? 'checked' : '' }}
                               onchange="toggleAutoReply()"
                               class="rounded border-gray-300 dark:border-gray-600"
                               style="accent-color: var(--primary);">
                        <label for="enable_auto_reply" class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-reply-all mr-1"></i> Enable Auto Reply
                        </label>
                    </div>
                    <div id="autoReplySettings" class="{{ $account->enable_auto_reply ? '' : 'hidden' }}">
                        <textarea name="auto_reply_message"
                                  rows="3"
                                  class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                  style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                  placeholder="Auto reply message...">{{ old('auto_reply_message', $account->auto_reply_message) }}</textarea>
                    </div>
                </div>

                <!-- Set as Primary -->
                <div class="flex items-center">
                    <input type="checkbox"
                           name="set_as_primary"
                           id="set_as_primary"
                           value="1"
                           {{ $account->is_primary ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600"
                           style="accent-color: var(--primary);">
                    <label for="set_as_primary" class="ml-2 text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-1 text-yellow-500"></i> Set as primary email account
                    </label>
                </div>

                <!-- Verification Button -->
                <div class="flex items-center gap-3">
                    <button type="button"
                            onclick="verifyCredentials()"
                            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-circle mr-2"></i> Verify Credentials
                    </button>
                    <span id="verificationStatus" class="text-sm" style="color: var(--text-secondary);"></span>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="submit"
                            class="inline-flex items-center px-6 py-2 rounded-lg font-medium text-white"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-save mr-2"></i> Update Account
                    </button>
                    <a href="{{ $backRoute }}"
                       class="px-4 py-2 rounded-lg font-medium transition"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verification Loading Overlay -->
<div id="verifyOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-spinner fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Verifying Credentials</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait while we verify your email credentials...</p>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const baseUrl   = '/{{ $urlPath }}';
const csrfToken = '{{ csrf_token() }}';

// ========================================== //
// 🛠️ SHARED API FETCH HELPER                 //
// ========================================== //
async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers || {}),
        },
    });

    const text = await response.text();
    let data;
    try {
        // Strip BOM if present
        const clean = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
        data = clean ? JSON.parse(clean) : {};
    } catch (e) {
        console.error('Non-JSON response from', url, ':', text.slice(0, 300));
        throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
    }

    if (!response.ok) {
        let message = data.message || `HTTP ${response.status}`;
        if (data.errors) {
            const details = Object.entries(data.errors)
                .map(([k, msgs]) => `${k}: ${Array.isArray(msgs) ? msgs.join(', ') : msgs}`)
                .join('; ');
            message += ': ' + details;
        }
        throw new Error(message);
    }

    return data;
}

// ========================================== //
// 🎚️ UI TOGGLES                              //
// ========================================== //
function toggleAutoReply() {
    const checkbox = document.getElementById('enable_auto_reply');
    const settings = document.getElementById('autoReplySettings');
    if (checkbox.checked) {
        settings.classList.remove('hidden');
    } else {
        settings.classList.add('hidden');
    }
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
}

// ========================================== //
// ✅ VERIFY CREDENTIALS                       //
// ========================================== //
function verifyCredentials() {
    const email          = document.getElementById('email').value;
    const password       = document.getElementById('password').value;
    const provider       = document.getElementById('provider').value || 'custom';
    const imapHost       = document.querySelector('[name="imap_host"]').value;
    const smtpHost       = document.querySelector('[name="smtp_host"]').value;
    const imapPort       = document.querySelector('[name="imap_port"]').value;
    const smtpPort       = document.querySelector('[name="smtp_port"]').value;
    const imapEncryption = document.querySelector('[name="imap_encryption"]').value;
    const smtpEncryption = document.querySelector('[name="smtp_encryption"]').value;

    if (!email) {
        showNotification('Please enter an email address', 'error');
        return;
    }

    if (!password) {
        showNotification('Please enter the password to verify (leave blank to keep current)', 'error');
        return;
    }

    if (!imapHost || !smtpHost) {
        showNotification('Please fill in IMAP and SMTP hosts', 'error');
        return;
    }

    const overlay = document.getElementById('verifyOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', password);
    formData.append('provider', provider);
    formData.append('imap_host', imapHost);
    formData.append('imap_port', imapPort || 993);
    formData.append('imap_encryption', imapEncryption || 'ssl');
    formData.append('smtp_host', smtpHost);
    formData.append('smtp_port', smtpPort || 587);
    formData.append('smtp_encryption', smtpEncryption || 'tls');

    fetch(`${baseUrl}/verify`, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
    })
    .then(async response => {
        const text = await response.text();
        let data;
        try {
            const clean = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
            data = clean ? JSON.parse(clean) : {};
        } catch (e) {
            console.error('Non-JSON response from verify:', text.slice(0, 300));
            throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
        }

        if (!response.ok) {
            let message = data.message || `HTTP ${response.status}`;
            if (data.errors) {
                const details = Object.entries(data.errors)
                    .map(([k, msgs]) => `${k}: ${Array.isArray(msgs) ? msgs.join(', ') : msgs}`)
                    .join('; ');
                message += ': ' + details;
            }
            throw new Error(message);
        }

        return data;
    })
    .then(data => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';

        const statusEl = document.getElementById('verificationStatus');

        if (data.success) {
            if (data.warning) {
                statusEl.innerHTML = `
                    <span class="text-yellow-600 font-medium">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Partially Verified
                    </span>
                `;
                showNotification(data.warning, 'warning');
            } else {
                statusEl.innerHTML = `
                    <span class="text-green-600 font-medium">
                        <i class="fas fa-check-circle mr-1"></i> Verified successfully!
                    </span>
                `;
                showNotification('Credentials verified successfully!', 'success');
            }
        } else {
            let errorMsg = data.error || 'Verification failed. Please check your credentials.';
            if (errorMsg.toLowerCase().includes('authentication') ||
                errorMsg.toLowerCase().includes('password') ||
                errorMsg.toLowerCase().includes('login')) {
                errorMsg += ' 💡 If you have 2FA enabled, try using an App Password instead.';
            }

            statusEl.innerHTML = `
                <span class="text-red-600 font-medium">
                    <i class="fas fa-exclamation-circle mr-1"></i> Verification failed
                </span>
            `;
            showNotification(errorMsg, 'error');
        }
    })
    .catch(error => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        showNotification('Verification failed: ' + error.message, 'error');
        console.error('Verify error:', error);
    });
}

// ========================================== //
// 💬 NOTIFICATIONS                           //
// ========================================== //
function showNotification(message, type = 'success') {
    document.querySelectorAll('.custom-notification').forEach(n => n.remove());

    const colors = {
        success: { bg: '#22c55e', icon: 'fa-check-circle' },
        error:   { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info:    { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    const color = colors[type] || colors.info;

    const notification = document.createElement('div');
    notification.className = 'custom-notification fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white';
    notification.style.backgroundColor = color.bg;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
            <button onclick="this.closest('.custom-notification').remove()" class="ml-3 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// 🎨 CSS STYLES                              //
// ========================================== //
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>
@endsection