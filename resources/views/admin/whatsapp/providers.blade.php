@extends('layouts.dev')

@section('title', 'WhatsApp Provider Configuration')

@section('content')
<div class="space-y-6 animate-fadeInUp">
    <!-- Header Section -->
    <div class="card overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">WhatsApp Provider Configuration</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Configure and manage your WhatsApp messaging integrations</p>
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
                <button id="sendTestMessage" 
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors hidden"
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-paper-plane mr-2"></i> Send Test Message
                </button>
                <a href="{{ url()->previous() }}" 
                   class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(var(--success-rgb), 0.2); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--success);">Success!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
                @if(session('provider'))
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Provider: {{ ucfirst(session('provider')) }}
                    </p>
                @endif
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(var(--danger-rgb), 0.2); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--danger);">Error!</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('warning'))
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(var(--warning-rgb), 0.2); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--warning);">Notice</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('warning') }}</p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Pending Updates Notification -->
    @if($hasPendingUpdate)
    <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm fade-in" style="border-color: rgba(var(--warning-rgb), 0.2); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 class="font-semibold" style="color: var(--warning);">Pending Configuration Update</h4>
                <p class="mt-1 text-sm" style="color: var(--text-primary);">
                    There is a pending WhatsApp configuration update that needs to be processed.
                    @if($pendingUpdate)
                        Provider: {{ ucfirst($pendingUpdate['provider'] ?? 'unknown') }}
                    @endif
                </p>
                <div class="mt-2">
                    <a href="{{ route('admin.whatsapp-providers.retry') }}" 
                       class="inline-flex items-center text-sm font-medium hover:opacity-80"
                       style="color: var(--warning);">
                        <i class="fas fa-redo mr-1"></i> Process Now
                    </a>
                </div>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Environment Status Banner -->
    <div id="environmentStatus" class="hidden relative overflow-hidden rounded-xl border p-4 shadow-sm">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div id="environmentStatusIcon" class="flex h-10 w-10 items-center justify-center rounded-full">
                    <i class="fab fa-whatsapp text-lg"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h4 id="environmentStatusTitle" class="font-semibold"></h4>
                <p id="environmentStatusText" class="mt-1 text-sm" style="color: var(--text-primary);"></p>
            </div>
            <button type="button" class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.classList.add('hidden')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Status Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4" id="providerStatusCards">
        <!-- Loading state -->
        <div class="col-span-1 md:col-span-2 lg:col-span-3 xl:col-span-5 flex justify-center items-center py-8">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-3" style="color: var(--primary);"></i>
                <span style="color: var(--text-secondary);">Loading WhatsApp provider status...</span>
            </div>
        </div>
    </div>

    <!-- System Status Card -->
    <div class="card overflow-hidden">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Status</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Current WhatsApp messaging system health and status</p>
                </div>
                <div id="systemStatusIndicator" class="flex items-center px-3 py-1 rounded-full">
                    <i class="fas fa-circle mr-2 text-xs"></i>
                    <span class="text-sm font-medium">Loading...</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Active Provider</p>
                            <p id="activeProvider" class="text-lg font-semibold mt-1" style="color: var(--text-primary);">--</p>
                        </div>
                        <i class="fas fa-server text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">WhatsApp Enabled</p>
                            <p id="whatsappEnabled" class="text-lg font-semibold mt-1" style="color: var(--text-primary);">--</p>
                        </div>
                        <i class="fab fa-whatsapp text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Configuration</p>
                            <p id="configurationStatus" class="text-lg font-semibold mt-1" style="color: var(--text-primary);">--</p>
                        </div>
                        <i class="fas fa-cog text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
                <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Can Send Messages</p>
                            <p id="canSendMessages" class="text-lg font-semibold mt-1" style="color: var(--text-primary);">--</p>
                        </div>
                        <i class="fas fa-paper-plane text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card overflow-hidden">
        <!-- Provider Navigation with Logos -->
        <div class="border-b p-6" style="border-color: var(--border-color);">
            <div class="mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">WhatsApp Provider Configuration</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Select a provider to configure settings (only one can be active at a time)</p>
            </div>
            
            <div class="flex flex-wrap gap-2" id="providerTabs" role="tablist">
                <!-- VumaAPI Tab (Ghana) -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors active"
                        style="background-color: rgba(206, 17, 38, 0.1); color: #CE1126; border: 1px solid rgba(206, 17, 38, 0.3);"
                        id="vumaapi-tab"
                        data-tab-target="vumaapi"
                        type="button"
                        role="tab"
                        aria-controls="vumaapi"
                        aria-selected="true">
                    <span class="inline-flex items-center justify-center h-5 w-5 mr-2 rounded" style="background-color: rgba(206, 17, 38, 0.15);">
                        <i class="fas fa-globe-africa text-xs" style="color: #CE1126;"></i>
                    </span>
                    VumaAPI <span class="ml-1 text-xs opacity-70">GH</span>
                </button>

                <!-- Vonage Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="vonage-tab" 
                        data-tab-target="vonage" 
                        type="button" 
                        role="tab" 
                        aria-controls="vonage" 
                        aria-selected="false">
                    <img src="{{ asset('images/whatsapp/vonage-logo.png') }}" alt="Vonage Logo" class="h-5 w-auto mr-2" onerror="this.onerror=null; this.src='https://cdn.worldvectorlogo.com/logos/vonage-1.svg'; this.style.height='1.25rem';">
                    Vonage WhatsApp
                </button>

                <!-- 360Dialog Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="360dialog-tab" 
                        data-tab-target="360dialog" 
                        type="button" 
                        role="tab" 
                        aria-controls="360dialog" 
                        aria-selected="false">
                    <img src="{{ asset('images/whatsapp/360dialog-logo.png') }}" alt="360Dialog Logo" class="h-5 w-auto mr-2" onerror="this.onerror=null; this.src='https://cdn.worldvectorlogo.com/logos/360dialog.svg'; this.style.height='1.25rem';">
                    360Dialog
                </button>

                <!-- WATI Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="wati-tab" 
                        data-tab-target="wati" 
                        type="button" 
                        role="tab" 
                        aria-controls="wati" 
                        aria-selected="false">
                    <img src="{{ asset('images/whatsapp/wati-logo.png') }}" alt="WATI Logo" class="h-5 w-auto mr-2" onerror="this.onerror=null; this.src='https://static.wixstatic.com/media/0ca1af_cf4562487b2a47e1b5c789df38eb5b72~mv2.png'; this.style.height='1.25rem';">
                    WATI
                </button>

                <!-- Custom API Tab -->
                <button class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                        id="custom-tab" 
                        data-tab-target="custom" 
                        type="button" 
                        role="tab" 
                        aria-controls="custom" 
                        aria-selected="false">
                    <i class="fas fa-code mr-2"></i>
                    Custom API
                </button>
            </div>
        </div>

        <!-- Provider Content -->
        <div class="tab-content" id="providerTabsContent">
            <!-- VumaAPI WhatsApp (Ghana) -->
            <div class="tab-pane active p-6" id="vumaapi" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="h-12 w-12 flex items-center justify-center rounded-lg mr-4" style="background-color: rgba(206, 17, 38, 0.1);">
                                <i class="fas fa-globe-africa text-2xl" style="color: #CE1126;"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">VumaAPI WhatsApp</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">Ghana-focused WhatsApp API with built-in sandbox</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span id="vumaapi-status-badge" class="px-3 py-1 text-xs rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <i class="fas fa-circle mr-1 text-xs"></i> Loading...
                            </span>
                            <button class="enable-provider-btn" data-provider="vumaapi" data-enabled="false">
                                <span class="toggle-switch">
                                    <span class="toggle-slider"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @include('admin.whatsapp.providers.vumaapi')
            </div>

            <!-- Vonage WhatsApp -->
            <div class="tab-pane hidden p-6" id="vonage" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <img src="{{ asset('images/whatsapp/vonage-logo.png') }}" alt="Vonage Logo" class="h-12 w-auto mr-4" onerror="this.onerror=null; this.src='https://cdn.worldvectorlogo.com/logos/vonage-1.svg'; this.style.height='3rem';">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Vonage WhatsApp</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">Formerly Nexmo - WhatsApp Business API integration</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span id="vonage-status-badge" class="px-3 py-1 text-xs rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <i class="fas fa-circle mr-1 text-xs"></i> Loading...
                            </span>
                            <button class="enable-provider-btn" data-provider="vonage" data-enabled="false">
                                <span class="toggle-switch">
                                    <span class="toggle-slider"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @include('admin.whatsapp.providers.vonage')
            </div>

            <!-- 360Dialog WhatsApp -->
            <div class="tab-pane hidden p-6" id="360dialog" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <img src="{{ asset('images/whatsapp/360dialog-logo.png') }}" alt="360Dialog Logo" class="h-12 w-auto mr-4" onerror="this.onerror=null; this.src='https://cdn.worldvectorlogo.com/logos/360dialog.svg'; this.style.height='3rem';">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">360Dialog WhatsApp</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">Official WhatsApp Business API solution provider</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span id="360dialog-status-badge" class="px-3 py-1 text-xs rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <i class="fas fa-circle mr-1 text-xs"></i> Loading...
                            </span>
                            <button class="enable-provider-btn" data-provider="360dialog" data-enabled="false">
                                <span class="toggle-switch">
                                    <span class="toggle-slider"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @include('admin.whatsapp.providers.360dialog')
            </div>

            <!-- WATI WhatsApp -->
            <div class="tab-pane hidden p-6" id="wati" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <img src="{{ asset('images/whatsapp/wati-logo.png') }}" alt="WATI Logo" class="h-12 w-auto mr-4" onerror="this.onerror=null; this.src='https://static.wixstatic.com/media/0ca1af_cf4562487b2a47e1b5c789df38eb5b72~mv2.png'; this.style.height='3rem';">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">WATI WhatsApp</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">WhatsApp Business API with CRM features</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span id="wati-status-badge" class="px-3 py-1 text-xs rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <i class="fas fa-circle mr-1 text-xs"></i> Loading...
                            </span>
                            <button class="enable-provider-btn" data-provider="wati" data-enabled="false">
                                <span class="toggle-switch">
                                    <span class="toggle-slider"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @include('admin.whatsapp.providers.wati')
            </div>

            <!-- Custom API -->
            <div class="tab-pane hidden p-6" id="custom" role="tabpanel">
                <div class="mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="h-12 w-12 flex items-center justify-center rounded-lg mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-code text-2xl" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Custom WhatsApp API</h3>
                                <p class="text-sm" style="color: var(--text-secondary);">Integrate with custom or self-hosted WhatsApp solutions</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span id="custom-status-badge" class="px-3 py-1 text-xs rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                <i class="fas fa-circle mr-1 text-xs"></i> Loading...
                            </span>
                            <button class="enable-provider-btn" data-provider="custom" data-enabled="false">
                                <span class="toggle-switch">
                                    <span class="toggle-slider"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @include('admin.whatsapp.providers.custom')
            </div>
        </div>
    </div>

    <!-- Test Message Modal -->
    <div id="testMessageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Send Test WhatsApp Message</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="document.getElementById('testMessageModal').classList.add('hidden')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Phone Number</label>
                        <input type="text" 
                               id="testPhoneNumber" 
                               class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50"
                               placeholder="whatsapp:+233XXXXXXXXX"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Format: whatsapp:+233XXXXXXXXX</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Message</label>
                        <textarea id="testMessage" 
                                  class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-opacity-50" 
                                  rows="3"
                                  placeholder="Enter test message content..."
                                  style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"></textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Max 1000 characters. Current: <span id="charCount">0</span></p>
                    </div>
                    <div class="flex items-center space-x-2 pt-4">
                        <input type="checkbox" id="queueMessage" class="rounded">
                        <label for="queueMessage" class="text-sm" style="color: var(--text-secondary);">Queue in background</label>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" 
                            class="px-4 py-2 rounded-lg font-medium transition-colors"
                            onclick="document.getElementById('testMessageModal').classList.add('hidden')"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                        Cancel
                    </button>
                    <button type="button" 
                            id="sendTestMessageBtn"
                            class="px-4 py-2 rounded-lg font-medium transition-colors"
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentActiveProvider = 'vumaapi';
    let providers = {!! json_encode($providers) !!};

    // Tab functionality
    const tabs = document.querySelectorAll('[data-tab-target]');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    // Provider color mapping
    const providerColors = {
        'vumaapi': {
            bg: 'rgba(206, 17, 38, 0.1)',
            border: 'rgba(206, 17, 38, 0.3)',
            text: '#CE1126',
            icon: 'fa-globe-africa'
        },
        'vonage': {
            bg: 'rgba(0, 169, 157, 0.1)',
            border: 'rgba(0, 169, 157, 0.3)',
            text: '#00A99D',
            icon: 'fa-comments'
        },
        '360dialog': {
            bg: 'rgba(66, 133, 244, 0.1)',
            border: 'rgba(66, 133, 244, 0.3)',
            text: '#4285F4',
            icon: 'fa-check-circle'
        },
        'wati': {
            bg: 'rgba(255, 87, 34, 0.1)',
            border: 'rgba(255, 87, 34, 0.3)',
            text: '#FF5722',
            icon: 'fa-headset'
        },
        'custom': {
            bg: 'rgba(156, 39, 176, 0.1)',
            border: 'rgba(156, 39, 176, 0.3)',
            text: '#9C27B0',
            icon: 'fa-code'
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
        currentActiveProvider = target;
        
        // Update test message button visibility
        updateTestMessageButton();
        
        // Update provider status badges
        updateProviderStatusBadges();
    }
    
    // Add click event to all tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            switchTab(tab);
        });
    });

    // Load provider status immediately on page load
    loadProviderStatus();
    loadSystemStatus();

    // Refresh status button
    document.getElementById('refreshStatus').addEventListener('click', function() {
        loadProviderStatus();
        loadSystemStatus();
        verifyCurrentEnvironment();
        showToast('Status refreshed successfully', 'success');
    });

    // Verify environment button
    document.getElementById('verifyEnvironment').addEventListener('click', function() {
        verifyCurrentEnvironment();
    });

    // Send test message button
    document.getElementById('sendTestMessage').addEventListener('click', function() {
        showTestMessageModal();
    });

    // Test connection buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('test-connection') || e.target.closest('.test-connection')) {
            const button = e.target.classList.contains('test-connection') ? e.target : e.target.closest('.test-connection');
            const provider = button.getAttribute('data-provider');
            testConnection(provider, button);
        }
    });

    // Toggle provider buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('enable-provider-btn') || e.target.closest('.enable-provider-btn')) {
            const button = e.target.classList.contains('enable-provider-btn') ? e.target : e.target.closest('.enable-provider-btn');
            const provider = button.getAttribute('data-provider');
            const currentlyEnabled = button.getAttribute('data-enabled') === 'true';
            toggleProvider(provider, !currentlyEnabled, button);
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

    // Character count for test message
    document.getElementById('testMessage')?.addEventListener('input', function() {
        document.getElementById('charCount').textContent = this.value.length;
    });

    // Send test message from modal
    document.getElementById('sendTestMessageBtn')?.addEventListener('click', function() {
        sendTestMessage();
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
    
    // Check hash on page load
    checkHash();
    
    // Update hash when tabs are clicked
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab-target');
            window.location.hash = target;
        });
    });

    // Load provider status from API
    function loadProviderStatus() {
        const cardsContainer = document.getElementById('providerStatusCards');
        
        // Show loading state
        cardsContainer.innerHTML = `
            <div class="col-span-1 md:col-span-2 lg:col-span-3 xl:col-span-5 flex justify-center items-center py-8">
                <div class="flex items-center">
                    <i class="fas fa-spinner fa-spin mr-3" style="color: var(--primary);"></i>
                    <span style="color: var(--text-secondary);">Loading WhatsApp provider status...</span>
                </div>
            </div>
        `;

        fetch('{{ route("admin.whatsapp-providers.status") }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateStatusCards(data.data.configuration);
                    updateSystemStatus(data.data.system);
                } else {
                    throw new Error(data.message || 'Failed to load provider status');
                }
            })
            .catch(error => {
                console.error('Error loading provider status:', error);
                cardsContainer.innerHTML = `
                    <div class="col-span-1 md:col-span-2 lg:col-span-3 xl:col-span-5 flex justify-center items-center py-8">
                        <div class="text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full mx-auto mb-2" style="background-color: rgba(var(--danger-rgb), 0.1);">
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                            </div>
                            <p style="color: var(--danger);" class="mb-2">Failed to load provider status</p>
                            <button onclick="loadProviderStatus()" class="text-sm hover:opacity-80" style="color: var(--primary);">
                                <i class="fas fa-redo mr-1"></i> Retry
                            </button>
                        </div>
                    </div>
                `;
                showToast('Error loading provider status', 'error');
            });
    }

    // Load system status
    function loadSystemStatus() {
        fetch('{{ route("admin.whatsapp-providers.configuration-status") }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateSystemStatus(data.data.system);
                }
            })
            .catch(error => {
                console.error('Error loading system status:', error);
            });
    }

    // Update status cards
    function updateStatusCards(statusData) {
        const cardsContainer = document.getElementById('providerStatusCards');
        const providers = {
            'vumaapi': { 
                name: 'VumaAPI', 
                logo: null,
                color: '#CE1126',
                icon: 'fa-globe-africa'
            },
            'vonage': { 
                name: 'Vonage', 
                logo: '{{ asset("images/whatsapp/vonage-logo.png") }}',
                color: '#00A99D',
                icon: 'fa-comments'
            },
            '360dialog': { 
                name: '360Dialog', 
                logo: '{{ asset("images/whatsapp/360dialog-logo.png") }}',
                color: '#4285F4',
                icon: 'fa-check-circle'
            },
            'wati': { 
                name: 'WATI', 
                logo: '{{ asset("images/whatsapp/wati-logo.png") }}',
                color: '#FF5722',
                icon: 'fa-headset'
            },
            'custom': { 
                name: 'Custom API', 
                logo: null,
                color: '#9C27B0',
                icon: 'fa-code'
            }
        };

        let cardsHTML = '';

        Object.entries(providers).forEach(([key, provider]) => {
            const status = statusData[key] || { 
                enabled: false, 
                configured: false,
                is_active: false,
                missing_configuration: [],
                environment: 'unknown'
            };
            
            const isEnabled = status.enabled;
            const isActive = status.is_active;
            const isConfigured = status.configured;
            const environment = status.environment || 'unknown';
            
            let statusText, statusColor, statusIcon, statusDescription, borderColor, bgColor;
            
            if (isActive) {
                statusText = 'Active';
                statusColor = 'var(--success)';
                statusIcon = 'fa-check-circle';
                statusDescription = `Currently active provider in ${environment}`;
                borderColor = 'rgba(var(--success-rgb), 0.3)';
                bgColor = 'rgba(var(--success-rgb), 0.05)';
            } else if (isEnabled && isConfigured) {
                statusText = 'Ready';
                statusColor = 'var(--primary)';
                statusIcon = 'fa-check';
                statusDescription = `Configured and ready in ${environment}`;
                borderColor = 'rgba(var(--primary-rgb), 0.3)';
                bgColor = 'rgba(var(--primary-rgb), 0.05)';
            } else if (!isEnabled) {
                statusText = 'Disabled';
                statusColor = 'var(--danger)';
                statusIcon = 'fa-times-circle';
                statusDescription = 'Provider is disabled';
                borderColor = 'rgba(var(--danger-rgb), 0.3)';
                bgColor = 'rgba(var(--danger-rgb), 0.05)';
            } else if (!isConfigured) {
                statusText = 'Incomplete';
                statusColor = 'var(--warning)';
                statusIcon = 'fa-exclamation-circle';
                statusDescription = `Missing configuration`;
                borderColor = 'rgba(var(--warning-rgb), 0.3)';
                bgColor = 'rgba(var(--warning-rgb), 0.05)';
            }

            // Update toggle button in tab
            updateProviderToggleButton(key, isActive, isEnabled);

            cardsHTML += `
                <div class="card overflow-hidden" style="border-color: ${borderColor};">
                    <div class="flex items-center justify-between p-4" style="background-color: ${bgColor};">
                        <div class="flex items-center">
                            ${provider.logo ? 
                                `<img src="${provider.logo}" alt="${provider.name}" class="h-6 w-auto mr-3" onerror="this.onerror=null; this.src='https://via.placeholder.com/24/333/fff?text=${provider.name.charAt(0)}'; this.style.height='1.5rem';">` :
                                `<div class="h-6 w-6 flex items-center justify-center rounded mr-3" style="background-color: ${provider.color}33; color: ${provider.color};">` +
                                `<i class="fas ${provider.icon} text-xs"></i></div>`
                            }
                            <span class="font-semibold" style="color: var(--text-primary);">${provider.name}</span>
                        </div>
                        <i class="fas ${statusIcon} text-lg" style="color: ${statusColor};"></i>
                    </div>
                    <div class="p-4">
                        <div class="text-sm mb-3">
                            <div class="font-medium mb-1" style="color: ${statusColor};">${statusText}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">${statusDescription}</div>
                        </div>
                        <div class="flex space-x-2">
                            <button class="test-connection text-xs px-3 py-1 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed" 
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                    data-provider="${key}" ${!isEnabled ? 'disabled' : ''}>
                                <i class="fas fa-plug mr-1"></i> Test
                            </button>
                            <button class="reset-config text-xs px-3 py-1 rounded transition-colors" 
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                    data-provider="${key}">
                                <i class="fas fa-trash-alt mr-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        cardsContainer.innerHTML = cardsHTML;
    }

    // Update system status display
    function updateSystemStatus(systemData) {
        const systemStatusIndicator = document.getElementById('systemStatusIndicator');
        const activeProviderEl = document.getElementById('activeProvider');
        const whatsappEnabledEl = document.getElementById('whatsappEnabled');
        const configurationStatusEl = document.getElementById('configurationStatus');
        const canSendMessagesEl = document.getElementById('canSendMessages');

        const status = systemData.health || 'unknown';
        const activeProvider = systemData.active_provider || 'none';
        const enabled = systemData.enabled || false;
        const configuration = systemData.configuration || 'unknown';
        const canSend = systemData.can_send_messages || false;

        // Update status indicator
        let statusColor, statusText, statusIcon;
        switch (status) {
            case 'healthy':
                statusColor = 'var(--success)';
                statusText = 'Healthy';
                statusIcon = 'fa-check-circle';
                break;
            case 'degraded':
                statusColor = 'var(--warning)';
                statusText = 'Degraded';
                statusIcon = 'fa-exclamation-triangle';
                break;
            case 'unhealthy':
                statusColor = 'var(--danger)';
                statusText = 'Unhealthy';
                statusIcon = 'fa-times-circle';
                break;
            default:
                statusColor = 'var(--secondary)';
                statusText = 'Unknown';
                statusIcon = 'fa-question-circle';
        }

        systemStatusIndicator.innerHTML = `
            <i class="fas ${statusIcon} mr-2" style="color: ${statusColor};"></i>
            <span style="color: ${statusColor};">${statusText}</span>
        `;

        // Update other fields
        activeProviderEl.textContent = activeProvider === 'none' ? 'None' : getProviderDisplayName(activeProvider);
        activeProviderEl.style.color = activeProvider === 'none' ? 'var(--danger)' : 'var(--success)';
        
        whatsappEnabledEl.textContent = enabled ? 'Yes' : 'No';
        whatsappEnabledEl.style.color = enabled ? 'var(--success)' : 'var(--danger)';
        
        configurationStatusEl.textContent = configuration;
        configurationStatusEl.style.color = configuration === 'complete' ? 'var(--success)' : 
                                          configuration === 'partial' ? 'var(--warning)' : 'var(--danger)';
        
        canSendMessagesEl.textContent = canSend ? 'Yes' : 'No';
        canSendMessagesEl.style.color = canSend ? 'var(--success)' : 'var(--danger)';

        // Update test message button visibility
        updateTestMessageButton();
    }

    // Update provider status badges
    function updateProviderStatusBadges() {
        Object.keys(providerColors).forEach(provider => {
            const badge = document.getElementById(`${provider}-status-badge`);
            const toggleBtn = document.querySelector(`.enable-provider-btn[data-provider="${provider}"]`);
            
            if (badge && providers[provider]) {
                const providerData = providers[provider];
                const isEnabled = providerData.enabled;
                const isActive = providerData.is_active;
                
                let statusText, statusColor, statusIcon;
                
                if (isActive) {
                    statusText = 'Active';
                    statusColor = 'var(--success)';
                    statusIcon = 'fa-check-circle';
                    badge.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                    badge.style.color = 'var(--success)';
                } else if (isEnabled) {
                    statusText = 'Enabled';
                    statusColor = 'var(--primary)';
                    statusIcon = 'fa-check';
                    badge.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
                    badge.style.color = 'var(--primary)';
                } else {
                    statusText = 'Disabled';
                    statusColor = 'var(--danger)';
                    statusIcon = 'fa-times-circle';
                    badge.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
                    badge.style.color = 'var(--danger)';
                }
                
                badge.innerHTML = `
                    <i class="fas ${statusIcon} mr-1 text-xs"></i> ${statusText}
                `;
                
                // Update toggle button
                if (toggleBtn) {
                    toggleBtn.setAttribute('data-enabled', isEnabled.toString());
                    const slider = toggleBtn.querySelector('.toggle-slider');
                    if (slider) {
                        if (isEnabled) {
                            toggleBtn.classList.add('active');
                            slider.style.transform = 'translateX(20px)';
                        } else {
                            toggleBtn.classList.remove('active');
                            slider.style.transform = 'translateX(0)';
                        }
                    }
                }
            }
        });
    }

    // Update provider toggle button
    function updateProviderToggleButton(provider, isActive, isEnabled) {
        const toggleBtn = document.querySelector(`.enable-provider-btn[data-provider="${provider}"]`);
        if (toggleBtn) {
            toggleBtn.setAttribute('data-enabled', isEnabled.toString());
            const slider = toggleBtn.querySelector('.toggle-slider');
            if (slider) {
                if (isActive) {
                    toggleBtn.classList.add('active');
                    slider.style.transform = 'translateX(20px)';
                    slider.style.backgroundColor = 'var(--success)';
                } else if (isEnabled) {
                    toggleBtn.classList.add('active');
                    slider.style.transform = 'translateX(20px)';
                    slider.style.backgroundColor = 'var(--primary)';
                } else {
                    toggleBtn.classList.remove('active');
                    slider.style.transform = 'translateX(0)';
                    slider.style.backgroundColor = 'var(--danger)';
                }
            }
        }
    }

    // Update test message button visibility
    function updateTestMessageButton() {
        const sendTestBtn = document.getElementById('sendTestMessage');
        const providerData = providers[currentActiveProvider];
        
        if (sendTestBtn && providerData && providerData.enabled) {
            sendTestBtn.classList.remove('hidden');
        } else {
            sendTestBtn.classList.add('hidden');
        }
    }

    // Verify current environment
    function verifyCurrentEnvironment() {
        const provider = currentActiveProvider;
        
        fetch('{{ route("admin.whatsapp-providers.verify-environment") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateEnvironmentStatus(data);
            } else {
                throw new Error(data.message || 'Failed to verify environment');
            }
        })
        .catch(error => {
            console.error('Error verifying environment:', error);
            showToast('Failed to verify environment', 'error');
        });
    }

    // Update environment status display
    function updateEnvironmentStatus(data) {
        const environmentStatus = document.getElementById('environmentStatus');
        const iconDiv = document.getElementById('environmentStatusIcon');
        const titleEl = document.getElementById('environmentStatusTitle');
        const textEl = document.getElementById('environmentStatusText');
        
        const isProduction = data.is_production;
        const currentEnv = data.current_environment;
        const provider = getProviderDisplayName(data.provider);
        const colors = providerColors[currentActiveProvider];
        
        if (!colors) return;
        
        // Set colors
        environmentStatus.style.borderColor = isProduction 
            ? 'rgba(var(--success-rgb), 0.3)' 
            : colors.border;
        environmentStatus.style.backgroundColor = isProduction 
            ? 'rgba(var(--success-rgb), 0.05)' 
            : colors.bg.replace('0.1', '0.05');
        
        iconDiv.style.backgroundColor = isProduction 
            ? 'rgba(var(--success-rgb), 0.1)' 
            : colors.bg.replace('0.1', '0.1');
        iconDiv.innerHTML = `<i class="fab fa-whatsapp text-lg" style="color: ${isProduction ? 'var(--success)' : colors.text};"></i>`;
        
        titleEl.style.color = isProduction ? 'var(--success)' : colors.text;
        titleEl.textContent = `${provider} Environment`;
        
        textEl.innerHTML = `
            <div class="flex items-center">
                <span class="font-medium">${currentEnv || 'Not configured'}</span>
                ${currentEnv ? `<span class="ml-2 px-2 py-1 text-xs rounded" style="background-color: ${isProduction ? 'rgba(var(--success-rgb), 0.2)' : 'rgba(var(--primary-rgb), 0.2)'}; color: ${isProduction ? 'var(--success)' : 'var(--primary)'};">
                    ${isProduction ? 'PRODUCTION' : 'SANDBOX'}
                </span>` : ''}
            </div>
            ${currentEnv ? `<div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas ${isProduction ? 'fa-exclamation-circle' : 'fa-check-circle'} mr-1"></i>
                ${isProduction ? 'Real messages will be sent' : 'Test messages only'}
            </div>` : ''}
        `;
        
        environmentStatus.classList.remove('hidden');
    }

    // Toggle provider function
    function toggleProvider(provider, enable, button) {
        if (!enable && providers[provider]?.is_active) {
            if (!confirm('This provider is currently active. Disabling it will stop WhatsApp messaging. Are you sure?')) {
                return;
            }
        }

        const originalSlider = button.querySelector('.toggle-slider').cloneNode(true);
        button.disabled = true;

        fetch('{{ route("admin.whatsapp-providers.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                provider: provider,
                enable: enable 
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(`${getProviderDisplayName(provider)} ${enable ? 'enabled' : 'disabled'} successfully!`, 'success');
                // Refresh status
                setTimeout(() => {
                    loadProviderStatus();
                    loadSystemStatus();
                }, 1000);
            } else {
                throw new Error(data.message || 'Failed to toggle provider');
            }
        })
        .catch(error => {
            console.error('Error toggling provider:', error);
            showToast(`Failed to toggle provider: ${error.message}`, 'error');
            // Revert toggle button
            button.querySelector('.toggle-slider').replaceWith(originalSlider);
        })
        .finally(() => {
            button.disabled = false;
        });
    }

    // Test connection function
    function testConnection(provider, button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        button.disabled = true;

        fetch('{{ route("admin.whatsapp-providers.test-connection") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                provider: provider,
                queue: false 
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(`${getProviderDisplayName(provider)}: ${data.message}`, 'success');
            } else {
                showToast(`${getProviderDisplayName(provider)}: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error testing connection:', error);
            showToast(`Connection test failed: Network error`, 'error');
        })
        .finally(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    // Reset configuration function
    function resetConfiguration(provider, button) {
        const providerName = getProviderDisplayName(provider);
        if (!confirm(`Are you sure you want to reset ${providerName} configuration? This will clear all settings.`)) {
            return;
        }

        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...';
        button.disabled = true;

        fetch('{{ route("admin.whatsapp-providers.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ provider: provider })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(`${data.message}`, 'success');
                // Reload status and refresh the page after a delay
                setTimeout(() => {
                    loadProviderStatus();
                    loadSystemStatus();
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
            showToast('Reset failed: Network error', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    // Submit provider form
    function submitProviderForm(provider, form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
        submitButton.disabled = true;

        // Create FormData object
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.redirect) {
                showToast(`${getProviderDisplayName(provider)} configuration updated successfully!`, 'success');
                // Refresh status and environment
                setTimeout(() => {
                    loadProviderStatus();
                    loadSystemStatus();
                    verifyCurrentEnvironment();
                }, 1000);
                
                // If there's a redirect URL, use it
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                }
            } else {
                // Handle validation errors
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) {
                            input.classList.add('error');
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'text-xs mt-1';
                            errorDiv.style.color = 'var(--danger)';
                            errorDiv.textContent = data.errors[field][0];
                            input.parentNode.appendChild(errorDiv);
                        }
                    });
                    showToast('Please fix the errors in the form', 'error');
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
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        });
    }

    // Show test message modal
    function showTestMessageModal() {
        const modal = document.getElementById('testMessageModal');
        modal.classList.remove('hidden');
        
        // Clear previous values
        document.getElementById('testPhoneNumber').value = '';
        document.getElementById('testMessage').value = '';
        document.getElementById('charCount').textContent = '0';
        document.getElementById('queueMessage').checked = false;
    }

    // Send test message
    function sendTestMessage() {
        const phoneNumber = document.getElementById('testPhoneNumber').value.trim();
        const message = document.getElementById('testMessage').value.trim();
        const queue = document.getElementById('queueMessage').checked;
        const sendBtn = document.getElementById('sendTestMessageBtn');
        
        // Validate inputs
        if (!phoneNumber) {
            showToast('Please enter a phone number', 'error');
            return;
        }
        
        if (!message) {
            showToast('Please enter a message', 'error');
            return;
        }
        
        // Validate phone number format
        if (!phoneNumber.match(/^whatsapp:\+\d+$/)) {
            showToast('Phone number must be in format: whatsapp:+233XXXXXXXXX', 'error');
            return;
        }
        
        // Validate message length
        if (message.length > 1000) {
            showToast('Message must be 1000 characters or less', 'error');
            return;
        }

        const originalText = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
        sendBtn.disabled = true;

        fetch('{{ route("admin.whatsapp-providers.send-test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                provider: currentActiveProvider,
                phone_number: phoneNumber,
                message: message,
                queue: queue
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(`Test message ${data.queued ? 'queued' : 'sent'} successfully!`, 'success');
                // Close modal
                document.getElementById('testMessageModal').classList.add('hidden');
            } else {
                showToast(`Failed to send message: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error sending test message:', error);
            showToast('Failed to send message: Network error', 'error');
        })
        .finally(() => {
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
        });
    }

    // Helper function to get provider display name
    function getProviderDisplayName(providerKey) {
        const providers = {
            'vumaapi': 'VumaAPI WhatsApp (Ghana)',
            'vonage': 'Vonage WhatsApp',
            '360dialog': '360Dialog WhatsApp',
            'wati': 'WATI WhatsApp',
            'custom': 'Custom WhatsApp API'
        };
        return providers[providerKey] || providerKey;
    }

    // Toast notification function
    function showToast(message, type = 'info') {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });

        const toast = document.createElement('div');
        toast.className = `toast-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
        
        // Type-specific styling
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
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);
        
        // Auto remove after 5 seconds
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

/* Toggle switch */
.toggle-switch {
    display: inline-block;
    width: 44px;
    height: 24px;
    background-color: var(--bg-secondary);
    border-radius: 12px;
    position: relative;
    cursor: pointer;
    transition: background-color 0.3s;
    border: 1px solid var(--border-color);
}

.toggle-slider {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background-color: var(--danger);
    border-radius: 50%;
    transition: transform 0.3s, background-color 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.enable-provider-btn.active .toggle-switch {
    background-color: rgba(var(--primary-rgb), 0.2);
}

.enable-provider-btn.active .toggle-slider {
    background-color: var(--primary);
    transform: translateX(20px);
}

.enable-provider-btn.active[data-enabled="true"] .toggle-slider {
    background-color: var(--success);
}

.enable-provider-btn:disabled .toggle-switch {
    opacity: 0.5;
    cursor: not-allowed;
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

/* Logo styling */
img[alt*="Logo"] {
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
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

/* Logo fallback styling */
img[alt*="Logo"] {
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    padding: 2px;
}

/* Fade in animation */
.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Form error styling */
input.error,
textarea.error,
select.error {
    border-color: var(--danger) !important;
}

input.error:focus,
textarea.error:focus,
select.error:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1) !important;
}

/* Modal backdrop */
#testMessageModal {
    animation: fadeIn 0.3s ease-out;
}

#testMessageModal > div {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
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

    #providerStatusCards {
        grid-template-columns: 1fr;
    }
    
    /* Make logos smaller on mobile */
    #providerTabs button img {
        height: 1rem !important;
    }
    
    .tab-pane .flex img {
        height: 2.5rem !important;
    }
    
    .card img {
        height: 1.5rem !important;
    }
    
    /* Adjust toggle switch on mobile */
    .toggle-switch {
        width: 40px;
        height: 22px;
    }
    
    .toggle-slider {
        width: 18px;
        height: 18px;
    }
    
    .enable-provider-btn.active .toggle-slider {
        transform: translateX(18px);
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
    
    .grid.grid-cols-1.md\\:grid-cols-4.gap-4 {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* WhatsApp specific styling */
.whatsapp-green {
    color: #25D366;
}

.whatsapp-bg {
    background-color: rgba(37, 211, 102, 0.1);
}

.whatsapp-border {
    border-color: rgba(37, 211, 102, 0.3);
}

/* Smooth transitions */
* {
    transition: color 0.3s, background-color 0.3s, border-color 0.3s, transform 0.3s;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Print styles */
@media print {
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    #refreshStatus,
    #verifyEnvironment,
    #sendTestMessage,
    .test-connection,
    .reset-config,
    .enable-provider-btn {
        display: none !important;
    }
}
</style>
@endsection