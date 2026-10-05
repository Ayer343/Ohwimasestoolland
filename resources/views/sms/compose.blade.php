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
        $pageTitle = 'Compose SMS - Super Admin';
        $canSelectUsers = true;
        $canBulkSend = true;
    } elseif ($isAdmin) {
        $layout = 'layouts.app';
        $pageTitle = 'Compose SMS - Admin';
        $canSelectUsers = true;
        $canBulkSend = true;
    } elseif ($isDeveloper) {
        $layout = 'layouts.dev';
        $pageTitle = 'Compose SMS - Developer';
        $canSelectUsers = true;
        $canBulkSend = true;
    } elseif ($isLandlord) {
        $layout = 'layouts.landlord';
        $pageTitle = 'Compose SMS';
        $canSelectUsers = true;
        $canBulkSend = false;
    } elseif ($isTenant) {
        $layout = 'layouts.tenant';
        $pageTitle = 'Compose SMS';
        $canSelectUsers = false;
        $canBulkSend = false;
    } else {
        $layout = 'layouts.app';
        $pageTitle = 'Compose SMS';
        $canSelectUsers = false;
        $canBulkSend = false;
    }

    // SMS character limits
    $maxSmsLength = 1600; // 10x GSM-7 segments
    $gsm7Length = 160;    // Standard GSM-7 character limit per segment

    // Added contractor, sanitation_personnel (and former_landlord/developer kept consistent)
    $userTypes = [
        'all'                  => 'All Users',
        'landlord'             => 'Landlords',
        'tenant'               => 'Tenants',
        'field_agent'          => 'Field Agents',
        'security_personnel'   => 'Security Personnel',
        'contractor'           => 'Contractors',
        'sanitation_personnel' => 'Sanitation Personnel',
        'admin'                => 'Admins',
        'super_admin'          => 'Super Admins',
    ];

    // Get the user list URL with fallback
    try {
        $userListUrl = route('sms.users.list');
    } catch (\Exception $e) {
        $userListUrl = '/sms/users/list';
    }

    // Get providers
    $providers = $providers ?? [];
    $defaultProvider = $defaultProvider ?? null;

    // Get properties for landlords
    $properties = $isLandlord ? ($user->properties ?? collect()) : collect();

    // Get recent logs
    $recentLogs = $recentLogs ?? collect();

    // Get SMS status
    $smsStatus = $smsStatus ?? ['is_available' => true, 'provider_name' => 'Default'];

    // ------------------------------------------------------------------
    // Effective Sender ID — resolved by the controller via SmsService.
    //
    // The composer NEVER edits the sender ID. It only displays the
    // resolved value for transparency. The actual sending call uses
    // SmsService::resolveSenderId() internally, so this variable is
    // purely informational.
    //
    // Resolution chain used by the controller:
    //   per-provider (.env) → global (DB) → system fallback
    // ------------------------------------------------------------------
    $effectiveSenderId = $effectiveSenderId ?? null;
    $effectiveSenderIdSource = $effectiveSenderIdSource ?? null;
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
                         style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; border-color: #22c55e;">
                        <i class="fas fa-sms text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sms mr-2" style="color: #22c55e;"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-info-circle mr-1"></i> Compose and send SMS messages</span>
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
                <a href="{{ route('sms.index') }}"
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- SMS Provider Status -->
    <div class="card">
        <div class="p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-signal mr-1" style="color: #22c55e;"></i> SMS Provider:
                    </span>
                    <span class="text-sm" style="color: var(--text-secondary);">
                        {{ $smsStatus['provider_name'] ?? ($defaultProvider ?? 'Not Configured') }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs">
                        @if($smsStatus['is_available'] ?? true)
                            <span class="text-green-500">●</span>
                            <span class="ml-1 text-green-700 dark:text-green-300">Available</span>
                        @else
                            <span class="text-red-500">●</span>
                            <span class="ml-1 text-red-700 dark:text-red-300">Unavailable</span>
                        @endif
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-balance-scale mr-0.5"></i>
                        Balance: {{ $smsStatus['balance'] ?? 'N/A' }}
                    </span>
                    <span class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-0.5"></i>
                        Last checked: {{ $smsStatus['last_checked'] ?? 'Never' }}
                    </span>
                    <button type="button" onclick="refreshSmsStatus(event)"
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
            <form id="composeForm" method="POST" action="{{ route('sms.send') }}" enctype="multipart/form-data" novalidate>
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
                                   oninput="filterUsers()"
                                   autocomplete="off">
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
                <!-- 📱 SMS COMPOSE FIELDS                     -->
                <!-- ========================================== -->

                <!-- Hidden fields for bulk send -->
                <input type="hidden" name="recipient_ids" id="recipientIds" value="">
                <input type="hidden" name="is_bulk" id="isBulk" value="0">

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

                <!-- ============================================================ -->
                <!-- SENDER ID — READ-ONLY INFO BADGE (not an input)              -->
                <!--                                                              -->
                <!-- The composer never edits the sender ID. The resolver chain   -->
                <!-- (provider → global → system) decides what's used at send     -->
                <!-- time. This badge is informational only.                     -->
                <!-- ============================================================ -->
                <div class="mb-4">
                    <div class="flex items-center justify-between p-3 rounded-lg"
                         style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-id-card flex-shrink-0" style="color: var(--info);"></i>
                            <span class="text-sm flex-shrink-0" style="color: var(--text-secondary);">Sender ID:</span>
                            <span class="text-sm font-mono font-medium truncate" style="color: var(--text-primary);">
                                {{ $effectiveSenderId ?: 'Auto (system default)' }}
                            </span>
                            @if($effectiveSenderId && $effectiveSenderIdSource)
                                <span class="text-xs px-1.5 py-0.5 rounded flex-shrink-0"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                      title="Where this sender ID comes from">
                                    @if($effectiveSenderIdSource === 'provider')
                                        provider
                                    @elseif($effectiveSenderIdSource === 'global')
                                        global
                                    @else
                                        fallback
                                    @endif
                                </span>
                            @endif
                        </div>
                        <span class="text-xs flex items-center flex-shrink-0 ml-3" style="color: var(--text-secondary);">
                            <i class="fas fa-lock mr-1"></i>
                            Managed by admins
                        </span>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-0.5"></i>
                        The sender ID is configured per provider. Contact an admin if it needs to change.
                    </p>
                </div>

                <!-- Phone Number Input (Manual) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-phone mr-1" style="color: var(--primary);"></i> Phone Number
                        <span class="text-xs" style="color: var(--text-secondary);">(or select users above)</span>
                    </label>
                    <div class="relative">
                        <input type="text"
                               name="phone_number"
                               id="phoneNumberInput"
                               value="{{ old('phone_number') }}"
                               placeholder="e.g., +233240000000"
                               class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                               style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                               autocomplete="off">
                        <div id="phoneSuggestions" class="absolute z-10 w-full mt-1 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        </div>
                    </div>
                    @error('phone_number')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> Include country code (e.g., +233 for Ghana)
                    </p>
                </div>

                <!-- Bulk Send Options -->
                @if($canBulkSend)
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-bolt mt-0.5" style="color: var(--warning);"></i>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Bulk Send Options</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                <div>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                        <input type="checkbox" name="individual_messages" value="1" checked
                                               class="rounded transition-all"
                                               style="accent-color: var(--primary);">
                                        Send individually (each recipient as To)
                                    </label>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                        <input type="checkbox" name="batch_send" value="1"
                                               class="rounded transition-all"
                                               style="accent-color: var(--primary);">
                                        Batch send (chunked for large lists)
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                        <input type="checkbox" name="include_user_names" value="1" checked
                                               class="rounded transition-all"
                                               style="accent-color: var(--primary);">
                                        Personalize with user names
                                    </label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="text-xs" style="color: var(--text-secondary);">Batch size:</span>
                                        <input type="number" name="batch_size" value="50" min="10" max="100"
                                               class="w-16 px-2 py-0.5 text-xs rounded border"
                                               style="background-color: var(--input-bg); color: var(--text-primary); border-color: var(--border-color);">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Message -->
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
                              maxlength="{{ $maxSmsLength }}"
                              required>{{ old('message') }}</textarea>
                    @error('message')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror

                    <!-- Character Counter -->
                    <div class="flex flex-wrap justify-between text-xs mt-1" style="color: var(--text-secondary);">
                        <div class="flex items-center gap-3">
                            <span><i class="fas fa-font mr-0.5"></i> <span id="charCounter">0</span> characters</span>
                            <span><i class="fas fa-layer-group mr-0.5"></i> <span id="smsCounter">1</span> SMS segment(s)</span>
                        </div>
                        <div>
                            <span id="charRemaining">{{ $gsm7Length }}</span> remaining
                            <span id="charWarning" class="hidden ml-2" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-0.5"></i> Multi-part SMS
                            </span>
                            <span id="unicodeWarning" class="hidden ml-2" style="color: var(--warning);">
                                <i class="fas fa-language mr-0.5"></i> Unicode (70 chars/segment)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Schedule
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="schedule" id="scheduleCheck"
                                   class="rounded transition-all"
                                   style="accent-color: var(--primary);"
                                   onchange="toggleSchedule()">
                            <label for="scheduleCheck" class="text-sm" style="color: var(--text-secondary);">
                                Schedule for later
                            </label>
                        </div>
                        <div id="scheduleFields" class="hidden flex items-center gap-3">
                            <input type="datetime-local"
                                   name="scheduled_at"
                                   id="scheduledAt"
                                   min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                                   class="rounded-lg px-3 py-1.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-0.5"></i>
                                Must be at least 5 minutes from now
                            </span>
                        </div>
                    </div>
                    @error('scheduled_at')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Template Suggestions -->
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

                <!-- Recipient Summary -->
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
                            <span style="color: var(--text-secondary);">SMS Parts:</span>
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
                                class="inline-flex items-center px-6 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105 disabled:opacity-60 disabled:cursor-not-allowed"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                            <i class="fas fa-paper-plane mr-2"></i>
                            <span id="sendButtonText">Send SMS</span>
                        </button>

                        <!-- ⚠️ type="button" (was "reset") — prevents native form reset flash -->
                        <button type="button"
                                onclick="resetForm()"
                                class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-undo mr-2"></i> Reset
                        </button>
                    </div>

                    <div class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                        <span>
                            <i class="fas fa-circle text-[6px] mr-1" style="color: var(--success);"></i>
                            System Ready
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
let hasLoadedOnce = false;              // ✅ FIX: track first load
let searchDebounceTimer = null;         // ✅ FIX: debounce search
let autoRefreshTimer = null;            // ✅ FIX: visibility-aware refresh
let isSubmitting = false;               // ✅ FIX: guard double submit

// Configuration
const USER_LIST_URL = '{{ $userListUrl }}';
const MAX_SMS_LENGTH = {{ $maxSmsLength }};
const GSM7_LENGTH = {{ $gsm7Length }};
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

// ✅ Centralized type maps — single source of truth
const USER_TYPE_BY_CODE = {
    0: 'super_admin',
    1: 'admin',
    2: 'landlord',
    3: 'tenant',
    4: 'field_agent',
    5: 'developer',
    6: 'security_personnel',
    7: 'former_landlord',
    8: 'contractor',
    9: 'sanitation_personnel',
};

const USER_TYPE_LABELS = {
    'super_admin':          'Super Admin',
    'admin':                'Admin',
    'developer':            'Developer',
    'landlord':             'Landlord',
    'former_landlord':      'Former Landlord',
    'tenant':               'Tenant',
    'field_agent':          'Field Agent',
    'security_personnel':   'Security Personnel',
    'contractor':           'Contractor',
    'sanitation_personnel': 'Sanitation Personnel',
};

/**
 * ✅ Resolve user type slug.
 */
function getUserTypeString(typeCode, roleSlug) {
    if (roleSlug && typeof roleSlug === 'string' && roleSlug.trim() !== '') {
        return roleSlug.trim().toLowerCase().replace(/-/g, '_');
    }
    if (typeCode !== null && typeCode !== undefined && typeCode !== '') {
        const key = String(typeCode);
        if (USER_TYPE_BY_CODE[key]) {
            return USER_TYPE_BY_CODE[key];
        }
    }
    return 'unknown';
}

function formatUserType(type) {
    if (!type) return 'Unknown';
    if (USER_TYPE_LABELS[type]) return USER_TYPE_LABELS[type];
    return String(type).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function getUserRoleSlug(user) {
    if (!user) return null;
    return user.primary_role || user.role_slug || user.user_type || user.role || null;
}

/**
 * Load users from the server.
 *
 * ⚠️ FIX: Only shows the "Loading..." spinner on the FIRST load.
 * Subsequent refetches don't blank the list — they swap it silently
 * once data arrives. This kills the "page refresh" feel.
 */
function loadUsers() {
    if (isLoadingUsers) return;
    isLoadingUsers = true;

    const userList = document.getElementById('userList');

    // Only show spinner on the very first load
    if (!hasLoadedOnce) {
        userList.innerHTML = `
            <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading users...
            </div>
        `;
    }

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

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF_TOKEN
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        // Remove BOM if present
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.substring(1);
        }

        const data = JSON.parse(text);
        isLoadingUsers = false;
        hasLoadedOnce = true;

        if (data.success) {
            allUsers = data.users || [];

            // Filter out developer users
            allUsers = allUsers.filter(user => {
                const slug = getUserTypeString(user.type, getUserRoleSlug(user));
                return slug !== 'developer';
            });

            // Only users with phone numbers
            allUsers = allUsers.filter(user => user.phone && user.phone.trim() !== '');

            renderUserList();

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
        }
    })
    .catch(error => {
        isLoadingUsers = false;
        userList.innerHTML = `
            <div class="text-center py-8 text-sm" style="color: var(--danger);">
                <i class="fas fa-exclamation-circle mr-2"></i>
                Error loading users.
                <br>
                <span class="text-xs">${error.message}</span>
                <br>
                <button type="button" onclick="loadUsers()" class="mt-2 px-3 py-1 text-xs rounded transition"
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
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase().trim() || '';

    filteredUsers = allUsers.filter(user => {
        const userTypeString = getUserTypeString(user.type, getUserRoleSlug(user));

        if (typeFilter !== 'all' && userTypeString !== typeFilter) {
            return false;
        }

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
        updateSelectedCount();
        updateRecipientSummary();
        return;
    }

    let html = '';
    filteredUsers.forEach(user => {
        const isSelected = selectedUsers.some(u => u.id === user.id);
        const hasPhone = user.phone && user.phone.trim() !== '';
        const userTypeString = getUserTypeString(user.type, getUserRoleSlug(user));
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

function addUserType(type) {
    const typeUsers = allUsers.filter(u => {
        const userTypeString = getUserTypeString(u.type, getUserRoleSlug(u));
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
 * ✅ FIX: filterUsers is now debounced.
 *   - Re-renders locally (instant, no network)
 *   - Only hits the server after user stops typing for 500ms
 */
function filterUsers() {
    // Always re-render locally first (instant feedback)
    renderUserList();

    // Debounce the server fetch
    clearTimeout(searchDebounceTimer);
    const searchTerm = document.getElementById('userSearch')?.value || '';
    if (searchTerm.length > 2) {
        searchDebounceTimer = setTimeout(() => {
            loadUsers();
        }, 500);
    }
}

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

function removeUser(userId) {
    selectedUsers = selectedUsers.filter(u => u.id !== userId);
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();
    showNotification('User removed from selection', 'info');
}

function updateSelectedCount() {
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = selectedUsers.length;
    }
}

function updateRecipientSummary() {
    const countElement = document.getElementById('recipientCount');
    const phoneCountElement = document.getElementById('recipientPhoneCount');
    const partsElement = document.getElementById('estimatedParts');
    const costElement = document.getElementById('estimatedCost');

    const withPhone = selectedUsers.filter(u => u.phone && u.phone.trim() !== '');

    if (countElement) {
        countElement.textContent = selectedUsers.length;
    }

    if (phoneCountElement) {
        phoneCountElement.textContent = withPhone.length;
    }

    const message = document.getElementById('messageInput')?.value || '';
    const messageLength = message.length;
    const isUnicode = /[^\x00-\x7F]/.test(message);
    const charsPerSegment = isUnicode ? 70 : 160;
    const smsParts = Math.max(1, Math.ceil(messageLength / charsPerSegment));
    const totalParts = smsParts * withPhone.length;

    if (partsElement) {
        partsElement.textContent = totalParts;
    }

    if (costElement) {
        const cost = totalParts * 0.05;
        costElement.textContent = cost.toFixed(2) + ' GHS';
    }
}

function updateSendButtonText() {
    const button = document.getElementById('sendButtonText');
    if (!button) return;

    if (selectedUsers.length > 0) {
        button.textContent = `Send to ${selectedUsers.length} recipient${selectedUsers.length > 1 ? 's' : ''}`;
    } else {
        button.textContent = 'Send SMS';
    }
}

function updateHiddenFields() {
    const ids = selectedUsers.map(u => u.id);
    document.getElementById('recipientIds').value = ids.join(',');
    document.getElementById('isBulk').value = selectedUsers.length > 0 ? '1' : '0';
}

function getInitials(name) {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function getColorForUser(user, lighter = false) {
    const colors = ['#4F46E5', '#7C3AED', '#EC4899', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6'];
    const index = (user.id || 0) % colors.length;
    return colors[index];
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// 📝 FORM HANDLING                           //
// ========================================== //

function updateSmsStats() {
    const messageInput = document.getElementById('messageInput');
    const charCounter = document.getElementById('charCounter');
    const smsCounter = document.getElementById('smsCounter');
    const charWarning = document.getElementById('charWarning');
    const charRemaining = document.getElementById('charRemaining');
    const unicodeWarning = document.getElementById('unicodeWarning');

    if (!messageInput) return;

    const text = messageInput.value;
    const length = text.length;
    const isUnicode = /[^\x00-\x7F]/.test(text);
    const charsPerSegment = isUnicode ? 70 : 160;
    const smsParts = Math.max(1, Math.ceil(length / charsPerSegment));
    const remaining = Math.max(0, charsPerSegment - length % charsPerSegment);

    if (charCounter) charCounter.textContent = length;
    if (smsCounter) smsCounter.textContent = smsParts;

    if (charRemaining) {
        charRemaining.textContent = remaining > 0 ? remaining : (isUnicode ? 70 : 160);
    }

    if (charWarning) {
        charWarning.classList.toggle('hidden', length <= charsPerSegment);
    }

    if (unicodeWarning) {
        unicodeWarning.classList.toggle('hidden', !isUnicode);
    }

    updateRecipientSummary();
}

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

function toggleSchedule() {
    const scheduleCheck = document.getElementById('scheduleCheck');
    const scheduleFields = document.getElementById('scheduleFields');
    const scheduledAt = document.getElementById('scheduledAt');

    if (scheduleCheck && scheduleFields) {
        if (scheduleCheck.checked) {
            scheduleFields.classList.remove('hidden');
            if (scheduledAt) scheduledAt.required = true;
        } else {
            scheduleFields.classList.add('hidden');
            if (scheduledAt) scheduledAt.required = false;
        }
    }
}

/**
 * ✅ FIX: Full JS reset (button is now type="button").
 */
function resetForm() {
    if (!confirm('Are you sure you want to reset the form? All selections will be cleared.')) {
        return;
    }

    const form = document.getElementById('composeForm');
    if (form) form.reset();

    const msg = document.getElementById('messageInput');
    if (msg) {
        msg.value = '';
        msg.dispatchEvent(new Event('input'));
    }

    selectedUsers = [];
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientSummary();
    updateSendButtonText();
    updateHiddenFields();

    const scheduleFields = document.getElementById('scheduleFields');
    if (scheduleFields) scheduleFields.classList.add('hidden');

    showNotification('Form reset', 'info');
}

/**
 * ✅ FIX: refreshSmsStatus accepts an optional event for the button state.
 */
function refreshSmsStatus(event) {
    const button = event?.target?.closest('button') || document.querySelector('[onclick="refreshSmsStatus(event)"]');
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-0.5"></i> Refreshing...';
        button.disabled = true;
    }

    fetch('/api/sms/status/refresh', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF_TOKEN
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('SMS status refreshed successfully', 'success');
            const statusElements = document.querySelectorAll('.sms-status');
            statusElements.forEach(el => {
                el.textContent = data.system_status?.status || 'Available';
            });
        } else {
            showNotification(data.message || 'Failed to refresh status', 'error');
        }
    })
    .catch(error => {
        console.error('Refresh error:', error);
        showNotification('Failed to refresh SMS status', 'error');
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
        error:   { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info:    { bg: '#3b82f6', icon: 'fa-info-circle' }
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
            <button type="button" onclick="this.closest('.custom-notification').remove()" class="ml-3 text-white hover:text-gray-200">
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
// 🛑 PREVENT PAGE RELOAD                     //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('composeForm');
    if (!form) return;

    // ✅ Block Enter-key submits from non-textarea fields
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const el = e.target;
        if (el.tagName === 'TEXTAREA') return;
        e.preventDefault();
    });

    // ✅ AJAX submit — no page reload
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isSubmitting) return;

        // Basic client-side validation
        const message = document.getElementById('messageInput')?.value?.trim() || '';
        const phone = document.getElementById('phoneNumberInput')?.value?.trim() || '';
        const recipientIds = document.getElementById('recipientIds')?.value?.trim() || '';

        if (!message) {
            showNotification('Please enter a message.', 'error');
            document.getElementById('messageInput')?.focus();
            return;
        }

        if (!phone && !recipientIds) {
            showNotification('Please enter a phone number or select at least one recipient.', 'error');
            document.getElementById('phoneNumberInput')?.focus();
            return;
        }

        isSubmitting = true;

        const btn = document.getElementById('sendButton');
        const btnText = document.getElementById('sendButtonText');
        const originalText = btnText?.textContent || 'Send SMS';
        if (btn) btn.disabled = true;
        if (btnText) btnText.textContent = 'Sending...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                showNotification(data.message || 'SMS sent successfully!', 'success');

                // Clear just the message — keep selections so the user can send another
                const msg = document.getElementById('messageInput');
                if (msg) {
                    msg.value = '';
                    msg.dispatchEvent(new Event('input'));
                }
            } else {
                const msg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to send SMS.');
                showNotification(msg, 'error');
            }
        })
        .catch(err => {
            console.error('Send error:', err);
            showNotification('Network error — please try again.', 'error');
        })
        .finally(() => {
            isSubmitting = false;
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        });
    });
});

// ========================================== //
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //

document.addEventListener('keydown', function (e) {
    // Ctrl+Enter to send (from anywhere in the form)
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('composeForm')?.requestSubmit
            ? document.getElementById('composeForm').requestSubmit()
            : document.getElementById('composeForm')?.submit();
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
`;
document.head.appendChild(style);

// ========================================== //
// 🚀 INITIALIZATION                          //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    console.log('📱 SMS Compose Page Loading...');

    // Initialize message input handler
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', updateSmsStats);
        setTimeout(updateSmsStats, 100);
    }

    // Load users if user selection is enabled
    @if($canSelectUsers)
        console.log('👥 User selection enabled, loading users...');
        setTimeout(loadUsers, 500);
    @else
        console.log('👤 User selection disabled for this role');
        const userSelection = document.querySelector('.user-selection-section');
        if (userSelection) {
            userSelection.style.display = 'none';
        }
    @endif

    // ✅ FIX: Visibility-aware auto-refresh — pauses when tab is hidden
    function startAutoRefresh() {
        if (autoRefreshTimer) return;
        autoRefreshTimer = setInterval(() => {
            // Silent refresh — no notification spam
            fetch('/api/sms/status/refresh', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            }).catch(() => { /* silent */ });
        }, 60000);
    }

    function stopAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });

    // Kick off auto-refresh on load (only if visible)
    if (!document.hidden) {
        startAutoRefresh();
    }

    // Update current time every minute
    setInterval(() => {
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }, 60000);

    console.log('✅ SMS Compose Page Loaded');
    console.log('📊 Selected users:', selectedUsers.length);
    console.log('📱 Max SMS length:', MAX_SMS_LENGTH);
});

// Clean up on page unload
window.addEventListener('beforeunload', function () {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

console.log('✅ SMS Compose JavaScript Loaded');
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
</style>
@endsection