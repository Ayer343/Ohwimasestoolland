{{-- resources/views/admin/whatsapp/messages/compose.blade.php --}}

@php
    $user = auth()->user();
    $isDeveloper = $user->isDeveloper();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isLandlord = $user->isLandlord();
    $isTenant = $user->isTenant();
    
    // Determine layout based on user role
    if ($isSuperAdmin) {
        $layout = 'layouts.app';
        $pageTitle = 'Compose WhatsApp - Super Admin';
        $canSelectUsers = true;
        $canBulkSend = true;
        $routePrefix = 'admin';
    } elseif ($isAdmin) {
        $layout = 'layouts.app';
        $pageTitle = 'Compose WhatsApp - Admin';
        $canSelectUsers = true;
        $canBulkSend = true;
        $routePrefix = 'admin';
    } elseif ($isDeveloper) {
        $layout = 'layouts.dev';
        $pageTitle = 'Compose WhatsApp - Developer';
        $canSelectUsers = true;
        $canBulkSend = true;
        $routePrefix = 'developer';
    } elseif ($isLandlord) {
        $layout = 'layouts.landlord';
        $pageTitle = 'Compose WhatsApp';
        $canSelectUsers = true;
        $canBulkSend = false;
        $routePrefix = 'landlord';
    } elseif ($isTenant) {
        $layout = 'layouts.tenant';
        $pageTitle = 'Compose WhatsApp';
        $canSelectUsers = false;
        $canBulkSend = false;
        $routePrefix = 'tenant';
    } else {
        $layout = 'layouts.app';
        $pageTitle = 'Compose WhatsApp';
        $canSelectUsers = false;
        $canBulkSend = false;
        $routePrefix = 'admin';
    }
    
    // WhatsApp character limits
    $maxMessageLength = 4096; // WhatsApp API limit
    
    // Get user types for filtering
    $userTypes = [
        'all' => 'All Users',
        'landlord' => 'Landlords',
        'tenant' => 'Tenants',
        'field_agent' => 'Field Agents',
        'security_personnel' => 'Security Personnel',
        'admin' => 'Admins',
        'super_admin' => 'Super Admins',
    ];
    
    // Get the user list URL
    try {
        $userListUrl = route($routePrefix . '.whatsapp.users.list');
    } catch (\Exception $e) {
        $userListUrl = '/admin/whatsapp/users/list';
    }
    
    // Get providers
    $providers = $providers ?? [];
    $defaultProvider = $defaultProvider ?? null;
    
    // Get message types
    $messageTypes = $messageTypes ?? [
        'text' => 'Text Message',
        'template' => 'Template Message',
        'media' => 'Media Message',
        'interactive' => 'Interactive Message'
    ];
    
    // Get templates
    $templates = $templates ?? [];
    
    // Get properties for landlords
    $properties = $isLandlord ? ($user->properties ?? collect()) : collect();
    
    // Get recent logs
    $recentLogs = $recentLogs ?? collect();
    
    // Get WhatsApp status
    $whatsappStatus = $status ?? ['system_ready' => false, 'can_send_whatsapp' => false];
    
    // Media types
    $mediaTypes = [
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
        'document' => 'Document',
        'sticker' => 'Sticker'
    ];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border-color: #25D366;">
                        <i class="fab fa-whatsapp text-2xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fab fa-whatsapp mr-2" style="color: #25D366;"></i> 
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-info-circle mr-1"></i> Compose and send WhatsApp messages</span>
                        @if($canBulkSend)
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                <i class="fas fa-users mr-0.5"></i> Bulk Send Available
                            </span>
                        @endif
                        @if($canSelectUsers)
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <i class="fas fa-user-check mr-0.5"></i> User Selection Available
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm mt-2 sm:mt-0" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route($routePrefix . '.whatsapp.messages.index') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Messages
                </a>
            </div>
        </div>
    </div>

    <!-- WhatsApp Provider Status -->
    <div class="card">
        <div class="p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i> WhatsApp Status:
                    </span>
                    <span class="text-sm" style="color: var(--text-secondary);">
                        {{ $whatsappStatus['provider'] ?? ($defaultProvider ?? 'Not Configured') }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs">
                        @if($whatsappStatus['system_ready'] ?? false)
                            <span class="text-green-500">●</span>
                            <span class="ml-1 text-green-700 dark:text-green-300">Online</span>
                        @else
                            <span class="text-red-500">●</span>
                            <span class="ml-1 text-red-700 dark:text-red-300">Offline</span>
                        @endif
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-0.5"></i> 
                        Last checked: {{ $whatsappStatus['last_checked'] ?? 'Never' }}
                    </span>
                    <button onclick="refreshWhatsAppStatus()" 
                            class="px-2 py-1 text-xs rounded transition hover:scale-105"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-sync mr-0.5"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error/Warning Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900 dark:border-green-700 dark:text-green-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ session('success') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative dark:bg-red-900 dark:border-red-700 dark:text-red-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ session('error') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('warning'))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">Warning!</span>
            <span class="ml-2">{{ session('warning') }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Compose Form -->
    <div class="card">
        <div class="p-6">
            <form id="composeForm" method="POST" action="{{ route($routePrefix . '.whatsapp.messages.send') }}" enctype="multipart/form-data">
                @csrf

                <!-- ========================================== -->
                <!-- 👥 USER SELECTION (Admin/Super Admin/Landlord only) -->
                <!-- ========================================== -->
                @if($canSelectUsers)
                <div class="mb-4 p-4 rounded-lg user-selection-section" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-users" style="color: var(--info);"></i>
                        <span class="text-sm font-semibold" style="color: var(--text-primary);">Select Recipients</span>
                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <span id="selectedCount">0</span> selected
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <!-- User Type Filter -->
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-filter mr-0.5"></i> User Type
                            </label>
                            <select id="userTypeFilter" 
                                    class="w-full rounded-lg px-3 py-2 text-sm transition-all focus:ring-2 focus:outline-none"
                                    style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                    onchange="filterUsers()">
                                @foreach($userTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Search -->
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-search mr-0.5"></i> Search Users
                            </label>
                            <input type="text" 
                                   id="userSearch" 
                                   placeholder="Search by name or phone..."
                                   class="w-full rounded-lg px-3 py-2 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                   oninput="filterUsers()">
                        </div>

                        <!-- Property Filter (for landlords) -->
                        @if($isLandlord && $properties->count() > 0)
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-building mr-0.5"></i> Property
                            </label>
                            <select id="propertyFilter" 
                                    class="w-full rounded-lg px-3 py-2 text-sm transition-all focus:ring-2 focus:outline-none"
                                    style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                    onchange="filterUsers()">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}">{{ $property->name ?? $property->property_name ?? 'Property #' . $property->id }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                    
                    <!-- User List -->
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <span id="visibleCount">0</span> users found
                            </span>
                            <div class="flex gap-2">
                                <button type="button" onclick="selectAllUsers()" 
                                        class="text-xs px-2 py-1 rounded transition"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-check-double mr-0.5"></i> Select All
                                </button>
                                <button type="button" onclick="deselectAllUsers()" 
                                        class="text-xs px-2 py-1 rounded transition"
                                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                    <i class="fas fa-times mr-0.5"></i> Deselect All
                                </button>
                                <button type="button" onclick="selectUsersWithPhone()" 
                                        class="text-xs px-2 py-1 rounded transition"
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-phone mr-0.5"></i> With Phone
                                </button>
                            </div>
                        </div>
                        
                        <div id="userListContainer" class="max-h-60 overflow-y-auto rounded-lg border" style="border-color: var(--border-color);">
                            <div id="userList" class="divide-y" style="border-color: var(--border-color);">
                                <!-- Users loaded via JavaScript -->
                                <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-spinner fa-spin mr-2"></i> Loading users...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span class="text-xs" style="color: var(--text-secondary);">Quick add:</span>
                        @foreach($userTypes as $key => $label)
                            @if($key !== 'all')
                            <button type="button" onclick="addUserType('{{ $key }}')" 
                                    class="text-xs px-2 py-1 rounded transition hover:scale-105"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                + {{ $label }}
                            </button>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Selected Users Tags -->
                <div id="selectedUsersTags" class="flex flex-wrap gap-1 mb-4">
                    <!-- Tags added via JavaScript -->
                </div>

                <!-- ========================================== -->
                <!-- 💬 WHATSAPP COMPOSE FIELDS                 -->
                <!-- ========================================== -->

                <!-- Hidden fields for bulk send -->
                <input type="hidden" name="recipient_ids" id="recipientIds" value="">
                <input type="hidden" name="is_bulk" id="isBulk" value="0">

                <!-- Message Type Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-comment-dots mr-1" style="color: #25D366;"></i> Message Type
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach($messageTypes as $key => $label)
                            <button type="button" 
                                    onclick="selectMessageType('{{ $key }}')"
                                    class="message-type-btn px-4 py-3 rounded-lg text-sm font-medium transition-all hover:scale-105 {{ $key === 'text' ? 'active' : '' }}"
                                    data-type="{{ $key }}"
                                    style="background-color: {{ $key === 'text' ? 'rgba(var(--primary-rgb), 0.1)' : 'var(--bg-secondary)' }}; 
                                           border: 2px solid {{ $key === 'text' ? 'var(--primary)' : 'var(--border-color)' }};
                                           color: {{ $key === 'text' ? 'var(--primary)' : 'var(--text-secondary)' }};">
                                <i class="fas {{ $key === 'text' ? 'fa-align-left' : ($key === 'template' ? 'fa-file-alt' : ($key === 'media' ? 'fa-image' : 'fa-hand-pointer')) }} mr-2"></i>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Provider Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-1" style="color: var(--primary);"></i> Provider
                    </label>
                    <select name="provider" id="providerSelect"
                            class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                            style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                        @if(count($providers) > 0)
                            @foreach($providers as $key => $provider)
                                <option value="{{ $key }}" {{ $defaultProvider === $key ? 'selected' : '' }}>
                                    {{ is_array($provider) ? ($provider['name'] ?? ucfirst($key)) : ucfirst($key) }}
                                </option>
                            @endforeach
                        @else
                            <option value="default">Default Provider</option>
                        @endif
                    </select>
                </div>

                <!-- From Number -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-phone-alt mr-1" style="color: var(--primary);"></i> From Number
                    </label>
                    <input type="text" 
                           name="from" 
                           id="fromInput"
                           value="{{ old('from', config('whatsapp.twilio_whatsapp_from', config('whatsapp.vonage_whatsapp_from', ''))) }}"
                           placeholder="WhatsApp Business Number (e.g., +1234567890)"
                           class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                           style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-0.5"></i> 
                        Your WhatsApp Business number with country code
                    </p>
                    @error('from')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone Number Input (Manual) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-phone mr-1" style="color: var(--primary);"></i> Recipient Phone Number 
                        <span class="text-xs" style="color: var(--text-secondary);">(or select users above)</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                               name="to" 
                               id="phoneNumberInput"
                               value="{{ old('to') }}"
                               placeholder="e.g., +233240000000"
                               class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                               style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                               autocomplete="off">
                        <div id="phoneSuggestions" class="absolute z-10 w-full mt-1 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        </div>
                    </div>
                    @error('to')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Include country code (e.g., +233 for Ghana)
                    </p>
                </div>

                <!-- ========================================== -->
                <!-- 📝 TEMPLATE MESSAGE FIELDS                  -->
                <!-- ========================================== -->
                <div id="templateFields" class="hidden mb-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-1" style="color: var(--info);"></i> Select Template
                    </label>
                    <select name="template" id="templateSelect"
                            class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                            style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                        <option value="">Select a template...</option>
                        @foreach($templates as $key => $template)
                            <option value="{{ $key }}">{{ is_array($template) ? ($template['name'] ?? ucfirst($key)) : ucfirst($key) }}</option>
                        @endforeach
                    </select>
                    
                    <div class="mt-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-code mr-1" style="color: var(--info);"></i> Template Parameters
                        </label>
                        <div id="templateParameters" class="space-y-2">
                            <!-- Dynamically added via JavaScript -->
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-0.5"></i> 
                                Select a template to see required parameters
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 📎 MEDIA MESSAGE FIELDS                    -->
                <!-- ========================================== -->
                <div id="mediaFields" class="hidden mb-4 p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-file-image mr-1" style="color: var(--warning);"></i> Media Type
                            </label>
                            <select name="media_type" id="mediaTypeSelect"
                                    class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                    style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                                @foreach($mediaTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-link mr-1" style="color: var(--warning);"></i> Media URL
                            </label>
                            <input type="url" 
                                   name="media_url" 
                                   id="mediaUrlInput"
                                   value="{{ old('media_url') }}"
                                   placeholder="https://example.com/image.jpg"
                                   class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-0.5"></i> 
                                Publicly accessible URL of the media file
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-caption mr-1" style="color: var(--warning);"></i> Caption
                        </label>
                        <input type="text" 
                               name="caption" 
                               id="captionInput"
                               value="{{ old('caption') }}"
                               placeholder="Caption for the media (optional)"
                               class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                               style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                               maxlength="1000">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-0.5"></i> 
                            Max 1000 characters
                        </p>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 📝 MESSAGE CONTENT                         -->
                <!-- ========================================== -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-align-left mr-1" style="color: var(--primary);"></i> Message <span class="text-red-500">*</span>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            (Use {name} for personalization)
                        </span>
                    </label>
                    <textarea name="message" 
                              id="messageInput"
                              rows="6"
                              placeholder="Type your message here... Use {name} to insert recipient's name"
                              class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none resize-y"
                              style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                              maxlength="{{ $maxMessageLength }}"
                              required>{{ old('message') }}</textarea>
                    @error('message')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Character Counter -->
                    <div class="flex flex-wrap justify-between text-xs mt-1" style="color: var(--text-secondary);">
                        <div class="flex items-center gap-3">
                            <span><i class="fas fa-font mr-0.5"></i> <span id="charCounter">0</span> characters</span>
                            <span><i class="fas fa-layer-group mr-0.5"></i> <span id="smsCounter">1</span> message part(s)</span>
                        </div>
                        <div>
                            <span id="charRemaining">{{ $maxMessageLength }}</span> remaining
                            <span id="charWarning" class="hidden ml-2" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-0.5"></i> Long message
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- ⚙️ ADVANCED OPTIONS                        -->
                <!-- ========================================== -->
                <div class="mb-4">
                    <button type="button" onclick="toggleAdvancedOptions()" 
                            class="text-sm font-medium transition hover:opacity-80"
                            style="color: var(--text-secondary);">
                        <i class="fas fa-chevron-right mr-1" id="advancedToggleIcon"></i>
                        Advanced Options
                    </button>
                    
                    <div id="advancedOptions" class="hidden mt-3 p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Priority -->
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                    <i class="fas fa-flag mr-1" style="color: var(--secondary);"></i> Priority
                                </label>
                                <select name="priority" id="prioritySelect"
                                        class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                        style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            
                            <!-- Schedule -->
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                    <i class="fas fa-clock mr-1" style="color: var(--secondary);"></i> Schedule
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="schedule" id="scheduleCheck" 
                                           class="rounded transition-all"
                                           style="accent-color: var(--primary);"
                                           onchange="toggleSchedule()">
                                    <label for="scheduleCheck" class="text-sm" style="color: var(--text-secondary);">
                                        Schedule for later
                                    </label>
                                </div>
                                <div id="scheduleFields" class="hidden mt-2">
                                    <input type="datetime-local" 
                                           name="scheduled_at" 
                                           id="scheduledAt"
                                           min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                                           class="w-full rounded-lg px-3 py-2 text-sm transition-all focus:ring-2 focus:outline-none"
                                           style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 📋 RECIPIENT SUMMARY                       -->
                <!-- ========================================== -->
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid var(--border-color);">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <div>
                            <span style="color: var(--text-secondary);">Recipients:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                <span id="recipientCount">0</span>
                            </span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary);">With Phone:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                <span id="recipientPhoneCount">0</span>
                            </span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary);">Message Parts:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                <span id="estimatedParts">0</span> total
                            </span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary);">Estimated Cost:</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                <span id="estimatedCost">0.00 GHS</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 📝 QUICK TEMPLATES                         -->
                <!-- ========================================== -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-1" style="color: var(--primary);"></i> Quick Templates
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="insertTemplate('welcome')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <i class="fas fa-hand-wave mr-0.5"></i> Welcome
                        </button>
                        <button type="button" onclick="insertTemplate('reminder')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <i class="fas fa-bell mr-0.5"></i> Reminder
                        </button>
                        <button type="button" onclick="insertTemplate('payment')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <i class="fas fa-credit-card mr-0.5"></i> Payment
                        </button>
                        <button type="button" onclick="insertTemplate('invitation')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <i class="fas fa-envelope-open-text mr-0.5"></i> Invitation
                        </button>
                        <button type="button" onclick="insertTemplate('maintenance')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <i class="fas fa-tools mr-0.5"></i> Maintenance
                        </button>
                        <button type="button" onclick="insertTemplate('notice')" 
                                class="text-xs px-3 py-1 rounded transition hover:scale-105"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                            <i class="fas fa-bullhorn mr-0.5"></i> Notice
                        </button>
                    </div>
                </div>

                <!-- Test Mode -->
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer" style="color: var(--text-secondary);">
                        <input type="checkbox" name="is_test" id="testMode" value="1" checked
                               class="rounded transition-all"
                               style="accent-color: var(--primary);">
                        <span class="text-sm">Send as test message</span>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-0.5"></i> 
                            Test messages are logged but marked as tests
                        </span>
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t" style="border-color: var(--border-color);">
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" 
                                id="sendButton"
                                class="inline-flex items-center px-6 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                                style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
                            <i class="fab fa-whatsapp mr-2"></i> 
                            <span id="sendButtonText">Send WhatsApp</span>
                        </button>
                        
                        <button type="reset" 
                                onclick="resetForm()"
                                class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-undo mr-2"></i> Reset
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                        <span>
                            <i class="fas fa-circle text-[6px] mr-1" style="color: {{ $whatsappStatus['system_ready'] ? 'var(--success)' : 'var(--danger)' }};"></i>
                            {{ $whatsappStatus['system_ready'] ? 'System Ready' : 'System Offline' }}
                        </span>
                        <span class="hidden sm:inline">•</span>
                        <span id="currentTime">{{ now()->format('g:i A') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 📋 JAVASCRIPT - FULL IMPLEMENTATION        -->
<!-- ========================================== -->

<script>
// ========================================== //
// 👥 USER MANAGEMENT                         //
// ========================================== //

let selectedUsers = [];
let allUsers = [];
let filteredUsers = [];
let isLoadingUsers = false;
let autoRefreshTimer = null;

// Configuration
const USER_LIST_URL = '{{ $userListUrl }}';
const MAX_MESSAGE_LENGTH = {{ $maxMessageLength }};

/**
 * Get user type string from type code
 */
function getUserTypeString(typeCode) {
    const typeMap = {
        0: 'super_admin',
        1: 'admin',
        2: 'landlord',
        3: 'tenant',
        4: 'field_agent',
        5: 'developer',
        6: 'security_personnel',
        '0': 'super_admin',
        '1': 'admin',
        '2': 'landlord',
        '3': 'tenant',
        '4': 'field_agent',
        '5': 'developer',
        '6': 'security_personnel',
    };
    return typeMap[typeCode] || 'unknown';
}

/**
 * Format user type for display
 */
function formatUserType(type) {
    const types = {
        'super_admin': 'Super Admin',
        'admin': 'Admin',
        'landlord': 'Landlord',
        'tenant': 'Tenant',
        'field_agent': 'Field Agent',
        'security_personnel': 'Security Personnel',
        'developer': 'Developer',
        'unknown': 'Unknown'
    };
    return types[type] || type || 'Unknown';
}

/**
 * Load users from the server
 */
function loadUsers() {
    if (isLoadingUsers) return;
    isLoadingUsers = true;
    
    const userList = document.getElementById('userList');
    userList.innerHTML = `
        <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
            <i class="fas fa-spinner fa-spin mr-2"></i> Loading users...
        </div>
    `;
    
    // Build URL with filters
    let url = USER_LIST_URL + '?limit=200';
    const typeFilter = document.getElementById('userTypeFilter')?.value || 'all';
    const propertyFilter = document.getElementById('propertyFilter')?.value || '';
    const searchTerm = document.getElementById('userSearch')?.value || '';
    
    if (typeFilter !== 'all') {
        url += '&type=' + encodeURIComponent(typeFilter);
    }
    if (propertyFilter) {
        url += '&property_id=' + encodeURIComponent(propertyFilter);
    }
    if (searchTerm) {
        url += '&search=' + encodeURIComponent(searchTerm);
    }
    
    console.log('📡 Fetching users from:', url);
    
    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        // Remove BOM if present
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.substring(1);
            console.log('✅ Removed BOM from response');
        }
        
        const data = JSON.parse(text);
        console.log('📡 Parsed data:', data);
        
        isLoadingUsers = false;
        
        if (data.success) {
            allUsers = data.users || [];
            
            // Filter out developer users (type 5)
            allUsers = allUsers.filter(user => {
                const userType = parseInt(user.type);
                return userType !== 5;
            });
            
            // Also filter out users without phone numbers
            allUsers = allUsers.filter(user => {
                return user.phone && user.phone.trim() !== '';
            });
            
            renderUserList();
            console.log(`✅ Loaded ${allUsers.length} users with phone numbers`);
            
            if (allUsers.length === 0) {
                userList.innerHTML = `
                    <div class="text-center py-8 text-sm" style="color: var(--warning);">
                        <i class="fas fa-info-circle mr-2"></i> 
                        No users with phone numbers found.
                    </div>
                `;
            }
        } else {
            userList.innerHTML = `
                <div class="text-center py-8 text-sm" style="color: var(--danger);">
                    <i class="fas fa-exclamation-circle mr-2"></i> 
                    ${data.message || 'Failed to load users'}
                </div>
            `;
            console.error('Failed to load users:', data.message);
        }
    })
    .catch(error => {
        isLoadingUsers = false;
        console.error('❌ Failed to load users:', error);
        userList.innerHTML = `
            <div class="text-center py-8 text-sm" style="color: var(--danger);">
                <i class="fas fa-exclamation-circle mr-2"></i> 
                Error loading users.
                <br>
                <span class="text-xs">${error.message}</span>
                <br>
                <button onclick="loadUsers()" class="mt-2 px-3 py-1 text-xs rounded transition" 
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-sync mr-1"></i> Retry
                </button>
            </div>
        `;
        showNotification('Failed to load user list.', 'error');
    });
}

/**
 * Render the user list with current filters
 */
function renderUserList() {
    const userList = document.getElementById('userList');
    const typeFilter = document.getElementById('userTypeFilter')?.value || 'all';
    const propertyFilter = document.getElementById('propertyFilter')?.value || '';
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase().trim() || '';
    
    filteredUsers = allUsers.filter(user => {
        const userTypeString = getUserTypeString(user.type);
        
        // Type filter
        if (typeFilter !== 'all' && userTypeString !== typeFilter) {
            return false;
        }
        
        // Search filter
        if (searchTerm) {
            const name = (user.name || '').toLowerCase();
            const phone = (user.phone || '').toLowerCase();
            if (!name.includes(searchTerm) && !phone.includes(searchTerm)) {
                return false;
            }
        }
        
        return true;
    });
    
    const visibleCount = document.getElementById('visibleCount');
    if (visibleCount) {
        visibleCount.textContent = filteredUsers.length;
    }
    
    if (filteredUsers.length === 0) {
        userList.innerHTML = `
            <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-user-slash mr-2"></i> No users found matching your criteria
            </div>
        `;
        return;
    }
    
    let html = '';
    filteredUsers.forEach(user => {
        const isSelected = selectedUsers.some(u => u.id === user.id);
        const hasPhone = user.phone && user.phone.trim() !== '';
        const userTypeString = getUserTypeString(user.type);
        const userTypeLabel = formatUserType(userTypeString);
        const initials = getInitials(user.name || 'U');
        
        html += `
            <div class="flex items-center justify-between p-3 transition select-user-item"
                 data-user-id="${user.id}"
                 onclick="toggleUser(${user.id})"
                 style="${isSelected ? 'background-color: rgba(var(--primary-rgb), 0.05);' : ''}">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input type="checkbox" 
                           ${isSelected ? 'checked' : ''} 
                           ${!hasPhone ? 'disabled' : ''}
                           class="rounded transition-all user-checkbox"
                           style="accent-color: var(--primary);"
                           onclick="event.stopPropagation(); toggleUser(${user.id})">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0"
                         style="background: linear-gradient(135deg, ${getColorForUser(user)}, ${getColorForUser(user, true)});">
                        ${initials}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium truncate" style="color: var(--text-primary);">
                            ${escapeHtml(user.name || 'Unknown')}
                            ${user.status === 'active' ? '<span class="text-xs ml-1 px-1.5 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">● Active</span>' : ''}
                            ${user.status === 'pending' ? '<span class="text-xs ml-1 px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">● Pending</span>' : ''}
                        </div>
                        <div class="text-xs truncate" style="color: var(--text-secondary);">
                            ${hasPhone ? escapeHtml(user.phone_formatted || user.phone) : '<span style="color: var(--danger);"><i class="fas fa-exclamation-circle mr-0.5"></i> No phone</span>'}
                            <span class="ml-2">• ${userTypeLabel}</span>
                            ${user.phone_verified ? '<span class="ml-2 text-xs text-green-600 dark:text-green-400"><i class="fas fa-check-circle"></i> Verified</span>' : ''}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    ${isSelected ? `<span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">Selected</span>` : ''}
                    ${!hasPhone ? `<span class="text-xs" style="color: var(--danger);"><i class="fas fa-phone-slash"></i></span>` : ''}
                </div>
            </div>
        `;
    });
    
    userList.innerHTML = html;
    updateSelectedCount();
    updateRecipientSummary();
}

/**
 * Toggle user selection
 */
function toggleUser(userId) {
    const user = allUsers.find(u => u.id === userId);
    if (!user) {
        showNotification('User not found', 'error');
        return;
    }
    
    // Check if user has phone number
    if (!user.phone || user.phone.trim() === '') {
        showNotification('This user does not have a phone number', 'warning');
        return;
    }
    
    const index = selectedUsers.findIndex(u => u.id === userId);
    if (index > -1) {
        selectedUsers.splice(index, 1);
    } else {
        selectedUsers.push(user);
    }
    
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
}

/**
 * Select all visible users
 */
function selectAllUsers() {
    const usersWithPhone = filteredUsers.filter(u => u.phone && u.phone.trim() !== '');
    if (usersWithPhone.length === 0) {
        showNotification('No users with phone numbers in the current filter', 'warning');
        return;
    }
    
    usersWithPhone.forEach(user => {
        if (!selectedUsers.some(u => u.id === user.id)) {
            selectedUsers.push(user);
        }
    });
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification(`Selected ${usersWithPhone.length} users`, 'success');
}

/**
 * Deselect all users
 */
function deselectAllUsers() {
    if (selectedUsers.length === 0) {
        showNotification('No users selected', 'info');
        return;
    }
    selectedUsers = [];
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification('All users deselected', 'info');
}

/**
 * Select only users with phone numbers
 */
function selectUsersWithPhone() {
    const usersWithPhone = filteredUsers.filter(u => u.phone && u.phone.trim() !== '');
    if (usersWithPhone.length === 0) {
        showNotification('No users with phone numbers found', 'warning');
        return;
    }
    selectedUsers = usersWithPhone;
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification(`Selected ${usersWithPhone.length} users with phone numbers`, 'success');
}

/**
 * Add all users of a specific type
 */
function addUserType(type) {
    const typeUsers = allUsers.filter(u => {
        const userTypeString = getUserTypeString(u.type);
        return userTypeString === type && u.phone && u.phone.trim() !== '';
    });
    
    if (typeUsers.length === 0) {
        showNotification(`No ${formatUserType(type)}s with phone numbers found`, 'warning');
        return;
    }
    
    typeUsers.forEach(user => {
        if (!selectedUsers.some(u => u.id === user.id)) {
            selectedUsers.push(user);
        }
    });
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification(`Added ${typeUsers.length} ${formatUserType(type)}${typeUsers.length > 1 ? 's' : ''}`, 'success');
}

/**
 * Filter users by type and search
 */
function filterUsers() {
    renderUserList();
    // Reload users if search term is long enough
    const searchTerm = document.getElementById('userSearch')?.value || '';
    if (searchTerm.length > 2) {
        loadUsers();
    }
}

/**
 * Update selected users tags display
 */
function updateSelectedUsersTags() {
    const container = document.getElementById('selectedUsersTags');
    if (!container) return;
    
    if (selectedUsers.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    const displayUsers = selectedUsers.slice(0, 10);
    displayUsers.forEach(user => {
        html += `
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs" 
                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                <i class="fas fa-phone text-[10px]" style="color: var(--primary);"></i>
                ${escapeHtml(user.name || user.phone)}
                <button type="button" onclick="removeUser(${user.id})" 
                        class="hover:text-red-500 transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times"></i>
                </button>
            </span>
        `;
    });
    
    if (selectedUsers.length > 10) {
        html += `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" 
                  style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                +${selectedUsers.length - 10} more
            </span>
        `;
    }
    
    container.innerHTML = html;
}

/**
 * Remove a user from selection
 */
function removeUser(userId) {
    selectedUsers = selectedUsers.filter(u => u.id !== userId);
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification('User removed from selection', 'info');
}

/**
 * Update selected count display
 */
function updateSelectedCount() {
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = selectedUsers.length;
    }
}

/**
 * Update recipient summary
 */
function updateRecipientSummary() {
    const countElement = document.getElementById('recipientCount');
    const phoneCountElement = document.getElementById('recipientPhoneCount');
    const partsElement = document.getElementById('estimatedParts');
    const costElement = document.getElementById('estimatedCost');
    
    // Count users with phone numbers
    const withPhone = selectedUsers.filter(u => u.phone && u.phone.trim() !== '');
    
    if (countElement) {
        countElement.textContent = selectedUsers.length;
    }
    
    if (phoneCountElement) {
        phoneCountElement.textContent = withPhone.length;
    }
    
    // Calculate estimated message parts
    const message = document.getElementById('messageInput')?.value || '';
    const messageLength = message.length;
    // WhatsApp allows up to 4096 characters
    const parts = Math.max(1, Math.ceil(messageLength / 4096));
    const totalParts = parts * withPhone.length;
    
    if (partsElement) {
        partsElement.textContent = totalParts;
    }
    
    if (costElement) {
        const cost = totalParts * 0.08; // Assuming 0.08 GHS per part
        costElement.textContent = cost.toFixed(2) + ' GHS';
    }
}

/**
 * Update send button text based on selection
 */
function updateSendButtonText() {
    const button = document.getElementById('sendButtonText');
    if (!button) return;
    
    if (selectedUsers.length > 0) {
        button.textContent = `Send to ${selectedUsers.length} recipient${selectedUsers.length > 1 ? 's' : ''}`;
    } else {
        button.textContent = 'Send WhatsApp';
    }
}

/**
 * Update hidden fields for form submission
 */
function updateHiddenFields() {
    const ids = selectedUsers.map(u => u.id);
    document.getElementById('recipientIds').value = ids.join(',');
    document.getElementById('isBulk').value = selectedUsers.length > 0 ? '1' : '0';
}

/**
 * Get initials from name
 */
function getInitials(name) {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

/**
 * Get color for user avatar
 */
function getColorForUser(user, lighter = false) {
    const colors = ['#4F46E5', '#7C3AED', '#EC4899', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6'];
    const index = (user.id || 0) % colors.length;
    return colors[index];
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// 📝 FORM HANDLING                           //
// ========================================== //

/**
 * Character counter
 */
function updateSmsStats() {
    const messageInput = document.getElementById('messageInput');
    const charCounter = document.getElementById('charCounter');
    const smsCounter = document.getElementById('smsCounter');
    const charWarning = document.getElementById('charWarning');
    const charRemaining = document.getElementById('charRemaining');
    
    if (!messageInput) return;
    
    const text = messageInput.value;
    const length = text.length;
    const parts = Math.max(1, Math.ceil(length / 4096));
    const remaining = Math.max(0, 4096 - length);
    
    if (charCounter) {
        charCounter.textContent = length;
    }
    
    if (smsCounter) {
        smsCounter.textContent = parts;
    }
    
    if (charRemaining) {
        charRemaining.textContent = remaining;
    }
    
    if (charWarning) {
        if (length > 4096) {
            charWarning.classList.remove('hidden');
        } else {
            charWarning.classList.add('hidden');
        }
    }
    
    // Update recipient summary
    updateRecipientSummary();
}

/**
 * Insert template into message
 */
function insertTemplate(type) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;

    const templates = {
        'welcome': 'Welcome {name}! Thank you for choosing our services. We look forward to serving you.',
        'reminder': 'Dear {name}, this is a friendly reminder about your upcoming appointment. Please confirm your attendance.',
        'payment': 'Dear {name}, this is a payment reminder. Your payment is due. Please ensure timely payment.',
        'invitation': 'Dear {name}, you are invited to join our platform. Please click the link to complete your registration.',
        'maintenance': 'Dear {name}, this is to inform you about scheduled maintenance. We apologize for any inconvenience.',
        'notice': 'NOTICE: Dear {name}, please be informed about the upcoming changes. Thank you for your understanding.'
    };

    const template = templates[type] || '';
    if (template) {
        messageInput.value = template;
        messageInput.dispatchEvent(new Event('input'));
        messageInput.focus();
        updateSmsStats();
        updateRecipientSummary();
        showNotification('Template inserted: ' + type.charAt(0).toUpperCase() + type.slice(1), 'success');
    }
}

/**
 * Select message type
 */
function selectMessageType(type) {
    document.querySelectorAll('.message-type-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.backgroundColor = 'var(--bg-secondary)';
        btn.style.borderColor = 'var(--border-color)';
        btn.style.color = 'var(--text-secondary)';
    });
    
    const selectedBtn = document.querySelector(`.message-type-btn[data-type="${type}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
        selectedBtn.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
        selectedBtn.style.borderColor = 'var(--primary)';
        selectedBtn.style.color = 'var(--primary)';
    }
    
    // Show/hide fields
    document.getElementById('templateFields').classList.toggle('hidden', type !== 'template');
    document.getElementById('mediaFields').classList.toggle('hidden', type !== 'media');
    
    // Update message input placeholder
    const messageInput = document.getElementById('messageInput');
    if (type === 'template') {
        messageInput.placeholder = 'Template message will be sent based on selected template...';
        messageInput.readOnly = true;
        messageInput.style.opacity = '0.7';
    } else if (type === 'media') {
        messageInput.placeholder = 'Message will be sent with media attachment...';
        messageInput.readOnly = true;
        messageInput.style.opacity = '0.7';
    } else {
        messageInput.placeholder = 'Type your message here... Use {name} to insert recipient\'s name';
        messageInput.readOnly = false;
        messageInput.style.opacity = '1';
    }
}

/**
 * Toggle schedule fields
 */
function toggleSchedule() {
    const scheduleCheck = document.getElementById('scheduleCheck');
    const scheduleFields = document.getElementById('scheduleFields');
    const scheduledAt = document.getElementById('scheduledAt');
    
    if (scheduleCheck && scheduleFields) {
        if (scheduleCheck.checked) {
            scheduleFields.classList.remove('hidden');
            if (scheduledAt) {
                scheduledAt.required = true;
            }
        } else {
            scheduleFields.classList.add('hidden');
            if (scheduledAt) {
                scheduledAt.required = false;
            }
        }
    }
}

/**
 * Toggle advanced options
 */
function toggleAdvancedOptions() {
    const advancedOptions = document.getElementById('advancedOptions');
    const icon = document.getElementById('advancedToggleIcon');
    
    if (advancedOptions) {
        advancedOptions.classList.toggle('hidden');
        if (icon) {
            icon.className = advancedOptions.classList.contains('hidden') ? 'fas fa-chevron-right mr-1' : 'fas fa-chevron-down mr-1';
        }
    }
}

/**
 * Reset form
 */
function resetForm() {
    if (!confirm('Are you sure you want to reset the form? All selections will be cleared.')) {
        return;
    }
    
    document.getElementById('composeForm').reset();
    document.getElementById('messageInput').value = '';
    document.getElementById('messageInput').dispatchEvent(new Event('input'));
    selectedUsers = [];
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    document.getElementById('scheduleFields').classList.add('hidden');
    document.getElementById('advancedOptions').classList.add('hidden');
    document.getElementById('templateFields').classList.add('hidden');
    document.getElementById('mediaFields').classList.add('hidden');
    
    // Reset message type to text
    selectMessageType('text');
    
    showNotification('Form reset', 'info');
}

/**
 * Refresh WhatsApp status
 */
function refreshWhatsAppStatus() {
    const button = event?.target || document.querySelector('[onclick="refreshWhatsAppStatus()"]');
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-0.5"></i> Refreshing...';
        button.disabled = true;
    }
    
    fetch('/api/whatsapp/status/refresh', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('WhatsApp status refreshed successfully', 'success');
            // Update status display
            const statusElements = document.querySelectorAll('.whatsapp-status');
            statusElements.forEach(el => {
                el.textContent = data.system_status?.status || 'Online';
            });
        } else {
            showNotification(data.message || 'Failed to refresh status', 'error');
        }
    })
    .catch(error => {
        console.error('Refresh error:', error);
        showNotification('Failed to refresh WhatsApp status', 'error');
    })
    .finally(() => {
        if (button) {
            button.innerHTML = '<i class="fas fa-sync mr-0.5"></i> Refresh';
            button.disabled = false;
        }
    });
}

// ========================================== //
// 💬 NOTIFICATIONS                           //
// ========================================== //

function showNotification(message, type = 'success') {
    const existing = document.querySelectorAll('.custom-notification');
    existing.forEach(n => n.remove());
    
    const colors = {
        success: { bg: '#22c55e', icon: 'fa-check-circle' },
        error: { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info: { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    const color = colors[type] || colors.info;
    
    const notification = document.createElement('div');
    notification.className = 'custom-notification fixed top-4 right-4 z-[99999] px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white';
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

// ========================================== //
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //

document.addEventListener('keydown', function(e) {
    // Ctrl+Enter to send
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('composeForm')?.submit();
    }
    
    // Escape to close notifications
    if (e.key === 'Escape') {
        document.querySelectorAll('.custom-notification').forEach(n => n.remove());
    }
});

// ========================================== //
// 🎨 STYLES                                  //
// ========================================== //

const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .select-user-item {
        cursor: pointer;
    }
    
    #userListContainer::-webkit-scrollbar {
        width: 6px;
    }
    #userListContainer::-webkit-scrollbar-track {
        background: transparent;
    }
    #userListContainer::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }
    #userListContainer::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }
    
    textarea {
        min-height: 150px;
        font-family: inherit;
    }
    
    .custom-notification {
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    
    .message-type-btn.active {
        box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
    }
`;
document.head.appendChild(style);

// ========================================== //
// 🚀 INITIALIZATION                          //
// ========================================== //

document.addEventListener('DOMContentLoaded', function() {
    console.log('💬 WhatsApp Compose Page Loading...');
    
    // Initialize message input handler
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', updateSmsStats);
        // Initial stats
        setTimeout(updateSmsStats, 100);
    }
    
    // Load users if user selection is enabled
    @if($canSelectUsers)
        console.log('👥 User selection enabled, loading users...');
        setTimeout(loadUsers, 500);
    @else
        console.log('👤 User selection disabled for this role');
        // Hide user selection section
        const userSelection = document.querySelector('.user-selection-section');
        if (userSelection) {
            userSelection.style.display = 'none';
        }
    @endif
    
    // Auto-refresh status every 60 seconds
    if (typeof refreshWhatsAppStatus === 'function') {
        autoRefreshTimer = setInterval(() => {
            refreshWhatsAppStatus();
        }, 60000);
    }
    
    // Update current time every minute
    setInterval(() => {
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }, 60000);
    
    console.log('✅ WhatsApp Compose Page Loaded');
    console.log('📊 Selected users:', selectedUsers.length);
    console.log('📱 Max message length:', MAX_MESSAGE_LENGTH);
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
    }
});

console.log('✅ WhatsApp Compose JavaScript Loaded');
</script>

<style>
/* Additional styles for the compose page */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* Dark mode adjustments */
.dark .card {
    border-color: rgba(255,255,255,0.1);
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

/* Tooltip styles */
.tooltip {
    position: relative;
    cursor: help;
}

.tooltip:hover::after {
    content: attr(data-tip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 4px 8px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 11px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    white-space: nowrap;
    z-index: 10;
}

/* Message type button styles */
.message-type-btn {
    transition: all 0.2s ease;
    cursor: pointer;
}

.message-type-btn:hover {
    transform: translateY(-2px);
}

.message-type-btn.active {
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
}

/* File input styling */
input[type="file"] {
    cursor: pointer;
}

input[type="file"]::-webkit-file-upload-button {
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 6px;
    border: none;
    background-color: var(--primary);
    color: white;
    font-size: 12px;
}

/* Loading spinner animation */
@keyframes spin {
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}
</style>
@endsection