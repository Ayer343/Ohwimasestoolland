{{-- email-accounts/compose.blade.php --}}
@php
    $user = auth()->user();
    $isDeveloper  = $user->isDeveloper();
    $isAdmin      = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isLandlord   = $user->isLandlord();
    $isTenant     = $user->isTenant();

    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath         = 'super-admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Compose Email - Super Admin';
        $backRoute       = route('super-admin.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = true;
        $canBulkSend     = true;
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'Compose Email - Developer';
        $backRoute       = route('developer.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = true;
        $canBulkSend     = true;
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath         = 'admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Compose Email - Admin';
        $backRoute       = route('admin.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = true;
        $canBulkSend     = true;
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'Compose Email';
        $backRoute       = route('landlord.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = false;
        $canBulkSend     = false;
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'Compose Email';
        $backRoute       = route('tenant.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = false;
        $canBulkSend     = false;
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Compose Email';
        $backRoute       = route('email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
        $canSelectUsers  = false;
        $canBulkSend     = false;
    }

    $emailAccount = $emailAccount ?? null;
    $attachments  = $attachments ?? [];

    $emailsSentToday = $emailAccount ? ($emailAccount->emails_sent_today ?? 0) : 0;
    $dailySendLimit  = $emailAccount ? ($emailAccount->daily_send_limit ?? 500) : 500;
    $quotaPercentage = $dailySendLimit > 0 ? min(100, ($emailsSentToday / $dailySendLimit) * 100) : 0;
    $quotaExceeded   = $emailsSentToday >= $dailySendLimit;

    $autoSaveInterval = 30;

    $errorMessage   = session('error');
    $successMessage = session('success');
    $warningMessage = session('warning');

    $userTypes = [
        'all'                   => 'All Users',
        'landlord'              => 'Landlords',
        'tenant'                => 'Tenants',
        'field_agent'           => 'Field Agents',
        'security_personnel'    => 'Security Personnel',
        'contractor'            => 'Contractors',
        'sanitation_personnel'  => 'Sanitation Personnel',
        'admin'                 => 'Admins',
        'super_admin'           => 'Super Admins',
    ];

    try {
        if ($isDeveloper && Route::has('developer.users.list-email')) {
            $userListUrl = route('developer.users.list-email');
        } elseif ($isSuperAdmin && Route::has('super-admin.users.list-email')) {
            $userListUrl = route('super-admin.users.list-email');
        } elseif (Route::has('admin.users.list-email')) {
            $userListUrl = route('admin.users.list-email');
        } elseif ($isLandlord && Route::has('landlord.users.list-email')) {
            $userListUrl = route('landlord.users.list-email');
        } elseif ($isTenant && Route::has('tenant.users.list-email')) {
            $userListUrl = route('tenant.users.list-email');
        } elseif (Route::has('users.list-email')) {
            $userListUrl = route('users.list-email');
        } else {
            $userListUrl = $isDeveloper
                ? url('/developer/users/list-email')
                : url('/admin/users/list-email');
        }
    } catch (\Exception $e) {
        $userListUrl = $isDeveloper
            ? url('/developer/users/list-email')
            : url('/admin/users/list-email');
    }
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
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-pen text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-pen mr-2" style="color: var(--primary);"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-info-circle mr-1"></i> Compose and send email messages</span>
                        @if($emailAccount)
                            <span class="hidden sm:inline">•</span>
                            <span><i class="fas fa-envelope mr-1"></i> {{ $emailAccount->email }}</span>
                            @if($emailAccount->is_primary)
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    <i class="fas fa-star mr-0.5"></i> Primary
                                </span>
                            @endif
                        @endif
                        @if($canBulkSend)
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                <i class="fas fa-users mr-0.5"></i> Bulk Send Available
                            </span>
                        @endif
                        @if($quotaExceeded)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                Daily send limit reached
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm mt-2 sm:mt-0" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ $backRoute }}"
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas {{ $backIcon }} mr-1"></i> {{ $backText }}
                </a>
            </div>
        </div>
    </div>

    <!-- Quota Warning -->
    @if($quotaExceeded)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative dark:bg-red-900 dark:border-red-700 dark:text-red-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Send Limit Reached!</span>
            <span class="ml-2">You have reached your daily send limit of {{ $dailySendLimit }} emails. Please try again tomorrow.</span>
        </div>
    </div>
    @endif

    <!-- Success/Error/Warning Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900 dark:border-green-700 dark:text-green-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($errorMessage)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative dark:bg-red-900 dark:border-red-700 dark:text-red-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($warningMessage)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">Warning!</span>
            <span class="ml-2">{{ $warningMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Compose Card -->
    <div class="card">
        <div class="p-6">
            @if(!$emailAccount)
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        No Email Account Linked
                    </h4>
                    <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                        Please link an email account before composing messages.
                    </p>
                    <a href="{{ route($routeNamePrefix . '.create') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                       style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-link mr-2"></i> Link Your First Email
                    </a>
                </div>
            @else
                <form id="composeForm" method="POST" action="{{ route($routeNamePrefix . '.send') }}" enctype="multipart/form-data">
                    @csrf

                    @if(auth()->user()->emailAccounts()->count() > 1)
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-user-circle mr-1" style="color: var(--primary);"></i> From
                        </label>
                        <select name="email_account_id"
                                class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                onchange="updateQuota(this.value)">
                            @foreach(auth()->user()->emailAccounts as $account)
                                <option value="{{ $account->id }}"
                                        {{ $emailAccount->id == $account->id ? 'selected' : '' }}
                                        data-sent="{{ $account->emails_sent_today ?? 0 }}"
                                        data-limit="{{ $account->daily_send_limit ?? 500 }}">
                                    {{ $account->email }}
                                    @if($account->is_primary) (Primary) @endif
                                    - {{ $account->emails_sent_today ?? 0 }}/{{ $account->daily_send_limit ?? 500 }}
                                    {{ (($account->emails_sent_today ?? 0) >= ($account->daily_send_limit ?? 500)) ? '🔴' : '🟢' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                        <input type="hidden" name="email_account_id" value="{{ $emailAccount->id }}">
                    @endif

                    @if($canSelectUsers)
                    <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-users" style="color: var(--info);"></i>
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">Select Recipients</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <span id="selectedCount">0</span> selected
                            </span>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-search mr-0.5"></i> Search Users
                                </label>
                                <input type="text"
                                       id="userSearch"
                                       placeholder="Search by name or email..."
                                       class="w-full rounded-lg px-3 py-2 text-sm transition-all focus:ring-2 focus:outline-none"
                                       style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                       oninput="filterUsers()">
                            </div>
                        </div>

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
                                    <button type="button" onclick="selectUsersWithEmail()"
                                            class="text-xs px-2 py-1 rounded transition"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-envelope mr-0.5"></i> With Email
                                    </button>
                                </div>
                            </div>

                            <div id="userListContainer" class="max-h-60 overflow-y-auto rounded-lg border" style="border-color: var(--border-color);">
                                <div id="userList" class="divide-y" style="border-color: var(--border-color);">
                                    <div class="text-center py-8 text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading users...
                                    </div>
                                </div>
                            </div>
                        </div>

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

                    <!-- To Field -->
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-1" style="color: var(--primary);"></i> To <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div id="selectedUsersTags" class="flex flex-wrap gap-1 mb-2"></div>

                            <input type="text"
                                   name="to"
                                   id="toInput"
                                   value="{{ old('to') }}"
                                   placeholder="recipient@example.com or select users above"
                                   class="w-full rounded-lg px-4 py-2.5 pr-10 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                                   autocomplete="off"
                                   inputmode="email">
                            <div id="toSuggestions" class="absolute z-10 w-full mt-1 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"
                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            </div>
                        </div>

                        <div id="toAutoFilledNotice"
                             class="hidden mt-1 text-xs flex items-center gap-1"
                             style="color: var(--info);">
                            <i class="fas fa-magic"></i>
                            <span><span id="toAutoFilledCount">0</span> recipient email(s) auto-selected from your user list</span>
                        </div>

                        @error('to')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror

                        <input type="hidden" name="recipient_ids" id="recipientIds" value="">
                        <input type="hidden" name="is_bulk" id="isBulk" value="0">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-copy mr-1" style="color: var(--secondary);"></i> CC
                            </label>
                            <input type="email"
                                   name="cc"
                                   value="{{ old('cc') }}"
                                   placeholder="cc@example.com"
                                   class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                            @error('cc')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-eye-slash mr-1" style="color: var(--secondary);"></i> BCC
                            </label>
                            <input type="email"
                                   name="bcc"
                                   value="{{ old('bcc') }}"
                                   placeholder="bcc@example.com"
                                   class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                            @error('bcc')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-heading mr-1" style="color: var(--primary);"></i> Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="subject"
                               id="subjectInput"
                               value="{{ old('subject') }}"
                               placeholder="Enter email subject"
                               class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                               style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                               required
                               maxlength="255">
                        <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="subjectCounter">0/255</span>
                            <span id="subjectWarning" class="hidden" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-0.5"></i> Subject may be truncated in some email clients
                            </span>
                        </div>
                        @error('subject')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-align-left mr-1" style="color: var(--primary);"></i> Message <span class="text-red-500">*</span>
                        </label>

                        <div class="flex flex-wrap items-center gap-1 p-2 rounded-t-lg border-b"
                             style="background-color: rgba(var(--secondary-rgb), 0.05); border-color: var(--border-color);">
                            <button type="button" data-command="bold" class="toolbar-btn" title="Bold (Ctrl+B)">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" data-command="italic" class="toolbar-btn" title="Italic (Ctrl+I)">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" data-command="underline" class="toolbar-btn" title="Underline (Ctrl+U)">
                                <i class="fas fa-underline"></i>
                            </button>
                            <button type="button" data-command="strikethrough" class="toolbar-btn" title="Strikethrough">
                                <i class="fas fa-strikethrough"></i>
                            </button>
                            <span class="w-px h-6" style="background-color: var(--border-color);"></span>

                            <button type="button" data-command="insertUnorderedList" class="toolbar-btn" title="Bullet List">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" data-command="insertOrderedList" class="toolbar-btn" title="Numbered List">
                                <i class="fas fa-list-ol"></i>
                            </button>
                            <span class="w-px h-6" style="background-color: var(--border-color);"></span>

                            <button type="button" data-command="indent" class="toolbar-btn" title="Increase Indent">
                                <i class="fas fa-indent"></i>
                            </button>
                            <button type="button" data-command="outdent" class="toolbar-btn" title="Decrease Indent">
                                <i class="fas fa-outdent"></i>
                            </button>
                            <span class="w-px h-6" style="background-color: var(--border-color);"></span>

                            <button type="button" data-command="link" class="toolbar-btn" title="Insert Link">
                                <i class="fas fa-link"></i>
                            </button>
                            <button type="button" data-command="image" class="toolbar-btn" title="Insert Image">
                                <i class="fas fa-image"></i>
                            </button>
                            <button type="button" data-command="clearFormatting" class="toolbar-btn" title="Clear Formatting">
                                <i class="fas fa-eraser"></i>
                            </button>
                            <span class="w-px h-6" style="background-color: var(--border-color);"></span>

                            <button type="button" data-command="fullscreen" class="toolbar-btn ml-auto" title="Fullscreen">
                                <i class="fas fa-expand" id="fullscreenIcon"></i>
                            </button>
                        </div>

                        <div id="bodyEditor"
                             contenteditable="true"
                             class="w-full rounded-b-lg px-4 py-3 text-sm focus:ring-2 focus:outline-none min-h-[300px] max-h-[500px] overflow-y-auto"
                             style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
                             role="textbox"
                             aria-multiline="true"
                             data-placeholder="Write your message here...">
                            {!! old('body') !!}
                        </div>

                        <textarea name="body" id="bodyInput" style="display:none;">{{ old('body') }}</textarea>

                        <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                            <span><span id="charCount">0</span> characters</span>
                            <span class="flex items-center gap-2">
                                <span id="wordCount">0</span> words
                                <span class="hidden sm:inline">•</span>
                                <span id="lineCount">0</span> lines
                            </span>
                            <span id="autoSaveStatus" style="color: var(--text-secondary);">
                                <i class="fas fa-circle text-[6px] mr-1" style="color: var(--success);"></i>
                                Auto-save enabled
                            </span>
                        </div>

                        @error('body')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                            <i class="fas fa-paperclip mr-1" style="color: var(--primary);"></i> Attachments
                        </label>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative">
                                <input type="file"
                                       name="attachments[]"
                                       id="attachmentsInput"
                                       multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       onchange="handleFiles(this.files)">
                                <button type="button"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-cloud-upload-alt mr-2"></i> Choose Files
                                </button>
                            </div>
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-0.5"></i> Max 10MB per file
                            </span>
                        </div>

                        <div id="fileList" class="mt-3 space-y-2"></div>

                        <div id="fileSizeWarning" class="hidden mt-2 p-2 rounded-lg text-xs"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Total attachment size exceeds 10MB. Some files may be rejected.
                        </div>
                    </div>

                    @if($emailAccount->enable_signature && $emailAccount->signature)
                    <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-signature mt-0.5" style="color: var(--secondary);"></i>
                            <div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">Signature</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="include_signature" value="1" checked class="sr-only peer">
                                        <div class="w-9 h-5 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"
                                             style="background-color: var(--primary);">
                                    </label>
                                    <span class="text-xs" style="color: var(--text-secondary);">Include signature</span>
                                </div>
                                <div class="text-sm mt-1 p-2 rounded" style="background-color: rgba(var(--text-primary-rgb), 0.05); color: var(--text-secondary);">
                                    {!! nl2br(e($emailAccount->signature)) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-flag mr-1" style="color: var(--primary);"></i> Priority
                            </label>
                            <select name="priority"
                                    class="w-full rounded-lg px-4 py-2.5 text-sm transition-all focus:ring-2 focus:outline-none"
                                    style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                                <i class="fas fa-check-double mr-1" style="color: var(--primary);"></i> Options
                            </label>
                            <div class="flex flex-wrap gap-3 pt-1.5">
                                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--text-secondary);">
                                    <input type="checkbox" name="request_read_receipt" value="1"
                                           class="rounded transition-all"
                                           style="accent-color: var(--primary);">
                                    Request read receipt
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--text-secondary);">
                                    <input type="checkbox" name="save_to_sent" value="1" checked
                                           class="rounded transition-all"
                                           style="accent-color: var(--primary);">
                                    Save to sent
                                </label>
                            </div>
                        </div>
                    </div>

                    @if($canBulkSend)
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-bolt mt-0.5" style="color: var(--warning);"></i>
                            <div class="flex-1">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Bulk Send Options</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                    <div>
                                        <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                            <input type="checkbox" name="individual_emails" value="1" checked
                                                   class="rounded transition-all"
                                                   style="accent-color: var(--primary);">
                                            Send individually (each recipient as To)
                                        </label>
                                        <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                            <input type="checkbox" name="use_bcc" value="1"
                                                   class="rounded transition-all"
                                                   style="accent-color: var(--primary);">
                                            Use BCC (all recipients hidden)
                                        </label>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                            <input type="checkbox" name="batch_send" value="1"
                                                   class="rounded transition-all"
                                                   style="accent-color: var(--primary);">
                                            Batch send (chunked for large lists)
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

                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                        <div class="flex justify-between text-xs mb-0.5" style="color: var(--text-secondary);">
                            <span>
                                <i class="fas fa-chart-line mr-1" style="color: var(--primary);"></i>
                                Daily Send Quota
                            </span>
                            <span id="quotaText">{{ $emailsSentToday }} / {{ $dailySendLimit }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                            <div class="h-full rounded-full transition-all duration-500"
                                 id="quotaBar"
                                 style="width: {{ $quotaPercentage }}%; background: linear-gradient(90deg, var(--primary), var(--secondary));">
                            </div>
                        </div>
                        <div class="flex justify-between text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="quotaStatus">
                                @if($quotaExceeded)
                                    <i class="fas fa-exclamation-circle text-red-500 mr-0.5"></i> Limit reached
                                @elseif($quotaPercentage > 80)
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-0.5"></i> Approaching limit
                                @else
                                    <i class="fas fa-check-circle text-green-500 mr-0.5"></i> Available
                                @endif
                            </span>
                            <span id="remainingQuota">{{ $dailySendLimit - $emailsSentToday }} remaining</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t" style="border-color: var(--border-color);">
                        <div class="flex flex-wrap gap-3">
                            <button type="submit"
                                    id="sendButton"
                                    class="inline-flex items-center px-6 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                                    style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"
                                    {{ $quotaExceeded ? 'disabled' : '' }}>
                                <i class="fas fa-paper-plane mr-2"></i> <span id="sendButtonText">Send</span>
                            </button>

                            <button type="button"
                                    onclick="saveDraft()"
                                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-save mr-2"></i> Save Draft
                            </button>

                            <button type="reset"
                                    onclick="resetForm()"
                                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                <i class="fas fa-undo mr-2"></i> Reset
                            </button>
                        </div>

                        <div class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                            <span id="draftStatus">
                                <i class="fas fa-circle text-[6px] mr-1" style="color: var(--success);"></i>
                                Draft saved
                            </span>
                            <span class="hidden sm:inline">•</span>
                            <span id="lastSavedTime">{{ now()->format('g:i A') }}</span>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<div id="draftNotification" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="px-6 py-3 rounded-lg shadow-lg text-white" style="background-color: var(--success);">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span>Draft saved successfully!</span>
            <button onclick="this.closest('#draftNotification').classList.add('hidden')" class="ml-3 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

<div id="fullscreenOverlay" class="fixed inset-0 hidden"
     style="z-index: 99999; background-color: rgba(0,0,0,0.5);">
    <div class="absolute inset-4 rounded-lg shadow-2xl overflow-auto"
         style="background-color: var(--card-bg); z-index: 100000;">
        <div class="sticky top-0 z-10 p-4 border-b flex justify-between items-center"
             style="background-color: var(--card-bg); border-color: var(--border-color);">
            <h4 class="font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-expand mr-2" style="color: var(--primary);"></i> Fullscreen Editor
            </h4>
            <button onclick="toggleFullscreen()" class="p-2 rounded-lg transition hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-compress"></i>
            </button>
        </div>
        <div class="p-4">
            <div id="fullscreenEditor"
                 contenteditable="true"
                 class="w-full rounded-lg px-4 py-3 text-sm focus:ring-2 focus:outline-none min-h-[400px]"
                 style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);"></div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const routePrefix     = '{{ $routeNamePrefix }}';
const csrfToken       = '{{ csrf_token() }}';
const autoSaveInterval = {{ $autoSaveInterval }};
const userListUrl     = '{{ $userListUrl }}';
const sendUrl         = '{{ route($routeNamePrefix . ".send") }}';

let autoSaveTimer = null;
let isFullscreen  = false;
let selectedUsers = [];
let allUsers      = [];
let filteredUsers = [];
let isLoadingUsers = false;

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
        credentials: 'same-origin',
    });

    const text = await response.text();
    let data;
    try {
        const clean = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
        data = clean ? JSON.parse(clean) : {};
    } catch (e) {
        console.error('Non-JSON response from', url, ':', text.slice(0, 300));
        throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
    }

    if (!response.ok) {
        let msg = data.message || `HTTP ${response.status}`;
        if (data.errors) {
            msg += ': ' + Object.entries(data.errors)
                .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
                .join('; ');
        }
        throw new Error(msg);
    }

    return data;
}

// ========================================== //
// 📧 RECIPIENT EMAIL RESOLUTION               //
// ========================================== //
/**
 * STRICT PRIORITY:
 *   1. `user.recipient_email` — backend computed, trust it.
 *   2. `user.email_accounts` — inspect directly as fallback:
 *        a. Primary account
 *        b. Any verified account
 *        c. Any linked account with an email
 *   3. `user.email` — profile email as last resort
 *   4. null → user is not a valid recipient
 */
function resolveRecipientEmail(user) {
    if (!user) return null;

    // 1. Backend-provided recipient_email
    if (user.recipient_email && typeof user.recipient_email === 'string' && user.recipient_email.trim() !== '') {
        return user.recipient_email.trim();
    }

    // 2. Inspect email_accounts directly
    var accounts = Array.isArray(user.email_accounts) ? user.email_accounts.slice() : [];

    if (accounts.length > 0) {
        var primary = accounts.find(function (a) {
            return (a.is_primary === true || a.is_primary === 1)
                && a.email && String(a.email).trim() !== '';
        });
        if (primary) return String(primary.email).trim();

        var verified = accounts.find(function (a) {
            return (a.status === 'verified' || a.is_verified === true)
                && a.email && String(a.email).trim() !== '';
        });
        if (verified) return String(verified.email).trim();

        var any = accounts.find(function (a) {
            return a.email && String(a.email).trim() !== '';
        });
        if (any) return String(any.email).trim();
    }

    // 3. Profile email
    if (user.email && typeof user.email === 'string' && user.email.trim() !== '') {
        return user.email.trim();
    }

    // 4. No email
    return null;
}

function resolveRecipientEmailSource(user) {
    if (!user) return null;

    if (user.recipient_source === 'linked' || user.recipient_source === 'profile') {
        return user.recipient_source;
    }

    if (Array.isArray(user.email_accounts) && user.email_accounts.length > 0) {
        var hasEmail = user.email_accounts.some(function (a) {
            return a.email && String(a.email).trim() !== '';
        });
        if (hasEmail) return 'linked';
    }

    return user.email ? 'profile' : null;
}

// ========================================== //
// 📧 SYNC THE "TO" INPUT                      //
// ========================================== //
function syncRecipientField() {
    var toInput     = document.getElementById('toInput');
    var notice      = document.getElementById('toAutoFilledNotice');
    var noticeCount = document.getElementById('toAutoFilledCount');

    if (!toInput) return;

    if (selectedUsers.length === 0) {
        toInput.readOnly = false;
        toInput.style.opacity = '1';
        if (notice) notice.classList.add('hidden');
        return;
    }

    var emails = selectedUsers
        .map(function (u) { return resolveRecipientEmail(u); })
        .filter(function (e) { return !!e; });

    var seen = {};
    emails = emails.filter(function (e) {
        if (seen[e]) return false;
        seen[e] = true;
        return true;
    });

    toInput.value = emails.join(', ');
    toInput.readOnly = true;
    toInput.style.opacity = '0.85';

    if (notice && noticeCount) {
        noticeCount.textContent = emails.length;
        notice.classList.remove('hidden');
    }

    // ✅ Visible debug log (console.log, not console.debug)
    console.log('📧 syncRecipientField resolved:', selectedUsers.map(function (u) {
        return {
            id: u.id,
            name: u.name,
            profile_email: u.email,
            recipient_email: u.recipient_email,
            recipient_source: u.recipient_source,
            email_accounts: u.email_accounts,
            chosen: resolveRecipientEmail(u),
            resolved_source: resolveRecipientEmailSource(u),
        };
    }));
}

// ========================================== //
// 📝 RICH TEXT EDITOR                        //
// ========================================== //

function getActiveEditor() {
    return isFullscreen
        ? document.getElementById('fullscreenEditor')
        : document.getElementById('bodyEditor');
}

function executeFormattingCommand(command) {
    var editor = getActiveEditor();
    if (!editor) return;

    editor.focus();

    try {
        if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
            document.execCommand(command, false, null);
            setTimeout(function () {
                fixListStyling();
                updateTextStats();
                syncBodyToTextarea();
            }, 50);
        } else {
            document.execCommand(command, false, null);
        }

        updateTextStats();
        syncBodyToTextarea();
    } catch (e) {
        console.error('Command execution failed:', e);
        showNotification('Formatting command failed', 'error');
    }
}

function insertLink() {
    var url = prompt('Enter URL:', 'https://');
    if (url && url.trim()) {
        var editor = getActiveEditor();
        if (editor) {
            editor.focus();
            document.execCommand('createLink', false, url.trim());
            updateTextStats();
            syncBodyToTextarea();
            showNotification('Link inserted', 'success');
        }
    }
}

function insertImage() {
    var url = prompt('Enter image URL:', 'https://');
    if (url && url.trim()) {
        var editor = getActiveEditor();
        if (editor) {
            editor.focus();
            document.execCommand('insertImage', false, url.trim());
            updateTextStats();
            syncBodyToTextarea();
            showNotification('Image inserted', 'success');
        }
    }
}

function clearFormatting() {
    var editor = getActiveEditor();
    if (editor) {
        editor.focus();
        document.execCommand('removeFormat', false, null);
        updateTextStats();
        syncBodyToTextarea();
        showNotification('Formatting cleared', 'info');
    }
}

function syncBodyToTextarea() {
    var editor   = document.getElementById('bodyEditor');
    var textarea = document.getElementById('bodyInput');
    if (editor && textarea) {
        textarea.value = editor.innerHTML;
    }
}

function updateTextStats() {
    var editor = getActiveEditor();
    if (!editor) return;

    var text  = editor.innerText || '';
    var chars = text.length;
    var words = text.trim() ? text.trim().split(/\s+/).length : 0;
    var lines = text.split('\n').length;

    var charEl = document.getElementById('charCount');
    var wordEl = document.getElementById('wordCount');
    var lineEl = document.getElementById('lineCount');
    if (charEl) charEl.textContent = chars;
    if (wordEl) wordEl.textContent = words;
    if (lineEl) lineEl.textContent = lines;
}

function fixListStyling() {
    var editor = document.getElementById('bodyEditor');
    if (!editor) return;

    editor.querySelectorAll('ul').forEach(function (ul) {
        ul.style.cssText = 'padding-left: 24px !important; margin: 8px 0 !important; display: block !important; list-style-type: disc; list-style-position: outside;';
        ul.querySelectorAll('li').forEach(function (li) {
            li.style.cssText = 'margin: 4px 0 !important; display: list-item !important; list-style-type: disc;';
        });
    });

    editor.querySelectorAll('ol').forEach(function (ol) {
        ol.style.cssText = 'padding-left: 24px !important; margin: 8px 0 !important; display: block !important; list-style-type: decimal; list-style-position: outside;';
        ol.querySelectorAll('li').forEach(function (li) {
            li.style.cssText = 'margin: 4px 0 !important; display: list-item !important; list-style-type: decimal;';
        });
    });
}

// ========================================== //
// 🛠️ TOOLBAR + EDITOR EVENTS               //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toolbar-btn').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            var command = this.dataset.command;
            if (!command) return;

            switch (command) {
                case 'bold':
                case 'italic':
                case 'underline':
                case 'strikethrough':
                case 'insertUnorderedList':
                case 'insertOrderedList':
                case 'indent':
                case 'outdent':
                    executeFormattingCommand(command);
                    break;
                case 'link':            insertLink();           break;
                case 'image':           insertImage();          break;
                case 'clearFormatting': clearFormatting();      break;
                case 'fullscreen':      toggleFullscreen();     break;
                default: console.warn('Unknown command:', command);
            }
        });
    });

    var editor = document.getElementById('bodyEditor');
    if (editor) {
        editor.addEventListener('keydown', function (e) {
            if (e.ctrlKey) {
                switch (e.key.toLowerCase()) {
                    case 'b': e.preventDefault(); executeFormattingCommand('bold');      break;
                    case 'i': e.preventDefault(); executeFormattingCommand('italic');    break;
                    case 'u': e.preventDefault(); executeFormattingCommand('underline'); break;
                }
            }
            if (e.key === 'Enter') {
                setTimeout(fixListStyling, 10);
            }
        });

        editor.addEventListener('input', function () {
            updateTextStats();
            syncBodyToTextarea();
        });

        editor.addEventListener('paste', function () {
            setTimeout(function () {
                fixListStyling();
                updateTextStats();
                syncBodyToTextarea();
            }, 100);
        });
    }

    var fullscreenEditor = document.getElementById('fullscreenEditor');
    if (fullscreenEditor) {
        fullscreenEditor.addEventListener('input', function () {
            var mainEditor = document.getElementById('bodyEditor');
            if (mainEditor) {
                mainEditor.innerHTML = this.innerHTML;
                updateTextStats();
                syncBodyToTextarea();
            }
        });
    }

    var subjectInput   = document.getElementById('subjectInput');
    var subjectCounter = document.getElementById('subjectCounter');
    var subjectWarning = document.getElementById('subjectWarning');
    if (subjectInput && subjectCounter) {
        subjectInput.addEventListener('input', function () {
            subjectCounter.textContent = this.value.length + '/255';
            if (subjectWarning) {
                if (this.value.length > 78) subjectWarning.classList.remove('hidden');
                else                        subjectWarning.classList.add('hidden');
            }
        });
    }

    setTimeout(function () {
        updateTextStats();
        syncBodyToTextarea();
        fixListStyling();
    }, 100);
});

// ========================================== //
// 🔄 FULLSCREEN TOGGLE                       //
// ========================================== //

function toggleFullscreen() {
    isFullscreen = !isFullscreen;
    var overlay           = document.getElementById('fullscreenOverlay');
    var fullscreenEditor  = document.getElementById('fullscreenEditor');
    var mainEditor        = document.getElementById('bodyEditor');
    var icon              = document.getElementById('fullscreenIcon');

    if (isFullscreen) {
        overlay.classList.remove('hidden');
        overlay.style.display = 'block';
        if (fullscreenEditor && mainEditor) {
            fullscreenEditor.innerHTML = mainEditor.innerHTML;
        }
        if (fullscreenEditor) {
            setTimeout(function () { fullscreenEditor.focus(); }, 100);
        }
        if (icon) icon.className = 'fas fa-compress';
        document.body.style.overflow = 'hidden';
        document.body.classList.add('fullscreen-active');
    } else {
        overlay.classList.add('hidden');
        overlay.style.display = 'none';
        if (fullscreenEditor && mainEditor) {
            mainEditor.innerHTML = fullscreenEditor.innerHTML;
            syncBodyToTextarea();
            updateTextStats();
            fixListStyling();
        }
        if (icon) icon.className = 'fas fa-expand';
        document.body.style.overflow = 'auto';
        document.body.classList.remove('fullscreen-active');
    }
}

// ========================================== //
// 👥 USER MANAGEMENT                         //
// ========================================== //

function getUserTypeString(typeCode, roleSlug) {
    if (roleSlug && typeof roleSlug === 'string') {
        return roleSlug.replace(/-/g, '_');
    }

    var map = {
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

    return map[String(typeCode)] || 'unknown';
}

function formatUserType(type) {
    var types = {
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

    if (!type) return 'Unknown';

    if (!types[type]) {
        return String(type)
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    return types[type];
}

function loadUsers() {
    // ✅ Top-of-function marker — confirms this runs
    console.log('🚀 loadUsers() called');

    if (isLoadingUsers) {
        console.log('⏭️ loadUsers: already loading, skipping');
        return;
    }
    isLoadingUsers = true;

    var userList = document.getElementById('userList');

    // ✅ Guard against missing container
    if (!userList) {
        console.error('❌ loadUsers: #userList element not found in the DOM');
        isLoadingUsers = false;
        return;
    }

    userList.innerHTML = '<div class="text-center py-8 text-sm" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i> Loading users...</div>';

    console.log('🌐 loadUsers: fetching', userListUrl + '?limit=100');

    apiFetch(userListUrl + '?limit=100')
        .then(function (data) {
            isLoadingUsers = false;

            console.log('📥 loadUsers: response received', data);

            if (data.success) {
                allUsers = (data.users || []).filter(function (user) {
                    var t = parseInt(user.type, 10);
                    if (t === 5 || t === 7) return false;
                    if (!resolveRecipientEmail(user)) return false;
                    return true;
                });

                // ✅ Visible debug log with all user data
                console.log('📬 listEmail loaded:', allUsers.map(function (u) {
                    return {
                        id: u.id,
                        name: u.name,
                        email: u.email,
                        recipient_email: u.recipient_email,
                        recipient_source: u.recipient_source,
                        email_accounts: u.email_accounts,
                        resolved: resolveRecipientEmail(u),
                        resolved_source: resolveRecipientEmailSource(u),
                    };
                }));

                renderUserList();
            } else {
                console.warn('⚠️ loadUsers: API returned success=false', data);
                userList.innerHTML = '<div class="text-center py-8 text-sm" style="color: var(--danger);"><i class="fas fa-exclamation-circle mr-2"></i> ' + (data.message || 'Failed to load users') + '</div>';
            }
        })
        .catch(function (error) {
            isLoadingUsers = false;
            console.error('❌ loadUsers: fetch failed', error);
            userList.innerHTML = '<div class="text-center py-8 text-sm" style="color: var(--danger);"><i class="fas fa-exclamation-circle mr-2"></i> ' + escapeHtml(error.message) + '<br><button onclick="loadUsers()" class="mt-2 px-3 py-1 text-xs rounded transition" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"><i class="fas fa-sync mr-1"></i> Retry</button></div>';
        });
}

function renderUserList() {
    var userList   = document.getElementById('userList');
    var typeFilter = document.getElementById('userTypeFilter').value;
    var searchTerm = document.getElementById('userSearch').value.toLowerCase().trim();

    filteredUsers = allUsers.filter(function (user) {
        var userTypeString = getUserTypeString(
            user.type,
            user.primary_role || user.role_slug || null
        );
        if (typeFilter !== 'all' && userTypeString !== typeFilter) return false;
        if (searchTerm) {
            var name  = (user.name  || '').toLowerCase();
            var email = (resolveRecipientEmail(user) || '').toLowerCase();
            if (!name.includes(searchTerm) && !email.includes(searchTerm)) return false;
        }
        return true;
    });

    document.getElementById('visibleCount').textContent = filteredUsers.length;

    if (filteredUsers.length === 0) {
        userList.innerHTML = '<div class="text-center py-8 text-sm" style="color: var(--text-secondary);"><i class="fas fa-user-slash mr-2"></i> No users with an email address found</div>';
        return;
    }

    var html = '';
    filteredUsers.forEach(function (user) {
        var isSelected    = selectedUsers.some(function (u) { return u.id === user.id; });
        var resolvedEmail = resolveRecipientEmail(user);
        var resolvedSource = resolveRecipientEmailSource(user);
        var userTypeString = getUserTypeString(
            user.type,
            user.primary_role || user.role_slug || null
        );
        var userTypeLabel = formatUserType(userTypeString);

        var emailSourceBadge = '';
        if (resolvedSource === 'linked') {
            emailSourceBadge = '<span class="text-[10px] ml-1 px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);" title="From linked email account"><i class="fas fa-link mr-0.5"></i>linked</span>';
        } else if (resolvedSource === 'profile') {
            emailSourceBadge = '<span class="text-[10px] ml-1 px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.15); color: var(--text-secondary);" title="From profile email"><i class="fas fa-user mr-0.5"></i>profile</span>';
        }

        html += '<div class="flex items-center justify-between p-3 transition select-user-item" ' +
                'onclick="toggleUser(' + user.id + ')" ' +
                'style="' + (isSelected ? 'background-color: rgba(var(--primary-rgb), 0.05);' : '') + '">' +
            '<div class="flex items-center gap-3 min-w-0 flex-1">' +
                '<input type="checkbox" ' +
                       (isSelected ? 'checked ' : '') +
                       'class="rounded transition-all user-checkbox" ' +
                       'style="accent-color: var(--primary);" ' +
                       'onclick="event.stopPropagation(); toggleUser(' + user.id + ')"' +
                '>' +
                '<div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0" ' +
                     'style="background: linear-gradient(135deg, ' + getColorForUser(user) + ', ' + getColorForUser(user) + ');">' +
                    getInitials(user.name || 'U') +
                '</div>' +
                '<div class="min-w-0 flex-1">' +
                    '<div class="text-sm font-medium truncate" style="color: var(--text-primary);">' +
                        escapeHtml(user.name || 'Unknown') +
                        (user.status === 'active' ? '<span class="text-xs ml-1 px-1.5 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">● Active</span>' : '') +
                    '</div>' +
                    '<div class="text-xs truncate" style="color: var(--text-secondary);">' +
                        escapeHtml(resolvedEmail) + emailSourceBadge +
                        '<span class="ml-2">• ' + userTypeLabel + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="flex items-center gap-2 flex-shrink-0">' +
                (isSelected ? '<span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">Selected</span>' : '') +
            '</div>' +
        '</div>';
    });

    userList.innerHTML = html;
    updateSelectedCount();
    updateRecipientIds();
}

function toggleUser(userId) {
    var user = allUsers.find(function (u) { return u.id === userId; });
    if (!user) return;

    if (!resolveRecipientEmail(user)) {
        showNotification('This user does not have a linked account or a profile email', 'warning');
        return;
    }

    var index = selectedUsers.findIndex(function (u) { return u.id === userId; });
    if (index > -1) selectedUsers.splice(index, 1);
    else            selectedUsers.push(user);

    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
}

function selectAllUsers() {
    var usersWithEmail = filteredUsers.filter(function (u) { return !!resolveRecipientEmail(u); });
    usersWithEmail.forEach(function (user) {
        if (!selectedUsers.some(function (u) { return u.id === user.id; })) selectedUsers.push(user);
    });
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
    showNotification('Selected ' + usersWithEmail.length + ' users', 'success');
}

function deselectAllUsers() {
    selectedUsers = [];
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
    showNotification('All users deselected', 'info');
}

function selectUsersWithEmail() {
    var usersWithEmail = filteredUsers.filter(function (u) { return !!resolveRecipientEmail(u); });
    selectedUsers = usersWithEmail;
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
    showNotification('Selected ' + usersWithEmail.length + ' users with email', 'success');
}

function addUserType(type) {
    var typeUsers = allUsers.filter(function (u) {
        var resolved = getUserTypeString(
            u.type,
            u.primary_role || u.role_slug || null
        );
        return resolved === type && !!resolveRecipientEmail(u);
    });

    typeUsers.forEach(function (user) {
        if (!selectedUsers.some(function (u) { return u.id === user.id; })) selectedUsers.push(user);
    });

    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();

    if (typeUsers.length === 0) {
        showNotification('No ' + formatUserType(type) + ' users with email found', 'info');
    } else {
        showNotification('Added ' + typeUsers.length + ' ' + formatUserType(type) + (typeUsers.length > 1 ? 's' : ''), 'success');
    }
}

function filterUsers() { renderUserList(); }

function updateSelectedUsersTags() {
    var container = document.getElementById('selectedUsersTags');
    if (!container) return;
    if (selectedUsers.length === 0) { container.innerHTML = ''; return; }

    var html = '';
    selectedUsers.slice(0, 10).forEach(function (user) {
        var resolvedEmail = resolveRecipientEmail(user);
        var label = user.name || resolvedEmail;

        html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); border: 1px solid rgba(var(--primary-rgb), 0.2);" title="' + escapeHtml(resolvedEmail) + '">' +
            escapeHtml(label) +
            '<button type="button" onclick="removeUser(' + user.id + ')" class="hover:text-red-500 transition" style="color: var(--text-secondary);"><i class="fas fa-times"></i></button>' +
        '</span>';
    });
    if (selectedUsers.length > 10) {
        html += '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">+' + (selectedUsers.length - 10) + ' more</span>';
    }
    container.innerHTML = html;
}

function removeUser(userId) {
    selectedUsers = selectedUsers.filter(function (u) { return u.id !== userId; });
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
}

function updateRecipientIds() {
    var ids = selectedUsers.map(function (u) { return u.id; });
    document.getElementById('recipientIds').value = ids.join(',');
    document.getElementById('isBulk').value = selectedUsers.length > 0 ? '1' : '0';

    syncRecipientField();
}

function updateSelectedCount() {
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = selectedUsers.length;
}

function updateSendButtonText() {
    var button = document.getElementById('sendButtonText');
    if (!button) return;
    button.textContent = selectedUsers.length > 0
        ? 'Send to ' + selectedUsers.length + ' recipient' + (selectedUsers.length > 1 ? 's' : '')
        : 'Send';
}

function getInitials(name) {
    if (!name) return 'U';
    var parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function getColorForUser(user) {
    var colors = ['#4F46E5', '#7C3AED', '#EC4899', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6'];
    return colors[(user.id || 0) % colors.length];
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// 📎 ATTACHMENTS                             //
// ========================================== //

var totalFileSize = 0;
var MAX_FILE_SIZE = 10 * 1024 * 1024;

function handleFiles(files) {
    var fileList = document.getElementById('fileList');
    totalFileSize = 0;
    fileList.innerHTML = '';

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        totalFileSize += file.size;
        var item = document.createElement('div');
        item.className = 'flex items-center justify-between p-2 rounded-lg text-sm';
        item.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.05)';
        var size = (file.size / 1024 / 1024).toFixed(2);
        var icon = file.type.startsWith('image/') ? 'fa-file-image' : 'fa-file';
        item.innerHTML = '<div class="flex items-center gap-3 min-w-0 flex-1"><i class="fas ' + icon + '" style="color: var(--primary);"></i><span class="truncate" style="color: var(--text-primary);">' + escapeHtml(file.name) + '</span><span class="text-xs flex-shrink-0" style="color: var(--text-secondary);">' + size + ' MB</span></div><button type="button" onclick="this.closest(\'div\').remove(); updateTotalFileSize()" class="flex-shrink-0 ml-2" style="color: var(--danger);"><i class="fas fa-times"></i></button>';
        fileList.appendChild(item);
    }

    updateTotalFileSize();
}

function updateTotalFileSize() {
    var warning = document.getElementById('fileSizeWarning');
    var fileItems = document.querySelectorAll('#fileList > div');
    var total = 0;
    fileItems.forEach(function (item) {
        var sizeText = item.querySelector('.text-xs')?.textContent || '0 MB';
        total += parseFloat(sizeText) || 0;
    });
    totalFileSize = total * 1024 * 1024;

    if (warning) {
        if (totalFileSize > MAX_FILE_SIZE) warning.classList.remove('hidden');
        else                                warning.classList.add('hidden');
    }
}

// ========================================== //
// 💾 DRAFT & AUTO-SAVE                       //
// ========================================== //

function startAutoSave() {
    if (autoSaveTimer) clearInterval(autoSaveTimer);
    autoSaveTimer = setInterval(saveDraft, autoSaveInterval * 1000);
}

function saveDraft() {
    var form = document.getElementById('composeForm');
    if (!form) return;
    var formData = new FormData(form);
    var editor = document.getElementById('bodyEditor');
    var data = {
        to:            formData.get('to')            || '',
        cc:            formData.get('cc')            || '',
        bcc:           formData.get('bcc')           || '',
        subject:       formData.get('subject')       || '',
        body:          editor ? editor.innerHTML : '',
        priority:      formData.get('priority')      || 'normal',
        recipient_ids: formData.get('recipient_ids') || '',
        is_bulk:       formData.get('is_bulk')       || '0'
    };

    var draftKey = 'email_draft_' + Date.now();
    try {
        localStorage.setItem(draftKey, JSON.stringify(data));
        var keys = Object.keys(localStorage).filter(function (k) { return k.startsWith('email_draft_'); });
        if (keys.length > 5) {
            keys.sort().slice(0, keys.length - 5).forEach(function (k) { localStorage.removeItem(k); });
        }
        updateDraftStatus(true);
    } catch (e) {
        console.error('Failed to save draft:', e);
        updateDraftStatus(false);
    }
}

function updateDraftStatus(success) {
    var status = document.getElementById('draftStatus');
    var time   = document.getElementById('lastSavedTime');
    if (time) time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (status) {
        status.innerHTML = success
            ? '<i class="fas fa-circle text-[6px] mr-1" style="color: var(--success);"></i> Draft saved'
            : '<i class="fas fa-exclamation-circle text-[6px] mr-1" style="color: var(--danger);"></i> Auto-save failed';
    }
}

function loadDrafts() {
    var keys = Object.keys(localStorage).filter(function (k) { return k.startsWith('email_draft_'); }).sort().reverse();
    if (keys.length === 0) return;
    var latest = localStorage.getItem(keys[0]);
    if (!latest) return;
    try {
        var data = JSON.parse(latest);
        if (confirm('You have a saved draft. Load it?')) {
            document.querySelector('[name="to"]').value      = data.to      || '';
            document.querySelector('[name="cc"]').value      = data.cc      || '';
            document.querySelector('[name="bcc"]').value     = data.bcc     || '';
            document.querySelector('[name="subject"]').value = data.subject || '';
            var editor = document.getElementById('bodyEditor');
            if (editor && data.body) {
                editor.innerHTML = data.body;
                syncBodyToTextarea();
                updateTextStats();
                fixListStyling();
            }
            var priority = document.querySelector('[name="priority"]');
            if (priority && data.priority) priority.value = data.priority;
            showNotification('Draft loaded successfully', 'success');
        }
    } catch (e) {
        console.error('Failed to load draft:', e);
    }
}

// ========================================== //
// 📊 QUOTA UPDATES                           //
// ========================================== //

function updateQuota(accountId) {
    var select = document.querySelector('[name="email_account_id"]');
    if (!select) return;
    var selected = select.options[select.selectedIndex];
    var sent  = parseInt(selected.dataset.sent)  || 0;
    var limit = parseInt(selected.dataset.limit) || 500;
    var percentage = Math.min(100, (sent / limit) * 100);

    document.getElementById('quotaText').textContent      = sent + ' / ' + limit;
    document.getElementById('quotaBar').style.width       = percentage + '%';
    document.getElementById('remainingQuota').textContent = (limit - sent) + ' remaining';

    var status = document.getElementById('quotaStatus');
    if (sent >= limit) {
        status.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-0.5"></i> Limit reached';
        document.querySelector('[type="submit"]').disabled = true;
    } else if (percentage > 80) {
        status.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500 mr-0.5"></i> Approaching limit';
        document.querySelector('[type="submit"]').disabled = false;
    } else {
        status.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-0.5"></i> Available';
        document.querySelector('[type="submit"]').disabled = false;
    }
}

// ========================================== //
// 🔄 FORM RESET                              //
// ========================================== //

function resetForm() {
    if (!confirm('Are you sure you want to reset the form?')) return;
    document.getElementById('composeForm').reset();
    var editor = document.getElementById('bodyEditor');
    if (editor) { editor.innerHTML = ''; syncBodyToTextarea(); updateTextStats(); }
    document.getElementById('fileList').innerHTML = '';
    document.getElementById('fileSizeWarning').classList.add('hidden');
    document.getElementById('toSuggestions').classList.add('hidden');

    var toInput = document.getElementById('toInput');
    if (toInput) {
        toInput.value = '';
        toInput.readOnly = false;
        toInput.style.opacity = '1';
    }
    var notice = document.getElementById('toAutoFilledNotice');
    if (notice) notice.classList.add('hidden');

    selectedUsers = [];
    renderUserList();
    updateSelectedUsersTags();
    updateRecipientIds();
    updateSendButtonText();
    showNotification('Form reset', 'info');
}

// ========================================== //
// 🚀 FORM SUBMIT                             //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('composeForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        syncBodyToTextarea();

        var sendBtn = document.getElementById('sendButton');
        var sendBtnText = document.getElementById('sendButtonText');
        var originalText = sendBtnText ? sendBtnText.textContent : 'Send';

        if (sendBtn) {
            sendBtn.disabled = true;
            if (sendBtnText) sendBtnText.textContent = 'Sending…';
        }

        var formData = new FormData(form);

        fetch(sendUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        })
        .then(async function (response) {
            const text = await response.text();
            let data;
            try {
                const clean = text.replace(/^\uFEFF/, '').replace(/^\uFFFE/, '');
                data = clean ? JSON.parse(clean) : {};
            } catch (parseErr) {
                console.error('Non-JSON response from send:', text.slice(0, 300));
                throw new Error('Server returned non-JSON response (HTTP ' + response.status + ')');
            }

            if (!response.ok) {
                let msg = data.message || ('HTTP ' + response.status);
                if (data.errors) {
                    msg += ': ' + Object.entries(data.errors)
                        .map(([k, v]) => k + ': ' + (Array.isArray(v) ? v.join(', ') : v))
                        .join('; ');
                }
                throw new Error(msg);
            }

            return data;
        })
        .then(function (data) {
            if (data.success) {
                showNotification(data.message || 'Email sent successfully!', 'success');
                Object.keys(localStorage)
                    .filter(k => k.startsWith('email_draft_'))
                    .forEach(k => localStorage.removeItem(k));

                setTimeout(function () {
                    window.location.href = '{{ route($routeNamePrefix . ".sent") }}';
                }, 1500);
            } else {
                showNotification(data.message || 'Failed to send email', 'error');
                if (sendBtn) sendBtn.disabled = false;
                if (sendBtnText) sendBtnText.textContent = originalText;
            }
        })
        .catch(function (error) {
            console.error('Send error:', error);
            showNotification('Send failed: ' + error.message, 'error');
            if (sendBtn) sendBtn.disabled = false;
            if (sendBtnText) sendBtnText.textContent = originalText;
        });
    });
});

// ========================================== //
// 🔍 EMAIL SUGGESTIONS                       //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    var toInput = document.getElementById('toInput');
    if (!toInput) return;

    var suggestionTimeout = null;
    toInput.addEventListener('input', function () {
        if (this.readOnly) return;

        clearTimeout(suggestionTimeout);
        var query = this.value.trim();
        var suggestions = document.getElementById('toSuggestions');
        if (query.length < 2) { suggestions.classList.add('hidden'); return; }

        suggestionTimeout = setTimeout(function () {
            var mockSuggestions = [query + '@example.com', query + '@gmail.com', query + '@outlook.com'];
            suggestions.innerHTML = '';
            mockSuggestions.forEach(function (email) {
                var item = document.createElement('div');
                item.className = 'px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition';
                item.textContent = email;
                item.onclick = function () { toInput.value = email; suggestions.classList.add('hidden'); };
                suggestions.appendChild(item);
            });
            suggestions.classList.remove('hidden');
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#toInput') && !e.target.closest('#toSuggestions')) {
            document.getElementById('toSuggestions').classList.add('hidden');
        }
    });
});

// ========================================== //
// 💬 NOTIFICATIONS                           //
// ========================================== //

function showNotification(message, type) {
    type = type || 'success';
    document.querySelectorAll('.custom-notification').forEach(function (n) { n.remove(); });

    var colors = {
        success: { bg: '#22c55e', icon: 'fa-check-circle' },
        error:   { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info:    { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    var color = colors[type] || colors.info;

    var notification = document.createElement('div');
    notification.className = 'custom-notification fixed top-4 right-4 z-[99999] px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white';
    notification.style.backgroundColor = color.bg;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = '<div class="flex items-center"><i class="fas ' + color.icon + ' mr-2 text-lg"></i><span class="text-sm">' + escapeHtml(message) + '</span><button onclick="this.closest(\'.custom-notification\').remove()" class="ml-3 text-white hover:text-gray-200"><i class="fas fa-times"></i></button></div>';
    document.body.appendChild(notification);

    setTimeout(function () {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(function () { notification.remove(); }, 300);
    }, 5000);
}

// ========================================== //
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //

document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('composeForm')?.requestSubmit();
    }
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveDraft();
        showNotification('Draft saved!', 'success');
    }
    if (e.key === 'Escape' && isFullscreen) toggleFullscreen();
});

// ========================================== //
// 📋 LOAD ON PAGE READY                      //
// ========================================== //

document.addEventListener('DOMContentLoaded', function () {
    console.log('🎬 DOMContentLoaded fired (compose loader)');

    @if($canSelectUsers)
        console.log('👥 loadUsers will run in 500ms');
        setTimeout(loadUsers, 500);
    @else
        console.log('⏭️ loadUsers skipped (canSelectUsers is false)');
    @endif

    setTimeout(loadDrafts, 500);
    if (document.getElementById('composeForm')) startAutoSave();

    syncRecipientField();
});

// ========================================== //
// 🎨 STYLES                                  //
// ========================================== //

var style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    .toolbar-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 6px; transition: all 0.15s ease;
        cursor: pointer; border: 1px solid transparent; font-size: 13px;
        background: transparent; color: var(--text-secondary); user-select: none;
    }
    .toolbar-btn:hover {
        background-color: rgba(var(--secondary-rgb), 0.12);
        color: var(--text-primary); border-color: rgba(var(--secondary-rgb), 0.2);
    }
    .toolbar-btn:active { transform: scale(0.92); background-color: rgba(var(--primary-rgb), 0.15); }

    #bodyEditor, #fullscreenEditor {
        line-height: 1.7; min-height: 300px; outline: none; font-family: inherit;
    }
    #bodyEditor:focus, #fullscreenEditor:focus { outline: 2px solid var(--primary); outline-offset: 0px; }

    #bodyEditor ul, #bodyEditor ol, #fullscreenEditor ul, #fullscreenEditor ol {
        padding-left: 24px !important;
        margin: 8px 0 !important;
        display: block !important;
    }
    #bodyEditor ul, #fullscreenEditor ul { list-style-type: disc !important; list-style-position: outside !important; }
    #bodyEditor ol, #fullscreenEditor ol { list-style-type: decimal !important; list-style-position: outside !important; }
    #bodyEditor li, #fullscreenEditor li { margin: 4px 0 !important; display: list-item !important; }
    #bodyEditor ul li, #fullscreenEditor ul li { list-style-type: disc !important; }
    #bodyEditor ol li, #fullscreenEditor ol li { list-style-type: decimal !important; }
    #bodyEditor p, #fullscreenEditor p { margin: 0 0 8px 0; }
    #bodyEditor a, #fullscreenEditor a { color: var(--primary); text-decoration: underline; }

    #fullscreenOverlay { z-index: 99999 !important; position: fixed !important; inset: 0 !important; }
    #fullscreenOverlay > div { z-index: 100000 !important; }
    #fullscreenOverlay.hidden { display: none !important; }

    body.fullscreen-active { overflow: hidden !important; }

    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    #fullscreenOverlay { animation: fadeIn 0.2s ease-out; }

    #toInput[readonly] {
        background-color: rgba(var(--info-rgb), 0.04) !important;
        cursor: default;
    }
`;
document.head.appendChild(style);

console.log('✅ Compose Email Page Loaded');
console.log('📧 Route Prefix:', routePrefix);
console.log('🔗 Send URL:', sendUrl);
console.log('👥 User list URL:', userListUrl);
console.log('👥 User selection enabled:', {{ $canSelectUsers ? 'true' : 'false' }});
</script>
@endsection