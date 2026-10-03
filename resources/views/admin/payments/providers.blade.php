{{-- ============================================ --}}
{{-- DYNAMIC LAYOUT SELECTION WITH DEVELOPER SUPPORT --}}
{{-- ============================================ --}}
@php
    // Get the authenticated user
    $user = auth()->user();
    
    // Determine layout based on user type and roles
    $layout = 'layouts.app'; // Default to admin layout
    
    if ($user) {
        // Check if user has developer role OR type 5
        $isDeveloper = $user->hasRole('developer') || $user->type == 5;
        $isAdmin = $user->hasRole('admin') || $user->type == 1;
        $isSuperAdmin = $user->hasRole('super-admin') || $user->type == 0;
        
        // Priority: Developer > Admin > Super Admin (for layout selection)
        if ($isDeveloper) {
            $layout = 'layouts.dev'; // ✅ Use developer layout
        } elseif ($isAdmin || $isSuperAdmin) {
            $layout = 'layouts.app'; // Use admin layout
        }
    }
    
    // Determine user role for display
    $userRole = $user ? (
        $isDeveloper ? 'developer' : (
            $isSuperAdmin ? 'super-admin' : (
                $isAdmin ? 'admin' : 'user'
            )
        )
    ) : 'guest';
@endphp

@extends($layout)

@section('title', 'Payment Provider Configuration')

@section('content')
<div class="space-y-6 animate-fadeInUp">
    <!-- Header Section -->
    <div class="card overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payment Provider Configuration</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Configure and manage your payment gateway integrations</p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 lg:mt-0">
                <button id="refreshStatus" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                </button>
                <button id="verifyEnvironment" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-check-circle mr-2"></i> Verify Environment
                </button>
                <button id="clearStates" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-eraser mr-2"></i> Clear States
                </button>
                <a href="{{ url()->previous() }}" 
                   class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================ --}}
    {{-- ROLE-BASED ACCESS BANNER --}}
    {{-- ============================================ --}}
    @if($userRole === 'developer')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" 
         style="border-color: rgba(var(--info-rgb), 0.2); background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-code text-lg" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--info);">🔧 Developer Full Access Mode</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    You have complete access to all payment provider configurations including sensitive API credentials and test modes.
                    <span class="block mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Using developer layout with full debugging capabilities.
                    </span>
                </p>
            </div>
        </div>
    </div>
    @elseif($userRole === 'super-admin')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" 
         style="border-color: rgba(var(--primary-rgb), 0.3); background-color: rgba(var(--primary-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-crown text-lg" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--primary);">👑 Super Admin Full Access</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    You have full administrative access to all payment provider configurations and system settings.
                </p>
            </div>
        </div>
    </div>
    @elseif($userRole === 'admin')
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm" 
         style="border-color: rgba(var(--primary-rgb), 0.2); background-color: rgba(var(--primary-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-shield-alt text-lg" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--primary);">🛡️ Admin Access Mode</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    You have administrative access to configure payment providers and manage settings.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================ --}}
    {{-- SUCCESS/ERROR MESSAGES --}}
    {{-- ============================================ --}}
    @if(session('success'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" 
         style="border-color: rgba(var(--success-rgb), 0.2); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--success-rgb), 0.1);">
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

    @if(session('error'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" 
         style="border-color: rgba(var(--danger-rgb), 0.2); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
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

    @if(session('warning'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" 
         style="border-color: rgba(var(--warning-rgb), 0.2); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" 
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--warning);">⚠️ Warning!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('warning') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" 
                    onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- ============================================ --}}
    {{-- ENVIRONMENT STATUS BANNER --}}
    {{-- ============================================ --}}
    <div id="environmentStatus" class="hidden relative overflow-hidden rounded-xl border p-4 shadow-sm">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div id="environmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full">
                    <i class="fas fa-server text-lg"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 id="environmentStatusTitle" class="font-semibold"></h4>
                <p id="environmentStatusText" class="mt-1 text-sm" style="color: var(--text-primary);"></p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" 
                    onclick="this.parentElement.parentElement.classList.add('hidden')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- ============================================ --}}
    {{-- MAIN CONTENT CARD --}}
    {{-- ============================================ --}}
    <div class="card overflow-hidden">
        <!-- Provider Navigation -->
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Payment Gateway Configuration</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Select a provider to configure settings</p>
            </div>
            
            <div class="flex flex-wrap gap-2" id="providerTabs" role="tablist">
                <!-- ExpressPay Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors active"
                        style="background-color: rgba(0, 102, 204, 0.1); color: #0066CC; border: 1px solid rgba(0, 102, 204, 0.3);"
                        id="expresspay-tab" 
                        data-tab-target="expresspay" 
                        type="button" 
                        role="tab" 
                        aria-controls="expresspay" 
                        aria-selected="true">
                    <i class="fas fa-credit-card mr-2"></i> ExpressPay
                </button>

                <!-- Flutterwave Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="flutterwave-tab" 
                        data-tab-target="flutterwave" 
                        type="button" 
                        role="tab" 
                        aria-controls="flutterwave" 
                        aria-selected="false">
                    <i class="fas fa-cloud-upload-alt mr-2"></i> Flutterwave
                </button>

                <!-- Hubtel Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="hubtel-tab" 
                        data-tab-target="hubtel" 
                        type="button" 
                        role="tab" 
                        aria-controls="hubtel" 
                        aria-selected="false">
                    <i class="fas fa-phone-alt mr-2"></i> Hubtel
                </button>

                <!-- Paystack Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="paystack-tab" 
                        data-tab-target="paystack" 
                        type="button" 
                        role="tab" 
                        aria-controls="paystack" 
                        aria-selected="false">
                    <i class="fas fa-credit-card mr-2"></i> Paystack
                </button>
            </div>
        </div>

        <!-- Provider Content -->
        <div class="tab-content" id="providerTabsContent">
            <!-- ExpressPay -->
            <div class="tab-pane active p-6" id="expresspay" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">ExpressPay</h3>
                        <p class="text-sm" style="color: var(--text-secondary);">Process mobile money and online payments via ExpressPay Ghana</p>
                    </div>
                </div>
                @include('admin.payments.providers.expresspay')
            </div>

            <!-- Flutterwave -->
            <div class="tab-pane hidden p-6" id="flutterwave" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Flutterwave</h3>
                        <p class="text-sm" style="color: var(--text-secondary);">Process payments via cards, mobile money, bank transfers, and more across Africa</p>
                    </div>
                </div>
                @include('admin.payments.providers.flutterwave')
            </div>

            <!-- Hubtel -->
            <div class="tab-pane hidden p-6" id="hubtel" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Hubtel</h3>
                        <p class="text-sm" style="color: var(--text-secondary);">Process mobile money and payment collections via Hubtel Ghana</p>
                    </div>
                </div>
                @include('admin.payments.providers.hubtel')
            </div>

            <!-- Paystack -->
            <div class="tab-pane hidden p-6" id="paystack" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Paystack</h3>
                        <p class="text-sm" style="color: var(--text-secondary);">Process online payments via cards, bank transfers, and more</p>
                    </div>
                </div>
                @include('admin.payments.providers.paystack')
            </div>
        </div>
    </div>

    <!-- ============================================ --}}
    {{-- WEBHOOK URLS SECTION --}}
    {{-- ============================================ --}}
    <div class="card overflow-hidden">
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Webhook URLs</h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">Copy these webhook URLs to your payment provider dashboards</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="webhookUrlsContainer">
                <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Paystack</span>
                        <code id="webhookUrl-paystack" class="block text-xs mt-1" style="color: var(--text-secondary);">{{ url('/api/payments/paystack/webhook') }}</code>
                    </div>
                    <button onclick="copyWebhookUrl('paystack')" class="px-3 py-1 text-xs rounded hover:opacity-80" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">ExpressPay</span>
                        <code id="webhookUrl-expresspay" class="block text-xs mt-1" style="color: var(--text-secondary);">{{ url('/api/payments/expresspay/webhook') }}</code>
                    </div>
                    <button onclick="copyWebhookUrl('expresspay')" class="px-3 py-1 text-xs rounded hover:opacity-80" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Flutterwave</span>
                        <code id="webhookUrl-flutterwave" class="block text-xs mt-1" style="color: var(--text-secondary);">{{ url('/api/payments/flutterwave/webhook') }}</code>
                    </div>
                    <button onclick="copyWebhookUrl('flutterwave')" class="px-3 py-1 text-xs rounded hover:opacity-80" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Hubtel</span>
                        <code id="webhookUrl-hubtel" class="block text-xs mt-1" style="color: var(--text-secondary);">{{ url('/api/payments/hubtel/webhook') }}</code>
                    </div>
                    <button onclick="copyWebhookUrl('hubtel')" class="px-3 py-1 text-xs rounded hover:opacity-80" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ============================================
// HELPER: Fetch JSON with BOM handling
// ============================================
function fetchJSON(url, options = {}) {
    // Add default headers
    options.headers = {
        ...options.headers,
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
    };
    
    // Add credentials
    options.credentials = 'same-origin';
    
    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            // Remove BOM if present (UTF-8 BOM is \uFEFF)
            if (text.charCodeAt(0) === 0xFEFF || text.substring(0, 1) === '\uFEFF') {
                text = text.substring(1);
            }
            text = text.trim();
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error. Text:', text.substring(0, 200));
                throw new Error('Invalid JSON response: ' + e.message);
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    let currentActiveProvider = 'expresspay';
    let isSubmitting = false;

    // Tab functionality
    const tabs = document.querySelectorAll('[data-tab-target]');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    // Provider color mapping
    const providerColors = {
        'expresspay': {
            bg: 'rgba(0, 102, 204, 0.1)',
            border: 'rgba(0, 102, 204, 0.3)',
            text: '#0066CC',
            icon: 'fa-credit-card'
        },
        'flutterwave': {
            bg: 'rgba(249, 115, 22, 0.1)',
            border: 'rgba(249, 115, 22, 0.3)',
            text: '#F97316',
            icon: 'fa-cloud-upload-alt'
        },
        'hubtel': {
            bg: 'rgba(37, 99, 235, 0.1)',
            border: 'rgba(37, 99, 235, 0.3)',
            text: '#2563EB',
            icon: 'fa-phone-alt'
        },
        'paystack': {
            bg: 'rgba(59, 130, 246, 0.1)',
            border: 'rgba(59, 130, 246, 0.3)',
            text: '#3B82F6',
            icon: 'fa-credit-card'
        }
    };

    // Function to switch tabs
    function switchTab(tab) {
        const target = tab.getAttribute('data-tab-target');
        
        // Update active tab
        tabs.forEach(t => {
            t.style.backgroundColor = 'var(--bg-secondary)';
            t.style.color = 'var(--text-primary)';
            t.style.borderColor = 'var(--border-color)';
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        
        // Style active tab
        const colors = providerColors[target];
        if (colors) {
            tab.style.backgroundColor = colors.bg;
            tab.style.color = colors.text;
            tab.style.borderColor = colors.border;
        }
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        
        // Show active pane and hide others
        tabPanes.forEach(pane => {
            pane.classList.add('hidden');
            pane.classList.remove('active');
            if (pane.id === target) {
                pane.classList.remove('hidden');
                pane.classList.add('active');
            }
        });

        // Update current active provider
        currentActiveProvider = getProviderKeyFromTab(target);
        
        // Verify environment for the active provider
        verifyCurrentEnvironment();
        
        // Load provider config
        loadProviderConfig(target);
    }
    
    // Add click event to all tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            switchTab(tab);
        });
    });

    // Helper function to map tab ID to provider key
    function getProviderKeyFromTab(tabId) {
        const tabMap = {
            'expresspay': 'expresspay',
            'flutterwave': 'flutterwave',
            'hubtel': 'hubtel',
            'paystack': 'paystack'
        };
        return tabMap[tabId] || 'expresspay';
    }

    // ============================================
    // LOAD PROVIDER CONFIG
    // ============================================
    function loadProviderConfig(provider) {
        fetchJSON('{{ route("admin.payment-providers.config") }}?provider=' + provider, {
            method: 'GET'
        })
        .then(data => {
            if (data.success && data.config) {
                updateProviderConfigUI(provider, data.config);
            }
        })
        .catch(error => {
            console.warn('Could not load provider config:', error);
        });
    }

    // ============================================
    // UPDATE PROVIDER CONFIG UI (FIXED)
    // ============================================
    function updateProviderConfigUI(provider, config) {
        // Update status badge
        const badge = document.querySelector(`[data-provider="${provider}"] .status-badge`);
        if (badge) {
            const isEnabled = config.enabled;
            badge.textContent = isEnabled ? '✓ Enabled' : '✕ Disabled';
            badge.className = `status-badge px-2 py-1 text-xs rounded ${isEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
        }
        
        // Update environment indicator
        const envIndicator = document.querySelector(`[data-provider="${provider}"] .env-indicator`);
        if (envIndicator && config.environment) {
            const isProd = config.environment === 'production';
            envIndicator.textContent = isProd ? '🔒 Production' : '🧪 Sandbox';
            envIndicator.className = `env-indicator text-xs ${isProd ? 'text-red-600' : 'text-blue-600'}`;
        }
        
        // Update toggle
        const toggle = document.querySelector(`[data-provider="${provider}"] input[type="checkbox"][name="enabled"]`);
        if (toggle && config.enabled !== undefined) {
            toggle.checked = config.enabled;
            toggle.dispatchEvent(new Event('change'));
        }
    }

    // ============================================
    // COPY WEBHOOK URL
    // ============================================
    function copyWebhookUrl(provider) {
        const codeElement = document.getElementById('webhookUrl-' + provider);
        if (!codeElement) return;
        
        const url = codeElement.textContent.trim();
        
        navigator.clipboard.writeText(url).then(() => {
            showToast(`${getProviderDisplayName(provider)} webhook URL copied to clipboard`, 'success');
        }).catch(() => {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = url;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast(`${getProviderDisplayName(provider)} webhook URL copied to clipboard`, 'success');
        });
    }

    // ============================================
    // VERIFY CURRENT ENVIRONMENT (UPDATED)
    // ============================================
    function verifyCurrentEnvironment() {
        const provider = currentActiveProvider;
        
        fetchJSON('{{ route("admin.payment-providers.verify-environment") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(data => {
            if (data.success) {
                updateEnvironmentStatus(data);
                // Also refresh the full provider status
                refreshProviderStatus(provider);
            } else {
                throw new Error(data.message || 'Failed to verify environment');
            }
        })
        .catch(error => {
            console.error('Error verifying environment:', error);
            showToast('Failed to verify environment: ' + error.message, 'error');
        });
    }

    // ============================================
    // UPDATE ENVIRONMENT STATUS DISPLAY (FIXED)
    // ============================================
    function updateEnvironmentStatus(data) {
        const environmentStatus = document.getElementById('environmentStatus');
        const iconDiv = document.getElementById('environmentStatusIcon');
        const titleEl = document.getElementById('environmentStatusTitle');
        const textEl = document.getElementById('environmentStatusText');
        
        // Check if elements exist
        if (!environmentStatus || !iconDiv || !titleEl || !textEl) {
            console.warn('Environment status elements not found in DOM');
            return;
        }
        
        const isProduction = data.is_production;
        const currentEnv = data.current_environment;
        const provider = getProviderDisplayName(data.provider);
        const tabId = getTabIdFromProvider(data.provider);
        const colors = providerColors[tabId];
        
        if (!colors) return;
        
        // Check if we have actual environment data (from immediate status)
        const actualEnv = data.actual_environment || currentEnv;
        const actualIsProd = data.actual_is_production !== undefined ? data.actual_is_production : isProduction;
        
        // Set colors
        environmentStatus.style.borderColor = actualIsProd 
            ? 'rgba(var(--success-rgb), 0.3)' 
            : colors.border;
        environmentStatus.style.backgroundColor = actualIsProd 
            ? 'rgba(var(--success-rgb), 0.05)' 
            : colors.bg.replace('0.1', '0.05');
        
        iconDiv.style.backgroundColor = actualIsProd 
            ? 'rgba(var(--success-rgb), 0.1)' 
            : colors.bg.replace('0.1', '0.1');
        iconDiv.innerHTML = `<i class="fas ${colors.icon} text-lg" style="color: ${actualIsProd ? 'var(--success)' : colors.text};"></i>`;
        
        titleEl.style.color = actualIsProd ? 'var(--success)' : colors.text;
        titleEl.textContent = `${provider} Environment`;
        
        // Show if there's a mismatch between expected and actual
        const hasMismatch = data.actual_environment && data.current_environment !== data.actual_environment;
        
        textEl.innerHTML = `
            <div class="flex items-center flex-wrap gap-2">
                <span class="font-medium">${actualEnv}</span>
                <span class="px-2 py-1 text-xs rounded" style="background-color: ${actualIsProd ? 'rgba(var(--success-rgb), 0.2)' : 'rgba(var(--primary-rgb), 0.2)'}; color: ${actualIsProd ? 'var(--success)' : 'var(--primary)'};">
                    ${actualIsProd ? '🚀 PRODUCTION' : '🧪 SANDBOX'}
                </span>
                ${data.recently_updated ? '<span class="px-2 py-1 text-xs rounded" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);"><i class="fas fa-clock mr-1"></i> Recently Updated</span>' : ''}
            </div>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas ${actualIsProd ? 'fa-exclamation-circle' : 'fa-check-circle'} mr-1"></i>
                ${actualIsProd ? '⚠️ Real transactions will be processed' : '✅ Test transactions only'}
            </div>
            ${actualIsProd ? `<div class="mt-2 text-xs" style="color: var(--danger);"><i class="fas fa-lock mr-1"></i> Production mode - Credentials are live</div>` : ''}
            ${hasMismatch ? `<div class="mt-2 text-xs" style="color: var(--warning);"><i class="fas fa-exclamation-triangle mr-1"></i> Environment mismatch detected. Please refresh.</div>` : ''}
        `;
        
        environmentStatus.classList.remove('hidden');
    }

    // ============================================
    // REFRESH PROVIDER STATUS (UPDATED)
    // ============================================
    function refreshProviderStatus(provider) {
        fetchJSON('{{ route("admin.payment-providers.immediate-status") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(data => {
            if (data.success && data.data && data.data[provider]) {
                const status = data.data[provider];
                
                // Update the environment banner
                if (status.environment) {
                    const isProduction = status.environment === 'production';
                    updateEnvironmentStatus({
                        is_production: isProduction,
                        current_environment: status.environment,
                        provider: provider,
                        actual_environment: status.actual_environment || status.environment,
                        actual_is_production: status.actual_is_production !== undefined ? status.actual_is_production : isProduction,
                        recently_updated: status.recently_updated || false
                    });
                }
                
                // Update any status indicators in the form
                updateProviderFormStatus(provider, status);
                
                // Dispatch a custom event for other components to listen to
                document.dispatchEvent(new CustomEvent('providerStatusUpdated', {
                    detail: { provider: provider, status: status }
                }));
            }
        })
        .catch(error => {
            console.warn('Could not refresh provider status:', error);
        });
    }

    // ============================================
    // UPDATE PROVIDER FORM STATUS (FIXED)
    // ============================================
    function updateProviderFormStatus(provider, status) {
        // Update enable/disable toggle if it exists
        const toggle = document.querySelector(`[data-provider="${provider}"] input[type="checkbox"][name="enabled"]`);
        if (toggle && status.enabled !== undefined) {
            toggle.checked = status.enabled;
            toggle.dispatchEvent(new Event('change'));
        }
        
        // Update status badge
        const badge = document.querySelector(`[data-provider="${provider}"] .status-badge`);
        if (badge) {
            const isEnabled = status.enabled;
            badge.textContent = isEnabled ? '✓ Enabled' : '✕ Disabled';
            badge.className = `status-badge px-2 py-1 text-xs rounded ${isEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
        }
        
        // Update configuration status
        const configStatus = document.querySelector(`[data-provider="${provider}"] .config-status`);
        if (configStatus) {
            const isConfigured = status.configured;
            configStatus.textContent = isConfigured ? '✓ Configured' : '⚠️ Not Configured';
            configStatus.className = `config-status text-sm ${isConfigured ? 'text-green-600' : 'text-yellow-600'}`;
        }
        
        // Update environment indicator
        const envIndicator = document.querySelector(`[data-provider="${provider}"] .env-indicator`);
        if (envIndicator && status.environment) {
            const isProd = status.environment === 'production';
            envIndicator.textContent = isProd ? '🔒 Production' : '🧪 Sandbox';
            envIndicator.className = `env-indicator text-xs ${isProd ? 'text-red-600' : 'text-blue-600'}`;
        }
        
        // Update submit button state
        const submitBtn = document.querySelector(`[data-provider="${provider}"] button[type="submit"]`);
        if (submitBtn) {
            const isReady = status.enabled && status.configured;
            submitBtn.disabled = !isReady;
            submitBtn.title = isReady ? 'Save configuration' : 'Configure provider first';
        }
    }

    // ============================================
    // TEST CONNECTION (UPDATED)
    // ============================================
    function testConnection(provider, button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        button.disabled = true;

        const card = button.closest('.card');
        if (card) {
            card.classList.add('testing');
        }

        fetchJSON('{{ route("admin.payment-providers.test-connection") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(data => {
            if (data.success) {
                showToast(`${getProviderDisplayName(provider)}: ${data.message}`, 'success');
                if (data.environment) {
                    verifyCurrentEnvironment();
                }
            } else {
                showToast(`${getProviderDisplayName(provider)}: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error testing connection:', error);
            showToast(`Connection test failed: ${error.message}`, 'error');
        })
        .finally(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            if (card) {
                card.classList.remove('testing');
            }
        });
    }

    // ============================================
    // RESET CONFIGURATION (UPDATED)
    // ============================================
    function resetConfiguration(provider, button) {
        const providerName = getProviderDisplayName(provider);
        if (!confirm(`Are you sure you want to reset ${providerName} configuration? This will disable the provider and clear all settings.`)) {
            return;
        }

        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...';
        button.disabled = true;

        fetchJSON('{{ route("admin.payment-providers.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(data => {
            if (data.success) {
                showToast(`${data.message}`, 'success');
                refreshProviderStatus(provider);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showToast(`${data.message}`, 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error resetting configuration:', error);
            showToast('Reset failed: ' + error.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    // ============================================
    // CLEAR STATES
    // ============================================
    function clearAllStates() {
        if (!confirm('Are you sure you want to clear all provider states from cache? This may affect status display.')) {
            return;
        }
        
        fetchJSON('{{ route("admin.payment-providers.clear-states") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ provider: 'all' })
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                refreshProviderStatus(currentActiveProvider);
            } else {
                showToast(data.message || 'Failed to clear states', 'error');
            }
        })
        .catch(error => {
            console.error('Error clearing states:', error);
            showToast('Failed to clear states: ' + error.message, 'error');
        });
    }

    // ============================================
    // SUBMIT PROVIDER FORM (UPDATED)
    // ============================================
    function submitProviderForm(provider, form) {
        if (isSubmitting) {
            return;
        }
        
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        
        const requiredFields = form.querySelectorAll('[required]');
        let hasError = false;
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                hasError = true;
            } else {
                field.style.borderColor = '';
            }
        });
        
        if (hasError) {
            showToast('Please fill in all required fields', 'warning');
            return;
        }
        
        isSubmitting = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
        submitButton.disabled = true;

        const formData = new FormData(form);

        fetchJSON(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(data => {
            if (data && data.success) {
                showToast(data.message || `${getProviderDisplayName(provider)} configuration updated successfully!`, 'success');
                
                refreshProviderStatus(provider);
                
                if (data.is_production !== undefined) {
                    updateEnvironmentStatus({
                        is_production: data.is_production,
                        current_environment: data.environment || 'sandbox',
                        provider: provider,
                        recently_updated: true
                    });
                }
                
                if (data.submitted_config) {
                    updateFormWithSubmittedData(provider, data.submitted_config);
                }
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                }
            } else if (data && !data.success) {
                if (data.errors) {
                    let errorMessages = '';
                    Object.values(data.errors).forEach(messages => {
                        errorMessages += messages.join(', ') + '\n';
                    });
                    showToast(`Validation failed: ${errorMessages}`, 'error');
                } else {
                    showToast(data.message || 'Failed to update configuration', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error updating configuration:', error);
            showToast(`Failed to update configuration: ${error.message}`, 'error');
        })
        .finally(() => {
            isSubmitting = false;
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        });
    }

    // ============================================
    // UPDATE FORM WITH SUBMITTED DATA
    // ============================================
    function updateFormWithSubmittedData(provider, config) {
        const toggle = document.querySelector(`[data-provider="${provider}"] input[type="checkbox"][name="enabled"]`);
        if (toggle && config.enabled !== undefined) {
            toggle.checked = config.enabled;
            toggle.dispatchEvent(new Event('change'));
        }
        
        const envBadge = document.querySelector(`[data-provider="${provider}"] .env-badge`);
        if (envBadge && config.environment) {
            const isProd = config.environment === 'production';
            envBadge.textContent = isProd ? '🔒 Production' : '🧪 Sandbox';
            envBadge.className = `env-badge px-2 py-1 text-xs rounded ${isProd ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}`;
        }
    }

    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    
    function getProviderDisplayName(providerKey) {
        const providers = {
            'expresspay': 'ExpressPay',
            'flutterwave': 'Flutterwave',
            'hubtel': 'Hubtel',
            'paystack': 'Paystack'
        };
        return providers[providerKey] || providerKey;
    }

    function getTabIdFromProvider(providerKey) {
        const providerMap = {
            'expresspay': 'expresspay',
            'flutterwave': 'flutterwave',
            'hubtel': 'hubtel',
            'paystack': 'paystack'
        };
        return providerMap[providerKey] || 'expresspay';
    }

    // ============================================
    // TOAST NOTIFICATION
    // ============================================
    function showToast(message, type = 'info') {
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });

        const toast = document.createElement('div');
        toast.className = `toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
        
        let icon = 'fa-info-circle';
        let bgColor = 'var(--info)';
        
        switch (type) {
            case 'success':
                icon = 'fa-check-circle';
                bgColor = 'var(--success)';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                bgColor = 'var(--danger)';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                bgColor = 'var(--warning)';
                break;
        }
        
        toast.style.backgroundColor = bgColor;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================
    
    // Verify environment button
    document.getElementById('verifyEnvironment').addEventListener('click', function() {
        verifyCurrentEnvironment();
    });

    // Refresh status button
    document.getElementById('refreshStatus').addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        button.disabled = true;
        
        refreshProviderStatus(currentActiveProvider);
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 1500);
    });

    // Clear states button
    document.getElementById('clearStates').addEventListener('click', function() {
        clearAllStates();
    });

    // Test connection buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('test-connection') || e.target.closest('.test-connection')) {
            const button = e.target.classList.contains('test-connection') ? e.target : e.target.closest('.test-connection');
            const provider = button.getAttribute('data-provider');
            testConnection(provider, button);
        }
    });

    // Reset configuration buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('reset-config') || e.target.closest('.reset-config')) {
            const button = e.target.classList.contains('reset-config') ? e.target : e.target.closest('.reset-config');
            const provider = button.getAttribute('data-provider');
            resetConfiguration(provider, button);
        }
    });

    // Form submission handlers
    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('provider-form')) {
            e.preventDefault();
            const provider = e.target.getAttribute('data-provider');
            submitProviderForm(provider, e.target);
        }
    });

    // Auto-hide success/error messages after 5 seconds
    const messages = document.querySelectorAll('.relative.overflow-hidden.rounded-xl.border');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });

    // Handle URL hash for direct tab access
    function checkHash() {
        const hash = window.location.hash.substring(1);
        if (hash) {
            const tab = document.querySelector(`[data-tab-target="${hash}"]`);
            if (tab) {
                switchTab(tab);
            }
        }
    }
    
    checkHash();
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab-target');
            window.location.hash = target;
        });
    });

    // Custom event listener
    document.addEventListener('providerStatusUpdated', function(e) {
        const { provider, status } = e.detail;
        console.log(`Provider ${provider} status updated:`, status);
    });

    // Initial status check
    setTimeout(() => {
        refreshProviderStatus(currentActiveProvider);
    }, 1000);
});
</script>

<style>
/* Card styling */
.card {
    border-radius: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Tab styling */
.tab-pane {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Status cards styling */
.card.testing {
    animation: pulse 2s infinite;
    border-color: rgba(var(--primary-rgb), 0.5) !important;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

/* Required field indicator */
.required::after {
    content: " *";
    color: var(--danger);
}

/* Loading animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Toast notifications */
.toast-notification {
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    border-radius: 0.75rem;
}

/* Fade in animation */
.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Developer badge styling */
.developer-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* Responsive design */
@media (max-width: 768px) {
    #providerTabs {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    #providerTabs button {
        width: 100%;
        justify-content: flex-start;
    }
    
    .tab-pane {
        padding: 1rem !important;
    }
    
    .tab-pane .flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
}

@media (max-width: 480px) {
    .flex-col.lg\\:flex-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .flex-wrap.gap-2 {
        gap: 0.5rem;
    }
    
    .toast-notification {
        min-width: 280px;
        max-width: 320px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        right: auto;
    }
}

/* Smooth transitions */
* {
    transition: color 0.3s, background-color 0.3s, border-color 0.3s;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Form validation styling */
input:invalid, select:invalid, textarea:invalid {
    border-color: #dc3545 !important;
}

input:valid, select:valid, textarea:valid {
    border-color: var(--border-color) !important;
}

/* Webhook URL styling */
code {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    word-break: break-all;
}
</style>
@endsection